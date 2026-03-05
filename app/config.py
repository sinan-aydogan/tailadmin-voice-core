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
    MODELS_DIR: Path = Path("./data/models")
    OUTPUTS_DIR: Path = Path("./data/outputs")
    PROFILES_DIR: Path = Path("./data/profiles")
    UPLOADS_DIR: Path = Path("./data/uploads")
    
    # Default Engines
    DEFAULT_TTS_ENGINE: str = "xtts"
    DEFAULT_TTS_LANGUAGE: str = "tr"
    DEFAULT_STT_ENGINE: str = "whisper"
    DEFAULT_WHISPER_MODEL: str = "medium"
    
    # Hardware / Processing
    USE_GPU: str = "auto"
    MAX_CPU_THREADS: int = 4
    PYTORCH_ENABLE_MPS_FALLBACK: int = 1
    
    # Logging
    LOG_LEVEL: str = "INFO"
    LOG_RETENTION_DAYS: int = 30
    
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore"
    )

# Instantiate global settings
settings = Settings()

# Ensure directories exist
for path in [settings.MODELS_DIR, settings.OUTPUTS_DIR, settings.PROFILES_DIR, settings.UPLOADS_DIR]:
    path.mkdir(parents=True, exist_ok=True)
