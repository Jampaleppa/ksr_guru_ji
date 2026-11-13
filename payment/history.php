<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$userId = (int)$_SESSION['user_id'];
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$total = (int)$pdo->prepare(
    "SELECT COUNT(*) FROM payment_transactions WHERE user_id = ?"
)->execute([$userId]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM payment_transactions WHERE user_id = ?");
$countStmt->execute([$userId]);
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT pt.payment_id, pt.amount_paise, pt.method, pt.status, pt.created_at,
            o.order_number
     FROM payment_transactions pt
     JOIN orders o ON o.id = pt.order_id
     WHERE pt.user_id = ?
     ORDER BY pt.created_at DESC
     LIMIT {$limit} OFFSET {$offset}"
);
$stmt->execute([$userId]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$calc     = new AmountCalculator();
$pages    = max(1, (int)ceil($total / $limit));

$statusColors = [
    'success' => '#1a7f4b', 'cod_collected' => '#1a7f4b',
    'pending' => '#f9a825', 'upi_awaiting' => '#f9a825',
    'failed' => '#e53e3e',  'cancelled' => '#e53e3e',
    'refunded' => '#2196f3',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment History — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.hist-card{max-width:800px;margin:2rem auto;padding:1.5rem;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);background:#fff}
table{width:100%;border-collapse:collapse}
th,td{padding:.6rem .8rem;text-align:left;border-bottom:1px solid #f0f0f0}
th{background:#f9f9f9;font-weight:600;font-size:.88rem;color:#555}
.badge{display:inline-block;padding:.2rem .6rem;border-radius:12px;font-size:.8rem;font-weight:600;color:#fff}
.pag{text-align:center;margin-top:1rem}
.pag a{display:inline-block;padding:.4rem .9rem;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#1a7f4b;margin:.2rem}
.pag .cur{background:#1a7f4b;color:#fff;border-color:#1a7f4b}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../global-header.php')) include __DIR__ . '/../global-header.php'; ?>

<div class="hist-card">
  <h2><i class="fas fa-history" style="color:#1a7f4b;"></i> Payment History</h2>
  <?php if (empty($payments)): ?>
    <p style="color:#aaa;text-align:center;padding:2rem;">No payments yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Order</th><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Receipt</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <?php $color = $statusColors[ksrNormalizePaymentStatus($p['status'])] ?? '#888'; ?>
        <tr>
          <td>#<?= htmlspecialchars($p['order_number']) ?></td>
          <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
          <td><?= $calc->formatINR((int)$p['amount_paise']) ?></td>
          <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $p['method']))) ?></td>
          <td><span class="badge" style="background:<?= $color ?>"><?= htmlspecialchars($p['status']) ?></span></td>
          <td>
            <?php if (in_array($p['status'], ['success','cod_collected'], true)): ?>
              <a href="/payment/receipt.php?pid=<?= urlencode($p['payment_id']) ?>"
                 style="color:#1a7f4b;font-size:.9rem;"><i class="fas fa-receipt"></i></a>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($pages > 1): ?>
    <div class="pag">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'cur' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>

