<?php
// ============================================================
// admin/inc/settings_logic.php
// ------------------------------------------------------------
// Pure, side-effect-free settings logic extracted from
// admin/settings.php so it can be unit-tested in isolation.
//
// Nothing in this file touches the database, sessions or output.
// ============================================================

if (!function_exists('getDefaultSettings')) {
    /**
     * Return the built-in default settings for a category.
     *
     * @param string $category One of: general, business_hours, delivery,
     *                         payment, notifications, security, integrations,
     *                         staff.
     * @return array The defaults for that category, or [] when unknown.
     */
    function getDefaultSettings(string $category): array
    {
        $defaults = [
            'general' => [
                'store_name' => 'KSR GURU JI',
                'store_tagline' => 'Everything Your Family Needs, Every Day',
                'store_email' => 'kasrguruji26@gmail.com',
                'store_phone' => '+91 8762474406',
                'store_address' => 'kustgi road laxmi nagar Gajendragad-582114',
                'currency' => 'INR',
                'timezone' => 'IST',
            ],
            'business_hours' => [
                'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '21:00'],
                'tuesday' => ['enabled' => true, 'open' => '09:00', 'close' => '20:00'],
                'wednesday' => ['enabled' => true, 'open' => '09:00', 'close' => '20:00'],
                'thursday' => ['enabled' => true, 'open' => '09:00', 'close' => '20:00'],
                'friday' => ['enabled' => true, 'open' => '09:00', 'close' => '20:00'],
                'saturday' => ['enabled' => true, 'open' => '10:00', 'close' => '20:00'],
                'sunday' => ['enabled' => true, 'open' => '09:00', 'close' => '20:00'],
            ],
            'delivery' => [
                'delivery_radius' => 15,
                'min_order_amount' => 200,
                'delivery_fee' => 40,
                'free_delivery_threshold' => 500,
                'delivery_time' => 45,
                'delivery_zones' => ['Central Business District', 'Residential Area North', 'Tech Park Zone'],
            ],
            'payment' => [
                'cash_on_delivery' => true,
                'card_payment' => true,
                'upi_payment' => true,
                'wallet_payment' => true,
                'razorpay_enabled' => true,
                'razorpay_key_id' => 'rzp_test_KSRGuruJi1234',
                'razorpay_key_secret' => 'KSRGuruJiSecret5678App',
                'razorpay_webhook_secret' => 'KSRGuruJiWebhookSecret9012',
                'payment_instructions' => 'Please keep exact change ready for cash payments. UPI payments should be completed before delivery.',
            ],
            'notifications' => [
                'email_new_order' => true,
                'email_delivery' => true,
                'email_daily_summary' => false,
                'sms_order_confirm' => true,
                'sms_delivery_update' => true,
                'push_new_order' => true,
                'push_low_stock' => true,
            ],
            'security' => [
                'password_min_length' => 8,
                'password_expiry' => 90,
                'require_uppercase' => true,
                'require_numbers' => true,
                'require_special_chars' => false,
                'session_timeout' => 30,
                'force_logout' => true,
                'prevent_multiple_sessions' => false,
                'two_factor_auth' => false,
            ],
            'integrations' => [
                'delivery_integration' => false,
                'analytics_integration' => true,
                'sms_integration' => true,
                'email_integration' => true,
                'payment_integration' => true,
                'api_key' => 'ksrg_apikey_7f3b2d8a5c1e9f6a4b7d2c8e5f9a3b6d',
            ],
            'staff' => [
                'staff_list' => [
                    ['name' => 'jkrathod', 'email' => 'jkrathod406@gmail.com', 'phone' => '8762474406', 'role' => 'Administrator', 'permissions' => ['view_orders', 'edit_orders', 'manage_inventory', 'view_reports', 'manage_staff', 'change_settings']],
                    ['name' => 'Priya Sharma', 'email' => 'priya@ksrguru.com', 'phone' => '', 'role' => 'Delivery Manager', 'permissions' => ['view_orders', 'edit_orders', 'view_reports']],
                    ['name' => 'Amit Singh', 'email' => 'amit@ksrguru.com', 'phone' => '', 'role' => 'Inventory Manager', 'permissions' => ['view_orders', 'manage_inventory', 'view_reports']],
                ],
                'role_permissions' => [
                    'administrator' => ['view_orders', 'edit_orders', 'manage_inventory', 'manage_staff', 'view_reports', 'change_settings'],
                    'manager' => ['view_orders', 'edit_orders', 'manage_inventory', 'view_reports'],
                    'staff' => ['view_orders'],
                    'delivery' => ['view_orders'],
                ],
            ],
        ];
        return $defaults[$category] ?? [];
    }
}

if (!function_exists('settings_encode_value')) {
    /**
     * Encode a setting value for storage. Arrays/objects become JSON,
     * scalars are passed through untouched (mirrors settings.php exactly).
     *
     * @param mixed $value
     * @return mixed
     */
    function settings_encode_value($value)
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        return $value;
    }
}

if (!function_exists('settings_decode_value')) {
    /**
     * Decode a stored setting value. JSON arrays are returned as arrays,
     * anything else (scalars, invalid JSON, "null", "true") is returned
     * as the raw string, mirroring settings.php's json_error check.
     */
    function settings_decode_value(string $value): mixed
    {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        return $value;
    }
}

if (!function_exists('settings_merge_with_defaults')) {
    /**
     * Merge stored settings over the defaults for a category.
     *
     * Keys present in $stored win (decoded); keys only in the defaults
     * fall back to the default value. Keys in $stored that are not in
     * $defaults are ignored — exactly as settings.php behaves.
     */
    function settings_merge_with_defaults(array $defaults, array $stored): array
    {
        $currentSettings = [];
        foreach ($defaults as $key => $default) {
            $currentSettings[$key] = isset($stored[$key])
                ? settings_decode_value((string) $stored[$key])
                : $default;
        }
        return $currentSettings;
    }
}

if (!function_exists('settings_normalise_category')) {
    /**
     * Normalise an incoming category/tab value to a known category.
     * Unknown or empty values fall back to 'general'.
     */
    function settings_normalise_category(?string $category): string
    {
        $category = trim((string) $category);
        $known = [
            'general',
            'business_hours',
            'delivery',
            'payment',
            'notifications',
            'security',
            'integrations',
            'staff',
        ];
        return in_array($category, $known, true) ? $category : 'general';
    }
}
