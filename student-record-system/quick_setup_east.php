<?php

// Quick setup script for east campus
echo "<h2>Quick East Campus Setup</h2>";

// Step 1: Create east campus directory
$east_campus_path = "c:\\xampp\\htdocs\\east_campus";
if (!is_dir($east_campus_path)) {
    if (mkdir($east_campus_path, 0777, true)) {
        echo "<p>??? Created east campus directory: $east_campus_path</p>";
    } else {
        echo "<p>??? Failed to create east campus directory</p>";
        exit;
    }
} else {
    echo "<p>?????? East campus directory already exists</p>";
}

// Step 2: Copy essential files
$source_path = __DIR__;
$destination_path = $east_campus_path;

$files_to_copy = [
    'config/',
    'auth/',
    'pages/',
    'assets/',
    'includes/',
    'index.php',
    'database_fixed.sql',
    'setup_east_campus.php',
    '.env.east',
    'config/env.php',
    'config/app_config.php',
    'config/bootstrap.php',
    'config/database.php',
    'config/error_handler.php'
];

echo "<p>???? Copying files...</p>";
$copied_count = 0;

function copyDirectory($src, $dst) {
    global $copied_count;
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                copyDirectory($src . '/' . $file,$dst . '/' . $file);
            } else {
                copy($src . '/' . $file,$dst . '/' . $file);
                $copied_count++;
            }
        }
    }
    closedir($dir);
}

foreach ($files_to_copy as $file) {
    $source = $source_path . '/' . $file;
    $destination = $destination_path . '/' . $file;
    
    if (is_dir($source)) {
        copyDirectory($source, $destination);
        echo "<p>??? Copied directory: $file</p>";
    } elseif (file_exists($source)) {
        if (copy($source, $destination)) {
            echo "<p>??? Copied file: $file</p>";
            $copied_count++;
        } else {
            echo "<p>??? Failed to copy: $file</p>";
        }
    } else {
        echo "<p>?????? File not found: $file</p>";
    }
}

// Step 3: Create .env file for east campus
$env_source = $source_path . '/.env.east';
$env_dest = $destination_path . '/.env';

if (file_exists($env_source)) {
    if (copy($env_source, $env_dest)) {
        echo "<p>??? Created .env file for east campus</p>";
    } else {
        echo "<p>??? Failed to create .env file</p>";
    }
} else {
    echo "<p>?????? .env.east not found, creating basic .env</p>";
    $basic_env = "DB_HOST=localhost\nDB_USERNAME=root\nDB_PASSWORD=\nDB_DATABASE=student_record_system_branch_east\nDEFAULT_SITE_ID=3\nADMIN_USERNAME=east_admin\nADMIN_PASSWORD=eastadmin123\nAPP_URL=http://localhost/east_campus";
    file_put_contents($env_dest, $basic_env);
}

echo "<p>???? Total files copied: $copied_count</p>";

// Step 4: Create index.php if it doesn't exist
$index_file = $destination_path . '/index.php';
if (!file_exists($index_file)) {
    $index_content = '<?php
require_once "config/bootstrap.php";
require_once "config/database.php";
require_once "auth/auth_helper.php";

// Check authentication
requireLogin();

// Check maintenance mode
if (AppConfig::isMaintenanceMode()) {
    http_response_code(503);
    echo "<h1>System Under Maintenance</h1><p>Please try again later.</p>";
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get statistics
$totalStudents = (int)$conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()[\'count\'];
$totalTeachers = (int)$conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()[\'count\'];
$totalSubjects = (int)$conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()[\'count\'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>East Campus - Student Record System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>???? East Campus - Student Record System</h1>
        <div class="alert alert-info">
            <strong>Welcome to East Campus!</strong> This is your local branch.
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>???? Students</h5>
                        <h3><?php echo $totalStudents; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>??????????? Teachers</h5>
                        <h3><?php echo $totalTeachers; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>???? Subjects</h5>
                        <h3><?php echo $totalSubjects; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="pages/students.php" class="btn btn-primary">Manage Students</a>
            <a href="pages/subjects.php" class="btn btn-secondary">Manage Subjects</a>
            <a href="auth/logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>
</body>
</html>';
    file_put_contents($index_file, $index_content);
    echo "<p>??? Created basic index.php</p>";
}

echo "<h3>???? Setup Complete!</h3>";
echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px;'>";
echo "<h4>Next Steps:</h4>";
echo "<p>1. Run the database setup: <a href='setup_east_campus.php'>Click here to setup database</a></p>";
echo "<p>2. Access east campus at: <a href='http://localhost/east_campus' target='_blank'>http://localhost/east_campus</a></p>";
echo "<p>3. Login with: east_admin / eastadmin123</p>";
echo "</div>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #1976d2; }
h3 { color: #388e3c; }
h4 { color: #f57c00; }
</style>

