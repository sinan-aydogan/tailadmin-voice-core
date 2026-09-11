"""
TTS Model Registry and Factory.
"""
from typing import Dict, Any, Callable
from loguru import logger

from app.tts.base import BaseTTS
from app.config import settings

class TTSRegistry:
    """Registry to manage and instantiate requested TTS engines lazily."""

    _engines: Dict[str, Callable[[], BaseTTS]] = {
        "xtts": lambda: __import__("app.tts.xtts", fromlist=["XTTSEngine"]).XTTSEngine(),
        "xtts-v2": lambda: __import__("app.tts.xtts", fromlist=["XTTSEngine"]).XTTSEngine(),
        "bark": lambda: __import__("app.tts.bark", fromlist=["BarkEngine"]).BarkEngine(),
        "musicgen-small": lambda: __import__("app.tts.musicgen", fromlist=["MusicGenEngine"]).MusicGenEngine("small"),
        "musicgen-medium": lambda: __import__("app.tts.musicgen", fromlist=["MusicGenEngine"]).MusicGenEngine("medium"),
        "musicgen-large": lambda: __import__("app.tts.musicgen", fromlist=["MusicGenEngine"]).MusicGenEngine("large"),
        "musicgen-melody": lambda: __import__("app.tts.musicgen", fromlist=["MusicGenEngine"]).MusicGenEngine("melody"),
        "tortoise": lambda: __import__("app.tts.tortoise", fromlist=["TortoiseEngine"]).TortoiseEngine(),
        "piper-tr": lambda: __import__("app.tts.piper", fromlist=["PiperEngine"]).PiperEngine("tr_TR-dfki-medium", model_id="piper-tr"),
        "piper-en": lambda: __import__("app.tts.piper", fromlist=["PiperEngine"]).PiperEngine("en_US-lessac-medium", model_id="piper-en"),
        "piper-de": lambda: __import__("app.tts.piper", fromlist=["PiperEngine"]).PiperEngine("de_DE-thorsten-medium", model_id="piper-de"),
        "piper-fr": lambda: __import__("app.tts.piper", fromlist=["PiperEngine"]).PiperEngine("fr_FR-siwis-medium", model_id="piper-fr"),
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

    @classmethod
    def unload(cls, name: str) -> bool:
        """Drop a loaded engine instance to free RAM/VRAM.

        Clears the singleton and best-effort empties the MPS/CUDA cache. Returns
        True if an instance was actually removed.
        """
        instance = cls._instances.pop(name, None)
        if instance is None:
            return False
        # Let engines release big tensors if they expose a hook.
        for attr in ("_model", "model"):
            if hasattr(instance, attr):
                try:
                    setattr(instance, attr, None)
                except Exception:
                    pass
        try:
            import torch
            if torch.backends.mps.is_available():
                torch.mps.empty_cache()
            elif torch.cuda.is_available():
                torch.cuda.empty_cache()
        except Exception:
            pass
        logger.info(f"Unloaded TTS engine: {name}")
        return True

    @classmethod
    def loaded_engines(cls) -> list:
        """Names of currently instantiated engines."""
        return list(cls._instances.keys())

# Global registry instance/helper
def get_tts_engine(name: str = None) -> BaseTTS:
    return TTSRegistry.get_engine(name)

def unload_tts_engine(name: str) -> bool:
    return TTSRegistry.unload(name)
