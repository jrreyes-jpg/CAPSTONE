<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_any_role(['foreman']);

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$assetId = isset($payload['asset_id']) ? (int)$payload['asset_id'] : 0;
$workerName = trim($payload['worker_name'] ?? '');
$notes = trim($payload['notes'] ?? '');
$device = trim($payload['device'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));

if ($assetId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing asset_id']);
    exit;
}

if ($workerName === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Worker name is required']);
    exit;
}

$foremanId = (int)($_SESSION['user_id'] ?? 0);
if ($foremanId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Prevent duplicate within 60 seconds
$duplicateStmt = $conn->prepare(
    'SELECT used_at FROM asset_usage_logs WHERE asset_id = ? AND foreman_id = ? ORDER BY used_at DESC LIMIT 1'
);
$duplicateStmt->bind_param('ii', $assetId, $foremanId);
$duplicateStmt->execute();
$dupResult = $duplicateStmt->get_result();
$recent = $dupResult ? $dupResult->fetch_assoc() : null;

if ($recent) {
    $lastUsed = new DateTime($recent['used_at']);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $lastUsed->getTimestamp();
    if ($diff < 60) {
        echo json_encode(['status' => 'error', 'message' => 'Scan ignored: same asset scanned recently.']);
        exit;
    }
}

// Log usage
$logStmt = $conn->prepare('INSERT INTO asset_usage_logs (asset_id, foreman_id, worker_name, notes) VALUES (?, ?, ?, ?)');
$logStmt->bind_param('iiss', $assetId, $foremanId, $workerName, $notes);
$logStmt->execute();

// Update asset status to in_use
$updateStmt = $conn->prepare('UPDATE assets SET asset_status = ? WHERE id = ?');
$status = 'in_use';
$updateStmt->bind_param('si', $status, $assetId);
$updateStmt->execute();

// Record scan history
$historyStmt = $conn->prepare('INSERT INTO asset_scan_history (asset_id, foreman_id, scan_device) VALUES (?, ?, ?)');
$historyStmt->bind_param('iis', $assetId, $foremanId, $device);
$historyStmt->execute();

echo json_encode(['status' => 'success', 'message' => 'Asset usage logged.']);
