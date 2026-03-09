<?php
require_once '../config/database.php';
require_once '../auth/auth_helper.php';
require_once '../includes/distributed_coordinator.php';

requireLogin();
if (!canAccessDistributedCoordinator()) {
    $_SESSION['error'] = 'Only admin can view branch details.';
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$coordinator = new DistributedCoordinator($db);
$distributed_ready = $coordinator->isDistributedReady();
$site_id = (int)($_GET['site_id'] ?? 0);
$site_details = ($distributed_ready && $site_id > 0) ? $coordinator->getSiteDetails($site_id) : null;
$page_title = $site_details ? ((string)$site_details['site']['site_name'] . ' Details') : 'Branch Details';

function formatBranchDateTime($value, $fallback = 'No activity yet') {
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y h:i A', $timestamp) : $value;
}

function formatBranchValue($value, $fallback = 'N/A') {
    if ($value === null) {
        return $fallback;
    }

    $value = trim((string)$value);
    return $value === '' ? $fallback : $value;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">Student Record System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="students.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="subjects.php">Subjects</a></li>
                    <li class="nav-item"><a class="nav-link" href="teachers.php">Teachers</a></li>
                    <li class="nav-item"><a class="nav-link" href="summary.php">Summary</a></li>
                    <li class="nav-item"><a class="nav-link" href="report.php">Reports</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars((string)($_SESSION['display_name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars(getRoleLabel(), ENT_QUOTES, 'UTF-8'); ?>)
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-1"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="text-muted mb-0">Branch-level records visible to the main admin.</p>
            </div>
            <a href="../index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <?php if (!$distributed_ready): ?>
        <div class="alert alert-warning">Distributed branch oversight is not enabled in the current system.</div>
        <?php elseif (!$site_details): ?>
        <div class="alert alert-danger">The selected branch campus could not be found.</div>
        <?php else: ?>
            <?php
            $site = $site_details['site'];
            $stats = $site_details['stats'];
            $is_online = ($site['connection_status'] ?? '') === 'online';
            ?>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-2">Teachers</div>
                            <h3 class="mb-0"><?php echo (int)($stats['teachers'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-2">Subjects</div>
                            <h3 class="mb-0"><?php echo (int)($stats['subjects'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-2">Students</div>
                            <h3 class="mb-0"><?php echo (int)($stats['students'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-2">Marks</div>
                            <h3 class="mb-0"><?php echo (int)($stats['marks'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-3">
                            <div class="text-muted small">Campus</div>
                            <strong><?php echo htmlspecialchars((string)$site['site_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div class="small text-muted"><?php echo htmlspecialchars((string)$site['site_code'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Database</div>
                            <strong><?php echo htmlspecialchars((string)$site['db_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Connection</div>
                            <span class="badge <?php echo $is_online ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $is_online ? 'Online' : 'Unavailable'; ?></span>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Latest Activity</div>
                            <strong><?php echo htmlspecialchars(formatBranchDateTime($stats['last_activity_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$is_online): ?>
            <div class="alert alert-warning">This branch database is currently unavailable, so only branch metadata can be shown.</div>
            <?php else: ?>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="#teachers-section" class="btn btn-sm btn-outline-primary">Teachers</a>
                <a href="#subjects-section" class="btn btn-sm btn-outline-primary">Subjects</a>
                <a href="#students-section" class="btn btn-sm btn-outline-primary">Students</a>
                <a href="#marks-section" class="btn btn-sm btn-outline-primary">Marks</a>
            </div>

            <div class="card mb-4" id="teachers-section">
                <div class="card-header">Teachers</div>
                <div class="card-body">
                    <?php if (empty($site_details['teachers'])): ?>
                    <p class="mb-0 text-muted">No teachers found in this branch.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Assigned Grade</th>
                                    <th>Subjects</th>
                                    <th>Homeroom</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($site_details['teachers'] as $teacher): ?>
                                <tr>
                                    <td><?php echo (int)($teacher['teacher_id'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($teacher['teacher_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($teacher['assigned_grade'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($teacher['subjects_taught'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo !empty($teacher['is_homeroom']) ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchDateTime($teacher['created_at'] ?? null, '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4" id="subjects-section">
                <div class="card-header">Subjects</div>
                <div class="card-body">
                    <?php if (empty($site_details['subjects'])): ?>
                    <p class="mb-0 text-muted">No subjects found in this branch.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Subject</th>
                                    <th>Total Mark</th>
                                    <th>Teachers Assigned</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($site_details['subjects'] as $subject): ?>
                                <tr>
                                    <td><?php echo (int)($subject['subject_id'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($subject['subject_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)($subject['total_mark'] ?? 0); ?></td>
                                    <td><?php echo (int)($subject['assigned_teachers'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchDateTime($subject['created_at'] ?? null, '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4" id="students-section">
                <div class="card-header">Students</div>
                <div class="card-body">
                    <?php if (empty($site_details['students'])): ?>
                    <p class="mb-0 text-muted">No students found in this branch.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Gender</th>
                                    <th>Grade</th>
                                    <th>Academic Year</th>
                                    <th>Semester</th>
                                    <th>Marks</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($site_details['students'] as $student): ?>
                                <tr>
                                    <td><?php echo (int)($student['student_id'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($student['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($student['gender'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($student['grade'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($student['academic_year'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($student['semester'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)($student['total_marks'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchDateTime($student['created_at'] ?? null, '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" id="marks-section">
                <div class="card-header">Marks</div>
                <div class="card-body">
                    <?php if (empty($site_details['marks'])): ?>
                    <p class="mb-0 text-muted">No marks found in this branch.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Grade</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Score</th>
                                    <th>Type</th>
                                    <th>Assessment Date</th>
                                    <th>Saved</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($site_details['marks'] as $mark): ?>
                                <tr>
                                    <td><?php echo (int)($mark['mark_id'] ?? 0); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(formatBranchValue($mark['student_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($mark['comments'])): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars((string)$mark['comments'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($mark['student_grade'] ?? '', '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($mark['subject_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($mark['teacher_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)($mark['score'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($mark['assessment_type'] ?? '', '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchDateTime($mark['assessment_date'] ?? null, '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchDateTime($mark['activity_at'] ?? null, '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php
    $footer_base_path = '../';
    include __DIR__ . '/../includes/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>