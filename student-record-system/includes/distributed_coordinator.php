<?php
require_once __DIR__ . '/../config/database.php';

class DistributedCoordinator {
    private $database;
    private $central;
    private $sites_cache = null;
    private $distributed_ready = null;

    public function __construct(Database $database) {
        $this->database = $database;
        $this->central = $database->getCentralConnection();
    }

    private function tableExists($table_name) {
        $table_name = $this->central->real_escape_string($table_name);
        $result = $this->central->query("SHOW TABLES LIKE '$table_name'");
        return $result && $result->num_rows > 0;
    }

    private function columnExists($table_name, $column_name) {
        $table_name = $this->central->real_escape_string($table_name);
        $column_name = $this->central->real_escape_string($column_name);
        $result = $this->central->query("SHOW COLUMNS FROM `$table_name` LIKE '$column_name'");
        return $result && $result->num_rows > 0;
    }

    public function isDistributedReady() {
        if ($this->distributed_ready !== null) {
            return $this->distributed_ready;
        }

        $this->distributed_ready = $this->tableExists('distributed_sites')
            && $this->tableExists('coordinator_sync_log')
            && $this->columnExists('students', 'site_id')
            && $this->columnExists('marks', 'site_id');

        return $this->distributed_ready;
    }

    public function getSites($active_only = true) {
        if ($this->sites_cache !== null) {
            if (!$active_only) {
                return $this->sites_cache;
            }

            return array_values(array_filter($this->sites_cache, static function ($site) {
                return !isset($site['is_active']) || (int)$site['is_active'] === 1;
            }));
        }

        if (!$this->isDistributedReady()) {
            $this->sites_cache = [[
                'site_id' => 1,
                'site_code' => 'CENTRAL',
                'site_name' => 'Central Coordinator',
                'db_name' => $this->database->getDefaultDatabaseName(),
                'is_default' => 1,
                'is_active' => 1,
            ]];
            return $this->sites_cache;
        }

        $sites = [];
        $sql = 'SELECT site_id, site_code, site_name, db_name, is_default, is_active FROM distributed_sites ORDER BY is_default DESC, site_name ASC';
        $result = $this->central->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $sites[] = $row;
            }
        }

        $this->sites_cache = $sites;
        if (!$active_only) {
            return $sites;
        }

        return array_values(array_filter($sites, static function ($site) {
            return (int)$site['is_active'] === 1;
        }));
    }

    public function getSiteById($site_id) {
        $site_id = (int)$site_id;
        foreach ($this->getSites(false) as $site) {
            if ((int)$site['site_id'] === $site_id) {
                return $site;
            }
        }

        return null;
    }

    public function getDefaultSiteId() {
        foreach ($this->getSites(false) as $site) {
            if ((int)($site['is_default'] ?? 0) === 1) {
                return (int)$site['site_id'];
            }
        }

        $sites = $this->getSites();
        return empty($sites) ? 1 : (int)$sites[0]['site_id'];
    }

    public function normalizeSiteId($site_id) {
        $site = $this->getSiteById((int)$site_id);
        if ($site && (int)($site['is_active'] ?? 1) === 1) {
            return (int)$site['site_id'];
        }

        return $this->getDefaultSiteId();
    }

    public function getSiteName($site_id) {
        $site = $this->getSiteById((int)$site_id);
        return $site ? (string)$site['site_name'] : 'Central Coordinator';
    }

    private function connectionTableExists($connection, $table_name) {
        if (!($connection instanceof mysqli)) {
            return false;
        }

        $table_name = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table_name);
        if ($table_name === '') {
            return false;
        }

        $table_name_safe = $connection->real_escape_string($table_name);
        $result = $connection->query("SHOW TABLES LIKE '$table_name_safe'");
        return $result && $result->num_rows > 0;
    }

    private function connectionColumnExists($connection, $table_name, $column_name) {
        if (!($connection instanceof mysqli) || !$this->connectionTableExists($connection, $table_name)) {
            return false;
        }

        $table_name = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table_name);
        $column_name = preg_replace('/[^A-Za-z0-9_]/', '', (string)$column_name);
        if ($table_name === '' || $column_name === '') {
            return false;
        }

        $column_name_safe = $connection->real_escape_string($column_name);
        $result = $connection->query("SHOW COLUMNS FROM `$table_name` LIKE '$column_name_safe'");
        return $result && $result->num_rows > 0;
    }

    private function connectionTableCount($connection, $table_name) {
        if (!$this->connectionTableExists($connection, $table_name)) {
            return 0;
        }

        $table_name = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table_name);
        $result = $connection->query("SELECT COUNT(*) AS total FROM `$table_name`");
        if (!$result || $result->num_rows === 0) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    private function connectionMaxTimestamp($connection, $table_name, $column_name) {
        if (!$this->connectionColumnExists($connection, $table_name, $column_name)) {
            return null;
        }

        $table_name = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table_name);
        $column_name = preg_replace('/[^A-Za-z0-9_]/', '', (string)$column_name);
        $result = $connection->query("SELECT MAX(`$column_name`) AS latest_value FROM `$table_name`");
        if (!$result || $result->num_rows === 0) {
            return null;
        }

        $row = $result->fetch_assoc();
        $value = isset($row['latest_value']) ? trim((string)$row['latest_value']) : '';
        return $value === '' ? null : $value;
    }

    private function newestTimestamp(array $timestamps) {
        $timestamps = array_values(array_filter($timestamps, static function ($value) {
            return is_string($value) && trim($value) !== '';
        }));

        if (empty($timestamps)) {
            return null;
        }

        rsort($timestamps);
        return $timestamps[0];
    }

    private function connectionQueryRows($connection, $sql) {
        if (!($connection instanceof mysqli)) {
            return [];
        }

        $rows = [];
        $result = $connection->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            $result->free();
        }

        return $rows;
    }

    private function siteRecentActivity($site, $connection, $limit_per_table) {
        if (!($connection instanceof mysqli)) {
            return [];
        }

        $site_id = (int)($site['site_id'] ?? 0);
        $site_name = (string)($site['site_name'] ?? 'Unknown Site');
        $site_code = (string)($site['site_code'] ?? 'N/A');
        $limit_per_table = max(1, (int)$limit_per_table);
        $activities = [];

        if ($this->connectionTableExists($connection, 'teachers') && $this->connectionColumnExists($connection, 'teachers', 'created_at')) {
            $result = $connection->query("SELECT teacher_id, teacher_name, assigned_grade, created_at FROM teachers ORDER BY created_at DESC LIMIT $limit_per_table");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $activities[] = [
                        'site_id' => $site_id,
                        'site_name' => $site_name,
                        'site_code' => $site_code,
                        'entity_type' => 'teacher',
                        'title' => (string)($row['teacher_name'] ?? 'Teacher created'),
                        'description' => 'Assigned grade: ' . (string)($row['assigned_grade'] ?? 'N/A'),
                        'activity_at' => (string)($row['created_at'] ?? ''),
                    ];
                }
            }
        }

        if ($this->connectionTableExists($connection, 'subjects') && $this->connectionColumnExists($connection, 'subjects', 'created_at')) {
            $result = $connection->query("SELECT subject_id, subject_name, total_mark, created_at FROM subjects ORDER BY created_at DESC LIMIT $limit_per_table");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $activities[] = [
                        'site_id' => $site_id,
                        'site_name' => $site_name,
                        'site_code' => $site_code,
                        'entity_type' => 'subject',
                        'title' => (string)($row['subject_name'] ?? 'Subject created'),
                        'description' => 'Total mark: ' . (int)($row['total_mark'] ?? 0),
                        'activity_at' => (string)($row['created_at'] ?? ''),
                    ];
                }
            }
        }

        if ($this->connectionTableExists($connection, 'students') && $this->connectionColumnExists($connection, 'students', 'created_at')) {
            $result = $connection->query("SELECT student_id, name, grade, created_at FROM students ORDER BY created_at DESC LIMIT $limit_per_table");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $activities[] = [
                        'site_id' => $site_id,
                        'site_name' => $site_name,
                        'site_code' => $site_code,
                        'entity_type' => 'student',
                        'title' => (string)($row['name'] ?? 'Student created'),
                        'description' => 'Grade: ' . (string)($row['grade'] ?? 'N/A'),
                        'activity_at' => (string)($row['created_at'] ?? ''),
                    ];
                }
            }
        }

        if ($this->connectionTableExists($connection, 'marks')) {
            $order_column = $this->connectionColumnExists($connection, 'marks', 'updated_at') ? 'updated_at' : 'created_at';
            if ($this->connectionColumnExists($connection, 'marks', $order_column)) {
                $result = $connection->query("SELECT mark_id, student_id, subject_id, score, `$order_column` AS activity_at FROM marks ORDER BY `$order_column` DESC LIMIT $limit_per_table");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $activities[] = [
                            'site_id' => $site_id,
                            'site_name' => $site_name,
                            'site_code' => $site_code,
                            'entity_type' => 'mark',
                            'title' => 'Mark saved',
                            'description' => 'Student #' . (int)($row['student_id'] ?? 0) . ' | Subject #' . (int)($row['subject_id'] ?? 0) . ' | Score ' . (int)($row['score'] ?? 0),
                            'activity_at' => (string)($row['activity_at'] ?? ''),
                        ];
                    }
                }
            }
        }

        return array_values(array_filter($activities, static function ($activity) {
            return !empty($activity['activity_at']);
        }));
    }

    public function getSiteStats() {
        if (!$this->isDistributedReady()) {
            $central_connection = $this->database->getCentralConnection();
            return [[
                'site_id' => 1,
                'site_name' => 'Central Coordinator',
                'site_code' => 'CENTRAL',
                'db_name' => $this->database->getDefaultDatabaseName(),
                'is_default' => 1,
                'is_active' => 1,
                'connection_status' => 'online',
                'total_teachers' => $this->connectionTableCount($central_connection, 'teachers'),
                'total_subjects' => $this->connectionTableCount($central_connection, 'subjects'),
                'total_students' => $this->connectionTableCount($central_connection, 'students'),
                'total_marks' => $this->connectionTableCount($central_connection, 'marks'),
                'last_activity_at' => $this->newestTimestamp([
                    $this->connectionMaxTimestamp($central_connection, 'teachers', 'created_at'),
                    $this->connectionMaxTimestamp($central_connection, 'subjects', 'created_at'),
                    $this->connectionMaxTimestamp($central_connection, 'students', 'created_at'),
                    $this->connectionMaxTimestamp($central_connection, 'marks', 'updated_at'),
                    $this->connectionMaxTimestamp($central_connection, 'marks', 'created_at'),
                ]),
            ]];
        }

        $stats = [];
        foreach ($this->getSites(false) as $site) {
            $connection = $this->siteConnection((int)$site['site_id']);
            $is_connected = $connection instanceof mysqli;
            $teacher_total = $is_connected ? $this->connectionTableCount($connection, 'teachers') : null;
            $subject_total = $is_connected ? $this->connectionTableCount($connection, 'subjects') : null;
            $student_total = $is_connected ? $this->connectionTableCount($connection, 'students') : null;
            $mark_total = $is_connected ? $this->connectionTableCount($connection, 'marks') : null;
            $last_activity_at = $is_connected ? $this->newestTimestamp([
                $this->connectionMaxTimestamp($connection, 'teachers', 'created_at'),
                $this->connectionMaxTimestamp($connection, 'subjects', 'created_at'),
                $this->connectionMaxTimestamp($connection, 'students', 'created_at'),
                $this->connectionMaxTimestamp($connection, 'marks', 'updated_at'),
                $this->connectionMaxTimestamp($connection, 'marks', 'created_at'),
            ]) : null;

            $stats[] = [
                'site_id' => (int)($site['site_id'] ?? 0),
                'site_code' => (string)($site['site_code'] ?? ''),
                'site_name' => (string)($site['site_name'] ?? 'Unknown Site'),
                'db_name' => (string)($site['db_name'] ?? ''),
                'is_default' => (int)($site['is_default'] ?? 0),
                'is_active' => (int)($site['is_active'] ?? 1),
                'connection_status' => $is_connected ? 'online' : 'offline',
                'total_teachers' => $teacher_total,
                'total_subjects' => $subject_total,
                'total_students' => $student_total,
                'total_marks' => $mark_total,
                'last_activity_at' => $last_activity_at,
            ];
        }

        return $stats;
    }

    public function getRecentBranchActivity($limit = 12) {
        $limit = max(1, (int)$limit);
        if (!$this->isDistributedReady()) {
            return [];
        }

        $activities = [];
        $limit_per_table = max(1, min(5, $limit));
        foreach ($this->getSites() as $site) {
            $connection = $this->siteConnection((int)$site['site_id']);
            if (!($connection instanceof mysqli)) {
                continue;
            }

            $activities = array_merge($activities, $this->siteRecentActivity($site, $connection, $limit_per_table));
        }

        usort($activities, static function ($left, $right) {
            return strcmp((string)($right['activity_at'] ?? ''), (string)($left['activity_at'] ?? ''));
        });

        return array_slice($activities, 0, $limit);
    }

    public function getSiteDetails($site_id) {
        $site = $this->getSiteById((int)$site_id);
        if (!$site || !$this->isDistributedReady()) {
            return null;
        }

        $site_id = (int)$site['site_id'];
        $connection = $this->siteConnection($site_id);
        $details = [
            'site' => [
                'site_id' => $site_id,
                'site_code' => (string)($site['site_code'] ?? ''),
                'site_name' => (string)($site['site_name'] ?? 'Unknown Site'),
                'db_name' => (string)($site['db_name'] ?? ''),
                'is_default' => (int)($site['is_default'] ?? 0),
                'is_active' => (int)($site['is_active'] ?? 1),
                'connection_status' => $connection instanceof mysqli ? 'online' : 'offline',
            ],
            'stats' => [
                'teachers' => null,
                'subjects' => null,
                'students' => null,
                'marks' => null,
                'last_activity_at' => null,
            ],
            'teachers' => [],
            'subjects' => [],
            'students' => [],
            'marks' => [],
        ];

        if (!($connection instanceof mysqli)) {
            return $details;
        }

        $has_teachers = $this->connectionTableExists($connection, 'teachers');
        $has_subjects = $this->connectionTableExists($connection, 'subjects');
        $has_students = $this->connectionTableExists($connection, 'students');
        $has_marks = $this->connectionTableExists($connection, 'marks');
        $has_teacher_subjects = $this->connectionTableExists($connection, 'teacher_subjects');

        $details['stats'] = [
            'teachers' => $this->connectionTableCount($connection, 'teachers'),
            'subjects' => $this->connectionTableCount($connection, 'subjects'),
            'students' => $this->connectionTableCount($connection, 'students'),
            'marks' => $this->connectionTableCount($connection, 'marks'),
            'last_activity_at' => $this->newestTimestamp([
                $this->connectionMaxTimestamp($connection, 'teachers', 'created_at'),
                $this->connectionMaxTimestamp($connection, 'subjects', 'created_at'),
                $this->connectionMaxTimestamp($connection, 'students', 'created_at'),
                $this->connectionMaxTimestamp($connection, 'marks', 'updated_at'),
                $this->connectionMaxTimestamp($connection, 'marks', 'created_at'),
            ]),
        ];

        $subject_rows = $has_subjects
            ? $this->connectionQueryRows($connection, 'SELECT subject_id, subject_name, total_mark, created_at FROM subjects ORDER BY subject_name ASC')
            : [];
        $subject_lookup = [];
        foreach ($subject_rows as $subject_row) {
            $subject_lookup[(int)$subject_row['subject_id']] = (string)($subject_row['subject_name'] ?? 'Unknown Subject');
        }

        $subject_counts_by_teacher = [];
        $subject_counts_by_subject = [];
        $subject_names_by_teacher = [];
        if ($has_teacher_subjects) {
            $teacher_subject_rows = $this->connectionQueryRows($connection, 'SELECT teacher_id, subject_id FROM teacher_subjects ORDER BY teacher_id ASC, subject_id ASC');
            foreach ($teacher_subject_rows as $teacher_subject_row) {
                $teacher_id = (int)($teacher_subject_row['teacher_id'] ?? 0);
                $subject_id = (int)($teacher_subject_row['subject_id'] ?? 0);
                if ($teacher_id <= 0 || $subject_id <= 0) {
                    continue;
                }

                $subject_counts_by_teacher[$teacher_id] = (int)($subject_counts_by_teacher[$teacher_id] ?? 0) + 1;
                $subject_counts_by_subject[$subject_id] = (int)($subject_counts_by_subject[$subject_id] ?? 0) + 1;
                if (isset($subject_lookup[$subject_id])) {
                    $subject_names_by_teacher[$teacher_id][] = $subject_lookup[$subject_id];
                }
            }
        }

        foreach ($subject_rows as &$subject_row) {
            $subject_id = (int)($subject_row['subject_id'] ?? 0);
            $subject_row['assigned_teachers'] = (int)($subject_counts_by_subject[$subject_id] ?? 0);
        }
        unset($subject_row);
        $details['subjects'] = $subject_rows;

        $teacher_rows = $has_teachers
            ? $this->connectionQueryRows($connection, 'SELECT teacher_id, teacher_name, department, assigned_grade, is_homeroom, created_at FROM teachers ORDER BY teacher_name ASC')
            : [];
        $teacher_lookup = [];
        foreach ($teacher_rows as &$teacher_row) {
            $teacher_id = (int)($teacher_row['teacher_id'] ?? 0);
            $teacher_lookup[$teacher_id] = (string)($teacher_row['teacher_name'] ?? 'Unknown Teacher');
            $subjects_taught = $subject_names_by_teacher[$teacher_id] ?? [];
            $teacher_row['subjects_taught'] = !empty($subjects_taught)
                ? implode(', ', array_unique($subjects_taught))
                : (string)($teacher_row['department'] ?? '');
            $teacher_row['subject_count'] = (int)($subject_counts_by_teacher[$teacher_id] ?? 0);
        }
        unset($teacher_row);
        $details['teachers'] = $teacher_rows;

        $student_mark_counts = [];
        if ($has_marks) {
            $student_mark_count_rows = $this->connectionQueryRows($connection, 'SELECT student_id, COUNT(*) AS total_marks FROM marks GROUP BY student_id');
            foreach ($student_mark_count_rows as $student_mark_count_row) {
                $student_mark_counts[(int)($student_mark_count_row['student_id'] ?? 0)] = (int)($student_mark_count_row['total_marks'] ?? 0);
            }
        }

        $student_rows = $has_students
            ? $this->connectionQueryRows($connection, 'SELECT student_id, name, gender, grade, academic_year, semester, created_at FROM students ORDER BY grade ASC, name ASC')
            : [];
        $student_lookup = [];
        $student_grade_lookup = [];
        foreach ($student_rows as &$student_row) {
            $student_id = (int)($student_row['student_id'] ?? 0);
            $student_lookup[$student_id] = (string)($student_row['name'] ?? 'Unknown Student');
            $student_grade_lookup[$student_id] = (string)($student_row['grade'] ?? '');
            $student_row['total_marks'] = (int)($student_mark_counts[$student_id] ?? 0);
        }
        unset($student_row);
        $details['students'] = $student_rows;

        if ($has_marks) {
            $mark_order_column = $this->connectionColumnExists($connection, 'marks', 'updated_at') ? 'updated_at' : 'created_at';
            $mark_rows = $this->connectionQueryRows($connection, "SELECT * FROM marks ORDER BY `$mark_order_column` DESC, mark_id DESC");
            foreach ($mark_rows as &$mark_row) {
                $student_id = (int)($mark_row['student_id'] ?? 0);
                $subject_id = (int)($mark_row['subject_id'] ?? 0);
                $teacher_id = (int)($mark_row['teacher_id'] ?? 0);
                $mark_row['student_name'] = $student_lookup[$student_id] ?? ('Student #' . $student_id);
                $mark_row['student_grade'] = $student_grade_lookup[$student_id] ?? '';
                $mark_row['subject_name'] = $subject_lookup[$subject_id] ?? ('Subject #' . $subject_id);
                $mark_row['teacher_name'] = $teacher_lookup[$teacher_id] ?? ('Teacher #' . $teacher_id);
                $mark_row['assessment_type'] = (string)($mark_row['assessment_type'] ?? 'Exam');
                $mark_row['assessment_date'] = (string)($mark_row['assessment_date'] ?? '');
                $mark_row['comments'] = (string)($mark_row['comments'] ?? '');
                $mark_row['activity_at'] = (string)($mark_row[$mark_order_column] ?? ($mark_row['created_at'] ?? ''));
            }
            unset($mark_row);
            $details['marks'] = $mark_rows;
        }

        return $details;
    }

    public function studentSiteId($student_id) {
        $student_id = (int)$student_id;
        if ($student_id <= 0 || !$this->isDistributedReady()) {
            return $this->getDefaultSiteId();
        }

        $result = $this->central->query("SELECT site_id FROM students WHERE student_id = $student_id LIMIT 1");
        if (!$result || $result->num_rows === 0) {
            return $this->getDefaultSiteId();
        }

        $row = $result->fetch_assoc();
        return $this->normalizeSiteId((int)($row['site_id'] ?? 0));
    }

    private function siteConnection($site_id) {
        $site = $this->getSiteById((int)$site_id);
        if (!$site || empty($site['db_name'])) {
            return null;
        }

        return $this->database->getConnectionForDatabase($site['db_name']);
    }

    private function fetchCentralRow($table_name, $id_column, $id_value) {
        $table_name = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table_name);
        $id_column = preg_replace('/[^A-Za-z0-9_]/', '', (string)$id_column);
        $id_value = (int)$id_value;

        if ($table_name === '' || $id_column === '' || $id_value <= 0) {
            return null;
        }

        $result = $this->central->query("SELECT * FROM `$table_name` WHERE `$id_column` = $id_value LIMIT 1");
        return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }

    private function logSync($entity_type, $entity_id, $site_id, $action_type, $status, $message = '') {
        if (!$this->isDistributedReady()) {
            return;
        }

        $stmt = $this->central->prepare('INSERT INTO coordinator_sync_log (entity_type, entity_id, site_id, action_type, status, message) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            return;
        }

        $entity_type = (string)$entity_type;
        $entity_id = (int)$entity_id;
        $site_id = (int)$site_id;
        $action_type = (string)$action_type;
        $status = (string)$status;
        $message = (string)$message;
        $stmt->bind_param('siisss', $entity_type, $entity_id, $site_id, $action_type, $status, $message);
        $stmt->execute();
        $stmt->close();
    }

    private function deleteStudentFromSite($site_id, $student_id) {
        $site_id = (int)$site_id;
        $student_id = (int)$student_id;
        $connection = $this->siteConnection($site_id);
        if (!$connection) {
            return false;
        }

        $connection->query("DELETE FROM student_profiles WHERE student_id = $student_id");
        $connection->query("DELETE FROM marks WHERE student_id = $student_id");
        $ok = $connection->query("DELETE FROM students WHERE student_id = $student_id") !== false;
        $this->logSync('student', $student_id, $site_id, 'delete', $ok ? 'success' : 'error', $ok ? 'Student removed from branch database' : 'Failed to remove student from branch database');
        return $ok;
    }

    public function syncStudent($student_id) {
        $student_id = (int)$student_id;
        if ($student_id <= 0 || !$this->isDistributedReady()) {
            return true;
        }

        $student = $this->fetchCentralRow('students', 'student_id', $student_id);
        if (!$student) {
            return $this->deleteStudentDistributed($student_id);
        }

        $site_id = $this->normalizeSiteId((int)($student['site_id'] ?? 0));
        $site_connection = $this->siteConnection($site_id);
        if (!$site_connection) {
            $this->logSync('student', $student_id, $site_id, 'upsert', 'error', 'Branch database connection missing');
            return false;
        }

        $stmt = $site_connection->prepare(
            'INSERT INTO students (student_id, name, gender, grade, grade_id, academic_year, semester, site_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                gender = VALUES(gender),
                grade = VALUES(grade),
                grade_id = VALUES(grade_id),
                academic_year = VALUES(academic_year),
                semester = VALUES(semester),
                site_id = VALUES(site_id),
                created_at = VALUES(created_at)'
        );

        if (!$stmt) {
            $this->logSync('student', $student_id, $site_id, 'upsert', 'error', 'Failed to prepare student sync statement');
            return false;
        }

        $grade_id = isset($student['grade_id']) ? (int)$student['grade_id'] : null;
        $created_at = (string)($student['created_at'] ?? date('Y-m-d H:i:s'));
        $stmt->bind_param(
            'isssissis',
            $student_id,
            $student['name'],
            $student['gender'],
            $student['grade'],
            $grade_id,
            $student['academic_year'],
            $student['semester'],
            $site_id,
            $created_at
        );

        $ok = $stmt->execute();
        $stmt->close();
        $this->logSync('student', $student_id, $site_id, 'upsert', $ok ? 'success' : 'error', $ok ? 'Student synced to branch database' : 'Failed to sync student to branch database');

        if (!$ok) {
            return false;
        }

        $this->syncStudentProfile($student_id);
        $marks_result = $this->central->query("SELECT mark_id FROM marks WHERE student_id = $student_id");
        if ($marks_result) {
            while ($mark = $marks_result->fetch_assoc()) {
                $this->syncMark((int)$mark['mark_id']);
            }
        }

        foreach ($this->getSites() as $site) {
            if ((int)$site['site_id'] !== $site_id) {
                $this->deleteStudentFromSite((int)$site['site_id'], $student_id);
            }
        }

        return true;
    }

    public function deleteStudentDistributed($student_id) {
        $student_id = (int)$student_id;
        if ($student_id <= 0 || !$this->isDistributedReady()) {
            return true;
        }

        $ok = true;
        foreach ($this->getSites() as $site) {
            if (!$this->deleteStudentFromSite((int)$site['site_id'], $student_id)) {
                $ok = false;
            }
        }

        return $ok;
    }

    private function deleteMarkFromSite($site_id, $mark_id) {
        $site_id = (int)$site_id;
        $mark_id = (int)$mark_id;
        $connection = $this->siteConnection($site_id);
        if (!$connection) {
            return false;
        }

        $ok = $connection->query("DELETE FROM marks WHERE mark_id = $mark_id") !== false;
        $this->logSync('mark', $mark_id, $site_id, 'delete', $ok ? 'success' : 'error', $ok ? 'Mark removed from branch database' : 'Failed to remove mark from branch database');
        return $ok;
    }

    public function syncMark($mark_id) {
        $mark_id = (int)$mark_id;
        if ($mark_id <= 0 || !$this->isDistributedReady()) {
            return true;
        }

        $mark = $this->fetchCentralRow('marks', 'mark_id', $mark_id);
        if (!$mark) {
            return $this->deleteMarkDistributed($mark_id);
        }

        $site_id = isset($mark['site_id']) ? (int)$mark['site_id'] : 0;
        if ($site_id <= 0) {
            $site_id = $this->studentSiteId((int)$mark['student_id']);
            $this->central->query("UPDATE marks SET site_id = $site_id WHERE mark_id = $mark_id");
            $mark['site_id'] = $site_id;
        }

        $site_connection = $this->siteConnection($site_id);
        if (!$site_connection) {
            $this->logSync('mark', $mark_id, $site_id, 'upsert', 'error', 'Branch database connection missing');
            return false;
        }

        $stmt = $site_connection->prepare(
            'INSERT INTO marks (mark_id, student_id, subject_id, teacher_id, site_id, academic_year_id, score, assessment_type, comments, assessment_date, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                student_id = VALUES(student_id),
                subject_id = VALUES(subject_id),
                teacher_id = VALUES(teacher_id),
                site_id = VALUES(site_id),
                academic_year_id = VALUES(academic_year_id),
                score = VALUES(score),
                assessment_type = VALUES(assessment_type),
                comments = VALUES(comments),
                assessment_date = VALUES(assessment_date),
                created_at = VALUES(created_at),
                updated_at = VALUES(updated_at)'
        );

        if (!$stmt) {
            $this->logSync('mark', $mark_id, $site_id, 'upsert', 'error', 'Failed to prepare mark sync statement');
            return false;
        }

        $student_id = (int)$mark['student_id'];
        $subject_id = (int)$mark['subject_id'];
        $teacher_id = (int)$mark['teacher_id'];
        $academic_year_id = isset($mark['academic_year_id']) ? (int)$mark['academic_year_id'] : null;
        $score = (int)$mark['score'];
        $assessment_type = (string)($mark['assessment_type'] ?? 'Exam');
        $comments = isset($mark['comments']) ? (string)$mark['comments'] : null;
        $assessment_date = isset($mark['assessment_date']) && $mark['assessment_date'] !== '' ? (string)$mark['assessment_date'] : null;
        $created_at = (string)($mark['created_at'] ?? date('Y-m-d H:i:s'));
        $updated_at = (string)($mark['updated_at'] ?? $created_at);

        $stmt->bind_param(
            'iiiiiiisssss',
            $mark_id,
            $student_id,
            $subject_id,
            $teacher_id,
            $site_id,
            $academic_year_id,
            $score,
            $assessment_type,
            $comments,
            $assessment_date,
            $created_at,
            $updated_at
        );

        $ok = $stmt->execute();
        $stmt->close();
        $this->logSync('mark', $mark_id, $site_id, 'upsert', $ok ? 'success' : 'error', $ok ? 'Mark synced to branch database' : 'Failed to sync mark to branch database');

        if (!$ok) {
            return false;
        }

        foreach ($this->getSites() as $site) {
            if ((int)$site['site_id'] !== $site_id) {
                $this->deleteMarkFromSite((int)$site['site_id'], $mark_id);
            }
        }

        return true;
    }

    public function deleteMarkDistributed($mark_id) {
        $mark_id = (int)$mark_id;
        if ($mark_id <= 0 || !$this->isDistributedReady()) {
            return true;
        }

        $ok = true;
        foreach ($this->getSites() as $site) {
            if (!$this->deleteMarkFromSite((int)$site['site_id'], $mark_id)) {
                $ok = false;
            }
        }

        return $ok;
    }

    public function syncStudentProfile($student_id) {
        $student_id = (int)$student_id;
        if ($student_id <= 0 || !$this->isDistributedReady()) {
            return true;
        }

        $site_id = $this->studentSiteId($student_id);
        $profile = $this->fetchCentralRow('student_profiles', 'student_id', $student_id);
        $site_connection = $this->siteConnection($site_id);

        if (!$site_connection) {
            $this->logSync('profile', $student_id, $site_id, 'upsert', 'error', 'Branch database connection missing');
            return false;
        }

        if (!$profile) {
            foreach ($this->getSites() as $site) {
                $connection = $this->siteConnection((int)$site['site_id']);
                if ($connection) {
                    $connection->query("DELETE FROM student_profiles WHERE student_id = $student_id");
                }
            }
            $this->logSync('profile', $student_id, $site_id, 'delete', 'success', 'Student profile removed from branch databases');
            return true;
        }

        $stmt = $site_connection->prepare(
            'INSERT INTO student_profiles (student_id, phone, address, date_of_birth, guardian_name, guardian_phone, bio, profile_photo, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                phone = VALUES(phone),
                address = VALUES(address),
                date_of_birth = VALUES(date_of_birth),
                guardian_name = VALUES(guardian_name),
                guardian_phone = VALUES(guardian_phone),
                bio = VALUES(bio),
                profile_photo = VALUES(profile_photo),
                updated_at = VALUES(updated_at)'
        );

        if (!$stmt) {
            $this->logSync('profile', $student_id, $site_id, 'upsert', 'error', 'Failed to prepare profile sync statement');
            return false;
        }

        $phone = isset($profile['phone']) ? (string)$profile['phone'] : null;
        $address = isset($profile['address']) ? (string)$profile['address'] : null;
        $date_of_birth = isset($profile['date_of_birth']) && $profile['date_of_birth'] !== '' ? (string)$profile['date_of_birth'] : null;
        $guardian_name = isset($profile['guardian_name']) ? (string)$profile['guardian_name'] : null;
        $guardian_phone = isset($profile['guardian_phone']) ? (string)$profile['guardian_phone'] : null;
        $bio = isset($profile['bio']) ? (string)$profile['bio'] : null;
        $profile_photo = isset($profile['profile_photo']) ? (string)$profile['profile_photo'] : null;
        $updated_at = (string)($profile['updated_at'] ?? date('Y-m-d H:i:s'));

        $stmt->bind_param(
            'issssssss',
            $student_id,
            $phone,
            $address,
            $date_of_birth,
            $guardian_name,
            $guardian_phone,
            $bio,
            $profile_photo,
            $updated_at
        );

        $ok = $stmt->execute();
        $stmt->close();
        $this->logSync('profile', $student_id, $site_id, 'upsert', $ok ? 'success' : 'error', $ok ? 'Student profile synced to branch database' : 'Failed to sync student profile to branch database');

        if (!$ok) {
            return false;
        }

        foreach ($this->getSites() as $site) {
            if ((int)$site['site_id'] !== $site_id) {
                $connection = $this->siteConnection((int)$site['site_id']);
                if ($connection) {
                    $connection->query("DELETE FROM student_profiles WHERE student_id = $student_id");
                }
            }
        }

        return true;
    }
}
?>
