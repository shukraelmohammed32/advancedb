<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_teacher'])) {
        $teacher_name = $conn->real_escape_string($_POST['teacher_name']);
        $department = $conn->real_escape_string($_POST['department']);
        $assigned_grade = $conn->real_escape_string($_POST['assigned_grade']);
        $is_homeroom = isset($_POST['is_homeroom']) ? 1 : 0;
        
        // If setting as homeroom teacher, unset previous homeroom teacher for that grade
        if ($is_homeroom) {
            $conn->query("UPDATE teachers SET is_homeroom = 0 WHERE assigned_grade = '$assigned_grade'");
        }
        
        $sql = "INSERT INTO teachers (teacher_name, department, assigned_grade, is_homeroom) 
                VALUES ('$teacher_name', '$department', '$assigned_grade', $is_homeroom)";
        $conn->query($sql);
        header("Location: teachers.php?success=Teacher added successfully");
        exit();
    }
    
    if (isset($_POST['edit_teacher'])) {
        $teacher_id = (int)$_POST['teacher_id'];
        $teacher_name = $conn->real_escape_string($_POST['teacher_name']);
        $department = $conn->real_escape_string($_POST['department']);
        $assigned_grade = $conn->real_escape_string($_POST['assigned_grade']);
        $is_homeroom = isset($_POST['is_homeroom']) ? 1 : 0;
        
        // If setting as homeroom teacher, unset previous homeroom teacher for that grade
        if ($is_homeroom) {
            $conn->query("UPDATE teachers SET is_homeroom = 0 WHERE assigned_grade = '$assigned_grade' AND teacher_id != $teacher_id");
        }
        
        $sql = "UPDATE teachers SET teacher_name='$teacher_name', department='$department', 
                assigned_grade='$assigned_grade', is_homeroom=$is_homeroom 
                WHERE teacher_id=$teacher_id";
        $conn->query($sql);
        header("Location: teachers.php?success=Teacher updated successfully");
        exit();
    }
    
    if (isset($_GET['delete'])) {
        $teacher_id = (int)$_GET['delete'];
        $conn->query("DELETE FROM teachers WHERE teacher_id=$teacher_id");
        header("Location: teachers.php?success=Teacher deleted successfully");
        exit();
    }
}

// Get teacher data for editing
$edit_teacher = null;
if (isset($_GET['edit'])) {
    $teacher_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM teachers WHERE teacher_id=$teacher_id");
    $edit_teacher = $result->fetch_assoc();
}

// Get all subjects for department dropdown
$subjects = $conn->query("SELECT subject_name FROM subjects ORDER BY subject_name");

// Get all teachers
$teachers = $conn->query("SELECT * FROM teachers ORDER BY teacher_name");
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

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_GET['success']; ?>
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
                            <?php if ($edit_teacher): ?>
                                <input type="hidden" name="teacher_id" value="<?php echo $edit_teacher['teacher_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="teacher_name" class="form-label">Teacher Name</label>
                                <input type="text" class="form-control" id="teacher_name" name="teacher_name" 
                                       value="<?php echo $edit_teacher ? $edit_teacher['teacher_name'] : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="department" class="form-label">Department (Subject)</label>
                                <select class="form-control" id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                                        <option value="<?php echo $subject['subject_name']; ?>" 
                                                <?php echo $edit_teacher && $edit_teacher['department'] == $subject['subject_name'] ? 'selected' : ''; ?>>
                                            <?php echo $subject['subject_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="assigned_grade" class="form-label">Assigned Grade</label>
                                <input type="text" class="form-control" id="assigned_grade" name="assigned_grade" 
                                       value="<?php echo $edit_teacher ? $edit_teacher['assigned_grade'] : ''; ?>" required>
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
                                        <th>Department</th>
                                        <th>Assigned Grade</th>
                                        <th>Homeroom</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Reset the subjects result set for reuse
                                    $subjects->data_seek(0);
                                    while ($teacher = $teachers->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo $teacher['teacher_id']; ?></td>
                                            <td><?php echo $teacher['teacher_name']; ?></td>
                                            <td><?php echo $teacher['department']; ?></td>
                                            <td><?php echo $teacher['assigned_grade']; ?></td>
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
                                                <a href="teachers.php?delete=<?php echo $teacher['teacher_id']; ?>" 
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>