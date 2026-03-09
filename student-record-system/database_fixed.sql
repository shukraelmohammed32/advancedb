-- Student Record Management System
-- Full setup + repair SQL for phpMyAdmin paste/import
-- Safe to run multiple times on MariaDB/MySQL

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS student_record_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE student_record_system;

-- ============================================
-- REFERENCE TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS grades (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    grade_name VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_grades_grade_name (grade_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS academic_years (
    year_id INT AUTO_INCREMENT PRIMARY KEY,
    year_name VARCHAR(20) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_academic_years_year_name (year_name)
) ENGINE=InnoDB;

-- ============================================
-- CORE TABLES REQUIRED BY THE PHP APP
-- ============================================

CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    grade VARCHAR(20) NOT NULL,
    grade_id INT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester ENUM('First', 'Second') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    total_mark INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teachers (
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    assigned_grade VARCHAR(20) NOT NULL,
    is_homeroom TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teacher_subjects (
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (teacher_id, subject_id),
    KEY idx_teacher_subjects_subject_id (subject_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS marks (
    mark_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    academic_year_id INT NULL,
    score INT NOT NULL,
    assessment_type ENUM('Exam', 'Quiz', 'Assignment', 'Final') NOT NULL DEFAULT 'Exam',
    comments TEXT NULL,
    assessment_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
    student_id INT NULL,
    teacher_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS student_profiles (
    student_id INT PRIMARY KEY,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    date_of_birth DATE NULL,
    guardian_name VARCHAR(100) NULL,
    guardian_phone VARCHAR(30) NULL,
    bio TEXT NULL,
    profile_photo VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- UPGRADE SUPPORT FOR OLDER/PARTIAL IMPORTS
-- ============================================

ALTER TABLE students ADD COLUMN IF NOT EXISTS grade_id INT NULL AFTER gender;

ALTER TABLE marks ADD COLUMN IF NOT EXISTS academic_year_id INT NULL AFTER teacher_id;
ALTER TABLE marks ADD COLUMN IF NOT EXISTS assessment_type ENUM('Exam', 'Quiz', 'Assignment', 'Final') NOT NULL DEFAULT 'Exam' AFTER score;
ALTER TABLE marks ADD COLUMN IF NOT EXISTS comments TEXT NULL AFTER assessment_type;
ALTER TABLE marks ADD COLUMN IF NOT EXISTS assessment_date DATE NULL AFTER comments;
ALTER TABLE marks ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id INT NULL AFTER role;
ALTER TABLE users ADD COLUMN IF NOT EXISTS teacher_id INT NULL AFTER student_id;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER teacher_id;
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE student_profiles ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) NULL AFTER bio;

-- Clean orphan legacy records before FK creation
DELETE ts
FROM teacher_subjects ts
LEFT JOIN teachers t ON t.teacher_id = ts.teacher_id
WHERE t.teacher_id IS NULL;

DELETE ts
FROM teacher_subjects ts
LEFT JOIN subjects sub ON sub.subject_id = ts.subject_id
WHERE sub.subject_id IS NULL;

DELETE m
FROM marks m
LEFT JOIN students s ON s.student_id = m.student_id
WHERE s.student_id IS NULL;

DELETE m
FROM marks m
LEFT JOIN subjects sub ON sub.subject_id = m.subject_id
WHERE sub.subject_id IS NULL;

DELETE m
FROM marks m
LEFT JOIN teachers t ON t.teacher_id = m.teacher_id
WHERE t.teacher_id IS NULL;

UPDATE users u
LEFT JOIN students s ON s.student_id = u.student_id
SET u.student_id = NULL
WHERE u.student_id IS NOT NULL AND s.student_id IS NULL;

UPDATE users u
LEFT JOIN teachers t ON t.teacher_id = u.teacher_id
SET u.teacher_id = NULL
WHERE u.teacher_id IS NOT NULL AND t.teacher_id IS NULL;

-- Keep only one mark per student/subject before unique key
DELETE m1
FROM marks m1
JOIN marks m2
  ON m1.mark_id > m2.mark_id
 AND m1.student_id = m2.student_id
 AND m1.subject_id = m2.subject_id;

-- ============================================
-- INDEXES (IF MISSING)
-- ============================================

SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'marks' AND index_name = 'uq_marks_student_subject'
);
SET @sql_stmt = IF(@idx_exists = 0,
    'ALTER TABLE marks ADD UNIQUE KEY uq_marks_student_subject (student_id, subject_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'marks' AND index_name = 'idx_marks_teacher_subject'
);
SET @sql_stmt = IF(@idx_exists = 0,
    'ALTER TABLE marks ADD INDEX idx_marks_teacher_subject (teacher_id, subject_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'students' AND index_name = 'idx_students_grade'
);
SET @sql_stmt = IF(@idx_exists = 0,
    'ALTER TABLE students ADD INDEX idx_students_grade (grade_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'teacher_subjects' AND index_name = 'idx_teacher_subjects_subject_id'
);
SET @sql_stmt = IF(@idx_exists = 0,
    'ALTER TABLE teacher_subjects ADD INDEX idx_teacher_subjects_subject_id (subject_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- FOREIGN KEYS (IF MISSING)
-- ============================================

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'students'
      AND COLUMN_NAME = 'grade_id'
      AND REFERENCED_TABLE_NAME = 'grades'
      AND REFERENCED_COLUMN_NAME = 'grade_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE students ADD CONSTRAINT fk_students_grade FOREIGN KEY (grade_id) REFERENCES grades(grade_id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'marks'
      AND COLUMN_NAME = 'student_id'
      AND REFERENCED_TABLE_NAME = 'students'
      AND REFERENCED_COLUMN_NAME = 'student_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE marks ADD CONSTRAINT fk_marks_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'marks'
      AND COLUMN_NAME = 'subject_id'
      AND REFERENCED_TABLE_NAME = 'subjects'
      AND REFERENCED_COLUMN_NAME = 'subject_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE marks ADD CONSTRAINT fk_marks_subject FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'marks'
      AND COLUMN_NAME = 'teacher_id'
      AND REFERENCED_TABLE_NAME = 'teachers'
      AND REFERENCED_COLUMN_NAME = 'teacher_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE marks ADD CONSTRAINT fk_marks_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'marks'
      AND COLUMN_NAME = 'academic_year_id'
      AND REFERENCED_TABLE_NAME = 'academic_years'
      AND REFERENCED_COLUMN_NAME = 'year_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE marks ADD CONSTRAINT fk_marks_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(year_id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'student_id'
      AND REFERENCED_TABLE_NAME = 'students'
      AND REFERENCED_COLUMN_NAME = 'student_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE users ADD CONSTRAINT fk_users_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'teacher_id'
      AND REFERENCED_TABLE_NAME = 'teachers'
      AND REFERENCED_COLUMN_NAME = 'teacher_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE users ADD CONSTRAINT fk_users_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'teacher_subjects'
      AND COLUMN_NAME = 'teacher_id'
      AND REFERENCED_TABLE_NAME = 'teachers'
      AND REFERENCED_COLUMN_NAME = 'teacher_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE teacher_subjects ADD CONSTRAINT fk_teacher_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'teacher_subjects'
      AND COLUMN_NAME = 'subject_id'
      AND REFERENCED_TABLE_NAME = 'subjects'
      AND REFERENCED_COLUMN_NAME = 'subject_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE teacher_subjects ADD CONSTRAINT fk_teacher_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'student_profiles'
      AND COLUMN_NAME = 'student_id'
      AND REFERENCED_TABLE_NAME = 'students'
      AND REFERENCED_COLUMN_NAME = 'student_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE student_profiles ADD CONSTRAINT fk_student_profiles_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- SEED DATA
-- ============================================

INSERT INTO grades (grade_name) VALUES
('Grade 1'), ('Grade 2'), ('Grade 3'), ('Grade 4'),
('Grade 5'), ('Grade 6'), ('Grade 7'), ('Grade 8')
ON DUPLICATE KEY UPDATE grade_name = VALUES(grade_name);

INSERT INTO academic_years (year_name, is_active) VALUES
('2023-2024', 0),
('2024-2025', 1)
ON DUPLICATE KEY UPDATE is_active = VALUES(is_active);

INSERT INTO subjects (subject_name, total_mark)
SELECT 'Maths', 100
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE subject_name = 'Maths');

INSERT INTO subjects (subject_name, total_mark)
SELECT 'English', 100
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE subject_name = 'English');

INSERT INTO subjects (subject_name, total_mark)
SELECT 'Biology', 100
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE subject_name = 'Biology');

INSERT INTO subjects (subject_name, total_mark)
SELECT 'Chemistry', 100
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE subject_name = 'Chemistry');

INSERT INTO subjects (subject_name, total_mark)
SELECT 'Physics', 100
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE subject_name = 'Physics');

-- Migrate old one-subject teacher department into many-to-many mapping
INSERT INTO teacher_subjects (teacher_id, subject_id)
SELECT DISTINCT t.teacher_id, sub.subject_id
FROM teachers t
JOIN subjects sub ON LOWER(TRIM(t.department)) = LOWER(TRIM(sub.subject_name))
ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id);

