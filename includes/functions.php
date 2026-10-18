<?php
// ============================================================
// functions.php - Core Helper Functions for KSR GURU JI
// ============================================================

require_once 'includes/db.php';

// ============================================================
// PRODUCT FUNCTIONS
// ============================================================

/**
 * Get all products with optional category filter and search
 */
function getProducts($category = '', $search = '') {
    $pdo = getDBConnection();
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE 1=1";
    $params = [];

    if (!empty($category)) {
        $sql .= " AND (p.category_id = ? OR c.slug = ?)";
        $params[] = $category;
        $params[] = $category;
    }

    if (!empty($search)) {
        $sql .= " AND (p.name LIKE ? OR p.kn_name LIKE ? OR p.description LIKE ?)";
        $term = '%' . $search . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= " ORDER BY p.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get a single product by ID
 *
 * @param int $id
 * @return array|false
 */
function getProductById(int $id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           WHERE p.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Get products by category slug
 *
 * @param int $categoryId
 * @return array
 */
function getProductsByCategory(int $categoryId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? ORDER BY name");
    $stmt->execute([$categoryId]);
    return $stmt->fetchAll();
}

/**
 * Get products by category name (fallback)
 *
 * @param string $name
 * @return array
 */
function getProductsByCategoryName(string $name) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT p.* FROM products p 
                           JOIN categories c ON p.category_id = c.id 
                           WHERE c.name LIKE ? OR c.slug LIKE ?");
    $like = '%' . $name . '%';
    $stmt->execute([$like, $like]);
    return $stmt->fetchAll();
}

/**
 * Get featured product (for AI showcase)
 */
function getFeaturedProduct() {
    $pdo = getDBConnection();
    // Get a random product or the most popular one
    $stmt = $pdo->query("SELECT p.*, c.name as category_name 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          ORDER BY p.id DESC LIMIT 1");
    return $stmt->fetch();
}

/**
 * Get product recommendations based on a product ID.
 * Optimized to remove random database sorting query execution times.
 *
 * @param int $productId
 * @param int $limit
 * @return array
 */
function getRecommendations(int $productId, int $limit = 4) {
    $pdo = getDBConnection();
    $product = getProductById($productId);

    // Optimized helper to select random products matching filters
    $getRandomRowSelect = function(array $excludeIds, ?string $categoryId = null, int $limitCount = 4) use ($pdo) {
        $conditions = [];
        $params = [];
        
        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $conditions[] = "id NOT IN ($placeholders)";
            $params = array_merge($params, $excludeIds);
        }
        
        if ($categoryId !== null) {
            $conditions[] = "category_id = ?";
            $params[] = $categoryId;
        }
        
        $whereSql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Fetch matching product IDs (utilizing indices)
        $stmt = $pdo->prepare("SELECT id FROM products $whereSql");
        $stmt->execute($params);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ids)) {
            return [];
        }
        
        // Randomize IDs in memory
        if (count($ids) <= $limitCount) {
            $selectedIds = $ids;
        } else {
            $randomKeys = array_rand($ids, $limitCount);
            $selectedIds = is_array($randomKeys) ? array_map(fn($key) => $ids[$key], $randomKeys) : [$ids[$randomKeys]];
        }
        
        // Retrieve full records
        $loadPlaceholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $stmtLoad = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug 
                                   FROM products p 
                                   LEFT JOIN categories c ON p.category_id = c.id 
                                   WHERE p.id IN ($loadPlaceholders)");
        $stmtLoad->execute($selectedIds);
        $rows = $stmtLoad->fetchAll();
        shuffle($rows);
        return $rows;
    };

    if (!$product) {
        return $getRandomRowSelect([$productId], null, $limit);
    }

    // 1. Get products from same category
    $recommendations = $getRandomRowSelect([$productId], $product['category_id'], $limit);

    // 2. If not enough recommendations found, pull from other categories
    if (count($recommendations) < $limit) {
        $remaining = $limit - count($recommendations);
        $alreadySelected = array_merge([$productId], array_column($recommendations, 'id'));
        $moreRecommendations = $getRandomRowSelect($alreadySelected, null, $remaining);
        $recommendations = array_merge($recommendations, $moreRecommendations);
    }

    return array_slice($recommendations, 0, $limit);
}

