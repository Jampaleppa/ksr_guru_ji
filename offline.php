<?php
// ============================================================
// offline.php — Offline fallback page (PWA)
// ============================================================
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Offline – KSR Guru Ji</title>
  <link rel="manifest" href="/manifest.json" />
  <meta name="theme-color" content="#0f7b3a" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f3 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 24px;
      color: #122116;
    }
    .offline-icon {
      font-size: 5rem;
      color: #0f7b3a;
      margin-bottom: 24px;
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.1); opacity: 0.7; }
    }
    h1 { font-size: 2rem; font-weight: 800; margin-bottom: 12px; color: #0f7b3a; }
    p { font-size: 1rem; color: #5e6f63; max-width: 400px; line-height: 1.6; }
    .retry-btn {
      margin-top: 28px;
      background: linear-gradient(135deg, #0f7b3a, #1ea85a);
      color: white;
      border: none;
      padding: 14px 36px;
      border-radius: 50px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
      font-family: 'Poppins', sans-serif;
    }
    .retry-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,123,58,0.3); }
    .cached-links {
      margin-top: 36px;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      justify-content: center;
    }
    .cached-links a {
      background: white;
      border: 2px solid rgba(15,123,58,0.15);
      border-radius: 12px;
      padding: 10px 20px;
      font-weight: 600;
      font-size: 0.85rem;
      color: #0f7b3a;
      text-decoration: none;
      transition: all 0.2s;
    }
    .cached-links a:hover { background: #0f7b3a; color: white; }
    .logo { font-size: 1.5rem; font-weight: 800; color: #0f7b3a; margin-bottom: 8px; }
    .tagline { font-size: 0.8rem; color: #5e6f63; margin-bottom: 32px; }
  </style>
    <link rel="stylesheet" href="/mobile-app.css?v=3.0" />
</head>
<body>
  <div class="logo">🛒 KSR GURU JI</div>
  <div class="tagline">ನಮ್ಮ ಅಂಗಡಿ · Everything Your Family Needs</div>
  <i class="fas fa-wifi-slash offline-icon"></i>
  <h1>You're Offline</h1>
  <p>No internet connection. Please check your Wi-Fi or mobile data and try again. Some pages may still be available from cache.</p>
  <button class="retry-btn" onclick="window.location.reload()">
    <i class="fas fa-rotate-right"></i> Try Again
  </button>
  <div class="cached-links">
    <a href="/"><i class="fas fa-home"></i> Home</a>
    <a href="/cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
    <a href="/orders.php"><i class="fas fa-box"></i> Orders</a>
    <a href="/contact.php"><i class="fas fa-phone"></i> Contact</a>
  </div>

  <!-- Mobile Bottom Navigation -->
  <?php if (file_exists(__DIR__ . '/mobile-bottom-nav.php')) require_once __DIR__ . '/mobile-bottom-nav.php'; ?>
  <script src="/assets/js/mobile-app.js?v=3.0" defer></script>
</body>
</html>
