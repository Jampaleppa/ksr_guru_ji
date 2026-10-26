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
            // Fall back to persistent SQLite database on disk and bootstrap schema.
            $sqliteDir = is_writable(__DIR__ . '/..') ? (__DIR__ . '/..') : sys_get_temp_dir();
            $sqliteFile = $sqliteDir . '/ksr_local_database.sqlite';
            $pdo = new PDO('sqlite:' . $sqliteFile, null, null, [
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
                address     TEXT,
                role        TEXT    NOT NULL DEFAULT 'user',
                ksr_id      TEXT,
                account_status TEXT DEFAULT 'ACTIVE',
                verified_at TEXT,
                failed_logins INTEGER DEFAULT 0,
                last_login_at TEXT,
                created_at  TEXT    DEFAULT (datetime('now', 'localtime'))
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
                channel     TEXT    DEFAULT 'whatsapp',
                destination TEXT,
                code_hash   TEXT,
                otp_hash    TEXT,
                purpose     TEXT    NOT NULL,
                attempts    INTEGER DEFAULT 0,
                sends       INTEGER DEFAULT 1,
                is_used     INTEGER DEFAULT 0,
                consumed    INTEGER DEFAULT 0,
                created_at  TEXT    DEFAULT (datetime('now')),
                expires_at  TEXT    NOT NULL,
                verified_at TEXT
            )",

            "CREATE TABLE IF NOT EXISTS patient_login_attempts (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier      TEXT    NOT NULL,
                ip              TEXT,
                ip_address      TEXT,
                user_agent      TEXT,
                attempt_type    TEXT    DEFAULT 'password',
                was_successful  INTEGER DEFAULT 0,
                attempt_at      TEXT    DEFAULT (datetime('now')),
                attempted_at    TEXT    DEFAULT (datetime('now'))
            )",

            "CREATE TABLE IF NOT EXISTS patient_remember_tokens (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id        INTEGER NOT NULL,
                selector       TEXT    UNIQUE,
                validator_hash TEXT,
                token_hash     TEXT,
                user_agent     TEXT,
                expires_at     TEXT    NOT NULL,
                last_used_at   TEXT,
                created_at     TEXT    DEFAULT (datetime('now', 'localtime'))
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

            // ── Suggestions: Products & Medical ──────────────────────────────
            "CREATE TABLE IF NOT EXISTS suggestions_products (
                id          TEXT PRIMARY KEY,
                user_id     INTEGER NULL,
                name        TEXT NOT NULL,
                price       REAL NOT NULL,
                unit        TEXT NOT NULL,
                qty         INTEGER NOT NULL DEFAULT 1,
                note        TEXT,
                payment     TEXT,
                payment_status TEXT DEFAULT 'Paid',
                upi_id      TEXT,
                image_base64 TEXT,
                created_at  TEXT DEFAULT (datetime('now')),
                updated_at  TEXT DEFAULT (datetime('now'))
            )",

            "CREATE TABLE IF NOT EXISTS suggestions_medical (
                id          TEXT PRIMARY KEY,
                user_id     INTEGER NULL,
                patient_name TEXT NOT NULL,
                age         INTEGER NOT NULL,
                gender      TEXT NOT NULL,
                shop_name   TEXT NOT NULL,
                symptoms    TEXT,
                suggested_meds TEXT,
                diagnosis   TEXT,
                date        TEXT,
                doctor_name TEXT,
                suggestion  TEXT NOT NULL,
                payment     TEXT,
                payment_status TEXT DEFAULT 'Paid',
                upi_id      TEXT,
                prescription_image TEXT,
                resolved    INTEGER DEFAULT 0,
                created_at  TEXT DEFAULT (datetime('now')),
                updated_at  TEXT DEFAULT (datetime('now'))
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

        // Migrate missing columns on existing patient tables
        try {
            $otpCols = $pdo->query("PRAGMA table_info(patient_otps)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('code_hash', $otpCols, true)) {
                try { $pdo->exec("ALTER TABLE patient_otps ADD COLUMN code_hash TEXT"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_otps ADD COLUMN channel TEXT DEFAULT 'whatsapp'"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_otps ADD COLUMN destination TEXT"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_otps ADD COLUMN is_used INTEGER DEFAULT 0"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_otps ADD COLUMN verified_at TEXT"); } catch (Throwable $e) {}
            }
            $attCols = $pdo->query("PRAGMA table_info(patient_login_attempts)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('was_successful', $attCols, true)) {
                try { $pdo->exec("ALTER TABLE patient_login_attempts ADD COLUMN was_successful INTEGER DEFAULT 0"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_login_attempts ADD COLUMN attempted_at TEXT"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_login_attempts ADD COLUMN ip_address TEXT"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_login_attempts ADD COLUMN user_agent TEXT"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_login_attempts ADD COLUMN attempt_type TEXT DEFAULT 'password'"); } catch (Throwable $e) {}
            }
            $remCols = $pdo->query("PRAGMA table_info(patient_remember_tokens)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('selector', $remCols, true)) {
                try { $pdo->exec("ALTER TABLE patient_remember_tokens ADD COLUMN selector TEXT"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_remember_tokens ADD COLUMN validator_hash TEXT"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_remember_tokens ADD COLUMN user_agent TEXT"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE patient_remember_tokens ADD COLUMN last_used_at TEXT"); } catch (Throwable $e) {}
            }
            $uCols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('verified_at', $uCols, true)) {
                try { $pdo->exec("ALTER TABLE users ADD COLUMN verified_at TEXT"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE users ADD COLUMN failed_logins INTEGER DEFAULT 0"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE users ADD COLUMN last_login_at TEXT"); } catch (Throwable $e) {}
            }
            if (!in_array('address', $uCols, true)) {
                try { $pdo->exec("ALTER TABLE users ADD COLUMN address TEXT"); } catch (Throwable $e) {}
            }
            if (!in_array('mobile', $uCols, true)) {
                try { $pdo->exec("ALTER TABLE users ADD COLUMN mobile TEXT"); } catch (Throwable $e) {}
            }
            if (!in_array('account_status', $uCols, true)) {
                try { $pdo->exec("ALTER TABLE users ADD COLUMN account_status TEXT DEFAULT 'PENDING_VERIFICATION'"); } catch (Throwable $e) {}
            }
        } catch (Throwable $e) {}

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
        $hasUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
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

        // Seed doctor user accounts for Dr. Shivukumar Rathod & Dr. D C Rathod
        $defaultDocPassword = password_hash('Doctor@123', PASSWORD_DEFAULT);

        // 1. Dr. Shivukumar Rathod (User & Doctor linkage)
        $shivUser = $pdo->query("SELECT id FROM users WHERE role = 'doctor' AND (name LIKE '%Shivukumar%' OR email = 'drshivukumar@ksrguru.com') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$shivUser) {
            $pdo->prepare("INSERT INTO users (name, email, phone, mobile, role, password, account_status)
                           VALUES (?, ?, ?, ?, 'doctor', ?, 'ACTIVE')")
                ->execute(['Dr. Shivukumar Rathod', 'drshivukumar@ksrguru.com', '6362079192', '6362079192', $defaultDocPassword]);
            $shivUserId = (int)$pdo->lastInsertId();
        } else {
            $shivUserId = (int)$shivUser['id'];
        }
        $pdo->prepare("UPDATE doctors SET user_id = ?, phone = '6362079192' WHERE doctor_name LIKE '%Shivukumar%'")
            ->execute([$shivUserId]);

        // 2. Dr. D C Rathod (User & Doctor linkage)
        $dcUser = $pdo->query("SELECT id FROM users WHERE role = 'doctor' AND (name LIKE '%D C Rathod%' OR email = 'drdcrathod@ksrguru.com') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$dcUser) {
            $pdo->prepare("INSERT INTO users (name, email, phone, mobile, role, password, account_status)
                           VALUES (?, ?, ?, ?, 'doctor', ?, 'ACTIVE')")
                ->execute(['Dr. D C Rathod', 'drdcrathod@ksrguru.com', '6362079192', '6362079192', $defaultDocPassword]);
            $dcUserId = (int)$pdo->lastInsertId();
        } else {
            $dcUserId = (int)$dcUser['id'];
        }
        $pdo->prepare("UPDATE doctors SET user_id = ?, phone = '6362079192' WHERE doctor_name LIKE '%D C Rathod%'")
            ->execute([$dcUserId]);
    }
}