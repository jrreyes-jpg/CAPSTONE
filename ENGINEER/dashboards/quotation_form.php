<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/quotation_module.php';

require_role('engineer');

$userId = (int)($_SESSION['user_id'] ?? 0);
$quotationId = (int)($_GET['id'] ?? 0);
$prefillProjectId = (int)($_GET['project_id'] ?? 0);
$flash = quotation_module_consume_flash();
$bootstrap = quotation_module_bootstrap_tables($conn);
$tablesReady = (bool)($bootstrap['ready'] ?? false);
$csrfToken = quotation_module_csrf_token();
$projects = $tablesReady ? quotation_module_fetch_engineer_projects($conn, $userId) : [];
$foremen = $tablesReady ? quotation_module_fetch_foremen($conn) : [];
$inventoryOptions = $tablesReady ? quotation_module_fetch_inventory_options($conn) : [];
$assetOptions = $tablesReady ? quotation_module_fetch_asset_options($conn) : [];
$quotation = null;
$items = [];
$reviews = [];
$history = [];

if ($tablesReady && $quotationId > 0) {
    $quotation = quotation_module_fetch_quotation($conn, $quotationId);
    if (!$quotation || !quotation_module_user_can_access($quotation, 'engineer', $userId)) {
        quotation_module_set_flash('error', 'Quotation not found or inaccessible.');
        quotation_module_redirect('/codesamplecaps/ENGINEER/dashboards/quotations.php');
    }

    $items = quotation_module_fetch_quotation_items($conn, $quotationId);
    $reviews = quotation_module_fetch_reviews($conn, $quotationId);
    $history = quotation_module_fetch_history($conn, $quotationId);
}

$canEditDraft = $quotation === null || (((string)$quotation['status'] === 'draft') && (int)($quotation['is_locked'] ?? 0) === 0);
$canSubmitForReview = $quotation !== null && (string)$quotation['status'] === 'draft' && (int)($quotation['is_locked'] ?? 0) === 0;
$canSubmitForApproval = $quotation !== null && (string)$quotation['status'] === 'under_review';

