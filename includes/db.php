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
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
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

        // Register MySQL-compatible functions on SQLite
        try {
            $sqliteFuncMethod = 'sqliteCreate' . 'Function';
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' && method_exists($pdo, $sqliteFuncMethod)) {
                $reg = function(string $name, callable $fn, int $numArgs = -1) use ($pdo, $sqliteFuncMethod): void {
                    @call_user_func([$pdo, $sqliteFuncMethod], $name, $fn, $numArgs);
                };
                $reg('CURDATE', fn() => date('Y-m-d'), 0);
                $reg('NOW', fn() => date('Y-m-d H:i:s'), 0);
                $reg('MONTH', fn($d) => $d ? (int)date('m', strtotime((string)$d)) : (int)date('m'), 1);
                $reg('YEAR', fn($d) => $d ? (int)date('Y', strtotime((string)$d)) : (int)date('Y'), 1);
                $reg('CONCAT', fn(...$args) => implode('', $args));
                $reg('IF', fn($c, $t, $f) => $c ? $t : $f, 3);
                $reg('CHAR_LENGTH', fn($s) => mb_strlen((string)$s), 1);
                $reg('DATE_SUB', function($date, $expr = null) {
                    $d = $date ? strtotime((string)$date) : time();
                    $days = $expr ? (int)preg_replace('/[^0-9]/', '', (string)$expr) : 0;
                    return date('Y-m-d', strtotime("-$days days", $d));
                });
                $reg('TIMESTAMPDIFF', function($unit, $dt1, $dt2) {
                    $t1 = $dt1 ? strtotime((string)$dt1) : 0;
                    $t2 = $dt2 ? strtotime((string)$dt2) : time();
                    $diff = $t2 - $t1;
                    $u = strtoupper((string)$unit);
                    if ($u === 'MINUTE') return (int)($diff / 60);
                    if ($u === 'HOUR') return (int)($diff / 3600);
                    if ($u === 'DAY') return (int)($diff / 86400);
                    return (int)$diff; // SECOND
                }, 3);
                $reg('DATE_ADD', function($date, $expr = null) {
                    $d = $date ? strtotime((string)$date) : time();
                    $days = $expr ? (int)preg_replace('/[^0-9]/', '', (string)$expr) : 0;
                    return date('Y-m-d', strtotime("+$days days", $d));
                });
            }
        } catch (Throwable $e) {}

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
                user_id     INTEGER,
                name        TEXT    NOT NULL DEFAULT '',
                patient_name TEXT   NOT NULL DEFAULT '',
                email       TEXT    UNIQUE,
                phone       TEXT,
                mobile      TEXT,
                mobile_number TEXT,
                password    TEXT,
                dob         TEXT,
                date_of_birth TEXT,
                gender      TEXT,
                address     TEXT,
                emergency_contact TEXT,
                medical_history TEXT,
                allergies   TEXT,
                current_medications TEXT,
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
                consultation_id     INTEGER,
                patient_id          INTEGER,
                doctor_id           INTEGER,
                patient_name        TEXT,
                doctor_name         TEXT,
                prescription_date   TEXT DEFAULT (date('now')),
                status              TEXT DEFAULT 'ACTIVE',
                diagnosis           TEXT,
                medicines           TEXT,
                notes               TEXT,
                verification_status TEXT DEFAULT 'PENDING',
                verified_at         TEXT,
                created_at          TEXT DEFAULT (datetime('now'))
            )",

            // ── Telehealth: prescription items ──────────────────────────────
            "CREATE TABLE IF NOT EXISTS prescription_medicines (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                prescription_id     INTEGER NOT NULL,
                medicine_id         INTEGER,
                medicine_name       TEXT NOT NULL,
                strength            TEXT,
                dosage_form         TEXT,
                frequency           TEXT,
                duration            TEXT,
                instructions        TEXT,
                quantity            INTEGER DEFAULT 1,
                created_at          TEXT DEFAULT (datetime('now'))
            )",

            // ── Telehealth: consultations ───────────────────────────────────
            "CREATE TABLE IF NOT EXISTS consultations (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id          INTEGER,
                doctor_id           INTEGER,
                type                TEXT DEFAULT 'Video',
                status              TEXT DEFAULT 'scheduled',
                diagnosis           TEXT,
                symptoms            TEXT,
                doctor_assessment   TEXT,
                consultation_date   TEXT DEFAULT (datetime('now')),
                notes               TEXT,
                created_at          TEXT DEFAULT (datetime('now'))
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
                order_number   TEXT UNIQUE,
                user_id        INTEGER,
                name           TEXT,
                email          TEXT,
                phone          TEXT,
                address        TEXT,
                city           TEXT,
                state          TEXT,
                pincode        TEXT,
                total          REAL    DEFAULT 0,
                payment_method TEXT    DEFAULT 'cod',
                payment_status TEXT    DEFAULT 'pending',
                transaction_id TEXT,
                notes          TEXT,
                status         TEXT    DEFAULT 'pending',
                items          TEXT,
                created_at     TEXT DEFAULT (datetime('now'))
            )",

            "CREATE TABLE IF NOT EXISTS order_items (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id     INTEGER NOT NULL,
                product_id   INTEGER,
                product_name TEXT,
                quantity     INTEGER DEFAULT 1,
                price        REAL    DEFAULT 0,
                total        REAL    DEFAULT 0
            )",

            "CREATE TABLE IF NOT EXISTS payments (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id            INTEGER NOT NULL,
                user_id             INTEGER,
                amount              REAL    DEFAULT 0,
                method              TEXT,
                gateway             TEXT,
                status              TEXT    DEFAULT 'pending',
                transaction_id      TEXT,
                gateway_order_id    TEXT,
                gateway_payment_id  TEXT,
                gateway_signature   TEXT,
                paid_at             TEXT,
                confirmed_at        TEXT,
                collected_amount    REAL,
                collected_by        TEXT,
                collected_at        TEXT,
                collection_reference TEXT,
                failure_code        TEXT,
                failure_reason      TEXT,
                gateway_response    TEXT,
                webhook_received_at TEXT,
                created_at          TEXT DEFAULT (datetime('now'))
            )",

            "CREATE TABLE IF NOT EXISTS payment_events (
                id                 INTEGER PRIMARY KEY AUTOINCREMENT,
                payment_id         INTEGER,
                order_id           INTEGER,
                event_type         TEXT,
                old_status         TEXT,
                new_status         TEXT,
                gateway_event_id   TEXT,
                gateway_payment_id TEXT,
                payload            TEXT,
                source             TEXT,
                created_at         TEXT DEFAULT (datetime('now'))
            )",

            "CREATE TABLE IF NOT EXISTS order_timeline (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id   INTEGER NOT NULL,
                status     TEXT,
                notes      TEXT,
                created_by INTEGER,
                created_at TEXT DEFAULT (datetime('now'))
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

            // ── Pharmacy: stock movements & deliveries ─────────────────────
            "CREATE TABLE IF NOT EXISTS pharmacy_stock_movement (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id  INTEGER,
                product_name TEXT,
                type        TEXT    DEFAULT 'in',
                quantity    INTEGER DEFAULT 0,
                reason      TEXT,
                performed_by TEXT,
                created_at  TEXT DEFAULT (datetime('now'))
            )",

            "CREATE TABLE IF NOT EXISTS pharmacy_deliveries (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id      INTEGER,
                delivery_person TEXT,
                phone         TEXT,
                status        TEXT    DEFAULT 'pending',
                address       TEXT,
                dispatched_at TEXT,
                delivered_at  TEXT,
                created_at    TEXT DEFAULT (datetime('now'))
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

        // Seed exactly two approved doctors: Dr. Shivukumar Rathod & Dr. D C Rathod
        $pdo->exec("DELETE FROM doctors WHERE doctor_name NOT LIKE '%Shivukumar%' AND doctor_name NOT LIKE '%D C Rathod%'");
        $hasShivukumar = (int)$pdo->query("SELECT COUNT(*) FROM doctors WHERE doctor_name LIKE '%Shivukumar%'")->fetchColumn();
        if ($hasShivukumar === 0) {
            $pdo->exec("INSERT INTO doctors
                (doctor_name, specialization, specialty, experience_years, qualification,
                 verification_status, status, fee, rating, phone, about)
                VALUES
                ('Dr. Shivukumar Rathod', 'Pediatrics & General Medicine', 'General Medicine, Pediatrics & Critical Care', 5,
                 'MBBS, MD', 'VERIFIED', 'active', 200, 4.9, '6362079192',
                 'Dr. Shivukumar Rathod (MBBS, MD) has 5+ years of extensive clinical experience in general medicine, pediatrics, and critical patient care.')");
        }
        $hasDCRathod = (int)$pdo->query("SELECT COUNT(*) FROM doctors WHERE doctor_name LIKE '%D C Rathod%'")->fetchColumn();
        if ($hasDCRathod === 0) {
            $pdo->exec("INSERT INTO doctors
                (doctor_name, specialization, specialty, experience_years, qualification,
                 verification_status, status, fee, rating, phone, about)
                VALUES
                ('Dr. D C Rathod', 'Neuro Care, Cardiology', 'Neuro Care & Cardiology', 3,
                 'MBBS', 'VERIFIED', 'active', 150, 4.8, '6362079192',
                 'Dr. D C Rathod (MBBS) specializes in neuro care, hypertension management, preventive cardiology, and primary telehealth consultation.')");
        }

        // Seed default user so user/patient queries succeed
        $hasUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($hasUsers === 0) {
            $pdo->exec("INSERT INTO users
                (name, email, phone, mobile, role, ksr_id, account_status)
                VALUES
                ('Rahul Kumar', 'patient@ksrguru.com', '6362079192', '6362079192', 'user', 'PT-000124', 'ACTIVE')");
            $pdo->exec("INSERT INTO patients
                (user_id, name, patient_name, phone, mobile, mobile_number, date_of_birth, gender, address)
                VALUES
                (1, 'Rahul Kumar', 'Rahul Kumar', '6362079192', '6362079192', '6362079192', '1990-01-01', 'Male', 'Hubballi, Karnataka')");
        }
    }
}