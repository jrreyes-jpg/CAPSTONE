<?php
$clientSidebarName = trim((string)($_SESSION['name'] ?? 'Client User'));
$clientSidebarInitial = strtoupper(substr($clientSidebarName !== '' ? $clientSidebarName : 'C', 0, 1));
?>
<nav class="client-sidebar" id="clientSidebar" data-client-sidebar aria-label="Client navigation">
    <div class="client-sidebar__top">
        <div class="client-sidebar__brand">
            <span class="client-sidebar__brand-mark" aria-hidden="true">EA</span>
            <div class="client-sidebar__brand-copy">
                <span class="client-sidebar__eyebrow">Edge Automation</span>
                <strong>Client Portal</strong>
                <small>Projects, progress, and updates</small>
            </div>
        </div>
        <button
            type="button"
            class="client-sidebar__close"
            data-sidebar-mobile-close
            aria-label="Close navigation"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18"></path>
            </svg>
        </button>
    </div>

    <div class="client-sidebar__profile">
        <span class="client-sidebar__avatar" aria-hidden="true"><?php echo htmlspecialchars($clientSidebarInitial); ?></span>
        <div class="client-sidebar__profile-copy">
            <strong><?php echo htmlspecialchars($clientSidebarName); ?></strong>
            <span>Client Workspace</span>
        </div>
    </div>

    <button
        type="button"
        class="client-sidebar__collapse"
        data-sidebar-desktop-toggle
        aria-label="Collapse sidebar"
        aria-expanded="true"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M15 6l-6 6 6 6"></path>
        </svg>
    </button>

    <ul class="client-nav">
        <li>
            <a href="../dashboards/client_dashboard.php" class="client-nav__link active-link" data-nav-link="dashboard">
                <span class="client-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 13h7V4H4v9zm9 7h7V4h-7v16zM4 20h7v-5H4v5z"></path>
                    </svg>
                </span>
                <span class="client-nav__label">Dashboard</span>
            </a>
        </li>
        <li>
            <a href="../dashboards/client_dashboard.php#projects-tab" class="client-nav__link" data-section-link="projects-tab">
                <span class="client-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z"></path>
                    </svg>
                </span>
                <span class="client-nav__label">My Projects</span>
            </a>
        </li>
        <li>
            <a href="../dashboards/client_dashboard.php#profile-tab" class="client-nav__link" data-section-link="profile-tab">
                <span class="client-nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm-7 8a7 7 0 0 1 14 0"></path>
                    </svg>
                </span>
                <span class="client-nav__label">Profile</span>
            </a>
        </li>
    </ul>

    <a href="../../LOGIN/php/logout.php" class="client-sidebar__logout">
        <span class="client-nav__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <path d="M10 17l5-5-5-5"></path>
                <path d="M15 12H3"></path>
                <path d="M20 4h-6v4"></path>
                <path d="M20 20h-6v-4"></path>
            </svg>
        </span>
        <span class="client-nav__label">Logout</span>
    </a>
</nav>
