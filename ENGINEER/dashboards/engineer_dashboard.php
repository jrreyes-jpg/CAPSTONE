<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/audit_log.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'engineer') {
    header('Location: /codesamplecaps/LOGIN/php/login.php');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$taskStatusOptions = ['pending', 'ongoing', 'completed', 'delayed'];

function normalize_text_or_null(?string $value): ?string {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function ensure_engineer_task_updates_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS engineer_task_updates (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            task_id INT(11) NOT NULL,
            engineer_id INT(11) NOT NULL,
            status_snapshot VARCHAR(50) DEFAULT NULL,
            progress_note TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_engineer_task_updates_task (task_id),
            KEY idx_engineer_task_updates_engineer (engineer_id),
            KEY idx_engineer_task_updates_created_at (created_at),
            CONSTRAINT fk_engineer_task_updates_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
            CONSTRAINT fk_engineer_task_updates_engineer FOREIGN KEY (engineer_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function build_deadline_meta(?string $deadline, string $status): array {
    if ($deadline === null || $deadline === '') {
        return [
            'label' => 'No deadline',
            'class' => 'is-neutral',
        ];
    }

    $today = new DateTimeImmutable('today');
    $deadlineDate = DateTimeImmutable::createFromFormat('Y-m-d', $deadline);

    if (!$deadlineDate) {
        return [
            'label' => $deadline,
            'class' => 'is-neutral',
        ];
    }

    $days = (int)$today->diff($deadlineDate)->format('%r%a');

    if ($status !== 'completed' && $days < 0) {
        return [
            'label' => 'Overdue by ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's'),
            'class' => 'is-danger',
        ];
    }

    if ($status !== 'completed' && $days <= 2) {
        return [
            'label' => $days === 0 ? 'Due today' : 'Due in ' . $days . ' day' . ($days === 1 ? '' : 's'),
            'class' => 'is-warning',
        ];
    }

    return [
        'label' => 'Due ' . $deadlineDate->format('M j, Y'),
        'class' => 'is-ok',
    ];
}

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
        "SELECT t.id, t.status, t.task_name, t.description, p.status AS project_status, p.project_name
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

ensure_engineer_task_updates_table($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        set_engineer_flash('error', 'Security check failed. Please try again.');
        redirect_engineer_dashboard();
    }

    if ($action === 'save_task_update') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));
        $progressNote = normalize_text_or_null($_POST['progress_note'] ?? null);
        $task = $taskId > 0 ? get_engineer_task_snapshot($conn, $taskId, $userId) : null;

        if ($taskId <= 0 || !in_array($status, $taskStatusOptions, true)) {
            set_engineer_flash('error', 'Invalid task update.');
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

        if ($progressNote !== null && mb_strlen($progressNote) > 1000) {
            set_engineer_flash('error', 'Progress note is too long.');
            redirect_engineer_dashboard();
        }

        if ($progressNote === null && ($task['status'] ?? '') === $status) {
            set_engineer_flash('error', 'Add a note or change the task status first.');
            redirect_engineer_dashboard();
        }

        $conn->begin_transaction();

        try {
            if (($task['status'] ?? '') !== $status) {
                $updateTask = $conn->prepare('UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?');

                if (
                    !$updateTask ||
                    !$updateTask->bind_param('sii', $status, $taskId, $userId) ||
                    !$updateTask->execute()
                ) {
                    throw new RuntimeException('Task update failed.');
                }
            }

            if ($progressNote !== null) {
                $insertUpdate = $conn->prepare(
                    'INSERT INTO engineer_task_updates (task_id, engineer_id, status_snapshot, progress_note)
                     VALUES (?, ?, ?, ?)'
                );

                if (
                    !$insertUpdate ||
                    !$insertUpdate->bind_param('iiss', $taskId, $userId, $status, $progressNote) ||
                    !$insertUpdate->execute()
                ) {
                    throw new RuntimeException('Progress note save failed.');
                }
            }

            audit_log_event(
                $conn,
                $userId,
                'engineer_task_update',
                'task',
                $taskId,
                [
                    'status' => $task['status'] ?? null,
                ],
                [
                    'status' => $status,
                    'progress_note' => $progressNote,
                ]
            );

            $conn->commit();
            set_engineer_flash('success', 'Task update saved.');
        } catch (Throwable $exception) {
            $conn->rollback();
            set_engineer_flash('error', 'Failed to save task update.');
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
$recentUpdates = [];
$taskCounts = array_fill_keys($taskStatusOptions, 0);
$openTaskCount = 0;

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
    "SELECT p.id, p.project_name, p.status, p.description, p.start_date, p.end_date, u.full_name AS client_name,
            creator.full_name AS project_owner_name,
            COUNT(t.id) AS total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks,
            SUM(CASE WHEN t.status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_tasks,
            SUM(CASE WHEN t.status = 'delayed' THEN 1 ELSE 0 END) AS delayed_tasks,
            MIN(CASE WHEN t.status <> 'completed' AND t.deadline IS NOT NULL THEN t.deadline END) AS next_deadline
     FROM projects p
     LEFT JOIN users u ON p.client_id = u.id
     LEFT JOIN users creator ON creator.id = p.created_by
     LEFT JOIN tasks t ON t.project_id = p.id
     WHERE EXISTS (
         SELECT 1
         FROM project_assignments pe
         WHERE pe.project_id = p.id
         AND pe.engineer_id = ?
     )
     AND p.status <> 'draft'
     GROUP BY p.id, p.project_name, p.status, p.description, p.start_date, p.end_date, u.full_name, creator.full_name
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
    "SELECT t.id, t.task_name, t.description, t.status, t.deadline, p.project_name, p.id AS project_id, p.status AS project_status,
            task_creator.full_name AS task_owner_name,
            project_creator.full_name AS project_owner_name,
            client.full_name AS client_name,
            (
                SELECT etu.progress_note
                FROM engineer_task_updates etu
                WHERE etu.task_id = t.id
                ORDER BY etu.created_at DESC, etu.id DESC
                LIMIT 1
            ) AS latest_progress_note,
            (
                SELECT etu.created_at
                FROM engineer_task_updates etu
                WHERE etu.task_id = t.id
                ORDER BY etu.created_at DESC, etu.id DESC
                LIMIT 1
            ) AS latest_progress_at
     FROM tasks t
     INNER JOIN projects p ON t.project_id = p.id
     LEFT JOIN users task_creator ON task_creator.id = t.created_by
     LEFT JOIN users project_creator ON project_creator.id = p.created_by
     LEFT JOIN users client ON client.id = p.client_id
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

$recentUpdatesStmt = $conn->prepare(
    "SELECT etu.progress_note, etu.status_snapshot, etu.created_at, t.task_name, p.project_name
     FROM engineer_task_updates etu
     INNER JOIN tasks t ON t.id = etu.task_id
     INNER JOIN projects p ON p.id = t.project_id
     WHERE etu.engineer_id = ?
     ORDER BY etu.created_at DESC, etu.id DESC
     LIMIT 6"
);
if ($recentUpdatesStmt) {
    $recentUpdatesStmt->bind_param('i', $userId);
    $recentUpdatesStmt->execute();
    $recentUpdatesResult = $recentUpdatesStmt->get_result();
    if ($recentUpdatesResult) {
        $recentUpdates = $recentUpdatesResult->fetch_all(MYSQLI_ASSOC);
    }
    $recentUpdatesStmt->close();
}

foreach ($tasks as $task) {
    $statusKey = (string)($task['status'] ?? '');

    if (isset($taskCounts[$statusKey])) {
        $taskCounts[$statusKey]++;
    }
}

$openTaskCount = $taskCounts['pending'] + $taskCounts['ongoing'] + $taskCounts['delayed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineer Dashboard - Edge Automation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/engineer-sidebar.css">
    <link rel="stylesheet" href="../css/engineer.css">
</head>
<body>

<button class="sidebar-mobile-toggle" type="button" aria-label="Toggle menu" data-sidebar-mobile-toggle>
    <span></span>
    <span></span>
    <span></span>
</button>

<div class="sidebar-overlay" data-sidebar-overlay></div>

<?php include '../sidebar/sidebar_engineer.php'; ?>

<div class="main-content">
    <section class="engineer-hero">
        <div>
            <p class="engineer-eyebrow">Engineer Workspace</p>
            <h1>Welcome, <?php echo htmlspecialchars((string)($_SESSION['name'] ?? 'Engineer')); ?></h1>
            <p class="engineer-intro">Check your assigned projects, update task status, and keep project progress moving.</p>
        </div>
        <div class="engineer-hero-panel">
            <span class="hero-chip">Assigned: <?php echo (int)$assignedCount; ?></span>
            <span class="hero-chip">Open Tasks: <?php echo (int)$openTaskCount; ?></span>
            <span class="hero-chip">Completed Projects: <?php echo (int)$completedCount; ?></span>
        </div>
    </section>

    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars((string)($flash['type'] ?? 'success')); ?>">
            <?php echo htmlspecialchars((string)($flash['message'] ?? '')); ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <h4>Assigned Projects</h4>
            <p><?php echo (int)$assignedCount; ?></p>
        </div>
        <div class="stat-card">
            <h4>Active Projects</h4>
            <p><?php echo (int)$inProgressCount; ?></p>
        </div>
        <div class="stat-card">
            <h4>Completed Projects</h4>
            <p><?php echo (int)$completedCount; ?></p>
        </div>
        <div class="stat-card">
            <h4>Open Tasks</h4>
            <p><?php echo (int)$openTaskCount; ?></p>
        </div>
    </div>

    <div class="tabs">
        <button class="tab active" type="button" data-tab-target="projects-tab">My Projects</button>
        <button class="tab" type="button" data-tab-target="tasks-tab">Tasks</button>
        <button class="tab" type="button" data-tab-target="profile-tab">Profile</button>
    </div>

    <div id="projects-tab" class="tab-content active">
        <h2>Assigned Projects</h2>
        <div class="projects-grid">
            <?php if (!empty($assignedProjects)): ?>
                <?php foreach ($assignedProjects as $project): ?>
                    <div class="project-card">
                        <?php
                        $projectTotalTasks = (int)($project['total_tasks'] ?? 0);
                        $projectCompletedTasks = (int)($project['completed_tasks'] ?? 0);
                        $projectProgressPercent = $projectTotalTasks > 0 ? (int)round(($projectCompletedTasks / $projectTotalTasks) * 100) : 0;
                        $projectDeadlineMeta = build_deadline_meta($project['next_deadline'] ?? null, (string)($project['status'] ?? 'pending'));
                        ?>
                        <div class="project-card__topline">
                            <div class="project-name"><?php echo htmlspecialchars((string)$project['project_name']); ?></div>
                            <span class="status <?php echo htmlspecialchars((string)$project['status']); ?>"><?php echo htmlspecialchars(ucfirst((string)$project['status'])); ?></span>
                        </div>
                        <p><strong>Client:</strong> <?php echo htmlspecialchars((string)($project['client_name'] ?? 'N/A')); ?></p>
                        <p><strong>Project Owner:</strong> <?php echo htmlspecialchars((string)($project['project_owner_name'] ?? 'N/A')); ?></p>
                        <p><strong>Start:</strong> <?php echo htmlspecialchars((string)($project['start_date'] ?? 'N/A')); ?></p>
                        <p><strong>End:</strong> <?php echo htmlspecialchars((string)($project['end_date'] ?? 'N/A')); ?></p>
                        <p class="project-description">
                            <?php echo htmlspecialchars(substr((string)($project['description'] ?? ''), 0, 100)); ?>
                        </p>
                        <div class="project-progress">
                            <div class="project-progress__meta">
                                <strong><?php echo $projectProgressPercent; ?>%</strong>
                                <span><?php echo $projectCompletedTasks; ?> of <?php echo $projectTotalTasks; ?> tasks done</span>
                            </div>
                            <div class="project-progress__bar">
                                <span style="width: <?php echo $projectProgressPercent; ?>%;"></span>
                            </div>
                        </div>
                        <div class="project-mini-stats">
                            <span>Ongoing: <?php echo (int)($project['ongoing_tasks'] ?? 0); ?></span>
                            <span>Delayed: <?php echo (int)($project['delayed_tasks'] ?? 0); ?></span>
                            <span class="deadline-flag <?php echo htmlspecialchars($projectDeadlineMeta['class']); ?>">
                                <?php echo htmlspecialchars($projectDeadlineMeta['label']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data no-data-full"><p>No assigned projects yet.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div id="tasks-tab" class="tab-content">
        <h2>My Tasks</h2>
        <div class="task-summary">
            <span class="task-pill pending">Pending: <?php echo (int)$taskCounts['pending']; ?></span>
            <span class="task-pill ongoing">Ongoing: <?php echo (int)$taskCounts['ongoing']; ?></span>
            <span class="task-pill delayed">Delayed: <?php echo (int)$taskCounts['delayed']; ?></span>
            <span class="task-pill completed">Completed: <?php echo (int)$taskCounts['completed']; ?></span>
        </div>
        <div class="task-toolbar">
            <input
                type="search"
                id="task-search"
                class="task-toolbar__input"
                placeholder="Search task or project"
                data-task-search
            >
            <select id="task-status-filter" class="task-toolbar__select" data-task-status-filter>
                <option value="">All Status</option>
                <?php foreach ($taskStatusOptions as $statusOption): ?>
                    <option value="<?php echo htmlspecialchars($statusOption); ?>"><?php echo htmlspecialchars(ucfirst($statusOption)); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="task-deadline-filter" class="task-toolbar__select" data-task-deadline-filter>
                <option value="">All Deadlines</option>
                <option value="overdue">Overdue</option>
                <option value="due-soon">Due Soon</option>
                <option value="no-deadline">No Deadline</option>
            </select>
        </div>
        <p class="task-toolbar__hint">
            Engineers review and update assigned tasks here. Task assignment stays with the project owner or super admin.
        </p>
        <?php if (!empty($recentUpdates)): ?>
            <div class="updates-panel">
                <h3>Recent Progress Reports</h3>
                <div class="updates-list">
                    <?php foreach ($recentUpdates as $update): ?>
                        <article class="update-card">
                            <div class="update-card__topline">
                                <strong><?php echo htmlspecialchars((string)$update['task_name']); ?></strong>
                                <span><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string)$update['created_at']))); ?></span>
                            </div>
                            <p><?php echo nl2br(htmlspecialchars((string)$update['progress_note'])); ?></p>
                            <small>
                                <?php echo htmlspecialchars((string)$update['project_name']); ?>
                                <?php if (!empty($update['status_snapshot'])): ?>
                                    · Status: <?php echo htmlspecialchars(ucfirst((string)$update['status_snapshot'])); ?>
                                <?php endif; ?>
                            </small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="tasks-list">
            <?php if (!empty($tasks)): ?>
                <?php foreach ($tasks as $task): ?>
                    <?php $deadlineMeta = build_deadline_meta($task['deadline'] ?? null, (string)$task['status']); ?>
                    <div
                        class="task-item <?php echo htmlspecialchars((string)$task['status']); ?>"
                        data-task-item
                        data-task-name="<?php echo htmlspecialchars(mb_strtolower((string)$task['task_name'])); ?>"
                        data-project-name="<?php echo htmlspecialchars(mb_strtolower((string)$task['project_name'])); ?>"
                        data-task-status="<?php echo htmlspecialchars((string)$task['status']); ?>"
                        data-deadline-group="<?php echo htmlspecialchars(
                            ($task['deadline'] ?? null) === null || ($task['deadline'] ?? '') === ''
                                ? 'no-deadline'
                                : ($deadlineMeta['class'] === 'is-danger' ? 'overdue' : ($deadlineMeta['class'] === 'is-warning' ? 'due-soon' : 'scheduled'))
                        ); ?>"
                    >
                        <div>
                            <div class="task-name"><?php echo htmlspecialchars((string)$task['task_name']); ?></div>
                            <div class="task-project"><?php echo htmlspecialchars((string)$task['project_name']); ?></div>
                            <div class="task-owners">
                                <span><strong>Assigned by:</strong> <?php echo htmlspecialchars((string)($task['task_owner_name'] ?? 'N/A')); ?></span>
                                <span><strong>Project Owner:</strong> <?php echo htmlspecialchars((string)($task['project_owner_name'] ?? 'N/A')); ?></span>
                                <span><strong>Client:</strong> <?php echo htmlspecialchars((string)($task['client_name'] ?? 'N/A')); ?></span>
                            </div>
                            <?php if (!empty($task['description'])): ?>
                                <div class="task-description"><?php echo htmlspecialchars((string)$task['description']); ?></div>
                            <?php endif; ?>
                            <div class="task-deadline">Deadline: <?php echo htmlspecialchars((string)($task['deadline'] ?? 'No deadline')); ?></div>
                            <div class="deadline-flag <?php echo htmlspecialchars($deadlineMeta['class']); ?>">
                                <?php echo htmlspecialchars($deadlineMeta['label']); ?>
                            </div>
                            <?php if (!empty($task['latest_progress_note'])): ?>
                                <div class="task-latest-update">
                                    <strong>Latest report:</strong>
                                    <p><?php echo nl2br(htmlspecialchars((string)$task['latest_progress_note'])); ?></p>
                                    <?php if (!empty($task['latest_progress_at'])): ?>
                                        <span><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string)$task['latest_progress_at']))); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
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
                                    <input type="hidden" name="action" value="save_task_update">
                                    <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                                    <select name="status" aria-label="Task status" required>
                                        <?php foreach ($taskStatusOptions as $statusOption): ?>
                                            <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo ($task['status'] ?? '') === $statusOption ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(ucfirst($statusOption)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <textarea
                                        name="progress_note"
                                        rows="3"
                                        placeholder="Add your progress report, blockers, or coordination notes"
                                    ></textarea>

                                    <button type="submit" class="btn">Save Update</button>
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
            <a class="btn btn-link" href="/codesamplecaps/LOGIN/php/forgot.php">Reset Password</a>
        </div>
    </div>
</div>

<script src="../js/engineer.js"></script>

</body>
</html>
