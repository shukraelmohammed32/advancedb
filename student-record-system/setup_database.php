<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'student_record_system';

$conn = new mysqli($host, $username, $password);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

if ($conn->query("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") === true) {
    echo "Database created successfully or already exists<br>";
} else {
    echo 'Error creating database: ' . $conn->error . '<br>';
}

$conn->select_db($database);

function runSqlFile(mysqli $conn, $file_path, $label) {
    if (!file_exists($file_path)) {
        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ': file not found.<br>';
        return false;
    }

    $sql = file_get_contents($file_path);
    if ($sql === false) {
        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ': unable to read file.<br>';
        return false;
    }

    if (!$conn->multi_query($sql)) {
        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . '<br>';
        return false;
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    if ($conn->error) {
        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ': completed with errors - ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . '<br>';
        return false;
    }

    echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ': completed successfully.<br>';
    return true;
}

$core_sql = __DIR__ . '/database_fixed.sql';
$distributed_sql = __DIR__ . '/distributed_local_setup.sql';

$core_ok = runSqlFile($conn, $core_sql, 'Core database import');
$distributed_ok = false;
if ($core_ok && file_exists($distributed_sql)) {
    $distributed_ok = runSqlFile($conn, $distributed_sql, 'Local distributed upgrade');
}

echo '<br>';
if ($core_ok && (!$distributed_ok && file_exists($distributed_sql))) {
    echo '<strong>Base setup completed, but distributed upgrade needs attention.</strong><br>';
} elseif ($core_ok) {
    echo '<strong>Database setup completed successfully!</strong><br>';
}

echo 'You can login with: admin / admin123<br>';
echo '<a href="auth/login.php">Go to Login</a>';

$conn->close();
?>
