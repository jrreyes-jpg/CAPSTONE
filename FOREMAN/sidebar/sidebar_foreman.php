<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$currentQuery = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '';

$isDashboard = str_contains($currentPath, '/views/dashboards/foreman_dashboard.php') && $currentQuery === '';
$isChangePassword = str_contains($currentPath, '/views/dashboards/change_password.php');
?>
<link rel="stylesheet" href="../css/sidebar_foreman.css">
<nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="sidebar-toggle" type="button">
        <span id="toggleIcon">❮</span>
    </button>

    <div class="sidebar-header">
        <h3>🛠️ <span class="menu-text">EDGE AUTOMATION</span></h3>
        <p class="sidebar-subtitle menu-text">Foreman Panel</p>
    </div>

    <ul class="nav-menu">
        <li><a href="../dashboards/foreman_dashboard.php" class="menu-link<?php echo $isDashboard ? ' active' : ''; ?>">📊 <span class="menu-text">Dashboard</span></a></li>
        <li><button id="qrScannerBtn" class="menu-link">📱 <span class="menu-text">QR Scanner</span></button></li>
        <li><a href="../dashboards/change_password.php" class="menu-link<?php echo $isChangePassword ? ' active' : ''; ?>">🔐 <span class="menu-text">Change Password</span></a></li>
        <li><a href="../../LOGIN/php/logout.php" class="menu-link logout">🚪 <span class="menu-text">Logout</span></a></li>
    </ul>
</nav>
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<script src="../js/sidebar_foreman.js"></script>