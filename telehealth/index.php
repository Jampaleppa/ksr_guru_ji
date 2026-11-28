<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$waNumber  = '916362079192';
$callPhone = '6362079192';
$supportWa = '918088584421';

$doctors = [];
try {
    require_once __DIR__ . '/../includes/db.php';
    $pdo = getDBConnection();
    $rows = $pdo->query(
        "SELECT id, doctor_name, specialization, specialty, experience_years, qualification, fee, rating, about
         FROM doctors
         WHERE status = 'active' AND verification_status = 'VERIFIED'
         ORDER BY rating DESC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $spec = trim((string)($r['specialization'] ?: $r['specialty'] ?: 'General Physician'));
        $isNeuro = stripos($spec, 'neuro') !== false || stripos($spec, 'cardio') !== false;
        $doctors[] = [
            'id'    => (int)$r['id'],
            'name'  => (string)$r['doctor_name'],
            'spec'  => $spec,
            'qual'  => trim((string)($r['qualification'] ?? 'MBBS')),
            'exp'   => (int)($r['experience_years'] ?? 3),
            'fee'   => (int)($r['fee'] > 0 ? $r['fee'] : 150),
            'rating'=> number_format((float)($r['rating'] > 0 ? $r['rating'] : 4.8), 1),
            'about' => trim((string)($r['about'] ?? '')),
            'emoji' => $isNeuro ? '🧠' : '👨‍⚕️',
            'slug'  => $isNeuro ? 'neuro' : 'general',
            'avail' => true,
        ];
    }
} catch (Throwable $e) {
    // Fall back safely when DB is unavailable
}

if (empty($doctors)) {
    $doctors = [
        [
            'id' => 1, 'name' => 'Dr. Shivukumar Rathod', 'spec' => 'Pediatrics & General Medicine',
            'qual' => 'MBBS, MD', 'exp' => 5, 'fee' => 200, 'rating' => '4.9',
            'about' => 'General medicine, pediatrics & critical patient care.',
            'emoji' => '👨‍⚕️', 'slug' => 'general', 'avail' => true,
        ],
        [
            'id' => 2, 'name' => 'Dr. D C Rathod', 'spec' => 'Neuro Care & Cardiology',
            'qual' => 'MBBS, MD', 'exp' => 3, 'fee' => 150, 'rating' => '4.8',
            'about' => 'Neuro care, hypertension & preventive cardiology.',
            'emoji' => '🧠', 'slug' => 'neuro', 'avail' => true,
        ],
    ];
}
$minFee = min(array_column($doctors, 'fee'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="Consult verified doctors online via Video, Call or WhatsApp. Book appointments and order medicines – KSR GURU JI Telehealth.">
<title>Telehealth – KSR GURU JI | ಆನ್ಲೈನ್ ಡಾಕ್ಟರ್ ಸಲಹೆ</title>
<link rel="icon" href="../images/logo.png" type="image/png">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --p:#0277bd;--pd:#01579b;--p-soft:#e1f5fe;--g:#1b5e20;--g-light:#e8f5e9;
  --wa:#128c7e;--bg:#f3f6fb;--white:#fff;--text:#0f172a;--muted:#546e7a;
  --border:#e2e8f0;--shadow:0 4px 16px rgba(15,23,42,.08);
  --r:16px;--nh:60px;--bnh:66px;
}
html{scroll-behavior:smooth}
body{
  font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
  background:var(--bg);color:var(--text);
  min-height:100vh;padding-bottom:calc(var(--bnh) + env(safe-area-inset-bottom,0px) + 12px);
  overflow-x:hidden;-webkit-font-smoothing:antialiased;
}
.wrap{max-width:960px;margin:0 auto}

/* ── HEADER ── */
header{
  position:sticky;top:0;z-index:300;
  background:linear-gradient(135deg,var(--pd),var(--p));
  height:var(--nh);padding:0 16px;
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  box-shadow:0 2px 12px rgba(1,87,155,.25);
}
.hlogo{display:flex;align-items:center;gap:10px;text-decoration:none;min-width:0}
.hlogo img{width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.35);flex-shrink:0}
.hlt{color:#fff;font-weight:800;font-size:.98rem;line-height:1.1;white-space:nowrap}
.hls{color:rgba(255,255,255,.82);font-size:.7rem}
.hactions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.hbtn-call{
  color:#fff;text-decoration:none;font-size:.82rem;font-weight:700;
  background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.28);
  min-height:40px;padding:0 12px;border-radius:20px;display:inline-flex;align-items:center;gap:5px;
}
.hbtn{
  color:var(--pd);text-decoration:none;font-size:.82rem;font-weight:800;
  background:#fff;min-height:40px;padding:0 14px;border-radius:20px;
  display:inline-flex;align-items:center;gap:5px;box-shadow:0 2px 8px rgba(0,0,0,.12);
}

