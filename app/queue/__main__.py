"""
Standalone queue worker process.

Run with:  python -m app.queue

Why a separate process: model inference (torch on multi-GB checkpoints) holds the
GIL for long stretches. When the worker lived inside the API process this froze the
API event loop — every HTTP/WS request stalled during a generation. As its own OS
process the worker has its own GIL, so the API stays responsive no matter how heavy
inference gets.

This process does NOT run migrations (the API owns `init_db`); it only reads/writes
via SessionLocal against the already-initialised DB. WebSocket notifications are
forwarded to the API over HTTP (the API owns the WS connections).
"""
import os
import sys
import asyncio
import time

from loguru import logger

from app.config import settings
from app.database import SessionLocal
from app.websocket import notification_manager
from app.queue.worker import worker


def _configure_runtime():
    """E2: keep a couple of cores free so torch inference doesn't starve everything."""
    try:
        import torch
        n = max(1, (os.cpu_count() or 4) - 2)
        torch.set_num_threads(n)
        logger.info(f"torch.set_num_threads({n})")
    except Exception as e:
        logger.debug(f"Could not set torch threads: {e}")


def _wait_for_db(timeout: float = 30.0):
    """Wait until the API has created the schema before the worker touches the DB."""
    from sqlalchemy import text
    deadline = time.time() + timeout
    while time.time() < deadline:
        db = SessionLocal()
        try:
            db.execute(text("SELECT 1 FROM tasks LIMIT 1"))
            return True
        except Exception:
            time.sleep(1.0)
        finally:
            db.close()
    logger.warning("DB not ready after wait; starting anyway.")
    return False


async def main():
    logger.info("Standalone queue worker starting...")

    # Route notifications to the API process (it owns the WebSocket connections).
    notify_url = f"http://127.0.0.1:{settings.API_PORT}/internal/notify"
    notification_manager.enable_remote_mode(notify_url, settings.INTERNAL_NOTIFY_TOKEN)

    _configure_runtime()
    _wait_for_db()

    await worker.start()  # spawns the processing loop; also resets stale 'running' tasks

    # Keep the process alive; the loop runs as a background task.
    while True:
        await asyncio.sleep(3600)


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("Worker interrupted, exiting.")
        sys.exit(0)
