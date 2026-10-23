<?php
// ============================================================
// checkout.php - Unified Checkout (Backend + Frontend)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Handle PDF Upload ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_invoice') {
    header('Content-Type: application/json');
    $orderNumber = trim($_POST['order_number'] ?? '');

    if (empty($orderNumber) || !isset($_FILES['pdf_file'])) {
        echo json_encode(['success' => false, 'error' => 'Missing data.']);
        exit;
    }

    $dir = __DIR__ . '/invoices';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $orderNumberClean = preg_replace('/[^A-Za-z0-9\-]/', '', $orderNumber);
    $filename = 'Invoice_' . $orderNumberClean . '.pdf';
    $filepath = $dir . '/' . $filename;

    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $filepath)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $baseDir = str_replace('/checkout/checkout.php', '', $scriptName);
        $pdfUrl = $protocol . "://" . $host . $baseDir . "/invoices/" . $filename;

        echo json_encode(['success' => true, 'pdf_url' => $pdfUrl]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save PDF invoice.']);
    }
    exit;
}

// --- Handle AJAX requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'verify_razorpay_payment') {
        header('Content-Type: application/json');
        $orderId = (int)($_POST['order_id'] ?? 0);
        $razorpay_payment_id = trim($_POST['razorpay_payment_id'] ?? '');
        $razorpay_order_id = trim($_POST['razorpay_order_id'] ?? '');
        $razorpay_signature = trim($_POST['razorpay_signature'] ?? '');

        if ($orderId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid order reference.']);
            exit;
        }

        // Only the owner of the order (or a guest that placed it in this
        // session) may trigger verification.
        $verifyUserId = $_SESSION['user_id'] ?? null;
        $verifyRole   = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? 'user');
        $verifyIsAdmin = in_array($verifyRole, ['admin', 'super_admin'], true);

        try {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare("SELECT id, user_id FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $gateOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$gateOrder) {
                echo json_encode(['success' => false, 'error' => 'Order not found.']);
                exit;
            }

            $guestOrders = $_SESSION['ksr_guest_orders'] ?? [];
            $isGuestOwner = is_array($guestOrders)
                && in_array($orderId, array_map('intval', $guestOrders), true);
            $isOwner = ($verifyUserId !== null && (int)$gateOrder['user_id'] === (int)$verifyUserId);

            if (!$verifyIsAdmin && !$isOwner && !$isGuestOwner) {
                echo json_encode(['success' => false, 'error' => 'Access denied.']);
                exit;
            }
        } catch (Throwable $e) {
            error_log('verify guard error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Unable to process verification.']);
            exit;
        }

        if (file_exists(__DIR__ . '/razorpay_helper.php')) {
            require_once __DIR__ . '/razorpay_helper.php';
        } else {
            require_once __DIR__ . '/../api/razorpay_helper.php';
        }
        if (file_exists(__DIR__ . '/payment_state.php')) {
            require_once __DIR__ . '/payment_state.php';
        } else {
            require_once __DIR__ . '/../api/payment_state.php';
        }

        if (empty($razorpay_payment_id) || empty($razorpay_order_id) || empty($razorpay_signature)) {
            echo json_encode(['success' => false, 'error' => 'Missing verification signatures.']);
            exit;
        }

        try {
            $pdo = getDBConnection();

            /* ----------------------------------------------------------
             * IDEMPOTENCY #1 — same Razorpay payment already recorded.
             *
             * This covers: browser retry, double-click, webhook arriving
             * first, network retry, page refresh, duplicate gateway event.
             * Never create a second payment; return SUCCESS.
             * ---------------------------------------------------------- */
            $existing = paymentFindByGatewayPaymentId($pdo, $razorpay_payment_id);
            if ($existing && (int)$existing['order_id'] === $orderId) {
                $existingStatus = ksrNormalizePaymentStatus($existing['status']);

                if (paymentStatusIsSettled($existingStatus)) {
                    echo json_encode([
                        'success' => true,
                        'idempotent' => true,
                        'message' => 'Payment already confirmed for this order.',
                        'status' => $existingStatus
                    ]);
                    exit;
                }
            }

            /* ----------------------------------------------------------
             * IDEMPOTENCY #2 — a DIFFERENT gateway payment id claiming the
             * same order. Refuse: one order can only be settled once.
             * ---------------------------------------------------------- */
            $existingForOrder = paymentRowForOrder($pdo, $orderId);

            if ($existingForOrder) {
                $orderCurrent = ksrNormalizePaymentStatus($existingForOrder['status']);
                $recordedGatewayPayId = (string)($existingForOrder['gateway_payment_id'] ?? '');

                if (paymentStatusIsSettled($orderCurrent)) {
                    echo json_encode([
                        'success' => true,
                        'idempotent' => true,
                        'message' => 'Payment already confirmed for this order.',
                        'status' => $orderCurrent
                    ]);
                    exit;
                }

                if ($recordedGatewayPayId !== '' && $recordedGatewayPayId !== $razorpay_payment_id) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'A different payment is already attached to this order.'
                    ]);
                    exit;
                }
            }

            /* ----------------------------------------------------------
             * OWNERSHIP — the Razorpay order must belong to this KSR order.
             * ---------------------------------------------------------- */
            $expectedGatewayOrderId = (string)($existingForOrder['gateway_order_id'] ?? '');

            if ($expectedGatewayOrderId === '') {
                // Legacy fallback: orders.transaction_id stored the razorpay order id.
                $txStmt = $pdo->prepare("SELECT transaction_id FROM orders WHERE id = ?");
                $txStmt->execute([$orderId]);
                $expectedGatewayOrderId = (string)$txStmt->fetchColumn();
            }

            $isSimulated = (strpos($razorpay_order_id, 'rzp_order_mock_') === 0)
                || (strpos($razorpay_payment_id, 'pay_mock_') === 0);

            if (
                !$isSimulated
                && $expectedGatewayOrderId !== ''
                && !hash_equals($expectedGatewayOrderId, $razorpay_order_id)
            ) {
                error_log("verify: gateway order mismatch order #{$orderId} expected {$expectedGatewayOrderId} got {$razorpay_order_id}");
                echo json_encode([
                    'success' => false,
                    'error' => 'Payment does not belong to this order.'
                ]);
                exit;
            }

            /* ----------------------------------------------------------
             * AMOUNT — re-check against the gateway / local record.
             * ---------------------------------------------------------- */
            if (!$isSimulated) {
                $remoteOrder = RazorpayHelper::fetchOrder($razorpay_order_id);
                if (is_array($remoteOrder) && isset($remoteOrder['amount'])) {
                    $gatewayAmount  = ((int)$remoteOrder['amount']) / 100;
                    $expectedAmount = (float)($existingForOrder['amount'] ?? 0);

                    if (abs($gatewayAmount - $expectedAmount) > 1.00) {
                        error_log("verify: amount mismatch order #{$orderId} gateway {$gatewayAmount} expected {$expectedAmount}");
                        echo json_encode([
                            'success' => false,
                            'error' => 'Payment amount does not match the order amount.'
                        ]);
                        exit;
                    }
                }
            }

            /* ----------------------------------------------------------
             * SIGNATURE verification.
             * ---------------------------------------------------------- */
            $isVerified = RazorpayHelper::verifySignature($razorpay_order_id, $razorpay_payment_id, $razorpay_signature);

            if (!$isVerified) {
                // Mark the payment as failed through the state machine,
                // but never overwrite an already-settled payment.
                try {
                    $pdo->beginTransaction();
                    if (
                        $existingForOrder
                        && !paymentStatusIsTerminal(ksrNormalizePaymentStatus($existingForOrder['status']))
                    ) {
                        applyPaymentStatus($pdo, (int)$existingForOrder['id'], 'failed', [
                            'event_type' => 'SIGNATURE_VERIFICATION_FAILED',
                            'failure_code' => 'SIGNATURE_MISMATCH',
                            'failure_reason' => 'Razorpay signature verification failed.',
                            'source' => 'checkout',
                        ]);
                    }
                    $pdo->commit();
                } catch (Throwable $inner) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('verify fail-mark error: ' . $inner->getMessage());
                }

                echo json_encode(['success' => false, 'error' => 'Payment signature verification failed. Possible fraud.']);
                exit;
            }

            /* ----------------------------------------------------------
             * APPLY the confirmed payment through the state machine.
             * This is immediate UX only — the webhook remains the
             * authoritative source and is idempotent with this path.
             * ---------------------------------------------------------- */
            $pdo->beginTransaction();

            $paymentRow = $existingForOrder ?: paymentRowForOrder($pdo, $orderId);

            if (!$paymentRow) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'No payment record for this order.']);
                exit;
            }

            $paymentId = (int)$paymentRow['id'];

            // If we validated on the gateway and it was captured, go straight
            // to success; otherwise move through processing.
            $intermediate = applyPaymentStatus($pdo, $paymentId, 'processing', [
                'event_type' => 'PAYMENT_VERIFYING',
                'gateway_order_id' => $razorpay_order_id,
                'gateway_payment_id' => $razorpay_payment_id,
                'gateway_signature' => $razorpay_signature,
                'gateway' => 'razorpay',
                'source' => 'checkout',
            ]);

            if (!$intermediate) {
                // Most likely already processing/success.
                $fresh = paymentRowForOrder($pdo, $orderId);
                if ($fresh && paymentStatusIsSettled(ksrNormalizePaymentStatus($fresh['status']))) {
                    $pdo->commit();
                    echo json_encode([
                        'success' => true,
                        'idempotent' => true,
                        'message' => 'Payment already confirmed for this order.',
                        'status' => ksrNormalizePaymentStatus($fresh['status'])
                    ]);
                    exit;
                }
            }

            $settled = applyPaymentStatus($pdo, $paymentId, 'success', [
                'event_type'          => 'PAYMENT_CONFIRMED',
                'gateway_order_id'    => $razorpay_order_id,
                'gateway_payment_id'  => $razorpay_payment_id,
                'gateway_signature'   => $razorpay_signature,
                'gateway'             => 'razorpay',
                'transaction_id'      => $razorpay_payment_id,
                'gateway_response'    => [
                    'razorpay_payment_id' => $razorpay_payment_id,
                    'razorpay_order_id'   => $razorpay_order_id,
                    'verified_at'         => date('Y-m-d H:i:s'),
                    'verified_via'        => 'browser_callback',
                ],
                'source'              => 'checkout',
            ]);

            if (!$settled) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'Unable to confirm payment state.']);
                exit;
            }

            // Mark the order as confirmed explicitly (state machine sets
            // processing; CONFIRMED is the business-level next step).
            $ordStmt = $pdo->prepare(
                "UPDATE orders SET status = 'processing', payment_status = 'paid', transaction_id = ? WHERE id = ?"
            );
            $ordStmt->execute([$razorpay_payment_id, $orderId]);

            $userId = $_SESSION['user_id'] ?? null;
            $tl = $pdo->prepare(
                "INSERT INTO order_timeline (order_id, status, notes, created_by) VALUES (?, 'processing', 'Payment verified and order confirmed via Razorpay Checkout.', ?)"
            );
            $tl->execute([$orderId, $userId]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Online payment verified successfully!',
                'status'  => 'success'
            ]);
            exit;
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Razorpay verify exception: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Database error during payment completion.']);
            exit;
        }
    }

    if ($_POST['action'] === 'create_order') {
        header('Content-Type: application/json');

        // Get and sanitize input
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $payment_method = $_POST['payment_method'] ?? 'cod';
        $notes = trim($_POST['notes'] ?? '');

        // Cart items from client (JSON)
        $cartItems = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

        // Client-supplied figures are treated as UNTRUSTED display hints only.
        $clientTotal     = (float)($_POST['total'] ?? 0);
        $deliveryCharge  = (float)($_POST['delivery_charge'] ?? 0);

        if (file_exists(__DIR__ . '/pricing.php')) {
            require_once __DIR__ . '/pricing.php';
        } else {
            require_once __DIR__ . '/../api/pricing.php';
        }
        if (file_exists(__DIR__ . '/payment_state.php')) {
            require_once __DIR__ . '/payment_state.php';
        } else {
            require_once __DIR__ . '/../api/payment_state.php';
        }

        // Validation
        $errors = [];
        if (empty($name)) $errors[] = 'Full name is required.';
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required if provided.';
        if (empty($phone) || !preg_match('/^[0-9]{10,15}$/', $phone)) $errors[] = 'Valid phone number is required.';
        if (empty($address)) $errors[] = 'Address is required.';
        if (empty($city)) $errors[] = 'City is required.';
        if (empty($state)) $errors[] = 'State is required.';
        if (empty($pincode) || !preg_match('/^[0-9]{5,6}$/', $pincode)) $errors[] = 'Valid PIN code is required.';
        if (empty($cartItems) || !is_array($cartItems)) $errors[] = 'Cart is empty.';

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        // ---- Authoritative server-side price recalculation ---------------
        // The final payable amount is NEVER taken from the browser.
        $priced = ksrCalculateCartTotal($cartItems);
        $subtotal = $priced['subtotal'];
        $total    = $priced['total'];
        $deliveryCharge = $priced['delivery_charge'];

        if ($total <= 0) {
            echo json_encode(['success' => false, 'errors' => ['Cart total could not be calculated.']]);
            exit;
        }

        // If the browser's number disagrees materially with the server, warn
        // in the log but proceed with the server figure.
        if ($clientTotal > 0 && abs($clientTotal - $total) > 1.00) {
            error_log(
                "create_order: client total {$clientTotal} != server total {$total}. Using server value."
            );
        }

        $payment_method = strtolower(trim((string)$payment_method));
        $allowedMethods = ['cod', 'upi', 'qr', 'gpay', 'phonepe', 'paytm', 'bhim', 'amazonpay', 'card'];
        if (!in_array($payment_method, $allowedMethods, true)) {
            $payment_method = 'upi';
        }

        // Begin transaction
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();

            // Get user ID if logged in
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

            // Generate order number
            $year = date('Y');
            $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
            $countSql = $isSqlite
                ? "SELECT COUNT(*) FROM orders WHERE strftime('%Y', created_at) = '$year'"
                : "SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = $year";
            $stmt = $pdo->query($countSql);
            $count = $stmt ? (int)$stmt->fetchColumn() : 0;
            $orderNumber = 'KSR-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

            // Determine payment status
            $payment_status = 'pending';

            // Insert order (transaction_id will be populated with Razorpay Order ID for online payments)
            $nowExpr = $isSqlite ? "datetime('now')" : "NOW()";
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    order_number, user_id, name, email, phone, address, city, state, pincode,
                    total, payment_method, transaction_id, payment_status, notes, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', $nowExpr)
            ");

            $stmt->execute([
                $orderNumber,
                $userId,
                $name,
                $email,
                $phone,
                $address,
                $city,
                $state,
                $pincode,
                $total,
                $payment_method,
                null, // will update below
                $payment_status,
                $notes
            ]);
            $orderId = $pdo->lastInsertId();

            // Insert order items (server-resolved unit prices)
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($priced['line_items'] as $line) {
                $stmt->execute([
                    $orderId,
                    $line['id'],
                    $line['name'],
                    $line['qty'],
                    $line['unit_price'],
                    $line['total'],
                ]);
            }

            // Insert payment record with explicit lifecycle state.
            $db_payment_method = ($payment_method === 'cod') ? 'cod' : 'upi';
            $initialPaymentStatus = ($payment_method === 'cod') ? 'cod_pending' : 'initiated';

            $stmt = $pdo->prepare("
                INSERT INTO payments
                    (order_id, user_id, amount, method, gateway, status, transaction_id, paid_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
            ");
            $stmt->execute([
                $orderId,
                $userId,
                $total,
                $db_payment_method,
                ($payment_method === 'cod') ? 'cod' : 'razorpay',
                $initialPaymentStatus,
                null,
            ]);
            $paymentRecordId = (int)$pdo->lastInsertId();

            // Record the opening events for the audit trail.
            recordPaymentEvent($pdo, $paymentRecordId, $orderId, 'ORDER_CREATED', null, null, null, null, [
                'order_number' => $orderNumber,
                'amount'       => $total,
                'method'       => $payment_method,
            ], 'checkout');

            recordPaymentEvent($pdo, $paymentRecordId, $orderId, 'PAYMENT_INITIATED', null, $initialPaymentStatus, null, null, [
                'method' => $payment_method,
            ], 'checkout');

            // Log initial status to timeline
            $initialOrderStatus = ($payment_method === 'cod') ? 'confirmed' : 'pending';
            $stmt = $pdo->prepare("
                INSERT INTO order_timeline (order_id, status, notes, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                $initialOrderStatus,
                ($payment_method === 'cod')
                    ? 'Order placed (Cash on Delivery).'
                    : 'Order placed; awaiting online payment.',
                $userId,
            ]);

            if ($payment_method === 'cod') {
                $ordUpdate = $pdo->prepare(
                    "UPDATE orders SET payment_status = 'cod_pending', status = 'confirmed' WHERE id = ?"
                );
                $ordUpdate->execute([$orderId]);
            }

            $pdo->commit();

            // Clear server-side session cart
            if (isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            // Generate Razorpay Order if not COD
            if ($payment_method !== 'cod') {
                if (file_exists(__DIR__ . '/razorpay_helper.php')) {
                    require_once __DIR__ . '/razorpay_helper.php';
                } else {
                    require_once __DIR__ . '/../api/razorpay_helper.php';
                }

                $rzpOrder = RazorpayHelper::createOrder($total, $orderNumber);

                if (isset($rzpOrder['id'])) {
                    $isMockOrder = (strpos($rzpOrder['id'], 'rzp_order_mock_') === 0);

                    // Persist the gateway order id on both records so the
                    // browser callback + webhook can prove ownership.
                    $stmt = $pdo->prepare(
                        "UPDATE orders SET transaction_id = ?, payment_status = 'initiated' WHERE id = ?"
                    );
                    $stmt->execute([$rzpOrder['id'], $orderId]);

                    $stmt = $pdo->prepare(
                        "UPDATE payments
                         SET transaction_id = ?,
                             gateway = 'razorpay',
                             gateway_order_id = ?,
                             status = 'pending'
                         WHERE order_id = ?"
                    );
                    $stmt->execute([$rzpOrder['id'], $rzpOrder['id'], $orderId]);

                    recordPaymentEvent(
                        $pdo,
                        $paymentRecordId,
                        $orderId,
                        'RAZORPAY_ORDER_CREATED',
                        'initiated',
                        'pending',
                        null,
                        null,
                        ['razorpay_order_id' => $rzpOrder['id'], 'mock' => $isMockOrder],
                        'checkout'
                    );

                    // Track this order in the session so a guest can poll
                    // its payment status.
                    if (!isset($_SESSION['ksr_guest_orders']) || !is_array($_SESSION['ksr_guest_orders'])) {
                        $_SESSION['ksr_guest_orders'] = [];
                    }
                    $_SESSION['ksr_guest_orders'][] = $orderId;

                    echo json_encode([
                        'success' => true,
                        'order_id' => $orderId,
                        'order_number' => $orderNumber,
                        'razorpay_order_id' => $rzpOrder['id'],
                        'razorpay_key_id' => RazorpayHelper::getKeyId(),
                        'amount' => round($total * 100),
                        'customer_name' => $name,
                        'customer_email' => $email,
                        'customer_phone' => $phone,
                        'simulated' => $isMockOrder,
                        'message' => 'Order initiated, open payment gateway.'
                    ]);
                    exit;
                } else {
                    // Online payment could not be initiated — mark the
                    // payment failed so it does not linger as initiated.
                    try {
                        applyPaymentStatus($pdo, $paymentRecordId, 'failed', [
                            'event_type' => 'GATEWAY_ORDER_CREATION_FAILED',
                            'failure_code' => 'GATEWAY_ORDER_FAILED',
                            'failure_reason' => (string)($rzpOrder['error'] ?? 'Razorpay order creation failed.'),
                            'source' => 'checkout',
                        ]);
                    } catch (Throwable $ignore) {
                    }

                    echo json_encode(['success' => false, 'errors' => ['Failed to initiate online payment via Razorpay. Please try again.']]);
                    exit;
                }
            }

            // If COD, complete immediately
            $codTxId = 'COD' . date('YmdHis') . rand(100, 999);
            $stmt = $pdo->prepare("UPDATE orders SET transaction_id = ? WHERE id = ?");
            $stmt->execute([$codTxId, $orderId]);

            $stmt = $pdo->prepare("UPDATE payments SET transaction_id = ? WHERE order_id = ?");
            $stmt->execute([$codTxId, $orderId]);

            // Guests need to be able to follow their COD order too.
            if (!isset($_SESSION['ksr_guest_orders']) || !is_array($_SESSION['ksr_guest_orders'])) {
                $_SESSION['ksr_guest_orders'] = [];
            }
            $_SESSION['ksr_guest_orders'][] = $orderId;

            echo json_encode([
                'success' => true,
                'cod' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'transaction_id' => $codTxId,
                'payment_status' => 'cod_pending',
                'message' => 'Order placed successfully (Cash on Delivery).'
            ]);
            exit;
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Order creation error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'errors' => ['Database error: ' . $e->getMessage()]]);
            exit;
        }
    }
}

