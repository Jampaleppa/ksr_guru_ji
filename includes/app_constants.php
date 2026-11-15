<?php
// ============================================================
// includes/app_constants.php – Application-wide constants
// Always loaded via config.php. Safe to include multiple times.
// ============================================================
declare(strict_types=1);

// ── Shop identity ────────────────────────────────────────────
if (!defined('SHOP_NAME')) {
    define('SHOP_NAME', getenv('SHOP_NAME') ?: 'KSR GURU JI');
}
if (!defined('SHOP_PHONE')) {
    define('SHOP_PHONE', getenv('SHOP_PHONE') ?: '8088584421');
}
if (!defined('SHOP_UPI')) {
    define('SHOP_UPI', getenv('SHOP_UPI') ?: 'ksrguruji@upi');
}
if (!defined('SHOP_WHATSAPP')) {
    define('SHOP_WHATSAPP', getenv('SHOP_WHATSAPP') ?: '918088584421');
}

// ── Pricing ──────────────────────────────────────────────────
// Selling price = Market price + KSR_DEFAULT_PRICE_MARGIN. NEVER CHANGE.
if (!defined('KSR_DEFAULT_PRICE_MARGIN')) {
    define('KSR_DEFAULT_PRICE_MARGIN', 3.00);
}
if (!defined('KSR_DELIVERY_CHARGE')) {
    define('KSR_DELIVERY_CHARGE', 15.00);
}
if (!defined('KSR_FREE_DELIVERY_ABOVE')) {
    define('KSR_FREE_DELIVERY_ABOVE', 500.00); // orders above ₹500 get free delivery
}

// ── API / Security ───────────────────────────────────────────
// Set KSR_API_KEY env var in Vercel/production secrets.
// When empty, only session-based admin auth is accepted.
if (!defined('KSR_API_KEY')) {
    define('KSR_API_KEY', getenv('KSR_API_KEY') ?: '');
}

// ── Environment ──────────────────────────────────────────────
if (!defined('KSR_IS_PRODUCTION')) {
    $__env = getenv('KSR_ENV') ?: getenv('APP_ENV') ?: '';
    define('KSR_IS_PRODUCTION', in_array($__env, ['production', 'prod'], true));
    unset($__env);
}
