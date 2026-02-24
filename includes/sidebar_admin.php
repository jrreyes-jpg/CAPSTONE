<style>
    .sidebar { 
        width: 250px; 
        background: linear-gradient(180deg, #0f9d38, #087f23);
        color: white; 
        padding: 20px; 
        min-height: 100vh; 
        position: fixed; 
        left: 0; 
        top: 0;
        box-shadow: 2px 0 8px rgba(0,0,0,0.15);
        font-family: 'Poppins', sans-serif;
        z-index: 1000;
    }
    .sidebar-header { margin-bottom: 30px; text-align: center; }
    .sidebar-header h3 { 
        font-size: 16px; 
        font-weight: 700; 
        letter-spacing: 2px; 
        color: white;
        margin-bottom: 10px;
    }
    .sidebar-header p { font-size: 11px; color: rgba(255,255,255,0.7); }
    .nav-menu { list-style: none; margin-top: 30px; }
    .nav-menu li { margin: 8px 0; }
    .nav-menu a { 
        color: #ecf0f1; 
        text-decoration: none; 
        display: block; 
        padding: 12px 15px; 
        border-radius: 5px; 
        transition: all 0.3s;
        border-left: 3px solid transparent;
        font-size: 14px;
        font-weight: 500;
    }
    .nav-menu a:hover { 
        background: rgba(255,255,255,0.15);
        border-left-color: #fff;
        transform: translateX(5px);
    }
    .nav-menu a.logout { 
        background: #e74c3c; 
        border-left-color: #c0392b;
        margin-top: 40px;
        font-weight: 600;
    }
    .nav-menu a.logout:hover { 
        background: #c0392b;
        transform: translateX(5px);
    }
</style>

<nav class="sidebar">
    <div class="sidebar-header">
        <h3>🏢 EDGE AUTOMATION</h3>
        <p>Admin Control Panel</p>
    </div>
    <ul class="nav-menu">
        <li><a href="../dashboards/admin_dashboard.php">📊 Dashboard</a></li>
        <li><a href="../dashboards/create_engineer.php">➕ Create Engineer</a></li>
        <li><a href="../dashboards/admin_dashboard.php#projects-tab">📁 Projects</a></li>
        <li><a href="#">📦 Inventory</a></li>
        <li><a href="#">🏗️ Assets</a></li>
        <li><a href="#">📈 Reports</a></li>
        <li><a href="../dashboards/change_password.php">🔐 Change Password</a></li>
        <li><a href="../auth/logout.php" class="logout">🚪 Logout</a></li>
    </ul>
</nav>
