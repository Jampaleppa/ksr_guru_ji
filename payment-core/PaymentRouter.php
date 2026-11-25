<?php
declare(strict_types=1);

namespace KsrPay;

/**
 * PaymentRouter — Routes checkout requests to the appropriate payment method handler
 * and validates service-level payment availability.
 */
class PaymentRouter
{
    /** Default checkout routes for each payment method */
    private const METHOD_ROUTES = [
        'upi'           => '/payment-methods/upi/merchant_qr.php',
        'cod'           => '/payment-methods/cod/create_collection.php',
        'bank_transfer' => '/payment-methods/bank-transfer/instructions.php',
    ];

    public function __construct(private readonly \PDO $pdo) {}

    /**
     * Get the target checkout URL for a given payment method and payment session.
     */
    public function getCheckoutUrl(string $method, string $paymentId, int $orderId): string
    {
        $method = strtolower(trim($method));
        $route = self::METHOD_ROUTES[$method] ?? '/payment/checkout.php';

        $query = http_build_query([
            'pid' => $paymentId,
            'oid' => $orderId,
        ]);

        return "{$route}?{$query}";
    }

    /**
     * Check if a payment method is enabled in the database.
     */
    public function isMethodEnabled(string $method): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT is_enabled FROM payment_methods WHERE code = ? LIMIT 1"
        );
        $stmt->execute([strtolower(trim($method))]);
        $enabled = $stmt->fetchColumn();

        return (bool)$enabled;
    }

    /**
     * Check if a payment method is eligible for a specific service or order amount.
     */
    public function isMethodEligible(string $method, int $amountPaise, string $serviceType = 'grocery'): array
    {
        $method = strtolower(trim($method));

        if (!$this->isMethodEnabled($method)) {
            return [
                'eligible' => false,
                'reason'   => "Payment method '{$method}' is currently not enabled.",
            ];
        }

        // COD limit rule: maximum ₹2,000 (200000 paise)
        if ($method === 'cod') {
            if ($amountPaise > 200000) {
                return [
                    'eligible' => false,
                    'reason'   => "Cash on Delivery is limited to orders up to ₹2,000. Please choose UPI or Bank Transfer.",
                ];
            }
        }

        // Service specific rules
        if (in_array($serviceType, ['doctor_consultation', 'doctor_assist'], true) && $method === 'cod') {
            return [
                'eligible' => false,
                'reason'   => 'Doctor consultations require prepaid payment.',
            ];
        }

        return ['eligible' => true, 'reason' => ''];
    }
}
