"""
GPU / CPU device detection module for ML models.
"""
import os
import torch
import platform
import subprocess
from loguru import logger
from app.config import settings

# Explicit setting for Tortoise fallback as per project docs
if settings.PYTORCH_ENABLE_MPS_FALLBACK:
    os.environ["PYTORCH_ENABLE_MPS_FALLBACK"] = "1"

def detect_device(preferred: str = None) -> torch.device:
    """
    Detects the best available processing device.
    Prioritizes CUDA -> MPS (Apple Silicon) -> CPU.
    """
    pref = preferred if preferred else settings.USE_GPU
    
    if pref != "auto":
        device_name = pref.lower()
        logger.debug(f"Using explicitly requested device: {device_name}")
        return torch.device(device_name)
        
    if torch.cuda.is_available():
        logger.info(f"GPU Detected: NVIDIA CUDA ({torch.cuda.get_device_name(0)})")
        return torch.device("cuda")
        
    elif torch.backends.mps.is_available():
        logger.info("GPU Detected: Apple Silicon MPS (Metal)")
        return torch.device("mps")
        
    else:
        logger.info("No supported GPU found, using CPU")
        return torch.device("cpu")

def get_system_info() -> dict:
    """Returns overview of system hardware and current OS."""
    import psutil
    
    info = {
        "os": platform.system(),
        "arch": platform.machine(),
        "cpu_count_physical": psutil.cpu_count(logical=False),
        "cpu_count_logical": psutil.cpu_count(logical=True),
        "ram_total_gb": round(psutil.virtual_memory().total / (1024 ** 3), 2),
    }

    device = detect_device()
    info["active_device"] = str(device)
    
    if str(device) == "cuda":
        info["gpu_name"] = torch.cuda.get_device_name(0)
    elif str(device) == "mps":
        info["gpu_name"] = "Apple M-Series GPU"
        
    return info
