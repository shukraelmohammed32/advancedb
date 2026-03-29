<?php

// Bootstrap the application
require_once 'config/bootstrap.php';

// Load database configuration
require_once 'config/database.php';

echo "<h2>North Campus Debug Information</h2>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<h3>1. Main Database Connection</h3>";
    if ($conn) {
        echo "<p>âœ… Main database connection successful</p>";
        
        // Check if distributed_sites table exists
        $result = $conn->query("SHOW TABLES LIKE 'distributed_sites'");
        if ($result && $result->num_rows > 0) {
            echo "<p>âœ… distributed_sites table exists</p>";
            
            // Get all sites
            $sites_result = $conn->query("SELECT * FROM distributed_sites ORDER BY site_id");
            if ($sites_result) {
                echo "<h4>Registered Sites:</h4>";
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>Site ID</th><th>Site Name</th><th>Site Code</th><th>DB Name</th><th>Active</th><th>Default</th></tr>";
                
                while ($site = $sites_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($site['site_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($site['site_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($site['site_code']) . "</td>";
                    echo "<td>" . htmlspecialchars($site['db_name']) . "</td>";
                    echo "<td>" . ($site['is_active'] ? 'Yes' : 'No') . "</td>";
                    echo "<td>" . ($site['is_default'] ? 'Yes' : 'No') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<p>âŒ distributed_sites table does not exist</p>";
        }
    } else {
        echo "<p>âŒ Main database connection failed</p>";
    }
    
    echo "<h3>2. Check North Campus Database</h3>";
    
    // Try to connect to north campus database
    $north_db_name = 'student_record_system_branch_north';
    $north_conn = new mysqli(
        AppConfig::getDatabaseHost(), 
        AppConfig::getDatabaseUsername(), 
        AppConfig::getDatabasePassword(), 
        $north_db_name
    );
    
    if ($north_conn->connect_error) {
        echo "<p>âŒ North Campus database connection failed: " . htmlspecialchars($north_conn->connect_error) . "</p>";
        echo "<p>Attempting to create North Campus database...</p>";
        
        // Create the database
        $server_conn = new mysqli(
            AppConfig::getDatabaseHost(), 
            AppConfig::getDatabaseUsername(), 
            AppConfig::getDatabasePassword()
        );
        
        if ($server_conn->query("CREATE DATABASE IF NOT EXISTS `$north_db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
            echo "<p>âœ… North Campus database created</p>";
            
            // Import structure from main database
            echo "<p>Importing database structure...</p>";
            $sql_file = __DIR__ . '/database_fixed.sql';
            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                $north_conn = new mysqli(
                    AppConfig::getDatabaseHost(), 
                    AppConfig::getDatabaseUsername(), 
                    AppConfig::getDatabasePassword(), 
                    $north_db_name
                );
                
                $imported = 0;
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        if ($north_conn->query($statement)) {
                            $imported++;
                        }
                    }
                }
                
                echo "<p>âœ… Imported $imported SQL statements</p>";
            } else {
                echo "<p>âŒ database_fixed.sql not found</p>";
            }
        } else {
            echo "<p>âŒ Failed to create North Campus database</p>";
        }
    } else {
        echo "<p>âœ… North Campus database connection successful</p>";
        
        // Check tables and counts
        echo "<h4>North Campus Data:</h4>";
        $tables = ['teachers', 'subjects', 'students', 'marks'];
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Table</th><th>Record Count</th></tr>";
        
        foreach ($tables as $table) {
            $result = $north_conn->query("SELECT COUNT(*) as count FROM `$table`");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "<tr><td>$table</td><td>" . $row['count'] . "</td></tr>";
            } else {
                echo "<tr><td>$table</td><td>Error: " . htmlspecialchars($north_conn->error) . "</td></tr>";
            }
        }
        
        echo "</table>";
        
        // Add some sample data if empty
        $teacher_count = $north_conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
        $subject_count = $north_conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
        
        if ($teacher_count == 0 || $subject_count == 0) {
            echo "<h3>3. Adding Sample Data to North Campus</h3>";
            
            if ($subject_count == 0) {
                echo "<p>Adding sample subjects...</p>";
                $subjects = [
                    ['Mathematics', 'MAT', 'Mathematics'],
                    ['Science', 'SCI', 'General Science'],
                    ['English', 'ENG', 'English Language'],
                    ['History', 'HIS', 'World History']
                ];
                
                foreach ($subjects as $subject) {
                    $north_conn->query("INSERT INTO subjects (subject_name, subject_code, description) VALUES (?, ?, ?)");
                    $stmt = $north_conn->prepare("INSERT INTO subjects (subject_name, subject_code, description) VALUES (?, ?, ?)");
                    $stmt->bind_param('sss', $subject[0], $subject[1], $subject[2]);
                    $stmt->execute();
                }
                echo "<p>âœ… Added 4 sample subjects</p>";
            }
            
            if ($teacher_count == 0) {
                echo "<p>Adding sample teachers...</p>";
                $teachers = [
                    ['John Smith', 'john.smith@north.edu', 'Mathematics'],
                    ['Jane Doe', 'jane.doe@north.edu', 'Science'],
                    ['Bob Wilson', 'bob.wilson@north.edu', 'English']
                ];
                
                foreach ($teachers as $teacher) {
                    $north_conn->query("INSERT INTO teachers (teacher_name, email, subject) VALUES (?, ?, ?)");
                    $stmt = $north_conn->prepare("INSERT INTO teachers (teacher_name, email, subject) VALUES (?, ?, ?)");
                    $stmt->bind_param('sss', $teacher[0], $teacher[1], $teacher[2]);
                    $stmt->execute();
                }
                echo "<p>âœ… Added 3 sample teachers</p>";
            }
        }
        
        $north_conn->close();
    }
    
    // Register North Campus in distributed_sites if not exists
    echo "<h3>4. Register North Campus in Distributed System</h3>";
    
    $check_site = $conn->query("SELECT * FROM distributed_sites WHERE site_code = 'NORTH'");
    if ($check_site && $check_site->num_rows == 0) {
        echo "<p>Registering North Campus...</p>";
        $stmt = $conn->prepare("INSERT INTO distributed_sites (site_id, site_name, site_code, db_name, is_default, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $site_id = 3;
        $site_name = 'North Campus';
        $site_code = 'NORTH';
        $db_name = $north_db_name;
        $is_default = 0;
        $is_active = 1;
        
        $stmt->bind_param('isssii', $site_id, $site_name, $site_code, $db_name, $is_default, $is_active);
        if ($stmt->execute()) {
            echo "<p>âœ… North Campus registered in distributed system</p>";
        } else {
            echo "<p>âŒ Failed to register North Campus: " . htmlspecialchars($stmt->error) . "</p>";
        }
    } else {
        echo "<p>âœ… North Campus already registered</p>";
    }
    
    echo "<h3>5. Test Distributed Coordinator</h3>";
    
    if (file_exists('includes/distributed_coordinator.php')) {
        require_once 'includes/distributed_coordinator.php';
        $coordinator = new DistributedCoordinator($db);
        
        if ($coordinator->isDistributedReady()) {
            echo "<p>âœ… Distributed system is ready</p>";
            
            $stats = $coordinator->getSiteStats();
            echo "<h4>Site Statistics:</h4>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Site</th><th>Status</th><th>Teachers</th><th>Subjects</th><th>Students</th><th>Marks</th></tr>";
            
            foreach ($stats as $stat) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($stat['site_name']) . "</td>";
                echo "<td>" . htmlspecialchars($stat['connection_status']) . "</td>";
                echo "<td>" . ($stat['total_teachers'] ?? 'N/A') . "</td>";
                echo "<td>" . ($stat['total_subjects'] ?? 'N/A') . "</td>";
                echo "<td>" . ($stat['total_students'] ?? 'N/A') . "</td>";
                echo "<td>" . ($stat['total_marks'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>âŒ Distributed system is not ready</p>";
        }
    } else {
        echo "<p>âŒ distributed_coordinator.php not found</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>âŒ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #1976d2; }
h3 { color: #388e3c; }
h4 { color: #f57c00; }
table { margin: 10px 0; }
th { background: #f0f0f0; }
</style>

