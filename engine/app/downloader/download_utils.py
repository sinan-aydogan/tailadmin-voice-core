"""
Utilities for downloading models from HuggingFace.

Progress is tracked with a byte-accurate disk poller (`_ProgressPoller`) rather than
by hooking tqdm. The poller knows the repo's total byte size up-front (from HF
metadata) and samples the actual on-disk size of `local_dir`, so the reported
percentage is a real fraction of the whole download and is isolated per model
(no cross-download race). It writes throttled updates to the DB and pushes live
progress to the UI over WebSocket.
"""
import os
import time
import threading
from typing import Optional
from huggingface_hub import snapshot_download
from loguru import logger

from app.config import settings
from app.downloader.download_manager import DownloadManager
from app.downloader.model_registry import get_model_info


def _dir_size(path: str) -> int:
    """Total size in bytes of all files under `path` (0 if missing)."""
    total = 0
    if not os.path.isdir(path):
        return 0
    for root, _dirs, files in os.walk(path):
        for name in files:
            fp = os.path.join(root, name)
            try:
                total += os.path.getsize(fp)
            except OSError:
                pass
    return total


def _compute_repo_total_bytes(repo_id: str, token: Optional[str]) -> int:
    """Best-effort total download size for a HF repo, in bytes.

    Uses file metadata from the Hub API. Returns 0 if it can't be determined so
    callers can fall back to a size estimate.
    """
    try:
        from huggingface_hub import HfApi
        api = HfApi()
        info = api.model_info(repo_id, files_metadata=True, token=token)
        total = 0
        for sibling in (info.siblings or []):
            size = getattr(sibling, "size", None)
            if size:
                total += size
        return total
    except Exception as e:
        logger.warning(f"Could not compute repo size for {repo_id}: {e}")
        return 0


class _ProgressPoller(threading.Thread):
    """Background thread that samples on-disk download size and reports progress.

    One poller per download => no shared/global state => no cross-download race.
    """

    def __init__(self, model_id: str, model_name: str, local_dir: str,
                 total_bytes: int, db_factory, user_id: Optional[str] = None,
                 interval: float = 1.0):
        super().__init__(daemon=True)
        self.model_id = model_id
        self.model_name = model_name
        self.local_dir = local_dir
        self.total_bytes = total_bytes
        self.db_factory = db_factory
        self.user_id = user_id
        self.interval = interval
        self._stop = threading.Event()
        self._last_db_pct = -100.0
        self._last_ws_pct = -100.0

    def stop(self):
        self._stop.set()

    def _report(self, downloaded: int, force: bool = False):
        if self.total_bytes > 0:
            pct = min(99.0, (downloaded / self.total_bytes) * 100.0)
        else:
            pct = 0.0

        # DB: write at most every ~2%
        if force or pct - self._last_db_pct >= 2.0:
            self._last_db_pct = pct
            try:
                with self.db_factory() as db:
                    DownloadManager.update_progress(
                        db, self.model_id, "downloading",
                        progress=round(pct, 1),
                        downloaded_bytes=downloaded,
                        total_bytes=self.total_bytes or None,
                    )
            except Exception as e:
                logger.debug(f"Progress DB update failed for {self.model_id}: {e}")

        # WebSocket: push at most every ~1%
        if self.user_id and (force or pct - self._last_ws_pct >= 1.0):
            self._last_ws_pct = pct
            try:
                from app.websocket import notification_manager
                notification_manager.notify_download_progress_threadsafe(
                    self.user_id, self.model_id, self.model_name, round(pct, 1)
                )
            except Exception as e:
                logger.debug(f"Progress WS push failed for {self.model_id}: {e}")

    def run(self):
        while not self._stop.is_set():
            downloaded = _dir_size(self.local_dir)
            self._report(downloaded)
            self._stop.wait(self.interval)


def download_piper_model(model_id: str, model_info: dict, local_dir: str, db_factory, user_id: str = None):
    """Download Piper TTS model files (.onnx and .onnx.json) atomically."""
    import requests
    from pathlib import Path
    from app.websocket import notification_manager

    model_name = model_info.get("name", model_id)
    download_url = model_info.get("download_url")
    json_url = model_info.get("json_url")

    if not download_url:
        logger.error(f"No download_url for Piper model {model_id}")
        return

    if not json_url:
        json_url = download_url.replace(".onnx", ".onnx.json")

    Path(local_dir).mkdir(parents=True, exist_ok=True)

    def _download_file(url: str, dest: str, timeout: int, on_bytes=None):
        """Stream `url` to `dest` atomically via a .part temp file."""
        tmp = dest + ".part"
        resp = requests.get(url, stream=True, timeout=timeout)
        resp.raise_for_status()
        size = int(resp.headers.get("content-length", 0))
        got = 0
        with open(tmp, "wb") as f:
            for chunk in resp.iter_content(chunk_size=8192):
                if chunk:
                    f.write(chunk)
                    got += len(chunk)
                    if on_bytes:
                        on_bytes(got, size)
        os.replace(tmp, dest)  # atomic: partial file never appears at final path
        return got

    try:
        with db_factory() as db:
            DownloadManager.update_progress(db, model_id, "downloading", progress=1.0)

        if user_id:
            notification_manager.notify_download_started_threadsafe(user_id, model_id, model_name)

        onnx_path = os.path.join(local_dir, os.path.basename(download_url))
        json_path = os.path.join(local_dir, os.path.basename(json_url))

        # First HEAD to learn the real total (onnx dominates; json is tiny).
        try:
            head = requests.head(download_url, timeout=30, allow_redirects=True)
            onnx_total = int(head.headers.get("content-length", 0))
        except Exception:
            onnx_total = 0

        logger.info(f"Downloading Piper model: {model_id}")
        last_pct = [-100.0]

        def _on_onnx(got, size):
            total = size or onnx_total
            if not total:
                return
            pct = min(99.0, (got / total) * 100.0)
            if pct - last_pct[0] >= 2.0:
                last_pct[0] = pct
                with db_factory() as db:
                    DownloadManager.update_progress(
                        db, model_id, "downloading", progress=round(pct, 1),
                        downloaded_bytes=got, total_bytes=total,
                    )
                if user_id:
                    notification_manager.notify_download_progress_threadsafe(
                        user_id, model_id, model_name, round(pct, 1),
                        os.path.basename(download_url),
                    )

        _download_file(download_url, onnx_path, timeout=300, on_bytes=_on_onnx)

        logger.info(f"Downloading Piper config: {model_id}")
        _download_file(json_url, json_path, timeout=60)

        with db_factory() as db:
            DownloadManager.update_progress(db, model_id, "completed", progress=100.0)
            logger.success(f"Finished downloading Piper model {model_id}")

        if user_id:
            notification_manager.notify_download_completed_threadsafe(user_id, model_id, model_name)

    except Exception as e:
        logger.error(f"Failed to download Piper model {model_id}: {e}")
        # Clean up any leftover .part files
        for p in (os.path.join(local_dir, os.path.basename(download_url)) + ".part",
                  os.path.join(local_dir, os.path.basename(json_url)) + ".part"):
            try:
                if os.path.exists(p):
                    os.remove(p)
            except OSError:
                pass
        with db_factory() as db:
            DownloadManager.update_progress(db, model_id, "failed", error=str(e))
        if user_id:
            notification_manager.notify_download_failed_threadsafe(user_id, model_id, model_name, str(e))


