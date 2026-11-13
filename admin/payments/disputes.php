<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

$sec     = new SecurityManager();
$csrf    = $sec->generateCSRFToken();
$adminId = (int)$_SESSION['user_id'];
$msg     = '';

// Resolve / close a dispute
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $disputeId  = (int)($_POST['dispute_id']  ?? 0);
    $resolution = trim($_POST['resolution']   ?? '');
    $newStatus  = trim($_POST['new_status']   ?? '');

    if ($disputeId > 0 && in_array($newStatus, ['investigating','resolved','closed'], true)) {
        $pdo->prepare(
            "UPDATE payment_disputes
             SET status = ?, resolution = ?, resolved_by = ?,
                 resolved_at = IF(? IN ('resolved','closed'), NOW(), resolved_at)
             WHERE id = ?"
        )->execute([$newStatus, $resolution, $adminId, $newStatus, $disputeId]);
        $msg = '<div style="color:#1a7f4b;background:#e8f5e9;padding:.6rem 1rem;border-radius:6px;margin-bottom:.8rem">Dispute updated.</div>';
    }
}

$filter = trim($_GET['filter'] ?? 'open');
$valid  = ['open','investigating','resolved','closed',''];
$filter = in_array($filter, $valid, true) ? $filter : 'open';

$where  = $filter !== '' ? 'WHERE pd.status = ?' : '';
$params = $filter !== '' ? [$filter] : [];

$stmt = $pdo->prepare(
    "SELECT pd.*, o.order_number, u.name AS customer_name, u.phone,
            ar.name AS resolver_name
     FROM payment_disputes pd
     LEFT JOIN orders o ON o.id  = pd.order_id
     LEFT JOIN users  u ON u.id  = pd.user_id
     LEFT JOIN users ar ON ar.id = pd.resolved_by
     {$where}
     ORDER BY pd.opened_at DESC
     LIMIT 100"
);
$stmt->execute($params);
$disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusColors = [
    'open'          => '#e53e3e',
    'investigating' => '#f9a825',
    'resolved'      => '#1a7f4b',
    'closed'        => '#888',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Disputes — Admin — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1100px;margin:1.5rem auto;padding:0 1rem}
.filter-tabs{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap}
.filter-tabs a{padding:.4rem 1rem;border-radius:20px;text-decoration:none;font-size:.85rem;border:1px solid #ddd;color:#555}
.filter-tabs a.active{background:#1a7f4b;color:#fff;border-color:#1a7f4b}
.dispute-card{background:#fff;border-radius:8px;padding:1.2rem;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:1rem}
.dispute-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem;margin-bottom:.7rem}
.badge{display:inline-block;padding:.2rem .6rem;border-radius:12px;font-size:.8rem;font-weight:600;color:#fff}
.desc-text{font-size:.88rem;color:#555;background:#f9f9f9;border-radius:5px;padding:.6rem .8rem;margin-bottom:.8rem;line-height:1.5}
.resolve-form{display:flex;flex-direction:column;gap:.5rem}
.resolve-form select,.resolve-form textarea,.resolve-form input{padding:.5rem .7rem;border:1px solid #ddd;border-radius:6px;font-size:.87rem}
.resolve-form button{padding:.5rem 1.2rem;background:#1a7f4b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;align-self:flex-start}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="admin-wrap">
  <h2><a href="/admin/payments/dashboard.php" style="color:#1a7f4b;text-decoration:none">← Dashboard</a>
  &nbsp; Payment Disputes</h2>

  <?= $msg ?>

  <div class="filter-tabs">
    <?php foreach (['open'=>'Open','investigating'=>'Investigating','resolved'=>'Resolved','closed'=>'Closed',''=>'All'] as $f=>$label): ?>
      <a href="?filter=<?= $f ?>" class="<?= $filter===$f?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($disputes)): ?>
    <p style="color:#aaa;padding:2rem;text-align:center">No disputes found.</p>
  <?php else: ?>
    <?php foreach ($disputes as $d): ?>
      <?php $col = $statusColors[$d['status']] ?? '#888'; ?>
      <div class="dispute-card">
        <div class="dispute-header">
          <div>
            <span class="badge" style="background:<?= $col ?>"><?= htmlspecialchars($d['status']) ?></span>
            &nbsp;
            <strong>#<?= (int)$d['id'] ?></strong>
            &nbsp;
            <span style="font-size:.9rem;color:#555"><?= htmlspecialchars($d['subject']) ?></span>
          </div>
          <div style="font-size:.82rem;color:#888">
            Order: <strong>#<?= htmlspecialchars($d['order_number'] ?? '—') ?></strong>
            &nbsp;|&nbsp;
            <?= htmlspecialchars($d['customer_name'] ?? 'Guest') ?>
            <?= $d['phone'] ? ' · ' . htmlspecialchars($d['phone']) : '' ?>
            &nbsp;|&nbsp;
            <?= date('d M Y H:i', strtotime($d['opened_at'])) ?>
          </div>
        </div>

        <?php if ($d['description']): ?>
        <div class="desc-text"><?= nl2br(htmlspecialchars($d['description'])) ?></div>
        <?php endif; ?>

        <?php if (!empty($d['resolution'])): ?>
        <div style="background:#e8f5e9;border-radius:5px;padding:.5rem .8rem;font-size:.85rem;margin-bottom:.7rem;color:#1a7f4b">
          <strong>Resolution:</strong> <?= nl2br(htmlspecialchars($d['resolution'])) ?>
          <?php if ($d['resolver_name']): ?>
            <small style="color:#888"> — by <?= htmlspecialchars($d['resolver_name']) ?></small>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!in_array($d['status'], ['resolved','closed'], true)): ?>
        <form method="post" class="resolve-form">
          <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="dispute_id"  value="<?= (int)$d['id'] ?>">
          <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            <select name="new_status" required style="max-width:200px">
              <option value="">Update status to…</option>
              <option value="investigating">Investigating</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
            <textarea name="resolution" rows="2" placeholder="Resolution / response to customer…" style="flex:1;min-width:200px"></textarea>
            <button type="submit">Update</button>
          </div>
        </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>

