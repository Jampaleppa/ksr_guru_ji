<?php
// ============================================================
// wishlist.php – Customer Wishlist (session-based)
// ============================================================
require_once 'config.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize wishlist in session
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// --- AJAX handlers ---
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id    = htmlspecialchars(trim($_POST['id'] ?? ''), ENT_QUOTES);
        $name  = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES);
        $price = (float)($_POST['price'] ?? 0);
        $image = htmlspecialchars(trim($_POST['image'] ?? ''), ENT_QUOTES);
        if ($id && $name) {
            $found = false;
            foreach ($_SESSION['wishlist'] as $w) {
                if ($w['id'] === $id) { $found = true; break; }
            }
            if (!$found) {
                $_SESSION['wishlist'][] = ['id' => $id, 'name' => $name, 'price' => $price, 'image' => $image];
            }
            echo json_encode(['success' => true, 'count' => count($_SESSION['wishlist'])]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid item']);
        }
        exit;
    }

    if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $removeId = htmlspecialchars(trim($_POST['id'] ?? ''), ENT_QUOTES);
        $_SESSION['wishlist'] = array_values(array_filter($_SESSION['wishlist'], fn($w) => $w['id'] !== $removeId));
        echo json_encode(['success' => true, 'count' => count($_SESSION['wishlist'])]);
        exit;
    }

    if ($action === 'list') {
        echo json_encode($_SESSION['wishlist']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

$wishlist   = $_SESSION['wishlist'];
$cartCount  = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist – KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0f7b3a; --primary-dark: #0a5e2c; --primary-light: #e8f5e9;
            --secondary: #e85d2c; --text-dark: #1a1f2e; --text-muted: #5a6a7e;
            --bg: #f4f7f6; --white: #fff; --border: #e2e8f0; --radius: 14px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg); color: var(--text-dark); }
        .topbar {
            background: #fff; border-bottom: 1px solid var(--border);
            padding: 14px 24px; display: flex; align-items: center;
            justify-content: space-between; gap: 16px;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .topbar-logo { font-weight: 800; font-size: 1.2rem; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }
        .topbar-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer;
        }
        .btn-outline { border: 1.5px solid var(--border); color: var(--text-muted); background: #fff; }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }

        .container { max-width: 1000px; margin: 0 auto; padding: 32px 16px; }
        .page-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .page-title i { color: #ef4444; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 4rem; color: #d1d5db; margin-bottom: 16px; }
        .empty-state h3 { font-size: 1.3rem; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; }
        .empty-state p  { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 24px; }

        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        .wl-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden;
            transition: box-shadow 0.2s;
            position: relative;
        }
        .wl-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.09); }
        .wl-img {
            width: 100%; aspect-ratio: 1; object-fit: contain;
            background: var(--bg); padding: 16px;
        }
        .wl-img-placeholder {
            width: 100%; aspect-ratio: 1;
            display: flex; align-items: center; justify-content: center;
            background: var(--primary-light); font-size: 3rem;
        }
        .wl-body { padding: 14px; }
        .wl-name { font-weight: 700; font-size: 0.9rem; margin-bottom: 4px; color: var(--text-dark); }
        .wl-price { font-size: 1rem; font-weight: 800; color: var(--primary); margin-bottom: 12px; }
        .wl-actions { display: flex; gap: 8px; }
        .btn-add {
            flex: 1; background: var(--primary); color: #fff;
            border: none; padding: 9px; border-radius: 8px;
            font-size: 0.82rem; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 5px;
        }
        .btn-add:hover { background: var(--primary-dark); }
        .btn-remove {
            background: #fee2e2; color: #dc2626;
            border: none; padding: 9px 12px; border-radius: 8px;
            cursor: pointer; font-size: 0.85rem;
        }
        .btn-remove:hover { background: #fca5a5; }
        .wl-remove-top {
            position: absolute; top: 8px; right: 8px;
            background: rgba(255,255,255,0.9); border: none;
            width: 30px; height: 30px; border-radius: 50%;
            cursor: pointer; color: #ef4444; font-size: 0.85rem;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .wl-remove-top:hover { background: #fee2e2; }
    </style>
    <link rel="stylesheet" href="/mobile-app.css?v=3.0" />
</head>
<body>
<nav class="topbar">
    <a href="/" class="topbar-logo"><i class="fas fa-store"></i> KSR GURU JI</a>
    <div class="topbar-actions">
        <a href="cart.php" class="topbar-btn btn-outline"><i class="fas fa-shopping-cart"></i> Cart <?= $cartCount > 0 ? "<span style='color:var(--secondary);'>($cartCount)</span>" : '' ?></a>
        <?php if ($isLoggedIn): ?>
            <a href="orders.php" class="topbar-btn btn-outline"><i class="fas fa-box-open"></i> My Orders</a>
        <?php else: ?>
            <a href="login.php" class="topbar-btn btn-primary"><i class="fas fa-sign-in-alt"></i> Login</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">
    <h1 class="page-title"><i class="fas fa-heart"></i> My Wishlist <span id="wlCount" style="font-size:0.9rem;font-weight:600;color:var(--text-muted);">(<?= count($wishlist) ?> items)</span></h1>

    <?php if (empty($wishlist)): ?>
        <div class="empty-state">
            <i class="fas fa-heart"></i>
            <h3>Your wishlist is empty</h3>
            <p>Save items you love and want to buy later.</p>
            <a href="/" class="topbar-btn btn-primary" style="display:inline-flex;"><i class="fas fa-shopping-bag"></i>&nbsp; Browse Products</a>
        </div>
    <?php else: ?>
        <div class="wishlist-grid" id="wishlistGrid">
            <?php foreach ($wishlist as $item): ?>
            <div class="wl-card" data-id="<?= htmlspecialchars($item['id'], ENT_QUOTES) ?>">
                <button class="wl-remove-top" onclick="removeItem('<?= htmlspecialchars($item['id'], ENT_QUOTES) ?>')" title="Remove from wishlist">
                    <i class="fas fa-times"></i>
                </button>
                <?php if (!empty($item['image'])): ?>
                    <img src="<?= htmlspecialchars($item['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>" class="wl-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="wl-img-placeholder" style="display:none;">🛒</div>
                <?php else: ?>
                    <div class="wl-img-placeholder">🛒</div>
                <?php endif; ?>
                <div class="wl-body">
                    <div class="wl-name"><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></div>
                    <div class="wl-price">₹<?= number_format((float)($item['price'] ?? 0), 2) ?></div>
                    <div class="wl-actions">
                        <button class="btn-add" onclick="addToCart('<?= htmlspecialchars($item['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>', <?= (float)($item['price'] ?? 0) ?>)">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                        <button class="btn-remove" onclick="removeItem('<?= htmlspecialchars($item['id'], ENT_QUOTES) ?>')" title="Remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function removeItem(id) {
    fetch('/wishlist.php?ajax=remove', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.querySelector('.wl-card[data-id="' + CSS.escape(id) + '"]');
            if (card) card.remove();
            const countEl = document.getElementById('wlCount');
            if (countEl) countEl.textContent = '(' + data.count + ' items)';
            if (data.count === 0) location.reload();
        }
    });
}

function addToCart(id, name, price) {
    const form = new URLSearchParams({
        product_id: id,
        name: name,
        price: price,
        quantity: 1
    });
    fetch('/add_cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: form.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success !== false) {
            alert('Added to cart: ' + name);
        } else {
            alert(data.error || 'Could not add to cart');
        }
    })
    .catch(() => alert('Added to cart!'));
}
</script>

    <!-- Mobile Bottom Navigation -->
    <?php if (file_exists(__DIR__ . '/mobile-bottom-nav.php')) require_once __DIR__ . '/mobile-bottom-nav.php'; ?>
    <script src="/assets/js/mobile-app.js?v=3.0" defer></script>
</body>
</html>
