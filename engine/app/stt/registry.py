"""
STT Model Registry and Factory.
"""
from typing import Dict, Type
from loguru import logger

from app.stt.base import BaseSTT
from app.stt.whisper import WhisperEngine
from app.config import settings

class STTRegistry:
    """Registry to manage and instantiate requested STT engines."""
    
    _engines: Dict[str, Type[BaseSTT]] = {
        "whisper": WhisperEngine,
    }
    
    _instances: Dict[str, BaseSTT] = {}
    
    @classmethod
    def get_engine(cls, name: str = None) -> BaseSTT:
        """Get or create an instance of the requested STT engine."""
        engine_name = name or settings.DEFAULT_STT_ENGINE
        
        if engine_name not in cls._engines:
            logger.error(f"STT engine '{engine_name}' not found. Falling back to default.")
            engine_name = settings.DEFAULT_STT_ENGINE
            
        if engine_name not in cls._instances:
            engine_class = cls._engines[engine_name]
            logger.info(f"Initializing STT Engine: {engine_name}")
            cls._instances[engine_name] = engine_class()
            
        return cls._instances[engine_name]

# Global registry instance/helper
def get_stt_engine(name: str = None) -> BaseSTT:
    return STTRegistry.get_engine(name)
