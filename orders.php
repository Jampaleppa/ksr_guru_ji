<?php
// ============================================================
// orders.php – My Orders (Customer View)
// ============================================================
require_once 'config.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/orders.php'));
    exit;
}

$userId  = (int) $_SESSION['user_id'];
$pdo     = getDBConnection();

// Fetch orders
$orders = [];
try {
    $stmt = $pdo->prepare(
        "SELECT id, order_number, total, payment_method, payment_status, status, created_at
         FROM orders WHERE user_id = ?
         ORDER BY id DESC LIMIT 100"
    );
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $orders = [];
}

// Cart count for header
$cartCount = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders – KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0f7b3a;
            --primary-dark: #0a5e2c;
            --primary-light: #e8f5e9;
            --secondary: #e85d2c;
            --text-dark: #1a1f2e;
            --text-muted: #5a6a7e;
            --bg: #f4f7f6;
            --white: #fff;
            --border: #e2e8f0;
            --radius: 14px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg); color: var(--text-dark); }

        /* ── TOP NAV ── */
        .topbar {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .topbar-logo { font-weight: 800; font-size: 1.2rem; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }
        .topbar-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer;
        }
        .btn-outline { border: 1.5px solid var(--border); color: var(--text-muted); background: var(--white); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        .btn-primary { background: var(--primary); color: var(--white); }
        .btn-primary:hover { background: var(--primary-dark); }

        /* ── MAIN ── */
        .container { max-width: 900px; margin: 0 auto; padding: 32px 16px; }
        .page-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--primary); }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 4rem; color: #d1d5db; margin-bottom: 16px; }
        .empty-state h3 { font-size: 1.3rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; }
        .empty-state p { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 24px; }

        /* ── ORDER CARD ── */
        .order-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 24px;
            margin-bottom: 16px;
            transition: box-shadow 0.2s;
        }
        .order-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
        .order-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .order-id { font-weight: 700; font-size: 0.95rem; color: var(--primary); }
        .order-date { font-size: 0.8rem; color: var(--text-muted); }
        .order-meta { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700;
        }
        .badge-green  { background: #dcfce7; color: #15803d; }
        .badge-yellow { background: #fef9c3; color: #a16207; }
        .badge-red    { background: #fee2e2; color: #dc2626; }
        .badge-blue   { background: #dbeafe; color: #1d4ed8; }
        .badge-gray   { background: #f1f5f9; color: var(--text-muted); }
        .order-total { font-weight: 800; font-size: 1.05rem; color: var(--text-dark); }
        .order-actions { display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); }
        .btn-sm {
            padding: 6px 14px; border-radius: 8px; font-size: 0.8rem; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-sm-primary { background: var(--primary); color: #fff; }
        .btn-sm-primary:hover { background: var(--primary-dark); }
        .btn-sm-outline { border: 1.5px solid var(--border); color: var(--text-muted); background: #fff; }
        .btn-sm-outline:hover { border-color: var(--primary); color: var(--primary); }
    </style>
</head>
<body>

<!-- TOP BAR -->
<nav class="topbar">
    <a href="/" class="topbar-logo"><i class="fas fa-store"></i> KSR GURU JI</a>
    <div class="topbar-actions">
        <a href="cart.php" class="topbar-btn btn-outline"><i class="fas fa-shopping-cart"></i> Cart <?= $cartCount > 0 ? "<span style='color:var(--secondary);'>($cartCount)</span>" : '' ?></a>
        <a href="profile.php" class="topbar-btn btn-outline"><i class="fas fa-user"></i> Profile</a>
        <a href="track-order.php" class="topbar-btn btn-primary"><i class="fas fa-truck"></i> Track</a>
    </div>
</nav>

<div class="container">
    <h1 class="page-title"><i class="fas fa-box-open"></i> My Orders</h1>

    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>No orders yet</h3>
            <p>Looks like you haven't placed any orders. Start shopping to see your orders here.</p>
            <a href="/" class="topbar-btn btn-primary" style="display:inline-flex;">
                <i class="fas fa-shopping-bag"></i>&nbsp; Shop Now
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order):
            $status = strtolower($order['status'] ?? 'pending');
            $pstatus = strtolower($order['payment_status'] ?? 'pending');
            $badgeClass = match($status) {
                'delivered'  => 'badge-green',
                'out_for_delivery', 'out' => 'badge-blue',
                'cancelled', 'failed'     => 'badge-red',
                'pending'    => 'badge-yellow',
                default      => 'badge-gray',
            };
            $pbadgeClass = match($pstatus) {
                'paid', 'completed' => 'badge-green',
                'failed', 'refunded' => 'badge-red',
                'pending'           => 'badge-yellow',
                default             => 'badge-gray',
            };
            $orderNum = $order['order_number'] ?: '#' . $order['id'];
            $orderDate = $order['created_at'] ? date('d M Y, h:i A', strtotime($order['created_at'])) : '—';
        ?>
        <div class="order-card">
            <div class="order-header">
                <span class="order-id"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($orderNum, ENT_QUOTES) ?></span>
                <span class="order-date"><i class="far fa-calendar"></i> <?= $orderDate ?></span>
                <div class="order-meta">
                    <span class="badge <?= $badgeClass ?>"><i class="fas fa-circle-dot"></i> <?= ucfirst(str_replace('_', ' ', $status)) ?></span>
                    <span class="badge <?= $pbadgeClass ?>"><i class="fas fa-credit-card"></i> <?= ucfirst($pstatus) ?></span>
                    <span class="badge badge-gray"><i class="fas fa-wallet"></i> <?= htmlspecialchars(strtoupper($order['payment_method'] ?? 'COD'), ENT_QUOTES) ?></span>
                </div>
                <span class="order-total">₹<?= number_format((float)($order['total'] ?? 0), 2) ?></span>
            </div>
            <div class="order-actions">
                <a href="track-order.php?order_ref=<?= urlencode($orderNum) ?>" class="btn-sm btn-sm-primary"><i class="fas fa-truck"></i> Track</a>
                <a href="delivery/tracking.php?order=<?= urlencode($orderNum) ?>" class="btn-sm btn-sm-outline"><i class="fas fa-eye"></i> Details</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
