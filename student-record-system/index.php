<?php
require_once 'config/database.php';
require_once 'auth/auth_helper.php';

requireLogin();

$db = new Database();
$conn = $db->getConnection();

// Get statistics
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$totalTeachers = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$totalSubjects = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
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
    <!-- Professional Navigation -->
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
                    <?php if (canAccessStudentRecords()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/students.php">Students</a>
                    </li>
                    <?php endif; ?>
                    <?php if (canAccessTeacherDashboard()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/subjects.php">Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/teachers.php">Teachers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/marks.php">Marks</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/report.php">Reports</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo $_SESSION['display_name']; ?> (<?php echo ucfirst($_SESSION['role']); ?>)
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container mt-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="dashboard-title">Dashboard</h1>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <h3><?php echo $totalStudents; ?></h3>
                            <p>Total Students</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <h3><?php echo $totalTeachers; ?></h3>
                            <p>Total Teachers</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <h3><?php echo $totalSubjects; ?></h3>
                            <p>Total Subjects</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card quick-actions">
                        <div class="card-header">
                            Quick Actions
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (canAccessStudentRecords()): ?>
                                <div class="col-md-6 mb-2">
                                    <a href="pages/students.php" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-user-plus"></i> Add Student
                                    </a>
                                </div>
                                <?php endif; ?>
                                <?php if (canAccessTeacherDashboard()): ?>
                                <div class="col-md-6 mb-2">
                                    <a href="pages/marks.php" class="btn btn-success btn-lg w-100">
                                        <i class="fas fa-edit"></i> Enter Marks
                                    </a>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <a href="pages/teachers.php" class="btn btn-info btn-lg w-100">
                                        <i class="fas fa-chalkboard-teacher"></i> Manage Teachers
                                    </a>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-6 mb-2">
                                    <a href="pages/report.php" class="btn btn-warning btn-lg w-100">
                                        <i class="fas fa-chart-bar"></i> Generate Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Professional Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>About System</h4>
                    <p>Professional Student Record Management System designed to streamline academic administration and enhance educational efficiency.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Dashboard</a></li>
                        <li><a href="pages/students.php">Students</a></li>
                        <li><a href="pages/report.php">Reports</a></li>
                        <li><a href="auth/logout.php">Logout</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <p>&#x1F4E7; support@school.edu<br>
                       &#x1F4F1; +1 (555) 123-4567<br>
                       &#x1F4CD; 123 Education Street</p>
                </div>
                <div class="footer-section">
                    <h4>System Info</h4>
                    <p>Version 2.0<br>
                       Last Updated: 2024<br>
                       Powered by PHP & MySQL</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Student Record Management System. All rights reserved. | Designed with &#x2764; for Education</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>