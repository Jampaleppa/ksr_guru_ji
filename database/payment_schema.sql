-- ============================================================
-- KSR GURU JI PAY — Payment System Schema
-- Release 1: UPI Manual Reconciliation + COD + Bank Transfer
--
-- Run this file BEFORE payment_migration.sql
-- Compatible with MySQL 5.7+ / 8.0+
-- No CREATE DATABASE / USE statement — imports into assigned DB
--
-- RULES:
--   • All monetary amounts stored as INTEGER PAISE (not decimal)
--   • customer_utr  = unverified, customer-submitted reference
--   • bank_confirmed_ref = set only after admin bank verification
--   • payment_audit_logs is IMMUTABLE (no DELETEs, no UPDATEs)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table: payment_methods
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `code`         varchar(30)  NOT NULL COMMENT 'upi, cod, bank_transfer, card, etc.',
  `label`        varchar(100) NOT NULL,
  `description`  varchar(255) DEFAULT NULL,
  `icon`         varchar(50)  DEFAULT 'fa-credit-card',
  `is_enabled`   tinyint(1)   NOT NULL DEFAULT 1,
  `display_order` tinyint(3)  NOT NULL DEFAULT 10,
  `created_at`   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_method_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_gateway_settings
-- WARNING: Rows with is_secret=1 must be encrypted at app layer.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_gateway_settings` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `gateway_code`  varchar(30)  NOT NULL,
  `setting_key`   varchar(60)  NOT NULL,
  `setting_value` text         DEFAULT NULL COMMENT 'Encrypt at app layer when is_secret=1',
  `is_secret`     tinyint(1)   NOT NULL DEFAULT 0,
  `updated_at`    timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`    int(11)      DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gateway_setting` (`gateway_code`, `setting_key`),
  KEY `idx_pgs_updated_by` (`updated_by`),
  CONSTRAINT `fk_pgs_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_transactions
