"""
Base abstract class for STT Engines.
"""
from abc import ABC, abstractmethod
from typing import Optional

class BaseSTT(ABC):
    """
    Abstract base class that all STT (Speech-to-Text) engines must implement.
    Ensures a unified interface for transcription.
    """
    
    @property
    @abstractmethod
    def engine_name(self) -> str:
        """Return the identifier for the STT engine (e.g., 'whisper')."""
        pass

    @abstractmethod
    async def transcribe(
        self, 
        audio_path: str, 
        language: Optional[str] = None, 
        model_size: Optional[str] = None,
        **kwargs
    ) -> str:
        """
        Transcribe audio from the given file path.
        
        Args:
            audio_path (str): The path to the audio file.
            language (Optional[str]): Language code (e.g., "tr", "en"). If None, auto-detect.
            model_size (Optional[str]): Size of the model to use (e.g., "base", "large-v3").
            **kwargs: Engine-specific transcription parameters.
            
        Returns:
            str: The transcribed text.
        """
        pass
        
    @abstractmethod
    def is_model_downloaded(self, model_size: str) -> bool:
        """Check if the required model is downloaded and ready."""
        pass
