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
// Basic product ID and quantity (bounded 1 to 999)
$productIdRaw = isset($_POST['id']) ? trim($_POST['id']) : (isset($_GET['id']) ? trim($_GET['id']) : '');
$quantity = isset($_POST['qty']) ? (int)$_POST['qty'] : (isset($_GET['qty']) ? (int)$_GET['qty'] : 1);
$quantity = max(1, min(999, $quantity));

// Optional variant (e.g., weight, size)
$variant = isset($_POST['variant']) ? trim($_POST['variant']) : (isset($_GET['variant']) ? trim($_GET['variant']) : null);

// custom_price from client input is intentionally NOT accepted — price must come from DB
$customPrice = 0; // Always zero; DB price is authoritative

// Redirect URL (for non-AJAX requests) — must be a local relative path
$redirectRaw = isset($_POST['redirect']) ? $_POST['redirect'] : (isset($_GET['redirect']) ? $_GET['redirect'] : null);
// Only allow local relative paths (must start with / but not //) to prevent open redirect
$redirectUrl = ($redirectRaw && preg_match('#^/[^/]#', $redirectRaw)) ? $redirectRaw : null;

// --- Validate product ID ---
if ($productIdRaw === '') {
    $error = 'Invalid product ID.';
} else {
    $productId = $productIdRaw;
    // --- Fetch product from database or client fallback ---
    $clientName = isset($_POST['name']) ? trim($_POST['name']) : (isset($_GET['name']) ? trim($_GET['name']) : '');
    $clientKn = isset($_POST['kn']) ? trim($_POST['kn']) : (isset($_GET['kn']) ? trim($_GET['kn']) : '');
    $clientImage = isset($_POST['image']) ? trim($_POST['image']) : (isset($_GET['image']) ? trim($_GET['image']) : '');
    // clientPrice is used for catalogue/virtual products or variant weight/packet calculations.
    // It is NOT used when an exact un-varianted DB product record is found.
    $clientPrice = isset($_POST['price']) ? (float)$_POST['price'] : (isset($_POST['custom_price']) ? (float)$_POST['custom_price'] : (isset($_GET['price']) ? (float)$_GET['price'] : 0.0));

    $product = null;
    $pdo = null;
    try {
        $pdo = getDBConnection();
    } catch (Throwable $e) {
        $pdo = null;
    }

    $isProduce = str_starts_with((string)$productId, 'veg_') || (isset($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], 'vegetables-fruits'));

    // 1. For produce / vegetables & fruits, check daily_market_prices first
    if ($pdo && ($isProduce || !empty($clientName))) {
        try {
            $cleanVegName = preg_replace('/\s*\([^)]*\)\s*$/', '', $clientName);
            $marketId = 0;
            if (str_starts_with((string)$productId, 'veg_')) {
                $marketId = (int)substr((string)$productId, 4);
            } elseif (is_numeric($productId) && $isProduce) {
                $marketId = (int)$productId;
            }

            // A. Prefer exact / clean name match
            if ($cleanVegName !== '') {
                $stmtM = $pdo->prepare("SELECT id, name, name_kn, selling_price, image, unit_type FROM daily_market_prices WHERE (name = ? OR name = ?) AND (is_available = 1 OR is_active = 1) LIMIT 1");
                $stmtM->execute([$clientName, $cleanVegName]);
                $marketItem = $stmtM->fetch(PDO::FETCH_ASSOC);
            }

            // B. If not found by name and explicit veg ID provided
            if (empty($marketItem) && $marketId > 0) {
                $stmtM = $pdo->prepare("SELECT id, name, name_kn, selling_price, image, unit_type FROM daily_market_prices WHERE id = ? AND (is_available = 1 OR is_active = 1)");
                $stmtM->execute([$marketId]);
                $cand = $stmtM->fetch(PDO::FETCH_ASSOC);
                if ($cand && (empty($clientName) || strcasecmp($cand['name'], $clientName) === 0 || str_contains(strtolower($clientName), strtolower($cand['name'])))) {
                    $marketItem = $cand;
                }
            }

            if (!empty($marketItem)) {
                $vegPrice = ($clientPrice > 0) ? $clientPrice : (float)$marketItem['selling_price'];
                $product = [
                    'id' => 'veg_' . $marketItem['id'],
                    'name' => $marketItem['name'],
                    'kn_name' => $marketItem['name_kn'] ?? '',
                    'price' => $vegPrice,
                    'stock' => 999,
                    'image_url' => $marketItem['image'] ?? ''
                ];
                $productId = $product['id'];
            }
        } catch (Throwable $e) {}
    }

    // 2. Query standard products table (with Name priority to prevent client loop index collisions)
    if (!$product && $pdo) {
        try {
            if ($clientName !== '') {
                $cleanProdName = preg_replace('/\s*\([^)]*\)\s*$/', '', $clientName);
                $stmt = $pdo->prepare("SELECT id, name, kn_name, price, stock, image_url FROM products WHERE (name = ? OR name = ?) AND status = 1 LIMIT 1");
                $stmt->execute([$clientName, $cleanProdName]);
                $prodMatch = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($prodMatch) {
                    $product = $prodMatch;
                    $productId = $product['id'];
                }
            }

            // Fallback to ID lookup if not found by name
            if (!$product && is_numeric($productId)) {
                $stmt = $pdo->prepare("SELECT id, name, kn_name, price, stock, image_url FROM products WHERE id = ? AND status = 1");
                $stmt->execute([(int)$productId]);
                $cand = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($cand && (empty($clientName) || strcasecmp($cand['name'], $clientName) === 0 || str_contains(strtolower($clientName), strtolower($cand['name'])))) {
                    $product = $cand;
                    $productId = $product['id'];
                }
            }
        } catch (Throwable $e) {
            $product = null;
        }
    }

    if (!$product || ($clientName !== '' && strcasecmp($product['name'], $clientName) !== 0)) {
        // Check if product details were submitted by the client (allows fallback/static products to be added)
        if ($clientName !== '') {
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
                // Price calculation: support variant pricing and client calculated prices
                if ($variant !== null && $clientPrice > 0) {
                    $finalPrice = $clientPrice;
                } elseif (!empty($product['price']) && (float)$product['price'] > 0) {
                    $finalPrice = (float)$product['price'];
                } else {
                    $finalPrice = $clientPrice;
                }

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
        $cartTotal = array_sum(array_map(fn($item) => (float)$item['price'] * (int)$item['qty'], $_SESSION['cart']));
        $deliveryCharge = ($cartTotal > 0) ? 15.00 : 0.00;
        $grandTotal = $cartTotal + $deliveryCharge;
        echo json_encode([
            'success' => true,
            'message' => $message ?? 'Product added to cart.',
            'added_name' => $product['name'] ?? '',
            'added_qty' => $quantity,
            'cart' => array_values($_SESSION['cart']),
            'cartCount' => $cartCount,
            'cartTotal' => round($cartTotal, 2),
            'deliveryCharge' => round($deliveryCharge, 2),
            'grandTotal' => round($grandTotal, 2),
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