<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';
require_once '../includes/distributed_coordinator.php';

requireLogin();
if (!canViewStudentReports()) {
    $_SESSION['error'] = 'Only admin, homeroom teachers, and students can access reports.';
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$coordinator = new DistributedCoordinator($db);
$is_admin = hasRole('admin');
$is_super_admin = $is_admin && (string)($_SESSION['admin_scope'] ?? 'main') === 'main';
$is_teacher = hasRole('teacher');
$is_student = hasRole('student');
$distributed_ready = $coordinator->isDistributedReady();
$can_access_distributed = canAccessDistributedCoordinator();
$show_site_details = $can_access_distributed && $distributed_ready;
$default_site_id = $coordinator->getDefaultSiteId();
$session_teacher_id = $is_teacher ? (int)($_SESSION['teacher_id'] ?? 0) : 0;

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

function gradesMatch($left_grade, $right_grade) {
    return strtolower(normalizeGradeLabel($left_grade)) === strtolower(normalizeGradeLabel($right_grade));
}

function getTeacherAssignedGrade($conn, $teacher_id) {
    $teacher_id = (int)$teacher_id;
    if ($teacher_id <= 0) {
        return '';
    }

    $result = $conn->query("SELECT assigned_grade FROM teachers WHERE teacher_id = $teacher_id LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        return '';
    }

    $row = $result->fetch_assoc();
    return normalizeGradeLabel($row['assigned_grade']);
}

function teacherCanAccessStudent($conn, $teacher_id, $student_id) {
    $teacher_id = (int)$teacher_id;
    $student_id = (int)$student_id;

    if ($teacher_id <= 0 || $student_id <= 0) {
        return false;
    }

    $sql = "SELECT t.assigned_grade, s.grade
            FROM teachers t
            JOIN students s ON s.student_id = $student_id
            WHERE t.teacher_id = $teacher_id
            LIMIT 1";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        return false;
    }

    $row = $result->fetch_assoc();
    return gradesMatch($row['assigned_grade'], $row['grade']);
}

function fetchTeacherReportData($conn, $teacher_id) {
    $teacher_id = (int)$teacher_id;
    if ($teacher_id <= 0) {
        return null;
    }

    $sql = "SELECT
                t.teacher_id,
                t.teacher_name,
                t.department,
                t.assigned_grade,
                t.is_homeroom,
                t.created_at,
                COALESCE(GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ', '), t.department) AS subjects_taught,
                COUNT(DISTINCT ts.subject_id) AS subject_count,
                COUNT(DISTINCT m.mark_id) AS marks_recorded,
                COUNT(DISTINCT m.student_id) AS students_graded,
                ROUND(AVG(m.score), 2) AS average_score,
                MAX(COALESCE(m.updated_at, m.created_at)) AS latest_mark_at,
                (SELECT u.username FROM users u WHERE u.teacher_id = t.teacher_id AND u.role = 'teacher' ORDER BY u.user_id ASC LIMIT 1) AS login_username,
                (SELECT u.email FROM users u WHERE u.teacher_id = t.teacher_id AND u.role = 'teacher' ORDER BY u.user_id ASC LIMIT 1) AS login_email,
                EXISTS(SELECT 1 FROM users u WHERE u.teacher_id = t.teacher_id AND u.role = 'teacher' AND u.is_active = 1) AS has_login
            FROM teachers t
            LEFT JOIN teacher_subjects ts ON ts.teacher_id = t.teacher_id
            LEFT JOIN subjects s ON s.subject_id = ts.subject_id
            LEFT JOIN marks m ON m.teacher_id = t.teacher_id
            WHERE t.teacher_id = $teacher_id
            GROUP BY t.teacher_id, t.teacher_name, t.department, t.assigned_grade, t.is_homeroom, t.created_at";

    $result = $conn->query($sql);
    return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

$teacher_grade = $is_teacher ? getTeacherAssignedGrade($conn, $session_teacher_id) : '';
$page_title = $is_admin ? 'School Reports' : (isHomeroomTeacher() ? 'Homeroom Reports' : 'My Academic Report');
$student_site_select = $show_site_details
    ? "s.site_id, COALESCE(ds.site_name, 'Unassigned Site') AS site_name"
    : "$default_site_id AS site_id, 'Central Coordinator' AS site_name";
