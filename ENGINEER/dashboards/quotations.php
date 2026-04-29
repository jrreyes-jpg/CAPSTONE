<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/quotation_module.php';

require_role('engineer');

$userId = (int)($_SESSION['user_id'] ?? 0);
$flash = quotation_module_consume_flash();
$bootstrap = quotation_module_bootstrap_tables($conn);
$tablesReady = (bool)($bootstrap['ready'] ?? false);
$quotations = $tablesReady ? quotation_module_fetch_quotations($conn, 'engineer', $userId) : [];
$projects = $tablesReady ? quotation_module_fetch_engineer_projects($conn, $userId) : [];

$statusCounts = [
    'draft' => 0,
    'under_review' => 0,
    'for_approval' => 0,
    'approved' => 0,
];

foreach ($quotations as $quotation) {
    $status = (string)($quotation['status'] ?? '');
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineer Quotations - Edge Automation</title>
    <link rel="stylesheet" href="../css/engineer-sidebar.css">
    <link rel="stylesheet" href="../css/engineer.css">
    <style>
        .quotation-shell { padding: 24px; display: grid; gap: 24px; }
        .quotation-hero, .quotation-panel { background: #fff; border-radius: 18px; box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08); padding: 24px; }
        .quotation-hero { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; }
        .quotation-hero h1, .quotation-panel h2 { margin: 0; color: #0f172a; }
        .quotation-hero p { margin: 8px 0 0; color: #475569; max-width: 700px; }
        .quotation-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-primary, .btn-secondary { border: 0; border-radius: 12px; padding: 12px 18px; text-decoration: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .stat-card { background: linear-gradient(180deg, #fff, #f8fafc); border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; }
        .stat-card span { display: block; color: #64748b; font-size: 0.85rem; margin-bottom: 8px; }
        .stat-card strong { font-size: 2rem; color: #0f172a; }
        .quotation-table { width: 100%; border-collapse: collapse; }
        .quotation-table th, .quotation-table td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 0.95rem; }
        .quotation-table th { color: #475569; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-pill { display: inline-flex; padding: 6px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .status-pill.is-draft { background: #e2e8f0; color: #334155; }
        .status-pill.is-review { background: #fef3c7; color: #92400e; }
        .status-pill.is-approval { background: #dbeafe; color: #1d4ed8; }
        .status-pill.is-approved { background: #dcfce7; color: #166534; }
        .status-pill.is-sent { background: #ede9fe; color: #6d28d9; }
        .status-pill.is-accepted { background: #ccfbf1; color: #115e59; }
        .status-pill.is-rejected { background: #fee2e2; color: #b91c1c; }
        .flash { padding: 14px 16px; border-radius: 14px; font-weight: 600; }
        .flash.success { background: #dcfce7; color: #166534; }
        .flash.error { background: #fee2e2; color: #b91c1c; }
        .empty-state { padding: 32px; border: 1px dashed #cbd5e1; border-radius: 16px; color: #64748b; text-align: center; }
        .helper-copy { color: #64748b; margin: 6px 0 0; }
        @media (max-width: 960px) {
            .quotation-hero { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .quotation-shell { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../sidebar/sidebar_engineer.php'; ?>
<main class="main-content">
    <div class="quotation-shell">
        <?php if ($flash): ?>
            <div class="flash <?php echo htmlspecialchars((string)$flash['type']); ?>">
                <?php echo htmlspecialchars((string)$flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($bootstrap['errors'])): ?>
            <div class="flash error">
                Quotation setup failed: <?php echo htmlspecialchars(implode(' | ', array_unique(array_map('strval', $bootstrap['errors'])))); ?>
            </div>
        <?php endif; ?>

        <section class="quotation-hero">
            <div>
                <h1>Quotation Workspace</h1>
                <p>Create project quotations, prepare material and manpower estimates, send them through foreman review, and submit them for super admin approval from the Engineer dashboard.</p>
            </div>
            <div class="quotation-actions">
                <a class="btn-primary" href="/codesamplecaps/ENGINEER/dashboards/quotation_form.php">Create Quotation</a>
                <a class="btn-secondary" href="/codesamplecaps/ENGINEER/dashboards/projects.php">Back to Projects</a>
            </div>
        </section>

        <?php if (!$tablesReady): ?>
            <section class="quotation-panel">
                <h2>Setup Needed</h2>
                <p class="helper-copy">The system tried to prepare quotation tables automatically, but they are still unavailable. Run <code>scripts/setup_quotation_tables.php</code> if the database user cannot create tables from the app.</p>
            </section>
        <?php else: ?>
            <section class="stats-grid" aria-label="Quotation summary">
                <article class="stat-card"><span>Draft Quotations</span><strong><?php echo (int)$statusCounts['draft']; ?></strong></article>
                <article class="stat-card"><span>Under Foreman Review</span><strong><?php echo (int)$statusCounts['under_review']; ?></strong></article>
                <article class="stat-card"><span>Ready For Approval</span><strong><?php echo (int)$statusCounts['for_approval']; ?></strong></article>
                <article class="stat-card"><span>Approved / Locked</span><strong><?php echo (int)$statusCounts['approved']; ?></strong></article>
            </section>

            <section class="quotation-panel">
                <h2>Assigned Projects</h2>
                <p class="helper-copy">These are the projects where you can prepare quotations.</p>
                <?php if (!empty($projects)): ?>
                    <table class="quotation-table">
                        <thead><tr><th>Project</th><th>Client</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$project['project_name']); ?></td>
                                <td><?php echo htmlspecialchars((string)$project['client_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst((string)$project['status'])); ?></td>
                                <td><a class="btn-secondary" href="/codesamplecaps/ENGINEER/dashboards/quotation_form.php?project_id=<?php echo (int)$project['id']; ?>">Start Quotation</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No assigned projects are available for quotation creation yet.</div>
                <?php endif; ?>
            </section>

            <section class="quotation-panel">
                <h2>My Quotations</h2>
                <p class="helper-copy">Track which quotations are still in draft, under foreman review, or already waiting for super admin approval.</p>
                <?php if (!empty($quotations)): ?>
                    <table class="quotation-table">
                        <thead>
                        <tr>
                            <th>Quotation No.</th>
                            <th>Project</th>
                            <th>Foreman Reviewer</th>
                            <th>Total Cost</th>
                            <th>Selling Price</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($quotations as $quotation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$quotation['quotation_no']); ?></td>
                                <td><?php echo htmlspecialchars((string)$quotation['project_name']); ?></td>
                                <td><?php echo htmlspecialchars((string)($quotation['foreman_name'] ?? 'Not assigned')); ?></td>
                                <td><?php echo htmlspecialchars(quotation_module_format_currency($quotation['total_cost'] ?? 0)); ?></td>
                                <td><?php echo htmlspecialchars(quotation_module_format_currency($quotation['selling_price'] ?? 0)); ?></td>
                                <td><span class="status-pill <?php echo htmlspecialchars(quotation_module_status_class((string)$quotation['status'])); ?>"><?php echo htmlspecialchars(quotation_module_status_label((string)$quotation['status'])); ?></span></td>
                                <td><?php echo htmlspecialchars(quotation_module_format_datetime((string)$quotation['updated_at'])); ?></td>
                                <td><a class="btn-secondary" href="/codesamplecaps/ENGINEER/dashboards/quotation_form.php?id=<?php echo (int)$quotation['id']; ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No quotations created yet. Start from one of your assigned projects.</div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
