<?php
$currentPath = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$currentQuery = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '';

$isDashboardPage = str_contains($currentPath, '/SUPERADMIN/dashboards/super_admin_dashboard.php');
$isDashboard = $isDashboardPage && ($currentQuery === '' || str_contains($currentQuery, 'tab=dashboard'));
$isCreate = $isDashboardPage && str_contains($currentQuery, 'tab=create');
$isUsers = $isDashboardPage && str_contains($currentQuery, 'tab=users');
$isProjects = str_contains($currentPath, '/SUPERADMIN/sidebar/projects.php');
$isInventory = str_contains($currentPath, '/SUPERADMIN/sidebar/inventory.php');
$isAssets = str_contains($currentPath, '/SUPERADMIN/sidebar/assets.php');
$isScanHistory = str_contains($currentPath, '/SUPERADMIN/sidebar/scan_history.php');
?>
<button id="sidebarMobileToggle" class="sidebar-mobile-toggle" type="button" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">
    <span></span>
    <span></span>
    <span></span>
</button>
<nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Collapse sidebar" aria-controls="sidebar" aria-expanded="true">
        <span id="toggleIcon">&#10094;</span>
    </button>

    <div class="sidebar-header">
        <h3>&#127970; <span class="menu-text">EDGE AUTOMATION</span></h3>
        <p class="sidebar-subtitle menu-text">Super Admin</p>
    </div>

    <ul class="nav-menu">
        <li><a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=dashboard" class="menu-link<?php echo $isDashboard ? ' active' : ''; ?>">&#128202; <span class="menu-text">Dashboard</span></a></li>
        <li><a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=create" class="menu-link<?php echo $isCreate ? ' active' : ''; ?>">&#10133; <span class="menu-text">Create Accounts</span></a></li>
        <li><a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=users" class="menu-link<?php echo $isUsers ? ' active' : ''; ?>">&#128101; <span class="menu-text">Manage Users</span></a></li>
        <li><a href="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="menu-link<?php echo $isProjects ? ' active' : ''; ?>">&#128193; <span class="menu-text">Projects</span></a></li>
        <li><a href="/codesamplecaps/SUPERADMIN/sidebar/inventory.php" class="menu-link<?php echo $isInventory ? ' active' : ''; ?>">&#128230; <span class="menu-text">Inventory</span></a></li>
        <li><a href="/codesamplecaps/SUPERADMIN/sidebar/assets.php" class="menu-link<?php echo $isAssets ? ' active' : ''; ?>">&#127959;&#65039; <span class="menu-text">Assets</span></a></li>
        <li><a href="/codesamplecaps/SUPERADMIN/sidebar/scan_history.php" class="menu-link<?php echo $isScanHistory ? ' active' : ''; ?>">&#128337; <span class="menu-text">Scan History</span></a></li>
        <li><a href="/codesamplecaps/LOGIN/php/logout.php" class="menu-link logout">&#128682; <span class="menu-text">Logout</span></a></li>
    </ul>
</nav>
<div id="sidebarOverlay" class="sidebar-overlay"></div>
<header class="global-topbar" aria-live="polite">
    <div class="global-topbar__copy">
        <strong>EDGE Automation</strong>
        <span>Super Admin Panel</span>
    </div>
    <div class="global-topbar__clock">
        <span class="global-topbar__clock-label">Philippines Time</span>
        <strong class="global-topbar__time" data-ph-time>--:--:--</strong>
        <span class="global-topbar__date" data-ph-date>Loading date...</span>
    </div>
</header>
