<?php
declare(strict_types=1);
// admin/payments/cod.php — COD collections & handover management

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;
use KsrPay\CodManager;

SecurityManager::requireAdminPage();

$calc    = new AmountCalculator();
$sec     = new SecurityManager();
$csrf    = $sec->generateCSRFToken();
$adminId = (int)$_SESSION['user_id'];
$msg     = '';
$msgType = 'ok';

// ── Handle Handover Record ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid CSRF token.'; $msgType = 'error';
    } elseif ($_POST['action'] === 'record_handover') {
        $collectionId        = (int)($_POST['collection_id'] ?? 0);
        $receivedInrRaw      = trim($_POST['received_amount_inr'] ?? '');
        $receivedAmountPaise = $receivedInrRaw !== '' ? AmountCalculator::toPaise((float)$receivedInrRaw) : 0;
        $handoverNotes       = trim($_POST['notes'] ?? '');

        if ($collectionId < 1 || $receivedAmountPaise <= 0) {
            $msg = 'Collection record and a valid received amount (> ₹0) are required.';
            $msgType = 'error';
        } else {
            try {
                // Look up delivery_partner_id from cod_collections
                $cStmt = $pdo->prepare("SELECT delivery_partner_id FROM cod_collections WHERE id = ? LIMIT 1");
                $cStmt->execute([$collectionId]);
                $partnerId = (int)($cStmt->fetchColumn() ?: 0);

                $cod = new CodManager($pdo);
                $res = $cod->recordHandover($collectionId, $partnerId, $adminId, $receivedAmountPaise, $handoverNotes);

                if (!empty($res['success'])) {
                    $disc = (int)($res['discrepancy'] ?? 0);
                    $msg  = "Handover #{$res['handover_id']} recorded (status: {$res['status']}"
                          . ($disc !== 0 ? ', discrepancy: ' . AmountCalculator::formatINR($disc) : '')
                          . ').';
                    $msgType = $disc === 0 ? 'ok' : 'error';
                } else {
                    $msg     = $res['message'] ?? 'Could not record handover.';
                    $msgType = 'error';
                }
            } catch (Throwable $e) {
                $msg = 'Error: ' . $e->getMessage(); $msgType = 'error';
            }
        }
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$tab    = in_array($_GET['tab'] ?? '', ['pending', 'collected', 'handover'], true) ? $_GET['tab'] : 'pending';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to']   ?? '');

if (!function_exists('qs')) {
    function qs(PDO $pdo, string $sql, array $p = []): int
    {
        $s = $pdo->prepare($sql);
        $s->execute($p);
        return (int)$s->fetchColumn();
    }
}

// ── Stats ────────────────────────────────────────────────────────────────────
$pendingCollectPaise = qs($pdo, "SELECT COALESCE(SUM(amount_paise),0) FROM payment_transactions WHERE method='cod' AND status='cod_pending'");
$pendingCount        = qs($pdo, "SELECT COUNT(*) FROM payment_transactions WHERE method='cod' AND status='cod_pending'");

$collectedPaise = qs(
    $pdo,
    "SELECT COALESCE(SUM(cc.collected_amount_paise),0)
       FROM cod_collections cc
       LEFT JOIN cod_handovers ch ON ch.cod_collection_id = cc.id AND ch.handover_status IN ('completed','discrepancy')
      WHERE cc.collection_status IN ('cod_collected','cod_delivered','cod_discrepancy') AND ch.id IS NULL"
);
$collectedCount = qs(
    $pdo,
    "SELECT COUNT(*)
       FROM cod_collections cc
       LEFT JOIN cod_handovers ch ON ch.cod_collection_id = cc.id AND ch.handover_status IN ('completed','discrepancy')
      WHERE cc.collection_status IN ('cod_collected','cod_delivered','cod_discrepancy') AND ch.id IS NULL"
);

$handedOverPaise = qs($pdo, "SELECT COALESCE(SUM(received_amount_paise),0) FROM cod_handovers WHERE handover_status IN ('completed','discrepancy')");
$handoverCount   = qs($pdo, "SELECT COUNT(*) FROM cod_handovers WHERE handover_status IN ('completed','discrepancy')");

// ── List Query ───────────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($tab === 'pending') {
    $where[]   = "pt.method = 'cod' AND pt.status = 'cod_pending'";
    $baseSQL   = "FROM payment_transactions pt
                  JOIN orders o ON o.id = pt.order_id
                  LEFT JOIN users u ON u.id = pt.user_id";
    $selFields = "pt.id AS txn_id, pt.payment_id, pt.amount_paise AS display_paise, pt.created_at,
                  o.order_number, u.name AS customer, u.phone, NULL AS collected_at,
                  NULL AS partner_name, NULL AS admin_name, 'cod_pending' AS display_status";
    $orderCol  = "pt.created_at";
    $countExpr = "pt.id";
} elseif ($tab === 'collected') {
    $baseSQL   = "FROM cod_collections cc
                  JOIN payment_transactions pt ON pt.id = cc.payment_transaction_id
                  JOIN orders o ON o.id = cc.order_id
                  LEFT JOIN users u ON u.id = pt.user_id
                  LEFT JOIN users up ON up.id = cc.delivery_partner_id
                  LEFT JOIN cod_handovers ch ON ch.cod_collection_id = cc.id AND ch.handover_status IN ('completed','discrepancy')";
    $selFields = "pt.id AS txn_id, pt.payment_id, cc.collected_amount_paise AS display_paise, pt.created_at,
                  o.order_number, u.name AS customer, u.phone, cc.collected_at,
                  up.name AS partner_name, NULL AS admin_name, cc.collection_status AS display_status";
    $where[]   = "cc.collection_status IN ('cod_collected','cod_delivered','cod_discrepancy')";
    $orderCol  = "cc.created_at";
    $countExpr = "cc.id";
} else {
    $baseSQL   = "FROM cod_handovers ch
                  JOIN cod_collections cc ON cc.id = ch.cod_collection_id
                  JOIN payment_transactions pt ON pt.id = cc.payment_transaction_id
                  JOIN orders o ON o.id = cc.order_id
                  LEFT JOIN users u ON u.id = pt.user_id
                  LEFT JOIN users up ON up.id = ch.delivery_partner_id
                  LEFT JOIN users ua ON ua.id = ch.received_by_admin_id";
    $selFields = "pt.id AS txn_id, pt.payment_id, ch.received_amount_paise AS display_paise, pt.created_at,
                  o.order_number, u.name AS customer, u.phone, ch.handover_at AS collected_at,
                  up.name AS partner_name, ua.name AS admin_name, ch.handover_status AS display_status";
    $orderCol  = "ch.created_at";
    $countExpr = "ch.id";
}

