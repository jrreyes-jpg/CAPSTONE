<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/quotation_module.php';

require_role('foreman');

$userId = (int)($_SESSION['user_id'] ?? 0);
$flash = quotation_module_consume_flash();
$csrfToken = quotation_module_csrf_token();
$tablesReady = quotation_module_tables_ready($conn);
$quotations = $tablesReady ? quotation_module_fetch_quotations($conn, 'foreman', $userId) : [];
$quotationId = (int)($_GET['id'] ?? 0);
$selectedQuotation = null;

if ($quotationId > 0 && $tablesReady) {
    $selectedQuotation = quotation_module_fetch_quotation($conn, $quotationId);
    if (!$selectedQuotation || !quotation_module_user_can_access($selectedQuotation, 'foreman', $userId)) {
        quotation_module_set_flash('error', 'Quotation not found for your review queue.');
        quotation_module_redirect('/codesamplecaps/FOREMAN/dashboards/quotation_reviews.php');
    }
} elseif (!empty($quotations)) {
    $selectedQuotation = quotation_module_fetch_quotation($conn, (int)$quotations[0]['id']);
}

$items = $selectedQuotation ? quotation_module_fetch_quotation_items($conn, (int)$selectedQuotation['id']) : [];
$reviews = $selectedQuotation ? quotation_module_fetch_reviews($conn, (int)$selectedQuotation['id']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foreman Quotation Reviews - Edge Automation</title>
    <link rel="stylesheet" href="../css/sidebar_foreman.css">
    <link rel="stylesheet" href="../css/foreman_dashboard.css">
    <style>
        .quotation-shell { padding: 24px; display: grid; gap: 24px; }
        .grid { display: grid; gap: 20px; grid-template-columns: 360px 1fr; }
        .panel { background: #fff; border-radius: 18px; box-shadow: 0 16px 38px rgba(15,23,42,.08); padding: 22px; }
        .queue-list, .review-thread { display: grid; gap: 12px; }
        .queue-card, .review-card, .summary-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; }
        .queue-card.active { border-color: #0f766e; box-shadow: 0 0 0 2px rgba(15,118,110,.1); }
        .queue-card a { text-decoration: none; color: inherit; display: grid; gap: 6px; }
        .status-pill { display: inline-flex; padding: 6px 10px; border-radius: 999px; font-size: .8rem; font-weight: 700; }
        .status-pill.is-review { background: #fef3c7; color: #92400e; }
        .status-pill.is-approval { background: #dbeafe; color: #1d4ed8; }
        .status-pill.is-draft { background: #e2e8f0; color: #334155; }
        .flash { padding: 14px 16px; border-radius: 14px; font-weight: 600; }
        .flash.success { background: #dcfce7; color: #166534; }
        .flash.error { background: #fee2e2; color: #b91c1c; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td { border-bottom: 1px solid #e2e8f0; padding: 10px 8px; text-align: left; }
        .items-table th { font-size: .8rem; text-transform: uppercase; color: #475569; }
        .btn-primary, .btn-danger { border: 0; border-radius: 12px; padding: 12px 16px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-danger { background: #fee2e2; color: #b91c1c; }
        textarea { width: 100%; min-height: 120px; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; font: inherit; }
        .empty-state { border: 1px dashed #cbd5e1; border-radius: 16px; padding: 24px; color: #64748b; text-align: center; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } .quotation-shell { padding: 16px; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../sidebar/sidebar_foreman.php'; ?>
<main class="main-content">
    <div class="quotation-shell">
        <?php if ($flash): ?><div class="flash <?php echo htmlspecialchars((string)$flash['type']); ?>"><?php echo htmlspecialchars((string)$flash['message']); ?></div><?php endif; ?>
        <section class="panel">
            <h1>Review Quotations</h1>
            <p>Foreman reviews manpower and execution feasibility only. Final approval remains with the Super Admin.</p>
        </section>

        <?php if (!$tablesReady): ?>
            <section class="panel"><div class="empty-state">Run <code>scripts/setup_quotation_tables.php</code> first to enable quotation reviews.</div></section>
        <?php else: ?>
            <div class="grid">
                <aside class="panel">
                    <h2>My Review Queue</h2>
                    <div class="queue-list">
                        <?php if (!empty($quotations)): ?>
                            <?php foreach ($quotations as $quotation): ?>
                                <article class="queue-card <?php echo $selectedQuotation && (int)$selectedQuotation['id'] === (int)$quotation['id'] ? 'active' : ''; ?>">
                                    <a href="/codesamplecaps/FOREMAN/dashboards/quotation_reviews.php?id=<?php echo (int)$quotation['id']; ?>">
                                        <strong><?php echo htmlspecialchars((string)$quotation['quotation_no']); ?></strong>
                                        <span><?php echo htmlspecialchars((string)$quotation['project_name']); ?></span>
                                        <span class="status-pill <?php echo htmlspecialchars(quotation_module_status_class((string)$quotation['status'])); ?>"><?php echo htmlspecialchars(quotation_module_status_label((string)$quotation['status'])); ?></span>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">No quotations are assigned to you yet.</div>
                        <?php endif; ?>
                    </div>
                </aside>

                <section class="panel">
                    <?php if ($selectedQuotation): ?>
                        <h2><?php echo htmlspecialchars((string)$selectedQuotation['quotation_no']); ?> | <?php echo htmlspecialchars((string)$selectedQuotation['project_name']); ?></h2>
                        <p>Engineer: <?php echo htmlspecialchars((string)$selectedQuotation['engineer_name']); ?> | Duration: <?php echo htmlspecialchars((string)($selectedQuotation['estimated_duration_days'] ?? 'N/A')); ?> day(s)</p>

                        <div class="review-thread" style="margin:16px 0;">
                            <article class="summary-card"><strong>Scope</strong><p><?php echo nl2br(htmlspecialchars((string)($selectedQuotation['scope_summary'] ?? 'No scope summary provided.'))); ?></p></article>
                            <article class="summary-card"><strong>Manpower Hours</strong><p><?php echo htmlspecialchars((string)($selectedQuotation['manpower_hours'] ?? 0)); ?> total estimated hours</p></article>
                            <article class="summary-card"><strong>Total Cost Snapshot</strong><p><?php echo htmlspecialchars(quotation_module_format_currency($selectedQuotation['total_cost'] ?? 0)); ?></p></article>
                        </div>

                        <table class="items-table">
                            <thead><tr><th>Type</th><th>Item</th><th>Unit</th><th>Qty</th><th>Hours</th><th>Rate</th><th>Total</th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(ucfirst((string)$item['item_type'])); ?></td>
                                    <td><?php echo htmlspecialchars((string)$item['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$item['unit']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$item['quantity']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$item['hours']); ?></td>
                                    <td><?php echo htmlspecialchars(quotation_module_format_currency($item['rate'] ?? 0)); ?></td>
                                    <td><?php echo htmlspecialchars(quotation_module_format_currency($item['line_total'] ?? 0)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="review-thread" style="margin-top:16px;">
                            <?php foreach ($reviews as $review): ?>
                                <article class="review-card">
                                    <small><?php echo htmlspecialchars((string)$review['full_name'] . ' | ' . quotation_module_format_datetime((string)$review['created_at'])); ?></small>
                                    <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$review['review_type']))); ?></strong>
                                    <p><?php echo nl2br(htmlspecialchars((string)$review['message'])); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if ((string)$selectedQuotation['status'] === 'under_review'): ?>
                            <form method="POST" action="/codesamplecaps/controllers/QuotationReviewController.php" class="review-thread" style="margin-top:20px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="quotation_id" value="<?php echo (int)$selectedQuotation['id']; ?>">
                                <div>
                                    <h3>Suggestion-Only Review Panel</h3>
                                    <p>Add execution or manpower feedback for the Engineer. Use return only when the draft needs revision.</p>
                                </div>
                                <textarea name="message" placeholder="Add manpower, duration, or execution feasibility notes..." required></textarea>
                                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                                    <button class="btn-primary" type="submit" name="action" value="add_foreman_comment">Save Suggestion</button>
                                    <button class="btn-danger" type="submit" name="action" value="return_to_engineer">Return To Engineer</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">Select a quotation from your queue to review it.</div>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
