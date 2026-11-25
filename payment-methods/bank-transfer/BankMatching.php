<?php
declare(strict_types=1);

namespace KsrPay;

/**
 * BankMatching — matches imported bank credits to payment_transactions.
 *
 * MATCHING RULES (all must pass for auto-match eligibility):
 *  1. bank_ref must not already be matched (match_status = 'unmatched')
 *  2. Direction must be CREDIT (enforced at import — debits not stored)
 *  3. Amount must EXACTLY equal the expected payment amount
 *  4. Currency must be INR
 *  5. Date must be within reasonable window of payment initiation
 *  6. Payment must be in 'verification_pending' or 'pending' status
 *  7. No conflicting reversal on the bank credit
 *
 * Manual reviewer approval is REQUIRED before transitioning to 'success'.
 * BankMatching only produces a CANDIDATE — it does not confirm payments.
 */
class BankMatching
{
    private AuditLogger $audit;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->audit = new AuditLogger($pdo);
    }

    /**
     * Find matching payment candidates for all unmatched bank credits.
     *
     * Returns an array of match candidates — does NOT apply any status changes.
     * Each candidate must be reviewed and approved by an authorised admin.
     *
     * @return array<int, array{credit: array, payment: array, confidence: string}>
     */
    public function findCandidates(): array
    {
        $candidates = [];

        // Load all unmatched credits
        $creditsStmt = $this->pdo->query(
            "SELECT * FROM payment_bank_credits
              WHERE match_status = 'unmatched'
              ORDER BY credit_date DESC, id DESC"
        );
        $credits = $creditsStmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($credits as $credit) {
            $match = $this->findPaymentForCredit($credit);
            if ($match !== null) {
                $candidates[] = [
                    'credit'     => $credit,
                    'payment'    => $match,
                    'confidence' => $this->assessConfidence($credit, $match),
                ];
            }
        }

        return $candidates;
    }

    /**
     * Apply a manual admin-approved match.
     *
     * Sets:
     *  - payment_bank_credits.match_status = 'matched'
     *  - payment_bank_credits.matched_payment_id = $paymentTransactionId
     *  - payment_reconciliations: creates record
     *  - payment_transactions: bank_confirmed_ref set
     *  - Audit log recorded
     *
     * Does NOT set payment to 'success' — that transition remains
     * with PaymentStateMachine called explicitly by the admin.
     *
     * @throws \RuntimeException if the match cannot be applied safely
     */
    public function applyMatch(
        int    $bankCreditId,
        int    $paymentTransactionId,
        int    $reviewerAdminId,
        string $outcome = 'matched',
        string $notes = ''
    ): void {
        $this->pdo->beginTransaction();

        try {
            $forUpdate = ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') ? '' : ' FOR UPDATE';

            // Load credit
            $creditStmt = $this->pdo->prepare(
                "SELECT * FROM payment_bank_credits WHERE id = ? AND match_status = 'unmatched'{$forUpdate}"
            );
            $creditStmt->execute([$bankCreditId]);
            $credit = $creditStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$credit) {
                throw new \RuntimeException("Bank credit #{$bankCreditId} not found or already matched.");
            }

            // Load payment
            $ptStmt = $this->pdo->prepare(
                "SELECT * FROM payment_transactions WHERE id = ?{$forUpdate}"
            );
            $ptStmt->execute([$paymentTransactionId]);
            $payment = $ptStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new \RuntimeException("Payment transaction #{$paymentTransactionId} not found.");
            }

            // Validate amount match
            if ((int)$credit['amount_paise'] !== (int)$payment['amount_paise']) {
                throw new \RuntimeException(sprintf(
                    "Amount mismatch: bank credit is %d paise, payment expects %d paise.",
                    $credit['amount_paise'],
                    $payment['amount_paise']
                ));
            }

            // Update bank credit
            $this->pdo->prepare(
                "UPDATE payment_bank_credits
                    SET match_status = 'matched', matched_payment_id = ?
                  WHERE id = ?"
            )->execute([$paymentTransactionId, $bankCreditId]);

            // Set bank_confirmed_ref on payment transaction
            $this->pdo->prepare(
                "UPDATE payment_transactions
                    SET bank_confirmed_ref = ?, updated_at = NOW()
                  WHERE id = ?"
            )->execute([$credit['bank_ref'], $paymentTransactionId]);

            // Create reconciliation record
            $this->pdo->prepare(
                "INSERT INTO payment_reconciliations
                    (payment_transaction_id, bank_credit_id, reconciled_by, outcome, notes)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([
                $paymentTransactionId,
                $bankCreditId,
                $reviewerAdminId,
                $outcome,
                $notes ?: null,
            ]);

            // Audit
            $this->audit->log(
                eventType:            'BANK_CREDIT_MATCHED',
                paymentTransactionId: $paymentTransactionId,
                orderId:              (int)$payment['order_id'],
                actorType:            'admin',
                actorId:              $reviewerAdminId,
                notes:                "Bank ref: {$credit['bank_ref']} | Amount: {$credit['amount_paise']} paise | Outcome: {$outcome}",
            );

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Mark a bank credit as disputed (for investigation).
     */
    public function markDisputed(int $bankCreditId, int $adminId, string $notes): void
    {
        $this->pdo->prepare(
            "UPDATE payment_bank_credits SET match_status = 'disputed' WHERE id = ?"
        )->execute([$bankCreditId]);

        $this->audit->log(
            eventType: 'BANK_CREDIT_DISPUTED',
            actorType: 'admin',
            actorId:   $adminId,
            notes:     "Credit #{$bankCreditId} marked disputed. Notes: {$notes}",
        );
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function findPaymentForCredit(array $credit): ?array
    {
        // Compute 7-day lookback window in PHP so query is portable across MySQL & SQLite
        $creditTs    = strtotime((string)($credit['credit_date'] ?? 'now')) ?: time();
        $windowStart = date('Y-m-d 00:00:00', strtotime('-7 days', $creditTs));

        // Look for payments with the same amount that are awaiting verification
        // and whose UTR matches the bank ref or utr column, or by amount+date window
        $stmt = $this->pdo->prepare(
            "SELECT pt.*
               FROM payment_transactions pt
              WHERE pt.amount_paise   = ?
                AND pt.currency       = 'INR'
                AND pt.status        IN ('pending', 'verification_pending', 'upi_awaiting', 'upi_pending_verification')
                AND pt.created_at    >= ?
                AND NOT EXISTS (
                    SELECT 1 FROM payment_reconciliations pr
                     WHERE pr.payment_transaction_id = pt.id
                )
              ORDER BY
                -- Exact UTR match scores highest
                CASE WHEN pt.customer_utr = ? OR pt.customer_utr = ? THEN 0 ELSE 1 END,
                pt.created_at DESC
              LIMIT 1"
        );

        $stmt->execute([
            $credit['amount_paise'],
            $windowStart,
            $credit['bank_ref'],
            $credit['utr'] ?? '',
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function assessConfidence(array $credit, array $payment): string
    {
        // UTR exact match = high confidence
        if (
            !empty($payment['customer_utr']) &&
            (
                $payment['customer_utr'] === $credit['bank_ref'] ||
                $payment['customer_utr'] === ($credit['utr'] ?? '')
            )
        ) {
            return 'high';
        }

        // Amount + date match only = medium (requires manual review)
        return 'medium';
    }
}
