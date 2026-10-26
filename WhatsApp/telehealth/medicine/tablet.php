<?php

declare(strict_types=1);
session_start();

/* =====================================================================
   0.  CONFIG
   ===================================================================== */
const PAGE             = 'tablet.php';
const ORDER_TABLE      = 'pharmacy_orders';
const DB_HOST          = 'localhost';
const DB_NAME          = 'ksr_guru_ji';
const DB_USER          = 'root';
const DB_PASS          = 'JKRathod@406';
const PER_PAGE         = 24;

const SHOP_WHATSAPP    = '919606597558';   // medical shop owner (primary)
const SHOP_PHONE       = '9606597558';
const SHOP_WHATSAPP2   = '916362079192';   // medical shop owner (backup)
const SHOP_PHONE2      = '6362079192';
const DELIVERY_NAME    = 'JK';
const DELIVERY_WHATSAPP = '918088584421';  // delivery boy JK
const DELIVERY_PHONE    = '8088584421';
const DELIVERY_FEE     = 15.00;

const UPLOAD_DIR       = __DIR__ . '/uploads/prescriptions';
const UPLOAD_URL       = 'uploads/prescriptions';

const PHARMACIST_USER  = 'pharmacist';
const PHARMACIST_PASS  = 'KSR@2026';   /* ← CHANGE THIS */

/* ---------- Shop identity (used on invoices) ---------- */
const SHOP_NAME     = 'KSR GURU JI PHARMACY';
const SHOP_TAGLINE  = 'Doctor Assist · Medicine & Prescription Services';
const SHOP_ADDRESS  = 'Main Road, Near Bus Stand, Your City, State – 000000';
const SHOP_GSTIN    = '';   // optional — leave empty to hide
const SHOP_EMAIL    = '';
const OWNER_NAME    = 'BHEEMAPPA KALI-APPA HANAMANNAVAR';
const OWNER_EXP     = 'D. Pharm · 6+ Years Experience';

/* =====================================================================
   1.  DATABASE
   ===================================================================== */
try {
  $dbHost = getenv('DB_HOST') ?: DB_HOST;
  $dbPort = getenv('DB_PORT') ?: '3306';
  $dbUser = getenv('DB_USER') ?: DB_USER;
  $dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : DB_PASS;
  $dbName = getenv('DB_NAME') ?: DB_NAME;

  $pdo = new PDO(
    'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4',
    $dbUser,
    $dbPass,
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
      PDO::ATTR_TIMEOUT            => 3,
    ]
  );
} catch (Throwable $e) {
  $sourceSqlite = __DIR__ . '/medicines.sqlite';
  $tmpSqlite    = sys_get_temp_dir() . '/medicines.sqlite';
  $sqliteDb     = null;

  if (file_exists($sourceSqlite)) {
    // If running in a writable temp directory (e.g. Vercel /tmp), copy to allow order placing
    if (is_writable(sys_get_temp_dir())) {
      if (!file_exists($tmpSqlite) || filesize($tmpSqlite) !== filesize($sourceSqlite)) {
        @copy($sourceSqlite, $tmpSqlite);
      }
      $sqliteDb = file_exists($tmpSqlite) && is_writable($tmpSqlite) ? $tmpSqlite : $sourceSqlite;
    } else {
      $sqliteDb = $sourceSqlite;
    }
    $pdo = new PDO('sqlite:' . $sqliteDb);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  } else {
    $pdo = new PDO('sqlite::memory:');
  }
}

/* ---------- Auto-create the pharmacy order table if missing ----------
   The project `orders` table belongs to the e-commerce module and has an
   incompatible schema, so medicine orders live in `pharmacy_orders`.       */
function ensureOrderTable(PDO $pdo): void
{
  try {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
      $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pharmacy_orders (
          id               INTEGER PRIMARY KEY AUTOINCREMENT,
          order_code       TEXT NOT NULL UNIQUE,
          customer_name    TEXT NOT NULL,
          customer_phone   TEXT NOT NULL,
          address_text     TEXT NULL,
          latitude         REAL NULL,
          longitude        REAL NULL,
          accuracy_m       REAL NULL,
          maps_url         TEXT NULL,
          items_json       TEXT NULL,
          items_text       TEXT NULL,
          subtotal         REAL NOT NULL DEFAULT 0.00,
          delivery         REAL NOT NULL DEFAULT 0.00,
          total            REAL NOT NULL DEFAULT 0.00,
          needs_rx         INTEGER NOT NULL DEFAULT 0,
          rx_file          TEXT NULL,
          customer_note    TEXT NULL,
          status           TEXT NOT NULL DEFAULT 'pending',
          reject_reason    TEXT NULL,
          verified_by      TEXT NULL,
          verified_at      TEXT NULL,
          created_at       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
      );
      return;
    }

    $pdo->query(
      "CREATE TABLE IF NOT EXISTS pharmacy_orders (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_code VARCHAR(32) NOT NULL,
        customer_name VARCHAR(120) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        address_text TEXT NULL,
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
        accuracy_m DECIMAL(10,2) NULL,
        maps_url VARCHAR(255) NULL,
        items_json LONGTEXT NULL,
        items_text LONGTEXT NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        delivery DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        needs_rx TINYINT(1) NOT NULL DEFAULT 0,
        rx_file VARCHAR(255) NULL,
        customer_note VARCHAR(500) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        reject_reason VARCHAR(255) NULL,
        verified_by VARCHAR(60) NULL,
        verified_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_po_code (order_code),
        KEY idx_po_status (status),
        KEY idx_po_phone (customer_phone)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
  } catch (Throwable $ex) {
    /* table already exists or read-only filesystem — queries below will report */
  }
}
ensureOrderTable($pdo);

/* =====================================================================
   2.  HELPERS
   ===================================================================== */
function e(mixed $v): string
{
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function money(?float $v): string
{
  return $v === null ? '—' : '₹' . number_format($v, 2);
}
function stockLabel(?int $stock): array
{
  if ($stock === null)  return ['na',  'Not tracked'];
  if ($stock <= 0)      return ['out', 'Out of stock'];
  if ($stock <= 20)     return ['low', 'Low stock (' . $stock . ')'];
  return ['in', 'In stock (' . $stock . ')'];
}
function isRx(?string $status): bool
{
  if (!$status) return false;
  if (stripos($status, 'not specified') !== false) return false;
  return stripos($status, 'prescription') !== false;
}
function defaultOrderingNote(): string
{
  return 'Enter the required number of packs in Order Quantity; '
    . 'verify current price, stock, batch number, expiry date '
    . 'and pack integrity before dispensing.';
}

/* =====================================================================
   3.  SESSION CART
   ===================================================================== */
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
  $_SESSION['cart'] = [];
}
foreach ($_SESSION['cart'] as $cartId => $cartQty) {
  if (is_array($cartQty)) $cartQty = $cartQty['qty'] ?? 0;
  if (is_bool($cartQty))  $cartQty = $cartQty ? 1 : 0;
  if (!is_numeric($cartQty)) {
    unset($_SESSION['cart'][$cartId]);
    continue;
  }
  $cartQty = (int)$cartQty;
  $cartKey = (int)$cartId;
  if ($cartKey <= 0 || $cartQty <= 0) {
    unset($_SESSION['cart'][$cartId]);
    continue;
  }
  if ((string)$cartKey !== (string)$cartId) unset($_SESSION['cart'][$cartId]);
  $_SESSION['cart'][$cartKey] = $cartQty;
}
function getCartCount(): int
{
  $count = 0;
  foreach ($_SESSION['cart'] ?? [] as $qty) {
    if (is_array($qty)) $qty = $qty['qty'] ?? 0;
    if (is_numeric($qty)) $count += max(0, (int)$qty);
  }
  return $count;
}

/* =====================================================================
   4.  AJAX / JSON ENDPOINTS
   ===================================================================== */
if (isset($_GET['action'])) {
  header('Content-Type: application/json; charset=utf-8');
  $action = (string)$_GET['action'];
  $out    = ['ok' => false];

  try {
    switch ($action) {

      case 'suggest':
        $q = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) {
          echo json_encode(['ok' => true, 'items' => []]);
          exit;
        }
        $st = $pdo->prepare(
          "SELECT id, medicine_name, strength, dosage_form, pack_quantity, selling_price
             FROM medicine_catalogue
            WHERE status = 1 AND (medicine_name LIKE :q OR composition LIKE :q2)
            ORDER BY medicine_name ASC LIMIT 8"
        );
        $st->execute([':q' => '%' . $q . '%', ':q2' => '%' . $q . '%']);
        echo json_encode(['ok' => true, 'items' => $st->fetchAll()]);
        exit;

      case 'cart_add':
        $id  = (int)($_POST['id']  ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $st  = $pdo->prepare("SELECT id, stock FROM medicine_catalogue WHERE id = :id AND status = 1");
        $st->execute([':id' => $id]);
        $med = $st->fetch();
        if (!$med) {
          echo json_encode(['ok' => false, 'msg' => 'Medicine not found.']);
          exit;
        }
        $current = (int)($_SESSION['cart'][$id] ?? 0);
        $new     = $current + $qty;
        if ($med['stock'] !== null && $new > (int)$med['stock']) {
          echo json_encode(['ok' => false, 'msg' => 'Only ' . (int)$med['stock'] . ' unit(s) available.']);
          exit;
        }
        $_SESSION['cart'][$id] = $new;
        $out = ['ok' => true, 'msg' => 'Added to cart.', 'count' => getCartCount()];
        break;

      case 'cart_update':
        $id  = (int)($_POST['id']  ?? 0);
        $qty = (int)($_POST['qty'] ?? 0);
        if ($qty <= 0) unset($_SESSION['cart'][$id]);
        else           $_SESSION['cart'][$id] = $qty;
        $out = ['ok' => true, 'count' => getCartCount()];
        break;

      case 'cart_remove':
        $id = (int)($_POST['id'] ?? 0);
        unset($_SESSION['cart'][$id]);
        $out = ['ok' => true, 'count' => getCartCount()];
        break;

      case 'cart_clear':
        $_SESSION['cart'] = [];
        $out = ['ok' => true, 'count' => 0];
        break;

      case 'cart_get':
        $items = [];
        $total = 0.0;
        $qtyAll = 0;
        if ($_SESSION['cart']) {
          $ids = array_map('intval', array_keys($_SESSION['cart']));
          $in  = implode(',', array_fill(0, count($ids), '?'));
          $st  = $pdo->prepare(
            "SELECT id, medicine_name, strength, dosage_form, pack_quantity,
                    selling_price, stock, prescription_status
               FROM medicine_catalogue
              WHERE id IN ($in) AND status = 1"
          );
          $st->execute($ids);
          foreach ($st->fetchAll() as $row) {
            $qty   = (int)$_SESSION['cart'][$row['id']];
            $price = $row['selling_price'] !== null ? (float)$row['selling_price'] : null;
            $line  = $price !== null ? $price * $qty : null;
            if ($line !== null) $total += $line;
            $qtyAll += $qty;
            $items[] = [
              'id'         => (int)$row['id'],
              'name'       => $row['medicine_name'],
              'strength'   => $row['strength'],
              'form'       => $row['dosage_form'],
              'pack'       => $row['pack_quantity'],
              'qty'        => $qty,
              'price'      => $price,
              'price_text' => money($price),
              'line_text'  => $line !== null ? money($line) : '—',
              'rx'         => $row['prescription_status'],
            ];
          }
        }
        $out = [
          'ok' => true,
          'items' => $items,
          'count' => $qtyAll,
          'total' => $total,
          'total_text' => money($total),
        ];
        break;

      default:
        $out = ['ok' => false, 'msg' => 'Unknown action.'];
    }
  } catch (Throwable $ex) {
    http_response_code(500);
    $out = ['ok' => false, 'msg' => 'Server error.'];
  }
  echo json_encode($out);
  exit;
}

/* =====================================================================
   5.  ROUTING
   ===================================================================== */
$route    = (string)($_GET['page'] ?? 'home');   // home | checkout | order-status | pharmacist | invoice
$detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* ---------------- Pharmacist auth ---------------- */
if (($_POST['do'] ?? '') === 'login' && $route === 'pharmacist') {
  if (($_POST['u'] ?? '') === PHARMACIST_USER && ($_POST['p'] ?? '') === PHARMACIST_PASS) {
    $_SESSION['pharm']      = true;
    $_SESSION['pharm_name'] = PHARMACIST_USER;
  } else {
    $flash = ['err', 'Invalid credentials.'];
  }
  header('Location: ' . PAGE . '?page=pharmacist');
  exit;
}
if ($route === 'pharmacist' && ($_GET['action2'] ?? '') === 'logout') {
  unset($_SESSION['pharm']);
  session_destroy();
  header('Location: ' . PAGE . '?page=pharmacist');
  exit;
}
$pharmLoggedIn = !empty($_SESSION['pharm']);

/* ---------------- Pharmacist status update ---------------- */
if ($pharmLoggedIn && ($_POST['do'] ?? '') === 'setstatus' && $route === 'pharmacist') {
  $id     = (int)($_POST['id'] ?? 0);
  $status = (string)($_POST['status'] ?? '');
  $reason = trim((string)($_POST['reason'] ?? ''));
  $allowed = ['verified', 'rejected', 'dispatched', 'completed', 'pending'];

  if ($id > 0 && in_array($status, $allowed, true)) {
    if ($status === 'rejected' && $reason === '') {
      $_SESSION['flash'] = ['err', 'Reject reason is required.'];
      $nowFn = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') ? 'CURRENT_TIMESTAMP' : 'NOW()';
      $st = $pdo->prepare(
        "UPDATE pharmacy_orders
            SET status = :s, reject_reason = :r, verified_by = :v, verified_at = $nowFn
          WHERE id = :id"
      );
      $st->execute([
        ':s'  => $status,
        ':r'  => $status === 'rejected' ? mb_substr($reason, 0, 255) : null,
        ':v'  => $_SESSION['pharm_name'] ?? 'pharmacist',
        ':id' => $id,
      ]);
      $_SESSION['flash'] = ['ok', 'Order updated to “' . $status . '”.'];
    }
  }
  header('Location: ' . PAGE . '?page=pharmacist&f=' . urlencode((string)($_POST['f'] ?? 'pending')));
  exit;
}

/* =====================================================================
   6.  CHECKOUT: build cart lines + handle placement
   ===================================================================== */
function cartLines(PDO $pdo): array
{
  $cart = $_SESSION['cart'] ?? [];
  if (!is_array($cart) || !$cart) {
    return ['rows' => [], 'subtotal' => 0.0, 'count' => 0, 'needs_rx' => false];
  }
  $ids = [];
  foreach ($cart as $k => $v) {
    $id = (int)$k;
    $q = (int)$v;
    if ($id > 0 && $q > 0) $ids[$id] = $q;
  }
  if (!$ids) return ['rows' => [], 'subtotal' => 0.0, 'count' => 0, 'needs_rx' => false];

  $rows = [];
  $subtotal = 0.0;
  $count = 0;
  $needsRx = false;

  try {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare(
      "SELECT id, medicine_name, strength, dosage_form, pack_quantity,
              selling_price, stock, prescription_status, why_used, composition
         FROM medicine_catalogue
        WHERE id IN ($in) AND status = 1"
    );
    $st->execute(array_keys($ids));
    $fetched = $st->fetchAll();
  } catch (Throwable $e) {
    $fetched = [];
  }

  foreach ($fetched as $r) {
    $qty   = (int)$ids[(int)$r['id']];
    $price = $r['selling_price'] !== null ? (float)$r['selling_price'] : null;
    $line  = $price !== null ? $price * $qty : null;
    if ($line !== null) $subtotal += $line;
    $count += $qty;

    $uses = trim((string)$r['why_used']);
    if ($uses === '') $uses = trim((string)$r['composition']);
    if ($uses === '') $uses = 'Uses not listed in catalogue';

    $ps   = (string)$r['prescription_status'];
    $isRx = $ps !== '' && stripos($ps, 'not specified') === false
      && stripos($ps, 'prescription') !== false;
    if ($isRx) $needsRx = true;

    $rows[] = [
      'id'       => (int)$r['id'],
      'name'     => (string)$r['medicine_name'],
      'strength' => (string)$r['strength'],
      'form'     => (string)$r['dosage_form'],
      'pack'     => (string)$r['pack_quantity'],
      'qty'      => $qty,
      'price'    => $price,
      'line'     => $line,
      'uses'     => $uses,
      'rx'       => $isRx,
    ];
  }
  return ['rows' => $rows, 'subtotal' => $subtotal, 'count' => $count, 'needs_rx' => $needsRx];
}

function itemsToText(array $rows): string
{
  $out = [];
  $n = 1;
  foreach ($rows as $r) {
    $pack = $r['pack'] !== '' ? ' | Pack: ' . $r['pack'] : '';
    $out[] = $n++ . '. *' . $r['name'] . '*'
      . ($r['strength'] !== '' ? ' ' . $r['strength'] : '')
      . "\n    Uses: " . $r['uses']
      . "\n    Qty: *" . $r['qty'] . ' tablet(s)*' . $pack
      . ' | ₹' . number_format((float)($r['line'] ?? 0), 2);
  }
  return implode("\n", $out);
}

function makeOrderCode(PDO $pdo): string
{
  for ($i = 0; $i < 8; $i++) {
    $code = 'KSR-' . date('ymd') . '-' . random_int(1000, 9999);
    $st = $pdo->prepare("SELECT 1 FROM pharmacy_orders WHERE order_code = ? LIMIT 1");
    $st->execute([$code]);
    if (!$st->fetchColumn()) return $code;
  }
  return 'KSR-' . date('ymd') . '-' . random_int(10000, 99999);
}

