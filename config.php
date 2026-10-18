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
        // Cloud MySQL (TiDB Cloud, Aiven, etc.) — enable SSL without cert verification
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        $flags = defined('MYSQLI_CLIENT_SSL') ? MYSQLI_CLIENT_SSL : 0;
        if (defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) {
            $flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        }
        $connected = @$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT, NULL, $flags);
    } else {
        $connected = @$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
    }
} catch (Throwable $e) {
    $connected = false;
    $connError = $e->getMessage();
}

// Check connection — show clear error (not blank page or FUNCTION_INVOCATION_FAILED crash)
if (!$connected || $conn->connect_errno) {
    $errMsg = $connError ?: ($conn->connect_error ?: 'Unknown connection error');
    $errCode = $conn->connect_errno ?: 500;
    http_response_code(500);
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Database Connection Error</title>";
    echo "<style>";
    echo "body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#0f172a;color:#f1f5f9;margin:0;padding:24px;}";
    echo ".container{max-width:680px;margin:40px auto;background:#1e293b;border:1px solid #334155;border-radius:16px;padding:32px;box-shadow:0 10px 25px rgba(0,0,0,0.5);}";
    echo "h2{color:#f87171;margin-top:0;font-size:22px;display:flex;align-items:center;gap:8px;}";
    echo ".badge{background:#450a0a;color:#fca5a5;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;}";
    echo ".box{background:#0f172a;border:1px solid #334155;border-radius:8px;padding:16px;margin:20px 0;font-family:monospace;font-size:13px;color:#fca5a5;word-break:break-all;}";
    echo "p{color:#cbd5e1;line-height:1.6;}";
    echo "ol{color:#cbd5e1;padding-left:20px;line-height:1.7;}";
    echo "code{background:#334155;color:#38bdf8;padding:2px 6px;border-radius:4px;font-size:13px;}";
    echo ".action-btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;margin-top:16px;}";
    echo ".action-btn:hover{background:#1d4ed8;}";
    echo "</style></head><body>";
    echo "<div class='container'>";
    echo "<h2><span>⚠️</span> Database Connection Error <span class='badge'>Error #{$errCode}</span></h2>";
    echo "<p>The application could not connect to the database server at <code>" . htmlspecialchars(DB_HOST) . ":" . DB_PORT . "</code> (Database: <code>" . htmlspecialchars(DB_NAME) . "</code>).</p>";
    echo "<div class='box'>" . htmlspecialchars($errMsg) . "</div>";
    echo "<p><strong>How to resolve this in Vercel:</strong></p>";
    echo "<ol>";
    echo "<li>Open the <a href='https://vercel.com/dashboard' target='_blank' style='color:#38bdf8;'>Vercel Dashboard</a> &rarr; Select your project &rarr; <strong>Settings &rarr; Environment Variables</strong>.</li>";
    echo "<li>Verify the following variables are set for <strong>Production</strong>, <strong>Preview</strong>, and <strong>Development</strong>:";
    echo "<ul>";
    echo "<li><code>DB_HOST</code> (e.g. your TiDB Cloud host <code>gateway01.ap-south-1.prod.aws.tidbcloud.com</code>)</li>";
    echo "<li><code>DB_PORT</code> (usually <code>4000</code> for TiDB Cloud, or <code>3306</code> for MySQL)</li>";
    echo "<li><code>DB_USER</code> (your TiDB/MySQL username)</li>";
    echo "<li><code>DB_PASS</code> (your database password)</li>";
    echo "<li><code>DB_NAME</code> (your database name)</li>";
    echo "</ul></li>";
    echo "<li>If using TiDB Cloud, ensure your cluster allows traffic from any IP (Security Settings &rarr; allow <code>0.0.0.0/0</code>).</li>";
    echo "</ol>";
    echo "<a class='action-btn' href='/api/debug.php?key=ksrdebug2024'>Open Diagnostic Page (/api/debug.php)</a>";
    echo "</div></body></html>";
    exit(0);
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
            PDO::ATTR_TIMEOUT => 5,
        ];
        if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
        }
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    }
}


