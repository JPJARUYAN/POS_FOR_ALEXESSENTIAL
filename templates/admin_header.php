<header>
    <span class="title">Point of Sale System</span>
    <button id="themeToggle" class="btn" style="padding:4px 10px; font-size:12px;">
        <span id="themeToggleIcon">🌙</span>
        <span id="themeToggleLabel">Dark</span>
    </button>
</header>

<script>
    // Simple light/dark theme toggle using CSS variables in main.css
    (function () {
        const root = document.documentElement;
        const stored = localStorage.getItem('pos_theme');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const initialTheme = stored || (prefersDark ? 'dark' : 'light');

        function applyTheme(theme) {
            if (theme === 'light') {
                root.setAttribute('data-theme', 'light');
            } else {
                root.removeAttribute('data-theme'); // default dark
            }
            localStorage.setItem('pos_theme', theme);
            const iconEl = document.getElementById('themeToggleIcon');
            const labelEl = document.getElementById('themeToggleLabel');
            if (iconEl && labelEl) {
                if (theme === 'light') {
                    iconEl.textContent = '☀️';
                    labelEl.textContent = 'Light';
                } else {
                    iconEl.textContent = '🌙';
                    labelEl.textContent = 'Dark';
                }
            }
        }

        applyTheme(initialTheme);

        const btn = document.getElementById('themeToggle');
        if (btn) {
            btn.addEventListener('click', function () {
                const current = root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
                applyTheme(current === 'light' ? 'dark' : 'light');
            });
        }
    })();
</script>