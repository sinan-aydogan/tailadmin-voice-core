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
    "sfx": {
        "label": "SFX",
        "label_tr": "Ses Efekti",
        "description": "Sound Effect & Foley models",
        "icon": "zap"
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
        "languages": ["tr", "en", "fr", "bg", "de", "it", "ru", "zh", "ko", "hi", "es", "pt", "pl", "ar", "ja", "hu", "cs"],
        "license": "Coqui Public Model License (CPML)",
        "homepage_url": "https://huggingface.co/coqui/XTTS-v2"
    },
    {
        "id": "bark",
        "engine": "bark",
        "name": "Suno Bark (Small)",
        "type": "tts",
        "repo_id": "suno/bark-small",
        "description": "Transformer-based text-to-audio model capable of highly realistic, multilingual speech.",
        "size_estimate_mb": 4500,
        "languages": ["en", "tr", "es", "fr", "de"],
        "license": "MIT",
        "homepage_url": "https://github.com/suno-ai/bark"
    },
    {
        "id": "tortoise",
        "engine": "tortoise",
        "name": "Tortoise TTS",
        "type": "tts",
        "repo_id": "Manmay/tortoise-tts",
        "description": "Strong multi-voice text-to-speech system. Best for English.",
        "size_estimate_mb": 4200,
        "languages": ["en"],
        "license": "Apache-2.0",
        "homepage_url": "https://github.com/neonbjb/tortoise-tts"
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
        "download_url": "https://huggingface.co/rhasspy/piper-voices/resolve/main/tr/tr_TR/dfki/medium/tr_TR-dfki-medium.onnx",
        "json_url": "https://huggingface.co/rhasspy/piper-voices/resolve/main/tr/tr_TR/dfki/medium/tr_TR-dfki-medium.onnx.json",
        "license": "MIT",
        "homepage_url": "https://github.com/rhasspy/piper"
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
        "download_url": "https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/lessac/medium/en_US-lessac-medium.onnx",
        "json_url": "https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/lessac/medium/en_US-lessac-medium.onnx.json",
        "license": "MIT",
        "homepage_url": "https://github.com/rhasspy/piper"
    },
    {
        "id": "freya-tts",
        "engine": "freya-tts",
        "name": "FreyaTTS (Açık Kaynak Türkçe - 183M DiT)",
        "type": "tts",
        "repo_id": "freyavoice/Freya-TTS",
        "description": "Tunga Bayrak (Freya Voice) tarafından geliştirilen açık kaynaklı Türkçe Diffusion Transformer modeli. Bilgisayarınıza indirilir ve yerel GPU/CPU üzerinde tamamen çevrimdışı çalışır.",
        "size_estimate_mb": 700,
        "languages": ["tr"],
        "is_cloud": False,
        "license": "Apache-2.0 / Açık Kaynak",
        "homepage_url": "https://huggingface.co/freyavoice/Freya-TTS"
    },
    {
        "id": "freya-adam",
        "engine": "freya-adam",
        "name": "Freya Voice - Adam (Bulut API Erkek)",
        "type": "tts",
        "repo_id": "freyavoice/adam",
        "description": "AudioRealismBench #1 (1418 Puan). San Francisco / YC Freya'nın dünyanın en gerçekçi erkek insansı ses modeli (Bulut API üzerinden çalışır, yerel indirme gerekmez).",
        "size_estimate_mb": 0,
        "languages": ["tr", "en"],
        "is_cloud": True,
        "cloud_provider": "freya",
        "license": "Freya Voice Bulut API",
        "homepage_url": "https://freyavoice.ai"
    },
    {
        "id": "freya-eve",
        "engine": "freya-eve",
        "name": "Freya Voice - Eve (Bulut API Kadın)",
        "type": "tts",
        "repo_id": "freyavoice/eve",
        "description": "AudioRealismBench #1 (1418 Puan). San Francisco / YC Freya'nın dünyanın en gerçekçi kadın insansı ses modeli (Bulut API üzerinden çalışır, yerel indirme gerekmez).",
        "size_estimate_mb": 0,
        "languages": ["tr", "en"],
        "is_cloud": True,
        "cloud_provider": "freya",
        "license": "Freya Voice Bulut API",
        "homepage_url": "https://freyavoice.ai"
    },
    # Patientdesk.ai Turkish Audio Models (Alania & Duyu & Antalia-1)
    {
        "id": "alania",
        "engine": "alania",
        "name": "Patientdesk Alania (Türkçe TTS • Bulut)",
        "type": "tts",
        "repo_id": "patientdesk/alania",
        "description": "Patientdesk.ai Türkçe metinden sese modeli. Doğal ve tutarlı tek bir ses üzerinden, düşük gecikmeli akış (streaming) desteğiyle telefon görüşmeleri ve sesli asistanlar için optimize edilmiştir. OpenAI API formatıyla tam uyumlu.",
        "size_estimate_mb": 0,
        "languages": ["tr"],
        "is_cloud": True,
        "cloud_provider": "patientdesk",
        "license": "Lansman Ücretsiz (1 Ay) / Ticari",
        "homepage_url": "https://speech.patientdesk.ai"
    },
    {
        "id": "duyu",
        "engine": "duyu",
        "name": "Patientdesk Duyu (Türkçe STT • Bulut)",
        "type": "stt",
        "repo_id": "patientdesk/duyu",
        "description": "Patientdesk.ai konuşmayı metne dönüştürme (STT) modeli. Kayıtlı sesleri ve canlı konuşmaları metne aktarır. FLEURS açık Türkçe veri setinde %4.71 kelime hata oranıyla Whisper Large-v3'ü (%5.04) geride bıraktı; telefon konuşmalarında %9.87 WER elde etti. OpenAI Whisper API formatıyla tam uyumlu.",
        "size_estimate_mb": 0,
        "languages": ["tr"],
        "is_cloud": True,
        "cloud_provider": "patientdesk",
        "license": "Lansman Ücretsiz (1 Ay) / Ticari",
        "homepage_url": "https://speech.patientdesk.ai"
    },
    {
        "id": "antalia-1",
        "engine": "antalia-1",
        "name": "Patientdesk Antalia-1 (Açık Kaynak Türkçe TTS)",
        "type": "tts",
        "repo_id": "cloud0day3/antalia-1",
        "description": "Patientdesk.ai araştırmacıları tarafından geliştirilen açık kaynaklı Türkçe metinden sese modeli (0.3B parametre, Flow-Matching). Seslendirme sanatçısının 5 saatlik stüdyo kayıtlarıyla (antalia-voice-corpus) tek ses üzerinden eğitilmiştir.",
        "size_estimate_mb": 1200,
        "languages": ["tr"],
        "is_cloud": False,
        "license": "OpenRAIL-M",
        "homepage_url": "https://huggingface.co/cloud0day3/antalia-1"
    },
    # OpenAI Cloud Audio Models
    {
        "id": "openai-tts-1",
        "engine": "openai-tts-1",
        "name": "OpenAI TTS (tts-1)",
        "type": "tts",
        "repo_id": "openai/tts-1",
        "description": "OpenAI standart TTS modeli. Alloy, Echo, Fable, Onyx, Nova, Shimmer sesleri ile akıcı ve insansı konuşma.",
        "size_estimate_mb": 0,
        "languages": ["multilingual", "tr", "en"],
        "is_cloud": True,
        "cloud_provider": "openai",
        "license": "OpenAI Ticari API",
        "homepage_url": "https://platform.openai.com/docs/guides/text-to-speech"
    },
    {
        "id": "openai-tts-hd",
        "engine": "openai-tts-hd",
        "name": "OpenAI TTS HD (tts-1-hd)",
        "type": "tts",
        "repo_id": "openai/tts-1-hd",
        "description": "OpenAI yüksek çözünürlüklü ve detaylı ses sentezleme modeli. Profesyonel anlatım ve podcast kalitesi.",
        "size_estimate_mb": 0,
        "languages": ["multilingual", "tr", "en"],
        "is_cloud": True,
        "cloud_provider": "openai",
        "license": "OpenAI Ticari API",
        "homepage_url": "https://platform.openai.com/docs/guides/text-to-speech"
    },
    {
        "id": "openai-whisper",
        "engine": "openai-whisper",
        "name": "OpenAI Whisper Cloud (whisper-1)",
        "type": "stt",
        "repo_id": "openai/whisper-1",
        "description": "OpenAI resmi bulut Whisper API servisi. 98+ dilde yüksek doğruluk ve otomatik noktalama.",
        "size_estimate_mb": 0,
        "languages": ["multilingual", "tr", "en"],
        "is_cloud": True,
        "cloud_provider": "openai",
        "license": "OpenAI Ticari API",
        "homepage_url": "https://platform.openai.com/docs/guides/speech-to-text"
    },
    # ElevenLabs Cloud Voice Models
    {
        "id": "elevenlabs-multilingual",
        "engine": "elevenlabs-multilingual",
        "name": "ElevenLabs Multilingual v2",
        "type": "tts",
        "repo_id": "elevenlabs/multilingual-v2",
        "description": "ElevenLabs'in 29+ dilde zengin duygu, fısıltı ve tonlama üreten endüstri lideri ses modeli.",
        "size_estimate_mb": 0,
        "languages": ["multilingual", "tr", "en"],
        "is_cloud": True,
        "cloud_provider": "elevenlabs",
        "license": "ElevenLabs Ticari API",
        "homepage_url": "https://elevenlabs.io"
    },
    {
        "id": "elevenlabs-flash",
        "engine": "elevenlabs-flash",
        "name": "ElevenLabs Flash v2.5",
        "type": "tts",
        "repo_id": "elevenlabs/flash-v2.5",
        "description": "Ultra düşük gecikmeli (~75ms) konuşma sentezi. Gerçek zamanlı interaktif sesli asistanlar için optimize.",
        "size_estimate_mb": 0,
        "languages": ["multilingual", "tr", "en"],
        "is_cloud": True,
        "cloud_provider": "elevenlabs",
        "license": "ElevenLabs Ticari API",
        "homepage_url": "https://elevenlabs.io"
    },
    # Google Cloud / Gemini Audio Models
    {
        "id": "google-cloud-tts",
        "engine": "google-cloud-tts",
        "name": "Google Cloud TTS (Journey & Neural2)",
        "type": "tts",
        "repo_id": "google/text-to-speech",
        "description": "Google Cloud Text-to-Speech API. Journey, Studio ve Neural2 yüksek kaliteli Türkçe ve global sesler.",
        "size_estimate_mb": 0,
        "languages": ["multilingual", "tr", "en"],
        "is_cloud": True,
        "cloud_provider": "google",
        "license": "Google Cloud Ticari API",
        "homepage_url": "https://cloud.google.com/text-to-speech"
    },
    {
        "id": "google-cloud-stt",
        "engine": "google-cloud-stt",
        "name": "Google Cloud STT (Chirp v2)",
        "type": "stt",
        "repo_id": "google/speech-to-text",
        "description": "Google Cloud Speech-to-Text API ve Chirp modeli ile yüksek doğrulukta kurumsal konuşma tanıma.",
        "size_estimate_mb": 0,
        "languages": ["multilingual", "tr", "en"],
        "is_cloud": True,
        "cloud_provider": "google",
        "license": "Google Cloud Ticari API",
        "homepage_url": "https://cloud.google.com/speech-to-text"
    },
    # Groq Cloud Whisper
    {
        "id": "groq-whisper",
        "engine": "groq-whisper",
        "name": "Groq Whisper Large v3 (LPU)",
        "type": "stt",
        "repo_id": "groq/whisper-large-v3",
        "description": "Groq LPU donanımında çalışan Whisper Large v3. Gerçek zamanlıdan 10 kat daha hızlı bulut deşifresi (<1sn).",
        "size_estimate_mb": 0,
        "languages": ["multilingual", "tr", "en"],
        "is_cloud": True,
        "cloud_provider": "groq",
        "license": "Groq Cloud API",
        "homepage_url": "https://groq.com"
    },
    {
        "id": "musicgen-small",
        "engine": "musicgen",
        "name": "MusicGen (Small)",
        "type": "music",
        "repo_id": "facebook/musicgen-small",
        "description": "AI music generation from text descriptions. Fast generation, good quality.",
        "size_estimate_mb": 5500,
        "languages": ["multilingual"],
        "license": "CC-BY-NC 4.0",
        "homepage_url": "https://huggingface.co/facebook/musicgen-small"
    },
    {
        "id": "musicgen-medium",
        "engine": "musicgen",
        "name": "MusicGen (Medium)",
        "type": "music",
        "repo_id": "facebook/musicgen-medium",
        "description": "AI music generation with better quality than small model.",
        "size_estimate_mb": 12000,
        "languages": ["multilingual"],
        "license": "CC-BY-NC 4.0",
        "homepage_url": "https://huggingface.co/facebook/musicgen-medium"
    },
    {
        "id": "musicgen-large",
        "engine": "musicgen",
        "name": "MusicGen (Large)",
        "type": "music",
        "repo_id": "facebook/musicgen-large",
        "description": "Best quality AI music generation. Slower but higher fidelity.",
        "size_estimate_mb": 19500,
        "languages": ["multilingual"],
        "license": "CC-BY-NC 4.0",
        "homepage_url": "https://huggingface.co/facebook/musicgen-large"
    },
    {
        "id": "musicgen-melody",
        "engine": "musicgen",
        "name": "MusicGen (Melody)",
        "type": "music",
        "repo_id": "facebook/musicgen-melody",
        "description": "Music generation conditioned on melodic input. Can use reference audio.",
        "size_estimate_mb": 8800,
        "languages": ["multilingual"],
        "license": "CC-BY-NC 4.0",
        "homepage_url": "https://huggingface.co/facebook/musicgen-melody"
    },
    {
        "id": "audiogen-medium",
        "engine": "audiogen",
        "name": "AudioGen Medium",
        "type": "sfx",
        "repo_id": "facebook/audiogen-medium",
        "description": "Meta AudioCraft — AI tabanlı yerel ses efekti üretimi (foley, sinematik sesler, ambiyans).",
        "size_estimate_mb": 1500,
        "languages": ["multilingual"],
        "license": "CC-BY-NC 4.0",
        "homepage_url": "https://huggingface.co/facebook/audiogen-medium"
    },
    {
        "id": "elevenlabs-sfx",
        "engine": "elevenlabs-sfx",
        "name": "ElevenLabs Sound Effects",
        "type": "sfx",
        "repo_id": "elevenlabs/sound-effects",
        "description": "ElevenLabs yapay zeka ses efekti ve foley motoru. Zengin ve sinematik ses efektleri üretir (Bulut API).",
        "size_estimate_mb": 0,
        "languages": ["multilingual"],
        "is_cloud": True,
        "cloud_provider": "elevenlabs",
        "license": "ElevenLabs Ticari API",
        "homepage_url": "https://elevenlabs.io/sound-effects"
    },
    {
        "id": "whisper-tiny",
        "engine": "whisper",
        "name": "Faster Whisper (Tiny)",
        "type": "stt",
        "repo_id": "Systran/faster-whisper-tiny",
        "description": "Extremely fast, low accuracy.",
        "size_estimate_mb": 150,
        "languages": ["multilingual"],
        "license": "MIT (Faster-Whisper)",
        "homepage_url": "https://github.com/SYSTRAN/faster-whisper"
    },
    {
        "id": "whisper-base",
        "engine": "whisper",
        "name": "Faster Whisper (Base)",
        "type": "stt",
        "repo_id": "Systran/faster-whisper-base",
        "description": "Fast, decent accuracy.",
        "size_estimate_mb": 250,
        "languages": ["multilingual"],
        "license": "MIT (Faster-Whisper)",
        "homepage_url": "https://github.com/SYSTRAN/faster-whisper"
    },
    {
        "id": "whisper-small",
        "engine": "whisper",
        "name": "Faster Whisper (Small)",
        "type": "stt",
        "repo_id": "Systran/faster-whisper-small",
        "description": "Good balance of speed and accuracy.",
        "size_estimate_mb": 1000,
        "languages": ["multilingual"],
        "license": "MIT (Faster-Whisper)",
        "homepage_url": "https://github.com/SYSTRAN/faster-whisper"
    },
    {
        "id": "whisper-medium",
        "engine": "whisper",
        "name": "Faster Whisper (Medium)",
        "type": "stt",
        "repo_id": "Systran/faster-whisper-medium",
        "description": "High accuracy, slower.",
        "size_estimate_mb": 3000,
        "languages": ["multilingual"],
        "license": "MIT (Faster-Whisper)",
        "homepage_url": "https://github.com/SYSTRAN/faster-whisper"
    },
    {
        "id": "whisper-large-v3",
        "engine": "whisper",
        "name": "Faster Whisper (Large V3)",
        "type": "stt",
        "repo_id": "Systran/faster-whisper-large-v3",
        "description": "Best accuracy, slowest.",
        "size_estimate_mb": 6000,
        "languages": ["multilingual"],
        "license": "MIT (Faster-Whisper)",
        "homepage_url": "https://github.com/SYSTRAN/faster-whisper"
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
        "capabilities": ["chat", "text-generation", "translation", "summarization", "code"],
        "license": "Apache-2.0",
        "homepage_url": "https://huggingface.co/Qwen/Qwen2.5-7B-Instruct"
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
        "capabilities": ["chat", "text-generation", "reasoning", "code", "tool-use"],
        "license": "Llama 3.1 Community License",
        "homepage_url": "https://huggingface.co/meta-llama/Llama-3.1-8B-Instruct"
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
        "capabilities": ["chat", "text-generation", "code", "reasoning"],
        "license": "Apache-2.0",
        "homepage_url": "https://huggingface.co/mistralai/Mistral-7B-Instruct-v0.3"
    },
    # Cloud LLM Models (Google Gemini, Groq, OpenAI, Claude, DeepSeek, OpenRouter)
    {
        "id": "gemini-2.0-flash",
        "engine": "gemini",
        "name": "Google Gemini 2.0 Flash",
        "type": "llm",
        "repo_id": "google/gemini-2.0-flash",
        "description": "Google'ın yeni nesil amiral gemisi hızlı modeli. 1M token bağlam penceresi, üstün Türkçe edebi kurgu ve yüksek hız. Bulut API üzerinden çalışır.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "gemini",
        "capabilities": ["chat", "storytelling", "reasoning", "multimodal", "fast"],
        "license": "Google Gemini API",
        "homepage_url": "https://ai.google.dev"
    },
    {
        "id": "gemini-1.5-pro",
        "engine": "gemini",
        "name": "Google Gemini 1.5 Pro",
        "type": "llm",
        "repo_id": "google/gemini-1.5-pro",
        "description": "Devasa 2 Milyon token bağlam hafızası ile uzun roman ve senaryolar için en güçlü akıl yürütme motoru. Bulut API üzerinden çalışır.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "gemini",
        "capabilities": ["deep-reasoning", "long-context", "storytelling"],
        "license": "Google Gemini API",
        "homepage_url": "https://ai.google.dev"
    },
    {
        "id": "gemini-1.5-flash",
        "engine": "gemini",
        "name": "Google Gemini 1.5 Flash",
        "type": "llm",
        "repo_id": "google/gemini-1.5-flash",
        "description": "Yüksek hacimli ve hızlı diyalog üretimleri için optimize edilmiş hafif Google modeli.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "gemini",
        "capabilities": ["fast", "chat", "summary"],
        "license": "Google Gemini API",
        "homepage_url": "https://ai.google.dev"
    },
    {
        "id": "groq-llama-3.3-70b",
        "engine": "groq",
        "name": "Groq Llama 3.3 70B (LPU)",
        "type": "llm",
        "repo_id": "groq/llama-3.3-70b-versatile",
        "description": "Groq LPU çipleri üzerinde saniyede 300+ token hızında çalışan Meta Llama 3.3 70B modeli. Anlık senaryo üretimi sağlar.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "groq",
        "capabilities": ["ultra-fast", "reasoning", "storytelling"],
        "license": "Groq LPU API",
        "homepage_url": "https://console.groq.com"
    },
    {
        "id": "groq-llama-3.1-8b",
        "engine": "groq",
        "name": "Groq Llama 3.1 8B (LPU)",
        "type": "llm",
        "repo_id": "groq/llama-3.1-8b-instant",
        "description": "Işık hızında diyalog ve prompt üretimi sunan 8B parametreli LPU modeli.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "groq",
        "capabilities": ["ultra-fast", "instant"],
        "license": "Groq LPU API",
        "homepage_url": "https://console.groq.com"
    },
    {
        "id": "openai-gpt-4o",
        "engine": "openai",
        "name": "OpenAI GPT-4o (Omni)",
        "type": "llm",
        "repo_id": "openai/gpt-4o",
        "description": "OpenAI'ın en gelişmiş çok modlu amiral gemisi modeli. Yüksek yaratıcılık, kusursuz edebi Türkçe ve duygu derinliği.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "openai",
        "capabilities": ["creative-writing", "reasoning", "vision", "chat"],
        "license": "OpenAI Ticari API",
        "homepage_url": "https://platform.openai.com"
    },
    {
        "id": "openai-gpt-4o-mini",
        "engine": "openai",
        "name": "OpenAI GPT-4o Mini",
        "type": "llm",
        "repo_id": "openai/gpt-4o-mini",
        "description": "GPT-4o kalitesinde metin kurgusu üreten ekonomik ve çevik bulut yapay zeka motoru.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "openai",
        "capabilities": ["fast", "chat", "storytelling"],
        "license": "OpenAI Ticari API",
        "homepage_url": "https://platform.openai.com"
    },
    {
        "id": "claude-3-5-sonnet",
        "engine": "anthropic",
        "name": "Claude 3.5 Sonnet",
        "type": "llm",
        "repo_id": "anthropic/claude-3-5-sonnet-20241022",
        "description": "Dünyanın en iyi yaratıcı yazarlık ve insan doğallığında diyalog kurabilen Anthropic modeli. Edebi masal ve drama kurguları için rakipsizdir.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "anthropic",
        "capabilities": ["creative-writing", "dialogue", "human-like"],
        "license": "Anthropic Ticari API",
        "homepage_url": "https://www.anthropic.com/claude"
    },
    {
        "id": "claude-3-5-haiku",
        "engine": "anthropic",
        "name": "Claude 3.5 Haiku",
        "type": "llm",
        "repo_id": "anthropic/claude-3-5-haiku-20241022",
        "description": "Anthropic'in en hızlı ve ekonomik modeli. Hızlı sahne tasarımı ve karakter replikleri için idealdir.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "anthropic",
        "capabilities": ["fast", "chat", "concise"],
        "license": "Anthropic Ticari API",
        "homepage_url": "https://www.anthropic.com/claude"
    },
    {
        "id": "deepseek-chat",
        "engine": "deepseek",
        "name": "DeepSeek Chat (V3)",
        "type": "llm",
        "repo_id": "deepseek/deepseek-chat",
        "description": "671 milyar parametreli MoE mimarisiyle açık kaynak öncüsü DeepSeek'in son sürüm sohbet motoru.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "zh", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "deepseek",
        "capabilities": ["reasoning", "storytelling", "cost-effective"],
        "license": "DeepSeek API",
        "homepage_url": "https://www.deepseek.com"
    },
    {
        "id": "deepseek-reasoner",
        "engine": "deepseek",
        "name": "DeepSeek Reasoner (R1)",
        "type": "llm",
        "repo_id": "deepseek/deepseek-reasoner",
        "description": "Mantıksal düşünce zinciri (CoT) kurabilen karmaşık senaryo ve kurgu çözümleme motoru.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "zh", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "deepseek",
        "capabilities": ["chain-of-thought", "deep-reasoning"],
        "license": "DeepSeek API",
        "homepage_url": "https://www.deepseek.com"
    },
    {
        "id": "openrouter-ai",
        "engine": "openrouter",
        "name": "OpenRouter Çoklu Model Havuzu",
        "type": "llm",
        "repo_id": "openrouter/multi-model",
        "description": "Tek bir API anahtarıyla yüzlerce yapay zeka modeline (Llama 3.3, Claude, Gemini, DeepSeek) anında erişim imkanı sunan küresel yönlendirici.",
        "size_estimate_mb": 0,
        "languages": ["tr", "en", "multilingual"],
        "is_cloud": True,
        "cloud_provider": "openrouter",
        "capabilities": ["multi-provider", "fallback", "all-models"],
        "license": "OpenRouter Global Router",
        "homepage_url": "https://openrouter.ai"
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
