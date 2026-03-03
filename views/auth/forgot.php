<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();
        $token = bin2hex(random_bytes(50));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $update = $conn->prepare("UPDATE users SET reset_token=?, token_expiry=? WHERE user_id=?");
        $update->bind_param("ssi", $token, $expiry, $user['user_id']);
        $update->execute();

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jeshowap@gmail.com'; // CHANGE
            $mail->Password   = 'otpobfbebgmiowww';   // CHANGE
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('jeshowap@gmail.com', 'Edge Automation');
            $mail->addAddress($email);

            $resetLink = "http://localhost/codesamplecaps/views/auth/reset_password.php?token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset - Edge Automation';
            $mail->Body    = "
                <h3>Password Reset Request</h3>
                <p>Click the link below to reset your password:</p>
                <a href='$resetLink'>$resetLink</a>
                <br><br>
                <p>This link expires in 1 hour.</p>
            ";

            $mail->send();

            $success = "Reset link sent to your email.";
        } catch (Exception $e) {
            $error = "Email failed: {$mail->ErrorInfo}";
        }

    } else {
        echo "If email exists, reset link will be sent.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - Edge Automation Portal</title>
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
        EDGE AUTOMATION TECHNOLOGY SERVICES, CO.
    </h1></div>

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
    <a href="/codesamplecaps/public/login.php">Back to Login</a>
</div>
</form>
</div>
</div>
</div>

<script src="/codesamplecaps/public/assets/js/script.js"></script>
</body>
</html>
