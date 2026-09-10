"""
Migration tracking model for database schema versioning.
Similar to Laravel's migration system.
"""
from datetime import datetime
from sqlalchemy import Column, Integer, String, DateTime, Text
from app.models.base import Base


class Migration(Base):
    """Tracks executed migrations."""
    __tablename__ = "migrations"

    id = Column(Integer, primary_key=True, index=True)
    migration = Column(String, unique=True, nullable=False, index=True)  # Filename
    batch = Column(Integer, nullable=False, default=0)  # Batch number for rollback
    executed_at = Column(DateTime, default=datetime.utcnow)


class MigrationFile:
    """Represents a migration file with up/down methods."""
    
    def __init__(self, filename: str, up_sql: str = None, down_sql: str = None):
        self.filename = filename
        self.up_sql = up_sql or ""
        self.down_sql = down_sql or ""
    
    def get_version(self) -> int:
        """Extract version number from filename (e.g., '001_add_users' -> 1)."""
        try:
            return int(self.filename.split('_')[0])
        except (ValueError, IndexError):
            return 0
