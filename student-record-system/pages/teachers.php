<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireLogin();
if (!canManageTeachers()) {
    $_SESSION['error'] = 'Only admin can manage teachers and homeroom assignments.';
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$can_manage_teacher_accounts = true;
$allowed_grade_names = ['Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];

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

function normalizeEmailLocalPart($value) {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '.', $value);
    $value = trim((string)$value, '.');
    return $value === '' ? 'teacher' : $value;
}

function teacherLoginEmailDomain() {
    $domain = strtolower(trim((string)env('TEACHER_LOGIN_DOMAIN', 'school.local')));
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

function generateUniqueTeacherLoginEmail($conn, $teacher_name, $teacher_id = 0, $exclude_user_id = 0) {
    $base = normalizeEmailLocalPart($teacher_name);
    $domain = teacherLoginEmailDomain();
    $teacher_id = (int)$teacher_id;

    $candidates = [];
    if ($teacher_id > 0) {
        $candidates[] = $base . '.' . $teacher_id . '@' . $domain;
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

function generateUniqueTeacherLoginUsername($conn, $teacher_name, $teacher_id = 0, $exclude_user_id = 0) {
    $base = normalizeEmailLocalPart($teacher_name);
    $base = preg_replace('/[^a-z0-9._-]/', '', $base);
    $base = trim((string)$base, '._-');

    if ($base === '') {
        $base = 'teacher';
    }

    if (!preg_match('/^[a-z]/', $base)) {
        $base = 't' . $base;
    }

    if ($teacher_id > 0) {
        $candidate = $base . '.' . (int)$teacher_id;
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

function normalizeSubjectIds($raw_subject_ids) {
    $subject_ids = [];

    if (is_array($raw_subject_ids)) {
        foreach ($raw_subject_ids as $raw_id) {
            $subject_id = (int)$raw_id;
            if ($subject_id > 0) {
                $subject_ids[$subject_id] = $subject_id;
            }
        }
    }

    return array_values($subject_ids);
}

function saveTeacherSubjects($conn, $teacher_id, $subject_ids) {
    if (!$conn->query("DELETE FROM teacher_subjects WHERE teacher_id = $teacher_id")) {
        return false;
    }

    foreach ($subject_ids as $subject_id) {
        if (!$conn->query("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES ($teacher_id, $subject_id)")) {
            return false;
        }
    }

    return true;
}

function getDepartmentLabel($conn, $subject_ids) {
    if (count($subject_ids) === 0) {
        return '';
    }

    if (count($subject_ids) > 1) {
        return 'Multiple Subjects';
    }

    $subject_id = (int)$subject_ids[0];
    $result = $conn->query("SELECT subject_name FROM subjects WHERE subject_id = $subject_id");

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['subject_name'];
    }

    return 'General';
}

function getTeacherAccount($conn, $teacher_id) {
    $teacher_id = (int)$teacher_id;
    if ($teacher_id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT user_id, username, email, is_active FROM users WHERE teacher_id = ? AND role = 'teacher' ORDER BY user_id ASC LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $teacher_id);
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

function saveTeacherAccount($conn, $teacher_id, $username, $email, $plain_password, $existing_user_id = 0) {
    $teacher_id = (int)$teacher_id;
    $existing_user_id = (int)$existing_user_id;

    if ($teacher_id <= 0) {
        return false;
    }

    if ($existing_user_id > 0) {
        if ($plain_password !== '') {
            $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, is_active = 1 WHERE user_id = ? AND role = 'teacher' LIMIT 1");
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('sssi', $username, $email, $password_hash, $existing_user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, is_active = 1 WHERE user_id = ? AND role = 'teacher' LIMIT 1");
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
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, teacher_id, is_active) VALUES (?, ?, ?, 'teacher', ?, 1)");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sssi', $username, $password_hash, $email, $teacher_id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

// Get grade options
$grade_rows = [];
$grade_result = $conn->query("SELECT grade_id, grade_name FROM grades WHERE grade_name IN ('Grade 9', 'Grade 10', 'Grade 11', 'Grade 12') ORDER BY grade_id");
if ($grade_result) {
    while ($grade_row = $grade_result->fetch_assoc()) {
        $grade_rows[] = $grade_row;
    }
}

// Get all subjects
$subject_rows = [];
$subjects_result = $conn->query('SELECT subject_id, subject_name FROM subjects ORDER BY subject_name');
if ($subjects_result) {
    while ($subject = $subjects_result->fetch_assoc()) {
        $subject_rows[] = $subject;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrfToken();

    $teacher_first_name = trim((string)($_POST['teacher_first_name'] ?? ''));
    $teacher_last_name = trim((string)($_POST['teacher_last_name'] ?? ''));
    $teacher_name = trim($teacher_first_name . ' ' . $teacher_last_name);
    $assigned_grade_input = normalizeGradeLabel($_POST['assigned_grade'] ?? '');
    $is_homeroom = isset($_POST['is_homeroom']) ? 1 : 0;
    $subject_ids = normalizeSubjectIds($_POST['subject_ids'] ?? []);
    $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;

    if ($teacher_first_name === '' || $teacher_last_name === '') {
        header('Location: teachers.php?error=' . urlencode('Teacher first name and last name are required'));
        exit();
    }

    if (count($subject_ids) === 0) {
        header('Location: teachers.php?error=' . urlencode('Please assign at least one subject'));
        exit();
    }

    if ($assigned_grade_input === '') {
        header('Location: teachers.php?error=' . urlencode('Please select assigned grade'));
        exit();
    }

    if (!in_array($assigned_grade_input, $allowed_grade_names, true)) {
        header('Location: teachers.php?error=' . urlencode('Only Grade 9 to Grade 12 are allowed'));
        exit();
    }

    $grade_safe = $conn->real_escape_string($assigned_grade_input);
    $grade_lookup = $conn->query("SELECT grade_name FROM grades WHERE grade_name = '$grade_safe' LIMIT 1");
    if (!$grade_lookup || $grade_lookup->num_rows === 0) {
        header('Location: teachers.php?error=' . urlencode('Selected grade is invalid'));
        exit();
    }

    $assigned_grade = $conn->real_escape_string($grade_lookup->fetch_assoc()['grade_name']);
    $department = $conn->real_escape_string(getDepartmentLabel($conn, $subject_ids));
    $teacher_name_safe = $conn->real_escape_string($teacher_name);

    $existing_account = $teacher_id > 0 ? getTeacherAccount($conn, $teacher_id) : null;
    $existing_user_id = (int)($existing_account['user_id'] ?? 0);
    $login_password = '';

    if ($can_manage_teacher_accounts) {
        $login_password = (string)($_POST['login_password'] ?? '');
        $login_password_confirm = (string)($_POST['login_password_confirm'] ?? '');
        $password_required = isset($_POST['add_teacher']) || $existing_user_id === 0;

        if ($password_required && strlen($login_password) < 6) {
            header('Location: teachers.php?error=' . urlencode('Teacher login password must be at least 6 characters'));
            exit();
        }

        if ($password_required && trim($login_password_confirm) === '') {
            header('Location: teachers.php?error=' . urlencode('Please confirm the teacher login password'));
            exit();
        }

        if ($login_password !== '' && $login_password !== $login_password_confirm) {
            header('Location: teachers.php?error=' . urlencode('Teacher login password and confirm password do not match'));
            exit();
        }
    }

    $conn->begin_transaction();

    if (isset($_POST['add_teacher'])) {
        if ($is_homeroom) {
            $conn->query("UPDATE teachers SET is_homeroom = 0 WHERE assigned_grade = '$assigned_grade'");
        }

        $sql = "INSERT INTO teachers (teacher_name, department, assigned_grade, is_homeroom)
                VALUES ('$teacher_name_safe', '$department', '$assigned_grade', $is_homeroom)";

        if (!$conn->query($sql)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Error adding teacher'));
            exit();
        }

        $teacher_id = (int)$conn->insert_id;

        if (!saveTeacherSubjects($conn, $teacher_id, $subject_ids)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Teacher saved but subjects failed to save'));
            exit();
        }

        $login_email = generateUniqueTeacherLoginEmail($conn, $teacher_name, $teacher_id, $existing_user_id);
        $login_username = generateUniqueTeacherLoginUsername($conn, $teacher_name, $teacher_id, $existing_user_id);

        if (loginIdentityExists($conn, $login_username, $login_email, $existing_user_id)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Unable to generate a unique teacher login identity. Please try again.'));
            exit();
        }

        if ($can_manage_teacher_accounts && !saveTeacherAccount($conn, $teacher_id, $login_username, $login_email, $login_password, $existing_user_id)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Teacher saved but login account could not be secured'));
            exit();
        }

        $conn->commit();
        $message = $can_manage_teacher_accounts
            ? 'Teacher and login account created successfully. Login email: ' . $login_email
            : 'Teacher added successfully';
        header('Location: teachers.php?success=' . urlencode($message));
        exit();
    }

    if (isset($_POST['edit_teacher'])) {
        if ($teacher_id <= 0) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Teacher record not found'));
            exit();
        }

        if ($is_homeroom) {
            $conn->query("UPDATE teachers SET is_homeroom = 0 WHERE assigned_grade = '$assigned_grade' AND teacher_id != $teacher_id");
        }

        $sql = "UPDATE teachers SET teacher_name='$teacher_name_safe', department='$department',
                assigned_grade='$assigned_grade', is_homeroom=$is_homeroom
                WHERE teacher_id=$teacher_id";

        if (!$conn->query($sql)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Error updating teacher'));
            exit();
        }

        if (!saveTeacherSubjects($conn, $teacher_id, $subject_ids)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Teacher updated but subjects failed to save'));
            exit();
        }

        $login_email = generateUniqueTeacherLoginEmail($conn, $teacher_name, $teacher_id, $existing_user_id);
        $login_username = generateUniqueTeacherLoginUsername($conn, $teacher_name, $teacher_id, $existing_user_id);

        if (loginIdentityExists($conn, $login_username, $login_email, $existing_user_id)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Unable to generate a unique teacher login identity. Please try again.'));
            exit();
        }

        if ($can_manage_teacher_accounts && !saveTeacherAccount($conn, $teacher_id, $login_username, $login_email, $login_password, $existing_user_id)) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Teacher updated but login account could not be secured'));
            exit();
        }

        $conn->commit();
        $message = $can_manage_teacher_accounts
            ? 'Teacher and login account updated successfully. Login email: ' . $login_email
            : 'Teacher updated successfully';
        header('Location: teachers.php?success=' . urlencode($message));
        exit();
    }
}

if (isset($_GET['delete'])) {
    requireValidCsrfToken();
    $teacher_id = (int)$_GET['delete'];

    if ($teacher_id > 0) {
        $conn->begin_transaction();

        // Remove login account, subject assignments, and marks attributed to this teacher
        $conn->query("DELETE FROM users WHERE teacher_id = $teacher_id AND role = 'teacher'");
        $conn->query("DELETE FROM teacher_subjects WHERE teacher_id = $teacher_id");

        // Only remove marks that carry a teacher_id FK (column may not exist on older schemas)
        $marksColCheck = $conn->query("SHOW COLUMNS FROM marks LIKE 'teacher_id'");
        if ($marksColCheck && $marksColCheck->num_rows > 0) {
            $conn->query("DELETE FROM marks WHERE teacher_id = $teacher_id");
        }

        if (!$conn->query("DELETE FROM teachers WHERE teacher_id = $teacher_id")) {
            $conn->rollback();
            header('Location: teachers.php?error=' . urlencode('Failed to delete teacher. Please try again.'));
            exit();
        }

        $conn->commit();
    }

    header('Location: teachers.php?success=' . urlencode('Teacher and login account deleted successfully'));
    exit();
}

// Get teacher data for editing
$edit_teacher = null;
$selected_subject_ids = [];
if (isset($_GET['edit'])) {
    $teacher_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT
                                t.*,
                                (SELECT u.user_id FROM users u WHERE u.teacher_id = t.teacher_id AND u.role = 'teacher' ORDER BY u.user_id ASC LIMIT 1) AS login_user_id,
                                (SELECT u.username FROM users u WHERE u.teacher_id = t.teacher_id AND u.role = 'teacher' ORDER BY u.user_id ASC LIMIT 1) AS login_username,
                                (SELECT u.email FROM users u WHERE u.teacher_id = t.teacher_id AND u.role = 'teacher' ORDER BY u.user_id ASC LIMIT 1) AS login_email
                            FROM teachers t
                            WHERE t.teacher_id = $teacher_id
                            LIMIT 1");
    $edit_teacher = $result ? $result->fetch_assoc() : null;

    if ($edit_teacher) {
        $subject_result = $conn->query("SELECT subject_id FROM teacher_subjects WHERE teacher_id = $teacher_id");
        if ($subject_result) {
            while ($subject_row = $subject_result->fetch_assoc()) {
                $selected_subject_ids[] = (int)$subject_row['subject_id'];
            }
        }

        if (count($selected_subject_ids) === 0 && !empty($edit_teacher['department'])) {
            $department = $conn->real_escape_string($edit_teacher['department']);
            $legacy_subject = $conn->query("SELECT subject_id FROM subjects WHERE subject_name = '$department'");
            if ($legacy_subject && $legacy_subject->num_rows > 0) {
                $legacy_row = $legacy_subject->fetch_assoc();
                $selected_subject_ids[] = (int)$legacy_row['subject_id'];
            }
        }
    }
}

// Get all teachers with their assigned subjects
$teachers = $conn->query("SELECT t.*,
                         COALESCE(GROUP_CONCAT(DISTINCT s.subject_name ORDER BY s.subject_name SEPARATOR ', '), t.department) AS subjects_taught,
                         (SELECT u.username FROM users u WHERE u.teacher_id = t.teacher_id AND u.role = 'teacher' ORDER BY u.user_id ASC LIMIT 1) AS login_username,
                         (SELECT u.email FROM users u WHERE u.teacher_id = t.teacher_id AND u.role = 'teacher' ORDER BY u.user_id ASC LIMIT 1) AS login_email,
                         EXISTS(SELECT 1 FROM users u WHERE u.teacher_id = t.teacher_id AND u.role = 'teacher') AS has_login
                         FROM teachers t
                         LEFT JOIN teacher_subjects ts ON t.teacher_id = ts.teacher_id
                         LEFT JOIN subjects s ON ts.subject_id = s.subject_id
                         GROUP BY t.teacher_id
                         ORDER BY t.teacher_name");

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') : '';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';
$csrf_token = urlencode(getCsrfToken());
$edit_teacher_name_parts = splitPersonName($edit_teacher['teacher_name'] ?? '');

$GLOBALS['dashboard_from_pages'] = true;
$GLOBALS['dashboard_nav_active'] = 'teachers';
$GLOBALS['dashboard_page_title'] = t('Teacher Management') . ' — ' . t('Student Record System');
$GLOBALS['dashboard_heading'] = t('Teacher Management');
$GLOBALS['dashboard_subtitle'] = null;
include __DIR__ . '/../includes/dashboard_shell_start.php';
?>

    <div class="container py-2">
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

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <?php echo $edit_teacher ? 'Edit Teacher' : 'Add New Teacher'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php csrfInput(); ?>
                            <?php if ($edit_teacher): ?>
                                <input type="hidden" name="teacher_id" value="<?php echo (int)$edit_teacher['teacher_id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="teacher_first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="teacher_first_name" name="teacher_first_name"
                                       value="<?php echo $edit_teacher ? htmlspecialchars((string)$edit_teacher_name_parts['first_name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="teacher_last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="teacher_last_name" name="teacher_last_name"
                                       value="<?php echo $edit_teacher ? htmlspecialchars((string)$edit_teacher_name_parts['last_name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="subject_ids" class="form-label">Subjects Taught</label>
                                <select class="form-control" id="subject_ids" name="subject_ids[]" multiple size="6" required>
                                    <?php foreach ($subject_rows as $subject): ?>
                                        <option value="<?php echo (int)$subject['subject_id']; ?>"
                                                <?php echo in_array((int)$subject['subject_id'], $selected_subject_ids, true) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple subjects.</small>
                            </div>

                            <div class="mb-3">
                                <label for="assigned_grade" class="form-label">Assigned Grade</label>
                                <select class="form-control" id="assigned_grade" name="assigned_grade" required>
                                    <option value="">Select Grade</option>
                                    <?php
                                    $current_assigned_grade = $edit_teacher ? normalizeGradeLabel($edit_teacher['assigned_grade']) : '';
                                    foreach ($grade_rows as $grade_row):
                                        $grade_name = normalizeGradeLabel($grade_row['grade_name']);
                                        $is_selected = strtolower($grade_name) === strtolower($current_assigned_grade);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($grade_name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($grade_name, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_homeroom" name="is_homeroom"
                                           <?php echo $edit_teacher && $edit_teacher['is_homeroom'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_homeroom">
                                        Homeroom Teacher
                                    </label>
                                </div>
                                <small class="text-muted">Only one homeroom teacher per grade.</small>
                            </div>

                            <?php if ($can_manage_teacher_accounts): ?>
                                <hr>
                                <h6 class="mb-3">Teacher Login Security</h6>

                                <div class="mb-3">
                                 <label for="login_email" class="form-label">Login Email</label>
                                 <input type="email" class="form-control" id="login_email"
                                           value="<?php echo $edit_teacher ? htmlspecialchars((string)($edit_teacher['login_email'] ?? ''), ENT_QUOTES, 'UTF-8') : ''; ?>"
                                           placeholder="Auto-generated from teacher name" readonly
                                           data-domain="<?php echo htmlspecialchars(teacherLoginEmailDomain(), ENT_QUOTES, 'UTF-8'); ?>">
                                 <div class="form-text">Automatically generated from teacher name and always unique.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="login_password" class="form-label">
                                        <?php echo $edit_teacher && !empty($edit_teacher['login_user_id']) ? 'New Password' : 'Login Password'; ?>
                                    </label>
                                    <input type="password" class="form-control" id="login_password" name="login_password"
                                           <?php echo $edit_teacher && !empty($edit_teacher['login_user_id']) ? '' : 'required'; ?>>
                                    <div class="form-text">
                                        <?php if ($edit_teacher && !empty($edit_teacher['login_user_id'])): ?>
                                            Leave blank to keep the current teacher password.
                                        <?php else: ?>
                                            Required for new teacher login accounts. Minimum 6 characters.
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="login_password_confirm" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="login_password_confirm" name="login_password_confirm"
                                           <?php echo $edit_teacher && !empty($edit_teacher['login_user_id']) ? '' : 'required'; ?>>
                                    <div class="form-text">
                                        <?php if ($edit_teacher && !empty($edit_teacher['login_user_id'])): ?>
                                            Enter the same new password again when resetting.
                                        <?php else: ?>
                                            Re-enter the same password to confirm.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info py-2 mb-3" role="alert">
                                    Teacher login credentials are managed by admin.
                                </div>
                            <?php endif; ?>

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

            <div class="col-lg-8">
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
                                        <th>Subjects</th>
                                        <th>Assigned Grade</th>
                                        <?php if ($can_manage_teacher_accounts): ?>
                                            <th>Email</th>
                                            <th>Account</th>
                                        <?php endif; ?>
                                        <th>Homeroom</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo (int)$teacher['teacher_id']; ?></td>
                                            <td><?php echo htmlspecialchars($teacher['teacher_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['subjects_taught'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(normalizeGradeLabel($teacher['assigned_grade']), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <?php if ($can_manage_teacher_accounts): ?>
                                                <td><?php echo htmlspecialchars((string)($teacher['login_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <?php if (!empty($teacher['has_login'])): ?>
                                                        <span class="badge bg-success">Secured</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Missing Login</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <?php if ($teacher['is_homeroom']): ?>
                                                    <span class="badge bg-success">Yes</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                                            <td>
                                                <a href="teachers.php?edit=<?php echo (int)$teacher['teacher_id']; ?>"
                                                   class="btn btn-sm btn-warning">Edit</a>
                                                <a href="teachers.php?delete=<?php echo (int)$teacher['teacher_id']; ?>&csrf_token=<?php echo $csrf_token; ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Are you sure you want to delete this teacher and login account?')">Delete</a>
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

    <?php
    ob_start();
    ?>
    <script>
        (function () {
            var firstNameInput = document.getElementById('teacher_first_name');
            var lastNameInput = document.getElementById('teacher_last_name');
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
                return normalized === '' ? 'teacher' : normalized;
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
    <?php
    $GLOBALS['dashboard_extra_body_html'] = ob_get_clean();
    include __DIR__ . '/../includes/dashboard_shell_end.php';
    ?>
