<?php
// ============================================================
// api/payment.php - Payment Processing API
// ============================================================
require_once '../config.php';
require_once '../functions.php';

// Set JSON response header
header('Content-Type: application/json');

// --- Enable CORS (adjust for production) ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

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

    require_once 'razorpay_helper.php';

    if ($paymentMethod === 'cod') {
        // Direct COD handling
        $transactionId = 'COD-' . time();
        $stmt = $pdo->prepare("UPDATE orders SET status = 'confirmed', payment_status = 'pending', transaction_id = ?, updated_at = NOW() WHERE id = ?");
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
            $stmt = $pdo->prepare("UPDATE orders SET status = 'confirmed', payment_status = 'paid', transaction_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$rzPaymentId, $orderId]);

            // Update payments table record
            try {
                $stmtPayment = $pdo->prepare("UPDATE payments SET status = 'success', transaction_id = ?, paid_at = NOW() WHERE order_id = ?");
                $stmtPayment->execute([$rzPaymentId, $orderId]);
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
                $stmtPayment = $pdo->prepare("UPDATE payments SET transaction_id = ? WHERE order_id = ?");
                $stmtPayment->execute([$rzpOrder['id'], $orderId]);
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