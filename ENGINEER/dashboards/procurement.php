<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/audit_log.php';
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../includes/engineer_helpers.php';

require_role('engineer');

function engineer_procurement_redirect(): void
{
    header('Location: /codesamplecaps/ENGINEER/dashboards/procurement.php');
    exit();
}

function engineer_procurement_ensure_purchase_requests_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS purchase_requests (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_no VARCHAR(80) NOT NULL UNIQUE,
            project_id INT(11) NOT NULL,
            requested_by INT(11) NOT NULL,
            engineer_reviewed_by INT(11) DEFAULT NULL,
            needed_date DATE DEFAULT NULL,
            request_type ENUM('material', 'tool', 'equipment', 'service') NOT NULL DEFAULT 'material',
            site_location VARCHAR(190) DEFAULT NULL,
            remarks TEXT DEFAULT NULL,
            engineer_review_notes TEXT DEFAULT NULL,
            status ENUM('draft', 'submitted', 'engineer_review', 'engineer_approved', 'engineer_rejected', 'approved', 'cancelled') NOT NULL DEFAULT 'submitted',
            engineer_reviewed_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_purchase_requests_project_status (project_id, status),
            KEY idx_purchase_requests_requested_by (requested_by),
            CONSTRAINT fk_purchase_requests_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
            CONSTRAINT fk_purchase_requests_requested_by FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $columnCheck = $conn->query("SHOW COLUMNS FROM purchase_requests LIKE 'engineer_reviewed_by'");
    if (!$columnCheck || (int)$columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE purchase_requests ADD COLUMN engineer_reviewed_by INT(11) DEFAULT NULL AFTER requested_by");
        $conn->query("ALTER TABLE purchase_requests ADD COLUMN engineer_review_notes TEXT DEFAULT NULL AFTER remarks");
        $conn->query("ALTER TABLE purchase_requests ADD COLUMN engineer_reviewed_at DATETIME DEFAULT NULL AFTER engineer_review_notes");
    }
}

function engineer_procurement_is_assigned(mysqli $conn, int $projectId, int $engineerId): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
         FROM project_assignments
         WHERE project_id = ?
         AND engineer_id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $projectId, $engineerId);
    $stmt->execute();
    $result = $stmt->get_result();

    return (bool)($result && $result->fetch_assoc());
}

engineer_procurement_ensure_purchase_requests_table($conn);

$userId = (int)($_SESSION['user_id'] ?? 0);
$flash = engineer_consume_flash();
$csrfToken = engineer_get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'review_purchase_request') {
        if (!engineer_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
            engineer_set_flash('error', 'Security check failed.');
            engineer_procurement_redirect();
        }

        $purchaseRequestId = (int)($_POST['purchase_request_id'] ?? 0);
        $reviewAction = trim((string)($_POST['review_action'] ?? ''));
        $reviewNotes = engineer_normalize_text_or_null($_POST['engineer_review_notes'] ?? null);

        if ($purchaseRequestId <= 0 || !in_array($reviewAction, ['engineer_review', 'engineer_approved', 'engineer_rejected'], true)) {
            engineer_set_flash('error', 'Invalid procurement review action.');
            engineer_procurement_redirect();
        }

        $snapshotStmt = $conn->prepare(
            "SELECT pr.id, pr.project_id, pr.request_no, pr.status, p.project_name
             FROM purchase_requests pr
             INNER JOIN projects p ON p.id = pr.project_id
             WHERE pr.id = ?
             LIMIT 1"
        );

        if (!$snapshotStmt) {
            engineer_set_flash('error', 'Failed to load request.');
            engineer_procurement_redirect();
        }

        $snapshotStmt->bind_param('i', $purchaseRequestId);
        $snapshotStmt->execute();
        $snapshot = $snapshotStmt->get_result()->fetch_assoc();

        if (!$snapshot || !engineer_procurement_is_assigned($conn, (int)$snapshot['project_id'], $userId)) {
            engineer_set_flash('error', 'You cannot review this request.');
            engineer_procurement_redirect();
        }

        if (!in_array((string)($snapshot['status'] ?? ''), ['submitted', 'engineer_review'], true)) {
            engineer_set_flash('error', 'This request is already finalized for engineer review.');
            engineer_procurement_redirect();
        }

        $updateStmt = $conn->prepare(
            'UPDATE purchase_requests
             SET status = ?, engineer_reviewed_by = ?, engineer_review_notes = ?, engineer_reviewed_at = NOW()
             WHERE id = ?'
        );

        if (
            $updateStmt &&
            $updateStmt->bind_param('sisi', $reviewAction, $userId, $reviewNotes, $purchaseRequestId) &&
            $updateStmt->execute()
        ) {
            audit_log_event(
                $conn,
                $userId,
                'engineer_purchase_request_review',
                'purchase_request',
                $purchaseRequestId,
                [
                    'status' => $snapshot['status'] ?? null,
                ],
                [
                    'request_no' => $snapshot['request_no'] ?? null,
                    'project_name' => $snapshot['project_name'] ?? null,
                    'status' => $reviewAction,
                    'engineer_review_notes' => $reviewNotes,
                ]
            );
            engineer_set_flash('success', 'Purchase request review saved.');
        } else {
            engineer_set_flash('error', 'Failed to save engineer review.');
        }

        engineer_procurement_redirect();
    }
}

