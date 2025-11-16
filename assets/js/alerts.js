// Modern Alert System JavaScript
class AlertSystem {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Create alert container if it doesn't exist
        if (!document.querySelector('.alert-container')) {
            this.container = document.createElement('div');
            this.container.className = 'alert-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.querySelector('.alert-container');
        }
    }

    // Show success alert
    success(message, title = 'Success', autoDismiss = true, duration = 5000) {
        return this.showAlert('success', message, title, autoDismiss, duration, 'fas fa-check');
    }

    // Show error alert
    error(message, title = 'Error', autoDismiss = true, duration = 5000) {
        return this.showAlert('error', message, title, autoDismiss, duration, 'fas fa-exclamation-triangle');
    }

    // Show warning alert
    warning(message, title = 'Warning', autoDismiss = true, duration = 5000) {
        return this.showAlert('warning', message, title, autoDismiss, duration, 'fas fa-exclamation-circle');
    }

    // Show info alert
    info(message, title = 'Information', autoDismiss = true, duration = 5000) {
        return this.showAlert('info', message, title, autoDismiss, duration, 'fas fa-info-circle');
    }

    // Show danger alert
    danger(message, title = 'Danger', autoDismiss = true, duration = 5000) {
        return this.showAlert('danger', message, title, autoDismiss, duration, 'fas fa-times-circle');
    }

    // Main alert function
    showAlert(type, message, title, autoDismiss, duration, iconClass) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        
        if (autoDismiss) {
            alert.classList.add('auto-dismiss');
        }

        // Create alert content
        alert.innerHTML = `
            <div class="alert-icon">
                <i class="${iconClass}"></i>
            </div>
            <div class="alert-content">
                <div class="alert-title">${title}</div>
                <div class="alert-message">${message}</div>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
            ${autoDismiss ? '<div class="alert-progress"></div>' : ''}
        `;

        // Add to container
        this.container.appendChild(alert);

        // Auto dismiss
        if (autoDismiss) {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.animation = 'slideOutRight 0.3s ease-in forwards';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 300);
                }
            }, duration);
        }

        // Add hover effect
        alert.addEventListener('mouseenter', () => {
            if (autoDismiss) {
                alert.style.animationPlayState = 'paused';
            }
        });

        alert.addEventListener('mouseleave', () => {
            if (autoDismiss) {
                alert.style.animationPlayState = 'running';
            }
        });

        return alert;
    }

    // Clear all alerts
    clearAll() {
        const alerts = this.container.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.animation = 'slideOutRight 0.3s ease-in forwards';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 300);
        });
    }

    // Clear specific alert
    clear(alertElement) {
        if (alertElement && alertElement.parentNode) {
            alertElement.style.animation = 'slideOutRight 0.3s ease-in forwards';
            setTimeout(() => {
                if (alertElement.parentNode) {
                    alertElement.parentNode.removeChild(alertElement);
                }
            }, 300);
        }
    }
}

// Initialize alert system
const alerts = new AlertSystem();

// Global functions for easy access
window.showSuccess = (message, title, autoDismiss, duration) => {
    return alerts.success(message, title, autoDismiss, duration);
};

window.showError = (message, title, autoDismiss, duration) => {
    return alerts.error(message, title, autoDismiss, duration);
};

window.showWarning = (message, title, autoDismiss, duration) => {
    return alerts.warning(message, title, autoDismiss, duration);
};

window.showInfo = (message, title, autoDismiss, duration) => {
    return alerts.info(message, title, autoDismiss, duration);
};

window.showDanger = (message, title, autoDismiss, duration) => {
    return alerts.danger(message, title, autoDismiss, duration);
};

window.clearAllAlerts = () => {
    alerts.clearAll();
};

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize alert system
    if (typeof alerts === 'undefined') {
        window.alerts = new AlertSystem();
    }
    
    // Convert existing session alerts to new format
    const existingAlerts = document.querySelectorAll('.alert-success, .alert-error, .alert-danger, .alert-warning, .alert-info');
    existingAlerts.forEach(alert => {
        // Add modern styling if not already present
        if (!alert.querySelector('.alert-icon')) {
            const message = alert.textContent.trim();
            const type = alert.className.includes('success') ? 'success' : 
                        alert.className.includes('error') ? 'error' : 
                        alert.className.includes('danger') ? 'danger' : 
                        alert.className.includes('warning') ? 'warning' : 'info';
            
            // Remove old alert
            alert.remove();
            
            // Show new alert
            alerts[type](message);
        }
    });
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AlertSystem;
} 