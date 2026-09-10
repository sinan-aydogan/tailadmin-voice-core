"""
Migration: create_users_table
Created at: 2024-01-01 00:00:00
"""
from sqlalchemy import text
from sqlalchemy.orm import Session


def up(db: Session):
    """Create users table."""
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL UNIQUE,
            hashed_password VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            theme VARCHAR(50) DEFAULT 'light',
            language VARCHAR(10) DEFAULT 'tr'
        )
    """))
    db.commit()


def down(db: Session):
    """Drop users table."""
    db.execute(text("DROP TABLE IF EXISTS users"))
    db.commit()
