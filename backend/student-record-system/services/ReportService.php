<?php

/**
 * Academic report calculations: totals, averages, rank, pass/fail.
 * Uses existing schema: students.grade as class cohort, subjects.total_mark as cap per subject.
 */

require_once __DIR__ . '/../config/app_config.php';

class ReportService
{
    public static function normalizeGradeLabel($grade): string
    {
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

    /**
     * @return array{sum:int, max:int, count:int}|null
     */
    public static function getSubjectAggregates(mysqli $conn): ?array
    {
        $res = $conn->query('SELECT COUNT(*) AS cnt, COALESCE(SUM(total_mark), 0) AS max_pts FROM subjects');
        if (!$res) {
            return null;
        }
        $row = $res->fetch_assoc();
        return [
            'count' => (int)($row['cnt'] ?? 0),
            'max' => (int)($row['max_pts'] ?? 0),
            'sum' => (int)($row['max_pts'] ?? 0),
        ];
    }

    public static function calculateTotal(array $marksBySubjectName, int $totalSubjects): int
    {
        $total = 0;
        foreach ($marksBySubjectName as $row) {
            if (isset($row['score']) && $row['score'] !== null && $row['score'] !== '') {
                $total += (int)$row['score'];
            }
        }
        return $total;
    }

    public static function calculateAverage(int $totalScore, int $totalSubjects, bool $hasAllMarks): ?float
    {
        if ($totalSubjects <= 0 || !$hasAllMarks) {
            return null;
        }
        return round($totalScore / $totalSubjects, 2);
    }

    /**
     * Standard competition ranking (1,2,2,4) via MySQL RANK().
     *
     * @return int|string Rank or 'N/A'
     */
    public static function calculateRank(mysqli $conn, int $studentId, string $gradeRaw)
    {
        $grade = $conn->real_escape_string($gradeRaw);
        $sql = "SELECT student_rank
                FROM (
                    SELECT
                        s.student_id,
                        RANK() OVER (PARTITION BY s.grade ORDER BY COALESCE(SUM(m.score), 0) DESC) AS student_rank
                    FROM students s
                    LEFT JOIN marks m ON s.student_id = m.student_id
                    WHERE s.grade = '$grade'
                    GROUP BY s.student_id, s.grade
                ) ranked
                WHERE student_id = " . (int)$studentId;

        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return $row ? (int)$row['student_rank'] : 'N/A';
    }

    /**
     * Overall status when all subject rows are present in $marksWithMeta: each recorded score >= $passingScore.
     * Incomplete / no marks handled by caller.
     */
    public static function determineStatus(
        array $marksBySubjectName,
        int $totalSubjects,
        int $recordedCount,
        int $passingScore
    ): string {
        if ($totalSubjects <= 0) {
            return 'NO SUBJECTS';
        }
        if ($recordedCount === 0) {
            return 'NO MARKS';
        }
        if ($recordedCount < $totalSubjects) {
            return 'INCOMPLETE';
        }

        foreach ($marksBySubjectName as $row) {
            $score = $row['score'] ?? null;
            if ($score === null || $score === '') {
                return 'INCOMPLETE';
            }
            if ((int)$score < $passingScore) {
                return 'FAIL';
            }
        }

        return 'PASS';
    }

    /**
     * @param array $studentRow associative student row including grade
     * @param mysqli $conn
     * @return array{
     *   student: array,
     *   marks: array<string, array>,
     *   total: int,
     *   max_total: int,
     *   average: float|null,
     *   rank: int|string,
     *   rank_label: string,
     *   status: string,
     *   recorded_subjects: int,
     *   total_subjects: int,
     *   has_all_marks: bool,
     *   passing_score: int
     * }
     */
    public static function generateReport(mysqli $conn, array $studentRow, int $studentId): array
    {
        $passingScore = (int)AppConfig::getPassingScore();
        $studentId = (int)$studentId;

        $agg = self::getSubjectAggregates($conn);
        $totalSubjects = $agg ? $agg['count'] : 0;
        $maxTotal = $agg ? $agg['max'] : 0;

        $marks_query = "SELECT
                            s.subject_name,
                            m.score,
                            COALESCE(t.teacher_name, '') AS teacher_name,
                            CASE
                                WHEN m.score IS NULL THEN 'FAIL'
                                WHEN m.score >= $passingScore THEN 'PASS'
                                ELSE 'FAIL'
                            END AS status
                        FROM subjects s
                        LEFT JOIN marks m ON s.subject_id = m.subject_id AND m.student_id = $studentId
                        LEFT JOIN teachers t ON t.teacher_id = m.teacher_id
                        ORDER BY s.subject_name";

        $marks_result = $conn->query($marks_query);
        $marks = [];
        $recorded = 0;
        $total_score = 0;

        if ($marks_result) {
            while ($mark = $marks_result->fetch_assoc()) {
                $marks[$mark['subject_name']] = $mark;
                if ($mark['score'] !== null && $mark['score'] !== '') {
                    $total_score += (int)$mark['score'];
                    $recorded++;
                }
            }
        }

        $has_all_marks = $totalSubjects > 0 && $recorded >= $totalSubjects;
        $average = self::calculateAverage($total_score, $totalSubjects, $has_all_marks);
        $status = self::determineStatus($marks, $totalSubjects, $recorded, $passingScore);

        $norm = self::normalizeGradeLabel($studentRow['grade']);
        $rank = self::calculateRank($conn, $studentId, $studentRow['grade']);

        return [
            'student' => $studentRow,
            'marks' => $marks,
            'total' => $total_score,
            'max_total' => $maxTotal,
            'average' => $average,
            'rank' => $rank,
            'rank_label' => 'Rank in ' . $norm,
            'status' => $status,
            'recorded_subjects' => $recorded,
            'total_subjects' => $totalSubjects,
            'has_all_marks' => $has_all_marks,
            'passing_score' => $passingScore,
        ];
    }
}
