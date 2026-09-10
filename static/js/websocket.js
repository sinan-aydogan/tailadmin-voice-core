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

        this.currentToken = token;
        this.isConnecting = true;
        // WS base comes from the dynamic runtime config (config.js); falls back to
        // the current host on :5001 if config didn't load.
        const wsBase = (window.VOICE_CORE_CONFIG && window.VOICE_CORE_CONFIG.wsBase)
            || ((location.protocol === 'https:' ? 'wss://' : 'ws://') + location.hostname + ':5001');
        const wsUrl = `${wsBase}/ws/notifications?token=${token}`;

        try {
            this.ws = new WebSocket(wsUrl);

            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.isConnected = true;
                this.isConnecting = false;
                this.reconnectAttempts = 0;
                this.emit('connected', {});
                
                // Update header status
                this.updateHeaderStatus(true);
                
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
                console.log('WebSocket closed:', event.code, event.reason, 'wasClean:', event.wasClean);
                this.isConnected = false;
                this.isConnecting = false;
                this.stopHeartbeat();
                
                // Update header status
                this.updateHeaderStatus(false);
                
                // 1001 = Going Away (normal closure like page refresh)
                // 1000 = Normal closure
                // Don't reconnect immediately for normal closures
                const isNormalClosure = event.code === 1000 || event.code === 1001;
                
                if (!event.wasClean && !isNormalClosure && this.reconnectAttempts < this.maxReconnectAttempts) {
                    this.scheduleReconnect();
                } else if (isNormalClosure && this.reconnectAttempts < this.maxReconnectAttempts) {
                    // For normal closures, wait a bit longer before reconnecting
                    setTimeout(() => this.connect(), 2000);
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
                // Heartbeat response - connection is alive
                this.lastPongTime = Date.now();
                break;
                
            case 'ping':
                // Server is checking if we're alive, respond with pong
                this.send({ action: 'ping' });
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

    /**
     * Update WebSocket status in header
     */
    updateHeaderStatus(isConnected) {
        const indicator = document.getElementById('headerWsIndicator');
        const text = document.getElementById('headerWsText');
        const container = document.getElementById('headerWsStatus');
        
        if (indicator && text) {
            if (isConnected) {
                indicator.className = 'w-2 h-2 rounded-full bg-success animate-pulse';
                indicator.style.backgroundColor = 'hsl(142, 76%, 36%)';
                text.textContent = 'Canlı';
                text.className = 'text-success';
                if (container) {
                    container.className = 'hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-success/10 text-xs';
                }
            } else {
                indicator.className = 'w-2 h-2 rounded-full bg-destructive';
                indicator.style.backgroundColor = 'hsl(0, 84%, 60%)';
                text.textContent = 'Bağlı Değil';
                text.className = 'text-destructive';
                if (container) {
                    container.className = 'hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-destructive/10 text-xs';
                }
            }
        }
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

// Reconnect when token changes (but avoid reconnecting if already connected with same token)
window.addEventListener('storage', (e) => {
    if (e.key === 'vc_token') {
        if (e.newValue) {
            // Only reconnect if not already connected or token actually changed
            if (!wsClient.isConnected || wsClient.currentToken !== e.newValue) {
                wsClient.currentToken = e.newValue;
                wsClient.disconnect();
                setTimeout(() => wsClient.connect(), 100);
            }
        } else {
            wsClient.currentToken = null;
            wsClient.disconnect();
        }
    }
});
