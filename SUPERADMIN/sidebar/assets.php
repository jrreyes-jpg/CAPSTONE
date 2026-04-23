<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/audit_log.php';
require_once __DIR__ . '/../../config/asset_unit_helpers.php';

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

function buildAssetSerialNumber(int $assetId, string $assetType = ''): string {
    $cleanType = preg_replace('/[^A-Za-z0-9]+/', '', strtoupper($assetType));
    $typePrefix = $cleanType !== '' ? substr($cleanType, 0, 3) : 'AST';

    return sprintf('%s-%s-%04d', $typePrefix, date('Y'), $assetId);
}

function buildAssetQrValue(int $assetId, string $serialNumber = ''): string {
    $parts = ['asset_id=' . $assetId];

    if ($serialNumber !== '') {
        $parts[] = 'sn=' . $serialNumber;
    }

    return implode('|', $parts);
}

function assets_table_has_column(mysqli $conn, string $columnName): bool {
    $stmt = $conn->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = ?
         AND column_name = ?'
    );

    if (!$stmt) {
        return false;
    }

    $tableName = 'assets';
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return (int)$count > 0;
}

function ensure_assets_classification_columns(mysqli $conn): void {
    if (!assets_table_has_column($conn, 'asset_category')) {
        $conn->query("ALTER TABLE assets ADD COLUMN asset_category VARCHAR(80) DEFAULT NULL AFTER asset_name");
    }

    if (!assets_table_has_column($conn, 'criticality')) {
        $conn->query("ALTER TABLE assets ADD COLUMN criticality VARCHAR(30) DEFAULT NULL AFTER asset_status");
    }
}

function asset_category_seed_defaults(): array {
    return [
        'tool' => ['label' => 'Tool', 'min_stock' => 1, 'description' => 'Reusable hand tools and power tools.', 'sort_order' => 10],
        'equipment' => ['label' => 'Equipment', 'min_stock' => 1, 'description' => 'Heavy or specialized operational equipment.', 'sort_order' => 20],
        'it_device' => ['label' => 'IT Device', 'min_stock' => 1, 'description' => 'Laptops, tablets, routers, cameras, and other IT hardware.', 'sort_order' => 30],
        'vehicle' => ['label' => 'Vehicle', 'min_stock' => 0, 'description' => 'Service vehicles and transport assets.', 'sort_order' => 40],
        'ppe' => ['label' => 'PPE', 'min_stock' => 10, 'description' => 'Safety gear issued to workers.', 'sort_order' => 50],
        'consumable' => ['label' => 'Consumable', 'min_stock' => 20, 'description' => 'Items that are used up and not returned.', 'sort_order' => 60],
        'electrical_material' => ['label' => 'Electrical Material', 'min_stock' => 10, 'description' => 'Wires, breakers, relays, conduits, and similar materials.', 'sort_order' => 70],
        'mechanical_material' => ['label' => 'Mechanical Material', 'min_stock' => 10, 'description' => 'Pipes, fittings, valves, and other mechanical materials.', 'sort_order' => 80],
        'spare_part' => ['label' => 'Spare Part', 'min_stock' => 2, 'description' => 'Replacement parts for repair and maintenance.', 'sort_order' => 90],
        'office_supply' => ['label' => 'Office Supply', 'min_stock' => 5, 'description' => 'Administrative and office-use supplies.', 'sort_order' => 100],
        'measuring_device' => ['label' => 'Measuring Device', 'min_stock' => 1, 'description' => 'Meters, testers, and calibration-sensitive devices.', 'sort_order' => 110],
    ];
}

