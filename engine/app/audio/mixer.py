"""
Multi-Track Audio Mixer with Audio Ducking and SFX Alignment.
Supports combining Voiceover, Background Music (BGM), and Sound Effects (SFX).
"""
import os
import math
import numpy as np
import soundfile as sf
import scipy.signal
from typing import List, Dict, Optional, Tuple
from loguru import logger

DEFAULT_SAMPLE_RATE = 24000

def load_audio(file_path: str, target_sr: int = DEFAULT_SAMPLE_RATE) -> Tuple[np.ndarray, int]:
    """Load an audio file, convert to mono float32, and resample to target_sr."""
    if not os.path.exists(file_path):
        raise FileNotFoundError(f"Audio file not found: {file_path}")
        
    data, sr = sf.read(file_path, dtype='float32')
    
    # Convert stereo/multichannel to mono by averaging
    if data.ndim > 1:
        data = np.mean(data, axis=1)
        
    # Resample if sample rate doesn't match
    if sr != target_sr:
        num_samples = int(len(data) * float(target_sr) / float(sr))
        data = scipy.signal.resample(data, num_samples).astype(np.float32)
        sr = target_sr
        
    return data, sr

def loop_or_trim(audio: np.ndarray, target_length: int) -> np.ndarray:
    """Loop audio if it's shorter than target_length, or trim if longer."""
    if len(audio) == target_length:
        return audio
    if len(audio) > target_length:
        return audio[:target_length]
    # Loop
    repeats = int(math.ceil(target_length / len(audio)))
    tiled = np.tile(audio, repeats)
    return tiled[:target_length]

def apply_audio_ducking(
    bgm_audio: np.ndarray,
    voice_audio: np.ndarray,
    sr: int = DEFAULT_SAMPLE_RATE,
    duck_gain: float = 0.20,
    normal_gain: float = 0.70,
    attack_ms: int = 150,
    release_ms: int = 400,
    speech_threshold: float = 0.015,
) -> np.ndarray:
    """
    Apply smooth audio ducking to BGM whenever voice energy is detected.
    Attenuates BGM during speech and restores normal volume during pauses.
    """
    target_length = len(voice_audio)
    bgm = loop_or_trim(bgm_audio, target_length).copy()
    voice = voice_audio
        
    # Compute voice amplitude envelope using a 50ms moving RMS window
    window_size = int(sr * 0.05)
    squared = voice ** 2
    # Moving average
    kernel = np.ones(window_size) / window_size
    rms = np.sqrt(np.convolve(squared, kernel, mode='same'))
    
    # Create target gain mask: duck_gain where voice > threshold, normal_gain otherwise
    is_speaking = rms > speech_threshold
    target_gains = np.where(is_speaking, duck_gain, normal_gain)
    
    # Smooth the gain curve using a low-pass filter (attack / release)
    smooth_samples = int(sr * ((attack_ms + release_ms) / 2000.0))
    if smooth_samples > 1:
        smoothing_kernel = np.hanning(smooth_samples)
        smoothing_kernel /= smoothing_kernel.sum()
        smooth_gains = np.convolve(target_gains, smoothing_kernel, mode='same')
    else:
        smooth_gains = target_gains
        
    return (bgm * smooth_gains).astype(np.float32)

