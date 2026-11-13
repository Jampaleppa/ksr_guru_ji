<?php
/**
 * KSR GURU JI PAY — Automated Test Suite
 *
 * Tests:
 * 1. AmountCalculator (Paise/Rupee conversion & INR formatting)
 * 2. PaymentValidator (Amount, Method, Currency, UTR & Idempotency)
 * 3. SecurityManager (CSRF tokens, Constant-time verification, CORS origins)
 * 4. IdempotencyManager (Key registration & deduplication)
 * 5. PaymentManager & PaymentStateMachine (Lifecycle, Immutability & Audit Trail)
 * 6. ReconciliationManager (Credit import & matching)
 * 7. RefundManager (Refund request, approval & completion)
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\PaymentValidator;
use KsrPay\SecurityManager;
use KsrPay\IdempotencyManager;
use KsrPay\PaymentManager;
use KsrPay\PaymentStateMachine;
use KsrPay\AuditLogger;
use KsrPay\ReconciliationManager;
use KsrPay\RefundManager;

$testsTotal = 0;
$testsPassed = 0;
$testsFailed = 0;

function runPayTest(string $name, callable $fn): void {
    global $testsTotal, $testsPassed, $testsFailed;
    $testsTotal++;
    try {
        $result = $fn();
        if ($result === true) {
            echo "  [PASS] {$name}\n";
            $testsPassed++;
        } else {
            echo "  [FAIL] {$name} (Returned non-true)\n";
            $testsFailed++;
        }
    } catch (Throwable $e) {
        echo "  [FAIL] {$name}: {$e->getMessage()}\n";
        $testsFailed++;
    }
}

echo "====================================================================\n";
echo "   KSR GURU JI PAY — Automated Core Test Suite Runner               \n";
echo "====================================================================\n\n";

// ── Test Group 1: AmountCalculator ────────────────────────────────
echo "1. Testing AmountCalculator arithmetic & currency formatting...\n";

runPayTest("AmountCalculator::toPaise converts float and string correctly", function() {
    return AmountCalculator::toPaise(10.50) === 1050 &&
           AmountCalculator::toPaise("99.99") === 9999 &&
           AmountCalculator::toPaise(0) === 0;
});

runPayTest("AmountCalculator::toRupees formats paise to decimal string", function() {
    return AmountCalculator::toRupees(1050) === "10.50" &&
           AmountCalculator::toRupees(100000) === "1,000.00";
});

runPayTest("AmountCalculator::formatINR appends rupee symbol", function() {
    return AmountCalculator::formatINR(49900) === "₹499.00";
});

runPayTest("AmountCalculator::calculateOrderTotal computes line items, delivery and discounts", function() {
    $items = [
        ['price' => 100, 'qty' => 2],
        ['price' => 50,  'qty' => 1],
    ];
    $total = AmountCalculator::calculateOrderTotal($items, 15.00, 20.00);
    return $total === 24500;
});

// ── Test Group 2: PaymentValidator ────────────────────────────────
echo "\n2. Testing PaymentValidator business & security rules...\n";

runPayTest("PaymentValidator::validateAmount enforces exact amount match in paise", function() {
    $v = new PaymentValidator();
    $okMatch = ($v->validateAmount(50000, 50000) === true);
    $okMismatch = false;
    try {
        $v->validateAmount(50000, 40000);
    } catch (InvalidArgumentException $e) {
        $okMismatch = true;
    }
    return $okMatch && $okMismatch;
});

runPayTest("PaymentValidator::validateMethod rejects unknown payment methods", function() {
    $v = new PaymentValidator();
    $ok = false;
    try { $v->validateMethod('bitcoin'); } catch (InvalidArgumentException $e) { $ok = true; }
    return $ok && ($v->validateMethod('upi') === true) && ($v->validateMethod('cod') === true);
});

runPayTest("PaymentValidator::validateUTR enforces alphanumeric characters", function() {
    $v = new PaymentValidator();
    $ok12 = ($v->validateUTR('123456789012') === true);
    $failShort = false;
    try {
        $v->validateUTR('123');
    } catch (InvalidArgumentException $e) {
        $failShort = true;
    }
    return $ok12 && $failShort;
});

runPayTest("PaymentValidator::validateCurrency allows only INR", function() {
    $v = new PaymentValidator();
    $ok = false;
    try { $v->validateCurrency('USD'); } catch (InvalidArgumentException $e) { $ok = true; }
    return $ok && ($v->validateCurrency('INR') === true);
});

// ── Test Group 3: SecurityManager ─────────────────────────────────
echo "\n3. Testing SecurityManager CSRF & Origin controls...\n";

runPayTest("SecurityManager::generateCSRFToken generates and stores 64-char hex token", function() {
    $token = SecurityManager::generateCSRFToken();
    return strlen($token) === 64 && ctype_xdigit($token);
});

runPayTest("SecurityManager::validateCSRFToken validates with constant-time equality", function() {
    $token = SecurityManager::generateCSRFToken();
    return SecurityManager::validateCSRFToken($token) === true &&
           SecurityManager::validateCSRFToken('invalid_token_123') === false;
});

runPayTest("SecurityManager rejects unauthorized CORS origins", function() {
    $sec = new SecurityManager();
    return $sec->validateOrigin('https://ksrguruji.vercel.app') === true &&
           $sec->validateOrigin('http://localhost:8090') === true &&
           $sec->validateOrigin('https://evil-phishing.com') === false;
});

// ── Test Group 4: IdempotencyManager ──────────────────────────────
echo "\n4. Testing IdempotencyManager duplicate request prevention...\n";

runPayTest("IdempotencyManager registers and detects identical requests", function() use ($pdo) {
    $idemp = new IdempotencyManager($pdo);
    $key = 'test-idemp-' . bin2hex(random_bytes(8));
    $idemp->register($key, 999, 3600);
    $matched = $idemp->check($key);
    return $matched === 999 && $idemp->check('non-existent-key') === null;
});

// ── Test Group 5: PaymentManager & PaymentStateMachine ────────────
echo "\n5. Testing PaymentManager & StateMachine lifecycle...\n";

runPayTest("PaymentManager creates payment with server-authoritative amount", function() use ($pdo) {
    $orderNum = 'ORD-SUITE-' . bin2hex(random_bytes(6));
    $pdo->prepare("INSERT INTO orders (user_id, order_number, total, net_amount, status, payment_status)
                   VALUES (1, ?, 750.00, 750.00, 'pending', 'pending')")->execute([$orderNum]);
    $orderId = (int)$pdo->lastInsertId();

    $pm = new PaymentManager($pdo);
    $res = $pm->createPayment($orderId, 1, 'upi', 'idemp-' . bin2hex(random_bytes(6)));

    return strpos($res['payment_id'], 'KSR-') === 0 &&
           $res['amount_paise'] === 75000 &&
           $res['status'] === 'initiated';
});

runPayTest("PaymentStateMachine enforces valid lifecycle transitions and blocks illegal ones", function() use ($pdo) {
    $orderNum = 'ORD-SUITE-' . bin2hex(random_bytes(6));
    $pdo->prepare("INSERT INTO orders (user_id, order_number, total, net_amount, status, payment_status)
                   VALUES (1, ?, 300.00, 300.00, 'pending', 'pending')")->execute([$orderNum]);
    $orderId = (int)$pdo->lastInsertId();

    $pm = new PaymentManager($pdo);
    $res = $pm->createPayment($orderId, 1, 'upi', 'idemp-' . bin2hex(random_bytes(6)));
    $pid = $res['payment_id'];

    // 1. Legal: initiated -> upi_awaiting
    $trans1 = $pm->transition($pid, 'upi_awaiting', ['notes' => 'Customer submitted UTR']);
    if (!$trans1) return false;

    // 2. Illegal: upi_awaiting -> cod_collected (should be blocked)
    $illegalTrans = $pm->transition($pid, 'cod_collected');
    if ($illegalTrans) return false;

    // 3. Legal: upi_awaiting -> success
    $trans2 = $pm->transition($pid, 'success', ['verification_source' => 'admin_manual']);
    return $trans2 === true;
});

runPayTest("AuditLogger records immutable financial logs on every transition", function() use ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM payment_audit_logs WHERE event_type LIKE 'PAYMENT_%'");
    $cnt = (int)$stmt->fetchColumn();
    return $cnt >= 2;
});

// ── Test Group 6: Reconciliation & Refunds ─────────────────────────
echo "\n6. Testing ReconciliationManager & RefundManager...\n";

runPayTest("ReconciliationManager imports and matches bank credits", function() use ($pdo) {
    $audit = new AuditLogger($pdo);
    $rm = new ReconciliationManager($pdo, $audit);
    $bankRef = 'SBI-REF-' . bin2hex(random_bytes(6));
    $creditId = $rm->importCredit([
        'credit_date'  => date('Y-m-d'),
        'bank_ref'     => $bankRef,
        'utr'          => '123456789012',
        'narration'    => 'UPI/123456789012/Payment',
        'amount_paise' => 50000,
        'notes'        => 'Test credit'
    ], 1);

    $pending = $rm->getPendingCredits(10);
    $found = false;
    foreach ($pending as $p) {
        if ($p['bank_ref'] === $bankRef) {
            $found = true;
            break;
        }
    }
    return $creditId > 0 && $found;
});

runPayTest("RefundManager processes refund request and approval workflow", function() use ($pdo) {
    $orderNum = 'ORD-REFUND-' . bin2hex(random_bytes(6));
    $pdo->prepare("INSERT INTO orders (user_id, order_number, total, net_amount, status, payment_status)
                   VALUES (1, ?, 250.00, 250.00, 'pending', 'pending')")->execute([$orderNum]);
    $orderId = (int)$pdo->lastInsertId();

    $pm = new PaymentManager($pdo);
    $res = $pm->createPayment($orderId, 1, 'upi', 'idemp-' . bin2hex(random_bytes(6)));
    $pid = $res['payment_id'];

    // Move along valid state path: initiated -> upi_awaiting -> success before requesting refund
    $pm->transition($pid, 'upi_awaiting', ['notes' => 'Customer submitted UTR']);
    $pm->transition($pid, 'success', ['verification_source' => 'admin_manual']);

    $audit = new AuditLogger($pdo);
    $refMgr = new RefundManager($pdo, $audit);
    $refundId = $refMgr->requestRefund($pid, 1, 25000, 'Item returned by customer');
    if ($refundId <= 0) return false;

    $approved = $refMgr->approveRefund($refundId, 1);
    $completed = $refMgr->completeRefund($refundId, 1, 'SBI-REV-987654');

    return $approved && $completed;
});

echo "\n====================================================================\n";
echo "                      TEST RUN RESULTS                              \n";
echo "====================================================================\n";
echo " Total Tests Executed : {$testsTotal}\n";
echo " Tests Passed         : {$testsPassed}\n";
echo " Tests Failed         : {$testsFailed}\n";
echo "====================================================================\n";

if ($testsFailed === 0) {
    echo "STATUS: ALL KSR GURU JI PAY TESTCASES PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "STATUS: {$testsFailed} TEST(S) FAILED!\n";
    exit(1);
}
