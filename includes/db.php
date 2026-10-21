<?php
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'ksr_guru');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: 'JKRathod@406');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

if (!function_exists('getDBConnection')) {
    function getDBConnection(): PDO
    {
        static $pdo = null;
        if ($pdo !== null) {
            return $pdo;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        // TiDB Cloud and other remote hosts require SSL (port 4000 = TiDB default)
        if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
        }
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (Throwable $e) {
            // MySQL unavailable (Vercel serverless / local dev without MySQL).
            // Fall back to an in-memory SQLite database and bootstrap every
            // table that any part of the application may need.
            $pdo = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            _bootstrapSQLiteSchema($pdo);
            return $pdo;
        }
    }
}

/**
 * Creates all tables required by the application in the SQLite fallback DB.
 * Every CREATE TABLE uses IF NOT EXISTS so it is safe to call multiple times.
 */
if (!function_exists('_bootstrapSQLiteSchema')) {
    function _bootstrapSQLiteSchema(PDO $pdo): void
    {
        $pdo->exec('PRAGMA journal_mode=WAL;');
        $pdo->exec('PRAGMA foreign_keys=ON;');

        $tables = [
            // ── Core user / patient tables ─────────────────────────────────
            "CREATE TABLE IF NOT EXISTS users (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL DEFAULT '',
                email       TEXT    UNIQUE,
                phone       TEXT,
                mobile      TEXT,
                password    TEXT,
                role        TEXT    NOT NULL DEFAULT 'user',
                ksr_id      TEXT,
                account_status TEXT DEFAULT 'ACTIVE',
                created_at  TEXT    DEFAULT (datetime('now'))
            )",

            "CREATE TABLE IF NOT EXISTS patients (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL DEFAULT '',
                email       TEXT    UNIQUE,
                phone       TEXT,
                mobile      TEXT,
                password    TEXT,
                dob         TEXT,
                gender      TEXT,
                address     TEXT,
                ksr_id      TEXT,
                account_status TEXT DEFAULT 'ACTIVE',
                created_at  TEXT DEFAULT (datetime('now'))
            )",

            // ── Telehealth: doctors ─────────────────────────────────────────
            "CREATE TABLE IF NOT EXISTS doctors (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id             INTEGER,
                doctor_name         TEXT    NOT NULL DEFAULT '',
                specialization      TEXT,
                specialty           TEXT,
                experience_years    INTEGER DEFAULT 0,
                qualification       TEXT,
                verification_status TEXT    DEFAULT 'PENDING',
                status              TEXT    DEFAULT 'active',
                phone               TEXT,
                email               TEXT,
                fee                 REAL    DEFAULT 0,
                rating              REAL    DEFAULT 0,
                avatar              TEXT,
                about               TEXT,
                created_at          TEXT    DEFAULT (datetime('now'))
            )",

            // ── Telehealth: appointments ────────────────────────────────────
            "CREATE TABLE IF NOT EXISTS appointments (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id    INTEGER,
                doctor_id     INTEGER,
                patient_name  TEXT,
                doctor_name   TEXT,
                appointment_date TEXT,
                appointment_time TEXT,
                type          TEXT DEFAULT 'Video',
                reason        TEXT,
                status        TEXT DEFAULT 'scheduled',
                notes         TEXT,
                created_at    TEXT DEFAULT (datetime('now'))
            )",

            // ── Telehealth: prescriptions ───────────────────────────────────
            "CREATE TABLE IF NOT EXISTS prescriptions (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id          INTEGER,
                doctor_id           INTEGER,
                patient_name        TEXT,
                doctor_name         TEXT,
                diagnosis           TEXT,
                medicines           TEXT,
                notes               TEXT,
                verification_status TEXT DEFAULT 'PENDING',
                created_at          TEXT DEFAULT (datetime('now'))
            )",

            // ── Telehealth: consultations ───────────────────────────────────
            "CREATE TABLE IF NOT EXISTS consultations (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id  INTEGER,
                doctor_id   INTEGER,
                type        TEXT,
                status      TEXT DEFAULT 'scheduled',
                notes       TEXT,
                created_at  TEXT DEFAULT (datetime('now'))
            )",

            // ── E-commerce: products / orders / cart ───────────────────────
            "CREATE TABLE IF NOT EXISTS products (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL DEFAULT '',
                category    TEXT,
                price       REAL    DEFAULT 0,
                mrp         REAL    DEFAULT 0,
                stock       INTEGER DEFAULT 0,
                description TEXT,
                image       TEXT,
                created_at  TEXT DEFAULT (datetime('now'))
            )",

            "CREATE TABLE IF NOT EXISTS orders (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id        INTEGER,
                name           TEXT,
                email          TEXT,
                phone          TEXT,
                address        TEXT,
                total          REAL    DEFAULT 0,
                payment_method TEXT    DEFAULT 'COD',
                status         TEXT    DEFAULT 'pending',
                items          TEXT,
                created_at     TEXT DEFAULT (datetime('now'))
            )",

            // ── Patient portal auth tables ──────────────────────────────────
            "CREATE TABLE IF NOT EXISTS patient_captcha (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                token       TEXT    UNIQUE NOT NULL,
                answer_hash TEXT    NOT NULL,
                consumed    INTEGER DEFAULT 0,
                expires_at  TEXT    NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS patient_otps (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL,
                purpose     TEXT    NOT NULL,
                otp_hash    TEXT    NOT NULL,
                attempts    INTEGER DEFAULT 0,
                sends       INTEGER DEFAULT 1,
                consumed    INTEGER DEFAULT 0,
                created_at  TEXT    DEFAULT (datetime('now')),
                expires_at  TEXT    NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS patient_login_attempts (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier  TEXT    NOT NULL,
                ip          TEXT,
                attempt_at  TEXT    DEFAULT (datetime('now'))
            )",

            "CREATE TABLE IF NOT EXISTS patient_remember_tokens (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL,
                token_hash  TEXT    NOT NULL,
                expires_at  TEXT    NOT NULL,
                created_at  TEXT    DEFAULT (datetime('now'))
            )",

            // ── Pharmacy / inventory ────────────────────────────────────────
            "CREATE TABLE IF NOT EXISTS pharmacy_inventory (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL DEFAULT '',
                category    TEXT,
                stock       INTEGER DEFAULT 0,
                price       REAL    DEFAULT 0,
                expiry_date TEXT,
                created_at  TEXT DEFAULT (datetime('now'))
            )",

            // ── Notifications ───────────────────────────────────────────────
            "CREATE TABLE IF NOT EXISTS notifications (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER,
                type        TEXT,
                title       TEXT,
                message     TEXT,
                is_read     INTEGER DEFAULT 0,
                created_at  TEXT DEFAULT (datetime('now'))
            )",
        ];

        foreach ($tables as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // Non-fatal: log and continue
                error_log('[db.php SQLite bootstrap] ' . $e->getMessage());
            }
        }

        // Seed one default doctor so telehealth pages don't show empty state
        $hasDoctors = (int) $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
        if ($hasDoctors === 0) {
            $pdo->exec("INSERT INTO doctors
                (doctor_name, specialization, specialty, experience_years, qualification,
                 verification_status, status, fee, rating, about)
                VALUES
                ('Dr. D C Rathod', 'Neuro Care, Cardiology', 'Neuro Care, Cardiology', 3,
                 'MBBS, MD (Neuro Care & Cardiology)', 'VERIFIED', 'active', 300, 4.8,
                 'Dr. D C Rathod is a neuro care and cardiology physician with 3 years of clinical experience.')");
        }
    }
}