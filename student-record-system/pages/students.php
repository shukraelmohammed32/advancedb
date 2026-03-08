<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireAnyRole(['admin', 'teacher']);

$db = new Database();
$conn = $db->getConnection();

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

function highSchoolGrades() {
    return ['Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
}

function isHighSchoolGrade($grade) {
    return in_array(normalizeGradeLabel($grade), highSchoolGrades(), true);
}

// Get grade options
$grade_rows = [];
$grade_result = $conn->query('SELECT grade_id, grade_name FROM grades ORDER BY grade_id');
if ($grade_result) {
    $grade_order = array_flip(highSchoolGrades());
    while ($grade_row = $grade_result->fetch_assoc()) {
        $grade_name = normalizeGradeLabel($grade_row['grade_name']);
        if (!isset($grade_order[$grade_name])) {
            continue;
        }

        $grade_rows[$grade_order[$grade_name]] = $grade_row;
    }

    ksort($grade_rows);
    $grade_rows = array_values($grade_rows);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrfToken();

    $name = $conn->real_escape_string($_POST['name']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $selected_grade = normalizeGradeLabel($_POST['grade'] ?? '');
    $academic_year = $conn->real_escape_string($_POST['academic_year']);
    $semester = $conn->real_escape_string($_POST['semester']);

    if ($selected_grade === '') {
        header('Location: students.php?error=' . urlencode('Please select a grade'));
        exit();
    }

    if (!isHighSchoolGrade($selected_grade)) {
        header('Location: students.php?error=' . urlencode('Only high school grades 9 to 12 are allowed'));
        exit();
    }

    $grade_name_safe = $conn->real_escape_string($selected_grade);
    $grade_lookup = $conn->query("SELECT grade_id, grade_name FROM grades WHERE grade_name = '$grade_name_safe' LIMIT 1");
    if (!$grade_lookup || $grade_lookup->num_rows === 0) {
        header('Location: students.php?error=' . urlencode('Selected grade is invalid'));
        exit();
    }

    $grade_record = $grade_lookup->fetch_assoc();
    $grade_id = (int)$grade_record['grade_id'];
    $grade_name = $conn->real_escape_string($grade_record['grade_name']);

    if (isset($_POST['add_student'])) {
        $sql = "INSERT INTO students (name, gender, grade, grade_id, academic_year, semester)
                VALUES ('$name', '$gender', '$grade_name', $grade_id, '$academic_year', '$semester')";
        $conn->query($sql);
        header('Location: students.php?success=' . urlencode('Student added successfully'));
        exit();
    }

    if (isset($_POST['edit_student'])) {
        $student_id = (int)$_POST['student_id'];

        $sql = "UPDATE students SET name='$name', gender='$gender', grade='$grade_name', grade_id=$grade_id,
                academic_year='$academic_year', semester='$semester'
                WHERE student_id=$student_id";
        $conn->query($sql);
        header('Location: students.php?success=' . urlencode('Student updated successfully'));
        exit();
    }
}

// Handle delete action (CSRF protected)
if (isset($_GET['delete'])) {
    requireValidCsrfToken();
    $student_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM students WHERE student_id=$student_id");
    header('Location: students.php?success=' . urlencode('Student deleted successfully'));
    exit();
}

// Get student data for editing
$edit_student = null;
if (isset($_GET['edit'])) {
    $student_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM students WHERE student_id=$student_id");
    $edit_student = $result ? $result->fetch_assoc() : null;
}

// Get all students grouped by grade/class
$students = $conn->query('SELECT * FROM students ORDER BY grade_id ASC, grade ASC, name ASC');
$students_by_grade = [];
if ($students) {
    while ($student = $students->fetch_assoc()) {
        $grade_label = normalizeGradeLabel($student['grade']);
        if ($grade_label === '') {
            $grade_label = 'Unassigned Grade';
        }

        if (!isset($students_by_grade[$grade_label])) {
            $students_by_grade[$grade_label] = [];
        }

        $students_by_grade[$grade_label][] = $student;
    }
}

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') : '';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';
$csrf_token = urlencode(getCsrfToken());
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
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
                        <a class="nav-link active" href="students.php">Students</a>
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
                        <a class="nav-link" href="summary.php">Summary</a>
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
                <h1 class="mb-4">Student Management</h1>
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

        <div class="row">
            <!-- Add/Edit Student Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <?php echo $edit_student ? 'Edit Student' : 'Add New Student'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php csrfInput(); ?>
                            <?php if ($edit_student): ?>
                                <input type="hidden" name="student_id" value="<?php echo $edit_student['student_id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo $edit_student ? htmlspecialchars($edit_student['name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-control" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo $edit_student && $edit_student['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $edit_student && $edit_student['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="grade" class="form-label">Grade</label>
                                <select class="form-control" id="grade" name="grade" required>
                                    <option value="">Select Grade</option>
                                    <?php
                                    $current_grade = $edit_student ? normalizeGradeLabel($edit_student['grade']) : '';
                                    foreach ($grade_rows as $grade_row):
                                        $grade_name = normalizeGradeLabel($grade_row['grade_name']);
                                        $is_selected = strtolower($grade_name) === strtolower($current_grade);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($grade_name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($grade_name, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="academic_year" class="form-label">Academic Year</label>
                                <input type="text" class="form-control" id="academic_year" name="academic_year"
                                       value="<?php echo $edit_student ? htmlspecialchars($edit_student['academic_year'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-control" id="semester" name="semester" required>
                                    <option value="">Select Semester</option>
                                    <option value="First" <?php echo $edit_student && $edit_student['semester'] === 'First' ? 'selected' : ''; ?>>First</option>
                                    <option value="Second" <?php echo $edit_student && $edit_student['semester'] === 'Second' ? 'selected' : ''; ?>>Second</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary" name="<?php echo $edit_student ? 'edit_student' : 'add_student'; ?>">
                                <?php echo $edit_student ? 'Update Student' : 'Add Student'; ?>
                            </button>
                            <?php if ($edit_student): ?>
                                <a href="students.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Students List Grouped by Grade -->
            <div class="col-md-8">
                <?php if (empty($students_by_grade)): ?>
                    <div class="card">
                        <div class="card-body">
                            <p class="mb-0 text-muted">No students found.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($students_by_grade as $grade_label => $grade_students): ?>
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($grade_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="badge bg-primary"><?php echo count($grade_students); ?> Students</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Gender</th>
                                                <th>Academic Year</th>
                                                <th>Semester</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($grade_students as $student): ?>
                                                <tr>
                                                    <td><?php echo $student['student_id']; ?></td>
                                                    <td><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($student['gender'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($student['academic_year'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($student['semester'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <a href="students.php?edit=<?php echo $student['student_id']; ?>"
                                                           class="btn btn-sm btn-warning">Edit</a>
                                                        <a href="students.php?delete=<?php echo $student['student_id']; ?>&csrf_token=<?php echo $csrf_token; ?>"
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Are you sure you want to delete this student?')">Delete</a>
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
</body>
</html>


