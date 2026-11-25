<?php
declare(strict_types=1);
// admin/payments/settlement.php — Settlement reports administration

require_once __DIR__ . '/../../payment-core/bootstrap.php';
use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

$calc   = new AmountCalculator();
$sec    = new SecurityManager();
$csrf   = $sec->generateCSRFToken();
$adminId = (int)$_SESSION['user_id'];
$msg    = '';
$msgType = 'info';

// Generate/refresh daily settlement report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $date = trim($_POST['report_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    try {
        $pdo->beginTransaction();

        // Reusable helper
        $qs = function(string $sql, array $p) use ($pdo): int {
            $s = $pdo->prepare($sql); $s->execute($p);
            return (int)$s->fetchColumn();
        };

        $collectedPaise = $qs("SELECT COALESCE(SUM(amount_paise),0) FROM payment_transactions WHERE status IN ('success','cod_collected') AND DATE(verified_at) = ?", [$date]);
        $refundedPaise  = $qs("SELECT COALESCE(SUM(amount_paise),0) FROM payment_refunds WHERE status='completed' AND DATE(completed_at) = ?", [$date]);
        $upiCount       = $qs("SELECT COUNT(*) FROM payment_transactions WHERE status='success' AND method='upi' AND DATE(verified_at) = ?", [$date]);
        $codCount       = $qs("SELECT COUNT(*) FROM payment_transactions WHERE status='cod_collected' AND DATE(verified_at) = ?", [$date]);
        $bankCount      = $qs("SELECT COUNT(*) FROM payment_transactions WHERE status='success' AND method='bank_transfer' AND DATE(verified_at) = ?", [$date]);
        $netPaise       = $collectedPaise - $refundedPaise;

        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->prepare(
                "INSERT OR REPLACE INTO payment_settlement_reports
                    (report_date, total_collected_paise, total_refunded_paise, net_paise,
                     upi_count, cod_count, bank_transfer_count, generated_at, generated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), ?)"
            )->execute([
                $date, $collectedPaise, $refundedPaise, $netPaise,
                $upiCount, $codCount, $bankCount, $adminId
            ]);
        } else {
            $pdo->prepare(
                "INSERT INTO payment_settlement_reports
                    (report_date, total_collected_paise, total_refunded_paise, net_paise,
                     upi_count, cod_count, bank_transfer_count, generated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    total_collected_paise = VALUES(total_collected_paise),
                    total_refunded_paise  = VALUES(total_refunded_paise),
                    net_paise             = VALUES(net_paise),
                    upi_count             = VALUES(upi_count),
                    cod_count             = VALUES(cod_count),
                    bank_transfer_count   = VALUES(bank_transfer_count),
                    generated_by          = VALUES(generated_by),
                    generated_at          = NOW()"
            )->execute([
                $date, $collectedPaise, $refundedPaise, $netPaise,
                $upiCount, $codCount, $bankCount, $adminId
            ]);
        }

        $pdo->commit();
        $msg     = "Settlement report for {$date} generated successfully.";
        $msgType = 'ok';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg     = 'Error: ' . $e->getMessage();
        $msgType = 'error';
    }
}

// Load reports
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to']   ?? date('Y-m-d'));

$stmt = $pdo->prepare(
    "SELECT psr.*, u.name AS generated_by_name
       FROM payment_settlement_reports psr
       LEFT JOIN users u ON u.id = psr.generated_by
      WHERE psr.report_date BETWEEN ? AND ?
      ORDER BY psr.report_date DESC"
);
$stmt->execute([$from, $to]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settlement Reports — KSR Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1100px;margin:1.5rem auto;padding:0 1rem}
h1{font-size:1.4rem;color:#1a7f4b;margin-bottom:1rem}
.card{background:#fff;border-radius:10px;padding:1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:1.5rem}
.filter-bar{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center}
.filter-bar input{padding:.45rem .7rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem}
.filter-bar button{padding:.45rem 1rem;background:#1a7f4b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.88rem}
table{width:100%;border-collapse:collapse}
th,td{padding:.7rem .9rem;text-align:left;font-size:.88rem;border-bottom:1px solid #f0f0f0}
th{background:#f5f5f5;font-weight:600;color:#555}
.msg-ok{background:#d4edda;color:#155724;padding:.8rem 1rem;border-radius:6px;margin-bottom:1rem}
.msg-error{background:#f8d7da;color:#721c24;padding:.8rem 1rem;border-radius:6px;margin-bottom:1rem}
.nav-links{display:flex;gap:.8rem;margin-bottom:1rem}
.nav-links a{text-decoration:none;color:#1a7f4b;font-size:.9rem;font-weight:600}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>
<div class="admin-wrap">
  <div class="nav-links">
    <a href="/admin/payments/dashboard.php">← Dashboard</a>
    <a href="/admin/payments/reconciliation.php">Reconciliation</a>
    <a href="/admin/payments/reports.php">Financial Reports</a>
  </div>

  <h1><i class="fas fa-file-invoice-dollar"></i> Settlement Reports</h1>

  <?php if ($msg): ?>
    <div class="msg-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <h3 style="margin-top:0;font-size:1rem">Generate / Refresh Daily Report</h3>
    <form method="post" style="display:flex;gap:.6rem;align-items:center">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <label>Date: <input type="date" name="report_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></label>
      <button type="submit" style="padding:.5rem 1.2rem;background:#1a7f4b;color:#fff;border:none;border-radius:6px;cursor:pointer">
        <i class="fas fa-sync"></i> Generate
      </button>
    </form>
  </div>

  <div class="card">
    <form method="get" class="filter-bar" style="margin-bottom:.8rem">
      <label>From: <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
      <label>To: <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
      <button type="submit"><i class="fas fa-search"></i> Filter</button>
    </form>

    <table>
      <thead>
        <tr>
          <th>Date</th><th>Collected</th><th>Refunded</th><th>Net</th>
          <th>UPI</th><th>COD</th><th>Bank</th><th>Generated By</th><th>Generated At</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($reports as $r): ?>
        <tr>
          <td><strong><?= date('d M Y', strtotime($r['report_date'])) ?></strong></td>
          <td style="color:#1a7f4b;font-weight:700">₹<?= number_format((int)$r['total_collected_paise']/100,2) ?></td>
          <td style="color:#e53e3e">₹<?= number_format((int)$r['total_refunded_paise']/100,2) ?></td>
          <td style="font-weight:700">₹<?= number_format((int)$r['net_paise']/100,2) ?></td>
          <td><?= (int)$r['upi_count'] ?></td>
          <td><?= (int)$r['cod_count'] ?></td>
          <td><?= (int)$r['bank_transfer_count'] ?></td>
          <td><?= htmlspecialchars($r['generated_by_name'] ?? 'System') ?></td>
          <td><?= date('d M Y H:i', strtotime($r['generated_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($reports)): ?>
        <tr><td colspan="9" style="text-align:center;color:#aaa;padding:2rem">No reports in this date range. Click Generate to create one.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
