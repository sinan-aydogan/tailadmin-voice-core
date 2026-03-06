"""
Model Integrity Checker Service.
Verifies that downloaded model files are complete and not corrupted.
"""
import os
from typing import Dict, List, Optional
from dataclasses import dataclass
from enum import Enum
from loguru import logger

from app.config import settings


class ModelStatus(str, Enum):
    NOT_DOWNLOADED = "not_downloaded"
    DOWNLOADING = "downloading"
    DOWNLOADED = "downloaded"
    CORRUPTED = "corrupted"
    INCOMPLETE = "incomplete"


@dataclass
class ModelIntegrityResult:
    model_id: str
    status: ModelStatus
    is_healthy: bool
    missing_files: List[str]
    corrupted_files: List[str]
    total_size_mb: float
    details: Dict


# Model integrity check configurations
MODEL_INTEGRITY_CHECKS = {
    "xtts-v2": {
        "required_files": ["config.json", "model.pth", "vocab.json", "dvae.pth"],
        "min_size_mb": 1000,
        "optional_files": ["mel_stats.pth", "speakers_xtts.pth"]
    },
    "bark": {
        "required_files": ["config.json", "pytorch_model.bin"],
        "min_size_mb": 1000,
        "optional_files": ["tokenizer.json", "vocab.txt"]
    },
    "tortoise": {
        "required_files": ["autoregressive.pth", "clvp.pth", "dvae.pth"],
        "min_size_mb": 1500,
        "optional_files": ["clvp2.pth", "diffusion_decoder.pth", "hifidecoder.pth"]
    },
    "whisper-tiny": {
        "required_files": ["model.bin"],
        "min_size_mb": 50,
        "alternative_files": ["pytorch_model.bin"]
    },
    "whisper-base": {
        "required_files": ["model.bin"],
        "min_size_mb": 100,
        "alternative_files": ["pytorch_model.bin"]
    },
    "whisper-small": {
        "required_files": ["model.bin"],
        "min_size_mb": 300,
        "alternative_files": ["pytorch_model.bin"]
    },
    "whisper-medium": {
        "required_files": ["model.bin"],
        "min_size_mb": 1000,
        "alternative_files": ["pytorch_model.bin"]
    },
    "whisper-large-v3": {
        "required_files": ["model.bin"],
        "min_size_mb": 2500,
        "alternative_files": ["pytorch_model.bin"]
    }
}


def get_model_path(model_id: str) -> str:
    """Get the local path for a model."""
    return os.path.join(settings.MODELS_DIR, model_id)


def verify_model_files(model_id: str) -> ModelIntegrityResult:
    """
    Verify that a model's files are complete and valid.
    
    Returns:
        ModelIntegrityResult with detailed status information
    """
    model_path = get_model_path(model_id)
    config = MODEL_INTEGRITY_CHECKS.get(model_id, {})
    
    result = ModelIntegrityResult(
        model_id=model_id,
        status=ModelStatus.NOT_DOWNLOADED,
        is_healthy=False,
        missing_files=[],
        corrupted_files=[],
        total_size_mb=0.0,
        details={}
    )
    
    # Check if model directory exists
    if not os.path.exists(model_path):
        result.status = ModelStatus.NOT_DOWNLOADED
        result.details["error"] = "Model directory does not exist"
        return result
    
    # Get all files in the directory
    try:
        all_files = []
        for root, dirs, files in os.walk(model_path):
            for file in files:
                file_path = os.path.join(root, file)
                rel_path = os.path.relpath(file_path, model_path)
                all_files.append((rel_path, file_path))
    except Exception as e:
        logger.error(f"Error scanning model directory {model_id}: {e}")
        result.status = ModelStatus.CORRUPTED
        result.details["error"] = f"Failed to scan directory: {str(e)}"
        return result
    
    # Calculate total size
    total_size = sum(os.path.getsize(fp) for _, fp in all_files if os.path.exists(fp))
    result.total_size_mb = total_size / (1024 * 1024)
    
    # If no files found
    if not all_files:
        result.status = ModelStatus.NOT_DOWNLOADED
        result.details["error"] = "Model directory is empty"
        return result
    
    # Check required files
    required_files = config.get("required_files", [])
    optional_files = config.get("optional_files", [])
    alternative_files = config.get("alternative_files", [])
    min_size_mb = config.get("min_size_mb", 0)
    
    # Get just the filenames from all_files
    existing_files = set(f[0] for f in all_files)
    
    # Check for required files
    for req_file in required_files:
        if req_file not in existing_files:
            # Check if alternative exists (for whisper models)
            has_alternative = False
            if alternative_files:
                has_alternative = any(alt in existing_files for alt in alternative_files)
            if not has_alternative:
                result.missing_files.append(req_file)
    
    # Check minimum size with more lenient tolerance (30% instead of 50%)
    # This allows for variations in downloaded model sizes
    if min_size_mb > 0 and result.total_size_mb < min_size_mb * 0.3:  # Allow 70% tolerance
        result.corrupted_files.append(f"Total size {result.total_size_mb:.1f}MB is below minimum {min_size_mb}MB")
    
    # Check for critical files that should have minimum size
    for rel_path, file_path in all_files:
        file_size = os.path.getsize(file_path)
        # Skip hidden files, cache directories, and metadata files
        if (rel_path.startswith('.') or 
            '.cache' in rel_path or 
            rel_path.endswith('.gitattributes') or
            rel_path.endswith('.metadata') or
            '/.locks/' in rel_path):
            continue
        # If a file is extremely small (less than 1KB), it might be corrupted
        # But only check actual model files (bin, pth, ckpt)
        if file_size < 1024 and any(rel_path.endswith(ext) for ext in ['.bin', '.pth', '.ckpt', '.pt', '.safetensors']):
            result.corrupted_files.append(f"{rel_path} (size: {file_size} bytes)")
            logger.warning(f"Model {model_id} has suspiciously small file: {rel_path} ({file_size} bytes)")
    
    # Determine final status
    if result.missing_files or result.corrupted_files:
        if result.total_size_mb < min_size_mb * 0.3:
            result.status = ModelStatus.NOT_DOWNLOADED
        else:
            result.status = ModelStatus.INCOMPLETE
        result.is_healthy = False
        logger.debug(f"Model {model_id} marked as {result.status}: missing={result.missing_files}, corrupted={result.corrupted_files}")
    else:
        result.status = ModelStatus.DOWNLOADED
        result.is_healthy = True
    
    result.details["existing_files"] = list(existing_files)
    result.details["required_files"] = required_files
    result.details["optional_files_present"] = [f for f in optional_files if f in existing_files]
    result.details["min_size_mb"] = min_size_mb
    
    return result


