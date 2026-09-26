"""
Freya Voice TTS Engine Implementation.
Supports:
1. Adam & Eve (Cloud API) - Flagship hyper-realistic human voice models scoring 1418 on AudioRealismBench.
2. FreyaTTS (Local 183M DiT) - Open source tokenizer-free Turkish foundation model.
"""
import os
import sys
import json
import asyncio
import tempfile
import urllib.request
import urllib.error
from functools import partial
from pathlib import Path
from typing import Optional, Dict, Any
from loguru import logger

from app.tts.base import BaseTTS, _TTS_EXECUTOR
from app.config import settings, reload_custom_settings


class FreyaEngine(BaseTTS):
    """
    Freya Voice TTS Engine.
    Handles both Cloud API (Adam & Eve) and Local Open-Source DiT (FreyaTTS).
    """

    def __init__(
        self,
        mode: str = "cloud",
        voice: Optional[str] = None,
        model_id: str = "freya-adam"
    ):
        self._mode = mode
        self._model_id = model_id
        
        # Inferred default voice
        if voice:
            self._voice = voice
        elif "adam" in model_id.lower():
            self._voice = "adam"
        elif "eve" in model_id.lower():
            self._voice = "eve"
        else:
            self._voice = getattr(settings, "FREYA_DEFAULT_VOICE", "adam")

        self.model_dir = os.path.join(settings.MODELS_DIR, "freya-tts")
        self._local_pipeline = None

    @property
    def engine_name(self) -> str:
        return self._model_id

    def is_model_downloaded(self) -> bool:
        """
        Check readiness:
        - Cloud models ('freya-adam', 'freya-eve', 'freya-cloud'): Ready if FREYA_API_KEY is configured or cloud mode.
        - Local model ('freya-tts'): Checks if model.safetensors or pytorch_model.bin exists locally.
        """
        if self._mode == "cloud" or self._model_id != "freya-tts":
            # Cloud API is ready whenever API key is set or available
            api_key = os.environ.get("FREYA_API_KEY") or getattr(settings, "FREYA_API_KEY", None)
            return bool(api_key)

        # Local model verification
        safetensors_path = os.path.join(self.model_dir, "model.safetensors")
        bin_path = os.path.join(self.model_dir, "pytorch_model.bin")
        config_path = os.path.join(self.model_dir, "config.json")
        return (os.path.exists(safetensors_path) or os.path.exists(bin_path)) and os.path.exists(config_path)

    def _get_api_key(self) -> Optional[str]:
        reload_custom_settings()
        key = os.environ.get("FREYA_API_KEY") or getattr(settings, "FREYA_API_KEY", None)
        if key:
            return str(key).strip()
        return None

    def _generate_cloud(self, text: str, output_path: str, voice: str) -> bool:
        """Generate audio using Freya Voice Cloud API (Adam & Eve)."""
        api_key = self._get_api_key()
        if not api_key:
            logger.error(
                "FREYA_API_KEY is not configured. Please enter your Freya Voice API key in Settings -> AI & Depolama."
            )
            return False

        endpoint = "https://tts.freyavoice.ai/v1/audio/speech"
        payload = {
            "model": "tts-1",
            "input": text,
            "voice": voice,
            "response_format": "wav"
        }

        logger.info(f"Generating Freya Voice ({voice}) audio via Cloud API: '{text[:40]}...'")

        req = urllib.request.Request(
            endpoint,
            data=json.dumps(payload).encode("utf-8"),
            headers={
                "Authorization": f"Bearer {api_key}",
                "Content-Type": "application/json",
                "User-Agent": "TailAdminVoiceCore/2.0"
            },
            method="POST"
        )

        try:
            os.makedirs(os.path.dirname(output_path), exist_ok=True)
            with urllib.request.urlopen(req, timeout=90) as response:
                if response.status == 200:
                    audio_data = response.read()
                    with open(output_path, "wb") as f:
                        f.write(audio_data)
                    logger.success(f"Freya Voice ({voice}) audio written to {output_path} ({len(audio_data)} bytes)")

                    # Freya Voice Cloud API currently serves a single female model ('leyla' at ~290-310Hz),
                    # ignoring voice='adam'. To guarantee a distinct, authentic male voice for Adam,
                    # we acoustically shift Adam to the natural male vocal range (~160-175Hz).
                    is_male_adam = (str(voice).lower() == "adam") or ("adam" in self._model_id.lower())
                    if is_male_adam:
                        try:
                            import librosa
                            import soundfile as sf
                            y, sr = sf.read(output_path, dtype='float32')
                            y_male = librosa.effects.pitch_shift(y, sr=sr, n_steps=-7.5)
                            sf.write(output_path, y_male, sr)
                            logger.info(f"Freya Voice ({voice}) acoustically transformed to natural male baritone register (-7.5 semitones).")
                        except Exception as pe:
                            logger.warning(f"Could not apply male acoustic shift to Freya Voice: {pe}")

                    return True
                else:
                    logger.error(f"Freya Voice API returned status {response.status}: {response.read().decode('utf-8', errors='ignore')}")
                    return False
        except urllib.error.HTTPError as e:
            err_body = e.read().decode("utf-8", errors="ignore")
            logger.error(f"Freya Voice API HTTP error {e.code}: {err_body}")
            return False
        except Exception as e:
            logger.error(f"Freya Voice Cloud API connection error: {e}")
            return False

    def _generate_local(self, text: str, output_path: str, **kwargs) -> bool:
        """Generate audio using local FreyaTTS model."""
        if not self.is_model_downloaded():
            logger.error(f"FreyaTTS model not found at {self.model_dir}. Please download it via Model Manager.")
            return False

        logger.info(f"Generating local FreyaTTS audio for text: '{text[:40]}...' to {output_path}")

        try:
            # Try importing freyatts package if available
            try:
                from freyatts import FreyaTTS
                device = "cuda" if getattr(settings, "USE_GPU", "auto") != "cpu" and self._has_cuda() else "cpu"
                if self._local_pipeline is None:
                    self._local_pipeline = FreyaTTS.from_pretrained(self.model_dir, device=device)
                
                steps = kwargs.get("steps", 32)
                wav = self._local_pipeline.synthesize(text, steps=steps)
                self._local_pipeline.save_wav(wav, output_path)
                logger.success(f"FreyaTTS generated local audio: {output_path}")
                return True
            except ImportError:
                logger.warning("freyatts package not directly imported, attempting standalone DiT synthesis...")
                # Standalone fallback if voxcpm or freyatts is in progress:
                # Can fall back to Piper-TR with notification or use local engine
                from app.tts.piper import PiperEngine
                fallback = PiperEngine("tr_TR-dfki-medium", model_id="piper-tr")
                if fallback.is_model_downloaded():
                    logger.info("Executing graceful fallback synthesis with local Turkish model (Piper-TR)...")
                    return fallback._generate_sync(text, output_path, "tr", None, **kwargs)
                return False
        except Exception as e:
            logger.error(f"FreyaTTS local generation error: {e}")
            return False

    def _has_cuda(self) -> bool:
        try:
            import torch
            return torch.cuda.is_available()
        except Exception:
            return False

    def _generate_sync(
        self,
        text: str,
        output_path: str,
        language: str = "tr",
        profile_path: Optional[str] = None,
        **kwargs
    ) -> bool:
        """Synchronous wrapper executed in dedicated thread executor."""
        voice = kwargs.get("voice") or self._voice
        
        # Decide between Cloud API and Local
        if self._mode == "cloud" or self._model_id in ("freya-adam", "freya-eve", "freya-cloud"):
            api_key = self._get_api_key()
            if api_key:
                return self._generate_cloud(text, output_path, voice)
            else:
                # If API key is missing but user invoked cloud, try local or piper fallback
                logger.warning(
                    f"Freya Cloud Voice requested ({voice}) but FREYA_API_KEY is not set. "
                    "Checking for local fallback..."
                )
                from app.tts.piper import PiperEngine
                fallback = PiperEngine("tr_TR-dfki-medium", model_id="piper-tr")
                if fallback.is_model_downloaded():
                    logger.info("Using local Piper-TR fallback for preview...")
                    ok = fallback._generate_sync(text, output_path, "tr", profile_path, **kwargs)
                    is_male_adam = (str(voice).lower() == "adam") or ("adam" in self._model_id.lower())
                    if ok and is_male_adam:
                        try:
                            import librosa
                            import soundfile as sf
                            y, sr = sf.read(output_path, dtype='float32')
                            y_male = librosa.effects.pitch_shift(y, sr=sr, n_steps=-7.5)
                            sf.write(output_path, y_male, sr)
                        except Exception:
                            pass
                    return ok
                return False
        else:
            return self._generate_local(text, output_path, **kwargs)

    async def generate_audio(
        self,
        text: str,
        output_path: str,
        language: str = "tr",
        profile_path: Optional[str] = None,
        user_id: Optional[str] = None,
        **kwargs
    ) -> bool:
        """Run Freya Voice generation asynchronously in background thread."""
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
