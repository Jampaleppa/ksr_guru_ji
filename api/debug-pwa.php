<?php
// Temporary debug endpoint - reveals routing state
// Access: https://ksrguruji.vercel.app/api/debug-pwa.php?tok=ksr2026
if (($_GET['tok'] ?? '') !== 'ksr2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: application/json');
$rawUri  = $_SERVER['REQUEST_URI'] ?? 'n/a';
$uriPath = parse_url($rawUri, PHP_URL_PATH);
$clean   = ltrim(urldecode($uriPath), '/');
echo json_encode([
    'REQUEST_URI'   => $rawUri,
    'cleanPath'     => $clean,
    'PHP_SELF'      => $_SERVER['PHP_SELF']        ?? 'n/a',
    'SCRIPT_NAME'   => $_SERVER['SCRIPT_NAME']     ?? 'n/a',
    'HTTP_HOST'     => $_SERVER['HTTP_HOST']        ?? 'n/a',
    'rootDir'       => dirname(__DIR__),
    'manifestExists'=> file_exists(dirname(__DIR__) . '/manifest.json'),
    'swExists'      => file_exists(dirname(__DIR__) . '/sw.js'),
    'php_version'   => PHP_VERSION,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
