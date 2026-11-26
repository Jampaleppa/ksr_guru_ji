<?php
// ============================================================
// includes/coupons.php — Shared Coupon Validation & Usage Engine
// Supports both SQLite and MySQL, plus both coupon column conventions
// (database.sql vs admin/management.php).
// ============================================================
declare(strict_types=1);

if (!function_exists('ensureCouponTables')) {
    function ensureCouponTables(PDO $pdo): void
    {
        static $ensured = [];
        $key = spl_object_id($pdo);
        if (isset($ensured[$key])) {
            return;
        }
        $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

        $couponsDdl = $isSqlite
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
                created_at       TEXT DEFAULT (datetime('now'))
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
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $usageDdl = $isSqlite
            ? "CREATE TABLE IF NOT EXISTS coupon_usage (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                coupon_code     TEXT NOT NULL,
                order_id        INTEGER NOT NULL,
                user_id         INTEGER NOT NULL DEFAULT 0,
                discount_amount REAL NOT NULL DEFAULT 0,
                used_at         TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE (coupon_code, order_id)
            )"
            : "CREATE TABLE IF NOT EXISTS coupon_usage (
                id              BIGINT AUTO_INCREMENT PRIMARY KEY,
                coupon_code     VARCHAR(64) NOT NULL,
                order_id        INT NOT NULL,
                user_id         INT NOT NULL DEFAULT 0,
                discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                used_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_coupon_order (coupon_code, order_id),
                INDEX idx_coupon_user (coupon_code, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            $pdo->exec($couponsDdl);
            $pdo->exec($usageDdl);
            $ensured[$key] = true;
        } catch (Throwable $e) {
            error_log('ensureCouponTables error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ksrValidateCoupon')) {
    /**
     * Validate a coupon code against authoritative DB state and subtotal.
     *
     * @return array{
     *     valid: bool,
     *     error: string,
     *     message: string,
     *     discount: float,
     *     coupon: ?array
     * }
     */
    function ksrValidateCoupon(PDO $pdo, string $code, float $subtotal, ?int $userId = null): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return ['valid' => false, 'error' => 'Please enter a coupon code.', 'message' => '', 'discount' => 0.0, 'coupon' => null];
        }
        if ($subtotal <= 0) {
            return ['valid' => false, 'error' => 'Add items to your cart before applying a coupon.', 'message' => '', 'discount' => 0.0, 'coupon' => null];
        }

        ensureCouponTables($pdo);

        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE UPPER(code) = ? LIMIT 1");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            return ['valid' => false, 'error' => 'Invalid coupon code.', 'message' => '', 'discount' => 0.0, 'coupon' => null];
        }

        // Active check: handles 'active'/'inactive' (admin/management.php) AND '1'/'0' (database.sql) and optional is_active
        $rawStatus = strtolower(trim((string)($coupon['status'] ?? ($coupon['is_active'] ?? '1'))));
        $isActive = in_array($rawStatus, ['active', '1', 'true', 'yes'], true);
        if (isset($coupon['is_active']) && (int)$coupon['is_active'] === 0) {
            $isActive = false;
        }
        if (!$isActive) {
            return ['valid' => false, 'error' => 'This coupon is currently inactive.', 'message' => '', 'discount' => 0.0, 'coupon' => null];
        }

        $now = time();
        $startRaw = trim((string)($coupon['start_date'] ?? ''));
        if ($startRaw !== '' && $startRaw !== '0000-00-00' && $startRaw !== '0000-00-00 00:00:00') {
            $startTs = strtotime($startRaw);
            if ($startTs !== false && $startTs > $now) {
                return ['valid' => false, 'error' => 'This coupon is not valid yet.', 'message' => '', 'discount' => 0.0, 'coupon' => null];
            }
        }

        $endRaw = trim((string)($coupon['end_date'] ?? $coupon['expiry_date'] ?? $coupon['expires_at'] ?? ''));
        if ($endRaw !== '' && $endRaw !== '0000-00-00' && $endRaw !== '0000-00-00 00:00:00') {
            // Date-only values (YYYY-MM-DD) remain valid through end of day.
            $endTs = preg_match('/^\d{4}-\d{2}-\d{2}$/', $endRaw)
                ? strtotime($endRaw . ' 23:59:59')
                : strtotime($endRaw);
            if ($endTs !== false && $endTs < $now) {
                return ['valid' => false, 'error' => 'This coupon has expired.', 'message' => '', 'discount' => 0.0, 'coupon' => null];
            }
        }

        $usageLimit = isset($coupon['usage_limit']) && $coupon['usage_limit'] !== '' && $coupon['usage_limit'] !== null
            ? (int)$coupon['usage_limit']
            : 0;
        $usedCount = (int)($coupon['used_count'] ?? 0);
        if ($usageLimit > 0 && $usedCount >= $usageLimit) {
            return ['valid' => false, 'error' => 'This coupon usage limit has been reached.', 'message' => '', 'discount' => 0.0, 'coupon' => null];
        }

        $userLimit = isset($coupon['user_limit']) && $coupon['user_limit'] !== '' && $coupon['user_limit'] !== null
            ? (int)$coupon['user_limit']
            : 0;
        if ($userId !== null && $userId > 0 && $userLimit > 0) {
            try {
                $uStmt = $pdo->prepare("SELECT COUNT(*) FROM coupon_usage WHERE UPPER(coupon_code) = ? AND user_id = ?");
                $uStmt->execute([$code, $userId]);
                if ((int)$uStmt->fetchColumn() >= $userLimit) {
                    return ['valid' => false, 'error' => 'You have already used this coupon the maximum number of times.', 'message' => '', 'discount' => 0.0, 'coupon' => null];
                }
            } catch (Throwable $e) {
            }
        }

        $minOrder = (float)($coupon['min_order_amount'] ?? $coupon['min_order'] ?? 0);
        if ($minOrder > 0 && $subtotal < $minOrder) {
            return [
                'valid'    => false,
                'error'    => 'Minimum order of ₹' . number_format($minOrder, 0) . ' required for this coupon.',
                'message'  => '',
                'discount' => 0.0,
                'coupon'   => null,
            ];
        }

        $dtype = strtolower(trim((string)($coupon['discount_type'] ?? 'fixed')));
        $dval  = (float)($coupon['discount_value'] ?? 0);
        $maxD  = isset($coupon['max_discount']) && $coupon['max_discount'] !== '' && $coupon['max_discount'] !== null
            ? (float)$coupon['max_discount']
            : 0.0;

        if (in_array($dtype, ['percent', 'percentage'], true)) {
            $discount = round($subtotal * ($dval / 100), 2);
            if ($maxD > 0 && $discount > $maxD) {
                $discount = round($maxD, 2);
            }
            $msg = rtrim(rtrim(number_format($dval, 2, '.', ''), '0'), '.') . '% off applied';
        } else {
            $discount = round(min($dval, $subtotal), 2);
            $msg = '₹' . number_format($discount, 0) . ' off applied';
        }

        $discount = max(0.0, min($discount, $subtotal));

        return [
            'valid'    => true,
            'error'    => '',
            'message'  => $msg,
            'discount' => $discount,
            'coupon'   => [
                'id'             => (int)$coupon['id'],
                'code'           => (string)$coupon['code'],
                'discount_type'  => $dtype,
                'discount_value' => $dval,
                'discount'       => $discount,
            ],
        ];
    }
}

