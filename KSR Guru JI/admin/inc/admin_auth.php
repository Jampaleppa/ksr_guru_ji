<?php
// ============================================================
// admin/inc/admin_auth.php
// ------------------------------------------------------------
// Core Admin Authentication + Authorization + Security Layer
// ------------------------------------------------------------
// Provides:
//   - secure sessions (regeneration, fingerprint, expiry)
//   - password hashing/verification wrappers
//   - CSRF token issue/verify
//   - login + OTP rate limiting
//   - permission resolution (super admin bypass + role matrix)
//   - audit logging
//
// This layer depends ONLY on the admin_* tables created by
// admin/database/admin_rbac_schema.sql.
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!class_exists('AdminAuth')) {

    class AdminAuth
    {
        private PDO $pdo;

        /** @var array<int,string>|null cached permission keys for the session user */
        private static ?array $permissionCache = null;

        /** Session lifetime in seconds (2 hours idle). */
        public const SESSION_TTL = 7200;

        /** Login rate limiting window. */
        public const LOGIN_MAX_ATTEMPTS = 5;
        public const LOGIN_WINDOW_MIN   = 15;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /* ====================================================
         * SESSION BOOTSTRAP
         * ==================================================== */

        /**
         * Harden the session cookie + verify it has not expired.
         * Call once at the top of every admin page.
         */
        public function bootSession(): void
        {
            // Harden cookie flags (only when headers not yet sent).
            if (!headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    session_id(),
                    [
                        'expires'  => 0,
                        'path'     => $params['path'] ?: '/',
                        'domain'   => $params['domain'] ?? '',
                        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]
                );
            }

            // Enforce idle timeout for authenticated admins.
            if (!empty($_SESSION['admin_auth_id'])) {
                $last = $_SESSION['admin_last_activity'] ?? time();
                if (time() - $last > self::SESSION_TTL) {
                    $this->logout();
                    return;
                }
                $_SESSION['admin_last_activity'] = time();
            }
        }

        /* ====================================================
         * CSRF
         * ==================================================== */

        public function csrfToken(): string
        {
            if (empty($_SESSION['admin_csrf_token'])) {
                $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['admin_csrf_token'];
        }

        public function csrfField(): string
        {
            return '<input type="hidden" name="csrf_token" value="'
                . htmlspecialchars($this->csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
        }

        public function verifyCsrf(?string $token): bool
        {
            return is_string($token)
                && !empty($_SESSION['admin_csrf_token'])
                && hash_equals($_SESSION['admin_csrf_token'], $token);
        }

        /**
         * Abort the request when CSRF validation fails.
         */
        public function requireCsrf(?string $token): void
        {
            if (!$this->verifyCsrf($token)) {
                http_response_code(419);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error'   => 'CSRF_FAILED',
                    'message' => 'Security token expired. Please refresh the page.',
                ]);
                exit;
            }
        }

        /* ====================================================
         * PASSWORD
         * ==================================================== */

        public function hashPassword(string $plain): string
        {
            return password_hash($plain, PASSWORD_DEFAULT);
        }

        public function verifyPassword(string $plain, string $hash): bool
        {
            return password_verify($plain, $hash);
        }

        /* ====================================================
         * RATE LIMITING
         * ==================================================== */

        /**
         * Count recent failed attempts for an IP / identifier.
         */
        public function countRecentAttempts(string $ip, ?string $identifier = null): int
        {
            $sql = "SELECT COUNT(*) FROM admin_login_attempts
                    WHERE ip_address = :ip
                      AND successful = 0
                      AND attempted_at > (NOW() - INTERVAL :mins MINUTE)";
            $params = ['ip' => $ip, 'mins' => self::LOGIN_WINDOW_MIN];

            if ($identifier !== null) {
                $sql .= " AND identifier = :ident";
                $params['ident'] = $identifier;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':ip', $params['ip']);
            $stmt->bindValue(':mins', $params['mins'], PDO::PARAM_INT);
            if (isset($params['ident'])) {
                $stmt->bindValue(':ident', $params['ident']);
            }
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        }

        public function isRateLimited(string $ip, ?string $identifier = null): bool
        {
            return $this->countRecentAttempts($ip, $identifier) >= self::LOGIN_MAX_ATTEMPTS;
        }

        public function logAttempt(
            ?string $identifier,
            ?int $adminId,
            string $ip,
            bool $successful,
            string $stage = 'password'
        ): void {
            $stmt = $this->pdo->prepare(
                "INSERT INTO admin_login_attempts
                    (identifier, admin_id, ip_address, user_agent, successful, stage)
                 VALUES (:ident, :aid, :ip, :ua, :ok, :stage)"
            );
            $stmt->execute([
                'ident' => $identifier,
                'aid'   => $adminId,
                'ip'    => $ip,
                'ua'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'ok'    => $successful ? 1 : 0,
                'stage' => $stage,
            ]);
        }

        public function clearAttempts(string $ip): void
        {
            $this->pdo->prepare("DELETE FROM admin_login_attempts WHERE ip_address = ?")
                ->execute([$ip]);
        }

        /* ====================================================
         * IDENTITY LOOKUPS
         * ==================================================== */

        /**
         * Find an admin by username OR email.
         * @return array<string,mixed>|null
         */
        public function findAdmin(string $login): ?array
        {
            $candidates = $this->findAdmins($login);
            return $candidates[0] ?? null;
        }

        /**
         * Every admin matching a username OR email identifier.
         *
         * More than one row is possible because the same identifier
         * (e.g. "KSR GURU JI") is shared by the Super Admin (username)
         * and the Admin (email). The correct one is then resolved by
         * password during authenticate().
         *
         * @return array<int,array<string,mixed>>
         */
        public function findAdmins(string $login): array
        {
            $stmt = $this->pdo->prepare(
                "SELECT au.*, ar.role_key, ar.role_name
                 FROM admin_users au
                 JOIN admin_roles ar ON ar.id = au.role_id
                 WHERE au.username = :login_u OR au.email = :login_e
                 ORDER BY au.is_super_admin DESC, au.id ASC"
            );
            $stmt->execute(['login_u' => $login, 'login_e' => $login]);
            return $stmt->fetchAll() ?: [];
        }

        public function findAdminById(int $id): ?array
        {
            $stmt = $this->pdo->prepare(
                "SELECT au.*, ar.role_key, ar.role_name
                 FROM admin_users au
                 JOIN admin_roles ar ON ar.id = au.role_id
                 WHERE au.id = ?
                 LIMIT 1"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ?: null;
        }

        /* ====================================================
         * LOGIN FLOW (stage 1: credentials)
         * ==================================================== */

        public const STAGE_NONE = 'none';
        public const STAGE_OTP  = 'otp';
        public const STAGE_DONE = 'done';

        /**
         * Validate credentials.
         * Returns:
         *   ['status' => 'otp',  'admin' => array]  super admin -> needs OTP
         *   ['status' => 'done', 'admin' => array]  regular admin -> session created
         *   ['status' => 'error','message' => str]  invalid / blocked
         *
         * @return array<string,mixed>
         */
        public function authenticate(string $login, string $password, string $ip): array
        {
            if ($this->isRateLimited($ip)) {
                return [
                    'status'  => 'error',
                    'message' => 'Too many failed attempts. Please try again in '
                        . self::LOGIN_WINDOW_MIN . ' minutes.',
                ];
            }

            $admin = null;

            // The identifier can match several accounts (the Super Admin
            // by username, the Admin by email). Pick the one whose
            // password actually verifies.
            foreach ($this->findAdmins($login) as $candidate) {
                if ($this->verifyPassword($password, $candidate['password_hash'])) {
                    $admin = $candidate;
                    break;
                }
            }

            if (!$admin) {
                $this->logAttempt($login, null, $ip, false, 'password');
                return ['status' => 'error', 'message' => 'Invalid username/email or password.'];
            }

            // Account state checks.
            if ($admin['status'] !== 'active') {
                $this->logAttempt($login, (int) $admin['id'], $ip, false, 'password');
                return [
                    'status'  => 'error',
                    'message' => 'This admin account is ' . $admin['status'] . '. Contact the Super Admin.',
                ];
            }

            if (!empty($admin['locked_until']) && strtotime($admin['locked_until']) > time()) {
                $this->logAttempt($login, (int) $admin['id'], $ip, false, 'password');
                return [
                    'status'  => 'error',
                    'message' => 'This account is temporarily locked. Try again later.',
                ];
            }

            // Credentials are valid from here on.
            $this->clearAttempts($ip);
            $this->resetFailedLogins((int) $admin['id']);

            // Super admin requires WhatsApp OTP (stage 2).
            if ((int) $admin['is_super_admin'] === 1 || $admin['role_key'] === 'SUPER_ADMIN') {
                return ['status' => 'otp', 'admin' => $admin];
            }

            // Regular admin: establish the session immediately.
            $this->establishSession($admin, $ip, false);

            return ['status' => 'done', 'admin' => $admin];
        }

        private function bumpFailedLogins(int $adminId): void
        {
            $this->pdo->prepare(
                "UPDATE admin_users
                 SET failed_logins = failed_logins + 1,
                     locked_until = IF(failed_logins + 1 >= 10,
                                      DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                                      locked_until)
                 WHERE id = ?"
            )->execute([$adminId]);
        }

        private function resetFailedLogins(int $adminId): void
        {
            $this->pdo->prepare(
                "UPDATE admin_users SET failed_logins = 0, locked_until = NULL WHERE id = ?"
            )->execute([$adminId]);
        }

        /* ====================================================
         * SESSION ESTABLISHMENT
         * ==================================================== */

        /**
         * Create the authenticated admin session.
         *
         * @param array<string,mixed> $admin   admin row (with role_key)
         * @param bool                $otpVerified
         */
        public function establishSession(array $admin, string $ip, bool $otpVerified): void
        {
            // Prevent session fixation (skip silently if output already started,
            // e.g. in CLI test harnesses).
            if (!headers_sent()) {
                session_regenerate_id(true);
            }

            $_SESSION['admin_auth_id']        = (int) $admin['id'];
            $_SESSION['admin_name']           = $admin['full_name'];
            $_SESSION['admin_username']       = $admin['username'];
            $_SESSION['admin_email']          = $admin['email'];
            $_SESSION['user_role']            = $admin['role_key'] === 'SUPER_ADMIN'
                ? 'super_admin' : 'admin';
            $_SESSION['admin_role_key']       = $admin['role_key'];
            $_SESSION['admin_is_super']       = (int) $admin['is_super_admin'] === 1
                || $admin['role_key'] === 'SUPER_ADMIN';
            $_SESSION['admin_otp_verified']   = $otpVerified;
            $_SESSION['admin_last_activity']  = time();
            $_SESSION['admin_fingerprint']    = $this->fingerprint();

            // Mirror legacy session keys for backward compatibility with
            // the existing dashboard scripts.
            $_SESSION['admin_id']   = (int) $admin['id'];
            $_SESSION['user_id']    = (int) $admin['id'];
            $_SESSION['user_name']  = $admin['full_name'];
            $_SESSION['role']       = $_SESSION['user_role'];

            // Rotate CSRF token on every login.
            unset($_SESSION['admin_csrf_token']);
            $this->csrfToken();

            self::$permissionCache = null;

            $this->recordSession($admin, $ip, $otpVerified);

            $this->pdo->prepare(
                "UPDATE admin_users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?"
            )->execute([$ip, (int) $admin['id']]);

            $this->logAttempt(
                $admin['email'] ?? $admin['username'] ?? null,
                (int) $admin['id'],
                $ip,
                true,
                $otpVerified ? 'otp' : 'password'
            );

            $this->audit('login', 'auth', 'admin_users', (int) $admin['id'], [
                'otp_verified' => $otpVerified,
            ]);
        }

        private function recordSession(array $admin, string $ip, bool $otpVerified): void
        {
            $stmt = $this->pdo->prepare(
                "INSERT INTO admin_sessions
                    (admin_id, session_id, ip_address, user_agent, csrf_token, is_otp_verified, expires_at)
                 VALUES (:aid, :sid, :ip, :ua, :csrf, :otp, DATE_ADD(NOW(), INTERVAL :ttl SECOND))
                 ON DUPLICATE KEY UPDATE
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent),
                    csrf_token = VALUES(csrf_token),
                    is_otp_verified = VALUES(is_otp_verified),
                    last_activity = NOW(),
                    expires_at = VALUES(expires_at)"
            );
            $stmt->execute([
                'aid'  => (int) $admin['id'],
                'sid'  => session_id(),
                'ip'   => $ip,
                'ua'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'csrf' => $_SESSION['admin_csrf_token'] ?? null,
                'otp'  => $otpVerified ? 1 : 0,
                'ttl'  => self::SESSION_TTL,
            ]);
        }

        private function fingerprint(): string
        {
            return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|ksr-admin');
        }

        /* ====================================================
         * AUTH STATE
         * ==================================================== */

        public function check(): bool
        {
            if (empty($_SESSION['admin_auth_id'])) {
                return false;
            }
            // Bind session to a coarse client fingerprint.
            if (
                isset($_SESSION['admin_fingerprint']) &&
                $_SESSION['admin_fingerprint'] !== $this->fingerprint()
            ) {
                $this->logout();
                return false;
            }
            return true;
        }

        public function id(): ?int
        {
            return isset($_SESSION['admin_auth_id']) ? (int) $_SESSION['admin_auth_id'] : null;
        }

        public function name(): string
        {
            return $_SESSION['admin_name'] ?? 'Admin';
        }

        public function roleKey(): string
        {
            return $_SESSION['admin_role_key'] ?? 'ADMIN';
        }

        public function isSuperAdmin(): bool
        {
            return !empty($_SESSION['admin_is_super']);
        }

        public function isOtpVerified(): bool
        {
            return !empty($_SESSION['admin_otp_verified']);
        }

        /* ====================================================
         * PERMISSIONS
         * ==================================================== */

        /**
         * Return the full permission-key list for the current admin.
         * Super Admin implicitly holds every permission ('*').
         *
         * @return array<int,string>
         */
        public function permissions(): array
        {
            if ($this->isSuperAdmin()) {
                return ['*'];
            }
            if (self::$permissionCache !== null) {
                return self::$permissionCache;
            }
            $stmt = $this->pdo->prepare(
                "SELECT p.permission_key
                 FROM admin_users au
                 JOIN admin_role_permissions rp ON rp.role_id = au.role_id
                 JOIN admin_permissions p ON p.id = rp.permission_id
                 WHERE au.id = ?"
            );
            $stmt->execute([$this->id()]);
            self::$permissionCache = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            return self::$permissionCache;
        }

        /**
         * Does the current admin hold a permission?
         *
         * @param string $permission e.g. "products.edit"
         */
        public function can(string $permission): bool
        {
            if ($this->isSuperAdmin()) {
                return true;
            }
            $perms = $this->permissions();
            return in_array($permission, $perms, true);
        }

        public function canAny(array $permissions): bool
        {
            if ($this->isSuperAdmin()) {
                return true;
            }
            foreach ($permissions as $p) {
                if ($this->can($p)) {
                    return true;
                }
            }
            return false;
        }

        /* ====================================================
         * AUDIT LOGGING
         * ==================================================== */

        /**
         * Write an audit-log entry.
         *
         * @param mixed $details scalar | array (json-encoded)
         */
        public function audit(
            string $action,
            ?string $module = null,
            ?string $targetTable = null,
            ?int $recordId = null,
            mixed $details = null
        ): void {
            try {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO admin_audit_logs
                        (admin_id, admin_name, action, module, target_table, record_id, details, ip_address, user_agent)
                     VALUES (:aid, :aname, :action, :module, :ttable, :rid, :details, :ip, :ua)"
                );
                $stmt->execute([
                    'aid'     => $this->id(),
                    'aname'   => $this->name(),
                    'action'  => $action,
                    'module'  => $module,
                    'ttable'  => $targetTable,
                    'rid'     => $recordId,
                    'details' => $details === null
                        ? null
                        : (is_array($details)
                            ? json_encode($details, JSON_UNESCAPED_UNICODE)
                            : (string) $details),
                    'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
                    'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]);
            } catch (Throwable $e) {
                // Auditing must never break the request.
                error_log('Admin audit log failure: ' . $e->getMessage());
            }
        }

        /* ====================================================
         * LOGOUT
         * ==================================================== */

        public function logout(): void
        {
            if (!empty($_SESSION['admin_auth_id'])) {
                try {
                    $this->audit('logout', 'auth', 'admin_users', (int) $_SESSION['admin_auth_id']);
                    $this->pdo->prepare("DELETE FROM admin_sessions WHERE session_id = ?")
                        ->execute([session_id()]);
                } catch (Throwable $e) {
                    // ignore
                }
            }

            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'] ?: '/',
                    $params['domain'] ?? '',
                    $params['secure'] ?? false,
                    $params['httponly'] ?? true
                );
            }
            session_destroy();
            self::$permissionCache = null;
        }
    }
}

/* ============================================================
 * GLOBAL HELPERS (thin wrappers around the shared instance)
 * ============================================================ */

if (!function_exists('admin_auth')) {
    /**
     * Returns a shared AdminAuth bound to the default PDO connection.
     */
    function admin_auth(?PDO $pdo = null): AdminAuth
    {
        static $instance = null;
        if ($instance === null) {
            if ($pdo === null) {
                if (!function_exists('getDBConnection')) {
                    require_once __DIR__ . '/../../config.php';
                }
                $pdo = getDBConnection();
            }
            $instance = new AdminAuth($pdo);
        }
        return $instance;
    }
}
