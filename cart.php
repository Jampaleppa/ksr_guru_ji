<?php
// ============================================================
// cart.php - Shopping Cart Page (KSR GURU JI - Premium UI)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Start session for cart
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Normalize every cart line to the expected shape {id, price, qty, ...}
$_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function ($item) {
    return is_array($item)
        && isset($item['id'])
        && isset($item['price'], $item['qty'])
        && is_numeric($item['price']) && is_numeric($item['qty']);
}));

// User info
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? ($_SESSION['user_name'] ?? 'User') : '';

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
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        header('Location: /cart.php');
        exit;
    }

    if ($action === 'remove' && isset($_POST['id'])) {
        $id = trim($_POST['id']);
        $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], fn($item) => (string)$item['id'] !== $id));
        header('Location: /cart.php');
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        header('Location: /cart.php');
        exit;
    }
}

// Calculate totals
$cartItems = $_SESSION['cart'];
$cartCount = array_sum(array_column($cartItems, 'qty'));
$cartTotal = array_sum(array_map(fn($item) => (float)$item['price'] * (int)$item['qty'], $cartItems));
$freeDeliveryThreshold = 499;
$deliveryFee = ($cartTotal >= $freeDeliveryThreshold || $cartTotal == 0) ? 0 : 40;
$grandTotal = $cartTotal + $deliveryFee;
$amountNeededForFreeDelivery = max(0, $freeDeliveryThreshold - $cartTotal);
$freeDeliveryPercent = min(100, round(($cartTotal / $freeDeliveryThreshold) * 100));

