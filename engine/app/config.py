"""
Project configuration and environment variables.
"""
import sys
import os

# On Windows, ensure SystemRoot and WINDIR are present in os.environ before
# importing pydantic_settings/asyncio to avoid Winsock WSAEPROVIDERFAILEDINIT (WinError 10106).
if sys.platform == "win32":
    if "SystemRoot" not in os.environ and "SYSTEMROOT" not in os.environ:
        os.environ["SystemRoot"] = os.environ.get("WINDIR", r"C:\Windows")
    if "WINDIR" not in os.environ and "windir" not in os.environ:
        os.environ["WINDIR"] = os.environ.get("SystemRoot", r"C:\Windows")

from pydantic_settings import BaseSettings, SettingsConfigDict
from typing import Optional
from pathlib import Path

class Settings(BaseSettings):
    # Application
    APP_ENV: str = "development"
    SECRET_KEY: str = "change-this-secret-key-in-production"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 60
    
    # Ports (used primarily by runner scripts)
    API_PORT: int = 5001
    UI_PORT: int = 5002
    
    # Database
    DATABASE_URL: str = "sqlite:///./data/voice_core.db"
    
    # Default User
    DEFAULT_USERNAME: str = "tailadmin.dev"
    DEFAULT_PASSWORD: str = "admin"
    
    # Paths
    ROOT_DIR: Path = Path(__file__).resolve().parent.parent.parent
    DATA_DIR: Path = ROOT_DIR / "data"
    MODELS_DIR: Path = ROOT_DIR / "data" / "models"
    OUTPUTS_DIR: Path = ROOT_DIR / "data" / "outputs"
    PROFILES_DIR: Path = ROOT_DIR / "data" / "profiles"
    UPLOADS_DIR: Path = ROOT_DIR / "data" / "uploads"
    
    # Default Engines
    DEFAULT_TTS_ENGINE: str = "xtts"
    DEFAULT_TTS_LANGUAGE: str = "tr"
    DEFAULT_STT_ENGINE: str = "whisper"
    DEFAULT_WHISPER_MODEL: str = "medium"
    DEFAULT_STT_MODEL_SIZE: str = "whisper-medium"
    
    # Hardware / Processing
    USE_GPU: str = "auto"
    MAX_CPU_THREADS: int = 4
    PYTORCH_ENABLE_MPS_FALLBACK: int = 1

    # Server runtime
    # Auto-reload watches files and restarts on change. Convenient in dev, but it
    # KILLS in-flight downloads/tasks and can leak the port. Keep off by default.
    RELOAD: bool = False
    # Queue worker placement:
    #   "inline"   -> worker runs inside the API process (default; backward compatible)
    #   "separate" -> worker runs as its own OS process (`python -m app.queue`), so
    #                 model inference GIL never blocks the API event loop.
    WORKER_MODE: str = "inline"
    # Shared secret for the API's /internal/notify endpoint, used by a separate
    # worker process to push WebSocket notifications through the API (which owns the
    # WS connections). Change in production.
    INTERNAL_NOTIFY_TOKEN: str = "change-me-internal"
    # Comma-separated model ids to pre-load into RAM at startup ("" = none,
    # "all" = every healthy model). Pre-loading everything wastes memory on Mac.
    PRELOAD_MODELS: str = ""
    # Push live system-resource stats (CPU/RAM/disk) to the UI footer over WS.
    SYSTEM_STATS_ENABLED: bool = True
    SYSTEM_STATS_INTERVAL_SEC: float = 3.0

    # Logging
    LOG_LEVEL: str = "INFO"
    LOG_RETENTION_DAYS: int = 30
    
    # HuggingFace Token (optional, for authenticated downloads)
    HF_TOKEN: Optional[str] = None

    # Freya Voice (Adam & Eve, YC S25 / Tunga Bayrak)
    FREYA_API_KEY: Optional[str] = None
    FREYA_DEFAULT_VOICE: str = "adam"

    # Cloud Providers (TTS, STT & LLM)
    OPENAI_API_KEY: Optional[str] = None
    ELEVENLABS_API_KEY: Optional[str] = None
    GOOGLE_CLOUD_API_KEY: Optional[str] = None
    GROQ_API_KEY: Optional[str] = None
    GEMINI_API_KEY: Optional[str] = None
    ANTHROPIC_API_KEY: Optional[str] = None
    DEEPSEEK_API_KEY: Optional[str] = None
    OPENROUTER_API_KEY: Optional[str] = None
    PATIENTDESK_API_KEY: Optional[str] = None
    
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore"
    )

import json
import os

# Instantiate global settings
settings = Settings()

