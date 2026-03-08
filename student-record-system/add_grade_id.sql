-- Add grade_id column to students table only
USE student_record_system;

-- Create grades reference table first
CREATE TABLE IF NOT EXISTS grades (
    grade_id INT PRIMARY KEY AUTO_INCREMENT,
    grade_name VARCHAR(10) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add grade_id column to students table
ALTER TABLE students ADD COLUMN grade_id INT NULL AFTER gender;

-- Add foreign key constraint
ALTER TABLE students ADD FOREIGN KEY (grade_id) REFERENCES grades(grade_id) ON DELETE SET NULL;

-- Insert high school grades only
DELETE FROM grades
WHERE grade_name NOT IN ('Grade 9', 'Grade 10', 'Grade 11', 'Grade 12');

INSERT INTO grades (grade_name) VALUES
('Grade 9'), ('Grade 10'), ('Grade 11'), ('Grade 12')
ON DUPLICATE KEY UPDATE grade_name = VALUES(grade_name);

-- Update existing students with grade_id
UPDATE students s 
JOIN grades g ON s.grade = g.grade_name 
SET s.grade_id = g.grade_id 
WHERE s.grade_id IS NULL;

-- Add index for performance
CREATE INDEX idx_students_grade ON students(grade_id);

-- Verification
SELECT 'grade_id column added successfully!' as message;
SELECT COUNT(*) as grades_added FROM grades;
SELECT COUNT(*) as students_updated FROM students WHERE grade_id IS NOT NULL;
