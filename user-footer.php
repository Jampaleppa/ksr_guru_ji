<?php
/**
 * includes/user-footer.php - Standardized platform-wide footer
 * 
 * Provides consistent footer across main shop and categories.
 * Expects $base_url to be defined, e.g. './' or '../'.
 */
$base_url = '/';
?>

<!-- ===== RELATED PAGES (Every Page) ===== -->
<section class="footer-related-pages" style="padding:32px 0 10px;">
    <div class="container">
        <h3 style="font-size:1.1rem;font-weight:800;color:var(--text-primary,#122116);margin-bottom:14px;"><i class="fas fa-compass" style="color:var(--color-primary,#0f7b3a);margin-right:6px;"></i>Quick Links · ತ್ವರಿತ ಲಿಂಕ್‌ಗಳು</h3>
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <a href="<?= $base_url ?>categories/vegetables-fruits.php" style="background:var(--bg-card,#fff);border:1px solid var(--border-color,#eee);padding:8px 16px;border-radius:30px;font-size:0.8rem;font-weight:600;color:var(--text-primary,#333);transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-carrot" style="color:var(--color-primary,#0f7b3a);"></i>Vegetables</a>
            <a href="<?= $base_url ?>categories/bakery.php" style="background:var(--bg-card,#fff);border:1px solid var(--border-color,#eee);padding:8px 16px;border-radius:30px;font-size:0.8rem;font-weight:600;color:var(--text-primary,#333);transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-bread-slice" style="color:var(--color-primary,#0f7b3a);"></i>Bakery</a>
            <a href="<?= $base_url ?>categories/dry-fruits.php" style="background:var(--bg-card,#fff);border:1px solid var(--border-color,#eee);padding:8px 16px;border-radius:30px;font-size:0.8rem;font-weight:600;color:var(--text-primary,#333);transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-seedling" style="color:var(--color-primary,#0f7b3a);"></i>Dry Fruits</a>
            <a href="<?= $base_url ?>categories/spices.php" style="background:var(--bg-card,#fff);border:1px solid var(--border-color,#eee);padding:8px 16px;border-radius:30px;font-size:0.8rem;font-weight:600;color:var(--text-primary,#333);transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-pepper-hot" style="color:var(--color-primary,#0f7b3a);"></i>Spices</a>
            <a href="<?= $base_url ?>categories/grains.php" style="background:var(--bg-card,#fff);border:1px solid var(--border-color,#eee);padding:8px 16px;border-radius:30px;font-size:0.8rem;font-weight:600;color:var(--text-primary,#333);transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-wheat-awn" style="color:var(--color-primary,#0f7b3a);"></i>Grains</a>
            <a href="<?= $base_url ?>categories/snacks.php" style="background:var(--bg-card,#fff);border:1px solid var(--border-color,#eee);padding:8px 16px;border-radius:30px;font-size:0.8rem;font-weight:600;color:var(--text-primary,#333);transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-cookie-bite" style="color:var(--color-primary,#0f7b3a);"></i>Snacks</a>
            <a href="<?= $base_url ?>categories/cleaning.php" style="background:var(--bg-card,#fff);border:1px solid var(--border-color,#eee);padding:8px 16px;border-radius:30px;font-size:0.8rem;font-weight:600;color:var(--text-primary,#333);transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-broom" style="color:var(--color-primary,#0f7b3a);"></i>Cleaning</a>
            <a href="<?= $base_url ?>categories/personal-care.php" style="background:var(--bg-card,#fff);border:1px solid var(--border-color,#eee);padding:8px 16px;border-radius:30px;font-size:0.8rem;font-weight:600;color:var(--text-primary,#333);transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-spa" style="color:var(--color-primary,#0f7b3a);"></i>Personal Care</a>
            <a href="<?= $base_url ?>WhatsApp/telehealth/index.php" style="background:linear-gradient(135deg,rgba(232,93,44,0.04),rgba(232,93,44,0.08));border:1px solid rgba(232,93,44,0.15);padding:8px 16px;border-radius:30px;font-size:0.8rem;font-weight:700;color:var(--color-accent,#e85d2c);transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-stethoscope"></i>Doctor Assist</a>
        </div>
    </div>
</section>

