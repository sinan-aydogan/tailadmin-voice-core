"""
TTS API Endpoints.
"""
import uuid
import os
from fastapi import APIRouter, Depends, HTTPException, BackgroundTasks
from sqlalchemy.orm import Session
from loguru import logger

from typing import List, Optional
from app.database import get_db
from app.auth.dependencies import get_current_user
from app.models.user import User
from app.models.profile import VoiceProfile
from app.models.generation import Tag, TTSOutput
from app.models.queue import Task
from app.schemas.tts import TTSGenerateRequest, TTSOutputResponse
from app.schemas.queue import TaskResponse
from app.tts.registry import get_tts_engine
from app.downloader.integrity_checker import is_model_healthy, get_ready_for_tts
from app.config import settings
import json

router = APIRouter(prefix="/tts", tags=["tts"])

@router.post("/generate", response_model=TaskResponse)
async def create_tts_task(
    request: TTSGenerateRequest,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """
    Submits a new TTS generation task to the queue for asynchronous processing.
    """
    engine_name = request.engine or settings.DEFAULT_TTS_ENGINE
    
    # Check model integrity using the new integrity checker
    engine_to_model = {
        "xtts": "xtts-v2",
        "bark": "bark", 
        "tortoise": "tortoise"
    }
    model_id = engine_to_model.get(engine_name, engine_name)
    
    if not is_model_healthy(model_id):
        raise HTTPException(
            status_code=400, 
            detail=f"Model '{model_id}' is not downloaded or is corrupted. Please download it from Model Manager."
        )
        
    profile_path = None
    if request.profile_id:
        profile = db.query(VoiceProfile).filter(VoiceProfile.id == request.profile_id).first()
        if not profile:
            raise HTTPException(status_code=404, detail="Voice profile not found")
        profile_path = profile.audio_file_path

    # Output filename generation
    filename = f"{uuid.uuid4().hex}.wav"
    output_path = os.path.join(settings.OUTPUTS_DIR, "tts", filename)
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    
    # Store request parameters for the worker
    payload = {
        "text": request.text,
        "engine": engine_name,
        "language": request.language or "tr",
        "profile_id": request.profile_id,
        "profile_path": profile_path,
        "output_path": output_path,
        "tag_ids": request.tag_ids,
        "user_id": str(current_user.id),  # Add user_id for WebSocket notifications
        "kwargs": {
            "speed_factor": request.speed_factor
        }
    }
    
    # Create Task in DB
    task = Task(
        type="tts",
        status="pending",
        payload=json.dumps(payload)
    )
    db.add(task)
    db.commit()
    db.refresh(task)
    
    logger.info(f"Queued TTS task {task.id} for engine {engine_name}")
    
    return task


@router.get("/engines", response_model=List[str])
async def get_available_engines(
    current_user: User = Depends(get_current_user)
):
    """Get list of TTS engines that are ready to use (downloaded and verified)."""
    ready_models = get_ready_for_tts()
    
    # Map model IDs back to engine names
    model_to_engine = {
        "xtts-v2": "xtts",
        "bark": "bark",
        "tortoise": "tortoise"
    }
    
    ready_engines = []
    for model_id in ready_models:
        engine = model_to_engine.get(model_id)
        if engine:
            ready_engines.append(engine)
    
    return ready_engines

@router.get("/history", response_model=list[TTSOutputResponse])
async def get_tts_history(
    skip: int = 0,
    limit: int = 100,
    tag_id: Optional[int] = None,
    engine: Optional[str] = None,
    profile_id: Optional[int] = None,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get history of completed TTS generations."""
    query = db.query(TTSOutput)
    
    if tag_id:
        query = query.filter(TTSOutput.tags.any(Tag.id == tag_id))
    
    if engine:
        query = query.filter(TTSOutput.engine == engine)
        
    if profile_id:
        query = query.filter(TTSOutput.profile_id == profile_id)
        
    outputs = query.order_by(TTSOutput.created_at.desc()).offset(skip).limit(limit).all()
    return outputs

@router.get("/{output_id}", response_model=TTSOutputResponse)
async def get_tts_output(
    output_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get details of a specific TTS generation."""
    output = db.query(TTSOutput).filter(TTSOutput.id == output_id).first()
    if not output:
        raise HTTPException(status_code=404, detail="TTS output not found")
    return output

@router.patch("/{output_id}/tags", response_model=TTSOutputResponse)
async def update_tts_output_tags(
    output_id: int,
    tag_ids: List[int],
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Update the tags associated with a specific TTS output."""
    output = db.query(TTSOutput).filter(TTSOutput.id == output_id).first()
    if not output:
        raise HTTPException(status_code=404, detail="TTS output not found")
    
    tags = db.query(Tag).filter(Tag.id.in_(tag_ids)).all()
    output.tags = tags
    db.commit()
    db.refresh(output)
    return output

@router.delete("/{output_id}")
async def delete_tts_output(
    output_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Delete a TTS output record and its audio file."""
    output = db.query(TTSOutput).filter(TTSOutput.id == output_id).first()
    if not output:
        raise HTTPException(status_code=404, detail="TTS output not found")
    
    # Optional: Delete file from disk
    import os
    file_path = f"outputs{output.output_path}"
    if os.path.exists(file_path):
        os.remove(file_path)
        
    db.delete(output)
    db.commit()
    return {"status": "success", "message": "TTS output deleted"}
