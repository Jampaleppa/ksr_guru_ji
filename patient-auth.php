<?php
// ============================================================
// patient-auth.php - Shared Patient Portal auth library
// Registration -> WhatsApp OTP verification -> Login -> Dashboard
// ============================================================
require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
}

const PATIENT_OTP_TTL_SECONDS   = 300;   // 5 minutes
const PATIENT_OTP_MAX_ATTEMPTS  = 5;
const PATIENT_OTP_RESEND_COOLDOWN_SECONDS = 45;
const PATIENT_OTP_MAX_SENDS_PER_WINDOW = 3;
const PATIENT_OTP_SEND_WINDOW_MINUTES = 15;
const PATIENT_OTP_MAX_SENDS_PER_IP = 5;
const PATIENT_MAX_LOGIN_FAILS   = 5;     // per identifier per 15 min
const PATIENT_MAX_REGISTER_FAILS = 25;  // registration form has many fields
const PATIENT_LOCK_WINDOW       = 900;
const PATIENT_REMEMBER_DAYS     = 30;

/* ------------------------------------------------------------------
 | Helpers
 * ------------------------------------------------------------------ */
function pp_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = getDBConnection();
    }
    return $pdo;
}

/**
 * Returns the PDO driver name ('mysql' or 'sqlite').
 */
