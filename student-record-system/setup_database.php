<?php
// Database setup script
$host = "localhost";
$username = "root";
$password = "";
$database = "student_record_system";

// Create connection without database first
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $database";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db($database);

// Read and execute the SQL file
$sql_file = __DIR__ . '/database.sql';
if (file_exists($sql_file)) {
    $sql = file_get_contents($sql_file);
    
    // Split SQL statements by semicolon
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            if ($conn->query($statement) === TRUE) {
                echo "✓ Executed: " . substr($statement, 0, 50) . "...<br>";
            } else {
                echo "✗ Error: " . $conn->error . "<br>";
                echo "Statement: " . $statement . "<br><br>";
            }
        }
    }
    
    echo "<br><strong>Database setup completed!</strong><br>";
    echo "You can now login with: admin / admin123<br>";
    echo '<a href="auth/login.php">Go to Login</a>';
    
} else {
    echo "Error: database.sql file not found!";
}

$conn->close();
?>
