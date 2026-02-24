<style>
    .sidebar { 
        width: 250px; 
        background: linear-gradient(180deg, #0f9d38, #087f23);
        padding: 20px; 
        position: fixed; 
        left: 0; 
        top: 0; 
        height: 100vh; 
        box-shadow: 2px 0 8px rgba(0,0,0,0.15);
        font-family: 'Poppins', sans-serif;
        z-index: 1000;
    }
    .sidebar h3 { 
        color: white; 
        margin-bottom: 30px; 
        text-align: center; 
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 1px;
    }
    .sidebar-subtitle { 
        text-align: center; 
        font-size: 11px; 
        color: rgba(255,255,255,0.7); 
        margin-bottom: 20px;
        font-weight: 500;
    }
    .sidebar a { 
        display: block; 
        padding: 12px 15px; 
        color: #ecf0f1; 
        text-decoration: none; 
        border-radius: 5px; 
        margin: 8px 0; 
        transition: all 0.3s; 
        border-left: 3px solid transparent;
        font-size: 14px;
        font-weight: 500;
    }
    .sidebar a:hover { 
        background: rgba(255,255,255,0.15); 
        border-left-color: #fff; 
        transform: translateX(5px);
    }
    .sidebar a.logout { 
        background: #e74c3c; 
        border-left-color: #c0392b;
        margin-top: 40px;
        font-weight: 600;
    }
    .sidebar a.logout:hover { 
        background: #c0392b;
        transform: translateX(5px);
    }
</style>

<div class="sidebar">
    <h3>🏢 EDGE AUTOMATION</h3>
    <div class="sidebar-subtitle">Engineer Portal</div>
    <a href="engineer_dashboard.php">📊 Dashboard</a>
    <a href="engineer_dashboard.php#projects-tab">📁 My Projects</a>
    <a href="engineer_dashboard.php#tasks-tab">📋 My Tasks</a>
    <a href="../dashboards/change_password.php">🔐 Change Password</a>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
</div>
