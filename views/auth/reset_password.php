<?php
/**
 * Password Reset Page
 * 
 * User lands here from the email link
 * They can set their new password using the token from the email
 */
date_default_timezone_set('Asia/Manila'); // or your local timezone
session_start();

require_once __DIR__ . '/../../services/AuthService.php';

$error = "";
$success = "";
$token = trim($_GET['token'] ?? '');
$authService = new AuthService();

// Check if token is provided
if (empty($token)) {
    $error = "Invalid or missing reset link.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $newPassword = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    // Validate passwords
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "All fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Use AuthService to reset password
        $result = $authService->resetPassword($token, $newPassword);

        if ($result['success']) {
            $success = $result['message'];
            $token = ""; // Clear token to prevent reuse
        } else {
            $error = $result['error'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Edge Automation Portal</title>
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .password-strength {
            height: 5px;
            background: #ddd;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }
        .strength {
            height: 100%;
            width: 0;
            transition: width 0.3s, background-color 0.3s;
        }
        .success-box {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
        }
        .error-box {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }
        .togglePassword{
            top: 70%;
        }       
    </style>
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
            </h1>
        </div>

        <div class="right-panel">
            <div class="form active">
                <form method="POST">
                    <h2>Create New Password</h2>

                    <?php if($error): ?>
                        <div class="error-box">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if($success): ?>
                        <div class="success-box">
                            <?php echo htmlspecialchars($success); ?>
                            <p style="margin-top: 10px;">
                                <a href="/codesamplecaps/public/login.php" style="color: #155724; text-decoration: underline;">
                                    Click here to login
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if(!empty($token) && empty($success)): ?>
                        <p style="margin: 15px 0; font-size: 14px; color: #555;">
                            Enter your new password below.
                        </p>

                        <div class="password-wrapper">
                            <label for="password" style="display: block; margin-bottom: 8px; font-weight: 500;">New Password</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Enter new password (min 8 characters)" 
                                required
                                onkeyup="checkPasswordStrength(this.value)"
                            >
                            <button type="button" class="togglePassword" data-target="password">Show</button>
                        </div>
                        <div class="password-strength" style="margin-bottom:20px;">
                            <div class="strength" id="strengthBar"></div>
                        </div>
                        <small id="strengthText" style="display: block; margin-top: 5px; color: #666; margin-bottom:20px;"></small>

                        <div class="password-wrapper">
                            <label for="confirm_password" style="display: block; margin-bottom: 8px; font-weight: 500;">Confirm Password</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                placeholder="Confirm the password" 
                                required
                            >
                            <button type="button" class="togglePassword" data-target="confirm_password">Show</button>
                        </div>

                        <button type="submit">Reset Password</button>
                    <?php endif; ?>

                    <div class="links" style="margin-top: 20px;">
                        <a href="/codesamplecaps/public/login.php">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/codesamplecaps/public/assets/js/script.js"></script>
    <script>
        /**
         * Check password strength and display visual feedback
         */
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            let text = '';
            let color = '#dc3545'; // Red
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[@$!%*?&]+/)) strength++;
            
            const percentages = [0, 20, 40, 60, 80, 100];
            const texts = ['', 'Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const colors = ['#dc3545', '#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745'];
            
            strengthBar.style.width = percentages[strength] + '%';
            strengthBar.style.backgroundColor = colors[strength];
            strengthText.textContent = texts[strength];
            strengthText.style.color = colors[strength];
        }

        /**
         * Validate password match on blur
         */
        document.getElementById('confirm_password')?.addEventListener('blur', function() {
            const password = document.getElementById('password').value;
            if (this.value && password && this.value !== password) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '';
            }
        });
    </script>
</body>
</html>
