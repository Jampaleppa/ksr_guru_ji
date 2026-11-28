<?php
session_start();
$waNumber = "919480000000"; // Replace with real WhatsApp number
$logFile  = __DIR__ . '/appointments_log.txt';

$doctors = [
  'Dr. Ramesh Kumar – General Physician (₹149)',
  'Dr. Lakshmi Devi – Ayurveda Specialist (₹199)',
  'Dr. Suresh Patel – Paediatrician (₹179)',
  'Dr. Priya Sharma – Dentist (₹129)',
  'Dr. Anita Rao – Dermatologist (₹249)',
  'Dr. Kiran Hegde – General Physician (₹149)',
  'Dr. Deepa Nair – Gynaecologist (₹299)',
  'Dr. Mahesh Gowda – Ayurveda Specialist (₹199)',
  'Dr. Ravi Shankar – Counsellor / Mental Health (₹399)',
  'Dr. Suma Patil – Paediatrician (₹149)',
];

$timeSlots = ['09:00 AM','09:30 AM','10:00 AM','10:30 AM','11:00 AM','11:30 AM',
              '12:00 PM','02:00 PM','02:30 PM','03:00 PM','03:30 PM','04:00 PM',
              '05:00 PM','05:30 PM','06:00 PM','06:30 PM','07:00 PM','07:30 PM'];

$success = false;
$errors  = [];
$waLink  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name     = trim($_POST['name'] ?? '');
  $phone    = trim($_POST['phone'] ?? '');
  $doctor   = trim($_POST['doctor'] ?? '');
  $date     = trim($_POST['date'] ?? '');
  $time     = trim($_POST['time'] ?? '');
  $type     = trim($_POST['type'] ?? 'video');
  $symptoms = trim($_POST['symptoms'] ?? '');

  if (strlen($name) < 2)   $errors[] = 'Please enter your full name.';
  if (!preg_match('/^[0-9+\s\-]{7,15}$/', $phone)) $errors[] = 'Please enter a valid phone number.';
  if (empty($doctor))      $errors[] = 'Please select a doctor.';
  if (empty($date))        $errors[] = 'Please select a date.';
  if (empty($time))        $errors[] = 'Please select a time slot.';

  if (empty($errors)) {
    // Log the appointment
    $logEntry = date('Y-m-d H:i:s') . " | {$name} | {$phone} | {$doctor} | {$date} {$time} | {$type} | {$symptoms}\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // Build WhatsApp message
    $typeLabel = ['video'=>'Video Call','chat'=>'Chat','lab'=>'Lab Test','medicine'=>'Medicine Delivery'][$type] ?? $type;
    $msg = "Hello KSR GURU JI Telehealth! I want to book an appointment:\n\n"
         . "👤 Name: {$name}\n"
         . "📱 Phone: {$phone}\n"
         . "👨‍⚕️ Doctor: {$doctor}\n"
         . "📅 Date: {$date}\n"
         . "⏰ Time: {$time}\n"
         . "🩺 Type: {$typeLabel}\n"
         . (strlen($symptoms) ? "📝 Symptoms: {$symptoms}" : '');
    $waLink  = "https://wa.me/{$waNumber}?text=" . rawurlencode($msg);
    $success = true;
  }
}

