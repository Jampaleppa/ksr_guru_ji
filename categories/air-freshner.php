<?php
// ============================================================
// air-freshener.php - Dynamic Air Fresheners (with images)
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
// Start session
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
  $_SESSION['cart'] = [];
}
// Handle AJAX requests for this page
if (isset($_GET['ajax'])) {
  header('Content-Type: application/json');
  $action = $_GET['ajax'] ?? '';
  switch ($action) {
    case 'products':
      $search = $_GET['search'] ?? '';
      $subcat = $_GET['subcategory'] ?? '';
      echo json_encode(getAirFreshenerProducts($search, $subcat));
      exit;
    case 'cart':
      echo json_encode($_SESSION['cart']);
      exit;
    case 'add_cart':
      $id = (int) ($_POST['id'] ?? 0);
      if ($id) {
        $product = getProductById($id);
        if (!$product) {
          $fallbackProducts = getFallbackAirFreshenerProducts();
          foreach ($fallbackProducts as $fp) {
            if ($fp['id'] == $id) {
              $product = [
                'id' => $fp['id'],
                'name' => $fp['name'],
                'kn_name' => $fp['kn_name'] ?? $fp['name'],
                'price' => $fp['price'],
                'icon' => 'fa-wind'
              ];
              break;
            }
          }
        }
        if ($product) {
          $found = false;
          foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $id) {
              $item['qty'] += 1;
              $found = true;
              break;
            }
          }
          if (!$found) {
            $_SESSION['cart'][] = [
              'id' => $product['id'],
              'name' => $product['name'],
              'kn' => $product['kn_name'] ?? $product['name'],
              'price' => $product['price'],
              'qty' => 1,
              'icon' => $product['icon'] ?? 'fa-wind'
            ];
          }
          echo json_encode(['success' => true, 'cart' => $_SESSION['cart']]);
          exit;
        }
      }
      echo json_encode(['success' => false]);
      exit;
    case 'update_cart':
      $id = (int) ($_POST['id'] ?? 0);
      $qty = (int) ($_POST['qty'] ?? 0);
      if ($id && $qty > 0) {
        foreach ($_SESSION['cart'] as &$item) {
          if ($item['id'] == $id) {
            $item['qty'] = $qty;
            break;
          }
        }
      } elseif ($id && $qty == 0) {
        $_SESSION['cart'] = array_filter($_SESSION['cart'], fn($i) => $i['id'] != $id);
      }
      $_SESSION['cart'] = array_values($_SESSION['cart']);
      echo json_encode(['success' => true, 'cart' => $_SESSION['cart']]);
      exit;
    case 'remove_cart':
      $id = (int) ($_POST['id'] ?? 0);
      if ($id) {
        $_SESSION['cart'] = array_filter($_SESSION['cart'], fn($i) => $i['id'] != $id);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        echo json_encode(['success' => true, 'cart' => $_SESSION['cart']]);
        exit;
      }
      echo json_encode(['success' => false]);
      exit;
    case 'clear_cart':
      $_SESSION['cart'] = [];
      echo json_encode(['success' => true]);
      exit;
    default:
      echo json_encode(['error' => 'Invalid action']);
      exit;
  }
}
// ============================================================
// HELPER: Normalize image path to root‑relative URL
// ============================================================
function normalizeAirImagePath(?string $rawPath): string
{
  if (empty($rawPath)) return '';
  // Remove Windows drive letter and backslashes
  $clean = str_replace('\\', '/', $rawPath);
  // Extract path after 'images/'
  if (preg_match('#(?:.*?)(images/.*)$#i', $clean, $matches)) {
    return '/' . $matches[1]; // e.g. /images/Air/xyz.jpg
  }
  // Fallback: just the filename
  return '/' . basename($clean);
}
// ============================================================
// FALLBACK: Static product data (converted from JS array)
// ============================================================
function getFallbackAirFreshenerProducts()
{
  static $fallback = null;
  if ($fallback !== null) return $fallback;
  $json = '[
  {"id":0,"category":"floral","name_en":"Ambi Pur Air Effect Rose Air Freshener","name_kn":"ಗುಲಾಬಿ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Refreshing rose fragrance for pooja room, 200ml spray.","desc_kn":"ಪೂಜಾ ಕೋಣೆಗೆ ತಾಜಾ ಗುಲಾಬಿ ಸುಗಂಧ, 200ml ಸ್ಪ್ರೇ.","price":180,"mrp":250,"source":"Amazon","ad":true,"imgLabel":"Rose","stock":25,"imgUrl":"images\\\\Ambi Pur Air Effect Rose and Blossom Air Freshener.jpg"},
  {"id":1,"category":"woody","name_en":"Sandalwood Air Freshener","name_kn":"ಶ್ರೀಗಂಧ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Traditional sandalwood scent, 200ml.","desc_kn":"ಸಾಂಪ್ರದಾಯಿಕ ಶ್ರೀಗಂಧದ ಸುಗಂಧ, 200ml.","price":210,"mrp":295,"source":"Flipkart","ad":false,"imgLabel":"Sandalwood","stock":18,"imgUrl":"images\\\\sandalwood.jpg"},
  {"id":2,"category":"floral","name_en":"Lavender Air Freshener","name_kn":"ಲ್ಯಾವೆಂಡರ್ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Calming lavender fragrance, 200ml.","desc_kn":"ಶಾಂತಗೊಳಿಸುವ ಲ್ಯಾವೆಂಡರ್ ಪರಿಮಳ, 200ml.","price":195,"mrp":275,"source":"Indiamart","ad":true,"imgLabel":"Lavender","stock":30,"imgUrl":"images\\\\Lavender Air Freshener.jpg"},
  {"id":3,"category":"sweet","name_en":"Mango Bloom Air Freshener","name_kn":"ಮಾವು ಬ್ಲೂಮ್ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Sweet mango fragrance, 200ml.","desc_kn":"ಸಿಹಿ ಮಾವಿನ ಸುಗಂಧ, 200ml.","price":170,"mrp":240,"source":"Local Mart","ad":false,"imgLabel":"Mango","stock":22,"imgUrl":"images\\\\Mango Bloom Air Freshener.jpg"},
  {"id":4,"category":"floral","name_en":"Jasmine Air Freshener","name_kn":"ಮಲ್ಲಿಗೆ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Exotic jasmine scent, 200ml.","desc_kn":"ವಿದೇಶಿ ಮಲ್ಲಿಗೆ ಪರಿಮಳ, 200ml.","price":190,"mrp":260,"source":"Amazon","ad":true,"imgLabel":"Jasmine","stock":15,"imgUrl":"images\\\\Jasmine Air Freshener.jpg"},
  {"id":5,"category":"woody","name_en":"Pine Forest Air Freshener","name_kn":"ಪೈನ್ ಕಾಡು ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Fresh pine fragrance, 200ml.","desc_kn":"ತಾಜಾ ಪೈನ್ ಸುಗಂಧ, 200ml.","price":185,"mrp":250,"source":"Flipkart","ad":false,"imgLabel":"Pine","stock":20,"imgUrl":"images\\\\Pine Forest Air Freshener.jpg"},
  {"id":6,"category":"citrus","name_en":"Lemon Zest Air Freshener","name_kn":"ನಿಂಬೆ ಝೆಸ್ಟ್ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Citrusy lemon freshness, 200ml spray.","desc_kn":"ನಿಂಬೆ ತಾಜಾತನ, 200ml ಸ್ಪ್ರೇ.","price":160,"mrp":220,"source":"Local Mart","ad":true,"imgLabel":"Lemon","stock":28,"imgUrl":"images\\\\Lemon Zest Air Freshener.jpg"},
  {"id":7,"category":"sweet","name_en":"Vanilla Bean Air Freshener","name_kn":"ವೆನಿಲ್ಲಾ ಬೀನ್ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Warm vanilla fragrance, 200ml.","desc_kn":"ಬೆಚ್ಚಗಿನ ವೆನಿಲ್ಲಾ ಸುಗಂಧ, 200ml.","price":200,"mrp":280,"source":"Nykaa","ad":false,"imgLabel":"Vanilla","stock":12,"imgUrl":"images\\\\Vanilla Bean Air Freshener.jpg"},
  {"id":8,"category":"marine","name_en":"Ocean Breeze Air Freshener","name_kn":"ಸಾಗರ ತಂಗಾಳಿ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Cool ocean mist, 200ml.","desc_kn":"ತಂಪಾದ ಸಾಗರ ಮಂಜು, 200ml.","price":175,"mrp":240,"source":"Amazon","ad":true,"imgLabel":"Ocean","stock":16,"imgUrl":"images\\\\Ocean Breeze Air Freshener.jpg"},
  {"id":9,"category":"floral","name_en":"Champa Flower Air Freshener","name_kn":"ಶಂಪಾ ಹೂ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Traditional champa fragrance, 200ml.","desc_kn":"ಸಾಂಪ್ರದಾಯಿಕ ಶಂಪಾ ಪರಿಮಳ, 200ml.","price":215,"mrp":300,"source":"Flipkart","ad":false,"imgLabel":"Champa","stock":14,"imgUrl":"images\\\\Champa Flower Air Freshener.jpg"},
  {"id":10,"category":"floral","name_en":"Air Freshener Spray","name_kn":"ಏರ್ ಫ್ರೆಶ್ನರ್ ಸ್ಪ್ರೇ","desc_en":"Fresh floral spray, 200ml.","desc_kn":"ತಾಜಾ ಹೂವಿನ ಸ್ಪ್ರೇ, 200ml.","price":95,"mrp":150,"source":"Local Mart","ad":false,"imgLabel":"Spray","stock":35,"imgUrl":"images\\\\Air Freshener Spray 500.jpg"},
  {"id":11,"category":"floral","name_en":"Automatic Air Freshener","name_kn":"ಆಟೋಮ್ಯಾಟಿಕ್ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Auto-spray freshener, 250ml.","desc_kn":"ಸ್ವಯಂಚಾಲಿತ ಸ್ಪ್ರೇ, 250ml.","price":350,"mrp":499,"source":"Amazon","ad":true,"imgLabel":"Auto","stock":8,"imgUrl":"images\\\\Automatic Air Freshener 250.jpg"},
  {"id":12,"category":"woody","name_en":"Cedarwood Air Freshener","name_kn":"ಸೀಡರ್ ವುಡ್ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Earthy cedarwood, 200ml.","desc_kn":"ಮಣ್ಣಿನ ಸೀಡರ್ ವುಡ್, 200ml.","price":1480,"mrp":2800,"source":"Amazon","ad":true,"imgLabel":"Cedar","stock":10,"imgUrl":"images\\\\Cedarwood Air Freshener 1480.jpg"},
  {"id":13,"category":"citrus","name_en":"Orange Burst Freshener","name_kn":"ಆರೆಂಜ್ ಬರ್ಸ್ಟ್ ಫ್ರೆಶ್ನರ್","desc_en":"Zesty orange, 200ml.","desc_kn":"ಕಿತ್ತಳೆ ರಸಭರಿತ, 200ml.","price":2800,"mrp":3900,"source":"Local Mart","ad":false,"imgLabel":"Orange","stock":19,"imgUrl":"images\\\\Orange Burst Freshener 2800.jpg"},
  {"id":14,"category":"marine","name_en":"Sea Salt Air Freshener","name_kn":"ಸಮುದ್ರ ಉಪ್ಪು ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Fresh sea salt breeze, 200ml.","desc_kn":"ತಾಜಾ ಸಮುದ್ರ ಉಪ್ಪು ತಂಗಾಳಿ, 200ml.","price":480,"mrp":600,"source":"Flipkart","ad":false,"imgLabel":"Sea Salt","stock":13,"imgUrl":"images\\\\Sea Salt Air Freshener 480.jpg"},
  {"id":15,"category":"sweet","name_en":"Coconut Bliss Air Freshener","name_kn":"ತೆಂಗಿನಕಾಯಿ ಬ್ಲಿಸ್ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Tropical coconut fragrance, 200ml.","desc_kn":"ಉಷ್ಣವಲಯದ ತೆಂಗಿನ ಸುಗಂಧ, 200ml.","price":2000,"mrp":2900,"source":"Amazon","ad":true,"imgLabel":"Coconut","stock":21,"imgUrl":"images\\\\Coconut Bliss Air Freshener 2900.jpg"},
  {"id":16,"category":"airfreshener","name_en":"Air Freshener 90","name_kn":"ಏರ್ ಫ್ರೆಶ್ನರ್ 90","desc_en":"Refreshing air freshener fragrance.","desc_kn":"ತಾಜಾ ಸುಗಂಧದ ಏರ್ ಫ್ರೆಶ್ನರ್.","price":90,"mrp":130,"source":"Amazon","ad":true,"imgLabel":"Air Freshener","stock":20,"imgUrl":"images\\\\Air Freshener 90.jpg"},
  {"id":17,"category":"airfreshener","name_en":"Air Freshener 680","name_kn":"ಏರ್ ಫ್ರೆಶ್ನರ್ 680","desc_en":"Long lasting premium air freshener.","desc_kn":"ದೀರ್ಘಕಾಲ ಉಳಿಯುವ ಪ್ರೀಮಿಯಂ ಏರ್ ಫ್ರೆಶ್ನರ್.","price":680,"mrp":750,"source":"Amazon","ad":true,"imgLabel":"Air Freshener","stock":18,"imgUrl":"images\\\\Air Freshener 680.jpg"},
  {"id":18,"category":"airfreshener","name_en":"Air Freshener Spray 290","name_kn":"ಏರ್ ಫ್ರೆಶ್ನರ್ ಸ್ಪ್ರೇ 290","desc_en":"Instant freshness spray for rooms.","desc_kn":"ಕೊಠಡಿಗಳಿಗೆ ತಕ್ಷಣದ ತಾಜಾತನ ಸ್ಪ್ರೇ.","price":290,"mrp":360,"source":"Amazon","ad":true,"imgLabel":"Spray","stock":25,"imgUrl":"images\\\\Air Freshener Spray 290.jpg"},
  {"id":19,"category":"airfreshener","name_en":"Air Freshener Spray 500","name_kn":"ಏರ್ ಫ್ರೆಶ್ನರ್ ಸ್ಪ್ರೇ 500","desc_en":"Premium room freshener spray.","desc_kn":"ಪ್ರೀಮಿಯಂ ರೂಮ್ ಫ್ರೆಶ್ನರ್ ಸ್ಪ್ರೇ.","price":500,"mrp":620,"source":"Amazon","ad":true,"imgLabel":"Spray","stock":14,"imgUrl":"images\\\\Air Freshener Spray 500.jpg"},
  {"id":20,"category":"airfreshener","name_en":"Automatic Air Freshener 250","name_kn":"ಸ್ವಯಂಚಾಲಿತ ಏರ್ ಫ್ರೆಶ್ನರ್ 250","desc_en":"Automatic fragrance dispenser.","desc_kn":"ಸ್ವಯಂಚಾಲಿತ ಸುಗಂಧ ವಿತರಕ.","price":250,"mrp":320,"source":"Amazon","ad":true,"imgLabel":"Automatic","stock":16,"imgUrl":"images\\\\Automatic Air Freshener 250.jpg"},
  {"id":21,"category":"airfreshener","name_en":"Automatic Air Freshener 420","name_kn":"ಸ್ವಯಂಚಾಲಿತ ಏರ್ ಫ್ರೆಶ್ನರ್ 420","desc_en":"Long lasting automatic room freshener.","desc_kn":"ದೀರ್ಘಕಾಲ ಉಳಿಯುವ ಸ್ವಯಂಚಾಲಿತ ರೂಮ್ ಫ್ರೆಶ್ನರ್.","price":420,"mrp":510,"source":"Amazon","ad":true,"imgLabel":"Automatic","stock":12,"imgUrl":"images\\\\Automatic Air Freshener 420.jpg"},
  {"id":22,"category":"airfreshener","name_en":"Automatic Air Freshener 510","name_kn":"ಸ್ವಯಂಚಾಲಿತ ಏರ್ ಫ್ರೆಶ್ನರ್ 510","desc_en":"Premium automatic fragrance spray.","desc_kn":"ಪ್ರೀಮಿಯಂ ಸ್ವಯಂಚಾಲಿತ ಸುಗಂಧ ಸ್ಪ್ರೇ.","price":510,"mrp":640,"source":"Amazon","ad":true,"imgLabel":"Automatic","stock":10,"imgUrl":"images\\\\Automatic Air Freshener 510.jpg"},
  {"id":23,"category":"airfreshener","name_en":"Champa Flower Air Freshener","name_kn":"ಚಂಪಾ ಹೂ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Pleasant champa flower fragrance.","desc_kn":"ಮನಮೋಹಕ ಚಂಪಾ ಹೂವಿನ ಸುಗಂಧ.","price":190,"mrp":250,"source":"Amazon","ad":true,"imgLabel":"Champa","stock":22,"imgUrl":"images\\\\Champa Flower Air Freshener.jpg"},
  {"id":24,"category":"airfreshener","name_en":"Godrej Aer Matic Violet Valley Bloom Automatic Room Fresheners","name_kn":"ಗೋದ್ರೆಜ್ ಏರ್ ಮ್ಯಾಟಿಕ್ ವೈಯೊಲೆಟ್ ವ್ಯಾಲಿ ಬ್ಲೂಮ್ ರೂಮ್ ಫ್ರೆಶ್ನರ್","desc_en":"Automatic floral fragrance room freshener.","desc_kn":"ಸ್ವಯಂಚಾಲಿತ ಹೂವಿನ ಸುಗಂಧದ ರೂಮ್ ಫ್ರೆಶ್ನರ್.","price":399,"mrp":499,"source":"Amazon","ad":true,"imgLabel":"Godrej","stock":15,"imgUrl":"images\\\\Godrej Aer Matic Violet Valley Bloom Automatic Room Fresheners.jpg"},
  {"id":25,"category":"airfreshener","name_en":"Godrej Aer Power Pocket Bathroom Freshener","name_kn":"ಗೋದ್ರೆಜ್ ಏರ್ ಪವರ್ ಪಾಕೆಟ್ ಬಾತ್‌ರೂಮ್ ಫ್ರೆಶ್ನರ್","desc_en":"Compact bathroom fragrance solution.","desc_kn":"ಸಣ್ಣ ಬಾತ್‌ರೂಮ್ ಸುಗಂಧ ಪರಿಹಾರ.","price":99,"mrp":140,"source":"Amazon","ad":true,"imgLabel":"Bathroom","stock":30,"imgUrl":"images\\\\Godrej Aer Power Pocket Bathroom Freshener.jpg"},
  {"id":26,"category":"airfreshener","name_en":"Herbal Shampoo","name_kn":"ಹರ್ಬಲ್ ಶಾಂಪು","desc_en":"Natural herbal care shampoo.","desc_kn":"ಸ್ವಾಭಾವಿಕ ಹರ್ಬಲ್ ಕೇರ್ ಶಾಂಪು.","price":210,"mrp":280,"source":"Amazon","ad":true,"imgLabel":"Shampoo","stock":19,"imgUrl":"images\\\\Herbal Shampoo.jpg"},
  {"id":27,"category":"airfreshener","name_en":"Lemon Zest Air Freshener","name_kn":"ಲೆಮನ್ ಜೆಸ್ಟ್ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Fresh lemon citrus fragrance.","desc_kn":"ತಾಜಾ ಲೆಮನ್ ಸಿಟ್ರಸ್ ಸುಗಂಧ.","price":160,"mrp":220,"source":"Amazon","ad":true,"imgLabel":"Lemon","stock":23,"imgUrl":"images\\\\Lemon Zest Air Freshener.jpg"},
  {"id":28,"category":"airfreshener","name_en":"Mango Bloom Air Freshener","name_kn":"ಮ್ಯಾಂಗೋ ಬ್ಲೂಮ್ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Sweet mango blossom fragrance.","desc_kn":"ಸಿಹಿ ಮಾವಿನ ಹೂವಿನ ಸುಗಂಧ.","price":180,"mrp":240,"source":"Amazon","ad":true,"imgLabel":"Mango","stock":20,"imgUrl":"images\\\\Mango Bloom Air Freshener.jpg"},
  {"id":29,"category":"airfreshener","name_en":"Odonil Floral Valley Gel Air Freshener","name_kn":"ಒಡೊನಿಲ್ ಫ್ಲೋರಲ್ ವ್ಯಾಲಿ ಜೆಲ್ ಏರ್ ಫ್ರೆಶ್ನರ್","desc_en":"Gel based floral fragrance freshener.","desc_kn":"ಜೆಲ್ ಆಧಾರಿತ ಹೂವಿನ ಸುಗಂಧ ಫ್ರೆಶ್ನರ್.","price":145,"mrp":199,"source":"Amazon","ad":true,"imgLabel":"Odonil","stock":28,"imgUrl":"images\\\\Odonil Floral Valley Gel Air Freshener.jpg"},
  {"id":30,"category":"airfreshener","name_en":"Rose 160","name_kn":"ರೋಸ್ 160","desc_en":"Classic rose fragrance air freshener.","desc_kn":"ಸಾಂಪ್ರದಾಯಿಕ ಗುಲಾಬಿ ಸುಗಂಧದ ಏರ್ ಫ್ರೆಶ್ನರ್.","price":160,"mrp":210,"source":"Amazon","ad":true,"imgLabel":"Rose","stock":24,"imgUrl":"images\\\\Rose 160.jpg"}
]';
  $data = json_decode($json, true);
  if (!is_array($data)) return [];
  $result = [];
  foreach ($data as $item) {
    $result[] = [
      'id' => $item['id'],
      'name' => $item['name_en'],
      'kn_name' => $item['name_kn'],
      'subcategory' => $item['category'],
      'price' => $item['price'],
      'mrp' => $item['mrp'],
      'desc_en' => $item['desc_en'],
      'desc_kn' => $item['desc_kn'],
      'source' => $item['source'],
      'stock' => $item['stock'],
      'is_featured' => $item['ad'] ? 1 : 0,
      'emoji' => '🌬️',
      'category_name' => ucfirst($item['category']),
      'image_url' => normalizeAirImagePath($item['imgUrl'] ?? '')
    ];
  }
  $fallback = $result;
  return $fallback;
}
// Helper function to fetch air freshener products (DB + fallback)
function getAirFreshenerProducts(string $search = '', string $subcat = ''): array
{
  try {
    $pdo = getDBConnection();

    // Resolve the category by its real slug. The products table has no
    // mrp/source/image_url/desc_*/subcategory columns, so we only select
    // columns that actually exist and alias them to what the page expects.
    $catStmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? OR slug = ? LIMIT 1");
    $catStmt->execute(['air-freshner', 'air-fresheners']);
    $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
    $catId = $catRow ? $catRow['id'] : null;

    $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.stock, p.emoji, p.is_featured,
                       p.images AS image_url, p.description AS desc_en,
                       c.name AS category_name, c.kn_name AS category_kn
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE 1=1";
    $params = [];

    if ($catId !== null) {
      $sql .= " AND p.category_id = ?";
      $params[] = $catId;
    } else {
      $sql .= " AND c.name LIKE ?";
      $params[] = '%Air Freshener%';
    }

    if ($search !== '') {
      $sql .= " AND (p.name LIKE ? OR p.kn_name LIKE ?)";
      $params[] = '%' . $search . '%';
      $params[] = '%' . $search . '%';
    }

    $sql .= " ORDER BY p.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $rows = [];
  }
  // If DB returned nothing, use fallback
  if (empty($rows)) {
    $rows = getFallbackAirFreshenerProducts();
    if ($search !== '') {
      $rows = array_filter($rows, function ($p) use ($search) {
        $s = strtolower($search);
        return stripos($p['name'], $s) !== false || stripos($p['kn_name'], $s) !== false;
      });
      $rows = array_values($rows);
    }
    if ($subcat !== '' && $subcat !== 'all') {
      $rows = array_filter($rows, function ($p) use ($subcat) {
        return $p['subcategory'] === $subcat;
      });
      $rows = array_values($rows);
    }
  }
  // Ensure all rows have required fields and normalize image paths
  foreach ($rows as &$item) {
    $item['kn_name'] = $item['kn_name'] ?? $item['name'];
    $item['desc_en'] = $item['desc_en'] ?? '';
    $item['desc_kn'] = $item['desc_kn'] ?? '';
    $item['mrp'] = $item['mrp'] ?? $item['price'];
    $item['stock'] = $item['stock'] ?? 10;
    $item['source'] = $item['source'] ?? 'KSR GURU';
    $item['is_featured'] = $item['is_featured'] ?? 0;
    $item['emoji'] = $item['emoji'] ?? '🌬️';
    $item['subcategory'] = $item['subcategory'] ?? 'airfreshener';
    // Normalize image path (root-relative)
    if (!empty($item['image_url'])) {
      $item['image_url'] = normalizeAirImagePath($item['image_url']);
    } else {
      $item['image_url'] = '';
    }
  }
  unset($item);
  return $rows;
}
// Get products
$products = getAirFreshenerProducts();
// Get cart count and total
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
  <title>KSR Guru JI · Air Fresheners</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    /* ===== WARM MINIMALISM DESIGN ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      background: #f9f6f2;
      padding: 24px 20px;
      min-height: 100vh;
    }

    #app {
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
      background: white;
      border-radius: 40px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
      padding: 28px 32px 40px;
    }

    .secondary-nav {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 28px;
    }

    .secondary-nav a {
      background: #f3eee9;
      padding: 10px 20px;
      border-radius: 50px;
      font-size: 0.9rem;
      font-weight: 500;
      color: #5c3e24;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: background 0.2s;
    }

    .secondary-nav a:hover {
      background: #e5d5c4;
    }

    .shop-header {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
      margin-bottom: 2rem;
    }

    .branding h1 {
      font-size: 2.2rem;
      font-weight: 700;
      color: #3e2c1b;
      line-height: 1.2;
    }

    .branding .kannada-tag {
      font-size: 0.85rem;
      color: #b45f2b;
      font-weight: 500;
      margin-top: 4px;
    }

    .cart-link {
      background: #f7ede3;
      border-radius: 80px;
      padding: 12px 24px;
      display: inline-flex;
      align-items: center;
      gap: 14px;
      transition: 0.2s;
      border: 1px solid #e6d9cd;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
      text-decoration: none;
      color: #5c3e24;
      font-weight: 600;
      font-size: 0.95rem;
    }

    .cart-link:hover {
      background: #e5d5c4;
      transform: translateY(-2px);
    }

    .cart-link i {
      font-size: 1.5rem;
      color: #b45f2b;
    }

    .cart-link .cart-count {
      background: #b45f2b;
      color: white;
      font-weight: 700;
      padding: 2px 12px;
      border-radius: 60px;
      font-size: 1rem;
      margin-left: 4px;
    }

    .search-panel {
      margin-bottom: 32px;
    }

    .search-bar {
      background: #f3eee9;
      border-radius: 70px;
      padding: 8px 24px;
      display: flex;
      align-items: center;
      border: 1px solid #e6d9cd;
      gap: 12px;
      margin-bottom: 24px;
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.02);
    }

    .search-bar i {
      font-size: 1.2rem;
      color: #b45f2b;
    }

    .search-bar input {
      flex: 1;
      border: none;
      padding: 12px 0;
      background: transparent;
      font-size: 1rem;
      outline: none;
      color: #2f2216;
      font-weight: 500;
    }

    .filter-row {
      overflow-x: auto;
      padding-bottom: 8px;
      scrollbar-width: thin;
    }

    .pill-group {
      display: flex;
      gap: 12px;
    }

    .pill {
      background: #f3eee9;
      padding: 10px 24px;
      border-radius: 60px;
      font-weight: 600;
      font-size: 0.85rem;
      color: #4f3e2e;
      cursor: pointer;
      white-space: nowrap;
      border: 1px solid transparent;
      transition: all 0.2s;
    }

    .pill.active {
      background: #5c3e24;
      color: white;
      border-color: #5c3e24;
      box-shadow: 0 6px 12px rgba(92, 62, 36, 0.2);
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 30px;
      margin: 32px 0 48px;
    }

    .product-card {
      background: white;
      border-radius: 28px;
      padding: 22px 18px 24px;
      transition: all 0.25s ease;
      border: 1px solid #f2e5d9;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
      display: flex;
      flex-direction: column;
      position: relative;
    }

    .product-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 30px rgba(0, 0, 0, 0.08);
      border-color: #e5d5c4;
    }

    .product-img {
      width: 100%;
      height: 160px;
      background: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.8rem;
      overflow: hidden;
      margin: 0 auto 16px;
      border-radius: 12px;
    }

    .product-img img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .product-title {
      font-weight: 800;
      font-size: 1.15rem;
      color: #2f2216;
      line-height: 1.3;
    }

    .product-title-kn {
      font-size: 0.75rem;
      color: #7b5f45;
      margin: 5px 0 12px;
      font-weight: 500;
    }

    .product-description {
      font-size: 0.8rem;
      background: #fefaf6;
      padding: 8px 12px;
      border-radius: 12px;
      color: #5c3e24;
      margin: 8px 0 14px;
      line-height: 1.4;
      border-left: 3px solid #b45f2b;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      flex-wrap: wrap;
      margin-top: auto;
    }

    .price {
      font-weight: 800;
      font-size: 1.5rem;
      color: #b45f2b;
      letter-spacing: -0.02em;
    }

    .price small {
      font-size: 0.7rem;
      font-weight: 500;
      color: #a07c5e;
    }

    .descriptive-cart-btn {
      background: #b45f2b;
      border: none;
      border-radius: 60px;
      padding: 12px 8px;
      font-weight: 700;
      font-size: 0.75rem;
      color: white;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 18px;
      transition: 0.2s;
      box-shadow: 0 4px 12px rgba(180, 95, 43, 0.25);
    }

    .descriptive-cart-btn i {
      font-size: 0.8rem;
    }

    .descriptive-cart-btn:hover {
      transform: scale(0.96);
      background: #9c5022;
    }

    .product-badge {
      position: absolute;
      top: 12px;
      left: 12px;
      background: #c45a2c;
      color: white;
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      z-index: 2;
      box-shadow: 0 4px 8px rgba(196, 90, 44, 0.25);
    }

    .product-source-row {
      font-size: 0.72rem;
      color: #8a6e54;
      margin: 0.5rem 0 0;
      display: flex;
      justify-content: space-between;
      width: 100%;
      border-top: 1px solid #f0e3d7;
      padding-top: 8px;
      font-weight: 500;
      gap: 4px;
      flex-wrap: wrap;
    }

    .product-source-row .stock-tag {
      font-weight: 600;
      color: #5c3e24;
    }

    .product-source-row .ad-tag {
      border-radius: 4px;
      padding: 1px 4px;
      font-size: 0.65rem;
      font-weight: 700;
    }

    .product-source-row .ad-tag.sponsored {
      background: #fbeadd;
      color: #c45a2c;
    }

    .product-source-row .ad-tag.trusted {
      background: #fdf5ef;
      color: #7b5f45;
    }

    footer {
      text-align: center;
      font-size: 0.75rem;
      color: #b9a690;
      margin-top: 32px;
      padding-top: 18px;
      border-top: 1px solid #f0e3d7;
    }

    .design-credits {
      margin-top: 20px;
      text-align: center;
      font-size: 0.7rem;
      color: #a07c5e;
    }

    @media (max-width: 640px) {
      #app {
        padding: 20px;
      }

      .product-grid {
        gap: 18px;
      }

      .product-img {
        aspect-ratio: 1/1;
      }

      .price {
        font-size: 1.3rem;
      }
    }
  </style>
  <link rel="stylesheet" href="category-style.css">
  <link rel="stylesheet" href="../index.css">
</head>

<body>
  <div id="app">
    <?php
    $base_url = '../';
    include __DIR__ . '/../includes/global-header.php';
    ?>

    <div class="search-panel">
      <div class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
      </div>

      <div class="filter-row">
        <div class="pill-group" id="filterPills">
          <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
          <div class="pill" data-category="floral">Floral / ಹೂವಿನ</div>
          <div class="pill" data-category="citrus">Citrus / ಸಿಟ್ರಸ್</div>
          <div class="pill" data-category="sweet">Sweet / ಸಿಹಿ</div>
          <div class="pill" data-category="woody">Woody / ಅರಣ್ಯ</div>
          <div class="pill" data-category="marine">Marine / ಸಾಗರ</div>
          <div class="pill" data-category="airfreshener">Misc / ಇತರೆ</div>
        </div>
      </div>
    </div>
    <div class="product-grid" id="productGrid"></div>

  </div>
  <script>
    // ============================================================
    // JAVASCRIPT – Unified with bakery.php design + images
    // ============================================================
    (function() {
      // ----- PRODUCT DATA FROM PHP -----
      const productsData = <?php
                            $jsProducts = [];
                            foreach ($products as $p) {
                              // Determine subcategory if not set
                              $subcat = $p['subcategory'] ?? 'airfreshener';
                              // Build button text
                              $buttonText = '➕ Add to cart';
                              if (stripos($p['name'], 'rose') !== false || stripos($p['name'], 'floral') !== false) $buttonText = '➕ Floral scent';
                              elseif (stripos($p['name'], 'citrus') !== false || stripos($p['name'], 'lemon') !== false) $buttonText = '➕ Citrus fresh';
                              elseif (stripos($p['name'], 'sweet') !== false || stripos($p['name'], 'vanilla') !== false) $buttonText = '➕ Sweet aroma';
                              elseif (stripos($p['name'], 'wood') !== false || stripos($p['name'], 'sandal') !== false) $buttonText = '➕ Woody scent';
                              else $buttonText = '➕ Air freshener';
                              // Image URL (already normalized to root-relative)
                              $img = $p['image_url'] ?? '';
                              $jsProducts[] = [
                                'id' => $p['id'],
                                'nameEn' => $p['name'],
                                'kn' => $p['kn_name'] ?? $p['name'],
                                'category' => $subcat,
                                'price' => (float)$p['price'],
                                'mrp' => (float)($p['mrp'] ?? $p['price']),
                                'image' => $img,
                                'description' => $p['desc_en'] ?? '',
                                'buttonText' => $buttonText,
                                'source' => $p['source'] ?? 'KSR GURU',
                                'stock' => (int)($p['stock'] ?? 10),
                                'isFeatured' => (int)($p['is_featured'] ?? 0),
                                'emoji' => $p['emoji'] ?? '🌬️'
                              ];
                            }
                            echo json_encode($jsProducts);
                            ?>;
      console.log('✅ Loaded ' + productsData.length + ' air freshener products');
      // ----- CART STATE -----
      let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
      let activeCategory = 'all';
      let searchTerm = '';
      const productGridEl = document.getElementById('productGrid');
      const cartCountSpan = document.getElementById('cartCount');
      const searchInput = document.getElementById('searchInput');
      const filterPills = document.querySelectorAll('.pill');
      // ----- HELPERS -----
      function updateCartBadge() {
        const totalQty = cart.reduce((sum, i) => sum + (i.qty || 0), 0);
        cartCountSpan.innerText = totalQty;
        localStorage.setItem('glowCartNew', JSON.stringify(cart));
      }

      function addToCart(productId) {
        let product = null;
        if (typeof products !== 'undefined' && Array.isArray(products)) {
          product = products.find(p => p.id === productId);
        } else if (typeof productsData !== 'undefined' && Array.isArray(productsData)) {
          product = productsData.find(p => p.id === productId);
        } else if (typeof productDatabase !== 'undefined' && Array.isArray(productDatabase)) {
          product = productDatabase.find(p => p.id === productId);
        }
        const formData = new FormData();
        formData.append('id', productId);
        formData.append('qty', 1);
        if (product) {
          formData.append('name', product.name || product.nameEn || '');
          formData.append('kn', product.kn || product.nameKn || product.knName || product.kn_name || '');
          formData.append('price', product.price || 0);
          formData.append('image', product.image || product.imageUrl || product.image_url || '');
        }
        fetch('../cart/add_cart.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              if (typeof cart !== "undefined") {
                cart = data.cart || cart;
              } else {
                window.cart = data.cart;
              }
              const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge, #cartBadgeCount");
              badges.forEach(b => {
                b.innerText = data.cart ? data.cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
              });
              if (typeof window.openCartDrawer === "function") {
                window.openCartDrawer();
              } else if (typeof openCart === "function") {
                openCart();
              }
            } else {
              alert(data.error || 'Failed to add product to cart');
            }
          })
          .catch(err => {
            console.error(err);
            alert("Error adding product to cart.");
          });
      }
      // ----- RENDER PRODUCTS (with images) -----
      function renderProducts() {
        const filtered = productsData.filter(p => {
          if (activeCategory !== 'all' && p.category !== activeCategory) return false;
          const term = searchTerm.trim().toLowerCase();
          if (term) {
            return p.nameEn.toLowerCase().includes(term) || p.kn.toLowerCase().includes(term);
          }
          return true;
        });
        if (filtered.length === 0) {
          productGridEl.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:3rem; color:#bb8a69;">✨ No items found / ಯಾವುದೇ ಉತ್ಪನ್ನ ಇಲ್ಲ</div>`;
          return;
        }
        productGridEl.innerHTML = filtered.map(p => {
          const hasOffer = p.isFeatured == 1;
          const sponsoredClass = hasOffer ? 'sponsored' : 'trusted';
          const sponsoredLabel = hasOffer ? 'Sponsored' : 'Trusted';
          // Image HTML – use <img> if image exists, else fallback to emoji
          let imgHtml = '';
          if (p.image && p.image.trim() !== '') {
            imgHtml = `<img src="${p.image}" alt="" onerror="this.parentElement.textContent='${p.emoji}'">`;
          } else {
            imgHtml = `<span style="font-size:3rem;">${p.emoji}</span>`;
          }
          return `
        <div class="product-card" data-id="${p.id}">
          ${hasOffer ? `<div class="product-badge">OFFER</div>` : ''}
          <div class="product-img">${imgHtml}</div>
          <div class="product-title">${p.nameEn}</div>
          <div class="product-title-kn">${p.kn}</div>
          <div class="product-description"><i class="fa-regular fa-note-sticky"></i> ${p.description}</div>
          <div class="price-row">
            <span class="price">₹${p.price.toFixed(2)} <small>inr</small></span>
            ${p.mrp > p.price ? `<span style="font-size:0.85rem;color:#b09e8f;text-decoration:line-through;margin-left:6px;">₹${p.mrp.toFixed(2)}</span>` : ''}
          </div>
          <div class="product-source-row">
            <span>${p.source}</span>
            <span class="ad-tag ${sponsoredClass}">${sponsoredLabel}</span>
            <span>Stock: <span class="stock-tag">${p.stock}</span></span>
          </div>
          <button class="descriptive-cart-btn" data-id="${p.id}" data-add>
            <i class="fa-solid fa-seedling"></i>
            <span>${p.buttonText}</span>
          </button>
        </div>
      `;
        }).join('');
        // Add to cart buttons
        document.querySelectorAll('.descriptive-cart-btn').forEach(btn => {
          btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const pid = parseInt(this.dataset.id);
            addToCart(pid);
            const orig = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check"></i> Added';
            setTimeout(() => this.innerHTML = orig, 800);
            updateCartBadge();
          });
        });
      }
      // ----- EVENT BINDING -----
      filterPills.forEach(pill => {
        pill.addEventListener('click', () => {
          filterPills.forEach(p => p.classList.remove('active'));
          pill.classList.add('active');
          activeCategory = pill.dataset.category;
          renderProducts();
        });
      });
      searchInput.addEventListener('input', e => {
        searchTerm = e.target.value;
        renderProducts();
      });
      // ----- INIT -----
      renderProducts();
      updateCartBadge();
      console.log('🌬️ Air freshener page loaded with images support!');
      // Removed inline cart block in favor of unified global cart drawer
    })();
  </script>


  <script>
    /* Injected Cart Drawer JavaScript Handlers */
    function openCart() {
      const sidebar = document.getElementById('cartSidebar');
      const overlay = document.getElementById('overlay');
      if (sidebar) sidebar.classList.add('open');
      if (overlay) overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
      if (typeof renderCartItems === "function") {
        renderCartItems();
      }
    }

    function closeCart() {
      const sidebar = document.getElementById('cartSidebar');
      const overlay = document.getElementById('overlay');
      if (sidebar) sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('active');
      document.body.style.overflow = '';
    }

    function updateCartQty(productId, qty) {
      const formData = new FormData();
      formData.append('id', productId);
      formData.append('qty', qty);
      fetch('../index.php?ajax=update_cart', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            if (typeof cart !== "undefined") {
              cart = data.cart;
            }
            const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
            badges.forEach(b => {
              b.innerText = data.cart ? data.cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
            });
            if (typeof renderCartItems === "function") {
              renderCartItems();
            }
          }
        })
        .catch(console.error);
    }

    function removeFromCart(productId) {
      const formData = new FormData();
      formData.append('id', productId);
      fetch('../index.php?ajax=remove_cart', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            if (typeof cart !== "undefined") {
              cart = data.cart;
            }
            const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
            badges.forEach(b => {
              b.innerText = data.cart ? data.cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
            });
            if (typeof renderCartItems === "function") {
              renderCartItems();
            }
          }
        })
        .catch(console.error);
    }

    function renderCartItems() {
      const container = document.getElementById('cartItems');
      const totalSpan = document.querySelector('.total-price');
      if (!container) return;
      const currentCart = (typeof cart !== "undefined") ? cart : [];
      if (!currentCart.length) {
        container.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-basket"></i><p>Your cart is empty</p></div>`;
        if (totalSpan) totalSpan.innerText = '₹0';
        return;
      }
      let total = 0;
      let html = '';
      currentCart.forEach(item => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        const itemImgSrc = item.image || '';
        const imgHtml = itemImgSrc ?
          `<img src="${itemImgSrc}" alt="${item.name}" onerror="this.outerHTML='<span class=\'emoji-fallback\'>🥣</span>'" style="width:100%; height:100%; object-fit:cover; border-radius:50%;" />` :
          `<span class="emoji-fallback">🥣</span>`;
        html += `
        <div class="cart-item" data-id="${item.id}">
          <div class="cart-item-image">${imgHtml}</div>
          <div class="cart-item-details">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-price">₹${item.price} × ${item.qty} = ₹${itemTotal.toFixed(2)}</div>
            <div class="cart-item-actions">
              <button onclick="window.changeQty('${item.id}', ${item.qty - 1})">-</button>
              <span>${item.qty}</span>
              <button onclick="window.changeQty('${item.id}', ${item.qty + 1})">+</button>
              <button onclick="window.removeItem('${item.id}')"><i class="fas fa-trash-alt"></i></button>
            </div>
          </div>
        </div>
      `;
      });
      container.innerHTML = html;
      if (totalSpan) totalSpan.innerText = `₹${total.toFixed(2)}`;
    }

    function setupCartSidebarEvents() {
      const closeBtn = document.getElementById('closeCart');
      const overlay = document.getElementById('overlay');
      const checkoutBtn = document.getElementById('checkoutBtn');
      const continueBtn = document.getElementById('continueShopping');
      if (closeBtn) closeBtn.addEventListener('click', closeCart);
      if (overlay) overlay.addEventListener('click', closeCart);
      if (continueBtn) continueBtn.addEventListener('click', closeCart);
      if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function() {
          const currentCart = (typeof cart !== "undefined") ? cart : [];
          if (currentCart.length) {
            window.location.href = '../checkout/checkout.php';
          } else {
            alert('Cart is empty');
          }
        });
      }
      const headerCartBtns = document.querySelectorAll('.cart-link, #cartLink, #cartInfoIcon');
      headerCartBtns.forEach(btn => {
        const clone = btn.cloneNode(true);
        btn.parentNode.replaceChild(clone, btn);
        clone.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          openCart();
        });
      });
      const legacyOverlay = document.getElementById('cartOverlay');
      if (legacyOverlay) {
        legacyOverlay.style.display = 'none';
        legacyOverlay.style.pointerEvents = 'none';
      }
      window.changeQty = updateCartQty;
      window.removeItem = removeFromCart;
      fetch('../index.php?ajax=cart')
        .then(res => res.json())
        .then(data => {
          if (data && typeof cart !== "undefined") {
            cart = data;
            const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
            badges.forEach(b => {
              b.innerText = cart ? cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
            });
          }
        }).catch(console.error);
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', setupCartSidebarEvents);
    } else {
      setupCartSidebarEvents();
    }
  </script>
  <!-- Injected Cart Sidebar Markup -->
  <div class="cart-sidebar" id="cartSidebar">
    <div class="cart-header">
      <h3><i class="fas fa-shopping-cart"></i> Your Cart</h3>
      <button class="close-cart" id="closeCart"><i class="fas fa-times"></i></button>
    </div>
    <div class="cart-items" id="cartItems"></div>
    <div class="cart-footer">
      <div class="cart-total"><span>Total</span><span class="total-price">₹0</span></div>
      <button class="checkout-btn" id="checkoutBtn">Proceed to Checkout</button>
      <button class="continue-shopping" id="continueShopping">Continue Shopping</button>
    </div>
  </div>
  <div class="overlay" id="overlay"></div>

  <?php include __DIR__ . '/../includes/user-footer.php'; ?>
  <script src="sorting.js"></script>
</body>

</html>