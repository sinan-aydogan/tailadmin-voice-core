import os
import psutil
from fastapi import APIRouter, Depends

from app.config import settings

router = APIRouter(prefix="/system", tags=["system"])


# One Process handle reused across calls; cpu_percent(None) is non-blocking and
# reports usage since the previous call.
_proc = psutil.Process(os.getpid())
# Prime the CPU counters so the first real reading isn't 0.0.
psutil.cpu_percent(interval=None)
_proc.cpu_percent(interval=None)


def collect_system_stats() -> dict:
    """Snapshot current resource usage. Non-blocking (no interval sleeps)."""
    vm = psutil.virtual_memory()

    # Disk usage of the volume where models live is the most relevant to the user.
    try:
        disk_target = str(settings.MODELS_DIR)
        if not os.path.exists(disk_target):
            disk_target = os.path.abspath(os.sep)
        du = psutil.disk_usage(disk_target)
        disk = {
            "used_gb": round(du.used / (1024 ** 3), 1),
            "total_gb": round(du.total / (1024 ** 3), 1),
            "pct": round(du.percent, 1),
        }
    except Exception:
        disk = {"used_gb": 0.0, "total_gb": 0.0, "pct": 0.0}

    stats = {
        "cpu_pct": round(psutil.cpu_percent(interval=None), 1),
        "ram": {
            "used_gb": round((vm.total - vm.available) / (1024 ** 3), 1),
            "total_gb": round(vm.total / (1024 ** 3), 1),
            "pct": round(vm.percent, 1),
        },
        "process_ram_gb": round(_proc.memory_info().rss / (1024 ** 3), 2),
        "disk": disk,
        "gpu": _collect_gpu(),
    }
    return stats


def _collect_gpu() -> dict:
    """Best-effort GPU/accelerator memory info (MPS or CUDA)."""
    try:
        import torch
        if torch.backends.mps.is_available():
            allocated = 0.0
            try:
                allocated = torch.mps.current_allocated_memory() / (1024 ** 3)
            except Exception:
                pass
            return {"backend": "mps", "allocated_gb": round(allocated, 2)}
        if torch.cuda.is_available():
            return {
                "backend": "cuda",
                "allocated_gb": round(torch.cuda.memory_allocated() / (1024 ** 3), 2),
            }
    except Exception:
        pass
    return {"backend": "cpu", "allocated_gb": 0.0}


@router.get("/stats")
async def get_system_stats():
    """Live system resource usage for the footer widget."""
    return collect_system_stats()

