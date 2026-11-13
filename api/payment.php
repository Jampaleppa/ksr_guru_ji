<?php
// ============================================================
// api/payment.php - Payment Processing API
// ============================================================
if (file_exists(__DIR__ . '/config.php')) { require_once __DIR__ . '/config.php'; } elseif (file_exists(__DIR__ . '/../config.php')) { require_once __DIR__ . '/../config.php'; }
if (file_exists(__DIR__ . '/functions.php')) { require_once __DIR__ . '/functions.php'; } elseif (file_exists(__DIR__ . '/../functions.php')) { require_once __DIR__ . '/../functions.php'; }

// Set JSON response header
header('Content-Type: application/json');

// --- Safe CORS policy (no wildcard *) ---
$allowedOrigins = [
    'https://ksrguruji.vercel.app',
    'https://ksrguruji-3rpi.vercel.app',
    'http://localhost',
    'http://localhost:80',
    'http://localhost:8090',
    'http://127.0.0.1',
    'http://127.0.0.1:8090',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$corsOrigin = in_array($origin, $allowedOrigins, true) ? $origin : $allowedOrigins[0];
header("Access-Control-Allow-Origin: {$corsOrigin}");
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Vary: Origin');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Only allow POST requests ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// --- Parse incoming JSON ---
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
    exit;
}

// --- Validate required fields ---
$required = ['order_id', 'payment_method', 'amount'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

$orderId = (int) $input['order_id'];
$paymentMethod = trim($input['payment_method']);
$amount = (float) $input['amount'];
$cardDetails = $input['card'] ?? null;      // For card payments
$upiId = $input['upi_id'] ?? null;          // For UPI

// --- Connect to Database & Validate order ---
try {
    $pdo = getDBConnection();
    $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
    $nowExpr = $isSqlite ? "datetime('now')" : "NOW()";
    $stmt = $pdo->prepare("SELECT id, order_number, total, status, payment_method FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found.']);
        exit;
    }

    if ($order['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Order is not pending. Already processed.']);
        exit;
    }

    // Verify amount matches order total
    if (abs($order['total'] - $amount) > 0.01) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Amount does not match order total.']);
        exit;
    }

    if (file_exists(__DIR__ . '/razorpay_helper.php')) { require_once __DIR__ . '/razorpay_helper.php'; } elseif (file_exists(__DIR__ . '/../razorpay_helper.php')) { require_once __DIR__ . '/../razorpay_helper.php'; }

        $utr = trim((string)($input['utr'] ?? ($input['upi_utr'] ?? '')));
    if (!empty($utr)) {
        $utrClean = preg_replace('/[^A-Za-z0-9]/', '', $utr);
        $stmt = $pdo->prepare("UPDATE orders SET status = 'pending', payment_status = 'upi_awaiting', transaction_id = ?, updated_at = {$nowExpr} WHERE id = ?");
        $stmt->execute([$utrClean, $orderId]);

        try {
            $stmtPayment = $pdo->prepare("UPDATE payments SET status = 'upi_awaiting', gateway = 'upi_direct', gateway_payment_id = ?, transaction_id = ? WHERE order_id = ?");
            $stmtPayment->execute([$utrClean, $utrClean, $orderId]);
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'message' => 'Direct UPI UTR submitted successfully.',
            'order_id' => $orderId,
            'order_number' => $order['order_number'],
            'transaction_id' => $utrClean,
            'status' => 'upi_awaiting'
        ]);
        exit;
    }

    if ($paymentMethod === 'cod') {
        // Direct COD handling
        $transactionId = 'COD-' . time();
        $stmt = $pdo->prepare("UPDATE orders SET status = 'confirmed', payment_status = 'pending', transaction_id = ?, updated_at = {$nowExpr} WHERE id = ?");
        $stmt->execute([$transactionId, $orderId]);

        // Update payment status
        try {
            $stmtPayment = $pdo->prepare("UPDATE payments SET status = 'pending', transaction_id = ? WHERE order_id = ?");
            $stmtPayment->execute([$transactionId, $orderId]);
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'message' => 'COD Order confirmed.',
            'order_id' => $orderId,
            'order_number' => $order['order_number'],
            'transaction_id' => $transactionId,
            'status' => 'confirmed'
        ]);
        exit;
    }

    // If online method: Check if we have signature verification details
    $rzPaymentId = $input['razorpay_payment_id'] ?? '';
    $rzOrderId = $input['razorpay_order_id'] ?? '';
    $rzSignature = $input['razorpay_signature'] ?? '';

    if (!empty($rzPaymentId) && !empty($rzOrderId) && !empty($rzSignature)) {
        // Verification phase
        $verified = RazorpayHelper::verifySignature($rzOrderId, $rzPaymentId, $rzSignature);
        if ($verified) {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'confirmed', payment_status = 'paid', transaction_id = ?, updated_at = {$nowExpr} WHERE id = ?");
            $stmt->execute([$rzPaymentId, $orderId]);

            // Update payments table record
            try {
                $stmtPayment = $pdo->prepare("UPDATE payments SET status = 'success', gateway_payment_id = ?, transaction_id = ?, gateway_signature = ?, paid_at = {$nowExpr} WHERE order_id = ?");
                $stmtPayment->execute([$rzPaymentId, $rzPaymentId, $rzSignature, $orderId]);
            } catch (Exception $e) {}

            echo json_encode([
                'success' => true,
                'message' => 'Payment verified and order confirmed successfully.',
                'order_id' => $orderId,
                'order_number' => $order['order_number'],
                'transaction_id' => $rzPaymentId,
                'status' => 'confirmed'
            ]);
            exit;
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Payment signature verification failed. Fraud suspected.',
                'order_id' => $orderId
            ]);
            exit;
        }
    } else {
        // Initiation phase - Create Razorpay Order
        $rzpOrder = RazorpayHelper::createOrder($amount, $order['order_number'], []);
        if (isset($rzpOrder['id'])) {
            // Save Razorpay Order ID as the order transaction ID
            $stmt = $pdo->prepare("UPDATE orders SET transaction_id = ? WHERE id = ?");
            $stmt->execute([$rzpOrder['id'], $orderId]);

            // Update payments table record
            try {
                $stmtPayment = $pdo->prepare("UPDATE payments SET gateway_order_id = ?, transaction_id = ? WHERE order_id = ?");
                $stmtPayment->execute([$rzpOrder['id'], $rzpOrder['id'], $orderId]);
            } catch (Exception $e) {}

            echo json_encode([
                'success' => true,
                'require_checkout' => true,
                'razorpay_order_id' => $rzpOrder['id'],
                'razorpay_key_id' => RazorpayHelper::getKeyId(),
                'amount' => round($amount * 100),
                'order_id' => $orderId,
                'order_number' => $order['order_number']
            ]);
            exit;
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Unable to create order in Razorpay gateway.',
                'order_id' => $orderId
            ]);
            exit;
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}