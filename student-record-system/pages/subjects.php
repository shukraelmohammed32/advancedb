<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireLogin();
if (!canAccessSubjects()) {
    $_SESSION['error'] = 'Only admin and teachers can access subjects.';
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$is_admin = canManageSubjects();
$is_teacher = hasRole('teacher');
$session_teacher_id = $is_teacher ? (int)($_SESSION['teacher_id'] ?? 0) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrfToken();

    if (!canManageSubjects()) {
        header('Location: subjects.php?error=' . urlencode('Only admin can manage subject definitions'));
        exit();
    }

    if (isset($_POST['add_subject'])) {
        $subject_name = $conn->real_escape_string(trim((string)($_POST['subject_name'] ?? '')));
        $total_mark = (int)($_POST['total_mark'] ?? 0);

        if ($subject_name === '' || $total_mark <= 0) {
            header('Location: subjects.php?error=' . urlencode('Please enter a valid subject and total mark'));
            exit();
        }

        $sql = "INSERT INTO subjects (subject_name, total_mark)
                VALUES ('$subject_name', $total_mark)";
        $conn->query($sql);
        header('Location: subjects.php?success=' . urlencode('Subject added successfully'));
        exit();
    }

    if (isset($_POST['edit_subject'])) {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $subject_name = $conn->real_escape_string(trim((string)($_POST['subject_name'] ?? '')));
        $total_mark = (int)($_POST['total_mark'] ?? 0);

        if ($subject_id <= 0 || $subject_name === '' || $total_mark <= 0) {
            header('Location: subjects.php?error=' . urlencode('Please enter a valid subject and total mark'));
            exit();
        }

        $sql = "UPDATE subjects SET subject_name='$subject_name', total_mark=$total_mark
                WHERE subject_id=$subject_id";
        $conn->query($sql);
        header('Location: subjects.php?success=' . urlencode('Subject updated successfully'));
        exit();
    }
}

if (isset($_GET['delete'])) {
    requireValidCsrfToken();

    if (!canManageSubjects()) {
        header('Location: subjects.php?error=' . urlencode('Only admin can delete subjects'));
        exit();
    }

    $subject_id = (int)$_GET['delete'];

    $conn->begin_transaction();

    // Remove marks and teacher assignments for this subject before deleting it
    $conn->query("DELETE FROM marks WHERE subject_id = $subject_id");
    $conn->query("DELETE FROM teacher_subjects WHERE subject_id = $subject_id");

    if (!$conn->query("DELETE FROM subjects WHERE subject_id = $subject_id")) {
        $conn->rollback();
        header('Location: subjects.php?error=' . urlencode('Failed to delete subject. Please try again.'));
        exit();
    }

    $conn->commit();
    header('Location: subjects.php?success=' . urlencode('Subject and related marks deleted successfully'));
    exit();
}

$edit_subject = null;
if ($is_admin && isset($_GET['edit'])) {
    $subject_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM subjects WHERE subject_id=$subject_id");
    $edit_subject = $result ? $result->fetch_assoc() : null;
}

if ($is_admin) {
    $subjects = $conn->query('SELECT * FROM subjects ORDER BY subject_name');
} else {
    $subjects = $conn->query("SELECT s.*
                              FROM subjects s
                              INNER JOIN teacher_subjects ts ON ts.subject_id = s.subject_id
                              WHERE ts.teacher_id = $session_teacher_id
                              ORDER BY s.subject_name");
}

$page_title = $is_admin ? 'Subject Management' : (isHomeroomTeacher() ? 'Homeroom Subject Assignments' : 'My Subject Assignments');
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') : '';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';
$info_message = $is_admin
    ? 'Administrators define subjects, grades, and academic structure for the school.'
    : 'You can review only the subjects assigned to your teacher account. Only admin can add, update, or delete subject definitions.';
$csrf_token = urlencode(getCsrfToken());
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLanguageTag(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
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
                    <li class="nav-item">
                        <a class="nav-link active" href="subjects.php"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php if (hasRole('admin')): ?>
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
                    <?php if (canViewStudentReports()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php"><?php echo htmlspecialchars(t('Reports'), ENT_QUOTES, 'UTF-8'); ?></a>
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
            <?php if ($is_admin): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <?php echo $edit_subject ? 'Edit Subject' : 'Add New Subject'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php csrfInput(); ?>
                            <?php if ($edit_subject): ?>
                                <input type="hidden" name="subject_id" value="<?php echo (int)$edit_subject['subject_id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="subject_name" class="form-label">Subject Name</label>
                                <input type="text" class="form-control" id="subject_name" name="subject_name"
                                       value="<?php echo $edit_subject ? htmlspecialchars($edit_subject['subject_name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="total_mark" class="form-label">Total Mark</label>
                                <input type="number" class="form-control" id="total_mark" name="total_mark"
                                       value="<?php echo $edit_subject ? (int)$edit_subject['total_mark'] : 100; ?>"
                                       min="1" max="100" required>
                            </div>

                            <button type="submit" class="btn btn-primary" name="<?php echo $edit_subject ? 'edit_subject' : 'add_subject'; ?>">
                                <?php echo $edit_subject ? 'Update Subject' : 'Add Subject'; ?>
                            </button>
                            <?php if ($edit_subject): ?>
                                <a href="subjects.php" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="<?php echo $is_admin ? 'col-md-8' : 'col-12'; ?>">
                <div class="card">
                    <div class="card-header">
                        <?php echo $is_admin ? 'Subjects List' : 'Assigned Subjects'; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Subject Name</th>
                                        <th>Total Mark</th>
                                        <th>Created At</th>
                                        <?php if ($is_admin): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($subjects && $subjects->num_rows > 0): ?>
                                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo (int)$subject['subject_id']; ?></td>
                                                <td><?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo (int)$subject['total_mark']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($subject['created_at'])); ?></td>
                                                <?php if ($is_admin): ?>
                                                <td>
                                                    <a href="subjects.php?edit=<?php echo (int)$subject['subject_id']; ?>"
                                                       class="btn btn-sm btn-warning">Edit</a>
                                                    <a href="subjects.php?delete=<?php echo (int)$subject['subject_id']; ?>&csrf_token=<?php echo $csrf_token; ?>"
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Are you sure you want to delete this subject? This will also delete all related marks.')">Delete</a>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?php echo $is_admin ? '5' : '4'; ?>" class="text-center text-muted py-4">
                                                <?php echo $is_admin ? 'No subjects found.' : 'No subjects are assigned to your teacher account yet.'; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
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
