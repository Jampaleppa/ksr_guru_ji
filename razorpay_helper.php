<?php
// ============================================================
// api/razorpay_helper.php - Razorpay Gateway helper with Route support
// ============================================================

require_once __DIR__ . '/config.php';

class RazorpayHelper {
    private static ?string $keyId = null;
    private static ?string $keySecret = null;
    private static ?string $webhookSecret = null;
    private static ?bool $enabled = null;

    private static function init(): void {
        if (self::$keyId !== null) return;

        // Default test keys
        self::$keyId = 'rzp_test_KSRGuruJi1234';
        self::$keySecret = 'KSRGuruJiSecret5678App';
        self::$webhookSecret = 'KSRGuruJiWebhookSecret9012';
        self::$enabled = true;

        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE category = 'payment'");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['setting_key'] === 'razorpay_key_id') {
                    self::$keyId = $row['setting_value'];
                } elseif ($row['setting_key'] === 'razorpay_key_secret') {
                    self::$keySecret = $row['setting_value'];
                } elseif ($row['setting_key'] === 'razorpay_webhook_secret') {
                    self::$webhookSecret = $row['setting_value'];
                } elseif ($row['setting_key'] === 'razorpay_enabled') {
                    self::$enabled = ($row['setting_value'] == '1' || $row['setting_value'] === 'true' || $row['setting_value'] === true);
                }
            }
        } catch (Exception $e) {
            error_log("RazorpayHelper init database query error: " . $e->getMessage());
        }
    }

    public static function isEnabled(): bool {
        self::init();
        return (bool) self::$enabled;
    }

    public static function getKeyId() {
        self::init();
        return self::$keyId;
    }

    public static function getKeySecret() {
        self::init();
        return self::$keySecret;
    }

    public static function getWebhookSecret() {
        self::init();
        return self::$webhookSecret;
    }

    public static function isMockMode() {
        self::init();
        return (strpos(self::$keyId, 'rzp_test_KSR') === 0 || empty(self::$keySecret));
    }

    /**
     * Is this a production environment?
     *
     * The browser-side simulated payment path must NEVER be able to
     * create a genuine SUCCESS payment in production. Set
     * KSR_ENV=production in config.php (or the server env) to disable
     * the simulation fallback entirely.
     */
    public static function isProduction() {
        $env = getenv('KSR_ENV');
        if ($env === false && defined('KSR_ENV')) {
            $env = KSR_ENV;
        }
        return is_string($env) && strtolower($env) === 'production';
    }

    /**
     * True when the mock/simulated path is permitted at all.
     * In production this is always false.
     */
    public static function simulationAllowed() {
        return !self::isProduction() && self::isMockMode();
    }

    /**
     * Fetch a Razorpay order from the gateway REST API.
     * Used to confirm a gateway order actually exists and that its amount
     * matches the KSR order amount before accepting a signature.
     */
    public static function fetchOrder(string $razorpayOrderId): ?array {
        self::init();

        if (empty($razorpayOrderId) || strpos((string)$razorpayOrderId, 'rzp_order_mock_') === 0) {
            return null;
        }

        $url = 'https://api.razorpay.com/v1/orders/' . rawurlencode($razorpayOrderId);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, self::$keyId . ':' . self::$keySecret);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch); // curl_close() deprecated in PHP 8.5; handle is freed automatically

        if ($httpCode !== 200) {
            error_log("Razorpay fetchOrder failure: HTTP {$httpCode} for {$razorpayOrderId}");
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Fetch a Razorpay payment (not order) from the gateway REST API.
     * Used by the expiry worker to confirm a delayed capture before
     * ever marking a payment expired.
     */
    public static function fetchPayment(string $razorpayPaymentId): ?array {
        self::init();

        if (empty($razorpayPaymentId)
            || strpos((string)$razorpayPaymentId, 'pay_mock_') === 0
            || strpos((string)$razorpayPaymentId, 'pay_webhook_') === 0) {
            return null;
        }

        $url = 'https://api.razorpay.com/v1/payments/' . rawurlencode($razorpayPaymentId);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, self::$keyId . ':' . self::$keySecret);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch); // curl_close() deprecated in PHP 8.5; handle is freed automatically

        if ($httpCode !== 200) {
            error_log("Razorpay fetchPayment failure: HTTP {$httpCode} for {$razorpayPaymentId}");
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Verify a Razorpay webhook signature.
     *
     * In production a missing/empty webhook secret means we cannot
     * trust the payload, so verification fails closed.
     */
    public static function verifyWebhookSignature(string $rawBody, string $signature): bool {
        self::init();

        if (empty($signature)) {
            return false;
        }

        if (empty(self::$webhookSecret)) {
            // No secret configured: only tolerated outside production.
            return !self::isProduction();
        }

        $expected = hash_hmac('sha256', (string)$rawBody, self::$webhookSecret);
        return hash_equals($expected, (string)$signature);
    }

    /**
     * Create a Razorpay Order on server side
     * Supports Razorpay Route Split Transfer logic:
     * Splitting 90% to vendor account, and 10% stays on the portal as platform fee.
     */
    public static function createOrder(int|float|string $amountInRupees, string $receiptId, array $items = []): array {
        self::init();

        $amountInPaise = round($amountInRupees * 100);

        // Build Payload
        $payload = [
            'amount' => $amountInPaise,
            'currency' => 'INR',
            'receipt' => (string)$receiptId,
            'payment_capture' => 1
        ];

        // Razorpay Route Split Transfer logic
        $transfers = [];
        $vendorAccMap = [
            'Pooja' => 'acc_pooja_vendor_123',
            'Cooking Oil' => 'acc_oil_vendor_456',
            'Spices' => 'acc_spices_vendor_789',
            'Dry Fruits' => 'acc_dryfruits_vendor_101',
            'Bakery' => 'acc_bakery_vendor_102'
        ];

        if (is_array($items)) {
            foreach ($items as $item) {
                // Determine item total
                $price = isset($item['price']) ? (float)$item['price'] : 0;
                $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
                $itemTotal = $price * $qty;

                if ($itemTotal > 0) {
                    $storeName = $item['store'] ?? 'general';
                    $vendorAccount = $vendorAccMap[$storeName] ?? 'acc_general_vendor_999';

                    // 90% goes to vendor
                    $vendorSharePaise = round($itemTotal * 0.90 * 100);
                    if ($vendorSharePaise > 0) {
                        $transfers[] = [
                            'account' => $vendorAccount,
                            'amount' => (int)$vendorSharePaise,
                            'currency' => 'INR',
                            'notes' => [
                                'item_name' => substr($item['name'] ?? '', 0, 30),
                                'store' => $storeName
                            ],
                            'on_hold' => false
                        ];
                    }
                }
            }
        }

        if (!empty($transfers)) {
            $payload['transfers'] = $transfers;
        }

        // If mock key or simulation mode, return mock payload immediately,
        // but NEVER in production.
        if (self::simulationAllowed()) {
            return [
                'success' => true,
                'id' => 'rzp_order_mock_' . bin2hex(random_bytes(8)),
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'receipt' => $receiptId,
                'mock' => true,
                'transfers' => $transfers
            ];
        }

        // Invoke Online Razorpay Order endpoint via curl
        $url = 'https://api.razorpay.com/v1/orders';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, self::$keyId . ':' . self::$keySecret);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch); // curl_close() deprecated in PHP 8.5; handle is freed automatically

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $data['success'] = true;
            return $data;
        } else {
            error_log("Razorpay Order API call failure: HTTP Code $httpCode. Response: $response.");

            // Production must fail loudly — never fabricate a mock order.
            if (self::isProduction() || !self::isMockMode()) {
                return [
                    'success' => false,
                    'error'   => 'Unable to create Razorpay order. Please try again.'
                ];
            }

            return [
                'success' => true,
                'id' => 'rzp_order_mock_' . bin2hex(random_bytes(8)),
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'receipt' => $receiptId,
                'mock' => true,
                'transfers' => $transfers
            ];
        }
    }

    /**
     * Verify server-side payment signature
     */
    public static function verifySignature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): bool {
        self::init();

        // Safe mock pass — explicitly refused in production so a forged
        // rzp_order_mock_*/pay_mock_* pair can never confirm a real order.
        if (strpos((string)$razorpayOrderId, 'rzp_order_mock_') === 0 || strpos((string)$razorpayPaymentId, 'pay_mock_') === 0) {
            return self::simulationAllowed();
        }

        if (empty(self::$keySecret)) {
            return false;
        }

        $generatedSignature = hash_hmac('sha256', $razorpayOrderId . "|" . $razorpayPaymentId, self::$keySecret);
        return hash_equals($generatedSignature, $razorpaySignature);
    }

    public static function verifyPaymentSignature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): bool {
        return self::verifySignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature);
    }

    /**
     * Process Refund via Razorpay API
     */
    public static function refundPayment(string $paymentId, int|float|string $amountInRupees, string $reason = ''): array {
        self::init();

        // If mock payment (development only).
        if (strpos((string)$paymentId, 'pay_mock_') === 0 || strpos((string)$paymentId, 'COD') === 0 || empty($paymentId)) {
            if (self::isProduction() && empty($paymentId)) {
                return ['success' => false, 'error' => 'Missing gateway payment id for refund.'];
            }
            return [
                'success' => true,
                'id' => 'rfnd_mock_' . bin2hex(random_bytes(8)),
                'status' => 'processed',
                'amount' => round($amountInRupees * 100),
                'mock' => true
            ];
        }

        $payload = [
            'amount' => round($amountInRupees * 100),
            'speed' => 'normal',
            'notes' => [
                'reason' => $reason
            ]
        ];

        $url = "https://api.razorpay.com/v1/payments/{$paymentId}/refund";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, self::$keyId . ':' . self::$keySecret);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch); // curl_close() deprecated in PHP 8.5; handle is freed automatically

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $data['success'] = true;
            return $data;
        } else {
            error_log("Razorpay Refund API error: HTTP Code $httpCode. Response: $response");
            return [
                'success' => false,
                'error' => json_decode($response, true)['error']['description'] ?? 'Refund failed via Razorpay API.'
            ];
        }
    }
}
