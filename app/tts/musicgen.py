"""
MusicGen Engine Implementation for AI Music Generation.
Uses HuggingFace Transformers for text-to-music generation (avoids PyAV dependency).
"""
import os
import asyncio
from functools import partial
from typing import Optional
from loguru import logger

from app.tts.base import BaseTTS
from app.config import settings
from app.core.device import detect_device

class MusicGenEngine(BaseTTS):
    def __init__(self):
        self._processor = None
        self._model = None
        self._device = detect_device()
        self.model_path = os.path.join(settings.MODELS_DIR, "musicgen")
        
    @property
    def engine_name(self) -> str:
        return "musicgen"
        
    def is_model_downloaded(self) -> bool:
        """Check if MusicGen model exists in local directory."""
        try:
            from transformers import AutoProcessor, MusicgenForConditionalGeneration
            return True
        except ImportError:
            return False

    def _load_model(self):
        """Lazy load the model to save memory until generation."""
        if self._model is not None:
            return
            
        try:
            from transformers import AutoProcessor, MusicgenForConditionalGeneration
            
            logger.info(f"Loading MusicGen model on {self._device}...")
            
            # Load model from HuggingFace
            model_name = "facebook/musicgen-small"
            self._processor = AutoProcessor.from_pretrained(model_name)
            self._model = MusicgenForConditionalGeneration.from_pretrained(model_name)
            self._model = self._model.to(self._device)
            
            logger.success("MusicGen model loaded successfully")
            
        except ImportError:
            logger.error("transformers package not installed. Cannot load MusicGen.")
            raise
        except Exception as e:
            logger.error(f"Failed to load MusicGen model: {e}")
            raise

    def _generate_sync(self, text: str, output_path: str, language: str, profile_path: Optional[str], **kwargs):
        """Synchronous generation logic to be run in an executor."""
        self._load_model()
            
        logger.info(f"Generating music for text: '{text[:50]}...' to {output_path}")
        
        try:
            import torch
            import scipy.io.wavfile as wavfile
            
            # Set generation parameters
            max_length = kwargs.get('max_length', 256)  # Default length
            
            # Process inputs
            inputs = self._processor(
                text=[text],
                padding=True,
                return_tensors="pt",
            ).to(self._device)
            
            # Generate audio
            with torch.no_grad():
                audio_values = self._model.generate(**inputs, max_new_tokens=max_length)
            
            # Save the audio (sample rate is 32000 for musicgen)
            sampling_rate = self._model.config.audio_encoder.sampling_rate
            audio_numpy = audio_values[0, 0].cpu().numpy()
            
            # Normalize to int16 range
            audio_int16 = (audio_numpy * 32767).astype('int16')
            wavfile.write(output_path, sampling_rate, audio_int16)
            
            logger.success(f"Successfully generated music: {output_path}")
            return True
            
        except Exception as e:
            logger.error(f"MusicGen Generation Error: {e}")
            return False

    async def generate_audio(
        self, 
        text: str, 
        output_path: str, 
        language: str = "tr", 
        profile_path: Optional[str] = None,
        **kwargs
    ) -> bool:
        """Run MusicGen generation asynchronously in a separate thread/process to avoid blocking API."""
        if not self.is_model_downloaded():
            logger.error(f"MusicGen model not ready")
            return False
            
        loop = asyncio.get_event_loop()
        sync_func = partial(
            self._generate_sync,
            text,
            output_path,
            language,
            profile_path,
            **kwargs
        )
        result = await loop.run_in_executor(None, sync_func)
        return result
