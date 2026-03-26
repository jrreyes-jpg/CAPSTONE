<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_role('super_admin');

/* CHECK DB CONNECTION */
if (!$conn) {
    die("DB Connection failed: " . mysqli_connect_error());
}

/* HANDLE CREATE PROJECT FIRST (BEFORE ANY OUTPUT) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {

    if (!isset($_SESSION['user_id'])) {
        die("User not logged in");
    }

    $name = trim($_POST['project_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO projects (project_name, description, status, created_by) VALUES (?, ?, 'ongoing', ?)");

        if (!$stmt) {
            die("Insert Prepare Error: " . $conn->error);
        }

        $stmt->bind_param("ssi", $name, $desc, $_SESSION['user_id']);

        if (!$stmt->execute()) {
            die("Insert Execute Error: " . $stmt->error);
        }

        $stmt->close();

        header("Location: projects.php");
        exit();
    }
}

/* FETCH PROJECTS AFTER HANDLING POST */
$projects = $conn->query("SELECT * FROM projects ORDER BY id DESC");

if (!$projects) {
    die("Projects Query Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Projects</title>
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/admin_dashboard.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

</head>
<body class="page-loaded">
<canvas id="particles"></canvas>
<?php include __DIR__ . '/../components/sidebar_super_admin.php'; ?>

<div class="main-content">
        <h1>📁 Projects</h1>

    <!-- CREATE PROJECT -->
    <form method="POST">
        <input type="hidden" name="action" value="create_project">
        <input type="text" name="project_name" placeholder="Project Name" required>
        <input type="text" name="description" placeholder="Description">
        <button type="submit">Add Project</button>
    </form>

    <hr>

    <!-- PROJECT LIST -->
    <div class="projects">

<?php if ($projects->num_rows > 0): ?>

    <?php while ($p = $projects->fetch_assoc()): ?>

        <div class="card">
            <h3><?= htmlspecialchars($p['project_name']); ?></h3>
            <p><?= htmlspecialchars($p['description']); ?></p>

            <!-- TABS -->
            <div>
                <button onclick="showTab(<?= $p['id']; ?>, 'tasks')">Tasks</button>
                <button onclick="showTab(<?= $p['id']; ?>, 'team')">Team</button>
            </div>

            <!-- TASKS -->
            <div id="tasks-<?= $p['id']; ?>" class="tab">

                <?php
                $stmt = $conn->prepare("SELECT task_name, status FROM tasks WHERE project_id=?");
                if ($stmt) {
                    $stmt->bind_param("i", $p['id']);
                    $stmt->execute();
                    $stmt->bind_result($task_name, $status);

                    while ($stmt->fetch()):
                ?>
                        <p>📝 <?= htmlspecialchars($task_name); ?> (<?= htmlspecialchars($status); ?>)</p>
                <?php endwhile;
                    $stmt->close();
                } ?>
            </div>

            <!-- TEAM -->
            <div id="team-<?= $p['id']; ?>" class="tab" style="display:none;">

                <?php
                $stmt2 = $conn->prepare("
                    SELECT u.full_name 
                    FROM project_assignments pa
                    JOIN users u ON u.id = pa.engineer_id
                    WHERE pa.project_id=?
                ");

                if ($stmt2) {
                    $stmt2->bind_param("i", $p['id']);
                    $stmt2->execute();
                    $stmt2->bind_result($full_name);

                    while ($stmt2->fetch()):
                ?>
                        <p>👷 <?= htmlspecialchars($full_name); ?></p>
                <?php endwhile;
                    $stmt2->close();
                } ?>
            </div>

        </div>

    <?php endwhile; ?>

<?php else: ?>
    <p>No projects found.</p>
<?php endif; ?>

</div>

<script>
function showTab(id, type) {
    const tasks = document.getElementById('tasks-'+id);
    const team = document.getElementById('team-'+id);

    tasks.style.display = 'none';
    team.style.display = 'none';

    const active = document.getElementById(type+'-'+id);
    active.style.display = 'block';
    active.style.opacity = 0;

    setTimeout(() => {
        active.style.opacity = 1;
    }, 50);
}
</script>

<script src="/codesamplecaps/public/assets/js/script.js"></script>
</body>
</html>