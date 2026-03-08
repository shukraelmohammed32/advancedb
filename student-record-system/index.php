<?php

// Bootstrap the application
require_once 'config/bootstrap.php';

// Load core files
require_once 'config/database.php';
require_once 'auth/auth_helper.php';

// Check if distributed coordinator exists before including
if (file_exists('includes/distributed_coordinator.php')) {
    require_once 'includes/distributed_coordinator.php';
} else {
    // Create a dummy coordinator class if file doesn't exist
    class DistributedCoordinator {
        private $database;
        public function __construct($db) { $this->database = $db; }
        public function isDistributedReady() { return false; }
        public function getSiteStats() { return []; }
    }
}

// Check authentication
requireLogin();

// Check maintenance mode
if (AppConfig::isMaintenanceMode()) {
    http_response_code(503);
    if (file_exists('maintenance.php')) {
        include 'maintenance.php';
    } else {
        echo '<h1>System Under Maintenance</h1><p>Please try again later.</p>';
    }
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$coordinator = new DistributedCoordinator($db);
$distributed_ready = $coordinator->isDistributedReady();
$can_access_distributed = canAccessDistributedCoordinator();
$site_stats = $can_access_distributed ? $coordinator->getSiteStats() : [];

$totalStudents = (int)$conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$totalTeachers = (int)$conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$totalSubjects = (int)$conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Record Management System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Student Record System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Dashboard</a>
                    </li>
                    <?php if (canViewStudentDirectory()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/students.php">Students</a>
                    </li>
                    <?php endif; ?>
                    <?php if (canViewSubjects()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/subjects.php">Subjects</a>
                    </li>
                    <?php endif; ?>
                    <?php if (canManageTeachers()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/teachers.php">Teachers</a>
                    </li>
                    <?php endif; ?>
                    <?php if (canEnterMarks()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/marks.php">Marks</a>
                    </li>
                    <?php endif; ?>
                    <?php if (canAccessSummary()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/summary.php">Summary</a>
                    </li>
                    <?php endif; ?>
                    <?php if (canViewStudentReports()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/report.php">Reports</a>
                    </li>
                    <?php endif; ?>
                    <?php if (canOnlyViewOwnRecords()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/profile.php">Profile</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars((string)($_SESSION['display_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars(getRoleLabel(), ENT_QUOTES, 'UTF-8'); ?>)
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="container mt-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="dashboard-title">Dashboard</h1>
                </div>
            </div>


            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card stats-card students-card">
                        <div class="card-body">
                            <span class="stats-label">Students</span>
                            <h3><?php echo $totalStudents; ?></h3>
                            <p class="stats-title">Total Students</p>
                            <p class="stats-description">All registered learners currently stored in the system.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card teachers-card">
                        <div class="card-body">
                            <span class="stats-label">Teachers</span>
                            <h3><?php echo $totalTeachers; ?></h3>
                            <p class="stats-title">Total Teachers</p>
                            <p class="stats-description">Faculty members handling subjects, classes, and compiled results.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card subjects-card">
                        <div class="card-body">
                            <span class="stats-label">Subjects</span>
                            <h3><?php echo $totalSubjects; ?></h3>
                            <p class="stats-title">Total Subjects</p>
                            <p class="stats-description">Courses used for marks, rankings, and final academic reports.</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($can_access_distributed && $distributed_ready && !empty($site_stats)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">Local Distributed Sites</div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($site_stats as $site_stat): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <strong><?php echo htmlspecialchars($site_stat['site_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if (!empty($site_stat['is_default'])): ?>
                                            <span class="badge bg-primary">Default</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small mb-2"><?php echo htmlspecialchars($site_stat['db_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="mb-1">Students: <strong><?php echo (int)$site_stat['total_students']; ?></strong></div>
                                        <div>Marks: <strong><?php echo (int)$site_stat['total_marks']; ?></strong></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card quick-actions">
                        <div class="card-header">Quick Actions</div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (isAdmin()): ?>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/students.php" class="btn btn-primary btn-lg w-100">Manage Students</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/subjects.php" class="btn btn-secondary btn-lg w-100">Manage Subjects</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/teachers.php" class="btn btn-info btn-lg w-100">Manage Teachers</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/summary.php" class="btn btn-dark btn-lg w-100">Review Summary</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/report.php" class="btn btn-warning btn-lg w-100">Generate Reports</a>
                                </div>
                                <?php elseif (isHomeroomTeacher()): ?>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/students.php" class="btn btn-primary btn-lg w-100">My Class Roster</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/subjects.php" class="btn btn-secondary btn-lg w-100">My Subjects</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/marks.php" class="btn btn-success btn-lg w-100">Enter Marks</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/summary.php" class="btn btn-dark btn-lg w-100">Compile Results</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/report.php" class="btn btn-warning btn-lg w-100">Generate Reports</a>
                                </div>
                                <?php elseif (isTeacher()): ?>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/students.php" class="btn btn-primary btn-lg w-100">Assigned Students</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/subjects.php" class="btn btn-secondary btn-lg w-100">My Subjects</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/marks.php" class="btn btn-success btn-lg w-100">Submit Marks</a>
                                </div>
                                <?php elseif (isStudent()): ?>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/report.php" class="btn btn-warning btn-lg w-100">View My Report</a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/profile.php" class="btn btn-primary btn-lg w-100">Update Profile</a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $footer_base_path = '';
    include __DIR__ . '/includes/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
