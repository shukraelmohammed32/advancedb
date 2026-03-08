<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireLogin();

$db = new Database();
$conn = $db->getConnection();
$is_admin = hasRole('admin');
$is_teacher = hasRole('teacher');
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

$teacher_grade = $is_teacher ? getTeacherAssignedGrade($conn, $session_teacher_id) : '';
$page_title = $is_teacher ? 'My Student Reports' : 'Academic Reports';

// Handle form submission for generating report
$report_data = null;
$selected_student = null;
$error_message = '';
$info_message = '';

if ($is_teacher) {
    if ($teacher_grade !== '') {
        $info_message = 'You can generate reports only for students in ' . $teacher_grade . '.';
    } else {
        $error_message = 'Your teacher account is not linked to an assigned grade. Contact admin.';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
    requireValidCsrfToken();

    if (canOnlyViewOwnRecords()) {
        $student_id = (int)($_SESSION['student_id'] ?? 0);
        if ($student_id <= 0) {
            $error_message = 'Your account is not linked to a student record.';
        }
    } else {
        $student_id = (int)($_POST['student_id'] ?? 0);
    }

    if (empty($error_message) && $is_teacher && !teacherCanAccessStudent($conn, $session_teacher_id, $student_id)) {
        $error_message = 'You can generate reports only for students in your assigned grade.';
    }

    if (empty($error_message) && $student_id > 0) {
        $student_result = $conn->query("SELECT * FROM students WHERE student_id = $student_id");
        $selected_student = $student_result ? $student_result->fetch_assoc() : null;

        if ($selected_student) {
            $marks_query = "SELECT
                            s.subject_name,
                            m.score,
                            CASE
                                WHEN m.score >= 50 THEN 'PASS'
                                ELSE 'FAIL'
                            END as status
                        FROM subjects s
                        LEFT JOIN marks m ON s.subject_id = m.subject_id AND m.student_id = $student_id
                        ORDER BY s.subject_name";

            $marks_result = $conn->query($marks_query);
            $marks = [];
            $total_score = 0;
            $subject_count = 0;
            $all_passed = true;

            if ($marks_result) {
                while ($mark = $marks_result->fetch_assoc()) {
                    $marks[$mark['subject_name']] = $mark;

                    if ($mark['score'] !== null) {
                        $total_score += (int)$mark['score'];
                        $subject_count++;

                        if ((int)$mark['score'] < 50) {
                            $all_passed = false;
                        }
                    } else {
                        $all_passed = false;
                    }
                }
            }

            $average = $subject_count > 0 ? round($total_score / $subject_count, 2) : 0;

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

            $subject_count_result = $conn->query('SELECT COUNT(*) as count FROM subjects');
            $total_subjects = $subject_count_result ? (int)$subject_count_result->fetch_assoc()['count'] : 0;
            $has_all_marks = $subject_count >= $total_subjects;

            $report_data = [
                'student' => $selected_student,
                'marks' => $marks,
                'total' => $total_score,
                'average' => $average,
                'rank' => $rank,
                'rank_label' => 'Rank in ' . normalizeGradeLabel($selected_student['grade']),
                'status' => ($has_all_marks && $all_passed && $subject_count > 0) ? 'PASS' : 'FAIL'
            ];
        } else {
            $error_message = 'Student not found.';
        }
    }
}

$student_rows = [];
$students_query = $conn->query('SELECT student_id, name, grade FROM students ORDER BY name');
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

$subjects = $conn->query('SELECT subject_name FROM subjects ORDER BY subject_name');
$all_subjects = [];
if ($subjects) {
    while ($subject = $subjects->fetch_assoc()) {
        $all_subjects[] = $subject['subject_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
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
            <a class="navbar-brand" href="../index.php">Student Record System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Dashboard</a>
                    </li>
                    <?php if (canAccessStudentRecords()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="students.php">Students</a>
                    </li>
                    <?php endif; ?>
                    <?php if (canAccessTeacherDashboard()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="subjects.php">Subjects</a>
                    </li>
                    <?php if ($is_admin): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="teachers.php">Teachers</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="marks.php">Marks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="summary.php">Summary</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="report.php">Reports</a>
                    </li>
                    <?php if (canOnlyViewOwnRecords()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <?php endif; ?>
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
                        Generate Report
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php csrfInput(); ?>
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Select Student</label>
                                <select class="form-control" id="student_id" name="student_id" required <?php echo canOnlyViewOwnRecords() ? 'disabled' : ''; ?>>
                                    <option value="">Select Student</option>
                                    <?php foreach ($student_rows as $student): ?>
                                        <option value="<?php echo (int)$student['student_id']; ?>"
                                                <?php echo isset($_POST['student_id']) && (int)$_POST['student_id'] === (int)$student['student_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['name'] . ' - ' . normalizeGradeLabel($student['grade']), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (canOnlyViewOwnRecords()): ?>
                                    <input type="hidden" name="student_id" value="<?php echo (int)($_SESSION['student_id'] ?? 0); ?>">
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary" name="generate_report">
                                Generate Report
                            </button>
                            <?php if ($report_data): ?>
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
                                    <h3><?php echo htmlspecialchars((string)$report_data['average'], ENT_QUOTES, 'UTF-8'); ?></h3>
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
                                        } else {
                                            echo '<span class="fail">FAIL</span>';
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
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h4>No Report Generated</h4>
                            <p class="text-muted">Select a student from the form to generate their academic report.</p>
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
</body>
</html>
