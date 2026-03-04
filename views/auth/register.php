<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'client'; // Default role for sign-ups

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $hashed_password, $role);

            if ($stmt->execute()) {
                $success = "Account created successfully! You can now login.";
                // Redirect after 2 seconds
                header("refresh:2;url=/codesamplecaps/public/login.php");
            } else {
                $error = "Error creating account. Please try again.";
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
<title>Register - Edge Automation Portal</title>
<link rel="stylesheet" href="/codesamplecaps/public/assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<canvas id="particles"></canvas>

<div class="container">
<div class="left-panel">
<div class="logo">
    <img src="/codesamplecaps/public/assets/images/edge.jpg" alt="Edge Logo">
</div>
<h1 class="company-name">
    EDGE AUTOMATION TECHNOLOGY SERVICES CO.
</h1>
</div>

<div class="right-panel">
<div class="form active">
<form method="POST">
<h2>Create Account</h2>

<?php if($error): ?>
    <p class="error-message"><?php echo $error; ?></p>
<?php endif; ?>

<?php if($success): ?>
    <p class="success-message"><?php echo $success; ?></p>
<?php endif; ?>

<input type="text" name="full_name" placeholder="Full Name" required>
<input type="email" name="email" placeholder="Email" required>
<div class="password-wrapper">
    <input id="password" type="password" name="password" placeholder="Password" required>
    <button type="button" class="togglePassword" data-target="password">Show</button>
</div>

<div class="password-wrapper">
    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
    <button type="button" class="togglePassword" data-target="confirm_password">Show</button>
</div>
<button type="submit" name="register">Register</button>

<div class="links">
    <a href="/codesamplecaps/public/login.php">Back to Login</a>
</div>
</form>
</div>
</head>
<body>

<script src="/codesamplecaps/public/assets/js/script.js"></script>
</body>
</html>
