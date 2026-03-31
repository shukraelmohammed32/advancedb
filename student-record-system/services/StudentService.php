<?php

class StudentService
{
    /**
     * @return array<string, mixed>|null
     */
    public static function getStudentById(mysqli $conn, int $studentId): ?array
    {
        $studentId = (int)$studentId;
        $res = $conn->query("SELECT * FROM students WHERE student_id = $studentId LIMIT 1");
        if (!$res || $res->num_rows === 0) {
            return null;
        }
        return $res->fetch_assoc();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listStudents(mysqli $conn, ?string $gradeFilter = null, ?string $searchQuery = null): array
    {
        $conditions = [];
        if ($gradeFilter !== null && $gradeFilter !== '') {
            $g = $conn->real_escape_string($gradeFilter);
            $conditions[] = "s.grade = '$g'";
        }

        $q = $searchQuery !== null ? trim($searchQuery) : '';
        if ($q !== '') {
            $esc = $conn->real_escape_string($q);
            $like = "'%" . $esc . "%'";
            $conditions[] = "(s.name LIKE $like
                OR s.first_name LIKE $like
                OR s.last_name LIKE $like
                OR CONCAT(TRIM(COALESCE(s.first_name,'')), ' ', TRIM(COALESCE(s.last_name,''))) LIKE $like
                OR CAST(s.student_id AS CHAR) LIKE $like
                OR s.grade LIKE $like
                OR s.academic_year LIKE $like
                OR s.semester LIKE $like
                OR s.gender LIKE $like)";
        }

        $where = $conditions === [] ? '' : ('WHERE ' . implode(' AND ', $conditions));

        $rows = [];
        $res = $conn->query("SELECT s.* FROM students s $where ORDER BY s.grade_id ASC, s.grade ASC, s.name ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * @param array{name:string,gender:string,grade:string,grade_id:int,academic_year:string,semester:string,site_id?:?int} $data
     */
    public static function createStudent(mysqli $conn, array $data, bool $includeSiteId): ?int
    {
        $name = $conn->real_escape_string($data['name']);
        $gender = $conn->real_escape_string($data['gender']);
        $grade = $conn->real_escape_string($data['grade']);
        $grade_id = (int)$data['grade_id'];
        $academic_year = $conn->real_escape_string($data['academic_year']);
        $semester = $conn->real_escape_string($data['semester']);

        if ($includeSiteId) {
            $site_id = (int)($data['site_id'] ?? 0);
            $sql = "INSERT INTO students (name, gender, grade, grade_id, academic_year, semester, site_id)
                    VALUES ('$name', '$gender', '$grade', $grade_id, '$academic_year', '$semester', $site_id)";
        } else {
            $sql = "INSERT INTO students (name, gender, grade, grade_id, academic_year, semester)
                    VALUES ('$name', '$gender', '$grade', $grade_id, '$academic_year', '$semester')";
        }

        if ($conn->query($sql)) {
            return (int)$conn->insert_id;
        }
        return null;
    }

    /**
     * @param array{name?:string,gender?:string,grade?:string,grade_id?:int,academic_year?:string,semester?:string,site_id?:?int} $data
     */
    public static function updateStudent(mysqli $conn, int $studentId, array $data, bool $includeSiteId): bool
    {
        $studentId = (int)$studentId;
        $parts = [];

        if (isset($data['name'])) {
            $parts[] = "name = '" . $conn->real_escape_string($data['name']) . "'";
        }
        if (isset($data['gender'])) {
            $parts[] = "gender = '" . $conn->real_escape_string($data['gender']) . "'";
        }
        if (isset($data['grade'])) {
            $parts[] = "grade = '" . $conn->real_escape_string($data['grade']) . "'";
        }
        if (isset($data['grade_id'])) {
            $parts[] = 'grade_id = ' . (int)$data['grade_id'];
        }
        if (isset($data['academic_year'])) {
            $parts[] = "academic_year = '" . $conn->real_escape_string($data['academic_year']) . "'";
        }
        if (isset($data['semester'])) {
            $parts[] = "semester = '" . $conn->real_escape_string($data['semester']) . "'";
        }
        if ($includeSiteId && array_key_exists('site_id', $data)) {
            $parts[] = 'site_id = ' . (int)$data['site_id'];
        }

        if ($parts === []) {
            return false;
        }

        $sql = 'UPDATE students SET ' . implode(', ', $parts) . " WHERE student_id = $studentId";
        return (bool)$conn->query($sql);
    }
}
