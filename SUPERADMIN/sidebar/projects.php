<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_role('super_admin');

$statusOptions = ['pending', 'ongoing', 'completed', 'on-hold'];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_project') {
        $projectName = normalize_text($_POST['project_name'] ?? '');
        $description = normalize_text($_POST['description'] ?? '');
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

        if (!in_array($status, $statusOptions, true)) {
            set_projects_flash('error', 'Invalid project status selected.');
            redirect_projects_page();
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            set_projects_flash('error', 'End date cannot be earlier than start date.');
            redirect_projects_page();
        }

        $conn->begin_transaction();

        try {
            $createProject = $conn->prepare(
                'INSERT INTO projects (project_name, description, client_id, start_date, end_date, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$createProject) {
                throw new RuntimeException('Failed to prepare project creation.');
            }

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

        if ($projectId <= 0 || !in_array($status, $statusOptions, true)) {
            set_projects_flash('error', 'Invalid project status update.');
            redirect_projects_page();
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

        if ($projectId <= 0 || $assignedTo <= 0 || $taskName === '') {
            set_projects_flash('error', 'Task name and assigned engineer are required.');
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

$projectsQuery = "
    SELECT
        p.id,
        p.project_name,
        p.description,
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
    <style>
        .page-stack {
            display: grid;
            gap: 24px;
        }

        .form-panel,
        .project-card {
            background: rgba(255, 255, 255, 0.92);
            border-radius: 18px;
            box-shadow: var(--shadow-soft);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }

        .metric-card {
            background: linear-gradient(135deg, #ecfdf5, #dcfce7);
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid rgba(22, 163, 74, 0.12);
        }

        .metric-card span {
            display: block;
            font-size: 0.84rem;
            color: var(--text-light);
            margin-bottom: 8px;
        }

        .metric-card strong {
            font-size: 1.8rem;
            color: var(--text-dark);
        }

        .form-panel {
            padding: 24px;
        }

        .section-title-inline {
            margin: 0 0 16px;
            font-size: 1.2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .input-group {
            display: grid;
            gap: 8px;
        }

        .input-group label {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            font: inherit;
            background: #fff;
        }

        .input-group textarea {
            min-height: 110px;
            resize: vertical;
        }

        .form-actions {
            margin-top: 18px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-primary,
        .btn-secondary {
            border: none;
            border-radius: 12px;
            padding: 12px 18px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: var(--text-dark);
        }

        .alert {
            border-radius: 14px;
            padding: 14px 16px;
            font-weight: 500;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .project-card {
            padding: 22px;
            display: grid;
            gap: 18px;
        }

        .project-card h3 {
            margin: 0;
            font-size: 1.18rem;
        }

        .project-meta {
            display: grid;
            gap: 8px;
            color: var(--text-light);
            font-size: 0.92rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-ongoing { background: #dbeafe; color: #1d4ed8; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-on-hold { background: #fee2e2; color: #991b1b; }

        .card-split {
            display: grid;
            gap: 16px;
        }

        .task-list {
            display: grid;
            gap: 10px;
        }

        .task-item {
            padding: 12px 14px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }

        .task-item strong {
            display: block;
            margin-bottom: 4px;
        }

        .task-item span {
            display: block;
            color: var(--text-light);
            font-size: 0.88rem;
        }

        .empty-state {
            padding: 18px;
            border-radius: 14px;
            background: #f8fafc;
            color: var(--text-light);
            border: 1px dashed #cbd5e1;
        }

        .mini-form {
            display: grid;
            gap: 12px;
            padding-top: 4px;
        }

        .mini-form .form-grid {
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        }

        .subheading {
            margin: 0;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .form-panel,
            .project-card {
                padding: 18px;
            }
        }
    </style>
</head>
<body class="page-loaded">
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
                            <label for="status">Initial Status</label>
                            <select id="status" name="status" required>
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <option value="<?php echo htmlspecialchars($statusOption); ?>"><?php echo htmlspecialchars(ucfirst($statusOption)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="input-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date">
                        </div>

                        <div class="input-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date">
                        </div>
                    </div>

                    <div class="input-group" style="margin-top: 16px;">
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
                            ?>
                            <article class="project-card">
                                <div class="card-split">
                                    <div>
                                        <h3><?php echo htmlspecialchars($project['project_name']); ?></h3>
                                        <div style="margin-top: 10px;">
                                            <span class="status-pill status-<?php echo htmlspecialchars($project['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($project['status'])); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="project-meta">
                                        <div><strong>Client:</strong> <?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?></div>
                                        <div><strong>Engineer:</strong> <?php echo htmlspecialchars($project['engineer_name'] ?? 'Not assigned'); ?></div>
                                        <div><strong>Start:</strong> <?php echo htmlspecialchars($project['start_date'] ?? 'N/A'); ?></div>
                                        <div><strong>End:</strong> <?php echo htmlspecialchars($project['end_date'] ?? 'N/A'); ?></div>
                                        <div><strong>Tasks:</strong> <?php echo (int)$project['completed_tasks']; ?> / <?php echo (int)$project['total_tasks']; ?> completed</div>
                                    </div>
                                </div>

                                <?php if (!empty($project['description'])): ?>
                                    <div class="empty-state" style="border-style: solid;">
                                        <?php echo nl2br(htmlspecialchars($project['description'])); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" class="mini-form">
                                    <input type="hidden" name="action" value="update_project_status">
                                    <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">

                                    <h4 class="subheading">Update Status</h4>
                                    <div class="form-grid">
                                        <div class="input-group">
                                            <label for="status-<?php echo $projectId; ?>">Status</label>
                                            <select id="status-<?php echo $projectId; ?>" name="status" required>
                                                <?php foreach ($statusOptions as $statusOption): ?>
                                                    <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $project['status'] === $statusOption ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars(ucfirst($statusOption)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" class="btn-secondary">Save Status</button>
                                    </div>
                                </form>

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
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
<script src="../js/dashboard-sidebar.js"></script>
</body>
</html>
