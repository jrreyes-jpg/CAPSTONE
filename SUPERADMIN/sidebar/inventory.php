<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_role('super_admin');

function determine_inventory_status_for_page(int $quantity, ?int $minStock): string {
    if ($quantity <= 0) {
        return 'out-of-stock';
    }

    if ($minStock !== null && $quantity <= $minStock) {
        return 'low-stock';
    }

    return 'available';
}

function redirect_inventory_page(): void {
    header('Location: /CAPSTONE/SUPERADMIN/sidebar/inventory.php');
    exit();
}

function set_inventory_flash(string $type, string $message): void {
    $_SESSION['inventory_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_inventory_item') {
        $assetId = (int)($_POST['asset_id'] ?? 0);
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        $minStockRaw = trim((string)($_POST['min_stock'] ?? ''));
        $minStock = $minStockRaw === '' ? null : max(0, (int)$minStockRaw);

        if ($assetId <= 0) {
            set_inventory_flash('error', 'Select an asset first.');
            redirect_inventory_page();
        }

        $existingStmt = $conn->prepare('SELECT id FROM inventory WHERE asset_id = ? LIMIT 1');
        if (!$existingStmt) {
            set_inventory_flash('error', 'Failed to validate inventory item.');
            redirect_inventory_page();
        }

        $existingStmt->bind_param('i', $assetId);
        $existingStmt->execute();
        $existingResult = $existingStmt->get_result();
        if ($existingResult && $existingResult->fetch_assoc()) {
            set_inventory_flash('error', 'That asset already has an inventory record.');
            redirect_inventory_page();
        }

        $status = determine_inventory_status_for_page($quantity, $minStock);
        $insertStmt = $conn->prepare('INSERT INTO inventory (asset_id, quantity, min_stock, status) VALUES (?, ?, ?, ?)');

        if (
            $insertStmt &&
            $insertStmt->bind_param('iiis', $assetId, $quantity, $minStock, $status) &&
            $insertStmt->execute()
        ) {
            set_inventory_flash('success', 'Inventory item created successfully.');
        } else {
            set_inventory_flash('error', 'Failed to create inventory item.');
        }

        redirect_inventory_page();
    }

    if ($action === 'update_inventory_item') {
        $inventoryId = (int)($_POST['inventory_id'] ?? 0);
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        $minStockRaw = trim((string)($_POST['min_stock'] ?? ''));
        $minStock = $minStockRaw === '' ? null : max(0, (int)$minStockRaw);

        if ($inventoryId <= 0) {
            set_inventory_flash('error', 'Invalid inventory item.');
            redirect_inventory_page();
        }

        $status = determine_inventory_status_for_page($quantity, $minStock);
        $updateStmt = $conn->prepare(
            'UPDATE inventory
             SET quantity = ?, min_stock = ?, status = ?
             WHERE id = ?'
        );

        if (
            $updateStmt &&
            $updateStmt->bind_param('iisi', $quantity, $minStock, $status, $inventoryId) &&
            $updateStmt->execute()
        ) {
            set_inventory_flash('success', 'Inventory item updated successfully.');
        } else {
            set_inventory_flash('error', 'Failed to update inventory item.');
        }

        redirect_inventory_page();
    }
}

$flash = $_SESSION['inventory_flash'] ?? null;
unset($_SESSION['inventory_flash']);

$assetsWithoutInventory = [];
$assetsWithoutInventoryResult = $conn->query(
    "SELECT a.id, a.asset_name, a.asset_type, a.serial_number
     FROM assets a
     LEFT JOIN inventory i ON i.asset_id = a.id
     WHERE i.id IS NULL
     ORDER BY a.asset_name ASC, a.id ASC"
);
if ($assetsWithoutInventoryResult) {
    $assetsWithoutInventory = $assetsWithoutInventoryResult->fetch_all(MYSQLI_ASSOC);
}

$inventoryItems = [];
$inventoryResult = $conn->query(
    "SELECT
        i.id,
        i.asset_id,
        i.quantity,
        i.min_stock,
        i.status,
        i.updated_at,
        a.asset_name,
        a.asset_type,
        a.serial_number
     FROM inventory i
     INNER JOIN assets a ON a.id = i.asset_id
     ORDER BY a.asset_name ASC, i.id ASC"
);
if ($inventoryResult) {
    $inventoryItems = $inventoryResult->fetch_all(MYSQLI_ASSOC);
}

$totalInventoryItems = count($inventoryItems);
$totalUnits = 0;
$lowStockItems = 0;
$outOfStockItems = 0;

