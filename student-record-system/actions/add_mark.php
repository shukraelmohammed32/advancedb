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

function gradesMatch($left_grade, $right_grade) {
    return strtolower(normalizeGradeLabel($left_grade)) === strtolower(normalizeGradeLabel($right_grade));
}

function teacherCanTeachSubjectAndGrade($conn, $teacher_id, $subject_id, $student_id) {
    $teacher_id = (int)$teacher_id;
    $subject_id = (int)$subject_id;
    $student_id = (int)$student_id;

    $sql = "SELECT t.assigned_grade, s.grade
            FROM teachers t
            JOIN students s ON s.student_id = $student_id
            JOIN teacher_subjects ts ON ts.teacher_id = t.teacher_id AND ts.subject_id = $subject_id
            WHERE t.teacher_id = $teacher_id
            LIMIT 1";

    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        return false;
    }

    $row = $result->fetch_assoc();
    return gradesMatch($row['assigned_grade'], $row['grade']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrfToken();

    $db = new Database();
    $conn = $db->getConnection();

    $student_id = (int)$_POST['student_id'];
    $subject_id = (int)$_POST['subject_id'];
    $teacher_id = (int)$_POST['teacher_id'];
    $score = (int)$_POST['score'];

    // Validate score
    if ($score < 0 || $score > 100) {
        header('Location: ../pages/marks.php?error=' . urlencode('Score must be between 0 and 100'));
        exit();
    }

    if (!teacherCanTeachSubjectAndGrade($conn, $teacher_id, $subject_id, $student_id)) {
        header('Location: ../pages/marks.php?error=' . urlencode('Teacher must be assigned to both this subject and student grade'));
        exit();
    }

    // Check if mark already exists for this student and subject
    $existing = $conn->query("SELECT mark_id FROM marks WHERE student_id=$student_id AND subject_id=$subject_id");

    if ($existing && $existing->num_rows > 0) {
        header('Location: ../pages/marks.php?error=' . urlencode('Mark already exists for this student and subject'));
        exit();
    }

    $sql = "INSERT INTO marks (student_id, subject_id, teacher_id, score)
            VALUES ($student_id, $subject_id, $teacher_id, $score)";

    if ($conn->query($sql)) {
        header('Location: ../pages/marks.php?success=' . urlencode('Mark added successfully'));
    } else {
        header('Location: ../pages/marks.php?error=' . urlencode('Error adding mark'));
    }
    exit();
}
?>
