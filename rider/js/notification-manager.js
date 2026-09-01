/**
 * Real-time Notification Manager
 * Handles polling for new notifications and displaying them
 * Usage: new NotificationManager({ email: 'user@example.com', pollInterval: 10000 });
 */

class NotificationManager {
	constructor(config = {}) {
		this.email = config.email || '';
		this.pollInterval = config.pollInterval || 15000; // 15 seconds default
		this.containerSelector = config.containerSelector || '#notifications';
		this.badgeSelector = config.badgeSelector || '.notif-badge';
		this.apiEndpoint = config.apiEndpoint || '../controllers/rider_notification.php';
		this.onNewNotification = config.onNewNotification || null;
		this.notifications = [];
		this.isPolling = false;
		this.pollTimer = null;

		if (this.email) {
			this.startPolling();
		}
	}

	/**
	 * Start polling for notifications
	 */
	startPolling() {
		if (this.isPolling) return;
		this.isPolling = true;
		this.pollForNotifications();
	}

	/**
	 * Stop polling for notifications
	 */
	stopPolling() {
		this.isPolling = false;
		if (this.pollTimer) {
			clearTimeout(this.pollTimer);
			this.pollTimer = null;
		}
	}

	/**
	 * Fetch notifications from server
	 */
	async pollForNotifications() {
		if (!this.isPolling) return;

		try {
			const response = await fetch(
				`${this.apiEndpoint}?email=${encodeURIComponent(this.email)}`
			);

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const result = await response.json();

			if (result.success && result.notifications) {
				const oldUnreadCount = this.getUnreadCount();
				this.notifications = result.notifications;
				const newUnreadCount = result.unreadCount || 0;

				// Check for new notifications
				if (newUnreadCount > oldUnreadCount) {
					const newNotifications = this.notifications.filter(
						n => !n.isRead && !this.hasSeenNotification(n.id)
					);
					
					if (newNotifications.length > 0 && this.onNewNotification) {
						newNotifications.forEach(notif => {
							this.onNewNotification(notif);
						});
					}
				}

				this.updateUI();
			}
		} catch (error) {
			console.error('Notification polling error:', error);
		}

		// Schedule next poll
		if (this.isPolling) {
			this.pollTimer = setTimeout(() => this.pollForNotifications(), this.pollInterval);
		}
	}

	/**
	 * Check if we've seen this notification before
	 */
	hasSeenNotification(id) {
		const seen = JSON.parse(localStorage.getItem('seenNotifications') || '[]');
		return seen.includes(id);
	}

	/**
	 * Mark notification as seen locally
	 */
	markAsSeenLocally(id) {
		const seen = JSON.parse(localStorage.getItem('seenNotifications') || '[]');
		if (!seen.includes(id)) {
			seen.push(id);
			localStorage.setItem('seenNotifications', JSON.stringify(seen));
		}
	}

	/**
	 * Get unread count
	 */
	getUnreadCount() {
		return this.notifications.filter(n => !n.isRead).length;
	}

	/**
	 * Update UI with notification count
	 */
	updateUI() {
		const unreadCount = this.getUnreadCount();
		const badges = document.querySelectorAll(this.badgeSelector);
		
		badges.forEach(badge => {
			if (unreadCount > 0) {
				badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
				badge.style.display = 'block';
			} else {
				badge.style.display = 'none';
			}
		});
	}

	/**
	 * Mark notification as read
	 */
	async markAsRead(notificationId) {
		try {
			const response = await fetch(this.apiEndpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					action: 'read_notification',
					notification_id: notificationId
				})
			});

			const result = await response.json();
			if (result.success) {
				const notif = this.notifications.find(n => n.id === notificationId);
				if (notif) {
					notif.isRead = true;
				}
				this.updateUI();
			}
		} catch (error) {
			console.error('Error marking notification as read:', error);
		}
	}

	/**
	 * Mark all notifications as read
	 */
	async markAllAsRead() {
		try {
			const response = await fetch(this.apiEndpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					action: 'mark_all_read',
					email: this.email
				})
			});

			const result = await response.json();
			if (result.success) {
				this.notifications.forEach(n => n.isRead = true);
				this.updateUI();
			}
		} catch (error) {
			console.error('Error marking all as read:', error);
		}
	}

	/**
	 * Show notification toast/popup
	 */
	showNotificationToast(notification, duration = 5000) {
		const toast = document.createElement('div');
		toast.className = `notification-toast notification-${notification.severity}`;
		toast.innerHTML = `
			<div class="toast-content">
				<div class="toast-title">${this.escapeHtml(notification.title)}</div>
				<div class="toast-message">${this.escapeHtml(notification.message)}</div>
			</div>
			<button class="toast-close">&times;</button>
		`;

		document.body.appendChild(toast);

		// Auto-remove after duration
		if (duration > 0) {
			setTimeout(() => {
				toast.style.animation = 'slideOut 0.3s ease-out forwards';
				setTimeout(() => toast.remove(), 300);
			}, duration);
		}

		// Close button
		toast.querySelector('.toast-close').addEventListener('click', () => {
			toast.style.animation = 'slideOut 0.3s ease-out forwards';
			setTimeout(() => toast.remove(), 300);
		});
	}

	/**
	 * Escape HTML to prevent XSS
	 */
	escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, m => map[m]);
	}

	/**
	 * Get notifications list
	 */
	getNotifications() {
		return [...this.notifications];
	}

	/**
	 * Get unread notifications
	 */
	getUnreadNotifications() {
		return this.notifications.filter(n => !n.isRead);
	}
}

// Add CSS for notification toast
const style = document.createElement('style');
style.textContent = `
	.notification-toast {
		position: fixed;
		top: 20px;
		right: 20px;
		background: white;
		border-radius: 8px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
		padding: 16px 20px;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		z-index: 10000;
		max-width: 400px;
		animation: slideIn 0.3s ease-out forwards;
		font-family: 'Poppins', sans-serif;
	}

	.notification-toast.notification-info {
		border-left: 4px solid #2456C7;
	}

	.notification-toast.notification-warning {
		border-left: 4px solid #F2B90F;
	}

	.notification-toast.notification-urgent {
		border-left: 4px solid #E5484D;
	}

	.toast-content {
		flex: 1;
		min-width: 0;
	}

	.toast-title {
		font-weight: 700;
		font-size: 14px;
		color: #1B2A4A;
		margin-bottom: 4px;
	}

	.toast-message {
		font-size: 13px;
		color: #5B6472;
		line-height: 1.4;
	}

	.toast-close {
		background: none;
		border: none;
		font-size: 24px;
		color: #9AA3B2;
		cursor: pointer;
		padding: 0;
		flex-shrink: 0;
	}

	.toast-close:hover {
		color: #1B2A4A;
	}

	@keyframes slideIn {
		from {
			transform: translateX(400px);
			opacity: 0;
		}
		to {
			transform: translateX(0);
			opacity: 1;
		}
	}

	@keyframes slideOut {
		from {
			transform: translateX(0);
			opacity: 1;
		}
		to {
			transform: translateX(400px);
			opacity: 0;
		}
	}

	@media (max-width: 640px) {
		.notification-toast {
			left: 12px;
			right: 12px;
			top: auto;
			bottom: 20px;
		}
	}
`;
document.head.appendChild(style);
