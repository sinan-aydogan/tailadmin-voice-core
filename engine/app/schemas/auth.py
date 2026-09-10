"""
User and Authentication Schemas.
"""
from typing import Optional
from datetime import datetime
from pydantic import BaseModel, ConfigDict

# Token schemas
class Token(BaseModel):
    access_token: str
    token_type: str
    expires_in: int

class TokenData(BaseModel):
    username: Optional[str] = None

# User schemas
class UserBase(BaseModel):
    username: str

class UserCreate(UserBase):
    password: str

class UserUpdate(BaseModel):
    password: Optional[str] = None

class UserResponse(UserBase):
    id: int
    is_active: bool
    created_at: datetime
    theme: Optional[str] = "light"
    language: Optional[str] = "tr"

    model_config = ConfigDict(from_attributes=True)

# User preferences schemas
class UserPreferencesResponse(BaseModel):
    theme: str
    language: str

class UserPreferencesUpdate(BaseModel):
    theme: Optional[str] = None
    language: Optional[str] = None
