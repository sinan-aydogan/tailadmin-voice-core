"""
Voice Profile model.
"""
from datetime import datetime
from sqlalchemy import Column, Integer, String, DateTime
from app.models.base import Base

class VoiceProfile(Base):
    __tablename__ = "voice_profiles"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, nullable=False)
    description = Column(String)
    tags = Column(String) # Stored as JSON string
    audio_file_path = Column(String)
    engine = Column(String) # Optional: associate profile with a specific engine (xtts, bark, etc)
    language = Column(String, default="tr")
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
