<?php
declare(strict_types=1);

namespace KsrPay;

/**
 * RefundManager — refund request lifecycle.
 */
class RefundManager
{
    public function __construct(
        private \PDO        $pdo,
        private AuditLogger $audit
    ) {}

    /**
     * Create a refund request. Returns new refund_id.
     * Payment must be in 'success' status.
     */
    public function requestRefund(string $paymentId, int $userId, int $amountPaise, string $reason): int
    {
        $pStmt = $this->pdo->prepare(
            "SELECT id, order_id, status, amount_paise FROM payment_transactions WHERE payment_id = ? LIMIT 1"
        );
        $pStmt->execute([$paymentId]);
        $payment = $pStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new \InvalidArgumentException("Payment not found: {$paymentId}");
        }

        if ($payment['status'] !== 'success') {
            throw new \InvalidArgumentException("Refund only allowed for successful payments. Current: {$payment['status']}");
        }

        if ($amountPaise > (int)$payment['amount_paise'] || $amountPaise <= 0) {
            throw new \InvalidArgumentException("Invalid refund amount.");
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO payment_refunds
                (payment_transaction_id, order_id, requested_by, amount_paise, reason)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([(int)$payment['id'], (int)$payment['order_id'], $userId, $amountPaise, $reason]);
        $refundId = (int)$this->pdo->lastInsertId();

        $this->audit->log(
            eventType:            'REFUND_REQUESTED',
            paymentTransactionId: (int)$payment['id'],
            orderId:              (int)$payment['order_id'],
            notes:                "Refund #{$refundId} requested for ₹" . number_format($amountPaise / 100, 2),
            actorType:            'customer',
            actorId:              $userId,
        );

        return $refundId;
    }

    /** Admin approves a refund request. */
    public function approveRefund(int $refundId, int $adminId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE payment_refunds SET status = 'approved', approved_by = ?, approved_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$adminId, $refundId]);
        $ok = $stmt->rowCount() > 0;

        if ($ok) {
            $this->audit->log(
                eventType: 'REFUND_APPROVED',
                notes:     "Refund #{$refundId} approved by admin #{$adminId}",
                actorType: 'admin',
                actorId:   $adminId,
            );
        }

        return $ok;
    }

    /** Mark a refund as completed (money sent back). */
    public function completeRefund(
        int     $refundId,
        int     $adminId,
        ?string $providerRefundId = null,
        ?string $bankRef          = null
    ): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE payment_refunds
             SET status = 'completed', completed_at = NOW(),
                 provider_refund_id = ?, bank_ref = ?,
                 approved_by = COALESCE(approved_by, ?)
             WHERE id = ? AND status IN ('approved','processing')"
        );
        $stmt->execute([$providerRefundId, $bankRef, $adminId, $refundId]);
        $ok = $stmt->rowCount() > 0;

        if ($ok) {
            $this->audit->log(
                eventType: 'REFUND_COMPLETED',
                notes:     "Refund #{$refundId} completed. Bank ref: {$bankRef}",
                actorType: 'admin',
                actorId:   $adminId,
            );
        }

        return $ok;
    }

    /** All pending refund requests (for admin dashboard). */
    public function getPendingRefunds(): array
    {
        $stmt = $this->pdo->query(
            "SELECT pr.*, o.order_number, u.name AS customer_name, u.phone,
                    pt.payment_id, pt.method
             FROM payment_refunds pr
             LEFT JOIN payment_transactions pt ON pt.id = pr.payment_transaction_id
             LEFT JOIN orders o ON o.id = pr.order_id
             LEFT JOIN users  u ON u.id = pr.requested_by
             WHERE pr.status IN ('pending','approved','processing')
             ORDER BY pr.requested_at ASC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

