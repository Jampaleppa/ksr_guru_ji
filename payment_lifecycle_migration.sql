-- ============================================================
-- KSR GURU JI — Payment Lifecycle Migration
-- Adds full payment state machine + audit trail + COD collection
-- + refund tracking to the existing `payments` table.
-- Safe to re-run: every ALTER/CREATE is guarded.
-- ============================================================

-- 1. Widen the payments.status ENUM to hold every lifecycle state.
ALTER TABLE payments
    MODIFY COLUMN status ENUM(
        'pending','success','failed','refunded',
        'initiated','processing','cancelled','expired',
        'refund_pending','refund_failed',
        'cod_pending','cod_collected'
    ) NOT NULL DEFAULT 'pending';

-- 2. Add gateway + lifecycle timestamp columns (idempotent via helper proc).
DROP PROCEDURE IF EXISTS ksr_add_payment_column;
DELIMITER //
CREATE PROCEDURE ksr_add_payment_column(
    IN p_col VARCHAR(64),
    IN p_ddl VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'payments'
          AND COLUMN_NAME = p_col
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE payments ADD COLUMN ', p_ddl);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL ksr_add_payment_column('gateway',             "gateway VARCHAR(50) NULL AFTER method");
CALL ksr_add_payment_column('gateway_order_id',    "gateway_order_id VARCHAR(100) NULL AFTER gateway");
CALL ksr_add_payment_column('gateway_payment_id',  "gateway_payment_id VARCHAR(100) NULL AFTER gateway_order_id");
CALL ksr_add_payment_column('gateway_signature',   "gateway_signature VARCHAR(255) NULL AFTER gateway_payment_id");
CALL ksr_add_payment_column('failure_code',        "failure_code VARCHAR(100) NULL AFTER status");
CALL ksr_add_payment_column('failure_reason',      "failure_reason TEXT NULL AFTER failure_code");
CALL ksr_add_payment_column('webhook_received_at', "webhook_received_at DATETIME NULL AFTER gateway_response");
CALL ksr_add_payment_column('confirmed_at',        "confirmed_at DATETIME NULL AFTER webhook_received_at");
CALL ksr_add_payment_column('failed_at',           "failed_at DATETIME NULL AFTER confirmed_at");
CALL ksr_add_payment_column('cancelled_at',        "cancelled_at DATETIME NULL AFTER failed_at");
CALL ksr_add_payment_column('expired_at',          "expired_at DATETIME NULL AFTER cancelled_at");
CALL ksr_add_payment_column('refund_status',       "refund_status VARCHAR(30) NULL AFTER expired_at");
CALL ksr_add_payment_column('refund_transaction_id',"refund_transaction_id VARCHAR(100) NULL AFTER refund_status");
CALL ksr_add_payment_column('refunded_at',         "refunded_at DATETIME NULL AFTER refund_transaction_id");
CALL ksr_add_payment_column('collected_amount',    "collected_amount DECIMAL(10,2) NULL AFTER refunded_at");
CALL ksr_add_payment_column('collected_by',        "collected_by VARCHAR(100) NULL AFTER collected_amount");
CALL ksr_add_payment_column('collected_at',        "collected_at DATETIME NULL AFTER collected_by");
CALL ksr_add_payment_column('collection_reference',"collection_reference VARCHAR(100) NULL AFTER collected_at");
CALL ksr_add_payment_column('updated_at',          "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER collection_reference");

DROP PROCEDURE IF EXISTS ksr_add_payment_column;

-- 3. Immutable payment event history (audit trail / timeline).
CREATE TABLE IF NOT EXISTS payment_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    payment_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NOT NULL,

    event_type VARCHAR(100) NOT NULL,

    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,

    gateway_event_id VARCHAR(150) NULL,
    gateway_payment_id VARCHAR(150) NULL,

    payload LONGTEXT NULL,

    source VARCHAR(30) NOT NULL DEFAULT 'system',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_payment_id (payment_id),
    INDEX idx_order_id (order_id),
    INDEX idx_event_type (event_type),
    INDEX idx_gateway_event_id (gateway_event_id),
    INDEX idx_gateway_payment_id (gateway_payment_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Refunds ledger (used by admin refund flow + webhook refund events).
CREATE TABLE IF NOT EXISTS refunds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NULL,

    refund_id VARCHAR(100) NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
    reason TEXT NULL,

    gateway_response LONGTEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,

    INDEX idx_order_id (order_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_refund_id (refund_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Backfill: give legacy paid records the new gateway columns.
UPDATE payments
SET gateway = CASE
        WHEN method = 'cod' THEN 'cod'
        ELSE 'razorpay'
    END
WHERE gateway IS NULL;

UPDATE payments
SET gateway_order_id = transaction_id
WHERE gateway_order_id IS NULL
  AND method <> 'cod'
  AND transaction_id IS NOT NULL;

UPDATE payments
SET status = 'cod_pending'
WHERE method = 'cod' AND status = 'pending';

UPDATE payments
SET status = 'success',
    confirmed_at = COALESCE(paid_at, NOW())
WHERE status = 'pending'
  AND paid_at IS NOT NULL;

-- Map legacy order-level enum values into the explicit lifecycle.
UPDATE payments
SET status = 'cod_collected'
WHERE status = 'cod_pending'
  AND order_id IN (SELECT o.id FROM orders o WHERE o.payment_status IN ('paid','cod_collected'));

SELECT 'payments lifecycle migration complete' AS result;

-- ============================================================
-- Follow-up: ensure payments.created_at exists.
-- The canonical `payments` table shipped without it, but the
-- dashboard, expiry worker and timelines all need a birth time.
-- Backfilled from updated_at / paid_at so ordering stays sane.
-- ============================================================

DROP PROCEDURE IF EXISTS ksr_add_payment_column2;
DELIMITER //
CREATE PROCEDURE ksr_add_payment_column2(
    IN p_col VARCHAR(64),
    IN p_ddl VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'payments'
          AND COLUMN_NAME = p_col
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE payments ADD COLUMN ', p_ddl);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL ksr_add_payment_column2(
    'created_at',
    "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status"
);

DROP PROCEDURE IF EXISTS ksr_add_payment_column2;

-- Backfill any rows whose created_at landed on the migration instant.
UPDATE payments
SET created_at = COALESCE(paid_at, updated_at, created_at)
WHERE created_at IS NULL
   OR created_at > NOW() - INTERVAL 1 MINUTE;

SELECT 'payments.created_at ensured' AS result;
