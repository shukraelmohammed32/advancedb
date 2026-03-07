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

// Read and execute SQL file
$sql_file = __DIR__ . '/database.sql';
if (!file_exists($sql_file)) {
    // Backward-compatible fallback
    $fallback_file = __DIR__ . '/database_fixed.sql';
    if (file_exists($fallback_file)) {
        $sql_file = $fallback_file;
    }
}

if (file_exists($sql_file)) {
    $sql = file_get_contents($sql_file);

    if ($sql === false) {
        echo "Error reading SQL file.<br>";
    } elseif ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());

        if ($conn->error) {
            echo "Database setup completed with errors: " . $conn->error . "<br>";
        } else {
            echo "<br><strong>Database setup completed successfully!</strong><br>";
            echo "You can now login with: admin / admin123<br>";
            echo '<a href="auth/login.php">Go to Login</a>';
        }
    } else {
        echo "Error executing SQL file: " . $conn->error . "<br>";
    }
} else {
    echo "Error: database.sql file not found!";
}

$conn->close();
?>
