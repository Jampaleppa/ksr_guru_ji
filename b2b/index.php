<?php
// ============================================================
// b2b/index.php  –  KSR GURU JI  •  B2B Wholesale Portal
// Features: catalogue browse, bulk-price tiers, quote request,
//           order placement (authoritative DB pricing & stock),
//           order history.
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/inventory.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo      = getDBConnection();
$isMysql  = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite';

// ── Ensure B2B tables exist (SQLite + MySQL compatible) ───────
$autoInc = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$ts      = $isMysql ? 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' : "TEXT NOT NULL DEFAULT (datetime('now'))";
$boolCol = $isMysql ? 'TINYINT(1)' : 'INTEGER';

$pdo->exec("CREATE TABLE IF NOT EXISTS b2b_customers (
    id            $autoInc,
    user_id       INT NOT NULL,
    business_name VARCHAR(150) NOT NULL,
    gst_number    VARCHAR(20) NULL,
    contact_name  VARCHAR(100) NOT NULL,
    phone         VARCHAR(15) NOT NULL,
    email         VARCHAR(150) NULL,
    address       TEXT NOT NULL,
    city          VARCHAR(80) NOT NULL,
    state         VARCHAR(80) NOT NULL DEFAULT 'Karnataka',
    pincode       VARCHAR(10) NOT NULL,
    approved      $boolCol NOT NULL DEFAULT 0,
    created_at    $ts
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS b2b_orders (
    id              $autoInc,
    b2b_customer_id INT NOT NULL,
    user_id         INT NOT NULL,
    order_number    VARCHAR(30) NOT NULL UNIQUE,
    status          VARCHAR(30) NOT NULL DEFAULT 'pending',
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
    delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
    total           DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes           TEXT NULL,
    created_at      $ts
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS b2b_order_items (
    id           $autoInc,
    order_id     INT NOT NULL,
    product_id   INT NULL,
    product_name VARCHAR(200) NOT NULL,
    unit_price   DECIMAL(10,2) NOT NULL,
    qty          INT NOT NULL,
    total        DECIMAL(12,2) NOT NULL
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS b2b_price_tiers (
    id         $autoInc,
    product_id INT NOT NULL,
    min_qty    INT NOT NULL DEFAULT 1,
    price      DECIMAL(10,2) NOT NULL,
    created_at $ts
)");

// ── Auth: must be logged in ───────────────────────────────────
$userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'user';

if (!$userId) {
    header('Location: /login.php?redirect=' . urlencode('/b2b/'));
    exit;
}

// Find existing B2B profile
$b2bCustomer = null;
try {
    $st = $pdo->prepare("SELECT * FROM b2b_customers WHERE user_id = ? LIMIT 1");
    $st->execute([$userId]);
    $b2bCustomer = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $ignore) {
}

// ── CSRF ─────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$message = $_GET['msg'] ?? '';
$error   = $_GET['err'] ?? '';

// ── POST handlers ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Session expired. Please reload and try again.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'register_b2b') {
        $bizName = trim((string)($_POST['business_name'] ?? ''));
        $gst     = strtoupper(trim((string)($_POST['gst_number'] ?? '')));
        $contact = trim((string)($_POST['contact_name'] ?? ''));
        $phone   = preg_replace('/[^0-9]/', '', (string)($_POST['phone'] ?? ''));
        $email   = trim((string)($_POST['email'] ?? ''));
        $addr    = trim((string)($_POST['address'] ?? ''));
        $city    = trim((string)($_POST['city'] ?? ''));
        $state   = trim((string)($_POST['state'] ?? 'Karnataka'));
        $pincode = trim((string)($_POST['pincode'] ?? ''));

        if ($bizName === '' || strlen($phone) < 10 || $addr === '' || $city === '' || !preg_match('/^\d{5,6}$/', $pincode)) {
            $error = 'Please fill all required fields cleanly (business name, 10-digit phone, address, city, 6-digit pincode).';
        } elseif ($b2bCustomer) {
            $error = 'Your B2B application has already been submitted.';
        } else {
            $ins = $pdo->prepare("INSERT INTO b2b_customers
                (user_id, business_name, gst_number, contact_name, phone, email, address, city, state, pincode)
                VALUES (?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([$userId, $bizName, $gst, $contact, $phone, $email, $addr, $city, $state, $pincode]);
            $b2bCustomer = [
                'id'            => (int)$pdo->lastInsertId(),
                'user_id'       => $userId,
                'business_name' => $bizName,
                'approved'      => 0,
            ];
            $message = '✅ B2B registration submitted. Our team will review and approve within 24 hours.';
        }
    }

    if ($action === 'place_order' && $b2bCustomer) {
        if ((int)($b2bCustomer['approved'] ?? 0) !== 1) {
            $error = 'Your B2B account is pending approval. Please wait for admin confirmation.';
        } else {
            $items    = json_decode((string)($_POST['items'] ?? '[]'), true);
            $notes    = trim((string)($_POST['notes'] ?? ''));
            $subtotal = 0.0;
            $orderNum = 'B2B-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            if (!is_array($items) || empty($items)) {
                $error = 'Please add at least one product to your order.';
            } else {
                $pdo->beginTransaction();
                try {
                    $ordIns = $pdo->prepare("INSERT INTO b2b_orders
                        (b2b_customer_id, user_id, order_number, status, subtotal, delivery_charge, total, notes)
                        VALUES (?,?,?,'pending',0,0,0,?)");
                    $ordIns->execute([(int)$b2bCustomer['id'], $userId, $orderNum, $notes]);
                    $ordId = (int)$pdo->lastInsertId();

                    $resolvedCount = 0;
                    foreach ($items as $it) {
                        $pid = (int)($it['product_id'] ?? 0);
                        $qty = (int)($it['qty'] ?? 0);
                        if ($pid <= 0 || $qty <= 0) {
                            continue;
                        }
                        if ($qty > 5000) {
                            throw new RuntimeException('Maximum single-line B2B quantity is 5,000 units.');
                        }

                        // Always read authoritative price & stock from DB (never trust client price)
                        $lockSql = $isMysql
                            ? "SELECT id, name, price, discount, stock, status FROM products WHERE id = ? AND status = 1 FOR UPDATE"
                            : "SELECT id, name, price, discount, stock, status FROM products WHERE id = ? AND status = 1";
                        $pStmt = $pdo->prepare($lockSql);
                        $pStmt->execute([$pid]);
                        $prodRow = $pStmt->fetch(PDO::FETCH_ASSOC);

                        if (!$prodRow) {
                            throw new RuntimeException("Product #{$pid} is unavailable.");
                        }
                        $oldStock = (int)$prodRow['stock'];
                        if ($oldStock < $qty) {
                            throw new RuntimeException("{$prodRow['name']}: only {$oldStock} left in stock.");
                        }

                        $unitPrice = (float)$prodRow['price'];
                        $disc      = (float)($prodRow['discount'] ?? 0);
                        if ($disc > 0 && $disc < $unitPrice) {
                            $unitPrice -= $disc;
                        }

                        // Override with qualifying B2B wholesale tier price if configured
                        $tierSt = $pdo->prepare(
                            "SELECT price FROM b2b_price_tiers WHERE product_id = ? AND min_qty <= ? ORDER BY min_qty DESC LIMIT 1"
                        );
                        $tierSt->execute([$pid, $qty]);
                        $tierPrice = $tierSt->fetchColumn();
                        if ($tierPrice !== false && (float)$tierPrice > 0) {
                            $unitPrice = (float)$tierPrice;
                        }

                        $lineTotal  = round($unitPrice * $qty, 2);
                        $subtotal  += $lineTotal;
                        $resolvedCount++;

                        $pdo->prepare("INSERT INTO b2b_order_items
                            (order_id, product_id, product_name, unit_price, qty, total)
                            VALUES (?,?,?,?,?,?)")
                            ->execute([$ordId, $pid, $prodRow['name'], $unitPrice, $qty, $lineTotal]);

                        $newStock = max(0, $oldStock - $qty);
                        if ($isMysql) {
                            $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?")
                                ->execute([$qty, $pid]);
                        } else {
                            $pdo->prepare("UPDATE products SET stock = MAX(0, stock - ?) WHERE id = ?")
                                ->execute([$qty, $pid]);
                        }
                        ksrRecordStockHistory($pdo, $pid, $oldStock, $newStock, 'b2b_order', "B2B {$orderNum}", $userId);
                    }

                    if ($resolvedCount === 0) {
                        throw new RuntimeException('No valid products were found in your B2B order.');
                    }

                    $subtotal = round($subtotal, 2);
                    $pdo->prepare("UPDATE b2b_orders SET subtotal=?, total=? WHERE id=?")
                        ->execute([$subtotal, $subtotal, $ordId]);
                    $pdo->commit();
                    header('Location: /b2b/?msg=' . urlencode("✅ Order {$orderNum} placed! Our team will confirm shortly."));
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Order failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// ── Load wholesale catalogue (products table) ─────────────────
$catalogue = [];
try {
    $catalogue = $pdo->query("SELECT p.id, p.name, p.price, p.stock, p.unit,
        (SELECT MIN(pt.price) FROM b2b_price_tiers pt WHERE pt.product_id = p.id) AS b2b_price,
        (SELECT MIN(pt.min_qty) FROM b2b_price_tiers pt WHERE pt.product_id = p.id) AS b2b_min_qty
        FROM products p WHERE p.status = 1 ORDER BY p.name ASC LIMIT 100")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ignore) {
}

// ── Load B2B order history ────────────────────────────────────
$orders = [];
if ($b2bCustomer) {
    try {
        $ost = $pdo->prepare("SELECT * FROM b2b_orders WHERE b2b_customer_id = ? ORDER BY id DESC LIMIT 20");
        $ost->execute([(int)$b2bCustomer['id']]);
        $orders = $ost->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ignore) {
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>B2B Wholesale – KSR GURU JI</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#1e3a5f">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  :root { --blue:#1e3a5f; --blue-l:#e8eef6; --green:#0f7b3a; --red:#e53e3e; --amber:#f59e0b; --radius:14px; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Segoe UI',sans-serif; background:#f4f6f9; color:#222; }
  header { background:var(--blue); color:#fff; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
  header h1 { font-size:1.05rem; }
  .container { max-width:860px; margin:20px auto; padding:0 16px 100px; }
  .card { background:#fff; border-radius:var(--radius); padding:20px; box-shadow:0 2px 10px rgba(0,0,0,.07); margin-bottom:16px; }
  .alert { padding:11px 15px; border-radius:9px; margin-bottom:14px; font-size:.9rem; }
  .alert.ok  { background:#e8f5ee; color:var(--green); border:1px solid #c3e6cb; }
  .alert.err { background:#fff0f0; color:var(--red);   border:1px solid #ffcdd2; }
  .alert.warn{ background:#fff9e6; color:#92400e;      border:1px solid #fcd34d; }
  label { display:block; font-size:.85rem; font-weight:600; margin-bottom:4px; color:#444; }
  input, select, textarea { width:100%; padding:10px 13px; border:1.5px solid #ddd; border-radius:9px; font-size:.93rem; margin-bottom:12px; outline:none; }
  input:focus, select:focus { border-color:var(--blue); }
  .btn { padding:12px 20px; border:none; border-radius:var(--radius); font-weight:700; cursor:pointer; }
  .btn-primary { background:var(--blue); color:#fff; width:100%; }
  .btn-green   { background:var(--green); color:#fff; }
  .section-title { font-size:1.05rem; font-weight:700; margin:20px 0 12px; color:#1e3a5f; border-left:4px solid var(--blue); padding-left:10px; }
  .prod-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:14px; }
  .prod-card { background:#fff; border-radius:12px; padding:14px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
  .prod-name { font-weight:700; font-size:.95rem; margin-bottom:4px; }
  .prod-price { color:var(--blue); font-weight:800; font-size:1.1rem; }
  .prod-b2b   { color:var(--green); font-size:.82rem; font-weight:600; }
  .prod-stock { color:#888; font-size:.78rem; }
  .qty-input  { width:70px; padding:6px 10px; border:1.5px solid #ddd; border-radius:7px; font-size:.9rem; text-align:center; margin-bottom:0; }
  .add-btn { background:var(--blue); color:#fff; border:none; border-radius:7px; padding:7px 14px; font-size:.85rem; font-weight:700; cursor:pointer; margin-left:6px; }
  table { width:100%; border-collapse:collapse; }
  th, td { padding:9px 12px; text-align:left; border-bottom:1px solid #eee; font-size:.88rem; }
  th { background:#f4f6f9; font-weight:700; color:#333; }
  .status-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:.75rem; font-weight:700; }
  .status-pending    { background:#fef3c7; color:#92400e; }
  .status-confirmed  { background:#dbeafe; color:#1e40af; }
  .status-dispatched { background:#ede9fe; color:#5b21b6; }
  .status-delivered  { background:#d1fae5; color:#065f46; }
  .status-cancelled  { background:#fee2e2; color:#991b1b; }
  .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
</style>
</head>
<body>
<header>
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="/index.php" style="color:#fff;font-size:1.2rem;"><i class="fas fa-arrow-left"></i></a>
    <h1>🏢 B2B Wholesale – KSR GURU JI</h1>
  </div>
  <?php if ($b2bCustomer): ?>
    <span style="font-size:.82rem;background:rgba(255,255,255,.15);padding:4px 10px;border-radius:16px;"><?= htmlspecialchars($b2bCustomer['business_name']) ?></span>
  <?php endif; ?>
</header>
<div class="container">

  <?php if ($message): ?><div class="alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if (!$b2bCustomer): ?>
  <div class="card">
    <p class="section-title"><i class="fas fa-building"></i> Register as a Wholesale Buyer</p>
    <p style="color:#555;margin-bottom:16px;font-size:.9rem;">Get access to bulk pricing tiers, wholesale catalogue and GST business invoices.</p>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="register_b2b">
      <label>Business Name *</label>
      <input name="business_name" required placeholder="e.g. ABC Supermarket">
      <div class="form-row">
        <div><label>Contact Person *</label><input name="contact_name" required placeholder="Your name"></div>
        <div><label>Phone *</label><input name="phone" required type="tel" placeholder="10-digit mobile"></div>
      </div>
      <div class="form-row">
        <div><label>Email</label><input name="email" type="email" placeholder="business@email.com"></div>
        <div><label>GST Number</label><input name="gst_number" placeholder="29AAAAA0000A1Z5"></div>
      </div>
      <label>Business Address *</label>
      <textarea name="address" required placeholder="Shop / Building / Street"></textarea>
      <div class="form-row">
        <div><label>City *</label><input name="city" required placeholder="e.g. Gajendragad"></div>
        <div><label>Pincode *</label><input name="pincode" required placeholder="6-digit pincode" maxlength="6"></div>
      </div>
      <label>State</label>
      <input name="state" placeholder="Karnataka" value="Karnataka">
      <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Registration</button>
    </form>
  </div>

  <?php elseif ((int)($b2bCustomer['approved'] ?? 0) !== 1): ?>
  <div class="alert warn">
    ⏳ <strong>Approval Pending:</strong> Your B2B account for <strong><?= htmlspecialchars($b2bCustomer['business_name']) ?></strong> is under review. You'll receive confirmation within 24 hours.
  </div>

  <?php else: ?>
  <p class="section-title"><i class="fas fa-boxes"></i> Wholesale Catalogue</p>
  <p style="color:#555;font-size:.88rem;margin-bottom:14px;">Select products and quantities. Qualifying B2B bulk pricing applies automatically server-side.</p>
  <div class="prod-grid" id="catalogue">
    <?php foreach ($catalogue as $p):
        $minQ = max(1, (int)($p['b2b_min_qty'] ?? 10));
    ?>
    <div class="prod-card">
      <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
      <div class="prod-price">₹<?= number_format((float)$p['price'], 2) ?> <span style="font-size:.8rem;font-weight:400;color:#888">/ <?= htmlspecialchars($p['unit'] ?: 'unit') ?></span></div>
      <?php if ($p['b2b_price']): ?>
      <div class="prod-b2b">🏷 B2B from ₹<?= number_format((float)$p['b2b_price'], 2) ?> (MOQ <?= $minQ ?>)</div>
      <?php endif; ?>
      <div class="prod-stock">Stock: <?= (int)$p['stock'] ?></div>
      <div style="display:flex;align-items:center;margin-top:10px;">
        <input class="qty-input" type="number" min="1" max="<?= max(1, (int)$p['stock']) ?>" value="<?= min(max(1, (int)$p['stock']), $minQ) ?>" id="qty_<?= (int)$p['id'] ?>">
        <button class="add-btn" onclick="addToCart(<?= (int)$p['id'] ?>, <?= htmlspecialchars(json_encode($p['name'])) ?>, <?= (float)($p['b2b_price'] ?? $p['price']) ?>, document.getElementById('qty_<?= (int)$p['id'] ?>').value)">
          + Add
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card" style="margin-top:20px;">
    <p class="section-title"><i class="fas fa-shopping-cart"></i> Your Order</p>
    <div id="cart-items"><p style="color:#888;font-size:.9rem;">No items added yet. Click "+ Add" on products above.</p></div>
    <div id="cart-total" style="font-weight:700;font-size:1.05rem;margin:12px 0;display:none;">Estimated Total: <span id="totalAmt">₹0</span></div>
    <form method="POST" id="b2b-order-form" style="display:none;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="place_order">
      <input type="hidden" name="items" id="items-json">
      <label>Notes / Special Instructions</label>
      <textarea name="notes" placeholder="Delivery instructions, specific packaging, etc."></textarea>
      <button type="submit" class="btn btn-green" style="width:100%;"><i class="fas fa-check"></i> Place Wholesale Order</button>
    </form>
  </div>

  <?php if (!empty($orders)): ?>
  <p class="section-title"><i class="fas fa-history"></i> Order History</p>
  <div class="card" style="overflow-x:auto;">
    <table>
      <thead><tr><th>Order #</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $ord): ?>
        <tr>
          <td><?= htmlspecialchars($ord['order_number']) ?></td>
          <td><?= date('d M Y', strtotime($ord['created_at'])) ?></td>
          <td>₹<?= number_format((float)$ord['total'], 2) ?></td>
          <td><span class="status-badge status-<?= htmlspecialchars($ord['status']) ?>"><?= ucfirst(htmlspecialchars($ord['status'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>

<script>
const cart = {};

function addToCart(id, name, price, qty) {
  qty = Math.max(1, parseInt(qty, 10) || 1);
  if (cart[id]) { cart[id].qty += qty; } else { cart[id] = { id, name, price: parseFloat(price), qty }; }
  renderCart();
}

function removeFromCart(id) { delete cart[id]; renderCart(); }

function esc(s) {
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function renderCart() {
  const items = Object.values(cart);
  const container = document.getElementById('cart-items');
  const form      = document.getElementById('b2b-order-form');
  const totalDiv  = document.getElementById('cart-total');
  if (!items.length) {
    container.innerHTML = '<p style="color:#888;font-size:.9rem;">No items added yet.</p>';
    form.style.display = 'none'; totalDiv.style.display = 'none'; return;
  }
  let total = 0, html = '<table><thead><tr><th>Product</th><th>Qty</th><th>Est. Rate</th><th>Total</th><th></th></tr></thead><tbody>';
  items.forEach(it => {
    const sub = it.price * it.qty; total += sub;
    html += `<tr><td>${esc(it.name)}</td><td>${it.qty}</td><td>₹${it.price.toFixed(2)}</td><td>₹${sub.toFixed(2)}</td><td><button type="button" onclick="removeFromCart(${it.id})" style="background:none;border:none;color:#e53e3e;cursor:pointer;font-size:1rem;">✕</button></td></tr>`;
  });
  html += '</tbody></table>';
  container.innerHTML = html;
  document.getElementById('totalAmt').textContent = '₹' + total.toFixed(2);
  document.getElementById('items-json').value = JSON.stringify(items.map(it => ({ product_id: it.id, qty: it.qty })));
  form.style.display = 'block'; totalDiv.style.display = 'block';
}
</script>
</body>
</html>
