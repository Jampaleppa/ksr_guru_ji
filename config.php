<?php
// ============================================================
// config.php - Database configuration
// ============================================================

require_once __DIR__ . '/logger.php';

// Database credentials – InfinityFree live values.
// These can also be overridden by environment variables so the
// same file works in development and production.
//
// InfinityFree (MySQL Databases panel) example:
//   Host : sqlXXX.infinityfree.com   (NOT localhost)
//   Name : if0_43170510_ksr_guru
//   User : if0_43170510
//   Pass : <your DB password>
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'sqlXXX.infinityfree.com');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'if0_43170510');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: 'YOUR_INFINITYFREE_DB_PASSWORD');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'if0_43170510_ksr_guru');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    // Never leak credentials to visitors in production.
    if (getenv('APP_ENV') === 'development') {
        die("Database connection failed: " . $conn->connect_error);
    }
    error_log('Database connection failed: ' . $conn->connect_error);
    die("The site is temporarily unavailable. Please try again shortly.");
}

// Set charset to utf8mb4 for Kannada text support
$conn->set_charset(DB_CHARSET);

if (!function_exists('getDBConnection')) {
    function getDBConnection(): PDO
    {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    }
}


