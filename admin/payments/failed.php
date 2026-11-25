<?php
declare(strict_types=1);
// admin/payments/failed.php — View failed and expired payments

require_once __DIR__ . '/../../payment-core/bootstrap.php';
use KsrPay\AmountCalculator;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$from   = trim($_GET['from']   ?? '');
$to     = trim($_GET['to']     ?? '');

$where  = ["pt.status IN ('failed','expired','cancelled')"];
$params = [];

if ($status !== '' && in_array($status, ['failed','expired','cancelled'], true)) {
    $where[0] = "pt.status = ?"; $params[] = $status;
}
if ($search !== '') {
    $where[]  = "(pt.payment_id LIKE ? OR o.order_number LIKE ? OR u.name LIKE ?)";
    $like     = "%{$search}%";
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($from !== '') { $where[] = "DATE(pt.created_at) >= ?"; $params[] = $from; }
if ($to   !== '') { $where[] = "DATE(pt.created_at) <= ?"; $params[] = $to;   }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM payment_transactions pt JOIN orders o ON o.id=pt.order_id LEFT JOIN users u ON u.id=pt.user_id {$whereSQL}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$listStmt = $pdo->prepare(
    "SELECT pt.payment_id, pt.amount_paise, pt.method, pt.status,
            pt.failure_reason, pt.created_at,
            o.order_number, u.name AS customer
       FROM payment_transactions pt
       JOIN orders o ON o.id=pt.order_id
       LEFT JOIN users u ON u.id=pt.user_id
     {$whereSQL}
     ORDER BY pt.created_at DESC
     LIMIT {$limit} OFFSET {$offset}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$statusColors = ['failed'=>'#e53e3e','expired'=>'#888','cancelled'=>'#ff9800'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Failed Payments — KSR Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1200px;margin:1.5rem auto;padding:0 1rem}
h1{font-size:1.4rem;color:#e53e3e;margin-bottom:1rem}
.filter-bar{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem}
.filter-bar input,.filter-bar select{padding:.45rem .7rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem}
.filter-bar button{padding:.45rem 1rem;background:#e53e3e;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.88rem}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.07)}
th,td{padding:.6rem .9rem;text-align:left;font-size:.88rem}
th{background:#f5f5f5;font-weight:600;color:#555}
tr:hover{background:#fff8f8}
.badge{display:inline-block;padding:.2rem .6rem;border-radius:12px;font-size:.78rem;font-weight:600;color:#fff}
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
    <a href="/admin/payments/successful.php">Successful</a>
  </div>

  <h1><i class="fas fa-times-circle"></i> Failed / Expired Payments</h1>
  <p style="color:#888;font-size:.9rem;margin-bottom:1rem">Total: <strong><?= $total ?></strong> records</p>

  <form method="get" class="filter-bar">
    <input type="text" name="search" placeholder="Payment/Order ID or customer" value="<?= htmlspecialchars($search) ?>">
    <select name="status">
      <option value="">All (failed+expired+cancelled)</option>
      <option value="failed"    <?= $status==='failed'?'selected':'' ?>>Failed</option>
      <option value="expired"   <?= $status==='expired'?'selected':'' ?>>Expired</option>
      <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
    </select>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    <input type="date" name="to"   value="<?= htmlspecialchars($to)   ?>">
    <button type="submit"><i class="fas fa-search"></i> Filter</button>
    <a href="failed.php" style="padding:.45rem .9rem;border:1px solid #ddd;border-radius:6px;text-decoration:none;font-size:.88rem;color:#555">Reset</a>
  </form>

  <table>
    <thead>
      <tr>
        <th>Payment ID</th><th>Order</th><th>Customer</th>
        <th>Method</th><th>Amount</th><th>Status</th>
        <th>Failure Reason</th><th>Date</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): $color = $statusColors[$r['status']] ?? '#888'; ?>
      <tr>
        <td style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($r['payment_id']) ?></td>
        <td><?= htmlspecialchars($r['order_number']) ?></td>
        <td><?= htmlspecialchars($r['customer'] ?? '—') ?></td>
        <td><?= strtoupper(htmlspecialchars($r['method'])) ?></td>
        <td>₹<?= number_format((int)$r['amount_paise']/100,2) ?></td>
        <td><span class="badge" style="background:<?= $color ?>"><?= htmlspecialchars($r['status']) ?></span></td>
        <td style="font-size:.82rem;color:#666"><?= htmlspecialchars(mb_strimwidth($r['failure_reason'] ?? '—', 0, 60, '…')) ?></td>
        <td><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="8" style="text-align:center;color:#aaa;padding:2rem">No failed payments found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
         class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
