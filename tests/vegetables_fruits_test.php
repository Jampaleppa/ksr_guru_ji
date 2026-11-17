<?php
// ============================================================
// tests/vegetables_fruits_test.php
// Unit & Integration Test Suite for Vegetables & Fruits Daily Rates System
// KSR GURU JI: Selling price = Market price + ₹3.00
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/market_prices.php';

$totalTests = 0;
$passedTests = 0;

function assertTest(bool $condition, string $title, string $details = ''): void {
    global $totalTests, $passedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$title}\n";
    } else {
        echo "  [FAIL] {$title} — Details: {$details}\n";
    }
}

echo "====================================================================\n";
echo "   VEGETABLES & FRUITS — Daily Market Price & Selling Price Tests   \n";
echo "====================================================================\n\n";

$pdo = getDBConnection();
ensureMarketPriceTables($pdo);

// ------------------------------------------------------------
// 1. Database Schema & Tables & Recommended Columns
// ------------------------------------------------------------
echo "1. Testing Database Schema & Recommended Fields...\n";
$stmtTables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('daily_market_prices', 'market_price_history')");
$foundTables = $stmtTables->fetchAll(PDO::FETCH_COLUMN);
assertTest(in_array('daily_market_prices', $foundTables), "daily_market_prices table exists in database");
assertTest(in_array('market_price_history', $foundTables), "market_price_history table exists in database");

$cols = array_map(fn($c) => strtolower($c['name']), $pdo->query("PRAGMA table_info(daily_market_prices)")->fetchAll(PDO::FETCH_ASSOC));
assertTest(in_array('product_name', $cols), "Column 'product_name' exists in daily_market_prices");
assertTest(in_array('base_quantity', $cols), "Column 'base_quantity' exists in daily_market_prices");
assertTest(in_array('display_unit', $cols), "Column 'display_unit' exists in daily_market_prices");
assertTest(in_array('stock_status', $cols), "Column 'stock_status' exists in daily_market_prices");
assertTest(in_array('is_active', $cols), "Column 'is_active' exists in daily_market_prices");

// ------------------------------------------------------------
// 2. Catalog Seeding & Structure
// ------------------------------------------------------------
echo "\n2. Testing Catalog Seeding & Unit Types...\n";
$seeded = seedDailyMarketPrices($pdo);
$items = getDailyMarketPrices();
assertTest(count($items) >= 50, "At least 50 produce items loaded in daily_market_prices", "Found " . count($items));

$unitTypes = array_unique(array_column($items, 'unit_type'));
assertTest(in_array('gram', $unitTypes), "Supports 'gram' (250g base) unit");
assertTest(in_array('kg', $unitTypes), "Supports 'kg' (weight) unit");
assertTest(in_array('piece', $unitTypes), "Supports 'piece' unit (e.g. Radish, Pumpkin)");
assertTest(in_array('dozen', $unitTypes), "Supports 'dozen' unit (e.g. Banana)");
assertTest(in_array('bunch', $unitTypes), "Supports 'bunch' unit (e.g. Tulsi, Mint)");
assertTest(in_array('packet', $unitTypes), "Supports 'packet' unit (e.g. Leafy Veg, Winged Bean)");

// ------------------------------------------------------------
// 3. Automatic Pricing Rule: Selling Price = Market Price + ₹3.00
// ------------------------------------------------------------
echo "\n3. Testing Automatic Pricing Rule (Selling = Market + ₹3.00)...\n";
$allFormulaMatch = true;
foreach ($items as $item) {
    $expectedSelling = round((float)$item['market_price'] + (float)$item['profit_margin'], 2);
    $actualSelling = (float)$item['selling_price'];
    if (abs($expectedSelling - $actualSelling) > 0.01) {
        $allFormulaMatch = false;
        break;
    }
}
assertTest($allFormulaMatch, "Selling price equals Market price + ₹3.00 for all items");

// ------------------------------------------------------------
// 4. Flexible Measurement & Fractional Option Calculations
// ------------------------------------------------------------
echo "\n4. Testing Flexible Measurement & Variant Pricing...\n";

