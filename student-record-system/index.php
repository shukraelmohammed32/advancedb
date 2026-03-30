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
$totalMarks = (int)$conn->query("SELECT COUNT(*) as count FROM marks")->fetch_assoc()['count'];

$passingScore = (int)AppConfig::getPassingScore();
$dashboard_student_scope_id = canOnlyViewOwnRecords() ? (int)($_SESSION['student_id'] ?? 0) : 0;

$gradeDistribution = [];
$gq = $conn->query("SELECT grade, COUNT(*) AS cnt FROM students GROUP BY grade ORDER BY grade ASC");
if ($gq) {
    while ($row = $gq->fetch_assoc()) {
        $gradeDistribution[] = $row;
    }
}

$marksTrend = [];
$trendSql = "SELECT DATE(COALESCE(m.updated_at, m.created_at)) AS d, COUNT(*) AS cnt
             FROM marks m
             WHERE COALESCE(m.updated_at, m.created_at) >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)";
if ($dashboard_student_scope_id > 0) {
    $trendSql .= " AND m.student_id = " . $dashboard_student_scope_id;
}
$trendSql .= ' GROUP BY d ORDER BY d ASC';
$tq = $conn->query($trendSql);
if ($tq) {
    while ($row = $tq->fetch_assoc()) {
        $marksTrend[] = $row;
    }
}

$recentActivity = [];
$activitySql = 'SELECT m.mark_id, s.name AS student_name, sub.subject_name, m.score,
                COALESCE(m.updated_at, m.created_at) AS act_at
                FROM marks m
                INNER JOIN students s ON s.student_id = m.student_id
                INNER JOIN subjects sub ON sub.subject_id = m.subject_id';
if ($dashboard_student_scope_id > 0) {
    $activitySql .= ' WHERE m.student_id = ' . $dashboard_student_scope_id;
}
$activitySql .= ' ORDER BY act_at DESC LIMIT 10';
$aq = $conn->query($activitySql);
if ($aq) {
    while ($row = $aq->fetch_assoc()) {
        $recentActivity[] = $row;
    }
}

