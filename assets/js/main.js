// Main JavaScript file for Om Engineers
// Application-specific functionality

class OMRequestor {
    constructor() {
        this.apiBase = 'api/';
        this.init();
    }

    init() {
        this.initFormValidation();
        this.initAjaxForms();
        this.initDataTables();
        this.initDatePickers();
        this.bindEvents();
    }

    // Form validation
    initFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');

        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }

    validateForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'This field is required');
                isValid = false;
            } else {
                this.clearFieldError(field);
            }

            // Email validation
            if (field.type === 'email' && field.value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(field.value)) {
                    this.showFieldError(field, 'Please enter a valid email address');
                    isValid = false;
                }
            }

            // Registration number validation
            if (field.name === 'registration_number' && field.value) {
                const regNoRegex = /^[A-Z]{2}\d{2}[A-Z]{2}\d{4}$/;
                if (!regNoRegex.test(field.value.toUpperCase())) {
                    this.showFieldError(field, 'Please enter a valid registration number (e.g., DL01AB1234)');
                    isValid = false;
                }
            }
        });

        return isValid;
    }

    showFieldError(field, message) {
        this.clearFieldError(field);

        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.cssText = `
            color: var(--md-sys-color-error);
            font-size: 0.75rem;
            margin-top: 0.25rem;
        `;
        errorDiv.textContent = message;

        field.parentElement.appendChild(errorDiv);
        field.style.borderColor = 'var(--md-sys-color-error)';
    }

    clearFieldError(field) {
        const errorDiv = field.parentElement.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
        field.style.borderColor = '';
    }

    // AJAX form handling
    initAjaxForms() {
        const ajaxForms = document.querySelectorAll('form[data-ajax]');

        ajaxForms.forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.submitForm(form);
            });
        });
    }

    async submitForm(form) {
        const formData = new FormData(form);
        const action = form.action || form.getAttribute('data-action');

        if (!action) {
            Material.showSnackbar('Form action not specified', 'error');
            return;
        }

        try {
            Material.showLoading();

            const response = await fetch(action, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            Material.hideLoading();

            if (result.success) {
                Material.showSnackbar(result.message || 'Operation completed successfully', 'success');

                // Redirect if specified
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1000);
                }

                // Reset form if specified
                if (form.hasAttribute('data-reset-on-success')) {
                    form.reset();
                }

                // Close modal if form is in modal
                const modal = form.closest('.modal-overlay');
                if (modal) {
                    Material.closeModal();
                }

                // Reload data if specified
                if (form.hasAttribute('data-reload-on-success')) {
                    this.reloadPageData();
                }
            } else {
                Material.showSnackbar(result.error || 'An error occurred', 'error');
            }
        } catch (error) {
            Material.hideLoading();
            console.error('Form submission error:', error);
            Material.showSnackbar('Network error occurred', 'error');
        }
    }

    // Data table functionality
    initDataTables() {
        const tables = document.querySelectorAll('.data-table');

        tables.forEach(table => {
            // Add sorting functionality
            const headers = table.querySelectorAll('th[data-sort]');
            headers.forEach(header => {
                header.addEventListener('click', () => {
                    this.sortTable(table, header.getAttribute('data-sort'));
                });
            });

            // Add search functionality
            const searchInput = table.closest('.table-container').querySelector('.table-search');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    this.filterTable(table, e.target.value);
                });
            }
        });
    }

    sortTable(table, column) {
        // Simple client-side sorting implementation
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const columnIndex = Array.from(table.querySelectorAll('th')).findIndex(th =>
            th.getAttribute('data-sort') === column
        );

        rows.sort((a, b) => {
            const aVal = a.cells[columnIndex].textContent.trim();
            const bVal = b.cells[columnIndex].textContent.trim();

            // Try to parse as numbers
            const aNum = parseFloat(aVal);
            const bNum = parseFloat(bVal);

            if (!isNaN(aNum) && !isNaN(bNum)) {
                return aNum - bNum;
            }

            return aVal.localeCompare(bVal);
        });

        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
    }

    filterTable(table, searchTerm) {
        const rows = table.querySelectorAll('tbody tr');
        const term = searchTerm.toLowerCase();

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }

    // Date picker initialization
    initDatePickers() {
        const dateInputs = document.querySelectorAll('input[type="date"]');

        dateInputs.forEach(input => {
            // Set max date to today for past dates
            if (input.hasAttribute('data-max-today')) {
                input.max = new Date().toISOString().split('T')[0];
            }

            // Set min date to today for future dates
            if (input.hasAttribute('data-min-today')) {
                input.min = new Date().toISOString().split('T')[0];
            }
        });
    }

    // Event binding
    bindEvents() {
        // Navigation active state
        this.setActiveNavigation();

        // Auto-submit search forms
        document.addEventListener('change', (e) => {
            if (e.target.matches('select[data-auto-submit]')) {
                e.target.closest('form').submit();
            }
        });

        // Confirmation dialogs
        document.addEventListener('click', (e) => {
            const confirmBtn = e.target.closest('[data-confirm]');
            if (confirmBtn) {
                e.preventDefault();
                const message = confirmBtn.getAttribute('data-confirm');
                Material.confirm(message, (confirmed) => {
                    if (confirmed) {
                        if (confirmBtn.tagName === 'A') {
                            window.location.href = confirmBtn.href;
                        } else if (confirmBtn.tagName === 'BUTTON') {
                            confirmBtn.click();
                        }
                    }
                });
            }
        });

        // Print functionality
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-print]')) {
                e.preventDefault();
                window.print();
            }
        });
    }

    setActiveNavigation() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.sidebar-nav a');

        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath ||
                currentPath.includes(link.getAttribute('href'))) {
                link.classList.add('active');
            }
        });
    }

    // Utility methods
    async loadData(endpoint, params = {}) {
        try {
            const url = new URL(this.apiBase + endpoint, window.location.origin);
            Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

            const response = await fetch(url);
            return await response.json();
        } catch (error) {
            console.error('Data loading error:', error);
            Material.showSnackbar('Failed to load data', 'error');
            return null;
        }
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR'
        }).format(amount);
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('en-IN', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    reloadPageData() {
        // Reload specific data sections without full page refresh
        const reloadable = document.querySelectorAll('[data-reload]');
        reloadable.forEach(element => {
            const endpoint = element.getAttribute('data-reload');
            if (endpoint) {
                this.loadData(endpoint).then(data => {
                    if (data) {
                        element.innerHTML = data.html || '';
                    }
                });
            }
        });
    }

    // Dashboard charts (if needed in future)
    initCharts() {
        // Placeholder for chart initialization
        // Can be extended with Chart.js or similar library
    }
}

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new OMRequestor();
});