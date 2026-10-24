<header class="header">
    <div class="container">
        <nav class="navbar">
            <!-- Logo -->
            <div class="logo-container" onclick="window.location.href='/index.php'" style="cursor: pointer;">
                <div class="logo-image">
                    <img src="/company_logo.jpg" alt="KSR GURU JI Logo" style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;" onerror="this.style.display='none';this.parentElement.innerHTML='🛒';" />
                </div>
                <div class="logo-text">
                    <span class="logo-brand">KSR GURU JI</span>
                    <span class="logo-tagline">Everything Your Family Needs, Every Day</span>
                </div>
            </div>

            <!-- Nav Links -->
            <div class="nav-links" id="navLinks">
                <a href="/categories/air-freshner.php" class="nav-link"><i class="fas fa-spray-can-sparkles"></i> Air Fresheners</a>
                <a href="/categories/bath-body.php" class="nav-link"><i class="fas fa-soap"></i> Bath & Body</a>
                <a href="/categories/breakfast.php" class="nav-link"><i class="fas fa-egg"></i> Breakfast</a>
                <a href="/categories/cold-drinks.php" class="nav-link"><i class="fas fa-glass-water"></i> Cold Drinks</a>
                <a href="/categories/cooking-oil.php" class="nav-link"><i class="fas fa-oil-can"></i> Cooking Oil</a>
                <a href="/categories/dairy.php" class="nav-link"><i class="fas fa-cow"></i> Dairy</a>
                <a href="/categories/dishwash.php" class="nav-link"><i class="fas fa-hand-sparkles"></i> Dishwash</a>
                <a href="/categories/hair-care.php" class="nav-link"><i class="fas fa-user-tie"></i> Hair Care</a>
                <a href="/categories/health-pharmacy.php" class="nav-link"><i class="fas fa-prescription-bottle-medical"></i> Health Care</a>
                <a href="/categories/kitchen.php" class="nav-link"><i class="fas fa-kitchen-set"></i> Kitchen Care</a>
                <a href="/categories/paper-products.php" class="nav-link"><i class="fas fa-toilet-paper"></i> Paper Products</a>
                <a href="/categories/pooja.php" class="nav-link"><i class="fas fa-hands-praying"></i> Pooja</a>
                <a href="/categories/pules-lentils.php" class="nav-link"><i class="fas fa-bowl-rice"></i> Pulses</a>
                <a href="/categories/ready-to-eat.php" class="nav-link"><i class="fas fa-bowl-food"></i> Ready to Eat</a>
                <a href="/WhatsApp/telehealth/medicine/tablet.php" class="nav-link"><i class="fas fa-medkit"></i> Medicines</a>
                <a href="/categories/parcels.php" class="nav-link"><i class="fas fa-box"></i> Parcel Delivery</a>
            </div>

            <!-- Header Actions (Visible on Mobile & Desktop) -->
            <div class="header-actions">
                <!-- Theme Toggle -->
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <span id="themeIcon">🌙</span>
                </button>

                <!-- Cart Link -->
                <a href="/cart.php" class="cart-link" id="cartLink">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-text">Cart</span>
                    <span class="cart-badge" id="cartCount"><?= isset($cartCount) ? (int)$cartCount : (isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0) ?></span>
                </a>

                <!-- User Dropdown -->
                <div class="user-dropdown">
                    <button class="user-btn" aria-label="User Profile"><i class="fas fa-user"></i></button>
                    <div class="dropdown-content">
                        <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                            <a href="/profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                            <a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        <?php endif; ?>
                        <a href="/admin/index.php"><i class="fas fa-user-cog"></i> Admin</a>
                        <a href="/delivery/index.php"><i class="fas fa-truck"></i> Delivery</a>
                        <a href="/WhatsApp/telehealth/index.php"><i class="fas fa-stethoscope"></i> Doctor Assist</a>
                    </div>
                </div>

                <!-- Support Link icon style -->
                <a href="/support.php" class="nav-link-support" title="Support"><i class="fas fa-headset"></i></a>

                <!-- Hamburger button for mobile menu -->
                <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
            </div>
        </nav>
    </div>
</header>
<?php include_once __DIR__ . '/cart-drawer.php'; ?>

<script>
(function() {
    // Mobile menu toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const nav = document.getElementById('navLinks');
    if (mobileBtn && nav && !mobileBtn._hasBoundClick) {
        mobileBtn._hasBoundClick = true;
        mobileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            nav.classList.toggle('show');
        });
        document.addEventListener('click', function(e) {
            if (!nav.contains(e.target) && e.target !== mobileBtn) {
                nav.classList.remove('show');
            }
        });
    }

    // Theme toggle initialization & listener
    const themeBtn = document.getElementById('themeToggle');
    const themeIco = document.getElementById('themeIcon');
    const htmlEl = document.documentElement;
    const activeTheme = localStorage.getItem('theme') || 'light';
    htmlEl.setAttribute('data-theme', activeTheme);
    if (themeIco) {
        themeIco.innerText = activeTheme === 'dark' ? '☀️' : '🌙';
    }
    if (themeBtn && !themeBtn._hasBoundClick) {
        themeBtn._hasBoundClick = true;
        themeBtn.addEventListener('click', function() {
            const nextTheme = htmlEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            htmlEl.setAttribute('data-theme', nextTheme);
            localStorage.setItem('theme', nextTheme);
            if (themeIco) {
                themeIco.innerText = nextTheme === 'dark' ? '☀️' : '🌙';
            }
        });
    }
})();
</script>