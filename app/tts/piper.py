"""
Piper TTS Engine Implementation.
Lightweight, fast TTS optimized for Raspberry Pi and local inference.
"""
import os
import sys
import asyncio
import subprocess
import tempfile
from functools import partial
from typing import Optional
from pathlib import Path
from loguru import logger

from app.tts.base import BaseTTS, _TTS_EXECUTOR
from app.config import settings


class PiperEngine(BaseTTS):
    def __init__(self, model_name: str = "tr_TR-dfki-medium", model_id: str = "piper-tr"):
        self._model_name = model_name
        # Models are downloaded to MODELS_DIR/<model_id>/ (e.g. data/models/piper-tr/),
        # so the engine must look there — NOT under a "piper/<model_name>" subdir.
        self._model_id = model_id
        self.model_path = os.path.join(settings.MODELS_DIR, model_id, model_name)

    @property
    def engine_name(self) -> str:
        return self._model_id

    def is_model_downloaded(self) -> bool:
        """Check if Piper model exists."""
        onnx_path = f"{self.model_path}.onnx"
        json_path = f"{self.model_path}.onnx.json"
        return os.path.exists(onnx_path) and os.path.exists(json_path)

    def _generate_sync(self, text: str, output_path: str, language: str, profile_path: Optional[str], **kwargs):
        """Synchronous generation via `python -m piper`.

        Invoking the module through the current interpreter avoids depending on a
        `piper` binary being on PATH (which it isn't when the venv isn't activated).
        """
        if not self.is_model_downloaded():
            logger.error(f"Piper model not found at {self.model_path}")
            return False

        logger.info(f"Generating Piper audio for text: '{text[:30]}...' to {output_path}")

        text_file = None
        try:
            os.makedirs(os.path.dirname(output_path), exist_ok=True)

            with tempfile.NamedTemporaryFile(mode='w', suffix='.txt', delete=False, encoding='utf-8') as f:
                f.write(text)
                text_file = f.name

            model_file = f"{self.model_path}.onnx"
            # piper-tts 1.4.x CLI uses hyphenated flags; input is --input-file (not --file).
            cmd = [
                sys.executable, "-m", "piper",
                "--model", model_file,
                "--input-file", text_file,
                "--output-file", output_path,
                "--sentence-silence", str(kwargs.get("sentence_silence", 0.2)),
            ]

            speaker_id = kwargs.get("speaker_id")
            if speaker_id is not None:
                cmd.extend(["--speaker", str(speaker_id)])

            length_scale = kwargs.get("length_scale", 1.0)
            if length_scale != 1.0:
                cmd.extend(["--length-scale", str(length_scale)])

            result = subprocess.run(cmd, capture_output=True, text=True, timeout=300)

            if result.returncode != 0:
                logger.error(f"Piper failed: {result.stderr[-1000:]}")
                return False

            logger.success(f"Successfully generated Piper audio: {output_path}")
            return True

        except subprocess.TimeoutExpired:
            logger.error("Piper generation timed out")
            return False
        except Exception as e:
            logger.error(f"Piper Generation Error: {e}")
            return False
        finally:
            if text_file and os.path.exists(text_file):
                try:
                    os.unlink(text_file)
                except OSError:
                    pass

    async def generate_audio(
        self,
        text: str,
        output_path: str,
        language: str = "tr",
        profile_path: Optional[str] = None,
        user_id: Optional[str] = None,
        **kwargs
    ) -> bool:
        """Run Piper generation asynchronously."""
        if not self.is_model_downloaded():
            logger.error(f"Piper model not found at {self.model_path}")
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
