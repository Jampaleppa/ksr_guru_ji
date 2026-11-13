<?php
declare(strict_types=1);

/**
 * payment-methods/upi/config.php
 * Returns UPI merchant config from payment_gateway_settings.
 * Falls back to safe defaults if not yet configured.
 * NEVER outputs is_secret=1 values to the browser.
 */

function getUpiConfig(\PDO $pdo): array
{
    $defaults = [
        'merchant_name' => 'KSR GURU JI',
        'upi_id'        => '',           // empty until SBI onboarding complete
        'display_name'  => 'KSR GURU JI PAY',
        'qr_image_path' => '',           // empty until QR image uploaded
    ];

    try {
        $stmt = $pdo->prepare(
            "SELECT setting_key, setting_value, is_secret
             FROM payment_gateway_settings
             WHERE gateway_code = 'upi' AND is_secret = 0"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $defaults[$row['setting_key']] = $row['setting_value'];
        }
    } catch (\Throwable $e) {
        error_log('getUpiConfig error: ' . $e->getMessage());
    }

    return $defaults;
}

function getBankTransferConfig(\PDO $pdo): array
{
    $defaults = [
        'account_holder' => 'KSR GURU JI',
        'bank_name'      => 'State Bank of India',
        'account_number' => '', // set via admin settings
        'ifsc'           => '',
        'account_type'   => 'Savings',
    ];

    try {
        $stmt = $pdo->prepare(
            "SELECT setting_key, setting_value
             FROM payment_gateway_settings
             WHERE gateway_code = 'bank_transfer' AND is_secret = 0"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $defaults[$row['setting_key']] = $row['setting_value'];
        }
    } catch (\Throwable $e) {
        error_log('getBankTransferConfig error: ' . $e->getMessage());
    }

    return $defaults;
}

