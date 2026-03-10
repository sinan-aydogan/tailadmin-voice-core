"""
Task Queue API Endpoints.
"""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from typing import List, Dict
import os
import re
from datetime import datetime

from app.database import get_db
from app.auth.dependencies import get_current_user
from app.models.user import User
from app.models.queue import Task
from app.schemas.queue import TaskResponse
from app.config import settings

router = APIRouter(prefix="/queue", tags=["queue"])

@router.get("/", response_model=List[TaskResponse])
async def list_tasks(
    skip: int = 0, 
    limit: int = 100,
    status_filter: str = None,
    include_all: bool = True,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Retrieve all tasks in the queue."""
    query = db.query(Task)
    if status_filter:
        query = query.filter(Task.status == status_filter)
    # By default, return all tasks including done/failed for complete visibility
    return query.order_by(Task.created_at.desc()).offset(skip).limit(limit).all()

@router.get("/{task_id}", response_model=TaskResponse)
async def get_task(
    task_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get a specific task by ID."""
    task = db.query(Task).filter(Task.id == task_id).first()
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")
    return task

@router.delete("/{task_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_task(
    task_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Delete or cancel a task."""
    task = db.query(Task).filter(Task.id == task_id).first()
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")
        
    # We don't strongly terminate running tasks here, just mark them deleted logic-wise
    # In a full system you would signal cancellation to the worker
    db.delete(task)
    db.commit()
    return None

@router.get("/{task_id}/logs", response_model=List[Dict])
async def get_task_logs(
    task_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get logs for a specific task from the log file."""
    task = db.query(Task).filter(Task.id == task_id).first()
    if not task:
        raise HTTPException(status_code=404, detail="Task not found")
    
    logs = []
    # Try multiple log file locations
    possible_paths = [
        "./logs/app.log",
        "./data/logs/app.log",
        os.path.join(settings.DATA_DIR, "logs", "app.log"),
    ]
    
    log_file = None
    for path in possible_paths:
        if os.path.exists(path):
            log_file = path
            break
    
    if not log_file:
        return logs
    
    try:
        with open(log_file, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                    
                # Parse log line format: 2026-03-10 16:56:47 | LEVEL    | module:function:line - message
                # or: 2026-03-10 16:56:47 | LEVEL | message
                match = re.match(r'(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+\|\s+(\w+)\s+\|\s+(.+)', line)
                if match:
                    timestamp_str, level, rest = match.groups()
                    
                    # Extract message (may contain module:function:line prefix)
                    message = rest
                    module_match = re.match(r'[\w\.]+:[\w<>]+:\d+\s+-\s+(.+)', rest)
                    if module_match:
                        message = module_match.group(1)
                    
                    # Check if this log line is related to this task
                    if f"[Task {task_id}]" in message or f"task {task_id}" in message.lower():
                        try:
                            timestamp = datetime.strptime(timestamp_str, '%Y-%m-%d %H:%M:%S')
                            logs.append({
                                "timestamp": timestamp.isoformat(),
                                "level": level.strip(),
                                "message": message.strip()
                            })
                        except ValueError:
                            continue
    except Exception as e:
        # Return empty logs on error
        pass
    
    return logs
