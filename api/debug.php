<?php
// ============================================================
// api/debug.php - Temporary Vercel Diagnostic Page
// REMOVE OR PROTECT THIS FILE AFTER DEBUGGING
// ============================================================

// Simple secret key protection — visit /api/debug.php?key=ksrdebug2024
$debugKey = 'ksrdebug2024';
if (($_GET['key'] ?? '') !== $debugKey) {
    http_response_code(403);
    echo "403 Forbidden — add ?key=ksrdebug2024 to URL";
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#eee}
h2{color:#00d4ff}table{border-collapse:collapse;width:100%}
td,th{border:1px solid #444;padding:6px 12px;text-align:left}
th{background:#0f3460}.ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}</style>";

echo "<h2>🔍 KSR GURU JI — Vercel Debug Diagnostic</h2>";

// PHP Info
echo "<h3>PHP Version</h3><p>" . phpversion() . "</p>";

// Environment Variables
echo "<h3>Environment Variables (DB)</h3><table><tr><th>Variable</th><th>Value</th><th>Status</th></tr>";
$vars = ['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_NAME', 'DB_PASS', 'APP_ENV'];
foreach ($vars as $v) {
    $val = getenv($v);
    $display = ($v === 'DB_PASS') ? ($val ? '***SET***' : '(not set)') : ($val ?: '(not set)');
    $cls = $val ? 'ok' : 'err';
    echo "<tr><td>$v</td><td>$display</td><td class='$cls'>" . ($val ? '✅ Set' : '❌ Missing') . "</td></tr>";
}
echo "</table>";

// Database Connection Test
echo "<h3>Database Connection Test</h3>";
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: 3306;
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: 'ksr_guru';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ];
    if ($host !== '127.0.0.1' && $host !== 'localhost') {
        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    $pdo = new PDO($dsn, $user, $pass, $opts);
    $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "<p class='ok'>✅ Connected successfully! MySQL version: <strong>$ver</strong></p>";

    // Check if tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables found: <strong>" . count($tables) . "</strong></p>";
    if ($tables) {
        echo "<ul>" . implode('', array_map(fn($t) => "<li>$t</li>", $tables)) . "</ul>";
    } else {
        echo "<p class='warn'>⚠️ No tables found — you need to import deploy_database.sql</p>";
    }
} catch (Exception $e) {
    echo "<p class='err'>❌ Connection failed: <strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>";
    echo "<p>Host: <code>$host:$port</code> | DB: <code>$name</code> | User: <code>$user</code></p>";
}

// Server Info
echo "<h3>Server Info</h3><table>";
$keys = ['HTTP_HOST','REQUEST_URI','SCRIPT_NAME','PHP_SELF','SERVER_SOFTWARE'];
foreach ($keys as $k) {
    echo "<tr><td>$k</td><td>" . htmlspecialchars($_SERVER[$k] ?? '(not set)') . "</td></tr>";
}
echo "</table>";

// File System
echo "<h3>Filesystem Checks</h3><table><tr><th>Path</th><th>Exists</th><th>Writable</th></tr>";
$paths = [sys_get_temp_dir(), dirname(__DIR__), dirname(__DIR__) . '/logs', dirname(__DIR__) . '/uploads'];
foreach ($paths as $p) {
    $ex = file_exists($p) ? "<span class='ok'>✅</span>" : "<span class='err'>❌</span>";
    $wr = is_writable($p) ? "<span class='ok'>✅</span>" : "<span class='err'>❌</span>";
    echo "<tr><td><code>" . htmlspecialchars($p) . "</code></td><td>$ex</td><td>$wr</td></tr>";
}
echo "</table>";
echo "<hr><p><em>Remove or protect api/debug.php after debugging is complete.</em></p>";
