<?php
declare(strict_types=1);

/**
 * api/payment/webhook.php
 * Future-ready webhook receiver for authorised payment providers.
 * - Verifies HMAC-SHA256 signature (header: X-KSR-Signature)
 * - Stores every event to payment_webhook_events (always)
 * - Returns HTTP 200 always (providers retry on non-2xx)
 * - NEVER exposes internal errors in response body
 */

require_once __DIR__ . '/../../payment-core/bootstrap.php';

header('Content-Type: application/json');

// Always respond 200 to prevent provider retries from flooding
http_response_code(200);
$responseBody = ['received' => true];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode($responseBody); exit;
    }

    $rawBody   = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_KSR_SIGNATURE'] ?? '';
    $eventId   = $_SERVER['HTTP_X_KSR_EVENT_ID']   ?? uniqid('wh_', true);
    $gateway   = $_SERVER['HTTP_X_KSR_GATEWAY']     ?? 'unknown';

    // Webhook secret from payment_gateway_settings (is_secret=1 OK here — server-only read)
    $secretStmt = $pdo->prepare(
        "SELECT setting_value FROM payment_gateway_settings
         WHERE gateway_code = ? AND setting_key = 'webhook_secret' LIMIT 1"
    );
    $secretStmt->execute([$gateway]);
    $secret = $secretStmt->fetchColumn();

    $signatureValid = false;
    if ($secret && $signature) {
        $expected       = hash_hmac('sha256', $rawBody, $secret);
        $signatureValid = hash_equals($expected, $signature);
    }

    $payload = json_decode($rawBody, true);
    $eventType = $payload['event'] ?? $payload['type'] ?? 'unknown';

    // Store the event (idempotent via UNIQUE event_id)
    $isSqlite = ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite');
    $sql = $isSqlite
        ? "INSERT OR IGNORE INTO payment_webhook_events
            (gateway_code, event_id, event_type, payload, signature, processed, error)
           VALUES (?, ?, ?, ?, ?, 0, ?)"
        : "INSERT IGNORE INTO payment_webhook_events
            (gateway_code, event_id, event_type, payload, signature, processed, error)
           VALUES (?, ?, ?, ?, ?, 0, ?)";
    $insertStmt = $pdo->prepare($sql);
    $insertStmt->execute([
        $gateway,
        $eventId,
        $eventType,
        $rawBody,
        $signature,
        $signatureValid ? null : 'SIGNATURE_INVALID',
    ]);

    if (!$signatureValid) {
        // Log but DO NOT process — still return 200
        error_log("Webhook signature invalid for gateway={$gateway} event_id={$eventId}");
        echo json_encode($responseBody); exit;
    }

    // Mark processed
    $pdo->prepare(
        "UPDATE payment_webhook_events SET processed = 1, processed_at = NOW() WHERE event_id = ?"
    )->execute([$eventId]);

    // Future: route to gateway-specific handler based on $gateway and $eventType

} catch (\Throwable $e) {
    // Never expose error details to the caller
    error_log('api/payment/webhook fatal: ' . $e->getMessage());
}

echo json_encode($responseBody);

