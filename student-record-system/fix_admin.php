<?php

// Bootstrap the application
require_once 'config/bootstrap.php';

// Use secure database connection
$host = AppConfig::getDatabaseHost();
$username = AppConfig::getDatabaseUsername();
$password = AppConfig::getDatabasePassword();
$database = AppConfig::getDatabaseName();

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die('Connection failed');
}

$conn->set_charset('utf8mb4');

// Get admin credentials from secure configuration
$admin_username = AppConfig::getAdminUsername();
$admin_password = AppConfig::getAdminPassword();
$admin_email = AppConfig::getAdminEmail();

// Delete the broken admin user
$stmt = $conn->prepare("DELETE FROM users WHERE username = ?");
$stmt->bind_param("s", $admin_username);
$stmt->execute();

// Create proper password hash
$proper_hash = password_hash($admin_password, PASSWORD_DEFAULT);

// Insert new admin user with correct hash
$sql = "INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, 'admin', 1)";
$stmt = $conn->prepare($sql);

$stmt->bind_param("sss", $admin_username, $proper_hash, $admin_email);

if ($stmt->execute()) {
    echo "✅ Admin user created with proper password hash!<br><br>";
    
    // Test it immediately
    $test = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $test->bind_param("s", $admin_username);
    $test->execute();
    $result = $test->get_result();
    $user = $result->fetch_assoc();
    
    if (password_verify($admin_password, $user['password'])) {
        echo "✅ Password verification SUCCESSFUL!<br>";
        echo "✅ Login credentials configured securely<br>";
    } else {
        echo "❌ Password verification FAILED!<br>";
    }
} else {
    echo "❌ Error creating admin: " . $stmt->error . "<br>";
}

$conn->close();
?>
