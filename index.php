<?php
session_start();
require_once __DIR__ . '/config/database.php';

$error = "";
$failed_attempts_display = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $error = "Email or password is incorrect."; // High security message
    $max_attempts = 10;
    $lockout_time = 15 * 60; // 15 minutes

    if (!empty($email) && !empty($password)) {

        $stmt = $conn->prepare("SELECT user_id, full_name, password, role, failed_attempts, last_failed_login 
                                FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $attempts = 0;

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $attempts = $user['failed_attempts'];

            // Check if account is locked
            if ($attempts >= $max_attempts && strtotime($user['last_failed_login']) + $lockout_time > time()) {
                $error = "Too many failed attempts. Try again later.";
                $failed_attempts_display = "Failed attempts: $attempts / $max_attempts";
            } else {
                if (password_verify($password, $user['password'])) {
                    // Successful login: reset failed attempts
                    $resetStmt = $conn->prepare("UPDATE users SET failed_attempts = 0, last_failed_login = NULL WHERE user_id = ?");
                    $resetStmt->bind_param("i", $user['user_id']);
                    $resetStmt->execute();

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];

                    // Redirect based on role
                    if ($user['role'] == 'admin') {
                        header("Location: /codesamplecaps/dashboards/admin_dashboard.php");
                    } elseif ($user['role'] == 'engineer') {
                        header("Location: /codesamplecaps/dashboards/engineer_dashboard.php");
                    } else {
                        header("Location: /codesamplecaps/dashboards/client_dashboard.php");
                    }
                    exit();
                } else {
                    // Wrong password: increase failed attempts
                    $attempts++;
                    $updateStmt = $conn->prepare("UPDATE users SET failed_attempts = ?, last_failed_login = NOW() WHERE user_id = ?");
                    $updateStmt->bind_param("ii", $attempts, $user['user_id']);
                    $updateStmt->execute();

                    $failed_attempts_display = "Failed attempts: $attempts / $max_attempts";
                }
            }
        } else {
            // Email not found, still high-security message
            $failed_attempts_display = "Failed attempts: 0 / $max_attempts";
        }

    } else {
        $error = "Please fill in all fields.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edge Automation Portal</title>

<link rel="stylesheet" href="assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>

<body>

<canvas id="particles"></canvas>

<div class="container">

<div class="left-panel">
<div class="logo" id="logo3d">
    <img src="assets/images/logo.png" alt="Edge Logo">
</div>
<h1 class="company-name">
    EDGE AUTOMATION TECHNOLOGY SERVICES, CO.
</h1></div>

<div class="right-panel">

    <!-- LOGIN FORM -->
    <div class="form active" id="loginForm">
        <form method="POST">
            <h2>Login</h2>
<?php if($error): ?>
    <div class="error-box">
        <?php echo $error; ?>
        <?php if(!empty($failed_attempts_display)): ?>
            <div style="margin-top:5px; font-size:13px; color:#800000;">
                <?php echo $failed_attempts_display; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>


          
            <input type="email" name="email" placeholder="Email" required>

<div class="password-wrapper">
    <input id="password" type="password" name="password" placeholder="Password" required>
    <button type="button" class="togglePassword" data-target="password">Show</button>
</div>

            <button type="submit" name="login">Login</button>

            <div class="links">
                <a onclick="showSignup()">Sign Up</a>
                <a onclick="showForgot()">Forgot Password?</a>
            </div>
        </form>
    </div>

</div>

</div>

<script>
function showSignup(){
    window.location.href = 'auth/register.php';
}

function showLogin(){
    window.location.href = 'index.php';
}

function showForgot(){
    window.location.href = 'auth/forgot.php';
}
</script>

<script>
// Show/hide password toggle
document.addEventListener('DOMContentLoaded', function () {
</script>
<script>
// Show/hide password toggles (supports multiple pages/buttons using .togglePassword + data-target)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.togglePassword').forEach(function(btn){
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var input = document.getElementById(targetId);
            if (!input) return;
            var type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            btn.textContent = type === 'text' ? 'Hide' : 'Show';
            btn.setAttribute('aria-pressed', type === 'text' ? 'true' : 'false');
        });
    });
});
</script>
</script>

<script src="assets/js/script.js"></script>
</body>
</html>