def download_hf_model(db_factory, model_id: str, repo_id: str, local_dir: str,
                     user_id: Optional[str] = None):
    """
    Downloads a model from HuggingFace hub with byte-accurate progress tracking.

    Args:
        db_factory: Callable that returns a new DB session (context-manager capable).
        model_id: The ID of the model in our registry.
        repo_id: The HuggingFace repository ID.
        local_dir: Where to save the model files.
        user_id: Optional user ID for WebSocket notifications.
    """
    from app.websocket import notification_manager

    model_info = get_model_info(model_id)

    # Piper models use a dedicated direct-download path.
    if model_info and model_info.get("engine", "").startswith("piper"):
        logger.info(f"Using Piper download method for {model_id}")
        download_piper_model(model_id, model_info, local_dir, db_factory, user_id)
        return

    logger.info(f"Starting download for {model_id} from {repo_id} to {local_dir}")
    model_name = model_info.get("name", model_id) if model_info else model_id

    os.makedirs(local_dir, exist_ok=True)

    # hf-transfer can cause "receiver dropped" on macOS; keep it off. Longer timeout
    # for flaky connections.
    os.environ["HF_HUB_ENABLE_HF_TRANSFER"] = "0"
    os.environ["HF_HUB_HTTP_TIMEOUT"] = "60"
    token = settings.HF_TOKEN or None
    if token:
        logger.info(f"Using HF_TOKEN for authenticated downloads (starts with: {token[:10]}...)")
    else:
        logger.warning("No HF_TOKEN configured. Downloads may be rate limited.")

    # Total size up-front so the poller can report a true overall percentage.
    total_bytes = _compute_repo_total_bytes(repo_id, token)
    if not total_bytes and model_info:
        total_bytes = int(model_info.get("size_estimate_mb", 0)) * 1024 * 1024

    poller = _ProgressPoller(model_id, model_name, local_dir, total_bytes, db_factory, user_id)

    try:
        with db_factory() as db:
            DownloadManager.update_progress(
                db, model_id, "downloading", progress=1.0, total_bytes=total_bytes or None
            )
        if user_id:
            notification_manager.notify_download_started_threadsafe(user_id, model_id, model_name)

        poller.start()

        from app.utils.tqdm_handler import StatusTqdm
        download_kwargs = {
            "repo_id": repo_id,
            "local_dir": local_dir,
            "local_dir_use_symlinks": False,
            "tqdm_class": StatusTqdm,
        }
        if token:
            download_kwargs["token"] = token

        snapshot_download(**download_kwargs)

        poller.stop()
        poller.join(timeout=5)

        with db_factory() as db:
            DownloadManager.update_progress(db, model_id, "completed", progress=100.0)
            logger.success(f"Finished downloading {model_id}")

        if user_id:
            notification_manager.notify_download_completed_threadsafe(user_id, model_id, model_name)

    except Exception as e:
        poller.stop()
        logger.error(f"Failed to download {model_id}: {e}")
        with db_factory() as db:
            DownloadManager.update_progress(db, model_id, "failed", error=str(e))
        if user_id:
            notification_manager.notify_download_failed_threadsafe(user_id, model_id, model_name, str(e))


class _DummyDb:
    def __enter__(self):
        return None
    def __exit__(self, *args):
        pass


def download_model(model_id: str) -> bool:
    """Download model files directly for CLI and workers without an active HTTP session."""
    model_info = get_model_info(model_id)
    if not model_info:
        raise ValueError(f"Unknown model ID: {model_id}")

    local_dir = os.path.join(str(settings.MODELS_DIR), model_id)
    os.makedirs(local_dir, exist_ok=True)


    if model_info.get("engine", "").startswith("piper"):
        download_piper_model(model_id, model_info, local_dir, db_factory=lambda: _DummyDb())
    else:
        repo_id = model_info.get("repo_id")
        download_hf_model(lambda: _DummyDb(), model_id, repo_id, local_dir)

    from app.downloader.integrity_checker import is_model_healthy
    return is_model_healthy(model_id)

