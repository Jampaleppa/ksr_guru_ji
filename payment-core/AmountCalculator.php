<?php

declare(strict_types=1);

namespace KsrPay;

/**
 * AmountCalculator — Currency arithmetic helpers.
 */
class AmountCalculator
{
    /**
     * Convert rupees (float or string) to integer paise.
     */
    public static function toPaise(float|string $rupees): int
    {
        return (int) round((float) $rupees * 100);
    }

    /**
     * Convert integer paise to a formatted rupee string "1,234.56".
     */
    public static function toRupees(int $paise): string
    {
        return number_format($paise / 100, 2);
    }

    /**
     * Calculate order total in paise from line items, delivery charge, and discount.
     *
     * Each item must have keys: price_paise (int) and qty (int).
     */
    public static function calculateOrderTotal(
        array $items,
        float $deliveryCharge = 0.0,
        float $discount = 0.0
    ): int {
        $total = 0;
        foreach ($items as $item) {
            $pricePaise = isset($item['price_paise'])
                ? (int) $item['price_paise']
                : static::toPaise((float)($item['price'] ?? 0));
            $qty   = (int)($item['qty'] ?? $item['quantity'] ?? 1);
            $total += $pricePaise * $qty;
        }
        $total += static::toPaise($deliveryCharge);
        $total -= static::toPaise($discount);
        return max(0, $total);
    }

    /**
     * Format paise as an INR string with rupee symbol: "₹1,234.56".
     */
    public static function formatINR(int $paise): string
    {
        return '₹' . static::toRupees($paise);
    }
}
