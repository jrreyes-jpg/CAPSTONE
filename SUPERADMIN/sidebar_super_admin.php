<?php
$currentPath = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$currentQuery = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '';

$isDashboardPage = str_contains($currentPath, '/SUPERADMIN/dashboards/super_admin_dashboard.php');
$isDashboard = $isDashboardPage && ($currentQuery === '' || str_contains($currentQuery, 'tab=dashboard'));
$isCreate = $isDashboardPage && str_contains($currentQuery, 'tab=create');
$isUsers = $isDashboardPage && str_contains($currentQuery, 'tab=users');
$isProjects = str_contains($currentPath, '/SUPERADMIN/sidebar/projects.php');
$isInventory = str_contains($currentPath, '/SUPERADMIN/sidebar/inventory.php');
$isAssets = str_contains($currentPath, '/SUPERADMIN/sidebar/assets.php');
$isScanHistory = str_contains($currentPath, '/SUPERADMIN/sidebar/scan_history.php');
$isActivityHistory = str_contains($currentPath, '/SUPERADMIN/sidebar/activity_history.php');

if (!function_exists('super_admin_sidebar_table_exists')) {
    function super_admin_sidebar_table_exists(mysqli $conn, string $tableName): bool {
        $stmt = $conn->prepare(
            'SELECT 1
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             LIMIT 1'
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $tableName);
        $stmt->execute();
        $result = $stmt->get_result();

        return (bool)($result && $result->fetch_assoc());
    }
}

if (!function_exists('super_admin_notification_relative_time')) {
    function super_admin_notification_relative_time(?string $dateTime): string {
        if (!$dateTime) {
            return 'Unknown time';
        }

        try {
            $date = new DateTimeImmutable($dateTime);
            $now = new DateTimeImmutable();
            $diff = $now->getTimestamp() - $date->getTimestamp();

            if ($diff < 60) {
                return 'Just now';
            }

            if ($diff < 3600) {
                return floor($diff / 60) . ' min ago';
            }

            if ($diff < 86400) {
                return floor($diff / 3600) . ' hr ago';
            }

            if ($diff < 604800) {
                return floor($diff / 86400) . ' day(s) ago';
            }

            return $date->format('M d, Y');
        } catch (Throwable $exception) {
            return (string)$dateTime;
        }
    }
}

if (!function_exists('super_admin_notification_action_label')) {
    function super_admin_notification_action_label(string $action): string {
        return ucwords(str_replace('_', ' ', $action));
    }
}

