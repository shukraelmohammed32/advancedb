<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_student'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $gender = $conn->real_escape_string($_POST['gender']);
        $grade = $conn->real_escape_string($_POST['grade']);
        $academic_year = $conn->real_escape_string($_POST['academic_year']);
        $semester = $conn->real_escape_string($_POST['semester']);
        
        $sql = "INSERT INTO students (name, gender, grade, academic_year, semester) 
                VALUES ('$name', '$gender', '$grade', '$academic_year', '$semester')";
        $conn->query($sql);
        header("Location: students.php?success=Student added successfully");
        exit();
    }
    
    if (isset($_POST['edit_student'])) {
        $student_id = (int)$_POST['student_id'];
        $name = $conn->real_escape_string($_POST['name']);
        $gender = $conn->real_escape_string($_POST['gender']);
        $grade = $conn->real_escape_string($_POST['grade']);
        $academic_year = $conn->real_escape_string($_POST['academic_year']);
        $semester = $conn->real_escape_string($_POST['semester']);
        
        $sql = "UPDATE students SET name='$name', gender='$gender', grade='$grade', 
                academic_year='$academic_year', semester='$semester' 
                WHERE student_id=$student_id";
        $conn->query($sql);
        header("Location: students.php?success=Student updated successfully");
        exit();
    }
    
    if (isset($_GET['delete'])) {
        $student_id = (int)$_GET['delete'];
        $conn->query("DELETE FROM students WHERE student_id=$student_id");
        header("Location: students.php?success=Student deleted successfully");
        exit();
    }
}

// Get student data for editing
$edit_student = null;
if (isset($_GET['edit'])) {
    $student_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM students WHERE student_id=$student_id");
    $edit_student = $result->fetch_assoc();
}

// Get all students
$students = $conn->query("SELECT * FROM students ORDER BY created_at DESC");
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

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_GET['success']; ?>
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
                            <?php if ($edit_student): ?>
                                <input type="hidden" name="student_id" value="<?php echo $edit_student['student_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo $edit_student ? $edit_student['name'] : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-control" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo $edit_student && $edit_student['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $edit_student && $edit_student['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="grade" class="form-label">Grade</label>
                                <input type="text" class="form-control" id="grade" name="grade" 
                                       value="<?php echo $edit_student ? $edit_student['grade'] : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="academic_year" class="form-label">Academic Year</label>
                                <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                       value="<?php echo $edit_student ? $edit_student['academic_year'] : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-control" id="semester" name="semester" required>
                                    <option value="">Select Semester</option>
                                    <option value="First" <?php echo $edit_student && $edit_student['semester'] == 'First' ? 'selected' : ''; ?>>First</option>
                                    <option value="Second" <?php echo $edit_student && $edit_student['semester'] == 'Second' ? 'selected' : ''; ?>>Second</option>
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

            <!-- Students List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        Students List
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Gender</th>
                                        <th>Grade</th>
                                        <th>Academic Year</th>
                                        <th>Semester</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $students->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $student['student_id']; ?></td>
                                            <td><?php echo $student['name']; ?></td>
                                            <td><?php echo $student['gender']; ?></td>
                                            <td><?php echo $student['grade']; ?></td>
                                            <td><?php echo $student['academic_year']; ?></td>
                                            <td><?php echo $student['semester']; ?></td>
                                            <td>
                                                <a href="students.php?edit=<?php echo $student['student_id']; ?>" 
                                                   class="btn btn-sm btn-warning">Edit</a>
                                                <a href="students.php?delete=<?php echo $student['student_id']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this student?')">Delete</a>
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