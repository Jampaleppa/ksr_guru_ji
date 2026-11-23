<?php
// ============================================================
// mobile-bottom-nav.php — Mobile Bottom Navigation Bar
// Include just before </body> in every customer-facing page
// ============================================================
$currentPath = $_SERVER['PHP_SELF'] ?? '';
$cartCount   = 0;
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
}
?>

<!-- ============================================================
     MOBILE BOTTOM NAVIGATION BAR
     Visible only on screens ≤ 768px via CSS
     ============================================================ -->
<nav class="bottom-nav" id="bottomNav" aria-label="Mobile navigation">

  <!-- Home -->
  <a href="/index.php"
     class="bottom-nav-item <?= (strpos($currentPath,'index') !== false || $currentPath === '/') ? 'active' : '' ?>"
     data-page="index"
     aria-label="Home">
    <i class="fas fa-home"></i>
    <span>Home</span>
  </a>

  <!-- Categories -->
  <a href="/index.php#categories"
     class="bottom-nav-item <?= strpos($currentPath,'categories') !== false ? 'active' : '' ?>"
     data-page="categories"
     aria-label="Categories">
    <i class="fas fa-th-large"></i>
    <span>Shop</span>
  </a>

  <!-- Cart (centre action) -->
  <a href="/cart.php"
     class="bottom-nav-item center-action <?= strpos($currentPath,'cart') !== false ? 'active' : '' ?>"
     data-page="cart"
     aria-label="Shopping Cart">
    <div class="center-btn-wrap">
      <i class="fas fa-shopping-cart"></i>
    </div>
    <?php if ($cartCount > 0): ?>
      <span class="nav-badge" id="bottomNavCartBadge"><?= $cartCount ?></span>
    <?php else: ?>
      <span class="nav-badge" id="bottomNavCartBadge" style="display:none;">0</span>
    <?php endif; ?>
    <span>Cart</span>
  </a>

  <!-- Orders -->
  <a href="/orders.php"
     class="bottom-nav-item <?= strpos($currentPath,'orders') !== false ? 'active' : '' ?>"
     data-page="orders"
     aria-label="My Orders">
    <i class="fas fa-box"></i>
    <span>Orders</span>
  </a>

  <!-- Profile -->
  <?php
  $isLoggedIn = !empty($_SESSION['user_id']);
  ?>
  <a href="<?= $isLoggedIn ? '/profile.php' : '/login.php' ?>"
     class="bottom-nav-item <?= (strpos($currentPath,'profile') !== false || strpos($currentPath,'login') !== false) ? 'active' : '' ?>"
     data-page="<?= $isLoggedIn ? 'profile' : 'login' ?>"
     aria-label="<?= $isLoggedIn ? 'My Profile' : 'Login' ?>">
    <i class="fas <?= $isLoggedIn ? 'fa-user-circle' : 'fa-user' ?>"></i>
    <span><?= $isLoggedIn ? 'Profile' : 'Login' ?></span>
  </a>

</nav>

<!-- PWA INSTALL BANNER (shown on first visit) -->
<div id="pwaInstallBanner" aria-live="polite" role="dialog" aria-label="Install KSR Guru Ji App">
  <img src="/icon-192.png" class="pwa-icon" alt="KSR Guru Ji" onerror="this.src='/company_logo.jpg'" />
  <div class="pwa-text">
    <strong>📱 Install KSR Guru Ji App</strong>
    <span>Get fast access, offline support & notifications</span>
  </div>
  <button class="pwa-install-btn" id="pwaInstallBtn">Install</button>
  <button class="pwa-close" id="pwaInstallClose" aria-label="Close">✕</button>
</div>

<!-- SCROLL TO TOP BUTTON -->
<button id="scrollTopBtn" aria-label="Scroll to top" title="Back to top">
  <i class="fas fa-chevron-up"></i>
</button>
