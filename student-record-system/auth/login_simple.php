<?php
require_once '../config/session.php';
startAppSession();
require_once '../config/database.php';

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
    $role = (string)($_POST['role'] ?? 'admin');

    if (!$conn) {
        $error = 'Database connection failed';
    } else {
        $stmt = $conn->prepare("SELECT u.*, s.name AS student_name, t.teacher_name 
                               FROM users u
                               LEFT JOIN students s ON u.student_id = s.student_id
                               LEFT JOIN teachers t ON u.teacher_id = t.teacher_id
                               WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1");

        if ($stmt === false) {
            $error = 'Database query failed';
        } else {
            $stmt->bind_param('ss', $login_input, $login_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

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

                    header('Location: ../index.php');
                    exit();
                } else {
                    $error = 'Invalid password';
                }
            } else {
                $error = 'Invalid username or password';
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
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: flex;
            min-height: 500px;
        }
        
        .login-left {
            background: linear-gradient(135deg, #144675 0%, #1f6da2 50%, #25a288 100%);
            color: white;
            padding: 40px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-right {
            padding: 40px;
            flex: 1;
        }
        
        .login-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            opacity: 0.9;
            margin-bottom: 30px;
        }
        
        .site-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 20px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 1px solid #ddd;
            padding: 12px 16px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .role-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .role-btn {
            flex: 1;
            padding: 10px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .role-btn:hover {
            border-color: #667eea;
        }
        
        .role-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .login-features {
            margin-top: 30px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .feature-icon {
            width: 30px;
            height: 30px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 400px;
            }
            
            .login-left {
                padding: 30px;
            }
            
            .login-right {
                padding: 30px;
            }
            
            .role-selector {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="site-badge"><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></div>
            <h1 class="login-title">Welcome Back</h1>
            <p class="login-subtitle">Sign in to access your dashboard</p>
            
            <div class="login-features">
                <div class="feature-item">
                    <div class="feature-icon">📚</div>
                    <span>View reports & marks</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">👥</div>
                    <span>Manage students</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">📊</div>
                    <span>Track progress</span>
                </div>
            </div>
        </div>
        
        <div class="login-right">
            <form method="POST">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                
                <div class="role-selector">
                    <div class="role-btn active" data-role="admin">
                        <div>👨‍💼</div>
                        <small>Admin</small>
                    </div>
                    <div class="role-btn" data-role="teacher">
                        <div>👨‍🏫</div>
                        <small>Teacher</small>
                    </div>
                    <div class="role-btn" data-role="student">
                        <div>👨‍🎓</div>
                        <small>Student</small>
                    </div>
                </div>
                
                <input type="hidden" name="role" id="selectedRole" value="admin">
                
                <div class="mb-3">
                    <label class="form-label">Username or Email</label>
                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($login_input, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter your username" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn btn-login">Sign In</button>
                
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        Default: <?php echo htmlspecialchars($adminUsername, ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($adminPassword, ENT_QUOTES, 'UTF-8'); ?>
                    </small>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.role-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('selectedRole').value = this.dataset.role;
            });
        });
    </script>
</body>
</html>
