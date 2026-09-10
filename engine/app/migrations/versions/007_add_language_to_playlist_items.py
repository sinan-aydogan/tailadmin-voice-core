"""
Migration: add_language_to_playlist_items
Created at: 2024-01-01 00:00:00
"""
from sqlalchemy import text
from sqlalchemy.orm import Session


def up(db: Session):
    """Add language column to playlist_items table."""
    db.execute(text("""
        ALTER TABLE playlist_items ADD COLUMN language VARCHAR(10)
    """))
    db.commit()


def down(db: Session):
    """Remove language column from playlist_items table."""
    pass
