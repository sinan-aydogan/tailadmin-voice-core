"""
HuggingFace tqdm handling.

Progress reporting for downloads is owned by the byte-accurate disk poller in
`app.downloader.download_utils` (see `_ProgressPoller`). This module used to make
`StatusTqdm` write per-file progress to the DB via a process-global
`CURRENT_DOWNLOAD_MODEL_ID` env var — that raced across concurrent downloads (wrong
model got the progress) and reported jumpy per-file percentages. That DB-writing
logic has been removed; `StatusTqdm` is now a thin, console-only tqdm subclass kept
only so the monkey-patch has something to install without breaking HF internals.
"""
from tqdm import tqdm
from loguru import logger


class StatusTqdm(tqdm):
    """A tqdm subclass that safely tolerates HF's extra kwargs (e.g. ``name=``).

    Intentionally does NOT touch the database — download progress is tracked by the
    disk poller. This avoids the cross-download race and per-file jumpiness.
    """

    def __init__(self, *args, **kwargs):
        # HF sometimes passes kwargs (like ``name=``) that base tqdm rejects.
        import inspect
        from tqdm import tqdm as tqdm_base

        tqdm_args = inspect.signature(tqdm_base.__init__).parameters.keys()
        filtered_kwargs = {k: v for k, v in kwargs.items() if k in tqdm_args or k == "iterable"}
        filtered_kwargs.pop("name", None)
        super().__init__(*args, **filtered_kwargs)


def patch_huggingface_tqdm():
    """Monkey-patch huggingface_hub to use our tolerant TQDM subclass.

    Kept mainly for kwarg-compatibility with older HF versions; progress itself is
    reported by the disk poller, not by tqdm.
    """
    try:
        import sys
        import huggingface_hub.utils as hf_utils

        if hasattr(hf_utils, "tqdm") and hf_utils.tqdm != StatusTqdm:
            hf_utils.tqdm = StatusTqdm

        if "huggingface_hub.utils.tqdm" in sys.modules:
            mod = sys.modules["huggingface_hub.utils.tqdm"]
            if hasattr(mod, "tqdm") and mod.tqdm != StatusTqdm:
                mod.tqdm = StatusTqdm

        logger.info("HuggingFace TQDM patch applied (console-only; progress via disk poller).")
    except Exception as e:
        logger.warning(f"Failed to patch HuggingFace TQDM: {e}")
