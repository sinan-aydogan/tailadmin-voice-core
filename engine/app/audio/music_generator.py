"""
Parametric DSP Music & Ambient BGM Generator.
Generates beautiful, harmonically rich musical textures, ambient pads,
chord progressions, and seamless loopable background tracks.
Provides offline zero-latency musical generation, plus extensibility for MusicGen.
"""

import os
import sys
import math
import wave
import struct
import random
import json
from pathlib import Path
from typing import Dict, Any, Optional, List

SAMPLE_RATE = 24000  # 24kHz studio standard

# Frequencies of standard musical notes in Hz
NOTE_FREQS = {
    # Octave 3
    "C3": 130.81, "D3": 146.83, "E3": 164.81, "F3": 174.61, "G3": 196.00, "A3": 220.00, "B3": 246.94,
    # Octave 4
    "C4": 261.63, "D4": 293.66, "E4": 329.63, "F4": 349.23, "G4": 392.00, "A4": 440.00, "B4": 493.88,
    # Octave 5
    "C5": 523.25, "D5": 587.33, "E5": 659.25, "F5": 698.46, "G5": 783.99, "A5": 880.00, "B5": 987.77,
}

# Musical chord templates for different emotional genres
GENRE_CONFIGS = {
    "fairytale_children": {
        "title_tr": "Masal & Çocuk (Neşeli & Huzurlu)",
        "scale": "C Major",
        "default_bpm": 90,
        "texture": "acoustic",
        "description": "Sıcak akustik tonlar, tatlı masalsı akorlar ve çocuk dünyasına uygun neşeli melodi yatağı.",
        "chords": [
            ["C3", "C4", "E4", "G4", "B4"],  # Cmaj7
            ["F3", "C4", "F4", "A4", "C5"],  # Fmaj
            ["A3", "C4", "E4", "A4", "C5"],  # Amin
            ["G3", "B3", "D4", "G4", "B4"],  # Gmaj
        ]
    },
    "dramatic_cinematic": {
        "title_tr": "Dramatik Sinematik (Melankoli & Gerilim)",
        "scale": "D Minor",
        "default_bpm": 70,
        "texture": "strings",
        "description": "Hüzünlü yaylılar, kuraklık ve tehlike anlarında derin hissettiren sinematik minör akorlar.",
        "chords": [
            ["D3", "A3", "D4", "F4", "A4"],  # Dmin
            ["A3", "E4", "A4", "C5", "E5"],  # Amin
            ["B3", "F4", "B4", "D5", "F5"],  # Bdim / Bbmaj
            ["D3", "A3", "C4", "F4", "A4"],  # Dmin7
        ]
    },
    "uplifting_adventure": {
        "title_tr": "Umut & Macera (Yükselen & Zafer)",
        "scale": "G Major",
        "default_bpm": 105,
        "texture": "orchestral",
        "description": "Bilgelik, dayanışma ve doğanın yeniden uyanışını kutlayan parlak, ilham verici müzik.",
        "chords": [
            ["G3", "G4", "B4", "D5", "G5"],  # Gmaj
            ["C3", "G4", "C5", "E5", "G5"],  # Cmaj
            ["E3", "G4", "B4", "E5", "G5"],  # Emin
            ["D3", "A4", "D5", "F5", "A5"],  # Dmaj
        ]
    },
    "lofi_ambient": {
        "title_tr": "Lo-Fi & Rahatlatıcı (Sakin Odaklanma)",
        "scale": "A Minor",
        "default_bpm": 80,
        "texture": "lofi",
        "description": "Sıcak Rhodes elektro piyano akorları, hafif vinil dokusu ve dinlendirici fon ambiyansı.",
        "chords": [
            ["A3", "C4", "E4", "G4", "B4"],  # Amin9
            ["D3", "F4", "A4", "C5", "E5"],  # Dmin9
            ["F3", "A4", "C5", "E5", "G5"],  # Fmaj9
            ["E3", "G4", "B4", "D5", "F5"],  # E7alt
        ]
    },
    "cosmic_scifi": {
        "title_tr": "Kozmik & Bilim Kurgu (Derin Uzay)",
        "scale": "E Minor",
        "default_bpm": 65,
        "texture": "synth",
        "description": "Derin analog synth pad'leri, uzay boşluğu tınlaması ve fütüristik gizemli akorlar.",
        "chords": [
            ["E3", "B3", "E4", "G4", "D5"],  # Emin7
            ["C3", "G3", "E4", "G4", "B4"],  # Cmaj7
            ["D3", "A3", "F4", "A4", "C5"],  # Dsus2
            ["B3", "F4", "B4", "D5", "F5"],  # Bmin
        ]
    }
}

