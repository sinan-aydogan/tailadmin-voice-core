"""
Project configuration and environment variables.
"""
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
        except Exception:
            pass

# Initial load from data/settings.json
reload_custom_settings()

# Ensure directories exist
for path in [settings.MODELS_DIR, settings.OUTPUTS_DIR, settings.PROFILES_DIR, settings.UPLOADS_DIR]:
    path.mkdir(parents=True, exist_ok=True)
