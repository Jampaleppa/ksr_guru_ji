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

/**
 * Delivery charge policy (matches checkout UI).
 */
if (!defined('KSR_DELIVERY_CHARGE')) {
    define('KSR_DELIVERY_CHARGE', 15.00);
}

/**
 * Recalculate a cart total from authoritative product rows.
 *
 * @param  array $cartItems  [['id'=>int,'qty'=>int,'price'=>float,'name'=>...], ...]
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
    foreach ($cartItems as $item) {
        $rawId = $item['id'] ?? null;
        if ($rawId !== null && is_numeric($rawId) && (int)$rawId > 0) {
            $productIds[] = (int)$rawId;
        }
    }
    $productIds = array_values(array_unique($productIds));

    // Batch-load authoritative prices.
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

    foreach ($cartItems as $item) {
        $qty = (int)($item['qty'] ?? $item['quantity'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }
        // Cap quantity to something sane to prevent overflow abuse.
        if ($qty > 999) {
            $qty = 999;
        }

        $rawId    = $item['id'] ?? null;
        $productId = (is_numeric($rawId) && (int)$rawId > 0) ? (int)$rawId : null;

        if ($productId !== null && isset($dbPrices[$productId]) && $dbPrices[$productId]['status'] === 1) {
            // Authoritative price from the database.
            $unitPrice = $dbPrices[$productId]['price'];
            $name      = $dbPrices[$productId]['name'];
            $matched++;
        } else {
            // Product not found / not active — fall back to the submitted
            // price but flag the line so callers can decide to reject.
            $unitPrice = (float)($item['price'] ?? 0);
            $name      = $item['name'] ?? 'Item';
            $unmatched++;
        }

        if ($unitPrice < 0) {
            $unitPrice = 0.0;
        }

        $lineTotal = round($unitPrice * $qty, 2);
        $subtotal += $lineTotal;

        $lineItems[] = [
            'id'        => $productId,
            'name'      => $name,
            'qty'       => $qty,
            'unit_price' => $unitPrice,
            'total'     => $lineTotal,
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
