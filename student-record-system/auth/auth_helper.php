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

function getCurrentUser() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'student_id' => $_SESSION['student_id'] ?? null,
        'teacher_id' => $_SESSION['teacher_id'] ?? null,
        'display_name' => $_SESSION['display_name'] ?? null
    ];
}

function canAccessTeacherDashboard() {
    return hasRole('admin') || hasRole('teacher');
}

function canAccessStudentRecords() {
    return hasRole('admin') || hasRole('teacher');
}

function canOnlyViewOwnRecords() {
    return hasRole('student');
}

function canManageMarks() {
    return hasRole('teacher');
}

function canViewStudentReports() {
    return hasRole('teacher') || hasRole('student');
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

