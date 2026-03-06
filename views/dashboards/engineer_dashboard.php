<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'engineer') {
    header("Location: /codesamplecaps/public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ASSIGNED PROJECTS COUNT */
$totalAssigned = $conn->prepare("
    SELECT COUNT(*)
    FROM project_assignments pa
    WHERE pa.user_id = ? AND pa.role_in_project = 'engineer'
");
$totalAssigned->bind_param("i",$user_id);
$totalAssigned->execute();
$totalAssigned->bind_result($assignedCount);
$totalAssigned->fetch();
$totalAssigned->close();

/* IN PROGRESS PROJECTS */
$inProgress = $conn->prepare("
    SELECT COUNT(*) FROM projects p
    JOIN project_assignments pa ON p.project_id = pa.project_id
    WHERE pa.user_id = ? AND pa.role_in_project = 'engineer' AND p.status='ongoing'
");
$inProgress->bind_param("i",$user_id);
$inProgress->execute();
$inProgress->bind_result($inProgressCount);
$inProgress->fetch();
$inProgress->close();

/* COMPLETED PROJECTS */
$completedProjects = $conn->prepare("
    SELECT COUNT(*) FROM projects p
    JOIN project_assignments pa ON p.project_id = pa.project_id
    WHERE pa.user_id = ? AND pa.role_in_project = 'engineer' AND p.status='completed'
");
$completedProjects->bind_param("i",$user_id);
$completedProjects->execute();
$completedProjects->bind_result($completedCount);
$completedProjects->fetch();
$completedProjects->close();

/* FETCH ASSIGNED PROJECTS */
$projectsStmt = $conn->prepare("
    SELECT p.project_id, p.project_name, p.status, p.description, p.start_date, p.end_date, u.full_name as client_name
    FROM projects p
    JOIN project_assignments pa ON p.project_id = pa.project_id
    LEFT JOIN users u ON p.client_id = u.id
    WHERE pa.user_id = ? AND pa.role_in_project = 'engineer'
    ORDER BY p.status DESC, p.created_at DESC
");
$projectsStmt->bind_param("i",$user_id);
$projectsStmt->execute();
$assigned_projects = $projectsStmt->get_result();

/* FETCH TASKS FOR ENGINEER */
$tasksStmt = $conn->prepare("
    SELECT t.task_id, t.task_name, t.status, t.deadline, p.project_name, p.project_id
    FROM tasks t
    JOIN projects p ON t.project_id = p.project_id
    JOIN project_assignments pa ON p.project_id = pa.project_id
    WHERE pa.user_id = ? AND pa.role_in_project = 'engineer'
    ORDER BY t.deadline ASC
");
$tasksStmt->bind_param("i",$user_id);
$tasksStmt->execute();
$tasks_list = $tasksStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineer Dashboard - Edge Automation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/global.css">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background: #ecf0f1; }
    .main-content { margin-left: 250px; padding: 40px; }
    h1 { color: #2c3e50; margin-bottom: 30px; }
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
    .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
    .project-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #0f9d38; }
    .project-card:hover { transform: translateY(-3px); box-shadow: 0 8px 16px rgba(0,0,0,0.15); }
    .project-name { font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 10px; }
    .status { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 15px; }
    .status.pending { background: #fff3cd; color: #856404; }
    .status.ongoing { background: #d1ecf1; color: #0c5460; }
    .status.completed { background: #d4edda; color: #155724; }
    .status.on-hold { background: #f8d7da; color: #721c24; }
    .tasks-list { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .task-item { padding: 15px; border-left: 4px solid #0f9d38; background: #f9fafb; margin-bottom: 12px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
    .task-item.completed { border-left-color: #0f9d38; background: #f0fdf4; }
    .task-item.pending { border-left-color: #f39c12; background: #fffbea; }
    .task-name { font-weight: 600; color: #2c3e50; }
    .task-project { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
    .task-deadline { font-size: 12px; color: #7f8c8d; }
    .no-data { text-align: center; padding: 50px; color: #7f8c8d; }
    .btn { padding: 10px 20px; background: #0f9d38; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 15px; }
    .btn:hover { background: #087f23; }
    .btn-secondary { background: #95a5a6; }
    .btn-secondary:hover { background: #7f8c8d; }
    .profile-form { background: white; padding: 25px; border-radius: 8px; max-width: 500px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 20px; }
    .form-group label { color: #2c3e50; font-weight: 600; display: block; margin-bottom: 8px; }
    .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #bdc3c7; border-radius: 5px; font-family: 'Poppins', sans-serif; }

@media (max-width: 768px) {
    body { overflow-x: hidden; }
    .main-content { margin-left: 0 !important; padding: 16px !important; }
    .stats-grid, .projects-grid { grid-template-columns: 1fr !important; }
    .tabs { flex-wrap: wrap; gap: 8px; }
    .tab { width: 100%; text-align: center; }
    .project-card { width: 100%; }
}

</style>
</head>
<body>

<?php include("../../views/components/sidebar_engineer.php"); ?>

<div class="main-content">
    <h1>👨‍💼 Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
    
    <div class="stats-grid">
        <div class="stat-card"><h4>📊 Assigned Projects</h4><p><?php echo $assignedCount; ?></p></div>
        <div class="stat-card"><h4>⏳ In Progress</h4><p><?php echo $inProgressCount; ?></p></div>
        <div class="stat-card"><h4>✅ Completed</h4><p><?php echo $completedCount; ?></p></div>
    </div>
    
    <div class="tabs">
        <button class="tab active" onclick="showTab('projects-tab', this)">📁 My Projects</button>
        <button class="tab" onclick="showTab('tasks-tab', this)">📋 Tasks</button>
        <button class="tab" onclick="showTab('profile-tab', this)">⚙️ Profile</button>
    </div>
    
    <div id="projects-tab" class="tab-content active">
        <h2>Assigned Projects</h2>
        <div class="projects-grid">
        <?php if($assigned_projects->num_rows > 0):
            while($project = $assigned_projects->fetch_assoc()): ?>
            <div class="project-card">
                <div class="project-name">📁 <?php echo htmlspecialchars($project['project_name']); ?></div>
                <span class="status <?php echo $project['status']; ?>">📊 <?php echo ucfirst($project['status']); ?></span>
                <p><strong>👤 Client:</strong> <?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?></p>
                <p><strong>📅 Start:</strong> <?php echo $project['start_date'] ?? 'N/A'; ?></p>
                <p><strong>🏁 End:</strong> <?php echo $project['end_date'] ?? 'N/A'; ?></p>
                <p style="color: #7f8c8d; font-size: 14px; margin-top: 10px;"><?php echo htmlspecialchars(substr($project['description'] ?? '', 0, 100)); ?></p>
            </div>
        <?php endwhile; else: ?>
            <div class="no-data" style="grid-column: 1/-1;"><p>📭 No assigned projects yet</p></div>
        <?php endif; ?>
        </div>
    </div>
    
    <div id="tasks-tab" class="tab-content">
        <h2>My Tasks</h2>
        <div class="tasks-list">
        <?php if($tasks_list->num_rows > 0):
            while($task = $tasks_list->fetch_assoc()): ?>
            <div class="task-item <?php echo $task['status']; ?>">
                <div>
                    <div class="task-name">✓ <?php echo htmlspecialchars($task['task_name']); ?></div>
                    <div class="task-project">📁 <?php echo htmlspecialchars($task['project_name']); ?></div>
                    <div class="task-deadline">⏰ Deadline: <?php echo $task['deadline'] ?? 'No deadline'; ?></div>
                </div>
                <span class="status <?php echo $task['status']; ?>"><?php echo ucfirst($task['status']); ?></span>
            </div>
        <?php endwhile; else: ?>
            <div class="no-data"><p>✅ No tasks yet</p></div>
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
            <div class="form-group">
                <label>Availability Status</label>
                <select id="availability" disabled>
                    <option>available</option>
                    <option>assigned</option>
                    <option>on-leave</option>
                </select>
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
</script>

</body>
</html>
