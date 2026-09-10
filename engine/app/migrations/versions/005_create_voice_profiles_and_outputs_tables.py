"""
Migration: create_voice_profiles_and_outputs_tables
Created at: 2024-01-01 00:00:00
"""
from sqlalchemy import text
from sqlalchemy.orm import Session


def up(db: Session):
    """Create voice_profiles, tts_outputs, stt_results, tags, and tts_output_tags tables."""
    
    # Create voice_profiles table
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS voice_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            tags TEXT,
            audio_file_path TEXT,
            engine VARCHAR(100),
            language VARCHAR(10) DEFAULT 'tr',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """))
    
    # Create tts_outputs table
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS tts_outputs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            text TEXT NOT NULL,
            engine VARCHAR(100) NOT NULL,
            profile_id INTEGER,
            language VARCHAR(10) NOT NULL,
            output_path TEXT NOT NULL,
            duration_sec FLOAT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (profile_id) REFERENCES voice_profiles(id)
        )
    """))
    
    # Create stt_results table
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS stt_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            input_path TEXT NOT NULL,
            engine VARCHAR(100) NOT NULL,
            model_size VARCHAR(50),
            transcript TEXT,
            language VARCHAR(10),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """))
    
    # Create tags table
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) UNIQUE NOT NULL,
            color VARCHAR(20) DEFAULT '#3C50E0',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """))
    
    # Create tts_output_tags association table
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS tts_output_tags (
            tts_output_id INTEGER NOT NULL,
            tag_id INTEGER NOT NULL,
            PRIMARY KEY (tts_output_id, tag_id),
            FOREIGN KEY (tts_output_id) REFERENCES tts_outputs(id),
            FOREIGN KEY (tag_id) REFERENCES tags(id)
        )
    """))
    
    db.commit()


def down(db: Session):
    """Drop voice_profiles, tts_outputs, stt_results, tags, and tts_output_tags tables."""
    db.execute(text("DROP TABLE IF EXISTS tts_output_tags"))
    db.execute(text("DROP TABLE IF EXISTS tags"))
    db.execute(text("DROP TABLE IF EXISTS stt_results"))
    db.execute(text("DROP TABLE IF EXISTS tts_outputs"))
    db.execute(text("DROP TABLE IF EXISTS voice_profiles"))
    db.commit()
