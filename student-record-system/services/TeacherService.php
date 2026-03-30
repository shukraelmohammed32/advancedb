<?php

class TeacherService
{
    public static function assignTeacherToDepartment(mysqli $conn, int $teacherId, string $department): bool
    {
        $teacherId = (int)$teacherId;
        $department = $conn->real_escape_string(trim($department));
        if ($teacherId <= 0 || $department === '') {
            return false;
        }
        $sql = "UPDATE teachers SET department = '$department' WHERE teacher_id = $teacherId";
        return (bool)$conn->query($sql);
    }

    public static function assignHomeroomTeacher(mysqli $conn, int $teacherId, bool $isHomeroom): bool
    {
        $teacherId = (int)$teacherId;
        if ($teacherId <= 0) {
            return false;
        }
        $flag = $isHomeroom ? 1 : 0;
        $sql = "UPDATE teachers SET is_homeroom = $flag WHERE teacher_id = $teacherId";
        return (bool)$conn->query($sql);
    }
}
