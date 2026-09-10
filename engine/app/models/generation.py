"""
TTS Output and STT Result models.
"""
from datetime import datetime
from sqlalchemy import Column, Integer, String, Float, DateTime, ForeignKey, Table
from sqlalchemy.orm import relationship
from app.models.base import Base

# Association table for Many-to-Many relationship between TTSOutput and Tag
tts_output_tags = Table(
    "tts_output_tags",
    Base.metadata,
    Column("tts_output_id", Integer, ForeignKey("tts_outputs.id"), primary_key=True),
    Column("tag_id", Integer, ForeignKey("tags.id"), primary_key=True)
)

class Tag(Base):
    __tablename__ = "tags"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, unique=True, index=True, nullable=False)
    color = Column(String, default="#3C50E0") # Default primary color
    created_at = Column(DateTime, default=datetime.utcnow)

    # Relationships
    outputs = relationship("TTSOutput", secondary=tts_output_tags, back_populates="tags")

class TTSOutput(Base):
    __tablename__ = "tts_outputs"

    id = Column(Integer, primary_key=True, index=True)
    text = Column(String, nullable=False)
    engine = Column(String, nullable=False)
    profile_id = Column(Integer, ForeignKey("voice_profiles.id"), nullable=True)
    language = Column(String, nullable=False)
    output_path = Column(String, nullable=False)
    duration_sec = Column(Float)
    created_at = Column(DateTime, default=datetime.utcnow)

    # Relationships
    tags = relationship("Tag", secondary=tts_output_tags, back_populates="outputs")

class STTResult(Base):
    __tablename__ = "stt_results"

    id = Column(Integer, primary_key=True, index=True)
    input_path = Column(String, nullable=False)
    engine = Column(String, nullable=False)
    model_size = Column(String)
    transcript = Column(String)
    language = Column(String)
    created_at = Column(DateTime, default=datetime.utcnow)
