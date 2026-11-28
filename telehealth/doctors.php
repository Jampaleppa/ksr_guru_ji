<?php
$specFilter = $_GET['spec'] ?? 'all';
$pageTitle = "Find a Doctor – KSR GURU JI Telehealth";

$doctors = [
  ['id'=>1,'name'=>'Dr. Ramesh Kumar',   'spec'=>'general',  'specLabel'=>'General Physician',   'exp'=>12,'fee'=>149,'langs'=>'Kannada, Hindi, English','about'=>'Expert in common illnesses, fever, cold, diabetes management.','emoji'=>'👨‍⚕️','avail'=>true],
  ['id'=>2,'name'=>'Dr. Lakshmi Devi',   'spec'=>'ayurveda', 'specLabel'=>'Ayurveda Specialist',  'exp'=>8, 'fee'=>199,'langs'=>'Kannada, Telugu, Hindi',   'about'=>'Holistic treatments using natural herbs. Joint pain, skin disorders.','emoji'=>'🌿','avail'=>true],
  ['id'=>3,'name'=>'Dr. Suresh Patel',   'spec'=>'child',    'specLabel'=>'Paediatrician',        'exp'=>10,'fee'=>179,'langs'=>'Kannada, Hindi, English',   'about'=>'Child health, growth monitoring, vaccinations, infections.','emoji'=>'👶','avail'=>true],
  ['id'=>4,'name'=>'Dr. Priya Sharma',   'spec'=>'dental',   'specLabel'=>'Dentist',              'exp'=>6, 'fee'=>129,'langs'=>'Kannada, English',           'about'=>'Tooth pain, gum issues, dental hygiene advice.','emoji'=>'🦷','avail'=>true],
  ['id'=>5,'name'=>'Dr. Anita Rao',      'spec'=>'skin',     'specLabel'=>'Dermatologist',        'exp'=>9, 'fee'=>249,'langs'=>'Kannada, English, Hindi',    'about'=>'Acne, eczema, skin rash, hair fall, pigmentation.','emoji'=>'🏥','avail'=>false],
  ['id'=>6,'name'=>'Dr. Kiran Hegde',    'spec'=>'general',  'specLabel'=>'General Physician',   'exp'=>15,'fee'=>149,'langs'=>'Kannada, English',           'about'=>'Hypertension, thyroid, gastric problems, fatigue.','emoji'=>'👨‍⚕️','avail'=>true],
  ['id'=>7,'name'=>'Dr. Deepa Nair',     'spec'=>'women',    'specLabel'=>'Gynaecologist',       'exp'=>11,'fee'=>299,'langs'=>'Kannada, Malayalam, English','about'=>'Women health, PCOS, menstrual issues, pregnancy advice.','emoji'=>'🩺','avail'=>true],
  ['id'=>8,'name'=>'Dr. Mahesh Gowda',   'spec'=>'ayurveda', 'specLabel'=>'Ayurveda Specialist',  'exp'=>14,'fee'=>199,'langs'=>'Kannada, Hindi',             'about'=>'Panchakarma, weight management, arthritis, digestion.','emoji'=>'🌿','avail'=>false],
  ['id'=>9,'name'=>'Dr. Ravi Shankar',   'spec'=>'mental',   'specLabel'=>'Counsellor / Mental Health','exp'=>7,'fee'=>399,'langs'=>'Kannada, English, Hindi','about'=>'Stress, anxiety, depression, relationship counselling.','emoji'=>'🧠','avail'=>true],
  ['id'=>10,'name'=>'Dr. Suma Patil',    'spec'=>'child',    'specLabel'=>'Paediatrician',        'exp'=>5, 'fee'=>149,'langs'=>'Kannada, Marathi, English',  'about'=>'Newborn care, immunisation, developmental milestones.','emoji'=>'👶','avail'=>true],
];

