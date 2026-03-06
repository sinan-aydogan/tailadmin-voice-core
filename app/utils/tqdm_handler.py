import os
from tqdm import tqdm
from loguru import logger
from app.database import SessionLocal
from app.downloader.download_manager import DownloadManager
from app.downloader.model_registry import AVAILABLE_MODELS

# Global mapping of repo_id -> model_id
# This helps us identify which model a download belongs to
REPO_TO_MODEL = {m["repo_id"]: m["id"] for m in AVAILABLE_MODELS}

class StatusTqdm(tqdm):
    """
    A TQDM wrapper that updates the database with progress.
    """
    def __init__(self, *args, **kwargs):
        # Broad protection: filter kwargs to only those supported by tqdm
        import inspect
        from tqdm import tqdm as tqdm_base
        
        # Get valid tqdm __init__ arguments
        tqdm_args = inspect.signature(tqdm_base.__init__).parameters.keys()
        
        # Filter kwargs
        filtered_kwargs = {k: v for k, v in kwargs.items() if k in tqdm_args or k == "iterable"}
        
        # Explicitly ensure name is gone if it's there
        filtered_kwargs.pop("name", None)
        
        # Initialize early to avoid AttributeError if super().__init__ fails
        self._model_id = None
        self._last_update_pct = 0
        
        super().__init__(*args, **filtered_kwargs)
        self._model_id = self._find_model_id()

    def _find_model_id(self):
        # Prefer explicit env var
        if "CURRENT_DOWNLOAD_MODEL_ID" in os.environ:
            return os.environ["CURRENT_DOWNLOAD_MODEL_ID"]
            
        # Try to infer from desc (hf_hub sets desc to filename)
        # or from repo_id mapping
        desc = getattr(self, "desc", "")
        if not desc:
            return None
            
        # If desc is a repo_id or contains a known model_id
        for repo_id, model_id in REPO_TO_MODEL.items():
            if repo_id in desc or model_id in desc:
                return model_id
        return None

    def update(self, n=1):
        super().update(n)
        _id = getattr(self, "_model_id", None)
        if not _id or self.total is None or self.total == 0:
            return

        pct = (self.n / self.total) * 100
        # Only update DB if progress changed by at least 2% to avoid heavy DB load
        if pct - self._last_update_pct >= 2.0 or pct >= 100:
            self._last_update_pct = pct
            try:
                db = SessionLocal()
                DownloadManager.update_progress(db, _id, "downloading", progress=round(pct, 1))
                db.close()
            except Exception:
                pass

    def close(self):
        super().close()
        _id = getattr(self, "_model_id", None)
        if _id and self.n >= (self.total or 0) and self.total:
            try:
                db = SessionLocal()
                DownloadManager.update_progress(db, _id, "completed", progress=100.0)
                db.close()
            except:
                pass

def patch_huggingface_tqdm():
    """
    Monkey-patch huggingface_hub to use our custom TQDM status reporter.
    """
    try:
        import sys
        
        # 1. Patch in huggingface_hub.utils (where most codes import it from)
        import huggingface_hub.utils as hf_utils
        if hasattr(hf_utils, "tqdm") and hf_utils.tqdm != StatusTqdm:
            hf_utils.tqdm = StatusTqdm
            
        # 2. Patch the module itself in sys.modules if it exists
        if "huggingface_hub.utils.tqdm" in sys.modules:
            mod = sys.modules["huggingface_hub.utils.tqdm"]
            # If it's a module, it might have a .tqdm class
            if hasattr(mod, "tqdm") and mod.tqdm != StatusTqdm:
                mod.tqdm = StatusTqdm
            # If the module itself is being used as the class (via some magic or old versions)
            # we already covered it via hf_utils.tqdm assignment or it's not possible to patch via attribute
            
        logger.info("Global HuggingFace TQDM patch applied for live progress tracking.")
    except Exception as e:
        logger.warning(f"Failed to patch HuggingFace TQDM: {e}")
