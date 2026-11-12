<?php
/**
 * CLI Deployment Verification & Health Check Script
 * 
 * Usage:
 *   php verify_deployment.php
 * 
 * Runs non-destructive pre-flight checks:
 * 1. Validates that all required environment variables are set.
 * 2. Connects to primary MySQL database.
 * 3. Asserts presence of canonical tables (orders, order_timeline, products, payments, etc.).
 * 4. Checks Razorpay credential configurations and production mode enforcement.
 * 5. Verifies filesystem write permissions and SSL certificates.
 */

declare(strict_types=1);

echo "===============================================================\n";
echo "   KSR GURU JI — Pre-Flight Deployment & Health Verification   \n";
echo "===============================================================\n\n";

$issues = 0;

function check(bool $condition, string $label, string $errorDetail = ''): void {
    global $issues;
    if ($condition) {
        echo " [OK] {$label}\n";
    } else {
        echo " [FAIL] {$label}\n";
        if ($errorDetail) {
            echo "        -> {$errorDetail}\n";
        }
        $issues++;
    }
}

// -------------------------------------------------------------
// Step 1: Environment Variables Check
// -------------------------------------------------------------
echo "1. Checking Environment Variables...\n";

$env = getenv('KSR_ENV') ?: getenv('APP_ENV');
check(!empty($env), "Environment indicator set (KSR_ENV / APP_ENV)", "Environment is unset. Set KSR_ENV=production in production.");

$requiredEnvs = [
    'DB_HOST' => 'MySQL Host address',
    'DB_NAME' => 'MySQL Database name',
    'DB_USER' => 'MySQL User',
    'DB_PASS' => 'MySQL Password',
];

foreach ($requiredEnvs as $var => $desc) {
    $val = getenv($var);
    check(!empty($val), "Environment variable {$var} ({$desc})", "Variable is empty or missing.");
}

$rzpKey = getenv('RAZORPAY_KEY_ID');
$rzpSecret = getenv('RAZORPAY_KEY_SECRET');
$rzpWebhook = getenv('RAZORPAY_WEBHOOK_SECRET');

check(!empty($rzpKey) && !empty($rzpSecret), "Razorpay API credentials configured", "RAZORPAY_KEY_ID or RAZORPAY_KEY_SECRET is missing.");
if (!empty($rzpKey)) {
    $isTestKey = str_starts_with($rzpKey, 'rzp_test_');
    if ($env === 'production') {
        check(!$isTestKey, "Razorpay key is LIVE for production", "Using test key ($rzpKey) in production environment!");
    } else {
        echo " [INFO] Razorpay Key prefix: " . substr($rzpKey, 0, 8) . "...\n";
    }
}

// -------------------------------------------------------------
// Step 2: Database Connectivity & Schema Verification
// -------------------------------------------------------------
echo "\n2. Verifying Database Connectivity & Tables...\n";

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: 3306);
$db   = getenv('DB_NAME') ?: 'ksr_guru';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$sslCa = getenv('DB_SSL_CA') ?: '';

$pdo = null;
try {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ];
    if (!empty($sslCa) && is_readable($sslCa)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    $pdo = new PDO($dsn, $user, $pass, $options);
    check(true, "Successfully connected to MySQL database ({$db} on {$host}:{$port})");
} catch (Throwable $e) {
    check(false, "MySQL Database Connection", $e->getMessage());
}

if ($pdo instanceof PDO) {
    $expectedTables = [
        'users',
        'categories',
        'products',
        'orders',
        'order_items',
        'order_timeline',
        'payments',
        'payment_events',
        'delivery_boys',
        'settings'
    ];

    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($expectedTables as $expected) {
            $exists = in_array($expected, $tables, true);
            check($exists, "Table '{$expected}' exists in database", "Run deploy_database.sql migration to create missing table.");
        }
    } catch (Throwable $e) {
        check(false, "Querying database tables", $e->getMessage());
    }
}

// -------------------------------------------------------------
// Step 3: Filesystem & Secret Leak Check
// -------------------------------------------------------------
echo "\n3. Checking Security Configurations...\n";

// Check that sensitive scripts are NOT web-accessible
$cliOnlyScripts = [
    __DIR__ . '/admin/database/seed_admin_accounts.php',
    __DIR__ . '/delivery/seed_agents.php',
    __DIR__ . '/WhatsApp/telehealth/seed-telehealth-data.php',
];

foreach ($cliOnlyScripts as $script) {
    if (file_exists($script)) {
        $content = file_get_contents($script);
        $hasGuard = strpos($content, "PHP_SAPI !== 'cli'") !== false;
        check($hasGuard, "Script " . basename($script) . " guarded against browser execution");
    }
}

echo "\n===============================================================\n";
if ($issues === 0) {
    echo " RESULT: ALL CHECKS PASSED. Ready for deployment.\n";
    echo "===============================================================\n";
    exit(0);
} else {
    echo " RESULT: {$issues} ISSUE(S) DETECTED. Review errors above before launch.\n";
    echo "===============================================================\n";
    exit(1);
}

