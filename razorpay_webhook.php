<?php
// ============================================================
// api/razorpay_webhook.php - Razorpay Webhook (server authority)
// ============================================================
//
// The webhook is the FINAL server-side authority for payment truth.
// The browser callback is only for immediate UX.
//
// Flow:
//   Razorpay -> verify signature -> find gateway order/payment
//            -> has payment_events seen this event? return 200
//            -> update payment (state machine) -> update order
//            -> insert event -> return 200
//
// Event mapping:
//   payment.authorized -> processing
//   payment.captured   -> success
//   payment.failed     -> failed
//   refund.created     -> refund_pending
//   refund.processed   -> refunded
//   refund.failed      -> refund_failed
// ============================================================

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/razorpay_helper.php';
require_once __DIR__ . '/payment_state.php';

/**
 * Always acknowledge with 200 so Razorpay does not retry forever,
 * except on genuine internal failure (500 -> a retry is wanted).
 */
function webhookRespond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
$rawBody   = file_get_contents('php://input');

if (empty($signature)) {
    webhookRespond(400, ['success' => false, 'error' => 'Missing signature header.']);
}

if (!RazorpayHelper::verifyWebhookSignature($rawBody, $signature)) {
    error_log('Razorpay webhook: signature verification failed.');
    webhookRespond(401, ['success' => false, 'error' => 'Invalid signature.']);
}

$data = json_decode($rawBody, true);
if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
    webhookRespond(400, ['success' => false, 'error' => 'Invalid JSON payload.']);
}

$event = (string)($data['event'] ?? '');

// Razorpay supplies a unique event id ("evt_...") in newer payloads.
// Fall back to a deterministic hash so retries are still deduped.
$gatewayEventId = (string)($data['id'] ?? '');
if ($gatewayEventId === '') {
    $gatewayEventId = 'sha_' . substr(hash('sha256', $rawBody), 0, 40);
}

error_log("Razorpay webhook event received: {$event} ({$gatewayEventId})");

// --- Extract entity + identifiers per event family ----------------------
$paymentEntity = $data['payload']['payment']['entity'] ?? null;
$refundEntity  = $data['payload']['refund']['entity'] ?? null;
$orderEntity   = $data['payload']['order']['entity'] ?? null;

$razorpayOrderId   = '';
$razorpayPaymentId = '';
$receipt           = '';

if (is_array($paymentEntity)) {
    $razorpayPaymentId = (string)($paymentEntity['id'] ?? '');
    $razorpayOrderId   = (string)($paymentEntity['order_id'] ?? '');
}
if (is_array($refundEntity)) {
    $razorpayPaymentId = $razorpayPaymentId ?: (string)($refundEntity['payment_id'] ?? '');
}
if (is_array($orderEntity)) {
    $razorpayOrderId = $razorpayOrderId ?: (string)($orderEntity['id'] ?? '');
    $receipt         = (string)($orderEntity['receipt'] ?? '');
}

if ($razorpayOrderId === '' && $razorpayPaymentId === '' && $receipt === '') {
    webhookRespond(200, ['success' => true, 'message' => 'No order references in payload.']);
}

// Determine the target payment state for this event.
$targetStatus = match ($event) {
    'payment.authorized' => 'processing',
    'payment.captured',
    'order.paid'         => 'success',
    'payment.failed'     => 'failed',
    'refund.created'     => 'refund_pending',
    'refund.processed'   => 'refunded',
    'refund.failed'      => 'refund_failed',
    default              => null,
};

if ($targetStatus === null) {
    webhookRespond(200, ['success' => true, 'message' => "Event '{$event}' acknowledged (no action)."]);
}

