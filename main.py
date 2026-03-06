"""
FastAPI application entrypoint (Backend).
Serves API endpoints on port 5001.
"""
import sys
import asyncio
from fastapi import FastAPI, WebSocket, WebSocketDisconnect, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from loguru import logger

from app.config import settings
from app.database import SessionLocal
from app.db_init import init_db, create_default_admin
from app.auth.router import router as auth_router
from app.auth.dependencies import get_current_user_ws
from app.models.user import User
from app.routers.tts_router import router as tts_router
from app.routers.stt_router import router as stt_router
from app.routers.profiles_router import router as profiles_router
from app.routers.models_router import router as models_router
from app.routers.queue_router import router as queue_router
from app.routers.settings_router import router as settings_router
from app.routers.logs_router import router as logs_router
from app.routers.tags_router import router as tags_router
from app.queue.worker import worker
from app.websocket import manager, notification_manager
import asyncio

# Configure logging
logger.remove()
logger.add(
    sys.stdout,
    colorize=True,
    format="<green>{time:YYYY-MM-DD HH:mm:ss}</green> | <level>{level: <8}</level> | <cyan>{name}</cyan>:<cyan>{function}</cyan>:<cyan>{line}</cyan> - <level>{message}</level>",
    level=settings.LOG_LEVEL
)
logger.add(
    "logs/app.log",
    rotation="50 MB",
    retention=f"{settings.LOG_RETENTION_DAYS} days",
    level=settings.LOG_LEVEL
)

from contextlib import asynccontextmanager

@asynccontextmanager
async def lifespan(app: FastAPI):
    """Lifecycle events for the FastAPI application."""
    logger.info("Initializing TailAdmin Voice Core API...")
    
    # Set event loop for notification manager (for thread-safe operations)
    notification_manager.set_event_loop(asyncio.get_event_loop())
    
    # Initialize Database
    init_db()
    # Create default user if not exists
    create_default_admin()
    
    # Global patches
    from app.utils.tqdm_handler import patch_huggingface_tqdm
    patch_huggingface_tqdm()
    
    # Start background worker
    worker_task = asyncio.create_task(worker.start())
    
    yield
    
    # Shutdown
    logger.info("Shutting down TailAdmin Voice Core API...")
    await worker.stop()
    worker_task.cancel()
    try:
        await worker_task
    except asyncio.CancelledError:
        pass

app = FastAPI(
    title="TailAdmin Voice Core API",
    description="Multilingual Voice Cloning, TTS & STT Backend",
    version="1.0.0",
    lifespan=lifespan
)

# CORS configuration
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Local development, UI will connect from 5002
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include routers
app.include_router(auth_router)
app.include_router(tts_router)
app.include_router(stt_router)
app.include_router(profiles_router)
app.include_router(models_router)
app.include_router(queue_router)
app.include_router(settings_router)
app.include_router(logs_router)
app.include_router(tags_router)

# Serve data directory for audio/model files
app.mount("/data", StaticFiles(directory="data"), name="data")


@app.websocket("/ws/notifications")
async def websocket_notifications(websocket: WebSocket, token: str = None):
    """
    WebSocket endpoint for real-time notifications.
    
    Connect with: ws://localhost:5001/ws/notifications?token=YOUR_JWT_TOKEN
    """
    user = None
    user_id = None
    
    # Authenticate the connection
    if token:
        try:
            user = await get_current_user_ws(token)
            user_id = str(user.id)
        except Exception as e:
            logger.warning(f"WebSocket authentication failed: {e}")
            await websocket.close(code=4001, reason="Authentication failed")
            return
    else:
        await websocket.close(code=4001, reason="Token required")
        return
    
    await manager.connect(websocket, user_id)
    
    try:
        # Send initial connection success message
        await websocket.send_json({
            "event": "connected",
            "payload": {
                "user_id": user_id,
                "message": "WebSocket connection established"
            }
        })
        
        # Send any pending notifications
        pending_notifications = notification_manager.get_user_notifications(user_id)
        if pending_notifications:
            await websocket.send_json({
                "event": "pending_notifications",
                "payload": pending_notifications
            })
        
        # Keep the connection alive and handle incoming messages
        while True:
            try:
                # Wait for messages from client (ping/acknowledgments)
                data = await websocket.receive_json()
                
                # Handle client messages
                if data.get("action") == "ping":
                    await websocket.send_json({"event": "pong"})
                elif data.get("action") == "mark_read":
                    notification_id = data.get("notification_id")
                    if notification_id:
                        notification_manager.mark_notification_read(user_id, notification_id)
                elif data.get("action") == "mark_all_read":
                    notification_manager.mark_all_read(user_id)
                elif data.get("action") == "clear_notifications":
                    notification_manager.clear_notifications(user_id)
                    
            except Exception as e:
                logger.error(f"Error handling WebSocket message: {e}")
                break
                
    except WebSocketDisconnect:
        logger.info(f"WebSocket disconnected for user {user_id}")
    except Exception as e:
        logger.error(f"WebSocket error for user {user_id}: {e}")
    finally:
        manager.disconnect(websocket)


@app.get("/")
def read_root():
    return {"status": "ok", "service": "TailAdmin Voice Core API"}


@app.get("/health")
def health_check():
    """Health check endpoint."""
    return {
        "status": "healthy",
        "websocket_connections": manager.get_connection_count()
    }

if __name__ == "__main__":
    import uvicorn
    # If run directly via python main.py
    logger.info(f"Starting API Server on port {settings.API_PORT}...")
    uvicorn.run("main:app", host="0.0.0.0", port=settings.API_PORT, reload=True)
