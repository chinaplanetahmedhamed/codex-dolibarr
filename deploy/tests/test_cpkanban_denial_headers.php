<?php

$file = dirname(__DIR__, 2).'/custom/cpkanban/card.php';
$source = file_get_contents($file);
$prefetch = strpos($source, '$externalViewPrefetched = false;');
$header = strpos($source, "llxHeader('', 'Project Card");
$denial = strpos($source, "http_response_code(403);", $prefetch);
$contentType = strpos($source, "header('Content-Type: text/html; charset=UTF-8');", $denial);
$output = strpos($source, "print '<!doctype html>", $contentType);

$ok = $prefetch !== false && $header !== false && $prefetch < $header
    && $denial !== false && $contentType !== false && $output !== false
    && $denial < $contentType && $contentType < $output
    && strpos(substr($source, $output, 500), '__FILE__') === false;

if (!$ok) {
    fwrite(STDERR, "FAIL external denial is not completed before page output\n");
    exit(1);
}
print "PASS external ownership denial precedes headers/output\n";

