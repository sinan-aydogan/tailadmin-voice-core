"""
Google Cloud Speech-to-Text STT Engine (Chirp & v1 REST API).
"""
import os
import base64
import asyncio
from functools import partial
from typing import Optional, Dict, Any, Union
from loguru import logger
import requests

from app.stt.base import BaseSTT
from app.config import settings, reload_custom_settings


class GoogleCloudSTTEngine(BaseSTT):
    """
    Google Cloud Speech-to-Text Engine.
    Accurate enterprise transcription for Turkish (tr-TR) and 125+ languages.
    """

    def __init__(self, model_id: str = "google-cloud-stt"):
        self._model_id = model_id

    @property
    def engine_name(self) -> str:
        return self._model_id

    def is_model_downloaded(self, model_size: str = "") -> bool:
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

    def _transcribe_sync(self, audio_path: str, language: Optional[str] = None, **kwargs) -> Union[Dict[str, Any], str]:
        api_key = self._get_api_key()
        if not api_key:
            return "Error: GOOGLE_CLOUD_API_KEY / GEMINI_API_KEY is not configured. Please define it in Models or Settings."

        endpoint = f"https://speech.googleapis.com/v1/speech:recognize?key={api_key}"
        lang_code = "tr-TR" if not language or language.lower().startswith("tr") else language

        logger.info(f"Transcribing via Google Cloud STT ({audio_path}, lang: {lang_code})...")

        try:
            with open(audio_path, "rb") as f:
                content_base64 = base64.b64encode(f.read()).decode("utf-8")

            payload = {
                "config": {
                    "languageCode": lang_code,
                    "enableAutomaticPunctuation": True,
                    "model": "default"
                },
                "audio": {
                    "content": content_base64
                }
            }

            resp = requests.post(endpoint, json=payload, timeout=120)
            if resp.status_code != 200:
                logger.error(f"Google Cloud STT API error (HTTP {resp.status_code}): {resp.text}")
                return f"Error: Google Cloud STT API failed ({resp.status_code}): {resp.text}"

            result_json = resp.json()
            results = result_json.get("results", [])
            transcripts = []
            for r in results:
                alternatives = r.get("alternatives", [])
                if alternatives:
                    transcripts.append(alternatives[0].get("transcript", ""))

            full_text = " ".join(transcripts).strip()
            return {
                "text": full_text,
                "language": lang_code,
                "segments": []
            }
        except Exception as e:
            logger.error(f"Google Cloud STT transcription failed: {e}")
            return f"Error: {str(e)}"

    async def transcribe(
        self,
        audio_path: str,
        language: Optional[str] = None,
        model_size: Optional[str] = None,
        **kwargs
    ) -> Union[Dict[str, Any], str]:
        if not os.path.exists(audio_path):
            raise FileNotFoundError(f"Audio file not found: {audio_path}")

        loop = asyncio.get_event_loop()
        func = partial(self._transcribe_sync, audio_path, language, **kwargs)
        return await loop.run_in_executor(None, func)
