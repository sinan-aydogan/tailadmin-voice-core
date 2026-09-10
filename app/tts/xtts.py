"""
Coqui XTTS v2 Engine Implementation.
"""
import os
import asyncio
import torch
from functools import partial
from typing import Optional
from loguru import logger

from app.tts.base import BaseTTS, _TTS_EXECUTOR
from app.config import settings
from app.core.device import detect_device

class XTTSEngine(BaseTTS):
    def __init__(self):
        self._model = None
        self._device = detect_device()
        self.model_path = os.path.join(settings.MODELS_DIR, "xtts-v2")
        # Cache of speaker conditioning latents keyed by (speaker_wav, mtime).
        # Recomputing these on every generation is expensive; the reference audio
        # rarely changes, so caching gives a big speedup for repeated same-profile runs.
        self._latent_cache = {}
        
    @property
    def engine_name(self) -> str:
        return "xtts"
        
    def is_model_downloaded(self) -> bool:
        """Check if XTTS v2 model exists in local directory."""
        # Simple check - could be expanded to verify all necessary files
        return os.path.exists(self.model_path) and os.listdir(self.model_path)

    def load_model(self):
        """Public method to load the model. Can be called for pre-loading."""
        self._load_model()
    
    def _load_model(self):
        """Lazy load the model to save memory until generation."""
        if self._model is not None:
            logger.info("XTTS model already loaded, skipping initialization")
            return
        
        logger.info(f"Starting XTTS model load from {self.model_path}...")
            
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
            
            logger.info(f"Loading XTTS v2 config from {self.model_path}...")
            config = XttsConfig()
            config.load_json(os.path.join(self.model_path, "config.json"))
            logger.info("XTTS config loaded, initializing model...")
            
            self._model = Xtts.init_from_config(config)
            logger.info("XTTS model initialized, loading checkpoint...")
            
            self._model.load_checkpoint(
                config, 
                checkpoint_dir=self.model_path, 
                eval=True,
                use_deepspeed=False # Set to True if DeepSpeed is configured
            )
            logger.info("XTTS checkpoint loaded, moving to device...")
            
            # Use appropriate device
            if self._device in ["cuda", "mps"]:
                self._model.cuda() # Coqui maps to appropriate hardware if configured right
                logger.info(f"XTTS model moved to {self._device}")
            
            logger.success("XTTS v2 model loaded successfully")
            
        except ImportError:
            logger.error("TTS package not installed. Cannot load XTTS.")
            raise
        except Exception as e:
            logger.error(f"Failed to load XTTS model: {e}")
            raise

    def _generate_sync(self, text: str, output_path: str, language: str, profile_path: Optional[str], **kwargs):
        """Synchronous generation logic to be run in a separate thread."""
        logger.info(f"[XTTS] Starting _generate_sync - text length: {len(text)}, language: {language}")

        # Load the model (runs in the worker process; GIL isolation is handled at the
        # process level now, so the previous time.sleep(0.01) "yield" hack is gone).
        self._load_model()
        logger.info("[XTTS] Model loaded, preparing speaker reference...")
        
        # Determine speaker reference
        speaker_wav = profile_path or os.path.join(self.model_path, "samples", "default.wav")
        if not os.path.exists(speaker_wav):
            logger.warning(f"Speaker profile not found at {speaker_wav}, falling back to built-in generation if possible")
            speaker_wav = None
        else:
            logger.info(f"[XTTS] Using speaker reference: {speaker_wav}")
            
        logger.info(f"[XTTS] Generating audio for text: '{text[:30]}...' in {language}")
        
        try:
            # Low-level inference approach to avoid 'gpt_inference' attribute error in higher level synthesize()
            # 1. Get speaker latents (cached per reference audio + mtime)
            if speaker_wav:
                try:
                    cache_key = (speaker_wav, os.path.getmtime(speaker_wav))
                except OSError:
                    cache_key = (speaker_wav, None)
                cached = self._latent_cache.get(cache_key)
                if cached is not None:
                    logger.info("[XTTS] Using cached speaker conditioning latents")
                    gpt_cond_latent, speaker_embedding = cached
                else:
                    gpt_cond_latent, speaker_embedding = self._model.get_conditioning_latents(audio_path=[speaker_wav])
                    self._latent_cache[cache_key] = (gpt_cond_latent, speaker_embedding)
            else:
                # Fallback to no latents if no speaker_wav, though XTTS usually needs it
                gpt_cond_latent, speaker_embedding = None, None

            # 2. Run inference
            logger.info("[XTTS] Getting conditioning latents...")
            # Sanitize kwargs for the inference method
            inference_kwargs = kwargs.copy()
            
            # Map speed_factor to speed if present (common across our API)
            if "speed_factor" in inference_kwargs:
                inference_kwargs["speed"] = inference_kwargs.pop("speed_factor")
                logger.info(f"[XTTS] Speed factor mapped to: {inference_kwargs.get('speed')}")
                
            # Filter out None values or other non-supported keys if necessary
            # For now, just ensuring speed is correctly mapped
            
            logger.info("[XTTS] Running model inference...")
            out = self._model.inference(
                text,
                language,
                gpt_cond_latent,
                speaker_embedding,
                **inference_kwargs
            )
            logger.info("[XTTS] Inference completed, saving audio...")
            
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
        """Run XTTS generation asynchronously in a separate thread to avoid blocking API."""
        if not self.is_model_downloaded():
            logger.error(f"XTTS model not found at {self.model_path}")
            return False
        
        # Use asyncio.to_thread for better event loop handling
        # This runs the sync function in a separate thread without blocking the event loop
        result = await asyncio.to_thread(
            self._generate_sync,
            text,
            output_path,
            language,
            profile_path,
            **kwargs
        )
        return result