def _render_synth_pad_chord(chord_notes: List[str], duration_sec: float, texture: str = "acoustic") -> List[float]:
    """Renders a single lush, polyphonic sustained chord with chorus, warmth, and gentle filter."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    samples = [0.0] * n_samples

    for note in chord_notes:
        base_f = NOTE_FREQS.get(note, 220.0)
        
        # Chorus detuning (3 subtle detuned oscillators for analog depth)
        detunes = [0.0, 0.45, -0.45]
        for idx, detune in enumerate(detunes):
            f = base_f + detune
            phase = random.uniform(0.0, 2.0 * math.pi)
            amp_osc = 0.22 if idx == 0 else 0.12
            
            for i in range(n_samples):
                t = i / SAMPLE_RATE
                t_rel = i / n_samples
                
                # ADSR style envelope for musical chord transitions
                # Smooth rise (attack) and gentle decay into sustain
                if t_rel < 0.25:
                    env = math.sin((t_rel / 0.25) * (math.pi / 2))
                elif t_rel > 0.75:
                    env = math.cos(((t_rel - 0.75) / 0.25) * (math.pi / 2))
                else:
                    env = 1.0
                    
                # Subtle slow LFO vibrato (0.3 Hz)
                vibrato = 1.0 + 0.003 * math.sin(2.0 * math.pi * 0.3 * t)
                phase += 2.0 * math.pi * f * vibrato / SAMPLE_RATE
                
                # Harmonics based on instrument texture
                if texture == "acoustic":
                    # Soft warm tone with second harmonic
                    s = math.sin(phase) + 0.35 * math.sin(phase * 2) + 0.1 * math.sin(phase * 3)
                elif texture == "strings":
                    # Richer saw-like overtones
                    s = math.sin(phase) + 0.5 * math.sin(phase * 2) + 0.25 * math.sin(phase * 3) + 0.12 * math.sin(phase * 4)
                elif texture == "lofi":
                    # Warm bell-like electric piano
                    s = math.sin(phase) + 0.4 * math.sin(phase * 2) + 0.05 * math.sin(phase * 4)
                else:  # synth/orchestral
                    s = math.sin(phase) + 0.4 * math.sin(phase * 1.5) + 0.2 * math.sin(phase * 2)
                    
                samples[i] += s * env * amp_osc

    # Warm low-pass filter
    lp = 0.0
    filtered = []
    alpha = 0.18 if texture in ["lofi", "acoustic"] else 0.28
    for s in samples:
        lp += alpha * (s - lp)
        filtered.append(lp)

    return filtered

def _make_seamless_loop(samples: List[float], crossfade_sec: float = 1.2) -> List[float]:
    """Applies crossfade between start and end of audio track for 100% click-free seamless loop."""
    xf_samples = int(crossfade_sec * SAMPLE_RATE)
    if len(samples) <= xf_samples * 2:
        return samples

    head = samples[:xf_samples]
    tail = samples[-xf_samples:]
    body = samples[xf_samples:-xf_samples]

    crossfaded_tail = []
    for i in range(xf_samples):
        ratio = i / xf_samples
        # Fade out tail, fade in head
        s = tail[i] * (1.0 - ratio) + head[i] * ratio
        crossfaded_tail.append(s)

    return body + crossfaded_tail

def _write_wav(output_path: str, samples: List[float], sample_rate: int = SAMPLE_RATE) -> str:
    """Normalize and write 16-bit mono PCM wav file."""
    os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
    peak = max(abs(s) for s in samples) if samples else 0.0001
    norm_factor = 0.88 / max(0.0001, peak)
    
    with wave.open(output_path, 'wb') as wf:
        wf.setnchannels(1)
        wf.setsampwidth(2)
        wf.setframerate(sample_rate)
        packed = bytearray()
        for s in samples:
            val = int(max(-32767, min(32767, s * norm_factor * 32767)))
            packed.extend(struct.pack('<h', val))
        wf.writeframes(packed)
    return output_path

# ==================== MAIN GENERATOR ====================

def generate_music(
    prompt: str = "",
    genre: Optional[str] = "fairytale_children",
    bpm: Optional[int] = None,
    scale: Optional[str] = None,
    texture: Optional[str] = None,
    duration: float = 15.0,
    loop: bool = True,
    output_path: Optional[str] = None
) -> Dict[str, Any]:
    """
    Main entry point for generating background music & ambient tracks.
    Selects chords and timbre based on genre and prompt, sequences the progression,
    and applies seamless loop blending.
    """
    prompt_lower = (prompt or "").lower()

    # Intelligent prompt fallback if genre not explicitly chosen
    if not genre or genre == "auto":
        if any(w in prompt_lower for w in ["dram", "hüzün", "korku", "kuraklık", "gerilim", "sad", "dark"]):
            genre = "dramatic_cinematic"
        elif any(w in prompt_lower for w in ["umut", "zafer", "macera", "uyanış", "hope", "uplifting", "epic"]):
            genre = "uplifting_adventure"
        elif any(w in prompt_lower for w in ["lofi", "lo-fi", "chill", "sakin", "piyano", "çalışma", "study"]):
            genre = "lofi_ambient"
        elif any(w in prompt_lower for w in ["uzay", "synth", "elektronik", "fütüristik", "scifi", "cyber"]):
            genre = "cosmic_scifi"
        else:
            genre = "fairytale_children"

    conf = GENRE_CONFIGS.get(genre, GENRE_CONFIGS["fairytale_children"])
    target_bpm = bpm if (bpm and 40 <= bpm <= 200) else conf["default_bpm"]
    target_texture = texture if texture else conf["texture"]
    target_scale = scale if scale else conf["scale"]

    # Calculate progression timing
    # Each chord lasts approx 2-4 bars depending on tempo and duration
    chords = conf["chords"]
    sec_per_beat = 60.0 / target_bpm
    beats_per_chord = 4  # 4 beats per chord
    chord_duration = beats_per_chord * sec_per_beat  # e.g., 90bpm -> 2.66s per chord

    total_duration = max(5.0, min(120.0, float(duration)))
    num_cycles = max(1, math.ceil(total_duration / (chord_duration * len(chords))))
    
    full_samples = []
    for _ in range(num_cycles):
        for chord in chords:
            chord_audio = _render_synth_pad_chord(chord, chord_duration, target_texture)
            full_samples.extend(chord_audio)
            if len(full_samples) / SAMPLE_RATE >= total_duration:
                break
        if len(full_samples) / SAMPLE_RATE >= total_duration:
            break

    # Trim to exact target duration
    target_samples = int(total_duration * SAMPLE_RATE)
    full_samples = full_samples[:target_samples]

    # Make seamless loop if requested
    if loop and len(full_samples) > SAMPLE_RATE * 3:
        full_samples = _make_seamless_loop(full_samples, crossfade_sec=1.5)

    # Output path
    if not output_path:
        import uuid
        data_bgm_dir = Path(__file__).resolve().parent.parent.parent.parent / "data" / "bgm"
        output_path = str(data_bgm_dir / f"{genre}_{uuid.uuid4().hex[:6]}.wav")

    final_path = _write_wav(output_path, full_samples)

    return {
        "success": True,
        "genre": genre,
        "title": conf["title_tr"],
        "scale": target_scale,
        "bpm": target_bpm,
        "texture": target_texture,
        "duration_sec": round(len(full_samples) / SAMPLE_RATE, 2),
        "loopable": loop,
        "output_path": final_path,
        "filename": os.path.basename(final_path),
        "size_bytes": os.path.getsize(final_path)
    }

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="TailAdmin Voice Core Music Generator")
    parser.add_argument("--prompt", default="", help="Text prompt / vibe description")
    parser.add_argument("--genre", default="fairytale_children", help="Genre key")
    parser.add_argument("--bpm", type=int, default=None, help="BPM tempo")
    parser.add_argument("--scale", default=None, help="Scale name")
    parser.add_argument("--texture", default=None, help="Sound texture: acoustic, strings, lofi, synth")
    parser.add_argument("--duration", type=float, default=15.0, help="Duration in seconds")
    parser.add_argument("--no-loop", action="store_true", default=False, help="Disable seamless loop")
    parser.add_argument("--output", default=None, help="Output wav path")

    args = parser.parse_args()
    res = generate_music(
        prompt=args.prompt,
        genre=args.genre,
        bpm=args.bpm,
        scale=args.scale,
        texture=args.texture,
        duration=args.duration,
        loop=not args.no_loop,
        output_path=args.output
    )
    print(json.dumps(res, ensure_ascii=False))
