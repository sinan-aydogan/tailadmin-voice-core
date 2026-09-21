"""
FastAPI application entrypoint (Backend).
Serves API endpoints on port 5001.
"""
import sys
import os

# On Windows, ensure SystemRoot and WINDIR are present in os.environ before
# importing asyncio / Winsock to prevent WSAEPROVIDERFAILEDINIT (WinError 10106).
if sys.platform == "win32":
    if "SystemRoot" not in os.environ and "SYSTEMROOT" not in os.environ:
        os.environ["SystemRoot"] = os.environ.get("WINDIR", r"C:\Windows")
    if "WINDIR" not in os.environ and "windir" not in os.environ:
        os.environ["WINDIR"] = os.environ.get("SystemRoot", r"C:\Windows")

import asyncio
from fastapi import FastAPI, WebSocket, WebSocketDisconnect, Depends, Request, Header, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.openapi.docs import get_swagger_ui_html
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
from app.routers.llm_router import router as llm_router
from app.routers.playlist_router import router as playlist_router
from app.routers.system_router import router as system_router
from app.queue.worker import worker
from app.websocket import manager, notification_manager

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
    notification_manager.set_event_loop(asyncio.get_running_loop())

    # Initialize Database
    init_db()
    # Create default user if not exists
    create_default_admin()

    # Recover from an unclean shutdown: any download left in "downloading" was
    # interrupted, and any model whose files disagree with its DB status gets fixed.
    try:
        from app.downloader.download_manager import DownloadManager
        from app.downloader.integrity_checker import repair_model_status, MODEL_INTEGRITY_CHECKS
        db = SessionLocal()
        try:
            DownloadManager.reset_interrupted_downloads(db)
        finally:
            db.close()
        for model_id in MODEL_INTEGRITY_CHECKS.keys():
            try:
                repair_model_status(model_id)
            except Exception as e:
                logger.debug(f"repair_model_status({model_id}) failed: {e}")
    except Exception as e:
        logger.warning(f"Startup download recovery failed: {e}")

    # Global patches
    from app.utils.tqdm_handler import patch_huggingface_tqdm
    patch_huggingface_tqdm()

    # Pre-load selected TTS models into RAM to avoid first-request delay. Controlled
    # by PRELOAD_MODELS ("" = none, "all" = every healthy model, else a CSV of ids).
    async def preload_models():
        try:
            from app.tts.registry import get_tts_engine
            from app.downloader.integrity_checker import get_ready_for_tts

            spec = (settings.PRELOAD_MODELS or "").strip()
            if not spec:
                return

            ready_models = get_ready_for_tts()
            if spec.lower() != "all":
                wanted = {s.strip() for s in spec.split(",") if s.strip()}
                ready_models = [m for m in ready_models if m in wanted]

            if not ready_models:
                return

            logger.info(f"Pre-loading TTS models: {ready_models}")
            # Model id -> registry engine name.
            model_to_engine = {
                "xtts-v2": "xtts",
                "bark": "bark",
                "tortoise": "tortoise",
                "piper-tr": "piper-tr",
                "piper-en": "piper-en",
            }
            for model_id in ready_models:
                engine_name = model_to_engine.get(model_id)
                if not engine_name:
                    continue
                try:
                    logger.info(f"Pre-loading {engine_name} engine...")
                    engine = get_tts_engine(engine_name)
                    if hasattr(engine, 'load_model'):
                        await asyncio.to_thread(engine.load_model)
                        logger.success(f"{engine_name} model pre-loaded successfully")
                except Exception as e:
                    logger.warning(f"Could not pre-load {engine_name}: {e}")
            logger.success("Model pre-loading completed")
        except Exception as e:
            logger.warning(f"Could not pre-load models: {e}")

    # Start pre-loading in background
    preload_task = asyncio.create_task(preload_models())

    # Start background worker — only when running in-process. In "separate" mode the
    # worker is its own OS process (`python -m app.queue`) so heavy inference never
    # blocks this event loop; here we just skip starting it.
    worker_task = None
    if settings.WORKER_MODE == "separate":
        logger.info("WORKER_MODE=separate: in-process worker disabled (expecting standalone worker).")
    else:
        worker_task = asyncio.create_task(worker.start())

    # Periodically broadcast system resource stats to the UI footer (only while
    # at least one client is connected, to avoid needless work).
    async def system_stats_loop():
        from app.routers.system_router import collect_system_stats
        interval = max(1.0, settings.SYSTEM_STATS_INTERVAL_SEC)
        while True:
            try:
                if manager.get_connection_count() > 0:
                    stats = await asyncio.to_thread(collect_system_stats)
                    await manager.broadcast({"event": "system_stats", "payload": stats})
            except asyncio.CancelledError:
                break
            except Exception as e:
                logger.debug(f"system_stats_loop error: {e}")
            await asyncio.sleep(interval)

    stats_task = None
    if settings.SYSTEM_STATS_ENABLED:
        stats_task = asyncio.create_task(system_stats_loop())

    yield

    # Shutdown
    logger.info("Shutting down TailAdmin Voice Core API...")
    if stats_task:
        stats_task.cancel()
    if worker_task:
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
    lifespan=lifespan,
    docs_url=None,  # Disable default docs to use custom dark mode swagger
    redoc_url=None
)

