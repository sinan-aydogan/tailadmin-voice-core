"""
Tortoise TTS Engine Implementation using isolated subprocess wrapper.
This avoids dependency conflicts with other TTS engines.
"""
import os
from typing import Optional
from loguru import logger

from app.tts.base import BaseTTS
from app.tts.tortoise_wrapper import TortoiseWrapper
from app.config import settings


class TortoiseEngine(BaseTTS):
    """
    Tortoise TTS Engine that runs in isolated subprocess.
    Uses TortoiseWrapper to avoid dependency conflicts.
    """
    
    def __init__(self):
        self.model_path = os.path.join(settings.MODELS_DIR, "tortoise")
        self._wrapper = TortoiseWrapper()
        
    @property
    def engine_name(self) -> str:
        return "tortoise"
        
    def is_model_downloaded(self) -> bool:
        """Check if Tortoise models exist in local directory."""
        return self._wrapper.is_model_downloaded()

    async def generate_audio(
        self, 
        text: str, 
        output_path: str, 
        language: str = "en",
        profile_path: Optional[str] = None,
        user_id: Optional[str] = None,
        **kwargs
    ) -> bool:
        """Generate audio using Tortoise in isolated subprocess."""
        if not self.is_model_downloaded():
            logger.error(f"Tortoise model not found at {self.model_path}")
            logger.error("Please download the model from Model Manager")
            return False
        
        if language != "en":
            logger.warning("Tortoise is primarily trained on English. Other languages may have poor quality.")
        
        return await self._wrapper.generate_audio(
            text=text,
            output_path=output_path,
            language=language,
            profile_path=profile_path,
            preset=kwargs.get("preset", "fast"),
            **kwargs
        )
