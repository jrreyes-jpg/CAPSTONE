<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/quotation_module.php';

require_role('super_admin');

$flash = quotation_module_consume_flash();
$csrfToken = quotation_module_csrf_token();
$tablesReady = quotation_module_tables_ready($conn);
$quotations = $tablesReady ? quotation_module_fetch_quotations($conn, 'super_admin', (int)($_SESSION['user_id'] ?? 0)) : [];
$quotationId = (int)($_GET['id'] ?? 0);
$selectedQuotation = null;

if ($quotationId > 0 && $tablesReady) {
    $selectedQuotation = quotation_module_fetch_quotation($conn, $quotationId);
} elseif (!empty($quotations)) {
    $selectedQuotation = quotation_module_fetch_quotation($conn, (int)$quotations[0]['id']);
}

$items = $selectedQuotation ? quotation_module_fetch_quotation_items($conn, (int)$selectedQuotation['id']) : [];
$history = $selectedQuotation ? quotation_module_fetch_history($conn, (int)$selectedQuotation['id']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Approval Panel - Edge Automation</title>
    <link rel="stylesheet" href="../css/dashboard-sidebar.css">
    <link rel="stylesheet" href="../css/super_admin_dashboard.css">
    <style>
        .quotation-shell { padding: 24px; display: grid; gap: 24px; }
        .grid { display: grid; gap: 20px; grid-template-columns: 360px 1fr; }
        .panel { background: #fff; border-radius: 18px; box-shadow: 0 16px 38px rgba(15,23,42,.08); padding: 22px; }
        .queue-list, .history-list { display: grid; gap: 12px; }
        .queue-card, .history-card, .metric-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; }
        .queue-card.active { border-color: #0f766e; box-shadow: 0 0 0 2px rgba(15,118,110,.1); }
        .queue-card a { color: inherit; text-decoration: none; display: grid; gap: 6px; }
        .metrics { display: grid; gap: 12px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td { border-bottom: 1px solid #e2e8f0; padding: 10px 8px; text-align: left; }
        .items-table th { font-size: .8rem; color: #475569; text-transform: uppercase; }
        .status-pill { display: inline-flex; padding: 6px 10px; border-radius: 999px; font-size: .8rem; font-weight: 700; }
        .status-pill.is-approval { background: #dbeafe; color: #1d4ed8; }
        .status-pill.is-approved { background: #dcfce7; color: #166534; }
        .status-pill.is-sent { background: #ede9fe; color: #6d28d9; }
        .status-pill.is-review { background: #fef3c7; color: #92400e; }
        .flash { padding: 14px 16px; border-radius: 14px; font-weight: 600; }
        .flash.success { background: #dcfce7; color: #166534; }
        .flash.error { background: #fee2e2; color: #b91c1c; }
        .form-group { display: grid; gap: 8px; }
        .form-group input, .form-group textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; font: inherit; }
        .btn-primary, .btn-secondary { border: 0; border-radius: 12px; padding: 12px 16px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        .empty-state { border: 1px dashed #cbd5e1; border-radius: 16px; padding: 24px; color: #64748b; text-align: center; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } .metrics { grid-template-columns: 1fr; } .quotation-shell { padding: 16px; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../super_admin_sidebar.php'; ?>
<main class="main-content">
    <div class="quotation-shell">
        <?php if ($flash): ?><div class="flash <?php echo htmlspecialchars((string)$flash['type']); ?>"><?php echo htmlspecialchars((string)$flash['message']); ?></div><?php endif; ?>
        <section class="panel">
            <h1>Quotation Approval Panel</h1>
            <p>Only the Super Admin can approve, lock, and send quotations to the client.</p>
        </section>

        <?php if (!$tablesReady): ?>
            <section class="panel"><div class="empty-state">Run <code>scripts/setup_quotation_tables.php</code> first to enable quotation approvals.</div></section>
        <?php else: ?>
            <div class="grid">
                <aside class="panel">
                    <h2>Approval Queue</h2>
                    <div class="queue-list">
                        <?php if (!empty($quotations)): ?>
                            <?php foreach ($quotations as $quotation): ?>
                                <article class="queue-card <?php echo $selectedQuotation && (int)$selectedQuotation['id'] === (int)$quotation['id'] ? 'active' : ''; ?>">
                                    <a href="/codesamplecaps/SUPERADMIN/sidebar/quotations.php?id=<?php echo (int)$quotation['id']; ?>">
                                        <strong><?php echo htmlspecialchars((string)$quotation['quotation_no']); ?></strong>
                                        <span><?php echo htmlspecialchars((string)$quotation['project_name']); ?></span>
                                        <span><?php echo htmlspecialchars((string)$quotation['engineer_name']); ?></span>
                                        <span class="status-pill <?php echo htmlspecialchars(quotation_module_status_class((string)$quotation['status'])); ?>"><?php echo htmlspecialchars(quotation_module_status_label((string)$quotation['status'])); ?></span>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">No quotations in the workflow yet.</div>
                        <?php endif; ?>
                    </div>
                </aside>

                <section class="panel">
                    <?php if ($selectedQuotation): ?>
                        <h2><?php echo htmlspecialchars((string)$selectedQuotation['quotation_no']); ?> | <?php echo htmlspecialchars((string)$selectedQuotation['project_name']); ?></h2>
                        <p>Engineer: <?php echo htmlspecialchars((string)$selectedQuotation['engineer_name']); ?> | Client: <?php echo htmlspecialchars((string)$selectedQuotation['client_name']); ?></p>

                        <div class="metrics" style="margin:16px 0;">
                            <article class="metric-card"><span>Total Cost</span><strong><?php echo htmlspecialchars(quotation_module_format_currency($selectedQuotation['total_cost'] ?? 0)); ?></strong></article>
                            <article class="metric-card"><span>Current Margin</span><strong><?php echo htmlspecialchars(number_format((float)($selectedQuotation['profit_margin_percent'] ?? 0), 2)); ?>%</strong></article>
                            <article class="metric-card"><span>Selling Price</span><strong><?php echo htmlspecialchars(quotation_module_format_currency($selectedQuotation['selling_price'] ?? 0)); ?></strong></article>
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

                        <div class="history-list" style="margin:18px 0;">
                            <?php foreach ($history as $entry): ?>
                                <article class="history-card">
                                    <small><?php echo htmlspecialchars(quotation_module_format_datetime((string)$entry['created_at'])); ?></small>
                                    <strong><?php echo htmlspecialchars((string)$entry['full_name']); ?></strong>
                                    <p><?php echo htmlspecialchars(quotation_module_status_label((string)($entry['from_status'] ?? 'draft')) . ' -> ' . quotation_module_status_label((string)$entry['to_status'])); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if ((string)$selectedQuotation['status'] === 'for_approval'): ?>
                            <form method="POST" action="/codesamplecaps/controllers/QuotationAdminController.php" class="history-list">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="quotation_id" value="<?php echo (int)$selectedQuotation['id']; ?>">
                                <div class="form-group">
                                    <label for="profit_margin_percent">Profit Margin (%)</label>
                                    <input id="profit_margin_percent" type="number" step="0.01" min="0" name="profit_margin_percent" value="<?php echo htmlspecialchars((string)($selectedQuotation['profit_margin_percent'] ?? 0)); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="remarks">Approval Note</label>
                                    <textarea id="remarks" name="remarks" rows="4" placeholder="Optional approval note for audit trail"></textarea>
                                </div>
                                <button class="btn-primary" type="submit" name="action" value="approve">Approve & Lock</button>
                            </form>
                        <?php elseif ((string)$selectedQuotation['status'] === 'approved'): ?>
                            <form method="POST" action="/codesamplecaps/controllers/QuotationAdminController.php" class="history-list">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="quotation_id" value="<?php echo (int)$selectedQuotation['id']; ?>">
                                <input type="hidden" name="profit_margin_percent" value="<?php echo htmlspecialchars((string)($selectedQuotation['profit_margin_percent'] ?? 0)); ?>">
                                <div class="form-group">
                                    <label for="send_remarks">Send Note</label>
                                    <textarea id="send_remarks" name="remarks" rows="4" placeholder="Optional note before sending to client"></textarea>
                                </div>
                                <button class="btn-primary" type="submit" name="action" value="send_to_client">Finalize & Send</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">Select a quotation from the approval queue to continue.</div>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
