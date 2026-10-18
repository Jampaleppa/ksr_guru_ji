-- --------------------------------------------------------
-- Database: ksr_guru_ji
-- --------------------------------------------------------
CREATE DATABASE IF NOT EXISTS ksr_guru_ji;
USE ksr_guru_ji;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','customer','delivery_boy') NOT NULL DEFAULT 'customer',
  `profile_pic` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: categories (includes Kannada names & icons)
-- --------------------------------------------------------
CREATE TABLE `categories` (
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
CREATE TABLE `main_categories` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `kn` varchar(100) NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `sub_ids` text NOT NULL,  -- comma‑separated category slugs
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: products
-- --------------------------------------------------------
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `kn_name` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
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
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_product_category` (`category_id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: cart
-- --------------------------------------------------------
CREATE TABLE `cart` (
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
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL UNIQUE,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL,
  `delivery_charge` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('cod','card','upi','bank_transfer') NOT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `order_status` enum('pending','confirmed','processed','shipped','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  `delivery_boy_id` int(11) DEFAULT NULL,
  `shipping_address` text NOT NULL,
  `billing_address` text DEFAULT NULL,
  `placed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_order_user` (`user_id`),
  KEY `fk_order_delivery_boy` (`delivery_boy_id`),
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_delivery_boy` FOREIGN KEY (`delivery_boy_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: order_items
-- --------------------------------------------------------
CREATE TABLE `order_items` (
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
-- Table: payments
-- --------------------------------------------------------
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cod','card','upi','bank_transfer') NOT NULL,
  `status` enum('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_payment_order` (`order_id`),
  KEY `fk_payment_user` (`user_id`),
  CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: delivery_boys (extended info)
-- --------------------------------------------------------
CREATE TABLE `delivery_boys` (
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
CREATE TABLE `coupons` (
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
CREATE TABLE `banners` (
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
CREATE TABLE `notifications` (
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
-- Table: audit_logs (admin security audit trail)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`      int(11)      DEFAULT NULL,
  `action`       varchar(100) NOT NULL,
  `target_table` varchar(100) DEFAULT NULL,
  `record_id`    int(11)      DEFAULT NULL,
  `details`      text         DEFAULT NULL,
  `ip_address`   varchar(45)  DEFAULT NULL,
  `user_agent`   text         DEFAULT NULL,
  `created_at`   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user`       (`user_id`),
  KEY `idx_audit_action`     (`action`),
  KEY `idx_audit_created_at` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- View alias so both `audit_log` and `audit_logs` queries work
CREATE OR REPLACE VIEW `audit_log` AS SELECT * FROM `audit_logs`;

-- --------------------------------------------------------
-- Table: settings
-- --------------------------------------------------------
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL UNIQUE,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- INSERT SAMPLE DATA
-- ========================================================

-- Insert categories (slugs match your .php filenames)
INSERT INTO `categories` (`name`, `kn_name`, `slug`, `icon`, `status`) VALUES
('Air Freshener', 'ಏರ್ ಫ್ರೆಶ್ನರ್', 'air-freshner', 'fa-wind', 1),
('Bakery', 'ಬೇಕರಿ', 'bakery', 'fa-bread-slice', 1),
('Bath & Body', 'ಸ್ನಾನ ಮತ್ತು ದೇಹ', 'bath-body', 'fa-bath', 1),
('Cleaning Supplies', 'ಶುಚಿಗೊಳಿಸುವ ಸರಬರಾಜು', 'cleaning', 'fa-soap', 1),
('Cold Drinks', 'ಪಾನೀಯಗಳು', 'cold-drinks', 'fa-mug-hot', 1),
('Cooking Oil', 'ಅಡುಗೆ ಎಣ್ಣೆ', 'cooking-oil', 'fa-oil-can', 1),
('Dairy', 'ಡೈರಿ', 'dairy', 'fa-cheese', 1),
('Dishwash', 'ಡಿಶ್ವಾಶ್', 'dishwash', 'fa-hand-sparkles', 1),
('Dry Fruits', 'ಒಣ ಹಣ್ಣುಗಳು', 'dry-fruits', 'fa-seedling', 1),
('Grains & Cereals', 'ಧಾನ್ಯಗಳು', 'grains', 'fa-wheat-alt', 1),
('Hair Care', 'ಹೇರ್ ಕೇರ್', 'hair-care', 'fa-user-tie', 1),
('Health & Pharmacy', 'ಆರೋಗ್ಯ ಮತ್ತು ಔಷಧ', 'health-pharmacy', 'fa-capsules', 1),
('Home Care', 'ಮನೆ ಆರೈಕೆ', 'home-care', 'fa-home', 1),
('Kitchenware', 'ಅಡುಗೆ ಸಾಮಾನು', 'kitchen', 'fa-utensils', 1),
('Laundry', 'ಲಾಂಡ್ರಿ', 'laundry', 'fa-jug-detergent', 1),
('Paper Products', 'ಪೇಪರ್ ಉತ್ಪನ್ನಗಳು', 'paper-products', 'fa-toilet-paper', 1),
('Personal Care', 'ವೈಯಕ್ತಿಕ ಆರೈಕೆ', 'personal-care', 'fa-hand-holding-heart', 1),
('Pet Supplies', 'ಪಿಇಟಿ ಸರಬರಾಜು', 'pet-supplies', 'fa-dog', 1),
('Pickles', 'ಉಪ್ಪಿನಕಾಯಿ', 'pickles', 'fa-jar', 1),
('Plastic Household', 'ಪ್ಲಾಸ್ಟಿಕ್ ಗೃಹೋಪಯೋಗಿ', 'plastic-household', 'fa-cube', 1),
('Pooja Items', 'ಪೂಜಾ ಸಾಮಗ್ರಿಗಳು', 'pooja', 'fa-pray', 1),
('Ready to Eat', 'ಸಿದ್ಧ ಊಟ', 'ready-to-eat', 'fa-utensil-spoon', 1),
('Seasonal Items', 'ಋತುಮಾನದ ವಸ್ತುಗಳು', 'seasonal', 'fa-calendar-alt', 1),
('Snacks', 'ತಿಂಡಿಗಳು', 'snacks', 'fa-cookie', 1),
('Spices', 'ಮಸಾಲೆಗಳು', 'spices', 'fa-mortar-pestle', 1),
('Stationery', 'ಸ್ಟೇಷನರಿ', 'stationery', 'fa-pen', 1),
('Vegetables & Fruits', 'ಹಣ್ಣುಗಳು ಮತ್ತು ತರಕಾರಿಗಳು', 'vegetables-fruits', 'fa-carrot', 1);

-- Insert main categories (groupings)
INSERT INTO `main_categories` (`id`, `name`, `kn`, `emoji`, `sub_ids`) VALUES
('fruits-veg', 'Fruits & Vegetables', 'ಹಣ್ಣುಗಳು ಮತ್ತು ತರಕಾರಿಗಳು', '🍎', 'vegetables-fruits,seasonal'),
('grains-pulses', 'Grains, Pulses & Staples', 'ಧಾನ್ಯಗಳು, ಬೇಳೆಗಳು ಮತ್ತು ಸ್ಟೇಪಲ್ಸ್', '🌾', 'grains,ready-to-eat'),
('spices-oils', 'Spices, Oils & Essentials', 'ಮಸಾಲೆಗಳು, ಎಣ್ಣೆ ಮತ್ತು ಅಗತ್ಯಗಳು', '🧂', 'spices,cooking-oil,pickles'),
('dairy-bakery', 'Dairy & Bakery', 'ಡೈರಿ ಮತ್ತು ಬೇಕರಿ', '🥛', 'dairy,bakery'),
('snacks-beverages', 'Snacks & Beverages', 'ತಿಂಡಿಗಳು ಮತ್ತು ಪಾನೀಯಗಳು', '🍪', 'snacks,cold-drinks,dry-fruits'),
('personal-beauty', 'Personal Care & Beauty', 'ವೈಯಕ್ತಿಕ ಆರೈಕೆ ಮತ್ತು ಸೌಂದರ್ಯ', '🧴', 'bath-body,hair-care,personal-care'),
('health-baby', 'Baby & Health Care', 'ಬೇಬಿ ಮತ್ತು ಆರೋಗ್ಯ ಆರೈಕೆ', '👶', 'health-pharmacy'), -- baby-care not in list, keep simple
('home-cleaning', 'Home Cleaning & Laundry', 'ಮನೆ ಶುಚಿಗೊಳಿಸುವಿಕೆ ಮತ್ತು ಲಾಂಡ್ರಿ', '🧹', 'cleaning,dishwash,laundry,air-freshner'),
('household-kitchen', 'Household & Kitchen', 'ಗೃಹೋಪಯೋಗಿ ಮತ್ತು ಅಡುಗೆಮನೆ', '🏠', 'kitchen,plastic-household,paper-products'),
('pet-supplies', 'Pet Supplies', 'ಪಿಇಟಿ ಸರಬರಾಜು', '🐕', 'pet-supplies'),
('pooja', 'Pooja & Religious Items', 'ಪೂಜಾ ಮತ್ತು ಧಾರ್ಮಿಕ ವಸ್ತುಗಳು', '🪔', 'pooja'),
('office-school', 'Office & School', 'ಕಚೇರಿ ಮತ್ತು ಶಾಲೆ', '✏️', 'stationery');


-- Insert default admin user (password: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`) VALUES
('Admin', 'admin@ksrguruji.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543210', 'admin');

-- Insert default settings
INSERT INTO `settings` (`key`, `value`) VALUES
('site_name', 'KSR GURU JI'),
('site_email', 'info@ksrguruji.com'),
('delivery_charge', '15.00'),
('currency', '₹'),
('tax_percent', '5.00');

-- Indexes for performance
CREATE INDEX idx_orders_user_status ON orders(user_id, order_status);
CREATE INDEX idx_orders_delivery_boy ON orders(delivery_boy_id);
CREATE INDEX idx_products_category_status ON products(category_id, status);
CREATE INDEX idx_cart_user ON cart(user_id);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);

-- --------------------------------------------------------
-- Done
-- --------------------------------------------------------