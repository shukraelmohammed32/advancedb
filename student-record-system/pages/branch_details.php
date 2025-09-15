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
$page_title = $site_details ? ((string)$site_details['site']['site_name'] . ' ' . t('Details')) : t('Branch Details');

function formatBranchDateTime($value, $fallback = null) {
    if ($fallback === null) {
        $fallback = t('No activity yet');
    }

    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y h:i A', $timestamp) : $value;
}

function formatBranchValue($value, $fallback = null) {
    if ($fallback === null) {
        $fallback = t('N/A');
    }

    if ($value === null) {
        return $fallback;
    }

    $value = trim((string)$value);
    return $value === '' ? $fallback : $value;
}
?>

<?php
$GLOBALS['dashboard_from_pages'] = true;
$GLOBALS['dashboard_nav_active'] = 'dashboard';
$GLOBALS['dashboard_page_title'] = (string)$page_title . ' — ' . t('Student Record System');
$GLOBALS['dashboard_heading'] = '';
$GLOBALS['dashboard_subtitle'] = '';
include __DIR__ . '/../includes/dashboard_shell_start.php';
?>

    <div class="container py-2 mb-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-1"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="text-muted mb-0"><?php echo htmlspecialchars(t('Branch-level records visible to the main admin.'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <a href="../index.php" class="btn btn-outline-secondary"><?php echo htmlspecialchars(t('Back to Dashboard'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>

        <?php if (!$distributed_ready): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars(t('Distributed branch oversight is not enabled in the current system.'), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php elseif (!$site_details): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars(t('The selected branch campus could not be found.'), ENT_QUOTES, 'UTF-8'); ?></div>
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
                            <div class="text-muted small mb-2"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <h3 class="mb-0"><?php echo (int)($stats['teachers'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-2"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <h3 class="mb-0"><?php echo (int)($stats['subjects'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-2"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <h3 class="mb-0"><?php echo (int)($stats['students'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-2"><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <h3 class="mb-0"><?php echo (int)($stats['marks'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-3">
                            <div class="text-muted small"><?php echo htmlspecialchars(t('Campus'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <strong><?php echo htmlspecialchars((string)$site['site_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div class="small text-muted"><?php echo htmlspecialchars((string)$site['site_code'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small"><?php echo htmlspecialchars(t('Database'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <strong><?php echo htmlspecialchars((string)$site['db_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small"><?php echo htmlspecialchars(t('Connection'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <span class="badge <?php echo $is_online ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($is_online ? t('Online') : t('Unavailable'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small"><?php echo htmlspecialchars(t('Latest Activity'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <strong><?php echo htmlspecialchars(formatBranchDateTime($stats['last_activity_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$is_online): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars(t('This branch database is currently unavailable, so only branch metadata can be shown.'), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="#teachers-section" class="btn btn-sm btn-outline-primary"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#subjects-section" class="btn btn-sm btn-outline-primary"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#students-section" class="btn btn-sm btn-outline-primary"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></a>
                <a href="#marks-section" class="btn btn-sm btn-outline-primary"><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></a>
            </div>

            <div class="card mb-4" id="teachers-section">
                <div class="card-header"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="card-body">
                    <?php if (empty($site_details['teachers'])): ?>
                    <p class="mb-0 text-muted"><?php echo htmlspecialchars(t('No teachers found in this branch.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars(t('ID'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Name'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Assigned Grade'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Homeroom'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Created'), ENT_QUOTES, 'UTF-8'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($site_details['teachers'] as $teacher): ?>
                                <tr>
                                    <td><?php echo (int)($teacher['teacher_id'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($teacher['teacher_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($teacher['assigned_grade'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatBranchValue($teacher['subjects_taught'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(!empty($teacher['is_homeroom']) ? t('Yes') : t('No'), ENT_QUOTES, 'UTF-8'); ?></td>
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
                <div class="card-header"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="card-body">
                    <?php if (empty($site_details['subjects'])): ?>
                    <p class="mb-0 text-muted"><?php echo htmlspecialchars(t('No subjects found in this branch.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars(t('ID'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Subject'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Total Mark'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Teachers Assigned'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Created'), ENT_QUOTES, 'UTF-8'); ?></th>
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
                <div class="card-header"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="card-body">
                    <?php if (empty($site_details['students'])): ?>
                    <p class="mb-0 text-muted"><?php echo htmlspecialchars(t('No students found in this branch.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars(t('ID'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Name'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Gender'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Grade'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Academic Year'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Semester'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Created'), ENT_QUOTES, 'UTF-8'); ?></th>
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
                <div class="card-header"><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="card-body">
                    <?php if (empty($site_details['marks'])): ?>
                    <p class="mb-0 text-muted"><?php echo htmlspecialchars(t('No marks found in this branch.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars(t('ID'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Student'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Grade'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Subject'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Teacher'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Score'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Type'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Assessment Date'), ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th><?php echo htmlspecialchars(t('Saved'), ENT_QUOTES, 'UTF-8'); ?></th>
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

    <?php include __DIR__ . '/../includes/dashboard_shell_end.php'; ?>