<?php
// ============================================================
// update_cart.php - Update or remove cart items (AJAX API)
// ============================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = strtolower(trim($_POST['action'] ?? ($_GET['action'] ?? 'update')));
$id     = trim($_POST['id'] ?? ($_GET['id'] ?? ''));
$rawQty = isset($_POST['qty']) ? (int)$_POST['qty'] : (isset($_GET['qty']) ? (int)$_GET['qty'] : 1);

// Helper function to return unified cart response
function ksrSendCartResponse(bool $success = true, string $message = '', string $error = ''): void {
    $cart = array_values($_SESSION['cart'] ?? []);
    $cartCount = array_sum(array_column($cart, 'qty'));
    $cartTotal = array_sum(array_map(function($i) {
        return (float)($i['price'] ?? 0) * (int)($i['qty'] ?? 1);
    }, $cart));
    $deliveryCharge = ($cartTotal > 0) ? 15.00 : 0.00;
    $grandTotal = $cartTotal + $deliveryCharge;

    $payload = [
        'success'        => $success,
        'cart'           => $cart,
        'cartCount'      => $cartCount,
        'cartTotal'      => round($cartTotal, 2),
        'deliveryCharge' => round($deliveryCharge, 2),
        'grandTotal'     => round($grandTotal, 2)
    ];
    if ($message !== '') $payload['message'] = $message;
    if ($error !== '')   $payload['error']   = $error;

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Clear cart
if ($action === 'clear' || $action === 'empty' || $action === 'clear_cart') {
    $_SESSION['cart'] = [];
    ksrSendCartResponse(true, 'Cart cleared successfully');
}

// 2. Get cart data
if ($action === 'get' || $action === 'load' || $action === 'view' || ($id === '' && $action === 'update')) {
    ksrSendCartResponse(true, 'Cart retrieved');
}

// 3. Mutate specific item
if ($id !== '') {
    if ($action === 'remove' || $action === 'delete' || $rawQty <= 0) {
        $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function ($i) use ($id) {
            return (string)($i['id'] ?? '') !== (string)$id;
        }));
        ksrSendCartResponse(true, 'Item removed from cart');
    }

    if ($action === 'incr' || $action === 'increment') {
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ((string)($item['id'] ?? '') === (string)$id) {
                $item['qty'] = min(999, (int)($item['qty'] ?? 1) + 1);
                $found = true;
                break;
            }
        }
        unset($item);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        ksrSendCartResponse($found, $found ? 'Item quantity incremented' : 'Item not found in cart');
    }

    if ($action === 'decr' || $action === 'decrement') {
        $found = false;
        foreach ($_SESSION['cart'] as $k => $item) {
            if ((string)($item['id'] ?? '') === (string)$id) {
                $found = true;
                $newQty = (int)($item['qty'] ?? 1) - 1;
                if ($newQty <= 0) {
                    unset($_SESSION['cart'][$k]);
                } else {
                    $_SESSION['cart'][$k]['qty'] = $newQty;
                }
                break;
            }
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        ksrSendCartResponse($found, $found ? 'Item quantity updated' : 'Item not found in cart');
    }

    // Default: update / set quantity
    $qty = max(1, min(999, $rawQty));
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ((string)($item['id'] ?? '') === (string)$id) {
            $item['qty'] = $qty;
            $found = true;
            break;
        }
    }
    unset($item);
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    ksrSendCartResponse($found, $found ? 'Cart updated' : 'Item not found in cart');
}

echo json_encode(['success' => false, 'error' => 'Missing item id'], JSON_UNESCAPED_UNICODE);
exit;
