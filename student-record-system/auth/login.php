<?php
require_once '../config/session.php';
startAppSession();
require_once '../config/localization.php';
require_once '../config/database.php';
require_once '../config/landing_redirect.php';

if (env('APP_ENV', 'local') !== 'production') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

function isBranchPortalDatabase($databaseName) {
    return is_string($databaseName) && stripos($databaseName, 'branch') !== false;
}

function defaultLoginPortal($isBranchPortal) {
    return $isBranchPortal ? 'branch_admin' : 'main_admin';
}

function normalizeLoginPortal($value, $isBranchPortal) {
    $normalized = strtolower(trim((string)$value));
    $aliases = [
        'student' => 'student',
        'teacher' => 'teacher',
        'branch_admin' => 'branch_admin',
        'super_admin' => 'main_admin',
        'main_admin' => 'main_admin',
    ];

    return $aliases[$normalized] ?? defaultLoginPortal($isBranchPortal);
}

function loginRoleProfiles($isBranchPortal, $siteName) {
    return [
        'main_admin' => [
            'label' => t('Super Admin'),
            'badge' => $isBranchPortal ? 'Central Portal Only' : 'Full ERP Control',
            'icon' => 'bi bi-shield-lock-fill',
            'headline' => $isBranchPortal
                ? 'Super Admin access is handled from the main campus portal.'
                : 'Lead the full academic ERP from one secure administrator workspace.',
            'description' => $isBranchPortal
                ? 'This branch portal is optimized for local staff and student access.'
                : 'Configure users, oversee branches, and manage school-wide records with central authority.',
            'support' => $isBranchPortal
                ? 'Use the central portal for Super Admin access and branch-wide controls.'
                : 'Best for central office administrators responsible for system-wide setup and reporting.',
        ],
        'branch_admin' => [
            'label' => t('Branch Admin'),
            'badge' => $isBranchPortal ? $siteName . ' Portal' : 'Branch Operations',
            'icon' => 'bi bi-building-fill-gear',
            'headline' => $isBranchPortal
                ? 'Manage branch records, teachers, and daily campus operations.'
                : 'Branch Admin accounts should sign in from their assigned campus portal.',
            'description' => $isBranchPortal
                ? 'Review academic activity, support teachers, and supervise branch-level operations with confidence.'
                : 'This role is reserved for branch campus administration and localized academic workflows.',
            'support' => $isBranchPortal
                ? 'Use this role for campus oversight, branch staff support, and localized record management.'
                : 'If your school uses separate branch portals, sign in from the correct campus URL for access.',
        ],
        'teacher' => [
            'label' => t('Teacher'),
            'badge' => 'Faculty Access',
            'icon' => 'bi bi-easel2-fill',
            'headline' => 'Update marks, view classes, and manage academic progress quickly.',
            'description' => 'Teachers can record subject performance, review assigned students, and access classroom tools.',
            'support' => 'Choose Teacher when your account is used for lesson delivery, grading, and student progress tracking.',
        ],
        'student' => [
            'label' => t('Student'),
            'badge' => 'Student Portal',
            'icon' => 'bi bi-mortarboard-fill',
            'headline' => 'Access results, academic reports, and your personal learning records.',
            'description' => 'Students can securely view report cards, subject performance, and profile information.',
            'support' => 'Choose Student to open your academic record dashboard and personal results area.',
        ],
    ];
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
            return 'The selected role is for student accounts only.';
        case 'teacher':
            return 'The selected role is for teacher accounts only.';
        case 'branch_admin':
            return $isBranchPortal
                ? 'This sign-in option is only for Branch Admin accounts assigned to ' . $siteName . '.'
                : 'Branch Admin accounts must sign in from their branch campus portal.';
        case 'main_admin':
        default:
            return $isBranchPortal
                ? 'Super Admin accounts are available only on the central portal.'
                : 'This sign-in option is only for Super Admin accounts.';
    }
}

function fetchLoginUserFromStatement($stmt) {
    $stmt->store_result();
    if ($stmt->num_rows !== 1) {
        return null;
    }

    $userId = null;
    $username = null;
    $passwordHash = null;
    $email = null;
    $role = null;
    $studentId = null;
    $teacherId = null;
    $studentName = null;
    $teacherName = null;
    $isHomeroom = 0;
    $assignedGrade = null;

    if (!$stmt->bind_result(
        $userId,
        $username,
        $passwordHash,
        $email,
        $role,
        $studentId,
        $teacherId,
        $studentName,
        $teacherName,
        $isHomeroom,
        $assignedGrade
    )) {
        return null;
    }

    if (!$stmt->fetch()) {
        return null;
    }

    return [
        'user_id' => $userId,
        'username' => $username,
        'password' => $passwordHash,
        'email' => $email,
        'role' => $role,
        'student_id' => $studentId,
        'teacher_id' => $teacherId,
        'student_name' => $studentName,
        'teacher_name' => $teacherName,
        'is_homeroom' => $isHomeroom,
        'assigned_grade' => $assignedGrade,
    ];
}