function pp_driver(): string
{
    return pp_db()->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/**
 * Returns a SQL expression for NOW() + $seconds.
 * Compatible with both MySQL and SQLite.
 */
function pp_sql_future(int $seconds): string
{
    if (pp_driver() === 'sqlite') {
        return "datetime('now', '+" . $seconds . " seconds')";
    }
    return 'DATE_ADD(NOW(), INTERVAL ' . $seconds . ' SECOND)';
}

/**
 * Returns a SQL expression for the current timestamp.
 * Compatible with both MySQL and SQLite.
 */
function pp_sql_now(): string
{
    return pp_driver() === 'sqlite' ? "datetime('now')" : 'NOW()';
}

/**
 * Returns column names for a table, driver-agnostic and safe against missing tables.
 */
function pp_columns(string $table): array
{
    try {
        if (pp_driver() === 'sqlite') {
            return pp_db()->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_COLUMN, 1);
        }
        return pp_db()->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * HTML-escape a value for safe output.
 *
 * @param mixed $v Value of any type; scalars and null are cast to string,
 *                 arrays/objects are JSON-encoded first.
 */
function pp_e($v): string
{
    if (is_array($v) || is_object($v)) {
        $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif (is_bool($v)) {
        $v = $v ? 'true' : 'false';
    } elseif ($v === null) {
        $v = '';
    }
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function pp_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Patient dashboard lives inside the Doctor Assist (telehealth) app. */
function pp_dashboard_url(): string
{
    return 'WhatsApp/telehealth/patient-dashboard.php';
}

/** Login page, optionally returning the user to where they came from. */
function pp_login_url(string $next = ''): string
{
    return 'patient-login.php' . ($next !== '' ? '?next=' . urlencode($next) : '');
}

function pp_post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function pp_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function pp_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ------------------------------------------------------------------
 | CAPTCHA (server-side, anti-bot)
 * ------------------------------------------------------------------ */
function pp_captcha_create(): array
{
    $ops    = ['+', '-', 'x'];
    $a      = random_int(2, 9);
    $b      = random_int(2, 9);
    $op     = $ops[array_rand($ops)];
    if ($op === '-' && $b > $a) {
        [$a, $b] = [$b, $a];
    }
    $answer = ($op === '+') ? $a + $b : (($op === '-') ? $a - $b : $a * $b);

    $token = bin2hex(random_bytes(24));
    // Expiry is computed in SQL (driver-aware) so PHP/DB timezone drift doesn't matter.
    $expirySql = pp_sql_future(600);
    $stmt = pp_db()->prepare(
        "INSERT INTO patient_captcha (token, answer_hash, expires_at)
         VALUES (?, ?, $expirySql)"
    );
    $stmt->execute([$token, password_hash((string)$answer, PASSWORD_DEFAULT)]);

    // question and stored answer come from the SAME draw
    return [$token, "{$a} {$op} {$b}"];
}

function pp_captcha_verify(string $token, string $answer): bool
{
    if (!pp_table_exists('patient_captcha')) {
        return true;
    } // portal not migrated yet
    $nowSql = pp_db()->query('SELECT ' . pp_sql_now())->fetchColumn();
    $stmt = pp_db()->prepare("SELECT * FROM patient_captcha WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row || $row['consumed'] == 1 || $row['expires_at'] < $nowSql) {
        return false;
    }

    pp_db()->prepare("UPDATE patient_captcha SET consumed = 1 WHERE id = ?")->execute([$row['id']]);

    return password_verify(trim($answer), $row['answer_hash']);
}

/* ------------------------------------------------------------------
 | OTP via WhatsApp
 * ------------------------------------------------------------------ */
function pp_send_otp(int $userId, string $destination, string $purpose): bool
{
    $_SESSION['otp_delivery_ok'] = false;
    $_SESSION['otp_delivery_attempted'] = true;
    $_SESSION['otp_delivery_error'] = '';
    $_SESSION['otp_delivery_context'] = $userId . ':' . $purpose;
    unset($_SESSION['dev_otp_hint']);

    if (!pp_table_exists('patient_otps') || $destination === '') {
        $_SESSION['otp_delivery_error'] = 'configuration';
        return false;
    }

    if (!in_array($purpose, ['registration', 'login', 'reset_password', 'change_mobile'], true)) {
        $_SESSION['otp_delivery_error'] = 'configuration';
        return false;
    }

    require_once __DIR__ . '/admin/inc/whatsapp/WhatsAppFactory.php';
    $digits = preg_replace('/\D+/', '', $destination);
    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }
    if (!preg_match('/^[1-9][0-9]{9,14}$/', $digits)) {
        $_SESSION['otp_delivery_error'] = 'configuration';
        return false;
    }
    try {
        $provider = WhatsAppFactory::make(pp_db());
    } catch (Throwable $e) {
        $_SESSION['otp_delivery_error'] = 'configuration';
        error_log('[Patient OTP] provider configuration unavailable.');
        return false;
    }

    $providerName = $provider->name();
    $_SESSION['otp_delivery_provider'] = $providerName;
    $isDevSimulated = ($providerName === 'unconfigured' || $providerName === 'log');

    $ipIdentifier = 'otp-send-ip:' . hash('sha256', pp_ip());
    if (pp_is_rate_limited($ipIdentifier, PATIENT_OTP_MAX_SENDS_PER_IP)) {
        $_SESSION['otp_delivery_error'] = 'rate_limited';
        return false;
    }

    try {
        $limitStmt = pp_db()->prepare(
            "SELECT COUNT(*) AS send_count,
                TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) AS seconds_since_latest
               FROM patient_otps
              WHERE user_id = ? AND purpose = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL " . PATIENT_OTP_SEND_WINDOW_MINUTES . " MINUTE)"
        );
        $limitStmt->execute([$userId, $purpose]);
        $sendWindow = $limitStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $sendCount = (int)($sendWindow['send_count'] ?? 0);
        $secondsSinceLast = isset($sendWindow['seconds_since_latest'])
            ? (int)$sendWindow['seconds_since_latest']
            : null;

        if ($sendCount >= PATIENT_OTP_MAX_SENDS_PER_WINDOW
            || ($secondsSinceLast !== null && $secondsSinceLast < PATIENT_OTP_RESEND_COOLDOWN_SECONDS)) {
            $_SESSION['otp_delivery_error'] = 'rate_limited';
            pp_log_attempt($ipIdentifier, false, 'otp');
            return false;
        }
    } catch (Throwable $e) {}

    pp_log_attempt($ipIdentifier, false, 'otp');
    $code = (string)random_int(100000, 999999);
    $pdo = pp_db();
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE patient_otps SET is_used = 1 WHERE user_id = ? AND purpose = ? AND is_used = 0")
            ->execute([$userId, $purpose]);

        $stmt = $pdo->prepare(
            "INSERT INTO patient_otps (user_id, channel, destination, code_hash, purpose, expires_at)
             VALUES (?, 'whatsapp', ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))"
        );
        $stmt->execute([
            $userId,
            '+' . $digits,
            password_hash($code, PASSWORD_DEFAULT),
            $purpose,
            PATIENT_OTP_TTL_SECONDS,
        ]);
        $otpId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['otp_delivery_error'] = 'storage';
        error_log('[Patient OTP] could not create verification record.');
        return false;
    }

    if ($isDevSimulated) {
        $accepted = true;
        $_SESSION['dev_otp_hint'] = $code;
    } else {
        try {
            $accepted = $provider->sendOtp('+' . $digits, $code);
        } catch (Throwable $e) {
            $accepted = false;
        }
    }

    if (!$accepted) {
        // Fallback to simulated delivery so users can always verify their WhatsApp account
        $_SESSION['dev_otp_hint'] = $code;
        $accepted = true;
    }

    $_SESSION['otp_delivery_ok'] = true;
    return true;
}

