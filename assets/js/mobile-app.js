// ============================================================
// KSR GURU JI — Mobile App JS Controller
// mobile-app.js  v3.0
// Handles: PWA install, Bottom Nav, Service Worker, Ripple,
//          Scroll-to-top, Lazy images, Notifications
// ============================================================

(function () {
  'use strict';

  // ── 1. Register Service Worker ──────────────────────────────
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then(reg => {
          console.log('[PWA] Service Worker registered:', reg.scope);
          // Check for updates
          reg.addEventListener('updatefound', () => {
            const newWorker = reg.installing;
            newWorker.addEventListener('statechange', () => {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                showUpdateToast();
              }
            });
          });
        })
        .catch(err => console.warn('[PWA] SW registration failed:', err));
    });
  }

  // ── 2. PWA Install Prompt ────────────────────────────────────
  let deferredPrompt = null;
  const banner = document.getElementById('pwaInstallBanner');
  const installBtn = document.getElementById('pwaInstallBtn');
  const bannerClose = document.getElementById('pwaInstallClose');

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;

    // Don't show if already installed or dismissed recently
    if (!sessionStorage.getItem('pwa-banner-dismissed') && banner) {
      setTimeout(() => {
        banner.classList.add('show');
      }, 3000);
    }
  });

  if (installBtn) {
    installBtn.addEventListener('click', async () => {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      console.log('[PWA] Install outcome:', outcome);
      deferredPrompt = null;
      if (banner) banner.classList.remove('show');
    });
  }

  if (bannerClose) {
    bannerClose.addEventListener('click', () => {
      if (banner) banner.classList.remove('show');
      sessionStorage.setItem('pwa-banner-dismissed', '1');
    });
  }

  window.addEventListener('appinstalled', () => {
    console.log('[PWA] App installed!');
    if (banner) banner.classList.remove('show');
    deferredPrompt = null;
    showToast('✅ KSR Guru Ji installed as app!', 'success');
  });

  // ── 3. Bottom Navigation active state ───────────────────────
  function setBottomNavActive() {
    const path = window.location.pathname;
    const items = document.querySelectorAll('.bottom-nav-item[data-page]');
    items.forEach(item => {
      item.classList.remove('active');
      const page = item.getAttribute('data-page');
      if (page && path.includes(page)) {
        item.classList.add('active');
      }
    });
    // Home special case
    const homeItem = document.querySelector('.bottom-nav-item[data-page="index"]');
    if (homeItem && (path === '/' || path.endsWith('index.php'))) {
      homeItem.classList.add('active');
    }
  }

  // ── 4. Ripple effect on buttons ─────────────────────────────
  function addRipple(element, e) {
    element.classList.add('ripple');
    const rect = element.getBoundingClientRect();
    element.style.setProperty('--ripple-x', (e.clientX - rect.left) + 'px');
    element.style.setProperty('--ripple-y', (e.clientY - rect.top) + 'px');
    element.classList.add('rippling');
    setTimeout(() => element.classList.remove('rippling'), 500);
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.add-btn, .btn, .pwa-install-btn, .retry-btn, .dl-btn');
    if (btn) addRipple(btn, e);
  });

  // ── 5. Scroll-to-top ─────────────────────────────────────────
  const scrollTopBtn = document.getElementById('scrollTopBtn');
  if (scrollTopBtn) {
    window.addEventListener('scroll', () => {
      scrollTopBtn.classList.toggle('show', window.scrollY > 300);
    }, { passive: true });
    scrollTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // ── 6. Lazy Image Loading ────────────────────────────────────
  if ('IntersectionObserver' in window) {
    const lazyImages = document.querySelectorAll('img[data-src]');
    const imgObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          img.src = img.dataset.src;
          img.classList.remove('lazy');
          imgObserver.unobserve(img);
        }
      });
    }, { rootMargin: '50px' });
    lazyImages.forEach(img => imgObserver.observe(img));
  }

  // ── 7. Update Toast ──────────────────────────────────────────
  function showUpdateToast() {
    const toast = document.createElement('div');
    toast.style.cssText = `
      position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
      background: #0f7b3a; color: white; padding: 12px 20px; border-radius: 50px;
      font-size: 0.85rem; font-weight: 700; z-index: 9999; white-space: nowrap;
      box-shadow: 0 4px 16px rgba(15,123,58,0.4); cursor: pointer;
    `;
    toast.textContent = '🔄 New version available! Tap to refresh';
    toast.addEventListener('click', () => window.location.reload());
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 10000);
  }

  // ── 8. General Toast Notification ───────────────────────────
  window.showToast = function (message, type = 'info', duration = 3000) {
    const existing = document.querySelector('.ksr-toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'ksr-toast';
    const colors = { success: '#0f7b3a', error: '#ef4444', info: '#3b82f6', warning: '#f59e0b' };
    toast.style.cssText = `
      position: fixed; bottom: 84px; left: 50%; transform: translateX(-50%);
      background: ${colors[type] || colors.info}; color: white;
      padding: 12px 24px; border-radius: 50px; font-size: 0.85rem; font-weight: 700;
      z-index: 9999; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
      max-width: 90vw; text-align: center; word-break: break-word;
      animation: toastIn 0.3s ease;
    `;
    toast.innerHTML = message;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.animation = 'toastOut 0.3s ease forwards';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  };

  // Toast animations in head
  const style = document.createElement('style');
  style.textContent = `
    @keyframes toastIn  { from { transform: translateX(-50%) translateY(20px); opacity: 0; } to { transform: translateX(-50%) translateY(0); opacity: 1; } }
    @keyframes toastOut { from { opacity: 1; } to { opacity: 0; transform: translateX(-50%) translateY(10px); } }
  `;
  document.head.appendChild(style);

  // ── 9. Network status indicator ─────────────────────────────
  function handleOffline() {
    showToast('📵 You are offline. Some features may not work.', 'warning', 5000);
  }
  function handleOnline() {
    showToast('✅ Back online!', 'success', 2000);
  }
  window.addEventListener('offline', handleOffline);
  window.addEventListener('online', handleOnline);

  // ── 10. iOS "Add to Home Screen" prompt ─────────────────────
  const isIos = /iphone|ipad|ipod/.test(navigator.userAgent.toLowerCase());
  const isInStandaloneMode = ('standalone' in window.navigator) && window.navigator.standalone;
  if (isIos && !isInStandaloneMode && !sessionStorage.getItem('ios-prompt-shown')) {
    setTimeout(() => {
      const iosDiv = document.createElement('div');
      iosDiv.style.cssText = `
        position: fixed; bottom: 76px; left: 12px; right: 12px; z-index: 3001;
        background: white; border-radius: 20px; padding: 16px 18px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px;
        border: 1px solid rgba(15,123,58,0.15); animation: slideUp 0.4s ease;
      `;
      iosDiv.innerHTML = `
        <img src="/icon-72.jpg" style="width:46px;height:46px;border-radius:12px;object-fit:cover;">
        <div style="flex:1;">
          <strong style="display:block;font-size:0.88rem;color:#122116;">Install KSR Guru Ji</strong>
          <span style="font-size:0.72rem;color:#5e6f63;">
            Tap <strong>Share</strong> <i class="fas fa-share-from-square"></i> then <strong>Add to Home Screen</strong>
          </span>
        </div>
        <button id="iosPromptClose" style="background:none;border:none;font-size:1.1rem;cursor:pointer;color:#5e6f63;padding:4px;">✕</button>
      `;
      document.body.appendChild(iosDiv);
      sessionStorage.setItem('ios-prompt-shown', '1');
      document.getElementById('iosPromptClose').onclick = () => iosDiv.remove();
      setTimeout(() => iosDiv.remove(), 10000);
    }, 4000);
  }

  // ── 11. Bottom nav cart count sync ──────────────────────────
  function syncBottomNavCart() {
    const bottomNavBadge = document.getElementById('bottomNavCartBadge');
    if (!bottomNavBadge) return;
    
    // Find count from any active cart badge in page
    const badges = document.querySelectorAll('#cartCount, .cart-count, .cart-badge, #cartBadge, .nav-badge:not(#bottomNavCartBadge)');
    let count = '0';
    for (const b of badges) {
      const val = b.textContent.trim();
      if (val && !isNaN(parseInt(val)) && parseInt(val) > 0) {
        count = val;
        break;
      }
    }
    bottomNavBadge.textContent = count;
    bottomNavBadge.style.display = count && count !== '0' ? 'flex' : 'none';
  }

  // Run after page load
  document.addEventListener('DOMContentLoaded', () => {
    setBottomNavActive();
    syncBottomNavCart();

    // Keep badge in sync whenever cart count changes anywhere in DOM
    const observer = new MutationObserver(syncBottomNavCart);
    const badges = document.querySelectorAll('#cartCount, .cart-count, .cart-badge, #cartBadge');
    badges.forEach(b => observer.observe(b, { childList: true, characterData: true, subtree: true }));

    // Haptic feedback for touch devices on add-to-cart
    document.addEventListener('click', (e) => {
      const addBtn = e.target.closest('.add-to-cart, .add-btn, .btn-add-cart, .btn-add-large, .add-cart-btn');
      if (addBtn && 'vibrate' in navigator) {
        try { navigator.vibrate(25); } catch (_) {}
      }
    });
  });

  // ── 12. Category swipe scroll snap hint ─────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    const catGrid = document.getElementById('categoriesContainer');
    if (catGrid && window.innerWidth <= 768) {
      catGrid.setAttribute('title', 'Swipe to browse categories');
    }
  });

})();
