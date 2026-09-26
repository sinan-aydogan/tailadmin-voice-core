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
        self.model_path = os.path.join(str(settings.MODELS_DIR), "tortoise")
        self._setup_attempted = False
    
    def _ensure_environment_sync(self) -> bool:
        """Blocking: Ensure Tortoise environment exists, create if needed."""
        python_exe = self._get_python_executable()
        if os.path.exists(python_exe):
            try:
                res = subprocess.run(
                    [python_exe, "-c", "from tortoise.api import TextToSpeech"],
                    capture_output=True,
                    text=True,
                    timeout=15
                )
                if res.returncode == 0:
                    return True
            except Exception as e:
                logger.warning(f"Error testing Tortoise import: {e}")
        
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
        if sys.platform == "win32":
            # On Windows, install directly to current Python environment
            logger.info("Installing Tortoise TTS into Python environment...")
            try:
                result = subprocess.run(
                    [sys.executable, "-m", "pip", "install", "--no-deps", "tortoise-tts", "progressbar"],
                    capture_output=True,
                    text=True,
                    timeout=300
                )
                return result.returncode == 0
            except Exception as e:
                logger.error(f"Windows Tortoise install error: {e}")
                return False

        # On POSIX (macOS/Linux), use setup script if available
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
        """Get the Python executable from Tortoise environment or current running environment."""
        if sys.platform == "win32":
            isolated_exe = os.path.join(self.tortoise_env_path, "Scripts", "python.exe")
        else:
            isolated_exe = os.path.join(self.tortoise_env_path, "bin", "python")

        if os.path.exists(isolated_exe):
            return isolated_exe

        # Default to current Python executable where tortoise-tts is installed
        return sys.executable
    
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
        
        # Normalize all paths to avoid Windows \U unicodeescape syntax errors
        norm_models_dir = str(settings.MODELS_DIR).replace('\\', '/')
        norm_model_path = str(self.model_path).replace('\\', '/')
        norm_output_path = str(output_path).replace('\\', '/')
        norm_profile_path = str(profile_path or "").replace('\\', '/')
        norm_env_path = str(self.tortoise_env_path).replace('\\', '/')

        # Determine sample counts based on preset and hardware
        preset_setting = preset or "fast"
        try:
            import torch
            has_gpu = torch.cuda.is_available()
        except Exception:
            has_gpu = False

        if not has_gpu:
            # On CPU, multi-sample autoregressive passes take 15+ minutes.
            # Use single sample and minimal diffusion steps to avoid 600s timeout.
            ar_samples = 1
            diff_steps = 3
        elif preset_setting in ("ultra_fast", "fast"):
            ar_samples = 4
            diff_steps = 10
        elif preset_setting == "standard":
            ar_samples = 8
            diff_steps = 20
        else:
            ar_samples = 16
            diff_steps = 30

        # Create temp script for Tortoise generation
        script_content = f'''
import sys
import traceback
import os
import warnings
warnings.filterwarnings("ignore")

# Fix macOS semaphore leak only on non-Windows
if sys.platform != "win32":
    import multiprocessing
    try:
        multiprocessing.set_start_method("fork", force=True)
    except Exception:
        pass

# Add isolated venv site-packages if it exists
if os.path.exists("{norm_env_path}"):
    for sp in [
        os.path.join("{norm_env_path}", "Lib", "site-packages"),
        os.path.join("{norm_env_path}", "lib", f"python{{sys.version_info.major}}.{{sys.version_info.minor}}", "site-packages")
    ]:
        if os.path.isdir(sp):
            sys.path.insert(0, sp)
            break

try:
    import torch
    import torchaudio
    from tortoise.api import TextToSpeech, MODELS_DIR as TORTOISE_MODELS_DIR
    from tortoise.utils.audio import load_voices, load_audio

    # Set model path
    os.environ["XDG_CACHE_HOME"] = "{norm_models_dir}"

    device = "cuda" if torch.cuda.is_available() else "cpu"
    print(f"Tortoise using device: {{device}}")
    
    tts = TextToSpeech(
        models_dir="{norm_model_path}",
        enable_redaction=False,
        use_deepspeed=False,
        kv_cache=False,
        half=False,
        device=device
    )

    # Prepare voice
    voice_samples = None
    conditioning_latents = None

    profile_path = "{norm_profile_path}"
    if profile_path and os.path.exists(profile_path):
        if os.path.isdir(profile_path):
            voice_name = os.path.basename(profile_path)
            extra_voice_dir = os.path.dirname(profile_path)
            voice_samples, conditioning_latents = load_voices([voice_name], extra_voice_dirs=[extra_voice_dir])
        else:
            voice_name = os.path.splitext(os.path.basename(profile_path))[0]
            extra_voice_dir = os.path.dirname(profile_path)
            try:
                clip = load_audio(profile_path, 22050)
                voice_samples = [clip]
            except Exception as ve:
                print(f"Warning loading voice sample: {{ve}}")

    text = {repr(text)}
    gen = tts.tts(
        text,
        voice_samples=voice_samples,
        conditioning_latents=conditioning_latents,
        num_autoregressive_samples={ar_samples},
        diffusion_iterations={diff_steps},
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
    
    import soundfile as sf
    os.makedirs(os.path.dirname("{norm_output_path}"), exist_ok=True)
    audio_data = gen.squeeze().cpu().numpy()
    sf.write("{norm_output_path}", audio_data, 24000)
    print("SUCCESS")
except Exception as e:
    print("ERROR:", str(e))
    traceback.print_exc()
    sys.exit(1)
'''
        
        # Write temp script
        script_path = None
        with tempfile.NamedTemporaryFile(mode='w', suffix='.py', delete=False, encoding='utf-8') as f:
            f.write(script_content)
            script_path = f.name
        
        try:
            logger.info(f"Running Tortoise TTS in subprocess for: {text[:30]}...")
            
            env_vars = {
                **os.environ,
                "HF_HUB_DISABLE_SYMLINKS_WARNING": "1",
                "PYTORCH_MPS_HIGH_WATERMARK_RATIO": "0.0",
                "DISABLE_MPS": "1",
                "PYTORCH_ENABLE_MPS_FALLBACK": "1",
            }
            if os.path.exists(self.tortoise_env_path):
                env_vars["PYTHONPATH"] = self.tortoise_env_path

            result = subprocess.run(
                [python_exe, script_path],
                capture_output=True,
                text=True,
                timeout=600,
                env=env_vars
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