function whatsappText(
  string $code,
  string $name,
  string $phone,
  string $addr,
  ?string $maps,
  array $cart,
  ?string $note,
  bool $rx,
  float $total,
  ?float $acc = null,
  ?string $rxFile = null
): string {
  $invUrl  = invoiceUrl($code);
  $lines   = [];
  $lines[] = "*NEW ORDER — " . SHOP_NAME . "*";
  $lines[] = "Order: *{$code}*";
  $lines[] = "Customer: {$name}";
  $lines[] = "Phone: {$phone}";
  $lines[] = "";
  $lines[] = "*Medicines & uses:*";
  $lines[] = itemsToText($cart['rows']);
  $lines[] = "";
  $lines[] = '*Total tablets: ' . (int)$cart['count'] . '*';
  $lines[] = 'Subtotal: ₹' . number_format($cart['subtotal'], 2);
  $lines[] = 'Delivery: ₹' . number_format(DELIVERY_FEE, 2);
  $lines[] = '*Grand Total: ₹' . number_format($total, 2) . '*';
  $lines[] = "";
  $lines[] = 'Prescription: ' . ($rx ? ($rxFile ? 'ATTACHED & VERIFIED ✓' : 'REQUIRED — photo/PDF submitted') : 'Not required');
  $lines[] = 'Delivery Address: ' . $addr;
  if ($maps) {
    $lines[] = 'Live location: ' . $maps . ($acc ? ' (Accuracy: ~' . round($acc) . 'm)' : '');
  }
  if ($note !== '') $lines[] = 'Customer Note: ' . $note;
  $lines[] = "";
  $lines[] = "📄 *Invoice copy:* " . $invUrl;
  $lines[] = "";
  $lines[] = "⚠️ *Price vary please customer will support this line.*";
  $lines[] = '_Awaiting pharmacist verification._';
  return implode("\n", $lines);
}

function deliveryBoyWhatsAppText(
  string $code,
  string $name,
  string $phone,
  string $addr,
  ?string $maps,
  array $cartRows,
  ?float $acc,
  ?string $rxFile,
  float $total
): string {
  $invUrl  = invoiceUrl($code);
  $totalTabs = 0;
  foreach ($cartRows as $it) $totalTabs += (int)($it['qty'] ?? 0);

  $lines   = [];
  $lines[] = "*🚚 DELIVERY ORDER — " . SHOP_NAME . "*";
  $lines[] = "Delivery Person: *" . DELIVERY_NAME . "*";
  $lines[] = "Order: *{$code}*";
  $lines[] = "Customer: {$name}";
  $lines[] = "Phone: {$phone}";
  $lines[] = "*Total tablets: {$totalTabs}*";
  $lines[] = "";
  $lines[] = "*Items to deliver:*";
  foreach ($cartRows as $idx => $it) {
    $lines[] = ($idx + 1) . '. ' . $it['name']
      . (!empty($it['strength']) ? ' ' . $it['strength'] : '')
      . ' — Qty: ' . (int)$it['qty'] . ' tablet(s)';
  }
  $lines[] = "";
  $lines[] = "Address: {$addr}";
  if ($maps) {
    $lines[] = "Live location: {$maps}" . ($acc ? " (Accuracy: ~" . round($acc) . "m)" : "");
  }
  if ($rxFile) {
    $lines[] = "Prescription: Verified & Attached";
  }
  $lines[] = "";
  $lines[] = "Collect *₹" . number_format($total, 2) . "* from customer.";
  $lines[] = "";
  $lines[] = "📄 *Invoice copy:* " . $invUrl;
  $lines[] = "";
  $lines[] = "⚠️ *Price vary please customer will support this line.*";
  $lines[] = "_Thank you JK_ 🙏";

  return implode("\n", $lines);
}

$cart    = cartLines($pdo);
$errors  = [];
$placed  = null;
$waLink  = null;
$deliveryWaLink = null;

if ($route === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'place') {

  $name  = trim((string)($_POST['name']  ?? ''));
  $phone = preg_replace('/\D+/', '', (string)($_POST['phone'] ?? ''));
  $addr  = trim((string)($_POST['address'] ?? ''));
  $note  = trim((string)($_POST['note'] ?? ''));
  $lat   = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
  $lng   = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;
  $acc   = ($_POST['acc'] ?? '') !== '' ? (float)$_POST['acc'] : null;

  if ($name === '' || mb_strlen($name) < 2)       $errors[] = 'Please enter your full name.';
  if (strlen($phone) < 10 || strlen($phone) > 13) $errors[] = 'Enter a valid 10-digit mobile number.';
  if ($addr === '' || mb_strlen($addr) < 8)       $errors[] = 'Please enter your delivery address.';
  if (!$cart['rows'])                             $errors[] = 'Your cart is empty.';

  $mapsUrl = ($lat !== null && $lng !== null)
    ? 'https://maps.google.com/?q=' . $lat . ',' . $lng
    : null;

  $rxFile = null;
  if (!empty($_FILES['rx']['name']) && (int)$_FILES['rx']['error'] === UPLOAD_ERR_OK) {
    $size = (int)$_FILES['rx']['size'];
    if ($size > 5 * 1024 * 1024) {
      $errors[] = 'Prescription file must be under 5 MB.';
    } else {
      $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['rx']['tmp_name']);
      $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
      if (!isset($ok[$mime])) {
        $errors[] = 'Prescription must be a JPG, PNG, WEBP or PDF.';
      } else {
        $uploadDir = UPLOAD_DIR;
        $fn = 'rx_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ok[$mime];
        $saved = false;

        // Try code directory if writable
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
        if (is_dir($uploadDir) && is_writable($uploadDir)) {
          if (@move_uploaded_file($_FILES['rx']['tmp_name'], $uploadDir . '/' . $fn)) {
            $rxFile = UPLOAD_URL . '/' . $fn;
            $saved = true;
          }
        }

        // Serverless / Read-only fallback (Vercel /var/task is read-only; /tmp is writable)
        if (!$saved) {
          $tmpDir = sys_get_temp_dir() . '/ksr_prescriptions';
          if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
          if (is_dir($tmpDir) && is_writable($tmpDir) && @move_uploaded_file($_FILES['rx']['tmp_name'], $tmpDir . '/' . $fn)) {
            $rawContent = @file_get_contents($tmpDir . '/' . $fn);
            $rxFile = $rawContent ? ('data:' . $mime . ';base64,' . base64_encode($rawContent)) : (UPLOAD_URL . '/' . $fn);
            $saved = true;
          } elseif (!empty($_FILES['rx']['tmp_name']) && file_exists($_FILES['rx']['tmp_name'])) {
            $rawContent = @file_get_contents($_FILES['rx']['tmp_name']);
            if ($rawContent !== false && strlen($rawContent) > 0) {
              $rxFile = 'data:' . $mime . ';base64,' . base64_encode($rawContent);
              $saved = true;
            }
          }
        }

        if (!$saved) {
          $errors[] = 'Could not save the prescription file. Please try again.';
        }
      }
    }
  }

  if ($cart['needs_rx'] && !$rxFile) {
    $errors[] = 'One or more items need a prescription. Please attach a photo/PDF.';
  }

  if (!$errors) {
    $delivery = DELIVERY_FEE;
    $total    = $cart['subtotal'] + $delivery;
    $itemsTxt = itemsToText($cart['rows']);
    $code     = makeOrderCode($pdo);

    $st = $pdo->prepare(
      "INSERT INTO pharmacy_orders
        (order_code, customer_name, customer_phone, address_text,
         latitude, longitude, accuracy_m, maps_url,
         items_json, items_text, subtotal, delivery, total,
         needs_rx, rx_file, customer_note, status)
       VALUES
        (:code, :nm, :ph, :ad, :lat, :lng, :acc, :maps,
         :ij, :it, :sub, :dl, :tot, :rx, :rxf, :note, 'pending')"
    );
    $st->execute([
      ':code' => $code,
      ':nm' => $name,
      ':ph' => $phone,
      ':ad' => $addr,
      ':lat' => $lat,
      ':lng' => $lng,
      ':acc' => $acc,
      ':maps' => $mapsUrl,
      ':ij' => json_encode($cart['rows'], JSON_UNESCAPED_UNICODE),
      ':it' => $itemsTxt,
      ':sub' => $cart['subtotal'],
      ':dl' => $delivery,
      ':tot' => $total,
      ':rx' => $cart['needs_rx'] ? 1 : 0,
      ':rxf' => $rxFile,
      ':note' => $note !== '' ? mb_substr($note, 0, 500) : null,
    ]);

    $_SESSION['cart'] = [];
    $_SESSION['last_order'] = $code;
    $placed = [
      'code' => $code,
      'total' => $total,
      'maps' => $mapsUrl,
      'count' => $cart['count'],
      'accuracy_m' => $acc,
      'rx_file' => $rxFile
    ];

    $waText = whatsappText(
      $placed['code'],
      $name,
      $phone,
      $addr,
      $placed['maps'],
      $cart,
      $note,
      $cart['needs_rx'],
      (float)$placed['total'],
      $acc,
      $rxFile
    );
    $waLink = 'https://wa.me/' . SHOP_WHATSAPP . '?text=' . rawurlencode($waText);

    $deliveryWaText = deliveryBoyWhatsAppText(
      $placed['code'],
      $name,
      $phone,
      $addr,
      $placed['maps'],
      $cart['rows'],
      $acc,
      $rxFile,
      (float)$placed['total']
    );
    $deliveryWaLink = 'https://wa.me/' . DELIVERY_WHATSAPP . '?text=' . rawurlencode($deliveryWaText);
  }
}

/* =====================================================================
   7.  ROUTE-SPECIFIC DATA LOADING
   ===================================================================== */

/* -------- Home / Detail: catalogue -------- */
$detail = null;
$related = [];
if ($route === 'home' && $detailId > 0) {
  $st = $pdo->prepare("SELECT * FROM medicine_catalogue WHERE id = :id AND status = 1 LIMIT 1");
  $st->execute([':id' => $detailId]);
  $detail = $st->fetch() ?: null;
  if ($detail && empty($detail['ordering_note'])) {
    $detail['ordering_note'] = defaultOrderingNote();
  }
  if ($detail) {
    $st = $pdo->prepare(
      "SELECT id, medicine_name, strength, dosage_form, pack_quantity,
              selling_price, stock, prescription_status
         FROM medicine_catalogue
        WHERE status = 1 AND id <> :id
          AND ( composition = :comp OR dosage_form = :form )
        ORDER BY medicine_name ASC LIMIT 6"
    );
    $st->execute([
      ':id'   => $detailId,
      ':comp' => (string)$detail['composition'],
      ':form' => (string)$detail['dosage_form'],
    ]);
    $related = $st->fetchAll();
  }
}

/* -------- Home list filters -------- */
$q     = trim((string)($_GET['q']     ?? ''));
$form  = trim((string)($_GET['form']  ?? ''));
$rx    = trim((string)($_GET['rx']    ?? ''));
$avail = trim((string)($_GET['avail'] ?? ''));
$sort  = trim((string)($_GET['sort']  ?? 'sl_asc'));
$page  = max(1, (int)($_GET['page']   ?? 1));
$view  = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';

$where  = ['m.status = 1'];
$params = [];

if ($q !== '') {
  $terms = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $i = 0;
  foreach (array_slice($terms, 0, 6) as $t) {
    $i++;
    /* Native prepares (ATTR_EMULATE_PREPARES = false) do NOT allow a named
       placeholder to be reused, so each column gets its own placeholder. */
    $phName = ':q' . $i;
    $phComp = ':q' . $i . 'c';
    $phUse  = ':q' . $i . 'u';
    $phStr  = ':q' . $i . 's';
    $where[] = "(m.medicine_name LIKE $phName OR m.composition LIKE $phComp
                 OR m.why_used LIKE $phUse OR m.strength LIKE $phStr)";
    $params[$phName] = '%' . $t . '%';
    $params[$phComp] = '%' . $t . '%';
    $params[$phUse]  = '%' . $t . '%';
    $params[$phStr]  = '%' . $t . '%';
  }
}
if ($form !== '') {
  $where[] = 'm.dosage_form = :form';
  $params[':form'] = $form;
}
if ($rx === 'yes') $where[] = "m.prescription_status LIKE '%Prescription%'";
if ($rx === 'no')  $where[] = "m.prescription_status NOT LIKE '%Prescription%'";
if ($avail === 'in')  $where[] = 'm.stock IS NOT NULL AND m.stock > 0';
if ($avail === 'out') $where[] = 'm.stock IS NOT NULL AND m.stock <= 0';
if ($avail === 'na')  $where[] = 'm.stock IS NULL';

$whereSql = implode(' AND ', $where);
$sortMap = [
  'sl_asc'     => 'm.sl_no ASC',
  'sl_desc'    => 'm.sl_no DESC',
  'name_asc'   => 'm.medicine_name ASC',
  'name_desc'  => 'm.medicine_name DESC',
  'price_asc'  => 'm.selling_price IS NULL, m.selling_price ASC',
  'price_desc' => 'm.selling_price IS NULL, m.selling_price DESC',
  'stock_desc' => 'm.stock IS NULL, m.stock DESC',
];
$orderSql = $sortMap[$sort] ?? $sortMap['sl_asc'];

try {
  $st = $pdo->prepare("SELECT COUNT(*) FROM medicine_catalogue m WHERE $whereSql");
  $st->execute($params);
  $totalRows  = (int)$st->fetchColumn();
} catch (Throwable $e) {
  $totalRows = 0;
}
$totalPages = max(1, (int)ceil($totalRows / PER_PAGE));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * PER_PAGE;

try {
  $st = $pdo->prepare(
    "SELECT m.* FROM medicine_catalogue m
     WHERE $whereSql ORDER BY $orderSql
     LIMIT " . PER_PAGE . " OFFSET $offset"
  );
  $st->execute($params);
  $medicines = $st->fetchAll();
} catch (Throwable $e) {
  $medicines = [];
}

try {
  $forms = $pdo->query(
    "SELECT dosage_form, COUNT(*) c FROM medicine_catalogue
      WHERE status = 1 AND dosage_form IS NOT NULL AND dosage_form <> ''
      GROUP BY dosage_form ORDER BY c DESC"
  )->fetchAll();
} catch (Throwable $e) {
  $forms = [];
}

$cartCount = getCartCount();

/* -------- Order status / Invoice -------- */
$order = null;
if ($route === 'order-status' || $route === 'invoice') {
  $code = trim((string)($_GET['code'] ?? $_SESSION['last_order'] ?? ''));
  if ($code !== '') {
    $st = $pdo->prepare("SELECT * FROM pharmacy_orders WHERE order_code = :c LIMIT 1");
    $st->execute([':c' => $code]);
    $order = $st->fetch() ?: null;
  }
}

/* -------- Pharmacist console -------- */
$pharmFilter = (string)($_GET['f'] ?? 'pending');
$pharmWhere  = in_array($pharmFilter, ['pending', 'verified', 'rejected', 'dispatched', 'completed', 'all'], true)
  ? ($pharmFilter === 'all' ? '1' : "status = " . $pdo->quote($pharmFilter))
  : "status = 'pending'";

$pharmOrders = [];
$pharmCounts = [];
if ($route === 'pharmacist' && $pharmLoggedIn) {
  $pharmOrders = $pdo->query("SELECT * FROM pharmacy_orders WHERE $pharmWhere ORDER BY id DESC LIMIT 200")->fetchAll();
  $pharmCounts = $pdo->query("SELECT status, COUNT(*) c FROM pharmacy_orders GROUP BY status")
    ->fetchAll(PDO::FETCH_KEY_PAIR);
}

/* -------- Pharmacist pending count (AJAX) -------- */
if ($route === 'pharmacist' && ($_GET['action2'] ?? '') === 'count') {
  header('Content-Type: application/json');
  if (!$pharmLoggedIn) {
    echo json_encode(['ok' => false]);
    exit;
  }
  $n = (int)$pdo->query("SELECT COUNT(*) FROM pharmacy_orders WHERE status='pending'")->fetchColumn();
  echo json_encode(['ok' => true, 'pending' => $n]);
  exit;
}

/* helper for query-string preservation */
function qs(array $overrides = []): string
{
  $base = $_GET;
  unset($base['id'], $base['action'], $base['action2']);
  return http_build_query(array_merge($base, $overrides));
}

/* -------- Build WhatsApp link for a given order (used in pharmacist + delivery) -------- */
function deliveryWhatsAppLink(array $o): string
{
  $items = json_decode((string)$o['items_json'], true) ?: [];
  $totalTabs = 0;
  foreach ($items as $it) $totalTabs += (int)($it['qty'] ?? 0);

  $invUrl = invoiceUrl((string)$o['order_code']);

  $lines   = [];
  $lines[] = "*🚚 DELIVERY ASSIGNED — " . SHOP_NAME . "*";
  $lines[] = "Delivery boy: *" . DELIVERY_NAME . "*";
  $lines[] = "Order: *{$o['order_code']}*";
  $lines[] = "Customer: {$o['customer_name']}";
  $lines[] = "Phone: {$o['customer_phone']}";
  $lines[] = "*Total tablets: {$totalTabs}*";
  $lines[] = "";
  $lines[] = "*Items to deliver*";
  foreach ($items as $idx => $it) {
    $lines[] = ($idx + 1) . '. ' . $it['name']
      . (!empty($it['strength']) ? ' ' . $it['strength'] : '')
      . ' — Qty: ' . (int)$it['qty'] . ' tablet(s)';
  }
  $lines[] = "Address: {$o['address_text']}";
  if (!empty($o['maps_url'])) {
    $lines[] = "Live location: {$o['maps_url']}" . (!empty($o['accuracy_m']) ? " (Accuracy: ~" . round((float)$o['accuracy_m']) . "m)" : "");
  }
  if (!empty($o['needs_rx'])) {
    $lines[] = "Prescription: " . (!empty($o['rx_file']) ? "Verified & Attached ✓" : "Required on delivery");
  }
  $lines[] = "";
  $lines[] = "Collect *₹" . number_format((float)$o['total'], 2) . "* from customer.";
  $lines[] = "";
  $lines[] = "📄 *Invoice copy:* " . $invUrl;
  $lines[] = "";
  $lines[] = "⚠️ *Price vary please customer will support this line.*";
  $lines[] = "_Thank you JK_ 🙏";

  return 'https://wa.me/' . DELIVERY_WHATSAPP . '?text=' . rawurlencode(implode("\n", $lines));
}

