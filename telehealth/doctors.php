<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$waNumber   = '916362079192';
$callPhone  = '6362079192';
$specFilter = strtolower(trim((string)($_GET['spec'] ?? 'all')));

$allDoctors = [];
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
    $specLabel = trim((string)($r['specialization'] ?: $r['specialty'] ?: 'General Physician'));
    $isNeuro   = stripos($specLabel, 'neuro') !== false || stripos($specLabel, 'cardio') !== false;
    $isPeds    = stripos($specLabel, 'pediatr') !== false;
    $tags      = ['general'];
    if ($isNeuro) $tags = ['neuro', 'cardio'];
    elseif ($isPeds) $tags = ['general', 'pediatrics'];

    $allDoctors[] = [
      'id'        => (int)$r['id'],
      'name'      => (string)$r['doctor_name'],
      'tags'      => $tags,
      'specLabel' => $specLabel,
      'qual'      => trim((string)($r['qualification'] ?? 'MBBS')),
      'exp'       => (int)($r['experience_years'] ?? 3),
      'fee'       => (int)($r['fee'] > 0 ? $r['fee'] : 150),
      'langs'     => 'Kannada, English, Hindi',
      'about'     => trim((string)($r['about'] ?? 'Available for online video, audio and WhatsApp consultations.')),
      'emoji'     => $isNeuro ? '🧠' : '👨‍⚕️',
      'avail'     => true,
      'rating'    => number_format((float)($r['rating'] > 0 ? $r['rating'] : 4.8), 1),
    ];
  }
} catch (Throwable $e) {
  // Fallback below
}

if (empty($allDoctors)) {
  $allDoctors = [
    [
      'id' => 1,
      'name' => 'Dr. Shivukumar Rathod',
      'tags' => ['general', 'pediatrics'],
      'specLabel' => 'Pediatrics & General Medicine',
      'qual' => 'MBBS, MD',
      'exp' => 5,
      'fee' => 200,
      'langs' => 'Kannada, English, Hindi',
      'about' => 'General medicine, pediatrics and critical patient care.',
      'emoji' => '👨‍⚕️',
      'avail' => true,
      'rating' => '4.9',
    ],
    [
      'id' => 2,
      'name' => 'Dr. D C Rathod',
      'tags' => ['neuro', 'cardio'],
      'specLabel' => 'Neuro Care & Cardiology',
      'qual' => 'MBBS, MD',
      'exp' => 3,
      'fee' => 150,
      'langs' => 'Kannada, English, Hindi',
      'about' => 'Neuro care, hypertension management & preventive cardiology.',
      'emoji' => '🧠',
      'avail' => true,
      'rating' => '4.8',
    ],
  ];
}

$specLabels = [
  'all'        => ['🩺', 'All Doctors'],
  'general'    => ['👨‍⚕️', 'General Medicine'],
  'pediatrics' => ['👶', 'Pediatrics'],
  'neuro'      => ['🧠', 'Neuro Care'],
  'cardio'     => ['🫀', 'Cardiology'],
];

