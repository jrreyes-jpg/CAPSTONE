<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/audit_log.php';
require_once __DIR__ . '/../includes/engineer_helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'engineer') {
    header('Location: /codesamplecaps/LOGIN/php/login.php');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$taskStatusOptions = ['pending', 'ongoing', 'completed', 'delayed'];
$data = engineer_fetch_data($conn, $userId, $taskStatusOptions);
$flash = engineer_consume_flash();
$priorityCards = $data['priority_cards'];
$recentUpdates = array_slice($data['recent_updates'], 0, 4);
$assignedProjects = array_slice($data['assigned_projects'], 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineer Overview - Edge Automation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/engineer-sidebar.css">
    <link rel="stylesheet" href="../css/engineer.css">
</head>
<body>

<button class="sidebar-mobile-toggle" type="button" aria-label="Toggle menu" data-sidebar-mobile-toggle>
    <span></span>
    <span></span>
    <span></span>
</button>

<div class="sidebar-overlay" data-sidebar-overlay></div>

<?php include '../sidebar/sidebar_engineer.php'; ?>

<div class="main-content">
    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars((string)($flash['type'] ?? 'success')); ?>">
            <?php echo htmlspecialchars((string)($flash['message'] ?? '')); ?>
        </div>
    <?php endif; ?>


    <div class="stats-grid">
        <div class="stat-card">
            <h4>Assigned Projects</h4>
            <p><?php echo (int)$data['assigned_count']; ?></p>
        </div>
        <div class="stat-card">
            <h4>Active Projects</h4>
            <p><?php echo (int)$data['in_progress_count']; ?></p>
        </div>
        <div class="stat-card">
            <h4>Completed Projects</h4>
            <p><?php echo (int)$data['completed_count']; ?></p>
        </div>
        <div class="stat-card">
            <h4>Open Tasks</h4>
            <p><?php echo (int)$data['open_task_count']; ?></p>
        </div>
    </div>

    <section class="priorities-panel">
        <div class="section-heading">
            <div>
                <p class="section-kicker">Today's Priorities</p>
                <h2>Start with what needs action first.</h2>
            </div>
        </div>
        <div class="priority-grid">
            <?php foreach ($priorityCards as $priorityCard): ?>
                <article class="priority-card priority-card--<?php echo htmlspecialchars((string)$priorityCard['tone']); ?>">
                    <span class="priority-card__label"><?php echo htmlspecialchars((string)$priorityCard['title']); ?></span>
                    <strong><?php echo (int)$priorityCard['count']; ?></strong>
                    <a href="../dashboards/tasks.php?quick=<?php echo urlencode((string)$priorityCard['filter']); ?>" class="priority-card__action">
                        <?php echo htmlspecialchars((string)$priorityCard['action']); ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="updates-panel">
        <div class="section-heading">
            <div>
                <p class="section-kicker">Recent Updates</p>
                <h2>Latest progress reports</h2>
            </div>
            <a class="btn btn-ghost btn-small" href="../dashboards/progress_updates.php">View All Updates</a>
        </div>
        <?php if (!empty($recentUpdates)): ?>
            <div class="updates-list">
                <?php foreach ($recentUpdates as $update): ?>
                    <article class="update-card">
                        <div class="update-card__topline">
                            <strong><?php echo htmlspecialchars((string)$update['task_name']); ?></strong>
                            <span><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string)$update['created_at']))); ?></span>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars((string)$update['progress_note'])); ?></p>
                        <div class="update-card__meta">
                            <span><?php echo htmlspecialchars((string)$update['project_name']); ?></span>
                            <?php if (!empty($update['status_snapshot'])): ?>
                                <span>Status: <?php echo htmlspecialchars(ucfirst((string)$update['status_snapshot'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-data"><p>No progress reports yet.</p></div>
        <?php endif; ?>
    </div>

    <section>
        <div class="section-heading">
            <div>
                <p class="section-kicker">Projects Preview</p>
                <h2>Assigned projects at a glance</h2>
            </div>
            <a class="btn btn-ghost btn-small" href="../dashboards/projects.php">Open My Projects</a>
        </div>
        <div class="projects-grid">
            <?php if (!empty($assignedProjects)): ?>
                <?php foreach ($assignedProjects as $project): ?>
                    <?php
                    $projectTotalTasks = (int)($project['total_tasks'] ?? 0);
                    $projectCompletedTasks = (int)($project['completed_tasks'] ?? 0);
                    $projectProgressPercent = $projectTotalTasks > 0 ? (int)round(($projectCompletedTasks / $projectTotalTasks) * 100) : 0;
                    ?>
                    <div class="project-card">
                        <div class="project-card__topline">
                            <div class="project-name"><?php echo htmlspecialchars((string)$project['project_name']); ?></div>
                            <span class="status <?php echo htmlspecialchars((string)$project['status']); ?>"><?php echo htmlspecialchars(ucfirst((string)$project['status'])); ?></span>
                        </div>
                        <p><strong>Client:</strong> <?php echo htmlspecialchars((string)($project['client_name'] ?? 'N/A')); ?></p>
                        <p><strong>Project Owner:</strong> <?php echo htmlspecialchars((string)($project['project_owner_name'] ?? 'N/A')); ?></p>
                        <div class="project-progress">
                            <div class="project-progress__meta">
                                <strong><?php echo $projectProgressPercent; ?>%</strong>
                                <span><?php echo $projectCompletedTasks; ?> of <?php echo $projectTotalTasks; ?> tasks done</span>
                            </div>
                            <div class="project-progress__bar">
                                <span style="width: <?php echo $projectProgressPercent; ?>%;"></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data no-data-full"><p>No assigned projects yet.</p></div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script src="../js/engineer.js"></script>

</body>
</html>
