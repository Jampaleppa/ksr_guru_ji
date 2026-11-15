<?php
// ============================================================
// admin/pricing.php — Product Pricing Management
// KSR GURU JI — Selling Price = Market Price + ₹3.00 (NEVER CHANGE)
// ============================================================
$currentPage = 'pricing';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'inc/auth_check.php';
require_once '../config.php';
require_once 'inc/functions.php';

define('PRICE_MARGIN', 3.00); // ₹3 margin — RULE: NEVER CHANGE

$pdo     = getDBConnection();
$message = '';
$error   = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update_prices'])) {
    $prices  = $_POST['market_price'] ?? [];
    $updated = 0;
    try {
        $stmt = $pdo->prepare("UPDATE products SET market_price = ?, price = ?, updated_at = NOW() WHERE id = ?");
        foreach ($prices as $id => $mp) {
            $mp = (float) $mp;
            if ($mp < 0) continue;
            $sp = $mp + PRICE_MARGIN; // Always market + ₹3
            if ($stmt->execute([$mp, $sp, (int) $id])) {
                $updated++;
            }
        }
        $message = "Updated {$updated} product price(s). Selling price = Market + ₹" . number_format(PRICE_MARGIN, 2) . " (auto-calculated).";
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

$catFilter = (int) ($_GET['cat'] ?? 0);
try {
    if ($catFilter) {
        $products = $pdo->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.category_id = ? ORDER BY c.name, p.name");
        $products->execute([$catFilter]);
        $products = $products->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $products = $pdo->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY c.name, p.name")->fetchAll(PDO::FETCH_ASSOC);
    }
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products   = [];
    $categories = [];
    $error      = 'Could not load products: ' . $e->getMessage();
}

require_once 'inc/header.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0"><i class="fas fa-tags text-primary"></i> Product Pricing</h2>
        <div class="alert alert-info mb-0 py-2 px-3">
            <i class="fas fa-info-circle"></i>
            <strong>Rule:</strong> Selling Price = Market Price + <strong>₹<?= number_format(PRICE_MARGIN, 2) ?></strong> (auto-calculated, never change)
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Category filter -->
    <form method="GET" class="mb-3">
        <div class="d-flex gap-2 align-items-center">
            <select name="cat" class="form-select w-auto" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="text-muted small"><?= count($products) ?> product(s)</span>
        </div>
    </form>

    <form method="POST">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th>Market Price (₹)</th>
                                <th>Selling Price (₹)</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted mb-3">
                                        <i class="fas fa-box-open fa-3x mb-2 d-block opacity-50"></i>
                                        <strong>No products found.</strong><br>
                                        <small>Add products via Product Management, then set market prices here.</small>
                                    </div>
                                    <a href="products.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Go to Product Management
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $i => $p):
                                $mp = (float) ($p['market_price'] ?? $p['price'] ?? 0);
                                $sp = $mp + PRICE_MARGIN;
                            ?>
                            <tr>
                                <td class="text-muted small"><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></span></td>
                                <td><small><?= htmlspecialchars($p['unit'] ?? 'kg') ?></small></td>
                                <td style="width:160px">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₹</span>
                                        <input type="number"
                                               name="market_price[<?= (int) $p['id'] ?>]"
                                               value="<?= number_format($mp, 2, '.', '') ?>"
                                               step="0.50" min="0" max="99999"
                                               class="form-control mp-input"
                                               data-id="<?= (int) $p['id'] ?>">
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-success fs-6 sp-display-<?= (int) $p['id'] ?>">
                                        ₹<?= number_format($sp, 2) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= htmlspecialchars($p['updated_at'] ?? '—') ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (!empty($products)): ?>
        <div class="mt-3 d-flex align-items-center gap-3">
            <button type="submit" name="update_prices" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Save All Prices
            </button>
            <span class="text-muted small">
                All selling prices are automatically set to Market Price + ₹<?= number_format(PRICE_MARGIN, 2) ?>
            </span>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
document.querySelectorAll('.mp-input').forEach(function (inp) {
    inp.addEventListener('input', function () {
        const id  = this.dataset.id;
        const mp  = parseFloat(this.value) || 0;
        const sp  = mp + <?= PRICE_MARGIN ?>;
        const el  = document.querySelector('.sp-display-' + id);
        if (el) el.textContent = '₹' + sp.toFixed(2);
    });
});
</script>

<?php require_once 'inc/footer.php'; ?>
