<?php

/**
 * REST-style JSON API (session cookie auth, same as web app).
 * Base URL examples (AcceptPathInfo / MultiViews typical on XAMPP Apache):
 *   .../api/index.php/students
 *   .../api/index.php/students/5
 *   .../api/index.php/marks
 *   .../api/index.php/marks/12
 *   .../api/index.php/marks/student/5
 *   .../api/index.php/reports/student/5
 *   .../api/index.php/reports/class/Grade%2010
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../auth/auth_helper.php';
require_once __DIR__ . '/../includes/distributed_coordinator.php';
require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../services/MarkService.php';
require_once __DIR__ . '/../services/StudentService.php';
require_once __DIR__ . '/../services/SubjectService.php';
require_once __DIR__ . '/../services/TeacherService.php';
require_once __DIR__ . '/api_helpers.php';

if (!isLoggedIn()) {
    apiJsonResponse(['ok' => false, 'error' => 'Authentication required'], 401);
}

$db = new Database();
$conn = $db->getConnection();
$coordinator = new DistributedCoordinator($db);
$distributed_ready = $coordinator->isDistributedReady();
$default_site_id = $coordinator->getDefaultSiteId();

$path = $_SERVER['PATH_INFO'] ?? '';
$segments = array_values(array_filter(explode('/', trim((string)$path, '/'))));
$method = $_SERVER['REQUEST_METHOD'];

$is_admin = hasRole('admin');
$is_teacher = hasRole('teacher');
$is_student = hasRole('student');
$is_homeroom = isHomeroomTeacher();
$session_teacher_id = $is_teacher ? (int)($_SESSION['teacher_id'] ?? 0) : 0;
$session_student_id = $is_student ? (int)($_SESSION['student_id'] ?? 0) : 0;

// --- GET /students (?q= optional search, ?grade= optional for admin) ---
if ($segments === ['students'] && $method === 'GET') {
    if (!$is_admin && !$is_teacher) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $apiSearchQ = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $apiGrade = '';
    if ($is_admin && isset($_GET['grade']) && trim((string)$_GET['grade']) !== '') {
        $apiGrade = apiNormalizeGradeLabel((string)$_GET['grade']);
        $allowed = ['Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
        if (!in_array($apiGrade, $allowed, true)) {
            $apiGrade = '';
        }
    }
    if ($is_teacher && !$is_admin) {
        $grade = apiNormalizeGradeLabel((string)($_SESSION['assigned_grade'] ?? ''));
        if ($grade === '') {
            apiJsonResponse(['ok' => false, 'error' => 'Teacher account has no assigned grade'], 403);
        }
        $list = StudentService::listStudents($conn, $grade, $apiSearchQ !== '' ? $apiSearchQ : null);
    } else {
        $list = StudentService::listStudents($conn, $apiGrade !== '' ? $apiGrade : null, $apiSearchQ !== '' ? $apiSearchQ : null);
    }
    apiJsonResponse(['ok' => true, 'students' => $list]);
}

// --- GET /students/:id ---
if (count($segments) === 2 && $segments[0] === 'students' && ctype_digit($segments[1]) && $method === 'GET') {
    $sid = (int)$segments[1];
    $row = StudentService::getStudentById($conn, $sid);
    if (!$row) {
        apiJsonResponse(['ok' => false, 'error' => 'Student not found'], 404);
    }
    if ($is_student && $sid !== $session_student_id) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    if ($is_teacher && !$is_admin && !apiTeacherCanAccessStudent($conn, $session_teacher_id, $sid)) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    apiJsonResponse(['ok' => true, 'student' => $row]);
}

// --- POST /students ---
if ($segments === ['students'] && $method === 'POST') {
    if (!canManageStudentDirectory()) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $body = apiReadJsonBody();
    $allowed_grade_names = ['Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
    $name = trim((string)($body['name'] ?? ''));
    $gender = trim((string)($body['gender'] ?? ''));
    $selected_grade = apiNormalizeGradeLabel((string)($body['grade'] ?? ''));
    $academic_year = trim((string)($body['academic_year'] ?? ''));
    $semester = trim((string)($body['semester'] ?? ''));

    if ($name === '' || $gender === '' || $selected_grade === '' || $academic_year === '' || $semester === '') {
        apiJsonResponse(['ok' => false, 'error' => 'Missing required fields'], 422);
    }
    if (!in_array($selected_grade, $allowed_grade_names, true)) {
        apiJsonResponse(['ok' => false, 'error' => 'Invalid grade'], 422);
    }

    $grade_name_safe = $conn->real_escape_string($selected_grade);
    $grade_lookup = $conn->query("SELECT grade_id, grade_name FROM grades WHERE grade_name = '$grade_name_safe' LIMIT 1");
    if (!$grade_lookup || $grade_lookup->num_rows === 0) {
        apiJsonResponse(['ok' => false, 'error' => 'Grade not found in database'], 422);
    }
    $grade_record = $grade_lookup->fetch_assoc();
    $grade_id = (int)$grade_record['grade_id'];
    $grade_name = (string)$grade_record['grade_name'];

    $site_id = $distributed_ready ? $coordinator->normalizeSiteId((int)($body['site_id'] ?? $default_site_id)) : $default_site_id;
    $newId = StudentService::createStudent($conn, [
        'name' => $name,
        'gender' => $gender,
        'grade' => $grade_name,
        'grade_id' => $grade_id,
        'academic_year' => $academic_year,
        'semester' => $semester,
        'site_id' => $site_id,
    ], $distributed_ready);

    if (!$newId) {
        apiJsonResponse(['ok' => false, 'error' => 'Could not create student'], 500);
    }
    $coordinator->syncStudent($newId);
    apiJsonResponse(['ok' => true, 'student_id' => $newId], 201);
}

// --- POST /marks ---
if ($segments === ['marks'] && $method === 'POST') {
    if (!canEnterMarks()) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $body = apiReadJsonBody();
    $student_id = (int)($body['student_id'] ?? 0);
    $subject_id = (int)($body['subject_id'] ?? 0);
    $teacher_id = $is_teacher ? $session_teacher_id : (int)($body['teacher_id'] ?? 0);
    $score = (int)($body['score'] ?? -1);

    if ($student_id <= 0 || $subject_id <= 0 || $teacher_id <= 0) {
        apiJsonResponse(['ok' => false, 'error' => 'student_id, subject_id, and teacher_id required'], 422);
    }
    if (!apiTeacherCanTeachSubjectAndGrade($conn, $teacher_id, $subject_id, $student_id)) {
        apiJsonResponse(['ok' => false, 'error' => 'Teacher not assigned to this subject and grade'], 422);
    }

    $site_id = $distributed_ready ? $coordinator->studentSiteId($student_id) : null;
    $res = $distributed_ready
        ? MarkService::addMark($conn, $student_id, $subject_id, $teacher_id, $score, $site_id)
        : MarkService::addMark($conn, $student_id, $subject_id, $teacher_id, $score, null);

    if (!$res['ok']) {
        apiJsonResponse(['ok' => false, 'error' => $res['error'] ?? 'Error'], 422);
    }
    $mark_id = (int)$res['mark_id'];
    $coordinator->syncMark($mark_id);
    apiJsonResponse(['ok' => true, 'mark_id' => $mark_id], 201);
}

// --- PUT /marks/:id ---
if (count($segments) === 2 && $segments[0] === 'marks' && ctype_digit($segments[1]) && $method === 'PUT') {
    if (!canEnterMarks()) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $mark_id = (int)$segments[1];
    $body = apiReadJsonBody();
    $score = (int)($body['score'] ?? -1);

    if ($is_teacher) {
        $own = $conn->query("SELECT teacher_id FROM marks WHERE mark_id = $mark_id LIMIT 1");
        $row = $own ? $own->fetch_assoc() : null;
        if (!$row || (int)$row['teacher_id'] !== $session_teacher_id) {
            apiJsonResponse(['ok' => false, 'error' => 'You can update only your own marks'], 403);
        }
    }

    $site_id = null;
    if ($distributed_ready) {
        $info = $conn->query("SELECT student_id FROM marks WHERE mark_id = $mark_id LIMIT 1");
        $mr = $info ? $info->fetch_assoc() : null;
        if (!$mr) {
            apiJsonResponse(['ok' => false, 'error' => 'Mark not found'], 404);
        }
        $site_id = $coordinator->studentSiteId((int)$mr['student_id']);
    }

    $extra = [];
    if (isset($body['student_id'])) {
        $extra['student_id'] = (int)$body['student_id'];
    }
    if (isset($body['subject_id'])) {
        $extra['subject_id'] = (int)$body['subject_id'];
    }
    if (isset($body['teacher_id'])) {
        $extra['teacher_id'] = (int)$body['teacher_id'];
    }
    if ($is_teacher) {
        unset($extra['teacher_id']);
    }

    $upd = MarkService::updateMark($conn, $mark_id, $score, $extra['student_id'] ?? null, $extra['subject_id'] ?? null, $extra['teacher_id'] ?? null);
    if (!$upd['ok']) {
        apiJsonResponse(['ok' => false, 'error' => $upd['error'] ?? 'Error'], 422);
    }

    if ($distributed_ready && $site_id !== null) {
        $conn->query("UPDATE marks SET site_id = $site_id WHERE mark_id = $mark_id");
    }
    $coordinator->syncMark($mark_id);
    apiJsonResponse(['ok' => true, 'mark_id' => $mark_id]);
}

// --- GET /marks/student/:id ---
if (count($segments) === 3 && $segments[0] === 'marks' && $segments[1] === 'student' && ctype_digit($segments[2]) && $method === 'GET') {
    $sid = (int)$segments[2];
    if ($is_student && $sid !== $session_student_id) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    if ($is_teacher && !$is_admin && !apiTeacherCanAccessStudent($conn, $session_teacher_id, $sid)) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    if (!$is_admin && !$is_teacher && !$is_student) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $marks = MarkService::getStudentMarks($conn, $sid);
    apiJsonResponse(['ok' => true, 'marks' => $marks]);
}

// --- GET /reports/student/:id ---
if (count($segments) === 3 && $segments[0] === 'reports' && $segments[1] === 'student' && ctype_digit($segments[2]) && $method === 'GET') {
    if (!canViewStudentReports()) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $sid = (int)$segments[2];
    if ($is_student && $sid !== $session_student_id) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    if ($is_teacher && !$is_admin && !apiTeacherCanAccessStudent($conn, $session_teacher_id, $sid)) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $student = StudentService::getStudentById($conn, $sid);
    if (!$student) {
        apiJsonResponse(['ok' => false, 'error' => 'Student not found'], 404);
    }
    $report = ReportService::generateReport($conn, $student, $sid);
    apiJsonResponse(['ok' => true, 'report' => $report]);
}

// --- GET /reports/class/:classId (grade label, URL-encoded) ---
if (count($segments) === 3 && $segments[0] === 'reports' && $segments[1] === 'class' && $method === 'GET') {
    if (!canViewStudentReports()) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $classRaw = urldecode((string)$segments[2]);
    $normalized = apiNormalizeGradeLabel($classRaw);

    if ($is_teacher && !$is_admin) {
        $tg = apiNormalizeGradeLabel((string)($_SESSION['assigned_grade'] ?? ''));
        if ($tg === '' || !apiGradesMatch($tg, $normalized)) {
            apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
        }
    }

    $gEsc = $conn->real_escape_string($normalized);
    $res = $conn->query("SELECT * FROM students WHERE grade = '$gEsc' ORDER BY name");
    $reports = [];
    if ($res) {
        while ($st = $res->fetch_assoc()) {
            $reports[] = ReportService::generateReport($conn, $st, (int)$st['student_id']);
        }
    }
    apiJsonResponse(['ok' => true, 'class_grade' => $normalized, 'reports' => $reports]);
}

// Subjects listing (helpful for API consumers)
if ($segments === ['subjects'] && $method === 'GET') {
    if (!canViewSubjects()) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    apiJsonResponse(['ok' => true, 'subjects' => SubjectService::getAllSubjects($conn)]);
}

if ($segments === ['subjects'] && $method === 'POST') {
    if (!canManageSubjects()) {
        apiJsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $body = apiReadJsonBody();
    $subject_name = (string)($body['subject_name'] ?? '');
    $total_mark = (int)($body['total_mark'] ?? 100);
    $id = SubjectService::addSubject($conn, $subject_name, $total_mark);
    if (!$id) {
        apiJsonResponse(['ok' => false, 'error' => 'Could not add subject'], 422);
    }
    apiJsonResponse(['ok' => true, 'subject_id' => $id], 201);
}

apiJsonResponse(['ok' => false, 'error' => 'Not found', 'hint' => 'Use PATH_INFO after index.php, e.g. index.php/students'], 404);
