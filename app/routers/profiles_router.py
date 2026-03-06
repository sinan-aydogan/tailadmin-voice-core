"""
Voice Profile API Endpoints.
"""
from fastapi import APIRouter, Depends, HTTPException, status, UploadFile, File
from sqlalchemy.orm import Session
from typing import List, Optional

from app.database import get_db
from app.auth.dependencies import get_current_user
from app.models.user import User
from app.models.profile import VoiceProfile
from app.schemas.profile import VoiceProfileCreate, VoiceProfileUpdate, VoiceProfileResponse
from app.profiles.profile_manager import ProfileManager

router = APIRouter(prefix="/profiles", tags=["profiles"])

@router.get("/", response_model=List[VoiceProfileResponse])
async def list_profiles(
    skip: int = 0, 
    limit: int = 100,
    engine: Optional[str] = None,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Retrieve all voice profiles."""
    query = db.query(VoiceProfile)
    if engine:
        query = query.filter(VoiceProfile.engine == engine)
    return query.order_by(VoiceProfile.created_at.desc()).offset(skip).limit(limit).all()

@router.post("/", response_model=VoiceProfileResponse, status_code=status.HTTP_201_CREATED)
async def create_profile(
    profile_in: VoiceProfileCreate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Create a new voice profile."""
    return ProfileManager.create(db, profile_in)

@router.get("/{profile_id}", response_model=VoiceProfileResponse)
async def get_profile(
    profile_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Get a specific voice profile by ID."""
    profile = ProfileManager.get(db, profile_id)
    if not profile:
        raise HTTPException(status_code=404, detail="Voice profile not found")
    return profile

@router.put("/{profile_id}", response_model=VoiceProfileResponse)
async def update_profile(
    profile_id: int,
    profile_in: VoiceProfileUpdate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Update a voice profile."""
    profile = ProfileManager.get(db, profile_id)
    if not profile:
        raise HTTPException(status_code=404, detail="Voice profile not found")
    return ProfileManager.update(db, profile, profile_in)

@router.delete("/{profile_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_profile(
    profile_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Delete a voice profile."""
    profile = ProfileManager.get(db, profile_id)
    if not profile:
        raise HTTPException(status_code=404, detail="Voice profile not found")
    ProfileManager.delete(db, profile)
    return None

@router.post("/{profile_id}/audio", response_model=VoiceProfileResponse)
async def upload_profile_audio(
    profile_id: int,
    file: UploadFile = File(...),
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    """Upload or update the reference audio for a voice profile."""
    profile = ProfileManager.get(db, profile_id)
    if not profile:
        raise HTTPException(status_code=404, detail="Voice profile not found")
        
    if not file.content_type.startswith("audio/"):
        raise HTTPException(status_code=400, detail="File must be an audio file")
        
    return await ProfileManager.upload_audio(db, profile, file)