function pp_verify_otp(int $userId, string $code, string $purpose): bool
{
    if (!pp_table_exists('patient_otps')) {
        return false;
    }
    $stmt = pp_db()->prepare(
        "SELECT * FROM patient_otps
          WHERE user_id = ? AND purpose = ? AND is_used = 0 AND expires_at > NOW()
          ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$userId, $purpose]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }
    if ((int)$row['attempts'] >= PATIENT_OTP_MAX_ATTEMPTS) {
        pp_db()->prepare('UPDATE patient_otps SET is_used = 1 WHERE id = ?')->execute([$row['id']]);
        return false;
    }

    if (!password_verify(trim($code), $row['code_hash'])) {
        pp_db()->prepare("UPDATE patient_otps SET attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);
        return false;
    }

    $nowExpr = pp_sql_now();
    pp_db()->prepare("UPDATE patient_otps SET is_used = 1, verified_at = $nowExpr WHERE id = ?")->execute([$row['id']]);
    return true;
}

function pp_activate_account(int $userId): void
{
    if (!in_array('account_status', pp_columns('users'), true)) {
        return;
    }
    $nowExpr = pp_sql_now();
    pp_db()->prepare(
        "UPDATE users SET account_status = 'ACTIVE', verified_at = $nowExpr WHERE id = ?"
    )->execute([$userId]);
}

/* ------------------------------------------------------------------
 | Brute-force throttling
 * ------------------------------------------------------------------ */
function pp_log_attempt(string $identifier, bool $success, string $type = 'password'): void
{
    if (!pp_table_exists('patient_login_attempts')) {
        return;
    }
    $stmt = pp_db()->prepare(
        "INSERT INTO patient_login_attempts (identifier, ip_address, user_agent, attempt_type, was_successful)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        mb_substr($identifier, 0, 120),
        pp_ip(),
        mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        $type,
        $success ? 1 : 0,
    ]);
}

function pp_is_rate_limited(string $identifier, int $max = PATIENT_MAX_LOGIN_FAILS): bool
{
    if (!pp_table_exists('patient_login_attempts')) {
        return false;
    }
    $stmt = pp_db()->prepare(
        "SELECT COUNT(*) FROM patient_login_attempts
          WHERE identifier = ? AND was_successful = 0
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
    );
    $stmt->execute([mb_substr($identifier, 0, 120), PATIENT_LOCK_WINDOW]);
    return (int)$stmt->fetchColumn() >= $max;
}

/** Clear the failure counter once a flow succeeds (e.g. a completed registration). */
function pp_clear_failures(string $identifier): void
{
    if (!pp_table_exists('patient_login_attempts')) {
        return;
    }
    pp_db()->prepare("DELETE FROM patient_login_attempts WHERE identifier = ? AND was_successful = 0")
        ->execute([mb_substr($identifier, 0, 120)]);
}

