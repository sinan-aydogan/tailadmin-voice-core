"""
Parametric DSP Sound Effects (SFX) Generator.
Generates realistic, high-quality procedural and AI-tailored sound effects offline
using synthesis, frequency modulation, bandpass filters, envelope shapers, and noise clustering.
Also provides extensible hooks for AudioGen and Cloud AI SFX APIs.
"""

import os
import sys
import math
import wave
import struct
import random
import json
from pathlib import Path
from typing import Dict, Any, Optional

SAMPLE_RATE = 24000  # 24kHz studio standard for Voice Core

def _apply_envelope(samples: list, attack_sec: float, decay_sec: float, sustain_level: float, release_sec: float, total_sec: float) -> list:
    """Apply standard ADSR envelope to a list of float audio samples (-1.0 to 1.0)."""
    n_total = len(samples)
    attack_samples = int(attack_sec * SAMPLE_RATE)
    decay_samples = int(decay_sec * SAMPLE_RATE)
    release_samples = int(release_sec * SAMPLE_RATE)
    sustain_samples = max(0, n_total - attack_samples - decay_samples - release_samples)

    env = []
    # Attack: 0.0 -> 1.0
    for i in range(min(attack_samples, n_total)):
        env.append(i / max(1, attack_samples))
    # Decay: 1.0 -> sustain_level
    for i in range(min(decay_samples, n_total - len(env))):
        t = i / max(1, decay_samples)
        env.append(1.0 - t * (1.0 - sustain_level))
    # Sustain: constant sustain_level
    for i in range(min(sustain_samples, n_total - len(env))):
        env.append(sustain_level)
    # Release: sustain_level -> 0.0
    current_len = len(env)
    rem = n_total - current_len
    for i in range(rem):
        env.append(sustain_level * (1.0 - (i / max(1, rem))))

    return [s * e for s, e in zip(samples, env)]

def _write_wav(output_path: str, samples: list, sample_rate: int = SAMPLE_RATE) -> str:
    """Normalize and write 16-bit mono PCM wav file."""
    os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
    # Normalize peak to 0.88 to avoid clipping
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

# ==================== SFX PROCEDURAL SYNTHESIZERS ====================

def synth_birds_chirping(duration_sec: float = 1.8) -> list:
    """Synthesizes melodic forest birds chirping with vibrato and frequency modulation."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    samples = [0.0] * n_samples
    
    # 3-5 distinct chirp chirps
    num_chirps = int(duration_sec * 3)
    for c in range(num_chirps):
        start_t = c * (duration_sec / (num_chirps + 0.5)) + random.uniform(0.02, 0.08)
        chirp_len = random.uniform(0.12, 0.22)
        start_idx = int(start_t * SAMPLE_RATE)
        end_idx = min(n_samples, start_idx + int(chirp_len * SAMPLE_RATE))
        base_f = random.choice([2600.0, 3100.0, 3700.0, 4200.0])
        
        phase = 0.0
        for i in range(start_idx, end_idx):
            t_rel = (i - start_idx) / (end_idx - start_idx)
            # Sweeping curve up and down
            freq = base_f + math.sin(t_rel * math.pi) * 900.0 + math.sin(t_rel * 40.0) * 120.0
            phase += 2.0 * math.pi * freq / SAMPLE_RATE
            amp = math.sin(t_rel * math.pi) * 0.7
            samples[i] += math.sin(phase) * amp + math.sin(phase * 2) * (amp * 0.25)
            
    return samples

def synth_wind_whoosh(duration_sec: float = 2.5) -> list:
    """Synthesizes deep cinematic wind whoosh / howling breeze using swept bandpass noise."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    # Generate white noise
    noise = [random.uniform(-1.0, 1.0) for _ in range(n_samples)]
    # Lowpass swept resonant filter simulation
    samples = [0.0] * n_samples
    lp1, lp2 = 0.0, 0.0
    for i in range(n_samples):
        t_rel = i / n_samples
        # Cutoff sweeps up in middle and gently falls
        cutoff = 200.0 + 850.0 * math.sin(t_rel * math.pi)
        res = 0.85
        f = 2.0 * math.sin(math.pi * cutoff / SAMPLE_RATE)
        q = 1.0 - res
        lp1 += f * (noise[i] - lp1 - q * lp2)
        lp2 += f * lp1
        mod = 1.0 + 0.3 * math.sin(t_rel * 14.0)
        samples[i] = lp2 * mod

    return _apply_envelope(samples, 0.4, 0.5, 0.8, 0.8, duration_sec)