/* ── HERO ── */
.hero{
  background:linear-gradient(155deg,var(--pd) 0%,#0288d1 65%,#039be5 100%);
  color:#fff;padding:24px 16px 28px;position:relative;overflow:hidden;
}
.hero-inner{max-width:960px;margin:0 auto;position:relative;z-index:2}
.hero-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3);
  border-radius:999px;padding:5px 13px;font-size:.75rem;font-weight:700;margin-bottom:12px;
}
.dot-live{width:8px;height:8px;border-radius:50%;background:#4ade80;animation:blink 1.4s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.35}}
.hero h1{font-size:1.6rem;font-weight:900;line-height:1.2;margin-bottom:6px;letter-spacing:-.3px}
.hero .kn{font-size:.95rem;opacity:.9;margin-bottom:10px;font-weight:600}
.hero p{font-size:.88rem;opacity:.92;line-height:1.55;max-width:460px;margin-bottom:20px}
.hero-cta{display:flex;gap:10px;flex-wrap:wrap}
.btn-primary,.btn-secondary{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  min-height:46px;padding:10px 20px;border-radius:999px;font-size:.9rem;font-weight:800;
  text-decoration:none;transition:transform .15s;
}
.btn-primary{background:#fff;color:var(--pd);box-shadow:0 6px 18px rgba(0,0,0,.18)}
.btn-secondary{background:rgba(255,255,255,.18);color:#fff;border:1.5px solid rgba(255,255,255,.4)}
.btn-primary:active,.btn-secondary:active{transform:scale(.97)}
.hero-stats{
  display:grid;grid-template-columns:repeat(4,1fr);gap:8px;
  margin-top:22px;padding-top:16px;border-top:1px solid rgba(255,255,255,.22);
}
.stat{text-align:center}
.stat-n{font-size:1.15rem;font-weight:900}
.stat-l{font-size:.68rem;opacity:.85;margin-top:2px}

/* ── EMERGENCY STRIP ── */
.emg-bar{
  margin:14px 16px 0;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;
  border-radius:12px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;
  font-size:.78rem;font-weight:600;
}
.emg-call{
  background:#dc2626;color:#fff;text-decoration:none;padding:6px 12px;border-radius:999px;
  font-weight:800;font-size:.74rem;white-space:nowrap;flex-shrink:0;
}

/* ── SECTION HEADINGS ── */
.sec{padding:22px 16px 10px;display:flex;align-items:flex-end;justify-content:space-between;gap:10px}
.sec-t{font-size:1.05rem;font-weight:800;color:var(--text)}
.sec-s{font-size:.76rem;color:var(--muted);margin-top:2px}
.sec-link{font-size:.8rem;font-weight:700;color:var(--p);text-decoration:none;white-space:nowrap}

/* ── SERVICES GRID ── */
.services{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;padding:0 16px}
.svc{
  background:var(--white);border-radius:var(--r);padding:16px 12px;
  text-align:center;text-decoration:none;color:var(--text);box-shadow:var(--shadow);
  border:1px solid var(--border);display:flex;flex-direction:column;align-items:center;gap:8px;
  position:relative;min-height:132px;justify-content:center;transition:transform .15s;
}
.svc:active{transform:scale(.97)}
.svc-ico{width:50px;height:50px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.45rem}
.svc h3{font-size:.86rem;font-weight:800;line-height:1.25}
.svc p{font-size:.72rem;color:var(--muted);line-height:1.35}
.svc-badge{position:absolute;top:9px;right:9px;font-size:.6rem;font-weight:800;padding:2px 7px;border-radius:999px;background:var(--g-light);color:var(--g)}
.ic-b{background:#e0f2fe;color:#0277bd}
.ic-g{background:#dcfce7;color:#15803d}
.ic-o{background:#ffedd5;color:#c2410c}
.ic-pu{background:#f3e8ff;color:#7e22ce}

/* ── SPECIALTIES ── */
.spec-row{display:flex;gap:10px;overflow-x:auto;padding:2px 16px 8px;scrollbar-width:none}
.spec-row::-webkit-scrollbar{display:none}
.spec-pill{flex:0 0 auto;display:flex;flex-direction:column;align-items:center;gap:6px;text-decoration:none;color:var(--text);min-width:68px}
.spec-circle{
  width:56px;height:56px;border-radius:50%;background:var(--white);
  border:2px solid var(--border);display:flex;align-items:center;justify-content:center;
  font-size:1.45rem;box-shadow:var(--shadow);
}
.spec-label{font-size:.7rem;font-weight:700;color:var(--muted);text-align:center;line-height:1.2}

/* ── VERIFIED DOCTORS CARDS ── */
.docs-list{display:grid;grid-template-columns:1fr;gap:12px;padding:0 16px}
.doc-card{
  background:var(--white);border-radius:var(--r);box-shadow:var(--shadow);
  border:1px solid var(--border);padding:14px;display:flex;flex-direction:column;gap:12px;
}
.doc-main{display:flex;gap:12px;align-items:flex-start}
.doc-ava{
  width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,#bae6fd,#e0f2fe);
  display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0;
}
.doc-info{flex:1;min-width:0}
.doc-name{font-size:.98rem;font-weight:800;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.ver-badge{font-size:.66rem;background:var(--g-light);color:var(--g);padding:2px 7px;border-radius:999px;font-weight:800}
.doc-sp{font-size:.78rem;color:var(--p);font-weight:700;margin-top:2px}
.doc-meta{font-size:.74rem;color:var(--muted);margin-top:4px;display:flex;flex-wrap:wrap;gap:8px}
.doc-about{font-size:.76rem;color:var(--muted);line-height:1.45;margin-top:6px}
.doc-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.btn-book,.btn-wa{
  min-height:44px;border-radius:10px;font-size:.82rem;font-weight:800;
  text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px;
}
.btn-book{background:var(--p);color:#fff}
.btn-wa{background:#25d366;color:#fff}

/* ── HOW IT WORKS ── */
.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:0 16px}
.step{background:var(--white);border-radius:12px;padding:12px 6px;text-align:center;border:1px solid var(--border)}
.step-n{width:32px;height:32px;border-radius:50%;background:var(--p);color:#fff;font-weight:900;font-size:.85rem;display:flex;align-items:center;justify-content:center;margin:0 auto 6px}
.step-l{font-size:.7rem;font-weight:700;line-height:1.3;color:var(--text)}

/* ── PORTAL / PHARMACY BRIDGE ── */
.bridge-grid{display:grid;grid-template-columns:1fr;gap:10px;padding:0 16px}
.bridge-card{
  background:var(--white);border-radius:var(--r);padding:14px 16px;border:1px solid var(--border);
  box-shadow:var(--shadow);display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--text);
}
.bridge-ico{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;flex-shrink:0}
.bridge-txt{flex:1;min-width:0}
.bridge-txt strong{display:block;font-size:.9rem}
.bridge-txt small{display:block;font-size:.75rem;color:var(--muted);margin-top:2px}
.bridge-go{color:var(--p);font-weight:800;font-size:1rem}

/* ── WHATSAPP CTA ── */
.wa-cta{
  margin:18px 16px;background:linear-gradient(135deg,#25d366,#128c7e);
  border-radius:var(--r);padding:16px 18px;display:flex;align-items:center;gap:14px;
  text-decoration:none;color:#fff;box-shadow:0 6px 20px rgba(37,211,102,.25);
}
.wa-cta-ico{font-size:2rem;flex-shrink:0}
.wa-cta-t{font-weight:800;font-size:.95rem;margin-bottom:2px}
.wa-cta-s{font-size:.76rem;opacity:.9}

/* ── BOTTOM NAV ── */
.bnav{
  position:fixed;bottom:0;left:0;right:0;
  height:calc(var(--bnh) + env(safe-area-inset-bottom,0px));
  padding-bottom:env(safe-area-inset-bottom,0px);
  background:var(--white);border-top:1px solid var(--border);
  display:flex;box-shadow:0 -4px 16px rgba(15,23,42,.08);z-index:300;
}
.bi{
  flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;
  text-decoration:none;color:var(--muted);font-size:.68rem;font-weight:700;
  min-height:48px;-webkit-tap-highlight-color:transparent;
}
.bi .ico{font-size:1.25rem;line-height:1}
.bi.active{color:var(--p)}

@media(min-width:560px){
  .services{grid-template-columns:repeat(4,1fr)}
  .docs-list{grid-template-columns:repeat(2,1fr)}
  .bridge-grid{grid-template-columns:repeat(2,1fr)}
}
@media(min-width:768px){
  body{padding-bottom:24px}
  .bnav{display:none}
  .hero h1{font-size:2rem}
}
@media(max-width:380px){
  .hbtn-call span{display:none}
  .hero-stats{grid-template-columns:repeat(2,1fr);gap:12px}
}
</style>
</head>
<body>

<header>
  <a class="hlogo" href="../index.php">
    <img src="../images/logo.png" alt="KSR GURU JI" onerror="this.style.display='none'">
    <div>
      <div class="hlt">KSR GURU JI</div>
      <div class="hls">🩺 Telehealth &amp; Care</div>
    </div>
  </a>
  <div class="hactions">
    <a class="hbtn-call" href="tel:+91<?= htmlspecialchars($callPhone) ?>" aria-label="Call Doctor Helpdesk">📞 <span>Call</span></a>
    <a class="hbtn" href="appointment.php">📅 Book</a>
  </div>
</header>

<section class="hero">
  <div class="hero-inner">
    <div class="hero-badge"><span class="dot-live"></span> Verified KSR Doctors Online</div>
    <h1>Talk to a Verified Doctor Online</h1>
    <p class="kn">ಆನ್ಲೈನ್ ಡಾಕ್ಟರ್ ಸಲಹೆ ಪಡೆಯಿರಿ — ಮನೆಯಲ್ಲಿಯೇ ಚಿಕಿತ್ಸೆ</p>
    <p>Direct consultation via Video, Audio Call or WhatsApp with NMC-verified KSR physicians. Fast prescription &amp; medicine delivery.</p>
    <div class="hero-cta">
      <a class="btn-primary" href="appointment.php">📅 Book Appointment</a>
      <a class="btn-secondary" href="doctors.php">👨‍⚕️ Meet Our Doctors</a>
    </div>
    <div class="hero-stats">
      <div class="stat"><div class="stat-n"><?= count($doctors) ?>+</div><div class="stat-l">Verified Docs</div></div>
      <div class="stat"><div class="stat-n">24/7</div><div class="stat-l">Care Support</div></div>
      <div class="stat"><div class="stat-n">₹<?= (int)$minFee ?></div><div class="stat-l">Starting Fee</div></div>
      <div class="stat"><div class="stat-n">4.9★</div><div class="stat-l">Patient Trust</div></div>
    </div>
  </div>
</section>

<div class="wrap">
  <div class="emg-bar" role="note">
    <span>🚨 Emergency (chest pain, breathing difficulty, heavy bleeding)?</span>
    <a class="emg-call" href="tel:108">Dial 108</a>
  </div>

  <!-- SERVICES -->
  <div class="sec">
    <div><div class="sec-t">Telehealth Services</div><div class="sec-s">ನಮ್ಮ ಆರೋಗ್ಯ ಸೇವೆಗಳು</div></div>
  </div>
  <div class="services">
    <a class="svc" href="appointment.php?type=video">
      <span class="svc-badge">Live</span>
      <div class="svc-ico ic-b">📹</div>
      <h3>Video Consult</h3>
      <p>Face-to-face call with doctor</p>
    </a>
    <a class="svc" href="appointment.php?type=chat">
      <span class="svc-badge">Fast</span>
      <div class="svc-ico ic-g">💬</div>
      <h3>Chat / WhatsApp</h3>
      <p>Quick text &amp; photo advice</p>
    </a>
    <a class="svc" href="appointment.php?type=lab">
      <div class="svc-ico ic-o">🔬</div>
      <h3>Lab &amp; Reports</h3>
      <p>Home blood test booking</p>
    </a>
    <a class="svc" href="../WhatsApp/telehealth/medicine/tablet.php">
      <div class="svc-ico ic-pu">💊</div>
      <h3>Order Medicines</h3>
      <p>3,689+ tablets in e-Pharmacy</p>
    </a>
  </div>

  <!-- SPECIALTIES -->
  <div class="sec">
    <div><div class="sec-t">Specialties</div><div class="sec-s">ವಿಶೇಷ ವಿಭಾಗಗಳು</div></div>
    <a class="sec-link" href="doctors.php">View All →</a>
  </div>
  <div class="spec-row">
    <a class="spec-pill" href="doctors.php?spec=general"><div class="spec-circle">👨‍⚕️</div><span class="spec-label">General</span></a>
    <a class="spec-pill" href="doctors.php?spec=pediatrics"><div class="spec-circle">👶</div><span class="spec-label">Pediatrics</span></a>
    <a class="spec-pill" href="doctors.php?spec=neuro"><div class="spec-circle">🧠</div><span class="spec-label">Neuro Care</span></a>
    <a class="spec-pill" href="doctors.php?spec=cardio"><div class="spec-circle">🫀</div><span class="spec-label">Cardiology</span></a>
    <a class="spec-pill" href="appointment.php?type=medicine"><div class="spec-circle">💊</div><span class="spec-label">Medicines</span></a>
    <a class="spec-pill" href="appointment.php?type=lab"><div class="spec-circle">🧪</div><span class="spec-label">Lab Tests</span></a>
  </div>

  <!-- VERIFIED DOCTORS -->
  <div class="sec">
    <div><div class="sec-t">Verified Doctors</div><div class="sec-s">ತಜ್ಞ ವೈದ್ಯರು · Ready Today</div></div>
    <a class="sec-link" href="doctors.php">See All →</a>
  </div>
  <div class="docs-list">
    <?php foreach ($doctors as $d): ?>
      <?php
        $docArg = urlencode($d['name'] . ' – ' . $d['spec'] . ' (₹' . $d['fee'] . ')');
        $waText = urlencode("Hello KSR Telehealth, I would like to book an appointment with {$d['name']} ({$d['spec']}). Fee: ₹{$d['fee']}.");
      ?>
      <div class="doc-card">
        <div class="doc-main">
          <div class="doc-ava"><?= $d['emoji'] ?></div>
          <div class="doc-info">
            <div class="doc-name">
              <span><?= htmlspecialchars($d['name']) ?></span>
              <span class="ver-badge">✔ Verified</span>
            </div>
            <div class="doc-sp"><?= htmlspecialchars($d['spec']) ?></div>
            <div class="doc-meta">
              <span>🎓 <?= htmlspecialchars($d['qual']) ?></span>
              <span>💼 <?= (int)$d['exp'] ?>+ yrs</span>
              <span>⭐ <?= htmlspecialchars($d['rating']) ?></span>
              <span style="color:var(--g);font-weight:800;">₹<?= (int)$d['fee'] ?></span>
            </div>
            <?php if ($d['about'] !== ''): ?>
              <div class="doc-about"><?= htmlspecialchars($d['about']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="doc-actions">
          <a class="btn-book" href="appointment.php?doctor=<?= $docArg ?>">📅 Book Slot</a>
          <a class="btn-wa" href="https://wa.me/<?= $waNumber ?>?text=<?= $waText ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- HOW IT WORKS -->
  <div class="sec">
    <div><div class="sec-t">How Consultation Works</div><div class="sec-s">ಹೇಗೆ ಕೆಲಸ ಮಾಡುತ್ತದೆ?</div></div>
  </div>
  <div class="steps">
    <div class="step"><div class="step-n">1</div><div class="step-l">Choose Doctor</div></div>
    <div class="step"><div class="step-n">2</div><div class="step-l">Pick Time Slot</div></div>
    <div class="step"><div class="step-n">3</div><div class="step-l">Video / Call</div></div>
    <div class="step"><div class="step-n">4</div><div class="step-l">Get Rx &amp; Meds</div></div>
  </div>

  <!-- WHATSAPP INSTANT CTA -->
  <a class="wa-cta" href="https://wa.me/<?= $waNumber ?>?text=<?= urlencode('Hello KSR Telehealth, I need an online doctor consultation.') ?>" target="_blank" rel="noopener">
    <span class="wa-cta-ico">💬</span>
    <div>
      <div class="wa-cta-t">Instant Doctor Support on WhatsApp</div>
      <div class="wa-cta-s">📞 +91 <?= htmlspecialchars($callPhone) ?> · ಈಗಲೇ WhatsApp ನಲ್ಲಿ ಸಂಪರ್ಕಿಸಿ</div>
    </div>
  </a>

  <!-- ECOSYSTEM LINKS -->
  <div class="sec">
    <div><div class="sec-t">More Health Portals</div><div class="sec-s">ಡಾಕ್ಟರ್ ಮತ್ತು ಫಾರ್ಮಸಿ ಪೋರ್ಟಲ್</div></div>
  </div>
  <div class="bridge-grid">
    <a class="bridge-card" href="../WhatsApp/telehealth/medicine/tablet.php">
      <div class="bridge-ico ic-pu">💊</div>
      <div class="bridge-txt"><strong>KSR e-Pharmacy &amp; Tablets</strong><small>Browse 3,689+ medicines &amp; upload prescription</small></div>
      <span class="bridge-go">→</span>
    </a>
    <a class="bridge-card" href="../WhatsApp/telehealth/index.php">
      <div class="bridge-ico ic-b">🏥</div>
      <div class="bridge-txt"><strong>Full Doctor Assist Portal</strong><small>Patient records, doctor &amp; pharmacist dashboards</small></div>
      <span class="bridge-go">→</span>
    </a>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bnav" aria-label="Telehealth navigation">
  <a class="bi active" href="index.php"><span class="ico">🏥</span><span>Telehealth</span></a>
  <a class="bi" href="doctors.php"><span class="ico">👨‍⚕️</span><span>Doctors</span></a>
  <a class="bi" href="appointment.php"><span class="ico">📅</span><span>Book</span></a>
  <a class="bi" href="../WhatsApp/telehealth/medicine/tablet.php"><span class="ico">💊</span><span>Medicines</span></a>
  <a class="bi" href="../index.php"><span class="ico">🛒</span><span>Shop</span></a>
</nav>

</body>
</html>