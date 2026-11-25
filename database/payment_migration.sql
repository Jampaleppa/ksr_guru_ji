-- ============================================================
-- KSR GURU JI PAY — Payment Migration SQL
-- Release 1 Supplemental Tables
--
-- Run AFTER payment_schema.sql
-- Adds: cod_collections, cod_handovers,
--        payment_support_cases, payment_admin_permissions
-- Compatible with MySQL 5.7+ / 8.0+
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table: cod_collections
-- Records cash collected from customer by delivery partner.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cod_collections` (
  `id`                   int(11)      NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` int(11)    NOT NULL,
  `order_id`             int(11)      NOT NULL,
  `delivery_partner_id`  int(11)      DEFAULT NULL,
  `expected_amount_paise` bigint(20)  NOT NULL COMMENT 'Amount customer was expected to pay',
  `collected_amount_paise` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Amount actually collected from customer',
  `collection_status`    enum(
                           'cod_assigned','cod_out_for_delivery','cod_collected',
                           'cod_delivered','cod_refused','cod_delivery_failed','cod_discrepancy'
                         ) NOT NULL DEFAULT 'cod_assigned',
  `delivery_otp`         varchar(10)  DEFAULT NULL COMMENT 'OTP issued to customer for delivery confirmation',
  `otp_verified`         tinyint(1)   NOT NULL DEFAULT 0,
  `collection_notes`     text         DEFAULT NULL,
  `discrepancy_paise`    bigint(20)   NOT NULL DEFAULT 0 COMMENT 'collected_amount - expected; negative = short',
  `collected_at`         datetime     DEFAULT NULL,
  `delivered_at`         datetime     DEFAULT NULL,
  `created_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cc_payment`          (`payment_transaction_id`),
  KEY `idx_cc_order`            (`order_id`),
  KEY `idx_cc_delivery_partner` (`delivery_partner_id`),
  KEY `idx_cc_status`           (`collection_status`),
  KEY `idx_cc_created`          (`created_at`),
  CONSTRAINT `fk_cc_payment`          FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cc_order`            FOREIGN KEY (`order_id`)               REFERENCES `orders`              (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cc_delivery_partner` FOREIGN KEY (`delivery_partner_id`)    REFERENCES `users`               (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Cash collected from customer by delivery partner. One row per delivery attempt.';

-- --------------------------------------------------------
-- Table: cod_handovers
-- Records cash handed from delivery partner to business.
-- A delivery collection does NOT auto-mean business received cash.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cod_handovers` (
  `id`                   int(11)      NOT NULL AUTO_INCREMENT,
  `cod_collection_id`    int(11)      NOT NULL,
  `delivery_partner_id`  int(11)      DEFAULT NULL,
  `received_by_admin_id` int(11)      DEFAULT NULL COMMENT 'Finance/admin staff who physically received cash',
  `expected_amount_paise` bigint(20)  NOT NULL,
  `received_amount_paise` bigint(20)  NOT NULL DEFAULT 0,
  `discrepancy_paise`    bigint(20)   NOT NULL DEFAULT 0 COMMENT 'received - expected; negative = short',
  `handover_status`      enum('pending','completed','discrepancy','disputed') NOT NULL DEFAULT 'pending',
  `handover_notes`       text         DEFAULT NULL,
  `handover_at`          datetime     DEFAULT NULL,
  `created_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ch_collection`         (`cod_collection_id`),
  KEY `idx_ch_delivery_partner`   (`delivery_partner_id`),
  KEY `idx_ch_received_by`        (`received_by_admin_id`),
  KEY `idx_ch_status`             (`handover_status`),
  KEY `idx_ch_created`            (`created_at`),
  CONSTRAINT `fk_ch_collection`         FOREIGN KEY (`cod_collection_id`)    REFERENCES `cod_collections` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ch_delivery_partner`   FOREIGN KEY (`delivery_partner_id`)  REFERENCES `users`           (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ch_received_by`        FOREIGN KEY (`received_by_admin_id`) REFERENCES `users`           (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Cash handover from delivery partner to business. SEPARATE from collection.';

-- --------------------------------------------------------
-- Table: payment_support_cases
-- Customer complaints and payment-related support tickets.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_support_cases` (
  `id`                     int(11)      NOT NULL AUTO_INCREMENT,
  `case_uid`               varchar(60)  NOT NULL COMMENT 'KSR-CASE-{date}-{random}',
  `payment_transaction_id` int(11)      DEFAULT NULL,
  `order_id`               int(11)      NOT NULL,
  `user_id`                int(11)      DEFAULT NULL,
  `case_type`              enum(
                             'payment_pending_debited','double_payment','wrong_amount',
                             'wrong_merchant','refund_delayed','order_cancelled_after_payment',
                             'receipt_missing','payment_reversed','other'
                           ) NOT NULL DEFAULT 'other',
  `subject`                varchar(200) NOT NULL,
  `description`            text         DEFAULT NULL,
  `status`                 enum('open','investigating','awaiting_customer','resolved','closed') NOT NULL DEFAULT 'open',
  `priority`               enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `assigned_to`            int(11)      DEFAULT NULL COMMENT 'Admin/support user assigned',
  `resolution`             text         DEFAULT NULL,
  `evidence_path`          varchar(255) DEFAULT NULL COMMENT 'Path to customer-uploaded evidence (private storage)',
  `opened_at`              timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`            datetime     DEFAULT NULL,
  `closed_at`              datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_case_uid`              (`case_uid`),
  KEY `idx_psc_payment`                (`payment_transaction_id`),
  KEY `idx_psc_order`                  (`order_id`),
  KEY `idx_psc_user`                   (`user_id`),
  KEY `idx_psc_status`                 (`status`),
  KEY `idx_psc_assigned`               (`assigned_to`),
  KEY `idx_psc_opened`                 (`opened_at`),
  CONSTRAINT `fk_psc_payment` FOREIGN KEY (`payment_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_psc_order`   FOREIGN KEY (`order_id`)               REFERENCES `orders`              (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_psc_user`    FOREIGN KEY (`user_id`)                REFERENCES `users`               (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_psc_assigned` FOREIGN KEY (`assigned_to`)           REFERENCES `users`               (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Customer payment support cases. Every case gets a unique case_uid.';

-- --------------------------------------------------------
-- Table: payment_admin_permissions
-- Role-based access control for payment administration.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_admin_permissions` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`      int(11)      NOT NULL,
  `payment_role` enum(
                   'superadmin','finance_admin','payment_verifier',
                   'refund_officer','delivery_admin','delivery_partner',
                   'customer_support','auditor'
                 ) NOT NULL,
  `granted_by`   int(11)      DEFAULT NULL,
  `granted_at`   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at`   datetime     DEFAULT NULL,
  `is_active`    tinyint(1)   NOT NULL DEFAULT 1,
  `notes`        varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_role` (`user_id`, `payment_role`),
  KEY `idx_pap_user`       (`user_id`),
  KEY `idx_pap_role`       (`payment_role`),
  KEY `idx_pap_granted_by` (`granted_by`),
  KEY `idx_pap_active`     (`is_active`),
  CONSTRAINT `fk_pap_user`       FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pap_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Role-based access control for KSR GURU JI PAY administration.';

-- --------------------------------------------------------
-- Seed: payment_methods (enabled methods for Release 1)
-- Disable any method that is not yet configured.
-- --------------------------------------------------------
INSERT IGNORE INTO `payment_methods` (`code`, `label`, `description`, `icon`, `is_enabled`, `display_order`) VALUES
  ('upi',           'UPI',           'Pay via UPI (GPay, PhonePe, Paytm, BHIM etc.)',   'fa-mobile-alt',  0, 1),
  ('cod',           'Cash on Delivery', 'Pay cash when your order arrives. Max ₹2,000.', 'fa-money-bill',  1, 2),
  ('bank_transfer', 'Bank Transfer',  'NEFT / IMPS bank transfer with UTR submission.',  'fa-university',  1, 3),
  ('card',          'Debit / Credit Card', 'Authorised card payments (not yet active).', 'fa-credit-card', 0, 4),
  ('netbanking',    'Net Banking',    'Net banking (not yet active).',                   'fa-globe',       0, 5);

-- NOTE: UPI is disabled (0) until SBI merchant arrangement is complete.
--       COD and bank_transfer are enabled.

SET FOREIGN_KEY_CHECKS = 1;
-- End of payment_migration.sql
