<?php

require_once __DIR__ . '/../../config/project_access.php';
require_once __DIR__ . '/../../config/asset_unit_helpers.php';

ensure_asset_unit_tracking_schema($conn);

function foreman_table_exists(mysqli $conn, string $tableName): bool
{
    static $cache = [];

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    $statement = $conn->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
         AND table_name = ?'
    );

    if (!$statement) {
        $cache[$tableName] = false;
        return false;
    }

    $statement->bind_param('s', $tableName);
    $statement->execute();
    $statement->bind_result($count);
    $statement->fetch();
    $statement->close();

    $cache[$tableName] = (int)$count > 0;
    return $cache[$tableName];
}

function foreman_column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = $tableName . '.' . $columnName;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $statement = $conn->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = ?
         AND column_name = ?'
    );

    if (!$statement) {
        $cache[$key] = false;
        return false;
    }

    $statement->bind_param('ss', $tableName, $columnName);
    $statement->execute();
    $statement->bind_result($count);
    $statement->fetch();
    $statement->close();

    $cache[$key] = (int)$count > 0;
    return $cache[$key];
}

function foreman_format_datetime(?string $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Not recorded';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y g:i A');
    } catch (Throwable $exception) {
        return $value;
    }
}

function foreman_status_label(?string $value): string
{
    $value = trim(str_replace(['-', '_'], ' ', (string)$value));
    return $value === '' ? 'Unknown' : ucwords($value);
}

function foreman_format_date(?string $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Not set';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable $exception) {
        return $value;
    }
}

function foreman_build_deadline_meta(?string $deadline, string $status): array
{
    $deadline = trim((string)$deadline);
    if ($deadline === '') {
        return [
            'label' => 'No deadline',
            'class' => 'status-badge--neutral',
        ];
    }

    $deadlineDate = DateTimeImmutable::createFromFormat('Y-m-d', $deadline);
    if (!$deadlineDate) {
        return [
            'label' => $deadline,
            'class' => 'status-badge--neutral',
        ];
    }

    if ($status === 'completed') {
        return [
            'label' => 'Delivered',
            'class' => 'status-badge--ok',
        ];
    }

    $today = new DateTimeImmutable('today');
    $days = (int)$today->diff($deadlineDate)->format('%r%a');

    if ($days < 0) {
        return [
            'label' => 'Overdue by ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's'),
            'class' => 'status-badge--danger',
        ];
    }

    if ($days <= 2) {
        return [
            'label' => $days === 0 ? 'Due today' : 'Due in ' . $days . ' day' . ($days === 1 ? '' : 's'),
            'class' => 'status-badge--warning',
        ];
    }

    return [
        'label' => 'Due ' . $deadlineDate->format('M j, Y'),
        'class' => 'status-badge--ok',
    ];
}

function foreman_excerpt(?string $value, int $limit = 120): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'No notes recorded.';
    }

    if (mb_strlen($value) <= $limit) {
        return $value;
    }

    return rtrim(mb_substr($value, 0, $limit - 3)) . '...';
}

function foreman_profile_initials(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'FO';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'FO';
}

function foreman_asset_status_expression(mysqli $conn, string $tableAlias = 'assets'): string
{
    $qualifiedStatus = $tableAlias . '.status';
    $qualifiedAssetStatus = $tableAlias . '.asset_status';

    return foreman_column_exists($conn, 'assets', 'status')
        ? "COALESCE(NULLIF({$qualifiedStatus}, ''), {$qualifiedAssetStatus})"
        : $qualifiedAssetStatus;
}

