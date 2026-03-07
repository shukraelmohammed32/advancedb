-- Add student profile support
USE student_record_system;

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

ALTER TABLE student_profiles
    ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) NULL AFTER bio;

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

SELECT 'student_profiles table is ready with profile_photo support.' AS message;

