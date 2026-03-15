<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_any_role(['foreman']);

header('Content-Type: application/json; charset=utf-8');

$assetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;
if ($assetId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing asset_id']);
    exit;
}

$stmt = $conn->prepare('SELECT id, asset_name, asset_type, serial_number, asset_status, created_at FROM assets WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $assetId);
$stmt->execute();
$result = $stmt->get_result();
$asset = $result ? $result->fetch_assoc() : null;

if (!$asset) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Asset not found']);
    exit;
}

// Return asset record and latest QR value
$qrStmt = $conn->prepare('SELECT qr_code_value FROM asset_qr_codes WHERE asset_id = ? ORDER BY id DESC LIMIT 1');
$qrStmt->bind_param('i', $assetId);
$qrStmt->execute();
$qrResult = $qrStmt->get_result();
$qr = $qrResult ? $qrResult->fetch_assoc() : null;

$asset['qr_code_value'] = $qr['qr_code_value'] ?? null;

echo json_encode(['status' => 'success', 'asset' => $asset]);
