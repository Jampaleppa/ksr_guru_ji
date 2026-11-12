<?php
// ============================================================
// delivery/suggestions.php - Assigned Product Sourcing Requests
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
delivery_require_login('suggestions.php');

$agentId = delivery_id();
$pdo = delivery_db();
$msg = '';

// Handle agent updating sourcing status (e.g., mark as SOURCED)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_sourcing') {
    $suggId = (int)($_POST['id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));
    $supplierInfo = trim((string)($_POST['supplier_info'] ?? ''));

    if ($suggId > 0 && in_array($status, ['sourced', 'under_review'], true)) {
        $stmt = $pdo->prepare("
            UPDATE product_suggestions
            SET status = ?, supplier_info = CONCAT(IFNULL(supplier_info, ''), '\n[Agent Update]: ', ?)
            WHERE id = ? AND assigned_delivery_id = ?
        ");
        $stmt->execute([$status, $supplierInfo, $suggId, $agentId]);
        $msg = "Sourcing status updated successfully.";
    }
}

// Fetch requests assigned to this agent or approved open requests
$stmt = $pdo->prepare("
    SELECT * FROM product_suggestions
    WHERE assigned_delivery_id = ? OR (status = 'approved' AND assigned_delivery_id IS NULL)
    ORDER BY id DESC
");
$stmt->execute([$agentId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

delivery_layout('Product Sourcing', 'suggestions', function () use ($requests, $agentId, $msg) {
?>
    <section class="dp-hero">
        <div class="dp-hero-l">
            <h2>Product Sourcing Tasks</h2>
            <p class="dp-hero-p">Items requested by customers and approved by Admin for procurement</p>
        </div>
    </section>

    <?php if ($msg): ?>
        <div style="background:#dcfce7; color:#15803d; padding:12px; border-radius:8px; margin-bottom:16px;">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <section class="dp-card">
        <h3 style="margin-bottom:14px;"><i class="fa-solid fa-list-check"></i> Assigned Sourcing Requests (<?= count($requests) ?>)</h3>
        <?php if (empty($requests)): ?>
            <p style="text-align:center; padding:30px; color:#64748b;">No active sourcing requests assigned to you.</p>
        <?php endif; ?>

        <?php foreach ($requests as $r): ?>
            <div style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; margin-bottom:12px; background:#fff;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
                    <div>
                        <span style="font-size:0.75rem; font-family:monospace; font-weight:800; background:#f1f5f9; padding:2px 8px; border-radius:4px;">
                            <?= e($r['request_id']) ?>
                        </span>
                        <h4 style="font-size:1.1rem; margin:6px 0 2px; color:#0f172a;"><?= e($r['product_name']) ?></h4>
                        <span style="font-size:0.85rem; color:#0f7b3a; font-weight:600;"><?= e($r['category_name'] ?: 'General') ?></span>
                    </div>
                    <div>
                        <span style="font-size:0.8rem; font-weight:700; text-transform:uppercase; padding:4px 10px; border-radius:12px; background:<?= $r['status'] === 'sourced' ? '#dcfce7; color:#15803d;' : '#fef3c7; color:#b45309;' ?>">
                            <?= e(str_replace('_', ' ', $r['status'])) ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($r['details'])): ?>
                    <p style="font-size:0.88rem; color:#475569; margin:8px 0; background:#f8fafc; padding:8px; border-radius:6px;">
                        <strong>Customer details:</strong> <?= e($r['details']) ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($r['admin_notes'])): ?>
                    <p style="font-size:0.85rem; color:#92400e; background:#fef3c7; padding:8px; border-radius:6px; margin:6px 0;">
                        <i class="fa-solid fa-clipboard-check"></i> <strong>Admin Instructions:</strong> <?= e($r['admin_notes']) ?>
                    </p>
                <?php endif; ?>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; padding-top:10px; border-top:1px dashed #e2e8f0; font-size:0.85rem;">
                    <div>
                        <strong>Customer:</strong> <?= e($r['customer_name'] ?: 'Customer') ?> 
                        (Tel: <a href="tel:<?= e($r['customer_phone']) ?>" style="color:#0f7b3a; font-weight:700;"><?= e($r['customer_phone']) ?></a>)
                        <?php if ($r['preferred_price']): ?>
                            | <strong>Budget:</strong> ₹<?= number_format((float)$r['preferred_price'], 2) ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($r['assigned_delivery_id'] == $agentId && $r['status'] !== 'sourced'): ?>
                        <form action="suggestions.php" method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="update_sourcing">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="status" value="sourced">
                            <input type="hidden" name="supplier_info" value="Picked up / Sourced by agent <?= e(delivery_name()) ?>">
                            <button type="submit" class="dp-btn dp-btn-primary dp-btn-sm" onclick="return confirm('Mark this product as successfully sourced?')" style="background:#0f7b3a; color:#fff; border:none; padding:6px 12px; border-radius:6px; font-weight:600; cursor:pointer;">
                                <i class="fa-solid fa-check"></i> Mark Sourced
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
<?php
});

