<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'student_record_system';

$conn = new mysqli($host, $username, $password);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

function existingInstallRequiresAdmin(mysqli $conn, $database) {
    $database_safe = $conn->real_escape_string($database);
    $database_result = $conn->query("SHOW DATABASES LIKE '$database_safe'");
    if (!$database_result || $database_result->num_rows === 0) {
        return false;
    }

    $table_result = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database_safe' AND TABLE_NAME = 'users' LIMIT 1");
    if (!$table_result || $table_result->num_rows === 0) {
        return false;
    }

    $count_result = $conn->query("SELECT COUNT(*) AS total_users FROM `$database`.users");
    if (!$count_result) {
        return false;
    }

    $row = $count_result->fetch_assoc();
    return (int)($row['total_users'] ?? 0) > 0;
}

$setup_protected = existingInstallRequiresAdmin($conn, $database);
if ($setup_protected && (string)($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Setup access is restricted. Please log in as admin before running the local database setup.<br>';
    echo '<a href="auth/login.php">Go to Login</a>';
    $conn->close();
    exit();
}

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