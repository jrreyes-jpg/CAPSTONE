<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/audit_log.php';

require_role('super_admin');

$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'dashboard';
$allowedRoles = ['engineer', 'foreman', 'client'];
$allowedStatuses = ['active', 'inactive'];
$action = '';
$old = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => ''
];

function normalizeRole(string $role): string {
    $role = strtolower(trim($role));
    return $role === 'foremen' ? 'foreman' : $role;
}

function isValidPhMobile(?string $phone): bool {
    if ($phone === null || $phone === '') {
        return true;
    }
    return (bool)preg_match('/^09\d{9}$/', $phone);
}

function isStrongPassword(string $password): bool {
    return strlen($password) >= 12
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function isValidCsrfToken(?string $token): bool {
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function getUserForStatusChange(mysqli $conn, int $userId): ?array {
    $stmt = $conn->prepare('SELECT id, full_name, email, phone, role, status FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function getCountByQuery(mysqli $conn, string $sql, int $userId): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return (int)($row['total'] ?? 0);
}

function getScalarInt(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    if (!$row) {
        return 0;
    }

    return (int)array_values($row)[0];
}

function hasTable(mysqli $conn, string $tableName): bool {
    $stmt = $conn->prepare(
        'SELECT 1
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();

    return (bool)($result && $result->fetch_assoc());
}

function formatRelativeDate(?string $dateTime): string {
    if (!$dateTime) {
        return 'Unknown time';
    }

    try {
        $date = new DateTimeImmutable($dateTime);
        $now = new DateTimeImmutable();
        $diff = $now->getTimestamp() - $date->getTimestamp();

        if ($diff < 60) {
            return 'Just now';
        }

        if ($diff < 3600) {
            return floor($diff / 60) . ' min ago';
        }

        if ($diff < 86400) {
            return floor($diff / 3600) . ' hr ago';
        }

        if ($diff < 604800) {
            return floor($diff / 86400) . ' day(s) ago';
        }

        return $date->format('M d, Y g:i A');
    } catch (Throwable $exception) {
        return $dateTime;
    }
}

function getDateRangeTrend(mysqli $conn, string $tableName, string $dateColumn, int $rangeDays, ?string $whereSql = null): array {
    $days = [];
    for ($offset = $rangeDays - 1; $offset >= 0; $offset--) {
        $date = new DateTimeImmutable("-{$offset} days");
        $key = $date->format('Y-m-d');
        $days[$key] = [
            'date' => $key,
            'label' => $date->format($rangeDays > 14 ? 'M d' : 'D'),
            'value' => 0,
        ];
    }

    $whereClause = $whereSql ? " AND {$whereSql}" : '';
    $result = $conn->query(
        "SELECT DATE({$dateColumn}) AS metric_date, COUNT(*) AS total
         FROM {$tableName}
         WHERE DATE({$dateColumn}) >= DATE_SUB(CURDATE(), INTERVAL " . ($rangeDays - 1) . " DAY)
         {$whereClause}
         GROUP BY DATE({$dateColumn})"
    );

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $metricDate = (string)($row['metric_date'] ?? '');
            if (isset($days[$metricDate])) {
                $days[$metricDate]['value'] = (int)($row['total'] ?? 0);
            }
        }
    }

    return array_values($days);
}

function getTrendPeak(array $trend): int {
    $values = array_map(static fn(array $item): int => (int)($item['value'] ?? 0), $trend);
    $peak = max($values ?: [0]);

    return $peak > 0 ? $peak : 1;
}

function buildAuditSummaryClean(array $entry): array {
    $summary = buildAuditSummary($entry);
    $summary['details'] = str_replace(
        ['â€¢', '•'],
        '|',
        (string)($summary['details'] ?? '')
    );

    return $summary;
}

function decodeAuditPayload(?string $payload): array {
    if (!$payload) {
        return [];
    }

    $decoded = json_decode($payload, true);

    return is_array($decoded) ? $decoded : [];
}

function formatAuditActionLabel(string $action): string {
    return ucwords(str_replace('_', ' ', $action));
}

function buildAuditSummary(array $entry): array {
    $action = (string)($entry['action'] ?? 'activity');
    $entityType = (string)($entry['entity_type'] ?? 'record');
    $actorName = (string)($entry['actor_name'] ?? 'System');
    $oldValues = decodeAuditPayload($entry['old_values'] ?? null);
    $newValues = decodeAuditPayload($entry['new_values'] ?? null);
    $title = formatAuditActionLabel($action);
    $details = 'Actor: ' . $actorName;

    if ($action === 'create_user') {
        $title = 'User created';
        $details = ($newValues['full_name'] ?? 'Unknown user') . ' • ' . ucwords(str_replace('_', ' ', (string)($newValues['role'] ?? 'user')));
    } elseif ($action === 'update_user_status') {
        $title = 'User status updated';
        $details = 'Status: ' . ucfirst((string)($oldValues['status'] ?? 'unknown')) . ' -> ' . ucfirst((string)($newValues['status'] ?? 'unknown'));
    } elseif ($action === 'update_user_profile') {
        $title = 'User profile updated';
        $details = ($newValues['full_name'] ?? 'User record updated') . ' • by ' . $actorName;
    } elseif ($action === 'create_project') {
        $title = 'Project created';
        $details = (string)($newValues['project_name'] ?? 'New project') . ' • ' . ucfirst((string)($newValues['status'] ?? 'pending'));
    } elseif ($action === 'update_project_status') {
        $title = 'Project status changed';
        $details = (string)($newValues['project_name'] ?? 'Project') . ' • ' . ucfirst((string)($oldValues['status'] ?? '')) . ' -> ' . ucfirst((string)($newValues['status'] ?? ''));
    } elseif ($action === 'update_project_details') {
        $title = 'Project details updated';
        $details = (string)($newValues['project_name'] ?? 'Project') . ' • by ' . $actorName;
    } elseif ($action === 'add_task') {
        $title = 'Task added';
        $details = (string)($newValues['task_name'] ?? 'Task') . ' • ' . (string)($newValues['project_name'] ?? 'Project');
    } elseif ($action === 'deploy_inventory_to_project') {
        $title = 'Inventory deployed';
        $details = (string)($newValues['asset_name'] ?? 'Inventory item') . ' • Qty ' . (int)($newValues['quantity'] ?? 0);
    } elseif ($action === 'return_project_inventory') {
        $title = 'Inventory returned';
        $details = (string)($newValues['asset_name'] ?? 'Inventory item') . ' • Qty ' . (int)($newValues['quantity'] ?? 0);
    } elseif ($action === 'create_inventory_item') {
        $title = 'Inventory record created';
        $details = (string)($newValues['asset_name'] ?? 'Inventory item') . ' • Qty ' . (int)($newValues['quantity'] ?? 0);
    } elseif ($action === 'update_inventory_item') {
        $title = 'Inventory updated';
        $details = (string)($newValues['asset_name'] ?? 'Inventory item') . ' • Qty ' . (int)($newValues['quantity'] ?? 0);
    } elseif ($action === 'create_asset') {
        $title = 'Asset created';
        $details = (string)($newValues['asset_name'] ?? 'Asset') . ' • ' . (string)($newValues['asset_type'] ?? 'Unspecified');
    } elseif ($action === 'delete_asset') {
        $title = 'Asset deleted';
        $details = (string)($oldValues['asset_name'] ?? 'Asset') . ' • by ' . $actorName;
    } elseif ($action === 'return_asset') {
        $title = 'Asset returned';
        $details = (string)($newValues['asset_name'] ?? 'Asset') . ' • status available';
    } else {
        $title = formatAuditActionLabel($action);
        $details = ucfirst($entityType) . ' • by ' . $actorName;
    }

    return [
        'title' => $title,
        'details' => $details,
        'badge' => $entityType !== '' ? $entityType : 'audit',
    ];
}

function getDeactivationBlockers(mysqli $conn, int $userId, string $role): array {
    $blockers = [];

    if ($role === 'engineer') {
        $activeProjects = getCountByQuery(
            $conn,
            "SELECT COUNT(*) AS total
             FROM project_assignments pa
             INNER JOIN projects p ON p.id = pa.project_id
             WHERE pa.engineer_id = ?
             AND p.status IN ('pending', 'ongoing', 'on-hold')",
            $userId
        );

        $openTasks = getCountByQuery(
            $conn,
            "SELECT COUNT(*) AS total
             FROM tasks
             WHERE assigned_to = ?
             AND status IN ('pending', 'ongoing', 'delayed')",
            $userId
        );

        if ($activeProjects > 0) {
            $blockers[] = $activeProjects . ' active project(s)';
        }

        if ($openTasks > 0) {
            $blockers[] = $openTasks . ' open task(s)';
        }
    }

    if ($role === 'client') {
        $activeProjects = getCountByQuery(
            $conn,
            "SELECT COUNT(*) AS total
             FROM projects
             WHERE client_id = ?
             AND status IN ('pending', 'ongoing', 'on-hold')",
            $userId
        );

        if ($activeProjects > 0) {
            $blockers[] = $activeProjects . ' active project(s)';
        }
    }

    return $blockers;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
        $activeTab = $action === 'create_account' ? 'create' : 'users';
    } elseif ($action === 'create_account') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = normalizeRole($_POST['role'] ?? '');
        $old['full_name'] = $fullName;
$old['email'] = $email;
$old['phone'] = $phone;
$old['role'] = $role;

        if ($fullName === '' || $email === '' || $password === '' || $role === '') {
            $error = 'Full name, email, password, and role are required.';
            $activeTab = 'create';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
            $activeTab = 'create';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $error = 'Invalid role selected.';
            $activeTab = 'create';
      } elseif (!preg_match('/^09\d{9}$/', $phone)) {
    $error = 'Phone number must be a valid PH mobile number (09xxxxxxxxx).';
    $activeTab = 'create';
} elseif (!isStrongPassword($password)) {
            $error = 'Temporary password must be STRONG: 12+ chars with uppercase, lowercase, number, special char.';
            $activeTab = 'create';
        } else {
            $dupStmt = $conn->prepare('SELECT id, full_name, email, phone FROM users WHERE full_name = ? OR email = ? OR phone = ? LIMIT 1');
            $dupStmt->bind_param('sss', $fullName, $email, $phone);
            $dupStmt->execute();
            $dup = $dupStmt->get_result();

            if ($dup->num_rows > 0) {
                $error = 'Duplicate detected. Full name, email, and phone must all be unique.';
                $activeTab = 'create';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $createStmt = $conn->prepare('INSERT INTO users (full_name, email, password, role, phone, status, created_by) VALUES (?, ?, ?, ?, ?, "active", ?)');
                $createStmt->bind_param('sssssi', $fullName, $email, $passwordHash, $role, $phone, $_SESSION['user_id']);

                if ($createStmt->execute()) {
                    $createdUserId = (int)$createStmt->insert_id;
                    audit_log_event(
                        $conn,
                        (int)($_SESSION['user_id'] ?? 0),
                        'create_user',
                        'user',
                        $createdUserId,
                        null,
                        [
                            'full_name' => $fullName,
                            'email' => $email,
                            'phone' => $phone,
                            'role' => $role,
                            'status' => 'active',
                        ]
                    );
                    $message = ucfirst($role) . ' account created successfully.';
                    $activeTab = 'users';
                } else {
                    $error = 'Failed to create account. Please check DB columns (role/phone/id) and try again.';
                    $activeTab = 'create';
                }
            }
        }
    }

    if ($action === 'update_status' && $error === '') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $user = $userId > 0 ? getUserForStatusChange($conn, $userId) : null;

        if ($userId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            $error = 'Invalid status update request.';
        } elseif (!$user) {
            $error = 'User not found.';
        } elseif (normalizeRole((string)$user['role']) === 'super_admin') {
            $error = 'Super admin accounts cannot be changed from this screen.';
        } elseif ($newStatus === 'inactive' && $userId === (int)$_SESSION['user_id']) {
            $error = 'You cannot deactivate your own super admin account.';
        } elseif ($newStatus === 'inactive') {
            $blockers = getDeactivationBlockers($conn, $userId, normalizeRole((string)$user['role']));

            if (!empty($blockers)) {
                $error = 'Cannot deactivate ' . $user['full_name'] . ' yet. Reassign ' . implode(' and ', $blockers) . ' first.';
            } else {
                $stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
                $stmt->bind_param('si', $newStatus, $userId);
                if ($stmt->execute()) {
                    audit_log_event(
                        $conn,
                        (int)($_SESSION['user_id'] ?? 0),
                        'update_user_status',
                        'user',
                        $userId,
                        ['status' => $user['status'] ?? null],
                        ['status' => $newStatus]
                    );
                    $message = 'User deactivated successfully.';
                } else {
                    $error = 'Failed to update user status.';
                }
            }
        } else {
            $stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $newStatus, $userId);
            if ($stmt->execute()) {
                audit_log_event(
                    $conn,
                    (int)($_SESSION['user_id'] ?? 0),
                    'update_user_status',
                    'user',
                    $userId,
                    ['status' => $user['status'] ?? null],
                    ['status' => $newStatus]
                );
                $message = 'User reactivated successfully.';
            } else {
                $error = 'Failed to update user status.';
            }
        }
        $activeTab = 'users';
    }

    if ($action === 'edit_user' && $error === '') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['edit_full_name'] ?? '');
        $email = trim($_POST['edit_email'] ?? '');
        $phone = trim($_POST['edit_phone'] ?? '');
        $user = $userId > 0 ? getUserForStatusChange($conn, $userId) : null;

        if ($userId <= 0 || $fullName === '' || $email === '') {
            $error = 'Invalid edit request. Full name and email are required.';
        } elseif (!$user) {
            $error = 'User not found.';
        } elseif (normalizeRole((string)$user['role']) === 'super_admin') {
            $error = 'Super admin accounts cannot be edited from this screen.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (!ctype_digit($phone) && $phone !== '') {
            $error = 'Phone number must contain numbers only.';
        } elseif (!isValidPhMobile($phone)) {
            $error = 'Phone number must be a valid PH mobile number (09xxxxxxxxx).';
        } else {
            $dupStmt = $conn->prepare('SELECT id FROM users WHERE (full_name = ? OR email = ? OR phone = ?) AND id != ? LIMIT 1');
            $dupStmt->bind_param('sssi', $fullName, $email, $phone, $userId);
            $dupStmt->execute();
            $dup = $dupStmt->get_result();

            if ($dup->num_rows > 0) {
                $error = 'Duplicate detected. Full name, email, and phone must all be unique.';
            } else {
                $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
                $stmt->bind_param('sssi', $fullName, $email, $phone, $userId);
                if ($stmt->execute()) {
                    audit_log_event(
                        $conn,
                        (int)($_SESSION['user_id'] ?? 0),
                        'update_user_profile',
                        'user',
                        $userId,
                        [
                            'full_name' => $user['full_name'] ?? null,
                            'email' => $user['email'] ?? null,
                            'phone' => $user['phone'] ?? null,
                        ],
                        [
                            'full_name' => $fullName,
                            'email' => $email,
                            'phone' => $phone,
                        ]
                    );
                    $message = 'User profile updated successfully.';
                } else {
                    $error = 'Failed to update user profile.';
                }
            }
        }
        $activeTab = 'users';
    }

}


