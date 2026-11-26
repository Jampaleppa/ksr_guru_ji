<?php
declare(strict_types=1);
// admin/payments/support-cases.php — Payment support case management

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;
use KsrPay\AuditLogger;

SecurityManager::requireAdminPage();

$sec     = new SecurityManager();
$audit   = new AuditLogger($pdo);
$csrf    = $sec->generateCSRFToken();
$adminId = (int)$_SESSION['user_id'];
$msg     = '';
$msgType = 'ok';

$allowedStatuses = ['open', 'investigating', 'awaiting_customer', 'resolved', 'closed'];

// ── Handle POST: update case status / assign ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid CSRF token.'; $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $caseId = (int)($_POST['case_id'] ?? 0);

        if ($caseId < 1) {
            $msg = 'Invalid case ID.'; $msgType = 'error';
        } elseif ($action === 'update_status') {
            $newStatus  = $_POST['new_status'] ?? '';
            $resolution = trim($_POST['resolution'] ?? '');

            if (!in_array($newStatus, $allowedStatuses, true)) {
                $msg = 'Invalid status.'; $msgType = 'error';
            } else {
                $nowSql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') ? "datetime('now')" : "NOW()";
                $extra  = '';
                if ($newStatus === 'resolved') {
                    $extra = ", resolved_at = COALESCE(resolved_at, {$nowSql})";
                } elseif ($newStatus === 'closed') {
                    $extra = ", closed_at = COALESCE(closed_at, {$nowSql})";
                }

                $stmt = $pdo->prepare(
                    "UPDATE payment_support_cases
                        SET status = ?, resolution = ?{$extra}
                      WHERE id = ?"
                );
                $stmt->execute([$newStatus, $resolution ?: null, $caseId]);

                if ($stmt->rowCount() > 0) {
                    $audit->log(
                        eventType: 'SUPPORT_CASE_UPDATED',
                        notes:     "Case #{$caseId} -> {$newStatus}. Resolution: {$resolution}",
                        actorType: 'admin',
                        actorId:   $adminId,
                    );
                    $msg = "Case #{$caseId} updated to '{$newStatus}'.";
                } else {
                    $msg = 'No changes made (case not found or unchanged).'; $msgType = 'error';
                }
            }
        } elseif ($action === 'assign') {
            $assignTo = (int)($_POST['assign_to'] ?? 0);
            $stmt = $pdo->prepare("UPDATE payment_support_cases SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$assignTo ?: null, $caseId]);
            $msg = $stmt->rowCount() ? "Case #{$caseId} assignment updated." : 'No change.';
        }
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$statusFilter = trim($_GET['status'] ?? '');
$typeFilter   = trim($_GET['type']   ?? '');
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 25;
$offset       = ($page - 1) * $limit;

if (!function_exists('qs')) {
    function qs(PDO $pdo, string $sql, array $p = []): int
    {
        $s = $pdo->prepare($sql);
        $s->execute($p);
        return (int)$s->fetchColumn();
    }
}

$openCount          = qs($pdo, "SELECT COUNT(*) FROM payment_support_cases WHERE status='open'");
$investigatingCount = qs($pdo, "SELECT COUNT(*) FROM payment_support_cases WHERE status='investigating'");
$awaitingCount      = qs($pdo, "SELECT COUNT(*) FROM payment_support_cases WHERE status='awaiting_customer'");
$resolvedToday      = qs($pdo, "SELECT COUNT(*) FROM payment_support_cases WHERE status IN ('resolved','closed') AND DATE(resolved_at)=CURDATE()");

// ── Case List ─────────────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($statusFilter !== '') { $where[] = "sc.status = ?";    $params[] = $statusFilter; }
if ($typeFilter   !== '') { $where[] = "sc.case_type = ?"; $params[] = $typeFilter;   }
if ($search       !== '') {
    $where[] = "(sc.case_uid LIKE ? OR u.name LIKE ? OR u.phone LIKE ? OR sc.subject LIKE ?)";
    $like    = "%{$search}%";
    $params  = array_merge($params, [$like, $like, $like, $like]);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = qs($pdo, "SELECT COUNT(*) FROM payment_support_cases sc LEFT JOIN users u ON u.id = sc.user_id {$whereSQL}", $params);
$pages = max(1, (int)ceil($total / $limit));

$listStmt = $pdo->prepare(
    "SELECT sc.id, sc.case_uid, sc.case_type, sc.subject, sc.status,
            sc.priority, sc.opened_at, sc.resolved_at, sc.closed_at,
            sc.resolution, sc.assigned_to,
            u.name AS customer, u.phone,
            pt.payment_id, pt.amount_paise, pt.method,
            aa.name AS assigned_name
       FROM payment_support_cases sc
       LEFT JOIN users u  ON u.id  = sc.user_id
       LEFT JOIN payment_transactions pt ON pt.id = sc.payment_transaction_id
       LEFT JOIN users aa ON aa.id = sc.assigned_to
     {$whereSQL}
     ORDER BY
       CASE sc.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
       CASE sc.status WHEN 'open' THEN 1 WHEN 'investigating' THEN 2 WHEN 'awaiting_customer' THEN 3 WHEN 'resolved' THEN 4 ELSE 5 END,
       sc.opened_at DESC
     LIMIT {$limit} OFFSET {$offset}"
);
$listStmt->execute($params);
$cases = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$adminUsers = $pdo->query("SELECT id, name FROM users WHERE role IN ('admin','super_admin') ORDER BY name LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$caseTypes  = $pdo->query("SELECT DISTINCT case_type FROM payment_support_cases WHERE case_type IS NOT NULL ORDER BY case_type")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Support Cases — KSR Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1250px;margin:1.5rem auto;padding:0 1rem}
h1{font-size:1.4rem;color:#1a7f4b;margin-bottom:1rem}
.nav-links{display:flex;gap:.8rem;margin-bottom:1rem;flex-wrap:wrap}
.nav-links a{text-decoration:none;color:#1a7f4b;font-size:.9rem;font-weight:600}
.stats-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
.stat-card{flex:1;min-width:160px;background:#fff;border-radius:10px;padding:1rem 1.4rem;box-shadow:0 2px 10px rgba(0,0,0,.07)}
.stat-val{font-size:1.4rem;font-weight:800;color:#1a7f4b}
.stat-label{font-size:.82rem;color:#888;margin-top:.2rem}
.filter-bar{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center}
.filter-bar input,.filter-bar select{padding:.45rem .7rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem}
.btn{padding:.45rem 1rem;background:#1a7f4b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.85rem;font-weight:600}
.btn-sm{padding:.25rem .6rem;font-size:.78rem}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.07)}
th,td{padding:.6rem .85rem;text-align:left;font-size:.86rem;border-bottom:1px solid #f0f0f0;vertical-align:top}
th{background:#f5f5f5;font-weight:600;color:#555}
.badge{display:inline-block;padding:.2rem .55rem;border-radius:12px;font-size:.75rem;font-weight:600;color:#fff;background:#607d8b}
.bg-danger{background:#e53e3e} .bg-warn{background:#ff9800} .bg-info{background:#2196f3} .bg-ok{background:#1a7f4b}
.p-urgent{border-left:4px solid #e53e3e} .p-high{border-left:4px solid #fd7e14}
.p-medium{border-left:4px solid #f9a825} .p-low{border-left:4px solid #1a7f4b}
.msg-ok{background:#d4edda;color:#155724;padding:.8rem 1rem;border-radius:6px;margin-bottom:1rem}
.msg-error{background:#f8d7da;color:#721c24;padding:.8rem 1rem;border-radius:6px;margin-bottom:1rem}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center}
.modal-backdrop.open{display:flex}
.modal-box{background:#fff;border-radius:10px;padding:1.4rem;width:min(480px,92vw);box-shadow:0 10px 30px rgba(0,0,0,.2)}
.modal-box h3{margin:0 0 1rem;font-size:1.05rem;color:#1a7f4b}
.modal-box label{display:block;font-weight:600;font-size:.85rem;margin:.6rem 0 .25rem}
.modal-box select,.modal-box textarea{width:100%;padding:.5rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem;box-sizing:border-box}
.modal-actions{display:flex;gap:.6rem;justify-content:flex-end;margin-top:1rem}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>
<div class="admin-wrap">
  <div class="nav-links">
    <a href="/admin/payments/dashboard.php">← Dashboard</a>
    <a href="/admin/payments/disputes.php">Disputes</a>
    <a href="/admin/payments/refunds.php">Refunds</a>
    <a href="/admin/payments/cod.php">COD</a>
  </div>

  <h1><i class="fas fa-headset"></i> Payment Support Cases</h1>

  <?php if ($msg): ?>
    <div class="msg-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="stats-row">
    <div class="stat-card"><div class="stat-val" style="color:#e53e3e"><?= $openCount ?></div><div class="stat-label">Open Cases</div></div>
    <div class="stat-card"><div class="stat-val" style="color:#ff9800"><?= $investigatingCount ?></div><div class="stat-label">Investigating</div></div>
    <div class="stat-card"><div class="stat-val" style="color:#2196f3"><?= $awaitingCount ?></div><div class="stat-label">Awaiting Customer</div></div>
    <div class="stat-card"><div class="stat-val"><?= $resolvedToday ?></div><div class="stat-label">Resolved Today</div></div>
  </div>

  <form method="get" class="filter-bar">
    <input type="text" name="search" placeholder="Case UID / customer / subject" value="<?= htmlspecialchars($search) ?>">
    <select name="status">
      <option value="">All Statuses</option>
      <?php foreach ($allowedStatuses as $st): ?>
        <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $st)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="type">
      <option value="">All Types</option>
      <?php foreach ($caseTypes as $ct): ?>
        <option value="<?= htmlspecialchars($ct) ?>" <?= $typeFilter === $ct ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $ct))) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
    <a href="support-cases.php" style="padding:.45rem .9rem;border:1px solid #ddd;border-radius:6px;text-decoration:none;font-size:.88rem;color:#555">Reset</a>
  </form>

  <table>
    <thead>
      <tr>
        <th>Case UID</th><th>Customer</th><th>Payment</th><th>Subject &amp; Resolution</th>
        <th>Type</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Opened</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$cases): ?>
      <tr><td colspan="10" style="text-align:center;color:#aaa;padding:2rem">No support cases found.</td></tr>
    <?php else: foreach ($cases as $c): ?>
      <?php
      $pCls = 'p-' . ($c['priority'] ?? 'medium');
      $sCls = match ($c['status'] ?? '') {
          'open'              => 'bg-danger',
          'investigating'     => 'bg-warn',
          'awaiting_customer' => 'bg-info',
          'resolved'          => 'bg-ok',
          default             => '',
      };
      ?>
      <tr class="<?= htmlspecialchars($pCls) ?>">
        <td style="font-family:monospace;font-size:.82rem;font-weight:700"><?= htmlspecialchars($c['case_uid'] ?? '#' . $c['id']) ?></td>
        <td><?= htmlspecialchars($c['customer'] ?? '—') ?><?= !empty($c['phone']) ? '<br><small style="color:#888">' . htmlspecialchars($c['phone']) . '</small>' : '' ?></td>
        <td>
          <?php if ($c['payment_id']): ?>
            <small style="font-family:monospace"><?= htmlspecialchars($c['payment_id']) ?></small><br>
            <small style="color:#666"><?= AmountCalculator::formatINR((int)$c['amount_paise']) ?> | <?= htmlspecialchars(strtoupper($c['method'] ?? '')) ?></small>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <?= htmlspecialchars(mb_strimwidth($c['subject'] ?? '', 0, 50, '…')) ?>
          <?php if (!empty($c['resolution'])): ?>
            <br><small style="color:#666;font-style:italic"><?= htmlspecialchars(mb_strimwidth($c['resolution'], 0, 60, '…')) ?></small>
          <?php endif; ?>
        </td>
        <td><small><?= htmlspecialchars(ucwords(str_replace('_', ' ', $c['case_type'] ?? ''))) ?></small></td>
        <td><span class="badge"><?= htmlspecialchars(ucfirst($c['priority'] ?? 'medium')) ?></span></td>
        <td><span class="badge <?= $sCls ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $c['status'] ?? ''))) ?></span></td>
        <td><small><?= htmlspecialchars($c['assigned_name'] ?? '—') ?></small></td>
        <td><small><?= htmlspecialchars(substr((string)($c['opened_at'] ?? ''), 0, 16)) ?></small></td>
        <td>
          <button type="button" class="btn btn-sm"
                  onclick='openEdit(<?= (int)$c["id"] ?>, <?= json_encode($c["status"] ?? "open") ?>, <?= json_encode($c["resolution"] ?? "") ?>)'>Edit</button>
          <button type="button" class="btn btn-sm" style="background:#607d8b"
                  onclick='openAssign(<?= (int)$c["id"] ?>, <?= (int)($c["assigned_to"] ?? 0) ?>)'>Assign</button>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Edit Status Modal -->
<div id="editModal" class="modal-backdrop">
  <div class="modal-box">
    <h3>Update Support Case</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="case_id" id="editCaseId">
      <label>New Status</label>
      <select name="new_status" id="editNewStatus" required>
        <?php foreach ($allowedStatuses as $st): ?>
          <option value="<?= $st ?>"><?= ucwords(str_replace('_', ' ', $st)) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Resolution / Admin Notes</label>
      <textarea name="resolution" id="editResolution" rows="3" placeholder="Resolution details or internal note..."></textarea>
      <div class="modal-actions">
        <button type="button" class="btn" style="background:#888" onclick="closeModals()">Cancel</button>
        <button type="submit" class="btn">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Assign Modal -->
<div id="assignModal" class="modal-backdrop">
  <div class="modal-box">
    <h3>Assign Case</h3>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="assign">
      <input type="hidden" name="case_id" id="assignCaseId">
      <label>Assign To</label>
      <select name="assign_to" id="assignUser">
        <option value="">— Unassigned —</option>
        <?php foreach ($adminUsers as $au): ?>
          <option value="<?= (int)$au['id'] ?>"><?= htmlspecialchars($au['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="modal-actions">
        <button type="button" class="btn" style="background:#888" onclick="closeModals()">Cancel</button>
        <button type="submit" class="btn">Assign</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(id, status, resolution) {
    document.getElementById('editCaseId').value = id;
    document.getElementById('editNewStatus').value = status;
    document.getElementById('editResolution').value = resolution || '';
    document.getElementById('editModal').classList.add('open');
}
function openAssign(id, assignedTo) {
    document.getElementById('assignCaseId').value = id;
    document.getElementById('assignUser').value = assignedTo || '';
    document.getElementById('assignModal').classList.add('open');
}
function closeModals() {
    document.getElementById('editModal').classList.remove('open');
    document.getElementById('assignModal').classList.remove('open');
}
</script>
</body>
</html>