// Build WhatsApp text for direct order
$waText = "Namaste KSR GURU JI! I would like to order the following items from my cart:%0A";
foreach ($cartItems as $ci) {
    $waText .= "• " . urlencode($ci['name']) . " x " . $ci['qty'] . " (₹" . ($ci['price'] * $ci['qty']) . ")%0A";
}
$waText .= "%0ATotal Amount: ₹" . $grandTotal . "%0APlease confirm my order.";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes" />
    <title>Your Cart (<?php echo $cartCount; ?>) · KSR GURU JI</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- Google Fonts: Inter + Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/index.css?v=1.5" />
    <link rel="stylesheet" href="/cart-checkout.css?v=1.1" />

    <style>
        :root {
            --ksr-primary: #0f7b3a;
            --ksr-primary-light: #1ea85a;
            --ksr-accent: #f59e0b;
            --ksr-bg: #f8fafc;
            --ksr-card-bg: #ffffff;
            --ksr-border: #e2e8f0;
            --ksr-text-main: #0f172a;
            --ksr-text-muted: #64748b;
        }

        [data-theme="dark"] {
            --ksr-bg: #0b1322;
            --ksr-card-bg: #142036;
            --ksr-border: #1e293b;
            --ksr-text-main: #f8fafc;
            --ksr-text-muted: #94a3b8;
        }

        .cart-checkout-stepper {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0 28px;
            gap: 12px;
        }

        .stepper-step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--ksr-text-muted);
        }

        .stepper-step.active {
            color: var(--ksr-primary);
        }

        .stepper-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--ksr-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--ksr-text-main);
        }

        .stepper-step.active .stepper-num {
            background: linear-gradient(135deg, var(--ksr-primary), var(--ksr-primary-light));
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(15, 123, 58, 0.25);
        }

        .stepper-divider {
            width: 36px;
            height: 2px;
            background: var(--ksr-border);
        }

        .free-delivery-meter {
            background: linear-gradient(135deg, rgba(15, 123, 58, 0.08), rgba(30, 168, 90, 0.12));
            border: 1px solid rgba(15, 123, 58, 0.25);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
        }

        .meter-text {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 600;
            font-size: 0.92rem;
            color: var(--ksr-text-main);
            margin-bottom: 8px;
        }

        .meter-bar-track {
            width: 100%;
            height: 8px;
            background: rgba(0, 0, 0, 0.08);
            border-radius: 6px;
            overflow: hidden;
        }

        .meter-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 6px;
            transition: width 0.4s ease;
        }

        .cart-grid-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 28px;
            align-items: start;
        }

        @media (max-width: 992px) {
            .cart-grid-layout {
                grid-template-columns: 1fr;
            }
        }

        .cart-items-card {
            background: var(--ksr-card-bg);
            border: 1px solid var(--ksr-border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }

        .cart-order-summary-card {
            background: var(--ksr-card-bg);
            border: 1px solid var(--ksr-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            position: sticky;
            top: 90px;
        }

        .summary-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--ksr-border);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: var(--ksr-text-muted);
        }

        .summary-row strong,
        .summary-row.total {
            color: var(--ksr-text-main);
            font-weight: 700;
        }

        .summary-row.total {
            font-size: 1.3rem;
            border-top: 2px dashed var(--ksr-border);
            padding-top: 14px;
            margin-top: 14px;
        }

        .checkout-main-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #0f7b3a, #1ea85a);
            color: #ffffff;
            font-size: 1.05rem;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            box-shadow: 0 6px 18px rgba(15, 123, 58, 0.3);
            transition: all 0.25s ease;
            border: none;
            cursor: pointer;
            margin-top: 16px;
        }

        .checkout-main-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(15, 123, 58, 0.4);
            color: #ffffff;
        }

        .whatsapp-order-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: #25d366;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 12px;
            text-decoration: none;
            margin-top: 10px;
            transition: background 0.2s ease;
        }

        .whatsapp-order-btn:hover {
            background: #1eb956;
            color: #ffffff;
        }

        .trust-badge-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid var(--ksr-border);
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            color: var(--ksr-text-muted);
            line-height: 1.3;
        }

        .trust-item i {
            color: var(--ksr-primary);
            font-size: 1rem;
        }

        .cart-item-row {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid var(--ksr-border);
            align-items: center;
        }

        .cart-item-row:last-child {
            border-bottom: none;
        }

        .cart-item-thumb {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: var(--ksr-bg);
            border: 1px solid var(--ksr-border);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .cart-item-thumb img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
        }

        .qty-stepper {
            display: inline-flex;
            align-items: center;
            background: var(--ksr-bg);
            border: 1px solid var(--ksr-border);
            border-radius: 8px;
            overflow: hidden;
        }

        .qty-stepper button {
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            font-size: 1rem;
            cursor: pointer;
            color: var(--ksr-text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .qty-stepper button:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .qty-stepper span {
            min-width: 32px;
            text-align: center;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--ksr-text-main);
        }

        .cart-actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--ksr-border);
        }

        .quick-category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 28px;
        }

        .quick-cat-card {
            background: var(--ksr-card-bg);
            border: 1px solid var(--ksr-border);
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            text-decoration: none;
            color: var(--ksr-text-main);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .quick-cat-card:hover {
            transform: translateY(-3px);
            border-color: var(--ksr-primary);
        }

        .quick-cat-icon {
            font-size: 2rem;
            margin-bottom: 8px;
            display: inline-block;
        }

        .quick-cat-title {
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .quick-cat-sub {
            font-size: 0.78rem;
            color: var(--ksr-text-muted);
        }
    </style>
</head>

<body>

    <!-- ===== HEADER ===== -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo-container" onclick="window.location.href='/index.php'" style="cursor: pointer; display: flex; align-items: center; gap: 12px;">
                    <div class="logo-image" style="width: 42px; height: 42px; background: linear-gradient(135deg, #0f7b3a, #1ea85a); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(15, 123, 58, 0.15); flex-shrink: 0; border: 1.5px solid rgba(255, 255, 255, 0.8);">
                        <img src="/company_logo.jpg" alt="KSR GURU JI Logo" style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;" onerror="this.style.display='none';this.parentElement.innerHTML='🛒';" />
                    </div>
                    <div class="logo-text">
                        <span class="logo-brand" style="font-family: 'Poppins', sans-serif; font-size: 1.25rem; font-weight: 800; background: linear-gradient(135deg, #0f7b3a, #1ea85a); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; display: block; line-height: 1.1;">KSR GURU JI</span>
                        <span class="logo-tagline" style="font-family: 'Inter', sans-serif; font-size: 0.72rem; color: var(--text-muted); font-weight: 600; display: block; line-height: 1.2;">Your Cart · ನಿಮ್ಮ ಬುಟ್ಟಿ</span>
                    </div>
                </div>

                <div class="nav-links" id="navLinks">
                    <a href="/index.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
                    <a href="/WhatsApp/telehealth/medicine/tablet.php" class="nav-link"><i class="fa-solid fa-pills"></i> Tablets (3,689+)</a>
                    <a href="/WhatsApp/telehealth/index.php" class="nav-link"><i class="fa-solid fa-stethoscope"></i> Telehealth</a>
                    <div class="user-dropdown">
                        <button class="user-btn"><i class="fas fa-user"></i></button>
                        <div class="dropdown-content">
                            <?php if ($isLoggedIn): ?>
                                <a href="/profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                                <a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            <?php else: ?>
                                <a href="/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                                <a href="/register.php"><i class="fas fa-user-plus"></i> Register</a>
                            <?php endif; ?>
                            <a href="/admin/index.php"><i class="fas fa-user-cog"></i> Admin</a>
                            <a href="/delivery/index.php"><i class="fas fa-truck"></i> Delivery</a>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                        <span id="themeIcon">🌙</span>
                    </button>
                    <div class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></div>
                </div>
            </nav>
        </div>
    </header>

    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 16px 60px;">

        <!-- Checkout Stepper -->
        <div class="cart-checkout-stepper">
            <div class="stepper-step active">
                <span class="stepper-num"><i class="fas fa-check" style="font-size: 0.75rem;"></i></span>
                <span>Cart (ನಿಮ್ಮ ಬುಟ್ಟಿ)</span>
            </div>
            <div class="stepper-divider"></div>
            <div class="stepper-step">
                <span class="stepper-num">2</span>
                <span>Address</span>
            </div>
            <div class="stepper-divider"></div>
            <div class="stepper-step">
                <span class="stepper-num">3</span>
                <span>Payment</span>
            </div>
        </div>

        <?php if (empty($cartItems)): ?>
            <!-- Empty Cart View -->
            <div class="cart-empty" style="max-width: 650px; margin: 30px auto; text-align: center; background: var(--ksr-card-bg); border: 1px solid var(--ksr-border); border-radius: 20px; padding: 48px 24px;">
                <div style="width: 80px; height: 80px; margin: 0 auto 20px; border-radius: 50%; background: rgba(15, 123, 58, 0.1); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--ksr-primary);">
                    🛒
                </div>
                <h2 style="font-size: 1.6rem; font-weight: 800; margin-bottom: 8px; color: var(--ksr-text-main);">Your Cart is Empty</h2>
                <p style="color: var(--ksr-text-muted); font-size: 0.95rem; margin-bottom: 24px;">ನಿಮ್ಮ ಬುಟ್ಟಿ ಖಾಲಿಯಾಗಿದೆ. Add fresh groceries or medicines to get started.</p>
                <a href="/index.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; border-radius: 12px; background: linear-gradient(135deg, #0f7b3a, #1ea85a); color: #fff; text-decoration: none; font-weight: 700;">
                    <i class="fas fa-arrow-left"></i> Start Shopping
                </a>

                <div style="margin-top: 36px; text-align: left;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: var(--ksr-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px;">Popular Departments</h3>
                    <div class="quick-category-grid">
                        <a href="/WhatsApp/telehealth/medicine/tablet.php" class="quick-cat-card">
                            <span class="quick-cat-icon">💊</span>
                            <div class="quick-cat-title">Medicines & Tablets</div>
                            <div class="quick-cat-sub">3,689+ Ayurvedic & Health items</div>
                        </a>
                        <a href="/categories/vegetables-fruits.php" class="quick-cat-card">
                            <span class="quick-cat-icon">🥦</span>
                            <div class="quick-cat-title">Fruits & Vegetables</div>
                            <div class="quick-cat-sub">Fresh from local farms</div>
                        </a>
                        <a href="/categories/grains.php" class="quick-cat-card">
                            <span class="quick-cat-icon">🌾</span>
                            <div class="quick-cat-title">Grains & Staples</div>
                            <div class="quick-cat-sub">Rice, Atta, Dals & Pulses</div>
                        </a>
                        <a href="/categories/personal-care.php" class="quick-cat-card">
                            <span class="quick-cat-icon">🧴</span>
                            <div class="quick-cat-title">Personal Care</div>
                            <div class="quick-cat-sub">Soaps, oils & wellness</div>
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <!-- Free Delivery Meter -->
            <div class="free-delivery-meter" id="freeDeliveryMeter">
                <div class="meter-text">
                    <span id="meterText">
                        <?php if ($amountNeededForFreeDelivery > 0): ?>
                            🚚 Add <strong>₹<?php echo number_format($amountNeededForFreeDelivery, 2); ?></strong> more to unlock <strong>FREE Delivery</strong>!
                        <?php else: ?>
                            🎉 <strong>FREE Delivery Unlocked!</strong> You saved ₹40 on this order.
                        <?php endif; ?>
                    </span>
                    <span id="meterPercent" style="font-size: 0.85rem; color: var(--ksr-primary); font-weight: 700;">
                        <?php echo $freeDeliveryPercent; ?>%
                    </span>
                </div>
                <div class="meter-bar-track">
                    <div class="meter-bar-fill" id="meterFill" style="width: <?php echo $freeDeliveryPercent; ?>%;"></div>
                </div>
            </div>

            <!-- Two-Column Cart Layout -->
            <div class="cart-grid-layout" id="cartContainer">

                <!-- Left Column: Cart Items -->
                <div class="cart-items-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid var(--ksr-border);">
                        <h2 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: var(--ksr-text-main);">
                            Shopping Bag (<span id="cartCountHeader"><?php echo $cartCount; ?></span> items)
                        </h2>
                        <button class="btn-clear" id="clearCartBtn" style="background: none; border: none; color: #ef4444; font-size: 0.88rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-trash-alt"></i> Clear All
                        </button>
                    </div>

                    <div class="cart-items-container">
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                            $itemPrice = (float)$item['price'];
                            $itemQty = (int)$item['qty'];
                            $itemSubtotal = $itemPrice * $itemQty;
                            $nameStr = $item['name'];
                            $knStr = $item['kn'] ?? '';
                            $showKn = !empty($knStr) && strcasecmp(trim($nameStr), trim($knStr)) !== 0;
                            ?>
                            <div class="cart-item-row" data-id="<?php echo htmlspecialchars($item['id']); ?>" data-price="<?php echo $itemPrice; ?>">
                                <!-- Thumbnail -->
                                <div class="cart-item-thumb">
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="<?php echo htmlspecialchars(normalizeImagePath($item['image'])); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" onerror="this.style.display='none';this.parentElement.innerHTML='🛒';" />
                                    <?php else: ?>
                                        <span style="font-size: 2rem;">🛒</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Details -->
                                <div class="cart-item-info">
                                    <h4 style="font-size: 1rem; font-weight: 700; margin: 0 0 4px; color: var(--ksr-text-main); line-height: 1.3;">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </h4>
                                    <?php if ($showKn): ?>
                                        <div style="font-size: 0.82rem; color: #059669; font-weight: 600; margin-bottom: 4px;">
                                            <?php echo htmlspecialchars($item['kn']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['variant'])): ?>
                                        <span style="display: inline-block; background: rgba(15, 123, 58, 0.08); color: var(--ksr-primary); font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; margin-bottom: 6px;">
                                            <?php echo htmlspecialchars($item['variant']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--ksr-text-main);">
                                        ₹<?php echo number_format($itemPrice, 2); ?>
                                    </div>
                                </div>

                                <!-- Qty & Total -->
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                    <div style="font-weight: 800; font-size: 1.1rem; color: var(--ksr-primary);" id="total-<?php echo htmlspecialchars($item['id']); ?>">
                                        ₹<?php echo number_format($itemSubtotal, 2); ?>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="qty-stepper">
                                            <button type="button" data-action="decr" data-id="<?php echo htmlspecialchars($item['id']); ?>" aria-label="Decrease quantity">−</button>
                                            <span id="qty-<?php echo htmlspecialchars($item['id']); ?>"><?php echo $itemQty; ?></span>
                                            <button type="button" data-action="incr" data-id="<?php echo htmlspecialchars($item['id']); ?>" aria-label="Increase quantity">+</button>
                                        </div>
                                        <button type="button" class="remove-btn" data-action="remove" data-id="<?php echo htmlspecialchars($item['id']); ?>" style="background: none; border: none; color: #94a3b8; font-size: 0.95rem; cursor: pointer; padding: 6px;" title="Remove item">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-actions-bar">
                        <a href="/index.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--ksr-primary); text-decoration: none; font-weight: 700; font-size: 0.92rem;">
                            <i class="fas fa-arrow-left"></i> Continue Shopping
                        </a>
                        <span style="font-size: 0.85rem; color: var(--ksr-text-muted);">
                            Free delivery threshold: ₹499
                        </span>
                    </div>
                </div>

                <!-- Right Column: Order Summary -->
                <div class="cart-order-summary-card">
                    <div class="summary-title">
                        <i class="fas fa-file-invoice-dollar" style="color: var(--ksr-primary);"></i> Order Summary
                    </div>

                    <div class="summary-row">
                        <span>Items Subtotal (<span id="summaryCount"><?php echo $cartCount; ?></span> items)</span>
                        <span id="summarySubtotal" style="font-weight: 600;">₹<?php echo number_format($cartTotal, 2); ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Delivery Charges</span>
                        <span id="summaryDelivery" style="font-weight: 600; color: <?php echo $deliveryFee === 0 ? '#10b981' : 'inherit'; ?>;">
                            <?php echo $deliveryFee === 0 ? '<span style="color:#10b981;">FREE</span>' : '₹' . number_format($deliveryFee, 2); ?>
                        </span>
                    </div>

                    <div class="summary-row">
                        <span>GST / Estimated Taxes</span>
                        <span style="color: #10b981; font-weight: 600;">Included</span>
                    </div>

                    <div class="summary-row total">
                        <span>Grand Total</span>
                        <span id="summaryGrandTotal" style="color: var(--ksr-primary); font-size: 1.4rem;">
                            ₹<?php echo number_format($grandTotal, 2); ?>
                        </span>
                    </div>

                    <!-- Checkout Button -->
                    <a href="/checkout.php" class="checkout-main-btn" id="checkoutBtn">
                        <i class="fas fa-lock"></i> Proceed to Checkout
                    </a>

                    <!-- Direct WhatsApp Order -->
                    <a href="https://wa.me/918088584421?text=<?php echo $waText; ?>" target="_blank" class="whatsapp-order-btn" title="Order directly on WhatsApp">
                        <i class="fab fa-whatsapp" style="font-size: 1.15rem;"></i> Order via WhatsApp
                    </a>

                    <!-- Trust Signals -->
                    <div class="trust-badge-row">
                        <div class="trust-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>100% Genuine Ayurvedic & Groceries</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-truck-fast"></i>
                            <span>Fast Doorstep Delivery</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Cash on Delivery Available</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-headset"></i>
                            <span>Support via Call & WhatsApp</span>
                        </div>
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
                <a href="/support.php">KSR GURU JI: TrustCenter</a>
                <a href="/WhatsApp/telehealth/medicine/tablet.php">Pharmacy Tablets</a>
                <a href="/WhatsApp/telehealth/index.php">Telehealth Consultation</a>
            </div>
        </div>
    </footer>

    <!-- ============================================================ -->
    <!-- ===== JAVASCRIPT: Robust AJAX cart operations ===== -->
    <!-- ============================================================ -->
    <script>
        (function() {
            const cartContainer = document.getElementById('cartContainer');
            const clearCartBtn = document.getElementById('clearCartBtn');
            const freeDeliveryThreshold = 499;

            // Toast helper
            function showToast(message, type = 'success') {
                const existing = document.querySelector('.cart-toast');
                if (existing) existing.remove();

                const toast = document.createElement('div');
                toast.className = 'cart-toast';
                toast.style.cssText = `
                    position: fixed;
                    bottom: 24px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: ${type === 'success' ? '#0f7b3a' : (type === 'error' ? '#ef4444' : '#1e293b')};
                    color: #ffffff;
                    padding: 12px 24px;
                    border-radius: 50px;
                    font-weight: 600;
                    font-size: 0.92rem;
                    z-index: 9999;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.25);
                    transition: opacity 0.3s ease;
                `;
                toast.innerText = message;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 2200);
            }

            // Safe parser for JSON responses
            function parseJsonSafely(text) {
                try {
                    const idx = text.indexOf('{');
                    if (idx !== -1) {
                        return JSON.parse(text.substring(idx));
                    }
                } catch (e) {
                    console.warn('JSON parsing error:', e, text);
                }
                return null;
            }

            // Update UI elements based on new cart array
            function updateCartUIFromData(cart) {
                const totalQty = cart.reduce((sum, i) => sum + (parseInt(i.qty) || 0), 0);
                const subtotal = cart.reduce((sum, i) => sum + (parseFloat(i.price) * (parseInt(i.qty) || 0)), 0);
                const deliveryFee = (subtotal >= freeDeliveryThreshold || subtotal === 0) ? 0 : 40;
                const grandTotal = subtotal + deliveryFee;

                // Header badge
                const headerCount = document.getElementById('cartCountHeader');
                if (headerCount) headerCount.innerText = totalQty;
                const summaryCount = document.getElementById('summaryCount');
                if (summaryCount) summaryCount.innerText = totalQty;

                // Subtotal & Grand Total
                const summarySubtotal = document.getElementById('summarySubtotal');
                if (summarySubtotal) summarySubtotal.innerText = '₹' + subtotal.toFixed(2);

                const summaryDelivery = document.getElementById('summaryDelivery');
                if (summaryDelivery) {
                    summaryDelivery.innerHTML = deliveryFee === 0 ? '<span style="color:#10b981;">FREE</span>' : '₹' + deliveryFee.toFixed(2);
                }

                const summaryGrandTotal = document.getElementById('summaryGrandTotal');
                if (summaryGrandTotal) summaryGrandTotal.innerText = '₹' + grandTotal.toFixed(2);

                // Free delivery progress meter
                const needed = Math.max(0, freeDeliveryThreshold - subtotal);
                const pct = Math.min(100, Math.round((subtotal / freeDeliveryThreshold) * 100));

                const meterFill = document.getElementById('meterFill');
                const meterPercent = document.getElementById('meterPercent');
                const meterText = document.getElementById('meterText');

                if (meterFill) meterFill.style.width = pct + '%';
                if (meterPercent) meterPercent.innerText = pct + '%';
                if (meterText) {
                    if (needed > 0) {
                        meterText.innerHTML = `🚚 Add <strong>₹${needed.toFixed(2)}</strong> more to unlock <strong>FREE Delivery</strong>!`;
                    } else {
                        meterText.innerHTML = `🎉 <strong>FREE Delivery Unlocked!</strong> You saved ₹40 on this order.`;
                    }
                }

                // If cart is now empty, reload to display clean empty state
                if (cart.length === 0 || totalQty === 0) {
                    window.location.reload();
                }
            }

            // Update item quantity
            function updateCartQty(id, qty) {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('qty', qty);

                fetch('/index.php?ajax=update_cart', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.text())
                .then(text => {
                    const data = parseJsonSafely(text);
                    if (data && data.success) {
                        const qtySpan = document.getElementById('qty-' + id);
                        if (qtySpan) qtySpan.innerText = qty;

                        const itemRow = document.querySelector(`.cart-item-row[data-id="${id}"]`);
                        if (itemRow) {
                            const price = parseFloat(itemRow.getAttribute('data-price') || 0);
                            const rowTotal = document.getElementById('total-' + id);
                            if (rowTotal) rowTotal.innerText = '₹' + (price * qty).toFixed(2);

                            if (qty === 0) {
                                itemRow.remove();
                            }
                        }

                        if (Array.isArray(data.cart)) {
                            updateCartUIFromData(data.cart);
                        }
                        showToast(qty === 0 ? 'Item removed' : 'Cart updated');
                    } else {
                        // Fallback reload if something went wrong
                        window.location.reload();
                    }
                })
                .catch(err => {
                    console.error('Update cart error:', err);
                    window.location.reload();
                });
            }

            // Remove cart item
            function removeCartItem(id) {
                const formData = new FormData();
                formData.append('id', id);

                fetch('/index.php?ajax=remove_cart', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.text())
                .then(text => {
                    const data = parseJsonSafely(text);
                    if (data && data.success) {
                        const itemRow = document.querySelector(`.cart-item-row[data-id="${id}"]`);
                        if (itemRow) itemRow.remove();

                        if (Array.isArray(data.cart)) {
                            updateCartUIFromData(data.cart);
                        }
                        showToast('Item removed from cart');
                    } else {
                        window.location.reload();
                    }
                })
                .catch(err => {
                    console.error('Remove cart error:', err);
                    window.location.reload();
                });
            }

            // Clear entire cart
            function clearCart() {
                if (!confirm('Are you sure you want to empty your entire cart?')) return;

                fetch('/index.php?ajax=clear_cart', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.text())
                .then(text => {
                    const data = parseJsonSafely(text);
                    if (data && data.success) {
                        window.location.reload();
                    } else {
                        window.location.reload();
                    }
                })
                .catch(err => {
                    console.error('Clear cart error:', err);
                    window.location.reload();
                });
            }

            // Event Delegation
            if (cartContainer) {
                cartContainer.addEventListener('click', function(e) {
                    const btn = e.target.closest('button');
                    if (!btn) return;

                    const id = btn.dataset.id;
                    const action = btn.dataset.action;
                    if (!id || !action) return;

                    if (action === 'incr' || action === 'decr') {
                        const qtySpan = document.getElementById('qty-' + id);
                        if (!qtySpan) return;
                        const current = parseInt(qtySpan.innerText) || 1;
                        const newQty = action === 'incr' ? current + 1 : Math.max(0, current - 1);
                        updateCartQty(id, newQty);
                    } else if (action === 'remove') {
                        removeCartItem(id);
                    }
                });
            }

            if (clearCartBtn) {
                clearCartBtn.addEventListener('click', clearCart);
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

            // Mobile menu toggle
            const mobileBtn = document.getElementById('mobileMenuBtn');
            const navLinks = document.getElementById('navLinks');
            if (mobileBtn && navLinks) {
                mobileBtn.addEventListener('click', function() {
                    navLinks.classList.toggle('show');
                });
            }
        })();
    </script>
</body>

</html>