function fetchUsersByRoles(mysqli $conn, array $roles, string $statusFilter = ''): array {
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $types = str_repeat('s', count($roles));
    $sql = "SELECT id, full_name, email, phone, status, role FROM users WHERE role IN ($placeholders)";

    if ($statusFilter !== '') {
        $sql .= ' AND status = ?';
        $types .= 's';
        $roles[] = $statusFilter;
    }

    $sql .= ' ORDER BY id DESC';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$roles);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$userStatusFilter = trim((string)($_GET['status'] ?? ''));
if (!in_array($userStatusFilter, $allowedStatuses, true)) {
    $userStatusFilter = '';
}

$engineers = fetchUsersByRoles($conn, ['engineer'], $activeTab === 'users' ? $userStatusFilter : '');
$foremen = fetchUsersByRoles($conn, ['foreman', 'foremen'], $activeTab === 'users' ? $userStatusFilter : '');
$clients = fetchUsersByRoles($conn, ['client'], $activeTab === 'users' ? $userStatusFilter : '');
$totalUsers = count($engineers) + count($foremen) + count($clients);
$csrfToken = getCsrfToken();

$projectMetrics = $conn->query(
    "SELECT
        COUNT(*) AS total_projects,
        SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_projects,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_projects,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_projects,
        SUM(CASE WHEN status = 'on-hold' THEN 1 ELSE 0 END) AS on_hold_projects
     FROM projects"
);
$projectMetricRow = $projectMetrics ? $projectMetrics->fetch_assoc() : [];

