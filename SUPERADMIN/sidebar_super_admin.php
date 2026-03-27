<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$currentQuery = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '';

$isDashboard = str_contains($currentPath, '/views/dashboards/super_admin_dashboard.php') && $currentQuery === '';
$isCreate = str_contains($currentPath, '/views/dashboards/super_admin_dashboard.php') && str_contains($currentQuery, 'tab=create');
$isUsers = str_contains($currentPath, '/views/dashboards/super_admin_dashboard.php') && str_contains($currentQuery, 'tab=users');
$isInventory = str_contains($currentPath, '/views/dashboards/inventory.php');
$isAssets = str_contains($currentPath, '/views/dashboards/assets.php');
$isReports = str_contains($currentPath, '/views/dashboards/reports.php');
$isChangePassword = str_contains($currentPath, '/views/dashboards/change_password.php');
$isScanHistory = str_contains($currentPath, '/views/dashboards/scan_history.php');
?>
<nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="sidebar-toggle" type="button">
        <span id="toggleIcon">❮</span>
    </button>

    <div class="sidebar-header">
        <h3>🏢 <span class="menu-text">EDGE AUTOMATION</span></h3>
        <p class="sidebar-subtitle menu-text">Super Admin Control Panel</p>
    </div>

    <ul class="nav-menu">
        <li><a href="./super_admin_dashboard.php?tab=dashboard" class="menu-link<?php echo $isDashboard ? ' active' : ''; ?>">📊 <span class="menu-text">Dashboard</span></a></li>
        <li><a href="./super_admin_dashboard.php?tab=create" class="menu-link<?php echo $isCreate ? ' active' : ''; ?>">➕ <span class="menu-text">Create Accounts</span></a></li>
        <li><a href="./super_admin_dashboard.php?tab=users" class="menu-link<?php echo $isUsers ? ' active' : ''; ?>">👥 <span class="menu-text">Manage Users</span></a></li>

        <li><a href="../sidebar/inventory.php" class="menu-link<?php echo $isInventory ? ' active' : ''; ?>">📦 <span class="menu-text">Inventory</span></a></li>
        <li><a href="../sidebar/assets.php" class="menu-link<?php echo $isAssets ? ' active' : ''; ?>">🏗️ <span class="menu-text">Assets</span></a></li>
        <li><a href="../sidebar/reports.php" class="menu-link<?php echo $isReports ? ' active' : ''; ?>">📈 <span class="menu-text">Reports</span></a></li>
        <li><a href="../sidebar/scan_history.php" class="menu-link<?php echo $isScanHistory ? ' active' : ''; ?>">🕑 <span class="menu-text">Scan History</span></a></li>

        <li><a href="dashboards/change_password.php" class="menu-link<?php echo $isChangePassword ? ' active' : ''; ?>">🔐 <span class="menu-text">Change Password</span></a></li>
        <li><a href="../LOGIN/php/logout.php" class="menu-link logout">🚪 <span class="menu-text">Logout</span></a></li>
    </ul>
</nav>
<div id="sidebarOverlay" class="sidebar-overlay"></div>
