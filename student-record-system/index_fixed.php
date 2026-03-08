<?php
require_once 'config/database.php';
require_once 'auth/auth_helper.php';

requireLogin();

$db = new Database();
$conn = $db->getConnection();

// Get enhanced statistics
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$totalTeachers = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$totalSubjects = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
$totalMarks = $conn->query("SELECT COUNT(*) as count FROM marks")->fetch_assoc()['count'];

// Get recent activity
$recentMarks = $conn->query("
    SELECT s.name as student_name, sub.subject_name, m.score, m.created_at 
    FROM marks m 
    JOIN students s ON m.student_id = s.student_id 
    JOIN subjects sub ON m.subject_id = sub.subject_id 
    ORDER BY m.created_at DESC 
    LIMIT 5
");

// Get top performers
$topStudents = $conn->query("
    SELECT s.name, AVG(m.score) as avg_score, COUNT(m.mark_id) as subject_count
    FROM students s 
    LEFT JOIN marks m ON s.student_id = m.student_id 
    GROUP BY s.student_id, s.name 
    HAVING subject_count > 0 
    ORDER BY avg_score DESC 
    LIMIT 3
");

// Get subject performance
$subjectStats = $conn->query("
    SELECT sub.subject_name, COUNT(m.mark_id) as student_count, AVG(m.score) as avg_score
    FROM subjects sub 
    LEFT JOIN marks m ON sub.subject_id = m.subject_id 
    GROUP BY sub.subject_id, sub.subject_name 
    ORDER BY avg_score DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Record Management System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <style>
        .dashboard-welcome {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        .dashboard-welcome h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        .dashboard-welcome p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .activity-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 0;
            margin-bottom: 20px;
        }
        .activity-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0;
            font-weight: 600;
        }
        .recent-activity {
            max-height: 400px;
            overflow-y: auto;
        }
        .recent-activity .list-group-item {
            border-left: 4px solid #667eea;
            margin-bottom: 10px;
        }
        .top-students {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 0;
        }
        .top-students .card-body {
            padding: 0;
        }
        .top-student-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .top-student-item:last-child {
            border-bottom: none;
        }
        .student-rank {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        .subject-performance {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 0;
        }
        .subject-performance .card-body {
            padding: 0;
        }
        .subject-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .subject-item:last-child {
            border-bottom: none;
        }
        .subject-stats {
            text-align: right;
        }
        .performance-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .performance-excellent {
            background: #28a745;
            color: white;
        }
        .performance-good {
            background: #17a2b8;
            color: white;
        }
        .performance-average {
            background: #ffc107;
            color: #212529;
        }
        .performance-poor {
            background: #dc3545;
            color: white;
        }
        .empty-stats {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            opacity: 0.7;
        }
        .empty-stats .card-body {
            text-align: center;
            padding: 40px 20px;
        }
        .empty-stats h3 {
            color: #6c757d;
            margin-bottom: 10px;
        }
        .empty-stats .stats-description {
            color: #6c757d;
            font-style: italic;
        }
        .stats-label {
            color: #6c757d !important;
        }
        .stats-title {
            color: #6c757d !important;
        }
        .stats-description {
            color: #6c757d !important;
        }
    </style>
</head>
<body>
    <!-- Professional Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-graduation-cap"></i> Student Record System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <?php if (canAccessStudentRecords()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/students.php">
                            <i class="fas fa-users"></i> Students
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (canAccessTeacherDashboard()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/subjects.php">
                            <i class="fas fa-book"></i> Subjects
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/teachers.php">
                            <i class="fas fa-chalkboard-teacher"></i> Teachers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/marks.php">
                            <i class="fas fa-edit"></i> Marks
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/report.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <?php if (canOnlyViewOwnRecords()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo $_SESSION['display_name']; ?>
                            <i class="fas fa-caret-down"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <div class="container mt-4">
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-welcome">
                        <h2>
                            <i class="fas fa-tachometer-alt"></i> 
                            Welcome back, <?php echo $_SESSION['display_name']; ?>!
                        </h2>
                        <p>
                            <?php 
                            $greeting = "Good " . (date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening'));
                            echo $greeting . "! Here's your academic overview.";
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stats-card <?php echo $totalStudents == 0 ? 'empty-stats' : 'students-card'; ?>">
                        <div class="card-body">
                            <span class="stats-label">Students</span>
                            <h3><?php echo $totalStudents; ?></h3>
                            <p class="stats-title">Total Students</p>
                            <p class="stats-description">
                                <?php if ($totalStudents == 0): ?>
                                    <i class="fas fa-exclamation-triangle"></i> No students registered
                                <?php else: ?>
                                    <i class="fas fa-users"></i> Registered learners
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card <?php echo $totalTeachers == 0 ? 'empty-stats' : 'teachers-card'; ?>">
                        <div class="card-body">
                            <span class="stats-label">Teachers</span>
                            <h3><?php echo $totalTeachers; ?></h3>
                            <p class="stats-title">Total Teachers</p>
                            <p class="stats-description">
                                <?php if ($totalTeachers == 0): ?>
                                    <i class="fas fa-exclamation-triangle"></i> No teachers added
                                <?php else: ?>
                                    <i class="fas fa-chalkboard-teacher"></i> Faculty members
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card <?php echo $totalSubjects == 0 ? 'empty-stats' : 'subjects-card'; ?>">
                        <div class="card-body">
                            <span class="stats-label">Subjects</span>
                            <h3><?php echo $totalSubjects; ?></h3>
                            <p class="stats-title">Total Subjects</p>
                            <p class="stats-description">
                                <?php if ($totalSubjects == 0): ?>
                                    <i class="fas fa-exclamation-triangle"></i> No subjects created
                                <?php else: ?>
                                    <i class="fas fa-book"></i> Available courses
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card <?php echo $totalMarks == 0 ? 'empty-stats' : 'marks-card'; ?>">
                        <div class="card-body">
                            <span class="stats-label">Marks</span>
                            <h3><?php echo $totalMarks; ?></h3>
                            <p class="stats-title">Total Marks</p>
                            <p class="stats-description">
                                <?php if ($totalMarks == 0): ?>
                                    <i class="fas fa-exclamation-triangle"></i> No marks recorded
                                <?php else: ?>
                                    <i class="fas fa-edit"></i> Recorded assessments
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content Grid -->
            <div class="row">
                <!-- Recent Activity -->
                <div class="col-md-6 mb-4">
                    <div class="card activity-card">
                        <div class="activity-header">
                            <i class="fas fa-clock"></i> Recent Activity
                        </div>
                        <div class="card-body recent-activity">
                            <?php if ($recentMarks && $recentMarks->num_rows > 0): ?>
                                <div class="list-group">
                                    <?php while ($mark = $recentMarks->fetch_assoc()): ?>
                                        <div class="list-group-item">
                                            <div>
                                                <strong><?php echo $mark['student_name']; ?></strong>
                                                <span class="badge bg-primary ms-2"><?php echo $mark['subject_name']; ?></span>
                                                <span class="badge bg-success ms-2"><?php echo $mark['score']; ?>%</span>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($mark['created_at'])); ?>
                                            </small>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted p-4">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p class="mb-0">No recent activity found.</p>
                                    <small>Start adding students and marks to see activity here.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- Top Students -->
                <div class="col-md-6 mb-4">
                    <div class="card top-students">
                        <div class="activity-header">
                            <i class="fas fa-trophy"></i> Top Performers
                        </div>
                        <div class="card-body">
                            <?php if ($topStudents && $topStudents->num_rows > 0): ?>
                                <?php while ($student = $topStudents->fetch_assoc()): ?>
                                    <div class="top-student-item">
                                        <div>
                                            <div class="student-rank"><?php echo array_search($student['name'], array_column($topStudents->fetch_all(), 'name')) + 1; ?></div>
                                            <div>
                                                <strong><?php echo $student['name']; ?></strong>
                                                <div class="subject-stats">
                                                    <small>Avg: <?php echo round($student['avg_score'], 1); ?>%</small><br>
                                                    <small>Subjects: <?php echo $student['subject_count']; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-muted p-4">
                                    <i class="fas fa-trophy fa-3x mb-3"></i>
                                    <p class="mb-0">No student performance data available.</p>
                                    <small>Add students and marks to see top performers here.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Subject Performance -->
                <div class="col-12 mb-4">
                    <div class="card subject-performance">
                        <div class="activity-header">
                            <i class="fas fa-chart-line"></i> Subject Performance
                        </div>
                        <div class="card-body">
                            <?php if ($subjectStats && $subjectStats->num_rows > 0): ?>
                                <?php while ($subject = $subjectStats->fetch_assoc()): ?>
                                    <div class="subject-item">
                                        <div>
                                            <strong><?php echo $subject['subject_name']; ?></strong>
                                            <div class="subject-stats">
                                                <?php 
                                                    $avgScore = round($subject['avg_score'], 1);
                                                    $badgeClass = '';
                                                    $badgeText = '';
                                                    if ($avgScore >= 80) {
                                                        $badgeClass = 'performance-excellent';
                                                        $badgeText = 'Excellent';
                                                    } elseif ($avgScore >= 60) {
                                                        $badgeClass = 'performance-good';
                                                        $badgeText = 'Good';
                                                    } else {
                                                        $badgeClass = 'performance-poor';
                                                        $badgeText = 'Needs Improvement';
                                                    }
                                                    ?>
                                                    <span class="performance-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                                    <small><?php echo $avgScore; ?>% avg</small><br>
                                                    <small><?php echo $subject['student_count']; ?> students</small>
                                                </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-muted p-4">
                                    <i class="fas fa-chart-line fa-3x mb-3"></i>
                                    <p class="mb-0">No subject performance data available.</p>
                                    <small>Add subjects and marks to see performance analytics here.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card quick-actions">
                            <div class="card-header">
                                <i class="fas fa-bolt"></i> Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if (canAccessStudentRecords()): ?>
                                    <div class="col-md-3 mb-2">
                                        <a href="pages/students.php" class="btn btn-primary btn-lg w-100">
                                            <i class="fas fa-user-plus"></i> Add Student
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (canAccessTeacherDashboard()): ?>
                                    <div class="col-md-3 mb-2">
                                        <a href="pages/marks.php" class="btn btn-success btn-lg w-100">
                                            <i class="fas fa-edit"></i> Enter Marks
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="pages/subjects.php" class="btn btn-info btn-lg w-100">
                                            <i class="fas fa-book"></i> Manage Subjects
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="pages/teachers.php" class="btn btn-warning btn-lg w-100">
                                            <i class="fas fa-chalkboard-teacher"></i> Manage Teachers
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-md-3 mb-2">
                                        <a href="pages/report.php" class="btn btn-secondary btn-lg w-100">
                                            <i class="fas fa-chart-bar"></i> Generate Reports
                                        </a>
                                    </div>
                                    <?php if (canOnlyViewOwnRecords()): ?>
                                    <div class="col-md-3 mb-2">
                                        <a href="pages/profile.php" class="btn btn-outline-primary btn-lg w-100">
                                            <i class="fas fa-user"></i> Update Profile
                                        </a>
                                    </div>
                                    <?php endif; ?>
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
                    <p>📧 support@school.edu<br>
                       📞 +1 (555) 123-4567<br>
                       📍 123 Education Street</p>
                </div>
                <div class="footer-section">
                    <h4>System Info</h4>
                    <p>Version 2.0<br>
                       Last Updated: <?php echo date('Y-m-d'); ?><br>
                       Powered by PHP & MySQL</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Student Record Management System. All rights reserved. | Designed with ❤️ for Education</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