foreach ($inventoryItems as $item) {
    $totalUnits += (int)$item['quantity'];
    if (($item['status'] ?? '') === 'low-stock') {
        $lowStockItems++;
    }
    if (($item['status'] ?? '') === 'out-of-stock') {
        $outOfStockItems++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Super Admin</title>
    <link rel="stylesheet" href="../css/admin-dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../sidebar_super_admin.php'; ?>

    <main class="main-content">
        <div class="page-stack">
            <section class="metrics-grid">
                <div class="metric-card">
                    <span>Inventory Items</span>
                    <strong><?php echo $totalInventoryItems; ?></strong>
                </div>
                <div class="metric-card">
                    <span>Total Units</span>
                    <strong><?php echo $totalUnits; ?></strong>
                </div>
                <div class="metric-card">
                    <span>Low Stock</span>
                    <strong><?php echo $lowStockItems; ?></strong>
                </div>
                <div class="metric-card">
                    <span>Out of Stock</span>
                    <strong><?php echo $outOfStockItems; ?></strong>
                </div>
            </section>

            <?php if ($flash): ?>
                <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <section class="form-panel">
                <h2 class="section-title-inline">Add Inventory Record</h2>

                <?php if (empty($assetsWithoutInventory)): ?>
                    <div class="empty-state">All assets already have inventory records.</div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_inventory_item">
                        <div class="form-grid">
                            <div class="input-group">
                                <label for="asset_id">Asset</label>
                                <select id="asset_id" name="asset_id" required>
                                    <option value="">Select asset</option>
                                    <?php foreach ($assetsWithoutInventory as $asset): ?>
                                        <option value="<?php echo (int)$asset['id']; ?>">
                                            <?php
                                            $label = $asset['asset_name'];
                                            if (!empty($asset['asset_type'])) {
                                                $label .= ' - ' . $asset['asset_type'];
                                            }
                                            if (!empty($asset['serial_number'])) {
                                                $label .= ' (SN: ' . $asset['serial_number'] . ')';
                                            }
                                            echo htmlspecialchars($label);
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="input-group">
                                <label for="quantity">Quantity</label>
                                <input type="number" id="quantity" name="quantity" min="0" step="1" value="0" required>
                            </div>

                            <div class="input-group">
                                <label for="min_stock">Min Stock</label>
                                <input type="number" id="min_stock" name="min_stock" min="0" step="1" placeholder="Optional">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Create Inventory Record</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="page-stack">
                <h2 class="section-title-inline">Inventory Items</h2>

                <?php if (empty($inventoryItems)): ?>
                    <div class="empty-state">No inventory records yet.</div>
                <?php else: ?>
                    <div class="projects-grid">
                        <?php foreach ($inventoryItems as $item): ?>
                            <article class="project-card">
                                <div class="card-split">
                                    <div>
                                        <h3><?php echo htmlspecialchars($item['asset_name']); ?></h3>
                                        <div class="status-pill-wrap">
                                            <span class="status-pill status-<?php echo htmlspecialchars($item['status']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($item['status'])); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="project-meta">
                                        <div><strong>Type:</strong> <?php echo htmlspecialchars($item['asset_type'] ?: 'N/A'); ?></div>
                                        <div><strong>Serial:</strong> <?php echo htmlspecialchars($item['serial_number'] ?: 'N/A'); ?></div>
                                        <div><strong>Available Qty:</strong> <?php echo (int)$item['quantity']; ?></div>
                                        <div><strong>Min Stock:</strong> <?php echo $item['min_stock'] !== null ? (int)$item['min_stock'] : 'Not set'; ?></div>
                                        <div><strong>Updated:</strong> <?php echo htmlspecialchars($item['updated_at']); ?></div>
                                    </div>
                                </div>

                                <form method="POST" class="mini-form">
                                    <input type="hidden" name="action" value="update_inventory_item">
                                    <input type="hidden" name="inventory_id" value="<?php echo (int)$item['id']; ?>">

                                    <h4 class="subheading">Update Stock</h4>
                                    <div class="form-grid">
                                        <div class="input-group">
                                            <label for="quantity-<?php echo (int)$item['id']; ?>">Quantity</label>
                                            <input type="number" id="quantity-<?php echo (int)$item['id']; ?>" name="quantity" min="0" step="1" value="<?php echo (int)$item['quantity']; ?>" required>
                                        </div>

                                        <div class="input-group">
                                            <label for="min_stock-<?php echo (int)$item['id']; ?>">Min Stock</label>
                                            <input type="number" id="min_stock-<?php echo (int)$item['id']; ?>" name="min_stock" min="0" step="1" value="<?php echo $item['min_stock'] !== null ? (int)$item['min_stock'] : ''; ?>">
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit" class="btn-primary">Save Stock</button>
                                    </div>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
<script src="../js/admin-script.js"></script>
</body>
</html>
