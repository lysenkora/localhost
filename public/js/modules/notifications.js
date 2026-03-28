class NotificationManager {
    constructor() {
        this.container = document.getElementById('notificationContainer');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'notification-container';
            this.container.id = 'notificationContainer';
            document.body.appendChild(this.container);
        }
    }
    
    show(type, title, message, duration = 5000) {
        const id = 'notification-' + Date.now();
        const icon = this.getIcon(type);
        
        const notification = document.createElement('div');
        notification.id = id;
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-icon"><i class="${icon}"></i></div>
            <div class="notification-content">
                <div class="notification-title">${title}</div>
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close" onclick="window.notificationManager.close('${id}')">×</button>
            <div class="notification-progress">
                <div class="notification-progress-bar" id="progress-${id}" style="width: 100%;"></div>
            </div>
        `;
        
        this.container.appendChild(notification);
        
        const startTime = Date.now();
        const animate = () => {
            const elapsed = Date.now() - startTime;
            const remaining = Math.max(0, duration - elapsed);
            const width = (remaining / duration) * 100;
            
            const progressBar = document.getElementById(`progress-${id}`);
            if (progressBar) {
                progressBar.style.width = width + '%';
            }
            
            if (remaining > 0) {
                requestAnimationFrame(animate);
            } else {
                this.close(id);
            }
        };
        
        requestAnimationFrame(animate);
        
        setTimeout(() => this.close(id), duration);
    }
    
    close(id) {
        const notification = document.getElementById(id);
        if (!notification) return;
        
        notification.classList.add('fade-out');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
    
    getIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-times-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }
}

window.notificationManager = new NotificationManager();
window.showNotification = (type, title, message) => window.notificationManager.show(type, title, message);