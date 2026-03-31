<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'engineer') {
    header('Location: /codesamplecaps/LOGIN/php/login.php');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$taskStatusOptions = ['pending', 'ongoing', 'completed', 'delayed'];

function redirect_engineer_dashboard(string $hash = 'tasks-tab'): void {
    header('Location: /codesamplecaps/ENGINEER/dashboards/engineer_dashboard.php#' . $hash);
    exit();
}

function set_engineer_flash(string $type, string $message): void {
    $_SESSION['engineer_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function is_valid_csrf_token(?string $token): bool {
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function get_engineer_task_snapshot(mysqli $conn, int $taskId, int $engineerId): ?array {
    $stmt = $conn->prepare(
        "SELECT t.id, t.status, p.status AS project_status
         FROM tasks t
         INNER JOIN projects p ON p.id = t.project_id
         WHERE t.id = ?
         AND t.assigned_to = ?
         AND EXISTS (
             SELECT 1
             FROM project_assignments pa
             WHERE pa.project_id = p.id
             AND pa.engineer_id = ?
         )
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('iii', $taskId, $engineerId, $engineerId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result ? $result->fetch_assoc() : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        set_engineer_flash('error', 'Security check failed. Please try again.');
        redirect_engineer_dashboard();
    }

    if ($action === 'update_task_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));
        $task = $taskId > 0 ? get_engineer_task_snapshot($conn, $taskId, $userId) : null;

        if ($taskId <= 0 || !in_array($status, $taskStatusOptions, true)) {
            set_engineer_flash('error', 'Invalid task status update.');
            redirect_engineer_dashboard();
        }

        if (!$task) {
            set_engineer_flash('error', 'Task not found.');
            redirect_engineer_dashboard();
        }

        if (($task['project_status'] ?? '') === 'completed') {
            set_engineer_flash('error', 'This task is locked because the project is already completed.');
            redirect_engineer_dashboard();
        }

        $updateTask = $conn->prepare('UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?');

        if ($updateTask && $updateTask->bind_param('sii', $status, $taskId, $userId) && $updateTask->execute()) {
            set_engineer_flash('success', 'Task status updated.');
        } else {
            set_engineer_flash('error', 'Failed to update task status.');
        }

        redirect_engineer_dashboard();
    }
}

$flash = $_SESSION['engineer_flash'] ?? null;
unset($_SESSION['engineer_flash']);
$csrfToken = get_csrf_token();

$assignedCount = 0;
$inProgressCount = 0;
$completedCount = 0;
$assignedProjects = [];
$tasks = [];

$totalAssigned = $conn->prepare(
    "SELECT COUNT(DISTINCT pa.project_id)
     FROM project_assignments pa
     INNER JOIN projects p ON p.id = pa.project_id
     WHERE pa.engineer_id = ?
     AND p.status <> 'draft'"
);
if ($totalAssigned) {
    $totalAssigned->bind_param('i', $userId);
    $totalAssigned->execute();
    $totalAssigned->bind_result($assignedCount);
    $totalAssigned->fetch();
    $totalAssigned->close();
}

$inProgress = $conn->prepare(
    "SELECT COUNT(*)
     FROM projects p
     WHERE p.status = 'ongoing'
     AND EXISTS (
         SELECT 1
         FROM project_assignments pe
         WHERE pe.project_id = p.id
         AND pe.engineer_id = ?
     )"
);
if ($inProgress) {
    $inProgress->bind_param('i', $userId);
    $inProgress->execute();
    $inProgress->bind_result($inProgressCount);
    $inProgress->fetch();
    $inProgress->close();
}

$completedProjects = $conn->prepare(
    "SELECT COUNT(*)
     FROM projects p
     WHERE p.status = 'completed'
     AND EXISTS (
         SELECT 1
         FROM project_assignments pe
         WHERE pe.project_id = p.id
         AND pe.engineer_id = ?
     )"
);
if ($completedProjects) {
    $completedProjects->bind_param('i', $userId);
    $completedProjects->execute();
    $completedProjects->bind_result($completedCount);
    $completedProjects->fetch();
    $completedProjects->close();
}

$projectsStmt = $conn->prepare(
    "SELECT p.id, p.project_name, p.status, p.description, p.start_date, p.end_date, u.full_name AS client_name
     FROM projects p
     LEFT JOIN users u ON p.client_id = u.id
     WHERE EXISTS (
         SELECT 1
         FROM project_assignments pe
         WHERE pe.project_id = p.id
         AND pe.engineer_id = ?
     )
     AND p.status <> 'draft'
     ORDER BY p.status DESC, p.created_at DESC"
);
if ($projectsStmt) {
    $projectsStmt->bind_param('i', $userId);
    $projectsStmt->execute();
    $projectResult = $projectsStmt->get_result();
    if ($projectResult) {
        $assignedProjects = $projectResult->fetch_all(MYSQLI_ASSOC);
    }
    $projectsStmt->close();
}

$tasksStmt = $conn->prepare(
    "SELECT t.id, t.task_name, t.status, t.deadline, p.project_name, p.id AS project_id, p.status AS project_status
     FROM tasks t
     INNER JOIN projects p ON t.project_id = p.id
     WHERE t.assigned_to = ?
     AND p.status <> 'draft'
     AND EXISTS (
         SELECT 1
         FROM project_assignments pe
         WHERE pe.project_id = p.id
         AND pe.engineer_id = ?
     )
     ORDER BY
         CASE t.status
             WHEN 'pending' THEN 1
             WHEN 'ongoing' THEN 2
             WHEN 'delayed' THEN 3
             WHEN 'completed' THEN 4
             ELSE 5
         END,
         t.deadline IS NULL,
         t.deadline ASC,
         t.id DESC"
);
if ($tasksStmt) {
    $tasksStmt->bind_param('ii', $userId, $userId);
    $tasksStmt->execute();
    $taskResult = $tasksStmt->get_result();
    if ($taskResult) {
        $tasks = $taskResult->fetch_all(MYSQLI_ASSOC);
    }
    $tasksStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineer Dashboard - Edge Automation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/engineer_dashboard.css">
</head>
<body>

<?php include '../sidebar/sidebar_engineer.php'; ?>

<div class="main-content">
    <h1>Welcome, <?php echo htmlspecialchars((string)($_SESSION['name'] ?? 'Engineer')); ?></h1>

    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars((string)($flash['type'] ?? 'success')); ?>">
            <?php echo htmlspecialchars((string)($flash['message'] ?? '')); ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><h4>Assigned Projects</h4><p><?php echo (int)$assignedCount; ?></p></div>
        <div class="stat-card"><h4>In Progress</h4><p><?php echo (int)$inProgressCount; ?></p></div>
        <div class="stat-card"><h4>Completed</h4><p><?php echo (int)$completedCount; ?></p></div>
    </div>

    <div class="tabs">
        <button class="tab active" onclick="showTab('projects-tab', this)">My Projects</button>
        <button class="tab" onclick="showTab('tasks-tab', this)">Tasks</button>
        <button class="tab" onclick="showTab('profile-tab', this)">Profile</button>
    </div>

    <div id="projects-tab" class="tab-content active">
        <h2>Assigned Projects</h2>
        <div class="projects-grid">
            <?php if (!empty($assignedProjects)): ?>
                <?php foreach ($assignedProjects as $project): ?>
                    <div class="project-card">
                        <div class="project-name"><?php echo htmlspecialchars((string)$project['project_name']); ?></div>
                        <span class="status <?php echo htmlspecialchars((string)$project['status']); ?>"><?php echo htmlspecialchars(ucfirst((string)$project['status'])); ?></span>
                        <p><strong>Client:</strong> <?php echo htmlspecialchars((string)($project['client_name'] ?? 'N/A')); ?></p>
                        <p><strong>Start:</strong> <?php echo htmlspecialchars((string)($project['start_date'] ?? 'N/A')); ?></p>
                        <p><strong>End:</strong> <?php echo htmlspecialchars((string)($project['end_date'] ?? 'N/A')); ?></p>
                        <p style="color: #7f8c8d; font-size: 14px; margin-top: 10px;">
                            <?php echo htmlspecialchars(substr((string)($project['description'] ?? ''), 0, 100)); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data" style="grid-column: 1/-1;"><p>No assigned projects yet.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div id="tasks-tab" class="tab-content">
        <h2>My Tasks</h2>
        <div class="tasks-list">
            <?php if (!empty($tasks)): ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-item <?php echo htmlspecialchars((string)$task['status']); ?>">
                        <div>
                            <div class="task-name"><?php echo htmlspecialchars((string)$task['task_name']); ?></div>
                            <div class="task-project"><?php echo htmlspecialchars((string)$task['project_name']); ?></div>
                            <div class="task-deadline">Deadline: <?php echo htmlspecialchars((string)($task['deadline'] ?? 'No deadline')); ?></div>
                            <?php if (($task['project_status'] ?? '') === 'completed'): ?>
                                <div class="task-note">Project is completed, so this task is locked.</div>
                            <?php endif; ?>
                        </div>

                        <div class="task-actions">
                            <span class="status <?php echo htmlspecialchars((string)$task['status']); ?>"><?php echo htmlspecialchars(ucfirst((string)$task['status'])); ?></span>

                            <?php if (($task['project_status'] ?? '') === 'completed'): ?>
                                <button type="button" class="btn btn-secondary" disabled>Locked</button>
                            <?php else: ?>
                                <form method="POST" class="task-form">
                                    <input type="hidden" name="action" value="update_task_status">
                                    <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                                    <select name="status" aria-label="Task status" required>
                                        <?php foreach ($taskStatusOptions as $statusOption): ?>
                                            <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo ($task['status'] ?? '') === $statusOption ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(ucfirst($statusOption)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit" class="btn">Save Task Status</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data"><p>No tasks yet.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div id="profile-tab" class="tab-content">
        <h2>Profile Settings</h2>
        <div class="profile-form">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" value="<?php echo htmlspecialchars((string)($_SESSION['name'] ?? '')); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" value="<?php echo htmlspecialchars((string)($_SESSION['email'] ?? '')); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Availability Status</label>
                <select id="availability" disabled>
                    <option>available</option>
                    <option>assigned</option>
                    <option>on-leave</option>
                </select>
            </div>
            <button class="btn" onclick="window.location.href='/codesamplecaps/views/dashboards/change_password.php'">Change Password</button>
        </div>
    </div>
</div>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(function (el) {
        el.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(function (el) {
        el.classList.remove('active');
    });
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', '#' + tabId);
    }
}

function activateTabFromHash() {
    var tabId = window.location.hash.replace('#', '');
    if (!tabId) {
        return;
    }

    var targetTab = document.getElementById(tabId);
    var buttons = document.querySelectorAll('.tab');
    var matchedButton = null;

    buttons.forEach(function (button) {
        var onclickValue = button.getAttribute('onclick') || '';
        if (onclickValue.indexOf("'" + tabId + "'") !== -1) {
            matchedButton = button;
        }
    });

    if (targetTab && matchedButton) {
        showTab(tabId, matchedButton);
    }
}

document.addEventListener('DOMContentLoaded', activateTabFromHash);
</script>

</body>
</html>
