<?php
// ============================================================
// addresses.php - Customer Address Book
// Logged-in customers can add, edit, delete and set a default
// delivery address.  Addresses are stored in customer_addresses.
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('/addresses.php'));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pdo    = getDBConnection();

// ── Ensure table exists ──────────────────────────────────────
$isMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite';
$pdo->exec($isMysql
    ? "CREATE TABLE IF NOT EXISTS customer_addresses (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT NOT NULL,
        label       VARCHAR(50)  NOT NULL DEFAULT 'Home',
        full_name   VARCHAR(120) NOT NULL,
        phone       VARCHAR(15)  NOT NULL,
        address     TEXT         NOT NULL,
        city        VARCHAR(80)  NOT NULL,
        state       VARCHAR(80)  NOT NULL DEFAULT 'Karnataka',
        pincode     VARCHAR(10)  NOT NULL,
        is_default  TINYINT(1)   NOT NULL DEFAULT 0,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    : "CREATE TABLE IF NOT EXISTS customer_addresses (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL,
        label      TEXT    NOT NULL DEFAULT 'Home',
        full_name  TEXT    NOT NULL,
        phone      TEXT    NOT NULL,
        address    TEXT    NOT NULL,
        city       TEXT    NOT NULL,
        state      TEXT    NOT NULL DEFAULT 'Karnataka',
        pincode    TEXT    NOT NULL,
        is_default INTEGER NOT NULL DEFAULT 0,
        created_at TEXT    NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT    NOT NULL DEFAULT (datetime('now'))
       )"
);

// ── CSRF ─────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

function validateCsrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Session expired. Please reload the page.');
    }
}

$message = '';
$error   = '';

// ── POST handlers ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $label    = trim($_POST['label']     ?? 'Home');
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
        $addr     = trim($_POST['address']   ?? '');
        $city     = trim($_POST['city']      ?? '');
        $state    = trim($_POST['state']     ?? 'Karnataka');
        $pincode  = trim($_POST['pincode']   ?? '');
        $isDefault= !empty($_POST['is_default']) ? 1 : 0;

        if (empty($fullName) || strlen($phone) < 10 || empty($addr) || empty($city) || empty($pincode)) {
            $error = 'Please fill all required fields (name, phone, address, city, pincode).';
        } else {
            if ($isDefault) {
                $pdo->prepare("UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
            }
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO customer_addresses
                    (user_id, label, full_name, phone, address, city, state, pincode, is_default)
                    VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$userId, $label, $fullName, $phone, $addr, $city, $state, $pincode, $isDefault]);
                $message = '✅ Address added successfully.';
            } else {
                $addrId = (int)($_POST['address_id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE customer_addresses
                    SET label=?, full_name=?, phone=?, address=?, city=?, state=?, pincode=?, is_default=?
                    WHERE id=? AND user_id=?");
                $stmt->execute([$label, $fullName, $phone, $addr, $city, $state, $pincode, $isDefault, $addrId, $userId]);
                $message = '✅ Address updated successfully.';
            }
        }
    }

    if ($action === 'delete') {
        $addrId = (int)($_POST['address_id'] ?? 0);
        $pdo->prepare("DELETE FROM customer_addresses WHERE id = ? AND user_id = ?")->execute([$addrId, $userId]);
        $message = 'Address removed.';
    }

    if ($action === 'set_default') {
        $addrId = (int)($_POST['address_id'] ?? 0);
        $pdo->prepare("UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("UPDATE customer_addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$addrId, $userId]);
        $message = '✅ Default address updated.';
    }
}

