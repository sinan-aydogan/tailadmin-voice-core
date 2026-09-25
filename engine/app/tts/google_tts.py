"""
Google Cloud Text-to-Speech Engine (Neural2, Journey, Studio, Standard).
"""
import os
import base64
import asyncio
from functools import partial
from typing import Optional
from loguru import logger
import requests

from app.tts.base import BaseTTS, _TTS_EXECUTOR
from app.config import settings, reload_custom_settings


class GoogleCloudTTSEngine(BaseTTS):
    """
    Google Cloud Text-to-Speech REST Engine.
    High-fidelity neural voices with multilingual and Turkish (tr-TR) support.
    """

    DEFAULT_VOICES = {
        "tr": "tr-TR-Neural2-A",
        "en": "en-US-Journey-F",
    }

    def __init__(self, model_id: str = "google-cloud-tts"):
        self._model_id = model_id

    @property
    def engine_name(self) -> str:
        return self._model_id

    def is_model_downloaded(self) -> bool:
        """Cloud API models are ready if an API key is available."""
        key = self._get_api_key()
        return bool(key)

    def _get_api_key(self) -> Optional[str]:
        reload_custom_settings()
        key = (
            os.environ.get("GOOGLE_CLOUD_API_KEY")
            or getattr(settings, "GOOGLE_CLOUD_API_KEY", None)
            or os.environ.get("GEMINI_API_KEY")
        )
        if key:
            return str(key).strip()
        return None

    def _generate(self, text: str, output_path: str, language: str = "tr", voice: Optional[str] = None) -> bool:
        api_key = self._get_api_key()
        if not api_key:
            logger.error("GOOGLE_CLOUD_API_KEY / GEMINI_API_KEY is not configured. Please define it in Models or Settings.")
            return False

        lang_code = "tr-TR" if language.lower().startswith("tr") else (language if "-" in language else f"{language}-US")
        voice_name = voice or self.DEFAULT_VOICES.get(language[:2].lower(), "tr-TR-Neural2-A")

        endpoint = f"https://texttospeech.googleapis.com/v1/text:synthesize?key={api_key}"
        payload = {
            "input": {"text": text},
            "voice": {
                "languageCode": lang_code,
                "name": voice_name
            },
            "audioConfig": {
                "audioEncoding": "LINEAR16"  # Generates standard uncompressed WAV bytes
            }
        }

        headers = {
            "Content-Type": "application/json",
        }

        logger.info(f"Google Cloud TTS ({voice_name}) generating: '{text[:40]}...'")

        try:
            resp = requests.post(endpoint, json=payload, headers=headers, timeout=60)
            if resp.status_code != 200:
                logger.error(f"Google Cloud TTS API error (HTTP {resp.status_code}): {resp.text}")
                return False

            data = resp.json()
            audio_base64 = data.get("audioContent")
            if not audio_base64:
                logger.error("Google Cloud TTS response did not contain audioContent.")
                return False

            raw_bytes = base64.b64decode(audio_base64)
            os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
            with open(output_path, "wb") as f:
                f.write(raw_bytes)

            if os.path.exists(output_path) and os.path.getsize(output_path) > 100:
                logger.info(f"Google Cloud TTS audio successfully written to {output_path}")
                return True
            else:
                logger.error(f"Google Cloud TTS output file was empty or missing: {output_path}")
                return False
        except Exception as e:
            logger.error(f"Google Cloud TTS generation request failed: {e}")
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
        voice = kwargs.get("voice", None)
        loop = asyncio.get_running_loop()
        func = partial(self._generate, text, output_path, language, voice)
        return await loop.run_in_executor(_TTS_EXECUTOR, func)
