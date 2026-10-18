<?php
// ============================================================
// api/debug.php - Temporary Vercel Diagnostic Page
// Visit: /api/debug.php?key=ksrdebug2024
// REMOVE OR PROTECT THIS FILE AFTER DEBUGGING
// ============================================================
$debugKey = 'ksrdebug2024';
if (($_GET['key'] ?? '') !== $debugKey) {
    http_response_code(403);
    echo "403 Forbidden — add ?key=ksrdebug2024 to URL";
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='utf-8'>
<title>KSR Debug</title>
<style>
  body{font-family:monospace;padding:24px;background:#0f0f1a;color:#e0e0e0;margin:0}
  h1{color:#00d4ff;margin-bottom:4px}h2{color:#60a5fa;border-bottom:1px solid #334;padding-bottom:4px}
  table{border-collapse:collapse;width:100%;margin-bottom:20px}
  td,th{border:1px solid #334;padding:8px 14px;text-align:left}
  th{background:#1e1e3a;color:#93c5fd}
  .ok{color:#4ade80;font-weight:bold}.err{color:#f87171;font-weight:bold}.warn{color:#fbbf24;font-weight:bold}
  code{background:#1a1a2e;padding:2px 6px;border-radius:4px;color:#a78bfa}
  .box{background:#1a1a2e;border:1px solid #334;border-radius:8px;padding:16px;margin-bottom:16px}
</style></head><body>";

echo "<h1>🔍 KSR GURU JI — Vercel Debug</h1><p>PHP " . phpversion() . " | " . date('Y-m-d H:i:s') . " IST</p>";

// ── 1. Environment Variables ──────────────────────────────────
echo "<h2>1. Environment Variables</h2><table>
<tr><th>Variable</th><th>Value</th><th>Status</th></tr>";
$dbVars = ['DB_HOST','DB_PORT','DB_USER','DB_NAME','DB_PASS','APP_ENV'];
$allSet = true;
foreach ($dbVars as $v) {
    $val = getenv($v);
    $display = ($v === 'DB_PASS') ? ($val ? '●●●●●● (set)' : '(not set)') : ($val ?: '(not set)');
    $ok = $val !== false && $val !== '';
    if (!$ok && $v !== 'APP_ENV') $allSet = false;
    $cls = $ok ? 'ok' : 'err';
    $icon = $ok ? '✅' : '❌';
    echo "<tr><td><code>$v</code></td><td>$display</td><td class='$cls'>$icon</td></tr>";
}
echo "</table>";
if (!$allSet) {
    echo "<div class='box'><span class='err'>⚠️ One or more DB variables are missing.</span><br>
    Go to <strong>Vercel → ksr-guru-ji → Settings → Environment Variables</strong> and add them.</div>";
}

// ── 2. MySQLi Connection (what the app actually uses) ─────────
echo "<h2>2. MySQLi Connection Test (App Connection)</h2>";
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: 'ksr_guru';

$mysqli = mysqli_init();
if ($host !== '127.0.0.1' && $host !== 'localhost') {
    $mysqli->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $connected = @$mysqli->real_connect($host, $user, $pass, $name, $port, NULL,
        MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);
} else {
    $connected = @$mysqli->real_connect($host, $user, $pass, $name, $port);
}

if ($connected && !$mysqli->connect_errno) {
    $mysqli->set_charset('utf8mb4');
    $ver = $mysqli->query('SELECT VERSION()')->fetch_row()[0];
    echo "<p class='ok'>✅ MySQLi connected! Server: <strong>$ver</strong></p>";
    $tables = [];
    $res = $mysqli->query("SHOW TABLES");
    while ($row = $res->fetch_row()) $tables[] = $row[0];
    echo "<p>Tables in <code>$name</code>: <strong>" . count($tables) . "</strong></p>";
    if (count($tables) > 0) {
        echo "<details><summary style='cursor:pointer;color:#60a5fa'>Show tables (" . count($tables) . ")</summary><ul>";
        foreach ($tables as $t) echo "<li>$t</li>";
        echo "</ul></details>";
    } else {
        echo "<div class='box'><span class='warn'>⚠️ No tables found.</span> You need to import <code>deploy_database.sql</code> via TiDB Cloud SQL Editor.</div>";
    }
} else {
    $errno = $mysqli->connect_errno;
    $errstr = $mysqli->connect_error ?: 'unknown';
    echo "<p class='err'>❌ MySQLi failed — Error #$errno: " . htmlspecialchars($errstr) . "</p>";
    echo "<p>Host: <code>$host:$port</code> | DB: <code>$name</code> | User: <code>$user</code></p>";
}

// ── 3. PDO Connection Test ────────────────────────────────────
echo "<h2>3. PDO Connection Test</h2>";
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5];
    if ($host !== '127.0.0.1' && $host !== 'localhost') {
        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    $pdo = new PDO($dsn, $user, $pass, $opts);
    $v = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "<p class='ok'>✅ PDO connected! Server: <strong>$v</strong></p>";
} catch (Exception $e) {
    echo "<p class='err'>❌ PDO failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// ── 4. PHP Extensions ────────────────────────────────────────
echo "<h2>4. PHP Extensions</h2><table><tr><th>Extension</th><th>Status</th></tr>";
foreach (['mysqli','pdo_mysql','openssl','json','mbstring','session'] as $ext) {
    $loaded = extension_loaded($ext);
    echo "<tr><td><code>$ext</code></td><td class='" . ($loaded?'ok':'err') . "'>" . ($loaded?'✅ Loaded':'❌ Missing') . "</td></tr>";
}
echo "</table>";

// ── 5. Filesystem ────────────────────────────────────────────
echo "<h2>5. Filesystem</h2><table><tr><th>Path</th><th>Exists</th><th>Writable</th></tr>";
$appRoot = dirname(__DIR__);
foreach ([sys_get_temp_dir(), $appRoot, "$appRoot/logs", "$appRoot/uploads", "$appRoot/includes"] as $p) {
    $ex = file_exists($p) ? "<span class='ok'>✅</span>" : "<span class='err'>❌</span>";
    $wr = is_writable($p) ? "<span class='ok'>✅</span>" : "<span class='warn'>🔒 read-only</span>";
    echo "<tr><td><code>" . htmlspecialchars($p) . "</code></td><td>$ex</td><td>$wr</td></tr>";
}
echo "</table>";

// ── 6. Router Test ───────────────────────────────────────────
echo "<h2>6. Router &amp; Server Info</h2><table>";
foreach (['HTTP_HOST','REQUEST_URI','SCRIPT_NAME','PHP_SELF','SERVER_SOFTWARE','HTTP_X_FORWARDED_PROTO'] as $k) {
    echo "<tr><td><code>$k</code></td><td>" . htmlspecialchars($_SERVER[$k] ?? '(not set)') . "</td></tr>";
}
echo "</table>";

echo "<hr><p style='color:#666;font-size:12px'>⚠️ Delete or disable <code>api/debug.php</code> after debugging is complete.</p></body></html>";
