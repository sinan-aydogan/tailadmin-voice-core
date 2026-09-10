"""
STT transcription schemas.
"""
from typing import Optional
from datetime import datetime
from pydantic import BaseModel, ConfigDict

class STTTranscribeRequest(BaseModel):
    engine: Optional[str] = None
    model_size: Optional[str] = None
    language: Optional[str] = None # Or "auto"

class STTResultResponse(BaseModel):
    id: int
    input_path: str
    engine: str
    model_size: Optional[str] = None
    transcript: str
    language: Optional[str] = None
    created_at: datetime

    model_config = ConfigDict(from_attributes=True)
