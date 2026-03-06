<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /codesamplecaps/public/login.php');
    exit();
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
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters.';
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
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                
            </div>
        </div>

        <div class="form-section" style="max-width:700px;">
            <h2 style="margin-top:0;">Update Super Admin Password</h2>

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
                        <div class="password-input-wrap"><input type="password" id="current_password" name="current_password" required><button type="button" class="togglePassword" data-target="current_password">Show</button></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-input-wrap"><input type="password" id="new_password" name="new_password" minlength="8" required><button type="button" class="togglePassword" data-target="new_password">Show</button></div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-input-wrap"><input type="password" id="confirm_password" name="confirm_password" minlength="8" required><button type="button" class="togglePassword" data-target="confirm_password">Show</button></div>
                    </div>
                </div>

                <button type="submit" name="change_password" class="btn-primary">Save New Password</button>
            </form>

            <div style="margin-top:18px;">
                <a href="/codesamplecaps/views/dashboards/super_admin_dashboard.php" class="action-chip">← Back to Super Admin Dashboard</a>
            </div>
        </div>
    </main>
</div>

<script src="/codesamplecaps/public/assets/js/script.js"></script>
</body>
</html>
