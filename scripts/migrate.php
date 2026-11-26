<?php
// ============================================================
// scripts/migrate.php — Idempotent schema migration runner
// CLI-only: php scripts/migrate.php
// Supports both MySQL and SQLite cleanly.
// ============================================================
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDBConnection();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$driver   = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$isSqlite = ($driver === 'sqlite');
$autoInc  = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
$bigAuto  = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT AUTO_INCREMENT PRIMARY KEY';
$tsNow    = $isSqlite ? "TEXT NOT NULL DEFAULT (datetime('now'))" : 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP';

echo "Running KSR GURU JI migrations on driver={$driver}...\n";

$statements = [
    'delivery_otps' => $isSqlite
        ? "CREATE TABLE IF NOT EXISTS delivery_otps (
            order_id    INTEGER PRIMARY KEY,
            otp_hash    TEXT NOT NULL,
            verified_at TEXT DEFAULT NULL,
            created_at  {$tsNow}
        )"
        : "CREATE TABLE IF NOT EXISTS delivery_otps (
            order_id    INT NOT NULL PRIMARY KEY,
            otp_hash    VARCHAR(255) NOT NULL,
            verified_at DATETIME DEFAULT NULL,
            created_at  {$tsNow}
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'coupons' => $isSqlite
        ? "CREATE TABLE IF NOT EXISTS coupons (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            code             TEXT NOT NULL UNIQUE,
            description      TEXT DEFAULT NULL,
            discount_type    TEXT NOT NULL DEFAULT 'percent',
            discount_value   REAL NOT NULL DEFAULT 0,
            min_order_amount REAL DEFAULT 0,
            min_order        REAL DEFAULT 0,
            max_discount     REAL DEFAULT NULL,
            usage_limit      INTEGER DEFAULT NULL,
            user_limit       INTEGER DEFAULT 1,
            used_count       INTEGER NOT NULL DEFAULT 0,
            start_date       TEXT DEFAULT NULL,
            end_date         TEXT DEFAULT NULL,
            expiry_date      TEXT DEFAULT NULL,
            status           TEXT NOT NULL DEFAULT 'active',
            created_at       {$tsNow}
        )"
        : "CREATE TABLE IF NOT EXISTS coupons (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            code             VARCHAR(50) NOT NULL UNIQUE,
            description      VARCHAR(255) DEFAULT NULL,
            discount_type    VARCHAR(20) NOT NULL DEFAULT 'percent',
            discount_value   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            min_order_amount DECIMAL(10,2) DEFAULT 0.00,
            min_order        DECIMAL(10,2) DEFAULT 0.00,
            max_discount     DECIMAL(10,2) DEFAULT NULL,
            usage_limit      INT DEFAULT NULL,
            user_limit       INT DEFAULT 1,
            used_count       INT NOT NULL DEFAULT 0,
            start_date       DATETIME DEFAULT NULL,
            end_date         DATETIME DEFAULT NULL,
            expiry_date      DATE DEFAULT NULL,
            status           VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at       {$tsNow}
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'coupon_usage' => $isSqlite
        ? "CREATE TABLE IF NOT EXISTS coupon_usage (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            coupon_code     TEXT NOT NULL,
            order_id        INTEGER NOT NULL,
            user_id         INTEGER NOT NULL DEFAULT 0,
            discount_amount REAL NOT NULL DEFAULT 0,
            used_at         {$tsNow},
            UNIQUE (coupon_code, order_id)
        )"
        : "CREATE TABLE IF NOT EXISTS coupon_usage (
            id              BIGINT AUTO_INCREMENT PRIMARY KEY,
            coupon_code     VARCHAR(64) NOT NULL,
            order_id        INT NOT NULL,
            user_id         INT NOT NULL DEFAULT 0,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            used_at         {$tsNow},
            UNIQUE KEY uq_coupon_order (coupon_code, order_id),
            INDEX idx_coupon_user (coupon_code, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'inventory_reservations' => $isSqlite
        ? "CREATE TABLE IF NOT EXISTS inventory_reservations (
            reservation_id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id     INTEGER NOT NULL,
            quantity       INTEGER NOT NULL,
            session_id     TEXT NOT NULL,
            expires_at     TEXT NOT NULL,
            created_at     {$tsNow}
        )"
        : "CREATE TABLE IF NOT EXISTS inventory_reservations (
            reservation_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id     INT NOT NULL,
            quantity       INT NOT NULL,
            session_id     VARCHAR(128) NOT NULL,
            expires_at     DATETIME NOT NULL,
            created_at     {$tsNow},
            INDEX idx_product (product_id),
            INDEX idx_session (session_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'stock_history' => $isSqlite
        ? "CREATE TABLE IF NOT EXISTS stock_history (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id   INTEGER NOT NULL,
            old_quantity INTEGER NOT NULL DEFAULT 0,
            new_quantity INTEGER NOT NULL DEFAULT 0,
            change_type  TEXT DEFAULT 'adjustment',
            note         TEXT,
            created_by   INTEGER,
            created_at   {$tsNow}
        )"
        : "CREATE TABLE IF NOT EXISTS stock_history (
            id           BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id   INT NOT NULL,
            old_quantity INT NOT NULL DEFAULT 0,
            new_quantity INT NOT NULL DEFAULT 0,
            change_type  VARCHAR(50) DEFAULT 'adjustment',
            note         VARCHAR(255) DEFAULT NULL,
            created_by   INT DEFAULT NULL,
            created_at   {$tsNow},
            INDEX idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'order_idempotency_keys' => $isSqlite
        ? "CREATE TABLE IF NOT EXISTS order_idempotency_keys (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            idempotency_key TEXT NOT NULL UNIQUE,
            order_id        INTEGER NOT NULL,
            response_json   TEXT NOT NULL,
            created_at      {$tsNow},
            expires_at      TEXT NOT NULL
        )"
        : "CREATE TABLE IF NOT EXISTS order_idempotency_keys (
            id              BIGINT AUTO_INCREMENT PRIMARY KEY,
            idempotency_key VARCHAR(128) NOT NULL UNIQUE,
            order_id        INT NOT NULL,
            response_json   MEDIUMTEXT NOT NULL,
            created_at      {$tsNow},
            expires_at      DATETIME NOT NULL,
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'customer_addresses' => $isSqlite
        ? "CREATE TABLE IF NOT EXISTS customer_addresses (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            label      TEXT NOT NULL DEFAULT 'Home',
            full_name  TEXT NOT NULL,
            phone      TEXT NOT NULL,
            address    TEXT NOT NULL,
            city       TEXT NOT NULL,
            state      TEXT NOT NULL DEFAULT 'Karnataka',
            pincode    TEXT NOT NULL,
            is_default INTEGER NOT NULL DEFAULT 0,
            created_at {$tsNow},
            updated_at {$tsNow}
        )"
        : "CREATE TABLE IF NOT EXISTS customer_addresses (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,
            label      VARCHAR(50) NOT NULL DEFAULT 'Home',
            full_name  VARCHAR(120) NOT NULL,
            phone      VARCHAR(15) NOT NULL,
            address    TEXT NOT NULL,
            city       VARCHAR(80) NOT NULL,
            state      VARCHAR(80) NOT NULL DEFAULT 'Karnataka',
            pincode    VARCHAR(10) NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at {$tsNow},
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'b2b_customers' => "CREATE TABLE IF NOT EXISTS b2b_customers (
        id            {$autoInc},
        user_id       INT NOT NULL,
        business_name VARCHAR(150) NOT NULL,
        gst_number    VARCHAR(20) NULL,
        contact_name  VARCHAR(100) NOT NULL,
        phone         VARCHAR(15) NOT NULL,
        email         VARCHAR(150) NULL,
        address       TEXT NOT NULL,
        city          VARCHAR(80) NOT NULL,
        state         VARCHAR(80) NOT NULL DEFAULT 'Karnataka',
        pincode       VARCHAR(10) NOT NULL,
        approved      " . ($isSqlite ? 'INTEGER' : 'TINYINT(1)') . " NOT NULL DEFAULT 0,
        created_at    {$tsNow}
    )",

    'b2b_orders' => "CREATE TABLE IF NOT EXISTS b2b_orders (
        id              {$autoInc},
        b2b_customer_id INT NOT NULL,
        user_id         INT NOT NULL,
        order_number    VARCHAR(30) NOT NULL UNIQUE,
        status          VARCHAR(30) NOT NULL DEFAULT 'pending',
        subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
        delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
        total           DECIMAL(12,2) NOT NULL DEFAULT 0,
        notes           TEXT NULL,
        created_at      {$tsNow}
    )",

    'b2b_order_items' => "CREATE TABLE IF NOT EXISTS b2b_order_items (
        id           {$autoInc},
        order_id     INT NOT NULL,
        product_id   INT NULL,
        product_name VARCHAR(200) NOT NULL,
        unit_price   DECIMAL(10,2) NOT NULL,
        qty          INT NOT NULL,
        total        DECIMAL(12,2) NOT NULL
    )",

    'b2b_price_tiers' => "CREATE TABLE IF NOT EXISTS b2b_price_tiers (
        id         {$autoInc},
        product_id INT NOT NULL,
        min_qty    INT NOT NULL DEFAULT 1,
        price      DECIMAL(10,2) NOT NULL,
        created_at {$tsNow}
    )",

    'account_deletion_requests' => $isSqlite
        ? "CREATE TABLE IF NOT EXISTS account_deletion_requests (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL,
            email        TEXT NULL,
            reason       TEXT NULL,
            status       TEXT NOT NULL DEFAULT 'pending',
            requested_at TEXT NOT NULL DEFAULT (datetime('now')),
            reviewed_by  INTEGER NULL,
            reviewed_at  TEXT NULL,
            admin_note   TEXT NULL
        )"
        : "CREATE TABLE IF NOT EXISTS account_deletion_requests (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            user_id      INT NOT NULL,
            email        VARCHAR(150) NULL,
            reason       TEXT NULL,
            status       VARCHAR(30) NOT NULL DEFAULT 'pending',
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_by  INT NULL,
            reviewed_at  DATETIME NULL,
            admin_note   TEXT NULL,
            INDEX idx_user (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$failed = 0;
foreach ($statements as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "  [OK] {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "  [ERR] {$name}: " . $e->getMessage() . "\n");
    }
}

