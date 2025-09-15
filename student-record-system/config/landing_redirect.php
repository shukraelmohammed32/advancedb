<?php

function publicLandingPath(array $query = [], string $fragment = ''): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $path = '../sign-in.html';

    if (preg_match('#/student-record-system/(pages|actions|auth)/#', $scriptName) === 1) {
        $path = '../../sign-in.html';
    }

    $filteredQuery = array_filter(
        $query,
        static fn ($value) => $value !== null && $value !== ''
    );

    if ($filteredQuery !== []) {
        $path .= '?' . http_build_query($filteredQuery);
    }

    if ($fragment !== '') {
        $path .= '#' . ltrim($fragment, '#');
    }

    return $path;
}

function redirectToPublicLanding(array $query = [], string $fragment = ''): void
{
    header('Location: ' . publicLandingPath($query, $fragment));
    exit();
}
