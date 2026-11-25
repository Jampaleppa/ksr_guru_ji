<?php
declare(strict_types=1);

namespace KsrPay;

/**
 * CodManager — Handles Cash on Delivery lifecycle:
 * eligibility check, delivery OTP generation/verification,
 * customer cash collection tracking, and delivery-partner cash handover.
 */
class CodManager
{
    public const MAX_COD_AMOUNT_PAISE = 200000; // ₹2,000 limit

    private AuditLogger $audit;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->audit = new AuditLogger($pdo);
    }

    /**
     * Check if order is eligible for COD.
     */
    public function isEligible(int $amountPaise): array
    {
        if ($amountPaise <= 0) {
            return ['eligible' => false, 'reason' => 'Invalid order amount.'];
        }

        if ($amountPaise > self::MAX_COD_AMOUNT_PAISE) {
            $maxInr = number_format(self::MAX_COD_AMOUNT_PAISE / 100, 2);
            return [
                'eligible' => false,
                'reason'   => "Cash on Delivery is only available for orders up to ₹{$maxInr}.",
            ];
        }

        return ['eligible' => true, 'reason' => ''];
    }

    /**
     * Generate a 4-digit numeric OTP for delivery confirmation.
     */
    public function generateDeliveryOtp(): string
    {
        return str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a COD collection assignment for a payment.
     */
    public function createCollection(int $paymentTransactionId, int $orderId, ?int $deliveryPartnerId, int $expectedAmountPaise): int
    {
        $otp = $this->generateDeliveryOtp();

        $stmt = $this->pdo->prepare(
            "INSERT INTO cod_collections
                (payment_transaction_id, order_id, delivery_partner_id, expected_amount_paise, collection_status, delivery_otp)
             VALUES (?, ?, ?, ?, 'cod_assigned', ?)"
        );
        $stmt->execute([
            $paymentTransactionId,
            $orderId,
            $deliveryPartnerId,
            $expectedAmountPaise,
            $otp,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Verify delivery OTP and record customer cash collection.
     */
    public function recordCollection(
        int    $codCollectionId,
        int    $collectedAmountPaise,
        string $otp,
        int    $partnerId,
        string $notes = ''
    ): array {
        $forUpdate = ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') ? '' : ' FOR UPDATE';
        $stmt = $this->pdo->prepare(
            "SELECT * FROM cod_collections WHERE id = ?{$forUpdate}"
        );
        $stmt->execute([$codCollectionId]);
        $coll = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$coll) {
            return ['success' => false, 'message' => 'Collection record not found.'];
        }

        if ($coll['delivery_otp'] !== trim($otp)) {
            return ['success' => false, 'message' => 'Invalid delivery OTP.'];
        }

        $expected = (int)$coll['expected_amount_paise'];
        $discrepancy = $collectedAmountPaise - $expected;
        $status = ($discrepancy === 0) ? 'cod_collected' : 'cod_discrepancy';

        $upd = $this->pdo->prepare(
            "UPDATE cod_collections
                SET collected_amount_paise = ?,
                    discrepancy_paise      = ?,
                    collection_status      = ?,
                    otp_verified           = 1,
                    collected_at           = NOW(),
                    delivered_at           = NOW(),
                    collection_notes       = ?
              WHERE id = ?"
        );
        $upd->execute([
            $collectedAmountPaise,
            $discrepancy,
            $status,
            $notes ?: null,
            $codCollectionId,
        ]);

        // Audit
        $this->audit->log(
            eventType:            'COD_COLLECTION_RECORDED',
            paymentTransactionId: (int)$coll['payment_transaction_id'],
            orderId:              (int)$coll['order_id'],
            actorType:            'system',
            actorId:              $partnerId,
            notes:                "Collected: {$collectedAmountPaise} paise, Expected: {$expected} paise. Discrepancy: {$discrepancy} paise.",
        );

        return [
            'success'     => true,
            'discrepancy' => $discrepancy,
            'status'      => $status,
        ];
    }

    /**
     * Record physical cash handover from delivery partner to finance.
     */
    public function recordHandover(
        int    $codCollectionId,
        int    $partnerId,
        int    $adminId,
        int    $receivedAmountPaise,
        string $notes = ''
    ): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM cod_collections WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$codCollectionId]);
        $coll = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$coll) {
            return ['success' => false, 'message' => 'Collection record not found.'];
        }

        $collected = (int)$coll['collected_amount_paise'];
        $discrepancy = $receivedAmountPaise - $collected;
        $status = ($discrepancy === 0) ? 'completed' : 'discrepancy';

        $ins = $this->pdo->prepare(
            "INSERT INTO cod_handovers
                (cod_collection_id, delivery_partner_id, received_by_admin_id, expected_amount_paise, received_amount_paise, discrepancy_paise, handover_status, handover_notes, handover_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $ins->execute([
            $codCollectionId,
            $partnerId,
            $adminId,
            $collected,
            $receivedAmountPaise,
            $discrepancy,
            $status,
            $notes ?: null,
        ]);

        $handoverId = (int)$this->pdo->lastInsertId();

        $this->audit->log(
            eventType:            'COD_HANDOVER_RECORDED',
            paymentTransactionId: (int)$coll['payment_transaction_id'],
            orderId:              (int)$coll['order_id'],
            actorType:            'admin',
            actorId:              $adminId,
            notes:                "Handover #{$handoverId}: Received {$receivedAmountPaise} paise, expected {$collected} paise.",
        );

        return [
            'success'     => true,
            'handover_id' => $handoverId,
            'discrepancy' => $discrepancy,
            'status'      => $status,
        ];
    }
}
