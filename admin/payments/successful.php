<?php
declare(strict_types=1);
// admin/payments/successful.php — View successful/confirmed payments

require_once __DIR__ . '/../../payment-core/bootstrap.php';
use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

$calc   = new AmountCalculator();
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$method = trim($_GET['method'] ?? '');
$from   = trim($_GET['from']   ?? '');
$to     = trim($_GET['to']     ?? '');

$where  = ["pt.status IN ('success','cod_collected')"];
$params = [];

if ($search !== '') {
    $where[]  = "(pt.payment_id LIKE ? OR o.order_number LIKE ? OR u.name LIKE ?)";
    $like     = "%{$search}%";
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($method !== '') { $where[] = "pt.method = ?";            $params[] = $method; }
if ($from   !== '') { $where[] = "DATE(pt.verified_at) >= ?"; $params[] = $from;   }
if ($to     !== '') { $where[] = "DATE(pt.verified_at) <= ?"; $params[] = $to;     }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM payment_transactions pt JOIN orders o ON o.id=pt.order_id LEFT JOIN users u ON u.id=pt.user_id {$whereSQL}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$listStmt = $pdo->prepare(
    "SELECT pt.payment_id, pt.amount_paise, pt.method, pt.status,
            pt.verified_at, pt.bank_confirmed_ref, pt.verification_source,
            o.order_number, u.name AS customer, u.phone
       FROM payment_transactions pt
       JOIN orders o ON o.id=pt.order_id
       LEFT JOIN users u ON u.id=pt.user_id
     {$whereSQL}
     ORDER BY pt.verified_at DESC
     LIMIT {$limit} OFFSET {$offset}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$totalPaise = (int)$pdo->query("SELECT COALESCE(SUM(amount_paise),0) FROM payment_transactions WHERE status IN ('success','cod_collected')")->fetchColumn();
$todayPaise = (int)$pdo->query("SELECT COALESCE(SUM(amount_paise),0) FROM payment_transactions WHERE status IN ('success','cod_collected') AND DATE(verified_at)=CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Successful Payments — KSR Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1200px;margin:1.5rem auto;padding:0 1rem}
h1{font-size:1.4rem;color:#1a7f4b;margin-bottom:1rem}
.stats-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
.stat-card{flex:1;min-width:160px;background:#fff;border-radius:10px;padding:1rem 1.4rem;box-shadow:0 2px 10px rgba(0,0,0,.07)}
.stat-val{font-size:1.5rem;font-weight:800;color:#1a7f4b}
.stat-label{font-size:.82rem;color:#888;margin-top:.2rem}
.filter-bar{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem}
.filter-bar input,.filter-bar select{padding:.45rem .7rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem}
.filter-bar button{padding:.45rem 1rem;background:#1a7f4b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.88rem}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.07)}
th,td{padding:.6rem .9rem;text-align:left;font-size:.88rem}
th{background:#f5f5f5;font-weight:600;color:#555}
tr:hover{background:#f9fdf5}
.badge-ok{display:inline-block;background:#1a7f4b;color:#fff;border-radius:12px;padding:.2rem .6rem;font-size:.78rem;font-weight:600}
.pagination{display:flex;gap:.4rem;margin-top:1rem;flex-wrap:wrap}
.pagination a{padding:.35rem .7rem;border:1px solid #ddd;border-radius:5px;text-decoration:none;font-size:.85rem;color:#1a7f4b}
.pagination a.active{background:#1a7f4b;color:#fff;border-color:#1a7f4b}
.nav-links{display:flex;gap:.8rem;margin-bottom:1rem;flex-wrap:wrap}
.nav-links a{text-decoration:none;color:#1a7f4b;font-size:.9rem;font-weight:600}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>
<div class="admin-wrap">
  <div class="nav-links">
    <a href="/admin/payments/dashboard.php">← Dashboard</a>
    <a href="/admin/payments/transactions.php">All Transactions</a>
    <a href="/admin/payments/pending.php">Pending</a>
    <a href="/admin/payments/failed.php">Failed</a>
    <a href="/admin/payments/reconciliation.php">Reconciliation</a>
  </div>

  <h1><i class="fas fa-check-circle" style="color:#1a7f4b"></i> Successful Payments</h1>

  <div class="stats-row">
    <div class="stat-card"><div class="stat-val">₹<?= number_format($totalPaise/100,2) ?></div><div class="stat-label">Total Collected (All Time)</div></div>
    <div class="stat-card"><div class="stat-val">₹<?= number_format($todayPaise/100,2) ?></div><div class="stat-label">Collected Today</div></div>
    <div class="stat-card"><div class="stat-val"><?= $total ?></div><div class="stat-label">Total Confirmed Payments</div></div>
  </div>

  <form method="get" class="filter-bar">
    <input type="text"   name="search" placeholder="Payment/Order ID or customer" value="<?= htmlspecialchars($search) ?>">
    <select name="method">
      <option value="">All methods</option>
      <option value="upi"  <?= $method==='upi'?'selected':'' ?>>UPI</option>
      <option value="cod"  <?= $method==='cod'?'selected':'' ?>>COD</option>
      <option value="bank_transfer" <?= $method==='bank_transfer'?'selected':'' ?>>Bank Transfer</option>
    </select>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    <input type="date" name="to"   value="<?= htmlspecialchars($to)   ?>">
    <button type="submit"><i class="fas fa-search"></i> Filter</button>
    <a href="successful.php" style="padding:.45rem .9rem;border:1px solid #ddd;border-radius:6px;text-decoration:none;font-size:.88rem;color:#555">Reset</a>
  </form>

  <table>
    <thead>
      <tr>
        <th>Payment ID</th><th>Order</th><th>Customer</th>
        <th>Method</th><th>Amount</th><th>Verified At</th>
        <th>Bank Ref</th><th>Source</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/admin/payments/transactions.php?search=<?= urlencode($r['payment_id']) ?>" style="color:#1a7f4b;font-family:monospace;font-size:.82rem"><?= htmlspecialchars($r['payment_id']) ?></a></td>
        <td><?= htmlspecialchars($r['order_number']) ?></td>
        <td><?= htmlspecialchars($r['customer'] ?? '—') ?></td>
        <td><span class="badge-ok"><?= strtoupper(htmlspecialchars($r['method'])) ?></span></td>
        <td><strong>₹<?= number_format((int)$r['amount_paise']/100,2) ?></strong></td>
        <td><?= $r['verified_at'] ? date('d M Y H:i', strtotime($r['verified_at'])) : '—' ?></td>
        <td style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($r['bank_confirmed_ref'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['verification_source'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="8" style="text-align:center;color:#aaa;padding:2rem">No confirmed payments found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&method=<?= urlencode($method) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
         class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
