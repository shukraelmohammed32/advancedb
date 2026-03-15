<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';
require_once '../includes/distributed_coordinator.php';

requireLogin();
if (!canViewStudentDirectory()) {
    $_SESSION['error'] = 'Only admin and teachers can access the student roster.';
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$coordinator = new DistributedCoordinator($db);
$is_admin = canManageStudentDirectory();
$is_teacher = hasRole('teacher');
$distributed_ready = $coordinator->isDistributedReady();
$can_access_distributed = canAccessDistributedCoordinator();
$show_site_details = $can_access_distributed && $distributed_ready;
$site_options = $show_site_details ? $coordinator->getSites() : [];
$default_site_id = $coordinator->getDefaultSiteId();

function normalizeGradeLabel($grade) {
    $grade = trim((string)$grade);
    if ($grade === '') {
        return '';
    }

    if (preg_match('/^\d+$/', $grade)) {
        return 'Grade ' . $grade;
    }

    if (preg_match('/^grade\s*(\d+)$/i', $grade, $matches)) {
        return 'Grade ' . $matches[1];
    }

    return $grade;
}

function splitPersonName($full_name) {
    $full_name = trim((string)$full_name);
    if ($full_name === '') {
        return ['first_name' => '', 'last_name' => ''];
    }

    $parts = preg_split('/\s+/', $full_name);
    if (!$parts || count($parts) === 0) {
        return ['first_name' => '', 'last_name' => ''];
    }

    $first_name = array_shift($parts);
    $last_name = trim(implode(' ', $parts));

    return [
        'first_name' => $first_name,
        'last_name' => $last_name,
    ];
}

function gradesMatch($left_grade, $right_grade) {
    return strtolower(normalizeGradeLabel($left_grade)) === strtolower(normalizeGradeLabel($right_grade));
}

function getTeacherScope($conn, $teacher_id) {
    $teacher_id = (int)$teacher_id;
    if ($teacher_id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT teacher_id, teacher_name, assigned_grade FROM teachers WHERE teacher_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $teacher_id);
    $stmt->execute();
    $teacher = dbStatementFetchOneAssoc($stmt);
    $stmt->close();

    return $teacher ?: null;
}

function normalizeEmailLocalPart($value) {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '.', $value);
    $value = trim((string)$value, '.');
    return $value === '' ? 'student' : $value;
}

function studentLoginEmailDomain() {
    $domain = strtolower(trim((string)(getenv('STUDENT_LOGIN_DOMAIN') ?: 'school.local')));
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
        return 'school.local';
    }
    return $domain;
}

function usernameExists($conn, $username, $exclude_user_id = 0) {
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE user_id != ? AND (username = ? OR email = ?) LIMIT 1');
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param('iss', $exclude_user_id, $username, $username);
    $stmt->execute();
    $exists = dbStatementHasRows($stmt);
    $stmt->close();

    return $exists;
}

function emailExists($conn, $email, $exclude_user_id = 0) {
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE user_id != ? AND (email = ? OR username = ?) LIMIT 1');
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param('iss', $exclude_user_id, $email, $email);
    $stmt->execute();
    $exists = dbStatementHasRows($stmt);
    $stmt->close();

    return $exists;
}

function generateUniqueStudentLoginEmail($conn, $student_name, $student_id = 0, $exclude_user_id = 0) {
    $base = normalizeEmailLocalPart($student_name);
    $domain = studentLoginEmailDomain();
    $student_id = (int)$student_id;

    $candidates = [];
    if ($student_id > 0) {
        $candidates[] = $base . '.' . $student_id . '@' . $domain;
    }
    $candidates[] = $base . '@' . $domain;

    foreach ($candidates as $candidate) {
        if (!emailExists($conn, $candidate, $exclude_user_id)) {
            return $candidate;
        }
    }

    for ($attempt = 1; $attempt <= 999; $attempt++) {
        $candidate = $base . '.' . $attempt . '@' . $domain;
        if (!emailExists($conn, $candidate, $exclude_user_id)) {
            return $candidate;
        }
    }

    return $base . '.' . time() . '@' . $domain;
}