$student_site_join = $show_site_details ? 'LEFT JOIN distributed_sites ds ON ds.site_id = s.site_id' : '';

// Handle form submission for generating report
$report_data = null;
$teacher_report_data = null;
$report_type = ($is_super_admin && isset($_POST['report_type']) && $_POST['report_type'] === 'teacher') ? 'teacher' : 'student';
$selected_student = null;
$error_message = '';
$info_message = '';

if (isHomeroomTeacher()) {
    if ($teacher_grade !== '') {
        $info_message = 'As homeroom teacher, you can generate final academic reports only for students in ' . $teacher_grade . '.';
    } else {
        $error_message = 'Your homeroom teacher account is not linked to an assigned grade. Contact admin.';
    }
} elseif ($is_admin) {
    $info_message = $is_super_admin
        ? 'Super Admin can generate academic reports for any student and activity reports for every teacher in the system.'
        : 'Admins can generate academic reports for any student in the system.';
} elseif ($is_student) {
    $info_message = 'You can view only your own academic report, marks, rank, and pass or fail status.';
}

$should_generate_report = (($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) || ($is_student && (int)($_SESSION['student_id'] ?? 0) > 0));

if ($should_generate_report) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
        requireValidCsrfToken();
    }

    if (canOnlyViewOwnRecords()) {
        $student_id = (int)($_SESSION['student_id'] ?? 0);
        if ($student_id <= 0) {
            $error_message = 'Your account is not linked to a student record.';
        }
    } else {
        $student_id = (int)($_POST['student_id'] ?? 0);
    }

    if (empty($error_message) && $report_type === 'teacher' && !$is_super_admin) {
        $error_message = 'Only Super Admin can generate teacher reports.';
    }

    if (empty($error_message) && $report_type === 'student' && $is_teacher && !teacherCanAccessStudent($conn, $session_teacher_id, $student_id)) {
        $error_message = 'You can generate reports only for students in your assigned grade.';
    }

    if (empty($error_message) && $report_type === 'teacher') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        if ($teacher_id <= 0) {
            $error_message = 'Teacher not found.';
        } else {
            $teacher_report_data = fetchTeacherReportData($conn, $teacher_id);
            if (!$teacher_report_data) {
                $error_message = 'Teacher not found.';
            }
        }
    }

    if (empty($error_message) && $report_type === 'student' && $student_id > 0) {
        $student_result = $conn->query("SELECT s.*, $student_site_select FROM students s $student_site_join WHERE s.student_id = $student_id");
        $selected_student = $student_result ? $student_result->fetch_assoc() : null;

        if ($selected_student) {
            $marks_query = "SELECT
                            s.subject_name,
                            m.score,
                            COALESCE(t.teacher_name, '') AS teacher_name,
                            CASE
                                WHEN m.score >= 50 THEN 'PASS'
                                ELSE 'FAIL'
                            END as status
                        FROM subjects s
                        LEFT JOIN marks m ON s.subject_id = m.subject_id AND m.student_id = $student_id
                        LEFT JOIN teachers t ON t.teacher_id = m.teacher_id
                        ORDER BY s.subject_name";

            $marks_result = $conn->query($marks_query);
            $marks = [];
            $total_score = 0;
            $subject_count = 0;

            if ($marks_result) {
                while ($mark = $marks_result->fetch_assoc()) {
                    $marks[$mark['subject_name']] = $mark;

                    if ($mark['score'] !== null) {
                        $total_score += (int)$mark['score'];
                        $subject_count++;
                    }
                }
            }

            $subject_count_result = $conn->query('SELECT COUNT(*) as count FROM subjects');
            $total_subjects = $subject_count_result ? (int)$subject_count_result->fetch_assoc()['count'] : 0;
            $has_all_marks = $total_subjects > 0 && $subject_count >= $total_subjects;
            $average = $has_all_marks ? round($total_score / $total_subjects, 2) : null;

            if ($total_subjects <= 0) {
                $overall_status = 'NO SUBJECTS';
            } elseif ($subject_count === 0) {
                $overall_status = 'NO MARKS';
            } elseif (!$has_all_marks) {
                $overall_status = 'INCOMPLETE';
            } elseif ($average >= 50) {
                $overall_status = 'PASS';
            } else {
                $overall_status = 'FAIL';
            }

            $student_grade = $conn->real_escape_string($selected_student['grade']);
            $rank_query = "SELECT student_rank
                           FROM (
                               SELECT
                                   s.student_id,
                                   s.grade,
                                   RANK() OVER (PARTITION BY s.grade ORDER BY COALESCE(SUM(m.score), 0) DESC) AS student_rank
                               FROM students s
                               LEFT JOIN marks m ON s.student_id = m.student_id
                               WHERE s.grade = '$student_grade'
                               GROUP BY s.student_id, s.grade
                           ) ranked
                           WHERE student_id = $student_id";

            $rank_result = $conn->query($rank_query);
            $rank_row = $rank_result ? $rank_result->fetch_assoc() : null;
            $rank = $rank_row ? $rank_row['student_rank'] : 'N/A';

            $report_data = [
                'student' => $selected_student,
                'marks' => $marks,
                'total' => $total_score,
                'average' => $average,
                'rank' => $rank,
                'rank_label' => 'Rank in ' . normalizeGradeLabel($selected_student['grade']),
                'status' => $overall_status,
                'recorded_subjects' => $subject_count,
                'total_subjects' => $total_subjects,
                'has_all_marks' => $has_all_marks
            ];
        } else {
            $error_message = 'Student not found.';
        }
    }
}

