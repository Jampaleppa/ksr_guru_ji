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

    /** Generate a CSRF token and store it in session. */
    public static function generateCSRFToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['ksr_csrf_token'])) {
            $_SESSION['ksr_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['ksr_csrf_token'];
    }

    /** Validate a submitted CSRF token against the session token (constant-time). */
    public static function validateCSRFToken(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
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

    /** Check if the request's HTTP_ORIGIN is from an allowed domain. */
    public function validateOrigin(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin === '') {
            return true; // same-origin requests don't send Origin
        }
        return in_array($origin, self::ALLOWED_ORIGINS, true);
    }

    /** Return CORS headers array (never wildcard *). */
    public function getCORSHeaders(): array
    {
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = in_array($origin, self::ALLOWED_ORIGINS, true) ? $origin : self::ALLOWED_ORIGINS[0];

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

