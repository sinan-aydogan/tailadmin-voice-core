from typing import Optional, List
from datetime import datetime
from pydantic import BaseModel, ConfigDict

class TagBase(BaseModel):
    name: str
    color: Optional[str] = "#3C50E0"

class TagCreate(TagBase):
    pass

class TagUpdate(TagBase):
    name: Optional[str] = None

class TagResponse(TagBase):
    id: int
    created_at: datetime

    model_config = ConfigDict(from_attributes=True)
