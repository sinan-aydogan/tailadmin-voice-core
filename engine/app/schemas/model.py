"""
Model Download and App Settings Schemas.
"""
from typing import Optional, Any
from datetime import datetime
from pydantic import BaseModel, ConfigDict

class ModelDownloadResponse(BaseModel):
    id: int
    model_id: str
    model_type: str
    status: str
    progress_pct: float
    downloaded_bytes: int
    total_bytes: int
    error_message: Optional[str] = None
    started_at: Optional[datetime] = None
    completed_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)

class AppSettingItem(BaseModel):
    tab: str
    key: str
    value: str # JSON representation of the value

    model_config = ConfigDict(from_attributes=True)

class AppSettingsUpdateRequest(BaseModel):
    settings: dict[str, Any] # Map of key -> value (will be serialized to strings in DB)
