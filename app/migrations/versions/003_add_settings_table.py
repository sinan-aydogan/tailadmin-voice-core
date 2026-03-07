"""
Migration: add_settings_table
Created at: 2026-03-07 18:02:57
"""
from sqlalchemy import text
from sqlalchemy.orm import Session


def up(db: Session):
    """
    Run the migration.
    Add your schema changes here.
    """
    # Example:
    # db.execute(text("""
    #     ALTER TABLE users ADD COLUMN new_field VARCHAR(255)
    # """))
    # db.commit()
    pass


def down(db: Session):
    """
    Rollback the migration.
    Reverse the changes made in 'up'.
    """
    # Example:
    # db.execute(text("""
    #     ALTER TABLE users DROP COLUMN new_field
    # """))
    # db.commit()
    pass
