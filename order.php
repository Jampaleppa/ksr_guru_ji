<?php
// ============================================================
// api/order.php - Order REST API
// ============================================================
require_once '../config.php';
require_once '../functions.php';

// --- CORS Headers ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Connect to Database
try {
    $pdo = getDBConnection();
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// --- Authentication ---
// Accept API key via header 'X-API-Key' or query param 'api_key'
// Also support session-based auth for admin (if called from same domain)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
$isAuthenticated = false;

// 1. Check API key (should be stored in config or database)
$validApiKey = 'YOUR_SECRET_API_KEY'; // Replace with a secure key, store in config
if ($apiKey === $validApiKey) {
    $isAuthenticated = true;
}

// 2. Fallback to session-based admin authentication
if (!$isAuthenticated && session_status() === PHP_SESSION_NONE) {
    session_start();
    if (isset($_SESSION['admin_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $isAuthenticated = true;
    }
}

if (!$isAuthenticated) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Provide valid API key or admin session.']);
    exit;
}

// --- Parse request ---
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

// --- Helper: JSON response ---
function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// --- Handle GET: List or single order ---
if ($method === 'GET') {
    try {
        if ($id > 0) {
            // Get single order with items
            $stmt = $pdo->prepare("
                SELECT o.*, u.name as customer_name, u.email as customer_email 
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                jsonResponse(['error' => 'Order not found'], 404);
            }

            // Get order items
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$id]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(['success' => true, 'order' => $order]);
        } else {
            // List orders with optional filters
            $filters = [];
            $params = [];
            $sql = "SELECT o.*, u.name as customer_name, u.email as customer_email 
                    FROM orders o 
                    LEFT JOIN users u ON o.user_id = u.id 
                    WHERE 1=1";

            if (!empty($_GET['status'])) {
                $sql .= " AND o.status = ?";
                $params[] = $_GET['status'];
            }
            if (!empty($_GET['from'])) {
                $sql .= " AND DATE(o.created_at) >= ?";
                $params[] = $_GET['from'];
            }
            if (!empty($_GET['to'])) {
                $sql .= " AND DATE(o.created_at) <= ?";
                $params[] = $_GET['to'];
            }
            if (!empty($_GET['customer_id'])) {
                $sql .= " AND o.user_id = ?";
                $params[] = (int)$_GET['customer_id'];
            }

            $sql .= " ORDER BY o.id DESC";
            if (!empty($_GET['limit'])) {
                $sql .= " LIMIT " . (int)$_GET['limit'];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch item counts in a single batch query
            if (!empty($orders)) {
                $orderIds = array_column($orders, 'id');
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                $cntStmt = $pdo->prepare("
                    SELECT order_id, COUNT(*) as cnt 
                    FROM order_items 
                    WHERE order_id IN ($placeholders) 
                    GROUP BY order_id
                ");
                $cntStmt->execute($orderIds);
                $counts = $cntStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                foreach ($orders as &$order) {
                    $order['item_count'] = (int)($counts[$order['id']] ?? 0);
                }
            }

            jsonResponse(['success' => true, 'orders' => $orders, 'count' => count($orders)]);
        }
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// --- Handle POST: Create new order ---
if ($method === 'POST') {
    // Validate required fields
    $required = ['name', 'email', 'phone', 'address', 'city', 'state', 'pincode', 'items'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            jsonResponse(['error' => "Missing required field: $field"], 400);
        }
    }

    // Validate email
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email address'], 400);
    }

    // Validate phone
    if (!preg_match('/^[0-9]{10,15}$/', $input['phone'])) {
        jsonResponse(['error' => 'Phone must be 10-15 digits'], 400);
    }

    // Validate pincode
    if (!preg_match('/^[0-9]{5,6}$/', $input['pincode'])) {
        jsonResponse(['error' => 'Pincode must be 5-6 digits'], 400);
    }

    // Validate items
    if (!is_array($input['items']) || count($input['items']) === 0) {
        jsonResponse(['error' => 'Items must be a non-empty array'], 400);
    }

    // Calculate total
    $total = 0;
    foreach ($input['items'] as $item) {
        if (!isset($item['price']) || !isset($item['quantity'])) {
            jsonResponse(['error' => 'Each item must have price and quantity'], 400);
        }
        $total += $item['price'] * $item['quantity'];
    }

    try {
        $pdo->beginTransaction();

        // Get or create user (if email exists, use it; else create)
        $userId = null;
        if (!empty($input['user_id'])) {
            $userId = (int)$input['user_id'];
        } else {
            // Check if user exists by email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$input['email']]);
            $existing = $stmt->fetch();
            if ($existing) {
                $userId = $existing['id'];
            } else {
                // Create new customer
                $passwordHash = !empty($input['password']) ? password_hash($input['password'], PASSWORD_DEFAULT) : password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, status, role) VALUES (?, ?, ?, ?, 'active', 'customer')");
                $stmt->execute([$input['name'], $input['email'], $input['phone'], $passwordHash]);
                $userId = $pdo->lastInsertId();
            }
        }

        // Generate order number
        $year = date('Y');
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn();
        $orderNumber = 'KSR-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        // Insert order
        $payment_method = $input['payment_method'] ?? 'cod';
        $status = $input['status'] ?? 'pending';
        $payment_status = $input['payment_status'] ?? 'pending';
        $notes = $input['notes'] ?? '';

        $stmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, user_id, name, email, phone, address, city, state, pincode,
                total, payment_method, status, payment_status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $orderNumber,
            $userId,
            $input['name'],
            $input['email'],
            $input['phone'],
            $input['address'],
            $input['city'],
            $input['state'],
            $input['pincode'],
            $total,
            $payment_method,
            $status,
            $payment_status,
            $notes
        ]);
        $orderId = $pdo->lastInsertId();

        // Insert order items
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($input['items'] as $item) {
            $productIdRaw = $item['product_id'] ?? null;
            $productId = (is_numeric($productIdRaw) && (int)$productIdRaw > 0) ? (int)$productIdRaw : null;
            $productName = $item['name'] ?? 'Product';
            $quantity = (int)$item['quantity'];
            $price = (float)$item['price'];
            $itemTotal = $quantity * $price;
            $stmt->execute([$orderId, $productId, $productName, $quantity, $price, $itemTotal]);
        }

        // Log initial status to timeline
        $stmt = $pdo->prepare("INSERT INTO order_timeline (order_id, status, notes, created_by) VALUES (?, 'pending', 'Order created via API.', ?)");
        $stmt->execute([$orderId, $userId]);

        $pdo->commit();

        // Return the created order
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        jsonResponse(['success' => true, 'order' => $order, 'message' => 'Order created successfully'], 201);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// --- Handle PUT: Update order status (or full update) ---
if ($method === 'PUT') {
    if (!$id) {
        jsonResponse(['error' => 'Order ID is required'], 400);
    }

    try {
        // Check if order exists
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Order not found'], 404);
        }

        // Build update fields
        $fields = [];
        $params = [];
        $allowedFields = ['status', 'payment_status', 'name', 'email', 'phone', 'address', 'city', 'state', 'pincode', 'notes'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $fields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }

        if (empty($fields)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }

        $sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Fetch updated order
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        jsonResponse(['success' => true, 'order' => $order, 'message' => 'Order updated']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// --- Handle DELETE: Delete order ---
if ($method === 'DELETE') {
    if (!$id) {
        jsonResponse(['error' => 'Order ID is required'], 400);
    }

    try {
        // Check if order exists
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Order not found'], 404);
        }

        // Delete order items first
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->execute([$id]);

        // Delete order
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['success' => true, 'message' => 'Order deleted']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

// --- If no method matched ---
jsonResponse(['error' => 'Method not allowed'], 405);