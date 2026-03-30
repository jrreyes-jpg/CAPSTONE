<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_role('super_admin');

$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'dashboard';
$allowedRoles = ['engineer', 'foreman', 'client'];
$allowedStatuses = ['active', 'inactive'];
$action = '';
$old = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => ''
];

function normalizeRole(string $role): string {
    $role = strtolower(trim($role));
    return $role === 'foremen' ? 'foreman' : $role;
}

function isValidPhMobile(?string $phone): bool {
    if ($phone === null || $phone === '') {
        return true;
    }
    return (bool)preg_match('/^09\d{9}$/', $phone);
}

function isStrongPassword(string $password): bool {
    return strlen($password) >= 12
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function isValidCsrfToken(?string $token): bool {
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function getUserForStatusChange(mysqli $conn, int $userId): ?array {
    $stmt = $conn->prepare('SELECT id, full_name, role, status FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function getCountByQuery(mysqli $conn, string $sql, int $userId): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    return (int)($row['total'] ?? 0);
}

function getDeactivationBlockers(mysqli $conn, int $userId, string $role): array {
    $blockers = [];

    if ($role === 'engineer') {
        $activeProjects = getCountByQuery(
            $conn,
            "SELECT COUNT(*) AS total
             FROM project_assignments pa
             INNER JOIN projects p ON p.id = pa.project_id
             WHERE pa.engineer_id = ?
             AND p.status IN ('pending', 'ongoing', 'on-hold')",
            $userId
        );

        $openTasks = getCountByQuery(
            $conn,
            "SELECT COUNT(*) AS total
             FROM tasks
             WHERE assigned_to = ?
             AND status IN ('pending', 'ongoing', 'delayed')",
            $userId
        );

        if ($activeProjects > 0) {
            $blockers[] = $activeProjects . ' active project(s)';
        }

        if ($openTasks > 0) {
            $blockers[] = $openTasks . ' open task(s)';
        }
    }

    if ($role === 'client') {
        $activeProjects = getCountByQuery(
            $conn,
            "SELECT COUNT(*) AS total
             FROM projects
             WHERE client_id = ?
             AND status IN ('pending', 'ongoing', 'on-hold')",
            $userId
        );

        if ($activeProjects > 0) {
            $blockers[] = $activeProjects . ' active project(s)';
        }
    }

    return $blockers;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
        $activeTab = $action === 'create_account' ? 'create' : 'users';
    } elseif ($action === 'create_account') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = normalizeRole($_POST['role'] ?? '');
        $old['full_name'] = $fullName;
$old['email'] = $email;
$old['phone'] = $phone;
$old['role'] = $role;

        if ($fullName === '' || $email === '' || $password === '' || $role === '') {
            $error = 'Full name, email, password, and role are required.';
            $activeTab = 'create';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
            $activeTab = 'create';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $error = 'Invalid role selected.';
            $activeTab = 'create';
      } elseif (!preg_match('/^09\d{9}$/', $phone)) {
    $error = 'Phone number must be a valid PH mobile number (09xxxxxxxxx).';
    $activeTab = 'create';
} elseif (!isStrongPassword($password)) {
            $error = 'Temporary password must be STRONG: 12+ chars with uppercase, lowercase, number, special char.';
            $activeTab = 'create';
        } else {
            $dupStmt = $conn->prepare('SELECT id, full_name, email, phone FROM users WHERE full_name = ? OR email = ? OR phone = ? LIMIT 1');
            $dupStmt->bind_param('sss', $fullName, $email, $phone);
            $dupStmt->execute();
            $dup = $dupStmt->get_result();

            if ($dup->num_rows > 0) {
                $error = 'Duplicate detected. Full name, email, and phone must all be unique.';
                $activeTab = 'create';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $createStmt = $conn->prepare('INSERT INTO users (full_name, email, password, role, phone, status, created_by) VALUES (?, ?, ?, ?, ?, "active", ?)');
                $createStmt->bind_param('sssssi', $fullName, $email, $passwordHash, $role, $phone, $_SESSION['user_id']);

                if ($createStmt->execute()) {
                    $message = ucfirst($role) . ' account created successfully.';
                    $activeTab = 'users';
                } else {
                    $error = 'Failed to create account. Please check DB columns (role/phone/id) and try again.';
                    $activeTab = 'create';
                }
            }
        }
    }

    if ($action === 'update_status' && $error === '') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $user = $userId > 0 ? getUserForStatusChange($conn, $userId) : null;

        if ($userId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            $error = 'Invalid status update request.';
        } elseif (!$user) {
            $error = 'User not found.';
        } elseif (normalizeRole((string)$user['role']) === 'super_admin') {
            $error = 'Super admin accounts cannot be changed from this screen.';
        } elseif ($newStatus === 'inactive' && $userId === (int)$_SESSION['user_id']) {
            $error = 'You cannot deactivate your own super admin account.';
        } elseif ($newStatus === 'inactive') {
            $blockers = getDeactivationBlockers($conn, $userId, normalizeRole((string)$user['role']));

            if (!empty($blockers)) {
                $error = 'Cannot deactivate ' . $user['full_name'] . ' yet. Reassign ' . implode(' and ', $blockers) . ' first.';
            } else {
                $stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
                $stmt->bind_param('si', $newStatus, $userId);
                if ($stmt->execute()) {
                    $message = 'User deactivated successfully.';
                } else {
                    $error = 'Failed to update user status.';
                }
            }
        } else {
            $stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $newStatus, $userId);
            if ($stmt->execute()) {
                $message = 'User reactivated successfully.';
            } else {
                $error = 'Failed to update user status.';
            }
        }
        $activeTab = 'users';
    }

    if ($action === 'edit_user' && $error === '') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['edit_full_name'] ?? '');
        $email = trim($_POST['edit_email'] ?? '');
        $phone = trim($_POST['edit_phone'] ?? '');
        $user = $userId > 0 ? getUserForStatusChange($conn, $userId) : null;

        if ($userId <= 0 || $fullName === '' || $email === '') {
            $error = 'Invalid edit request. Full name and email are required.';
        } elseif (!$user) {
            $error = 'User not found.';
        } elseif (normalizeRole((string)$user['role']) === 'super_admin') {
            $error = 'Super admin accounts cannot be edited from this screen.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (!ctype_digit($phone) && $phone !== '') {
            $error = 'Phone number must contain numbers only.';
        } elseif (!isValidPhMobile($phone)) {
            $error = 'Phone number must be a valid PH mobile number (09xxxxxxxxx).';
        } else {
            $dupStmt = $conn->prepare('SELECT id FROM users WHERE (full_name = ? OR email = ? OR phone = ?) AND id != ? LIMIT 1');
            $dupStmt->bind_param('sssi', $fullName, $email, $phone, $userId);
            $dupStmt->execute();
            $dup = $dupStmt->get_result();

            if ($dup->num_rows > 0) {
                $error = 'Duplicate detected. Full name, email, and phone must all be unique.';
            } else {
                $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
                $stmt->bind_param('sssi', $fullName, $email, $phone, $userId);
                if ($stmt->execute()) {
                    $message = 'User profile updated successfully.';
                } else {
                    $error = 'Failed to update user profile.';
                }
            }
        }
        $activeTab = 'users';
    }

}


