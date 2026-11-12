
-- --------------------------------------------------------
-- Table: product_suggestions
-- Stores customer requests for missing products across all 36+ categories.
-- Workflow: Pending -> Approved / Sourced -> Assigned to Delivery / Added to Catalog -> Completed / Rejected
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_suggestions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` varchar(50) NOT NULL UNIQUE,
  `user_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `preferred_price` decimal(10,2) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_phone` varchar(30) NOT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `status` enum('pending','under_review','approved','sourced','added_to_catalog','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `assigned_delivery_id` int(11) DEFAULT NULL,
  `supplier_info` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sugg_status` (`status`),
  KEY `idx_sugg_phone` (`customer_phone`),
  KEY `idx_sugg_category` (`category_id`),
  KEY `idx_sugg_delivery` (`assigned_delivery_id`),
  CONSTRAINT `fk_sugg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sugg_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

