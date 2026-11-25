<?php
declare(strict_types=1);
// api/payment/methods.php — Return enabled payment methods (authenticated customer)

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/payment-core/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Guest access allowed — just returns available methods
try {
    $stmt = $pdo->query(
        "SELECT code, label, description, icon, display_order
           FROM payment_methods
          WHERE is_enabled = 1
          ORDER BY display_order ASC"
    );
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'methods' => $methods,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load payment methods.']);
}