/* -------- Absolute URL of the invoice for a given order code -------- */
function invoiceUrl(string $code): string
{
  $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
  return $scheme . '://' . $host . $dir . '/tablet.php?page=invoice&code=' . urlencode($code);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($route === 'order-status' && $order): ?>
    <meta http-equiv="refresh" content="30">
  <?php endif; ?>
  <title>
    <?php
    if ($route === 'checkout')          echo 'Checkout · ';
    elseif ($route === 'order-status')  echo 'Order status · ';
    elseif ($route === 'pharmacist')    echo 'Pharmacist · ';
    elseif ($route === 'invoice')       echo 'Invoice · ';
    elseif ($detail)                    echo e($detail['medicine_name']) . ' — ';
    ?>
    KSR GURU JI Pharmacy · Doctor Assist
  </title>
  <style>
    :root {
      --navy: #0B1F3A;
      --blue: #1976D2;
      --teal: #00897B;
      --success: #2E7D32;
      --warning: #F9A825;
      --danger: #D32F2F;
      --purple: #7C3AED;
      --bg: #F5F8FA;
      --card: #FFFFFF;
      --line: #E2E8F0;
      --text: #0F172A;
      --muted: #64748B;
      --radius: 14px;
      --shadow: 0 1px 3px rgba(11, 31, 58, .06), 0 8px 24px rgba(11, 31, 58, .06);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    html,
    body {
      height: 100%
    }

    body {
      font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    a {
      color: inherit;
      text-decoration: none
    }

    button,
    input,
    textarea,
    select {
      font: inherit;
      color: inherit
    }

    /* header */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 60;
      background: var(--navy);
      color: #fff;
      box-shadow: 0 2px 12px rgba(11, 31, 58, .18)
    }

    .topbar-in {
      max-width: 1380px;
      margin: 0 auto;
      padding: 12px 18px;
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 700;
      font-size: 1.05rem;
      white-space: nowrap
    }

    .brand .logo {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--blue), var(--teal));
      display: grid;
      place-items: center;
      font-size: 18px
    }

    .brand small {
      display: block;
      font-weight: 400;
      font-size: .7rem;
      opacity: .72;
      letter-spacing: .4px
    }

    .pill {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, .12);
      border: 1px solid rgba(255, 255, 255, .2);
      padding: 8px 14px;
      border-radius: 20px;
      font-weight: 700;
      font-size: .84rem
    }

    .pill .dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--warning);
      animation: pulse 1.6s infinite
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1
      }

      50% {
        opacity: .35
      }
    }

    .searchwrap {
      position: relative;
      flex: 1 1 340px;
      min-width: 220px
    }

    .searchwrap input {
      width: 100%;
      padding: 11px 44px 11px 42px;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, .18);
      background: rgba(255, 255, 255, .10);
      color: #fff;
      outline: none;
      transition: .18s
    }

    .searchwrap input::placeholder {
      color: rgba(255, 255, 255, .62)
    }

    .searchwrap input:focus {
      background: #fff;
      color: var(--text);
      border-color: #fff
    }

    .searchwrap input:focus::placeholder {
      color: var(--muted)
    }

    .searchwrap .ico {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      opacity: .75;
      pointer-events: none
    }

    .searchwrap .clr {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: 0;
      cursor: pointer;
      color: inherit;
      opacity: .6;
      padding: 6px 8px;
      border-radius: 8px;
      display: none
    }

    .suggest {
      position: absolute;
      left: 0;
      right: 0;
      top: calc(100% + 6px);
      background: #fff;
      color: var(--text);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 12px 32px rgba(11, 31, 58, .22);
      display: none;
      z-index: 70
    }

    .suggest.open {
      display: block
    }

    .suggest a {
      display: block;
      padding: 10px 14px;
      border-bottom: 1px solid var(--line);
      font-size: .9rem
    }

    .suggest a:last-child {
      border-bottom: 0
    }

    .suggest a:hover {
      background: #F1F6FC
    }

    .suggest .meta {
      font-size: .76rem;
      color: var(--muted)
    }

    .cartbtn {
      position: relative;
      background: rgba(255, 255, 255, .12);
      border: 1px solid rgba(255, 255, 255, .2);
      color: #fff;
      padding: 10px 16px;
      border-radius: 12px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
      transition: .18s
    }

    .cartbtn:hover {
      background: rgba(255, 255, 255, .2)
    }

    .cartbtn .badge {
      background: var(--warning);
      color: #3b2600;
      font-weight: 700;
      border-radius: 20px;
      padding: 1px 8px;
      font-size: .74rem;
      min-width: 22px;
      text-align: center
    }

    .rxlink {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
      padding: 10px 16px;
      border-radius: 12px;
      font-weight: 700;
      font-size: .86rem;
      text-decoration: none;
      background: linear-gradient(135deg, var(--blue), var(--teal));
      color: #fff;
      transition: .18s
    }

    .rxlink:hover {
      transform: translateY(-1px);
      filter: brightness(1.08)
    }

    .rxlink.call {
      background: #16A34A
    }

    .rxlink.call:hover {
      background: #15803D
    }

    .rxlink.delivery {
      background: #7C3AED
    }

    .rxlink.delivery:hover {
      background: #6D28D9
    }

    .rxlink.small {
      padding: 7px 12px;
      font-size: .78rem;
      border-radius: 10px
    }

    .rxlink.ghost {
      background: rgba(255, 255, 255, .14);
      border: 1px solid rgba(255, 255, 255, .2)
    }

    /* layout */
    .wrap {
      max-width: 1380px;
      margin: 0 auto;
      padding: 22px 18px 90px
    }

    .wrap-narrow {
      max-width: 960px;
      margin: 0 auto;
      padding: 22px 18px 80px
    }

    .wrap-mid {
      max-width: 860px;
      margin: 0 auto;
      padding: 22px 18px 80px
    }

    .crumbs {
      font-size: .82rem;
      color: var(--muted);
      margin-bottom: 14px
    }

    .crumbs a {
      color: var(--blue)
    }

    .crumbs span {
      margin: 0 6px;
      opacity: .5
    }

    /* buttons */
    .btn {
      border: 0;
      cursor: pointer;
      border-radius: 10px;
      padding: 10px 16px;
      font-weight: 600;
      font-size: .88rem;
      transition: .18s;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      justify-content: center;
      text-align: center
    }

    .btn-primary {
      background: var(--blue);
      color: #fff
    }

    .btn-primary:hover {
      background: #1565C0
    }

    .btn-ghost {
      background: #EEF3F9;
      color: var(--navy)
    }

    .btn-ghost:hover {
      background: #E1EAF4
    }

    .btn-teal {
      background: var(--teal);
      color: #fff
    }

    .btn-teal:hover {
      background: #00796B
    }

    .btn-wa {
      background: #25D366;
      color: #fff
    }

    .btn-wa:hover {
      background: #1EBE57
    }

    .btn-ok {
      background: var(--success);
      color: #fff
    }

    .btn-ok:hover {
      background: #256428
    }

    .btn-no {
      background: var(--danger);
      color: #fff
    }

    .btn-no:hover {
      background: #B71C1C
    }

    .btn-purple {
      background: var(--purple);
      color: #fff
    }

    .btn-purple:hover {
      background: #6D28D9
    }

    .btn-line {
      background: #fff;
      border: 1px solid var(--line);
      color: var(--navy)
    }

    .btn-line:hover {
      border-color: var(--blue);
      color: var(--blue)
    }

    .btn:disabled {
      opacity: .5;
      cursor: not-allowed
    }

    .btn-sm {
      padding: 7px 12px;
      font-size: .8rem;
      border-radius: 9px
    }

    .btn-block {
      width: 100%
    }

    /* panels / cards */
    .panel {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 20px
    }

    .panel+.panel {
      margin-top: 18px
    }

    .panel h2 {
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .7px;
      color: var(--muted);
      font-weight: 800;
      margin-bottom: 10px
    }

    .panel p {
      font-size: .9rem;
      color: #334155
    }

    .panel .kv {
      display: grid;
      grid-template-columns: 150px 1fr;
      gap: 9px 16px;
      font-size: .88rem
    }

    .panel .kv dt {
      color: var(--muted);
      font-weight: 600
    }

    .panel .kv dd {
      color: var(--text)
    }

    @media(max-width:560px) {
      .panel .kv {
        grid-template-columns: 1fr;
        gap: 2px 0
      }

      .panel .kv dd {
        margin-bottom: 8px
      }
    }

    /* filters */
    .filters {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 14px;
      box-shadow: var(--shadow);
      margin-bottom: 18px;
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      align-items: end
    }

    .field label {
      display: block;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .5px;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 5px
    }

    .field select,
    .field input,
    .field textarea {
      width: 100%;
      padding: 9px 11px;
      border-radius: 10px;
      border: 1px solid var(--line);
      background: #fff;
      outline: none;
      transition: .18s
    }

    .field textarea {
      resize: vertical;
      min-height: 82px;
      padding: 11px 13px
    }

    .field select:focus,
    .field input:focus,
    .field textarea:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 3px rgba(25, 118, 210, .14)
    }

    .filter-actions {
      display: flex;
      gap: 8px
    }

    /* results head */
    .res-head {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 14px
    }

    .res-head h1 {
      font-size: 1.15rem
    }

    .res-head .sub {
      color: var(--muted);
      font-size: .85rem;
      margin-top: 2px
    }

    .viewtoggle {
      display: flex;
      background: #E8EEF5;
      border-radius: 10px;
      padding: 3px;
      gap: 2px
    }

    .viewtoggle button {
      border: 0;
      background: none;
      padding: 7px 13px;
      border-radius: 8px;
      cursor: pointer;
      font-size: .82rem;
      font-weight: 600;
      color: var(--muted)
    }

    .viewtoggle button.on {
      background: #fff;
      color: var(--navy);
      box-shadow: 0 1px 3px rgba(11, 31, 58, .12)
    }

    /* grid */
    .grid {
      display: grid;
      gap: 16px;
      grid-template-columns: repeat(auto-fill, minmax(265px, 1fr))
    }

    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: .2s
    }

    .card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(11, 31, 58, .12);
      border-color: #CFE0F2
    }

    .card-top {
      padding: 14px 14px 12px;
      display: flex;
      gap: 12px;
      align-items: flex-start;
      border-bottom: 1px solid var(--line)
    }

    .thumb {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      flex: 0 0 auto;
      background: linear-gradient(135deg, #E3F0FF, #E0F2F1);
      display: grid;
      place-items: center;
      font-size: 22px
    }

    .card-title {
      font-size: .95rem;
      font-weight: 700;
      line-height: 1.32;
      color: var(--navy);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden
    }

    .card-title:hover {
      color: var(--blue)
    }

    .card-sub {
      font-size: .76rem;
      color: var(--muted);
      margin-top: 3px
    }

    .card-body {
      padding: 12px 14px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      flex: 1
    }

    .chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px
    }

    .chip {
      font-size: .7rem;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 20px;
      background: #EEF3F9;
      color: #3D5A80;
      letter-spacing: .2px
    }

    .chip.rx {
      background: #FFF4E5;
      color: #8A5300
    }

    .chip.otc {
      background: #E8F5E9;
      color: #1B5E20
    }

    .chip.in {
      background: #E8F5E9;
      color: #1B5E20
    }

    .chip.low {
      background: #FFF8E1;
      color: #8A6100
    }

    .chip.out {
      background: #FDECEA;
      color: #8C1D18
    }

    .chip.na {
      background: #EEF1F5;
      color: #5A6B80
    }

    .chip.tabs {
      background: #EDE9FE;
      color: #5B21B6
    }

    .desc {
      font-size: .79rem;
      color: var(--muted);
      display: -webkit-box;
      -webkit-line-clamp: 3;
      line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden
    }

    .card-foot {
      padding: 12px 14px;
      border-top: 1px solid var(--line);
      background: #FBFDFF;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: space-between
    }

    .card-foot>.actions {
      display: flex;
      gap: 8px;
      align-items: center;
      flex: 0 1 auto;
      flex-wrap: wrap
    }

    .price {
      font-weight: 800;
      color: var(--navy);
      font-size: 1rem;
      white-space: nowrap;
      flex: 0 0 auto
    }

    .price small {
      display: block;
      font-weight: 500;
      font-size: .68rem;
      color: var(--muted)
    }

    .qtybox {
      display: flex;
      align-items: center;
      border: 1px solid var(--line);
      border-radius: 9px;
      overflow: hidden;
      background: #fff
    }

    .qtybox button {
      border: 0;
      background: #F1F5F9;
      width: 28px;
      height: 30px;
      cursor: pointer;
      font-weight: 700;
      color: var(--navy)
    }

    .qtybox button:hover {
      background: #E2E8F0
    }

    .qtybox input {
      width: 40px;
      height: 30px;
      border: 0;
      text-align: center;
      font-weight: 700;
      font-size: .85rem;
      outline: none;
      -moz-appearance: textfield;
      appearance: textfield
    }

    .qtybox input::-webkit-outer-spin-button,
    .qtybox input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0
    }

    .desc-toggle {
      border: 0;
      background: none;
      color: var(--blue);
      cursor: pointer;
      font-size: .78rem;
      font-weight: 700;
      padding: 6px 0;
      text-align: left;
      display: flex;
      align-items: center;
      gap: 4px
    }

    .desc-toggle .arr {
      transition: .2s;
      display: inline-block
    }

    .desc-toggle.open .arr {
      transform: rotate(90deg)
    }

    .desc-panel {
      display: none;
      padding: 12px 14px;
      border-top: 1px solid var(--line);
      background: #F8FBFF;
      font-size: .82rem;
      color: #334155;
      line-height: 1.6
    }

    .desc-panel.open {
      display: block
    }

    .desc-panel .kv {
      display: grid;
      grid-template-columns: 110px 1fr;
      gap: 6px 12px;
      margin-bottom: 10px
    }

    .desc-panel .kv dt {
      color: var(--muted);
      font-weight: 600;
      font-size: .78rem
    }

    .desc-panel .kv dd {
      font-size: .82rem
    }

    .desc-panel .note {
      background: #FFF8E1;
      border: 1px solid #FFE082;
      color: #7A5A00;
      border-radius: 8px;
      padding: 8px 10px;
      font-size: .75rem;
      margin-top: 8px
    }

    /* table */
    .tablewrap {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: auto
    }

    table.med {
      width: 100%;
      border-collapse: collapse;
      font-size: .86rem;
      min-width: 1000px
    }

    table.med thead th {
      background: #F1F6FC;
      color: var(--navy);
      text-align: left;
      padding: 12px 14px;
      font-size: .74rem;
      text-transform: uppercase;
      letter-spacing: .6px;
      font-weight: 800;
      border-bottom: 1px solid var(--line);
      white-space: nowrap
    }

    table.med td {
      padding: 12px 14px;
      border-bottom: 1px solid var(--line);
      vertical-align: middle
    }

    table.med tbody tr:hover {
      background: #FAFCFF
    }

    table.med .nm {
      font-weight: 700;
      color: var(--navy)
    }

    table.med .nm:hover {
      color: var(--blue)
    }

    .muted {
      color: var(--muted)
    }

    .rowdesc {
      display: none
    }

    .rowdesc td {
      background: #F8FBFF;
      padding: 0
    }

    .rowdesc .inner {
      padding: 14px 18px;
      font-size: .84rem;
      line-height: 1.6;
      border-bottom: 1px solid var(--line)
    }

    .rowdesc .inner .kv {
      display: grid;
      grid-template-columns: 120px 1fr;
      gap: 6px 14px
    }

    .rowdesc .inner .kv dt {
      color: var(--muted);
      font-weight: 600;
      font-size: .78rem
    }

    .rowdesc .inner .note {
      background: #FFF8E1;
      border: 1px solid #FFE082;
      color: #7A5A00;
      border-radius: 8px;
      padding: 8px 10px;
      font-size: .75rem;
      margin-top: 10px
    }

    /* pagination */
    .pager {
      display: flex;
      gap: 6px;
      justify-content: center;
      flex-wrap: wrap;
      margin-top: 26px
    }

    .pager a,
    .pager span {
      min-width: 38px;
      height: 38px;
      padding: 0 12px;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #fff;
      border: 1px solid var(--line);
      font-size: .86rem;
      font-weight: 600;
      color: var(--navy)
    }

    .pager a:hover {
      border-color: var(--blue);
      color: var(--blue)
    }

    .pager .on {
      background: var(--blue);
      border-color: var(--blue);
      color: #fff
    }

    .pager .gap {
      border: 0;
      background: none
    }

    .empty {
      background: #fff;
      border: 1px dashed var(--line);
      border-radius: var(--radius);
      padding: 56px 24px;
      text-align: center;
      color: var(--muted)
    }

    .empty .big {
      font-size: 44px;
      margin-bottom: 10px
    }

    /* detail */
    .detail {
      display: grid;
      gap: 20px;
      grid-template-columns: minmax(0, 1fr) 340px
    }

    @media(max-width:900px) {
      .detail {
        grid-template-columns: 1fr
      }
    }

    .buybox {
      position: sticky;
      top: 88px
    }

    .buybox .head {
      display: flex;
      gap: 14px;
      align-items: flex-start;
      margin-bottom: 14px
    }

    .buybox h1 {
      font-size: 1.2rem;
      line-height: 1.3;
      color: var(--navy)
    }

    .buybox .big-price {
      font-size: 1.7rem;
      font-weight: 800;
      color: var(--navy);
      margin: 8px 0
    }

    .buybox .note {
      font-size: .78rem;
      color: var(--muted)
    }

    .buybox hr {
      border: 0;
      border-top: 1px solid var(--line);
      margin: 16px 0
    }

    .buybox .row {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-bottom: 12px
    }

    .buybox .btn {
      flex: 1
    }

    .notice {
      background: #FFF8E1;
      border: 1px solid #FFE082;
      color: #7A5A00;
      border-radius: 10px;
      padding: 10px 12px;
      font-size: .79rem;
      line-height: 1.45
    }

    .notice.blue {
      background: #E9F2FD;
      border-color: #BBD9F7;
      color: #14497F
    }

    .notice.red {
      background: #FDECEA;
      border-color: #F5C6C2;
      color: #8C1D18
    }

    .notice.green {
      background: #E8F5E9;
      border-color: #C8E6C9;
      color: #1B5E20
    }

    .notice.purple {
      background: #EDE9FE;
      border-color: #C4B5FD;
      color: #5B21B6
    }

    /* drawer */
    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(11, 31, 58, .5);
      opacity: 0;
      visibility: hidden;
      transition: .25s;
      z-index: 90
    }

    .overlay.open {
      opacity: 1;
      visibility: visible
    }

    .drawer {
      position: fixed;
      top: 0;
      right: 0;
      height: 100%;
      width: min(420px, 100%);
      background: #fff;
      z-index: 100;
      display: flex;
      flex-direction: column;
      transform: translateX(102%);
      transition: transform .28s cubic-bezier(.4, 0, .2, 1);
      box-shadow: -12px 0 40px rgba(11, 31, 58, .24)
    }

    .drawer.open {
      transform: translateX(0)
    }

    .drawer-head {
      padding: 18px;
      border-bottom: 1px solid var(--line);
      display: flex;
      align-items: center;
      justify-content: space-between
    }

    .drawer-head h3 {
      font-size: 1.02rem;
      color: var(--navy)
    }

    .xbtn {
      border: 0;
      background: #F1F5F9;
      width: 34px;
      height: 34px;
      border-radius: 10px;
      cursor: pointer;
      font-size: 16px;
      color: var(--navy)
    }

    .xbtn:hover {
      background: #E2E8F0
    }

    .drawer-body {
      flex: 1;
      overflow: auto;
      padding: 14px 18px
    }

    .drawer-foot {
      border-top: 1px solid var(--line);
      padding: 16px 18px;
      background: #FBFDFF
    }

    .citem {
      display: flex;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid var(--line)
    }

    .citem:last-child {
      border-bottom: 0
    }

    .citem .nm {
      font-weight: 700;
      font-size: .88rem;
      color: var(--navy);
      line-height: 1.3
    }

    .citem .meta {
      font-size: .75rem;
      color: var(--muted);
      margin-top: 2px
    }

    .citem .r {
      margin-left: auto;
      text-align: right;
      display: flex;
      flex-direction: column;
      gap: 6px;
      align-items: flex-end
    }

    .citem .rm {
      background: none;
      border: 0;
      color: var(--danger);
      cursor: pointer;
      font-size: .75rem;
      font-weight: 600
    }

    .totrow {
      display: flex;
      justify-content: space-between;
      font-size: .88rem;
      margin-bottom: 7px;
      color: var(--muted)
    }

    .totrow.grand {
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--navy);
      margin: 12px 0 14px
    }

    /* toast */
    .toast {
      position: fixed;
      left: 50%;
      bottom: 26px;
      transform: translate(-50%, 140%);
      background: var(--navy);
      color: #fff;
      padding: 12px 20px;
      border-radius: 12px;
      font-size: .88rem;
      font-weight: 600;
      z-index: 200;
      transition: .3s;
      box-shadow: 0 12px 30px rgba(11, 31, 58, .3);
      max-width: 90vw;
      text-align: center
    }

    .toast.show {
      transform: translate(-50%, 0)
    }

    .toast.err {
      background: var(--danger)
    }

    /* footer */
    .foot {
      text-align: center;
      color: var(--muted);
      font-size: .78rem;
      padding: 26px 18px;
      border-top: 1px solid var(--line);
      margin-top: 40px
    }

    /* checkout */
    .grid2 {
      display: grid;
      gap: 14px;
      grid-template-columns: 1fr 1fr
    }

    @media(max-width:640px) {
      .grid2 {
        grid-template-columns: 1fr
      }
    }

    .field.full {
      grid-column: 1/-1
    }

    .geo-status {
      font-size: .82rem;
      color: var(--muted);
      margin-top: 8px
    }

    .geo-status.ok {
      color: var(--success);
      font-weight: 600
    }

    .geo-status.err {
      color: var(--danger);
      font-weight: 600
    }

    .line {
      display: flex;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid var(--line)
    }

    .line:last-child {
      border-bottom: 0
    }

    .line .nm {
      font-weight: 700;
      color: var(--navy);
      font-size: .9rem
    }

    .line .uses {
      font-size: .78rem;
      color: var(--muted);
      margin-top: 3px;
      line-height: 1.45
    }

    .line .r {
      margin-left: auto;
      text-align: right;
      font-weight: 700;
      color: var(--navy);
      white-space: nowrap
    }

    .ok-hero {
      text-align: center;
      padding: 14px 4px 6px
    }

    .ok-hero .tick {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: #E8F5E9;
      color: var(--success);
      display: grid;
      place-items: center;
      font-size: 32px;
      margin: 0 auto 12px
    }

    .ok-hero h1 {
      font-size: 1.28rem;
      color: var(--navy);
      margin-bottom: 6px
    }

    .ok-hero .code {
      font-family: ui-monospace, Consolas, monospace;
      background: #EEF3F9;
      display: inline-block;
      padding: 6px 14px;
      border-radius: 8px;
      font-weight: 800;
      color: var(--navy);
      letter-spacing: 1px;
      margin: 8px 0
    }

    .steps {
      display: grid;
      gap: 10px;
      margin-top: 16px;
      text-align: left
    }

    .step {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      background: #F8FBFF;
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 12px 14px;
      font-size: .85rem
    }

    .step .n {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: var(--blue);
      color: #fff;
      display: grid;
      place-items: center;
      font-size: .74rem;
      font-weight: 800;
      flex: 0 0 auto
    }

    /* order status */
    .badge {
      display: inline-block;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 800;
      font-size: .86rem
    }

    .tabscount {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #EDE9FE;
      color: #5B21B6;
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 700;
      font-size: .82rem;
      margin-top: 10px
    }

    /* pharmacist */
    .tabs {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 18px
    }

    .tabs a {
      padding: 9px 16px;
      border-radius: 10px;
      background: #fff;
      border: 1px solid var(--line);
      font-weight: 600;
      font-size: .85rem;
      color: var(--navy);
      display: inline-flex;
      align-items: center;
      gap: 6px
    }

    .tabs a.on {
      background: var(--navy);
      color: #fff;
      border-color: var(--navy)
    }

    .tabs a .c {
      background: rgba(0, 0, 0, .08);
      padding: 1px 8px;
      border-radius: 20px;
      font-size: .74rem
    }

    .tabs a.on .c {
      background: rgba(255, 255, 255, .2)
    }

    .order {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      margin-bottom: 16px;
      overflow: hidden
    }

    .order-head {
      display: flex;
      gap: 14px;
      align-items: center;
      flex-wrap: wrap;
      padding: 14px 18px;
      border-bottom: 1px solid var(--line);
      background: #FBFDFF
    }

    .order-head .code {
      font-family: ui-monospace, Consolas, monospace;
      font-weight: 800;
      color: var(--navy);
      letter-spacing: .5px
    }

    .order-head .when {
      font-size: .76rem;
      color: var(--muted)
    }

    .order-head .tabcount {
      background: #EDE9FE;
      color: #5B21B6;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: .76rem;
      font-weight: 800
    }

    .st {
      font-size: .72rem;
      font-weight: 800;
      padding: 4px 12px;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: .6px
    }

    .st.pending {
      background: #FFF8E1;
      color: #8A6100
    }

    .st.verified {
      background: #E8F5E9;
      color: #1B5E20
    }

    .st.rejected {
      background: #FDECEA;
      color: #8C1D18
    }

    .st.dispatched {
      background: #E9F2FD;
      color: #14497F
    }

    .st.completed {
      background: #EEF1F5;
      color: #334155
    }

    .order-body {
      display: grid;
      grid-template-columns: 1.15fr .85fr;
      gap: 0
    }

    @media(max-width:900px) {
      .order-body {
        grid-template-columns: 1fr
      }
    }

    .col {
      padding: 16px 18px
    }

    .col+.col {
      border-left: 1px solid var(--line)
    }

    @media(max-width:900px) {
      .col+.col {
        border-left: 0;
        border-top: 1px solid var(--line)
      }
    }

    .col h3 {
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .7px;
      color: var(--muted);
      font-weight: 800;
      margin-bottom: 10px
    }

    .itm {
      padding: 9px 0;
      border-bottom: 1px dashed var(--line);
      display: flex;
      gap: 10px;
      align-items: flex-start
    }

    .itm:last-child {
      border-bottom: 0
    }

    .itm .tq {
      background: #EDE9FE;
      color: #5B21B6;
      font-weight: 800;
      font-size: .74rem;
      padding: 3px 9px;
      border-radius: 6px;
      flex: 0 0 auto;
      margin-top: 2px
    }

    .itm .nm {
      font-weight: 700;
      color: var(--navy);
      font-size: .88rem
    }

    .itm .uses {
      font-size: .76rem;
      color: var(--muted);
      margin-top: 2px;
      line-height: 1.45
    }

    .itm .qty {
      font-size: .76rem;
      color: var(--muted);
      margin-top: 2px
    }

    .rx-thumb {
      display: block;
      max-width: 100%;
      max-height: 220px;
      border-radius: 10px;
      border: 1px solid var(--line);
      margin-top: 8px
    }

    .acts {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      padding: 14px 18px;
      border-top: 1px solid var(--line);
      background: #FBFDFF
    }

    .flash {
      padding: 12px 16px;
      border-radius: 10px;
      margin-bottom: 16px;
      font-size: .88rem;
      font-weight: 600
    }

    .flash.ok {
      background: #E8F5E9;
      border: 1px solid #C8E6C9;
      color: #1B5E20
    }

    .flash.err {
      background: #FDECEA;
      border: 1px solid #F5C6C2;
      color: #8C1D18
    }

    .login {
      max-width: 380px;
      margin: 70px auto;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 26px
    }

    .login h1 {
      font-size: 1.1rem;
      color: var(--navy);
      margin-bottom: 6px
    }

    .login p {
      font-size: .82rem;
      color: var(--muted);
      margin-bottom: 18px
    }

    .fld {
      margin-bottom: 12px
    }

    .fld label {
      display: block;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .5px;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 5px
    }

    .fld input {
      width: 100%;
      padding: 11px 13px;
      border-radius: 10px;
      border: 1px solid var(--line)
    }

    /* ================= INVOICE ================= */
    .invoice-wrap {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 14px;
      box-shadow: var(--shadow);
      max-width: 900px;
      margin: 0 auto;
      padding: 32px;
      position: relative
    }

    .inv-head {
      display: flex;
      justify-content: space-between;
      gap: 24px;
      flex-wrap: wrap;
      border-bottom: 2px solid var(--navy);
      padding-bottom: 16px;
      margin-bottom: 20px
    }

    .inv-brand {
      display: flex;
      gap: 14px;
      align-items: flex-start
    }

    .inv-brand .logo {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--blue), var(--teal));
      display: grid;
      place-items: center;
      font-size: 26px;
      flex: 0 0 auto
    }

    .inv-brand h1 {
      font-size: 1.35rem;
      color: var(--navy);
      letter-spacing: .3px;
      line-height: 1.2
    }

    .inv-brand .tag {
      font-size: .78rem;
      color: var(--muted);
      margin-top: 2px
    }

    .inv-brand .addr {
      font-size: .78rem;
      color: #334155;
      margin-top: 6px;
      line-height: 1.5;
      max-width: 340px
    }

    .inv-meta {
      text-align: right;
      font-size: .82rem
    }

    .inv-meta .label {
      font-size: .68rem;
      text-transform: uppercase;
      letter-spacing: .8px;
      color: var(--muted);
      font-weight: 800
    }

    .inv-meta .code {
      font-family: ui-monospace, Consolas, monospace;
      font-weight: 800;
      font-size: 1.05rem;
      color: var(--navy);
      letter-spacing: 1px;
      margin: 3px 0 8px
    }

    .inv-stamp {
      display: inline-block;
      padding: 6px 14px;
      border-radius: 20px;
      font-weight: 900;
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      border: 2px solid
    }

    .inv-stamp.pending {
      color: #8A6100;
      border-color: #F9A825;
      background: #FFF8E1
    }

    .inv-stamp.verified {
      color: #1B5E20;
      border-color: #2E7D32;
      background: #E8F5E9
    }

    .inv-stamp.rejected {
      color: #8C1D18;
      border-color: #D32F2F;
      background: #FDECEA
    }

    .inv-stamp.dispatched {
      color: #14497F;
      border-color: #1976D2;
      background: #E9F2FD
    }

    .inv-stamp.completed {
      color: #334155;
      border-color: #94A3B8;
      background: #F1F5F9
    }

    .inv-two {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px
    }

    @media(max-width:640px) {
      .inv-two {
        grid-template-columns: 1fr
      }
    }

    .inv-box {
      background: #F8FBFF;
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 12px 14px
    }

    .inv-box h3 {
      font-size: .68rem;
      text-transform: uppercase;
      letter-spacing: .8px;
      color: var(--muted);
      font-weight: 800;
      margin-bottom: 6px
    }

    .inv-box .line1 {
      font-size: .88rem;
      line-height: 1.5
    }

    .inv-box .line1 b {
      color: var(--navy)
    }

    .inv-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .86rem;
      margin: 8px 0 16px
    }

    .inv-table thead th {
      background: var(--navy);
      color: #fff;
      text-align: left;
      padding: 10px 12px;
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .6px;
      font-weight: 800
    }

    .inv-table td {
      padding: 10px 12px;
      border-bottom: 1px solid var(--line);
      vertical-align: top
    }

    .inv-table td.num,
    .inv-table th.num {
      text-align: right;
      white-space: nowrap
    }

    .inv-table td.ctr,
    .inv-table th.ctr {
      text-align: center;
      white-space: nowrap
    }

    .inv-table td.uses {
      font-size: .76rem;
      color: var(--muted);
      line-height: 1.4;
      max-width: 280px
    }

    .inv-table .rxpill {
      display: inline-block;
      font-size: .62rem;
      font-weight: 800;
      background: #FFF4E5;
      color: #8A5300;
      padding: 1px 6px;
      border-radius: 10px;
      margin-left: 6px
    }

    .inv-table .qtypill {
      display: inline-block;
      font-size: .78rem;
      font-weight: 900;
      background: #EDE9FE;
      color: #5B21B6;
      padding: 2px 9px;
      border-radius: 8px
    }

    .inv-tot {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 20px;
      align-items: start
    }

    @media(max-width:700px) {
      .inv-tot {
        grid-template-columns: 1fr
      }
    }

    .inv-tot .summary {
      background: #F8FBFF;
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 12px 14px
    }

    .inv-tot .summary .row {
      display: flex;
      justify-content: space-between;
      font-size: .85rem;
      padding: 5px 0;
      color: var(--muted)
    }

    .inv-tot .summary .row b {
      color: var(--navy)
    }

    .inv-tot .summary .row.grand {
      border-top: 2px solid var(--navy);
      margin-top: 6px;
      padding-top: 9px;
      font-size: 1.05rem;
      font-weight: 900;
      color: var(--navy)
    }

    .inv-tot .summary .tabsrow {
      background: #EDE9FE;
      color: #5B21B6;
      font-weight: 900;
      padding: 6px 10px;
      border-radius: 8px;
      display: flex;
      justify-content: space-between;
      font-size: .85rem;
      margin-top: 6px
    }

    .inv-footer {
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px dashed var(--line);
      font-size: .78rem;
      color: var(--muted);
      line-height: 1.6
    }

    .inv-footer .sign {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-top: 18px;
      text-align: center
    }

    .inv-footer .sign .sig-box {
      border-top: 1px solid #94A3B8;
      padding-top: 6px;
      font-size: .74rem
    }

    .inv-delivery {
      background: #EDE9FE;
      border: 1px solid #C4B5FD;
      border-radius: 10px;
      padding: 12px 14px;
      font-size: .82rem;
      color: #5B21B6;
      margin-top: 10px
    }

    .inv-delivery b {
      color: #4C1D95
    }

    .inv-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: center;
      margin: 22px auto 0
    }

    .inv-barcode {
      font-family: ui-monospace, Consolas, monospace;
      letter-spacing: 2px;
      font-size: .7rem;
      color: var(--muted);
      text-align: center;
      margin-top: 6px
    }

    @media print {
      body {
        background: #fff
      }

      .topbar,
      .no-print,
      .foot {
        display: none !important
      }

      .wrap-mid {
        max-width: 100%;
        padding: 0
      }

      .invoice-wrap {
        border: 0;
        box-shadow: none;
        padding: 0;
        max-width: 100%
      }

      .inv-table thead th {
        background: #0B1F3A !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        color: #fff !important
      }

      .inv-tot .summary .tabsrow {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact
      }

      .inv-stamp {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact
      }

      a {
        color: inherit !important;
        text-decoration: none !important
      }
    }

    /* misc */
    .kv {
      font-size: .85rem;
      margin-bottom: 6px
    }

    .kv b {
      color: var(--navy)
    }

    .summary-box {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 10px;
      margin-top: 14px
    }

    .summary-box .item {
      background: #F8FBFF;
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 10px 14px;
      text-align: center
    }

    .summary-box .item .k {
      font-size: .68rem;
      text-transform: uppercase;
      letter-spacing: .6px;
      color: var(--muted);
      font-weight: 800
    }

    .summary-box .item .v {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--navy);
      margin-top: 2px
    }

    .summary-box .item.tabs .v {
      color: #5B21B6
    }
  </style>