$student_rows = [];
$students_query = $conn->query("SELECT s.student_id, s.name, s.grade, $student_site_select FROM students s $student_site_join ORDER BY s.name");
if ($students_query) {
    while ($student = $students_query->fetch_assoc()) {
        if (canOnlyViewOwnRecords() && (int)$student['student_id'] !== (int)($_SESSION['student_id'] ?? 0)) {
            continue;
        }

        if ($is_teacher && ($teacher_grade === '' || !gradesMatch($student['grade'], $teacher_grade))) {
            continue;
        }

        $student_rows[] = $student;
    }
}

$teacher_rows = [];
if ($is_super_admin) {
    $teachers_query = $conn->query("SELECT t.teacher_id, t.teacher_name, t.assigned_grade,
                                           COALESCE(GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ', '), t.department) AS subjects_taught
                                    FROM teachers t
                                    LEFT JOIN teacher_subjects ts ON ts.teacher_id = t.teacher_id
                                    LEFT JOIN subjects s ON s.subject_id = ts.subject_id
                                    GROUP BY t.teacher_id, t.teacher_name, t.assigned_grade, t.department
                                    ORDER BY t.teacher_name ASC");
    if ($teachers_query) {
        while ($teacher = $teachers_query->fetch_assoc()) {
            $teacher_rows[] = $teacher;
        }
    }
}

