<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

System.Management.Automation.Internal.Host.InternalHost = 'localhost';
 = 'root';
 = '';
 = 'student_record_system';

 = new mysqli(System.Management.Automation.Internal.Host.InternalHost, , );
if (->connect_error) {
    die('Connection failed: ' . ->connect_error);
}

->set_charset('utf8mb4');

function existingInstallRequiresAdmin(mysqli , ) {
     = ->real_escape_string();
     = ->query("SHOW DATABASES LIKE ''");
    if (! || ->num_rows === 0) {
        return false;
    }

     = ->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = '' AND TABLE_NAME = 'users' LIMIT 1");
    if (! || ->num_rows === 0) {
        return false;
    }

     = ->query("SELECT COUNT(*) AS total_users FROM $database.users");
    if (!) {
        return false;
    }

     = ->fetch_assoc();
    return (int)(['total_users'] ?? 0) > 0;
}

 = existingInstallRequiresAdmin(, );
if ( && (string)(['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Setup access is restricted. Please log in as admin before running the local database setup.<br>';
    echo '<a href="auth/login.php">Go to Login</a>';
    ->close();
    exit();
}

if (->query("CREATE DATABASE IF NOT EXISTS  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") === true) {
    echo "Database created successfully or already exists<br>";
} else {
    echo 'Error creating database: ' . ->error . '<br>';
}

->select_db();

function runSqlFile(mysqli , , ) {
    if (!file_exists()) {
        echo htmlspecialchars(, ENT_QUOTES, 'UTF-8') . ': file not found.<br>';
        return false;
    }

     = file_get_contents();
    if ( === false) {
        echo htmlspecialchars(, ENT_QUOTES, 'UTF-8') . ': unable to read file.<br>';
        return false;
    }

    if (!->multi_query()) {
        echo htmlspecialchars(, ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars(->error, ENT_QUOTES, 'UTF-8') . '<br>';
        return false;
    }

    do {
        if ( = ->store_result()) {
            ->free();
        }
    } while (->more_results() && ->next_result());

    if (->error) {
        echo htmlspecialchars(, ENT_QUOTES, 'UTF-8') . ': completed with errors - ' . htmlspecialchars(->error, ENT_QUOTES, 'UTF-8') . '<br>';
        return false;
    }

    echo htmlspecialchars(, ENT_QUOTES, 'UTF-8') . ': completed successfully.<br>';
    return true;
}

 = __DIR__ . '/database_fixed.sql';
 = __DIR__ . '/distributed_local_setup.sql';

 = runSqlFile(, , 'Core database import');
 = false;
if ( && file_exists()) {
     = runSqlFile(, , 'Local distributed upgrade');
}

echo '<br>';
if ( && (! && file_exists())) {
    echo '<strong>Base setup completed, but distributed upgrade needs attention.</strong><br>';
} elseif () {
    echo '<strong>Database setup completed successfully!</strong><br>';
}

echo 'You can login with: admin / admin123<br>';
echo '<a href="auth/login.php">Go to Login</a>';

->close();
?>