/**
 * Update product stock
 *
 * @param int $productId
 * @param int $quantity
 * @return bool
 */
function updateProductStock(int $productId, int $quantity) {
    $pdo = getDBConnection();
    // Check if stock column exists in products table
    $check = $pdo->query("SHOW COLUMNS FROM products LIKE 'stock'")->fetch();
    if (!$check) {
        return true;
    }
    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
    return $stmt->execute([$quantity, $productId, $quantity]);
}

/**
 * Check if product is in stock
 *
 * @param int $productId
 * @param int $quantity
 * @return bool
 */
function isProductInStock(int $productId, int $quantity = 1) {
    $pdo = getDBConnection();
    // Check if stock column exists in products table
    $check = $pdo->query("SHOW COLUMNS FROM products LIKE 'stock'")->fetch();
    if (!$check) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $stock = $stmt->fetchColumn();
    return $stock !== false && $stock >= $quantity;
}

// ============================================================
// CATEGORY FUNCTIONS
// ============================================================

/**
 * Get all categories
 */
function getCategories() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Get a category by ID
 *
 * @param int $id
 * @return array|false
 */
function getCategoryById(int $id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Get a category by slug
 *
 * @param string $slug
 * @return array|false
 */
function getCategoryBySlug(string $slug) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

/**
 * Get main categories (for home page grouping)
 */
function getMainCategories() {
    // This could be stored in a separate table or you can define them here
    // For now, we'll use a static list that matches the frontend
    return [
        ['id' => 'fruits-vegetables', 'name' => 'Fruits & Vegetables', 'kn' => 'ಹಣ್ಣುಗಳು ಮತ್ತು ತರಕಾರಿಗಳು', 'emoji' => '🍎', 'sub_ids' => '1,2,3'],
        ['id' => 'grains-pulses', 'name' => 'Grains, Pulses & Staples', 'kn' => 'ಧಾನ್ಯಗಳು, ಬೇಳೆಗಳು ಮತ್ತು ಸ್ಟೇಪಲ್ಸ್', 'emoji' => '🌾', 'sub_ids' => '4,5,6'],
        ['id' => 'spices-oils', 'name' => 'Spices, Oils & Cooking Essentials', 'kn' => 'ಮಸಾಲೆಗಳು, ಎಣ್ಣೆ ಮತ್ತು ಅಡುಗೆ ಅಗತ್ಯಗಳು', 'emoji' => '🧂', 'sub_ids' => '7,8,9'],
        ['id' => 'dairy-bakery', 'name' => 'Dairy & Bakery', 'kn' => 'ಡೈರಿ ಮತ್ತು ಬೇಕರಿ', 'emoji' => '🥛', 'sub_ids' => '10,11'],
        ['id' => 'snacks-beverages', 'name' => 'Snacks & Beverages', 'kn' => 'ತಿಂಡಿಗಳು ಮತ್ತು ಪಾನೀಯಗಳು', 'emoji' => '🍪', 'sub_ids' => '12,13,14'],
        ['id' => 'frozen-imported', 'name' => 'Frozen & Imported Foods', 'kn' => 'ಫ್ರೋಜನ್ ಮತ್ತು ಆಮದು ಆಹಾರ', 'emoji' => '🧊', 'sub_ids' => '15,16,17'],
        ['id' => 'personal-care', 'name' => 'Personal Care & Beauty', 'kn' => 'ವೈಯಕ್ತಿಕ ಆರೈಕೆ ಮತ್ತು ಸೌಂದರ್ಯ', 'emoji' => '🧴', 'sub_ids' => '18,19,20,21'],
        ['id' => 'baby-health', 'name' => 'Baby & Health Care', 'kn' => 'ಬೇಬಿ ಮತ್ತು ಆರೋಗ್ಯ ಆರೈಕೆ', 'emoji' => '👶', 'sub_ids' => '22,23'],
        ['id' => 'home-cleaning', 'name' => 'Home Cleaning & Laundry', 'kn' => 'ಮನೆ ಶುಚಿಗೊಳಿಸುವಿಕೆ ಮತ್ತು ಲಾಂಡ್ರಿ', 'emoji' => '🧹', 'sub_ids' => '24,25,26,27'],
        ['id' => 'household-kitchen', 'name' => 'Household & Kitchen', 'kn' => 'ಗೃಹೋಪಯೋಗಿ ಮತ್ತು ಅಡುಗೆಮನೆ', 'emoji' => '🏠', 'sub_ids' => '28,29,30'],
        ['id' => 'pet-supplies', 'name' => 'Pet Supplies', 'kn' => 'ಪಿಇಟಿ ಸರಬರಾಜು', 'emoji' => '🐕', 'sub_ids' => '31'],
        ['id' => 'pooja-items', 'name' => 'Pooja & Religious Items', 'kn' => 'ಪೂಜಾ ಮತ್ತು ಧಾರ್ಮಿಕ ವಸ್ತುಗಳು', 'emoji' => '🪔', 'sub_ids' => '32'],
        ['id' => 'office-school', 'name' => 'Office & School', 'kn' => 'ಕಚೇರಿ ಮತ್ತು ಶಾಲೆ', 'emoji' => '✏️', 'sub_ids' => '33']
    ];
}

// ============================================================
// CART FUNCTIONS
// ============================================================

/**
 * Get cart items from session
 */
function getCart() {
    return $_SESSION['cart'] ?? [];
}

/**
 * Get cart count (total quantity)
 */
function getCartCount() {
    $cart = getCart();
    return array_sum(array_column($cart, 'qty'));
}

/**
 * Get cart total price
 */
function getCartTotal() {
    $cart = getCart();
    return array_sum(array_map(fn($item) => $item['price'] * $item['qty'], $cart));
}

/**
 * Add item to cart
 *
 * @param mixed $productId
 * @param int $quantity
 * @param string|null $variant
 * @param float|int|null $customPrice
 * @return bool
 */
function addToCart($productId, int $quantity = 1, ?string $variant = null, $customPrice = null) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $product = getProductById($productId);
    if (!$product) return false;

    // Build unique ID for variant
    $cartItemId = $productId;
    if ($variant) {
        $cartItemId .= ':' . md5($variant);
    }

    // Check if item already exists
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $cartItemId) {
            $item['qty'] += $quantity;
            return true;
        }
    }

    // Add new item
    $_SESSION['cart'][] = [
        'id' => $cartItemId,
        'product_id' => $productId,
        'name' => $product['name'] . ($variant ? ' (' . $variant . ')' : ''),
        'kn' => $product['kn_name'] ?? '',
        'price' => $customPrice ?? $product['price'],
        'qty' => $quantity,
        'image' => $product['image_url'] ?? '',
        'variant' => $variant
    ];

    return true;
}

