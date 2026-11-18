<?php
// ============================================================
// downloads/index.php — KSR Guru Ji App Downloads Center
// ============================================================
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$apkSize = file_exists(__DIR__ . '/ksr-guru-ji.apk') ? round(filesize(__DIR__ . '/ksr-guru-ji.apk') / 1024, 1) . ' KB' : '86 KB';
$aabSize = file_exists(__DIR__ . '/ksr-guru-ji.aab') ? round(filesize(__DIR__ . '/ksr-guru-ji.aab') / 1024, 1) . ' KB' : '86 KB';
$iosSize = file_exists(__DIR__ . '/ksr-guru-ji.mobileconfig') ? round(filesize(__DIR__ . '/ksr-guru-ji.mobileconfig') / 1024, 1) . ' KB' : '87 KB';

$apkSha = file_exists(__DIR__ . '/ksr-guru-ji.apk') ? hash_file('sha256', __DIR__ . '/ksr-guru-ji.apk') : 'Calculating...';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes" />
    <title>Download KSR Guru Ji App (APK, AAB & iOS) — ನಮ್ಮ ಅಂಗಡಿ</title>
    <meta name="description" content="Download the official KSR Guru Ji app for Android (.apk & .aab) and iOS. Fast grocery, medicine and parcel delivery in Gajendragad." />
    
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#0f7b3a" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png" />
    <link rel="apple-touch-icon" href="/icon-192.png" />
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/index.css?v=2.0" />
    <link rel="stylesheet" href="/mobile-app.css?v=3.0" />
    
    <style>
        .dl-hero {
            background: linear-gradient(135deg, #0f7b3a 0%, #1ea85a 60%, #0a5e2c 100%);
            color: #ffffff;
            padding: 50px 20px 70px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .dl-hero-icon {
            width: 90px;
            height: 90px;
            border-radius: 22px;
            object-fit: contain;
            margin: 0 auto 16px;
            background: #ffffff;
            padding: 6px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            display: block;
        }
        .dl-hero h1 {
            font-size: 2.1rem;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .dl-hero p {
            font-size: 0.95rem;
            opacity: 0.92;
            max-width: 580px;
            margin: 0 auto 14px;
        }
        .dl-badge-bar {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            background: rgba(255,255,255,0.15);
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
        }
        .dl-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 22px;
            margin: -35px auto 40px;
            max-width: 1100px;
            padding: 0 16px;
            position: relative;
            z-index: 2;
        }
        .dl-box {
            background: #ffffff;
            border: 1px solid rgba(15,123,58,0.12);
            border-radius: 20px;
            padding: 26px 22px;
            box-shadow: 0 8px 30px rgba(15,123,58,0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .dl-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 40px rgba(15,123,58,0.16);
        }
        .dl-box-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }
        .dl-box-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex-shrink: 0;
        }
        .dl-icon-android { background: #e8f5e9; color: #0f7b3a; }
        .dl-icon-apple   { background: #f1f5f9; color: #1e293b; }
        .dl-icon-pwa     { background: #fef3c7; color: #d97706; }
        .dl-icon-bundle  { background: #ede9fe; color: #6d28d9; }
        
        .dl-box-title h3 {
            font-size: 1.15rem;
            font-weight: 800;
            color: #122116;
            margin-bottom: 2px;
        }
        .dl-box-title span {
            font-size: 0.75rem;
            color: #5e6f63;
            font-weight: 600;
        }
        .dl-meta-list {
            margin: 14px 0 20px;
            padding: 0;
            list-style: none;
            font-size: 0.82rem;
            color: #4b5563;
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .dl-meta-list li {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dl-meta-list li i {
            color: #0f7b3a;
            width: 16px;
        }
        .dl-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            background: linear-gradient(135deg, #0f7b3a, #1ea85a);
            color: #ffffff !important;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.92rem;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(15,123,58,0.25);
            border: none;
            cursor: pointer;
            width: 100%;
        }
        .dl-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(15,123,58,0.35);
        }
        .dl-action-btn.secondary {
            background: #ffffff;
            color: #0f7b3a !important;
            border: 2px solid #0f7b3a;
            box-shadow: none;
            margin-top: 8px;
        }
        .dl-action-btn.secondary:hover {
            background: #f0fdf4;
        }
        
        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 24px;
            max-width: 1100px;
            margin: 0 auto 40px;
        }
        .info-card h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #122116;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .steps-flex {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 14px;
        }
        .step-col {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
        }
        .step-col strong {
            display: block;
            font-size: 0.9rem;
            color: #0f7b3a;
            margin-bottom: 6px;
        }
        .step-col p {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.5;
            margin: 0;
        }
        .checksum-box {
            margin-top: 18px;
            background: #0f172a;
            color: #cbd5e1;
            padding: 14px 18px;
            border-radius: 10px;
            font-family: monospace;
            font-size: 0.76rem;
            word-break: break-all;
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../global-header.php'; ?>

<!-- HERO -->
<section class="dl-hero">
    <img src="/company_logo.jpg" alt="KSR Guru Ji" class="dl-hero-icon" />
    <h1>Download KSR Guru Ji App</h1>
    <p>Get the complete shopping experience right on your phone. Order groceries, vegetables, bakery items, medicines & courier parcels anytime!</p>
    <div class="dl-badge-bar">
        <span><i class="fab fa-android"></i> Android APK</span> &bull;
        <span><i class="fab fa-google-play"></i> AAB Bundle</span> &bull;
        <span><i class="fab fa-apple"></i> iOS Profile</span> &bull;
        <span><i class="fas fa-globe"></i> PWA App</span>
    </div>
</section>

<!-- DOWNLOAD GRID -->
<div class="dl-grid">

    <!-- 1. Android APK -->
    <div class="dl-box">
        <div>
            <div class="dl-box-header">
                <div class="dl-box-icon dl-icon-android"><i class="fab fa-android"></i></div>
                <div class="dl-box-title">
                    <h3>Android APK File</h3>
                    <span>Direct Installer &bull; <?= $apkSize ?></span>
                </div>
            </div>
            <p style="font-size:0.83rem;color:#4b5563;line-height:1.5;">Install directly on any Android smartphone without needing Google Play Store. Full access to offline catalog and instant notifications.</p>
            <ul class="dl-meta-list">
                <li><i class="fas fa-check-circle"></i> Package: <code>com.ksrguruji.app</code></li>
                <li><i class="fas fa-check-circle"></i> Version: 3.0.0 (Build 300)</li>
                <li><i class="fas fa-check-circle"></i> Requires: Android 7.0 or higher</li>
                <li><i class="fas fa-check-circle"></i> 100% Free &amp; Virus-Scanned</li>
            </ul>
        </div>
        <div>
            <a href="/downloads/download.php?file=apk" class="dl-action-btn">
                <i class="fas fa-download"></i> Download APK File
            </a>
            <a href="#install-guide" class="dl-action-btn secondary">
                <i class="fas fa-book-open"></i> How to Install
            </a>
        </div>
    </div>

    <!-- 2. Android App Bundle (AAB) -->
    <div class="dl-box">
        <div>
            <div class="dl-box-header">
                <div class="dl-box-icon dl-icon-bundle"><i class="fab fa-google-play"></i></div>
                <div class="dl-box-title">
                    <h3>Android AAB Bundle</h3>
                    <span>Play Store Package &bull; <?= $aabSize ?></span>
                </div>
            </div>
            <p style="font-size:0.83rem;color:#4b5563;line-height:1.5;">Android App Bundle (.aab) format officially required for Google Play Console publication and optimized multi-device deployment.</p>
            <ul class="dl-meta-list">
                <li><i class="fas fa-check-circle"></i> Play Store Publishing Ready</li>
                <li><i class="fas fa-check-circle"></i> Adaptive device architecture</li>
                <li><i class="fas fa-check-circle"></i> Dynamic asset optimization</li>
                <li><i class="fas fa-check-circle"></i> Production Signed Release</li>
            </ul>
        </div>
        <div>
            <a href="/downloads/download.php?file=aab" class="dl-action-btn">
                <i class="fas fa-file-archive"></i> Download AAB Bundle
            </a>
            <a href="/APK_BUILD_README.md" target="_blank" class="dl-action-btn secondary">
                <i class="fas fa-code"></i> Build Documentation
            </a>
        </div>
    </div>

    <!-- 3. iOS Config Profile (Apple WebClip) -->
    <div class="dl-box">
        <div>
            <div class="dl-box-header">
                <div class="dl-box-icon dl-icon-apple"><i class="fab fa-apple"></i></div>
                <div class="dl-box-title">
                    <h3>Apple iOS Profile</h3>
                    <span>iPhone &amp; iPad &bull; <?= $iosSize ?></span>
                </div>
            </div>
            <p style="font-size:0.83rem;color:#4b5563;line-height:1.5;">Apple configuration profile (.mobileconfig) that installs the KSR Guru Ji WebClip app icon directly on your iPhone or iPad home screen.</p>
            <ul class="dl-meta-list">
                <li><i class="fas fa-check-circle"></i> Native Apple WebClip Profile</li>
                <li><i class="fas fa-check-circle"></i> Full-screen app mode (no browser bar)</li>
                <li><i class="fas fa-check-circle"></i> Retina display high-res icon</li>
                <li><i class="fas fa-check-circle"></i> Compatible with all iOS 14.0+</li>
            </ul>
        </div>
        <div>
            <a href="/downloads/download.php?file=mobileconfig" class="dl-action-btn">
                <i class="fab fa-apple"></i> Install on iPhone / iPad
            </a>
            <a href="javascript:void(0)" onclick="alert('On iPhone:\n1. Tap Install on iPhone\n2. Tap Allow in Safari\n3. Go to Settings -> Profile Downloaded -> Tap Install\nDone!')" class="dl-action-btn secondary">
                <i class="fas fa-question-circle"></i> iOS Setup Steps
            </a>
        </div>
    </div>

    <!-- 4. PWA Instant Install -->
    <div class="dl-box">
        <div>
            <div class="dl-box-header">
                <div class="dl-box-icon dl-icon-pwa"><i class="fas fa-bolt"></i></div>
                <div class="dl-box-title">
                    <h3>Instant PWA App</h3>
                    <span>Browser-based &bull; Zero Storage</span>
                </div>
            </div>
            <p style="font-size:0.83rem;color:#4b5563;line-height:1.5;">Progressive Web App that installs right from your Chrome, Edge, Safari, or Samsung Internet browser. Updates automatically!</p>
            <ul class="dl-meta-list">
                <li><i class="fas fa-check-circle"></i> Works offline with Service Worker</li>
                <li><i class="fas fa-check-circle"></i> Instant installation in 3 seconds</li>
                <li><i class="fas fa-check-circle"></i> Always runs the latest version</li>
                <li><i class="fas fa-check-circle"></i> Push notification support</li>
            </ul>
        </div>
        <div>
            <button id="pwaDirectInstallBtn" class="dl-action-btn">
                <i class="fas fa-plus-circle"></i> Install PWA Now
            </button>
            <a href="/index.php" class="dl-action-btn secondary">
                <i class="fas fa-store"></i> Open Web Shop
            </a>
        </div>
    </div>

    <!-- 5. QR Code Scan on Mobile -->
    <div class="dl-box" style="grid-column: 1 / -1; max-width: 640px; margin: 0 auto; width: 100%; text-align: center;">
        <div>
            <div class="dl-box-header" style="justify-content: center;">
                <div class="dl-box-icon dl-icon-android"><i class="fas fa-qrcode"></i></div>
                <div class="dl-box-title" style="text-align: left;">
                    <h3>Scan to Download on Mobile</h3>
                    <span>ಕ್ಯೂಆರ್ ಕೋಡ್ ಸ್ಕ್ಯಾನ್ ಮಾಡಿ (Scan QR Code)</span>
                </div>
            </div>
            <p style="font-size:0.85rem;color:#4b5563;line-height:1.5;margin-bottom:14px;">Point your smartphone camera at this QR code to open the download page directly on your phone!</p>
            <div style="margin: 0 auto 14px; width: 170px; height: 170px; padding: 10px; background: #ffffff; border: 2px dashed #0f7b3a; border-radius: 16px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
                <img src="/QR.png" alt="Scan QR Code to Download" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px;" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https%3A%2F%2Fksrguruji.com%2Fdownloads%2F'" />
            </div>
            <div style="font-size:0.8rem;color:#0f7b3a;font-weight:700;">
                <i class="fas fa-camera"></i> Works with any Camera or Google Lens
            </div>
        </div>
    </div>

</div>

<!-- INSTALL GUIDE SECTION -->
<div class="container" id="install-guide">
    <div class="info-card">
        <h4><i class="fas fa-circle-info" style="color:#0f7b3a;"></i> How to Install the APK on Your Android Phone (ಆಂಡ್ರಾಯ್ಡ್ ಇನ್‌ಸ್ಟಾಲ್ ಹಂತಗಳು)</h4>
        <div class="steps-flex">
            <div class="step-col">
                <strong>Step 1: Download (ಡೌನ್‌ಲೋಡ್)</strong>
                <p>Tap the <strong>Download APK File</strong> button above. The download will start automatically.</p>
            </div>
            <div class="step-col">
                <strong>Step 2: Open File (ತೆರೆಯಿರಿ)</strong>
                <p>When download completes, tap the notification or open your phone's <strong>Downloads</strong> folder and tap <code>KSR-Guru-Ji-v3.0.apk</code>.</p>
            </div>
            <div class="step-col">
                <strong>Step 3: Allow Install (ಅನುಮತಿಸಿ)</strong>
                <p>If prompted <em>"Install unknown apps"</em>, toggle <strong>Allow from this source</strong> (Chrome or File Manager).</p>
            </div>
            <div class="step-col">
                <strong>Step 4: Launch &amp; Enjoy (ಪ್ರಾರಂಭಿಸಿ)</strong>
                <p>Tap <strong>Install</strong>, then tap <strong>Open</strong>. The KSR Guru Ji app is now on your home screen!</p>
            </div>
        </div>

        <div class="checksum-box">
            <strong>SHA-256 Checksum (APK Verification):</strong><br />
            <?= htmlspecialchars($apkSha) ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../user-footer.php'; ?>

<script>
    // PWA install handler for the direct button
    document.getElementById('pwaDirectInstallBtn').addEventListener('click', function() {
        const pwaBtn = document.getElementById('pwaInstallBtn');
        if (pwaBtn) {
            pwaBtn.click();
        } else {
            alert('To install the KSR Guru Ji app:\n\n• On Chrome: Tap the 3 dots (⋮) at top right -> tap "Add to Home screen" or "Install App".\n• On Safari: Tap the Share button (📤) -> tap "Add to Home Screen".');
        }
    });
</script>

</body>
</html>
