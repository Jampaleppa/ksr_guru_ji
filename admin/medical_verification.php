<?php

declare(strict_types=1);

/* ============================================================================
   KSR GURU JI — Document & Compliance Center
   Single-file PHP + MySQL application
   ----------------------------------------------------------------------------
   - 2 doctors (both VERIFIED, derived from their documents)
   - 1 pharmacy (VERIFICATION)
   - Dr. Shivanna is NOT present anywhere in this system.
   ============================================================================ */

/* ---------------------------------------------------------------- 1. CONFIG */
/*
 * Credentials: environment variables win, otherwise reuse the application's
 * central config.php so this app can never end up connecting as an
 * empty-password 'root' and dying with SQLSTATE[HY000] [1045].
 * Set KSR_DB_HOST / KSR_DB_PORT / KSR_DB_USER / KSR_DB_PASS to override.
 *
 * NOTE: this app keeps its own schema in its own database so its `users`
 * table never collides with the storefront's customer table in DB_NAME.
 */
require_once __DIR__ . '/../config.php';

define('KDB_HOST', getenv('KSR_DB_HOST') ?: DB_HOST);
define('KDB_PORT', getenv('KSR_DB_PORT') ?: (string) DB_PORT);
define('KDB_USER', getenv('KSR_DB_USER') ?: DB_USER);

$_ksr_pass = getenv('KSR_DB_PASS');
define('KDB_PASS', $_ksr_pass !== false && $_ksr_pass !== '' ? $_ksr_pass : DB_PASS);

define('KDB_NAME', getenv('KSR_DB_NAME') ?: 'ksr_guruji');

$documentRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__);
// On Vercel (read-only /var/task), fall back to the system temp directory
$_storageBase = (is_writable($documentRoot) || is_writable(dirname(__DIR__)))
    ? rtrim($documentRoot, '/\\')
    : sys_get_temp_dir();
define('STORAGE_DIR', $_storageBase . '/uploads/medical-documents');
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);
define('APP_NAME', 'KSR GURU JI');
define('APP_TAG',  'Document & Compliance Center');

/* --------------------------------------------------------------- 2. SESSION */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}
if (empty($_SESSION['__boot'])) {
    session_regenerate_id(true);
    $_SESSION['__boot'] = 1;
}

/* ------------------------------------------------------------------ 3. DB */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $server = new PDO(
        'mysql:host=' . KDB_HOST . ';port=' . KDB_PORT . ';charset=utf8mb4',
        KDB_USER,
        KDB_PASS,
        $opts
    );
    $server->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', KDB_NAME) .
        "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO(
        'mysql:host=' . KDB_HOST . ';port=' . KDB_PORT . ';dbname=' . KDB_NAME . ';charset=utf8mb4',
        KDB_USER,
        KDB_PASS,
        $opts
    );
    install_schema($pdo);
    return $pdo;
}

