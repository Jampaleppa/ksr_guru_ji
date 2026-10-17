-- Medicine (tablet.php) order storage.
-- The e-commerce `orders` table has an incompatible schema, so medicine
-- orders are kept in a dedicated table.
CREATE TABLE IF NOT EXISTS `pharmacy_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_code` VARCHAR(32) NOT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `customer_phone` VARCHAR(20) NOT NULL,
  `address_text` TEXT NULL,
  `latitude` DECIMAL(10,7) NULL,
  `longitude` DECIMAL(10,7) NULL,
  `accuracy_m` DECIMAL(10,2) NULL,
  `maps_url` VARCHAR(255) NULL,
  `items_json` LONGTEXT NULL,
  `items_text` LONGTEXT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `delivery` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `needs_rx` TINYINT(1) NOT NULL DEFAULT 0,
  `rx_file` VARCHAR(255) NULL,
  `customer_note` VARCHAR(500) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `reject_reason` VARCHAR(255) NULL,
  `verified_by` VARCHAR(60) NULL,
  `verified_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_po_code` (`order_code`),
  KEY `idx_po_status` (`status`),
  KEY `idx_po_phone` (`customer_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
