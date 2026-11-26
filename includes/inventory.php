<?php
// ============================================================
// includes/inventory.php — Inventory Reservations & Stock History
// Works uniformly across both MySQL and SQLite.
// ============================================================
declare(strict_types=1);

if (!function_exists('ensureInventoryReservationTables')) {
    function ensureInventoryReservationTables(PDO $pdo): void
    {
        static $ensured = [];
        $key = spl_object_id($pdo);
        if (isset($ensured[$key])) {
            return;
        }
        $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

        $resDdl = $isSqlite
            ? "CREATE TABLE IF NOT EXISTS inventory_reservations (
                reservation_id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id     INTEGER NOT NULL,
                quantity       INTEGER NOT NULL,
                session_id     TEXT NOT NULL,
                expires_at     TEXT NOT NULL,
                created_at     TEXT NOT NULL DEFAULT (datetime('now'))
            )"
            : "CREATE TABLE IF NOT EXISTS inventory_reservations (
                reservation_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                product_id     INT NOT NULL,
                quantity       INT NOT NULL,
                session_id     VARCHAR(128) NOT NULL,
                expires_at     DATETIME NOT NULL,
                created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_product (product_id),
                INDEX idx_session (session_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $shDdl = $isSqlite
            ? "CREATE TABLE IF NOT EXISTS stock_history (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id   INTEGER NOT NULL,
                old_quantity INTEGER NOT NULL DEFAULT 0,
                new_quantity INTEGER NOT NULL DEFAULT 0,
                change_type  TEXT DEFAULT 'adjustment',
                note         TEXT,
                created_by   INTEGER,
                created_at   TEXT DEFAULT (datetime('now', 'localtime'))
            )"
            : "CREATE TABLE IF NOT EXISTS stock_history (
                id           BIGINT AUTO_INCREMENT PRIMARY KEY,
                product_id   INT NOT NULL,
                old_quantity INT NOT NULL DEFAULT 0,
                new_quantity INT NOT NULL DEFAULT 0,
                change_type  VARCHAR(50) DEFAULT 'adjustment',
                note         VARCHAR(255) DEFAULT NULL,
                created_by   INT DEFAULT NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        try {
            $pdo->exec($resDdl);
            $pdo->exec($shDdl);
            $ensured[$key] = true;
        } catch (Throwable $e) {
            error_log('ensureInventoryReservationTables error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ksrPurgeExpiredReservations')) {
    function ksrPurgeExpiredReservations(PDO $pdo): int
    {
        ensureInventoryReservationTables($pdo);
        $nowSql = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("DELETE FROM inventory_reservations WHERE expires_at <= ?");
        $stmt->execute([$nowSql]);
        return $stmt->rowCount();
    }
}

if (!function_exists('ksrGetReservedQuantity')) {
    /**
     * Sum active reservations held by OTHER sessions for a given product.
     */
    function ksrGetReservedQuantity(PDO $pdo, int $productId, string $excludeSessionId = ''): int
    {
        if ($productId <= 0) {
            return 0;
        }
        ensureInventoryReservationTables($pdo);
        $nowSql = date('Y-m-d H:i:s');
        if ($excludeSessionId !== '') {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(quantity), 0)
                   FROM inventory_reservations
                  WHERE product_id = ? AND expires_at > ? AND session_id != ?"
            );
            $stmt->execute([$productId, $nowSql, $excludeSessionId]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(quantity), 0)
                   FROM inventory_reservations
                  WHERE product_id = ? AND expires_at > ?"
            );
            $stmt->execute([$productId, $nowSql]);
        }
        return max(0, (int)$stmt->fetchColumn());
    }
}

if (!function_exists('ksrReserveProductStock')) {
    function ksrReserveProductStock(PDO $pdo, int $productId, int $qty, string $sessionId, int $ttlSeconds = 600): void
    {
        if ($productId <= 0 || $qty <= 0 || $sessionId === '') {
            return;
        }
        ensureInventoryReservationTables($pdo);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $pdo->prepare("DELETE FROM inventory_reservations WHERE product_id = ? AND session_id = ?")
            ->execute([$productId, $sessionId]);
        $pdo->prepare(
            "INSERT INTO inventory_reservations (product_id, quantity, session_id, expires_at)
             VALUES (?, ?, ?, ?)"
        )->execute([$productId, $qty, $sessionId, $expiresAt]);
    }
}

if (!function_exists('ksrReleaseSessionReservations')) {
    function ksrReleaseSessionReservations(PDO $pdo, string $sessionId): void
    {
        if ($sessionId === '') {
            return;
        }
        ensureInventoryReservationTables($pdo);
        $pdo->prepare("DELETE FROM inventory_reservations WHERE session_id = ?")
            ->execute([$sessionId]);
    }
}

if (!function_exists('ksrRecordStockHistory')) {
    function ksrRecordStockHistory(
        PDO $pdo,
        int $productId,
        int $oldQty,
        int $newQty,
        string $changeType = 'order',
        string $note = '',
        ?int $createdBy = null
    ): void {
        if ($productId <= 0) {
            return;
        }
        ensureInventoryReservationTables($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO stock_history (product_id, old_quantity, new_quantity, change_type, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$productId, $oldQty, $newQty, $changeType, $note, $createdBy]);
    }
}
