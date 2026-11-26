<?php
// ============================================================
// api/coupons/validate.php – Coupon validation endpoint
// POST body: { "code": "SAVE10", "subtotal": 450.00 }
// Returns: { "success": true, "code": "SAVE10", "discount": 45.00, "message": "10% off applied" }
// ============================================================
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/coupons.php';
header('Content-Type: application/json');

// Accept JSON body or form-post
$rawBody  = file_get_contents('php://input');
$body     = is_string($rawBody) && $rawBody !== '' ? (json_decode($rawBody, true) ?? []) : [];
$code     = strtoupper(trim((string)($body['code'] ?? $_POST['code'] ?? '')));
$subtotal = (float)($body['subtotal'] ?? $_POST['subtotal'] ?? 0);

// Fallback to session cart subtotal if caller passed subtotal=0 while session cart has items
if ($subtotal <= 0 && !empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $subtotal = (float)array_sum(array_map(
        fn($item) => (float)($item['price'] ?? 0) * (int)($item['qty'] ?? $item['quantity'] ?? 1),
        $_SESSION['cart']
    ));
}

if ($code === '') {
    echo json_encode(['success' => false, 'error' => 'Please enter a coupon code.']);
    exit;
}

try {
    $pdo    = getDBConnection();
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $res    = ksrValidateCoupon($pdo, $code, $subtotal, $userId);

    if (!$res['valid'] || !$res['coupon']) {
        echo json_encode(['success' => false, 'error' => $res['error'] ?: 'Invalid coupon code.']);
        exit;
    }

    $_SESSION['applied_coupon'] = [
        'id'       => $res['coupon']['id'],
        'code'     => $res['coupon']['code'],
        'discount' => $res['discount'],
        'type'     => $res['coupon']['discount_type'],
        'value'    => $res['coupon']['discount_value'],
    ];

    echo json_encode([
        'success'  => true,
        'code'     => $res['coupon']['code'],
        'discount' => $res['discount'],
        'message'  => $res['message'],
    ]);
} catch (Throwable $e) {
    error_log('coupon validate error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
