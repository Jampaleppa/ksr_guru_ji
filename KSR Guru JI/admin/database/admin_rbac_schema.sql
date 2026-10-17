-- ============================================================
-- KSR GURU JI - ADMIN RBAC / SECURITY SCHEMA
-- ============================================================
-- Creates the dedicated admin security subsystem:
--   admin_users, admin_roles, admin_permissions,
--   admin_role_permissions, admin_sessions, admin_otp,
--   admin_login_attempts, admin_audit_logs
--
-- Safe to run multiple times (CREATE TABLE IF NOT EXISTS +
-- INSERT ... ON DUPLICATE KEY UPDATE).
--
-- NOTE: The legacy `users` table still holds admin rows
-- (role = admin / super_admin). admin_users is the NEW
-- authoritative identity table for portal administrators.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 1. admin_roles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_roles` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_key`      VARCHAR(50)  NOT NULL,
  `role_name`     VARCHAR(100) NOT NULL,
  `description`   VARCHAR(255) DEFAULT NULL,
  `is_system`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_roles_key` (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------------
-- 2. admin_permissions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_permissions` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `permission_key`    VARCHAR(100) NOT NULL,   -- e.g. products.create
  `module`            VARCHAR(60)  NOT NULL,   -- e.g. products
  `action`            VARCHAR(30)  NOT NULL,   -- view|create|edit|delete|approve|export
  `label`             VARCHAR(120) NOT NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_perm_key` (`permission_key`),
  KEY `idx_admin_perm_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------------
-- 3. admin_role_permissions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_arp_permission` (`permission_id`),
  CONSTRAINT `fk_arp_role`
      FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_arp_permission`
      FOREIGN KEY (`permission_id`) REFERENCES `admin_permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------------
-- 4. admin_users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`        VARCHAR(80)  NOT NULL,
  `email`           VARCHAR(150) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `full_name`       VARCHAR(150) NOT NULL,
  `phone`           VARCHAR(20)  DEFAULT NULL,   -- used for WhatsApp OTP
  `role_id`         INT UNSIGNED NOT NULL,
  `status`          ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  `is_super_admin`  TINYINT(1)   NOT NULL DEFAULT 0,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at`   TIMESTAMP    NULL DEFAULT NULL,
  `last_login_ip`   VARCHAR(45)  DEFAULT NULL,
  `failed_logins`   INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`    TIMESTAMP    NULL DEFAULT NULL,
  `created_by`      INT UNSIGNED DEFAULT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_users_username` (`username`),
  UNIQUE KEY `uq_admin_users_email` (`email`),
  KEY `idx_admin_users_role` (`role_id`),
  CONSTRAINT `fk_admin_users_role`
      FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------------
-- 5. admin_sessions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`      INT UNSIGNED NOT NULL,
  `session_id`    VARCHAR(128) NOT NULL,
  `ip_address`    VARCHAR(45)  DEFAULT NULL,
  `user_agent`    VARCHAR(255) DEFAULT NULL,
  `csrf_token`    VARCHAR(128) DEFAULT NULL,
  `is_otp_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `last_activity` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`    TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_sessions_sid` (`session_id`),
  KEY `idx_admin_sessions_admin` (`admin_id`),
  CONSTRAINT `fk_admin_sessions_admin`
      FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------------
-- 6. admin_otp
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_otp` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`      INT UNSIGNED NOT NULL,
  `otp_hash`      VARCHAR(255) NOT NULL,          -- hash of the OTP, never plaintext
  `purpose`       VARCHAR(40)  NOT NULL DEFAULT 'login',
  `channel`       VARCHAR(20)  NOT NULL DEFAULT 'whatsapp',
  `destination`   VARCHAR(30)  DEFAULT NULL,
  `attempts`      INT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts`  INT UNSIGNED NOT NULL DEFAULT 5,
  `resend_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `is_used`       TINYINT(1)   NOT NULL DEFAULT 0,
  `expires_at`    TIMESTAMP    NOT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_otp_admin` (`admin_id`, `purpose`),
  CONSTRAINT `fk_admin_otp_admin`
      FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------------
