<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRErrorCorrectLevel;

require_role('super_admin');

$message = '';
$error = '';

// -- QR generation helper (SVG, offline) --
function generateQRDataUri(string $value): string {
    $options = new QROptions([
        'version'      => 5,
        'outputType'   => 'svg',       // OUTPUT_IMAGE_SVG sa v3, OUTPUT_SVG sa v6
        'eccLevel'     => 'L',  // ECC_L sa v3, QRErrorCorrectLevel::L sa v6
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets & QR Codes - Super Admin</title>
    <style>/* ==========================================
   ASSETS & QR CODES PAGE - CSS3 STYLES
   Modern UI with Flexbox, Gradients, Transitions
========================================== */

:root {
    --primary: #16a34a;
    --primary-dark: #15803d;
    --primary-light: #22c55e;
    --danger: #dc2626;
    --warning: #f59e0b;
    --success: #10b981;
    --bg-light: #f8fafc;
    --text-dark: #1f2937;
    --text-light: #6b7280;
    --shadow-soft: 0 4px 12px rgba(0,0,0,0.05);
    --shadow-hover: 0 8px 25px rgba(0,0,0,0.1);
    --border-radius: 8px;
    --transition: all 0.3s ease;
}

.container {
    display: flex;
    min-height: 100vh;
}

.main-content {
    flex: 1;
    margin-left: 260px;
    padding: 30px;
    background: var(--bg-light);
}

.header {
    margin-bottom: 30px;
}

.header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Alerts */
.alert {
    padding: 15px 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    font-weight: 500;
    transition: var(--transition);
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
    border-left: 4px solid var(--success);
}

.alert-error {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
    border-left: 4px solid var(--danger);
}

/* Form Section */
.form-section {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: var(--border-radius);
    padding: 30px;
    box-shadow: var(--shadow-soft);
    margin-bottom: 30px;
    transition: var(--transition);
}

.form-section:hover {
    box-shadow: var(--shadow-hover);
}

.form-section h2 {
    font-size: 1.8rem;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 20px;
    border-bottom: 2px solid var(--primary);
    padding-bottom: 10px;
}

.asset-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.form-group {
    flex: 1;
    min-width: 200px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
}

.form-group input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e5e7eb;
    border-radius: var(--border-radius);
    font-size: 1rem;
    transition: var(--transition);
    background: #fff;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: var(--border-radius);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: var(--shadow-soft);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.btn-secondary {
    background: linear-gradient(135deg, #6b7280, #9ca3af);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-secondary:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-soft);
}

/* Table Section */
.table-wrapper {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-soft);
    transition: var(--transition);
}

.table-wrapper:hover {
    box-shadow: var(--shadow-hover);
}

.responsive-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.responsive-table th,
.responsive-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.responsive-table th {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.responsive-table tbody tr {
    transition: var(--transition);
}

.responsive-table tbody tr:hover {
    background: rgba(22, 163, 74, 0.05);
}

.responsive-table tbody tr:last-child td {
    border-bottom: none;
}

.responsive-table img {
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-soft);
}

/* Responsive Design */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 20px;
    }

    .form-row {
        flex-direction: column;
    }

    .responsive-table {
        font-size: 0.8rem;
    }

    .responsive-table th,
    .responsive-table td {
        padding: 10px;
    }

    .header h1 {
        font-size: 2rem;
    }

}

</style>
</head>
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
