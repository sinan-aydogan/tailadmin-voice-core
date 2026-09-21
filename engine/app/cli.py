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

def tts_command(args):
    from app.tts.registry import get_tts_engine
    try:
        engine = get_tts_engine(args.engine)
        output_path = args.output
        if not output_path:
            import uuid
            os.makedirs(settings.OUTPUTS_DIR, exist_ok=True)
            output_path = str(settings.OUTPUTS_DIR / f"tts_{uuid.uuid4().hex[:8]}.wav")
        else:
            os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)

        import asyncio
        kwargs = {}
        if args.profile:
            kwargs["profile_path"] = args.profile

        ok = asyncio.run(engine.generate_audio(
            text=args.text,
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
        engine = get_stt_engine("whisper")
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

def main():
    parser = argparse.ArgumentParser(description="TailAdmin Voice Core CLI")
    subparsers = parser.add_subparsers(dest="command", required=True)

    # TTS
    tts_parser = subparsers.add_parser("tts")
    tts_parser.add_argument("--text", required=True)
    tts_parser.add_argument("--engine", default="piper-tr")
    tts_parser.add_argument("--language", default="tr")
    tts_parser.add_argument("--output", default=None)
    tts_parser.add_argument("--profile", default=None)

    # STT
    stt_parser = subparsers.add_parser("stt")
    stt_parser.add_argument("--audio", required=True)
    stt_parser.add_argument("--language", default="tr")
    stt_parser.add_argument("--model-size", dest="model_size", default=None)

    # Download
    dl_parser = subparsers.add_parser("download")
    dl_parser.add_argument("--model", required=True)
    dl_parser.add_argument("--token", default=None)

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
    elif args.command == "models":
        sys.exit(models_command(args))
    elif args.command == "system":
        sys.exit(system_command(args))

if __name__ == "__main__":
    main()
