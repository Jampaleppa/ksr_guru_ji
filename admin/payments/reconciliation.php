<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\ReconciliationManager;
use KsrPay\AuditLogger;
use KsrPay\PaymentStateMachine;
use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

$calc    = new AmountCalculator();
$sec     = new SecurityManager();
$csrf    = $sec->generateCSRFToken();
$adminId = (int)$_SESSION['user_id'];

$recon    = new ReconciliationManager($pdo, new AuditLogger($pdo));
$credits  = $recon->getPendingCredits();
$payments = $recon->getPendingPayments();

// Auto-suggest matches: same amount, within 3 days
$suggestions = [];
foreach ($credits as $c) {
    foreach ($payments as $p) {
        if ((int)$c['amount_paise'] === (int)$p['amount_paise']) {
            $daysDiff = abs(strtotime($c['credit_date']) - strtotime($p['created_at'])) / 86400;
            if ($daysDiff <= 3) {
                $suggestions[] = [
                    'bank_credit_id' => $c['id'],
                    'payment_id'     => $p['payment_id'],
                    'amount_paise'   => $c['amount_paise'],
                    'bank_ref'       => $c['bank_ref'],
                    'utr'            => $p['customer_utr'],
                    'order_number'   => $p['order_number'],
                    'credit_date'    => $c['credit_date'],
                    'days_diff'      => round($daysDiff, 1),
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reconciliation — Admin — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1200px;margin:1.5rem auto;padding:0 1rem}
.recon-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.5rem}
@media(max-width:768px){.recon-grid{grid-template-columns:1fr}}
.panel{background:#fff;border-radius:10px;padding:1.2rem;box-shadow:0 2px 10px rgba(0,0,0,.07)}
.panel h3{margin-top:0;font-size:.97rem;border-bottom:2px solid #f0f0f0;padding-bottom:.5rem;color:#333}
.credit-row,.payment-row{padding:.6rem .4rem;border-bottom:1px solid #f5f5f5;font-size:.85rem;cursor:pointer;border-radius:5px;transition:background .1s}
.credit-row:hover,.payment-row:hover{background:#e8f5e9}
.credit-row.selected,.payment-row.selected{background:#c8e6c9;border-left:3px solid #1a7f4b}
.amount-tag{display:inline-block;background:#e8f5e9;color:#1a7f4b;border-radius:4px;padding:.1rem .4rem;font-size:.8rem;font-weight:700}
.suggest-box{background:#fff;border-radius:10px;padding:1.2rem;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:1.5rem}
.suggest-row{display:flex;align-items:center;gap:.7rem;padding:.6rem .5rem;border-bottom:1px solid #f5f5f5;font-size:.85rem;flex-wrap:wrap}
.match-btn{padding:.35rem .9rem;background:#1a7f4b;color:#fff;border:none;border-radius:5px;cursor:pointer;font-weight:700;font-size:.82rem}
.match-action-bar{display:flex;align-items:center;gap:.7rem;background:#fff;padding:1rem;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:1.5rem;flex-wrap:wrap}
.match-action-bar input{padding:.5rem .7rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem;flex:1;min-width:200px}
.match-action-bar button{padding:.5rem 1.2rem;background:#1a7f4b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700}
.msg-box{padding:.7rem 1rem;border-radius:6px;margin-bottom:.8rem;font-size:.9rem}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="admin-wrap">
  <h2><a href="/admin/payments/dashboard.php" style="color:#1a7f4b;text-decoration:none">← Dashboard</a>
  &nbsp; UPI Payment Reconciliation</h2>

  <div id="globalMsg"></div>

  <!-- Auto-match suggestions -->
  <?php if (!empty($suggestions)): ?>
  <div class="suggest-box">
    <h3><i class="fas fa-magic" style="color:#f9a825;"></i> Auto-Match Suggestions (same amount, within 3 days)</h3>
    <?php foreach ($suggestions as $s): ?>
    <div class="suggest-row">
      <span class="amount-tag"><?= $calc->formatINR((int)$s['amount_paise']) ?></span>
      <span>Bank Ref: <strong style="font-family:monospace"><?= htmlspecialchars(substr($s['bank_ref'],0,20)) ?></strong></span>
      <span>↔</span>
      <span>Order: <strong>#<?= htmlspecialchars($s['order_number']) ?></strong></span>
      <?php if ($s['utr']): ?>
        <span>UTR: <small style="font-family:monospace"><?= htmlspecialchars($s['utr']) ?></small></span>
      <?php endif; ?>
      <span style="color:#888;font-size:.8rem"><?= $s['days_diff'] ?> days apart</span>
      <button class="match-btn"
        onclick="quickMatch(<?= (int)$s['bank_credit_id'] ?>,'<?= htmlspecialchars($s['payment_id']) ?>')">
        Match &amp; Verify ✓
      </button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Manual match bar -->
  <div class="match-action-bar">
    <strong style="white-space:nowrap">Manual Match:</strong>
    <input id="manualPid"  type="text"   placeholder="Payment ID">
    <input id="manualBcId" type="number" placeholder="Bank Credit ID">
    <input id="manualNotes" type="text"  placeholder="Notes (optional)" style="max-width:200px">
    <button onclick="doManualMatch()"><i class="fas fa-link"></i> Match &amp; Verify</button>
  </div>

  <!-- Side-by-side view -->
  <div class="recon-grid">
    <!-- Bank Credits -->
    <div class="panel">
      <h3><i class="fas fa-university"></i> Unmatched Bank Credits (<?= count($credits) ?>)</h3>
      <?php if (empty($credits)): ?>
        <p style="color:#aaa;text-align:center;padding:1rem">None</p>
      <?php else: ?>
        <?php foreach ($credits as $c): ?>
        <div class="credit-row" id="credit-<?= (int)$c['id'] ?>"
             onclick="selectCredit(<?= (int)$c['id'] ?>)"
             title="Click to select">
          <div style="display:flex;justify-content:space-between">
            <strong><?= $calc->formatINR((int)$c['amount_paise']) ?></strong>
            <small style="color:#888"><?= htmlspecialchars($c['credit_date']) ?></small>
          </div>
          <div style="font-family:monospace;font-size:.8rem;color:#555"><?= htmlspecialchars(substr($c['bank_ref'],0,30)) ?></div>
          <?php if ($c['utr']): ?>
          <div style="font-size:.78rem;color:#888">UTR: <?= htmlspecialchars($c['utr']) ?></div>
          <?php endif; ?>
          <div style="font-size:.78rem;color:#aaa;white-space:nowrap;overflow:hidden"><?= htmlspecialchars(substr($c['narration']??'',0,40)) ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Pending UPI Payments -->
    <div class="panel">
      <h3><i class="fas fa-mobile-alt"></i> Pending UPI Payments (<?= count($payments) ?>)</h3>
      <?php if (empty($payments)): ?>
        <p style="color:#aaa;text-align:center;padding:1rem">None</p>
      <?php else: ?>
        <?php foreach ($payments as $p): ?>
        <div class="payment-row" id="pay-<?= htmlspecialchars($p['payment_id']) ?>"
             onclick="selectPayment('<?= htmlspecialchars($p['payment_id']) ?>')"
             title="Click to select">
          <div style="display:flex;justify-content:space-between">
            <strong><?= $calc->formatINR((int)$p['amount_paise']) ?></strong>
            <small style="color:#888">#<?= htmlspecialchars($p['order_number']) ?></small>
          </div>
          <div style="font-size:.82rem;color:#555"><?= htmlspecialchars($p['customer_name']??'Guest') ?>
            <?= $p['phone'] ? '· ' . htmlspecialchars($p['phone']) : '' ?></div>
          <?php if ($p['customer_utr']): ?>
          <div style="font-family:monospace;font-size:.78rem;color:#1a7f4b">
            UTR: <?= htmlspecialchars($p['customer_utr']) ?>
          </div>
          <?php endif; ?>
          <div style="font-size:.78rem;color:#aaa"><?= date('d M H:i', strtotime($p['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
let selCredit = null;
let selPayment = null;
const csrf = '<?= htmlspecialchars($csrf) ?>';

function selectCredit(id) {
  document.querySelectorAll('.credit-row').forEach(r => r.classList.remove('selected'));
  document.getElementById('credit-' + id)?.classList.add('selected');
  selCredit = id;
  document.getElementById('manualBcId').value = id;
}

function selectPayment(pid) {
  document.querySelectorAll('.payment-row').forEach(r => r.classList.remove('selected'));
  document.getElementById('pay-' + pid)?.classList.add('selected');
  selPayment = pid;
  document.getElementById('manualPid').value = pid;
}

async function quickMatch(bcId, pid) {
  if (!confirm('Match bank credit #' + bcId + ' to payment ' + pid + ' and mark as SUCCESS?')) return;
  await doMatch(bcId, pid, 'Auto-match suggestion');
}

async function doManualMatch() {
  const pid    = document.getElementById('manualPid').value.trim();
  const bcId   = document.getElementById('manualBcId').value.trim();
  const notes  = document.getElementById('manualNotes').value.trim();
  if (!pid || !bcId) { showMsg('Enter both Payment ID and Bank Credit ID.', false); return; }
  if (!confirm('Match credit #' + bcId + ' to payment ' + pid + '?')) return;
  await doMatch(parseInt(bcId), pid, notes);
}

async function doMatch(bcId, pid, notes) {
  const fd = new FormData();
  fd.append('csrf_token',    csrf);
  fd.append('payment_id',   pid);
  fd.append('bank_credit_id', bcId);
  fd.append('notes', notes);
  const r = await fetch('/api/payment/verify.php', {method:'POST', body:fd});
  const d = await r.json();
  showMsg(d.message || d.error, d.success);
  if (d.success) setTimeout(() => location.reload(), 1500);
}

function showMsg(text, ok) {
  const m = document.getElementById('globalMsg');
  m.innerHTML = '<div class="msg-box" style="background:' + (ok?'#e8f5e9':'#fde') + ';color:' + (ok?'#1a7f4b':'#c00') + '">' + text + '</div>';
  m.scrollIntoView({behavior:'smooth'});
}
</script>
</body>
</html>
