"""
LLM Router - Endpoints for text generation and chat completion.
"""
from typing import Optional
from fastapi import APIRouter, Depends, HTTPException, status
from fastapi.responses import StreamingResponse
from sqlalchemy.orm import Session
from loguru import logger

from app.database import get_db
from app.auth.dependencies import get_current_user
from app.models.user import User
from app.llm.registry import LLMRegistry, get_llm_engine
from app.downloader.model_registry import get_llm_models
from app.schemas.llm import (
    LLMGenerateRequest,
    LLMChatRequest,
    LLMGenerateResponse,
    LLMChatResponse,
    LLMModelsResponse,
    LLMModelInfo,
    LLMUnloadRequest,
    StreamChunk,
    ChatMessage,
    MessageRole
)

router = APIRouter(prefix="/llm", tags=["llm"])

# Default LLM model
DEFAULT_LLM_MODEL = "qwen2.5-7b-instruct"


@router.get("/models", response_model=LLMModelsResponse)
async def list_llm_models(
    current_user: User = Depends(get_current_user)
):
    """List all available LLM models and their status."""
    models = get_llm_models()
    loaded_models = LLMRegistry.list_loaded_models()
    
    model_infos = []
    for model in models:
        try:
            engine = get_llm_engine(model["id"])
            model_infos.append(LLMModelInfo(
                id=model["id"],
                name=model["name"],
                description=model["description"],
                size_mb=model["size_estimate_mb"],
                context_length=model.get("context_length", 4096),
                languages=model.get("languages", ["en"]),
                capabilities=model.get("capabilities", ["text-generation"]),
                is_downloaded=engine.is_available(),
                is_loaded=model["id"] in loaded_models
            ))
        except Exception as e:
            logger.error(f"Error checking model {model['id']}: {e}")
            model_infos.append(LLMModelInfo(
                id=model["id"],
                name=model["name"],
                description=model["description"],
                size_mb=model["size_estimate_mb"],
                context_length=model.get("context_length", 4096),
                languages=model.get("languages", ["en"]),
                capabilities=model.get("capabilities", ["text-generation"]),
                is_downloaded=False,
                is_loaded=False
            ))
    
    return LLMModelsResponse(
        models=model_infos,
        default_model=DEFAULT_LLM_MODEL
    )


@router.post("/generate", response_model=LLMGenerateResponse)
async def generate_text(
    request: LLMGenerateRequest,
    current_user: User = Depends(get_current_user)
):
    """
    Generate text from a prompt.
    
    This endpoint takes a prompt and generates text using the specified LLM model.
    """
    model_id = request.model_id or DEFAULT_LLM_MODEL
    
    try:
        engine = get_llm_engine(model_id)
        
        if not engine.is_available():
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Model {model_id} is not downloaded. Please download it first."
            )
        
        logger.info(f"Generating text with model {model_id}")
        
        # Generate text
        generated_text = await engine.generate_async(
            prompt=request.prompt,
            system_prompt=request.system_prompt,
            max_tokens=request.max_tokens,
            temperature=request.temperature,
            top_p=request.top_p,
            top_k=request.top_k,
            repetition_penalty=request.repetition_penalty,
            stop_sequences=request.stop_sequences
        )
        
        # Estimate tokens (rough approximation)
        tokens_generated = len(generated_text.split())
        
        return LLMGenerateResponse(
            text=generated_text,
            model_id=model_id,
            tokens_generated=tokens_generated,
            finish_reason="complete"
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Text generation failed: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Generation failed: {str(e)}"
        )


@router.post("/generate/stream")
async def generate_text_stream(
    request: LLMGenerateRequest,
    current_user: User = Depends(get_current_user)
):
    """
    Generate text from a prompt with streaming response.
    
    Streams the generated text token by token using Server-Sent Events (SSE).
    """
    model_id = request.model_id or DEFAULT_LLM_MODEL
    
    try:
        engine = get_llm_engine(model_id)
        
        if not engine.is_available():
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Model {model_id} is not downloaded. Please download it first."
            )
        
        logger.info(f"Streaming text generation with model {model_id}")
        
        async def stream_generator():
            async for text_chunk in engine.generate_stream_async(
                prompt=request.prompt,
                system_prompt=request.system_prompt,
                max_tokens=request.max_tokens,
                temperature=request.temperature,
                top_p=request.top_p,
                top_k=request.top_k,
                repetition_penalty=request.repetition_penalty,
                stop_sequences=request.stop_sequences
            ):
                chunk = StreamChunk(text=text_chunk, is_finished=False)
                yield f"data: {chunk.model_dump_json()}\n\n"
            
            # Send final chunk
            final_chunk = StreamChunk(text="", is_finished=True)
            yield f"data: {final_chunk.model_dump_json()}\n\n"
        
        return StreamingResponse(
            stream_generator(),
            media_type="text/event-stream",
            headers={
                "Cache-Control": "no-cache",
                "Connection": "keep-alive",
            }
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Streaming generation failed: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Streaming generation failed: {str(e)}"
        )


