<?php
// ============================================================
// admin/database/seed_admin_accounts.php
// ------------------------------------------------------------
// Creates / updates the production administrator accounts from
// admin/database/admin_accounts.php.
//
// Usage (CLI):  php admin/database/seed_admin_accounts.php
//
// The script is idempotent: running it again updates the existing
// rows (password, role, status) instead of failing or duplicating.
//
// It requires the RBAC schema to exist first:
//     php admin/database/install_admin_rbac.php
// ============================================================

require_once __DIR__ . '/../../config.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

/**
 * Normalise an Indian mobile number to E.164 (91XXXXXXXXXX).
 */
function normalisePhone(?string $phone): ?string
{
    if ($phone === null || trim($phone) === '') {
        return null;
    }
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 10) {
        return '91' . $digits;
    }
    return $digits;
}

$accountsFile = __DIR__ . '/admin_accounts.php';
if (!is_file($accountsFile)) {
    exit("ERROR: missing credentials file {$accountsFile}\n");
}

/** @var array<int,array<string,mixed>> $accounts */
$accounts = require $accountsFile;

if (!$accounts) {
    exit("ERROR: no accounts defined.\n");
}

try {
    $pdo = getDBConnection();

    // The schema must be present.
    $hasRoles = $pdo->query("SHOW TABLES LIKE 'admin_roles'")->fetchColumn();
    if (!$hasRoles) {
        exit("ERROR: RBAC tables missing. Run: php admin/database/install_admin_rbac.php\n");
    }

    $update = $pdo->prepare(
        "UPDATE admin_users
            SET email          = :email,
                password_hash  = :hash,
                full_name      = :full_name,
                phone          = :phone,
                role_id        = (SELECT id FROM admin_roles WHERE role_key = :role_key),
                status         = :status,
                is_super_admin = :is_super,
                must_change_password = 0
          WHERE username = :username"
    );

    $insert = $pdo->prepare(
        "INSERT INTO admin_users
            (username, email, password_hash, full_name, phone, role_id,
             status, is_super_admin, must_change_password)
         VALUES
            (:username, :email, :hash, :full_name, :phone,
             (SELECT id FROM admin_roles WHERE role_key = :role_key),
             :status, :is_super, 0)"
    );

    foreach ($accounts as $account) {
        $username = (string) $account['username'];
        $email    = (string) $account['email'];

        // Guard: an identifier must not collide with another account.
        $clash = $pdo->prepare(
            "SELECT id, username, email FROM admin_users
              WHERE (username = :id OR email = :id2)
                AND username <> :username
              LIMIT 1"
        );
        $clash->execute(['id' => $username, 'id2' => $username, 'username' => $username]);
        if ($other = $clash->fetch()) {
            printf(
                "  [SKIP] %-16s identifier also used by #%d (%s / %s)\n",
                $username,
                $other['id'],
                $other['username'],
                $other['email']
            );
            continue;
        }

        $params = [
            'username'  => $username,
            'email'     => $email,
            'hash'      => password_hash((string) $account['password'], PASSWORD_DEFAULT),
            'full_name' => (string) $account['full_name'],
            'phone'     => normalisePhone($account['phone'] ?? null),
            'role_key'  => (string) $account['role_key'],
            'status'    => (string) ($account['status'] ?? 'active'),
            'is_super'  => (int) ($account['is_super_admin'] ?? 0),
        ];

        $update->execute($params);
        $action = 'UPDATED';

        if ($update->rowCount() === 0) {
            // rowCount() is 0 for "no change" too, so confirm absence.
            $exists = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
            $exists->execute([$username]);
            if ($exists->fetch()) {
                $action = 'UNCHANGED';
            } else {
                $insert->execute($params);
                $action = 'CREATED';
            }
        }

        printf(
            "  [%s] %-16s role=%-11s super=%d phone=%s\n",
            $action,
            $username,
            $account['role_key'],
            $params['is_super'],
            $params['phone'] ?? '-'
        );
    }

    // Report the final state.
    echo "\nAdministrator accounts in admin_users:\n";
    foreach (
        $pdo->query(
            "SELECT au.id, au.username, au.email, ar.role_key, au.status,
                au.is_super_admin, au.phone
           FROM admin_users au
           JOIN admin_roles ar ON ar.id = au.role_id
          ORDER BY au.is_super_admin DESC, au.id"
        )->fetchAll() as $row
    ) {
        printf(
            "  #%d  %-18s | %-34s | %-11s | %-8s | OTP: %s\n",
            $row['id'],
            $row['username'],
            $row['email'],
            $row['role_key'],
            $row['status'],
            $row['phone'] ?? 'none'
        );
    }

    echo "\nDone.\n";
} catch (Throwable $e) {
    exit('ERROR: ' . $e->getMessage() . "\n");
}
