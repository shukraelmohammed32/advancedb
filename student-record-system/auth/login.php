<?php
session_start();
require_once '../config/database.php';

$error = '';
$login_input = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn) {
        $error = "Database connection failed. Please check if the database is imported.";
    } else {
        $login_input = trim((string)($_POST['username'] ?? ''));
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT u.*, s.name as student_name, t.teacher_name, t.is_homeroom, t.assigned_grade
                               FROM users u
                               LEFT JOIN students s ON u.student_id = s.student_id
                               LEFT JOIN teachers t ON u.teacher_id = t.teacher_id
                               WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1");

        if ($stmt === false) {
            $error = "Database query failed. Please check if users table exists.";
        } else {
            $stmt->bind_param("ss", $login_input, $login_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['student_id'] = $user['student_id'];
                    $_SESSION['teacher_id'] = $user['teacher_id'];
                    $_SESSION['is_homeroom'] = (int)($user['is_homeroom'] ?? 0);
                    $_SESSION['assigned_grade'] = $user['assigned_grade'] ?? null;
                    $_SESSION['display_name'] = $user['student_name'] ?: $user['teacher_name'] ?: $user['username'];

                    header("Location: ../index.php");
                    exit();
                } else {
                    $error = "Invalid password";
                }
            } else {
                $error = "Invalid username/email or account not active";
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
    <title>Sign In - Student Record System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        .auth-page {
            min-height: 100vh;
            padding-top: 0;
            background:
                radial-gradient(circle at top left, rgba(114, 168, 255, 0.34), transparent 34%),
                radial-gradient(circle at 82% 18%, rgba(61, 219, 179, 0.22), transparent 24%),
                linear-gradient(145deg, #081c33 0%, #123b63 42%, #1c6588 100%);
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 32px 18px;
        }

        .login-container::before {
            content: "";
            position: absolute;
            inset: auto auto -20% -10%;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.22) 0%, rgba(255, 255, 255, 0) 68%);
            filter: blur(8px);
        }

        .login-container::after {
            content: "";
            position: absolute;
            inset: 7% -8% auto auto;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(115, 255, 221, 0.22) 0%, rgba(115, 255, 221, 0) 72%);
            filter: blur(10px);
        }

        .login-card {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(360px, 0.92fr);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(14px);
            border-radius: 28px;
            box-shadow: 0 32px 80px rgba(5, 16, 31, 0.34);
            border: 1px solid rgba(255, 255, 255, 0.18);
            max-width: 1020px;
            width: 100%;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .login-showcase {
            position: relative;
            padding: 52px 46px;
            color: white;
            background:
                radial-gradient(circle at top right, rgba(143, 235, 255, 0.28), transparent 34%),
                linear-gradient(155deg, rgba(8, 29, 52, 0.95) 0%, rgba(17, 67, 112, 0.94) 48%, rgba(15, 110, 122, 0.92) 100%);
        }

        .login-showcase::before {
            content: "";
            position: absolute;
            inset: auto -40px -80px auto;
            width: 220px;
            height: 220px;
            border-radius: 32px;
            background: rgba(255, 255, 255, 0.08);
            transform: rotate(18deg);
        }

        .showcase-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #e4f7ff;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
        }

        .showcase-title {
            margin: 26px 0 16px;
            font-size: clamp(2.2rem, 3vw, 3.2rem);
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .showcase-copy {
            max-width: 480px;
            margin: 0 0 28px;
            font-size: 1rem;
            line-height: 1.7;
            color: rgba(236, 247, 255, 0.84);
        }

        .showcase-highlights {
            display: grid;
            gap: 14px;
            margin-bottom: 30px;
        }

        .showcase-item {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .showcase-item-icon {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: #0d395d;
            background: linear-gradient(145deg, #d5efff 0%, #f7fdff 100%);
        }

        .showcase-item h3 {
            margin: 0 0 4px;
            font-size: 1rem;
            font-weight: 700;
        }

        .showcase-item p {
            margin: 0;
            font-size: 0.92rem;
            line-height: 1.6;
            color: rgba(233, 246, 255, 0.8);
        }

        .showcase-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .showcase-stat {
            padding: 16px 18px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.11);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .showcase-stat strong {
            display: block;
            margin-bottom: 6px;
            font-size: 1.4rem;
            font-weight: 800;
            color: #ffffff;
        }

        .showcase-stat span {
            display: block;
            font-size: 0.84rem;
            line-height: 1.45;
            color: rgba(229, 244, 255, 0.76);
        }

        .login-panel {
            padding: 46px 42px 38px;
            background: linear-gradient(180deg, rgba(250, 253, 255, 0.98) 0%, rgba(240, 247, 253, 0.96) 100%);
        }

        .login-header {
            margin-bottom: 28px;
        }

        .login-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #1b6287;
            background: rgba(31, 95, 149, 0.1);
        }

        .login-header h2 {
            margin: 0 0 10px;
            font-weight: 800;
            font-size: clamp(1.9rem, 2.6vw, 2.4rem);
            color: #102f4d;
            letter-spacing: -0.04em;
        }

        .login-header p {
            margin: 0;
            color: #597085;
            line-height: 1.7;
            font-size: 0.97rem;
        }

        .alert {
            border-radius: 16px;
            border: 1px solid rgba(193, 57, 57, 0.16);
            margin-bottom: 22px;
            padding: 14px 16px;
            background: rgba(255, 238, 238, 0.94);
            color: #973131;
            box-shadow: none;
        }

        .login-form {
            display: grid;
            gap: 18px;
        }

        .form-group {
            display: grid;
            gap: 8px;
        }

        .form-label {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 700;
            color: #163d61;
        }

        .form-control {
            min-height: 56px;
            border: 1px solid #d7e3ef;
            border-radius: 16px;
            padding: 14px 16px;
            transition: all 0.25s ease;
            background: rgba(255, 255, 255, 0.92);
            color: #17324a;
        }

        .form-control::placeholder {
            color: #8ea0b2;
        }

        .form-control:focus {
            border-color: #2e7bbd;
            box-shadow: 0 0 0 0.24rem rgba(31, 95, 149, 0.14);
            background: #ffffff;
        }

        .form-note {
            margin: -4px 0 0;
            font-size: 0.82rem;
            color: #6c8194;
        }

        .btn-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 56px;
            margin-top: 6px;
            background: linear-gradient(135deg, #144675 0%, #1f6da2 50%, #25a288 100%);
            border: none;
            border-radius: 18px;
            padding: 14px 24px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: white;
            width: 100%;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(22, 79, 128, 0.28);
            color: white;
        }

        .btn-login::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0));
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .btn-login:hover::before {
            opacity: 1;
        }

        .btn-login span {
            position: relative;
            z-index: 1;
        }

        .login-help {
            display: grid;
            gap: 14px;
            margin-top: 24px;
        }

        .login-demo,
        .login-support {
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(19, 59, 99, 0.08);
            background: rgba(255, 255, 255, 0.8);
        }

        .login-demo strong,
        .login-support strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.92rem;
            font-weight: 700;
            color: #153d63;
        }

        .login-demo p,
        .login-support p {
            margin: 0;
            font-size: 0.88rem;
            line-height: 1.6;
            color: #61778b;
        }

        .credential-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.83rem;
            font-weight: 700;
            color: #114a76;
            background: #e7f4ff;
        }

        .login-support a {
            color: #1f5f95;
            text-decoration: none;
            font-weight: 700;
        }

        .login-support a:hover {
            text-decoration: underline;
        }

        @media (max-width: 991px) {
            .login-card {
                grid-template-columns: 1fr;
                max-width: 680px;
            }

            .login-showcase {
                padding: 42px 32px 34px;
            }

            .login-panel {
                padding: 36px 32px 34px;
            }
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 18px 12px;
            }

            .login-showcase,
            .login-panel {
                padding-left: 22px;
                padding-right: 22px;
            }

            .showcase-stats {
                grid-template-columns: 1fr;
            }

            .showcase-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-showcase">
                <span class="showcase-badge">Student Record System</span>
                <h1 class="showcase-title">A cleaner portal for students, teachers, and school staff.</h1>
                <p class="showcase-copy">
                    Access academic records, monitor performance, and manage school operations from one secure dashboard.
                </p>

                <div class="showcase-highlights">
                    <div class="showcase-item">
                        <span class="showcase-item-icon">01</span>
                        <div>
                            <h3>Fast sign-in flow</h3>
                            <p>Simple access for administrators, teachers, and students with one shared portal.</p>
                        </div>
                    </div>
                    <div class="showcase-item">
                        <span class="showcase-item-icon">02</span>
                        <div>
                            <h3>Organized records</h3>
                            <p>Keep grades, teacher data, and student details easy to review and update.</p>
                        </div>
                    </div>
                    <div class="showcase-item">
                        <span class="showcase-item-icon">03</span>
                        <div>
                            <h3>School-ready design</h3>
                            <p>A stronger visual style with a clearer layout and more readable form controls.</p>
                        </div>
                    </div>
                </div>

                <div class="showcase-stats">
                    <div class="showcase-stat">
                        <strong>3</strong>
                        <span>user roles supported in one system</span>
                    </div>
                    <div class="showcase-stat">
                        <strong>24/7</strong>
                        <span>access to records and reports</span>
                    </div>
                    <div class="showcase-stat">
                        <strong>1</strong>
                        <span>dashboard to manage everything faster</span>
                    </div>
                </div>
            </div>

            <div class="login-panel">
                <div class="login-header">
                    <span class="login-eyebrow">Portal Access</span>
                    <h2>Sign in to continue</h2>
                    <p>Enter your account details to open the student record management dashboard.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label class="form-label" for="username">Username or Email</label>
                        <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            placeholder="Enter your username or email"
                            value="<?php echo htmlspecialchars($login_input, ENT_QUOTES, 'UTF-8'); ?>"
                            autocomplete="username"
                            required
                        >
                        <p class="form-note">Use the account provided by your school administrator.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-login">
                        <span>Sign In</span>
                    </button>
                </form>

                <div class="login-help">
                    <div class="login-demo">
                        <strong>Default admin account</strong>
                        <p>Use this only for first-time system access and replace it after setup.</p>
                        <span class="credential-pill">admin / admin123</span>
                    </div>

                    <div class="login-support">
                        <strong>Need help?</strong>
                        <p>Contact your system administrator or <a href="#">support team</a> if you cannot access your account.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

