<?php
$path = __DIR__ . '/../pages/summary.php';
$s = file_get_contents($path);
$start = 'REMOVED_STYLE_BLOCK_START';
$p0 = strpos($s, $start);
if ($p0 === false) {
    fwrite(STDERR, "marker not found\n");
    exit(1);
}
$p1 = strpos($s, '<div class="main-container">', $p0);
if ($p1 === false) {
    fwrite(STDERR, "main-container not found\n");
    exit(1);
}
$s = substr($s, 0, $p0) . substr($s, $p1);
file_put_contents($path, $s);
echo "trimmed\n";