$taskMetrics = $conn->query(
    "SELECT
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN status IN ('pending', 'ongoing', 'delayed') THEN 1 ELSE 0 END) AS open_tasks,
        SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) AS delayed_tasks
     FROM tasks"
);
$taskMetricRow = $taskMetrics ? $taskMetrics->fetch_assoc() : [];

$inventoryMetrics = $conn->query(
    "SELECT
        COUNT(*) AS inventory_items,
        COALESCE(SUM(quantity), 0) AS total_units,
        SUM(CASE WHEN status = 'low-stock' THEN 1 ELSE 0 END) AS low_stock_items,
        SUM(CASE WHEN status = 'out-of-stock' THEN 1 ELSE 0 END) AS out_of_stock_items
     FROM inventory"
);
$inventoryMetricRow = $inventoryMetrics ? $inventoryMetrics->fetch_assoc() : [];

$assetMetrics = $conn->query(
    "SELECT
        COUNT(*) AS total_assets,
        SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) AS assets_this_month,
        SUM(CASE WHEN serial_number IS NULL OR TRIM(serial_number) = '' THEN 1 ELSE 0 END) AS assets_missing_serial
     FROM assets"
);
$assetMetricRow = $assetMetrics ? $assetMetrics->fetch_assoc() : [];

$totalProjects = (int)($projectMetricRow['total_projects'] ?? 0);
$ongoingProjects = (int)($projectMetricRow['ongoing_projects'] ?? 0);
$completedProjects = (int)($projectMetricRow['completed_projects'] ?? 0);
$pendingProjects = (int)($projectMetricRow['pending_projects'] ?? 0);
$onHoldProjects = (int)($projectMetricRow['on_hold_projects'] ?? 0);
$totalTasks = (int)($taskMetricRow['total_tasks'] ?? 0);
$openTasks = (int)($taskMetricRow['open_tasks'] ?? 0);
$delayedTasks = (int)($taskMetricRow['delayed_tasks'] ?? 0);
$inventoryItems = (int)($inventoryMetricRow['inventory_items'] ?? 0);
$totalUnits = (int)($inventoryMetricRow['total_units'] ?? 0);
$lowStockItems = (int)($inventoryMetricRow['low_stock_items'] ?? 0);
$outOfStockItems = (int)($inventoryMetricRow['out_of_stock_items'] ?? 0);
$totalAssets = (int)($assetMetricRow['total_assets'] ?? 0);
$assetsThisMonth = (int)($assetMetricRow['assets_this_month'] ?? 0);
$rangeDays = (int)($_GET['range'] ?? 7);
if (!in_array($rangeDays, [7, 14, 30, 90], true)) {
    $rangeDays = 7;
}
$scansToday = getScalarInt($conn, "SELECT COUNT(*) FROM asset_scan_history WHERE DATE(scan_time) = CURDATE()");
$scanTrend = getDateRangeTrend($conn, 'asset_scan_history', 'scan_time', $rangeDays);
$projectTrend = getDateRangeTrend($conn, 'projects', 'created_at', $rangeDays);
$userTrend = getDateRangeTrend($conn, 'users', 'created_at', $rangeDays, "role IN ('engineer', 'foreman', 'foremen', 'client')");
$scanTrendPeak = getTrendPeak($scanTrend);
$projectTrendPeak = getTrendPeak($projectTrend);
$userTrendPeak = getTrendPeak($userTrend);
$activeDeployments = hasTable($conn, 'project_inventory_deployments')
    ? getScalarInt(
        $conn,
        "SELECT COUNT(*)
         FROM (
             SELECT pid.id
             FROM project_inventory_deployments pid
             LEFT JOIN (
                 SELECT deployment_id, SUM(quantity) AS returned_quantity
                 FROM project_inventory_return_logs
                 GROUP BY deployment_id
             ) returns ON returns.deployment_id = pid.id
             WHERE (pid.quantity - COALESCE(returns.returned_quantity, 0)) > 0
         ) active_deployments"
    )
    : 0;

