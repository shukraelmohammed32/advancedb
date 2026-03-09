<?php

// Bootstrap the application
require_once 'config/bootstrap.php';

// Load core files
require_once 'config/database.php';
require_once 'auth/auth_helper.php';
require_once 'includes/distributed_coordinator.php';

echo "<h2>Dashboard vs Insights Data Fix</h2>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    $coordinator = new DistributedCoordinator($db);
    
    echo "<h3>1. Main Dashboard Counts (Direct Database)</h3>";
    
    // Get main dashboard counts
    $totalStudents = (int)$conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
    $totalTeachers = (int)$conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
    $totalSubjects = (int)$conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
    
    echo "<table border='1' cellpadding='5' style='margin: 10px 0;'>";
    echo "<tr><th>Entity</th><th>Count (Dashboard)</th></tr>";
    echo "<tr><td>Students</td><td>$totalStudents</td></tr>";
    echo "<tr><td>Teachers</td><td>$totalTeachers</td></tr>";
    echo "<tr><td>Subjects</td><td>$totalSubjects</td></tr>";
    echo "</table>";
    
    echo "<h3>2. Distributed Coordinator Status</h3>";
    
    if ($coordinator->isDistributedReady()) {
        echo "<p>✅ Distributed system is ready</p>";
        
        $site_stats = $coordinator->getSiteStats();
        echo "<h4>Site Statistics from Distributed Coordinator:</h4>";
        echo "<table border='1' cellpadding='5' style='margin: 10px 0;'>";
        echo "<tr><th>Site</th><th>DB Name</th><th>Teachers</th><th>Subjects</th><th>Students</th><th>Status</th></tr>";
        
        foreach ($site_stats as $stat) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($stat['site_name']) . "</td>";
            echo "<td>" . htmlspecialchars($stat['db_name']) . "</td>";
            echo "<td>" . ($stat['total_teachers'] ?? 'N/A') . "</td>";
            echo "<td>" . ($stat['total_subjects'] ?? 'N/A') . "</td>";
            echo "<td>" . ($stat['total_students'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($stat['connection_status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check each site's database connection
        echo "<h3>3. Individual Site Database Checks</h3>";
        
        foreach ($site_stats as $stat) {
            $site_id = $stat['site_id'];
            $site_name = $stat['site_name'];
            $db_name = $stat['db_name'];
            
            echo "<h4>Site: $site_name (ID: $site_id, DB: $db_name)</h4>";
            
            // Try to connect to the site's database
            $site_conn = new mysqli(
                AppConfig::getDatabaseHost(), 
                AppConfig::getDatabaseUsername(), 
                AppConfig::getDatabasePassword(), 
                $db_name
            );
            
            if ($site_conn->connect_error) {
                echo "<p style='color: red;'>❌ Database connection failed: " . htmlspecialchars($site_conn->connect_error) . "</p>";
                
                // Try to create the database if it doesn't exist
                echo "<p>Attempting to create database '$db_name'...</p>";
                $server_conn = new mysqli(
                    AppConfig::getDatabaseHost(), 
                    AppConfig::getDatabaseUsername(), 
                    AppConfig::getDatabasePassword()
                );
                
                if ($server_conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
                    echo "<p style='color: green;'>✅ Database created</p>";
                    
                    // Import data from main database
                    echo "<p>Importing data from main database...</p>";
                    $tables = ['teachers', 'subjects', 'students', 'marks'];
                    
                    $site_conn = new mysqli(
                        AppConfig::getDatabaseHost(), 
                        AppConfig::getDatabaseUsername(), 
                        AppConfig::getDatabasePassword(), 
                        $db_name
                    );
                    
                    foreach ($tables as $table) {
                        // Copy table structure
                        $create_table = $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc()['Create Table'];
                        $site_conn->query($create_table);
                        
                        // Copy data
                        $data = $conn->query("SELECT * FROM `$table`");
                        if ($data && $data->num_rows > 0) {
                            while ($row = $data->fetch_assoc()) {
                                $columns = array_keys($row);
                                $values = array_values($row);
                                
                                $escaped_columns = array_map(function($col) use ($site_conn) {
                                    return '`' . $site_conn->real_escape_string($col) . '`';
                                }, $columns);
                                
                                $escaped_values = array_map(function($val) use ($site_conn) {
                                    return $val === null ? 'NULL' : "'" . $site_conn->real_escape_string($val) . "'";
                                }, $values);
                                
                                $sql = "INSERT INTO `$table` (" . implode(', ', $escaped_columns) . ") VALUES (" . implode(', ', $escaped_values) . ")";
                                $site_conn->query($sql);
                            }
                            echo "<p style='color: green;'>✅ Copied " . $data->num_rows . " records to $table</p>";
                        }
                    }
                } else {
                    echo "<p style='color: red;'>❌ Failed to create database</p>";
                }
                $server_conn->close();
            } else {
                echo "<p style='color: green;'>✅ Database connection successful</p>";
                
                // Check table counts
                echo "<table border='1' cellpadding='3' style='margin: 5px 0;'>";
                echo "<tr><th>Table</th><th>Records</th></tr>";
                
                $tables = ['teachers', 'subjects', 'students', 'marks'];
                foreach ($tables as $table) {
                    $result = $site_conn->query("SELECT COUNT(*) as count FROM `$table`");
                    if ($result) {
                        $count = $result->fetch_assoc()['count'];
                        echo "<tr><td>$table</td><td>$count</td></tr>";
                    } else {
                        echo "<tr><td>$table</td><td style='color: red;'>Error: " . htmlspecialchars($site_conn->error) . "</td></tr>";
                    }
                }
                echo "</table>";
            }
            
            $site_conn->close();
        }
        
    } else {
        echo "<p>❌ Distributed system is not ready</p>";
        
        // Check if distributed_sites table exists
        $result = $conn->query("SHOW TABLES LIKE 'distributed_sites'");
        if ($result && $result->num_rows > 0) {
            echo "<p>distributed_sites table exists</p>";
            
            // Check sites
            $sites = $conn->query("SELECT * FROM distributed_sites");
            if ($sites && $sites->num_rows > 0) {
                echo "<h4>Registered Sites:</h4>";
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>Site ID</th><th>Name</th><th>Code</th><th>DB Name</th><th>Active</th></tr>";
                
                while ($site = $sites->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $site['site_id'] . "</td>";
                    echo "<td>" . htmlspecialchars($site['site_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($site['site_code']) . "</td>";
                    echo "<td>" . htmlspecialchars($site['db_name']) . "</td>";
                    echo "<td>" . ($site['is_active'] ? 'Yes' : 'No') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No sites found in distributed_sites table</p>";
            }
        } else {
            echo "<p>distributed_sites table does not exist</p>";
            
            // Create distributed setup
            echo "<p>Setting up distributed system...</p>";
            
            // Create distributed_sites table
            $create_table = "
                CREATE TABLE IF NOT EXISTS distributed_sites (
                    site_id INT PRIMARY KEY,
                    site_name VARCHAR(100) NOT NULL,
                    site_code VARCHAR(20) NOT NULL UNIQUE,
                    db_name VARCHAR(100) NOT NULL UNIQUE,
                    is_default TINYINT(1) DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            if ($conn->query($create_table)) {
                echo "<p style='color: green;'>✅ distributed_sites table created</p>";
                
                // Create coordinator_sync_log table
                $create_log = "
                    CREATE TABLE IF NOT EXISTS coordinator_sync_log (
                        log_id INT AUTO_INCREMENT PRIMARY KEY,
                        entity_type VARCHAR(50) NOT NULL,
                        entity_id INT NOT NULL,
                        site_id INT,
                        action_type VARCHAR(20) NOT NULL,
                        status VARCHAR(20) NOT NULL,
                        message TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_entity (entity_type, entity_id),
                        INDEX idx_site (site_id),
                        INDEX idx_status (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                
                if ($conn->query($create_log)) {
                    echo "<p style='color: green;'>✅ coordinator_sync_log table created</p>";
                    
                    // Add site_id columns to existing tables
                    $tables_to_update = ['students', 'marks'];
                    foreach ($tables_to_update as $table) {
                        $check_column = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'site_id'");
                        if ($check_column && $check_column->num_rows == 0) {
                            $conn->query("ALTER TABLE `$table` ADD COLUMN site_id INT DEFAULT 1");
                            echo "<p style='color: green;'>✅ Added site_id column to $table</p>";
                        }
                    }
                    
                    // Insert main site
                    $conn->query("INSERT IGNORE INTO distributed_sites (site_id, site_name, site_code, db_name, is_default, is_active) VALUES (1, 'Main Campus', 'MAIN', '" . AppConfig::getDatabaseName() . "', 1, 1)");
                    echo "<p style='color: green;'>✅ Main campus registered</p>";
                    
                } else {
                    echo "<p style='color: red;'>❌ Failed to create coordinator_sync_log table</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ Failed to create distributed_sites table</p>";
            }
        }
    }
    
    echo "<h3>4. Final Verification</h3>";
    
    // Test the coordinator again
    if ($coordinator->isDistributedReady()) {
        echo "<p style='color: green;'>✅ Distributed system is now ready</p>";
        
        $final_stats = $coordinator->getSiteStats();
        echo "<h4>Final Site Statistics:</h4>";
        echo "<table border='1' cellpadding='5' style='margin: 10px 0;'>";
        echo "<tr><th>Site</th><th>Teachers</th><th>Subjects</th><th>Students</th></tr>";
        
        foreach ($final_stats as $stat) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($stat['site_name']) . "</td>";
            echo "<td>" . ($stat['total_teachers'] ?? 'N/A') . "</td>";
            echo "<td>" . ($stat['total_subjects'] ?? 'N/A') . "</td>";
            echo "<td>" . ($stat['total_students'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if ($totalTeachers == ($final_stats[0]['total_teachers'] ?? 0) && 
            $totalSubjects == ($final_stats[0]['total_subjects'] ?? 0)) {
            echo "<p style='color: green; font-weight: bold;'>✅ Dashboard and Insights data now match!</p>";
        } else {
            echo "<p style='color: orange; font-weight: bold;'>⚠️ Data counts still don't match. Check individual site databases.</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Distributed system still not ready</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #1976d2; }
h3 { color: #388e3c; }
h4 { color: #f57c00; }
table { margin: 10px 0; border-collapse: collapse; }
th { background: #f0f0f0; padding: 8px; text-align: left; }
td { padding: 8px; border: 1px solid #ddd; }
</style>