// 250 Gram Base Unit: Tomato (Market: 15 -> Selling: 18 / 250 g)
// Rule: base_quantity = 250. Customer selects 500 g -> calculate ₹36.
$tomato250Options = getPurchaseOptionsForUnit('gram', 18.00, '', 250, '250 g');
$t250 = array_values(array_filter($tomato250Options, fn($o) => $o['label'] === '250g'))[0] ?? null;
$t500 = array_values(array_filter($tomato250Options, fn($o) => $o['label'] === '500g'))[0] ?? null;
$t1kg = array_values(array_filter($tomato250Options, fn($o) => $o['label'] === '1 kg'))[0] ?? null;
assertTest($t250 && abs($t250['price'] - 18.00) < 0.01, "Tomato 250g base calculation: ₹18.00");
assertTest($t500 && abs($t500['price'] - 36.00) < 0.01, "Tomato 500g calculation: 2 × ₹18 = ₹36.00");
assertTest($t1kg && abs($t1kg['price'] - 72.00) < 0.01, "Tomato 1 kg calculation: 4 × ₹18 = ₹72.00");

// Leafy Vegetables – Packet: Coriander Leaves (Market: 15 -> Selling: 18 / Packet)
$corianderOptions = getPurchaseOptionsForUnit('packet', 18.00, '', 1, 'Packet');
$c1pkt = array_values(array_filter($corianderOptions, fn($o) => str_starts_with($o['label'], '1 packet')))[0] ?? null;
$c2pkt = array_values(array_filter($corianderOptions, fn($o) => str_starts_with($o['label'], '2 packet')))[0] ?? null;
assertTest($c1pkt && abs($c1pkt['price'] - 18.00) < 0.01, "Coriander 1 packet calculation: ₹18.00");
assertTest($c2pkt && abs($c2pkt['price'] - 36.00) < 0.01, "Coriander 2 packets calculation: ₹36.00");

// Vegetables – Piece: Radish (Market: 10 -> Selling: 13 / Piece)
$radishOptions = getPurchaseOptionsForUnit('piece', 13.00, '', 1, 'Piece');
$r1pc = array_values(array_filter($radishOptions, fn($o) => str_starts_with($o['label'], '1 piece')))[0] ?? null;
$r2pc = array_values(array_filter($radishOptions, fn($o) => str_starts_with($o['label'], '2 piece')))[0] ?? null;
assertTest($r1pc && abs($r1pc['price'] - 13.00) < 0.01, "Radish 1 piece calculation: ₹13.00");
assertTest($r2pc && abs($r2pc['price'] - 26.00) < 0.01, "Radish 2 pieces calculation: ₹26.00");

// Special Vegetables – Packet: Winged Bean (Market: 50 -> Selling: 53 / Packet)
$wingedOptions = getPurchaseOptionsForUnit('packet', 53.00, '', 1, 'Packet');
$w1pkt = array_values(array_filter($wingedOptions, fn($o) => str_starts_with($o['label'], '1 packet')))[0] ?? null;
assertTest($w1pkt && abs($w1pkt['price'] - 53.00) < 0.01, "Winged Bean 1 packet calculation: ₹53.00");

// Weight per kg: (e.g. 35/kg)
$kgOptions = getPurchaseOptionsForUnit('kg', 35.00);
$kg250 = array_values(array_filter($kgOptions, fn($o) => $o['label'] === '250g'))[0] ?? null;
$kg1kg = array_values(array_filter($kgOptions, fn($o) => $o['label'] === '1 kg'))[0] ?? null;
assertTest($kg250 && abs($kg250['price'] - 8.75) < 0.01, "250g per kg calculation: (35 * 250)/1000 = ₹8.75");
assertTest($kg1kg && abs($kg1kg['price'] - 35.00) < 0.01, "1 kg calculation: ₹35.00");

// Dozen: Banana (Market: 47 -> Selling: 50)
$bananaOptions = getPurchaseOptionsForUnit('dozen', 50.00);
$halfDozen = array_values(array_filter($bananaOptions, fn($o) => str_starts_with($o['label'], '1/2 dozen')))[0] ?? null;
$oneDozen = array_values(array_filter($bananaOptions, fn($o) => str_starts_with($o['label'], '1 dozen')))[0] ?? null;
assertTest($halfDozen && abs($halfDozen['price'] - 25.00) < 0.01, "1/2 dozen calculation: 50 * 0.5 = ₹25.00");
assertTest($oneDozen && abs($oneDozen['price'] - 50.00) < 0.01, "1 dozen calculation: ₹50.00");

