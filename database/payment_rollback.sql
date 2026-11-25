-- ============================================================
-- KSR GURU JI PAY — Rollback Script
-- Use ONLY to undo payment_schema.sql + payment_migration.sql
--
-- WARNING: This destroys ALL payment data permanently.
-- Only run with explicit written approval from superadmin.
-- Take a verified backup BEFORE running this.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Migration tables (payment_migration.sql)
DROP TABLE IF EXISTS `payment_admin_permissions`;
DROP TABLE IF EXISTS `payment_support_cases`;
DROP TABLE IF EXISTS `cod_handovers`;
DROP TABLE IF EXISTS `cod_collections`;

-- Core schema tables (payment_schema.sql)
DROP TABLE IF EXISTS `payment_idempotency_keys`;
DROP TABLE IF EXISTS `payment_webhook_events`;
DROP TABLE IF EXISTS `payment_audit_logs`;
DROP TABLE IF EXISTS `payment_disputes`;
DROP TABLE IF EXISTS `payment_refunds`;
DROP TABLE IF EXISTS `payment_reconciliations`;
DROP TABLE IF EXISTS `payment_bank_credits`;
DROP TABLE IF EXISTS `payment_references`;
DROP TABLE IF EXISTS `payment_attempts`;
DROP TABLE IF EXISTS `payment_settlement_reports`;
DROP TABLE IF EXISTS `payment_transactions`;
DROP TABLE IF EXISTS `payment_gateway_settings`;
DROP TABLE IF EXISTS `payment_methods`;

SET FOREIGN_KEY_CHECKS = 1;

-- End of payment_rollback.sql
-- To verify: SHOW TABLES LIKE 'payment%'; SHOW TABLES LIKE 'cod_%';
