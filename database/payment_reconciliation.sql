-- ============================================================
-- KSR GURU JI PAY — Schema Reconciliation Plan
-- payment_reconciliation.sql
--
-- PURPOSE: Bridge the legacy `payments` table (used by the
--   existing Razorpay/checkout.php path) with the new
--   `payment_transactions` table (KSR PAY engine).
--
-- STRATEGY CHOSEN: Option A — Keep Both Tables
--   • `payments`             — legacy Razorpay path
--   • `payment_transactions` — KSR PAY engine (all new flows)
--   • `payment_state.php` functions that reference `payments`
--     remain untouched; they serve the legacy checkout path.
--
-- COMPATIBILITY: MySQL 5.7+, MySQL 8.0+, MariaDB 10.2+, TiDB
--   Uses INFORMATION_SCHEMA guards instead of ADD COLUMN IF NOT EXISTS
--   so it executes cleanly on Oracle MySQL 5.7 / 8.0 without syntax errors.
--
-- RUN ORDER (after payment_schema.sql + payment_migration.sql):
--   mysql -u root -p ksr_guru < database/payment_reconciliation.sql
-- ============================================================

SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- ============================================================
-- SECTION 1: Confirm both tables exist
-- ============================================================
SELECT 'Checking payments table...' AS step;
SELECT COUNT(*) AS legacy_payments_count FROM `payments`;

SELECT 'Checking payment_transactions table...' AS step;
SELECT COUNT(*) AS new_transactions_count FROM `payment_transactions`;

-- ============================================================
-- SECTION 2: Conditionally add legacy_payment_id column + index
--   to payment_transactions (MySQL 5.7/8.0 & MariaDB safe)
-- ============================================================
SELECT 'Ensuring payment_transactions.legacy_payment_id exists...' AS step;

SET @col_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'payment_transactions'
       AND COLUMN_NAME  = 'legacy_payment_id'
);
SET @sql_add_col := IF(
    @col_exists = 0,
    'ALTER TABLE `payment_transactions` ADD COLUMN `legacy_payment_id` INT(11) DEFAULT NULL COMMENT ''FK to legacy payments.id for Razorpay-path orders''',
    'SELECT ''legacy_payment_id already exists'' AS info'
);
PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

SET @idx_pt_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'payment_transactions'
       AND INDEX_NAME   = 'idx_pt_legacy_payment'
);
SET @sql_add_idx_pt := IF(
    @idx_pt_exists = 0,
    'ALTER TABLE `payment_transactions` ADD INDEX `idx_pt_legacy_payment` (`legacy_payment_id`)',
    'SELECT ''idx_pt_legacy_payment already exists'' AS info'
);
PREPARE stmt_add_idx_pt FROM @sql_add_idx_pt;
EXECUTE stmt_add_idx_pt;
DEALLOCATE PREPARE stmt_add_idx_pt;

-- ============================================================
-- SECTION 3: Conditionally add payment_engine column + index
--   to legacy payments table (MySQL 5.7/8.0 & MariaDB safe)
-- ============================================================
SELECT 'Ensuring payments.payment_engine exists...' AS step;

SET @pe_col_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'payments'
       AND COLUMN_NAME  = 'payment_engine'
);
SET @sql_add_pe := IF(
    @pe_col_exists = 0,
    'ALTER TABLE `payments` ADD COLUMN `payment_engine` VARCHAR(20) DEFAULT ''razorpay'' COMMENT ''razorpay = legacy path, ksr_pay = new engine''',
    'SELECT ''payment_engine already exists'' AS info'
);
PREPARE stmt_add_pe FROM @sql_add_pe;
EXECUTE stmt_add_pe;
DEALLOCATE PREPARE stmt_add_pe;

SET @idx_pay_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'payments'
       AND INDEX_NAME   = 'idx_pay_engine'
);
SET @sql_add_idx_pay := IF(
    @idx_pay_exists = 0,
    'ALTER TABLE `payments` ADD INDEX `idx_pay_engine` (`payment_engine`)',
    'SELECT ''idx_pay_engine already exists'' AS info'
);
PREPARE stmt_add_idx_pay FROM @sql_add_idx_pay;
EXECUTE stmt_add_idx_pay;
DEALLOCATE PREPARE stmt_add_idx_pay;

-- ============================================================
-- SECTION 4: Diagnostic views (non-destructive)
-- ============================================================
SELECT 'Creating reconciliation views...' AS step;

CREATE OR REPLACE VIEW `v_unified_payments` AS
    SELECT
        'ksr_pay'                   AS engine,
        pt.id                       AS engine_id,
        pt.payment_id               AS payment_ref,
        pt.order_id,
        pt.amount_paise             AS amount_paise,
        NULL                        AS amount_rupees,
        pt.method,
        pt.status,
        pt.created_at,
        pt.verified_at              AS paid_at,
        pt.bank_confirmed_ref       AS gateway_ref,
        pt.legacy_payment_id
    FROM `payment_transactions` pt
  UNION ALL
    SELECT
        'razorpay'                  AS engine,
        p.id                        AS engine_id,
        p.gateway_payment_id        AS payment_ref,
        p.order_id,
        NULL                        AS amount_paise,
        p.amount                    AS amount_rupees,
        p.method,
        p.status,
        p.created_at,
        p.paid_at,
        p.gateway_payment_id        AS gateway_ref,
        NULL                        AS legacy_payment_id
    FROM `payments` p;

CREATE OR REPLACE VIEW `v_orders_without_payment` AS
    SELECT
        o.id          AS order_id,
        o.order_number,
        o.total       AS order_total,
        o.status      AS order_status,
        o.created_at
    FROM `orders` o
    WHERE
        NOT EXISTS (SELECT 1 FROM `payment_transactions` pt WHERE pt.order_id = o.id)
        AND
        NOT EXISTS (SELECT 1 FROM `payments` p WHERE p.order_id = o.id);

CREATE OR REPLACE VIEW `v_orders_dual_payment` AS
    SELECT
        o.id                        AS order_id,
        o.order_number,
        COUNT(DISTINCT pt.id)       AS ksr_pay_count,
        COUNT(DISTINCT p.id)        AS razorpay_count,
        COALESCE(SUM(pt.amount_paise), 0) AS ksr_total_paise,
        COALESCE(SUM(p.amount), 0)  AS razorpay_total_rupees
    FROM `orders` o
    LEFT JOIN `payment_transactions` pt ON pt.order_id = o.id
    LEFT JOIN `payments` p              ON p.order_id = o.id
    GROUP BY o.id, o.order_number
    HAVING ksr_pay_count > 0 AND razorpay_count > 0;

-- ============================================================
-- SECTION 5: Verification queries
-- ============================================================
SELECT 'Verification: unified payment count' AS check_name,
       COUNT(*) AS total_rows FROM `v_unified_payments`;

SELECT 'Verification: orphaned orders' AS check_name,
       COUNT(*) AS orphan_count FROM `v_orders_without_payment`;

SELECT 'Verification: dual-engine orders (investigate if > 0)' AS check_name,
       COUNT(*) AS dual_count FROM `v_orders_dual_payment`;

SELECT 'Verification: legacy_payment_id column' AS check_name,
       COUNT(*) AS rows_with_legacy_link
  FROM `payment_transactions` WHERE `legacy_payment_id` IS NOT NULL;

SELECT 'payment_reconciliation.sql complete — Option A applied.' AS result;