$lowStockAlerts = [];
$lowStockResult = $conn->query(
    "SELECT a.asset_name, i.quantity, i.min_stock, i.status
     FROM inventory i
     INNER JOIN assets a ON a.id = i.asset_id
     WHERE i.status IN ('low-stock', 'out-of-stock')
     ORDER BY FIELD(i.status, 'out-of-stock', 'low-stock'), i.quantity ASC, a.asset_name ASC
     LIMIT 5"
);
if ($lowStockResult) {
    $lowStockAlerts = $lowStockResult->fetch_all(MYSQLI_ASSOC);
}

$projectRiskAlerts = [];
$projectRiskResult = $conn->query(
    "SELECT
        p.project_name,
        p.status,
        p.end_date,
        COALESCE(task_totals.open_tasks, 0) AS open_tasks,
        COALESCE(task_totals.delayed_tasks, 0) AS delayed_tasks
     FROM projects p
     LEFT JOIN (
         SELECT
             project_id,
             SUM(CASE WHEN status IN ('pending', 'ongoing', 'delayed') THEN 1 ELSE 0 END) AS open_tasks,
             SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) AS delayed_tasks
         FROM tasks
         GROUP BY project_id
     ) task_totals ON task_totals.project_id = p.id
     WHERE p.status IN ('pending', 'ongoing', 'on-hold')
     AND (
         COALESCE(task_totals.delayed_tasks, 0) > 0
         OR (p.end_date IS NOT NULL AND p.end_date < CURDATE())
     )
     ORDER BY
        COALESCE(task_totals.delayed_tasks, 0) DESC,
        p.end_date ASC,
        p.updated_at DESC
     LIMIT 5"
);
if ($projectRiskResult) {
    $projectRiskAlerts = $projectRiskResult->fetch_all(MYSQLI_ASSOC);
}

