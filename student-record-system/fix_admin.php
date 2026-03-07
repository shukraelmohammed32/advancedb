<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Delete the broken admin user
$conn->query("DELETE FROM users WHERE username = 'admin'");

// Create proper password hash for "admin123"
$password = 'admin123';
$proper_hash = password_hash($password, PASSWORD_DEFAULT);

echo "Creating admin with proper hash: " . $proper_hash . "<br>";

// Insert new admin user with correct hash
$sql = "INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, 'admin', 1)";
$stmt = $conn->prepare($sql);

$username = 'admin';
$email = 'admin@school.edu';
$stmt->bind_param("sss", $username, $proper_hash, $email);

if ($stmt->execute()) {
    echo "✅ Admin user created with proper password hash!<br><br>";
    
    // Test it immediately
    $test = $conn->query("SELECT password FROM users WHERE username = 'admin'");
    $user = $test->fetch_assoc();
    
    if (password_verify('admin123', $user['password'])) {
        echo "✅ Password verification SUCCESSFUL!<br>";
        echo "✅ You can now login with: admin / admin123<br><br>";
        echo '<a href="auth/login.php">Go to Login</a>';
    } else {
        echo "❌ Still having issues with password verification<br>";
    }
} else {
    echo "❌ Error: " . $conn->error;
}
?>
