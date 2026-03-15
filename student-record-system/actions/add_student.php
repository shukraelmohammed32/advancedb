<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';
require_once '../includes/distributed_coordinator.php';

if (!canManageStudentDirectory()) { header('Location: ../index.php'); exit(); }

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

    $allowed_grade_names = ['Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];

    $db = new Database();
    $conn = $db->getConnection();
    $coordinator = new DistributedCoordinator($db);
    $distributed_ready = $coordinator->isDistributedReady();
    $default_site_id = $coordinator->getDefaultSiteId();

    $name = $conn->real_escape_string($_POST['name']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $selected_grade = normalizeGradeLabel($_POST['grade'] ?? '');
    $academic_year = $conn->real_escape_string($_POST['academic_year']);
    $semester = $conn->real_escape_string($_POST['semester']);
    $site_id = $distributed_ready ? $coordinator->normalizeSiteId((int)($_POST['site_id'] ?? $default_site_id)) : $default_site_id;

    if ($selected_grade === '') {
        header('Location: ../pages/students.php?error=' . urlencode('Please select a grade'));
        exit();
    }

    if (!in_array($selected_grade, $allowed_grade_names, true)) {
        header('Location: ../pages/students.php?error=' . urlencode('Only Grade 9 to Grade 12 are allowed'));
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

    if ($distributed_ready) {
        $sql = "INSERT INTO students (name, gender, grade, grade_id, academic_year, semester, site_id)
                VALUES ('$name', '$gender', '$grade_name', $grade_id, '$academic_year', '$semester', $site_id)";
    } else {
        $sql = "INSERT INTO students (name, gender, grade, grade_id, academic_year, semester)
                VALUES ('$name', '$gender', '$grade_name', $grade_id, '$academic_year', '$semester')";
    }

    if ($conn->query($sql)) {
        $student_id = (int)$conn->insert_id;
        $sync_ok = $coordinator->syncStudent($student_id);
        $message = $sync_ok
            ? 'Student added and routed to ' . $coordinator->getSiteName($site_id)
            : 'Student added centrally, but branch sync failed';
        header('Location: ../pages/students.php?success=' . urlencode($message));
    } else {
        header('Location: ../pages/students.php?error=' . urlencode('Error adding student'));
    }
    exit();
}
?>
