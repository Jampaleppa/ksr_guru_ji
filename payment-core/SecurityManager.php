<?php
declare(strict_types=1);

namespace KsrPay;

/**
 * SecurityManager — CSRF, rate limiting, CORS, input sanitization.
 */
class SecurityManager
{
    /** Allowed origins — no wildcard ever. */
    private const ALLOWED_ORIGINS = [
        'https://ksrguruji.vercel.app',
        'https://ksrguruji-3rpi.vercel.app',
        'http://localhost',
        'http://localhost:80',
        'http://localhost:8090',
        'http://127.0.0.1',
        'http://127.0.0.1:8090',
    ];

    /** Roles permitted to access KSR PAY administration pages and APIs. */
    private const ADMIN_ROLES = ['admin', 'super_admin'];

    /** Whether the active PHP session belongs to an admin or super_admin. */
    public static function isAdminSession(): bool
    {
        return !empty($_SESSION['user_id'])
            && in_array($_SESSION['role'] ?? '', self::ADMIN_ROLES, true);
    }

    /** Ensure an admin/super_admin session for HTML pages, redirecting otherwise. */
    public static function requireAdminPage(string $redirectTo = '/admin/login.php'): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!self::isAdminSession()) {
            header('Location: ' . $redirectTo);
            exit;
        }
        return (int)$_SESSION['user_id'];
    }

    /** Ensure an admin/super_admin session for JSON APIs, returning 403 otherwise. */
    public static function requireAdminApi(string $errorKey = 'error'): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!self::isAdminSession()) {
            http_response_code(403);
            echo json_encode(['success' => false, $errorKey => 'Admin access required.']);
            exit;
        }
        return (int)$_SESSION['user_id'];
    }

    /** Generate a CSRF token and store it in session. */
    public static function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (empty($_SESSION['ksr_csrf_token'])) {
            $_SESSION['ksr_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['ksr_csrf_token'];
    }

    /** Validate a submitted CSRF token against the session token (constant-time). */
    public static function validateCSRFToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $expected = $_SESSION['ksr_csrf_token'] ?? '';
        return hash_equals($expected, $token);
    }

    /**
     * File-based rate limit: returns true if the request is ALLOWED.
     * Returns false if the limit is exceeded.
     */
    public function rateLimitCheck(string $key, int $maxAttempts = 5, int $windowSeconds = 60): bool
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        $file    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ksr_rl_' . $safeKey . '.json';

        $now     = time();
        $window  = $now - $windowSeconds;
        $entries = [];

        if (file_exists($file)) {
            $data    = json_decode(file_get_contents($file), true);
            $entries = array_filter((array)($data['entries'] ?? []), fn($t) => $t > $window);
        }

        if (count($entries) >= $maxAttempts) {
            return false; // limit exceeded
        }

        $entries[] = $now;
        file_put_contents($file, json_encode(['entries' => array_values($entries)]), LOCK_EX);
        return true;
    }

    /** Strip tags, trim, and truncate input string. */
    public function sanitizeInput(string $input, int $maxLen = 255): string
    {
        return mb_substr(trim(strip_tags($input)), 0, $maxLen);
    }

    /**
     * Effective CORS allowlist, combining defaults with CORS_ALLOWED_ORIGIN env var.
     * Never permits wildcard '*'.
     *
     * @return string[]
     */
    public static function resolveAllowedOrigins(): array
    {
        $origins = self::ALLOWED_ORIGINS;
        $envOrigins = getenv('CORS_ALLOWED_ORIGIN');
        if ($envOrigins !== false && $envOrigins !== '') {
            foreach (explode(',', $envOrigins) as $candidate) {
                $clean = rtrim(trim($candidate), '/');
                if ($clean !== '' && $clean !== '*' && preg_match('#^https?://#i', $clean)) {
                    $origins[] = $clean;
                }
            }
        }
        return array_values(array_unique($origins));
    }

    /** Check if the request's HTTP_ORIGIN is from an allowed domain. */
    public function validateOrigin(?string $origin = null): bool
    {
        $origin = $origin ?? ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === '') {
            return true; // same-origin requests don't send Origin
        }
        return in_array($origin, self::resolveAllowedOrigins(), true);
    }

    /** Return CORS headers array (never wildcard *). */
    public function getCORSHeaders(): array
    {
        $allowedList = self::resolveAllowedOrigins();
        $origin      = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed     = in_array($origin, $allowedList, true) ? $origin : $allowedList[0];

        return [
            'Access-Control-Allow-Origin'      => $allowed,
            'Access-Control-Allow-Methods'     => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type, X-CSRF-Token, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
            'Vary'                             => 'Origin',
        ];
    }

    /** Send CORS headers (call before any output). */
    public function sendCORSHeaders(): void
    {
        foreach ($this->getCORSHeaders() as $name => $value) {
            header("{$name}: {$value}");
        }
    }
}

