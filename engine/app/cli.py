"""
CLI interface for Python Voice Core engine.
Supports execution via Laravel Process facade or standalone CLI.
"""
import sys
import os

# On Windows, ensure SystemRoot and WINDIR are present in os.environ before
# importing asyncio / Winsock to prevent WSAEPROVIDERFAILEDINIT (WinError 10106).
if sys.platform == "win32":
    if "SystemRoot" not in os.environ and "SYSTEMROOT" not in os.environ:
        os.environ["SystemRoot"] = os.environ.get("WINDIR", r"C:\Windows")
    if "WINDIR" not in os.environ and "windir" not in os.environ:
        os.environ["WINDIR"] = os.environ.get("SystemRoot", r"C:\Windows")

import json
import argparse
from pathlib import Path

ENGINE_DIR = Path(__file__).resolve().parent.parent
if str(ENGINE_DIR) not in sys.path:
    sys.path.insert(0, str(ENGINE_DIR))

from app.config import settings

def split_text_by_pauses(text: str):
    import re
    pattern = r'\[(?:pause|es|duraklama)(?::([\d\.]+s?))?\]'
    matches = list(re.finditer(pattern, text, flags=re.IGNORECASE))
    if not matches:
        return [(text.strip(), 0.0)]

    segments = []
    last_idx = 0
    for m in matches:
        chunk = text[last_idx:m.start()].strip()
        dur_str = m.group(1)
        dur = 1.0
        if dur_str:
            try:
                dur = float(dur_str.lower().replace('s', '').strip())
            except ValueError:
                dur = 1.0
        if chunk:
            segments.append((chunk, dur))
        last_idx = m.end()

    rest = text[last_idx:].strip()
    if rest:
        segments.append((rest, 0.0))

    return segments

def clean_segment_text(text: str, engine_name: str = "") -> str:
    import re
    if not text:
        return ""
    is_bark = "bark" in (engine_name or "").lower()

    if not is_bark:
        text = re.sub(r'\[(?:whisper|f[ıi]s[ıi]lt[ıi])\]', '', text, flags=re.IGNORECASE)
        text = re.sub(r'\[[a-zA-Z0-9_\-\s]{2,20}\]', ' ', text)

    text = re.sub(r'[.!?,;:]\s*\.{2,}', '... ', text)
    text = re.sub(r'\.{4,}', '...', text)
    text = re.sub(r'(?:\s*\.\.\.\s*)+', '... ', text)
    text = re.sub(r'\s+([,?!.])', r'\1', text)
    text = re.sub(r'\s+', ' ', text)
    return text.strip()

def tts_command(args):
    from app.tts.registry import get_tts_engine
    try:
        text = args.text
        if getattr(args, "text_file", None) and os.path.exists(args.text_file):
            with open(args.text_file, "r", encoding="utf-8") as tf:
                text = tf.read()

        if not text:
            print(json.dumps({"success": False, "error": "No text provided"}), file=sys.stderr)
            return 1

        engine = get_tts_engine(args.engine)
        output_path = args.output
        if not output_path:
            import uuid
            os.makedirs(settings.OUTPUTS_DIR, exist_ok=True)
            output_path = str(settings.OUTPUTS_DIR / f"tts_{uuid.uuid4().hex[:8]}.wav")
        else:
            os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)

        import asyncio
        import uuid
        import shutil
        import numpy as np
        import soundfile as sf

        from app.audio.tts_stitcher import generate_stitched_audio

        kwargs = {}
        if args.profile:
            kwargs["profile_path"] = args.profile
        if getattr(args, "stability", None) is not None:
            kwargs["stability"] = args.stability
            # For autoregressive / diffusion models (XTTS, Bark), high stability maps to lower temperature
            kwargs["temperature"] = max(0.1, min(1.2, round(1.15 - args.stability * 0.75, 2)))
        if getattr(args, "temperature", None) is not None:
            kwargs["temperature"] = args.temperature
        if getattr(args, "speed", None) is not None:
            kwargs["speed_factor"] = args.speed
            kwargs["speed"] = args.speed
        if getattr(args, "pitch", None) is not None:
            kwargs["pitch"] = args.pitch
        if getattr(args, "similarity_boost", None) is not None:
            kwargs["similarity_boost"] = args.similarity_boost
        if getattr(args, "style", None) is not None:
            kwargs["style"] = args.style
        if getattr(args, "voice", None):
            kwargs["voice"] = args.voice
        if getattr(args, "default_pause", None) is not None:
            kwargs["default_pause_sec"] = args.default_pause

        ok = asyncio.run(generate_stitched_audio(
            engine=engine,
            text=text,
            output_path=output_path,
            language=args.language,
            **kwargs
        ))

        if ok and os.path.exists(output_path):
            result = {
                "success": True,
                "output_path": output_path,
                "size_bytes": os.path.getsize(output_path),
            }
            print(json.dumps(result))
            return 0
        else:
            result = {"success": False, "error": "Generation failed or output file not created"}
            print(json.dumps(result), file=sys.stderr)
            return 1
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}), file=sys.stderr)
        return 1

