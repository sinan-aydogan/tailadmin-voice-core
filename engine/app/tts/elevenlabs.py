"""
ElevenLabs Text-to-Speech Engine (eleven_multilingual_v2, eleven_flash_v2_5).
"""
import os
import asyncio
from functools import partial
from typing import Optional
from loguru import logger
import requests

from app.tts.base import BaseTTS, _TTS_EXECUTOR
from app.config import settings, reload_custom_settings


class ElevenLabsTTSEngine(BaseTTS):
    """
    ElevenLabs Cloud Text-to-Speech Engine.
    Industry-leading natural emotion, tone, and inflection in 29+ languages.
    """

    DEFAULT_VOICES = {
        "rachel": "21m00Tcm4TlvDq8ikWAM",
        "adam": "pNInz6obpgDQGcFmaJgB",
        "charlie": "IKne3meq5aSn9XLyUdCD",
        "george": "JBFqnCBsd6RMkjVDRZzb",
    }

    def __init__(self, model_id: str = "elevenlabs-multilingual", default_voice_id: str = "21m00Tcm4TlvDq8ikWAM"):
        self._model_id = model_id
        self._default_voice_id = default_voice_id
        self._api_model = "eleven_flash_v2_5" if "flash" in model_id.lower() else "eleven_multilingual_v2"

    @property
    def engine_name(self) -> str:
        return self._model_id

    def is_model_downloaded(self) -> bool:
        """Cloud API models are ready if an API key is available."""
        key = self._get_api_key()
        return bool(key)

    def _get_api_key(self) -> Optional[str]:
        reload_custom_settings()
        key = os.environ.get("ELEVENLABS_API_KEY") or getattr(settings, "ELEVENLABS_API_KEY", None)
        if key:
            return str(key).strip()
        return None

    def _generate(self, text: str, output_path: str, voice: Optional[str] = None, **kwargs) -> bool:
        api_key = self._get_api_key()
        if not api_key:
            logger.error("ELEVENLABS_API_KEY is not configured. Please define it in Models or Settings.")
            return False

        # Resolve voice ID
        voice_id = self._default_voice_id
        if voice:
            voice_lower = str(voice).lower().strip()
            voice_id = self.DEFAULT_VOICES.get(voice_lower, voice)

        # Dynamic voice settings
        stability = kwargs.get("stability")
        try:
            stability = float(stability) if stability is not None else 0.50
            stability = max(0.0, min(1.0, stability))
        except (ValueError, TypeError):
            stability = 0.50

        similarity_boost = kwargs.get("similarity_boost")
        try:
            similarity_boost = float(similarity_boost) if similarity_boost is not None else 0.75
            similarity_boost = max(0.0, min(1.0, similarity_boost))
        except (ValueError, TypeError):
            similarity_boost = 0.75

        style = kwargs.get("style")
        try:
            style = float(style) if style is not None else 0.0
            style = max(0.0, min(1.0, style))
        except (ValueError, TypeError):
            style = 0.0

        endpoint = f"https://api.elevenlabs.io/v1/text-to-speech/{voice_id}"
        payload = {
            "text": text,
            "model_id": self._api_model,
            "voice_settings": {
                "stability": stability,
                "similarity_boost": similarity_boost,
                "style": style,
                "use_speaker_boost": True
            }
        }

        headers = {
            "xi-api-key": api_key,
            "Content-Type": "application/json",
            "Accept": "audio/mpeg"
        }

        logger.info(f"ElevenLabs TTS ({self._api_model}, voice: {voice_id}) generating: '{text[:40]}...'")

        try:
            resp = requests.post(endpoint, json=payload, headers=headers, timeout=90)
            if resp.status_code != 200:
                logger.error(f"ElevenLabs API error (HTTP {resp.status_code}): {resp.text}")
                return False

            os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
            with open(output_path, "wb") as f:
                f.write(resp.content)

            if os.path.exists(output_path) and os.path.getsize(output_path) > 100:
                logger.info(f"ElevenLabs TTS audio successfully written to {output_path}")
                return True
            else:
                logger.error(f"ElevenLabs output file was empty or missing: {output_path}")
                return False
        except Exception as e:
            logger.error(f"ElevenLabs TTS generation request failed: {e}")
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
        func = partial(self._generate, text, output_path, voice, **kwargs)
        return await loop.run_in_executor(_TTS_EXECUTOR, func)
