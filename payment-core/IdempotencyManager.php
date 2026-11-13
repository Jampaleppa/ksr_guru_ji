<?php
declare(strict_types=1);

namespace KsrPay;

/**
 * IdempotencyManager — prevents duplicate payment creation on retries.
 */
class IdempotencyManager
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Check if idempotency key already has a payment.
     * Returns the existing payment_transaction_id or null.
     */
    public function check(string $key): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT payment_transaction_id FROM payment_idempotency_keys
             WHERE idempotency_key = ? AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$key]);
        $row = $stmt->fetchColumn();
        return $row !== false ? (int)$row : null;
    }

    /**
     * Register an idempotency key bound to a payment_transaction_id.
     */
    public function register(string $key, int $paymentTransactionId, int $ttlSeconds = 86400): void
    {
        $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO payment_idempotency_keys
                (idempotency_key, payment_transaction_id, expires_at)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$key, $paymentTransactionId, $expires]);
    }

    /**
     * Generate a deterministic idempotency key for an order+user+method.
     */
    public function generate(int $orderId, int $userId, string $method): string
    {
        return hash('sha256', "ksr|{$orderId}|{$userId}|{$method}|" . date('Y-m-d'));
    }

    /** Delete expired keys (run via cron or admin). */
    public function purgeExpired(): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM payment_idempotency_keys WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}

