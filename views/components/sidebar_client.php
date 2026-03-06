<nav class="sidebar client-sidebar">
    <div class="sidebar-header">
        <h3>🏢 EDGE AUTOMATION</h3>
        <p style="font-size: 11px; color: rgba(255,255,255,0.7); margin-top: 10px; font-weight: 500;">Client Portal</p>
    </div>
    <ul class="nav-menu">
        <li><a href="/codesamplecaps/views/dashboards/client_dashboard.php">📊 Dashboard</a></li>
        <li><a href="/codesamplecaps/views/dashboards/client_dashboard.php#engineers-tab">👷 Browse Engineers</a></li>
        <li><a href="/codesamplecaps/views/dashboards/client_dashboard.php#projects-tab">📁 My Projects</a></li>
        <li><a href="/codesamplecaps/views/dashboards/client_dashboard.php#profile-tab">⚙️ Profile Settings</a></li>
        <li><a href="/codesamplecaps/views/dashboards/change_password.php">🔐 Change Password</a></li>
        <li><a href="/codesamplecaps/views/auth/logout.php" class="logout">🚪 Logout</a></li>
    </ul>
</nav>

<style>
.client-sidebar {
    width: 250px;
    background: linear-gradient(180deg, #0f9d38, #087f23);
    color: #fff;
    padding: 20px;
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    z-index: 1000;
}
.client-sidebar .nav-menu { list-style: none; padding: 0; margin: 20px 0 0; }
.client-sidebar .nav-menu a {
    display: block;
    color: #ecf0f1;
    text-decoration: none;
    padding: 10px 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    font-weight: 500;
}
.client-sidebar .nav-menu a:hover { background: rgba(255,255,255,0.15); }
.client-sidebar .nav-menu a.logout { background: #dc2626; }

@media (max-width: 1024px) {
    .client-sidebar {
        position: relative;
        width: 100%;
        height: auto;
        min-height: auto;
    }
}
</style>
