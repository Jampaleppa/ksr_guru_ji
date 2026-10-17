<?php
// ============================================================
// cart.php - Shopping Cart Page (PHP + MySQL dynamic)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Start session for cart
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get cart items and totals
$cartItems = $_SESSION['cart'];
$cartCount = array_sum(array_column($cartItems, 'qty'));
$cartTotal = array_sum(array_map(fn($item) => $item['price'] * $item['qty'], $cartItems));

// User info
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';

// Handle POST actions (form submissions for fallback when JS is off)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_qty' && isset($_POST['id'], $_POST['qty'])) {
        $id = trim($_POST['id']);
        $qty = (int)$_POST['qty'];
        if ($qty <= 0) {
            $_SESSION['cart'] = array_filter($_SESSION['cart'], fn($item) => (string)$item['id'] !== $id);
        } else {
            foreach ($_SESSION['cart'] as &$item) {
                if ((string)$item['id'] === $id) {
                    $item['qty'] = $qty;
                    break;
                }
            }
        }
        // Redirect to avoid form resubmission
        header('Location: cart.php');
        exit;
    }

    if ($action === 'remove' && isset($_POST['id'])) {
        $id = trim($_POST['id']);
        $_SESSION['cart'] = array_filter($_SESSION['cart'], fn($item) => (string)$item['id'] !== $id);
        header('Location: cart.php');
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        header('Location: cart.php');
        exit;
    }
}

// Recalculate after potential POST
$cartItems = $_SESSION['cart'];
$cartCount = array_sum(array_column($cartItems, 'qty'));
$cartTotal = array_sum(array_map(fn($item) => $item['price'] * $item['qty'], $cartItems));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes" />
    <title>Your Cart · KSR GURU JI</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- Google Fonts: Inter + Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../index.css?v=1.4" />
    <link rel="stylesheet" href="../cart-checkout.css?v=1.0" />
</head>

