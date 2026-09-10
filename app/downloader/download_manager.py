"""
Model Download Manager.
"""
from datetime import datetime, timedelta
from sqlalchemy.orm import Session
from loguru import logger

from app.models.queue import ModelDownload
from app.downloader.model_registry import get_model_info

# A download stuck in "downloading" with no progress update for this long is
# considered dead (crashed/interrupted) and may be restarted.
STALE_DOWNLOAD_MINUTES = 10


class DownloadManager:
    """Manages the state and DB entries for downloading models."""
    
    @staticmethod
    def get_all_downloads(db: Session, skip: int = 0, limit: int = 100):
        return db.query(ModelDownload).order_by(ModelDownload.started_at.desc()).offset(skip).limit(limit).all()
        
    @staticmethod
    def get_download(db: Session, model_id: str):
        return db.query(ModelDownload).filter(ModelDownload.model_id == model_id).first()
        
    @staticmethod
    def request_download(db: Session, model_id: str) -> ModelDownload:
        """Create or update a download request."""
        model_info = get_model_info(model_id)
        if not model_info:
            raise ValueError(f"Unknown model ID: {model_id}")
            
        existing = db.query(ModelDownload).filter(ModelDownload.model_id == model_id).first()

        if existing:
            if existing.status == "completed":
                return existing
            if existing.status == "downloading" and not DownloadManager._is_stale(existing):
                # A genuinely in-flight download: don't disturb it.
                return existing
            # Failed, pending, or a stale/crashed "downloading" record -> restart it.
            if existing.status == "downloading":
                logger.warning(f"Restarting stale download for {model_id} (no progress > {STALE_DOWNLOAD_MINUTES}m)")
            existing.status = "pending"
            existing.progress_pct = 0.0
            existing.downloaded_bytes = 0
            existing.error_message = None
            existing.started_at = None
            existing.completed_at = None
            db.add(existing)
        else:
            existing = ModelDownload(
                model_id=model_id,
                model_type=model_info["type"],
                status="pending",
                total_bytes=model_info.get("size_estimate_mb", 0) * 1024 * 1024
            )
            db.add(existing)
            
        db.commit()
        db.refresh(existing)
        logger.info(f"Requested download for model {model_id}")
        return existing

    @staticmethod
    def _is_stale(download: ModelDownload) -> bool:
        """True if a 'downloading' record hasn't advanced for STALE_DOWNLOAD_MINUTES."""
        # `started_at` is bumped on each progress write below, so it doubles as a
        # "last activity" timestamp. No timestamp at all => treat as stale.
        ref = download.started_at
        if ref is None:
            return True
        return datetime.utcnow() - ref > timedelta(minutes=STALE_DOWNLOAD_MINUTES)

    @staticmethod
    def update_progress(db: Session, model_id: str, status: str, progress: float = None,
                        error: str = None, downloaded_bytes: int = None, total_bytes: int = None):
        """Update the progress of an ongoing download."""
        download = db.query(ModelDownload).filter(ModelDownload.model_id == model_id).first()
        if not download:
            return

        download.status = status

        # Refresh started_at on every progress tick so _is_stale reflects real activity.
        if status == "downloading":
            download.started_at = datetime.utcnow()

        if progress is not None:
            download.progress_pct = progress

        if downloaded_bytes is not None:
            download.downloaded_bytes = downloaded_bytes
        if total_bytes is not None:
            download.total_bytes = total_bytes

        if error:
            download.error_message = error

        if status in ["completed", "failed"]:
            download.completed_at = datetime.utcnow()
            if status == "completed":
                download.progress_pct = 100.0
                if download.total_bytes:
                    download.downloaded_bytes = download.total_bytes

        db.add(download)
        db.commit()

    @staticmethod
    def reset_interrupted_downloads(db: Session) -> int:
        """On startup, flip any 'downloading' records to 'failed'.

        A record left in 'downloading' means the server died mid-download; without
        this the model could never be re-requested (request_download short-circuits
        on 'downloading'). Returns the number of records reset.
        """
        stuck = db.query(ModelDownload).filter(ModelDownload.status == "downloading").all()
        for d in stuck:
            d.status = "failed"
            d.error_message = "İndirme sunucu yeniden başlatılınca kesildi. Tekrar deneyin."
            d.completed_at = datetime.utcnow()
            db.add(d)
        if stuck:
            db.commit()
            logger.warning(f"Reset {len(stuck)} interrupted download(s) to 'failed' on startup")
        return len(stuck)
    
    @staticmethod
    def delete_model(db: Session, model_id: str, remove_files: bool = True) -> bool:
        """
        Delete a model's DB record and optionally its files from disk.
        
        Args:
            db: Database session.
            model_id: The ID of the model.
            remove_files: If True, deletes the model directory from disk.
        """
        from app.config import settings
        import shutil
        import os
        
        # 1. Delete files from disk if requested
        model_dir = os.path.join(settings.MODELS_DIR, model_id)
        if remove_files and os.path.exists(model_dir):
            try:
                shutil.rmtree(model_dir)
                logger.info(f"Deleted model files for {model_id} at {model_dir}")
            except Exception as e:
                logger.error(f"Failed to delete model files for {model_id}: {e}")

        # 2. Delete from DB
        download = db.query(ModelDownload).filter(ModelDownload.model_id == model_id).first()
        if download:
            db.delete(download)
            db.commit()
            return True
        elif remove_files and os.path.exists(model_dir) == False:
            # If DB record was missing but files were there and we deleted them
            return True

        return False