// Piece: Pineapple (Market: 77 -> Selling: 80)
$pineappleOptions = getPurchaseOptionsForUnit('piece', 80.00);
$onePiece = array_values(array_filter($pineappleOptions, fn($o) => $o['label'] === '1 piece'))[0] ?? null;
$twoPieces = array_values(array_filter($pineappleOptions, fn($o) => $o['label'] === '2 pieces'))[0] ?? null;
assertTest($onePiece && abs($onePiece['price'] - 80.00) < 0.01, "Pineapple 1 piece calculation: ₹80.00");
assertTest($twoPieces && abs($twoPieces['price'] - 160.00) < 0.01, "Pineapple 2 pieces calculation: ₹160.00");

// Bunch: Mint / Tulsi (Market: 17 -> Selling: 20)
$bunchOptions = getPurchaseOptionsForUnit('bunch', 20.00);
$oneBunch = array_values(array_filter($bunchOptions, fn($o) => str_starts_with($o['label'], '1 bunch')))[0] ?? null;
assertTest($oneBunch && abs($oneBunch['price'] - 20.00) < 0.01, "1 bunch (ಕಟ್ಟು) calculation: ₹20.00");

// Packet with custom weights: Frozen Veg Mix (Selling: 120, sizes: 250g, 500g, 1kg)
$packetOptions = getPurchaseOptionsForUnit('packet', 120.00, '250g,500g,1kg');
$pkt250 = array_values(array_filter($packetOptions, fn($o) => str_contains($o['label'], '250g')))[0] ?? null;
$pkt1kg = array_values(array_filter($packetOptions, fn($o) => str_contains($o['label'], '1kg')))[0] ?? null;
assertTest($pkt250 && abs($pkt250['price'] - 30.00) < 0.01, "250g packet: ₹30.00");
assertTest($pkt1kg && abs($pkt1kg['price'] - 120.00) < 0.01, "1kg packet: ₹120.00");

// ------------------------------------------------------------
// 5. Price Update & Price History Tracking
// ------------------------------------------------------------
echo "\n5. Testing Price Updates & History Recording...\n";
$firstItem = $items[0];
$newMarketPrice = (float)$firstItem['market_price'] + 5.00;
$updateResult = updateDailyMarketPrice((int)$firstItem['id'], $newMarketPrice, null, 3.00, 'Test update', 'AutomatedTest');
assertTest($updateResult === true, "updateDailyMarketPrice returns true");

$stmtUpdated = $pdo->prepare("SELECT * FROM daily_market_prices WHERE id = ?");
$stmtUpdated->execute([(int)$firstItem['id']]);
$updatedItem = $stmtUpdated->fetch(PDO::FETCH_ASSOC);
assertTest(abs((float)$updatedItem['market_price'] - $newMarketPrice) < 0.01, "Updated market price saved");
assertTest(abs((float)$updatedItem['selling_price'] - ($newMarketPrice + 3.00)) < 0.01, "Selling price automatically updated to Market + ₹3.00");

$history = getMarketPriceHistory((int)$firstItem['id'], 5);
assertTest(count($history) > 0, "Price history recorded in market_price_history table");
assertTest($history[0]['updated_by'] === 'AutomatedTest', "History records updater identity");
assertTest(abs((float)$history[0]['selling_price'] - ($newMarketPrice + 3.00)) < 0.01, "History records calculated selling price");

// Revert test update
updateDailyMarketPrice((int)$firstItem['id'], (float)$firstItem['market_price'], null, 3.00, 'Revert test', 'System');

// ------------------------------------------------------------
// 6. Page Syntax & File Integrity
// ------------------------------------------------------------
echo "\n6. Testing Page Syntax & Compilation...\n";
$catPageOutput = shell_exec("C:\\wamp64\\bin\\php\\php8.3.14\\php.exe -l categories/vegetables-fruits.php 2>&1");
assertTest(str_contains((string)$catPageOutput, 'No syntax errors detected'), "categories/vegetables-fruits.php passes PHP lint");

$adminPageOutput = shell_exec("C:\\wamp64\\bin\\php\\php8.3.14\\php.exe -l admin/daily_prices.php 2>&1");
assertTest(str_contains((string)$adminPageOutput, 'No syntax errors detected'), "admin/daily_prices.php passes PHP lint");

echo "\n====================================================================\n";
echo "                      TEST RUN RESULTS                              \n";
echo "====================================================================\n";
echo " Total Tests Executed : {$totalTests}\n";
echo " Tests Passed         : {$passedTests}\n";
echo " Tests Failed         : " . ($totalTests - $passedTests) . "\n";
echo "====================================================================\n";

if ($totalTests === $passedTests) {
    echo "STATUS: ALL VEGETABLES & FRUITS PRICE TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "STATUS: SOME TESTS FAILED.\n";
    exit(1);
}
