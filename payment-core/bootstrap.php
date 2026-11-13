<?php
/**
 * payment-core/bootstrap.php
 * Include this at the top of every payment page / API endpoint.
 * Provides: $pdo, all KsrPay classes, and ksrPaymentStates() etc.
 */
declare(strict_types=1);

// Locate the project root regardless of where this file is included from
$_ksrRoot = dirname(__DIR__);

// Config + DB
if (file_exists($_ksrRoot . '/config.php')) {
    require_once $_ksrRoot . '/config.php';
}
require_once $_ksrRoot . '/includes/db.php';

// Central state machine (functions, not class)
require_once $_ksrRoot . '/payment_state.php';

// KsrPay classes
require_once __DIR__ . '/AuditLogger.php';
require_once __DIR__ . '/AmountCalculator.php';
require_once __DIR__ . '/PaymentValidator.php';
require_once __DIR__ . '/IdempotencyManager.php';
require_once __DIR__ . '/TransactionManager.php';
require_once __DIR__ . '/PaymentStateMachine.php';
require_once __DIR__ . '/ReconciliationManager.php';
require_once __DIR__ . '/RefundManager.php';
require_once __DIR__ . '/SecurityManager.php';
require_once __DIR__ . '/PaymentManager.php';

// Obtain PDO
$pdo = getDBConnection();

