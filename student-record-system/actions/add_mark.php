<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';
require_once '../includes/distributed_coordinator.php';

if (!canEnterMarks()) { header('Location: ../index.php'); exit(); }

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
    $coordinator = new DistributedCoordinator($db);
    $distributed_ready = $coordinator->isDistributedReady();

    $student_id = (int)($_POST['student_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $score = (int)($_POST['score'] ?? -1);
    $site_id = $coordinator->studentSiteId($student_id);

    if ($score < 0 || $score > 100) {
        header('Location: ../pages/marks.php?error=' . urlencode('Score must be between 0 and 100'));
        exit();
    }

    if (!teacherCanTeachSubjectAndGrade($conn, $teacher_id, $subject_id, $student_id)) {
        header('Location: ../pages/marks.php?error=' . urlencode('Teacher must be assigned to both this subject and student grade'));
        exit();
    }

    $existing = $conn->query("SELECT mark_id FROM marks WHERE student_id=$student_id AND subject_id=$subject_id");
    if ($existing && $existing->num_rows > 0) {
        header('Location: ../pages/marks.php?error=' . urlencode('Mark already exists for this student and subject'));
        exit();
    }

    if ($distributed_ready) {
        $sql = "INSERT INTO marks (student_id, subject_id, teacher_id, site_id, score)
                VALUES ($student_id, $subject_id, $teacher_id, $site_id, $score)";
    } else {
        $sql = "INSERT INTO marks (student_id, subject_id, teacher_id, score)
                VALUES ($student_id, $subject_id, $teacher_id, $score)";
    }

    if ($conn->query($sql)) {
        $mark_id = (int)$conn->insert_id;
        $sync_ok = $coordinator->syncMark($mark_id);
        $message = $sync_ok
            ? 'Mark added successfully'
            : 'Mark added, but synchronization needs admin attention';
        header('Location: ../pages/marks.php?success=' . urlencode($message));
    } else {
        header('Location: ../pages/marks.php?error=' . urlencode('Error adding mark'));
    }
    exit();
}
?>

