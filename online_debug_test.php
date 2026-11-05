<?php
/**
 * online_debug_test.php
 * Automated end-to-end debug & test runner for KSR GURU JI
 * Tests both Live Vercel and Local WAMP environments.
 */

$targets = [
    'Live Vercel (ksrguruji-3rpi)' => 'https://ksrguruji-3rpi.vercel.app',
    'Live Vercel (ksrgurujiupload)' => 'https://ksrgurujiupload.vercel.app',
    'Local WAMP'  => 'http://localhost:8090'
];

/**
 * @param string $url
 * @param array<string, mixed> $opts
 * @param string|null $cookieJar
 * @return array<string, mixed>
 */
function runReq(string $url, array $opts = [], ?string $cookieJar = null): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Mobile Safari/537.36');

    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if (!empty($opts['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['headers']);
    }
    if (!empty($opts['post'])) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['post']);
    }

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    return [
        'url' => $url,
        'effective_url' => $finalUrl,
        'http_code' => $httpCode,
        'body' => $body,
        'error' => $err,
        'size' => strlen($body ?? '')
    ];
}

echo "======================================================================\n";
echo "       KSR GURU JI — ONLINE DEBUG & COMPREHENSIVE TEST SUITE          \n";
echo "       Timestamp: " . date('Y-m-d H:i:s T') . "\n";
echo "======================================================================\n\n";

