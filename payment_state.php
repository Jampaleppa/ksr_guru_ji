<?php
// ============================================================
// api/payment_state.php - Centralized Payment State Machine
// ============================================================
//
// Single source of truth for the KSR GURU JI payment lifecycle.
//
// Payment states:
//   initiated -> pending -> processing -> success
//                                     -> failed
//                                     -> cancelled
//                                     -> expired
//   success  -> refund_pending -> refunded / refund_failed
//   cod_pending -> cod_collected -> success
//
// Everything here is pure / idempotent and safe to include
// from any endpoint (checkout, webhook, cron, admin).
// ============================================================

declare(strict_types=1);

if (!function_exists('ksrPaymentStates')) {

    /**
     * Full ordered list of payment lifecycle states.
     */
    function ksrPaymentStates(): array
    {
        return [
            'initiated',
            'pending',
            'processing',
            'success',
            'failed',
            'cancelled',
            'expired',
            'refund_pending',
            'refunded',
            'refund_failed',
            'cod_pending',
            'cod_collected',
        ];
    }

    /**
     * Terminal states never transition forward (except refund flows).
     */
    function paymentStatusIsTerminal(string $status): bool
    {
        return in_array(
            strtolower($status),
            ['success', 'failed', 'cancelled', 'expired', 'refunded'],
            true
        );
    }

    /**
     * A "settled" state means the customer's money outcome is final
     * and no amount is outstanding.
     */
    function paymentStatusIsSettled(string $status): bool
    {
        return in_array(
            strtolower($status),
            ['success', 'refunded', 'cod_collected'],
            true
        );
    }

    /**
     * Map an order-level payment_status value to a payment record status.
     * Keeps legacy 'paid' rows working.
     */
    function ksrNormalizePaymentStatus(?string $status): string
    {
        $status = strtolower(trim((string)$status));

        if ($status === '') {
            return 'pending';
        }

        if ($status === 'paid' || $status === 'completed' || $status === 'captured') {
            return 'success';
        }

        if ($status === 'cancelled' || $status === 'canceled') {
            return 'cancelled';
        }

        if ($status === 'refund') {
            return 'refunded';
        }

        return $status;
    }

    /**
     * The transition table. Returns true when $new is reachable from $old.
     * Identical old/new is always allowed (idempotent no-op).
     */
    function paymentStatusCanTransition(string $old, string $new): bool
    {
        $old = ksrNormalizePaymentStatus($old);
        $new = ksrNormalizePaymentStatus($new);

        if ($old === $new) {
            return true;
        }

        $allowed = [
            'initiated' => ['pending', 'processing', 'cancelled', 'expired', 'failed'],

            'pending' => ['processing', 'success', 'failed', 'cancelled', 'expired'],

            'processing' => ['success', 'failed', 'cancelled', 'expired'],

            'success' => ['refund_pending'],

            'refund_pending' => ['refunded', 'refund_failed'],

            'failed' => [],

            'cancelled' => [],

            'expired' => [],

            'refunded' => [],

            'refund_failed' => ['refund_pending', 'refunded'],

            'cod_pending' => ['cod_collected', 'cancelled', 'failed'],

            'cod_collected' => ['success', 'refund_pending'],
        ];

        return in_array($new, $allowed[$old] ?? [], true);
    }

    /**
     * Which payment state should the *order* status become for a given
     * payment state? Returns null when the order status should not move.
     */
    function ksrOrderStatusForPayment(string $paymentStatus): ?string
    {
        return match (strtolower($paymentStatus)) {
            'success', 'cod_collected' => 'processing',
            'refunded'                => 'returned',
            'cancelled'               => 'cancelled',
            default                   => null,
        };
    }

    /**
     * Map an order-level payment_status value (what `orders.payment_status`
     * stores) onto the payment lifecycle vocabulary.
     */
    function ksrOrderPaymentStatusValue(string $paymentStatus): string
    {
        return match (strtolower($paymentStatus)) {
            'initiated'      => 'initiated',
            'processing'     => 'processing',
            'success'        => 'paid',
            'cod_collected'  => 'cod_collected',
            'cod_pending'    => 'cod_pending',
            'refund_pending' => 'refund_pending',
            'refunded'       => 'refunded',
            'refund_failed'  => 'refund_failed',
            'cancelled'      => 'cancelled',
            'expired'        => 'expired',
            'failed'         => 'failed',
            default          => 'pending',
        };
    }

    /**
     * Append a row to the immutable payment_events audit trail.
     *
     * Never throws for a logging failure — a broken audit row must not
     * roll back the business transaction that is being recorded.
     */
    function recordPaymentEvent(
        PDO $pdo,
        ?int $paymentId,
        int $orderId,
        string $eventType,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?string $gatewayEventId = null,
        ?string $gatewayPaymentId = null,
        ?array $payload = null,
        string $source = 'system'
    ): void {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payment_events
                (
                    payment_id,
                    order_id,
                    event_type,
                    old_status,
                    new_status,
                    gateway_event_id,
                    gateway_payment_id,
                    payload,
                    source
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $paymentId,
                $orderId,
                $eventType,
                $oldStatus,
                $newStatus,
                $gatewayEventId,
                $gatewayPaymentId,
                $payload
                    ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : null,
                $source,
            ]);
        } catch (Throwable $e) {
            error_log('recordPaymentEvent failed: ' . $e->getMessage());
        }
    }

    /**
     * Has this gateway event already been consumed? Used for webhook
     * idempotency (Razorpay retries the same event id many times).
     */
    function paymentGatewayEventSeen(PDO $pdo, ?string $gatewayEventId): bool
    {
        if ($gatewayEventId === null || $gatewayEventId === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT 1 FROM payment_events WHERE gateway_event_id = ? LIMIT 1"
            );
            $stmt->execute([$gatewayEventId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('paymentGatewayEventSeen failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Has this Razorpay payment id already produced a payment record?
     * Used for idempotency on the browser verification callback.
     * Returns the existing row or null.
     */
    function paymentFindByGatewayPaymentId(PDO $pdo, string $gatewayPaymentId): ?array
    {
        if ($gatewayPaymentId === '') {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM payments
                 WHERE gateway_payment_id = ? OR transaction_id = ?
                 ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute([$gatewayPaymentId, $gatewayPaymentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('paymentFindByGatewayPaymentId failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Apply a state transition to a payment record + keep the parent order
     * in sync, atomically, inside the caller's transaction.
     *
     * Returns true when the state actually changed (or was already target).
     * Returns false when the transition is illegal (caller should NOT
     * treat the payment as confirmed).
     */
    function applyPaymentStatus(
        PDO $pdo,
        int $paymentId,
        string $newStatus,
        array $context = []
    ): bool {
        $newStatus = ksrNormalizePaymentStatus($newStatus);

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            return false;
        }

        $oldStatus = ksrNormalizePaymentStatus($payment['status']);

        if (!paymentStatusCanTransition($oldStatus, $newStatus)) {
            error_log(
                "applyPaymentStatus: illegal transition {$oldStatus} -> {$newStatus} " .
                    "for payment #{$paymentId}"
            );
            return false;
        }

        $orderId = (int)$payment['order_id'];

        // Build the SET clause with lifecycle timestamps.
        $sets   = ['status = ?'];
        $params = [$newStatus];

        switch ($newStatus) {
            case 'success':
                $sets[]  = 'paid_at = COALESCE(paid_at, NOW())';
                $sets[]  = 'confirmed_at = COALESCE(confirmed_at, NOW())';
                break;
            case 'processing':
                if ($oldStatus === 'initiated') {
                    $sets[] = 'confirmed_at = COALESCE(confirmed_at, NOW())';
                }
                break;
            case 'failed':
                $sets[] = 'failed_at = NOW()';
                break;
            case 'cancelled':
                $sets[] = 'cancelled_at = NOW()';
                break;
            case 'expired':
                $sets[] = 'expired_at = NOW()';
                break;
            case 'refund_pending':
                $sets[]  = "refund_status = 'pending'";
                break;
            case 'refunded':
                $sets[]  = "refund_status = 'processed'";
                $sets[]  = 'refunded_at = NOW()';
                break;
            case 'refund_failed':
                $sets[]  = "refund_status = 'failed'";
                break;
            case 'cod_collected':
                if (!empty($context['collected_amount'])) {
                    $sets[]   = 'collected_amount = ?';
                    $params[] = $context['collected_amount'];
                }
                if (!empty($context['collected_by'])) {
                    $sets[]   = 'collected_by = ?';
                    $params[] = $context['collected_by'];
                }
                $sets[]  = 'collected_at = NOW()';
                if (!empty($context['collection_reference'])) {
                    $sets[]   = 'collection_reference = ?';
                    $params[] = $context['collection_reference'];
                }
                $sets[] = 'paid_at = COALESCE(paid_at, NOW())';
                $sets[] = 'confirmed_at = COALESCE(confirmed_at, NOW())';
                break;
        }

        // Optional gateway / failure fields passed by caller.
        foreach (
            [
                'gateway'            => 'gateway',
                'gateway_order_id'   => 'gateway_order_id',
                'gateway_payment_id' => 'gateway_payment_id',
                'gateway_signature'  => 'gateway_signature',
                'transaction_id'     => 'transaction_id',
                'failure_code'       => 'failure_code',
                'failure_reason'     => 'failure_reason',
            ] as $key => $column
        ) {
            if (isset($context[$key]) && $context[$key] !== null && $context[$key] !== '') {
                $sets[]   = "{$column} = ?";
                $params[] = $context[$key];
            }
        }

        if (!empty($context['gateway_response'])) {
            $sets[]   = 'gateway_response = ?';
            $params[] = is_string($context['gateway_response'])
                ? $context['gateway_response']
                : json_encode($context['gateway_response'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (!empty($context['webhook_received'])) {
            $sets[] = 'webhook_received_at = NOW()';
        }

        $params[] = $paymentId;

        $sql = "UPDATE payments SET " . implode(', ', $sets) . " WHERE id = ?";
        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        // Keep the parent order in sync.
        $orderPaymentStatus = ksrOrderPaymentStatusValue($newStatus);
        $orderStatus        = ksrOrderStatusForPayment($newStatus);

        if ($orderStatus !== null) {
            $ostmt = $pdo->prepare(
                "UPDATE orders SET payment_status = ?, status = ? WHERE id = ?"
            );
            $ostmt->execute([$orderPaymentStatus, $orderStatus, $orderId]);
        } else {
            $ostmt = $pdo->prepare(
                "UPDATE orders SET payment_status = ? WHERE id = ?"
            );
            $ostmt->execute([$orderPaymentStatus, $orderId]);
        }

        // Record the audit event.
        recordPaymentEvent(
            $pdo,
            $paymentId,
            $orderId,
            $context['event_type'] ?? 'PAYMENT_' . strtoupper($newStatus),
            $oldStatus,
            $newStatus,
            $context['gateway_event_id'] ?? null,
            $context['gateway_payment_id'] ?? null,
            $context['payload'] ?? null,
            $context['source'] ?? 'system'
        );

        return true;
    }

    /**
     * Convenience: fetch the payment row for an order (most recent).
     */
    function paymentRowForOrder(PDO $pdo, int $orderId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT * FROM payments WHERE order_id = ? ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Friendly, human-facing copy for a given payment status.
     */
    function ksrPaymentStatusMessage(string $status): string
    {
        return match (ksrNormalizePaymentStatus($status)) {
            'initiated'      => 'Payment has been initiated.',
            'pending'        => 'Payment confirmation is pending.',
            'processing'     => 'Payment is being verified.',
            'success'        => 'Payment confirmed successfully.',
            'failed'         => 'Payment failed.',
            'cancelled'      => 'Payment was cancelled.',
            'expired'        => 'Payment session expired.',
            'refund_pending' => 'Refund is being processed.',
            'refunded'       => 'Payment has been refunded.',
            'refund_failed'  => 'Refund could not be completed. Our team will contact you.',
            'cod_pending'    => 'Cash will be collected on delivery.',
            'cod_collected'  => 'Cash has been collected.',
            default          => 'Payment status is being updated.',
        };
    }
}
