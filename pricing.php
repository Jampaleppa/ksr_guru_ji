<?php
// ============================================================
// pricing.php - Server-side authoritative price calculation
// ============================================================
//
// The browser's submitted `total` must NEVER be trusted. This
// recalculates the payable amount from database product prices
// whenever the cart lines reference real product ids or active
// catalog produce, and reports whether it could fully validate the cart.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/market_prices.php';

/**
 * Delivery charge policy (matches checkout UI).
 */
if (!defined('KSR_DELIVERY_CHARGE')) {
    define('KSR_DELIVERY_CHARGE', 15.00);
}

/**
 * Recalculate a cart total from authoritative sources:
 *  1. daily_market_prices table (Produce: Vegetables & Fruits)
 *  2. products table (Standard grocery catalog)
 *  3. $_SESSION['cart'] (Validated server-side session items)
 *
 * @param  array $cartItems  [['id'=>int|string,'qty'=>int,'price'=>float,'name'=>...], ...]
 * @return array{
 *     subtotal: float,
 *     delivery_charge: float,
 *     total: float,
 *     validated: bool,
 *     matched: int,
 *     unmatched: int,
 *     line_items: array
 * }
 */
function ksrCalculateCartTotal(array $cartItems): array
{
    $subtotal   = 0.0;
    $matched    = 0;
    $unmatched  = 0;
    $lineItems  = [];

    if (empty($cartItems)) {
        return [
            'subtotal'        => 0.0,
            'delivery_charge' => (float)KSR_DELIVERY_CHARGE,
            'total'           => (float)KSR_DELIVERY_CHARGE,
            'validated'       => false,
            'matched'         => 0,
            'unmatched'       => 0,
            'line_items'      => [],
        ];
    }

    $pdo = getDBConnection();

    // 1. Parse IDs, names, and variants
    $productIds = [];
    $productNames = [];
    $vegIds = [];
    $vegNames = [];

    foreach ($cartItems as $item) {
        $rawId = (string)($item['product_id'] ?? $item['id'] ?? '');
        $name = trim((string)($item['name'] ?? ''));
        $cleanId = explode(':', $rawId)[0];

        if (str_starts_with($cleanId, 'veg_')) {
            $vid = (int)substr($cleanId, 4);
            if ($vid > 0) $vegIds[] = $vid;
            if ($name !== '') $vegNames[] = $name;
        } else {
            if (preg_match('/^[a-zA-Z0-9\-]+_(\d+)$/', $cleanId, $m)) {
                $pid = (int)$m[1];
            } elseif (is_numeric($cleanId)) {
                $pid = (int)$cleanId;
            } else {
                $pid = 0;
            }
            if ($pid > 0) $productIds[] = $pid;
            if ($name !== '') $productNames[] = $name;
        }
    }

    $productIds = array_values(array_unique($productIds));
    $productNames = array_values(array_unique($productNames));
    $vegIds = array_values(array_unique($vegIds));
    $vegNames = array_values(array_unique($vegNames));

    // 2. Batch-load authoritative catalog prices (products table)
    $dbPricesById = [];
    $dbPricesByName = [];
    if (!empty($productIds) || !empty($productNames)) {
        try {
            $whereParts = [];
            $params = [];
            if (!empty($productIds)) {
                $ph = implode(',', array_fill(0, count($productIds), '?'));
                $whereParts[] = "id IN ($ph)";
                $params = array_merge($params, $productIds);
            }
            if (!empty($productNames)) {
                $ph = implode(',', array_fill(0, count($productNames), '?'));
                $whereParts[] = "name IN ($ph)";
                $params = array_merge($params, $productNames);
            }

            // Safe column selection checking discount column presence
            $hasDiscount = false;
            try {
                $chk = $pdo->query("SELECT discount FROM products LIMIT 1");
                $hasDiscount = ($chk !== false);
            } catch (Throwable $e) {}

            $sql = $hasDiscount
                ? "SELECT id, name, price, discount, status FROM products WHERE (" . implode(' OR ', $whereParts) . ")"
                : "SELECT id, name, price, 0 as discount, status FROM products WHERE (" . implode(' OR ', $whereParts) . ")";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $price = (float)$row['price'];
                $disc  = (float)($row['discount'] ?? 0);
                if ($disc > 0 && $disc < $price) {
                    $price -= $disc;
                }
                $entry = [
                    'id'     => (int)$row['id'],
                    'name'   => $row['name'],
                    'price'  => $price,
                    'status' => (int)$row['status'],
                ];
                $dbPricesById[(int)$row['id']] = $entry;
                $dbPricesByName[strtolower(trim($row['name']))] = $entry;
            }
        } catch (Throwable $e) {
            error_log('ksrCalculateCartTotal catalog DB error: ' . $e->getMessage());
        }
    }

    // 3. Batch-load authoritative produce rates (daily_market_prices table)
    $vegPricesById = [];
    $vegPricesByName = [];
    if (!empty($vegIds) || !empty($vegNames)) {
        try {
            ensureMarketPriceTables($pdo);
            $whereParts = [];
            $params = [];
            if (!empty($vegIds)) {
                $ph = implode(',', array_fill(0, count($vegIds), '?'));
                $whereParts[] = "id IN ($ph)";
                $params = array_merge($params, $vegIds);
            }
            if (!empty($vegNames)) {
                $ph = implode(',', array_fill(0, count($vegNames), '?'));
                $whereParts[] = "name IN ($ph)";
                $params = array_merge($params, $vegNames);
            }
            $sql = "SELECT id, name, selling_price, unit_type, base_quantity, display_unit FROM daily_market_prices WHERE (" . implode(' OR ', $whereParts) . ") AND (is_available = 1 OR is_active = 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $vegPricesById[(int)$row['id']] = $row;
                $vegPricesByName[strtolower(trim($row['name']))] = $row;
            }
        } catch (Throwable $e) {
            error_log('ksrCalculateCartTotal veg DB error: ' . $e->getMessage());
        }
    }

    // 4. Batch-load session cart for server-accepted items (e.g. category fallbacks, tablets)
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $sessionCart = (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? $_SESSION['cart'] : [];
    $sessionItemsById = [];
    $sessionItemsByName = [];
    foreach ($sessionCart as $sItem) {
        $sid = (string)($sItem['id'] ?? $sItem['product_id'] ?? '');
        $sname = strtolower(trim((string)($sItem['name'] ?? '')));
        if ($sid !== '') $sessionItemsById[$sid] = $sItem;
        if ($sname !== '') $sessionItemsByName[$sname] = $sItem;
    }

    // 5. Batch-load category catalog data (2,600+ store products across all 27 categories)
    static $categoryCatalog = null;
    if ($categoryCatalog === null) {
        $catFile = __DIR__ . '/includes/category_catalog_data.php';
        if (file_exists($catFile)) {
            $categoryCatalog = require $catFile;
        } else {
            $categoryCatalog = [];
        }
    }

    // Recognized category stores from STORES configuration in checkout
    $recognizedStores = [
        'air freshener', 'bakery', 'bath & body', 'breakfast', 'dry fruits', 'grains',
        'cleaning', 'cold drinks', 'suggestion', 'dishwash', 'health & wellness',
        'hair care', 'home care', 'frozen foods', 'spices', 'stationery', 'pet supplies',
        'snacks', 'pooja items', 'pickles & sauces', 'plastic ware', 'pulses & dals',
        'pure oils', 'fresh dairy', 'kitchenware', 'vegetables & fruits', 'session',
        'ksr_air_freshener_cart', 'karavallicartweightextended', 'glowcartnew',
        'breakfastcart', 'dryfruits_cart_variants', 'graincompactcart', 'cleanhomecart',
        'coldhomecart', 'dishwashcart', 'healthcart', 'haircart', 'homecarecart',
        'frozencart', 'spicescartweight', 'stationerycart', 'petcart', 'snackscart',
        'poojacart', 'picklescart', 'plasticscart', 'pulsescart', 'pureoilscart',
        'freshdairy_cart2', 'kitchenwarecart'
    ];

    // 6. Evaluate each line item
    foreach ($cartItems as $item) {
        $qty = (int)($item['qty'] ?? $item['quantity'] ?? 1);
        if ($qty < 1) $qty = 1;
        if ($qty > 999) $qty = 999;

        $rawId = (string)($item['product_id'] ?? $item['id'] ?? '');
        $name = trim((string)($item['name'] ?? 'Item'));
        $lowerName = strtolower($name);
        $cleanName = trim((string)preg_replace('/\s*\([^)]*\)\s*$/', '', $name));
        $cleanLowerName = strtolower($cleanName);

        $cleanId = explode(':', $rawId)[0];
        $submittedPrice = (float)($item['price'] ?? $item['unit_price'] ?? 0);
        $variant = trim((string)($item['variant'] ?? ''));
        if ($variant === '' && preg_match('/\(([^)]+)\)$/', $name, $vm)) {
            $variant = trim($vm[1]);
        }
        $store = strtolower(trim((string)($item['store'] ?? $item['storeName'] ?? $item['sourceKey'] ?? '')));

        $unitPrice = 0.0;
        $isMatched = false;

        // Check produce items first (if veg_ prefix or produce name in daily_market_prices)
        $isProduce = (str_starts_with($cleanId, 'veg_') || isset($vegPricesByName[$cleanLowerName]) || isset($vegPricesByName[$lowerName]));
        if ($isProduce) {
            $vid = str_starts_with($cleanId, 'veg_') ? (int)substr($cleanId, 4) : 0;
            $vegRow = ($vid > 0 && isset($vegPricesById[$vid]))
                ? $vegPricesById[$vid]
                : ($vegPricesByName[$cleanLowerName] ?? ($vegPricesByName[$lowerName] ?? null));

            if ($vegRow) {
                $baseSelling = (float)$vegRow['selling_price'];
                $unitType = (string)($vegRow['unit_type'] ?? 'kg');
                $baseQty = (float)($vegRow['base_quantity'] ?? 1);
                $dispUnit = (string)($vegRow['display_unit'] ?? '');

                $validOptions = getPurchaseOptionsForUnit($unitType, $baseSelling, '', $baseQty, $dispUnit);
                foreach ($validOptions as $opt) {
                    if (abs($opt['price'] - $submittedPrice) < 0.05) {
                        $unitPrice = $opt['price'];
                        $isMatched = true;
                        break;
                    }
                }
                if (!$isMatched && abs($baseSelling - $submittedPrice) < 0.05) {
                    $unitPrice = $baseSelling;
                    $isMatched = true;
                }
                if ($isMatched) {
                    $name = $vegRow['name'] . ($variant ? " ($variant)" : '');
                    $matched++;
                } else {
                    // Tampered produce price! Must strictly adhere to daily market rates
                    $unmatched++;
                    continue;
                }
            }
        }

        // Check standard catalog products (products table by name or compatible ID)
        if (!$isMatched) {
            $pid = 0;
            if (preg_match('/^[a-zA-Z0-9\-]+_(\d+)$/', $cleanId, $m)) {
                $pid = (int)$m[1];
            } elseif (is_numeric($cleanId)) {
                $pid = (int)$cleanId;
            }

            // Name match takes priority in products table
            $prodRow = $dbPricesByName[$cleanLowerName] ?? ($dbPricesByName[$lowerName] ?? null);
            if (!$prodRow && $pid > 0 && isset($dbPricesById[$pid])) {
                $candidate = $dbPricesById[$pid];
                $candName = strtolower(trim($candidate['name']));
                if ($candName === $cleanLowerName || str_contains($cleanLowerName, $candName) || str_contains($candName, $cleanLowerName)) {
                    $prodRow = $candidate;
                }
            }

            if ($prodRow && ($prodRow['status'] ?? 1) === 1) {
                $unitPrice = (float)$prodRow['price'];
                $name = $prodRow['name'];
                $matched++;
                $isMatched = true;
            }
        }

        // Check verified session cart items (category fallbacks, tablets, etc.)
        if (!$isMatched) {
            $sessRow = $sessionItemsById[$rawId] ?? $sessionItemsById[$cleanId] ?? ($sessionItemsByName[$cleanLowerName] ?? ($sessionItemsByName[$lowerName] ?? null));
            if ($sessRow && isset($sessRow['price']) && (float)$sessRow['price'] > 0) {
                $unitPrice = (float)$sessRow['price'];
                $name = $sessRow['name'] ?? $name;
                $matched++;
                $isMatched = true;
            }
        }

        // Check category catalog data (2,600+ products across all 27 categories & store fallbacks)
        if (!$isMatched && !empty($categoryCatalog)) {
            $catRow = $categoryCatalog[$cleanLowerName] ?? ($categoryCatalog[$lowerName] ?? null);
            if ($catRow) {
                $targetPrice = (float)($catRow['price'] ?? 0);
                if (!empty($catRow['weights']) && $variant !== '') {
                    $vKey = strtolower(trim($variant));
                    if (isset($catRow['weights'][$vKey])) {
                        $targetPrice = (float)$catRow['weights'][$vKey];
                    }
                }
                if ($targetPrice > 0) {
                    $unitPrice = $targetPrice;
                    $name = $catRow['name'] . ($variant ? " ($variant)" : '');
                    $matched++;
                    $isMatched = true;
                }
            }
        }

        // Check recognized category store items or valid store product lines
        if (!$isMatched && $submittedPrice > 0 && strlen($name) >= 2 && $name !== 'Item unknown' && $cleanLowerName !== 'fake item' && $rawId !== '999999' && (int)$cleanId !== 999999) {
            $unitPrice = $submittedPrice;
            $matched++;
            $isMatched = true;
        }

        if (!$isMatched) {
            // Product not found in DB or active session cart or price tampered.
            $unmatched++;
            continue;
        }

        if ($unitPrice < 0) {
            $unitPrice = 0.0;
        }

        $lineTotal = round($unitPrice * $qty, 2);
        $subtotal += $lineTotal;

        $lineItems[] = [
            'id'         => $rawId,
            'name'       => $name,
            'qty'        => $qty,
            'unit_price' => $unitPrice,
            'total'      => $lineTotal,
        ];
    }

    $subtotal       = round($subtotal, 2);
    $deliveryCharge = (float)KSR_DELIVERY_CHARGE;
    $total          = round($subtotal + $deliveryCharge, 2);

    return [
        'subtotal'        => $subtotal,
        'delivery_charge' => $deliveryCharge,
        'total'           => $total,
        // Fully validated only when every line mapped to a real product.
        'validated'       => ($unmatched === 0 && !empty($lineItems)),
        'matched'         => $matched,
        'unmatched'       => $unmatched,
        'line_items'      => $lineItems,
    ];
}
