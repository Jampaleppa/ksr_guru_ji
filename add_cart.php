<?php
// ============================================================
// add_cart.php - Add product to cart (AJAX + fallback)
// ============================================================
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Start session for cart
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- Get parameters ---
// Basic product ID and quantity
$productIdRaw = isset($_POST['id']) ? trim($_POST['id']) : (isset($_GET['id']) ? trim($_GET['id']) : '');
$quantity = isset($_POST['qty']) ? (int)$_POST['qty'] : (isset($_GET['qty']) ? (int)$_GET['qty'] : 1);

// Optional variant (e.g., weight, size)
$variant = isset($_POST['variant']) ? trim($_POST['variant']) : (isset($_GET['variant']) ? trim($_GET['variant']) : null);

// Optional custom price (if you want to override the DB price)
$customPrice = isset($_POST['custom_price']) ? (float)$_POST['custom_price'] : (isset($_GET['custom_price']) ? (float)$_GET['custom_price'] : 0);

// Redirect URL (for non‑AJAX requests)
$redirectUrl = isset($_POST['redirect']) ? $_POST['redirect'] : (isset($_GET['redirect']) ? $_GET['redirect'] : null);

// --- Validate product ID ---
if ($productIdRaw === '') {
    $error = 'Invalid product ID.';
} else {
    $productId = $productIdRaw;
    // --- Fetch product from database ---
    try {
        $pdo = getDBConnection();
        
        // Statically define columns in products table to avoid slow SHOW COLUMNS query
        $columns = ['id', 'name', 'kn_name', 'price', 'category_id', 'icon', 'emoji', 'is_featured', 'created_at', 'images', 'stock'];

        $selectedFields = ['id', 'name', 'kn_name', 'price'];
        
        $hasStock = in_array('stock', $columns);
        if ($hasStock) {
            $selectedFields[] = 'stock';
        }
        
        $imgCol = null;
        if (in_array('image_url', $columns)) {
            $imgCol = 'image_url';
        } elseif (in_array('images', $columns)) {
            $imgCol = 'images AS image_url';
        }
        if ($imgCol) {
            $selectedFields[] = $imgCol;
        }

        $fieldsStr = implode(', ', $selectedFields);
        
        $stmt = $pdo->prepare("SELECT $fieldsStr FROM products WHERE id = ?");
        $stmt->execute([is_numeric($productId) ? (int)$productId : $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        $clientName = isset($_POST['name']) ? trim($_POST['name']) : (isset($_GET['name']) ? trim($_GET['name']) : '');

        if (!$product || ($clientName !== '' && strcasecmp($product['name'], $clientName) !== 0)) {
            // Check if product details were submitted by the client (allows fallback/static products to be added)
            if ($clientName !== '') {
                $clientKn = isset($_POST['kn']) ? trim($_POST['kn']) : (isset($_GET['kn']) ? trim($_GET['kn']) : '');
                $clientImage = isset($_POST['image']) ? trim($_POST['image']) : (isset($_GET['image']) ? trim($_GET['image']) : '');
                
                // Determine price: custom_price, price, or fallback
                $clientPrice = $customPrice > 0 ? $customPrice : (isset($_POST['price']) ? (float)$_POST['price'] : (isset($_GET['price']) ? (float)$_GET['price'] : 0.0));
                
                // Construct a unique productId prefix to avoid collisions
                $refererPath = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) : '';
                $pageName = $refererPath ? pathinfo($refererPath, PATHINFO_FILENAME) : 'fallback';
                $virtualId = $productId;
                if (!empty($pageName) && $pageName !== 'index' && !str_starts_with($productId, $pageName . '_')) {
                    $virtualId = $pageName . '_' . $productId;
                }
                
                $product = [
                    'id' => $virtualId,
                    'name' => $clientName,
                    'kn_name' => $clientKn,
                    'price' => $clientPrice,
                    'stock' => null,
                    'image_url' => $clientImage
                ];
                
                $productId = $virtualId;
            } else {
                $error = 'Product not found.';
            }
        }

        if (empty($error)) {
            // Supply empty if not exists in product array
            if (!isset($product['stock'])) {
                $product['stock'] = null;
            }
            if (!isset($product['image_url'])) {
                $product['image_url'] = '';
            }

            // --- Check stock (if stock column is not NULL) ---
            if ($product['stock'] !== null && $product['stock'] < $quantity) {
                $error = 'Not enough stock available. Maximum: ' . $product['stock'];
            } else {
                // Determine final price (custom price overrides DB price)
                $finalPrice = ($customPrice > 0) ? $customPrice : $product['price'];

                // --- Build item identifier (handle variant) ---
                // If a variant is provided, append it to the ID so different variants are separate in cart
                $cartItemId = $productId;
                if ($variant !== null) {
                    $cartItemId .= ':' . md5($variant); // hash to keep unique
                }

                // Check if item already in cart (with same variant)
                $found = false;
                foreach ($_SESSION['cart'] as &$item) {
                    if ((string)$item['id'] === (string)$cartItemId) {
                        // If same variant, just increase quantity
                        $item['qty'] += $quantity;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    // New item – build cart entry
                    $cartItem = [
                        'id' => $cartItemId,
                        'product_id' => $productId,
                        'name' => $product['name'],
                        'kn' => $product['kn_name'] ?? '',
                        'price' => $finalPrice,
                        'qty' => $quantity,
                        'image' => $product['image_url'] ?? '',
                    ];
                    // If variant is set, store it separately for display
                    if ($variant !== null) {
                        $cartItem['variant'] = $variant;
                        $cartItem['name'] .= ' (' . $variant . ')';
                        $cartItem['kn'] .= ' (' . $variant . ')';
                    }
                    $_SESSION['cart'][] = $cartItem;
                }

                $success = true;
                $message = 'Product added to cart.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// --- Determine response type ---
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
$secFetchMode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';

// Treat as JSON if: requested via AJAX, wants application/json, fetch cors mode, or POST without explicit redirect
$wantsJson = $isAjax 
    || strpos($acceptHeader, 'application/json') !== false 
    || $secFetchMode === 'cors'
    || (empty($redirectUrl) && !empty($_POST));

if ($wantsJson) {
    // Discard any PHP notices or accidental whitespace
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    if (isset($error)) {
        echo json_encode(['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    } else {
        // Return updated cart info
        $cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
        $cartTotal = array_sum(array_map(fn($item) => $item['price'] * $item['qty'], $_SESSION['cart']));
        echo json_encode([
            'success' => true,
            'message' => $message ?? 'Product added to cart.',
            'added_name' => $product['name'] ?? '',
            'added_qty' => $quantity,
            'cart' => array_values($_SESSION['cart']),
            'cartCount' => $cartCount,
            'cartTotal' => $cartTotal,
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
} else {
    // --- Fallback redirect ---
    if ($redirectUrl) {
        $redirect = $redirectUrl;
    } elseif (!empty($_SERVER['HTTP_REFERER'])) {
        $redirect = $_SERVER['HTTP_REFERER'];
    } else {
        $redirect = 'index.php';
    }

    // Append success/error message
    $sep = strpos($redirect, '?') === false ? '?' : '&';
    if (isset($error)) {
        $redirect .= $sep . 'cart_error=' . urlencode($error);
    } else {
        $redirect .= $sep . 'cart_success=1';
    }

    header('Location: ' . $redirect);
    exit;
}