"""
Base abstract class for TTS Engines.
"""
from abc import ABC, abstractmethod
from typing import Optional
from concurrent.futures import ThreadPoolExecutor

# Dedicated thread pool for TTS inference
# Each engine gets its own thread so they don't block each other or the event loop
_TTS_EXECUTOR = ThreadPoolExecutor(max_workers=4, thread_name_prefix="tts_inference")


class BaseTTS(ABC):
    """
    Abstract base class that all TTS engines must implement.
    Ensures a unified interface for text-to-speech generation.
    """
    
    @property
    @abstractmethod
    def engine_name(self) -> str:
        """Return the identifier for the TTS engine (e.g., 'xtts', 'bark', 'tortoise')."""
        pass

    @abstractmethod
    async def generate_audio(
        self, 
        text: str, 
        output_path: str, 
        language: str = "tr", 
        profile_path: Optional[str] = None,
        user_id: Optional[str] = None,
        **kwargs
    ) -> bool:
        """
        Generate audio from text and save it to output_path.
        
        Args:
            text (str): The input text to synthesize.
            output_path (str): The file path to save the generated audio (.wav).
            language (str): Language code (e.g., "tr", "en"). Defaults to "tr".
            profile_path (Optional[str]): Path to a reference audio file for voice cloning.
            **kwargs: Engine-specific generation parameters (e.g., speed_factor).
            
        Returns:
            bool: True if generation was successful, False otherwise.
        """
        pass
        
    @abstractmethod
    def is_model_downloaded(self) -> bool:
        """Check if the required models for this engine are downloaded and ready."""
        pass
