-- ============================================================
-- KSR GURU JI PAY — Migration: Extend Existing Tables + Seed
--
-- PREREQUISITE: Run database/payment_schema.sql FIRST.
-- Then run this file.
--
-- Safe to run multiple times (uses IF NOT EXISTS, INSERT IGNORE,
-- and ON DUPLICATE KEY UPDATE).
-- ============================================================

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Step 1: Extend orders.payment_status ENUM
-- Adds new lifecycle states without breaking existing rows.
-- --------------------------------------------------------
ALTER TABLE `orders`
  MODIFY COLUMN `payment_status`
  ENUM(
    'pending','paid','failed','refunded',
    'processing','initiated','cancelled','expired',
    'cod_pending','cod_collected',
    'upi_awaiting','upi_pending_verification',
    'bank_credit_imported',
    'refund_pending','refund_failed'
  ) NOT NULL DEFAULT 'pending';

-- --------------------------------------------------------
-- Step 2: Extend orders.payment_method ENUM
-- --------------------------------------------------------
ALTER TABLE `orders`
  MODIFY COLUMN `payment_method`
  ENUM('cod','card','upi','bank_transfer','razorpay','net_banking','wallet')
  NOT NULL;

-- --------------------------------------------------------
-- Step 3: Seed payment_methods
-- --------------------------------------------------------
INSERT IGNORE INTO `payment_methods` (`code`, `label`, `description`, `icon`, `is_enabled`, `display_order`) VALUES
  ('upi',           'UPI Payment',       'Pay via any UPI app (PhonePe, GPay, Paytm, etc.)', 'fa-mobile-alt',   1, 1),
  ('cod',           'Cash on Delivery',  'Pay cash when your order arrives',                   'fa-money-bill',   1, 2),
  ('bank_transfer', 'Bank Transfer',     'NEFT / IMPS direct bank transfer',                   'fa-university',   1, 3),
  ('card',          'Debit / Credit Card','Coming soon',                                        'fa-credit-card',  0, 4),
  ('net_banking',   'Net Banking',       'Coming soon',                                         'fa-laptop',       0, 5),
  ('wallet',        'Wallets',           'Coming soon',                                         'fa-wallet',       0, 6);

-- --------------------------------------------------------
-- Step 4: Seed payment_gateway_settings (UPI placeholder)
-- IMPORTANT: Replace PLACEHOLDER values after SBI merchant
--            onboarding is complete.
-- --------------------------------------------------------
INSERT INTO `payment_gateway_settings` (`gateway_code`, `setting_key`, `setting_value`, `is_secret`) VALUES
  ('upi', 'merchant_name',  'KSR GURU JI',               0),
  ('upi', 'upi_id',         'PLACEHOLDER_UPI_ID@sbi',    0),
  ('upi', 'display_name',   'KSR GURU JI PAY',           0),
  ('upi', 'qr_image_path',  '',                           0),
  ('bank_transfer', 'account_holder', 'KSR GURU JI',     0),
  ('bank_transfer', 'bank_name',      'State Bank of India', 0),
  ('bank_transfer', 'account_number', 'PLACEHOLDER',     1),
  ('bank_transfer', 'ifsc',           'PLACEHOLDER',     0),
  ('bank_transfer', 'account_type',   'Savings',         0)
ON DUPLICATE KEY UPDATE
  `setting_value` = IF(`setting_value` = '' OR `setting_value` LIKE 'PLACEHOLDER%',
                        VALUES(`setting_value`),
                        `setting_value`);

-- ============================================================
-- End of payment_migration.sql
-- ============================================================

