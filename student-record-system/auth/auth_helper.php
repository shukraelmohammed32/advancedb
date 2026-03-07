<?php
session_start();

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
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles)) {
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
?>
