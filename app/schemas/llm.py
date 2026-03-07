"""
Pydantic schemas for LLM endpoints.
"""
from typing import List, Optional, Dict, Any
from pydantic import BaseModel, Field
from enum import Enum


class MessageRole(str, Enum):
    """Message roles for chat conversations."""
    USER = "user"
    ASSISTANT = "assistant"
    SYSTEM = "system"


class ChatMessage(BaseModel):
    """A single message in a chat conversation."""
    role: MessageRole = Field(..., description="The role of the message sender")
    content: str = Field(..., description="The content of the message", min_length=1)


class LLMGenerateRequest(BaseModel):
    """Request schema for text generation."""
    prompt: str = Field(..., description="The input prompt for generation", min_length=1)
    system_prompt: Optional[str] = Field(
        None,
        description="Optional system prompt to guide the model's behavior"
    )
    model_id: Optional[str] = Field(
        None,
        description="The LLM model to use. If not specified, uses the default model."
    )
    max_tokens: int = Field(
        default=512,
        ge=1,
        le=4096,
        description="Maximum number of tokens to generate"
    )
    temperature: float = Field(
        default=0.7,
        ge=0.0,
        le=2.0,
        description="Sampling temperature (higher = more creative)"
    )
    top_p: float = Field(
        default=0.9,
        ge=0.0,
        le=1.0,
        description="Nucleus sampling parameter"
    )
    top_k: int = Field(
        default=40,
        ge=1,
        le=100,
        description="Top-k sampling parameter"
    )
    repetition_penalty: float = Field(
        default=1.1,
        ge=1.0,
        le=2.0,
        description="Penalty for repeating tokens"
    )
    stop_sequences: Optional[List[str]] = Field(
        default=None,
        description="Optional list of sequences that will stop generation"
    )
    stream: bool = Field(
        default=False,
        description="Whether to stream the response"
    )


class LLMChatRequest(BaseModel):
    """Request schema for chat completion."""
    messages: List[ChatMessage] = Field(
        ...,
        description="List of messages in the conversation",
        min_length=1
    )
    model_id: Optional[str] = Field(
        None,
        description="The LLM model to use. If not specified, uses the default model."
    )
    max_tokens: int = Field(
        default=512,
        ge=1,
        le=4096,
        description="Maximum number of tokens to generate"
    )
    temperature: float = Field(
        default=0.7,
        ge=0.0,
        le=2.0,
        description="Sampling temperature (higher = more creative)"
    )
    top_p: float = Field(
        default=0.9,
        ge=0.0,
        le=1.0,
        description="Nucleus sampling parameter"
    )
    top_k: int = Field(
        default=40,
        ge=1,
        le=100,
        description="Top-k sampling parameter"
    )
    repetition_penalty: float = Field(
        default=1.1,
        ge=1.0,
        le=2.0,
        description="Penalty for repeating tokens"
    )
    stop_sequences: Optional[List[str]] = Field(
        default=None,
        description="Optional list of sequences that will stop generation"
    )
    stream: bool = Field(
        default=False,
        description="Whether to stream the response"
    )


class LLMGenerateResponse(BaseModel):
    """Response schema for text generation."""
    text: str = Field(..., description="The generated text")
    model_id: str = Field(..., description="The model used for generation")
    tokens_generated: int = Field(..., description="Number of tokens generated")
    finish_reason: str = Field(
        default="complete",
        description="Reason for finishing (complete, length, stop)"
    )


class LLMChatResponse(BaseModel):
    """Response schema for chat completion."""
    message: ChatMessage = Field(..., description="The assistant's response message")
    model_id: str = Field(..., description="The model used for generation")
    tokens_generated: int = Field(..., description="Number of tokens generated")
    finish_reason: str = Field(
        default="complete",
        description="Reason for finishing (complete, length, stop)"
    )


class LLMModelInfo(BaseModel):
    """Information about an LLM model."""
    id: str = Field(..., description="Model identifier")
    name: str = Field(..., description="Human-readable model name")
    description: str = Field(..., description="Model description")
    size_mb: int = Field(..., description="Model size in MB")
    context_length: int = Field(..., description="Maximum context length")
    languages: List[str] = Field(..., description="Supported languages")
    capabilities: List[str] = Field(..., description="Model capabilities")
    is_downloaded: bool = Field(..., description="Whether the model is downloaded")
    is_loaded: bool = Field(..., description="Whether the model is loaded in memory")


class LLMModelsResponse(BaseModel):
    """Response schema for listing available LLM models."""
    models: List[LLMModelInfo] = Field(..., description="List of available models")
    default_model: Optional[str] = Field(None, description="Default model ID")


class LLMUnloadRequest(BaseModel):
    """Request to unload an LLM model from memory."""
    model_id: str = Field(..., description="The model ID to unload")


class StreamChunk(BaseModel):
    """A single chunk in a streaming response."""
    text: str = Field(..., description="The text chunk")
    is_finished: bool = Field(default=False, description="Whether this is the final chunk")
