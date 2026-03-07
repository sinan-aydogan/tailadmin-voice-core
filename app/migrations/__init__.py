"""
Database Migration System
Similar to Laravel's migration system.

Usage:
    from app.migrations import MigrationRunner
    
    runner = MigrationRunner(db_session)
    runner.run_migrations()  # Run pending migrations
    runner.rollback_last()   # Rollback last batch
    
Creating new migrations:
    1. Create a new file in migrations/ folder with format: XXX_description.py
    2. Define 'up' and 'down' functions
    3. Run migrations
"""
from .runner import MigrationRunner

__all__ = ['MigrationRunner']
