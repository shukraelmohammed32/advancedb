-- Update existing database to add first_name and last_name columns
-- Safe to run multiple times on MariaDB/MySQL

USE student_record_system;

-- ============================================
-- ADD FIRST_NAME AND LAST_NAME COLUMNS
-- ============================================

-- Add columns to students table
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS first_name VARCHAR(50) NOT NULL DEFAULT '' AFTER student_id,
ADD COLUMN IF NOT EXISTS last_name VARCHAR(50) NOT NULL DEFAULT '' AFTER first_name;

-- Add columns to teachers table  
ALTER TABLE teachers
ADD COLUMN IF NOT EXISTS first_name VARCHAR(50) NOT NULL DEFAULT '' AFTER teacher_id,
ADD COLUMN IF NOT EXISTS last_name VARCHAR(50) NOT NULL DEFAULT '' AFTER first_name;

-- ============================================
-- MIGRATE EXISTING NAME DATA
-- ============================================

-- Split student names into first_name and last_name
UPDATE students SET 
    first_name = CASE 
        WHEN LOCATE(' ', TRIM(name)) > 0 
        THEN SUBSTRING_INDEX(TRIM(name), ' ', 1)
        ELSE TRIM(name)
    END,
    last_name = CASE 
        WHEN LOCATE(' ', TRIM(name)) > 0 
        THEN SUBSTRING_INDEX(TRIM(name), ' ', -1)
        ELSE ''
    END
WHERE first_name = '' OR last_name = '';

-- Split teacher names into first_name and last_name
UPDATE teachers SET 
    first_name = CASE 
        WHEN LOCATE(' ', TRIM(teacher_name)) > 0 
        THEN SUBSTRING_INDEX(TRIM(teacher_name), ' ', 1)
        ELSE TRIM(teacher_name)
    END,
    last_name = CASE 
        WHEN LOCATE(' ', TRIM(teacher_name)) > 0 
        THEN SUBSTRING_INDEX(TRIM(teacher_name), ' ', -1)
        ELSE ''
    END
WHERE first_name = '' OR last_name = '';

-- ============================================
-- ADD GENERATED COLUMNS (MySQL 5.7+)
-- ============================================

-- Check MySQL version for generated column support
SET @version = (SELECT VERSION());
SET @mysql_version = CAST(SUBSTRING_INDEX(@version, '.', 2) AS DECIMAL(3,1));

-- Add generated name column to students (if MySQL 5.7+)
SET @sql = IF(@mysql_version >= 5.7,
    'ALTER TABLE students ADD COLUMN IF NOT EXISTS name_new VARCHAR(100) GENERATED ALWAYS AS (CONCAT(first_name, \" \", last_name)) STORED',
    'SELECT \"MySQL version too old for generated columns - will use triggers instead\" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add generated teacher_name column to teachers (if MySQL 5.7+)
SET @sql = IF(@mysql_version >= 5.7,
    'ALTER TABLE teachers ADD COLUMN IF NOT EXISTS teacher_name_new VARCHAR(100) GENERATED ALWAYS AS (CONCAT(first_name, \" \", last_name)) STORED',
    'SELECT \"MySQL version too old for generated columns - will use triggers instead\" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- CREATE TRIGGERS FOR OLDER MYSQL VERSIONS
-- ============================================

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS students_before_insert;
DROP TRIGGER IF EXISTS students_before_update;
DROP TRIGGER IF EXISTS teachers_before_insert;
DROP TRIGGER IF EXISTS teachers_before_update;

-- Create triggers to maintain name columns (for MySQL < 5.7)
DELIMITER //

CREATE TRIGGER IF NOT EXISTS students_before_insert
BEFORE INSERT ON students
FOR EACH ROW
BEGIN
    SET NEW.name = CONCAT(NEW.first_name, ' ', NEW.last_name);
END//
    
CREATE TRIGGER IF NOT EXISTS students_before_update
BEFORE UPDATE ON students
FOR EACH ROW
BEGIN
    SET NEW.name = CONCAT(NEW.first_name, ' ', NEW.last_name);
END//

CREATE TRIGGER IF NOT EXISTS teachers_before_insert
BEFORE INSERT ON teachers
FOR EACH ROW
BEGIN
    SET NEW.teacher_name = CONCAT(NEW.first_name, ' ', NEW.last_name);
END//

CREATE TRIGGER IF NOT EXISTS teachers_before_update
BEFORE UPDATE ON teachers
FOR EACH ROW
BEGIN
    SET NEW.teacher_name = CONCAT(NEW.first_name, ' ', NEW.last_name);
END//

DELIMITER ;

-- ============================================
-- UPDATE EMAIL GENERATION LOGIC
-- ============================================

-- Update existing emails to use new name format
SET @teacher_domain = 'school.local';
SET @student_domain = 'school.local';

-- Update teacher emails
UPDATE users u
JOIN teachers t ON t.teacher_id = u.teacher_id
SET u.email = CONCAT(
    LOWER(REPLACE(REPLACE(REPLACE(CONCAT(t.first_name, '.', t.last_name), ' ', '.'), '..', '.'), '''', '')),
    '.', u.user_id, '@', @teacher_domain
)
WHERE u.role = 'teacher'
  AND (u.email IS NULL OR TRIM(u.email) = '' OR u.email LIKE '%@%');

-- Update student emails
UPDATE users u
JOIN students s ON s.student_id = u.student_id
SET u.email = CONCAT(
    LOWER(REPLACE(REPLACE(REPLACE(CONCAT(s.first_name, '.', s.last_name), ' ', '.'), '..', '.'), '''', '')),
    '.', u.user_id, '@', @student_domain
)
WHERE u.role = 'student'
  AND (u.email IS NULL OR TRIM(u.email) = '' OR u.email LIKE '%@%');

-- ============================================
-- VERIFICATION
-- ============================================

SELECT 'First name and last name columns added successfully' AS message;
SELECT 
    COUNT(*) AS total_students,
    COUNT(CASE WHEN first_name != '' THEN 1 END) AS students_with_first_name,
    COUNT(CASE WHEN last_name != '' THEN 1 END) AS students_with_last_name
FROM students;

SELECT 
    COUNT(*) AS total_teachers,
    COUNT(CASE WHEN first_name != '' THEN 1 END) AS teachers_with_first_name,
    COUNT(CASE WHEN last_name != '' THEN 1 END) AS teachers_with_last_name
FROM teachers;

-- Show sample data
SELECT student_id, first_name, last_name, name FROM students LIMIT 5;
SELECT teacher_id, first_name, last_name, teacher_name FROM teachers LIMIT 5;
