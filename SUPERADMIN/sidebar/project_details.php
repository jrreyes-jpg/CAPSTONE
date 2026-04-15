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

function pm_format_money($value): string {
    return 'PHP ' . number_format((float)$value, 2);
}

function pm_format_date(?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return 'N/A';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable $exception) {
        return $value;
    }
}

function pm_build_budget_health(float $budgetAmount, float $totalCost): array {
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

function pm_determine_payment_status(float $totalCost, float $amountPaid): array {
    if ($amountPaid <= 0.0) {
        return ['status' => 'unpaid', 'label' => 'Unpaid'];
    }

    if ($totalCost > 0 && $amountPaid + 0.00001 < $totalCost) {
        return ['status' => 'partial', 'label' => 'Partial'];
    }

    if ($totalCost <= 0) {
        return ['status' => 'partial', 'label' => 'Partial'];
    }

    return ['status' => 'paid', 'label' => 'Paid'];
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

function pm_ensure_project_budget_profiles_table(mysqli $conn): void {
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

function pm_ensure_project_cost_entries_table(mysqli $conn): void {
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

function pm_ensure_project_payments_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS project_payments (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            project_id INT(11) NOT NULL,
            payment_date DATE NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            created_by INT(11) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_project_payments_project_date (project_id, payment_date, id),
            KEY idx_project_payments_created_by (created_by),
            CONSTRAINT fk_project_payments_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
            CONSTRAINT fk_project_payments_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function pm_ensure_project_address_column(mysqli $conn): void {
    if (!pm_table_has_column($conn, 'projects', 'project_address')) {
        $conn->query("ALTER TABLE projects ADD COLUMN project_address TEXT DEFAULT NULL AFTER client_id");
    }
}

function pm_ensure_project_email_column(mysqli $conn): void {
    if (!pm_table_has_column($conn, 'projects', 'project_email')) {
        $conn->query("ALTER TABLE projects ADD COLUMN project_email VARCHAR(190) DEFAULT NULL AFTER project_address");
    }
}

function pm_ensure_project_code_column(mysqli $conn): void {
    if (!pm_table_has_column($conn, 'projects', 'project_code')) {
        $conn->query("ALTER TABLE projects ADD COLUMN project_code VARCHAR(80) DEFAULT NULL AFTER project_email");
    }
}

function pm_ensure_project_po_number_column(mysqli $conn): void {
    if (!pm_table_has_column($conn, 'projects', 'po_number')) {
        $conn->query("ALTER TABLE projects ADD COLUMN po_number VARCHAR(80) DEFAULT NULL AFTER project_code");
    }
}

function pm_get_project_financial_snapshot(mysqli $conn, int $projectId): ?array {
    $stmt = $conn->prepare(
        'SELECT
            p.id,
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

function pm_get_project_payment_snapshot(mysqli $conn, int $projectId): ?array {
    $stmt = $conn->prepare(
        'SELECT
            p.id,
            COALESCE(cost_totals.total_cost, 0) AS total_cost,
            COALESCE(payment_totals.amount_paid, 0) AS amount_paid,
            COALESCE(payment_totals.payment_entry_count, 0) AS payment_entry_count
         FROM projects p
         LEFT JOIN (
             SELECT project_id, SUM(amount) AS total_cost
             FROM project_cost_entries
             GROUP BY project_id
         ) cost_totals ON cost_totals.project_id = p.id
         LEFT JOIN (
             SELECT project_id, SUM(amount) AS amount_paid, COUNT(*) AS payment_entry_count
             FROM project_payments
             GROUP BY project_id
         ) payment_totals ON payment_totals.project_id = p.id
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

function pm_fetch_project_cost_entries(mysqli $conn, int $projectId): array {
    $stmt = $conn->prepare(
        'SELECT
            pce.cost_date,
            pce.cost_category,
            pce.description,
            pce.amount,
            u.full_name AS created_by_name
         FROM project_cost_entries pce
         LEFT JOIN users u ON u.id = pce.created_by
         WHERE pce.project_id = ?
         ORDER BY pce.cost_date DESC, pce.id DESC'
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function pm_fetch_project_payment_entries(mysqli $conn, int $projectId): array {
    $stmt = $conn->prepare(
        'SELECT
            pp.payment_date,
            pp.amount,
            pp.notes,
            u.full_name AS created_by_name
         FROM project_payments pp
         LEFT JOIN users u ON u.id = pp.created_by
         WHERE pp.project_id = ?
         ORDER BY pp.payment_date DESC, pp.id DESC'
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$supportsDraftStatus = pm_enum_supports_value($conn, 'projects', 'status', 'draft');
$supportsCancelledStatus = pm_enum_supports_value($conn, 'projects', 'status', 'cancelled');
$supportsArchivedStatus = pm_enum_supports_value($conn, 'projects', 'status', 'archived');
pm_ensure_project_address_column($conn);
pm_ensure_project_email_column($conn);
pm_ensure_project_code_column($conn);
pm_ensure_project_po_number_column($conn);
$hasProjectAddressColumn = pm_table_has_column($conn, 'projects', 'project_address');
$hasProjectEmailColumn = pm_table_has_column($conn, 'projects', 'project_email');
$hasProjectCodeColumn = pm_table_has_column($conn, 'projects', 'project_code');
$hasPoNumberColumn = pm_table_has_column($conn, 'projects', 'po_number');
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
pm_ensure_project_budget_profiles_table($conn);
pm_ensure_project_cost_entries_table($conn);
pm_ensure_project_payments_table($conn);

$flash = $_SESSION['projects_flash'] ?? null;
unset($_SESSION['projects_flash']);

$clients = [];
$engineers = [];
$project = null;
$tasks = [];
$activeDeployments = [];
$deploymentHistory = [];
$availableInventory = [];
$projectFinancials = null;
$projectCostEntries = [];
$projectPaymentSnapshot = null;
$projectPaymentEntries = [];

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
    $projectEmailSelect = $hasProjectEmailColumn ? 'p.project_email,' : 'NULL AS project_email,';
    $projectCodeSelect = $hasProjectCodeColumn ? 'p.project_code,' : 'NULL AS project_code,';
    $poNumberSelect = $hasPoNumberColumn ? 'p.po_number,' : 'NULL AS po_number,';

    $projectStmt = $conn->prepare("
        SELECT
            p.id,
            p.project_name,
            p.description,
            {$projectAddressSelect}
            {$projectEmailSelect}
            {$projectCodeSelect}
            {$poNumberSelect}
            p.client_id,
            p.start_date,
            p.end_date,
            p.status,
            p.created_at,
            client.full_name AS client_name,
            client.email AS client_email,
            assignment_summary.engineer_ids_csv,
            assignment_summary.engineer_names,
            COALESCE(task_totals.total_tasks, 0) AS total_tasks,
            COALESCE(task_totals.completed_tasks, 0) AS completed_tasks
        FROM projects p
        LEFT JOIN users client ON client.id = p.client_id
        LEFT JOIN (
            SELECT
                pa.project_id,
                GROUP_CONCAT(DISTINCT pa.engineer_id ORDER BY pa.engineer_id SEPARATOR ',') AS engineer_ids_csv,
                GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') AS engineer_names
            FROM project_assignments pa
            INNER JOIN users u ON u.id = pa.engineer_id
            GROUP BY pa.project_id
        ) assignment_summary ON assignment_summary.project_id = p.id
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

    if ($project) {
        $projectFinancials = pm_get_project_financial_snapshot($conn, $projectId);
        $projectCostEntries = pm_fetch_project_cost_entries($conn, $projectId);
        $projectPaymentSnapshot = pm_get_project_payment_snapshot($conn, $projectId);
        $projectPaymentEntries = pm_fetch_project_payment_entries($conn, $projectId);
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
    <?php include __DIR__ . '/../super_admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-stack">
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
                $assignedEngineerIds = array_values(array_filter(array_map('intval', explode(',', (string)($project['engineer_ids_csv'] ?? '')))));
                $assignedEngineerNames = trim((string)($project['engineer_names'] ?? ''));
                $defaultTaskEngineerId = $assignedEngineerIds[0] ?? 0;
                $completionRate = (int)round(((int)($project['completed_tasks'] ?? 0) / max(1, (int)($project['total_tasks'] ?? 0))) * 100);
                $budgetAmount = (float)($projectFinancials['budget_amount'] ?? 0);
                $budgetNotes = trim((string)($projectFinancials['budget_notes'] ?? ''));
                $totalCost = (float)($projectFinancials['total_cost'] ?? 0);
                $remainingBudget = $budgetAmount - $totalCost;
                $costEntryCount = (int)($projectFinancials['cost_entry_count'] ?? 0);
                $budgetUsage = $budgetAmount > 0 ? min(100, round(($totalCost / $budgetAmount) * 100)) : 0;
                $budgetHealth = pm_build_budget_health($budgetAmount, $totalCost);
                $amountPaid = (float)($projectPaymentSnapshot['amount_paid'] ?? 0);
                $paymentEntryCount = (int)($projectPaymentSnapshot['payment_entry_count'] ?? 0);
                $remainingBalance = max(0, $totalCost - $amountPaid);
                $paymentUsage = $totalCost > 0 ? min(100, round(($amountPaid / $totalCost) * 100)) : 0;
                $paymentStatus = pm_determine_payment_status($totalCost, $amountPaid);
                if ($totalCost <= 0) {
                    $paymentStatusDetail = 'No project cost logged yet';
                } elseif ($paymentStatus['status'] === 'paid') {
                    $paymentStatusDetail = 'Client is fully paid';
                } elseif ($paymentStatus['status'] === 'partial') {
                    $paymentStatusDetail = 'Remaining: ' . pm_format_money($remainingBalance);
                } else {
                    $paymentStatusDetail = 'No payment recorded yet';
                }
                $projectCode = trim((string)($project['project_code'] ?? ''));
                $projectPoNumber = trim((string)($project['po_number'] ?? ''));
                $projectEmail = trim((string)($project['project_email'] ?? ''));
                ?>

                <section class="form-panel project-details-shell">
                    <div class="project-details-hero">
                        <div class="project-details-hero__main">
                            <div class="project-details-hero__headline">
                                <div class="project-details-hero__eyebrow-row">
                                    <span class="project-details-hero__eyebrow">Project Overview</span>
                                    <?php if ($projectCode !== ''): ?>
                                        <span class="project-details-hero__reference"><?php echo htmlspecialchars($projectCode); ?></span>
                                    <?php endif; ?>
                                </div>
                                <h1 class="project-details-hero__title"><?php echo htmlspecialchars($project['project_name']); ?></h1>
                                <div class="status-pill-wrap">
                                    <span class="status-pill status-<?php echo htmlspecialchars($project['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($project['status'])); ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($project['description'])): ?>
                                <div class="empty-state empty-state-solid project-details-hero__description"><?php echo nl2br(htmlspecialchars($project['description'])); ?></div>
                            <?php endif; ?>

                            <div class="project-details-progress-card" aria-label="Project progress">
                                <div class="project-details-progress-card__header">
                                    <div>
                                        <span class="project-details-progress-card__label">Project Progress</span>
                                        <strong><?php echo $completionRate; ?>% complete</strong>
                                    </div>
                                    <small><?php echo (int)$project['completed_tasks']; ?> of <?php echo (int)$project['total_tasks']; ?> tasks finished</small>
                                </div>
                                <div class="project-details-progress-card__track" aria-hidden="true">
                                    <span class="project-details-progress-card__fill" style="width: <?php echo $completionRate; ?>%;"></span>
                                </div>
                            </div>
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
                                <span>Assigned Engineer/s</span>
                                <strong><?php echo htmlspecialchars($assignedEngineerNames !== '' ? $assignedEngineerNames : 'Not assigned'); ?></strong>
                                <small>Current assigned team</small>
                            </div>
                            <div class="project-details-stat">
                                <span>Completed On</span>
                                <strong><?php echo htmlspecialchars(pm_format_date($project['end_date'] ?? null)); ?></strong>
                                <small><?php echo $isCompleted ? 'Saved when marked completed' : 'Pending until project completion'; ?></small>
                            </div>
                            <div class="project-details-stat project-details-stat--payment">
                                <span>Customer Payment</span>
                                <strong><?php echo htmlspecialchars($paymentStatus['label']); ?></strong>
                                <small><?php echo htmlspecialchars($paymentStatusDetail); ?></small>
                            </div>
                            <div class="project-details-stat project-details-stat--payment">
                                <span>Balance To Collect</span>
                                <strong><?php echo htmlspecialchars(pm_format_money($remainingBalance)); ?></strong>
                                <small><?php echo $paymentEntryCount > 0 ? htmlspecialchars($paymentEntryCount . ' payment record' . ($paymentEntryCount > 1 ? 's' : '')) : 'No payment entries yet'; ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="project-details-glance">
                        <div><strong>Project Code:</strong> <?php echo htmlspecialchars($projectCode !== '' ? $projectCode : 'Not set'); ?></div>
                        <div><strong>P.O Number:</strong> <?php echo htmlspecialchars($projectPoNumber !== '' ? $projectPoNumber : 'Not set'); ?></div>
                        <div><strong>P.O Date:</strong> <?php echo htmlspecialchars(pm_format_date($project['start_date'] ?? null)); ?></div>
                        <div><strong>Completed:</strong> <?php echo htmlspecialchars(pm_format_date($project['end_date'] ?? null)); ?></div>
                        <div><strong>Created:</strong> <?php echo htmlspecialchars($project['created_at'] ?? 'N/A'); ?></div>
                        <?php if ($projectEmail !== ''): ?>
                            <div><strong>Email Address:</strong> <?php echo htmlspecialchars($projectEmail); ?></div>
                        <?php endif; ?>
                        <?php if ($hasProjectAddressColumn): ?>
                            <div><strong>Project Site:</strong> <?php echo htmlspecialchars($project['project_address'] ?? 'Not set'); ?></div>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="project-details-sticky-bar">
                    <a href="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="btn-secondary btn-back-projects">Back to Projects</a>
                    <nav class="project-details-tabs" aria-label="Project detail sections">
                        <button type="button" class="project-details-tab is-active" data-project-tab="overview">Overview</button>
                        <button type="button" class="project-details-tab" data-project-tab="finance">Budget / Cost / Payment</button>
                        <button type="button" class="project-details-tab" data-project-tab="status">Status</button>
                        <button type="button" class="project-details-tab" data-project-tab="tasks">Tasks</button>
                        <button type="button" class="project-details-tab" data-project-tab="inventory">Inventory</button>
                        <button type="button" class="project-details-tab" data-project-tab="history">History</button>
                    </nav>
                </div>

                <div class="project-details-panels">
                <section class="form-panel project-details-panel is-active" data-project-panel="overview">
                    <div class="project-details-panel__header">
                        <h2 class="section-title-inline">Project Details</h2>
                        <?php if (!$isCompleted): ?>
                            <div class="project-edit-actions">
                                <button type="button" class="btn-secondary project-edit-toggle project-edit-toggle--edit" data-project-edit-toggle>Edit</button>
                                <button type="submit" form="project-details-edit-form" class="btn-primary project-edit-toggle project-edit-toggle--update hidden" data-project-update-button>Update</button>
                                <button type="button" class="btn-secondary project-edit-toggle project-edit-toggle--cancel hidden" data-project-cancel-button>Cancel</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" data-project-edit-form id="project-details-edit-form">
                        <input type="hidden" name="action" value="update_project_details">
                        <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">

                        <div class="project-details-form-sections">
                            <section class="project-form-section">
                                <div class="project-form-section__header">
                                    <span class="project-form-section__eyebrow">Project Info</span>
                                    <h3>Core project details</h3>
                                </div>
                                <div class="form-grid">
                                    <div class="input-group">
                                        <label for="project_name">Project Title</label>
                                        <input type="text" id="project_name" name="project_name" value="<?php echo htmlspecialchars($project['project_name']); ?>" required readonly data-project-editable>
                                    </div>

                                    <?php if ($hasProjectEmailColumn): ?>
                                        <div class="input-group">
                                            <label for="project_email">Email Address <span class="optional-indicator">(Optional)</span></label>
                                            <input type="email" id="project_email" name="project_email" value="<?php echo htmlspecialchars($project['project_email'] ?? ''); ?>" readonly data-project-editable>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($hasProjectCodeColumn): ?>
                                        <div class="input-group">
                                            <label for="project_code">Project Code <span class="required-indicator" aria-hidden="true">*</span></label>
                                            <input type="text" id="project_code" name="project_code" value="<?php echo htmlspecialchars($project['project_code'] ?? ''); ?>" required readonly data-project-editable>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($hasPoNumberColumn): ?>
                                        <div class="input-group">
                                            <label for="po_number">P.O Number <span class="required-indicator" aria-hidden="true">*</span></label>
                                            <input type="text" id="po_number" name="po_number" value="<?php echo htmlspecialchars($project['po_number'] ?? ''); ?>" required readonly data-project-editable>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($hasProjectAddressColumn): ?>
                                        <div class="input-group input-group-wide">
                                            <label for="project_address">Project Address / Site Location</label>
                                            <textarea id="project_address" name="project_address" rows="2" readonly data-project-editable><?php echo htmlspecialchars($project['project_address'] ?? ''); ?></textarea>
                                        </div>
                                    <?php endif; ?>

                                    <div class="input-group input-group-wide input-group-spaced">
                                        <label for="description">Comment <span class="optional-indicator">(Optional)</span></label>
                                        <textarea id="description" name="description" readonly data-project-editable><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </section>

                            <section class="project-form-section">
                                <div class="project-form-section__header">
                                    <span class="project-form-section__eyebrow">Timeline</span>
                                    <h3>Key project dates</h3>
                                </div>
                                <div class="form-grid">
                                    <div class="input-group">
                                        <label for="start_date">P.O Date</label>
                                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($project['start_date'] ?? ''); ?>" max="<?php echo htmlspecialchars($todayDate); ?>" readonly data-project-editable>
                                    </div>
                                    <div class="input-group">
                                        <label>Completed On</label>
                                        <div class="project-form-static-field"><?php echo htmlspecialchars(pm_format_date($project['end_date'] ?? null)); ?></div>
                                    </div>
                                    <div class="input-group">
                                        <label>Created On</label>
                                        <div class="project-form-static-field"><?php echo htmlspecialchars(pm_format_date($project['created_at'] ?? null)); ?></div>
                                    </div>
                                </div>
                            </section>

                            <section class="project-form-section">
                                <div class="project-form-section__header">
                                    <span class="project-form-section__eyebrow">Assignment</span>
                                    <h3>People responsible</h3>
                                </div>
                                <div class="form-grid">
                                    <div class="input-group">
                                        <label for="client_id">Client</label>
                                        <select id="client_id" name="client_id" required disabled data-project-editable>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?php echo (int)$client['id']; ?>" <?php echo (int)$project['client_id'] === (int)$client['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($client['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="input-group">
                                        <label for="engineer_ids">Assigned Engineer/s</label>
                                        <select id="engineer_ids" name="engineer_ids[]" required multiple size="<?php echo min(6, max(3, count($engineers))); ?>" disabled data-project-editable>
                                            <?php foreach ($engineers as $engineer): ?>
                                                <option value="<?php echo (int)$engineer['id']; ?>" <?php echo in_array((int)$engineer['id'], $assignedEngineerIds, true) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($engineer['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </section>
                        </div>

                    </form>
                </section>

                <section class="form-panel project-details-panel" data-project-panel="finance">
                    <div class="project-details-panel__header">
                        <h2 class="section-title-inline">Budget / Cost / Payment Management</h2>
                    </div>

                    <section class="cost-note-panel" aria-label="Project costing note">
                        <div class="cost-note-panel__title-row">
                            <div>
                                <span class="cost-note-panel__eyebrow">Project Costing Note</span>
                                <h4>Converted from the submitted layout</h4>
                            </div>
                            <span class="budget-health budget-health--<?php echo htmlspecialchars($budgetHealth['status']); ?>">
                                <?php echo htmlspecialchars($budgetHealth['label']); ?>
                            </span>
                        </div>

                        <div class="cost-note-grid">
                            <div class="cost-note-field">
                                <span>Client</span>
                                <strong><?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?></strong>
                            </div>
                            <div class="cost-note-field">
                                <span>PO Number</span>
                                <strong><?php echo htmlspecialchars($projectPoNumber !== '' ? $projectPoNumber : 'Not set'); ?></strong>
                            </div>
                            <div class="cost-note-field cost-note-field--wide">
                                <span>Project Title</span>
                                <strong><?php echo htmlspecialchars($project['project_name'] ?? 'Untitled project'); ?></strong>
                            </div>
                            <div class="cost-note-field">
                                <span>PO Date</span>
                                <strong><?php echo htmlspecialchars(pm_format_date($project['start_date'] ?? null)); ?></strong>
                            </div>
                            <div class="cost-note-field">
                                <span>Project Code</span>
                                <strong><?php echo htmlspecialchars($projectCode !== '' ? $projectCode : 'Not set'); ?></strong>
                            </div>
                            <div class="cost-note-field cost-note-field--wide">
                                <span>Address</span>
                                <strong><?php echo htmlspecialchars($project['project_address'] ?? 'Not set'); ?></strong>
                            </div>
                            <div class="cost-note-field cost-note-field--wide">
                                <span>Email Address</span>
                                <strong><?php echo htmlspecialchars($projectEmail !== '' ? $projectEmail : (trim((string)($project['client_email'] ?? '')) !== '' ? trim((string)($project['client_email'] ?? '')) : 'Not set')); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="budget-panel budget-panel--<?php echo htmlspecialchars($budgetHealth['status']); ?>">
                        <div class="budget-panel__header">
                            <div>
                                <h4>Budget Overview</h4>
                                <span class="budget-health budget-health--<?php echo htmlspecialchars($budgetHealth['status']); ?>">
                                    <?php echo htmlspecialchars($budgetHealth['label']); ?>
                                </span>
                            </div>
                            <strong><?php echo htmlspecialchars(pm_format_money($remainingBudget)); ?></strong>
                        </div>

                        <div class="budget-stats">
                            <div>
                                <span>Budget</span>
                                <strong><?php echo htmlspecialchars(pm_format_money($budgetAmount)); ?></strong>
                            </div>
                            <div>
                                <span>Actual</span>
                                <strong><?php echo htmlspecialchars(pm_format_money($totalCost)); ?></strong>
                            </div>
                            <div>
                                <span>Entries</span>
                                <strong><?php echo $costEntryCount; ?></strong>
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

                        <?php if (!empty($projectCostEntries)): ?>
                            <div class="budget-ledger">
                                <?php foreach ($projectCostEntries as $costEntry): ?>
                                    <div class="budget-ledger__item">
                                        <div>
                                            <strong><?php echo htmlspecialchars($costEntry['cost_category'] ?? 'Cost'); ?></strong>
                                            <span><?php echo htmlspecialchars($costEntry['cost_date'] ?? ''); ?><?php echo !empty($costEntry['created_by_name']) ? ' - ' . htmlspecialchars($costEntry['created_by_name']) : ''; ?></span>
                                            <?php if (!empty($costEntry['description'])): ?>
                                                <small><?php echo htmlspecialchars($costEntry['description']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <strong><?php echo htmlspecialchars(pm_format_money((float)($costEntry['amount'] ?? 0))); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No cost entries yet.</div>
                        <?php endif; ?>
                    </section>

                    <section class="budget-panel payment-panel payment-panel--<?php echo htmlspecialchars($paymentStatus['status']); ?>">
                        <div class="budget-panel__header">
                            <div>
                                <h4>Payment Summary</h4>
                                <span class="payment-status payment-status--<?php echo htmlspecialchars($paymentStatus['status']); ?>">
                                    <?php echo htmlspecialchars($paymentStatus['label']); ?>
                                </span>
                            </div>
                            <strong><?php echo htmlspecialchars(pm_format_money($remainingBalance)); ?></strong>
                        </div>

                        <div class="budget-stats">
                            <div>
                                <span>Total Cost</span>
                                <strong><?php echo htmlspecialchars(pm_format_money($totalCost)); ?></strong>
                            </div>
                            <div>
                                <span>Amount Paid</span>
                                <strong><?php echo htmlspecialchars(pm_format_money($amountPaid)); ?></strong>
                            </div>
                            <div>
                                <span>Remaining Balance</span>
                                <strong><?php echo htmlspecialchars(pm_format_money($remainingBalance)); ?></strong>
                            </div>
                            <div>
                                <span>Payments</span>
                                <strong><?php echo $paymentEntryCount; ?></strong>
                            </div>
                        </div>

                        <div class="budget-progress">
                            <div class="budget-progress__track">
                                <span class="budget-progress__fill payment-progress__fill payment-progress__fill--<?php echo htmlspecialchars($paymentStatus['status']); ?>" style="width: <?php echo $paymentUsage; ?>%;"></span>
                            </div>
                            <small><?php echo $totalCost > 0 ? $paymentUsage . '% paid' : 'Add a project cost first to start tracking payments'; ?></small>
                        </div>

                        <?php if (!empty($projectPaymentEntries)): ?>
                            <div class="budget-ledger">
                                <?php foreach ($projectPaymentEntries as $paymentEntry): ?>
                                    <div class="budget-ledger__item">
                                        <div>
                                            <strong>Payment Received</strong>
                                            <span><?php echo htmlspecialchars($paymentEntry['payment_date'] ?? ''); ?><?php echo !empty($paymentEntry['created_by_name']) ? ' - ' . htmlspecialchars($paymentEntry['created_by_name']) : ''; ?></span>
                                            <?php if (!empty($paymentEntry['notes'])): ?>
                                                <small><?php echo htmlspecialchars($paymentEntry['notes']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <strong><?php echo htmlspecialchars(pm_format_money((float)($paymentEntry['amount'] ?? 0))); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No payments recorded yet.</div>
                        <?php endif; ?>
                    </section>

                    <div class="project-finance-forms">
                        <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form project-finance-form" data-inline-edit-form>
                            <input type="hidden" name="action" value="save_project_budget">
                            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                            <div class="project-inline-form__header">
                                <h4 class="subheading">Update Budget</h4>
                                <div class="project-inline-form__actions">
                                    <button type="button" class="btn-secondary project-inline-action project-inline-action--edit" data-inline-edit>Edit</button>
                                    <button type="submit" class="btn-primary project-inline-action project-inline-action--update hidden" data-inline-update>Update</button>
                                    <button type="button" class="btn-secondary project-inline-action project-inline-action--cancel hidden" data-inline-cancel>Cancel</button>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="input-group">
                                    <label for="budget_amount">Budget Amount</label>
                                    <input type="number" id="budget_amount" name="budget_amount" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format($budgetAmount, 2, '.', '')); ?>" required data-inline-editable>
                                </div>
                                <div class="input-group">
                                    <label for="budget_notes">Notes</label>
                                    <input type="text" id="budget_notes" name="budget_notes" value="<?php echo htmlspecialchars($budgetNotes); ?>" placeholder="Approved budget notes" data-inline-editable>
                                </div>
                            </div>
                        </form>

                        <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form project-finance-form" data-inline-edit-form>
                            <input type="hidden" name="action" value="add_project_cost_entry">
                            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                            <div class="project-inline-form__header">
                                <h4 class="subheading">Log Cost</h4>
                                <div class="project-inline-form__actions">
                                    <button type="button" class="btn-secondary project-inline-action project-inline-action--edit" data-inline-edit>Edit</button>
                                    <button type="submit" class="btn-primary project-inline-action project-inline-action--update hidden" data-inline-update>Update</button>
                                    <button type="button" class="btn-secondary project-inline-action project-inline-action--cancel hidden" data-inline-cancel>Cancel</button>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="input-group">
                                    <label for="cost_date">Date</label>
                                    <input type="date" id="cost_date" name="cost_date" value="<?php echo htmlspecialchars($todayDate); ?>" required data-inline-editable>
                                </div>
                                <div class="input-group">
                                    <label for="cost_category">Category</label>
                                    <input type="text" id="cost_category" name="cost_category" placeholder="Labor, Materials, Permit" required data-inline-editable>
                                </div>
                                <div class="input-group">
                                    <label for="cost_amount">Amount</label>
                                    <input type="number" id="cost_amount" name="cost_amount" min="0.01" step="0.01" placeholder="0.00" required data-inline-editable>
                                </div>
                                <div class="input-group">
                                    <label for="cost_description">Description</label>
                                    <input type="text" id="cost_description" name="cost_description" placeholder="Supplier, PO, or work package" data-inline-editable>
                                </div>
                            </div>
                        </form>

                        <?php if ($totalCost <= 0): ?>
                            <div class="empty-state">Add a project cost first before recording payments.</div>
                        <?php elseif ($remainingBalance <= 0): ?>
                            <div class="empty-state">This project is already fully paid.</div>
                        <?php else: ?>
                            <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form project-finance-form" data-inline-edit-form>
                                <input type="hidden" name="action" value="add_project_payment">
                                <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                                <div class="project-inline-form__header">
                                    <h4 class="subheading">Add Payment</h4>
                                    <div class="project-inline-form__actions">
                                        <button type="button" class="btn-secondary project-inline-action project-inline-action--edit" data-inline-edit>Edit</button>
                                        <button type="submit" class="btn-primary project-inline-action project-inline-action--update hidden" data-inline-update>Update</button>
                                        <button type="button" class="btn-secondary project-inline-action project-inline-action--cancel hidden" data-inline-cancel>Cancel</button>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div class="input-group">
                                        <label for="payment_date">Payment Date</label>
                                        <input type="date" id="payment_date" name="payment_date" value="<?php echo htmlspecialchars($todayDate); ?>" required data-inline-editable>
                                    </div>
                                    <div class="input-group">
                                        <label for="payment_amount">Amount</label>
                                        <input type="number" id="payment_amount" name="payment_amount" min="0.01" step="0.01" max="<?php echo htmlspecialchars(number_format($remainingBalance, 2, '.', '')); ?>" placeholder="0.00" required data-inline-editable>
                                    </div>
                                    <div class="input-group input-group-wide">
                                        <label for="payment_notes">Notes</label>
                                        <input type="text" id="payment_notes" name="payment_notes" placeholder="Receipt, reference, or short note" data-inline-editable>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="form-panel project-details-panel" data-project-panel="status">
                    <div class="project-details-panel__header">
                        <h2 class="section-title-inline">Update Status</h2>
                    </div>
                    <?php if (!$isCompleted): ?>
                        <div class="lock-note">The completion date is now recorded automatically when the project is marked as Completed.</div>
                    <?php endif; ?>
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
                        <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form" data-inline-edit-form>
                            <input type="hidden" name="action" value="update_project_status">
                            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                            <div class="project-inline-form__header">
                                <h4 class="subheading">Primary Status</h4>
                                <div class="project-inline-form__actions">
                                    <button type="button" class="btn-secondary project-inline-action project-inline-action--edit" data-inline-edit>Edit</button>
                                    <button type="submit" class="btn-primary project-inline-action project-inline-action--update hidden" data-inline-update>Update</button>
                                    <button type="button" class="btn-secondary project-inline-action project-inline-action--cancel hidden" data-inline-cancel>Cancel</button>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="input-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" required data-inline-editable>
                                        <?php foreach ($statusOptions as $statusOption): ?>
                                            <?php if ($statusOption === 'pending' && !in_array(($project['status'] ?? ''), ['pending', 'draft'], true)) { continue; } ?>
                                            <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $project['status'] === $statusOption ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(ucfirst($statusOption)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
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
                    <div class="project-details-panel__header">
                        <h2 class="section-title-inline">Tasks</h2>
                    </div>

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
                        <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form" data-inline-edit-form>
                            <input type="hidden" name="action" value="add_task">
                            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">

                            <div class="project-inline-form__header">
                                <h4 class="subheading">Add Task</h4>
                                <div class="project-inline-form__actions">
                                    <button type="button" class="btn-secondary project-inline-action project-inline-action--edit" data-inline-edit>Edit</button>
                                    <button type="submit" class="btn-primary project-inline-action project-inline-action--update hidden" data-inline-update>Update</button>
                                    <button type="button" class="btn-secondary project-inline-action project-inline-action--cancel hidden" data-inline-cancel>Cancel</button>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="input-group">
                                    <label for="task_name">Task Name</label>
                                    <input type="text" id="task_name" name="task_name" required data-inline-editable>
                                </div>

                                <div class="input-group">
                                    <label for="assigned_to">Assign To</label>
                                    <select id="assigned_to" name="assigned_to" required data-inline-editable>
                                        <option value="">Select engineer</option>
                                        <?php foreach ($engineers as $engineer): ?>
                                            <option value="<?php echo (int)$engineer['id']; ?>" <?php echo $defaultTaskEngineerId === (int)$engineer['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($engineer['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="input-group">
                                    <label for="deadline">Deadline</label>
                                    <input type="date" id="deadline" name="deadline" min="<?php echo htmlspecialchars($todayDate); ?>" data-inline-editable>
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="task_description">Task Description</label>
                                <textarea id="task_description" name="task_description" placeholder="Task details" data-inline-editable></textarea>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="form-panel project-details-panel" data-project-panel="inventory">
                    <div class="project-details-panel__header">
                        <h2 class="section-title-inline">Deployed Inventory</h2>
                    </div>

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
                        <form method="POST" action="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="mini-form" data-inline-edit-form>
                            <input type="hidden" name="action" value="deploy_inventory_to_project">
                            <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($detailsPath); ?>">
                            <div class="project-inline-form__header">
                                <h4 class="subheading">Deploy Inventory</h4>
                                <div class="project-inline-form__actions">
                                    <button type="button" class="btn-secondary project-inline-action project-inline-action--edit" data-inline-edit>Edit</button>
                                    <button type="submit" class="btn-primary project-inline-action project-inline-action--update hidden" data-inline-update>Update</button>
                                    <button type="button" class="btn-secondary project-inline-action project-inline-action--cancel hidden" data-inline-cancel>Cancel</button>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="input-group">
                                    <label for="inventory_id">Inventory Item</label>
                                    <select id="inventory_id" name="inventory_id" required data-inline-editable>
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
                                    <input type="number" id="deployment_quantity" name="deployment_quantity" min="1" step="1" required data-inline-editable>
                                </div>

                                <div class="input-group">
                                    <label for="deployment_notes">Notes</label>
                                    <input type="text" id="deployment_notes" name="deployment_notes" placeholder="Optional deployment note" data-inline-editable>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="form-panel project-details-panel" data-project-panel="history">
                    <div class="project-details-panel__header">
                        <h2 class="section-title-inline">Deployment History</h2>
                        <span class="project-readonly-badge">Read Only</span>
                    </div>

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

    const editForm = document.querySelector('[data-project-edit-form]');
    const editToggle = document.querySelector('[data-project-edit-toggle]');
    const updateButton = document.querySelector('[data-project-update-button]');
    const cancelButton = document.querySelector('[data-project-cancel-button]');
    const editableFields = Array.from(document.querySelectorAll('[data-project-editable]'));
    const overviewPanel = document.querySelector('[data-project-panel="overview"]');

    if (editForm && editToggle && updateButton && cancelButton && editableFields.length > 0) {
        const fieldSnapshots = editableFields.map(function (field) {
            return {
                field: field,
                value: field.value,
            };
        });

        const setEditMode = function (isEditing) {
            editableFields.forEach(function (field) {
                if (field.tagName === 'SELECT') {
                    field.disabled = !isEditing;
                } else {
                    if (isEditing) {
                        field.removeAttribute('readonly');
                    } else {
                        field.setAttribute('readonly', 'readonly');
                    }
                }
            });

            editToggle.classList.toggle('hidden', isEditing);
            updateButton.classList.toggle('hidden', !isEditing);
            cancelButton.classList.toggle('hidden', !isEditing);

            if (overviewPanel) {
                overviewPanel.classList.toggle('is-editing', isEditing);
            }
        };

        setEditMode(false);

        editToggle.addEventListener('click', function () {
            setEditMode(true);
            const firstField = editableFields.find(function (field) {
                return !field.disabled;
            });

            if (firstField && typeof firstField.focus === 'function') {
                firstField.focus();
            }
        });

        cancelButton.addEventListener('click', function () {
            fieldSnapshots.forEach(function (snapshot) {
                snapshot.field.value = snapshot.value;
            });
            setEditMode(false);
        });
    }

    document.querySelectorAll('[data-inline-edit-form]').forEach(function (form) {
        const editButton = form.querySelector('[data-inline-edit]');
        const updateInlineButton = form.querySelector('[data-inline-update]');
        const cancelInlineButton = form.querySelector('[data-inline-cancel]');
        const fields = Array.from(form.querySelectorAll('[data-inline-editable]'));

        if (!editButton || !updateInlineButton || !cancelInlineButton || fields.length === 0) {
            return;
        }

        const fieldSnapshots = fields.map(function (field) {
            return {
                field: field,
                value: field.value,
            };
        });

        const setInlineEditMode = function (isEditing) {
            fields.forEach(function (field) {
                field.disabled = !isEditing;
            });

            form.classList.toggle('is-editing', isEditing);
            editButton.classList.toggle('hidden', isEditing);
            updateInlineButton.classList.toggle('hidden', !isEditing);
            cancelInlineButton.classList.toggle('hidden', !isEditing);
        };

        setInlineEditMode(false);

        editButton.addEventListener('click', function () {
            setInlineEditMode(true);
            const firstField = fields.find(function (field) {
                return !field.disabled;
            });

            if (firstField && typeof firstField.focus === 'function') {
                firstField.focus();
            }
        });

        cancelInlineButton.addEventListener('click', function () {
            fieldSnapshots.forEach(function (snapshot) {
                snapshot.field.value = snapshot.value;
            });
            setInlineEditMode(false);
        });
    });
});
</script>
</body>
</html>
