"""
Piper TTS Engine Implementation.
Lightweight, fast TTS optimized for Raspberry Pi and local inference.
"""
import os
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
    def __init__(self, model_name: str = "tr_TR-dfki-medium"):
        self._model_name = model_name
        self.model_path = os.path.join(settings.MODELS_DIR, "piper", model_name)
        self._piper_binary = self._find_piper_binary()
        
    @property
    def engine_name(self) -> str:
        return f"piper-{self._model_name}"
        
    def _find_piper_binary(self) -> Optional[str]:
        """Find piper binary in PATH or common locations."""
        # Check PATH
        import shutil
        piper_path = shutil.which("piper")
        if piper_path:
            return piper_path
            
        # Check common locations
        common_paths = [
            "/usr/local/bin/piper",
            "/usr/bin/piper",
            os.path.expanduser("~/.local/bin/piper"),
            os.path.join(settings.MODELS_DIR, "piper", "piper"),
        ]
        for path in common_paths:
            if os.path.exists(path):
                return path
        return None
        
    def is_model_downloaded(self) -> bool:
        """Check if Piper model exists."""
        onnx_path = f"{self.model_path}.onnx"
        json_path = f"{self.model_path}.onnx.json"
        return os.path.exists(onnx_path) and os.path.exists(json_path)

    def _generate_sync(self, text: str, output_path: str, language: str, profile_path: Optional[str], **kwargs):
        """Synchronous generation using piper command line."""
        if not self._piper_binary:
            logger.error("Piper binary not found. Install with: pip install piper-tts")
            return False
            
        if not self.is_model_downloaded():
            logger.error(f"Piper model not found at {self.model_path}")
            return False
            
        logger.info(f"Generating Piper audio for text: '{text[:30]}...' to {output_path}")
        
        try:
            # Create temp file for text input
            with tempfile.NamedTemporaryFile(mode='w', suffix='.txt', delete=False) as f:
                f.write(text)
                text_file = f.name
            
            # Build piper command
            model_file = f"{self.model_path}.onnx"
            cmd = [
                self._piper_binary,
                "--model", model_file,
                "--file", text_file,
                "--output_file", output_path,
                "--sentence_silence", str(kwargs.get("sentence_silence", 0.2)),
            ]
            
            # Add speaker if specified (for multi-speaker models)
            speaker_id = kwargs.get("speaker_id")
            if speaker_id is not None:
                cmd.extend(["--speaker", str(speaker_id)])
            
            # Add length scale (speed)
            length_scale = kwargs.get("length_scale", 1.0)
            if length_scale != 1.0:
                cmd.extend(["--length_scale", str(length_scale)])
            
            # Run piper
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=300  # 5 minute timeout
            )
            
            # Cleanup temp file
            os.unlink(text_file)
            
            if result.returncode != 0:
                logger.error(f"Piper failed: {result.stderr}")
                return False
                
            logger.success(f"Successfully generated Piper audio: {output_path}")
            return True
            
        except subprocess.TimeoutExpired:
            logger.error("Piper generation timed out")
            return False
        except Exception as e:
            logger.error(f"Piper Generation Error: {e}")
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
        """Run Piper generation asynchronously."""
        if not self._piper_binary:
            logger.error("Piper binary not found. Install with: pip install piper-tts")
            return False
            
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
