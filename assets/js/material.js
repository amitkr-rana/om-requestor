// Material Design 3 JavaScript Components
// Custom implementation for OM Requestor

class MaterialComponents {
    static init() {
        this.initInputFields();
        this.initModals();
        this.initRippleEffect();
        this.initTooltips();
    }

    // Initialize input field animations
    static initInputFields() {
        const inputFields = document.querySelectorAll('.input-field input, .input-field textarea, .input-field select');

        inputFields.forEach(input => {
            // Check if field has value on page load
            if (input.value && input.value.trim() !== '') {
                input.classList.add('has-value');
            }

            // Handle focus and blur events
            input.addEventListener('focus', () => {
                input.parentElement.classList.add('focused');
            });

            input.addEventListener('blur', () => {
                input.parentElement.classList.remove('focused');
                if (input.value && input.value.trim() !== '') {
                    input.classList.add('has-value');
                } else {
                    input.classList.remove('has-value');
                }
            });

            // Handle input events
            input.addEventListener('input', () => {
                if (input.value && input.value.trim() !== '') {
                    input.classList.add('has-value');
                } else {
                    input.classList.remove('has-value');
                }
            });
        });
    }

    // Modal functionality
    static initModals() {
        // Modal triggers
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal]');
            if (trigger) {
                e.preventDefault();
                const modalId = trigger.getAttribute('data-modal');
                this.openModal(modalId);
            }

            // Close modal
            const closeBtn = e.target.closest('.modal-close, [data-close-modal]');
            if (closeBtn) {
                e.preventDefault();
                this.closeModal();
            }

            // Close modal on overlay click
            if (e.target.classList.contains('modal-overlay')) {
                this.closeModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModal();
            }
        });
    }

    static openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            // Focus first input in modal
            const firstInput = modal.querySelector('input, textarea, select');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    }

    static closeModal() {
        const openModal = document.querySelector('.modal-overlay[style*="flex"]');
        if (openModal) {
            openModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    // Ripple effect for buttons
    static initRippleEffect() {
        document.addEventListener('click', (e) => {
            const button = e.target.closest('.btn');
            if (button && !button.disabled) {
                this.createRipple(e, button);
            }
        });
    }

    static createRipple(event, element) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;

        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: ripple 0.6s linear;
            left: ${x}px;
            top: ${y}px;
            width: ${size}px;
            height: ${size}px;
            pointer-events: none;
        `;

        // Add ripple animation to stylesheet if not exists
        if (!document.querySelector('#ripple-animation')) {
            const style = document.createElement('style');
            style.id = 'ripple-animation';
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    // Simple tooltip functionality
    static initTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');

        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target);
            });

            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    static showTooltip(element) {
        const tooltipText = element.getAttribute('data-tooltip');
        const tooltip = document.createElement('div');

        tooltip.className = 'tooltip';
        tooltip.textContent = tooltipText;
        tooltip.style.cssText = `
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            white-space: nowrap;
            z-index: 1001;
            pointer-events: none;
        `;

        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
    }

    static hideTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }

    // Show loading spinner
    static showLoading(container = document.body) {
        const loading = document.createElement('div');
        loading.className = 'loading-overlay';
        loading.innerHTML = '<div class="spinner"></div>';
        loading.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        `;
        container.appendChild(loading);
    }

    static hideLoading() {
        const loading = document.querySelector('.loading-overlay');
        if (loading) {
            loading.remove();
        }
    }

    // Show snackbar notification
    static showSnackbar(message, type = 'info', duration = 4000) {
        const snackbar = document.createElement('div');
        snackbar.className = `snackbar snackbar-${type}`;
        snackbar.textContent = message;

        const colors = {
            success: 'var(--md-sys-color-success)',
            error: 'var(--md-sys-color-error)',
            warning: 'var(--md-sys-color-warning)',
            info: 'var(--md-sys-color-primary)'
        };

        snackbar.style.cssText = `
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: ${colors[type] || colors.info};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--md-sys-shape-corner-small);
            box-shadow: var(--md-sys-elevation-level2);
            z-index: 1002;
            animation: snackbarIn 0.3s ease;
        `;

        // Add animation to stylesheet if not exists
        if (!document.querySelector('#snackbar-animation')) {
            const style = document.createElement('style');
            style.id = 'snackbar-animation';
            style.textContent = `
                @keyframes snackbarIn {
                    from {
                        opacity: 0;
                        transform: translateX(-50%) translateY(100px);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(-50%) translateY(0);
                    }
                }
                @keyframes snackbarOut {
                    from {
                        opacity: 1;
                        transform: translateX(-50%) translateY(0);
                    }
                    to {
                        opacity: 0;
                        transform: translateX(-50%) translateY(100px);
                    }
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(snackbar);

        setTimeout(() => {
            snackbar.style.animation = 'snackbarOut 0.3s ease';
            setTimeout(() => snackbar.remove(), 300);
        }, duration);
    }

    // Confirm dialog
    static confirm(message, callback) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.style.display = 'flex';

        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.maxWidth = '400px';

        modal.innerHTML = `
            <div class="modal-header">
                <h3 class="modal-title">Confirm</h3>
            </div>
            <div class="modal-content">
                <p>${message}</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-text cancel-btn">Cancel</button>
                <button type="button" class="btn btn-primary confirm-btn">Confirm</button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        modal.querySelector('.cancel-btn').addEventListener('click', () => {
            overlay.remove();
            if (callback) callback(false);
        });

        modal.querySelector('.confirm-btn').addEventListener('click', () => {
            overlay.remove();
            if (callback) callback(true);
        });

        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.remove();
                if (callback) callback(false);
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    MaterialComponents.init();
});

// Export for global use
window.Material = MaterialComponents;