if (!function_exists('super_admin_fetch_notification_data')) {
    function super_admin_fetch_notification_data(mysqli $conn): array {
        $projectRiskCount = 0;
        $stockAlertCount = 0;
        $inactiveAssignmentCount = 0;
        $projectRiskAlerts = [];
        $stockAlerts = [];
        $inactiveAssignmentAlerts = [];
        $recentActivity = [];

        $projectRiskCountResult = $conn->query(
            "SELECT COUNT(*) AS total
             FROM projects p
             LEFT JOIN (
                 SELECT
                     project_id,
                     SUM(CASE WHEN status IN ('pending', 'ongoing', 'delayed') THEN 1 ELSE 0 END) AS open_tasks,
                     SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) AS delayed_tasks
                 FROM tasks
                 GROUP BY project_id
             ) task_totals ON task_totals.project_id = p.id
             WHERE p.status IN ('pending', 'ongoing', 'on-hold')
             AND (
                 COALESCE(task_totals.delayed_tasks, 0) > 0
                 OR (p.end_date IS NOT NULL AND p.end_date < CURDATE())
             )"
        );
        if ($projectRiskCountResult) {
            $projectRiskCount = (int)(($projectRiskCountResult->fetch_assoc()['total'] ?? 0));
        }

        $projectRiskResult = $conn->query(
            "SELECT
                p.id,
                p.project_name,
                p.status,
                p.end_date,
                COALESCE(task_totals.delayed_tasks, 0) AS delayed_tasks
             FROM projects p
             LEFT JOIN (
                 SELECT
                     project_id,
                     SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) AS delayed_tasks
                 FROM tasks
                 GROUP BY project_id
             ) task_totals ON task_totals.project_id = p.id
             WHERE p.status IN ('pending', 'ongoing', 'on-hold')
             AND (
                 COALESCE(task_totals.delayed_tasks, 0) > 0
                 OR (p.end_date IS NOT NULL AND p.end_date < CURDATE())
             )
             ORDER BY COALESCE(task_totals.delayed_tasks, 0) DESC, p.end_date ASC, p.updated_at DESC
             LIMIT 4"
        );
        if ($projectRiskResult) {
            $projectRiskAlerts = $projectRiskResult->fetch_all(MYSQLI_ASSOC);
        }

        $stockCountResult = $conn->query(
            "SELECT COUNT(*) AS total
             FROM inventory
             WHERE status IN ('low-stock', 'out-of-stock')"
        );
        if ($stockCountResult) {
            $stockAlertCount = (int)(($stockCountResult->fetch_assoc()['total'] ?? 0));
        }

        $stockResult = $conn->query(
            "SELECT a.asset_name, i.quantity, i.min_stock, i.status
             FROM inventory i
             INNER JOIN assets a ON a.id = i.asset_id
             WHERE i.status IN ('low-stock', 'out-of-stock')
             ORDER BY FIELD(i.status, 'out-of-stock', 'low-stock'), i.quantity ASC, a.asset_name ASC
             LIMIT 4"
        );
        if ($stockResult) {
            $stockAlerts = $stockResult->fetch_all(MYSQLI_ASSOC);
        }

        $inactiveCountResult = $conn->query(
            "SELECT COUNT(*) AS total
             FROM (
                 SELECT u.id
                 FROM users u
                 LEFT JOIN project_assignments pa ON pa.engineer_id = u.id
                 LEFT JOIN projects p ON p.id = pa.project_id AND p.status IN ('pending', 'ongoing', 'on-hold')
                 WHERE u.status = 'inactive'
                 AND u.role IN ('engineer', 'foreman', 'client')
                 GROUP BY u.id
                 HAVING COUNT(DISTINCT p.id) > 0
             ) flagged_users"
        );
        if ($inactiveCountResult) {
            $inactiveAssignmentCount = (int)(($inactiveCountResult->fetch_assoc()['total'] ?? 0));
        }

        $inactiveResult = $conn->query(
            "SELECT
                u.full_name,
                u.role,
                COUNT(DISTINCT p.id) AS active_projects
             FROM users u
             LEFT JOIN project_assignments pa ON pa.engineer_id = u.id
             LEFT JOIN projects p ON p.id = pa.project_id AND p.status IN ('pending', 'ongoing', 'on-hold')
             WHERE u.status = 'inactive'
             AND u.role IN ('engineer', 'foreman', 'client')
             GROUP BY u.id, u.full_name, u.role
             HAVING active_projects > 0
             ORDER BY active_projects DESC, u.full_name ASC
             LIMIT 4"
        );
        if ($inactiveResult) {
            $inactiveAssignmentAlerts = $inactiveResult->fetch_all(MYSQLI_ASSOC);
        }

        $hasAuditTable = function_exists('audit_log_table_exists')
            ? audit_log_table_exists($conn)
            : super_admin_sidebar_table_exists($conn, 'audit_logs');

        if ($hasAuditTable) {
            $recentActivityResult = $conn->query(
                "SELECT
                    l.created_at,
                    l.action,
                    l.entity_type,
                    actor.full_name AS actor_name
                 FROM audit_logs l
                 LEFT JOIN users actor ON actor.id = l.user_id
                 ORDER BY l.created_at DESC
                 LIMIT 4"
            );

            if ($recentActivityResult) {
                $recentActivity = $recentActivityResult->fetch_all(MYSQLI_ASSOC);
            }
        }

        return [
            'project_risk_count' => $projectRiskCount,
            'stock_alert_count' => $stockAlertCount,
            'inactive_assignment_count' => $inactiveAssignmentCount,
            'urgent_count' => $projectRiskCount,
            'project_risk_alerts' => $projectRiskAlerts,
            'stock_alerts' => $stockAlerts,
            'inactive_assignment_alerts' => $inactiveAssignmentAlerts,
            'recent_activity' => $recentActivity,
        ];
    }
}

