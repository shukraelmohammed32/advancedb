<?php
session_start();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        $error = "Database connection failed. Please check if the database is imported.";
    } else {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        
        $stmt = $conn->prepare("SELECT u.*, s.name as student_name, t.teacher_name 
                               FROM users u 
                               LEFT JOIN students s ON u.student_id = s.student_id 
                               LEFT JOIN teachers t ON u.teacher_id = t.teacher_id 
                               WHERE u.username = ? AND u.is_active = 1");
        
        if ($stmt === false) {
            $error = "Database query failed. Please check if users table exists.";
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['student_id'] = $user['student_id'];
                    $_SESSION['teacher_id'] = $user['teacher_id'];
                    $_SESSION['display_name'] = $user['student_name'] ?: $user['teacher_name'] ?: $user['username'];
                    
                    header("Location: ../index.php");
                    exit();
                } else {
                    $error = "Invalid password";
                }
            } else {
                $error = "Invalid username or account not active";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Record System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0 text-center">Student Record System</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            Default Admin: admin / admin123
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
