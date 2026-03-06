"""
Model API Endpoints.
"""
from fastapi import APIRouter, Depends, HTTPException, status, WebSocket, WebSocketDisconnect
from sqlalchemy.orm import Session
from typing import List, Dict

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

@router.get("/available", response_model=List[Dict])
async def list_available_models(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """List all models available for download and their download status."""
    models = get_available_models()
    
    # Check actual download status with integrity checker
    result = []
    for model in models:
        m = dict(model)
        
        # Use integrity checker for accurate status
        integrity_result = verify_model_files(m["id"])
        m["status"] = integrity_result.status.value
        m["is_downloaded"] = integrity_result.is_healthy
        m["integrity_details"] = {
            "missing_files": integrity_result.missing_files,
            "corrupted_files": integrity_result.corrupted_files,
            "total_size_mb": round(integrity_result.total_size_mb, 2)
        }
        
        # Also check DB status for downloading state
        download = DownloadManager.get_download(db, m["id"])
        if download and download.status == "downloading":
            m["status"] = "downloading"
            m["download_progress"] = download.progress_pct
                
        result.append(m)
        
    return result


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

from fastapi import BackgroundTasks
import asyncio

async def real_download_task(model_id: str, user_id: str = None):
    """Real background task to download a model using huggingface_hub."""
    from app.downloader.model_registry import get_model_info
    from app.config import settings
    import os
    
    model_info = get_model_info(model_id)
    if not model_info or not model_info.get("repo_id"):
        return
        
    repo_id = model_info["repo_id"]
    local_dir = os.path.join(settings.MODELS_DIR, model_id)
    
    # Notify download started via WebSocket
    if user_id:
        await notification_manager.notify_download_started(
            user_id, model_id, model_info.get("name", model_id)
        )
    
    # Run the blocking download in a thread to not block the event loop
    loop = asyncio.get_event_loop()
    try:
        await loop.run_in_executor(
            None, 
            download_hf_model, 
            SessionLocal, 
            model_id, 
            repo_id, 
            local_dir,
            user_id
        )
        
        # Verify the download
        integrity_result = verify_model_files(model_id)
        if not integrity_result.is_healthy:
            # Update DB to reflect the issue
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
        else:
            if user_id:
                await notification_manager.notify_download_completed(
                    user_id, model_id, model_info.get("name", model_id)
                )
                
    except Exception as e:
        logger.error(f"Download task failed for {model_id}: {e}")
        if user_id:
            await notification_manager.notify_download_failed(
                user_id, model_id, model_info.get("name", model_id), str(e)
            )

@router.post("/download/{model_id}", response_model=ModelDownloadResponse)
async def request_model_download(
    model_id: str,
    background_tasks: BackgroundTasks,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Request a model to be downloaded."""
    try:
        # First verify current status
        integrity_result = verify_model_files(model_id)
        if integrity_result.is_healthy:
            raise HTTPException(status_code=400, detail="Model is already downloaded and healthy")
        
        download = DownloadManager.request_download(db, model_id)
        
        if download.status == "pending":
            # Schedule the real download task with user_id for notifications
            background_tasks.add_task(real_download_task, model_id, str(current_user.id))
            
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
    return None
