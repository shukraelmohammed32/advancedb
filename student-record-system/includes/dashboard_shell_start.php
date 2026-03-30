<?php

declare(strict_types=1);

$GLOBALS['dashboard_from_pages'] = !empty($GLOBALS['dashboard_from_pages']);
require_once __DIR__ . '/dashboard_href.php';

$dashboard_nav_active = (string)($GLOBALS['dashboard_nav_active'] ?? '');
$dashboard_page_title = (string)($GLOBALS['dashboard_page_title'] ?? t('Dashboard'));
$dashboard_heading = array_key_exists('dashboard_heading', $GLOBALS) ? $GLOBALS['dashboard_heading'] : null;
$dashboard_subtitle = array_key_exists('dashboard_subtitle', $GLOBALS) ? $GLOBALS['dashboard_subtitle'] : null;
$dashboard_body_class = (string)($GLOBALS['dashboard_body_class'] ?? 'dashboard-body');
$dashboard_extra_head = (string)($GLOBALS['dashboard_extra_head'] ?? '');

$full_title = $dashboard_page_title;

?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLanguageTag(), ENT_QUOTES, 'UTF-8'); ?>" data-dashboard-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($full_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(dash_asset_href('style.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(dash_asset_href('dashboard-app.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <?php echo $dashboard_extra_head; ?>
</head>
<body class="<?php echo htmlspecialchars($dashboard_body_class, ENT_QUOTES, 'UTF-8'); ?>">
    <aside class="app-sidebar" id="appSidebar" aria-label="<?php echo htmlspecialchars(t('Main navigation'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="app-sidebar__brand">
            <a href="<?php echo htmlspecialchars(dash_index_href(), ENT_QUOTES, 'UTF-8'); ?>" class="app-sidebar__logo"><span class="app-sidebar__logo-mark">SR</span><span class="app-sidebar__logo-text"><?php echo htmlspecialchars(t('Record System'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <button type="button" class="app-sidebar__collapse btn-icon" id="sidebarCollapse" title="<?php echo htmlspecialchars(t('Collapse menu'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('Collapse menu'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>
        </div>
        <nav class="app-sidebar__nav">
            <a class="<?php echo htmlspecialchars(dash_nav_classes('dashboard', $dashboard_nav_active), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars(dash_index_href(), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Dashboard'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php if (canViewStudentDirectory()): ?>
            <a class="<?php echo htmlspecialchars(dash_nav_classes('students', $dashboard_nav_active), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars(dash_page_href('students.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-people-fill" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Students'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canViewSubjects()): ?>
            <a class="<?php echo htmlspecialchars(dash_nav_classes('subjects', $dashboard_nav_active), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars(dash_page_href('subjects.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-book-half" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Subjects'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canManageTeachers()): ?>
            <a class="<?php echo htmlspecialchars(dash_nav_classes('teachers', $dashboard_nav_active), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars(dash_page_href('teachers.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-person-badge" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Teachers'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canEnterMarks()): ?>
            <a class="<?php echo htmlspecialchars(dash_nav_classes('marks', $dashboard_nav_active), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars(dash_page_href('marks.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Marks'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canAccessSummary()): ?>
            <a class="<?php echo htmlspecialchars(dash_nav_classes('summary', $dashboard_nav_active), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars(dash_page_href('summary.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Summary'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canViewStudentReports()): ?>
            <a class="<?php echo htmlspecialchars(dash_nav_classes('reports', $dashboard_nav_active), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars(dash_page_href('report.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-file-earmark-text" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Reports'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
            <?php if (canOnlyViewOwnRecords()): ?>
            <a class="<?php echo htmlspecialchars(dash_nav_classes('profile', $dashboard_nav_active), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars(dash_page_href('profile.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-person-circle" aria-hidden="true"></i><span><?php echo htmlspecialchars(t('Profile'), ENT_QUOTES, 'UTF-8'); ?></span></a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="app-sidebar-backdrop" id="sidebarBackdrop" hidden></div>

    <div class="app-main">
        <header class="app-topbar">
            <button type="button" class="btn-icon app-topbar__menu d-lg-none" id="sidebarOpen" aria-label="<?php echo htmlspecialchars(t('Open menu'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-list"></i></button>
            <?php if (canViewStudentDirectory()): ?>
            <form class="app-topbar__search" action="<?php echo htmlspecialchars(dash_page_href('students.php'), ENT_QUOTES, 'UTF-8'); ?>" method="get" role="search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" name="q" class="form-control" placeholder="<?php echo htmlspecialchars(t('Search students…'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" aria-label="<?php echo htmlspecialchars(t('Search'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo isset($_GET['q']) ? htmlspecialchars((string)$_GET['q'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </form>
            <?php else: ?>
            <div class="app-topbar__search app-topbar__search--muted d-flex align-items-center px-3 flex-grow-1">
                <i class="bi bi-mortarboard" aria-hidden="true"></i>
                <span class="small text-muted"><?php echo htmlspecialchars(t('Student Record System'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>
            <div class="app-topbar__actions">
                <button type="button" class="btn-icon" id="themeToggle" title="<?php echo htmlspecialchars(t('Toggle theme'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(t('Toggle dark mode'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <div class="dropdown app-topbar__user">
                    <button class="btn app-topbar__user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="app-topbar__avatar"><?php echo htmlspecialchars(strtoupper(substr((string)($_SESSION['display_name'] ?? 'User'), 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="app-topbar__user-text d-none d-sm-flex">
                            <span class="app-topbar__user-name"><?php echo htmlspecialchars((string)($_SESSION['display_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="app-topbar__user-role"><?php echo htmlspecialchars(getRoleLabel(), ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                        <i class="bi bi-chevron-down small ms-1 opacity-50"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end app-topbar__dropdown shadow border-0">
                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars(dash_auth_href('logout.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-box-arrow-right me-2"></i><?php echo htmlspecialchars(t('Logout'), ENT_QUOTES, 'UTF-8'); ?></a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content">
            <?php if ($dashboard_heading !== null && $dashboard_heading !== ''): ?>
            <div class="app-content__head">
                <div>
                    <h1 class="app-content__title"><?php echo htmlspecialchars((string)$dashboard_heading, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <?php if ($dashboard_subtitle !== null && $dashboard_subtitle !== ''): ?>
                    <p class="app-content__subtitle"><?php echo htmlspecialchars((string)$dashboard_subtitle, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
