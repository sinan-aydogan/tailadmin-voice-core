"""
STT API Endpoints.
"""
import uuid
import os
import aiofiles
from fastapi import APIRouter, Depends, HTTPException, UploadFile, File, Form
from sqlalchemy.orm import Session
from loguru import logger

from app.database import get_db
from app.auth.dependencies import get_current_user
from app.models.user import User
from app.models.generation import STTResult
from app.models.queue import Task
from app.schemas.stt import STTTranscribeRequest, STTResultResponse
from app.schemas.queue import TaskResponse
from app.stt.registry import get_stt_engine
from app.config import settings
import json

router = APIRouter(prefix="/stt", tags=["stt"])

@router.post("/transcribe", response_model=TaskResponse)
@router.post("/transcribe/upload", response_model=TaskResponse)
async def create_stt_task(
    file: UploadFile = File(..., alias="file"),
    engine: str = Form(None),
    model_size: str = Form(None),
    language: str = Form(None),
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Submits a new STT transcription task to the queue for asynchronous processing.
    """
    engine_name = engine or settings.DEFAULT_STT_ENGINE
    engine_instance = get_stt_engine(engine_name)
    model_size = model_size or settings.DEFAULT_STT_MODEL_SIZE
    
    # Save the uploaded file to disk
    filename = f"{uuid.uuid4().hex}_{file.filename}"
    upload_path = os.path.join(settings.UPLOADS_DIR, "stt", filename)
    os.makedirs(os.path.dirname(upload_path), exist_ok=True)
    
    async with aiofiles.open(upload_path, 'wb') as out_file:
        content = await file.read()
        await out_file.write(content)
        
    logger.info(f"Saved STT upload to {upload_path}")
    
    # Store request parameters for the worker
    payload = {
        "audio_path": upload_path,
        "engine": engine_name,
        "language": language,
        "model_size": model_size,
        "kwargs": {}
    }
    
    # Create Task in DB
    task = Task(
        type="stt",
        status="pending",
        payload=json.dumps(payload)
    )
    db.add(task)
    db.commit()
    db.refresh(task)
    
    logger.info(f"Queued STT task {task.id} for engine {engine_name} ({model_size})")
    
    return task

@router.get("/history", response_model=list[STTResultResponse])
async def get_stt_history(
    skip: int = 0,
    limit: int = 100,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get history of completed STT transcriptions."""
    results = db.query(STTResult).order_by(STTResult.created_at.desc()).offset(skip).limit(limit).all()
    return results

@router.get("/{result_id}", response_model=STTResultResponse)
async def get_stt_result(
    result_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get details of a specific STT transcription."""
    result = db.query(STTResult).filter(STTResult.id == result_id).first()
    if not result:
        raise HTTPException(status_code=404, detail="STT result not found")
    return result
