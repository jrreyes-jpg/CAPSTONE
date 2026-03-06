<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
    header("Location: /codesamplecaps/public/login.php");
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

/* PROJECT SUMMARY COUNTS */
$totalProjects = $conn->prepare("SELECT COUNT(*) FROM projects WHERE client_id=?");
$totalProjects->bind_param("i", $user_id);
$totalProjects->execute();
$totalProjects->bind_result($totalCount);
$totalProjects->fetch();
$totalProjects->close();

$ongoing = $conn->prepare("SELECT COUNT(*) FROM projects WHERE client_id=? AND status='ongoing'");
$ongoing->bind_param("i", $user_id);
$ongoing->execute();
$ongoing->bind_result($ongoingCount);
$ongoing->fetch();
$ongoing->close();

$completed = $conn->prepare("SELECT COUNT(*) FROM projects WHERE client_id=? AND status='completed'");
$completed->bind_param("i", $user_id);
$completed->execute();
$completed->bind_result($completedCount);
$completed->fetch();
$completed->close();

/* TOTAL ENGINEERS COUNT (used in stats) */
$totalEngineersStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role='engineer'");
$totalEngineersStmt->execute();
$totalEngineersStmt->bind_result($availCount);
$totalEngineersStmt->fetch();
$totalEngineersStmt->close();

/* FETCH ENGINEERS */
$engineersStmt = $conn->prepare("SELECT id AS user_id, full_name, status FROM users WHERE role='engineer' ORDER BY full_name ASC");
$engineersStmt->execute();
$available_engineers = $engineersStmt->get_result();

