<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireAnyRole(['admin', 'teacher']);

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireValidCsrfToken();

    $db = new Database();
    $conn = $db->getConnection();

    $teacher_name = $conn->real_escape_string($_POST['teacher_name']);
    $assigned_grade = $conn->real_escape_string($_POST['assigned_grade']);
    $is_homeroom = isset($_POST['is_homeroom']) ? 1 : 0;

    $subject_ids = normalizeSubjectIds($_POST['subject_ids'] ?? []);

    // Backward compatibility: allow old single department post
    if (count($subject_ids) === 0 && !empty($_POST['department'])) {
        $department = $conn->real_escape_string($_POST['department']);
        $legacy_result = $conn->query("SELECT subject_id FROM subjects WHERE subject_name = '$department' LIMIT 1");
        if ($legacy_result && $legacy_result->num_rows > 0) {
            $row = $legacy_result->fetch_assoc();
            $subject_ids[] = (int)$row['subject_id'];
        }
    }

    if (count($subject_ids) === 0) {
        header('Location: ../pages/teachers.php?error=' . urlencode('Please assign at least one subject'));
        exit();
    }

    if ($is_homeroom) {
        $conn->query("UPDATE teachers SET is_homeroom = 0 WHERE assigned_grade = '$assigned_grade'");
    }

    $department = 'Multiple Subjects';
    if (count($subject_ids) === 1) {
        $subject_id = (int)$subject_ids[0];
        $single_subject = $conn->query("SELECT subject_name FROM subjects WHERE subject_id = $subject_id LIMIT 1");
        if ($single_subject && $single_subject->num_rows > 0) {
            $subject_row = $single_subject->fetch_assoc();
            $department = $subject_row['subject_name'];
        }
    }
    $department = $conn->real_escape_string($department);

    $conn->begin_transaction();

    $sql = "INSERT INTO teachers (teacher_name, department, assigned_grade, is_homeroom)
            VALUES ('$teacher_name', '$department', '$assigned_grade', $is_homeroom)";

    if (!$conn->query($sql)) {
        $conn->rollback();
        header('Location: ../pages/teachers.php?error=' . urlencode('Error adding teacher'));
        exit();
    }

    $teacher_id = (int)$conn->insert_id;

    if (!saveTeacherSubjects($conn, $teacher_id, $subject_ids)) {
        $conn->rollback();
        header('Location: ../pages/teachers.php?error=' . urlencode('Teacher added but subject mapping failed'));
        exit();
    }

    $conn->commit();
    header('Location: ../pages/teachers.php?success=' . urlencode('Teacher added successfully'));
    exit();
}
?>
