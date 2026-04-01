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

function ensure_project_inventory_deployments_table(mysqli $conn): void {
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

function ensure_project_inventory_return_logs_table(mysqli $conn): void {
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

function normalize_positive_int($value): int {
    $normalized = (int)$value;
    return $normalized > 0 ? $normalized : 0;
}

function determine_inventory_status(int $quantity, ?int $minStock): string {
    if ($quantity <= 0) {
        return 'out-of-stock';
    }

    if ($minStock !== null && $quantity <= $minStock) {
        return 'low-stock';
    }

    return 'available';
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

ensure_project_inventory_deployments_table($conn);
ensure_project_inventory_return_logs_table($conn);

function get_projects_redirect_target(): string {
    $redirectTo = $_POST['redirect_to'] ?? $_GET['redirect_to'] ?? '';
    $redirectTo = is_string($redirectTo) ? trim($redirectTo) : '';

    if ($redirectTo !== '' && str_starts_with($redirectTo, '/CAPSTONE/SUPERADMIN/sidebar/')) {
        return $redirectTo;
    }

    return '/CAPSTONE/SUPERADMIN/sidebar/projects.php';
}

function redirect_projects_page(): void {
    header('Location: ' . get_projects_redirect_target());
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

function countActiveProjectInventoryDeployments(mysqli $conn, int $projectId): int {
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM (
             SELECT pid.id
             FROM project_inventory_deployments pid
             LEFT JOIN (
                 SELECT deployment_id, SUM(quantity) AS returned_quantity
                 FROM project_inventory_return_logs
                 GROUP BY deployment_id
             ) returns ON returns.deployment_id = pid.id
             WHERE pid.project_id = ?
             AND (pid.quantity - COALESCE(returns.returned_quantity, 0)) > 0
         ) active_deployments'
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

function getActiveProjectInventoryDeployment(mysqli $conn, int $deploymentId): ?array {
    $stmt = $conn->prepare(
        'SELECT
            pid.id,
            pid.project_id,
            pid.inventory_id,
            pid.quantity,
            COALESCE(returns.returned_quantity, 0) AS returned_quantity,
            (pid.quantity - COALESCE(returns.returned_quantity, 0)) AS remaining_quantity
         FROM project_inventory_deployments pid
         LEFT JOIN (
             SELECT deployment_id, SUM(quantity) AS returned_quantity
             FROM project_inventory_return_logs
             GROUP BY deployment_id
         ) returns ON returns.deployment_id = pid.id
         WHERE pid.id = ?
         AND (pid.quantity - COALESCE(returns.returned_quantity, 0)) > 0
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $deploymentId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
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
            $activeDeployments = countActiveProjectInventoryDeployments($conn, $projectId);

            if (in_array(($project['status'] ?? ''), ['pending', 'draft'], true)) {
                set_projects_flash('error', 'A pending or draft project cannot jump directly to Completed. Move it to Ongoing or On-hold first.');
                redirect_projects_page();
            }

            if ($openTasks > 0) {
                set_projects_flash('error', 'Complete all open tasks before marking this project as completed.');
                redirect_projects_page();
            }

            if ($activeDeployments > 0) {
                set_projects_flash('error', 'Return all deployed inventory before marking this project as completed.');
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

    if ($action === 'update_project_details') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $projectName = normalize_text($_POST['project_name'] ?? '');
        $description = normalize_text($_POST['description'] ?? '');
        $projectAddress = $hasProjectAddressColumn ? normalize_text_or_null($_POST['project_address'] ?? null) : null;
        $clientId = (int)($_POST['client_id'] ?? 0);
        $engineerId = (int)($_POST['engineer_id'] ?? 0);
        $startDate = normalize_date_or_null($_POST['start_date'] ?? null);
        $endDate = normalize_date_or_null($_POST['end_date'] ?? null);
        $project = $projectId > 0 ? getProjectSnapshot($conn, $projectId) : null;
        $updatedBy = (int)($_SESSION['user_id'] ?? 0);

        if ($projectId <= 0 || !$project) {
            set_projects_flash('error', 'Project not found.');
            redirect_projects_page();
        }

        if (($project['status'] ?? '') === 'completed') {
            set_projects_flash('error', 'Completed projects are locked. Reopen first before editing details.');
            redirect_projects_page();
        }

        if ($projectName === '' || $clientId <= 0 || $engineerId <= 0) {
            set_projects_flash('error', 'Project name, client, and engineer are required.');
            redirect_projects_page();
        }

        if ($hasProjectAddressColumn && ($project['status'] ?? '') !== 'draft' && $projectAddress === null) {
            set_projects_flash('error', 'Project address is required unless the project stays in Draft.');
            redirect_projects_page();
        }

        if ($startDate !== null && $startDate < $todayDate) {
            set_projects_flash('error', 'Start date cannot be earlier than today.');
            redirect_projects_page();
        }

        if (($project['status'] ?? '') === 'ongoing' && $startDate === null) {
            set_projects_flash('error', 'Ongoing projects must have a start date.');
            redirect_projects_page();
        }

        if (($project['status'] ?? '') === 'ongoing' && $startDate !== $todayDate) {
            set_projects_flash('error', 'Ongoing projects must start today when updating details.');
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
                $updateProject = $conn->prepare(
                    'UPDATE projects
                     SET project_name = ?, description = ?, client_id = ?, project_address = ?, start_date = ?, end_date = ?
                     WHERE id = ?'
                );
            } else {
                $updateProject = $conn->prepare(
                    'UPDATE projects
                     SET project_name = ?, description = ?, client_id = ?, start_date = ?, end_date = ?
                     WHERE id = ?'
                );
            }

            if (!$updateProject) {
                throw new RuntimeException('Failed to prepare project update.');
            }

            if ($hasProjectAddressColumn) {
                if (
                    !$updateProject->bind_param('ssisssi', $projectName, $description, $clientId, $projectAddress, $startDate, $endDate, $projectId) ||
                    !$updateProject->execute()
                ) {
                    throw new RuntimeException('Failed to update project details.');
                }
            } else {
                if (
                    !$updateProject->bind_param('ssissi', $projectName, $description, $clientId, $startDate, $endDate, $projectId) ||
                    !$updateProject->execute()
                ) {
                    throw new RuntimeException('Failed to update project details.');
                }
            }

            $reassignEngineer = $conn->prepare(
                'INSERT IGNORE INTO project_assignments (project_id, engineer_id, assigned_by)
                 VALUES (?, ?, ?)'
            );

            if (!$reassignEngineer) {
                throw new RuntimeException('Failed to prepare engineer reassignment.');
            }

            if (
                !$reassignEngineer->bind_param('iii', $projectId, $engineerId, $updatedBy) ||
                !$reassignEngineer->execute()
            ) {
                throw new RuntimeException('Failed to update engineer assignment.');
            }

            $conn->commit();
            set_projects_flash('success', 'Project details updated successfully.');
        } catch (Throwable $exception) {
            $conn->rollback();
            set_projects_flash('error', $exception->getMessage());
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

        if ($deadline !== null && $deadline < $todayDate) {
            set_projects_flash('error', 'Task deadline cannot be earlier than today.');
            redirect_projects_page();
        }

        if (!empty($project['start_date']) && $deadline !== null && $deadline < $project['start_date']) {
            set_projects_flash('error', 'Task deadline cannot be earlier than the project start date.');
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

    if ($action === 'deploy_inventory_to_project') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $inventoryId = (int)($_POST['inventory_id'] ?? 0);
        $quantity = normalize_positive_int($_POST['deployment_quantity'] ?? 0);
        $notes = normalize_text_or_null($_POST['deployment_notes'] ?? null);
        $deployedBy = (int)($_SESSION['user_id'] ?? 0);
        $project = $projectId > 0 ? getProjectSnapshot($conn, $projectId) : null;

        if ($projectId <= 0 || $inventoryId <= 0 || $quantity <= 0) {
            set_projects_flash('error', 'Project, inventory item, and quantity are required for deployment.');
            redirect_projects_page();
        }

        if (!$project) {
            set_projects_flash('error', 'Project not found.');
            redirect_projects_page();
        }

        if (($project['status'] ?? '') === 'draft') {
            set_projects_flash('error', 'Cannot deploy assets to a draft project.');
            redirect_projects_page();
        }

        if (($project['status'] ?? '') === 'completed') {
            set_projects_flash('error', 'Cannot deploy assets to a completed project.');
            redirect_projects_page();
        }

        $inventoryStmt = $conn->prepare(
            'SELECT i.id, i.quantity, i.min_stock, a.asset_name
             FROM inventory i
             INNER JOIN assets a ON a.id = i.asset_id
             WHERE i.id = ?
             LIMIT 1'
        );

        if (!$inventoryStmt) {
            set_projects_flash('error', 'Failed to prepare inventory lookup.');
            redirect_projects_page();
        }

        $inventoryStmt->bind_param('i', $inventoryId);
        $inventoryStmt->execute();
        $inventoryResult = $inventoryStmt->get_result();
        $inventoryItem = $inventoryResult ? $inventoryResult->fetch_assoc() : null;

        if (!$inventoryItem) {
            set_projects_flash('error', 'Selected inventory item not found.');
            redirect_projects_page();
        }

        $availableQuantity = (int)($inventoryItem['quantity'] ?? 0);

        if ($availableQuantity < $quantity) {
            set_projects_flash('error', 'Not enough stock available for that deployment quantity.');
            redirect_projects_page();
        }

        $remainingQuantity = $availableQuantity - $quantity;
        $minStock = array_key_exists('min_stock', $inventoryItem) && $inventoryItem['min_stock'] !== null
            ? (int)$inventoryItem['min_stock']
            : null;
        $nextStatus = determine_inventory_status($remainingQuantity, $minStock);

        $conn->begin_transaction();

        try {
            $deployStmt = $conn->prepare(
                'INSERT INTO project_inventory_deployments (project_id, inventory_id, quantity, deployed_by, notes)
                 VALUES (?, ?, ?, ?, ?)'
            );

            if (
                !$deployStmt ||
                !$deployStmt->bind_param('iiiis', $projectId, $inventoryId, $quantity, $deployedBy, $notes) ||
                !$deployStmt->execute()
            ) {
                throw new RuntimeException('Failed to save project inventory deployment.');
            }

            $updateInventory = $conn->prepare(
                'UPDATE inventory
                 SET quantity = ?, status = ?
                 WHERE id = ?'
            );

            if (
                !$updateInventory ||
                !$updateInventory->bind_param('isi', $remainingQuantity, $nextStatus, $inventoryId) ||
                !$updateInventory->execute()
            ) {
                throw new RuntimeException('Failed to update inventory quantity after deployment.');
            }

            $conn->commit();
            set_projects_flash('success', 'Inventory deployed to project successfully.');
        } catch (Throwable $exception) {
            $conn->rollback();
            set_projects_flash('error', $exception->getMessage());
        }

        redirect_projects_page();
    }

    if ($action === 'return_project_inventory') {
        $deploymentId = (int)($_POST['deployment_id'] ?? 0);
        $returnQuantity = normalize_positive_int($_POST['return_quantity'] ?? 0);
        $returnNotes = normalize_text_or_null($_POST['return_notes'] ?? null);
        $returnedBy = (int)($_SESSION['user_id'] ?? 0);
        $deployment = $deploymentId > 0 ? getActiveProjectInventoryDeployment($conn, $deploymentId) : null;

        if (!$deployment) {
            set_projects_flash('error', 'Active inventory deployment not found.');
            redirect_projects_page();
        }

        if ($returnQuantity <= 0) {
            set_projects_flash('error', 'Return quantity must be greater than zero.');
            redirect_projects_page();
        }

        $remainingQuantity = (int)($deployment['remaining_quantity'] ?? 0);

        if ($returnQuantity > $remainingQuantity) {
            set_projects_flash('error', 'Return quantity cannot be greater than the remaining deployed quantity.');
            redirect_projects_page();
        }

        $inventoryStmt = $conn->prepare(
            'SELECT id, quantity, min_stock
             FROM inventory
             WHERE id = ?
             LIMIT 1'
        );

        if (!$inventoryStmt) {
            set_projects_flash('error', 'Failed to prepare inventory lookup for return.');
            redirect_projects_page();
        }

        $inventoryId = (int)$deployment['inventory_id'];

        $inventoryStmt->bind_param('i', $inventoryId);
        $inventoryStmt->execute();
        $inventoryResult = $inventoryStmt->get_result();
        $inventoryItem = $inventoryResult ? $inventoryResult->fetch_assoc() : null;

        if (!$inventoryItem) {
            set_projects_flash('error', 'Inventory record not found for this deployment.');
            redirect_projects_page();
        }

        $nextQuantity = (int)$inventoryItem['quantity'] + $returnQuantity;
        $minStock = $inventoryItem['min_stock'] !== null ? (int)$inventoryItem['min_stock'] : null;
        $nextStatus = determine_inventory_status($nextQuantity, $minStock);
        $willBeFullyReturned = $returnQuantity === $remainingQuantity;

        $conn->begin_transaction();

        try {
            $logReturn = $conn->prepare(
                'INSERT INTO project_inventory_return_logs (deployment_id, quantity, returned_by, notes)
                 VALUES (?, ?, ?, ?)'
            );

            if (
                !$logReturn ||
                !$logReturn->bind_param('iiis', $deploymentId, $returnQuantity, $returnedBy, $returnNotes) ||
                !$logReturn->execute()
            ) {
                throw new RuntimeException('Failed to save inventory return log.');
            }

            $returnStmt = $conn->prepare(
                'UPDATE project_inventory_deployments
                 SET returned_at = CASE WHEN ? = 1 THEN CURRENT_TIMESTAMP ELSE returned_at END
                 WHERE id = ?
                 AND (returned_at IS NULL OR ? = 0)'
            );

            if (
                !$returnStmt ||
                !$returnStmt->bind_param('iii', $willBeFullyReturned, $deploymentId, $willBeFullyReturned) ||
                !$returnStmt->execute()
            ) {
                throw new RuntimeException('Failed to mark the deployment as returned.');
            }

            $updateInventory = $conn->prepare(
                'UPDATE inventory
                 SET quantity = ?, status = ?
                 WHERE id = ?'
            );

            if (
                !$updateInventory ||
                !$updateInventory->bind_param('isi', $nextQuantity, $nextStatus, $inventoryId) ||
                !$updateInventory->execute()
            ) {
                throw new RuntimeException('Failed to restore inventory quantity.');
            }

            $conn->commit();
            set_projects_flash('success', 'Inventory return saved successfully.');
        } catch (Throwable $exception) {
            $conn->rollback();
            set_projects_flash('error', $exception->getMessage());
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
$projectAddressSelect = $hasProjectAddressColumn ? 'p.project_address,' : 'NULL AS project_address,';
$searchQuery = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($currentPage - 1) * $perPage;
$whereClauses = [];

if ($searchQuery !== '') {
    $escapedSearch = $conn->real_escape_string($searchQuery);
    $searchLike = "'%" . $escapedSearch . "%'";
    $searchColumns = [
        'p.project_name',
        'p.description',
        'client.full_name',
        'engineer.full_name',
    ];

    if ($hasProjectAddressColumn) {
        $searchColumns[] = 'p.project_address';
    }

    $searchConditions = array_map(
        static fn(string $column): string => "{$column} LIKE {$searchLike}",
        $searchColumns
    );
    $whereClauses[] = '(' . implode(' OR ', $searchConditions) . ')';
}

if ($statusFilter !== '' && in_array($statusFilter, $statusOptions, true)) {
    $escapedStatus = $conn->real_escape_string($statusFilter);
    $whereClauses[] = "p.status = '{$escapedStatus}'";
}

$whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);

$projectMetricsResult = $conn->query("
    SELECT
        COUNT(*) AS total_projects,
        SUM(CASE WHEN p.status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_projects,
        SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_projects
    FROM projects p
");
$projectMetrics = $projectMetricsResult ? $projectMetricsResult->fetch_assoc() : [];
$totalProjects = (int)($projectMetrics['total_projects'] ?? 0);
$ongoingProjects = (int)($projectMetrics['ongoing_projects'] ?? 0);
$completedProjects = (int)($projectMetrics['completed_projects'] ?? 0);

$taskMetricsResult = $conn->query("SELECT COUNT(*) AS total_tasks FROM tasks");
$taskMetrics = $taskMetricsResult ? $taskMetricsResult->fetch_assoc() : [];
$totalTasks = (int)($taskMetrics['total_tasks'] ?? 0);

$projectCountQuery = "
    SELECT COUNT(*) AS total
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
    {$whereSql}
";

$projectCountResult = $conn->query($projectCountQuery);
$filteredProjects = 0;
if ($projectCountResult) {
    $projectCountRow = $projectCountResult->fetch_assoc();
    $filteredProjects = (int)($projectCountRow['total'] ?? 0);
}

$totalPages = max(1, (int)ceil($filteredProjects / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}

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
    {$whereSql}
    ORDER BY p.created_at DESC, p.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";

$projectsResult = $conn->query($projectsQuery);
if ($projectsResult) {
    $projects = $projectsResult->fetch_all(MYSQLI_ASSOC);
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
                    <form method="GET" class="project-toolbar" id="project-search-form">
                        <input type="search" id="project-search" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search project, client, engineer, or site">
                        <select id="project-status-filter" name="status">
                            <option value="">All statuses</option>
                            <?php foreach ($statusOptions as $statusOption): ?>
                                <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $statusFilter === $statusOption ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($statusOption)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-primary">Search</button>
                    </form>

                    <div class="project-results-meta">
                        <span>Showing <?php echo count($projects); ?> of <?php echo $filteredProjects; ?> matching projects</span>
                        <span>Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
                    </div>

                    <div class="projects-grid" id="projects-grid">
                        <?php foreach ($projects as $project): ?>
                            <?php
                            $isDraft = ($project['status'] ?? '') === 'draft';
                            $isCompleted = ($project['status'] ?? '') === 'completed';
                            $searchText = strtolower(trim(implode(' ', [
                                $project['project_name'] ?? '',
                                $project['client_name'] ?? '',
                                $project['engineer_name'] ?? '',
                                $project['project_address'] ?? '',
                                $project['status'] ?? '',
                            ])));
                            $detailsPath = '/CAPSTONE/SUPERADMIN/sidebar/project_details.php?id=' . (int)$project['id'];
                            ?>
                            <article class="project-card<?php echo $isCompleted ? ' is-locked' : ''; ?><?php echo $isDraft ? ' is-draft' : ''; ?>" data-project-card data-status="<?php echo htmlspecialchars($project['status']); ?>" data-search="<?php echo htmlspecialchars($searchText); ?>">
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
                                    <div class="empty-state empty-state-solid"><?php echo nl2br(htmlspecialchars($project['description'])); ?></div>
                                <?php endif; ?>

                                <div class="form-actions">
                                    <a href="<?php echo htmlspecialchars($detailsPath); ?>" class="btn-primary">View Details</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <?php
                                $pageParams = [];
                                if ($searchQuery !== '') {
                                    $pageParams['q'] = $searchQuery;
                                }
                                if ($statusFilter !== '') {
                                    $pageParams['status'] = $statusFilter;
                                }
                                $pageParams['page'] = $page;
                                $pageLink = '/CAPSTONE/SUPERADMIN/sidebar/projects.php?' . http_build_query($pageParams);
                                ?>
                                <a href="<?php echo htmlspecialchars($pageLink); ?>" class="pagination-link<?php echo $page === $currentPage ? ' is-active' : ''; ?>">
                                    <?php echo $page; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
<script src="../js/admin-script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('project-search');
    const statusFilter = document.getElementById('project-status-filter');
    const cards = Array.from(document.querySelectorAll('[data-project-card]'));

    function applyProjectFilters() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        const status = (statusFilter?.value || '').trim().toLowerCase();

        cards.forEach((card) => {
            const cardSearch = (card.getAttribute('data-search') || '').toLowerCase();
            const cardStatus = (card.getAttribute('data-status') || '').toLowerCase();
            const matchesQuery = query === '' || cardSearch.includes(query);
            const matchesStatus = status === '' || cardStatus === status;

            card.hidden = !(matchesQuery && matchesStatus);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyProjectFilters);
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', applyProjectFilters);
    }
});
</script>
</body>
</html>
