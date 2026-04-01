<?php
require_once __DIR__ . '/../config/auth_middleware.php';
require_once __DIR__ . '/../config/database.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

require_role('super_admin');

$qrLibraryReady = false;
$qrAutoloadPath = __DIR__ . '/../vendor/autoload.php';
$qrRequiredFiles = [
    __DIR__ . '/../vendor/symfony/polyfill-ctype/bootstrap.php',
    __DIR__ . '/../vendor/chillerlan/php-qrcode/src/QRCode.php',
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
        $qrLibraryReady = class_exists(QRCode::class) && class_exists(QROptions::class);
    }
}

function generateQRDataUri(string $value): string {
    global $qrLibraryReady;

    if (!$qrLibraryReady) {
        return '';
    }

    $options = new QROptions([
        'outputType' => 'png',
        'scale' => 8
    ]);

    return (new QRCode($options))->render($value);
}   
$assetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;

$sql = 'SELECT a.id, a.asset_name, a.asset_type, a.serial_number, a.asset_status, q.qr_code_value
        FROM assets a
        LEFT JOIN asset_qr_codes q ON q.asset_id = a.id';
$params = [];
if ($assetId > 0) {
    $sql .= ' WHERE a.id = ?';
    $params[] = $assetId;
}
$sql .= ' GROUP BY a.id ORDER BY a.id DESC';

$stmt = $conn->prepare($sql);
if ($assetId > 0) {
    $stmt->bind_param('i', $assetId);
}
$stmt->execute();
$result = $stmt->get_result();
$assets = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Codes - Edge Automation</title>
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f6f8; margin: 0; padding: 20px; }
        .page { max-width: 900px; margin: 0 auto; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
        .header h1 { margin: 0; font-size: 20px; }
        .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #2d3a56; color: #fff; }
        .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 18px; }
        .card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 12px 30px rgba(0,0,0,0.08); }
        .card h3 { margin: 0 0 6px; font-size: 16px; }
        .card p { margin: 4px 0; font-size: 13px; color: #444; }
        .card .qr { width: 130px; height: 130px; margin: 10px auto; }
        @media print {
            body { padding: 0; }
            .header { display: none; }
            .card { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <h1>Print QR Codes</h1>
        <button class="btn btn-primary" onclick="window.print();">Print / Save as PDF</button>
    </div>
    <?php if (!$qrLibraryReady): ?>
        <div style="margin-bottom:16px; padding:12px 14px; border-radius:10px; background:#fee2e2; color:#991b1b;">
            QR printing is unavailable because Composer packages are incomplete in vendor.
        </div>
    <?php endif; ?>

    <div class="cards">
        <?php if (count($assets) === 0): ?>
            <div>No assets found.</div>
        <?php endif; ?>

        <?php foreach ($assets as $asset): ?>
            <?php
                $qrValue = "asset_id=".$asset['id'];
                $qrDataUri = $qrLibraryReady ? generateQRDataUri($qrValue) : '';
            ?>
            <div class="card">
                <div class="qr">
                    <?php if ($qrLibraryReady): ?>
                        <img src="<?php echo $qrDataUri; ?>" alt="QR code" style="width:100%; height:auto; display:block;">
                    <?php else: ?>
                        <div style="display:flex; align-items:center; justify-content:center; height:100%; text-align:center; font-size:12px; color:#666;">QR unavailable</div>
                    <?php endif; ?>
                </div>
                <h3><?php echo htmlspecialchars($asset['asset_name']); ?> (ID <?php echo $asset['id']; ?>)</h3>
                <p>Type: <?php echo htmlspecialchars($asset['asset_type'] ?: '-'); ?></p>
                <p>Status: <?php echo htmlspecialchars($asset['asset_status']); ?></p>
                <p style="font-size: 12px; color:#555;">Scan value: <code><?php echo htmlspecialchars($qrValue); ?></code></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
