<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: auth/login.php");
        exit();
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function isAdmin() {
    return hasRole('admin');
}

function isTeacher() {
    return hasRole('teacher');
}

function isStudent() {
    return hasRole('student');
}

function isHomeroomTeacher() {
    return isTeacher() && !empty($_SESSION['is_homeroom']);
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        $_SESSION['error'] = "Access denied. You don't have permission to access this page.";
        header("Location: ../index.php");
        exit();
    }
}

function requireAnyRole($roles) {
    requireLogin();
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles, true)) {
        $_SESSION['error'] = "Access denied. You don't have permission to access this page.";
        header("Location: ../index.php");
        exit();
    }
}

function getRoleLabel() {
    if (isHomeroomTeacher()) {
        return 'Homeroom Teacher';
    }

    if (isAdmin()) {
        return 'Administrator';
    }

    if (isStudent()) {
        return 'Student';
    }

    if (isTeacher()) {
        return 'Teacher';
    }

    return 'User';
}

function getCurrentUser() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'role_label' => getRoleLabel(),
        'email' => $_SESSION['email'] ?? null,
        'student_id' => $_SESSION['student_id'] ?? null,
        'teacher_id' => $_SESSION['teacher_id'] ?? null,
        'is_homeroom' => $_SESSION['is_homeroom'] ?? 0,
        'assigned_grade' => $_SESSION['assigned_grade'] ?? null,
        'display_name' => $_SESSION['display_name'] ?? null
    ];
}

function canAccessTeacherDashboard() {
    return isAdmin() || isTeacher();
}

function canViewStudentDirectory() {
    return isAdmin() || isTeacher();
}

function canManageStudentDirectory() {
    return isAdmin();
}

function canAccessStudentRecords() {
    return canViewStudentDirectory();
}

function canViewSubjects() {
    return isAdmin() || isTeacher();
}

function canAccessSubjects() {
    return canViewSubjects();
}

function canManageSubjects() {
    return isAdmin();
}

function canManageTeachers() {
    return isAdmin();
}

function canManageAcademicYear() {
    return isAdmin();
}

function canManageGrades() {
    return isAdmin();
}

function canViewOwnAcademicRecords() {
    return isStudent();
}

function canOnlyViewOwnRecords() {
    return canViewOwnAcademicRecords();
}

function canEnterMarks() {
    return isTeacher();
}

function canManageSubjectResults() {
    return isTeacher();
}

function canSubmitMarks() {
    return isTeacher();
}

function canManageMarks() {
    return canEnterMarks();
}

function canGenerateSchoolReports() {
    return isAdmin();
}

function canCompileFinalResults() {
    return isAdmin() || isHomeroomTeacher();
}

function canGenerateStudentReports() {
    return canGenerateSchoolReports() || isHomeroomTeacher();
}

function canViewStudentReports() {
    return canGenerateStudentReports() || canViewOwnAcademicRecords();
}

function canAccessSummary() {
    return canCompileFinalResults();
}

function canAccessDistributedCoordinator() {
    return isAdmin();
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfInput() {
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    echo '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function isValidCsrfToken($token) {
    if (!is_string($token) || $token === '') {
        return false;
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireValidCsrfToken() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

    if (!isValidCsrfToken($token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}
?>
