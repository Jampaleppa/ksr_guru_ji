<?php

declare(strict_types=1);
session_start();

/* ============================================================
   KSR GURU JI  •  PARCEL TO DOOR  •  ₹50 FIXED (CASH ON DELIVERY)
   Complete Module: Customer Booking + Live GPS Location + 
   Instant WhatsApp Sharing + Live Tracking + Driver + Admin
   PHP 7.4+ / 8.x   •   SQLite (zero config) or MySQL
   ============================================================ */

/* ------------------------- CONFIG ------------------------- */
require_once __DIR__ . '/../config.php';

const APP_NAME       = 'KSR GURU JI';
const APP_SUBTITLE   = 'PARCEL TO DOOR • LOCAL SAME-DAY EXPRESS';
const DB_DRIVER      = 'mysql';                  // 'sqlite' | 'mysql'
const DB_PATH        = __DIR__ . '/parcel.sqlite';
const PARCEL_CHARGE  = 50;                       // fixed door-delivery charge
const STAFF_PIN      = '2468';                   // driver + admin PIN
const CURRENCY       = '₹';

/* -------- contact / WhatsApp numbers -------- */
const OWNER_NAME     = 'KSR GURU JI Owner';
const OWNER_PHONE    = '8088584421';
const OWNER_WHATSAPP = '918088584421';           // Owner WhatsApp

const DRIVER_NAME    = 'Delivery Partner';
const DRIVER_PHONE   = '8762474406';
const DRIVER_WHATSAPP= '918762474406';          // Driver WhatsApp

const JK_NAME        = 'JK';
const JK_PHONE       = '8088584421';
const JK_WHATSAPP    = '918088584421';

const SHOP_ADDRESS   = 'Main Road, Near Bus Stand, Gajendragad';
const SHOP_TAGLINE   = 'Local · Fast · Reliable · Door-to-Door';
/* ---------------------------------------------------------- */

/* ======================= HELPERS ========================== */
function h(?string $s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function money(float $n): string
{
  return CURRENCY . number_format($n, 0);
}
function redirect(string $u): void
{
  header('Location: ' . $u);
  exit;
}
function is_staff(): bool
{
  return !empty($_SESSION['staff']);
}

function csrf_token(): string
{
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_verify(): void
{
  if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
    http_response_code(419);
    exit('Session expired. Please reload the page.');
  }
}
function flash(?string $msg = null, string $type = 'ok')
{
  if ($msg !== null) {
    $_SESSION['flash'] = ['m' => $msg, 't' => $type];
    return null;
  }
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $f;
}
function new_order_code(): string
{
  return 'KSR-P-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}
function new_token(): string
{
  return bin2hex(random_bytes(16));
}

function cleanPhone(string $phone): string
{
  $p = preg_replace('/\D/', '', $phone);
  if (strlen($p) === 10) return '91' . $p;
  return $p;
}

function waLink(string $phone, string $text): string
{
  $num = cleanPhone($phone);
  return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
}

function waAnyLink(string $text): string
{
  return 'https://api.whatsapp.com/send?text=' . rawurlencode($text);
}

function trackLink(array $row): string
{
  return '?page=track&code=' . urlencode((string)$row['order_code'])
    . '&m=' . urlencode((string)$row['pickup_mobile']);
}

function fullTrackUrl(array $row): string
{
  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
  return $protocol . $host . $uri . trackLink($row);
}

function mapsSearchUrl(string $address, string $coords = ''): string
{
  if (!empty($coords) && strpos($coords, ',') !== false) {
    return 'https://www.google.com/maps?q=' . urlencode($coords);
  }
  return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address);
}

function mapsDirectionsUrl(string $address, string $coords = ''): string
{
  if (!empty($coords) && strpos($coords, ',') !== false) {
    return 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($coords);
  }
  return 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($address);
}

function buildWhatsAppMessage(array $row): string
{
  $trackUrl = fullTrackUrl($row);
  $pickupMap = mapsSearchUrl($row['pickup_address'], $row['pickup_coords'] ?? '');
  $delivMap  = mapsSearchUrl($row['delivery_address'], $row['delivery_coords'] ?? '');
  
  $msg  = "📦 *KSR GURU JI - PARCEL TO DOOR*\n";
  $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
  $msg .= "🆔 *Order ID:* " . $row['order_code'] . "\n";
  $msg .= "📅 *Booked:* " . ($row['created_at'] ?? date('Y-m-d H:i')) . "\n\n";

  $msg .= "📍 *PICKUP DETAILS:*\n";
  $msg .= "• Name: " . $row['pickup_name'] . "\n";
  $msg .= "• Mobile: " . $row['pickup_mobile'] . "\n";
  $msg .= "• Address: " . $row['pickup_address'];
  if (!empty($row['pickup_landmark'])) $msg .= " (Near: " . $row['pickup_landmark'] . ")";
  $msg .= "\n";
  if (!empty($row['pickup_coords'])) {
    $msg .= "• 📍 Live GPS Pin: " . $pickupMap . "\n";
  } else {
    $msg .= "• 🗺️ Map Search: " . $pickupMap . "\n";
  }
  $msg .= "\n";

  $msg .= "🏠 *DELIVERY DETAILS:*\n";
  $msg .= "• Name: " . $row['receiver_name'] . "\n";
  $msg .= "• Mobile: " . $row['receiver_mobile'] . "\n";
  $msg .= "• Address: " . $row['delivery_address'];
  if (!empty($row['delivery_landmark'])) $msg .= " (Near: " . $row['delivery_landmark'] . ")";
  $msg .= "\n";
  if (!empty($row['delivery_coords'])) {
    $msg .= "• 📍 Drop GPS Pin: " . $delivMap . "\n";
  } else {
    $msg .= "• 🗺️ Map Search: " . $delivMap . "\n";
  }
  $msg .= "\n";

  $msg .= "📦 *PARCEL INFO:*\n";
  $msg .= "• Type: " . $row['parcel_type'];
  if (!empty($row['weight'])) $msg .= " (" . $row['weight'] . " kg)";
  $msg .= "\n";
  if (!empty($row['description'])) $msg .= "• Notes: " . $row['description'] . "\n";
  $msg .= "\n";

  $msg .= "💵 *PAYMENT:*\n";
  $msg .= "• Method: *CASH ON DELIVERY (COD)*\n";
  $msg .= "• Total Charge: " . money((float)$row['amount']) . " (Pay upon pickup/delivery)\n";
  $msg .= "• Payment Status: " . label($row['payment_status']) . "\n\n";

  $msg .= "🚚 *DELIVERY STATUS:* " . label($row['delivery_status']) . "\n";
  if (!empty($row['driver_name'])) $msg .= "• Driver Assigned: " . $row['driver_name'] . "\n";
  $msg .= "\n";

  $msg .= "🔎 *TRACK LIVE STATUS:*\n" . $trackUrl . "\n";
  $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
  $msg .= "KSR GURU JI • Local · Fast · Reliable";

  return $msg;
}

/* ========================== DB ============================ */
function db(): PDO
{
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;
  if (DB_DRIVER === 'sqlite') {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->exec('PRAGMA journal_mode = WAL');
  } else {
    $pdo = getDBConnection();
  }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $pdo;
}

function db_init(): void
{
  if (DB_DRIVER === 'sqlite') {
    db()->exec("CREATE TABLE IF NOT EXISTS parcel_requests (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            order_code       TEXT NOT NULL UNIQUE,
            access_token     TEXT NOT NULL,
            pickup_name      TEXT NOT NULL,
            pickup_mobile    TEXT NOT NULL,
            pickup_address   TEXT NOT NULL,
            pickup_landmark  TEXT DEFAULT '',
            pickup_coords    TEXT DEFAULT '',
            receiver_name    TEXT NOT NULL,
            receiver_mobile  TEXT NOT NULL,
            delivery_address TEXT NOT NULL,
            delivery_landmark TEXT DEFAULT '',
            delivery_coords  TEXT DEFAULT '',
            parcel_type      TEXT NOT NULL,
            weight           TEXT DEFAULT '',
            description      TEXT DEFAULT '',
            amount           NUMERIC NOT NULL DEFAULT 50,
            payment_method   TEXT NOT NULL DEFAULT 'CASH_ON_DELIVERY',
            payment_status   TEXT NOT NULL DEFAULT 'PENDING',
            delivery_status  TEXT NOT NULL DEFAULT 'REQUESTED',
            payment_ref      TEXT DEFAULT '',
            driver_name      TEXT DEFAULT '',
            driver_note      TEXT DEFAULT '',
            created_at       TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at       TEXT DEFAULT CURRENT_TIMESTAMP
        )");

    // Safe column migrations: check existing columns first to avoid duplicate column exceptions
    $existing = [];
    try {
      $st = db()->query("PRAGMA table_info(parcel_requests)");
      if ($st) {
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
          if (!empty($row['name'])) $existing[$row['name']] = true;
        }
      }
    } catch (\Throwable $e) {}

    $cols = [
      'pickup_coords'   => "TEXT DEFAULT ''",
      'delivery_coords' => "TEXT DEFAULT ''",
      'payment_method'  => "TEXT DEFAULT 'CASH_ON_DELIVERY'"
    ];
    foreach ($cols as $col => $def) {
      if (!isset($existing[$col])) {
        try {
          db()->exec("ALTER TABLE parcel_requests ADD COLUMN $col $def");
        } catch (\Throwable $e) {}
      }
    }
  } else {
    db()->exec("CREATE TABLE IF NOT EXISTS parcel_requests (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            order_code       VARCHAR(32) NOT NULL UNIQUE,
            access_token     VARCHAR(64) NOT NULL,
            pickup_name      VARCHAR(120) NOT NULL,
            pickup_mobile    VARCHAR(15)  NOT NULL,
            pickup_address   TEXT NOT NULL,
            pickup_landmark  VARCHAR(160) DEFAULT '',
            pickup_coords    VARCHAR(100) DEFAULT '',
            receiver_name    VARCHAR(120) NOT NULL,
            receiver_mobile  VARCHAR(15)  NOT NULL,
            delivery_address TEXT NOT NULL,
            delivery_landmark VARCHAR(160) DEFAULT '',
            delivery_coords  VARCHAR(100) DEFAULT '',
            parcel_type      VARCHAR(40)  NOT NULL,
            weight           VARCHAR(20)  DEFAULT '',
            description      TEXT,
            amount           DECIMAL(10,2) NOT NULL DEFAULT 50,
            payment_method   VARCHAR(30) NOT NULL DEFAULT 'CASH_ON_DELIVERY',
            payment_status   VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            delivery_status  VARCHAR(30) NOT NULL DEFAULT 'REQUESTED',
            payment_ref      VARCHAR(64) DEFAULT '',
            driver_name      VARCHAR(120) DEFAULT '',
            driver_note      TEXT,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $existing = [];
    try {
      $st = db()->query("SHOW COLUMNS FROM parcel_requests");
      if ($st) {
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
          if (!empty($row['Field'])) $existing[$row['Field']] = true;
        }
      }
    } catch (\Throwable $e) {}

    $cols = [
      'pickup_coords'   => "VARCHAR(100) DEFAULT ''",
      'delivery_coords' => "VARCHAR(100) DEFAULT ''",
      'payment_method'  => "VARCHAR(30) NOT NULL DEFAULT 'CASH_ON_DELIVERY'"
    ];
    foreach ($cols as $col => $def) {
      if (!isset($existing[$col])) {
        try {
          db()->exec("ALTER TABLE parcel_requests ADD COLUMN $col $def");
        } catch (\Throwable $e) {}
      }
    }
  }
}
db_init();