$filtered = $specFilter === 'all'
  ? $allDoctors
  : array_values(array_filter($allDoctors, fn($d) => in_array($specFilter, $d['tags'], true)));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Find a Doctor – KSR GURU JI Telehealth</title>
  <link rel="icon" href="../images/logo.png" type="image/png">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    :root {
      --p: #0277bd;
      --pd: #01579b;
      --g: #1b5e20;
      --g-bg: #e8f5e9;
      --bg: #f3f6fb;
      --white: #fff;
      --text: #0f172a;
      --muted: #546e7a;
      --border: #e2e8f0;
      --shadow: 0 4px 16px rgba(15, 23, 42, .08);
      --r: 16px;
      --nh: 58px;
      --bnh: 66px;
    }

    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      padding-bottom: calc(var(--bnh) + env(safe-area-inset-bottom, 0px) + 12px);
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    header {
      position: sticky;
      top: 0;
      z-index: 300;
      background: linear-gradient(135deg, var(--pd), var(--p));
      height: var(--nh);
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 0 12px;
      box-shadow: 0 2px 10px rgba(1, 87, 155, .22);
    }

    .back {
      color: #fff;
      text-decoration: none;
      font-size: 1.3rem;
      width: 44px;
      height: 44px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 12px
    }

    .nav-t {
      color: #fff;
      font-weight: 800;
      font-size: 1rem;
      flex: 1
    }

    .hbtn {
      color: var(--pd);
      text-decoration: none;
      font-size: .8rem;
      background: #fff;
      padding: 8px 14px;
      border-radius: 999px;
      font-weight: 800;
      white-space: nowrap
    }

    .search-wrap {
      background: var(--p);
      padding: 0 16px 14px
    }

    .search-box {
      max-width: 920px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      gap: 10px;
      background: #fff;
      border-radius: 999px;
      padding: 6px 14px;
      min-height: 46px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
    }

    .search-box input {
      flex: 1;
      border: none;
      outline: none;
      font-size: 1rem;
      color: var(--text);
      background: transparent
    }

    .search-clear {
      border: none;
      background: none;
      color: var(--muted);
      font-size: 1rem;
      cursor: pointer;
      display: none;
      padding: 4px 8px
    }

    .filter-bar {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      padding: 12px 16px;
      background: #fff;
      border-bottom: 1px solid var(--border);
      scrollbar-width: none;
    }

    .filter-bar::-webkit-scrollbar {
      display: none
    }

    .chip {
      flex: 0 0 auto;
      min-height: 40px;
      padding: 8px 14px;
      border-radius: 999px;
      font-size: .8rem;
      font-weight: 700;
      border: 2px solid var(--border);
      background: #fff;
      color: var(--muted);
      text-decoration: none;
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .chip.active {
      border-color: var(--p);
      background: var(--p);
      color: #fff
    }

    .wrap {
      max-width: 960px;
      margin: 0 auto
    }

    .meta {
      padding: 12px 16px 8px;
      font-size: .82rem;
      color: var(--muted);
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-weight: 600
    }

    .sort-hint {
      color: var(--g)
    }

    .docs-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      padding: 0 16px 24px
    }

    .doc {
      background: var(--white);
      border-radius: var(--r);
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .doc-head {
      display: flex;
      gap: 14px;
      align-items: flex-start
    }

    .doc-ava {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      background: linear-gradient(135deg, #bae6fd, #e0f2fe);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.2rem;
      flex-shrink: 0;
    }

    .doc-body {
      flex: 1;
      min-width: 0
    }

    .doc-name-row {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
      margin-bottom: 3px
    }

    .doc-name {
      font-size: 1rem;
      font-weight: 800;
      color: var(--text)
    }

    .avail-y {
      font-size: .66rem;
      font-weight: 800;
      background: var(--g-bg);
      color: var(--g);
      padding: 2px 8px;
      border-radius: 999px
    }

    .doc-sp {
      font-size: .82rem;
      color: var(--p);
      font-weight: 700;
      margin-bottom: 4px
    }

    .doc-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      font-size: .76rem;
      color: var(--muted);
      margin-bottom: 6px
    }

    .doc-fee {
      color: var(--g);
      font-weight: 800;
      font-size: .9rem
    }

    .doc-about {
      font-size: .78rem;
      color: var(--muted);
      line-height: 1.45
    }

    .doc-langs {
      font-size: .74rem;
      color: var(--muted);
      margin-top: 4px
    }

    .doc-actions {
      display: grid;
      grid-template-columns: 1fr 1fr auto;
      gap: 8px
    }

    .btn-a {
      min-height: 44px;
      border-radius: 10px;
      font-size: .82rem;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 0 12px;
    }

    .btn-book {
      background: var(--p);
      color: #fff
    }

    .btn-wa {
      background: #25d366;
      color: #fff
    }

    .btn-call {
      background: #f1f5f9;
      color: var(--text);
      border: 1px solid var(--border);
      min-width: 46px
    }

    .empty {
      grid-column: 1/-1;
      text-align: center;
      padding: 48px 20px;
      color: var(--muted);
      background: #fff;
      border-radius: var(--r)
    }

    .empty a {
      display: inline-block;
      margin-top: 10px;
      color: var(--p);
      font-weight: 700;
      text-decoration: none
    }

    .bnav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      height: calc(var(--bnh) + env(safe-area-inset-bottom, 0px));
      padding-bottom: env(safe-area-inset-bottom, 0px);
      background: var(--white);
      border-top: 1px solid var(--border);
      display: flex;
      box-shadow: 0 -4px 16px rgba(15, 23, 42, .08);
      z-index: 300;
    }

    .bi {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 2px;
      text-decoration: none;
      color: var(--muted);
      font-size: .68rem;
      font-weight: 700
    }

    .bi .ico {
      font-size: 1.25rem;
      line-height: 1
    }

    .bi.active {
      color: var(--p)
    }

    @media(min-width:640px) {
      .docs-grid {
        grid-template-columns: repeat(2, 1fr)
      }
    }

    @media(min-width:768px) {
      body {
        padding-bottom: 24px
      }

      .bnav {
        display: none
      }
    }
  </style>
</head>

