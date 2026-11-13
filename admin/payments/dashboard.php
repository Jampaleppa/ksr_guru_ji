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

// ── Stats ─────────────────────────────────────────────────────────────────────
function qs(PDO $pdo, string $sql, array $p = []): mixed {
    $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn();
}

$totalCollectedPaise = (int)qs($pdo, "SELECT COALESCE(SUM(amount_paise),0) FROM payment_transactions WHERE status IN ('success','cod_collected')");
$todayCollectedPaise = (int)qs($pdo, "SELECT COALESCE(SUM(amount_paise),0) FROM payment_transactions WHERE status IN ('success','cod_collected') AND DATE(verified_at)=CURDATE()");
$pendingUPI          = (int)qs($pdo, "SELECT COUNT(*) FROM payment_transactions WHERE status IN ('upi_awaiting','upi_pending_verification') AND method='upi'");
$failedToday         = (int)qs($pdo, "SELECT COUNT(*) FROM payment_transactions WHERE status='failed' AND DATE(created_at)=CURDATE()");
$pendingRefunds      = (int)qs($pdo, "SELECT COUNT(*) FROM payment_refunds WHERE status='pending'");
$unmatchedCredits    = (int)qs($pdo, "SELECT COUNT(*) FROM payment_bank_credits WHERE match_status='unmatched'");
$dupUTRs             = (int)qs($pdo,
    "SELECT COUNT(*) FROM (
       SELECT customer_utr FROM payment_transactions
       WHERE customer_utr IS NOT NULL AND customer_utr != ''
       GROUP BY customer_utr HAVING COUNT(*) > 1
     ) x");

// ── Recent transactions ───────────────────────────────────────────────────────
$recentStmt = $pdo->query(
    "SELECT pt.payment_id, pt.amount_paise, pt.method, pt.status, pt.created_at,
            o.order_number, u.name AS customer
     FROM payment_transactions pt
     JOIN orders o ON o.id = pt.order_id
     LEFT JOIN users u ON u.id = pt.user_id
     ORDER BY pt.created_at DESC LIMIT 10"
);
$recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

$statusColors = [
    'success'      => '#1a7f4b', 'cod_collected' => '#1a7f4b',
    'upi_awaiting' => '#f9a825', 'pending'       => '#f9a825',
    'failed'       => '#e53e3e', 'cancelled'     => '#e53e3e',
    'refunded'     => '#2196f3', 'refund_pending'=> '#ff9800',
    'initiated'    => '#888',    'cod_pending'   => '#607d8b',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Dashboard — Admin — KSR GURU JI</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1200px;margin:1.5rem auto;padding:0 1rem}
