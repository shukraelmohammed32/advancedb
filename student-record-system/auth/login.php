<?php
require_once '../config/session.php';
startAppSession();
require_once '../config/database.php';

function isBranchPortalDatabase($databaseName) {
    return is_string($databaseName) && stripos($databaseName, 'branch') !== false;
}

function defaultLoginPortal($isBranchPortal) {
    return $isBranchPortal ? 'branch_admin' : 'main_admin';
}

function normalizeLoginPortal($value, $isBranchPortal) {
    $allowed = ['student', 'teacher', 'main_admin', 'branch_admin'];
    return in_array($value, $allowed, true) ? $value : defaultLoginPortal($isBranchPortal);
}


function loginPortalCards($isBranchPortal, $siteName) {
    $portalCards = [
        [
            'key' => 'student',
            'icon' => 'ST',
            'title' => 'Student',
            'tag' => 'View records',
            'description' => 'Access your reports and marks',
        ],
        [
            'key' => 'teacher',
            'icon' => 'TC',
            'title' => 'Teacher',
            'tag' => 'Manage class',
            'description' => 'Enter marks and manage students',
        ],
        [
            'key' => 'main_admin',
            'icon' => 'MA',
            'title' => 'Main Admin',
            'tag' => $isBranchPortal ? 'Main campus' : 'Central control',
            'description' => $isBranchPortal
                ? 'Use main campus portal for full access'
                : 'Control the entire system',
        ],
        [
            'key' => 'branch_admin',
            'icon' => 'BA',
            'title' => 'Branch Admin',
            'tag' => $isBranchPortal ? $siteName : 'Branch portal',
            'description' => $isBranchPortal
                ? 'Manage ' . $siteName . ' campus'
                : 'Use your branch campus portal',
        ],
    ];
    return $portalCards;
}

function loginPortalPresentation($portal, $isBranchPortal, $siteName, $mainAdminUsername, $mainAdminPassword, $branchAdminUsername, $branchAdminPassword) {
    switch ($portal) {
        case 'student':
            return [
                'heading' => 'Student Login',
                'description' => 'Access your reports and marks',
                'summary' => 'View your academic records',
                'submit_label' => 'Student Dashboard',
                'help_title' => 'Student Access',
                'help_copy' => 'Use your school username and password',
                'credential_value' => '',
            ];
        case 'teacher':
            return [
                'heading' => 'Teacher Login',
                'description' => 'Manage your class and enter marks',
                'summary' => 'Access teacher tools',
                'submit_label' => 'Teacher Dashboard',
                'help_title' => 'Teacher Access',
                'help_copy' => 'Use your teacher account provided by admin',
                'credential_value' => '',
            ];
        case 'branch_admin':
            if ($isBranchPortal) {
                return [
                    'heading' => 'Branch Admin Login',
                    'description' => 'Manage ' . $siteName . ' campus',
                    'summary' => 'Branch administrator access',
                    'submit_label' => 'Branch Dashboard',
                    'help_title' => 'Branch Admin',
                    'help_copy' => 'Manage your local campus',
                    'credential_value' => $branchAdminUsername . ' / ' . $branchAdminPassword,
                ];
            }

            return [
                'heading' => 'Branch Admin Login',
                'description' => 'Use your branch campus portal',
                'summary' => 'Branch administrators use local portal',
                'submit_label' => 'Continue',
                'help_title' => 'Branch Access',
                'help_copy' => 'Access from your branch campus URL',
                'credential_value' => '',
            ];
        case 'main_admin':
        default:
            if ($isBranchPortal) {
                return [
                    'heading' => 'Main Admin Login',
                    'description' => 'Use main campus portal',
                    'summary' => 'Main administrators use central portal',
                    'submit_label' => 'Continue',
                    'help_title' => 'Main Campus',
                    'help_copy' => 'This portal is for branch access only',
                    'credential_value' => '',
                ];
            }

            return [
                'heading' => 'Main Admin Login',
                'description' => 'Control the entire system',
                'summary' => 'Central system administrator',
                'submit_label' => 'Admin Dashboard',
                'help_title' => 'System Admin',
                'help_copy' => 'Change password after first login',
                'credential_value' => $mainAdminUsername . ' / ' . $mainAdminPassword,
            ];
    }
}

