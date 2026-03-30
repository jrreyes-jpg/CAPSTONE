<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_role('super_admin');

$history = [];
$stmt = $conn->prepare(
    'SELECT h.id, h.scan_time, h.scan_device, a.asset_name, a.asset_type, a.serial_number, u.full_name AS foreman_name
     FROM asset_scan_history h
     LEFT JOIN assets a ON a.id = h.asset_id
     LEFT JOIN users u ON u.id = h.foreman_id
     ORDER BY h.scan_time DESC
     LIMIT 200'
);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $history = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan History - Edge Automation</title>
    <link rel="stylesheet" href="../css/admin_dashboard.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../components/sidebar_super_admin.php'; ?>

    <main class="main-content">
        <div class="header">
            <h1>Scan History</h1>
            <p>Last 200 QR scans (auditing)</p>
        </div>

        <div class="table-wrapper">
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Asset</th>
                        <th>Foreman</th>
                        <th>Device</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($history) === 0): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 20px;">No scan history yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['scan_time']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['asset_name'] ?? 'Unknown'); ?></strong><br>
                                    <small><?php echo htmlspecialchars($row['asset_type'] ?? ''); ?></small><br>
                                    <small>SN: <?php echo htmlspecialchars($row['serial_number'] ?? ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['foreman_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($row['scan_device'] ?? 'Unknown'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
