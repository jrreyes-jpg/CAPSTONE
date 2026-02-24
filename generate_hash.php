<?php
/**
 * 🔑 PASSWORD HASH GENERATOR
 * 
 * Use this file to generate a password hash for creating admin accounts
 * 
 * Instructions:
 * 1. Upload this file to your htdocs folder
 * 2. Visit: http://localhost/codesamplecaps/generate_hash.php?password=yourpassword
 * 3. Copy the hash
 * 4. Use it in your SQL INSERT statement
 */

if (isset($_GET['password'])) {
    $password = $_GET['password'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head><title>Password Hash Generator</title></head>";
    echo "<body style='font-family: Arial; padding: 20px;'>";
    echo "<h2>Password Hash Generator</h2>";
    echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
    echo "<p><strong>Hash:</strong></p>";
    echo "<textarea style='width:100%; height:100px;'>";
    echo $hash;
    echo "</textarea>";
    echo "<p><small>Copy this hash and use it in your SQL INSERT statement</small></p>";
    echo "</body>";
    echo "</html>";
} else {
    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head><title>Password Hash Generator</title></head>";
    echo "<body style='font-family: Arial; padding: 20px;'>";
    echo "<h2>Password Hash Generator</h2>";
    echo "<p>Usage: <code>generate_hash.php?password=yourpassword</code></p>";
    echo "<p>Example: <code>http://localhost/codesamplecaps/generate_hash.php?password=admin123</code></p>";
    echo "</body>";
    echo "</html>";
}
?>
