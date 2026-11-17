<?php
// ============================================================
// api/pricing.php - Server-side authoritative price calculation
// ============================================================
//
// The browser's submitted `total` must NEVER be trusted. This
// recalculates the payable amount from database product prices
// whenever the cart lines reference real product ids, and reports
// whether it could fully validate the cart.
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
 * Recalculate a cart total from authoritative product rows.
 * Supports both standard catalog products and daily produce (Vegetables & Fruits).
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

    $productIds = [];
    $vegIds = [];

    foreach ($cartItems as $item) {
        $rawId = (string)($item['product_id'] ?? $item['id'] ?? '');
        if (str_starts_with($rawId, 'veg_')) {
            $cleaned = explode(':', substr($rawId, 4))[0];
            if (is_numeric($cleaned) && (int)$cleaned > 0) {
                $vegIds[] = (int)$cleaned;
            }
        } elseif (is_numeric($rawId) && (int)$rawId > 0) {
            $productIds[] = (int)$rawId;
        }
    }
    $productIds = array_values(array_unique($productIds));
    $vegIds = array_values(array_unique($vegIds));

    // 1. Batch-load authoritative catalog prices (products table)
    $dbPrices = [];
    if (!empty($productIds)) {
        try {
            $pdo = getDBConnection();
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT id, name, price, discount, status FROM products WHERE id IN ({$placeholders})"
            );
            $stmt->execute($productIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                // Effective price honours a discount column when present.
                $price = (float)$row['price'];
                $disc  = (float)($row['discount'] ?? 0);
                if ($disc > 0 && $disc < $price) {
                    $price = $price - $disc;
                }
                $dbPrices[(int)$row['id']] = [
                    'name'   => $row['name'],
                    'price'  => $price,
                    'status' => (int)$row['status'],
                ];
            }
        } catch (Throwable $e) {
            error_log('ksrCalculateCartTotal DB error: ' . $e->getMessage());
        }
    }

    // 2. Batch-load authoritative produce rates (daily_market_prices table)
    $vegPrices = [];
    if (!empty($vegIds)) {
        try {
            $pdo = getDBConnection();
            ensureMarketPriceTables($pdo);
            $placeholders = implode(',', array_fill(0, count($vegIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT id, name, selling_price, unit_type, base_quantity, display_unit FROM daily_market_prices WHERE id IN ({$placeholders}) AND (is_available = 1 OR is_active = 1)"
            );
            $stmt->execute($vegIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $vegPrices[(int)$row['id']] = $row;
            }
        } catch (Throwable $e) {
            error_log('ksrCalculateCartTotal veg DB error: ' . $e->getMessage());
        }
    }

    foreach ($cartItems as $item) {
        $qty = (int)($item['qty'] ?? $item['quantity'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }
        // Cap quantity to something sane to prevent overflow abuse.
        if ($qty > 999) {
            $qty = 999;
        }

        $rawId = (string)($item['product_id'] ?? $item['id'] ?? '');
        $productId = (is_numeric($rawId) && (int)$rawId > 0) ? (int)$rawId : null;
        $unitPrice = 0.0;
        $name = $item['name'] ?? 'Item';
        $isMatched = false;

        // Check standard catalog products
        if ($productId !== null && isset($dbPrices[$productId]) && $dbPrices[$productId]['status'] === 1) {
            $unitPrice = $dbPrices[$productId]['price'];
            $name      = $dbPrices[$productId]['name'];
            $matched++;
            $isMatched = true;
        }
        // Check produce items from daily_market_prices
        elseif (str_starts_with($rawId, 'veg_')) {
            $cleaned = explode(':', substr($rawId, 4))[0];
            $vegId = (is_numeric($cleaned) && (int)$cleaned > 0) ? (int)$cleaned : 0;
            if ($vegId > 0 && isset($vegPrices[$vegId])) {
                $row = $vegPrices[$vegId];
                $baseSelling = (float)$row['selling_price'];
                $submittedPrice = (float)($item['price'] ?? $item['unit_price'] ?? 0);
                $unitType = (string)($row['unit_type'] ?? 'kg');
                $baseQty = (float)($row['base_quantity'] ?? 1);
                $dispUnit = (string)($row['display_unit'] ?? '');

                // Verify submitted unit price against valid authoritative variant options
                $validOptions = getPurchaseOptionsForUnit($unitType, $baseSelling, '', $baseQty, $dispUnit);
                $isPriceValid = false;
                foreach ($validOptions as $opt) {
                    if (abs($opt['price'] - $submittedPrice) < 0.05) {
                        $isPriceValid = true;
                        $unitPrice = $opt['price'];
                        break;
                    }
                }
                if (!$isPriceValid && abs($baseSelling - $submittedPrice) < 0.05) {
                    $isPriceValid = true;
                    $unitPrice = $baseSelling;
                }

                if ($isPriceValid) {
                    $name = $item['name'] ?? $row['name'];
                    $matched++;
                    $isMatched = true;
                }
            }
        }

        if (!$isMatched) {
            // Product not found in DB or not active or price tampered.
            // Mark as unmatched so the checkout layer can reject this cart.
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
        'validated'       => ($unmatched === 0),
        'matched'         => $matched,
        'unmatched'       => $unmatched,
        'line_items'      => $lineItems,
    ];
}
