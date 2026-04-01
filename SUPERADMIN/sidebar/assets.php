<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/audit_log.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions as QRCodeOptions;

require_role('super_admin');

$message = '';
$error = '';
$qrPreview = '';
$createdAssetId = 0;
$qrLibraryReady = false;

$qrAutoloadPath = __DIR__ . '/../../vendor/autoload.php';
$qrRequiredFiles = [
    __DIR__ . '/../../vendor/symfony/polyfill-ctype/bootstrap.php',
    __DIR__ . '/../../vendor/chillerlan/php-qrcode/src/QRCode.php',
];

if (is_file($qrAutoloadPath)) {
    $missingQrDependency = false;
    foreach ($qrRequiredFiles as $requiredFile) {
        if (!is_file($requiredFile)) {
            $missingQrDependency = true;
            break;
        }
    }

    if (!$missingQrDependency) {
        require_once $qrAutoloadPath;
        $qrLibraryReady = class_exists(QRCode::class) && class_exists(QRCodeOptions::class);
    }
}

function generateQRDataUri(string $value): string {
    global $qrLibraryReady;

    if (!$qrLibraryReady) {
        return '';
    }

    $options = [
        'version' => 5,
        'outputType' => 'png',
        'eccLevel' => 'L',
        'scale' => 6,
        'imageBase64' => false,
    ];

    return (new QRCode($options))->render($value);
}

