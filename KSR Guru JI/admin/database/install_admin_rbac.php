<?php
// ============================================================
// admin/database/install_admin_rbac.php
// ------------------------------------------------------------
// One-click installer for the admin RBAC / security schema.
//
// Usage (CLI):   php admin/database/install_admin_rbac.php
// Usage (web):   open  /admin/database/install_admin_rbac.php
//
// It executes admin_rbac_schema.sql against the configured
// database. The script is idempotent (CREATE IF NOT EXISTS +
// INSERT ... ON DUPLICATE KEY UPDATE) so it is safe to re-run.
// ============================================================

require_once __DIR__ . '/../../config.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$sqlFile = __DIR__ . '/admin_rbac_schema.sql';
if (!is_file($sqlFile)) {
    exit("ERROR: schema file not found at {$sqlFile}\n");
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    exit("ERROR: could not read schema file.\n");
}

try {
    $pdo = getDBConnection();
    $pdo->exec($sql);

    echo "SUCCESS: admin RBAC schema applied.\n";

    // Quick verification.
    $tables = [
        'admin_users',
        'admin_roles',
        'admin_permissions',
        'admin_role_permissions',
        'admin_sessions',
        'admin_otp',
        'admin_login_attempts',
        'admin_audit_logs'
    ];

    foreach ($tables as $t) {
        $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn();
        echo sprintf("  %-9s %s\n", $exists ? '[ok]' : '[MISSING]', $t);
    }

    $admins = (int) $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    $perms  = (int) $pdo->query("SELECT COUNT(*) FROM admin_permissions")->fetchColumn();
    echo "\nAdmin accounts: {$admins}\nPermissions: {$perms}\n";

    if ($admins === 0) {
        echo "\nNo accounts yet. Create the production admins with:\n";
        echo "  php admin/database/seed_admin_accounts.php\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
