<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* PROJECTS COUNT & STATUS */
$totalProjects = $conn->prepare("SELECT COUNT(*) FROM projects");
$totalProjects->execute();
$totalProjects->bind_result($projectsCount);
$totalProjects->fetch();
$totalProjects->close();

$ongoingProjects = $conn->prepare("SELECT COUNT(*) FROM projects WHERE status='ongoing'");
$ongoingProjects->execute();
$ongoingProjects->bind_result($ongoingCount);
$ongoingProjects->fetch();
$ongoingProjects->close();

/* ENGINEERS COUNT */
$totalEngineers = $conn->prepare("SELECT COUNT(*) FROM users WHERE role='engineer'");
$totalEngineers->execute();
$totalEngineers->bind_result($engineersCount);
$totalEngineers->fetch();
$totalEngineers->close();

$availableEngineers = $conn->prepare("SELECT COUNT(*) FROM users WHERE role='engineer' AND availability_status='available'");
$availableEngineers->execute();
$availableEngineers->bind_result($availCount);
$availableEngineers->fetch();
$availableEngineers->close();

/* CLIENTS COUNT */
$totalClients = $conn->prepare("SELECT COUNT(*) FROM users WHERE role='client'");
$totalClients->execute();
$totalClients->bind_result($clientsCount);
$totalClients->fetch();
$totalClients->close();

/* INVENTORY COUNT */
$totalInventory = $conn->prepare("SELECT COUNT(*) FROM inventory");
$totalInventory->execute();
$totalInventory->bind_result($inventoryCount);
$totalInventory->fetch();
$totalInventory->close();

