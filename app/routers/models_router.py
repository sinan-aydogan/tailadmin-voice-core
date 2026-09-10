"""
Model API Endpoints.
"""
import asyncio
import time
from fastapi import APIRouter, Depends, HTTPException, status, WebSocket, WebSocketDisconnect
from sqlalchemy.orm import Session
from typing import List, Dict
from loguru import logger

from app.database import get_db
from app.auth.dependencies import get_current_user
from app.models.user import User
from app.schemas.model import ModelDownloadResponse
from app.downloader.download_manager import DownloadManager
from app.downloader.model_registry import get_available_models, get_model_info
from app.downloader.download_utils import download_hf_model
from app.downloader.integrity_checker import (
    verify_model_files, is_model_healthy, get_model_status, 
    get_ready_for_tts, get_ready_for_stt, ModelStatus
)
from app.database import SessionLocal, get_db
from app.websocket import manager, notification_manager

router = APIRouter(prefix="/models", tags=["models"])

# Short-lived cache for the (relatively expensive) integrity scan. The UI polls
# /models/available frequently; without this every poll re-walks every model dir.
_AVAILABLE_CACHE_TTL = 10.0  # seconds
_available_cache = {"ts": 0.0, "data": None}
_available_lock = asyncio.Lock()


async def _scan_models() -> List[Dict]:
    """Run integrity checks for all models off the event loop, concurrently."""
    models = get_available_models()

    def _check(model_id):
        try:
            return verify_model_files(model_id)
        except Exception as e:
            logger.error(f"Error checking model {model_id}: {e}")
            return None

    # asyncio.to_thread keeps the event loop responsive; gather runs them together.
    integrity_results = await asyncio.gather(
        *[asyncio.to_thread(_check, m["id"]) for m in models]
    )

    result = []
    for model, integrity_result in zip(models, integrity_results):
        m = dict(model)
        if integrity_result:
            m["status"] = integrity_result.status.value
            m["is_downloaded"] = integrity_result.is_healthy
            m["integrity_details"] = {
                "missing_files": integrity_result.missing_files,
                "corrupted_files": integrity_result.corrupted_files,
                "total_size_mb": round(integrity_result.total_size_mb, 2),
            }
        else:
            m["status"] = "not_downloaded"
            m["is_downloaded"] = False
            m["integrity_details"] = {}
        result.append(m)
    return result