foreach ($targets as $envName => $baseUrl) {
    echo "======================================================================\n";
    echo " Testing Target: {$envName} ({$baseUrl})\n";
    echo "======================================================================\n";

    $cookieFile = sys_get_temp_dir() . '/ksr_test_' . md5($baseUrl) . '.txt';
    if (file_exists($cookieFile)) @unlink($cookieFile);

    $passed = 0;
    $failed = 0;

    /**
     * @param string $title
     * @param array<string, mixed> $res
     * @param array<string, mixed> $checks
     * @return bool
     */
    $assert = function(string $title, array $res, array $checks = []) use (&$passed, &$failed): bool {
        $ok = true;
        $reasons = [];

        if (isset($checks['status']) && $res['http_code'] !== $checks['status']) {
            $ok = false;
            $reasons[] = "HTTP status expected {$checks['status']}, got {$res['http_code']}";
        }

        if (isset($checks['min_size']) && $res['size'] < $checks['min_size']) {
            $ok = false;
            $reasons[] = "Size {$res['size']}B less than min {$checks['min_size']}B";
        }

        if (!empty($checks['contains'])) {
            foreach ($checks['contains'] as $c) {
                if (stripos($res['body'], $c) === false) {
                    $ok = false;
                    $reasons[] = "Missing string: '{$c}'";
                }
            }
        }

        if (!empty($checks['not_contains'])) {
            foreach ($checks['not_contains'] as $nc) {
                if (stripos($res['body'], $nc) !== false) {
                    $ok = false;
                    $reasons[] = "Found forbidden string: '{$nc}'";
                }
            }
        }

        if ($ok) {
            $passed++;
            echo "  [PASS] {$title}\n";
            echo "         --> {$res['effective_url']} ({$res['http_code']}, {$res['size']} bytes)\n";
        } else {
            $failed++;
            echo "  [FAIL] {$title}\n";
            echo "         --> {$res['url']}\n";
            foreach ($reasons as $r) {
                echo "         ! {$r}\n";
            }
            if ($res['error']) {
                echo "         ! cURL Error: {$res['error']}\n";
            }
        }
        return $ok;
    };

    // ── TEST 1: Homepage & Brand Header
    $res = runReq($baseUrl . '/', [], $cookieFile);
    $assert('1. Homepage & Brand Check', $res, [
        'status' => 200,
        'min_size' => 5000,
        'contains' => ['KSR GURU JI', '<!DOCTYPE html>'],
        'not_contains' => ['Fatal error:', 'Parse error:', 'Warning: PDO']
    ]);

    // ── TEST 2: Favicon Assets
    $res = runReq($baseUrl . '/favicon.ico', [], $cookieFile);
    $assert('2. Favicon Accessibility (/favicon.ico)', $res, [
        'status' => 200,
        'min_size' => 500
    ]);

    // ── TEST 3: Shared CSS Stylesheet & Mobile Media Queries
    $res = runReq($baseUrl . '/cart-checkout.css?v=1.2', [], $cookieFile);
    $assert('3. Stylesheet (/cart-checkout.css?v=1.2)', $res, [
        'status' => 200,
        'contains' => ['.cart-title', 'max-width: 767px', 'max-width: 360px'],
        'min_size' => 10000
    ]);

    // ── TEST 4: Clean Cart URL Routing (No 403)
    $res = runReq($baseUrl . '/cart', [], $cookieFile);
    $assert('4. Cart URL (/cart)', $res, [
        'status' => 200,
        'not_contains' => ['403 Forbidden', 'Directory listing', 'Index of /']
    ]);

    // ── TEST 5: Clean Checkout URL & Axis UPI Routing
    $res = runReq($baseUrl . '/checkout', [], $cookieFile);
    $assert('5. Checkout Route & Axis UPI Routing', $res, [
        'status' => 200,
        'contains' => [
            '9590902742@axl',                // Axis VPA for ICICI bypass
            '5411',                          // Merchant Category Code
            'jyampallepparathod-1@oksbi'     // SBI primary handle for QR
        ],
        'not_contains' => [
            '403 Forbidden',
            '959090274@axl',                 // The old typo with missing digit
            'Fatal error:'
        ]
    ]);

    // ── TEST 6: Doctor Consultation Payment & 1-Click UPI Launchers
    $res = runReq($baseUrl . '/WhatsApp/telehealth/payment.php', [], $cookieFile);
    $assert('6. Doctor Consultation Payment Page', $res, [
        'status' => 200,
        'contains' => [
            '9590902742@axl',                // GPay and PhonePe use Axis handle
            'mc=8011',                       // Doctor consultation MCC
            'Cash and Delivery',             // Zero advance fallback
            'QR.png'                         // QR scan image
        ],
        'not_contains' => [
            '959090274@axl',
            'Fatal error:',
            'Parse error:'
        ]
    ]);

    // ── TEST 7: Telehealth Dynamic Cart & Checkout
    $res = runReq($baseUrl . '/WhatsApp/telehealth/checkout.php', [], $cookieFile);
    $assert('7. Telehealth Pharmacy Checkout', $res, [
        'status' => 200,
        'contains' => [
            '9590902742@axl',
            'mc=5912',                       // Pharmacy MCC
            'da_pharmacy_cart',              // Dynamic localStorage cart
            'chk-cart-items'                 // Dynamic container
        ],
        'not_contains' => [
            'Fatal error:',
            'Parse error:'
        ]
    ]);

    // ── TEST 8: Tablet Pharmacy Catalogue & Cart Session
    // 8a: Load shop page
    $res = runReq($baseUrl . '/WhatsApp/telehealth/medicine/tablet.php', [], $cookieFile);
    $assert('8a. Tablet Pharmacy Catalogue Page', $res, [
        'status' => 200,
        'contains' => ['KSR', 'tablet', '<!DOCTYPE html>'],
        'not_contains' => ['Fatal error:', 'Parse error:']
    ]);

    // 8b: Add item to session cart via AJAX action
    $res = runReq($baseUrl . '/WhatsApp/telehealth/medicine/tablet.php?action=cart_add', [
        'post' => ['id' => 1, 'qty' => 2]
    ], $cookieFile);
    $assert('8b. Tablet Pharmacy Add-to-Cart Action', $res, [
        'status' => 200,
        'contains' => ['"ok":true']
    ]);

    // 8c: Access checkout page with active cart
    $res = runReq($baseUrl . '/WhatsApp/telehealth/medicine/tablet.php?page=checkout', [], $cookieFile);
    $assert('8c. Tablet Pharmacy Checkout (Active Cart)', $res, [
        'status' => 200,
        'contains' => [
            '9590902742@axl',                // ICICI-safe Axis VPA
            'mc=5912',                       // Pharmacy MCC
            'Instant UPI',                   // UPI payment option
            'Cash on Delivery'               // COD option
        ],
        'not_contains' => [
            'Your cart is empty',
            'Fatal error:',
            'TypeError:'
        ]
    ]);

    // ── TEST 10: Doctor Appointment Booking & Confirmation Flow
    $res = runReq($baseUrl . '/WhatsApp/telehealth/appointment-confirmation.php', [
        'post' => [
            'doc_id' => 1,
            'type' => 'video',
            'date' => '2026-11-05',
            'time' => '10:30 AM',
            'amount' => '140.00',
            'patient_name' => 'Online Tester',
            'patient_mobile' => '8088584421',
            'payment_method' => 'UPI',
            'txn_id' => 'TEST-UPI-' . time()
        ]
    ], $cookieFile);
    $assert('10. Appointment Confirmation & DB Persistence Flow', $res, [
        'status' => 200,
        'contains' => ['Appointment Confirmed', 'UPI Payment'],
        'not_contains' => ['Fatal error:', 'Parse error:']
    ]);

    // ── TEST 9: Telehealth Order Creation API (Cross-DB Verification)
    $res = runReq($baseUrl . '/WhatsApp/telehealth/api_place_order.php', [
        'post' => [
            'payment_method' => 'UPI',
            'total' => '85.00',
            'transaction_id' => 'DEBUG-' . time(),
            'name' => 'Automated Debugger',
            'phone' => '8088584421',
            'address' => 'Healthcare Hub, Karnataka'
        ]
    ], $cookieFile);
    $assert('9. Telehealth api_place_order.php API', $res, [
        'status' => 200,
        'contains' => ['"success":true', 'order_number'],
        'not_contains' => ['"success":false', 'Database error', 'AUTO_INCREMENT']
    ]);

    // ── TEST 11a: Express Parcel Booking Page & Payment UI
    $res = runReq($baseUrl . '/categories/parcels.php', [], $cookieFile);
    $assert('11a. Express Parcel Booking & UPI Selector', $res, [
        'status' => 200,
        'contains' => [
            'Choose Payment Method',
            '9590902742@axl',                // Axis VPA
            'mc=5411',                       // Retail & Courier MCC
            'Cash on Delivery',
            'Instant UPI'
        ],
        'not_contains' => ['Fatal error:', 'Parse error:']
    ]);

    // ── TEST 11b: Express Parcel Booking Flow with UPI
    $csrf = '';
    if (preg_match('/name="csrf"\s+value="([a-f0-9]+)"/i', $res['body'], $m)) {
        $csrf = $m[1];
    }
    $testUtr = 'UTR' . substr((string)time(), -8);
    $res = runReq($baseUrl . '/categories/parcels.php', [
        'post' => [
            'action' => 'create_request',
            'csrf' => $csrf,
            'pickup_name' => 'Debug Sender',
            'pickup_mobile' => '8088584421',
            'pickup_address' => 'Bus Stand Main Road, Gajendragad',
            'receiver_name' => 'Debug Receiver',
            'receiver_mobile' => '8762474406',
            'delivery_address' => 'Station Road, Gajendragad',
            'parcel_type' => 'Document',
            'payment_method' => 'UPI',
            'payment_ref' => $testUtr
        ]
    ], $cookieFile);
    $assert('11b. Express Parcel UPI Booking & Tracking Verification', $res, [
        'status' => 200,
        'contains' => [
            'Parcel booked successfully',
            'INSTANT UPI',
            'PREPAID',
            $testUtr
        ],
        'not_contains' => ['Fatal error:', 'Parse error:']
    ]);

    // ── TEST 12: Driver Operations Portal (Live Manifest)
    $res = runReq($baseUrl . '/WhatsApp/telehealth/driver-dashboard.php', [], $cookieFile);
    $assert('12. Driver Operations Portal (Live Shift & Telemetry)', $res, [
        'status' => 200,
        'contains' => [
            'DRIVER PORTAL',
            "Today's Shift",
            'Active Jobs',
            'Delivered Today'
        ],
        'not_contains' => ['Fatal error:', 'Parse error:', 'Warning:']
    ]);

    // ── TEST 13: Admin Control Center (Live Cross-Module Telemetry)
    $res = runReq($baseUrl . '/WhatsApp/telehealth/admin-dashboard.php', [], $cookieFile);
    $assert('13. Admin Control Center & Multi-Stream GMV', $res, [
        'status' => 200,
        'contains' => [
            'DOCTOR ASSIST ADMIN',
            'Orders & Parcels',
            'Express Parcels',
            'Revenue (Live)'
        ],
        'not_contains' => ['Fatal error:', 'Parse error:', 'Warning:']
    ]);

    // ── TEST 14: Courier Logistics Fleet Dashboard
    $res = runReq($baseUrl . '/WhatsApp/telehealth/delivery-dashboard.php', [], $cookieFile);
    $assert('14. Courier Logistics Fleet Dashboard', $res, [
        'status' => 200,
        'contains' => [
            'Courier Outpost',
            'Active Manifest',
            'Delivery Logs'
        ],
        'not_contains' => ['Fatal error:', 'Parse error:', 'Warning:']
    ]);

    @unlink($cookieFile);

    $totalTests = $passed + $failed;
    $pct = $totalTests > 0 ? round(($passed / $totalTests) * 100) : 0;
    echo "\n Target {$envName} Results: {$passed}/{$totalTests} Passed ({$pct}%)\n\n";
}

echo "======================================================================\n";
echo "              ALL TEST RUNS FINISHED SUCCESSFULLY                     \n";
echo "======================================================================\n";
