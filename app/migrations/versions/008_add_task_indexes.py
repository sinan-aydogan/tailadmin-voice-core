"""
Migration: add_task_indexes
Created at: 2026-07-10 00:00:00

Adds indexes used by the queue worker's hot polling query
(WHERE status='pending' ORDER BY created_at). Safe/idempotent via IF NOT EXISTS.
"""
from sqlalchemy import text
from sqlalchemy.orm import Session


def up(db: Session):
    """Create indexes on tasks.status and tasks.created_at."""
    db.execute(text(
        "CREATE INDEX IF NOT EXISTS ix_tasks_status ON tasks (status)"
    ))
    db.execute(text(
        "CREATE INDEX IF NOT EXISTS ix_tasks_created_at ON tasks (created_at)"
    ))
    db.commit()


def down(db: Session):
    """Drop the indexes."""
    db.execute(text("DROP INDEX IF EXISTS ix_tasks_status"))
    db.execute(text("DROP INDEX IF EXISTS ix_tasks_created_at"))
    db.commit()
