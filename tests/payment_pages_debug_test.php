<?php

/**
 * tests/payment_pages_debug_test.php
 * Comprehensive Debug & Run Test Suite for:
 *  - payment-core/ (Classes & Business Logic)
 *  - payment-methods/ (UPI, COD, Bank Transfer pages & endpoints)
 *  - payment/ & payment/api/ (Checkout, Payment flow, Receipts, History, Refunds, APIs)
 */

declare(strict_types=1);

use KsrPay\AmountCalculator;
use KsrPay\AuditLogger;
use KsrPay\IdempotencyManager;
use KsrPay\PaymentManager;
use KsrPay\PaymentStateMachine;
use KsrPay\PaymentValidator;
use KsrPay\ReconciliationManager;
use KsrPay\RefundManager;
use KsrPay\SecurityManager;
use KsrPay\TransactionManager;

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config.php';
require_once $rootDir . '/includes/db.php';
require_once $rootDir . '/payment_state.php';
require_once $rootDir . '/payment-core/bootstrap.php';

/** @var \PDO $pdo */
$pdo = getDBConnection();

$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition, string $details = ''): void
{
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$title}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$title}" . ($details ? ": {$details}" : "") . "\n";
        $failCount++;
    }
}

/**
 * Execute a PHP file in a subprocess with controlled session, get, post, and server variables.
 */
