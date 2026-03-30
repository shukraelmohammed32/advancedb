<?php
require_once '../config/database.php';
require_once '../config/app_config.php';
require_once '../auth/auth_helper.php';
require_once '../includes/distributed_coordinator.php';

requireLogin();
if (!canAccessSummary()) {
    $_SESSION['error'] = 'Only admin and homeroom teachers can review compiled academic results.';
    header('Location: ../index.php');
    exit();
}

function normalizeGradeLabel($grade) {
    $grade = trim((string)$grade);
    if ($grade === '') {
        return '';
    }

    if (preg_match('/^\d+$/', $grade)) {
        return 'Grade ' . $grade;
    }

    if (preg_match('/^grade\s*(\d+)$/i', $grade, $matches)) {
        return 'Grade ' . $matches[1];
    }

    return $grade;
}

function formatSummaryLabel($value) {
    $value = str_replace('_', ' ', trim((string)$value));
    if ($value === '') {
        return '';
    }

    return ucwords(strtolower($value));
}

function getStatusPillClass($status) {
    $status = strtoupper(trim((string)$status));

    switch ($status) {
        case 'PASS':
        case 'EXCELLENT':
            return 'status-pill status-pass';
        case 'GOOD':
            return 'status-pill status-good';
        case 'FAIR':
            return 'status-pill status-fair';
        case 'INCOMPLETE':
            return 'status-pill status-incomplete';
        case 'NO MARKS':
        case 'NO SUBJECTS':
        case 'NO DATA':
            return 'status-pill status-muted';
        default:
            return 'status-pill status-fail';
    }
}

function buildStudentSummaryRecord($student, $defaultTotalSubjects) {
    $recordedSubjects = (int)($student['recorded_subjects'] ?? 0);
    $targetSubjects = (int)($student['total_subjects'] ?? $defaultTotalSubjects);
    $passedSubjects = (int)($student['passed_subjects'] ?? 0);
    $averageScore = array_key_exists('average_score', $student) && $student['average_score'] !== null
        ? (float)$student['average_score']
        : null;

    if ($targetSubjects <= 0) {
        $averageScore = null;
        $overallStatus = 'NO SUBJECTS';
    } elseif ($recordedSubjects <= 0) {
        $averageScore = null;
        $overallStatus = 'NO MARKS';
    } elseif ($recordedSubjects < $targetSubjects) {
        $averageScore = null;
        $overallStatus = 'INCOMPLETE';
    } else {
        $averageScore = $averageScore !== null ? round($averageScore, 1) : 0.0;
        // PASS only when every subject has a mark and each mark meets the passing threshold.
        $overallStatus = ($passedSubjects === $targetSubjects) ? 'PASS' : 'FAIL';
    }

    $student['recorded_subjects'] = $recordedSubjects;
    $student['total_subjects'] = $targetSubjects;
    $student['passed_subjects'] = $passedSubjects;
    $student['average_score'] = $averageScore;
    $student['overall_status'] = $overallStatus;

    return $student;
}

$db = new Database();
$conn = $db->getConnection();
$coordinator = new DistributedCoordinator($db);
$is_admin = hasRole('admin');
$is_super_admin = $is_admin && (string)($_SESSION['admin_scope'] ?? 'main') === 'main';
$distributed_ready = $coordinator->isDistributedReady();
$can_access_distributed = canAccessDistributedCoordinator();
$site_stats = ($is_super_admin && $can_access_distributed && $distributed_ready) ? $coordinator->getSiteStats() : [];

$totalStudents = (int)$conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$totalTeachers = (int)$conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$totalSubjects = (int)$conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
$totalMarks = (int)$conn->query("SELECT COUNT(*) as count FROM marks")->fetch_assoc()['count'];
$passingScoreForSummary = (int)AppConfig::getPassingScore();

$summary_title = isHomeroomTeacher()
    ? 'Compile final results for your homeroom without jumping between multiple pages.'
    : ($is_super_admin
        ? 'Monitor completion, performance, and branch health across the entire platform.'
        : 'Review compiled results, completion, and subject health across the whole school.');