-- 7. admin_login_attempts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_login_attempts` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier`    VARCHAR(150) DEFAULT NULL,   -- username/email attempted
  `admin_id`      INT UNSIGNED DEFAULT NULL,
  `ip_address`    VARCHAR(45)  NOT NULL,
  `user_agent`    VARCHAR(255) DEFAULT NULL,
  `successful`    TINYINT(1)   NOT NULL DEFAULT 0,
  `stage`         VARCHAR(20)  NOT NULL DEFAULT 'password', -- password|otp
  `attempted_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ala_ip` (`ip_address`, `attempted_at`),
  KEY `idx_ala_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------------
-- 8. admin_audit_logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`      INT UNSIGNED DEFAULT NULL,
  `admin_name`    VARCHAR(150) DEFAULT NULL,
  `action`        VARCHAR(100) NOT NULL,
  `module`        VARCHAR(60)  DEFAULT NULL,
  `target_table`  VARCHAR(100) DEFAULT NULL,
  `record_id`     BIGINT       DEFAULT NULL,
  `details`       TEXT         DEFAULT NULL,
  `ip_address`    VARCHAR(45)  DEFAULT NULL,
  `user_agent`    VARCHAR(255) DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aal_admin` (`admin_id`),
  KEY `idx_aal_module` (`module`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================
-- SEED: roles, permissions, role_permissions, super admin user
-- ============================================================

INSERT INTO `admin_roles` (`role_key`, `role_name`, `description`, `is_system`) VALUES
  ('SUPER_ADMIN', 'Super Admin', 'Full unrestricted system access', 1),
  ('ADMIN', 'Admin', 'Permission-based operational access', 1)
ON DUPLICATE KEY UPDATE `role_name` = VALUES(`role_name`),
                        `description` = VALUES(`description`);

-- Permissions: module x action grid
INSERT INTO `admin_permissions` (`permission_key`, `module`, `action`, `label`) VALUES
  ('dashboard.view',    'dashboard',    'view',    'View Dashboard'),
  ('products.view',     'products',     'view',    'View Products'),
  ('products.create',   'products',     'create',  'Create Products'),
  ('products.edit',     'products',     'edit',    'Edit Products'),
  ('products.delete',   'products',     'delete',  'Delete Products'),
  ('categories.view',   'categories',   'view',    'View Categories'),
  ('categories.create', 'categories',   'create',  'Create Categories'),
  ('categories.edit',   'categories',   'edit',    'Edit Categories'),
  ('categories.delete', 'categories',   'delete',  'Delete Categories'),
  ('orders.view',       'orders',       'view',    'View Orders'),
  ('orders.edit',       'orders',       'edit',    'Update Orders'),
  ('orders.approve',    'orders',       'approve', 'Approve Orders'),
  ('orders.export',     'orders',       'export',  'Export Orders'),
  ('payments.view',     'payments',     'view',    'View Payments'),
  ('payments.edit',     'payments',     'edit',    'Edit Payments'),
  ('payments.approve',  'payments',     'approve', 'Approve Payments'),
  ('payments.export',   'payments',     'export',  'Export Payments'),
  ('customers.view',    'customers',    'view',    'View Customers'),
  ('customers.edit',    'customers',    'edit',    'Edit Customers'),
  ('customers.delete',  'customers',    'delete',  'Delete Customers'),
  ('vendors.view',      'vendors',      'view',    'View Vendors'),
  ('vendors.edit',      'vendors',      'edit',    'Manage Vendors'),
  ('vendors.approve',   'vendors',      'approve', 'Approve Vendors'),
  ('pharmacy.view',     'pharmacy',     'view',    'View Pharmacy / Medicines'),
  ('pharmacy.create',   'pharmacy',     'create',  'Create Medicines'),
  ('pharmacy.edit',     'pharmacy',     'edit',    'Edit Medicines'),
  ('pharmacy.delete',   'pharmacy',     'delete',  'Delete Medicines'),
  ('doctors.view',      'doctors',      'view',    'View Doctors'),
  ('doctors.approve',   'doctors',      'approve', 'Verify Doctors'),
  ('appointments.view', 'appointments', 'view',    'View Appointments'),
  ('appointments.edit', 'appointments', 'edit',    'Manage Appointments'),
  ('prescriptions.view','prescriptions','view',    'View Prescriptions'),
  ('prescriptions.approve','prescriptions','approve','Approve Prescriptions'),
  ('delivery.view',     'delivery',     'view',    'View Delivery Partners'),
  ('delivery.edit',     'delivery',     'edit',    'Manage Delivery'),
  ('reports.view',      'reports',      'view',    'View Reports'),
  ('reports.export',    'reports',      'export',  'Export Reports'),
  ('settings.view',     'settings',     'view',    'View Settings'),
  ('settings.edit',     'settings',     'edit',    'Edit Settings'),
  ('notifications.view','notifications','view',    'View Notifications'),
  ('notifications.create','notifications','create','Send Notifications'),
  ('notifications.edit','notifications','edit',    'Mark / Manage Notifications'),
  ('notifications.delete','notifications','delete','Delete Notifications'),
  ('audit.view',        'audit',        'view',    'View Audit Logs'),
  ('audit.export',      'audit',        'export',  'Export Audit Logs'),
  ('inventory.view',    'inventory',    'view',    'View Inventory / Banners / Coupons'),
  ('inventory.create',  'inventory',    'create',  'Create Inventory Items'),
  ('inventory.edit',    'inventory',    'edit',    'Edit Inventory Items'),
  ('inventory.delete',  'inventory',    'delete',  'Delete Inventory Items'),
  ('whatsapp.view',     'whatsapp',     'view',    'View WhatsApp Command Center'),
  ('whatsapp.edit',     'whatsapp',     'edit',    'Manage WhatsApp Automation'),
  ('maintenance.view',  'maintenance',  'view',    'View Maintenance Tools'),
  ('maintenance.edit',  'maintenance',  'edit',    'Run Maintenance Actions'),
  ('medical.view',      'medical',      'view',    'View Medical / Compliance Records'),
  ('medical.approve',   'medical',      'approve', 'Approve Medical Verification'),
  ('admins.view',       'admins',       'view',    'View Admin Accounts'),
  ('admins.create',     'admins',       'create',  'Create Admin Accounts'),
  ('admins.edit',       'admins',       'edit',    'Edit Admin Accounts'),
  ('admins.delete',     'admins',       'delete',  'Delete / Suspend Admin Accounts')
ON DUPLICATE KEY UPDATE `module` = VALUES(`module`),
                        `action` = VALUES(`action`),
                        `label`  = VALUES(`label`);

-- Give ADMIN role the read/operate set defined by the spec
-- (dashboard, products, categories, orders, payments, customers,
--  pharmacy, doctors, appointments, prescriptions, delivery, reports)
INSERT IGNORE INTO `admin_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `admin_roles` r
JOIN `admin_permissions` p
  ON p.`permission_key` IN (
    'dashboard.view',
    'products.view','products.create','products.edit',
    'categories.view','categories.create','categories.edit',
    'orders.view','orders.edit','orders.approve',
    'payments.view','payments.approve',
    'customers.view','customers.edit',
    'pharmacy.view','pharmacy.create','pharmacy.edit',
    'doctors.view','appointments.view','appointments.edit',
    'prescriptions.view','prescriptions.approve',
    'delivery.view','delivery.edit',
    'reports.view',
    'inventory.view','inventory.create','inventory.edit',
    'notifications.view','notifications.create','notifications.edit',
    'whatsapp.view',
    'maintenance.view',
    'medical.view','medical.approve'
  )
WHERE r.`role_key` = 'ADMIN';

-- SUPER_ADMIN gets every permission
INSERT IGNORE INTO `admin_role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `admin_roles` r
CROSS JOIN `admin_permissions` p
WHERE r.`role_key` = 'SUPER_ADMIN';

-- ------------------------------------------------------------
-- Administrator accounts are NOT seeded here on purpose.
--
-- The production identities (Super Admin + Admin) are created by
-- the dedicated, re-runnable seeder so that the credentials live
-- in exactly one place and the schema stays idempotent:
--
--     php admin/database/seed_admin_accounts.php
--
-- (seeding a default password in a .sql file is a security trap)
-- ------------------------------------------------------------

-- ============================================================
-- END
-- ============================================================