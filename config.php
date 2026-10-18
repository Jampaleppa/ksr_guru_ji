<?php
// ============================================================
// config.php - Database configuration
// ============================================================

require_once __DIR__ . '/logger.php';

// ============================================================
// Database credentials
// For InfinityFree: Get these from VistaPanel -> MySQL Databases
// For Local WAMP: Defaults are 127.0.0.1 / root / JKRathod@406 / ksr_guru
// ============================================================
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1'); // e.g. 'sql302.infinityfree.com'
}
if (!defined('DB_PORT')) {
    define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');      // e.g. 'if0_38000000'
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'JKRathod@406'); // Account password
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'ksr_guru');  // e.g. 'if0_38000000_ksr_guru'
}


// Create connection — supports both local MySQL and cloud (TiDB / Aiven) with SSL
$conn = mysqli_init();
if (!$conn) {
    die("<div style='font-family:sans-serif;padding:20px;color:#721c24;background:#f8d7da;border-radius:8px;'>mysqli_init() failed. PHP mysqli extension may not be loaded.</div>");
}

if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
    // Cloud MySQL (TiDB Cloud, Aiven, etc.) — enable SSL without cert verification
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $connected = $conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT, NULL, MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);
} else {
    $connected = $conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
}

// Check connection — show clear error (not blank page)
if (!$connected || $conn->connect_errno) {
    $errMsg = $conn->connect_error ?: 'Unknown connection error';
    die("<div style='font-family:sans-serif;padding:20px;margin:20px auto;max-width:600px;border:1px solid #f5c6cb;background:#f8d7da;color:#721c24;border-radius:8px;'>" .
        "<h3>&#9888; Database Connection Error</h3>" .
        "<p>Could not connect to <strong>" . htmlspecialchars(DB_HOST) . ":" . DB_PORT . "</strong></p>" .
        "<p><small>Error #" . $conn->connect_errno . ": " . htmlspecialchars($errMsg) . "</small></p>" .
        "<p>Please set DB_HOST, DB_USER, DB_PASS, DB_NAME in Vercel Environment Variables.</p>" .
        "</div>");
}

// Set charset to utf8mb4 for Kannada text support
$conn->set_charset("utf8mb4");



if (!function_exists('getDBConnection')) {
    function getDBConnection(): PDO
    {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
        }
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    }
}


