<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /codesamplecaps/public/login.php');
    exit();
}

function isStrongPassword(string $password): bool {
    return strlen($password) >= 12
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Please fill in all fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } elseif (!isStrongPassword($newPassword)) {
        $error = 'Password must be STRONG: 12+ chars with uppercase, lowercase, number, and special symbol.';
    } else {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $updateStmt->bind_param('si', $hash, $userId);

            if ($updateStmt->execute()) {
                $success = 'Password changed successfully.';
            } else {
                $error = 'Error updating password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Edge Automation</title>
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/admin_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../components/sidebar_super_admin.php'; ?>

    <main class="main-content">
        <div class="header">
            <h1>Change Password</h1>
            <div class="user-info"><span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span></div>
        </div>

        <div class="form-section" style="max-width:700px;">
            <h2 style="margin-top:0;">Update Super Admin Password</h2>
            <p class="password-tip">Use strong password: 12+ chars with uppercase, lowercase, number, special symbol (e.g. <code>Admin#2026Secure!</code>).</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="password-input-wrap">
                            <input type="password" id="current_password" name="current_password" required>
                            <button type="button" class="togglePassword" data-target="current_password">Show</button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-input-wrap">
                            <input type="password" id="new_password" name="new_password" minlength="12" required>
                            <button type="button" class="togglePassword" data-target="new_password">Show</button>
                        </div>
                        <small id="newPassStrength" class="pass-indicator">Strength: -</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-input-wrap">
                            <input type="password" id="confirm_password" name="confirm_password" minlength="12" required>
                            <button type="button" class="togglePassword" data-target="confirm_password">Show</button>
                        </div>
                        <small id="confirmPassMatch" class="pass-indicator">Match: -</small>
                    </div>
                </div>

                <button type="submit" name="change_password" class="btn-primary">Save New Password</button>
            </form>
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

function renderStrength(input, indicator) {
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

const newPass = document.getElementById('new_password');
const confirmPass = document.getElementById('confirm_password');
const strength = document.getElementById('newPassStrength');
const match = document.getElementById('confirmPassMatch');

if (newPass && strength) {
    newPass.addEventListener('input', function(){
        renderStrength(newPass, strength);
        if (confirmPass && match) {
            const same = confirmPass.value !== '' && confirmPass.value === newPass.value;
            match.textContent = 'Match: ' + (same ? 'Yes' : 'No');
            match.className = 'pass-indicator ' + (same ? 'strong' : 'weak');
        }
    });
}

if (confirmPass && match) {
    confirmPass.addEventListener('input', function(){
        const same = confirmPass.value !== '' && confirmPass.value === newPass.value;
        match.textContent = 'Match: ' + (same ? 'Yes' : 'No');
        match.className = 'pass-indicator ' + (same ? 'strong' : 'weak');
    });
}
</script>
</body>
</html>