if ($from !== '') { $where[] = "DATE(pt.created_at) >= ?"; $params[] = $from; }
if ($to   !== '') { $where[] = "DATE(pt.created_at) <= ?"; $params[] = $to;   }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total    = qs($pdo, "SELECT COUNT(DISTINCT {$countExpr}) {$baseSQL} {$whereSQL}", $params);
$pages    = max(1, (int)ceil($total / $limit));

$listStmt = $pdo->prepare(
    "SELECT {$selFields}
       {$baseSQL}
       {$whereSQL}
      ORDER BY {$orderCol} DESC
      LIMIT {$limit} OFFSET {$offset}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Collections awaiting physical handover ───────────────────────────────────
$pendingHandovers = $pdo->query(
    "SELECT cc.id, pt.payment_id, cc.collected_amount_paise, u.name AS customer
       FROM cod_collections cc
       JOIN payment_transactions pt ON pt.id = cc.payment_transaction_id
       LEFT JOIN users u ON u.id = pt.user_id
       LEFT JOIN cod_handovers ch ON ch.cod_collection_id = cc.id AND ch.handover_status IN ('completed','discrepancy')
      WHERE cc.collection_status IN ('cod_collected','cod_delivered','cod_discrepancy')
        AND ch.id IS NULL
      ORDER BY cc.collected_at DESC
      LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>COD Management — KSR Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:1200px;margin:1.5rem auto;padding:0 1rem}
h1{font-size:1.4rem;color:#1a7f4b;margin-bottom:1rem}
.nav-links{display:flex;gap:.8rem;margin-bottom:1rem;flex-wrap:wrap}
.nav-links a{text-decoration:none;color:#1a7f4b;font-size:.9rem;font-weight:600}
.stats-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
.stat-card{flex:1;min-width:170px;background:#fff;border-radius:10px;padding:1rem 1.4rem;box-shadow:0 2px 10px rgba(0,0,0,.07)}
.stat-val{font-size:1.4rem;font-weight:800;color:#1a7f4b}
.stat-label{font-size:.82rem;color:#888;margin-top:.2rem}
.card{background:#fff;border-radius:10px;padding:1.4rem;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:1.5rem}
.tabs{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap}
.tabs a{padding:.45rem 1rem;border:1px solid #ddd;border-radius:6px;text-decoration:none;font-size:.88rem;color:#555;font-weight:600}
.tabs a.active{background:#1a7f4b;color:#fff;border-color:#1a7f4b}
.filter-bar{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;align-items:center}
.filter-bar input,.filter-bar select,.form-row input,.form-row select{padding:.45rem .7rem;border:1px solid #ddd;border-radius:6px;font-size:.88rem}
.btn{padding:.45rem 1rem;background:#1a7f4b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.88rem;font-weight:600}
.form-row{display:flex;gap:.8rem;flex-wrap:wrap;align-items:end}
.form-row label{display:block;font-size:.8rem;color:#555;margin-bottom:.25rem;font-weight:600}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.07)}
th,td{padding:.6rem .9rem;text-align:left;font-size:.88rem;border-bottom:1px solid #f0f0f0}
th{background:#f5f5f5;font-weight:600;color:#555}
.badge{display:inline-block;padding:.2rem .6rem;border-radius:12px;font-size:.78rem;font-weight:600;color:#fff;background:#607d8b}
.badge-ok{background:#1a7f4b} .badge-warn{background:#ff9800} .badge-err{background:#e53e3e}
.msg-ok{background:#d4edda;color:#155724;padding:.8rem 1rem;border-radius:6px;margin-bottom:1rem}
.msg-error{background:#f8d7da;color:#721c24;padding:.8rem 1rem;border-radius:6px;margin-bottom:1rem}
.pagination{display:flex;gap:.4rem;margin-top:1rem;flex-wrap:wrap}
.pagination a{padding:.35rem .7rem;border:1px solid #ddd;border-radius:5px;text-decoration:none;font-size:.85rem;color:#1a7f4b}
.pagination a.active{background:#1a7f4b;color:#fff;border-color:#1a7f4b}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>
<div class="admin-wrap">
  <div class="nav-links">
    <a href="/admin/payments/dashboard.php">← Dashboard</a>
    <a href="/admin/payments/transactions.php">All Transactions</a>
    <a href="/admin/payments/settlement.php">Settlements</a>
    <a href="/admin/payments/support-cases.php">Support Cases</a>
  </div>

  <h1><i class="fas fa-money-bill-wave"></i> COD Management</h1>

  <?php if ($msg): ?>
    <div class="msg-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-val" style="color:#f9a825"><?= $pendingCount ?></div>
      <div class="stat-label">Pending Delivery (<?= AmountCalculator::formatINR($pendingCollectPaise) ?>)</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#2196f3"><?= $collectedCount ?></div>
      <div class="stat-label">Cash in Transit (<?= AmountCalculator::formatINR($collectedPaise) ?>)</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $handoverCount ?></div>
      <div class="stat-label">Handed Over (<?= AmountCalculator::formatINR($handedOverPaise) ?>)</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= AmountCalculator::formatINR($pendingCollectPaise + $collectedPaise) ?></div>
      <div class="stat-label">Total Outstanding Cash</div>
    </div>
  </div>

  <?php if ($pendingHandovers): ?>
  <div class="card">
    <h3 style="margin-top:0;font-size:1rem">Record Cash Handover to Accounts</h3>
    <form method="post" class="form-row">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="record_handover">
      <div>
        <label>Collection Record *</label>
        <select name="collection_id" id="handoverCollection" required onchange="fillExpectedAmount(this)">
          <option value="">— Select collection —</option>
          <?php foreach ($pendingHandovers as $ph): ?>
            <option value="<?= (int)$ph['id'] ?>" data-inr="<?= number_format((int)$ph['collected_amount_paise'] / 100, 2, '.', '') ?>">
              #<?= (int)$ph['id'] ?> | <?= htmlspecialchars($ph['payment_id']) ?> | <?= AmountCalculator::formatINR((int)$ph['collected_amount_paise']) ?> | <?= htmlspecialchars($ph['customer'] ?? 'N/A') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Received Amount (₹) *</label>
        <input type="number" step="0.01" min="0.01" name="received_amount_inr" id="receivedAmountInr" placeholder="0.00" required>
      </div>
      <div style="flex:1;min-width:200px">
        <label>Handover Notes</label>
        <input type="text" name="notes" style="width:100%" placeholder="Envelope ref, denominations, discrepancies...">
      </div>
      <div>
        <button type="submit" class="btn"><i class="fas fa-check"></i> Record Handover</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="tabs">
    <?php foreach (['pending' => 'Pending Delivery', 'collected' => 'Collections', 'handover' => 'Handovers'] as $t => $lbl): ?>
      <a class="<?= $tab === $t ? 'active' : '' ?>"
         href="?tab=<?= $t ?><?= $from ? '&from=' . urlencode($from) : '' ?><?= $to ? '&to=' . urlencode($to) : '' ?>">
        <?= $lbl ?>
      </a>
    <?php endforeach; ?>
  </div>

  <form method="get" class="filter-bar">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    <label>From: <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
    <label>To: <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
    <button type="submit" class="btn"><i class="fas fa-search"></i> Filter</button>
    <a href="?tab=<?= urlencode($tab) ?>" style="padding:.45rem .9rem;border:1px solid #ddd;border-radius:6px;text-decoration:none;font-size:.88rem;color:#555">Reset</a>
  </form>

  <table>
    <thead>
      <tr>
        <th>Payment ID</th><th>Order</th><th>Customer</th><th>Amount</th>
        <th>Created</th><th>Partner</th><th>Collected / Handover At</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="8" style="text-align:center;color:#aaa;padding:2rem">No COD records found.</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <?php
      $st = $r['display_status'] ?? '';
      $cls = match ($st) {
          'completed', 'cod_collected', 'cod_delivered' => 'badge-ok',
          'cod_discrepancy', 'discrepancy', 'disputed'  => 'badge-err',
          'cod_pending', 'pending'                      => 'badge-warn',
          default                                       => '',
      };
      ?>
      <tr>
        <td style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($r['payment_id']) ?></td>
        <td><?= htmlspecialchars($r['order_number']) ?></td>
        <td><?= htmlspecialchars($r['customer'] ?? '—') ?><?= !empty($r['phone']) ? '<br><small style="color:#888">' . htmlspecialchars($r['phone']) . '</small>' : '' ?></td>
        <td><strong><?= AmountCalculator::formatINR((int)$r['display_paise']) ?></strong></td>
        <td><?= htmlspecialchars(substr((string)$r['created_at'], 0, 16)) ?></td>
        <td><?= htmlspecialchars($r['partner_name'] ?? '—') ?><?= !empty($r['admin_name']) ? ' → ' . htmlspecialchars($r['admin_name']) : '' ?></td>
        <td><?= $r['collected_at'] ? htmlspecialchars(substr((string)$r['collected_at'], 0, 16)) : '—' ?></td>
        <td><span class="badge <?= $cls ?>"><?= htmlspecialchars($st) ?></span></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?tab=<?= urlencode($tab) ?>&page=<?= $i ?><?= $from ? '&from=' . urlencode($from) : '' ?><?= $to ? '&to=' . urlencode($to) : '' ?>"
         class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<script>
function fillExpectedAmount(sel) {
    const opt = sel.options[sel.selectedIndex];
    const inr = opt ? opt.getAttribute('data-inr') : '';
    if (inr) document.getElementById('receivedAmountInr').value = inr;
}
</script>
</body>
</html>