if (!function_exists('ksrRecordCouponUsage')) {
    function ksrRecordCouponUsage(PDO $pdo, string $code, int $orderId, ?int $userId = null, float $discountAmount = 0.0): void
    {
        $code = strtoupper(trim($code));
        if ($code === '' || $orderId <= 0) {
            return;
        }
        ensureCouponTables($pdo);
        $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
        $uid = (int)($userId ?? 0);

        $chk = $pdo->prepare("SELECT 1 FROM coupon_usage WHERE UPPER(coupon_code) = ? AND order_id = ? LIMIT 1");
        $chk->execute([$code, $orderId]);
        if ($chk->fetchColumn()) {
            return; // already recorded idempotently
        }

        $sql = $isSqlite
            ? "INSERT OR IGNORE INTO coupon_usage (coupon_code, order_id, user_id, discount_amount) VALUES (?, ?, ?, ?)"
            : "INSERT IGNORE INTO coupon_usage (coupon_code, order_id, user_id, discount_amount) VALUES (?, ?, ?, ?)";
        $ins = $pdo->prepare($sql);
        $ins->execute([$code, $orderId, $uid, round($discountAmount, 2)]);

        if ($ins->rowCount() > 0) {
            $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE UPPER(code) = ?")
                ->execute([$code]);
        }
    }
}
