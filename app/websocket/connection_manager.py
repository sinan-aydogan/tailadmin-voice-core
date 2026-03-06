"""
WebSocket Connection Manager.
Manages active WebSocket connections and message broadcasting.
"""
import json
from typing import Dict, List, Optional
from fastapi import WebSocket, WebSocketDisconnect
from loguru import logger


class ConnectionManager:
    """Manages WebSocket connections for real-time updates."""
    
    def __init__(self):
        # Map of user_id to list of WebSocket connections
        self.active_connections: Dict[str, List[WebSocket]] = {}
        # Map of websocket to user_id for quick lookup
        self.connection_users: Dict[WebSocket, str] = {}
    
    async def connect(self, websocket: WebSocket, user_id: str):
        """Accept a new WebSocket connection."""
        await websocket.accept()
        
        if user_id not in self.active_connections:
            self.active_connections[user_id] = []
        
        self.active_connections[user_id].append(websocket)
        self.connection_users[websocket] = user_id
        
        logger.info(f"WebSocket connected for user {user_id}. Total connections: {len(self.active_connections[user_id])}")
    
    def disconnect(self, websocket: WebSocket):
        """Remove a WebSocket connection."""
        user_id = self.connection_users.get(websocket)
        
        if user_id and user_id in self.active_connections:
            if websocket in self.active_connections[user_id]:
                self.active_connections[user_id].remove(websocket)
            
            # Clean up empty user entries
            if not self.active_connections[user_id]:
                del self.active_connections[user_id]
        
        if websocket in self.connection_users:
            del self.connection_users[websocket]
        
        if user_id:
            logger.info(f"WebSocket disconnected for user {user_id}")
    
    async def send_personal_message(self, message: dict, user_id: str):
        """Send a message to all connections of a specific user."""
        if user_id not in self.active_connections:
            return
        
        disconnected = []
        for connection in self.active_connections[user_id]:
            try:
                await connection.send_json(message)
            except Exception as e:
                logger.error(f"Failed to send message to user {user_id}: {e}")
                disconnected.append(connection)
        
        # Clean up disconnected connections
        for conn in disconnected:
            self.disconnect(conn)
    
    async def broadcast(self, message: dict):
        """Broadcast a message to all connected users."""
        disconnected = []
        
        for user_id, connections in self.active_connections.items():
            for connection in connections:
                try:
                    await connection.send_json(message)
                except Exception as e:
                    logger.error(f"Failed to broadcast message: {e}")
                    disconnected.append(connection)
        
        # Clean up disconnected connections
        for conn in disconnected:
            self.disconnect(conn)
    
    async def send_to_all_except(self, message: dict, exclude_user_id: str):
        """Send a message to all users except one."""
        for user_id, connections in self.active_connections.items():
            if user_id == exclude_user_id:
                continue
            
            for connection in connections:
                try:
                    await connection.send_json(message)
                except Exception as e:
                    logger.error(f"Failed to send message: {e}")
    
    def get_user_connections(self, user_id: str) -> List[WebSocket]:
        """Get all connections for a specific user."""
        return self.active_connections.get(user_id, [])
    
    def is_user_connected(self, user_id: str) -> bool:
        """Check if a user has any active connections."""
        return user_id in self.active_connections and len(self.active_connections[user_id]) > 0
    
    def get_connection_count(self) -> int:
        """Get total number of active connections."""
        return sum(len(connections) for connections in self.active_connections.values())


# Global connection manager instance
manager = ConnectionManager()