function generateUniqueStudentLoginUsername($conn, $student_name, $student_id = 0, $exclude_user_id = 0) {
    $base = normalizeEmailLocalPart($student_name);
    $base = preg_replace('/[^a-z0-9._-]/', '', $base);
    $base = trim((string)$base, '._-');

    if ($base === '') {
        $base = 'student';
    }

    if (!preg_match('/^[a-z]/', $base)) {
        $base = 's' . $base;
    }

    if ($student_id > 0) {
        $candidate = $base . '.' . (int)$student_id;
        if (!usernameExists($conn, $candidate, $exclude_user_id)) {
            return $candidate;
        }
    }

    if (!usernameExists($conn, $base, $exclude_user_id)) {
        return $base;
    }

    for ($attempt = 1; $attempt <= 999; $attempt++) {
        $candidate = $base . '.' . $attempt;
        if (!usernameExists($conn, $candidate, $exclude_user_id)) {
            return $candidate;
        }
    }

    return $base . '.' . time();
}

function getStudentAccount($conn, $student_id) {
    $student_id = (int)$student_id;
    if ($student_id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT user_id, username, email, is_active FROM users WHERE student_id = ? AND role = 'student' ORDER BY user_id ASC LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $account = dbStatementFetchOneAssoc($stmt);
    $stmt->close();

    return $account ?: null;
}

function loginIdentityExists($conn, $username, $email, $exclude_user_id = 0) {
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE user_id != ? AND (username = ? OR email = ? OR username = ? OR email = ?) LIMIT 1');
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param('issss', $exclude_user_id, $username, $username, $email, $email);
    $stmt->execute();
    $exists = dbStatementHasRows($stmt);
    $stmt->close();

    return $exists;
}

function saveStudentAccount($conn, $student_id, $username, $email, $plain_password, $existing_user_id = 0) {
    $student_id = (int)$student_id;
    $existing_user_id = (int)$existing_user_id;

    if ($student_id <= 0) {
        return false;
    }

    if ($existing_user_id > 0) {
        if ($plain_password !== '') {
            $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, is_active = 1 WHERE user_id = ? AND role = 'student' LIMIT 1");
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('sssi', $username, $email, $password_hash, $existing_user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, is_active = 1 WHERE user_id = ? AND role = 'student' LIMIT 1");
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('ssi', $username, $email, $existing_user_id);
        }

        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    if ($plain_password === '') {
        return false;
    }

    $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, student_id, is_active) VALUES (?, ?, ?, 'student', ?, 1)");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sssi', $username, $password_hash, $email, $student_id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

// Get grade options
$grade_rows = [];
$grade_result = $conn->query('SELECT grade_id, grade_name FROM grades ORDER BY grade_id');
if ($grade_result) {
    while ($grade_row = $grade_result->fetch_assoc()) {
        $grade_rows[] = $grade_row;
    }
}

$teacher_scope = null;
$teacher_grade = '';
if ($is_teacher) {
    $teacher_scope = getTeacherScope($conn, (int)($_SESSION['teacher_id'] ?? 0));
    if ($teacher_scope) {
        $teacher_grade = normalizeGradeLabel($teacher_scope['assigned_grade']);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrfToken();

    if (!$is_admin) {
        header('Location: students.php?error=' . urlencode('Only admin can add or update student records'));
        exit();
    }

    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name = trim((string)($_POST['last_name'] ?? ''));
    $name = trim($first_name . ' ' . $last_name);
    $gender = trim((string)($_POST['gender'] ?? ''));
    $selected_grade = normalizeGradeLabel($_POST['grade'] ?? '');
    $academic_year = trim((string)($_POST['academic_year'] ?? ''));
    $semester = trim((string)($_POST['semester'] ?? ''));
    $selected_site_id = $distributed_ready ? $coordinator->normalizeSiteId((int)($_POST['site_id'] ?? $default_site_id)) : $default_site_id;
    $login_password = (string)($_POST['login_password'] ?? '');
    $login_password_confirm = (string)($_POST['login_password_confirm'] ?? '');

    if ($first_name === '' || $last_name === '') {
        header('Location: students.php?error=' . urlencode('Student first name and last name are required'));
        exit();
    }

    if ($selected_grade === '') {
        header('Location: students.php?error=' . urlencode('Please select a grade'));
        exit();
    }

    $grade_name_safe = $conn->real_escape_string($selected_grade);
    $grade_lookup = $conn->query("SELECT grade_id, grade_name FROM grades WHERE grade_name = '$grade_name_safe' LIMIT 1");
    if (!$grade_lookup || $grade_lookup->num_rows === 0) {
        header('Location: students.php?error=' . urlencode('Selected grade is invalid'));
        exit();
    }

    $grade_record = $grade_lookup->fetch_assoc();
    $grade_id = (int)$grade_record['grade_id'];
    $grade_name = $conn->real_escape_string($grade_record['grade_name']);

    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $existing_account = $student_id > 0 ? getStudentAccount($conn, $student_id) : null;
    $existing_user_id = (int)($existing_account['user_id'] ?? 0);
    $password_required = isset($_POST['add_student']) || $existing_user_id === 0;

    if ($password_required && strlen($login_password) < 6) {
        header('Location: students.php?error=' . urlencode('Student login password must be at least 6 characters'));
        exit();
    }

    if ($password_required && trim($login_password_confirm) === '') {
        header('Location: students.php?error=' . urlencode('Please confirm the student login password'));
        exit();
    }

    if ($login_password !== '' && $login_password !== $login_password_confirm) {
        header('Location: students.php?error=' . urlencode('Student login password and confirm password do not match'));
        exit();
    }

    $name_safe = $conn->real_escape_string($name);
    $gender_safe = $conn->real_escape_string($gender);
    $academic_year_safe = $conn->real_escape_string($academic_year);
    $semester_safe = $conn->real_escape_string($semester);
    $conn->begin_transaction();

    if (isset($_POST['add_student'])) {
        if ($distributed_ready) {
            $sql = "INSERT INTO students (name, gender, grade, grade_id, academic_year, semester, site_id)
                    VALUES ('$name_safe', '$gender_safe', '$grade_name', $grade_id, '$academic_year_safe', '$semester_safe', $selected_site_id)";
        } else {
            $sql = "INSERT INTO students (name, gender, grade, grade_id, academic_year, semester)
                    VALUES ('$name_safe', '$gender_safe', '$grade_name', $grade_id, '$academic_year_safe', '$semester_safe')";
        }

        if (!$conn->query($sql)) {
            $conn->rollback();
            header('Location: students.php?error=' . urlencode('Error adding student'));
            exit();
        }

        $student_id = (int)$conn->insert_id;
    }

    if (isset($_POST['edit_student'])) {
        if ($student_id <= 0) {
            $conn->rollback();
            header('Location: students.php?error=' . urlencode('Student record not found'));
            exit();
        }

        if ($distributed_ready) {
            $sql = "UPDATE students SET name='$name_safe', gender='$gender_safe', grade='$grade_name', grade_id=$grade_id,
                    academic_year='$academic_year_safe', semester='$semester_safe', site_id=$selected_site_id
                    WHERE student_id=$student_id";
        } else {
            $sql = "UPDATE students SET name='$name_safe', gender='$gender_safe', grade='$grade_name', grade_id=$grade_id,
                    academic_year='$academic_year_safe', semester='$semester_safe'
                    WHERE student_id=$student_id";
        }

        if (!$conn->query($sql)) {
            $conn->rollback();
            header('Location: students.php?error=' . urlencode('Error updating student'));
            exit();
        }
    }

    $login_email = generateUniqueStudentLoginEmail($conn, $name, $student_id, $existing_user_id);
    $login_username = generateUniqueStudentLoginUsername($conn, $name, $student_id, $existing_user_id);

    if (loginIdentityExists($conn, $login_username, $login_email, $existing_user_id)) {
        $conn->rollback();
        header('Location: students.php?error=' . urlencode('Unable to generate a unique student login identity. Please try again.'));
        exit();
    }

    if (!saveStudentAccount($conn, $student_id, $login_username, $login_email, $login_password, $existing_user_id)) {
        $conn->rollback();
        header('Location: students.php?error=' . urlencode('Student saved but login account could not be secured'));
        exit();
    }

    $conn->commit();
    $sync_ok = $coordinator->syncStudent($student_id);
    $site_label = $coordinator->getSiteName($selected_site_id);
    if (isset($_POST['add_student'])) {
        $message = $sync_ok
            ? 'Student created and routed to ' . $site_label
            : 'Student created in central coordinator, but branch sync failed';
    } else {
        $message = $sync_ok
            ? 'Student updated and synced to ' . $site_label
            : 'Student updated in central coordinator, but branch sync failed';
    }
    $message .= ' Login email: ' . $login_email;
    header('Location: students.php?success=' . urlencode($message));
    exit();
}

// Handle delete action (CSRF protected)
if (isset($_GET['delete'])) {
    requireValidCsrfToken();

    if (!$is_admin) {
        header('Location: students.php?error=' . urlencode('Only admin can delete student records'));
        exit();
    }

    $student_id = (int)$_GET['delete'];

    if ($student_id > 0) {
        $conn->begin_transaction();
        $conn->query("DELETE FROM users WHERE student_id = $student_id AND role = 'student'");
        $conn->query("DELETE FROM student_profiles WHERE student_id = $student_id");
        $conn->query("DELETE FROM marks WHERE student_id = $student_id");
        $conn->query("DELETE FROM students WHERE student_id=$student_id");
        $conn->commit();
        $sync_ok = $coordinator->deleteStudentDistributed($student_id);
        $message = $sync_ok
            ? 'Student deleted from coordinator and all branch databases'
            : 'Student deleted centrally, but some branch cleanup failed';
        header('Location: students.php?success=' . urlencode($message));
        exit();
    }

    header('Location: students.php?success=' . urlencode('Student deleted successfully'));
    exit();
}

$student_site_select = $show_site_details
    ? "s.site_id, COALESCE(ds.site_name, 'Unassigned Site') AS site_name, COALESCE(ds.site_code, 'N/A') AS site_code,"
    : "$default_site_id AS site_id, 'Central Coordinator' AS site_name, 'CENTRAL' AS site_code,";
$student_site_join = $distributed_ready ? 'LEFT JOIN distributed_sites ds ON ds.site_id = s.site_id' : '';

// Get student data for editing
$edit_student = null;
if ($is_admin && isset($_GET['edit'])) {
    $student_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT
                                s.*,
                                $student_site_select
                                (SELECT u.user_id FROM users u WHERE u.student_id = s.student_id AND u.role = 'student' ORDER BY u.user_id ASC LIMIT 1) AS login_user_id,
                                (SELECT u.username FROM users u WHERE u.student_id = s.student_id AND u.role = 'student' ORDER BY u.user_id ASC LIMIT 1) AS login_username,
                                (SELECT u.email FROM users u WHERE u.student_id = s.student_id AND u.role = 'student' ORDER BY u.user_id ASC LIMIT 1) AS login_email
                            FROM students s
                            $student_site_join
                            WHERE s.student_id = $student_id
                            LIMIT 1");
    $edit_student = $result ? $result->fetch_assoc() : null;
}

$edit_student_name_parts = splitPersonName($edit_student['name'] ?? '');

// Get all students grouped by grade/class
$students = $conn->query("SELECT
                            s.*,
                            $student_site_select
                            (SELECT u.username FROM users u WHERE u.student_id = s.student_id AND u.role = 'student' ORDER BY u.user_id ASC LIMIT 1) AS login_username,
                            (SELECT u.email FROM users u WHERE u.student_id = s.student_id AND u.role = 'student' ORDER BY u.user_id ASC LIMIT 1) AS login_email,
                            EXISTS(SELECT 1 FROM users u WHERE u.student_id = s.student_id AND u.role = 'student') AS has_login
                         FROM students s
                         $student_site_join
                         ORDER BY s.grade_id ASC, s.grade ASC, s.name ASC");
$students_by_grade = [];
if ($students) {
    while ($student = $students->fetch_assoc()) {
        $grade_label = normalizeGradeLabel($student['grade']);
        if ($grade_label === '') {
            $grade_label = 'Unassigned Grade';
        }

        if ($is_teacher && ($teacher_grade === '' || !gradesMatch($grade_label, $teacher_grade))) {
            continue;
        }

        if (!isset($students_by_grade[$grade_label])) {
            $students_by_grade[$grade_label] = [];
        }

        $students_by_grade[$grade_label][] = $student;
    }
}

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') : '';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';
$info_message = '';
$page_title = $is_admin ? 'Student Management' : (isHomeroomTeacher() ? 'Homeroom Student Roster' : 'Student Roster');

if ($is_teacher) {
    if ($teacher_scope && $teacher_grade !== '') {
        $info_message = isHomeroomTeacher()
            ? 'You can review only students in ' . $teacher_grade . ' while compiling final results. Student accounts remain admin-managed.'
            : 'You can view only students in ' . $teacher_grade . '. Student records and accounts remain admin-managed.';
    } else {
        $error_message = $error_message !== ''
            ? $error_message
            : 'Your teacher account is not linked to an assigned grade. Contact admin.';
    }
} elseif ($is_admin && $show_site_details) {
    $info_message = 'Distributed mode is active. New students are routed through the central coordinator to a selected local branch site.';
}

$csrf_token = urlencode(getCsrfToken());
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLanguageTag(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><?php echo htmlspecialchars(t('Student Record System'), ENT_QUOTES, 'UTF-8'); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php"><?php echo htmlspecialchars(t('Dashboard'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="students.php"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="subjects.php"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php if ($is_admin): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="teachers.php"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canManageMarks()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="marks.php"><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canAccessSummary()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="summary.php"><?php echo htmlspecialchars(t('Summary'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                    <?php if (canViewStudentReports()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php"><?php echo htmlspecialchars(t('Reports'), ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
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

        <?php if ($info_message): ?>
            <div class="alert alert-info" role="alert">
                <?php echo htmlspecialchars($info_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <?php if ($is_admin): ?>
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <?php echo $edit_student ? 'Edit Student' : 'Add New Student'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php csrfInput(); ?>
                            <?php if ($edit_student): ?>
                                <input type="hidden" name="student_id" value="<?php echo (int)$edit_student['student_id']; ?>">
                            <?php endif; ?>

                            <?php if ($show_site_details): ?>
                            <div class="mb-3">
                                <label for="site_id" class="form-label">Branch Site</label>
                                <select class="form-control" id="site_id" name="site_id" required>
                                    <?php
                                    $current_site_id = $edit_student ? (int)($edit_student['site_id'] ?? $default_site_id) : $default_site_id;
                                    foreach ($site_options as $site_option):
                                    ?>
                                        <option value="<?php echo (int)$site_option['site_id']; ?>" <?php echo $current_site_id === (int)$site_option['site_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($site_option['site_name'] . ' (' . $site_option['site_code'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">The central coordinator will store this student in the selected local branch database.</div>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                       value="<?php echo $edit_student ? htmlspecialchars((string)$edit_student_name_parts['first_name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       value="<?php echo $edit_student ? htmlspecialchars((string)$edit_student_name_parts['last_name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-control" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo $edit_student && $edit_student['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $edit_student && $edit_student['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="grade" class="form-label">Grade</label>
                                <select class="form-control" id="grade" name="grade" required>
                                    <option value="">Select Grade</option>
                                    <?php
                                    $current_grade = $edit_student ? normalizeGradeLabel($edit_student['grade']) : '';
                                    foreach ($grade_rows as $grade_row):
                                        $grade_name = normalizeGradeLabel($grade_row['grade_name']);
                                        $is_selected = strtolower($grade_name) === strtolower($current_grade);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($grade_name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($grade_name, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="academic_year" class="form-label">Academic Year</label>
                                <input type="text" class="form-control" id="academic_year" name="academic_year"
                                       value="<?php echo $edit_student ? htmlspecialchars($edit_student['academic_year'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-control" id="semester" name="semester" required>
                                    <option value="">Select Semester</option>
                                    <option value="First" <?php echo $edit_student && $edit_student['semester'] === 'First' ? 'selected' : ''; ?>>First</option>
                                    <option value="Second" <?php echo $edit_student && $edit_student['semester'] === 'Second' ? 'selected' : ''; ?>>Second</option>
                                </select>
                            </div>

                            <hr>
                            <h6 class="mb-3">Student Login Security</h6>

                            <div class="mb-3">
                                <label for="login_email" class="form-label">Login Email</label>
                                <input type="email" class="form-control" id="login_email"
                                       value="<?php echo $edit_student ? htmlspecialchars((string)($edit_student['login_email'] ?? ''), ENT_QUOTES, 'UTF-8') : ''; ?>"
                                       placeholder="Auto-generated from student name" readonly
                                       data-domain="<?php echo htmlspecialchars(studentLoginEmailDomain(), ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="form-text">Automatically generated from student name and always unique.</div>
                            </div>

                            <div class="mb-3">
                                <label for="login_password" class="form-label">
                                    <?php echo $edit_student && !empty($edit_student['login_user_id']) ? 'New Password' : 'Login Password'; ?>
                                </label>
                                <input type="password" class="form-control" id="login_password" name="login_password"
                                       <?php echo $edit_student && !empty($edit_student['login_user_id']) ? '' : 'required'; ?>>
                                <div class="form-text">
                                    <?php if ($edit_student && !empty($edit_student['login_user_id'])): ?>
                                        Leave blank to keep the current student password.
                                    <?php else: ?>
                                        Required for new student login accounts. Minimum 6 characters.
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="login_password_confirm" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="login_password_confirm" name="login_password_confirm"
                                       <?php echo $edit_student && !empty($edit_student['login_user_id']) ? '' : 'required'; ?>>
                                <div class="form-text">
                                    <?php if ($edit_student && !empty($edit_student['login_user_id'])): ?>
                                        Enter the same new password again when resetting.
                                    <?php else: ?>
                                        Re-enter the same password to confirm.
                                    <?php endif; ?>
                                </div>
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
            <?php endif; ?>

            <div class="<?php echo $is_admin ? 'col-lg-8' : 'col-12'; ?>">
                <?php if (empty($students_by_grade)): ?>
                    <div class="card">
                        <div class="card-body">
                            <p class="mb-0 text-muted"><?php echo $is_teacher ? 'No students found in your assigned grade.' : 'No students found.'; ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($students_by_grade as $grade_label => $grade_students): ?>
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($grade_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="badge bg-primary"><?php echo count($grade_students); ?> Students</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <?php if ($show_site_details): ?>
                                                <th>Site</th>
                                                <?php endif; ?>
                                                <?php if ($is_admin): ?>
                                                <th>Email</th>
                                                <?php endif; ?>
                                                <th>Academic Year</th>
                                                <th>Semester</th>
                                                <?php if ($is_admin): ?>
                                                <th>Account</th>
                                                <th>Actions</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($grade_students as $student): ?>
                                                <tr>
                                                    <td><?php echo (int)$student['student_id']; ?></td>
                                                    <td><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php if ($show_site_details): ?>
                                                    <td><?php echo htmlspecialchars((string)($student['site_name'] ?? 'Central Coordinator'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php endif; ?>
                                                    <?php if ($is_admin): ?>
                                                    <td><?php echo htmlspecialchars((string)($student['login_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php endif; ?>
                                                    <td><?php echo htmlspecialchars($student['academic_year'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($student['semester'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php if ($is_admin): ?>
                                                    <td>
                                                        <?php if (!empty($student['has_login'])): ?>
                                                            <span class="badge bg-success">Secured</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Missing Login</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="students.php?edit=<?php echo (int)$student['student_id']; ?>"
                                                           class="btn btn-sm btn-warning">Edit</a>
                                                        <a href="students.php?delete=<?php echo (int)$student['student_id']; ?>&csrf_token=<?php echo $csrf_token; ?>"
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Are you sure you want to delete this student from the coordinator and branch database?')">Delete</a>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $footer_base_path = '../';
    include __DIR__ . '/../includes/footer.php';
    ?>
    <script>
        (function () {
            var firstNameInput = document.getElementById('first_name');
            var lastNameInput = document.getElementById('last_name');
            var emailInput = document.getElementById('login_email');

            if (!firstNameInput || !lastNameInput || !emailInput) {
                return;
            }

            if (emailInput.value.trim() !== '') {
                return;
            }

            var domain = (emailInput.getAttribute('data-domain') || 'school.local').toLowerCase();

            function toLocalPart(value) {
                var normalized = value.toLowerCase().replace(/[^a-z0-9]+/g, '.').replace(/^\.+|\.+$/g, '');
                return normalized === '' ? 'student' : normalized;
            }

            function updatePreview() {
                var fullName = ((firstNameInput.value || '') + ' ' + (lastNameInput.value || '')).trim();
                emailInput.value = toLocalPart(fullName) + '@' + domain;
            }

            firstNameInput.addEventListener('input', updatePreview);
            lastNameInput.addEventListener('input', updatePreview);
            updatePreview();
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
