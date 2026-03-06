<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'client') {
    header("Location: /codesamplecaps/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* PROJECT SUMMARY COUNTS */
$totalProjects = $conn->prepare("SELECT COUNT(*) FROM projects WHERE client_id=?");
$totalProjects->bind_param("i",$user_id);
$totalProjects->execute();
$totalProjects->bind_result($totalCount);
$totalProjects->fetch();
$totalProjects->close();

$ongoing = $conn->prepare("SELECT COUNT(*) FROM projects WHERE client_id=? AND status='ongoing'");
$ongoing->bind_param("i",$user_id);
$ongoing->execute();
$ongoing->bind_result($ongoingCount);
$ongoing->fetch();
$ongoing->close();

$completed = $conn->prepare("SELECT COUNT(*) FROM projects WHERE client_id=? AND status='completed'");
$completed->bind_param("i",$user_id);
$completed->execute();
$completed->bind_result($completedCount);
$completed->fetch();
$completed->close();

/* TOTAL ENGINEERS COUNT (used in stats) */
$totalEngineersStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role='engineer'");
$totalEngineersStmt->execute();
$totalEngineersStmt->bind_result($availCount);
$totalEngineersStmt->fetch();
$totalEngineersStmt->close();

/* FETCH ENGINEERS */
$engineersStmt = $conn->prepare("SELECT id, full_name, status FROM users WHERE role='engineer' ORDER BY full_name ASC");
$engineersStmt->execute();
$available_engineers = $engineersStmt->get_result();

$projectsStmt = $conn->prepare("
SELECT p.*, u.full_name AS engineer_name
FROM projects p
LEFT JOIN project_assignments pa ON pa.project_id = p.id
LEFT JOIN users u ON u.id = pa.engineer_id
WHERE p.client_id=?
ORDER BY p.created_at DESC
");$projectsStmt->bind_param("i",$user_id);
$projectsStmt->execute();
$client_projects = $projectsStmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Dashboard - Edge Automation</title>
<link rel="stylesheet" href="/codesamplecaps/public/assets/css/global.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    body { background: #f5f7fa; }
    .main-content { margin-left: 250px; padding: 40px; }
    h1 { color: #2c3e50; }
    h2 { color: #2c3e50; border-bottom: 3px solid #0f9d38; padding-bottom: 10px; margin-bottom: 20px; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .stat-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s; }
    .stat-card:hover { transform: translateY(-5px); }
    .stat-card h4 { color: #7f8c8d; font-size: 14px; margin-bottom: 10px; }
    .stat-card p { font-size: 36px; font-weight: bold; color: #0f9d38; }
    .tabs { display: flex; gap: 10px; margin: 30px 0; border-bottom: 2px solid #ecf0f1; }
    .tab { padding: 12px 20px; cursor: pointer; background: none; border: none; font-weight: 600; color: #7f8c8d; border-bottom: 3px solid transparent; transition: all 0.3s; }
    .tab.active { color: #0f9d38; border-bottom-color: #0f9d38; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .engineers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
    .engineer-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-top: 4px solid #0f9d38; }
    .engineer-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.15); }
    .engineer-name { font-size: 18px; font-weight: 700; color: #2c3e50; }
    .availability { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin: 10px 0; }
    .availability.available { background: #d4edda; color: #0f9d38; }
    .engineer-card p { color: #7f8c8d; margin: 8px 0; font-size: 14px; }
    .btn { padding: 10px 20px; background: #0f9d38; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; transition: background 0.3s; }
    .btn:hover { background: #087f23; }
    .btn:disabled { background: #bdc3c7; cursor: not-allowed; }
    .btn-secondary { background: #95a5a6; }
    .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
    .project-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #0f9d38; }
    .project-card:hover { transform: translateY(-3px); }
    .project-name { font-size: 16px; font-weight: 700; color: #2c3e50; }
    .status { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 15px; }
    .status.pending { background: #fff3cd; }
    .status.ongoing { background: #d1ecf1; }
    .status.completed { background: #d4edda; }
    .no-data { text-align: center; padding: 50px; color: #7f8c8d; }
    .action-buttons { display: flex; gap: 10px; margin-top: 15px; }
    .action-buttons .btn { flex: 1; }
    .profile-form { background: white; padding: 25px; border-radius: 8px; max-width: 500px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { color: #2c3e50; font-weight: 600; display: block; margin-bottom: 8px; }
    .form-group input { width: 100%; padding: 10px; border: 1px solid #bdc3c7; border-radius: 5px; }

@media (max-width: 768px) {
    body { overflow-x: hidden; }
    .sidebar { position: relative; width: 100%; height: auto; min-height: auto; }
    .main-content { margin-left: 0; padding: 16px; }
    .tabs { flex-wrap: wrap; gap: 8px; }
    .tab { width: 100%; text-align: center; }
    .engineers-grid, .projects-grid, .stats-grid { grid-template-columns: 1fr; }
    .project-card, .engineer-card, .profile-form { width: 100%; }
}

</style>
</head>
<body>

<?php include("../../views/components/sidebar_client.php"); ?>

<div class="main-content">
    <h1>💼 Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
    
    <div class="stats-grid">
        <div class="stat-card"><h4>📁 Your Projects</h4><p><?php echo $totalCount; ?></p></div>
        <div class="stat-card"><h4>⏳ In Progress</h4><p><?php echo $ongoingCount; ?></p></div>
        <div class="stat-card"><h4>✅ Completed</h4><p><?php echo $completedCount; ?></p></div>
        <div class="stat-card"><h4>👷 Engineers</h4><p><?php echo $availCount; ?></p></div>
    </div>
    
    <div class="tabs">
        <button class="tab active" onclick="showTab('engineers-tab', this)">👷 Request/Assign Edge Engineer</button>
        <button class="tab" onclick="showTab('projects-tab', this)">📁 My Projects</button>
        <button class="tab" onclick="showTab('profile-tab', this)">⚙️ Profile</button>
    </div>
    
    <div id="engineers-tab" class="tab-content active">
        <h2>Engineers for Project Scope</h2>
        <p style="color: #7f8c8d; margin-bottom: 20px;">Browse engineers and see how many active projects they currently handle (capacity defaults to 1).</p>
        <div class="engineers-grid">
        <?php if($available_engineers->num_rows > 0):
            $countActiveStmt = $conn->prepare("SELECT COUNT(*) FROM project_engineers pe JOIN projects p ON pe.project_id=p.project_id WHERE pe.engineer_id=? AND p.status!='completed'");
            while($engineer = $available_engineers->fetch_assoc()):
                $engId = $engineer['user_id'];
                $countActiveStmt->bind_param("i", $engId);
                $countActiveStmt->execute();
                $countActiveStmt->bind_result($activeProjects);
                $countActiveStmt->fetch();
                $capacity = isset($engineer['capacity']) ? (int)$engineer['capacity'] : 1;
                $slotsLeft = $capacity - $activeProjects;
        ?>
            <div class="engineer-card">
                <div class="engineer-name">👨‍💼 <?php echo htmlspecialchars($engineer['full_name']); ?></div>
                <p style="margin:8px 0;"><strong>Active Projects:</strong> <?php echo $activeProjects; ?> &nbsp;•&nbsp; <strong>Capacity:</strong> <?php echo $capacity; ?></p>
                <p><strong>User ID:</strong> #<?php echo $engineer['user_id']; ?></p>
                <div class="action-buttons">
                    <?php if($slotsLeft > 0): ?>
                        <button class="btn" onclick="hireEngineer(<?php echo $engineer['user_id']; ?>, '<?php echo htmlspecialchars($engineer['full_name']); ?>')">📨 Request</button>
                    <?php else: ?>
                        <button class="btn" disabled>Fully Booked</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; $countActiveStmt->close(); else: ?>
            <div class="no-data" style="grid-column: 1/-1;"><p>😔 No available engineers</p></div>
        <?php endif; ?>
        </div>
    </div>
    
    <div id="projects-tab" class="tab-content">
        <h2>My Projects</h2>
        <div class="projects-grid">
        <?php if($client_projects->num_rows > 0):
            while($proj = $client_projects->fetch_assoc()): ?>
            <div class="project-card">
                <h3><?php echo htmlspecialchars($proj['project_name']); ?></h3>
                <span class="status <?php echo $proj['status']; ?>">📊 <?php echo ucfirst($proj['status']); ?></span>
                <p><strong>👨‍💼 Engineer:</strong> <?php echo htmlspecialchars($proj['engineer_name'] ?? 'Not Assigned'); ?></p>
                <p style="color: #7f8c8d; font-size: 14px;"><?php echo htmlspecialchars(substr($proj['description'] ?? '', 0, 150)); ?></p>
            </div>
        <?php endwhile; else: ?>
            <div class="no-data" style="grid-column: 1/-1;"><p>📁 No projects yet</p></div>
        <?php endif; ?>
        </div>
    </div>
    
    <div id="profile-tab" class="tab-content">
        <h2>Profile Settings</h2>
        <div class="profile-form">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" value="<?php echo htmlspecialchars($_SESSION['name']); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" disabled>
            </div>
            <button class="btn" onclick="window.location.href='/codesamplecaps/views/dashboards/change_password.php'">🔐 Change Password</button>
        </div>
    </div>
</div>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}

function hireEngineer(engineerId, engineerName) {
    if(confirm('Hire ' + engineerName + '?')) {
        alert('Hire request submitted! This feature requires backend implementation.');
    }
}
</script>

</body>
</html>