@router.post("/chat", response_model=LLMChatResponse)
async def chat_completion(
    request: LLMChatRequest,
    current_user: User = Depends(get_current_user)
):
    """
    Chat completion endpoint.
    
    Takes a list of messages and generates a response in a conversational format.
    """
    model_id = request.model_id or DEFAULT_LLM_MODEL
    
    try:
        engine = get_llm_engine(model_id)
        
        if not engine.is_available():
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Model {model_id} is not downloaded. Please download it first."
            )
        
        logger.info(f"Chat completion with model {model_id}")
        
        # Extract system prompt if present
        system_prompt = None
        messages = []
        for msg in request.messages:
            if msg.role == MessageRole.SYSTEM:
                system_prompt = msg.content
            else:
                messages.append({"role": msg.role.value, "content": msg.content})
        
        # Format messages for the model
        prompt = engine.format_chat_prompt(messages, system_prompt)
        
        # Generate response
        generated_text = await engine.generate_async(
            prompt=prompt,
            max_tokens=request.max_tokens,
            temperature=request.temperature,
            top_p=request.top_p,
            top_k=request.top_k,
            repetition_penalty=request.repetition_penalty,
            stop_sequences=request.stop_sequences or ["User:", "Human:"]
        )
        
        # Estimate tokens
        tokens_generated = len(generated_text.split())
        
        return LLMChatResponse(
            message=ChatMessage(role=MessageRole.ASSISTANT, content=generated_text),
            model_id=model_id,
            tokens_generated=tokens_generated,
            finish_reason="complete"
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Chat completion failed: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Chat completion failed: {str(e)}"
        )


@router.post("/chat/stream")
async def chat_completion_stream(
    request: LLMChatRequest,
    current_user: User = Depends(get_current_user)
):
    """
    Chat completion with streaming response.
    
    Streams the assistant's response token by token.
    """
    model_id = request.model_id or DEFAULT_LLM_MODEL
    
    try:
        engine = get_llm_engine(model_id)
        
        if not engine.is_available():
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Model {model_id} is not downloaded. Please download it first."
            )
        
        logger.info(f"Streaming chat completion with model {model_id}")
        
        # Extract system prompt if present
        system_prompt = None
        messages = []
        for msg in request.messages:
            if msg.role == MessageRole.SYSTEM:
                system_prompt = msg.content
            else:
                messages.append({"role": msg.role.value, "content": msg.content})
        
        # Format messages for the model
        prompt = engine.format_chat_prompt(messages, system_prompt)
        
        async def stream_generator():
            async for text_chunk in engine.generate_stream_async(
                prompt=prompt,
                max_tokens=request.max_tokens,
                temperature=request.temperature,
                top_p=request.top_p,
                top_k=request.top_k,
                repetition_penalty=request.repetition_penalty,
                stop_sequences=request.stop_sequences or ["User:", "Human:"]
            ):
                chunk = StreamChunk(text=text_chunk, is_finished=False)
                yield f"data: {chunk.model_dump_json()}\n\n"
            
            # Send final chunk
            final_chunk = StreamChunk(text="", is_finished=True)
            yield f"data: {final_chunk.model_dump_json()}\n\n"
        
        return StreamingResponse(
            stream_generator(),
            media_type="text/event-stream",
            headers={
                "Cache-Control": "no-cache",
                "Connection": "keep-alive",
            }
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Streaming chat completion failed: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Streaming chat completion failed: {str(e)}"
        )


@router.post("/unload")
async def unload_model(
    request: LLMUnloadRequest,
    current_user: User = Depends(get_current_user)
):
    """
    Unload an LLM model from memory to free up resources.
    
    This is useful when switching between models or when memory is needed for other tasks.
    """
    try:
        LLMRegistry.unload_engine(request.model_id)
        return {"status": "success", "message": f"Model {request.model_id} unloaded successfully"}
    except Exception as e:
        logger.error(f"Failed to unload model {request.model_id}: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to unload model: {str(e)}"
        )


@router.post("/unload-all")
async def unload_all_models(
    current_user: User = Depends(get_current_user)
):
    """Unload all LLM models from memory."""
    try:
        LLMRegistry.unload_all()
        return {"status": "success", "message": "All LLM models unloaded successfully"}
    except Exception as e:
        logger.error(f"Failed to unload all models: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to unload all models: {str(e)}"
        )
