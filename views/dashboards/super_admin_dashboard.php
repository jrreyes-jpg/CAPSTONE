<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_role('super_admin');

$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'dashboard';
$allowedRoles = ['engineer', 'foreman', 'client'];
$allowedStatuses = ['active', 'inactive'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_account') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? '');

        if ($fullName === '' || $email === '' || $password === '' || $role === '') {
            $error = 'Full name, email, password, and role are required.';
            $activeTab = 'create';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
            $activeTab = 'create';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
            $activeTab = 'create';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $error = 'Invalid role selected.';
            $activeTab = 'create';
        } else {
            $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $existing = $checkStmt->get_result();

            if ($existing->num_rows > 0) {
                $error = 'Email is already used by another account.';
                $activeTab = 'create';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $createStmt = $conn->prepare('INSERT INTO users (full_name, email, password, role, phone, status, created_by) VALUES (?, ?, ?, ?, ?, "active", ?)');
                $createStmt->bind_param('sssssi', $fullName, $email, $passwordHash, $role, $phone, $_SESSION['user_id']);

                if ($createStmt->execute()) {
                    $message = ucfirst($role) . ' account created successfully.';
                    $activeTab = 'users';
                } else {
                    $error = 'Failed to create account. Please try again.';
                    $activeTab = 'create';
                }
            }
        }
    }

    if ($action === 'update_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');

        if ($userId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            $error = 'Invalid status update request.';
        } elseif ($userId === (int)$_SESSION['user_id']) {
            $error = 'You cannot deactivate your own super admin account.';
        } else {
            $stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $newStatus, $userId);
            if ($stmt->execute()) {
                $message = 'User status updated to ' . $newStatus . '.';
            } else {
                $error = 'Failed to update user status.';
            }
        }
        $activeTab = 'users';
    }

    if ($action === 'edit_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['edit_full_name'] ?? '');
        $phone = trim($_POST['edit_phone'] ?? '');

        if ($userId <= 0 || $fullName === '') {
            $error = 'Invalid edit request. Full name is required.';
        } else {
            $stmt = $conn->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?');
            $stmt->bind_param('ssi', $fullName, $phone, $userId);
            if ($stmt->execute()) {
                $message = 'User profile updated successfully.';
            } else {
                $error = 'Failed to update user profile.';
            }
        }
        $activeTab = 'users';
    }

    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            $error = 'Invalid delete request.';
        } elseif ($userId === (int)$_SESSION['user_id']) {
            $error = 'You cannot delete your own super admin account.';
        } else {
            // Soft delete: set inactive for safety/audit trail
            $stmt = $conn->prepare('UPDATE users SET status = "inactive" WHERE id = ?');
            $stmt->bind_param('i', $userId);
            if ($stmt->execute()) {
                $message = 'User account was set to inactive (soft deleted).';
            } else {
                $error = 'Failed to delete user.';
            }
        }
        $activeTab = 'users';
    }
}

function fetchUsersByRole(mysqli $conn, string $role): array {
    $stmt = $conn->prepare('SELECT id, full_name, email, phone, status FROM users WHERE role = ? ORDER BY id DESC');
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$engineers = fetchUsersByRole($conn, 'engineer');
$foremen = fetchUsersByRole($conn, 'foreman');
$clients = fetchUsersByRole($conn, 'client');
$totalUsers = count($engineers) + count($foremen) + count($clients);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Edge Automation</title>
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/admin_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../components/sidebar_super_admin.php'; ?>

    <main class="main-content">
        <div class="header">
            <h1>Super Admin Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="/codesamplecaps/views/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="dashboard-actions">
            <a class="action-chip" href="?tab=dashboard">Overview</a>
            <a class="action-chip" href="?tab=create">Create Client / Foreman / Engineer</a>
            <a class="action-chip" href="?tab=users">View All Accounts</a>
        </div>

        <div id="dashboard" class="tab-content <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'dashboard' ? 'display: block;' : 'display: none;'; ?>">
            <h2 style="margin-bottom: 20px;">System Overview</h2>
            <div class="stats">
                <div class="stat-card"><h3>Engineers</h3><div class="number counter" data-target="<?php echo count($engineers); ?>">0</div></div>
                <div class="stat-card"><h3>Foremen</h3><div class="number counter" data-target="<?php echo count($foremen); ?>">0</div></div>
                <div class="stat-card"><h3>Clients</h3><div class="number counter" data-target="<?php echo count($clients); ?>">0</div></div>
                <div class="stat-card"><h3>Total Users</h3><div class="number counter" data-target="<?php echo $totalUsers; ?>">0</div></div>
            </div>
        </div>

        <div id="create" class="tab-content <?php echo $activeTab === 'create' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'create' ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-section">
                <h2>Create New Account (Client, Foreman, Engineer)</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_account">
                    <div class="form-row">
                        <div class="form-group"><label for="full_name">Full Name *</label><input type="text" id="full_name" name="full_name" required></div>
                        <div class="form-group"><label for="email">Email Address *</label><input type="email" id="email" name="email" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="phone">Phone Number</label><input type="tel" id="phone" name="phone"></div>
                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="">Select a role</option>
                                <option value="engineer">Engineer</option>
                                <option value="foreman">Foreman</option>
                                <option value="client">Client</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row"><div class="form-group"><label for="password">Temporary Password *</label><input type="password" id="password" name="password" minlength="8" required></div></div>
                    <button type="submit" class="btn-primary">Create Account</button>
                </form>
            </div>
        </div>

        <div id="users" class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'users' ? 'display: block;' : 'display: none;'; ?>">
            <?php $sections = ['Engineers' => $engineers, 'Foremen' => $foremen, 'Clients' => $clients]; foreach ($sections as $title => $users): ?>
                <h2 style="margin-top: 20px; margin-bottom: 15px;"><?php echo $title; ?></h2>
                <div class="users-table">
                    <table>
                        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No <?php echo strtolower($title); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <form method="POST" class="inline-edit-form">
                                                <input type="hidden" name="action" value="edit_user">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                                <input type="text" name="edit_full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><input type="text" name="edit_phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></td>
                                        <td>
                                            <?php $status = $user['status'] ?? 'active'; ?>
                                            <span class="status-badge <?php echo $status === 'active' ? 'status-active' : 'status-inactive'; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                                        </td>
                                        <td>
                                            <div class="action-group">
                                                <button type="submit" class="action-btn edit">Save</button>
                                            </form>

                                            <form method="POST" style="display:inline-block;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo $status === 'active' ? 'inactive' : 'active'; ?>">
                                                <button type="submit" class="action-btn <?php echo $status === 'active' ? 'deactivate' : 'activate'; ?>"><?php echo $status === 'active' ? 'Set Inactive' : 'Set Active'; ?></button>
                                            </form>

                                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete user? This will set the account to inactive.');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                                <button type="submit" class="action-btn delete">Delete</button>
                                            </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<script src="/codesamplecaps/public/assets/js/script.js"></script>
</body>
</html>
