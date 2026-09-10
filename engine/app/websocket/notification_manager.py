"""
Notification Manager for WebSocket-based notifications.
Handles different types of notifications: downloads, TTS tasks, STT tasks, etc.
"""
import json
import asyncio
import threading
from datetime import datetime
from typing import Dict, List, Optional, Any
from enum import Enum
from loguru import logger

from app.websocket.connection_manager import manager


class NotificationType(str, Enum):
    # Download notifications
    DOWNLOAD_STARTED = "download_started"
    DOWNLOAD_PROGRESS = "download_progress"
    DOWNLOAD_COMPLETED = "download_completed"
    DOWNLOAD_FAILED = "download_failed"
    DOWNLOAD_FILE_PROGRESS = "download_file_progress"
    
    # Task notifications
    TASK_STARTED = "task_started"
    TASK_PROGRESS = "task_progress"
    TASK_COMPLETED = "task_completed"
    TASK_FAILED = "task_failed"
    
    # TTS specific
    TTS_STARTED = "tts_started"
    TTS_PROGRESS = "tts_progress"
    TTS_COMPLETED = "tts_completed"
    TTS_FAILED = "tts_failed"
    
    # STT specific
    STT_STARTED = "stt_started"
    STT_COMPLETED = "stt_completed"
    STT_FAILED = "stt_failed"
    
    # MusicGen specific
    MUSICGEN_STARTED = "musicgen_started"
    MUSICGEN_COMPLETED = "musicgen_completed"
    MUSICGEN_FAILED = "musicgen_failed"
    
    # System notifications
    SYSTEM_INFO = "system_info"
    SYSTEM_WARNING = "system_warning"
    SYSTEM_ERROR = "system_error"


class NotificationPriority(str, Enum):
    LOW = "low"
    NORMAL = "normal"
    HIGH = "high"
    URGENT = "urgent"


