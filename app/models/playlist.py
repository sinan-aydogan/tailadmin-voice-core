"""
Playlist (Job List) models for batch TTS processing.
"""
from datetime import datetime
from sqlalchemy import Column, Integer, String, Boolean, DateTime, ForeignKey, Text, Enum
from sqlalchemy.orm import relationship
from app.models.base import Base
import enum


class PlaylistStatus(str, enum.Enum):
    """Playlist processing status."""
    PENDING = "pending"      # Not started yet
    PROCESSING = "processing"  # Currently processing
    COMPLETED = "completed"   # All items done
    PAUSED = "paused"        # User paused
    FAILED = "failed"        # Some items failed


class PlaylistItemStatus(str, enum.Enum):
    """Individual item status in playlist."""
    QUEUED = "queued"        # Waiting in queue
    PROCESSING = "processing"  # Currently being processed
    COMPLETED = "completed"   # Done successfully
    FAILED = "failed"        # Processing failed


class Playlist(Base):
    """A playlist/job list for batch TTS processing."""
    __tablename__ = "playlists"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, nullable=False)
    description = Column(Text, nullable=True)
    
    # Processing configuration
    use_single_model = Column(Boolean, default=False)  # If True, all items use same model
    single_model_id = Column(String, nullable=True)    # Model ID if single_model is True
    single_profile_id = Column(Integer, nullable=True) # Profile ID if single_model is True
    single_tag_id = Column(Integer, nullable=True)     # Tag ID if single_model is True
    single_language = Column(String, nullable=True)    # Language code if single_model is True
    
    # Status tracking
    status = Column(String, default=PlaylistStatus.PENDING)
    total_items = Column(Integer, default=0)
    completed_items = Column(Integer, default=0)
    failed_items = Column(Integer, default=0)
    
    # Timestamps
    created_at = Column(DateTime, default=datetime.utcnow)
    started_at = Column(DateTime, nullable=True)
    completed_at = Column(DateTime, nullable=True)
    
    # User relationship (for future multi-user support)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=True)
    user = relationship("User", back_populates="playlists")
    
    # Relationship to items
    items = relationship("PlaylistItem", back_populates="playlist", 
                        order_by="PlaylistItem.position", cascade="all, delete-orphan")
    
    def get_duration_seconds(self):
        """Calculate total processing duration in seconds."""
        if self.started_at and self.completed_at:
            return int((self.completed_at - self.started_at).total_seconds())
        return None
    
    def get_progress_percentage(self):
        """Get processing progress as percentage."""
        if self.total_items == 0:
            return 0
        return int((self.completed_items / self.total_items) * 100)


class PlaylistItem(Base):
    """Individual TTS job in a playlist."""
    __tablename__ = "playlist_items"

    id = Column(Integer, primary_key=True, index=True)
    playlist_id = Column(Integer, ForeignKey("playlists.id"), nullable=False)
    
    # Content
    text = Column(Text, nullable=False)
    position = Column(Integer, default=0)  # For ordering
    
    # TTS Configuration (if use_single_model is False)
    model_id = Column(String, nullable=True)
    profile_id = Column(Integer, nullable=True)
    language = Column(String, nullable=True)
    
    # Status
    status = Column(String, default=PlaylistItemStatus.QUEUED)
    error_message = Column(Text, nullable=True)
    
    # Output
    output_path = Column(String, nullable=True)
    
    # Timestamps
    created_at = Column(DateTime, default=datetime.utcnow)
    started_at = Column(DateTime, nullable=True)
    completed_at = Column(DateTime, nullable=True)
    
    # Relationship
    playlist = relationship("Playlist", back_populates="items")
    
    def get_duration_seconds(self):
        """Calculate processing duration for this item."""
        if self.started_at and self.completed_at:
            return int((self.completed_at - self.started_at).total_seconds())
        return None
