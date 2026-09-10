"""
Application Logs Endpoint.
"""
import os
import aiofiles
from fastapi import APIRouter, Depends, HTTPException, Query
from typing import List, Dict

from app.auth.dependencies import get_current_user
from app.models.user import User

router = APIRouter(prefix="/logs", tags=["logs"])

LOG_FILE_PATH = "logs/app.log"

@router.get("/", response_model=Dict[str, List[str]])
async def get_recent_logs(
    lines: int = Query(100, ge=1, le=1000),
    current_user: User = Depends(get_current_user)
):
    """Retrieve the most recent log lines from the application log file."""
    if not os.path.exists(LOG_FILE_PATH):
        return {"logs": []}
        
    try:
        # A simple approach to read the last N lines.
        # For very large files in production, a more efficient tail strategy is better.
        async with aiofiles.open(LOG_FILE_PATH, mode='r') as f:
            all_lines = await f.readlines()
            
        recent_lines = all_lines[-lines:]
        return {"logs": [line.strip() for line in recent_lines]}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Failed to read logs: {str(e)}")