// Idempotent column upgrades for existing tables created before this migration
try {
    if ($isSqlite) {
        $adrCols = $pdo->query("PRAGMA table_info(account_deletion_requests)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $adrAdditions = [
            'reviewed_by INTEGER NULL',
            'reviewed_at TEXT NULL',
            'admin_note TEXT NULL',
        ];
    } else {
        $adrCols = $pdo->query("SHOW COLUMNS FROM account_deletion_requests")->fetchAll(PDO::FETCH_COLUMN, 0);
        $adrAdditions = [
            'reviewed_by INT NULL',
            'reviewed_at DATETIME NULL',
            'admin_note TEXT NULL',
        ];
    }
    foreach ($adrAdditions as $colDef) {
        $colName = explode(' ', $colDef)[0];
        if (!empty($adrCols) && !in_array($colName, $adrCols, true)) {
            $pdo->exec("ALTER TABLE account_deletion_requests ADD COLUMN {$colDef}");
            echo "  [OK] account_deletion_requests.{$colName} added\n";
        }
    }
} catch (Throwable $e) {
    $failed++;
    fwrite(STDERR, "  [ERR] account_deletion_requests column upgrade: " . $e->getMessage() . "\n");
}

// Upgrade notifications & users.account_status on existing schemas if needed
try {
    if ($isSqlite) {
        $notifCols = $pdo->query("PRAGMA table_info(notifications)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $notifAdds = ['title TEXT', 'subject TEXT', 'link TEXT', 'read_at TEXT'];
    } else {
        $notifCols = $pdo->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN, 0);
        $notifAdds = ['title VARCHAR(200) NULL', 'subject VARCHAR(255) NULL', 'link VARCHAR(255) NULL', 'read_at DATETIME NULL'];
    }
    foreach ($notifAdds as $colDef) {
        $colName = explode(' ', $colDef)[0];
        if (!empty($notifCols) && !in_array($colName, $notifCols, true)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN {$colDef}");
            echo "  [OK] notifications.{$colName} added\n";
        }
    }
    if (!$isSqlite) {
        $uStatusCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'account_status'")->fetch(PDO::FETCH_ASSOC);
        $uType = strtoupper((string)($uStatusCol['Type'] ?? ''));
        if ($uType !== '' && str_starts_with($uType, 'ENUM(') && strpos($uType, "'DELETED'") === false) {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN account_status ENUM('PENDING_VERIFICATION','ACTIVE','SUSPENDED','LOCKED','DELETED') NOT NULL DEFAULT 'ACTIVE'");
            echo "  [OK] users.account_status enum extended with DELETED\n";
        }
    }
} catch (Throwable $e) {
    $failed++;
    fwrite(STDERR, "  [ERR] notifications/users column upgrade: " . $e->getMessage() . "\n");
}

if ($failed > 0) {
    exit(1);
}

echo "Migration completed successfully (" . count($statements) . " tables checked).\n";
