"""
Registry of available models for download.
"""
from typing import List, Dict

# Hardcoded list of supported models for the MVP
AVAILABLE_MODELS = [
    {
        "id": "xtts-v2",
        "engine": "xtts",
        "name": "Coqui XTTS v2",
        "type": "tts",
        "repo_id": "coqui/XTTS-v2",
        "description": "High quality multi-lingual text-to-speech with voice cloning.",
        "size_estimate_mb": 2500,
        "languages": ["en", "tr", "es", "fr", "de", "it", "pt", "pl", "ar", "ru", "zh", "ja", "ko", "hu", "cs"]
    },
    {
        "id": "bark",
        "engine": "bark",
        "name": "Suno Bark (Small)",
        "type": "tts",
        "repo_id": "suno/bark-small",
        "description": "Transformer-based text-to-audio model capable of highly realistic, multilingual speech.",
        "size_estimate_mb": 4500,
        "languages": ["en", "tr", "es", "fr", "de"] # Technically many, but focusing on core
    },
    {
        "id": "tortoise",
        "engine": "tortoise",
        "name": "Tortoise TTS",
        "type": "tts",
        "repo_id": "Manmay/tortoise-tts",
        "description": "Strong multi-voice text-to-speech system. Best for English.",
        "size_estimate_mb": 3000,
        "languages": ["en"]
    },
    {
        "id": "musicgen",
        "engine": "musicgen",
        "name": "MusicGen (Small)",
        "type": "music",
        "repo_id": "facebook/musicgen-small",
        "description": "AI music generation from text descriptions. Creates instrumental music.",
        "size_estimate_mb": 1500,
        "languages": ["multilingual"]
    },
    {
        "id": "whisper-tiny",
        "engine": "whisper",
        "name": "Faster Whisper (Tiny)",
        "type": "stt",
        "repo_id": "Systran/faster-whisper-tiny",
        "description": "Extremely fast, low accuracy.",
        "size_estimate_mb": 150,
        "languages": ["multilingual"]
    },
    {
        "id": "whisper-base",
        "engine": "whisper",
        "name": "Faster Whisper (Base)",
        "type": "stt",
        "repo_id": "Systran/faster-whisper-base",
        "description": "Fast, decent accuracy.",
        "size_estimate_mb": 250,
        "languages": ["multilingual"]
    },
    {
        "id": "whisper-small",
        "engine": "whisper",
        "name": "Faster Whisper (Small)",
        "type": "stt",
        "repo_id": "Systran/faster-whisper-small",
        "description": "Good balance of speed and accuracy.",
        "size_estimate_mb": 1000,
        "languages": ["multilingual"]
    },
    {
        "id": "whisper-medium",
        "engine": "whisper",
        "name": "Faster Whisper (Medium)",
        "type": "stt",
        "repo_id": "Systran/faster-whisper-medium",
        "description": "High accuracy, slower.",
        "size_estimate_mb": 3000,
        "languages": ["multilingual"]
    },
    {
        "id": "whisper-large-v3",
        "engine": "whisper",
        "name": "Faster Whisper (Large V3)",
        "type": "stt",
        "repo_id": "Systran/faster-whisper-large-v3",
        "description": "Best accuracy, slowest.",
        "size_estimate_mb": 6000,
        "languages": ["multilingual"]
    }
]

def get_available_models() -> List[Dict]:
    return AVAILABLE_MODELS

def get_model_info(model_id: str) -> Dict:
    for model in AVAILABLE_MODELS:
        if model["id"] == model_id:
            return model
    return None
