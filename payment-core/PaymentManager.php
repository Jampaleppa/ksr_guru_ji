<?php

declare(strict_types=1);

namespace KsrPay;

require_once __DIR__ . '/AuditLogger.php';
require_once __DIR__ . '/AmountCalculator.php';
require_once __DIR__ . '/PaymentValidator.php';
require_once __DIR__ . '/IdempotencyManager.php';
require_once __DIR__ . '/TransactionManager.php';
require_once __DIR__ . '/PaymentStateMachine.php';

/**
 * PaymentManager — orchestrates all payment operations.
 * Entry point for checkout and admin flows.
 */
class PaymentManager
{
    private AuditLogger        $audit;
    private AmountCalculator   $calc;
    private IdempotencyManager $idempotency;
    private TransactionManager $txMgr;
    private PaymentStateMachine $stateMachine;

    public function __construct(private \PDO $pdo)
    {
        $this->audit        = new AuditLogger($pdo);
        $this->calc         = new AmountCalculator();
        $this->idempotency  = new IdempotencyManager($pdo);
        $this->txMgr        = new TransactionManager($pdo);
        $this->stateMachine = new PaymentStateMachine($pdo, $this->audit);
    }

    /**
     * Create a new payment transaction for an order.
     * Amount is ALWAYS server-calculated from the orders table.
     *
     * Returns array: ['payment_id', 'db_id', 'status', 'amount_paise', 'amount_inr']
     * Throws \RuntimeException or \InvalidArgumentException on error.
     */
    public function createPayment(
        int    $orderId,
        int    $userId,
        string $method,
        string $idempotencyKey
    ): array {
        // Validate method
        $validator = new PaymentValidator();
        $validator->validateMethod($method);
        $validator->validateIdempotencyKey($idempotencyKey);

        // Check idempotency — return existing if already created
        $existingDbId = $this->idempotency->check($idempotencyKey);
        if ($existingDbId !== null) {
            $existing = $this->txMgr->getByPaymentId(''); // find by DB id
            $stmt = $this->pdo->prepare("SELECT * FROM payment_transactions WHERE id = ? LIMIT 1");
            $stmt->execute([$existingDbId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'payment_id'   => $row['payment_id'],
                    'db_id'        => (int)$row['id'],
                    'status'       => $row['status'],
                    'amount_paise' => (int)$row['amount_paise'],
                    'amount_inr'   => $this->calc->formatINR((int)$row['amount_paise']),
                    'idempotent'   => true,
                ];
            }
        }

        // Server-calculate order total — NEVER trust client-submitted amount
        $order = $this->getOrderForPayment($orderId, $userId);
        if (!$order) {
            throw new \InvalidArgumentException("Order #{$orderId} not found or does not belong to this user.");
        }

        // Convert existing decimal amount to paise
        $amountPaise = $this->calc->toPaise((float)$order['net_amount']);

        // Generate unique payment_id
        $paymentId = $this->generatePaymentId();

        $dbId = $this->txMgr->run(function () use (
            $paymentId,
            $orderId,
            $userId,
            $method,
            $amountPaise,
            $idempotencyKey
        ): int {
            $dbId = $this->txMgr->insertPaymentTransaction([
                'payment_id'      => $paymentId,
                'order_id'        => $orderId,
                'user_id'         => $userId,
                'method'          => $method,
                'gateway_code'    => 'manual',
                'amount_paise'    => $amountPaise,
                'currency'        => 'INR',
                'status'          => 'initiated',
                'idempotency_key' => $idempotencyKey,
                'expires_at'      => date('Y-m-d H:i:s', time() + 3600),
            ]);

            // Audit: payment created — this MUST succeed (audit is immutable gate)
            $this->audit->log(
                eventType: 'PAYMENT_CREATED',
                paymentTransactionId: $dbId,
                orderId: $orderId,
                newStatus: 'initiated',
                actorType: 'customer',
                actorId: $userId,
                notes: "Method: {$method}",
            );

            // Register idempotency key
            $this->idempotency->register($idempotencyKey, $dbId, 86400);

            return $dbId;
        });

        return [
            'payment_id'   => $paymentId,
            'db_id'        => $dbId,
            'status'       => 'initiated',
            'amount_paise' => $amountPaise,
            'amount_inr'   => $this->calc->formatINR($amountPaise),
            'idempotent'   => false,
        ];
    }

    /** Fetch payment_transactions row by payment_id. */
    public function getPayment(string $paymentId): ?array
    {
        return $this->txMgr->getByPaymentId($paymentId);
    }

    /** Fetch the most recent payment for an order. */
    public function getPaymentByOrder(int $orderId): ?array
    {
        $rows = $this->txMgr->getByOrderId($orderId);
        return $rows[0] ?? null;
    }

    /** Transition payment status (delegates to PaymentStateMachine). */
    public function transition(string $paymentId, string $newStatus, array $context = []): bool
    {
        return $this->stateMachine->transition($paymentId, $newStatus, $context);
    }

    /** Expose AmountCalculator for controllers. */
    public function calc(): AmountCalculator
    {
        return $this->calc;
    }

    // ── Private helpers ──────────────────────────────────────────────

    /** Generate a unique KSR payment ID. */
    private function generatePaymentId(): string
    {
        $date    = date('Ymd');
        $suffix  = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        return "KSR-{$date}-{$suffix}";
    }

    /**
     * Fetch order for payment, verifying user ownership.
     * Returns null if order not found or belongs to another user.
     */
    private function getOrderForPayment(int $orderId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM orders WHERE id = ? AND (user_id = ? OR user_id IS NULL) LIMIT 1"
        );
        $stmt->execute([$orderId, $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
