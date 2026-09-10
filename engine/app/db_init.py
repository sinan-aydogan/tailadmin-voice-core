"""
Database initialization and seeding.
Uses migration system for schema management.
"""
from sqlalchemy.orm import Session
from loguru import logger

from app.database import engine, SessionLocal
from app.models.base import Base
import app.models  # Ensures all models are imported before create_all
from app.models.user import User
from app.auth.service import get_password_hash
from app.config import settings


def init_db():
    """Initialize database using migration system."""
    logger.info("Initializing database...")
    
    # Run migrations
    from app.migrations import MigrationRunner
    db = SessionLocal()
    try:
        runner = MigrationRunner(db)
        runner.run_migrations()
        logger.info("Migrations completed successfully.")
    finally:
        db.close()
    
    reset_stale_tasks()

def reset_stale_tasks():
    """Reset tasks and downloads that were left in an active state from a previous run."""
    from app.database import SessionLocal
    from app.models.queue import Task, ModelDownload
    
    db = SessionLocal()
    try:
        # Reset running tasks to pending
        stale_tasks = db.query(Task).filter(Task.status == "running").all()
        if stale_tasks:
            logger.info(f"Resetting {len(stale_tasks)} stale running tasks to pending")
            for task in stale_tasks:
                task.status = "pending"
        
        # Reset downloading models to pending (or failed if we prefer a clean restart)
        # For now, let's keep them as downloading/failed but clean up the status
        stale_downloads = db.query(ModelDownload).filter(ModelDownload.status.in_(["downloading", "verifying"])).all()
        if stale_downloads:
            logger.info(f"Resetting {len(stale_downloads)} stale model downloads to pending")
            for dw in stale_downloads:
                dw.status = "failed" # Better to fail and let user retry to avoid partial file issues
                dw.error_message = "Server restarted during download."
        
        db.commit()
    except Exception as e:
        logger.error(f"Failed to reset stale tasks: {e}")
        db.rollback()
    finally:
        db.close()

def seed_default_admin(db: Session):
    """Seed the default admin account if no users exist."""
    user = db.query(User).filter(User.username == settings.DEFAULT_USERNAME).first()
    if not user:
        logger.info(f"Creating default admin user: {settings.DEFAULT_USERNAME}")
        hashed_password = get_password_hash(settings.DEFAULT_PASSWORD)
        db_user = User(
            username=settings.DEFAULT_USERNAME,
            hashed_password=hashed_password,
            is_active=True
        )
        db.add(db_user)
        db.commit()
        db.refresh(db_user)
        logger.success("Default admin user created.")
    else:
        logger.debug("Default admin user already exists.")

def create_default_admin():
    """Wrapper to seed the default admin using a new database session."""
    from app.database import SessionLocal
    db = SessionLocal()
    try:
        seed_default_admin(db)
    finally:
        db.close()