$requests = [];
$requestsStmt = $conn->prepare(
    "SELECT
        pr.id,
        pr.request_no,
        pr.status,
        pr.request_type,
        pr.needed_date,
        pr.site_location,
        pr.remarks,
        pr.engineer_review_notes,
        pr.engineer_reviewed_at,
        p.project_name,
        requester.full_name AS requested_by_name,
        pri.item_description,
        pri.specification,
        pri.unit,
        pri.quantity_requested
     FROM purchase_requests pr
     INNER JOIN projects p ON p.id = pr.project_id
     INNER JOIN project_assignments pa ON pa.project_id = p.id
     INNER JOIN users requester ON requester.id = pr.requested_by
     LEFT JOIN purchase_request_items pri ON pri.purchase_request_id = pr.id
     WHERE pa.engineer_id = ?
     ORDER BY
        CASE
            WHEN pr.status = 'submitted' THEN 1
            WHEN pr.status = 'engineer_review' THEN 2
            WHEN pr.status = 'engineer_approved' THEN 3
            WHEN pr.status = 'engineer_rejected' THEN 4
            ELSE 5
        END,
        pr.created_at DESC"
);

if ($requestsStmt) {
    $requestsStmt->bind_param('i', $userId);
    $requestsStmt->execute();
    $result = $requestsStmt->get_result();
    $requests = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

foreach ($requests as $request) {
    $status = (string)($request['status'] ?? '');
    if (in_array($status, ['submitted', 'engineer_review'], true)) {
        $pendingCount++;
    } elseif ($status === 'engineer_approved') {
        $approvedCount++;
    } elseif ($status === 'engineer_rejected') {
        $rejectedCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement Reviews - Engineer</title>
    <link rel="stylesheet" href="../css/engineer-sidebar.css">
    <link rel="stylesheet" href="../css/engineer.css">
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../sidebar/sidebar_engineer.php'; ?>

    <main class="dashboard-main">
        <section class="stats-grid" aria-label="Procurement review metrics">
            <article class="metric-card">
                <span>Total Requests</span>
                <strong><?php echo count($requests); ?></strong>
            </article>
            <article class="metric-card">
                <span>Pending Review</span>
                <strong><?php echo $pendingCount; ?></strong>
            </article>
            <article class="metric-card">
                <span>Approved</span>
                <strong><?php echo $approvedCount; ?></strong>
            </article>
            <article class="metric-card">
                <span>Rejected</span>
                <strong><?php echo $rejectedCount; ?></strong>
            </article>
        </section>

        <?php if ($flash): ?>
            <div class="alert <?php echo ($flash['type'] ?? '') === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars((string)($flash['message'] ?? '')); ?>
            </div>
        <?php endif; ?>

        <section class="content-panel">
            <div class="panel-header">
                <div>
                    <h1>Procurement Reviews</h1>
                    <p>Engineer validation happens here before Super Admin handles supplier and purchasing.</p>
                </div>
            </div>

            <?php if (empty($requests)): ?>
                <div class="empty-state">No purchase requests assigned to your projects yet.</div>
            <?php else: ?>
                <div class="project-list">
                    <?php foreach ($requests as $request): ?>
                        <?php $status = (string)($request['status'] ?? 'submitted'); ?>
                        <article class="project-card">
                            <div class="project-card-header">
                                <div>
                                    <p class="project-card-kicker"><?php echo htmlspecialchars((string)($request['request_no'] ?? '')); ?></p>
                                    <h2><?php echo htmlspecialchars((string)($request['item_description'] ?? 'Request Item')); ?></h2>
                                </div>
                                <span class="status <?php echo htmlspecialchars(str_replace('_', '-', $status)); ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status))); ?>
                                </span>
                            </div>

                            <div class="project-card-body">
                                <p><strong>Project:</strong> <?php echo htmlspecialchars((string)($request['project_name'] ?? 'N/A')); ?></p>
                                <p><strong>Requested By:</strong> <?php echo htmlspecialchars((string)($request['requested_by_name'] ?? 'N/A')); ?></p>
                                <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst((string)($request['request_type'] ?? 'material'))); ?></p>
                                <p><strong>Qty:</strong> <?php echo htmlspecialchars((string)($request['quantity_requested'] ?? '0')); ?> <?php echo htmlspecialchars((string)($request['unit'] ?? '')); ?></p>
                                <p><strong>Specification:</strong> <?php echo htmlspecialchars((string)($request['specification'] ?? 'Not set')); ?></p>
                                <p><strong>Needed Date:</strong> <?php echo htmlspecialchars((string)($request['needed_date'] ?? 'Not set')); ?></p>
                                <p><strong>Site Location:</strong> <?php echo htmlspecialchars((string)($request['site_location'] ?? 'Not set')); ?></p>
                                <p><strong>Request Note:</strong> <?php echo htmlspecialchars((string)($request['remarks'] ?? 'None')); ?></p>
                                <?php if (!empty($request['engineer_review_notes'])): ?>
                                    <p><strong>Latest Review Note:</strong> <?php echo htmlspecialchars((string)$request['engineer_review_notes']); ?></p>
                                <?php endif; ?>
                            </div>

                            <?php if (in_array($status, ['submitted', 'engineer_review'], true)): ?>
                                <form method="POST" class="task-update-form">
                                    <input type="hidden" name="action" value="review_purchase_request">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="purchase_request_id" value="<?php echo (int)$request['id']; ?>">

                                    <div class="task-update-fields">
                                        <label>
                                            Decision
                                            <select name="review_action" required>
                                                <option value="engineer_review" <?php echo $status === 'engineer_review' ? 'selected' : ''; ?>>Mark Under Review</option>
                                                <option value="engineer_approved">Approve For Procurement</option>
                                                <option value="engineer_rejected">Reject Request</option>
                                            </select>
                                        </label>
                                        <label>
                                            Note
                                            <textarea name="engineer_review_notes" rows="3" placeholder="Technical validation note, correction, or rejection reason"></textarea>
                                        </label>
                                    </div>

                                    <div class="task-update-actions">
                                        <button type="submit">Save Review</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
