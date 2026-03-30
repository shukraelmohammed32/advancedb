<?php
require_once '../config/landing_redirect.php';

$legacyLoginQuery = [];
foreach (['timeout', 'logged_out', 'auth_required', 'login_error', 'login_identity', 'login_role'] as $key) {
    $value = $_GET[$key] ?? '';
    if ($value !== '') {
        $legacyLoginQuery[$key] = (string)$value;
    }
}

redirectToPublicLanding($legacyLoginQuery);

$error = '';
$login_input = '';

$databaseName = getenv('DB_DATABASE') ?: 'student_record_system';
$isBranchPortal = is_string($databaseName) && stripos($databaseName, 'branch') !== false;
$siteName = getenv('SITE_NAME') ?: ($isBranchPortal ? 'Branch Campus' : 'Main Campus');
$adminUsername = getenv('ADMIN_USERNAME') ?: 'admin';
$adminPassword = getenv('ADMIN_PASSWORD') ?: 'admin123';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    $login_input = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? 'student');

    if (!$conn) {
        $error = 'Database connection failed. Please try again.';
    } else {
        $stmt = $conn->prepare("SELECT u.*, s.name AS student_name, t.teacher_name 
                               FROM users u
                               LEFT JOIN students s ON u.student_id = s.student_id
                               LEFT JOIN teachers t ON u.teacher_id = t.teacher_id
                               WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1");

        if ($stmt === false) {
            $error = 'Database query failed. Please contact administrator.';
        } else {
            $stmt->bind_param('ss', $login_input, $login_input);
            $stmt->execute();
            $user = dbStatementFetchOneAssoc($stmt);

            if ($user !== null) {

                if (password_verify($password, $user['password'])) {
                    $displayName = $user['student_name'] ?: $user['teacher_name'] ?: $user['username'];
                    
                    session_regenerate_id(true);
                    $_SESSION = [];
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['display_name'] = $displayName;
                    $_SESSION['site_name'] = $siteName;

                    // Redirect based on role
                    switch ($user['role']) {
                        case 'admin':
                            header('Location: ../index.php');
                            break;
                        case 'teacher':
                            header('Location: ../pages/teachers.php');
                            break;
                        case 'student':
                            header('Location: ../pages/students.php');
                            break;
                        default:
                            header('Location: ../index.php');
                    }
                    exit();
                } else {
                    $error = 'Invalid password. Please try again.';
                }
            } else {
                $error = 'Invalid username/email or account not active.';
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Academic Record Management System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #7e8ba3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Background with education theme */
        .bg-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>'),
                radial-gradient(circle at 20% 20%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.08) 0%, transparent 50%),
                radial-gradient(circle at 40% 60%, rgba(255,255,255,0.05) 0%, transparent 50%);
            opacity: 0.3;
            animation: floatPattern 30s ease-in-out infinite;
        }

        @keyframes floatPattern {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(-20px, -10px) rotate(1deg); }
            66% { transform: translate(10px, -20px) rotate(-1deg); }
        }

        /* Floating education icons */
        .floating-icons {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .floating-icon {
            position: absolute;
            font-size: 2rem;
            color: rgba(255, 255, 255, 0.1);
            animation: float 20s ease-in-out infinite;
        }

        .floating-icon:nth-child(1) {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
            animation-duration: 25s;
        }

        .floating-icon:nth-child(2) {
            top: 20%;
            right: 15%;
            animation-delay: 5s;
            animation-duration: 30s;
        }

        .floating-icon:nth-child(3) {
            bottom: 20%;
            left: 15%;
            animation-delay: 10s;
            animation-duration: 35s;
        }

        .floating-icon:nth-child(4) {
            bottom: 10%;
            right: 10%;
            animation-delay: 15s;
            animation-duration: 40s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            position: relative;
            z-index: 10;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
        }

        .login-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #7e8ba3 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .school-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .system-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .system-subtitle {
            font-size: 0.85rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2a5298;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #1e3c72;
            box-shadow: 0 0 0 0.2rem rgba(30, 60, 114, 0.25);
            background: white;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 1rem;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon .form-control {
            padding-left: 45px;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #7e8ba3 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(30, 60, 114, 0.4);
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #f5c6cb;
        }

        .extra-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .extra-links a {
            color: #1e3c72;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .extra-links a:hover {
            color: #7e8ba3;
            text-decoration: underline;
        }

        .site-info {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e3c72;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .login-container {
                margin: 20px;
                max-width: 100%;
            }
            
            .login-body {
                padding: 30px 20px;
            }
            
            .site-info {
                position: static;
                margin-bottom: 20px;
                text-align: center;
            }
        }

        @media (max-width: 400px) {
            .login-header {
                padding: 25px 20px;
            }
            
            .school-logo {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .system-title {
                font-size: 1rem;
            }
            
            .system-subtitle {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Background Pattern -->
    <div class="bg-pattern"></div>
    
    <!-- Floating Education Icons -->
    <div class="floating-icons">
        <div class="floating-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="floating-icon"><i class="fas fa-book"></i></div>
        <div class="floating-icon"><i class="fas fa-award"></i></div>
        <div class="floating-icon"><i class="fas fa-user-graduate"></i></div>
    </div>

    <!-- Site Information -->
    <div class="site-info">
        <i class="fas fa-map-marker-alt me-2"></i>
        <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
    </div>

    <!-- Login Card -->
    <div class="login-container">
        <!-- Header with Logo and Title -->
        <div class="login-header">
            <div class="school-logo">
                <i class="fas fa-school"></i>
            </div>
            <h1 class="system-title">Student Academic Record</h1>
            <p class="system-subtitle">Management System</p>
        </div>

        <!-- Login Form -->
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <!-- Email/Username Field -->
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-envelope me-2"></i>Email or Username
                    </label>
                    <div class="input-with-icon">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($login_input, ENT_QUOTES, 'UTF-8'); ?>" 
                               placeholder="Enter your email or username" 
                               required>
                    </div>
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <div class="input-with-icon">
                        <i class="fas fa-key input-icon"></i>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password" 
                               required>
                    </div>
                </div>

                <!-- Role Selection -->
                <div class="form-group">
                    <label for="role" class="form-label">
                        <i class="fas fa-user-tag me-2"></i>Select Your Role
                    </label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="">-- Select Role --</option>
                        <option value="super_admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'super_admin') ? 'selected' : ''; ?>>
                            <i class="fas fa-user-shield me-2"></i>Super Admin
                        </option>
                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>
                            <i class="fas fa-user-tie me-2"></i>Branch Admin
                        </option>
                        <option value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'selected' : ''; ?>>
                            <i class="fas fa-chalkboard-teacher me-2"></i>Teacher
                        </option>
                        <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>
                            <i class="fas fa-user-graduate me-2"></i>Student
                        </option>
                    </select>
                </div>

                <!-- Login Button -->
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Sign In
                </button>
            </form>

            <!-- Extra Links -->
            <div class="extra-links">
                <a href="login.php">
                    <i class="fas fa-arrow-left me-1"></i>
                    Back to main sign-in
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on username field
            document.getElementById('username').focus();
            
            // Add ripple effect to button
            const loginBtn = document.querySelector('.btn-login');
            loginBtn.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.5)';
                ripple.style.width = ripple.style.height = '40px';
                ripple.style.top = (e.clientY - e.target.offsetTop - 20) + 'px';
                ripple.style.left = (e.clientX - e.target.offsetLeft - 20) + 'px';
                ripple.style.animation = 'ripple 0.6s ease-out';
                ripple.style.pointerEvents = 'none';
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                0% {
                    transform: scale(0);
                    opacity: 1;
                }
                100% {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