$superAdminNotificationData = isset($conn) && $conn instanceof mysqli
    ? super_admin_fetch_notification_data($conn)
    : [
        'project_risk_count' => 0,
        'stock_alert_count' => 0,
        'inactive_assignment_count' => 0,
        'urgent_count' => 0,
        'project_risk_alerts' => [],
        'stock_alerts' => [],
        'inactive_assignment_alerts' => [],
        'recent_activity' => [],
    ];
?>
<button id="sidebarMobileToggle" class="sidebar-mobile-toggle" type="button" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">
    <span></span>
    <span></span>
    <span></span>
</button>
<nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="sidebar-toggle" type="button" aria-label="Collapse sidebar" aria-controls="sidebar" aria-expanded="true">
        <span id="toggleIcon" class="sidebar-toggle-icon" aria-hidden="true">
            <svg class="sidebar-toggle-svg" viewBox="0 0 20 20" focusable="false" aria-hidden="true">
                <path d="M11.75 4.75L6.5 10l5.25 5.25"></path>
            </svg>
        </span>
    </button>

    <div class="sidebar-header brand-block">
        <span class="brand-mark" aria-hidden="true">EA</span>
        <div class="brand-copy">
            <h3 class="brand-title">Edge Automation</h3>
            <p class="sidebar-subtitle">Super Admin Panel</p>
        </div>
    </div>
    <div class="nav-divider"></div>

    <ul class="nav-menu">
        <li>
            <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=dashboard" class="menu-link<?php echo $isDashboard ? ' active' : ''; ?>">
                <span class="menu-visual" aria-hidden="true">
                    <span class="menu-icon">
                        <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <rect x="3" y="3" width="7" height="7" rx="2"></rect>
                            <rect x="14" y="3" width="7" height="5" rx="2"></rect>
                            <rect x="14" y="10" width="7" height="11" rx="2"></rect>
                            <rect x="3" y="12" width="7" height="9" rx="2"></rect>
                        </svg>
                    </span>
                    <span class="menu-mini-label">Home</span>
                </span>
                <span class="menu-text">Dashboard</span>
            </a>
        </li>
        <li>
            <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=create" class="menu-link<?php echo $isCreate ? ' active' : ''; ?>">
                <span class="menu-visual" aria-hidden="true">
                    <span class="menu-icon">
                        <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M12 5v14"></path>
                            <path d="M5 12h14"></path>
                            <path d="M7 19h10"></path>
                        </svg>
                    </span>
                    <span class="menu-mini-label">Create</span>
                </span>
                <span class="menu-text">Create Accounts</span>
            </a>
        </li>
        <li>
            <a href="/codesamplecaps/SUPERADMIN/dashboards/super_admin_dashboard.php?tab=users" class="menu-link<?php echo $isUsers ? ' active' : ''; ?>">
                <span class="menu-visual" aria-hidden="true">
                    <span class="menu-icon">
                        <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M16 20a4 4 0 0 0-8 0"></path>
                            <circle cx="12" cy="9" r="3.5"></circle>
                            <path d="M19 20a3 3 0 0 0-3-3"></path>
                            <path d="M5 20a3 3 0 0 1 3-3"></path>
                        </svg>
                    </span>
                    <span class="menu-mini-label">Users</span>
                </span>
                <span class="menu-text">Manage Users</span>
            </a>
        </li>
        <li>
            <a href="/codesamplecaps/SUPERADMIN/sidebar/projects.php" class="menu-link<?php echo $isProjects ? ' active' : ''; ?>">
                <span class="menu-visual" aria-hidden="true">
                    <span class="menu-icon">
                        <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M3.5 7.5a2 2 0 0 1 2-2h4l1.6 1.8H18.5a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"></path>
                            <path d="M3.5 10.5h17"></path>
                        </svg>
                    </span>
                    <span class="menu-mini-label">Proj</span>
                </span>
                <span class="menu-text">Projects</span>
            </a>
        </li>
        <li>
            <a href="/codesamplecaps/SUPERADMIN/sidebar/inventory.php" class="menu-link<?php echo $isInventory ? ' active' : ''; ?>">
                <span class="menu-visual" aria-hidden="true">
                    <span class="menu-icon">
                        <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M4 8.5 12 4l8 4.5"></path>
                            <path d="M4 8.5V16l8 4 8-4V8.5"></path>
                            <path d="M12 12l8-3.5"></path>
                            <path d="M12 12 4 8.5"></path>
                            <path d="M12 12v8"></path>
                        </svg>
                    </span>
                    <span class="menu-mini-label">Stock</span>
                </span>
                <span class="menu-text">Inventory</span>
            </a>
        </li>
        <li>
            <a href="/codesamplecaps/SUPERADMIN/sidebar/assets.php" class="menu-link<?php echo $isAssets ? ' active' : ''; ?>">
                <span class="menu-visual" aria-hidden="true">
                    <span class="menu-icon">
                        <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M4 19h16"></path>
                            <path d="M6 19V9l6-4 6 4v10"></path>
                            <path d="M9 19v-4h6v4"></path>
                        </svg>
                    </span>
                    <span class="menu-mini-label">Asset</span>
                </span>
                <span class="menu-text">Assets</span>
            </a>
        </li>
        <li>
            <a href="/codesamplecaps/SUPERADMIN/sidebar/scan_history.php" class="menu-link<?php echo $isScanHistory ? ' active' : ''; ?>">
                <span class="menu-visual" aria-hidden="true">
                    <span class="menu-icon">
                        <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M7 4H5a1 1 0 0 0-1 1v2"></path>
                            <path d="M17 4h2a1 1 0 0 1 1 1v2"></path>
                            <path d="M20 17v2a1 1 0 0 1-1 1h-2"></path>
                            <path d="M4 17v2a1 1 0 0 0 1 1h2"></path>
                            <path d="M7 12h10"></path>
                            <path d="M12 7v10"></path>
                        </svg>
                    </span>
                    <span class="menu-mini-label">Scan</span>
                </span>
                <span class="menu-text">Scan History</span>
            </a>
        </li>
        <li>
            <a href="/codesamplecaps/SUPERADMIN/sidebar/activity_history.php" class="menu-link<?php echo $isActivityHistory ? ' active' : ''; ?>">
                <span class="menu-visual" aria-hidden="true">
                    <span class="menu-icon">
                        <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M12 8v5l3 2"></path>
                            <circle cx="12" cy="12" r="8"></circle>
                        </svg>
                    </span>
                    <span class="menu-mini-label">Audit</span>
                </span>
                <span class="menu-text">Activity History</span>
            </a>
        </li>
        <li>
            <a href="/codesamplecaps/LOGIN/php/logout.php" class="menu-link logout">
                <span class="menu-visual" aria-hidden="true">
                    <span class="menu-icon">
                        <svg class="menu-icon-svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                            <path d="M10 5H6.5A1.5 1.5 0 0 0 5 6.5v11A1.5 1.5 0 0 0 6.5 19H10"></path>
                            <path d="M13 8l4 4-4 4"></path>
                            <path d="M9 12h8"></path>
                        </svg>
                    </span>
                    <span class="menu-mini-label">Exit</span>
                </span>
                <span class="menu-text">Logout</span>
            </a>
        </li>
    </ul>
