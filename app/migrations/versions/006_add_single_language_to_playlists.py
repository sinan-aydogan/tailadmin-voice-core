"""
Migration: add_single_language_to_playlists
Created at: 2024-01-01 00:00:00
"""
from sqlalchemy import text
from sqlalchemy.orm import Session


def up(db: Session):
    """Add single_language column to playlists table."""
    db.execute(text("""
        ALTER TABLE playlists ADD COLUMN single_language VARCHAR(10)
    """))
    db.commit()


def down(db: Session):
    """Remove single_language column from playlists table."""
    # SQLite doesn't support DROP COLUMN directly, need to recreate table
    # For simplicity, we'll skip the down migration for SQLite
    pass