-- One row per payment attempt. Amounts in INTEGER PAISE.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id`                   int(11)      NOT NULL AUTO_INCREMENT,
  `payment_id`           varchar(60)  NOT NULL COMMENT 'KSR-prefixed unique ref e.g. KSR-20241105-A3X9',
  `order_id`             int(11)      NOT NULL,
  `user_id`              int(11)      DEFAULT NULL,
  `method`               varchar(30)  NOT NULL COMMENT 'upi, cod, bank_transfer, card',
  `gateway_code`         varchar(30)  DEFAULT NULL,
  `amount_paise`         bigint(20)   NOT NULL COMMENT 'Integer paise — 1 INR = 100 paise',
  `currency`             char(3)      NOT NULL DEFAULT 'INR',
  `status`               varchar(30)  NOT NULL DEFAULT 'initiated',
  `customer_utr`         varchar(100) DEFAULT NULL COMMENT 'UNVERIFIED — customer submitted; not proof of receipt',
  `bank_confirmed_ref`   varchar(100) DEFAULT NULL COMMENT 'Set ONLY after admin-verified bank credit match',
  `idempotency_key`      varchar(100) DEFAULT NULL,
  `failure_reason`       text         DEFAULT NULL,
  `metadata`             json         DEFAULT NULL,
  `created_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `verified_at`          datetime     DEFAULT NULL,
  `verification_source`  varchar(30)  DEFAULT NULL COMMENT 'admin_manual, webhook, auto_reconcile',
  `verified_by_admin_id` int(11)      DEFAULT NULL,
  `expires_at`           datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_id`      (`payment_id`),
  UNIQUE KEY `uq_idempotency_key` (`idempotency_key`),
  KEY `idx_pt_order_id`    (`order_id`),
  KEY `idx_pt_user_id`     (`user_id`),
  KEY `idx_pt_status`      (`status`),
  KEY `idx_pt_method`      (`method`),
  KEY `idx_pt_created_at`  (`created_at`),
  KEY `idx_pt_verified_by` (`verified_by_admin_id`),
  CONSTRAINT `fk_pt_order`       FOREIGN KEY (`order_id`)             REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pt_user`        FOREIGN KEY (`user_id`)              REFERENCES `users`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pt_verified_by` FOREIGN KEY (`verified_by_admin_id`) REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_attempts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_attempts` (
  `id`                     int(11)     NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` int(11)     NOT NULL,
  `attempt_number`         tinyint(3)  NOT NULL DEFAULT 1,
  `status`                 varchar(30) NOT NULL,
  `gateway_response`       json        DEFAULT NULL,
  `failure_reason`         text        DEFAULT NULL,
  `attempted_at`           timestamp   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pa_transaction` (`payment_transaction_id`),
  CONSTRAINT `fk_pa_transaction` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_references
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_references` (
  `id`                     int(11)      NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` int(11)      NOT NULL,
  `ref_type`               enum('customer_utr','bank_ref','provider_ref','receipt_no') NOT NULL,
  `ref_value`              varchar(100) NOT NULL,
  `submitted_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_by`           int(11)      DEFAULT NULL,
  `source`                 varchar(30)  DEFAULT NULL COMMENT 'customer, admin, webhook, import',
  PRIMARY KEY (`id`),
  KEY `idx_pr_transaction`  (`payment_transaction_id`),
  KEY `idx_pr_ref_value`    (`ref_value`),
  KEY `idx_pr_submitted_by` (`submitted_by`),
  CONSTRAINT `fk_pr_transaction`  FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_submitted_by` FOREIGN KEY (`submitted_by`)           REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_bank_credits
-- bank_ref UNIQUE ensures one credit cannot be matched twice.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_bank_credits` (
  `id`                 int(11)      NOT NULL AUTO_INCREMENT,
  `credit_date`        date         NOT NULL,
  `bank_ref`           varchar(100) NOT NULL COMMENT 'Bank transaction reference — UNIQUE: one credit matched once',
  `utr`                varchar(100) DEFAULT NULL,
  `narration`          text         DEFAULT NULL,
  `amount_paise`       bigint(20)   NOT NULL,
  `matched_payment_id` int(11)      DEFAULT NULL,
  `match_status`       enum('unmatched','matched','disputed') NOT NULL DEFAULT 'unmatched',
  `imported_at`        timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `imported_by`        int(11)      DEFAULT NULL,
  `notes`              text         DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_ref`          (`bank_ref`),
  KEY `idx_pbc_credit_date`         (`credit_date`),
  KEY `idx_pbc_match_status`        (`match_status`),
  KEY `idx_pbc_matched_payment`     (`matched_payment_id`),
  KEY `idx_pbc_imported_by`         (`imported_by`),
  CONSTRAINT `fk_pbc_matched_payment` FOREIGN KEY (`matched_payment_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pbc_imported_by`    FOREIGN KEY (`imported_by`)         REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_reconciliations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_reconciliations` (
  `id`                     int(11)   NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` int(11)   NOT NULL,
  `bank_credit_id`         int(11)   NOT NULL,
  `reconciled_at`          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reconciled_by`          int(11)   DEFAULT NULL,
  `outcome`                enum('matched','partial','mismatch','manual_override') NOT NULL,
  `notes`                  text      DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prec_transaction`   (`payment_transaction_id`),
  KEY `idx_prec_bank_credit`   (`bank_credit_id`),
  KEY `idx_prec_reconciled_by` (`reconciled_by`),
  CONSTRAINT `fk_prec_transaction`   FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prec_bank_credit`   FOREIGN KEY (`bank_credit_id`)         REFERENCES `payment_bank_credits` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_prec_reconciled_by` FOREIGN KEY (`reconciled_by`)          REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_refunds
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_refunds` (
  `id`                     int(11)      NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` int(11)      NOT NULL,
  `order_id`               int(11)      NOT NULL,
  `requested_by`           int(11)      DEFAULT NULL,
  `approved_by`            int(11)      DEFAULT NULL,
  `amount_paise`           bigint(20)   NOT NULL,
  `reason`                 text         DEFAULT NULL,
  `status`                 enum('pending','approved','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `provider_refund_id`     varchar(100) DEFAULT NULL,
  `bank_ref`               varchar(100) DEFAULT NULL,
  `requested_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at`            datetime     DEFAULT NULL,
  `completed_at`           datetime     DEFAULT NULL,
  `failure_reason`         text         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pref_transaction`  (`payment_transaction_id`),
  KEY `idx_pref_order`        (`order_id`),
  KEY `idx_pref_requested_by` (`requested_by`),
  KEY `idx_pref_approved_by`  (`approved_by`),
  KEY `idx_pref_status`       (`status`),
  CONSTRAINT `fk_pref_transaction`  FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pref_order`        FOREIGN KEY (`order_id`)               REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pref_requested_by` FOREIGN KEY (`requested_by`)           REFERENCES `users`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pref_approved_by`  FOREIGN KEY (`approved_by`)            REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_disputes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_disputes` (
  `id`                     int(11)      NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` int(11)      DEFAULT NULL,
  `order_id`               int(11)      NOT NULL,
  `user_id`                int(11)      DEFAULT NULL,
  `subject`                varchar(200) NOT NULL,
  `description`            text         DEFAULT NULL,
  `status`                 enum('open','investigating','resolved','closed') NOT NULL DEFAULT 'open',
  `resolution`             text         DEFAULT NULL,
  `opened_at`              timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`            datetime     DEFAULT NULL,
  `resolved_by`            int(11)      DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pd_transaction` (`payment_transaction_id`),
  KEY `idx_pd_order`       (`order_id`),
  KEY `idx_pd_user`        (`user_id`),
  KEY `idx_pd_status`      (`status`),
  KEY `idx_pd_resolved_by` (`resolved_by`),
  CONSTRAINT `fk_pd_transaction` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pd_order`       FOREIGN KEY (`order_id`)               REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pd_user`        FOREIGN KEY (`user_id`)                REFERENCES `users`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pd_resolved_by` FOREIGN KEY (`resolved_by`)            REFERENCES `users`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_webhook_events
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_webhook_events` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `gateway_code` varchar(30)  NOT NULL,
  `event_id`     varchar(100) NOT NULL,
  `event_type`   varchar(100) DEFAULT NULL,
  `payload`      json         DEFAULT NULL,
  `signature`    varchar(255) DEFAULT NULL,
  `processed`    tinyint(1)   NOT NULL DEFAULT 0,
  `error`        text         DEFAULT NULL,
  `received_at`  timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webhook_event_id` (`event_id`),
  KEY `idx_pwe_gateway`   (`gateway_code`),
  KEY `idx_pwe_processed` (`processed`),
  KEY `idx_pwe_received`  (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_audit_logs
-- IMMUTABLE — application MUST NOT issue UPDATE or DELETE here.
-- A failed audit INSERT must block the financial action.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_audit_logs` (
  `id`                     int(11)   NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` int(11)   DEFAULT NULL,
  `order_id`               int(11)   DEFAULT NULL,
  `event_type`             varchar(60) NOT NULL,
  `old_status`             varchar(30) DEFAULT NULL,
  `new_status`             varchar(30) DEFAULT NULL,
  `actor_type`             enum('customer','admin','system','webhook') NOT NULL DEFAULT 'system',
  `actor_id`               int(11)   DEFAULT NULL,
  `ip_address`             varchar(45) DEFAULT NULL,
  `user_agent`             text      DEFAULT NULL,
  `notes`                  text      DEFAULT NULL,
  `created_at`             timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pal_transaction` (`payment_transaction_id`),
  KEY `idx_pal_order`       (`order_id`),
  KEY `idx_pal_event_type`  (`event_type`),
  KEY `idx_pal_actor`       (`actor_type`, `actor_id`),
  KEY `idx_pal_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IMMUTABLE — no UPDATE or DELETE permitted';

-- --------------------------------------------------------
-- Table: payment_idempotency_keys
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_idempotency_keys` (
  `id`                     int(11)      NOT NULL AUTO_INCREMENT,
  `idempotency_key`        varchar(100) NOT NULL,
  `payment_transaction_id` int(11)      DEFAULT NULL,
  `created_at`             timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`             datetime     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_idempotency_key` (`idempotency_key`),
  KEY `idx_pik_transaction` (`payment_transaction_id`),
  KEY `idx_pik_expires_at`  (`expires_at`),
  CONSTRAINT `fk_pik_transaction` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_settlement_reports
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_settlement_reports` (
  `id`                    int(11)    NOT NULL AUTO_INCREMENT,
  `report_date`           date       NOT NULL,
  `total_collected_paise` bigint(20) NOT NULL DEFAULT 0,
  `total_refunded_paise`  bigint(20) NOT NULL DEFAULT 0,
  `net_paise`             bigint(20) NOT NULL DEFAULT 0,
  `upi_count`             int(11)    NOT NULL DEFAULT 0,
  `cod_count`             int(11)    NOT NULL DEFAULT 0,
  `bank_transfer_count`   int(11)    NOT NULL DEFAULT 0,
  `generated_at`          timestamp  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `generated_by`          int(11)    DEFAULT NULL,
  `notes`                 text       DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report_date`    (`report_date`),
  KEY `idx_psr_generated_by`     (`generated_by`),
  CONSTRAINT `fk_psr_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
-- End of payment_schema.sql
