"""
Patientdesk.ai Speech-to-Text Engine (Duyu).
High-accuracy Turkish speech-to-text with OpenAI Whisper API compatible format.
FLEURS %4.71 WER / Phone conversations %9.87 WER.
"""
import os
import asyncio
from functools import partial
from typing import Optional, Dict, Any, Union
from loguru import logger
import requests

from app.stt.base import BaseSTT
from app.config import settings, reload_custom_settings


class PatientdeskSTTEngine(BaseSTT):
    """
    Patientdesk.ai Cloud STT Engine (Duyu).
    Turkish speech transcription with FLEURS %4.71 WER.
    OpenAI Whisper API compatible endpoint at https://speech.patientdesk.ai/v1/audio/transcriptions.
    """

    def __init__(self, model_id: str = "duyu"):
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
        key = os.environ.get("PATIENTDESK_API_KEY") or getattr(settings, "PATIENTDESK_API_KEY", None)
        if key:
            return str(key).strip()
        return None

    def _transcribe_sync(self, audio_path: str, language: Optional[str] = "tr", **kwargs) -> Union[Dict[str, Any], str]:
        api_key = self._get_api_key()
        if not api_key:
            return "Error: PATIENTDESK_API_KEY is not configured. Please define it in Models or Settings."

        endpoint = "https://voice.patientdesk.ai/v1/audio/transcriptions"
        headers = {
            "Authorization": f"Bearer {api_key}"
        }

        # Patientdesk official model identifier is 'duyu-1'
        api_model = "duyu-1" if self._model_id in ("duyu", "duyu-1", None) else self._model_id
        data = {
            "model": api_model,
            "response_format": "verbose_json"
        }
        if language and language != "auto":
            data["language"] = language

        logger.info(f"Transcribing via Patientdesk Cloud STT (Duyu) ({audio_path})...")

        try:
            with open(audio_path, "rb") as f:
                files = {"file": (os.path.basename(audio_path), f)}
                resp = requests.post(endpoint, headers=headers, data=data, files=files, timeout=120)

            if resp.status_code != 200:
                logger.error(f"Patientdesk Duyu API error (HTTP {resp.status_code}): {resp.text}")
                return f"Error: Patientdesk Duyu API failed ({resp.status_code}): {resp.text}"

            result_json = resp.json()
            return {
                "text": result_json.get("text", "").strip(),
                "language": result_json.get("language", language or "tr"),
                "segments": [
                    {
                        "start": seg.get("start", 0.0),
                        "end": seg.get("end", 0.0),
                        "text": seg.get("text", "")
                    }
                    for seg in result_json.get("segments", [])
                ]
            }
        except Exception as e:
            logger.error(f"Patientdesk Duyu transcription failed: {e}")
            return f"Error: {str(e)}"

    async def transcribe(
        self,
        audio_path: str,
        language: Optional[str] = "tr",
        model_size: Optional[str] = None,
        **kwargs
    ) -> Union[Dict[str, Any], str]:
        if not os.path.exists(audio_path):
            raise FileNotFoundError(f"Audio file not found: {audio_path}")

        loop = asyncio.get_event_loop()
        func = partial(self._transcribe_sync, audio_path, language, **kwargs)
        return await loop.run_in_executor(None, func)
