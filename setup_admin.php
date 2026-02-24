<?php
// Database connection
$host = "127.0.0.1";
$port = "3307";
$user = "root";
$pass = "";
$db = "edge_project_asset_inventory_db";

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("❌ Database Connection Failed: " . $conn->connect_error);
}

$email = "admin@gmail.com";
$password_hash = '$2y$10$TXYCSy3Vzf0lANth77aNC.TTE8.M5VioAEgKOJA/gCdTJVHPaPwIO';
$full_name = "Administrator";
$role = "admin";

// Check if admin already exists
$check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing admin
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, full_name = ?, role = ? WHERE email = ?");
    $update_stmt->bind_param("ssss", $password_hash, $full_name, $role, $email);
    
    if ($update_stmt->execute()) {
        echo "✅ Admin account updated successfully!\n";
        echo "Email: $email\n";
        echo "Password: Jhayreyes01@\n";
    } else {
        echo "❌ Error updating admin: " . $update_stmt->error;
    }
    $update_stmt->close();
} else {
    // Insert new admin
    $insert_stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, availability_status) VALUES (?, ?, ?, ?, 'available')");
    $insert_stmt->bind_param("ssss", $full_name, $email, $password_hash, $role);
    
    if ($insert_stmt->execute()) {
        echo "✅ Admin account created successfully!\n";
        echo "Email: $email\n";
        echo "Password: Jhayreyes01@\n";
    } else {
        echo "❌ Error creating admin: " . $insert_stmt->error;
    }
    $insert_stmt->close();
}

$check_stmt->close();
$conn->close();
?>
