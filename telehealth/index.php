<?php
session_start();
$pageTitle = "Telehealth – KSR GURU JI | ಆನ್ಲೈನ್ ಡಾಕ್ಟರ್ ಸಲಹೆ";
$waNumber = "919480000000"; // Replace with real WhatsApp number
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Talk to a doctor online. Book teleconsultation, lab tests, medicine delivery – KSR GURU JI Telehealth.">
  <title><?= htmlspecialchars($pageTitle) ?></title>

  <!-- Favicon -->
  <link rel="icon" href="../images/logo.png" type="image/png">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --primary: #0277bd;
      --primary-dark: #01579b;
      --accent: #1b5e20;
      --bg: #f0f4f8;
      --white: #ffffff;
      --text: #1a1a2e;
      --muted: #607d8b;
      --card-shadow: 0 2px 12px rgba(0,0,0,.10);
      --radius: 14px;
      --nav-h: 60px;
      --bottom-nav-h: 64px;
    }

    body {
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      padding-bottom: var(--bottom-nav-h);
    }

    /* ── TOP NAV ── */
    header {
      position: sticky; top: 0; z-index: 200;
      background: var(--primary);
      height: var(--nav-h);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,.2);
    }
    .logo-wrap { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .logo-wrap img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
    .logo-text { color: var(--white); font-weight: 700; font-size: 1rem; line-height: 1.1; }
    .logo-sub  { color: rgba(255,255,255,.75); font-size: .7rem; }
    .header-right a {
      color: var(--white);
      text-decoration: none;
      font-size: .85rem;
      background: rgba(255,255,255,.18);
      padding: 7px 14px;
      border-radius: 20px;
      font-weight: 600;
    }

    /* ── HERO ── */
    .hero {
      background: linear-gradient(135deg, var(--primary-dark) 0%, #0288d1 100%);
      color: var(--white);
      padding: 36px 20px 40px;
      text-align: center;
    }
    .hero-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,.18);
      border: 1px solid rgba(255,255,255,.35);
      border-radius: 20px;
      padding: 5px 14px;
      font-size: .78rem;
      font-weight: 600;
      margin-bottom: 16px;
      letter-spacing: .3px;
    }
    .hero h1 { font-size: 1.55rem; font-weight: 800; line-height: 1.25; margin-bottom: 6px; }
    .hero .kn { font-size: 1rem; opacity: .85; margin-bottom: 16px; }
    .hero p { font-size: .92rem; opacity: .88; max-width: 360px; margin: 0 auto 24px; line-height: 1.55; }
    .btn-hero {
      display: inline-block;
      background: #fff;
      color: var(--primary-dark);
      font-weight: 700;
      font-size: 1rem;
      padding: 14px 30px;
      border-radius: 30px;
      text-decoration: none;
      box-shadow: 0 4px 16px rgba(0,0,0,.18);
      transition: transform .15s, box-shadow .15s;
    }
    .btn-hero:active { transform: scale(.97); }
    .hero-stats {
      display: flex; justify-content: center; gap: 32px;
      margin-top: 24px;
    }
    .stat { text-align: center; }
    .stat-num { font-size: 1.3rem; font-weight: 800; }
    .stat-lbl { font-size: .72rem; opacity: .75; }

    /* ── SECTION TITLES ── */
    .section { padding: 24px 16px 8px; }
    .section-title {
      font-size: 1.05rem; font-weight: 700; color: var(--text);
      margin-bottom: 4px;
    }
    .section-sub { font-size: .8rem; color: var(--muted); margin-bottom: 16px; }

    /* ── SERVICES GRID ── */
    .services-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
      padding: 0 16px 8px;
    }
    .service-card {
      background: var(--white);
      border-radius: var(--radius);
      padding: 20px 14px;
      text-align: center;
      box-shadow: var(--card-shadow);
      text-decoration: none;
      color: var(--text);
      transition: transform .15s;
      display: flex; flex-direction: column; align-items: center; gap: 10px;
    }
    .service-card:active { transform: scale(.97); }
    .service-icon {
      width: 54px; height: 54px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem;
    }
    .service-card h3 { font-size: .88rem; font-weight: 700; line-height: 1.25; }
    .service-card p  { font-size: .74rem; color: var(--muted); line-height: 1.4; }

    .ic-blue   { background: #e3f2fd; color: #0277bd; }
    .ic-green  { background: #e8f5e9; color: #2e7d32; }
    .ic-orange { background: #fff3e0; color: #e65100; }
    .ic-purple { background: #f3e5f5; color: #6a1b9a; }

    /* ── DOCTOR CARDS ── */
    .doctors-row {
      display: flex; gap: 12px;
      overflow-x: auto; padding: 0 16px 16px;
      scrollbar-width: none;
    }
    .doctors-row::-webkit-scrollbar { display: none; }
    .doc-card {
      flex: 0 0 160px;
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--card-shadow);
      overflow: hidden;
      text-decoration: none;
      color: var(--text);
      transition: transform .15s;
    }
    .doc-card:active { transform: scale(.97); }
    .doc-avatar {
      width: 100%; height: 110px;
      display: flex; align-items: center; justify-content: center;
      font-size: 3.2rem;
      background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    }
    .doc-info { padding: 10px 12px 14px; }
    .doc-name { font-size: .85rem; font-weight: 700; margin-bottom: 2px; }
    .doc-spec { font-size: .72rem; color: var(--primary); font-weight: 600; }
    .doc-exp  { font-size: .7rem; color: var(--muted); margin: 3px 0; }
    .doc-fee  { font-size: .78rem; font-weight: 700; color: #2e7d32; }
    .doc-avail { font-size: .68rem; color: #388e3c; display: flex; align-items: center; gap: 3px; margin-top: 4px; }
    .dot { width: 7px; height: 7px; background: #4caf50; border-radius: 50%; display: inline-block; }

    /* ── HOW IT WORKS ── */
    .steps-row {
      display: flex; gap: 0;
      padding: 0 16px 16px;
      position: relative;
    }
    .step {
      flex: 1; text-align: center; position: relative; padding: 0 6px;
    }
    .step::after {
      content: ''; position: absolute; top: 20px; right: -1px;
      width: 50%; height: 2px; background: #bbdefb;
    }
    .step:last-child::after { display: none; }
    .step-num {
      width: 40px; height: 40px; border-radius: 50%;
      background: var(--primary); color: #fff;
      font-weight: 800; font-size: .95rem;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 8px;
    }
    .step-label { font-size: .72rem; color: var(--text); font-weight: 600; line-height: 1.3; }

    /* ── QUICK ACTIONS ── */
    .quick-actions {
      display: flex; gap: 10px;
      padding: 0 16px 24px;
    }
    .qa-btn {
      flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px;
      background: var(--white);
      border-radius: var(--radius);
      padding: 16px 8px;
      text-decoration: none; color: var(--text);
      box-shadow: var(--card-shadow);
      font-size: .78rem; font-weight: 600;
      text-align: center;
      transition: transform .15s;
    }
    .qa-btn:active { transform: scale(.96); }
    .qa-btn .qa-icon { font-size: 1.5rem; }

    /* ── BOTTOM NAV ── */
    .bottom-nav {
      position: fixed; bottom: 0; left: 0; right: 0;
      height: var(--bottom-nav-h);
      background: var(--white);
      border-top: 1px solid #e0e0e0;
      display: flex;
      box-shadow: 0 -2px 12px rgba(0,0,0,.08);
      z-index: 300;
    }
    .bnav-item {
      flex: 1; display: flex; flex-direction: column; align-items: center;
      justify-content: center; gap: 3px;
      text-decoration: none; color: var(--muted);
      font-size: .68rem; font-weight: 600;
      transition: color .15s;
      -webkit-tap-highlight-color: transparent;
    }
    .bnav-item .ico { font-size: 1.3rem; }
    .bnav-item.active { color: var(--primary); }

    /* ── UTILITY ── */
    @media (min-width: 480px) {
      .services-grid { grid-template-columns: repeat(4, 1fr); }
      .hero h1 { font-size: 2rem; }
    }
    @media (min-width: 768px) {
      body { padding-bottom: 0; }
      .bottom-nav { display: none; }
      .services-grid { max-width: 700px; margin: auto; }
      .doctors-row { max-width: 760px; margin: auto; padding-left: 0; padding-right: 0; justify-content: center; flex-wrap: wrap; overflow: visible; }
      .doc-card { flex: 0 0 180px; }
    }
  </style>
</head>
<body>

<!-- ── HEADER ── -->
<header>
  <a class="logo-wrap" href="../index.php">
    <img src="../images/logo.png" alt="KSR GURU JI" onerror="this.style.display='none'">
    <div>
      <div class="logo-text">KSR GURU JI</div>
      <div class="logo-sub">🩺 Telehealth</div>
    </div>
  </a>
  <a href="appointment.php">📅 Book Now</a>
</header>

<!-- ── HERO ── -->
<section class="hero">
  <div class="hero-badge">🟢 Doctors Available Now</div>
  <h1>Talk to a Doctor Online</h1>
  <p class="kn">ಆನ್ಲೈನ್ ಡಾಕ್ಟರ್ ಸಲಹೆ ಪಡೆಯಿರಿ</p>
  <p>Get expert medical advice from certified doctors – from the comfort of your home. Available 7 days, morning to night.</p>
  <a class="btn-hero" href="appointment.php">📅 Book Consultation</a>

  <div class="hero-stats">
    <div class="stat"><div class="stat-num">50+</div><div class="stat-lbl">Doctors</div></div>
    <div class="stat"><div class="stat-num">24/7</div><div class="stat-lbl">Support</div></div>
    <div class="stat"><div class="stat-num">₹99</div><div class="stat-lbl">Starting</div></div>
  </div>
</section>

<!-- ── SERVICES ── -->
<div class="section">
  <div class="section-title">Our Services</div>
  <div class="section-sub">ನಮ್ಮ ಸೇವೆಗಳು</div>
</div>
<div class="services-grid">
  <a class="service-card" href="appointment.php?type=video">
    <div class="service-icon ic-blue">📹</div>
    <h3>Video Consultation</h3>
    <p>Face-to-face with a doctor via video call</p>
  </a>
  <a class="service-card" href="appointment.php?type=chat">
    <div class="service-icon ic-green">💬</div>
    <h3>Chat Consultation</h3>
    <p>Text-based advice – no camera needed</p>
  </a>
  <a class="service-card" href="appointment.php?type=lab">
    <div class="service-icon ic-orange">🔬</div>
    <h3>Lab Tests</h3>
    <p>Book blood tests & reports at home</p>
  </a>
  <a class="service-card" href="appointment.php?type=medicine">
    <div class="service-icon ic-purple">💊</div>
    <h3>Medicine Delivery</h3>
    <p>Medicines delivered to your doorstep</p>
  </a>
</div>

<!-- ── FEATURED DOCTORS ── -->
<div class="section" style="margin-top:16px;">
  <div class="section-title">Featured Doctors</div>
  <div class="section-sub">ತಜ್ಞ ವೈದ್ಯರು</div>
</div>
<div class="doctors-row">
  <a class="doc-card" href="doctors.php?spec=general">
    <div class="doc-avatar">👨‍⚕️</div>
    <div class="doc-info">
      <div class="doc-name">Dr. Ramesh Kumar</div>
      <div class="doc-spec">General Physician</div>
      <div class="doc-exp">12 yrs experience</div>
      <div class="doc-fee">₹149 / session</div>
      <div class="doc-avail"><span class="dot"></span> Available Now</div>
    </div>
  </a>
  <a class="doc-card" href="doctors.php?spec=ayurveda">
    <div class="doc-avatar">🌿</div>
    <div class="doc-info">
      <div class="doc-name">Dr. Lakshmi Devi</div>
      <div class="doc-spec">Ayurveda Specialist</div>
      <div class="doc-exp">8 yrs experience</div>
      <div class="doc-fee">₹199 / session</div>
      <div class="doc-avail"><span class="dot"></span> Available Now</div>
    </div>
  </a>
  <a class="doc-card" href="doctors.php?spec=child">
    <div class="doc-avatar">👶</div>
    <div class="doc-info">
      <div class="doc-name">Dr. Suresh Patel</div>
      <div class="doc-spec">Paediatrician</div>
      <div class="doc-exp">10 yrs experience</div>
      <div class="doc-fee">₹179 / session</div>
      <div class="doc-avail"><span class="dot"></span> Available Now</div>
    </div>
  </a>
  <a class="doc-card" href="doctors.php?spec=dental">
    <div class="doc-avatar">🦷</div>
    <div class="doc-info">
      <div class="doc-name">Dr. Priya Sharma</div>
      <div class="doc-spec">Dentist</div>
      <div class="doc-exp">6 yrs experience</div>
      <div class="doc-fee">₹129 / session</div>
      <div class="doc-avail"><span class="dot"></span> Available Now</div>
    </div>
  </a>
  <a class="doc-card" href="doctors.php?spec=skin">
    <div class="doc-avatar">🏥</div>
    <div class="doc-info">
      <div class="doc-name">Dr. Anita Rao</div>
      <div class="doc-spec">Dermatologist</div>
      <div class="doc-exp">9 yrs experience</div>
      <div class="doc-fee">₹249 / session</div>
      <div class="doc-avail"><span class="dot"></span> Available Now</div>
    </div>
  </a>
</div>

<!-- ── HOW IT WORKS ── -->
<div class="section" style="margin-top:8px;">
  <div class="section-title">How It Works</div>
  <div class="section-sub">ಹೇಗೆ ಕೆಲಸ ಮಾಡುತ್ತದೆ?</div>
</div>
<div class="steps-row">
  <div class="step">
    <div class="step-num">1</div>
    <div class="step-label">Choose Doctor</div>
  </div>
  <div class="step">
    <div class="step-num">2</div>
    <div class="step-label">Book Slot</div>
  </div>
  <div class="step">
    <div class="step-num">3</div>
    <div class="step-label">Consult Online</div>
  </div>
  <div class="step">
    <div class="step-num">4</div>
    <div class="step-label">Get Prescription</div>
  </div>
</div>

<!-- ── QUICK ACTIONS ── -->
<div class="section" style="margin-top:8px;">
  <div class="section-title">Quick Actions</div>
</div>
<div class="quick-actions">
  <a class="qa-btn" href="https://wa.me/<?= $waNumber ?>?text=Hello%2C%20I%20need%20a%20doctor%20consultation" target="_blank">
    <span class="qa-icon">💬</span>WhatsApp Doctor
  </a>
  <a class="qa-btn" href="doctors.php">
    <span class="qa-icon">👨‍⚕️</span>All Doctors
  </a>
  <a class="qa-btn" href="appointment.php">
    <span class="qa-icon">📅</span>Book Now
  </a>
  <a class="qa-btn" href="../index.php">
    <span class="qa-icon">🏠</span>Main Store
  </a>
</div>

<!-- ── BOTTOM NAV ── -->
<nav class="bottom-nav">
  <a class="bnav-item active" href="index.php">
    <span class="ico">🏥</span>Telehealth
  </a>
  <a class="bnav-item" href="doctors.php">
    <span class="ico">👨‍⚕️</span>Doctors
  </a>
  <a class="bnav-item" href="appointment.php">
    <span class="ico">📅</span>Book
  </a>
  <a class="bnav-item" href="../index.php">
    <span class="ico">🛒</span>Shop
  </a>
</nav>

</body>
</html>
