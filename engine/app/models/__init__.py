"""
Central module pointing to all models to facilitate migrations and Base awareness.
"""
from app.models.base import Base
from app.models.user import User
from app.models.profile import VoiceProfile
from app.models.generation import TTSOutput, STTResult
from app.models.queue import Task, ModelDownload
from app.models.settings import AppSettings

# Explicitly export Base and models to keep linters/tools happy
__all__ = [
    "Base", 
    "User", 
    "VoiceProfile", 
    "TTSOutput", 
    "STTResult", 
    "Task", 
    "ModelDownload", 
    "AppSettings"
]