</head>

<body>
  <!-- KSR brand bar (shared) -->
  <link rel="stylesheet" href="../partials/brandbar.css">
  <link rel="stylesheet" href="../assist-style.css">
  <?php $ksr_active = 'medicines';
  $ksr_depth = 1;
  include __DIR__ . '/../partials/brandbar.php'; ?>






  <header class="topbar">
    <div class="topbar-in">
      <?php if ($route === 'home'): ?>
        <a href="<?= PAGE ?>" class="brand">
          <span class="logo">💊</span>
          <span>Doctor Assist<small>KSR GURU JI Pharmacy</small></span>
        </a>
        <div class="searchwrap">
          <span class="ico">🔎</span>
          <input type="text" id="searchInput" autocomplete="off"
            placeholder="Search medicine, tablet, brand, salt or composition…"
            value="<?= e($q) ?>">
          <button class="clr" id="clearSearch" title="Clear">✕</button>
          <div class="suggest" id="suggestBox"></div>
        </div>
        <a class="rxlink" href="../build-prescription.php">🧾 Prescription Builder</a>
        <a class="rxlink ghost" href="<?= PAGE ?>?page=order-status">📦 Track Order</a>
        <button class="cartbtn" id="openCart">
          🛒 Cart <span class="badge" id="cartCount"><?= (int)$cartCount ?></span>
        </button>

      <?php elseif ($route === 'checkout'): ?>
        <a class="brand" href="<?= PAGE ?>">
          <span class="logo">💊</span>
          <span>Doctor Assist<small>KSR GURU JI Pharmacy</small></span>
        </a>
        <a class="btn btn-ghost" style="margin-left:auto" href="<?= PAGE ?>">← Back to medicines</a>

      <?php elseif ($route === 'order-status'): ?>
        <a class="brand" href="<?= PAGE ?>">
          <span class="logo">💊</span>
          <span>Doctor Assist<small>KSR GURU JI Pharmacy</small></span>
        </a>
        <a class="btn btn-ghost" style="margin-left:auto" href="<?= PAGE ?>">← Shop</a>

      <?php elseif ($route === 'invoice'): ?>
        <a class="brand" href="<?= PAGE ?>">
          <span class="logo">💊</span>
          <span>Invoice<small>KSR GURU JI Pharmacy</small></span>
        </a>
        <a class="btn btn-ghost no-print" style="margin-left:auto" href="<?= PAGE ?>">← Shop</a>

      <?php elseif ($route === 'pharmacist'): ?>
        <span class="brand">
          <span class="logo">🩺</span>
          <span>Pharmacist Console<small>KSR GURU JI Pharmacy · Owner</small></span>
        </span>
        <?php if ($pharmLoggedIn): ?>
          <span class="pill" style="margin-left:auto"><span class="dot"></span>
            <span id="pendCount"><?= (int)($pharmCounts['pending'] ?? 0) ?></span> pending
          </span>
          <a class="btn btn-line" style="background:rgba(255,255,255,.14);color:#fff;border-color:transparent"
            href="<?= PAGE ?>?page=pharmacist&action2=logout">Logout</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </header>

  <?php /* ============================================================
   ROUTE: CHECKOUT
   ============================================================ */
  if ($route === 'checkout'): ?>

    <div class="wrap-narrow">
      <?php if ($placed): ?>

        <nav class="crumbs"><a href="<?= PAGE ?>">Pharmacy</a> › Checkout › Confirmation</nav>

        <div class="panel">
          <div class="ok-hero">
            <div class="tick">✓</div>
            <h1>Order placed</h1>
            <div class="code"><?= e($placed['code']) ?></div>
            <p class="muted" style="font-size:.88rem">
              Send the order on WhatsApp so the pharmacist can verify your prescription.
            </p>
            <div class="tabscount">💊 Total tablets ordered: <?= (int)$placed['count'] ?></div>
          </div>

          <?php if ($waLink): ?>
            <a class="btn btn-wa btn-block" style="margin-top:16px;padding:15px"
              href="<?= e($waLink) ?>" target="_blank" rel="noopener">
              📲 Send order to medical shop (<?= e(SHOP_PHONE) ?>)
            </a>
            <p class="muted" style="font-size:.78rem;text-align:center;margin-top:8px">
              Opens WhatsApp with full order (names, uses, tablet count, price, location).
            </p>
          <?php endif; ?>

          <?php if (!empty($deliveryWaLink)): ?>
            <a class="btn btn-purple btn-block" style="margin-top:10px;padding:14px"
              href="<?= e($deliveryWaLink) ?>" target="_blank" rel="noopener">
              🚚 Share order list with delivery boy JK (<?= e(DELIVERY_PHONE) ?>)
            </a>
          <?php endif; ?>

          <button type="button" class="btn btn-teal btn-block" style="margin-top:10px"
            onclick="copyTabletOrderSummary('<?= e($placed['code']) ?>', '<?= number_format((float)$placed['total'], 2) ?>', '<?= (int)$placed['count'] ?>', '<?= e(invoiceUrl($placed['code'])) ?>')">
            📋 Copy Order &amp; Invoice Details
          </button>

          <a class="btn btn-line btn-block" style="margin-top:10px"
            href="<?= PAGE ?>?page=order-status&code=<?= urlencode($placed['code']) ?>">
            🔎 Track this order
          </a>

          <a class="btn btn-line btn-block" style="margin-top:8px"
            href="<?= PAGE ?>?page=invoice&code=<?= urlencode($placed['code']) ?>">
            🧾 View / print invoice
          </a>

          <div style="margin-top:14px;padding:12px;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;color:#9f1239;font-size:.82rem;text-align:center;font-weight:600">
            ⚠️ Note: Price vary please customer will support this line.
          </div>

          <div class="steps">
            <div class="step"><span class="n">1</span>
              <div><strong>Order placed</strong><br>Status: <em>Pending</em>.</div>
            </div>
            <div class="step"><span class="n">2</span>
              <div><strong>Owner verifies</strong><br>Medical shop owner verifies or rejects your prescription.</div>
            </div>
            <div class="step"><span class="n">3</span>
              <div><strong>JK delivers</strong><br>Our delivery boy <strong>JK (8088584421)</strong> is notified and brings your order.</div>
            </div>
          </div>
        </div>

      <?php else: ?>

        <nav class="crumbs"><a href="<?= PAGE ?>">Pharmacy</a> › Checkout</nav>

        <?php if ($errors): ?>
          <div class="notice red">
            <strong>Please fix the following:</strong>
            <ul style="margin:6px 0 0 18px">
              <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!$cart['rows']): ?>
          <div class="panel" style="text-align:center;padding:48px 20px">
            <div style="font-size:44px">🛒</div>
            <h2 style="margin-top:10px;text-transform:none;letter-spacing:0;color:var(--navy);font-size:1.05rem">
              Your cart is empty
            </h2>
            <p class="muted" style="font-size:.86rem;margin:6px 0 16px">Add medicines before checking out.</p>
            <a class="btn btn-primary" href="<?= PAGE ?>">Browse medicines</a>
          </div>
        <?php else: ?>

          <div class="panel">
            <h2>Order summary · <?= (int)$cart['count'] ?> tablet<?= $cart['count'] === 1 ? '' : 's' ?></h2>

            <div class="summary-box">
              <div class="item">
                <div class="k">Items</div>
                <div class="v"><?= count($cart['rows']) ?></div>
              </div>
              <div class="item tabs">
                <div class="k">Total tablets</div>
                <div class="v"><?= (int)$cart['count'] ?></div>
              </div>
              <div class="item">
                <div class="k">Subtotal</div>
                <div class="v"><?= money($cart['subtotal']) ?></div>
              </div>
              <div class="item">
                <div class="k">Grand total</div>
                <div class="v"><?= money($cart['subtotal'] + DELIVERY_FEE) ?></div>
              </div>
            </div>

            <div style="margin-top:18px">
              <?php foreach ($cart['rows'] as $r): ?>
                <div class="line">
                  <div style="min-width:0">
                    <div class="nm"><?= e($r['name']) ?><?= $r['strength'] !== '' ? ' · ' . e($r['strength']) : '' ?></div>
                    <div class="uses"><strong>Uses:</strong> <?= e($r['uses']) ?></div>
                    <div class="uses">
                      Qty <?= (int)$r['qty'] ?> tablet(s)
                      <?= $r['pack'] !== '' ? ' · pack of ' . e($r['pack']) : '' ?>
                      <?= $r['rx'] ? ' · <span style="color:#8A5300;font-weight:700">Rx required</span>' : '' ?>
                    </div>
                  </div>
                  <div class="r"><?= money($r['line']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if ($cart['needs_rx']): ?>
            <div class="notice blue">
              <strong>Prescription required.</strong>
              One or more items are Rx-only. Please attach a clear photo or PDF — the order stays
              <em>Pending</em> until the owner verifies it.
            </div>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data" class="panel"
            action="<?= PAGE ?>?page=checkout" id="checkoutForm">
            <h2>Delivery details</h2>
            <input type="hidden" name="do" value="place">
            <input type="hidden" name="lat" id="lat" value="">
            <input type="hidden" name="lng" id="lng" value="">
            <input type="hidden" name="acc" id="acc" value="">

            <div class="grid2">
              <div class="field">
                <label for="name">Full name *</label>
                <input type="text" id="name" name="name" required minlength="2"
                  value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Ramesh Kumar">
              </div>
              <div class="field">
                <label for="phone">Mobile number *</label>
                <input type="tel" id="phone" name="phone" required inputmode="numeric"
                  pattern="[0-9+ -]{10,15}" value="<?= e($_POST['phone'] ?? '') ?>"
                  placeholder="10-digit mobile">
              </div>

              <div class="field full">
                <label for="address">Delivery address *</label>
                <textarea id="address" name="address" required minlength="8"
                  placeholder="House / flat, street, area, landmark, city, PIN"><?= e($_POST['address'] ?? '') ?></textarea>
              </div>

              <div class="field full">
                <label>Current location (optional but recommended)</label>
                <button type="button" class="btn btn-teal btn-block" id="geoBtn">📍 Share my current location</button>
                <div class="geo-status" id="geoStatus">Location not shared yet.</div>
              </div>

              <div class="field full">
                <label for="rx">Prescription photo / PDF <?= $cart['needs_rx'] ? '*' : '(optional)' ?></label>
                <input type="file" id="rx" name="rx" accept="image/*,application/pdf"
                  <?= $cart['needs_rx'] ? 'required' : '' ?>>
              </div>

              <div class="field full">
                <label for="note">Note for the pharmacist (optional)</label>
                <input type="text" id="note" name="note" maxlength="240"
                  value="<?= e($_POST['note'] ?? '') ?>"
                  placeholder="e.g. Please call before delivery">
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:18px;padding:15px">
              ✅ Place order &amp; notify owner (<?= e(SHOP_PHONE) ?>)
            </button>
            <p class="muted" style="font-size:.76rem;text-align:center;margin-top:10px">
              After placing, you'll get a WhatsApp button to send the order.
            </p>
          </form>

        <?php endif; ?>
      <?php endif; ?>
    </div>

  <?php /* ============================================================
   ROUTE: ORDER STATUS
   ============================================================ */
  elseif ($route === 'order-status'): ?>

    <?php
    $badge = [
      'pending'    => ['#FFF8E1', '#8A6100', '⏳ Pending verification'],
      'verified'   => ['#E8F5E9', '#1B5E20', '✓ Verified — assigned to JK'],
      'rejected'   => ['#FDECEA', '#8C1D18', '✕ Rejected'],
      'dispatched' => ['#E9F2FD', '#14497F', '🚚 Out for delivery with JK'],
      'completed'  => ['#EEF1F5', '#334155', '✔ Delivered'],
    ];
    $orderTabCount = 0;
    if ($order) {
      foreach (json_decode((string)$order['items_json'], true) ?: [] as $it) {
        $orderTabCount += (int)($it['qty'] ?? 0);
      }
    }
    ?>

    <div class="wrap-mid">
      <?php if (!$order): ?>
        <div class="panel">
          <h2>Track your order</h2>
          <form method="get" action="<?= PAGE ?>">
            <input type="hidden" name="page" value="order-status">
            <input type="text" name="code" placeholder="Enter order code e.g. KSR-240615-1234"
              value="<?= e($_GET['code'] ?? '') ?>" required
              style="width:100%;padding:11px 13px;border-radius:10px;border:1px solid var(--line)">
            <button class="btn btn-primary btn-block" style="margin-top:12px">Track</button>
          </form>
        </div>
      <?php else: ?>
        <?php [$bgc, $fgc, $label] = $badge[$order['status']] ?? ['#EEF1F5', '#334155', $order['status']]; ?>

        <div class="panel" style="text-align:center">
          <div style="font-family:ui-monospace,Consolas,monospace;font-weight:800;
                letter-spacing:1px;color:var(--navy);margin-bottom:10px">
            <?= e($order['order_code']) ?>
          </div>
          <span class="badge" style="background:<?= $bgc ?>;color:<?= $fgc ?>">
            <?= e($label) ?>
          </span>
          <div class="tabscount">💊 <?= (int)$orderTabCount ?> tablet(s) ordered</div>
          <p class="muted" style="font-size:.8rem;margin-top:12px">
            Placed <?= e(date('d M Y, H:i', strtotime((string)$order['created_at']))) ?>
            · auto-refreshes every 30 s
          </p>
          <?php if ($order['status'] === 'rejected' && $order['reject_reason']): ?>
            <div class="notice red" style="margin-top:14px;text-align:left">
              <strong>Reason:</strong> <?= e($order['reject_reason']) ?>
            </div>
          <?php endif; ?>
          <?php if ($order['status'] === 'verified' || $order['status'] === 'dispatched'): ?>
            <div class="notice green" style="margin-top:14px;text-align:left">
              ✓ Verified by <strong><?= e($order['verified_by'] ?: 'owner') ?></strong>
              on <?= e(date('d M Y, H:i', strtotime((string)$order['verified_at']))) ?>.
            </div>
            <div class="notice purple" style="margin-top:8px;text-align:left">
              🚚 Delivery by <strong><?= e(DELIVERY_NAME) ?></strong> ·
              <a href="tel:<?= e(DELIVERY_PHONE) ?>" style="color:#5B21B6;font-weight:700">
                <?= e(DELIVERY_PHONE) ?>
              </a>
            </div>
          <?php endif; ?>
        </div>

        <div class="panel">
          <h2>Medicines</h2>
          <?php foreach (json_decode((string)$order['items_json'], true) ?: [] as $it): ?>
            <div class="line">
              <div style="min-width:0">
                <div class="nm"><?= e($it['name']) ?><?= !empty($it['strength']) ? ' · ' . e($it['strength']) : '' ?></div>
                <div class="uses"><strong>Uses:</strong> <?= e($it['uses'] ?? '—') ?></div>
                <div class="uses">
                  Qty <strong style="color:#5B21B6"><?= (int)$it['qty'] ?> tablet(s)</strong>
                  <?= !empty($it['pack']) ? ' · pack of ' . e($it['pack']) : '' ?>
                </div>
              </div>
              <div class="r"><?= money(isset($it['line']) ? (float)$it['line'] : null) ?></div>
            </div>
          <?php endforeach; ?>
          <div style="margin-top:14px">
            <div class="totrow"><span>Subtotal</span><span><?= money((float)$order['subtotal']) ?></span></div>
            <div class="totrow"><span>Delivery</span><span><?= money((float)$order['delivery']) ?></span></div>
            <div class="totrow grand"><span>Total</span><span><?= money((float)$order['total']) ?></span></div>
          </div>
        </div>

        <div class="panel">
          <h2>Delivery address</h2>
          <p style="font-size:.9rem">
            <strong><?= e($order['customer_name']) ?></strong><br>
            <?= e($order['customer_phone']) ?><br>
            <?= nl2br(e((string)$order['address_text'])) ?>
          </p>
          <?php if ($order['maps_url']): ?>
            <a class="btn btn-primary btn-block" style="margin-top:12px"
              href="<?= e($order['maps_url']) ?>" target="_blank" rel="noopener">📍 View shared location</a>
          <?php endif; ?>
        </div>

        <div class="panel">
          <h2>Invoice</h2>
          <p class="muted" style="font-size:.84rem">
            View or print the tax invoice for this order.
          </p>
          <a class="btn btn-teal btn-block" style="margin-top:10px" target="_blank" rel="noopener"
            href="<?= PAGE ?>?page=invoice&code=<?= urlencode((string)$order['order_code']) ?>">
            🧾 View / print invoice
          </a>
        </div>

        <div class="panel">
          <h2>Need help?</h2>
          <?php
          $help = "Hello, I'd like to ask about my order *{$order['order_code']}*.";
          $wa   = 'https://wa.me/' . SHOP_WHATSAPP . '?text=' . rawurlencode($help);
          ?>
          <a class="btn btn-wa btn-block" href="<?= e($wa) ?>" target="_blank" rel="noopener">
            💬 WhatsApp the pharmacy owner
          </a>
          <a class="btn btn-primary btn-block" style="margin-top:10px"
            href="tel:<?= e(SHOP_PHONE) ?>">📞 Call owner <?= e(SHOP_PHONE) ?></a>
          <a class="btn btn-purple btn-block" style="margin-top:10px"
            href="tel:<?= e(DELIVERY_PHONE) ?>">🚚 Call delivery boy <?= e(DELIVERY_NAME) ?> (<?= e(DELIVERY_PHONE) ?>)</a>
        </div>
      <?php endif; ?>
    </div>

  <?php /* ============================================================
   ROUTE: PHARMACIST  (owner)
   ============================================================ */
  elseif ($route === 'pharmacist'): ?>

    <?php if (!$pharmLoggedIn): ?>
      <div class="login">
        <h1>Medical shop owner login</h1>
        <p>Sign in to verify, reject, or dispatch incoming orders.</p>
        <?php if ($flash && $flash[0] === 'err'): ?>
          <div class="flash err"><?= e($flash[1]) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= PAGE ?>?page=pharmacist">
          <input type="hidden" name="do" value="login">
          <div class="fld"><label>Username</label>
            <input type="text" name="u" required autocomplete="username">
          </div>
          <div class="fld"><label>Password</label>
            <input type="password" name="p" required autocomplete="current-password">
          </div>
          <button class="btn btn-primary btn-block">Sign in</button>
        </form>
      </div>
    <?php else: ?>
      <div class="wrap">

        <?php if ($flash): ?>
          <div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
        <?php endif; ?>

        <div class="tabs">
          <?php
          $tabs = [
            'pending' => '⏳ Pending',
            'verified' => '✓ Verified',
            'dispatched' => '🚚 Dispatched',
            'completed' => '✔ Completed',
            'rejected' => '✕ Rejected',
            'all' => 'All'
          ];
          foreach ($tabs as $k => $lbl):
            $n = $k === 'all' ? array_sum($pharmCounts) : ($pharmCounts[$k] ?? 0);
          ?>
            <a href="<?= PAGE ?>?page=pharmacist&f=<?= e($k) ?>" class="<?= $pharmFilter === $k ? 'on' : '' ?>">
              <?= $lbl ?><span class="c"><?= (int)$n ?></span>
            </a>
          <?php endforeach; ?>
        </div>

        <?php if (!$pharmOrders): ?>
          <div class="empty">
            <div class="big">📭</div>
            <strong>No orders in this view.</strong>
          </div>
        <?php endif; ?>

        <?php foreach ($pharmOrders as $o): ?>
          <?php
          $items = json_decode((string)$o['items_json'], true) ?: [];
          $tabTotal = 0;
          foreach ($items as $it) $tabTotal += (int)($it['qty'] ?? 0);
          $waDelivery = deliveryWhatsAppLink($o);
          $waDeliveryRejected = 'https://wa.me/' . DELIVERY_WHATSAPP
            . '?text=' . rawurlencode("❌ Order {$o['order_code']} was rejected by the owner. Cancel delivery.");
          ?>
          <article class="order">
            <div class="order-head">
              <span class="code"><?= e($o['order_code']) ?></span>
              <span class="st <?= e($o['status']) ?>"><?= e($o['status']) ?></span>
              <span class="tabcount">💊 <?= (int)$tabTotal ?> tablet(s)</span>
              <?php if ((int)$o['needs_rx'] === 1): ?>
                <span class="st pending">Rx required</span>
              <?php endif; ?>
              <span class="when" style="margin-left:auto">
                <?= e(date('d M Y · H:i', strtotime((string)$o['created_at']))) ?>
              </span>
            </div>

            <div class="order-body">
              <div class="col">
                <h3>Medicines &amp; uses</h3>
                <?php foreach ($items as $it): ?>
                  <div class="itm">
                    <span class="tq"><?= (int)$it['qty'] ?>💊</span>
                    <div style="flex:1;min-width:0">
                      <div class="nm">
                        <?= e($it['name']) ?><?= !empty($it['strength']) ? ' · ' . e($it['strength']) : '' ?>
                      </div>
                      <div class="uses"><strong>Uses:</strong> <?= e($it['uses'] ?? '—') ?></div>
                      <div class="qty">
                        <?= !empty($it['pack']) ? 'Pack of ' . e($it['pack']) . ' · ' : '' ?>
                        Line: <?= money(isset($it['line']) ? (float)$it['line'] : null) ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
                <div class="summary-box" style="margin-top:14px">
                  <div class="item tabs">
                    <div class="k">Total tablets</div>
                    <div class="v"><?= (int)$tabTotal ?></div>
                  </div>
                  <div class="item">
                    <div class="k">Subtotal</div>
                    <div class="v"><?= money((float)$o['subtotal']) ?></div>
                  </div>
                  <div class="item">
                    <div class="k">Grand total</div>
                    <div class="v"><?= money((float)$o['total']) ?></div>
                  </div>
                </div>
              </div>

              <div class="col">
                <h3>Customer</h3>
                <div class="kv"><b><?= e($o['customer_name']) ?></b></div>
                <div class="kv">📞 <?= e($o['customer_phone']) ?></div>
                <div class="kv">🏠 <?= nl2br(e((string)$o['address_text'])) ?></div>
                <?php if ($o['maps_url']): ?>
                  <a class="btn btn-line" style="margin-top:6px" target="_blank" rel="noopener"
                    href="<?= e($o['maps_url']) ?>">📍 Open live location</a>
                <?php endif; ?>
                <?php if ($o['customer_note']): ?>
                  <div class="kv" style="margin-top:10px">📝 <?= e($o['customer_note']) ?></div>
                <?php endif; ?>

                <h3 style="margin-top:16px">Prescription</h3>
                <?php if ($o['rx_file']): ?>
                  <?php $ext = strtolower(pathinfo((string)$o['rx_file'], PATHINFO_EXTENSION)); ?>
                  <?php if ($ext === 'pdf'): ?>
                    <a class="btn btn-line" href="<?= e($o['rx_file']) ?>" target="_blank" rel="noopener">
                      📄 Open prescription PDF
                    </a>
                  <?php else: ?>
                    <a href="<?= e($o['rx_file']) ?>" target="_blank" rel="noopener">
                      <img class="rx-thumb" src="<?= e($o['rx_file']) ?>" alt="Prescription">
                    </a>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="kv muted">No file attached.</div>
                <?php endif; ?>

                <?php if ($o['status'] === 'rejected' && $o['reject_reason']): ?>
                  <div class="flash err" style="margin-top:12px">
                    <strong>Rejected:</strong> <?= e($o['reject_reason']) ?>
                  </div>
                <?php endif; ?>
                <?php if ($o['verified_at']): ?>
                  <div class="kv muted" style="margin-top:10px;font-size:.78rem">
                    Actioned by <?= e($o['verified_by']) ?>
                    · <?= e(date('d M Y H:i', strtotime((string)$o['verified_at']))) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="acts">
              <?php if ($o['status'] === 'pending'): ?>
                <form method="post" action="<?= PAGE ?>?page=pharmacist" style="display:inline">
                  <input type="hidden" name="do" value="setstatus">
                  <input type="hidden" name="f" value="<?= e($pharmFilter) ?>">
                  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="status" value="verified">
                  <button class="btn btn-ok">✓ Verify &amp; notify JK</button>
                </form>
                <form method="post" action="<?= PAGE ?>?page=pharmacist"
                  style="display:inline-flex;gap:8px;flex:1;min-width:280px">
                  <input type="hidden" name="do" value="setstatus">
                  <input type="hidden" name="f" value="<?= e($pharmFilter) ?>">
                  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="status" value="rejected">
                  <input type="text" name="reason" placeholder="Reject reason (required)"
                    required maxlength="200"
                    style="flex:1;padding:10px 12px;border-radius:10px;border:1px solid var(--line)">
                  <button class="btn btn-no">✕ Reject</button>
                </form>

              <?php elseif ($o['status'] === 'verified'): ?>
                <a class="btn btn-purple" target="_blank" rel="noopener"
                  href="<?= e($waDelivery) ?>">
                  🚚 Send to delivery boy <?= e(DELIVERY_NAME) ?> (<?= e(DELIVERY_PHONE) ?>)
                </a>
                <form method="post" action="<?= PAGE ?>?page=pharmacist" style="display:inline">
                  <input type="hidden" name="do" value="setstatus">
                  <input type="hidden" name="f" value="<?= e($pharmFilter) ?>">
                  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="status" value="dispatched">
                  <button class="btn btn-primary">📦 Mark dispatched</button>
                </form>

              <?php elseif ($o['status'] === 'dispatched'): ?>
                <a class="btn btn-purple" target="_blank" rel="noopener"
                  href="<?= e($waDelivery) ?>">🚚 Resend to JK</a>
                <form method="post" action="<?= PAGE ?>?page=pharmacist" style="display:inline">
                  <input type="hidden" name="do" value="setstatus">
                  <input type="hidden" name="f" value="<?= e($pharmFilter) ?>">
                  <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="status" value="completed">
                  <button class="btn btn-ok">✔ Mark delivered</button>
                </form>

              <?php elseif ($o['status'] === 'rejected'): ?>
                <a class="btn btn-purple" target="_blank" rel="noopener"
                  href="<?= e($waDeliveryRejected) ?>">🚚 Tell JK: cancel order</a>
              <?php endif; ?>

              <?php
              $phone = preg_replace('/\D+/', '', (string)$o['customer_phone']);
              if (strlen($phone) === 10) $phone = '91' . $phone;
              if ($o['status'] === 'verified') {
                $msg = "✅ *KSR GURU JI Pharmacy*\nYour order {$o['order_code']} has been *verified* "
                  . "and assigned to our delivery boy *" . DELIVERY_NAME . "* (" . DELIVERY_PHONE . ").";
              } elseif ($o['status'] === 'rejected') {
                $msg = "❌ *KSR GURU JI Pharmacy*\nYour order {$o['order_code']} was *rejected*.\n"
                  . "Reason: " . ($o['reject_reason'] ?? '—');
              } elseif ($o['status'] === 'dispatched') {
                $msg = "🚚 *KSR GURU JI Pharmacy*\nYour order {$o['order_code']} is out for delivery with "
                  . DELIVERY_NAME . " (" . DELIVERY_PHONE . ").";
              } else {
                $msg = "Hello, about your order {$o['order_code']}…";
              }
              $custWa = 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
              ?>
              <a class="btn btn-wa" target="_blank" rel="noopener" href="<?= e($custWa) ?>">
                💬 Notify customer
              </a>
              <a class="btn btn-teal" target="_blank" rel="noopener"
                href="<?= PAGE ?>?page=invoice&code=<?= urlencode((string)$o['order_code']) ?>">
                🧾 Invoice
              </a>
              <a class="btn btn-line" href="tel:+<?= e($phone) ?>">📞 Call customer</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <script>
        setInterval(async () => {
          try {
            const r = await fetch('<?= PAGE ?>?page=pharmacist&action2=count', {
              cache: 'no-store'
            });
            const j = await r.json();
            if (j.ok) {
              const el = document.getElementById('pendCount');
              if (el && el.textContent !== String(j.pending)) {
                el.textContent = j.pending;
                const dot = document.querySelector('.pill .dot');
                if (dot) dot.style.background = j.pending > 0 ? '#F9A825' : '#2E7D32';
              }
            }
          } catch (e) {}
        }, 20000);
      </script>
    <?php endif; ?>

  <?php /* ============================================================
   ROUTE: INVOICE  (printable)
   ============================================================ */
  elseif ($route === 'invoice'): ?>

    <?php
    $tabTotal = 0;
    $items    = $order ? (json_decode((string)$order['items_json'], true) ?: []) : [];
    foreach ($items as $it) $tabTotal += (int)($it['qty'] ?? 0);

    $stampClass = 'pending';
    if ($order) {
      if ($order['status'] === 'verified')   $stampClass = 'verified';
      elseif ($order['status'] === 'rejected')   $stampClass = 'rejected';
      elseif ($order['status'] === 'dispatched') $stampClass = 'dispatched';
      elseif ($order['status'] === 'completed')  $stampClass = 'completed';
    }
    $invoiceNo = $order ? 'INV-' . str_replace('KSR-', '', (string)$order['order_code']) : '';
    ?>

    <div class="wrap-mid">

      <?php if (!$order): ?>
        <div class="panel no-print" style="text-align:center;padding:48px 20px">
          <div style="font-size:44px">🧾</div>
          <h2 style="margin-top:10px;text-transform:none;letter-spacing:0;color:var(--navy);font-size:1.05rem">
            Invoice not found
          </h2>
          <p class="muted" style="font-size:.86rem;margin:6px 0 16px">
            Enter a valid order code to view its invoice.
          </p>
          <form method="get" action="<?= PAGE ?>">
            <input type="hidden" name="page" value="invoice">
            <input type="text" name="code" placeholder="e.g. KSR-240615-1234" required
              value="<?= e($_GET['code'] ?? '') ?>"
              style="width:100%;max-width:320px;padding:11px 13px;border-radius:10px;
                      border:1px solid var(--line)">
            <button class="btn btn-primary" style="margin-top:10px">View invoice</button>
          </form>
          <a class="btn btn-line" style="margin-top:10px" href="<?= PAGE ?>">← Back to shop</a>
        </div>
      <?php else: ?>

        <div class="crumbs no-print" style="text-align:center">
          <a href="<?= PAGE ?>">Pharmacy</a><span>›</span>
          <a href="<?= PAGE ?>?page=order-status&code=<?= urlencode((string)$order['order_code']) ?>">
            Order status
          </a><span>›</span> Invoice
        </div>

        <div class="invoice-wrap" id="invoicePrint">

          <!-- HEADER -->
          <div class="inv-head">
            <div class="inv-brand">
              <div class="logo">💊</div>
              <div>
                <h1><?= e(SHOP_NAME) ?></h1>
                <div class="tag"><?= e(SHOP_TAGLINE) ?></div>
                <div class="addr">
                  <?= e(SHOP_ADDRESS) ?><br>
                  📞 <?= e(SHOP_PHONE) ?>
                  <?php if (SHOP_GSTIN !== ''): ?><br>GSTIN: <?= e(SHOP_GSTIN) ?><?php endif; ?>
                    <?php if (SHOP_EMAIL !== ''): ?><br><?= e(SHOP_EMAIL) ?><?php endif; ?>
                </div>
              </div>
            </div>

            <div class="inv-meta">
              <div class="label">Tax Invoice / Bill of Supply</div>
              <div class="code"><?= e($invoiceNo) ?></div>
              <div class="label">Order</div>
              <div style="font-family:ui-monospace,Consolas,monospace;font-weight:700;color:var(--navy);
                      margin-bottom:8px"><?= e($order['order_code']) ?></div>
              <div class="label">Date</div>
              <div style="margin-bottom:10px">
                <?= e(date('d M Y, H:i', strtotime((string)$order['created_at']))) ?>
              </div>
              <div class="inv-stamp <?= e($stampClass) ?>">
                <?= e($stampClass === 'verified' ? 'PAID & VERIFIED' : ($stampClass === 'completed' ? 'DELIVERED' :
                  strtoupper($stampClass))) ?>
              </div>
            </div>
          </div>

          <!-- BILL TO + DELIVERY -->
          <div class="inv-two">
            <div class="inv-box">
              <h3>Bill to / Customer</h3>
              <div class="line1">
                <b><?= e($order['customer_name']) ?></b><br>
                📞 <?= e($order['customer_phone']) ?><br>
                <?= nl2br(e((string)$order['address_text'])) ?>
              </div>
              <?php if (!empty($order['maps_url'])): ?>
                <div style="margin-top:8px">
                  <a class="btn btn-line btn-sm" target="_blank" rel="noopener"
                    href="<?= e($order['maps_url']) ?>">📍 Live location</a>
                </div>
              <?php endif; ?>
            </div>

            <div class="inv-box">
              <h3>Order information</h3>
              <div class="line1">
                <b>Order code:</b> <?= e($order['order_code']) ?><br>
                <b>Total tablets:</b> <?= (int)$tabTotal ?><br>
                <b>Items:</b> <?= count($items) ?><br>
                <b>Prescription:</b>
                <?= ((int)$order['needs_rx'] === 1) ? 'Rx required' : 'Not required' ?>
              </div>
              <?php if ($order['customer_note']): ?>
                <div style="margin-top:8px;font-size:.78rem;color:#334155">
                  📝 <b>Note:</b> <?= e($order['customer_note']) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- ITEMS TABLE -->
          <table class="inv-table">
            <thead>
              <tr>
                <th style="width:36px">#</th>
                <th>Medicine &amp; uses</th>
                <th class="ctr" style="width:90px">Qty (tab)</th>
                <th class="num" style="width:100px">Rate</th>
                <th class="num" style="width:110px">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $idx => $it):
                $qty   = (int)($it['qty'] ?? 0);
                $price = isset($it['price']) && $it['price'] !== null ? (float)$it['price'] : null;
                $line  = isset($it['line'])  && $it['line']  !== null ? (float)$it['line']  : null;
              ?>
                <tr>
                  <td class="ctr"><?= $idx + 1 ?></td>
                  <td>
                    <strong style="color:var(--navy)">
                      <?= e($it['name']) ?><?= !empty($it['strength']) ? ' · ' . e($it['strength']) : '' ?>
                    </strong>
                    <?php if (!empty($it['rx'])): ?>
                      <span class="rxpill">Rx</span>
                    <?php endif; ?>
                    <div class="uses">
                      <b>Uses:</b> <?= e($it['uses'] ?? '—') ?>
                      <?php if (!empty($it['pack'])): ?>
                        · Pack of <?= e($it['pack']) ?>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="ctr"><span class="qtypill"><?= $qty ?> 💊</span></td>
                  <td class="num"><?= money($price) ?></td>
                  <td class="num"><strong><?= money($line) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <!-- TOTALS -->
          <div class="inv-tot">
            <div>
              <div class="inv-box">
                <h3>Verification</h3>
                <div class="line1" style="font-size:.82rem">
                  <?php if ($order['verified_at']): ?>
                    ✓ Verified by <b><?= e($order['verified_by'] ?: 'pharmacist') ?></b><br>
                    on <?= e(date('d M Y, H:i', strtotime((string)$order['verified_at']))) ?>
                  <?php else: ?>
                    <span class="muted">Awaiting owner verification.</span>
                  <?php endif; ?>
                  <?php if ($order['status'] === 'rejected' && $order['reject_reason']): ?>
                    <div style="color:#8C1D18;margin-top:6px">
                      ✕ <b>Rejected:</b> <?= e($order['reject_reason']) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <?php if ($order['status'] === 'verified' || $order['status'] === 'dispatched' || $order['status'] === 'completed'): ?>
                <div class="inv-delivery">
                  <b>🚚 Delivery assigned to <?= e(DELIVERY_NAME) ?></b><br>
                  📞 <a href="tel:<?= e(DELIVERY_PHONE) ?>" style="color:#5B21B6;font-weight:700">
                    <?= e(DELIVERY_PHONE) ?>
                  </a>
                  &nbsp;·&nbsp;
                  <a target="_blank" rel="noopener"
                    href="https://wa.me/<?= e(DELIVERY_WHATSAPP) ?>?text=<?=
                                                                          rawurlencode('Hello, about order ' . $order['order_code']) ?>"
                    style="color:#5B21B6;font-weight:700">WhatsApp JK</a>
                </div>
              <?php endif; ?>
            </div>

            <div class="summary">
              <div class="row"><span>Subtotal</span><b><?= money((float)$order['subtotal']) ?></b></div>
              <div class="row"><span>Delivery charge</span><b><?= money((float)$order['delivery']) ?></b></div>
              <div class="row"><span>Discount</span><b>₹0.00</b></div>
              <div class="row grand"><span>Grand total</span><b><?= money((float)$order['total']) ?></b></div>
              <div class="tabsrow">
                <span>💊 Total tablets</span>
                <span><?= (int)$tabTotal ?></span>
              </div>
            </div>
          </div>

          <!-- FOOTER -->
          <div class="inv-footer">
            <div>
              <b>Terms:</b> Medicines once sold are not returnable or exchangeable.
              Prescription medicines dispensed only against a valid prescription.
              Please check batch number, expiry date and pack integrity at delivery.
              <br><strong style="color:#b91c1c;display:inline-block;margin-top:6px;">* Price vary please customer will support this line.</strong>
            </div>
            <div class="inv-barcode">
              *<?= strtoupper(substr(md5((string)$order['order_code']), 0, 20)) ?>*
            </div>
            <div class="sign">
              <div class="sig-box">Customer signature</div>
              <div class="sig-box">
                For <b><?= e(SHOP_NAME) ?></b><br>
                <?= e(OWNER_NAME) ?><br>
                <?= e(OWNER_EXP) ?>
              </div>
            </div>
            <div style="text-align:center;margin-top:14px;font-size:.72rem">
              Thank you for choosing <?= e(SHOP_NAME) ?> · <?= e(SHOP_TAGLINE) ?>
            </div>
          </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="inv-actions no-print">
          <button class="btn btn-primary" onclick="window.print()">🖨 Print / Save PDF</button>

          <button class="btn btn-teal" type="button"
            onclick="copyInvoiceText('<?= e($invoiceNo) ?>', '<?= e($order['order_code']) ?>', '<?= (int)$tabTotal ?>', '<?= number_format((float)$order['total'], 2) ?>', '<?= e(invoiceUrl((string)$order['order_code'])) ?>')">
            📋 Copy Invoice Summary
          </button>

          <a class="btn btn-wa" target="_blank" rel="noopener"
            href="https://wa.me/?text=<?=
                                      rawurlencode('*Invoice ' . $invoiceNo . '* from ' . SHOP_NAME . "\n" .
                                        'Order: ' . $order['order_code'] . "\n" .
                                        'Total tablets: ' . $tabTotal . "\n" .
                                        'Grand total: ₹' . number_format((float)$order['total'], 2) . "\n" .
                                        'View invoice: ' . invoiceUrl((string)$order['order_code']) . "\n\n" .
                                        '⚠️ Price vary please customer will support this line.')
                                      ?>">💬 Share on WhatsApp</a>

          <a class="btn btn-purple" target="_blank" rel="noopener"
            href="https://wa.me/<?= e(DELIVERY_WHATSAPP) ?>?text=<?=
                                                                  rawurlencode('🚚 Invoice ' . $invoiceNo . ' ready for order ' . $order['order_code'] .
                                                                    "\nTotal tablets: " . $tabTotal .
                                                                    "\nCollect ₹" . number_format((float)$order['total'], 2) . ' from customer.' .
                                                                    "\n" . invoiceUrl((string)$order['order_code']) .
                                                                    "\n⚠️ Price vary please customer will support this line.")
                                                                  ?>">🚚 Send to JK</a>

          <a class="btn btn-line"
            href="<?= PAGE ?>?page=order-status&code=<?= urlencode((string)$order['order_code']) ?>">
            ← Back to order status
          </a>
        </div>

      <?php endif; ?>
    </div>

  <?php /* ============================================================
   ROUTE: HOME  (LIST or DETAIL)
   ============================================================ */
  else: ?>

    <div class="wrap">

      <?php if ($detail): ?>

        <nav class="crumbs">
          <a href="<?= PAGE ?>">Pharmacy</a><span>›</span>
          <a href="<?= PAGE ?>">Medicines</a><span>›</span>
          <?= e($detail['medicine_name']) ?>
        </nav>

        <div class="detail">
          <div>
            <div class="panel">
              <div style="display:flex;gap:16px;align-items:flex-start">
                <div class="thumb" style="width:64px;height:64px;font-size:28px">💊</div>
                <div style="flex:1">
                  <h1 style="font-size:1.35rem;color:var(--navy);line-height:1.3">
                    <?= e($detail['medicine_name']) ?>
                  </h1>
                  <div class="chips" style="margin-top:9px">
                    <span class="chip">Sl. <?= (int)$detail['sl_no'] ?></span>
                    <?php if ($detail['dosage_form']): ?>
                      <span class="chip"><?= e($detail['dosage_form']) ?></span>
                    <?php endif; ?>
                    <?php if ($detail['strength']): ?>
                      <span class="chip"><?= e($detail['strength']) ?></span>
                    <?php endif; ?>
                    <?php if ($detail['pack_quantity']): ?>
                      <span class="chip">Pack of <?= e($detail['pack_quantity']) ?></span>
                    <?php endif; ?>
                    <?php $detailRx = isRx($detail['prescription_status']); ?>
                    <span class="chip <?= $detailRx ? 'rx' : 'otc' ?>">
                      <?= $detailRx ? 'Rx Required' : 'OTC / As applicable' ?>
                    </span>
                    <?php [$sk, $sl] = stockLabel($detail['stock'] !== null ? (int)$detail['stock'] : null); ?>
                    <span class="chip <?= $sk ?>"><?= e($sl) ?></span>
                  </div>
                </div>
              </div>
            </div>

            <div class="panel">
              <h2>Basic information</h2>
              <dl class="kv">
                <dt>Sl. No.</dt>
                <dd><?= (int)$detail['sl_no'] ?></dd>
                <dt>Medicine name</dt>
                <dd><?= e($detail['medicine_name']) ?></dd>
                <dt>Composition</dt>
                <dd><?= e($detail['composition'] ?: '—') ?></dd>
                <dt>Strength</dt>
                <dd><?= e($detail['strength'] ?: '—') ?></dd>
                <dt>Dosage form</dt>
                <dd><?= e($detail['dosage_form'] ?: '—') ?></dd>
                <dt>Pack quantity</dt>
                <dd><?= $detail['pack_quantity'] ? e($detail['pack_quantity']) . ' per pack' : '—' ?></dd>
                <dt>Prescription</dt>
                <dd><?= e($detail['prescription_status'] ?: 'Not specified') ?></dd>
                <dt>Stock</dt>
                <dd><?= e($sl) ?></dd>
              </dl>
            </div>

            <?php if ($detail['why_used']): ?>
              <div class="panel">
                <h2>Primary use</h2>
                <p><?= nl2br(e($detail['why_used'])) ?></p>
              </div>
            <?php endif; ?>
            <?php if ($detail['description']): ?>
              <div class="panel">
                <h2>Description</h2>
                <p><?= nl2br(e($detail['description'])) ?></p>
              </div>
            <?php endif; ?>
            <?php if (!empty($detail['ordering_note'])): ?>
              <div class="panel">
                <h2>Ordering &amp; quality check</h2>
                <div class="notice blue"><?= nl2br(e($detail['ordering_note'])) ?></div>
              </div>
            <?php endif; ?>

            <div class="panel">
              <h2>Safety &amp; clinical information</h2>
              <div class="notice">
                <strong>Clinical data not supplied.</strong>
                Dosage, precautions, contraindications, side effects, drug interactions,
                storage, pregnancy/lactation guidance and prescription requirements were
                <em>not</em> provided in the source catalogue. Verify against the official
                product insert before dispensing.
              </div>
            </div>

            <?php if ($related): ?>
              <div class="panel">
                <h2>Related medicines</h2>
                <div class="chips">
                  <?php foreach ($related as $r): ?>
                    <a class="chip" href="<?= PAGE ?>?id=<?= (int)$r['id'] ?>">
                      <?= e($r['medicine_name']) ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <aside>
            <div class="panel buybox">
              <div class="head">
                <div class="thumb">💊</div>
                <div>
                  <h1><?= e($detail['medicine_name']) ?></h1>
                  <div class="card-sub">
                    <?= e($detail['dosage_form'] ?: '') ?>
                    <?= $detail['strength'] ? ' • ' . e($detail['strength']) : '' ?>
                  </div>
                </div>
              </div>

              <div class="big-price">
                <?= $detail['selling_price'] !== null ? money((float)$detail['selling_price']) : '₹ —' ?>
              </div>
              <div class="note">
                <?= $detail['selling_price'] !== null
                  ? 'Price per pack of ' . e((string)$detail['pack_quantity'])
                  : 'Price was not supplied in the source catalogue.' ?>
              </div>

              <hr>

              <div class="row">
                <div class="qtybox">
                  <button type="button" onclick="stepQty(-1)">−</button>
                  <input type="number" id="detailQty" value="1" min="1" max="999">
                  <button type="button" onclick="stepQty(1)">+</button>
                </div>
                <span class="note">pack(s)</span>
              </div>

              <button class="btn btn-primary btn-block"
                onclick="addToCart(<?= (int)$detail['id'] ?>, document.getElementById('detailQty').value)">
                🛒 Add to cart
              </button>

              <a class="rxlink" style="width:100%;margin-top:10px;justify-content:center"
                href="../build-prescription.php?med=<?= urlencode((string)$detail['medicine_name']) ?>">
                🧾 Prescribe this medicine
              </a>
              <a class="rxlink call" style="width:100%;margin-top:10px;justify-content:center"
                href="tel:<?= e(SHOP_PHONE) ?>">📞 Audio call owner <?= e(SHOP_PHONE) ?></a>
              <button class="btn btn-line btn-block" style="margin-top:10px"
                onclick="location.href='<?= PAGE ?>'">← Back to medicines</button>

              <?php if ($detail['stock'] !== null && (int)$detail['stock'] <= 0): ?>
                <div class="notice red" style="margin-top:14px">Currently out of stock.</div>
              <?php endif; ?>
            </div>
          </aside>
        </div>

      <?php else: ?>

        <nav class="crumbs">
          <a href="<?= PAGE ?>">Pharmacy</a><span>›</span> Medicines &amp; Tablets
        </nav>

        <form class="filters" method="get" action="<?= PAGE ?>" id="filterForm">
          <input type="hidden" name="view" value="<?= e($view) ?>">

          <div class="field" style="grid-column:span 2">
            <label for="fq">Search</label>
            <input type="text" id="fq" name="q" value="<?= e($q) ?>"
              placeholder="Name, salt, strength or use…">
          </div>

          <div class="field">
            <label for="fform">Dosage form</label>
            <select id="fform" name="form">
              <option value="">All forms</option>
              <?php foreach ($forms as $f): ?>
                <option value="<?= e($f['dosage_form']) ?>"
                  <?= $form === $f['dosage_form'] ? 'selected' : '' ?>>
                  <?= e($f['dosage_form']) ?> (<?= (int)$f['c'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label for="frx">Prescription</label>
            <select id="frx" name="rx">
              <option value="">Any</option>
              <option value="yes" <?= $rx === 'yes' ? 'selected' : '' ?>>Rx required</option>
              <option value="no" <?= $rx === 'no'  ? 'selected' : '' ?>>No / not specified</option>
            </select>
          </div>

          <div class="field">
            <label for="favail">Availability</label>
            <select id="favail" name="avail">
              <option value="">Any</option>
              <option value="in" <?= $avail === 'in'  ? 'selected' : '' ?>>In stock</option>
              <option value="out" <?= $avail === 'out' ? 'selected' : '' ?>>Out of stock</option>
              <option value="na" <?= $avail === 'na'  ? 'selected' : '' ?>>Not tracked</option>
            </select>
          </div>

          <div class="field">
            <label for="fsort">Sort by</label>
            <select id="fsort" name="sort">
              <option value="sl_asc" <?= $sort === 'sl_asc'     ? 'selected' : '' ?>>Sl. No. ↑</option>
              <option value="sl_desc" <?= $sort === 'sl_desc'    ? 'selected' : '' ?>>Sl. No. ↓</option>
              <option value="name_asc" <?= $sort === 'name_asc'   ? 'selected' : '' ?>>Name A → Z</option>
              <option value="name_desc" <?= $sort === 'name_desc'  ? 'selected' : '' ?>>Name Z → A</option>
              <option value="price_asc" <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price low → high</option>
              <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price high → low</option>
              <option value="stock_desc" <?= $sort === 'stock_desc' ? 'selected' : '' ?>>Stock high → low</option>
            </select>
          </div>

          <div class="filter-actions">
            <button type="submit" class="btn btn-primary" style="flex:1">Apply</button>
            <a class="btn btn-ghost" href="<?= PAGE ?>">Reset</a>
          </div>
        </form>

        <div class="res-head">
          <div>
            <h1>Medicines &amp; Tablets</h1>
            <div class="sub">
              <?= number_format($totalRows) ?> item<?= $totalRows === 1 ? '' : 's' ?> found
              <?php if ($q !== ''): ?> for “<?= e($q) ?>”<?php endif; ?>
                · page <?= $page ?> of <?= $totalPages ?>
            </div>
          </div>
          <div class="viewtoggle">
            <button type="button" class="<?= $view === 'grid' ? 'on' : '' ?>" onclick="setView('grid')">▦ Grid</button>
            <button type="button" class="<?= $view === 'list' ? 'on' : '' ?>" onclick="setView('list')">☰ Table</button>
          </div>
        </div>

        <?php if (!$medicines): ?>
          <div class="empty">
            <div class="big">🔍</div>
            <strong>No medicines matched your filters.</strong>
            <p style="margin-top:6px">Try a shorter search term or reset the filters.</p>
            <a class="btn btn-primary" style="margin-top:16px" href="<?= PAGE ?>">Reset filters</a>
          </div>
        <?php else: ?>

          <div class="grid" id="gridView" <?= $view === 'list' ? 'style="display:none"' : '' ?>>
            <?php foreach ($medicines as $m): ?>
              <?php
              [$sk, $sl] = stockLabel($m['stock'] !== null ? (int)$m['stock'] : null);
              $mRx = isRx($m['prescription_status']);
              $uid = 'd' . (int)$m['id'];
              ?>
              <article class="card">
                <div class="card-top">
                  <div class="thumb">💊</div>
                  <div style="min-width:0;flex:1">
                    <a class="card-title" href="<?= PAGE ?>?id=<?= (int)$m['id'] ?>">
                      <?= e($m['medicine_name']) ?>
                    </a>
                    <div class="card-sub">
                      <?= e($m['dosage_form'] ?: 'Medicine') ?>
                      <?= $m['strength'] ? ' • ' . e($m['strength']) : '' ?>
                      <?= $m['pack_quantity'] ? ' • ' . e($m['pack_quantity']) . ' per pack' : '' ?>
                    </div>
                  </div>
                </div>

                <div class="card-body">
                  <div class="chips">
                    <span class="chip">Sl. <?= (int)$m['sl_no'] ?></span>
                    <span class="chip <?= $sk ?>"><?= e($sl) ?></span>
                    <span class="chip <?= $mRx ? 'rx' : 'otc' ?>"><?= $mRx ? 'Rx' : 'OTC' ?></span>
                  </div>
                  <div class="desc">
                    <?= e($m['composition'] ?: ($m['why_used'] ?: ($m['description'] ?: 'No description supplied.'))) ?>
                  </div>
                  <button class="desc-toggle" data-target="<?= $uid ?>" onclick="toggleDesc(this)">
                    <span class="arr">▸</span> View full description
                  </button>
                </div>

                <div class="desc-panel" id="<?= $uid ?>">
                  <dl class="kv">
                    <dt>Sl. No.</dt>
                    <dd><?= (int)$m['sl_no'] ?></dd>
                    <dt>Medicine Name</dt>
                    <dd><strong><?= e($m['medicine_name']) ?></strong></dd>
                    <dt>Strength</dt>
                    <dd><?= e($m['strength'] ?: '—') ?></dd>
                    <dt>Composition</dt>
                    <dd><?= e($m['composition'] ?: '—') ?></dd>
                    <dt>Form</dt>
                    <dd><?= e($m['dosage_form'] ?: '—') ?></dd>
                    <dt>Pack Quantity</dt>
                    <dd><?= e($m['pack_quantity'] ?: '—') ?></dd>
                    <dt>Why Used</dt>
                    <dd><?= e($m['why_used'] ?: '—') ?></dd>
                    <dt>Description</dt>
                    <dd><?= e($m['description'] ?: '—') ?></dd>
                    <dt>Price</dt>
                    <dd><strong><?= money($m['selling_price'] !== null ? (float)$m['selling_price'] : null) ?></strong> per pack</dd>
                    <dt>Stock</dt>
                    <dd><?= e($sl) ?></dd>
                    <dt>Prescription</dt>
                    <dd><?= e($m['prescription_status'] ?: 'Not specified') ?></dd>
                  </dl>
                  <div class="note">
                    <strong>Clinical data not supplied in source.</strong> Verify against the official product insert.
                  </div>
                </div>

                <div class="card-foot">
                  <div class="price">
                    <?= $m['selling_price'] !== null ? money((float)$m['selling_price']) : '₹ —' ?>
                    <small><?= $m['selling_price'] !== null ? 'per pack' : 'price not set' ?></small>
                  </div>
                  <div class="actions">
                    <div class="qtybox">
                      <button type="button" onclick="stepCard(<?= (int)$m['id'] ?>,-1)">−</button>
                      <input type="number" id="q<?= (int)$m['id'] ?>" value="1" min="1" max="999">
                      <button type="button" onclick="stepCard(<?= (int)$m['id'] ?>,1)">+</button>
                    </div>
                    <button class="btn btn-primary btn-sm"
                      onclick="addToCart(<?= (int)$m['id'] ?>, document.getElementById('q<?= (int)$m['id'] ?>').value)">
                      Add
                    </button>
                    <a class="rxlink small" title="Prescribe this medicine"
                      href="../build-prescription.php?med=<?= urlencode((string)$m['medicine_name']) ?>">🧾</a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <div class="tablewrap" id="listView" <?= $view === 'grid' ? 'style="display:none"' : '' ?>>
            <table class="med">
              <thead>
                <tr>
                  <th>Sl. No.</th>
                  <th>Medicine Name</th>
                  <th>Strength</th>
                  <th>Composition</th>
                  <th>Form</th>
                  <th>Pack</th>
                  <th>Price</th>
                  <th>Stock</th>
                  <th>Rx</th>
                  <th style="text-align:right">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($medicines as $m): ?>
                  <?php
                  [$sk, $sl] = stockLabel($m['stock'] !== null ? (int)$m['stock'] : null);
                  $mRx = isRx($m['prescription_status']);
                  $rowId = 'r' . (int)$m['id'];
                  ?>
                  <tr onclick="toggleRow('<?= $rowId ?>')" style="cursor:pointer">
                    <td class="muted"><?= (int)$m['sl_no'] ?></td>
                    <td>
                      <a class="nm" href="<?= PAGE ?>?id=<?= (int)$m['id'] ?>"
                        onclick="event.stopPropagation()"><?= e($m['medicine_name']) ?></a>
                    </td>
                    <td class="muted"><?= e($m['strength'] ?: '—') ?></td>
                    <td class="muted" style="max-width:220px">
                      <?= e(mb_strimwidth((string)($m['composition'] ?: '—'), 0, 60, '…')) ?>
                    </td>
                    <td class="muted"><?= e($m['dosage_form'] ?: '—') ?></td>
                    <td class="muted"><?= e($m['pack_quantity'] ?: '—') ?></td>
                    <td><strong><?= money($m['selling_price'] !== null ? (float)$m['selling_price'] : null) ?></strong></td>
                    <td><span class="chip <?= $sk ?>"><?= e($sl) ?></span></td>
                    <td><span class="chip <?= $mRx ? 'rx' : 'otc' ?>"><?= $mRx ? 'Rx' : 'OTC' ?></span></td>
                    <td style="text-align:right;white-space:nowrap">
                      <button class="btn btn-line btn-sm"
                        onclick="event.stopPropagation();location.href='<?= PAGE ?>?id=<?= (int)$m['id'] ?>'">👁</button>
                      <button class="btn btn-primary btn-sm"
                        onclick="event.stopPropagation();addToCart(<?= (int)$m['id'] ?>, 1)">Add</button>
                      <a class="rxlink small" style="vertical-align:middle"
                        onclick="event.stopPropagation()" title="Prescribe this medicine"
                        href="../build-prescription.php?med=<?= urlencode((string)$m['medicine_name']) ?>">🧾</a>
                    </td>
                  </tr>
                  <tr class="rowdesc" id="<?= $rowId ?>">
                    <td colspan="10">
                      <div class="inner">
                        <dl class="kv">
                          <dt>Sl. No.</dt>
                          <dd><?= (int)$m['sl_no'] ?></dd>
                          <dt>Why Used</dt>
                          <dd><?= e($m['why_used'] ?: '—') ?></dd>
                          <dt>Description</dt>
                          <dd><?= e($m['description'] ?: '—') ?></dd>
                          <dt>Order Quantity</dt>
                          <dd><?= e($m['pack_quantity'] ?: '—') ?> per pack</dd>
                          <dt>Prescription</dt>
                          <dd><?= e($m['prescription_status'] ?: 'Not specified') ?></dd>
                        </dl>
                        <div class="note">
                          Clinical data not supplied. Verify against the official product insert.
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php if ($totalPages > 1): ?>
            <nav class="pager">
              <?php if ($page > 1): ?>
                <a href="?<?= e(qs(['page' => $page - 1])) ?>">‹ Prev</a>
              <?php endif; ?>

              <?php
              $start = max(1, $page - 2);
              $end   = min($totalPages, $page + 2);
              if ($start > 1) {
                echo '<a href="?' . e(qs(['page' => 1])) . '">1</a>';
                if ($start > 2) echo '<span class="gap">…</span>';
              }
              for ($p = $start; $p <= $end; $p++):
              ?>
                <?php if ($p === $page): ?>
                  <span class="on"><?= $p ?></span>
                <?php else: ?>
                  <a href="?<?= e(qs(['page' => $p])) ?>"><?= $p ?></a>
                <?php endif; ?>
              <?php endfor; ?>

              <?php
              if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo '<span class="gap">…</span>';
                echo '<a href="?' . e(qs(['page' => $totalPages])) . '">' . $totalPages . '</a>';
              }
              ?>
              <?php if ($page < $totalPages): ?>
                <a href="?<?= e(qs(['page' => $page + 1])) ?>">Next ›</a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>

        <?php endif; ?>

      <?php endif; ?>

      <div class="foot">
        Doctor Assist · KSR GURU JI Pharmacy — medicine master data.
        Price, stock and prescription status are shown only when present in the source catalogue.
        <div style="margin-top:14px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
          <a class="rxlink small" href="../build-prescription.php">🧾 Prescription Builder</a>
          <a class="rxlink small" href="../doctor-prescriptions.php">📋 All prescriptions</a>
          <a class="rxlink small" href="<?= PAGE ?>?page=order-status">📦 Track my order</a>
          <a class="rxlink call small" href="tel:<?= e(SHOP_PHONE) ?>">📞 Owner <?= e(SHOP_PHONE) ?></a>
          <a class="rxlink delivery small" href="tel:<?= e(DELIVERY_PHONE) ?>">🚚 Delivery JK <?= e(DELIVERY_PHONE) ?></a>
        </div>
        <div style="margin-top:10px">
          <?= e(OWNER_NAME) ?> · <?= e(OWNER_EXP) ?>
        </div>
      </div>
    </div>

  <?php endif; /* end home */ ?>

  <!-- CART DRAWER -->
  <?php if ($route === 'home'): ?>
    <div class="overlay" id="overlay" onclick="closeCart()"></div>

    <aside class="drawer" id="drawer" aria-label="Shopping cart">
      <div class="drawer-head">
        <h3>🛒 Your cart</h3>
        <button class="xbtn" onclick="closeCart()" aria-label="Close">✕</button>
      </div>
      <div class="drawer-body" id="cartBody">
        <p class="muted" style="font-size:.86rem">Loading…</p>
      </div>
      <div class="drawer-foot">
        <div class="totrow"><span>Tablets</span><span id="sumItems">0</span></div>
        <div class="totrow"><span>Subtotal</span><span id="sumSubtotal">₹0.00</span></div>
        <div class="totrow"><span>Delivery</span><span>₹15.00</span></div>
        <div class="totrow grand"><span>Total</span><span id="sumTotal">₹15.00</span></div>

        <a class="btn btn-wa" style="width:100%;margin-bottom:8px"
          href="<?= PAGE ?>?page=checkout">📲 Order on WhatsApp</a>

        <button class="btn btn-teal" style="width:100%"
          onclick="location.href='<?= PAGE ?>?page=checkout'">
          Proceed to prescription &amp; checkout
        </button>
        <button class="btn btn-ghost" style="width:100%;margin-top:8px" onclick="clearCart()">
          Clear cart
        </button>
      </div>
    </aside>

    <div class="toast" id="toast"></div>
  <?php endif; ?>

  <!-- SHARED JS -->
  <?php if ($route === 'home'): ?>
    <script>
      const PAGE = '<?= PAGE ?>';
      const $ = (s, r = document) => r.querySelector(s);
      const $$ = (s, r = document) => [...r.querySelectorAll(s)];

      let toastTimer = null;

      function toast(msg, isError = false) {
        const t = $('#toast');
        if (!t) return;
        t.textContent = msg;
        t.classList.toggle('err', isError);
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
      }

      function stepCard(id, delta) {
        const el = $('#q' + id);
        if (!el) return;
        el.value = Math.max(1, (parseInt(el.value, 10) || 1) + delta);
      }

      function stepQty(delta) {
        const el = $('#detailQty');
        if (!el) return;
        el.value = Math.max(1, (parseInt(el.value, 10) || 1) + delta);
      }

      function toggleDesc(btn) {
        const target = btn.getAttribute('data-target');
        const panel = document.getElementById(target);
        if (!panel) return;
        const open = panel.classList.toggle('open');
        btn.classList.toggle('open', open);
        btn.innerHTML = open ?
          '<span class="arr">▸</span> Hide description' :
          '<span class="arr">▸</span> View full description';
      }

      function toggleRow(id) {
        const row = document.getElementById(id);
        if (!row) return;
        const open = row.style.display === 'table-row';
        $$('.rowdesc').forEach(r => r.style.display = 'none');
        if (!open) row.style.display = 'table-row';
      }

      async function api(action, data = null) {
        const opts = data ? {
          method: 'POST',
          body: new URLSearchParams(data)
        } : {
          method: 'GET'
        };
        const res = await fetch(PAGE + '?action=' + action, opts);
        return res.json();
      }
      async function addToCart(id, qty) {
        try {
          const r = await api('cart_add', {
            id,
            qty
          });
          if (!r.ok) {
            toast(r.msg || 'Could not add to cart.', true);
            return;
          }
          setCartCount(r.count);
          toast('✓ Added to cart');
          refreshCart();
        } catch (e) {
          toast('Network error.', true);
        }
      }
      async function refreshCart() {
        try {
          const r = await api('cart_get');
          if (!r.ok) return;
          renderCart(r);
        } catch (e) {}
      }

      function renderCart(r) {
        const body = $('#cartBody');
        if (!body) return;
        setCartCount(r.count);

        if (!r.items.length) {
          body.innerHTML = `<div class="empty" style="padding:38px 14px">
      <div class="big">🛒</div>
      <strong>Your cart is empty</strong>
      <p style="margin-top:6px;font-size:.84rem">Add medicines to continue.</p></div>`;
        } else {
          body.innerHTML = r.items.map(it => `
      <div class="citem">
        <div style="min-width:0;flex:1">
          <div class="nm">${escapeHtml(it.name)}</div>
          <div class="meta">
            ${escapeHtml(it.form || '')}
            ${it.strength ? ' • ' + escapeHtml(it.strength) : ''}
            ${it.pack ? ' • pack of ' + escapeHtml(it.pack) : ''}
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
            <div class="qtybox">
              <button type="button" onclick="changeQty(${it.id}, ${it.qty - 1})">−</button>
              <input type="number" value="${it.qty}" min="1"
                     onchange="changeQty(${it.id}, this.value)">
              <button type="button" onclick="changeQty(${it.id}, ${it.qty + 1})">+</button>
            </div>
            <span class="meta">${it.price_text} each</span>
          </div>
        </div>
        <div class="r">
          <strong style="color:var(--navy)">${it.line_text}</strong>
          <button class="rm" onclick="changeQty(${it.id}, 0)">Remove</button>
        </div>
      </div>`).join('');
        }
        const delivery = r.items.length ? 15 : 0;
        $('#sumItems').textContent = r.count;
        $('#sumSubtotal').textContent = r.total_text;
        $('#sumTotal').textContent = '₹' + (r.total + delivery).toFixed(2);
      }
      async function changeQty(id, qty) {
        qty = parseInt(qty, 10);
        if (isNaN(qty) || qty < 0) qty = 0;
        const r = await api(qty === 0 ? 'cart_remove' : 'cart_update', {
          id,
          qty
        });
        if (r.ok) {
          setCartCount(r.count);
          refreshCart();
        }
      }
      async function clearCart() {
        const r = await api('cart_clear');
        if (r.ok) {
          setCartCount(0);
          refreshCart();
          toast('Cart cleared');
        }
      }

      function setCartCount(n) {
        const el = $('#cartCount');
        if (el) el.textContent = n;
      }

      function openCart() {
        $('#drawer').classList.add('open');
        $('#overlay').classList.add('open');
        refreshCart();
      }

      function closeCart() {
        $('#drawer').classList.remove('open');
        $('#overlay').classList.remove('open');
      }
      document.addEventListener('click', e => {
        if (e.target.id === 'openCart') openCart();
      });

      function setView(v) {
        const grid = $('#gridView'),
          list = $('#listView');
        if (grid) grid.style.display = (v === 'grid') ? '' : 'none';
        if (list) list.style.display = (v === 'list') ? '' : 'none';
        $$('.viewtoggle button').forEach(b => b.classList.toggle('on',
          b.textContent.toLowerCase().includes(v === 'grid' ? 'grid' : 'table')));
        const url = new URL(location.href);
        url.searchParams.set('view', v);
        history.replaceState(null, '', url);
      }

      function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;'
        } [c]));
      }

      const searchInput = $('#searchInput');
      const suggestBox = $('#suggestBox');
      const clearBtn = $('#clearSearch');
      let suggestTimer = null;

      if (searchInput) {
        searchInput.addEventListener('input', () => {
          const q = searchInput.value.trim();
          clearBtn.style.display = q ? 'block' : 'none';
          clearTimeout(suggestTimer);
          if (q.length < 2) {
            suggestBox.classList.remove('open');
            return;
          }
          suggestTimer = setTimeout(async () => {
            try {
              const r = await api('suggest&q=' + encodeURIComponent(q));
              if (!r.ok || !r.items.length) {
                suggestBox.classList.remove('open');
                return;
              }
              suggestBox.innerHTML = r.items.map(it => `
          <a href="${PAGE}?id=${it.id}">
            <strong>${escapeHtml(it.medicine_name)}</strong>
            <div class="meta">
              ${escapeHtml(it.dosage_form || '')}
              ${it.strength ? ' • ' + escapeHtml(it.strength) : ''}
              ${it.pack_quantity ? ' • ' + escapeHtml(it.pack_quantity) + ' per pack' : ''}
              ${it.selling_price ? ' • ₹' + it.selling_price : ''}
            </div>
          </a>`).join('');
              suggestBox.classList.add('open');
            } catch (e) {
              suggestBox.classList.remove('open');
            }
          }, 220);
        });
        searchInput.addEventListener('keydown', ev => {
          if (ev.key === 'Enter') {
            ev.preventDefault();
            const url = new URL(location.href);
            url.searchParams.set('q', searchInput.value.trim());
            url.searchParams.delete('id');
            url.searchParams.set('page', '1');
            location.href = url.toString();
          }
          if (ev.key === 'Escape') suggestBox.classList.remove('open');
        });
        clearBtn.addEventListener('click', () => {
          searchInput.value = '';
          clearBtn.style.display = 'none';
          suggestBox.classList.remove('open');
          searchInput.focus();
        });
        document.addEventListener('click', ev => {
          if (!ev.target.closest('.searchwrap')) suggestBox.classList.remove('open');
        });
        document.addEventListener('DOMContentLoaded', () => {
          if (searchInput.value) clearBtn.style.display = 'block';
          refreshCart();
        });
      }
    </script>
  <?php endif; ?>

  <script>
    // Geolocation for Checkout
    (function initGeo() {
      const geoBtn = document.getElementById('geoBtn');
      if (!geoBtn) return;
      const status = document.getElementById('geoStatus');

      geoBtn.addEventListener('click', () => {
        if (!navigator.geolocation) {
          if (status) status.textContent = 'Geolocation is not supported by your browser.';
          return;
        }
        geoBtn.disabled = true;
        const originalText = geoBtn.innerHTML;
        geoBtn.innerHTML = '📍 Capturing GPS location...';
        if (status) {
          status.textContent = 'Requesting GPS coordinates (high accuracy)...';
          status.style.color = '#0284c7';
        }

        navigator.geolocation.getCurrentPosition(
          pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = pos.coords.accuracy;

            const latInput = document.getElementById('lat');
            const lngInput = document.getElementById('lng');
            const accInput = document.getElementById('acc');
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;
            if (accInput) accInput.value = acc;

            geoBtn.innerHTML = '✓ Location shared (' + Math.round(acc) + 'm accuracy)';
            geoBtn.style.background = '#15803d';
            geoBtn.disabled = false;

            if (status) {
              status.innerHTML = '📍 ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ' (Accuracy: ~' + Math.round(acc) + 'm)';
              status.style.color = '#15803d';
            }

            // Auto reverse geocode into address field if empty
            const addrField = document.getElementById('address');
            if (addrField && addrField.value.trim() === '') {
              fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng)
                .then(r => r.json())
                .then(d => {
                  if (d && d.display_name && addrField.value.trim() === '') {
                    addrField.value = d.display_name;
                  }
                }).catch(() => {});
            }
          },
          err => {
            geoBtn.disabled = false;
            geoBtn.innerHTML = originalText;
            if (status) {
              status.textContent = 'Could not get location (' + err.message + '). Please enter address manually.';
              status.style.color = '#b91c1c';
            }
          },
          { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
      });
    })();

    // Copy invoice details helper
    function copyInvoiceText(invNo, code, tabs, total, url) {
      const text = "📄 INVOICE " + invNo + " — " + <?= json_encode(SHOP_NAME) ?> + "\n" +
        "Order: " + code + "\n" +
        "Total Tablets: " + tabs + "\n" +
        "Grand Total: ₹" + total + "\n" +
        "Invoice Link: " + url + "\n\n" +
        "⚠️ Price vary please customer will support this line.";
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
          alert("✓ Invoice summary copied to clipboard with price variation notice!");
        }).catch(() => {
          prompt("Copy invoice text:", text);
        });
      } else {
        prompt("Copy invoice text:", text);
      }
    }

    // Copy order summary helper
    function copyTabletOrderSummary(code, total, count, url) {
      const text = "📦 ORDER SUMMARY — " + <?= json_encode(SHOP_NAME) ?> + "\n" +
        "Order: " + code + "\n" +
        "Total Tablets: " + count + "\n" +
        "Grand Total: ₹" + total + "\n" +
        "Invoice Link: " + url + "\n\n" +
        "⚠️ Price vary please customer will support this line.";
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
          alert("✓ Order details copied to clipboard!");
        }).catch(() => {
          prompt("Copy order details:", text);
        });
      } else {
        prompt("Copy order details:", text);
      }
    }
  </script>

</body>

</html>