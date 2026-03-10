"""
Migration: create_tasks_and_downloads_tables
Created at: 2024-01-01 00:00:00
"""
from sqlalchemy import text
from sqlalchemy.orm import Session


def up(db: Session):
    """Create tasks and model_downloads tables."""
    # Create tasks table
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            payload TEXT NOT NULL,
            result TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP,
            completed_at TIMESTAMP
        )
    """))
    
    # Create model_downloads table
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS model_downloads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            model_id VARCHAR(255) UNIQUE NOT NULL,
            model_type VARCHAR(50) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            progress_pct FLOAT DEFAULT 0.0,
            downloaded_bytes INTEGER DEFAULT 0,
            total_bytes INTEGER DEFAULT 0,
            error_message TEXT,
            started_at TIMESTAMP,
            completed_at TIMESTAMP
        )
    """))
    
    db.commit()


def down(db: Session):
    """Drop tasks and model_downloads tables."""
    db.execute(text("DROP TABLE IF EXISTS tasks"))
    db.execute(text("DROP TABLE IF EXISTS model_downloads"))
    db.commit()
