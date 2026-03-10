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
from app.models.playlist import PlaylistItem, PlaylistItemStatus
from app.tts.registry import get_tts_engine
from app.stt.registry import get_stt_engine
from app.websocket import notification_manager

class TaskWorker:
    def __init__(self, max_concurrent=1):
        self.is_running = False
        self._task = None
        self.max_concurrent = max_concurrent
        self._semaphore = asyncio.Semaphore(max_concurrent)
        self._running_tasks = set()

    async def start(self):
        """Start the background worker loop."""
        if self.is_running:
            return
        self.is_running = True
        
        # Patch HF for captured downloads in worker process
        from app.utils.tqdm_handler import patch_huggingface_tqdm
        patch_huggingface_tqdm()
        
        logger.info(f"Starting background task worker (max concurrent: {self.max_concurrent})...")
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
                # Check if we can start more tasks
                if len(self._running_tasks) < self.max_concurrent:
                    # Try to get a pending task
                    task_id = await self._get_pending_task()
                    if task_id:
                        # Start task in background (doesn't block)
                        task_coro = self._process_task_with_semaphore(task_id)
                        task_future = asyncio.create_task(task_coro)
                        self._running_tasks.add(task_future)
                        task_future.add_done_callback(self._running_tasks.discard)
                
                # Clean up completed tasks
                done_tasks = [t for t in self._running_tasks if t.done()]
                for t in done_tasks:
                    self._running_tasks.discard(t)
                    try:
                        await t  # Get any exceptions
                    except Exception as e:
                        logger.error(f"Task error: {e}")
                        
            except asyncio.CancelledError:
                break
            except Exception as e:
                logger.error(f"Worker loop error: {e}")
            
            # Prevent hot loop - check more frequently when tasks are running
            await asyncio.sleep(0.5 if self._running_tasks else 2)
    
    async def _get_pending_task(self):
        """Get next pending task from DB, returns task ID only."""
        db = SessionLocal()
        try:
            task = db.query(Task).filter(Task.status == "pending").order_by(Task.created_at.asc()).first()
            if task:
                # Mark as running immediately to prevent other workers from picking it up
                task_id = task.id
                task.status = "running"
                task.started_at = datetime.utcnow()
                db.commit()
                return task_id  # Return only the ID, not the ORM object
            return None
        finally:
            db.close()
    
    async def _process_task_with_semaphore(self, task_id: int):
        """Process a task with semaphore control."""
        async with self._semaphore:
            await self._execute_task(task_id)

    async def _execute_task(self, task_id: int):
        """Execute a single task (called by semaphore-controlled wrapper)."""
        db = SessionLocal()
        try:
            # Fetch task fresh from DB using the ID
            task = db.query(Task).filter(Task.id == task_id).first()
            if not task:
                return
            
            logger.info(f"Worker picked up task {task.id} (type: {task.type})")
            
            # Update playlist item status to PROCESSING if this is a playlist task
            payload = json.loads(task.payload)
            playlist_item_id = payload.get("playlist_item_id")
            if playlist_item_id:
                from app.models.playlist import PlaylistItem, PlaylistItemStatus
                playlist_item = db.query(PlaylistItem).filter(PlaylistItem.id == playlist_item_id).first()
                if playlist_item:
                    playlist_item.status = PlaylistItemStatus.PROCESSING
                    db.commit()
                    logger.info(f"Updated playlist item {playlist_item_id} to PROCESSING")
            
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
        engine_name = payload.get("engine") or payload.get("model_id")
        text = payload.get("text")
        language = payload.get("language", "tr")
        output_path = payload.get("output_path")
        profile_id = payload.get("profile_id")
        profile_path = payload.get("profile_path")
        playlist_item_id = payload.get("playlist_item_id")
        kwargs = payload.get("kwargs", {})
        
        logger.info(f"[Task {task_id}] Starting TTS processing - Engine: {engine_name}, Text length: {len(text) if text else 0}, Profile: {profile_id}")
        
        # Update playlist item status if applicable
        playlist_item = None
        if playlist_item_id:
            playlist_item = db.query(PlaylistItem).filter(PlaylistItem.id == playlist_item_id).first()
            if playlist_item:
                playlist_item.status = PlaylistItemStatus.PROCESSING
                db.commit()
        
        try:
            logger.info(f"[Task {task_id}] Initializing TTS engine: {engine_name}")
            engine = get_tts_engine(engine_name)
            logger.info(f"[Task {task_id}] Engine initialized, starting audio generation...")
            
            success = await engine.generate_audio(
                text=text,
                output_path=output_path,
                language=language,
                profile_path=profile_path,
                user_id=user_id,
                **kwargs
            )
            logger.info(f"[Task {task_id}] Audio generation completed with success={success}")
            
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
                
                # Update playlist item on success
                if playlist_item:
                    playlist_item.status = PlaylistItemStatus.COMPLETED
                    playlist_item.output_path = output_path
                    db.commit()
                
                return True, {"output_id": tts_record.id, "output_path": output_path, "text": text[:100]}
            else:
                # Update playlist item on failure
                if playlist_item:
                    playlist_item.status = PlaylistItemStatus.FAILED
                    playlist_item.error_message = "Engine generation failed"
                    db.commit()
                return False, {"error": "Engine generation failed"}
                
        except Exception as e:
            logger.error(f"TTS processing error: {e}")
            # Update playlist item on exception
            if playlist_item:
                playlist_item.status = PlaylistItemStatus.FAILED
                playlist_item.error_message = str(e)
                db.commit()
            raise

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


def submit_tts_task(text: str, model_id: str, profile_id: int = None, user_id: int = None, playlist_item_id: int = None) -> Task:
    """
    Submit a TTS task to the queue.
    
    Args:
        text: Text to synthesize
        model_id: TTS model identifier
        profile_id: Optional voice profile ID
        user_id: User ID for the task
        playlist_item_id: Optional playlist item ID to update status
        
    Returns:
        Created Task object
    """
    import json
    import uuid
    db = SessionLocal()
    try:
        # Generate output path
        output_filename = f"{uuid.uuid4().hex}.wav"
        output_path = f"data/outputs/tts/{output_filename}"
        
        payload = {
            "text": text,
            "model_id": model_id,
            "profile_id": profile_id,
            "playlist_item_id": playlist_item_id,
            "output_path": output_path
        }
        
        task = Task(
            type="tts",
            status="pending",
            payload=json.dumps(payload)
        )
        db.add(task)
        db.commit()
        db.refresh(task)
        logger.info(f"Submitted TTS task {task.id} for playlist item {playlist_item_id}")
        return task
    finally:
        db.close()


# Global worker instance
worker = TaskWorker()
