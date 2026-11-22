<?php
// Return complete cart item list and summary as JSON
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart = array_values($_SESSION['cart']);
$cartCount = array_sum(array_column($cart, 'qty'));
$cartTotal = array_sum(array_map(function($i) {
    return (float)($i['price'] ?? 0) * (int)($i['qty'] ?? 1);
}, $cart));
$deliveryCharge = ($cartTotal > 0) ? 15.00 : 0.00;
$grandTotal = $cartTotal + $deliveryCharge;

echo json_encode([
    'success'        => true,
    'cart'           => $cart,
    'cartCount'      => $cartCount,
    'cartTotal'      => round($cartTotal, 2),
    'deliveryCharge' => round($deliveryCharge, 2),
    'grandTotal'     => round($grandTotal, 2)
], JSON_UNESCAPED_UNICODE);
exit;