<body>

    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo-container" onclick="window.location.href='../index.php'" style="cursor: pointer; display: flex; align-items: center; gap: 12px;">
                    <div class="logo-image" style="width: 42px; height: 42px; background: linear-gradient(135deg, #0f7b3a, #1ea85a); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(15, 123, 58, 0.15); flex-shrink: 0; border: 1.5px solid rgba(255, 255, 255, 0.8);">
                        <img src="../company_logo.jpg" alt="KSR GURU JI Logo" style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;" onerror="this.style.display='none';this.parentElement.innerHTML='🛒';" />
                    </div>
                    <div class="logo-text">
                        <span class="logo-brand" style="font-family: 'Poppins', sans-serif; font-size: 1.25rem; font-weight: 800; background: linear-gradient(135deg, #0f7b3a, #1ea85a); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; display: block; line-height: 1.1;">KSR GURU JI</span>
                        <span class="logo-tagline" style="font-family: 'Inter', sans-serif; font-size: 0.72rem; color: var(--text-muted); font-weight: 600; display: block; line-height: 1.2;">Your Cart</span>
                    </div>
                    <div class="nav-links" id="navLinks">
                        <a href="../index.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
                        <a href="../telehealth/index.php" class="nav-link active"><i class="fa-solid fa-stethoscope"></i>Telehealth</a>
                        <div class="user-dropdown">
                            <button class="user-btn"><i class="fas fa-user"></i></button>
                            <div class="dropdown-content">
                                <?php if ($isLoggedIn): ?>
                                    <a href="../profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                                <?php else: ?>
                                    <!-- <a href="../login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                                <a href="../register.php"><i class="fas fa-user-plus"></i> Register</a> -->
                                <?php endif; ?>
                                <a href="../admin/index.php"><i class="fas fa-user-cog"></i> Admin</a>
                                <a href="../delivery/index.php"><i class="fas fa-truck"></i> Delivery</a>
                            </div>
                        </div>
                    </div>
                    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme" style="margin-left: 8px;">
                        <span id="themeIcon">🌙</span>
                    </button>
                    <div class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></div>
                </div>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="cart-title">
            <i class="fas fa-shopping-cart"></i>
            <span>Your Cart · ನಿಮ್ಮ ಬುಟ್ಟಿ</span>
            <span id="cartCountHeader" style="font-size:1rem;font-weight:400;color:var(--text-muted);margin-left:auto;">
                <?php echo $cartCount; ?> item<?php echo $cartCount !== 1 ? 's' : ''; ?>
            </span>
        </div>

        <?php if (empty($cartItems)): ?>
            <div class="cart-empty">
                <i class="fas fa-bag-shopping"></i>
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any items yet. Start shopping!</p>
                <a href="../index.php" class="btn-primary"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-table-wrap" id="cartContainer">
                <div class="cart-items-container">
                    <?php foreach ($cartItems as $item): ?>
                        <?php
                        $itemPrice = (float)$item['price'];
                        $itemQty = (int)$item['qty'];
                        $itemSubtotal = $itemPrice * $itemQty;

                        $nameStr = $item['name'];
                        $knStr = $item['kn'] ?? '';

                        $nameClean = strtolower(preg_replace('/[^a-z0-9]/i', '', $nameStr));
                        $knClean = strtolower(preg_replace('/[^a-z0-9]/i', '', $knStr));

                        $showKn = (!empty($knStr) && $nameClean !== $knClean && strlen($knClean) > 0);
                        ?>
                        <div class="cart-item cart-item-row" data-id="<?php echo $item['id']; ?>" data-price="<?php echo $itemPrice; ?>">
                            <div class="cart-item-image">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="<?php echo htmlspecialchars(normalizeImagePath($item['image'])); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <span style="display:none;font-size:2rem;">🛒</span>
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                            </div>

                            <div class="cart-item-details">
                                <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <?php if ($showKn): ?>
                                    <div class="cart-item-kn"><?php echo htmlspecialchars($item['kn']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['variant'])): ?>
                                    <div style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">
                                        <strong>Selection:</strong> <?php echo htmlspecialchars($item['variant']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($item['description'])): ?>
                                    <div style="font-size:0.85rem; color:var(--text-muted); font-style:italic; margin-top:2px;">
                                        <strong>Details:</strong> <?php echo htmlspecialchars($item['description']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="cart-item-price">₹<?php echo number_format($itemPrice, 2); ?></div>
                            </div>

                            <div class="cart-item-controls">
                                <div class="qty-group">
                                    <button class="qty-btn" data-action="decr" data-id="<?php echo $item['id']; ?>">−</button>
                                    <span class="qty-value" id="qty-<?php echo $item['id']; ?>"><?php echo $itemQty; ?></span>
                                    <button class="qty-btn" data-action="incr" data-id="<?php echo $item['id']; ?>">+</button>
                                </div>
                                <div class="total-cell" style="font-weight: 800; font-size: 1.15rem; color: var(--text-primary); text-align: right; min-width: 80px;" id="total-<?php echo $item['id']; ?>">
                                    ₹<?php echo number_format($itemSubtotal, 2); ?>
                                </div>
                                <button class="remove-btn" data-action="remove" data-id="<?php echo $item['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <div class="cart-total">
                        Total: <span>₹<?php echo number_format($cartTotal, 2); ?></span>
                    </div>
                    <div class="cart-actions">
                        <button class="btn-secondary btn-danger" id="clearCartBtn"><i class="fas fa-trash-alt"></i> Clear Cart</button>
                        <a href="../index.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
                        <button class="btn-primary btn-success" id="checkoutBtn"><i class="fas fa-credit-card"></i> Proceed to Checkout</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2026 KSR GURU JI. All rights reserved.</p>
            <div class="footer-links">
                <a href="../support.php">KSR GURU JI: TrustCenter</a>
            </div>
        </div>
    </footer>

    <!-- ============================================================ -->
    <!-- ===== JAVASCRIPT – AJAX cart operations ===== -->
    <!-- ============================================================ -->
    <script>
        (function() {
            // DOM refs
            const cartContainer = document.getElementById('cartContainer');
            const clearCartBtn = document.getElementById('clearCartBtn');
            const checkoutBtn = document.getElementById('checkoutBtn');

            // Helper: update cart totals and UI
            function updateCartBadge(count) {
                const badges = document.querySelectorAll('.cart-badge');
                badges.forEach(badge => badge.innerText = count);

                const headerCount = document.getElementById('cartCountHeader');
                if (headerCount) {
                    headerCount.innerText = count + ' item' + (count !== 1 ? 's' : '');
                }
            }

            // Helper: show toast notification
            function showToast(message, type = 'success') {
                const existing = document.querySelector('.toast');
                if (existing) existing.remove();
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.style.cssText = `
                    position: fixed;
                    bottom: 24px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: ${type === 'success' ? '#059669' : '#DC2626'};
                    color: white;
                    padding: 12px 24px;
                    border-radius: 60px;
                    font-weight: 500;
                    z-index: 1000;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
                    animation: slideUp 0.3s ease;
                `;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.3s';
                    setTimeout(() => toast.remove(), 300);
                }, 2500);
            }

            // AJAX functions
            function updateCartQty(id, qty) {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('qty', qty);
                fetch('../index.php?ajax=update_cart', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Update local UI
                            const qtySpan = document.getElementById('qty-' + id);
                            if (qtySpan) qtySpan.innerText = qty;
                            // Update total badge
                            const totalQty = data.cart.reduce((sum, i) => sum + i.qty, 0);
                            updateCartBadge(totalQty);
                            // Update total price
                            const totalPrice = data.cart.reduce((sum, i) => sum + i.price * i.qty, 0);
                            document.querySelector('.cart-total span').innerText = '₹' + totalPrice.toFixed(2);

                            // Update row total cell
                            const itemRow = document.querySelector(`.cart-item-row[data-id="${id}"]`);
                            if (itemRow) {
                                const price = parseFloat(itemRow.getAttribute('data-price') || 0);
                                const rowTotalSpan = document.getElementById('total-' + id);
                                if (rowTotalSpan) {
                                    rowTotalSpan.innerText = '₹' + (price * qty).toFixed(2);
                                }
                            }

                            // If qty becomes 0, remove the item row
                            if (qty === 0) {
                                if (itemRow) itemRow.remove();
                                // Check if cart is now empty
                                const remaining = document.querySelectorAll('.cart-item-row').length;
                                if (remaining === 0) {
                                    location.reload(); // Reload to show empty state
                                }
                            }
                            showToast('Cart updated');
                        }
                    })
                    .catch(console.error);
            }

            function removeCartItem(id) {
                const formData = new FormData();
                formData.append('id', id);
                fetch('../index.php?ajax=remove_cart', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const itemRow = document.querySelector(`.cart-item-row[data-id="${id}"]`);
                            if (itemRow) itemRow.remove();
                            const totalQty = data.cart.reduce((sum, i) => sum + i.qty, 0);
                            updateCartBadge(totalQty);
                            const totalPrice = data.cart.reduce((sum, i) => sum + i.price * i.qty, 0);
                            document.querySelector('.cart-total span').innerText = '₹' + totalPrice.toFixed(2);
                            const remaining = document.querySelectorAll('.cart-item-row').length;
                            if (remaining === 0) {
                                location.reload();
                            }
                            showToast('Item removed');
                        }
                    })
                    .catch(console.error);
            }

            function clearCart() {
                if (!confirm('Are you sure you want to clear your entire cart?')) return;
                fetch('../index.php?ajax=clear_cart', {
                        method: 'POST'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    })
                    .catch(console.error);
            }

            // Event listeners (delegated)
            if (cartContainer) {
                cartContainer.addEventListener('click', function(e) {
                    const target = e.target.closest('button');
                    if (!target) return;

                    const id = target.dataset.id;
                    if (!id) return;

                    const action = target.dataset.action;

                    if (action === 'incr' || action === 'decr') {
                        const qtySpan = document.getElementById('qty-' + id);
                        if (!qtySpan) return;
                        let currentQty = parseInt(qtySpan.innerText);
                        let newQty = action === 'incr' ? currentQty + 1 : currentQty - 1;
                        if (newQty < 0) newQty = 0;
                        updateCartQty(id, newQty);
                    }

                    if (action === 'remove') {
                        removeCartItem(id);
                    }
                });
            }

            if (clearCartBtn) {
                clearCartBtn.addEventListener('click', clearCart);
            }

            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', function() {
                    if (<?php echo json_encode(empty($cartItems)); ?>) {
                        showToast('Your cart is empty', 'error');
                        return;
                    }
                    // Redirect to checkout page
                    window.location.href = '../checkout/checkout.php';
                });
            }

            // Mobile menu toggle
            const mobileBtn = document.getElementById('mobileMenuBtn');
            const navLinks = document.getElementById('navLinks');
            if (mobileBtn && navLinks) {
                mobileBtn.addEventListener('click', function() {
                    navLinks.classList.toggle('show');
                });
            }

            // Theme toggle
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            const htmlTag = document.documentElement;

            const savedTheme = localStorage.getItem('theme') || 'light';
            htmlTag.setAttribute('data-theme', savedTheme);
            if (themeIcon) {
                themeIcon.innerText = savedTheme === 'dark' ? '☀️' : '🌙';
            }

            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const currentTheme = htmlTag.getAttribute('data-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    htmlTag.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    if (themeIcon) {
                        themeIcon.innerText = newTheme === 'dark' ? '☀️' : '🌙';
                    }
                });
            }

            // Initial cart badge sync (in case of page load)
            const initialCount = <?php echo $cartCount; ?>;
            updateCartBadge(initialCount);
        })();
    </script>

    <!-- Add toast animation style -->
    <style>
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        .toast {
            animation: slideUp 0.3s ease;
        }
    </style>
</body>

</html>