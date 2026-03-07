<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireAnyRole(['admin', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $student_id = (int)$_POST['student_id'];
    $subject_id = (int)$_POST['subject_id'];
    $teacher_id = (int)$_POST['teacher_id'];
    $score = (int)$_POST['score'];
    
    // Validate score
    if ($score < 0 || $score > 100) {
        header("Location: ../pages/marks.php?error=Score must be between 0 and 100");
        exit();
    }
    
    // Check if mark already exists for this student and subject
    $existing = $conn->query("SELECT mark_id FROM marks WHERE student_id=$student_id AND subject_id=$subject_id");
    
    if ($existing->num_rows > 0) {
        header("Location: ../pages/marks.php?error=Mark already exists for this student and subject");
        exit();
    }
    
    $sql = "INSERT INTO marks (student_id, subject_id, teacher_id, score) 
            VALUES ($student_id, $subject_id, $teacher_id, $score)";
    
    if ($conn->query($sql)) {
        header("Location: ../pages/marks.php?success=Mark added successfully");
    } else {
        header("Location: ../pages/marks.php?error=Error adding mark");
    }
    exit();
}
?>