$subjects = $conn->query('SELECT subject_name FROM subjects ORDER BY subject_name');
$all_subjects = [];
if ($subjects) {
    while ($subject = $subjects->fetch_assoc()) {
        $all_subjects[] = $subject['subject_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLanguageTag(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .report-card { box-shadow: none !important; }
        }
        .report-header {
            background-color: #1f5f95;
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .student-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .report-table th {
            background-color: #1f5f95;
            color: white;
        }
        .summary-card {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark no-print">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><?php echo htmlspecialchars(t('Student Record System'), ENT_QUOTES, 'UTF-8'); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php"><?php echo htmlspecialchars(t('Dashboard'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php if (canAccessStudentRecords()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="students.php"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canAccessTeacherDashboard()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="subjects.php"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php if ($is_admin): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="teachers.php"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canManageMarks()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="marks.php"><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canAccessSummary()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="summary.php"><?php echo htmlspecialchars(t('Summary'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="report.php"><?php echo htmlspecialchars(t('Reports'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php if (canOnlyViewOwnRecords()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php"><?php echo htmlspecialchars(t('Profile'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item d-flex align-items-center ms-lg-3">
                        <?php echo renderLanguageSwitcher('../pages'); ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($info_message): ?>
            <div class="alert alert-info no-print" role="alert">
                <?php echo htmlspecialchars($info_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 no-print">
                <div class="card">
                    <div class="card-header">
                        <?php echo $is_student ? 'View My Report' : 'Generate Report'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="reportGeneratorForm">
                            <?php csrfInput(); ?>
                            <?php if ($is_super_admin): ?>
                            <div class="mb-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-control" id="report_type" name="report_type">
                                    <option value="student" <?php echo $report_type === 'student' ? 'selected' : ''; ?>>Student Report</option>
                                    <option value="teacher" <?php echo $report_type === 'teacher' ? 'selected' : ''; ?>>Teacher Report</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3" id="studentSelectorGroup" <?php echo $report_type === 'teacher' ? 'style="display:none;"' : ''; ?>>
                                <label for="student_id" class="form-label">Select Student</label>
                                <select class="form-control" id="student_id" name="student_id" required <?php echo canOnlyViewOwnRecords() ? 'disabled' : ''; ?>>
                                    <option value="">Select Student</option>
                                    <?php foreach ($student_rows as $student): ?>
                                        <option value="<?php echo (int)$student['student_id']; ?>"
                                                <?php echo isset($_POST['student_id']) && (int)$_POST['student_id'] === (int)$student['student_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['name'] . ' - ' . normalizeGradeLabel($student['grade']) . ($show_site_details ? ' - ' . ($student['site_name'] ?? 'Central Coordinator') : ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (canOnlyViewOwnRecords()): ?>
                                    <input type="hidden" name="student_id" value="<?php echo (int)($_SESSION['student_id'] ?? 0); ?>">
                                <?php endif; ?>
                            </div>

                            <?php if ($is_super_admin): ?>
                            <div class="mb-3" id="teacherSelectorGroup" <?php echo $report_type === 'teacher' ? '' : 'style="display:none;"'; ?>>
                                <label for="teacher_id" class="form-label">Select Teacher</label>
                                <select class="form-control" id="teacher_id" name="teacher_id">
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teacher_rows as $teacher): ?>
                                        <option value="<?php echo (int)$teacher['teacher_id']; ?>" <?php echo isset($_POST['teacher_id']) && (int)$_POST['teacher_id'] === (int)$teacher['teacher_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['teacher_name'] . ' - ' . normalizeGradeLabel($teacher['assigned_grade']) . ' - ' . ($teacher['subjects_taught'] ?: 'No subjects assigned'), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-primary" name="generate_report">
                                <?php echo $is_student ? 'View My Report' : ($report_type === 'teacher' ? 'Generate Teacher Report' : 'Generate Report'); ?>
                            </button>
                            <?php if ($report_data || $teacher_report_data): ?>
                                <button type="button" class="btn btn-secondary" onclick="window.print()">
                                    <i class="fas fa-print"></i> Print Report
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <?php if ($report_data): ?>
                    <div class="card report-card">
                        <div class="report-header">
                            <h2>Student Academic Report</h2>
                            <p>Academic Year: <?php echo htmlspecialchars($report_data['student']['academic_year'], ENT_QUOTES, 'UTF-8'); ?> - Semester: <?php echo htmlspecialchars($report_data['student']['semester'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>

                        <div class="student-info">
                            <h4>Student Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Name:</strong> <?php echo htmlspecialchars($report_data['student']['name'], ENT_QUOTES, 'UTF-8'); ?><br>
                                    <strong>Student ID:</strong> <?php echo (int)$report_data['student']['student_id']; ?><br>
                                    <strong>Grade:</strong> <?php echo htmlspecialchars(normalizeGradeLabel($report_data['student']['grade']), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($show_site_details): ?><br>
                                    <strong>Site:</strong> <?php echo htmlspecialchars((string)($report_data['student']['site_name'] ?? 'Central Coordinator'), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Gender:</strong> <?php echo htmlspecialchars($report_data['student']['gender'], ENT_QUOTES, 'UTF-8'); ?><br>
                                    <strong>Academic Year:</strong> <?php echo htmlspecialchars($report_data['student']['academic_year'], ENT_QUOTES, 'UTF-8'); ?><br>
                                    <strong>Semester:</strong> <?php echo htmlspecialchars($report_data['student']['semester'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered report-table">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_subjects as $subject): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php
                                                $teacherName = isset($report_data['marks'][$subject]) ? trim((string)($report_data['marks'][$subject]['teacher_name'] ?? '')) : '';
                                                if ($teacherName !== '') {
                                                    echo htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8');
                                                } else {
                                                    echo '<span class="text-muted">Not Available</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if (isset($report_data['marks'][$subject])) {
                                                    $score = $report_data['marks'][$subject]['score'];
                                                    if ($score !== null) {
                                                        echo (int)$score;
                                                    } else {
                                                        echo '<span class="text-danger">No Marks</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-danger">No Marks</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if (isset($report_data['marks'][$subject])) {
                                                    $status = $report_data['marks'][$subject]['status'];
                                                    $score = $report_data['marks'][$subject]['score'];

                                                    if ($score !== null) {
                                                        if ((int)$score >= 50) {
                                                            echo '<span class="badge bg-success">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
                                                        } else {
                                                            echo '<span class="badge bg-danger">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
                                                        }
                                                    } else {
                                                        echo '<span class="badge bg-danger">FAIL</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-danger">FAIL</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="summary-card">
                            <h4>Summary</h4>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Total Score:</strong><br>
                                    <h3><?php echo (int)$report_data['total']; ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <strong>Average:</strong><br>
                                    <?php if ($report_data['average'] !== null): ?>
                                        <h3><?php echo htmlspecialchars(number_format((float)$report_data['average'], 2), ENT_QUOTES, 'UTF-8'); ?>%</h3>
                                    <?php elseif ($report_data['status'] === 'INCOMPLETE'): ?>
                                        <h3 class="text-muted">Pending</h3>
                                    <?php else: ?>
                                        <h3 class="text-muted">N/A</h3>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong><?php echo htmlspecialchars($report_data['rank_label'], ENT_QUOTES, 'UTF-8'); ?>:</strong><br>
                                    <h3><?php echo htmlspecialchars((string)$report_data['rank'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <strong>Overall Status:</strong><br>
                                    <h3>
                                        <?php
                                        if ($report_data['status'] === 'PASS') {
                                            echo '<span class="pass">PASS</span>';
                                        } elseif ($report_data['status'] === 'FAIL') {
                                            echo '<span class="fail">FAIL</span>';
                                        } elseif ($report_data['status'] === 'INCOMPLETE') {
                                            echo '<span class="text-warning">INCOMPLETE</span>';
                                        } else {
                                            echo '<span class="text-muted">' . htmlspecialchars((string)$report_data['status'], ENT_QUOTES, 'UTF-8') . '</span>';
                                        }
                                        ?>
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4 no-print">
                            <small class="text-muted">
                                Generated on: <?php echo date('Y-m-d H:i:s'); ?>
                            </small>
                        </div>
                    </div>
                <?php elseif ($teacher_report_data): ?>
                    <div class="card report-card">
                        <div class="report-header">
                            <h2>Teacher Activity Report</h2>
                            <p>Generated for Super Admin review</p>
                        </div>

                        <div class="student-info">
                            <h4>Teacher Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Name:</strong> <?php echo htmlspecialchars((string)$teacher_report_data['teacher_name'], ENT_QUOTES, 'UTF-8'); ?><br>
                                    <strong>Teacher ID:</strong> <?php echo (int)$teacher_report_data['teacher_id']; ?><br>
                                    <strong>Assigned Grade:</strong> <?php echo htmlspecialchars(normalizeGradeLabel((string)$teacher_report_data['assigned_grade']), ENT_QUOTES, 'UTF-8'); ?><br>
                                    <strong>Homeroom:</strong> <?php echo !empty($teacher_report_data['is_homeroom']) ? 'Yes' : 'No'; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Username:</strong> <?php echo htmlspecialchars((string)($teacher_report_data['login_username'] ?? 'Not Available'), ENT_QUOTES, 'UTF-8'); ?><br>
                                    <strong>Email:</strong> <?php echo htmlspecialchars((string)($teacher_report_data['login_email'] ?? 'Not Available'), ENT_QUOTES, 'UTF-8'); ?><br>
                                    <strong>Account Status:</strong> <?php echo !empty($teacher_report_data['has_login']) ? 'Secured' : 'Missing Login'; ?><br>
                                    <strong>Created At:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime((string)$teacher_report_data['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered report-table">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Subjects Taught</th>
                                        <th>Assigned Subject Count</th>
                                        <th>Students Graded</th>
                                        <th>Marks Recorded</th>
                                        <th>Average Score</th>
                                        <th>Latest Mark Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$teacher_report_data['department'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)$teacher_report_data['subjects_taught'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int)($teacher_report_data['subject_count'] ?? 0); ?></td>
                                        <td><?php echo (int)($teacher_report_data['students_graded'] ?? 0); ?></td>
                                        <td><?php echo (int)($teacher_report_data['marks_recorded'] ?? 0); ?></td>
                                        <td>
                                            <?php if ($teacher_report_data['average_score'] !== null): ?>
                                                <?php echo htmlspecialchars(number_format((float)$teacher_report_data['average_score'], 2), ENT_QUOTES, 'UTF-8'); ?>%
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($teacher_report_data['latest_mark_at'])): ?>
                                                <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime((string)$teacher_report_data['latest_mark_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No mark activity yet</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="summary-card">
                            <h4>Summary</h4>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Subjects:</strong><br>
                                    <h3><?php echo (int)($teacher_report_data['subject_count'] ?? 0); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <strong>Students Graded:</strong><br>
                                    <h3><?php echo (int)($teacher_report_data['students_graded'] ?? 0); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <strong>Marks Recorded:</strong><br>
                                    <h3><?php echo (int)($teacher_report_data['marks_recorded'] ?? 0); ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <strong>Average Score:</strong><br>
                                    <h3>
                                        <?php echo $teacher_report_data['average_score'] !== null
                                            ? htmlspecialchars(number_format((float)$teacher_report_data['average_score'], 2), ENT_QUOTES, 'UTF-8') . '%'
                                            : '<span class="text-muted">N/A</span>'; ?>
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4 no-print">
                            <small class="text-muted">
                                Generated on: <?php echo date('Y-m-d H:i:s'); ?>
                            </small>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h4>No Report Generated</h4>
                            <p class="text-muted"><?php echo $is_student ? 'Use the form to load your current academic report.' : ($is_super_admin && $report_type === 'teacher' ? 'Select a teacher from the form to generate their activity report.' : 'Select a student from the form to generate their academic report.'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $footer_base_path = '../';
    include __DIR__ . '/../includes/footer.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        (function () {
            var reportType = document.getElementById('report_type');
            var studentGroup = document.getElementById('studentSelectorGroup');
            var teacherGroup = document.getElementById('teacherSelectorGroup');
            var studentSelect = document.getElementById('student_id');
            var teacherSelect = document.getElementById('teacher_id');

            function toggleReportSelectors() {
                if (!reportType) {
                    return;
                }

                var isTeacher = reportType.value === 'teacher';
                if (studentGroup) {
                    studentGroup.style.display = isTeacher ? 'none' : '';
                }
                if (teacherGroup) {
                    teacherGroup.style.display = isTeacher ? '' : 'none';
                }
                if (studentSelect) {
                    studentSelect.disabled = isTeacher;
                    studentSelect.required = !isTeacher;
                }
                if (teacherSelect) {
                    teacherSelect.disabled = !isTeacher;
                    teacherSelect.required = isTeacher;
                }
            }

            if (reportType) {
                reportType.addEventListener('change', toggleReportSelectors);
                toggleReportSelectors();
            }
        }());
    </script>
</body>
</html>








