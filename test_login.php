<?php
// Test script to verify admin login
$host = "127.0.0.1";
$port = "3307";
$user = "root";
$pass = "";
$db = "edge_project_asset_inventory_db";

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("❌ Database Connection Failed: " . $conn->connect_error);
}

echo "✅ Database Connected!\n\n";

// Check if admin exists
$email = "admin@gmail.com";
$password = "Jhayreyes01@";

$stmt = $conn->prepare("SELECT user_id, full_name, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    echo "✅ Admin found: " . $user['full_name'] . "\n";
    echo "   Role: " . $user['role'] . "\n";
    echo "   Email: " . $email . "\n\n";
    
    // Test password verification
    if (password_verify($password, $user['password'])) {
        echo "✅ PASSWORD VERIFIED! Login should work!\n";
        echo "\nYou can now login with:\n";
        echo "   Email: admin@gmail.com\n";
        echo "   Password: Jhayreyes01@\n";
    } else {
        echo "❌ Password verification FAILED!\n";
        echo "   Expected hash: " . $user['password'] . "\n";
    }
} else {
    echo "❌ Admin user not found!\n";
}

$stmt->close();
$conn->close();
?>