/* ==================== DOMAIN CONSTANTS ==================== */
const PARCEL_TYPES = ['Document', 'Small Parcel', 'Food Parcel', 'Grocery Parcel', 'Medicines', 'Clothing', 'Other'];

const PAYMENT_STATUSES = ['PENDING', 'CONFIRMED', 'CANCELLED'];

const DELIVERY_FLOW = ['REQUESTED', 'PICKUP_ASSIGNED', 'PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED'];
const DELIVERY_ALL  = ['REQUESTED', 'PICKUP_ASSIGNED', 'PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED', 'FAILED', 'CANCELLED'];

function status_slug(string $s): string
{
  return match ($s) {
    'CONFIRMED', 'DELIVERED'         => 'green',
    'PENDING', 'REQUESTED'           => 'amber',
    'FAILED', 'CANCELLED'            => 'red',
    'PICKUP_ASSIGNED', 'PICKED_UP'   => 'teal',
    'IN_TRANSIT', 'OUT_FOR_DELIVERY' => 'blue',
    default                          => 'grey',
  };
}
function label(string $s): string
{
  return ucwords(strtolower(str_replace('_', ' ', $s)));
}

// Logo resolver (local relative paths)
$logoUrl = 'company_logo.jpg';
if (file_exists(__DIR__ . '/company_logo.jpg')) {
  $logoUrl = 'company_logo.jpg';
} elseif (file_exists(__DIR__ . '/../company_logo.jpg')) {
  $logoUrl = '../company_logo.jpg';
} elseif (file_exists(__DIR__ . '/../KSR_GURU_JI_NEW.png')) {
  $logoUrl = '../KSR_GURU_JI_NEW.png';
}