$summary_intro = isHomeroomTeacher()
    ? 'This page helps homeroom teachers collect subject marks, monitor completion, and move directly into final student reports.'
    : ($is_super_admin
        ? 'This page gives Super Admin a single place to review school-wide completion, subject trends, and branch-level summary health before moving into reports or branch details.'
        : 'This page gives administrators a single place to review completion, averages, pass rate, and subject trends before opening reports.');

// Homeroom teachers must only see students in their assigned grade
$summaryGradeFilter = '';
if (isHomeroomTeacher() && !empty($_SESSION['assigned_grade'])) {
    $summaryGrade = $conn->real_escape_string((string)$_SESSION['assigned_grade']);
    $summaryGradeFilter = "WHERE s.grade = '$summaryGrade'";
}

$studentSummaryResult = $conn->query("
    SELECT
        s.student_id,
        s.name,
        s.grade,
        COUNT(m.mark_id) AS recorded_subjects,
        {$totalSubjects} AS total_subjects,
        SUM(CASE WHEN m.score >= {$passingScoreForSummary} THEN 1 ELSE 0 END) AS passed_subjects,
        COALESCE(ROUND(AVG(m.score), 1), 0) AS average_score
    FROM students s
    LEFT JOIN marks m ON s.student_id = m.student_id
    {$summaryGradeFilter}
    GROUP BY s.student_id, s.name, s.grade
");

$studentSummaryRows = [];
if ($studentSummaryResult) {
    while ($student = $studentSummaryResult->fetch_assoc()) {
        $studentSummaryRows[] = buildStudentSummaryRecord($student, $totalSubjects);
    }

    usort($studentSummaryRows, function ($left, $right) {
        $leftPending = $left['average_score'] === null ? 1 : 0;
        $rightPending = $right['average_score'] === null ? 1 : 0;

        if ($leftPending !== $rightPending) {
            return $leftPending <=> $rightPending;
        }

        if ($leftPending === 0) {
            $scoreComparison = (float)$right['average_score'] <=> (float)$left['average_score'];
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }
        }

        return strcasecmp((string)$left['name'], (string)$right['name']);
    });
}


