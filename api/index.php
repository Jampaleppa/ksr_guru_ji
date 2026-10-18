<?php
// ============================================================
// api/index.php - Vercel Serverless PHP Router / Front-Controller
// ============================================================

// Set temporary session save path for serverless environments
$tmpDir = sys_get_temp_dir();
if (is_writable($tmpDir)) {
    @session_save_path($tmpDir);
}

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath = parse_url($rawUri, PHP_URL_PATH);
$uriPath = urldecode($uriPath);
$cleanPath = ltrim($uriPath, '/');

$rootDir = dirname(__DIR__);

// Route resolution
if ($cleanPath === '' || $cleanPath === 'index.php') {
    $targetFile = $rootDir . '/index.php';
} else {
    $candidate = $rootDir . '/' . $cleanPath;
    if (is_file($candidate)) {
        $targetFile = $candidate;
    } elseif (is_file($candidate . '.php')) {
        // Pretty URL: /cart -> /cart.php
        $targetFile = $candidate . '.php';
    } elseif (is_dir($candidate) && is_file($candidate . '/index.php')) {
        // Directory index: /admin -> /admin/index.php
        $targetFile = $candidate . '/index.php';
    } else {
        // Fallback to home
        $targetFile = $rootDir . '/index.php';
    }
}

// Security guard: prevent path traversal outside root directory
$realTarget = realpath($targetFile);
$realRoot = realpath($rootDir);
if (!$realTarget || strpos($realTarget, $realRoot) !== 0) {
    http_response_code(404);
    echo "404 Not Found";
    exit;
}

// Setup execution context to mimic standard web server
$_SERVER['SCRIPT_FILENAME'] = $realTarget;
$_SERVER['SCRIPT_NAME'] = '/' . ltrim(str_replace($realRoot, '', $realTarget), '/\\');
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

// Switch to the target script's directory so relative includes function correctly
chdir(dirname($realTarget));

// Execute target script
require $realTarget;