function ensure_asset_category_defaults_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS asset_category_defaults (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            category_key VARCHAR(80) NOT NULL UNIQUE,
            category_label VARCHAR(120) NOT NULL,
            recommended_min_stock INT(11) NOT NULL DEFAULT 0,
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT(11) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_asset_category_defaults_active_sort (is_active, sort_order, category_label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function seed_asset_category_defaults(mysqli $conn): void {
    $seedDefaults = asset_category_seed_defaults();
    $statement = $conn->prepare(
        "INSERT INTO asset_category_defaults (
            category_key,
            category_label,
            recommended_min_stock,
            description,
            is_active,
            sort_order
        ) VALUES (?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            category_label = VALUES(category_label),
            recommended_min_stock = VALUES(recommended_min_stock),
            description = COALESCE(asset_category_defaults.description, VALUES(description)),
            sort_order = VALUES(sort_order)"
    );

    if (!$statement) {
        return;
    }

    foreach ($seedDefaults as $categoryKey => $meta) {
        $categoryLabel = (string)($meta['label'] ?? $categoryKey);
        $recommendedMinStock = (int)($meta['min_stock'] ?? 0);
        $description = (string)($meta['description'] ?? '');
        $sortOrder = (int)($meta['sort_order'] ?? 0);
        $statement->bind_param('ssisi', $categoryKey, $categoryLabel, $recommendedMinStock, $description, $sortOrder);
        $statement->execute();
    }
}

function fetch_asset_category_defaults(mysqli $conn): array {
    $rows = [];
    $result = $conn->query(
        "SELECT
            id,
            category_key,
            category_label,
            recommended_min_stock,
            description,
            is_active,
            sort_order
         FROM asset_category_defaults
         ORDER BY is_active DESC, sort_order ASC, category_label ASC"
    );

    if ($result) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
    }

    return $rows;
}

function asset_category_defaults_index(array $rows): array {
    $indexed = [];

    foreach ($rows as $row) {
        $categoryKey = trim((string)($row['category_key'] ?? ''));
        if ($categoryKey === '') {
            continue;
        }

        $indexed[$categoryKey] = [
            'id' => (int)($row['id'] ?? 0),
            'label' => (string)($row['category_label'] ?? $categoryKey),
            'min_stock' => (int)($row['recommended_min_stock'] ?? 0),
            'description' => (string)($row['description'] ?? ''),
            'is_active' => (int)($row['is_active'] ?? 1) === 1,
            'sort_order' => (int)($row['sort_order'] ?? 0),
        ];
    }

    return $indexed;
}

function active_asset_category_defaults(array $rows): array {
    return array_values(array_filter($rows, static fn(array $row): bool => (int)($row['is_active'] ?? 1) === 1));
}

function criticality_options(): array {
    return [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ];
}

function asset_category_label(?string $category): string {
    $category = trim((string)$category);
    global $assetCategoryDefaults;
    $defaults = $assetCategoryDefaults;

    if ($category !== '' && isset($defaults[$category]['label'])) {
        return (string)$defaults[$category]['label'];
    }

    return $category !== '' ? ucwords(str_replace('_', ' ', $category)) : 'Uncategorized';
}

function asset_criticality_label(?string $criticality): string {
    $criticality = trim((string)$criticality);
    $options = criticality_options();

    if ($criticality !== '' && isset($options[$criticality])) {
        return $options[$criticality];
    }

    return $criticality !== '' ? ucfirst($criticality) : 'Medium';
}

function determine_asset_inventory_status(int $quantity, ?int $minStock): string {
    if ($quantity <= 0) {
        return 'out-of-stock';
    }

    if ($minStock !== null && $quantity <= $minStock) {
        return 'low-stock';
    }

    return 'available';
}

function build_asset_stock_badge_label(?string $status): string {
    $normalized = trim((string)$status);
    if ($normalized === '') {
        return 'No inventory';
    }

    if ($normalized === 'out-of-stock') {
        return 'Out of Stock';
    }

    if ($normalized === 'low-stock') {
        return 'Low Stock';
    }

    if ($normalized === 'available') {
        return 'Available';
    }

    return ucwords(str_replace('-', ' ', $normalized));
}

ensure_asset_unit_tracking_schema($conn);
ensure_assets_classification_columns($conn);
ensure_asset_category_defaults_table($conn);
seed_asset_category_defaults($conn);
$assetCategoryDefaultRows = fetch_asset_category_defaults($conn);
$assetCategoryDefaults = asset_category_defaults_index($assetCategoryDefaultRows);
$activeAssetCategoryDefaultRows = active_asset_category_defaults($assetCategoryDefaultRows);
$criticalityChoices = criticality_options();

function syncAssetIdentity(mysqli $conn, array $asset): array {
    $assetId = (int)($asset['id'] ?? 0);
    if ($assetId <= 0) {
        return $asset;
    }

    $assetType = trim((string)($asset['asset_type'] ?? ''));
    $serialNumber = trim((string)($asset['serial_number'] ?? ''));

    if ($serialNumber === '') {
        $serialNumber = buildAssetSerialNumber($assetId, $assetType);
        $serialStmt = $conn->prepare('UPDATE assets SET serial_number = ? WHERE id = ?');
        if ($serialStmt) {
            $serialStmt->bind_param('si', $serialNumber, $assetId);
            $serialStmt->execute();
            $serialStmt->close();
        }
        $asset['serial_number'] = $serialNumber;
    }

    $qrValue = trim((string)($asset['qr_code_value'] ?? ''));
    if ($qrValue === '') {
        $qrValue = buildAssetQrValue($assetId, $serialNumber);
        $qrStmt = $conn->prepare('INSERT INTO asset_qr_codes (asset_id, qr_code_value) VALUES (?, ?)');
        if ($qrStmt) {
            $qrStmt->bind_param('is', $assetId, $qrValue);
            $qrStmt->execute();
            $qrStmt->close();
        }
        $asset['qr_code_value'] = $qrValue;
    }

    return $asset;
}

function getAssetSnapshot(mysqli $conn, int $assetId): ?array {
    $stmt = $conn->prepare(
        'SELECT a.id, a.asset_name, a.asset_category, a.asset_type, a.serial_number, a.asset_status, a.criticality, q.id AS qr_id
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

    if ($action === 'save_asset_category_default') {
        $categoryDefaultId = (int)($_POST['category_default_id'] ?? 0);
        $categoryLabel = trim((string)($_POST['category_label'] ?? ''));
        $recommendedMinStock = max(0, (int)($_POST['recommended_min_stock'] ?? 0));
        $description = trim((string)($_POST['description'] ?? ''));
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($categoryDefaultId <= 0 || $categoryLabel === '') {
            $_SESSION['assets_error'] = 'Category label is required.';
            header('Location: /codesamplecaps/SUPERADMIN/sidebar/assets.php');
            exit();
        }

        $updateDefault = $conn->prepare(
            'UPDATE asset_category_defaults
             SET category_label = ?, recommended_min_stock = ?, description = ?, is_active = ?, sort_order = ?
             WHERE id = ?'
        );

        if (
            $updateDefault &&
            $updateDefault->bind_param('sisiii', $categoryLabel, $recommendedMinStock, $description, $isActive, $sortOrder, $categoryDefaultId) &&
            $updateDefault->execute()
        ) {
            $_SESSION['assets_message'] = 'Asset category defaults updated.';
        } else {
            $_SESSION['assets_error'] = 'Failed to update asset category defaults.';
        }

        header('Location: /codesamplecaps/SUPERADMIN/sidebar/assets.php');
        exit();
    }

    if ($action === 'create_asset') {
        $assetName = trim($_POST['asset_name'] ?? '');
        $assetCategory = trim((string)($_POST['asset_category'] ?? ''));
        $assetType = trim($_POST['asset_type'] ?? '');
        $criticality = trim((string)($_POST['criticality'] ?? 'medium'));
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        $minStockRaw = trim((string)($_POST['min_stock'] ?? ''));
        $minStock = $minStockRaw === '' ? null : max(0, (int)$minStockRaw);

        if (!isset($assetCategoryDefaults[$assetCategory]) || empty($assetCategoryDefaults[$assetCategory]['is_active'])) {
            $assetCategory = 'tool';
        }

        if (!isset($criticalityChoices[$criticality])) {
            $criticality = 'medium';
        }

        if ($minStock === null && isset($assetCategoryDefaults[$assetCategory]['min_stock'])) {
            $minStock = (int)$assetCategoryDefaults[$assetCategory]['min_stock'];
        }

        if ($assetName === '') {
            $_SESSION['assets_error'] = 'Asset name is required.';
        } else {
            $emptySerial = '';
            $inventoryStatus = determine_asset_inventory_status($quantity, $minStock);

            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare('INSERT INTO assets (asset_name, asset_category, asset_type, serial_number, criticality) VALUES (?, ?, ?, ?, ?)');
                if (
                    !$stmt ||
                    !$stmt->bind_param('sssss', $assetName, $assetCategory, $assetType, $emptySerial, $criticality) ||
                    !$stmt->execute()
                ) {
                    throw new RuntimeException('Failed to create asset.');
                }

                $assetId = (int)$stmt->insert_id;
                $serial = buildAssetSerialNumber($assetId, $assetType);
                $qrValue = buildAssetQrValue($assetId, $serial);

                $serialStmt = $conn->prepare('UPDATE assets SET serial_number = ? WHERE id = ?');
                if (
                    !$serialStmt ||
                    !$serialStmt->bind_param('si', $serial, $assetId) ||
                    !$serialStmt->execute()
                ) {
                    throw new RuntimeException('Failed to save the asset serial number.');
                }

                $qrStmt = $conn->prepare('INSERT INTO asset_qr_codes (asset_id, qr_code_value) VALUES (?, ?)');
                if (
                    !$qrStmt ||
                    !$qrStmt->bind_param('is', $assetId, $qrValue) ||
                    !$qrStmt->execute()
                ) {
                    throw new RuntimeException('Failed to save the asset QR code.');
                }

                $inventoryStmt = $conn->prepare(
                    'INSERT INTO inventory (asset_id, quantity, min_stock, status)
                     VALUES (?, ?, ?, ?)'
                );
                if (
                    !$inventoryStmt ||
                    !$inventoryStmt->bind_param('iiis', $assetId, $quantity, $minStock, $inventoryStatus) ||
                    !$inventoryStmt->execute()
                ) {
                    throw new RuntimeException('Failed to create the inventory record.');
                }

                $inventoryId = (int)$inventoryStmt->insert_id;
                asset_units_sync_for_inventory($conn, $inventoryId, $quantity);

                audit_log_event(
                    $conn,
                    (int)($_SESSION['user_id'] ?? 0),
                    'create_asset',
                    'asset',
                    $assetId,
                    null,
                    [
                        'asset_name' => $assetName,
                        'asset_category' => $assetCategory,
                        'asset_type' => $assetType,
                        'serial_number' => $serial,
                        'criticality' => $criticality,
                        'quantity' => $quantity,
                        'min_stock' => $minStock,
                        'inventory_status' => $inventoryStatus,
                    ]
                );

                audit_log_event(
                    $conn,
                    (int)($_SESSION['user_id'] ?? 0),
                    'create_inventory_item',
                    'inventory',
                    $inventoryId,
                    null,
                    [
                        'asset_id' => $assetId,
                        'asset_name' => $assetName,
                        'quantity' => $quantity,
                        'min_stock' => $minStock,
                        'status' => $inventoryStatus,
                    ]
                );

                $conn->commit();

                $_SESSION['assets_message'] = 'Asset created with inventory and QR generated.';
                $_SESSION['assets_qr_preview'] = $qrValue;
                $_SESSION['assets_created_asset_id'] = $assetId;
            } catch (Throwable $exception) {
                $conn->rollback();
                $_SESSION['assets_error'] = $exception->getMessage();
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

$assets = [];
$assetQuery = 'SELECT
    a.*,
    i.id AS inventory_id,
    i.quantity AS inventory_quantity,
    i.min_stock AS inventory_min_stock,
    i.status AS inventory_status,
    (
        SELECT q.qr_code_value
        FROM asset_qr_codes q
        WHERE q.asset_id = a.id
        ORDER BY q.id DESC
        LIMIT 1
    ) AS qr_code_value
    FROM assets a
    LEFT JOIN inventory i ON i.asset_id = a.id';
$assetQuery .= ' ORDER BY a.created_at DESC';
$result = $conn->query($assetQuery);

if ($result) {
    $assets = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($assets as $index => $asset) {
        $assets[$index] = syncAssetIdentity($conn, $asset);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets & QR Codes - Super Admin</title>
    <link rel="stylesheet" href="../css/super_admin_dashboard.css">
    <link rel="stylesheet" href="../css/assets.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../super_admin_sidebar.php'; ?>

    <main class="main-content assets-content">
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
                        <label for="asset_category">Asset Category</label>
                        <select id="asset_category" name="asset_category" data-default-min-stock-target="min_stock">
                            <?php foreach ($activeAssetCategoryDefaultRows as $categoryRow): ?>
                                <option
                                    value="<?php echo htmlspecialchars((string)($categoryRow['category_key'] ?? '')); ?>"
                                    data-default-min-stock="<?php echo (int)($categoryRow['recommended_min_stock'] ?? 0); ?>"
                                    <?php echo (string)($categoryRow['category_key'] ?? '') === 'tool' ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars((string)($categoryRow['category_label'] ?? ($categoryRow['category_key'] ?? 'Category'))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="criticality">Criticality</label>
                        <select id="criticality" name="criticality">
                            <?php foreach ($criticalityChoices as $criticalityValue => $criticalityLabel): ?>
                                <option value="<?php echo htmlspecialchars($criticalityValue); ?>" <?php echo $criticalityValue === 'medium' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($criticalityLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="0" step="1" value="0" required>
                    </div>
                    <div class="form-group">
                        <label for="min_stock">Min Stock</label>
                        <input type="number" id="min_stock" name="min_stock" min="0" step="1" value="<?php echo (int)($assetCategoryDefaults['tool']['min_stock'] ?? 0); ?>" placeholder="Auto-filled by category">
                    </div>
                </div>
                <button type="submit" class="btn-primary">Create Asset + Inventory + QR</button>
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
            <div class="table-wrapper">
                <table class="responsive-table mobile-card-table assets-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Asset</th>
                            <th>Status</th>
                            <th>QR Code</th>
                            <th>Created On</th>
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
                                        <small>Category: <?php echo htmlspecialchars(asset_category_label($asset['asset_category'] ?? null)); ?></small><br>
                                        <small><?php echo htmlspecialchars($asset['asset_type'] ?: 'No type'); ?></small><br>
                                        <small>SN: <?php echo htmlspecialchars($asset['serial_number'] ?: 'Generating...'); ?></small>
                                    </td>
                                    <td data-label="Status">
                                        <span class="asset-status asset-status--<?php echo htmlspecialchars($asset['asset_status']); ?>">
                                            <?php echo htmlspecialchars($asset['asset_status']); ?>
                                        </span>
                                        <br>
                                        <span class="status-pill status-<?php echo htmlspecialchars((string)($asset['inventory_status'] ?? 'available')); ?>">
                                            <?php echo htmlspecialchars(build_asset_stock_badge_label($asset['inventory_status'] ?? null)); ?>
                                        </span>
                                        <br>
                                        <small>
                                            Qty: <?php echo (int)($asset['inventory_quantity'] ?? 0); ?>
                                            |
                                            Min: <?php echo $asset['inventory_min_stock'] !== null ? (int)$asset['inventory_min_stock'] : 'N/A'; ?>
                                        </small>
                                        <br>
                                        <small>Criticality: <?php echo htmlspecialchars(asset_criticality_label($asset['criticality'] ?? null)); ?></small>
                                    </td>
                                    <td data-label="QR Code">
                                        <div class="asset-qr-actions">
                                            <?php if (!empty($asset['qr_code_value'])): ?>
                                                <?php if ($qrLibraryReady): ?>
                                                    <?php
                                                    $qrValue = $asset['qr_code_value'] ?: buildAssetQrValue((int)$asset['id'], (string)($asset['serial_number'] ?? ''));
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
                                                <button type="submit" class="btn-danger">Delete Asset</button>
                                            </form>

                                            <?php if ($asset['asset_status'] === 'in_use'): ?>
                                                <form method="POST" class="asset-inline-form">
                                                    <input type="hidden" name="action" value="return_asset">
                                                    <input type="hidden" name="asset_id" value="<?php echo $asset['id']; ?>">
                                                    <button type="submit" class="btn-secondary">Mark Returned</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="asset-inline-note">Ready to use</span>
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

<script src="../js/super_admin_dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const categoryField = document.getElementById('asset_category');
    const minStockField = document.getElementById('min_stock');

    if (!categoryField || !minStockField) {
        return;
    }

    categoryField.addEventListener('change', () => {
        const selectedOption = categoryField.options[categoryField.selectedIndex];
        if (!selectedOption) {
            return;
        }

        const recommendedMinStock = selectedOption.getAttribute('data-default-min-stock');
        if (recommendedMinStock === null) {
            return;
        }

        minStockField.value = recommendedMinStock;
    });
});
</script>
</body>
</html>
