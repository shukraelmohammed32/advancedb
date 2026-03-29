<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Debug Admin User</h2>";

// Check if users table exists
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check->num_rows == 0) {
    echo "❌ Users table does not exist! <a href='setup_database.php'>Run setup first</a>";
    exit();
}

// Check if admin user exists
$admin_check = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($admin_check->num_rows == 0) {
    echo "❌ Admin user not found. Creating new one...<br>";
    
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    echo "New hash: " . $hashed_password . "<br>";
    
    $sql = "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'admin')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", 'admin', $hashed_password, 'admin@school.edu');
    
    if ($stmt->execute()) {
        echo "✅ Admin user created<br>";
    } else {
        echo "❌ Error: " . $conn->error . "<br>";
    }
}

// Get admin user data
$result = $conn->query("SELECT * FROM users WHERE username = 'admin'");
$admin = $result->fetch_assoc();

echo "<h3>Admin User Data:</h3>";
echo "Username: " . $admin['username'] . "<br>";
echo "Role: " . $admin['role'] . "<br>";
echo "Active: " . ($admin['is_active'] ? 'Yes' : 'No') . "<br>";
echo "Password Hash: " . $admin['password'] . "<br>";

// Test password verification
echo "<h3>Password Tests:</h3>";
$passwords_to_test = ['admin123', 'admin', 'password'];

foreach ($passwords_to_test as $test_pass) {
    $verify = password_verify($test_pass, $admin['password']);
    echo "Password '$test_pass': " . ($verify ? '✅ Valid' : '❌ Invalid') . "<br>";
}

// Test login query
echo "<h3>Login Query Test:</h3>";
$stmt = $conn->prepare("SELECT u.*, s.name as student_name, t.teacher_name 
                       FROM users u 
                       LEFT JOIN students s ON u.student_id = s.student_id 
                       LEFT JOIN teachers t ON u.teacher_id = t.teacher_id 
                       WHERE u.username = ? AND u.is_active = 1");

if ($stmt === false) {
    echo "❌ Prepare failed: " . $conn->error . "<br>";
} else {
    echo "✅ Query prepared successfully<br>";
    $stmt->bind_param("s", $admin['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    echo "Found " . $result->num_rows . " users<br>";
}

echo "<br><a href='auth/login.php'>Try Login</a>";
?>
