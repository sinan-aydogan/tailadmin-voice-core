"""
Background Worker Loop for Processing Tasks.
"""
import asyncio
import json
from datetime import datetime
from sqlalchemy.orm import Session
from loguru import logger

from app.database import SessionLocal
from app.models.queue import Task
from app.models.generation import Tag, TTSOutput, STTResult
from app.tts.registry import get_tts_engine
from app.stt.registry import get_stt_engine
from app.websocket import notification_manager

class TaskWorker:
    def __init__(self):
        self.is_running = False
        self._task = None

    async def start(self):
        """Start the background worker loop."""
        if self.is_running:
            return
        self.is_running = True
        
        # Patch HF for captured downloads in worker process
        from app.utils.tqdm_handler import patch_huggingface_tqdm
        patch_huggingface_tqdm()
        
        logger.info("Starting background task worker...")
        self._task = asyncio.create_task(self._process_loop())

    async def stop(self):
        """Stop the background worker loop."""
        self.is_running = False
        if self._task:
            self._task.cancel()
        logger.info("Background task worker stopped.")

    async def _process_loop(self):
        while self.is_running:
            try:
                await self._process_next_task()
            except asyncio.CancelledError:
                break
            except Exception as e:
                logger.error(f"Worker loop error: {e}")
            
            # Prevent hot loop
            await asyncio.sleep(2)

    async def _process_next_task(self):
        db = SessionLocal()
        try:
            # Find next pending task
            # In a real distributed system, you'd use row locking (SELECT ... FOR UPDATE SKIP LOCKED)
            task = db.query(Task).filter(Task.status == "pending").order_by(Task.created_at.asc()).first()
            if not task:
                return
                
            # Mark as running
            task.status = "running"
            task.started_at = datetime.utcnow()
            db.commit()
            
            logger.info(f"Worker picked up task {task.id} (type: {task.type})")
            
            payload = json.loads(task.payload)
            success = False
            result_data = None
            
            # Get user_id from payload if available
            user_id = payload.get("user_id")
            
            # Notify task started
            if user_id:
                await notification_manager.notify_task_started(
                    user_id=user_id,
                    task_id=task.id,
                    task_type=task.type,
                    details=payload.get("text", "")[:50] if task.type == "tts" else ""
                )
            
            try:
                if task.type == "tts":
                    success, result_data = await self._handle_tts(db, payload, task.id, user_id)
                elif task.type == "stt":
                    success, result_data = await self._handle_stt(db, payload, task.id, user_id)
                else:
                    raise ValueError(f"Unknown task type: {task.type}")
                    
                task.status = "done" if success else "failed"
                task.result = json.dumps(result_data) if result_data else None
                
                # Notify task completed
                if user_id:
                    if success:
                        await notification_manager.notify_task_completed(
                            user_id=user_id,
                            task_id=task.id,
                            task_type=task.type,
                            result_data=result_data
                        )
                    else:
                        await notification_manager.notify_task_failed(
                            user_id=user_id,
                            task_id=task.id,
                            task_type=task.type,
                            error=result_data.get("error", "Unknown error") if result_data else "Unknown error"
                        )
                
            except Exception as e:
                logger.exception(f"Task {task.id} failed with exception: {e}")
                task.status = "failed"
                task.result = json.dumps({"error": str(e)})
                
                # Notify task failed
                if user_id:
                    await notification_manager.notify_task_failed(
                        user_id=user_id,
                        task_id=task.id,
                        task_type=task.type,
                        error=str(e)
                    )
                
            task.completed_at = datetime.utcnow()
            db.commit()
            logger.info(f"Worker finished task {task.id} with status: {task.status}")
            
        finally:
            db.close()

    async def _handle_tts(self, db: Session, payload: dict, task_id: int, user_id: str = None):
        engine_name = payload.get("engine")
        text = payload.get("text")
        language = payload.get("language", "tr")
        output_path = payload.get("output_path")
        profile_id = payload.get("profile_id")
        profile_path = payload.get("profile_path")
        kwargs = payload.get("kwargs", {})
        
        engine = get_tts_engine(engine_name)
        success = await engine.generate_audio(
            text=text,
            output_path=output_path,
            language=language,
            profile_path=profile_path,
            **kwargs
        )
        
        if success:
            # Create TTSOutput record
            tts_record = TTSOutput(
                text=text,
                engine=engine_name,
                profile_id=profile_id,
                language=language,
                output_path=output_path,
                duration_sec=0.0 # Could calculate this if needed
            )
            
            # Attach tags if any
            tag_ids = payload.get("tag_ids", [])
            if tag_ids:
                tags = db.query(Tag).filter(Tag.id.in_(tag_ids)).all()
                tts_record.tags = tags
                
            db.add(tts_record)
            db.flush()
            return True, {"output_id": tts_record.id, "output_path": output_path, "text": text[:100]}
            
        return False, {"error": "Engine generation failed"}

    async def _handle_stt(self, db: Session, payload: dict, task_id: int, user_id: str = None):
        engine_name = payload.get("engine")
        audio_path = payload.get("audio_path")
        language = payload.get("language")
        model_size = payload.get("model_size")
        kwargs = payload.get("kwargs", {})
        
        engine = get_stt_engine(engine_name)
        result_text = await engine.transcribe(
            audio_path=audio_path,
            language=language,
            model_size=model_size,
            **kwargs
        )
        
        if not result_text.startswith("Error:"):
            # Create STTResult record
            stt_record = STTResult(
                input_path=audio_path,
                engine=engine_name,
                model_size=model_size,
                transcript=result_text,
                language=language
            )
            db.add(stt_record)
            db.flush()
            return True, {"result_id": stt_record.id, "transcript": result_text[:200]}
            
        return False, {"error": result_text}

# Global worker instance
worker = TaskWorker()
