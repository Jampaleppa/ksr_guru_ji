<?php
declare(strict_types=1);
// admin/payments/permissions.php — Manage payment admin roles

require_once __DIR__ . '/../../payment-core/bootstrap.php';
use KsrPay\SecurityManager;
use KsrPay\AuditLogger;

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Only superadmin can manage permissions
$role = $_SESSION['role'] ?? '';
if (empty($_SESSION['user_id']) || $role !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

// Extra check: must have superadmin payment role
$isSuperadmin = false;
try {
    $chk = $pdo->prepare(
        "SELECT 1 FROM payment_admin_permissions
          WHERE user_id = ? AND payment_role = 'superadmin' AND is_active = 1"
    );
    $chk->execute([(int)$_SESSION['user_id']]);
    $isSuperadmin = (bool)$chk->fetchColumn();
} catch (Throwable $e) {
    // Table may not exist yet
}

$sec    = new SecurityManager();
$audit  = new AuditLogger($pdo);
$csrf   = $sec->generateCSRFToken();
$adminId = (int)$_SESSION['user_id'];
$msg    = '';
$msgType = 'info';

$allRoles = [
    'superadmin'      => 'Complete financial configuration and oversight',
    'finance_admin'   => 'Bank credits, reconciliation, settlement',
    'payment_verifier'=> 'Assigned payment verification only',
    'refund_officer'  => 'Refund processing',
    'delivery_admin'  => 'COD operations management',
    'delivery_partner'=> 'Own collection records only',
    'customer_support'=> 'Payment complaint cases',
    'auditor'         => 'Read-only financial access',
];

// Handle grant/revoke
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
    if (!$isSuperadmin) {
        $msg = 'Only superadmin can manage payment roles.';
        $msgType = 'error';
    } else {
        $action    = trim($_POST['action']       ?? '');
        $targetId  = (int)($_POST['target_user_id'] ?? 0);
        $grantRole = trim($_POST['payment_role'] ?? '');

        if (!array_key_exists($grantRole, $allRoles)) {
            $msg = 'Invalid role.'; $msgType = 'error';
        } elseif ($targetId < 1) {
            $msg = 'Invalid user.'; $msgType = 'error';
        } else {
            try {
                if ($action === 'grant') {
                    if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                        $pdo->prepare(
                            "INSERT INTO payment_admin_permissions (user_id, payment_role, granted_by)
                             VALUES (?, ?, ?)
                             ON CONFLICT(user_id, payment_role) DO UPDATE SET
                               is_active=1, revoked_at=NULL, granted_by=excluded.granted_by"
                        )->execute([$targetId, $grantRole, $adminId]);
                    } else {
                        $pdo->prepare(
                            "INSERT INTO payment_admin_permissions (user_id, payment_role, granted_by)
                             VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE is_active=1, revoked_at=NULL, granted_by=VALUES(granted_by)"
                        )->execute([$targetId, $grantRole, $adminId]);
                    }
                    $msg = "Role '{$grantRole}' granted."; $msgType = 'ok';
                } elseif ($action === 'revoke') {
                    $pdo->prepare(
                        "UPDATE payment_admin_permissions
                            SET is_active=0, revoked_at=NOW()
                          WHERE user_id=? AND payment_role=?"
                    )->execute([$targetId, $grantRole]);
                    $msg = "Role '{$grantRole}' revoked."; $msgType = 'ok';
                }

                $audit->log(
                    eventType: 'PERMISSION_' . strtoupper($action),
                    actorType: 'admin',
                    actorId:   $adminId,
                    notes:     "Target user #{$targetId} role {$grantRole} {$action}d",
                );
            } catch (Throwable $e) {
                $msg = 'Error: ' . $e->getMessage(); $msgType = 'error';
            }
        }
    }
}

// Load current permissions
$permStmt = $pdo->query(
    "SELECT pap.*, u.name AS user_name, u.email, g.name AS grantor_name
       FROM payment_admin_permissions pap
       JOIN users u ON u.id = pap.user_id
       LEFT JOIN users g ON g.id = pap.granted_by
      WHERE pap.is_active = 1
      ORDER BY pap.payment_role, u.name"
);
$permissions = $permStmt ? $permStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Load all admin users for the grant form
$adminUsers = $pdo->query(
    "SELECT id, name, email FROM users WHERE role IN ('admin','super_admin') ORDER BY name"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Permissions — KSR Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1100px;margin:1.5rem auto;padding:0 1rem}
