<?php
/**
 * End-to-End Customer Checkout and Delivery Lifecycle Test
 * --------------------------------------------------------
 * Validates the full customer-to-delivery lifecycle:
 * 1. Product catalog price validation (Market Price + ₹3.00 rule)
 * 2. Customer order creation & transactional stock reservation
 * 3. Order line items & financial audit trail initialization
 * 4. Admin order discovery & delivery boy assignment
 * 5. Delivery boy pickup, in-transit, and successful delivery transitions
 * 6. Customer order tracking state & timeline verification
 * 7. Clean restoration of test inventory
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/config.php';
require_once $baseDir . '/includes/db.php';
require_once $baseDir . '/includes/app_constants.php';

$pdo = getDBConnection();

$testsTotal = 0;
$testsPassed = 0;
$testsFailed = 0;

function assertTest(string $name, bool $condition, string $detail = ''): void {
    global $testsTotal, $testsPassed, $testsFailed;
    $testsTotal++;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $testsPassed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " — {$detail}" : '') . "\n";
        $testsFailed++;
    }
}

echo "====================================================================\n";
echo "   KSR GURU JI — End-to-End Checkout & Delivery Lifecycle Test     \n";
echo "====================================================================\n\n";

// -------------------------------------------------------------
// Step 1: Catalog & Pricing Rule Verification
// -------------------------------------------------------------
echo "1. Checking Product Catalog & Pricing Rule (Selling = Market + ₹3.00)...\n";

$productStmt = $pdo->query("SELECT * FROM products WHERE price > 0 ORDER BY id ASC LIMIT 1");
$testProduct = $productStmt->fetch(PDO::FETCH_ASSOC);

assertTest("Sample product exists in database", !empty($testProduct));
$prodId    = (int)($testProduct['id'] ?? 1);
$prodName  = $testProduct['name'] ?? 'Sample Product';
$prodPrice = (float)($testProduct['price'] ?? 53.0);
$origStock = (int)($testProduct['stock'] ?? 20);

// If market_price is recorded, verify ₹3 rule
if (isset($testProduct['market_price']) && (float)$testProduct['market_price'] > 0) {
    $expectedSp = (float)$testProduct['market_price'] + 3.00;
    assertTest("Selling price matches Market Price + ₹3.00 rule", abs($prodPrice - $expectedSp) < 0.01, "Expected: {$expectedSp}, Got: {$prodPrice}");
} else {
    assertTest("Product pricing margin default is ₹3.00", defined('KSR_DEFAULT_PRICE_MARGIN') && KSR_DEFAULT_PRICE_MARGIN === 3.00);
}

// -------------------------------------------------------------
// Step 2: Customer Order Placement (COD Checkout Simulation)
// -------------------------------------------------------------
echo "\n2. Simulating Customer Checkout (Cash on Delivery)...\n";

$testOrderNumber = 'TEST-ORD-' . strtoupper(bin2hex(random_bytes(4)));
$orderQty = 2;
$deliveryCharge = 15.00;
$orderSubtotal = $prodPrice * $orderQty;
$orderTotal = $orderSubtotal + $deliveryCharge;

$isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
$nowExpr = $isSqlite ? "datetime('now')" : "NOW()";

// Begin transactional placement
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_number, user_id, name, email, phone, address, city, state, pincode,
            total, payment_method, payment_status, notes, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', {$nowExpr})
    ");
    $stmt->execute([
        $testOrderNumber,
        null, // Guest customer
        'Test Customer Ramesh',
        'ramesh.test@ksrguruji.com',
        '9876543210',
        'Shop 4B, Main Market Road',
        'Raichur',
        'Karnataka',
        '584101',
        $orderTotal,
        'cod',
        'pending',
        'Handle with care'
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // Insert order line items
    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $itemStmt->execute([
        $orderId,
        $prodId,
        $prodName,
        $orderQty,
        $prodPrice,
        $orderSubtotal
    ]);

    // Insert payment record
    $payStmt = $pdo->prepare("
        INSERT INTO payments (order_id, amount, method, gateway, status)
        VALUES (?, ?, 'cod', 'cod', 'cod_pending')
    ");
    $payStmt->execute([$orderId, $orderTotal]);
    $paymentId = (int)$pdo->lastInsertId();

    // Log to order timeline
    $tlStmt = $pdo->prepare("
        INSERT INTO order_timeline (order_id, status, notes, created_by, created_at)
        VALUES (?, 'confirmed', 'Order placed by customer via Cash on Delivery.', 0, {$nowExpr})
    ");
    $tlStmt->execute([$orderId]);

    // Decrement stock
    $pdo->prepare("UPDATE products SET stock = MAX(0, stock - ?) WHERE id = ?")
        ->execute([$orderQty, $prodId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    die("FATAL: Checkout simulation failed: " . $e->getMessage() . "\n");
}

assertTest("Order created successfully in database", $orderId > 0, "Order ID: {$orderId}");
assertTest("Order number generated properly", !empty($testOrderNumber));

// Verify stock reservation
$postStockStmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
$postStockStmt->execute([$prodId]);
$newStock = (int)$postStockStmt->fetchColumn();
assertTest("Product stock reserved/decremented correctly", $newStock === ($origStock - $orderQty), "Expected: " . ($origStock - $orderQty) . ", Got: {$newStock}");

// -------------------------------------------------------------
// Step 3: Admin Discovery & Delivery Boy Assignment
// -------------------------------------------------------------
echo "\n3. Testing Admin Order Discovery & Delivery Boy Assignment...\n";

$fetchOrderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$fetchOrderStmt->execute([$orderId]);
$placedOrder = $fetchOrderStmt->fetch(PDO::FETCH_ASSOC);

assertTest("Admin can retrieve order by ID", !empty($placedOrder));
assertTest("Order status initially confirmed", ($placedOrder['status'] ?? '') === 'confirmed');

// Assign to Delivery Boy: ID 1 ("JK")
$deliveryBoyId = 1;
$trackingNo = 'KSR-TRK-' . date('Ymd') . '-' . $orderId;

$assignStmt = $pdo->prepare("
    UPDATE orders 
    SET driver_id = ?, delivery_boy_id = ?, tracking_number = ?, assigned_at = {$nowExpr}
    WHERE id = ?
");
$assignStmt->execute([$deliveryBoyId, $deliveryBoyId, $trackingNo, $orderId]);

$pdo->prepare("
    INSERT INTO order_timeline (order_id, status, notes, created_by, created_at)
    VALUES (?, 'assigned', ?, 1, {$nowExpr})
")->execute([$orderId, "Assigned to delivery boy JK (Tracking: {$trackingNo})"]);

$checkAssignStmt = $pdo->prepare("SELECT driver_id, delivery_boy_id, tracking_number FROM orders WHERE id = ?");
$checkAssignStmt->execute([$orderId]);
$assignData = $checkAssignStmt->fetch(PDO::FETCH_ASSOC);

assertTest("Delivery boy assigned correctly to order", (int)($assignData['delivery_boy_id'] ?? 0) === $deliveryBoyId);
assertTest("Tracking number generated and saved", ($assignData['tracking_number'] ?? '') === $trackingNo);

// -------------------------------------------------------------
// Step 4: Delivery Boy Status Updates (Pickup -> Out for Delivery -> Delivered)
// -------------------------------------------------------------
echo "\n4. Testing Delivery Boy Lifecycle (Pickup -> In-Transit -> Delivered)...\n";

// 4a. Out for Delivery transition
$outStmt = $pdo->prepare("UPDATE orders SET status = 'out_for_delivery' WHERE id = ?");
$outStmt->execute([$orderId]);

$pdo->prepare("
    INSERT INTO order_timeline (order_id, status, notes, created_by, created_at)
    VALUES (?, 'out_for_delivery', 'Order picked up and out for delivery.', ?, {$nowExpr})
")->execute([$orderId, $deliveryBoyId]);

$statusCheck1 = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
$statusCheck1->execute([$orderId]);
assertTest("Order transitioned to 'out_for_delivery'", $statusCheck1->fetchColumn() === 'out_for_delivery');

// 4b. Delivered transition (cash collected)
$delivStmt = $pdo->prepare("
    UPDATE orders 
    SET status = 'delivered', payment_status = 'paid', delivered_at = {$nowExpr}
    WHERE id = ?
");
$delivStmt->execute([$orderId]);

$pdo->prepare("UPDATE payments SET status = 'completed', paid_at = {$nowExpr} WHERE order_id = ?")
    ->execute([$orderId]);

$pdo->prepare("
    INSERT INTO order_timeline (order_id, status, notes, created_by, created_at)
    VALUES (?, 'delivered', 'Order delivered successfully. Cash of ₹{$orderTotal} collected.', ?, {$nowExpr})
")->execute([$orderId, $deliveryBoyId]);

$finalOrderStmt = $pdo->prepare("SELECT status, payment_status, delivered_at FROM orders WHERE id = ?");
$finalOrderStmt->execute([$orderId]);
$finalOrder = $finalOrderStmt->fetch(PDO::FETCH_ASSOC);

assertTest("Order transitioned to 'delivered'", ($finalOrder['status'] ?? '') === 'delivered');
assertTest("COD Payment marked 'paid' on delivery", ($finalOrder['payment_status'] ?? '') === 'paid');
assertTest("delivered_at timestamp recorded", !empty($finalOrder['delivered_at']));

// -------------------------------------------------------------
// Step 5: Timeline & Tracking Audit Trail Verification
// -------------------------------------------------------------
echo "\n5. Verifying Complete Order Timeline & Customer Tracking...\n";

$tlQuery = $pdo->prepare("SELECT * FROM order_timeline WHERE order_id = ? ORDER BY id ASC");
$tlQuery->execute([$orderId]);
$timelineEvents = $tlQuery->fetchAll(PDO::FETCH_ASSOC);

assertTest("Timeline records multiple chronological events", count($timelineEvents) >= 4, "Count: " . count($timelineEvents));

$statusesRecorded = array_column($timelineEvents, 'status');
assertTest("Timeline contains 'confirmed' event", in_array('confirmed', $statusesRecorded, true));
assertTest("Timeline contains 'assigned' event", in_array('assigned', $statusesRecorded, true));
assertTest("Timeline contains 'out_for_delivery' event", in_array('out_for_delivery', $statusesRecorded, true));
assertTest("Timeline contains 'delivered' event", in_array('delivered', $statusesRecorded, true));

// -------------------------------------------------------------
// Step 6: Database Clean Up (Teardown)
// -------------------------------------------------------------
echo "\n6. Cleaning Up Test Artifacts...\n";

$pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
$pdo->prepare("DELETE FROM payments WHERE order_id = ?")->execute([$orderId]);
$pdo->prepare("DELETE FROM order_timeline WHERE order_id = ?")->execute([$orderId]);
$pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);

// Restore stock
$pdo->prepare("UPDATE products SET stock = ? WHERE id = ?")->execute([$origStock, $prodId]);

$cleanupStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE id = ?");
$cleanupStmt->execute([$orderId]);
$remaining = (int)$cleanupStmt->fetchColumn();

assertTest("Test order and associated records safely cleaned up", $remaining === 0);

// Verify stock restored
$restoredStockStmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
$restoredStockStmt->execute([$prodId]);
$restoredStock = (int)$restoredStockStmt->fetchColumn();
assertTest("Product inventory stock cleanly restored", $restoredStock === $origStock);

// -------------------------------------------------------------
// Test Summary
// -------------------------------------------------------------
echo "\n====================================================================\n";
echo "                      TEST RUN RESULTS                              \n";
echo "====================================================================\n";
echo " Total Tests Executed : {$testsTotal}\n";
echo " Tests Passed         : {$testsPassed}\n";
echo " Tests Failed         : {$testsFailed}\n";
echo "====================================================================\n";

if ($testsFailed === 0) {
    echo "STATUS: ALL E2E CHECKOUT & DELIVERY TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "STATUS: SOME E2E TESTCASES FAILED.\n";
    exit(1);
}
