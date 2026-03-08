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

function normalizeSubjectIds($raw_subject_ids) {
    $subject_ids = [];

    if (is_array($raw_subject_ids)) {
        foreach ($raw_subject_ids as $raw_id) {
            $subject_id = (int)$raw_id;
            if ($subject_id > 0) {
                $subject_ids[$subject_id] = $subject_id;
            }
        }
    }

    return array_values($subject_ids);
}

function saveTeacherSubjects($conn, $teacher_id, $subject_ids) {
    if (!$conn->query("DELETE FROM teacher_subjects WHERE teacher_id = $teacher_id")) {
        return false;
    }

    foreach ($subject_ids as $subject_id) {
        if (!$conn->query("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES ($teacher_id, $subject_id)")) {
            return false;
        }
    }

    return true;
}

function getDepartmentLabel($conn, $subject_ids) {
    if (count($subject_ids) === 0) {
        return '';
    }

    if (count($subject_ids) > 1) {
        return 'Multiple Subjects';
    }

    $subject_id = (int)$subject_ids[0];
    $result = $conn->query("SELECT subject_name FROM subjects WHERE subject_id = $subject_id");

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['subject_name'];
    }

    return 'General';
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

    $teacher_name = $conn->real_escape_string($_POST['teacher_name']);
    $assigned_grade_input = normalizeGradeLabel($_POST['assigned_grade'] ?? '');
    $is_homeroom = isset($_POST['is_homeroom']) ? 1 : 0;
    $subject_ids = normalizeSubjectIds($_POST['subject_ids'] ?? []);

    if (count($subject_ids) === 0) {
        header('Location: teachers.php?error=' . urlencode('Please assign at least one subject'));
        exit();
    }

    if ($assigned_grade_input === '') {
        header('Location: teachers.php?error=' . urlencode('Please select assigned grade'));
        exit();
    }

    if (!isHighSchoolGrade($assigned_grade_input)) {
        header('Location: teachers.php?error=' . urlencode('Only high school grades 9 to 12 are allowed'));
        exit();
    }

    $grade_safe = $conn->real_escape_string($assigned_grade_input);
    $grade_lookup = $conn->query("SELECT grade_name FROM grades WHERE grade_name = '$grade_safe' LIMIT 1");
    if (!$grade_lookup || $grade_lookup->num_rows === 0) {
        header('Location: teachers.php?error=' . urlencode('Selected grade is invalid'));
        exit();
    }

    $assigned_grade = $conn->real_escape_string($grade_lookup->fetch_assoc()['grade_name']);

    if (isset($_POST['add_teacher'])) {
        if ($is_homeroom) {
            $conn->query("UPDATE teachers SET is_homeroom = 0 WHERE assigned_grade = '$assigned_grade'");
        }

        $department = $conn->real_escape_string(getDepartmentLabel($conn, $subject_ids));

        $conn->begin_transaction();

        $sql = "INSERT INTO teachers (teacher_name, department, assigned_grade, is_homeroom)
                VALUES ('$teacher_name', '$department', '$assigned_grade', $is_homeroom)";

        if (!$conn->query($sql)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Error adding teacher'));
            exit();
        }

        $teacher_id = (int)$conn->insert_id;

        if (!saveTeacherSubjects($conn, $teacher_id, $subject_ids)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Teacher saved but subjects failed to save'));
            exit();
        }

        $conn->commit();
        header('Location: teachers.php?success=' . urlencode('Teacher added successfully'));
        exit();
    }

    if (isset($_POST['edit_teacher'])) {
        $teacher_id = (int)$_POST['teacher_id'];

        if ($is_homeroom) {
            $conn->query("UPDATE teachers SET is_homeroom = 0 WHERE assigned_grade = '$assigned_grade' AND teacher_id != $teacher_id");
        }

        $department = $conn->real_escape_string(getDepartmentLabel($conn, $subject_ids));

        $conn->begin_transaction();

        $sql = "UPDATE teachers SET teacher_name='$teacher_name', department='$department',
                assigned_grade='$assigned_grade', is_homeroom=$is_homeroom
                WHERE teacher_id=$teacher_id";

        if (!$conn->query($sql)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Error updating teacher'));
            exit();
        }

        if (!saveTeacherSubjects($conn, $teacher_id, $subject_ids)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Teacher updated but subjects failed to save'));
            exit();
        }

        $conn->commit();
        header('Location: teachers.php?success=' . urlencode('Teacher updated successfully'));
        exit();
    }
}

if (isset($_GET['delete'])) {
    requireValidCsrfToken();
    $teacher_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM teachers WHERE teacher_id=$teacher_id");
    header('Location: teachers.php?success=' . urlencode('Teacher deleted successfully'));
    exit();
}

