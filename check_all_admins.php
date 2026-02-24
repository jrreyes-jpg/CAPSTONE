<?php
$host = "127.0.0.1";
$port = "3307";
$user = "root";
$pass = "";
$db = "edge_project_asset_inventory_db";

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("❌ Database Connection Failed: " . $conn->connect_error);
}

echo "📊 All Admin Accounts in Database:\n";
echo "=====================================\n\n";

$result = $conn->query("SELECT user_id, full_name, email, password, role FROM users WHERE role = 'admin'");

if ($result->num_rows > 0) {
    while ($user = $result->fetch_assoc()) {
        echo "Full Name: " . $user['full_name'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "Password Hash: " . $user['password'] . "\n";
        
        // Test with admin123
        if (password_verify('admin123', $user['password'])) {
            echo "✅ Password 'admin123' verfified!\n";
        }
        
        // Test with Jhayreyes01@
        if (password_verify('Jhayreyes01@', $user['password'])) {
            echo "✅ Password 'Jhayreyes01@' verified!\n";
        }
        
        echo "\n";
    }
} else {
    echo "No admin accounts found!\n";
}

$conn->close();
?>