/* FETCH CLIENT'S PROJECTS */
$projectsStmt = $conn->prepare("
SELECT p.*, u.full_name AS engineer_name
FROM projects p
LEFT JOIN users u ON u.id = (
    SELECT pa.user_id FROM project_assignments pa
    WHERE pa.project_id = p.project_id AND pa.role_in_project='engineer'
    LIMIT 1
)
WHERE p.client_id=?
ORDER BY p.created_at DESC
");
$projectsStmt->bind_param("i", $user_id);
$projectsStmt->execute();
$client_projects = $projectsStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Dashboard - Edge Automation</title>
<link rel="stylesheet" href="/codesamplecaps/public/assets/css/global.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    body { background: #f5f7fa; font-family: 'Poppins', sans-serif; }
    .main-content { margin-left: 250px; padding: 30px; }
    h1 { color: #2c3e50; margin-bottom: 22px; }
    h2 { color: #2c3e50; border-bottom: 3px solid #0f9d38; padding-bottom: 10px; margin-bottom: 16px; }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(170px, 1fr));
        gap: 14px;
        margin-bottom: 26px;
    }
    .stat-card {
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        text-align: center;
    }
    .stat-card h4 { color: #7f8c8d; font-size: 13px; margin-bottom: 6px; }
    .stat-card p { font-size: 30px; font-weight: 700; color: #0f9d38; }

    .tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 18px 0 22px;
        border-bottom: 2px solid #ecf0f1;
        padding-bottom: 8px;
    }
    .tab {
        padding: 10px 14px;
        cursor: pointer;
        background: #fff;
        border: 1px solid #dbe4ea;
        border-radius: 8px;
        font-weight: 600;
        color: #5e6b78;
        transition: all 0.2s ease;
    }
    .tab.active { color: #0f9d38; border-color: #0f9d38; }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .table-wrap {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    table.responsive-table {
        width: 100%;
        min-width: 850px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .responsive-table th,
    .responsive-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #edf2f7;
        text-align: left;
        vertical-align: middle;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 13px;
    }

    .responsive-table th {
        background: #f8fafc;
        color: #64748b;
        font-weight: 600;
    }

    .status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
    }
    .status.pending { background: #fff3cd; color: #8a6d3b; }
    .status.ongoing { background: #d1ecf1; color: #0c5460; }
    .status.completed { background: #d4edda; color: #155724; }

    .btn {
        padding: 8px 12px;
        background: #0f9d38;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
    }
    .btn:hover { background: #087f23; }
    .btn:disabled { background: #b8c2cc; cursor: not-allowed; }

    .profile-form {
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        max-width: 520px;
    }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
    .form-group input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 7px; }
    .no-data { padding: 24px; color: #8b96a3; text-align: center; }

    @media (max-width: 1024px) {
        .main-content { margin-left: 0; padding: 18px; }
        .stats-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
        table.responsive-table { min-width: 720px; }
    }

    @media (max-width: 768px) {
        body { overflow-x: hidden; }
        .main-content { margin-left: 0; padding: 14px; }
        .stats-grid { grid-template-columns: 1fr; }
        .tab { width: 100%; text-align: center; }
        table.responsive-table { min-width: 680px; }
        .responsive-table th,
        .responsive-table td { font-size: 12px; padding: 10px 8px; }
        .btn { font-size: 11px; padding: 7px 10px; }
    }
</style>
</head>
<body>

<?php include __DIR__ . '/../components/sidebar_client.php'; ?>

<div class="main-content">
    <h1>💼 Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>

    <div class="stats-grid">
        <div class="stat-card"><h4>📁 Your Projects</h4><p><?php echo $totalCount; ?></p></div>
        <div class="stat-card"><h4>⏳ In Progress</h4><p><?php echo $ongoingCount; ?></p></div>
        <div class="stat-card"><h4>✅ Completed</h4><p><?php echo $completedCount; ?></p></div>
        <div class="stat-card"><h4>👷 Engineers</h4><p><?php echo $availCount; ?></p></div>
    </div>

    <div class="tabs">
        <button class="tab active" onclick="showTab('engineers-tab', this)">👷 Browse Engineers</button>
        <button class="tab" onclick="showTab('projects-tab', this)">📁 My Projects</button>
        <button class="tab" onclick="showTab('profile-tab', this)">⚙️ Profile</button>
    </div>

    <div id="engineers-tab" class="tab-content active">
        <h2>Available Engineers</h2>
        <div class="table-wrap">
            <table class="responsive-table">
                <colgroup>
                    <col style="width: 36%;">
                    <col style="width: 18%;">
                    <col style="width: 18%;">
                    <col style="width: 28%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Active Projects</th>
                        <th>Capacity</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($available_engineers->num_rows > 0):
                        $countActiveStmt = $conn->prepare("SELECT COUNT(*) FROM project_assignments pa JOIN projects p ON pa.project_id = p.project_id WHERE pa.user_id=? AND pa.role_in_project='engineer' AND p.status!='completed'");
                        while ($engineer = $available_engineers->fetch_assoc()):
                            $engId = (int)$engineer['user_id'];
                            $countActiveStmt->bind_param("i", $engId);
                            $countActiveStmt->execute();
                            $countActiveStmt->bind_result($activeProjects);
                            $countActiveStmt->fetch();
                            $capacity = 1;
                            $slotsLeft = $capacity - $activeProjects;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($engineer['full_name']); ?></td>
                            <td><?php echo (int)$activeProjects; ?></td>
                            <td><?php echo (int)$capacity; ?></td>
                            <td>
                                <?php if ($slotsLeft > 0): ?>
                                    <button class="btn" onclick="hireEngineer(<?php echo $engId; ?>, '<?php echo htmlspecialchars($engineer['full_name']); ?>')">Request</button>
                                <?php else: ?>
                                    <button class="btn" disabled>Fully Booked</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; $countActiveStmt->close(); else: ?>
                        <tr><td colspan="4" class="no-data">No available engineers.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="projects-tab" class="tab-content">
        <h2>My Projects</h2>
        <div class="table-wrap">
            <table class="responsive-table">
                <colgroup>
                    <col style="width: 28%;">
                    <col style="width: 16%;">
                    <col style="width: 24%;">
                    <col style="width: 32%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Engineer</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($client_projects->num_rows > 0): while ($proj = $client_projects->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($proj['project_name']); ?></td>
                            <td><span class="status <?php echo htmlspecialchars($proj['status']); ?>"><?php echo ucfirst(htmlspecialchars($proj['status'])); ?></span></td>
                            <td><?php echo htmlspecialchars($proj['engineer_name'] ?? 'Not Assigned'); ?></td>
                            <td><?php echo htmlspecialchars(substr($proj['description'] ?? '', 0, 120)); ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="4" class="no-data">No projects yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="profile-tab" class="tab-content">
        <h2>Profile Settings</h2>
        <div class="profile-form">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" value="<?php echo htmlspecialchars($_SESSION['name']); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" disabled>
            </div>
            <button class="btn" onclick="window.location.href='/codesamplecaps/views/dashboards/change_password.php'">🔐 Change Password</button>
        </div>
    </div>
</div>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}

function hireEngineer(engineerId, engineerName) {
    if (confirm('Request ' + engineerName + '?')) {
        alert('Request submitted. Backend workflow can be connected next.');
    }
}
</script>

</body>
</html>
