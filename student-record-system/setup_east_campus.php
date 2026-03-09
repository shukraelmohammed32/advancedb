<?php

// Bootstrap the application
require_once 'config/bootstrap.php';

// Load database configuration
require_once 'config/database.php';

echo "<h2>Setting Up East Campus Branch</h2>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Create east campus database
    $east_db_name = 'student_record_system_east';
    
    echo "<p>Creating east campus database: $east_db_name</p>";
    
    // Connect to MySQL server (without database)
    $server_conn = new mysqli(
        AppConfig::getDatabaseHost(), 
        AppConfig::getDatabaseUsername(), 
        AppConfig::getDatabasePassword()
    );
    
    if ($server_conn->connect_error) {
        throw new Exception("Failed to connect to MySQL server");
    }
    
    // Create east campus database
    $server_conn->query("CREATE DATABASE IF NOT EXISTS `$east_db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Switch to east campus database
    $server_conn->select_db($east_db_name);
    
    echo "<p>✅ East campus database created</p>";
    
    // Import the main database structure
    echo "<p>Importing database structure...</p>";
    
    $sql_file = __DIR__ . '/database_fixed.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        
        // Split SQL statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                if (!$server_conn->query($statement)) {
                    echo "<p>⚠️ Warning: " . $server_conn->error . "</p>";
                }
            }
        }
        
        echo "<p>✅ Database structure imported</p>";
    } else {
        echo "<p>❌ database_fixed.sql not found</p>";
    }
    
    // Add east campus specific data
    echo "<p>Adding east campus configuration...</p>";
    
    // Insert east campus site
    $server_conn->query("INSERT INTO sites (site_id, site_name, site_code, is_active, is_default) VALUES 
        (2, 'East Campus', 'EAST', 1, 0) ON DUPLICATE KEY UPDATE site_name = 'East Campus'");
    
    // Create east campus admin
    $east_admin_pass = password_hash('eastadmin123', PASSWORD_DEFAULT);
    $server_conn->query("INSERT INTO users (username, password, email, role, is_active, site_id) VALUES 
        ('east_admin', '$east_admin_pass', 'eastadmin@school.edu', 'admin', 1, 2) 
        ON DUPLICATE KEY UPDATE password = '$east_admin_pass'");
    
    echo "<p>✅ East campus configuration added</p>";
    
    // Update main database with east campus info
    $conn->query("INSERT INTO sites (site_id, site_name, site_code, is_active, is_default) VALUES 
        (2, 'East Campus', 'EAST', 1, 0) ON DUPLICATE KEY UPDATE site_name = 'East Campus'");
    
    echo "<h3>🎉 East Campus Setup Complete!</h3>";
    
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>East Campus Access Information:</h4>";
    echo "<p><strong>Database:</strong> $east_db_name</p>";
    echo "<p><strong>Admin Login:</strong> east_admin / eastadmin123</p>";
    echo "<p><strong>Site Code:</strong> EAST</p>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Next Steps:</h4>";
    echo "<ol>";
    echo "<li>Create a separate folder for east campus: <code>c:\xampp\htdocs\east_campus</code></li>";
    echo "<li>Copy all files from main project to east_campus folder</li>";
    echo "<li>Update east_campus/.env file with:</li>";
    echo "<pre>DB_DATABASE=student_record_system_east
DEFAULT_SITE_ID=2</pre>";
    echo "<li>Access east campus at: <code>http://localhost/east_campus</code></li>";
    echo "</ol>";
    echo "</div>";
    
    $server_conn->close();
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #1976d2; }
h3 { color: #388e3c; }
h4 { color: #f57c00; }
pre { background: #f5f5f5; padding: 10px; border-radius: 3px; }
code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; }
</style>
