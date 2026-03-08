<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireAnyRole(['admin', 'teacher']);

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

function getTeacherScope($conn, $teacher_id) {
    $teacher_id = (int)$teacher_id;
    if ($teacher_id <= 0) {
        return null;
    }

    $sql = "SELECT
                t.teacher_id,
                t.teacher_name,
                t.assigned_grade,
                COALESCE(GROUP_CONCAT(DISTINCT ts.subject_id ORDER BY ts.subject_id SEPARATOR ','), '') AS subject_ids,
                COALESCE(GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ', '), 'No subjects assigned') AS subjects_taught
            FROM teachers t
            LEFT JOIN teacher_subjects ts ON t.teacher_id = ts.teacher_id
            LEFT JOIN subjects s ON ts.subject_id = s.subject_id
            WHERE t.teacher_id = $teacher_id
            GROUP BY t.teacher_id, t.teacher_name, t.assigned_grade";

    $result = $conn->query($sql);
    return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

function teacherOwnsMark($conn, $teacher_id, $mark_id) {
    $teacher_id = (int)$teacher_id;
    $mark_id = (int)$mark_id;

    if ($teacher_id <= 0 || $mark_id <= 0) {
        return false;
    }

    $result = $conn->query("SELECT mark_id FROM marks WHERE mark_id = $mark_id AND teacher_id = $teacher_id LIMIT 1");
    return $result && $result->num_rows > 0;
}

function teacherCanTeachSubjectAndGrade($conn, $teacher_id, $subject_id, $student_id) {
    $teacher_id = (int)$teacher_id;
    $subject_id = (int)$subject_id;
    $student_id = (int)$student_id;

    $sql = "SELECT t.assigned_grade, s.grade
            FROM teachers t
            JOIN students s ON s.student_id = $student_id
            JOIN teacher_subjects ts ON ts.teacher_id = t.teacher_id AND ts.subject_id = $subject_id
            WHERE t.teacher_id = $teacher_id
            LIMIT 1";

    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        return false;
    }

    $row = $result->fetch_assoc();
    return gradesMatch($row['assigned_grade'], $row['grade']);
}

