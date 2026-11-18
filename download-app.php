<?php
// ============================================================
// download-app.php — KSR Guru Ji Mobile App Download Page
// ============================================================
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$cartCount = !empty($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Download KSR Guru Ji App — ಅಪ್ ಡೌನ್‌ಲೋಡ್</title>
  <meta name="description" content="Download KSR Guru Ji mobile app for Android and iOS. Get fast grocery, medicine & household delivery at your fingertips." />
  <link rel="manifest" href="/manifest.json" />
  <meta name="theme-color" content="#0f7b3a" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <meta name="apple-mobile-web-app-title" content="KSR Guru Ji" />
  <link rel="shortcut icon" href="/favicon.ico" />
  <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png" />
  <link rel="apple-touch-icon" href="/icon-192.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/index.css?v=2.0" />
  <link rel="stylesheet" href="/mobile-app.css?v=3.0" />
  <style>
    .app-hero {
      background: linear-gradient(135deg, #0f7b3a 0%, #1ea85a 50%, #0a5e2c 100%);
      color: white;
      padding: 60px 24px 80px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .app-hero::before {
      content: '';
      position: absolute;
      top: -60px; right: -60px;
      width: 300px; height: 300px;
      background: rgba(255,255,255,0.07);
      border-radius: 50%;
    }
    .app-hero::after {
      content: '';
      position: absolute;
      bottom: -80px; left: -60px;
      width: 250px; height: 250px;
      background: rgba(255,255,255,0.05);
      border-radius: 50%;
    }
    .app-hero .app-icon {
      width: 100px; height: 100px;
      border-radius: 24px;
      object-fit: cover;
      margin: 0 auto 20px;
      display: block;
      box-shadow: 0 8px 32px rgba(0,0,0,0.25);
      position: relative; z-index: 1;
    }
    .app-hero h1 {
      font-size: 2.2rem; font-weight: 800;
      margin-bottom: 10px; position: relative; z-index: 1;
    }
    .app-hero .sub {
      font-size: 1rem; opacity: 0.9;
      max-width: 500px; margin: 0 auto 10px;
      position: relative; z-index: 1;
    }
    .app-hero .rating {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,0.15);
      border-radius: 50px; padding: 6px 16px;
      font-weight: 700; font-size: 0.85rem;
      margin: 12px 0; position: relative; z-index: 1;
    }
    .download-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      padding: 32px 24px;
      max-width: 900px;
      margin: -40px auto 0;
    }
    .dl-card {
      background: white;
      border-radius: 24px;
      padding: 28px 24px;
      box-shadow: 0 8px 40px rgba(15,123,58,0.12);
      border: 1px solid rgba(15,123,58,0.08);
      text-align: center;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .dl-card:hover { transform: translateY(-4px); box-shadow: 0 16px 48px rgba(15,123,58,0.2); }
    .dl-card .platform-icon {
      font-size: 3rem; margin-bottom: 14px;
    }
    .dl-card h3 { font-size: 1.3rem; font-weight: 800; margin-bottom: 8px; color: #122116; }
    .dl-card p { font-size: 0.85rem; color: #5e6f63; margin-bottom: 20px; line-height: 1.5; }
    .dl-card .primary-btn {
      display: inline-flex; align-items: center; gap: 10px;
      background: linear-gradient(135deg, #0f7b3a, #1ea85a);
      color: white; border: none;
      padding: 14px 28px; border-radius: 50px;
      font-size: 1rem; font-weight: 700;
      cursor: pointer; text-decoration: none;
      transition: transform 0.2s, box-shadow 0.2s;
      font-family: 'Poppins', sans-serif;
      width: 100%; justify-content: center;
      margin-bottom: 10px;
    }
    .dl-card .primary-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(15,123,58,0.35); }
    .dl-card .secondary-btn {
      display: inline-flex; align-items: center; gap: 8px;
      background: none; border: 2px solid rgba(15,123,58,0.2);
      color: #0f7b3a; padding: 10px 20px;
      border-radius: 50px; font-size: 0.85rem; font-weight: 600;
      cursor: pointer; text-decoration: none;
      transition: all 0.2s; font-family: 'Poppins', sans-serif;
      width: 100%; justify-content: center;
    }
    .dl-card .secondary-btn:hover { background: rgba(15,123,58,0.06); border-color: #0f7b3a; }
    .steps-section {
      background: #f8faf8; padding: 48px 24px;
    }
    .steps-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 24px; max-width: 900px; margin: 0 auto;
    }
    .step-item { text-align: center; }
    .step-num {
      width: 48px; height: 48px; border-radius: 50%;
      background: linear-gradient(135deg, #0f7b3a, #1ea85a);
      color: white; font-size: 1.3rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 12px;
    }
    .step-item h4 { font-size: 0.95rem; font-weight: 700; margin-bottom: 6px; }
    .step-item p { font-size: 0.8rem; color: #5e6f63; }
    .features-row {
      display: flex; flex-wrap: wrap; gap: 10px;
      justify-content: center; padding: 24px 24px 0;
    }
    .feat-chip {
      background: rgba(15,123,58,0.06); border: 1px solid rgba(15,123,58,0.15);
      border-radius: 50px; padding: 8px 18px;
      font-size: 0.8rem; font-weight: 600; color: #0f7b3a;
      display: inline-flex; align-items: center; gap: 6px;
    }
    @media (max-width: 480px) {
      .app-hero h1 { font-size: 1.6rem; }
      .download-cards { padding: 24px 16px; margin-top: -30px; }
      .steps-section { padding: 32px 16px; }
    }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/global-header.php'; ?>

<!-- HERO -->
<section class="app-hero">
  <img src="/icon-512.png" class="app-icon" alt="KSR Guru Ji App Icon" onerror="this.src='/company_logo.jpg'" />
  <h1>📱 KSR Guru Ji App</h1>
  <p class="sub">Groceries, Medicines & More — Delivered Fast to Your Doorstep in Gajendragad</p>
  <div class="rating">⭐ 4.8 &nbsp;|&nbsp; <i class="fas fa-download"></i> 5000+ Downloads &nbsp;|&nbsp; Free</div>
</section>

<!-- DOWNLOAD CARDS -->
<div class="download-cards container">

  <!-- Android App (APK & AAB) -->
  <div class="dl-card">
    <div class="platform-icon">🤖</div>
    <h3>Android App (APK &amp; AAB)</h3>
    <p>Download the official Android APK file directly or install as PWA from Chrome without Google Play!</p>
    <a href="/downloads/download.php?file=apk" class="primary-btn">
      <i class="fas fa-download"></i> Download APK File (.apk)
    </a>
    <a href="/downloads/download.php?file=aab" class="secondary-btn" style="margin-bottom:8px;">
      <i class="fas fa-file-archive"></i> Download AAB Bundle (.aab)
    </a>
    <button class="secondary-btn" id="androidInstallBtn">
      <i class="fab fa-android"></i> Or Install via Browser PWA
    </button>
  </div>

  <!-- iOS App (MobileConfig & Safari PWA) -->
  <div class="dl-card">
    <div class="platform-icon">🍎</div>
    <h3>iPhone / iPad App</h3>
    <p>Install the Apple WebClip profile for full-screen mode, or add directly to Home Screen via Safari!</p>
    <a href="/downloads/download.php?file=mobileconfig" class="primary-btn">
      <i class="fab fa-apple"></i> Download iOS Profile (.mobileconfig)
    </a>
    <a href="javascript:void(0)" class="secondary-btn" id="iosInstallBtn" onclick="showIosInstructions()">
      <i class="fas fa-arrow-up-from-bracket"></i> Add via Safari Share Sheet
    </a>
  </div>

  <!-- WhatsApp & Direct Order -->
  <div class="dl-card">
    <div class="platform-icon">💬</div>
    <h3>Order via WhatsApp</h3>
    <p>No app needed! Send us your grocery or medicine list on WhatsApp and we'll deliver in 45 minutes.</p>
    <a href="https://wa.me/918088584421?text=Hi+KSR+GURU+JI%2C+I+want+to+place+an+order%21"
       target="_blank" class="primary-btn">
      <i class="fab fa-whatsapp"></i> Chat on WhatsApp
    </a>
    <a href="/downloads/index.php" class="secondary-btn">
      <i class="fas fa-boxes-packing"></i> All Download Packages
    </a>
  </div>

</div>

<!-- APP FEATURES -->
<div class="features-row container">
  <span class="feat-chip"><i class="fas fa-bolt"></i> Fast 45-min Delivery</span>
  <span class="feat-chip"><i class="fas fa-wifi-slash"></i> Works Offline</span>
  <span class="feat-chip"><i class="fas fa-bell"></i> Push Notifications</span>
  <span class="feat-chip"><i class="fas fa-moon"></i> Dark Mode</span>
  <span class="feat-chip"><i class="fas fa-stethoscope"></i> Doctor Assist</span>
  <span class="feat-chip"><i class="fas fa-box"></i> Order Tracking</span>
  <span class="feat-chip"><i class="fas fa-tags"></i> Best Prices</span>
  <span class="feat-chip"><i class="fas fa-heart"></i> Wishlist</span>
  <span class="feat-chip"><i class="fas fa-language"></i> Kannada / English</span>
</div>

<!-- ANDROID STEPS -->
<section class="steps-section" id="android-steps" style="margin-top: 40px;">
  <div style="max-width:900px;margin:0 auto;text-align:center;margin-bottom:28px;">
    <h2 style="font-size:1.5rem;font-weight:800;color:#122116;">🤖 Install on Android — 3 Easy Steps</h2>
  </div>
  <div class="steps-grid">
    <div class="step-item">
      <div class="step-num">1</div>
      <h4>Open Chrome</h4>
      <p>Open <strong>Chrome browser</strong> on your Android phone and go to <strong>ksrguruji.com</strong></p>
    </div>
    <div class="step-item">
      <div class="step-num">2</div>
      <h4>Tap Install Banner</h4>
      <p>A banner saying <strong>"Install KSR Guru Ji"</strong> will appear. Tap <strong>Install</strong></p>
    </div>
    <div class="step-item">
      <div class="step-num">3</div>
      <h4>Done! Open App</h4>
      <p>The app appears on your <strong>Home Screen</strong>. Tap it to open — works like any app!</p>
    </div>
  </div>
  <div style="text-align:center;margin-top:20px;font-size:0.82rem;color:#5e6f63;">
    <i class="fas fa-info-circle"></i> No Play Store required. No storage space wasted. Free forever.
  </div>
</section>

<!-- iOS STEPS -->
<section class="steps-section" id="ios-steps">
  <div style="max-width:900px;margin:0 auto;text-align:center;margin-bottom:28px;">
    <h2 style="font-size:1.5rem;font-weight:800;color:#122116;">🍎 Install on iPhone — 3 Easy Steps</h2>
  </div>
  <div class="steps-grid">
    <div class="step-item">
      <div class="step-num">1</div>
      <h4>Open Safari</h4>
      <p>Open <strong>Safari browser</strong> (must be Safari!) and go to <strong>ksrguruji.com</strong></p>
    </div>
    <div class="step-item">
      <div class="step-num">2</div>
      <h4>Tap Share 📤</h4>
      <p>Tap the <strong>Share button</strong> (box with arrow) at the bottom of Safari</p>
    </div>
    <div class="step-item">
      <div class="step-num">3</div>
      <h4>Add to Home Screen</h4>
      <p>Scroll down and tap <strong>"Add to Home Screen"</strong> → tap <strong>Add</strong>. Done!</p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/user-footer.php'; ?>

<script>
  // Android PWA install
  let _prompt = null;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    _prompt = e;
    const btn = document.getElementById('androidInstallBtn');
    if (btn) btn.style.animation = 'none';
  });
  document.getElementById('androidInstallBtn').addEventListener('click', async () => {
    if (_prompt) {
      _prompt.prompt();
      const { outcome } = await _prompt.userChoice;
      if (outcome === 'accepted') {
        document.getElementById('androidInstallBtn').textContent = '✅ App Installed!';
      }
      _prompt = null;
    } else {
      alert('Open this page in Chrome on Android to install the app!\n\nURL: https://ksrguruji.com/download-app.php');
    }
  });

  function showIosInstructions() {
    alert('On iPhone:\n1. Open this page in Safari\n2. Tap the Share button (📤)\n3. Scroll and tap "Add to Home Screen"\n4. Tap Add — Done!');
  }
</script>
</body>
</html>
