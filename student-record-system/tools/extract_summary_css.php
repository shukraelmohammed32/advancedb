<?php
$s = file_get_contents(__DIR__ . '/../pages/summary.php');
if (!preg_match('/<style>(.*)<\/style>/s', $s, $m)) {
    fwrite(STDERR, "no match\n");
    exit(1);
}
$c = $m[1];
$c = str_replace('body.summary-page', 'body.dashboard-body.summary-page', $c);
$c = str_replace('.summary-page .main-container', 'body.dashboard-body.summary-page .main-container', $c);
$c = str_replace('padding-top: 72px;', 'padding-top: 0;', $c);
file_put_contents(__DIR__ . '/../assets/summary-page.css', $c);
echo strlen($c) . " bytes\n";