-- Ensure any existing marks imply valid teaching assignments
INSERT INTO teacher_subjects (teacher_id, subject_id)
SELECT DISTINCT m.teacher_id, m.subject_id
FROM marks m
JOIN teachers t ON t.teacher_id = m.teacher_id
JOIN subjects sub ON sub.subject_id = m.subject_id
ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id);

UPDATE students s
JOIN grades g ON LOWER(TRIM(s.grade)) = LOWER(TRIM(g.grade_name))
SET s.grade_id = g.grade_id
WHERE s.grade_id IS NULL;

UPDATE students s
JOIN grades g ON g.grade_name = CONCAT('Grade ', TRIM(s.grade))
SET s.grade_id = g.grade_id
WHERE s.grade_id IS NULL AND s.grade REGEXP '^[0-9]+$';

UPDATE marks m
JOIN academic_years ay ON ay.is_active = 1
SET m.academic_year_id = ay.year_id
WHERE m.academic_year_id IS NULL;

-- Default admin user (password: admin123)
UPDATE users
SET password = '$2y$10$jim8N8JPcSPg2sbajBD33uxZ/y/P3P6HXRy4UpRXSQu7bR9/9qySu',
    role = 'admin',
    is_active = 1
WHERE username = 'admin';

INSERT INTO users (username, password, email, role, is_active)
SELECT
    'admin',
    '$2y$10$jim8N8JPcSPg2sbajBD33uxZ/y/P3P6HXRy4UpRXSQu7bR9/9qySu',
    CONCAT('admin+', UNIX_TIMESTAMP(), '@school.edu'),
    'admin',
    1
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE username = 'admin'
);