def mix_tracks(
    voice_path: str,
    bgm_path: Optional[str] = None,
    sfx_list: Optional[List[Dict]] = None,
    output_path: Optional[str] = None,
    ducking: bool = True,
    bgm_volume: float = 0.25,
    stems_dir: Optional[str] = None,
    target_sr: int = DEFAULT_SAMPLE_RATE,
) -> Dict:
    """
    Mix voice, BGM, and SFX tracks into a unified master audio file.
    
    Args:
        voice_path: Path to main spoken audio file.
        bgm_path: Optional path to background music file.
        sfx_list: List of dicts, each with keys:
                  'path' (str), 'time_ms' (float or int), 'volume' (float, default 1.0).
        output_path: Path to save master mixed audio.
        ducking: Whether to duck BGM during voiceover.
        bgm_volume: Base volume multiplier for BGM.
        stems_dir: Optional directory to save separated stem tracks.
        target_sr: Standard sample rate for mixing.
        
    Returns:
        Dict with duration, paths, and status.
    """
    voice, sr = load_audio(voice_path, target_sr)
    total_length = len(voice)
    
    # 1. Process BGM
    bgm_final = None
    if bgm_path and os.path.exists(bgm_path):
        try:
            bgm_raw, _ = load_audio(bgm_path, target_sr)
            if ducking:
                bgm_final = apply_audio_ducking(
                    bgm_raw, voice, sr,
                    duck_gain=bgm_volume * 0.35,
                    normal_gain=bgm_volume
                )
            else:
                bgm_final = (loop_or_trim(bgm_raw, len(voice)) * bgm_volume).astype(np.float32)
        except Exception as e:
            logger.warning(f"Could not load or process BGM {bgm_path}: {e}")
            bgm_final = None
            
    # 2. Process SFX tracks
    sfx_track = np.zeros(total_length, dtype=np.float32)
    if sfx_list:
        for sfx_item in sfx_list:
            sfx_file = sfx_item.get("path")
            if not sfx_file or not os.path.exists(sfx_file):
                continue
            time_ms = float(sfx_item.get("time_ms", 0))
            vol = float(sfx_item.get("volume", 0.8))
            
            try:
                sfx_audio, _ = load_audio(sfx_file, target_sr)
                start_idx = max(0, int((time_ms / 1000.0) * target_sr))
                end_idx = start_idx + len(sfx_audio)
                
                # Expand master length if SFX extends past voice
                if end_idx > len(sfx_track):
                    extra = end_idx - len(sfx_track)
                    sfx_track = np.pad(sfx_track, (0, extra), mode='constant')
                    voice = np.pad(voice, (0, extra), mode='constant')
                    if bgm_final is not None:
                        bgm_final = np.pad(bgm_final, (0, extra), mode='constant')
                        
                sfx_track[start_idx:end_idx] += sfx_audio * vol
            except Exception as e:
                logger.warning(f"Failed to place SFX {sfx_file} at {time_ms}ms: {e}")

    # Ensure all tracks match final voice/SFX duration
    max_len = max(len(voice), len(sfx_track))
    voice = loop_or_trim(voice, max_len)
    sfx_track = loop_or_trim(sfx_track, max_len)
    
    # 3. Master Mix
    master = voice.copy() + sfx_track
    if bgm_final is not None:
        bgm_final = loop_or_trim(bgm_final, max_len)
        master += bgm_final
        
    # Soft peak limiter / normalization to avoid clipping
    peak = np.max(np.abs(master))
    if peak > 0.95:
        master = master * (0.95 / peak)
        
    # Save output master
    if not output_path:
        import uuid
        from app.config import settings
        os.makedirs(settings.OUTPUTS_DIR, exist_ok=True)
        output_path = str(settings.OUTPUTS_DIR / f"story_master_{uuid.uuid4().hex[:8]}.wav")
    else:
        os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
        
    sf.write(output_path, master, target_sr, subtype='PCM_16')
    duration_sec = round(len(master) / float(target_sr), 2)
    
    # Save stems if requested
    stems = {}
    if stems_dir:
        os.makedirs(stems_dir, exist_ok=True)
        v_stem = os.path.join(stems_dir, "stem_voice.wav")
        s_stem = os.path.join(stems_dir, "stem_sfx.wav")
        sf.write(v_stem, voice, target_sr, subtype='PCM_16')
        sf.write(s_stem, sfx_track, target_sr, subtype='PCM_16')
        stems["voice"] = v_stem
        stems["sfx"] = s_stem
        if bgm_final is not None:
            b_stem = os.path.join(stems_dir, "stem_bgm.wav")
            sf.write(b_stem, bgm_final, target_sr, subtype='PCM_16')
            stems["bgm"] = b_stem

    return {
        "success": True,
        "master_audio_path": output_path,
        "duration_sec": duration_sec,
        "sample_rate": target_sr,
        "stems": stems
    }
