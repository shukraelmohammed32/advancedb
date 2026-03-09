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

function eastGradeLabels() {
    $grades = [];
    for ($grade = 1; $grade <= 8; $grade++) {
        $grades[] = 'Grade ' . $grade;
    }

    return $grades;
}

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

heading('Setting Up Independent East Campus Branch');

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
$sharedReferenceTables = ['academic_years'];

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

foreach ($sharedReferenceTables as $table) {
    queryOrFail(
        $branch,
        'INSERT INTO `' . $table . '` SELECT * FROM `' . $centralDbName . '`.`' . $table . '`',
        'Failed to copy reference table ' . $table
    );
}
$gradeValues = [];
foreach (eastGradeLabels() as $gradeName) {
    $gradeValues[] = "('" . $branch->real_escape_string($gradeName) . "')";
}
queryOrFail(
    $branch,
    'INSERT INTO `grades` (grade_name) VALUES ' . implode(', ', $gradeValues),
    'Failed to seed East branch grades'
);
out('Copied academic years and seeded East grades (Grade 1 to Grade 8).');

$adminPasswordHash = password_hash(EAST_ADMIN_PASSWORD, PASSWORD_DEFAULT);
$adminUsername = $branch->real_escape_string(EAST_ADMIN_USERNAME);
$adminEmail = $branch->real_escape_string(EAST_ADMIN_EMAIL);
$adminPasswordHashEscaped = $branch->real_escape_string($adminPasswordHash);
queryOrFail(
    $branch,
    "INSERT INTO users (username, password, email, role, student_id, teacher_id, is_active)
     VALUES ('" . $adminUsername . "', '" . $adminPasswordHashEscaped . "', '" . $adminEmail . "', 'admin', NULL, NULL, 1)",
    'Failed to create East admin account'
);
out('Created standalone East admin account.');

$counts = [
    'students' => 0,
    'teachers' => 0,
    'subjects' => 0,
    'marks' => 0,
];

foreach (array_keys($counts) as $table) {
    $result = $branch->query('SELECT COUNT(*) AS count FROM `' . $table . '`');
    if ($result) {
        $row = $result->fetch_assoc();
        $counts[$table] = (int)($row['count'] ?? 0);
    }
}

heading('East Campus Setup Complete', 3);
out('Mode: standalone branch with independent local data');
out('Database: ' . EAST_DB_NAME);
out('Site ID: ' . EAST_SITE_ID);
out('Admin login: ' . EAST_ADMIN_USERNAME . ' / ' . EAST_ADMIN_PASSWORD);
out('Grade range: Grade 1 to Grade 8');
out('Teachers: ' . $counts['teachers']);
out('Subjects: ' . $counts['subjects']);
out('Students: ' . $counts['students']);
out('Marks: ' . $counts['marks']);
out('You can now add East-only teachers, subjects, students, and marks inside the branch app.');

$branch->close();
$server->close();
?>