def synth_thunder_storm(duration_sec: float = 3.0) -> list:
    """Synthesizes deep rolling thunder crack and rumbling explosion."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    noise = [random.uniform(-1.0, 1.0) for _ in range(n_samples)]
    samples = [0.0] * n_samples
    
    # Sharp initial crack followed by deep lowpass rumble
    lp = 0.0
    for i in range(n_samples):
        t = i / SAMPLE_RATE
        t_rel = i / n_samples
        # Crack decay
        crack_decay = math.exp(-t * 8.0) * 0.6
        # Rumble filter
        cutoff = 120.0 + 350.0 * math.exp(-t * 2.0)
        alpha = (2.0 * math.pi * cutoff / SAMPLE_RATE) / (1.0 + 2.0 * math.pi * cutoff / SAMPLE_RATE)
        lp += alpha * (noise[i] - lp)
        rumble = lp * (0.8 + 0.4 * math.sin(t * 18.0))
        samples[i] = (noise[i] * crack_decay * 0.4) + (rumble * (1.0 - math.exp(-t * 12.0)))

    return _apply_envelope(samples, 0.02, 0.8, 0.5, 1.2, duration_sec)

def synth_magic_bell(duration_sec: float = 2.5) -> list:
    """Synthesizes ethereal, glistening fairy bell / magic chime with rich overtone decay."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    samples = [0.0] * n_samples
    
    # Chord notes for sparkling magic chime (Pentatonic bell cluster)
    frequencies = [1046.5, 1318.5, 1567.98, 2093.0, 2637.0, 3135.96]
    for idx, f in enumerate(frequencies):
        delay = idx * 0.04
        start_i = int(delay * SAMPLE_RATE)
        decay_rate = 2.2 - idx * 0.15
        for i in range(start_i, n_samples):
            t = (i - start_i) / SAMPLE_RATE
            amp = math.exp(-t * decay_rate) * 0.25
            shimmer = 1.0 + 0.15 * math.sin(t * 12.0)
            phase = 2.0 * math.pi * f * t
            # Fundamental + crystal harmonic
            s = (math.sin(phase) + 0.35 * math.sin(phase * 2.76) + 0.15 * math.sin(phase * 5.4)) * amp * shimmer
            samples[i] += s

    return samples

def synth_door_creak(duration_sec: float = 1.6) -> list:
    """Synthesizes an eerie, wooden door creak with friction stuttering."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    samples = [0.0] * n_samples
    phase = 0.0
    for i in range(n_samples):
        t_rel = i / n_samples
        # Pitch jitter for wooden friction
        friction = (math.sin(i * 0.05) > 0.3) * 1.0
        f = 320.0 + 480.0 * t_rel + math.sin(t_rel * 60.0) * 120.0 * friction
        phase += 2.0 * math.pi * f / SAMPLE_RATE
        # Non-linear square/saw resonance
        s = math.sin(phase) + 0.5 * math.sin(phase * 2) + 0.3 * math.sin(phase * 3)
        samples[i] = s * (0.4 + 0.6 * friction)

    return _apply_envelope(samples, 0.15, 0.2, 0.8, 0.3, duration_sec)

def synth_laser_blaster(duration_sec: float = 0.7) -> list:
    """Synthesizes high-tech sci-fi laser blaster / zap."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    samples = [0.0] * n_samples
    phase = 0.0
    for i in range(n_samples):
        t = i / SAMPLE_RATE
        # Rapid downward frequency sweep from 4500Hz down to 120Hz
        f = 120.0 + 4400.0 * math.exp(-t * 22.0)
        phase += 2.0 * math.pi * f / SAMPLE_RATE
        amp = math.exp(-t * 7.0)
        # Distorted square-ish wave
        samples[i] = math.tanh(math.sin(phase) * 2.5) * amp

    return samples

def synth_sword_clash(duration_sec: float = 1.4) -> list:
    """Synthesizes metallic sword clash / ringing metal impact."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    noise = [random.uniform(-1.0, 1.0) for _ in range(n_samples)]
    samples = [0.0] * n_samples
    
    # Sharp initial impact noise
    for i in range(min(n_samples, int(0.04 * SAMPLE_RATE))):
        samples[i] += noise[i] * 0.8
        
    # Metallic inharmonic ringing modes (typical of blade steel)
    modes = [1840.0, 2920.0, 4210.0, 5840.0, 7100.0]
    for m in modes:
        for i in range(n_samples):
            t = i / SAMPLE_RATE
            amp = math.exp(-t * 3.5) * 0.22
            samples[i] += math.sin(2.0 * math.pi * m * t) * amp

    return _apply_envelope(samples, 0.005, 0.3, 0.5, 0.6, duration_sec)

def synth_footsteps(duration_sec: float = 1.5) -> list:
    """Synthesizes sequence of 2-3 footsteps on soil/ground."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    samples = [0.0] * n_samples
    step_times = [0.1, 0.65, 1.2]
    
    for st in step_times:
        if st >= duration_sec:
            continue
        start_i = int(st * SAMPLE_RATE)
        step_len = int(0.18 * SAMPLE_RATE)
        for i in range(start_i, min(n_samples, start_i + step_len)):
            t = (i - start_i) / SAMPLE_RATE
            # Low thud + friction scratch
            thud = math.sin(2.0 * math.pi * 90.0 * t) * math.exp(-t * 35.0)
            crunch = random.uniform(-1.0, 1.0) * math.exp(-t * 25.0) * 0.4
            samples[i] += (thud * 0.8 + crunch) * 0.6

    return samples

