<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // In production, generate a reset token and send email
            // For now, just show a message
            $success = "Password reset instructions have been sent to " . htmlspecialchars($email);
        } else {
            $error = "Email not found in our system.";
        }
    } else {
        $error = "Please enter your email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - Edge Automation Portal</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<canvas id="particles"></canvas>

<div class="container">
<div class="left-panel">
<div class="logo">
    <img src="../assets/images/logo.png" alt="Edge Logo">
</div>
    <h1>EDGE AUTOMATION</h1>
    <p>Technology Services Co.<br>PCAB LIC: 58783</p>
</div>

<div class="right-panel">
<div class="form active">
<form method="POST">
<h2>Reset Password</h2>

<?php if($error): ?>
    <p class="error-message"><?php echo $error; ?></p>
<?php endif; ?>

<?php if($success): ?>
    <p class="success-message"><?php echo $success; ?></p>
<?php endif; ?>

<input type="email" name="email" placeholder="Enter your Email" required>
<button type="submit">Send Reset Link</button>

<div class="links">
    <a href="../index.php">Back to Login</a>
</div>
</form>
</div>
</div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