function selectedPortalMatchesUser($portal, $userRole, $isBranchPortal) {
    if ($portal === 'student') {
        return $userRole === 'student';
    }

    if ($portal === 'teacher') {
        return $userRole === 'teacher';
    }

    if ($portal === 'main_admin') {
        return $userRole === 'admin' && !$isBranchPortal;
    }

    if ($portal === 'branch_admin') {
        return $userRole === 'admin' && $isBranchPortal;
    }

    return false;
}

function portalMismatchMessage($portal, $isBranchPortal, $siteName) {
    switch ($portal) {
        case 'student':
            return 'This login box is only for student accounts.';
        case 'teacher':
            return 'This login box is only for teacher accounts.';
        case 'branch_admin':
            return $isBranchPortal
                ? 'This login box is only for branch administrator accounts in ' . $siteName . '.'
                : 'Branch administrators must sign in from their own branch campus portal.';
        case 'main_admin':
        default:
            return $isBranchPortal
                ? 'Main administrator accounts are not available on the ' . $siteName . ' portal.'
                : 'This login box is only for the main administrator account.';
    }
}

$error = '';
$login_input = '';

$databaseName = getenv('DB_DATABASE') ?: 'student_record_system';
$isBranchPortal = isBranchPortalDatabase($databaseName);
$siteName = getenv('SITE_NAME') ?: ($isBranchPortal ? 'Branch Campus' : 'Main Campus');
$siteCode = getenv('SITE_CODE') ?: ($isBranchPortal ? 'BRANCH' : 'MAIN');
$mainAdminUsername = $isBranchPortal ? 'admin' : (getenv('ADMIN_USERNAME') ?: 'admin');
$mainAdminPassword = $isBranchPortal ? 'admin123' : (getenv('ADMIN_PASSWORD') ?: 'admin123');
$branchAdminUsername = getenv('ADMIN_USERNAME') ?: 'branch_admin';
$branchAdminPassword = getenv('ADMIN_PASSWORD') ?: 'branchadmin123';
$loginPortal = defaultLoginPortal($isBranchPortal);
$portalCards = loginPortalCards($isBranchPortal, $siteName);
$portalPresentations = [];

