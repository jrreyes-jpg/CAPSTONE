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
<link rel="stylesheet" href="/codesamplecaps/public/assets/css/client_dashboard.css">
</head>
<body>

<?php include("../../views/components/sidebar_client.php"); ?>

<main class="main-content">

<header class="dashboard-header">
<h1>💼 Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
</header>
    <div class="stats-grid">
        <div class="stat-card"><h4>📁 Your Projects</h4><p><?php echo $totalCount; ?></p></div>
        <div class="stat-card"><h4>⏳ In Progress</h4><p><?php echo $ongoingCount; ?></p></div>
        <div class="stat-card"><h4>✅ Completed</h4><p><?php echo $completedCount; ?></p></div>
        <div class="stat-card"><h4>👷 Engineers</h4><p><?php echo $availCount; ?></p></div>
    </div>
    
    <div class="tabs">
        <button class="tab" onclick="showTab('projects-tab', this)">📁 My Projects</button>
        <button class="tab" onclick="showTab('profile-tab', this)">⚙️ Profile</button>
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

</body>
<script src="/codesamplecaps/public/assets/js/client_dashboard.js"></script>
</html>
