<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

$calc  = new AmountCalculator();
$sec   = new SecurityManager();
$csrf  = $sec->generateCSRFToken();
$from  = trim($_GET['from'] ?? date('Y-m-01'));
$to    = trim($_GET['to']   ?? date('Y-m-d'));

// Summary
$summary = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN status IN ('success','cod_collected') THEN amount_paise END), 0) AS collected,
        COALESCE(SUM(CASE WHEN status = 'failed'                    THEN amount_paise END), 0) AS failed,
        COUNT(CASE WHEN status IN ('success','cod_collected')         THEN 1 END)              AS success_count,
        COUNT(CASE WHEN status IN ('upi_awaiting','pending')          THEN 1 END)              AS pending_count,
        COUNT(CASE WHEN status = 'failed'                             THEN 1 END)              AS failed_count
     FROM payment_transactions
     WHERE DATE(created_at) BETWEEN ? AND ?"
);
$summary->execute([$from, $to]);
$sum = $summary->fetch(PDO::FETCH_ASSOC);

$refunded = (int)$pdo->prepare(
    "SELECT COALESCE(SUM(amount_paise),0) FROM payment_refunds WHERE status='completed' AND DATE(completed_at) BETWEEN ? AND ?"
)->execute([$from, $to]) ? $pdo->query("SELECT COALESCE(SUM(amount_paise),0) FROM payment_refunds WHERE status='completed' AND DATE(completed_at) BETWEEN '{$from}' AND '{$to}'")->fetchColumn() : 0;

// Daily breakdown
$daily = $pdo->prepare(
    "SELECT DATE(created_at) AS day,
            COUNT(*)                                                                AS total,
            SUM(CASE WHEN status IN ('success','cod_collected') THEN amount_paise END) AS collected,
            COUNT(CASE WHEN status IN ('success','cod_collected') THEN 1 END)         AS success,
            COUNT(CASE WHEN status = 'failed'                    THEN 1 END)          AS failed
     FROM payment_transactions
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY day ORDER BY day DESC"
);
$daily->execute([$from, $to]);
$days = $daily->fetchAll(PDO::FETCH_ASSOC);

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ksr_payment_report_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Total Txns', 'Collected (₹)', 'Success Count', 'Failed Count']);
    foreach ($days as $d) {
        fputcsv($out, [
            $d['day'],
            $d['total'],
            number_format((int)$d['collected'] / 100, 2),
            $d['success'],
            $d['failed'],
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reports — Admin — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1000px;margin:1.5rem auto;padding:0 1rem}
.filter-row{display:flex;gap:.7rem;flex-wrap:wrap;align-items:center;margin-bottom:1.5rem;background:#fff;padding:1rem;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.07)}
.filter-row input{padding:.55rem .7rem;border:1px solid #ddd;border-radius:6px}
.filter-row button,.filter-row a{padding:.55rem 1.1rem;border:none;border-radius:6px;cursor:pointer;font-weight:600;text-decoration:none;font-size:.88rem}
.sum-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:1rem;margin-bottom:1.5rem}
.sum-card{background:#fff;border-radius:8px;padding:1rem 1.2rem;box-shadow:0 2px 8px rgba(0,0,0,.07);text-align:center}
.sum-val{font-size:1.4rem;font-weight:800;color:#1a7f4b}
.sum-label{font-size:.8rem;color:#888}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.07)}
th,td{padding:.6rem .8rem;text-align:left;font-size:.87rem;border-bottom:1px solid #f5f5f5}
th{background:#f5f5f5;font-weight:600}
@media print{ .filter-row,.no-print{display:none} body{margin:0} }
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="admin-wrap">
  <h2><a href="/admin/payments/dashboard.php" style="color:#1a7f4b;text-decoration:none">← Dashboard</a> &nbsp; Payment Reports</h2>

  <form method="get" class="filter-row">
    <label>From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    <label>To</label>  <input type="date" name="to"   value="<?= htmlspecialchars($to) ?>">
    <button type="submit" style="background:#1a7f4b;color:#fff">Apply</button>
    <a href="?from=<?= $from ?>&to=<?= $to ?>&export=csv" style="background:#2196f3;color:#fff" class="no-print">
      <i class="fas fa-download"></i> CSV
    </a>
    <button type="button" onclick="window.print()" style="background:#607d8b;color:#fff" class="no-print">
      <i class="fas fa-print"></i> Print/PDF
    </button>
  </form>

  <div class="sum-grid">
    <div class="sum-card"><div class="sum-val"><?= $calc->formatINR((int)$sum['collected']) ?></div><div class="sum-label">Total Collected</div></div>
    <div class="sum-card"><div class="sum-val"><?= $calc->formatINR((int)$refunded) ?></div><div class="sum-label">Total Refunded</div></div>
    <div class="sum-card"><div class="sum-val"><?= $calc->formatINR(max(0, (int)$sum['collected'] - (int)$refunded)) ?></div><div class="sum-label">Net</div></div>
    <div class="sum-card"><div class="sum-val" style="color:#1a7f4b"><?= (int)$sum['success_count'] ?></div><div class="sum-label">Successful</div></div>
    <div class="sum-card"><div class="sum-val" style="color:#f9a825"><?= (int)$sum['pending_count'] ?></div><div class="sum-label">Pending</div></div>
    <div class="sum-card"><div class="sum-val" style="color:#e53e3e"><?= (int)$sum['failed_count'] ?></div><div class="sum-label">Failed</div></div>
  </div>

  <table>
    <thead><tr><th>Date</th><th>Total Txns</th><th>Collected</th><th>Success</th><th>Failed</th></tr></thead>
    <tbody>
    <?php foreach ($days as $d): ?>
      <tr>
        <td><?= date('d M Y', strtotime($d['day'])) ?></td>
        <td><?= (int)$d['total'] ?></td>
        <td><?= $calc->formatINR((int)($d['collected'] ?? 0)) ?></td>
        <td style="color:#1a7f4b;font-weight:600"><?= (int)$d['success'] ?></td>
        <td style="color:#e53e3e"><?= (int)$d['failed'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body>
</html>

