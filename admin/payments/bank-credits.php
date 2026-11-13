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
$msg     = '';

// Handle manual import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_credit'])) {
    if (!$sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = '<div style="color:#c00">Invalid CSRF token.</div>';
    } else {
        $recon = new ReconciliationManager($pdo, new AuditLogger($pdo));
        $amtPaise = (int)round((float)($_POST['amount_inr'] ?? 0) * 100);
        try {
            $recon->importCredit([
                'credit_date' => $_POST['credit_date'] ?? date('Y-m-d'),
                'bank_ref'    => trim($_POST['bank_ref'] ?? ''),
                'utr'         => trim($_POST['utr'] ?? ''),
                'narration'   => trim($_POST['narration'] ?? ''),
                'amount_paise'=> $amtPaise,
                'notes'       => trim($_POST['notes'] ?? ''),
            ], $adminId);
            $msg = '<div style="color:#1a7f4b">Credit imported successfully.</div>';
        } catch (\Throwable $e) {
            $msg = '<div style="color:#c00">' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

$filter = trim($_GET['filter'] ?? '');
$validFilters = ['unmatched', 'matched', 'disputed', ''];
$filter = in_array($filter, $validFilters, true) ? $filter : '';

$where  = $filter !== '' ? "WHERE match_status = ?" : '';
$params = $filter !== '' ? [$filter] : [];

$stmt = $pdo->prepare(
    "SELECT pbc.*, u.name AS importer
     FROM payment_bank_credits pbc
     LEFT JOIN users u ON u.id = pbc.imported_by
     {$where}
     ORDER BY pbc.credit_date DESC, pbc.imported_at DESC
     LIMIT 100"
);
$stmt->execute($params);
$credits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bank Credits — Admin — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1100px;margin:1.5rem auto;padding:0 1rem}
.import-form{background:#fff;border-radius:10px;padding:1.2rem;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:1.5rem}
.import-form h3{margin-top:0;font-size:1rem}
.import-form .row{display:flex;flex-wrap:wrap;gap:.7rem}
.import-form input{padding:.55rem .7rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem;flex:1;min-width:140px}
.btn-import{padding:.55rem 1.2rem;background:#1a7f4b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;font-size:.88rem}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.07)}
th,td{padding:.6rem .8rem;text-align:left;font-size:.85rem;border-bottom:1px solid #f5f5f5}
th{background:#f5f5f5;font-weight:600}
.match-badge{display:inline-block;padding:.15rem .5rem;border-radius:10px;font-size:.75rem;font-weight:600}
.filter-tabs{display:flex;gap:.5rem;margin-bottom:.8rem}
.filter-tabs a{padding:.4rem 1rem;border-radius:20px;text-decoration:none;font-size:.85rem;border:1px solid #ddd;color:#555}
.filter-tabs a.active{background:#1a7f4b;color:#fff;border-color:#1a7f4b}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="admin-wrap">
  <h2><a href="/admin/payments/dashboard.php" style="color:#1a7f4b;text-decoration:none">← Dashboard</a> &nbsp; Bank Credits</h2>

  <?= $msg ?>

  <!-- Manual Import Form -->
  <div class="import-form">
    <h3><i class="fas fa-plus-circle" style="color:#1a7f4b;"></i> Import Bank Credit (Manual)</h3>
    <form method="post">
      <input type="hidden" name="csrf_token"    value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="import_credit" value="1">
      <div class="row">
        <input type="date"   name="credit_date" required placeholder="Credit Date"    title="Credit Date">
        <input type="text"   name="bank_ref"    required placeholder="Bank Ref *"     title="Bank transaction reference">
        <input type="text"   name="utr"                  placeholder="UTR (optional)" title="UPI Transaction Reference">
        <input type="number" name="amount_inr"  required placeholder="Amount ₹ *" step="0.01" min="0.01">
        <input type="text"   name="narration"            placeholder="Narration">
        <input type="text"   name="notes"                placeholder="Notes">
        <button type="submit" class="btn-import"><i class="fas fa-upload"></i> Import</button>
      </div>
    </form>
  </div>

  <!-- Filter tabs -->
  <div class="filter-tabs">
    <a href="?" class="<?= $filter===''?'active':'' ?>">All</a>
    <a href="?filter=unmatched" class="<?= $filter==='unmatched'?'active':'' ?>">Unmatched</a>
    <a href="?filter=matched"   class="<?= $filter==='matched'?'active':'' ?>">Matched</a>
    <a href="?filter=disputed"  class="<?= $filter==='disputed'?'active':'' ?>">Disputed</a>
  </div>

  <table>
    <thead>
      <tr><th>Date</th><th>Bank Ref</th><th>UTR</th><th>Amount</th><th>Narration</th><th>Status</th><th>Matched To</th><th>Imported By</th></tr>
    </thead>
    <tbody>
    <?php foreach ($credits as $c): ?>
      <?php $col = match($c['match_status']) { 'matched'=>'#1a7f4b', 'disputed'=>'#e53e3e', default=>'#f9a825' }; ?>
      <tr>
        <td><?= htmlspecialchars($c['credit_date']) ?></td>
        <td><small style="font-family:monospace"><?= htmlspecialchars($c['bank_ref']) ?></small></td>
        <td><small style="font-family:monospace"><?= htmlspecialchars($c['utr'] ?? '—') ?></small></td>
        <td><?= $calc->formatINR((int)$c['amount_paise']) ?></td>
        <td style="max-width:200px;overflow:hidden;white-space:nowrap"><?= htmlspecialchars(substr($c['narration']??'',0,40)) ?></td>
        <td><span class="match-badge" style="background:<?= $col ?>;color:#fff"><?= htmlspecialchars($c['match_status']) ?></span></td>
        <td><?= $c['matched_payment_id'] ? '<small style="font-family:monospace">#' . (int)$c['matched_payment_id'] . '</small>' : '—' ?></td>
        <td><?= htmlspecialchars($c['importer'] ?? 'System') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body>
</html>