/**
 * Update cart item quantity
 *
 * @param string $itemId
 * @param int $quantity
 * @return bool
 */
function updateCartItem(string $itemId, int $quantity) {
    if (!isset($_SESSION['cart'])) return false;

    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $itemId) {
            if ($quantity <= 0) {
                return removeFromCart($itemId);
            }
            $item['qty'] = $quantity;
            return true;
        }
    }
    return false;
}

/**
 * Remove item from cart
 *
 * @param string $itemId
 * @return bool
 */
function removeFromCart(string $itemId) {
    if (!isset($_SESSION['cart'])) return false;
    $_SESSION['cart'] = array_filter($_SESSION['cart'], fn($item) => $item['id'] != $itemId);
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    return true;
}

/**
 * Clear entire cart
 */
function clearCart() {
    $_SESSION['cart'] = [];
    return true;
}

// ============================================================
// USER FUNCTIONS
// ============================================================

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Get user by email
 *
 * @param string $email
 * @return array|false
 */
function getUserByEmail(string $email) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

/**
 * Get user by ID
 *
 * @param int $id
 * @return array|false
 */
function getUserById(int $id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Create a new user
 *
 * @param string $name
 * @param string $email
 * @param string $password
 * @param string $phone
 * @param string $role
 * @return bool
 */
function createUser(string $name, string $email, string $password, string $phone = '', string $role = 'customer') {
    $pdo = getDBConnection();
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
    return $stmt->execute([$name, $email, $phone, $hashed, $role]);
}

/**
 * Update user profile
 *
 * @param int $userId
 * @param array $data
 * @return bool
 */
function updateUser(int $userId, array $data) {
    $pdo = getDBConnection();
    $fields = [];
    $params = [];
    $allowed = ['name', 'email', 'phone', 'delivery_zone', 'status'];

    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (isset($data['password']) && !empty($data['password'])) {
        $fields[] = "password_hash = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (empty($fields)) return false;

    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Verify user login
 *
 * @param string $email
 * @param string $password
 * @return array|false
 */
function verifyUser(string $email, string $password) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return false;
}

// ============================================================
// ORDER FUNCTIONS
// ============================================================

/**
 * Create a new order
 *
 * @param mixed $userId
 * @param array $customerData
 * @param array $cartItems
 * @param float|int $total
 * @param string $paymentMethod
 * @param string $notes
 * @return array|false
 */
function createOrder($userId, array $customerData, array $cartItems, $total, string $paymentMethod, string $notes = '') {
    $pdo = getDBConnection();

    try {
        $pdo->beginTransaction();

        // Generate order number
        $year = date('Y');
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn();
        $orderNumber = 'KSR-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        // Insert order
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, user_id, name, email, phone, address, city, state, pincode,
                total, payment_method, notes, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $orderNumber,
            $userId,
            $customerData['name'],
            $customerData['email'],
            $customerData['phone'],
            $customerData['address'],
            $customerData['city'],
            $customerData['state'],
            $customerData['pincode'],
            $total,
            $paymentMethod,
            $notes
        ]);
        $orderId = $pdo->lastInsertId();

        // Insert order items
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
        foreach ($cartItems as $item) {
            $productId = $item['id'];
            if (strpos($productId, ':') !== false) {
                $parts = explode(':', $productId);
                $productId = $parts[0];
            }
            $stmt->execute([$orderId, (int)$productId, $item['name'], $item['qty'], $item['price']]);
        }

        $pdo->commit();
        return ['order_id' => $orderId, 'order_number' => $orderNumber];

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Order creation error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get orders for a user
 *
 * @param mixed $userId
 * @param int|null $limit
 * @return array
 */
function getUserOrders($userId, ?int $limit = null) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC";
    if ($limit) {
        $sql .= " LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $limit]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
    }
    return $stmt->fetchAll();
}

/**
 * Get order by ID
 *
 * @param int $orderId
 * @return array|false
 */
function getOrderById(int $orderId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    return $stmt->fetch();
}

/**
 * Get order items
 *
 * @param int $orderId
 * @return array
 */
function getOrderItems(int $orderId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT oi.*, p.name as product_name 
                           FROM order_items oi 
                           LEFT JOIN products p ON oi.product_id = p.id 
                           WHERE oi.order_id = ?");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

/**
 * Update order status
 *
 * @param int $orderId
 * @param string $status
 * @return bool
 */
function updateOrderStatus(int $orderId, string $status) {
    $pdo = getDBConnection();
    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) return false;
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $orderId]);
}

