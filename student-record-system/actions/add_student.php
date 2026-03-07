<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';

requireAnyRole(['admin', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $name = $conn->real_escape_string($_POST['name']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $grade = $conn->real_escape_string($_POST['grade']);
    $academic_year = $conn->real_escape_string($_POST['academic_year']);
    $semester = $conn->real_escape_string($_POST['semester']);
    
    $sql = "INSERT INTO students (name, gender, grade, academic_year, semester) 
            VALUES ('$name', '$gender', '$grade', '$academic_year', '$semester')";
    
    if ($conn->query($sql)) {
        header("Location: ../pages/students.php?success=Student added successfully");
    } else {
        header("Location: ../pages/students.php?error=Error adding student");
    }
    exit();
}
?>