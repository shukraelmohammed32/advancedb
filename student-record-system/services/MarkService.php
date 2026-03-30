<?php

class MarkService
{
    public static function validateMark(int $score): bool
    {
        return $score >= 0 && $score <= 100;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getStudentMarks(mysqli $conn, int $studentId): array
    {
        $studentId = (int)$studentId;
        $sql = "SELECT m.mark_id, m.student_id, m.subject_id, m.teacher_id, m.score,
                       s.subject_name, COALESCE(t.teacher_name, '') AS teacher_name
                FROM marks m
                INNER JOIN subjects s ON s.subject_id = m.subject_id
                LEFT JOIN teachers t ON t.teacher_id = m.teacher_id
                WHERE m.student_id = $studentId
                ORDER BY s.subject_name";

        $rows = [];
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * @return array{ok:bool, error?:string, mark_id?:int}
     */
    public static function addMark(
        mysqli $conn,
        int $studentId,
        int $subjectId,
        int $teacherId,
        int $score,
        ?int $siteId = null
    ): array {
        if (!self::validateMark($score)) {
            return ['ok' => false, 'error' => 'Score must be between 0 and 100'];
        }

        $studentId = (int)$studentId;
        $subjectId = (int)$subjectId;
        $teacherId = (int)$teacherId;

        $check = $conn->query("SELECT mark_id FROM marks WHERE student_id = $studentId AND subject_id = $subjectId LIMIT 1");
        if ($check && $check->num_rows > 0) {
            return ['ok' => false, 'error' => 'Mark already exists for this student and subject'];
        }

        if ($siteId !== null) {
            $siteId = (int)$siteId;
            $sql = "INSERT INTO marks (student_id, subject_id, teacher_id, site_id, score)
                    VALUES ($studentId, $subjectId, $teacherId, $siteId, $score)";
        } else {
            $sql = "INSERT INTO marks (student_id, subject_id, teacher_id, score)
                    VALUES ($studentId, $subjectId, $teacherId, $score)";
        }

        if ($conn->query($sql)) {
            return ['ok' => true, 'mark_id' => (int)$conn->insert_id];
        }

        return ['ok' => false, 'error' => 'Database error creating mark'];
    }

    /**
     * @return array{ok:bool, error?:string}
     */
    public static function updateMark(
        mysqli $conn,
        int $markId,
        int $score,
        ?int $studentId = null,
        ?int $subjectId = null,
        ?int $teacherId = null
    ): array {
        if (!self::validateMark($score)) {
            return ['ok' => false, 'error' => 'Score must be between 0 and 100'];
        }

        $markId = (int)$markId;
        $parts = ["score = " . (int)$score];

        if ($studentId !== null) {
            $parts[] = 'student_id = ' . (int)$studentId;
        }
        if ($subjectId !== null) {
            $parts[] = 'subject_id = ' . (int)$subjectId;
        }
        if ($teacherId !== null) {
            $parts[] = 'teacher_id = ' . (int)$teacherId;
        }

        $sql = 'UPDATE marks SET ' . implode(', ', $parts) . " WHERE mark_id = $markId";
        if ($conn->query($sql)) {
            return ['ok' => true];
        }

        return ['ok' => false, 'error' => 'Database error updating mark'];
    }
}
