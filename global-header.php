<header class="header">
    <div class="container">
        <nav class="navbar">
            <!-- Logo -->
            <div class="logo-container" onclick="window.location.href='<?= isset($base_url) ? $base_url : '' ?>index.php'" style="cursor: pointer;">
                <div class="logo-image">
                    <img src="<?= isset($base_url) ? $base_url : '' ?>company_logo.jpg" alt="KSR GURU JI Logo" style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;" onerror="this.src='<?= isset($base_url) ? $base_url : '' ?>company_logo.jpg';" />
                </div>
                <div class="logo-text">
                    <span class="logo-brand">KSR GURU JI</span>
                    <span class="logo-tagline">Everything Your Family Needs, Every Day</span>
                </div>

                <!-- Nav Links -->
                <div class="nav-links" id="navLinks">
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/air-freshner.php" class="nav-link"><i class="fas fa-spray-can-sparkles"></i> Air Fresheners</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/bath-body.php" class="nav-link"><i class="fas fa-soap"></i> Bath & Body</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/breakfast.php" class="nav-link"><i class="fas fa-egg"></i> Breakfast</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/cold-drinks.php" class="nav-link"><i class="fas fa-glass-water"></i> Cold Drinks</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/cooking-oil.php" class="nav-link"><i class="fas fa-oil-can"></i> Cooking Oil</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/dairy.php" class="nav-link"><i class="fas fa-cow"></i> Dairy</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/dishwash.php" class="nav-link"><i class="fas fa-hand-sparkles"></i> Dishwash</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/hair-care.php" class="nav-link"><i class="fas fa-user-tie"></i> Hair Care</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/health-pharmacy.php" class="nav-link"><i class="fas fa-prescription-bottle-medical"></i> Health Care</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/kitchen.php" class="nav-link"><i class="fas fa-kitchen-set"></i> Kitchen Care</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/paper-products.php" class="nav-link"><i class="fas fa-toilet-paper"></i> Paper Products</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/pooja.php" class="nav-link"><i class="fas fa-hands-praying"></i> Pooja</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/pules-lentils.php" class="nav-link"><i class="fas fa-bowl-rice"></i> Pulses</a>
                    <a href="<?= isset($base_url) ? $base_url : '' ?>categories/ready-to-eat.php" class="nav-link"><i class="fas fa-bowl-food"></i> Ready to Eat</a>
                </div>

            </div>
            <!-- Header Actions (Visible on Mobile & Desktop) -->
            <div class="header-actions">
                <!-- Theme Toggle -->
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <span id="themeIcon">🌙</span>
                </button>

                <!-- Cart Link -->
                <a href="<?= isset($base_url) ? $base_url : '' ?>cart/cart.php" class="cart-link" id="cartLink">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-text">Cart</span>
                    <span class="cart-badge" id="cartCount"><?= isset($cartCount) ? $cartCount : 0 ?></span>
                </a>

                <!-- User Dropdown -->
                <div class="user-dropdown">
                    <button class="user-btn" aria-label="User Profile"><i class="fas fa-user"></i></button>
                    <div class="dropdown-content">
                        <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                            <a href="<?= isset($base_url) ? $base_url : '' ?>profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                            <a href="<?= isset($base_url) ? $base_url : '' ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        <?php endif; ?>
                        <a href="<?= isset($base_url) ? $base_url : '' ?>admin/index.php"><i class="fas fa-user-cog"></i> Admin</a>
                        <a href="<?= isset($base_url) ? $base_url : '' ?>delivery/index.php"><i class="fas fa-truck"></i> Delivery</a>
                        <a href="<?= isset($base_url) ? $base_url : '' ?>WhatsApp/telehealth/index.php"><i class="fas fa-stethoscope"></i> Doctor Assist</a>
                    </div>
                </div>

                <!-- Support Link icon style -->
                <a href="<?= isset($base_url) ? $base_url : '' ?>support.php" class="nav-link-support" title="Support"><i class="fas fa-headset"></i></a>
            </div>
        </nav>
    </div>
</header>
<?php include_once __DIR__ . '/cart-drawer.php'; ?>
<!-- Cart link left alone to allow normal navigation to cart.php -->