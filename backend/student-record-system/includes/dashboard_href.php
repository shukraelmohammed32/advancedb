<?php

declare(strict_types=1);

if (!function_exists('dash_is_from_pages')) {
    function dash_is_from_pages(): bool
    {
        return !empty($GLOBALS['dashboard_from_pages']);
    }
}

if (!function_exists('dash_index_href')) {
    function dash_index_href(): string
    {
        return dash_is_from_pages() ? '../index.php' : 'index.php';
    }
}

if (!function_exists('dash_page_href')) {
    function dash_page_href(string $script): string
    {
        return (dash_is_from_pages() ? '' : 'pages/') . $script;
    }
}

if (!function_exists('dash_asset_href')) {
    function dash_asset_href(string $path): string
    {
        return (dash_is_from_pages() ? '../' : '') . 'assets/' . ltrim($path, '/');
    }
}

if (!function_exists('dash_auth_href')) {
    function dash_auth_href(string $script): string
    {
        return (dash_is_from_pages() ? '../' : '') . 'auth/' . ltrim($script, '/');
    }
}

if (!function_exists('dash_nav_classes')) {
    function dash_nav_classes(string $key, string $active): string
    {
        return $key === $active ? 'app-sidebar__link is-active' : 'app-sidebar__link';
    }
}