<!-- ===== INSPIRATIONAL QUOTE (Every Page) ===== -->
<section style="padding:10px 0 12px;">
    <div class="container">
        <div style="background:linear-gradient(135deg,rgba(15,123,58,0.04),rgba(15,123,58,0.08));border:1px solid rgba(15,123,58,0.1);border-radius:16px;padding:20px 28px;text-align:center;position:relative;">
            <i class="fas fa-quote-left" style="color:var(--color-primary,#0f7b3a);opacity:0.2;font-size:1.2rem;"></i>
            <p class="footer-quote" style="font-size:0.9rem;font-weight:600;color:var(--text-primary,#122116);margin:6px 0;font-style:italic;line-height:1.6;">"Let food be thy medicine and medicine be thy food."</p>
            <span style="font-size:0.75rem;font-weight:700;color:var(--color-primary,#0f7b3a);">— Hippocrates</span>
        </div>
    </div>
</section>

<!-- ===== STANDARDIZED FOOTER ===== -->
<footer class="main-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>KSR GURU JI</h3>
                <p>Freshness delivered daily. Everything your family needs, every day.</p>
                <div class="social-links">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://wa.me/918088584421" target="_blank" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="<?= $base_url ?>index.php">Home</a></li>
                    <li><a href="<?= $base_url ?>categories/stationery.php">Stationery</a></li>
                    <li><a href="<?= $base_url ?>WhatsApp/telehealth/index.php">Doctor Assist</a></li>
                    <li><a href="<?= $base_url ?>track-order.php">Track Order</a></li>
                    <li><a href="<?= $base_url ?>final-suggest.php">Suggestion Box</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Categories</h4>
                <ul>
                    <li><a href="<?= $base_url ?>categories/kitchen.php">Kitchen Essentials</a></li>
                    <li><a href="<?= $base_url ?>categories/cleaning.php">Cleaning Items</a></li>
                    <li><a href="<?= $base_url ?>categories/personal-care.php">Personal Care</a></li>
                    <li><a href="<?= $base_url ?>categories/ready-to-eat.php">Ready-to-Eat Products</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contact Us</h4>
                <ul>
                    <li><i class="fas fa-phone"></i> 8088584421, 8762474406,9590902742</li>
                    <li><i class="fas fa-envelope"></i> ksrguruji26@gmail.com</li>
                    <li><i class="fas fa-map-marker-alt"></i> Gajendragad, Karnataka, India</li>
                </ul>
                </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 KSR GURU JI. All rights reserved. | <a href="<?= $base_url ?>support.php?view=privacy">Privacy Policy</a> | <a href="<?= $base_url ?>support.php?view=terms">Terms &amp; Conditions</a></p>
            <p style="margin-top: 15px; font-size: 0.85rem; color: #c2a69cff; font-weight: 600;">📢 Note: Product prices are subject to change. Please check the latest price before placing your order. For any price-related queries or assistance, please contact Customer Support.</p>
        </div>
    </div>
</footer>

<script>
window.showFastCartToast = function(msg) {
    try {
        var existing = document.getElementById('ksr-fast-toast');
        if (existing) existing.remove();
        var t = document.createElement('div');
        t.id = 'ksr-fast-toast';
        t.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(20px);background:#0f172a;color:#f8fafc;padding:12px 22px;border-radius:999px;font-family:Inter,system-ui,sans-serif;font-size:0.88rem;font-weight:600;box-shadow:0 12px 30px rgba(0,0,0,0.35);z-index:999999;opacity:0;transition:all 0.22s cubic-bezier(0.16,1,0.3,1);pointer-events:none;display:inline-flex;align-items:center;gap:8px;';
        t.innerHTML = '<i class="fas fa-shopping-cart" style="color:#f59e0b;"></i> ' + (msg || 'Your cart is empty! Add items to continue.');
        document.body.appendChild(t);
        requestAnimationFrame(function() {
            t.style.opacity = '1';
            t.style.transform = 'translateX(-50%) translateY(0)';
        });
        setTimeout(function() {
            t.style.opacity = '0';
            t.style.transform = 'translateX(-50%) translateY(10px)';
            setTimeout(function() { if (t.parentNode) t.remove(); }, 250);
        }, 2200);
    } catch (e) {}
};

// Safeguard against main-thread blocking alerts on cart operations to preserve low INP
(function() {
    if (typeof window === 'undefined') return;
    var _origAlert = window.alert;
    window.alert = function(msg) {
        if (typeof msg === 'string' && /cart is empty/i.test(msg)) {
            window.showFastCartToast('🛒 Your cart is empty! Add items to proceed.');
            return;
        }
        return _origAlert ? _origAlert.apply(this, arguments) : undefined;
    };
})();
</script>