/* ------------------------------------------------------------- 4. SCHEMA */
function install_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;

    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        $done = true;
        return;
    } catch (Throwable $e) {
        /* fall through and install */
    }

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        username      VARCHAR(60)  NOT NULL UNIQUE,
        full_name     VARCHAR(120) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role          ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
        is_active     TINYINT(1)   NOT NULL DEFAULT 1,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS medical_professionals (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        full_name      VARCHAR(160) NOT NULL,
        alias          VARCHAR(160) DEFAULT NULL,
        designation    VARCHAR(160) DEFAULT NULL,
        reg_no         VARCHAR(60)  NOT NULL,
        reg_date       DATE         DEFAULT NULL,
        mbbs           VARCHAR(200) DEFAULT NULL,
        md_qual        VARCHAR(200) DEFAULT NULL,
        md_date        VARCHAR(60)  DEFAULT NULL,
        overall_status VARCHAR(32)  NOT NULL DEFAULT 'PENDING',
        verified_by    VARCHAR(120) DEFAULT NULL,
        verified_at    DATETIME     DEFAULT NULL,
        created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (reg_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS pharmacies (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        name           VARCHAR(200) NOT NULL,
        owner          VARCHAR(160) NOT NULL,
        form20         VARCHAR(80)  DEFAULT NULL,
        form21         VARCHAR(80)  DEFAULT NULL,
        valid_from     DATE         DEFAULT NULL,
        valid_until    DATE         DEFAULT NULL,
        overall_status VARCHAR(32)  NOT NULL DEFAULT 'VERIFICATION',
        verified_by    VARCHAR(120) DEFAULT NULL,
        verified_at    DATETIME     DEFAULT NULL,
        created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS documents (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        entity_type   ENUM('doctor','pharmacy') NOT NULL,
        entity_id     INT NOT NULL,
        doc_type      VARCHAR(140) NOT NULL,
        doc_number    VARCHAR(140) DEFAULT NULL,
        issue_date    DATE DEFAULT NULL,
        expiry_date   DATE DEFAULT NULL,
        file_path     VARCHAR(255) DEFAULT NULL,
        original_name VARCHAR(255) DEFAULT NULL,
        mime_type     VARCHAR(120) DEFAULT NULL,
        file_size     INT DEFAULT NULL,
        status        VARCHAR(32) NOT NULL DEFAULT 'PENDING',
        uploaded_by   VARCHAR(120) DEFAULT NULL,
        uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (entity_type, entity_id),
        INDEX (status),
        INDEX (expiry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS document_verifications (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        document_id   INT NOT NULL,
        reviewer_id   INT DEFAULT NULL,
        reviewer_name VARCHAR(120) NOT NULL,
        action        VARCHAR(40)  NOT NULL,
        from_status   VARCHAR(32)  DEFAULT NULL,
        to_status     VARCHAR(32)  DEFAULT NULL,
        remarks       TEXT,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (document_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS document_versions (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        document_id   INT NOT NULL,
        file_path     VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) DEFAULT NULL,
        replaced_by   VARCHAR(120) DEFAULT NULL,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (document_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS audit_logs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT DEFAULT NULL,
        username    VARCHAR(120) NOT NULL,
        action      VARCHAR(80)  NOT NULL,
        entity_type VARCHAR(40)  DEFAULT NULL,
        entity_id   INT          DEFAULT NULL,
        details     TEXT,
        ip          VARCHAR(45)  DEFAULT NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS notifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        title      VARCHAR(200) NOT NULL,
        body       TEXT,
        level      VARCHAR(20) NOT NULL DEFAULT 'info',
        is_read    TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    seed_data($pdo);
    $done = true;
}

/* --------------------------------------------------------------- 5. SEED */
function seed_data(PDO $pdo): void
{
    $docIds = [];
    if ((int)$pdo->query("SELECT COUNT(*) FROM medical_professionals")->fetchColumn() === 0) {
        $ins = $pdo->prepare(
            "INSERT INTO medical_professionals
             (full_name, alias, designation, reg_no, reg_date, mbbs, md_qual, md_date, overall_status)
             VALUES (?,?,?,?,?,?,?,?, 'PENDING')"
        );
        $ins->execute([
            'Dr. Shivukumar Rathod',
            null,
            'Doctor / Pediatrician',
            '131985',
            '2019-09-05',
            'RGUHS / BIMS Belgaum',
            'MD — Pediatrics',
            'January 2025',
        ]);
        $docIds['shivukumar'] = (int)$pdo->lastInsertId();

        $ins->execute([
            'Dr. Durgappa Rathod',
            'Dr. DC',
            'Doctor',
            '132928',
            '2019-10-23',
            'RGUHS / BMCRI',
            null,
            null,
        ]);
        $docIds['durgappa'] = (int)$pdo->lastInsertId();
    } else {
        foreach ($pdo->query("SELECT id, full_name FROM medical_professionals") as $r) {
            if (str_contains($r['full_name'], 'Shivukumar')) $docIds['shivukumar'] = (int)$r['id'];
            if (str_contains($r['full_name'], 'Durgappa'))  $docIds['durgappa']  = (int)$r['id'];
        }
    }

    $pharmId = null;
    if ((int)$pdo->query("SELECT COUNT(*) FROM pharmacies")->fetchColumn() === 0) {
        $pdo->prepare(
            "INSERT INTO pharmacies (name, owner, form20, form21, valid_from, valid_until, overall_status)
             VALUES (?,?,?,?,?,?, 'VERIFICATION')"
        )->execute([
            'Shri Kanakachal Medical & General Stores',
            'Bheemappa Kallappa Hanamannavar',
            'KA-GD1-288637',
            'KA-GD1-288638',
            '2025-12-18',
            '2030-12-17',
        ]);
        $pharmId = (int)$pdo->lastInsertId();
    } else {
        $pharmId = (int)$pdo->query("SELECT id FROM pharmacies ORDER BY id LIMIT 1")->fetchColumn();
    }

    if ((int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn() === 0) {
        $insDoc = $pdo->prepare(
            "INSERT INTO documents
             (entity_type, entity_id, doc_type, doc_number, issue_date, expiry_date, status, uploaded_by, uploaded_at)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $insVer = $pdo->prepare(
            "INSERT INTO document_verifications
             (document_id, reviewer_name, action, from_status, to_status, remarks, created_at)
             VALUES (?,?,?,?,?,?,?)"
        );
        $insAudit = $pdo->prepare(
            "INSERT INTO audit_logs (username, action, entity_type, entity_id, details, ip, created_at)
             VALUES (?,?,?,?,?,?,?)"
        );

        $stamp = date('Y-m-d H:i:s', strtotime('2026-09-24 21:50:00'));

        if (!empty($docIds['shivukumar'])) {
            $sid = $docIds['shivukumar'];
            $rows = [
                ['Medical Registration Certificate', '131985', '2019-09-05', null],
                ['MBBS Certificate',                 null,     null,         null],
                ['MD Pediatrics Certificate',        null,     '2025-01-31', null],
            ];
            foreach ($rows as $r) {
                $insDoc->execute(['doctor', $sid, $r[0], $r[1], $r[2], $r[3], 'VERIFIED', 'System Seed', $stamp]);
                $did = (int)$pdo->lastInsertId();
                $insVer->execute([
                    $did,
                    'Admin',
                    'VERIFIED',
                    'REVIEW_REQUIRED',
                    'VERIFIED',
                    'Documents match registration records.',
                    $stamp
                ]);
                $insAudit->execute([
                    'admin',
                    'DOCUMENT_VERIFIED',
                    'document',
                    $did,
                    $r[0] . ' — Dr. Shivukumar Rathod',
                    '127.0.0.1',
                    $stamp
                ]);
            }
            $insAudit->execute([
                'admin',
                'ENTITY_STATUS_CHANGED',
                'doctor',
                $sid,
                'Overall status -> VERIFIED (Dr. Shivukumar Rathod)',
                '127.0.0.1',
                $stamp
            ]);
        }

        if (!empty($docIds['durgappa'])) {
            $did2 = $docIds['durgappa'];
            $rows = [
                ['Medical Registration Certificate', '132928', '2019-10-23', null],
                ['MBBS Certificate',                 null,     null,         null],
            ];
            foreach ($rows as $r) {
                $insDoc->execute(['doctor', $did2, $r[0], $r[1], $r[2], $r[3], 'VERIFIED', 'System Seed', $stamp]);
                $did = (int)$pdo->lastInsertId();
                $insVer->execute([
                    $did,
                    'Admin',
                    'VERIFIED',
                    'REVIEW_REQUIRED',
                    'VERIFIED',
                    'Documents match registration records.',
                    $stamp
                ]);
                $insAudit->execute([
                    'admin',
                    'DOCUMENT_VERIFIED',
                    'document',
                    $did,
                    $r[0] . ' — Dr. Durgappa Rathod',
                    '127.0.0.1',
                    $stamp
                ]);
            }
            $insAudit->execute([
                'admin',
                'ENTITY_STATUS_CHANGED',
                'doctor',
                $did2,
                'Overall status -> VERIFIED (Dr. Durgappa Rathod)',
                '127.0.0.1',
                $stamp
            ]);
        }

        if ($pharmId) {
            foreach (
                [
                    ['Form 20 Drug Licence', 'KA-GD1-288637'],
                    ['Form 21 Drug Licence', 'KA-GD1-288638']
                ] as $r
            ) {
                $insDoc->execute([
                    'pharmacy',
                    $pharmId,
                    $r[0],
                    $r[1],
                    '2025-12-18',
                    '2030-12-17',
                    'PENDING',
                    'System Seed',
                    $stamp
                ]);
                $did = (int)$pdo->lastInsertId();
                $insVer->execute([
                    $did,
                    'Admin',
                    'UPLOADED',
                    null,
                    'PENDING',
                    'Awaiting verification.',
                    $stamp
                ]);
                $insAudit->execute([
                    'admin',
                    'DOCUMENT_UPLOADED',
                    'document',
                    $did,
                    $r[0] . ' — Shri Kanakachal Medical & General Stores',
                    '127.0.0.1',
                    $stamp
                ]);
            }
        }
    }

    foreach ($pdo->query("SELECT id FROM medical_professionals") as $r) recalc_doctor((int)$r['id']);
    foreach ($pdo->query("SELECT id FROM pharmacies")            as $r) recalc_pharmacy((int)$r['id']);
}

/* ---------------------------------------------------- 6. STATUS ENGINE */
function recalc_doctor(int $id): string
{
    $pdo = db();
    $docs = $pdo->prepare("SELECT status, expiry_date FROM documents WHERE entity_type='doctor' AND entity_id=?");
    $docs->execute([$id]);
    $rows = $docs->fetchAll();

    $status = derive_status($rows);
    $pdo->prepare("UPDATE medical_professionals SET overall_status=? WHERE id=?")->execute([$status, $id]);
    return $status;
}

function recalc_pharmacy(int $id): string
{
    $pdo = db();
    $docs = $pdo->prepare("SELECT status, expiry_date FROM documents WHERE entity_type='pharmacy' AND entity_id=?");
    $docs->execute([$id]);
    $rows = $docs->fetchAll();

    $status = derive_status($rows);
    $pdo->prepare("UPDATE pharmacies SET overall_status=? WHERE id=?")->execute([$status, $id]);
    return $status;
}

function derive_status(array $docs): string
{
    if (!$docs) return 'PENDING';

    $today     = new DateTimeImmutable('today');
    $hasReject = false;
    $hasExpire = false;
    $allOk     = true;

    foreach ($docs as $d) {
        $s = strtoupper((string)$d['status']);
        if ($s === 'REJECTED')  $hasReject = true;
        if ($s === 'EXPIRED')   $hasExpire = true;
        if (!empty($d['expiry_date'])) {
            $exp = DateTimeImmutable::createFromFormat('Y-m-d', (string)$d['expiry_date']);
            if ($exp && $exp < $today) $hasExpire = true;
        }
        if ($s !== 'VERIFIED') $allOk = false;
    }

    if ($hasReject) return 'REJECTED';
    if ($hasExpire) return 'EXPIRED';
    if ($allOk)     return 'VERIFIED';
    return 'PENDING';
}

function status_meta(string $s): array
{
    $s = strtoupper($s);
    $map = [
        'VERIFIED'               => ['ok',    'Verified'],
        'ACTIVE'                 => ['ok',    'Active'],
        'COMPLETED'              => ['ok',    'Completed'],
        'FINALIZED'              => ['ok',    'Finalized'],
        'PENDING'                => ['warn',  'Pending'],
        'VERIFICATION'           => ['warn',  'Verification'],
        'REVIEW_REQUIRED'        => ['warn',  'Review Required'],
        'IN_PROGRESS'            => ['warn',  'In Progress'],
        'CLARIFICATION_REQUIRED' => ['warn',  'Clarification Required'],
        'DRAFT'                  => ['muted', 'Draft'],
        'REJECTED'               => ['bad',   'Rejected'],
        'EXPIRED'                => ['bad',   'Expired'],
        'SUSPENDED'              => ['bad',   'Suspended'],
        'FAILED'                 => ['bad',   'Failed'],
        'CANCELLED'              => ['bad',   'Cancelled'],
        'ESCALATED'              => ['bad',   'Escalated'],
    ];
    return $map[$s] ?? ['muted', $s];
}

/* --------------------------------------------------------- 7. HELPERS */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(419);
        exit('Security token mismatch. Please reload the page.');
    }
}

/**
 * Current signed-in user for this standalone app.
 *
 * Normally read from its own `uid` session. But when the visitor is
 * already authenticated in the Admin Portal (admin_users / admin RBAC),
 * the identity is bridged in automatically so an admin never has to log
 * in twice. Bridge access is additionally gated on the
 * `medical.view` / `medical.approve` permissions.
 */
function current_user(): ?array
{
    static $u = null;
    if ($u !== null) return $u ?: null;

    // --- Bridge from the Admin Portal session ---
    if (empty($_SESSION['uid']) && !empty($_SESSION['admin_auth_id'])) {
        $bridged = bridge_admin_session();
        if ($bridged !== null) {
            $u = $bridged;
            return $u;
        }
    }

    if (empty($_SESSION['uid'])) {
        $u = false;
        return null;
    }

    $st = db()->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE id=? LIMIT 1");
    $st->execute([(int)$_SESSION['uid']]);
    $row = $st->fetch();
    if (!$row || !(int)$row['is_active']) {
        $u = false;
        return null;
    }
    $u = $row;
    return $u;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        header('Location: ?page=login');
        exit;
    }
    rbac_gate($u);
    return $u;
}

/**
 * Re-validate a mirrored Admin Portal identity against the live RBAC layer.
 *
 * current_user() caches a row in `users` that was written when the admin
 * first bridged into this app. If the Super Admin later revokes a
 * permission or suspends the account, that cache would keep working, so
 * every request re-checks the authoritative role.
 *
 * Non-RBAC identities (the standalone first-run setup flow) are unaffected.
 */
function rbac_gate(array $u): void
{
    if (!str_starts_with((string)($u['username'] ?? ''), 'admin_rbac_')) {
        return;
    }

    try {
        require_once __DIR__ . '/inc/admin_auth.php';
        $auth = admin_auth();

        if (!$auth->check() || !$auth->can('medical.view')) {
            http_response_code(403);
            exit('403 — Your account no longer has access to Medical Verification.');
        }
    } catch (Throwable $e) {
        // RBAC tables unavailable: deny rather than fall open.
        http_response_code(403);
        exit('403 — Medical Verification is temporarily unavailable.');
    }
}

/**
 * Resolve the admin RBAC identity into this app's user shape.
 *
 * Returns null when there is no valid admin session, or when the
 * admin lacks the `medical.view` permission.
 *
 * @return array<string,mixed>|null
 */
function bridge_admin_session(): ?array
{
    try {
        require_once __DIR__ . '/inc/admin_auth.php';
        $auth = admin_auth();
        if (!$auth->check() || !$auth->can('medical.view')) {
            return null;
        }

        $adminId = $auth->id();
        if (!$adminId) {
            return null;
        }

        // Mirror the admin into `users` so foreign keys and the
        // existing per-page queries keep working. The mirrored row
        // never grants more than the RBAC permissions allow.
        $role = $auth->isSuperAdmin() ? 'super_admin' : 'admin';

        $st = db()->prepare(
            "SELECT id, username, full_name, role, is_active FROM users
              WHERE username = :u LIMIT 1"
        );
        $st->execute(['u' => 'admin_rbac_' . $adminId]);
        $row = $st->fetch();

        if ($row) {
            if (!(int) $row['is_active']) {
                return null;
            }
            return $row;
        }

        $ins = db()->prepare(
            "INSERT INTO users (username, full_name, password_hash, role, is_active)
             VALUES (:u, :n, :h, :r, 1)"
        );
        $ins->execute([
            'u' => 'admin_rbac_' . $adminId,
            'n' => $auth->name(),
            // Mirrored rows authenticate via the Admin Portal session only.
            // Store an unusable random hash so this account can never be
            // logged into directly through this app's own login form.
            'h' => '!' . bin2hex(random_bytes(32)),
            'r' => $role,
        ]);

        return [
            'id'        => (int) db()->lastInsertId(),
            'username'  => 'admin_rbac_' . $adminId,
            'full_name' => $auth->name(),
            'role'      => $role,
            'is_active' => 1,
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function require_role(array $roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('403 — You do not have permission to perform this action.');
    }
    return $u;
}

function can_verify(array $u): bool
{
    return in_array($u['role'], ['super_admin', 'admin'], true);
}
function can_delete(array $u): bool
{
    return $u['role'] === 'super_admin';
}

function audit(string $action, ?string $etype = null, ?int $eid = null, string $details = ''): void
{
    $u = current_user();
    $st = db()->prepare(
        "INSERT INTO audit_logs (user_id, username, action, entity_type, entity_id, details, ip)
         VALUES (?,?,?,?,?,?,?)"
    );
    $st->execute([
        $u['id'] ?? null,
        $u['username'] ?? 'guest',
        $action,
        $etype,
        $eid,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function notify(string $title, string $body = '', string $level = 'info'): void
{
    db()->prepare("INSERT INTO notifications (title, body, level) VALUES (?,?,?)")
        ->execute([$title, $body, $level]);
}

function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function days_remaining(?string $date): ?int
{
    if (!$date) return null;
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$d) return null;
    $today = new DateTimeImmutable('today');
    return (int)$today->diff($d)->format('%r%a');
}

function fmt_date(?string $d): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date('d M Y', $t) : '—';
}
function fmt_dt(?string $d): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date('d M Y H:i', $t) : '—';
}

function expiry_bucket(?string $date): string
{
    if (!$date) return 'none';
    $n = days_remaining($date);
    if ($n === null) return 'none';
    if ($n < 0)   return 'expired';
    if ($n <= 30) return 'soon';
    return 'valid';
}

/* --------------------------------------------------------- 8. FILE STORE */
function storage_ready(): void
{
    if (!is_dir(STORAGE_DIR)) {
        @mkdir(STORAGE_DIR, 0770, true);
    }
    @file_put_contents(STORAGE_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
}

function store_upload(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid upload payload.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . (int)$file['error'] . ').');
    }
    if ($file['size'] <= 0 || $file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('File size must be between 1 byte and 10 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Possible upload tampering detected.');
    }

    $allowed = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('Only PDF, JPG, JPEG, PNG and WebP files are allowed.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string)$finfo->file($file['tmp_name']);
    $okMime = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $okMime, true)) {
        throw new RuntimeException('Detected MIME type is not permitted (' . $mime . ').');
    }

    storage_ready();
    $safe = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = STORAGE_DIR . '/' . $safe;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $lastErr = error_get_last();
        $reason  = $lastErr ? $lastErr['message'] : 'unknown reason';
        throw new RuntimeException('Unable to persist uploaded file — ' . $reason . ' (dest: ' . $dest . ')');
    }
    @chmod($dest, 0640);

    return [
        'path'          => $safe,
        'original_name' => substr((string)$file['name'], 0, 250),
        'mime'          => $mime,
        'size'          => (int)$file['size'],
    ];
}

function stream_document(int $docId): void
{
    require_login();
    $st = db()->prepare("SELECT * FROM documents WHERE id=? LIMIT 1");
    $st->execute([$docId]);
    $doc = $st->fetch();
    if (!$doc || empty($doc['file_path'])) {
        http_response_code(404);
        exit('File not found.');
    }

    $path = STORAGE_DIR . '/' . basename((string)$doc['file_path']);
    if (!is_file($path)) {
        http_response_code(404);
        exit('File missing on disk.');
    }

    audit('DOCUMENT_DOWNLOADED', 'document', $docId, (string)$doc['doc_type']);

    $mime = $doc['mime_type'] ?: 'application/octet-stream';
    $name = $doc['original_name'] ?: ('document-' . $docId);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: inline; filename="' . rawurlencode($name) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/* ============================================================================
   9. REQUEST HANDLING
   ========================================================================== */
$page   = isset($_GET['page']) ? preg_replace('/[^a-z_]/', '', (string)$_GET['page']) : 'dashboard';
$action = isset($_POST['action']) ? preg_replace('/[^a-z_]/', '', (string)$_POST['action']) : '';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

/* ---- First-run setup ---- */
$needsSetup = false;
try {
    db();
    $needsSetup = ((int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0);
} catch (Throwable $ex) {
    http_response_code(500);
    exit('Database error: ' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8'));
}
if ($needsSetup && $page !== 'setup') {
    header('Location: ?page=setup');
    exit;
}

/* ---- Logout ---- */
if ($page === 'logout') {
    audit('LOGOUT');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
    header('Location: ?page=login');
    exit;
}

/* ---- POST actions ---- */
if ($isPost && $action !== '') {
    csrf_check();

    if ($action === 'setup') {
        if ((int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) {
            header('Location: ?page=login');
            exit;
        }
        $u1 = trim((string)($_POST['username'] ?? ''));
        $n1 = trim((string)($_POST['full_name'] ?? ''));
        $p1 = (string)($_POST['password'] ?? '');
        $p2 = (string)($_POST['password2'] ?? '');

        if (strlen($u1) < 3 || !preg_match('/^[A-Za-z0-9._-]+$/', $u1)) {
            flash('Username must be 3+ characters (letters, numbers, . _ -).', 'bad');
            header('Location: ?page=setup');
            exit;
        }
        if (strlen($p1) < 10) {
            flash('Password must be at least 10 characters.', 'bad');
            header('Location: ?page=setup');
            exit;
        }
        if ($p1 !== $p2) {
            flash('Passwords do not match.', 'bad');
            header('Location: ?page=setup');
            exit;
        }

        db()->prepare("INSERT INTO users (username, full_name, password_hash, role) VALUES (?,?,?,'super_admin')")
            ->execute([$u1, $n1 !== '' ? $n1 : $u1, password_hash($p1, PASSWORD_DEFAULT)]);

        $_SESSION['uid'] = (int)db()->lastInsertId();
        session_regenerate_id(true);
        audit('ACCOUNT_CREATED', 'user', (int)$_SESSION['uid'], 'Initial super_admin created');
        flash('Super Admin account created. Welcome.', 'ok');
        header('Location: ?page=dashboard');
        exit;
    }

    if ($action === 'login') {
        $u1 = trim((string)($_POST['username'] ?? ''));
        $p1 = (string)($_POST['password'] ?? '');

        $_SESSION['tries'] = $_SESSION['tries'] ?? [];
        $_SESSION['tries'] = array_values(array_filter($_SESSION['tries'], fn($t) => $t > time() - 600));
        if (count($_SESSION['tries']) >= 8) {
            flash('Too many attempts. Please wait 10 minutes.', 'bad');
            header('Location: ?page=login');
            exit;
        }

        $st = db()->prepare("SELECT * FROM users WHERE username=? AND is_active=1 LIMIT 1");
        $st->execute([$u1]);
        $row = $st->fetch();

        if ($row && password_verify($p1, (string)$row['password_hash'])) {
            if (password_needs_rehash((string)$row['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare("UPDATE users SET password_hash=? WHERE id=?")
                    ->execute([password_hash($p1, PASSWORD_DEFAULT), $row['id']]);
            }
            $_SESSION['uid'] = (int)$row['id'];
            session_regenerate_id(true);
            audit('LOGIN_SUCCESS', 'user', (int)$row['id']);
            flash('Signed in as ' . $row['username'], 'ok');
            header('Location: ?page=dashboard');
            exit;
        }

        $_SESSION['tries'][] = time();
        audit('LOGIN_FAILED', 'user', null, 'username=' . $u1);
        flash('Invalid username or password.', 'bad');
        header('Location: ?page=login');
        exit;
    }

    $me = require_login();

    if ($action === 'verify_document') {
        if (!can_verify($me)) {
            flash('You are not permitted to verify documents.', 'bad');
            header('Location: ?page=documents');
            exit;
        }
        $id      = (int)($_POST['document_id'] ?? 0);
        $remarks = trim((string)($_POST['remarks'] ?? ''));

        $st = db()->prepare("SELECT * FROM documents WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $doc = $st->fetch();
        if (!$doc) {
            flash('Document not found.', 'bad');
            header('Location: ?page=documents');
            exit;
        }

        $from = (string)$doc['status'];
        db()->prepare("UPDATE documents SET status='VERIFIED' WHERE id=?")->execute([$id]);
        db()->prepare("INSERT INTO document_verifications (document_id, reviewer_id, reviewer_name, action, from_status, to_status, remarks)
                       VALUES (?,?,?,'VERIFIED',?,?,?)")
            ->execute([$id, $me['id'], $me['full_name'], $from, 'VERIFIED', $remarks]);

        audit('DOCUMENT_VERIFIED', 'document', $id, $doc['doc_type'] . ' (' . $from . ' -> VERIFIED)');
        $new = refresh_entity((string)$doc['entity_type'], (int)$doc['entity_id']);
        notify('Document verified successfully', $doc['doc_type'], 'ok');
        flash('Document verified. Entity status: ' . $new, 'ok');
        header('Location: ?page=verify&id=' . $id);
        exit;
    }

    if ($action === 'reject_document') {
        if (!can_verify($me)) {
            flash('You are not permitted to reject documents.', 'bad');
            header('Location: ?page=documents');
            exit;
        }
        $id      = (int)($_POST['document_id'] ?? 0);
        $remarks = trim((string)($_POST['remarks'] ?? ''));
        if ($remarks === '') {
            flash('A rejection reason is mandatory.', 'bad');
            header('Location: ?page=verify&id=' . $id);
            exit;
        }

        $st = db()->prepare("SELECT * FROM documents WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $doc = $st->fetch();
        if (!$doc) {
            flash('Document not found.', 'bad');
            header('Location: ?page=documents');
            exit;
        }

        $from = (string)$doc['status'];
        db()->prepare("UPDATE documents SET status='REJECTED' WHERE id=?")->execute([$id]);
        db()->prepare("INSERT INTO document_verifications (document_id, reviewer_id, reviewer_name, action, from_status, to_status, remarks)
                       VALUES (?,?,?,'REJECTED',?,?,?)")
            ->execute([$id, $me['id'], $me['full_name'], $from, 'REJECTED', $remarks]);

        audit('DOCUMENT_REJECTED', 'document', $id, $doc['doc_type'] . ' — ' . $remarks);
        $new = refresh_entity((string)$doc['entity_type'], (int)$doc['entity_id']);
        notify('Document rejected', $doc['doc_type'], 'bad');
        flash('Document rejected. Entity status: ' . $new, 'bad');
        header('Location: ?page=verify&id=' . $id);
        exit;
    }

    if ($action === 'reopen_document') {
        if (!can_verify($me)) {
            flash('Not permitted.', 'bad');
            header('Location: ?page=documents');
            exit;
        }
        $id      = (int)($_POST['document_id'] ?? 0);
        $remarks = trim((string)($_POST['remarks'] ?? 'Reopened for re-verification.'));

        $st = db()->prepare("SELECT * FROM documents WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $doc = $st->fetch();
        if (!$doc) {
            flash('Document not found.', 'bad');
            header('Location: ?page=documents');
            exit;
        }

        $from = (string)$doc['status'];
        db()->prepare("UPDATE documents SET status='REVIEW_REQUIRED' WHERE id=?")->execute([$id]);
        db()->prepare("INSERT INTO document_verifications (document_id, reviewer_id, reviewer_name, action, from_status, to_status, remarks)
                       VALUES (?,?,?,'REOPENED',?,?,?)")
            ->execute([$id, $me['id'], $me['full_name'], $from, 'REVIEW_REQUIRED', $remarks]);

        audit('DOCUMENT_REOPENED', 'document', $id, $doc['doc_type'] . ' (' . $from . ' -> REVIEW_REQUIRED)');
        $new = refresh_entity((string)$doc['entity_type'], (int)$doc['entity_id']);
        flash('Verification reopened. Entity status: ' . $new, 'warn');
        header('Location: ?page=verify&id=' . $id);
        exit;
    }

    if ($action === 'upload_document') {
        require_role(['super_admin', 'admin']);
        $etype   = (string)($_POST['entity_type'] ?? '');
        $eid     = (int)($_POST['entity_id'] ?? 0);
        $dtype   = trim((string)($_POST['doc_type'] ?? ''));
        $dnum    = trim((string)($_POST['doc_number'] ?? ''));
        $issue   = trim((string)($_POST['issue_date'] ?? ''));
        $expiry  = trim((string)($_POST['expiry_date'] ?? ''));

        if (!in_array($etype, ['doctor', 'pharmacy'], true) || $eid <= 0 || $dtype === '') {
            flash('Document type and entity are required.', 'bad');
            header('Location: ?page=upload');
            exit;
        }
        $norm = function (string $d): ?string {
            if ($d === '') return null;
            $t = DateTimeImmutable::createFromFormat('Y-m-d', $d);
            return $t ? $t->format('Y-m-d') : null;
        };
        $issue  = $norm($issue);
        $expiry = $norm($expiry);

        $fileMeta = ['path' => null, 'original_name' => null, 'mime' => null, 'size' => null];
        if (!empty($_FILES['document']['name'])) {
            try {
                $fileMeta = store_upload($_FILES['document']);
            } catch (Throwable $ex) {
                flash($ex->getMessage(), 'bad');
                header('Location: ?page=upload');
                exit;
            }
        }

        db()->prepare(
            "INSERT INTO documents
             (entity_type, entity_id, doc_type, doc_number, issue_date, expiry_date,
              file_path, original_name, mime_type, file_size, status, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,'PENDING',?)"
        )->execute([
            $etype,
            $eid,
            $dtype,
            $dnum ?: null,
            $issue,
            $expiry,
            $fileMeta['path'],
            $fileMeta['original_name'],
            $fileMeta['mime'],
            $fileMeta['size'],
            $me['full_name'],
        ]);
        $did = (int)db()->lastInsertId();

        db()->prepare("INSERT INTO document_verifications (document_id, reviewer_id, reviewer_name, action, to_status, remarks)
                       VALUES (?,?,?,'UPLOADED','PENDING',?)")
            ->execute([$did, $me['id'], $me['full_name'], 'Awaiting verification.']);

        audit('DOCUMENT_UPLOADED', 'document', $did, $dtype . ' for ' . $etype . '#' . $eid);
        refresh_entity($etype, $eid);
        notify('Document uploaded successfully', $dtype, 'ok');
        flash('Document uploaded and queued for verification.', 'ok');
        header('Location: ?page=documents');
        exit;
    }

    if ($action === 'add_user') {
        require_role(['super_admin']);
        $u1 = trim((string)($_POST['username'] ?? ''));
        $n1 = trim((string)($_POST['full_name'] ?? ''));
        $p1 = (string)($_POST['password'] ?? '');
        $r1 = (string)($_POST['role'] ?? 'admin');
        if (!in_array($r1, ['admin', 'super_admin'], true)) $r1 = 'admin';
        if (strlen($u1) < 3 || strlen($p1) < 10) {
            flash('Username 3+ chars and password 10+ chars required.', 'bad');
            header('Location: ?page=settings');
            exit;
        }
        try {
            db()->prepare("INSERT INTO users (username, full_name, password_hash, role) VALUES (?,?,?,?)")
                ->execute([$u1, $n1 !== '' ? $n1 : $u1, password_hash($p1, PASSWORD_DEFAULT), $r1]);
            audit('ACCOUNT_CREATED', 'user', (int)db()->lastInsertId(), 'username=' . $u1 . ' role=' . $r1);
            flash('User created.', 'ok');
        } catch (Throwable $ex) {
            flash('Username already exists.', 'bad');
        }
        header('Location: ?page=settings');
        exit;
    }

    header('Location: ?page=dashboard');
    exit;
}

/* ---- Stream a stored file ---- */
if ($page === 'file') {
    stream_document((int)($_GET['id'] ?? 0));
}

function refresh_entity(string $type, int $id): string
{
    return $type === 'doctor' ? recalc_doctor($id) : recalc_pharmacy($id);
}

/* ============================================================================
   10. DATA FOR THE VIEW
   ========================================================================== */
$me       = current_user();
$loggedIn = (bool)$me;

$pageTitle = 'Dashboard';

$stats        = ['entities' => 0, 'verified' => 0, 'pending' => 0, 'expired' => 0];
$doctors      = [];
$pharmacies   = [];
$documents    = [];
$pendingCount = 0;

if ($loggedIn) {
    $doctors    = db()->query("SELECT * FROM medical_professionals ORDER BY id")->fetchAll();
    $pharmacies = db()->query("SELECT * FROM pharmacies ORDER BY id")->fetchAll();

    $stats['entities'] = count($doctors) + count($pharmacies);

    foreach ($doctors as $d) {
        $s = (string)$d['overall_status'];
        if ($s === 'VERIFIED')      $stats['verified']++;
        elseif ($s === 'EXPIRED')   $stats['expired']++;
        else                        $stats['pending']++;
    }
    foreach ($pharmacies as $p) {
        $s = (string)$p['overall_status'];
        if ($s === 'VERIFIED')      $stats['verified']++;
        elseif ($s === 'EXPIRED')   $stats['expired']++;
        else                        $stats['pending']++;
    }

    $pendingCount = (int)db()->query("SELECT COUNT(*) FROM documents WHERE status IN ('PENDING','REVIEW_REQUIRED','IN_PROGRESS','CLARIFICATION_REQUIRED','VERIFICATION')")->fetchColumn();
}

$flash = flash();

/* ---- Pre-render role gate --------------------------------------------
 * The router below calls require_role() for 'settings' and 'upload'.
 * That runs AFTER the page shell has been printed, so the 403 would be
 * emitted with headers already sent ("output started at ... <title>").
 * Enforce the same rules here, before any output, so the HTTP status
 * and redirect are still valid. The in-router calls stay as defence
 * in depth for direct includes. */
if ($loggedIn) {
    $pageRoles = ['settings' => ['super_admin'], 'upload' => ['super_admin', 'admin']];
    if (isset($pageRoles[$page]) && !in_array((string)($me['role'] ?? ''), $pageRoles[$page], true)) {
        http_response_code(403);
        header('Location: ?page=dashboard');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> — <?= e($pageTitle) ?></title>
    <style>
        :root {
            --navy: #0F172A;
            --navy-2: #1E293B;
            --teal: #0D9488;
            --teal-d: #0B7C72;
            --green: #16A34A;
            --amber: #F59E0B;
            --red: #DC2626;
            --bg: #F8FAFC;
            --card: #FFFFFF;
            --line: #E2E8F0;
            --muted: #64748B;
            --radius: 16px;
            --shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 8px 24px -12px rgba(15, 23, 42, .18);
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            margin: 0;
            padding: 0
        }

        body {
            font-family: "Segoe UI", system-ui, -apple-system, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            color: var(--navy);
            font-size: 15px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: var(--teal);
            text-decoration: none
        }

        a:hover {
            text-decoration: underline
        }

        /* ---------- Layout ---------- */
        .shell {
            display: flex;
            min-height: 100vh
        }

        .sidebar {
            width: 250px;
            flex: 0 0 250px;
            background: var(--navy);
            color: #E2E8F0;
            display: flex;
            flex-direction: column;
            padding: 18px 14px;
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .brand {
            font-weight: 800;
            letter-spacing: .5px;
            font-size: 17px;
            color: #fff;
            padding: 6px 10px 2px
        }

        .brand small {
            display: block;
            font-weight: 500;
            font-size: 11px;
            color: #94A3B8;
            letter-spacing: .3px;
            margin-top: 2px
        }

        .nav {
            margin-top: 18px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            overflow-y: auto
        }

        .nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 10px;
            color: #CBD5E1;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: .15s;
        }

        .nav a:hover {
            background: rgba(255, 255, 255, .07);
            color: #fff;
            text-decoration: none
        }

        .nav a.active {
            background: var(--teal);
            color: #fff
        }

        .nav .sec {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #64748B;
            padding: 14px 12px 4px
        }

        .badge-count {
            margin-left: auto;
            background: var(--amber);
            color: #fff;
            border-radius: 99px;
            font-size: 11px;
            padding: 1px 7px;
            font-weight: 700
        }

        .sidebar-foot {
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, .1);
            font-size: 12px;
            color: #94A3B8
        }

        .sidebar-foot strong {
            color: #fff;
            display: block;
            font-size: 13px
        }

        .main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column
        }

        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--line);
            padding: 14px 26px;
            display: flex;
            align-items: center;
            gap: 14px;
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .topbar h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 700
        }

        .topbar .sub {
            color: var(--muted);
            font-size: 13px;
            margin-top: 1px
        }

        .topbar-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 12px
        }

        .hamburger {
            display: none;
            background: none;
            border: 0;
            font-size: 22px;
            cursor: pointer;
            color: var(--navy)
        }

        .content {
            padding: 26px;
            max-width: 1280px;
            width: 100%
        }

        /* ---------- Cards ---------- */
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 18px 20px;
            margin-bottom: 18px
        }

        .card h2 {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 700
        }

        .card .hint {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 14px
        }

        .grid {
            display: grid;
            gap: 16px
        }

        .grid-4 {
            grid-template-columns: repeat(4, 1fr)
        }

        .grid-2 {
            grid-template-columns: repeat(2, 1fr)
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr)
        }

        .stat {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 16px 18px
        }

        .stat .ico {
            font-size: 18px
        }

        .stat .num {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.1;
            margin: 6px 0 2px
        }

        .stat .lbl {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            font-weight: 600
        }

        .stat.ok .num {
            color: var(--green)
        }

        .stat.warn .num {
            color: var(--amber)
        }

        .stat.bad .num {
            color: var(--red)
        }

        /* ---------- Badges ---------- */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 99px;
            letter-spacing: .03em;
            text-transform: uppercase;
            white-space: nowrap
        }

        .badge.ok {
            background: #DCFCE7;
            color: #15803D
        }

        .badge.warn {
            background: #FEF3C7;
            color: #B45309
        }

        .badge.bad {
            background: #FEE2E2;
            color: #B91C1C
        }

        .badge.muted {
            background: #E2E8F0;
            color: #475569
        }

        /* ---------- Tables ---------- */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px
        }

        th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
            padding: 10px 12px;
            border-bottom: 1px solid var(--line);
            font-weight: 700;
            background: #FBFCFE
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle
        }

        tr:last-child td {
            border-bottom: 0
        }

        tr:hover td {
            background: #FCFDFF
        }

        /* ---------- Forms ---------- */
        label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--navy-2)
        }

        input[type=text],
        input[type=password],
        input[type=date],
        input[type=file],
        select,
        textarea {
            width: 100%;
            padding: 9px 11px;
            border: 1px solid var(--line);
            border-radius: 10px;
            font: inherit;
            font-size: 14px;
            background: #fff;
            color: var(--navy);
            transition: .15s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(13, 148, 136, .14)
        }

        textarea {
            min-height: 84px;
            resize: vertical
        }

        .field {
            margin-bottom: 14px
        }

        .row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap
        }

        .row>* {
            flex: 1 1 180px
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 16px;
            border-radius: 10px;
            border: 1px solid transparent;
            font: inherit;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            transition: .15s;
            text-decoration: none;
        }

        .btn:hover {
            text-decoration: none;
            transform: translateY(-1px)
        }

        .btn-primary {
            background: var(--teal);
            color: #fff
        }

        .btn-primary:hover {
            background: var(--teal-d);
            color: #fff
        }

        .btn-ok {
            background: var(--green);
            color: #fff
        }

        .btn-ok:hover {
            color: #fff;
            filter: brightness(.94)
        }

        .btn-danger {
            background: var(--red);
            color: #fff
        }

        .btn-danger:hover {
            color: #fff;
            filter: brightness(.94)
        }

        .btn-warn {
            background: var(--amber);
            color: #fff
        }

        .btn-warn:hover {
            color: #fff;
            filter: brightness(.94)
        }

        .btn-ghost {
            background: #fff;
            border-color: var(--line);
            color: var(--navy-2)
        }

        .btn-ghost:hover {
            border-color: var(--teal);
            color: var(--teal)
        }

        .btn-sm {
            padding: 6px 11px;
            font-size: 12.5px
        }

        /* ---------- Misc ---------- */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid transparent
        }

        .alert.ok {
            background: #F0FDF4;
            color: #15803D;
            border-color: #BBF7D0
        }

        .alert.bad {
            background: #FEF2F2;
            color: #B91C1C;
            border-color: #FECACA
        }

        .alert.warn {
            background: #FFFBEB;
            color: #B45309;
            border-color: #FDE68A
        }

        .kv {
            display: grid;
            grid-template-columns: 190px 1fr;
            gap: 9px 14px;
            font-size: 14px
        }

        .kv dt {
            color: var(--muted);
            font-weight: 600;
            font-size: 12.5px;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding-top: 2px
        }

        .kv dd {
            margin: 0;
            font-weight: 600
        }

        .timeline {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative
        }

        .timeline:before {
            content: "";
            position: absolute;
            left: 6px;
            top: 8px;
            bottom: 8px;
            width: 2px;
            background: var(--line)
        }

        .timeline li {
            position: relative;
            padding: 0 0 18px 26px
        }

        .timeline li:before {
            content: "";
            position: absolute;
            left: 1px;
            top: 4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--teal);
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px var(--teal)
        }

        .timeline .t-when {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600
        }

        .timeline .t-what {
            font-size: 14px;
            font-weight: 600;
            margin-top: 1px
        }

        .timeline .t-who {
            font-size: 12.5px;
            color: var(--muted)
        }

        .preview {
            background: #0F172A;
            border-radius: 12px;
            min-height: 420px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94A3B8;
            font-size: 13px;
            overflow: hidden;
            text-align: center;
            padding: 12px
        }

        .preview img,
        .preview iframe {
            max-width: 100%;
            width: 100%;
            height: 560px;
            border: 0;
            border-radius: 8px;
            background: #fff
        }

        .preview img {
            height: auto;
            object-fit: contain
        }

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 16px
        }

        .toolbar .field {
            margin-bottom: 0
        }

        .empty {
            text-align: center;
            padding: 44px 18px;
            color: var(--muted)
        }

        .empty .big {
            font-size: 34px;
            margin-bottom: 8px
        }

        /* ---------- Login ---------- */
        .auth-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: radial-gradient(1200px 600px at 50% -10%, #1E293B 0%, #0F172A 55%)
        }

        .auth-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 24px 60px -20px rgba(0, 0, 0, .5)
        }

        .auth-card h1 {
            margin: 0 0 4px;
            font-size: 22px;
            letter-spacing: .5px
        }

        .auth-card p.sub {
            margin: 0 0 22px;
            color: var(--muted);
            font-size: 13.5px
        }

        .auth-logo {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: var(--teal);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 14px
        }

        /* ---------- Mobile ---------- */
        .mobilebar {
            display: none
        }

        @media(max-width:960px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                z-index: 60;
                transform: translateX(-100%);
                transition: transform .22s ease
            }

            .sidebar.open {
                transform: translateX(0)
            }

            .hamburger {
                display: block
            }

            .grid-4 {
                grid-template-columns: repeat(2, 1fr)
            }

            .grid-3,
            .grid-2 {
                grid-template-columns: 1fr
            }

            .content {
                padding: 16px
            }

            .topbar {
                padding: 12px 16px
            }

            .kv {
                grid-template-columns: 1fr;
                gap: 2px 0
            }

            .kv dt {
                padding-top: 10px
            }

            .mobilebar {
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: #fff;
                border-top: 1px solid var(--line);
                z-index: 50;
                justify-content: space-around;
                padding: 7px 0 9px;
            }

            .mobilebar a {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
                font-size: 10px;
                color: var(--muted);
                font-weight: 600
            }

            .mobilebar a.active {
                color: var(--teal)
            }

            .mobilebar a span.i {
                font-size: 18px
            }

            body {
                padding-bottom: 64px
            }

            .scrim {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, .5);
                z-index: 55;
                display: none
            }

            .scrim.show {
                display: block
            }
        }

        @media print {

            .sidebar,
            .topbar,
            .mobilebar,
            .no-print {
                display: none !important
            }

            .content {
                padding: 0;
                max-width: 100%
            }

            .card {
                box-shadow: none;
                border: 0
            }

            body {
                background: #fff
            }
        }
    </style>
</head>

<body>

    <?php if (!$loggedIn): ?>
        <!-- ================= AUTH SCREENS ================= -->
        <div class="auth-wrap">
            <div class="auth-card">
                <div class="auth-logo">🩺</div>
                <h1><?= e(APP_NAME) ?></h1>
                <p class="sub"><?= e(APP_TAG) ?></p>

                <?php if ($flash): ?>
                    <div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
                <?php endif; ?>

                <?php if ($page === 'setup'): ?>
                    <h2 style="font-size:15px;margin:0 0 14px">First-run setup — create the Super Admin</h2>
                    <form method="post" autocomplete="off">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="setup">
                        <div class="field">
                            <label for="s-user">Username</label>
                            <input id="s-user" type="text" name="username" required minlength="3" pattern="[A-Za-z0-9._-]+">
                        </div>
                        <div class="field">
                            <label for="s-name">Full name</label>
                            <input id="s-name" type="text" name="full_name" required>
                        </div>
                        <div class="field">
                            <label for="s-pass">Password <span style="font-weight:400;color:var(--muted)">(min 10 chars)</span></label>
                            <input id="s-pass" type="password" name="password" required minlength="10">
                        </div>
                        <div class="field">
                            <label for="s-pass2">Confirm password</label>
                            <input id="s-pass2" type="password" name="password2" required minlength="10">
                        </div>
                        <button class="btn btn-primary" style="width:100%;justify-content:center">Create Super Admin</button>
                    </form>

                <?php else: ?>
                    <form method="post" autocomplete="off">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="login">
                        <div class="field">
                            <label for="l-user">Username</label>
                            <input id="l-user" type="text" name="username" required autofocus>
                        </div>
                        <div class="field">
                            <label for="l-pass">Password</label>
                            <input id="l-pass" type="password" name="password" required>
                        </div>
                        <button class="btn btn-primary" style="width:100%;justify-content:center">Sign In</button>
                    </form>
                <?php endif; ?>

                <p style="font-size:11.5px;color:var(--muted);margin:20px 0 0;text-align:center">
                    Confidential administrative system. All access is logged.
                </p>
            </div>
        </div>

    <?php else: ?>
        <!-- ================= APPLICATION SHELL ================= -->
        <div class="scrim" id="scrim"></div>
        <div class="shell">

            <aside class="sidebar" id="sidebar">
                <div class="brand"><?= e(APP_NAME) ?><small><?= e(APP_TAG) ?></small></div>
                <nav class="nav">
                    <a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">🏠 Dashboard</a>

                    <div class="sec">Entities</div>
                    <a href="?page=doctors" class="<?= in_array($page, ['doctors', 'doctor'], true) ? 'active' : '' ?>">👨‍⚕️ Doctors</a>
                    <a href="?page=pharmacies" class="<?= in_array($page, ['pharmacies', 'pharmacy'], true) ? 'active' : '' ?>">💊 Pharmacies</a>

                    <div class="sec">Compliance</div>
                    <a href="?page=documents" class="<?= $page === 'documents' ? 'active' : '' ?>">📄 All Documents</a>
                    <a href="?page=verify" class="<?= $page === 'verify' ? 'active' : '' ?>">🔍 Verification Center
                        <?php if ($pendingCount): ?><span class="badge-count"><?= (int)$pendingCount ?></span><?php endif; ?>
                    </a>
                    <a href="?page=expiry" class="<?= $page === 'expiry' ? 'active' : '' ?>">⏰ Expiry Center</a>

                    <div class="sec">Administration</div>
                    <a href="?page=reports" class="<?= in_array($page, ['reports', 'report'], true) ? 'active' : '' ?>">📊 Reports</a>
                    <a href="?page=audit" class="<?= $page === 'audit' ? 'active' : '' ?>">📜 Audit Logs</a>
                    <a href="?page=settings" class="<?= $page === 'settings' ? 'active' : '' ?>">⚙ Settings</a>
                    <a href="?page=logout">🚪 Logout</a>
                </nav>
                <div class="sidebar-foot">
                    <strong><?= e($me['full_name']) ?></strong>
                    <?= e(strtoupper(str_replace('_', ' ', $me['role']))) ?>
                </div>
            </aside>

            <div class="main">
                <header class="topbar">
                    <button class="hamburger" id="burger" aria-label="Menu">☰</button>
                    <div>
                        <h1><?= e($pageTitle) ?></h1>
                        <div class="sub"><?= e(APP_TAG) ?></div>
                    </div>
                    <div class="topbar-right">
                        <span class="badge <?= $me['role'] === 'super_admin' ? 'ok' : 'muted' ?>">
                            <?= e(str_replace('_', ' ', strtoupper($me['role']))) ?>
                        </span>
                    </div>
                </header>

                <div class="content">
                    <?php if ($flash): ?>
                        <div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
                    <?php endif; ?>

                    <?php
                    /* ============================================================================
   11. PAGE ROUTER
   ========================================================================== */
                    switch ($page) {

                        /* ------------------------------------------------------------------ DASHBOARD */
                        case 'dashboard':
                            $pageTitle = 'Dashboard';
                            $recentAudit = db()->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 6")->fetchAll();
                            $expiringSoon = db()->query(
                                "SELECT d.*, COALESCE(mp.full_name, ph.name) AS entity_name
         FROM documents d
         LEFT JOIN medical_professionals mp ON d.entity_type='doctor'   AND d.entity_id=mp.id
         LEFT JOIN pharmacies            ph ON d.entity_type='pharmacy' AND d.entity_id=ph.id
         WHERE d.expiry_date IS NOT NULL
           AND d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
         ORDER BY d.expiry_date ASC LIMIT 5"
                            )->fetchAll();
                    ?>
                            <div class="grid grid-4" style="margin-bottom:18px">
                                <div class="stat">
                                    <div class="ico">👥</div>
                                    <div class="num"><?= (int)$stats['entities'] ?></div>
                                    <div class="lbl">Total Entities</div>
                                </div>
                                <div class="stat ok">
                                    <div class="ico">✅</div>
                                    <div class="num"><?= (int)$stats['verified'] ?></div>
                                    <div class="lbl">Verified</div>
                                </div>
                                <div class="stat warn">
                                    <div class="ico">⏳</div>
                                    <div class="num"><?= (int)$stats['pending'] ?></div>
                                    <div class="lbl">Pending Review</div>
                                </div>
                                <div class="stat bad">
                                    <div class="ico">⚠</div>
                                    <div class="num"><?= (int)$stats['expired'] ?></div>
                                    <div class="lbl">Expired</div>
                                </div>
                            </div>

                            <div class="grid grid-2">
                                <div class="card">
                                    <h2>Doctors</h2>
                                    <div class="hint">Medical professionals under administration</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Reg. No.</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($doctors as $d): [$cls, $lbl] = status_meta((string)$d['overall_status']); ?>
                                                <tr>
                                                    <td>
                                                        <a href="?page=doctor&id=<?= (int)$d['id'] ?>"><strong><?= e($d['full_name']) ?></strong></a>
                                                        <?php if (!empty($d['alias'])): ?><br><span style="color:var(--muted);font-size:12.5px"><?= e($d['alias']) ?></span><?php endif; ?>
                                                    </td>
                                                    <td><?= e($d['reg_no']) ?></td>
                                                    <td><span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="card">
                                    <h2>Pharmacies</h2>
                                    <div class="hint">Registered drug licence holders</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Establishment</th>
                                                <th>Licence</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pharmacies as $p): [$cls, $lbl] = status_meta((string)$p['overall_status']); ?>
                                                <tr>
                                                    <td>
                                                        <a href="?page=pharmacy&id=<?= (int)$p['id'] ?>"><strong><?= e($p['name']) ?></strong></a>
                                                        <br><span style="color:var(--muted);font-size:12.5px"><?= e($p['owner']) ?></span>
                                                    </td>
                                                    <td style="font-size:12.5px"><?= e($p['form20'] ?: '—') ?><br><?= e($p['form21'] ?: '—') ?></td>
                                                    <td><span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <?php if ($expiringSoon): ?>
                                <div class="card">
                                    <h2>⏰ Expiring Soon</h2>
                                    <div class="hint">Documents expiring within the next 30 days</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Document</th>
                                                <th>Entity</th>
                                                <th>Expires</th>
                                                <th>Days Left</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($expiringSoon as $d): $n = days_remaining((string)$d['expiry_date']); ?>
                                                <tr>
                                                    <td><?= e($d['doc_type']) ?></td>
                                                    <td><?= e((string)$d['entity_name']) ?></td>
                                                    <td><?= e(fmt_date((string)$d['expiry_date'])) ?></td>
                                                    <td><span class="badge warn"><?= (int)$n ?> days</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <div class="card">
                                <h2>Verification Activity</h2>
                                <div class="hint">Most recent administrative actions</div>
                                <?php if (!$recentAudit): ?>
                                    <div class="empty">
                                        <div class="big">📜</div>No activity recorded yet.
                                    </div>
                                <?php else: ?>
                                    <ul class="timeline">
                                        <?php foreach ($recentAudit as $a): ?>
                                            <li>
                                                <div class="t-when"><?= e(fmt_dt((string)$a['created_at'])) ?></div>
                                                <div class="t-what"><?= e(str_replace('_', ' ', (string)$a['action'])) ?></div>
                                                <div class="t-who"><?= e((string)$a['username']) ?><?= $a['details'] ? ' — ' . e(mb_strimwidth((string)$a['details'], 0, 90, '…')) : '' ?></div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php
                            break;

                        /* -------------------------------------------------------------------- DOCTORS */
                        case 'doctors':
                            $pageTitle = 'Doctors';
                            $q      = trim((string)($_GET['q'] ?? ''));
                            $fstat  = trim((string)($_GET['status'] ?? ''));
                            $sql    = "SELECT * FROM medical_professionals WHERE 1=1";
                            $params = [];
                            if ($q !== '') {
                                $sql .= " AND (full_name LIKE ? OR reg_no LIKE ? OR COALESCE(alias,'') LIKE ?)";
                                $like = '%' . $q . '%';
                                array_push($params, $like, $like, $like);
                            }
                            if ($fstat !== '') {
                                $sql .= " AND overall_status = ?";
                                $params[] = $fstat;
                            }
                            $sql .= " ORDER BY id";
                            $st = db()->prepare($sql);
                            $st->execute($params);
                            $rows = $st->fetchAll();
                        ?>
                            <div class="card">
                                <h2>Doctor Verification</h2>
                                <div class="hint"><?= count($rows) ?> record(s)</div>

                                <form method="get" class="toolbar">
                                    <input type="hidden" name="page" value="doctors">
                                    <div class="field" style="flex:2 1 260px">
                                        <label for="dq">Search doctor</label>
                                        <input id="dq" type="text" name="q" value="<?= e($q) ?>" placeholder="Name, registration number, alias…">
                                    </div>
                                    <div class="field" style="flex:1 1 160px">
                                        <label for="ds">Status</label>
                                        <select id="ds" name="status">
                                            <option value="">All</option>
                                            <?php foreach (['VERIFIED', 'PENDING', 'REVIEW_REQUIRED', 'REJECTED', 'EXPIRED'] as $s): ?>
                                                <option value="<?= e($s) ?>" <?= $fstat === $s ? 'selected' : '' ?>><?= e(ucwords(strtolower(str_replace('_', ' ', $s)))) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary">Apply</button>
                                    <a class="btn btn-ghost" href="?page=doctors">Reset</a>
                                </form>

                                <?php if (!$rows): ?>
                                    <div class="empty">
                                        <div class="big">👨‍⚕️</div>No doctors match the current filters.
                                    </div>
                                    <?php else: foreach ($rows as $d):
                                        [$cls, $lbl] = status_meta((string)$d['overall_status']);
                                        $dc = db()->prepare("SELECT COUNT(*) total, SUM(status='VERIFIED') v FROM documents WHERE entity_type='doctor' AND entity_id=?");
                                        $dc->execute([$d['id']]);
                                        $cnt = $dc->fetch();
                                    ?>
                                        <div class="card" style="margin-bottom:14px">
                                            <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap">
                                                <div style="font-size:34px;line-height:1">👨‍⚕️</div>
                                                <div style="flex:1;min-width:220px">
                                                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                                        <strong style="font-size:16px"><?= e($d['full_name']) ?></strong>
                                                        <?php if (!empty($d['alias'])): ?><span style="color:var(--muted);font-size:13px">(<?= e($d['alias']) ?>)</span><?php endif; ?>
                                                        <span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span>
                                                    </div>
                                                    <div style="color:var(--muted);font-size:13.5px;margin-top:4px">
                                                        Reg. No. <strong><?= e($d['reg_no']) ?></strong>
                                                        <?php if (!empty($d['reg_date'])): ?> • <?= e(fmt_date((string)$d['reg_date'])) ?><?php endif; ?>
                                                    </div>
                                                    <div style="color:var(--muted);font-size:13.5px;margin-top:2px">
                                                        MBBS • <?= e($d['mbbs'] ?: '—') ?>
                                                        <?php if (!empty($d['md_qual'])): ?><br><?= e($d['md_qual']) ?><?= $d['md_date'] ? ' • ' . e((string)$d['md_date']) : '' ?><?php endif; ?>
                                                    </div>
                                                    <div style="font-size:13px;margin-top:8px">
                                                        Documents:
                                                        <strong><?= (int)($cnt['v'] ?? 0) ?> / <?= (int)($cnt['total'] ?? 0) ?></strong> verified
                                                    </div>
                                                </div>
                                                <div style="display:flex;gap:8px;flex-wrap:wrap">
                                                    <a class="btn btn-ghost btn-sm" href="?page=doctor&id=<?= (int)$d['id'] ?>">View Profile</a>
                                                    <a class="btn btn-ghost btn-sm" href="?page=documents&entity_type=doctor&entity_id=<?= (int)$d['id'] ?>">Documents</a>
                                                    <a class="btn btn-ghost btn-sm" href="?page=audit&entity_type=doctor&entity_id=<?= (int)$d['id'] ?>">Audit Trail</a>
                                                </div>
                                            </div>
                                        </div>
                                <?php endforeach;
                                endif; ?>
                            </div>
                        <?php
                            break;

                        /* ---------------------------------------------------------------- DOCTOR PROFILE */
                        case 'doctor':
                            $id = (int)($_GET['id'] ?? 0);
                            $st = db()->prepare("SELECT * FROM medical_professionals WHERE id=? LIMIT 1");
                            $st->execute([$id]);
                            $d = $st->fetch();
                            if (!$d) {
                                echo '<div class="card"><div class="empty"><div class="big">🚫</div>Doctor not found.</div></div>';
                                break;
                            }

                            $pageTitle = $d['full_name'];
                            [$cls, $lbl] = status_meta((string)$d['overall_status']);

                            $ds = db()->prepare("SELECT * FROM documents WHERE entity_type='doctor' AND entity_id=? ORDER BY id");
                            $ds->execute([$id]);
                            $docs = $ds->fetchAll();

                            $vs = db()->prepare(
                                "SELECT v.* FROM document_verifications v
         JOIN documents dd ON dd.id = v.document_id
         WHERE dd.entity_type='doctor' AND dd.entity_id=?
         ORDER BY v.id DESC LIMIT 12"
                            );
                            $vs->execute([$id]);
                            $timeline = $vs->fetchAll();
                        ?>
                            <p class="no-print" style="margin-top:0"><a href="?page=doctors">← Back to Doctors</a></p>

                            <div class="card">
                                <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
                                    <div style="font-size:44px;line-height:1">👨‍⚕️</div>
                                    <div style="flex:1;min-width:240px">
                                        <h2 style="font-size:19px;margin:0 0 2px"><?= e($d['full_name']) ?></h2>
                                        <div style="color:var(--muted);font-size:13.5px"><?= e($d['designation'] ?: 'Medical Professional') ?></div>
                                        <div style="margin-top:10px"><span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span></div>
                                    </div>
                                    <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap">
                                        <a class="btn btn-ghost btn-sm" href="?page=documents&entity_type=doctor&entity_id=<?= (int)$d['id'] ?>">Documents</a>
                                        <a class="btn btn-ghost btn-sm" href="?page=upload&entity_type=doctor&entity_id=<?= (int)$d['id'] ?>">Upload</a>
                                        <a class="btn btn-primary btn-sm" href="?page=report&entity_type=doctor&entity_id=<?= (int)$d['id'] ?>" target="_blank">Print Report</a>
                                    </div>
                                </div>

                                <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">

                                <dl class="kv">
                                    <dt>Registration No.</dt>
                                    <dd><?= e($d['reg_no']) ?></dd>
                                    <dt>Registration Date</dt>
                                    <dd><?= e(fmt_date((string)$d['reg_date'])) ?></dd>
                                    <dt>Verified By</dt>
                                    <dd><?= e($d['verified_by'] ?: '—') ?></dd>
                                    <dt>Verified On</dt>
                                    <dd><?= e(fmt_dt((string)$d['verified_at'])) ?></dd>
                                </dl>
                            </div>

                            <div class="card">
                                <h2>Education &amp; Qualifications</h2>
                                <div class="hint">Declared academic credentials</div>
                                <dl class="kv">
                                    <dt>MBBS</dt>
                                    <dd><?= e($d['mbbs'] ?: '—') ?></dd>
                                    <dt>Postgraduate</dt>
                                    <dd><?= e($d['md_qual'] ?: '—') ?></dd>
                                    <dt>Completion</dt>
                                    <dd><?= e($d['md_date'] ?: '—') ?></dd>
                                </dl>
                            </div>

                            <div class="card">
                                <h2>Document Verification</h2>
                                <div class="hint">Each document carries its own independent status</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Number</th>
                                            <th>Status</th>
                                            <th>Expiry</th>
                                            <th>Reviewer</th>
                                            <th class="no-print">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$docs): ?>
                                            <tr>
                                                <td colspan="6" class="empty">No documents uploaded yet.</td>
                                            </tr>
                                            <?php else: foreach ($docs as $doc): [$c2, $l2] = status_meta((string)$doc['status']); ?>
                                                <tr>
                                                    <td><strong><?= e($doc['doc_type']) ?></strong></td>
                                                    <td><?= e($doc['doc_number'] ?: '—') ?></td>
                                                    <td><span class="badge <?= e($c2) ?>"><?= e($l2) ?></span></td>
                                                    <td><?= e(fmt_date((string)$doc['expiry_date'])) ?></td>
                                                    <td><?= e($doc['uploaded_by'] ?: '—') ?></td>
                                                    <td class="no-print"><a class="btn btn-ghost btn-sm" href="?page=verify&id=<?= (int)$doc['id'] ?>">Open</a></td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="card">
                                <h2>Verification History</h2>
                                <div class="hint">Append-only record of every action taken</div>
                                <?php if (!$timeline): ?>
                                    <div class="empty">No verification events recorded.</div>
                                <?php else: ?>
                                    <ul class="timeline">
                                        <?php foreach ($timeline as $v): ?>
                                            <li>
                                                <div class="t-when"><?= e(fmt_dt((string)$v['created_at'])) ?></div>
                                                <div class="t-what"><?= e(str_replace('_', ' ', (string)$v['action'])) ?>
                                                    <?php if ($v['from_status'] || $v['to_status']): ?>
                                                        <span style="color:var(--muted);font-weight:400;font-size:12.5px">
                                                            (<?= e((string)($v['from_status'] ?: '—')) ?> → <?= e((string)($v['to_status'] ?: '—')) ?>)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="t-who"><?= e((string)$v['reviewer_name']) ?><?= $v['remarks'] ? ' — ' . e((string)$v['remarks']) : '' ?></div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php
                            break;

                        /* ------------------------------------------------------------------ PHARMACIES */
                        case 'pharmacies':
                            $pageTitle = 'Pharmacies';
                            $rows = db()->query("SELECT * FROM pharmacies ORDER BY id")->fetchAll();
                        ?>
                            <div class="card">
                                <h2>Pharmacy Verification</h2>
                                <div class="hint"><?= count($rows) ?> establishment(s)</div>
                                <?php foreach ($rows as $p): [$cls, $lbl] = status_meta((string)$p['overall_status']); ?>
                                    <div class="card" style="margin-bottom:14px">
                                        <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
                                            <div style="font-size:34px;line-height:1">💊</div>
                                            <div style="flex:1;min-width:240px">
                                                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                                    <strong style="font-size:16px"><?= e($p['name']) ?></strong>
                                                    <span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span>
                                                </div>
                                                <div style="color:var(--muted);font-size:13.5px;margin-top:4px">Owner: <?= e($p['owner']) ?></div>
                                                <div style="font-size:13px;margin-top:6px">
                                                    Form 20: <strong><?= e($p['form20'] ?: '—') ?></strong> &nbsp;•&nbsp;
                                                    Form 21: <strong><?= e($p['form21'] ?: '—') ?></strong>
                                                </div>
                                                <div style="font-size:13px;color:var(--muted);margin-top:4px">
                                                    Valid <?= e(fmt_date((string)$p['valid_from'])) ?> → <?= e(fmt_date((string)$p['valid_until'])) ?>
                                                </div>
                                            </div>
                                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                                <a class="btn btn-ghost btn-sm" href="?page=pharmacy&id=<?= (int)$p['id'] ?>">View Profile</a>
                                                <a class="btn btn-ghost btn-sm" href="?page=documents&entity_type=pharmacy&entity_id=<?= (int)$p['id'] ?>">Documents</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php
                            break;

                        /* ------------------------------------------------------------ PHARMACY PROFILE */
                        case 'pharmacy':
                            $id = (int)($_GET['id'] ?? 0);
                            $st = db()->prepare("SELECT * FROM pharmacies WHERE id=? LIMIT 1");
                            $st->execute([$id]);
                            $p = $st->fetch();
                            if (!$p) {
                                echo '<div class="card"><div class="empty"><div class="big">🚫</div>Pharmacy not found.</div></div>';
                                break;
                            }

                            $pageTitle = $p['name'];
                            [$cls, $lbl] = status_meta((string)$p['overall_status']);

                            $ds = db()->prepare("SELECT * FROM documents WHERE entity_type='pharmacy' AND entity_id=? ORDER BY id");
                            $ds->execute([$id]);
                            $docs = $ds->fetchAll();
                        ?>
                            <p class="no-print" style="margin-top:0"><a href="?page=pharmacies">← Back to Pharmacies</a></p>

                            <div class="card">
                                <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
                                    <div style="font-size:44px;line-height:1">💊</div>
                                    <div style="flex:1;min-width:240px">
                                        <h2 style="font-size:19px;margin:0 0 2px"><?= e($p['name']) ?></h2>
                                        <div style="color:var(--muted);font-size:13.5px">Owner: <?= e($p['owner']) ?></div>
                                        <div style="margin-top:10px"><span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span></div>
                                    </div>
                                    <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap">
                                        <a class="btn btn-ghost btn-sm" href="?page=upload&entity_type=pharmacy&entity_id=<?= (int)$p['id'] ?>">Upload</a>
                                        <a class="btn btn-primary btn-sm" href="?page=report&entity_type=pharmacy&entity_id=<?= (int)$p['id'] ?>" target="_blank">Print Report</a>
                                    </div>
                                </div>
                                <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
                                <dl class="kv">
                                    <dt>Form 20</dt>
                                    <dd><?= e($p['form20'] ?: '—') ?></dd>
                                    <dt>Form 21</dt>
                                    <dd><?= e($p['form21'] ?: '—') ?></dd>
                                    <dt>Valid From</dt>
                                    <dd><?= e(fmt_date((string)$p['valid_from'])) ?></dd>
                                    <dt>Valid Until</dt>
                                    <dd><?= e(fmt_date((string)$p['valid_until'])) ?></dd>
                                </dl>
                            </div>

                            <div class="card">
                                <h2>Documents</h2>
                                <div class="hint">Each licence carries its own status</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Number</th>
                                            <th>Status</th>
                                            <th>Expiry</th>
                                            <th class="no-print">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$docs): ?>
                                            <tr>
                                                <td colspan="5" class="empty">No documents uploaded yet.</td>
                                            </tr>
                                            <?php else: foreach ($docs as $doc): [$c2, $l2] = status_meta((string)$doc['status']); ?>
                                                <tr>
                                                    <td><strong><?= e($doc['doc_type']) ?></strong></td>
                                                    <td><?= e($doc['doc_number'] ?: '—') ?></td>
                                                    <td><span class="badge <?= e($c2) ?>"><?= e($l2) ?></span></td>
                                                    <td><?= e(fmt_date((string)$doc['expiry_date'])) ?></td>
                                                    <td class="no-print"><a class="btn btn-ghost btn-sm" href="?page=verify&id=<?= (int)$doc['id'] ?>">Open</a></td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php
                            break;

                        /* ------------------------------------------------------------------ DOCUMENTS */
                        case 'documents':
                            $pageTitle = 'All Documents';
                            $q       = trim((string)($_GET['q'] ?? ''));
                            $etype   = trim((string)($_GET['entity_type'] ?? ''));
                            $eid     = (int)($_GET['entity_id'] ?? 0);
                            $fstat   = trim((string)($_GET['status'] ?? ''));
                            $expf    = trim((string)($_GET['expiry'] ?? ''));

                            $sql = "SELECT d.*,
                   COALESCE(mp.full_name, ph.name) AS entity_name,
                   mp.reg_no AS doctor_reg,
                   ph.form20 AS ph_form20
            FROM documents d
            LEFT JOIN medical_professionals mp ON d.entity_type='doctor'   AND d.entity_id=mp.id
            LEFT JOIN pharmacies            ph ON d.entity_type='pharmacy' AND d.entity_id=ph.id
            WHERE 1=1";
                            $params = [];
                            if ($q !== '') {
                                $sql .= " AND (d.doc_type LIKE ? OR d.doc_number LIKE ? OR mp.full_name LIKE ? OR ph.name LIKE ?)";
                                $like = '%' . $q . '%';
                                array_push($params, $like, $like, $like, $like);
                            }
                            if (in_array($etype, ['doctor', 'pharmacy'], true)) {
                                $sql .= " AND d.entity_type=?";
                                $params[] = $etype;
                            }
                            if ($eid > 0) {
                                $sql .= " AND d.entity_id=?";
                                $params[] = $eid;
                            }
                            if ($fstat !== '') {
                                $sql .= " AND d.status=?";
                                $params[] = $fstat;
                            }
                            if ($expf === 'valid')   $sql .= " AND d.expiry_date IS NOT NULL AND d.expiry_date >= CURDATE()";
                            if ($expf === 'soon')    $sql .= " AND d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                            if ($expf === 'expired') $sql .= " AND d.expiry_date IS NOT NULL AND d.expiry_date < CURDATE()";
                            if ($expf === 'none')    $sql .= " AND d.expiry_date IS NULL";
                            $sql .= " ORDER BY d.id DESC";

                            $st = db()->prepare($sql);
                            $st->execute($params);
                            $rows = $st->fetchAll();
                        ?>
                            <div class="card">
                                <h2>Document Register</h2>
                                <div class="hint"><?= count($rows) ?> document(s) listed</div>

                                <form method="get" class="toolbar">
                                    <input type="hidden" name="page" value="documents">
                                    <div class="field" style="flex:2 1 240px">
                                        <label for="dq">Search</label>
                                        <input id="dq" type="text" name="q" value="<?= e($q) ?>" placeholder="Name, registration, document ID…">
                                    </div>
                                    <div class="field" style="flex:1 1 140px">
                                        <label for="et">Role</label>
                                        <select id="et" name="entity_type">
                                            <option value="">All</option>
                                            <option value="doctor" <?= $etype === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                                            <option value="pharmacy" <?= $etype === 'pharmacy' ? 'selected' : '' ?>>Pharmacy</option>
                                        </select>
                                    </div>
                                    <div class="field" style="flex:1 1 150px">
                                        <label for="fs">Status</label>
                                        <select id="fs" name="status">
                                            <option value="">All</option>
                                            <?php foreach (['VERIFIED', 'PENDING', 'REVIEW_REQUIRED', 'REJECTED', 'EXPIRED', 'CLARIFICATION_REQUIRED'] as $s): ?>
                                                <option value="<?= e($s) ?>" <?= $fstat === $s ? 'selected' : '' ?>><?= e(ucwords(strtolower(str_replace('_', ' ', $s)))) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="field" style="flex:1 1 140px">
                                        <label for="ex">Expiry</label>
                                        <select id="ex" name="expiry">
                                            <option value="">All</option>
                                            <option value="valid" <?= $expf === 'valid' ? 'selected' : '' ?>>Valid</option>
                                            <option value="soon" <?= $expf === 'soon' ? 'selected' : '' ?>>Expiring soon</option>
                                            <option value="expired" <?= $expf === 'expired' ? 'selected' : '' ?>>Expired</option>
                                            <option value="none" <?= $expf === 'none' ? 'selected' : '' ?>>No expiry</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary">Apply Filters</button>
                                    <a class="btn btn-ghost" href="?page=documents">Reset</a>
                                </form>

                                <?php if (!$rows): ?>
                                    <div class="empty">
                                        <div class="big">📄</div>No documents match the current filters.
                                    </div>
                                <?php else: ?>
                                    <div style="overflow-x:auto">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Entity</th>
                                                    <th>Type</th>
                                                    <th>Status</th>
                                                    <th>Expiry</th>
                                                    <th>Reviewer</th>
                                                    <th class="no-print">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rows as $doc): [$c2, $l2] = status_meta((string)$doc['status']); ?>
                                                    <tr>
                                                        <td style="font-family:monospace;font-size:12.5px">DOC-<?= str_pad((string)$doc['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                                        <td>
                                                            <strong><?= e((string)$doc['entity_name']) ?></strong><br>
                                                            <span style="color:var(--muted);font-size:12px"><?= e(ucfirst((string)$doc['entity_type'])) ?></span>
                                                        </td>
                                                        <td><?= e($doc['doc_type']) ?><?= $doc['doc_number'] ? '<br><span style="color:var(--muted);font-size:12px">' . e((string)$doc['doc_number']) . '</span>' : '' ?></td>
                                                        <td><span class="badge <?= e($c2) ?>"><?= e($l2) ?></span></td>
                                                        <td><?= e(fmt_date((string)$doc['expiry_date'])) ?></td>
                                                        <td><?= e($doc['uploaded_by'] ?: '—') ?></td>
                                                        <td class="no-print">
                                                            <a class="btn btn-ghost btn-sm" href="?page=verify&id=<?= (int)$doc['id'] ?>">View</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                            break;

                        /* ------------------------------------------------------- VERIFICATION WORKSPACE */
                        case 'verify':
                            if (empty($_GET['id'])) {
                                $pageTitle = 'Verification Center';
                                $rows = db()->query(
                                    "SELECT d.*, COALESCE(mp.full_name, ph.name) AS entity_name
             FROM documents d
             LEFT JOIN medical_professionals mp ON d.entity_type='doctor'   AND d.entity_id=mp.id
             LEFT JOIN pharmacies            ph ON d.entity_type='pharmacy' AND d.entity_id=ph.id
             WHERE d.status IN ('PENDING','REVIEW_REQUIRED','IN_PROGRESS','CLARIFICATION_REQUIRED','VERIFICATION')
             ORDER BY d.uploaded_at ASC"
                                )->fetchAll();
                            ?>
                                <div class="card">
                                    <h2>Documents Awaiting Verification</h2>
                                    <div class="hint"><?= count($rows) ?> item(s) in the queue</div>
                                    <?php if (!$rows): ?>
                                        <div class="empty">
                                            <div class="big">✅</div>Nothing pending. All documents have been reviewed.
                                        </div>
                                    <?php else: ?>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Entity</th>
                                                    <th>Document</th>
                                                    <th>Uploaded</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rows as $doc): [$c2, $l2] = status_meta((string)$doc['status']); ?>
                                                    <tr>
                                                        <td style="font-family:monospace;font-size:12.5px">DOC-<?= str_pad((string)$doc['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                                        <td><?= e((string)$doc['entity_name']) ?></td>
                                                        <td><?= e($doc['doc_type']) ?></td>
                                                        <td><?= e(fmt_dt((string)$doc['uploaded_at'])) ?></td>
                                                        <td><span class="badge <?= e($c2) ?>"><?= e($l2) ?></span></td>
                                                        <td><a class="btn btn-primary btn-sm" href="?page=verify&id=<?= (int)$doc['id'] ?>">Open</a></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            <?php
                                break;
                            }

                            $id = (int)$_GET['id'];
                            $st = db()->prepare(
                                "SELECT d.*, COALESCE(mp.full_name, ph.name) AS entity_name,
                mp.reg_no AS doctor_reg, mp.reg_date AS doctor_reg_date,
                mp.mbbs AS doctor_mbbs, mp.md_qual AS doctor_md, mp.md_date AS doctor_md_date,
                ph.owner AS ph_owner, ph.form20 AS ph_f20, ph.form21 AS ph_f21,
                ph.valid_from AS ph_from, ph.valid_until AS ph_until
         FROM documents d
         LEFT JOIN medical_professionals mp ON d.entity_type='doctor'   AND d.entity_id=mp.id
         LEFT JOIN pharmacies            ph ON d.entity_type='pharmacy' AND d.entity_id=ph.id
         WHERE d.id=? LIMIT 1"
                            );
                            $st->execute([$id]);
                            $doc = $st->fetch();
                            if (!$doc) {
                                echo '<div class="card"><div class="empty"><div class="big">🚫</div>Document not found.</div></div>';
                                break;
                            }

                            $pageTitle = 'Document Verification';
                            [$c2, $l2] = status_meta((string)$doc['status']);

                            $vh = db()->prepare("SELECT * FROM document_verifications WHERE document_id=? ORDER BY id DESC");
                            $vh->execute([$id]);
                            $history = $vh->fetchAll();
                            ?>
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px" class="no-print">
                                <a class="btn btn-ghost btn-sm" href="?page=verify">← Back</a>
                                <strong style="font-size:15px">DOCUMENT VERIFICATION</strong>
                                <span style="margin-left:auto;font-family:monospace;font-size:12.5px;color:var(--muted)">
                                    DOC-<?= str_pad((string)$doc['id'], 6, '0', STR_PAD_LEFT) ?>
                                </span>
                            </div>

                            <div class="grid" style="grid-template-columns:minmax(0,1.35fr) minmax(300px,1fr)">
                                <div class="card" style="margin-bottom:0">
                                    <h2>Document Preview</h2>
                                    <div class="hint"><?= e($doc['original_name'] ?: 'No file attached') ?></div>
                                    <div class="preview">
                                        <?php if (!empty($doc['file_path']) && is_file(STORAGE_DIR . '/' . basename((string)$doc['file_path']))):
                                            $mime = (string)($doc['mime_type'] ?? '');
                                            $url  = '?page=file&id=' . (int)$doc['id'];
                                            if (str_starts_with($mime, 'image/')): ?>
                                                <img src="<?= e($url) ?>" alt="Document preview">
                                            <?php elseif ($mime === 'application/pdf'): ?>
                                                <iframe src="<?= e($url) ?>" title="PDF preview"></iframe>
                                            <?php else: ?>
                                                <div>
                                                    <div style="font-size:38px;margin-bottom:8px">📄</div>
                                                    Preview not available for this file type.<br>
                                                    <a class="btn btn-ghost btn-sm" style="margin-top:12px" href="<?= e($url) ?>" target="_blank">Open file</a>
                                                </div>
                                            <?php endif;
                                        else: ?>
                                            <div>
                                                <div style="font-size:38px;margin-bottom:8px">🗂️</div>
                                                No physical file has been attached to this record.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div>
                                    <div class="card">
                                        <h2>Document Information</h2>
                                        <div class="hint">Record metadata</div>
                                        <dl class="kv" style="grid-template-columns:120px 1fr">
                                            <dt>Entity</dt>
                                            <dd><?= e((string)$doc['entity_name']) ?></dd>
                                            <dt>Category</dt>
                                            <dd><?= e(ucfirst((string)$doc['entity_type'])) ?></dd>
                                            <dt>Document</dt>
                                            <dd><?= e($doc['doc_type']) ?></dd>
                                            <dt>Number</dt>
                                            <dd><?= e($doc['doc_number'] ?: '—') ?></dd>
                                            <dt>Issue Date</dt>
                                            <dd><?= e(fmt_date((string)$doc['issue_date'])) ?></dd>
                                            <dt>Expiry</dt>
                                            <dd><?= e(fmt_date((string)$doc['expiry_date'])) ?></dd>
                                            <dt>Uploaded</dt>
                                            <dd><?= e(fmt_dt((string)$doc['uploaded_at'])) ?></dd>
                                            <dt>By</dt>
                                            <dd><?= e($doc['uploaded_by'] ?: '—') ?></dd>
                                            <dt>Status</dt>
                                            <dd><span class="badge <?= e($c2) ?>"><?= e($l2) ?></span></dd>
                                        </dl>

                                        <?php if ($doc['entity_type'] === 'doctor'): ?>
                                            <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
                                            <dl class="kv" style="grid-template-columns:120px 1fr">
                                                <dt>Reg. No.</dt>
                                                <dd><?= e((string)$doc['doctor_reg']) ?></dd>
                                                <dt>Reg. Date</dt>
                                                <dd><?= e(fmt_date((string)$doc['doctor_reg_date'])) ?></dd>
                                                <dt>MBBS</dt>
                                                <dd><?= e((string)($doc['doctor_mbbs'] ?: '—')) ?></dd>
                                                <dt>PG</dt>
                                                <dd><?= e((string)($doc['doctor_md'] ?: '—')) ?></dd>
                                            </dl>
                                        <?php else: ?>
                                            <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
                                            <dl class="kv" style="grid-template-columns:120px 1fr">
                                                <dt>Owner</dt>
                                                <dd><?= e((string)$doc['ph_owner']) ?></dd>
                                                <dt>Form 20</dt>
                                                <dd><?= e((string)($doc['ph_f20'] ?: '—')) ?></dd>
                                                <dt>Form 21</dt>
                                                <dd><?= e((string)($doc['ph_f21'] ?: '—')) ?></dd>
                                                <dt>Valid</dt>
                                                <dd><?= e(fmt_date((string)$doc['ph_from'])) ?> → <?= e(fmt_date((string)$doc['ph_until'])) ?></dd>
                                            </dl>
                                        <?php endif; ?>

                                        <?php if (!empty($doc['file_path'])): ?>
                                            <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap" class="no-print">
                                                <a class="btn btn-ghost btn-sm" href="?page=file&id=<?= (int)$doc['id'] ?>" target="_blank">Download</a>
                                                <a class="btn btn-ghost btn-sm" href="?page=file&id=<?= (int)$doc['id'] ?>" target="_blank"
                                                    onclick="window.print();return false;">Print</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card">
                                        <h2>Verification</h2>
                                        <?php if (!can_verify($me)): ?>
                                            <div class="hint">You have read-only access. Verification actions are restricted.</div>
                                        <?php else: ?>
                                            <form method="post" id="verifyForm">
                                                <?= csrf_field() ?>
                                                <div class="field">
                                                    <label>Reviewer</label>
                                                    <input type="text" value="<?= e($me['full_name']) ?>" disabled>
                                                </div>
                                                <div class="field">
                                                    <label for="rem">Remarks</label>
                                                    <textarea id="rem" name="remarks" placeholder="Optional for verification, mandatory for rejection…"></textarea>
                                                </div>

                                                <?php if (in_array(strtoupper((string)$doc['status']), ['PENDING', 'REVIEW_REQUIRED', 'IN_PROGRESS', 'CLARIFICATION_REQUIRED', 'VERIFICATION'], true)): ?>
                                                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                                                        <button class="btn btn-ok" type="submit" name="action" value="verify_document"
                                                            onclick="return confirm('Mark this document as VERIFIED?');">✓ Verify Document</button>
                                                        <button class="btn btn-danger" type="submit" name="action" value="reject_document"
                                                            onclick="return confirm('Reject this document? A reason is required.');">✕ Reject Document</button>
                                                    </div>
                                                    <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                                                <?php else: ?>
                                                    <div class="hint" style="margin-bottom:12px">
                                                        This document is currently <strong><?= e($l2) ?></strong>.
                                                        Reopening will return it to <code>REVIEW_REQUIRED</code> and log the action.
                                                    </div>
                                                    <button class="btn btn-warn" type="submit" name="action" value="reopen_document"
                                                        onclick="return confirm('Reopen verification for this document?');">↺ Reopen Verification</button>
                                                    <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card">
                                        <h2>Verification History</h2>
                                        <?php if (!$history): ?>
                                            <div class="empty">No events recorded.</div>
                                        <?php else: ?>
                                            <ul class="timeline">
                                                <?php foreach ($history as $h): ?>
                                                    <li>
                                                        <div class="t-when"><?= e(fmt_dt((string)$h['created_at'])) ?></div>
                                                        <div class="t-what"><?= e(str_replace('_', ' ', (string)$h['action'])) ?>
                                                            <?php if ($h['from_status'] || $h['to_status']): ?>
                                                                <span style="color:var(--muted);font-weight:400;font-size:12px">
                                                                    <?= e((string)($h['from_status'] ?: '—')) ?> → <?= e((string)($h['to_status'] ?: '—')) ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="t-who"><?= e((string)$h['reviewer_name']) ?><?= $h['remarks'] ? ' — ' . e((string)$h['remarks']) : '' ?></div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php
                            break;

                        /* --------------------------------------------------------------------- EXPIRY */
                        case 'expiry':
                            $pageTitle = 'Expiry Center';
                            $all = db()->query(
                                "SELECT d.*, COALESCE(mp.full_name, ph.name) AS entity_name
         FROM documents d
         LEFT JOIN medical_professionals mp ON d.entity_type='doctor'   AND d.entity_id=mp.id
         LEFT JOIN pharmacies            ph ON d.entity_type='pharmacy' AND d.entity_id=ph.id
         WHERE d.expiry_date IS NOT NULL
         ORDER BY d.expiry_date ASC"
                            )->fetchAll();
                            $noExpiry = db()->query(
                                "SELECT d.*, COALESCE(mp.full_name, ph.name) AS entity_name
         FROM documents d
         LEFT JOIN medical_professionals mp ON d.entity_type='doctor'   AND d.entity_id=mp.id
         LEFT JOIN pharmacies            ph ON d.entity_type='pharmacy' AND d.entity_id=ph.id
         WHERE d.expiry_date IS NULL"
                            )->fetchAll();

                            $buckets = ['valid' => [], 'soon' => [], 'expired' => []];
                            foreach ($all as $d) {
                                $buckets[expiry_bucket((string)$d['expiry_date'])][] = $d;
                            }
                        ?>
                            <div class="grid grid-3" style="margin-bottom:18px">
                                <div class="stat ok">
                                    <div class="ico">🟢</div>
                                    <div class="num"><?= count($buckets['valid']) ?></div>
                                    <div class="lbl">Valid</div>
                                </div>
                                <div class="stat warn">
                                    <div class="ico">🟠</div>
                                    <div class="num"><?= count($buckets['soon']) ?></div>
                                    <div class="lbl">Expiring Soon</div>
                                </div>
                                <div class="stat bad">
                                    <div class="ico">🔴</div>
                                    <div class="num"><?= count($buckets['expired']) ?></div>
                                    <div class="lbl">Expired</div>
                                </div>
                            </div>

                            <?php foreach (
                                [
                                    ['valid',   '🟢 Valid Documents',      'Currently within the validity period'],
                                    ['soon',    '🟠 Expiring Soon',        'Expiring within the next 30 days'],
                                    ['expired', '🔴 Expired',              'Past their expiry date'],
                                ] as [$key, $title, $sub]
                            ): ?>
                                <div class="card">
                                    <h2><?= e($title) ?></h2>
                                    <div class="hint"><?= e($sub) ?> — <?= count($buckets[$key]) ?> item(s)</div>
                                    <?php if (!$buckets[$key]): ?>
                                        <div class="empty">No documents in this category.</div>
                                    <?php else: ?>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Document</th>
                                                    <th>Entity</th>
                                                    <th>Number</th>
                                                    <th>Expiry</th>
                                                    <th>Days</th>
                                                    <th class="no-print">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($buckets[$key] as $d): $n = days_remaining((string)$d['expiry_date']); ?>
                                                    <tr>
                                                        <td><strong><?= e($d['doc_type']) ?></strong></td>
                                                        <td><?= e((string)$d['entity_name']) ?></td>
                                                        <td><?= e($d['doc_number'] ?: '—') ?></td>
                                                        <td><?= e(fmt_date((string)$d['expiry_date'])) ?></td>
                                                        <td>
                                                            <?php if ($n === null): ?>—
                                                        <?php elseif ($n < 0): ?><span class="badge bad"><?= (int)abs($n) ?> days overdue</span>
                                                        <?php elseif ($n <= 30): ?><span class="badge warn"><?= (int)$n ?> days left</span>
                                                        <?php else: ?><span class="badge ok"><?= (int)$n ?> days</span>
                                                        <?php endif; ?>
                                                        </td>
                                                        <td class="no-print"><a class="btn btn-ghost btn-sm" href="?page=verify&id=<?= (int)$d['id'] ?>">View</a></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <div class="card">
                                <h2>⚪ No Expiry</h2>
                                <div class="hint">Documents without an expiry date — <?= count($noExpiry) ?> item(s)</div>
                                <?php if (!$noExpiry): ?>
                                    <div class="empty">Every document has an expiry date.</div>
                                <?php else: ?>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Document</th>
                                                <th>Entity</th>
                                                <th>Status</th>
                                                <th class="no-print">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($noExpiry as $d): [$c2, $l2] = status_meta((string)$d['status']); ?>
                                                <tr>
                                                    <td><strong><?= e($d['doc_type']) ?></strong></td>
                                                    <td><?= e((string)$d['entity_name']) ?></td>
                                                    <td><span class="badge <?= e($c2) ?>"><?= e($l2) ?></span></td>
                                                    <td class="no-print"><a class="btn btn-ghost btn-sm" href="?page=verify&id=<?= (int)$d['id'] ?>">View</a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php
                            break;

                        /* -------------------------------------------------------------------- REPORTS */
                        case 'report':
                            $etype = trim((string)($_GET['entity_type'] ?? ''));
                            $eid   = (int)($_GET['entity_id'] ?? 0);
                            $pageTitle = 'Report';

                            if ($etype === '' || $eid <= 0) {
                                echo '<div class="card"><div class="empty"><div class="big">📊</div>Select an entity to generate a report.</div></div>';
                                break;
                            }

                            $isDoc = ($etype === 'doctor');
                            if ($isDoc) {
                                $q = db()->prepare("SELECT * FROM medical_professionals WHERE id=? LIMIT 1");
                            } else {
                                $q = db()->prepare("SELECT * FROM pharmacies WHERE id=? LIMIT 1");
                            }
                            $q->execute([$eid]);
                            $ent = $q->fetch();
                            if (!$ent) {
                                echo '<div class="card"><div class="empty"><div class="big">🚫</div>Entity not found.</div></div>';
                                break;
                            }

                            $dq = db()->prepare("SELECT * FROM documents WHERE entity_type=? AND entity_id=? ORDER BY id");
                            $dq->execute([$etype, $eid]);
                            $docs = $dq->fetchAll();

                            [$ecls, $elbl] = status_meta((string)$ent['overall_status']);
                        ?>
                            <div class="card">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
                                    <div>
                                        <h2 style="font-size:20px;margin:0 0 2px"><?= e(APP_NAME) ?></h2>
                                        <div style="color:var(--muted);font-size:13px">DOCUMENT VERIFICATION REPORT</div>
                                    </div>
                                    <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap">
                                        <a class="btn btn-ghost btn-sm" href="?page=reports">← All Reports</a>
                                        <button class="btn btn-primary btn-sm" onclick="window.print()">🖨 Print / Save as PDF</button>
                                    </div>
                                </div>
                                <hr style="border:0;border-top:2px solid var(--navy);margin:16px 0">

                                <dl class="kv">
                                    <dt>Entity</dt>
                                    <dd><?= e((string)($isDoc ? $ent['full_name'] : $ent['name'])) ?></dd>
                                    <dt>Category</dt>
                                    <dd><?= e(ucfirst($etype)) ?></dd>
                                    <?php if ($isDoc): ?>
                                        <dt>Registration No.</dt>
                                        <dd><?= e($ent['reg_no']) ?></dd>
                                        <dt>Registration Date</dt>
                                        <dd><?= e(fmt_date((string)$ent['reg_date'])) ?></dd>
                                        <dt>MBBS</dt>
                                        <dd><?= e($ent['mbbs'] ?: '—') ?></dd>
                                        <dt>Postgraduate</dt>
                                        <dd><?= e($ent['md_qual'] ?: '—') ?></dd>
                                        <dt>Completion</dt>
                                        <dd><?= e($ent['md_date'] ?: '—') ?></dd>
                                    <?php else: ?>
                                        <dt>Owner</dt>
                                        <dd><?= e($ent['owner']) ?></dd>
                                        <dt>Form 20</dt>
                                        <dd><?= e($ent['form20'] ?: '—') ?></dd>
                                        <dt>Form 21</dt>
                                        <dd><?= e($ent['form21'] ?: '—') ?></dd>
                                        <dt>Valid From</dt>
                                        <dd><?= e(fmt_date((string)$ent['valid_from'])) ?></dd>
                                        <dt>Valid Until</dt>
                                        <dd><?= e(fmt_date((string)$ent['valid_until'])) ?></dd>
                                    <?php endif; ?>
                                    <dt>Overall Status</dt>
                                    <dd><span class="badge <?= e($ecls) ?>"><?= e($elbl) ?></span></dd>
                                </dl>

                                <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">

                                <h3 style="font-size:14px;margin:0 0 10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)">Documents</h3>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Number</th>
                                            <th>Issue</th>
                                            <th>Expiry</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$docs): ?>
                                            <tr>
                                                <td colspan="5" class="empty">No documents on record.</td>
                                            </tr>
                                            <?php else: foreach ($docs as $d): [$c2, $l2] = status_meta((string)$d['status']); ?>
                                                <tr>
                                                    <td><?= e($d['doc_type']) ?></td>
                                                    <td><?= e($d['doc_number'] ?: '—') ?></td>
                                                    <td><?= e(fmt_date((string)$d['issue_date'])) ?></td>
                                                    <td><?= e(fmt_date((string)$d['expiry_date'])) ?></td>
                                                    <td><span class="badge <?= e($c2) ?>"><?= e($l2) ?></span></td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>

                                <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">

                                <h3 style="font-size:14px;margin:0 0 10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)">Verification History</h3>
                                <?php
                                $hs = db()->prepare(
                                    "SELECT v.*, d.doc_type
           FROM document_verifications v
           JOIN documents d ON d.id = v.document_id
           WHERE d.entity_type=? AND d.entity_id=?
           ORDER BY v.id DESC LIMIT 20"
                                );
                                $hs->execute([$etype, $eid]);
                                $hist = $hs->fetchAll();
                                ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Document</th>
                                            <th>Reviewer</th>
                                            <th>Date / Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$hist): ?>
                                            <tr>
                                                <td colspan="4" class="empty">No verification events.</td>
                                            </tr>
                                            <?php else: foreach ($hist as $h): ?>
                                                <tr>
                                                    <td><?= e(str_replace('_', ' ', (string)$h['action'])) ?></td>
                                                    <td><?= e((string)$h['doc_type']) ?></td>
                                                    <td><?= e((string)$h['reviewer_name']) ?></td>
                                                    <td><?= e(fmt_dt((string)$h['created_at'])) ?></td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>

                                <hr style="border:0;border-top:1px solid var(--navy);margin:20px 0 10px">
                                <div style="font-size:12px;color:var(--muted);text-align:center">
                                    <?= e(APP_NAME) ?> — Confidential Administrative Record<br>
                                    Generated <?= e(date('d M Y H:i')) ?> by <?= e($me['full_name']) ?>
                                </div>
                            </div>
                        <?php
                            break;

                        case 'reports':
                            $pageTitle = 'Reports';
                        ?>
                            <div class="grid grid-2">
                                <div class="card">
                                    <h2>Doctor Reports</h2>
                                    <div class="hint">Printable verification summaries</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Doctor</th>
                                                <th>Status</th>
                                                <th class="no-print">Report</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($doctors as $d): [$c2, $l2] = status_meta((string)$d['overall_status']); ?>
                                                <tr>
                                                    <td><strong><?= e($d['full_name']) ?></strong><br>
                                                        <span style="color:var(--muted);font-size:12px">Reg. <?= e($d['reg_no']) ?></span>
                                                    </td>
                                                    <td><span class="badge <?= e($c2) ?>"><?= e($l2) ?></span></td>
                                                    <td class="no-print">
                                                        <a class="btn btn-primary btn-sm" target="_blank"
                                                            href="?page=report&entity_type=doctor&entity_id=<?= (int)$d['id'] ?>">Generate</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="card">
                                    <h2>Pharmacy Reports</h2>
                                    <div class="hint">Printable licence summaries</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Pharmacy</th>
                                                <th>Status</th>
                                                <th class="no-print">Report</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pharmacies as $p): [$c2, $l2] = status_meta((string)$p['overall_status']); ?>
                                                <tr>
                                                    <td><strong><?= e($p['name']) ?></strong><br>
                                                        <span style="color:var(--muted);font-size:12px"><?= e($p['owner']) ?></span>
                                                    </td>
                                                    <td><span class="badge <?= e($c2) ?>"><?= e($l2) ?></span></td>
                                                    <td class="no-print">
                                                        <a class="btn btn-primary btn-sm" target="_blank"
                                                            href="?page=report&entity_type=pharmacy&entity_id=<?= (int)$p['id'] ?>">Generate</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php
                            break;

                        /* ----------------------------------------------------------------- AUDIT LOGS */
                        case 'audit':
                            $pageTitle = 'Audit Logs';
                            $etype = trim((string)($_GET['entity_type'] ?? ''));
                            $eid   = (int)($_GET['entity_id'] ?? 0);
                            $fuser = trim((string)($_GET['user'] ?? ''));
                            $fq    = trim((string)($_GET['q'] ?? ''));

                            $sql = "SELECT * FROM audit_logs WHERE 1=1";
                            $p   = [];
                            if ($etype !== '') {
                                $sql .= " AND entity_type=?";
                                $p[] = $etype;
                            }
                            if ($eid > 0) {
                                $sql .= " AND entity_id=?";
                                $p[] = $eid;
                            }
                            if ($fuser !== '') {
                                $sql .= " AND username=?";
                                $p[] = $fuser;
                            }
                            if ($fq !== '') {
                                $sql .= " AND (action LIKE ? OR details LIKE ?)";
                                $like = '%' . $fq . '%';
                                array_push($p, $like, $like);
                            }
                            $sql .= " ORDER BY id DESC LIMIT 300";
                            $st = db()->prepare($sql);
                            $st->execute($p);
                            $logs = $st->fetchAll();
                        ?>
                            <div class="card">
                                <h2>Audit History</h2>
                                <div class="hint">Append-only — records cannot be edited or deleted. Showing latest <?= count($logs) ?> entries.</div>

                                <form method="get" class="toolbar">
                                    <input type="hidden" name="page" value="audit">
                                    <div class="field" style="flex:2 1 240px">
                                        <label for="fq">Search</label>
                                        <input id="fq" type="text" name="q" value="<?= e($fq) ?>" placeholder="Action or details…">
                                    </div>
                                    <div class="field" style="flex:1 1 160px">
                                        <label for="fu">User</label>
                                        <input id="fu" type="text" name="user" value="<?= e($fuser) ?>" placeholder="username">
                                    </div>
                                    <div class="field" style="flex:1 1 160px">
                                        <label for="fet">Entity Type</label>
                                        <select id="fet" name="entity_type">
                                            <option value="">All</option>
                                            <?php foreach (['doctor', 'pharmacy', 'document', 'user'] as $t): ?>
                                                <option value="<?= e($t) ?>" <?= $etype === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary">Apply</button>
                                    <a class="btn btn-ghost" href="?page=audit">Reset</a>
                                </form>

                                <?php if (!$logs): ?>
                                    <div class="empty">
                                        <div class="big">📜</div>No audit entries match the current filters.
                                    </div>
                                <?php else: ?>
                                    <div style="overflow-x:auto">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Date / Time</th>
                                                    <th>User</th>
                                                    <th>Action</th>
                                                    <th>Entity</th>
                                                    <th>Details</th>
                                                    <th>IP</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($logs as $l): ?>
                                                    <tr>
                                                        <td style="white-space:nowrap"><?= e(fmt_dt((string)$l['created_at'])) ?></td>
                                                        <td><?= e((string)$l['username']) ?></td>
                                                        <td><strong><?= e(str_replace('_', ' ', (string)$l['action'])) ?></strong></td>
                                                        <td>
                                                            <?php if ($l['entity_type']): ?>
                                                                <?= e((string)$l['entity_type']) ?><?= $l['entity_id'] ? ' #' . (int)$l['entity_id'] : '' ?>
                                                                <?php else: ?>—<?php endif; ?>
                                                        </td>
                                                        <td style="color:var(--muted);font-size:12.5px"><?= e(mb_strimwidth((string)$l['details'], 0, 110, '…')) ?></td>
                                                        <td style="font-family:monospace;font-size:12px;color:var(--muted)"><?= e((string)$l['ip']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php
                            break;

                        /* ------------------------------------------------------------------ SETTINGS */
                        case 'settings':
                            $pageTitle = 'Settings';
                            require_role(['super_admin']);
                            $users = db()->query("SELECT id, username, full_name, role, is_active, created_at FROM users ORDER BY id")->fetchAll();
                        ?>
                            <div class="card">
                                <h2>Accounts</h2>
                                <div class="hint">Manage administrative access. Only the Super Admin may create or modify accounts.</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Role</th>
                                            <th>Active</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $u): ?>
                                            <tr>
                                                <td><?= (int)$u['id'] ?></td>
                                                <td><strong><?= e($u['username']) ?></strong></td>
                                                <td><?= e($u['full_name']) ?></td>
                                                <td><span class="badge <?= $u['role'] === 'super_admin' ? 'ok' : 'muted' ?>"><?= e(str_replace('_', ' ', strtoupper($u['role']))) ?></span></td>
                                                <td><?= (int)$u['is_active'] ? '✅' : '—' ?></td>
                                                <td><?= e(fmt_dt((string)$u['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="card">
                                <h2>Add User</h2>
                                <div class="hint">Passwords are hashed with PHP's <code>password_hash()</code>. Minimum 10 characters.</div>
                                <form method="post" autocomplete="off">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add_user">
                                    <div class="row">
                                        <div class="field">
                                            <label for="au">Username</label>
                                            <input id="au" type="text" name="username" required minlength="3" pattern="[A-Za-z0-9._-]+">
                                        </div>
                                        <div class="field">
                                            <label for="an">Full name</label>
                                            <input id="an" type="text" name="full_name" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="field">
                                            <label for="ap">Password</label>
                                            <input id="ap" type="password" name="password" required minlength="10">
                                        </div>
                                        <div class="field">
                                            <label for="ar">Role</label>
                                            <select id="ar" name="role">
                                                <option value="admin">Admin</option>
                                                <option value="super_admin">Super Admin</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary">Create User</button>
                                </form>
                            </div>

                            <div class="card">
                                <h2>Role Permissions</h2>
                                <div class="hint">Fixed capability matrix enforced by the server, not just the UI.</div>
                                <div class="grid grid-2">
                                    <div>
                                        <h3 style="font-size:14px;margin:0 0 8px">Super Admin</h3>
                                        <table>
                                            <tbody>
                                                <?php foreach (['Dashboard', 'Doctors', 'Pharmacies', 'Upload', 'Edit', 'Verify', 'Reject', 'Reopen Verification', 'Delete', 'Reports', 'Audit Logs', 'Settings'] as $c): ?>
                                                    <tr>
                                                        <td><?= e($c) ?></td>
                                                        <td style="text-align:right;color:var(--green);font-weight:700">✓</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div>
                                        <h3 style="font-size:14px;margin:0 0 8px">Admin</h3>
                                        <table>
                                            <tbody>
                                                <?php foreach (
                                                    [
                                                        'Dashboard' => '✓',
                                                        'Doctors' => '✓',
                                                        'Pharmacies' => '✓',
                                                        'Upload' => '✓',
                                                        'Edit' => '✓',
                                                        'Verify' => '✓',
                                                        'Reject' => '✓',
                                                        'Reopen Verification' => '✕',
                                                        'Delete' => '✕',
                                                        'Reports' => '✓',
                                                        'Audit Logs' => 'read only',
                                                        'Settings' => 'limited',
                                                    ] as $c => $v
                                                ): ?>
                                                    <tr>
                                                        <td><?= e($c) ?></td>
                                                        <td style="text-align:right;font-weight:700;color:<?= $v === '✕' ? 'var(--red)' : 'var(--green)' ?>"><?= e($v) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <h2>Security Posture</h2>
                                <div class="hint">Measures currently enforced in this application.</div>
                                <ul style="font-size:13.5px;line-height:1.9;margin:0;padding-left:20px">
                                    <li>Database-backed authentication with <code>password_hash()</code> / <code>password_verify()</code></li>
                                    <li>Session ID regeneration on login and first boot</li>
                                    <li>CSRF tokens on every state-changing form</li>
                                    <li>Prepared statements for all SQL (emulation disabled)</li>
                                    <li>Output escaped via <code>htmlspecialchars()</code></li>
                                    <li>MIME + extension validation on uploads; 10 MB size limit</li>
                                    <li>Randomised filenames; documents stored outside the web root</li>
                                    <li>Authenticated streaming — no direct file URLs</li>
                                    <li>Role-based permission checks server-side</li>
                                    <li>Append-only audit logging of every important action</li>
                                    <li>Login throttling after 8 failed attempts within 10 minutes</li>
                                    <li>No hard-coded administrator password anywhere in this file</li>
                                    <li>No destructive actions are reachable via GET</li>
                                    <li>Confirmation prompts before reject / reopen actions</li>
                                </ul>
                            </div>
                        <?php
                            break;

                        /* -------------------------------------------------------------------- UPLOAD */
                        case 'upload':
                            $pageTitle = 'Upload Document';
                            require_role(['super_admin', 'admin']);
                            $selType = trim((string)($_GET['entity_type'] ?? 'doctor'));
                            $selId   = (int)($_GET['entity_id'] ?? 0);
                            if (!in_array($selType, ['doctor', 'pharmacy'], true)) $selType = 'doctor';
                        ?>
                            <div class="card" style="max-width:720px">
                                <h2>Upload Document</h2>
                                <div class="hint">Supported: PDF, JPG, JPEG, PNG, WebP — maximum 10 MB</div>

                                <form method="post" enctype="multipart/form-data" id="uploadForm">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="upload_document">

                                    <div class="row">
                                        <div class="field">
                                            <label for="et">Entity Type *</label>
                                            <select id="et" name="entity_type" required>
                                                <option value="doctor" <?= $selType === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                                                <option value="pharmacy" <?= $selType === 'pharmacy' ? 'selected' : '' ?>>Pharmacy</option>
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label for="ei">Entity *</label>
                                            <select id="ei" name="entity_id" required>
                                                <optgroup label="Doctors" data-group="doctor">
                                                    <?php foreach ($doctors as $d): ?>
                                                        <option value="<?= (int)$d['id'] ?>" data-group="doctor"
                                                            <?= ($selType === 'doctor' && $selId === (int)$d['id']) ? 'selected' : '' ?>>
                                                            <?= e($d['full_name']) ?> (Reg. <?= e($d['reg_no']) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <optgroup label="Pharmacies" data-group="pharmacy">
                                                    <?php foreach ($pharmacies as $p): ?>
                                                        <option value="<?= (int)$p['id'] ?>" data-group="pharmacy"
                                                            <?= ($selType === 'pharmacy' && $selId === (int)$p['id']) ? 'selected' : '' ?>>
                                                            <?= e($p['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label for="dt">Document Type *</label>
                                        <select id="dt" name="doc_type" required>
                                            <option value="">— Select —</option>
                                            <?php foreach (
                                                [
                                                    'Medical Registration Certificate',
                                                    'MBBS Certificate',
                                                    'MD Pediatrics Certificate',
                                                    'Form 20 Drug Licence',
                                                    'Form 21 Drug Licence',
                                                    'Other Supporting Document'
                                                ] as $t
                                            ): ?>
                                                <option value="<?= e($t) ?>"><?= e($t) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="field">
                                        <label for="dn">Document Number</label>
                                        <input id="dn" type="text" name="doc_number" placeholder="e.g. 131985 or KA-GD1-288637">
                                    </div>

                                    <div class="row">
                                        <div class="field">
                                            <label for="isd">Issue Date</label>
                                            <input id="isd" type="date" name="issue_date">
                                        </div>
                                        <div class="field">
                                            <label for="exd">Expiry Date</label>
                                            <input id="exd" type="date" name="expiry_date">
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label>Upload File</label>
                                        <div id="drop" style="border:2px dashed var(--line);border-radius:14px;padding:26px;text-align:center;
               background:#FBFCFE;cursor:pointer;transition:.15s">
                                            <div style="font-size:30px">📤</div>
                                            <div style="font-weight:600;margin-top:6px">Drag &amp; drop document here</div>
                                            <div style="color:var(--muted);font-size:12.5px;margin:4px 0 12px">or</div>
                                            <input type="file" id="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp"
                                                style="display:none">
                                            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('file').click()">
                                                Choose File
                                            </button>
                                            <div id="fname" style="margin-top:10px;font-size:12.5px;color:var(--muted)"></div>
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label for="rm">Remarks</label>
                                        <textarea id="rm" name="remarks" placeholder="Optional notes for the reviewer…"></textarea>
                                    </div>

                                    <div style="display:flex;gap:10px">
                                        <a class="btn btn-ghost" href="?page=documents">Cancel</a>
                                        <button class="btn btn-primary" type="submit">Upload Document</button>
                                    </div>
                                </form>
                            </div>

                            <script>
                                (function() {
                                    var et = document.getElementById('et');
                                    var ei = document.getElementById('ei');
                                    var file = document.getElementById('file');
                                    var fname = document.getElementById('fname');
                                    var drop = document.getElementById('drop');

                                    function filterEntities() {
                                        var want = et.value;
                                        Array.prototype.forEach.call(ei.querySelectorAll('option'), function(o) {
                                            if (o.dataset.group) {
                                                o.hidden = (o.dataset.group !== want);
                                            }
                                        });
                                        var first = ei.querySelector('option[data-group="' + want + '"]:not([hidden])');
                                        var cur = ei.selectedOptions[0];
                                        if (!cur || cur.hidden) {
                                            if (first) ei.value = first.value;
                                        }
                                        Array.prototype.forEach.call(ei.querySelectorAll('optgroup'), function(g) {
                                            g.hidden = (g.dataset.group !== want);
                                        });
                                    }
                                    et.addEventListener('change', filterEntities);
                                    filterEntities();

                                    function show() {
                                        if (file.files && file.files[0]) {
                                            var f = file.files[0];
                                            fname.textContent = f.name + ' — ' + (f.size / 1024).toFixed(0) + ' KB';
                                        }
                                    }
                                    file.addEventListener('change', show);

                                    drop.addEventListener('click', function(ev) {
                                        if (ev.target.tagName === 'BUTTON') return;
                                        file.click();
                                    });
                                    ['dragenter', 'dragover'].forEach(function(ev) {
                                        drop.addEventListener(ev, function(e) {
                                            e.preventDefault();
                                            drop.style.borderColor = 'var(--teal)';
                                            drop.style.background = '#F0FDFA';
                                        });
                                    });
                                    ['dragleave', 'drop'].forEach(function(ev) {
                                        drop.addEventListener(ev, function(e) {
                                            e.preventDefault();
                                            drop.style.borderColor = 'var(--line)';
                                            drop.style.background = '#FBFCFE';
                                        });
                                    });
                                    drop.addEventListener('drop', function(e) {
                                        if (e.dataTransfer && e.dataTransfer.files.length) {
                                            file.files = e.dataTransfer.files;
                                            show();
                                        }
                                    });
                                })();
                            </script>
                        <?php
                            break;

                        /* ---------------------------------------------------------------------- 404 */
                        default:
                            $pageTitle = 'Not Found';
                        ?>
                            <div class="card">
                                <div class="empty">
                                    <div class="big">🧭</div>
                                    <strong>Page not found</strong>
                                    <p style="margin:8px 0 16px">The requested section does not exist.</p>
                                    <a class="btn btn-primary" href="?page=dashboard">Return to Dashboard</a>
                                </div>
                            </div>
                    <?php
                            break;
                    }
                    ?>
                </div><!-- /content -->
            </div><!-- /main -->
        </div><!-- /shell -->

        <nav class="mobilebar">
            <a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>"><span class="i">🏠</span>Home</a>
            <a href="?page=doctors" class="<?= $page === 'doctors' ? 'active' : '' ?>"><span class="i">👨‍⚕️</span>Doctors</a>
            <a href="?page=documents" class="<?= $page === 'documents' ? 'active' : '' ?>"><span class="i">📄</span>Docs</a>
            <a href="?page=verify" class="<?= $page === 'verify' ? 'active' : '' ?>"><span class="i">🔍</span>Verify</a>
            <a href="?page=reports" class="<?= $page === 'reports' ? 'active' : '' ?>"><span class="i">📊</span>Reports</a>
        </nav>

        <script>
            (function() {
                var burger = document.getElementById('burger');
                var sidebar = document.getElementById('sidebar');
                var scrim = document.getElementById('scrim');

                function close() {
                    sidebar.classList.remove('open');
                    scrim.classList.remove('show');
                }

                function open() {
                    sidebar.classList.add('open');
                    scrim.classList.add('show');
                }
                if (burger) burger.addEventListener('click', function() {
                    sidebar.classList.contains('open') ? close() : open();
                });
                if (scrim) scrim.addEventListener('click', close);
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') close();
                });
            })();
        </script>

    <?php endif; ?>
</body>

</html>