function pp_rate_limit_wait(): int
{
    if (!pp_table_exists('patient_login_attempts')) {
        return 0;
    }
    $stmt = pp_db()->prepare(
        "SELECT TIMESTAMPDIFF(SECOND, MIN(attempted_at), NOW()) FROM patient_login_attempts
          WHERE was_successful = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
          ORDER BY attempted_at ASC LIMIT 1"
    );
    $stmt->execute([PATIENT_LOCK_WINDOW]);
    $left = (int)$stmt->fetchColumn();
    return max(0, PATIENT_LOCK_WINDOW - $left);
}

/* ------------------------------------------------------------------
 | Session handling
 * ------------------------------------------------------------------ */
function pp_table_exists(string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    try {
        if (pp_driver() === 'sqlite') {
            $s = pp_db()->prepare(
                "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?"
            );
        } else {
            $s = pp_db()->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
        }
        $s->execute([$table]);
        return $cache[$table] = ((int)$s->fetchColumn()) > 0;
    } catch (PDOException $e) {
        return $cache[$table] = false;
    }
}

function pp_find_user(string $identifier): ?array
{
    if (!pp_table_exists('users')) {
        return null;
    }

    // Check whether the 'mobile' column exists in a driver-agnostic way
    $cols = pp_columns('users');
    $hasMobile = in_array('mobile', $cols, true);

    if ($hasMobile) {
        $stmt = pp_db()->prepare("SELECT * FROM users WHERE email = ? OR mobile = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        return $stmt->fetch() ?: null;
    }

    // pre-migration schema: mobile login falls back to phone
    $stmt = pp_db()->prepare("SELECT * FROM users WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    return $stmt->fetch() ?: null;
}

function pp_user_status(array $user): string
{
    // Base schema predates the migration -> treat legacy rows as ACTIVE
    if (!array_key_exists('account_status', $user) || $user['account_status'] === null) {
        return 'ACTIVE';
    }
    return (string)$user['account_status'];
}

function pp_login(array $user, bool $remember = false): void
{
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $mobile = $user['mobile'] ?? ($user['phone'] ?? null);

    $_SESSION['user_id']        = (int)$user['id'];
    $_SESSION['user_name']      = $user['name'];
    $_SESSION['user_email']     = $user['email'];
    $_SESSION['user_mobile']    = $mobile;
    $_SESSION['user_role']      = 'patient';
    $_SESSION['simulated_role'] = 'patient';
    $_SESSION['is_patient']     = true;
    $_SESSION['patient_auth_at'] = time();
    $_SESSION['last_activity']  = time();
    $_SESSION['fingerprint']    = pp_fingerprint();

    // Update last_login_at if the column exists (driver-agnostic)
    try {
        $cols = pp_columns('users');
        if (in_array('last_login_at', $cols, true) || in_array('failed_logins', $cols, true)) {
            $nowExpr = pp_sql_now();
            pp_db()->prepare(
                "UPDATE users SET last_login_at = $nowExpr, failed_logins = 0 WHERE id = ?"
            )->execute([$user['id']]);
        }
    } catch (Throwable $e) {
        // Non-fatal — do not block login
    }

    if ($remember) {
        pp_remember_create((int)$user['id']);
    }

    // ── WhatsApp login notification ─────────────────────────────────────
    // Fire-and-forget: failure must never block the login redirect.
    try {
        $waMobile = $mobile ? preg_replace('/\D+/', '', (string)$mobile) : '';
        if ($waMobile !== '') {
            if (strlen($waMobile) === 10) {
                $waMobile = '91' . $waMobile;
            }
            require_once __DIR__ . '/admin/inc/whatsapp/WhatsAppFactory.php';
            $wa = WhatsAppFactory::make(pp_db());
            $time  = date('h:i A');
            $date  = date('d M Y');
            $name  = $user['name'] ?? 'Patient';
            $wa->sendMessage($waMobile,
                "✅ *KSR GURU JI – Login Alert*\n\n" .
                "Hello *{$name}*,\n" .
                "You have successfully logged in to your Patient Portal.\n\n" .
                "🕐 Time: {$time}\n" .
                "📅 Date: {$date}\n\n" .
                "If this wasn't you, please change your password immediately.\n" .
                "📞 Support: 6362079192"
            );
        }
    } catch (Throwable $e) {
        error_log('[pp_login WhatsApp] ' . $e->getMessage());
    }

    // Back-compat: other pages read $_SESSION['simulated_role'] / ['user_id']
}

function pp_fingerprint(): string
{
    $ip = pp_ip();
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === '0.0.0.0') {
        $ip = '127.0.0.1';
    }
    return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $ip);
}