def stt_command(args):
    import asyncio
    from app.stt.registry import get_stt_engine
    try:
        engine_name = getattr(args, "engine", None) or "whisper"
        engine = get_stt_engine(engine_name)
        lang = args.language if args.language != "auto" else None
        model_size = getattr(args, "model_size", None)
        transcription = asyncio.run(engine.transcribe(
            audio_path=args.audio,
            language=lang,
            model_size=model_size,
        ))

        if isinstance(transcription, str) and transcription.startswith("Error:"):
            raise RuntimeError(transcription)

        if isinstance(transcription, dict):
            text = transcription.get("text", "")
            detected_lang = transcription.get("language", args.language)
            segments = transcription.get("segments", [])
        else:
            text = str(transcription)
            detected_lang = args.language
            segments = []

        result = {
            "success": True,
            "text": text,
            "language": detected_lang,
            "segments": segments,
        }
        print(json.dumps(result))
        return 0
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}), file=sys.stderr)
        return 1

def download_command(args):
    from app.config import settings, reload_custom_settings
    reload_custom_settings()
    if getattr(args, "token", None):
        settings.HF_TOKEN = args.token
        os.environ["HF_TOKEN"] = args.token
        os.environ["HUGGING_FACE_HUB_TOKEN"] = args.token
    from app.downloader.download_utils import download_model
    try:
        ok = download_model(args.model)
        result = {"success": ok, "model_id": args.model}
        print(json.dumps(result))
        return 0 if ok else 1
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}), file=sys.stderr)
        return 1

def models_command(args):
    from app.downloader.model_registry import AVAILABLE_MODELS
    from app.downloader.integrity_checker import is_model_healthy
    models = []
    for m in AVAILABLE_MODELS:
        m_copy = dict(m)
        m_copy["is_downloaded"] = is_model_healthy(m["id"])
        models.append(m_copy)
    print(json.dumps(models))
    return 0

def system_command(args):
    from app.routers.system_router import collect_system_stats
    stats = collect_system_stats()
    print(json.dumps(stats))
    return 0

def story_mix_command(args):
    from app.audio.mixer import mix_tracks
    try:
        sfx_list = []
        if getattr(args, "sfx_json", None):
            try:
                if os.path.exists(args.sfx_json):
                    with open(args.sfx_json, "r", encoding="utf-8") as f:
                        sfx_list = json.load(f)
                else:
                    sfx_list = json.loads(args.sfx_json)
            except Exception as ex:
                print(f"Warning: Failed to parse sfx_json: {ex}", file=sys.stderr)

        ducking = not getattr(args, "no_ducking", False)
        bgm_volume = float(getattr(args, "bgm_volume", 0.25))

        res = mix_tracks(
            voice_path=args.voice,
            bgm_path=getattr(args, "bgm", None),
            sfx_list=sfx_list,
            output_path=getattr(args, "output", None),
            ducking=ducking,
            bgm_volume=bgm_volume,
            stems_dir=getattr(args, "stems_dir", None),
        )
        print(json.dumps(res))
        return 0
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}), file=sys.stderr)
        return 1

def story_align_command(args):
    from app.stt.whisper import WhisperEngine
    try:
        engine = WhisperEngine()
        words = engine.align_words_sync(
            audio_path=args.audio,
            language=getattr(args, "language", "tr"),
            model_size=getattr(args, "model_size", None)
        )
        print(json.dumps({"success": True, "words": words}))
        return 0
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}), file=sys.stderr)
        return 1

def sfx_generate_command(args):
    from app.audio.sfx_generator import generate_sfx
    try:
        res = generate_sfx(
            prompt=getattr(args, "prompt", ""),
            preset=getattr(args, "preset", None),
            duration=float(getattr(args, "duration", 2.0)),
            reverb=getattr(args, "reverb", "room"),
            tone=getattr(args, "tone", "balanced"),
            output_path=getattr(args, "output", None)
        )
        print(json.dumps(res, ensure_ascii=False))
        return 0
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}), file=sys.stderr)
        return 1

