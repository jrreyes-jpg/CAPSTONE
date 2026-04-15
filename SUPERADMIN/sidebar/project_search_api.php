<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/project_search_support.php';

require_role('super_admin');

header('Content-Type: application/json; charset=UTF-8');

$searchQuery = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$limit = min(10, max(1, (int)($_GET['limit'] ?? 8)));
$hasProjectAddressColumn = project_search_table_has_column($conn, 'projects', 'project_address');
$hasProjectEmailColumn = project_search_table_has_column($conn, 'projects', 'project_email');

ensure_project_search_indexes($conn, $hasProjectAddressColumn);

if (mb_strlen($searchQuery) < 2) {
    echo json_encode([
        'query' => $searchQuery,
        'results' => [],
        'message' => 'Type at least 2 characters.',
    ]);
    exit();
}

$results = project_search_fetch_suggestions($conn, $hasProjectAddressColumn, $hasProjectEmailColumn, $searchQuery, $statusFilter, $limit);
$payload = array_map(
    static function (array $project): array {
        return [
            'id' => (int)($project['id'] ?? 0),
            'title' => (string)($project['project_name'] ?? 'Project'),
            'status' => (string)($project['status'] ?? ''),
            'client' => (string)($project['client_name'] ?? 'N/A'),
            'engineer' => (string)($project['engineer_name'] ?? 'Not assigned'),
            'site' => (string)($project['project_address'] ?? ''),
            'link' => '/codesamplecaps/SUPERADMIN/sidebar/project_details.php?id=' . (int)($project['id'] ?? 0),
        ];
    },
    $results
);

echo json_encode([
    'query' => $searchQuery,
    'results' => $payload,
    'message' => empty($payload) ? 'No matching projects found.' : null,
]);