$subjectPerformance = $conn->query("
    SELECT
        subject_id,
        subject_name,
        total_students AS total_marks,
        average_score,
        highest_score,
        lowest_score,
        passed_count,
        COALESCE(failed_count, GREATEST(total_students - passed_count, 0)) AS failed_count,
        COALESCE(pass_rate, 0) AS pass_rate,
        COALESCE(
            performance_band,
            CASE
                WHEN total_students = 0 THEN 'NO DATA'
                WHEN average_score >= 80 THEN 'EXCELLENT'
                WHEN average_score >= 60 THEN 'GOOD'
                WHEN average_score >= 50 THEN 'FAIR'
                ELSE 'NEEDS IMPROVEMENT'
            END
        ) AS performance_band
    FROM subject_performance
    ORDER BY average_score DESC, subject_name ASC
");

if ($subjectPerformance === false) {
    $subjectPerformance = $conn->query("
        SELECT
            sub.subject_id,
            sub.subject_name,
            COUNT(m.mark_id) AS total_marks,
            COALESCE(ROUND(AVG(m.score), 1), 0) AS average_score,
            COALESCE(MAX(m.score), 0) AS highest_score,
            COALESCE(MIN(m.score), 0) AS lowest_score,
            SUM(CASE WHEN m.score >= {$passingScoreForSummary} THEN 1 ELSE 0 END) AS passed_count,
            SUM(CASE WHEN m.score < {$passingScoreForSummary} THEN 1 ELSE 0 END) AS failed_count,
            COALESCE(
                ROUND((SUM(CASE WHEN m.score >= {$passingScoreForSummary} THEN 1 ELSE 0 END) / NULLIF(COUNT(m.mark_id), 0)) * 100, 1),
                0
            ) AS pass_rate,
            CASE
                WHEN COUNT(m.mark_id) = 0 THEN 'NO DATA'
                WHEN AVG(m.score) >= 80 THEN 'EXCELLENT'
                WHEN AVG(m.score) >= 60 THEN 'GOOD'
                WHEN AVG(m.score) >= {$passingScoreForSummary} THEN 'FAIR'
                ELSE 'NEEDS IMPROVEMENT'
            END AS performance_band
        FROM subjects sub
        LEFT JOIN marks m ON sub.subject_id = m.subject_id
        GROUP BY sub.subject_id, sub.subject_name
        ORDER BY average_score DESC, sub.subject_name ASC
    ");
}
?>

<?php
$GLOBALS['dashboard_from_pages'] = true;
$GLOBALS['dashboard_nav_active'] = 'summary';
$GLOBALS['dashboard_page_title'] = t('Performance Summary') . ' — ' . t('Student Record System');
$GLOBALS['dashboard_heading'] = '';
$GLOBALS['dashboard_subtitle'] = '';
$GLOBALS['dashboard_body_class'] = 'dashboard-body summary-page';
$GLOBALS['dashboard_extra_head'] = '<link rel="stylesheet" href="../assets/summary-page.css">';
include __DIR__ . '/../includes/dashboard_shell_start.php';
?>

<div class="main-container">
        <div class="container mt-4 summary-shell">
            <section class="summary-hero">
                <div>
                    <span class="summary-kicker"><?php echo htmlspecialchars(t('Academic Summary'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <h1 class="summary-title"><?php echo htmlspecialchars($summary_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="summary-intro"><?php echo htmlspecialchars($summary_intro, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (canEnterMarks() || canViewStudentReports()): ?>
                    <div class="summary-actions">
                        <?php if (canEnterMarks()): ?>
                        <a href="marks.php" class="summary-action summary-action-primary"><?php echo htmlspecialchars(t('Open Mark Entry'), ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php endif; ?>
                        <?php if (canViewStudentReports()): ?>
                        <a href="report.php" class="summary-action summary-action-secondary"><?php echo htmlspecialchars(t('Open Reports'), ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php endif; ?>
                        <?php if ($is_super_admin && !empty($site_stats)): ?>
                        <a href="../index.php" class="summary-action summary-action-secondary"><?php echo htmlspecialchars(t('Open Branch Oversight'), ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="summary-stats">
                    <div class="summary-stat-card summary-stat-card-emerald">
                        <span><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <strong><?php echo $totalStudents; ?></strong>
                        <p><?php echo htmlspecialchars(t('Learners currently tracked in the system.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="summary-stat-card summary-stat-card-clay">
                        <span><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <strong><?php echo $totalTeachers; ?></strong>
                        <p><?php echo htmlspecialchars(t('Faculty records linked to grades, subjects, and reports.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="summary-stat-card summary-stat-card-sand">
                        <span><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <strong><?php echo $totalSubjects; ?></strong>
                        <p><?php echo htmlspecialchars(t('Courses contributing to progress and reports.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="summary-stat-card summary-stat-card-ink">
                        <span><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <strong><?php echo $totalMarks; ?></strong>
                        <p><?php echo htmlspecialchars(t('Recorded assessments available for analysis.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="summary-stat-card summary-stat-card-clay">
                        <span><?php echo htmlspecialchars(t('Focus'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <strong><?php echo $totalSubjects > 0 ? number_format(($totalMarks / max($totalSubjects, 1)), 1) : '0.0'; ?></strong>
                        <p><?php echo htmlspecialchars(t('Average recorded marks per subject slot.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </section>

            <?php if ($is_super_admin && !empty($site_stats)): ?>
            <section class="summary-panel mt-4">
                <div class="summary-panel-header">
                    <div>
                        <span class="summary-panel-label"><?php echo htmlspecialchars(t('Branch Summary'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <h2 class="summary-panel-title"><?php echo htmlspecialchars(t('All campus summary in one place'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="summary-panel-copy"><?php echo htmlspecialchars(t('Super Admin can review each campus connection, teacher and student totals, and open branch details from here.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <span class="summary-panel-meta"><?php echo count($site_stats) . ' ' . htmlspecialchars(t('campuses'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="summary-panel-body">
                    <div class="summary-site-grid">
                        <?php foreach ($site_stats as $site_stat): ?>
                        <?php $isOnline = ($site_stat['connection_status'] ?? '') === 'online'; ?>
                        <a href="branch_details.php?site_id=<?php echo (int)$site_stat['site_id']; ?>" class="summary-site-card">
                            <div class="summary-site-top">
                                <div>
                                    <h3 class="summary-site-name"><?php echo htmlspecialchars((string)$site_stat['site_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="summary-site-db"><?php echo htmlspecialchars((string)$site_stat['db_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <span class="summary-site-badge<?php echo $isOnline ? '' : ' summary-site-badge-offline'; ?>">
                                    <?php echo htmlspecialchars($isOnline ? t('Online') : t('Offline'), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <div class="summary-site-stats">
                                <div class="summary-site-stat">
                                    <strong><?php echo (int)($site_stat['total_teachers'] ?? 0); ?></strong>
                                    <span><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="summary-site-stat">
                                    <strong><?php echo (int)($site_stat['total_students'] ?? 0); ?></strong>
                                    <span><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="summary-site-stat">
                                    <strong><?php echo (int)($site_stat['total_subjects'] ?? 0); ?></strong>
                                    <span><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="summary-site-stat">
                                    <strong><?php echo (int)($site_stat['total_marks'] ?? 0); ?></strong>
                                    <span><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <div class="summary-site-link"><?php echo htmlspecialchars(t('Open branch details'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <div class="summary-grid">
                <section class="summary-panel">
                    <div class="summary-panel-header">
                        <div>
                            <span class="summary-panel-label">Student Summary</span>
                            <h2 class="summary-panel-title">Who is on track and who still needs marks</h2>
                            <p class="summary-panel-copy">Completed students are sorted by final average, while incomplete records stay pending until every subject has a mark.</p>
                        </div>
                        <span class="summary-panel-meta"><?php echo $totalStudents; ?> students</span>
                    </div>
                    <div class="summary-panel-body">
                        <?php if (!empty($studentSummaryRows)): ?>
                            <?php foreach ($studentSummaryRows as $student): ?>
                                <?php
                                $recordedSubjects = (int)($student['recorded_subjects'] ?? 0);
                                $targetSubjects = (int)($student['total_subjects'] ?? $totalSubjects);
                                $passedSubjects = (int)($student['passed_subjects'] ?? 0);
                                $pendingSubjects = max($targetSubjects - $recordedSubjects, 0);
                                $completionWidth = $targetSubjects > 0 ? max(10, min(100, (int)round(($recordedSubjects / $targetSubjects) * 100))) : 10;
                                $averageScoreValue = $student['average_score'] ?? null;
                                if ($averageScoreValue !== null) {
                                    $averageScore = number_format((float)$averageScoreValue, 1) . '%';
                                    $averageCaption = 'Average score';
                                } elseif (($student['overall_status'] ?? '') === 'INCOMPLETE') {
                                    $averageScore = 'Pending';
                                    $averageCaption = 'Awaiting full marks';
                                } else {
                                    $averageScore = 'N/A';
                                    $averageCaption = 'Average unavailable';
                                }
                                $statusLabel = formatSummaryLabel($student['overall_status'] ?? 'NO DATA');
                                ?>
                                <article class="summary-row">
                                    <div class="summary-row-main">
                                        <div class="summary-row-head">
                                            <h3 class="summary-name"><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <span class="summary-grade"><?php echo htmlspecialchars(normalizeGradeLabel($student['grade']), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <p class="summary-meta">
                                            <?php if ($targetSubjects > 0): ?>
                                                <?php echo $passedSubjects; ?>/<?php echo $targetSubjects; ?> subjects passed
                                                <?php if ($pendingSubjects > 0): ?>
                                                    <span>&bull; <?php echo $pendingSubjects; ?> still waiting for marks</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                No subjects have been configured yet.
                                            <?php endif; ?>
                                        </p>
                                        <div class="summary-chip-row">
                                            <span class="summary-chip"><?php echo $recordedSubjects; ?> recorded</span>
                                            <span class="summary-chip"><?php echo $pendingSubjects; ?> pending</span>
                                        </div>
                                        <div class="summary-progress">
                                            <span style="width: <?php echo $completionWidth; ?>%"></span>
                                        </div>
                                    </div>
                                    <div class="summary-row-side">
                                        <div class="summary-value"><?php echo htmlspecialchars($averageScore, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="summary-caption"><?php echo htmlspecialchars($averageCaption, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <span class="<?php echo getStatusPillClass($student['overall_status'] ?? 'NO DATA'); ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="summary-empty">
                                <p>No student summary data available yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="summary-panel">
                    <div class="summary-panel-header">
                        <div>
                            <span class="summary-panel-label">Subject Performance</span>
                            <h2 class="summary-panel-title">Which subjects are performing strongly</h2>
                            <p class="summary-panel-copy">Average score, pass rate, and score range for each subject in a single list.</p>
                        </div>
                        <span class="summary-panel-meta"><?php echo $totalSubjects; ?> subjects</span>
                    </div>
                    <div class="summary-panel-body">
                        <?php if ($subjectPerformance && $subjectPerformance->num_rows > 0): ?>
                            <?php while ($subject = $subjectPerformance->fetch_assoc()): ?>
                                <?php
                                $totalMarksForSubject = (int)($subject['total_marks'] ?? 0);
                                $passedCount = (int)($subject['passed_count'] ?? 0);
                                $failedCount = (int)($subject['failed_count'] ?? 0);
                                $averageScore = (float)($subject['average_score'] ?? 0);
                                $passRate = (float)($subject['pass_rate'] ?? 0);
                                $bandLabel = formatSummaryLabel($subject['performance_band'] ?? 'NO DATA');
                                $barWidth = $totalMarksForSubject > 0 ? max(10, min(100, (int)round($averageScore))) : 10;
                                ?>
                                <article class="summary-row">
                                    <div class="summary-row-main">
                                        <div class="summary-row-head">
                                            <h3 class="summary-name"><?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <span class="<?php echo getStatusPillClass($subject['performance_band'] ?? 'NO DATA'); ?>"><?php echo htmlspecialchars($bandLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <p class="summary-meta">
                                            <?php if ($totalMarksForSubject > 0): ?>
                                                <?php echo $passedCount; ?> pass
                                                <span>&bull; <?php echo $failedCount; ?> below pass mark</span>
                                                <span>&bull; High <?php echo (int)round((float)($subject['highest_score'] ?? 0)); ?></span>
                                                <span>&bull; Low <?php echo (int)round((float)($subject['lowest_score'] ?? 0)); ?></span>
                                            <?php else: ?>
                                                No marks recorded for this subject yet.
                                            <?php endif; ?>
                                        </p>
                                        <div class="summary-chip-row">
                                            <span class="summary-chip"><?php echo number_format($passRate, 1); ?>% pass rate</span>
                                            <span class="summary-chip"><?php echo $totalMarksForSubject; ?> marks</span>
                                        </div>
                                        <div class="summary-progress">
                                            <span style="width: <?php echo $barWidth; ?>%"></span>
                                        </div>
                                    </div>
                                    <div class="summary-row-side">
                                        <div class="summary-value"><?php echo number_format($averageScore, 1); ?>%</div>
                                        <div class="summary-caption">Average score</div>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="summary-empty">
                                <p>No subject performance data available yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/dashboard_shell_end.php'; ?>



