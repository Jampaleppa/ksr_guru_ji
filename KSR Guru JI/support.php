<?php
// ============================================================
// support.php - Support, Terms & Privacy (Dynamic)
// ============================================================
require_once 'includes/config.php';
require_once __DIR__ . '/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes" />
  <title>KSR GURU JI · Support, Terms &amp; Privacy</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <style>
    /* ----- reset & base (merged) ----- */
    * { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
      --primary-dark: #1b5e5a;
      --primary: #2c7873;
      --primary-light: #e0f2f0;
      --accent-coral: #ff7e5f;
      --accent-peach: #feb47b;
      --gray-light: #f8fafc;
      --gray-mid: #eef2f6;
      --text-dark: #1e2b37;
      --text-soft: #2c3e4f;
      --white: #ffffff;
      --shadow-sm: 0 8px 20px rgba(0, 40, 40, 0.06);
      --shadow-hover: 0 16px 30px rgba(0, 70, 60, 0.12);
      --border-radius-card: 24px;
      --border-radius-block: 20px;
      --transition: all 0.2s ease;
      --ai-accent: #6c4f9c;
      --secondary: #FF7F32;
      --secondary-hover: #E65C00;
      --text-muted: #4A5B6E;
      --border-light: #e9edf2;
    }

    body {
      font-family: 'Inter', 'Poppins', sans-serif;
      background-color: var(--gray-light);
      color: var(--text-dark);
      line-height: 1.5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 24px;
      width: 100%;
    }

    /* ----- header (common) ----- */
    .main-header {
      background: linear-gradient(145deg, var(--primary-dark) 0%, var(--primary) 100%);
      color: white;
      padding: 1.6rem 0 1.4rem;
      border-radius: 0 0 32px 32px;
      box-shadow: 0 6px 18px rgba(27, 94, 90, 0.25);
    }

    .logo {
      font-size: 2.4rem;
      font-weight: 700;
      line-height: 1.1;
      letter-spacing: -0.02em;
    }
    .logo span { font-weight: 300; opacity: 0.9; }
    .tagline {
      font-size: 1.2rem;
      font-weight: 350;
      opacity: 0.9;
      border-left: 3px solid rgba(255,255,255,0.5);
      padding-left: 1rem;
      margin-top: 0.25rem;
    }

    /* ----- view toggle bar (now 3 buttons) ----- */
    .view-toggle-bar {
      background: #fff;
      border-radius: 12px;
      padding: 6px;
      display: inline-flex;
      gap: 4px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
      margin: 2rem 0 1.5rem;
      flex-wrap: wrap;
    }
    .view-toggle-bar button {
      background: transparent;
      border: none;
      padding: 10px 24px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      transition: 0.25s;
      color: #64748b;
      display: flex;
      align-items: center;
      gap: 8px;
      font-family: 'Inter', sans-serif;
    }
    .view-toggle-bar button.active {
      background: var(--primary-dark);
      color: #fff;
      box-shadow: 0 4px 12px rgba(27, 94, 90, 0.25);
    }
    .view-toggle-bar button i { font-size: 16px; }

    .view-section { display: none; animation: fadeUp 0.3s ease; }
    .view-section.active { display: block; }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ----- SUPPORT VIEW (from support.html) ----- */
    .support-hero {
      text-align: center;
      padding: 2.5rem 1.5rem;
      background: var(--white);
      margin: 1rem 0 2.5rem;
      border-radius: var(--border-radius-block);
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(44, 120, 115, 0.1);
    }
    .support-hero h1 {
      color: var(--primary-dark);
      font-size: 2.2rem;
      margin-bottom: 0.75rem;
    }
    .support-hero p {
      font-size: 1.2rem;
      color: var(--text-soft);
      max-width: 750px;
      margin: 0 auto;
    }

    .emergency-banner {
      background: linear-gradient(125deg, var(--accent-coral), var(--accent-peach));
      color: white;
      padding: 2rem 1.8rem;
      border-radius: 32px;
      text-align: center;
      margin: 2rem 0 2.5rem;
      box-shadow: 0 18px 30px -8px rgba(255, 126, 95, 0.4);
      border: 1px solid rgba(255,255,255,0.2);
    }
    .emergency-banner h2 {
      font-size: 2rem;
      font-weight: 650;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .emergency-phone {
      font-size: 2.6rem;
      font-weight: 800;
      letter-spacing: -0.02em;
      background: rgba(255,255,255,0.15);
      backdrop-filter: blur(8px);
      display: inline-block;
      padding: 0.3rem 1.8rem;
      border-radius: 60px;
      margin: 1rem 0 0.5rem;
      border: 2px solid white;
      color: white;
      text-decoration: none;
      transition: var(--transition);
    }
    .emergency-phone:hover {
      background: white;
      color: var(--accent-coral);
      transform: scale(1.02);
      outline: 3px solid rgba(255,255,255,0.5);
    }
    .info-note {
      font-size: 0.95rem;
      opacity: 0.9;
      margin-top: 0.75rem;
      font-style: italic;
    }

    .contact-methods {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 2rem;
      margin: 3rem 0;
    }
    .contact-card {
      background: var(--white);
      padding: 2.2rem 1.5rem;
      border-radius: var(--border-radius-card);
      text-align: center;
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
      border: 1px solid rgba(0,0,0,0.02);
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .contact-card:hover {
      transform: translateY(-6px);
      box-shadow: var(--shadow-hover);
      border-color: rgba(44, 120, 115, 0.2);
    }
    .contact-icon { font-size: 3.8rem; line-height: 1; margin-bottom: 1rem; }
    .contact-card h3 {
      color: var(--primary-dark);
      font-size: 1.8rem;
      font-weight: 600;
      margin-bottom: 0.75rem;
    }
    .phone-number, .email-link {
      font-weight: 700;
      text-decoration: none;
      transition: var(--transition);
      display: inline-block;
      margin: 0.25rem 0;
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-size: 1.8rem;
      word-break: break-word;
    }
    .phone-number {
      color: #d54d4d;
      background: rgba(213, 77, 77, 0.05);
      border: 1px solid rgba(213,77,77,0.2);
      width: fit-content;
      margin-left: auto;
      margin-right: auto;
    }
    .phone-number:hover {
      background: #d54d4d;
      color: white;
      border-color: #d54d4d;
    }
    .email-link {
      color: var(--primary);
      font-size: 1.4rem;
      background: rgba(44, 120, 115, 0.06);
      border: 1px solid rgba(44,120,115,0.2);
      padding: 0.5rem 1.2rem;
    }
    .email-link:hover {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }
    .contact-card p { color: var(--text-soft); margin: 0.5rem 0; font-size: 1rem; }
    .contact-card .info-note { color: var(--text-soft); opacity: 0.7; font-size: 0.9rem; }

    /* AI suggestions */
    .ai-suggestions {
      background: linear-gradient(145deg, #f9f5ff, #ffffff);
      border: 2px solid rgba(108, 79, 156, 0.15);
      border-radius: 40px;
      padding: 2.5rem 2rem;
      margin: 3rem 0;
      box-shadow: var(--shadow-sm);
      position: relative;
      overflow: hidden;
    }
    .ai-suggestions::before {
      content: "✨";
      position: absolute;
      font-size: 8rem;
      bottom: -10px;
      right: 10px;
      opacity: 0.1;
      transform: rotate(15deg);
      pointer-events: none;
    }
    .ai-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
    }
    .ai-header h2 {
      color: var(--ai-accent);
      font-size: 2.2rem;
      font-weight: 700;
      margin: 0;
    }
    .ai-header span { font-size: 2.8rem; }
    .ai-sub {
      font-size: 1.2rem;
      color: var(--text-soft);
      max-width: 700px;
      margin-bottom: 2rem;
      background: rgba(108, 79, 156, 0.04);
      padding: 1rem 1.5rem;
      border-radius: 60px;
      border-left: 5px solid var(--ai-accent);
    }
    .ai-input-group {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      max-width: 800px;
      margin: 0 auto 2rem auto;
    }
    .ai-input-group label {
      font-weight: 600;
      color: var(--primary-dark);
      font-size: 1.2rem;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .ai-row {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
    }
    #aiProblemInput {
      flex: 1 1 280px;
      padding: 1.2rem 1.5rem;
      border: 2px solid var(--gray-mid);
      border-radius: 60px;
      font-size: 1.1rem;
      font-family: 'Inter', sans-serif;
      background: white;
      transition: var(--transition);
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }
    #aiProblemInput:focus {
      border-color: var(--ai-accent);
      outline: none;
      box-shadow: 0 0 0 4px rgba(108, 79, 156, 0.2);
    }
    #aiGetSuggestionBtn {
      background: var(--ai-accent);
      color: white;
      border: none;
      padding: 1.2rem 2.5rem;
      border-radius: 60px;
      font-size: 1.2rem;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 8px 16px rgba(108, 79, 156, 0.25);
      border: 1px solid rgba(255,255,255,0.3);
      white-space: nowrap;
    }
    #aiGetSuggestionBtn:hover {
      background: #5a3e84;
      transform: scale(1.02);
      box-shadow: 0 14px 22px rgba(108, 79, 156, 0.35);
    }
    .ai-suggestion-card {
      background: white;
      border-radius: 32px;
      padding: 2rem;
      margin-top: 2rem;
      border: 1px solid rgba(0,0,0,0.05);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
      border-left: 8px solid var(--ai-accent);
      opacity: 0;
      transform: translateY(10px);
      animation: fadeSlideIn 0.3s ease forwards;
    }
    @keyframes fadeSlideIn { to { opacity: 1; transform: translateY(0); } }
    .suggestion-head {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      font-size: 1.5rem;
      font-weight: 650;
      color: var(--ai-accent);
      margin-bottom: 1.2rem;
    }
    .suggestion-text {
      font-size: 1.2rem;
      color: var(--text-dark);
      background: var(--gray-light);
      padding: 1.5rem;
      border-radius: 24px;
      line-height: 1.6;
      border: 1px dashed var(--gray-mid);
    }
    .suggestion-text strong { color: var(--primary-dark); }
    .ai-footer-note {
      margin-top: 1.5rem;
      font-size: 0.95rem;
      color: var(--text-soft);
      opacity: 0.7;
      display: flex;
      align-items: center;
      gap: 0.3rem;
      justify-content: flex-end;
    }

    .service-hours {
      background: var(--white);
      padding: 2.2rem 2rem;
      border-radius: 32px;
      margin: 2rem 0 3rem;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(44,120,115,0.12);
      text-align: center;
    }
    .service-hours h3 {
      color: var(--primary-dark);
      font-size: 2rem;
      margin-bottom: 1.5rem;
      font-weight: 600;
    }
    .service-hours p {
      font-size: 1.15rem;
      padding: 0.6rem 0;
      border-bottom: 1px dashed var(--gray-mid);
      max-width: 550px;
      margin: 0 auto;
    }
    .service-hours p:last-child { border-bottom: none; }

    /* ----- TERMS VIEW (from terms and condition.html) ----- */
    .terms-hero {
      background: linear-gradient(135deg, #e9f7eb 0%, #ffffff 100%);
      padding: 3rem 0;
      text-align: center;
      border-radius: var(--border-radius-block);
      margin: 1rem 0 2rem;
    }
    .terms-hero h1 {
      font-size: 2.5rem;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 1rem;
    }
    .terms-hero p {
      font-size: 1.1rem;
      color: var(--text-muted);
      max-width: 700px;
      margin: 0 auto;
    }

    .terms-content {
      background: var(--white);
      border-radius: var(--border-radius-card);
      box-shadow: var(--shadow-sm);
      padding: 2.5rem;
      margin: 2rem 0 3rem;
    }
    .terms-content h2 {
      color: var(--primary);
      font-size: 1.5rem;
      margin-top: 1.8rem;
      margin-bottom: 1rem;
      border-left: 4px solid var(--secondary);
      padding-left: 1rem;
    }
    .terms-content h2:first-of-type { margin-top: 0; }
    .terms-content p, .terms-content ul {
      margin-bottom: 1rem;
      color: var(--text-muted);
    }
    .terms-content ul { padding-left: 2rem; }
    .terms-content li { margin-bottom: 0.5rem; }
    .last-updated {
      font-style: italic;
      color: #6c757d;
      margin-top: 2rem;
      border-top: 1px solid var(--border-light);
      padding-top: 1.5rem;
    }

    /* ----- PRIVACY VIEW (from privacy-policy.html, adapted) ----- */
    .privacy-hero {
      background: linear-gradient(135deg, #e0f2f0 0%, #ffffff 100%);
      padding: 3rem 0;
      text-align: center;
      border-radius: var(--border-radius-block);
      margin: 1rem 0 2rem;
    }
    .privacy-hero h1 {
      font-size: 2.5rem;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 1rem;
    }
    .privacy-hero p {
      font-size: 1.1rem;
      color: var(--text-muted);
      max-width: 700px;
      margin: 0 auto;
    }

    .privacy-content {
      background: var(--white);
      border-radius: var(--border-radius-card);
      box-shadow: var(--shadow-sm);
      padding: 2.5rem;
      margin: 2rem 0 3rem;
    }
    .privacy-content h2 {
      color: var(--primary);
      font-size: 1.5rem;
      margin-top: 1.8rem;
      margin-bottom: 1rem;
      border-left: 4px solid var(--secondary);
      padding-left: 1rem;
    }
    .privacy-content h2:first-of-type { margin-top: 0; }
    .privacy-content p, .privacy-content ul {
      margin-bottom: 1rem;
      color: var(--text-muted);
    }
    .privacy-content ul { padding-left: 2rem; }
    .privacy-content li { margin-bottom: 0.5rem; }

    /* ----- footer (common, enhanced) ----- */
    .main-footer {
      background: #0f172a;
      color: #cbd5e1;
      padding: 3rem 0 1.5rem;
      margin-top: 2rem;
      border-radius: 40px 40px 0 0;
    }
    .main-footer a { color: #94a3b8; text-decoration: none; transition: 0.2s; }
    .main-footer a:hover { color: var(--secondary); }
    .footer-content {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 2rem;
      margin-bottom: 2rem;
    }
    .footer-section h3, .footer-section h4 {
      color: white;
      margin-bottom: 1rem;
      font-weight: 600;
    }
    .footer-section ul { list-style: none; padding: 0; }
    .footer-section ul li { margin-bottom: 0.5rem; }
    .footer-section ul li i { margin-right: 8px; width: 18px; }
    .social-links {
      display: flex;
      gap: 1rem;
      margin-top: 1rem;
    }
    .social-links a {
      background: rgba(255,255,255,0.1);
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: 0.2s;
    }
    .social-links a:hover {
      background: var(--secondary);
      color: white;
    }
    .footer-bottom {
      text-align: center;
      border-top: 1px solid #2d3a4e;
      padding-top: 1.5rem;
      font-size: 0.85rem;
    }
    .footer-bottom a { color: #94a3b8; }
    .footer-bottom a:hover { color: #fff; }

    /* ----- responsive ----- */
    @media (max-width: 768px) {
      .container { padding: 0 16px; }
      .logo { font-size: 2rem; }
      .tagline { font-size: 1rem; }
      .view-toggle-bar { width: 100%; justify-content: center; }
      .view-toggle-bar button { padding: 8px 16px; font-size: 13px; }
      .support-hero h1 { font-size: 1.9rem; }
      .emergency-banner h2 { font-size: 1.6rem; flex-direction: column; }
      .emergency-phone { font-size: 1.9rem; }
      .phone-number { font-size: 1.5rem; }
      .email-link { font-size: 1.2rem; }
      .ai-header h2 { font-size: 1.8rem; }
      .ai-sub { font-size: 1rem; }
      .ai-row { flex-direction: column; }
      #aiGetSuggestionBtn { justify-content: center; width: 100%; }
      .suggestion-text { font-size: 1rem; }
      .terms-hero h1 { font-size: 1.8rem; }
      .privacy-hero h1 { font-size: 1.8rem; }
      .terms-content, .privacy-content { padding: 1.5rem; }
      .footer-content { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 480px) {
      .footer-content { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- ===== HEADER ===== -->
<header class="main-header" style="background: linear-gradient(135deg, #0b1f2e, #1b3a4f); border-radius: 0 0 32px 32px; padding: 20px 0; box-shadow: 0 6px 18px rgba(11, 31, 46, 0.25);">
  <div class="container" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
    <div class="logo-container" onclick="window.location.href='index.php'" style="cursor: pointer; display: flex; align-items: center; gap: 12px;">
      <div class="logo-image" style="width: 48px; height: 48px; background: linear-gradient(135deg, #0f7b3a, #1ea85a); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(15, 123, 58, 0.15); flex-shrink: 0; border: 1.5px solid rgba(255, 255, 255, 0.8);">
        <img src="company_logo.jpg" alt="KSR GURU JI Logo" style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;" onerror="this.outerHTML='🛒';" />
      </div>
      <div class="logo-text">
        <span class="logo-brand" style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 800; color: #ffffff; display: block; line-height: 1.1;">KSR GURU JI</span>
        <span class="logo-tagline" style="font-family: 'Inter', sans-serif; font-size: 0.75rem; color: rgba(255,255,255,0.85); font-weight: 500; display: block; line-height: 1.2;">Everything Your Family Needs, Every Day</span>
      </div>
    </div>
    <a href="index.php" style="color: white; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.1); padding: 8px 16px; border-radius: 20px; font-size: 0.9rem;" class="nav-back-home"><i class="fas fa-home"></i> Back to Shop</a>
  </div>
</header>

<main>
  <div class="container">

    <!-- ===== VIEW TOGGLE (3 tabs) ===== -->
    <div class="view-toggle-bar">
      <button class="active" data-view="support" id="toggleSupport">
        <i class="fas fa-headset"></i> Support
      </button>
      <button data-view="terms" id="toggleTerms">
        <i class="fas fa-file-contract"></i> Terms
      </button>
      <button data-view="privacy" id="togglePrivacy">
        <i class="fas fa-shield-alt"></i> Privacy
      </button>
    </div>

    <!-- ============================================================= -->
    <!-- VIEW: SUPPORT -->
    <!-- ============================================================= -->
    <div class="view-section active" id="viewSupport">

      <!-- hero -->
      <section class="support-hero">
        <h1>💬 We’re here to help + AI suggestions</h1>
        <p>Our customer support team is available 24/7, and now our AI assistant gives instant suggestions for any problem.</p>
      </section>

      <!-- emergency banner -->
      <section class="emergency-banner">
        <h2><span>🚨</span> 60‑minute emergency medicine</h2>
        <p style="font-size:1.2rem; margin-top:0.2rem;">Urgent need? Call now — we deliver fast</p>
        <a href="tel:8088584421" class="emergency-phone">📞 8088584421</a>
        <p class="info-note">*Terms & area restrictions apply. Live operators 24/7.</p>
      </section>

      <!-- contact cards -->
      <div class="contact-methods">
        <article class="contact-card">
          <div class="contact-icon">📞</div>
          <h3>Call support</h3>
          <p>Speak directly with our team — 24 hours</p>
          <a href="tel:8088584421" class="phone-number">8088584421</a>
          <a href="tel:8762474406" class="phone-number">8762474406</a>
          <p class="info-note">toll‑free? just call, we're here</p>
        </article>

        <article class="contact-card">
          <div class="contact-icon">✉️</div>
          <h3>Email support</h3>
          <p>For detailed inquiries &amp; documents</p>
          <a href="mailto:support@ksrguru.com" class="email-link">support@ksrguru.com</a>
          <p class="info-note">typically replies within 2 hours</p>
        </article>

        <article class="contact-card">
          <div class="contact-icon">💬</div>
          <h3>In‑app chat</h3>
          <p>Chat live via KSR GURU JI app</p>
          <p style="font-weight:600; background:var(--primary-light); padding:0.5rem 1rem; border-radius:30px; color:var(--primary-dark); margin-top:0.5rem;">💚 24/7 • fastest response</p>
          <p class="info-note">track orders • get instant help</p>
        </article>
      </div>

      <!-- AI suggestion -->
      <section class="ai-suggestions" id="aiSuggestionSection">
        <div class="ai-header">
          <span>🤖</span>
          <h2>AI suggestion engine</h2>
        </div>
        <div class="ai-sub">
          ⚡ Every customer problem gets an instant AI suggestion — just describe your issue.
        </div>

        <div class="ai-input-group">
          <label for="aiProblemInput"><span>🔍</span> What seems to be the problem?</label>
          <div class="ai-row">
            <input type="text" id="aiProblemInput" placeholder="e.g., delivery delay, wrong item, return, medicine not available..." />
            <button id="aiGetSuggestionBtn" aria-label="get AI suggestion">✨ Get suggestion</button>
          </div>
        </div>

        <div id="aiSuggestionContainer" style="min-height:100px;"></div>

        <div class="ai-footer-note">
          <span>🧠</span> AI suggestions are generated based on common patterns — always verified by our team.
        </div>
      </section>

      <!-- service hours -->
      <section class="service-hours">
        <h3>⏱️ Delivery made simple</h3>
        <p><strong>Same‑day delivery:</strong> order before 2PM</p>
        <p><strong>Choose your slot:</strong> morning or evening</p>
        <p><strong>Live tracking:</strong> real‑time map for every order</p>
      </section>

    </div><!-- /support view -->

    <!-- ============================================================= -->
    <!-- VIEW: TERMS & CONDITIONS -->
    <!-- ============================================================= -->
    <div class="view-section" id="viewTerms">

      <section class="terms-hero">
        <div class="container">
          <h1>Terms &amp; Conditions</h1>
          <p>Please read these terms carefully before using our services. By using KSR GURU, you agree to be bound by these terms.</p>
        </div>
      </section>

      <div class="terms-content">
        <h2>1. Introduction</h2>
        <p>Welcome to KSR GURU JI ("we", "our", "us"). These Terms &amp; Conditions govern your use of our website, mobile application, and services (collectively, the "Services"). By accessing or using our Services, you agree to be bound by these terms. If you do not agree, please do not use our Services.</p>
        <h2>2. Eligibility</h2>
        <p>You must be at least 18 years old or have parental consent to use our Services. By placing an order, you confirm that you are legally capable of entering into binding contracts.</p>
        <h2>3. Orders &amp; Pricing</h2>
        <p>All orders are subject to acceptance and availability. We reserve the right to refuse or cancel any order for reasons including but not limited to product unavailability, pricing errors, or suspected fraud. Prices are displayed in Indian Rupees (INR) and are inclusive of applicable taxes unless stated otherwise.</p>
        <h2>4. Delivery Policy</h2>
        <p>We strive to deliver within the estimated time frames (same-day for orders placed before 2 PM, 60‑minute emergency delivery subject to location). However, delays may occur due to unforeseen circumstances. Delivery charges, if applicable, will be shown at checkout. Title and risk of loss pass to you upon delivery.</p>
        <h2>5. Returns &amp; Refunds</h2>
        <p>We accept returns within 7 days of delivery for unopened, unused products in original packaging. Perishable items (e.g., fresh produce, dairy) are non‑returnable unless damaged upon arrival. To initiate a return, contact support@ksrguru.com. Refunds will be processed to the original payment method within 7‑10 business days after approval.</p>
        <h2>6. Payment Methods</h2>
        <p>We accept major credit/debit cards, UPI, net banking, and cash on delivery (where available). All payments are securely processed. You agree to provide accurate payment information and authorize us to charge the total order amount.</p>
        <h2>7. Account Registration</h2>
        <p>To access certain features, you may need to create an account. You are responsible for maintaining the confidentiality of your login credentials and for all activities under your account. Notify us immediately of any unauthorized use.</p>
        <h2>8. Intellectual Property</h2>
        <p>All content on our website (text, graphics, logos, images) is the property of KSR GURU or its licensors and is protected by copyright and trademark laws. You may not reproduce, distribute, or modify any content without our prior written consent.</p>
        <h2>9. Limitation of Liability</h2>
        <p>To the maximum extent permitted by law, KSR GURU shall not be liable for any indirect, incidental, or consequential damages arising from the use of our Services. Our total liability shall not exceed the amount you paid for the specific order giving rise to the claim.</p>
        <h2>10. Governing Law &amp; Dispute Resolution</h2>
        <p>These terms are governed by the laws of India. Any disputes shall be subject to the exclusive jurisdiction of the courts in Your City, India. We encourage you to contact us first to resolve any issues amicably.</p>
        <h2>11. Modifications to Terms</h2>
        <p>We may update these Terms &amp; Conditions from time to time. Continued use of our Services after changes constitutes acceptance of the revised terms. Please review this page periodically.</p>
        <h2>12. Contact Us</h2>
        <p>If you have any questions about these Terms &amp; Conditions, please contact us:</p>
        <ul>
          <li><strong>Email:</strong> <a href="mailto:support@ksrguru.com">support@ksrguru.com</a></li>
          <li><strong>Phone:</strong> 8088584421, 8762474406</li>
          <li><strong>Address:</strong> Your City, India</li>
        </ul>
        <div class="last-updated">
          <p><strong>Last Updated:</strong> Aug 21, 2026</p>
        </div>
      </div>
    </div><!-- /terms view -->

    <!-- ============================================================= -->
    <!-- VIEW: PRIVACY POLICY -->
    <!-- ============================================================= -->
    <div class="view-section" id="viewPrivacy">
      <section class="privacy-hero">
        <div class="container">
          <h1>Privacy Policy</h1>
          <p>Your privacy matters to us. Learn how we collect, use, and protect your information.</p>
        </div>
      </section>

      <div class="privacy-content">
        <h2>1. Information We Collect</h2>
        <p>We collect personal information that you voluntarily provide when you register, place an order, or contact us. This may include your name, email address, phone number, delivery address, and payment details. We also automatically collect usage data (IP address, browser type, pages visited) to improve our services.</p>
        <h2>2. How We Use Your Information</h2>
        <p>We use your information to:</p>
        <ul>
          <li>Process and deliver your orders</li>
          <li>Communicate with you about orders, updates, and promotions</li>
          <li>Improve our website, products, and customer service</li>
          <li>Prevent fraud and ensure security</li>
        </ul>

        <h2>3. Data Sharing &amp; Security</h2>
        <p>We never sell or rent your personal data. We may share information with trusted third parties (delivery partners, payment processors) solely to fulfill your orders. All data is encrypted using SSL technology, and we follow industry standards to protect your information.</p>
        <h2>4. Cookies &amp; Tracking</h2>
        <p>We use cookies to enhance your browsing experience, remember your preferences, and analyze site traffic. You can disable cookies in your browser settings, but some features may not function properly.</p>
        <h2>5. Your Rights</h2>
        <p>You have the right to access, correct, or delete your personal data. To exercise these rights, please contact us at <a href="mailto:support@ksrguru.com">support@ksrguru.com</a>. You may also opt out of marketing emails at any time.</p>
        <h2>6. Children's Privacy</h2>
        <p>Our services are not intended for children under 13. We do not knowingly collect information from minors.</p>
        <h2>7. Changes to This Policy</h2>
        <p>We may update this privacy policy occasionally. Any changes will be posted on this page with an updated effective date. We encourage you to review this page periodically.</p>
        <div class="last-updated">
          <p><strong>Last Updated:</strong> March 21, 2026</p>
          <p>For any questions regarding this privacy policy, please contact us at <a href="mailto:support@ksrguru.com">support@ksrguru.com</a> or call 8088584421.</p>
        </div>
      </div>

    </div><!-- /privacy view -->
  </div><!-- /container -->
</main>

<!-- ===== FOOTER ===== -->
<?php
$base_url = '';
include 'includes/user-footer.php';
?>


<!-- ===== JAVASCRIPT ===== -->
<script>
  (function() {
    'use strict';
    // ----- View toggle (3 views) -----
    const toggleSupport = document.getElementById('toggleSupport');
    const toggleTerms = document.getElementById('toggleTerms');
    const togglePrivacy = document.getElementById('togglePrivacy');
    const viewSupport = document.getElementById('viewSupport');
    const viewTerms = document.getElementById('viewTerms');
    const viewPrivacy = document.getElementById('viewPrivacy');
    function setActiveView(view) {
      // remove active from all buttons
      document.querySelectorAll('.view-toggle-bar button').forEach(b => b.classList.remove('active'));
      // hide all views
      [viewSupport, viewTerms, viewPrivacy].forEach(v => v.classList.remove('active'));

      if (view === 'support') {
        toggleSupport.classList.add('active');
        viewSupport.classList.add('active');
      } else if (view === 'terms') {
        toggleTerms.classList.add('active');
        viewTerms.classList.add('active');
      } else if (view === 'privacy') {
        togglePrivacy.classList.add('active');
        viewPrivacy.classList.add('active');
      }
    }
    toggleSupport.addEventListener('click', () => setActiveView('support'));
    toggleTerms.addEventListener('click', () => setActiveView('terms'));
    togglePrivacy.addEventListener('click', () => setActiveView('privacy'));

    // ----- AI suggestion logic (support view only) -----
    const inputField = document.getElementById('aiProblemInput');
    const suggestBtn = document.getElementById('aiGetSuggestionBtn');
    const container = document.getElementById('aiSuggestionContainer');
    const suggestionMap = [
      { keywords: ['delay', 'late', 'delayed', 'not delivered', 'delivery time', 'taking long'],
        suggestion: '⏳ **Delivery delay** – We sincerely apologize. Please check your tracking link in the app. If the order is beyond the promised time, contact our support at <strong>8088584421</strong> for immediate assistance. You can also request a priority update via live chat.' },
      { keywords: ['wrong item', 'incorrect', 'not what i ordered', 'different product', 'mismatch'],
        suggestion: '🔄 **Wrong item received** – We’re sorry for the mix‑up. Please email <strong>support@ksrguru.com</strong> with your order number and a photo of the item. Our team will arrange a replacement or refund within 24 hours.' },
      { keywords: ['return', 'refund', 'cancel', 'want to return', 'money back'],
        suggestion: '💳 **Return / refund** – You can initiate a return directly from the KSR GURU app (Orders → Return). For help, call <strong>8762474406</strong> or describe the issue in live chat. Refunds are processed within 3–5 business days.' },
      { keywords: ['medicine', 'prescription', 'urgent medicine', 'emergency medicine', 'tablet', 'drug'],
        suggestion: '💊 **Medicine / prescription** – For urgent medicine needs, use our emergency line <strong>8088584421</strong> for 60‑min delivery (if within service area). Upload prescription via app for quick refills.' },
      { keywords: ['damaged', 'broken', 'leaking', 'quality issue', 'spoiled'],
        suggestion: '⚠️ **Damaged product** – We apologise for the inconvenience. Please take a photo and send it to <strong>support@ksrguru.com</strong> along with order ID. We will issue a replacement or full refund immediately.' },
      { keywords: ['payment failed', 'money deducted', 'charged twice', 'refund not received', 'payment issue'],
        suggestion: '💰 **Payment problem** – If you were charged but order is not confirmed, please wait 30 mins. If still not resolved, contact us at <strong>8762474406</strong> with transaction details. We’ll help you get a refund or confirm order.' },
      { keywords: ['track', 'tracking', 'where is my order', 'order status', 'not updated'],
        suggestion: '📍 **Order tracking** – Use the live tracking in the KSR GURU app (Track Order). If the status hasn’t changed for a long time, call <strong>8088584421</strong> and we’ll investigate.' },
      { keywords: ['coupon', 'offer', 'discount', 'promo code', 'not working'],
        suggestion: '🏷️ **Coupon / offer** – Check if the coupon is valid for your items and minimum order. For specific issues, email <strong>support@ksrguru.com</strong> with the code and we’ll manually apply it.' },
      { keywords: ['account', 'login', 'sign in', 'password', 'can’t log in'],
        suggestion: '🔐 **Account access** – Try resetting your password via the app. If you still face issues, contact live chat or email <strong>support@ksrguru.com</strong> with your registered mobile.' }
    ];
    const defaultSuggestion = '🔎 **AI suggestion** – Based on your input, we recommend visiting our <strong>FAQ section</strong> or starting a <strong>live chat</strong> from the app. You can also call our helpline <strong>8088584421</strong> for personal assistance.';
    function getSuggestion(query) {
      if (!query || query.trim() === '') return defaultSuggestion;
      const lowerQuery = query.toLowerCase();
      for (let entry of suggestionMap) {
        for (let kw of entry.keywords) {
          if (lowerQuery.includes(kw)) {
            return entry.suggestion;
          }
        }
      }
      return defaultSuggestion;
    }
    function displaySuggestion(suggestionHtml) {
      container.innerHTML = `
        <div class="ai-suggestion-card">
          <div class="suggestion-head">
            <span>✨</span> AI suggestion
          </div>
          <div class="suggestion-text">
            ${suggestionHtml}
          </div>
        </div>
      `;
    }
    function handleSuggestion() {
      const problem = inputField.value.trim() || 'general help';
      const suggestion = getSuggestion(problem);
      displaySuggestion(suggestion);
    }
    suggestBtn.addEventListener('click', handleSuggestion);
    inputField.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        handleSuggestion();
      }
    });
    // Show a welcome hint on load (only if container empty)
    window.addEventListener('load', function() {
      if (container.children.length === 0) {
        displaySuggestion('💡 **Try it!** Type your problem above and click "Get suggestion". Our AI is ready to help.');
      }
    });

    // ----- URL hash routing: if ?view=terms or ?view=privacy, set active tab -----
    const urlParams = new URLSearchParams(window.location.search);
    const viewParam = urlParams.get('view');
    if (viewParam === 'terms') setActiveView('terms');
    else if (viewParam === 'privacy') setActiveView('privacy');
    // else keep support as default
  })();
</script>
</body>
</html>