"""
Suno Bark Engine Implementation.
"""
import os
import asyncio
from functools import partial
from typing import Optional
from loguru import logger

from app.tts.base import BaseTTS
from app.config import settings
from app.core.device import detect_device

class BarkEngine(BaseTTS):
    def __init__(self):
        self._model = None
        self._device = detect_device()
        self.model_path = os.path.join(settings.MODELS_DIR, "bark")
        
    @property
    def engine_name(self) -> str:
        return "bark"
        
    def is_model_downloaded(self) -> bool:
        """Check if Bark model exists in local directory."""
        return os.path.exists(self.model_path) and os.listdir(self.model_path)

    def _load_model(self):
        """Lazy load the model to save memory until generation."""
        if self._model is not None:
            return
            
        try:
            from bark import SAMPLE_RATE, generate_audio, preload_models
            
            logger.info(f"Preloading Bark models on {self._device}...")
            # We enforce HuggingFace to use our custom directories if defined
            os.environ["BARK_USE_SMALL_MODELS"] = "True" # Memory optimization
            
            # If we downloaded it via our UI, it will be in self.model_path
            # Bark/Transformers usually look at XDG_CACHE_HOME or HUGGINGFACE_HUB_CACHE
            # We can point to our local dir if it exists
            if self.is_model_downloaded():
                os.environ["XDG_CACHE_HOME"] = str(settings.MODELS_DIR) # Simplest way to redirect most HF models
            
            # Set context for StatusTqdm if downloading starts
            os.environ["CURRENT_DOWNLOAD_MODEL_ID"] = "bark"
            
            preload_models()
            
            if "CURRENT_DOWNLOAD_MODEL_ID" in os.environ:
                del os.environ["CURRENT_DOWNLOAD_MODEL_ID"]
            
            self._model = "loaded" # Just a flag, Bark uses global state mostly
            logger.success("Bark models loaded successfully")
            
        except ImportError:
            logger.error("Bark package not installed. Cannot load Bark TTS.")
            raise
        except Exception as e:
            logger.error(f"Failed to load Bark model: {e}")
            raise

    def _generate_sync(self, text: str, output_path: str, language: str, profile_path: Optional[str], **kwargs):
        """Synchronous generation logic to be run in an executor."""
        self._load_model()
            
        logger.info(f"Generating Bark audio for text: '{text[:20]}...' in {language} to {output_path}")
        
        try:
            from bark import generate_audio, SAMPLE_RATE
            import scipy.io.wavfile as wavfile
            
            # Map language to Bark history prompt prefixes if we had predefined ones
            history_prompt = None
            if profile_path and os.path.exists(profile_path):
                # Advanced logic required here to convert profile.wav mapping to Bark NPZ format
                logger.warning("Bark requires specific .npz history prompts. Using generated/fallback for custom voices.")
            elif language == "tr":
                history_prompt = "v2/tr_speaker_0"
                
            audio_array = generate_audio(text, history_prompt=history_prompt)
            
            # Save the file
            wavfile.write(output_path, SAMPLE_RATE, audio_array)
            logger.success(f"Successfully generated Bark audio: {output_path}")
            return True
            
        except Exception as e:
            logger.error(f"Bark Generation Error: {e}")
            return False

    async def generate_audio(
        self, 
        text: str, 
        output_path: str, 
        language: str = "tr", 
        profile_path: Optional[str] = None,
        **kwargs
    ) -> bool:
        """Run Bark generation asynchronously in a separate thread/process to avoid blocking API."""
        if not self.is_model_downloaded():
            logger.error(f"Bark model not ready")
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
