"""
Migration: create_playlists_table
Created at: 2024-01-01 00:00:00
"""
from sqlalchemy import text
from sqlalchemy.orm import Session


def up(db: Session):
    """Create playlists and playlist_items tables."""
    # Create playlists table
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS playlists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            use_single_model BOOLEAN DEFAULT 0,
            single_model_id VARCHAR(100),
            single_profile_id INTEGER,
            single_tag_id INTEGER,
            status VARCHAR(50) DEFAULT 'pending',
            total_items INTEGER DEFAULT 0,
            completed_items INTEGER DEFAULT 0,
            failed_items INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP,
            completed_at TIMESTAMP,
            user_id INTEGER,
            FOREIGN KEY (user_id) REFERENCES users (id)
        )
    """))
    
    # Create playlist_items table
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS playlist_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            playlist_id INTEGER NOT NULL,
            text TEXT NOT NULL,
            position INTEGER DEFAULT 0,
            model_id VARCHAR(100),
            profile_id INTEGER,
            status VARCHAR(50) DEFAULT 'queued',
            error_message TEXT,
            output_path VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP,
            completed_at TIMESTAMP,
            FOREIGN KEY (playlist_id) REFERENCES playlists (id) ON DELETE CASCADE
        )
    """))
    
    db.commit()


def down(db: Session):
    """Drop playlists and playlist_items tables."""
    db.execute(text("DROP TABLE IF EXISTS playlist_items"))
    db.execute(text("DROP TABLE IF EXISTS playlists"))
    db.commit()
