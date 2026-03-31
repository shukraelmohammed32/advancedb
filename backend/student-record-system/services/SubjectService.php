<?php

class SubjectService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getAllSubjects(mysqli $conn): array
    {
        $rows = [];
        $res = $conn->query('SELECT subject_id, subject_name, total_mark, created_at FROM subjects ORDER BY subject_name');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public static function addSubject(mysqli $conn, string $subjectName, int $totalMark): ?int
    {
        $name = $conn->real_escape_string(trim($subjectName));
        $totalMark = max(1, (int)$totalMark);
        if ($name === '') {
            return null;
        }
        $sql = "INSERT INTO subjects (subject_name, total_mark) VALUES ('$name', $totalMark)";
        if ($conn->query($sql)) {
            return (int)$conn->insert_id;
        }
        return null;
    }
}
