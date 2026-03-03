<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'foreman') {
    header("Location: /codesamplecaps/public/login.php");
    exit();
}

// Placeholder values; controller should provide real data via prepared statements
$user_id = $_SESSION['user_id'];

$assignedProjectsCount = 0; // controller: count projects assigned via project_assignments
$assignedTasksCount = 0;    // controller: count tasks assigned to this foreman
$teamMembers = [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foreman Dashboard - Edge Automation</title>
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/global.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f6f8; }
        .main-content { margin-left: 250px; padding: 40px; }
        .stats-grid { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); flex: 1; }
    </style>
</head>
<body>

<?php include(__DIR__ . '/../components/sidebar_super_admin.php'); ?>

<div class="main-content">
    <h1>🛠️ Foreman Dashboard</h1>
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Assigned Projects</h4>
            <p style="font-size:28px; font-weight:700;"><?php echo $assignedProjectsCount; ?></p>
        </div>
        <div class="stat-card">
            <h4>Assigned Tasks</h4>
            <p style="font-size:28px; font-weight:700;"><?php echo $assignedTasksCount; ?></p>
        </div>
        <div class="stat-card">
            <h4>Team Members</h4>
            <p style="font-size:18px; color:#666;"><?php echo count($teamMembers); ?> members</p>
        </div>
    </div>

    <div class="card-section">
        <h2>On-site Progress</h2>
        <p>Use the task list to update manpower availability and site progress. This page should be backed by a controller that queries `project_assignments`, `tasks` and `foreman_profiles`.</p>
    </div>
</div>

</body>
</html>