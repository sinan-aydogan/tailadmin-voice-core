/**
 * Notification System for Voice Core
 * Manages UI notifications and integrates with WebSocket
 */

class NotificationSystem {
    constructor() {
        this.container = null;
        this.badge = null;
        this.dropdown = null;
        this.isDropdownOpen = false;
        this.activeTab = 'all';
        this.init();
    }

    /**
     * Initialize notification system
     */
    init() {
        // Create notification container if it doesn't exist
        this.createUI();
        
        // Listen for WebSocket notifications
        if (typeof wsClient !== 'undefined') {
            wsClient.on('notification', (notification) => {
                this.handleNotification(notification);
            });
            
            wsClient.on('connected', () => {
                this.showToast('Bağlantı kuruldu', 'success', 2000);
            });
            
            wsClient.on('disconnected', () => {
                this.showToast('Bağlantı kesildi', 'warning', 3000);
            });
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (this.isDropdownOpen && !this.container.contains(e.target)) {
                this.closeDropdown();
            }
        });
    }

    /**
     * Create notification UI
     */
    createUI() {
        // Find or create notification button in header
        const headerRight = document.querySelector('header .flex.items-center.gap-3');
        if (!headerRight) return;

        // Create notification container
        this.container = document.createElement('div');
        this.container.className = 'relative';
        this.container.id = 'notificationSystem';

        // Create notification button
        this.container.innerHTML = `
            <button id="notificationBtn" class="relative flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-background hover:bg-accent transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-foreground">
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                    <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                </svg>
                <span id="notificationBadge" class="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-destructive text-destructive-foreground text-xs font-medium flex items-center justify-center hidden">0</span>
            </button>
            
            <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-96 rounded-lg border border-border bg-popover shadow-lg z-50 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-border bg-muted/50">
                    <h3 class="font-semibold text-foreground">Bildirimler</h3>
                    <div class="flex items-center gap-2">
                        <button id="markAllReadBtn" class="text-xs text-muted-foreground hover:text-foreground transition-colors">
                            Tümünü Okundu İşaretle
                        </button>
                        <button id="clearAllBtn" class="text-xs text-destructive hover:text-destructive/80 transition-colors">
                            Temizle
                        </button>
                    </div>
                </div>
                
                <div class="flex border-b border-border">
                    <button class="notification-tab flex-1 px-4 py-2 text-sm font-medium text-foreground border-b-2 border-primary" data-tab="all">
                        Tümü
                    </button>
                    <button class="notification-tab flex-1 px-4 py-2 text-sm font-medium text-muted-foreground border-b-2 border-transparent hover:text-foreground" data-tab="active">
                        Devam Eden
                    </button>
                    <button class="notification-tab flex-1 px-4 py-2 text-sm font-medium text-muted-foreground border-b-2 border-transparent hover:text-foreground" data-tab="completed">
                        Tamamlanan
                    </button>
                </div>
                
                <div id="notificationList" class="max-h-96 overflow-y-auto">
                    <div class="p-8 text-center text-muted-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-3 opacity-50">
                            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                        </svg>
                        <p class="text-sm">Henüz bildirim yok</p>
                    </div>
                </div>
            </div>
        `;

        // Insert before user dropdown
        const userDropdown = headerRight.querySelector('.relative');
        if (userDropdown) {
            headerRight.insertBefore(this.container, userDropdown);
        } else {
            headerRight.appendChild(this.container);
        }

        // Cache elements
        this.badge = this.container.querySelector('#notificationBadge');
        this.dropdown = this.container.querySelector('#notificationDropdown');
        this.list = this.container.querySelector('#notificationList');

        // Bind events
        this.container.querySelector('#notificationBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });

        this.container.querySelector('#markAllReadBtn').addEventListener('click', () => {
            this.markAllAsRead();
        });

        this.container.querySelector('#clearAllBtn').addEventListener('click', () => {
            this.clearAll();
        });

        // Tab switching
        this.container.querySelectorAll('.notification-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                this.switchTab(tab.dataset.tab);
            });
        });
    }

    /**
     * Handle incoming notification
     */
    handleNotification(notification) {
        // Update badge
        this.updateBadge();
        
        // Show toast for important notifications
        if (notification.priority === 'high' || notification.priority === 'urgent') {
            this.showToast(notification.message, this.getTypeFromNotification(notification.type));
        }
        
        // Play sound for completed tasks (optional)
        if (notification.type.includes('completed')) {
            this.playNotificationSound();
        }
        
        // Refresh list if dropdown is open
        if (this.isDropdownOpen) {
            this.renderList();
        }
    }

    /**
     * Get toast type from notification type
     */
    getTypeFromNotification(type) {
        if (type.includes('failed') || type.includes('error')) return 'error';
        if (type.includes('completed')) return 'success';
        if (type.includes('progress')) return 'info';
        return 'info';
    }

    /**
     * Play notification sound
     */
    playNotificationSound() {
        // Optional: Add sound notification
        // const audio = new Audio('/static/sounds/notification.mp3');
        // audio.play().catch(() => {});
    }

    /**
     * Update notification badge
     */
    updateBadge() {
        if (typeof wsClient !== 'undefined') {
            const count = wsClient.getUnreadCount();
            if (count > 0) {
                this.badge.textContent = count > 99 ? '99+' : count;
                this.badge.classList.remove('hidden');
            } else {
                this.badge.classList.add('hidden');
            }
        }
    }

    /**
     * Toggle dropdown
     */
    toggleDropdown() {
        if (this.isDropdownOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }

    /**
     * Open dropdown
     */
    openDropdown() {
        this.isDropdownOpen = true;
        this.dropdown.classList.remove('hidden');
        this.renderList();
        
        // Mark visible notifications as read
        if (typeof wsClient !== 'undefined') {
            wsClient.markAllAsRead();
            this.updateBadge();
        }
    }

    /**
     * Close dropdown
     */
    closeDropdown() {
        this.isDropdownOpen = false;
        this.dropdown.classList.add('hidden');
    }

    /**
     * Switch tab
     */
    switchTab(tab) {
        this.activeTab = tab;
        
        // Update tab styles
        this.container.querySelectorAll('.notification-tab').forEach(t => {
            if (t.dataset.tab === tab) {
                t.classList.add('border-primary', 'text-foreground');
                t.classList.remove('border-transparent', 'text-muted-foreground');
            } else {
                t.classList.remove('border-primary', 'text-foreground');
                t.classList.add('border-transparent', 'text-muted-foreground');
            }
        });
        
        this.renderList();
    }

    /**
     * Render notification list
     */
    renderList() {
        if (typeof wsClient === 'undefined') return;
        
        let notifications = wsClient.getNotifications();
        
        // Filter by tab
        if (this.activeTab === 'active') {
            notifications = notifications.filter(n => 
                n.type.includes('started') || n.type.includes('progress') || n.type.includes('downloading')
            );
        } else if (this.activeTab === 'completed') {
            notifications = notifications.filter(n => 
                n.type.includes('completed') || n.type.includes('failed')
            );
        }
        
        if (notifications.length === 0) {
            this.list.innerHTML = `
                <div class="p-8 text-center text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-3 opacity-50">
                        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                    </svg>
                    <p class="text-sm">${this.activeTab === 'all' ? 'Henüz bildirim yok' : 'Bu kategoride bildirim yok'}</p>
                </div>
            `;
            return;
        }
        
        this.list.innerHTML = notifications.map(n => this.renderNotificationItem(n)).join('');
        
        // Bind item actions
        this.list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = item.dataset.id;
                this.handleNotificationClick(id);
            });
        });
    }

    /**
     * Render single notification item
     */
    renderNotificationItem(notification) {
        const icon = this.getNotificationIcon(notification.type);
        const colorClass = this.getNotificationColorClass(notification.type);
        const time = this.formatTime(notification.timestamp);
        const hasProgress = notification.data?.progress !== undefined;
        
        return `
            <div class="notification-item p-4 border-b border-border hover:bg-muted/50 cursor-pointer transition-colors ${notification.read ? 'opacity-60' : ''}" data-id="${notification.id}">
                <div class="flex gap-3">
                    <div class="flex-shrink-0 mt-0.5">
                        <div class="w-8 h-8 rounded-full ${colorClass} flex items-center justify-center">
                            ${icon}
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-foreground">${notification.title}</p>
                        <p class="text-xs text-muted-foreground mt-0.5 line-clamp-2">${notification.message}</p>
                        ${hasProgress ? `
                            <div class="mt-2">
                                <div class="progress">
                                    <div class="progress-bar" style="width: ${notification.data.progress}%"></div>
                                </div>
                                <p class="text-xs text-muted-foreground mt-1">%${notification.data.progress.toFixed(1)}</p>
                            </div>
                        ` : ''}
                        <p class="text-xs text-muted-foreground mt-1">${time}</p>
                    </div>
                    ${!notification.read ? `
                        <div class="flex-shrink-0">
                            <div class="w-2 h-2 rounded-full bg-primary"></div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Get icon for notification type
     */
    getNotificationIcon(type) {
        const icons = {
            download: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>`,
            task: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>`,
            tts: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>`,
            stt: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>`,
            success: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
            error: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg>`,
            info: `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/></svg>`
        };
        
        if (type.includes('download')) return icons.download;
        if (type.includes('tts')) return icons.tts;
        if (type.includes('stt')) return icons.stt;
        if (type.includes('task')) return icons.task;
        if (type.includes('completed') || type.includes('success')) return icons.success;
        if (type.includes('failed') || type.includes('error')) return icons.error;
        return icons.info;
    }

    /**
     * Get color class for notification type
     */
    getNotificationColorClass(type) {
        if (type.includes('completed') || type.includes('success')) return 'bg-success text-success-foreground';
        if (type.includes('failed') || type.includes('error')) return 'bg-destructive text-destructive-foreground';
        if (type.includes('progress') || type.includes('started')) return 'bg-primary text-primary-foreground';
        return 'bg-muted text-muted-foreground';
    }

    /**
     * Format timestamp
     */
    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        // Less than a minute
        if (diff < 60000) return 'Az önce';
        
        // Less than an hour
        if (diff < 3600000) {
            const minutes = Math.floor(diff / 60000);
            return `${minutes} dakika önce`;
        }
        
        // Less than a day
        if (diff < 86400000) {
            const hours = Math.floor(diff / 3600000);
            return `${hours} saat önce`;
        }
        
        // More than a day
        return date.toLocaleDateString('tr-TR');
    }

    /**
     * Handle notification click
     */
    handleNotificationClick(notificationId) {
        // Mark as read
        if (typeof wsClient !== 'undefined') {
            wsClient.markAsRead(notificationId);
        }
        
        // Navigate based on notification type
        const notification = wsClient?.getNotifications().find(n => n.id === notificationId);
        if (notification) {
            if (notification.type.includes('download')) {
                window.location.hash = '#models';
            } else if (notification.type.includes('tts')) {
                window.location.hash = '#tts?tab=historyTab';
            } else if (notification.type.includes('stt')) {
                window.location.hash = '#stt';
            } else if (notification.type.includes('task')) {
                window.location.hash = '#queue';
            }
        }
        
        this.closeDropdown();
        this.updateBadge();
        this.renderList();
    }

    /**
     * Mark all as read
     */
    markAllAsRead() {
        if (typeof wsClient !== 'undefined') {
            wsClient.markAllAsRead();
            this.updateBadge();
            this.renderList();
        }
    }

    /**
     * Clear all notifications
     */
    clearAll() {
        if (typeof wsClient !== 'undefined') {
            wsClient.clearNotifications();
            this.updateBadge();
            this.renderList();
        }
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        
        const colors = {
            success: 'bg-success text-success-foreground border-success',
            error: 'bg-destructive text-destructive-foreground border-destructive',
            warning: 'bg-warning text-warning-foreground border-warning',
            info: 'bg-primary text-primary-foreground border-primary'
        };
        
        const icons = {
            success: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
            error: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg>',
            warning: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>',
            info: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/></svg>'
        };
        
        toast.className = `fixed bottom-4 right-4 z-50 flex items-center gap-3 px-4 py-3 rounded-lg border shadow-lg animate-slide-in ${colors[type] || colors.info}`;
        toast.innerHTML = `
            ${icons[type] || icons.info}
            <span class="text-sm font-medium">${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
}

// Global notification system instance
const notificationSystem = new NotificationSystem();