function fetchUsersByRoles(mysqli $conn, array $roles): array {
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $types = str_repeat('s', count($roles));
    $sql = "SELECT id, full_name, email, phone, status, role FROM users WHERE role IN ($placeholders) ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$roles);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$engineers = fetchUsersByRoles($conn, ['engineer']);
$foremen = fetchUsersByRoles($conn, ['foreman', 'foremen']);
$clients = fetchUsersByRoles($conn, ['client']);
$totalUsers = count($engineers) + count($foremen) + count($clients);
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Edge Automation</title>
    <link rel="stylesheet" href="../css/admin-dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../sidebar_super_admin.php'; ?>

    <main class="main-content">
        <div class="header">
            <h1>Super Admin Dashboard</h1>
            <div class="user-info"><span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span></div>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div id="dashboard" class="tab-content <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'dashboard' ? 'display: block;' : 'display: none;'; ?>">
            <h2 style="margin-bottom: 20px;">System Overview</h2>
            <div class="stats">
                <div class="stat-card"><h3>Engineers</h3><div class="number counter" data-target="<?php echo count($engineers); ?>">0</div></div>
                <div class="stat-card"><h3>Foreman</h3><div class="number counter" data-target="<?php echo count($foremen); ?>">0</div></div>
                <div class="stat-card"><h3>Clients</h3><div class="number counter" data-target="<?php echo count($clients); ?>">0</div></div>
                <div class="stat-card"><h3>Total Users</h3><div class="number counter" data-target="<?php echo $totalUsers; ?>">0</div></div>
            </div>
        </div>

        <div id="create" class="tab-content <?php echo $activeTab === 'create' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'create' ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-section">
                <h2>Create Account</h2>
                                <form method="POST">
                    <input type="hidden" name="action" value="create_account">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="form-row">
                        <div class="form-group"><label for="full_name">Full Name *</label><input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($old['full_name']); ?>" required></div>
                        <div class="form-group"><label for="email">Email *</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old['email']); ?>" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="phone">Phone Number (PH)</label><input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($old['phone']); ?>" pattern="^09[0-9]{9}$" maxlength="11" placeholder="09XXXXXXXXX" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,''); if(!this.value.startsWith('09')){this.value='09';}"></div>
                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="">Select a role</option>
                                <option value="engineer" <?php echo $old['role']=='engineer'?'selected':''; ?>>Engineer</option>
