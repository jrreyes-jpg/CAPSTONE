<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/quotation_module.php';

require_role('client');

$userId = (int)($_SESSION['user_id'] ?? 0);
$flash = quotation_module_consume_flash();
$csrfToken = quotation_module_csrf_token();
$tablesReady = quotation_module_tables_ready($conn);
$quotations = $tablesReady ? quotation_module_fetch_quotations($conn, 'client', $userId) : [];
$quotationId = (int)($_GET['id'] ?? 0);
$selectedQuotation = null;

if ($quotationId > 0 && $tablesReady) {
    $selectedQuotation = quotation_module_fetch_quotation($conn, $quotationId);
    if (!$selectedQuotation || !quotation_module_user_can_access($selectedQuotation, 'client', $userId)) {
        quotation_module_set_flash('error', 'Quotation not found in your account.');
        quotation_module_redirect('/codesamplecaps/CLIENT/dashboards/quotations.php');
    }
} elseif (!empty($quotations)) {
    $selectedQuotation = quotation_module_fetch_quotation($conn, (int)$quotations[0]['id']);
}

$items = $selectedQuotation ? quotation_module_fetch_quotation_items($conn, (int)$selectedQuotation['id']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quotations - Edge Automation</title>
    <link rel="stylesheet" href="../css/client_sidebar.css">
    <link rel="stylesheet" href="../css/client_dashboard.css">
    <style>
        .quotation-shell { padding: 24px; display: grid; gap: 24px; }
        .grid { display: grid; gap: 20px; grid-template-columns: 320px 1fr; }
        .panel { background: #fff; border-radius: 18px; box-shadow: 0 16px 38px rgba(15,23,42,.08); padding: 22px; }
        .queue-list { display: grid; gap: 12px; }
        .queue-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; }
        .queue-card.active { border-color: #0f766e; }
        .queue-card a { color: inherit; text-decoration: none; display: grid; gap: 6px; }
        .status-pill { display: inline-flex; padding: 6px 10px; border-radius: 999px; font-size: .8rem; font-weight: 700; }
        .status-pill.is-sent { background: #ede9fe; color: #6d28d9; }
        .status-pill.is-accepted { background: #ccfbf1; color: #115e59; }
        .status-pill.is-rejected { background: #fee2e2; color: #b91c1c; }
        .status-pill.is-approved { background: #dcfce7; color: #166534; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td { border-bottom: 1px solid #e2e8f0; padding: 10px 8px; text-align: left; }
        .items-table th { font-size: .8rem; color: #475569; text-transform: uppercase; }
        .form-group { display: grid; gap: 8px; }
        .form-group textarea { width: 100%; min-height: 120px; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; font: inherit; }
        .btn-primary, .btn-danger { border: 0; border-radius: 12px; padding: 12px 16px; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-danger { background: #fee2e2; color: #b91c1c; }
        .flash { padding: 14px 16px; border-radius: 14px; font-weight: 600; }
        .flash.success { background: #dcfce7; color: #166534; }
        .flash.error { background: #fee2e2; color: #b91c1c; }
        .empty-state { border: 1px dashed #cbd5e1; border-radius: 16px; padding: 24px; color: #64748b; text-align: center; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } .quotation-shell { padding: 16px; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../sidebar/client_sidebar.php'; ?>
<main class="main-content" id="mainContent">
    <div class="quotation-shell">
        <?php if ($flash): ?><div class="flash <?php echo htmlspecialchars((string)$flash['type']); ?>"><?php echo htmlspecialchars((string)$flash['message']); ?></div><?php endif; ?>
        <section class="panel">
            <h1>My Quotations</h1>
            <p>Review project quotations sent to your account and respond with accept or reject.</p>
        </section>

        <?php if (!$tablesReady): ?>
            <section class="panel"><div class="empty-state">Quotation records are not available yet. Please contact the system administrator.</div></section>
        <?php else: ?>
            <div class="grid">
                <aside class="panel">
                    <h2>Quotation List</h2>
                    <div class="queue-list">
                        <?php if (!empty($quotations)): ?>
                            <?php foreach ($quotations as $quotation): ?>
                                <article class="queue-card <?php echo $selectedQuotation && (int)$selectedQuotation['id'] === (int)$quotation['id'] ? 'active' : ''; ?>">
                                    <a href="/codesamplecaps/CLIENT/dashboards/quotations.php?id=<?php echo (int)$quotation['id']; ?>">
                                        <strong><?php echo htmlspecialchars((string)$quotation['quotation_no']); ?></strong>
                                        <span><?php echo htmlspecialchars((string)$quotation['project_name']); ?></span>
                                        <span class="status-pill <?php echo htmlspecialchars(quotation_module_status_class((string)$quotation['status'])); ?>"><?php echo htmlspecialchars(quotation_module_status_label((string)$quotation['status'])); ?></span>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">No quotations have been sent to you yet.</div>
                        <?php endif; ?>
                    </div>
                </aside>

                <section class="panel">
                    <?php if ($selectedQuotation): ?>
                        <h2><?php echo htmlspecialchars((string)$selectedQuotation['quotation_no']); ?> | <?php echo htmlspecialchars((string)$selectedQuotation['project_name']); ?></h2>
                        <p>Prepared by: <?php echo htmlspecialchars((string)$selectedQuotation['engineer_name']); ?> | Selling Price: <?php echo htmlspecialchars(quotation_module_format_currency($selectedQuotation['selling_price'] ?? 0)); ?></p>

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

                        <?php if ((string)$selectedQuotation['status'] === 'sent'): ?>
                            <form method="POST" action="/codesamplecaps/controllers/QuotationClientController.php" style="margin-top:20px; display:grid; gap:12px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="quotation_id" value="<?php echo (int)$selectedQuotation['id']; ?>">
                                <div class="form-group">
                                    <label for="note">Response Note</label>
                                    <textarea id="note" name="note" placeholder="Optional acceptance or rejection note"></textarea>
                                </div>
                                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                                    <button class="btn-primary" type="submit" name="action" value="client_accept">Accept</button>
                                    <button class="btn-danger" type="submit" name="action" value="client_reject">Reject</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="empty-state" style="margin-top:20px;">This quotation is currently <strong><?php echo htmlspecialchars(quotation_module_status_label((string)$selectedQuotation['status'])); ?></strong>. Client response becomes available only after it is sent.</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">Select a quotation to view the full breakdown.</div>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
