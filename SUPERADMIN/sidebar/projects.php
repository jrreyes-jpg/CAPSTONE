<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_role('super_admin');

function get_column_type(mysqli $conn, string $tableName, string $columnName): ?string {
    static $cache = [];
    $cacheKey = $tableName . '.' . $columnName;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = $conn->prepare(
        'SELECT COLUMN_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND COLUMN_NAME = ?
         LIMIT 1'
    );

    if (!$stmt) {
        $cache[$cacheKey] = null;
        return null;
    }

    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    $cache[$cacheKey] = $row['COLUMN_TYPE'] ?? null;

    return $cache[$cacheKey];
}

function table_has_column(mysqli $conn, string $tableName, string $columnName): bool {
    return get_column_type($conn, $tableName, $columnName) !== null;
}

function enum_supports_value(mysqli $conn, string $tableName, string $columnName, string $value): bool {
    $columnType = get_column_type($conn, $tableName, $columnName);

    return $columnType !== null && str_contains($columnType, "'" . $value . "'");
}

function normalize_text_or_null(?string $value): ?string {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function today_date(): string {
    return (new DateTimeImmutable('today'))->format('Y-m-d');
}

$supportsDraftStatus = enum_supports_value($conn, 'projects', 'status', 'draft');
$hasProjectAddressColumn = table_has_column($conn, 'projects', 'project_address');
$statusOptions = $supportsDraftStatus
    ? ['draft', 'pending', 'ongoing', 'completed', 'on-hold']
    : ['pending', 'ongoing', 'completed', 'on-hold'];
$initialStatusOptions = $supportsDraftStatus
    ? ['draft', 'pending', 'ongoing']
    : ['pending', 'ongoing'];
$todayDate = today_date();

function redirect_projects_page(): void {
    header('Location: /codesamplecaps/SUPERADMIN/sidebar/projects.php');
    exit();
}

function set_projects_flash(string $type, string $message): void {
    $_SESSION['projects_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function normalize_text(?string $value): string {
    return trim((string)$value);
}

function normalize_date_or_null(?string $value): ?string {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function getProjectSnapshot(mysqli $conn, int $projectId): ?array {
    $stmt = $conn->prepare('SELECT id, project_name, status FROM projects WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result ? $result->fetch_assoc() : null;
}

function countOpenProjectTasks(mysqli $conn, int $projectId): int {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM tasks
         WHERE project_id = ?
         AND status IN ('pending', 'ongoing', 'delayed')"
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return (int)($row['total'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_project') {
        $projectName = normalize_text($_POST['project_name'] ?? '');
        $description = normalize_text($_POST['description'] ?? '');
        $projectAddress = $hasProjectAddressColumn ? normalize_text_or_null($_POST['project_address'] ?? null) : null;
        $clientId = (int)($_POST['client_id'] ?? 0);
        $engineerId = (int)($_POST['engineer_id'] ?? 0);
        $status = normalize_text($_POST['status'] ?? 'pending');
        $startDate = normalize_date_or_null($_POST['start_date'] ?? null);
        $endDate = normalize_date_or_null($_POST['end_date'] ?? null);
        $createdBy = (int)($_SESSION['user_id'] ?? 0);

        if ($projectName === '' || $clientId <= 0 || $engineerId <= 0) {
            set_projects_flash('error', 'Project name, client, and engineer are required.');
            redirect_projects_page();
        }

        if (!in_array($status, $initialStatusOptions, true)) {
            set_projects_flash(
                'error',
                $supportsDraftStatus
                    ? 'Initial project status must be Draft, Pending, or Ongoing only.'
                    : 'Initial project status must be Pending or Ongoing only.'
            );
            redirect_projects_page();
        }

        if ($hasProjectAddressColumn && $status !== 'draft' && $projectAddress === null) {
            set_projects_flash('error', 'Project address is required unless the project stays in Draft.');
            redirect_projects_page();
        }

        if ($status === 'ongoing' && $startDate === null) {
            set_projects_flash('error', 'Start date is required when creating an ongoing project.');
            redirect_projects_page();
        }

        if ($startDate !== null && $startDate < $todayDate) {
            set_projects_flash('error', 'Start date cannot be earlier than today.');
            redirect_projects_page();
        }

        if ($status === 'ongoing' && $startDate !== $todayDate) {
            set_projects_flash('error', 'An ongoing project must start today.');
            redirect_projects_page();
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            set_projects_flash('error', 'End date cannot be earlier than start date.');
            redirect_projects_page();
        }

        if ($startDate === null && $endDate !== null && $endDate < $todayDate) {
            set_projects_flash('error', 'End date cannot be earlier than today.');
            redirect_projects_page();
        }

        $conn->begin_transaction();

        try {
            if ($hasProjectAddressColumn) {
                $createProject = $conn->prepare(
                    'INSERT INTO projects (project_name, description, client_id, project_address, start_date, end_date, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
            } else {
                $createProject = $conn->prepare(
                    'INSERT INTO projects (project_name, description, client_id, start_date, end_date, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
            }

            if (!$createProject) {
                throw new RuntimeException('Failed to prepare project creation.');
            }

            if ($hasProjectAddressColumn) {
                $createProject->bind_param(
                    'ssissssi',
                    $projectName,
                    $description,
                    $clientId,
                    $projectAddress,
                    $startDate,
                    $endDate,
                    $status,
                    $createdBy
                );
            } else {
                $createProject->bind_param(
                    'ssisssi',
                    $projectName,
                    $description,
                    $clientId,
                    $startDate,
                    $endDate,
                    $status,
                    $createdBy
                );
            }

            if (!$createProject->execute()) {
                throw new RuntimeException('Failed to create project.');
            }

            $projectId = (int)$createProject->insert_id;

            $assignEngineer = $conn->prepare(
                'INSERT INTO project_assignments (project_id, engineer_id, assigned_by)
                 VALUES (?, ?, ?)'
            );

            if (!$assignEngineer) {
                throw new RuntimeException('Failed to prepare engineer assignment.');
            }

            $assignEngineer->bind_param('iii', $projectId, $engineerId, $createdBy);

            if (!$assignEngineer->execute()) {
                throw new RuntimeException('Failed to assign engineer to project.');
            }

            $conn->commit();
            set_projects_flash('success', 'Project created successfully.');
        } catch (Throwable $exception) {
            $conn->rollback();
            set_projects_flash('error', $exception->getMessage());
        }

        redirect_projects_page();
    }

    if ($action === 'update_project_status') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $status = normalize_text($_POST['status'] ?? '');
        $project = $projectId > 0 ? getProjectSnapshot($conn, $projectId) : null;

        if ($projectId <= 0 || !in_array($status, $statusOptions, true)) {
            set_projects_flash('error', 'Invalid project status update.');
            redirect_projects_page();
        }

        if (!$project) {
            set_projects_flash('error', 'Project not found.');
            redirect_projects_page();
        }

        if (($project['status'] ?? '') === 'completed') {
            set_projects_flash('error', 'Completed projects are locked. Use Reopen first.');
            redirect_projects_page();
        }

        if (!in_array(($project['status'] ?? ''), ['pending', 'draft'], true) && $status === 'pending') {
            set_projects_flash('error', 'A started project cannot go back to Pending. Use On-hold instead.');
            redirect_projects_page();
        }

        if ($status === 'completed') {
            $openTasks = countOpenProjectTasks($conn, $projectId);

            if ($openTasks > 0) {
                set_projects_flash('error', 'Complete all open tasks before marking this project as completed.');
                redirect_projects_page();
            }
        }

        $updateStatus = $conn->prepare('UPDATE projects SET status = ? WHERE id = ?');

        if ($updateStatus && $updateStatus->bind_param('si', $status, $projectId) && $updateStatus->execute()) {
            set_projects_flash('success', 'Project status updated.');
        } else {
            set_projects_flash('error', 'Failed to update project status.');
        }

        redirect_projects_page();
    }

    if ($action === 'add_task') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $taskName = normalize_text($_POST['task_name'] ?? '');
        $description = normalize_text($_POST['task_description'] ?? '');
        $deadline = normalize_date_or_null($_POST['deadline'] ?? null);
        $createdBy = (int)($_SESSION['user_id'] ?? 0);
        $project = $projectId > 0 ? getProjectSnapshot($conn, $projectId) : null;

        if ($projectId <= 0 || $assignedTo <= 0 || $taskName === '') {
            set_projects_flash('error', 'Task name and assigned engineer are required.');
            redirect_projects_page();
        }

        if (!$project) {
            set_projects_flash('error', 'Project not found.');
            redirect_projects_page();
        }

        if (($project['status'] ?? '') === 'completed') {
            set_projects_flash('error', 'Cannot add tasks to a completed project. Reopen it first.');
            redirect_projects_page();
        }

        if (($project['status'] ?? '') === 'draft') {
            set_projects_flash('error', 'Cannot add tasks to a draft project. Change its status to Pending or Ongoing first.');
            redirect_projects_page();
        }

        $insertTask = $conn->prepare(
            'INSERT INTO tasks (project_id, assigned_to, task_name, description, deadline, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (
            $insertTask &&
            $insertTask->bind_param('iisssi', $projectId, $assignedTo, $taskName, $description, $deadline, $createdBy) &&
            $insertTask->execute()
        ) {
            set_projects_flash('success', 'Task added successfully.');
        } else {
            set_projects_flash('error', 'Failed to add task.');
        }

        redirect_projects_page();
    }

    if ($action === 'reopen_project') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $project = $projectId > 0 ? getProjectSnapshot($conn, $projectId) : null;

        if ($projectId <= 0 || !$project) {
            set_projects_flash('error', 'Project not found.');
            redirect_projects_page();
        }

        if (($project['status'] ?? '') !== 'completed') {
            set_projects_flash('error', 'Only completed projects can be reopened.');
            redirect_projects_page();
        }

        $reopenProject = $conn->prepare("UPDATE projects SET status = 'ongoing' WHERE id = ?");

        if ($reopenProject && $reopenProject->bind_param('i', $projectId) && $reopenProject->execute()) {
            set_projects_flash('success', 'Project reopened successfully.');
        } else {
            set_projects_flash('error', 'Failed to reopen project.');
        }

        redirect_projects_page();
    }
}

$flash = $_SESSION['projects_flash'] ?? null;
unset($_SESSION['projects_flash']);

$clients = [];
$engineers = [];

$clientResult = $conn->query("SELECT id, full_name FROM users WHERE role = 'client' AND status = 'active' ORDER BY full_name ASC");
if ($clientResult) {
    $clients = $clientResult->fetch_all(MYSQLI_ASSOC);
}

$engineerResult = $conn->query("SELECT id, full_name FROM users WHERE role = 'engineer' AND status = 'active' ORDER BY full_name ASC");
if ($engineerResult) {
    $engineers = $engineerResult->fetch_all(MYSQLI_ASSOC);
}

$projects = [];
$tasksByProject = [];
$projectAddressSelect = $hasProjectAddressColumn ? 'p.project_address,' : 'NULL AS project_address,';

$projectsQuery = "
    SELECT
        p.id,
        p.project_name,
        p.description,
        {$projectAddressSelect}
        p.client_id,
        p.start_date,
        p.end_date,
        p.status,
        p.created_at,
        client.full_name AS client_name,
        latest_assignment.engineer_id,
        engineer.full_name AS engineer_name,
        COALESCE(task_totals.total_tasks, 0) AS total_tasks,
        COALESCE(task_totals.completed_tasks, 0) AS completed_tasks
    FROM projects p
    LEFT JOIN users client ON client.id = p.client_id
    LEFT JOIN (
        SELECT pa.project_id, pa.engineer_id
        FROM project_assignments pa
        INNER JOIN (
            SELECT project_id, MAX(id) AS latest_id
            FROM project_assignments
            GROUP BY project_id
        ) latest ON latest.latest_id = pa.id
    ) latest_assignment ON latest_assignment.project_id = p.id
    LEFT JOIN users engineer ON engineer.id = latest_assignment.engineer_id
    LEFT JOIN (
        SELECT
            project_id,
            COUNT(*) AS total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks
        FROM tasks
        GROUP BY project_id
    ) task_totals ON task_totals.project_id = p.id
    ORDER BY p.created_at DESC, p.id DESC
";

$projectsResult = $conn->query($projectsQuery);
if ($projectsResult) {
    $projects = $projectsResult->fetch_all(MYSQLI_ASSOC);
}

$tasksResult = $conn->query("
    SELECT
        t.id,
        t.project_id,
        t.task_name,
        t.status,
        t.deadline,
        assignee.full_name AS assignee_name
    FROM tasks t
    LEFT JOIN users assignee ON assignee.id = t.assigned_to
    ORDER BY t.project_id DESC, t.deadline IS NULL, t.deadline ASC, t.id DESC
");

if ($tasksResult) {
    while ($task = $tasksResult->fetch_assoc()) {
        $tasksByProject[(int)$task['project_id']][] = $task;
    }
}

$totalProjects = count($projects);
$ongoingProjects = 0;
$completedProjects = 0;
$totalTasks = 0;

foreach ($projects as $project) {
    if (($project['status'] ?? '') === 'ongoing') {
        $ongoingProjects++;
    }
    if (($project['status'] ?? '') === 'completed') {
        $completedProjects++;
    }
    $totalTasks += (int)($project['total_tasks'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management - Super Admin</title>
    <link rel="stylesheet" href="../css/admin-dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../sidebar_super_admin.php'; ?>

    <main class="main-content">
        <div class="page-stack">
            <section class="metrics-grid">
                <div class="metric-card">
                    <span>Total Projects</span>
                    <strong><?php echo $totalProjects; ?></strong>
                </div>
                <div class="metric-card">
                    <span>Ongoing</span>
                    <strong><?php echo $ongoingProjects; ?></strong>
                </div>
                <div class="metric-card">
                    <span>Completed</span>
                    <strong><?php echo $completedProjects; ?></strong>
                </div>
                <div class="metric-card">
                    <span>Total Tasks</span>
                    <strong><?php echo $totalTasks; ?></strong>
                </div>
            </section>

            <?php if ($flash): ?>
                <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <section class="form-panel">
                <h2 class="section-title-inline">Create Project</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_project">

                    <div class="form-grid">
                        <div class="input-group">
                            <label for="project_name">Project Name</label>
                            <input type="text" id="project_name" name="project_name" required>
                        </div>

                        <div class="input-group">
                            <label for="client_id">Client</label>
                            <select id="client_id" name="client_id" required>
                                <option value="">Select client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo (int)$client['id']; ?>"><?php echo htmlspecialchars($client['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="input-group">
                            <label for="engineer_id">Engineer</label>
                            <select id="engineer_id" name="engineer_id" required>
                                <option value="">Select engineer</option>
                                <?php foreach ($engineers as $engineer): ?>
                                    <option value="<?php echo (int)$engineer['id']; ?>"><?php echo htmlspecialchars($engineer['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="input-group">
                            <div class="field-label-row">
                                <label for="status">Initial Status</label>
                                <button type="button" class="field-tip" aria-label="Project status reminder">
                                    <span class="field-tip__icon" aria-hidden="true">i</span>
                                    <span class="field-tip__bubble">
                                        <?php if ($supportsDraftStatus): ?>
                                            Use Draft for incomplete or possibly wrong project entries. Use Pending for approved work, and choose Ongoing only when work starts today.
                                        <?php else: ?>
                                            Use Pending for approved work. Choose Ongoing only when work starts today.
                                        <?php endif; ?>
                                    </span>
                                </button>
                            </div>
                            <select id="status" name="status" required>
                                <?php foreach ($initialStatusOptions as $statusOption): ?>
                                    <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $statusOption === 'pending' ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($statusOption)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="initial-status-help" class="form-help-text">
                                <?php if ($supportsDraftStatus): ?>
                                    Draft is safe for incomplete or mistaken entries. Pending stays as the default.
                                <?php else: ?>
                                    Use Pending for planned projects. Choose Ongoing only if work starts now.
                                <?php endif; ?>
                            </small>
                        </div>

                        <?php if ($hasProjectAddressColumn): ?>
                            <div class="input-group input-group-wide">
                                <div class="field-label-row">
                                    <label for="project_address">Project Address / Site Location</label>
                                    <button type="button" class="field-tip" aria-label="Project address reminder">
                                        <span class="field-tip__icon" aria-hidden="true">i</span>
                                        <span class="field-tip__bubble">Save the actual site or client location here. This is recommended for planning and required once the project moves out of Draft.</span>
                                    </button>
                                </div>
                                <textarea id="project_address" name="project_address" rows="2" placeholder="Street, barangay, city, landmark, or site location"></textarea>
                            </div>
                        <?php endif; ?>

                        <div class="input-group">
                            <div class="field-label-row">
                                <label for="start_date">Start Date</label>
                                <button type="button" class="field-tip" aria-label="Start date reminder">
                                    <span class="field-tip__icon" aria-hidden="true">i</span>
                                    <span class="field-tip__bubble">Start Date cannot be in the past. If Initial Status is Ongoing, Start Date must be today.</span>
                                </button>
                            </div>
                            <input type="date" id="start_date" name="start_date" min="<?php echo htmlspecialchars($todayDate); ?>">
                            <small id="start-date-help" class="form-help-text">Optional while pending. Required if the project starts as ongoing.</small>
                        </div>

                        <div class="input-group">
                            <div class="field-label-row">
                                <label for="end_date">End Date</label>
                                <button type="button" class="field-tip" aria-label="End date reminder">
                                    <span class="field-tip__icon" aria-hidden="true">i</span>
                                    <span class="field-tip__bubble">End Date can be the same day as Start Date or any later day, but it can never be earlier than Start Date.</span>
                                </button>
                            </div>
                            <input type="date" id="end_date" name="end_date" min="<?php echo htmlspecialchars($todayDate); ?>">
                            <small class="form-help-text">Same-day end dates are allowed. Earlier-than-start dates are invalid.</small>
                        </div>
                    </div>

                    <div class="input-group input-group-spaced">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Project description"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" <?php echo (count($clients) === 0 || count($engineers) === 0) ? 'disabled' : ''; ?>>Create Project</button>
                    </div>
                </form>
            </section>

            <section class="page-stack">
                <h2 class="section-title-inline">Projects</h2>

                <?php if (empty($projects)): ?>
                    <div class="empty-state">No projects yet. Create your first project above.</div>
                <?php else: ?>
                    <div class="projects-grid">
                        <?php foreach ($projects as $project): ?>
                            <?php
                            $projectId = (int)$project['id'];
                            $projectTasks = $tasksByProject[$projectId] ?? [];
                            $currentEngineerId = (int)($project['engineer_id'] ?? 0);
                            $isDraft = ($project['status'] ?? '') === 'draft';
                            $isCompleted = ($project['status'] ?? '') === 'completed';
                            ?>
                            <article class="project-card<?php echo $isCompleted ? ' is-locked' : ''; ?><?php echo $isDraft ? ' is-draft' : ''; ?>">
                                <div class="card-split">
                                    <div>
                                        <h3><?php echo htmlspecialchars($project['project_name']); ?></h3>
                                        <div class="status-pill-wrap">
                                            <span class="status-pill status-<?php echo htmlspecialchars($project['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($project['status'])); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="project-meta">
                                        <div><strong>Client:</strong> <?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?></div>
                                        <div><strong>Engineer:</strong> <?php echo htmlspecialchars($project['engineer_name'] ?? 'Not assigned'); ?></div>
                                        <?php if ($hasProjectAddressColumn): ?>
                                            <div><strong>Project Site:</strong> <?php echo htmlspecialchars($project['project_address'] ?? 'Not set'); ?></div>
                                        <?php endif; ?>
                                        <div><strong>Start:</strong> <?php echo htmlspecialchars($project['start_date'] ?? 'N/A'); ?></div>
                                        <div><strong>End:</strong> <?php echo htmlspecialchars($project['end_date'] ?? 'N/A'); ?></div>
                                        <div><strong>Tasks:</strong> <?php echo (int)$project['completed_tasks']; ?> / <?php echo (int)$project['total_tasks']; ?> completed</div>
                                    </div>
                                </div>

                                <?php if (!empty($project['description'])): ?>
                                    <div class="empty-state empty-state-solid">
                                        <?php echo nl2br(htmlspecialchars($project['description'])); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($isDraft): ?>
                                    <div class="draft-note">This project is still in draft. Finalize the address and dates, then change the status to Pending or Ongoing when ready.</div>
                                <?php endif; ?>

                                <?php if ($isCompleted): ?>
                                    <div class="lock-note">This project is locked because it is already completed.</div>
                                    <form method="POST" class="mini-form">
                                        <input type="hidden" name="action" value="reopen_project">
                                        <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                        <div class="form-actions">
                                            <button type="submit" class="btn-secondary">Reopen Project</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" class="mini-form">
                                        <input type="hidden" name="action" value="update_project_status">
                                        <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">

                                        <h4 class="subheading">Update Status</h4>
                                        <div class="form-grid">
                                            <div class="input-group">
                                                <label for="status-<?php echo $projectId; ?>">Status</label>
                                                <select id="status-<?php echo $projectId; ?>" name="status" required>
                                                    <?php foreach ($statusOptions as $statusOption): ?>
                                                        <?php if ($statusOption === 'pending' && !in_array(($project['status'] ?? ''), ['pending', 'draft'], true)) { continue; } ?>
                                                        <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $project['status'] === $statusOption ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars(ucfirst($statusOption)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" class="btn-primary">Save Status</button>
                                        </div>
                                    </form>
                                <?php endif; ?>

                                <div class="card-split">
                                    <h4 class="subheading">Tasks</h4>

                                    <?php if (empty($projectTasks)): ?>
                                        <div class="empty-state">No tasks yet for this project.</div>
                                    <?php else: ?>
                                        <div class="task-list">
                                            <?php foreach ($projectTasks as $task): ?>
                                                <div class="task-item">
                                                    <strong><?php echo htmlspecialchars($task['task_name']); ?></strong>
                                                    <span>Status: <?php echo htmlspecialchars(ucfirst($task['status'])); ?></span>
                                                    <span>Assigned To: <?php echo htmlspecialchars($task['assignee_name'] ?? 'N/A'); ?></span>
                                                    <span>Deadline: <?php echo htmlspecialchars($task['deadline'] ?? 'No deadline'); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isCompleted): ?>
                                    <div class="empty-state">Task creation is disabled while this project is completed.</div>
                                <?php elseif ($isDraft): ?>
                                    <div class="empty-state">Task creation is disabled while this project is still in draft.</div>
                                <?php else: ?>
                                    <form method="POST" class="mini-form">
                                        <input type="hidden" name="action" value="add_task">
                                        <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">

                                        <h4 class="subheading">Add Task</h4>

                                        <div class="form-grid">
                                            <div class="input-group">
                                                <label for="task_name-<?php echo $projectId; ?>">Task Name</label>
                                                <input type="text" id="task_name-<?php echo $projectId; ?>" name="task_name" required>
                                            </div>

                                            <div class="input-group">
                                                <label for="assigned_to-<?php echo $projectId; ?>">Assign To</label>
                                                <select id="assigned_to-<?php echo $projectId; ?>" name="assigned_to" required>
                                                    <option value="">Select engineer</option>
                                                    <?php foreach ($engineers as $engineer): ?>
                                                        <option value="<?php echo (int)$engineer['id']; ?>" <?php echo $currentEngineerId === (int)$engineer['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($engineer['full_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="input-group">
                                                <label for="deadline-<?php echo $projectId; ?>">Deadline</label>
                                                <input type="date" id="deadline-<?php echo $projectId; ?>" name="deadline">
                                            </div>
                                        </div>

                                        <div class="input-group">
                                            <label for="task_description-<?php echo $projectId; ?>">Task Description</label>
                                            <textarea id="task_description-<?php echo $projectId; ?>" name="task_description" placeholder="Task details"></textarea>
                                        </div>

                                        <div class="form-actions">
                                            <button type="submit" class="btn-primary">Add Task</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
<script src="../js/admin-script.js"></script>
</body>
</html>
