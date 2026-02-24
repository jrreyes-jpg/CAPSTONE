<?php
// ADMIN ACCOUNT SETUP HELPER
// This script lets you create or update any admin account with your chosen password

$host = "127.0.0.1";
$port = "3307";
$user = "root";
$pass = "";
$db = "edge_project_asset_inventory_db";

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("❌ Database Connection Failed: " . $conn->connect_error);
}

echo "🔧 ADMIN ACCOUNT SETUP HELPER\n";
echo "====================================\n\n";

// Check existing accounts
$result = $conn->query("SELECT email FROM users WHERE role = 'admin'");
echo "📌 Existing Admin Accounts:\n";
while ($row = $result->fetch_assoc()) {
    echo "   - " . $row['email'] . "\n";
}
echo "\n";

// Ask for input
$email = "admin@edgeautomation.com"; // Default
$password = "admin123"; // Default

echo "Setting up admin account:\n";
echo "Email: " . $email . "\n";
echo "Password: " . $password . "\n";
echo "Generating hash...\n\n";

$hashed = password_hash($password, PASSWORD_DEFAULT);

// Check if email exists
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // Update
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashed, $email);
    $stmt->execute();
    echo "✅ Admin account UPDATED!\n";
} else {
    // Insert
    $role = "admin";
    $name = "Administrator";
    $status = "available";
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, availability_status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $hashed, $role, $status);
    $stmt->execute();
    echo "✅ Admin account CREATED!\n";
    $stmt->close();
}

$check->close();

echo "\n✅ You can now login with:\n";
echo "   Email: " . $email . "\n";
echo "   Password: " . $password . "\n";
echo "\n📝 Password Hash (for reference):\n";
echo "   " . $hashed . "\n";

// Verify
echo "\n🔍 Verifying...\n";
$stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (password_verify($password, $user['password'])) {
    echo "✅ Password verification: SUCCESS!\n";
} else {
    echo "❌ Password verification: FAILED!\n";
}

$stmt->close();
$conn->close();
?>
