<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/audit_log.php';
require_once __DIR__ . '/project_search_support.php';

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

function ensure_project_budget_profiles_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS project_budget_profiles (
            project_id INT(11) NOT NULL PRIMARY KEY,
            budget_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            budget_notes TEXT DEFAULT NULL,
            created_by INT(11) NOT NULL,
            updated_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_project_budget_profiles_updated_at (updated_at),
            CONSTRAINT fk_project_budget_profiles_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
            CONSTRAINT fk_project_budget_profiles_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_project_budget_profiles_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function ensure_project_cost_entries_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS project_cost_entries (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            project_id INT(11) NOT NULL,
            cost_date DATE NOT NULL,
            cost_category VARCHAR(80) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            amount DECIMAL(14,2) NOT NULL,
            created_by INT(11) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_project_cost_entries_project_date (project_id, cost_date, id),
            KEY idx_project_cost_entries_created_by (created_by),
            CONSTRAINT fk_project_cost_entries_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
            CONSTRAINT fk_project_cost_entries_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function normalize_positive_int($value): int {
    $normalized = (int)$value;
    return $normalized > 0 ? $normalized : 0;
}

function normalize_money_or_null($value): ?float {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $normalized = str_replace([',', ' '], '', $value);
    if (!is_numeric($normalized)) {
        return null;
    }

    return round((float)$normalized, 2);
}

function format_money($value): string {
    return 'PHP ' . number_format((float)$value, 2);
}

function format_project_reference(int $projectId): string {
    return 'PRJ-' . str_pad((string)$projectId, 5, '0', STR_PAD_LEFT);
}

function build_budget_health(float $budgetAmount, float $totalCost): array {
    if ($budgetAmount <= 0) {
        return ['status' => 'unplanned', 'label' => 'No budget set'];
    }

    $usage = $totalCost / $budgetAmount;
    if ($usage >= 1) {
        return ['status' => 'over', 'label' => 'Over budget'];
    }

    if ($usage >= 0.85) {
        return ['status' => 'warning', 'label' => 'Budget watch'];
    }

    return ['status' => 'healthy', 'label' => 'On track'];
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
$supportsCancelledStatus = enum_supports_value($conn, 'projects', 'status', 'cancelled');
$supportsArchivedStatus = enum_supports_value($conn, 'projects', 'status', 'archived');
$hasProjectAddressColumn = table_has_column($conn, 'projects', 'project_address');
ensure_project_search_indexes($conn, $hasProjectAddressColumn);
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
$initialStatusOptions = $supportsDraftStatus
    ? ['draft', 'pending', 'ongoing']
    : ['pending', 'ongoing'];
$todayDate = today_date();

ensure_project_inventory_deployments_table($conn);
ensure_project_inventory_return_logs_table($conn);
ensure_project_budget_profiles_table($conn);
ensure_project_cost_entries_table($conn);

function get_projects_redirect_target(): string {
    $redirectTo = $_POST['redirect_to'] ?? $_GET['redirect_to'] ?? '';
    $redirectTo = is_string($redirectTo) ? trim($redirectTo) : '';

    if ($redirectTo !== '' && str_starts_with($redirectTo, '/codesamplecaps/SUPERADMIN/sidebar/')) {
        return $redirectTo;
    }

    return '/codesamplecaps/SUPERADMIN/sidebar/projects.php';
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

function getProjectFinancialSnapshot(mysqli $conn, int $projectId): ?array {
    $stmt = $conn->prepare(
        'SELECT
            p.id,
            p.project_name,
            p.status,
            COALESCE(bp.budget_amount, 0) AS budget_amount,
            bp.budget_notes,
            COALESCE(cost_totals.total_cost, 0) AS total_cost,
            COALESCE(cost_totals.cost_entry_count, 0) AS cost_entry_count
         FROM projects p
         LEFT JOIN project_budget_profiles bp ON bp.project_id = p.id
         LEFT JOIN (
             SELECT project_id, SUM(amount) AS total_cost, COUNT(*) AS cost_entry_count
             FROM project_cost_entries
             GROUP BY project_id
         ) cost_totals ON cost_totals.project_id = p.id
         WHERE p.id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result ? $result->fetch_assoc() : null;
}

function fetchRecentProjectCostEntries(mysqli $conn, array $projectIds, int $limitPerProject = 3): array {
    $projectIds = array_values(array_filter(array_map('intval', $projectIds)));
    if ($projectIds === []) {
        return [];
    }

    $inList = implode(',', $projectIds);
    $result = $conn->query(
        "SELECT
            pce.project_id,
            pce.cost_date,
            pce.cost_category,
            pce.description,
            pce.amount,
            u.full_name AS created_by_name
         FROM project_cost_entries pce
         LEFT JOIN users u ON u.id = pce.created_by
         WHERE pce.project_id IN ({$inList})
         ORDER BY pce.project_id ASC, pce.cost_date DESC, pce.id DESC"
    );

    if (!$result) {
        return [];
    }

    $groupedEntries = [];
    while ($row = $result->fetch_assoc()) {
        $projectId = (int)($row['project_id'] ?? 0);
        if (!isset($groupedEntries[$projectId])) {
            $groupedEntries[$projectId] = [];
        }

        if (count($groupedEntries[$projectId]) >= $limitPerProject) {
            continue;
        }

        $groupedEntries[$projectId][] = $row;
    }

    return $groupedEntries;
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

function projectNameExists(mysqli $conn, string $projectName, ?int $excludeProjectId = null): bool {
    $normalizedName = trim(mb_strtolower($projectName));

    if ($normalizedName === '') {
        return false;
    }

    if ($excludeProjectId !== null && $excludeProjectId > 0) {
        $stmt = $conn->prepare(
            'SELECT id
             FROM projects
             WHERE LOWER(TRIM(project_name)) = ?
             AND id <> ?
             LIMIT 1'
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('si', $normalizedName, $excludeProjectId);
    } else {
        $stmt = $conn->prepare(
            'SELECT id
             FROM projects
             WHERE LOWER(TRIM(project_name)) = ?
             LIMIT 1'
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $normalizedName);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    return (bool)($result && $result->fetch_assoc());
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
            a.asset_name,
            COALESCE(returns.returned_quantity, 0) AS returned_quantity,
            (pid.quantity - COALESCE(returns.returned_quantity, 0)) AS remaining_quantity
         FROM project_inventory_deployments pid
         INNER JOIN inventory i ON i.id = pid.inventory_id
         INNER JOIN assets a ON a.id = i.asset_id
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
        $endDate = null;
        $budgetAmount = normalize_money_or_null($_POST['budget_amount'] ?? null);
        $budgetNotes = normalize_text_or_null($_POST['budget_notes'] ?? null);
        $createdBy = (int)($_SESSION['user_id'] ?? 0);

        if ($projectName === '' || $clientId <= 0 || $engineerId <= 0) {
            set_projects_flash('error', 'Project name, client, and engineer are required.');
            redirect_projects_page();
        }

        if (projectNameExists($conn, $projectName)) {
            set_projects_flash('error', 'Project name already exists. Use a more specific name like site, phase, or year.');
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

        if (($_POST['budget_amount'] ?? '') !== '' && $budgetAmount === null) {
            set_projects_flash('error', 'Budget must be a valid amount.');
            redirect_projects_page();
        }

        if ($budgetAmount !== null && $budgetAmount < 0) {
            set_projects_flash('error', 'Budget cannot be negative.');
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

            if ($budgetAmount !== null || $budgetNotes !== null) {
                $saveBudget = $conn->prepare(
                    'INSERT INTO project_budget_profiles (project_id, budget_amount, budget_notes, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?)'
                );

                if (!$saveBudget) {
                    throw new RuntimeException('Failed to prepare project budget.');
                }

                $initialBudget = $budgetAmount ?? 0.00;
                if (
                    !$saveBudget->bind_param('idsii', $projectId, $initialBudget, $budgetNotes, $createdBy, $createdBy) ||
                    !$saveBudget->execute()
                ) {
                    throw new RuntimeException('Failed to save project budget.');
                }
            }

            $conn->commit();
            audit_log_event(
                $conn,
                $createdBy,
                'create_project',
                'project',
                $projectId,
                null,
                [
                    'project_name' => $projectName,
                    'status' => $status,
                    'client_id' => $clientId,
                    'engineer_id' => $engineerId,
                    'budget_amount' => $budgetAmount,
                ]
            );
            set_projects_flash('success', 'Project created successfully.');
        } catch (Throwable $exception) {
            $conn->rollback();
            set_projects_flash('error', $exception->getMessage());
        }

        redirect_projects_page();
    }

    if ($action === 'save_project_budget') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $budgetAmountInput = $_POST['budget_amount'] ?? '';
        $budgetAmount = normalize_money_or_null($budgetAmountInput);
        $budgetNotes = normalize_text_or_null($_POST['budget_notes'] ?? null);
        $updatedBy = (int)($_SESSION['user_id'] ?? 0);
        $projectFinancials = $projectId > 0 ? getProjectFinancialSnapshot($conn, $projectId) : null;

        if ($projectId <= 0 || !$projectFinancials) {
            set_projects_flash('error', 'Project not found.');
            redirect_projects_page();
        }

        if ($budgetAmountInput !== '' && $budgetAmount === null) {
            set_projects_flash('error', 'Budget must be a valid amount.');
            redirect_projects_page();
        }

        if ($budgetAmount === null || $budgetAmount < 0) {
            set_projects_flash('error', 'Budget cannot be blank or negative.');
            redirect_projects_page();
        }

        $saveBudget = $conn->prepare(
            'INSERT INTO project_budget_profiles (project_id, budget_amount, budget_notes, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                budget_amount = VALUES(budget_amount),
                budget_notes = VALUES(budget_notes),
                updated_by = VALUES(updated_by)'
        );

        if (
            $saveBudget &&
            $saveBudget->bind_param('idsii', $projectId, $budgetAmount, $budgetNotes, $updatedBy, $updatedBy) &&
            $saveBudget->execute()
        ) {
            audit_log_event(
                $conn,
                $updatedBy,
                'update_project_budget',
                'project',
                $projectId,
                [
                    'project_name' => $projectFinancials['project_name'] ?? null,
                    'budget_amount' => (float)($projectFinancials['budget_amount'] ?? 0),
                    'budget_notes' => $projectFinancials['budget_notes'] ?? null,
                ],
                [
                    'project_name' => $projectFinancials['project_name'] ?? null,
                    'budget_amount' => $budgetAmount,
                    'budget_notes' => $budgetNotes,
                ]
            );
            set_projects_flash('success', 'Project budget saved.');
        } else {
            set_projects_flash('error', 'Failed to save project budget.');
        }

        redirect_projects_page();
    }

    if ($action === 'add_project_cost_entry') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $costDate = normalize_date_or_null($_POST['cost_date'] ?? null);
        $costCategory = normalize_text($_POST['cost_category'] ?? '');
        $costDescription = normalize_text_or_null($_POST['cost_description'] ?? null);
        $amountInput = $_POST['cost_amount'] ?? '';
        $costAmount = normalize_money_or_null($amountInput);
        $createdBy = (int)($_SESSION['user_id'] ?? 0);
        $projectFinancials = $projectId > 0 ? getProjectFinancialSnapshot($conn, $projectId) : null;

        if ($projectId <= 0 || !$projectFinancials) {
            set_projects_flash('error', 'Project not found.');
            redirect_projects_page();
        }

        if ($costDate === null || $costCategory === '') {
            set_projects_flash('error', 'Cost date and category are required.');
            redirect_projects_page();
        }

        if ($amountInput === '' || $costAmount === null || $costAmount <= 0) {
            set_projects_flash('error', 'Cost amount must be greater than zero.');
            redirect_projects_page();
        }

        $insertCostEntry = $conn->prepare(
            'INSERT INTO project_cost_entries (project_id, cost_date, cost_category, description, amount, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (
            $insertCostEntry &&
            $insertCostEntry->bind_param('isssdi', $projectId, $costDate, $costCategory, $costDescription, $costAmount, $createdBy) &&
            $insertCostEntry->execute()
        ) {
            audit_log_event(
                $conn,
                $createdBy,
                'add_project_cost',
                'project',
                $projectId,
                null,
                [
                    'project_name' => $projectFinancials['project_name'] ?? null,
                    'cost_date' => $costDate,
                    'cost_category' => $costCategory,
                    'amount' => $costAmount,
                    'description' => $costDescription,
                ]
            );
            set_projects_flash('success', 'Project cost entry added.');
        } else {
            set_projects_flash('error', 'Failed to save project cost entry.');
        }

        redirect_projects_page();
    }

    if ($action === 'update_project_status') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $status = normalize_text($_POST['status'] ?? '');
        $project = $projectId > 0 ? getProjectSnapshot($conn, $projectId) : null;
        $completedAt = $status === 'completed' ? $todayDate : null;

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

        if (!in_array(($project['status'] ?? ''), ['pending', 'draft'], true) && $status === 'draft') {
            set_projects_flash('error', 'Only projects that have not started yet can stay in Draft.');
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

        if (in_array($status, ['cancelled', 'archived'], true)) {
            $activeDeployments = countActiveProjectInventoryDeployments($conn, $projectId);

            if ($activeDeployments > 0) {
                set_projects_flash('error', 'Return all deployed inventory before cancelling or archiving this project.');
                redirect_projects_page();
            }
        }

        $updateStatus = $conn->prepare('UPDATE projects SET status = ?, end_date = ? WHERE id = ?');

        if ($updateStatus && $updateStatus->bind_param('ssi', $status, $completedAt, $projectId) && $updateStatus->execute()) {
            audit_log_event(
                $conn,
                (int)($_SESSION['user_id'] ?? 0),
                'update_project_status',
                'project',
                $projectId,
                [
                    'project_name' => $project['project_name'] ?? null,
                    'status' => $project['status'] ?? null,
                ],
                [
                    'project_name' => $project['project_name'] ?? null,
                    'status' => $status,
                    'end_date' => $completedAt,
                ]
            );
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
        $endDate = null;
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

        if (projectNameExists($conn, $projectName, $projectId)) {
            set_projects_flash('error', 'Project name already exists. Use a more specific name like site, phase, or year.');
            redirect_projects_page();
        }

        if ($hasProjectAddressColumn && ($project['status'] ?? '') !== 'draft' && $projectAddress === null) {
            set_projects_flash('error', 'Project address is required unless the project stays in Draft.');
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
            audit_log_event(
                $conn,
                $updatedBy,
                'update_project_details',
                'project',
                $projectId,
                [
                    'project_name' => $project['project_name'] ?? null,
                    'status' => $project['status'] ?? null,
                ],
                [
                    'project_name' => $projectName,
                    'client_id' => $clientId,
                    'engineer_id' => $engineerId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            );
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

        $insertTask = $conn->prepare(
            'INSERT INTO tasks (project_id, assigned_to, task_name, description, deadline, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (
            $insertTask &&
            $insertTask->bind_param('iisssi', $projectId, $assignedTo, $taskName, $description, $deadline, $createdBy) &&
            $insertTask->execute()
        ) {
            audit_log_event(
                $conn,
                $createdBy,
                'add_task',
                'task',
                (int)$insertTask->insert_id,
                null,
                [
                    'project_name' => $project['project_name'] ?? null,
                    'task_name' => $taskName,
                    'assigned_to' => $assignedTo,
                    'deadline' => $deadline,
                ]
            );
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

        $reopenProject = $conn->prepare("UPDATE projects SET status = 'ongoing', end_date = NULL WHERE id = ?");

        if ($reopenProject && $reopenProject->bind_param('i', $projectId) && $reopenProject->execute()) {
            audit_log_event(
                $conn,
                (int)($_SESSION['user_id'] ?? 0),
                'update_project_status',
                'project',
                $projectId,
                [
                    'project_name' => $project['project_name'] ?? null,
                    'status' => $project['status'] ?? null,
                ],
                [
                    'project_name' => $project['project_name'] ?? null,
                    'status' => 'ongoing',
                    'end_date' => null,
                ]
            );
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
            audit_log_event(
                $conn,
                $deployedBy,
                'deploy_inventory_to_project',
                'deployment',
                (int)$deployStmt->insert_id,
                null,
                [
                    'project_name' => $project['project_name'] ?? null,
                    'asset_name' => $inventoryItem['asset_name'] ?? null,
                    'quantity' => $quantity,
                    'remaining_quantity' => $remainingQuantity,
                ]
            );
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
            audit_log_event(
                $conn,
                $returnedBy,
                'return_project_inventory',
                'deployment',
                $deploymentId,
                [
                    'quantity' => $remainingQuantity,
                ],
                [
                    'asset_name' => $deployment['asset_name'] ?? null,
                    'quantity' => $returnQuantity,
                    'next_inventory_quantity' => $nextQuantity,
                ]
            );
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
$searchQuery = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
if (!in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = '';
}
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($currentPage - 1) * $perPage;

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
$statusCounts = array_fill_keys($statusOptions, 0);

$statusCountsResult = $conn->query("
    SELECT p.status, COUNT(*) AS total
    FROM projects p
    GROUP BY p.status
");
if ($statusCountsResult) {
    while ($statusRow = $statusCountsResult->fetch_assoc()) {
        $statusKey = (string)($statusRow['status'] ?? '');
        if (array_key_exists($statusKey, $statusCounts)) {
            $statusCounts[$statusKey] = (int)($statusRow['total'] ?? 0);
        }
    }
}

$filteredProjects = project_search_fetch_count($conn, $hasProjectAddressColumn, $searchQuery, $statusFilter);

$totalPages = max(1, (int)ceil($filteredProjects / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $perPage;
}

$projects = project_search_fetch_page($conn, $hasProjectAddressColumn, $searchQuery, $statusFilter, $perPage, $offset);
$projectIds = array_map(static fn(array $project): int => (int)($project['id'] ?? 0), $projects);
$recentProjectCosts = fetchRecentProjectCostEntries($conn, $projectIds);
$financialSummaryResult = $conn->query(
    "SELECT
        COALESCE(SUM(bp.budget_amount), 0) AS total_budget,
        COALESCE((SELECT SUM(amount) FROM project_cost_entries), 0) AS total_cost,
        COUNT(bp.project_id) AS projects_with_budget,
        (SELECT COUNT(*) FROM project_cost_entries) AS total_cost_entries
     FROM project_budget_profiles bp"
);
$financialSummary = $financialSummaryResult ? $financialSummaryResult->fetch_assoc() : [];
$totalBudgetAmount = (float)($financialSummary['total_budget'] ?? 0);
$totalTrackedCost = (float)($financialSummary['total_cost'] ?? 0);
$projectsWithBudget = (int)($financialSummary['projects_with_budget'] ?? 0);
$totalCostEntries = (int)($financialSummary['total_cost_entries'] ?? 0);
$budgetCoverageRate = $totalProjects > 0 ? round(($projectsWithBudget / $totalProjects) * 100) : 0;
$portfolioRemainingBudget = $totalBudgetAmount - $totalTrackedCost;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management - Super Admin</title>
    <link rel="stylesheet" href="../css/super_admin_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../super_admin_sidebar.php'; ?>

    <main class="main-content projects-content">
        <div class="page-stack">
            <?php if ($flash): ?>
                <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <section class="metrics-grid">
                <article class="metric-card">
                    <span>Total Budget</span>
                    <strong><?php echo htmlspecialchars(format_money($totalBudgetAmount)); ?></strong>
                </article>
                <article class="metric-card">
                    <span>Total Recorded Cost</span>
                    <strong><?php echo htmlspecialchars(format_money($totalTrackedCost)); ?></strong>
                </article>
                <article class="metric-card">
                    <span>Remaining Portfolio Budget</span>
                    <strong><?php echo htmlspecialchars(format_money($portfolioRemainingBudget)); ?></strong>
                </article>
                <article class="metric-card">
                    <span>Coverage / Entries</span>
                    <strong><?php echo $budgetCoverageRate; ?>% / <?php echo $totalCostEntries; ?></strong>
                </article>
            </section>

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
                                            Use Draft for incomplete or possibly wrong project entries. Use Pending for approved work, and choose Ongoing when work is already active.
                                        <?php else: ?>
                                            Use Pending for approved work. Choose Ongoing when work is already active.
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
                                <label for="start_date">P.O Date</label>
                                <button type="button" class="field-tip" aria-label="P.O date reminder">
                                    <span class="field-tip__icon" aria-hidden="true">i</span>
                                    <span class="field-tip__bubble">Use the purchase order date here. This stays editable while the project is not yet completed.</span>
                                </button>
                            </div>
                            <input type="date" id="start_date" name="start_date">
                        </div>

                        <div class="input-group">
                            <label for="budget_amount">Project Budget</label>
                            <input type="number" id="budget_amount" name="budget_amount" min="0" step="0.01" placeholder="0.00">
                        </div>

                        <div class="input-group input-group-wide">
                            <label for="budget_notes">Budget Notes</label>
                            <textarea id="budget_notes" name="budget_notes" rows="2" placeholder="Approved ceiling, scope assumption, supplier cap, or payment notes"></textarea>
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

            <section
                class="page-stack"
                id="projects-list-section"
                data-reset-url="/codesamplecaps/SUPERADMIN/sidebar/projects.php<?php echo $statusFilter !== '' ? '?' . http_build_query(['status' => $statusFilter]) : ''; ?>"
                data-search-endpoint="/codesamplecaps/SUPERADMIN/sidebar/project_search_api.php"
            >
                <h2 class="section-title-inline">Projects</h2>
                <div class="project-controls">
                    <div class="project-filter-chips">
                        <?php
                        $chipOptions = ['' => 'All'];
                        foreach ($statusOptions as $statusOption) {
                            $chipOptions[$statusOption] = ucfirst($statusOption);
                        }
                        foreach ($chipOptions as $chipValue => $chipLabel):
                            $chipParams = [];
                            if ($searchQuery !== '') {
                                $chipParams['q'] = $searchQuery;
                            }
                            if ($chipValue !== '') {
                                $chipParams['status'] = $chipValue;
                            }
                            $chipLink = '/codesamplecaps/SUPERADMIN/sidebar/projects.php' . ($chipParams ? '?' . http_build_query($chipParams) : '');
                            $isActiveChip = $statusFilter === $chipValue;
                            $chipTone = $chipValue === '' ? 'all' : str_replace('_', '-', $chipValue);
                        ?>
                            <a href="<?php echo htmlspecialchars($chipLink); ?>" class="project-filter-chip project-filter-chip--<?php echo htmlspecialchars($chipTone); ?><?php echo $isActiveChip ? ' is-active' : ''; ?>">
                                <?php
                                $chipCount = $chipValue === '' ? $totalProjects : (int)($statusCounts[$chipValue] ?? 0);
                                echo htmlspecialchars($chipLabel . ' (' . $chipCount . ')');
                                ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <form method="GET" class="project-toolbar" id="project-search-form">
                        <?php if ($statusFilter !== ''): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                        <?php endif; ?>
                        <div class="project-search-shell">
                            <div class="project-search-input-row">
                                <span class="project-search-icon" aria-hidden="true">&#128269;</span>
                                <input
                                    type="text"
                                    id="project-search"
                                    name="q"
                                    value="<?php echo htmlspecialchars($searchQuery); ?>"
                                    placeholder="Search project, client, engineer, or site"
                                    autocomplete="off"
                                    aria-autocomplete="list"
                                    aria-haspopup="listbox"
                                    aria-controls="project-search-dropdown"
                                    aria-expanded="false"
                                >
                                <button
                                    type="button"
                                    class="project-search-clear<?php echo $searchQuery !== '' ? ' is-visible' : ''; ?>"
                                    id="project-search-clear"
                                    aria-label="Clear search"
                                >
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="project-search-dropdown" id="project-search-dropdown" role="listbox" hidden></div>
                        </div>
                    </form>
                </div>

                <?php if (empty($projects)): ?>
                    <div class="empty-state">
                        <?php echo $searchQuery !== '' || $statusFilter !== '' ? 'No matching projects found.' : 'No projects yet. Create your first project above.'; ?>
                    </div>
                <?php else: ?>
                    <div class="project-results-meta">
                        <span>Showing <?php echo count($projects); ?> of <?php echo $filteredProjects; ?> matching projects</span>
                        <span>Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
                    </div>

                    <div class="projects-grid" id="projects-grid">
                        <?php foreach ($projects as $project): ?>
                            <?php
                            $isDraft = ($project['status'] ?? '') === 'draft';
                            $isCompleted = ($project['status'] ?? '') === 'completed';
                            $budgetAmount = (float)($project['budget_amount'] ?? 0);
                            $budgetNotes = (string)($project['budget_notes'] ?? '');
                            $totalCost = (float)($project['total_cost'] ?? 0);
                            $remainingBudget = $budgetAmount - $totalCost;
                            $budgetUsage = $budgetAmount > 0 ? min(100, round(($totalCost / $budgetAmount) * 100)) : 0;
                            $budgetHealth = build_budget_health($budgetAmount, $totalCost);
                            $projectRecentCosts = $recentProjectCosts[(int)($project['id'] ?? 0)] ?? [];
                            $projectReference = format_project_reference((int)($project['id'] ?? 0));
                            $clientEmail = trim((string)($project['client_email'] ?? ''));
                            $searchText = strtolower(trim(implode(' ', [
                                $project['project_name'] ?? '',
                                $project['client_name'] ?? '',
                                $project['engineer_name'] ?? '',
                                $project['project_address'] ?? '',
                                $project['status'] ?? '',
                            ])));
                            $detailsPath = '/codesamplecaps/SUPERADMIN/sidebar/project_details.php?id=' . (int)$project['id'];
                            ?>
                            <article class="project-card<?php echo $isCompleted ? ' is-locked' : ''; ?><?php echo $isDraft ? ' is-draft' : ''; ?>" data-project-card data-status="<?php echo htmlspecialchars($project['status']); ?>" data-search="<?php echo htmlspecialchars($searchText); ?>" data-title="<?php echo htmlspecialchars($project['project_name']); ?>" data-link="<?php echo htmlspecialchars($detailsPath); ?>" data-client="<?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?>" data-engineer="<?php echo htmlspecialchars($project['engineer_name'] ?? 'Not assigned'); ?>">
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
                                        <div><strong>P.O Date:</strong> <?php echo htmlspecialchars($project['start_date'] ?? 'N/A'); ?></div>
                                        <div><strong>Completed:</strong> <?php echo htmlspecialchars($project['end_date'] ?? 'N/A'); ?></div>
                                        <div><strong>Tasks:</strong> <?php echo (int)$project['completed_tasks']; ?> / <?php echo (int)$project['total_tasks']; ?> completed</div>
                                    </div>
                                </div>

                                <?php if (!empty($project['description'])): ?>
                                    <div class="empty-state empty-state-solid project-card__description"><?php echo nl2br(htmlspecialchars($project['description'])); ?></div>
                                <?php endif; ?>

                                <section class="budget-panel budget-panel--<?php echo htmlspecialchars($budgetHealth['status']); ?>">
                                    <div class="budget-panel__header">
                                        <div>
                                            <h4>Budget Overview</h4>
                                            <span class="budget-health budget-health--<?php echo htmlspecialchars($budgetHealth['status']); ?>">
                                                <?php echo htmlspecialchars($budgetHealth['label']); ?>
                                            </span>
                                        </div>
                                        <strong><?php echo htmlspecialchars(format_money($remainingBudget)); ?></strong>
                                    </div>
                                    <div class="budget-stats">
                                        <div>
                                            <span>Budget</span>
                                            <strong><?php echo htmlspecialchars(format_money($budgetAmount)); ?></strong>
                                        </div>
                                        <div>
                                            <span>Actual</span>
                                            <strong><?php echo htmlspecialchars(format_money($totalCost)); ?></strong>
                                        </div>
                                        <div>
                                            <span>Entries</span>
                                            <strong><?php echo (int)($project['cost_entry_count'] ?? 0); ?></strong>
                                        </div>
                                    </div>
                                    <div class="budget-progress">
                                        <div class="budget-progress__track">
                                            <span class="budget-progress__fill budget-progress__fill--<?php echo htmlspecialchars($budgetHealth['status']); ?>" style="width: <?php echo $budgetUsage; ?>%;"></span>
                                        </div>
                                        <small><?php echo $budgetAmount > 0 ? $budgetUsage . '% spent' : 'Set a budget to track project variance'; ?></small>
                                    </div>
                                    <?php if ($budgetNotes !== ''): ?>
                                        <div class="budget-notes"><?php echo nl2br(htmlspecialchars($budgetNotes)); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($projectRecentCosts)): ?>
                                        <div class="budget-ledger">
                                            <?php foreach ($projectRecentCosts as $costEntry): ?>
                                                <div class="budget-ledger__item">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($costEntry['cost_category'] ?? 'Cost'); ?></strong>
                                                        <span><?php echo htmlspecialchars($costEntry['cost_date'] ?? ''); ?><?php echo !empty($costEntry['created_by_name']) ? ' • ' . htmlspecialchars($costEntry['created_by_name']) : ''; ?></span>
                                                        <?php if (!empty($costEntry['description'])): ?>
                                                            <small><?php echo htmlspecialchars($costEntry['description']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars(format_money((float)($costEntry['amount'] ?? 0))); ?></strong>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-state">No cost entries yet.</div>
                                    <?php endif; ?>
                                </section>

                                <div class="form-actions project-card__actions">
                                    <a href="<?php echo htmlspecialchars($detailsPath); ?>" class="btn-primary project-card__details-btn">View Details</a>
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
                                $pageLink = '/codesamplecaps/SUPERADMIN/sidebar/projects.php?' . http_build_query($pageParams);
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
<script src="../js/super_admin_dashboard.js"></script>
<script>
function initProjectSearchUI() {
    const section = document.getElementById('projects-list-section');
    const searchForm = document.getElementById('project-search-form');
    const searchInput = document.getElementById('project-search');
    const searchClear = document.getElementById('project-search-clear');
    const searchDropdown = document.getElementById('project-search-dropdown');
    const projectCards = Array.from(document.querySelectorAll('[data-project-card]'));
    const statusInput = searchForm?.querySelector('input[name="status"]');
    let activeSuggestionIndex = -1;
    let searchDebounceId = null;
    const savedFocusState = window.__projectSearchFocusState || null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function highlightMatch(text, query) {
        const lowerText = text.toLowerCase();
        const matchIndex = lowerText.indexOf(query);

        if (matchIndex === -1 || query === '') {
            return escapeHtml(text);
        }

        const before = escapeHtml(text.slice(0, matchIndex));
        const matched = escapeHtml(text.slice(matchIndex, matchIndex + query.length));
        const after = escapeHtml(text.slice(matchIndex + query.length));

        return before + '<mark>' + matched + '</mark>' + after;
    }

    function getSuggestionLinks() {
        return Array.from(searchDropdown?.querySelectorAll('.project-search-result') || []);
    }

    function syncSuggestionFocus() {
        const links = getSuggestionLinks();

        links.forEach(function (link, index) {
            link.classList.toggle('is-active', index === activeSuggestionIndex);
        });
    }

    function calculateSearchScore(card, query) {
        const title = (card.getAttribute('data-title') || '').toLowerCase();
        const client = (card.getAttribute('data-client') || '').toLowerCase();
        const engineer = (card.getAttribute('data-engineer') || '').toLowerCase();
        const status = (card.getAttribute('data-status') || '').toLowerCase();
        let score = 0;

        // Title match gets highest priority
        if (title.startsWith(query)) score += 100;
        else if (title.includes(query)) score += 80;

        // Status match
        if (status.startsWith(query)) score += 50;
        else if (status.includes(query)) score += 30;

        // Client match
        if (client.startsWith(query)) score += 40;
        else if (client.includes(query)) score += 20;

        // Engineer match
        if (engineer.startsWith(query)) score += 40;
        else if (engineer.includes(query)) score += 15;

        return score;
    }

    function updateSearchDropdown() {
        if (!searchInput || !searchDropdown) {
            return;
        }

        const query = searchInput.value.trim().toLowerCase();

        if (query.length < 1) {
            searchDropdown.hidden = true;
            searchDropdown.innerHTML = '';
            activeSuggestionIndex = -1;
            return;
        }

        const matches = projectCards
            .map(function (card) {
                return {
                    card: card,
                    score: calculateSearchScore(card, query)
                };
            })
            .filter(function (item) {
                return item.score > 0;
            })
            .sort(function (a, b) {
                return b.score - a.score;
            })
            .slice(0, 8)
            .map(function (item) {
                return item.card;
            });

        if (matches.length === 0) {
            searchDropdown.innerHTML = '<div class="project-search-empty">No matching projects yet.</div>';
            searchDropdown.hidden = false;
            activeSuggestionIndex = -1;
            return;
        }

        searchDropdown.innerHTML = matches.map(function (card) {
            const title = card.getAttribute('data-title') || 'Project';
            const status = card.getAttribute('data-status') || '';
            const link = card.getAttribute('data-link') || '#';
            const client = card.getAttribute('data-client') || 'N/A';
            const engineer = card.getAttribute('data-engineer') || 'Not assigned';
            const statusBadgeClass = 'search-status-badge status-' + escapeHtml(status);
            const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);

            return '<a class="project-search-result" href="' + link + '">' +
                '<div class="search-result-header">' +
                '<strong>' + highlightMatch(title, query) + '</strong>' +
                '<span class="' + statusBadgeClass + '">' + escapeHtml(statusLabel) + '</span>' +
                '</div>' +
                '<div class="search-result-meta">' +
                '<small>👤 ' + escapeHtml(client) + ' · 👨‍💼 ' + escapeHtml(engineer) + '</small>' +
                '</div>' +
                '</a>';
        }).join('');
        searchDropdown.hidden = false;
        activeSuggestionIndex = -1;
        syncSuggestionFocus();
    }

    function updateClearVisibility() {
        if (!searchInput || !searchClear) {
            return;
        }

        searchClear.classList.toggle('is-visible', searchInput.value.trim() !== '');
    }

    function refreshProjectsSection(url) {
        if (!section) {
            window.location.href = url;
            return;
        }

        if (searchInput) {
            window.__projectSearchFocusState = {
                value: searchInput.value,
                selectionStart: searchInput.selectionStart ?? searchInput.value.length,
                selectionEnd: searchInput.selectionEnd ?? searchInput.value.length,
            };
        }

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                const parser = new DOMParser();
                const documentFromResponse = parser.parseFromString(html, 'text/html');
                const nextSection = documentFromResponse.getElementById('projects-list-section');

                if (!nextSection) {
                    window.location.href = url;
                    return;
                }

                section.replaceWith(nextSection);
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', url);
                }
                initProjectSearchUI();
            })
            .catch(function () {
                window.location.href = url;
            });
    }

    function buildProjectsUrl() {
        const params = new URLSearchParams();
        const queryValue = searchInput ? searchInput.value.trim() : '';
        const statusValue = statusInput ? statusInput.value.trim() : '';

        if (queryValue !== '') {
            params.set('q', queryValue);
        }

        if (statusValue !== '') {
            params.set('status', statusValue);
        }

        const queryString = params.toString();
        return '/codesamplecaps/SUPERADMIN/sidebar/projects.php' + (queryString ? '?' + queryString : '');
    }

    function triggerSearchRefresh(immediate) {
        if (!searchInput) {
            return;
        }

        if (searchDebounceId) {
            window.clearTimeout(searchDebounceId);
        }

        const runSearch = function () {
            refreshProjectsSection(buildProjectsUrl());
        };

        if (immediate) {
            runSearch();
            return;
        }

        searchDebounceId = window.setTimeout(runSearch, 3000);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            updateClearVisibility();
            updateSearchDropdown();
            triggerSearchRefresh(false);
        });

        searchInput.addEventListener('focus', updateSearchDropdown);
        searchInput.addEventListener('keydown', function (event) {
            const links = getSuggestionLinks();

            if (searchDropdown.hidden || links.length === 0) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                activeSuggestionIndex = (activeSuggestionIndex + 1) % links.length;
                syncSuggestionFocus();
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                activeSuggestionIndex = activeSuggestionIndex <= 0 ? links.length - 1 : activeSuggestionIndex - 1;
                syncSuggestionFocus();
                return;
            }

            if (event.key === 'Enter' && activeSuggestionIndex >= 0 && links[activeSuggestionIndex]) {
                event.preventDefault();
                window.location.href = links[activeSuggestionIndex].href;
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                triggerSearchRefresh(true);
                return;
            }

            if (event.key === 'Escape') {
                searchDropdown.hidden = true;
                activeSuggestionIndex = -1;
            }
        });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', function (event) {
            event.preventDefault();
            triggerSearchRefresh(true);
        });
    }

    if (searchClear) {
        searchClear.addEventListener('click', function (event) {
            event.preventDefault();

            if (!searchInput) {
                return;
            }

            searchInput.value = '';
            updateClearVisibility();
            if (searchDropdown) {
                searchDropdown.hidden = true;
                searchDropdown.innerHTML = '';
            }

            if (searchDebounceId) {
                window.clearTimeout(searchDebounceId);
            }

            const resetUrl = section?.getAttribute('data-reset-url') || searchClear.getAttribute('href') || '/codesamplecaps/SUPERADMIN/sidebar/projects.php';
            refreshProjectsSection(resetUrl);
        });
    }

    if (!window.__projectSearchOutsideBound) {
        document.addEventListener('click', function (event) {
            const currentDropdown = document.getElementById('project-search-dropdown');
            const isInsideSearch = event.target.closest('.project-search-shell');

            if (!isInsideSearch && currentDropdown) {
                currentDropdown.hidden = true;
            }
        });

        window.__projectSearchOutsideBound = true;
    }

    updateClearVisibility();
    updateSearchDropdown();

    if (savedFocusState && searchInput) {
        const restoredValue = typeof savedFocusState.value === 'string' ? savedFocusState.value : searchInput.value;
        searchInput.value = restoredValue;
        searchInput.focus();
        const cursorStart = typeof savedFocusState.selectionStart === 'number' ? savedFocusState.selectionStart : restoredValue.length;
        const cursorEnd = typeof savedFocusState.selectionEnd === 'number' ? savedFocusState.selectionEnd : restoredValue.length;
        searchInput.setSelectionRange(cursorStart, cursorEnd);
        window.__projectSearchFocusState = null;
    }
}

document.addEventListener('DOMContentLoaded', initProjectSearchUI);
</script>
</body>
</html>
