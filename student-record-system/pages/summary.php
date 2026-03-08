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
    <style>
        body.summary-page {
            color: #1f292c;
            background:
                radial-gradient(circle at 10% 12%, rgba(196, 111, 52, 0.18), transparent 28%),
                radial-gradient(circle at 88% 10%, rgba(31, 92, 88, 0.18), transparent 26%),
                linear-gradient(180deg, #f6efe6 0%, #efe3d5 100%);
        }

        .summary-page .main-container {
            background: transparent;
            min-height: calc(100vh - 82px);
            padding-bottom: 28px;
        }

        .summary-shell {
            max-width: 1180px;
            margin: 0 auto;
        }

        .summary-hero {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(300px, 0.9fr);
            gap: 1.4rem;
            padding: 2rem;
            overflow: hidden;
            border: 1px solid rgba(87, 68, 44, 0.1);
            border-radius: 28px;
            background: linear-gradient(135deg, rgba(255, 250, 243, 0.95) 0%, rgba(245, 236, 225, 0.98) 100%);
            box-shadow: 0 24px 48px rgba(76, 55, 36, 0.12);
        }

        .summary-hero::after {
            content: "";
            position: absolute;
            inset: auto -120px -120px auto;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(31, 92, 88, 0.16) 0%, rgba(31, 92, 88, 0) 70%);
            pointer-events: none;
        }

        .summary-kicker {
            display: inline-block;
            margin-bottom: 0.75rem;
            padding: 0.38rem 0.85rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8c5228;
            background: rgba(196, 111, 52, 0.12);
        }

        .summary-title {
            margin: 0;
            font-size: 2.7rem;
            line-height: 1.05;
            color: #1d2f30;
        }

        .summary-intro {
            max-width: 48rem;
            margin: 0.9rem 0 0;
            font-size: 1rem;
            line-height: 1.7;
            color: #5d6668;
        }

        .summary-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1.4rem;
        }

        .summary-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 150px;
            padding: 0.75rem 1.1rem;
            border-radius: 999px;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .summary-action:hover {
            transform: translateY(-1px);
        }

        .summary-action-primary {
            color: #ffffff;
            background: linear-gradient(135deg, #1f5c58 0%, #347772 100%);
            box-shadow: 0 12px 24px rgba(31, 92, 88, 0.22);
        }

        .summary-action-secondary {
            color: #8c5228;
            background: rgba(196, 111, 52, 0.1);
            border: 1px solid rgba(196, 111, 52, 0.18);
        }

        .summary-stats {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.95rem;
            align-content: start;
        }

        .summary-stat-card {
            min-height: 128px;
            padding: 1rem;
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            box-shadow: 0 16px 28px rgba(70, 51, 34, 0.1);
        }

        .summary-stat-card strong {
            display: block;
            margin-top: 1.2rem;
            font-size: 2rem;
            line-height: 1;
        }

        .summary-stat-card span {
            font-size: 0.84rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            opacity: 0.88;
        }

        .summary-stat-card p {
            margin: 0.55rem 0 0;
            font-size: 0.9rem;
            line-height: 1.45;
            opacity: 0.9;
        }

        .summary-stat-card-emerald {
            color: #ffffff;
            background: linear-gradient(145deg, #1f5c58 0%, #2f817a 100%);
        }

        .summary-stat-card-clay {
            color: #ffffff;
            background: linear-gradient(145deg, #b86434 0%, #d7844f 100%);
        }

        .summary-stat-card-sand {
            color: #3a2d22;
            background: linear-gradient(145deg, #fffaf2 0%, #f4ead9 100%);
        }

        .summary-stat-card-ink {
            color: #ffffff;
            background: linear-gradient(145deg, #37404d 0%, #596273 100%);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
            margin-top: 1.6rem;
        }

        .summary-panel {
            overflow: hidden;
            border: 1px solid rgba(87, 68, 44, 0.1);
            border-radius: 26px;
            background: rgba(255, 251, 246, 0.94);
            box-shadow: 0 20px 40px rgba(76, 55, 36, 0.1);
        }

        .summary-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.45rem 1.45rem 1rem;
            border-bottom: 1px solid #efe2d3;
        }

        .summary-panel-label {
            display: inline-block;
            margin-bottom: 0.35rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8c5228;
        }

        .summary-panel-title {
            margin: 0;
            font-size: 1.2rem;
            color: #1d2f30;
        }

        .summary-panel-copy {
            margin: 0.45rem 0 0;
            color: #677173;
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .summary-panel-meta {
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #1f5c58;
            background: rgba(31, 92, 88, 0.1);
        }

        .summary-panel-body {
            padding: 1rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid #eee1d2;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(76, 55, 36, 0.06);
        }

        .summary-row + .summary-row {
            margin-top: 0.9rem;
        }

        .summary-row-main {
            flex: 1;
            min-width: 0;
        }

        .summary-row-head {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
            margin-bottom: 0.4rem;
        }

        .summary-name {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #1f292c;
        }

        .summary-grade {
            display: inline-flex;
            align-items: center;
            padding: 0.28rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #1f5c58;
            background: #ebf4f2;
        }

        .summary-meta {
            margin: 0;
            color: #6c7678;
            font-size: 0.92rem;
            line-height: 1.55;
        }

        .summary-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .summary-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.34rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #5f5850;
            background: #f5ede2;
        }

        .summary-progress {
            height: 9px;
            margin-top: 0.9rem;
            overflow: hidden;
            border-radius: 999px;
            background: #efe2d3;
        }

        .summary-progress span {
            display: block;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #c46f34 0%, #1f5c58 100%);
        }

        .summary-row-side {
            min-width: 132px;
            text-align: right;
        }

        .summary-value {
            font-size: 1.7rem;
            font-weight: 700;
            line-height: 1;
            color: #1d2f30;
        }

        .summary-caption {
            margin-top: 0.35rem;
            color: #7a8284;
            font-size: 0.83rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 0.6rem;
            padding: 0.36rem 0.78rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .status-pass {
            color: #1f5c58;
            background: rgba(31, 92, 88, 0.12);
        }

        .status-good {
            color: #47682f;
            background: rgba(127, 140, 56, 0.16);
        }

        .status-fair {
            color: #8c5228;
            background: rgba(196, 111, 52, 0.14);
        }

        .status-incomplete {
            color: #8d5a14;
            background: rgba(210, 157, 61, 0.18);
        }

        .status-fail {
            color: #8a3d4d;
            background: rgba(138, 61, 77, 0.12);
        }

        .status-muted {
            color: #56626f;
            background: rgba(89, 98, 115, 0.12);
        }

        .summary-empty {
            padding: 2rem 1.2rem;
            text-align: center;
            color: #6c7678;
        }

        .summary-empty p {
            margin: 0;
        }

        @media (max-width: 991px) {
            .summary-hero,
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body.summary-page {
                padding-top: 72px;
            }

            .summary-hero {
                padding: 1.35rem;
                border-radius: 22px;
            }

            .summary-title {
                font-size: 2.1rem;
            }

            .summary-stats {
                grid-template-columns: 1fr 1fr;
            }

            .summary-row {
                flex-direction: column;
            }

            .summary-row-side {
                min-width: 0;
                width: 100%;
                text-align: left;
            }
        }

        @media (max-width: 575px) {
            .summary-stats {
                grid-template-columns: 1fr;
            }

            .summary-actions {
                flex-direction: column;
            }

            .summary-action {
                width: 100%;
            }
        }
    </style>
</head>
<body class="summary-page">
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
        <div class="container mt-4 summary-shell">
            <section class="summary-hero">
                <div>
                    <span class="summary-kicker">Academic Summary</span>
                    <h1 class="summary-title">A cleaner view of student progress and subject health.</h1>
                    <p class="summary-intro">This page brings together completion, averages, pass rate, and subject trends so teachers and admins can review the school picture quickly without opening multiple reports.</p>
                    <div class="summary-actions">
                        <a href="marks.php" class="summary-action summary-action-primary">Manage Marks</a>
                        <a href="report.php" class="summary-action summary-action-secondary">Open Reports</a>
                    </div>
                </div>
                <div class="summary-stats">
                    <div class="summary-stat-card summary-stat-card-emerald">
                        <span>Students</span>
                        <strong><?php echo $totalStudents; ?></strong>
                        <p>Learners currently tracked in the system.</p>
                    </div>
                    <div class="summary-stat-card summary-stat-card-clay">
                        <span>Subjects</span>
                        <strong><?php echo $totalSubjects; ?></strong>
                        <p>Courses contributing to progress and reports.</p>
                    </div>
                    <div class="summary-stat-card summary-stat-card-sand">
                        <span>Marks</span>
                        <strong><?php echo $totalMarks; ?></strong>
                        <p>Recorded assessments available for analysis.</p>
                    </div>
                    <div class="summary-stat-card summary-stat-card-ink">
                        <span>Focus</span>
                        <strong><?php echo $totalSubjects > 0 ? number_format(($totalMarks / max($totalSubjects, 1)), 1) : '0.0'; ?></strong>
                        <p>Average recorded marks per subject slot.</p>
                    </div>
                </div>
            </section>

            <div class="summary-grid">
                <section class="summary-panel">
                    <div class="summary-panel-header">
                        <div>
                            <span class="summary-panel-label">Student Summary</span>
                            <h2 class="summary-panel-title">Who is on track and who still needs marks</h2>
                            <p class="summary-panel-copy">Sorted by average score, with completion progress and status for each student.</p>
                        </div>
                        <span class="summary-panel-meta"><?php echo $totalStudents; ?> students</span>
                    </div>
                    <div class="summary-panel-body">
                        <?php if ($studentSummary && $studentSummary->num_rows > 0): ?>
                            <?php while ($student = $studentSummary->fetch_assoc()): ?>
                                <?php
                                $recordedSubjects = (int)($student['recorded_subjects'] ?? 0);
                                $targetSubjects = (int)($student['total_subjects'] ?? $totalSubjects);
                                $passedSubjects = (int)($student['passed_subjects'] ?? 0);
                                $pendingSubjects = max($targetSubjects - $recordedSubjects, 0);
                                $completionWidth = $targetSubjects > 0 ? max(10, min(100, (int)round(($recordedSubjects / $targetSubjects) * 100))) : 10;
                                $averageScore = number_format((float)($student['average_score'] ?? 0), 1);
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
                                        <div class="summary-value"><?php echo $averageScore; ?>%</div>
                                        <div class="summary-caption">Average score</div>
                                        <span class="<?php echo getStatusPillClass($student['overall_status'] ?? 'NO DATA'); ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </article>
                            <?php endwhile; ?>
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

    <?php
    $footer_base_path = '..';
    include __DIR__ . '/../includes/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>