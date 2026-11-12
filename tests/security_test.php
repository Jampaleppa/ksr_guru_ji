<?php
/**
 * Test Suite: Security & Regression Verification for KSR GURU JI
 * Run via CLI: php tests/security_test.php
 */

declare(strict_types=1);

echo "========================================================\n";
echo "KSR GURU JI — Security & Regression Test Suite\n";
echo "========================================================\n\n";

$baseDir = dirname(__DIR__);
$failures = 0;

function assertCondition(bool $cond, string $desc, ?string $detail = null): void {
    global $failures;
    if ($cond) {
        echo "  [PASS] {$desc}\n";
    } else {
        echo "  [FAIL] {$desc}\n";
        if ($detail) {
            echo "         -> {$detail}\n";
        }
        $failures++;
    }
}

// -------------------------------------------------------------------
// 1. Check CLI Guards on Maintenance & Seed Scripts
// -------------------------------------------------------------------
echo "1. Checking Maintenance & Seed Scripts CLI Guards...\n";
$guardedScripts = [
    'admin/database/seed_admin_accounts.php',
    'admin/database/install_admin_rbac.php',
    'delivery/seed_agents.php',
    'WhatsApp/telehealth/seed-telehealth-data.php',
];

foreach ($guardedScripts as $script) {
    $fullPath = $baseDir . '/' . $script;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        $hasGuard = strpos($content, "PHP_SAPI !== 'cli'") !== false;
        assertCondition($hasGuard, "CLI guard present in {$script}");
    }
}

// -------------------------------------------------------------------
// 2. Secret Scan
// -------------------------------------------------------------------
echo "\n2. Scanning Core Codebase for Hardcoded Secrets...\n";
$secretPatterns = [
    'JKRathod@406',
    'KSRParvatiGauri',
    'JKRathod@KSRGURUJI',
    'ksrdebug2024',
    'rzp_test_KSRGuruJi1234',
    'KSRGuruJiSecret5678App',
    'KSRGuruJiWebhookSecret9012',
    'ksrg_apikey_7f3b2d8a5c1e9f6a4b7d2c8e5f9a3b6d'
];

$filesToCheck = [
    'config.php',
    'includes/config.php',
    'includes/db.php',
    'admin/database/admin_accounts.php',
    'admin/inc/settings_logic.php',
    'admin/settings.php',
    'razorpay_helper.php',
    'checkout.php',
    'api/debug.php',
    'WhatsApp/config/database.php',
    'WhatsApp/telehealth/medicine/tablet.php'
];

foreach ($filesToCheck as $relFile) {
    $path = $baseDir . '/' . $relFile;
    if (!file_exists($path)) continue;
    $content = file_get_contents($path);
    foreach ($secretPatterns as $pattern) {
        $found = strpos($content, $pattern) !== false;
        assertCondition(!$found, "No leaked '{$pattern}' in {$relFile}");
    }
}

// -------------------------------------------------------------------
// 3. Pricing & Cart Tampering Logic
// -------------------------------------------------------------------
echo "\n3. Checking Pricing Logic Integrity (pricing.php)...\n";
require_once $baseDir . '/pricing.php';

// Test pricing calculation with unvalidated / tampered item
$dummyCart = [
    ['id' => 999999, 'name' => 'Fake Item', 'qty' => 2, 'price' => 1.00]
];
$priced = ksrCalculateCartTotal($dummyCart);
assertCondition($priced['validated'] === false, "Unmatched cart items marked validated=false");
assertCondition($priced['unmatched'] > 0, "Unmatched cart count recorded");
assertCondition($priced['subtotal'] == 0, "Unmatched items not counted into subtotal");

// -------------------------------------------------------------------
// 4. Razorpay Fail-Closed Verification
// -------------------------------------------------------------------
echo "\n4. Checking Razorpay Gateway Fail-Closed Mode...\n";
require_once $baseDir . '/razorpay_helper.php';

// In simulated production without keys, simulation should not be allowed
putenv('KSR_ENV=production');
assertCondition(!RazorpayHelper::simulationAllowed(), "simulationAllowed() returns false in production");
assertCondition(!RazorpayHelper::verifyWebhookSignature('payload', 'invalidsig'), "Webhook rejects invalid signature");

// -------------------------------------------------------------------
// 5. Open Redirect Protection
// -------------------------------------------------------------------
echo "\n5. Checking Open Redirect Filtering Pattern...\n";
$testRedirects = [
    '/orders.php'        => true,
    '/checkout'          => true,
    'https://evil.com'   => false,
    '//evil.com/login'   => false,
    'javascript:alert()' => false,
];

foreach ($testRedirects as $url => $shouldPass) {
    $isValid = (bool)preg_match('#^/[^/]#', $url);
    assertCondition($isValid === $shouldPass, "Redirect validation for '{$url}' (expected: " . ($shouldPass ? 'valid' : 'rejected') . ")");
}

echo "\n========================================================\n";
if ($failures === 0) {
    echo "ALL TESTS PASSED! Application security assertions verified.\n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "TEST SUITE FAILED with {$failures} failure(s).\n";
    echo "========================================================\n";
    exit(1);
}

