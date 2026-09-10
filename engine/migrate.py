#!/usr/bin/env python3
"""
Database Migration CLI Tool
Similar to Laravel's artisan migrate command.

Usage:
    python migrate.py              # Run pending migrations
    python migrate.py status       # Show migration status
    python migrate.py rollback     # Rollback last batch
    python migrate.py reset        # Rollback all migrations
    python migrate.py refresh      # Reset and re-run all migrations
    python migrate.py make <name>  # Create new migration file

Examples:
    python migrate.py make add_email_to_users
    python migrate.py
    python migrate.py rollback
"""
import sys
import os

# Add parent directory to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from app.database import SessionLocal
from app.migrations import MigrationRunner
from app.migrations.runner import create_migration_file


def main():
    """Main CLI entry point."""
    args = sys.argv[1:]
    
    # Get database session
    db = SessionLocal()
    
    try:
        runner = MigrationRunner(db)
        
        if not args:
            # Default: run migrations
            runner.run_migrations()
        
        elif args[0] == 'status':
            runner.status()
        
        elif args[0] == 'rollback':
            steps = int(args[1]) if len(args) > 1 else 1
            runner.rollback_last(steps=steps)
        
        elif args[0] == 'reset':
            confirm = input("This will rollback ALL migrations. Are you sure? (yes/no): ")
            if confirm.lower() == 'yes':
                runner.reset()
            else:
                print("Cancelled.")
        
        elif args[0] == 'refresh':
            confirm = input("This will reset and re-run all migrations. Are you sure? (yes/no): ")
            if confirm.lower() == 'yes':
                runner.refresh()
            else:
                print("Cancelled.")
        
        elif args[0] == 'make':
            if len(args) < 2:
                print("Error: Migration name required")
                print("Usage: python migrate.py make <migration_name>")
                sys.exit(1)
            
            name = args[1]
            filepath = create_migration_file(name)
            print(f"Created migration: {filepath}")
        
        else:
            print(f"Unknown command: {args[0]}")
            print(__doc__)
            sys.exit(1)
    
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)
    
    finally:
        db.close()


if __name__ == '__main__':
    main()
