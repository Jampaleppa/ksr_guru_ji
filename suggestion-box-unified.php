<?php
// ============================================================
// suggestion-box-unified.php
// CONSOLIDATED: Product Suggestions + Medical Suggestions
// Replaces: suggestion-box.php, final-suggest.php, final-suggestion-box.php
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- CSRF helpers (match parcels.php pattern) ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

function validateCsrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        echo json_encode(['success' => false, 'error' => 'Session expired. Please reload the page.']);
        exit;
    }
}

$pdo = null;
try {
    $pdo = getDBConnection();
    // Ensure tables exist (idempotent)
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isSqlite = $driver === 'sqlite';
    $autoInc = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ts      = $isSqlite ? "TEXT DEFAULT (datetime('now'))"    : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_suggestions (
        id $autoInc,
        user_id INT NULL,
        product_name VARCHAR(255) NOT NULL,
        category_id INT NULL,
        category_name VARCHAR(100) NULL,
        details TEXT NULL,
        preferred_price DECIMAL(10,2) NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(15) NOT NULL,
        customer_email VARCHAR(150) NULL,
        request_id VARCHAR(50) NOT NULL,
        status VARCHAR(30) DEFAULT 'pending',
        created_at $ts
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS suggestions_medical (
        id VARCHAR(36) PRIMARY KEY,
        user_id INT NULL,
        patient_name VARCHAR(255) NOT NULL,
        age INT NOT NULL,
        gender VARCHAR(20) NOT NULL,
        shop_name VARCHAR(255) NOT NULL DEFAULT 'KSR GURU JI Pharmacy',
        symptoms TEXT NULL,
        suggested_meds TEXT NULL,
        diagnosis VARCHAR(255) NULL,
        date TEXT NULL,
        doctor_name VARCHAR(255) NULL,
        suggestion TEXT NOT NULL,
        payment VARCHAR(100) NULL,
        payment_status VARCHAR(50) DEFAULT 'Paid',
        upi_id VARCHAR(100) NULL,
        prescription_image TEXT NULL,
        resolved INTEGER DEFAULT 0,
        created_at $ts
    )");
} catch (Throwable $e) {
    error_log('suggestion-box-unified table init: ' . $e->getMessage());
}

// ---- Prefill from query string (redirected from search) ----
$prefillProduct = trim((string)($_GET['product'] ?? ''));
$isNotFoundRedirect = !empty($_GET['not_found']);
$activeTab = $_GET['tab'] ?? 'product'; // 'product' | 'medical'

// ---- API: JSON responses ----
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'];

    if ($action === 'submit_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCsrf();
        $productName   = trim((string)($_POST['product_name'] ?? ''));
        $customerPhone = preg_replace('/[^0-9]/', '', (string)($_POST['customer_phone'] ?? ''));
        $customerName  = trim((string)($_POST['customer_name'] ?? ''));
        $details       = trim((string)($_POST['details'] ?? ''));
        $preferredPrice = !empty($_POST['preferred_price']) ? (float)$_POST['preferred_price'] : null;
        $categoryId    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

        if (empty($productName)) { echo json_encode(['success' => false, 'error' => 'Product name required.']); exit; }
        if (strlen($customerPhone) < 10) { echo json_encode(['success' => false, 'error' => 'Valid 10-digit phone required.']); exit; }

        try {
            $requestId = 'KSR-PROD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $categoryName = null;
            if ($categoryId) {
                $st = $pdo->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
                $st->execute([$categoryId]);
                $categoryName = $st->fetchColumn() ?: null;
            }
            $userId = $_SESSION['user_id'] ?? null;
            $stmt = $pdo->prepare("INSERT INTO product_suggestions
                (user_id, product_name, category_id, category_name, details, preferred_price, customer_name, customer_phone, request_id)
                VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$userId, $productName, $categoryId, $categoryName, $details, $preferredPrice, $customerName, $customerPhone, $requestId]);
            echo json_encode(['success' => true, 'request_id' => $requestId, 'message' => "✅ Thank you! We'll notify you when {$productName} is available. Ref: {$requestId}"]);
        } catch (Throwable $e) {
            error_log('product_suggestion insert: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
        }
        exit;
    }

    if ($action === 'submit_medical' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCsrf();
        $patientName  = trim((string)($_POST['patient_name'] ?? ''));
        $age          = (int)($_POST['age'] ?? 0);
        $gender       = trim((string)($_POST['gender'] ?? ''));
        $symptoms     = trim((string)($_POST['symptoms'] ?? ''));
        $suggestion   = trim((string)($_POST['suggestion'] ?? ''));
        $diagnosis    = trim((string)($_POST['diagnosis'] ?? ''));
        $suggestedMeds= trim((string)($_POST['suggested_meds'] ?? ''));
        $doctorName   = trim((string)($_POST['doctor_name'] ?? ''));
        $payment      = trim((string)($_POST['payment'] ?? 'Cash on Delivery'));

        if (empty($patientName) || $age < 1 || empty($gender) || empty($suggestion)) {
            echo json_encode(['success' => false, 'error' => 'Patient name, age, gender and suggestion are required.']);
            exit;
        }

        try {
            $requestId = 'KSR-MED-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $userId = $_SESSION['user_id'] ?? null;
            $shopName = trim((string)($_POST['shop_name'] ?? 'KSR GURU JI Pharmacy'));
            $stmt = $pdo->prepare("INSERT INTO suggestions_medical
                (id, user_id, patient_name, age, gender, shop_name, symptoms, diagnosis, suggested_meds, date, doctor_name, suggestion, payment)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$requestId, $userId, $patientName, $age, $gender, $shopName, $symptoms, $diagnosis, $suggestedMeds, date('Y-m-d'), $doctorName, $suggestion, $payment]);
            echo json_encode(['success' => true, 'request_id' => $requestId, 'message' => "✅ Medical suggestion submitted. Ref: {$requestId}"]);
        } catch (Throwable $e) {
            error_log('medical_suggestion insert: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit;
}

// ---- Categories for dropdown ----
$categories = [];
try {
    if ($pdo) {
        $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $ignore) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Suggestion Box – KSR GURU JI</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0f7b3a">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  :root { --green:#0f7b3a; --green-light:#e8f5ee; --red:#e53e3e; --gray:#f7f7f7; --radius:14px; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Segoe UI',sans-serif; background:var(--gray); color:#222; min-height:100vh; }
  header { background:var(--green); color:#fff; padding:16px 20px; display:flex; align-items:center; gap:12px; }
  header h1 { font-size:1.1rem; }
  .container { max-width:600px; margin:24px auto; padding:0 16px 100px; }
  .tabs { display:flex; border-radius:var(--radius); overflow:hidden; border:2px solid var(--green); margin-bottom:20px; }
  .tab-btn { flex:1; padding:12px; background:#fff; border:none; cursor:pointer; font-size:.95rem; font-weight:600; color:var(--green); transition:.2s; }
  .tab-btn.active { background:var(--green); color:#fff; }
  .tab-pane { display:none; }
  .tab-pane.active { display:block; }
  .card { background:#fff; border-radius:var(--radius); padding:20px; box-shadow:0 2px 10px rgba(0,0,0,.07); }
  label { display:block; font-size:.85rem; font-weight:600; margin-bottom:4px; color:#444; }
  input,select,textarea { width:100%; padding:11px 14px; border:1.5px solid #ddd; border-radius:10px; font-size:.95rem; margin-bottom:14px; outline:none; transition:.2s; }
  input:focus,select:focus,textarea:focus { border-color:var(--green); }
  textarea { resize:vertical; min-height:80px; }
  .btn { width:100%; padding:14px; background:var(--green); color:#fff; border:none; border-radius:var(--radius); font-size:1rem; font-weight:700; cursor:pointer; }
  .btn:active { opacity:.85; }
  .alert { border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:.9rem; display:none; }
  .alert.success { background:#e8f5ee; color:#0f7b3a; border:1px solid #c3e6cb; display:block; }
  .alert.error   { background:#fff0f0; color:var(--red); border:1px solid #ffcdd2; display:block; }
  .back-link { display:inline-block; margin-bottom:16px; color:var(--green); font-weight:600; text-decoration:none; }
  .back-link:hover { text-decoration:underline; }
  .optional { color:#888; font-weight:400; font-size:.8rem; }
</style>
</head>
<body>
<header>
  <a href="/index.php" style="color:#fff;font-size:1.3rem;"><i class="fas fa-arrow-left"></i></a>
  <h1>💡 Suggestion Box – KSR GURU JI</h1>
</header>
<div class="container">
  <?php if ($isNotFoundRedirect): ?>
  <div class="alert success" style="display:block;">
    We searched for <strong><?= htmlspecialchars($prefillProduct) ?></strong> but couldn't find it in our store. Use the form below to request it!
  </div>
  <?php endif; ?>
  <a href="javascript:history.back()" class="back-link"><i class="fas fa-chevron-left"></i> Back</a>

  <div class="tabs">
    <button class="tab-btn <?= $activeTab==='product'?'active':'' ?>" onclick="showTab('product')">🛒 Product Request</button>
    <button class="tab-btn <?= $activeTab==='medical'?'active':'' ?>" onclick="showTab('medical')">💊 Medical Suggestion</button>
  </div>

  <!-- Product Suggestion Tab -->
  <div class="tab-pane <?= $activeTab==='product'?'active':'' ?>" id="tab-product">
    <div class="card">
      <div id="prod-alert" class="alert"></div>
      <form id="prod-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <label>Product Name *</label>
        <input name="product_name" required placeholder="e.g. Ashirwad Atta 10kg" value="<?= htmlspecialchars($prefillProduct) ?>">
        <label>Category <span class="optional">(optional)</span></label>
        <select name="category_id">
          <option value="">— Select Category —</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Preferred Price ₹ <span class="optional">(optional)</span></label>
        <input name="preferred_price" type="number" min="0" placeholder="e.g. 250">
        <label>Details <span class="optional">(brand, weight, quantity, etc.)</span></label>
        <textarea name="details" placeholder="Any details about the product..."></textarea>
        <label>Your Name *</label>
        <input name="customer_name" required placeholder="Your Name">
        <label>Mobile Number *</label>
        <input name="customer_phone" required type="tel" placeholder="10-digit mobile number" maxlength="10">
        <label>Email <span class="optional">(optional)</span></label>
        <input name="customer_email" type="email" placeholder="yourname@email.com">
        <button type="submit" class="btn">📩 Submit Product Request</button>
      </form>
    </div>
  </div>

  <!-- Medical Suggestion Tab -->
  <div class="tab-pane <?= $activeTab==='medical'?'active':'' ?>" id="tab-medical">
    <div class="card">
      <div id="med-alert" class="alert"></div>
      <form id="med-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <label>Patient Name *</label>
        <input name="patient_name" required placeholder="Patient full name">
        <label>Age *</label>
        <input name="age" required type="number" min="1" max="120" placeholder="Age in years">
        <label>Gender *</label>
        <select name="gender" required>
          <option value="">— Select —</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
        <label>Symptoms <span class="optional">(optional)</span></label>
        <textarea name="symptoms" placeholder="Describe the symptoms..."></textarea>
        <label>Diagnosis <span class="optional">(optional)</span></label>
        <input name="diagnosis" placeholder="e.g. Fever, Cold">
        <label>Suggested Medicines <span class="optional">(optional)</span></label>
        <textarea name="suggested_meds" placeholder="Names of medicines you need..."></textarea>
        <label>Doctor Name <span class="optional">(optional)</span></label>
        <input name="doctor_name" placeholder="Prescribing doctor's name">
        <label>Your Suggestion / Message *</label>
        <textarea name="suggestion" required placeholder="Describe what you need or any additional notes..."></textarea>
        <label>Payment Method</label>
        <select name="payment">
          <option value="Cash on Delivery">Cash on Delivery</option>
          <option value="UPI">UPI</option>
          <option value="Online">Online</option>
        </select>
        <button type="submit" class="btn">💊 Submit Medical Suggestion</button>
      </form>
    </div>
  </div>
</div>

<script>
function showTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  document.querySelectorAll('.tab-btn').forEach(b => { if (b.textContent.toLowerCase().includes(tab === 'product' ? 'product' : 'medical')) b.classList.add('active'); });
}

function showAlert(id, type, msg) {
  const el = document.getElementById(id);
  el.className = 'alert ' + type;
  el.textContent = msg;
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

document.getElementById('prod-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('.btn');
  btn.disabled = true; btn.textContent = 'Submitting…';
  const fd = new FormData(this);
  const res = await fetch('?ajax=submit_product', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showAlert('prod-alert', 'success', data.message);
    this.reset();
  } else {
    showAlert('prod-alert', 'error', data.error || 'Submission failed.');
  }
  btn.disabled = false; btn.textContent = '📩 Submit Product Request';
});

document.getElementById('med-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('.btn');
  btn.disabled = true; btn.textContent = 'Submitting…';
  const fd = new FormData(this);
  const res = await fetch('?ajax=submit_medical', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    showAlert('med-alert', 'success', data.message);
    this.reset();
  } else {
    showAlert('med-alert', 'error', data.error || 'Submission failed.');
  }
  btn.disabled = false; btn.textContent = '💊 Submit Medical Suggestion';
});
</script>
</body>
</html>
