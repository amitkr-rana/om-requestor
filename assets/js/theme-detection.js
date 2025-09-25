/**
 * Immediate Theme Detection Script
 * Must be loaded in <head> before any CSS to prevent FOUC (Flash of Unstyled Content)
 */
(function() {
    'use strict';

    // Detect preferred theme immediately
    function detectTheme() {
        // Check localStorage first
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme && (savedTheme === 'light' || savedTheme === 'dark')) {
            return savedTheme;
        }

        // Check system preference as fallback
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }

        // Default to light theme
        return 'light';
    }

    // Apply theme immediately to prevent flickering
    function applyTheme(theme) {
        const html = document.documentElement;
        const body = document.body;

        // Apply to HTML element (highest priority)
        html.setAttribute('data-theme', theme);
        html.className = html.className.replace(/theme-\w+/g, '') + ' theme-' + theme;

        // Apply to body when available
        if (body) {
            body.setAttribute('data-theme', theme);
            body.className = body.className.replace(/theme-\w+/g, '') + ' theme-' + theme;
        }

        // Store the applied theme
        window.currentTheme = theme;

        // Save to localStorage for persistence
        try {
            localStorage.setItem('theme', theme);
        } catch (e) {
            console.warn('Could not save theme to localStorage:', e);
        }
    }

    // Initialize theme immediately
    const initialTheme = detectTheme();
    applyTheme(initialTheme);

    // Listen for system theme changes
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addListener(function(e) {
            // Only auto-switch if user hasn't manually set a preference
            const savedTheme = localStorage.getItem('theme');
            if (!savedTheme) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    // Expose theme functions globally for use by toggle buttons
    window.ThemeManager = {
        getCurrentTheme: function() {
            return window.currentTheme || 'light';
        },

        setTheme: function(theme) {
            if (theme === 'light' || theme === 'dark') {
                applyTheme(theme);

                // Trigger custom event for components that need to update
                try {
                    const event = new CustomEvent('themeChanged', {
                        detail: { theme: theme }
                    });
                    document.dispatchEvent(event);
                } catch (e) {
                    // Fallback for older browsers
                    console.log('Theme changed to:', theme);
                }
            }
        },

        toggleTheme: function() {
            const current = this.getCurrentTheme();
            const newTheme = current === 'dark' ? 'light' : 'dark';
            this.setTheme(newTheme);
            return newTheme;
        },

        // Check if system supports dark mode
        systemSupportsDarkMode: function() {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
    };

    // Console log for debugging
    if (window.console && console.log) {
        console.log('🌓 Theme Manager initialized:', {
            theme: initialTheme,
            systemDarkMode: window.ThemeManager.systemSupportsDarkMode(),
            hasLocalStorage: !!localStorage.getItem('theme')
        });
    }
})();