/**
 * Modern Notification System
 */

function showNotification(type, title, message, duration = 5000) {
    const container = document.getElementById('notificationContainer');
    const notification = document.createElement('div');
    
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <div class="notification-icon">
                <i class="fas ${getIcon(type)}"></i>
            </div>
            <div class="notification-text">
                <h4 class="notification-title">${title}</h4>
                <p class="notification-message">${message}</p>
            </div>
        </div>
        <button class="notification-close">×</button>
        <div class="notification-progress"></div>
    `;
    
    container.appendChild(notification);
    
    // Show notification with animation
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Auto-dismiss
    const autoClose = setTimeout(() => {
        removeNotification(notification);
    }, duration);
    
    // Manual close button
    notification.querySelector('.notification-close').onclick = () => {
        clearTimeout(autoClose);
        removeNotification(notification);
    };
    
    return notification;
}

function removeNotification(notification) {
    notification.classList.add('hide');
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 400);
}

function getIcon(type) {
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    return icons[type] || 'fa-info-circle';
}

// Make functions globally available
window.showNotification = showNotification;
window.removeNotification = removeNotification;