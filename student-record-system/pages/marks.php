<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

function teacherCanTeachSubject($conn, $teacher_id, $subject_id) {
    $teacher_id = (int)$teacher_id;
    $subject_id = (int)$subject_id;

    $result = $conn->query("SELECT 1 FROM teacher_subjects WHERE teacher_id = $teacher_id AND subject_id = $subject_id LIMIT 1");
    return $result && $result->num_rows > 0;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_mark'])) {
        $student_id = (int)$_POST['student_id'];
        $subject_id = (int)$_POST['subject_id'];
        $teacher_id = (int)$_POST['teacher_id'];
        $score = (int)$_POST['score'];

        if ($score < 0 || $score > 100) {
            header("Location: marks.php?error=Score must be between 0 and 100");
            exit();
        }

        if (!teacherCanTeachSubject($conn, $teacher_id, $subject_id)) {
            header("Location: marks.php?error=Selected teacher is not assigned to this subject");
            exit();
        }

        // Check if mark already exists for this student and subject
        $existing = $conn->query("SELECT mark_id FROM marks WHERE student_id=$student_id AND subject_id=$subject_id");

        if ($existing && $existing->num_rows > 0) {
            header("Location: marks.php?error=Mark already exists for this student and subject");
            exit();
        }

        $sql = "INSERT INTO marks (student_id, subject_id, teacher_id, score)
                VALUES ($student_id, $subject_id, $teacher_id, $score)";
        $conn->query($sql);
        header("Location: marks.php?success=Mark added successfully");
        exit();
    }

    if (isset($_POST['edit_mark'])) {
        $mark_id = (int)$_POST['mark_id'];
        $student_id = (int)$_POST['student_id'];
        $subject_id = (int)$_POST['subject_id'];
        $teacher_id = (int)$_POST['teacher_id'];
        $score = (int)$_POST['score'];

        if ($score < 0 || $score > 100) {
            header("Location: marks.php?error=Score must be between 0 and 100");
            exit();
        }

        if (!teacherCanTeachSubject($conn, $teacher_id, $subject_id)) {
            header("Location: marks.php?error=Selected teacher is not assigned to this subject");
            exit();
        }

        $existing = $conn->query("SELECT mark_id FROM marks WHERE student_id=$student_id AND subject_id=$subject_id AND mark_id != $mark_id");
        if ($existing && $existing->num_rows > 0) {
            header("Location: marks.php?error=Another mark already exists for this student and subject");
            exit();
        }

        $sql = "UPDATE marks SET student_id=$student_id, subject_id=$subject_id,
                teacher_id=$teacher_id, score=$score WHERE mark_id=$mark_id";
        $conn->query($sql);
        header("Location: marks.php?success=Mark updated successfully");
        exit();
    }
}

if (isset($_GET['delete'])) {
    $mark_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM marks WHERE mark_id=$mark_id");
    header("Location: marks.php?success=Mark deleted successfully");
    exit();
}

// Get mark data for editing
$edit_mark = null;
if (isset($_GET['edit'])) {
    $mark_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM marks WHERE mark_id=$mark_id");
    $edit_mark = $result ? $result->fetch_assoc() : null;
}

// Get dropdown data
$students = $conn->query("SELECT student_id, name, grade FROM students ORDER BY name");
$subjects = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
$teachers = $conn->query("SELECT t.teacher_id, t.teacher_name,
                         COALESCE(GROUP_CONCAT(DISTINCT ts.subject_id ORDER BY ts.subject_id SEPARATOR ','), '') AS subject_ids,
                         COALESCE(GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ', '), 'No subjects assigned') AS subjects_taught
                         FROM teachers t
                         LEFT JOIN teacher_subjects ts ON t.teacher_id = ts.teacher_id
                         LEFT JOIN subjects s ON ts.subject_id = s.subject_id
                         GROUP BY t.teacher_id, t.teacher_name
                         ORDER BY t.teacher_name");

