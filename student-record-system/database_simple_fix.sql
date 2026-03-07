-- Student Academic Record Management System - Simple Enhancement
-- Fixes specific issues without over-engineering
-- Create database
CREATE DATABASE IF NOT EXISTS student_record_system;
USE student_record_system;

-- ============================================
-- ONLY ADD WHAT'S NEEDED - Keep it Simple
-- ============================================

-- Add missing academic_years table (for better year management)
CREATE TABLE IF NOT EXISTS academic_years (
    year_id INT PRIMARY KEY AUTO_INCREMENT,
    year_name VARCHAR(20) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add grades reference table (to standardize grade names)
CREATE TABLE IF NOT EXISTS grades (
    grade_id INT PRIMARY KEY AUTO_INCREMENT,
    grade_name VARCHAR(10) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- IMPROVE EXISTING TABLES (Add missing indexes and constraints)
-- ============================================

-- Add grade_id to students table (for better relationships)
ALTER TABLE students ADD COLUMN grade_id INT NULL AFTER gender;
ALTER TABLE students ADD FOREIGN KEY (grade_id) REFERENCES grades(grade_id) ON DELETE SET NULL;

-- Add academic_year_id to marks table (for better year tracking)
ALTER TABLE marks ADD COLUMN academic_year_id INT NULL AFTER teacher_id;
ALTER TABLE marks ADD FOREIGN KEY (academic_year_id) REFERENCES academic_years(year_id) ON DELETE SET NULL;

-- Add assessment_type to marks table (for different assessment types)
ALTER TABLE marks ADD COLUMN assessment_type ENUM('Exam', 'Quiz', 'Assignment', 'Final') DEFAULT 'Exam' AFTER score;

-- Add comments to marks table (for teacher notes)
ALTER TABLE marks ADD COLUMN comments TEXT NULL AFTER assessment_type;

-- Add assessment_date to marks table (for proper date tracking)
ALTER TABLE marks ADD COLUMN assessment_date DATE NULL AFTER comments;

-- Add updated_at timestamp to marks table (for tracking changes)
ALTER TABLE marks ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- ============================================
-- ADD USEFUL INDEXES (Performance improvements)
-- ============================================

-- Composite indexes for common queries
CREATE INDEX idx_marks_student_subject ON marks(student_id, subject_id);
CREATE INDEX idx_marks_teacher_subject ON marks(teacher_id, subject_id);
CREATE INDEX idx_students_grade ON students(grade_id);

-- ============================================
-- INSERT BASIC REFERENCE DATA
-- ============================================

-- Academic years
INSERT INTO academic_years (year_name, is_active) VALUES
('2023-2024', FALSE),
('2024-2025', TRUE)
ON DUPLICATE KEY UPDATE is_active = VALUES(is_active);

-- Grades (extracted from existing data if any)
INSERT INTO grades (grade_name) VALUES
('Grade 1'), ('Grade 2'), ('Grade 3'), ('Grade 4'), ('Grade 5'), ('Grade 6'),
('Grade 7'), ('Grade 8'), ('Grade 9'), ('Grade 10'), ('Grade 11'), ('Grade 12')
ON DUPLICATE KEY UPDATE grade_name = VALUES(grade_name);

-- Update existing students with grade_id if possible
UPDATE students s 
JOIN grades g ON s.grade = g.grade_name 
SET s.grade_id = g.grade_id 
WHERE s.grade_id IS NULL;

-- Update existing marks with current academic year
UPDATE marks m 
JOIN academic_years ay ON ay.is_active = TRUE 
SET m.academic_year_id = ay.year_id 
WHERE m.academic_year_id IS NULL;

-- ============================================
-- CREATE SIMPLE USEFUL VIEWS
-- ============================================

-- Student summary view (for easy reporting)
CREATE OR REPLACE VIEW student_summary AS
SELECT 
    s.student_id,
    s.name,
    s.grade,
    s.academic_year,
    s.semester,
    COUNT(m.mark_id) as total_marks,
    AVG(m.score) as average_score,
    SUM(CASE WHEN m.score >= 50 THEN 1 ELSE 0 END) as passed_subjects,
    COUNT(m.mark_id) as total_subjects,
    CASE 
        WHEN AVG(m.score) >= 50 THEN 'PASS'
        ELSE 'FAIL'
    END as overall_status
FROM students s
LEFT JOIN marks m ON s.student_id = m.student_id
GROUP BY s.student_id, s.name, s.grade, s.academic_year, s.semester;

-- Subject performance view
CREATE OR REPLACE VIEW subject_performance AS
SELECT 
    sub.subject_name,
    COUNT(m.mark_id) as total_students,
    AVG(m.score) as average_score,
    MAX(m.score) as highest_score,
    MIN(m.score) as lowest_score,
    COUNT(CASE WHEN m.score >= 50 THEN 1 END) as passed_count
FROM subjects sub
LEFT JOIN marks m ON sub.subject_id = m.subject_id
GROUP BY sub.subject_id, sub.subject_name;

-- ============================================
-- ADD SIMPLE STORED PROCEDURE (Useful functionality)
-- ============================================

DELIMITER //

-- Simple procedure to get student rank
CREATE PROCEDURE GetStudentRank(IN p_student_id INT)
BEGIN
    SELECT 
        student_rank,
        total_students
    FROM (
        SELECT 
            s.student_id,
            RANK() OVER (ORDER BY COALESCE(AVG(m.score), 0) DESC) as student_rank,
            COUNT(DISTINCT s.student_id) OVER () as total_students
        FROM students s
        LEFT JOIN marks m ON s.student_id = m.student_id
        GROUP BY s.student_id
    ) ranked
    WHERE student_id = p_student_id;
END //

DELIMITER ;

-- ============================================
-- VERIFICATION
-- ============================================

-- Check if everything was created successfully
SELECT 'Database enhancement completed!' as message;
SELECT 'Tables added: academic_years, grades' as additions;
SELECT 'Tables enhanced: students, marks' as improvements;
SELECT 'Views created: student_summary, subject_performance' as views;
SELECT 'Procedure created: GetStudentRank' as procedures;

-- Test the views
SELECT 'Sample student summary:' as info;
SELECT * FROM student_summary LIMIT 3;

SELECT 'Sample subject performance:' as info;
SELECT * FROM subject_performance LIMIT 3;
