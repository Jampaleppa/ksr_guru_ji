-- ============================================================
-- KSR GURU JI — Complete Deployment Database Schema
-- Compatible with InfinityFree, cPanel, and MySQL 5.7+ / 8.0+
-- (No CREATE DATABASE / USE statement so it imports cleanly into your assigned database)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table: users (E-commerce + Patient Portal + Delivery Staff)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `fullname` varchar(150) DEFAULT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','customer','delivery','delivery_boy') NOT NULL DEFAULT 'customer',
  `account_status` enum('PENDING_VERIFICATION','ACTIVE','SUSPENDED','LOCKED') NOT NULL DEFAULT 'ACTIVE',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `failed_logins` int(11) NOT NULL DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: categories (with Kannada names & icons)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `kn_name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL UNIQUE,
  `icon` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_category_parent` (`parent_id`),
  CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: main_categories (groupings)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `main_categories` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `kn` varchar(100) NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `sub_ids` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: products
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `kn_name` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL UNIQUE,
  `description` text DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(50) DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-box',
  `emoji` varchar(10) DEFAULT '📦',
  `is_featured` tinyint(1) DEFAULT 0,
  `images` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_product_category` (`category_id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: cart
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cart_item` (`user_id`,`product_id`),
  KEY `fk_cart_user` (`user_id`),
  KEY `fk_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: orders
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL UNIQUE,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_charge` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('cod','card','upi','bank_transfer') NOT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','confirmed','processing','shipped','out_for_delivery','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  `order_status` enum('pending','confirmed','processed','shipped','out_for_delivery','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  `driver_id` int(11) DEFAULT NULL,
  `delivery_boy_id` int(11) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `placed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_order_user` (`user_id`),
  KEY `fk_order_driver` (`driver_id`),
  KEY `fk_order_delivery_boy` (`delivery_boy_id`),
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: order_items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_order_item_order` (`order_id`),
  KEY `fk_order_item_product` (`product_id`),
  CONSTRAINT `fk_order_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payments (Full lifecycle + COD + Gateway support)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cod','card','upi','bank_transfer') NOT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `gateway_order_id` varchar(100) DEFAULT NULL,
  `gateway_payment_id` varchar(100) DEFAULT NULL,
  `gateway_signature` varchar(255) DEFAULT NULL,
  `gateway_response` text DEFAULT NULL,
  `status` enum(
      'pending','success','failed','refunded',
      'initiated','processing','cancelled','expired',
      'refund_pending','refund_failed',
      'cod_pending','cod_collected'
  ) NOT NULL DEFAULT 'pending',
  `failure_code` varchar(100) DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `webhook_received_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `refund_status` varchar(30) DEFAULT NULL,
  `refund_transaction_id` varchar(100) DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `collected_amount` decimal(10,2) DEFAULT NULL,
  `collected_by` varchar(100) DEFAULT NULL,
  `collected_at` datetime DEFAULT NULL,
  `collection_reference` varchar(100) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_payment_order` (`order_id`),
  KEY `fk_payment_user` (`user_id`),
  CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: payment_events
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) DEFAULT NULL,
  `gateway_event_id` varchar(150) DEFAULT NULL,
  `gateway_payment_id` varchar(150) DEFAULT NULL,
  `payload` longtext DEFAULT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'system',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_gateway_event_id` (`gateway_event_id`),
  KEY `idx_gateway_payment_id` (`gateway_payment_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: refunds
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `refunds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `refund_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','processed','failed') NOT NULL DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_refund_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: delivery_boys
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `delivery_boys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL UNIQUE,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `availability` tinyint(1) NOT NULL DEFAULT 1,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_deliveries` int(11) DEFAULT 0,
  `earnings` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_delivery_boy_user` (`user_id`),
  CONSTRAINT `fk_delivery_boy_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: coupons
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL UNIQUE,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: banners
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `image` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `position` enum('home','category','product') DEFAULT 'home',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: notifications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('order','promo','system','delivery') NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notification_user` (`user_id`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: audit_logs & View
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_table` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created_at` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW `audit_log` AS SELECT * FROM `audit_logs`;

-- --------------------------------------------------------
-- Table: settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL UNIQUE,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Patient Portal Tables
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `patient_name` varchar(150) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
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

CREATE TABLE IF NOT EXISTS `patient_otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `channel` enum('whatsapp','sms','email') NOT NULL DEFAULT 'whatsapp',
  `destination` varchar(120) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `purpose` enum('registration','login','reset_password','change_mobile') NOT NULL DEFAULT 'registration',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_user` (`user_id`),
  KEY `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS `patient_login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(120) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `attempt_type` enum('password','otp') NOT NULL DEFAULT 'password',
  `was_successful` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempt_lookup` (`identifier`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- --------------------------------------------------------
-- Performance Indexes
-- --------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_orders_user_status ON orders(user_id, order_status);
CREATE INDEX IF NOT EXISTS idx_orders_delivery_boy ON orders(delivery_boy_id);
CREATE INDEX IF NOT EXISTS idx_products_category_status ON products(category_id, status);
CREATE INDEX IF NOT EXISTS idx_cart_user ON cart(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, is_read);

-- ========================================================
-- SEED DATA
-- ========================================================

-- Default Admin User (Password: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `mobile`, `role`, `account_status`)
VALUES ('Admin', 'admin@ksrguruji.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543210', '9876543210', 'admin', 'ACTIVE')
ON DUPLICATE KEY UPDATE `role` = 'admin';

-- Categories
INSERT IGNORE INTO `categories` (`id`, `name`, `kn_name`, `slug`, `icon`, `status`) VALUES
(1, 'Air Freshener', 'ಏರ್ ಫ್ರೆಶ್ನರ್', 'air-freshner', 'fa-wind', 1),
(2, 'Bakery', 'ಬೇಕರಿ', 'bakery', 'fa-bread-slice', 1),
(3, 'Bath & Body', 'ಸ್ನಾನ ಮತ್ತು ದೇಹ', 'bath-body', 'fa-bath', 1),
(4, 'Cleaning Supplies', 'ಶುಚಿಗೊಳಿಸುವ ಸರಬರಾಜು', 'cleaning', 'fa-soap', 1),
(5, 'Cold Drinks', 'ಪಾನೀಯಗಳು', 'cold-drinks', 'fa-mug-hot', 1),
(6, 'Cooking Oil', 'ಅಡುಗೆ ಎಣ್ಣೆ', 'cooking-oil', 'fa-oil-can', 1),
(7, 'Dairy', 'ಡೈರಿ', 'dairy', 'fa-cheese', 1),
(8, 'Dishwash', 'ಡಿಶ್ವಾಶ್', 'dishwash', 'fa-hand-sparkles', 1),
(9, 'Dry Fruits', 'ಒಣ ಹಣ್ಣುಗಳು', 'dry-fruits', 'fa-seedling', 1),
(10, 'Grains & Cereals', 'ಧಾನ್ಯಗಳು', 'grains', 'fa-wheat-alt', 1),
(11, 'Hair Care', 'ಹೇರ್ ಕೇರ್', 'hair-care', 'fa-user-tie', 1),
(12, 'Health & Pharmacy', 'ಆರೋಗ್ಯ ಮತ್ತು ಔಷಧ', 'health-pharmacy', 'fa-capsules', 1),
(13, 'Home Care', 'ಮನೆ ಆರೈಕೆ', 'home-care', 'fa-home', 1),
(14, 'Kitchenware', 'ಅಡುಗೆ ಸಾಮಾನು', 'kitchen', 'fa-utensils', 1),
(15, 'Laundry', 'ಲಾಂಡ್ರಿ', 'laundry', 'fa-jug-detergent', 1),
(16, 'Paper Products', 'ಪೇಪರ್ ಉತ್ಪನ್ನಗಳು', 'paper-products', 'fa-toilet-paper', 1),
(17, 'Personal Care', 'ವೈಯಕ್ತಿಕ ಆರೈಕೆ', 'personal-care', 'fa-hand-holding-heart', 1),
(18, 'Pet Supplies', 'ಪಿಇಟಿ ಸರಬರಾಜು', 'pet-supplies', 'fa-dog', 1),
(19, 'Pickles', 'ಉಪ್ಪಿನಕಾಯಿ', 'pickles', 'fa-jar', 1),
(20, 'Plastic Household', 'ಪ್ಲಾಸ್ಟಿಕ್ ಗೃಹೋಪಯೋಗಿ', 'plastic-household', 'fa-cube', 1),
(21, 'Pooja Items', 'ಪೂಜಾ ಸಾಮಗ್ರಿಗಳು', 'pooja', 'fa-pray', 1),
(22, 'Ready to Eat', 'ಸಿದ್ಧ ಊಟ', 'ready-to-eat', 'fa-utensil-spoon', 1),
(23, 'Seasonal Items', 'ಋತುಮಾನದ ವಸ್ತುಗಳು', 'seasonal', 'fa-calendar-alt', 1),
(24, 'Snacks', 'ತಿಂಡಿಗಳು', 'snacks', 'fa-cookie', 1),
(25, 'Spices', 'ಮಸಾಲೆಗಳು', 'spices', 'fa-mortar-pestle', 1),
(26, 'Stationery', 'ಸ್ಟೇಷನರಿ', 'stationery', 'fa-pen', 1),
(27, 'Vegetables & Fruits', 'ಹಣ್ಣುಗಳು ಮತ್ತು ತರಕಾರಿಗಳು', 'vegetables-fruits', 'fa-carrot', 1);

-- Main Categories Groupings
INSERT IGNORE INTO `main_categories` (`id`, `name`, `kn`, `emoji`, `sub_ids`) VALUES
('fruits-veg', 'Fruits & Vegetables', 'ಹಣ್ಣುಗಳು ಮತ್ತು ತರಕಾರಿಗಳು', '🍎', 'vegetables-fruits,seasonal'),
('grains-pulses', 'Grains, Pulses & Staples', 'ಧಾನ್ಯಗಳು, ಬೇಳೆಗಳು ಮತ್ತು ಸ್ಟೇಪಲ್ಸ್', '🌾', 'grains,ready-to-eat'),
('spices-oils', 'Spices, Oils & Essentials', 'ಮಸಾಲೆಗಳು, ಎಣ್ಣೆ ಮತ್ತು ಅಗತ್ಯಗಳು', '🧂', 'spices,cooking-oil,pickles'),
('dairy-bakery', 'Dairy & Bakery', 'ಡೈರಿ ಮತ್ತು ಬೇಕರಿ', '🥛', 'dairy,bakery'),
('snacks-beverages', 'Snacks & Beverages', 'ತಿಂಡಿಗಳು ಮತ್ತು ಪಾನೀಯಗಳು', '🍪', 'snacks,cold-drinks,dry-fruits'),
('personal-beauty', 'Personal Care & Beauty', 'ವೈಯಕ್ತಿಕ ಆರೈಕೆ ಮತ್ತು ಸೌಂದರ್ಯ', '🧴', 'bath-body,hair-care,personal-care'),
('health-baby', 'Baby & Health Care', 'ಬೇಬಿ ಮತ್ತು ಆರೋಗ್ಯ ಆರೈಕೆ', '👶', 'health-pharmacy'),
('home-cleaning', 'Home Cleaning & Laundry', 'ಮನೆ ಶುಚಿಗೊಳಿಸುವಿಕೆ ಮತ್ತು ಲಾಂಡ್ರಿ', '🧹', 'cleaning,dishwash,laundry,air-freshner'),
('household-kitchen', 'Household & Kitchen', 'ಗೃಹೋಪಯೋಗಿ ಮತ್ತು ಅಡುಗೆಮನೆ', '🏠', 'kitchen,plastic-household,paper-products'),
('pet-supplies', 'Pet Supplies', 'ಪಿಇಟಿ ಸರಬರಾಜು', '🐕', 'pet-supplies'),
('pooja', 'Pooja & Religious Items', 'ಪೂಜಾ ಮತ್ತು ಧಾರ್ಮಿಕ ವಸ್ತುಗಳು', '🪔', 'pooja'),
('office-school', 'Office & School', 'ಕಚೇರಿ ಮತ್ತು ಶಾಲೆ', '✏️', 'stationery');

-- Default Site Settings
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('site_name', 'KSR GURU JI'),
('site_email', 'info@ksrguruji.com'),
('delivery_charge', '15.00'),
('currency', '₹'),
('tax_percent', '5.00');

SET FOREIGN_KEY_CHECKS = 1;