def music_generate_command(args):
    from app.audio.music_generator import generate_music
    try:
        no_loop = getattr(args, "no_loop", False)
        bpm = getattr(args, "bpm", None)
        if bpm is not None:
            bpm = int(bpm)
        res = generate_music(
            prompt=getattr(args, "prompt", ""),
            genre=getattr(args, "genre", "fairytale_children"),
            bpm=bpm,
            scale=getattr(args, "scale", None),
            texture=getattr(args, "texture", None),
            duration=float(getattr(args, "duration", 15.0)),
            loop=not no_loop,
            output_path=getattr(args, "output", None)
        )
        print(json.dumps(res, ensure_ascii=False))
        return 0
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}), file=sys.stderr)
        return 1

def main():
    parser = argparse.ArgumentParser(description="TailAdmin Voice Core CLI")
    subparsers = parser.add_subparsers(dest="command", required=True)

    # TTS
    tts_parser = subparsers.add_parser("tts")
    tts_parser.add_argument("--text", default=None)
    tts_parser.add_argument("--text-file", dest="text_file", default=None)
    tts_parser.add_argument("--engine", default="piper-tr")
    tts_parser.add_argument("--language", default="tr")
    tts_parser.add_argument("--output", default=None)
    tts_parser.add_argument("--profile", default=None)
    tts_parser.add_argument("--stability", type=float, default=None)
    tts_parser.add_argument("--temperature", type=float, default=None)
    tts_parser.add_argument("--speed", type=float, default=None)
    tts_parser.add_argument("--pitch", type=float, default=0.0)
    tts_parser.add_argument("--similarity-boost", dest="similarity_boost", type=float, default=None)
    tts_parser.add_argument("--style", type=float, default=None)
    tts_parser.add_argument("--voice", default=None)
    tts_parser.add_argument("--default-pause", dest="default_pause", type=float, default=None)

    # STT
    stt_parser = subparsers.add_parser("stt")
    stt_parser.add_argument("--audio", required=True)
    stt_parser.add_argument("--language", default="tr")
    stt_parser.add_argument("--model-size", dest="model_size", default=None)
    stt_parser.add_argument("--engine", default=None)

    # Download
    dl_parser = subparsers.add_parser("download")
    dl_parser.add_argument("--model", required=True)
    dl_parser.add_argument("--token", default=None)

    # Story Mix
    mix_parser = subparsers.add_parser("story-mix")
    mix_parser.add_argument("--voice", required=True)
    mix_parser.add_argument("--bgm", default=None)
    mix_parser.add_argument("--sfx-json", default=None)
    mix_parser.add_argument("--output", default=None)
    mix_parser.add_argument("--stems-dir", default=None)
    mix_parser.add_argument("--no-ducking", action="store_true", default=False)
    mix_parser.add_argument("--bgm-volume", default=0.25, type=float)

    # Story Align
    align_parser = subparsers.add_parser("story-align")
    align_parser.add_argument("--audio", required=True)
    align_parser.add_argument("--language", default="tr")
    align_parser.add_argument("--model-size", default=None)

    # SFX Generate
    sfx_parser = subparsers.add_parser("sfx-generate")
    sfx_parser.add_argument("--prompt", default="")
    sfx_parser.add_argument("--preset", default=None)
    sfx_parser.add_argument("--duration", type=float, default=2.0)
    sfx_parser.add_argument("--reverb", default="room")
    sfx_parser.add_argument("--tone", default="balanced")
    sfx_parser.add_argument("--output", default=None)

    # Music Generate
    music_parser = subparsers.add_parser("music-generate")
    music_parser.add_argument("--prompt", default="")
    music_parser.add_argument("--genre", default="fairytale_children")
    music_parser.add_argument("--bpm", type=int, default=None)
    music_parser.add_argument("--scale", default=None)
    music_parser.add_argument("--texture", default=None)
    music_parser.add_argument("--duration", type=float, default=15.0)
    music_parser.add_argument("--no-loop", action="store_true", default=False)
    music_parser.add_argument("--output", default=None)

    # Models list
    subparsers.add_parser("models")

    # System stats
    subparsers.add_parser("system")

    args = parser.parse_args()
    if args.command == "tts":
        sys.exit(tts_command(args))
    elif args.command == "stt":
        sys.exit(stt_command(args))
    elif args.command == "download":
        sys.exit(download_command(args))
    elif args.command == "story-mix":
        sys.exit(story_mix_command(args))
    elif args.command == "story-align":
        sys.exit(story_align_command(args))
    elif args.command == "sfx-generate":
        sys.exit(sfx_generate_command(args))
    elif args.command == "music-generate":
        sys.exit(music_generate_command(args))
    elif args.command == "models":
        sys.exit(models_command(args))
    elif args.command == "system":
        sys.exit(system_command(args))

if __name__ == "__main__":
    main()