// --- If not AJAX, render the full page ---

// Needed so the template can flag production mode to the client.
require_once __DIR__ . '/razorpay_helper.php';

// Pre‑fill user data if logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;
$userEmail = $isLoggedIn ? ($_SESSION['user_email'] ?? '') : '';
$userName = $isLoggedIn ? ($_SESSION['user_name'] ?? '') : '';
$userPhone = '';
$userRole = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? 'user');
$isAdmin = in_array($userRole, ['admin', 'super_admin']);

if ($isLoggedIn) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('SELECT phone FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $userPhone = $userData['phone'] ?? '';
    } catch (PDOException $e) {
        // ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes" />
    <title>KSR GURU JI - Secure Checkout</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <!-- jsPDF + autoTable for PDF invoice generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js">
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js">
    </script>
    <!-- Razorpay Checkout SDK -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <link rel="stylesheet" href="../index.css?v=1.4" />
    <link rel="stylesheet" href="../cart-checkout.css?v=1.0" />
</head>

<body>

    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    CHECKOUT WRAPPER
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <div class="checkout-wrapper">

        <!-- ─── LEFT COLUMN ─── -->
        <div>

            <div class="brand-header">
                <div class="brand" onclick="window.location.href='../index.php'" style="cursor: pointer; display: flex; align-items: center; gap: 14px;">
                    <div class="logo-image" style="width: 48px; height: 48px; background: linear-gradient(135deg, #0f7b3a, #1ea85a); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 16px rgba(15, 123, 58, 0.15); flex-shrink: 0; border: 2px solid rgba(255, 255, 255, 0.8);">
                        <img src="../company_logo.jpg" alt="KSR GURU JI Logo" style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;" onerror="this.style.display='none';this.parentElement.innerHTML='🛒';">
                    </div>
                    <div>
                        <h1 style="margin: 0; line-height: 1.1; font-size: 1.5rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px;">KSR <span style="background: var(--gradient-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">GURU JI</span></h1>
                        <div class="brand-sub" style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 4px; margin-top: 2px;"><i class="fas fa-shield-alt" style="color:var(--color-primary);"></i> Trusted Digital Payments</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div class="merchant-tag">
                        <i class="fas fa-check-circle"></i> Verified Merchant
                        <i class="fas fa-phone" style="margin-left:6px;"></i> 8088584421
                    </div>
                    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                        <span id="themeIcon">🌙</span>
                    </button>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="card">
                <div class="card-title"><i class="fas fa-user"></i> Customer Details</div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" id="fullName" placeholder="John Doe" value="<?php echo htmlspecialchars($userName); ?>" />
                        <div class="error-text" id="fullNameError">Please enter your full name</div>
                    </div>
                    <div class="form-group">
                        <label>Mobile Number <span class="required">*</span></label>
                        <input type="tel" id="mobile" placeholder="9876543210" maxlength="10" value="<?php echo htmlspecialchars($userPhone); ?>" />
                        <div class="error-text" id="mobileError">Enter a valid 10-digit number</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Delivery Address <span class="required">*</span></label>
                    <input type="text" id="address" placeholder="Street, building, landmark" />
                    <div class="error-text" id="addressError">Please enter your delivery address</div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>City <span class="required">*</span></label>
                        <input type="text" id="city" placeholder="Mumbai" />
                        <div class="error-text" id="cityError">Please enter your city</div>
                    </div>
                    <div class="form-group">
                        <label>Pincode <span class="required">*</span></label>
                        <input type="text" id="pincode" placeholder="400001" maxlength="6" />
                        <div class="error-text" id="pincodeError">Enter a 6-digit pincode</div>
                    </div>
                </div>

                <button id="locationBtn" class="location-btn"><i class="fas fa-location-dot"></i> Use my current location</button>
                <div class="location-status" id="locationStatus"></div>
            </div>

            <!-- Payment Methods -->
            <div class="card">
                <div class="card-title"><i class="fas fa-credit-card"></i> Payment Method</div>

                <div class="payment-grid" id="paymentGrid">
                    <!-- QR Scan & Pay -->
                    <div class="payment-option selected" data-method="qr">
                        <span class="check-mark"><i class="fas fa-check-circle"></i></span>
                        <div class="icon-wrap" style="color:#10b981;"><i class="fas fa-qrcode"></i></div>
                        <div class="payment-text">
                            <span class="label">UPI via Razorpay</span>
                            <span class="sub-label">UPI apps and QR when available</span>
                        </div>
                    </div>

                    <!-- UPI ID -->
                    <div class="payment-option" data-method="upi">
                        <span class="check-mark"><i class="fas fa-check-circle"></i></span>
                        <div class="icon-wrap" style="color: #097969;"><i class="fas fa-at"></i></div>
                        <div class="payment-text">
                            <span class="label">UPI ID</span>
                            <span class="sub-label">Pay to UPI</span>
                        </div>
                    </div>

                    <!-- Google Pay -->
                    <div class="payment-option" data-method="gpay">
                        <span class="check-mark"><i class="fas fa-check-circle"></i></span>
                        <div class="icon-wrap" style="color: #4285f4;"><i class="fab fa-google-pay"></i></div>
                        <div class="payment-text">
                            <span class="label">Google Pay</span>
                            <span class="sub-label">UPI</span>
                        </div>
                    </div>

                    <!-- PhonePe -->
                    <div class="payment-option" data-method="phonepe">
                        <span class="check-mark"><i class="fas fa-check-circle"></i></span>
                        <div class="icon-wrap" style="color: #5f259f;"><i class="fas fa-mobile-alt"></i></div>
                        <div class="payment-text">
                            <span class="label">PhonePe</span>
                            <span class="sub-label">UPI</span>
                        </div>
                    </div>

                    <!-- Paytm -->
                    <div class="payment-option" data-method="paytm">
                        <span class="check-mark"><i class="fas fa-check-circle"></i></span>
                        <div class="icon-wrap" style="color: #00baf2;"><i class="fas fa-wallet"></i></div>
                        <div class="payment-text">
                            <span class="label">Paytm</span>
                            <span class="sub-label">Wallet / UPI</span>
                        </div>
                    </div>

                    <!-- BHIM UPI -->
                    <div class="payment-option" data-method="bhim">
                        <span class="check-mark"><i class="fas fa-check-circle"></i></span>
                        <div class="icon-wrap" style="color: #1a73e8;"><i class="fas fa-hand-holding-heart"></i></div>
                        <div class="payment-text">
                            <span class="label">BHIM UPI</span>
                            <span class="sub-label">NPCI</span>
                        </div>
                    </div>

                    <!-- Amazon Pay -->
                    <div class="payment-option" data-method="amazonpay">
                        <span class="check-mark"><i class="fas fa-check-circle"></i></span>
                        <div class="icon-wrap" style="color: #ff9900;"><i class="fab fa-amazon"></i></div>
                        <div class="payment-text">
                            <span class="label">Amazon Pay</span>
                            <span class="sub-label">Wallet</span>
                        </div>
                    </div>

                    <!-- Cards / Net Banking (Razorpay) -->
                    <div class="payment-option" data-method="card">
                        <span class="check-mark"><i class="fas fa-check-circle"></i></span>
                        <div class="icon-wrap" style="color: #1a1b41;"><i class="fas fa-credit-card"></i></div>
                        <div class="payment-text">
                            <span class="label">Card / Netbanking</span>
                            <span class="sub-label">Visa · Mastercard · RuPay</span>
                        </div>
                    </div>

                    <!-- Cash on Delivery -->
                    <div class="payment-option" data-method="cod">
                        <span class="check-mark"><i class="fas fa-check-circle"></i></span>
                        <div class="icon-wrap" style="color: #f59e0b;"><i class="fas fa-hand-holding-usd"></i></div>
                        <div class="payment-text">
                            <span class="label">Cash on Delivery</span>
                            <span class="sub-label">Pay at doorstep</span>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="orderAmount" value="0.00">
                <input type="hidden" id="orderId" value="KSR Order">

                <!-- Payment Detail Panel -->
                <div class="payment-detail active" id="paymentDetail">
                    <div class="detail-title" id="detailTitle">
                        <i class="fas fa-qrcode"></i> Scan QR Code
                    </div>
                    <div id="detailContent">
                        <!-- Dynamic content rendered by JS -->
                    </div>

                </div>

                <!-- Real-time payment status (server-authoritative) -->
                <div id="paymentStatusBox"
                    style="
                    display:none;
                    margin-top:20px;
                    padding:20px;
                    border-radius:16px;
                    background:#f8fafc;
                    border:1px solid #e2e8f0;
                 ">

                    <div id="paymentStatusIcon"
                        style="font-size:42px;text-align:center;">
                        ⏳
                    </div>

                    <h3 id="paymentStatusTitle"
                        style="text-align:center;margin:10px 0;">
                        Payment Pending
                    </h3>

                    <p id="paymentStatusMessage"
                        style="
                      text-align:center;
                      color:#64748b;
                      margin:0;
                   ">
                        Waiting for payment confirmation...
                    </p>

                    <div style="
                    margin-top:20px;
                    height:8px;
                    background:#e2e8f0;
                    border-radius:20px;
                    overflow:hidden;
                ">
                        <div id="paymentProgress"
                            style="
                            width:40%;
                            height:100%;
                            border-radius:20px;
                            background:linear-gradient(90deg,#10b981,#1ea85a);
                            transition:width .5s ease;
                         ">
                        </div>
                    </div>

                    <div id="paymentTransaction"
                        style="
                        text-align:center;
                        margin-top:15px;
                        font-size:.8rem;
                        color:#64748b;
                     ">
                    </div>

                    <div id="paymentStatusActions" style="text-align:center;margin-top:14px;"></div>

                </div>
            </div>

            <!-- ─── DELIVERY & CONTACT ─── -->
            <div class="card">
                <div class="card-title"><i class="fas fa-truck"></i> Delivery &amp; Contact</div>

                <div class="delivery-info">
                    <div class="icon"><i class="fas fa-rocket"></i></div>
                    <div class="text">
                        <strong>🚀 Delivery within 45 minutes</strong>
                        <span>We'll deliver your order super fast! Track your delivery via WhatsApp.</span>
                    </div>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-weight:600;font-size:0.82rem;color:#334155;display:block;margin-bottom:4px;">
                        <i class="fab fa-whatsapp" style="color:#25d366;"></i> Contact us on WhatsApp
                    </label>
                    <div class="whatsapp-contact">
                        <a href="https://wa.me/918762474406" target="_blank" class="wa-btn">
                            <i class="fab fa-whatsapp"></i> 8762474406
                        </a>
                        <a href="https://wa.me/918088584421" target="_blank" class="wa-btn">
                            <i class="fab fa-whatsapp"></i> 8088584421
                        </a>
                    </div>
                    <p style="font-size:0.75rem;color:#64748b;margin-top:6px;">
                        <i class="fas fa-info-circle"></i> Reach out for order updates, delivery tracking, or any queries.
                    </p>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label>Order Notes <span style="font-size:0.7rem;color:#64748b;">(optional)</span></label>
                    <textarea id="orderNotes" rows="2" placeholder="Gate code, special instructions, landmark..."></textarea>
                </div>
            </div>

        </div>

        <!-- ─── RIGHT COLUMN: ORDER SUMMARY ─── -->
        <div>
            <div class="summary-card">
                <div class="summary-title"><i class="fas fa-receipt"></i> Order Summary</div>

                <!-- Dynamic order items -->
                <div id="orderItems">
                    <!-- loaded by JS -->
                </div>

                <hr class="summary-divider" />

                <div class="summary-row">
                    <span class="label">Subtotal</span>
                    <span class="value" id="subtotalDisplay">₹0.00</span>
                </div>

                <!-- Delivery Charge row -->
                <div class="summary-row" id="deliveryChargeRow">
                    <span class="label">Delivery Charge</span>
                    <span class="value" id="deliveryChargeDisplay">₹15.00</span>
                </div>

                <hr class="summary-divider" />

                <div class="summary-row total">
                    <span>Grand Total</span>
                    <span class="value" id="grandTotalDisplay">₹0.00</span>
                </div>

                <!-- Selected Payment Display -->
                <div class="selected-payment-display">
                    <i class="fas fa-check-circle"></i>
                    <span>Selected Payment: <span class="method-name" id="selectedPaymentName">QR Scan & Pay</span></span>
                </div>

                <!-- Action Buttons -->
                <button class="btn-place" id="placeOrderBtn">
                    <i class="fas fa-check-circle"></i> Place Order
                </button>

                <div style="display:flex;justify-content:flex-end;margin-top:10px;">
                    <button class="btn-reset" id="resetBtn"><i class="fas fa-undo-alt"></i> Reset</button>
                </div>
            </div>
        </div>

    </div>

    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    TOAST
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <div class="toast" id="toast">
        <i class="fas fa-info-circle"></i>
        <span id="toastMessage">Notification</span>
    </div>

    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     SUCCESS MODAL (with Download Invoice & WhatsApp send)
     ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <div class="modal-overlay" id="successModal">
        <div class="modal-box">
            <div class="icon-success"><i class="fas fa-check-circle"></i></div>
            <h3>Order Placed! 🎉</h3>
            <p class="order-id">Order ID: <strong id="orderIdDisplay">ORD-2026-001</strong></p>
            <p style="color:#64748b;font-size:0.9rem;margin-bottom:10px;">We'll deliver within 45 minutes.</p>

            <!-- WhatsApp Share with Customer -->
            <?php if ($isAdmin): ?>
                <div style="margin: 15px 0; border: 1px dashed #20ba5a; padding: 12px; border-radius: 12px; background: #f0fdf4;">
                    <div style="font-weight: 700; font-size: 0.85rem; color: #166534; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; justify-content: center;">
                        <i class="fab fa-whatsapp" style="font-size: 1.1rem; color: #20ba5a;"></i> Customer WhatsApp Notification
                    </div>
                    <a href="#" id="waCustomerBtn" class="wa-send-btn" target="_blank" style="display: flex; align-items: center; justify-content: center; gap: 8px; background: #20ba5a; color: white; padding: 10px 14px; border-radius: 30px; font-weight: 600; text-decoration: none; font-size: 0.85rem; border: none; transition: 0.2s;">
                        <i class="fab fa-whatsapp" style="font-size: 1.2rem;"></i> Send Order Confirmation
                    </a>
                </div>
            <?php endif; ?>

            <!-- Action Row -->
            <div class="action-row" style="margin-bottom:15px; display:flex; gap:10px;">
                <button class="btn-modal outline" id="modalContinueBtn" style="flex:1;"><i class="fas fa-arrow-right"></i> Continue</button>
                <a href="#" id="modalTrackBtn" class="btn-modal" style="flex:1; text-align:center; background:var(--accent); color:white; padding:10px 14px; border-radius:30px; font-weight:600; text-decoration:none; font-size:0.85rem; display:inline-flex; align-items:center; justify-content:center; gap:8px; border:none; transition:0.2s;"><i class="fas fa-shipping-fast"></i> Track Order</a>
            </div>

            <?php if ($isAdmin): ?>
                <!-- WhatsApp send buttons (re‑send to drivers) -->
                <div style="margin-top: 10px; font-size: 0.75rem; color: #64748b; font-weight: 600; text-align: center; margin-bottom:6px;">Notify Delivery Personnel:</div>
                <div class="wa-send-row" style="display:flex; gap:10px; justify-content:center;">
                    <a href="#" id="waPrimaryBtn" class="wa-send-btn" style="flex:1; text-align:center; padding:8px; border-radius:8px; font-size:0.75rem; text-decoration:none;" target="_blank"><i class="fab fa-whatsapp"></i> Primary Driver</a>
                    <a href="#" id="waSecondaryBtn" class="wa-send-btn" style="flex:1; text-align:center; padding:8px; border-radius:8px; font-size:0.75rem; text-decoration:none;" target="_blank"><i class="fab fa-whatsapp"></i> Secondary Driver</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    JAVASCRIPT – Complete client-side workflow with backend integration
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <script>
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  CONFIGURATION
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        const STORES = {
            FRESHNER: {
                key: 'ksr_air_freshener_cart',
                name: 'Air Freshener'
            },
            BAKERY: {
                key: 'karavalliCartWeightExtended',
                name: 'Bakery'
            },
            BATHBODY: {
                key: 'glowCartNew',
                name: 'Bath & Body'
            },
            BREAKFAST: {
                key: 'breakfastCart',
                name: 'Breakfast'
            },
            DRYFRUITS: {
                key: 'dryfruits_cart_variants',
                name: 'Dry Fruits'
            },
            GRAINS: {
                key: 'grainCompactCart',
                name: 'Grains'
            },
            CLEANING: {
                key: 'cleanHomeCart',
                name: 'Cleaning'
            },
            COLD: {
                key: 'coldHomeCart',
                name: 'Cold Drinks'
            },
            SUGGESTION: {
                key: 'suggestionCart',
                name: 'Suggestion'
            },
            DISHWASH: {
                key: 'dishwashCart',
                name: 'Dishwash'
            },
            HEALTH: {
                key: 'healthCart',
                name: 'Health & Wellness'
            },
            HAIR: {
                key: 'hairCart',
                name: 'Hair Care'
            },
            HOMECARE: {
                key: 'homeCareCart',
                name: 'Home Care'
            },
            FROZEN: {
                key: 'frozenCart',
                name: 'Frozen Foods'
            },
            SPICES: {
                key: 'spicesCartWeight',
                name: 'Spices'
            },
            STATIONERY: {
                key: 'stationeryCart',
                name: 'Stationery'
            },
            PET: {
                key: 'petCart',
                name: 'Pet Supplies'
            },
            SNACKS: {
                key: 'snacksCart',
                name: 'Snacks'
            },
            POOJA: {
                key: 'poojaCart',
                name: 'Pooja Items'
            },
            PICKLES: {
                key: 'picklesCart',
                name: 'Pickles & Sauces'
            },
            PLASTICS: {
                key: 'plasticsCart',
                name: 'Plastic Ware'
            },
            PULSES: {
                key: 'pulsesCart',
                name: 'Pulses & Dals'
            },
            PUREOILS: {
                key: 'pureOilsCart',
                name: 'Pure Oils'
            },
            FRESHDAIRY: {
                key: 'freshdairy_cart2',
                name: 'Fresh Dairy'
            },
            KITCHENWARE: {
                key: 'kitchenwareCart',
                name: 'Kitchenware'
            }
        };

        const COD_LIMIT = 2000;
        const DELIVERY_CHARGE = 15; // fixed delivery fee
        const UPI_ID = 'jyampallepparathod-1@oksbi';
        const MERCHANT_UPI_IDS = ['jyampallepparathod-1@oksbi', '8088584421@apl'];
        const COMPANY_NAME = 'KSR GURU JI';
        const COMPANY_PHONE = '8088584421';
        const COMPANY_EMAIL = 'support@ksrguru.com';
        const WHATSAPP_NUMBERS = ['918088584421', '918762474406']; // with country code

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  STATE
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        const rawSessionCartItems = <?php echo json_encode(array_values($_SESSION['cart'] ?? [])); ?>;
        const sessionCartItems = Array.isArray(rawSessionCartItems) ? rawSessionCartItems : Object.values(rawSessionCartItems || {});
        let unifiedItems = [];
        let selectedMethod = 'qr';
        let subtotal = 0;
        let userLocation = {
            lat: null,
            lng: null,
            address: ''
        };
        let lastGeneratedOrder = null; // store for PDF download
        let lastWhatsAppMessage = ''; // store for re‑send

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  DOM REFS
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        const paymentOptions = document.querySelectorAll('.payment-option');
        const detailPanel = document.getElementById('paymentDetail');
        const detailTitle = document.getElementById('detailTitle');
        const detailContent = document.getElementById('detailContent');
        const selectedPaymentName = document.getElementById('selectedPaymentName');
        const subtotalDisplay = document.getElementById('subtotalDisplay');
        const deliveryChargeDisplay = document.getElementById('deliveryChargeDisplay');
        const grandTotalDisplay = document.getElementById('grandTotalDisplay');
        const orderItemsContainer = document.getElementById('orderItems');
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        const resetBtn = document.getElementById('resetBtn');
        const toast = document.getElementById('toast');
        const toastMsg = document.getElementById('toastMessage');
        const successModal = document.getElementById('successModal');
        const orderIdDisplay = document.getElementById('orderIdDisplay');
        const modalContinueBtn = document.getElementById('modalContinueBtn');
        const downloadInvoiceBtn = document.getElementById('downloadInvoiceBtn');
        const waPrimaryBtn = document.getElementById('waPrimaryBtn');
        const waSecondaryBtn = document.getElementById('waSecondaryBtn');
        const waCustomerBtn = document.getElementById('waCustomerBtn');

        detailContent.addEventListener('click', async event => {
            const copyButton = event.target.closest('[data-copy-upi-id]');
            if (!copyButton) return;

            try {
                await navigator.clipboard.writeText(copyButton.dataset.copyUpiId);
                showToast('UPI ID copied to clipboard.', 'success');
            } catch (error) {
                showToast('Clipboard access is unavailable in this browser.', 'error');
            }
        });

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  HELPERS
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function formatINR(amount) {
            return '₹' + amount.toFixed(2);
        }

        function normalizeImagePathJS(rawPath) {
            if (!rawPath) return '';
            let clean = rawPath.replace(/\\/g, '/');
            const idx = clean.toLowerCase().indexOf('images/');
            if (idx !== -1) {
                clean = clean.substring(idx);
            } else {
                const parts = clean.split('/');
                clean = 'images/' + parts[parts.length - 1];
            }
            clean = clean.replace(/%(?!25)/g, '%25');
            const path = window.location.pathname;
            if (path.includes('/checkout/') || path.includes('/categories/') || path.includes('/cart/') || path.includes('/admin/') || path.includes('/delivery/')) {
                return '../' + clean;
            }
            return clean;
        }

        function getTotal() {
            return subtotal + DELIVERY_CHARGE;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        function getFormattedTime(date) {
            let hours = date.getHours();
            let minutes = date.getMinutes();
            let seconds = date.getSeconds();
            const ampm = hours >= 12 ? 'pm' : 'am';
            hours = hours % 12;
            hours = hours ? hours : 12;
            const mins = String(minutes).padStart(2, '0');
            const secs = String(seconds).padStart(2, '0');
            return `${hours}:${mins}:${secs} ${ampm}`;
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  GENERATE WHATSAPP MESSAGE (same as original)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function generateWhatsAppMessage(orderData, orderNo, pdfUploadUrl = '') {
            const customer = orderData.customer;
            const items = orderData.items;
            const total = orderData.total;
            const paymentMethod = orderData.paymentMethod;
            const now = new Date();
            const timeStr = getFormattedTime(now);
            const deadline = new Date(now.getTime() + 45 * 60 * 1000);
            const deadlineStr = getFormattedTime(deadline);

            const locationStatus = (orderData.location.lat && orderData.location.lng) ? 'Live Location' : 'Manual Address';
            const mapsLink = `https://maps.google.com/?q=${encodeURIComponent(customer.address)}`;

            let itemsList = items.map(item => {
                let str = `* ${item.name} x ${item.qty}`;
                if (item.variant) str += `\n  - Selection: ${item.variant}`;
                if (item.description) str += `\n  - Details: ${item.description}`;
                return str;
            }).join('\n');

            const primary = WHATSAPP_NUMBERS[0];
            const secondary = WHATSAPP_NUMBERS[1];
            const primaryDisplay = primary.slice(-10);
            const secondaryDisplay = secondary.slice(-10);

            let message = `🆕 NEW ORDER - WITH LIVE LOCATION!

        Order ID: ${orderNo}
        Time: ${timeStr}

        Customer Information:
        Name: ${customer.name}
        Phone: ${customer.mobile}
        Address: ${customer.address}

        📍 Location Status: ${locationStatus} 📍

        Order Details:
        ${itemsList}

        Total Amount: ${formatINR(total)}
        Payment Methods: ${paymentMethod === 'cod' ? 'cash' : paymentMethod}

        🚀Estimated Delivery Time: 45 minutes
        Deadline: ${deadlineStr}

        Priority Delivery Boy:
        Primary: ${primaryDisplay}
        Secondary: ${secondaryDisplay}

        🗺️ Google Maps Link:
        ${mapsLink}`;

            if (pdfUploadUrl) {
                message += `\n\n        📄 Download Invoice PDF:\n        ${pdfUploadUrl}`;
            }

            message += `\n\n        Admin Actions Required:
        1. ✅ Verify order details
        2. 📦 Check inventory
        3. 🔥 Start preparation
        4. 📞 Confirm with delivery boy

        Payment  Related Issues: Contact 8088584421
        Thank you for ordering with KSR GURU JI!


        Reply with:
        ✅ ACCEPT - to confirm order
        ❌ REJECT - to cancel order
        🔧 CHANGE - to modify order`;

            return message;
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  LOAD UNIFIED CART
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function loadUnifiedCart() {
            unifiedItems = [];
            // 1. Load from PHP Session Cart (source of truth)
            if (typeof sessionCartItems !== 'undefined' && Array.isArray(sessionCartItems)) {
                sessionCartItems.forEach(item => {
                    let baseItem = {
                        sourceKey: 'session',
                        storeName: 'Session',
                        originalRef: item,
                        id: item.id || item.product_id,
                        quantity: item.qty || item.quantity || 1,
                        price: parseFloat(item.price || 0),
                        name: item.name,
                        image: normalizeImagePathJS(item.image),
                        meta: item.kn || ''
                    };
                    if (!baseItem.image) baseItem.image = 'https://placehold.co/200x200?text=Product';
                    if (baseItem.price && baseItem.quantity) {
                        unifiedItems.push(baseItem);
                    }
                });
            }

            // 2. Load from localStorage as fallback
            for (let [storeId, store] of Object.entries(STORES)) {
                const raw = localStorage.getItem(store.key);
                if (!raw) continue;
                try {
                    const cartArray = JSON.parse(raw);
                    if (!Array.isArray(cartArray)) continue;
                    cartArray.forEach(item => {
                        let itemId = item.id || item.productId || item.variantId;
                        // Avoid duplicates from session
                        if (unifiedItems.some(i => String(i.id) === String(itemId))) {
                            return;
                        }
                        let baseItem = {
                            sourceKey: store.key,
                            storeName: store.name,
                            originalRef: item,
                            id: itemId,
                            quantity: item.quantity || item.qty || 1,
                            price: parseFloat(item.price || item.unitPrice || item.variantPrice || 0),
                            name: item.name || item.name_en || item.displayName || item.nameEn,
                            image: normalizeImagePathJS(item.image || item.imgUrl || item.imageUrl),
                            meta: item.weightLabel || item.variantSize || item.name_kn || item.nameKn || ''
                        };
                        if (!baseItem.name) baseItem.name = `Item ${baseItem.id || 'unknown'}`;
                        if (!baseItem.image) baseItem.image = 'https://placehold.co/200x200?text=Product';
                        if (baseItem.price && baseItem.quantity) {
                            unifiedItems.push(baseItem);
                        }
                    });
                } catch (e) {
                    console.warn(`Error parsing ${store.key}`, e);
                }
            }
            subtotal = unifiedItems.reduce((sum, i) => sum + (i.price * i.quantity), 0);
            updateSummary();
            renderPaymentDetail(selectedMethod);
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  UPDATE SUMMARY
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function updateSummary() {
            const total = getTotal();
            subtotalDisplay.textContent = formatINR(subtotal);
            deliveryChargeDisplay.textContent = formatINR(DELIVERY_CHARGE);
            grandTotalDisplay.textContent = formatINR(total);

            const orderAmountEl = document.getElementById("orderAmount");
            if (orderAmountEl) {
                orderAmountEl.value = total.toFixed(2);
            }

            let html = '';
            if (unifiedItems.length === 0) {
                html = `<p style="color:#64748b; text-align:center; padding:1rem 0;">Your cart is empty.</p>`;
                placeOrderBtn.disabled = true;
            } else {
                placeOrderBtn.disabled = false;
                unifiedItems.forEach(item => {
                    const itemTotal = item.price * item.quantity;

                    // ---- Build one clean detail line, without duplicates ----
                    // The variant and the meta label frequently carry the same
                    // text (e.g. "250g"). Show each piece of information once.
                    const norm = (s) => String(s || '').replace(/[^a-z0-9]/gi, '').toLowerCase();

                    const seen = new Set();
                    const extraBits = [];

                    const pushBit = (text) => {
                        const t = String(text || '').trim();
                        const key = norm(t);
                        if (t !== '' && key !== '' && !seen.has(key)) {
                            seen.add(key);
                            extraBits.push(t);
                        }
                    };

                    pushBit(item.originalRef ? item.originalRef.variant : '');
                    pushBit(item.meta);

                    // Never repeat the product name itself as a detail line.
                    seen.add(norm(item.name));

                    let detailsHtml = '';
                    if (extraBits.length) {
                        detailsHtml += `<div class="oi-sub">${escapeHtml(extraBits.join(' · '))}</div>`;
                    }
                    if (item.originalRef && item.originalRef.description) {
                        detailsHtml += `<div class="oi-note">${escapeHtml(item.originalRef.description)}</div>`;
                    }

                    const qtyChip = item.quantity > 1 ?
                        `<span class="oi-qty">×${item.quantity}</span>` :
                        '';

                    const imgHtml = item.image && !item.image.includes('placehold.co') ?
                        `<img src="${item.image}" alt="${item.name}" onerror="this.outerHTML='<span class=\\'emoji-fallback\\'>🛒</span>'" style="width:100%; height:100%; object-fit:cover;" />` :
                        `<span class="emoji-fallback">🛒</span>`;

                    html += `
                            <div class="order-item">
                                <div class="order-item-image">
                                    ${imgHtml}
                                </div>
                                <div class="oi-body">
                                    <div class="oi-name">${escapeHtml(item.name)}${qtyChip}</div>
                                    ${detailsHtml}
                                </div>
                                <div class="oi-price">${formatINR(itemTotal)}</div>
                            </div>
                        `;
                });
            }
            orderItemsContainer.innerHTML = html;
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  PAYMENT DETAIL PANEL (method-aware)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function renderPaymentDetail(method) {
            const total = getTotal();

            // Shared "you pay" strip so the amount is always visible.
            const amountStrip = `
                <div class="pd-amount">
                    <span class="pd-amount-label">Amount to pay</span>
                    <span class="pd-amount-value">${formatINR(total)}</span>
                </div>
            `;

            const secureNote = `
                <div class="pd-secure">
                    <i class="fas fa-lock"></i> Secure 256-bit encrypted checkout
                </div>
            `;

            const merchantAccounts = `
                <div style="display:grid;gap:8px;margin:14px 0;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                    <strong style="font-size:0.84rem;color:#334155;">Merchant UPI IDs</strong>
                    ${MERCHANT_UPI_IDS.map(upiId => `
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                            <span style="overflow-wrap:anywhere;font-weight:600;color:#0f172a;">${escapeHtml(upiId)}</span>
                            <button type="button" data-copy-upi-id="${escapeHtml(upiId)}" aria-label="Copy ${escapeHtml(upiId)}" title="Copy UPI ID" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:#334155;cursor:pointer;">
                                <i class="fas fa-copy" aria-hidden="true"></i>
                            </button>
                        </div>
                    `).join('')}
                    <span style="font-size:0.76rem;line-height:1.4;color:#64748b;">For account reference. Complete this order in Razorpay Checkout so payment is verified automatically.</span>
                </div>
            `;

            if (method !== 'cod') {
                // Per-method identity so the panel is not one generic blob.
                const meta = {
                    qr: {
                        icon: 'fa-qrcode',
                        color: '#10b981',
                        title: 'UPI via Razorpay',
                        blurb: 'Continue to Razorpay Checkout to see the order-specific UPI options and QR, if available for your device.'
                    },
                    upi: {
                        icon: 'fa-at',
                        color: '#097969',
                        title: 'Pay by UPI',
                        blurb: 'Choose an available UPI app in Razorpay Checkout. Payment is confirmed after Razorpay verifies it.'
                    },
                    gpay: {
                        icon: 'fa-google-pay',
                        color: '#4285f4',
                        title: 'Google Pay',
                        blurb: 'Choose Google Pay in Razorpay Checkout if it is available for your device and account.'
                    },
                    phonepe: {
                        icon: 'fa-mobile-alt',
                        color: '#5f259f',
                        title: 'PhonePe',
                        blurb: 'Choose PhonePe in Razorpay Checkout if it is available for your device and account.'
                    },
                    paytm: {
                        icon: 'fa-wallet',
                        color: '#00baf2',
                        title: 'Paytm',
                        blurb: 'Choose a supported Paytm payment option in Razorpay Checkout if available.'
                    },
                    bhim: {
                        icon: 'fa-hand-holding-heart',
                        color: '#1a73e8',
                        title: 'BHIM UPI',
                        blurb: 'Choose BHIM in Razorpay Checkout if it is available for your device and account.'
                    },
                    amazonpay: {
                        icon: 'fa-amazon',
                        color: '#ff9900',
                        title: 'Amazon Pay',
                        blurb: 'Choose Amazon Pay in Razorpay Checkout if it is available for your device and account.'
                    },
                    card: {
                        icon: 'fa-credit-card',
                        color: '#1a1b41',
                        title: 'Card / Net Banking',
                        blurb: 'Choose an available card or net banking option in secure Razorpay Checkout.'
                    }
                };

                const info = meta[method] || meta.card;

                detailTitle.innerHTML =
                    `<i class="fas ${info.icon}" style="color:${info.color};"></i> ${escapeHtml(info.title)}`;

                detailContent.innerHTML = `
                    <div class="pd-panel">
                        <div class="pd-icon" style="color:${info.color};">
                            <i class="fas ${info.icon}"></i>
                        </div>
                        <p class="pd-blurb">${escapeHtml(info.blurb)}</p>
                        <div class="pd-brands">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-google-pay"></i>
                            <i class="fas fa-mobile-alt"></i>
                            <i class="fas fa-credit-card"></i>
                        </div>
                        ${amountStrip}
                        ${merchantAccounts}
                        ${secureNote}
                    </div>
                `;
                detailPanel.classList.add('active');

                const nameMap = {
                    qr: 'UPI via Razorpay',
                    upi: 'UPI ID',
                    gpay: 'Google Pay',
                    phonepe: 'PhonePe',
                    paytm: 'Paytm',
                    bhim: 'BHIM UPI',
                    amazonpay: 'Amazon Pay',
                    card: 'Card / Net Banking'
                };
                selectedPaymentName.textContent = nameMap[method] || 'Razorpay Gateway';
                return;
            }

            const qrImageUrl =
                `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=upi://pay?pa=${encodeURIComponent(UPI_ID)}&pn=KSR%20GURU%20JI&am=${total}&cu=INR`;

            // Check if user is on mobile
            const isAndroid = /Android/i.test(navigator.userAgent);
            const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            const isMobile = isAndroid || isIOS;

            const upiDeepLink = `upi://pay?pa=${encodeURIComponent(UPI_ID)}&pn=${encodeURIComponent(COMPANY_NAME)}&am=${total}&cu=INR&tn=KSR%20Order`;

            let intentUrl = upiDeepLink;
            if (isMobile) {
                if (isAndroid) {
                    if (method === 'gpay') intentUrl = `intent://pay?pa=${encodeURIComponent(UPI_ID)}&pn=${encodeURIComponent(COMPANY_NAME)}&am=${total}&cu=INR&tn=KSR%20Order#Intent;scheme=upi;package=com.google.android.apps.nbu.paisa.user;S.browser_fallback_url=${encodeURIComponent('https://play.google.com/store/apps/details?id=com.google.android.apps.nbu.paisa.user')};end`;
                    else if (method === 'phonepe') intentUrl = `intent://pay?pa=${encodeURIComponent(UPI_ID)}&pn=${encodeURIComponent(COMPANY_NAME)}&am=${total}&cu=INR&tn=KSR%20Order#Intent;scheme=upi;package=com.phonepe.app;S.browser_fallback_url=${encodeURIComponent('https://play.google.com/store/apps/details?id=com.phonepe.app')};end`;
                    else if (method === 'paytm') intentUrl = `intent://pay?pa=${encodeURIComponent(UPI_ID)}&pn=${encodeURIComponent(COMPANY_NAME)}&am=${total}&cu=INR&tn=KSR%20Order#Intent;scheme=upi;package=net.one97.paytm;S.browser_fallback_url=${encodeURIComponent('https://play.google.com/store/apps/details?id=net.one97.paytm')};end`;
                    else if (method === 'bhim') intentUrl = `intent://pay?pa=${encodeURIComponent(UPI_ID)}&pn=${encodeURIComponent(COMPANY_NAME)}&am=${total}&cu=INR&tn=KSR%20Order#Intent;scheme=upi;package=in.org.npci.upiapp;S.browser_fallback_url=${encodeURIComponent('https://play.google.com/store/apps/details?id=in.org.npci.upiapp')};end`;
                    else if (method === 'amazonpay') intentUrl = `intent://pay?pa=${encodeURIComponent(UPI_ID)}&pn=${encodeURIComponent(COMPANY_NAME)}&am=${total}&cu=INR&tn=KSR%20Order#Intent;scheme=upi;package=in.amazon.mShop.android.shopping;S.browser_fallback_url=${encodeURIComponent('https://play.google.com/store/apps/details?id=in.amazon.mShop.android.shopping')};end`;
                } else if (isIOS) {
                    if (method === 'gpay') intentUrl = `gpay://upi/pay?pa=${encodeURIComponent(UPI_ID)}&pn=${encodeURIComponent(COMPANY_NAME)}&am=${total}&cu=INR&tn=KSR%20Order`;
                    else if (method === 'phonepe') intentUrl = `phonepe://pay?pa=${encodeURIComponent(UPI_ID)}&pn=${encodeURIComponent(COMPANY_NAME)}&am=${total}&cu=INR&tn=KSR%20Order`;
                    else if (method === 'paytm') intentUrl = `paytmmp://pay?pa=${encodeURIComponent(UPI_ID)}&pn=${encodeURIComponent(COMPANY_NAME)}&am=${total}&cu=INR&tn=KSR%20Order`;
                }
            }

            const copyBtnHtml = `
                <button type="button" onclick="navigator.clipboard.writeText('${UPI_ID}'); showToast('UPI ID copied to clipboard!', 'success');" style="display:inline-flex;align-items:center;gap:4px;margin-left:8px;background:#eef2ff;border:none;color:#2563eb;font-weight:700;font-size:0.75rem;cursor:pointer;padding:4px 8px;border-radius:6px;transition:0.2s;">
                    <i class="fas fa-copy"></i> Copy
                </button>
            `;

            let launchButtonHtml = '';
            if (isMobile && method !== 'cod') {
                launchButtonHtml = `
                    <div style="margin: 14px 0 8px 0;">
                        <a href="${intentUrl}" class="pay-intent-btn" style="display:flex;align-items:center;justify-content:center;gap:10px;background:#28a745;color:#fff;padding:12px;border-radius:12px;font-weight:700;text-decoration:none;font-size:1rem;box-shadow:0 4px 6px rgba(0,0,0,0.1);transition:transform 0.2s;text-align:center;">
                            <i class="${method === 'gpay' ? 'fab fa-google-pay' : method === 'phonepe' ? 'fas fa-mobile-alt' : method === 'paytm' ? 'fas fa-wallet' : method === 'amazonpay' ? 'fab fa-amazon' : 'fas fa-paper-plane'}"></i>
                            Pay ₹${total.toFixed(2)} via ${method === 'gpay' ? 'GPay' : method === 'phonepe' ? 'PhonePe' : method === 'paytm' ? 'Paytm' : method === 'amazonpay' ? 'Amazon Pay' : method === 'bhim' ? 'BHIM UPI' : 'UPI App'}
                        </a>
                        <div style="font-size:0.75rem;color:#64748b;text-align:center;margin-top:6px;">
                            <i class="fas fa-arrow-right"></i> Pay on app, return here, and paste your UTR below.
                        </div>
                    </div>
                `;
            }

            const configs = {
                qr: {
                    title: '<i class="fas fa-qrcode"></i> Scan QR Code',
                    content: `
                                <div class="qr-box">
                                    <img src="${qrImageUrl}" alt="UPI QR Code" style="max-width:200px;margin:0 auto;display:block;" />
                                    <div class="upi-id-display" style="margin-top:10px;">
                                        <small>UPI ID</small>
                                        <strong>${UPI_ID}</strong> ${copyBtnHtml}
                                    </div>
                                    ${launchButtonHtml}
                                    <p style="font-size:0.8rem;color:#64748b;margin-top:4px;">
                                        Scan with Google Pay, PhonePe, Paytm, BHIM, Amazon Pay or any UPI app
                                    </p>
                                </div>
                            `
                },
                upi: {
                    title: '<i class="fas fa-at"></i> Pay with UPI ID',
                    content: `
                                <div style="margin:6px 0;">
                                    <label style="font-weight:600;font-size:0.85rem;">UPI ID</label>
                                    <div class="upi-id-display" style="font-size:1.15rem;letter-spacing:0.3px;display:flex;align-items:center;justify-content:center;gap:10px;padding:12px;background:#f8fafc;border-radius:10px;border:1px solid #cbd5e1;">
                                        <strong>${UPI_ID}</strong>
                                        ${copyBtnHtml}
                                        </p>
                                    </div>
                                    ${launchButtonHtml}
                                    <p style="font-size:0.8rem;color:#64748b;margin-top:4px;">
                                        Send money to this UPI ID from any UPI app.
                                    </p>
                                </div>
                            `
                },
                gpay: {
                    title: '<i class="fab fa-google-pay"></i> Google Pay',
                    content: `
                                <div style="margin:6px 0;">
                                    <div style="background:#e8f0fe;padding:12px 16px;border-radius:12px;display:flex;align-items:center;gap:12px;border:1px solid #c2dbff;">
                                        <i class="fab fa-google-pay" style="font-size:2.4rem;color:#4285f4;"></i>
                                        <div style="flex:1;">
                                            <div style="font-weight:700;color:#1a73e8;">Google Pay</div>
                                            <div style="font-size:0.8rem;color:#64748b;word-break:break-all;">UPI ID: <strong>${UPI_ID}</strong> ${copyBtnHtml}</div>
                                        </div>
                                    </div>
                                    ${launchButtonHtml}
                                </div>
                                <div class="detail-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Pay ${formatINR(total)} to UPI ID: <strong>${UPI_ID}</strong>
                                </div>
                            `
                },
                phonepe: {
                    title: '<i class="fas fa-mobile-alt"></i> PhonePe',
                    content: `
                                <div style="margin:6px 0;">
                                    <div style="background:#f3e8ff;padding:12px 16px;border-radius:12px;display:flex;align-items:center;gap:12px;border:1px solid #e9d5ff;">
                                        <i class="fas fa-mobile-alt" style="font-size:2rem;color:#5f259f;"></i>
                                        <div style="flex:1;">
                                            <div style="font-weight:700;color:#6b21a8;">PhonePe</div>
                                            <div style="font-size:0.8rem;color:#64748b;word-break:break-all;">UPI ID: <strong>${UPI_ID}</strong> ${copyBtnHtml}</div>
                                        </div>
                                    </div>
                                    ${launchButtonHtml}
                                </div>
                                <div class="detail-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Pay ${formatINR(total)} to UPI ID: <strong>${UPI_ID}</strong>
                                </div>
                            `
                },
                paytm: {
                    title: '<i class="fas fa-wallet"></i> Paytm',
                    content: `
                                <div style="margin:6px 0;">
                                    <div style="background:#e0f2fe;padding:12px 16px;border-radius:12px;display:flex;align-items:center;gap:12px;border:1px solid #bae6fd;">
                                        <i class="fas fa-wallet" style="font-size:2rem;color:#00baf2;"></i>
                                        <div style="flex:1;">
                                            <div style="font-weight:700;color:#0369a1;">Paytm</div>
                                            <div style="font-size:0.8rem;color:#64748b;word-break:break-all;">UPI ID: <strong>${UPI_ID}</strong> ${copyBtnHtml}</div>
                                        </div>
                                    </div>
                                    ${launchButtonHtml}
                                </div>
                                <div class="detail-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Pay ${formatINR(total)} to UPI ID: <strong>${UPI_ID}</strong>
                                </div>
                            `
                },
                bhim: {
                    title: '<i class="fas fa-hand-holding-heart"></i> BHIM UPI',
                    content: `
                                <div style="margin:6px 0;">
                                    <div style="background:#f0faf3;padding:12px 16px;border-radius:12px;display:flex;align-items:center;gap:12px;border:1px solid #bbf7d0;">
                                        <i class="fas fa-hand-holding-heart" style="font-size:2rem;color:#1a73e8;"></i>
                                        <div style="flex:1;">
                                            <div style="font-weight:700;color:#1e7e34;">BHIM UPI</div>
                                            <div style="font-size:0.8rem;color:#64748b;word-break:break-all;">UPI ID: <strong>${UPI_ID}</strong> ${copyBtnHtml}</div>
                                        </div>
                                    </div>
                                    ${launchButtonHtml}
                                </div>
                                <div class="detail-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Pay ${formatINR(total)} to UPI ID: <strong>${UPI_ID}</strong>
                                </div>
                            `
                },
                amazonpay: {
                    title: '<i class="fab fa-amazon"></i> Amazon Pay',
                    content: `
                                <div style="margin:6px 0;">
                                    <div style="background:#fff7ed;padding:12px 16px;border-radius:12px;display:flex;align-items:center;gap:12px;border:1px solid #ffedd5;">
                                        <i class="fab fa-amazon" style="font-size:2.2rem;color:#ff9900;"></i>
                                        <div style="flex:1;">
                                            <div style="font-weight:700;color:#c2410c;">Amazon Pay</div>
                                            <div style="font-size:0.8rem;color:#64748b;word-break:break-all;">UPI ID: <strong>${UPI_ID}</strong> ${copyBtnHtml}</div>
                                        </div>
                                    </div>
                                    ${launchButtonHtml}
                                </div>
                                <div class="detail-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Pay ${formatINR(total)} to UPI ID: <strong>${UPI_ID}</strong>
                                </div>
                            `
                },
                cod: {
                    title: '<i class="fas fa-hand-holding-usd"></i> Cash on Delivery',
                    content: `
                                <div style="margin:6px 0;">
                                    <div style="background:#fff8e6;padding:14px 16px;border-radius:12px;border-left:4px solid #f5a623;">
                                        <div style="font-weight:700;font-size:1.1rem;">Pay at Delivery</div>
                                        <div style="font-size:0.9rem;color:#5e4a1a;margin-top:4px;">
                                            <i class="fas fa-truck"></i> You will pay <strong>${formatINR(total)}</strong> in cash
                                            when your order arrives.
                                        </div>
                                    </div>
                                    <div class="cod-warning" style="margin-top:8px;">
                                        <i class="fas fa-info-circle"></i>
                                        Cash on Delivery is available for orders up to <strong>${formatINR(COD_LIMIT)}</strong>.
                                        Your total is <strong>${formatINR(total)}</strong>.
                                        ${total > COD_LIMIT ? '<span style="color:#dc2626;font-weight:600;">⚠️ Exceeds COD limit!</span>' : '✅ Within COD limit.'}
                                    </div>
                                </div>
                            `
                }
            };

            const config = configs[method] || configs.qr;
            detailTitle.innerHTML = config.title;
            detailContent.innerHTML = config.content;
            detailPanel.classList.add('active');

            const nameMap = {
                qr: 'QR Scan & Pay',
                upi: 'UPI ID',
                gpay: 'Google Pay',
                phonepe: 'PhonePe',
                paytm: 'Paytm',
                bhim: 'BHIM UPI',
                amazonpay: 'Amazon Pay',
                cod: 'Cash on Delivery'
            };
            selectedPaymentName.textContent = nameMap[method] || method.toUpperCase();
        }

        function selectPayment(method, element) {
            paymentOptions.forEach(el => el.classList.remove('selected'));
            if (element) {
                element.classList.add('selected');
            } else {
                const el = document.querySelector(`.payment-option[data-method="${method}"]`);
                if (el) el.classList.add('selected');
            }
            selectedMethod = method;
            renderPaymentDetail(method);
        }

        function payUPI(method) {
            selectPayment(method);

            let amountVal = document.getElementById("orderAmount") ? document.getElementById("orderAmount").value : getTotal().toFixed(2);
            let orderIdVal = document.getElementById("orderId") ? document.getElementById("orderId").value : "KSR Order";

            let upiId = "jyampallepparathod-1@oksbi";
            let name = "Jampaleppa Rathod";

            let upi =
                "upi://pay?pa=" + encodeURIComponent(upiId) +
                "&pn=" + encodeURIComponent(name) +
                "&am=" + amountVal +
                "&cu=INR" +
                "&tn=" + orderIdVal;

            switch (method) {
                case "gpay":
                    window.location.href =
                        "intent://" + upi.replace("upi://", "") +
                        "#Intent;scheme=upi;package=com.google.android.apps.nbu.paisa.user;end";
                    break;

                case "phonepe":
                    window.location.href =
                        "intent://" + upi.replace("upi://", "") +
                        "#Intent;scheme=upi;package=com.phonepe.app;end";
                    break;

                case "paytm":
                    window.location.href =
                        "intent://" + upi.replace("upi://", "") +
                        "#Intent;scheme=upi;package=net.one97.paytm;end";
                    break;

                case "amazonpay":
                    window.location.href =
                        "intent://" + upi.replace("upi://", "") +
                        "#Intent;scheme=upi;package=in.amazon.mShop.android.shopping;end";
                    break;

                case "bhim":
                    window.location.href =
                        "intent://" + upi.replace("upi://", "") +
                        "#Intent;scheme=upi;package=in.org.npci.upiapp;end";
                    break;
            }
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  VALIDATION (with UTR ID)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function validateForm() {
            let isValid = true;
            const fields = [{
                    id: 'fullName',
                    errorId: 'fullNameError',
                    test: val => val.trim().length >= 2
                },
                {
                    id: 'mobile',
                    errorId: 'mobileError',
                    test: val => /^[6-9]\d{9}$/.test(val.trim())
                },
                {
                    id: 'address',
                    errorId: 'addressError',
                    test: val => val.trim().length >= 5
                },
                {
                    id: 'city',
                    errorId: 'cityError',
                    test: val => val.trim().length >= 2
                },
                {
                    id: 'pincode',
                    errorId: 'pincodeError',
                    test: val => /^\d{6}$/.test(val.trim())
                }
            ];

            fields.forEach(f => {
                const input = document.getElementById(f.id);
                const error = document.getElementById(f.errorId);
                const val = input.value;
                if (!f.test(val)) {
                    input.classList.add('error');
                    error.classList.add('show');
                    isValid = false;
                } else {
                    input.classList.remove('error');
                    error.classList.remove('show');
                }
            });
            return isValid;
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  CLEAR ALL CARTS (client-side)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function clearAllCarts() {
            for (let store of Object.values(STORES)) {
                localStorage.removeItem(store.key);
            }
            localStorage.removeItem('ksrCart');
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  GENERATE PDF INVOICE (same as original)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function generateInvoicePDF(orderData, orderNo, transactionId, paymentStatus) {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            const pageWidth = 210;
            const margin = 20;
            let y = margin;

            // Header
            doc.setFontSize(18);
            doc.setTextColor(15, 123, 58);
            doc.text(COMPANY_NAME, pageWidth / 2, y, {
                align: 'center'
            });
            y += 8;
            doc.setFontSize(10);
            doc.setTextColor(50);
            doc.text('Everything Your Family Needs, Every Day', pageWidth / 2, y, {
                align: 'center'
            });
            y += 8;
            doc.setDrawColor(15, 123, 58);
            doc.line(margin, y, pageWidth - margin, y);
            y += 10;

            // Invoice details
            doc.setFontSize(10);
            doc.setTextColor(50);
            const dateStr = new Date().toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
            doc.text(`Invoice No : ${orderNo}`, margin, y);
            doc.text(`Date       : ${dateStr}`, margin, y + 6);
            doc.text(`Payment    : ${paymentStatus}`, 140, y);
            doc.text(`Txn ID     : ${transactionId}`, 140, y + 6);
            y += 20;

            // Customer
            doc.setFontSize(11);
            doc.setFont(undefined, 'bold');
            doc.text('Customer', margin, y);
            y += 6;
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.text(`Name  : ${orderData.customer.name}`, margin, y);
            doc.text(`Phone : ${orderData.customer.mobile}`, margin, y + 6);
            doc.text(`Email : ${orderData.customer.email || '—'}`, margin, y + 12);
            doc.text(`Address: ${orderData.customer.address}, ${orderData.customer.city}, ${orderData.customer.pincode}`, margin, y +
                18);
            y += 30;

            // Items table
            const tableRows = orderData.items.map(item => {
                let displayName = item.name;
                if (item.variant) displayName += `\n(${item.variant})`;
                if (item.description) displayName += `\nDetails: ${item.description}`;
                return [
                    displayName,
                    String(item.qty),
                    `Rs. ${item.price.toFixed(2)}`,
                    `Rs. ${(item.price * item.qty).toFixed(2)}`
                ];
            });
            doc.autoTable({
                startY: y,
                head: [
                    ['Product', 'Qty', 'Price', 'Total']
                ],
                body: tableRows,
                theme: 'striped',
                headStyles: {
                    fillColor: [15, 123, 58]
                },
                columnStyles: {
                    1: {
                        halign: 'center'
                    },
                    2: {
                        halign: 'right'
                    },
                    3: {
                        halign: 'right'
                    }
                },
                margin: {
                    left: margin,
                    right: margin
                }
            });

            // Get final Y after table
            let finalY = doc.lastAutoTable.finalY + 10;

            // Totals: subtotal, delivery, grand total
            const subtotalVal = orderData.subtotal;
            const delivery = DELIVERY_CHARGE;
            const grandTotal = subtotalVal + delivery;

            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.text('Subtotal', 140, finalY);
            doc.text(`Rs. ${subtotalVal.toFixed(2)}`, 190, finalY, {
                align: 'right'
            });
            finalY += 6;
            doc.text('Delivery Charge', 140, finalY);
            doc.text(`Rs. ${delivery.toFixed(2)}`, 190, finalY, {
                align: 'right'
            });
            finalY += 6;
            finalY += 4;
            doc.setFont(undefined, 'bold');
            doc.setFontSize(12);
            doc.text('Grand Total', 140, finalY);
            doc.text(`Rs. ${grandTotal.toFixed(2)}`, 190, finalY, {
                align: 'right'
            });
            finalY += 14;

            // Payment info
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.text(`Payment Method : ${orderData.paymentMethod.toUpperCase()}`, margin, finalY);
            doc.text(`Transaction ID : ${transactionId}`, margin, finalY + 6);
            doc.text(`Payment Status : ${paymentStatus}`, margin, finalY + 12);
            finalY += 18;

            // Delivery info
            doc.setFontSize(10);
            doc.setFont(undefined, 'bold');
            doc.text('Delivery', margin, finalY);
            finalY += 6;
            doc.setFont(undefined, 'normal');
            doc.text('Delivery within 45 minutes', margin, finalY);
            doc.text('WhatsApp: 8088584421, 8762474406', margin, finalY + 6);

            // Footer
            const pageHeight = doc.internal.pageSize.getHeight();
            doc.setFontSize(10);
            doc.setTextColor(15, 123, 58);
            doc.setFont(undefined, 'bold');
            doc.text('Thank You for Shopping with ' + COMPANY_NAME, pageWidth / 2, pageHeight - 14, {
                align: 'center'
            });
            doc.setFontSize(8);
            doc.setTextColor(80);
            doc.setFont(undefined, 'normal');
            doc.text(`Website: www.ksrguruji.com  |  WhatsApp: ${COMPANY_PHONE}  |  Email: ${COMPANY_EMAIL}`, pageWidth / 2,
                pageHeight - 6, {
                    align: 'center'
                });

            // Save PDF
            const pdfBlob = doc.output('blob');
            const pdfUrl = URL.createObjectURL(pdfBlob);
            return {
                url: pdfUrl,
                blob: pdfBlob
            };
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  PLACE ORDER – Sends data to backend via AJAX
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        async function placeOrder() {
            if (unifiedItems.length === 0) {
                showToast('Your cart is empty. Add items before checkout.', 'error');
                return;
            }
            if (!validateForm()) {
                showToast('Please fill in all required fields correctly.', 'error');
                return;
            }
            let total = getTotal();
            if (selectedMethod === 'cod' && total > COD_LIMIT) {
                showToast(`Cash on Delivery available only for orders up to ${formatINR(COD_LIMIT)}. Your total is ${formatINR(total)}.`,
                    'error');
                return;
            }
            placeOrderBtn.disabled = true;
            const originalHTML = placeOrderBtn.innerHTML;
            placeOrderBtn.innerHTML = '<span class="spinner"></span> Placing Order...';

            // Build order data
            const orderData = {
                customer: {
                    name: document.getElementById('fullName').value.trim(),
                    mobile: document.getElementById('mobile').value.trim(),
                    email: '',
                    address: document.getElementById('address').value.trim(),
                    city: document.getElementById('city').value.trim(),
                    pincode: document.getElementById('pincode').value.trim(),
                },
                location: {
                    lat: userLocation.lat,
                    lng: userLocation.lng,
                    address: userLocation.address || ''
                },
                items: unifiedItems.map(i => ({
                    name: i.name,
                    price: i.price,
                    qty: i.quantity,
                    store: i.storeName,
                    id: i.id,
                    variant: i.originalRef ? i.originalRef.variant || '' : '',
                    description: i.originalRef ? i.originalRef.description || '' : ''
                })),
                subtotal: subtotal,
                total: total,
                deliveryCharge: DELIVERY_CHARGE,
                paymentMethod: selectedMethod,
                delivery: {
                    notes: document.getElementById('orderNotes').value.trim()
                }
            };

            // Prepare POST data
            const formData = new FormData();
            formData.append('action', 'create_order');
            formData.append('name', orderData.customer.name);
            formData.append('email', orderData.customer.email);
            formData.append('phone', orderData.customer.mobile);
            formData.append('address', orderData.customer.address);
            formData.append('city', orderData.customer.city);
            formData.append('state', 'Karnataka'); // default, could add field
            formData.append('pincode', orderData.customer.pincode);
            formData.append('payment_method', orderData.paymentMethod);
            formData.append('notes', orderData.delivery.notes);
            formData.append('items', JSON.stringify(orderData.items));
            formData.append('subtotal', orderData.subtotal);
            formData.append('total', orderData.total);
            formData.append('delivery_charge', orderData.deliveryCharge);
            formData.append('transaction_id', '');

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    const orderNo = result.order_number;
                    const orderId = result.order_id;

                    if (result.amount !== undefined) {
                        const authoritativeTotal = Number(result.amount) / 100;
                        if (Number.isFinite(authoritativeTotal)) {
                            total = authoritativeTotal;
                            orderData.total = authoritativeTotal;
                            grandTotalDisplay.textContent = formatINR(authoritativeTotal);
                            document.getElementById('orderAmount').value = authoritativeTotal.toFixed(2);
                            const paymentAmount = detailContent.querySelector('.pd-amount-value');
                            if (paymentAmount) paymentAmount.textContent = formatINR(authoritativeTotal);
                        }
                    }

                    let transactionId = '';
                    let paymentStatus = '';

                    if (selectedMethod === 'cod') {
                        transactionId = result.transaction_id || ('COD' + Date.now().toString().slice(-8));
                        paymentStatus = 'COD_PENDING';
                    } else {
                        // ── Step 1: open the gateway and collect the response ──
                        try {
                            await initiateRazorpayCheckout(result, orderData);
                        } catch (paymentErr) {
                            showToast('⚠️ Payment failed or was cancelled: ' + paymentErr, 'warning', 5000);
                            placeOrderBtn.disabled = false;
                            placeOrderBtn.innerHTML = originalHTML;
                            return;
                        }

                        // ── Step 2: WAIT FOR SERVER CONFIRMATION ──
                        // The browser callback alone never marks an order
                        // paid. We poll api/payment_status.php until the
                        // server (or the webhook) confirms SUCCESS.
                        showPaymentStatusBox();
                        placeOrderBtn.innerHTML = '<span class="spinner"></span> Confirming payment...';

                        try {
                            const confirmed = await waitForPaymentConfirmation(orderId);
                            transactionId = (confirmed.payment && confirmed.payment.gateway_payment_id) ||
                                (confirmed.payment && confirmed.payment.transaction_id) ||
                                '';
                            paymentStatus = 'PAID';
                        } catch (confirmErr) {
                            showToast('⚠️ ' + confirmErr.message, 'error', 6000);
                            placeOrderBtn.disabled = false;
                            placeOrderBtn.innerHTML = originalHTML;
                            return;
                        }
                    }

                    // ── Store in localStorage for reference (display only) ──
                    let orders = JSON.parse(localStorage.getItem('ksrOrders')) || [];
                    orders.unshift({
                        id: orderNo,
                        ...orderData,
                        transactionId,
                        paymentStatus,
                        timestamp: new Date().toISOString()
                    });
                    localStorage.setItem('ksrOrders', JSON.stringify(orders));

                    // ── Generate the invoice document ──
                    // NOTE: this is a customer receipt. It is only produced
                    // after the SERVER confirmed the payment above, so an
                    // unpaid order can never receive a PAID invoice.
                    const pdfResult = generateInvoicePDF(orderData, orderNo, transactionId, paymentStatus);
                    lastGeneratedOrder = {
                        ...orderData,
                        orderNo,
                        transactionId,
                        paymentStatus,
                        pdfResult
                    };

                    // ── Upload PDF Invoice to Server ──
                    let pdfUploadUrl = '';
                    const invoiceFormData = new FormData();
                    invoiceFormData.append('action', 'upload_invoice');
                    invoiceFormData.append('order_number', orderNo);
                    invoiceFormData.append('pdf_file', pdfResult.blob, `Invoice_${orderNo}.pdf`);

                    try {
                        const uploadRes = await fetch(window.location.href, {
                            method: 'POST',
                            body: invoiceFormData
                        });
                        const uploadData = await uploadRes.json();
                        if (uploadData.success) {
                            pdfUploadUrl = uploadData.pdf_url;
                            lastGeneratedOrder.pdfUrlOnServer = pdfUploadUrl;
                        }
                    } catch (uploadError) {
                        console.error('Invoice upload failed:', uploadError);
                    }

                    if (!pdfUploadUrl) {
                        const protocol = window.location.protocol;
                        const host = window.location.host;
                        const path = window.location.pathname.replace('/checkout/checkout.php', '');
                        pdfUploadUrl = `${protocol}//${host}${path}/invoices/Invoice_${orderNo}.pdf`;
                    }

                    // ── Generate WhatsApp message for delivery boy with PDF URL ──
                    const waMessage = generateWhatsAppMessage(orderData, orderNo, pdfUploadUrl);
                    lastWhatsAppMessage = waMessage;

                    // Format Customer Mobile for WhatsApp
                    let rawPhone = orderData.customer.mobile.replace(/[^0-9]/g, '');
                    if (rawPhone.length === 10) {
                        rawPhone = '91' + rawPhone;
                    }

                    // Customer WhatsApp Template (NO PDF Invoice Link)
                    const customerMsg = `Hello *${orderData.customer.name}*, thank you for shopping with *KSR GURU JI*! 🛍️\n\n` +
                        `Your order *${orderNo}* has been successfully placed.\n` +
                        `Total Amount: *${formatINR(total)}*\n` +
                        `Payment Method: *${orderData.paymentMethod.toUpperCase()}*\n\n` +
                        `We are preparing your order and it will be delivered within 45 minutes! Thank you.`;

                    // Set Customer Button href
                    waCustomerBtn.href = `https://wa.me/${rawPhone}?text=${encodeURIComponent(customerMsg)}`;

                    // ── Send WhatsApp to both delivery boys ──
                    const waNumbers = WHATSAPP_NUMBERS;
                    waNumbers.forEach(num => {
                        const url = `https://wa.me/${num}?text=${encodeURIComponent(waMessage)}`;
                        window.open(url, '_blank');
                    });
                    showToast('📱 Opening WhatsApp for delivery boys...', 'info', 4000);

                    // ── Clear client-side carts ──
                    clearAllCarts();

                    // ── Update UI ──
                    unifiedItems = [];
                    subtotal = 0;
                    updateSummary();
                    renderPaymentDetail(selectedMethod);

                    // ── Show success modal ──
                    orderIdDisplay.textContent = orderNo;
                    const trackBtn = document.getElementById('modalTrackBtn');
                    if (trackBtn) {
                        trackBtn.href = `../delivery/tracking.php?ref=${orderNo}`;
                    }
                    waPrimaryBtn.href = `https://wa.me/${WHATSAPP_NUMBERS[0]}?text=${encodeURIComponent(waMessage)}`;
                    waSecondaryBtn.href = `https://wa.me/${WHATSAPP_NUMBERS[1]}?text=${encodeURIComponent(waMessage)}`;
                    successModal.classList.add('show');

                    showToast(`✅ Order ${orderNo} placed successfully!`, 'success', 3000);

                } else {
                    // Show errors
                    const errors = result.errors || ['Something went wrong.'];
                    showToast('❌ ' + errors.join(' '), 'error');
                }
            } catch (err) {
                showToast('❌ Network error. Please try again.', 'error');
                console.error(err);
            }

            placeOrderBtn.disabled = false;
            placeOrderBtn.innerHTML = originalHTML;
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  REAL-TIME PAYMENT STATUS UI
        //  The server is the source of truth. The browser callback only
        //  accelerates the UX; confirmation is always re-read from
        //  api/payment_status.php.
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function showPaymentStatusBox() {
            const box = document.getElementById('paymentStatusBox');
            if (box) {
                box.style.display = 'block';
            }
        }

        function hidePaymentStatusBox() {
            const box = document.getElementById('paymentStatusBox');
            if (box) {
                box.style.display = 'none';
            }
        }

        function updatePaymentStatusUI(data) {
            showPaymentStatusBox();

            const status = String(
                (data && data.payment && data.payment.status) || 'pending'
            ).toLowerCase();

            const icon = document.getElementById('paymentStatusIcon');
            const title = document.getElementById('paymentStatusTitle');
            const message = document.getElementById('paymentStatusMessage');
            const progress = document.getElementById('paymentProgress');
            const transaction = document.getElementById('paymentTransaction');
            const actions = document.getElementById('paymentStatusActions');

            const config = {
                initiated: {
                    icon: '🟡',
                    title: 'Payment Started',
                    message: 'Preparing secure payment...',
                    progress: '20%'
                },
                pending: {
                    icon: '⏳',
                    title: 'Payment Pending',
                    message: 'Waiting for payment confirmation...',
                    progress: '45%'
                },
                processing: {
                    icon: '🔄',
                    title: 'Verifying Payment',
                    message: 'Your payment is being verified securely...',
                    progress: '70%'
                },
                success: {
                    icon: '✅',
                    title: 'Payment Confirmed',
                    message: 'Payment received successfully.',
                    progress: '100%'
                },
                failed: {
                    icon: '❌',
                    title: 'Payment Failed',
                    message: 'Payment was not completed.',
                    progress: '100%'
                },
                cancelled: {
                    icon: '🚫',
                    title: 'Payment Cancelled',
                    message: 'The payment was cancelled.',
                    progress: '100%'
                },
                expired: {
                    icon: '⌛',
                    title: 'Payment Expired',
                    message: 'The payment session has expired.',
                    progress: '100%'
                },
                refund_pending: {
                    icon: '↩️',
                    title: 'Refund Processing',
                    message: 'Your refund is being processed.',
                    progress: '80%'
                },
                refunded: {
                    icon: '💸',
                    title: 'Refund Completed',
                    message: 'Your payment has been refunded.',
                    progress: '100%'
                },
                cod_pending: {
                    icon: '💵',
                    title: 'Cash on Delivery',
                    message: 'Cash will be collected when your order arrives.',
                    progress: '60%'
                },
                cod_collected: {
                    icon: '✅',
                    title: 'Cash Collected',
                    message: 'Payment collected. Thank you!',
                    progress: '100%'
                }
            };

            const current = config[status] || config.pending;

            if (icon) icon.textContent = current.icon;
            if (title) title.textContent = current.title;
            if (message) message.textContent = data.message || current.message;
            if (progress) progress.style.width = current.progress;

            if (transaction) {
                transaction.textContent = (data.payment && data.payment.gateway_payment_id) ?
                    `Payment ID: ${data.payment.gateway_payment_id}` :
                    '';
            }

            if (actions) {
                if (status === 'success' || status === 'cod_pending' || status === 'cod_collected') {
                    const orderNumber = (data.order && data.order.number) || '';
                    actions.innerHTML = orderNumber ?
                        `<a href="../payment/status.php?order=${encodeURIComponent(orderNumber)}" target="_blank"
                               style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-weight:600;font-size:0.85rem;color:#2563eb;">
                               <i class="fas fa-receipt"></i> View payment receipt
                           </a>` :
                        '';
                } else {
                    actions.innerHTML = '';
                }
            }
        }

        /**
         * Poll the server until the payment reaches a terminal state.
         * This is what makes the checkout safe: we do NOT trust the
         * browser's Razorpay handler response on its own.
         */
        async function waitForPaymentConfirmation(orderId) {
            const maxAttempts = 60; // ~2 minutes at 2s intervals

            for (let attempt = 0; attempt < maxAttempts; attempt++) {
                try {
                    const response = await fetch(
                        `../api/payment_status.php?order_id=${encodeURIComponent(orderId)}`, {
                            method: 'GET',
                            cache: 'no-store',
                            credentials: 'same-origin'
                        }
                    );

                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.error || 'Unable to check payment status.');
                    }

                    updatePaymentStatusUI(data);

                    const status = data.payment.status;

                    if (status === 'success') {
                        return data;
                    }

                    if (status === 'failed' || status === 'cancelled' || status === 'expired') {
                        throw new Error(data.message || 'Payment was not successful.');
                    }

                    // A sibling COD/anomalous state should end the wait too.
                    if (status === 'cod_pending' || status === 'cod_collected') {
                        return data;
                    }

                } catch (error) {
                    if (attempt === maxAttempts - 1) {
                        throw error;
                    }
                }

                await new Promise(resolve => setTimeout(resolve, 2000));
            }

            throw new Error('Payment confirmation is taking longer than expected.');
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  RAZORPAY INTEGRATION & SIMULATOR
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // Server tells us whether production mode is active. In production
        // the simulated payment path is refused client-side as well as
        // server-side (RazorpayHelper::simulationAllowed() fails closed).
        const KSR_PRODUCTION = <?php echo RazorpayHelper::isProduction() ? 'true' : 'false'; ?>;

        function initiateRazorpayCheckout(result, orderData) {
            return new Promise((resolve, reject) => {
                // A simulated order must never resolve in production, even
                // if a mock order id somehow reaches the browser.
                const looksMock = String(result.razorpay_order_id || '').startsWith('rzp_order_mock_');

                if (KSR_PRODUCTION && (looksMock || typeof Razorpay === 'undefined')) {
                    reject('Online payment is not available right now. Please try again later.');
                    return;
                }

                const isMock = looksMock || typeof Razorpay === 'undefined';

                if (isMock) {
                    // Inject and display custom premium sandbox/simulation overlay modal
                    const modalHtml = `
                        <div id="razorpaySimModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(10px); z-index: 10000; animation: fadeIn 0.25s ease-out;">
                            <div style="background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(255, 255, 255, 0.25); border-radius: 24px; width: 90%; max-width: 440px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; font-family: 'Inter', system-ui, -apple-system, sans-serif; transition: all 0.3s ease;">
                                <div style="background: #2b6cb0; padding: 22px; color: white; display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <i class="fab fa-cc-amazon-pay" style="font-size: 1.9rem; color: #fff;"></i>
                                        <span style="font-weight: 800; font-size: 1.15rem; letter-spacing: 0.5px;">Razorpay Gateway</span>
                                    </div>
                                    <span style="background: rgba(255, 255, 255, 0.22); font-size: 0.7rem; padding: 4px 10px; border-radius: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Sandbox</span>
                                </div>
                                <div style="padding: 28px 24px; text-align: center;">
                                    <h3 style="font-size: 1.35rem; font-weight: 800; margin: 0 0 6px 0; color: #000;">KSR GURU JI</h3>
                                    <p style="color: #64748b; font-size: 0.85rem; margin: 0 0 22px 0; font-weight: 500;">Checkout Reference: ${result.order_number}</p>
                                    <div style="font-weight: 800; font-size: 2.2rem; color: #0f172a; margin-bottom: 28px; letter-spacing: -0.5px;">₹${(result.amount / 100).toFixed(2)}</div>
                                    
                                    <div style="display:flex; flex-direction:column; gap:12px; margin-bottom: 10px;">
                                        <button id="rzpSimSuccess" style="background:#10b981; color:white; border:none; padding:14px; border-radius:14px; font-weight:700; font-size:0.95rem; cursor:pointer; width:100%; transition:all 0.2s ease; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 10px 15px -3px rgba(16,185,129,0.3);">
                                            <i class="fas fa-check-circle"></i> Complete Payment (Simulate Success)
                                        </button>
                                        <button id="rzpSimFail" style="background:#ef4444; color:white; border:none; padding:14px; border-radius:14px; font-weight:700; font-size:0.95rem; cursor:pointer; width:100%; transition:all 0.2s ease; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 10px 15px -3px rgba(239,68,68,0.3);">
                                            <i class="fas fa-times-circle"></i> Cancel Payment (Simulate Failure)
                                        </button>
                                    </div>
                                </div>
                                <div style="background:#f8fafc; padding: 16px; text-align:center; border-top:1px solid #f1f5f9;">
                                    <button id="rzpSimCancel" style="background:none; border:none; color:#94a3b8; font-weight:600; font-size:0.85rem; cursor:pointer; transition:color 0.2s;">
                                        Dismiss Window
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.insertAdjacentHTML('beforeend', modalHtml);

                    const simModal = document.getElementById('razorpaySimModal');
                    const successBtn = document.getElementById('rzpSimSuccess');
                    const failBtn = document.getElementById('rzpSimFail');
                    const cancelBtn = document.getElementById('rzpSimCancel');

                    const cleanup = () => {
                        if (simModal) {
                            simModal.style.opacity = '0';
                            setTimeout(() => simModal.remove(), 250);
                        }
                    };

                    successBtn.onclick = async () => {
                        successBtn.disabled = true;
                        successBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing Secure Signature...';

                        const paymentId = 'pay_mock_' + Math.random().toString(36).substring(2, 10);
                        const signature = 'sig_mock_' + Math.random().toString(36).substring(2, 15);

                        const verifyData = new FormData();
                        verifyData.append('action', 'verify_razorpay_payment');
                        verifyData.append('order_id', result.order_id);
                        verifyData.append('razorpay_payment_id', paymentId);
                        verifyData.append('razorpay_order_id', result.razorpay_order_id);
                        verifyData.append('razorpay_signature', signature);

                        try {
                            const verifyResponse = await fetch(window.location.href, {
                                method: 'POST',
                                body: verifyData
                            });
                            const verifyResult = await verifyResponse.json();
                            cleanup();
                            if (verifyResult.success) {
                                resolve({
                                    razorpay_payment_id: paymentId,
                                    razorpay_order_id: result.razorpay_order_id,
                                    razorpay_signature: signature
                                });
                            } else {
                                reject(verifyResult.error || 'Verification of simulated signature failed.');
                            }
                        } catch (err) {
                            cleanup();
                            reject('Network error during simulated verification.');
                        }
                    };

                    failBtn.onclick = () => {
                        cleanup();
                        reject('Simulated payment failure option clicked.');
                    };

                    cancelBtn.onclick = () => {
                        cleanup();
                        reject('Transaction cancelled by user.');
                    };
                } else {
                    // Standard Real Razorpay checkout flow
                    const options = {
                        key: result.razorpay_key_id,
                        amount: result.amount,
                        currency: "INR",
                        name: "KSR GURU JI",
                        description: "Order #" + result.order_number,
                        order_id: result.razorpay_order_id,
                        handler: async function(response) {
                            const verifyData = new FormData();
                            verifyData.append('action', 'verify_razorpay_payment');
                            verifyData.append('order_id', result.order_id);
                            verifyData.append('razorpay_payment_id', response.razorpay_payment_id);
                            verifyData.append('razorpay_order_id', response.razorpay_order_id);
                            verifyData.append('razorpay_signature', response.razorpay_signature);

                            try {
                                const verifyResponse = await fetch(window.location.href, {
                                    method: 'POST',
                                    body: verifyData
                                });
                                const verifyResult = await verifyResponse.json();
                                if (verifyResult.success) {
                                    resolve(response);
                                } else {
                                    reject(verifyResult.error || 'Signature verification failed.');
                                }
                            } catch (err) {
                                reject('Network error during validation.');
                            }
                        },
                        prefill: {
                            name: result.customer_name,
                            email: result.customer_email || 'customer@ksrguruji.com',
                            contact: result.customer_phone
                        },
                        theme: {
                            color: "#3b82f6"
                        },
                        modal: {
                            ondismiss: function() {
                                reject('Payment window closed by user.');
                            }
                        }
                    };
                    const rzp = new Razorpay(options);
                    rzp.open();
                }
            });
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  RESET
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function resetCheckout() {
            document.querySelectorAll('.form-group input, .form-group textarea').forEach(el => {
                el.value = '';
                el.classList.remove('error');
            });
            document.querySelectorAll('.error-text').forEach(el => el.classList.remove('show'));
            userLocation = {
                lat: null,
                lng: null,
                address: ''
            };
            document.getElementById('locationStatus').textContent = '';
            document.getElementById('orderNotes').value = '';
            updateSummary();
            renderPaymentDetail(selectedMethod);
            showToast('🔄 Form reset. Cart items unchanged.', 'info', 2000);
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  TOAST
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function showToast(message, type = 'info', duration = 3500) {
            toast.className = 'toast ' + type;
            toastMsg.textContent = message;
            const icon = toast.querySelector('i');
            icon.className = type === 'success' ? 'fas fa-check-circle' :
                type === 'error' ? 'fas fa-exclamation-circle' :
                'fas fa-info-circle';
            toast.classList.add('show');
            clearTimeout(toast._hideTimer);
            toast._hideTimer = setTimeout(() => toast.classList.remove('show'), duration);
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  DOWNLOAD INVOICE
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        function downloadInvoice() {
            if (!lastGeneratedOrder) {
                showToast('No invoice available. Place an order first.', 'error');
                return;
            }
            const {
                pdfResult,
                orderNo
            } = lastGeneratedOrder;
            const link = document.createElement('a');
            link.href = pdfResult.url;
            link.download = `Invoice_${orderNo}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showToast('📄 Invoice downloaded!', 'success', 2000);
        }

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  EVENT LISTENERS
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        // Geolocation
        document.getElementById('locationBtn').addEventListener('click', () => {
            const btn = document.getElementById('locationBtn');
            const status = document.getElementById('locationStatus');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Detecting...';
            btn.disabled = true;
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(async (pos) => {
                    userLocation.lat = pos.coords.latitude;
                    userLocation.lng = pos.coords.longitude;
                    status.textContent = `📍 ${userLocation.lat.toFixed(4)}, ${userLocation.lng.toFixed(4)}`;
                    try {
                        const res = await fetch(
                            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${userLocation.lat}&lon=${userLocation.lng}&zoom=18&addressdetails=1`
                        );
                        const data = await res.json();
                        if (data.display_name) {
                            document.getElementById('address').value = data.display_name;
                            userLocation.address = data.display_name;
                            document.getElementById('address').classList.remove('error');
                            document.getElementById('addressError').classList.remove('show');
                        }
                    } catch (e) {}
                    btn.innerHTML = original;
                    btn.disabled = false;
                }, (err) => {
                    status.textContent = '⚠️ Location access denied. Please fill address manually.';
                    btn.innerHTML = original;
                    btn.disabled = false;
                });
            } else {
                status.textContent = '⚠️ Geolocation not supported.';
                btn.innerHTML = original;
                btn.disabled = false;
            }
        });

        // Payment selection
        paymentOptions.forEach(el => {
            el.addEventListener('click', () => {
                const method = el.dataset.method;
                selectPayment(method, el);
            });
        });

        // Place Order
        placeOrderBtn.addEventListener('click', placeOrder);

        // Reset
        resetBtn.addEventListener('click', resetCheckout);

        // Modal actions
        modalContinueBtn.addEventListener('click', () => {
            successModal.classList.remove('show');
            window.location.href = '../index.php';
        });

        if (downloadInvoiceBtn) {
            downloadInvoiceBtn.addEventListener('click', downloadInvoice);
        }

        successModal.addEventListener('click', (e) => {
            if (e.target === successModal) successModal.classList.remove('show');
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.closest('.form-group')) {
                e.preventDefault();
                placeOrder();
            }
        });

        toast.addEventListener('click', () => toast.classList.remove('show'));

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        //  INIT
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        (function init() {
            // Theme toggle init
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const htmlTag = document.documentElement;

            const savedTheme = localStorage.getItem('theme') || 'light';
            htmlTag.setAttribute('data-theme', savedTheme);
            if (themeIcon) {
                themeIcon.innerText = savedTheme === 'dark' ? '☀️' : '🌙';
            }

            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const currentTheme = htmlTag.getAttribute('data-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    htmlTag.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    if (themeIcon) {
                        themeIcon.innerText = newTheme === 'dark' ? '☀️' : '🌙';
                    }
                });
            }

            loadUnifiedCart();
            updateSummary();

            // Check for passed payment method from suggestion pages
            let initialMethod = 'qr';
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('method')) {
                initialMethod = urlParams.get('method');
            } else {
                const savedMethod = localStorage.getItem('selectedPaymentMethod');
                if (savedMethod) {
                    initialMethod = savedMethod;
                    localStorage.removeItem('selectedPaymentMethod'); // consume it
                }
            }

            const defaultEl = document.querySelector(`.payment-option[data-method="${initialMethod}"]`);
            if (defaultEl) {
                selectPayment(initialMethod, defaultEl);
            } else {
                const fallbackEl = document.querySelector('.payment-option[data-method="qr"]');
                if (fallbackEl) selectPayment('qr', fallbackEl);
            }

            if (unifiedItems.length === 0) placeOrderBtn.disabled = true;

        })();
    </script>
</body>

</html>