-- ============================================================
-- Patient Portal migration
-- Registration -> OTP verification (WhatsApp) -> Login -> Dashboard
-- Idempotent: safe to run multiple times.
-- ============================================================

-- 1) users: add mobile-based login + verification columns
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='mobile');
SET @has_phone := (SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='phone');
SET @s := IF(@c=0,
            IF(@has_phone > 0,
               'ALTER TABLE `users` ADD COLUMN `mobile` varchar(20) DEFAULT NULL AFTER `phone`',
               'ALTER TABLE `users` ADD COLUMN `mobile` varchar(20) DEFAULT NULL'
            ),
            'DO 0'
          );
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='account_status');
SET @s := IF(@c=0, 'ALTER TABLE `users` ADD COLUMN `account_status` ENUM(''PENDING_VERIFICATION'',''ACTIVE'',''SUSPENDED'',''LOCKED'') NOT NULL DEFAULT ''PENDING_VERIFICATION''', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='verified_at');
SET @s := IF(@c=0, 'ALTER TABLE `users` ADD COLUMN `verified_at` timestamp NULL DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='failed_logins');
SET @s := IF(@c=0, 'ALTER TABLE `users` ADD COLUMN `failed_logins` int(11) NOT NULL DEFAULT 0', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='last_login_at');
SET @s := IF(@c=0, 'ALTER TABLE `users` ADD COLUMN `last_login_at` timestamp NULL DEFAULT NULL', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- allow mobile OR email login (email already UNIQUE in base schema)
-- Normalize blank mobile values and enforce one account per mobile.
UPDATE `users`
   SET `mobile` = NULL
 WHERE `mobile` = '';

SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='uk_user_mobile');
SET @s := IF(@i=0, 'ALTER TABLE `users` ADD UNIQUE INDEX `uk_user_mobile` (`mobile`)', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Existing accounts predate portal verification -> keep them usable.
-- Only accounts created by the Patient Portal (with a mobile + PENDING) stay pending.
UPDATE `users`
   SET `account_status` = 'ACTIVE',
       `verified_at`    = COALESCE(`verified_at`, NOW())
 WHERE `account_status` = 'PENDING_VERIFICATION'
   AND (`mobile` IS NULL OR `mobile` = '');

-- 2) Patient demographic profile (created in migrate_v2.sql too - IF NOT EXISTS keeps it idempotent)
CREATE TABLE IF NOT EXISTS `patients` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `patient_name` varchar(150) NOT NULL,
    `date_of_birth` date DEFAULT NULL,
    `gender` ENUM('Male','Female','Other') DEFAULT 'Male',
    `blood_group` varchar(5) DEFAULT NULL,
    `mobile_number` varchar(20) DEFAULT NULL,
    `address` text DEFAULT NULL,
    `emergency_contact` varchar(150) DEFAULT NULL,
    `medical_history` text DEFAULT NULL,
    `allergies` text DEFAULT NULL,
    `current_medications` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_patient_user` (`user_id`),
    KEY `idx_patient_mobile` (`mobile_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) OTP codes (WhatsApp / Email), hashed at rest
CREATE TABLE IF NOT EXISTS `patient_otps` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `channel` ENUM('whatsapp','sms','email') NOT NULL DEFAULT 'whatsapp',
    `destination` varchar(120) NOT NULL,
    `code_hash` varchar(255) NOT NULL,
    `purpose` ENUM('registration','login','reset_password','change_mobile') NOT NULL DEFAULT 'registration',
    `attempts` int(11) NOT NULL DEFAULT 0,
    `is_used` tinyint(1) NOT NULL DEFAULT 0,
    `expires_at` datetime NOT NULL,
    `verified_at` datetime NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_otp_user` (`user_id`),
    KEY `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Remember-me persistent tokens (only a hash is stored)
CREATE TABLE IF NOT EXISTS `patient_remember_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `selector` varchar(64) NOT NULL,
    `validator_hash` varchar(255) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `expires_at` datetime NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` datetime NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_remember_selector` (`selector`),
    KEY `idx_remember_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Brute-force / throttling log
CREATE TABLE IF NOT EXISTS `patient_login_attempts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `identifier` varchar(120) NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `attempt_type` ENUM('password','otp') NOT NULL DEFAULT 'password',
    `was_successful` tinyint(1) NOT NULL DEFAULT 0,
    `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attempt_lookup` (`identifier`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6) Captcha challenge store (server-side anti-bot)
CREATE TABLE IF NOT EXISTS `patient_captcha` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `token` varchar(64) NOT NULL,
    `answer_hash` varchar(255) NOT NULL,
    `expires_at` datetime NOT NULL,
    `consumed` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_captcha_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
