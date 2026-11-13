<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AuditLogger;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

$evType = trim($_GET['event_type'] ?? '');
$actor  = trim($_GET['actor_type'] ?? '');
$pid    = trim($_GET['pid']        ?? '');
$from   = trim($_GET['from']       ?? '');
$to     = trim($_GET['to']         ?? '');

$where  = [];
$params = [];
if ($evType !== '') { $where[] = "event_type = ?";      $params[] = $evType; }
if ($actor  !== '') { $where[] = "actor_type = ?";      $params[] = $actor;  }
if ($pid    !== '') { $where[] = "payment_transaction_id = (SELECT id FROM payment_transactions WHERE payment_id = ? LIMIT 1)"; $params[] = $pid; }
if ($from   !== '') { $where[] = "DATE(created_at) >= ?"; $params[] = $from; }
if ($to     !== '') { $where[] = "DATE(created_at) <= ?"; $params[] = $to;   }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM payment_audit_logs {$whereSQL}")->execute($params) ? 0 : 0;
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM payment_audit_logs {$whereSQL}");
$cStmt->execute($params);
$total = (int)$cStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$lStmt = $pdo->prepare(
    "SELECT pal.*, u.name AS actor_name
     FROM payment_audit_logs pal
     LEFT JOIN users u ON u.id = pal.actor_id
     {$whereSQL}
     ORDER BY pal.id DESC
     LIMIT {$limit} OFFSET {$offset}"
);
$lStmt->execute($params);
$logs = $lStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Audit Logs — Admin — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1150px;margin:1.5rem auto;padding:0 1rem}
.filters{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;background:#fff;padding:.8rem 1rem;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.07)}
.filters input,.filters select{padding:.45rem .6rem;border:1px solid #ddd;border-radius:5px;font-size:.85rem}
.filters button{padding:.45rem .9rem;background:#1a7f4b;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:.85rem;font-weight:600}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.07);font-size:.83rem}
th,td{padding:.5rem .7rem;text-align:left;border-bottom:1px solid #f5f5f5}
th{background:#f5f5f5;font-weight:600;color:#555}
.ev-badge{display:inline-block;padding:.15rem .45rem;border-radius:5px;background:#e8f4fd;color:#1976d2;font-size:.75rem;font-family:monospace;font-weight:600}
.actor-badge{display:inline-block;padding:.15rem .4rem;border-radius:10px;font-size:.74rem;font-weight:600;color:#fff}
.pag{text-align:center;margin-top:1rem}
.pag a{display:inline-block;padding:.3rem .7rem;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#1a7f4b;margin:.1rem;font-size:.82rem}
.pag .cur{background:#1a7f4b;color:#fff}
.immutable-notice{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:.6rem 1rem;font-size:.84rem;margin-bottom:.8rem}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="admin-wrap">
  <h2><a href="/admin/payments/dashboard.php" style="color:#1a7f4b;text-decoration:none">← Dashboard</a> &nbsp; Payment Audit Logs</h2>

  <div class="immutable-notice">
    <i class="fas fa-lock" style="color:#f9a825;"></i>
    <strong>Immutable record.</strong> These logs cannot be edited or deleted. Every financial event is recorded here permanently.
  </div>

  <form method="get" class="filters">
    <input type="text"  name="pid"        value="<?= htmlspecialchars($pid) ?>"    placeholder="Payment ID">
    <input type="text"  name="event_type" value="<?= htmlspecialchars($evType) ?>" placeholder="Event type">
    <select name="actor_type">
      <option value="">All Actors</option>
      <?php foreach (['customer','admin','system','webhook'] as $a): ?>
        <option value="<?= $a ?>" <?= $actor===$a?'selected':'' ?>><?= ucfirst($a) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    <input type="date" name="to"   value="<?= htmlspecialchars($to) ?>">
    <button type="submit"><i class="fas fa-search"></i></button>
    <a href="?" style="padding:.45rem .7rem;border:1px solid #ddd;border-radius:5px;text-decoration:none;color:#555;font-size:.85rem">Clear</a>
  </form>

  <p style="font-size:.82rem;color:#888;margin-bottom:.5rem"><?= $total ?> log entries</p>

  <table>
    <thead>
      <tr><th>Timestamp</th><th>Event</th><th>Payment ID</th><th>Order</th><th>Transition</th><th>Actor</th><th>Notes</th></tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <?php
        $actorColors = ['customer'=>'#1a7f4b','admin'=>'#9c27b0','system'=>'#607d8b','webhook'=>'#2196f3'];
        $acol = $actorColors[$l['actor_type']] ?? '#888';
        // Load payment_id for display
        $ptIdDisplay = '';
        if ($l['payment_transaction_id']) {
            static $pidCache = [];
            $ptid = (int)$l['payment_transaction_id'];
            if (!isset($pidCache[$ptid])) {
                $ps = $pdo->prepare("SELECT payment_id FROM payment_transactions WHERE id=? LIMIT 1");
                $ps->execute([$ptid]);
                $pidCache[$ptid] = $ps->fetchColumn() ?: '#' . $ptid;
            }
            $ptIdDisplay = $pidCache[$ptid];
        }
      ?>
      <tr>
        <td><?= date('d M Y H:i:s', strtotime($l['created_at'])) ?></td>
        <td><span class="ev-badge"><?= htmlspecialchars($l['event_type']) ?></span></td>
        <td><small style="font-family:monospace"><?= htmlspecialchars(substr($ptIdDisplay,0,20)) ?></small></td>
        <td><?= $l['order_id'] ? '#' . (int)$l['order_id'] : '—' ?></td>
        <td><?= $l['old_status'] ? htmlspecialchars($l['old_status']) . ' → ' . htmlspecialchars($l['new_status']??'') : htmlspecialchars($l['new_status']??'—') ?></td>
        <td><span class="actor-badge" style="background:<?= $acol ?>"><?= htmlspecialchars($l['actor_type']) ?></span>
            <?= $l['actor_name'] ? '<small> ' . htmlspecialchars($l['actor_name']) . '</small>' : '' ?></td>
        <td style="max-width:200px;white-space:nowrap;overflow:hidden"><?= htmlspecialchars(substr($l['notes']??'',0,60)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pag">
    <?php
    $q = http_build_query(array_filter(['pid'=>$pid,'event_type'=>$evType,'actor_type'=>$actor,'from'=>$from,'to'=>$to]));
    for ($i = 1; $i <= min($pages, 20); $i++):
    ?>
      <a href="?<?= $q ?>&page=<?= $i ?>" class="<?= $i===$page?'cur':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>

