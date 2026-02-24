<?php

$host = "127.0.0.1";   // Mas stable kaysa localhost
$port = "3307";        // Custom MySQL port mo
$user = "root";        // Default sa XAMPP
$pass = "";            // Default walang password
$db   = "edge_project_asset_inventory_db";

// Create connection
$conn = new mysqli($host, $user, $pass, $db, $port);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Optional: set charset
$conn->set_charset("utf8mb4");

?>
