<?php
// api/sw.php - Dedicated service worker handler
// Vercel route: { "src": "/sw.js", "dest": "/api/sw.php" }
header('Content-Type: application/javascript; charset=utf-8');
header('Service-Worker-Allowed: /');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$swPath = dirname(__DIR__) . '/sw.js';
if (is_file($swPath)) {
    readfile($swPath);
} else {
    // Minimal fallback service worker
    echo "// KSR Guru Ji Service Worker\n";
    echo "const CACHE_NAME = 'ksr-v1';\n";
    echo "self.addEventListener('install', e => self.skipWaiting());\n";
    echo "self.addEventListener('activate', e => self.clients.claim());\n";
    echo "self.addEventListener('fetch', e => e.respondWith(fetch(e.request).catch(() => caches.match(e.request))));\n";
}
