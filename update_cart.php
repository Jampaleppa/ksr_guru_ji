<?php
// ============================================================
// update_cart.php - Update or remove cart items (AJAX API)
// ============================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? ($_GET['action'] ?? 'update');
$id = $_POST['id'] ?? ($_GET['id'] ?? '');
$qty = isset($_POST['qty']) ? (int)$_POST['qty'] : (isset($_GET['qty']) ? (int)$_GET['qty'] : 1);

if ($id !== '') {
    if ($action === 'remove' || $qty <= 0) {
        $_SESSION['cart'] = array_filter($_SESSION['cart'], function ($i) use ($id) {
            return (string)$i['id'] !== (string)$id;
        });
    } else {
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ((string)$item['id'] === (string)$id) {
                $item['qty'] = $qty;
                $found = true;
                break;
            }
        }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    $cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
    $cartTotal = array_sum(array_map(function($i) {
        return (float)($i['price'] ?? 0) * (int)($i['qty'] ?? 1);
    }, $_SESSION['cart']));

    echo json_encode([
        'success' => true,
        'cart' => $_SESSION['cart'],
        'cartCount' => $cartCount,
        'cartTotal' => $cartTotal
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Missing item id'], JSON_UNESCAPED_UNICODE);
exit;
