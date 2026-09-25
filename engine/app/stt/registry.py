"""
STT Model Registry and Factory.
"""
from typing import Dict, Callable
from loguru import logger

from app.stt.base import BaseSTT
from app.config import settings

class STTRegistry:
    """Registry to manage and instantiate requested STT engines."""
    
    _engines: Dict[str, Callable[[], BaseSTT]] = {
        "whisper": lambda: __import__("app.stt.whisper", fromlist=["WhisperEngine"]).WhisperEngine(),
        "faster-whisper": lambda: __import__("app.stt.whisper", fromlist=["WhisperEngine"]).WhisperEngine(),
        "openai-whisper": lambda: __import__("app.stt.openai_whisper", fromlist=["OpenAIWhisperEngine"]).OpenAIWhisperEngine("openai-whisper"),
        "groq-whisper": lambda: __import__("app.stt.groq_whisper", fromlist=["GroqWhisperEngine"]).GroqWhisperEngine("groq-whisper"),
        "google-cloud-stt": lambda: __import__("app.stt.google_stt", fromlist=["GoogleCloudSTTEngine"]).GoogleCloudSTTEngine("google-cloud-stt"),
        "duyu": lambda: __import__("app.stt.patientdesk_stt", fromlist=["PatientdeskSTTEngine"]).PatientdeskSTTEngine("duyu-1"),
        "duyu-1": lambda: __import__("app.stt.patientdesk_stt", fromlist=["PatientdeskSTTEngine"]).PatientdeskSTTEngine("duyu-1"),
        "patientdesk-duyu": lambda: __import__("app.stt.patientdesk_stt", fromlist=["PatientdeskSTTEngine"]).PatientdeskSTTEngine("duyu-1"),
        "patientdesk": lambda: __import__("app.stt.patientdesk_stt", fromlist=["PatientdeskSTTEngine"]).PatientdeskSTTEngine("duyu-1"),
        "patientdesk-stt": lambda: __import__("app.stt.patientdesk_stt", fromlist=["PatientdeskSTTEngine"]).PatientdeskSTTEngine("duyu-1"),
    }
    
    _instances: Dict[str, BaseSTT] = {}
    
    @classmethod
    def get_engine(cls, name: str = None) -> BaseSTT:
        """Get or create an instance of the requested STT engine."""
        engine_name = name or settings.DEFAULT_STT_ENGINE
        
        # If name is a specific whisper model id (e.g. whisper-medium, whisper-tiny), resolve to whisper
        if engine_name and engine_name.startswith("whisper-") and engine_name not in cls._engines:
            engine_name = "whisper"

        if engine_name not in cls._engines:
            logger.error(f"STT engine '{engine_name}' not found. Falling back to default.")
            engine_name = settings.DEFAULT_STT_ENGINE
            
        if engine_name not in cls._instances:
            engine_factory = cls._engines[engine_name]
            logger.info(f"Initializing STT Engine: {engine_name}")
            cls._instances[engine_name] = engine_factory()
            
        return cls._instances[engine_name]

# Global registry instance/helper
def get_stt_engine(name: str = None) -> BaseSTT:
    return STTRegistry.get_engine(name)
