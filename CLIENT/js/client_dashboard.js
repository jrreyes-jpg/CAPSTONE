document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const mobileToggle = document.getElementById('sidebarMobileToggle');
    const desktopToggle = document.getElementById('sidebarToggle');
    const toggleIcon = document.getElementById('toggleIcon');
    const sectionLinks = document.querySelectorAll('[data-section-link]');
    const tabButtons = document.querySelectorAll('[data-tab-target]');
    const jumpButtons = document.querySelectorAll('[data-jump-section], [data-jump-tab]');
    const sectionPanels = document.querySelectorAll('.tab-content');
    const notificationRoot = document.querySelector('[data-notification-root]');
    const notificationToggle = document.getElementById('topbarNotificationToggle');
    const notificationPanel = document.getElementById('topbarNotificationDropdown');
    const profileRoot = document.querySelector('[data-profile-root]');
    const profileToggle = document.getElementById('topbarProfileToggle');
    const profilePanel = document.getElementById('topbarProfileDropdown');
    const phDate = document.querySelector('[data-ph-date]');
    const phTime = document.querySelector('[data-ph-time]');
    const collapsedStorageKey = 'edgeClientSidebarCollapsed';
    const mobileMedia = window.matchMedia('(max-width: 992px)');
    const defaultSectionId = 'projects-tab';

    if (!sidebar) {
        return;
    }

    const setMobileOpen = function (isOpen) {
        sidebar.classList.toggle('mobile-open', isOpen);
        body.classList.toggle('sidebar-mobile-open', isOpen);

        if (overlay) {
            overlay.classList.toggle('active', isOpen);
        }

        if (mobileToggle) {
            mobileToggle.classList.toggle('active', isOpen);
            mobileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
    };

    const updateDesktopToggleState = function (isCollapsed) {
        if (desktopToggle) {
            desktopToggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            desktopToggle.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }

        if (toggleIcon) {
            toggleIcon.classList.toggle('is-collapsed', isCollapsed);
        }
    };

    const setDesktopCollapsed = function (isCollapsed) {
        if (mobileMedia.matches) {
            body.classList.remove('sidebar-collapsed');
            sidebar.classList.remove('shrink');
            updateDesktopToggleState(false);
            return;
        }

        body.classList.toggle('sidebar-collapsed', isCollapsed);
        sidebar.classList.toggle('shrink', isCollapsed);
        window.localStorage.setItem(collapsedStorageKey, isCollapsed ? '1' : '0');
        updateDesktopToggleState(isCollapsed);
    };

    const syncNavigationState = function (activeSectionId) {
        sectionLinks.forEach(function (link) {
            const isActive = link.dataset.sectionLink === activeSectionId;
            link.classList.toggle('active-link', isActive);
            link.classList.toggle('active', isActive);
            link.setAttribute('aria-current', isActive ? 'page' : 'false');
        });

        tabButtons.forEach(function (button) {
            const isActive = button.dataset.tabTarget === activeSectionId;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    };

    const activateSection = function (sectionId, options) {
        const settings = Object.assign({ updateHash: true }, options || {});
        let targetPanel = document.getElementById(sectionId);

        if (!targetPanel || !targetPanel.classList.contains('tab-content')) {
            targetPanel = document.getElementById(defaultSectionId);
            sectionId = defaultSectionId;
        }

        sectionPanels.forEach(function (panel) {
            const isActive = panel === targetPanel;
            panel.classList.toggle('active', isActive);
            panel.style.display = isActive ? 'block' : 'none';
        });

        if (settings.updateHash) {
            window.history.replaceState(null, '', '#' + sectionId);
        }

        syncNavigationState(sectionId);
    };

    const initDropdown = function (root, toggle, panel, onOpen) {
        if (!root || !toggle || !panel) {
            return function () {};
        }

        const setOpen = function (isOpen) {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            panel.hidden = !isOpen;

            if (typeof onOpen === 'function' && isOpen) {
                onOpen();
            }
        };

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';
            setOpen(!isOpen);
        });

        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) {
                setOpen(false);
            }
        });

        panel.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                setOpen(false);
            });
        });

        return function () {
            setOpen(false);
        };
    };

    const closeNotifications = initDropdown(notificationRoot, notificationToggle, notificationPanel);
    const closeProfile = initDropdown(profileRoot, profileToggle, profilePanel, closeNotifications);

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function () {
            setMobileOpen(!sidebar.classList.contains('mobile-open'));
        });
    }

    if (desktopToggle) {
        desktopToggle.addEventListener('click', function () {
            if (mobileMedia.matches) {
                return;
            }

            setDesktopCollapsed(!body.classList.contains('sidebar-collapsed'));
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            setMobileOpen(false);
        });
    }

    sectionLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            const sectionId = link.dataset.sectionLink;

            if (!sectionId) {
                return;
            }

            event.preventDefault();
            activateSection(sectionId, { updateHash: true });
            setMobileOpen(false);
        });
    });

    jumpButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const sectionId = button.dataset.jumpSection || button.dataset.jumpTab;

            if (!sectionId) {
                return;
            }

            activateSection(sectionId, { updateHash: true });
            setMobileOpen(false);
        });
    });

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const sectionId = button.dataset.tabTarget;
            if (!sectionId) {
                return;
            }

            activateSection(sectionId, { updateHash: true });
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        closeNotifications();
        closeProfile();
        setMobileOpen(false);
    });

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

    const applyResponsiveSidebarState = function () {
        if (mobileMedia.matches) {
            setMobileOpen(false);
            body.classList.remove('sidebar-collapsed');
            sidebar.classList.remove('shrink');
            updateDesktopToggleState(false);
            return;
        }

        setDesktopCollapsed(window.localStorage.getItem(collapsedStorageKey) === '1');
    };

    setMobileOpen(false);
    applyResponsiveSidebarState();

    if (mobileMedia.addEventListener) {
        mobileMedia.addEventListener('change', applyResponsiveSidebarState);
    } else if (mobileMedia.addListener) {
        mobileMedia.addListener(applyResponsiveSidebarState);
    }

    activateSection(window.location.hash.replace('#', '') || defaultSectionId, { updateHash: false });

    window.addEventListener('hashchange', function () {
        activateSection(window.location.hash.replace('#', '') || defaultSectionId, { updateHash: false });
    });
});
