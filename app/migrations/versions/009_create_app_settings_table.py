"""
Migration: create_app_settings_table
Created at: 2026-07-10 00:00:00

Fixes a long-standing gap: the AppSettings model (table `app_settings`) had no
migration that actually created it — migration 003 was scaffolded but left empty
(`pass`), so `app_settings` never existed and the Settings page failed with
"no such table: app_settings" (GET /settings/ and /settings/secret-key/status → 500).

`tab` is NOT NULL to match the model, but carries a DEFAULT so the create-setting
endpoint (which doesn't set `tab`) still works.
"""
from sqlalchemy import text
from sqlalchemy.orm import Session


def up(db: Session):
    """Create the app_settings table if it doesn't exist."""
    db.execute(text("""
        CREATE TABLE IF NOT EXISTS app_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tab VARCHAR NOT NULL DEFAULT 'general',
            key VARCHAR NOT NULL UNIQUE,
            value VARCHAR,
            description VARCHAR,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """))
    db.execute(text(
        "CREATE INDEX IF NOT EXISTS ix_app_settings_key ON app_settings (key)"
    ))
    db.commit()


def down(db: Session):
    """Drop the app_settings table."""
    db.execute(text("DROP TABLE IF EXISTS app_settings"))
    db.commit()
