"""
Coqui XTTS v2 Engine Implementation.
"""
import os
import asyncio
import torch
from functools import partial
from typing import Optional
from loguru import logger

from app.tts.base import BaseTTS
from app.config import settings
from app.core.device import detect_device

class XTTSEngine(BaseTTS):
    def __init__(self):
        self._model = None
        self._device = detect_device()
        self.model_path = os.path.join(settings.MODELS_DIR, "xtts-v2")
        
    @property
    def engine_name(self) -> str:
        return "xtts"
        
    def is_model_downloaded(self) -> bool:
        """Check if XTTS v2 model exists in local directory."""
        # Simple check - could be expanded to verify all necessary files
        return os.path.exists(self.model_path) and os.listdir(self.model_path)

    def _load_model(self):
        """Lazy load the model to save memory until generation."""
        if self._model is not None:
            return
            
        # Fix for PyTorch 2.6+ safe loading (weights_only=True)
        # We need to allowlist XttsConfig and related classes because the TTS library uses torch.load internally
        try:
            from TTS.tts.configs.xtts_config import XttsConfig, XttsAudioConfig, XttsArgs
            from TTS.tts.models.xtts import XttsAudioConfig as XttsAudioConfigModel, XttsArgs as XttsArgsModel
            from TTS.config.shared_configs import BaseDatasetConfig, BaseAudioConfig
            
            # Additional classes that might be needed recursively
            from TTS.tts.models.xtts import Xtts
            
            if hasattr(torch.serialization, 'add_safe_globals'):
                # Allowlist all relevant classes to be safe for unpickling
                torch.serialization.add_safe_globals([
                    XttsConfig, 
                    XttsAudioConfig, 
                    XttsArgs,
                    XttsAudioConfigModel,
                    XttsArgsModel,
                    BaseDatasetConfig,
                    BaseAudioConfig
                ])
        except ImportError:
            pass

        try:
            from TTS.tts.configs.xtts_config import XttsConfig
            from TTS.tts.models.xtts import Xtts
            
            logger.info(f"Loading XTTS v2 model from {self.model_path} on {self._device}...")
            config = XttsConfig()
            config.load_json(os.path.join(self.model_path, "config.json"))
            
            self._model = Xtts.init_from_config(config)
            self._model.load_checkpoint(
                config, 
                checkpoint_dir=self.model_path, 
                eval=True,
                use_deepspeed=False # Set to True if DeepSpeed is configured
            )
            
            # Use appropriate device
            if self._device in ["cuda", "mps"]:
                self._model.cuda() # Coqui maps to appropriate hardware if configured right
            
            logger.success("XTTS v2 model loaded successfully")
            
        except ImportError:
            logger.error("TTS package not installed. Cannot load XTTS.")
            raise
        except Exception as e:
            logger.error(f"Failed to load XTTS model: {e}")
            raise

    def _generate_sync(self, text: str, output_path: str, language: str, profile_path: Optional[str], **kwargs):
        """Synchronous generation logic to be run in an executor."""
        self._load_model()
        
        # Determine speaker reference
        speaker_wav = profile_path or os.path.join(self.model_path, "samples", "default.wav")
        if not os.path.exists(speaker_wav):
            logger.warning(f"Speaker profile not found at {speaker_wav}, falling back to built-in generation if possible")
            speaker_wav = None
            
        logger.info(f"Generating XTTS audio for text: '{text[:20]}...' in {language} to {output_path}")
        
        try:
            # Low-level inference approach to avoid 'gpt_inference' attribute error in higher level synthesize()
            # 1. Get speaker latents
            if speaker_wav:
                gpt_cond_latent, speaker_embedding = self._model.get_conditioning_latents(audio_path=[speaker_wav])
            else:
                # Fallback to no latents if no speaker_wav, though XTTS usually needs it
                gpt_cond_latent, speaker_embedding = None, None

            # 2. Run inference
            # Sanitize kwargs for the inference method
            inference_kwargs = kwargs.copy()
            
            # Map speed_factor to speed if present (common across our API)
            if "speed_factor" in inference_kwargs:
                inference_kwargs["speed"] = inference_kwargs.pop("speed_factor")
                
            # Filter out None values or other non-supported keys if necessary
            # For now, just ensuring speed is correctly mapped
            
            out = self._model.inference(
                text,
                language,
                gpt_cond_latent,
                speaker_embedding,
                **inference_kwargs
            )
            
            # Save the file
            import torchaudio
            import torch
            
            tensor_out = torch.tensor(out["wav"]).unsqueeze(0)
            torchaudio.save(output_path, tensor_out, self._model.config.audio.sample_rate)
            
            logger.success(f"Successfully generated XTTS audio: {output_path}")
            return True
            
        except Exception as e:
            logger.error(f"XTTS Generation Error: {e}")
            return False

    async def generate_audio(
        self, 
        text: str, 
        output_path: str, 
        language: str = "tr", 
        profile_path: Optional[str] = None,
        user_id: Optional[str] = None,
        **kwargs
    ) -> bool:
        """Run XTTS generation asynchronously in a separate thread/process to avoid blocking API."""
        if not self.is_model_downloaded():
            logger.error(f"XTTS model not found at {self.model_path}")
            return False
            
        loop = asyncio.get_event_loop()
        # Non-blocking execution for heavy CPU/GPU workload
        # Use partial to pass kwargs correctly to the sync method
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