<body>

  <header>
    <a class="back" href="index.php" aria-label="Back to Telehealth Home">←</a>
    <div class="nav-t">👨‍⚕️ Verified Doctors</div>
    <a class="hbtn" href="appointment.php">📅 Book Slot</a>
  </header>

  <div class="search-wrap">
    <div class="search-box">
      <span>🔍</span>
      <input type="search" id="searchInput" placeholder="Search doctor, specialty, qualification..." oninput="filterDoctors()">
      <button type="button" class="search-clear" id="clearSearch" onclick="resetSearch()" aria-label="Clear search">✕</button>
    </div>
  </div>

  <div class="filter-bar" role="tablist" aria-label="Doctor specialties">
    <?php foreach ($specLabels as $key => [$ico, $lbl]): ?>
      <a class="chip <?= $specFilter === $key ? 'active' : '' ?>" href="?spec=<?= urlencode($key) ?>">
        <span><?= $ico ?></span><span><?= htmlspecialchars($lbl) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="wrap">
    <div class="meta">
      <span id="countLabel"><?= count($filtered) ?> verified doctor<?= count($filtered) !== 1 ? 's' : '' ?></span>
      <span class="sort-hint">🟢 Available Today</span>
    </div>

    <div class="docs-grid" id="doctorGrid">
      <?php if (empty($filtered)): ?>
        <div class="empty">No doctors matched this specialty.<br><a href="?spec=all">Show all verified doctors</a></div>
      <?php else: ?>
        <?php foreach ($filtered as $doc): ?>
          <?php
          $docParam = urlencode($doc['name'] . ' – ' . $doc['specLabel'] . ' (₹' . $doc['fee'] . ')');
          $msg      = urlencode("Hello, I want to book an online consultation with {$doc['name']} ({$doc['specLabel']}). Fee: ₹{$doc['fee']}");
          ?>
          <article class="doc" data-search="<?= htmlspecialchars(strtolower($doc['name'] . ' ' . $doc['specLabel'] . ' ' . $doc['qual'] . ' ' . $doc['about'])) ?>">
            <div class="doc-head">
              <div class="doc-ava"><?= $doc['emoji'] ?></div>
              <div class="doc-body">
                <div class="doc-name-row">
                  <h2 class="doc-name"><?= htmlspecialchars($doc['name']) ?></h2>
                  <span class="avail-y">🟢 Available</span>
                </div>
                <div class="doc-sp"><?= htmlspecialchars($doc['specLabel']) ?></div>
                <div class="doc-tags">
                  <span>🎓 <?= htmlspecialchars($doc['qual']) ?></span>
                  <span>💼 <?= (int)$doc['exp'] ?>+ yrs exp</span>
                  <span>⭐ <?= htmlspecialchars($doc['rating']) ?></span>
                  <span class="doc-fee">₹<?= (int)$doc['fee'] ?>/consult</span>
                </div>
                <p class="doc-about"><?= htmlspecialchars($doc['about']) ?></p>
                <div class="doc-langs">🗣 <?= htmlspecialchars($doc['langs']) ?></div>
              </div>
            </div>
            <div class="doc-actions">
              <a class="btn-a btn-book" href="appointment.php?doctor=<?= $docParam ?>">📅 Book Slot</a>
              <a class="btn-a btn-wa" href="https://wa.me/<?= $waNumber ?>?text=<?= $msg ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
              <a class="btn-a btn-call" href="tel:+91<?= htmlspecialchars($callPhone) ?>" aria-label="Call doctor desk">📞</a>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <nav class="bnav" aria-label="Telehealth navigation">
    <a class="bi" href="index.php"><span class="ico">🏥</span><span>Telehealth</span></a>
    <a class="bi active" href="doctors.php"><span class="ico">👨‍⚕️</span><span>Doctors</span></a>
    <a class="bi" href="appointment.php"><span class="ico">📅</span><span>Book</span></a>
    <a class="bi" href="../WhatsApp/telehealth/medicine/tablet.php"><span class="ico">💊</span><span>Medicines</span></a>
    <a class="bi" href="../index.php"><span class="ico">🛒</span><span>Shop</span></a>
  </nav>

  <script>
    function filterDoctors() {
      const inp = document.getElementById('searchInput');
      const q = inp.value.toLowerCase().trim();
      document.getElementById('clearSearch').style.display = q ? 'block' : 'none';
      const cards = document.querySelectorAll('#doctorGrid .doc');
      let visible = 0;
      cards.forEach(c => {
        const match = !q || (c.dataset.search || '').includes(q);
        c.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      document.getElementById('countLabel').textContent = visible + ' verified doctor' + (visible !== 1 ? 's' : '');
    }

    function resetSearch() {
      document.getElementById('searchInput').value = '';
      filterDoctors();
    }
  </script>
</body>

</html>