class NotificationManager:
    """Manages notifications and broadcasts them via WebSocket."""
    
    def __init__(self):
        # In-memory store for recent notifications (could be moved to Redis for multi-instance)
        self.recent_notifications: Dict[str, List[Dict]] = {}
        self.max_notifications_per_user = 100
        self._loop: Optional[asyncio.AbstractEventLoop] = None
        self._lock = threading.Lock()
        # Remote mode: when enabled (in a separate worker process), notifications are
        # forwarded to the API process over HTTP instead of delivered locally — because
        # the WebSocket connections live in the API process, not here.
        self._remote_mode = False
        self._remote_url: Optional[str] = None
        self._remote_token: Optional[str] = None

    def set_event_loop(self, loop: asyncio.AbstractEventLoop):
        """Set the event loop for thread-safe operations."""
        self._loop = loop

    def enable_remote_mode(self, api_notify_url: str, token: str):
        """Route notifications to the API process (call this only in the worker process)."""
        self._remote_mode = True
        self._remote_url = api_notify_url
        self._remote_token = token
        logger.info(f"NotificationManager remote mode enabled -> {api_notify_url}")

    async def _forward_to_api(self, user_id, notification_type, title, message, data, priority, store):
        """POST a notification to the API's /internal/notify (worker -> API bridge)."""
        try:
            import httpx
            payload = {
                "user_id": user_id,
                "type": notification_type.value if hasattr(notification_type, "value") else str(notification_type),
                "title": title,
                "message": message,
                "data": data or {},
                "priority": priority.value if hasattr(priority, "value") else str(priority),
                "store": store,
            }
            async with httpx.AsyncClient(timeout=5.0) as client:
                await client.post(self._remote_url, json=payload,
                                  headers={"X-Internal-Token": self._remote_token or ""})
        except Exception as e:
            # A dropped notification must never fail the task that emitted it.
            logger.debug(f"Notification forward to API failed: {e}")
    
    def _run_coroutine_threadsafe(self, coro):
        """Run a coroutine in the main event loop from a thread."""
        if self._loop and self._loop.is_running():
            try:
                asyncio.run_coroutine_threadsafe(coro, self._loop)
            except Exception as e:
                logger.error(f"Failed to run coroutine threadsafe: {e}")
        else:
            # Fallback: try to get current loop or create one
            try:
                loop = asyncio.get_event_loop()
                if loop.is_running():
                    loop.create_task(coro)
                else:
                    asyncio.run(coro)
            except Exception as e:
                logger.error(f"Failed to run coroutine: {e}")
    
    def _create_notification(
        self,
        notification_type: NotificationType,
        title: str,
        message: str,
        data: Optional[Dict] = None,
        priority: NotificationPriority = NotificationPriority.NORMAL
    ) -> Dict:
        """Create a notification object."""
        return {
            "id": f"{notification_type.value}_{datetime.utcnow().timestamp()}",
            "type": notification_type.value,
            "title": title,
            "message": message,
            "data": data or {},
            "priority": priority.value,
            "timestamp": datetime.utcnow().isoformat(),
            "read": False
        }
    
    def _store_notification(self, user_id: str, notification: Dict):
        """Store notification in memory for the user."""
        if user_id not in self.recent_notifications:
            self.recent_notifications[user_id] = []
        
        self.recent_notifications[user_id].insert(0, notification)
        
        # Keep only recent notifications
        if len(self.recent_notifications[user_id]) > self.max_notifications_per_user:
            self.recent_notifications[user_id] = self.recent_notifications[user_id][:self.max_notifications_per_user]
    
    async def send_notification(
        self,
        user_id: str,
        notification_type: NotificationType,
        title: str,
        message: str,
        data: Optional[Dict] = None,
        priority: NotificationPriority = NotificationPriority.NORMAL,
        store: bool = True
    ):
        """Send a notification to a specific user."""
        # In a separate worker process, hand off to the API which owns the WS sockets.
        if self._remote_mode:
            await self._forward_to_api(user_id, notification_type, title, message, data, priority, store)
            return

        notification = self._create_notification(
            notification_type=notification_type,
            title=title,
            message=message,
            data=data,
            priority=priority
        )

        if store:
            self._store_notification(user_id, notification)

        await manager.send_personal_message({
            "event": "notification",
            "payload": notification
        }, user_id)

        logger.debug(f"Sent {notification_type.value} notification to user {user_id}")
    
    async def broadcast_notification(
        self,
        notification_type: NotificationType,
        title: str,
        message: str,
        data: Optional[Dict] = None,
        priority: NotificationPriority = NotificationPriority.NORMAL
    ):
        """Broadcast a notification to all connected users."""
        notification = self._create_notification(
            notification_type=notification_type,
            title=title,
            message=message,
            data=data,
            priority=priority
        )
        
        await manager.broadcast({
            "event": "notification",
            "payload": notification
        })
        
        logger.debug(f"Broadcast {notification_type.value} notification to all users")
    
    # Convenience methods for specific notification types
    
    async def notify_download_started(self, user_id: str, model_id: str, model_name: str):
        """Notify that a download has started."""
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.DOWNLOAD_STARTED,
            title="Model İndirme Başladı",
            message=f"{model_name} indirilmeye başlandı.",
            data={"model_id": model_id, "model_name": model_name},
            priority=NotificationPriority.NORMAL
        )
    
    def notify_download_started_threadsafe(self, user_id: str, model_id: str, model_name: str):
        """Thread-safe version for notifying download start."""
        self._run_coroutine_threadsafe(
            self.notify_download_started(user_id, model_id, model_name)
        )
    
    async def notify_download_progress(
        self,
        user_id: str,
        model_id: str,
        model_name: str,
        progress: float,
        current_file: Optional[str] = None
    ):
        """Notify download progress."""
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.DOWNLOAD_PROGRESS,
            title="İndirme Devam Ediyor",
            message=f"{model_name} - %{progress:.1f} tamamlandı",
            data={
                "model_id": model_id,
                "model_name": model_name,
                "progress": progress,
                "current_file": current_file
            },
            priority=NotificationPriority.LOW,
            store=False  # Don't store progress updates to avoid flooding
        )
    
    def notify_download_progress_threadsafe(
        self,
        user_id: str,
        model_id: str,
        model_name: str,
        progress: float,
        current_file: Optional[str] = None
    ):
        """Thread-safe version for notifying download progress (from tqdm threads)."""
        self._run_coroutine_threadsafe(
            self.notify_download_progress(user_id, model_id, model_name, progress, current_file)
        )

    async def notify_download_file_progress(
        self,
        user_id: str,
        model_id: str,
        file_name: str,
        file_progress: float,
        downloaded_bytes: int,
        total_bytes: int
    ):
        """Notify progress for a specific file."""
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.DOWNLOAD_FILE_PROGRESS,
            title="Dosya İndiriliyor",
            message=f"{file_name} - %{file_progress:.1f}",
            data={
                "model_id": model_id,
                "file_name": file_name,
                "progress": file_progress,
                "downloaded_bytes": downloaded_bytes,
                "total_bytes": total_bytes
            },
            priority=NotificationPriority.LOW,
            store=False
        )
    
    async def notify_download_completed(self, user_id: str, model_id: str, model_name: str):
        """Notify that a download has completed."""
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.DOWNLOAD_COMPLETED,
            title="İndirme Tamamlandı",
            message=f"{model_name} başarıyla indirildi ve kullanıma hazır.",
            data={"model_id": model_id, "model_name": model_name},
            priority=NotificationPriority.NORMAL
        )
    
    def notify_download_completed_threadsafe(self, user_id: str, model_id: str, model_name: str):
        """Thread-safe version for notifying download completion."""
        self._run_coroutine_threadsafe(
            self.notify_download_completed(user_id, model_id, model_name)
        )
    
    async def notify_download_failed(self, user_id: str, model_id: str, model_name: str, error: str):
        """Notify that a download has failed."""
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.DOWNLOAD_FAILED,
            title="İndirme Başarısız",
            message=f"{model_name} indirilemedi: {error}",
            data={"model_id": model_id, "model_name": model_name, "error": error},
            priority=NotificationPriority.HIGH
        )
    
    def notify_download_failed_threadsafe(self, user_id: str, model_id: str, model_name: str, error: str):
        """Thread-safe version for notifying download failure."""
        self._run_coroutine_threadsafe(
            self.notify_download_failed(user_id, model_id, model_name, error)
        )
    
    async def notify_task_started(self, user_id: str, task_id: int, task_type: str, details: str = ""):
        """Notify that a task has started."""
        type_labels = {"tts": "Ses Üretimi", "stt": "Sesten Metne"}
        label = type_labels.get(task_type, task_type.upper())
        
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.TASK_STARTED,
            title=f"{label} Başladı",
            message=details or f"Görev #{task_id} kuyruğa eklendi.",
            data={"task_id": task_id, "task_type": task_type},
            priority=NotificationPriority.NORMAL
        )
    
    async def notify_task_completed(
        self,
        user_id: str,
        task_id: int,
        task_type: str,
        result_data: Optional[Dict] = None
    ):
        """Notify that a task has completed."""
        type_labels = {"tts": "Ses Üretimi", "stt": "Sesten Metne"}
        label = type_labels.get(task_type, task_type.upper())
        
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.TASK_COMPLETED,
            title=f"{label} Tamamlandı",
            message=f"Görev #{task_id} başarıyla tamamlandı.",
            data={"task_id": task_id, "task_type": task_type, **(result_data or {})},
            priority=NotificationPriority.NORMAL
        )
    
    async def notify_task_failed(self, user_id: str, task_id: int, task_type: str, error: str):
        """Notify that a task has failed."""
        type_labels = {"tts": "Ses Üretimi", "stt": "Sesten Metne"}
        label = type_labels.get(task_type, task_type.upper())
        
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.TASK_FAILED,
            title=f"{label} Başarısız",
            message=f"Görev #{task_id} başarısız oldu: {error}",
            data={"task_id": task_id, "task_type": task_type, "error": error},
            priority=NotificationPriority.HIGH
        )
    
    # MusicGen specific notifications
    async def notify_musicgen_started(self, user_id: str, text: str, model_size: str):
        """Notify that MusicGen generation has started."""
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.MUSICGEN_STARTED,
            title="Müzik Üretimi Başladı",
            message=f"'{text[:50]}...' için müzik üretiliyor",
            data={"text": text, "model_size": model_size, "status": "started"},
            priority=NotificationPriority.NORMAL
        )
    
    def notify_musicgen_started_threadsafe(self, user_id: str, text: str, model_size: str):
        """Thread-safe version for notifying MusicGen start."""
        self._run_coroutine_threadsafe(
            self.notify_musicgen_started(user_id, text, model_size)
        )
    
    async def notify_musicgen_completed(self, user_id: str, text: str, model_size: str, output_path: str):
        """Notify that MusicGen generation has completed."""
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.MUSICGEN_COMPLETED,
            title="Müzik Üretimi Tamamlandı",
            message=f"'{text[:50]}...' için müzik başarıyla üretildi",
            data={"text": text, "model_size": model_size, "output_path": output_path, "status": "completed"},
            priority=NotificationPriority.NORMAL
        )
    
    def notify_musicgen_completed_threadsafe(self, user_id: str, text: str, model_size: str, output_path: str):
        """Thread-safe version for notifying MusicGen completion."""
        self._run_coroutine_threadsafe(
            self.notify_musicgen_completed(user_id, text, model_size, output_path)
        )
    
    async def notify_musicgen_failed(self, user_id: str, text: str, model_size: str, error: str):
        """Notify that MusicGen generation has failed."""
        await self.send_notification(
            user_id=user_id,
            notification_type=NotificationType.MUSICGEN_FAILED,
            title="Müzik Üretimi Başarısız",
            message=f"'{text[:50]}...' için müzik üretilemedi: {error}",
            data={"text": text, "model_size": model_size, "error": error, "status": "failed"},
            priority=NotificationPriority.HIGH
        )
    
    def notify_musicgen_failed_threadsafe(self, user_id: str, text: str, model_size: str, error: str):
        """Thread-safe version for notifying MusicGen failure."""
        self._run_coroutine_threadsafe(
            self.notify_musicgen_failed(user_id, text, model_size, error)
        )
    
    def get_user_notifications(self, user_id: str, unread_only: bool = False) -> List[Dict]:
        """Get notifications for a user."""
        notifications = self.recent_notifications.get(user_id, [])
        
        if unread_only:
            notifications = [n for n in notifications if not n.get("read", False)]
        
        return notifications
    
    def mark_notification_read(self, user_id: str, notification_id: str) -> bool:
        """Mark a notification as read."""
        notifications = self.recent_notifications.get(user_id, [])
        
        for notification in notifications:
            if notification.get("id") == notification_id:
                notification["read"] = True
                return True
        
        return False
    
    def mark_all_read(self, user_id: str):
        """Mark all notifications as read for a user."""
        notifications = self.recent_notifications.get(user_id, [])
        
        for notification in notifications:
            notification["read"] = True
    
    def clear_notifications(self, user_id: str):
        """Clear all notifications for a user."""
        if user_id in self.recent_notifications:
            self.recent_notifications[user_id] = []


# Global notification manager instance
notification_manager = NotificationManager()
