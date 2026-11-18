// ============================================================
// KSR GURU JI - Service Worker (PWA Offline Support)
// Version: 3.0 - Full Mobile App Ready
// ============================================================

const CACHE_NAME = 'ksr-guru-ji-v3';
const STATIC_CACHE = 'ksr-static-v3';
const DYNAMIC_CACHE = 'ksr-dynamic-v3';

// Core files to cache immediately on install
const STATIC_FILES = [
  '/',
  '/index.php',
  '/index.css',
  '/manifest.json',
  '/favicon.ico',
  '/favicon.png',
  '/icon-192.png',
  '/icon-512.png',
  '/cart.php',
  '/checkout.php',
  '/login.php',
  '/register.php',
  '/search.php',
  '/about.php',
  '/contact.php',
  '/support.php',
  '/pricing.php',
  '/track-order.php',
  '/orders.php',
  '/wishlist.php',
  '/offline.php'
];

// Install - cache static assets
self.addEventListener('install', (event) => {
  console.log('[SW] Installing KSR Guru Ji Service Worker v3');
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      console.log('[SW] Caching static files');
      return cache.addAll(STATIC_FILES.map(url => new Request(url, { credentials: 'same-origin' })))
        .catch(err => console.warn('[SW] Some static files failed to cache:', err));
    })
  );
  self.skipWaiting();
});

// Activate - clean old caches
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating KSR Guru Ji Service Worker v3');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter(name => name !== STATIC_CACHE && name !== DYNAMIC_CACHE)
          .map(name => {
            console.log('[SW] Deleting old cache:', name);
            return caches.delete(name);
          })
      );
    })
  );
  self.clients.claim();
});

// Fetch - Network first with cache fallback
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Skip non-GET, cross-origin, admin, and API calls
  if (
    event.request.method !== 'GET' ||
    !url.origin.includes(self.location.origin.split('//')[1]) ||
    url.pathname.startsWith('/admin') ||
    url.pathname.startsWith('/api') ||
    url.pathname.includes('ajax') ||
    url.pathname.includes('razorpay')
  ) {
    return;
  }

  // For PHP pages: Network first, then cache, then offline
  if (url.pathname.endsWith('.php') || url.pathname === '/') {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (response && response.status === 200) {
            const cloned = response.clone();
            caches.open(DYNAMIC_CACHE).then(cache => cache.put(event.request, cloned));
          }
          return response;
        })
        .catch(() => {
          return caches.match(event.request)
            .then(cached => cached || caches.match('/offline.php'));
        })
    );
    return;
  }

  // For static assets: Cache first, then network
  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) return cached;
      return fetch(event.request).then((response) => {
        if (response && response.status === 200) {
          const cloned = response.clone();
          caches.open(DYNAMIC_CACHE).then(cache => cache.put(event.request, cloned));
        }
        return response;
      });
    })
  );
});

// Background Sync for cart
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-cart') {
    console.log('[SW] Background sync: cart');
  }
});

// Push notifications
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'KSR Guru Ji';
  const options = {
    body: data.body || 'You have a new notification from KSR Guru Ji!',
    icon: '/icon-192.png',
    badge: '/icon-72.jpg',
    vibrate: [200, 100, 200],
    tag: data.tag || 'ksr-notification',
    data: { url: data.url || '/' },
    actions: [
      { action: 'view', title: 'View', icon: '/icon-72.jpg' },
      { action: 'dismiss', title: 'Dismiss' }
    ]
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

// Notification click
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then((clientList) => {
      for (const client of clientList) {
        if (client.url === url && 'focus' in client) return client.focus();
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});
