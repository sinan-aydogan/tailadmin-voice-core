"""
Task Queue and Model Download models.
"""
from datetime import datetime
from sqlalchemy import Column, Integer, String, Float, DateTime
from app.models.base import Base

class Task(Base):
    __tablename__ = "tasks"

    id = Column(Integer, primary_key=True, index=True)
    type = Column(String, nullable=False) # "tts" or "stt"
    status = Column(String, default="pending") # pending, running, done, failed, cancelled
    payload = Column(String, nullable=False) # JSON string
    result = Column(String) # JSON string (result or error)
    created_at = Column(DateTime, default=datetime.utcnow)
    started_at = Column(DateTime, nullable=True)
    completed_at = Column(DateTime, nullable=True)

class ModelDownload(Base):
    __tablename__ = "model_downloads"

    id = Column(Integer, primary_key=True, index=True)
    model_id = Column(String, unique=True, index=True, nullable=False)
    model_type = Column(String, nullable=False) # "tts" or "stt"
    status = Column(String, default="pending") # pending, downloading, completed, failed, verifying
    progress_pct = Column(Float, default=0.0)
    downloaded_bytes = Column(Integer, default=0)
    total_bytes = Column(Integer, default=0)
    error_message = Column(String)
    started_at = Column(DateTime, nullable=True)
    completed_at = Column(DateTime, nullable=True)
