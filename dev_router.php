<?php
/**
 * dev_router.php - PHP built-in server router for KSR GURU JI
 * Routes all requests correctly, suppresses fatal startup for missing sessions
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$filePath = __DIR__ . $path;

// Serve static files directly
if ($path !== '/' && file_exists($filePath) && !is_dir($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
    return false; // let built-in server handle static files
}

// If it's a PHP file, include it
if ($path !== '/' && file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
    $_SERVER['SCRIPT_FILENAME'] = $filePath;
    chdir(dirname($filePath));
    return false; // PHP built-in server handles .php files natively
}

// Default to index.php
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
chdir(__DIR__);
include __DIR__ . '/index.php';
