<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireAnyRole(['admin', 'teacher']);

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

function formatDashboardLabel($value) {
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

$db = new Database();
$conn = $db->getConnection();

$totalStudents = (int)$conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$totalSubjects = (int)$conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
$totalMarks = (int)$conn->query("SELECT COUNT(*) as count FROM marks")->fetch_assoc()['count'];

$studentSummary = $conn->query("
    SELECT
        student_id,
        name,
        grade,
        total_marks AS recorded_subjects,
        total_subjects,
        passed_subjects,
        average_score,
        overall_status
    FROM student_summary
    ORDER BY average_score DESC, name ASC
");

if ($studentSummary === false) {
    $studentSummary = $conn->query("
        SELECT
            s.student_id,
            s.name,
            s.grade,
            COUNT(m.mark_id) AS recorded_subjects,
            {$totalSubjects} AS total_subjects,
            SUM(CASE WHEN m.score >= 50 THEN 1 ELSE 0 END) AS passed_subjects,
            COALESCE(ROUND(AVG(m.score), 1), 0) AS average_score,
            CASE
                WHEN {$totalSubjects} = 0 THEN 'NO SUBJECTS'
                WHEN COUNT(m.mark_id) = 0 THEN 'NO MARKS'
                WHEN COUNT(m.mark_id) < {$totalSubjects} THEN 'INCOMPLETE'
                WHEN SUM(CASE WHEN m.score >= 50 THEN 1 ELSE 0 END) = {$totalSubjects} THEN 'PASS'
                ELSE 'FAIL'
            END AS overall_status
        FROM students s
        LEFT JOIN marks m ON s.student_id = m.student_id
        GROUP BY s.student_id, s.name, s.grade
        ORDER BY average_score DESC, s.name ASC
    ");
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
            SUM(CASE WHEN m.score >= 50 THEN 1 ELSE 0 END) AS passed_count,
            SUM(CASE WHEN m.score < 50 THEN 1 ELSE 0 END) AS failed_count,
            COALESCE(
                ROUND((SUM(CASE WHEN m.score >= 50 THEN 1 ELSE 0 END) / NULLIF(COUNT(m.mark_id), 0)) * 100, 1),
                0
            ) AS pass_rate,
            CASE
                WHEN COUNT(m.mark_id) = 0 THEN 'NO DATA'
                WHEN AVG(m.score) >= 80 THEN 'EXCELLENT'
                WHEN AVG(m.score) >= 60 THEN 'GOOD'
                WHEN AVG(m.score) >= 50 THEN 'FAIR'
                ELSE 'NEEDS IMPROVEMENT'
            END AS performance_band
        FROM subjects sub
        LEFT JOIN marks m ON sub.subject_id = m.subject_id
        GROUP BY sub.subject_id, sub.subject_name
        ORDER BY average_score DESC, sub.subject_name ASC
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">Student Record System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="subjects.php">Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="teachers.php">Teachers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="marks.php">Marks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="summary.php">Summary</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">Reports</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="container mt-4">
            <div class="row align-items-end mb-4">
                <div class="col-lg-8">
                    <h1 class="dashboard-title mb-2">Performance Summary</h1>
                    <p class="text-muted mb-0">Student results and subject trends in one place for admin and teacher review.</p>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0">
                    <div class="card quick-actions">
                        <div class="card-body py-3">
                            <div class="row text-center g-2">
                                <div class="col-4">
                                    <div class="fw-bold text-primary fs-5"><?php echo $totalStudents; ?></div>
                                    <small class="text-muted">Students</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold" style="color: #158a55; font-size: 1.25rem;"><?php echo $totalSubjects; ?></div>
                                    <small class="text-muted">Subjects</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold" style="color: #b06e1e; font-size: 1.25rem;"><?php echo $totalMarks; ?></div>
                                    <small class="text-muted">Marks</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-6 mb-4">
                    <div class="card dashboard-panel">
                        <div class="card-header dashboard-panel-header">
                            <div>
                                <span class="dashboard-section-label">Student Summary</span>
                                <h2 class="dashboard-section-title">How students are performing</h2>
                            </div>
                            <span class="dashboard-section-meta"><?php echo $totalStudents; ?> students</span>
                        </div>
                        <div class="card-body dashboard-panel-body">
                            <?php if ($studentSummary && $studentSummary->num_rows > 0): ?>
                                <div class="dashboard-list">
                                    <?php while ($student = $studentSummary->fetch_assoc()): ?>
                                        <?php
                                        $recordedSubjects = (int)($student['recorded_subjects'] ?? 0);
                                        $targetSubjects = (int)($student['total_subjects'] ?? $totalSubjects);
                                        $passedSubjects = (int)($student['passed_subjects'] ?? 0);
                                        $pendingSubjects = max($targetSubjects - $recordedSubjects, 0);
                                        $averageScore = number_format((float)($student['average_score'] ?? 0), 1);
                                        $statusLabel = formatDashboardLabel($student['overall_status'] ?? 'NO DATA');
                                        ?>
                                        <div class="dashboard-list-item">
                                            <div class="dashboard-list-main">
                                                <div class="dashboard-list-head">
                                                    <h3 class="dashboard-list-title"><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                                    <span class="dashboard-grade"><?php echo htmlspecialchars(normalizeGradeLabel($student['grade']), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                                <p class="dashboard-list-meta">
                                                    <?php if ($targetSubjects > 0): ?>
                                                        <?php echo $passedSubjects; ?>/<?php echo $targetSubjects; ?> passed
                                                        <?php if ($pendingSubjects > 0): ?>
                                                            <span>&bull; <?php echo $pendingSubjects; ?> pending</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        No subjects configured yet.
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <div class="dashboard-list-side">
                                                <div class="dashboard-score"><?php echo $averageScore; ?>%</div>
                                                <span class="<?php echo getStatusPillClass($student['overall_status'] ?? 'NO DATA'); ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="dashboard-empty-state">
                                    <p>No student summary data available yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 mb-4">
                    <div class="card dashboard-panel">
                        <div class="card-header dashboard-panel-header">
                            <div>
                                <span class="dashboard-section-label">Subject Performance</span>
                                <h2 class="dashboard-section-title">How each subject is performing</h2>
                            </div>
                            <span class="dashboard-section-meta"><?php echo $totalSubjects; ?> subjects</span>
                        </div>
                        <div class="card-body dashboard-panel-body">
                            <?php if ($subjectPerformance && $subjectPerformance->num_rows > 0): ?>
                                <div class="dashboard-list">
                                    <?php while ($subject = $subjectPerformance->fetch_assoc()): ?>
                                        <?php
                                        $totalMarks = (int)($subject['total_marks'] ?? 0);
                                        $passedCount = (int)($subject['passed_count'] ?? 0);
                                        $averageScore = (float)($subject['average_score'] ?? 0);
                                        $passRate = (float)($subject['pass_rate'] ?? 0);
                                        $bandLabel = formatDashboardLabel($subject['performance_band'] ?? 'NO DATA');
                                        $barWidth = $totalMarks > 0 ? max(12, min(100, (int)round($averageScore))) : 12;
                                        ?>
                                        <div class="dashboard-list-item">
                                            <div class="dashboard-list-main">
                                                <div class="dashboard-list-head">
                                                    <h3 class="dashboard-list-title"><?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                                    <span class="<?php echo getStatusPillClass($subject['performance_band'] ?? 'NO DATA'); ?>"><?php echo htmlspecialchars($bandLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                                <p class="dashboard-list-meta">
                                                    <?php if ($totalMarks > 0): ?>
                                                        <?php echo $passedCount; ?>/<?php echo $totalMarks; ?> pass
                                                        <span>&bull; High <?php echo (int)round((float)($subject['highest_score'] ?? 0)); ?></span>
                                                        <span>&bull; Low <?php echo (int)round((float)($subject['lowest_score'] ?? 0)); ?></span>
                                                    <?php else: ?>
                                                        No marks recorded for this subject yet.
                                                    <?php endif; ?>
                                                </p>
                                                <div class="performance-track <?php echo $totalMarks > 0 ? '' : 'performance-track-empty'; ?>">
                                                    <span style="width: <?php echo $barWidth; ?>%"></span>
                                                </div>
                                            </div>
                                            <div class="dashboard-list-side">
                                                <div class="dashboard-score"><?php echo number_format($averageScore, 1); ?>%</div>
                                                <div class="dashboard-subscore"><?php echo $totalMarks > 0 ? number_format($passRate, 1) . '% pass rate' : 'Awaiting marks'; ?></div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="dashboard-empty-state">
                                    <p>No subject performance data available yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $footer_base_path = '..';
    include __DIR__ . '/../includes/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>