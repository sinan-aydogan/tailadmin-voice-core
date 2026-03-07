"""
Migration runner - executes migrations and tracks their status.
"""
import os
import re
import importlib.util
from pathlib import Path
from typing import List, Optional
from sqlalchemy import text
from sqlalchemy.orm import Session
from loguru import logger

# Migration model is used only for type hints, actual queries use raw SQL


MIGRATIONS_DIR = Path(__file__).parent / "versions"


class MigrationRunner:
    """Runs database migrations similar to Laravel."""
    
    def __init__(self, db: Session):
        self.db = db
        self._ensure_migrations_table()
    
    def _ensure_migrations_table(self):
        """Create migrations tracking table if not exists."""
        self.db.execute(text("""
            CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INTEGER NOT NULL DEFAULT 0,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        """))
        self.db.commit()
    
    def get_executed_migrations(self) -> List[str]:
        """Get list of already executed migration filenames."""
        result = self.db.execute(text(
            "SELECT migration FROM migrations ORDER BY id"
        )).fetchall()
        return [r[0] for r in result]
    
    def get_pending_migrations(self) -> List[str]:
        """Get list of migration files that haven't been executed yet."""
        executed = set(self.get_executed_migrations())
        all_files = self._get_migration_files()
        return [f for f in all_files if f not in executed]
    
    def _get_migration_files(self) -> List[str]:
        """Get all migration files sorted by version number."""
        if not MIGRATIONS_DIR.exists():
            return []
        
        files = []
        for f in MIGRATIONS_DIR.iterdir():
            if f.is_file() and f.suffix == '.py' and not f.name.startswith('__'):
                files.append(f.name)
        
        # Sort by version number (001_, 002_, etc.)
        def get_version(filename):
            match = re.match(r'^(\d+)_.*\.py$', filename)
            return int(match.group(1)) if match else 0
        
        return sorted(files, key=get_version)
    
    def _load_migration(self, filename: str):
        """Load a migration file and return its module."""
        filepath = MIGRATIONS_DIR / filename
        if not filepath.exists():
            raise FileNotFoundError(f"Migration file not found: {filename}")
        
        spec = importlib.util.spec_from_file_location(
            f"migration_{filename[:-3]}", 
            filepath
        )
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        
        return module
    
    def get_next_batch_number(self) -> int:
        """Get next batch number for migrations."""
        result = self.db.execute(text(
            "SELECT batch FROM migrations ORDER BY batch DESC LIMIT 1"
        )).fetchone()
        return (result[0] + 1) if result else 1
    
    def run_migrations(self, target: Optional[str] = None):
        """
        Run all pending migrations.
        
        Args:
            target: If specified, run migrations up to this file (inclusive)
        """
        pending = self.get_pending_migrations()
        
        if not pending:
            logger.info("No pending migrations")
            return
        
        if target:
            # Run only up to target
            try:
                target_index = pending.index(target)
                pending = pending[:target_index + 1]
            except ValueError:
                logger.error(f"Target migration '{target}' not found in pending list")
                return
        
        batch = self.get_next_batch_number()
        logger.info(f"Running {len(pending)} migration(s) in batch {batch}...")
        
        for filename in pending:
            try:
                self._run_migration(filename, batch)
                logger.success(f"Migrated: {filename}")
            except Exception as e:
                logger.error(f"Migration failed: {filename}")
                logger.error(str(e))
                self.db.rollback()
                raise
        
        logger.success("All migrations completed successfully")
    
    def _run_migration(self, filename: str, batch: int):
        """Execute a single migration."""
        module = self._load_migration(filename)
        
        # Check if 'up' function exists
        if not hasattr(module, 'up'):
            raise AttributeError(f"Migration {filename} has no 'up' function")
        
        # Run the migration
        module.up(self.db)
        
        # Record the migration
        self.db.execute(text(
            "INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)"
        ), {"migration": filename, "batch": batch})
        self.db.commit()
    
    def rollback_last(self, steps: int = 1):
        """
        Rollback the last batch of migrations.
        
        Args:
            steps: Number of batches to rollback (default: 1)
        """
        # Get last batch number
        result = self.db.execute(text(
            "SELECT batch FROM migrations ORDER BY batch DESC LIMIT 1"
        )).fetchone()
        if not result:
            logger.info("No migrations to rollback")
            return
        
        last_batch = result[0]
        
        for _ in range(steps):
            if last_batch <= 0:
                break
                
            migrations = self.db.execute(text(
                "SELECT id, migration FROM migrations WHERE batch = :batch ORDER BY id DESC"
            ), {"batch": last_batch}).fetchall()
            
            if not migrations:
                logger.info(f"No migrations in batch {last_batch}")
                last_batch -= 1
                continue
            
            logger.info(f"Rolling back batch {last_batch} ({len(migrations)} migration(s))...")
            
            for mig_id, mig_name in migrations:
                try:
                    self._rollback_migration(mig_name)
                    self.db.execute(text(
                        "DELETE FROM migrations WHERE id = :id"
                    ), {"id": mig_id})
                    self.db.commit()
                    logger.success(f"Rolled back: {mig_name}")
                except Exception as e:
                    logger.error(f"Rollback failed: {mig_name}")
                    logger.error(str(e))
                    self.db.rollback()
                    raise
            
            last_batch -= 1
        
        logger.success("Rollback completed")
    
    def _rollback_migration(self, filename: str):
        """Rollback a single migration."""
        module = self._load_migration(filename)
        
        # Check if 'down' function exists
        if not hasattr(module, 'down'):
            logger.warning(f"Migration {filename} has no 'down' function, skipping")
            return
        
        # Run the rollback
        module.down(self.db)
    
    def reset(self):
        """Rollback all migrations."""
        executed = self.get_executed_migrations()
        if not executed:
            logger.info("No migrations to reset")
            return
        
        # Get all batch numbers
        batches = self.db.execute(text(
            "SELECT DISTINCT batch FROM migrations ORDER BY batch DESC"
        )).fetchall()
        total_batches = len(batches)
        
        logger.info(f"Resetting all migrations ({total_batches} batches)...")
        self.rollback_last(steps=total_batches)
    
    def refresh(self):
        """Rollback all migrations and re-run them."""
        self.reset()
        self.run_migrations()
    
    def status(self):
        """Show migration status."""
        executed = self.get_executed_migrations()
        pending = self.get_pending_migrations()
        
        print("\n" + "=" * 60)
        print("MIGRATION STATUS")
        print("=" * 60)
        print(f"Executed: {len(executed)}")
        print(f"Pending:  {len(pending)}")
        print("-" * 60)
        
        if executed:
            print("\nExecuted migrations:")
            for m in executed:
                print(f"  ✓ {m}")
        
        if pending:
            print("\nPending migrations:")
            for m in pending:
                print(f"  ○ {m}")
        
        print("=" * 60 + "\n")


def create_migration_file(name: str) -> str:
    """
    Create a new migration file with the next version number.
    
    Args:
        name: Description of the migration (e.g., 'add_users_table')
    
    Returns:
        Path to the created file
    """
    # Ensure migrations directory exists
    MIGRATIONS_DIR.mkdir(parents=True, exist_ok=True)
    
    # Find next version number
    existing = list(MIGRATIONS_DIR.glob('*.py'))
    versions = []
    for f in existing:
        match = re.match(r'^(\d+)_.*\.py$', f.name)
        if match:
            versions.append(int(match.group(1)))
    
    next_version = max(versions, default=0) + 1
    filename = f"{next_version:03d}_{name}.py"
    filepath = MIGRATIONS_DIR / filename
    
    # Create migration file template
    content = f'''"""
Migration: {name}
Created at: {__import__('datetime').datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
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
'''
    
    filepath.write_text(content)
    return str(filepath)
