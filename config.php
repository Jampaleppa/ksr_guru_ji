<?php
// ============================================================
// config.php - Database configuration
// ============================================================

require_once __DIR__ . '/logger.php';
// Shared DB layer — provides _bootstrapSQLiteSchema() used by the SQLite fallback.
require_once __DIR__ . '/includes/db.php';

// ============================================================
// Database credentials
// For InfinityFree: Get these from VistaPanel -> MySQL Databases
// For Local WAMP: Configure DB_HOST, DB_USER, DB_PASS, DB_NAME in environment
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
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'ksr_guru');  // e.g. 'if0_38000000_ksr_guru'
}


// Define SSL constant fallback if not already defined
if (!defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) {
    define('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT', 64);
}

// Disable automatic exception throwing so we can catch & display connection errors gracefully
mysqli_report(MYSQLI_REPORT_OFF);

// Create connection — supports both local MySQL and cloud (TiDB / Aiven) with SSL
$conn = mysqli_init();
if (!$conn) {
    http_response_code(500);
    echo "<div style='font-family:sans-serif;padding:20px;color:#721c24;background:#f8d7da;border-radius:8px;'>mysqli_init() failed. PHP mysqli extension may not be loaded.</div>";
    exit(0);
}

// Set connection timeout (5 seconds) so serverless functions don't hang and hit maxDuration
if (defined('MYSQLI_OPT_CONNECT_TIMEOUT')) {
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
}

$connected = false;
$connError = '';

try {
    if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
        // Cloud MySQL (TiDB Cloud, Aiven, etc.) — enable SSL with optional CA cert verification
        $_dbSslCa = getenv('DB_SSL_CA') ?: '';
        if (!empty($_dbSslCa) && is_readable($_dbSslCa)) {
            // Verified TLS: CA cert provided — recommended for production
            $conn->ssl_set(NULL, NULL, $_dbSslCa, NULL, NULL);
            $flags = defined('MYSQLI_CLIENT_SSL') ? MYSQLI_CLIENT_SSL : 0;
        } else {
            // Unverified TLS: no CA cert configured — log warning in production
            if (getenv('KSR_ENV') === 'production' || getenv('APP_ENV') === 'production') {
                error_log('SECURITY WARNING: DB_SSL_CA not set; connecting to cloud MySQL without certificate verification.');
            }
            $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
            $flags = defined('MYSQLI_CLIENT_SSL') ? MYSQLI_CLIENT_SSL : 0;
            if (defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) {
                $flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
            }
        }
        $connected = @$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT, NULL, $flags);
    } else {
        $connected = @$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
    }
} catch (Throwable $e) {
    $connected = false;
    $connError = $e->getMessage();
}

// Check connection — if not connected, set $conn = null so application can use graceful fallback
if (!$connected || $conn->connect_errno) {
    $dbErrMsg = $connError ?: ($conn->connect_error ?? 'Unable to connect to database');
    $conn = null;
    define('DB_CONNECTED', false);
    define('DB_ERROR_MESSAGE', $dbErrMsg);
} else {
    define('DB_CONNECTED', true);
    define('DB_ERROR_MESSAGE', '');
    // Set charset to utf8mb4 for Kannada text support
    $conn->set_charset("utf8mb4");
}

if (!function_exists('getDBConnection')) {
    function getDBConnection(): PDO
    {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];
        if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
            $_dbSslCa = getenv('DB_SSL_CA') ?: '';
            if (!empty($_dbSslCa) && is_readable($_dbSslCa)) {
                // Verified TLS with CA cert
                if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $_dbSslCa;
                }
                if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                }
            } else {
                // No CA cert: use SSL without verification (log warning in production)
                if (getenv('KSR_ENV') === 'production' || getenv('APP_ENV') === 'production') {
                    error_log('SECURITY WARNING: DB_SSL_CA not set; PDO connecting without certificate verification.');
                }
                if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }
        }
        try {
            return new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (Throwable $e) {
            $_isProduction = (getenv('KSR_ENV') === 'production' || getenv('APP_ENV') === 'production');
            if ($_isProduction) {
                // In production, fail closed: do NOT silently fall back to SQLite.
                // A hidden SQLite database would create silent data loss and inconsistency.
                error_log('CRITICAL: Primary MySQL database unavailable in production: ' . $e->getMessage());
                http_response_code(503);
                echo json_encode(['success' => false, 'error' => 'Service temporarily unavailable. Please try again shortly.']);
                exit;
            }
            // Local development fallback to SQLite only
            $sqliteDir = is_writable(__DIR__) ? __DIR__ : sys_get_temp_dir();
            $sqliteFile = $sqliteDir . '/ksr_local_database.sqlite';
            $pdo = new PDO('sqlite:' . $sqliteFile, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            if (function_exists('_bootstrapSQLiteSchema')) {
                _bootstrapSQLiteSchema($pdo);
            }
            return $pdo;
        }
    }
}


