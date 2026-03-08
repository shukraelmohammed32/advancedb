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
                <h4>Student Record System</h4>
                <p>Reliable record management for classes, teachers, marks, and reports.</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'index.php', ENT_QUOTES, 'UTF-8'); ?>">Dashboard</a></li>
                    <?php if (function_exists('canOnlyViewOwnRecords') && canOnlyViewOwnRecords()): ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/profile.php', ENT_QUOTES, 'UTF-8'); ?>">Profile</a></li>
                    <?php else: ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/students.php', ENT_QUOTES, 'UTF-8'); ?>">Students</a></li>
                    <?php if (function_exists('canManageMarks') && canManageMarks()): ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/marks.php', ENT_QUOTES, 'UTF-8'); ?>">Marks</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/summary.php', ENT_QUOTES, 'UTF-8'); ?>">Summary</a></li>
                    <?php endif; ?>
                    <?php if (function_exists('canViewStudentReports') && canViewStudentReports()): ?>
                    <li><a href="<?php echo htmlspecialchars($footer_base_path . 'pages/report.php', ENT_QUOTES, 'UTF-8'); ?>">Reports</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-section">
                <h4>System</h4>
                <p>Secure login, role-based access, and class-level reporting for all users.</p>
            </div>
            <div class="footer-section">
                <h4>Version</h4>
                <p>Student Record System v2<br>Built with PHP and MySQL</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Student Record System. All rights reserved.</p>
        </div>
    </div>
</footer>



