"""
LLM Engine Registry - Factory pattern for LLM engines.
"""
from typing import Dict, Type, Optional
from pathlib import Path
from loguru import logger

from app.llm.base import LLMEngine
from app.llm.engines.transformers_llm import TransformersLLMEngine
from app.config import settings


# Registry of available LLM engines
LLM_ENGINES: Dict[str, Type[LLMEngine]] = {
    "transformers": TransformersLLMEngine,
    # Future engines can be added here:
    # "llamacpp": LlamaCppEngine,
    # "vllm": VLLMEngine,
}

# Mapping of model IDs to their engine types and configurations
MODEL_ENGINE_MAP = {
    "qwen2.5-7b-instruct": {"engine": "transformers", "chat_template": "qwen"},
    "llama-3.1-8b-instruct": {"engine": "transformers", "chat_template": "llama"},
    "mistral-7b-instruct-v0.3": {"engine": "transformers", "chat_template": "mistral"},
}


class LLMRegistry:
    """Registry for managing LLM engines."""
    
    _instances: Dict[str, LLMEngine] = {}
    
    @classmethod
    def get_engine(
        cls,
        model_id: str,
        model_path: Optional[Path] = None,
        device: Optional[str] = None
    ) -> LLMEngine:
        """
        Get or create an LLM engine instance.
        
        Args:
            model_id: The model identifier (e.g., "qwen2.5-7b-instruct")
            model_path: Optional path to the model files
            device: Device to load the model on (cuda, mps, cpu)
            
        Returns:
            LLMEngine instance
        """
        # Check if we already have an instance
        if model_id in cls._instances:
            logger.debug(f"Reusing existing LLM engine for {model_id}")
            return cls._instances[model_id]
        
        # Get engine configuration
        engine_config = MODEL_ENGINE_MAP.get(model_id, {"engine": "transformers", "chat_template": "default"})
        engine_type = engine_config["engine"]
        chat_template = engine_config.get("chat_template", "default")
        
        # Get the engine class
        engine_class = LLM_ENGINES.get(engine_type)
        if not engine_class:
            raise ValueError(f"Unknown LLM engine type: {engine_type}")
        
        # Determine model path
        if model_path is None:
            model_path = Path(settings.MODELS_DIR) / "llm" / model_id
        
        # Create engine instance
        logger.info(f"Creating new LLM engine for {model_id} (type: {engine_type})")
        engine = engine_class(
            model_id=model_id,
            model_path=model_path,
            device=device,
            chat_template=chat_template
        )
        
        # Cache the instance
        cls._instances[model_id] = engine
        
        return engine
    
    @classmethod
    def unload_engine(cls, model_id: str) -> None:
        """Unload an engine from memory."""
        if model_id in cls._instances:
            logger.info(f"Unloading LLM engine: {model_id}")
            cls._instances[model_id].unload()
            del cls._instances[model_id]
    
    @classmethod
    def unload_all(cls) -> None:
        """Unload all engines from memory."""
        logger.info("Unloading all LLM engines")
        for model_id in list(cls._instances.keys()):
            cls.unload_engine(model_id)
    
    @classmethod
    def list_loaded_models(cls) -> list:
        """List currently loaded models."""
        return list(cls._instances.keys())


# Convenience function
def get_llm_engine(
    model_id: str,
    model_path: Optional[Path] = None,
    device: Optional[str] = None
) -> LLMEngine:
    """Get an LLM engine instance."""
    return LLMRegistry.get_engine(model_id, model_path, device)
