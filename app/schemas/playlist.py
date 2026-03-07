"""
Playlist schemas for API.
"""
from typing import List, Optional
from datetime import datetime
from pydantic import BaseModel, ConfigDict


class PlaylistItemBase(BaseModel):
    """Base schema for playlist item."""
    text: str
    position: int = 0
    model_id: Optional[str] = None
    profile_id: Optional[int] = None


class PlaylistItemCreate(PlaylistItemBase):
    """Schema for creating a playlist item."""
    pass


class PlaylistItemUpdate(BaseModel):
    """Schema for updating a playlist item."""
    text: Optional[str] = None
    position: Optional[int] = None
    model_id: Optional[str] = None
    profile_id: Optional[int] = None


class PlaylistItemResponse(PlaylistItemBase):
    """Schema for playlist item response."""
    model_config = ConfigDict(from_attributes=True)
    
    id: int
    playlist_id: int
    status: str
    error_message: Optional[str] = None
    output_path: Optional[str] = None
    created_at: datetime
    started_at: Optional[datetime] = None
    completed_at: Optional[datetime] = None
    duration_seconds: Optional[int] = None


class PlaylistBase(BaseModel):
    """Base schema for playlist."""
    name: str
    description: Optional[str] = None
    use_single_model: bool = False
    single_model_id: Optional[str] = None
    single_profile_id: Optional[int] = None
    single_tag_id: Optional[int] = None


class PlaylistCreate(PlaylistBase):
    """Schema for creating a playlist."""
    items: List[PlaylistItemCreate] = []


class PlaylistUpdate(BaseModel):
    """Schema for updating a playlist."""
    name: Optional[str] = None
    description: Optional[str] = None
    use_single_model: Optional[bool] = None
    single_model_id: Optional[str] = None
    single_profile_id: Optional[int] = None
    single_tag_id: Optional[int] = None


class PlaylistResponse(PlaylistBase):
    """Schema for playlist response."""
    model_config = ConfigDict(from_attributes=True)
    
    id: int
    status: str
    total_items: int
    completed_items: int
    failed_items: int
    progress_percentage: int
    created_at: datetime
    started_at: Optional[datetime] = None
    completed_at: Optional[datetime] = None
    duration_seconds: Optional[int] = None
    items: List[PlaylistItemResponse] = []


class PlaylistListResponse(BaseModel):
    """Schema for playlist list response (without items)."""
    model_config = ConfigDict(from_attributes=True)
    
    id: int
    name: str
    description: Optional[str] = None
    status: str
    total_items: int
    completed_items: int
    failed_items: int
    progress_percentage: int
    use_single_model: bool
    created_at: datetime
    started_at: Optional[datetime] = None
    completed_at: Optional[datetime] = None
    duration_seconds: Optional[int] = None


class PlaylistReorderRequest(BaseModel):
    """Schema for reordering playlist items."""
    item_ids: List[int]  # Ordered list of item IDs


class PlaylistProcessRequest(BaseModel):
    """Schema for starting playlist processing."""
    pass  # Just start processing


class PlaylistStatusResponse(BaseModel):
    """Schema for playlist status update."""
    status: str  # start, pause, resume, stop
