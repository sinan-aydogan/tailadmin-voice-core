"""
TTS Silence & Pause Stitcher Utility.
Enables true acoustic pauses across TTS models (Alania, Piper, Freya, Bark, etc.)
by generating separate audio clips for text segments and splicing exact zero-amplitude silence intervals.
"""
import os
import re
import sys
import uuid
import shutil
from pathlib import Path
from typing import List, Tuple, Optional
from loguru import logger

try:
    import numpy as np
    import soundfile as sf
except ImportError:
    np = None
    sf = None


def split_text_by_pauses(text: str, default_pause_sec: float = 1.0) -> List[Tuple[str, float]]:
    """
    Parses text and splits it by [pause], [pause:Xs], [es], or [duraklama] tags.
    Returns a list of tuples: (segment_text, pause_duration_seconds).
    """
    pattern = r'\[(?:pause|es|duraklama)(?::([\d\.]+s?))?\]'
    matches = list(re.finditer(pattern, text, flags=re.IGNORECASE))
    if not matches:
        return [(text.strip(), 0.0)]

    segments = []
    last_idx = 0
    for m in matches:
        chunk = text[last_idx:m.start()].strip()
        dur_str = m.group(1)
        dur = float(default_pause_sec) if default_pause_sec else 1.0
        if dur_str:
            try:
                dur = float(dur_str.lower().replace('s', '').strip())
                dur = max(0.1, min(dur, 10.0))
            except ValueError:
                dur = float(default_pause_sec) if default_pause_sec else 1.0
        if chunk:
            segments.append((chunk, dur))
        last_idx = m.end()

    rest = text[last_idx:].strip()
    if rest:
        segments.append((rest, 0.0))

    return segments


def clean_segment_text(text: str, engine_name: str = "") -> str:
    """
    Cleans segment text before sending to the TTS model.
    Preserves Bark acoustic tags if engine is Bark.
    Strips raw shortcode brackets for non-Bark models.
    """
    if not text:
        return ""
    is_bark = "bark" in (engine_name or "").lower()

    if not is_bark:
        text = re.sub(r'\[(?:whisper|f[ıi]s[ıi]lt[ıi])\]', '', text, flags=re.IGNORECASE)
        # Strip any remaining bracket tags so words like [sigh] or [direction] are never read aloud
        text = re.sub(r'\[[^\]]+\]', ' ', text)

    # Normalize excessive dots & spaces
    text = re.sub(r'\.{4,}', '...', text)
    text = re.sub(r'[.!?,;:]\s*\.{2,}', '... ', text)
    text = re.sub(r'(?:\s*\.\.\.\s*)+', '... ', text)
    text = re.sub(r'\s+([,?!.])', r'\1', text)
    text = re.sub(r'\s+', ' ', text)
    return text.strip()


def _apply_pitch_shift(audio_path: str, n_steps: float):
    try:
        import librosa
        import soundfile as sf
        y, sr = sf.read(audio_path, dtype='float32')
        y_shifted = librosa.effects.pitch_shift(y, sr=sr, n_steps=n_steps)
        sf.write(audio_path, y_shifted, sr)
        logger.info(f"Applied pitch shift ({n_steps:+.1f} semitones) to {audio_path}")
    except Exception as e:
        logger.warning(f"Failed to apply pitch shift: {e}")


async def generate_stitched_audio(
    engine,
    text: str,
    output_path: str,
    language: str = "tr",
    profile_path: Optional[str] = None,
    user_id: Optional[str] = None,
    **kwargs
) -> bool:
    """
    Generates TTS audio. If [pause] tags are present, generates separate audio clips
    and stitches exact acoustic silence between them.
    Also applies post-processing (pitch shifting) if requested.
    """
    default_pause_sec = kwargs.get("default_pause_sec", 1.0)
    segs = split_text_by_pauses(text, default_pause_sec=default_pause_sec)
    engine_name = getattr(engine, "engine_name", "")

    pitch_steps = kwargs.get("pitch", 0.0)
    try:
        pitch_steps = float(pitch_steps)
    except (ValueError, TypeError):
        pitch_steps = 0.0

    # If single segment or numpy/soundfile unavailable, generate directly
    if len(segs) <= 1 or np is None or sf is None:
        clean_text = clean_segment_text(text, engine_name)
        if not clean_text:
            return False
        ok = await engine.generate_audio(
            text=clean_text,
            output_path=output_path,
            language=language,
            profile_path=profile_path,
            user_id=user_id,
            **kwargs
        )
        if ok and os.path.exists(output_path) and abs(pitch_steps) >= 0.1:
            _apply_pitch_shift(output_path, pitch_steps)
        return ok

    # Multi-segment stitching with exact silence intervals
    temp_dir = Path(output_path).parent / f"pause_temp_{uuid.uuid4().hex[:8]}"
    temp_dir.mkdir(parents=True, exist_ok=True)
    audio_parts = []
    sample_rate = 24000
    success = False

    try:
        logger.info(f"Splitting TTS into {len(segs)} segments for physical pause/silence insertion...")
        for idx, (seg_text, pause_sec) in enumerate(segs):
            clean_seg = clean_segment_text(seg_text, engine_name)
            if not clean_seg:
                continue

            seg_wav = str(temp_dir / f"seg_{idx}.wav")
            seg_ok = await engine.generate_audio(
                text=clean_seg,
                output_path=seg_wav,
                language=language,
                profile_path=profile_path,
                user_id=user_id,
                **kwargs
            )

            if seg_ok and os.path.exists(seg_wav) and os.path.getsize(seg_wav) > 100:
                data, sr = sf.read(seg_wav, dtype='float32')
                sample_rate = sr
                if data.ndim > 1:
                    data = np.mean(data, axis=1)
                audio_parts.append(data)

                if pause_sec > 0:
                    silence_samples = int(sample_rate * pause_sec)
                    audio_parts.append(np.zeros(silence_samples, dtype=np.float32))
            else:
                logger.warning(f"Segment {idx} audio generation failed or returned empty: '{clean_seg[:30]}'")

        if audio_parts:
            final_audio = np.concatenate(audio_parts)
            os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
            sf.write(output_path, final_audio, sample_rate)
            if abs(pitch_steps) >= 0.1:
                _apply_pitch_shift(output_path, pitch_steps)
            success = True
            logger.info(f"Successfully stitched {len(audio_parts)} parts into {output_path}")
    finally:
        shutil.rmtree(temp_dir, ignore_errors=True)

    return success
