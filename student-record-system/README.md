# Student Academic Record Management System

A comprehensive web-based system for managing student academic records, generating reports, and tracking performance.

## Features

- **Student Management**: Register, edit, and delete student records
- **Subject Management**: Add and manage academic subjects
- **Teacher Management**: Manage teacher information and assignments
- **Mark Entry**: Enter and manage student marks with validation
- **Report Generation**: Generate comprehensive academic reports with rankings
- **Dashboard**: Overview with key statistics

## Tech Stack

- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript
- **Backend**: PHP 8+
- **Database**: MySQL
- **Server**: Apache (XAMPP recommended)

## Installation

### Prerequisites
- XAMPP (or similar LAMP/WAMP stack)
- MySQL database
- PHP 7.4 or higher

### Setup Instructions

1. **Download/Clone the Project**
   ```bash
   git clone <repository-url>
   cd student-record-system
   ```

2. **Database Setup**
   - Start XAMPP and start Apache and MySQL services
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the `database.sql` file to create the database and tables
   - Alternatively, run the SQL commands directly in phpMyAdmin

3. **Configure Database Connection**
   - Open `config/database.php`
   - Update the database credentials if needed:
     ```php
     private $host = "localhost";
     private $username = "root";
     private $password = "";
     private $database = "student_record_system";
     ```

4. **Deploy the Application**
   - Copy the project files to your XAMPP htdocs folder
   - Navigate to `http://localhost/student-record-system` in your browser

## Usage

### 1. Dashboard
- View total counts of students, teachers, and subjects
- Quick access to all main functions

### 2. Student Management
- Add new students with personal and academic information
- Edit existing student details
- Delete student records
- View complete student list

### 3. Subject Management
- Add new subjects with total marks
- Edit subject details
- Delete subjects (cascades to related marks)

### 4. Teacher Management
- Add teachers with department and grade assignments
- Assign homeroom teachers (one per grade)
- Edit and delete teacher records

### 5. Mark Entry
- Enter marks for students across different subjects
- Validate scores (0-100 range)
- Prevent duplicate entries
- Edit and delete existing marks

### 6. Report Generation
- Generate comprehensive academic reports for individual students
- View subject-wise performance
- Calculate total, average, and rank
- Determine pass/fail status
- Print-friendly reports

## Database Schema

### Tables

1. **students**
   - student_id (Primary Key)
   - name, gender, grade
   - academic_year, semester
   - created_at

2. **subjects**
   - subject_id (Primary Key)
   - subject_name, total_mark
   - created_at

3. **teachers**
   - teacher_id (Primary Key)
   - teacher_name, department
   - assigned_grade, is_homeroom
   - created_at

4. **marks**
   - mark_id (Primary Key)
   - student_id, subject_id, teacher_id
   - score (0-100)
   - created_at

## Default Subjects

The system comes pre-configured with these subjects:
- Maths
- English
- Biology
- Chemistry
- Physics

## Validation Rules

- **Score Range**: 0-100 marks
- **Pass Mark**: 50 marks per subject
- **Homeroom Teachers**: Only one per grade
- **Duplicate Marks**: Prevented for same student-subject combination

## Report Features

- **Total Score**: Sum of all subject marks
- **Average**: Mean of all subject marks
- **Rank**: Student ranking based on total score (SQL Window Function)
- **Status**: PASS if all subjects ≥ 50, FAIL otherwise

## File Structure

```
student-record-system/
├── actions/                 # Form processing scripts
│   ├── add_student.php
│   ├── add_subject.php
│   ├── add_teacher.php
│   └── add_mark.php
├── assets/
│   └── style.css           # Custom styles
├── config/
│   └── database.php        # Database connection
├── pages/                  # Main application pages
│   ├── students.php
│   ├── subjects.php
│   ├── teachers.php
│   ├── marks.php
│   └── report.php
├── database.sql            # Database schema and data
├── index.php              # Dashboard
└── README.md              # This file
```

## Security Considerations

- Input validation and sanitization
- SQL injection prevention using prepared statements
- Score range validation
- Confirmation dialogs for destructive actions

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Support

For issues and questions, please refer to the documentation or contact the development team.

## License

This project is for educational purposes. Feel free to modify and distribute according to your needs.
