-- Local distributed database upgrade for Student Record System
-- Central coordinator: student_record_system
-- Local branch databases: branch_north, branch_south, branch_east

SET NAMES utf8mb4;
USE student_record_system;

CREATE TABLE IF NOT EXISTS distributed_sites (
    site_id INT AUTO_INCREMENT PRIMARY KEY,
    site_code VARCHAR(20) NOT NULL,
    site_name VARCHAR(100) NOT NULL,
    db_name VARCHAR(100) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_distributed_sites_code (site_code),
    UNIQUE KEY uq_distributed_sites_db_name (db_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coordinator_sync_log (
    sync_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('student', 'mark', 'profile') NOT NULL,
    entity_id INT NOT NULL,
    site_id INT NOT NULL,
    action_type ENUM('upsert', 'delete') NOT NULL,
    status ENUM('success', 'error') NOT NULL DEFAULT 'success',
    message VARCHAR(255) NULL,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sync_log_entity (entity_type, entity_id),
    KEY idx_sync_log_site (site_id),
    KEY idx_sync_log_time (synced_at)
) ENGINE=InnoDB;

INSERT INTO distributed_sites (site_id, site_code, site_name, db_name, is_default, is_active)
VALUES
    (1, 'NORTH', 'North Campus', 'student_record_system_branch_north', 1, 1),
    (2, 'SOUTH', 'South Campus', 'student_record_system_branch_south', 0, 1),
    (3, 'EAST',  'East Campus',  'student_record_system_branch_east',  0, 1)
ON DUPLICATE KEY UPDATE
    site_name = VALUES(site_name),
    db_name = VALUES(db_name),
    is_default = VALUES(is_default),
    is_active = VALUES(is_active);

UPDATE distributed_sites
SET is_default = CASE WHEN site_id = 1 THEN 1 ELSE 0 END;

ALTER TABLE students ADD COLUMN IF NOT EXISTS site_id INT NULL AFTER semester;
ALTER TABLE marks ADD COLUMN IF NOT EXISTS site_id INT NULL AFTER teacher_id;

UPDATE students
SET site_id = 1
WHERE site_id IS NULL OR site_id = 0;

UPDATE marks m
JOIN students s ON s.student_id = m.student_id
SET m.site_id = s.site_id
WHERE m.site_id IS NULL OR m.site_id = 0;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'students'
      AND COLUMN_NAME = 'site_id'
      AND REFERENCED_TABLE_NAME = 'distributed_sites'
      AND REFERENCED_COLUMN_NAME = 'site_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE students ADD CONSTRAINT fk_students_site FOREIGN KEY (site_id) REFERENCES distributed_sites(site_id) ON DELETE RESTRICT',
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
      AND COLUMN_NAME = 'site_id'
      AND REFERENCED_TABLE_NAME = 'distributed_sites'
      AND REFERENCED_COLUMN_NAME = 'site_id'
);
SET @sql_stmt = IF(@fk_exists = 0,
    'ALTER TABLE marks ADD CONSTRAINT fk_marks_site FOREIGN KEY (site_id) REFERENCES distributed_sites(site_id) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt FROM @sql_stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE DATABASE IF NOT EXISTS student_record_system_branch_north
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS student_record_system_branch_south
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS student_record_system_branch_east
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_record_system_branch_north.students (
    student_id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    grade VARCHAR(20) NOT NULL,
    grade_id INT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester ENUM('First', 'Second') NOT NULL,
    site_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_record_system_branch_north.marks (
    mark_id INT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    site_id INT NOT NULL,
    academic_year_id INT NULL,
    score INT NOT NULL,
    assessment_type ENUM('Exam', 'Quiz', 'Assignment', 'Final') NOT NULL DEFAULT 'Exam',
    comments TEXT NULL,
    assessment_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_marks_student_subject (student_id, subject_id),
    KEY idx_marks_teacher_subject (teacher_id, subject_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_record_system_branch_north.student_profiles (
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

CREATE TABLE IF NOT EXISTS student_record_system_branch_north.subjects (
    subject_id INT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    total_mark INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_record_system_branch_north.teachers (
    teacher_id INT PRIMARY KEY,
    teacher_name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    assigned_grade VARCHAR(20) NOT NULL,
    is_homeroom TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_record_system_branch_north.teacher_subjects (
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (teacher_id, subject_id),
    KEY idx_teacher_subjects_subject_id (subject_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_record_system_branch_south.students LIKE student_record_system_branch_north.students;
CREATE TABLE IF NOT EXISTS student_record_system_branch_south.marks LIKE student_record_system_branch_north.marks;
CREATE TABLE IF NOT EXISTS student_record_system_branch_south.student_profiles LIKE student_record_system_branch_north.student_profiles;
CREATE TABLE IF NOT EXISTS student_record_system_branch_south.subjects LIKE student_record_system_branch_north.subjects;
CREATE TABLE IF NOT EXISTS student_record_system_branch_south.teachers LIKE student_record_system_branch_north.teachers;
CREATE TABLE IF NOT EXISTS student_record_system_branch_south.teacher_subjects LIKE student_record_system_branch_north.teacher_subjects;

CREATE TABLE IF NOT EXISTS student_record_system_branch_east.students LIKE student_record_system_branch_north.students;
CREATE TABLE IF NOT EXISTS student_record_system_branch_east.marks LIKE student_record_system_branch_north.marks;
CREATE TABLE IF NOT EXISTS student_record_system_branch_east.student_profiles LIKE student_record_system_branch_north.student_profiles;
CREATE TABLE IF NOT EXISTS student_record_system_branch_east.subjects LIKE student_record_system_branch_north.subjects;
CREATE TABLE IF NOT EXISTS student_record_system_branch_east.teachers LIKE student_record_system_branch_north.teachers;
CREATE TABLE IF NOT EXISTS student_record_system_branch_east.teacher_subjects LIKE student_record_system_branch_north.teacher_subjects;

INSERT INTO student_record_system_branch_north.students (student_id, name, gender, grade, grade_id, academic_year, semester, site_id, created_at)
SELECT student_id, name, gender, grade, grade_id, academic_year, semester, site_id, created_at
FROM student_record_system.students
WHERE site_id = 1
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    gender = VALUES(gender),
    grade = VALUES(grade),
    grade_id = VALUES(grade_id),
    academic_year = VALUES(academic_year),
    semester = VALUES(semester),
    site_id = VALUES(site_id),
    created_at = VALUES(created_at);

INSERT INTO student_record_system_branch_south.students (student_id, name, gender, grade, grade_id, academic_year, semester, site_id, created_at)
SELECT student_id, name, gender, grade, grade_id, academic_year, semester, site_id, created_at
FROM student_record_system.students
WHERE site_id = 2
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    gender = VALUES(gender),
    grade = VALUES(grade),
    grade_id = VALUES(grade_id),
    academic_year = VALUES(academic_year),
    semester = VALUES(semester),
    site_id = VALUES(site_id),
    created_at = VALUES(created_at);

INSERT INTO student_record_system_branch_east.students (student_id, name, gender, grade, grade_id, academic_year, semester, site_id, created_at)
SELECT student_id, name, gender, grade, grade_id, academic_year, semester, site_id, created_at
FROM student_record_system.students
WHERE site_id = 3
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    gender = VALUES(gender),
    grade = VALUES(grade),
    grade_id = VALUES(grade_id),
    academic_year = VALUES(academic_year),
    semester = VALUES(semester),
    site_id = VALUES(site_id),
    created_at = VALUES(created_at);

INSERT INTO student_record_system_branch_north.marks (mark_id, student_id, subject_id, teacher_id, site_id, academic_year_id, score, assessment_type, comments, assessment_date, created_at, updated_at)
SELECT mark_id, student_id, subject_id, teacher_id, site_id, academic_year_id, score, assessment_type, comments, assessment_date, created_at, updated_at
FROM student_record_system.marks
WHERE site_id = 1
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
    updated_at = VALUES(updated_at);

INSERT INTO student_record_system_branch_south.marks (mark_id, student_id, subject_id, teacher_id, site_id, academic_year_id, score, assessment_type, comments, assessment_date, created_at, updated_at)
SELECT mark_id, student_id, subject_id, teacher_id, site_id, academic_year_id, score, assessment_type, comments, assessment_date, created_at, updated_at
FROM student_record_system.marks
WHERE site_id = 2
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
    updated_at = VALUES(updated_at);

INSERT INTO student_record_system_branch_east.marks (mark_id, student_id, subject_id, teacher_id, site_id, academic_year_id, score, assessment_type, comments, assessment_date, created_at, updated_at)
SELECT mark_id, student_id, subject_id, teacher_id, site_id, academic_year_id, score, assessment_type, comments, assessment_date, created_at, updated_at
FROM student_record_system.marks
WHERE site_id = 3
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
    updated_at = VALUES(updated_at);

INSERT INTO student_record_system_branch_north.student_profiles (student_id, phone, address, date_of_birth, guardian_name, guardian_phone, bio, profile_photo, updated_at)
SELECT sp.student_id, sp.phone, sp.address, sp.date_of_birth, sp.guardian_name, sp.guardian_phone, sp.bio, sp.profile_photo, sp.updated_at
FROM student_record_system.student_profiles sp
JOIN student_record_system.students s ON s.student_id = sp.student_id
WHERE s.site_id = 1
ON DUPLICATE KEY UPDATE
    phone = VALUES(phone),
    address = VALUES(address),
    date_of_birth = VALUES(date_of_birth),
    guardian_name = VALUES(guardian_name),
    guardian_phone = VALUES(guardian_phone),
    bio = VALUES(bio),
    profile_photo = VALUES(profile_photo),
    updated_at = VALUES(updated_at);

INSERT INTO student_record_system_branch_south.student_profiles (student_id, phone, address, date_of_birth, guardian_name, guardian_phone, bio, profile_photo, updated_at)
SELECT sp.student_id, sp.phone, sp.address, sp.date_of_birth, sp.guardian_name, sp.guardian_phone, sp.bio, sp.profile_photo, sp.updated_at
FROM student_record_system.student_profiles sp
JOIN student_record_system.students s ON s.student_id = sp.student_id
WHERE s.site_id = 2
ON DUPLICATE KEY UPDATE
    phone = VALUES(phone),
    address = VALUES(address),
    date_of_birth = VALUES(date_of_birth),
    guardian_name = VALUES(guardian_name),
    guardian_phone = VALUES(guardian_phone),
    bio = VALUES(bio),
    profile_photo = VALUES(profile_photo),
    updated_at = VALUES(updated_at);

INSERT INTO student_record_system_branch_east.student_profiles (student_id, phone, address, date_of_birth, guardian_name, guardian_phone, bio, profile_photo, updated_at)
SELECT sp.student_id, sp.phone, sp.address, sp.date_of_birth, sp.guardian_name, sp.guardian_phone, sp.bio, sp.profile_photo, sp.updated_at
FROM student_record_system.student_profiles sp
JOIN student_record_system.students s ON s.student_id = sp.student_id
WHERE s.site_id = 3
ON DUPLICATE KEY UPDATE
    phone = VALUES(phone),
    address = VALUES(address),
    date_of_birth = VALUES(date_of_birth),
    guardian_name = VALUES(guardian_name),
    guardian_phone = VALUES(guardian_phone),
    bio = VALUES(bio),
    profile_photo = VALUES(profile_photo),
    updated_at = VALUES(updated_at);

INSERT INTO student_record_system_branch_north.subjects (subject_id, subject_name, total_mark, created_at)
SELECT DISTINCT sub.subject_id, sub.subject_name, sub.total_mark, sub.created_at
FROM student_record_system.subjects sub
JOIN student_record_system.marks m ON m.subject_id = sub.subject_id
WHERE m.site_id = 1
ON DUPLICATE KEY UPDATE
    subject_name = VALUES(subject_name),
    total_mark = VALUES(total_mark),
    created_at = VALUES(created_at);

INSERT INTO student_record_system_branch_south.subjects (subject_id, subject_name, total_mark, created_at)
SELECT DISTINCT sub.subject_id, sub.subject_name, sub.total_mark, sub.created_at
FROM student_record_system.subjects sub
JOIN student_record_system.marks m ON m.subject_id = sub.subject_id
WHERE m.site_id = 2
ON DUPLICATE KEY UPDATE
    subject_name = VALUES(subject_name),
    total_mark = VALUES(total_mark),
    created_at = VALUES(created_at);

INSERT INTO student_record_system_branch_east.subjects (subject_id, subject_name, total_mark, created_at)
SELECT DISTINCT sub.subject_id, sub.subject_name, sub.total_mark, sub.created_at
FROM student_record_system.subjects sub
JOIN student_record_system.marks m ON m.subject_id = sub.subject_id
WHERE m.site_id = 3
ON DUPLICATE KEY UPDATE
    subject_name = VALUES(subject_name),
    total_mark = VALUES(total_mark),
    created_at = VALUES(created_at);

INSERT INTO student_record_system_branch_north.teachers (teacher_id, teacher_name, department, assigned_grade, is_homeroom, created_at)
SELECT DISTINCT t.teacher_id, t.teacher_name, t.department, t.assigned_grade, t.is_homeroom, t.created_at
FROM student_record_system.teachers t
JOIN student_record_system.marks m ON m.teacher_id = t.teacher_id
WHERE m.site_id = 1
ON DUPLICATE KEY UPDATE
    teacher_name = VALUES(teacher_name),
    department = VALUES(department),
    assigned_grade = VALUES(assigned_grade),
    is_homeroom = VALUES(is_homeroom),
    created_at = VALUES(created_at);

INSERT INTO student_record_system_branch_south.teachers (teacher_id, teacher_name, department, assigned_grade, is_homeroom, created_at)
SELECT DISTINCT t.teacher_id, t.teacher_name, t.department, t.assigned_grade, t.is_homeroom, t.created_at
FROM student_record_system.teachers t
JOIN student_record_system.marks m ON m.teacher_id = t.teacher_id
WHERE m.site_id = 2
ON DUPLICATE KEY UPDATE
    teacher_name = VALUES(teacher_name),
    department = VALUES(department),
    assigned_grade = VALUES(assigned_grade),
    is_homeroom = VALUES(is_homeroom),
    created_at = VALUES(created_at);

INSERT INTO student_record_system_branch_east.teachers (teacher_id, teacher_name, department, assigned_grade, is_homeroom, created_at)
SELECT DISTINCT t.teacher_id, t.teacher_name, t.department, t.assigned_grade, t.is_homeroom, t.created_at
FROM student_record_system.teachers t
JOIN student_record_system.marks m ON m.teacher_id = t.teacher_id
WHERE m.site_id = 3
ON DUPLICATE KEY UPDATE
    teacher_name = VALUES(teacher_name),
    department = VALUES(department),
    assigned_grade = VALUES(assigned_grade),
    is_homeroom = VALUES(is_homeroom),
    created_at = VALUES(created_at);

INSERT INTO student_record_system_branch_north.teacher_subjects (teacher_id, subject_id, assigned_at)
SELECT DISTINCT teacher_id, subject_id, CURRENT_TIMESTAMP
FROM student_record_system.marks
WHERE site_id = 1
ON DUPLICATE KEY UPDATE
    assigned_at = VALUES(assigned_at);

INSERT INTO student_record_system_branch_south.teacher_subjects (teacher_id, subject_id, assigned_at)
SELECT DISTINCT teacher_id, subject_id, CURRENT_TIMESTAMP
FROM student_record_system.marks
WHERE site_id = 2
ON DUPLICATE KEY UPDATE
    assigned_at = VALUES(assigned_at);

INSERT INTO student_record_system_branch_east.teacher_subjects (teacher_id, subject_id, assigned_at)
SELECT DISTINCT teacher_id, subject_id, CURRENT_TIMESTAMP
FROM student_record_system.marks
WHERE site_id = 3
ON DUPLICATE KEY UPDATE
    assigned_at = VALUES(assigned_at);

SELECT 'Local distributed setup completed. Central coordinator + 3 branch databases are ready.' AS message;
