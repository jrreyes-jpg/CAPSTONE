<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

require_role('super_admin');

$message = '';
$error = '';

// -- QR generation helper (SVG, offline) --
function generateQRDataUri(string $value): string {
    $options = new QROptions([
        'version'      => 5,
        'outputType'   => QRCode::OUTPUT_IMAGE_SVG,
        'eccLevel'     => QRCode::ECC_L,
        'scale'        => 6,
        'imageBase64'  => false,
    ]);

    $svg = (new QRCode($options))->render($value);
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

// Helper: build absolute URL for QR code (used for QR text)
function getBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $scheme . '://' . $host . $path;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_asset') {
        $assetName = trim($_POST['asset_name'] ?? '');
        $assetType = trim($_POST['asset_type'] ?? '');
        $serial = trim($_POST['serial_number'] ?? '');

        if ($assetName === '') {
            $error = 'Asset name is required.';
        } else {
            $stmt = $conn->prepare('INSERT INTO assets (asset_name, asset_type, serial_number) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $assetName, $assetType, $serial);
            if ($stmt->execute()) {
                $assetId = $stmt->insert_id;
                $qrValue = 'asset_id=' . $assetId;

                $qrStmt = $conn->prepare('INSERT INTO asset_qr_codes (asset_id, qr_code_value) VALUES (?, ?)');
                $qrStmt->bind_param('is', $assetId, $qrValue);
                $qrStmt->execute();

                $message = 'Asset created. QR code generated.';
            } else {
                $error = 'Failed to create asset. Please check the database configuration.';
            }
        }
    }

    if ($action === 'return_asset') {
        $assetId = (int)($_POST['asset_id'] ?? 0);
        if ($assetId <= 0) {
            $error = 'Invalid asset specified.';
        } else {
            $updateStmt = $conn->prepare('UPDATE assets SET asset_status = ? WHERE id = ?');
            $status = 'available';
            $updateStmt->bind_param('si', $status, $assetId);
            if ($updateStmt->execute()) {
                $message = 'Asset marked as available.';
            } else {
                $error = 'Failed to update asset status.';
            }
        }
    }
}
    ?>
    <?php
// --- FETCH ALL ASSETS ---
$assets = []; // initialize bilang empty array para safe

$result = $conn->query("
    SELECT a.*, q.qr_code_value
    FROM assets a
    LEFT JOIN asset_qr_codes q ON a.id = q.asset_id
    ORDER BY a.created_at DESC
");

if ($result) {
    $assets = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<body>
<div class="container">
    <?php include __DIR__ . '/../components/sidebar_super_admin.php'; ?>

    <main class="main-content">
        <div class="header">
            <h1>Assets & QR Codes</h1>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <section class="form-section">
            <h2>Create New Asset</h2>
            <form method="POST" class="asset-form">
                <input type="hidden" name="action" value="create_asset">
                <div class="form-row">
                    <div class="form-group">
                        <label for="asset_name">Asset Name *</label>
                        <input type="text" id="asset_name" name="asset_name" required>
                    </div>
                    <div class="form-group">
                        <label for="asset_type">Asset Type</label>
                        <input type="text" id="asset_type" name="asset_type">
                    </div>
                    <div class="form-group">
                        <label for="serial_number">Serial Number</label>
                        <input type="text" id="serial_number" name="serial_number">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Create Asset + Generate QR</button>
            </form>
        </section>

        <section style="margin-top: 30px;">
            <div style="display:flex; align-items:center; justify-content:space-between;">
                <h2>Existing Assets</h2>
                <div>
                    <a href="/codesamplecaps/views/dashboards/print_qr_codes.php" target="_blank" class="btn-secondary" style="margin-right: 8px;">Print QR Codes</a>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="responsive-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Asset</th>
                            <th>Status</th>
                            <th>QR Code</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $asset): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asset['id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($asset['asset_type']); ?></small><br>
                                    <small>SN: <?php echo htmlspecialchars($asset['serial_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($asset['asset_status']); ?></td>
                                <td>
                                    <?php if (!empty($asset['qr_code_value'])): ?>
                                        <?php
                                            $qrValue = $asset['qr_code_value'] ?: 'asset_id=' . $asset['id'];
                                            $qrDataUri = generateQRDataUri($qrValue);
                                        ?>
                                        <img src="<?php echo $qrDataUri; ?>" alt="QR code" style="max-width:140px;">
                                        <div style="margin-top:4px;"><a href="/codesamplecaps/views/dashboards/print_qr_codes.php?asset_id=<?php echo $asset['id']; ?>" target="_blank" rel="noreferrer noopener">Print</a></div>
                                    <?php else: ?>
                                        <span style="opacity: 0.7;">No QR</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($asset['created_at']); ?></td>
                                <td>
                                    <?php if ($asset['asset_status'] === 'in_use'): ?>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="action" value="return_asset">
                                            <input type="hidden" name="asset_id" value="<?php echo $asset['id']; ?>">
                                            <button type="submit" class="btn-secondary" style="padding:6px 10px;">Return</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($assets) === 0): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 20px;">No assets yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
