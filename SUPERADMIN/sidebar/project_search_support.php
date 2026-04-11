<?php

if (!function_exists('project_search_table_has_column')) {
    function project_search_table_has_column(mysqli $conn, string $tableName, string $columnName): bool {
        $stmt = $conn->prepare(
            'SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             AND COLUMN_NAME = ?
             LIMIT 1'
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $tableName, $columnName);
        $stmt->execute();
        $result = $stmt->get_result();

        return (bool)($result && $result->fetch_assoc());
    }
}

if (!function_exists('project_search_index_exists')) {
    function project_search_index_exists(mysqli $conn, string $tableName, string $indexName): bool {
        $stmt = $conn->prepare(
            'SELECT 1
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             AND INDEX_NAME = ?
             LIMIT 1'
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $tableName, $indexName);
        $stmt->execute();
        $result = $stmt->get_result();

        return (bool)($result && $result->fetch_assoc());
    }
}

if (!function_exists('ensure_project_search_indexes')) {
    function ensure_project_search_indexes(mysqli $conn, bool $hasProjectAddressColumn): void {
        if (!project_search_index_exists($conn, 'projects', 'idx_projects_search_name_status_created')) {
            $conn->query(
                'ALTER TABLE projects
                 ADD INDEX idx_projects_search_name_status_created (project_name, status, created_at, id)'
            );
        }

        if ($hasProjectAddressColumn && !project_search_index_exists($conn, 'projects', 'idx_projects_search_address')) {
            $conn->query(
                'ALTER TABLE projects
                 ADD INDEX idx_projects_search_address (project_address(120))'
            );
        }

        if (!project_search_index_exists($conn, 'users', 'idx_users_search_role_status_name')) {
            $conn->query(
                'ALTER TABLE users
                 ADD INDEX idx_users_search_role_status_name (role, status, full_name)'
            );
        }

        if (!project_search_index_exists($conn, 'project_assignments', 'idx_project_assignments_project_latest')) {
            $conn->query(
                'ALTER TABLE project_assignments
                 ADD INDEX idx_project_assignments_project_latest (project_id, id, engineer_id)'
            );
        }
    }
}

if (!function_exists('project_search_bind_params')) {
    function project_search_bind_params(mysqli_stmt $stmt, string $types, array $params): bool {
        if ($types === '' || empty($params)) {
            return true;
        }

        $references = [];
        foreach ($params as $index => $value) {
            $references[$index] = &$params[$index];
        }

        array_unshift($references, $types);

        return call_user_func_array([$stmt, 'bind_param'], $references);
    }
}

if (!function_exists('project_search_latest_assignment_sql')) {
    function project_search_latest_assignment_sql(): string {
        return "
            LEFT JOIN (
                SELECT pa.project_id, pa.engineer_id
                FROM project_assignments pa
                INNER JOIN (
                    SELECT project_id, MAX(id) AS latest_id
                    FROM project_assignments
                    GROUP BY project_id
                ) latest ON latest.latest_id = pa.id
            ) latest_assignment ON latest_assignment.project_id = p.id
        ";
    }
}

if (!function_exists('project_search_build_filter')) {
    function project_search_build_filter(bool $hasProjectAddressColumn, string $searchQuery, string $statusFilter): array {
        $conditions = [];
        $types = '';
        $params = [];

        if ($searchQuery !== '') {
            $likeValue = $searchQuery . '%';
            $searchConditions = [
                'p.project_name LIKE ?',
                'client.full_name LIKE ?',
                'engineer.full_name LIKE ?',
                'p.status LIKE ?',
            ];
            $searchParams = [$likeValue, $likeValue, $likeValue, $likeValue];

            if ($hasProjectAddressColumn) {
                $searchConditions[] = 'p.project_address LIKE ?';
                $searchParams[] = $likeValue;
            }

            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            $types .= str_repeat('s', count($searchParams));
            array_push($params, ...$searchParams);
        }

        if ($statusFilter !== '') {
            $conditions[] = 'p.status = ?';
            $types .= 's';
            $params[] = $statusFilter;
        }

        $whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $types, $params];
    }
}

if (!function_exists('project_search_fetch_count')) {
    function project_search_fetch_count(mysqli $conn, bool $hasProjectAddressColumn, string $searchQuery, string $statusFilter): int {
        [$whereSql, $types, $params] = project_search_build_filter($hasProjectAddressColumn, $searchQuery, $statusFilter);

        $sql = "
            SELECT COUNT(*) AS total
            FROM projects p
            LEFT JOIN users client ON client.id = p.client_id
            " . project_search_latest_assignment_sql() . "
            LEFT JOIN users engineer ON engineer.id = latest_assignment.engineer_id
            {$whereSql}
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt || !project_search_bind_params($stmt, $types, $params) || !$stmt->execute()) {
            return 0;
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('project_search_fetch_page')) {
    function project_search_fetch_page(
        mysqli $conn,
        bool $hasProjectAddressColumn,
        string $searchQuery,
        string $statusFilter,
        int $limit,
        int $offset
    ): array {
        [$whereSql, $types, $params] = project_search_build_filter($hasProjectAddressColumn, $searchQuery, $statusFilter);
        $projectAddressSelect = $hasProjectAddressColumn ? 'p.project_address,' : 'NULL AS project_address,';

        $sql = "
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
            " . project_search_latest_assignment_sql() . "
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
            LIMIT ? OFFSET ?
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $typesWithPaging = $types . 'ii';
        $paramsWithPaging = array_merge($params, [$limit, $offset]);

        if (!project_search_bind_params($stmt, $typesWithPaging, $paramsWithPaging) || !$stmt->execute()) {
            return [];
        }

        $result = $stmt->get_result();

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}

if (!function_exists('project_search_fetch_suggestions')) {
    function project_search_fetch_suggestions(
        mysqli $conn,
        bool $hasProjectAddressColumn,
        string $searchQuery,
        string $statusFilter,
        int $limit = 8
    ): array {
        [$whereSql, $types, $params] = project_search_build_filter($hasProjectAddressColumn, $searchQuery, $statusFilter);
        $projectAddressSelect = $hasProjectAddressColumn ? 'p.project_address,' : 'NULL AS project_address,';
        $relevanceLike = $searchQuery . '%';

        $sql = "
            SELECT
                p.id,
                p.project_name,
                p.status,
                {$projectAddressSelect}
                client.full_name AS client_name,
                engineer.full_name AS engineer_name
            FROM projects p
            LEFT JOIN users client ON client.id = p.client_id
            " . project_search_latest_assignment_sql() . "
            LEFT JOIN users engineer ON engineer.id = latest_assignment.engineer_id
            {$whereSql}
            ORDER BY
                CASE
                    WHEN p.project_name LIKE ? THEN 0
                    WHEN client.full_name LIKE ? THEN 1
                    WHEN engineer.full_name LIKE ? THEN 2
                    " . ($hasProjectAddressColumn ? "WHEN p.project_address LIKE ? THEN 3" : '') . "
                    ELSE 4
                END,
                p.created_at DESC,
                p.id DESC
            LIMIT ?
        ";

        $relevanceParams = [$relevanceLike, $relevanceLike, $relevanceLike];
        if ($hasProjectAddressColumn) {
            $relevanceParams[] = $relevanceLike;
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $typesWithOrdering = $types . str_repeat('s', count($relevanceParams)) . 'i';
        $paramsWithOrdering = array_merge($params, $relevanceParams, [$limit]);

        if (!project_search_bind_params($stmt, $typesWithOrdering, $paramsWithOrdering) || !$stmt->execute()) {
            return [];
        }

        $result = $stmt->get_result();

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
