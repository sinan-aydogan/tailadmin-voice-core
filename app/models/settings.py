"""
App Settings persistence model.
"""
from datetime import datetime
from sqlalchemy import Column, Integer, String, DateTime
from app.models.base import Base

class AppSettings(Base):
    __tablename__ = "app_settings"

    id = Column(Integer, primary_key=True, index=True)
    tab = Column(String, nullable=False) # tts, stt, hardware, storage, security, server, log
    key = Column(String, unique=True, index=True, nullable=False) # e.g. use_gpu
    value = Column(String) # JSON string representation
    description = Column(String, nullable=True) # Added to match router schema
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

