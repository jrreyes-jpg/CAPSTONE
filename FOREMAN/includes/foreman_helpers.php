<?php

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

function foreman_asset_status_expression(mysqli $conn): string
{
    return foreman_column_exists($conn, 'assets', 'status')
        ? "COALESCE(NULLIF(status, ''), asset_status)"
        : 'asset_status';
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
    $assetStatusExpression = foreman_asset_status_expression($conn);

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

    if (foreman_table_exists($conn, 'tasks') && foreman_table_exists($conn, 'projects')) {
        $supportStatement = $conn->prepare(
            "SELECT
                COUNT(DISTINCT CASE WHEN p.status IN ('pending', 'ongoing', 'on-hold') THEN t.project_id END) AS active_projects,
                SUM(CASE WHEN t.status IN ('pending', 'ongoing', 'delayed') THEN 1 ELSE 0 END) AS open_tasks
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id
             WHERE t.assigned_to = ?
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
                {$assetStatusExpression} AS resolved_status
             FROM asset_usage_logs aul
             INNER JOIN assets a ON a.id = aul.asset_id
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
                a.asset_type
             FROM asset_scan_history ash
             INNER JOIN assets a ON a.id = ash.asset_id
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
