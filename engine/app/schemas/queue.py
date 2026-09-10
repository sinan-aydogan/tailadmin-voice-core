"""
Task Queue Schemas.
"""
from typing import Optional, Any
from datetime import datetime
from pydantic import BaseModel, ConfigDict
import json

class TaskCreate(BaseModel):
    type: str
    payload: dict # Will be serialized to JSON string

class TaskResponse(BaseModel):
    id: int
    type: str
    status: str
    payload: str
    result: Optional[str] = None
    created_at: datetime
    started_at: Optional[datetime] = None
    completed_at: Optional[datetime] = None

    model_config = ConfigDict(from_attributes=True)

    @property
    def payload_dict(self) -> dict:
        return json.loads(self.payload) if self.payload else {}
        
    @property
    def result_dict(self) -> dict:
        return json.loads(self.result) if self.result else {}