h1{font-size:1.4rem;color:#1a7f4b;margin-bottom:1rem}
.card{background:#fff;border-radius:10px;padding:1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:1.5rem}
table{width:100%;border-collapse:collapse}
th,td{padding:.7rem .9rem;text-align:left;font-size:.88rem;border-bottom:1px solid #f0f0f0}
th{background:#f5f5f5;font-weight:600;color:#555}
.form-row{display:flex;gap:.8rem;flex-wrap:wrap;align-items:flex-end;margin-top:.8rem}
.form-row select,.form-row input{padding:.5rem .8rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem}
.btn-grant{background:#1a7f4b;color:#fff;border:none;border-radius:6px;padding:.5rem 1.2rem;cursor:pointer;font-size:.88rem}
.btn-revoke{background:#e53e3e;color:#fff;border:none;border-radius:6px;padding:.35rem .8rem;cursor:pointer;font-size:.82rem}
.msg-ok{background:#d4edda;color:#155724;padding:.8rem 1rem;border-radius:6px;margin-bottom:1rem}
.msg-error{background:#f8d7da;color:#721c24;padding:.8rem 1rem;border-radius:6px;margin-bottom:1rem}
.role-desc{font-size:.82rem;color:#888}
.nav-links{display:flex;gap:.8rem;margin-bottom:1rem}
.nav-links a{text-decoration:none;color:#1a7f4b;font-size:.9rem;font-weight:600}
.warning-box{background:#fff3cd;border-left:4px solid #ffc107;padding:.8rem 1rem;border-radius:4px;margin-bottom:1rem;font-size:.9rem}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>
<div class="admin-wrap">
  <div class="nav-links">
    <a href="/admin/payments/dashboard.php">← Dashboard</a>
    <a href="/admin/payments/audit-logs.php">Audit Logs</a>
  </div>

  <h1><i class="fas fa-user-shield"></i> Payment Admin Permissions</h1>

  <?php if (!$isSuperadmin): ?>
  <div class="warning-box">
    <i class="fas fa-exclamation-triangle"></i>
    You are viewing permission records. Only the <strong>superadmin</strong> payment role can grant or revoke permissions.
    If this is your first time setup, ask your database administrator to run:
    <code style="display:block;margin-top:.4rem;font-size:.85rem">
      INSERT INTO payment_admin_permissions (user_id, payment_role, granted_by)
      VALUES ([your_user_id], 'superadmin', [your_user_id]);
    </code>
  </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="msg-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if ($isSuperadmin): ?>
  <div class="card">
    <h3 style="margin-top:0;font-size:1rem"><i class="fas fa-plus-circle" style="color:#1a7f4b"></i> Grant Role</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="grant">
      <div class="form-row">
        <div>
          <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.3rem">Admin User</label>
          <select name="target_user_id" required>
            <option value="">— Select user —</option>
            <?php foreach ($adminUsers as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:.3rem">Payment Role</label>
          <select name="payment_role" required>
            <option value="">— Select role —</option>
            <?php foreach ($allRoles as $r => $desc): ?>
              <option value="<?= $r ?>"><?= $r ?> — <?= htmlspecialchars($desc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn-grant"><i class="fas fa-check"></i> Grant Role</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="card">
    <h3 style="margin-top:0;font-size:1rem">Active Permissions</h3>
    <table>
      <thead>
        <tr>
          <th>User</th><th>Email</th><th>Role</th><th>Role Description</th>
          <th>Granted By</th><th>Granted At</th>
          <?php if ($isSuperadmin): ?><th>Action</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($permissions as $p): ?>
        <tr>
          <td><strong><?= htmlspecialchars($p['user_name']) ?></strong></td>
          <td><?= htmlspecialchars($p['email'] ?? '—') ?></td>
          <td><code><?= htmlspecialchars($p['payment_role']) ?></code></td>
          <td class="role-desc"><?= htmlspecialchars($allRoles[$p['payment_role']] ?? '—') ?></td>
          <td><?= htmlspecialchars($p['grantor_name'] ?? 'System') ?></td>
          <td><?= date('d M Y H:i', strtotime($p['granted_at'])) ?></td>
          <?php if ($isSuperadmin): ?>
          <td>
            <form method="post" style="display:inline" onsubmit="return confirm('Revoke this role?')">
              <input type="hidden" name="csrf_token"      value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="action"          value="revoke">
              <input type="hidden" name="target_user_id"  value="<?= (int)$p['user_id'] ?>">
              <input type="hidden" name="payment_role"    value="<?= htmlspecialchars($p['payment_role']) ?>">
              <button type="submit" class="btn-revoke"><i class="fas fa-times"></i> Revoke</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($permissions)): ?>
        <tr><td colspan="7" style="text-align:center;color:#aaa;padding:2rem">No active payment permissions found. Grant the first role above.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3 style="margin-top:0;font-size:1rem">Role Reference</h3>
    <table>
      <thead><tr><th>Role</th><th>Permissions</th></tr></thead>
      <tbody>
      <?php foreach ($allRoles as $r => $desc): ?>
        <tr><td><code><?= $r ?></code></td><td class="role-desc"><?= htmlspecialchars($desc) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