$teacher_scope = $is_teacher ? getTeacherScope($conn, $session_teacher_id) : null;
$teacher_grade = $teacher_scope ? normalizeGradeLabel($teacher_scope['assigned_grade']) : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrfToken();

    if ($is_teacher && (!$teacher_scope || $teacher_grade === '')) {
        header('Location: marks.php?error=' . urlencode('Your teacher account is not linked to a valid grade assignment'));
        exit();
    }

    if (isset($_POST['add_mark'])) {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $teacher_id = $is_teacher ? $session_teacher_id : (int)($_POST['teacher_id'] ?? 0);
        $score = (int)($_POST['score'] ?? -1);

        if ($score < 0 || $score > 100) {
            header('Location: marks.php?error=' . urlencode('Score must be between 0 and 100'));
            exit();
        }

        if (!teacherCanTeachSubjectAndGrade($conn, $teacher_id, $subject_id, $student_id)) {
            header('Location: marks.php?error=' . urlencode('Teacher must be assigned to both this subject and student grade'));
            exit();
        }

        $existing = $conn->query("SELECT mark_id FROM marks WHERE student_id=$student_id AND subject_id=$subject_id");
        if ($existing && $existing->num_rows > 0) {
            header('Location: marks.php?error=' . urlencode('Mark already exists for this student and subject'));
            exit();
        }

        $sql = "INSERT INTO marks (student_id, subject_id, teacher_id, score)
                VALUES ($student_id, $subject_id, $teacher_id, $score)";
        $conn->query($sql);
        header('Location: marks.php?success=' . urlencode('Mark added successfully'));
        exit();
    }

    if (isset($_POST['edit_mark'])) {
        $mark_id = (int)($_POST['mark_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $teacher_id = $is_teacher ? $session_teacher_id : (int)($_POST['teacher_id'] ?? 0);
        $score = (int)($_POST['score'] ?? -1);

        if ($score < 0 || $score > 100) {
            header('Location: marks.php?error=' . urlencode('Score must be between 0 and 100'));
            exit();
        }

        if ($is_teacher && !teacherOwnsMark($conn, $session_teacher_id, $mark_id)) {
            header('Location: marks.php?error=' . urlencode('You can edit only marks entered under your teacher account'));
            exit();
        }

        if (!teacherCanTeachSubjectAndGrade($conn, $teacher_id, $subject_id, $student_id)) {
            header('Location: marks.php?error=' . urlencode('Teacher must be assigned to both this subject and student grade'));
            exit();
        }

        $existing = $conn->query("SELECT mark_id FROM marks WHERE student_id=$student_id AND subject_id=$subject_id AND mark_id != $mark_id");
        if ($existing && $existing->num_rows > 0) {
            header('Location: marks.php?error=' . urlencode('Another mark already exists for this student and subject'));
            exit();
        }

        $sql = "UPDATE marks SET student_id=$student_id, subject_id=$subject_id,
                teacher_id=$teacher_id, score=$score WHERE mark_id=$mark_id";
        $conn->query($sql);
        header('Location: marks.php?success=' . urlencode('Mark updated successfully'));
        exit();
    }
}

if (isset($_GET['delete'])) {
    requireValidCsrfToken();
    $mark_id = (int)$_GET['delete'];

    if ($is_teacher && !teacherOwnsMark($conn, $session_teacher_id, $mark_id)) {
        header('Location: marks.php?error=' . urlencode('You can delete only marks entered under your teacher account'));
        exit();
    }

    $conn->query("DELETE FROM marks WHERE mark_id=$mark_id");
    header('Location: marks.php?success=' . urlencode('Mark deleted successfully'));
    exit();
}

// Get mark data for editing
$edit_mark = null;
if (isset($_GET['edit'])) {
    $mark_id = (int)$_GET['edit'];

    if ($is_teacher && !teacherOwnsMark($conn, $session_teacher_id, $mark_id)) {
        header('Location: marks.php?error=' . urlencode('You can edit only marks entered under your teacher account'));
        exit();
    }

    $result = $conn->query("SELECT * FROM marks WHERE mark_id=$mark_id");
    $edit_mark = $result ? $result->fetch_assoc() : null;
}

// Build dropdown data
$student_rows = [];
$student_query = $conn->query('SELECT student_id, name, grade FROM students ORDER BY name');
if ($student_query) {
    while ($student = $student_query->fetch_assoc()) {
        if ($is_teacher && ($teacher_grade === '' || !gradesMatch($student['grade'], $teacher_grade))) {
            continue;
        }

        $student_rows[] = $student;
    }
}

$subject_rows = [];
if ($is_teacher) {
    $subject_query = $conn->query("SELECT s.subject_id, s.subject_name
                                  FROM subjects s
                                  INNER JOIN teacher_subjects ts ON ts.subject_id = s.subject_id
                                  WHERE ts.teacher_id = $session_teacher_id
                                  ORDER BY s.subject_name");
} else {
    $subject_query = $conn->query('SELECT subject_id, subject_name FROM subjects ORDER BY subject_name');
}
if ($subject_query) {
    while ($subject = $subject_query->fetch_assoc()) {
        $subject_rows[] = $subject;
    }
}

$teacher_rows = [];
$teachers_sql = "SELECT t.teacher_id, t.teacher_name, t.assigned_grade,
                         COALESCE(GROUP_CONCAT(DISTINCT ts.subject_id ORDER BY ts.subject_id SEPARATOR ','), '') AS subject_ids,
                         COALESCE(GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ', '), 'No subjects assigned') AS subjects_taught
                         FROM teachers t
                         LEFT JOIN teacher_subjects ts ON t.teacher_id = ts.teacher_id
                         LEFT JOIN subjects s ON ts.subject_id = s.subject_id";
if ($is_teacher) {
    $teachers_sql .= " WHERE t.teacher_id = $session_teacher_id";
}
$teachers_sql .= " GROUP BY t.teacher_id, t.teacher_name, t.assigned_grade ORDER BY t.teacher_name";
$teacher_query = $conn->query($teachers_sql);
if ($teacher_query) {
    while ($teacher = $teacher_query->fetch_assoc()) {
        $teacher_rows[] = $teacher;
    }
}

$marks_sql = "SELECT m.*, s.name as student_name, s.grade,
                     sub.subject_name, t.teacher_name
              FROM marks m
              JOIN students s ON m.student_id = s.student_id
              JOIN subjects sub ON m.subject_id = sub.subject_id
              JOIN teachers t ON m.teacher_id = t.teacher_id";
if ($is_teacher) {
    $marks_sql .= " WHERE m.teacher_id = $session_teacher_id";
}
$marks_sql .= " ORDER BY s.grade, s.name, sub.subject_name";
$marks = $conn->query($marks_sql);

$marks_by_grade = [];
if ($marks) {
    while ($mark = $marks->fetch_assoc()) {
        $grade_label = normalizeGradeLabel($mark['grade']);
        if ($grade_label === '') {
            $grade_label = 'Unassigned Grade';
        }

        $marks_by_grade[$grade_label][] = $mark;
    }
}

$teacher_display = '';
if ($is_teacher && $teacher_scope) {
    $teacher_display = $teacher_scope['teacher_name'] . ' (' . $teacher_scope['subjects_taught'] . ' | ' . $teacher_grade . ')';
}

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') : '';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';
$info_message = '';
$page_title = $is_teacher ? 'My Student Marks' : 'Mark Entry';

if ($is_teacher) {
    if ($teacher_scope && $teacher_grade !== '') {
        $info_message = 'You can record marks only for ' . $teacher_grade . ' students in your assigned subjects.';
    } else {
        $error_message = $error_message !== ''
            ? $error_message
            : 'Your teacher account is not linked to an assigned grade. Contact admin.';
    }
}

$csrf_token = urlencode(getCsrfToken());
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Entry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
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
                    <?php if ($is_admin): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="teachers.php">Teachers</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="marks.php">Marks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="summary.php">Summary</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">Reports</a>
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

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($info_message): ?>
            <div class="alert alert-info" role="alert">
                <?php echo htmlspecialchars($info_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <?php echo $edit_mark ? 'Edit Mark' : 'Enter New Mark'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="markForm">
                            <?php csrfInput(); ?>
                            <?php if ($edit_mark): ?>
                                <input type="hidden" name="mark_id" value="<?php echo $edit_mark['mark_id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student</label>
                                <select class="form-control" id="student_id" name="student_id" required>
                                    <option value="">Select Student</option>
                                    <?php foreach ($student_rows as $student): ?>
                                        <?php $student_grade = normalizeGradeLabel($student['grade']); ?>
                                        <option value="<?php echo (int)$student['student_id']; ?>"
                                                data-grade="<?php echo htmlspecialchars($student_grade, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo $edit_mark && (int)$edit_mark['student_id'] === (int)$student['student_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['name'] . ' - ' . $student_grade, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="subject_id" class="form-label">Subject</label>
                                <select class="form-control" id="subject_id" name="subject_id" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subject_rows as $subject): ?>
                                        <option value="<?php echo (int)$subject['subject_id']; ?>"
                                                <?php echo $edit_mark && (int)$edit_mark['subject_id'] === (int)$subject['subject_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="teacher_id" class="form-label">Teacher</label>
                                <?php if ($is_teacher): ?>
                                    <input type="hidden" name="teacher_id" value="<?php echo $session_teacher_id; ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($teacher_display, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                    <small id="teacherHint" class="text-muted">Marks are saved under your teacher account only.</small>
                                <?php else: ?>
                                    <select class="form-control" id="teacher_id" name="teacher_id" required>
                                        <option value="">Select Teacher</option>
                                        <?php foreach ($teacher_rows as $teacher): ?>
                                            <?php $teacher_grade = normalizeGradeLabel($teacher['assigned_grade']); ?>
                                            <option value="<?php echo (int)$teacher['teacher_id']; ?>"
                                                    data-subjects="<?php echo htmlspecialchars($teacher['subject_ids'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-grade="<?php echo htmlspecialchars($teacher_grade, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo $edit_mark && (int)$edit_mark['teacher_id'] === (int)$teacher['teacher_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($teacher['teacher_name'] . ' (' . $teacher['subjects_taught'] . ' | ' . $teacher_grade . ')', ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small id="teacherHint" class="text-muted">Teacher must match selected subject and student grade/class.</small>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="score" class="form-label">Score (0-100)</label>
                                <input type="number" class="form-control" id="score" name="score"
                                       value="<?php echo $edit_mark ? (int)$edit_mark['score'] : ''; ?>"
                                       min="0" max="100" required>
                                <small class="text-muted">Pass mark is 50</small>
                            </div>

                            <button type="submit" class="btn btn-primary" name="<?php echo $edit_mark ? 'edit_mark' : 'add_mark'; ?>">
                                <?php echo $edit_mark ? 'Update Mark' : 'Add Mark'; ?>
                            </button>
                            <?php if ($edit_mark): ?>
                                <a href="marks.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <?php if (empty($marks_by_grade)): ?>
                    <div class="card">
                        <div class="card-body">
                            <p class="mb-0 text-muted"><?php echo $is_teacher ? 'No marks found for your assigned students.' : 'No marks found.'; ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($marks_by_grade as $grade_label => $grade_marks): ?>
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($grade_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="badge bg-primary"><?php echo count($grade_marks); ?> Marks</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Subject</th>
                                                <th>Teacher</th>
                                                <th>Score</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($grade_marks as $mark): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($mark['student_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($mark['subject_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($mark['teacher_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <?php echo (int)$mark['score']; ?>
                                                        <?php if ($mark['score'] >= 50): ?>
                                                            <span class="badge bg-success">Pass</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Fail</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($mark['score'] >= 50): ?>
                                                            <span class="pass">PASS</span>
                                                        <?php else: ?>
                                                            <span class="fail">FAIL</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="marks.php?edit=<?php echo (int)$mark['mark_id']; ?>"
                                                           class="btn btn-sm btn-warning">Edit</a>
                                                        <a href="marks.php?delete=<?php echo (int)$mark['mark_id']; ?>&csrf_token=<?php echo $csrf_token; ?>"
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Are you sure you want to delete this mark?')">Delete</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $footer_base_path = '../';
    include __DIR__ . '/../includes/footer.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterTeachersBySubjectAndGrade() {
            var subjectSelect = document.getElementById('subject_id');
            var studentSelect = document.getElementById('student_id');
            var teacherSelect = document.getElementById('teacher_id');
            var teacherHint = document.getElementById('teacherHint');

            if (!teacherSelect || !teacherHint) {
                return;
            }

            var subjectId = subjectSelect.value;
            var studentOption = studentSelect.options[studentSelect.selectedIndex];
            var studentGrade = studentOption ? (studentOption.getAttribute('data-grade') || '') : '';
            var hasVisibleTeacher = false;

            for (var i = 0; i < teacherSelect.options.length; i++) {
                var option = teacherSelect.options[i];

                if (i === 0) {
                    option.hidden = false;
                    continue;
                }

                var subjectIds = option.getAttribute('data-subjects') || '';
                var allowedSubjectIds = subjectIds ? subjectIds.split(',') : [];
                var teacherGrade = option.getAttribute('data-grade') || '';

                var canTeachSubject = subjectId === '' ? true : allowedSubjectIds.indexOf(subjectId) !== -1;
                var canTeachGrade = studentGrade === '' ? true : (teacherGrade === studentGrade);
                var canTeach = canTeachSubject && canTeachGrade;

                option.hidden = !canTeach;
                if (!canTeach && option.selected) {
                    option.selected = false;
                }

                if (canTeach) {
                    hasVisibleTeacher = true;
                }
            }

            if ((subjectId !== '' || studentGrade !== '') && !hasVisibleTeacher) {
                teacherHint.textContent = 'No teacher matches this subject + class. Assign the correct teacher grade/subject first.';
                teacherHint.className = 'text-danger';
            } else {
                teacherHint.textContent = 'Teacher must match selected subject and student grade/class.';
                teacherHint.className = 'text-muted';
            }
        }

        document.getElementById('markForm').addEventListener('submit', function(e) {
            var score = parseInt(document.getElementById('score').value, 10);
            var subjectId = document.getElementById('subject_id').value;
            var studentId = document.getElementById('student_id').value;
            var teacherIdInput = document.getElementById('teacher_id');
            var teacherId = teacherIdInput ? teacherIdInput.value : '<?php echo $is_teacher ? $session_teacher_id : ''; ?>';

            if (isNaN(score) || score < 0 || score > 100) {
                e.preventDefault();
                alert('Score must be between 0 and 100');
                return;
            }

            if (!studentId || !subjectId || !teacherId) {
                e.preventDefault();
                alert('Please select student, subject, and teacher.');
            }
        });

        var teacherSelect = document.getElementById('teacher_id');
        if (teacherSelect) {
            document.getElementById('subject_id').addEventListener('change', filterTeachersBySubjectAndGrade);
            document.getElementById('student_id').addEventListener('change', filterTeachersBySubjectAndGrade);
            filterTeachersBySubjectAndGrade();
        }
    </script>
</body>
</html>