# Custom Swagger UI with dark mode support
@app.get("/docs", include_in_schema=False)
async def custom_swagger_ui_html():
    return get_swagger_ui_html(
        openapi_url=app.openapi_url,
        title=f"{app.title} - Swagger UI",
        swagger_js_url="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.9.0/swagger-ui-bundle.js",
        swagger_css_url="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.9.0/swagger-ui.css",
        swagger_favicon_url="/static/favicon.ico",
        init_oauth={},
        swagger_ui_parameters={
            "syntaxHighlight.theme": "monokai",
            "tryItOutEnabled": True,
            "persistAuthorization": True,
        }
    )

# Dark mode CSS injection for Swagger
@app.get("/docs-dark.css", include_in_schema=False)
async def swagger_dark_css():
    from fastapi.responses import PlainTextResponse
    css = '''
    /* Swagger Dark Mode */
    html, body { background-color: #0f172a !important; }
    .swagger-ui { background-color: #0f172a; filter: invert(88%) hue-rotate(180deg); }
    .swagger-ui .topbar { display: none; }
    .swagger-ui .info .title { color: #3b4151; }
    .swagger-ui .scheme-container { background: #1e293b; }
    .swagger-ui .opblock .opblock-summary-method { background: #3b4151; }
    .swagger-ui .opblock { background: #1e293b; border-color: #334155; }
    .swagger-ui .opblock .opblock-summary { border-color: #334155; }
    .swagger-ui .btn { background: #3b82f6; color: white; }
    .swagger-ui select { background: #1e293b; border-color: #334155; color: #e2e8f0; }
    .swagger-ui input[type=text] { background: #1e293b; border-color: #334155; color: #e2e8f0; }
    .swagger-ui textarea { background: #1e293b; border-color: #334155; color: #e2e8f0; }
    .swagger-ui .model-box { background: #1e293b; }
    .swagger-ui .model { color: #e2e8f0; }
    .swagger-ui .prop-type { color: #60a5fa; }
    .swagger-ui .response-content-type { color: #e2e8f0; }
    .swagger-ui table thead tr th { color: #e2e8f0; border-bottom-color: #334155; }
    .swagger-ui table tbody tr td { color: #cbd5e1; border-bottom-color: #334155; }
    .swagger-ui .parameter__name { color: #e2e8f0; }
    .swagger-ui .parameter__type { color: #94a3b8; }
    .swagger-ui .opblock-description-wrapper p { color: #cbd5e1; }
    .swagger-ui .responses-inner h4 { color: #e2e8f0; }
    .swagger-ui .responses-inner .response-col_status { color: #e2e8f0; }
    .swagger-ui .microlight { filter: invert(100%) hue-rotate(180deg); }
    .swagger-ui .curl-command { filter: invert(100%) hue-rotate(180deg); }
    .swagger-ui .highlight-code { filter: invert(100%) hue-rotate(180deg); }
    .swagger-ui .opblock-body pre.microlight { background: #1e1e1e !important; }
    '''
    return PlainTextResponse(content=css, media_type="text/css")

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
app.include_router(llm_router)
app.include_router(playlist_router)
app.include_router(system_router)

# Serve data directory for audio/model files only
# Static UI files are served by a separate frontend server on port 5002
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
                # Wait for messages from client with timeout
                data = await asyncio.wait_for(websocket.receive_json(), timeout=60.0)
                
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
                    
            except asyncio.TimeoutError:
                # Send ping to keep connection alive
                try:
                    await websocket.send_json({"event": "ping"})
                except Exception:
                    break
            except Exception as e:
                error_code = getattr(e, 'code', None)
                # 1001 is normal closure (page refresh, navigation)
                if error_code == 1001:
                    logger.debug(f"WebSocket normal closure for user {user_id}")
                else:
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


@app.post("/internal/notify")
async def internal_notify(request: Request, x_internal_token: str = Header(default=None)):
    """Internal bridge: a separate worker process pushes WS notifications through here.

    Protected by a shared token AND restricted to loopback callers. The API owns the
    WebSocket connections, so it re-emits the notification locally via the same path a
    normal in-process notification would take.
    """
    if x_internal_token != settings.INTERNAL_NOTIFY_TOKEN:
        raise HTTPException(status_code=403, detail="Forbidden")
    client_host = request.client.host if request.client else ""
    if client_host not in ("127.0.0.1", "::1", "localhost"):
        raise HTTPException(status_code=403, detail="Loopback only")

    body = await request.json()
    from app.websocket.notification_manager import NotificationType, NotificationPriority
    try:
        ntype = NotificationType(body["type"])
    except ValueError:
        raise HTTPException(status_code=400, detail=f"Unknown notification type: {body.get('type')}")
    try:
        priority = NotificationPriority(body.get("priority", "normal"))
    except ValueError:
        priority = NotificationPriority.NORMAL

    await notification_manager.send_notification(
        user_id=body["user_id"],
        notification_type=ntype,
        title=body.get("title", ""),
        message=body.get("message", ""),
        data=body.get("data"),
        priority=priority,
        store=body.get("store", True),
    )
    return {"ok": True}

if __name__ == "__main__":
    import uvicorn
    # If run directly via python main.py
    logger.info(f"Starting API Server on port {settings.API_PORT} (reload={settings.RELOAD})...")
    # reload=True KILLS in-flight downloads/tasks on any file save and can leak the
    # port; keep it off by default (RELOAD env var toggles it for development).
    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=settings.API_PORT,
        reload=settings.RELOAD,
        loop="uvloop",   # Faster event loop, reduces blocking
        workers=1        # Single worker to share model state
    )
