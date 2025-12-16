/**
 * Theme Manager
 * Handles toggling between light and dark modes and persisting preference.
 */

const ThemeManager = {
    init() {
        // Check for saved theme or default to dark (since the app was originally dark)
        const savedTheme = localStorage.getItem('theme') || 'dark';
        this.setTheme(savedTheme);

        // Expose toggle function globally
        window.toggleTheme = () => this.toggle();
    },

    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        this.updateIcons(theme);
    },

    toggle() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    },

    updateIcons(theme) {
        // Update any theme toggle icons if they exist
        const toggleBtns = document.querySelectorAll('.theme-toggle-btn');
        toggleBtns.forEach(btn => {
            // You can add specific icon logic here if needed
            // For now, we might just rely on CSS to show/hide sun/moon icons
            const sunIcon = btn.querySelector('.icon-sun');
            const moonIcon = btn.querySelector('.icon-moon');

            if (sunIcon && moonIcon) {
                if (theme === 'light') {
                    sunIcon.style.display = 'none';
                    moonIcon.style.display = 'block';
                } else {
                    sunIcon.style.display = 'block';
                    moonIcon.style.display = 'none';
                }
            }
        });
    }
};

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    ThemeManager.init();
});
