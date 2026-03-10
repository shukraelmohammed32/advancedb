<?php
if (!isset($footer_base_path)) {
    $footer_base_path = '';
}

$footer_base_path = rtrim((string)$footer_base_path, '/');
if ($footer_base_path !== '') {
    $footer_base_path .= '/';
}
?>
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h4><?php echo htmlspecialchars(t('Student Record System'), ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><?php echo htmlspecialchars(t('Reliable record management for classes, teachers, marks, and reports.'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="footer-section">
                <h4><?php echo htmlspecialchars(t('Quick Links'), ENT_QUOTES, 'UTF-8'); ?></h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'index.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('Dashboard'), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php if (function_exists('canViewStudentDirectory') && canViewStudentDirectory()): ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/students.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endif; ?>
                    <?php if (function_exists('canAccessSubjects') && canAccessSubjects()): ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/subjects.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endif; ?>
                    <?php if (function_exists('canManageTeachers') && canManageTeachers()): ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/teachers.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endif; ?>
                    <?php if (function_exists('canEnterMarks') && canEnterMarks()): ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/marks.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endif; ?>
                    <?php if (function_exists('canAccessSummary') && canAccessSummary()): ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/summary.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('Summary'), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endif; ?>
                    <?php if (function_exists('canViewStudentReports') && canViewStudentReports()): ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/report.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('Reports'), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endif; ?>
                    <?php if (function_exists('canOnlyViewOwnRecords') && canOnlyViewOwnRecords()): ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/profile.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('Profile'), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-section">
                <h4><?php echo htmlspecialchars(t('System'), ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><?php echo htmlspecialchars(t('Role-based access keeps admin, teachers, homeroom teachers, and students inside their correct workflow.'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="footer-section">
                <h4><?php echo htmlspecialchars(t('Version'), ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><?php echo htmlspecialchars(t('Student Record System'), ENT_QUOTES, 'UTF-8'); ?> v2<br><?php echo htmlspecialchars(t('Built with PHP and MySQL'), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(t('Student Record System'), ENT_QUOTES, 'UTF-8'); ?>. <?php echo htmlspecialchars(t('All rights reserved.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
</footer>
