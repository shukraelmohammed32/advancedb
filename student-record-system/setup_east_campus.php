<?php
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';

const EAST_SITE_ID = 3;
const EAST_SITE_CODE = 'EAST';
const EAST_SITE_NAME = 'East Campus';
const EAST_DB_NAME = 'student_record_system_branch_east';
const EAST_ADMIN_USERNAME = 'east_admin';
const EAST_ADMIN_PASSWORD = 'eastadmin123';
const EAST_ADMIN_EMAIL = 'eastadmin@school.edu';

function out($message) {
    if (PHP_SAPI === 'cli') {
        echo $message . PHP_EOL;
        return;
    }

    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
}

function heading($message, $level = 2) {
    $level = max(1, min(6, (int)$level));

    if (PHP_SAPI === 'cli') {
        echo PHP_EOL . $message . PHP_EOL;
        echo str_repeat('=', strlen($message)) . PHP_EOL;
        return;
    }

    echo '<h' . $level . '>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h' . $level . '>';
}

function fail($message) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
    } else {
        echo '<p style="color:red;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    exit(1);
}

function queryOrFail(mysqli $connection, $sql, $errorPrefix) {
    if (!$connection->query($sql)) {
        fail($errorPrefix . ': ' . $connection->error);
    }
}

heading('Setting Up East Campus Branch');

$db = new Database();
$credentials = $db->getCredentials();
$centralDbName = $credentials['database'];
$server = new mysqli($credentials['host'], $credentials['username'], $credentials['password']);
if ($server->connect_error) {
    fail('Failed to connect to MySQL server: ' . $server->connect_error);
}
$server->set_charset('utf8mb4');

queryOrFail(
    $server,
    'CREATE DATABASE IF NOT EXISTS `' . EAST_DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    'Failed to create East branch database'
);
out('Branch database ready: ' . EAST_DB_NAME);

$branch = new mysqli($credentials['host'], $credentials['username'], $credentials['password'], EAST_DB_NAME);
if ($branch->connect_error) {
    fail('Failed to connect to East branch database: ' . $branch->connect_error);
}
$branch->set_charset('utf8mb4');

queryOrFail(
    $db->getCentralConnection(),
    "INSERT INTO distributed_sites (site_id, site_name, site_code, db_name, is_default, is_active)
     VALUES (" . EAST_SITE_ID . ", '" . EAST_SITE_NAME . "', '" . EAST_SITE_CODE . "', '" . EAST_DB_NAME . "', 0, 1)
     ON DUPLICATE KEY UPDATE
        site_name = VALUES(site_name),
        site_code = VALUES(site_code),
        db_name = VALUES(db_name),
        is_default = VALUES(is_default),
        is_active = VALUES(is_active)",
    'Failed to register East Campus in distributed_sites'
);
out('Central distributed_sites entry confirmed for East Campus.');

$dropOrder = ['student_profiles', 'marks', 'teacher_subjects', 'users', 'students', 'teachers', 'subjects', 'academic_years', 'grades'];
$createOrder = ['grades', 'academic_years', 'subjects', 'teachers', 'students', 'teacher_subjects', 'users', 'marks', 'student_profiles'];
$copyAllTables = ['grades', 'academic_years', 'subjects', 'teachers', 'teacher_subjects'];

queryOrFail($branch, 'SET FOREIGN_KEY_CHECKS=0', 'Failed to disable foreign key checks');
foreach ($dropOrder as $table) {
    queryOrFail($branch, 'DROP TABLE IF EXISTS `' . $table . '`', 'Failed to drop old East table ' . $table);
}
queryOrFail($branch, 'SET FOREIGN_KEY_CHECKS=1', 'Failed to re-enable foreign key checks');

foreach ($createOrder as $table) {
    queryOrFail(
        $branch,
        'CREATE TABLE `' . $table . '` LIKE `' . $centralDbName . '`.`' . $table . '`',
        'Failed to clone schema for ' . $table
    );
}
out('Cloned current schema from central database.');

foreach ($copyAllTables as $table) {
    queryOrFail(
        $branch,
        'INSERT INTO `' . $table . '` SELECT * FROM `' . $centralDbName . '`.`' . $table . '`',
        'Failed to copy reference table ' . $table
    );
}

queryOrFail(
    $branch,
    'INSERT INTO `students` SELECT * FROM `' . $centralDbName . '`.`students` WHERE site_id = ' . EAST_SITE_ID,
    'Failed to copy East students'
);
queryOrFail(
    $branch,
    'INSERT INTO `marks` SELECT * FROM `' . $centralDbName . '`.`marks` WHERE site_id = ' . EAST_SITE_ID,
    'Failed to copy East marks'
);
queryOrFail(
    $branch,
    'INSERT INTO `student_profiles`
     SELECT sp.*
     FROM `' . $centralDbName . '`.`student_profiles` sp
     INNER JOIN `' . $centralDbName . '`.`students` s ON s.student_id = sp.student_id
     WHERE s.site_id = ' . EAST_SITE_ID,
    'Failed to copy East student profiles'
);
queryOrFail(
    $branch,
    "INSERT INTO `users`
     SELECT *
     FROM `" . $centralDbName . "`.`users`
     WHERE role = 'teacher'
        OR (role = 'student' AND student_id IN (SELECT student_id FROM `" . $centralDbName . "`.`students` WHERE site_id = " . EAST_SITE_ID . '))',
    'Failed to copy East branch user accounts'
);

$adminPasswordHash = password_hash(EAST_ADMIN_PASSWORD, PASSWORD_DEFAULT);
$adminUsername = $branch->real_escape_string(EAST_ADMIN_USERNAME);
$adminEmail = $branch->real_escape_string(EAST_ADMIN_EMAIL);
$adminPasswordHashEscaped = $branch->real_escape_string($adminPasswordHash);
queryOrFail(
    $branch,
    "INSERT INTO users (username, password, email, role, student_id, teacher_id, is_active)
     VALUES ('" . $adminUsername . "', '" . $adminPasswordHashEscaped . "', '" . $adminEmail . "', 'admin', NULL, NULL, 1)
     ON DUPLICATE KEY UPDATE
        password = VALUES(password),
        email = VALUES(email),
        role = 'admin',
        is_active = 1",
    'Failed to create East admin account'
);
out('Reference data and East admin account seeded.');

$studentCount = 0;
$result = $branch->query('SELECT COUNT(*) AS count FROM students');
if ($result) {
    $row = $result->fetch_assoc();
    $studentCount = (int)($row['count'] ?? 0);
}

$markCount = 0;
$result = $branch->query('SELECT COUNT(*) AS count FROM marks');
if ($result) {
    $row = $result->fetch_assoc();
    $markCount = (int)($row['count'] ?? 0);
}

$teacherCount = 0;
$result = $branch->query('SELECT COUNT(*) AS count FROM teachers');
if ($result) {
    $row = $result->fetch_assoc();
    $teacherCount = (int)($row['count'] ?? 0);
}

heading('East Campus Setup Complete', 3);
out('Database: ' . EAST_DB_NAME);
out('Site ID: ' . EAST_SITE_ID);
out('Admin login: ' . EAST_ADMIN_USERNAME . ' / ' . EAST_ADMIN_PASSWORD);
out('Teachers copied: ' . $teacherCount);
out('East students copied: ' . $studentCount);
out('East marks copied: ' . $markCount);
out('Next step: copy this app to C:\\xampp\\htdocs\\east_campus and place .env.east there as .env.');

$branch->close();
$server->close();
?>
