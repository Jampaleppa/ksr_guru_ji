<?php
declare(strict_types=1);

namespace KsrPay;

/**
 * TransactionManager — DB transaction wrapper + payment_transactions DML.
 */
class TransactionManager
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Run $fn inside a PDO transaction; rollback and rethrow on any error.
     */
    public function run(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Insert a new payment_transactions row; return its auto-increment id.
     */
    public function insertPaymentTransaction(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_transactions
                (payment_id, order_id, user_id, method, gateway_code,
                 amount_paise, currency, status, idempotency_key, expires_at)
            VALUES
                (:payment_id, :order_id, :user_id, :method, :gateway_code,
                 :amount_paise, :currency, :status, :idempotency_key, :expires_at)
        ");

        $stmt->execute([
            ':payment_id'      => $data['payment_id'],
            ':order_id'        => $data['order_id'],
            ':user_id'         => $data['user_id'] ?? null,
            ':method'          => $data['method'],
            ':gateway_code'    => $data['gateway_code'] ?? 'manual',
            ':amount_paise'    => $data['amount_paise'],
            ':currency'        => $data['currency'] ?? 'INR',
            ':status'          => $data['status'] ?? 'initiated',
            ':idempotency_key' => $data['idempotency_key'] ?? null,
            ':expires_at'      => $data['expires_at'] ?? date('Y-m-d H:i:s', time() + 3600),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update specific fields on a payment_transactions row by payment_id.
     */
    public function updatePaymentTransaction(string $paymentId, array $fields): bool
    {
        if (empty($fields)) {
            return false;
        }

        $allowed = [
            'status', 'customer_utr', 'bank_confirmed_ref',
            'failure_reason', 'metadata', 'verified_at',
            'verification_source', 'verified_by_admin_id',
        ];

        $sets   = [];
        $params = [];

        foreach ($fields as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $sets[]       = "`{$key}` = ?";
                $params[]     = $value;
            }
        }

        if (empty($sets)) {
            return false;
        }

        $params[] = $paymentId;
        $sql      = "UPDATE payment_transactions SET " . implode(', ', $sets) . " WHERE payment_id = ?";
        $stmt     = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /** Fetch a payment_transactions row by payment_id. */
    public function getByPaymentId(string $paymentId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM payment_transactions WHERE payment_id = ? LIMIT 1"
        );
        $stmt->execute([$paymentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Fetch payment_transactions rows for an order_id. */
    public function getByOrderId(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM payment_transactions WHERE order_id = ? ORDER BY id DESC"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

