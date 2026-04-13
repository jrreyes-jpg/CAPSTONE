<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'foreman') {
    header("Location: /codesamplecaps/public/login.php");
    exit();
}

// Placeholder values; controller should provide real data via prepared statements
$user_id = $_SESSION['user_id'];

$assignedProjectsCount = 0; // controller: count projects assigned via project_assignments
$assignedTasksCount = 0;    // controller: count tasks assigned to this foreman
$teamMembers = [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foreman Dashboard - Edge Automation</title>
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/global.css">
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/foreman_dashboard.css">
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/qr_scanner.css">
</head>
<body class="foreman-dashboard">

<?php include(__DIR__ . '/../sidebar/sidebar_foreman.php'); ?>

<div class="main-content">
    <h1>🛠️ Foreman Dashboard</h1>
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Assigned Projects</h4>
            <p style="font-size:28px; font-weight:700;"><?php echo $assignedProjectsCount; ?></p>
        </div>
        <div class="stat-card">
            <h4>Assigned Tasks</h4>
            <p style="font-size:28px; font-weight:700;"><?php echo $assignedTasksCount; ?></p>
        </div>
        <div class="stat-card">
            <h4>Team Members</h4>
            <p style="font-size:18px; color:#666;"><?php echo count($teamMembers); ?> members</p>
        </div>
    </div>

    <div class="card-section">
        <h2>On-site Progress</h2>
        <p>Use the task list to update manpower availability and site progress. This page should be backed by a controller that queries `project_assignments`, `tasks` and `foreman_profiles`.</p>
    </div>
</div>

<!-- QR Scanner Modal -->
<div class="qr-modal" id="qrScannerModal" aria-hidden="true">
    <div class="qr-modal-content">
        <div class="qr-modal-header">
            <h2>QR Asset Scanner</h2>
            <button id="qrScannerClose" class="qr-close" type="button" aria-label="Close">×</button>
        </div>
        <div class="qr-modal-body">
            <div class="qr-status" id="qrStatus">Ready to scan.</div>
            <div class="qr-scanner-area" id="qr-reader"></div>
            <div class="qr-error" id="qrScannerError"></div>
            <div class="qr-asset-info" id="qrAssetInfo"></div>
            <div class="qr-input-row">
                <input id="qrWorkerName" placeholder="Worker / personnel name" aria-label="Worker name">
                <textarea id="qrNotes" rows="2" placeholder="Optional notes (location, condition, etc.)" aria-label="Notes"></textarea>
            </div>
            <div class="qr-actions">
                <button class="btn-primary" id="qrLogUsage" type="button">Log Usage</button>
                <button class="btn-secondary" id="qrScannerCloseSecondary" type="button">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../js/html5-qrcode.min.js"></script>
<script src="../js/qr_scanner_foreman.js"></script>
</body>
</html>