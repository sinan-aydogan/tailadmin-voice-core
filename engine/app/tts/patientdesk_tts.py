"""
Patientdesk.ai Text-to-Speech Engine (Alania).
Low-latency, natural Turkish speech synthesis with OpenAI API compatible format.
"""
import os
import asyncio
from functools import partial
from typing import Optional
from loguru import logger
import requests

from app.tts.base import BaseTTS, _TTS_EXECUTOR
from app.config import settings, reload_custom_settings


class PatientdeskTTSEngine(BaseTTS):
    """
    Patientdesk.ai Cloud Text-to-Speech Engine (Alania).
    Optimized for Turkish voice assistants, call centers, and interactive systems.
    OpenAI API compatible endpoint at https://voice.patientdesk.ai/v1/audio/speech.
    """

    def __init__(self, model_id: str = "alania", default_voice: str = "default"):
        self._model_id = model_id
        self._default_voice = default_voice
        self._api_model = "alania"

    @property
    def engine_name(self) -> str:
        return self._model_id

    def is_model_downloaded(self) -> bool:
        """Cloud API models are ready if an API key is available."""
        key = self._get_api_key()
        return bool(key)

    def _get_api_key(self) -> Optional[str]:
        reload_custom_settings()
        key = os.environ.get("PATIENTDESK_API_KEY") or getattr(settings, "PATIENTDESK_API_KEY", None)
        if key:
            return str(key).strip()
        return None

    def _generate(self, text: str, output_path: str, voice: Optional[str] = None, speed: float = 1.0) -> bool:
        api_key = self._get_api_key()
        if not api_key:
            logger.error("PATIENTDESK_API_KEY is not configured. Please define it in Models or Settings.")
            return False

        selected_voice = voice or self._default_voice
        endpoint = "https://voice.patientdesk.ai/v1/audio/speech"
        payload = {
            "model": self._api_model,
            "input": text,
            "voice": selected_voice,
            "speed": max(0.25, min(4.0, float(speed))),
            "response_format": "wav",
        }

        headers = {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        }

        logger.info(f"Patientdesk TTS ({self._api_model}, voice: {selected_voice}) generating: '{text[:40]}...'")

        try:
            resp = requests.post(endpoint, json=payload, headers=headers, timeout=180)
            if resp.status_code != 200:
                logger.error(f"Patientdesk TTS API error (HTTP {resp.status_code}): {resp.text}")
                return False

            os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
            with open(output_path, "wb") as f:
                f.write(resp.content)

            if os.path.exists(output_path) and os.path.getsize(output_path) > 100:
                logger.info(f"Patientdesk TTS audio successfully written to {output_path}")
                return True
            else:
                logger.error(f"Patientdesk TTS output file was empty or missing: {output_path}")
                return False
        except Exception as e:
            logger.error(f"Patientdesk TTS generation request failed: {e}")
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
        voice = kwargs.get("voice", self._default_voice)
        speed = kwargs.get("speed", 1.0)
        loop = asyncio.get_running_loop()
        func = partial(self._generate, text, output_path, voice, speed)
        return await loop.run_in_executor(_TTS_EXECUTOR, func)
