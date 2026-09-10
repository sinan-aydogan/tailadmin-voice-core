"""
Voice Profile Schemas.
"""
from typing import Optional
from datetime import datetime
from pydantic import BaseModel, ConfigDict

class VoiceProfileBase(BaseModel):
    name: str
    description: Optional[str] = None
    tags: Optional[str] = "[]" # JSON string list
    language: Optional[str] = "tr"
    engine: Optional[str] = None

class VoiceProfileCreate(VoiceProfileBase):
    pass

class VoiceProfileUpdate(BaseModel):
    name: Optional[str] = None
    description: Optional[str] = None
    tags: Optional[str] = None
    language: Optional[str] = None
    engine: Optional[str] = None

class VoiceProfileResponse(VoiceProfileBase):
    id: int
    audio_file_path: Optional[str] = None
    created_at: datetime
    updated_at: datetime

    model_config = ConfigDict(from_attributes=True)