try {
    $pdo = getDBConnection();

    // --- Idempotency: has this exact gateway event been processed? ------
    if (paymentGatewayEventSeen($pdo, $gatewayEventId)) {
        webhookRespond(200, ['success' => true, 'message' => 'Duplicate event ignored.']);
    }

    $pdo->beginTransaction();

    // --- Locate the KSR order + payment record --------------------------
    // Priority: gateway_order_id match, then payment id, then receipt.
    $order = null;

    if ($razorpayOrderId !== '') {
        $stmt = $pdo->prepare("
            SELECT o.* FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE p.gateway_order_id = ? OR o.transaction_id = ?
            LIMIT 1
        ");
        $stmt->execute([$razorpayOrderId, $razorpayOrderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$order && $razorpayPaymentId !== '') {
        $stmt = $pdo->prepare("
            SELECT o.* FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE p.gateway_payment_id = ? OR p.transaction_id = ?
            LIMIT 1
        ");
        $stmt->execute([$razorpayPaymentId, $razorpayPaymentId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$order && $receipt !== '') {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
        $stmt->execute([$receipt]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$order) {
        $pdo->rollBack();
        error_log("Webhook: no KSR order for gateway order={$razorpayOrderId} payment={$razorpayPaymentId}");
        webhookRespond(200, ['success' => true, 'message' => 'Order not found; acknowledged.']);
    }

    $orderId = (int)$order['id'];
    $payment = paymentRowForOrder($pdo, $orderId);

    if (!$payment) {
        $pdo->rollBack();
        error_log("Webhook: no payments row for order #{$orderId}");
        webhookRespond(200, ['success' => true, 'message' => 'Payment record missing; acknowledged.']);
    }

    $paymentId     = (int)$payment['id'];
    $currentStatus = ksrNormalizePaymentStatus($payment['status']);

    // --- Amount reconciliation on success -------------------------------
    if ($targetStatus === 'success' && is_array($paymentEntity) && isset($paymentEntity['amount'])) {
        $gatewayAmount  = ((int)$paymentEntity['amount']) / 100;
        $expectedAmount = (float)$payment['amount'];

        if (abs($gatewayAmount - $expectedAmount) > 1.00) {
            error_log(
                "Webhook AMOUNT MISMATCH order #{$orderId}: gateway={$gatewayAmount} expected={$expectedAmount}"
            );

            applyPaymentStatus($pdo, $paymentId, 'failed', [
                'event_type'         => 'PAYMENT_AMOUNT_MISMATCH',
                'failure_code'       => 'AMOUNT_MISMATCH',
                'failure_reason'     => "Gateway amount {$gatewayAmount} does not match order amount {$expectedAmount}.",
                'gateway_event_id'   => $gatewayEventId,
                'gateway_payment_id' => $razorpayPaymentId,
                'payload'            => $paymentEntity,
                'source'             => 'webhook',
                'webhook_received'   => true,
            ]);

            $pdo->commit();
            webhookRespond(200, ['success' => true, 'message' => 'Amount mismatch recorded as failed.']);
        }
    }

    // --- Build the context for the state machine ------------------------
    $context = [
        'event_type'         => 'RAZORPAY_' . strtoupper(str_replace('.', '_', $event)),
        'gateway_event_id'   => $gatewayEventId,
        'gateway_payment_id' => $razorpayPaymentId ?: null,
        'payload'            => $paymentEntity ?? $refundEntity ?? $orderEntity ?? $data,
        'source'             => 'webhook',
        'webhook_received'   => true,
        'gateway'            => 'razorpay',
    ];

    if ($razorpayOrderId !== '') {
        $context['gateway_order_id'] = $razorpayOrderId;
    }

    if ($targetStatus === 'success' && is_array($paymentEntity)) {
        $context['transaction_id']   = $razorpayPaymentId;
        $context['gateway_response'] = $paymentEntity;
    }

    if ($targetStatus === 'failed' && is_array($paymentEntity)) {
        $context['failure_code']   = (string)($paymentEntity['error_code'] ?? 'PAYMENT_FAILED');
        $context['failure_reason'] = (string)($paymentEntity['error_description'] ?? 'Payment failed at gateway.');
    }

    if (in_array($targetStatus, ['refund_pending', 'refunded', 'refund_failed'], true) && is_array($refundEntity)) {
        $refundId = (string)($refundEntity['id'] ?? '');
        if ($refundId !== '') {
            $context['refund_transaction_id'] = $refundId;
        }

        // Update / create the refunds ledger row.
        $refundAmount = isset($refundEntity['amount']) ? ((int)$refundEntity['amount']) / 100 : null;

        $rStmt = $pdo->prepare("SELECT id FROM refunds WHERE refund_id = ? OR payment_id = ? ORDER BY id DESC LIMIT 1");
        $rStmt->execute([$refundId, $paymentId]);
        $existingRefundId = $rStmt->fetchColumn();

        $refundStatus = match ($targetStatus) {
            'refunded'      => 'processed',
            'refund_failed' => 'failed',
            default         => 'pending',
        };

        if ($existingRefundId) {
            $u = $pdo->prepare("
                UPDATE refunds
                SET status = ?, refund_id = COALESCE(NULLIF(?, ''), refund_id),
                    amount = COALESCE(?, amount),
                    gateway_response = ?,
                    processed_at = CASE WHEN ? = 'processed' THEN NOW() ELSE processed_at END
                WHERE id = ?
            ");
            $u->execute([
                $refundStatus,
                $refundId,
                $refundAmount,
                json_encode($refundEntity, JSON_UNESCAPED_SLASHES),
                $refundStatus,
                $existingRefundId,
            ]);
        } elseif ($refundAmount !== null) {
            $i = $pdo->prepare("
                INSERT INTO refunds (order_id, payment_id, refund_id, amount, status, reason, gateway_response, created_at, processed_at)
                VALUES (?, ?, ?, ?, ?, 'Gateway webhook', ?, NOW(), ?)
            ");
            $i->execute([
                $orderId,
                $paymentId,
                $refundId ?: null,
                $refundAmount,
                $refundStatus,
                json_encode($refundEntity, JSON_UNESCAPED_SLASHES),
                $refundStatus === 'processed' ? date('Y-m-d H:i:s') : null,
            ]);
        }
    }

    // --- Apply the transition atomically --------------------------------
    $changed = applyPaymentStatus($pdo, $paymentId, $targetStatus, $context);

    if (!$changed) {
        // Illegal transition — most commonly a duplicate/late event.
        // Record it for the audit trail but do NOT change status.
        recordPaymentEvent(
            $pdo,
            $paymentId,
            $orderId,
            'WEBHOOK_IGNORED_TRANSITION',
            $currentStatus,
            $targetStatus,
            $gatewayEventId,
            $razorpayPaymentId ?: null,
            ['event' => $event],
            'webhook'
        );

        $pdo->commit();
        webhookRespond(200, ['success' => true, 'message' => 'Transition not applicable; acknowledged.']);
    }

    // --- Mirror the authoritative event into order_timeline -------------
    $timelineNote = match ($targetStatus) {
        'success'        => 'Payment captured and confirmed via Razorpay webhook.',
        'processing'     => 'Payment authorized; awaiting capture.',
        'failed'         => 'Payment failed at Razorpay gateway.',
        'refund_pending' => 'Refund initiated via Razorpay.',
        'refunded'       => 'Refund processed successfully.',
        'refund_failed'  => 'Refund failed at Razorpay.',
        default          => 'Payment updated via Razorpay webhook.',
    };

    $tStmt = $pdo->prepare("
        INSERT INTO order_timeline (order_id, status, notes, created_by)
        VALUES (?, ?, ?, ?)
    ");
    $tStmt->execute([$orderId, $order['status'], $timelineNote, $order['user_id'] ?? null]);

    $pdo->commit();

    error_log("Webhook: order #{$orderId} payment #{$paymentId} -> {$targetStatus}");

    webhookRespond(200, [
        'success' => true,
        'message' => 'Payment lifecycle updated.',
        'data'    => ['order_id' => $orderId, 'status' => $targetStatus],
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Razorpay webhook exception: ' . $e->getMessage());
    // 500 -> Razorpay will retry, which is correct for transient DB errors.
    webhookRespond(500, ['success' => false, 'error' => 'Internal server error.']);
}
