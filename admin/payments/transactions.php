<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

$calc = new AmountCalculator();
$sec  = new SecurityManager();
$csrf = $sec->generateCSRFToken();

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$method = trim($_GET['method'] ?? '');
$from   = trim($_GET['from']   ?? '');
$to     = trim($_GET['to']     ?? '');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = "(pt.payment_id LIKE ? OR o.order_number LIKE ? OR pt.customer_utr LIKE ? OR u.name LIKE ?)";
    $like     = "%{$search}%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
}
if ($status !== '') { $where[] = "pt.status = ?"; $params[] = $status; }
if ($method !== '') { $where[] = "pt.method = ?"; $params[] = $method; }
if ($from   !== '') { $where[] = "DATE(pt.created_at) >= ?"; $params[] = $from; }
if ($to     !== '') { $where[] = "DATE(pt.created_at) <= ?"; $params[] = $to;   }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM payment_transactions pt JOIN orders o ON o.id=pt.order_id LEFT JOIN users u ON u.id=pt.user_id {$whereSQL}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$listStmt = $pdo->prepare(
    "SELECT pt.*, o.order_number, u.name AS customer, u.phone
     FROM payment_transactions pt
     JOIN orders o ON o.id=pt.order_id
     LEFT JOIN users u ON u.id=pt.user_id
     {$whereSQL}
     ORDER BY pt.created_at DESC
     LIMIT {$limit} OFFSET {$offset}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$statusColors = [
    'success'     =>'#1a7f4b','cod_collected'=>'#1a7f4b',
    'upi_awaiting'=>'#f9a825','pending'=>'#f9a825',
    'failed'=>'#e53e3e','cancelled'=>'#e53e3e',
    'refunded'=>'#2196f3','initiated'=>'#888','cod_pending'=>'#607d8b',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Transactions — Admin — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1200px;margin:1.5rem auto;padding:0 1rem}
.filters{display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:1.2rem;background:#fff;padding:1rem;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.07)}
.filters input,.filters select{padding:.5rem .7rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem}
.filters button{padding:.5rem 1.1rem;background:#1a7f4b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:600}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.07)}
th,td{padding:.6rem .8rem;text-align:left;font-size:.85rem;border-bottom:1px solid #f5f5f5}
th{background:#f5f5f5;font-weight:600;color:#555}
.badge{display:inline-block;padding:.15rem .55rem;border-radius:10px;font-size:.75rem;font-weight:600;color:#fff}
.pag{text-align:center;margin-top:1rem}
.pag a{display:inline-block;padding:.35rem .8rem;border:1px solid #ddd;border-radius:5px;text-decoration:none;color:#1a7f4b;margin:.15rem;font-size:.85rem}
.pag .cur{background:#1a7f4b;color:#fff;border-color:#1a7f4b}
.action-btn{padding:.25rem .6rem;border-radius:4px;font-size:.78rem;cursor:pointer;border:none;font-weight:600;text-decoration:none;display:inline-block}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="admin-wrap">
  <h2><a href="/admin/payments/dashboard.php" style="color:#1a7f4b;text-decoration:none">← Dashboard</a> &nbsp; All Transactions</h2>

  <form method="get" class="filters">
    <input type="text"   name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Payment ID / Order / Customer / UTR">
    <select name="status">
      <option value="">All Status</option>
      <?php foreach (['initiated','pending','upi_awaiting','success','cod_pending','cod_collected','failed','cancelled','refunded'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="method">
      <option value="">All Methods</option>
      <?php foreach (['upi','cod','bank_transfer','card'] as $m): ?>
        <option value="<?= $m ?>" <?= $method===$m?'selected':'' ?>><?= strtoupper($m) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" title="From date">
    <input type="date" name="to"   value="<?= htmlspecialchars($to) ?>"   title="To date">
    <button type="submit"><i class="fas fa-search"></i> Filter</button>
    <a href="?" style="padding:.5rem .9rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem;text-decoration:none;color:#555">Clear</a>
  </form>

  <p style="font-size:.85rem;color:#888;margin-bottom:.6rem">Showing <?= count($rows) ?> of <?= $total ?> transactions</p>

  <table>
    <thead>
      <tr><th>Payment ID</th><th>Order</th><th>Customer</th><th>Amount</th><th>Method</th><th>Status</th><th>UTR</th><th>Date</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php $st=$r['status']; $col=$statusColors[ksrNormalizePaymentStatus($st)]??'#888'; ?>
      <tr>
        <td><small style="font-family:monospace;font-size:.78rem"><?= htmlspecialchars($r['payment_id']) ?></small></td>
        <td>#<?= htmlspecialchars($r['order_number']) ?></td>
        <td><?= htmlspecialchars($r['customer'] ?? 'Guest') ?></td>
        <td><?= $calc->formatINR((int)$r['amount_paise']) ?></td>
        <td><?= htmlspecialchars(ucfirst($r['method'])) ?></td>
        <td><span class="badge" style="background:<?= $col ?>"><?= htmlspecialchars($st) ?></span></td>
        <td><small style="font-family:monospace"><?= $r['customer_utr'] ? htmlspecialchars(substr($r['customer_utr'],0,8)).'…' : '—' ?></small></td>
        <td><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
        <td>
          <?php if (in_array(ksrNormalizePaymentStatus($st), ['upi_awaiting','upi_pending_verification'], true)): ?>
            <button class="action-btn" style="background:#1a7f4b;color:#fff"
              onclick="verifyPayment('<?= htmlspecialchars($r['payment_id']) ?>')">Verify</button>
          <?php endif; ?>
          <a class="action-btn" style="background:#e8f4fd;color:#1976d2"
             href="/payment/receipt.php?pid=<?= urlencode($r['payment_id']) ?>" target="_blank">Receipt</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pag">
    <?php
    $q = http_build_query(array_filter(['search'=>$search,'status'=>$status,'method'=>$method,'from'=>$from,'to'=>$to]));
    for ($i = 1; $i <= $pages; $i++):
    ?>
      <a href="?<?= $q ?>&page=<?= $i ?>" class="<?= $i===$page?'cur':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<div id="verifyBox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:1.5rem;max-width:400px;width:90%">
    <h3 style="margin-top:0">Verify Payment</h3>
    <input id="vcPid" type="hidden">
    <label>Bank Credit ID</label>
    <input id="vcBcId" type="number" style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:6px;margin:.4rem 0 .8rem">
    <label>Notes</label>
    <input id="vcNotes" type="text" style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:6px;margin:.4rem 0 1rem">
    <div id="vcMsg" style="display:none;padding:.5rem;border-radius:5px;margin-bottom:.7rem;font-size:.87rem"></div>
    <button onclick="doVerify()" style="background:#1a7f4b;color:#fff;padding:.6rem 1.4rem;border:none;border-radius:6px;cursor:pointer;font-weight:700">Confirm</button>
    <button onclick="document.getElementById('verifyBox').style.display='none'" style="background:#f0f0f0;padding:.6rem 1.2rem;border:none;border-radius:6px;cursor:pointer;margin-left:.5rem">Cancel</button>
  </div>
</div>

<script>
function verifyPayment(pid) {
  document.getElementById('vcPid').value = pid;
  document.getElementById('verifyBox').style.display = 'flex';
  document.getElementById('vcMsg').style.display = 'none';
}
async function doVerify() {
  const pid=document.getElementById('vcPid').value;
  const bc=document.getElementById('vcBcId').value;
  const notes=document.getElementById('vcNotes').value;
  const msg=document.getElementById('vcMsg');
  if (!bc) { msg.textContent='Enter Bank Credit ID'; msg.style.cssText='display:block;background:#fde;color:#c00'; return; }
  const fd=new FormData();
  fd.append('csrf_token','<?= htmlspecialchars($csrf) ?>');
  fd.append('payment_id',pid); fd.append('bank_credit_id',bc); fd.append('notes',notes);
  const r=await fetch('/api/payment/verify.php',{method:'POST',body:fd});
  const d=await r.json();
  msg.style.cssText=`display:block;background:${d.success?'#dfd':'#fde'};color:${d.success?'#1a7f4b':'#c00'}`;
  msg.textContent=d.message||d.error;
  if (d.success) setTimeout(()=>location.reload(),1200);
}
</script>
</body>
</html>

