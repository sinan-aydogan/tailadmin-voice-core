"""
Suno Bark Engine Implementation using Hugging Face Transformers.
Loads local model from data/models/bark without uncompressed downloads.
"""
import os
import asyncio
from functools import partial
from typing import Optional
from loguru import logger

from app.tts.base import BaseTTS, _TTS_EXECUTOR
from app.config import settings
from app.core.device import detect_device

LANG_VOICE_PRESETS = {
    "tr": "v2/tr_speaker_0",
    "en": "v2/en_speaker_6",
    "de": "v2/de_speaker_0",
    "fr": "v2/fr_speaker_0",
    "es": "v2/es_speaker_0",
    "it": "v2/it_speaker_0",
    "ja": "v2/ja_speaker_0",
    "ko": "v2/ko_speaker_0",
    "zh": "v2/zh_speaker_0",
    "ru": "v2/ru_speaker_0",
    "pt": "v2/pt_speaker_0",
    "pl": "v2/pl_speaker_0",
}

class BarkEngine(BaseTTS):
    def __init__(self):
        self._model = None
        self._processor = None
        self._device = detect_device()
        self._runtime_device = "cpu"
        self.model_path = os.path.join(settings.MODELS_DIR, "bark")
        
    @property
    def engine_name(self) -> str:
        return "bark"
        
    def is_model_downloaded(self) -> bool:
        """Check if Bark model exists in local directory."""
        bin_path = os.path.join(self.model_path, "pytorch_model.bin")
        safe_path = os.path.join(self.model_path, "model.safetensors")
        return (os.path.exists(bin_path) and os.path.getsize(bin_path) > 100_000_000) or \
               (os.path.exists(safe_path) and os.path.getsize(safe_path) > 100_000_000)

    def load_model(self):
        """Public method to load the model. Can be called for pre-loading."""
        self._load_model()
    
    def _load_model(self):
        """Lazy load the Bark model from local files."""
        if self._model is not None and self._processor is not None:
            return
            
        try:
            import torch
            from transformers import AutoProcessor, BarkModel
            
            # Determine device
            if self._device == "cuda" and torch.cuda.is_available():
                self._runtime_device = "cuda"
            elif self._device == "mps" and torch.backends.mps.is_available():
                self._runtime_device = "mps"
            else:
                self._runtime_device = "cpu"
                
            local_files = self.is_model_downloaded()
            model_target = self.model_path if local_files else "suno/bark-small"
            
            logger.info(f"Loading Bark model from '{model_target}' on {self._runtime_device}...")
            
            self._processor = AutoProcessor.from_pretrained(
                model_target, 
                local_files_only=local_files
            )
            
            self._model = BarkModel.from_pretrained(
                model_target, 
                local_files_only=local_files
            ).to(self._runtime_device)
            
            logger.success(f"Bark model loaded successfully on {self._runtime_device}")
            
        except Exception as e:
            logger.error(f"Failed to load Bark model: {e}")
            raise

    def _generate_sync(self, text: str, output_path: str, language: str, profile_path: Optional[str], **kwargs):
        """Synchronous generation logic to be run in an executor."""
        self._load_model()
            
        logger.info(f"Generating Bark audio for text: '{text[:30]}...' in {language} to {output_path}")
        
        try:
            import torch
            import scipy.io.wavfile as wavfile
            
            voice_preset = LANG_VOICE_PRESETS.get(language, "v2/tr_speaker_0" if language == "tr" else "v2/en_speaker_6")
            
            inputs = self._processor(text, voice_preset=voice_preset, return_tensors="pt")
            inputs = {k: v.to(self._runtime_device) for k, v in inputs.items()}
            
            with torch.no_grad():
                audio_array = self._model.generate(**inputs, do_sample=True, pad_token_id=10000)
                audio = audio_array.cpu().numpy().squeeze()
            
            sample_rate = getattr(self._model.generation_config, "sample_rate", 24000)
            os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
            wavfile.write(output_path, sample_rate, audio)
            
            logger.success(f"Successfully generated Bark audio ({len(audio)} samples): {output_path}")
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
        user_id: Optional[str] = None,
        **kwargs
    ) -> bool:
        """Run Bark generation asynchronously in a separate thread/process to avoid blocking API."""
        if not self.is_model_downloaded():
            logger.error(f"Bark model not ready or not downloaded in {self.model_path}")
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
        result = await loop.run_in_executor(_TTS_EXECUTOR, sync_func)
        return result
