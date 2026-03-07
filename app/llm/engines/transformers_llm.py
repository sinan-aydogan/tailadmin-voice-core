"""
Transformers-based LLM Engine implementation.
Supports models like Qwen, Llama, Mistral via HuggingFace Transformers.
"""
import asyncio
import torch
from typing import Dict, List, Optional, Generator, AsyncGenerator
from pathlib import Path
from loguru import logger

from app.llm.base import LLMEngine
from app.core.device import detect_device


class TransformersLLMEngine(LLMEngine):
    """LLM Engine using HuggingFace Transformers."""
    
    # Chat templates for different model families
    CHAT_TEMPLATES = {
        "qwen": {
            "system": "<|im_start|>system\n{content}<|im_end|>\n",
            "user": "<|im_start|>user\n{content}<|im_end|>\n",
            "assistant": "<|im_start|>assistant\n{content}<|im_end|>\n",
            "assistant_start": "<|im_start|>assistant\n"
        },
        "llama": {
            "system": "<|begin_of_text|><|start_header_id|>system<|end_header_id|>\n\n{content}<|eot_id|>",
            "user": "<|start_header_id|>user<|end_header_id|>\n\n{content}<|eot_id|>",
            "assistant": "<|start_header_id|>assistant<|end_header_id|>\n\n{content}<|eot_id|>",
            "assistant_start": "<|start_header_id|>assistant<|end_header_id|>\n\n"
        },
        "mistral": {
            "system": "",  # Mistral doesn't use system prompts in the same way
            "user": "[INST] {content} [/INST]",
            "assistant": " {content}</s>",
            "assistant_start": ""
        },
        "default": {
            "system": "System: {content}\n\n",
            "user": "User: {content}\n",
            "assistant": "Assistant: {content}\n",
            "assistant_start": "Assistant:"
        }
    }
    
    # Context lengths for known models
    CONTEXT_LENGTHS = {
        "qwen2.5-7b-instruct": 32768,
        "llama-3.1-8b-instruct": 128000,
        "mistral-7b-instruct-v0.3": 32768,
    }
    
    def __init__(
        self,
        model_id: str,
        model_path: Optional[Path] = None,
        device: Optional[str] = None,
        chat_template: str = "default",
        load_in_8bit: bool = False,
        load_in_4bit: bool = True
    ):
        super().__init__(model_id, model_path)
        self.device = device or str(detect_device())
        self.chat_template_name = chat_template
        self.chat_template = self.CHAT_TEMPLATES.get(chat_template, self.CHAT_TEMPLATES["default"])
        self.load_in_8bit = load_in_8bit
        self.load_in_4bit = load_in_4bit
        self._context_length = self.CONTEXT_LENGTHS.get(model_id, 4096)
    
    def load_model(self) -> None:
        """Load the model and tokenizer."""
        if self._is_loaded:
            return
        
        try:
            from transformers import AutoModelForCausalLM, AutoTokenizer, BitsAndBytesConfig
            
            logger.info(f"Loading LLM model: {self.model_id}")
            logger.info(f"Model path: {self.model_path}")
            logger.info(f"Device: {self.device}")
            
            # Check if model exists locally
            if not self.model_path or not self.model_path.exists():
                raise FileNotFoundError(f"Model not found at {self.model_path}. Please download it first.")
            
            # Configure quantization for memory efficiency
            quantization_config = None
            if self.load_in_4bit and torch.cuda.is_available():
                quantization_config = BitsAndBytesConfig(
                    load_in_4bit=True,
                    bnb_4bit_compute_dtype=torch.float16,
                    bnb_4bit_quant_type="nf4",
                    bnb_4bit_use_double_quant=True,
                )
            elif self.load_in_8bit and torch.cuda.is_available():
                quantization_config = BitsAndBytesConfig(load_in_8bit=True)
            
            # Load tokenizer
            self._tokenizer = AutoTokenizer.from_pretrained(
                self.model_path,
                trust_remote_code=True,
                local_files_only=True
            )
            
            # Set padding token if not present
            if self._tokenizer.pad_token is None:
                self._tokenizer.pad_token = self._tokenizer.eos_token
            
            # Load model
            model_kwargs = {
                "local_files_only": True,
                "trust_remote_code": True,
                "torch_dtype": torch.float16 if self.device in ["cuda", "mps"] else torch.float32,
            }
            
            if quantization_config:
                model_kwargs["quantization_config"] = quantization_config
            elif self.device == "cpu":
                model_kwargs["low_cpu_mem_usage"] = True
            
            self._model = AutoModelForCausalLM.from_pretrained(
                self.model_path,
                **model_kwargs
            )
            
            # Move to device if not using quantization
            if quantization_config is None and self.device != "cpu":
                self._model = self._model.to(self.device)
            
            self._model.eval()
            self._is_loaded = True
            
            logger.success(f"LLM model {self.model_id} loaded successfully")
            
        except Exception as e:
            logger.error(f"Failed to load LLM model {self.model_id}: {e}")
            raise
    
    def is_available(self) -> bool:
        """Check if the model files exist."""
        if not self.model_path:
            return False
        return self.model_path.exists() and any(self.model_path.iterdir())
    
    @property
    def supports_chat(self) -> bool:
        """All supported models support chat."""
        return True
    
    @property
    def context_length(self) -> int:
        """Get the maximum context length."""
        return self._context_length
    
    def format_chat_prompt(
        self,
        messages: List[Dict[str, str]],
        system_prompt: Optional[str] = None
    ) -> str:
        """Format messages according to the model's chat template."""
        formatted = ""
        
        # Add system prompt if provided
        if system_prompt and self.chat_template["system"]:
            formatted += self.chat_template["system"].format(content=system_prompt)
        
        # Add messages
        for msg in messages:
            role = msg.get("role", "user")
            content = msg.get("content", "")
            
            if role == "user":
                formatted += self.chat_template["user"].format(content=content)
            elif role == "assistant":
                formatted += self.chat_template["assistant"].format(content=content)
        
        # Add assistant start for generation
        formatted += self.chat_template.get("assistant_start", "Assistant:")
        
        return formatted
    
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
        if not self._is_loaded:
            self.load_model()
        
        try:
            # Format prompt with system prompt if provided
            if system_prompt:
                full_prompt = f"{system_prompt}\n\nUser: {prompt}\n\nAssistant:"
            else:
                full_prompt = prompt
            
            # Tokenize
            inputs = self._tokenizer(full_prompt, return_tensors="pt", padding=True)
            inputs = {k: v.to(self._model.device) for k, v in inputs.items()}
            
            # Generate
            with torch.no_grad():
                outputs = self._model.generate(
                    **inputs,
                    max_new_tokens=max_tokens,
                    temperature=temperature,
                    top_p=top_p,
                    top_k=top_k,
                    repetition_penalty=repetition_penalty,
                    do_sample=temperature > 0,
                    pad_token_id=self._tokenizer.pad_token_id,
                    eos_token_id=self._tokenizer.eos_token_id,
                    **kwargs
                )
            
            # Decode
            generated_text = self._tokenizer.decode(outputs[0], skip_special_tokens=True)
            
            # Extract only the generated part (after the prompt)
            if full_prompt in generated_text:
                generated_text = generated_text[len(full_prompt):].strip()
            
            # Apply stop sequences
            if stop_sequences:
                for stop_seq in stop_sequences:
                    if stop_seq in generated_text:
                        generated_text = generated_text[:generated_text.index(stop_seq)].strip()
                        break
            
            return generated_text
            
        except Exception as e:
            logger.error(f"Generation failed: {e}")
            raise
    
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
        """Generate text with streaming (simulated - yields full text for now)."""
        # Note: True streaming requires more complex implementation with TextIteratorStreamer
        # For now, we generate and yield word by word to simulate streaming
        full_text = self.generate(
            prompt=prompt,
            system_prompt=system_prompt,
            max_tokens=max_tokens,
            temperature=temperature,
            top_p=top_p,
            top_k=top_k,
            repetition_penalty=repetition_penalty,
            stop_sequences=stop_sequences,
            **kwargs
        )
        
        # Yield word by word to simulate streaming
        words = full_text.split()
        current_text = ""
        for word in words:
            current_text += word + " "
            yield current_text.strip()
    
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
        loop = asyncio.get_event_loop()
        return await loop.run_in_executor(
            None,
            lambda: self.generate(
                prompt=prompt,
                system_prompt=system_prompt,
                max_tokens=max_tokens,
                temperature=temperature,
                top_p=top_p,
                top_k=top_k,
                repetition_penalty=repetition_penalty,
                stop_sequences=stop_sequences,
                **kwargs
            )
        )
    
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
        loop = asyncio.get_event_loop()
        
        def sync_generate():
            return self.generate_stream(
                prompt=prompt,
                system_prompt=system_prompt,
                max_tokens=max_tokens,
                temperature=temperature,
                top_p=top_p,
                top_k=top_k,
                repetition_penalty=repetition_penalty,
                stop_sequences=stop_sequences,
                **kwargs
            )
        
        # Run sync generator in executor and yield results
        import concurrent.futures
        with concurrent.futures.ThreadPoolExecutor() as executor:
            future = executor.submit(sync_generate)
            generator = future.result()
            
            for text in generator:
                yield text
                await asyncio.sleep(0.01)  # Small delay for streaming effect
