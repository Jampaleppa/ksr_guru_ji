<?php
declare(strict_types=1);

/**
 * scripts/verify_launch_readiness.php
 * CLI-only pre-launch operational readiness verifier for KSR GURU JI PAY.
 * Checks DB driver, 17 tables, 3 views, superadmin seed, UPI status,
 * CORS policy, environment secrets, storage/.htaccess protection, and cron files.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/payment-core/bootstrap.php';

use KsrPay\SecurityManager;

/** @var \PDO $pdo */
$pdo = $pdo ?? getDBConnection();
$driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

$pass = 0;
$warn = 0;
$fail = 0;

$report = function (string $status, string $label, string $detail = '') use (&$pass, &$warn, &$fail): void {
    if ($status === 'PASS') {
        $pass++;
    } elseif ($status === 'WARN') {
        $warn++;
    } else {
        $fail++;
    }
    $suffix = $detail !== '' ? " — {$detail}" : '';
    echo sprintf("  [%s] %s%s\n", $status, $label, $suffix);
};

echo "====================================================================\n";
echo "   KSR GURU JI PAY — Operational Launch-Readiness Verifier          \n";
echo "====================================================================\n\n";

// 1. Database driver & schema
if ($driver === 'mysql') {
    $report('PASS', 'Database driver is MySQL');
} else {
    $report('FAIL', 'Database driver is ' . $driver, 'Live MySQL not connected (using SQLite dev fallback)');
}

$requiredTables = [
    'payment_methods', 'payment_gateway_settings', 'payment_transactions',
    'payment_attempts', 'payment_references', 'payment_bank_credits',
    'payment_reconciliations', 'payment_refunds', 'payment_disputes',
    'payment_webhook_events', 'payment_audit_logs', 'payment_idempotency_keys',
    'payment_settlement_reports', 'cod_collections', 'cod_handovers',
    'payment_support_cases', 'payment_admin_permissions',
];

$missingTables = [];
foreach ($requiredTables as $tbl) {
    try {
        $pdo->query("SELECT 1 FROM {$tbl} LIMIT 1");
    } catch (\Throwable $e) {
        $missingTables[] = $tbl;
    }
}
if (empty($missingTables)) {
    $report('PASS', 'All 17 required payment tables exist');
} else {
    $report('FAIL', 'Missing payment tables (' . count($missingTables) . ')', implode(', ', $missingTables));
}

if ($driver === 'mysql') {
    $requiredViews = ['v_unified_payments', 'v_orders_without_payment', 'v_orders_dual_payment'];
    $missingViews = [];
    foreach ($requiredViews as $view) {
        try {
            $pdo->query("SELECT 1 FROM {$view} LIMIT 1");
        } catch (\Throwable $e) {
            $missingViews[] = $view;
        }
    }
    if (empty($missingViews)) {
        $report('PASS', 'All 3 reconciliation views exist');
    } else {
        $report('FAIL', 'Missing reconciliation views', implode(', ', $missingViews));
    }
} else {
    $report('WARN', 'MySQL reconciliation views check skipped on SQLite');
}

// 2. Superadmin bootstrap check
try {
    $saCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM payment_admin_permissions WHERE payment_role = 'superadmin' AND is_active = 1"
    )->fetchColumn();
    if ($saCount > 0) {
        $report('PASS', 'Superadmin bootstrapped in payment_admin_permissions', "count={$saCount}");
    } else {
        $report('FAIL', 'No active superadmin in payment_admin_permissions', 'Run INSERT seed before launch');
    }
} catch (\Throwable $e) {
    $report('FAIL', 'Could not check superadmin permissions', $e->getMessage());
}

// 3. UPI onboarding gate check
try {
    $upiEnabled = (int)$pdo->query(
        "SELECT is_enabled FROM payment_methods WHERE code = 'upi' LIMIT 1"
    )->fetchColumn();
    $vpa = (string)$pdo->query(
        "SELECT setting_value FROM payment_gateway_settings WHERE gateway_code = 'upi' AND setting_key = 'upi_id' LIMIT 1"
    )->fetchColumn();

    if ($upiEnabled === 1 && $vpa !== '' && !str_starts_with($vpa, 'PLACEHOLDER')) {
        $report('PASS', 'UPI enabled with configured merchant VPA');
    } elseif ($upiEnabled === 0) {
        $report('WARN', 'UPI disabled (is_enabled=0)', 'Expected until SBI merchant onboarding completes');
    } else {
        $report('FAIL', 'UPI enabled without valid non-placeholder VPA');
    }
} catch (\Throwable $e) {
    $report('WARN', 'UPI config check failed', $e->getMessage());
}

// 4. Environment & Security checks
$isProdEnv = (getenv('KSR_ENV') === 'production' || getenv('APP_ENV') === 'production');
$report(
    $isProdEnv ? 'PASS' : 'WARN',
    'Production fail-closed mode (KSR_ENV / APP_ENV = production)',
    $isProdEnv ? 'enabled' : 'not set (SQLite fallback active in dev)'
);

$hasDbPassEnv = getenv('DB_PASS') !== false && getenv('DB_PASS') !== '';
$report(
    $hasDbPassEnv ? 'PASS' : 'WARN',
    'DB_PASS provided via environment variable',
    $hasDbPassEnv ? 'set' : 'not set in current shell environment'
);

$sec = new SecurityManager();
$cors = $sec->getCORSHeaders();
$originHeader = $cors['Access-Control-Allow-Origin'] ?? '*';
if ($originHeader !== '*') {
    $report('PASS', 'SecurityManager CORS allowlist active (no wildcard *)', "default={$originHeader}");
} else {
    $report('FAIL', 'SecurityManager CORS returned wildcard *');
}

// 5. Protected directories & cron scripts
$protectedFiles = [
    '.htaccess',
    'storage/.htaccess',
    'payment-core/.htaccess',
    'scripts/.htaccess',
    'database/.htaccess',
    'scripts/cron_expire_sessions.php',
    'scripts/cron_cod_reconciliation.php',
    'scripts/cron_daily_settlement.php',
];
$missingFiles = array_values(array_filter(
    $protectedFiles,
    fn(string $rel): bool => !file_exists($rootDir . '/' . $rel)
));
if (empty($missingFiles)) {
    $report('PASS', 'All .htaccess guards and 3 cron scripts present');
} else {
    $report('FAIL', 'Missing security/cron files', implode(', ', $missingFiles));
}

echo "\n====================================================================\n";
echo " Summary: PASS={$pass} | WARN={$warn} | FAIL={$fail}\n";
echo "====================================================================\n";

exit($fail > 0 ? 1 : 0);
