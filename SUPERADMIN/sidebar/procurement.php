<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/audit_log.php';

require_role('super_admin');

function procurement_redirect(): void {
    header('Location: /codesamplecaps/SUPERADMIN/sidebar/procurement.php');
    exit();
}

function procurement_set_flash(string $type, string $message): void {
    $_SESSION['procurement_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function procurement_normalize_text(?string $value): string {
    return trim((string)$value);
}

function procurement_normalize_text_or_null(?string $value): ?string {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function procurement_normalize_phone(?string $value): ?string {
    $value = preg_replace('/[^0-9+]/', '', trim((string)$value));
    return $value === '' ? null : $value;
}

function procurement_normalize_money_or_null($value): ?float {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $normalized = str_ireplace(['PHP', 'P'], '', $value);
    $normalized = str_replace([',', ' '], '', $normalized);
    if (!is_numeric($normalized)) {
        return null;
    }

    return round((float)$normalized, 2);
}

function procurement_generate_code(string $prefix): string {
    return $prefix . '-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function procurement_status_class(string $status): string {
    return 'status-' . str_replace('_', '-', trim($status));
}

function procurement_ensure_suppliers_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS suppliers (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            supplier_code VARCHAR(80) NOT NULL UNIQUE,
            supplier_name VARCHAR(190) NOT NULL,
            contact_person VARCHAR(150) DEFAULT NULL,
            contact_number VARCHAR(50) DEFAULT NULL,
            email VARCHAR(190) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            description TEXT DEFAULT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_suppliers_status_name (status, supplier_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function procurement_ensure_supplier_description_column(mysqli $conn): void {
    $columnCheck = $conn->query("SHOW COLUMNS FROM suppliers LIKE 'description'");
    if (!$columnCheck || (int)$columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE suppliers ADD COLUMN description TEXT DEFAULT NULL AFTER address");
    }
}

function procurement_ensure_purchase_requests_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS purchase_requests (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_no VARCHAR(80) NOT NULL UNIQUE,
            project_id INT(11) NOT NULL,
            requested_by INT(11) NOT NULL,
            needed_date DATE DEFAULT NULL,
            request_type ENUM('material', 'tool', 'equipment', 'service') NOT NULL DEFAULT 'material',
            site_location VARCHAR(190) DEFAULT NULL,
            remarks TEXT DEFAULT NULL,
            status ENUM('draft', 'submitted', 'engineer_review', 'engineer_approved', 'engineer_rejected', 'approved', 'cancelled') NOT NULL DEFAULT 'submitted',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_purchase_requests_project_status (project_id, status),
            KEY idx_purchase_requests_requested_by (requested_by),
            CONSTRAINT fk_purchase_requests_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
            CONSTRAINT fk_purchase_requests_requested_by FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function procurement_ensure_purchase_request_review_columns(mysqli $conn): void {
    $columnCheck = $conn->query("SHOW COLUMNS FROM purchase_requests LIKE 'engineer_reviewed_by'");
    if (!$columnCheck || (int)$columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE purchase_requests ADD COLUMN engineer_reviewed_by INT(11) DEFAULT NULL AFTER requested_by");
        $conn->query("ALTER TABLE purchase_requests ADD COLUMN engineer_review_notes TEXT DEFAULT NULL AFTER remarks");
        $conn->query("ALTER TABLE purchase_requests ADD COLUMN engineer_reviewed_at DATETIME DEFAULT NULL AFTER engineer_review_notes");
        $conn->query("ALTER TABLE purchase_requests ADD CONSTRAINT fk_purchase_requests_engineer_reviewed_by FOREIGN KEY (engineer_reviewed_by) REFERENCES users (id) ON DELETE SET NULL");
    }
}

function procurement_ensure_purchase_request_items_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS purchase_request_items (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            purchase_request_id INT(11) NOT NULL,
            item_description VARCHAR(255) NOT NULL,
            specification VARCHAR(255) DEFAULT NULL,
            unit VARCHAR(40) NOT NULL,
            quantity_requested DECIMAL(12,2) NOT NULL,
            estimated_unit_cost DECIMAL(14,2) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_purchase_request_items_request (purchase_request_id),
            CONSTRAINT fk_purchase_request_items_request FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function procurement_ensure_purchase_orders_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS purchase_orders (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            po_no VARCHAR(80) NOT NULL UNIQUE,
            purchase_request_id INT(11) NOT NULL,
            project_id INT(11) NOT NULL,
            supplier_id INT(11) NOT NULL,
            order_date DATE NOT NULL,
            expected_delivery_date DATE DEFAULT NULL,
            remarks TEXT DEFAULT NULL,
            status ENUM('draft', 'issued', 'partially_delivered', 'delivered', 'cancelled', 'closed') NOT NULL DEFAULT 'issued',
            created_by INT(11) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_purchase_orders_request (purchase_request_id),
            KEY idx_purchase_orders_project_status (project_id, status),
            KEY idx_purchase_orders_supplier (supplier_id),
            CONSTRAINT fk_purchase_orders_request FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests (id) ON DELETE CASCADE,
            CONSTRAINT fk_purchase_orders_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
            CONSTRAINT fk_purchase_orders_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE,
            CONSTRAINT fk_purchase_orders_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function procurement_ensure_purchase_order_items_table(mysqli $conn): void {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS purchase_order_items (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id INT(11) NOT NULL,
            purchase_request_item_id INT(11) DEFAULT NULL,
            item_description VARCHAR(255) NOT NULL,
            specification VARCHAR(255) DEFAULT NULL,
            unit VARCHAR(40) NOT NULL,
            quantity_ordered DECIMAL(12,2) NOT NULL,
            unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_purchase_order_items_order (purchase_order_id),
            CONSTRAINT fk_purchase_order_items_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
            CONSTRAINT fk_purchase_order_items_request_item FOREIGN KEY (purchase_request_item_id) REFERENCES purchase_request_items (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

procurement_ensure_suppliers_table($conn);
procurement_ensure_supplier_description_column($conn);
procurement_ensure_purchase_requests_table($conn);
procurement_ensure_purchase_request_items_table($conn);
procurement_ensure_purchase_request_review_columns($conn);
procurement_ensure_purchase_orders_table($conn);
procurement_ensure_purchase_order_items_table($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_supplier') {
        $supplierName = procurement_normalize_text($_POST['supplier_name'] ?? '');
        $contactPerson = procurement_normalize_text($_POST['contact_person'] ?? '');
        $contactNumber = procurement_normalize_phone($_POST['contact_number'] ?? null);
        $email = procurement_normalize_text_or_null($_POST['email'] ?? null);
        $address = procurement_normalize_text($_POST['address'] ?? '');
        $description = procurement_normalize_text_or_null($_POST['description'] ?? null);

        if ($supplierName === '' || $contactPerson === '' || $contactNumber === null || $address === '') {
            procurement_set_flash('error', 'Supplier name, contact person, contact number, and address are required.');
            procurement_redirect();
        }

        if (strlen($contactNumber) < 7) {
            procurement_set_flash('error', 'Supplier contact number is invalid.');
            procurement_redirect();
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            procurement_set_flash('error', 'Supplier email is invalid.');
            procurement_redirect();
        }

        $supplierCode = procurement_generate_code('SUP');
        $stmt = $conn->prepare(
            'INSERT INTO suppliers (supplier_code, supplier_name, contact_person, contact_number, email, address, description)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        if (
            $stmt &&
            $stmt->bind_param('sssssss', $supplierCode, $supplierName, $contactPerson, $contactNumber, $email, $address, $description) &&
            $stmt->execute()
        ) {
            audit_log_event(
                $conn,
                (int)($_SESSION['user_id'] ?? 0),
                'create_supplier',
                'supplier',
                (int)$stmt->insert_id,
                null,
                [
                    'supplier_code' => $supplierCode,
                    'supplier_name' => $supplierName,
                    'contact_person' => $contactPerson,
                    'description' => $description,
                ]
            );
            procurement_set_flash('success', 'Supplier added.');
        } else {
            procurement_set_flash('error', 'Failed to add supplier.');
        }

        procurement_redirect();
    }

    if ($action === 'create_purchase_request') {
        procurement_set_flash('error', 'Purchase requests should be created by the project team, not by Super Admin.');
        procurement_redirect();
    }

    if ($action === 'create_purchase_order') {
        $purchaseRequestId = (int)($_POST['purchase_request_id'] ?? 0);
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $orderDate = procurement_normalize_text_or_null($_POST['order_date'] ?? null);
        $expectedDeliveryDate = procurement_normalize_text_or_null($_POST['expected_delivery_date'] ?? null);
        $poRemarks = procurement_normalize_text_or_null($_POST['po_remarks'] ?? null);
        $createdBy = (int)($_SESSION['user_id'] ?? 0);

        if ($purchaseRequestId <= 0 || $supplierId <= 0 || $orderDate === null) {
            procurement_set_flash('error', 'Approved request, supplier, and order date are required.');
            procurement_redirect();
        }

        $requestSnapshotStmt = $conn->prepare(
            "SELECT pr.id, pr.request_no, pr.project_id, pr.status, p.project_name
             FROM purchase_requests pr
             INNER JOIN projects p ON p.id = pr.project_id
             WHERE pr.id = ?
             LIMIT 1"
        );

        if (!$requestSnapshotStmt) {
            procurement_set_flash('error', 'Failed to load approved request.');
            procurement_redirect();
        }

        $requestSnapshotStmt->bind_param('i', $purchaseRequestId);
        $requestSnapshotStmt->execute();
        $requestSnapshot = $requestSnapshotStmt->get_result()->fetch_assoc();

        if (!$requestSnapshot || (string)($requestSnapshot['status'] ?? '') !== 'engineer_approved') {
            procurement_set_flash('error', 'Only engineer-approved requests can be converted to purchase orders.');
            procurement_redirect();
        }

        $requestItemStmt = $conn->prepare(
            "SELECT id, item_description, specification, unit, quantity_requested, COALESCE(estimated_unit_cost, 0) AS estimated_unit_cost
             FROM purchase_request_items
             WHERE purchase_request_id = ?
             ORDER BY id ASC"
        );

        if (!$requestItemStmt) {
            procurement_set_flash('error', 'Failed to load request items.');
            procurement_redirect();
        }

        $requestItemStmt->bind_param('i', $purchaseRequestId);
        $requestItemStmt->execute();
        $requestItems = $requestItemStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($requestItems)) {
            procurement_set_flash('error', 'Cannot create a purchase order without request items.');
            procurement_redirect();
        }

        $poNo = procurement_generate_code('PO');
        $conn->begin_transaction();

        try {
            $insertPo = $conn->prepare(
                'INSERT INTO purchase_orders (po_no, purchase_request_id, project_id, supplier_id, order_date, expected_delivery_date, remarks, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $poStatus = 'issued';

            if (
                !$insertPo ||
                !$insertPo->bind_param('siiissssi', $poNo, $purchaseRequestId, $requestSnapshot['project_id'], $supplierId, $orderDate, $expectedDeliveryDate, $poRemarks, $poStatus, $createdBy) ||
                !$insertPo->execute()
            ) {
                throw new RuntimeException('Failed to create purchase order.');
            }

            $purchaseOrderId = (int)$insertPo->insert_id;
            $insertPoItem = $conn->prepare(
                'INSERT INTO purchase_order_items (purchase_order_id, purchase_request_item_id, item_description, specification, unit, quantity_ordered, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$insertPoItem) {
                throw new RuntimeException('Failed to prepare purchase order items.');
            }

            foreach ($requestItems as $item) {
                $quantityOrdered = (float)($item['quantity_requested'] ?? 0);
                $unitPrice = (float)($item['estimated_unit_cost'] ?? 0);
                $lineTotal = round($quantityOrdered * $unitPrice, 2);

                if (
                    !$insertPoItem->bind_param(
                        'iisssddd',
                        $purchaseOrderId,
                        $item['id'],
                        $item['item_description'],
                        $item['specification'],
                        $item['unit'],
                        $quantityOrdered,
                        $unitPrice,
                        $lineTotal
                    ) ||
                    !$insertPoItem->execute()
                ) {
                    throw new RuntimeException('Failed to save purchase order item.');
                }
            }

            $updateRequest = $conn->prepare('UPDATE purchase_requests SET status = ? WHERE id = ?');
            $convertedStatus = 'approved';
            if (
                !$updateRequest ||
                !$updateRequest->bind_param('si', $convertedStatus, $purchaseRequestId) ||
                !$updateRequest->execute()
            ) {
                throw new RuntimeException('Failed to update request status after PO creation.');
            }

            $conn->commit();

            audit_log_event(
                $conn,
                $createdBy,
                'create_purchase_order',
                'purchase_order',
                $purchaseOrderId,
                null,
                [
                    'po_no' => $poNo,
                    'request_no' => $requestSnapshot['request_no'] ?? null,
                    'project_name' => $requestSnapshot['project_name'] ?? null,
                    'supplier_id' => $supplierId,
                ]
            );

            procurement_set_flash('success', 'Purchase order created from engineer-approved request.');
        } catch (Throwable $exception) {
            $conn->rollback();
            procurement_set_flash('error', $exception->getMessage());
        }

        procurement_redirect();
    }

    if ($action === 'trash_supplier') {
        $supplierId = (int)($_POST['supplier_id'] ?? 0);

        if ($supplierId <= 0) {
            procurement_set_flash('error', 'Invalid supplier.');
            procurement_redirect();
        }

        $beforeStmt = $conn->prepare('SELECT supplier_name, status FROM suppliers WHERE id = ? LIMIT 1');
        $before = null;
        if ($beforeStmt) {
            $beforeStmt->bind_param('i', $supplierId);
            $beforeStmt->execute();
            $before = $beforeStmt->get_result()->fetch_assoc();
        }

        $stmt = $conn->prepare("UPDATE suppliers SET status = 'inactive' WHERE id = ?");
        if ($stmt && $stmt->bind_param('i', $supplierId) && $stmt->execute()) {
            audit_log_event(
                $conn,
                (int)($_SESSION['user_id'] ?? 0),
                'trash_supplier',
                'supplier',
                $supplierId,
                $before,
                ['status' => 'inactive']
            );
            procurement_set_flash('success', 'Supplier moved to trash bin.');
        } else {
            procurement_set_flash('error', 'Failed to move supplier to trash bin.');
        }

        procurement_redirect();
    }

    if ($action === 'trash_purchase_request') {
        $purchaseRequestId = (int)($_POST['purchase_request_id'] ?? 0);

        if ($purchaseRequestId <= 0) {
            procurement_set_flash('error', 'Invalid request.');
            procurement_redirect();
        }

        $beforeStmt = $conn->prepare('SELECT request_no, status FROM purchase_requests WHERE id = ? LIMIT 1');
        $before = null;
        if ($beforeStmt) {
            $beforeStmt->bind_param('i', $purchaseRequestId);
            $beforeStmt->execute();
            $before = $beforeStmt->get_result()->fetch_assoc();
        }

        $stmt = $conn->prepare("UPDATE purchase_requests SET status = 'cancelled' WHERE id = ?");
        if ($stmt && $stmt->bind_param('i', $purchaseRequestId) && $stmt->execute()) {
            audit_log_event(
                $conn,
                (int)($_SESSION['user_id'] ?? 0),
                'trash_purchase_request',
                'purchase_request',
                $purchaseRequestId,
                $before,
                ['status' => 'cancelled']
            );
            procurement_set_flash('success', 'Purchase request moved to trash bin.');
        } else {
            procurement_set_flash('error', 'Failed to move purchase request to trash bin.');
        }

        procurement_redirect();
    }
}

$flash = $_SESSION['procurement_flash'] ?? null;
unset($_SESSION['procurement_flash']);

$projects = [];
$projectResult = $conn->query("SELECT id, project_name, status FROM projects WHERE status IN ('pending', 'ongoing', 'on-hold') ORDER BY project_name ASC");
if ($projectResult) {
    $projects = $projectResult->fetch_all(MYSQLI_ASSOC);
}

$requesters = [];
$requesterResult = $conn->query("SELECT id, full_name, role FROM users WHERE role IN ('foreman', 'engineer') AND status = 'active' ORDER BY FIELD(role, 'foreman', 'engineer'), full_name ASC");
if ($requesterResult) {
    $requesters = $requesterResult->fetch_all(MYSQLI_ASSOC);
}

$supplierRows = [];
$supplierResult = $conn->query("SELECT * FROM suppliers ORDER BY created_at DESC LIMIT 8");
if ($supplierResult) {
    $supplierRows = $supplierResult->fetch_all(MYSQLI_ASSOC);
}

$activeSuppliers = [];
$activeSupplierResult = $conn->query("SELECT id, supplier_name, supplier_code FROM suppliers WHERE status = 'active' ORDER BY supplier_name ASC");
if ($activeSupplierResult) {
    $activeSuppliers = $activeSupplierResult->fetch_all(MYSQLI_ASSOC);
}

$purchaseRequests = [];
$requestsResult = $conn->query(
    "SELECT
        pr.id,
        pr.request_no,
        pr.status,
        pr.request_type,
        pr.needed_date,
        pr.engineer_review_notes,
        pr.engineer_reviewed_at,
        pr.created_at,
        p.project_name,
        u.full_name AS requested_by_name,
        reviewer.full_name AS engineer_reviewer_name,
        pri.item_description,
        pri.unit,
        pri.quantity_requested
     FROM purchase_requests pr
     INNER JOIN projects p ON p.id = pr.project_id
     INNER JOIN users u ON u.id = pr.requested_by
     LEFT JOIN users reviewer ON reviewer.id = pr.engineer_reviewed_by
     LEFT JOIN purchase_request_items pri ON pri.purchase_request_id = pr.id
     ORDER BY pr.created_at DESC, pri.id ASC
     LIMIT 12"
);
if ($requestsResult) {
    $purchaseRequests = $requestsResult->fetch_all(MYSQLI_ASSOC);
}

$engineerApprovedRequests = [];
$engineerApprovedResult = $conn->query(
    "SELECT
        pr.id,
        pr.request_no,
        p.project_name,
        reviewer.full_name AS engineer_reviewer_name,
        pri.item_description,
        pri.quantity_requested,
        pri.unit
     FROM purchase_requests pr
     INNER JOIN projects p ON p.id = pr.project_id
     LEFT JOIN users reviewer ON reviewer.id = pr.engineer_reviewed_by
     LEFT JOIN purchase_request_items pri ON pri.purchase_request_id = pr.id
     WHERE pr.status = 'engineer_approved'
     ORDER BY pr.updated_at DESC, pri.id ASC"
);
if ($engineerApprovedResult) {
    $engineerApprovedRequests = $engineerApprovedResult->fetch_all(MYSQLI_ASSOC);
}

$purchaseOrders = [];
$purchaseOrderResult = $conn->query(
    "SELECT
        po.id,
        po.po_no,
        po.status,
        po.order_date,
        po.expected_delivery_date,
        p.project_name,
        s.supplier_name,
        pr.request_no
     FROM purchase_orders po
     INNER JOIN projects p ON p.id = po.project_id
     INNER JOIN suppliers s ON s.id = po.supplier_id
     INNER JOIN purchase_requests pr ON pr.id = po.purchase_request_id
     ORDER BY po.created_at DESC
     LIMIT 10"
);
if ($purchaseOrderResult) {
    $purchaseOrders = $purchaseOrderResult->fetch_all(MYSQLI_ASSOC);
}

$procurementStats = [
    'suppliers' => 0,
    'requests' => 0,
    'submitted' => 0,
    'approved' => 0,
];
$statsResult = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM suppliers WHERE status = 'active') AS suppliers,
        (SELECT COUNT(*) FROM purchase_requests) AS requests,
        (SELECT COUNT(*) FROM purchase_requests WHERE status IN ('submitted', 'engineer_review')) AS submitted,
        (SELECT COUNT(*) FROM purchase_requests WHERE status IN ('engineer_approved', 'approved')) AS approved"
);
if ($statsResult) {
    $procurementStats = array_merge($procurementStats, $statsResult->fetch_assoc() ?: []);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement - Super Admin</title>
    <link rel="stylesheet" href="../css/super_admin_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../super_admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-stack">
            <section class="metrics-grid">
                <div class="metric-card">
                    <span>Active Suppliers</span>
                    <strong><?php echo (int)($procurementStats['suppliers'] ?? 0); ?></strong>
                </div>
                <div class="metric-card">
                    <span>Requests</span>
                    <strong><?php echo (int)($procurementStats['requests'] ?? 0); ?></strong>
                </div>
                <div class="metric-card">
                    <span>For Review</span>
                    <strong><?php echo (int)($procurementStats['submitted'] ?? 0); ?></strong>
                </div>
                <div class="metric-card">
                    <span>Approved</span>
                    <strong><?php echo (int)($procurementStats['approved'] ?? 0); ?></strong>
                </div>
            </section>

            <?php if ($flash): ?>
                <div class="alert <?php echo ($flash['type'] ?? '') === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

           

            <section class="form-panel">
                <h1 class="section-title-inline">Procurement Control</h1>
                <div class="lock-note">Workflow: Foreman usually creates the purchase request, Engineer validates it, then Super Admin selects supplier and creates the purchase order.</div>
            </section>

            <section class="form-panel">
                <h2 class="section-title-inline">Add Supplier</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_supplier">
                    <div class="form-grid">
                        <div class="input-group">
                            <label for="supplier_name">Supplier Name <span class="required-indicator" aria-hidden="true">*</span></label>
                            <input type="text" id="supplier_name" name="supplier_name" required>
                        </div>
                        <div class="input-group">
                            <label for="contact_person">Contact Person <span class="required-indicator" aria-hidden="true">*</span></label>
                            <input type="text" id="contact_person" name="contact_person" required>
                        </div>
                        <div class="input-group">
                            <label for="contact_number">Contact Number <span class="required-indicator" aria-hidden="true">*</span></label>
                            <input type="text" id="contact_number" name="contact_number" inputmode="numeric" required>
                        </div>
                        <div class="input-group">
                            <label for="email">Email <span class="optional-indicator">(Optional)</span></label>
                            <input type="email" id="email" name="email">
                        </div>
                        <div class="input-group input-group-wide">
                            <label for="address">Address <span class="required-indicator" aria-hidden="true">*</span></label>
                            <input type="text" id="address" name="address" required>
                        </div>
                        <div class="input-group input-group-wide">
                            <label for="description">Supplier Description</label>
                            <input type="text" id="description" name="description" placeholder="What this supplier provides or when to call or email them">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Add Supplier</button>
                    </div>
                </form>
            </section>

            <section class="form-panel">
                <h2 class="section-title-inline">Purchase Request Intake</h2>
                <div class="lock-note">Create Purchase Request should not be under Super Admin. Foreman or the assigned project team should submit the request first, Engineer reviews it, then Super Admin handles supplier selection and purchase order creation.</div>
            </section>

            <section class="form-panel">
                <h2 class="section-title-inline">Create Purchase Order</h2>
                <?php if (empty($engineerApprovedRequests)): ?>
                    <div class="empty-state">No engineer-approved requests yet.</div>
                <?php elseif (empty($activeSuppliers)): ?>
                    <div class="empty-state">Add an active supplier first before creating a purchase order.</div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_purchase_order">
                        <div class="form-grid">
                            <div class="input-group">
                                <label for="purchase_request_id">Engineer-Approved Request</label>
                                <select id="purchase_request_id" name="purchase_request_id" required>
                                    <option value="">Select approved request</option>
                                    <?php foreach ($engineerApprovedRequests as $request): ?>
                                        <option value="<?php echo (int)$request['id']; ?>">
                                            <?php echo htmlspecialchars(($request['request_no'] ?? '') . ' - ' . ($request['project_name'] ?? 'Project') . ' - ' . ($request['item_description'] ?? 'Item')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <label for="supplier_id">Supplier</label>
                                <select id="supplier_id" name="supplier_id" required>
                                    <option value="">Select supplier</option>
                                    <?php foreach ($activeSuppliers as $supplier): ?>
                                        <option value="<?php echo (int)$supplier['id']; ?>">
                                            <?php echo htmlspecialchars(($supplier['supplier_name'] ?? 'Supplier') . ' - ' . ($supplier['supplier_code'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <label for="order_date">Order Date</label>
                                <input type="date" id="order_date" name="order_date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
                            </div>
                            <div class="input-group">
                                <label for="expected_delivery_date">Expected Delivery Date</label>
                                <input type="date" id="expected_delivery_date" name="expected_delivery_date">
                            </div>
                            <div class="input-group input-group-wide">
                                <label for="po_remarks">Remarks</label>
                                <input type="text" id="po_remarks" name="po_remarks" placeholder="Terms, schedule, or purchasing note">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Create Purchase Order</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="page-stack">
                <h2 class="section-title-inline">Recent Purchase Requests</h2>
                <?php if (empty($purchaseRequests)): ?>
                    <div class="empty-state">No purchase requests yet.</div>
                <?php else: ?>
                    <div class="projects-grid">
                        <?php foreach ($purchaseRequests as $request): ?>
                            <article class="project-card">
                                <div class="card-split">
                                    <div>
                                        <div class="project-card__eyebrow-row">
                                            <span class="project-card__eyebrow">Request No</span>
                                            <span class="project-card__reference"><?php echo htmlspecialchars($request['request_no'] ?? ''); ?></span>
                                        </div>
                                        <h3><?php echo htmlspecialchars($request['item_description'] ?? 'Request Item'); ?></h3>
                                        <div class="status-pill-wrap">
                                            <span class="status-pill <?php echo htmlspecialchars(procurement_status_class((string)($request['status'] ?? 'submitted'))); ?>">
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($request['status'] ?? 'submitted')))); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="project-meta">
                                        <div><strong>Project:</strong> <?php echo htmlspecialchars($request['project_name'] ?? 'N/A'); ?></div>
                                        <div><strong>Requested By:</strong> <?php echo htmlspecialchars($request['requested_by_name'] ?? 'N/A'); ?></div>
                                        <div><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst((string)($request['request_type'] ?? 'material'))); ?></div>
                                        <div><strong>Qty:</strong> <?php echo htmlspecialchars((string)($request['quantity_requested'] ?? '0')); ?> <?php echo htmlspecialchars((string)($request['unit'] ?? '')); ?></div>
                                        <div><strong>Needed:</strong> <?php echo htmlspecialchars((string)($request['needed_date'] ?? 'Not set')); ?></div>
                                        <div><strong>Engineer Review:</strong> <?php echo htmlspecialchars((string)($request['engineer_reviewer_name'] ?? 'Pending')); ?></div>
                                        <div><strong>Created:</strong> <?php echo htmlspecialchars((string)($request['created_at'] ?? '')); ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($request['engineer_review_notes'])): ?>
                                    <div class="lock-note"><strong>Engineer Note:</strong> <?php echo htmlspecialchars((string)$request['engineer_review_notes']); ?></div>
                                <?php endif; ?>
                                <?php if (!in_array((string)($request['status'] ?? ''), ['approved', 'cancelled'], true)): ?>
                                    <div class="form-actions">
                                        <form method="POST" onsubmit="return confirm('Move this purchase request to trash bin?');">
                                            <input type="hidden" name="action" value="trash_purchase_request">
                                            <input type="hidden" name="purchase_request_id" value="<?php echo (int)$request['id']; ?>">
                                            <button type="submit" class="btn-danger">Move To Trash Bin</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="page-stack">
                <h2 class="section-title-inline">Recent Purchase Orders</h2>
                <?php if (empty($purchaseOrders)): ?>
                    <div class="empty-state">No purchase orders yet.</div>
                <?php else: ?>
                    <div class="projects-grid">
                        <?php foreach ($purchaseOrders as $purchaseOrder): ?>
                            <article class="project-card">
                                <div class="card-split">
                                    <div>
                                        <div class="project-card__eyebrow-row">
                                            <span class="project-card__eyebrow">PO No</span>
                                            <span class="project-card__reference"><?php echo htmlspecialchars((string)($purchaseOrder['po_no'] ?? '')); ?></span>
                                        </div>
                                        <h3><?php echo htmlspecialchars((string)($purchaseOrder['project_name'] ?? 'Project')); ?></h3>
                                        <div class="status-pill-wrap">
                                            <span class="status-pill <?php echo htmlspecialchars(procurement_status_class((string)($purchaseOrder['status'] ?? 'issued'))); ?>">
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($purchaseOrder['status'] ?? 'issued')))); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="project-meta">
                                        <div><strong>Supplier:</strong> <?php echo htmlspecialchars((string)($purchaseOrder['supplier_name'] ?? 'N/A')); ?></div>
                                        <div><strong>Request:</strong> <?php echo htmlspecialchars((string)($purchaseOrder['request_no'] ?? 'N/A')); ?></div>
                                        <div><strong>Order Date:</strong> <?php echo htmlspecialchars((string)($purchaseOrder['order_date'] ?? '')); ?></div>
                                        <div><strong>Expected Delivery:</strong> <?php echo htmlspecialchars((string)($purchaseOrder['expected_delivery_date'] ?? 'Not set')); ?></div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="page-stack">
                <h2 class="section-title-inline">Suppliers</h2>
                <?php if (empty($supplierRows)): ?>
                    <div class="empty-state">No suppliers yet.</div>
                <?php else: ?>
                    <div class="projects-grid">
                        <?php foreach ($supplierRows as $supplier): ?>
                            <article class="project-card">
                                <div class="card-split">
                                    <div>
                                        <div class="project-card__eyebrow-row">
                                            <span class="project-card__eyebrow">Supplier</span>
                                            <span class="project-card__reference"><?php echo htmlspecialchars($supplier['supplier_code'] ?? ''); ?></span>
                                        </div>
                                        <h3><?php echo htmlspecialchars($supplier['supplier_name'] ?? 'Supplier'); ?></h3>
                                        <div class="status-pill-wrap">
                                            <span class="status-pill status-<?php echo htmlspecialchars((string)($supplier['status'] ?? 'active')); ?>">
                                                <?php echo htmlspecialchars(ucfirst((string)($supplier['status'] ?? 'active'))); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="project-meta">
                                        <div><strong>Contact:</strong> <?php echo htmlspecialchars((string)($supplier['contact_person'] ?? 'Not set')); ?></div>
                                        <div><strong>Number:</strong> <?php echo htmlspecialchars((string)($supplier['contact_number'] ?? 'Not set')); ?></div>
                                        <div><strong>Email:</strong> <?php echo htmlspecialchars((string)($supplier['email'] ?? 'Not set')); ?></div>
                                        <div><strong>Address:</strong> <?php echo htmlspecialchars((string)($supplier['address'] ?? 'Not set')); ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($supplier['description'])): ?>
                                    <div class="lock-note"><strong>Description:</strong> <?php echo htmlspecialchars((string)$supplier['description']); ?></div>
                                <?php endif; ?>
                                <?php if ((string)($supplier['status'] ?? 'active') !== 'inactive'): ?>
                                    <div class="form-actions">
                                        <form method="POST" onsubmit="return confirm('Move this supplier to trash bin?');">
                                            <input type="hidden" name="action" value="trash_supplier">
                                            <input type="hidden" name="supplier_id" value="<?php echo (int)$supplier['id']; ?>">
                                            <button type="submit" class="btn-danger">Move To Trash Bin</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
<script src="../js/super_admin_dashboard.js"></script>
</body>
</html>
