<?php
/**
 * tests/run_all_tests.php
 * KSR GURU JI - Master Test Suite Runner
 *
 * Runs all 6 test suites across:
 *  1. tests/payment_pages_debug_test.php (Payment core, methods, checkout, API)
 *  2. tests/vegetables_fruits_test.php   (Daily market price, units, variants, +₹3 rule)
 *  3. tests/payment_test.php             (Payment logic, arithmetic, security)
 *  4. tests/integration_test.php         (Multi-category search, suggestions, sourcing)
 *  5. tests/e2e_checkout_delivery_test.php (Checkout, delivery assignment, tracking)
 *  6. tests/security_test.php            (Credential isolation, token leak prevention)
 */

declare(strict_types=1);

$phpBinary = PHP_BINARY ?: 'php';
$testsDir = __DIR__;

$suites = [
    [
        'name' => 'Payment Pages, Methods & Core Debug Suite',
        'file' => $testsDir . '/payment_pages_debug_test.php',
    ],
    [
        'name' => 'Vegetables & Fruits Pricing & Schema Suite',
        'file' => $testsDir . '/vegetables_fruits_test.php',
    ],
    [
        'name' => 'Payment Core Logic & Arithmetic Suite',
        'file' => $testsDir . '/payment_test.php',
    ],
    [
        'name' => 'Multi-Module Integration Suite',
        'file' => $testsDir . '/integration_test.php',
    ],
    [
        'name' => 'E2E Checkout & Delivery Workflow Suite',
        'file' => $testsDir . '/e2e_checkout_delivery_test.php',
    ],
    [
        'name' => 'Security, Gateway & Secrets Audit Suite',
        'file' => $testsDir . '/security_test.php',
    ],
    [
        'name' => 'Hardening, Checkout, Coupons & Reservations Suite',
        'file' => $testsDir . '/hardening_features_test.php',
    ],
];

echo "====================================================================\n";
echo "       KSR GURU JI — MASTER AUTOMATED TEST SUITE RUNNER             \n";
echo "====================================================================\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP Binary: " . $phpBinary . "\n\n";

$overallStart = microtime(true);
$totalSuites = count($suites);
$passedSuites = 0;
$failedSuites = 0;
$totalAssertions = 0;
$passedAssertions = 0;
$suiteResults = [];

foreach ($suites as $index => $suite) {
    $num = $index + 1;
    $suiteName = $suite['name'];
    $suiteFile = $suite['file'];

    echo "--------------------------------------------------------------------\n";
    echo "[Suite {$num}/{$totalSuites}] {$suiteName}\n";
    echo "Running: " . basename($suiteFile) . "...\n";
    echo "--------------------------------------------------------------------\n";

    $startTime = microtime(true);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($suiteFile);
    $proc = proc_open($cmd, $descriptors, $pipes, dirname($testsDir));

    if (!is_resource($proc)) {
        echo "  [ERROR] Failed to spawn process for: {$suiteFile}\n\n";
        $failedSuites++;
        $suiteResults[] = ['name' => $suiteName, 'passed' => false, 'time' => 0, 'assertions' => 0];
        continue;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($proc);
    $duration = round(microtime(true) - $startTime, 2);

    // Count pass/fail lines from stdout
    $passCount = preg_match_all('/^\s*\[PASS\]/m', $stdout);
    $failCount = preg_match_all('/^\s*\[FAIL\]/m', $stdout);
    $assertions = $passCount + $failCount;

    $totalAssertions += $assertions;
    $passedAssertions += $passCount;

    if ($exitCode === 0 && $failCount === 0) {
        $passedSuites++;
        echo "  -> RESULT: PASSED ({$passCount}/{$assertions} assertions in {$duration}s)\n\n";
        $suiteResults[] = ['name' => $suiteName, 'passed' => true, 'time' => $duration, 'assertions' => $passCount];
    } else {
        $failedSuites++;
        echo "  -> RESULT: FAILED ({$failCount} failed, exit code {$exitCode} in {$duration}s)\n";
        if ($stderr) {
            echo "  STDERR: " . trim($stderr) . "\n";
        }
        echo "\n";
        $suiteResults[] = ['name' => $suiteName, 'passed' => false, 'time' => $duration, 'assertions' => $assertions];
    }
}

$overallDuration = round(microtime(true) - $overallStart, 2);

echo "====================================================================\n";
echo "                      MASTER TEST SUMMARY                           \n";
echo "====================================================================\n";
foreach ($suiteResults as $res) {
    $status = $res['passed'] ? '[PASS]' : '[FAIL]';
    printf(" %-6s %-48s (%d tests, %.2fs)\n", $status, $res['name'], $res['assertions'], $res['time']);
}
echo "--------------------------------------------------------------------\n";
echo " Total Suites Run       : {$totalSuites}\n";
echo " Suites Passed          : {$passedSuites}\n";
echo " Suites Failed          : {$failedSuites}\n";
echo " Total Assertions       : {$totalAssertions}\n";
echo " Assertions Passed      : {$passedAssertions}\n";
echo " Total Execution Time   : {$overallDuration} seconds\n";
echo "====================================================================\n";

if ($failedSuites === 0) {
    echo " STATUS: ALL TEST SUITES PASSED WITH 100% SUCCESS!\n";
    exit(0);
} else {
    echo " STATUS: {$failedSuites} TEST SUITE(S) FAILED.\n";
    exit(1);
}
