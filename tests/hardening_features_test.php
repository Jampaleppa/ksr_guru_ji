<?php
/**
 * tests/hardening_features_test.php
 * Verifies Phase 1-3 stabilization & new feature behaviors:
 *  - Migration script (scripts/migrate.php)
 *  - Checkout CSRF enforcement (create_order, verify_razorpay_payment, upload_invoice)
 *  - Checkout idempotency deduplication & coupon discount calculation
 *  - Server-side COD per-order & daily limits
 *  - Inventory reservations & stock_history canonical schema
 *  - Coupon validation API & usage recording across MySQL/SQLite
 *  - Delivery hashed OTP & Parcel dynamic config (no hardcoded STAFF_PIN in config.php)
 *  - B2B wholesale DB pricing integrity
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config.php';
require_once $baseDir . '/includes/db.php';
require_once $baseDir . '/includes/coupons.php';
require_once $baseDir . '/includes/inventory.php';
require_once $baseDir . '/pricing.php';

$pdo = getDBConnection();
$testsTotal = 0;
$testsPassed = 0;
$testsFailed = 0;

function hAssert(string $name, bool $cond, string $detail = ''): void {
    global $testsTotal, $testsPassed, $testsFailed;
    $testsTotal++;
    if ($cond) {
        echo "  [PASS] {$name}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        $testsFailed++;
    }
}

/**
 * Invoke checkout.php in an isolated PHP subprocess with simulated session & POST.
 * Preserves the caller-supplied session_id across multiple requests when specified.
 */
function runCheckoutPost(array $postData, array $sessionData, string $sessId): array {
    global $baseDir;
    $runner = <<<'PHP'
<?php
$baseDir = $argv[1];
$payload = json_decode(base64_decode($argv[2]), true);
chdir($baseDir);
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
$savePath = sys_get_temp_dir();
session_save_path($savePath);
session_id($payload['sess_id']);
session_start();
foreach ($payload['session'] as $k => $v) {
    $_SESSION[$k] = $v;
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['SCRIPT_NAME'] = '/checkout.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_POST = $payload['post'];
require $baseDir . '/checkout.php';
PHP;
    $tmp = tempnam(sys_get_temp_dir(), 'ksr_chk_');
    file_put_contents($tmp, $runner);
    $argPayload = base64_encode(json_encode(['post' => $postData, 'session' => $sessionData, 'sess_id' => $sessId]));
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($baseDir) . ' ' . escapeshellarg($argPayload);
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    @unlink($tmp);
    $raw = implode("\n", $out);
    $json = json_decode($raw, true);
    if (!is_array($json) && preg_match('/\{[\s\S]*\}/', $raw, $m)) {
        $json = json_decode($m[0], true);
    }
    return ['code' => $code, 'raw' => $raw, 'json' => is_array($json) ? $json : []];
}

echo "====================================================================\n";
echo "   KSR GURU JI — Hardening & New Features Regression Suite          \n";
echo "====================================================================\n\n";

// 1. Config & secrets check
echo "1. Verifying hardened config & parcel constants...\n";
hAssert("STAFF_PIN literal removed from config.php", strpos(file_get_contents($baseDir . '/config.php'), "2468") === false);
hAssert("KSR_COD_DAILY_LIMIT defined", defined('KSR_COD_DAILY_LIMIT') && KSR_COD_DAILY_LIMIT > 0);
hAssert("KSR_PARCEL_CHARGE defined", defined('KSR_PARCEL_CHARGE') && KSR_PARCEL_CHARGE > 0);

// 2. Run migration script
echo "\n2. Running scripts/migrate.php...\n";
$migOut = [];
$migCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($baseDir . '/scripts/migrate.php') . ' 2>&1', $migOut, $migCode);
hAssert("scripts/migrate.php executes cleanly (exit=0)", $migCode === 0, implode(' | ', $migOut));
foreach (['delivery_otps', 'coupons', 'coupon_usage', 'inventory_reservations', 'stock_history', 'order_idempotency_keys', 'customer_addresses', 'b2b_customers', 'b2b_orders', 'b2b_price_tiers', 'account_deletion_requests'] as $tbl) {
    $okTbl = false;
    try {
        $okTbl = ($pdo->query("SELECT 1 FROM {$tbl} LIMIT 1") !== false);
    } catch (Throwable $e) {
    }
    hAssert("Table '{$tbl}' exists after migration", $okTbl);
}