h1{font-size:1.4rem;margin-bottom:1.2rem;color:#1a7f4b}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:1rem;margin-bottom:2rem}
.stat-card{background:#fff;border-radius:10px;padding:1.2rem 1.4rem;box-shadow:0 2px 10px rgba(0,0,0,.07)}
.stat-val{font-size:1.6rem;font-weight:800;color:#1a7f4b}
.stat-val.warn{color:#f9a825} .stat-val.danger{color:#e53e3e} .stat-val.info{color:#2196f3}
.stat-label{font-size:.82rem;color:#888;margin-top:.2rem}
.section-title{font-weight:700;font-size:1rem;margin:1.5rem 0 .8rem;border-bottom:2px solid #f0f0f0;padding-bottom:.4rem}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.07)}
th,td{padding:.6rem .9rem;text-align:left;font-size:.88rem}
th{background:#f5f5f5;font-weight:600;color:#555}
tr:hover{background:#f9f9f9}
.badge{display:inline-block;padding:.2rem .6rem;border-radius:12px;font-size:.78rem;font-weight:600;color:#fff}
.action-btns a,.action-btns button{margin-right:.4rem;padding:.3rem .7rem;border-radius:5px;font-size:.82rem;cursor:pointer;border:none;text-decoration:none;font-weight:600}
.btn-verify{background:#1a7f4b;color:#fff}
.btn-view{background:#e8f4fd;color:#1976d2}
.quick-links{display:flex;flex-wrap:wrap;gap:.7rem;margin-bottom:1.5rem}
.quick-links a{padding:.55rem 1.1rem;border-radius:7px;text-decoration:none;font-weight:600;font-size:.88rem;border:1px solid #ddd}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="admin-wrap">
  <h1><i class="fas fa-chart-line"></i> Payment Dashboard</h1>

  <!-- Quick actions -->
  <div class="quick-links">
    <a href="/admin/payments/transactions.php" style="color:#1a7f4b;border-color:#1a7f4b"><i class="fas fa-list"></i> All Transactions</a>
    <a href="/admin/payments/pending.php"      style="color:#f9a825;border-color:#f9a825"><i class="fas fa-clock"></i> Pending UPI (<?= $pendingUPI ?>)</a>
    <a href="/admin/payments/bank-credits.php" style="color:#2196f3;border-color:#2196f3"><i class="fas fa-university"></i> Bank Credits</a>
    <a href="/admin/payments/reconciliation.php" style="color:#9c27b0;border-color:#9c27b0"><i class="fas fa-link"></i> Reconcile</a>
    <a href="/admin/payments/refunds.php"      style="color:#ff9800;border-color:#ff9800"><i class="fas fa-undo"></i> Refunds (<?= $pendingRefunds ?>)</a>
    <a href="/admin/payments/reports.php"      style="color:#607d8b;border-color:#607d8b"><i class="fas fa-file-alt"></i> Reports</a>
    <a href="/admin/payments/settings.php"     style="color:#555;border-color:#ccc"><i class="fas fa-cog"></i> Settings</a>
  </div>

  <!-- Stat cards -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-val"><?= $calc->formatINR($totalCollectedPaise) ?></div>
      <div class="stat-label"><i class="fas fa-coins"></i> Total Collected</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $calc->formatINR($todayCollectedPaise) ?></div>
      <div class="stat-label"><i class="fas fa-calendar-day"></i> Today's Collection</div>
    </div>
    <div class="stat-card">
      <div class="stat-val warn"><?= $pendingUPI ?></div>
      <div class="stat-label"><i class="fas fa-mobile-alt"></i> Pending UPI Verifications</div>
    </div>
    <div class="stat-card">
      <div class="stat-val danger"><?= $failedToday ?></div>
      <div class="stat-label"><i class="fas fa-times-circle"></i> Failed Today</div>
    </div>
    <div class="stat-card">
      <div class="stat-val warn"><?= $pendingRefunds ?></div>
      <div class="stat-label"><i class="fas fa-undo"></i> Pending Refunds</div>
    </div>
    <div class="stat-card">
      <div class="stat-val info"><?= $unmatchedCredits ?></div>
      <div class="stat-label"><i class="fas fa-university"></i> Unmatched Bank Credits</div>
    </div>
    <?php if ($dupUTRs > 0): ?>
    <div class="stat-card" style="border:2px solid #e53e3e">
      <div class="stat-val danger"><?= $dupUTRs ?></div>
      <div class="stat-label"><i class="fas fa-exclamation-triangle"></i> Duplicate UTR Alerts</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recent Transactions -->
  <div class="section-title">Recent Transactions</div>
  <table>
    <thead><tr><th>Payment ID</th><th>Order</th><th>Customer</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <?php $st = ksrNormalizePaymentStatus($r['status']); $col = $statusColors[$st] ?? '#888'; ?>
      <tr>
        <td><small style="font-family:monospace"><?= htmlspecialchars(substr($r['payment_id'], 0, 20)) ?></small></td>
        <td>#<?= htmlspecialchars($r['order_number']) ?></td>
        <td><?= htmlspecialchars($r['customer'] ?? 'Guest') ?></td>
        <td><?= $calc->formatINR((int)$r['amount_paise']) ?></td>
        <td><?= htmlspecialchars(ucfirst($r['method'])) ?></td>
        <td><span class="badge" style="background:<?= $col ?>"><?= htmlspecialchars($r['status']) ?></span></td>
        <td><?= date('d M, H:i', strtotime($r['created_at'])) ?></td>
        <td class="action-btns">
          <?php if (in_array($st, ['upi_awaiting','upi_pending_verification'], true)): ?>
            <button class="btn-verify" onclick="openVerifyModal('<?= htmlspecialchars($r['payment_id']) ?>')">Verify</button>
          <?php endif; ?>
          <a class="btn-view" href="/admin/payments/transactions.php?search=<?= urlencode($r['payment_id']) ?>">View</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Verify Modal -->
<div id="verifyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:1.5rem;max-width:420px;width:90%">
    <h3 style="margin-top:0">Verify UPI Payment</h3>
    <p style="font-size:.88rem;color:#888">Select the matching bank credit and confirm.</p>
    <label style="display:block;margin-bottom:.4rem;font-weight:600">Bank Credit ID</label>
    <input type="number" id="bcId" placeholder="Bank credit ID" style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:6px;margin-bottom:.8rem">
    <label style="display:block;margin-bottom:.4rem;font-weight:600">Notes (optional)</label>
    <input type="text" id="vcNotes" placeholder="Verification notes" style="width:100%;padding:.6rem;border:1px solid #ddd;border-radius:6px;margin-bottom:1rem">
    <div id="verifyMsg" style="display:none;padding:.6rem;border-radius:5px;margin-bottom:.8rem;font-size:.88rem"></div>
    <button onclick="submitVerify()" style="background:#1a7f4b;color:#fff;padding:.65rem 1.5rem;border:none;border-radius:7px;cursor:pointer;font-weight:700">Confirm Verification</button>
    <button onclick="closeVerifyModal()" style="background:#f0f0f0;padding:.65rem 1.5rem;border:none;border-radius:7px;cursor:pointer;margin-left:.5rem">Cancel</button>
  </div>
</div>

<script>
let currentPid = '';
function openVerifyModal(pid) {
  currentPid = pid;
  document.getElementById('verifyModal').style.display = 'flex';
  document.getElementById('verifyMsg').style.display = 'none';
}
function closeVerifyModal() {
  document.getElementById('verifyModal').style.display = 'none';
}
async function submitVerify() {
  const bcId  = document.getElementById('bcId').value;
  const notes = document.getElementById('vcNotes').value;
  const msg   = document.getElementById('verifyMsg');
  if (!bcId) { msg.textContent='Enter a bank credit ID'; msg.style.cssText='display:block;background:#fde;color:#c00;'; return; }
  const fd = new FormData();
  fd.append('csrf_token',    '<?= htmlspecialchars($csrf) ?>');
  fd.append('payment_id',   currentPid);
  fd.append('bank_credit_id', bcId);
  fd.append('notes', notes);
  const r = await fetch('/api/payment/verify.php', {method:'POST', body:fd});
  const d = await r.json();
  msg.style.cssText = `display:block;background:${d.success?'#dfd':'#fde'};color:${d.success?'#1a7f4b':'#c00'};`;
  msg.textContent = d.message || d.error;
  if (d.success) setTimeout(() => location.reload(), 1200);
}
</script>
</body>
</html>

