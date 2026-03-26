document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleIcon = document.getElementById('toggleIcon');
    const mainContent = document.querySelector('.main-content');
    const storageKey = 'edgeSidebarCollapsed';

    if (!sidebar || !toggleButton || !overlay || !toggleIcon) {
        return;
    }

    const isMobile = () => window.innerWidth <= 768;

    const syncMainContent = () => {
        const shouldCollapse = sidebar.classList.contains('collapsed') && !isMobile();
        mainContent?.classList.toggle('sidebar-collapsed', shouldCollapse);
    };

    const closeMobileSidebar = () => {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    };

    const updateToggleLabel = () => {
        if (isMobile()) {
            const isOpen = sidebar.classList.contains('mobile-open');
            toggleIcon.textContent = isOpen ? 'x' : '=';
            toggleButton.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
            return;
        }

        const isCollapsed = sidebar.classList.contains('collapsed');
        toggleIcon.textContent = isCollapsed ? '>' : '<';
        toggleButton.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
    };

    const applyStoredDesktopState = () => {
        if (isMobile()) {
            sidebar.classList.remove('collapsed');
            syncMainContent();
            closeMobileSidebar();
            updateToggleLabel();
            return;
        }

        const shouldCollapse = window.localStorage.getItem(storageKey) === '1';
        sidebar.classList.toggle('collapsed', shouldCollapse);
        closeMobileSidebar();
        syncMainContent();
        updateToggleLabel();
    };

    toggleButton.addEventListener('click', () => {
        if (isMobile()) {
            const isOpen = sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active', isOpen);
            updateToggleLabel();
            return;
        }

        const isCollapsed = sidebar.classList.toggle('collapsed');
        window.localStorage.setItem(storageKey, isCollapsed ? '1' : '0');
        syncMainContent();
        updateToggleLabel();
    });

    overlay.addEventListener('click', () => {
        closeMobileSidebar();
        updateToggleLabel();
    });

    window.addEventListener('resize', applyStoredDesktopState);
    applyStoredDesktopState();
});