// Get teacher data for editing
$edit_teacher = null;
$selected_subject_ids = [];
if (isset($_GET['edit'])) {
    $teacher_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM teachers WHERE teacher_id=$teacher_id");
    $edit_teacher = $result ? $result->fetch_assoc() : null;

    if ($edit_teacher) {
        $subject_result = $conn->query("SELECT subject_id FROM teacher_subjects WHERE teacher_id=$teacher_id");
        if ($subject_result) {
            while ($subject_row = $subject_result->fetch_assoc()) {
                $selected_subject_ids[] = (int)$subject_row['subject_id'];
            }
        }

        // Legacy fallback for older records that only used department
        if (count($selected_subject_ids) === 0 && !empty($edit_teacher['department'])) {
            $department = $conn->real_escape_string($edit_teacher['department']);
            $legacy_subject = $conn->query("SELECT subject_id FROM subjects WHERE subject_name = '$department'");
            if ($legacy_subject && $legacy_subject->num_rows > 0) {
                $legacy_row = $legacy_subject->fetch_assoc();
                $selected_subject_ids[] = (int)$legacy_row['subject_id'];
            }
        }
    }
}

// Get all subjects
$subject_rows = [];
$subjects_result = $conn->query('SELECT subject_id, subject_name FROM subjects ORDER BY subject_name');
if ($subjects_result) {
    while ($subject = $subjects_result->fetch_assoc()) {
        $subject_rows[] = $subject;
    }
}

// Get all teachers with their assigned subjects
$teachers = $conn->query("SELECT t.*,
                         COALESCE(GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ', '), t.department) AS subjects_taught
                         FROM teachers t
                         LEFT JOIN teacher_subjects ts ON t.teacher_id = ts.teacher_id
                         LEFT JOIN subjects s ON ts.subject_id = s.subject_id
                         GROUP BY t.teacher_id
                         ORDER BY t.teacher_name");

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') : '';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';
$csrf_token = urlencode(getCsrfToken());
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management</title>
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
                        <a class="nav-link active" href="teachers.php">Teachers</a>
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
                <h1 class="mb-4">Teacher Management</h1>
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
            <!-- Add/Edit Teacher Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <?php echo $edit_teacher ? 'Edit Teacher' : 'Add New Teacher'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php csrfInput(); ?>
                            <?php if ($edit_teacher): ?>
                                <input type="hidden" name="teacher_id" value="<?php echo $edit_teacher['teacher_id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="teacher_name" class="form-label">Teacher Name</label>
                                <input type="text" class="form-control" id="teacher_name" name="teacher_name"
                                       value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['teacher_name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="subject_ids" class="form-label">Subjects Taught</label>
                                <select class="form-control" id="subject_ids" name="subject_ids[]" multiple size="6" required>
                                    <?php foreach ($subject_rows as $subject): ?>
                                        <option value="<?php echo $subject['subject_id']; ?>"
                                                <?php echo in_array((int)$subject['subject_id'], $selected_subject_ids, true) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple subjects.</small>
                            </div>

                            <div class="mb-3">
                                <label for="assigned_grade" class="form-label">Assigned Grade</label>
                                <select class="form-control" id="assigned_grade" name="assigned_grade" required>
                                    <option value="">Select Grade</option>
                                    <?php
                                    $current_assigned_grade = $edit_teacher ? normalizeGradeLabel($edit_teacher['assigned_grade']) : '';
                                    foreach ($grade_rows as $grade_row):
                                        $grade_name = normalizeGradeLabel($grade_row['grade_name']);
                                        $is_selected = strtolower($grade_name) === strtolower($current_assigned_grade);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($grade_name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($grade_name, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_homeroom" name="is_homeroom"
                                           <?php echo $edit_teacher && $edit_teacher['is_homeroom'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_homeroom">
                                        Homeroom Teacher
                                    </label>
                                </div>
                                <small class="text-muted">Only one homeroom teacher per grade</small>
                            </div>

                            <button type="submit" class="btn btn-primary" name="<?php echo $edit_teacher ? 'edit_teacher' : 'add_teacher'; ?>">
                                <?php echo $edit_teacher ? 'Update Teacher' : 'Add Teacher'; ?>
                            </button>
                            <?php if ($edit_teacher): ?>
                                <a href="teachers.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Teachers List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        Teachers List
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Subjects</th>
                                        <th>Assigned Grade</th>
                                        <th>Homeroom</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $teacher['teacher_id']; ?></td>
                                            <td><?php echo htmlspecialchars($teacher['teacher_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['subjects_taught'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(normalizeGradeLabel($teacher['assigned_grade']), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php if ($teacher['is_homeroom']): ?>
                                                    <span class="badge bg-success">Yes</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                                            <td>
                                                <a href="teachers.php?edit=<?php echo $teacher['teacher_id']; ?>"
                                                   class="btn btn-sm btn-warning">Edit</a>
                                                <a href="teachers.php?delete=<?php echo $teacher['teacher_id']; ?>&csrf_token=<?php echo $csrf_token; ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Are you sure you want to delete this teacher?')">Delete</a>
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

    
    <?php
    $footer_base_path = '../';
    include __DIR__ . '/../includes/footer.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