function foreman_fetch_profile(mysqli $conn, int $userId): array
{
    $profile = [
        'full_name' => (string)($_SESSION['name'] ?? 'Foreman'),
        'email' => '',
        'phone' => '',
        'status' => 'active',
    ];

    $statement = $conn->prepare(
        'SELECT full_name, email, phone, status
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    if (!$statement) {
        return $profile;
    }

    $statement->bind_param('i', $userId);
    $statement->execute();
    $result = $statement->get_result();

    if ($result && $result->num_rows === 1) {
        $profile = array_merge($profile, $result->fetch_assoc());
    }

    $statement->close();

    return $profile;
}

function foreman_fetch_dashboard_data(mysqli $conn, int $userId): array
{
    $assetStatusExpression = foreman_asset_status_expression($conn, 'assets');

    $data = [
        'asset_summary' => [
            'total_assets' => 0,
            'available_assets' => 0,
            'in_use_assets' => 0,
            'maintenance_assets' => 0,
            'damaged_assets' => 0,
        ],
        'usage_summary' => [
            'logs_today' => 0,
            'workers_today' => 0,
            'logs_last_7_days' => 0,
        ],
        'scan_summary' => [
            'scans_today' => 0,
            'scans_last_7_days' => 0,
        ],
        'support_summary' => [
            'active_projects' => 0,
            'open_tasks' => 0,
        ],
        'assigned_projects' => [],
        'recent_usage_logs' => [],
        'recent_scan_rows' => [],
        'worker_summary_rows' => [],
    ];

    if (foreman_table_exists($conn, 'assets')) {
        $assetSummaryResult = $conn->query(
            "SELECT
                COUNT(*) AS total_assets,
                SUM(CASE WHEN {$assetStatusExpression} = 'available' THEN 1 ELSE 0 END) AS available_assets,
                SUM(CASE WHEN {$assetStatusExpression} = 'in_use' THEN 1 ELSE 0 END) AS in_use_assets,
                SUM(CASE WHEN {$assetStatusExpression} = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_assets,
                SUM(CASE WHEN {$assetStatusExpression} IN ('damaged', 'lost') THEN 1 ELSE 0 END) AS damaged_assets
             FROM assets"
        );

        if ($assetSummaryResult) {
            $data['asset_summary'] = array_merge($data['asset_summary'], $assetSummaryResult->fetch_assoc() ?: []);
        }
    }

    if (foreman_table_exists($conn, 'asset_usage_logs')) {
        $usageSummaryStatement = $conn->prepare(
            "SELECT
                SUM(CASE WHEN DATE(used_at) = CURDATE() THEN 1 ELSE 0 END) AS logs_today,
                COUNT(DISTINCT CASE WHEN DATE(used_at) = CURDATE() THEN worker_name END) AS workers_today,
                SUM(CASE WHEN used_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS logs_last_7_days
             FROM asset_usage_logs
             WHERE foreman_id = ?"
        );

        if ($usageSummaryStatement) {
            $usageSummaryStatement->bind_param('i', $userId);
            $usageSummaryStatement->execute();
            $usageSummaryResult = $usageSummaryStatement->get_result();

            if ($usageSummaryResult && $usageSummaryResult->num_rows === 1) {
                $data['usage_summary'] = array_merge($data['usage_summary'], $usageSummaryResult->fetch_assoc());
            }

            $usageSummaryStatement->close();
        }

        $workerSummaryStatement = $conn->prepare(
            "SELECT
                worker_name,
                COUNT(*) AS usage_count,
                MAX(used_at) AS last_used_at
             FROM asset_usage_logs
             WHERE foreman_id = ?
             AND used_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY worker_name
             ORDER BY usage_count DESC, last_used_at DESC
             LIMIT 12"
        );

        if ($workerSummaryStatement) {
            $workerSummaryStatement->bind_param('i', $userId);
            $workerSummaryStatement->execute();
            $workerSummaryResult = $workerSummaryStatement->get_result();

            if ($workerSummaryResult) {
                $data['worker_summary_rows'] = $workerSummaryResult->fetch_all(MYSQLI_ASSOC);
            }

            $workerSummaryStatement->close();
        }
    }

    if (foreman_table_exists($conn, 'asset_scan_history')) {
        $scanSummaryStatement = $conn->prepare(
            "SELECT
                SUM(CASE WHEN DATE(scan_time) = CURDATE() THEN 1 ELSE 0 END) AS scans_today,
                SUM(CASE WHEN scan_time >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS scans_last_7_days
             FROM asset_scan_history
             WHERE foreman_id = ?"
        );

        if ($scanSummaryStatement) {
            $scanSummaryStatement->bind_param('i', $userId);
            $scanSummaryStatement->execute();
            $scanSummaryResult = $scanSummaryStatement->get_result();

            if ($scanSummaryResult && $scanSummaryResult->num_rows === 1) {
                $data['scan_summary'] = array_merge($data['scan_summary'], $scanSummaryResult->fetch_assoc());
            }

            $scanSummaryStatement->close();
        }
    }

    if (
        project_user_can('view_assigned_projects', 'foreman') &&
        foreman_table_exists($conn, 'project_assignments') &&
        foreman_table_exists($conn, 'projects')
    ) {
        $supportStatement = $conn->prepare(
            "SELECT
                COUNT(DISTINCT CASE WHEN p.status IN ('pending', 'ongoing', 'on-hold') THEN p.id END) AS active_projects,
                COALESCE(SUM(CASE WHEN task_totals.open_tasks IS NOT NULL THEN task_totals.open_tasks ELSE 0 END), 0) AS open_tasks
             FROM project_assignments pa
             INNER JOIN projects p ON p.id = pa.project_id
             LEFT JOIN (
                 SELECT
                    project_id,
                    SUM(CASE WHEN status IN ('pending', 'ongoing', 'delayed') THEN 1 ELSE 0 END) AS open_tasks
                 FROM tasks
                 GROUP BY project_id
             ) task_totals ON task_totals.project_id = p.id
             WHERE pa.engineer_id = ?
             AND p.status <> 'draft'"
        );

        if ($supportStatement) {
            $supportStatement->bind_param('i', $userId);
            $supportStatement->execute();
            $supportResult = $supportStatement->get_result();

            if ($supportResult && $supportResult->num_rows === 1) {
                $data['support_summary'] = array_merge($data['support_summary'], $supportResult->fetch_assoc());
            }

            $supportStatement->close();
        }

        $assignedProjectsStatement = $conn->prepare(
            "SELECT
                p.id,
                p.project_name,
                p.description,
                p.start_date,
                p.end_date,
                p.status,
                client.full_name AS client_name,
                creator.full_name AS project_owner_name,
                COALESCE(task_totals.total_tasks, 0) AS total_tasks,
                COALESCE(task_totals.completed_tasks, 0) AS completed_tasks,
                COALESCE(task_totals.open_tasks, 0) AS open_tasks,
                task_totals.next_deadline
             FROM project_assignments pa
             INNER JOIN projects p ON p.id = pa.project_id
             LEFT JOIN users client ON client.id = p.client_id
             LEFT JOIN users creator ON creator.id = p.created_by
             LEFT JOIN (
                 SELECT
                    project_id,
                    COUNT(*) AS total_tasks,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks,
                    SUM(CASE WHEN status IN ('pending', 'ongoing', 'delayed') THEN 1 ELSE 0 END) AS open_tasks,
                    MIN(CASE WHEN status <> 'completed' AND deadline IS NOT NULL THEN deadline END) AS next_deadline
                 FROM tasks
                 GROUP BY project_id
             ) task_totals ON task_totals.project_id = p.id
             WHERE pa.engineer_id = ?
             AND p.status <> 'draft'
             GROUP BY p.id, p.project_name, p.description, p.start_date, p.end_date, p.status, client.full_name, creator.full_name,
                      task_totals.total_tasks, task_totals.completed_tasks, task_totals.open_tasks, task_totals.next_deadline
             ORDER BY
                CASE p.status
                    WHEN 'ongoing' THEN 1
                    WHEN 'pending' THEN 2
                    WHEN 'on-hold' THEN 3
                    WHEN 'completed' THEN 4
                    ELSE 5
                END,
                p.created_at DESC,
                p.id DESC"
        );

        if ($assignedProjectsStatement) {
            $assignedProjectsStatement->bind_param('i', $userId);
            $assignedProjectsStatement->execute();
            $assignedProjectsResult = $assignedProjectsStatement->get_result();

            if ($assignedProjectsResult) {
                $data['assigned_projects'] = $assignedProjectsResult->fetch_all(MYSQLI_ASSOC);
            }

            $assignedProjectsStatement->close();
        }
    }

    if (foreman_table_exists($conn, 'asset_usage_logs') && foreman_table_exists($conn, 'assets')) {
        $usageLogsStatement = $conn->prepare(
            "SELECT
                aul.id,
                aul.worker_name,
                aul.notes,
                aul.used_at,
                a.asset_name,
                a.asset_type,
                au.unit_code,
                " . foreman_asset_status_expression($conn, 'a') . " AS resolved_status
             FROM asset_usage_logs aul
             INNER JOIN assets a ON a.id = aul.asset_id
             LEFT JOIN asset_units au ON au.id = aul.asset_unit_id
             WHERE aul.foreman_id = ?
             ORDER BY aul.used_at DESC, aul.id DESC
             LIMIT 20"
        );

        if ($usageLogsStatement) {
            $usageLogsStatement->bind_param('i', $userId);
            $usageLogsStatement->execute();
            $usageLogsResult = $usageLogsStatement->get_result();

            if ($usageLogsResult) {
                $data['recent_usage_logs'] = $usageLogsResult->fetch_all(MYSQLI_ASSOC);
            }

            $usageLogsStatement->close();
        }
    }

    if (foreman_table_exists($conn, 'asset_scan_history') && foreman_table_exists($conn, 'assets')) {
        $scanRowsStatement = $conn->prepare(
            "SELECT
                ash.id,
                ash.scan_time,
                ash.scan_device,
                a.asset_name,
                a.asset_type,
                au.unit_code
             FROM asset_scan_history ash
             INNER JOIN assets a ON a.id = ash.asset_id
             LEFT JOIN asset_units au ON au.id = ash.asset_unit_id
             WHERE ash.foreman_id = ?
             ORDER BY ash.scan_time DESC, ash.id DESC
             LIMIT 20"
        );

        if ($scanRowsStatement) {
            $scanRowsStatement->bind_param('i', $userId);
            $scanRowsStatement->execute();
            $scanRowsResult = $scanRowsStatement->get_result();

            if ($scanRowsResult) {
                $data['recent_scan_rows'] = $scanRowsResult->fetch_all(MYSQLI_ASSOC);
            }

            $scanRowsStatement->close();
        }
    }

    return $data;
}