/* ==================== POST / ACTIONS ====================== */
$page   = $_GET['page'] ?? 'home';
$errors = [];
$form   = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  /* ---------- 1. CREATE REQUEST (ONLY CASH AND DELIVERY) ---------- */
  if ($action === 'create_request') {
    $f = [
      'pickup_name'       => trim($_POST['pickup_name'] ?? ''),
      'pickup_mobile'     => preg_replace('/\D/', '', $_POST['pickup_mobile'] ?? ''),
      'pickup_address'    => trim($_POST['pickup_address'] ?? ''),
      'pickup_landmark'   => trim($_POST['pickup_landmark'] ?? ''),
      'pickup_coords'     => trim($_POST['pickup_coords'] ?? ''),
      'receiver_name'     => trim($_POST['receiver_name'] ?? ''),
      'receiver_mobile'   => preg_replace('/\D/', '', $_POST['receiver_mobile'] ?? ''),
      'delivery_address'  => trim($_POST['delivery_address'] ?? ''),
      'delivery_landmark' => trim($_POST['delivery_landmark'] ?? ''),
      'delivery_coords'   => trim($_POST['delivery_coords'] ?? ''),
      'parcel_type'       => $_POST['parcel_type'] ?? 'Document',
      'weight'            => trim($_POST['weight'] ?? ''),
      'description'       => trim($_POST['description'] ?? ''),
    ];

    foreach (
      [
        'pickup_name' => 'Pickup name',
        'pickup_address' => 'Pickup address',
        'receiver_name' => 'Receiver name',
        'delivery_address' => 'Delivery address'
      ] as $k => $lbl
    ) {
      if ($f[$k] === '') $errors[$k] = "$lbl is required.";
    }
    foreach (['pickup_mobile' => 'Pickup mobile', 'receiver_mobile' => 'Receiver mobile'] as $k => $lbl) {
      if (!preg_match('/^[6-9]\d{9}$/', $f[$k])) $errors[$k] = "$lbl must be a valid 10-digit number.";
    }
    if (!in_array($f['parcel_type'], PARCEL_TYPES, true)) $f['parcel_type'] = 'Other';
    if ($f['weight'] !== '' && !is_numeric($f['weight'])) $errors['weight'] = 'Weight must be a number.';

    if ($errors) {
      $page = 'home';
      $form = $f;
      flash('Please correct the highlighted fields.', 'err');
    } else {
      $code  = new_order_code();
      $token = new_token();
      $sql = "INSERT INTO parcel_requests
                (order_code, access_token, pickup_name, pickup_mobile, pickup_address, pickup_landmark, pickup_coords,
                 receiver_name, receiver_mobile, delivery_address, delivery_landmark, delivery_coords,
                 parcel_type, weight, description, amount, payment_method, payment_status, delivery_status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?," . PARCEL_CHARGE . ", 'CASH_ON_DELIVERY', 'PENDING', 'REQUESTED')";
      db()->prepare($sql)->execute([
        $code,
        $token,
        $f['pickup_name'],
        $f['pickup_mobile'],
        $f['pickup_address'],
        $f['pickup_landmark'],
        $f['pickup_coords'],
        $f['receiver_name'],
        $f['receiver_mobile'],
        $f['delivery_address'],
        $f['delivery_landmark'],
        $f['delivery_coords'],
        $f['parcel_type'],
        $f['weight'],
        $f['description'],
      ]);

      flash('Parcel booked successfully! Payment Method: Cash on Delivery (' . money(PARCEL_CHARGE) . '). Share details on WhatsApp below.', 'ok');
      redirect('?page=track&code=' . urlencode($code) . '&m=' . urlencode($f['pickup_mobile']) . '&booked=1');
    }
  }

  /* ---------- 2. MARK CASH PAID ---------- */
  if ($action === 'mark_cash_paid') {
    if (!is_staff()) {
      flash('Staff login required.', 'err');
      redirect('?page=driver');
    }
    $id = (int)($_POST['id'] ?? 0);
    $ref = 'CASH-' . strtoupper(bin2hex(random_bytes(4)));
    db()->prepare("UPDATE parcel_requests SET payment_status = 'CONFIRMED', payment_ref = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
      ->execute([$ref, $id]);
    flash('Cash of ' . money(PARCEL_CHARGE) . ' marked as collected successfully.', 'ok');
    redirect('?page=' . ($_POST['back'] ?? 'driver'));
  }

  /* ---------- 3. STAFF LOGIN / LOGOUT ---------- */
  if ($action === 'staff_login') {
    if (hash_equals(STAFF_PIN, (string)($_POST['pin'] ?? ''))) {
      $_SESSION['staff'] = true;
      flash('Welcome back.', 'ok');
      redirect('?page=' . ($_POST['goto'] ?? 'driver'));
    }
    flash('Incorrect PIN.', 'err');
    redirect('?page=' . ($_POST['goto'] ?? 'driver'));
  }
  if ($action === 'staff_logout') {
    unset($_SESSION['staff']);
    flash('Signed out.', 'ok');
    redirect('?page=home');
  }

  /* ---------- 4. STAFF STATUS UPDATES ---------- */
  if (in_array($action, ['advance_status', 'set_delivery', 'set_payment', 'assign_driver'], true)) {
    if (!is_staff()) {
      flash('Staff login required.', 'err');
      redirect('?page=driver');
    }
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'set_delivery' || $action === 'advance_status') {
      $to = $_POST['delivery_status'] ?? '';
      if (in_array($to, DELIVERY_ALL, true)) {
        db()->prepare("UPDATE parcel_requests SET delivery_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
          ->execute([$to, $id]);
        flash('Delivery status updated to ' . label($to) . '.', 'ok');
      }
    }
    if ($action === 'set_payment') {
      $to = $_POST['payment_status'] ?? '';
      if (in_array($to, PAYMENT_STATUSES, true)) {
        db()->prepare("UPDATE parcel_requests SET payment_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
          ->execute([$to, $id]);
        flash('Payment status updated to ' . label($to) . '.', 'ok');
      }
    }
    if ($action === 'assign_driver') {
      $d = trim($_POST['driver_name'] ?? '');
      $n = trim($_POST['driver_note'] ?? '');
      $cur = db()->prepare("SELECT delivery_status FROM parcel_requests WHERE id = ?");
      $cur->execute([$id]);
      $cs = (string)$cur->fetchColumn();
      $newStatus = ($cs === 'REQUESTED' && $d !== '') ? 'PICKUP_ASSIGNED' : $cs;
      db()->prepare("UPDATE parcel_requests SET driver_name = ?, driver_note = ?, delivery_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$d, $n, $newStatus, $id]);
      flash('Driver details saved.', 'ok');
    }
    redirect('?page=' . ($_POST['back'] ?? 'driver'));
  }
}

$fl = flash();

/* ======================= DATA FETCH ======================= */
$trackRow = null;
$trackErr = '';
if ($page === 'track') {
  if (!empty($_GET['code']) && !empty($_GET['m'])) {
    $st = db()->prepare("SELECT * FROM parcel_requests WHERE order_code = ? AND (pickup_mobile = ? OR receiver_mobile = ?)");
    $st->execute([trim($_GET['code']), preg_replace('/\D/', '', $_GET['m']), preg_replace('/\D/', '', $_GET['m'])]);
    $trackRow = $st->fetch() ?: null;
    if (!$trackRow) $trackErr = 'No parcel found with that Order ID and mobile number.';
  }
}

$driverRows = [];
if ($page === 'driver' && is_staff()) {
  $driverRows = db()->query("SELECT * FROM parcel_requests
        WHERE delivery_status NOT IN ('DELIVERED','CANCELLED','FAILED')
        ORDER BY id DESC")->fetchAll();
}

$adminRows = [];
if ($page === 'admin' && is_staff()) {
  $filter = $_GET['f'] ?? 'all';
  $where  = [];
  if ($filter === 'pending')       $where[] = "payment_status = 'PENDING'";
  elseif ($filter === 'active')    $where[] = "delivery_status NOT IN ('DELIVERED','CANCELLED','FAILED')";
  elseif ($filter === 'delivered') $where[] = "delivery_status = 'DELIVERED'";
  elseif ($filter === 'failed')    $where[] = "delivery_status IN ('FAILED','CANCELLED')";

  $q = trim($_GET['q'] ?? '');
  if ($q !== '') {
    $where[] = '(order_code LIKE ? OR pickup_name LIKE ? OR pickup_mobile LIKE ?
                OR receiver_name LIKE ? OR receiver_mobile LIKE ?)';
  }
  $w  = $where ? 'WHERE ' . implode(' AND ', $where) : '';
  $st = db()->prepare("SELECT * FROM parcel_requests $w ORDER BY id DESC LIMIT 200");
  $st->execute($q !== ''
    ? array_fill(0, 5, '%' . $q . '%')
    : []);
  $adminRows = $st->fetchAll();
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h(APP_NAME) ?> — Parcel to Door <?= money(PARCEL_CHARGE) ?> (Cash on Delivery)</title>
  <!-- Google Fonts & Font Awesome -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    :root {
      --navy: #0B132B;
      --navy-light: #1C2541;
      --teal: #0D9488;
      --teal-light: #14B8A6;
      --green: #15803D;
      --green-light: #22C55E;
      --amber: #D97706;
      --red: #DC2626;
      --blue: #2563EB;
      --bg: #F8FAFC;
      --card: #FFFFFF;
      --line: #E2E8F0;
      --muted: #64748B;
      --ink: #0F172A;
      --r: 18px;
      --shadow: 0 10px 30px rgba(15, 23, 42, .06);
      --shadow-hover: 0 18px 40px rgba(15, 23, 42, .12);
    }

    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--ink);
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }
    a { color: inherit; }

    /* ---------- Top Header Bar ---------- */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 100;
      background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
      color: #fff;
      box-shadow: 0 4px 20px rgba(11, 19, 43, .25);
      border-bottom: 1px solid rgba(255, 255, 255, .1);
    }
    .topbar .inner {
      max-width: 1120px;
      margin: 0 auto;
      padding: 12px 18px;
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .brand-wrap {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
    }
    .brand-logo-img {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--teal-light);
      background: white;
      box-shadow: 0 2px 10px rgba(13, 148, 136, .4);
    }
    .brand-text {
      display: flex;
      flex-direction: column;
    }
    .brand-title {
      font-weight: 900;
      font-size: 18px;
      letter-spacing: .5px;
      color: #FFFFFF;
      line-height: 1.1;
    }
    .brand-title span { color: var(--teal-light); }
    .brand-subtitle {
      font-size: 10.5px;
      letter-spacing: 1px;
      color: #94A3B8;
      text-transform: uppercase;
      font-weight: 700;
    }

    .nav {
      margin-left: auto;
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .nav a {
      padding: 8px 14px;
      border-radius: 12px;
      font-size: 13.5px;
      font-weight: 700;
      text-decoration: none;
      color: #CBD5E1;
      transition: all .2s;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .nav a:hover {
      background: rgba(255, 255, 255, .12);
      color: #fff;
      transform: translateY(-1px);
    }
    .nav a.on {
      background: var(--teal);
      color: #fff;
      box-shadow: 0 4px 12px rgba(13, 148, 136, .35);
    }

    /* ---------- Container Layout ---------- */
    .wrap {
      max-width: 1120px;
      margin: 0 auto;
      padding: 24px 18px 120px;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: var(--r);
      padding: 24px;
      box-shadow: var(--shadow);
      margin-bottom: 22px;
      transition: transform .2s, box-shadow .2s;
    }
    .card h2 {
      margin: 0 0 4px;
      font-size: 18px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--navy);
    }
    .card .sub {
      margin: 0 0 18px;
      color: var(--muted);
      font-size: 13.5px;
    }

    .grid2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    @media(max-width:780px) {
      .grid2 { grid-template-columns: 1fr; }
    }

    /* ---------- Hero Banner ---------- */
    .hero {
      text-align: center;
      padding: 28px 18px 10px;
    }
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 14px;
      border-radius: 999px;
      background: #E0F2FE;
      color: #0369A1;
      font-size: 12.5px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: 12px;
    }
    .hero h1 {
      margin: 6px 0 8px;
      font-size: clamp(26px, 5vw, 38px);
      font-weight: 900;
      letter-spacing: -.5px;
      color: var(--navy);
    }
    .hero p {
      margin: 0;
      color: var(--muted);
      font-size: 15.5px;
      font-weight: 500;
    }

    /* ---------- Price / COD Card ---------- */
    .price-card {
      max-width: 480px;
      margin: 24px auto 32px;
      text-align: center;
      background: linear-gradient(155deg, #0B132B 0%, #1C2541 100%);
      color: #fff;
      border-radius: 26px;
      padding: 26px 24px 28px;
      box-shadow: 0 20px 48px rgba(11, 19, 43, .35);
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, .15);
    }
    .price-card::after {
      content: "";
      position: absolute;
      width: 250px;
      height: 250px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(13, 148, 136, .45), transparent 70%);
      top: -100px;
      right: -80px;
      pointer-events: none;
    }
    .price-card .lbl {
      position: relative;
      font-size: 12px;
      letter-spacing: 1.8px;
      text-transform: uppercase;
      color: #94A3B8;
      font-weight: 800;
    }
    .price-card .amt {
      position: relative;
      font-size: 64px;
      font-weight: 900;
      color: #FBBF24;
      line-height: 1;
      margin: 8px 0 4px;
      text-shadow: 0 4px 18px rgba(251, 191, 36, .3);
    }
    .price-card .note {
      position: relative;
      font-size: 14px;
      font-weight: 700;
      color: #E2E8F0;
    }
    .price-card .cod-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(34, 197, 94, .2);
      border: 1px solid rgba(34, 197, 94, .5);
      color: #4ADE80;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 12.5px;
      font-weight: 800;
      margin-top: 14px;
      text-transform: uppercase;
      letter-spacing: .5px;
    }

    /* ---------- Form Styles ---------- */
    .field { margin-bottom: 16px; }
    .field label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 13px;
      font-weight: 800;
      color: #334155;
      margin-bottom: 6px;
    }
    .field .req { color: var(--red); }
    
    input[type=text],
    input[type=tel],
    input[type=number],
    input[type=password],
    select,
    textarea {
      width: 100%;
      padding: 13px 15px;
      border: 1.5px solid var(--line);
      border-radius: 12px;
      font-size: 15px;
      font-family: inherit;
      background: #fff;
      color: var(--ink);
      transition: all .15s;
    }
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: var(--teal);
      box-shadow: 0 0 0 4px rgba(13, 148, 136, .15);
    }
    textarea { resize: vertical; min-height: 80px; }
    .err-txt { color: var(--red); font-size: 12.5px; margin-top: 5px; font-weight: 700; }
    input.bad, select.bad, textarea.bad { border-color: var(--red); background: #FEF2F2; }

    /* ---------- Live Location Fetcher ---------- */
    .location-btn-row {
      display: flex;
      gap: 8px;
      margin-top: 6px;
    }
    .btn-gps {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 9px;
      border: 1px solid #BAE6FD;
      background: #F0F9FF;
      color: #0284C7;
      font-size: 12.5px;
      font-weight: 700;
      cursor: pointer;
      transition: all .15s;
    }
    .btn-gps:hover {
      background: #E0F2FE;
      border-color: #7DD3FC;
    }
    .gps-status {
      display: none;
      font-size: 12px;
      font-weight: 700;
      color: #15803D;
      margin-top: 6px;
      padding: 4px 8px;
      border-radius: 6px;
      background: #DCFCE7;
    }

    /* ---------- Totals & Payment Method Highlight ---------- */
    .totals {
      border-top: 1px dashed var(--line);
      margin-top: 12px;
      padding-top: 16px;
    }
    .trow {
      display: flex;
      justify-content: space-between;
      font-size: 15px;
      padding: 6px 0;
      color: #475569;
    }
    .trow.big {
      font-size: 22px;
      font-weight: 900;
      color: var(--navy);
      border-top: 1px solid var(--line);
      margin-top: 8px;
      padding-top: 14px;
    }

    .cod-box-highlight {
      background: linear-gradient(135deg, #ECFDF5 0%, #F0FDF4 100%);
      border: 2px solid #86EFAC;
      border-radius: 14px;
      padding: 16px 18px;
      margin: 16px 0;
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .cod-box-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      background: #22C55E;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }
    .cod-box-text strong {
      display: block;
      color: #166534;
      font-size: 15px;
    }
    .cod-box-text span {
      font-size: 13px;
      color: #15803D;
      font-weight: 500;
    }

    /* ---------- Buttons ---------- */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 15px 24px;
      border-radius: 14px;
      border: 0;
      cursor: pointer;
      font-size: 16px;
      font-weight: 800;
      font-family: inherit;
      text-decoration: none;
      transition: all .2s;
      width: 100%;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary {
      background: linear-gradient(135deg, #0D9488 0%, #0F766E 100%);
      color: #fff;
      box-shadow: 0 8px 24px rgba(13, 148, 136, .35);
    }
    .btn-primary:hover {
      background: #0F766E;
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(13, 148, 136, .45);
    }
    .btn-navy { background: var(--navy); color: #fff; }
    .btn-navy:hover { background: var(--navy-light); }
    .btn-ghost { background: #fff; color: var(--navy); border: 1.5px solid var(--line); }
    .btn-ghost:hover { border-color: var(--navy); }
    .btn-sm { padding: 9px 14px; font-size: 13px; border-radius: 10px; width: auto; }
    .btn-row { display: flex; gap: 10px; flex-wrap: wrap; }

    /* ---------- WhatsApp Buttons ---------- */
    .btn-wa-owner {
      background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
      color: #fff !important;
      font-weight: 800;
      border-radius: 12px;
      padding: 12px 18px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      font-size: 14px;
      box-shadow: 0 4px 14px rgba(37, 211, 102, .3);
      transition: all .2s;
    }
    .btn-wa-owner:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(37, 211, 102, .45);
    }
    .btn-wa-driver {
      background: linear-gradient(135deg, #0D9488 0%, #083344 100%);
      color: #fff !important;
      font-weight: 800;
      border-radius: 12px;
      padding: 12px 18px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      font-size: 14px;
      box-shadow: 0 4px 14px rgba(13, 148, 136, .3);
      transition: all .2s;
    }
    .btn-wa-driver:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(13, 148, 136, .45);
    }

    /* ---------- Badges ---------- */
    .badge {
      display: inline-block;
      padding: 5px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .4px;
      text-transform: uppercase;
    }
    .b-green { background: #DCFCE7; color: #15803D; }
    .b-amber { background: #FEF3C7; color: #B45309; }
    .b-red   { background: #FEE2E2; color: #B91C1C; }
    .b-blue  { background: #DBEAFE; color: #1D4ED8; }
    .b-teal  { background: #CCFBF1; color: #0F766E; }
    .b-grey  { background: #E2E8F0; color: #475569; }

    /* ---------- Timeline ---------- */
    .timeline { list-style: none; margin: 0; padding: 0; }
    .timeline li {
      position: relative;
      padding: 0 0 24px 36px;
      font-size: 14px;
    }
    .timeline li::before {
      content: "";
      position: absolute;
      left: 8px;
      top: 3px;
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: #fff;
      border: 3px solid var(--line);
      z-index: 2;
    }
    .timeline li::after {
      content: "";
      position: absolute;
      left: 15px;
      top: 19px;
      bottom: 0;
      width: 2px;
      background: var(--line);
    }
    .timeline li:last-child { padding-bottom: 0; }
    .timeline li:last-child::after { display: none; }
    .timeline li.done::before { background: var(--green); border-color: var(--green); }
    .timeline li.done::after { background: var(--green); }
    .timeline li.now::before {
      background: #F59E0B;
      border-color: #F59E0B;
      box-shadow: 0 0 0 6px rgba(245, 158, 11, .25);
    }
    .timeline li .t { font-weight: 800; font-size: 15px; }
    .timeline li .d { color: var(--muted); font-size: 13px; margin-top: 2px; }

    /* ---------- Notice / Toast ---------- */
    .notice {
      padding: 14px 18px;
      border-radius: 14px;
      font-size: 14.5px;
      font-weight: 700;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .n-ok { background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0; }
    .n-err { background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }

    /* ---------- Tables & Stats ---------- */
    .tbl-wrap {
      overflow-x: auto;
      border: 1px solid var(--line);
      border-radius: 14px;
    }
    table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 760px; background: #fff; }
    th {
      background: #F1F5F9;
      text-align: left;
      padding: 12px 14px;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .6px;
      color: #475569;
      font-weight: 800;
      white-space: nowrap;
    }
    td { padding: 12px 14px; border-top: 1px solid var(--line); vertical-align: top; }
    tr:hover td { background: #F8FAFC; }

    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 14px;
      margin-bottom: 22px;
    }
    .stat {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 16px 18px;
      box-shadow: var(--shadow);
    }
    .stat .k { font-size: 12px; text-transform: uppercase; letter-spacing: .7px; color: var(--muted); font-weight: 800; }
    .stat .v { font-size: 26px; font-weight: 900; margin-top: 4px; }

    /* ---------- How It Works Steps ---------- */
    .steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
    }
    .step {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 20px 18px;
      box-shadow: var(--shadow);
    }
    .step .n {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--teal);
      color: #fff;
      font-weight: 900;
      margin-bottom: 12px;
    }
    .step h3 { margin: 0 0 6px; font-size: 15px; font-weight: 800; }
    .step p { margin: 0; font-size: 13.5px; color: var(--muted); line-height: 1.5; }

    /* ---------- Features Grid ---------- */
    .feat {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 14px;
    }
    .feat .f {
      border: 1px solid var(--line);
      background: #fff;
      border-radius: 16px;
      padding: 18px 14px;
      text-align: center;
      box-shadow: var(--shadow);
    }
    .feat .f .ic { font-size: 28px; }
    .feat .f .t { font-weight: 800; font-size: 14px; margin-top: 8px; }
    .feat .f .d { font-size: 12.5px; color: var(--muted); margin-top: 3px; }

    /* ---------- Section Headers ---------- */
    .sec-head {
      display: flex;
      align-items: center;
      gap: 14px;
      margin: 10px 0 16px;
    }
    .sec-head h2 {
      margin: 0;
      font-size: 19px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--navy);
    }
    .sec-head .line { flex: 1; height: 1.5px; background: var(--line); }

    /* ---------- WhatsApp Share Showcase Banner ---------- */
    .wa-share-card {
      background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);
      border: 2px solid #86EFAC;
      border-radius: var(--r);
      padding: 24px;
      margin-bottom: 22px;
      box-shadow: 0 8px 24px rgba(22, 163, 74, .12);
    }
    .wa-share-card h3 {
      margin: 0 0 6px;
      font-size: 18px;
      font-weight: 900;
      color: #166534;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .wa-share-card p {
      margin: 0 0 16px;
      color: #15803D;
      font-size: 14px;
      font-weight: 500;
    }

    .key-value {
      display: flex;
      gap: 12px;
      font-size: 14px;
      padding: 8px 0;
      border-bottom: 1px dashed var(--line);
    }
    .key-value:last-child { border-bottom: 0; }
    .key-value .k { color: var(--muted); min-width: 130px; font-weight: 700; }
    .key-value .v { font-weight: 700; word-break: break-word; color: #0F172A; }

    /* ---------- Footer ---------- */
    .foot {
      text-align: center;
      color: var(--muted);
      font-size: 13px;
      padding: 16px 18px 100px;
    }
    .foot b { color: var(--navy); }

    /* ---------- Sticky CTA ---------- */
    .sticky-cta {
      position: fixed;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 70;
      display: none;
      padding: 12px 18px calc(12px + env(safe-area-inset-bottom));
      background: rgba(255, 255, 255, .95);
      backdrop-filter: blur(12px);
      border-top: 1px solid var(--line);
      box-shadow: 0 -8px 24px rgba(15, 23, 42, .12);
    }
    @media(max-width:780px) { .sticky-cta { display: block; } }

    /* ---------- Print Slip ---------- */
    .slip { display: none; }
    @media print {
      .topbar, .nav, .sticky-cta, .btn, .foot, .no-print, #toTop, #toast { display: none !important; }
      body { background: #fff; }
      .card { box-shadow: none; border: 1px solid #ccc; }
      .slip { display: block !important; }
    }
  </style>
</head>

<body>

  <!-- Top Bar -->
  <header class="topbar">
    <div class="inner">
      <a href="?page=home" class="brand-wrap">
        <img src="<?= h($logoUrl) ?>" alt="KSR GURU JI Logo" class="brand-logo-img" onerror="this.src='company_logo.jpg';">
        <div class="brand-text">
          <div class="brand-title">KSR <span>GURU JI</span></div>
          <div class="brand-subtitle">Parcel to Door • ₹50 COD</div>
        </div>
      </a>
      <nav class="nav">
        <a href="?page=home" class="<?= $page === 'home' ? 'on' : '' ?>"><i class="fas fa-box"></i> Book Parcel</a>
        <a href="?page=track" class="<?= $page === 'track' ? 'on' : '' ?>"><i class="fas fa-search-location"></i> Track</a>
        <a href="?page=driver" class="<?= $page === 'driver' ? 'on' : '' ?>"><i class="fas fa-truck"></i> Driver</a>
        <a href="?page=admin" class="<?= $page === 'admin' ? 'on' : '' ?>"><i class="fas fa-user-shield"></i> Admin</a>
        <?php if (is_staff()): ?>
          <a href="#" onclick="document.getElementById('logoutForm').submit();return false;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <form id="logoutForm" method="post" style="display:none">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="staff_logout">
  </form>

  <main class="wrap">

    <?php if ($fl): ?>
      <div class="notice n-<?= $fl['t'] === 'ok' ? 'ok' : 'err' ?>">
        <i class="fas fa-<?= $fl['t'] === 'ok' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <span><?= h($fl['m']) ?></span>
      </div>
    <?php endif; ?>

    <?php /* ============================================================
       PAGE: HOME (Book Parcel — Cash on Delivery ONLY)
       ============================================================ */ ?>
    <?php if ($page === 'home'): ?>

      <section class="hero">
        <div class="hero-badge"><i class="fas fa-shield-alt"></i> Fast • Same-Day Local Delivery</div>
        <h1>Express Parcel to Door</h1>
        <p>Doorstep pickup and doorstep delivery anywhere in town — Fixed <?= money(PARCEL_CHARGE) ?> Cash on Delivery</p>
      </section>

      <!-- Price Card & COD Notice -->
      <div class="price-card">
        <div class="lbl">Fixed Service Charge</div>
        <div class="amt"><?= money(PARCEL_CHARGE) ?></div>
        <div class="note">Pay only when parcel is collected or delivered</div>
        <div>
          <span class="cod-pill"><i class="fas fa-money-bill-wave"></i> Payment Method: Only Cash on Delivery</span>
        </div>
      </div>

      <div class="sec-head">
        <h2><i class="fas fa-route" style="color:var(--teal)"></i> How Parcel to Door Works</h2>
        <div class="line"></div>
      </div>
      <div class="steps" style="margin-bottom:24px">
        <div class="step">
          <div class="n">1</div>
          <h3>Fill Details & GPS</h3>
          <p>Enter sender, receiver, mobile numbers, and optionally capture your live GPS location with 1 tap.</p>
        </div>
        <div class="step">
          <div class="n">2</div>
          <h3>Only Cash on Delivery</h3>
          <p>Zero advance payment! Pay <?= money(PARCEL_CHARGE) ?> cash upon doorstep pickup or handover.</p>
        </div>
        <div class="step">
          <div class="n">3</div>
          <h3>Doorstep Pickup</h3>
          <p>Our driver partner reaches your live location to collect the parcel quickly.</p>
        </div>
        <div class="step">
          <div class="n">4</div>
          <h3>WhatsApp Updates</h3>
          <p>Share all order details instantly with Owner (8088584421) and Delivery Driver (8762474406).</p>
        </div>
      </div>

      <!-- Booking Form -->
      <form method="post" id="parcelForm" novalidate>
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_request">

        <div class="grid2">

          <!-- PICKUP DETAILS -->
          <div class="card">
            <h2><i class="fas fa-map-marker-alt" style="color:#0284C7"></i> 1. Pickup Details</h2>
            <p class="sub">Where should our delivery team collect the parcel from?</p>

            <div class="field">
              <label>Pickup / Sender Name <span class="req">*</span></label>
              <input type="text" name="pickup_name" value="<?= h($form['pickup_name'] ?? '') ?>"
                class="<?= isset($errors['pickup_name']) ? 'bad' : '' ?>" placeholder="Full name of person sending">
              <?php if (isset($errors['pickup_name'])): ?><div class="err-txt"><?= h($errors['pickup_name']) ?></div><?php endif; ?>
            </div>

            <div class="field">
              <label>Sender Mobile Number <span class="req">*</span></label>
              <input type="tel" name="pickup_mobile" inputmode="numeric" maxlength="10"
                value="<?= h($form['pickup_mobile'] ?? '') ?>"
                class="<?= isset($errors['pickup_mobile']) ? 'bad' : '' ?>" placeholder="10-digit mobile number">
              <?php if (isset($errors['pickup_mobile'])): ?><div class="err-txt"><?= h($errors['pickup_mobile']) ?></div><?php endif; ?>
            </div>

            <div class="field">
              <label>
                <span>Pickup Address <span class="req">*</span></span>
              </label>
              <textarea name="pickup_address" id="pickup_address" class="<?= isset($errors['pickup_address']) ? 'bad' : '' ?>"
                placeholder="House / Shop no., street, colony, area"><?= h($form['pickup_address'] ?? '') ?></textarea>
              <?php if (isset($errors['pickup_address'])): ?><div class="err-txt"><?= h($errors['pickup_address']) ?></div><?php endif; ?>
              
              <!-- Live Location Fetcher -->
              <input type="hidden" name="pickup_coords" id="pickup_coords" value="<?= h($form['pickup_coords'] ?? '') ?>">
              <div class="location-btn-row">
                <button type="button" class="btn-gps" onclick="fetchLiveGPS('pickup')">
                  <i class="fas fa-crosshairs"></i> Share My Current Live GPS Location
                </button>
              </div>
              <div class="gps-status" id="pickup_gps_status"></div>
            </div>

            <div class="field" style="margin-bottom:0">
              <label>Nearby Landmark <span style="color:var(--muted);font-weight:600">(Optional)</span></label>
              <input type="text" name="pickup_landmark" value="<?= h($form['pickup_landmark'] ?? '') ?>" placeholder="e.g. Near Bus Stand, opposite Temple">
            </div>
          </div>

          <!-- DELIVERY DETAILS -->
          <div class="card">
            <h2><i class="fas fa-home" style="color:#16A34A"></i> 2. Delivery Details</h2>
            <p class="sub">Where should we deliver the parcel to?</p>

            <div class="field">
              <label>Receiver Name <span class="req">*</span></label>
              <input type="text" name="receiver_name" value="<?= h($form['receiver_name'] ?? '') ?>"
                class="<?= isset($errors['receiver_name']) ? 'bad' : '' ?>" placeholder="Full name of person receiving">
              <?php if (isset($errors['receiver_name'])): ?><div class="err-txt"><?= h($errors['receiver_name']) ?></div><?php endif; ?>
            </div>

            <div class="field">
              <label>Receiver Mobile Number <span class="req">*</span></label>
              <input type="tel" name="receiver_mobile" inputmode="numeric" maxlength="10"
                value="<?= h($form['receiver_mobile'] ?? '') ?>"
                class="<?= isset($errors['receiver_mobile']) ? 'bad' : '' ?>" placeholder="10-digit mobile number">
              <?php if (isset($errors['receiver_mobile'])): ?><div class="err-txt"><?= h($errors['receiver_mobile']) ?></div><?php endif; ?>
            </div>

            <div class="field">
              <label>
                <span>Delivery Address <span class="req">*</span></span>
              </label>
              <textarea name="delivery_address" id="delivery_address" class="<?= isset($errors['delivery_address']) ? 'bad' : '' ?>"
                placeholder="House no., street name, ward, landmark"><?= h($form['delivery_address'] ?? '') ?></textarea>
              <?php if (isset($errors['delivery_address'])): ?><div class="err-txt"><?= h($errors['delivery_address']) ?></div><?php endif; ?>

              <!-- Drop Location GPS -->
              <input type="hidden" name="delivery_coords" id="delivery_coords" value="<?= h($form['delivery_coords'] ?? '') ?>">
              <div class="location-btn-row">
                <button type="button" class="btn-gps" onclick="fetchLiveGPS('delivery')">
                  <i class="fas fa-crosshairs"></i> Set Delivery Live GPS (if currently at drop location)
                </button>
              </div>
              <div class="gps-status" id="delivery_gps_status"></div>
            </div>

            <div class="field" style="margin-bottom:0">
              <label>Nearby Landmark <span style="color:var(--muted);font-weight:600">(Optional)</span></label>
              <input type="text" name="delivery_landmark" value="<?= h($form['delivery_landmark'] ?? '') ?>" placeholder="e.g. Near Water Tank, Hospital Road">
            </div>
          </div>
        </div>

        <!-- PARCEL INFO & PAYMENT -->
        <div class="card">
          <h2><i class="fas fa-boxes" style="color:#D97706"></i> 3. Parcel Information & Payment</h2>
          <p class="sub">Specify the contents. Only Cash on Delivery accepted.</p>
          
          <div class="grid2">
            <div>
              <div class="field">
                <label>Parcel Category</label>
                <select name="parcel_type">
                  <?php foreach (PARCEL_TYPES as $pt): ?>
                    <option value="<?= h($pt) ?>" <?= ($form['parcel_type'] ?? 'Document') === $pt ? 'selected' : '' ?>>
                      <?= h($pt) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field" style="margin-bottom:0">
                <label>Approx. Weight (kg) <span style="color:var(--muted);font-weight:600">(Optional)</span></label>
                <input type="text" name="weight" inputmode="decimal" value="<?= h($form['weight'] ?? '') ?>"
                  class="<?= isset($errors['weight']) ? 'bad' : '' ?>" placeholder="e.g. 1.0">
                <?php if (isset($errors['weight'])): ?><div class="err-txt"><?= h($errors['weight']) ?></div><?php endif; ?>
              </div>
            </div>
            
            <div class="field" style="margin-bottom:0">
              <label>Description of Contents <span style="color:var(--muted);font-weight:600">(Optional)</span></label>
              <textarea name="description" placeholder="Short description of what is inside the parcel (e.g. key, books, urgent documents)"><?= h($form['description'] ?? '') ?></textarea>
            </div>
          </div>

          <!-- Highlight: ONLY Cash and Delivery -->
          <div class="cod-box-highlight">
            <div class="cod-box-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="cod-box-text">
              <strong>Payment Method: Cash on Delivery Only (<?= money(PARCEL_CHARGE) ?>)</strong>
              <span>No online payment needed! Pay <?= money(PARCEL_CHARGE) ?> in cash when our driver collects or delivers the parcel.</span>
            </div>
          </div>

          <div class="totals">
            <div class="trow"><span>Fixed Door Delivery Charge</span><span><?= money(PARCEL_CHARGE) ?></span></div>
            <div class="trow"><span>Payment Mode</span><span style="font-weight:800;color:#16A34A;">Cash on Delivery (COD)</span></div>
            <div class="trow big"><span>Total Payable on Delivery</span><span><?= money(PARCEL_CHARGE) ?></span></div>
          </div>

          <div style="margin-top:20px">
            <button type="submit" class="btn btn-primary">
              🚚 Confirm Parcel Request — Pay <?= money(PARCEL_CHARGE) ?> Cash on Delivery
            </button>
            <div style="text-align:center; font-size:13px; color:var(--muted); margin-top:8px;">
              Your order will be booked immediately and you can share full details to WhatsApp with 1 tap.
            </div>
          </div>
        </div>
      </form>

      <!-- Why Choose Us -->
      <div class="sec-head">
        <h2><i class="fas fa-star" style="color:#F59E0B"></i> Why Choose KSR GURU JI</h2>
        <div class="line"></div>
      </div>
      <div class="feat" style="margin-bottom:24px">
        <div class="f">
          <div class="ic">💰</div>
          <div class="t">Flat <?= money(PARCEL_CHARGE) ?></div>
          <div class="d">Zero hidden costs</div>
        </div>
        <div class="f">
          <div class="ic">💵</div>
          <div class="t">Cash on Delivery</div>
          <div class="d">Pay on door delivery</div>
        </div>
        <div class="f">
          <div class="ic">📍</div>
          <div class="t">Live GPS Pins</div>
          <div class="d">Accurate navigation</div>
        </div>
        <div class="f">
          <div class="ic">💬</div>
          <div class="t">WhatsApp Sharing</div>
          <div class="d">Direct to Owner & Driver</div>
        </div>
        <div class="f">
          <div class="ic">⚡</div>
          <div class="t">Same-Day Express</div>
          <div class="d">Quick local handover</div>
        </div>
      </div>

    <?php /* ============================================================
       PAGE: TRACK (Track Parcel + WhatsApp Share All Details)
       ============================================================ */ ?>
    <?php elseif ($page === 'track'): ?>

      <div class="hero">
        <div class="hero-badge"><i class="fas fa-satellite-dish"></i> Live Tracking & Verification</div>
        <h1>Track Your Parcel</h1>
        <p>Enter your Order ID and registered mobile number to follow status</p>
      </div>

      <div class="card" style="max-width:680px; margin:0 auto 24px;">
        <form method="get" id="trackForm">
          <input type="hidden" name="page" value="track">
          <div class="grid2">
            <div class="field">
              <label>Order ID</label>
              <input type="text" name="code" class="mono" placeholder="KSR-P-250101-ABCD"
                value="<?= h($_GET['code'] ?? '') ?>" required>
            </div>
            <div class="field">
              <label>Mobile Number</label>
              <input type="tel" name="m" inputmode="numeric" maxlength="10" placeholder="10-digit mobile"
                value="<?= h($_GET['m'] ?? '') ?>" required>
            </div>
          </div>
          <button class="btn btn-navy">🔎 Track Parcel Status</button>
        </form>
      </div>

      <?php if ($trackErr): ?>
        <div class="notice n-err"><i class="fas fa-exclamation-triangle"></i> <?= h($trackErr) ?></div>
      <?php endif; ?>

      <?php if ($trackRow): ?>
        <?php
        $flowIdx = array_search($trackRow['delivery_status'], DELIVERY_FLOW, true);
        $isCancelled = in_array($trackRow['delivery_status'], ['CANCELLED', 'FAILED'], true);
        $waMsg = buildWhatsAppMessage($trackRow);
        ?>

        <!-- WHATSAPP SHARE SHOWCASE CARD -->
        <div class="wa-share-card no-print">
          <h3><i class="fab fa-whatsapp" style="font-size:24px;"></i> Share All Details via WhatsApp</h3>
          <p>Instantly notify the Owner and Delivery Partner with complete pickup, drop, GPS location, and COD details:</p>
          
          <div class="btn-row">
            <!-- Share with Owner (8088584421) -->
            <a class="btn-wa-owner" target="_blank" rel="noopener"
              href="<?= h(waLink(OWNER_WHATSAPP, $waMsg)) ?>">
              <i class="fab fa-whatsapp"></i> Share with Owner (<?= h(OWNER_PHONE) ?>)
            </a>
            
            <!-- Share with Driver / Delivery Team (8762474406) -->
            <a class="btn-wa-driver" target="_blank" rel="noopener"
              href="<?= h(waLink(DRIVER_WHATSAPP, $waMsg)) ?>">
              <i class="fas fa-motorcycle"></i> Share with Delivery Driver (<?= h(DRIVER_PHONE) ?>)
            </a>

            <!-- Share to Any WhatsApp Contact -->
            <a class="btn btn-ghost btn-sm" style="background:white;width:auto;" target="_blank" rel="noopener"
              href="<?= h(waAnyLink($waMsg)) ?>">
              <i class="fas fa-share-alt"></i> Share to Other
            </a>

            <!-- Copy Full Text -->
            <button type="button" class="btn btn-ghost btn-sm" style="background:white;width:auto;"
              onclick="copyOrderDetails(`<?= addslashes($waMsg) ?>`)">
              <i class="fas fa-copy"></i> Copy Details
            </button>
          </div>
        </div>

        <!-- MAIN TRACKING CARD -->
        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
            <div>
              <div style="font-size:12px; font-weight:800; color:var(--muted); text-transform:uppercase;">Parcel Tracking</div>
              <h2 style="font-size:22px; margin:2px 0 4px;"><span class="mono"><?= h($trackRow['order_code']) ?></span></h2>
              <div style="color:var(--muted); font-size:13px;">
                Placed: <?= h($trackRow['created_at']) ?> &bull; Last updated: <?= h($trackRow['updated_at']) ?>
              </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
              <span class="badge b-<?= status_slug($trackRow['payment_status']) ?>">
                <i class="fas fa-money-bill-wave"></i> Payment: <?= h(label($trackRow['payment_status'])) ?> (COD)
              </span>
              <span class="badge b-<?= status_slug($trackRow['delivery_status']) ?>">
                <i class="fas fa-truck"></i> <?= h(label($trackRow['delivery_status'])) ?>
              </span>
            </div>
          </div>

          <!-- Progress Bar -->
          <?php if (!$isCancelled): ?>
            <?php $pct = (int)round(((int)$flowIdx + 1) / count(DELIVERY_FLOW) * 100); ?>
            <div style="background:#E2E8F0; height:10px; border-radius:999px; overflow:hidden; margin:18px 0 8px;">
              <div style="background:linear-gradient(90deg, var(--teal), #22C55E); height:100%; width:<?= $pct ?>%; border-radius:999px; transition:width .4s;"></div>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:12.5px; color:var(--muted); font-weight:700;">
              <span>Step <?= (int)$flowIdx + 1 ?> of <?= count(DELIVERY_FLOW) ?>: <?= h(label($trackRow['delivery_status'])) ?></span>
              <span><?= $pct ?>% Complete</span>
            </div>
          <?php endif; ?>

          <div style="margin-top:20px;">
            <div class="key-value">
              <div class="k">Pickup Address</div>
              <div class="v">
                <b><?= h($trackRow['pickup_name']) ?></b> (<?= h($trackRow['pickup_mobile']) ?>)<br>
                <?= h($trackRow['pickup_address']) ?><?= $trackRow['pickup_landmark'] ? ' (Near: ' . h($trackRow['pickup_landmark']) . ')' : '' ?>
                <div style="margin-top:6px;">
                  <a href="<?= h(mapsSearchUrl($trackRow['pickup_address'], $trackRow['pickup_coords'] ?? '')) ?>" target="_blank" rel="noopener" class="btn-gps" style="font-size:12px; padding:4px 10px;">
                    <i class="fas fa-map-marked-alt"></i> Open Pickup in Google Maps
                  </a>
                </div>
              </div>
            </div>

            <div class="key-value">
              <div class="k">Delivery Address</div>
              <div class="v">
                <b><?= h($trackRow['receiver_name']) ?></b> (<?= h($trackRow['receiver_mobile']) ?>)<br>
                <?= h($trackRow['delivery_address']) ?><?= $trackRow['delivery_landmark'] ? ' (Near: ' . h($trackRow['delivery_landmark']) . ')' : '' ?>
                <div style="margin-top:6px;">
                  <a href="<?= h(mapsSearchUrl($trackRow['delivery_address'], $trackRow['delivery_coords'] ?? '')) ?>" target="_blank" rel="noopener" class="btn-gps" style="font-size:12px; padding:4px 10px;">
                    <i class="fas fa-map-marked-alt"></i> Open Delivery in Google Maps
                  </a>
                </div>
              </div>
            </div>

            <div class="key-value">
              <div class="k">Parcel Category</div>
              <div class="v"><?= h($trackRow['parcel_type']) ?><?= $trackRow['weight'] !== '' ? ' &bull; ' . h($trackRow['weight']) . ' kg' : '' ?></div>
            </div>

            <?php if (!empty($trackRow['description'])): ?>
              <div class="key-value">
                <div class="k">Contents</div>
                <div class="v"><?= h($trackRow['description']) ?></div>
              </div>
            <?php endif; ?>

            <div class="key-value">
              <div class="k">Payment Method</div>
              <div class="v">
                <b style="color:#15803D;">Cash on Delivery (COD) &bull; <?= money((float)$trackRow['amount']) ?></b>
                <span style="font-size:12.5px; color:var(--muted); font-weight:normal; display:block;">
                  <?= $trackRow['payment_status'] === 'CONFIRMED' ? '✅ Paid in cash upon delivery' : '⏳ To be collected in cash upon pickup/delivery' ?>
                </span>
              </div>
            </div>

            <?php if (!empty($trackRow['driver_name'])): ?>
              <div class="key-value">
                <div class="k">Assigned Driver</div>
                <div class="v"><b><?= h($trackRow['driver_name']) ?></b> <?= !empty($trackRow['driver_note']) ? '(' . h($trackRow['driver_note']) . ')' : '' ?></div>
              </div>
            <?php endif; ?>
          </div>

          <div class="btn-row no-print" style="margin-top:20px;">
            <a class="btn btn-ghost btn-sm" href="tel:<?= h($trackRow['pickup_mobile']) ?>"><i class="fas fa-phone"></i> Call Sender</a>
            <a class="btn btn-ghost btn-sm" href="tel:<?= h($trackRow['receiver_mobile']) ?>"><i class="fas fa-phone"></i> Call Receiver</a>
            <a class="btn btn-ghost btn-sm" href="tel:<?= h(OWNER_PHONE) ?>"><i class="fas fa-phone-alt"></i> Call Owner (<?= h(OWNER_PHONE) ?>)</a>
            <button class="btn btn-ghost btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print Receipt</button>
          </div>
        </div>

        <!-- TIMELINE -->
        <div class="card">
          <h2><i class="fas fa-history" style="color:var(--teal)"></i> Delivery Milestone Timeline</h2>
          <p class="sub">Step-by-step progress of your door parcel delivery</p>
          <ul class="timeline">
            <?php if ($isCancelled): ?>
              <li class="done">
                <div class="t"><?= h(label($trackRow['delivery_status'])) ?></div>
                <div class="d">This request was <?= h(strtolower(label($trackRow['delivery_status']))) ?>.</div>
              </li>
            <?php else: ?>
              <?php foreach (DELIVERY_FLOW as $i => $step): ?>
                <?php
                $cls = '';
                if ($flowIdx !== false) {
                  if ($i < $flowIdx) $cls = 'done';
                  elseif ($i === $flowIdx) $cls = 'now';
                }
                ?>
                <li class="<?= $cls ?>">
                  <div class="t"><?= $cls === 'now' ? '📍 ' : '' ?><?= h(label($step)) ?></div>
                  <div class="d">
                    <?php if ($cls === 'done'): ?>Completed
                    <?php elseif ($cls === 'now'): ?>Current stage &mdash; in progress
                    <?php else: ?>Awaiting<?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

        <!-- PRINTABLE SLIP -->
        <div class="slip">
          <div style="text-align:center; margin-bottom:14px; border-bottom:2px solid #000; padding-bottom:10px;">
            <h2 style="margin:0; font-size:22px;"><?= h(APP_NAME) ?> &bull; PARCEL DELIVERY SLIP</h2>
            <p style="margin:4px 0 0; font-size:12px;"><?= h(SHOP_ADDRESS) ?> &bull; Phone: <?= h(OWNER_PHONE) ?> / <?= h(DRIVER_PHONE) ?></p>
          </div>
          <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-weight:bold;">
            <div>Order: <?= h($trackRow['order_code']) ?></div>
            <div>Mode: CASH ON DELIVERY &bull; <?= money((float)$trackRow['amount']) ?></div>
          </div>
          <div style="border:1px solid #999; padding:12px; border-radius:8px; margin-bottom:12px;">
            <p style="margin:0 0 6px;"><b>Pickup:</b> <?= h($trackRow['pickup_name']) ?> (<?= h($trackRow['pickup_mobile']) ?>) - <?= h($trackRow['pickup_address']) ?></p>
            <p style="margin:0;"><b>Deliver To:</b> <?= h($trackRow['receiver_name']) ?> (<?= h($trackRow['receiver_mobile']) ?>) - <?= h($trackRow['delivery_address']) ?></p>
          </div>
          <p style="font-size:12px; margin-top:20px;">Receiver Signature: _______________________ &nbsp; Date: ____________</p>
        </div>

      <?php endif; ?>

    <?php /* ============================================================
       PAGE: DRIVER (Delivery Partner Portal)
       ============================================================ */ ?>
    <?php elseif ($page === 'driver'): ?>

      <?php if (!is_staff()): ?>
        <div class="hero">
          <div class="hero-badge"><i class="fas fa-id-badge"></i> Staff Access</div>
          <h1>Delivery Partner Sign In</h1>
          <p>Enter the staff PIN to access active parcel pickup & delivery jobs</p>
        </div>
        <div class="card" style="max-width:400px; margin:0 auto">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="staff_login">
            <input type="hidden" name="goto" value="driver">
            <div class="field">
              <label>Staff PIN</label>
              <input type="password" name="pin" inputmode="numeric" placeholder="••••" required autofocus>
            </div>
            <button class="btn btn-navy">Sign In</button>
          </form>
        </div>
      <?php else: ?>
        <div class="hero">
          <div class="hero-badge"><i class="fas fa-motorcycle"></i> Partner Workspace</div>
          <h1>Driver & Delivery Dashboard</h1>
          <p>Active pickup, live GPS routes, and cash collection on delivery</p>
        </div>

        <div class="stats">
          <div class="stat">
            <div class="k">Active Jobs</div>
            <div class="v"><?= count($driverRows) ?></div>
          </div>
          <div class="stat">
            <div class="k">To Pick Up</div>
            <div class="v" style="color:#D97706"><?= count(array_filter($driverRows, fn($r) => in_array($r['delivery_status'], ['REQUESTED', 'PICKUP_ASSIGNED'], true))) ?></div>
          </div>
          <div class="stat">
            <div class="k">On The Way</div>
            <div class="v" style="color:#2563EB"><?= count(array_filter($driverRows, fn($r) => in_array($r['delivery_status'], ['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'], true))) ?></div>
          </div>
          <div class="stat">
            <div class="k">COD Cash to Collect</div>
            <div class="v" style="color:#16A34A"><?= money((float)array_sum(array_map(fn($r) => $r['payment_status'] === 'PENDING' ? (float)$r['amount'] : 0, $driverRows))) ?></div>
          </div>
        </div>

        <div class="sec-head">
          <h2><i class="fas fa-tasks"></i> Active Delivery Orders</h2>
          <div class="line"></div>
        </div>

        <?php if (!$driverRows): ?>
          <div class="card" style="text-align:center; padding:40px 20px;">
            <div style="font-size:48px; margin-bottom:10px;">🎉</div>
            <h2 style="justify-content:center">All Deliveries Caught Up!</h2>
            <p class="sub">There are currently no active deliveries pending. New orders will appear here automatically.</p>
            <a class="btn btn-ghost" style="width:auto; margin:0 auto;" href="?page=driver">🔄 Refresh Dashboard</a>
          </div>
        <?php endif; ?>

        <?php foreach ($driverRows as $r): ?>
          <?php
          $idx = array_search($r['delivery_status'], DELIVERY_FLOW, true);
          $next = ($idx !== false && $idx < count(DELIVERY_FLOW) - 1) ? DELIVERY_FLOW[$idx + 1] : null;
          $waMsg = buildWhatsAppMessage($r);
          ?>
          <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
              <div>
                <h2 style="margin-bottom:4px;">📦 <span class="mono"><?= h($r['order_code']) ?></span></h2>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:4px;">
                  <span class="badge b-<?= status_slug($r['delivery_status']) ?>"><?= h(label($r['delivery_status'])) ?></span>
                  <span class="badge b-<?= status_slug($r['payment_status']) ?>">Cash: <?= h(label($r['payment_status'])) ?></span>
                </div>
              </div>
              <div style="text-align:right;">
                <div style="font-size:20px; font-weight:900; color:#16A34A;"><?= money((float)$r['amount']) ?> COD</div>
                <div style="font-size:12px; color:var(--muted);"><?= h($r['created_at']) ?></div>
              </div>
            </div>

            <!-- Addresses & Navigation -->
            <div class="grid2" style="margin-top:16px;">
              <div style="background:#F8FAFC; padding:14px; border-radius:12px; border:1px solid #E2E8F0;">
                <div style="font-size:11px; font-weight:800; color:#0284C7; text-transform:uppercase; margin-bottom:4px;">📍 Pickup Location</div>
                <div style="font-weight:800; font-size:15px;"><?= h($r['pickup_name']) ?> &bull; <a href="tel:<?= h($r['pickup_mobile']) ?>"><?= h($r['pickup_mobile']) ?></a></div>
                <div style="font-size:13.5px; color:#475569; margin-top:2px;">
                  <?= h($r['pickup_address']) ?><?= $r['pickup_landmark'] ? ' (Near: ' . h($r['pickup_landmark']) . ')' : '' ?>
                </div>
                <div style="margin-top:8px;">
                  <a href="<?= h(mapsDirectionsUrl($r['pickup_address'], $r['pickup_coords'] ?? '')) ?>" target="_blank" rel="noopener" class="btn-gps" style="font-size:12px; padding:5px 12px;">
                    <i class="fas fa-location-arrow"></i> Navigate to Pickup in Maps
                  </a>
                </div>
              </div>

              <div style="background:#F8FAFC; padding:14px; border-radius:12px; border:1px solid #E2E8F0;">
                <div style="font-size:11px; font-weight:800; color:#16A34A; text-transform:uppercase; margin-bottom:4px;">🏠 Delivery Destination</div>
                <div style="font-weight:800; font-size:15px;"><?= h($r['receiver_name']) ?> &bull; <a href="tel:<?= h($r['receiver_mobile']) ?>"><?= h($r['receiver_mobile']) ?></a></div>
                <div style="font-size:13.5px; color:#475569; margin-top:2px;">
                  <?= h($r['delivery_address']) ?><?= $r['delivery_landmark'] ? ' (Near: ' . h($r['delivery_landmark']) . ')' : '' ?>
                </div>
                <div style="margin-top:8px;">
                  <a href="<?= h(mapsDirectionsUrl($r['delivery_address'], $r['delivery_coords'] ?? '')) ?>" target="_blank" rel="noopener" class="btn-gps" style="font-size:12px; padding:5px 12px;">
                    <i class="fas fa-location-arrow"></i> Navigate to Drop in Maps
                  </a>
                </div>
              </div>
            </div>

            <!-- Action buttons -->
            <div class="btn-row" style="margin-top:16px;">
              <?php if ($next): ?>
                <form method="post" style="flex:1; min-width:180px;">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="advance_status">
                  <input type="hidden" name="back" value="driver">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="delivery_status" value="<?= h($next) ?>">
                  <button class="btn btn-primary btn-sm" style="width:100%">
                    <i class="fas fa-arrow-right"></i> Advance to <?= h(label($next)) ?>
                  </button>
                </form>
              <?php endif; ?>

              <?php if ($r['payment_status'] === 'PENDING'): ?>
                <form method="post" style="flex:1; min-width:180px;">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="mark_cash_paid">
                  <input type="hidden" name="back" value="driver">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm" style="width:100%; background:#15803D; color:white;">
                    <i class="fas fa-check-double"></i> Mark <?= money((float)$r['amount']) ?> Cash Received
                  </button>
                </form>
              <?php endif; ?>

              <a class="btn-wa-owner" style="padding:8px 12px; font-size:12px;" target="_blank" rel="noopener"
                href="<?= h(waLink(OWNER_WHATSAPP, "Update on Job {$r['order_code']}: Status is " . label($r['delivery_status']) . ". Track: " . fullTrackUrl($r))) ?>">
                <i class="fab fa-whatsapp"></i> Update Owner (<?= h(OWNER_PHONE) ?>)
              </a>

              <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
                href="<?= h(waLink($r['pickup_mobile'], "Hello {$r['pickup_name']}, KSR GURU JI delivery partner is assigned to your parcel {$r['order_code']}. Status: " . label($r['delivery_status']))) ?>">
                <i class="fab fa-whatsapp"></i> WhatsApp Sender
              </a>

              <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
                href="<?= h(waLink($r['receiver_mobile'], "Hello {$r['receiver_name']}, your parcel {$r['order_code']} from {$r['pickup_name']} is currently " . label($r['delivery_status']))) ?>">
                <i class="fab fa-whatsapp"></i> WhatsApp Receiver
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php /* ============================================================
       PAGE: ADMIN (Full Control Center)
       ============================================================ */ ?>
    <?php elseif ($page === 'admin'): ?>

      <?php if (!is_staff()): ?>
        <div class="hero">
          <div class="hero-badge"><i class="fas fa-user-lock"></i> Staff Login Required</div>
          <h1>Admin Control Panel</h1>
          <p>Enter staff PIN to manage all parcel requests and operations</p>
        </div>
        <div class="card" style="max-width:400px; margin:0 auto">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="staff_login">
            <input type="hidden" name="goto" value="admin">
            <div class="field">
              <label>Staff PIN</label>
              <input type="password" name="pin" inputmode="numeric" placeholder="••••" required autofocus>
            </div>
            <button class="btn btn-navy">Sign In</button>
          </form>
        </div>
      <?php else: ?>
        <?php
        $allTotal   = (int)db()->query("SELECT COUNT(*) FROM parcel_requests")->fetchColumn();
        $codPending = (int)db()->query("SELECT COUNT(*) FROM parcel_requests WHERE payment_status='PENDING'")->fetchColumn();
        $cashPaid   = (int)db()->query("SELECT COUNT(*) FROM parcel_requests WHERE payment_status='CONFIRMED'")->fetchColumn();
        $activeJobs = (int)db()->query("SELECT COUNT(*) FROM parcel_requests WHERE delivery_status NOT IN ('DELIVERED','CANCELLED','FAILED')")->fetchColumn();
        $delivered  = (int)db()->query("SELECT COUNT(*) FROM parcel_requests WHERE delivery_status='DELIVERED'")->fetchColumn();
        $totalRev   = (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM parcel_requests WHERE payment_status='CONFIRMED'")->fetchColumn();
        ?>
        <div class="hero">
          <div class="hero-badge"><i class="fas fa-shield-alt"></i> Management Console</div>
          <h1>Admin Operations Panel</h1>
          <p>Complete control for parcel assignments, live routing, and COD collection</p>
        </div>

        <div class="stats">
          <div class="stat">
            <div class="k">Total Requests</div>
            <div class="v"><?= $allTotal ?></div>
          </div>
          <div class="stat">
            <div class="k">Active In Progress</div>
            <div class="v" style="color:#D97706;"><?= $activeJobs ?></div>
          </div>
          <div class="stat">
            <div class="k">COD Cash Collected</div>
            <div class="v" style="color:#16A34A;"><?= money($totalRev) ?></div>
          </div>
          <div class="stat">
            <div class="k">Delivered</div>
            <div class="v" style="color:var(--teal);"><?= $delivered ?></div>
          </div>
        </div>

        <div class="card">
          <!-- Search & Filter Toolbar -->
          <form method="get" class="btn-row no-print" style="margin-bottom:16px;">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="f" value="<?= h($_GET['f'] ?? 'all') ?>">
            <div style="flex:1; min-width:240px;">
              <input type="text" name="q" placeholder="Search Order ID, sender, receiver, or mobile…"
                value="<?= h($_GET['q'] ?? '') ?>" style="padding:10px 14px; font-size:14px;">
            </div>
            <button class="btn btn-navy btn-sm"><i class="fas fa-search"></i> Search</button>
            <?php if (!empty($_GET['q'])): ?>
              <a class="btn btn-ghost btn-sm" href="?page=admin&f=<?= h($_GET['f'] ?? 'all') ?>">✕ Clear</a>
            <?php endif; ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
          </form>

          <div class="btn-row no-print" style="margin-bottom:18px;">
            <?php foreach (['all' => 'All Orders', 'active' => 'Active Deliveries', 'pending' => 'Pending Cash', 'delivered' => 'Delivered', 'failed' => 'Cancelled/Failed'] as $k => $lbl): ?>
              <a class="btn btn-sm <?= ($_GET['f'] ?? 'all') === $k ? 'btn-navy' : 'btn-ghost' ?>"
                href="?page=admin&f=<?= $k ?><?= !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '' ?>"><?= h($lbl) ?></a>
            <?php endforeach; ?>
          </div>

          <?php if (!$adminRows): ?>
            <p class="sub" style="margin:0">No parcel records found.</p>
          <?php else: ?>
            <div class="tbl-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Sender & Receiver</th>
                    <th>Parcel</th>
                    <th>Payment (COD)</th>
                    <th>Delivery Status</th>
                    <th>Driver Assigned</th>
                    <th>WhatsApp Actions</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($adminRows as $r): ?>
                    <?php $waAdminMsg = buildWhatsAppMessage($r); ?>
                    <tr>
                      <td class="mono" style="font-weight:800; font-size:13px;">
                        <a href="<?= h(trackLink($r)) ?>" target="_blank" style="color:var(--blue);"><?= h($r['order_code']) ?></a>
                        <div style="font-size:11.5px; color:var(--muted); font-weight:normal;"><?= h($r['created_at']) ?></div>
                      </td>
                      <td>
                        <b>From:</b> <?= h($r['pickup_name']) ?> (<?= h($r['pickup_mobile']) ?>)<br>
                        <span style="font-size:12px; color:var(--muted);"><?= h($r['pickup_address']) ?></span>
                        <div style="margin:4px 0;">
                          <b>To:</b> <?= h($r['receiver_name']) ?> (<?= h($r['receiver_mobile']) ?>)<br>
                          <span style="font-size:12px; color:var(--muted);"><?= h($r['delivery_address']) ?></span>
                        </div>
                        <div style="display:flex; gap:6px; margin-top:4px;">
                          <a href="<?= h(mapsSearchUrl($r['pickup_address'], $r['pickup_coords'] ?? '')) ?>" target="_blank" class="btn-gps" style="font-size:10.5px; padding:2px 6px;">📍 Pickup Pin</a>
                          <a href="<?= h(mapsSearchUrl($r['delivery_address'], $r['delivery_coords'] ?? '')) ?>" target="_blank" class="btn-gps" style="font-size:10.5px; padding:2px 6px;">📍 Drop Pin</a>
                        </div>
                      </td>
                      <td>
                        <b><?= h($r['parcel_type']) ?></b>
                        <?= $r['weight'] !== '' ? '<br><span style="font-size:12px; color:var(--muted);">' . h($r['weight']) . ' kg</span>' : '' ?>
                      </td>
                      <td>
                        <span class="badge b-<?= status_slug($r['payment_status']) ?>">
                          COD: <?= h(label($r['payment_status'])) ?>
                        </span>
                        <div style="font-weight:800; margin-top:3px;"><?= money((float)$r['amount']) ?></div>
                        <?php if ($r['payment_status'] === 'PENDING'): ?>
                          <form method="post" style="margin-top:4px;">
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="mark_cash_paid">
                            <input type="hidden" name="back" value="admin">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm" style="padding:4px 8px; font-size:11px; background:#15803D; color:white;">
                              Mark Cash Paid
                            </button>
                          </form>
                        <?php endif; ?>
                      </td>
                      <td>
                        <form method="post">
                          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                          <input type="hidden" name="action" value="set_delivery">
                          <input type="hidden" name="back" value="admin">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <select name="delivery_status" onchange="this.form.submit()" style="padding:6px 8px; font-size:12px;">
                            <?php foreach (DELIVERY_ALL as $s): ?>
                              <option value="<?= h($s) ?>" <?= $r['delivery_status'] === $s ? 'selected' : '' ?>><?= h(label($s)) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </form>
                      </td>
                      <td>
                        <form method="post" style="display:flex; gap:4px;">
                          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                          <input type="hidden" name="action" value="assign_driver">
                          <input type="hidden" name="back" value="admin">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <input type="text" name="driver_name" value="<?= h($r['driver_name']) ?>" placeholder="Driver Name"
                            style="padding:6px 8px; font-size:12px; width:100px;">
                          <button class="btn btn-ghost btn-sm" style="padding:6px 8px;">Save</button>
                        </form>
                      </td>
                      <td>
                        <div style="display:flex; flex-direction:column; gap:4px;">
                          <a class="btn-wa-driver" style="padding:4px 8px; font-size:11px;" target="_blank" rel="noopener"
                            href="<?= h(waLink(DRIVER_WHATSAPP, $waAdminMsg)) ?>">
                            <i class="fab fa-whatsapp"></i> Send to Driver (<?= h(DRIVER_PHONE) ?>)
                          </a>
                          <a class="btn-wa-owner" style="padding:4px 8px; font-size:11px;" target="_blank" rel="noopener"
                            href="<?= h(waLink(OWNER_WHATSAPP, $waAdminMsg)) ?>">
                            <i class="fab fa-whatsapp"></i> Send to Owner (<?= h(OWNER_PHONE) ?>)
                          </a>
                        </div>
                      </td>
                      <td>
                        <a href="<?= h(trackLink($r)) ?>" target="_blank" class="btn btn-ghost btn-sm" style="padding:4px 8px; font-size:11px;">Track</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>

  </main>

  <!-- Sticky CTA for Mobile -->
  <?php if ($page === 'home'): ?>
    <div class="sticky-cta">
      <button class="btn btn-primary" onclick="document.getElementById('parcelForm').requestSubmit();">
        🚚 Request Door Delivery &bull; <?= money(PARCEL_CHARGE) ?> Cash on Delivery
      </button>
    </div>
  <?php endif; ?>

  <!-- Global Footer -->
  <footer class="foot">
    <div style="display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:6px;">
      <img src="<?= h($logoUrl) ?>" alt="KSR GURU JI" style="width:28px; height:28px; border-radius:50%; object-fit:cover;" onerror="this.src='company_logo.jpg';">
      <b style="font-size:15px;"><?= h(APP_NAME) ?></b> &bull; <?= h(SHOP_TAGLINE) ?>
    </div>
    <div>Door Delivery Service &bull; Fixed <?= money(PARCEL_CHARGE) ?> Cash on Delivery &bull; <?= h(SHOP_ADDRESS) ?></div>
    
    <!-- WhatsApp & Phone Contacts -->
    <div style="margin-top:14px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
      <a class="btn-wa-owner" target="_blank" rel="noopener"
        href="<?= h(waLink(OWNER_WHATSAPP, 'Hello, I need help with KSR GURU JI Parcel to Door.')) ?>">
        <i class="fab fa-whatsapp"></i> WhatsApp Owner (<?= h(OWNER_PHONE) ?>)
      </a>
      <a class="btn-wa-driver" target="_blank" rel="noopener"
        href="<?= h(waLink(DRIVER_WHATSAPP, 'Hello, I need delivery service assistance.')) ?>">
        <i class="fas fa-truck"></i> WhatsApp Delivery (<?= h(DRIVER_PHONE) ?>)
      </a>
      <a class="btn btn-ghost btn-sm" href="tel:<?= h(OWNER_PHONE) ?>">
        <i class="fas fa-phone-alt"></i> Call <?= h(OWNER_PHONE) ?>
      </a>
      <a class="btn btn-ghost btn-sm" href="tel:<?= h(DRIVER_PHONE) ?>">
        <i class="fas fa-phone"></i> Call <?= h(DRIVER_PHONE) ?>
      </a>
    </div>
  </footer>

  <button class="totop" id="toTop" style="display:none;" aria-label="Back to top">↑</button>
  <div class="toast" id="toast"></div>

  <script>
    /* Digits only for phone inputs */
    document.querySelectorAll('input[type="tel"]').forEach(function(el) {
      el.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
      });
    });

    /* Live GPS Location Capture */
    function fetchLiveGPS(type) {
      var statusEl = document.getElementById(type + '_gps_status');
      var coordsEl = document.getElementById(type + '_coords');
      var addrEl   = document.getElementById(type + '_address');

      if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
      }

      statusEl.style.display = 'block';
      statusEl.style.color = '#0284C7';
      statusEl.style.background = '#E0F2FE';
      statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching precise GPS location...';

      navigator.geolocation.getCurrentPosition(function(pos) {
        var lat = pos.coords.latitude.toFixed(6);
        var lng = pos.coords.longitude.toFixed(6);
        var acc = Math.round(pos.coords.accuracy);

        coordsEl.value = lat + ',' + lng;
        statusEl.style.color = '#15803D';
        statusEl.style.background = '#DCFCE7';
        statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Live GPS Captured: ' + lat + ', ' + lng + ' (Accuracy: ±' + acc + 'm)';

        // Auto append GPS notice if address is empty
        if (addrEl && addrEl.value.trim() === '') {
          addrEl.value = 'Current Live GPS Location (Lat: ' + lat + ', Lng: ' + lng + ')';
        }
      }, function(err) {
        statusEl.style.color = '#B91C1C';
        statusEl.style.background = '#FEE2E2';
        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Location permission denied or unavailable (' + err.message + ')';
      }, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
      });
    }

    /* Client side validation */
    (function() {
      var f = document.getElementById('parcelForm');
      if (!f) return;
      f.addEventListener('submit', function(e) {
        var bad = false;
        ['pickup_name', 'pickup_mobile', 'pickup_address', 'receiver_name', 'receiver_mobile', 'delivery_address'].forEach(function(n) {
          var el = f.querySelector('[name="' + n + '"]');
          if (!el) return;
          var v = el.value.trim();
          var ok = v !== '';
          if (n.indexOf('mobile') > -1) ok = /^[6-9]\d{9}$/.test(v);
          el.classList.toggle('bad', !ok);
          if (!ok) bad = true;
        });
        if (bad) {
          e.preventDefault();
          var first = f.querySelector('.bad');
          if (first) {
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            first.focus({ preventScroll: true });
          }
        }
      });
    })();

    /* Copy order details */
    function copyOrderDetails(text) {
      navigator.clipboard.writeText(text).then(function() {
        alert('All parcel details copied to clipboard! You can paste directly into WhatsApp.');
      }).catch(function() {
        prompt('Copy details:', text);
      });
    }
  </script>
</body>

</html>