$inactiveAssignmentAlerts = [];
$inactiveAssignmentResult = $conn->query(
    "SELECT
        u.full_name,
        u.role,
        COUNT(DISTINCT p.id) AS active_projects
     FROM users u
     LEFT JOIN project_assignments pa ON pa.engineer_id = u.id
     LEFT JOIN projects p ON p.id = pa.project_id AND p.status IN ('pending', 'ongoing', 'on-hold')
     WHERE u.status = 'inactive'
     AND u.role IN ('engineer', 'foreman', 'client')
     GROUP BY u.id, u.full_name, u.role
     HAVING active_projects > 0
     ORDER BY active_projects DESC, u.full_name ASC
     LIMIT 5"
);
if ($inactiveAssignmentResult) {
    $inactiveAssignmentAlerts = $inactiveAssignmentResult->fetch_all(MYSQLI_ASSOC);
}

$auditLogFeed = [];
if (audit_log_table_exists($conn)) {
    $recentActivityResult = $conn->query(
        "SELECT
            l.created_at AS activity_time,
            l.action,
            l.entity_type,
            l.entity_id,
            actor.full_name AS actor_name,
            l.old_values,
            l.new_values
         FROM audit_logs l
         LEFT JOIN users actor ON actor.id = l.user_id
         WHERE DATE(l.created_at) >= DATE_SUB(CURDATE(), INTERVAL " . ($rangeDays - 1) . " DAY)
         ORDER BY l.created_at DESC
        "
    );

    if ($recentActivityResult) {
        $auditLogFeed = $recentActivityResult->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Edge Automation</title>
    <link rel="stylesheet" href="../css/admin-dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../sidebar_super_admin.php'; ?>

    <main class="main-content">
        <div class="header">
            <h1>Super Admin Dashboard</h1>
            <div class="user-info"><span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span></div>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div id="dashboard" class="tab-content <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'dashboard' ? 'display: block;' : 'display: none;'; ?>">
            <section class="dashboard-panel problems-panel">
                    <div class="panel-heading">
                        <div>
                            <h2 class="dashboard-section-title">Needs Attention</h2>
                            <p class="panel-copy">Check these first.</p>
                        </div>
                    </div>

                    <div class="alert-group">
                        <a href="/codesamplecaps/SUPERADMIN/sidebar/projects.php?status=ongoing" class="alert-card alert-card-danger alert-card-link">
                            <div class="alert-card-head">
                                <h3>Projects</h3>
                                <span><?php echo count($projectRiskAlerts); ?></span>
                            </div>
                            <?php if (empty($projectRiskAlerts)): ?>
                                <p class="alert-empty">No project problems right now.</p>
                            <?php else: ?>
                                <ul class="alert-list">
                                    <?php foreach ($projectRiskAlerts as $projectAlert): ?>
                                        <li>
                                            <strong><?php echo htmlspecialchars($projectAlert['project_name']); ?></strong>
                                            <span>
                                                <?php
                                                $parts = [];
                                                if ((int)$projectAlert['delayed_tasks'] > 0) {
                                                    $parts[] = (int)$projectAlert['delayed_tasks'] . ' delayed task(s)';
                                                }
                                                if (!empty($projectAlert['end_date']) && $projectAlert['end_date'] < date('Y-m-d')) {
                                                    $parts[] = 'late end date';
                                                }
                                                echo htmlspecialchars(implode(' | ', $parts) ?: 'Needs checking');
                                                ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </a>

                        <a href="/codesamplecaps/SUPERADMIN/sidebar/inventory.php?status=attention" class="alert-card alert-card-warning alert-card-link">
                            <div class="alert-card-head">
                                <h3>Stock</h3>
                                <span><?php echo count($lowStockAlerts); ?></span>
                            </div>
                            <?php if (empty($lowStockAlerts)): ?>
                                <p class="alert-empty">No stock problems.</p>
                            <?php else: ?>
                                <ul class="alert-list">
                                    <?php foreach ($lowStockAlerts as $stockAlert): ?>
                                        <li>
                                            <strong><?php echo htmlspecialchars($stockAlert['asset_name']); ?></strong>
                                            <span><?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $stockAlert['status']))); ?> | Qty <?php echo (int)$stockAlert['quantity']; ?><?php echo $stockAlert['min_stock'] !== null ? ' | Min ' . (int)$stockAlert['min_stock'] : ''; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </a>

                        <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=users&amp;status=inactive" class="alert-card alert-card-info alert-card-link">
                            <div class="alert-card-head">
                                <h3>Users</h3>
                                <span><?php echo count($inactiveAssignmentAlerts); ?></span>
                            </div>
                            <?php if (empty($inactiveAssignmentAlerts)): ?>
                                <p class="alert-empty">No user problems found.</p>
                            <?php else: ?>
                                <ul class="alert-list">
                                    <?php foreach ($inactiveAssignmentAlerts as $userAlert): ?>
                                        <li>
                                            <strong><?php echo htmlspecialchars($userAlert['full_name']); ?></strong>
                                            <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$userAlert['role']))); ?> | <?php echo (int)$userAlert['active_projects']; ?> active project(s)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </a>

                    </div>
            </section>

            <section class="dashboard-grid">
                <section class="dashboard-panel summary-panel">
                    <div class="panel-heading">
                        <div>
                            <h2 class="dashboard-section-title">Summary</h2>
                            <p class="panel-copy">Quick system check.</p>
                        </div>
                    </div>
                    <div class="metric-strip metric-strip-compact">
                        <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=users" class="metric-tile metric-tile-link metric-tile-people">
                            <span>People</span>
                            <strong><?php echo $totalUsers; ?></strong>
                            <small>Open manage users</small>
                        </a>
                        <a href="/codesamplecaps/SUPERADMIN/sidebar/projects.php?status=active" class="metric-tile metric-tile-link metric-tile-projects">
                            <span>Projects</span>
                            <strong><?php echo $totalProjects; ?></strong>
                            <small><?php echo $ongoingProjects; ?> active now</small>
                        </a>
                        <a href="/codesamplecaps/SUPERADMIN/sidebar/projects.php?status=active" class="metric-tile metric-tile-link metric-tile-tasks">
                            <span>Tasks</span>
                            <strong><?php echo $openTasks; ?></strong>
                            <small>Review active project work</small>
                        </a>
                        <a href="/codesamplecaps/SUPERADMIN/sidebar/scan_history.php" class="metric-tile metric-tile-link metric-tile-scans">
                            <span>Scans Today</span>
                            <strong><?php echo $scansToday; ?></strong>
                            <small>Open scan history</small>
                        </a>
                    </div>
                </section>

                <aside class="dashboard-panel activity-panel">
                    <div class="panel-heading">
                        <div>
                            <h2 class="dashboard-section-title">Recent Activity</h2>
                            <p class="panel-copy">Latest <?php echo count($auditLogFeed); ?> admin actions in the selected range.</p>
                        </div>
                        <div class="dashboard-actions activity-actions">
                            <button type="button" class="action-chip action-chip-button" id="toggleActivityHistory" aria-expanded="false" <?php echo count($auditLogFeed) <= 3 ? 'hidden' : ''; ?>>
                                Show All History
                            </button>
                            <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=dashboard&amp;range=7" class="action-chip<?php echo $rangeDays === 7 ? ' active-chip' : ''; ?>">7 Days</a>
                            <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=dashboard&amp;range=14" class="action-chip<?php echo $rangeDays === 14 ? ' active-chip' : ''; ?>">14 Days</a>
                            <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=dashboard&amp;range=30" class="action-chip<?php echo $rangeDays === 30 ? ' active-chip' : ''; ?>">30 Days</a>
                            <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=dashboard&amp;range=90" class="action-chip<?php echo $rangeDays === 90 ? ' active-chip' : ''; ?>">90 Days</a>
                        </div>
                    </div>

                    <?php if (!empty($auditLogFeed)): ?>
                        <div class="activity-search">
                            <input type="search" id="activitySearchInput" class="activity-search__input" placeholder="Search activity, actor, action, date..." autocomplete="off" aria-label="Search recent activity">
                            <span class="activity-search__hint">Press ESC to clear</span>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($auditLogFeed)): ?>
                        <div class="empty-state-solid">No audit logs yet. New admin actions will appear here.</div>
                    <?php else: ?>
                        <div class="activity-feed activity-feed-compact" id="activityFeed">
                            <?php foreach ($auditLogFeed as $index => $activity): ?>
                                <?php $auditSummary = buildAuditSummaryClean($activity); ?>
                                <?php
                                try {
                                    $activityDate = new DateTimeImmutable((string)($activity['activity_time'] ?? 'now'));
                                    $activityFullDate = $activityDate->format('l, M d, Y g:i A');
                                } catch (Throwable $exception) {
                                    $activityFullDate = (string)($activity['activity_time'] ?? '');
                                }
                                $activitySearch = strtolower(trim(implode(' ', [
                                    (string)($auditSummary['title'] ?? ''),
                                    (string)($auditSummary['details'] ?? ''),
                                    (string)($activity['actor_name'] ?? ''),
                                    $activityFullDate,
                                ])));
                                ?>
                                <div class="activity-item<?php echo $index >= 3 ? ' activity-item-extra' : ''; ?>" data-activity-item data-activity-search="<?php echo htmlspecialchars($activitySearch); ?>" data-activity-rank="<?php echo $index; ?>"<?php echo $index >= 3 ? ' hidden' : ''; ?>>
                                    <div class="activity-badge activity-<?php echo htmlspecialchars($auditSummary['badge']); ?>">
                                        <?php echo strtoupper(substr((string)$auditSummary['badge'], 0, 1)); ?>
                                    </div>
                                    <div class="activity-copy">
                                        <strong><?php echo htmlspecialchars($auditSummary['title']); ?></strong>
                                        <span><?php echo htmlspecialchars($auditSummary['details']); ?></span>
                                    </div>
                                    <time>
                                        <span class="activity-time-relative"><?php echo htmlspecialchars(formatRelativeDate($activity['activity_time'] ?? null)); ?></span>
                                        <span class="activity-time-full">
                                            <?php echo htmlspecialchars($activityFullDate); ?>
                                        </span>
                                    </time>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </aside>
            </section>
        </div>

        <div id="create" class="tab-content <?php echo $activeTab === 'create' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'create' ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-section">
                <h2 class="dashboard-section-title">Create Account</h2>
                                <form method="POST">
                    <input type="hidden" name="action" value="create_account">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="form-row">
                        <div class="form-group"><label for="full_name">Full Name *</label><input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($old['full_name']); ?>" required></div>
                        <div class="form-group"><label for="email">Email *</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old['email']); ?>" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="phone">Phone Number (PH)</label><input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($old['phone']); ?>" pattern="^09[0-9]{9}$" maxlength="11" placeholder="09XXXXXXXXX" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,''); if(!this.value.startsWith('09')){this.value='09';}"></div>
                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="">Select a role</option>
                                <option value="engineer" <?php echo $old['role']=='engineer'?'selected':''; ?>>Engineer</option>
