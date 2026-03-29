<?php
require_once 'config/database.php';

// Create database connection
$db = new Database();
$conn = $db->getConnection();

// Delete existing admin user if exists
$conn->query("DELETE FROM users WHERE username = 'admin'");

// Create fresh password hash for "admin123"
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new admin user
$sql = "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'admin')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", 'admin', $hashed_password, 'admin@school.edu');

if ($stmt->execute()) {
    echo "✅ Admin user created successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br><br>";
    
    // Test the password
    $test_stmt = $conn->prepare("SELECT password FROM users WHERE username = 'admin'");
    $test_stmt->execute();
    $result = $test_stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (password_verify('admin123', $user['password'])) {
        echo "✅ Password verification successful!<br>";
    } else {
        echo "❌ Password verification failed!<br>";
    }
    
    echo '<br><a href="auth/login.php">Go to Login</a>';
} else {
    echo "❌ Error creating admin user: " . $conn->error;
}
?>
