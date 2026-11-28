<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$waNumber  = '916362079192';
$callPhone = '6362079192';
$logFile   = __DIR__ . '/appointments_log.txt';

$doctors = [];
$pdo     = null;
try {
    require_once __DIR__ . '/../includes/db.php';
    $pdo  = getDBConnection();
    $rows = $pdo->query(
        "SELECT doctor_name, specialization, specialty, fee
         FROM doctors
         WHERE status = 'active' AND verification_status = 'VERIFIED'
         ORDER BY rating DESC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $spec = trim((string)($r['specialization'] ?: $r['specialty'] ?: 'General Physician'));
        $fee  = (int)($r['fee'] > 0 ? $r['fee'] : 150);
        $doctors[] = "{$r['doctor_name']} – {$spec} (₹{$fee})";
    }
} catch (Throwable $e) {
    $pdo = null;
}

if (empty($doctors)) {
    $doctors = [
        'Dr. Shivukumar Rathod – Pediatrics & General Medicine (₹200)',
        'Dr. D C Rathod – Neuro Care & Cardiology (₹150)',
    ];
}

$slotGroups = [
    'Morning'   => ['09:00 AM', '09:30 AM', '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM'],
    'Afternoon' => ['12:00 PM', '02:00 PM', '02:30 PM', '03:00 PM', '03:30 PM', '04:00 PM'],
    'Evening'   => ['05:00 PM', '05:30 PM', '06:00 PM', '06:30 PM', '07:00 PM', '07:30 PM'],
];

$validTypes = ['video', 'chat', 'lab', 'medicine'];
$preType    = strtolower(trim((string)($_GET['type'] ?? '')));
if (!in_array($preType, $validTypes, true)) {
    $preType = 'video';
}
$selectedType   = in_array($_POST['type'] ?? '', $validTypes, true) ? $_POST['type'] : $preType;
$selectedDoctor = trim((string)($_POST['doctor'] ?? ($_GET['doctor'] ?? $doctors[0])));

$todayDate   = date('Y-m-d');
$tomorrow    = date('Y-m-d', strtotime('+1 day'));
$dayAfter    = date('Y-m-d', strtotime('+2 days'));
$maxDate     = date('Y-m-d', strtotime('+30 days'));
$defaultDate = trim((string)($_POST['date'] ?? $tomorrow));