/* FETCH RECENT PROJECTS */
$recentProjects = $conn->prepare("
    SELECT project_id, project_name, status, client_id, created_at 
    FROM projects 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recentProjects->execute();
$projects_list = $recentProjects->get_result();

/* FETCH ALL ENGINEERS */
$allEngineers = $conn->prepare("
    SELECT user_id, full_name, email, availability_status 
    FROM users 
    WHERE role='engineer' 
    ORDER BY full_name ASC
");
$allEngineers->execute();
$engineers_list = $allEngineers->get_result();

/* FETCH ALL CLIENTS */
$allClients = $conn->prepare("
    SELECT user_id, full_name, email 
    FROM users 
    WHERE role='client' 
    ORDER BY full_name ASC
");
$allClients->execute();
$clients_list = $allClients->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Edge Automation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/global.css">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background: #ecf0f1; }
    .main-content { margin-left: 250px; padding: 40px; min-height: 100vh; }
    h1 { color: #2c3e50; margin-bottom: 30px; font-size: 28px; }
    h2 { color: #2c3e50; border-bottom: 3px solid #0f9d38; padding-bottom: 10px; margin: 30px 0 20px 0; font-size: 20px; }
    h3 { color: #2c3e50; margin-bottom: 10px; font-size: 16px; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .stat-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s; border-top: 4px solid #0f9d38; }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.15); }
    .stat-card h4 { color: #7f8c8d; font-size: 12px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
    .stat-card p { font-size: 36px; font-weight: bold; color: #0f9d38; }
    
    .tabs { display: flex; gap: 10px; margin: 30px 0; border-bottom: 2px solid #ecf0f1; flex-wrap: wrap; }
    .tab { padding: 12px 20px; cursor: pointer; background: none; border: none; font-weight: 600; color: #7f8c8d; border-bottom: 3px solid transparent; transition: all 0.3s; }
    .tab.active { color: #0f9d38; border-bottom-color: #0f9d38; }
    .tab:hover { color: #2c3e50; }
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    
    .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }
    @media (max-width: 1200px) { .content-grid { grid-template-columns: 1fr; } }
    
    .card-section { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .card-section h3 { margin-bottom: 20px; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; }
    
    .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
    .project-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #0f9d38; transition: all 0.3s; }
    .project-card:hover { transform: translateY(-3px); box-shadow: 0 8px 16px rgba(0,0,0,0.15); }
    .project-name { font-size: 16px; font-weight: 700; color: #2c3e50; margin-bottom: 8px; }
    
    .users-table { width: 100%; border-collapse: collapse; }
    .users-table th { background: #0f9d38; color: white; padding: 12px; text-align: left; font-weight: 600; }
    .users-table td { padding: 12px; border-bottom: 1px solid #ecf0f1; }
    .users-table tr:hover { background: #f9fafb; }
    
    .status { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .status.pending { background: #fff3cd; color: #856404; }
    .status.ongoing { background: #d1ecf1; color: #0c5460; }
    .status.completed { background: #d4edda; color: #155724; }
    .status.available { background: #d4edda; color: #0f9d38; }
    .status.assigned { background: #e7f3ff; color: #004085; }
    
    .btn { padding: 10px 20px; background: #0f9d38; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; transition: background 0.3s; }
    .btn:hover { background: #087f23; }
    .btn-small { padding: 6px 12px; font-size: 12px; }
    .btn-secondary { background: #95a5a6; }
    .btn-secondary:hover { background: #7f8c8d; }
    
    .no-data { text-align: center; padding: 40px; color: #7f8c8d; }
    .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    .alert-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #0c5460; }
</style>
</head>
<body>

<?php include("../includes/sidebar_admin.php"); ?>

<div class="main-content">
    <h1>📊 Admin Dashboard</h1>
    
    <div class="stats-grid">
        <div class="stat-card"><h4>📁 Projects</h4><p><?php echo $projectsCount; ?></p></div>
        <div class="stat-card"><h4>⏳ Ongoing</h4><p><?php echo $ongoingCount; ?></p></div>
        <div class="stat-card"><h4>👨‍💼 Engineers</h4><p><?php echo $engineersCount; ?></p></div>
        <div class="stat-card"><h4>✅ Available</h4><p><?php echo $availCount; ?></p></div>
        <div class="stat-card"><h4>👥 Clients</h4><p><?php echo $clientsCount; ?></p></div>
        <div class="stat-card"><h4>📦 Inventory</h4><p><?php echo $inventoryCount; ?></p></div>
    </div>
    
    <div class="tabs">
        <button class="tab active" onclick="showTab('dashboard-tab', this)">📈 Dashboard</button>
        <button class="tab" onclick="showTab('projects-tab', this)">📁 Projects</button>
        <button class="tab" onclick="showTab('engineers-tab', this)">👨‍💼 Engineers</button>
        <button class="tab" onclick="showTab('clients-tab', this)">👥 Clients</button>
    </div>
    
    <div id="dashboard-tab" class="tab-content active">
        <div class="alert alert-info">
            <strong>🔔 System Status:</strong> All systems operational - <?php echo date('Y-m-d H:i:s'); ?>
        </div>
        
        <div class="content-grid">
            <div class="card-section">
                <h3>📊 Quick Actions</h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button class="btn" onclick="window.location.href='create_engineer.php'">➕ Create Engineer</button>
                    <button class="btn btn-secondary">📁 Create Project</button>
                    <button class="btn btn-secondary">📦 Add Inventory</button>
                </div>
            </div>
            
            <div class="card-section">
                <h3>📝 System Info</h3>
                <p><strong>Total Users:</strong> <?php echo $engineersCount + $clientsCount; ?></p>
                <p><strong>Active Projects:</strong> <?php echo $ongoingCount; ?></p>
                <p><strong>System Users:</strong> Admin, 
                   <?php echo $engineersCount; ?> Engineers, 
                   <?php echo $clientsCount; ?> Clients</p>
            </div>
        </div>
    </div>
    
    <div id="projects-tab" class="tab-content">
        <h2>📁 Recent Projects</h2>
        <?php if($projects_list->num_rows > 0): ?>
            <div class="projects-grid">
            <?php while($project = $projects_list->fetch_assoc()): ?>
                <div class="project-card">
                    <div class="project-name"><?php echo htmlspecialchars($project['project_name']); ?></div>
                    <span class="status <?php echo $project['status']; ?>"><?php echo ucfirst($project['status']); ?></span>
                    <p style="margin-top: 10px; font-size: 12px; color: #7f8c8d;">
                        Created: <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
                    </p>
                    <button class="btn btn-small" style="width: 100%; margin-top: 15px;">View Details</button>
                </div>
            <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-data"><p>📭 No projects found</p></div>
        <?php endif; ?>
    </div>
    
    <div id="engineers-tab" class="tab-content">
        <h2>👨‍💼 Engineers Management</h2>
        <?php if($engineers_list->num_rows > 0): ?>
            <div class="card-section">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($eng = $engineers_list->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($eng['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($eng['email']); ?></td>
                            <td>
                                <span class="status <?php echo $eng['availability_status']; ?>">
                                    <?php echo ucfirst($eng['availability_status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-small">Edit</button>
                                <button class="btn btn-small btn-secondary">Assign</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data"><p>👨‍💼 No engineers found</p></div>
        <?php endif; ?>
    </div>
    
    <div id="clients-tab" class="tab-content">
        <h2>👥 Clients Management</h2>
        <?php if($clients_list->num_rows > 0): ?>
            <div class="card-section">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Projects Assigned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($client = $clients_list->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td>
                                <?php 
                                    $projCount = $conn->prepare("SELECT COUNT(*) FROM projects WHERE client_id=?");
                                    $projCount->bind_param("i", $client['user_id']);
                                    $projCount->execute();
                                    $projCount->bind_result($pCount);
                                    $projCount->fetch();
                                    $projCount->close();
                                    echo $pCount;
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-small">Edit</button>
                                <button class="btn btn-small btn-secondary">View</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data"><p>👥 No clients found</p></div>
        <?php endif; ?>
    </div>
</div>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}
</script>

</body>
</html>
