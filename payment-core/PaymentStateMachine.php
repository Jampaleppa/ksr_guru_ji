<?php
declare(strict_types=1);

namespace KsrPay;

require_once __DIR__ . '/../payment_state.php';

/**
 * PaymentStateMachine — wraps payment_state.php transitions
 * and applies them to payment_transactions + orders.
 */
class PaymentStateMachine
{
    public function __construct(
        private \PDO         $pdo,
        private AuditLogger  $audit
    ) {}

    /**
     * Transition a payment to $newStatus.
     * Returns false if the transition is illegal.
     *
     * $context keys (all optional):
     *   actor_type, actor_id, notes, verification_source,
     *   verified_by_admin_id, failure_reason
     */
    public function transition(string $paymentId, string $newStatus, array $context = []): bool
    {
        $newStatus = ksrNormalizePaymentStatus($newStatus);

        // Load current row
        $stmt = $this->pdo->prepare(
            "SELECT * FROM payment_transactions WHERE payment_id = ? LIMIT 1"
        );
        $stmt->execute([$paymentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            error_log("PaymentStateMachine: payment_id '{$paymentId}' not found");
            return false;
        }

        $oldStatus = ksrNormalizePaymentStatus($row['status']);

        if (!paymentStatusCanTransition($oldStatus, $newStatus)) {
            error_log("PaymentStateMachine: illegal {$oldStatus} -> {$newStatus} for {$paymentId}");
            return false;
        }

        // Write audit FIRST — if it throws, the caller's transaction rolls back
        $this->audit->log(
            eventType:             'PAYMENT_' . strtoupper($newStatus),
            paymentTransactionId:  (int)$row['id'],
            orderId:               (int)$row['order_id'],
            oldStatus:             $oldStatus,
            newStatus:             $newStatus,
            actorType:             $context['actor_type'] ?? 'system',
            actorId:               $context['actor_id']   ?? null,
            notes:                 $context['notes']      ?? null,
        );

        // Build UPDATE fields
        $sets   = ['status = ?', 'updated_at = NOW()'];
        $params = [$newStatus];

        if ($newStatus === 'success' || $newStatus === 'cod_collected') {
            $sets[]   = 'verified_at = COALESCE(verified_at, NOW())';
            if (!empty($context['verification_source'])) {
                $sets[]   = 'verification_source = ?';
                $params[] = $context['verification_source'];
            }
            if (!empty($context['verified_by_admin_id'])) {
                $sets[]   = 'verified_by_admin_id = ?';
                $params[] = (int)$context['verified_by_admin_id'];
            }
        }

        if (!empty($context['failure_reason']) && in_array($newStatus, ['failed', 'refund_failed'], true)) {
            $sets[]   = 'failure_reason = ?';
            $params[] = $context['failure_reason'];
        }

        if (!empty($context['bank_confirmed_ref'])) {
            $sets[]   = 'bank_confirmed_ref = ?';
            $params[] = $context['bank_confirmed_ref'];
        }

        $params[] = $paymentId;
        $sql      = "UPDATE payment_transactions SET " . implode(', ', $sets) . " WHERE payment_id = ?";
        $this->pdo->prepare($sql)->execute($params);

        // Sync orders.payment_status
        $orderPaymentStatus = ksrOrderPaymentStatusValue($newStatus);
        $orderStatus        = ksrOrderStatusForPayment($newStatus);
        $orderId            = (int)$row['order_id'];

        if ($orderStatus !== null) {
            $this->pdo->prepare(
                "UPDATE orders SET payment_status = ?, status = ? WHERE id = ?"
            )->execute([$orderPaymentStatus, $orderStatus, $orderId]);
        } else {
            $this->pdo->prepare(
                "UPDATE orders SET payment_status = ? WHERE id = ?"
            )->execute([$orderPaymentStatus, $orderId]);
        }

        return true;
    }
}