def reload_custom_settings():
    """Reload dynamic settings from data/settings.json into global settings and env vars."""
    _settings_file = settings.DATA_DIR / "settings.json"
    if _settings_file.exists():
        try:
            with open(_settings_file, "r", encoding="utf-8") as _f:
                _custom = json.load(_f)
                if "models_dir" in _custom and _custom["models_dir"]:
                    _custom_path = Path(_custom["models_dir"])
                    settings.MODELS_DIR = _custom_path
                if "hf_token" in _custom:
                    val = str(_custom["hf_token"]).strip() if _custom["hf_token"] else None
                    settings.HF_TOKEN = val
                    if val:
                        os.environ["HF_TOKEN"] = val
                        os.environ["HUGGING_FACE_HUB_TOKEN"] = val
                    else:
                        os.environ.pop("HF_TOKEN", None)
                        os.environ.pop("HUGGING_FACE_HUB_TOKEN", None)
                if "freya_api_key" in _custom:
                    f_val = str(_custom["freya_api_key"]).strip() if _custom["freya_api_key"] else None
                    settings.FREYA_API_KEY = f_val
                    if f_val:
                        os.environ["FREYA_API_KEY"] = f_val
                    else:
                        os.environ.pop("FREYA_API_KEY", None)
                if "openai_api_key" in _custom:
                    o_val = str(_custom["openai_api_key"]).strip() if _custom["openai_api_key"] else None
                    settings.OPENAI_API_KEY = o_val
                    if o_val:
                        os.environ["OPENAI_API_KEY"] = o_val
                    else:
                        os.environ.pop("OPENAI_API_KEY", None)
                if "elevenlabs_api_key" in _custom:
                    e_val = str(_custom["elevenlabs_api_key"]).strip() if _custom["elevenlabs_api_key"] else None
                    settings.ELEVENLABS_API_KEY = e_val
                    if e_val:
                        os.environ["ELEVENLABS_API_KEY"] = e_val
                    else:
                        os.environ.pop("ELEVENLABS_API_KEY", None)
                if "google_cloud_api_key" in _custom or "gemini_api_key" in _custom:
                    g_val = str(_custom.get("google_cloud_api_key") or _custom.get("gemini_api_key") or "").strip() or None
                    settings.GOOGLE_CLOUD_API_KEY = g_val
                    settings.GEMINI_API_KEY = g_val
                    if g_val:
                        os.environ["GOOGLE_CLOUD_API_KEY"] = g_val
                        os.environ["GEMINI_API_KEY"] = g_val
                    else:
                        os.environ.pop("GOOGLE_CLOUD_API_KEY", None)
                        os.environ.pop("GEMINI_API_KEY", None)
                if "groq_api_key" in _custom:
                    q_val = str(_custom["groq_api_key"]).strip() if _custom["groq_api_key"] else None
                    settings.GROQ_API_KEY = q_val
                    if q_val:
                        os.environ["GROQ_API_KEY"] = q_val
                    else:
                        os.environ.pop("GROQ_API_KEY", None)
                if "anthropic_api_key" in _custom or "claude_api_key" in _custom:
                    ant_val = str(_custom.get("anthropic_api_key") or _custom.get("claude_api_key") or "").strip() or None
                    settings.ANTHROPIC_API_KEY = ant_val
                    if ant_val:
                        os.environ["ANTHROPIC_API_KEY"] = ant_val
                        os.environ["CLAUDE_API_KEY"] = ant_val
                    else:
                        os.environ.pop("ANTHROPIC_API_KEY", None)
                        os.environ.pop("CLAUDE_API_KEY", None)
                if "deepseek_api_key" in _custom:
                    ds_val = str(_custom["deepseek_api_key"]).strip() if _custom["deepseek_api_key"] else None
                    settings.DEEPSEEK_API_KEY = ds_val
                    if ds_val:
                        os.environ["DEEPSEEK_API_KEY"] = ds_val
                    else:
                        os.environ.pop("DEEPSEEK_API_KEY", None)
                if "openrouter_api_key" in _custom:
                    or_val = str(_custom["openrouter_api_key"]).strip() if _custom["openrouter_api_key"] else None
                    settings.OPENROUTER_API_KEY = or_val
                    if or_val:
                        os.environ["OPENROUTER_API_KEY"] = or_val
                    else:
                        os.environ.pop("OPENROUTER_API_KEY", None)
                if "patientdesk_api_key" in _custom:
                    pd_val = str(_custom["patientdesk_api_key"]).strip() if _custom["patientdesk_api_key"] else None
                    settings.PATIENTDESK_API_KEY = pd_val
                    if pd_val:
                        os.environ["PATIENTDESK_API_KEY"] = pd_val
                    else:
                        os.environ.pop("PATIENTDESK_API_KEY", None)
                if "freya_default_voice" in _custom and _custom["freya_default_voice"]:
                    settings.FREYA_DEFAULT_VOICE = str(_custom["freya_default_voice"]).strip()
                if "default_stt_engine" in _custom and _custom["default_stt_engine"]:
                    settings.DEFAULT_STT_ENGINE = str(_custom["default_stt_engine"]).strip()
                if "default_stt_model" in _custom and _custom["default_stt_model"]:
                    stt_mod = str(_custom["default_stt_model"]).strip()
                    settings.DEFAULT_WHISPER_MODEL = stt_mod
                    settings.DEFAULT_STT_MODEL_SIZE = stt_mod
        except Exception:
            pass

# Initial load from data/settings.json
reload_custom_settings()

# Ensure directories exist
for path in [settings.MODELS_DIR, settings.OUTPUTS_DIR, settings.PROFILES_DIR, settings.UPLOADS_DIR]:
    path.mkdir(parents=True, exist_ok=True)
