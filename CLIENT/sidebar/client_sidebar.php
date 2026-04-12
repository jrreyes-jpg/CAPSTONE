<button id="sidebarMobileToggle" class="sidebar-mobile-toggle" type="button" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">
    <span></span>
    <span></span>
    <span></span>
</button>

<aside class="sidebar client-sidebar" id="sidebar" aria-label="Client navigation">
    <div class="sidebar-toggle-row">
        <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Collapse sidebar" aria-expanded="true">
            <span id="toggleIcon" class="sidebar-toggle-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M15 6 9 12 15 18"></path>
                </svg>
            </span>
        </button>
    </div>

    <nav class="nav-menu" aria-label="Client menu">
        <ul class="sidebar-menu">
            <li>
                <a href="../dashboards/client_dashboard.php#overview-section" class="menu-link" data-section-link="overview-section">
                    <span class="menu-visual" aria-hidden="true">
                        <span class="menu-icon">
                            <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false">
                                <path d="M4 13h7V4H4v9zm9 7h7V4h-7v16zM4 20h7v-5H4v5z"></path>
                            </svg>
                        </span>
                        <span class="menu-mini-label">Home</span>
                    </span>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="../dashboards/client_dashboard.php#projects-tab" class="menu-link" data-section-link="projects-tab">
                    <span class="menu-visual" aria-hidden="true">
                        <span class="menu-icon">
                            <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false">
                                <path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z"></path>
                            </svg>
                        </span>
                        <span class="menu-mini-label">Proj</span>
                    </span>
                    <span class="menu-text">My Projects</span>
                </a>
            </li>
            <li>
                <a href="../dashboards/client_dashboard.php#profile-tab" class="menu-link" data-section-link="profile-tab">
                    <span class="menu-visual" aria-hidden="true">
                        <span class="menu-icon">
                            <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false">
                                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8"></path>
                                <path d="M5 20a7 7 0 0 1 14 0"></path>
                            </svg>
                        </span>
                        <span class="menu-mini-label">Prof</span>
                    </span>
                    <span class="menu-text">Profile</span>
                </a>
            </li>
            <li>
                <a href="../../LOGIN/php/logout.php" class="menu-link logout client-sidebar__logout-link">
                    <span class="menu-visual" aria-hidden="true">
                        <span class="menu-icon">
                            <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false">
                                <path d="M10 5H6.5A1.5 1.5 0 0 0 5 6.5v11A1.5 1.5 0 0 0 6.5 19H10"></path>
                                <path d="M13 8l4 4-4 4"></path>
                                <path d="M9 12h8"></path>
                            </svg>
                        </span>
                        <span class="menu-mini-label">Exit</span>
                    </span>
                    <span class="menu-text">Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<div id="sidebarOverlay" class="sidebar-overlay"></div>
