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
        const isActive = link.dataset.sectionLink === tabId;
        link.classList.toggle('active-link', isActive);
        link.setAttribute('aria-current', isActive ? 'page' : 'false');
    });
};

const replaceHash = (tabId) => {
    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', `#${tabId}`);
    } else {
        window.location.hash = tabId;
    }
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
        toggleButton.setAttribute('aria-label', isShrink ? 'Expand menu' : 'Collapse menu');
        toggleButton.setAttribute('aria-expanded', String(!isShrink));
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

const spotlightTask = (taskId) => {
    if (!taskId) {
        return;
    }

    const taskItem = document.querySelector(`[data-task-item-id="${taskId}"]`);

    if (!taskItem) {
        return;
    }

    taskItem.classList.add('is-spotlight');
    taskItem.scrollIntoView({ behavior: 'smooth', block: 'center' });

    window.setTimeout(() => {
        taskItem.classList.remove('is-spotlight');
    }, 2200);
};

const initTaskFilters = () => {
    const searchInput = document.querySelector('[data-task-search]');
    const statusFilter = document.querySelector('[data-task-status-filter]');
    const deadlineFilter = document.querySelector('[data-task-deadline-filter]');
    const taskItems = Array.from(document.querySelectorAll('[data-task-item]'));
    const quickButtons = Array.from(document.querySelectorAll('[data-task-quick-filter]'));
    const taskJumpButtons = Array.from(document.querySelectorAll('[data-open-task-id]'));

    if (!searchInput || !statusFilter || !deadlineFilter) {
        return;
    }

    let quickFilterValue = '';

    const setQuickFilter = (value) => {
        quickFilterValue = value;

        quickButtons.forEach((button) => {
            const buttonFilter = button.dataset.taskQuickFilter ?? '';
            button.classList.toggle('active', value !== '' && buttonFilter === value);
        });
    };

    const openTasksTab = () => {
        activateTab('tasks-tab');
        replaceHash('tasks-tab');
    };

    const resetStandardFilters = () => {
        searchInput.value = '';
        statusFilter.value = '';
        deadlineFilter.value = '';
    };

    const applyFilters = () => {
        const searchValue = searchInput.value.trim().toLowerCase();
        const statusValue = statusFilter.value;
        const deadlineValue = deadlineFilter.value;

        taskItems.forEach((item) => {
            const taskName = item.dataset.taskName ?? '';
            const projectName = item.dataset.projectName ?? '';
            const taskStatus = item.dataset.taskStatus ?? '';
            const deadlineGroup = item.dataset.deadlineGroup ?? '';
            const hasUpdate = item.dataset.taskHasUpdate ?? 'no';
            const isDueToday = item.dataset.taskIsDueToday ?? 'no';
            const isOverdue = item.dataset.taskIsOverdue ?? 'no';
            const isBlocked = item.dataset.taskIsBlocked ?? 'no';
            const isLocked = item.dataset.taskIsLocked ?? 'no';

            const matchesSearch =
                searchValue === '' ||
                taskName.includes(searchValue) ||
                projectName.includes(searchValue);
            const matchesStatus = statusValue === '' || taskStatus === statusValue;
            const matchesDeadline = deadlineValue === '' || deadlineGroup === deadlineValue;
            const matchesQuick =
                quickFilterValue === '' ||
                (quickFilterValue === 'all-open' && taskStatus !== 'completed' && isLocked !== 'yes') ||
                (quickFilterValue === 'overdue' && isOverdue === 'yes') ||
                (quickFilterValue === 'due-today' && isDueToday === 'yes') ||
                (quickFilterValue === 'no-update' && hasUpdate === 'no' && taskStatus !== 'completed' && isLocked !== 'yes') ||
                (quickFilterValue === 'blocked' && isBlocked === 'yes' && isLocked !== 'yes');

            item.hidden = !(matchesSearch && matchesStatus && matchesDeadline && matchesQuick);
        });
    };

    quickButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const nextQuickFilter = button.dataset.taskQuickFilter ?? '';
            const isResetButton = button.hasAttribute('data-reset-task-filters');

            resetStandardFilters();
            setQuickFilter(isResetButton ? '' : nextQuickFilter);
            applyFilters();
            openTasksTab();
            document.getElementById('tasks-tab')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    taskJumpButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const taskId = button.dataset.openTaskId ?? '';

            resetStandardFilters();
            setQuickFilter('');
            applyFilters();
            openTasksTab();

            window.setTimeout(() => {
                spotlightTask(taskId);
            }, 120);
        });
    });

    searchInput.addEventListener('input', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    deadlineFilter.addEventListener('change', applyFilters);

    applyFilters();
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
            replaceHash(tabId);
        });
    });

    document.querySelectorAll('[data-section-link]').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeMobileSidebar();
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
    const activeTabButton = document.querySelector('[data-tab-target].active');
    const firstTabButton = document.querySelector('[data-tab-target]');
    const defaultTabId =
        hashTabId ||
        activeTabButton?.dataset.tabTarget ||
        firstTabButton?.dataset.tabTarget ||
        'dashboard-tab';

    activateTab(defaultTabId);
    initTaskFilters();
    setSidebarState(false);
});
