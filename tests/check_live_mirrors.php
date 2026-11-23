<?php
/**
 * Test live deployment mirrors for checkout and payment configurations
 */

$targets = [
    'Vercel Main'       => 'https://ksrguruji.vercel.app',
    'Vercel Alternate'  => 'https://ksrguruji-3rpi.vercel.app',
    'Netlify'           => 'https://ksr-guru-ji.netlify.app',
    'Cloudflare Pages'  => 'https://ksr-guru-ji.pages.dev',
];

echo "=== CHECKING LIVE DEPLOYMENT MIRRORS ===\n\n";

foreach ($targets as $label => $baseUrl) {
    echo "--- Testing $label ($baseUrl) ---\n";
    
    // 1. Check checkout.php HTML
    $checkoutUrl = $baseUrl . '/checkout.php';
    $ch = curl_init($checkoutUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $hasBank = (strpos($html, 'bank_transfer') !== false);
    $hasName = (strpos($html, 'JAMPALEPPA') !== false);
    $hasAcc  = (strpos($html, '00000033196470488') !== false);
    $hasIfsc = (strpos($html, 'SBIN0011275') !== false);
    $hasUpi  = (strpos($html, 'jyampallepparathod-1@oksbi') !== false);

    echo "  HTTP Status      : $httpCode\n";
    echo "  bank_transfer    : " . ($hasBank ? "✅ PRESENT" : "❌ MISSING") . "\n";
    echo "  Account Name     : " . ($hasName ? "✅ PRESENT" : "❌ MISSING") . "\n";
    echo "  Account Number   : " . ($hasAcc ? "✅ PRESENT" : "❌ MISSING") . "\n";
    echo "  IFSC Code        : " . ($hasIfsc ? "✅ PRESENT" : "❌ MISSING") . "\n";
    echo "  UPI ID           : " . ($hasUpi ? "✅ PRESENT" : "❌ MISSING") . "\n";

    // 2. Test live order validation POST with COD
    $cookieJar = tempnam(sys_get_temp_dir(), 'cj_');
    
    // First, hit checkout to get session/CSRF if any
    $ch = curl_init($checkoutUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
    ]);
    $initialHtml = curl_exec($ch);
    curl_close($ch);

    // Extract CSRF token
    $csrf = '';
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $initialHtml, $m)) {
        $csrf = $m[1];
    } elseif (preg_match('/csrf_token\s*=\s*[\'"]([^\'"]+)[\'"]/i', $initialHtml, $m)) {
        $csrf = $m[1];
    }

    // Now test place_order with a sample valid catalog item (e.g. Tomato or Onion with standard catalog pricing)
    $orderPayload = [
        'csrf_token' => $csrf,
        'customer_name' => 'Verification Bot',
        'customer_phone' => '9876543210',
        'customer_address' => '123 Test Street, MG Road',
        'customer_city' => 'Bengaluru',
        'customer_pincode' => '560001',
        'payment_method' => 'cod',
        'items' => json_encode([
            [
                'id' => 1,
                'name' => 'Fresh Tomatoes',
                'price' => 30.00,
                'quantity' => 1,
                'unit' => 'kg'
            ]
        ])
    ];

    $ch = curl_init($checkoutUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($orderPayload),
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json'
        ]
    ]);
    $resp = curl_exec($ch);
    $respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($cookieJar);

    $json = json_decode($resp, true);
    if (is_array($json)) {
        if (!empty($json['success'])) {
            echo "  COD Checkout Test: ✅ SUCCESS (Order #" . ($json['order_id'] ?? $json['order_number'] ?? 'N/A') . ")\n";
        } else {
            echo "  COD Checkout Test: ⚠️ Returned message: " . ($json['message'] ?? $json['error'] ?? substr($resp, 0, 100)) . "\n";
        }
    } else {
        echo "  COD Checkout Test: Response Code $respCode (Raw: " . substr(strip_tags($resp), 0, 80) . ")\n";
    }
    
    echo "\n";
}
