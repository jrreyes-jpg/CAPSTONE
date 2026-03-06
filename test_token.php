<?php
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/repositories/UserRepository.php';

// Palitan ito ng token na nasa link ng email mo
$token = 'b2276b338a7c3048265484cf7638ba48fe2558478960db0502';

$userRepo = new UserRepository();
$user = $userRepo->findByResetToken($token);

if (!$user) {
    echo "❌ Token not found or expired!";
} else {
    $expiry = strtotime($user['token_expiry']);
    $now = time();

    if ($now > $expiry) {
        echo "⏰ Token is expired!";
    } else {
        echo "✅ Token is valid! User: " . $user['email'];
    }
}
?>