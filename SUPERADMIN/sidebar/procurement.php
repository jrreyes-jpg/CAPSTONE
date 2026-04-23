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
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_suppliers_status_name (status, supplier_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
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

procurement_ensure_suppliers_table($conn);
procurement_ensure_purchase_requests_table($conn);
procurement_ensure_purchase_request_items_table($conn);
procurement_ensure_purchase_request_review_columns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_supplier') {
        $supplierName = procurement_normalize_text($_POST['supplier_name'] ?? '');
        $contactPerson = procurement_normalize_text($_POST['contact_person'] ?? '');
        $contactNumber = procurement_normalize_phone($_POST['contact_number'] ?? null);
        $email = procurement_normalize_text_or_null($_POST['email'] ?? null);
        $address = procurement_normalize_text($_POST['address'] ?? '');

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
            'INSERT INTO suppliers (supplier_code, supplier_name, contact_person, contact_number, email, address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (
            $stmt &&
            $stmt->bind_param('ssssss', $supplierCode, $supplierName, $contactPerson, $contactNumber, $email, $address) &&
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
                ]
            );
            procurement_set_flash('success', 'Supplier added.');
        } else {
            procurement_set_flash('error', 'Failed to add supplier.');
        }

        procurement_redirect();
    }

    if ($action === 'create_purchase_request') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $requestedBy = (int)($_POST['requested_by'] ?? 0);
        $neededDate = procurement_normalize_text_or_null($_POST['needed_date'] ?? null);
        $requestType = procurement_normalize_text($_POST['request_type'] ?? 'material');
        $siteLocation = procurement_normalize_text_or_null($_POST['site_location'] ?? null);
        $remarks = procurement_normalize_text_or_null($_POST['remarks'] ?? null);
        $itemDescription = procurement_normalize_text($_POST['item_description'] ?? '');
        $specification = procurement_normalize_text_or_null($_POST['specification'] ?? null);
        $unit = procurement_normalize_text($_POST['unit'] ?? '');
        $quantityRequested = procurement_normalize_money_or_null($_POST['quantity_requested'] ?? null);
        $estimatedUnitCost = procurement_normalize_money_or_null($_POST['estimated_unit_cost'] ?? null);
        $allowedTypes = ['material', 'tool', 'equipment', 'service'];

        if ($projectId <= 0 || $requestedBy <= 0 || $itemDescription === '' || $unit === '' || $quantityRequested === null || $quantityRequested <= 0) {
            procurement_set_flash('error', 'Project, requester, item, unit, and quantity are required.');
            procurement_redirect();
        }

        if (!in_array($requestType, $allowedTypes, true)) {
            procurement_set_flash('error', 'Invalid request type.');
            procurement_redirect();
        }

        $requestNo = procurement_generate_code('PR');
        $conn->begin_transaction();

        try {
            $insertRequest = $conn->prepare(
                'INSERT INTO purchase_requests (request_no, project_id, requested_by, needed_date, request_type, site_location, remarks, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $status = 'submitted';
            if (
                !$insertRequest ||
                !$insertRequest->bind_param('siisssss', $requestNo, $projectId, $requestedBy, $neededDate, $requestType, $siteLocation, $remarks, $status) ||
                !$insertRequest->execute()
            ) {
                throw new RuntimeException('Failed to create purchase request.');
            }

            $purchaseRequestId = (int)$insertRequest->insert_id;
            $insertItem = $conn->prepare(
                'INSERT INTO purchase_request_items (purchase_request_id, item_description, specification, unit, quantity_requested, estimated_unit_cost)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            if (
                !$insertItem ||
                !$insertItem->bind_param('isssdd', $purchaseRequestId, $itemDescription, $specification, $unit, $quantityRequested, $estimatedUnitCost) ||
                !$insertItem->execute()
            ) {
                throw new RuntimeException('Failed to save request item.');
            }

            $conn->commit();

            audit_log_event(
                $conn,
                (int)($_SESSION['user_id'] ?? 0),
                'create_purchase_request',
                'purchase_request',
                $purchaseRequestId,
                null,
                [
                    'request_no' => $requestNo,
                    'project_id' => $projectId,
                    'requested_by' => $requestedBy,
                    'request_type' => $requestType,
                    'item_description' => $itemDescription,
                    'quantity_requested' => $quantityRequested,
                ]
            );

            procurement_set_flash('success', 'Purchase request created.');
        } catch (Throwable $exception) {
            $conn->rollback();
            procurement_set_flash('error', $exception->getMessage());
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
$requesterResult = $conn->query("SELECT id, full_name, role FROM users WHERE role IN ('foreman', 'engineer') AND status = 'active' ORDER BY full_name ASC");
if ($requesterResult) {
    $requesters = $requesterResult->fetch_all(MYSQLI_ASSOC);
}

$supplierRows = [];
$supplierResult = $conn->query("SELECT * FROM suppliers ORDER BY created_at DESC LIMIT 8");
if ($supplierResult) {
    $supplierRows = $supplierResult->fetch_all(MYSQLI_ASSOC);
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
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Add Supplier</button>
                    </div>
                </form>
            </section>

            <section class="form-panel">
                <h2 class="section-title-inline">Create Purchase Request</h2>
                <?php if (empty($projects) || empty($requesters)): ?>
                    <div class="empty-state">Need active projects and engineer or foreman users before creating a request.</div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_purchase_request">
                        <div class="form-grid">
                            <div class="input-group">
                                <label for="project_id">Project</label>
                                <select id="project_id" name="project_id" required>
                                    <option value="">Select project</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo (int)$project['id']; ?>">
                                            <?php echo htmlspecialchars(($project['project_name'] ?? 'Project') . ' - ' . ucfirst((string)($project['status'] ?? ''))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <label for="requested_by">Requested By</label>
                                <select id="requested_by" name="requested_by" required>
                                    <option value="">Select requester</option>
                                    <?php foreach ($requesters as $requester): ?>
                                        <option value="<?php echo (int)$requester['id']; ?>">
                                            <?php echo htmlspecialchars(($requester['full_name'] ?? 'User') . ' - ' . ucfirst((string)($requester['role'] ?? ''))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <label for="request_type">Type</label>
                                <select id="request_type" name="request_type" required>
                                    <option value="material">Material</option>
                                    <option value="tool">Tool</option>
                                    <option value="equipment">Equipment</option>
                                    <option value="service">Service</option>
                                </select>
                            </div>
                            <div class="input-group">
                                <label for="needed_date">Needed Date</label>
                                <input type="date" id="needed_date" name="needed_date">
                            </div>
                            <div class="input-group">
                                <label for="site_location">Site Location</label>
                                <input type="text" id="site_location" name="site_location" placeholder="Area, floor, or work zone">
                            </div>
                            <div class="input-group">
                                <label for="item_description">Item</label>
                                <input type="text" id="item_description" name="item_description" placeholder="Cement, rebar, conduit" required>
                            </div>
                            <div class="input-group">
                                <label for="specification">Specification</label>
                                <input type="text" id="specification" name="specification" placeholder="Size, grade, brand">
                            </div>
                            <div class="input-group">
                                <label for="unit">Unit</label>
                                <input type="text" id="unit" name="unit" placeholder="bags, pcs, rolls" required>
                            </div>
                            <div class="input-group">
                                <label for="quantity_requested">Quantity</label>
                                <input type="number" id="quantity_requested" name="quantity_requested" min="0.01" step="0.01" required>
                            </div>
                            <div class="input-group">
                                <label for="estimated_unit_cost">Estimated Unit Cost</label>
                                <input type="number" id="estimated_unit_cost" name="estimated_unit_cost" min="0" step="0.01">
                            </div>
                            <div class="input-group input-group-wide">
                                <label for="remarks">Remarks</label>
                                <input type="text" id="remarks" name="remarks" placeholder="Why needed or what activity this supports">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Create Request</button>
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
                                    </div>
                                </div>
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