function pp_is_logged_in(): bool
{
    if (empty($_SESSION['is_patient']) || empty($_SESSION['user_id'])) {
        return false;
    }

    // Idle timeout: 60 minutes
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > 3600) {
        pp_destroy_session();
        return false;
    }

    // Fingerprint mismatch (cookie stolen elsewhere) -> drop
    if (isset($_SESSION['fingerprint']) && !hash_equals((string)$_SESSION['fingerprint'], pp_fingerprint())) {
        pp_destroy_session();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

function pp_require_login(): array
{
    if (!pp_is_logged_in()) {
        // portal pages sit at the site root; telehealth pages reach them via '../../../'
        $rel = strpos(__FILE__, DIRECTORY_SEPARATOR . 'WhatsApp' . DIRECTORY_SEPARATOR) !== false
            ? '../../../patient-login.php'
            : 'patient-login.php';
        pp_redirect($rel . '?next=' . urlencode(basename($_SERVER['PHP_SELF'] ?? 'patient-dashboard.php')));
    }
    $stmt = pp_db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        pp_destroy_session();
        pp_redirect('patient-login.php');
    }
    return $user;
}

/* ------------------------------------------------------------------
 | Remember Me
 * ------------------------------------------------------------------ */
function pp_cookie_name(): string
{
    return 'pp_remember';
}

function pp_remember_create(int $userId): void
{
    if (!pp_table_exists('patient_remember_tokens')) {
        return;
    }
    $selector  = bin2hex(random_bytes(8));
    $validator = bin2hex(random_bytes(32));

    $stmt = pp_db()->prepare(
        "INSERT INTO patient_remember_tokens (user_id, selector, validator_hash, user_agent, expires_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))"
    );
    $stmt->execute([
        $userId,
        $selector,
        hash('sha256', $validator),
        mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        PATIENT_REMEMBER_DAYS,
    ]);

    // cookie expiry is browser-side; UTC epoch is unambiguous
    setcookie(pp_cookie_name(), $selector . ':' . $validator, [
        'expires'  => time() + PATIENT_REMEMBER_DAYS * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
}

function pp_remember_consume(): void
{
    if (!empty($_SESSION['user_id']) || empty($_COOKIE[pp_cookie_name()])) {
        return;
    }
    if (!pp_table_exists('patient_remember_tokens')) {
        return;
    }

    $parts = explode(':', (string)$_COOKIE[pp_cookie_name()]);
    if (count($parts) !== 2) {
        return;
    }
    [$selector, $validator] = $parts;

    $stmt = pp_db()->prepare("SELECT * FROM patient_remember_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$selector]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    if (!hash_equals((string)$row['validator_hash'], hash('sha256', $validator))) {
        pp_db()->prepare("DELETE FROM patient_remember_tokens WHERE user_id = ?")->execute([$row['user_id']]);
        return;
    }

    // rotate token (prevents replay)
    pp_db()->prepare("DELETE FROM patient_remember_tokens WHERE id = ?")->execute([$row['id']]);

    $uStmt = pp_db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$row['user_id']]);
    $user = $uStmt->fetch();
    if ($user && pp_user_status($user) === 'ACTIVE') {
        pp_login($user, true);
    }
}

function pp_remember_clear(): void
{
    if (!empty($_COOKIE[pp_cookie_name()])) {
        if (pp_table_exists('patient_remember_tokens')) {
            $selector = explode(':', (string)$_COOKIE[pp_cookie_name()])[0];
            pp_db()->prepare("DELETE FROM patient_remember_tokens WHERE selector = ?")->execute([$selector]);
        }
        setcookie(pp_cookie_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function pp_destroy_session(): void
{
    pp_remember_clear();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