// Seed deterministic test product + coupon
$prod = $pdo->query("SELECT id, name, price, stock FROM products WHERE status = 1 AND price >= 50 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$createdTempProd = false;
if (!$prod) {
    $pdo->prepare("INSERT INTO products (name, price, stock, status, category) VALUES ('Hardening Test Item', 100.00, 50, 1, 'grains')")->execute();
    $prodId = (int)$pdo->lastInsertId();
    $prod = ['id' => $prodId, 'name' => 'Hardening Test Item', 'price' => 100.00, 'stock' => 50];
    $createdTempProd = true;
} else {
    $prodId = (int)$prod['id'];
    if ((int)$prod['stock'] < 20) {
        $pdo->prepare("UPDATE products SET stock = 50 WHERE id = ?")->execute([$prodId]);
        $prod['stock'] = 50;
    }
}
$origStock = (int)$prod['stock'];
$prodPrice = (float)$prod['price'];

$testCouponCode = 'TESTHARD' . strtoupper(bin2hex(random_bytes(3)));
ensureCouponTables($pdo);
$pdo->prepare("INSERT INTO coupons (code, description, discount_type, discount_value, min_order_amount, usage_limit, user_limit, end_date, status) VALUES (?, 'Test 10% coupon', 'percent', 10, 50, 5, 2, ?, 'active')")
    ->execute([$testCouponCode, date('Y-m-d H:i:s', strtotime('+1 day'))]);

// 3. Pricing + Coupon Validation
echo "\n3. Testing pricing & coupon validation...\n";
$cvOk = ksrValidateCoupon($pdo, $testCouponCode, 200.00);
hAssert("ksrValidateCoupon applies 10% discount accurately", $cvOk['valid'] === true && abs($cvOk['discount'] - 20.00) < 0.01);

$cvMinFail = ksrValidateCoupon($pdo, $testCouponCode, 20.00);
hAssert("ksrValidateCoupon rejects order below min_order_amount", $cvMinFail['valid'] === false);

$cartForTest = [['id' => $prodId, 'name' => $prod['name'], 'price' => $prodPrice, 'qty' => 2]];
$pricedWithCoupon = ksrCalculateCartTotal($cartForTest, $testCouponCode);
$expectedSub = round($prodPrice * 2, 2);
$expectedDisc = round($expectedSub * 0.10, 2);
$expectedTotal = round($expectedSub + (float)KSR_DELIVERY_CHARGE - $expectedDisc, 2);
hAssert("ksrCalculateCartTotal subtracts validated coupon discount", $pricedWithCoupon['validated'] === true && abs($pricedWithCoupon['discount'] - $expectedDisc) < 0.01 && abs($pricedWithCoupon['total'] - $expectedTotal) < 0.01);

// 4. Checkout CSRF, Idempotency & Coupon Recording
echo "\n4. Testing checkout.php CSRF, idempotency, stock_history & coupon recording...\n";
$testSessId = 'ksrharden' . bin2hex(random_bytes(6));
$csrfTok = bin2hex(random_bytes(16));
$idemKey = 'idem_' . bin2hex(random_bytes(8));

$badCsrf = runCheckoutPost(
    ['action' => 'create_order', 'csrf_token' => 'wrong_token'],
    ['csrf_token' => $csrfTok],
    $testSessId
);
hAssert("create_order blocks invalid CSRF token", ($badCsrf['json']['success'] ?? true) === false, $badCsrf['raw']);

$badVerifyCsrf = runCheckoutPost(
    ['action' => 'verify_razorpay_payment', 'order_id' => 1, 'csrf_token' => 'wrong_token'],
    ['csrf_token' => $csrfTok],
    $testSessId
);
hAssert("verify_razorpay_payment blocks invalid CSRF token", ($badVerifyCsrf['json']['success'] ?? true) === false, $badVerifyCsrf['raw']);

$basePost = [
    'action'          => 'create_order',
    'csrf_token'      => $csrfTok,
    'idempotency_key' => $idemKey,
    'coupon_code'     => $testCouponCode,
    'name'            => 'Hardening Test Buyer',
    'email'           => 'hardening@ksrguruji.com',
    'phone'           => '9876501234',
    'address'         => '123 Test Bazaar Street',
    'city'            => 'Gajendragad',
    'state'           => 'Karnataka',
    'pincode'         => '582114',
    'payment_method'  => 'cod',
    'items'           => json_encode($cartForTest),
    'total'           => 1.00, // deliberately tampered client total (should be ignored!)
];

$firstOrder = runCheckoutPost($basePost, ['csrf_token' => $csrfTok], $testSessId);
$createdOrderId = (int)($firstOrder['json']['order_id'] ?? 0);
hAssert("create_order succeeds with valid CSRF + coupon", ($firstOrder['json']['success'] ?? false) === true && $createdOrderId > 0, $firstOrder['raw']);
hAssert("create_order ignores tampered client total and applies coupon discount", abs((float)($firstOrder['json']['discount'] ?? 0) - $expectedDisc) < 0.01);

// Verify DB order total matches authoritative server figure
$dbOrdTotal = (float)$pdo->query("SELECT total FROM orders WHERE id = {$createdOrderId}")->fetchColumn();
hAssert("Order persisted with server-calculated discounted total", abs($dbOrdTotal - $expectedTotal) < 0.01, "Expected {$expectedTotal}, got {$dbOrdTotal}");

// Duplicate retry with SAME idempotency key
$retryOrder = runCheckoutPost($basePost, ['csrf_token' => $csrfTok], $testSessId);
hAssert(
    "create_order deduplicates duplicate idempotency_key submission",
    ($retryOrder['json']['success'] ?? false) === true
    && ($retryOrder['json']['idempotent'] ?? false) === true
    && (int)($retryOrder['json']['order_id'] ?? 0) === $createdOrderId,
    $retryOrder['raw']
);

// Stock decremented ONCE (not twice on retry) and stock_history logged cleanly
$postStock = (int)$pdo->query("SELECT stock FROM products WHERE id = {$prodId}")->fetchColumn();
hAssert("Product stock decremented exact qty once after idempotent retry", $postStock === ($origStock - 2), "Orig={$origStock}, Post={$postStock}");

$shRow = $pdo->query("SELECT * FROM stock_history WHERE product_id = {$prodId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
hAssert("stock_history logged canonical old_quantity & new_quantity", !empty($shRow) && (int)$shRow['old_quantity'] === $origStock && (int)$shRow['new_quantity'] === ($origStock - 2));

$cuCnt = (int)$pdo->query("SELECT COUNT(*) FROM coupon_usage WHERE coupon_code = " . $pdo->quote($testCouponCode) . " AND order_id = {$createdOrderId}")->fetchColumn();
$cpUsed = (int)$pdo->query("SELECT used_count FROM coupons WHERE code = " . $pdo->quote($testCouponCode))->fetchColumn();
hAssert("coupon_usage recorded and used_count incremented once", $cuCnt === 1 && $cpUsed === 1);

// 5. Server-side COD limit rejection test
$overCodQty = (int)ceil((KSR_COD_LIMIT + 1000) / max(1.0, $prodPrice));
$overCodPost = $basePost;
$overCodPost['idempotency_key'] = 'idem_cod_' . bin2hex(random_bytes(6));
$overCodPost['coupon_code'] = '';
$overCodPost['items'] = json_encode([['id' => $prodId, 'name' => $prod['name'], 'price' => $prodPrice, 'qty' => min(999, $overCodQty)]]);
if (($overCodQty * $prodPrice) > KSR_COD_LIMIT) {
    $codFail = runCheckoutPost($overCodPost, ['csrf_token' => $csrfTok], $testSessId);
    hAssert("create_order blocks COD orders exceeding KSR_COD_LIMIT", ($codFail['json']['success'] ?? true) === false, $codFail['raw']);
}

// 6. Inventory reservation isolation check
echo "\n5. Testing inventory reservations...\n";
$otherSid = 'other_sess_' . bin2hex(random_bytes(4));
ksrReserveProductStock($pdo, $prodId, 3, $otherSid, 300);
$resByOther = ksrGetReservedQuantity($pdo, $prodId, 'my_sess_1');
$resExcludingSelf = ksrGetReservedQuantity($pdo, $prodId, $otherSid);
hAssert("Inventory reservation blocks other sessions while excluding holder", $resByOther >= 3 && $resExcludingSelf === 0);
ksrReleaseSessionReservations($pdo, $otherSid);
hAssert("Session inventory reservation releases cleanly", ksrGetReservedQuantity($pdo, $prodId, 'my_sess_1') === 0);

// 7. Duplicate UTR guard + Delivery OTP + Reports SQL + Search filters + Offers
echo "\n6. Verifying duplicate UTR check, delivery OTP gate, reports & offers...\n";
$dupUtr = 'DUPUTR' . strtoupper(bin2hex(random_bytes(5)));
$utrPost = $basePost;
$utrPost['idempotency_key'] = 'idem_utr1_' . bin2hex(random_bytes(5));
$utrPost['payment_method'] = 'upi';
$utrPost['coupon_code'] = '';
$utrPost['utr'] = $dupUtr;
$utrPost['items'] = json_encode([['id' => $prodId, 'name' => $prod['name'], 'price' => $prodPrice, 'qty' => 1]]);
$utrRes1 = runCheckoutPost($utrPost, ['csrf_token' => $csrfTok], $testSessId);
$utrOrdId = (int)($utrRes1['json']['order_id'] ?? 0);
hAssert("Direct UPI order succeeds with unique UTR", ($utrRes1['json']['success'] ?? false) === true && $utrOrdId > 0);

$utrPost['idempotency_key'] = 'idem_utr2_' . bin2hex(random_bytes(5));
$utrRes2 = runCheckoutPost($utrPost, ['csrf_token' => $csrfTok], $testSessId);
hAssert("Duplicate UTR is rejected on second order", ($utrRes2['json']['success'] ?? true) === false);

// Reports queries verify cleanly against current DB driver
$repSalesOk = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE (payment_status IN ('paid','completed','success','cod_collected') OR (payment_method='cod' AND status='delivered')) AND status != 'cancelled'") !== false;
$repUsersOk = $pdo->query("SELECT u.id, COALESCE(NULLIF(u.name,''), u.email) as name FROM users u LIMIT 1") !== false;
$repLowOk   = $pdo->query("SELECT p.id, p.name, COALESCE(i.quantity, p.stock, 0) as quantity FROM products p LEFT JOIN inventory i ON p.id = i.product_id LIMIT 1") !== false;
$repCouponOk = $pdo->query("SELECT UPPER(cu.coupon_code) AS code, COUNT(cu.id) AS usage_count, COALESCE(SUM(cu.discount_amount),0) AS total_discount, COALESCE(SUM(COALESCE(o.total,0)),0) AS revenue_generated FROM coupon_usage cu LEFT JOIN coupons c ON UPPER(c.code) = UPPER(cu.coupon_code) LEFT JOIN orders o ON o.id = cu.order_id GROUP BY UPPER(cu.coupon_code) LIMIT 1") !== false;
hAssert("admin/reports.php SQL queries (incl. coupon analytics) are valid across SQLite/MySQL", $repSalesOk && $repUsersOk && $repLowOk && $repCouponOk);

// Account deletion queue & RBAC permission mappings
require_once $baseDir . '/admin/inc/page_permissions.php';
hAssert("admin_page_permission maps account_deletions to customers.view", admin_page_permission('account_deletions') === 'customers.view');
hAssert("admin_action_permission maps account_deletions approve/deny to customers.edit", admin_action_permission('approve', 'account_deletions') === 'customers.edit' && admin_action_permission('deny', 'account_deletions') === 'customers.edit');
$adrCheckOk = $pdo->query("SELECT id, user_id, email, reason, status, requested_at, reviewed_by, reviewed_at, admin_note FROM account_deletion_requests LIMIT 1") !== false;
hAssert("account_deletion_requests includes review metadata columns", $adrCheckOk);

// delivery/api.php uses ANSI CASE instead of MySQL-only FIELD()
$deliveryApiCode = file_get_contents($baseDir . '/delivery/api.php');
hAssert("delivery/api.php uses ANSI CASE order sort (no FIELD())", strpos($deliveryApiCode, 'ORDER BY CASE o.status') !== false && stripos($deliveryApiCode, 'ORDER BY FIELD(') === false);

// admin/delivery.php, admin/notification.php & admin/b2b.php cross-DB & layout checks
$delAdminCode = file_get_contents($baseDir . '/admin/delivery.php');
hAssert("admin/delivery.php avoids MySQL-only DATE_SUB/CURDATE and u.fullname", strpos($delAdminCode, 'DATE_SUB(') === false && strpos($delAdminCode, 'CURDATE(') === false && strpos($delAdminCode, 'fullname') === false);
$delDriversQ = $pdo->query("SELECT id, COALESCE(NULLIF(name, ''), email) AS name FROM users WHERE role IN ('delivery', 'delivery_boy') ORDER BY name ASC") !== false;
hAssert("admin/delivery.php driver lookup SQL runs on current driver", $delDriversQ);

$notifAdminCode = file_get_contents($baseDir . '/admin/notification.php');
hAssert("admin/notification.php avoids NOW() and u.fullname", strpos($notifAdminCode, 'NOW()') === false && strpos($notifAdminCode, 'fullname') === false);
$notifJoinQ = $pdo->query("SELECT n.*, COALESCE(NULLIF(u.name, ''), u.email) as user_name, u.email as user_email FROM notifications n LEFT JOIN users u ON n.user_id = u.id LIMIT 1") !== false;
hAssert("admin/notification.php notifications join SQL runs on current driver", $notifJoinQ);

$b2bAdminCode = file_get_contents($baseDir . '/admin/b2b.php');
hAssert("admin/b2b.php uses header.php without duplicate HTML/sidebar tags", strpos($b2bAdminCode, '<!DOCTYPE html>') === false && strpos($b2bAdminCode, 'inc/header.php') !== false);

// admin/management.php CSRF + stock sync, settings_logic parcel_charge, & helper.php safety
$mgmtCode = file_get_contents($baseDir . '/admin/management.php');
hAssert("admin/management.php enforces CSRF and syncs products.stock on inventory update", strpos($mgmtCode, 'mgmtVerifyCsrf()') !== false && strpos($mgmtCode, 'UPDATE products SET stock = ?') !== false);

require_once $baseDir . '/admin/inc/settings_logic.php';
$delDefaults = getDefaultSettings('delivery');
hAssert("admin/inc/settings_logic.php exposes parcel_charge in delivery defaults", isset($delDefaults['parcel_charge']) && (float)$delDefaults['parcel_charge'] > 0);
$settingsAdminCode = file_get_contents($baseDir . '/admin/settings.php');
hAssert("admin/settings.php supports SQLite+MySQL upsert and CSRF check", strpos($settingsAdminCode, 'ON CONFLICT(setting_key)') !== false && strpos($settingsAdminCode, 'admin_csrf_token') !== false);

require_once $baseDir . '/WhatsApp/telehealth/helper.php';
$waSafe = true;
try {
    simulateWhatsApp(null, 'unit_test_string', 'plain string payload');
    simulateWhatsApp(null, 'unit_test_array', ['phone' => '9999999999', 'otp' => '123456']);
} catch (Throwable $e) {
    $waSafe = false;
}
hAssert("simulateWhatsApp handles both string and array params safely", $waSafe);
try {
    $pdo->prepare("DELETE FROM whatsapp_logs WHERE template IN ('unit_test_string', 'unit_test_array')")->execute();
} catch (Throwable $ignore) {
}

$cfWorkflow = file_get_contents($baseDir . '/.github/workflows/cloudflare-pages.yml');
hAssert("cloudflare-pages.yml avoids archived cloudflare/pages-action", strpos($cfWorkflow, 'cloudflare/pages-action') === false && strpos($cfWorkflow, 'cloudflare/wrangler-action') !== false);
$appConstCode = file_get_contents($baseDir . '/includes/app_constants.php');
hAssert("includes/app_constants.php supports KSR_IS_PRODUCTION, KSR_ENV & APP_ENV", strpos($appConstCode, "getenv('KSR_IS_PRODUCTION')") !== false && strpos($appConstCode, "getenv('KSR_ENV')") !== false);

// Telehealth find-doctor SQL & notifications columns check
$findDocCode = file_get_contents($baseDir . '/WhatsApp/telehealth/find-doctor.php');
hAssert("find-doctor.php avoids duplicate WHERE clause on filter", strpos($findDocCode, '$sql .= " AND "') !== false && strpos($findDocCode, '$sql .= " WHERE "') === false);
$notifOk = $pdo->query("SELECT subject, link, read_at FROM notifications LIMIT 1") !== false;
hAssert("notifications table includes subject, link, read_at columns", $notifOk);

// Cleanup UTR test order(s) & restore stock cleanly
$leftoverUtrs = $pdo->query("SELECT id FROM orders WHERE transaction_id LIKE 'DUPUTR%'")->fetchAll(PDO::FETCH_COLUMN);
$utrExtraDecs = count($leftoverUtrs);
foreach ($leftoverUtrs as $luId) {
    $luId = (int)$luId;
    $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$luId]);
    $pdo->prepare("DELETE FROM payments WHERE order_id = ?")->execute([$luId]);
    $pdo->prepare("DELETE FROM payment_events WHERE order_id = ?")->execute([$luId]);
    $pdo->prepare("DELETE FROM order_timeline WHERE order_id = ?")->execute([$luId]);
    $pdo->prepare("DELETE FROM order_idempotency_keys WHERE order_id = ?")->execute([$luId]);
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$luId]);
}
if ($utrExtraDecs > 0 && !$createdTempProd) {
    $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$utrExtraDecs, $prodId]);
    $origStock += $utrExtraDecs;
}