def synth_cartoon_boing(duration_sec: float = 1.0) -> list:
    """Synthesizes classic cartoon spring boing sound."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    samples = [0.0] * n_samples
    phase = 0.0
    for i in range(n_samples):
        t = i / SAMPLE_RATE
        # Pitch sweeps up, with rapid spring modulation
        f_base = 220.0 + 350.0 * (1.0 - math.exp(-t * 4.0))
        f_spring = f_base + math.sin(t * 50.0) * 75.0 * math.exp(-t * 2.5)
        phase += 2.0 * math.pi * f_spring / SAMPLE_RATE
        amp = math.exp(-t * 2.8) * 0.8
        samples[i] = math.sin(phase) * amp

    return samples

def synth_rain_drops(duration_sec: float = 2.5) -> list:
    """Synthesizes gentle rain drops on leaves / water surface."""
    n_samples = int(duration_sec * SAMPLE_RATE)
    samples = [0.0] * n_samples
    num_drops = int(duration_sec * 24)
    
    for _ in range(num_drops):
        t_start = random.uniform(0.0, duration_sec - 0.1)
        start_i = int(t_start * SAMPLE_RATE)
        drop_len = int(random.uniform(0.02, 0.06) * SAMPLE_RATE)
        f_drop = random.uniform(1400.0, 2800.0)
        for i in range(start_i, min(n_samples, start_i + drop_len)):
            t = (i - start_i) / SAMPLE_RATE
            amp = math.exp(-t * 120.0) * random.uniform(0.15, 0.4)
            samples[i] += math.sin(2.0 * math.pi * f_drop * t) * amp
            
    # Add subtle pink noise floor for gentle ambient rain
    pink = 0.0
    for i in range(n_samples):
        pink = 0.95 * pink + 0.05 * random.uniform(-0.15, 0.15)
        samples[i] += pink * 0.2

    return samples

# ==================== DISPATCHER & PROMPT MATCHING ====================

PRESET_GENERATORS = {
    "birds_chirping": (synth_birds_chirping, "Kuş Cıvıltısı", "Doğa & Çevre"),
    "wind_whoosh": (synth_wind_whoosh, "Rüzgar Uğultusu", "Doğa & Çevre"),
    "thunder_storm": (synth_thunder_storm, "Gök Gürültüsü & Şimşek", "Doğa & Çevre"),
    "rain_drops": (synth_rain_drops, "Yağmur & Su Damlaları", "Doğa & Çevre"),
    "magic_bell": (synth_magic_bell, "Sihirli Çan & Peri Tozu", "Fantezi & Büyü"),
    "laser_blaster": (synth_laser_blaster, "Lazer Silahı & Zap", "Mekanik & Bilim Kurgu"),
    "sword_clash": (synth_sword_clash, "Kılıç & Metal Çarpışması", "Sinematik & Savaş"),
    "door_creak": (synth_door_creak, "Eski Ahşap Kapı Gıcırtısı", "Mekanik & Gizem"),
    "footsteps": (synth_footsteps, "Adım Sesleri", "Karakter & Hareket"),
    "cartoon_boing": (synth_cartoon_boing, "Çizgi Film Yay / Boing", "Komedi & Eğlence"),
}

def generate_sfx(
    prompt: str = "",
    preset: Optional[str] = None,
    duration: float = 2.0,
    reverb: str = "room",
    tone: str = "balanced",
    output_path: Optional[str] = None
) -> Dict[str, Any]:
    """
    Main entry point for generating sound effects.
    Intelligently matches user prompt or preset to the highest fidelity procedural synthesizer,
    applies tone shaping and acoustic reverberation, and writes standard WAV.
    """
    prompt_lower = (prompt or "").lower()
    selected_preset = preset

    # Automatic prompt mapping if preset not explicitly chosen
    if not selected_preset or selected_preset == "auto":
        if any(w in prompt_lower for w in ["kuş", "bird", "chirp", "ormanda", "kanat"]):
            selected_preset = "birds_chirping"
        elif any(w in prompt_lower for w in ["rüzgar", "wind", "whoosh", "fırtına", "esinti"]):
            selected_preset = "wind_whoosh"
        elif any(w in prompt_lower for w in ["şimşek", "gök gürültü", "thunder", "patlama", "yıldırım"]):
            selected_preset = "thunder_storm"
        elif any(w in prompt_lower for w in ["yağmur", "rain", "damla", "su", "şırıltı"]):
            selected_preset = "rain_drops"
        elif any(w in prompt_lower for w in ["büyü", "sihir", "magic", "peri", "çan", "bell", "glowing"]):
            selected_preset = "magic_bell"
        elif any(w in prompt_lower for w in ["lazer", "laser", "blaster", "uzay", "sci-fi", "ateş"]):
            selected_preset = "laser_blaster"
        elif any(w in prompt_lower for w in ["kılıç", "sword", "metal", "çarpış", "bıçak"]):
            selected_preset = "sword_clash"
        elif any(w in prompt_lower for w in ["kapı", "door", "gıcırtı", "creak", "kilit"]):
            selected_preset = "door_creak"
        elif any(w in prompt_lower for w in ["adım", "footstep", "yürüme", "koşma"]):
            selected_preset = "footsteps"
        elif any(w in prompt_lower for w in ["yay", "boing", "zıpla", "bounce", "komik", "cartoon"]):
            selected_preset = "cartoon_boing"
        else:
            # Default to versatile magic bell or wind whoosh based on prompt vibe
            selected_preset = "magic_bell" if "hoş" in prompt_lower or "güzel" in prompt_lower else "wind_whoosh"

    # Clamp duration to realistic bounds
    duration = max(0.4, min(10.0, float(duration)))
    gen_func, title_tr, category = PRESET_GENERATORS.get(selected_preset, (synth_magic_bell, "Özel Efekt", "Özel"))

    # Generate core raw samples
    samples = gen_func(duration)

    # Apply tone filters
    if tone == "bass":
        # Boost low end with moving average smoothing
        smoothed = []
        val = 0.0
        for s in samples:
            val = 0.7 * val + 0.3 * s
            smoothed.append(val * 1.3)
        samples = smoothed
    elif tone == "bright":
        # Highpass / treble boost
        brightened = []
        prev = 0.0
        for s in samples:
            diff = s - prev
            prev = s
            brightened.append(s * 0.7 + diff * 0.8)
        samples = brightened

    # Apply Reverb (simple comb delay lines)
    if reverb in ["room", "cave", "hall"]:
        delay_times = [0.03, 0.045, 0.06] if reverb == "room" else [0.06, 0.09, 0.14]
        decay = 0.35 if reverb == "room" else 0.55
        reverb_samples = list(samples)
        for dt in delay_times:
            d_idx = int(dt * SAMPLE_RATE)
            for i in range(d_idx, len(samples)):
                reverb_samples[i] += samples[i - d_idx] * decay
        samples = reverb_samples

    # Write WAV file
    if not output_path:
        import uuid
        data_sfx_dir = Path(__file__).resolve().parent.parent.parent.parent / "data" / "sfx"
        output_path = str(data_sfx_dir / f"{selected_preset}_{uuid.uuid4().hex[:6]}.wav")

    final_path = _write_wav(output_path, samples)

    return {
        "success": True,
        "preset": selected_preset,
        "title": title_tr,
        "category": category,
        "duration_sec": round(duration, 2),
        "output_path": final_path,
        "filename": os.path.basename(final_path),
        "size_bytes": os.path.getsize(final_path)
    }

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="TailAdmin Voice Core SFX Generator")
    parser.add_argument("--prompt", default="", help="Text prompt for the effect")
    parser.add_argument("--preset", default=None, help="Preset key")
    parser.add_argument("--duration", type=float, default=2.0, help="Duration in seconds")
    parser.add_argument("--reverb", default="room", help="Reverb: dry, room, cave, hall")
    parser.add_argument("--tone", default="balanced", help="Tone: balanced, bass, bright")
    parser.add_argument("--output", default=None, help="Output wav path")

    args = parser.parse_args()
    res = generate_sfx(
        prompt=args.prompt,
        preset=args.preset,
        duration=args.duration,
        reverb=args.reverb,
        tone=args.tone,
        output_path=args.output
    )
    print(json.dumps(res, ensure_ascii=False))
