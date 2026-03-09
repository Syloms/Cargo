/**
 * ============================================================================
 * ADMIN NOTIFICATION SYSTEM
 * Complete notification management for admin panel
 * ============================================================================
 */

class AdminNotifications {
    constructor() {
        this.unreadCount = 0;
        this.notifications = [];
        this.panel = null;
        this.badge = null;
        this.isOpen = false;
        this.refreshInterval = null;
        
        this.init();
    }
    
    init() {
        this.createNotificationPanel();
        this.attachEventListeners();
        this.loadNotifications();
        
        // Auto-refresh every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.loadNotifications(true); // Silent refresh
        }, 30000);
    }
    
    createNotificationPanel() {
        const container = document.querySelector('.notification-dropdown') || document.querySelector('.notification-btn').parentElement;
        
        if (!container) {
            console.error('Notification container not found');
            return;
        }
        
        const panel = document.createElement('div');
        panel.className = 'notification-panel';
        panel.id = 'notificationPanel';
        panel.innerHTML = `
            <div class="notification-header">
                <h3><i class="bi bi-bell me-2"></i>Notifications</h3>
                <div class="notification-actions">
                    <button class="notification-action-btn" onclick="adminNotifications.markAllAsRead()" title="Mark all as read">
                        <i class="bi bi-check-all"></i>
                    </button>
                    <button class="notification-action-btn" onclick="adminNotifications.refreshNotifications()" title="Refresh">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            <div class="notification-list" id="notificationList">
                <div class="notification-loading">
                    <i class="bi bi-hourglass-split"></i>
                    <p>Loading notifications...</p>
                </div>
            </div>
            <div class="notification-footer">
                <a href="notifications.php" class="notification-view-all">
                    View All Notifications <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        `;
        
        container.appendChild(panel);
        this.panel = panel;
        
        // Get badge element
        this.badge = document.querySelector('.notification-badge');
    }
    
    attachEventListeners() {
        // Toggle panel on button click
        const notifBtn = document.querySelector('.notification-btn');
        if (notifBtn) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.togglePanel();
            });
        }
        
        // Close panel when clicking outside
        document.addEventListener('click', (e) => {
            if (this.isOpen && this.panel && !this.panel.contains(e.target)) {
                this.closePanel();
            }
        });
        
        // Prevent panel from closing when clicking inside
        if (this.panel) {
            this.panel.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
    }
    
    togglePanel() {
        if (this.isOpen) {
            this.closePanel();
        } else {
            this.openPanel();
        }
    }
    
    openPanel() {
        if (this.panel) {
            this.panel.classList.add('show');
            this.isOpen = true;
            this.loadNotifications(); // Refresh when opening
        }
    }
    
    closePanel() {
        if (this.panel) {
            this.panel.classList.remove('show');
            this.isOpen = false;
        }
    }
    
    loadNotifications(silent = false) {
        if (!silent) {
            this.showLoading();
        }
        
        fetch('api/notifications/get_admin_notifications.php?limit=10')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.notifications = data.notifications;
                    this.unreadCount = parseInt(data.unread_count);
                    this.updateBadge();
                    this.renderNotifications();
                } else {
                    this.showError('Failed to load notifications');
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                if (!silent) {
                    this.showError('Network error occurred');
                }
            });
    }
    
    renderNotifications() {
        const list = document.getElementById('notificationList');
        if (!list) return;
        
        if (this.notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="bi bi-inbox"></i>
                    <p>No notifications</p>
                </div>
            `;
            return;
        }
        
        list.innerHTML = this.notifications.map(notif => `
            <div class="notification-item ${notif.read_status}" 
                 data-id="${notif.id}"
                 onclick="adminNotifications.handleNotificationClick(${notif.id}, '${notif.link}')">
                <div class="notification-icon ${notif.priority}">
                    <i class="${notif.icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">
                        ${notif.read_status === 'unread' ? '<span class="notification-unread-dot"></span>' : ''}
                        ${this.escapeHtml(notif.title)}
                    </div>
                    <div class="notification-message">${this.escapeHtml(notif.message)}</div>
                    <div class="notification-time">
                        <i class="bi bi-clock me-1"></i>${notif.time_ago}
                    </div>
                </div>
                <div class="notification-delete">
                    <button class="notification-delete-btn" 
                            onclick="event.stopPropagation(); adminNotifications.deleteNotification(${notif.id})"
                            title="Delete">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    updateBadge() {
        if (this.badge) {
            if (this.unreadCount > 0) {
                this.badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                this.badge.style.display = 'block';
            } else {
                this.badge.style.display = 'none';
            }
        }
    }
    
    handleNotificationClick(id, link) {
        // Mark as read
        this.markAsRead(id);
        
        // Navigate to link if provided
        if (link) {
            setTimeout(() => {
                window.location.href = link;
            }, 100);
        }
    }
    
    markAsRead(id) {
        const formData = new FormData();
        formData.append('notification_id', id);
        
        fetch('api/notifications/mark_as_read.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update local state
                const notif = this.notifications.find(n => n.id === id);
                if (notif) {
                    notif.read_status = 'read';
                }
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.updateBadge();
                this.renderNotifications();
            }
        })
        .catch(error => console.error('Error marking as read:', error));
    }
    
    markAllAsRead() {
        const formData = new FormData();
        formData.append('mark_all', 'true');
        
        fetch('api/notifications/mark_as_read.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadNotifications();
            }
        })
        .catch(error => console.error('Error marking all as read:', error));
    }
    
    deleteNotification(id) {
        if (!confirm('Delete this notification?')) return;
        
        const formData = new FormData();
        formData.append('notification_id', id);
        
        fetch('api/notifications/delete_notification.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadNotifications();
            }
        })
        .catch(error => console.error('Error deleting notification:', error));
    }
    
    refreshNotifications() {
        this.loadNotifications();
    }
    
    showLoading() {
        const list = document.getElementById('notificationList');
        if (list) {
            list.innerHTML = `
                <div class="notification-loading">
                    <i class="bi bi-hourglass-split"></i>
                    <p>Loading notifications...</p>
                </div>
            `;
        }
    }
    
    showError(message) {
        const list = document.getElementById('notificationList');
        if (list) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="bi bi-exclamation-triangle text-danger"></i>
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `;
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Initialize notification system when DOM is ready
let adminNotifications;

document.addEventListener('DOMContentLoaded', function() {
    adminNotifications = new AdminNotifications();
});