$specLabels = [
  'all'=>'All Doctors','general'=>'General','ayurveda'=>'Ayurveda','dental'=>'Dental',
  'child'=>'Child/Paeds','skin'=>'Skin','women'=>'Women','mental'=>'Mental Health',
];

$filtered = $specFilter === 'all' ? $doctors : array_values(array_filter($doctors, fn($d)=>$d['spec']===$specFilter));
$waNumber = "919480000000";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Browse certified doctors on KSR GURU JI Telehealth. Filter by specialty.">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" href="../images/logo.png" type="image/png">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --primary: #0277bd; --primary-dark: #01579b; --bg: #f0f4f8;
      --white: #ffffff; --text: #1a1a2e; --muted: #607d8b;
      --card-shadow: 0 2px 12px rgba(0,0,0,.10); --radius: 14px;
      --nav-h: 60px; --bottom-nav-h: 64px;
    }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); padding-bottom: var(--bottom-nav-h); }

    /* NAV */
    header {
      position: sticky; top: 0; z-index: 200;
      background: var(--primary); height: var(--nav-h);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 16px; box-shadow: 0 2px 8px rgba(0,0,0,.2);
    }
    .back-btn { color: #fff; text-decoration: none; font-size: 1.3rem; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; }
    .nav-title { color: #fff; font-weight: 700; font-size: 1rem; }
    .book-all { color: #fff; font-size: .82rem; background: rgba(255,255,255,.18); padding: 7px 13px; border-radius: 20px; text-decoration: none; font-weight: 600; }

    /* FILTER CHIPS */
    .filter-bar {
      display: flex; gap: 8px; overflow-x: auto; padding: 14px 16px 10px;
      scrollbar-width: none; background: var(--white); border-bottom: 1px solid #e0e0e0;
    }
    .filter-bar::-webkit-scrollbar { display: none; }
    .chip {
      flex: 0 0 auto; padding: 8px 16px; border-radius: 20px; font-size: .78rem;
      font-weight: 600; border: 2px solid #e0e0e0; background: var(--white);
      color: var(--muted); cursor: pointer; text-decoration: none; transition: all .15s;
      white-space: nowrap;
    }
    .chip.active { border-color: var(--primary); background: var(--primary); color: #fff; }

    /* COUNT */
    .result-count { padding: 10px 16px 6px; font-size: .82rem; color: var(--muted); }

    /* DOCTOR GRID */
    .doctors-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px; padding: 0 12px 24px;
    }
    .doc-card {
      background: var(--white); border-radius: var(--radius);
      box-shadow: var(--card-shadow); overflow: hidden;
      text-decoration: none; color: var(--text);
    }
    .doc-top {
      background: linear-gradient(135deg, #e3f2fd, #bbdefb);
      text-align: center; padding: 18px 8px 10px;
      font-size: 3rem;
    }
    .doc-info { padding: 10px 12px 12px; }
    .doc-name { font-size: .82rem; font-weight: 700; margin-bottom: 2px; line-height: 1.25; }
    .doc-spec { font-size: .7rem; color: var(--primary); font-weight: 600; }
    .doc-exp  { font-size: .68rem; color: var(--muted); margin: 3px 0; }
    .doc-fee  { font-size: .78rem; font-weight: 800; color: #2e7d32; }
    .doc-langs { font-size: .66rem; color: var(--muted); margin-top: 3px; line-height: 1.3; }
    .avail-badge {
      display: inline-flex; align-items: center; gap: 3px;
      font-size: .65rem; font-weight: 600; padding: 2px 8px; border-radius: 10px;
      margin-top: 6px;
    }
    .avail-yes { background: #e8f5e9; color: #2e7d32; }
    .avail-no  { background: #fafafa; color: #9e9e9e; }
    .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; display: inline-block; }

    /* BOOK BTN inside each card */
    .book-btn {
      display: block; width: calc(100% - 24px); margin: 0 12px 12px;
      background: var(--primary); color: #fff; text-align: center;
      padding: 10px; border-radius: 8px; font-size: .78rem; font-weight: 700;
      text-decoration: none; transition: background .15s;
    }
    .book-btn:active { background: var(--primary-dark); }
    .book-btn.wa { background: #25d366; }
    .book-btn.wa:active { background: #1da851; }

    /* EMPTY */
    .empty { text-align: center; padding: 60px 20px; color: var(--muted); font-size: .9rem; }

    /* BOTTOM NAV */
    .bottom-nav {
      position: fixed; bottom: 0; left: 0; right: 0; height: var(--bottom-nav-h);
      background: var(--white); border-top: 1px solid #e0e0e0;
      display: flex; box-shadow: 0 -2px 12px rgba(0,0,0,.08); z-index: 300;
    }
    .bnav-item {
      flex: 1; display: flex; flex-direction: column; align-items: center;
      justify-content: center; gap: 3px; text-decoration: none; color: var(--muted);
      font-size: .68rem; font-weight: 600; -webkit-tap-highlight-color: transparent;
    }
    .bnav-item .ico { font-size: 1.3rem; }
    .bnav-item.active { color: var(--primary); }

    @media (min-width: 480px) { .doctors-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (min-width: 768px) {
      body { padding-bottom: 0; } .bottom-nav { display: none; }
      .doctors-grid { grid-template-columns: repeat(4, 1fr); max-width: 900px; margin: auto; }
    }
  </style>
</head>
<body>

<header>
  <a class="back-btn" href="index.php">←</a>
  <div class="nav-title">👨‍⚕️ Find a Doctor</div>
  <a class="book-all" href="appointment.php">📅 Book</a>
</header>

<!-- FILTER CHIPS -->
<div class="filter-bar">
  <?php foreach ($specLabels as $key => $label): ?>
    <a class="chip <?= $specFilter===$key ? 'active' : '' ?>" href="?spec=<?= $key ?>">
      <?= htmlspecialchars($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="result-count"><?= count($filtered) ?> doctor<?= count($filtered)!==1?'s':'' ?> found</div>

<!-- DOCTOR GRID -->
<div class="doctors-grid">
  <?php if (empty($filtered)): ?>
    <div class="empty" style="grid-column:1/-1;">No doctors found for this specialty.<br><a href="?spec=all">Show all doctors</a></div>
  <?php else: ?>
    <?php foreach ($filtered as $doc): ?>
      <div class="doc-card">
        <div class="doc-top"><?= $doc['emoji'] ?></div>
        <div class="doc-info">
          <div class="doc-name"><?= htmlspecialchars($doc['name']) ?></div>
          <div class="doc-spec"><?= htmlspecialchars($doc['specLabel']) ?></div>
          <div class="doc-exp"><?= $doc['exp'] ?> yrs exp</div>
          <div class="doc-fee">₹<?= $doc['fee'] ?>/session</div>
          <div class="doc-langs">🗣 <?= htmlspecialchars($doc['langs']) ?></div>
          <div class="avail-badge <?= $doc['avail'] ? 'avail-yes' : 'avail-no' ?>">
            <span class="dot"></span>
            <?= $doc['avail'] ? 'Available Now' : 'Next Available' ?>
          </div>
        </div>
        <?php
          $waMsg = urlencode("Hello, I want to book a consultation with {$doc['name']} ({$doc['specLabel']}). Fee: ₹{$doc['fee']}");
        ?>
        <a class="book-btn wa" href="https://wa.me/<?= $waNumber ?>?text=<?= $waMsg ?>" target="_blank">
          💬 Book via WhatsApp
        </a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <a class="bnav-item" href="index.php"><span class="ico">🏥</span>Telehealth</a>
  <a class="bnav-item active" href="doctors.php"><span class="ico">👨‍⚕️</span>Doctors</a>
  <a class="bnav-item" href="appointment.php"><span class="ico">📅</span>Book</a>
  <a class="bnav-item" href="../index.php"><span class="ico">🛒</span>Shop</a>
</nav>

</body>
</html>
