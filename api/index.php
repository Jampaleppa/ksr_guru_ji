<?php
// ============================================================
// api/index.php - Vercel Serverless PHP Router / Front-Controller
// ============================================================

// Enable detailed error reporting immediately so any runtime issues can be seen
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Catch any fatal shutdown errors (E_ERROR, E_PARSE, compile errors)
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo "<!DOCTYPE html><html><body style='font-family:sans-serif;padding:30px;background:#0f172a;color:#f87171;'>";
        echo "<h2>PHP Fatal Error</h2>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($err['message']) . "</p>";
        echo "<p><strong>File:</strong> <code>" . htmlspecialchars($err['file']) . "</code> line " . $err['line'] . "</p>";
        echo "</body></html>";
    }
});

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
$normTarget = $realTarget ? str_replace('\\', '/', $realTarget) : '';
$normRoot = $realRoot ? str_replace('\\', '/', $realRoot) : '';

if (!$realTarget || strpos($normTarget, $normRoot) !== 0) {
    http_response_code(404);
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>404 Not Found</title></head>";
    echo "<body style='font-family:sans-serif;padding:40px;background:#0f172a;color:#f1f5f9;'>";
    echo "<h2>404 Not Found</h2><p>Page: <code>" . htmlspecialchars($cleanPath) . "</code></p>";
    echo "<p><a href='/' style='color:#38bdf8;'>&larr; Return to Home</a></p>";
    echo "</body></html>";
    exit(0);
}

// Setup execution context to mimic standard web server
$_SERVER['SCRIPT_FILENAME'] = $realTarget;
$_SERVER['SCRIPT_NAME'] = '/' . ltrim(str_replace($realRoot, '', $realTarget), '/\\');
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

// Switch to the target script's directory so relative includes function correctly
chdir(dirname($realTarget));

// Execute target script with top-level error catching
try {
    require $realTarget;
} catch (Throwable $e) {
    http_response_code(500);
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Application Error</title>";
    echo "<style>";
    echo "body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f172a;color:#e2e8f0;padding:40px;line-height:1.6;}";
    echo ".card{max-width:800px;margin:0 auto;background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.5);}";
    echo "h1{color:#f87171;margin-top:0;font-size:22px;}p{color:#94a3b8;}code{background:#0f172a;color:#38bdf8;padding:2px 8px;border-radius:4px;font-size:14px;}";
    echo "pre{background:#0f172a;color:#cbd5e1;padding:16px;border-radius:8px;overflow:auto;font-size:13px;border:1px solid #334155;}";
    echo ".links{margin-top:24px;border-top:1px solid #334155;padding-top:16px;}";
    echo ".links a{color:#38bdf8;text-decoration:none;font-weight:500;}.links a:hover{text-decoration:underline;}";
    echo "</style></head><body><div class='card'>";
    echo "<h1>⚠️ Application Runtime Error</h1>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Location:</strong> <code>" . htmlspecialchars($e->getFile()) . "</code> on line <strong>" . $e->getLine() . "</strong></p>";
    echo "<details><summary style='cursor:pointer;color:#38bdf8;margin:12px 0;'>View Stack Trace</summary>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>";
    echo "<div class='links'><a href='/api/debug.php?key=ksrdebug2024'>👉 Open Vercel Diagnostic Page (/api/debug.php)</a></div>";
    echo "</div></body></html>";
    exit(0);
}
