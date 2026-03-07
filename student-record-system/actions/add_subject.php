<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $subject_name = $conn->real_escape_string($_POST['subject_name']);
    $total_mark = (int)$_POST['total_mark'];
    
    $sql = "INSERT INTO subjects (subject_name, total_mark) 
            VALUES ('$subject_name', $total_mark)";
    
    if ($conn->query($sql)) {
        header("Location: ../pages/subjects.php?success=Subject added successfully");
    } else {
        header("Location: ../pages/subjects.php?error=Error adding subject");
    }
    exit();
}
?>