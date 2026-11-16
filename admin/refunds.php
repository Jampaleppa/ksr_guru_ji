<?php
// ============================================================
// admin/refunds.php – Refund Management
// ============================================================
$currentPage = 'refunds';
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

$pdo = getDBConnection();

$message = '';
$error   = '';

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $refundId = (int)($_POST['refund_id'] ?? 0);
    $action   = $_POST['action'] ?? '';
    $adminId  = $_SESSION['admin_auth_id'] ?? 1;

    if ($refundId > 0 && in_array($action, ['approve', 'reject'], true)) {
        try {
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $pdo->prepare("UPDATE payment_refunds SET status=?, approved_by=?, approved_at=datetime('now','localtime') WHERE id=?")
                ->execute([$newStatus, $adminId, $refundId]);
            $message = 'Refund ' . $newStatus . '.';
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch refunds
$refunds = [];
try {
    $refunds = $pdo->query("SELECT r.*, o.order_number, u.name AS user_name
        FROM payment_refunds r
        LEFT JOIN orders o ON o.id = r.order_id
        LEFT JOIN users u ON u.id = r.requested_by
        ORDER BY r.requested_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Table may not exist yet — not fatal
    $refunds = [];
}

$pageTitle  = 'Refund Management';
require_once 'inc/header.php';
?>
    <div class="page-header">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="subtitle">Review and process customer refund requests.</p>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header"><h3>Refund Requests (<?= count($refunds) ?>)</h3></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($refunds)): ?>
                <div style="text-align:center;padding:40px;color:#94a3b8;">
                    <i class="fas fa-receipt" style="font-size:2.5rem;margin-bottom:12px;"></i>
                    <p>No refund requests found.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th><th>Order</th><th>Customer</th><th>Amount</th>
                                <th>Reason</th><th>Status</th><th>Requested</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($refunds as $r):
                            $statusClass = match($r['status']) {
                                'approved'  => 'badge-success',
                                'rejected'  => 'badge-danger',
                                'completed' => 'badge-info',
                                default     => 'badge-warning',
                            };
                        ?>
                            <tr>
                                <td><?= (int)$r['id'] ?></td>
                                <td><?= htmlspecialchars($r['order_number'] ?? '#' . $r['order_id'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($r['user_name'] ?? '—', ENT_QUOTES) ?></td>
                                <td><strong>₹<?= number_format((float)$r['amount_paise'] / 100, 2) ?></strong></td>
                                <td><?= htmlspecialchars($r['reason'] ?? '—', ENT_QUOTES) ?></td>
                                <td><span class="badge <?= $statusClass ?>"><?= ucfirst($r['status']) ?></span></td>
                                <td style="font-size:0.8rem;"><?= $r['requested_at'] ? date('d M Y', strtotime($r['requested_at'])) : '—' ?></td>
                                <td>
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="refund_id" value="<?= (int)$r['id'] ?>">
                                            <button name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Approve refund?')"><i class="fas fa-check"></i> Approve</button>
                                            <button name="action" value="reject" class="btn btn-sm btn-danger" onclick="return confirm('Reject refund?')"><i class="fas fa-times"></i> Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php require_once 'inc/footer.php'; ?>
