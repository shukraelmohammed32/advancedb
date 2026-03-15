<?php
require_once '../config/session.php';
startAppSession();
require_once '../config/localization.php';
require_once '../config/database.php';

if ((getenv('APP_ENV') ?: 'local') !== 'production') {
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

$error = '';
$loginInput = '';

$databaseName = getenv('DB_DATABASE') ?: 'student_record_system';
$isBranchPortal = isBranchPortalDatabase($databaseName);
$siteName = getenv('SITE_NAME') ?: ($isBranchPortal ? 'Branch Campus' : 'Main Campus');
$siteCode = getenv('SITE_CODE') ?: ($isBranchPortal ? 'BRANCH' : 'MAIN');
$loginPortal = defaultLoginPortal($isBranchPortal);
$roleProfiles = loginRoleProfiles($isBranchPortal, $siteName);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    $loginPortal = normalizeLoginPortal($_POST['role'] ?? ($_POST['login_portal'] ?? ''), $isBranchPortal);
    $loginInput = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($loginInput === '' || $password === '') {
        $error = t('Enter your email or username and password to continue.');
    } elseif (!$conn) {
        $error = 'Database connection failed. Please try again in a moment.';
    } else {
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
            WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1");

        if ($stmt === false) {
            $error = 'Unable to process your sign-in request right now.';
        } else {
            $stmt->bind_param('ss', $loginInput, $loginInput);
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

$currentRoleProfile = $roleProfiles[$loginPortal] ?? $roleProfiles[defaultLoginPortal($isBranchPortal)];
$roleProfilesJson = json_encode($roleProfiles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
$campusLabel = $isBranchPortal ? $siteName . ' Branch' : 'Central Academic Portal';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLanguageTag(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('Student Record System') . ' - ' . t('Sign In'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/login.css" rel="stylesheet">
</head>
<body class="auth-login-page">
    <main class="login-shell">
        <section class="login-card<?php echo $error !== '' ? ' has-error' : ''; ?>">
            <div class="login-hero">
                <div class="hero-topline">
                    <span class="hero-pill"><i class="bi bi-building"></i> <?php echo htmlspecialchars($campusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="hero-pill hero-pill-soft"><i class="bi bi-shield-check"></i> <?php echo htmlspecialchars(t('Sign In'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="brand-lockup">
                    <img src="../assets/school-logo.svg" alt="School logo" class="school-logo">
                    <div class="brand-copy">
                        <span class="brand-kicker"><?php echo htmlspecialchars(t('Sign In'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <h1><?php echo htmlspecialchars(t('Student Record System'), ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p><?php echo htmlspecialchars(t('Professional access for administrators, teachers, and students from one academic portal.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="hero-focus">
                    <div class="role-badge" id="roleBadge"><?php echo htmlspecialchars($currentRoleProfile['badge'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <h2 id="roleHeadline"><?php echo htmlspecialchars($currentRoleProfile['headline'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p id="roleDescription"><?php echo htmlspecialchars($currentRoleProfile['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <div class="hero-highlights">
                    <article class="highlight-card">
                        <span class="highlight-icon"><i class="bi bi-diagram-3-fill"></i></span>
                        <div>
                            <h3>Role-aware access</h3>
                            <p>Separate sign-in paths for Super Admin, Branch Admin, Teacher, and Student users.</p>
                        </div>
                    </article>
                    <article class="highlight-card">
                        <span class="highlight-icon"><i class="bi bi-journal-richtext"></i></span>
                        <div>
                            <h3>Academic record tools</h3>
                            <p>Support marks entry, reports, student profiles, and branch-level management.</p>
                        </div>
                    </article>
                    <article class="highlight-card">
                        <span class="highlight-icon"><i class="bi bi-phone-fill"></i></span>
                        <div>
                            <h3>Responsive by design</h3>
                            <p>Built to work smoothly across desktop screens, tablets, and mobile devices.</p>
                        </div>
                    </article>
                </div>
            </div>

            <div class="login-panel">
                <div class="panel-header">
                    <span class="panel-kicker"><i class="bi bi-person-badge-fill"></i> <?php echo htmlspecialchars(t('Sign In'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <h2><?php echo htmlspecialchars(t('Sign In'), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?php echo htmlspecialchars(t('Enter your email or username and password to continue.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger login-alert" id="loginError" role="alert" aria-live="assertive">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div>
                            <strong><?php echo htmlspecialchars(t('Sign In'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="login-form" id="loginForm" novalidate>
                    <div class="field-group">
                        <label for="username" class="form-label"><?php echo htmlspecialchars(t('Username or Email'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <div class="field-shell">
                            <span class="field-icon"><i class="bi bi-person-circle"></i></span>
                            <input
                                type="text"
                                class="form-control<?php echo $error !== '' ? ' is-invalid' : ''; ?>"
                                id="username"
                                name="username"
                                placeholder="<?php echo htmlspecialchars(t('Username or Email'), ENT_QUOTES, 'UTF-8'); ?>"
                                value="<?php echo htmlspecialchars($loginInput, ENT_QUOTES, 'UTF-8'); ?>"
                                autocomplete="username"
                                required
                            >
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password" class="form-label"><?php echo htmlspecialchars(t('Password'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <div class="field-shell">
                            <span class="field-icon"><i class="bi bi-lock-fill"></i></span>
                            <input
                                type="password"
                                class="form-control<?php echo $error !== '' ? ' is-invalid' : ''; ?>"
                                id="password"
                                name="password"
                                placeholder="<?php echo htmlspecialchars(t('Password'), ENT_QUOTES, 'UTF-8'); ?>"
                                autocomplete="current-password"
                                required
                            >
                            <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="field-group">
                        <div class="field-header">
                            <label for="role" class="form-label"><?php echo htmlspecialchars(t('Role'), ENT_QUOTES, 'UTF-8'); ?></label>
                            <span class="field-meta">Choose your portal access level</span>
                        </div>
                        <div class="field-shell select-shell">
                            <span class="field-icon" id="roleIconShell"><i class="<?php echo htmlspecialchars($currentRoleProfile['icon'], ENT_QUOTES, 'UTF-8'); ?>" id="roleIcon"></i></span>
                            <select class="form-select<?php echo $error !== '' ? ' is-invalid' : ''; ?>" id="role" name="role" required>
                                <option value="main_admin"<?php echo $loginPortal === 'main_admin' ? ' selected' : ''; ?>><?php echo htmlspecialchars(t('Super Admin'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="branch_admin"<?php echo $loginPortal === 'branch_admin' ? ' selected' : ''; ?>><?php echo htmlspecialchars(t('Branch Admin'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="teacher"<?php echo $loginPortal === 'teacher' ? ' selected' : ''; ?>><?php echo htmlspecialchars(t('Teacher'), ENT_QUOTES, 'UTF-8'); ?></option>
                                <option value="student"<?php echo $loginPortal === 'student' ? ' selected' : ''; ?>><?php echo htmlspecialchars(t('Student'), ENT_QUOTES, 'UTF-8'); ?></option>
                            </select>
                            <span class="select-caret"><i class="bi bi-chevron-down"></i></span>
                        </div>
                    </div>

                    <div class="role-note-card">
                        <span class="role-note-icon"><i class="<?php echo htmlspecialchars($currentRoleProfile['icon'], ENT_QUOTES, 'UTF-8'); ?>" id="roleNoteIcon"></i></span>
                        <div>
                            <strong id="roleLabel"><?php echo htmlspecialchars($currentRoleProfile['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p id="roleSupport"><?php echo htmlspecialchars($currentRoleProfile['support'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login" id="loginButton">
                        <span class="btn-copy"><?php echo htmlspecialchars(t('Sign In'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <i class="bi bi-arrow-right-circle-fill"></i>
                    </button>

                    <div class="login-links">
                        <a href="#" class="forgot-link" data-bs-toggle="modal" data-bs-target="#aboutSystemModal">
                            <i class="bi bi-info-circle"></i>
                            <?php echo htmlspecialchars(t('About'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <a href="#" class="forgot-link" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                            <i class="bi bi-question-circle"></i>
                            Forgot password?
                        </a>
                        <span class="login-footnote"><i class="bi bi-lock"></i> Protected academic access</span>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content forgot-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="forgotPasswordModalLabel">Password assistance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="forgot-icon"><i class="bi bi-envelope-paper-fill"></i></div>
                    <p>If you cannot remember your password, contact your school administrator or ICT office to reset your account securely.</p>
                    <div class="forgot-contact">
                        <span><i class="bi bi-building-fill"></i> <?php echo htmlspecialchars($campusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><i class="bi bi-person-workspace"></i> Academic support desk</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="aboutSystemModal" tabindex="-1" aria-labelledby="aboutSystemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content forgot-modal">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="aboutSystemModalLabel"><?php echo htmlspecialchars(t('About This System'), ENT_QUOTES, 'UTF-8'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(t('Close'), ENT_QUOTES, 'UTF-8'); ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="forgot-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
                    <p><?php echo htmlspecialchars(t('Student Record System centralizes student records, teacher assignments, marks, summaries, and reports in one school platform.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="forgot-contact text-start">
                        <div class="fw-semibold mb-2"><?php echo htmlspecialchars(t('Core Modules'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div><?php echo htmlspecialchars(t('Student profiles and academic records'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div><?php echo htmlspecialchars(t('Teacher assignments and homeroom management'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div><?php echo htmlspecialchars(t('Marks entry, summaries, and printable reports'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div><?php echo htmlspecialchars(t('Distributed branch oversight for campus operations'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="forgot-contact mt-3 text-start">
                        <div class="fw-semibold mb-2"><?php echo htmlspecialchars(t('Who Uses It'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div><?php echo htmlspecialchars(t('Super Admin manages the full platform, Branch Admin oversees campus operations, teachers manage marks, and students view results.'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.loginRoleProfiles = <?php echo $roleProfilesJson ?: '{}'; ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/login.js"></script>
</body>
</html>
