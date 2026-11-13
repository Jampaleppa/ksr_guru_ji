<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\RefundManager;
use KsrPay\AuditLogger;
use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

$calc    = new AmountCalculator();
$sec     = new SecurityManager();
$csrf    = $sec->generateCSRFToken();
$adminId = (int)$_SESSION['user_id'];
$msg     = '';

$refunds = (new RefundManager($pdo, new AuditLogger($pdo)))->getPendingRefunds();

$statusColors = [
    'pending'    => '#f9a825',
    'approved'   => '#2196f3',
    'processing' => '#9c27b0',
    'completed'  => '#1a7f4b',
    'failed'     => '#e53e3e',
    'cancelled'  => '#888',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Refunds — Admin — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1100px;margin:1.5rem auto;padding:0 1rem}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.07)}
th,td{padding:.6rem .8rem;text-align:left;font-size:.85rem;border-bottom:1px solid #f5f5f5}
th{background:#f5f5f5;font-weight:600}
.badge{display:inline-block;padding:.15rem .5rem;border-radius:10px;font-size:.75rem;font-weight:600;color:#fff}
.action-btn{padding:.28rem .7rem;border-radius:5px;font-size:.8rem;cursor:pointer;border:none;font-weight:700}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="admin-wrap">
  <h2><a href="/admin/payments/dashboard.php" style="color:#1a7f4b;text-decoration:none">← Dashboard</a> &nbsp; Refund Requests</h2>

  <?= $msg ?>

  <?php if (empty($refunds)): ?>
    <p style="color:#aaa;padding:2rem;text-align:center">No pending refund requests. ✅</p>
  <?php else: ?>
  <table>
    <thead>
      <tr><th>#</th><th>Order</th><th>Customer</th><th>Amount</th><th>Reason</th><th>Status</th><th>Requested</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($refunds as $r): ?>
      <?php $col = $statusColors[$r['status']] ?? '#888'; ?>
      <tr id="refund-<?= (int)$r['id'] ?>">
        <td><?= (int)$r['id'] ?></td>
        <td>#<?= htmlspecialchars($r['order_number'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['customer_name'] ?? 'Guest') ?></td>
        <td><?= $calc->formatINR((int)$r['amount_paise']) ?></td>
        <td style="max-width:200px"><?= htmlspecialchars(substr($r['reason']??'',0,60)) ?><?= strlen($r['reason']??'')>60?'…':'' ?></td>
        <td><span class="badge" style="background:<?= $col ?>"><?= htmlspecialchars($r['status']) ?></span></td>
        <td><?= date('d M Y', strtotime($r['requested_at'])) ?></td>
        <td>
          <?php if ($r['status'] === 'pending'): ?>
            <button class="action-btn" style="background:#2196f3;color:#fff"
              onclick="doRefundAction(<?= (int)$r['id'] ?>,'approve')">Approve</button>
          <?php elseif (in_array($r['status'], ['approved','processing'], true)): ?>
            <button class="action-btn" style="background:#1a7f4b;color:#fff"
              onclick="doRefundAction(<?= (int)$r['id'] ?>,'complete')">Mark Complete</button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<script>
async function doRefundAction(refundId, action) {
  if (!confirm(action === 'approve' ? 'Approve this refund?' : 'Mark refund as completed?')) return;
  const fd = new FormData();
  fd.append('csrf_token','<?= htmlspecialchars($csrf) ?>');
  fd.append('refund_id', refundId);
  fd.append('action', action);
  const r = await fetch('/api/payment/refund.php', {method:'POST', body:fd});
  const d = await r.json();
  alert(d.message || d.error);
  if (d.success) location.reload();
}
</script>
</body>
</html>