// Get all marks with student, subject, and teacher names
$marks = $conn->query("SELECT m.*, s.name as student_name, s.grade,
                      sub.subject_name, t.teacher_name
                      FROM marks m
                      JOIN students s ON m.student_id = s.student_id
                      JOIN subjects sub ON m.subject_id = sub.subject_id
                      JOIN teachers t ON m.teacher_id = t.teacher_id
                      ORDER BY s.name, sub.subject_name");
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
    <!-- Navigation -->
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
                    <li class="nav-item">
                        <a class="nav-link" href="teachers.php">Teachers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="marks.php">Marks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">Reports</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Mark Entry</h1>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_GET['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_GET['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add/Edit Mark Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <?php echo $edit_mark ? 'Edit Mark' : 'Enter New Mark'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="markForm">
                            <?php if ($edit_mark): ?>
                                <input type="hidden" name="mark_id" value="<?php echo $edit_mark['mark_id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student</label>
                                <select class="form-control" id="student_id" name="student_id" required>
                                    <option value="">Select Student</option>
                                    <?php while ($student = $students->fetch_assoc()): ?>
                                        <option value="<?php echo $student['student_id']; ?>"
                                                <?php echo $edit_mark && $edit_mark['student_id'] == $student['student_id'] ? 'selected' : ''; ?>>
                                            <?php echo $student['name'] . ' - Grade ' . $student['grade']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="subject_id" class="form-label">Subject</label>
                                <select class="form-control" id="subject_id" name="subject_id" required>
                                    <option value="">Select Subject</option>
                                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                                        <option value="<?php echo $subject['subject_id']; ?>"
                                                <?php echo $edit_mark && $edit_mark['subject_id'] == $subject['subject_id'] ? 'selected' : ''; ?>>
                                            <?php echo $subject['subject_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="teacher_id" class="form-label">Teacher</label>
                                <select class="form-control" id="teacher_id" name="teacher_id" required>
                                    <option value="">Select Teacher</option>
                                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                        <option value="<?php echo $teacher['teacher_id']; ?>"
                                                data-subjects="<?php echo htmlspecialchars($teacher['subject_ids'], ENT_QUOTES); ?>"
                                                <?php echo $edit_mark && $edit_mark['teacher_id'] == $teacher['teacher_id'] ? 'selected' : ''; ?>>
                                            <?php echo $teacher['teacher_name'] . ' (' . $teacher['subjects_taught'] . ')'; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <small id="teacherHint" class="text-muted">Only teachers assigned to the selected subject are shown.</small>
                            </div>

                            <div class="mb-3">
                                <label for="score" class="form-label">Score (0-100)</label>
                                <input type="number" class="form-control" id="score" name="score"
                                       value="<?php echo $edit_mark ? $edit_mark['score'] : ''; ?>"
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

            <!-- Marks List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        Marks List
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Grade</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($mark = $marks->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $mark['student_name']; ?></td>
                                            <td><?php echo $mark['grade']; ?></td>
                                            <td><?php echo $mark['subject_name']; ?></td>
                                            <td><?php echo $mark['teacher_name']; ?></td>
                                            <td>
                                                <?php echo $mark['score']; ?>
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
                                                <a href="marks.php?edit=<?php echo $mark['mark_id']; ?>"
                                                   class="btn btn-sm btn-warning">Edit</a>
                                                <a href="marks.php?delete=<?php echo $mark['mark_id']; ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Are you sure you want to delete this mark?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterTeachersBySubject() {
            var subjectSelect = document.getElementById('subject_id');
            var teacherSelect = document.getElementById('teacher_id');
            var teacherHint = document.getElementById('teacherHint');
            var subjectId = subjectSelect.value;
            var hasVisibleTeacher = false;

            for (var i = 0; i < teacherSelect.options.length; i++) {
                var option = teacherSelect.options[i];

                // Keep placeholder visible
                if (i === 0) {
                    option.hidden = false;
                    continue;
                }

                var subjectIds = option.getAttribute('data-subjects') || '';
                var allowedSubjectIds = subjectIds ? subjectIds.split(',') : [];
                var canTeach = subjectId === '' ? true : allowedSubjectIds.indexOf(subjectId) !== -1;

                option.hidden = !canTeach;
                if (!canTeach && option.selected) {
                    option.selected = false;
                }

                if (canTeach) {
                    hasVisibleTeacher = true;
                }
            }

            if (subjectId !== '' && !hasVisibleTeacher) {
                teacherHint.textContent = 'No teacher is assigned to this subject yet. Assign subjects in the Teachers page first.';
                teacherHint.className = 'text-danger';
            } else {
                teacherHint.textContent = 'Only teachers assigned to the selected subject are shown.';
                teacherHint.className = 'text-muted';
            }
        }

        // Form validation
        document.getElementById('markForm').addEventListener('submit', function(e) {
            var score = parseInt(document.getElementById('score').value, 10);
            var subjectId = document.getElementById('subject_id').value;
            var teacherId = document.getElementById('teacher_id').value;

            if (isNaN(score) || score < 0 || score > 100) {
                e.preventDefault();
                alert('Score must be between 0 and 100');
                return;
            }

            if (subjectId && !teacherId) {
                e.preventDefault();
                alert('Please select a teacher assigned to the selected subject.');
            }
        });

        document.getElementById('subject_id').addEventListener('change', filterTeachersBySubject);
        filterTeachersBySubject();
    </script>
</body>
</html>
