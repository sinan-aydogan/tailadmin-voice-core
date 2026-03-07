"""
Tortoise TTS Engine Implementation.
"""
import os
import asyncio
from functools import partial
from typing import Optional
from loguru import logger

from app.tts.base import BaseTTS
from app.config import settings
from app.core.device import detect_device

class TortoiseEngine(BaseTTS):
    def __init__(self):
        self._model = None
        self._device = detect_device()
        self.model_path = os.path.join(settings.MODELS_DIR, "tortoise")
        
    @property
    def engine_name(self) -> str:
        return "tortoise"
        
    def is_model_downloaded(self) -> bool:
        """Check if Tortoise models exist in local directory."""
        return os.path.exists(self.model_path) and os.listdir(self.model_path)

    def _load_model(self):
        """Lazy load the model to save memory until generation."""
        if self._model is not None:
            return
            
        try:
            from tortoise.api import TextToSpeech
            
            logger.info(f"Loading Tortoise TTS model on {self._device}...")
            
            # Disable hf-transfer which can cause "receiver dropped" on macOS
            os.environ["HF_HUB_ENABLE_HF_TRANSFER"] = "0"
            # Set context for StatusTqdm if downloading starts
            os.environ["CURRENT_DOWNLOAD_MODEL_ID"] = "tortoise"
            # Set a long timeout for HF hub
            os.environ["HF_HUB_HTTP_TIMEOUT"] = "60"
            
            # Note: models_dir in tortoise constructor is used as cache_dir for hf_hub_download
            self._model = TextToSpeech(models_dir=self.model_path, use_deepspeed=False, kv_cache=True)
            
            if "CURRENT_DOWNLOAD_MODEL_ID" in os.environ:
                del os.environ["CURRENT_DOWNLOAD_MODEL_ID"]
            
            logger.success("Tortoise TTS model loaded successfully")
            
        except ImportError:
            logger.error("Tortoise TTS package not installed. Cannot load Tortoise.")
            raise
        except Exception as e:
            logger.error(f"Failed to load Tortoise model: {e}")
            raise

    def _generate_sync(self, text: str, output_path: str, language: str, profile_path: Optional[str], **kwargs):
        """Synchronous generation logic to be run in an executor."""
        self._load_model()
            
        logger.info(f"Generating Tortoise audio for text: '{text[:20]}...' to {output_path}")
        if language != "en":
            logger.warning("Tortoise is primarily trained on English. Other languages may have poor quality.")
            
        try:
            from tortoise.utils.audio import load_voices
            import torchaudio
            
            # Format voice samples if profile provided
            voice_samples, conditioning_latents = None, None
            if profile_path and os.path.exists(profile_path):
                # If profile_path is a directory, use it as the voice name
                # If it's a file, use the directory containing it
                if os.path.isdir(profile_path):
                    voice_name = os.path.basename(profile_path)
                    extra_voice_dir = os.path.dirname(profile_path)
                else:
                    voice_name = os.path.basename(os.path.dirname(profile_path))
                    extra_voice_dir = os.path.dirname(os.path.dirname(profile_path))
                
                logger.info(f"Loading Tortoise voice '{voice_name}' from {extra_voice_dir}")
                voice_samples, conditioning_latents = load_voices([voice_name], extra_voice_dirs=[extra_voice_dir])
            
            preset = kwargs.get("preset", "fast") # 'ultra_fast', 'fast', 'standard', 'high_quality'
            
            gen = self._model.tts_with_preset(
                text, 
                voice_samples=voice_samples, 
                conditioning_latents=conditioning_latents, 
                preset=preset
            )
            
            # Save the file
            torchaudio.save(output_path, gen.squeeze(0).cpu(), 24000)
            logger.success(f"Successfully generated Tortoise audio: {output_path}")
            return True
            
        except Exception as e:
            logger.error(f"Tortoise Generation Error: {e}")
            return False

    async def generate_audio(
        self, 
        text: str, 
        output_path: str, 
        language: str = "en", # Defaulting to en for Tortoise
        profile_path: Optional[str] = None,
        user_id: Optional[str] = None,
        **kwargs
    ) -> bool:
        """Run Tortoise generation asynchronously in a separate thread/process to avoid blocking API."""
        if not self.is_model_downloaded():
            logger.error(f"Tortoise model not found at {self.model_path}")
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