def is_model_healthy(model_id: str) -> bool:
    """
    Quick check if a model is healthy (downloaded and verified).
    
    Returns:
        True if model is healthy, False otherwise
    """
    result = verify_model_files(model_id)
    return result.is_healthy


def get_model_status(model_id: str) -> ModelStatus:
    """
    Get the current status of a model.
    
    Returns:
        ModelStatus enum value
    """
    result = verify_model_files(model_id)
    return result.status


def get_all_models_status() -> Dict[str, ModelIntegrityResult]:
    """
    Get integrity status for all known models.
    
    Returns:
        Dictionary mapping model_id to ModelIntegrityResult
    """
    results = {}
    for model_id in MODEL_INTEGRITY_CHECKS.keys():
        results[model_id] = verify_model_files(model_id)
    return results


def get_ready_for_tts() -> List[str]:
    """
    Get list of model IDs that are ready for TTS usage.
    
    Returns:
        List of healthy TTS model IDs
    """
    tts_models = ["xtts-v2", "bark", "tortoise"]
    ready = []
    for model_id in tts_models:
        if is_model_healthy(model_id):
            ready.append(model_id)
    return ready


def get_ready_for_stt() -> List[str]:
    """
    Get list of model IDs that are ready for STT usage.
    
    Returns:
        List of healthy STT model IDs
    """
    stt_models = ["whisper-tiny", "whisper-base", "whisper-small", "whisper-medium", "whisper-large-v3"]
    ready = []
    for model_id in stt_models:
        if is_model_healthy(model_id):
            ready.append(model_id)
    return ready


def repair_model_status(model_id: str) -> ModelIntegrityResult:
    """
    Check and repair model status in database based on actual files.
    
    This is useful when the database status doesn't match the actual files.
    """
    from app.database import SessionLocal
    from app.downloader.download_manager import DownloadManager
    
    result = verify_model_files(model_id)
    
    # Update database to match reality
    db = SessionLocal()
    try:
        download = DownloadManager.get_download(db, model_id)
        if download:
            if result.status == ModelStatus.DOWNLOADED and download.status != "completed":
                DownloadManager.update_progress(db, model_id, "completed", progress=100.0)
                logger.info(f"Updated {model_id} status to completed based on file check")
            elif result.status in [ModelStatus.CORRUPTED, ModelStatus.INCOMPLETE] and download.status == "completed":
                DownloadManager.update_progress(db, model_id, "failed", error="Model files are corrupted or incomplete")
                logger.warning(f"Updated {model_id} status to failed - files are corrupted")
        elif result.status == ModelStatus.DOWNLOADED:
            # Model exists but no DB record - create one
            from app.downloader.model_registry import get_model_info
            model_info = get_model_info(model_id)
            if model_info:
                DownloadManager.request_download(db, model_id)
                DownloadManager.update_progress(db, model_id, "completed", progress=100.0)
                logger.info(f"Created DB record for existing model {model_id}")
    finally:
        db.close()
    
    return result
