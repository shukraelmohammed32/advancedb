<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Handle form submission for generating report
$report_data = null;
$selected_student = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
    $student_id = (int)$_POST['student_id'];
    
    // Get student information
    $student_result = $conn->query("SELECT * FROM students WHERE student_id = $student_id");
    $selected_student = $student_result->fetch_assoc();
    
    if ($selected_student) {
        // Get marks for all subjects for this student
        $marks_query = "SELECT 
                            s.subject_name,
                            m.score,
                            CASE 
                                WHEN m.score >= 50 THEN 'PASS' 
                                ELSE 'FAIL' 
                            END as status
                        FROM subjects s
                        LEFT JOIN marks m ON s.subject_id = m.subject_id AND m.student_id = $student_id
                        ORDER BY s.subject_name";
        
        $marks_result = $conn->query($marks_query);
        $marks = [];
        $total_score = 0;
        $subject_count = 0;
        $all_passed = true;
        $failed_subjects = 0;
        
        while ($mark = $marks_result->fetch_assoc()) {
            $marks[$mark['subject_name']] = $mark;
            
            if ($mark['score'] !== null) {
                $total_score += $mark['score'];
                $subject_count++;
                
                if ($mark['score'] < 50) {
                    $all_passed = false;
                    $failed_subjects++;
                }
            } else {
                // Student has no marks for this subject - consider as fail
                $all_passed = false;
                $failed_subjects++;
            }
        }
        
        $average = $subject_count > 0 ? round($total_score / $subject_count, 2) : 0;
        
        // Get rank using window function
        $rank_query = "SELECT student_rank 
                       FROM (
                           SELECT 
                               student_id,
                               RANK() OVER (ORDER BY COALESCE(SUM(score), 0) DESC) as student_rank
                           FROM marks
                           GROUP BY student_id
                       ) ranked
                       WHERE student_id = $student_id";
        
        $rank_result = $conn->query($rank_query);
        $rank_row = $rank_result->fetch_assoc();
        $rank = $rank_row ? $rank_row['student_rank'] : 'N/A';
        
        // Overall status: PASS only if student has marks for ALL subjects and ALL are >= 50
        $total_subjects = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
        $has_all_marks = $subject_count >= $total_subjects;
        
        $report_data = [
            'student' => $selected_student,
            'marks' => $marks,
            'total' => $total_score,
            'average' => $average,
            'rank' => $rank,
            'status' => ($has_all_marks && $all_passed && $subject_count > 0) ? 'PASS' : 'FAIL'
        ];
    }
}

// Get all students for dropdown
$students = $conn->query("SELECT student_id, name, grade FROM students ORDER BY name");

// Get all subjects for display
$subjects = $conn->query("SELECT subject_name FROM subjects ORDER BY subject_name");
$all_subjects = [];
while ($subject = $subjects->fetch_assoc()) {
    $all_subjects[] = $subject['subject_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .report-card { box-shadow: none !important; }
        }
        .report-header {
            background-color: #007bff;
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .student-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .report-table th {
            background-color: #007bff;
            color: white;
        }
        .summary-card {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark no-print">
        <div class="container">
            <a class="navbar-brand" href="../index.php">Student Record System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="students.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="subjects.php">Subjects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="teachers.php">Teachers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="marks.php">Marks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="report.php">Reports</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Academic Reports</h1>
            </div>
        </div>

        <div class="row">
            <!-- Report Generation Form -->
            <div class="col-md-4 no-print">
                <div class="card">
                    <div class="card-header">
                        Generate Report
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Select Student</label>
                                <select class="form-control" id="student_id" name="student_id" required>
                                    <option value="">Select Student</option>
                                    <?php while ($student = $students->fetch_assoc()): ?>
                                        <option value="<?php echo $student['student_id']; ?>" 
                                                <?php echo isset($_POST['student_id']) && $_POST['student_id'] == $student['student_id'] ? 'selected' : ''; ?>>
                                            <?php echo $student['name'] . ' - Grade ' . $student['grade']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" name="generate_report">
                                Generate Report
                            </button>
                            <?php if ($report_data): ?>
                                <button type="button" class="btn btn-secondary" onclick="window.print()">
                                    <i class="fas fa-print"></i> Print Report
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Report Display -->
            <div class="col-md-8">
                <?php if ($report_data): ?>
                    <div class="card report-card">
                        <!-- Report Header -->
                        <div class="report-header">
                            <h2>Student Academic Report</h2>
                            <p>Academic Year: <?php echo $report_data['student']['academic_year']; ?> - Semester: <?php echo $report_data['student']['semester']; ?></p>
                        </div>

                        <!-- Student Information -->
                        <div class="student-info">
                            <h4>Student Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Name:</strong> <?php echo $report_data['student']['name']; ?><br>
                                    <strong>Student ID:</strong> <?php echo $report_data['student']['student_id']; ?><br>
                                    <strong>Grade:</strong> <?php echo $report_data['student']['grade']; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Gender:</strong> <?php echo $report_data['student']['gender']; ?><br>
                                    <strong>Academic Year:</strong> <?php echo $report_data['student']['academic_year']; ?><br>
                                    <strong>Semester:</strong> <?php echo $report_data['student']['semester']; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Subject Marks Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered report-table">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_subjects as $subject): ?>
                                        <tr>
                                            <td><?php echo $subject; ?></td>
                                            <td>
                                                <?php 
                                                if (isset($report_data['marks'][$subject])) {
                                                    $score = $report_data['marks'][$subject]['score'];
                                                    if ($score !== null) {
                                                        echo $score;
                                                    } else {
                                                        echo '<span class="text-danger">No Marks</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-danger">No Marks</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if (isset($report_data['marks'][$subject])) {
                                                    $status = $report_data['marks'][$subject]['status'];
                                                    $score = $report_data['marks'][$subject]['score'];
                                                    
                                                    if ($score !== null) {
                                                        if ($score >= 50) {
                                                            echo '<span class="badge bg-success">' . $status . '</span>';
                                                        } else {
                                                            echo '<span class="badge bg-danger">' . $status . '</span>';
                                                        }
                                                    } else {
                                                        echo '<span class="badge bg-danger">FAIL</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-danger">FAIL</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Summary -->
                        <div class="summary-card">
                            <h4>Summary</h4>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Total Score:</strong><br>
                                    <h3><?php echo $report_data['total']; ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <strong>Average:</strong><br>
                                    <h3><?php echo $report_data['average']; ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <strong>Rank:</strong><br>
                                    <h3><?php echo $report_data['rank']; ?></h3>
                                </div>
                                <div class="col-md-3">
                                    <strong>Overall Status:</strong><br>
                                    <h3>
                                        <?php 
                                        if ($report_data['status'] == 'PASS') {
                                            echo '<span class="pass">' . $report_data['status'] . '</span>';
                                        } else {
                                            echo '<span class="fail">' . $report_data['status'] . '</span>';
                                        }
                                        ?>
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="text-center mt-4 no-print">
                            <small class="text-muted">
                                Generated on: <?php echo date('Y-m-d H:i:s'); ?>
                            </small>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h4>No Report Generated</h4>
                            <p class="text-muted">Select a student from the form to generate their academic report.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>