"""
TTS Model Registry and Factory.
"""
from typing import Dict, Type
from loguru import logger

from app.tts.base import BaseTTS
from app.tts.xtts import XTTSEngine
from app.tts.bark import BarkEngine
# Tortoise temporarily disabled due to dependency conflicts
# from app.tts.tortoise import TortoiseEngine
from app.config import settings

class TTSRegistry:
    """Registry to manage and instantiate requested TTS engines."""
    
    _engines: Dict[str, Type[BaseTTS]] = {
        "xtts": XTTSEngine,
        "bark": BarkEngine,
        # "tortoise": TortoiseEngine,  # Temporarily disabled
    }
    
    _instances: Dict[str, BaseTTS] = {}
    
    @classmethod
    def get_engine(cls, name: str = None) -> BaseTTS:
        """Get or create an instance of the requested TTS engine."""
        engine_name = name or settings.DEFAULT_TTS_ENGINE
        
        if engine_name not in cls._engines:
            logger.error(f"TTS engine '{engine_name}' not found. Falling back to default.")
            engine_name = settings.DEFAULT_TTS_ENGINE
            
        # Singleton pattern per engine type
        if engine_name not in cls._instances:
            engine_class = cls._engines[engine_name]
            logger.info(f"Initializing TTS Engine: {engine_name}")
            cls._instances[engine_name] = engine_class()
            
        return cls._instances[engine_name]

# Global registry instance/helper
def get_tts_engine(name: str = None) -> BaseTTS:
    return TTSRegistry.get_engine(name)
