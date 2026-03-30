<?php

function apiJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function apiReadJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function apiNormalizeGradeLabel($grade): string
{
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

function apiGradesMatch($left_grade, $right_grade): bool
{
    return strtolower(apiNormalizeGradeLabel($left_grade)) === strtolower(apiNormalizeGradeLabel($right_grade));
}

function apiTeacherCanAccessStudent(mysqli $conn, int $teacher_id, int $student_id): bool
{
    $teacher_id = (int)$teacher_id;
    $student_id = (int)$student_id;
    if ($teacher_id <= 0 || $student_id <= 0) {
        return false;
    }
    $sql = "SELECT t.assigned_grade, s.grade
            FROM teachers t
            JOIN students s ON s.student_id = $student_id
            WHERE t.teacher_id = $teacher_id
            LIMIT 1";
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        return false;
    }
    $row = $result->fetch_assoc();
    return apiGradesMatch($row['assigned_grade'], $row['grade']);
}

function apiTeacherCanTeachSubjectAndGrade(mysqli $conn, int $teacher_id, int $subject_id, int $student_id): bool
{
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
    return apiGradesMatch($row['assigned_grade'], $row['grade']);
}