-- ============================================
-- REPORTING VIEWS
-- ============================================

CREATE OR REPLACE VIEW student_summary AS
SELECT
    s.student_id,
    s.name,
    s.grade,
    s.academic_year,
    s.semester,
    COUNT(m.mark_id) AS total_marks,
    subject_totals.total_subjects AS total_subjects,
    COALESCE(ROUND(AVG(m.score), 2), 0) AS average_score,
    SUM(CASE WHEN m.score >= 50 THEN 1 ELSE 0 END) AS passed_subjects,
    GREATEST(subject_totals.total_subjects - COUNT(m.mark_id), 0) AS missing_marks,
    CASE
        WHEN subject_totals.total_subjects = 0 THEN 'NO SUBJECTS'
        WHEN COUNT(m.mark_id) = 0 THEN 'NO MARKS'
        WHEN COUNT(m.mark_id) < subject_totals.total_subjects THEN 'INCOMPLETE'
        WHEN SUM(CASE WHEN m.score >= 50 THEN 1 ELSE 0 END) = subject_totals.total_subjects THEN 'PASS'
        ELSE 'FAIL'
    END AS overall_status
FROM students s
CROSS JOIN (
    SELECT COUNT(*) AS total_subjects
    FROM subjects
) subject_totals
LEFT JOIN marks m ON s.student_id = m.student_id
GROUP BY s.student_id, s.name, s.grade, s.academic_year, s.semester, subject_totals.total_subjects;

CREATE OR REPLACE VIEW subject_performance AS
SELECT
    sub.subject_id,
    sub.subject_name,
    COUNT(m.mark_id) AS total_students,
    COALESCE(ROUND(AVG(m.score), 2), 0) AS average_score,
    COALESCE(MAX(m.score), 0) AS highest_score,
    COALESCE(MIN(m.score), 0) AS lowest_score,
    SUM(CASE WHEN m.score >= 50 THEN 1 ELSE 0 END) AS passed_count,
    SUM(CASE WHEN m.score < 50 THEN 1 ELSE 0 END) AS failed_count,
    COALESCE(
        ROUND((SUM(CASE WHEN m.score >= 50 THEN 1 ELSE 0 END) / NULLIF(COUNT(m.mark_id), 0)) * 100, 2),
        0
    ) AS pass_rate,
    CASE
        WHEN COUNT(m.mark_id) = 0 THEN 'NO DATA'
        WHEN AVG(m.score) >= 80 THEN 'EXCELLENT'
        WHEN AVG(m.score) >= 60 THEN 'GOOD'
        WHEN AVG(m.score) >= 50 THEN 'FAIR'
        ELSE 'NEEDS IMPROVEMENT'
    END AS performance_band
FROM subjects sub
LEFT JOIN marks m ON sub.subject_id = m.subject_id
GROUP BY sub.subject_id, sub.subject_name;

-- ============================================
-- VERIFICATION
-- ============================================

SELECT 'Database setup/repair completed successfully.' AS message;
SELECT COUNT(*) AS students_count FROM students;
SELECT COUNT(*) AS teachers_count FROM teachers;
SELECT COUNT(*) AS teacher_subject_links FROM teacher_subjects;
SELECT COUNT(*) AS subjects_count FROM subjects;
SELECT COUNT(*) AS marks_count FROM marks;
SELECT COUNT(*) AS student_profiles_count FROM student_profiles;
SELECT username, role, is_active FROM users WHERE username = 'admin';
