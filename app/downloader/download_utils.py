"""
Utilities for downloading models from HuggingFace.
"""
import os
from typing import Optional, Callable
from huggingface_hub import snapshot_download
from huggingface_hub.utils import tqdm as hf_tqdm
from sqlalchemy.orm import Session
from loguru import logger

from app.config import settings
from app.downloader.download_manager import DownloadManager
from app.downloader.model_registry import get_model_info

# Global progress callback for WebSocket notifications
_progress_callback: Optional[Callable] = None


def set_progress_callback(callback: Optional[Callable]):
    """Set a callback function to receive download progress updates."""
    global _progress_callback
    _progress_callback = callback


def notify_progress(model_id: str, progress: float, current_file: Optional[str] = None, 
                   downloaded_bytes: int = 0, total_bytes: int = 0):
    """Notify progress via callback if set."""
    if _progress_callback:
        try:
            _progress_callback(model_id, progress, current_file, downloaded_bytes, total_bytes)
        except Exception as e:
            logger.error(f"Error in progress callback: {e}")

class WebSocketProgressTqdm(hf_tqdm):
    """Custom tqdm that reports progress via WebSocket."""
    
    def __init__(self, *args, **kwargs):
        self.model_id = os.environ.get("CURRENT_DOWNLOAD_MODEL_ID", "unknown")
        self.current_file = None
        super().__init__(*args, **kwargs)
    
    def update(self, n=1):
        super().update(n)
        if self.total:
            progress = (self.n / self.total) * 100
            notify_progress(
                self.model_id, 
                progress, 
                self.current_file,
                downloaded_bytes=self.n,
                total_bytes=self.total
            )
    
    def set_description(self, desc):
        super().set_description(desc)
        if desc:
            # Extract filename from description (e.g., "Downloading pytorch_model.bin: 100%")
            if "Downloading" in desc:
                parts = desc.split()
                for part in parts:
                    if "." in part and not part.endswith(":"):
                        self.current_file = part.replace(":", "")
                        break


def download_hf_model(db_factory, model_id: str, repo_id: str, local_dir: str, 
                     user_id: Optional[str] = None):
    """
    Downloads a model from HuggingFace hub and updates progress in DB.
    
    Args:
        db_factory: Callable that returns a new DB session.
        model_id: The ID of the model in our registry.
        repo_id: The HuggingFace repository ID.
        local_dir: Where to save the model files.
        user_id: Optional user ID for WebSocket notifications.
    """
    from app.utils.tqdm_handler import StatusTqdm
    from app.websocket import notification_manager
    
    logger.info(f"Starting real download for {model_id} from {repo_id} to {local_dir}")
    
    # Get model info for notifications
    model_info = get_model_info(model_id)
    model_name = model_info.get("name", model_id) if model_info else model_id
    
    # Ensure local directory exists
    os.makedirs(local_dir, exist_ok=True)
    
    # Set context for StatusTqdm
    os.environ["CURRENT_DOWNLOAD_MODEL_ID"] = model_id
    
    # Disable hf-transfer which can cause "receiver dropped" on macOS
    os.environ["HF_HUB_ENABLE_HF_TRANSFER"] = "0"
    # Set a longer timeout for HF requests
    os.environ["HF_HUB_HTTP_TIMEOUT"] = "60"
    # Set HF Token if available (to avoid rate limiting)
    if settings.HF_TOKEN:
        os.environ["HF_TOKEN"] = settings.HF_TOKEN
        logger.info(f"Using HF_TOKEN for authenticated downloads (token starts with: {settings.HF_TOKEN[:10]}...)")
    else:
        logger.warning("No HF_TOKEN configured. Downloads may be rate limited.")

    try:
        with db_factory() as db:
            DownloadManager.update_progress(db, model_id, "downloading", progress=1.0)
        
        # Notify download started (thread-safe)
        if user_id:
            notification_manager.notify_download_started_threadsafe(user_id, model_id, model_name)
        
        # Prepare download kwargs
        download_kwargs = {
            "repo_id": repo_id,
            "local_dir": local_dir,
            "local_dir_use_symlinks": False,
            "tqdm_class": StatusTqdm
        }
        
        # Add token if available
        if settings.HF_TOKEN:
            download_kwargs["token"] = settings.HF_TOKEN
        
        snapshot_download(**download_kwargs)
        
        with db_factory() as db:
            DownloadManager.update_progress(db, model_id, "completed", progress=100.0)
            logger.success(f"Finished downloading {model_id}")
        
        # Notify download completed (thread-safe)
        if user_id:
            notification_manager.notify_download_completed_threadsafe(user_id, model_id, model_name)
            
    except Exception as e:
        logger.error(f"Failed to download {model_id}: {e}")
        with db_factory() as db:
            DownloadManager.update_progress(db, model_id, "failed", error=str(e))
        
        # Notify download failed (thread-safe)
        if user_id:
            notification_manager.notify_download_failed_threadsafe(user_id, model_id, model_name, str(e))
    finally:
        if "CURRENT_DOWNLOAD_MODEL_ID" in os.environ:
            del os.environ["CURRENT_DOWNLOAD_MODEL_ID"]
        set_progress_callback(None)