foreach ($portalCards as $portalCard) {
    $portalPresentations[$portalCard['key']] = loginPortalPresentation(
        $portalCard['key'],
        $isBranchPortal,
        $siteName,
        $mainAdminUsername,
        $mainAdminPassword,
        $branchAdminUsername,
        $branchAdminPassword
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    $loginPortal = normalizeLoginPortal($_POST['login_portal'] ?? '', $isBranchPortal);

    if (!$conn) {
        $error = 'Database connection failed. Please check if the database is imported.';
    } else {
        $login_input = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $stmt = $conn->prepare("SELECT u.*, s.name AS student_name, t.teacher_name, t.is_homeroom, t.assigned_grade
                               FROM users u
                               LEFT JOIN students s ON u.student_id = s.student_id
                               LEFT JOIN teachers t ON u.teacher_id = t.teacher_id
                               WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1");

        if ($stmt === false) {
            $error = 'Database query failed. Please check if users table exists.';
        } else {
            $stmt->bind_param('ss', $login_input, $login_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    if (!selectedPortalMatchesUser($loginPortal, (string)$user['role'], $isBranchPortal)) {
                        $error = portalMismatchMessage($loginPortal, $isBranchPortal, $siteName);
                    } else {
                        $displayName = $user['student_name'] ?: $user['teacher_name'] ?: $user['username'];
                        $adminScope = null;
                        $roleLabelOverride = null;

                        if ($user['role'] === 'admin') {
                            $adminScope = $loginPortal === 'branch_admin' ? 'branch' : 'main';
                            $roleLabelOverride = $loginPortal === 'branch_admin' ? 'Branch Administrator' : 'Main Administrator';
                            if ($displayName === '' || $displayName === null || $displayName === $user['username']) {
                                $displayName = $loginPortal === 'branch_admin' ? 'Branch Admin' : 'Main Admin';
                            }
                        }

                        session_regenerate_id(true);
                        $_SESSION = [];
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['student_id'] = $user['student_id'];
                        $_SESSION['teacher_id'] = $user['teacher_id'];
                        $_SESSION['is_homeroom'] = (int)($user['is_homeroom'] ?? 0);
                        $_SESSION['assigned_grade'] = $user['assigned_grade'] ?? null;
                        $_SESSION['display_name'] = $displayName;
                        $_SESSION['portal_mode'] = $loginPortal;
                        $_SESSION['admin_scope'] = $adminScope;
                        $_SESSION['role_label_override'] = $roleLabelOverride;
                        $_SESSION['site_name'] = $siteName;
                        $_SESSION['site_code'] = $siteCode;
                        $_SESSION['is_branch_portal'] = $isBranchPortal ? 1 : 0;

                        header('Location: ../index.php');
                        exit();
                    }
                } else {
                    $error = 'Invalid password';
                }
            } else {
                $error = 'Invalid username/email or account not active';
            }

            $stmt->close();
        }
    }
}

$currentPortalPresentation = $portalPresentations[$loginPortal];
$portalJson = json_encode($portalPresentations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
$showcaseTitle = $isBranchPortal
    ? 'Smart login for every ' . $siteName . ' role.'
    : 'Smart login for every school role.';
$showcaseCopy = $isBranchPortal
    ? 'Students, teachers, and branch administrators can use one clean portal built for ' . $siteName . '.'
    : 'Students, teachers, and administrators get clearer entry points before they open the dashboard.';
$portalContextLabel = $isBranchPortal ? $siteName . ' Portal' : 'Central Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> Sign In - Student Record System</title>
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
            padding: 20px;
        }

        .login-container::before {
            content: "";
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3), transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3), transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2), transparent 50%);
            animation: floatBackground 20s ease-in-out infinite;
        }

        @keyframes floatBackground {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(-20px, -20px) rotate(120deg); }
            66% { transform: translate(20px, -10px) rotate(240deg); }
        }

        .login-card {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(400px, 0.9fr);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            max-width: 1200px;
            width: 100%;
            position: relative;
            z-index: 1;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
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
            font-size: clamp(1.8rem, 2.5vw, 2.4rem);
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .showcase-copy {
            max-width: 420px;
            margin: 0 0 20px;
            font-size: 0.95rem;
            line-height: 1.5;
            color: rgba(236, 247, 255, 0.84);
        }

        .showcase-highlights {
            display: grid;
            gap: 12px;
            margin-bottom: 20px;
        }

        .showcase-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .showcase-item-icon {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 700;
            color: #0d395d;
            background: linear-gradient(145deg, #d5efff 0%, #f7fdff 100%);
        }

        .showcase-item h3 {
            margin: 0 0 2px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .showcase-item p {
            margin: 0;
            font-size: 0.85rem;
            line-height: 1.4;
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
            padding: 50px 45px 40px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 252, 255, 0.96) 100%);
            position: relative;
        }

        .login-panel::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 0 0 0 100%;
        }

        .login-header {
            margin-bottom: 24px;
        }

        .login-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #18527f;
            background: #e7f4ff;
        }

        .login-header h2 {
            margin: 0 0 10px;
            font-size: 2rem;
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #102f4c;
        }

        .login-header p {
            margin: 0;
            color: #5e7387;
            line-height: 1.7;
        }
        .alert {
            margin-bottom: 18px;
            border-radius: 18px;
            border: none;
        }

        .login-form {
            display: grid;
            gap: 18px;
        }

        .portal-selector {
            padding: 22px;
            border-radius: 24px;
            background: linear-gradient(180deg, #ffffff 0%, #f4f9fd 100%);
            border: 1px solid rgba(19, 59, 99, 0.08);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
        }

        .portal-selector-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 16px;
        }

        .portal-selector-head strong {
            display: block;
            font-size: 0.95rem;
            font-weight: 800;
            color: #153d63;
        }

        .portal-selector-head p {
            margin: 6px 0 0;
            font-size: 0.84rem;
            line-height: 1.55;
            color: #698094;
        }

        .portal-context {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #0f4d70;
            background: #e4f4ff;
            white-space: nowrap;
        }

        .portal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .portal-option {
            width: 100%;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px 16px;
            border-radius: 20px;
            border: 1px solid rgba(19, 59, 99, 0.09);
            background: linear-gradient(180deg, #f7fbff 0%, #ecf5fb 100%);
            color: #163d63;
            text-align: left;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
        }

        .portal-option:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(17, 65, 106, 0.12);
        }

        .portal-option.is-active {
            background: linear-gradient(145deg, #123f68 0%, #1b6f97 52%, #1ea187 100%);
            border-color: transparent;
            box-shadow: 0 18px 34px rgba(17, 65, 106, 0.22);
            color: #ffffff;
        }

        .portal-option.is-active .portal-option-icon {
            background: rgba(255, 255, 255, 0.18);
            color: #ffffff;
        }

        .portal-option.is-active .portal-option-copy small,
        .portal-option.is-active .portal-option-copy span {
            color: rgba(239, 247, 255, 0.82);
        }

        .portal-option-icon {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.86rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: #0f4c73;
            background: #dfeef9;
        }

        .portal-option-copy {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .portal-option-copy strong {
            font-size: 0.94rem;
            font-weight: 800;
        }

        .portal-option-copy small {
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #3f6b8b;
        }

        .portal-option-copy span {
            font-size: 0.82rem;
            line-height: 1.55;
            color: #698094;
        }

        .portal-summary {
            margin-top: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            font-size: 0.84rem;
            line-height: 1.6;
            color: #215378;
            background: #ebf6ff;
            border: 1px solid rgba(31, 95, 149, 0.1);
        }

        .form-group {
            display: grid;
            gap: 8px;
        }

        .form-label {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 700;
            color: #183d61;
        }

        .form-control {
            min-height: 54px;
            border-radius: 16px;
            border: 2px solid rgba(102, 126, 234, 0.1);
            padding: 14px 18px;
            font-size: 1rem;
            color: #1a365d;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .form-control::placeholder {
            color: #93a7b7;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
            background: rgba(255, 255, 255, 1);
            transform: translateY(-2px);
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
            gap: 12px;
            min-height: 56px;
            margin-top: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 16px;
            padding: 16px 24px;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 0.02em;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-login::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:active {
            transform: translateY(-1px);
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

        .credential-pill.is-hidden {
            display: none;
        }

        @media (max-width: 991px) {
            .login-card {
                grid-template-columns: 1fr;
                max-width: 760px;
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

            .showcase-stats,
            .portal-grid {
                grid-template-columns: 1fr;
            }

            .showcase-title {
                font-size: 2rem;
            }

            .portal-selector-head {
                flex-direction: column;
            }

            .portal-context {
                white-space: normal;
            }
        }
    </style>
</head>
<body class="auth-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-showcase">
                <span class="showcase-badge">Student Record System <?php echo $isBranchPortal ? '| ' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') : '| Main Campus'; ?></span>
                <h1 class="showcase-title"><?php echo htmlspecialchars($showcaseTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="showcase-copy"><?php echo htmlspecialchars($showcaseCopy, ENT_QUOTES, 'UTF-8'); ?></p>

                <div class="showcase-highlights">
                    <div class="showcase-item">
                        <span class="showcase-item-icon">01</span>
                        <div>
                            <h3>Choose Your Role</h3>
                            <p>Select the right login box for your account type</p>
                        </div>
                    </div>
                    <div class="showcase-item">
                        <span class="showcase-item-icon">02</span>
                        <div>
                            <h3>Quick Access</h3>
                            <p>Each role opens the correct dashboard instantly</p>
                        </div>
                    </div>
                    <div class="showcase-item">
                        <span class="showcase-item-icon">03</span>
                        <div>
                            <h3>Secure Login</h3>
                            <p>Safe access to your school records</p>
                        </div>
                    </div>
                </div>

                <div class="showcase-stats">
                    <div class="showcase-stat">
                        <strong>4</strong>
                        <span>Role options</span>
                    </div>
                    <div class="showcase-stat">
                        <strong><?php echo htmlspecialchars($siteCode, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo htmlspecialchars($portalContextLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="showcase-stat">
                        <strong>24/7</strong>
                        <span>Secure access</span>
                    </div>
                </div>
            </div>

            <div class="login-panel">
                <div class="login-header">
                    <span class="login-eyebrow">Portal Access</span>
                    <h2 id="portalHeading"><?php echo htmlspecialchars($currentPortalPresentation['heading'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p id="portalDescription"><?php echo htmlspecialchars($currentPortalPresentation['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="login-form" id="loginForm">
                    <div class="portal-selector">
                        <div class="portal-selector-head">
                            <div>
                                <strong>Select login type</strong>
                                <p>Choose your role to access the right dashboard</p>
                            </div>
                            <span class="portal-context"><?php echo htmlspecialchars($portalContextLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <input type="hidden" name="login_portal" id="login_portal" value="<?php echo htmlspecialchars($loginPortal, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="portal-grid">
                            <?php foreach ($portalCards as $portalCard): ?>
                                <?php $isActive = $loginPortal === $portalCard['key']; ?>
                                <button type="button" class="portal-option<?php echo $isActive ? ' is-active' : ''; ?>" data-portal="<?php echo htmlspecialchars($portalCard['key'], ENT_QUOTES, 'UTF-8'); ?>" aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>">
                                    <span class="portal-option-icon"><?php echo htmlspecialchars($portalCard['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="portal-option-copy">
                                        <strong><?php echo htmlspecialchars($portalCard['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <small><?php echo htmlspecialchars($portalCard['tag'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <span><?php echo htmlspecialchars($portalCard['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="portal-summary" id="portalSummary"><?php echo htmlspecialchars($currentPortalPresentation['summary'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

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
                        <p class="form-note">Choose the correct role box first, then enter the account given by your school.</p>
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
                        <span id="submitLabel"><?php echo htmlspecialchars($currentPortalPresentation['submit_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                </form>

                <div class="login-help">
                    <div class="login-demo">
                        <strong id="helpTitle"><?php echo htmlspecialchars($currentPortalPresentation['help_title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <p id="helpCopy"><?php echo htmlspecialchars($currentPortalPresentation['help_copy'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <span class="credential-pill<?php echo $currentPortalPresentation['credential_value'] === '' ? ' is-hidden' : ''; ?>" id="credentialPill"><?php echo htmlspecialchars($currentPortalPresentation['credential_value'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="login-support">
                        <strong>Need help?</strong>
                        <p>Pick the box that matches your account type before signing in. Main admins use the central portal, and branch admins use their branch campus portal.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const portalData = <?php echo $portalJson ?: '{}'; ?>;
            const portalInput = document.getElementById('login_portal');
            const portalButtons = document.querySelectorAll('.portal-option');
            const portalHeading = document.getElementById('portalHeading');
            const portalDescription = document.getElementById('portalDescription');
            const portalSummary = document.getElementById('portalSummary');
            const submitLabel = document.getElementById('submitLabel');
            const helpTitle = document.getElementById('helpTitle');
            const helpCopy = document.getElementById('helpCopy');
            const credentialPill = document.getElementById('credentialPill');

            function renderPortal(portal) {
                const data = portalData[portal];
                if (!data) {
                    return;
                }

                portalInput.value = portal;
                portalHeading.textContent = data.heading;
                portalDescription.textContent = data.description;
                portalSummary.textContent = data.summary;
                submitLabel.textContent = data.submit_label;
                helpTitle.textContent = data.help_title;
                helpCopy.textContent = data.help_copy;
                credentialPill.textContent = data.credential_value || '';
                credentialPill.classList.toggle('is-hidden', !data.credential_value);

                portalButtons.forEach(function (button) {
                    const isActive = button.dataset.portal === portal;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            }

            portalButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    renderPortal(button.dataset.portal);
                });
            });
            renderPortal(portalInput.value);
        }());
    </script>
</body>
</html>
