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
    
    def set_event_loop(self, loop: asyncio.AbstractEventLoop):
        """Set the event loop for thread-safe operations."""
        self._loop = loop
    
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
