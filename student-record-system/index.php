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
        public function getRecentBranchActivity($limit = 12) { return []; }
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

function formatDashboardMetric($value) {
    return $value === null ? 'N/A' : (string)(int)$value;
}

function formatDashboardDateTime($value, $fallback = 'No activity yet') {
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y h:i A', $timestamp) : $value;
}

?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLanguageTag(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('Student Record System') . ' - ' . t('Dashboard'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><?php echo htmlspecialchars(t('Student Record System'), ENT_QUOTES, 'UTF-8'); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php"><?php echo htmlspecialchars(t('Dashboard'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php if (canViewStudentDirectory()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/students.php"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canViewSubjects()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/subjects.php"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canManageTeachers()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/teachers.php"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canEnterMarks()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/marks.php"><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canAccessSummary()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/summary.php"><?php echo htmlspecialchars(t('Summary'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canViewStudentReports()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/report.php"><?php echo htmlspecialchars(t('Reports'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canOnlyViewOwnRecords()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pages/profile.php"><?php echo htmlspecialchars(t('Profile'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item d-flex align-items-center ms-lg-3 me-lg-2">
                        <?php echo renderLanguageSwitcher(''); ?>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars((string)($_SESSION['display_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars(getRoleLabel(), ENT_QUOTES, 'UTF-8'); ?>)
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="auth/logout.php"><?php echo htmlspecialchars(t('Logout'), ENT_QUOTES, 'UTF-8'); ?></a></li>
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
                    <h1 class="dashboard-title"><?php echo htmlspecialchars(t('Dashboard'), ENT_QUOTES, 'UTF-8'); ?></h1>
                </div>
            </div>


            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card stats-card students-card">
                        <div class="card-body">
                            <span class="stats-label"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <h3><?php echo $totalStudents; ?></h3>
                            <p class="stats-title"><?php echo htmlspecialchars(t('Total Students'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="stats-description"><?php echo htmlspecialchars(t('All registered learners currently stored in the system.'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card teachers-card">
                        <div class="card-body">
                            <span class="stats-label"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <h3><?php echo $totalTeachers; ?></h3>
                            <p class="stats-title"><?php echo htmlspecialchars(t('Total Teachers'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="stats-description"><?php echo htmlspecialchars(t('Faculty members handling subjects, classes, and compiled results.'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card subjects-card">
                        <div class="card-body">
                            <span class="stats-label"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <h3><?php echo $totalSubjects; ?></h3>
                            <p class="stats-title"><?php echo htmlspecialchars(t('Total Subjects'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="stats-description"><?php echo htmlspecialchars(t('Courses used for marks, rankings, and final academic reports.'), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($can_access_distributed && $distributed_ready && !empty($site_stats)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><?php echo htmlspecialchars(t('Branch Oversight'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($site_stats as $site_stat): ?>
                                <div class="col-md-4 mb-3">
                                    <a href="pages/branch_details.php?site_id=<?php echo (int)$site_stat['site_id']; ?>" class="text-decoration-none text-reset d-block h-100">
                                        <div class="border rounded p-3 h-100">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($site_stat['site_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($site_stat['db_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge <?php echo ($site_stat['connection_status'] ?? '') === 'online' ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo htmlspecialchars(($site_stat['connection_status'] ?? '') === 'online' ? t('Online') : t('Unavailable'), ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                    <?php if (!empty($site_stat['is_default'])): ?>
                                                    <div class="mt-2"><span class="badge bg-primary"><?php echo htmlspecialchars(t('Default'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="row g-2 small">
                                                <div class="col-6"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo htmlspecialchars(formatDashboardMetric($site_stat['total_teachers'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                                <div class="col-6"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo htmlspecialchars(formatDashboardMetric($site_stat['total_subjects'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                                <div class="col-6"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo htmlspecialchars(formatDashboardMetric($site_stat['total_students'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                                <div class="col-6"><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo htmlspecialchars(formatDashboardMetric($site_stat['total_marks'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            </div>
                                            <div class="small text-muted mt-3"><?php echo htmlspecialchars(t('Latest activity:'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars(formatDashboardDateTime($site_stat['last_activity_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="small text-primary mt-3"><?php echo htmlspecialchars(t('Open campus details'), ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    </a>
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
                        <div class="card-header"><?php echo htmlspecialchars(t('Quick Actions'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (isAdmin()): ?>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/students.php" class="btn btn-primary btn-lg w-100"><?php echo htmlspecialchars(t('Manage Students'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/subjects.php" class="btn btn-secondary btn-lg w-100"><?php echo htmlspecialchars(t('Manage Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/teachers.php" class="btn btn-info btn-lg w-100"><?php echo htmlspecialchars(t('Manage Teachers'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/summary.php" class="btn btn-dark btn-lg w-100"><?php echo htmlspecialchars(t('Review Summary'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/report.php" class="btn btn-warning btn-lg w-100"><?php echo htmlspecialchars(t('Generate Reports'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <?php elseif (isHomeroomTeacher()): ?>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/students.php" class="btn btn-primary btn-lg w-100"><?php echo htmlspecialchars(t('My Class Roster'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/subjects.php" class="btn btn-secondary btn-lg w-100"><?php echo htmlspecialchars(t('My Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/marks.php" class="btn btn-success btn-lg w-100"><?php echo htmlspecialchars(t('Enter Marks'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/summary.php" class="btn btn-dark btn-lg w-100"><?php echo htmlspecialchars(t('Compile Results'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/report.php" class="btn btn-warning btn-lg w-100"><?php echo htmlspecialchars(t('Generate Reports'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <?php elseif (isTeacher()): ?>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/students.php" class="btn btn-primary btn-lg w-100"><?php echo htmlspecialchars(t('Assigned Students'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/subjects.php" class="btn btn-secondary btn-lg w-100"><?php echo htmlspecialchars(t('My Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/marks.php" class="btn btn-success btn-lg w-100"><?php echo htmlspecialchars(t('Submit Marks'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <?php elseif (isStudent()): ?>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/report.php" class="btn btn-warning btn-lg w-100"><?php echo htmlspecialchars(t('View My Report'), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <a href="pages/profile.php" class="btn btn-primary btn-lg w-100"><?php echo htmlspecialchars(t('Update Profile'), ENT_QUOTES, 'UTF-8'); ?></a>
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
