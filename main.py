"""
FastAPI application entrypoint (Backend).
Serves API endpoints on port 5001.
"""
import sys
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from loguru import logger

from app.config import settings

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

app = FastAPI(
    title="TailAdmin Voice Core API",
    description="Multilingual Voice Cloning, TTS & STT Backend",
    version="1.0.0"
)

# CORS configuration
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Local development, UI will connect from 5002
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.on_event("startup")
async def startup_event():
    """Operations to perform when the server starts."""
    logger.info("Initializing TailAdmin Voice Core API...")
    # TODO: Initialize Database
    # TODO: Create default admin user if not exists

@app.get("/")
def read_root():
    return {"status": "ok", "service": "TailAdmin Voice Core API"}

if __name__ == "__main__":
    import uvicorn
    # If run directly via python main.py
    logger.info(f"Starting API Server on port {settings.API_PORT}...")
    uvicorn.run("main:app", host="0.0.0.0", port=settings.API_PORT, reload=True)
