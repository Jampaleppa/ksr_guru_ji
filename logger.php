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

    // --- Core function to write application activity logs ---
    if (!function_exists('writeAppLog')) {
        function writeAppLog(string $message, string $level = 'INFO'): void
        {
            $ip = getClientIp();
            $logsDir = __DIR__ . DIRECTORY_SEPARATOR;
            if (!is_dir($logsDir)) {
                @mkdir($logsDir, 0755, true);
            }
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

            $logsDir = __DIR__ . DIRECTORY_SEPARATOR;
            if (!is_dir($logsDir)) {
                @mkdir($logsDir, 0755, true);
            }

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

            $logsDir = __DIR__ . DIRECTORY_SEPARATOR;
            if (!is_dir($logsDir)) {
                @mkdir($logsDir, 0755, true);
            }
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

            $logsDir = __DIR__ . DIRECTORY_SEPARATOR;
            if (!is_dir($logsDir)) {
                @mkdir($logsDir, 0755, true);
            }
            @file_put_contents($logsDir . 'error.log', $logMessage, FILE_APPEND | LOCK_EX);

            if (ini_get('display_errors')) {
                echo "<h1>Uncaught Exception</h1>";
                echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
                echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8') . " index line " . $exception->getLine() . "</p>";
                echo "<pre>" . htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
            } else {
                http_response_code(500);
                echo "<h1>500 Internal Server Error</h1>";
                echo "<p>A critical error has occurred. The administrator has been notified. Please try again later.</p>";
            }
            exit(1);
        }
    }

    // --- Register custom error and exception handling ---
    set_error_handler('app_error_handler');
    set_exception_handler('app_exception_handler');

    // --- Register access logging to write at the end of the request execution ---
    register_shutdown_function('logPageAccess');
}