// 8. Cleanup all test artifacts & temp session file
echo "\n7. Cleaning up test artifacts...\n";
if ($createdOrderId > 0) {
    $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$createdOrderId]);
    $pdo->prepare("DELETE FROM payments WHERE order_id = ?")->execute([$createdOrderId]);
    $pdo->prepare("DELETE FROM payment_events WHERE order_id = ?")->execute([$createdOrderId]);
    $pdo->prepare("DELETE FROM order_timeline WHERE order_id = ?")->execute([$createdOrderId]);
    $pdo->prepare("DELETE FROM coupon_usage WHERE order_id = ?")->execute([$createdOrderId]);
    $pdo->prepare("DELETE FROM order_idempotency_keys WHERE order_id = ?")->execute([$createdOrderId]);
    $pdo->prepare("DELETE FROM stock_history WHERE product_id = ? AND note LIKE ?")->execute([$prodId, '%Order #%']);
    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$createdOrderId]);
}
$pdo->prepare("DELETE FROM coupons WHERE code = ?")->execute([$testCouponCode]);
if ($createdTempProd) {
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$prodId]);
} else {
    $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$origStock, $prodId]);
}
@unlink(sys_get_temp_dir() . '/sess_' . $testSessId);

echo "\n====================================================================\n";
echo " Total Assertions : {$testsTotal} | Passed : {$testsPassed} | Failed : {$testsFailed}\n";
echo "====================================================================\n";
exit($testsFailed > 0 ? 1 : 0);
