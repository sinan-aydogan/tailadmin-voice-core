"""
Registry of available models for download.
"""
from typing import List, Dict

# Model categories for tabbed UI
MODEL_CATEGORIES = {
    "tts": {
        "label": "TTS",
        "label_tr": "Metin Okuma",
        "description": "Text-to-Speech models",
        "icon": "volume-2"
    },
    "stt": {
        "label": "STT", 
        "label_tr": "Sesten Metne",
        "description": "Speech-to-Text models",
        "icon": "mic"
    },
    "music": {
        "label": "MUSIC",
        "label_tr": "Müzik",
        "description": "AI Music Generation models",
        "icon": "music"
    },
    "llm": {
        "label": "LLM",
        "label_tr": "Dil Modeli",
        "description": "Large Language Models for text generation",
        "icon": "brain"
    }
}

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
        "id": "piper-tr",
        "engine": "piper-tr",
        "name": "Piper TTS (Turkish)",
        "type": "tts",
        "repo_id": "rhasspy/piper-voices",
        "description": "Fast, lightweight TTS optimized for Turkish. Great for real-time applications.",
        "size_estimate_mb": 70,
        "languages": ["tr"],
        "download_url": "https://huggingface.co/rhasspy/piper-voices/resolve/main/tr/tr_TR-dfki-medium/tr_TR-dfki-medium.onnx",
        "json_url": "https://huggingface.co/rhasspy/piper-voices/resolve/main/tr/tr_TR-dfki-medium/tr_TR-dfki-medium.onnx.json"
    },
    {
        "id": "piper-en",
        "engine": "piper-en",
        "name": "Piper TTS (English)",
        "type": "tts",
        "repo_id": "rhasspy/piper-voices",
        "description": "Fast, lightweight TTS optimized for English. Great for real-time applications.",
        "size_estimate_mb": 70,
        "languages": ["en"],
        "download_url": "https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US-lessac-medium/en_US-lessac-medium.onnx",
        "json_url": "https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US-lessac-medium/en_US-lessac-medium.onnx.json"
    },
    {
        "id": "musicgen-small",
        "engine": "musicgen",
        "name": "MusicGen (Small)",
        "type": "music",
        "repo_id": "facebook/musicgen-small",
        "description": "AI music generation from text descriptions. Fast generation, good quality.",
        "size_estimate_mb": 1500,
        "languages": ["multilingual"]
    },
    {
        "id": "musicgen-medium",
        "engine": "musicgen",
        "name": "MusicGen (Medium)",
        "type": "music",
        "repo_id": "facebook/musicgen-medium",
        "description": "AI music generation with better quality than small model.",
        "size_estimate_mb": 3500,
        "languages": ["multilingual"]
    },
    {
        "id": "musicgen-large",
        "engine": "musicgen",
        "name": "MusicGen (Large)",
        "type": "music",
        "repo_id": "facebook/musicgen-large",
        "description": "Best quality AI music generation. Slower but higher fidelity.",
        "size_estimate_mb": 8000,
        "languages": ["multilingual"]
    },
    {
        "id": "musicgen-melody",
        "engine": "musicgen",
        "name": "MusicGen (Melody)",
        "type": "music",
        "repo_id": "facebook/musicgen-melody",
        "description": "Music generation conditioned on melodic input. Can use reference audio.",
        "size_estimate_mb": 3500,
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
    },
    # LLM Models - Optimized for MacBook M4 Pro 24GB RAM
    {
        "id": "qwen2.5-7b-instruct",
        "engine": "llm",
        "name": "Qwen 2.5 7B Instruct",
        "type": "llm",
        "repo_id": "Qwen/Qwen2.5-7B-Instruct",
        "description": "Alibaba's powerful multilingual LLM. Excellent for Turkish, English, and many other languages. Supports long context up to 128K tokens.",
        "size_estimate_mb": 15000,
        "languages": ["tr", "en", "zh", "ar", "de", "es", "fr", "it", "ja", "ko", "pt", "ru", "vi"],
        "context_length": 32768,
        "quantization": "Q4_K_M",
        "ram_required_gb": 8,
        "capabilities": ["chat", "text-generation", "translation", "summarization", "code"]
    },
    {
        "id": "llama-3.1-8b-instruct",
        "engine": "llm",
        "name": "Llama 3.1 8B Instruct",
        "type": "llm",
        "repo_id": "unsloth/Llama-3.1-8B-Instruct",
        "description": "Meta's Llama 3.1 model (via Unsloth). Strong reasoning and multilingual capabilities. Great for conversation and content creation.",
        "size_estimate_mb": 16000,
        "languages": ["en", "de", "fr", "it", "pt", "es", "tr", "ar", "hi", "th", "vi"],
        "context_length": 128000,
        "quantization": "Q4_K_M",
        "ram_required_gb": 9,
        "capabilities": ["chat", "text-generation", "reasoning", "code", "tool-use"]
    },
    {
        "id": "mistral-7b-instruct-v0.3",
        "engine": "llm",
        "name": "Mistral 7B Instruct v0.3",
        "type": "llm",
        "repo_id": "mistralai/Mistral-7B-Instruct-v0.3",
        "description": "Efficient and fast French-made LLM. Excellent performance for its size. Great for chat and creative writing.",
        "size_estimate_mb": 15000,
        "languages": ["en", "fr", "de", "es", "it", "pt", "tr", "ar", "zh", "ja", "ko"],
        "context_length": 32768,
        "quantization": "Q4_K_M",
        "ram_required_gb": 8,
        "capabilities": ["chat", "text-generation", "code", "reasoning"]
    }
]

def get_available_models() -> List[Dict]:
    """Get all available models."""
    return AVAILABLE_MODELS

def get_models_by_type(model_type: str) -> List[Dict]:
    """Get models filtered by type (tts, stt, music, llm)."""
    return [m for m in AVAILABLE_MODELS if m.get("type") == model_type]

def get_model_info(model_id: str) -> Dict:
    """Get detailed info for a specific model."""
    for model in AVAILABLE_MODELS:
        if model["id"] == model_id:
            return model
    return None

def get_model_categories() -> Dict:
    """Get model category definitions for UI."""
    return MODEL_CATEGORIES

def get_llm_models() -> List[Dict]:
    """Get only LLM models."""
    return get_models_by_type("llm")
