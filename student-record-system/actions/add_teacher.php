<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $teacher_name = $conn->real_escape_string($_POST['teacher_name']);
    $department = $conn->real_escape_string($_POST['department']);
    $assigned_grade = $conn->real_escape_string($_POST['assigned_grade']);
    $is_homeroom = isset($_POST['is_homeroom']) ? 1 : 0;
    
    // If setting as homeroom teacher, unset previous homeroom teacher for that grade
    if ($is_homeroom) {
        $conn->query("UPDATE teachers SET is_homeroom = 0 WHERE assigned_grade = '$assigned_grade'");
    }
    
    $sql = "INSERT INTO teachers (teacher_name, department, assigned_grade, is_homeroom) 
            VALUES ('$teacher_name', '$department', '$assigned_grade', $is_homeroom)";
    
    if ($conn->query($sql)) {
        header("Location: ../pages/teachers.php?success=Teacher added successfully");
    } else {
        header("Location: ../pages/teachers.php?error=Error adding teacher");
    }
    exit();
}
?>