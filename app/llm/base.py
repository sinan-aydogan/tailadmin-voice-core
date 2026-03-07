"""
Base class for LLM engines.
"""
from abc import ABC, abstractmethod
from typing import Dict, List, Optional, Generator, AsyncGenerator
from pathlib import Path


class LLMEngine(ABC):
    """Abstract base class for LLM engines."""
    
    def __init__(self, model_id: str, model_path: Optional[Path] = None):
        self.model_id = model_id
        self.model_path = model_path
        self._model = None
        self._tokenizer = None
        self._is_loaded = False
    
    @abstractmethod
    def load_model(self) -> None:
        """Load the model into memory."""
        pass
    
    @abstractmethod
    def generate(
        self,
        prompt: str,
        system_prompt: Optional[str] = None,
        max_tokens: int = 512,
        temperature: float = 0.7,
        top_p: float = 0.9,
        top_k: int = 40,
        repetition_penalty: float = 1.1,
        stop_sequences: Optional[List[str]] = None,
        **kwargs
    ) -> str:
        """Generate text from a prompt."""
        pass
    
    @abstractmethod
    def generate_stream(
        self,
        prompt: str,
        system_prompt: Optional[str] = None,
        max_tokens: int = 512,
        temperature: float = 0.7,
        top_p: float = 0.9,
        top_k: int = 40,
        repetition_penalty: float = 1.1,
        stop_sequences: Optional[List[str]] = None,
        **kwargs
    ) -> Generator[str, None, None]:
        """Generate text from a prompt with streaming."""
        pass
    
    @abstractmethod
    async def generate_async(
        self,
        prompt: str,
        system_prompt: Optional[str] = None,
        max_tokens: int = 512,
        temperature: float = 0.7,
        top_p: float = 0.9,
        top_k: int = 40,
        repetition_penalty: float = 1.1,
        stop_sequences: Optional[List[str]] = None,
        **kwargs
    ) -> str:
        """Generate text asynchronously."""
        pass
    
    @abstractmethod
    async def generate_stream_async(
        self,
        prompt: str,
        system_prompt: Optional[str] = None,
        max_tokens: int = 512,
        temperature: float = 0.7,
        top_p: float = 0.9,
        top_k: int = 40,
        repetition_penalty: float = 1.1,
        stop_sequences: Optional[List[str]] = None,
        **kwargs
    ) -> AsyncGenerator[str, None]:
        """Generate text asynchronously with streaming."""
        pass
    
    @abstractmethod
    def is_available(self) -> bool:
        """Check if the model is available (downloaded)."""
        pass
    
    @property
    @abstractmethod
    def supports_chat(self) -> bool:
        """Check if the model supports chat/conversation format."""
        pass
    
    @property
    @abstractmethod
    def context_length(self) -> int:
        """Get the maximum context length for this model."""
        pass
    
    def unload(self) -> None:
        """Unload the model from memory to free resources."""
        self._model = None
        self._tokenizer = None
        self._is_loaded = False
    
    def format_chat_prompt(
        self,
        messages: List[Dict[str, str]],
        system_prompt: Optional[str] = None
    ) -> str:
        """Format messages into a chat prompt. Override in subclasses for model-specific formatting."""
        formatted = ""
        
        if system_prompt:
            formatted += f"System: {system_prompt}\n\n"
        
        for msg in messages:
            role = msg.get("role", "user")
            content = msg.get("content", "")
            formatted += f"{role.capitalize()}: {content}\n"
        
        formatted += "Assistant:"
        return formatted
