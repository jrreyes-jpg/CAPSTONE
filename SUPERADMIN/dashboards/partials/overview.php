<div id="dashboard" class="tab-content <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>">
    <section class="dashboard-grid">
        <section class="dashboard-panel summary-panel">
            <div class="panel-heading">
                <div>
                    <h1 class="dashboard-section-title">Summary</h1>
                </div>
            </div>
            <div class="metric-strip metric-strip-compact">
                <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=users" class="metric-tile metric-tile-link metric-tile-people">
                    <span>People</span>
                    <strong><?php echo $totalUsers; ?></strong>
                    <small>Open manage users</small>
                </a>
                <a href="/codesamplecaps/SUPERADMIN/sidebar/projects.php?status=active" class="metric-tile metric-tile-link metric-tile-projects">
                    <span>Projects</span>
                    <strong><?php echo $totalProjects; ?></strong>
                    <small><?php echo $ongoingProjects; ?> active now</small>
                </a>
                <a href="/codesamplecaps/SUPERADMIN/sidebar/projects.php?status=active" class="metric-tile metric-tile-link metric-tile-tasks">
                    <span>Tasks</span>
                    <strong><?php echo $openTasks; ?></strong>
                    <small>Review active project work</small>
                </a>
                <a href="/codesamplecaps/SUPERADMIN/sidebar/scan_history.php" class="metric-tile metric-tile-link metric-tile-scans">
                    <span>Scans Today</span>
                    <strong><?php echo $scansToday; ?></strong>
                    <small>Open scan history</small>
                </a>
            </div>
        </section>

        <section class="dashboard-panel analytics-panel">
            <div class="panel-heading">
                <div>
                    <h2 class="dashboard-section-title">Operations Analytics</h2>
                </div>
            </div>
            <div class="mini-overview">
                <article class="mini-overview-card">
                    <span>Project Completion</span>
                    <strong><?php echo $projectCompletionRate; ?>%</strong>
                    <small><?php echo $completedProjects; ?> of <?php echo $totalProjects; ?> projects completed</small>
                </article>
                <article class="mini-overview-card">
                    <span>Task Delay Pressure</span>
                    <strong><?php echo $taskDelayRate; ?>%</strong>
                    <small><?php echo $delayedTasks; ?> delayed out of <?php echo $totalTasks; ?> total tasks</small>
                </article>
                <article class="mini-overview-card">
                    <span>Inventory Alerts</span>
                    <strong><?php echo $inventoryAlertCount; ?></strong>
                    <small><?php echo $inventoryAlertRate; ?>% of inventory items need attention</small>
                </article>
                <article class="mini-overview-card">
                    <span>Active Workforce</span>
                    <strong><?php echo $activeEngineerCount + $activeForemanCount + $activeClientCount; ?></strong>
                    <small><?php echo $activeEngineerCount; ?> engineers, <?php echo $activeForemanCount; ?> foremen, <?php echo $activeClientCount; ?> clients</small>
                </article>
                <article class="mini-overview-card">
                    <span>7-Day Intake</span>
                    <strong><?php echo $projectsCreatedThisWeek; ?>/<?php echo $tasksCreatedThisWeek; ?></strong>
                    <small><?php echo $projectsCreatedThisWeek; ?> projects and <?php echo $tasksCreatedThisWeek; ?> tasks created this week</small>
                </article>
                <article class="mini-overview-card">
                    <span>Scan Activity</span>
                    <strong><?php echo $scansThisWeek; ?></strong>
                    <small>Last 7 days, peak daily scans: <?php echo $scanTrendPeak; ?></small>
                </article>
            </div>
        </section>
    </section>
</div>
