<?php
require_once __DIR__ . '/env.php';

function normalizeSessionBasePath($path) {
    $path = str_replace('\\', '/', (string)$path);
    $path = trim($path);

    if ($path === '' || $path === '/') {
        return '/';
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return rtrim($path, '/');
}

function detectAppBasePath() {
    $requestUri = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
    if ($requestUri !== '') {
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        if (is_string($requestPath) && $requestPath !== '') {
            $marker = '/student-record-system';
            $position = strpos($requestPath, $marker);
            if ($position !== false) {
                $publicBase = substr($requestPath, 0, $position + strlen($marker));
                return normalizeSessionBasePath($publicBase);
            }
        }
    }

    $appUrl = env('APP_URL', '');
    $urlPath = is_string($appUrl) ? parse_url($appUrl, PHP_URL_PATH) : '';
    if (is_string($urlPath) && $urlPath !== '') {
        return normalizeSessionBasePath($urlPath);
    }

    $appRoot = realpath(dirname(__DIR__));
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

    if ($appRoot !== false && $documentRoot !== '') {
        $normalizedAppRoot = str_replace('\\', '/', $appRoot);
        $normalizedDocumentRoot = realpath($documentRoot);

        if ($normalizedDocumentRoot !== false) {
            $normalizedDocumentRoot = str_replace('\\', '/', $normalizedDocumentRoot);
            if (strpos($normalizedAppRoot, $normalizedDocumentRoot) === 0) {
                $relativePath = substr($normalizedAppRoot, strlen($normalizedDocumentRoot));
                return normalizeSessionBasePath($relativePath);
            }
        }
    }

    return '/';
}

function detectSessionName() {
    $siteCode = trim((string)env('SITE_CODE', ''));
    $databaseName = trim((string)env('DB_DATABASE', 'student_record_system'));
    $nameSource = $siteCode !== '' ? $siteCode : $databaseName;
    $sessionName = preg_replace('/[^A-Za-z0-9_]/', '_', 'SRS_' . $nameSource);
    $sessionName = strtoupper(trim((string)$sessionName, '_'));

    return $sessionName !== '' ? $sessionName : 'SRS_APP';
}

function startAppSession() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    loadEnv();

    $cookiePath = detectAppBasePath();
    $useSecureCookie = filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN);

    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_path', $cookiePath);

    if ($useSecureCookie) {
        ini_set('session.cookie_secure', '1');
    }

    session_name(detectSessionName());
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => $useSecureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
?>
