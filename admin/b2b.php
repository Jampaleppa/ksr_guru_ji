<?php
// ============================================================
// admin/b2b.php - B2B Wholesale Account, Pricing Tier & Order Admin
// ============================================================
$currentPage = 'b2b';
require_once __DIR__ . '/inc/auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/inc/functions.php';

$pdo      = getDBConnection();
$isMysql  = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite';
$autoInc  = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$ts       = $isMysql ? 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' : "TEXT NOT NULL DEFAULT (datetime('now'))";
$boolCol  = $isMysql ? 'TINYINT(1)' : 'INTEGER';

// Ensure B2B schema exists if admin visits before a customer registers
$pdo->exec("CREATE TABLE IF NOT EXISTS b2b_customers (
    id $autoInc, user_id INT NOT NULL, business_name VARCHAR(150) NOT NULL, gst_number VARCHAR(20) NULL,
    contact_name VARCHAR(100) NOT NULL, phone VARCHAR(15) NOT NULL, email VARCHAR(150) NULL,
    address TEXT NOT NULL, city VARCHAR(80) NOT NULL, state VARCHAR(80) NOT NULL DEFAULT 'Karnataka',
    pincode VARCHAR(10) NOT NULL, approved $boolCol NOT NULL DEFAULT 0, created_at $ts
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS b2b_orders (
    id $autoInc, b2b_customer_id INT NOT NULL, user_id INT NOT NULL, order_number VARCHAR(30) NOT NULL UNIQUE,
    status VARCHAR(30) NOT NULL DEFAULT 'pending', subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0, total DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL, created_at $ts
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS b2b_price_tiers (
    id $autoInc, product_id INT NOT NULL, min_qty INT NOT NULL DEFAULT 1, price DECIMAL(10,2) NOT NULL, created_at $ts
)");

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['admin_csrf_token'];

$message = $_GET['msg'] ?? '';
$error   = $_GET['err'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Session expired. Please reload.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_approve') {
        $cid      = (int)($_POST['customer_id'] ?? 0);
        $approved = (int)($_POST['approved'] ?? 0) === 1 ? 1 : 0;
        $pdo->prepare("UPDATE b2b_customers SET approved = ? WHERE id = ?")->execute([$approved, $cid]);
        $message = $approved ? 'B2B buyer approved.' : 'B2B buyer approval revoked.';
    } elseif ($action === 'add_tier') {
        $pid   = (int)($_POST['product_id'] ?? 0);
        $minQ  = max(1, (int)($_POST['min_qty'] ?? 1));
        $price = (float)($_POST['price'] ?? 0);
        if ($pid > 0 && $price > 0) {
            $pdo->prepare("INSERT INTO b2b_price_tiers (product_id, min_qty, price) VALUES (?, ?, ?)")
                ->execute([$pid, $minQ, $price]);
            $message = 'Wholesale price tier added.';
        } else {
            $error = 'Select a valid product and positive wholesale price.';
        }
    } elseif ($action === 'delete_tier') {
        $tid = (int)($_POST['tier_id'] ?? 0);
        $pdo->prepare("DELETE FROM b2b_price_tiers WHERE id = ?")->execute([$tid]);
        $message = 'Wholesale tier removed.';
    } elseif ($action === 'update_b2b_order') {
        $oid    = (int)($_POST['order_id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'pending');
        if (in_array($status, ['pending', 'confirmed', 'dispatched', 'delivered', 'cancelled'], true)) {
            $pdo->prepare("UPDATE b2b_orders SET status = ? WHERE id = ?")->execute([$status, $oid]);
            $message = "B2B order #{$oid} updated to " . strtoupper($status) . '.';
        }
    }
    header('Location: b2b.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

$b2bCustomers = $pdo->query("SELECT * FROM b2b_customers ORDER BY approved ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$b2bOrders    = $pdo->query("SELECT o.*, c.business_name, c.phone FROM b2b_orders o LEFT JOIN b2b_customers c ON o.b2b_customer_id = c.id ORDER BY o.id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$b2bTiers     = $pdo->query("SELECT t.*, p.name AS product_name, p.price AS retail_price FROM b2b_price_tiers t LEFT JOIN products p ON t.product_id = p.id ORDER BY t.product_id ASC, t.min_qty ASC")->fetchAll(PDO::FETCH_ASSOC);
$productsList = $pdo->query("SELECT id, name, price FROM products WHERE status = 1 ORDER BY name ASC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'B2B Wholesale';
require_once __DIR__ . '/inc/header.php';
?>
    <style>
        .card { background:#fff; border-radius:12px; padding:20px; border:1px solid #e2e8f0; margin-bottom:20px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px 12px; border-bottom:1px solid #f1f5f9; text-align:left; font-size:0.9rem; }
        th { background:#f8fafc; font-weight:700; color:#475569; }
        .badge { padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:700; }
        .btn-sm { padding:6px 12px; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:0.8rem; }
    </style>
        <div class="page-header" style="margin-bottom:20px;">
            <h1><i class="fas fa-building"></i> B2B Wholesale Management</h1>
            <p class="subtitle" style="color:#64748b;">Approve business buyers, configure wholesale quantity price tiers, and process bulk orders.</p>
        </div>
        <?php if ($message): ?><div style="padding:12px;background:#dcfce7;color:#15803d;border-radius:8px;margin-bottom:16px;"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div style="padding:12px;background:#fee2e2;color:#b91c1c;border-radius:8px;margin-bottom:16px;"><?= e($error) ?></div><?php endif; ?>

        <div class="card">
            <h3 style="margin-bottom:12px;">Wholesale Buyers (<?= count($b2bCustomers) ?>)</h3>
            <div style="overflow-x:auto;">
            <table>
                <thead><tr><th>Business</th><th>GST</th><th>Contact</th><th>City</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (empty($b2bCustomers)): ?><tr><td colspan="6" style="color:#94a3b8;text-align:center;">No B2B registrations yet.</td></tr><?php endif; ?>
                    <?php foreach ($b2bCustomers as $c): $ok = (int)$c['approved'] === 1; ?>
                    <tr>
                        <td><strong><?= e($c['business_name']) ?></strong></td>
                        <td><?= e($c['gst_number'] ?: '—') ?></td>
                        <td><?= e($c['contact_name']) ?> (<?= e($c['phone']) ?>)</td>
                        <td><?= e($c['city']) ?> - <?= e($c['pincode']) ?></td>
                        <td><span class="badge" style="background:<?= $ok ? '#dcfce7;color:#15803d' : '#fef3c7;color:#92400e' ?>;"><?= $ok ? 'Approved' : 'Pending' ?></span></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="toggle_approve">
                                <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                                <input type="hidden" name="approved" value="<?= $ok ? 0 : 1 ?>">
                                <button type="submit" class="btn-sm" style="background:<?= $ok ? '#ef4444' : '#0f7b3a' ?>;color:#fff;"><?= $ok ? 'Revoke' : 'Approve' ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom:12px;">Wholesale Price Tiers</h3>
            <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="add_tier">
                <select name="product_id" required style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;min-width:220px;">
                    <option value="">Select product...</option>
                    <?php foreach ($productsList as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (Retail ₹<?= number_format((float)$p['price'], 2) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="min_qty" min="1" value="10" placeholder="Min Qty" required style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;width:110px;">
                <input type="number" step="0.01" name="price" min="0.01" placeholder="B2B Unit Price ₹" required style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;width:150px;">
                <button type="submit" class="btn-sm" style="background:#1e3a5f;color:#fff;padding:8px 16px;">+ Add Tier</button>
            </form>
            <table>
                <thead><tr><th>Product</th><th>Retail Price</th><th>Min Qty</th><th>B2B Price</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (empty($b2bTiers)): ?><tr><td colspan="5" style="color:#94a3b8;text-align:center;">No wholesale tiers configured.</td></tr><?php endif; ?>
                    <?php foreach ($b2bTiers as $t): ?>
                    <tr>
                        <td><?= e($t['product_name'] ?: ('Product #' . $t['product_id'])) ?></td>
                        <td>₹<?= number_format((float)($t['retail_price'] ?? 0), 2) ?></td>
                        <td><?= (int)$t['min_qty'] ?>+</td>
                        <td><strong>₹<?= number_format((float)$t['price'], 2) ?></strong></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete_tier">
                                <input type="hidden" name="tier_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn-sm" style="background:#fee2e2;color:#b91c1c;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3 style="margin-bottom:12px;">B2B Orders (<?= count($b2bOrders) ?>)</h3>
            <table>
                <thead><tr><th>Order #</th><th>Business</th><th>Total</th><th>Status</th><th>Update</th></tr></thead>
                <tbody>
                    <?php if (empty($b2bOrders)): ?><tr><td colspan="5" style="color:#94a3b8;text-align:center;">No B2B orders placed yet.</td></tr><?php endif; ?>
                    <?php foreach ($b2bOrders as $o): ?>
                    <tr>
                        <td><strong><?= e($o['order_number']) ?></strong></td>
                        <td><?= e($o['business_name'] ?: 'Buyer') ?> (<?= e($o['phone'] ?: '') ?>)</td>
                        <td>₹<?= number_format((float)$o['total'], 2) ?></td>
                        <td><span class="badge" style="background:#e2e8f0;color:#1e293b;"><?= e(strtoupper($o['status'])) ?></span></td>
                        <td>
                            <form method="POST" style="display:flex;gap:6px;align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="update_b2b_order">
                                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                <select name="status" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:6px;">
                                    <?php foreach (['pending','confirmed','dispatched','delivered','cancelled'] as $stOpt): ?>
                                        <option value="<?= $stOpt ?>" <?= $o['status'] === $stOpt ? 'selected' : '' ?>><?= ucfirst($stOpt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-sm" style="background:#0f7b3a;color:#fff;">Save</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
