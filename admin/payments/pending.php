<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\ReconciliationManager;
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

$recon   = new ReconciliationManager($pdo, new AuditLogger($pdo));
$pending = $recon->getPendingPayments();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pending UPI — Admin — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1100px;margin:1.5rem auto;padding:0 1rem}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.07)}
th,td{padding:.6rem .9rem;text-align:left;font-size:.87rem;border-bottom:1px solid #f5f5f5}
th{background:#f5f5f5;font-weight:600;color:#555}
.action-btn{padding:.3rem .75rem;border-radius:5px;font-size:.82rem;cursor:pointer;border:none;font-weight:700}
.utr-mono{font-family:monospace;font-size:.88rem;background:#f5f5f5;padding:.2rem .4rem;border-radius:4px}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="admin-wrap">
  <h2><a href="/admin/payments/dashboard.php" style="color:#1a7f4b;text-decoration:none">← Dashboard</a>
  &nbsp; Pending UPI Verifications
  <span style="font-size:.85rem;font-weight:400;color:#888">(<?= count($pending) ?> waiting)</span></h2>

  <?php if (empty($pending)): ?>
    <p style="color:#aaa;padding:2rem;text-align:center">No pending UPI payments. 🎉</p>
  <?php else: ?>
  <table>
    <thead>
      <tr><th>Payment ID</th><th>Order</th><th>Customer</th><th>Phone</th><th>Amount</th><th>UTR (Customer)</th><th>Submitted</th><th>Verify</th></tr>
    </thead>
    <tbody>
    <?php foreach ($pending as $p): ?>
    <tr id="row-<?= htmlspecialchars($p['payment_id']) ?>">
      <td><small style="font-family:monospace"><?= htmlspecialchars(substr($p['payment_id'],0,20)) ?></small></td>
      <td>#<?= htmlspecialchars($p['order_number']) ?></td>
      <td><?= htmlspecialchars($p['customer_name'] ?? 'Guest') ?></td>
      <td><?= htmlspecialchars($p['phone'] ?? '—') ?></td>
      <td><?= $calc->formatINR((int)$p['amount_paise']) ?></td>
      <td><?= $p['customer_utr'] ? '<span class="utr-mono">' . htmlspecialchars($p['customer_utr']) . '</span>' : '<em style="color:#aaa">Not submitted</em>' ?></td>
      <td><?= date('d M H:i', strtotime($p['created_at'])) ?></td>
      <td>
        <button class="action-btn" style="background:#1a7f4b;color:#fff"
          onclick="openVerify('<?= htmlspecialchars($p['payment_id']) ?>','<?= htmlspecialchars($p['customer_utr']??'') ?>')">
          Verify ✓
        </button>
        <button class="action-btn" style="background:#e53e3e;color:#fff;margin-left:.3rem"
          onclick="markFailed('<?= htmlspecialchars($p['payment_id']) ?>')">
          Fail ✗
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Verify modal -->
<div id="vModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:1.5rem;max-width:420px;width:90%">
    <h3 style="margin-top:0">Verify UPI Payment</h3>
    <p style="font-size:.88rem;color:#888">Customer UTR: <span id="modalUTR" style="font-family:monospace;font-weight:700"></span></p>
    <input type="hidden" id="vPid">
    <label style="font-weight:600;display:block;margin-bottom:.4rem">Bank Credit ID *</label>
    <input id="vBcId" type="number" style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:6px;margin-bottom:.8rem" placeholder="From bank-credits page">
    <label style="font-weight:600;display:block;margin-bottom:.4rem">Notes</label>
    <input id="vNotes" type="text" style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:6px;margin-bottom:1rem">
    <div id="vMsg" style="display:none;padding:.5rem;border-radius:5px;margin-bottom:.8rem;font-size:.87rem"></div>
    <button onclick="submitVerify()" style="background:#1a7f4b;color:#fff;padding:.65rem 1.4rem;border:none;border-radius:6px;cursor:pointer;font-weight:700">Confirm</button>
    <button onclick="document.getElementById('vModal').style.display='none'" style="background:#f0f0f0;padding:.65rem 1.2rem;border:none;border-radius:6px;cursor:pointer;margin-left:.5rem">Cancel</button>
  </div>
</div>

<script>
function openVerify(pid, utr) {
  document.getElementById('vPid').value = pid;
  document.getElementById('modalUTR').textContent = utr || '(not submitted)';
  document.getElementById('vMsg').style.display = 'none';
  document.getElementById('vModal').style.display = 'flex';
}
async function submitVerify() {
  const pid=document.getElementById('vPid').value;
  const bc=document.getElementById('vBcId').value;
  const notes=document.getElementById('vNotes').value;
  const msg=document.getElementById('vMsg');
  if (!bc) { msg.textContent='Enter Bank Credit ID'; msg.style.cssText='display:block;background:#fde;color:#c00'; return; }
  const fd=new FormData();
  fd.append('csrf_token','<?= htmlspecialchars($csrf) ?>');
  fd.append('payment_id',pid); fd.append('bank_credit_id',bc); fd.append('notes',notes);
  const r=await fetch('/api/payment/verify.php',{method:'POST',body:fd});
  const d=await r.json();
  msg.style.cssText=`display:block;background:${d.success?'#dfd':'#fde'};color:${d.success?'#1a7f4b':'#c00'}`;
  msg.textContent=d.message||d.error;
  if (d.success) { document.getElementById('row-'+pid).style.opacity='.4'; setTimeout(()=>location.reload(),1500); }
}
async function markFailed(pid) {
  if (!confirm('Mark this payment as FAILED?')) return;
  const fd=new FormData();
  fd.append('csrf_token','<?= htmlspecialchars($csrf) ?>');
  fd.append('payment_id',pid); fd.append('new_status','failed'); fd.append('reason','Marked failed by admin');
  // Simple status update — extend as needed
  alert('Failed marking not yet implemented via separate endpoint. Use Transactions page.');
}
</script>
</body>
</html>

