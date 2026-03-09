<?php

/**
 * Load environment variables from a configuration file.
 * Falls back to visible filenames for hosts that hide dotfiles.
 */
function loadEnv($path = null) {
    $paths = [];

    if ($path !== null) {
        $paths[] = $path;
    } else {
        $basePath = dirname(__DIR__);
        $paths = [
            $basePath . '/.env',
            $basePath . '/app.env',
            __DIR__ . '/app.env',
            __DIR__ . '/hosting.env',
        ];
    }

    $loadedPath = null;
    foreach ($paths as $candidatePath) {
        if (is_string($candidatePath) && $candidatePath !== '' && file_exists($candidatePath)) {
            $loadedPath = $candidatePath;
            break;
        }
    }

    if ($loadedPath === null) {
        return false;
    }

    $lines = file($loadedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    return true;
}

/**
 * Get environment variable
 */
function env($key, $default = null) {
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    if (strtolower($value) === 'true') {
        return true;
    } elseif (strtolower($value) === 'false') {
        return false;
    }

    return $value;
}

loadEnv();
?>