</nav>
<div id="sidebarOverlay" class="sidebar-overlay"></div>
<header class="global-topbar" aria-live="polite">
    <div class="global-topbar__copy">
        <strong>EDGE Automation</strong>
        <span>Super Admin Panel</span>
    </div>
    <div class="global-topbar__actions">
        <div class="topbar-notifications" data-notification-root>
            <button
                id="topbarNotificationToggle"
                class="topbar-notifications__toggle"
                type="button"
                aria-label="Open notifications"
                aria-controls="topbarNotificationDropdown"
                aria-expanded="false"
            >
                <span class="topbar-notifications__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 3a4 4 0 0 0-4 4v1.1a7 7 0 0 1-1.52 4.33L5 14.5V16h14v-1.5l-1.48-2.07A7 7 0 0 1 16 8.1V7a4 4 0 0 0-4-4Zm0 18a3 3 0 0 0 2.83-2H9.17A3 3 0 0 0 12 21Z" fill="currentColor"/>
                    </svg>
                </span>
                <?php if (($superAdminNotificationData['urgent_count'] ?? 0) > 0): ?>
                    <span class="topbar-notifications__badge">
                        <?php echo $superAdminNotificationData['urgent_count'] > 99 ? '99+' : (int)$superAdminNotificationData['urgent_count']; ?>
                    </span>
                <?php endif; ?>
            </button>

                <div id="topbarNotificationDropdown" class="topbar-notifications__dropdown" hidden>
                <div class="topbar-notifications__panel-head">
                    <div>
                        <strong>Notifications</strong>
                        <span>
                            <?php echo (int)($superAdminNotificationData['urgent_count'] ?? 0); ?> need attention
                        </span>
                    </div>
                </div>

                <?php if (($superAdminNotificationData['project_risk_count'] ?? 0) > 0): ?>
                    <div class="topbar-notifications__summary">
                        <a href="/codesamplecaps/SUPERADMIN/sidebar/projects.php?status=ongoing" class="notification-summary-chip notification-summary-chip--danger">
                            <strong><?php echo (int)($superAdminNotificationData['project_risk_count'] ?? 0); ?></strong>
                            <span>Project risks</span>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="topbar-notifications__section">
                    <div class="topbar-notifications__section-title">Needs attention</div>
                    <?php if (($superAdminNotificationData['urgent_count'] ?? 0) === 0): ?>
                        <div class="topbar-notifications__empty">
                            No project alerts right now.
                        </div>
                    <?php else: ?>
                        <?php foreach ($superAdminNotificationData['project_risk_alerts'] as $projectAlert): ?>
                            <a href="/codesamplecaps/SUPERADMIN/sidebar/project_details.php?id=<?php echo (int)($projectAlert['id'] ?? 0); ?>" class="notification-item notification-item--danger">
                                <span class="notification-item__dot"></span>
                                <div class="notification-item__copy">
                                    <strong><?php echo htmlspecialchars((string)$projectAlert['project_name']); ?></strong>
                                    <span>
                                        <?php
                                        $parts = [];
                                        if ((int)($projectAlert['delayed_tasks'] ?? 0) > 0) {
                                            $parts[] = (int)$projectAlert['delayed_tasks'] . ' delayed task(s)';
                                        }
                                        if (!empty($projectAlert['end_date']) && $projectAlert['end_date'] < date('Y-m-d')) {
                                            $parts[] = 'Late end date';
                                        }
                                        echo htmlspecialchars(implode(' | ', $parts) ?: 'Needs checking');
                                        ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <div class="global-topbar__clock">
            <span class="global-topbar__clock-label">Philippines Time</span>
            <strong class="global-topbar__time" data-ph-time>--:--:--</strong>
            <span class="global-topbar__date" data-ph-date>Loading date...</span>
        </div>
    </div>
</header>
