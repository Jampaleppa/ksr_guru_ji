<?php

declare(strict_types=1);

namespace KsrPay;

/**
 * AuditLogger — IMMUTABLE financial audit trail writer.
 *
 * A failed audit write throws \RuntimeException and MUST block
 * the financial action that triggered it (do NOT catch silently).
 */
class AuditLogger
{
    public function __construct(private readonly \PDO $pdo) {}

    /**
     * Insert a row into payment_audit_logs. Throws on failure (immutable).
     */
    public function log(
        string  $eventType,
        ?int    $paymentTransactionId = null,
        ?int    $orderId              = null,
        ?string $oldStatus            = null,
        ?string $newStatus            = null,
        string  $actorType            = 'system',
        ?int    $actorId              = null,
        ?string $notes                = null,
        ?string $ipAddress            = null,
        ?string $userAgent            = null,
    ): void {
        $ip        = $ipAddress ?? ($_SERVER['REMOTE_ADDR']    ?? 'cli');
        $ua        = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');

        $sql = "
            INSERT INTO payment_audit_logs
                (payment_transaction_id, order_id, event_type,
                 old_status, new_status, actor_type, actor_id,
                 ip_address, user_agent, notes, created_at)
            VALUES
                (:payment_transaction_id, :order_id, :event_type,
                 :old_status, :new_status, :actor_type, :actor_id,
                 :ip_address, :user_agent, :notes, NOW())
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':payment_transaction_id' => $paymentTransactionId,
                ':order_id'               => $orderId,
                ':event_type'             => $eventType,
                ':old_status'             => $oldStatus,
                ':new_status'             => $newStatus,
                ':actor_type'             => $actorType,
                ':actor_id'               => $actorId,
                ':ip_address'             => $ip,
                ':user_agent'             => $ua,
                ':notes'                  => $notes,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'AuditLogger: failed to write audit log — ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Fetch all audit rows for a given payment_transaction_id.
     */
    public function getLogs(int $paymentTransactionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM payment_audit_logs
              WHERE payment_transaction_id = :id
              ORDER BY id ASC'
        );
        $stmt->execute([':id' => $paymentTransactionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
