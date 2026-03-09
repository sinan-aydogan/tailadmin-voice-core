"""
Tortoise TTS Wrapper - Runs Tortoise in isolated subprocess to avoid dependency conflicts.
"""
import os
import sys
import asyncio
import subprocess
import tempfile
import json
from concurrent.futures import ThreadPoolExecutor
from functools import partial
from pathlib import Path
from typing import Optional
from loguru import logger

from app.config import settings

# Dedicated executor for Tortoise (subprocess-based, isolated)
_TORTOISE_EXECUTOR = ThreadPoolExecutor(max_workers=1, thread_name_prefix="tortoise")


class TortoiseWrapper:
    """
    Wrapper that runs Tortoise TTS in a separate Python environment.
    This avoids dependency conflicts with other TTS engines.
    """
    
    def __init__(self):
        # Resolve project root from this file's location (app/tts/tortoise_wrapper.py -> project root)
        project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
        self.tortoise_env_path = os.path.join(project_root, ".venv-tortoise")
        self.model_path = os.path.join(project_root, str(settings.MODELS_DIR), "tortoise")
        self._setup_attempted = False
    
    def _ensure_environment_sync(self) -> bool:
        """Blocking: Ensure Tortoise environment exists, create if needed."""
        if os.path.exists(self.tortoise_env_path):
            python_exe = self._get_python_executable()
            if os.path.exists(python_exe):
                return True
        
        if self._setup_attempted:
            return False
        
        self._setup_attempted = True
        logger.info("Tortoise environment not found. Setting up automatically...")
        
        try:
            return self._run_setup_sync()
        except Exception as e:
            logger.error(f"Failed to setup Tortoise environment: {e}")
            return False
    
    def _run_setup_sync(self) -> bool:
        """Blocking: Run Tortoise environment setup."""
        # Find project root
        project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
        setup_script = os.path.join(project_root, "setup-tortoise.sh")
        
        if not os.path.exists(setup_script):
            logger.error(f"Setup script not found: {setup_script}")
            return False
        
        logger.info("Running Tortoise setup script (this may take a few minutes)...")
        
        try:
            result = subprocess.run(
                ["bash", setup_script],
                cwd=project_root,
                capture_output=True,
                text=True,
                timeout=600
            )
            
            if result.returncode == 0:
                logger.success("Tortoise environment setup completed!")
                return True
            else:
                logger.error(f"Setup script failed: {result.stderr}")
                return False
        except subprocess.TimeoutExpired:
            logger.error("Tortoise setup timed out")
            return False
        except Exception as e:
            logger.error(f"Setup error: {e}")
            return False
    
    def _get_python_executable(self) -> str:
        """Get the Python executable from Tortoise environment."""
        return os.path.join(self.tortoise_env_path, "bin", "python")
    
    def is_model_downloaded(self) -> bool:
        """Check if Tortoise models exist."""
        return os.path.exists(self.model_path) and bool(os.listdir(self.model_path))
    
    def _generate_sync(
        self,
        text: str,
        output_path: str,
        language: str = "en",
        profile_path: Optional[str] = None,
        preset: str = "fast",
        **kwargs
    ) -> bool:
        """Blocking: Run full Tortoise pipeline (env setup + generation) in thread."""
        # Ensure environment first (blocking, but runs in thread)
        if not self._ensure_environment_sync():
            logger.error("Tortoise environment could not be set up")
            return False
        
        python_exe = self._get_python_executable()
        
        if not os.path.exists(python_exe):
            logger.error(f"Tortoise Python not found at {python_exe}")
            return False
        
        if not self.is_model_downloaded():
            logger.error(f"Tortoise model not found at {self.model_path}")
            return False
        
        # Create temp script for Tortoise generation
        script_content = f'''
import sys
import traceback
import multiprocessing
# Fix macOS semaphore leak - must be set before any multiprocessing usage
multiprocessing.set_start_method("fork", force=True)
sys.path.insert(0, "{self.tortoise_env_path}/lib/python{sys.version_info.major}.{sys.version_info.minor}/site-packages")

import os
import warnings
warnings.filterwarnings("ignore")

try:
    import torch
    import torchaudio
    from tortoise.api import TextToSpeech, MODELS_DIR as TORTOISE_MODELS_DIR
    from tortoise.utils.audio import load_voices

    # Set model path
    os.environ["XDG_CACHE_HOME"] = "{settings.MODELS_DIR}"

    # Load model - force CPU by patching MPS detection (MPS crashes in subprocess on macOS)
    import unittest.mock
    device = "cpu"
    print(f"Tortoise using device: {{device}}")
    with unittest.mock.patch("torch.backends.mps.is_available", return_value=False):
        tts = TextToSpeech(
            models_dir="{self.model_path}",
            use_deepspeed=False,
            kv_cache=False,
            half=False,
            device=device
        )
        tts.device = torch.device(device)

        # Prepare voice
        voice_samples = None
        conditioning_latents = None

        profile_path = "{profile_path or ""}"
        if profile_path and os.path.exists(profile_path):
            if os.path.isdir(profile_path):
                voice_name = os.path.basename(profile_path)
                extra_voice_dir = os.path.dirname(profile_path)
            else:
                voice_name = os.path.basename(os.path.dirname(profile_path))
                extra_voice_dir = os.path.dirname(os.path.dirname(profile_path))
            
            voice_samples, conditioning_latents = load_voices([voice_name], extra_voice_dirs=[extra_voice_dir])

        # Generate with minimal memory settings (MPS patched to False so CPU path is used)
        text = {repr(text)}
        gen = tts.tts(
            text,
            voice_samples=voice_samples,
            conditioning_latents=conditioning_latents,
            num_autoregressive_samples=16,
            diffusion_iterations=10,
            cond_free=False,
            use_deterministic_seed=42,
        )

    # Save - handle both tensor and list output
    if isinstance(gen, list):
        gen = gen[0]
    if len(gen.shape) == 1:
        gen = gen.unsqueeze(0)
    elif len(gen.shape) == 3:
        gen = gen.squeeze(0)
    
    torchaudio.save("{output_path}", gen.cpu(), 24000)
    print("SUCCESS")
except Exception as e:
    print("ERROR:", str(e))
    traceback.print_exc()
    sys.exit(1)
'''
        
        # Write temp script
        script_path = None
        with tempfile.NamedTemporaryFile(mode='w', suffix='.py', delete=False) as f:
            f.write(script_content)
            script_path = f.name
        
        try:
            logger.info(f"Running Tortoise TTS in subprocess for: {text[:30]}...")
            
            result = subprocess.run(
                [python_exe, script_path],
                capture_output=True,
                text=True,
                timeout=600,
                env={
                    **os.environ,
                    "PYTHONPATH": self.tortoise_env_path,
                    "PYTORCH_MPS_HIGH_WATERMARK_RATIO": "0.0",  # Disable MPS memory in subprocess
                    "DISABLE_MPS": "1",                         # Custom flag to disable MPS
                    "PYTORCH_ENABLE_MPS_FALLBACK": "1",
                }
            )
            
            if result.returncode != 0:
                logger.error(f"Tortoise subprocess failed: {result.stderr[-3000:]}")
                logger.error(f"Tortoise stdout: {result.stdout[-1000:]}")
                return False
            
            if "SUCCESS" in result.stdout:
                logger.success(f"Tortoise generated: {output_path}")
                return True
            else:
                logger.error(f"Tortoise generation no SUCCESS marker. stdout: {result.stdout[-1000:]}")
                logger.error(f"stderr: {result.stderr[-1000:]}")
                return False
                
        except subprocess.TimeoutExpired:
            logger.error("Tortoise generation timed out")
            return False
        except Exception as e:
            logger.error(f"Tortoise wrapper error: {e}")
            return False
        finally:
            if script_path:
                try:
                    os.unlink(script_path)
                except:
                    pass

    async def generate_audio(
        self,
        text: str,
        output_path: str,
        language: str = "en",
        profile_path: Optional[str] = None,
        preset: str = "fast",
        **kwargs
    ) -> bool:
        """
        Non-blocking: Run full Tortoise pipeline in dedicated thread executor.
        """
        loop = asyncio.get_event_loop()
        sync_func = partial(
            self._generate_sync,
            text,
            output_path,
            language,
            profile_path,
            preset,
            **kwargs
        )
        # Run all blocking work (setup + subprocess) in thread, never blocks event loop
        return await loop.run_in_executor(_TORTOISE_EXECUTOR, sync_func)
