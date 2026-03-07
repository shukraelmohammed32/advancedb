<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireAnyRole(['admin', 'teacher']);

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrfToken();

    $db = new Database();
    $conn = $db->getConnection();

    $name = $conn->real_escape_string($_POST['name']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $selected_grade = normalizeGradeLabel($_POST['grade'] ?? '');
    $academic_year = $conn->real_escape_string($_POST['academic_year']);
    $semester = $conn->real_escape_string($_POST['semester']);

    if ($selected_grade === '') {
        header('Location: ../pages/students.php?error=' . urlencode('Please select a grade'));
        exit();
    }

    $grade_name_safe = $conn->real_escape_string($selected_grade);
    $grade_lookup = $conn->query("SELECT grade_id, grade_name FROM grades WHERE grade_name = '$grade_name_safe' LIMIT 1");
    if (!$grade_lookup || $grade_lookup->num_rows === 0) {
        header('Location: ../pages/students.php?error=' . urlencode('Selected grade is invalid'));
        exit();
    }

    $grade_record = $grade_lookup->fetch_assoc();
    $grade_id = (int)$grade_record['grade_id'];
    $grade_name = $conn->real_escape_string($grade_record['grade_name']);

    $sql = "INSERT INTO students (name, gender, grade, grade_id, academic_year, semester)
            VALUES ('$name', '$gender', '$grade_name', $grade_id, '$academic_year', '$semester')";

    if ($conn->query($sql)) {
        header('Location: ../pages/students.php?success=' . urlencode('Student added successfully'));
    } else {
        header('Location: ../pages/students.php?error=' . urlencode('Error adding student'));
    }
    exit();
}
?>
