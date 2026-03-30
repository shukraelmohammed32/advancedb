<?php

declare(strict_types=1);

require_once __DIR__ . '/dashboard_href.php';

$footer_base_path = dash_is_from_pages() ? '..' : '';

?>
        </main>

<?php include __DIR__ . '/footer.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo htmlspecialchars(dash_asset_href('dashboard-chrome.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php
if (!empty($GLOBALS['dashboard_extra_body_html'])) {
    echo $GLOBALS['dashboard_extra_body_html'];
    $GLOBALS['dashboard_extra_body_html'] = '';
}
?>
</body>
</html>
