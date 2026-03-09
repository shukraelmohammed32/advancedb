<?php

/**
 * Bootstrap the application
 * Initialize all required components
 */

// Load environment variables
require_once __DIR__ . '/env.php';

// Load application configuration
require_once __DIR__ . '/app_config.php';

// Initialize error handling
if (file_exists(__DIR__ . '/error_handler.php')) {
    require_once __DIR__ . '/error_handler.php';
}

// Set timezone
date_default_timezone_set('UTC');

// Start application session with an app-specific cookie name and path.
require_once __DIR__ . '/session.php';
startAppSession();

// Set session lifetime
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > AppConfig::getSessionLifetime() * 60)) {
    session_unset();
    session_destroy();
    header('Location: auth/login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Security headers
if (AppConfig::isForceHttps()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// CORS headers
$allowed_origins = AppConfig::getAllowedOrigins();
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
