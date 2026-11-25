<?php
declare(strict_types=1);

namespace KsrPay;

/**
 * PaymentSessionManager — Manages payment session tokens, TTL expiry,
 * and secure guest order verification tokens.
 */
class PaymentSessionManager
{
    private const SESSION_EXPIRY_SECONDS = 3600; // 1 hour session lifetime

    public function __construct(private readonly \PDO $pdo) {}

    /**
     * Create or refresh a payment session expiry.
     */
    public function initializeSession(int $paymentTransactionId, int $ttlSeconds = self::SESSION_EXPIRY_SECONDS): string
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $stmt = $this->pdo->prepare(
            "UPDATE payment_transactions
                SET expires_at = ?, updated_at = NOW()
              WHERE id = ?"
        );
        $stmt->execute([$expiresAt, $paymentTransactionId]);

        return $expiresAt;
    }

    /**
     * Validate if a payment session is still active (not expired).
     */
    public function isSessionValid(array $payment): bool
    {
        if (empty($payment['expires_at'])) {
            return true;
        }

        $expiry = strtotime($payment['expires_at']);
        if ($expiry === false) {
            return true;
        }

        return time() <= $expiry;
    }

    /**
     * Expire a payment session if TTL has elapsed.
     */
    public function checkAndExpire(string $paymentId, PaymentStateMachine $stateMachine): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM payment_transactions WHERE payment_id = ? LIMIT 1"
        );
        $stmt->execute([$paymentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $currentStatus = ksrNormalizePaymentStatus($row['status']);
        if (in_array($currentStatus, ['success', 'cancelled', 'failed', 'refunded', 'expired'], true)) {
            return false;
        }

        if (!$this->isSessionValid($row)) {
            return $stateMachine->transition($paymentId, 'expired', [
                'actor_type'     => 'system',
                'failure_reason' => 'Payment session expired after TTL timeout.',
                'notes'          => 'Automatically marked expired by PaymentSessionManager.',
            ]);
        }

        return false;
    }
}
