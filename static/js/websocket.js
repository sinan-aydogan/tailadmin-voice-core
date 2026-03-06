/**
 * WebSocket Client for Voice Core
 * Handles real-time notifications and updates
 */

class WebSocketClient {
    constructor() {
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.isConnecting = false;
        this.listeners = new Map();
        this.pendingNotifications = [];
        this.isConnected = false;
    }

    /**
     * Connect to WebSocket server
     */
    connect() {
        if (this.isConnecting || this.ws?.readyState === WebSocket.OPEN) {
            return;
        }

        const token = localStorage.getItem('vc_token');
        if (!token) {
            console.warn('No token available for WebSocket connection');
            return;
        }

        this.isConnecting = true;
        const wsUrl = `ws://localhost:5001/ws/notifications?token=${token}`;

        try {
            this.ws = new WebSocket(wsUrl);

            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.isConnected = true;
                this.isConnecting = false;
                this.reconnectAttempts = 0;
                this.emit('connected', {});
                
                // Start heartbeat
                this.startHeartbeat();
            };

            this.ws.onmessage = (event) => {
                try {
                    const message = JSON.parse(event.data);
                    this.handleMessage(message);
                } catch (e) {
                    console.error('Failed to parse WebSocket message:', e);
                }
            };

            this.ws.onclose = (event) => {
                console.log('WebSocket closed:', event.code, event.reason);
                this.isConnected = false;
                this.isConnecting = false;
                this.stopHeartbeat();
                
                if (!event.wasClean && this.reconnectAttempts < this.maxReconnectAttempts) {
                    this.scheduleReconnect();
                }
                
                this.emit('disconnected', { code: event.code, reason: event.reason });
            };

            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.emit('error', error);
            };

        } catch (e) {
            console.error('Failed to create WebSocket connection:', e);
            this.isConnecting = false;
        }
    }

    /**
     * Handle incoming WebSocket messages
     */
    handleMessage(message) {
        const { event, payload } = message;
        
        switch (event) {
            case 'connected':
                console.log('Server confirmed connection:', payload);
                break;
                
            case 'pong':
                // Heartbeat response
                break;
                
            case 'notification':
                this.handleNotification(payload);
                break;
                
            case 'pending_notifications':
                // Load pending notifications
                if (Array.isArray(payload)) {
                    payload.forEach(notification => {
                        this.pendingNotifications.push(notification);
                    });
                    this.emit('pending_notifications_loaded', payload);
                }
                break;
                
            default:
                console.log('Unknown event type:', event, payload);
        }
        
        // Emit event for listeners
        this.emit(event, payload);
    }

    /**
     * Handle notification messages
     */
    handleNotification(notification) {
        console.log('Received notification:', notification);
        
        // Store notification
        this.pendingNotifications.unshift(notification);
        
        // Keep only last 50 notifications
        if (this.pendingNotifications.length > 50) {
            this.pendingNotifications = this.pendingNotifications.slice(0, 50);
        }
        
        // Emit notification event
        this.emit('notification', notification);
        
        // Emit specific notification type event
        this.emit(`notification:${notification.type}`, notification);
    }

    /**
     * Schedule reconnection attempt
     */
    scheduleReconnect() {
        this.reconnectAttempts++;
        const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);
        
        console.log(`Scheduling reconnect attempt ${this.reconnectAttempts} in ${delay}ms`);
        
        setTimeout(() => {
            this.connect();
        }, delay);
    }

    /**
     * Start heartbeat to keep connection alive
     */
    startHeartbeat() {
        this.heartbeatInterval = setInterval(() => {
            if (this.ws?.readyState === WebSocket.OPEN) {
                this.send({ action: 'ping' });
            }
        }, 30000); // Every 30 seconds
    }

    /**
     * Stop heartbeat
     */
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }

    /**
     * Send message to server
     */
    send(data) {
        if (this.ws?.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
            return true;
        }
        return false;
    }

    /**
     * Disconnect from WebSocket server
     */
    disconnect() {
        this.stopHeartbeat();
        if (this.ws) {
            this.ws.close(1000, 'Client disconnecting');
            this.ws = null;
        }
        this.isConnected = false;
        this.isConnecting = false;
    }

    /**
     * Add event listener
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
        
        // Return unsubscribe function
        return () => {
            const callbacks = this.listeners.get(event);
            if (callbacks) {
                const index = callbacks.indexOf(callback);
                if (index > -1) {
                    callbacks.splice(index, 1);
                }
            }
        };
    }

    /**
     * Emit event to listeners
     */
    emit(event, data) {
        const callbacks = this.listeners.get(event);
        if (callbacks) {
            callbacks.forEach(callback => {
                try {
                    callback(data);
                } catch (e) {
                    console.error('Error in event listener:', e);
                }
            });
        }
    }

    /**
     * Mark notification as read
     */
    markAsRead(notificationId) {
        return this.send({ action: 'mark_read', notification_id: notificationId });
    }

    /**
     * Mark all notifications as read
     */
    markAllAsRead() {
        this.pendingNotifications.forEach(n => n.read = true);
        return this.send({ action: 'mark_all_read' });
    }

    /**
     * Clear all notifications
     */
    clearNotifications() {
        this.pendingNotifications = [];
        return this.send({ action: 'clear_notifications' });
    }

    /**
     * Get unread notifications count
     */
    getUnreadCount() {
        return this.pendingNotifications.filter(n => !n.read).length;
    }

    /**
     * Get all notifications
     */
    getNotifications() {
        return [...this.pendingNotifications];
    }

    /**
     * Get notifications by type
     */
    getNotificationsByType(type) {
        return this.pendingNotifications.filter(n => n.type === type);
    }

    /**
     * Check if connected
     */
    getConnectionStatus() {
        return {
            isConnected: this.isConnected,
            isConnecting: this.isConnecting,
            reconnectAttempts: this.reconnectAttempts
        };
    }
}

// Global WebSocket client instance
const wsClient = new WebSocketClient();

// Auto-connect when token is available
document.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('vc_token');
    if (token) {
        // Delay connection slightly to ensure everything is loaded
        setTimeout(() => wsClient.connect(), 500);
    }
});

// Reconnect when token changes
window.addEventListener('storage', (e) => {
    if (e.key === 'vc_token') {
        if (e.newValue) {
            wsClient.connect();
        } else {
            wsClient.disconnect();
        }
    }
});
