<?php
require_once __DIR__ . '/../../config/auth_middleware.php';
require_once __DIR__ . '/../../config/database.php';

require_role('super_admin');

$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'dashboard';
$allowedRoles = ['engineer', 'foreman', 'client'];
$allowedStatuses = ['active', 'inactive'];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_account') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = normalizeRole($_POST['role'] ?? '');

        if ($fullName === '' || $email === '' || $password === '' || $role === '') {
            $error = 'Full name, email, password, and role are required.';
            $activeTab = 'create';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
            $activeTab = 'create';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $error = 'Invalid role selected.';
            $activeTab = 'create';
        } elseif (!ctype_digit($phone) && $phone !== '') {
            $error = 'Phone number must contain numbers only.';
            $activeTab = 'create';
        } elseif (!isValidPhMobile($phone)) {
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
        $email = trim($_POST['edit_email'] ?? '');
        $phone = trim($_POST['edit_phone'] ?? '');

        if ($userId <= 0 || $fullName === '' || $email === '') {
            $error = 'Invalid edit request. Full name and email are required.';
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

    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $error = 'Invalid delete request.';
        } elseif ($userId === (int)$_SESSION['user_id']) {
            $error = 'You cannot delete your own super admin account.';
        } else {
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
                    <div class="form-row">
                        <div class="form-group"><label for="full_name">Full Name *</label><input type="text" id="full_name" name="full_name" required></div>
                        <div class="form-group"><label for="email">Email *</label><input type="email" id="email" name="email" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="phone">Phone Number (PH)</label><input type="tel" id="phone" name="phone" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></div>
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
                    <div class="form-row">
                        <div class="form-group password-field">
                            <label for="password">Temporary Password *</label>
                            <div class="password-input-wrap">
                                <input type="password" id="password" name="password" minlength="12" required>
                                <small class="password-tip">Password must be strong: 12+ chars, uppercase, lowercase, number, special symbol (e.g. Edge#2026Secure!).</small>
                                <button type="button" class="togglePassword" data-target="password">Show</button>
                            </div>
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
                    <table>
                        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No <?php echo strtolower($title); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): $status = $user['status'] ?? 'active'; ?>
                                    <tr>
                                        <td colspan="5">
                                            <form method="POST" class="row-edit-form" data-row-form>
                                                <input type="hidden" name="action" value="edit_user">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                                <div class="row-grid">
                                                    <input type="text" name="edit_full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required readonly>
                                                    <input type="email" name="edit_email" value="<?php echo htmlspecialchars($user['email']); ?>" required readonly>
                                                    <input type="text" name="edit_phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" oninput="this.value=this.value.replace(/[^0-9]/g,'')" readonly>
                                                    <span class="status-badge <?php echo $status === 'active' ? 'status-active' : 'status-inactive'; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                                                    <div class="action-group">
                                                        <button type="button" class="action-btn edit" data-edit-btn>Edit</button>
                                                        <button type="submit" class="action-btn save" data-save-btn hidden>Save</button>
                                                        <button type="button" class="action-btn cancel" data-cancel-btn hidden>Cancel</button>
                                                    </div>
                                                </div>
                                            </form>
                                            <div class="action-group row-secondary-actions">
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
<script>
function scorePassword(value) {
    let score = 0;
    if (value.length >= 12) score++;
    if (/[A-Z]/.test(value)) score++;
    if (/[a-z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;
    return score;
}

function applyStrengthUI(input, indicator) {
    const score = scorePassword(input.value);
    let text = 'Weak';
    let cls = 'weak';
    if (score >= 5) { text = 'Super Strong'; cls = 'super-strong'; }
    else if (score === 4) { text = 'Strong'; cls = 'strong'; }
    else if (score === 3) { text = 'Medium'; cls = 'medium'; }

    indicator.textContent = 'Strength: ' + text;
    indicator.className = 'pass-indicator ' + cls;
    input.classList.remove('weak-border', 'medium-border', 'strong-border');
    if (cls === 'weak') input.classList.add('weak-border');
    else if (cls === 'medium') input.classList.add('medium-border');
    else input.classList.add('strong-border');
}

const tempPass = document.getElementById('password');
const tempIndicator = document.getElementById('tempPassStrength');
if (tempPass && tempIndicator) {
    tempPass.addEventListener('input', function(){
        applyStrengthUI(tempPass, tempIndicator);
    });
}

document.querySelectorAll('[data-row-form]').forEach(function(form){
    const editBtn = form.querySelector('[data-edit-btn]');
    const saveBtn = form.querySelector('[data-save-btn]');
    const cancelBtn = form.querySelector('[data-cancel-btn]');
    const inputs = form.querySelectorAll('input[type="text"], input[type="email"]');
    const original = Array.from(inputs).map((i) => i.value);

    editBtn.addEventListener('click', function(){
        inputs.forEach((i) => i.removeAttribute('readonly'));
        editBtn.hidden = true;
        saveBtn.hidden = false;
        cancelBtn.hidden = false;
    });

    cancelBtn.addEventListener('click', function(){
        inputs.forEach((i, idx) => {
            i.value = original[idx];
            i.setAttribute('readonly', 'readonly');
        });
        editBtn.hidden = false;
        saveBtn.hidden = true;
        cancelBtn.hidden = true;
    });
});
</script>
</body>
</html>