$preType   = $_GET['type'] ?? '';
$preDoctor = count($doctors) > 0 ? '' : '';
$minDate   = date('Y-m-d', strtotime('+1 day'));
$maxDate   = date('Y-m-d', strtotime('+30 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Book a telehealth appointment with KSR GURU JI doctors.">
  <title>Book Appointment – KSR GURU JI Telehealth</title>
  <link rel="icon" href="../images/logo.png" type="image/png">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --primary: #0277bd; --primary-dark: #01579b; --accent: #25d366;
      --bg: #f0f4f8; --white: #fff; --text: #1a1a2e; --muted: #607d8b;
      --error: #c62828; --success: #2e7d32;
      --radius: 12px; --nav-h: 60px; --bottom-nav-h: 64px;
    }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); padding-bottom: calc(var(--bottom-nav-h) + 16px); }

    /* NAV */
    header {
      position: sticky; top: 0; z-index: 200;
      background: var(--primary); height: var(--nav-h);
      display: flex; align-items: center; gap: 12px;
      padding: 0 16px; box-shadow: 0 2px 8px rgba(0,0,0,.2);
    }
    .back-btn { color: #fff; text-decoration: none; font-size: 1.4rem; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; }
    .nav-title { color: #fff; font-weight: 700; font-size: 1rem; flex: 1; }

    /* HERO STRIP */
    .appt-hero {
      background: linear-gradient(135deg, #01579b, #0277bd);
      color: #fff; padding: 20px 20px 24px; text-align: center;
    }
    .appt-hero h1 { font-size: 1.3rem; font-weight: 800; margin-bottom: 4px; }
    .appt-hero p { font-size: .82rem; opacity: .88; }

    /* FORM CARD */
    .form-card {
      background: var(--white); border-radius: var(--radius);
      box-shadow: 0 2px 16px rgba(0,0,0,.09);
      margin: 16px; padding: 20px;
    }

    /* TYPE TABS */
    .type-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
    .type-tab {
      flex: 1; min-width: 70px; text-align: center; padding: 10px 6px;
      border-radius: 10px; border: 2px solid #e0e0e0; cursor: pointer;
      font-size: .75rem; font-weight: 600; color: var(--muted);
      transition: all .15s; background: var(--white);
    }
    .type-tab .tab-icon { font-size: 1.3rem; display: block; margin-bottom: 3px; }
    .type-tab.active { border-color: var(--primary); background: #e3f2fd; color: var(--primary); }

    /* FORM FIELDS */
    .field { margin-bottom: 16px; }
    label { display: block; font-size: .8rem; font-weight: 700; color: var(--text); margin-bottom: 6px; }
    .req { color: var(--error); }
    input, select, textarea {
      width: 100%; border: 2px solid #e0e0e0; border-radius: 10px;
      padding: 12px 14px; font-size: .88rem; font-family: inherit; color: var(--text);
      background: var(--white); outline: none; transition: border-color .15s;
      -webkit-appearance: none;
    }
    input:focus, select:focus, textarea:focus { border-color: var(--primary); }
    input.err, select.err, textarea.err { border-color: var(--error); }
    textarea { min-height: 90px; resize: vertical; }
    select { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='%23607d8b' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 12px center; background-size: 18px; padding-right: 36px; }

    /* TIME SLOTS */
    .time-slots { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .slot-btn {
      padding: 10px 4px; border: 2px solid #e0e0e0; border-radius: 8px;
      background: var(--white); font-size: .74rem; font-weight: 600; cursor: pointer;
      text-align: center; color: var(--muted); transition: all .15s;
    }
    .slot-btn:hover, .slot-btn.selected { border-color: var(--primary); background: #e3f2fd; color: var(--primary); }

    /* ERRORS */
    .error-box {
      background: #ffebee; border: 1px solid #ef9a9a; border-radius: 10px;
      padding: 12px 14px; margin-bottom: 16px;
    }
    .error-box p { font-size: .8rem; color: var(--error); margin-bottom: 2px; }
    .error-box p::before { content: '• '; }

    /* SUCCESS */
    .success-card {
      background: var(--white); border-radius: var(--radius); margin: 20px 16px;
      padding: 32px 20px; text-align: center; box-shadow: 0 2px 16px rgba(0,0,0,.09);
    }
    .success-icon { font-size: 3.5rem; margin-bottom: 12px; display: block; }
    .success-card h2 { font-size: 1.3rem; color: var(--success); margin-bottom: 8px; }
    .success-card p { font-size: .86rem; color: var(--muted); margin-bottom: 20px; line-height: 1.6; }
    .wa-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      background: #25d366; color: #fff; padding: 15px 24px; border-radius: 30px;
      text-decoration: none; font-weight: 700; font-size: 1rem;
      box-shadow: 0 4px 16px rgba(37,211,102,.3); margin: 0 auto 16px; max-width: 300px;
    }
    .wa-btn:active { background: #1da851; }
    .new-btn {
      display: inline-block; padding: 10px 24px; border: 2px solid var(--primary);
      border-radius: 20px; color: var(--primary); font-weight: 600; font-size: .9rem;
      text-decoration: none;
    }

    /* SUBMIT */
    .submit-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; background: var(--primary); color: #fff;
      padding: 15px; border-radius: 12px; border: none;
      font-size: 1rem; font-weight: 700; cursor: pointer;
      transition: background .15s; margin-top: 4px;
    }
    .submit-btn:active { background: var(--primary-dark); }

    /* NOTE */
    .note { font-size: .75rem; color: var(--muted); margin-top: 12px; text-align: center; line-height: 1.5; }

    /* BOTTOM NAV */
    .bottom-nav {
      position: fixed; bottom: 0; left: 0; right: 0; height: var(--bottom-nav-h);
      background: var(--white); border-top: 1px solid #e0e0e0;
      display: flex; box-shadow: 0 -2px 12px rgba(0,0,0,.08); z-index: 300;
    }
    .bnav-item {
      flex: 1; display: flex; flex-direction: column; align-items: center;
      justify-content: center; gap: 3px; text-decoration: none; color: var(--muted);
      font-size: .68rem; font-weight: 600;
    }
    .bnav-item .ico { font-size: 1.3rem; }
    .bnav-item.active { color: var(--primary); }

    @media (min-width: 600px) { .form-card { max-width: 520px; margin: 20px auto; } }
    @media (min-width: 768px) { body { padding-bottom: 0; } .bottom-nav { display: none; } }
  </style>
</head>
<body>

<header>
  <a class="back-btn" href="index.php">←</a>
  <div class="nav-title">📅 Book Appointment</div>
</header>

<div class="appt-hero">
  <h1>Book Your Consultation</h1>
  <p>ನಿಮ್ಮ ಅಪಾಯಿಂಟ್‌ಮೆಂಟ್ ಬುಕ್ ಮಾಡಿ — Quick &amp; Easy</p>
</div>

<?php if ($success): ?>

  <!-- SUCCESS STATE -->
  <div class="success-card">
    <span class="success-icon">✅</span>
    <h2>Appointment Booked!</h2>
    <p>Your request has been recorded. Click the button below to confirm via WhatsApp — our team will respond within 30 minutes.</p>
    <a class="wa-btn" href="<?= htmlspecialchars($waLink) ?>" target="_blank">
      <span>💬</span> Confirm on WhatsApp
    </a>
    <a class="new-btn" href="appointment.php">Book Another</a>
  </div>

<?php else: ?>

  <div class="form-card">

    <?php if (!empty($errors)): ?>
      <div class="error-box">
        <?php foreach ($errors as $e): ?>
          <p><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="apptForm" novalidate>

      <!-- CONSULTATION TYPE -->
      <div class="field">
        <label>Consultation Type <span class="req">*</span></label>
        <div class="type-tabs">
          <?php $types = ['video'=>['📹','Video Call'],'chat'=>['💬','Chat'],'lab'=>['🔬','Lab Test'],'medicine'=>['💊','Medicine']]; ?>
          <?php foreach ($types as $k=>[$icon,$lbl]): ?>
            <div class="type-tab <?= ($preType||'video')===$k?'active':'' ?>" onclick="selectType('<?= $k ?>')" data-type="<?= $k ?>">
              <span class="tab-icon"><?= $icon ?></span><?= $lbl ?>
            </div>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="type" id="typeInput" value="<?= htmlspecialchars($preType ?: 'video') ?>">
      </div>

      <!-- NAME -->
      <div class="field">
        <label for="name">Full Name <span class="req">*</span></label>
        <input type="text" id="name" name="name" placeholder="ನಿಮ್ಮ ಹೆಸರು / Your name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" autocomplete="name" required>
      </div>

      <!-- PHONE -->
      <div class="field">
        <label for="phone">Phone Number <span class="req">*</span></label>
        <input type="tel" id="phone" name="phone" placeholder="+91 98765 43210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" autocomplete="tel" inputmode="tel" required>
      </div>

      <!-- DOCTOR -->
      <div class="field">
        <label for="doctor">Choose Doctor <span class="req">*</span></label>
        <select id="doctor" name="doctor" required>
          <option value="">-- Select a doctor --</option>
          <?php foreach ($doctors as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= (($_POST['doctor'] ?? '')===$d)?'selected':'' ?>>
              <?= htmlspecialchars($d) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- DATE -->
      <div class="field">
        <label for="date">Preferred Date <span class="req">*</span></label>
        <input type="date" id="date" name="date" min="<?= $minDate ?>" max="<?= $maxDate ?>" value="<?= htmlspecialchars($_POST['date'] ?? '') ?>" required>
      </div>

      <!-- TIME SLOTS -->
      <div class="field">
        <label>Preferred Time <span class="req">*</span></label>
        <div class="time-slots">
          <?php foreach ($timeSlots as $slot): ?>
            <button type="button" class="slot-btn <?= (($_POST['time'] ?? '')===$slot)?'selected':'' ?>"
              onclick="selectSlot(this, '<?= $slot ?>')">
              <?= $slot ?>
            </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="time" id="timeInput" value="<?= htmlspecialchars($_POST['time'] ?? '') ?>">
      </div>

      <!-- SYMPTOMS -->
      <div class="field">
        <label for="symptoms">Symptoms / Reason <span style="color:var(--muted);font-weight:400">(optional)</span></label>
        <textarea id="symptoms" name="symptoms" placeholder="Describe your symptoms or reason for visit...&#10;ನಿಮ್ಮ ಲಕ್ಷಣಗಳನ್ನು ವಿವರಿಸಿ..."><?= htmlspecialchars($_POST['symptoms'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="submit-btn">
        📅 Book Appointment
      </button>
      <p class="note">After booking you'll confirm via WhatsApp. No advance payment required.</p>
    </form>
  </div>

<?php endif; ?>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
  <a class="bnav-item" href="index.php"><span class="ico">🏥</span>Telehealth</a>
  <a class="bnav-item" href="doctors.php"><span class="ico">👨‍⚕️</span>Doctors</a>
  <a class="bnav-item active" href="appointment.php"><span class="ico">📅</span>Book</a>
  <a class="bnav-item" href="../index.php"><span class="ico">🛒</span>Shop</a>
</nav>

<script>
function selectType(type) {
  document.querySelectorAll('.type-tab').forEach(t => {
    t.classList.toggle('active', t.dataset.type === type);
  });
  document.getElementById('typeInput').value = type;
}

function selectSlot(btn, slot) {
  document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('timeInput').value = slot;
}

// Pre-select type from URL param
(function() {
  const params = new URLSearchParams(location.search);
  const t = params.get('type');
  if (t) selectType(t);
})();
</script>

</body>
</html>