// ============================================================
// DELIVERY BOY FUNCTIONS
// ============================================================

/**
 * Get delivery boy by ID
 *
 * @param int $id
 * @return array|false
 */
function getDeliveryBoy(int $id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'delivery'");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Get orders assigned to a delivery boy
 *
 * @param int $deliveryBoyId
 * @param string|null $status
 * @return array
 */
function getDeliveryBoyOrders(int $deliveryBoyId, ?string $status = null) {
    $pdo = getDBConnection();
    $sql = "SELECT * FROM orders WHERE driver_id = ?";
    if ($status) {
        $sql .= " AND status = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$deliveryBoyId, $status]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$deliveryBoyId]);
    }
    return $stmt->fetchAll();
}

/**
 * Assign an order to a delivery boy
 *
 * @param int $orderId
 * @param int $deliveryBoyId
 * @return bool
 */
function assignOrderToDeliveryBoy(int $orderId, int $deliveryBoyId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE orders SET driver_id = ?, status = 'assigned', assigned_at = NOW() WHERE id = ?");
    return $stmt->execute([$deliveryBoyId, $orderId]);
}

/**
 * Update delivery status of an order
 *
 * @param int $orderId
 * @param string $status
 * @return bool
 */
function updateDeliveryStatus(int $orderId, string $status) {
    $pdo = getDBConnection();
    $allowed = ['assigned', 'picked_up', 'in_transit', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) return false;
    $sql = "UPDATE orders SET status = ?";
    if ($status === 'delivered') {
        $sql .= ", delivered_at = NOW()";
    }
    $sql .= " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$status, $orderId]);
}

// ============================================================
// ADMIN FUNCTIONS
// ============================================================

/**
 * Get dashboard stats
 */
function getDashboardStats() {
    $pdo = getDBConnection();
    $stats = [];

    $stats['total_orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stats['pending_orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $stats['total_revenue'] = $pdo->query("SELECT SUM(total) FROM orders WHERE status = 'delivered'")->fetchColumn() ?: 0;
    $stats['total_customers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity < 5")->fetchColumn();

    return $stats;
}

/**
 * Get revenue by period (days)
 */
function getRevenueByDays($days = 7) {
    $pdo = getDBConnection();
    $result = [];
    
    // Initialize array with 0.0 for last N days
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $result[$date] = 0.0;
    }
    
    $startDate = date('Y-m-d 00:00:00', strtotime("-".($days - 1)." days"));
    $endDate = date('Y-m-d 23:59:59');
    
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date_only, COALESCE(SUM(total), 0) as daily_revenue 
        FROM orders 
        WHERE created_at BETWEEN ? AND ? 
          AND status = 'delivered' 
        GROUP BY DATE(created_at)
    ");
    $stmt->execute([$startDate, $endDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[$row['date_only']] = (float)$row['daily_revenue'];
    }
    
    return $result;
}

/**
 * Get recent orders
 */
function getRecentOrders($limit = 10) {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT o.*, u.name as customer_name 
                         FROM orders o 
                         LEFT JOIN users u ON o.user_id = u.id 
                         ORDER BY o.id DESC LIMIT $limit");
    return $stmt->fetchAll();
}

/**
 * Get top selling products
 */
function getTopProducts($limit = 5) {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT p.id, p.name, SUM(oi.quantity) as total_sold 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        GROUP BY p.id 
        ORDER BY total_sold DESC 
        LIMIT $limit
    ");
    return $stmt->fetchAll();
}

// ============================================================
// FORMATTING & UTILITY FUNCTIONS
// ============================================================

/**
 * Format currency (INR)
 *
 * @param float|int $amount
 * @return string
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Get status badge HTML
 *
 * @param string $status
 * @return string
 */
function getStatusBadge(string $status) {
    $classes = [
        'pending' => 'pending',
        'processing' => 'processing',
        'shipped' => 'shipped',
        'delivered' => 'delivered',
        'cancelled' => 'cancelled',
        'active' => 'active',
        'inactive' => 'inactive'
    ];
    $class = $classes[$status] ?? 'pending';
    return '<span class="status-badge ' . $class . '">' . ucfirst($status) . '</span>';
}

/**
 * Generate a random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate a unique order number
 */
function generateOrderNumber() {
    $year = date('Y');
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = $year");
    $count = $stmt->fetchColumn();
    return 'KSR-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

/**
 * Get delivery time estimate
 */
function getDeliveryEstimate() {
    return date('h:i A', strtotime('+45 minutes'));
}

/**
 * Convert image path for web
 *
 * @param string $path
 * @return string
 */
function getImageUrl(string $path) {
    if (empty($path)) return '';
    if (strpos($path, 'http') === 0) return $path;
    if (strpos($path, 'uploads/') === 0) return '/' . $path;
    return '/' . $path;
}

// ============================================================
// EMAIL FUNCTIONS (Placeholder - configure as needed)
// ============================================================

/**
 * Send an email (placeholder - implement with PHPMailer or similar)
 *
 * @param string $to
 * @param string $subject
 * @param string $message
 * @param string $from
 * @return bool
 */
function sendEmail(string $to, string $subject, string $message, string $from = 'noreply@ksrguru.com') {
    // TODO: Implement email sending
    // For now, just log
    error_log("Email to $to: $subject");
    return true;
}

/**
 * Send order confirmation email
 *
 * @param int $orderId
 * @return bool
 */
function sendOrderConfirmation(int $orderId) {
    $order = getOrderById($orderId);
    if (!$order) return false;
    $items = getOrderItems($orderId);

    $subject = 'Order Confirmation - ' . $order['order_number'];
    $message = "Thank you for your order!\n\n";
    $message .= "Order #: " . $order['order_number'] . "\n";
    $message .= "Date: " . date('d M Y, h:i A', strtotime($order['created_at'])) . "\n\n";
    $message .= "Items:\n";
    foreach ($items as $item) {
        $message .= "- " . $item['product_name'] . " x " . $item['quantity'] . " = ₹" . number_format($item['price'] * $item['quantity'], 2) . "\n";
    }
    $message .= "\nTotal: ₹" . number_format($order['total'], 2) . "\n";
    $message .= "\nDelivery Address: " . $order['address'] . ", " . $order['city'] . ", " . $order['state'] . " - " . $order['pincode'];

    return sendEmail($order['email'], $subject, $message);
}

// ============================================================
// SESSION HELPERS
// ============================================================

/**
 * Set flash message
 *
 * @param string $key
 * @param string $message
 * @param string $type
 * @return void
 */
function setFlash(string $key, string $message, string $type = 'success') {
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

/**
 * Get flash message
 *
 * @param string $key
 * @return array|null
 */
function getFlash(string $key) {
    if (isset($_SESSION['flash'][$key])) {
        $flash = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $flash;
    }
    return null;
}

// ============================================================
// VALIDATION FUNCTIONS
// ============================================================

/**
 * Validate email
 *
 * @param string $email
 * @return bool
 */
function isValidEmail(string $email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Indian format)
 *
 * @param string $phone
 * @return bool
 */
function isValidPhone(string $phone) {
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

/**
 * Validate pincode (Indian format)
 *
 * @param string $pincode
 * @return bool
 */
function isValidPincode(string $pincode) {
    return preg_match('/^[0-9]{5,6}$/', $pincode);
}

/**
 * Sanitize input
 *
 * @param string $input
 * @return string
 */
function sanitize(string $input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// JSON RESPONSE HELPERS
// ============================================================

/**
 * Send JSON response
 *
 * @param mixed $data
 * @param int $statusCode
 * @return void
 */
function jsonResponse($data, int $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send success JSON response
 */
function jsonSuccess($data = [], $message = 'Success') {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

/**
 * Send error JSON response
 */
function jsonError($message = 'Error', $statusCode = 400) {
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}

if (!function_exists('esc')) {
    /**
     * Escape value for safe HTML output.
     * 
     * @param mixed $value
     * @return string
     */
    function esc($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('normalizeImagePath')) {
    function normalizeImagePath(?string $rawPath): string {
        if (empty($rawPath)) return '';
        $clean = str_replace('\\', '/', $rawPath);
        if (preg_match('#(?:.*)(images/.*)$#i', $clean, $matches)) {
            return '../' . $matches[1];
        }
        return '../images/' . basename($clean);
    }
}