function ensureDefaultAdminEmail($conn) {
    if (!($conn instanceof mysqli)) {
        return;
    }

    $adminEmail = trim((string)env('ADMIN_EMAIL', 'admin@school.edu'));
    if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND role != 'admin' LIMIT 1");
    if (!$checkStmt) {
        return;
    }

    $checkStmt->bind_param('s', $adminEmail);
    $checkStmt->execute();
    $conflictExists = dbStatementHasRows($checkStmt);
    $checkStmt->close();

    if ($conflictExists) {
        return;
    }

    $stmt = $conn->prepare("UPDATE users SET email = ? WHERE role = 'admin' AND username = 'admin' AND (email IS NULL OR TRIM(email) = '') LIMIT 1");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('s', $adminEmail);
    $stmt->execute();
    $stmt->close();
}

$error = '';
$loginIdentity = '';

$databaseName = env('DB_DATABASE', 'student_record_system');
$isBranchPortal = isBranchPortalDatabase($databaseName);
$siteName = env('SITE_NAME', $isBranchPortal ? 'Branch Campus' : 'Main Campus');
$siteCode = env('SITE_CODE', $isBranchPortal ? 'BRANCH' : 'MAIN');
$loginPortal = defaultLoginPortal($isBranchPortal);
$roleProfiles = loginRoleProfiles($isBranchPortal, $siteName);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $landingQuery = [];

    foreach (['timeout', 'logged_out', 'auth_required'] as $key) {
        $value = $_GET[$key] ?? '';
        if ($value !== '') {
            $landingQuery[$key] = (string)$value;
        }
    }

    redirectToPublicLanding($landingQuery);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    $loginPortal = normalizeLoginPortal($_POST['role'] ?? ($_POST['login_portal'] ?? ''), $isBranchPortal);
    $loginIdentity = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($loginIdentity === '' || $password === '') {
        $error = t('Enter your email and password to continue.');
    } elseif (strpos($loginIdentity, '@') !== false && !filter_var($loginIdentity, FILTER_VALIDATE_EMAIL)) {
        $error = t('Please enter a valid email address.');
    } elseif (!$conn) {
        $error = 'Database connection failed. Please try again in a moment.';
    } else {
        ensureDefaultAdminEmail($conn);

        $stmt = $conn->prepare("SELECT
                u.user_id,
                u.username,
                u.password,
                u.email,
                u.role,
                u.student_id,
                u.teacher_id,
                s.name AS student_name,
                t.teacher_name,
                t.is_homeroom,
                t.assigned_grade
            FROM users u
            LEFT JOIN students s ON u.student_id = s.student_id
            LEFT JOIN teachers t ON u.teacher_id = t.teacher_id
            WHERE (u.email = ? OR u.username = ?) AND u.is_active = 1");

        if ($stmt === false) {
            $error = 'Unable to process your sign-in request right now.';
        } else {
            $stmt->bind_param('ss', $loginIdentity, $loginIdentity);
            $executed = $stmt->execute();

            if ($executed) {
                $user = fetchLoginUserFromStatement($stmt);

                if ($user !== null && password_verify($password, $user['password'])) {
                    if (!selectedPortalMatchesUser($loginPortal, (string)$user['role'], $isBranchPortal)) {
                        $error = portalMismatchMessage($loginPortal, $isBranchPortal, $siteName);
                    } else {
                        $displayName = $user['student_name'] ?: $user['teacher_name'] ?: $user['username'];
                        $adminScope = null;
                        $roleLabelOverride = null;

                        if ($user['role'] === 'admin') {
                            $adminScope = $loginPortal === 'branch_admin' ? 'branch' : 'main';
                            $roleLabelOverride = $loginPortal === 'branch_admin' ? 'Branch Administrator' : 'Super Administrator';

                            if ($displayName === '' || $displayName === null || $displayName === $user['username']) {
                                $displayName = $loginPortal === 'branch_admin' ? 'Branch Admin' : 'Super Admin';
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
                    $error = t('Login failed. Please verify your credentials and selected role.');
                }
            } else {
                $error = 'Unable to process your sign-in request right now.';
            }

            $stmt->close();
        }
    }
}

if ($error !== '') {
    $landingQuery = ['login_error' => $error];

    if ($loginIdentity !== '') {
        $landingQuery['login_identity'] = $loginIdentity;
    }

    if ($loginPortal !== '') {
        $landingQuery['login_role'] = $loginPortal;
    }

    redirectToPublicLanding($landingQuery);
}

redirectToPublicLanding();