if (empty($items)) {
    $items = [[
        'item_type' => 'material',
        'source_table' => '',
        'source_id' => '',
        'item_name' => '',
        'description' => '',
        'unit' => 'unit',
        'quantity' => 1,
        'rate' => 0,
        'hours' => 0,
        'days' => 0,
        'line_total' => 0,
    ]];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineer Quotation Form - Edge Automation</title>
    <link rel="stylesheet" href="../css/engineer-sidebar.css">
    <link rel="stylesheet" href="../css/engineer.css">
    <style>
        .quotation-shell { padding: 24px; display: grid; gap: 24px; }
        .panel { background: #fff; border-radius: 18px; box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08); padding: 24px; }
        .grid { display: grid; gap: 16px; }
        .grid.two { grid-template-columns: 2fr 1fr; align-items: start; }
        .grid.form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .flash { padding: 14px 16px; border-radius: 14px; font-weight: 600; }
        .flash.success { background: #dcfce7; color: #166534; }
        .flash.error { background: #fee2e2; color: #b91c1c; }
        h1, h2, h3 { margin: 0; color: #0f172a; }
        p { color: #475569; }
        .helper-copy { margin-top: 8px; color: #64748b; }
        .form-group { display: grid; gap: 8px; }
        .form-group label { font-weight: 700; color: #334155; font-size: 0.92rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px 14px; font: inherit; }
        .catalog-grid { display: grid; gap: 12px; max-height: 420px; overflow: auto; }
        .catalog-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; display: grid; gap: 8px; }
        .catalog-card strong { color: #0f172a; }
        .catalog-card small { color: #64748b; }
        .btn-row, .top-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .btn-primary, .btn-secondary, .btn-ghost, .btn-danger { border: 0; border-radius: 12px; padding: 12px 18px; text-decoration: none; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        .btn-ghost { background: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; }
        .btn-danger { background: #fee2e2; color: #b91c1c; }
        .status-pill { display: inline-flex; padding: 6px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .status-pill.is-draft { background: #e2e8f0; color: #334155; }
        .status-pill.is-review { background: #fef3c7; color: #92400e; }
        .status-pill.is-approval { background: #dbeafe; color: #1d4ed8; }
        .status-pill.is-approved { background: #dcfce7; color: #166534; }
        .status-pill.is-sent { background: #ede9fe; color: #6d28d9; }
        .status-pill.is-accepted { background: #ccfbf1; color: #115e59; }
        .status-pill.is-rejected { background: #fee2e2; color: #b91c1c; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td { border-bottom: 1px solid #e2e8f0; padding: 10px 8px; vertical-align: top; }
        .items-table th { color: #475569; font-size: 0.8rem; text-transform: uppercase; }
        .items-table input, .items-table select { min-width: 100px; }
        .totals-card { display: grid; gap: 12px; }
        .totals-row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .totals-row strong { color: #0f172a; }
        .timeline, .review-thread { display: grid; gap: 12px; }
        .timeline-card, .review-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; }
        .review-card small, .timeline-card small { color: #64748b; display: block; margin-bottom: 8px; }
        .notice-card { border: 1px dashed #cbd5e1; border-radius: 14px; padding: 18px; color: #64748b; }
        .readonly-banner { background: #eff6ff; color: #1d4ed8; padding: 14px 16px; border-radius: 14px; font-weight: 600; }
        @media (max-width: 1200px) { .grid.two { grid-template-columns: 1fr; } }
        @media (max-width: 760px) { .grid.form { grid-template-columns: 1fr; } .quotation-shell { padding: 16px; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../sidebar/sidebar_engineer.php'; ?>
<main class="main-content">
    <div class="quotation-shell">
        <?php if ($flash): ?>
            <div class="flash <?php echo htmlspecialchars((string)$flash['type']); ?>"><?php echo htmlspecialchars((string)$flash['message']); ?></div>
        <?php endif; ?>

        <?php if (!empty($bootstrap['errors'])): ?>
            <div class="flash error">
                Quotation setup failed: <?php echo htmlspecialchars(implode(' | ', array_unique(array_map('strval', $bootstrap['errors'])))); ?>
            </div>
        <?php endif; ?>

        <?php if (!$tablesReady): ?>
            <section class="panel">
                <h1>Quotation Setup Needed</h1>
                <p class="helper-copy">The app tried to create the quotation tables automatically, but they are still unavailable. Run <code>scripts/setup_quotation_tables.php</code> if the database account blocks table creation.</p>
            </section>
        <?php else: ?>
            <section class="panel">
                <div class="top-actions">
                    <div>
                        <h1><?php echo $quotation ? 'Quotation ' . htmlspecialchars((string)$quotation['quotation_no']) : 'Create New Quotation'; ?></h1>
                        <p class="helper-copy">Engineer owns the quotation draft. Foreman reviews only. Super Admin gives final approval.</p>
                    </div>
                    <div class="btn-row">
                        <a class="btn-secondary" href="/codesamplecaps/ENGINEER/dashboards/quotations.php">Back to List</a>
                        <?php if ($quotation): ?>
                            <span class="status-pill <?php echo htmlspecialchars(quotation_module_status_class((string)$quotation['status'])); ?>"><?php echo htmlspecialchars(quotation_module_status_label((string)$quotation['status'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <?php if ($quotation && !$canEditDraft): ?>
                <div class="readonly-banner">This quotation is no longer editable as a draft. You can still review comments and move it forward if it is under foreman review.</div>
            <?php endif; ?>

            <div class="grid two">
                <form class="grid" method="POST" action="/codesamplecaps/controllers/QuotationController.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="quotation_id" value="<?php echo (int)($quotation['id'] ?? 0); ?>">

                    <section class="panel grid">
                        <div>
                            <h2>Quotation Header</h2>
                            <p class="helper-copy">Link the quotation to the assigned project and choose the foreman reviewer.</p>
                        </div>
                        <div class="grid form">
                            <div class="form-group">
                                <label for="project_id">Project</label>
                                <select id="project_id" name="project_id" <?php echo $canEditDraft ? '' : 'disabled'; ?> required>
                                    <option value="">Select project</option>
                                    <?php foreach ($projects as $project): ?>
                                        <?php $selectedProjectId = (int)($quotation['project_id'] ?? $prefillProjectId); ?>
                                        <option value="<?php echo (int)$project['id']; ?>" <?php echo $selectedProjectId === (int)$project['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string)$project['project_name'] . ' | ' . (string)$project['client_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$canEditDraft): ?><input type="hidden" name="project_id" value="<?php echo (int)$quotation['project_id']; ?>"><?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="foreman_reviewer_id">Foreman Reviewer</label>
                                <select id="foreman_reviewer_id" name="foreman_reviewer_id" <?php echo $canEditDraft ? '' : 'disabled'; ?>>
                                    <option value="">Select foreman</option>
                                    <?php foreach ($foremen as $foreman): ?>
                                        <option value="<?php echo (int)$foreman['id']; ?>" <?php echo (int)($quotation['foreman_reviewer_id'] ?? 0) === (int)$foreman['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string)$foreman['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$canEditDraft && !empty($quotation['foreman_reviewer_id'])): ?><input type="hidden" name="foreman_reviewer_id" value="<?php echo (int)$quotation['foreman_reviewer_id']; ?>"><?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="title">Quotation Title</label>
                                <input id="title" type="text" name="title" value="<?php echo htmlspecialchars((string)($quotation['title'] ?? '')); ?>" <?php echo $canEditDraft ? '' : 'readonly'; ?> required>
                            </div>
                            <div class="form-group">
                                <label for="estimated_duration_days">Estimated Duration (Days)</label>
                                <input id="estimated_duration_days" type="number" min="1" name="estimated_duration_days" value="<?php echo htmlspecialchars((string)($quotation['estimated_duration_days'] ?? '')); ?>" <?php echo $canEditDraft ? '' : 'readonly'; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="scope_summary">Scope Summary</label>
                            <textarea id="scope_summary" name="scope_summary" rows="4" <?php echo $canEditDraft ? '' : 'readonly'; ?>><?php echo htmlspecialchars((string)($quotation['scope_summary'] ?? '')); ?></textarea>
                        </div>
                    </section>

                    <section class="panel grid">
                        <div class="top-actions">
                            <div>
                                <h2>Cost Breakdown</h2>
                                <p class="helper-copy">Materials, assets, manpower, and other costs roll up automatically into the quotation totals.</p>
                            </div>
                            <?php if ($canEditDraft): ?>
                                <div class="btn-row">
                                    <button class="btn-secondary" type="button" data-add-row="material">Add Material</button>
                                    <button class="btn-secondary" type="button" data-add-row="manpower">Add Manpower</button>
                                    <button class="btn-secondary" type="button" data-add-row="other">Add Other</button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="overflow:auto;">
                            <table class="items-table" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Item</th>
                                        <th>Description</th>
                                        <th>Unit</th>
                                        <th>Qty</th>
                                        <th>Hours</th>
                                        <th>Rate</th>
                                        <th>Line Total</th>
                                        <?php if ($canEditDraft): ?><th></th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody id="quotationItemsBody">
                                    <?php foreach ($items as $item): ?>
                                        <tr class="quotation-item-row">
                                            <td>
                                                <select name="item_type[]" class="item-type" <?php echo $canEditDraft ? '' : 'disabled'; ?>>
                                                    <?php foreach (['material', 'asset', 'manpower', 'other'] as $type): ?>
                                                        <option value="<?php echo $type; ?>" <?php echo (string)$item['item_type'] === $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" name="source_table[]" value="<?php echo htmlspecialchars((string)($item['source_table'] ?? '')); ?>">
                                                <input type="hidden" name="source_id[]" value="<?php echo htmlspecialchars((string)($item['source_id'] ?? '')); ?>">
                                            </td>
                                            <td><input type="text" name="item_name[]" value="<?php echo htmlspecialchars((string)$item['item_name']); ?>" <?php echo $canEditDraft ? '' : 'readonly'; ?> required></td>
                                            <td><input type="text" name="item_description[]" value="<?php echo htmlspecialchars((string)($item['description'] ?? '')); ?>" <?php echo $canEditDraft ? '' : 'readonly'; ?>></td>
                                            <td><input type="text" name="unit[]" value="<?php echo htmlspecialchars((string)($item['unit'] ?? 'unit')); ?>" <?php echo $canEditDraft ? '' : 'readonly'; ?>></td>
                                            <td><input type="number" step="0.01" min="0" name="quantity[]" class="item-quantity" value="<?php echo htmlspecialchars((string)($item['quantity'] ?? 0)); ?>" <?php echo $canEditDraft ? '' : 'readonly'; ?>></td>
                                            <td><input type="number" step="0.01" min="0" name="hours[]" class="item-hours" value="<?php echo htmlspecialchars((string)($item['hours'] ?? 0)); ?>" <?php echo $canEditDraft ? '' : 'readonly'; ?>></td>
                                            <td><input type="number" step="0.01" min="0" name="rate[]" class="item-rate" value="<?php echo htmlspecialchars((string)($item['rate'] ?? 0)); ?>" <?php echo $canEditDraft ? '' : 'readonly'; ?>></td>
                                            <td><input type="text" class="item-total" value="<?php echo htmlspecialchars(number_format((float)($item['line_total'] ?? 0), 2)); ?>" readonly></td>
                                            <?php if ($canEditDraft): ?><td><button type="button" class="btn-danger" data-remove-row>Remove</button></td><?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="btn-row">
                            <?php if ($canEditDraft): ?>
                                <button class="btn-primary" type="submit" name="action" value="save_draft">Save Draft</button>
                            <?php endif; ?>
                            <?php if ($canSubmitForReview): ?>
                                <button class="btn-secondary" type="submit" name="action" value="submit_review">Submit For Foreman Review</button>
                            <?php endif; ?>
                            <?php if ($canSubmitForApproval): ?>
                                <button class="btn-primary" type="submit" name="action" value="submit_for_approval">Submit For Super Admin Approval</button>
                            <?php endif; ?>
                        </div>
                    </section>
                </form>

                <div class="grid">
                    <section class="panel totals-card">
                        <h2>Live Totals</h2>
                        <div class="totals-row"><span>Materials</span><strong id="materialsTotal">PHP 0.00</strong></div>
                        <div class="totals-row"><span>Assets</span><strong id="assetsTotal">PHP 0.00</strong></div>
                        <div class="totals-row"><span>Manpower</span><strong id="manpowerTotal">PHP 0.00</strong></div>
                        <div class="totals-row"><span>Other</span><strong id="otherTotal">PHP 0.00</strong></div>
                        <div class="totals-row"><span>Total Cost</span><strong id="totalCost">PHP 0.00</strong></div>
                        <div class="totals-row"><span>Selling Price</span><strong id="sellingPrice">PHP 0.00</strong></div>
                    </section>

                    <section class="panel">
                        <h2>Materials Catalog</h2>
                        <p class="helper-copy">Quick-add from inventory to avoid retyping common material rows.</p>
                        <div class="catalog-grid">
                            <?php foreach ($inventoryOptions as $inventory): ?>
                                <article class="catalog-card">
                                    <strong><?php echo htmlspecialchars((string)$inventory['asset_name']); ?></strong>
                                    <small><?php echo htmlspecialchars((string)($inventory['asset_category'] ?? 'Material')); ?> | Stock: <?php echo (int)$inventory['quantity']; ?></small>
                                    <?php if ($canEditDraft): ?>
                                        <button type="button" class="btn-ghost" data-catalog-item data-item-type="material" data-source-table="inventory" data-source-id="<?php echo (int)$inventory['id']; ?>" data-item-name="<?php echo htmlspecialchars((string)$inventory['asset_name']); ?>" data-unit="unit">Add Material Row</button>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="panel">
                        <h2>Assets Catalog</h2>
                        <p class="helper-copy">Use this for equipment and tracked asset usage lines.</p>
                        <div class="catalog-grid">
                            <?php foreach ($assetOptions as $asset): ?>
                                <article class="catalog-card">
                                    <strong><?php echo htmlspecialchars((string)$asset['asset_name']); ?></strong>
                                    <small><?php echo htmlspecialchars((string)($asset['asset_type'] ?? 'Asset')); ?> | <?php echo htmlspecialchars((string)($asset['asset_status'] ?? 'available')); ?></small>
                                    <?php if ($canEditDraft): ?>
                                        <button type="button" class="btn-ghost" data-catalog-item data-item-type="asset" data-source-table="assets" data-source-id="<?php echo (int)$asset['id']; ?>" data-item-name="<?php echo htmlspecialchars((string)$asset['asset_name']); ?>" data-unit="day">Add Asset Row</button>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <?php if ($quotation): ?>
                        <section class="panel review-thread">
                            <h2>Review Notes</h2>
                            <?php if (!empty($reviews)): ?>
                                <?php foreach ($reviews as $review): ?>
                                    <article class="review-card">
                                        <small><?php echo htmlspecialchars((string)$review['full_name'] . ' | ' . quotation_module_format_datetime((string)$review['created_at'])); ?></small>
                                        <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$review['review_type']))); ?></strong>
                                        <p><?php echo nl2br(htmlspecialchars((string)$review['message'])); ?></p>
                                    </article>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notice-card">No review notes yet.</div>
                            <?php endif; ?>
                        </section>

                        <section class="panel timeline">
                            <h2>Status History</h2>
                            <?php if (!empty($history)): ?>
                                <?php foreach ($history as $entry): ?>
                                    <article class="timeline-card">
                                        <small><?php echo htmlspecialchars(quotation_module_format_datetime((string)$entry['created_at'])); ?></small>
                                        <strong><?php echo htmlspecialchars((string)$entry['full_name']); ?></strong>
                                        <p><?php echo htmlspecialchars(quotation_module_status_label((string)($entry['from_status'] ?? 'draft')) . ' -> ' . quotation_module_status_label((string)$entry['to_status'])); ?></p>
                                        <?php if (!empty($entry['remarks'])): ?><p><?php echo nl2br(htmlspecialchars((string)$entry['remarks'])); ?></p><?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notice-card">No workflow history yet.</div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>
<script>
    (function () {
        var tableBody = document.getElementById('quotationItemsBody');
        if (!tableBody) {
            return;
        }

        function currency(value) {
            return 'PHP ' + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function calculateRow(row) {
            var type = row.querySelector('.item-type').value;
            var quantity = parseFloat(row.querySelector('.item-quantity').value || '0');
            var hours = parseFloat(row.querySelector('.item-hours').value || '0');
            var rate = parseFloat(row.querySelector('.item-rate').value || '0');
            var total = type === 'manpower' ? hours * rate : quantity * rate;
            row.querySelector('.item-total').value = total.toFixed(2);
            return {type: type, total: total};
        }

        function recalcTotals() {
            var totals = {material: 0, asset: 0, manpower: 0, other: 0};
            tableBody.querySelectorAll('.quotation-item-row').forEach(function (row) {
                var rowData = calculateRow(row);
                totals[rowData.type] = (totals[rowData.type] || 0) + rowData.total;
            });
            var totalCost = totals.material + totals.asset + totals.manpower + totals.other;
            document.getElementById('materialsTotal').textContent = currency(totals.material);
            document.getElementById('assetsTotal').textContent = currency(totals.asset);
            document.getElementById('manpowerTotal').textContent = currency(totals.manpower);
            document.getElementById('otherTotal').textContent = currency(totals.other);
            document.getElementById('totalCost').textContent = currency(totalCost);
            document.getElementById('sellingPrice').textContent = currency(totalCost);
        }

        function buildRow(data) {
            var row = document.createElement('tr');
            row.className = 'quotation-item-row';
            row.innerHTML =
                '<td><select name="item_type[]" class="item-type"><option value="material">Material</option><option value="asset">Asset</option><option value="manpower">Manpower</option><option value="other">Other</option></select><input type="hidden" name="source_table[]" value=""><input type="hidden" name="source_id[]" value=""></td>' +
                '<td><input type="text" name="item_name[]" required></td>' +
                '<td><input type="text" name="item_description[]"></td>' +
                '<td><input type="text" name="unit[]" value="unit"></td>' +
                '<td><input type="number" step="0.01" min="0" name="quantity[]" class="item-quantity" value="1"></td>' +
                '<td><input type="number" step="0.01" min="0" name="hours[]" class="item-hours" value="0"></td>' +
                '<td><input type="number" step="0.01" min="0" name="rate[]" class="item-rate" value="0"></td>' +
                '<td><input type="text" class="item-total" value="0.00" readonly></td>' +
                '<td><button type="button" class="btn-danger" data-remove-row>Remove</button></td>';
            row.querySelector('.item-type').value = data.item_type || 'other';
            row.querySelector('input[name="source_table[]"]').value = data.source_table || '';
            row.querySelector('input[name="source_id[]"]').value = data.source_id || '';
            row.querySelector('input[name="item_name[]"]').value = data.item_name || '';
            row.querySelector('input[name="unit[]"]').value = data.unit || 'unit';
            if (data.item_type === 'asset') {
                row.querySelector('input[name="quantity[]"]').value = data.quantity || '1';
            }
            tableBody.appendChild(row);
            recalcTotals();
        }

        tableBody.addEventListener('input', recalcTotals);
        tableBody.addEventListener('change', recalcTotals);
        tableBody.addEventListener('click', function (event) {
            if (event.target.matches('[data-remove-row]')) {
                event.preventDefault();
                event.target.closest('tr').remove();
                recalcTotals();
            }
        });

        document.querySelectorAll('[data-add-row]').forEach(function (button) {
            button.addEventListener('click', function () {
                buildRow({item_type: button.getAttribute('data-add-row')});
            });
        });

        document.querySelectorAll('[data-catalog-item]').forEach(function (button) {
            button.addEventListener('click', function () {
                buildRow({
                    item_type: button.getAttribute('data-item-type'),
                    source_table: button.getAttribute('data-source-table'),
                    source_id: button.getAttribute('data-source-id'),
                    item_name: button.getAttribute('data-item-name'),
                    unit: button.getAttribute('data-unit')
                });
            });
        });

        recalcTotals();
    })();
</script>
</body>
</html>