<option value="foreman" <?php echo $old['role']=='foreman'?'selected':''; ?>>Foreman</option>
<option value="client" <?php echo $old['role']=='client'?'selected':''; ?>>Client</option>                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group password-field">
                            <label for="password">Temporary Password *</label>
                            <div class="password-input-wrap">
                                <input type="password" id="password" name="password" minlength="12" required>
                                <button type="button" class="togglePassword" data-target="password">Show</button>
                            </div>
                            <small class="password-tip">Password must be strong: 12+ chars, uppercase, lowercase, number, special symbol (e.g. Edge#2026Secure!).</small>
                            <small id="tempPassStrength" class="pass-indicator">Strength: -</small>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Create Account</button>
                </form>
            </div>
        </div>

        <div id="users" class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'users' ? 'display: block;' : 'display: none;'; ?>">
            <div class="dashboard-actions">
                <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=users" class="action-chip<?php echo $userStatusFilter === '' ? ' active-chip' : ''; ?>">All Users</a>
                <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=users&amp;status=active" class="action-chip<?php echo $userStatusFilter === 'active' ? ' active-chip' : ''; ?>">Active</a>
                <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=users&amp;status=inactive" class="action-chip<?php echo $userStatusFilter === 'inactive' ? ' active-chip' : ''; ?>">Inactive</a>
            </div>
            <?php $sections = ['Engineers' => $engineers, 'Foreman' => $foremen, 'Clients' => $clients]; foreach ($sections as $title => $users): ?>
                <h2 class="dashboard-section-title" style="margin-top: 20px; margin-bottom: 15px;"><?php echo $title; ?></h2>
                <div class="users-table">
                    <table class="responsive-table">
                        <colgroup>
                            <col style="width: 22%;">
                            <col style="width: 26%;">
                            <col style="width: 18%;">
                            <col style="width: 14%;">
                            <col style="width: 20%;">
                        </colgroup>
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No <?php echo strtolower($title); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): $status = $user['status'] ?? 'active'; $rowId = (int)$user['id']; ?>
                                    <tr class="user-row" data-row-id="<?php echo $rowId; ?>">
                                        <td data-label="Name"><input class="table-input" type="text" data-field="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly required></td>
                                        <td data-label="Email"><input class="table-input" type="email" data-field="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly required></td>
                                        <td data-label="Phone"><input class="table-input" type="text" data-field="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="^09[0-9]{9}$" maxlength="11" placeholder="09XXXXXXXXX" oninput="this.value=this.value.replace(/[^0-9]/g,''); if(!this.value.startsWith('09')){this.value='09';}" readonly></td>
                                        <td data-label="Status"><span class="status-badge <?php echo $status === 'active' ? 'status-active' : 'status-inactive'; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
                                        <td data-label="Actions">
                                            <div class="action-group compact">
                                                <button type="button" class="action-btn edit" data-edit-btn>Edit</button>
                                                <button type="button" class="action-btn save" data-save-btn hidden>Save</button>
                                                <button type="button" class="action-btn cancel" data-cancel-btn hidden>Cancel</button>
                                            </div>
                                            <div class="action-group compact row-secondary-actions">
                                                <form method="POST" style="display:inline-block; margin:0;" onsubmit="return confirm('<?php echo $status === 'active' ? 'Deactivate this user? They will lose access to login.' : 'Reactivate this user?'; ?>');">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $rowId; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $status === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="action-btn <?php echo $status === 'active' ? 'deactivate' : 'activate'; ?>"><?php echo $status === 'active' ? 'Deactivate' : 'Reactivate'; ?></button>
                                                </form>
                                                <form method="POST" id="save-form-<?php echo $rowId; ?>" style="display:none;">
                                                    <input type="hidden" name="action" value="edit_user">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $rowId; ?>">
                                                    <input type="hidden" name="edit_full_name" data-save-field="full_name">
                                                    <input type="hidden" name="edit_email" data-save-field="email">
                                                    <input type="hidden" name="edit_phone" data-save-field="phone">
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
</div>
    </main>
</div>

<script src="../js/admin-script.js"></script>
</body>
</html>
