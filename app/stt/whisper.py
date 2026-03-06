"""
Faster Whisper Engine Implementation.
"""
import os
import asyncio
from functools import partial
from typing import Optional
from loguru import logger

from app.stt.base import BaseSTT
from app.config import settings
from app.core.device import detect_device

class WhisperEngine(BaseSTT):
    def __init__(self):
        self._models = {} # Cache multiple model sizes if loaded
        self._device = detect_device()
        self.download_dir = os.path.join(settings.MODELS_DIR, "whisper")
        os.makedirs(self.download_dir, exist_ok=True)
        
    @property
    def engine_name(self) -> str:
        return "whisper"
        
    def is_model_downloaded(self, model_size: str) -> bool:
        """Check if Faster Whisper model exists in local directory or cache."""
        # Using faster-whisper which handles HuggingFace hub downloads.
        # We can assume true for lazy loading, or do a specific directory check if we override download_root.
        expected_path = os.path.join(self.download_dir, f"models--Systran--faster-whisper-{model_size}")
        return os.path.exists(expected_path) or True # True as a fallback letting it download on the fly if needed

    def _load_model(self, model_size: str):
        """Lazy load the specified model size."""
        if model_size in self._models:
            return self._models[model_size]
            
        try:
            from faster_whisper import WhisperModel
            
            logger.info(f"Loading faster-whisper model ({model_size}) on {self._device}...")
            
            # Map common generic devices to faster-whisper parameters
            device = "cuda" if self._device == "cuda" else "cpu"
            # Setting compute_type for MPS or CPU might be limited, int8 is safe for CPU, float16 for CUDA
            compute_type = "float16" if device == "cuda" else "int8"
            
            model = WhisperModel(
                model_size, 
                device=device, 
                compute_type=compute_type, 
                download_root=self.download_dir
            )
            
            self._models[model_size] = model
            logger.success(f"Whisper model '{model_size}' loaded successfully")
            return model
            
        except ImportError:
            logger.error("faster-whisper package not installed. Cannot load Whisper STT.")
            raise
        except Exception as e:
            logger.error(f"Failed to load Whisper model '{model_size}': {e}")
            raise

    def _transcribe_sync(self, audio_path: str, language: Optional[str], model_size: Optional[str], **kwargs):
        """Synchronous generation logic to be run in an executor."""
        model_size = model_size or settings.DEFAULT_STT_MODEL_SIZE or "base"
        model = self._load_model(model_size)
            
        logger.info(f"Transcribing audio from {audio_path} using Whisper ({model_size})")
        
        try:
            segments, info = model.transcribe(audio_path, language=language, beam_size=5)
            
            logger.info(f"Detected language '{info.language}' with probability {info.language_probability}")
            
            text_result = ""
            for segment in segments:
                text_result += segment.text + " "
                
            stripped_text = text_result.strip()
            logger.success(f"Successfully transcribed video/audio. Output length: {len(stripped_text)} chars")
            
            return stripped_text
            
        except Exception as e:
            logger.error(f"Whisper Transcription Error: {e}")
            return f"Error: {str(e)}"

    async def transcribe(
        self, 
        audio_path: str, 
        language: Optional[str] = None, 
        model_size: Optional[str] = None,
        **kwargs
    ) -> str:
        """Run Whisper transcription asynchronously in a separate thread/process to avoid blocking API."""
        if not os.path.exists(audio_path):
            raise FileNotFoundError(f"Audio file not found: {audio_path}")
            
        loop = asyncio.get_event_loop()
        sync_func = partial(
            self._transcribe_sync,
            audio_path,
            language,
            model_size,
            **kwargs
        )
        result = await loop.run_in_executor(None, sync_func)
        return result