$markCoveragePct = ($totalStudents > 0 && $totalSubjects > 0)
    ? min(100, round(($totalMarks / max(1, $totalStudents * $totalSubjects)) * 100, 1))
    : 0;

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
<html lang="<?php echo htmlspecialchars(currentLanguageTag(), ENT_QUOTES, 'UTF-8'); ?>" data-dashboard-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('Student Record System') . ' - ' . t('Dashboard'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <link href="assets/dashboard-app.css" rel="stylesheet">
</head>
<body class="dashboard-body">
    <aside class="app-sidebar" id="appSidebar" aria-label="<?php echo htmlspecialchars(t('Main navigation'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="app-sidebar__brand">
            <a href="index.php" class="app-sidebar__logo"><span class="app-sidebar__logo-mark">SR</span><span class="app-sidebar__logo-text"><?php echo htmlspecialchars(t('Record System'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <button type="button" class="app-sidebar__collapse btn-icon" id="sidebarCollapse" title="<?php echo htmlspecialchars(t('Collapse menu'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('Collapse menu'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>
        </div>
        <nav class="app-sidebar__nav">
            <a class="app-sidebar__link is-active" href="index.php"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Dashboard'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php if (canViewStudentDirectory()): ?>
            <a class="app-sidebar__link" href="pages/students.php"><i class="bi bi-people-fill" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canViewSubjects()): ?>
            <a class="app-sidebar__link" href="pages/subjects.php"><i class="bi bi-book-half" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canManageTeachers()): ?>
            <a class="app-sidebar__link" href="pages/teachers.php"><i class="bi bi-person-badge" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canEnterMarks()): ?>
            <a class="app-sidebar__link" href="pages/marks.php"><i class="bi bi-pencil-square" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canAccessSummary()): ?>
            <a class="app-sidebar__link" href="pages/summary.php"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Summary'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canViewStudentReports()): ?>
            <a class="app-sidebar__link" href="pages/report.php"><i class="bi bi-file-earmark-text" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Reports'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canOnlyViewOwnRecords()): ?>
            <a class="app-sidebar__link" href="pages/profile.php"><i class="bi bi-person-circle" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Profile'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="app-sidebar-backdrop" id="sidebarBackdrop" hidden></div>

    <div class="app-main">
        <header class="app-topbar">
            <button type="button" class="btn-icon app-topbar__menu d-lg-none" id="sidebarOpen" aria-label="<?php echo htmlspecialchars(t('Open menu'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-list"></i></button>
            <?php if (canViewStudentDirectory()): ?>
            <form class="app-topbar__search" action="pages/students.php" method="get" role="search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" name="q" class="form-control" placeholder="<?php echo htmlspecialchars(t('Search students…'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" aria-label="<?php echo htmlspecialchars(t('Search'), ENT_QUOTES, 'UTF-8'); ?>">
            </form>
            <?php else: ?>
            <div class="app-topbar__search app-topbar__search--muted d-flex align-items-center px-3 flex-grow-1">
                <i class="bi bi-mortarboard" aria-hidden="true"></i>
                <span class="small text-muted"><?php echo htmlspecialchars(t('Student Record System'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <div class="app-topbar__actions">
                <button type="button" class="btn-icon" id="themeToggle" title="<?php echo htmlspecialchars(t('Toggle theme'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('Toggle dark mode'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <div class="dropdown app-topbar__user">
                    <button class="btn app-topbar__user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="app-topbar__avatar"><?php echo htmlspecialchars(strtoupper(substr((string)($_SESSION['display_name'] ?? 'User'), 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="app-topbar__user-text d-none d-sm-flex">
                            <span class="app-topbar__user-name"><?php echo htmlspecialchars((string)($_SESSION['display_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="app-topbar__user-role"><?php echo htmlspecialchars(getRoleLabel(), ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                        <i class="bi bi-chevron-down small ms-1 opacity-50"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end app-topbar__dropdown shadow border-0">
                        <li><a class="dropdown-item" href="auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?php echo htmlspecialchars(t('Logout'), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content">
            <div class="app-content__head">
                <div>
                    <h1 class="app-content__title"><?php echo htmlspecialchars(t('Dashboard'), ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="app-content__subtitle"><?php echo htmlspecialchars(t('Student Record System'), ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars(getRoleLabel(), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-card">
                        <div class="dash-card__head">
                            <span class="dash-card__label"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="dash-card__icon"><i class="bi bi-people"></i></span>
                        </div>
                        <div class="dash-card__value"><?php echo (int)$totalStudents; ?></div>
                        <div class="dash-card__foot">
                            <span class="dash-card__hint"><?php echo htmlspecialchars(t('Total Students'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!$dashboard_student_scope_id && count($gradeDistribution) > 0): ?>
                            <span class="dash-card__trend dash-card__trend--up" title="<?php echo htmlspecialchars(t('Grade levels'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int)count($gradeDistribution); ?> <?php echo htmlspecialchars(t('grades'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php else: ?>
                            <span class="dash-card__trend dash-card__trend--neutral"><?php echo htmlspecialchars($dashboard_student_scope_id ? t('Your portal') : t('Live'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-card">
                        <div class="dash-card__head">
                            <span class="dash-card__label"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="dash-card__icon"><i class="bi bi-person-workspace"></i></span>
                        </div>
                        <div class="dash-card__value"><?php echo (int)$totalTeachers; ?></div>
                        <div class="dash-card__foot">
                            <span class="dash-card__hint"><?php echo htmlspecialchars(t('Total Teachers'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="dash-card__trend dash-card__trend--neutral"><?php echo htmlspecialchars(t('Faculty'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-card">
                        <div class="dash-card__head">
                            <span class="dash-card__label"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="dash-card__icon"><i class="bi bi-journal-bookmark"></i></span>
                        </div>
                        <div class="dash-card__value"><?php echo (int)$totalSubjects; ?></div>
                        <div class="dash-card__foot">
                            <span class="dash-card__hint"><?php echo htmlspecialchars(t('Total Subjects'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="dash-card__trend dash-card__trend--neutral"><?php echo htmlspecialchars(t('Catalog'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-card">
                        <div class="dash-card__head">
                            <span class="dash-card__label"><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="dash-card__icon"><i class="bi bi-clipboard-data"></i></span>
                        </div>
                        <div class="dash-card__value"><?php echo (int)$totalMarks; ?></div>
                        <div class="dash-card__foot">
                            <span class="dash-card__hint"><?php echo htmlspecialchars(t('Recorded assessments'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!$dashboard_student_scope_id && $markCoveragePct >= 0): ?>
                            <span class="dash-card__trend <?php echo $markCoveragePct >= 50 ? 'dash-card__trend--up' : 'dash-card__trend--down'; ?>" title="<?php echo htmlspecialchars(t('Mark coverage vs roster × subjects'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($markCoveragePct, ENT_QUOTES, 'UTF-8'); ?>% <?php echo htmlspecialchars(t('coverage'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php else: ?>
                            <span class="dash-card__trend dash-card__trend--neutral"><?php echo htmlspecialchars(t('Entries'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="dash-panel">
                        <div class="dash-panel__head">
                            <h2 class="dash-panel__title"><?php echo htmlspecialchars($dashboard_student_scope_id ? t('Your recent mark activity') : t('Marks activity (14 days)'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <div class="dash-panel__body dash-chart-wrap">
                            <canvas id="chartLineMarks" height="120" aria-label="<?php echo htmlspecialchars(t('Line chart'), ENT_QUOTES, 'UTF-8'); ?>"></canvas>
                        </div>
                    </div>
                </div>
                <?php if (!$dashboard_student_scope_id): ?>
                <div class="col-lg-4">
                    <div class="dash-panel h-100">
                        <div class="dash-panel__head">
                            <h2 class="dash-panel__title"><?php echo htmlspecialchars(t('Students by grade'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <div class="dash-panel__body dash-chart-wrap dash-chart-wrap--pie">
                            <canvas id="chartPieGrades" aria-label="<?php echo htmlspecialchars(t('Pie chart'), ENT_QUOTES, 'UTF-8'); ?>"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$dashboard_student_scope_id): ?>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="dash-panel">
                        <div class="dash-panel__head">
                            <h2 class="dash-panel__title"><?php echo htmlspecialchars(t('Students per grade'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <div class="dash-panel__body dash-chart-wrap">
                            <canvas id="chartBarGrades" height="100" aria-label="<?php echo htmlspecialchars(t('Bar chart'), ENT_QUOTES, 'UTF-8'); ?>"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($can_access_distributed && $distributed_ready && !empty($site_stats)): ?>
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="dash-panel">
                        <div class="dash-panel__head">
                            <h2 class="dash-panel__title"><?php echo htmlspecialchars(t('Branch Oversight'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <div class="dash-panel__body p-0">
                            <div class="row g-3 p-3">
                                <?php foreach ($site_stats as $site_stat): ?>
                                <div class="col-md-4">
                                    <a href="pages/branch_details.php?site_id=<?php echo (int)$site_stat['site_id']; ?>" class="dash-branch-card text-decoration-none">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <strong class="text-body"><?php echo htmlspecialchars($site_stat['site_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="badge rounded-pill <?php echo ($site_stat['connection_status'] ?? '') === 'online' ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                                <?php echo htmlspecialchars(($site_stat['connection_status'] ?? '') === 'online' ? t('Online') : t('Unavailable'), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                        <div class="small text-muted mb-2"><?php echo htmlspecialchars($site_stat['db_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="row g-2 small text-body">
                                            <div class="col-6"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo htmlspecialchars(formatDashboardMetric($site_stat['total_teachers'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            <div class="col-6"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo htmlspecialchars(formatDashboardMetric($site_stat['total_subjects'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            <div class="col-6"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo htmlspecialchars(formatDashboardMetric($site_stat['total_students'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            <div class="col-6"><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo htmlspecialchars(formatDashboardMetric($site_stat['total_marks'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                        </div>
                                        <div class="small text-muted mt-2"><?php echo htmlspecialchars(t('Latest activity:'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars(formatDashboardDateTime($site_stat['last_activity_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if (!empty($site_stat['is_default'])): ?>
                                        <span class="badge text-bg-primary mt-2"><?php echo htmlspecialchars(t('Default'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-xl-8">
                    <div class="dash-panel">
                        <div class="dash-panel__head">
                            <h2 class="dash-panel__title"><?php echo htmlspecialchars($dashboard_student_scope_id ? t('My recent marks') : t('Recent mark updates'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <div class="dash-panel__body p-0">
                            <div class="table-responsive">
                                <table class="table dash-table mb-0">
                                    <thead>
                                        <tr>
                                            <th><?php echo htmlspecialchars(t('Subject'), ENT_QUOTES, 'UTF-8'); ?></th>
                                            <?php if (!$dashboard_student_scope_id): ?>
                                            <th><?php echo htmlspecialchars(t('Student'), ENT_QUOTES, 'UTF-8'); ?></th>
                                            <?php endif; ?>
                                            <th><?php echo htmlspecialchars(t('Date'), ENT_QUOTES, 'UTF-8'); ?></th>
                                            <th><?php echo htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8'); ?></th>
                                            <th class="text-end"><?php echo htmlspecialchars(t('Score'), ENT_QUOTES, 'UTF-8'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentActivity)): ?>
                                        <tr>
                                            <td colspan="<?php echo $dashboard_student_scope_id ? '4' : '5'; ?>" class="text-muted py-4 text-center"><?php echo htmlspecialchars(t('No recent mark activity yet.'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($recentActivity as $act): ?>
                                        <?php
                                            $score = (int)($act['score'] ?? 0);
                                            $stLabel = $score >= $passingScore ? t('PASS') : t('Below threshold');
                                            $stClass = $score >= $passingScore ? 'dash-pill--ok' : 'dash-pill--bad';
                                            ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string)($act['subject_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <?php if (!$dashboard_student_scope_id): ?>
                                            <td><?php echo htmlspecialchars((string)($act['student_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <?php endif; ?>
                                            <td class="text-muted small"><?php echo htmlspecialchars(formatDashboardDateTime((string)($act['act_at'] ?? ''), '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span class="dash-pill <?php echo htmlspecialchars($stClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($stLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td class="text-end fw-semibold"><?php echo (int)$score; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="app-quick dash-panel h-100">
                        <div class="dash-panel__head">
                            <h2 class="dash-panel__title"><?php echo htmlspecialchars(t('Quick Actions'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <div class="dash-panel__body d-grid gap-2">
                            <?php if (isAdmin()): ?>
                            <a href="pages/students.php" class="btn btn-dash-primary"><?php echo htmlspecialchars(t('Manage Students'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/subjects.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('Manage Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/teachers.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('Manage Teachers'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/summary.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('Review Summary'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/report.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('Generate Reports'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php elseif (isHomeroomTeacher()): ?>
                            <a href="pages/students.php" class="btn btn-dash-primary"><?php echo htmlspecialchars(t('My Class Roster'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/subjects.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('My Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/marks.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('Enter Marks'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/summary.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('Compile Results'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/report.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('Generate Reports'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php elseif (isTeacher()): ?>
                            <a href="pages/students.php" class="btn btn-dash-primary"><?php echo htmlspecialchars(t('Assigned Students'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/subjects.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('My Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/marks.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('Submit Marks'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php elseif (isStudent()): ?>
                            <a href="pages/report.php" class="btn btn-dash-primary"><?php echo htmlspecialchars(t('View My Report'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <a href="pages/profile.php" class="btn btn-dash-secondary"><?php echo htmlspecialchars(t('Update Profile'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <?php
        $footer_base_path = '';
        include __DIR__ . '/includes/footer.php';
        ?>
    </div>

    <?php
    $chart_grade_labels = [];
    $chart_grade_counts = [];
    foreach ($gradeDistribution as $gd) {
        $chart_grade_labels[] = (string)($gd['grade'] ?? '');
        $chart_grade_counts[] = (int)($gd['cnt'] ?? 0);
    }
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        const lineLabels = <?php echo json_encode(array_column($marksTrend, 'd'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const lineData = <?php echo json_encode(array_map('intval', array_column($marksTrend, 'cnt')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const gradeLabels = <?php echo json_encode($chart_grade_labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const gradeCounts = <?php echo json_encode($chart_grade_counts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const accent = getComputedStyle(document.documentElement).getPropertyValue('--dash-accent').trim() || '#4f46e5';
        const accentMuted = getComputedStyle(document.documentElement).getPropertyValue('--dash-accent-soft').trim() || 'rgba(79, 70, 229, 0.12)';
        const tickColor = getComputedStyle(document.documentElement).getPropertyValue('--dash-chart-tick').trim() || '#64748b';
        const gridColor = getComputedStyle(document.documentElement).getPropertyValue('--dash-chart-grid').trim() || 'rgba(100,116,139,0.12)';

        const commonOpts = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: tickColor, maxRotation: 0, autoSkip: true }, grid: { color: gridColor } },
                y: { beginAtZero: true, ticks: { color: tickColor, precision: 0 }, grid: { color: gridColor } }
            }
        };

        const lineCtx = document.getElementById('chartLineMarks');
        if (lineCtx) {
            new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: lineLabels.length ? lineLabels : ['—'],
                    datasets: [{
                        label: '<?php echo addslashes(t('Marks')); ?>',
                        data: lineData.length ? lineData : [0],
                        borderColor: accent,
                        backgroundColor: accentMuted,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ...commonOpts.scales.x, grid: { display: false } },
                        y: commonOpts.scales.y
                    }
                }
            });
        }

        const pieCtx = document.getElementById('chartPieGrades');
        if (pieCtx && gradeLabels.length) {
            const neutrals = ['#94a3b8', '#64748b', '#475569', '#334155', '#0f172a', '#4f46e5', '#6366f1'];
            new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: gradeLabels,
                    datasets: [{
                        data: gradeCounts,
                        backgroundColor: gradeLabels.map((_, i) => neutrals[i % neutrals.length]),
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: tickColor, boxWidth: 10 } }
                    }
                }
            });
        }

        const barCtx = document.getElementById('chartBarGrades');
        if (barCtx && gradeLabels.length) {
            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: gradeLabels,
                    datasets: [{
                        label: '<?php echo addslashes(t('Students')); ?>',
                        data: gradeCounts,
                        backgroundColor: accentMuted,
                        borderColor: accent,
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: { ...commonOpts, plugins: { legend: { display: false } } }
            });
        }
    })();

    (function () {
        const root = document.documentElement;
        const key = 'srs-dashboard-theme';
        const saved = localStorage.getItem(key);
        if (saved === 'dark') root.setAttribute('data-dashboard-theme', 'dark');

        document.getElementById('themeToggle')?.addEventListener('click', function () {
            const next = root.getAttribute('data-dashboard-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-dashboard-theme', next);
            localStorage.setItem(key, next);
            const icon = this.querySelector('i');
            if (icon) icon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        });
        const tbtn = document.getElementById('themeToggle');
        if (tbtn && root.getAttribute('data-dashboard-theme') === 'dark') {
            const icon = tbtn.querySelector('i');
            if (icon) icon.className = 'bi bi-sun';
        }

        const sidebar = document.getElementById('appSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const collapseBtn = document.getElementById('sidebarCollapse');
        const openBtn = document.getElementById('sidebarOpen');

        function closeMobile() {
            sidebar?.classList.remove('is-open');
            backdrop?.setAttribute('hidden', '');
        }
        openBtn?.addEventListener('click', function () {
            sidebar?.classList.add('is-open');
            backdrop?.removeAttribute('hidden');
        });
        backdrop?.addEventListener('click', closeMobile);
        window.addEventListener('resize', function () { if (window.innerWidth >= 992) closeMobile(); });

        const collapsedKey = 'srs-sidebar-collapsed';
        if (localStorage.getItem(collapsedKey) === '1' && sidebar) sidebar.classList.add('is-collapsed');
        collapseBtn?.addEventListener('click', function () {
            sidebar?.classList.toggle('is-collapsed');
            localStorage.setItem(collapsedKey, sidebar?.classList.contains('is-collapsed') ? '1' : '0');
        });
    })();
    </script>
</body>
</html>
