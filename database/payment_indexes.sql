-- ============================================================
-- KSR GURU JI PAY — Supplemental Indexes
-- Apply AFTER payment_schema.sql AND payment_migration.sql
--
-- Compatible with MySQL 5.7+, MySQL 8.0+, MariaDB 10.2+, TiDB.
-- Uses INFORMATION_SCHEMA guards so re-running never errors and
-- Oracle MySQL 5.7/8.0 does not reject `ADD INDEX IF NOT EXISTS`.
-- ============================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `ksr_add_index_if_missing`$$
CREATE PROCEDURE `ksr_add_index_if_missing`(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_cols  VARCHAR(255)
)
BEGIN
    DECLARE v_exists INT DEFAULT 0;
    SELECT COUNT(*) INTO v_exists
      FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = p_table
       AND INDEX_NAME   = p_index;

    IF v_exists = 0 THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_cols, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- payment_transactions: fast status + date reporting
CALL ksr_add_index_if_missing('payment_transactions', 'idx_pt_status_created',  '`status`, `created_at`');
CALL ksr_add_index_if_missing('payment_transactions', 'idx_pt_status_verified', '`status`, `verified_at`');
CALL ksr_add_index_if_missing('payment_transactions', 'idx_pt_method_status',   '`method`, `status`');
CALL ksr_add_index_if_missing('payment_transactions', 'idx_pt_customer_utr',    '`customer_utr`');
CALL ksr_add_index_if_missing('payment_transactions', 'idx_pt_bank_confirmed',  '`bank_confirmed_ref`');

-- payment_bank_credits: reconciliation matching
CALL ksr_add_index_if_missing('payment_bank_credits', 'idx_pbc_amount_date',    '`amount_paise`, `credit_date`');
CALL ksr_add_index_if_missing('payment_bank_credits', 'idx_pbc_utr',            '`utr`');

-- payment_refunds: status reporting
CALL ksr_add_index_if_missing('payment_refunds',      'idx_pref_status_date',   '`status`, `requested_at`');

-- payment_audit_logs: timeline queries
CALL ksr_add_index_if_missing('payment_audit_logs',   'idx_pal_created_event',  '`created_at`, `event_type`');

-- payment_webhook_events: unprocessed event queue
CALL ksr_add_index_if_missing('payment_webhook_events', 'idx_pwe_unprocessed',  '`processed`, `received_at`');

-- cod_collections: delivery partner workload
CALL ksr_add_index_if_missing('cod_collections',      'idx_cc_partner_status',  '`delivery_partner_id`, `collection_status`');

-- cod_handovers: pending handover report
CALL ksr_add_index_if_missing('cod_handovers',        'idx_ch_status_date',     '`handover_status`, `created_at`');

-- payment_support_cases: open case queue
CALL ksr_add_index_if_missing('payment_support_cases', 'idx_psc_status_opened', '`status`, `opened_at`');
CALL ksr_add_index_if_missing('payment_support_cases', 'idx_psc_assigned_status', '`assigned_to`, `status`');

-- payment_settlement_reports: date range queries
CALL ksr_add_index_if_missing('payment_settlement_reports', 'idx_psr_date',     '`report_date`');

DROP PROCEDURE IF EXISTS `ksr_add_index_if_missing`;
-- End of payment_indexes.sql
