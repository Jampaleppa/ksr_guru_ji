<?php
declare(strict_types=1);

namespace KsrPay;

/**
 * ReconciliationManager — bank credit matching and import.
 */
class ReconciliationManager
{
    public function __construct(
        private \PDO        $pdo,
        private AuditLogger $audit
    ) {}

    /**
     * Match a bank credit to a payment transaction.
     * Sets bank_credits.match_status = 'matched' and inserts reconciliation record.
     * Does NOT transition payment status — caller (via PaymentStateMachine) does that.
     */
    public function matchCredit(int $bankCreditId, string $paymentId, int $adminId, string $notes = ''): bool
    {
        // Verify credit exists and is unmatched
        $cStmt = $this->pdo->prepare(
            "SELECT * FROM payment_bank_credits WHERE id = ? AND match_status = 'unmatched' LIMIT 1"
        );
        $cStmt->execute([$bankCreditId]);
        $credit = $cStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$credit) {
            return false;
        }

        // Verify payment exists
        $pStmt = $this->pdo->prepare(
            "SELECT id, amount_paise FROM payment_transactions WHERE payment_id = ? LIMIT 1"
        );
        $pStmt->execute([$paymentId]);
        $payment = $pStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$payment) {
            return false;
        }

        $outcome = ($credit['amount_paise'] === $payment['amount_paise']) ? 'matched' : 'manual_override';

        // Update bank credit
        $this->pdo->prepare(
            "UPDATE payment_bank_credits
             SET matched_payment_id = ?, match_status = 'matched'
             WHERE id = ?"
        )->execute([$payment['id'], $bankCreditId]);

        // Insert reconciliation record
        $this->pdo->prepare(
            "INSERT INTO payment_reconciliations
                (payment_transaction_id, bank_credit_id, reconciled_by, outcome, notes)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$payment['id'], $bankCreditId, $adminId, $outcome, $notes]);

        // Update payment.bank_confirmed_ref from bank credit's bank_ref
        $this->pdo->prepare(
            "UPDATE payment_transactions SET bank_confirmed_ref = ? WHERE payment_id = ?"
        )->execute([$credit['bank_ref'], $paymentId]);

        $this->audit->log(
            eventType:            'BANK_CREDIT_MATCHED',
            paymentTransactionId: (int)$payment['id'],
            notes:                "Bank credit #{$bankCreditId} matched. Outcome: {$outcome}. {$notes}",
            actorType:            'admin',
            actorId:              $adminId,
        );

        return true;
    }

    /** Fetch all unmatched bank credits. */
    public function getPendingCredits(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM payment_bank_credits WHERE match_status = 'unmatched' ORDER BY credit_date DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Fetch UPI payments awaiting admin verification. */
    public function getPendingPayments(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pt.*, o.order_number, u.name AS customer_name, u.phone
             FROM payment_transactions pt
             LEFT JOIN orders o ON o.id = pt.order_id
             LEFT JOIN users  u ON u.id = pt.user_id
             WHERE pt.status IN ('upi_awaiting','upi_pending_verification')
               AND pt.method = 'upi'
             ORDER BY pt.created_at ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Import a bank credit row (manual entry by admin).
     * Returns the new bank_credit_id.
     */
    public function importCredit(array $data, int $adminId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO payment_bank_credits
                (credit_date, bank_ref, utr, narration, amount_paise, imported_by, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['credit_date'],
            $data['bank_ref'],
            $data['utr']       ?? null,
            $data['narration'] ?? null,
            (int)$data['amount_paise'],
            $adminId,
            $data['notes']     ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}

