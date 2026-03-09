from typing import Optional, List
from datetime import datetime
from pydantic import BaseModel, ConfigDict
from app.schemas.tag import TagResponse

class TTSGenerateRequest(BaseModel):
    text: str
    engine: Optional[str] = None
    profile_id: Optional[int] = None
    language: Optional[str] = None
    tag_ids: Optional[List[int]] = []
    # engine-specific kwargs
    speed_factor: Optional[float] = 1.0
    max_length: Optional[int] = None  # For MusicGen duration (tokens)

class TTSOutputResponse(BaseModel):
    id: int
    text: str
    engine: str
    profile_id: Optional[int] = None
    profile_name: Optional[str] = None  # Added for display
    language: str
    output_path: str
    duration_sec: Optional[float] = None
    created_at: datetime
    tags: List[TagResponse] = []

    model_config = ConfigDict(from_attributes=True)