function getAssetSnapshot(mysqli $conn, int $assetId): ?array {
    $stmt = $conn->prepare(
        'SELECT a.id, a.asset_name, a.asset_type, a.serial_number, a.asset_status, q.id AS qr_id
         FROM assets a
         LEFT JOIN asset_qr_codes q ON q.asset_id = a.id
         WHERE a.id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $assetId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result ? $result->fetch_assoc() : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_asset') {
        $assetName = trim($_POST['asset_name'] ?? '');
        $assetType = trim($_POST['asset_type'] ?? '');
        $serial = trim($_POST['serial_number'] ?? '');

        if ($assetName === '') {
            $_SESSION['assets_error'] = 'Asset name is required.';
        } else {
            $stmt = $conn->prepare('INSERT INTO assets (asset_name, asset_type, serial_number) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $assetName, $assetType, $serial);

            if ($stmt->execute()) {
                $assetId = $stmt->insert_id;
                $qrValue = 'asset_id=' . $assetId;

                $qrStmt = $conn->prepare('INSERT INTO asset_qr_codes (asset_id, qr_code_value) VALUES (?, ?)');
                $qrStmt->bind_param('is', $assetId, $qrValue);
                $qrStmt->execute();

                audit_log_event(
                    $conn,
                    (int)($_SESSION['user_id'] ?? 0),
                    'create_asset',
                    'asset',
                    $assetId,
                    null,
                    [
                        'asset_name' => $assetName,
                        'asset_type' => $assetType,
                        'serial_number' => $serial,
                    ]
                );

                $_SESSION['assets_message'] = 'Asset created. QR generated.';
                $_SESSION['assets_qr_preview'] = $qrValue;
                $_SESSION['assets_created_asset_id'] = $assetId;
            } else {
                $_SESSION['assets_error'] = 'Failed to create asset.';
            }
        }

        header('Location: /codesamplecaps/SUPERADMIN/sidebar/assets.php');
        exit();
    }

    if ($action === 'return_asset') {
        $assetId = (int)($_POST['asset_id'] ?? 0);

        if ($assetId > 0) {
            $beforeReturn = getAssetSnapshot($conn, $assetId);
            $stmt = $conn->prepare('UPDATE assets SET asset_status = ? WHERE id = ?');
            $status = 'available';
            $stmt->bind_param('si', $status, $assetId);
            $stmt->execute();

            audit_log_event(
                $conn,
                (int)($_SESSION['user_id'] ?? 0),
                'return_asset',
                'asset',
                $assetId,
                $beforeReturn ? ['asset_status' => $beforeReturn['asset_status'] ?? null] : null,
                [
                    'asset_name' => $beforeReturn['asset_name'] ?? null,
                    'asset_status' => 'available',
                ]
            );

            $_SESSION['assets_message'] = 'Asset returned.';
        }

        header('Location: /codesamplecaps/SUPERADMIN/sidebar/assets.php');
        exit();
    }

    if ($action === 'delete_asset') {
        $assetId = (int)($_POST['asset_id'] ?? 0);

        if ($assetId > 0) {
            $beforeDelete = getAssetSnapshot($conn, $assetId);
            $stmtQR = $conn->prepare('DELETE FROM asset_qr_codes WHERE asset_id = ?');
            $stmtQR->bind_param('i', $assetId);
            $stmtQR->execute();

            $stmt = $conn->prepare('DELETE FROM assets WHERE id = ?');
            $stmt->bind_param('i', $assetId);
            $stmt->execute();

            audit_log_event(
                $conn,
                (int)($_SESSION['user_id'] ?? 0),
                'delete_asset',
                'asset',
                $assetId,
                $beforeDelete ? [
                    'asset_name' => $beforeDelete['asset_name'] ?? null,
                    'asset_type' => $beforeDelete['asset_type'] ?? null,
                    'serial_number' => $beforeDelete['serial_number'] ?? null,
                    'asset_status' => $beforeDelete['asset_status'] ?? null,
                ] : null,
                null
            );

            $_SESSION['assets_message'] = 'Asset deleted.';
        }

        header('Location: /codesamplecaps/SUPERADMIN/sidebar/assets.php');
        exit();
    }
}

if (isset($_SESSION['assets_message'])) {
    $message = (string)$_SESSION['assets_message'];
    unset($_SESSION['assets_message']);
}

if (isset($_SESSION['assets_error'])) {
    $error = (string)$_SESSION['assets_error'];
    unset($_SESSION['assets_error']);
}

if (isset($_SESSION['assets_qr_preview'])) {
    $qrPreview = (string)$_SESSION['assets_qr_preview'];
    unset($_SESSION['assets_qr_preview']);
}

if (isset($_SESSION['assets_created_asset_id'])) {
    $createdAssetId = (int)$_SESSION['assets_created_asset_id'];
    unset($_SESSION['assets_created_asset_id']);
}

$assetFilter = trim((string)($_GET['filter'] ?? ''));
if (!in_array($assetFilter, ['quality'], true)) {
    $assetFilter = '';
}

$assets = [];
$assetQuery = 'SELECT a.*, q.qr_code_value
    FROM assets a
    LEFT JOIN asset_qr_codes q ON a.id = q.asset_id';
if ($assetFilter === 'quality') {
    $assetQuery .= "
    WHERE q.id IS NULL
    OR a.serial_number IS NULL
    OR TRIM(a.serial_number) = ''";
}
$assetQuery .= ' ORDER BY a.created_at DESC';
$result = $conn->query($assetQuery);

if ($result) {
    $assets = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets & QR Codes - Super Admin</title>
    <link rel="stylesheet" href="../css/admin-dashboard.css">
    <link rel="stylesheet" href="../css/assets.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../sidebar_super_admin.php'; ?>

    <main class="main-content assets-content">
        <div class="header page-header-card">
            <div class="header-copy">
                <h1>Assets & QR Codes</h1>
                <p>Create assets, preview QR codes, and manage return actions cleanly on mobile or desktop.</p>
            </div>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (!$qrLibraryReady): ?><div class="alert alert-error">QR preview/print is temporarily unavailable because Composer packages are incomplete in `vendor/`.</div><?php endif; ?>

        <?php if ($qrPreview !== ''): ?>
            <section class="form-section asset-preview-section">
                <h2 class="dashboard-section-title">QR Preview (Newest Asset)</h2>
                <div class="asset-preview">
                    <?php if ($qrLibraryReady): ?>
                        <img class="asset-preview-image" src="<?php echo generateQRDataUri($qrPreview); ?>" alt="Latest asset QR preview">
                    <?php else: ?>
                        <div class="empty-state-solid">QR preview unavailable until vendor packages are restored.</div>
                    <?php endif; ?>
                    <div class="asset-preview-meta">
                        <?php if ($createdAssetId > 0): ?>
                            <p>Asset ID: <strong><?php echo htmlspecialchars((string)$createdAssetId); ?></strong></p>
                            <?php if ($qrLibraryReady): ?>
                                <a href="/codesamplecaps/SUPERADMIN/print_qr_codes.php?asset_id=<?php echo $createdAssetId; ?>" target="_blank" class="btn-secondary" rel="noreferrer noopener">Print This QR</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="form-section">
            <h2 class="dashboard-section-title">Create New Asset</h2>
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

        <section class="asset-listing-section">
            <div class="asset-section-header">
                <h2 class="dashboard-section-title">Existing Assets</h2>
                <div class="asset-section-actions">
                    <?php if ($qrLibraryReady): ?>
                        <a href="/codesamplecaps/SUPERADMIN/print_qr_codes.php" target="_blank" class="btn-secondary">Print QR Codes</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dashboard-actions">
                <a href="/codesamplecaps/SUPERADMIN/sidebar/assets.php" class="action-chip<?php echo $assetFilter === '' ? ' active-chip' : ''; ?>">All Assets</a>
                <a href="/codesamplecaps/SUPERADMIN/sidebar/assets.php?filter=quality" class="action-chip<?php echo $assetFilter === 'quality' ? ' active-chip' : ''; ?>">Data Quality</a>
            </div>

            <div class="table-wrapper">
                <table class="responsive-table mobile-card-table assets-table">
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
                        <?php if (count($assets) === 0): ?>
                            <tr>
                                <td colspan="6" class="table-empty-cell">No assets yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td data-label="ID"><?php echo htmlspecialchars($asset['id']); ?></td>
                                    <td data-label="Asset">
                                        <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($asset['asset_type']); ?></small><br>
                                        <small>SN: <?php echo htmlspecialchars($asset['serial_number']); ?></small>
                                    </td>
                                    <td data-label="Status">
                                        <span class="asset-status asset-status--<?php echo htmlspecialchars($asset['asset_status']); ?>">
                                            <?php echo htmlspecialchars($asset['asset_status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="QR Code">
                                        <div class="asset-qr-actions">
                                            <?php if (!empty($asset['qr_code_value'])): ?>
                                                <?php if ($qrLibraryReady): ?>
                                                    <?php
                                                    $qrValue = $asset['qr_code_value'] ?: 'asset_id=' . $asset['id'];
                                                    $qrDataUri = generateQRDataUri($qrValue);
                                                    ?>
                                                    <button type="button" onclick="showQR('<?php echo $qrDataUri; ?>')" class="btn-secondary">Preview</button>
                                                    <a href="/codesamplecaps/SUPERADMIN/print_qr_codes.php?asset_id=<?php echo $asset['id']; ?>" target="_blank" rel="noreferrer noopener" class="asset-link-btn">Print</a>
                                                <?php else: ?>
                                                    <span class="asset-inline-note">QR unavailable</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="asset-inline-note">No QR</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Created"><?php echo htmlspecialchars($asset['created_at']); ?></td>
                                    <td data-label="Actions">
                                        <div class="asset-row-actions">
                                            <form method="POST" class="asset-inline-form" onsubmit="return confirm('Delete this asset?');">
                                                <input type="hidden" name="action" value="delete_asset">
                                                <input type="hidden" name="asset_id" value="<?php echo $asset['id']; ?>">
                                                <button type="submit" class="btn-danger">Delete</button>
                                            </form>

                                            <?php if ($asset['asset_status'] === 'in_use'): ?>
                                                <form method="POST" class="asset-inline-form">
                                                    <input type="hidden" name="action" value="return_asset">
                                                    <input type="hidden" name="asset_id" value="<?php echo $asset['id']; ?>">
                                                    <button type="submit" class="btn-secondary">Return</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="asset-inline-note">No return needed</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<div id="qrModal" class="modal-backdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); justify-content:center; align-items:center;">
    <img id="qrModalImg" class="modal-image" style="max-width:300px; background:#fff; padding:10px;">
</div>

<script src="../js/admin-script.js"></script>
</body>
</html>
