"""
Model Download Manager.
"""
from datetime import datetime
from sqlalchemy.orm import Session
from loguru import logger

from app.models.queue import ModelDownload
from app.downloader.model_registry import get_model_info

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
            if existing.status in ["completed", "downloading"]:
                return existing
            # Reset if failed
            existing.status = "pending"
            existing.progress_pct = 0.0
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
    def update_progress(db: Session, model_id: str, status: str, progress: float = None, error: str = None):
        """Update the progress of an ongoing download."""
        download = db.query(ModelDownload).filter(ModelDownload.model_id == model_id).first()
        if not download:
            return
            
        download.status = status
        
        if status == "downloading" and not download.started_at:
            download.started_at = datetime.utcnow()
            
        if progress is not None:
            download.progress_pct = progress
            
        if error:
            download.error_message = error
            
        if status in ["completed", "failed"]:
            download.completed_at = datetime.utcnow()
            if status == "completed":
                download.progress_pct = 100.0
                
        db.add(download)
        db.commit()
    
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
