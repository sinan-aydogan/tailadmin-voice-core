"""
Voice Profile Manager for handling CRUD operations and file storage.
"""
import os
import aiofiles
from fastapi import UploadFile
from sqlalchemy.orm import Session
from loguru import logger

from app.models.profile import VoiceProfile
from app.schemas.profile import VoiceProfileCreate, VoiceProfileUpdate
from app.config import settings

class ProfileManager:
    """Handles business logic for Voice Profiles."""
    
    @staticmethod
    def get_all(db: Session, skip: int = 0, limit: int = 100):
        return db.query(VoiceProfile).order_by(VoiceProfile.created_at.desc()).offset(skip).limit(limit).all()
        
    @staticmethod
    def get(db: Session, profile_id: int):
        return db.query(VoiceProfile).filter(VoiceProfile.id == profile_id).first()
        
    @staticmethod
    def create(db: Session, profile_in: VoiceProfileCreate) -> VoiceProfile:
        db_profile = VoiceProfile(
            name=profile_in.name,
            description=profile_in.description,
            tags=profile_in.tags,
            language=profile_in.language,
            engine=profile_in.engine
        )
        db.add(db_profile)
        db.commit()
        db.refresh(db_profile)
        logger.info(f"Created new voice profile: {db_profile.name} (ID: {db_profile.id})")
        return db_profile
        
    @staticmethod
    def update(db: Session, db_profile: VoiceProfile, profile_in: VoiceProfileUpdate) -> VoiceProfile:
        update_data = profile_in.model_dump(exclude_unset=True)
        for key, value in update_data.items():
            setattr(db_profile, key, value)
            
        db.add(db_profile)
        db.commit()
        db.refresh(db_profile)
        logger.info(f"Updated voice profile (ID: {db_profile.id})")
        return db_profile
        
    @staticmethod
    def delete(db: Session, db_profile: VoiceProfile) -> bool:
        # Also remove the associated file if it exists
        if db_profile.audio_file_path and os.path.exists(db_profile.audio_file_path):
            try:
                os.remove(db_profile.audio_file_path)
            except Exception as e:
                logger.error(f"Failed to delete audio file {db_profile.audio_file_path}: {e}")
                
        db.delete(db_profile)
        db.commit()
        logger.info(f"Deleted voice profile (ID: {db_profile.id})")
        return True
        
    @staticmethod
    async def upload_audio(db: Session, db_profile: VoiceProfile, file: UploadFile) -> VoiceProfile:
        """Saves an uploaded audio file and updates the profile's file path."""
        # Ensure uploads directory exists
        profile_dir = os.path.join(settings.UPLOADS_DIR, "profiles", str(db_profile.id))
        os.makedirs(profile_dir, exist_ok=True)
        
        # Determine the file path
        # Keep original extension if possible, default to .wav
        ext = os.path.splitext(file.filename)[1] if file.filename else ".wav"
        temp_file_path = os.path.join(profile_dir, f"temp_upload{ext}")
        final_file_path = os.path.join(profile_dir, "reference.wav")
        
        # Save the file temporarily
        async with aiofiles.open(temp_file_path, 'wb') as out_file:
            content = await file.read()
            await out_file.write(content)
            
        try:
            # Convert to standard format (24000Hz mono WAV is best for XTTS)
            from pydub import AudioSegment
            audio = AudioSegment.from_file(temp_file_path)
            audio = audio.set_frame_rate(24000).set_channels(1)
            audio.export(final_file_path, format="wav")
            
            # Remove temp file
            if os.path.exists(temp_file_path):
                os.remove(temp_file_path)
                
            file_path = final_file_path
        except Exception as e:
            logger.error(f"Failed to convert audio file {temp_file_path}: {e}")
            # Fallback to the original file if conversion fails
            file_path = temp_file_path
        
        # Remove old file if returning exactly the same path wasn't guaranteed and it's different.
        if db_profile.audio_file_path and db_profile.audio_file_path != file_path:
             if os.path.exists(db_profile.audio_file_path):
                 try:
                     os.remove(db_profile.audio_file_path)
                 except:
                     pass
                     
        # Update database
        db_profile.audio_file_path = file_path
        db.add(db_profile)
        db.commit()
        db.refresh(db_profile)
        
        logger.success(f"Successfully attached audio to profile {db_profile.id}: {file_path}")
        return db_profile