// ── Load addresses ───────────────────────────────────────────
$addresses = $pdo->prepare("SELECT * FROM customer_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$addresses->execute([$userId]);
$addresses = $addresses->fetchAll(PDO::FETCH_ASSOC);

$editAddr = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($addresses as $a) {
        if ((int)$a['id'] === $editId) { $editAddr = $a; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Addresses – KSR GURU JI</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0f7b3a">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  :root { --green:#0f7b3a; --green-light:#e8f5ee; --red:#e53e3e; --radius:14px; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Segoe UI',sans-serif; background:#f7f7f7; color:#222; }
  header { background:var(--green); color:#fff; padding:14px 20px; display:flex; align-items:center; gap:12px; }
  header h1 { font-size:1.05rem; }
  .container { max-width:640px; margin:20px auto; padding:0 16px 100px; }
  .card { background:#fff; border-radius:var(--radius); padding:20px; box-shadow:0 2px 10px rgba(0,0,0,.07); margin-bottom:16px; }
  .addr-card { position:relative; border-left:4px solid var(--green); }
  .addr-card.default-addr { border-left-color:#f59e0b; }
  .badge { background:#f59e0b; color:#fff; border-radius:20px; font-size:.72rem; padding:2px 8px; font-weight:700; vertical-align:middle; }
  .addr-label { font-size:.8rem; font-weight:700; color:var(--green); text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
  .addr-name { font-weight:700; font-size:1rem; margin-bottom:2px; }
  .addr-line { color:#555; font-size:.9rem; margin-bottom:2px; }
  .addr-actions { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
  .btn-sm { padding:7px 14px; border:none; border-radius:8px; font-size:.85rem; font-weight:600; cursor:pointer; }
  .btn-edit   { background:#3b82f6; color:#fff; }
  .btn-del    { background:var(--red); color:#fff; }
  .btn-def    { background:#f59e0b; color:#fff; }
  .btn-add    { background:var(--green); color:#fff; padding:12px 20px; font-size:.95rem; border-radius:var(--radius); border:none; cursor:pointer; font-weight:700; display:block; width:100%; }
  label { display:block; font-size:.85rem; font-weight:600; margin-bottom:4px; color:#444; }
  input, select, textarea { width:100%; padding:10px 13px; border:1.5px solid #ddd; border-radius:9px; font-size:.93rem; margin-bottom:12px; outline:none; }
  input:focus, select:focus { border-color:var(--green); }
  .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .alert { padding:11px 15px; border-radius:9px; margin-bottom:14px; font-size:.9rem; }
  .alert.ok  { background:#e8f5ee; color:var(--green); border:1px solid #c3e6cb; }
  .alert.err { background:#fff0f0; color:var(--red); border:1px solid #ffcdd2; }
  .section-title { font-size:1rem; font-weight:700; margin:20px 0 12px; color:#333; }
  .empty-state { text-align:center; color:#888; padding:30px 0; }
</style>
</head>
<body>
<header>
  <a href="profile.php" style="color:#fff;font-size:1.2rem;"><i class="fas fa-arrow-left"></i></a>
  <h1>📍 My Delivery Addresses</h1>
</header>
<div class="container">
  <?php if ($message): ?><div class="alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Saved Addresses -->
  <p class="section-title">Saved Addresses (<?= count($addresses) ?>)</p>
  <?php if (empty($addresses)): ?>
  <div class="empty-state"><i class="fas fa-map-marker-alt" style="font-size:2rem;color:#ccc;"></i><br><br>No saved addresses yet. Add one below.</div>
  <?php endif; ?>
  <?php foreach ($addresses as $a): ?>
  <div class="card addr-card <?= $a['is_default'] ? 'default-addr' : '' ?>">
    <div class="addr-label"><?= htmlspecialchars($a['label']) ?><?= $a['is_default'] ? ' &nbsp;<span class="badge">Default</span>' : '' ?></div>
    <div class="addr-name"><?= htmlspecialchars($a['full_name']) ?> &nbsp;<span style="color:#888;font-weight:400;font-size:.9rem;"><?= htmlspecialchars($a['phone']) ?></span></div>
    <div class="addr-line"><?= htmlspecialchars($a['address']) ?></div>
    <div class="addr-line"><?= htmlspecialchars($a['city']) ?>, <?= htmlspecialchars($a['state']) ?> – <?= htmlspecialchars($a['pincode']) ?></div>
    <div class="addr-actions">
      <a href="?edit=<?= $a['id'] ?>" class="btn-sm btn-edit"><i class="fas fa-edit"></i> Edit</a>
      <?php if (!$a['is_default']): ?>
      <form method="POST" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="set_default">
        <input type="hidden" name="address_id" value="<?= $a['id'] ?>">
        <button type="submit" class="btn-sm btn-def"><i class="fas fa-check-circle"></i> Set Default</button>
      </form>
      <?php endif; ?>
      <form method="POST" style="margin:0;" onsubmit="return confirm('Remove this address?');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="address_id" value="<?= $a['id'] ?>">
        <button type="submit" class="btn-sm btn-del"><i class="fas fa-trash"></i> Remove</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Add / Edit Form -->
  <p class="section-title"><?= $editAddr ? '✏️ Edit Address' : '➕ Add New Address' ?></p>
  <div class="card">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="action"     value="<?= $editAddr ? 'edit' : 'add' ?>">
      <?php if ($editAddr): ?><input type="hidden" name="address_id" value="<?= (int)$editAddr['id'] ?>"><?php endif; ?>

      <label>Label (Home / Work / Other)</label>
      <select name="label">
        <?php foreach (['Home','Work','Other'] as $lbl): ?>
        <option value="<?= $lbl ?>" <?= ($editAddr['label'] ?? 'Home') === $lbl ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>

      <div class="form-row">
        <div>
          <label>Full Name *</label>
          <input name="full_name" required placeholder="Recipient name" value="<?= htmlspecialchars($editAddr['full_name'] ?? '') ?>">
        </div>
        <div>
          <label>Phone *</label>
          <input name="phone" required type="tel" placeholder="10-digit mobile" maxlength="15" value="<?= htmlspecialchars($editAddr['phone'] ?? '') ?>">
        </div>
      </div>

      <label>Address *</label>
      <textarea name="address" required placeholder="Flat / Building / Street / Landmark"><?= htmlspecialchars($editAddr['address'] ?? '') ?></textarea>

      <div class="form-row">
        <div>
          <label>City *</label>
          <input name="city" required placeholder="e.g. Gajendragad" value="<?= htmlspecialchars($editAddr['city'] ?? '') ?>">
        </div>
        <div>
          <label>Pincode *</label>
          <input name="pincode" required placeholder="6-digit pincode" maxlength="6" value="<?= htmlspecialchars($editAddr['pincode'] ?? '') ?>">
        </div>
      </div>

      <label>State</label>
      <input name="state" placeholder="Karnataka" value="<?= htmlspecialchars($editAddr['state'] ?? 'Karnataka') ?>">

      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:16px;">
        <input type="checkbox" name="is_default" value="1" style="width:auto;margin:0;" <?= !empty($editAddr['is_default']) ? 'checked' : '' ?>>
        Set as default delivery address
      </label>

      <button type="submit" class="btn-add">
        <i class="fas fa-<?= $editAddr ? 'save' : 'plus' ?>"></i> <?= $editAddr ? 'Save Changes' : 'Add Address' ?>
      </button>
      <?php if ($editAddr): ?><br><a href="addresses.php" style="display:block;text-align:center;margin-top:12px;color:#888;">Cancel Edit</a><?php endif; ?>
    </form>
  </div>
</div>
</body>
</html>
