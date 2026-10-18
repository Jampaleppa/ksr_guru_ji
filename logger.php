<?php
// ============================================================
// includes/logger.php - Centralized Logging and Error Handling
// ============================================================

if (!defined('KSR_LOGGER_LOADED')) {
    define('KSR_LOGGER_LOADED', true);

    // --- Helper function to get client IP ---
    if (!function_exists('getClientIp')) {
        function getClientIp(): string
        {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                return $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            } else {
                return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            }
        }
    }

    // --- Helper function to get class of session info ---
    if (!function_exists('getSessionLogInfo')) {
        function getSessionLogInfo(): string
        {
            if (session_status() === PHP_SESSION_ACTIVE) {
                if (!empty($_SESSION['admin_name'])) {
                    return 'Admin: ' . $_SESSION['admin_name'];
                } elseif (!empty($_SESSION['user_id'])) {
                    return 'User ID: ' . $_SESSION['user_id'] . ' (' . ($_SESSION['user_name'] ?? 'Unknown') . ')';
                }
            }
            return 'Guest';
        }
    }

    // --- Helper function to get writable logs directory ---
    if (!function_exists('getLogsDir')) {
        function getLogsDir(): string
        {
            $localLogs = __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
            if (is_dir($localLogs) && is_writable($localLogs)) {
                return $localLogs;
            }
            if (is_writable(__DIR__)) {
                return __DIR__ . DIRECTORY_SEPARATOR;
            }
            $tmpLogs = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ksr_logs' . DIRECTORY_SEPARATOR;
            if (!is_dir($tmpLogs)) {
                @mkdir($tmpLogs, 0755, true);
            }
            return $tmpLogs;
        }
    }

    // --- Core function to write application activity logs ---
    if (!function_exists('writeAppLog')) {
        function writeAppLog(string $message, string $level = 'INFO'): void
        {
            $ip = getClientIp();
            $logsDir = getLogsDir();
            $logMessage = sprintf(
                "[%s] [%s] [%s] %s\n",
                date('Y-m-d H:i:s'),
                $ip,
                strtoupper($level),
                $message
            );
            @file_put_contents($logsDir . 'app.log', $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    // --- Page Access hit logging function ---
    if (!function_exists('logPageAccess')) {
        function logPageAccess(): void
        {
            $ip = getClientIp();
            $uri = $_SERVER['REQUEST_URI'] ?? '-';
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $referer = $_SERVER['HTTP_REFERER'] ?? '-';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '-';
            $status = http_response_code();
            $sessionInfo = getSessionLogInfo();
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_GET['ajax']) ? 'AJAX' : 'PAGE';

            $logsDir = getLogsDir();

            $logLine = sprintf(
                "[%s] [%s] [%s] [%s] %s %s - Status: %d - Referrer: %s - UA: %s\n",
                date('Y-m-d H:i:s'),
                $ip,
                $sessionInfo,
                $isAjax,
                $method,
                $uri,
                $status,
                $referer,
                $userAgent
            );

            @file_put_contents($logsDir . 'access.log', $logLine, FILE_APPEND | LOCK_EX);
        }
    }

    // --- Central PHP Error Handler ---
    if (!function_exists('app_error_handler')) {
        function app_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool
        {
            if (!(error_reporting() & $errno)) {
                return false;
            }

            $levels = [
                E_ERROR             => 'FATAL ERROR',
                E_WARNING           => 'WARNING',
                E_PARSE             => 'PARSE ERROR',
                E_NOTICE            => 'NOTICE',
                E_CORE_ERROR        => 'CORE ERROR',
                E_CORE_WARNING      => 'CORE WARNING',
                E_COMPILE_ERROR     => 'COMPILE ERROR',
                E_COMPILE_WARNING   => 'COMPILE WARNING',
                E_USER_ERROR        => 'USER ERROR',
                E_USER_WARNING      => 'USER WARNING',
                E_USER_NOTICE       => 'USER NOTICE',
                E_STRICT            => 'STRICT NOTICE',
                E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
                E_DEPRECATED        => 'DEPRECATED',
                E_USER_DEPRECATED   => 'USER DEPRECATED'
            ];

            $levelName = $levels[$errno] ?? 'UNKNOWN ERROR';
            $ip = getClientIp();
            $url = $_SERVER['REQUEST_URI'] ?? '-';

            $logMessage = sprintf(
                "[%s] [%s] [%s] [%s] %s in %s on line %d\n",
                date('Y-m-d H:i:s'),
                $ip,
                $levelName,
                $url,
                $errstr,
                $errfile,
                $errline
            );

            $logsDir = getLogsDir();
            @file_put_contents($logsDir . 'error.log', $logMessage, FILE_APPEND | LOCK_EX);

            return false; // let the default error handler continue executing
        }
    }

    // --- Central PHP Exception Handler ---
    if (!function_exists('app_exception_handler')) {
        function app_exception_handler(Throwable $exception): void
        {
            $ip = getClientIp();
            $url = $_SERVER['REQUEST_URI'] ?? '-';

            $logMessage = sprintf(
                "[%s] [%s] [UNCAUGHT EXCEPTION] [%s] %s: %s in %s on line %d\nStack trace:\n%s\n",
                date('Y-m-d H:i:s'),
                $ip,
                $url,
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );

            $logsDir = getLogsDir();
            @file_put_contents($logsDir . 'error.log', $logMessage, FILE_APPEND | LOCK_EX);

            http_response_code(500);
            echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Uncaught Exception</title>";
            echo "<style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f172a;color:#e2e8f0;padding:40px;line-height:1.6;}";
            echo ".card{max-width:800px;margin:0 auto;background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.5);}";
            echo "h1{color:#f87171;margin-top:0;font-size:22px;}p{color:#94a3b8;}code{background:#0f172a;color:#38bdf8;padding:2px 8px;border-radius:4px;font-size:14px;}";
            echo "pre{background:#0f172a;color:#cbd5e1;padding:16px;border-radius:8px;overflow:auto;font-size:13px;border:1px solid #334155;}";
            echo "</style></head><body><div class='card'>";
            echo "<h1>⚠️ Uncaught Exception</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
            echo "<p><strong>File:</strong> <code>" . htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8') . "</code> line " . $exception->getLine() . "</p>";
            echo "<details><summary style='cursor:pointer;color:#38bdf8;margin:12px 0;'>Show Stack Trace</summary>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre></details>";
            echo "</div></body></html>";
            exit(0);
        }
    }

    // --- Register custom error and exception handling ---
    set_error_handler('app_error_handler');
    set_exception_handler('app_exception_handler');

    // --- Register access logging to write at the end of the request execution ---
    register_shutdown_function('logPageAccess');
}
