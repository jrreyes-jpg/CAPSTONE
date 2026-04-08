<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_role('super_admin');

function pm_get_column_type(mysqli $conn, string $tableName, string $columnName): ?string {
    $stmt = $conn->prepare(
        'SELECT COLUMN_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND COLUMN_NAME = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return $row['COLUMN_TYPE'] ?? null;
}

function pm_table_has_column(mysqli $conn, string $tableName, string $columnName): bool {
    return pm_get_column_type($conn, $tableName, $columnName) !== null;
}

function pm_enum_supports_value(mysqli $conn, string $tableName, string $columnName, string $value): bool {
    $columnType = pm_get_column_type($conn, $tableName, $columnName);
    return $columnType !== null && str_contains($columnType, "'" . $value . "'");
}

function pm_today_date(): string {
    return (new DateTimeImmutable('today'))->format('Y-m-d');
}

function pm_ensure_project_inventory_deployments_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS project_inventory_deployments (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            project_id INT(11) NOT NULL,
            inventory_id INT(11) NOT NULL,
            quantity INT(11) NOT NULL DEFAULT 1,
            deployed_by INT(11) NOT NULL,
            deployed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            returned_at TIMESTAMP NULL DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            KEY idx_project_inventory_deployments_project (project_id),
            KEY idx_project_inventory_deployments_inventory (inventory_id),
            KEY idx_project_inventory_deployments_returned (returned_at),
            CONSTRAINT fk_project_inventory_deployments_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
            CONSTRAINT fk_project_inventory_deployments_inventory FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE CASCADE,
            CONSTRAINT fk_project_inventory_deployments_user FOREIGN KEY (deployed_by) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function pm_ensure_project_inventory_return_logs_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS project_inventory_return_logs (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            deployment_id INT(11) NOT NULL,
            quantity INT(11) NOT NULL DEFAULT 1,
            returned_by INT(11) NOT NULL,
            returned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notes TEXT DEFAULT NULL,
            KEY idx_project_inventory_return_logs_deployment (deployment_id),
            KEY idx_project_inventory_return_logs_returned_at (returned_at),
            CONSTRAINT fk_project_inventory_return_logs_deployment FOREIGN KEY (deployment_id) REFERENCES project_inventory_deployments (id) ON DELETE CASCADE,
            CONSTRAINT fk_project_inventory_return_logs_user FOREIGN KEY (returned_by) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

$supportsDraftStatus = pm_enum_supports_value($conn, 'projects', 'status', 'draft');
$supportsCancelledStatus = pm_enum_supports_value($conn, 'projects', 'status', 'cancelled');
$supportsArchivedStatus = pm_enum_supports_value($conn, 'projects', 'status', 'archived');
$hasProjectAddressColumn = pm_table_has_column($conn, 'projects', 'project_address');
$statusOptions = [];
if ($supportsDraftStatus) {
    $statusOptions[] = 'draft';
}
$statusOptions = array_merge($statusOptions, ['pending', 'ongoing', 'completed', 'on-hold']);
if ($supportsCancelledStatus) {
    $statusOptions[] = 'cancelled';
}
if ($supportsArchivedStatus) {
    $statusOptions[] = 'archived';
}
$todayDate = pm_today_date();
$projectId = max(0, (int)($_GET['id'] ?? 0));
$detailsPath = '/codesamplecaps/SUPERADMIN/sidebar/project_details.php?id=' . $projectId;

pm_ensure_project_inventory_deployments_table($conn);
pm_ensure_project_inventory_return_logs_table($conn);

$flash = $_SESSION['projects_flash'] ?? null;
unset($_SESSION['projects_flash']);

$clients = [];
$engineers = [];
$project = null;
$tasks = [];
$activeDeployments = [];
$deploymentHistory = [];
$availableInventory = [];

$clientResult = $conn->query("SELECT id, full_name FROM users WHERE role = 'client' AND status = 'active' ORDER BY full_name ASC");
if ($clientResult) {
    $clients = $clientResult->fetch_all(MYSQLI_ASSOC);
}

$engineerResult = $conn->query("SELECT id, full_name FROM users WHERE role = 'engineer' AND status = 'active' ORDER BY full_name ASC");
if ($engineerResult) {
    $engineers = $engineerResult->fetch_all(MYSQLI_ASSOC);
}

if ($projectId > 0) {
    $projectAddressSelect = $hasProjectAddressColumn ? 'p.project_address,' : 'NULL AS project_address,';

    $projectStmt = $conn->prepare("
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
        WHERE p.id = ?
        LIMIT 1
    ");

    if ($projectStmt) {
        $projectStmt->bind_param('i', $projectId);
        $projectStmt->execute();
        $projectResult = $projectStmt->get_result();
        $project = $projectResult ? $projectResult->fetch_assoc() : null;
    }

    $tasksStmt = $conn->prepare("
        SELECT
            t.id,
            t.task_name,
            t.status,
            t.deadline,
            t.description,
            assignee.full_name AS assignee_name
        FROM tasks t
        LEFT JOIN users assignee ON assignee.id = t.assigned_to
        WHERE t.project_id = ?
        ORDER BY t.deadline IS NULL, t.deadline ASC, t.id DESC
    ");

    if ($tasksStmt) {
        $tasksStmt->bind_param('i', $projectId);
        $tasksStmt->execute();
        $tasksResult = $tasksStmt->get_result();
        $tasks = $tasksResult ? $tasksResult->fetch_all(MYSQLI_ASSOC) : [];
    }

    $availableInventoryResult = $conn->query(
        "SELECT
            i.id,
            i.quantity,
            a.asset_name,
            a.asset_type,
            a.serial_number
         FROM inventory i
         INNER JOIN assets a ON a.id = i.asset_id
         WHERE i.quantity > 0
         ORDER BY a.asset_name ASC, i.id ASC"
    );

    if ($availableInventoryResult) {
        $availableInventory = $availableInventoryResult->fetch_all(MYSQLI_ASSOC);
    }

    $activeDeploymentsStmt = $conn->prepare("
        SELECT
            pid.id,
            pid.quantity,
            COALESCE(returns.returned_quantity, 0) AS returned_quantity,
            (pid.quantity - COALESCE(returns.returned_quantity, 0)) AS remaining_quantity,
            pid.deployed_at,
            pid.notes,
            a.asset_name,
            a.asset_type,
            a.serial_number,
            deployer.full_name AS deployed_by_name
        FROM project_inventory_deployments pid
        INNER JOIN inventory i ON i.id = pid.inventory_id
        INNER JOIN assets a ON a.id = i.asset_id
        LEFT JOIN users deployer ON deployer.id = pid.deployed_by
        LEFT JOIN (
            SELECT deployment_id, SUM(quantity) AS returned_quantity
            FROM project_inventory_return_logs
            GROUP BY deployment_id
        ) returns ON returns.deployment_id = pid.id
        WHERE pid.project_id = ?
        AND (pid.quantity - COALESCE(returns.returned_quantity, 0)) > 0
        ORDER BY pid.deployed_at DESC, pid.id DESC
    ");

    if ($activeDeploymentsStmt) {
        $activeDeploymentsStmt->bind_param('i', $projectId);
        $activeDeploymentsStmt->execute();
        $activeDeploymentsResult = $activeDeploymentsStmt->get_result();
        $activeDeployments = $activeDeploymentsResult ? $activeDeploymentsResult->fetch_all(MYSQLI_ASSOC) : [];
    }

    $deploymentHistoryStmt = $conn->prepare("
        SELECT
            pid.id,
            pid.quantity,
            COALESCE(returns.returned_quantity, 0) AS returned_quantity,
            (pid.quantity - COALESCE(returns.returned_quantity, 0)) AS remaining_quantity,
            pid.deployed_at,
            pid.notes,
            a.asset_name,
            a.asset_type,
            a.serial_number,
            deployer.full_name AS deployed_by_name,
            last_return.last_returned_at
        FROM project_inventory_deployments pid
        INNER JOIN inventory i ON i.id = pid.inventory_id
        INNER JOIN assets a ON a.id = i.asset_id
        LEFT JOIN users deployer ON deployer.id = pid.deployed_by
        LEFT JOIN (
            SELECT deployment_id, SUM(quantity) AS returned_quantity
            FROM project_inventory_return_logs
            GROUP BY deployment_id
        ) returns ON returns.deployment_id = pid.id
        LEFT JOIN (
            SELECT deployment_id, MAX(returned_at) AS last_returned_at
            FROM project_inventory_return_logs
            GROUP BY deployment_id
        ) last_return ON last_return.deployment_id = pid.id
        WHERE pid.project_id = ?
        ORDER BY pid.deployed_at DESC, pid.id DESC
    ");

    if ($deploymentHistoryStmt) {
        $deploymentHistoryStmt->bind_param('i', $projectId);
        $deploymentHistoryStmt->execute();
        $deploymentHistoryResult = $deploymentHistoryStmt->get_result();
        $deploymentHistory = $deploymentHistoryResult ? $deploymentHistoryResult->fetch_all(MYSQLI_ASSOC) : [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details - Super Admin</title>
    <link rel="stylesheet" href="../css/super_admin_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../sidebar_super_admin.php'; ?>

    <main class="main-content">
        <div class="page-stack">
            <div class="form-actions">
                <a href="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="btn-secondary">Back to Projects</a>
            </div>

            <?php if ($flash): ?>
                <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (!$project): ?>
                <div class="empty-state">Project not found.</div>
            <?php else: ?>
                <?php
                $isDraft = ($project['status'] ?? '') === 'draft';
                $isCompleted = ($project['status'] ?? '') === 'completed';
                $currentEngineerId = (int)($project['engineer_id'] ?? 0);
                $completionRate = (int)round(((int)($project['completed_tasks'] ?? 0) / max(1, (int)($project['total_tasks'] ?? 0))) * 100);
                ?>

                <section class="form-panel project-details-shell">
                    <div class="project-details-hero">
                        <div class="project-details-hero__main">
                            <div>
                                <h2 class="section-title-inline"><?php echo htmlspecialchars($project['project_name']); ?></h2>
                                <div class="status-pill-wrap">
                                    <span class="status-pill status-<?php echo htmlspecialchars($project['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($project['status'])); ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($project['description'])): ?>
                                <div class="empty-state empty-state-solid project-details-hero__description"><?php echo nl2br(htmlspecialchars($project['description'])); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="project-details-stats">
                            <div class="project-details-stat">
                                <span>Progress</span>
                                <strong><?php echo $completionRate; ?>%</strong>
                                <small><?php echo (int)$project['completed_tasks']; ?> / <?php echo (int)$project['total_tasks']; ?> tasks done</small>
                            </div>
                            <div class="project-details-stat">
                                <span>Client</span>
                                <strong><?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?></strong>
                                <small>Assigned client</small>
                            </div>
                            <div class="project-details-stat">
                                <span>Engineer</span>
                                <strong><?php echo htmlspecialchars($project['engineer_name'] ?? 'Not assigned'); ?></strong>
                                <small>Current lead</small>
                            </div>
                            <div class="project-details-stat">
                                <span>Target End</span>
                                <strong><?php echo htmlspecialchars($project['end_date'] ?? 'N/A'); ?></strong>
                                <small>Planned completion</small>
                            </div>
                        </div>
                    </div>

                    <div class="project-details-glance">
                        <div><strong>Start:</strong> <?php echo htmlspecialchars($project['start_date'] ?? 'N/A'); ?></div>
                        <div><strong>Created:</strong> <?php echo htmlspecialchars($project['created_at'] ?? 'N/A'); ?></div>
                        <?php if ($hasProjectAddressColumn): ?>
                            <div><strong>Project Site:</strong> <?php echo htmlspecialchars($project['project_address'] ?? 'Not set'); ?></div>
                        <?php endif; ?>
                    </div>
                </section>

                <nav class="project-details-tabs" aria-label="Project detail sections">
                    <button type="button" class="project-details-tab is-active" data-project-tab="overview">Overview</button>
                    <button type="button" class="project-details-tab" data-project-tab="status">Status</button>
                    <button type="button" class="project-details-tab" data-project-tab="tasks">Tasks</button>
                    <button type="button" class="project-details-tab" data-project-tab="inventory">Inventory</button>
                    <button type="button" class="project-details-tab" data-project-tab="history">History</button>
                </nav>

                <div class="project-details-panels">
                <section class="form-panel project-details-panel is-active" data-project-panel="overview">
                    <h2 class="section-title-inline">Edit Project Details</h2>
                    <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php">
                        <input type="hidden" name="action" value="update_project_details">
                        <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">

                        <div class="form-grid">
                            <div class="input-group">
                                <label for="project_name">Project Name</label>
                                <input type="text" id="project_name" name="project_name" value="<?php echo htmlspecialchars($project['project_name']); ?>" required>
                            </div>

                            <div class="input-group">
                                <label for="client_id">Client</label>
                                <select id="client_id" name="client_id" required>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo (int)$client['id']; ?>" <?php echo (int)$project['client_id'] === (int)$client['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="input-group">
                                <label for="engineer_id">Engineer</label>
                                <select id="engineer_id" name="engineer_id" required>
                                    <?php foreach ($engineers as $engineer): ?>
                                        <option value="<?php echo (int)$engineer['id']; ?>" <?php echo $currentEngineerId === (int)$engineer['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($engineer['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($hasProjectAddressColumn): ?>
                                <div class="input-group input-group-wide">
                                    <label for="project_address">Project Address / Site Location</label>
                                    <textarea id="project_address" name="project_address" rows="2"><?php echo htmlspecialchars($project['project_address'] ?? ''); ?></textarea>
                                </div>
                            <?php endif; ?>

                            <div class="input-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" min="<?php echo htmlspecialchars($todayDate); ?>" value="<?php echo htmlspecialchars($project['start_date'] ?? ''); ?>">
                            </div>

                            <div class="input-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" min="<?php echo htmlspecialchars($todayDate); ?>" value="<?php echo htmlspecialchars($project['end_date'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="input-group input-group-spaced">
                            <label for="description">Description</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary" <?php echo $isCompleted ? 'disabled' : ''; ?>>Save Project Details</button>
                        </div>
                    </form>
                </section>

                <section class="form-panel project-details-panel" data-project-panel="status">
                    <h2 class="section-title-inline">Update Status</h2>
                    <?php if ($isCompleted): ?>
                        <div class="lock-note">This project is locked because it is already completed.</div>
                        <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form">
                            <input type="hidden" name="action" value="reopen_project">
                            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                            <div class="form-actions">
                                <button type="submit" class="btn-secondary">Reopen Project</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form">
                            <input type="hidden" name="action" value="update_project_status">
                            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                            <div class="form-grid">
                                <div class="input-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" required>
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

                        <?php if ($supportsCancelledStatus || $supportsArchivedStatus): ?>
                            <div class="status-quick-actions">
                                <?php if ($supportsCancelledStatus && ($project['status'] ?? '') !== 'cancelled'): ?>
                                    <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="inline-form" data-confirm-action="Cancel this project? This is for projects that will not continue.">
                                        <input type="hidden" name="action" value="update_project_status">
                                        <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="btn-secondary btn-status-danger">Mark as Cancelled</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($supportsArchivedStatus && ($project['status'] ?? '') !== 'archived'): ?>
                                    <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="inline-form" data-confirm-action="Archive this project? It will stay in history but should no longer be active.">
                                        <input type="hidden" name="action" value="update_project_status">
                                        <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                                        <input type="hidden" name="status" value="archived">
                                        <button type="submit" class="btn-secondary btn-status-neutral">Archive Project</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>

                <section class="form-panel project-details-panel" data-project-panel="tasks">
                    <h2 class="section-title-inline">Tasks</h2>

                    <?php if (empty($tasks)): ?>
                        <div class="empty-state">No tasks yet for this project.</div>
                    <?php else: ?>
                        <div class="task-list">
                            <?php foreach ($tasks as $task): ?>
                                <div class="task-item">
                                    <strong><?php echo htmlspecialchars($task['task_name']); ?></strong>
                                    <span>Status: <?php echo htmlspecialchars(ucfirst($task['status'])); ?></span>
                                    <span>Assigned To: <?php echo htmlspecialchars($task['assignee_name'] ?? 'N/A'); ?></span>
                                    <span>Deadline: <?php echo htmlspecialchars($task['deadline'] ?? 'No deadline'); ?></span>
                                    <span>Description: <?php echo htmlspecialchars($task['description'] ?: 'None'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isCompleted): ?>
                        <div class="empty-state">Task creation is disabled while this project is completed.</div>
                    <?php elseif ($isDraft): ?>
                        <div class="empty-state">Task creation is disabled while this project is still in draft.</div>
                    <?php else: ?>
                        <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form">
                            <input type="hidden" name="action" value="add_task">
                            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">

                            <h4 class="subheading">Add Task</h4>
                            <div class="form-grid">
                                <div class="input-group">
                                    <label for="task_name">Task Name</label>
                                    <input type="text" id="task_name" name="task_name" required>
                                </div>

                                <div class="input-group">
                                    <label for="assigned_to">Assign To</label>
                                    <select id="assigned_to" name="assigned_to" required>
                                        <option value="">Select engineer</option>
                                        <?php foreach ($engineers as $engineer): ?>
                                            <option value="<?php echo (int)$engineer['id']; ?>" <?php echo $currentEngineerId === (int)$engineer['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($engineer['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="input-group">
                                    <label for="deadline">Deadline</label>
                                    <input type="date" id="deadline" name="deadline" min="<?php echo htmlspecialchars($todayDate); ?>">
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="task_description">Task Description</label>
                                <textarea id="task_description" name="task_description" placeholder="Task details"></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-primary">Add Task</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="form-panel project-details-panel" data-project-panel="inventory">
                    <h2 class="section-title-inline">Deployed Inventory</h2>

                    <?php if (empty($activeDeployments)): ?>
                        <div class="empty-state">No inventory deployed to this project yet.</div>
                    <?php else: ?>
                        <div class="task-list">
                            <?php foreach ($activeDeployments as $deployment): ?>
                                <div class="task-item">
                                    <strong><?php echo htmlspecialchars($deployment['asset_name']); ?></strong>
                                    <span>Type: <?php echo htmlspecialchars($deployment['asset_type'] ?: 'N/A'); ?></span>
                                    <span>Serial: <?php echo htmlspecialchars($deployment['serial_number'] ?: 'N/A'); ?></span>
                                    <span>Deployed: <?php echo (int)$deployment['quantity']; ?></span>
                                    <span>Returned: <?php echo (int)$deployment['returned_quantity']; ?></span>
                                    <span>Remaining: <?php echo (int)$deployment['remaining_quantity']; ?></span>
                                    <span>Deployed By: <?php echo htmlspecialchars($deployment['deployed_by_name'] ?? 'N/A'); ?></span>
                                    <span>Deployed At: <?php echo htmlspecialchars($deployment['deployed_at']); ?></span>
                                    <span>Notes: <?php echo htmlspecialchars($deployment['notes'] ?: 'None'); ?></span>
                                    <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form">
                                        <input type="hidden" name="action" value="return_project_inventory">
                                        <input type="hidden" name="deployment_id" value="<?php echo (int)$deployment['id']; ?>">
                                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                                        <div class="form-grid">
                                            <div class="input-group">
                                                <label for="return_quantity-<?php echo (int)$deployment['id']; ?>">Return Qty</label>
                                                <input type="number" id="return_quantity-<?php echo (int)$deployment['id']; ?>" name="return_quantity" min="1" max="<?php echo (int)$deployment['remaining_quantity']; ?>" required>
                                            </div>
                                            <div class="input-group">
                                                <label for="return_notes-<?php echo (int)$deployment['id']; ?>">Return Notes</label>
                                                <input type="text" id="return_notes-<?php echo (int)$deployment['id']; ?>" name="return_notes" placeholder="Optional note">
                                            </div>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" class="btn-secondary">Return Inventory</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isCompleted): ?>
                        <div class="empty-state">Inventory deployment is disabled while this project is completed.</div>
                    <?php elseif ($isDraft): ?>
                        <div class="empty-state">Inventory deployment is disabled while this project is still in draft.</div>
                    <?php elseif (empty($availableInventory)): ?>
                        <div class="empty-state">No available inventory to deploy right now.</div>
                    <?php else: ?>
                        <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form">
                            <input type="hidden" name="action" value="deploy_inventory_to_project">
                            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                            <h4 class="subheading">Deploy Inventory</h4>
                            <div class="form-grid">
                                <div class="input-group">
                                    <label for="inventory_id">Inventory Item</label>
                                    <select id="inventory_id" name="inventory_id" required>
                                        <option value="">Select inventory item</option>
                                        <?php foreach ($availableInventory as $inventoryItem): ?>
                                            <option value="<?php echo (int)$inventoryItem['id']; ?>">
                                                <?php
                                                $inventoryLabel = $inventoryItem['asset_name'];
                                                if (!empty($inventoryItem['asset_type'])) {
                                                    $inventoryLabel .= ' - ' . $inventoryItem['asset_type'];
                                                }
                                                if (!empty($inventoryItem['serial_number'])) {
                                                    $inventoryLabel .= ' (SN: ' . $inventoryItem['serial_number'] . ')';
                                                }
                                                $inventoryLabel .= ' | Available: ' . (int)$inventoryItem['quantity'];
                                                echo htmlspecialchars($inventoryLabel);
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="input-group">
                                    <label for="deployment_quantity">Quantity</label>
                                    <input type="number" id="deployment_quantity" name="deployment_quantity" min="1" step="1" required>
                                </div>

                                <div class="input-group">
                                    <label for="deployment_notes">Notes</label>
                                    <input type="text" id="deployment_notes" name="deployment_notes" placeholder="Optional deployment note">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-primary">Deploy Inventory</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="form-panel project-details-panel" data-project-panel="history">
                    <h2 class="section-title-inline">Deployment History</h2>

                    <?php if (empty($deploymentHistory)): ?>
                        <div class="empty-state">No deployment history for this project yet.</div>
                    <?php else: ?>
                        <div class="task-list">
                            <?php foreach ($deploymentHistory as $historyItem): ?>
                                <div class="task-item">
                                    <strong><?php echo htmlspecialchars($historyItem['asset_name']); ?></strong>
                                    <span>Type: <?php echo htmlspecialchars($historyItem['asset_type'] ?: 'N/A'); ?></span>
                                    <span>Serial: <?php echo htmlspecialchars($historyItem['serial_number'] ?: 'N/A'); ?></span>
                                    <span>Deployed: <?php echo (int)$historyItem['quantity']; ?></span>
                                    <span>Returned: <?php echo (int)$historyItem['returned_quantity']; ?></span>
                                    <span>Remaining: <?php echo (int)$historyItem['remaining_quantity']; ?></span>
                                    <span>Status: <?php echo (int)$historyItem['remaining_quantity'] > 0 ? 'Active' : 'Closed'; ?></span>
                                    <span>Deployed By: <?php echo htmlspecialchars($historyItem['deployed_by_name'] ?? 'N/A'); ?></span>
                                    <span>Deployed At: <?php echo htmlspecialchars($historyItem['deployed_at']); ?></span>
                                    <span>Last Return: <?php echo htmlspecialchars($historyItem['last_returned_at'] ?? 'None yet'); ?></span>
                                    <span>Notes: <?php echo htmlspecialchars($historyItem['notes'] ?: 'None'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="../js/super_admin_dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirm-action]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const message = form.getAttribute('data-confirm-action') || 'Are you sure?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const tabs = Array.from(document.querySelectorAll('[data-project-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-project-panel]'));

    function setActiveProjectTab(tabName) {
        tabs.forEach(function (tab) {
            const isActive = tab.getAttribute('data-project-tab') === tabName;
            tab.classList.toggle('is-active', isActive);
        });

        panels.forEach(function (panel) {
            const isActive = panel.getAttribute('data-project-panel') === tabName;
            panel.classList.toggle('is-active', isActive);
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const tabName = tab.getAttribute('data-project-tab') || 'overview';
            setActiveProjectTab(tabName);
        });
    });
});
</script>
</body>
</html>
