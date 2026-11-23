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
        'merchant_name' => 'mr.JAMPALEPPA KRUSHNAPPA RATHOD',
        'upi_id'        => 'jyampallepparathod-1@oksbi',
        'display_name'  => 'KSR GURU JI PAY',
        'qr_image_path' => '/images/ksr_upi_qr.png',
        'bank_name'     => 'State Bank of India',
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
            $val = trim((string)($row['setting_value'] ?? ''));
            if ($val !== '' && !str_starts_with($val, 'PLACEHOLDER')) {
                $defaults[$row['setting_key']] = $val;
            }
        }
    } catch (\Throwable $e) {
        error_log('getUpiConfig error: ' . $e->getMessage());
    }

    return $defaults;
}

function getBankTransferConfig(\PDO $pdo): array
{
    $defaults = [
        'account_holder' => 'mr.JAMPALEPPA KRUSHNAPPA RATHOD',
        'bank_name'      => 'State Bank of India',
        'account_number' => '00000033196470488',
        'ifsc'           => 'SBIN0011275',
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
            $val = trim((string)($row['setting_value'] ?? ''));
            if ($val !== '' && !str_starts_with($val, 'PLACEHOLDER') && $val !== 'SBIN0000000') {
                $defaults[$row['setting_key']] = $val;
            }
        }
        if (isset($defaults['account_name']) && (empty($defaults['account_holder']) || $defaults['account_holder'] === 'KSR GURU JI')) {
            $defaults['account_holder'] = $defaults['account_name'];
        }
    } catch (\Throwable $e) {
        error_log('getBankTransferConfig error: ' . $e->getMessage());
    }

    return $defaults;
}