@router.get("/available", response_model=List[Dict])
async def list_available_models(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """List all models available for download and their download status."""
    now = time.monotonic()

    # Serve cached scan if fresh; otherwise rescan (single-flight via lock).
    async with _available_lock:
        if _available_cache["data"] is None or now - _available_cache["ts"] > _AVAILABLE_CACHE_TTL:
            _available_cache["data"] = await _scan_models()
            _available_cache["ts"] = now

    # Copy cached scan and overlay live DB download state (cheap, always fresh).
    result = []
    for cached in _available_cache["data"]:
        m = dict(cached)
        model_id = m["id"]
        download = DownloadManager.get_download(db, model_id)

        if m.get("is_downloaded"):
            # Reconcile DB with reality if the files are healthy but DB lags.
            if download and download.status != "completed":
                DownloadManager.update_progress(db, model_id, "completed", progress=100.0)
        if download and download.status == "downloading":
            m["status"] = "downloading"
            m["download_progress"] = download.progress_pct
        elif download and download.status == "completed":
            m["status"] = "downloaded"
            m["is_downloaded"] = True

        result.append(m)

    return result


def _invalidate_available_cache():
    """Force the next /models/available call to rescan (after download/delete)."""
    _available_cache["data"] = None


@router.get("/ready-for-tts", response_model=List[str])
async def list_ready_tts_models(
    current_user: User = Depends(get_current_user)
):
    """List model IDs that are ready for TTS usage (downloaded and verified)."""
    return get_ready_for_tts()


@router.get("/ready-for-stt", response_model=List[str])
async def list_ready_stt_models(
    current_user: User = Depends(get_current_user)
):
    """List model IDs that are ready for STT usage (downloaded and verified)."""
    return get_ready_for_stt()


@router.post("/verify/{model_id}")
async def verify_model(
    model_id: str,
    current_user: User = Depends(get_current_user)
):
    """Manually verify a model's integrity."""
    model_info = get_model_info(model_id)
    if not model_info:
        raise HTTPException(status_code=404, detail="Model not found")
    
    result = verify_model_files(model_id)
    
    return {
        "model_id": model_id,
        "status": result.status.value,
        "is_healthy": result.is_healthy,
        "missing_files": result.missing_files,
        "corrupted_files": result.corrupted_files,
        "total_size_mb": round(result.total_size_mb, 2),
        "details": result.details
    }

@router.get("/downloads", response_model=List[ModelDownloadResponse])
async def list_downloads(
    skip: int = 0, 
    limit: int = 100,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """List all active or completed model downloads."""
    return DownloadManager.get_all_downloads(db, skip=skip, limit=limit)

from concurrent.futures import ThreadPoolExecutor

# Dedicated thread pool for model downloads (doesn't block TTS worker)
_download_executor = ThreadPoolExecutor(max_workers=2, thread_name_prefix="model_download")

# Strong references to in-flight download tasks so they aren't garbage-collected
# mid-run (asyncio only holds weak refs), and so we don't start two for one model.
_download_tasks: Dict[str, "asyncio.Task"] = {}

async def real_download_task(model_id: str, user_id: str = None):
    """Background task to download a model.

    started / progress / completed / failed WebSocket notifications are owned by
    `download_hf_model` (via the byte-accurate poller); here we only add a
    post-download integrity gate that can override a spurious "completed".
    """
    from app.downloader.model_registry import get_model_info
    from app.config import settings
    import os

    model_info = get_model_info(model_id)
    if not model_info or not model_info.get("repo_id"):
        return

    repo_id = model_info["repo_id"]
    local_dir = os.path.join(settings.MODELS_DIR, model_id)

    # Run the blocking download in a dedicated thread pool (doesn't block TTS worker)
    loop = asyncio.get_running_loop()
    try:
        await loop.run_in_executor(
            _download_executor,
            download_hf_model,
            SessionLocal,
            model_id,
            repo_id,
            local_dir,
            user_id,
        )

        # Authoritative gate: verify files after download completes.
        integrity_result = verify_model_files(model_id)
        if not integrity_result.is_healthy:
            db = SessionLocal()
            try:
                DownloadManager.update_progress(
                    db, model_id, "failed",
                    error=f"Download incomplete. Missing: {integrity_result.missing_files}"
                )
            finally:
                db.close()
            if user_id:
                await notification_manager.notify_download_failed(
                    user_id, model_id, model_info.get("name", model_id),
                    f"Dosyalar eksik: {', '.join(integrity_result.missing_files)}"
                )
    except Exception as e:
        logger.error(f"Download task failed for {model_id}: {e}")
        if user_id:
            await notification_manager.notify_download_failed(
                user_id, model_id, model_info.get("name", model_id), str(e)
            )
    finally:
        _invalidate_available_cache()
        _download_tasks.pop(model_id, None)

@router.post("/download/{model_id}", response_model=ModelDownloadResponse)
async def request_model_download(
    model_id: str,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Request a model to be downloaded."""
    try:
        # First verify current status
        integrity_result = verify_model_files(model_id)
        if integrity_result.is_healthy:
            raise HTTPException(status_code=400, detail="Model is already downloaded and healthy")

        if model_id in _download_tasks and not _download_tasks[model_id].done():
            raise HTTPException(status_code=409, detail="Model zaten indiriliyor")

        download = DownloadManager.request_download(db, model_id)

        if download.status == "pending":
            # Start download in background, keeping a strong reference (R7).
            task = asyncio.create_task(real_download_task(model_id, str(current_user.id)))
            _download_tasks[model_id] = task

        return download
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

@router.get("/download/{model_id}", response_model=ModelDownloadResponse)
async def check_download_status(
    model_id: str,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Check the status of a specific model download."""
    download = DownloadManager.get_download(db, model_id)
    if not download:
        raise HTTPException(status_code=404, detail="Download record not found")
    return download

@router.delete("/{model_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_model(
    model_id: str,
    remove_files: bool = True,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Delete a downloaded model record and optionally its files."""
    if not DownloadManager.delete_model(db, model_id, remove_files=remove_files):
        raise HTTPException(status_code=404, detail="Model record not found")
    _invalidate_available_cache()
    return None
