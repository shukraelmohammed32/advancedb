(function () {
    const root = document.documentElement;
    const themeKey = 'srs-dashboard-theme';
    const savedTheme = localStorage.getItem(themeKey);
    if (savedTheme === 'dark') root.setAttribute('data-dashboard-theme', 'dark');

    const themeToggle = document.getElementById('themeToggle');
    themeToggle?.addEventListener('click', function () {
        const next = root.getAttribute('data-dashboard-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-dashboard-theme', next);
        localStorage.setItem(themeKey, next);
        const icon = this.querySelector('i');
        if (icon) icon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    });
    if (themeToggle && root.getAttribute('data-dashboard-theme') === 'dark') {
        const icon = themeToggle.querySelector('i');
        if (icon) icon.className = 'bi bi-sun';
    }

    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const collapseBtn = document.getElementById('sidebarCollapse');
    const openBtn = document.getElementById('sidebarOpen');

    function closeMobile() {
        sidebar?.classList.remove('is-open');
        backdrop?.setAttribute('hidden', '');
    }

    openBtn?.addEventListener('click', function () {
        sidebar?.classList.add('is-open');
        backdrop?.removeAttribute('hidden');
    });
    backdrop?.addEventListener('click', closeMobile);
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) closeMobile();
    });

    const collapsedKey = 'srs-sidebar-collapsed';
    if (localStorage.getItem(collapsedKey) === '1' && sidebar) sidebar.classList.add('is-collapsed');
    collapseBtn?.addEventListener('click', function () {
        sidebar?.classList.toggle('is-collapsed');
        localStorage.setItem(collapsedKey, sidebar?.classList.contains('is-collapsed') ? '1' : '0');
    });
})();
