<?php

/**
 * Load environment variables from a configuration file.
 */
function loadEnv($path = null) {
    if ($path !== null) {
        $paths = [$path];
    } else {
        $basePath = dirname(__DIR__);
        $paths = [
            $basePath . '/.env',
        ];
    }

    $loadedPaths = [];
    foreach ($paths as $candidatePath) {
        if (is_string($candidatePath) && $candidatePath !== '' && file_exists($candidatePath)) {
            $loadedPaths[] = $candidatePath;
        }
    }

    if ($loadedPaths === []) {
        return false;
    }

    foreach ($loadedPaths as $loadedPath) {
        $lines = file($loadedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $key = preg_replace('/^\xEF\xBB\xBF/', '', $key);
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