<option value="foreman" <?php echo $old['role']=='foreman'?'selected':''; ?>>Foreman</option>
<option value="client" <?php echo $old['role']=='client'?'selected':''; ?>>Client</option>                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group password-field">
                            <label for="password">Temporary Password *</label>
                            <div class="password-input-wrap">
                                <input type="password" id="password" name="password" minlength="12" required>
                                <button type="button" class="togglePassword" data-target="password">Show</button>
                            </div>
                            <small class="password-tip">Password must be strong: 12+ chars, uppercase, lowercase, number, special symbol (e.g. Edge#2026Secure!).</small>
                            <small id="tempPassStrength" class="pass-indicator">Strength: -</small>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Create Account</button>
                </form>
            </div>
        </div>

        <div id="users" class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>" style="<?php echo $activeTab === 'users' ? 'display: block;' : 'display: none;'; ?>">
            <?php $sections = ['Engineers' => $engineers, 'Foreman' => $foremen, 'Clients' => $clients]; foreach ($sections as $title => $users): ?>
                <h2 style="margin-top: 20px; margin-bottom: 15px;"><?php echo $title; ?></h2>
                <div class="users-table">
                    <table class="responsive-table">
                        <colgroup>
                            <col style="width: 22%;">
                            <col style="width: 26%;">
                            <col style="width: 18%;">
                            <col style="width: 14%;">
                            <col style="width: 20%;">
                        </colgroup>
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No <?php echo strtolower($title); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): $status = $user['status'] ?? 'active'; $rowId = (int)$user['id']; ?>
                                    <tr class="user-row" data-row-id="<?php echo $rowId; ?>">
                                        <td><input class="table-input" type="text" data-field="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly required></td>
                                        <td><input class="table-input" type="email" data-field="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly required></td>
                                        <td><input class="table-input" type="text" data-field="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="^09[0-9]{9}$" maxlength="11" placeholder="09XXXXXXXXX" oninput="this.value=this.value.replace(/[^0-9]/g,''); if(!this.value.startsWith('09')){this.value='09';}" readonly></td>
                                        <td><span class="status-badge <?php echo $status === 'active' ? 'status-active' : 'status-inactive'; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
                                        <td>
                                            <div class="action-group compact">
                                                <button type="button" class="action-btn edit" data-edit-btn>Edit</button>
                                                <button type="button" class="action-btn save" data-save-btn hidden>Save</button>
                                                <button type="button" class="action-btn cancel" data-cancel-btn hidden>Cancel</button>
                                            </div>
                                            <div class="action-group compact row-secondary-actions">
                                                <form method="POST" style="display:inline-block; margin:0;" onsubmit="return confirm('<?php echo $status === 'active' ? 'Deactivate this user? They will lose access to login.' : 'Reactivate this user?'; ?>');">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $rowId; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $status === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="action-btn <?php echo $status === 'active' ? 'deactivate' : 'activate'; ?>"><?php echo $status === 'active' ? 'Deactivate' : 'Reactivate'; ?></button>
                                                </form>
                                                <form method="POST" id="save-form-<?php echo $rowId; ?>" style="display:none;">
                                                    <input type="hidden" name="action" value="edit_user">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $rowId; ?>">
                                                    <input type="hidden" name="edit_full_name" data-save-field="full_name">
                                                    <input type="hidden" name="edit_email" data-save-field="email">
                                                    <input type="hidden" name="edit_phone" data-save-field="phone">
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
</div>
    </main>
</div>

<script src="../js/script.js"></script>
</body>
</html>
