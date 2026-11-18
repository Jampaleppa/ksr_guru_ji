<?php
// ============================================================
// downloads/download.php — Secure File Download Handler
// ============================================================

$file = $_GET['file'] ?? '';
$baseDir = __DIR__;

$allowed = [
    'apk' => [
        'path' => $baseDir . '/ksr-guru-ji.apk',
        'name' => 'KSR-Guru-Ji-v3.0.apk',
        'mime' => 'application/vnd.android.package-archive'
    ],
    'aab' => [
        'path' => $baseDir . '/ksr-guru-ji.aab',
        'name' => 'KSR-Guru-Ji-v3.0.aab',
        'mime' => 'application/octet-stream'
    ],
    'mobileconfig' => [
        'path' => $baseDir . '/ksr-guru-ji.mobileconfig',
        'name' => 'KSR-Guru-Ji.mobileconfig',
        'mime' => 'application/x-apple-aspen-config'
    ],
    'ios' => [
        'path' => $baseDir . '/ksr-guru-ji.mobileconfig',
        'name' => 'KSR-Guru-Ji.mobileconfig',
        'mime' => 'application/x-apple-aspen-config'
    ]
];

if (!isset($allowed[$file])) {
    header('HTTP/1.1 404 Not Found');
    echo 'File not found. Please visit <a href="/download-app.php">the App Download page</a>.';
    exit;
}

$target = $allowed[$file];
$filepath = $target['path'];

if (!file_exists($filepath)) {
    header('HTTP/1.1 404 Not Found');
    echo 'The requested package is being prepared. Please check back shortly.';
    exit;
}

// Clear any output buffering
while (ob_get_level()) {
    ob_end_clean();
}

$filesize = filesize($filepath);

header('Content-Description: File Transfer');
header('Content-Type: ' . $target['mime']);
header('Content-Disposition: attachment; filename="' . $target['name'] . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $filesize);

readfile($filepath);
exit;
