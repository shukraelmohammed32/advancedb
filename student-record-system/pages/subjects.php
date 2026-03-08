<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireAnyRole(['admin', 'teacher']);

$db = new Database();
$conn = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrfToken();

    if (isset($_POST['add_subject'])) {
        $subject_name = $conn->real_escape_string($_POST['subject_name']);
        $total_mark = (int)$_POST['total_mark'];

        $sql = "INSERT INTO subjects (subject_name, total_mark)
                VALUES ('$subject_name', $total_mark)";
        $conn->query($sql);
        header('Location: subjects.php?success=' . urlencode('Subject added successfully'));
        exit();
    }

    if (isset($_POST['edit_subject'])) {
        $subject_id = (int)$_POST['subject_id'];
        $subject_name = $conn->real_escape_string($_POST['subject_name']);
        $total_mark = (int)$_POST['total_mark'];

        $sql = "UPDATE subjects SET subject_name='$subject_name', total_mark=$total_mark
                WHERE subject_id=$subject_id";
        $conn->query($sql);
        header('Location: subjects.php?success=' . urlencode('Subject updated successfully'));
        exit();
    }
}

// Handle delete action (CSRF protected)
if (isset($_GET['delete'])) {
    requireValidCsrfToken();
    $subject_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM subjects WHERE subject_id=$subject_id");
    header('Location: subjects.php?success=' . urlencode('Subject deleted successfully'));
    exit();
}

// Get subject data for editing
$edit_subject = null;
if (isset($_GET['edit'])) {
    $subject_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM subjects WHERE subject_id=$subject_id");
    $edit_subject = $result ? $result->fetch_assoc() : null;
}

// Get all subjects
$subjects = $conn->query('SELECT * FROM subjects ORDER BY subject_name');

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') : '';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';
$csrf_token = urlencode(getCsrfToken());
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Management</title>
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
                        <a class="nav-link active" href="subjects.php">Subjects</a>
                    </li>
                    <?php if (hasRole('admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="teachers.php">Teachers</a>
                    </li>
                    <?php endif; ?>
                    <?php if (canManageMarks()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="marks.php">Marks</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="summary.php">Summary</a>
                    </li>
                    <?php if (canViewStudentReports()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">Reports</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Subject Management</h1>
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
            <!-- Add/Edit Subject Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <?php echo $edit_subject ? 'Edit Subject' : 'Add New Subject'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php csrfInput(); ?>
                            <?php if ($edit_subject): ?>
                                <input type="hidden" name="subject_id" value="<?php echo $edit_subject['subject_id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="subject_name" class="form-label">Subject Name</label>
                                <input type="text" class="form-control" id="subject_name" name="subject_name"
                                       value="<?php echo $edit_subject ? htmlspecialchars($edit_subject['subject_name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="total_mark" class="form-label">Total Mark</label>
                                <input type="number" class="form-control" id="total_mark" name="total_mark"
                                       value="<?php echo $edit_subject ? (int)$edit_subject['total_mark'] : '100'; ?>"
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

            <!-- Subjects List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        Subjects List
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
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $subject['subject_id']; ?></td>
                                            <td><?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo (int)$subject['total_mark']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($subject['created_at'])); ?></td>
                                            <td>
                                                <a href="subjects.php?edit=<?php echo $subject['subject_id']; ?>"
                                                   class="btn btn-sm btn-warning">Edit</a>
                                                <a href="subjects.php?delete=<?php echo $subject['subject_id']; ?>&csrf_token=<?php echo $csrf_token; ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Are you sure you want to delete this subject? This will also delete all related marks.')">Delete</a>
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