$success    = false;
$errors     = [];
$fieldErrs  = [];
$waLink     = '';
$summary    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim((string)($_POST['name'] ?? ''));
    $phone    = trim((string)($_POST['phone'] ?? ''));
    $doctor   = trim((string)($_POST['doctor'] ?? ''));
    $date     = trim((string)($_POST['date'] ?? ''));
    $time     = trim((string)($_POST['time'] ?? ''));
    $type     = trim((string)($_POST['type'] ?? 'video'));
    $symptoms = trim((string)($_POST['symptoms'] ?? ''));

    if (mb_strlen($name) < 2) {
        $errors[] = 'Please enter your full name (at least 2 characters).';
        $fieldErrs['name'] = true;
    }
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) < 10 || strlen($digits) > 13) {
        $errors[] = 'Please enter a valid 10-digit mobile number.';
        $fieldErrs['phone'] = true;
    }
    if ($doctor === '') {
        $errors[] = 'Please select a doctor.';
        $fieldErrs['doctor'] = true;
    }
    if ($date === '' || $date < $todayDate || $date > $maxDate) {
        $errors[] = 'Please choose a valid appointment date within the next 30 days.';
        $fieldErrs['date'] = true;
    }
    if ($time === '') {
        $errors[] = 'Please select a preferred time slot.';
        $fieldErrs['time'] = true;
    }
    if (!in_array($type, $validTypes, true)) {
        $type = 'video';
    }

    if (empty($errors)) {
        // Save log & attempt DB insert cleanly
        $logEntry = date('Y-m-d H:i:s') . " | {$name} | {$phone} | {$doctor} | {$date} {$time} | {$type} | {$symptoms}\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        if ($pdo instanceof PDO) {
            try {
                $docShort = explode('–', $doctor)[0];
                $stmt = $pdo->prepare(
                    "INSERT INTO appointments (patient_id, patient_name, doctor_name, appointment_date, appointment_time, type, reason, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)"
                );
                $stmt->execute([
                    !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                    $name,
                    trim($docShort),
                    $date,
                    $time,
                    ucfirst($type),
                    $symptoms ?: 'Telehealth consultation',
                    'Phone: ' . $phone,
                ]);
            } catch (Throwable $e) {
                error_log('Telehealth appointment DB insert skipped: ' . $e->getMessage());
            }
        }

        $typeLabel = [
            'video'    => 'Video Consultation',
            'chat'     => 'Chat / WhatsApp Consult',
            'lab'      => 'Lab Test',
            'medicine' => 'Medicine Consultation',
        ][$type] ?? ucfirst($type);

        $msg = "Hello KSR GURU JI Telehealth! I booked an appointment:\n\n"
             . "👤 Patient: {$name}\n"
             . "📱 Phone: {$phone}\n"
             . "👨‍⚕️ Doctor: {$doctor}\n"
             . "📅 Date: " . date('d M Y', strtotime($date)) . "\n"
             . "⏰ Time: {$time}\n"
             . "🩺 Type: {$typeLabel}\n"
             . ($symptoms !== '' ? "📝 Symptoms: {$symptoms}" : '');
        $waLink  = "https://wa.me/{$waNumber}?text=" . rawurlencode($msg);
        $summary = compact('name', 'phone', 'doctor', 'date', 'time', 'typeLabel', 'symptoms');
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="Book an online telehealth appointment with verified KSR GURU JI doctors.">
<title>Book Appointment – KSR GURU JI Telehealth</title>
<link rel="icon" href="../images/logo.png" type="image/png">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --p:#0277bd;--pd:#01579b;--p-soft:#e0f2fe;--g:#15803d;
  --bg:#f3f6fb;--white:#fff;--text:#0f172a;--muted:#546e7a;--border:#dbe4ee;
  --err:#c62828;--err-bg:#fef2f2;--r:16px;--nh:58px;--bnh:66px;
}
body{
  font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);
  padding-bottom:calc(var(--bnh) + env(safe-area-inset-bottom,0px) + 18px);
  overflow-x:hidden;-webkit-font-smoothing:antialiased;
}
header{
  position:sticky;top:0;z-index:300;background:linear-gradient(135deg,var(--pd),var(--p));
  height:var(--nh);display:flex;align-items:center;gap:8px;padding:0 12px;
  box-shadow:0 2px 10px rgba(1,87,155,.22);
}
.back-btn{color:#fff;text-decoration:none;font-size:1.35rem;width:44px;height:44px;display:flex;align-items:center;justify-content:center}
.nav-title{color:#fff;font-weight:800;font-size:1rem;flex:1}
.call-pill{color:#fff;text-decoration:none;font-size:.78rem;font-weight:700;background:rgba(255,255,255,.18);padding:7px 12px;border-radius:999px}

.appt-hero{background:linear-gradient(145deg,var(--pd),var(--p));color:#fff;padding:18px 18px 24px;text-align:center}
.appt-hero h1{font-size:1.35rem;font-weight:900;margin-bottom:4px}
.appt-hero p{font-size:.82rem;opacity:.9}

.form-card,.success-card{
  background:var(--white);border-radius:var(--r);border:1px solid var(--border);
  box-shadow:0 4px 18px rgba(15,23,42,.07);margin:14px 12px;padding:18px 16px;
}

.type-tabs{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:18px}
.type-tab{
  min-height:66px;text-align:center;padding:8px 4px;border-radius:12px;
  border:2px solid var(--border);cursor:pointer;font-size:.72rem;font-weight:700;
  color:var(--muted);background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;
}
.type-tab .tab-icon{font-size:1.3rem}
.type-tab.active{border-color:var(--p);background:var(--p-soft);color:var(--pd)}

.field{margin-bottom:16px}
label{display:block;font-size:.82rem;font-weight:700;color:var(--text);margin-bottom:6px}
.req{color:var(--err)}
input,select,textarea{
  width:100%;border:2px solid var(--border);border-radius:12px;
  padding:11px 13px;font-size:16px;font-family:inherit;color:var(--text);
  background:#fff;outline:none;transition:border-color .15s;min-height:46px;
}
input:focus,select:focus,textarea:focus{border-color:var(--p)}
input.err,select.err,textarea.err{border-color:var(--err);background:#fff9f9}
textarea{min-height:92px;resize:vertical}

.quick-dates{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
.date-chip,.sym-chip{
  border:1.5px solid var(--border);background:#f8fafc;color:var(--text);
  border-radius:999px;padding:7px 12px;font-size:.76rem;font-weight:700;cursor:pointer;min-height:36px;
}
.date-chip.active{border-color:var(--p);background:var(--p);color:#fff}
.sym-chips{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}
.sym-chip:active{background:var(--p-soft);border-color:var(--p)}

.slot-group-title{font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin:10px 0 6px}
.time-slots{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.slot-btn{
  min-height:42px;padding:8px 4px;border:1.5px solid var(--border);border-radius:10px;
  background:#fff;font-size:.78rem;font-weight:700;cursor:pointer;color:var(--text);
}
.slot-btn.selected{border-color:var(--p);background:var(--p);color:#fff}

.error-box{
  background:var(--err-bg);border:1px solid #fecaca;border-radius:12px;
  padding:12px 14px;margin-bottom:16px;
}
.error-box p{font-size:.82rem;color:var(--err);margin-bottom:3px;font-weight:600}
.error-box p:last-child{margin-bottom:0}

.submit-btn{
  width:100%;min-height:52px;background:var(--p);color:#fff;border:none;border-radius:12px;
  font-size:1rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
}
.submit-btn:active{background:var(--pd)}
.note{font-size:.76rem;color:var(--muted);margin-top:10px;text-align:center;line-height:1.45}

.success-card{text-align:center;padding:24px 18px}
.success-icon{font-size:3rem;display:block;margin-bottom:10px}
.success-card h2{font-size:1.3rem;color:var(--g);margin-bottom:6px}
.summary-box{background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin:16px 0;text-align:left;font-size:.84rem}
.sum-row{display:flex;justify-content:space-between;gap:10px;padding:5px 0;border-bottom:1px solid #edf2f7}
.sum-row:last-child{border-bottom:none}
.wa-btn{
  display:flex;align-items:center;justify-content:center;gap:8px;min-height:50px;
  background:#25d366;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;
  font-weight:800;font-size:.98rem;margin:0 auto 12px;max-width:320px;box-shadow:0 4px 16px rgba(37,211,102,.3);
}
.new-btn{
  display:inline-flex;align-items:center;justify-content:center;min-height:42px;
  padding:8px 20px;border:2px solid var(--p);border-radius:999px;color:var(--p);
  font-weight:700;font-size:.86rem;text-decoration:none;
}

.bottom-nav{
  position:fixed;bottom:0;left:0;right:0;
  height:calc(var(--bnh) + env(safe-area-inset-bottom,0px));
  padding-bottom:env(safe-area-inset-bottom,0px);
  background:var(--white);border-top:1px solid var(--border);
  display:flex;box-shadow:0 -4px 16px rgba(15,23,42,.08);z-index:300;
}
.bnav-item{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;text-decoration:none;color:var(--muted);font-size:.68rem;font-weight:700}
.bnav-item .ico{font-size:1.25rem;line-height:1}
.bnav-item.active{color:var(--p)}

@media(min-width:600px){.form-card,.success-card{max-width:560px;margin:20px auto;padding:24px}}
@media(min-width:768px){body{padding-bottom:24px}.bottom-nav{display:none}}
@media(max-width:380px){.type-tabs{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>

<header>
  <a class="back-btn" href="index.php" aria-label="Back">←</a>
  <div class="nav-title">📅 Book Appointment</div>
  <a class="call-pill" href="tel:+91<?= htmlspecialchars($callPhone) ?>">📞 Help</a>
</header>

<div class="appt-hero">
  <h1>Book Doctor Consultation</h1>
  <p>ನಿಮ್ಮ ಅಪಾಯಿಂಟ್‌ಮೆಂಟ್ ಬುಕ್ ಮಾಡಿ · Quick Mobile Booking</p>
</div>

<?php if ($success): ?>
  <div class="success-card" role="status" aria-live="polite">
    <span class="success-icon">✅</span>
    <h2>Appointment Request Recorded!</h2>
    <p style="font-size:.86rem;color:var(--muted);line-height:1.5;">Send the pre-filled details on WhatsApp so our care team confirms your slot immediately.</p>
    <div class="summary-box">
      <div class="sum-row"><span>Patient</span><strong><?= htmlspecialchars($summary['name']) ?></strong></div>
      <div class="sum-row"><span>Phone</span><strong><?= htmlspecialchars($summary['phone']) ?></strong></div>
      <div class="sum-row"><span>Doctor</span><strong><?= htmlspecialchars($summary['doctor']) ?></strong></div>
      <div class="sum-row"><span>Date &amp; Time</span><strong><?= htmlspecialchars(date('d M Y', strtotime($summary['date']))) ?> · <?= htmlspecialchars($summary['time']) ?></strong></div>
      <div class="sum-row"><span>Consultation</span><strong><?= htmlspecialchars($summary['typeLabel']) ?></strong></div>
    </div>
    <a class="wa-btn" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener">💬 Confirm on WhatsApp</a>
    <a class="new-btn" href="appointment.php">Book Another Appointment</a>
  </div>
<?php else: ?>
  <div class="form-card">
    <?php if (!empty($errors)): ?>
      <div class="error-box" role="alert">
        <?php foreach ($errors as $e): ?>
          <p>• <?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="apptForm" novalidate>
      <div class="field">
        <label>Consultation Type <span class="req">*</span></label>
        <div class="type-tabs" role="radiogroup" aria-label="Consultation type">
          <?php
            $types = [
              'video'    => ['📹', 'Video Call'],
              'chat'     => ['💬', 'Chat / WA'],
              'lab'      => ['🔬', 'Lab Test'],
              'medicine' => ['💊', 'Medicine'],
            ];
            foreach ($types as $k => [$icon, $lbl]):
          ?>
            <button type="button"
                    class="type-tab <?= $selectedType === $k ? 'active' : '' ?>"
                    onclick="selectType('<?= $k ?>')"
                    data-type="<?= $k ?>"
                    aria-pressed="<?= $selectedType === $k ? 'true' : 'false' ?>">
              <span class="tab-icon"><?= $icon ?></span><span><?= $lbl ?></span>
            </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="type" id="typeInput" value="<?= htmlspecialchars($selectedType) ?>">
      </div>

      <div class="field">
        <label for="name">Patient Full Name <span class="req">*</span></label>
        <input type="text" id="name" name="name" class="<?= !empty($fieldErrs['name']) ? 'err' : '' ?>"
               placeholder="ನಿಮ್ಮ ಹೆಸರು / Your full name"
               value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>" autocomplete="name" required>
      </div>

      <div class="field">
        <label for="phone">Mobile Number (WhatsApp) <span class="req">*</span></label>
        <input type="tel" id="phone" name="phone" class="<?= !empty($fieldErrs['phone']) ? 'err' : '' ?>"
               placeholder="e.g. 9876543210"
               value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>" autocomplete="tel" inputmode="tel" required>
      </div>

      <div class="field">
        <label for="doctor">Select Verified Doctor <span class="req">*</span></label>
        <select id="doctor" name="doctor" class="<?= !empty($fieldErrs['doctor']) ? 'err' : '' ?>" required>
          <option value="">-- Choose doctor --</option>
          <?php foreach ($doctors as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= ($selectedDoctor === $d) ? 'selected' : '' ?>>
              <?= htmlspecialchars($d) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="date">Preferred Date <span class="req">*</span></label>
        <div class="quick-dates">
          <button type="button" class="date-chip <?= $defaultDate === $todayDate ? 'active' : '' ?>" onclick="pickDate('<?= $todayDate ?>', this)">Today</button>
          <button type="button" class="date-chip <?= $defaultDate === $tomorrow ? 'active' : '' ?>" onclick="pickDate('<?= $tomorrow ?>', this)">Tomorrow</button>
          <button type="button" class="date-chip <?= $defaultDate === $dayAfter ? 'active' : '' ?>" onclick="pickDate('<?= $dayAfter ?>', this)"><?= date('d M', strtotime($dayAfter)) ?></button>
        </div>
        <input type="date" id="date" name="date" class="<?= !empty($fieldErrs['date']) ? 'err' : '' ?>"
               min="<?= $todayDate ?>" max="<?= $maxDate ?>"
               value="<?= htmlspecialchars($defaultDate) ?>" required>
      </div>

      <div class="field">
        <label>Preferred Time Slot <span class="req">*</span></label>
        <?php foreach ($slotGroups as $gLabel => $slots): ?>
          <div class="slot-group-title"><?= $gLabel ?></div>
          <div class="time-slots">
            <?php foreach ($slots as $slot): ?>
              <button type="button"
                      class="slot-btn <?= (($_POST['time'] ?? '') === $slot) ? 'selected' : '' ?>"
                      onclick="selectSlot(this, '<?= $slot ?>')">
                <?= $slot ?>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <input type="hidden" name="time" id="timeInput" value="<?= htmlspecialchars((string)($_POST['time'] ?? '')) ?>">
      </div>

      <div class="field">
        <label for="symptoms">Symptoms / Reason <span style="color:var(--muted);font-weight:400">(tap quick tags or type)</span></label>
        <div class="sym-chips">
          <?php foreach (['Fever / Cold', 'Headache', 'BP / Sugar Check', 'Stomach Pain', 'Joint Pain', 'Medicine Refill'] as $sym): ?>
            <button type="button" class="sym-chip" onclick="addSymptom('<?= htmlspecialchars($sym, ENT_QUOTES) ?>')">+ <?= htmlspecialchars($sym) ?></button>
          <?php endforeach; ?>
        </div>
        <textarea id="symptoms" name="symptoms" placeholder="Describe your symptoms or health concern..."><?= htmlspecialchars((string)($_POST['symptoms'] ?? '')) ?></textarea>
      </div>

      <button type="submit" class="submit-btn">📅 Confirm Appointment Request</button>
      <p class="note">Pay directly during confirmation. Zero advance fee required.</p>
    </form>
  </div>
<?php endif; ?>

<nav class="bottom-nav" aria-label="Telehealth navigation">
  <a class="bnav-item" href="index.php"><span class="ico">🏥</span><span>Telehealth</span></a>
  <a class="bnav-item" href="doctors.php"><span class="ico">👨‍⚕️</span><span>Doctors</span></a>
  <a class="bnav-item active" href="appointment.php"><span class="ico">📅</span><span>Book</span></a>
  <a class="bnav-item" href="../WhatsApp/telehealth/medicine/tablet.php"><span class="ico">💊</span><span>Medicines</span></a>
  <a class="bnav-item" href="../index.php"><span class="ico">🛒</span><span>Shop</span></a>
</nav>

<script>
function selectType(type) {
  document.querySelectorAll('.type-tab').forEach(t => {
    const active = t.dataset.type === type;
    t.classList.toggle('active', active);
    t.setAttribute('aria-pressed', active ? 'true' : 'false');
  });
  document.getElementById('typeInput').value = type;
}
function pickDate(isoDate, btn) {
  document.getElementById('date').value = isoDate;
  document.querySelectorAll('.date-chip').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');
}
function selectSlot(btn, slot) {
  document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('timeInput').value = slot;
}
function addSymptom(text) {
  const ta = document.getElementById('symptoms');
  const cur = ta.value.trim();
  if (!cur.toLowerCase().includes(text.toLowerCase())) {
    ta.value = cur ? (cur + ', ' + text) : text;
  }
  ta.focus();
}
</script>
</body>
</html>
