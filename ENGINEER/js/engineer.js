const activateTab = (tabId) => {
    const targetPanel = document.getElementById(tabId);

    if (!targetPanel) {
        return;
    }

    document.querySelectorAll('.tab-content').forEach((panel) => {
        panel.classList.remove('active');
    });

    document.querySelectorAll('[data-tab-target]').forEach((button) => {
        button.classList.remove('active');
    });

    targetPanel.classList.add('active');
    document.querySelector(`[data-tab-target="${tabId}"]`)?.classList.add('active');

    document.querySelectorAll('[data-section-link]').forEach((link) => {
        link.classList.toggle('active-link', link.dataset.sectionLink === tabId);
    });
};

const setSidebarState = (isShrink) => {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const toggleButton = document.querySelector('[data-sidebar-toggle]');

    if (!sidebar || !mainContent) {
        return;
    }

    sidebar.classList.toggle('shrink', isShrink);
    mainContent.classList.toggle('sidebar-shrink', isShrink);

    if (toggleButton) {
        toggleButton.innerHTML = isShrink ? '&rsaquo;' : '&lsaquo;';
    }
};

const closeMobileSidebar = () => {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const mobileToggle = document.querySelector('[data-sidebar-mobile-toggle]');

    sidebar?.classList.remove('mobile-open');
    overlay?.classList.remove('active');
    mobileToggle?.classList.remove('active');
};

const initTaskFilters = () => {
    const searchInput = document.querySelector('[data-task-search]');
    const statusFilter = document.querySelector('[data-task-status-filter]');
    const deadlineFilter = document.querySelector('[data-task-deadline-filter]');
    const taskItems = Array.from(document.querySelectorAll('[data-task-item]'));

    if (!searchInput || !statusFilter || !deadlineFilter || taskItems.length === 0) {
        return;
    }

    const applyFilters = () => {
        const searchValue = searchInput.value.trim().toLowerCase();
        const statusValue = statusFilter.value;
        const deadlineValue = deadlineFilter.value;

        taskItems.forEach((item) => {
            const taskName = item.dataset.taskName ?? '';
            const projectName = item.dataset.projectName ?? '';
            const taskStatus = item.dataset.taskStatus ?? '';
            const deadlineGroup = item.dataset.deadlineGroup ?? '';

            const matchesSearch =
                searchValue === '' ||
                taskName.includes(searchValue) ||
                projectName.includes(searchValue);
            const matchesStatus = statusValue === '' || taskStatus === statusValue;
            const matchesDeadline = deadlineValue === '' || deadlineGroup === deadlineValue;

            item.style.display = matchesSearch && matchesStatus && matchesDeadline ? '' : 'none';
        });
    };

    searchInput.addEventListener('input', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    deadlineFilter.addEventListener('change', applyFilters);
};

document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const mobileToggle = document.querySelector('[data-sidebar-mobile-toggle]');
    const overlay = document.querySelector('[data-sidebar-overlay]');

    document.querySelectorAll('[data-tab-target]').forEach((button) => {
        button.addEventListener('click', () => {
            const tabId = button.dataset.tabTarget;

            if (!tabId) {
                return;
            }

            activateTab(tabId);

            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', `#${tabId}`);
            } else {
                window.location.hash = tabId;
            }
        });
    });

    window.addEventListener('hashchange', () => {
        const hashTabId = window.location.hash.replace('#', '');

        if (hashTabId) {
            activateTab(hashTabId);
        }
    });

    sidebarToggle?.addEventListener('click', () => {
        const sidebar = document.querySelector('.sidebar');
        const nextShrinkState = !sidebar?.classList.contains('shrink');
        setSidebarState(nextShrinkState);
    });

    mobileToggle?.addEventListener('click', () => {
        const sidebar = document.querySelector('.sidebar');
        const isOpen = sidebar?.classList.toggle('mobile-open');
        overlay?.classList.toggle('active', Boolean(isOpen));
        mobileToggle.classList.toggle('active', Boolean(isOpen));
    });

    overlay?.addEventListener('click', closeMobileSidebar);

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            closeMobileSidebar();
        }
    });

    const hashTabId = window.location.hash.replace('#', '');
    const defaultTabId =
        document.querySelector('[data-tab-target].active')?.dataset.tabTarget ??
        document.querySelector('[data-tab-target]')?.dataset.tabTarget ??
        'projects-tab';

    activateTab(hashTabId || defaultTabId);
    initTaskFilters();
    setSidebarState(false);
});