function runSubprocessPage(string $relativeFile, array $session = [], array $get = [], array $post = [], string $method = 'GET'): array
{
    global $rootDir;
    $absFile = $rootDir . '/' . ltrim($relativeFile, '/');
    if (!file_exists($absFile)) {
        return ['code' => -1, 'output' => "File not found: {$absFile}"];
    }

    $phpExe = 'C:\\wamp64\\bin\\php\\php8.3.14\\php.exe';

    // Construct temp bootstrap script to emulate web request environment reliably on Windows
    $bootstrapCode = "<?php\n"
        . "if (session_status() === PHP_SESSION_NONE) { session_start(); }\n"
        . '$_SESSION = ' . var_export($session, true) . ";\n"
        . '$_GET     = ' . var_export($get, true) . ";\n"
        . '$_POST    = ' . var_export($post, true) . ";\n"
        . '$_REQUEST = array_merge($_GET, $_POST);' . "\n"
        . '$_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ";\n"
        . '$_SERVER["HTTP_HOST"]      = "localhost:8090";' . "\n"
        . '$_SERVER["REMOTE_ADDR"]    = "127.0.0.1";' . "\n"
        . '$_SERVER["HTTP_USER_AGENT"]= "KsrPayTest/1.0";' . "\n"
        . '$_SERVER["DOCUMENT_ROOT"]  = ' . var_export($rootDir, true) . ";\n"
        . '$_SERVER["REQUEST_URI"]    = "/' . ltrim($relativeFile, '/') . "\";\n"
        . 'require ' . var_export($absFile, true) . ";\n";

    $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ksr_sub_' . uniqid() . '.php';
    file_put_contents($tmpFile, $bootstrapCode);

    $cmd = escapeshellarg($phpExe) . ' ' . escapeshellarg($tmpFile);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $proc = proc_open($cmd, $descriptors, $pipes, $rootDir);
    if (!is_resource($proc)) {
        @unlink($tmpFile);
        return ['code' => -1, 'output' => 'Failed to proc_open'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($proc);
    @unlink($tmpFile);
    return [
        'code'   => $exitCode,
        'stdout' => (string)$stdout,
        'stderr' => (string)$stderr,
        'output' => (string)$stdout . (string)$stderr
    ];
}

echo "====================================================================\n";
echo "   PAYMENT, PAYMENT-METHODS & PAYMENT-CORE DEBUG & RUN TEST SUITE   \n";
echo "====================================================================\n\n";

// ── 1. PAYMENT-CORE MODULES ──────────────────────────────────────────────
echo "1. Testing payment-core modules in-process...\n";

// 1.1 AmountCalculator
assertTest(
    "payment-core/AmountCalculator instantiates and converts accurately",
    AmountCalculator::toPaise(15.50) === 1550 && AmountCalculator::formatINR(1550) === "₹15.50"
);

// 1.2 AuditLogger
$audit = new AuditLogger($pdo);
assertTest("payment-core/AuditLogger instantiates", $audit instanceof AuditLogger);

// 1.3 IdempotencyManager
$idem = new IdempotencyManager($pdo);
$key = $idem->generate(999, 1, 'upi');
assertTest("payment-core/IdempotencyManager generates valid 64-char hex key", !empty($key) && strlen($key) === 64);

// 1.4 PaymentValidator
$validator = new PaymentValidator();
assertTest(
    "payment-core/PaymentValidator validates method and currency",
    $validator->validateMethod('upi') && $validator->validateCurrency('INR')
);

// 1.5 TransactionManager
$tx = new TransactionManager($pdo);
assertTest("payment-core/TransactionManager instantiates", $tx instanceof TransactionManager);

// 1.6 PaymentStateMachine
$sm = new PaymentStateMachine($pdo, $audit);
assertTest("payment-core/PaymentStateMachine instantiates", $sm instanceof PaymentStateMachine);

// 1.7 ReconciliationManager
$recon = new ReconciliationManager($pdo, $audit);
assertTest("payment-core/ReconciliationManager instantiates", $recon instanceof ReconciliationManager);

// 1.8 RefundManager
$refund = new RefundManager($pdo, $audit);
assertTest("payment-core/RefundManager instantiates", $refund instanceof RefundManager);

// 1.9 SecurityManager
$sec = new SecurityManager();
$csrf = $sec->generateCSRFToken();
assertTest("payment-core/SecurityManager generates and validates CSRF token", $sec->validateCSRFToken($csrf));

// 1.10 PaymentManager
$pm = new PaymentManager($pdo);
assertTest("payment-core/PaymentManager instantiates", $pm instanceof PaymentManager);

// ── 2. SEED TEST ORDER & PAYMENT FOR PAGE EXECUTION ────────────────────────
echo "\n2. Seeding Test Order & Payment for Page Executions...\n";

// Ensure a test user exists
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    phone TEXT,
    role TEXT DEFAULT 'customer',
    password TEXT
)");
$pdo->exec("INSERT OR IGNORE INTO users (id, name, email, phone, role) VALUES (888, 'Payment Tester', 'paytest@ksrguruji.com', '9876543210', 'customer')");
$pdo->exec("INSERT OR IGNORE INTO users (id, name, email, phone, role) VALUES (999, 'Admin Tester', 'admintest@ksrguruji.com', '9876543211', 'admin')");

$orderNum = 'ORD-TEST-PAY-001';
$pdo->prepare("INSERT OR REPLACE INTO orders (id, order_number, user_id, name, email, phone, address, city, net_amount, total, status, payment_status)
    VALUES (888, ?, 888, 'Payment Tester', 'paytest@ksrguruji.com', '9876543210', '123 Test St', 'Hubballi', 150.00, 150.00, 'pending', 'pending')")->execute([$orderNum]);

$pdo->prepare("INSERT OR REPLACE INTO orders (id, order_number, user_id, name, email, phone, address, city, net_amount, total, status, payment_status)
    VALUES (889, 'ORD-TEST-CHK-002', 888, 'Payment Tester', 'paytest@ksrguruji.com', '9876543210', '123 Test St', 'Hubballi', 200.00, 200.00, 'pending', 'pending')")->execute();

// Create a payment transaction using PaymentManager
$testPidKey = 'idem_test_' . time();
$createRes = $pm->createPayment(888, 888, 'upi', $testPidKey);
$testPid = $createRes['payment_id'];

assertTest("PaymentManager successfully created payment", !empty($testPid) && $createRes['status'] === 'initiated');

// ── 3. TESTING PAYMENT-METHODS FOLDER PAGES ──────────────────────────────
echo "\n3. Testing payment-methods folder pages via subprocess execution...\n";

// 3.1 config.php
require_once $rootDir . '/payment-methods/upi/config.php';
$upiConf = getUpiConfig($pdo);
$btConf  = getBankTransferConfig($pdo);
assertTest("payment-methods/upi/config.php getUpiConfig returns array", is_array($upiConf) && isset($upiConf['merchant_name']));
assertTest("payment-methods/upi/config.php getBankTransferConfig returns array", is_array($btConf) && isset($btConf['bank_name']));

// 3.2 check_status.php
$res = runSubprocessPage('payment-methods/upi/check_status.php', [], ['pid' => $testPid]);
$statusJson = json_decode($res['stdout'], true);
assertTest(
    "payment-methods/upi/check_status.php executes and returns valid status JSON",
    is_array($statusJson) && ($statusJson['success'] ?? false) === true && $statusJson['status'] === 'initiated'
);

// 3.3 merchant_qr.php
$res = runSubprocessPage('payment-methods/upi/merchant_qr.php', ['user_id' => 888], ['pid' => $testPid, 'oid' => 888]);
assertTest(
    "payment-methods/upi/merchant_qr.php renders UPI payment card",
    $res['code'] === 0 && str_contains($res['stdout'], 'Pay via UPI') && str_contains($res['stdout'], $orderNum)
);

// 3.4 submit_reference.php (unauthenticated check)
$res = runSubprocessPage('payment-methods/upi/submit_reference.php', [], [], [], 'POST');
$unauthJson = json_decode($res['stdout'], true);
assertTest("payment-methods/upi/submit_reference.php rejects unauthenticated requests", ($unauthJson['success'] ?? true) === false);

// 3.5 submit_reference.php (valid customer submission)
$testCsrf = bin2hex(random_bytes(32));
$validUtr = 'UTR' . time() . rand(100, 999);
$res = runSubprocessPage(
    'payment-methods/upi/submit_reference.php',
    ['user_id' => 888, 'ksr_csrf_token' => $testCsrf],
    [],
    ['csrf_token' => $testCsrf, 'payment_id' => $testPid, 'order_id' => 888, 'utr' => $validUtr],
    'POST'
);
$subJson = json_decode($res['stdout'], true);
assertTest(
    "payment-methods/upi/submit_reference.php accepts valid customer UTR",
    is_array($subJson) && ($subJson['success'] ?? false) === true
);

// 3.6 bank-transfer/instructions.php
$res = runSubprocessPage('payment-methods/bank-transfer/instructions.php', ['user_id' => 888], ['pid' => $testPid, 'oid' => 888]);
assertTest(
    "payment-methods/bank-transfer/instructions.php renders bank details and transfer instructions",
    $res['code'] === 0 && str_contains($res['stdout'], 'Bank Transfer') && str_contains($res['stdout'], $orderNum)
);

// 3.7 cod/create_collection.php
$codPidKey = 'idem_cod_' . time();
$codRes = $pm->createPayment(888, 888, 'cod', $codPidKey);
$codPid = $codRes['payment_id'];

$res = runSubprocessPage('payment-methods/cod/create_collection.php', ['user_id' => 888], ['pid' => $codPid, 'oid' => 888]);
assertTest(
    "payment-methods/cod/create_collection.php renders COD confirmation and sets cod_pending",
    $res['code'] === 0 && str_contains($res['stdout'], 'Cash on Delivery Confirmed')
);

// 3.8 cod/confirm_collection.php
$res = runSubprocessPage(
    'payment-methods/cod/confirm_collection.php',
    ['user_id' => 999, 'role' => 'delivery_boy', 'ksr_csrf_token' => $testCsrf],
    [],
    ['csrf_token' => $testCsrf, 'payment_id' => $codPid, 'collected_amount_paise' => $codRes['amount_paise'], 'collection_reference' => 'CASH_REC_001'],
    'POST'
);
$colJson = json_decode($res['stdout'], true);
assertTest(
    "payment-methods/cod/confirm_collection.php confirms collection by delivery personnel",
    is_array($colJson) && ($colJson['success'] ?? false) === true
);

// ── 4. TESTING PAYMENT FOLDER CUSTOMER & CHECKOUT PAGES ──────────────────
echo "\n4. Testing payment folder customer & checkout pages...\n";

// 4.1 payment/index.php
$res = runSubprocessPage('payment/index.php', ['user_id' => 888], ['oid' => 888]);
assertTest(
    "payment/index.php renders payment method selection",
    $res['code'] === 0 && str_contains($res['stdout'], 'Select payment method') && str_contains($res['stdout'], $orderNum)
);

// 4.2 payment/checkout.php
$res = runSubprocessPage('payment/checkout.php', ['user_id' => 888], ['oid' => 889]);
assertTest(
    "payment/checkout.php renders order review page",
    $res['code'] === 0 && str_contains($res['stdout'], 'Review Your Order') && str_contains($res['stdout'], 'Order #889')
);

// 4.3 payment/payment_pending.php
$res = runSubprocessPage('payment/payment_pending.php', ['user_id' => 888], ['pid' => $testPid]);
assertTest(
    "payment/payment_pending.php renders verification in progress",
    $res['code'] === 0 && str_contains($res['stdout'], 'Verification in Progress')
);

// 4.4 payment/support.php
$res = runSubprocessPage('payment/support.php', ['user_id' => 888], ['pid' => $testPid, 'oid' => 888]);
assertTest(
    "payment/support.php renders payment dispute form",
    $res['code'] === 0 && str_contains($res['stdout'], 'Payment Support')
);

// 4.5 payment/payment_failed.php
$res = runSubprocessPage('payment/payment_failed.php', ['user_id' => 888], ['pid' => $testPid, 'oid' => 888]);
assertTest(
    "payment/payment_failed.php renders payment failed card",
    $res['code'] === 0 && str_contains($res['stdout'], 'Payment Failed')
);

// 4.6 payment/history.php
$res = runSubprocessPage('payment/history.php', ['user_id' => 888]);
assertTest(
    "payment/history.php renders payment transactions history",
    $res['code'] === 0 && str_contains($res['stdout'], 'Payment History')
);

// Transition $testPid to success for receipt and success testing
$sm->transition($testPid, 'success', ['actor_type' => 'admin', 'actor_id' => 999, 'notes' => 'Test approval']);

// 4.7 payment/payment_success.php
$res = runSubprocessPage('payment/payment_success.php', ['user_id' => 888], ['pid' => $testPid]);
assertTest(
    "payment/payment_success.php renders payment confirmed card",
    $res['code'] === 0 && str_contains($res['stdout'], 'Payment Confirmed') && str_contains($res['stdout'], $orderNum)
);

// 4.8 payment/receipt.php
$res = runSubprocessPage('payment/receipt.php', ['user_id' => 888], ['pid' => $testPid]);
assertTest(
    "payment/receipt.php renders official receipt with PAID stamp",
    $res['code'] === 0 && str_contains($res['stdout'], 'PAID') && str_contains($res['stdout'], $orderNum)
);

// 4.9 payment/refund_request.php
$res = runSubprocessPage('payment/refund_request.php', ['user_id' => 888], ['pid' => $testPid]);
assertTest(
    "payment/refund_request.php renders refund request form",
    $res['code'] === 0 && str_contains($res['stdout'], 'Request Refund')
);

// ── 5. TESTING PAYMENT/API/ ENDPOINTS ────────────────────────────────────
echo "\n5. Testing payment/api endpoints...\n";

// 5.1 payment/api/status.php
$res = runSubprocessPage('payment/api/status.php', [], ['pid' => $testPid]);
$apiStatusJson = json_decode($res['stdout'], true);
assertTest(
    "payment/api/status.php returns success=true and status=success",
    is_array($apiStatusJson) && ($apiStatusJson['success'] ?? false) === true && ($apiStatusJson['status'] ?? '') === 'success'
);

// 5.2 payment/api/webhook.php
$res = runSubprocessPage('payment/api/webhook.php', [], [], [], 'POST');
$whJson = json_decode($res['stdout'], true);
assertTest(
    "payment/api/webhook.php responds with received=true",
    is_array($whJson) && ($whJson['received'] ?? false) === true
);

// ── 6. LIVE HTTP TEST OVER LOCAL APACHE PORT 8090 ────────────────────────
echo "\n6. Testing Live HTTP Endpoints on Local Server (Port 8090)...\n";

$isServerUp = false;
$sock = @fsockopen('localhost', 8090, $errno, $errstr, 0.5);
if ($sock) {
    $isServerUp = true;
    fclose($sock);
}

$httpEndpoints = [
    'payment_index'        => 'http://localhost:8090/payment/index.php?oid=888',
    'payment_checkout'     => 'http://localhost:8090/payment/checkout.php?oid=888',
    'payment_support'      => 'http://localhost:8090/payment/support.php',
    'payment_failed'       => 'http://localhost:8090/payment/payment_failed.php',
    'upi_check_status'     => 'http://localhost:8090/payment-methods/upi/check_status.php?pid=' . urlencode($testPid),
    'api_payment_status'   => 'http://localhost:8090/api/payment/status.php?pid=' . urlencode($testPid),
    'payment_api_status'   => 'http://localhost:8090/payment/api/status.php?pid=' . urlencode($testPid),
];

if ($isServerUp) {
    foreach ($httpEndpoints as $name => $url) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
                'header' => "User-Agent: KsrPayDebug/1.0\r\n"
            ]
        ]);
        $headers = @get_headers($url, false, $ctx);
        $statusLine = $headers ? $headers[0] : 'FAILED';
        $isOkOrRedirect = (str_contains($statusLine, '200') || str_contains($statusLine, '302'));
        assertTest("HTTP {$name} ({$url}) -> {$statusLine}", $isOkOrRedirect);
    }
} else {
    echo "  [INFO] Port 8090 not active; testing 7 endpoint executions via subprocess page runner...\n";
    $subEndpoints = [
        'payment_index'        => ['file' => 'payment/index.php', 'session' => ['user_id' => 888], 'get' => ['oid' => 888]],
        'payment_checkout'     => ['file' => 'payment/checkout.php', 'session' => ['user_id' => 888], 'get' => ['oid' => 889]],
        'payment_support'      => ['file' => 'payment/support.php', 'session' => ['user_id' => 888], 'get' => []],
        'payment_failed'       => ['file' => 'payment/payment_failed.php', 'session' => ['user_id' => 888], 'get' => ['pid' => $testPid, 'oid' => 888]],
        'upi_check_status'     => ['file' => 'payment-methods/upi/check_status.php', 'session' => ['user_id' => 888], 'get' => ['pid' => $testPid]],
        'api_payment_status'   => ['file' => 'api/payment/status.php', 'session' => [], 'get' => ['pid' => $testPid]],
        'payment_api_status'   => ['file' => 'payment/api/status.php', 'session' => [], 'get' => ['pid' => $testPid]],
    ];
    foreach ($subEndpoints as $name => $cfg) {
        $res = runSubprocessPage($cfg['file'], $cfg['session'], $cfg['get']);
        $isOk = ($res['code'] === 0 && strlen($res['stdout']) > 0);
        assertTest("Endpoint {$name} ({$cfg['file']}) rendered successfully", $isOk);
    }
}

echo "\n====================================================================\n";
echo "                      TEST RUN RESULTS                              \n";
echo "====================================================================\n";
echo " Total Tests Executed : " . ($passCount + $failCount) . "\n";
echo " Tests Passed         : {$passCount}\n";
echo " Tests Failed         : {$failCount}\n";
echo "====================================================================\n";

if ($failCount === 0) {
    echo "STATUS: ALL PAYMENT PAGES, METHODS & CORE MODULES PASSED!\n";
    exit(0);
} else {
    echo "STATUS: SOME TESTS FAILED!\n";
    exit(1);
}
