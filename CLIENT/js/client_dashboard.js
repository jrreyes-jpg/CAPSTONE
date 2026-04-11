document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    const sidebar = document.querySelector('[data-client-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const mobileToggleButtons = document.querySelectorAll('[data-sidebar-mobile-toggle]');
    const mobileCloseButtons = document.querySelectorAll('[data-sidebar-mobile-close]');
    const desktopToggleButtons = document.querySelectorAll('[data-sidebar-desktop-toggle]');
    const tabButtons = document.querySelectorAll('[data-tab-target]');
    const sectionLinks = document.querySelectorAll('[data-section-link]');
    const jumpButtons = document.querySelectorAll('[data-jump-tab]');
    const notificationShell = document.querySelector('[data-notification-shell]');
    const notificationToggle = document.getElementById('clientNotificationToggle');
    const notificationPanel = document.getElementById('clientNotificationPanel');
    const phDate = document.querySelector('[data-ph-date]');
    const phTime = document.querySelector('[data-ph-time]');
    const collapsedStorageKey = 'edgeClientSidebarCollapsed';
    const mobileMedia = window.matchMedia('(max-width: 992px)');
    const defaultTabId = 'projects-tab';

    if (!sidebar) {
        return;
    }

    const setMobileOpen = function (isOpen) {
        body.classList.toggle('sidebar-mobile-open', isOpen);

        if (overlay) {
            overlay.hidden = !isOpen;
        }

        mobileToggleButtons.forEach(function (button) {
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    };

    const setDesktopCollapsed = function (isCollapsed) {
        if (mobileMedia.matches) {
            body.classList.remove('sidebar-collapsed');
            return;
        }

        body.classList.toggle('sidebar-collapsed', isCollapsed);
        window.localStorage.setItem(collapsedStorageKey, isCollapsed ? '1' : '0');

        desktopToggleButtons.forEach(function (button) {
            button.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        });
    };

    const syncNavigationState = function () {
        const activeHash = window.location.hash.replace('#', '');

        document.querySelectorAll('[data-nav-link="dashboard"], [data-section-link]').forEach(function (link) {
            link.classList.remove('active-link');
        });

        if (activeHash === 'projects-tab' || activeHash === 'profile-tab') {
            document.querySelector('[data-section-link="' + activeHash + '"]')?.classList.add('active-link');
            return;
        }

        document.querySelector('[data-nav-link="dashboard"]')?.classList.add('active-link');
    };

    const activateTab = function (tabId, options) {
        const settings = Object.assign({ updateHash: true }, options || {});
        const targetPanel = document.getElementById(tabId);

        if (!targetPanel) {
            syncNavigationState();
            return;
        }

        document.querySelectorAll('.tab-content').forEach(function (panel) {
            panel.classList.remove('active');
        });

        tabButtons.forEach(function (button) {
            const isActive = button.dataset.tabTarget === tabId;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        targetPanel.classList.add('active');

        if (settings.updateHash) {
            window.history.replaceState(null, '', '#' + tabId);
        }

        syncNavigationState();
    };

    mobileToggleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setMobileOpen(true);
        });
    });

    mobileCloseButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setMobileOpen(false);
        });
    });

    desktopToggleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (mobileMedia.matches) {
                return;
            }

            setDesktopCollapsed(!body.classList.contains('sidebar-collapsed'));
        });
    });

    if (overlay) {
        overlay.addEventListener('click', function () {
            setMobileOpen(false);
        });
    }

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const tabId = button.dataset.tabTarget;

            if (!tabId) {
                return;
            }

            activateTab(tabId, { updateHash: true });
            setMobileOpen(false);
        });
    });

    sectionLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            const tabId = link.dataset.sectionLink;

            if (!tabId) {
                return;
            }

            event.preventDefault();
            activateTab(tabId, { updateHash: true });
            setMobileOpen(false);
        });
    });

    jumpButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const tabId = button.dataset.jumpTab;

            if (!tabId) {
                return;
            }

            activateTab(tabId, { updateHash: true });
            setMobileOpen(false);
        });
    });

    if (notificationShell && notificationToggle && notificationPanel) {
        const setNotificationOpen = function (isOpen) {
            notificationToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            notificationPanel.hidden = !isOpen;
        };

        notificationToggle.addEventListener('click', function () {
            const isOpen = notificationToggle.getAttribute('aria-expanded') === 'true';
            setNotificationOpen(!isOpen);
        });

        document.addEventListener('click', function (event) {
            if (!notificationShell.contains(event.target)) {
                setNotificationOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setNotificationOpen(false);
                setMobileOpen(false);
            }
        });
    } else {
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setMobileOpen(false);
            }
        });
    }

    if (phDate && phTime) {
        const dateFormatter = new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila',
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        });

        const timeFormatter = new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
        });

        const updateClock = function () {
            const now = new Date();
            phDate.textContent = dateFormatter.format(now);
            phTime.textContent = timeFormatter.format(now);
        };

        updateClock();
        window.setInterval(updateClock, 1000);
    }

    const initialCollapsed = window.localStorage.getItem(collapsedStorageKey) === '1';
    setDesktopCollapsed(initialCollapsed);
    setMobileOpen(false);

    if (mobileMedia.addEventListener) {
        mobileMedia.addEventListener('change', function () {
            if (mobileMedia.matches) {
                setMobileOpen(false);
                body.classList.remove('sidebar-collapsed');
            } else {
                setDesktopCollapsed(window.localStorage.getItem(collapsedStorageKey) === '1');
            }
        });
    } else if (mobileMedia.addListener) {
        mobileMedia.addListener(function () {
            if (mobileMedia.matches) {
                setMobileOpen(false);
                body.classList.remove('sidebar-collapsed');
            } else {
                setDesktopCollapsed(window.localStorage.getItem(collapsedStorageKey) === '1');
            }
        });
    }

    const requestedTab = window.location.hash.replace('#', '');
    activateTab(requestedTab || defaultTabId, { updateHash: false });
    syncNavigationState();

    window.addEventListener('hashchange', function () {
        const tabId = window.location.hash.replace('#', '');

        if (tabId === 'projects-tab' || tabId === 'profile-tab') {
            activateTab(tabId, { updateHash: false });
            return;
        }

        syncNavigationState();
    });
});
