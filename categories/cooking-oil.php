<?php
// ============================================================
// cooking-oil.php - Dynamic Cooking Oil product page
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
      echo json_encode(getCookingOilProducts($search));
      exit;
    case 'cart':
      echo json_encode($_SESSION['cart']);
      exit;
    case 'add_cart':
      $id = (int) ($_POST['id'] ?? 0);
      if ($id) {
        $product = getProductById($id);
        if (!$product) {
          // Fallback to static data
          $fallback = getFallbackCookingOilProducts();
          foreach ($fallback as $fp) {
            if ($fp['id'] == $id) {
              $product = [
                'id' => $fp['id'],
                'name' => $fp['name'],
                'kn_name' => $fp['kn_name'] ?? $fp['name'],
                'price' => $fp['variants'][0]['price'] ?? 0,
                'icon' => 'fa-bottle-droplet'
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
              'icon' => $product['icon'] ?? 'fa-bottle-droplet'
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
// HELPER: Normalize image path (Windows → web-relative)
// ============================================================
if (!function_exists('normalizeImagePath')) {
  function normalizeImagePath(?string $rawPath): string
  {
    return ksrNormalizeFlatImage($rawPath);
  }
}
// ============================================================
// FETCH COOKING OIL PRODUCTS - PDO version
// ============================================================
function getCookingOilProducts(string $search = ''): array
{
  try {
    $pdo = getDBConnection();
    // Try to check column existence dynamically
    $schema = getProductSchemaInfo();
    $descCol = $schema["desc_col"];
    $imgCol = $schema["img_col"];
    $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                       c.name AS category_name, c.kn_name AS category_kn,
                       {$descCol} AS description,
                       {$imgCol} AS image
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE (c.slug = 'cooking-oil' OR c.id = 'cooking-oil' OR c.name LIKE '%Oil%' OR c.name LIKE '%oil%')";
    $params = [];
    if ($search !== '') {
      $sql .= " AND (p.name LIKE ? OR p.kn_name LIKE ? OR {$descCol} LIKE ?)";
      $searchWild = '%' . $search . '%';
      $params[] = $searchWild;
      $params[] = $searchWild;
      $params[] = $searchWild;
    }
    $sql .= " ORDER BY p.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
      $row['description'] = $row['description'] ?? 'Premium cooking oil';
      if (!empty($row['image'])) {
        $row['image'] = normalizeImagePath($row['image']);
      }
      // Build variants from price and weight if available (you can store variants as JSON in DB)
      // For now, we'll create a default variant
      $row['variants'] = [
        ['label' => '1 Litre', 'price' => $row['price']]
      ];
    }
    return $rows;
  } catch (Throwable $e) {
    return [];
  }
}
// ============================================================
// FALLBACK: If no products from DB, load static data from JSON
// ============================================================
function getFallbackCookingOilProducts(): array
{
  static $fallback = null;
  if ($fallback !== null) {
    return $fallback;
  }
  $jsonPath = __DIR__ . '/cooking-oil-fallback.json';
  if (file_exists($jsonPath)) {
    $fallback = json_decode(file_get_contents($jsonPath), true);
    if (is_array($fallback)) {
      return $fallback;
    }
  }
  $json = '[
  {"id":1,"name":"Disano Extra Virgin Olive Oil","nameKn":"ಡಿಸಾನೊ ಎಕ್ಸ್ಟ್ರಾ ವರ್ಜಿನ್ ಆಲಿವ್ ಆಯಿಲ್","category":"Olive Oil","description":"Premium extra virgin olive oil for cooking and dressings.","imageUrl":"images\\\\Disano Extra Virgin Olive Oil 250ml.jpg","weights":[{"label":"250 ml","price":281},{"label":"500 ml","price":551},{"label":"1 L","price":1051},{"label":"5 L","price":4701}]},
  {"id":2,"name":"Figaro Olive Oil","nameKn":"ಫಿಗಾರೊ ಆಲಿವ್ ಆಯಿಲ್","category":"Olive Oil","description":"Premium extra virgin olive oil for cooking and dressings.","imageUrl":"images\\\\Figaro Olive Oil.jpg","weights":[{"label":"100 ml","price":44},{"label":"200 ml","price":82},{"label":"300 ml","price":187},{"label":"500 ml","price":205},{"label":"600 ml","price":317},{"label":"1 L","price":397}]},
  {"id":3,"name":"Borges Extra Virgin Olive Oil","nameKn":"ಬೋರ್ಗೆಸ್ ಎಕ್ಸ್ಟ್ರಾ ವರ್ಜಿನ್ ಆಲಿವ್ ಆಯಿಲ್","category":"Olive Oil","description":"Premium extra virgin olive oil for cooking and dressings.","imageUrl":"images\\\\Borges Extra Virgin Olive Oil 530.jpg","weights":[{"label":"100 ml","price":46},{"label":"200 ml","price":87},{"label":"300 ml","price":192},{"label":"500 ml","price":382},{"label":"600 ml","price":502},{"label":"1 L","price":1052}]},
  {"id":4,"name":"Leonardo Olive Pomace Oil","nameKn":"ಲಿಯೊನಾರ್ಡೊ ಆಲಿವ್ ಪೊಮಾಸ್ ಆಯಿಲ್","category":"Olive Oil","description":"Premium extra virgin olive oil for cooking and dressings.","imageUrl":"images\\\\Leonardo Olive Pomace Oil 2400.jpg","weights":[{"label":"100 ml","price":41},{"label":"200 ml","price":77},{"label":"300 ml","price":177},{"label":"500 ml","price":192},{"label":"600 ml","price":287},{"label":"1 L","price":367}]},
  {"id":5,"name":"Del Monte Extra Virgin Olive Oil","nameKn":"ಡೆಲ್ ಮಾಂಟೆ ಎಕ್ಸ್ಟ್ರಾ ವರ್ಜಿನ್ ಆಲಿವ್ ಆಯಿಲ್","category":"Olive Oil","description":"Premium extra virgin olive oil for cooking and dressings.","imageUrl":"images\\\\Del Monte Extra Virgin Olive Oil 650.jpg","weights":[{"label":"100 ml","price":44},{"label":"200 ml","price":84},{"label":"300 ml","price":186},{"label":"500 ml","price":207},{"label":"600 ml","price":315},{"label":"1 L","price":395}]},
  {"id":6,"name":"Oleev Active Olive Oil","nameKn":"ಒಲೀವ್ ಆಕ್ಟಿವ್ ಆಲಿವ್ ಆಯಿಲ್","category":"Olive Oil","description":"Premium extra virgin olive oil for cooking and dressings.","imageUrl":"images\\\\Oleev Active Olive Oil 350.jpg","weights":[{"label":"100 ml","price":39},{"label":"200 ml","price":76},{"label":"300 ml","price":174},{"label":"500 ml","price":192},{"label":"600 ml","price":290},{"label":"1 L","price":365}]},
  {"id":8,"name":"Borges Classic Olive Oil","nameKn":"ಬೋರ್ಗೆಸ್ ಕ್ಲಾಸಿಕ್ ಆಲಿವ್ ಆಯಿಲ್","category":"Olive Oil","description":"Premium extra virgin olive oil for cooking and dressings.","imageUrl":"images\\\\Borges Classic Olive Oil 300.jpg","weights":[{"label":"100 ml","price":46},{"label":"200 ml","price":87},{"label":"300 ml","price":192},{"label":"500 ml","price":212},{"label":"600 ml","price":327}]},
  {"id":12,"name":"Colavita Extra Virgin Olive Oil","nameKn":"ಕೊಲಾವಿಟಾ ಎಕ್ಸ್ಟ್ರಾ ವರ್ಜಿನ್ ಆಲಿವ್ ಆಯಿಲ್","category":"Olive Oil","description":"Premium extra virgin olive oil for cooking and dressings.","imageUrl":"images\\\\Colavita Extra Virgin Olive Oil 2300.jpg","weights":[{"label":"500 ml","price":2302}]},
  {"id":28,"name":"Parachute Coconut Oil","nameKn":"ಪ್ಯಾರಾಶೂಟ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Parachute Coconut Oil.jpg","weights":[{"label":"100 ml","price":53},{"label":"200 ml","price":101},{"label":"300 ml","price":144},{"label":"500 ml","price":202},{"label":"600 ml","price":271},{"label":"1 L","price":377}]},
  {"id":29,"name":"KLF Nirmal Coconut Oil","nameKn":"ಕೆಎಲ್ಎಫ್ ನಿರ್ಮಲ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\KLF Nirmal Coconut Oil.jpg","weights":[{"label":"100 ml","price":33},{"label":"250 ml","price":132},{"label":"500 ml","price":322},{"label":"1 L","price":524}]},
  {"id":30,"name":"Coco Soul Virgin Coconut Oil","nameKn":"ಕೋಕೋ ಸೋಲ್ ವರ್ಜಿನ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Coco Soul Virgin Coconut Oil.jpg","weights":[{"label":"250 ml","price":255},{"label":"500 ml","price":462},{"label":"1 L","price":876}]},
  {"id":31,"name":"Max Care Virgin Coconut Oil","nameKn":"ಮ್ಯಾಕ್ಸ್ ಕೇರ್ ವರ್ಜಿನ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Max Care Virgin Coconut Oil.jpg","weights":[{"label":"100 ml","price":48},{"label":"200 ml","price":92},{"label":"300 ml","price":137},{"label":"500 ml","price":212},{"label":"600 ml","price":252},{"label":"1 L","price":402}]},
  {"id":32,"name":"Kerala Ayurveda Coconut Oil","nameKn":"ಕೇರಳ ಆಯುರ್ವೇದ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Kerala Ayurveda Coconut Oil.jpg","weights":[{"label":"100 ml","price":41},{"label":"200 ml","price":72},{"label":"300 ml","price":107},{"label":"500 ml","price":167},{"label":"600 ml","price":202},{"label":"1 L","price":352}]},
  {"id":33,"name":"Dabur Anmol Coconut Oil","nameKn":"ಡಾಬರ್ ಅನ್ಮೋಲ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Dabur Anmol Coconut Oil.jpg","weights":[{"label":"100 ml","price":42},{"label":"200 ml","price":64},{"label":"300 ml","price":97},{"label":"500 ml","price":174},{"label":"600 ml","price":277},{"label":"1 L","price":522}]},
  {"id":34,"name":"Patanjali Coconut Oil","nameKn":"ಪತಂಜಲಿ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Patanjali Coconut Oil.jpg","weights":[{"label":"100 ml","price":49},{"label":"200 ml","price":104},{"label":"300 ml","price":178},{"label":"500 ml","price":280},{"label":"600 ml","price":302},{"label":"1 L","price":586}]},
  {"id":35,"name":"Marico Hair & Care Coconut Oil","nameKn":"ಮಾರಿಕೊ ಹೇರ್ & ಕೇರ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Marico Hair & Care Coconut Oil.jpg","weights":[{"label":"100 ml","price":64},{"label":"200 ml","price":113},{"label":"300 ml","price":175},{"label":"500 ml","price":274},{"label":"600 ml","price":302},{"label":"1 L","price":451}]},
  {"id":36,"name":"Parachute Coconut Oil Jar","nameKn":"ಪ್ಯಾರಾಶೂಟ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ ಜಾರ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Parachute Coconut Oil Jar.jpg","weights":[{"label":"100 ml","price":60},{"label":"200 ml","price":77},{"label":"300 ml","price":177},{"label":"500 ml","price":264},{"label":"1 L","price":537}]},
  {"id":37,"name":"KLF Nirmal Coconut Oil Jar","nameKn":"ಕೆಎಲ್ಎಫ್ ನಿರ್ಮಲ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ ಜಾರ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\KLF Nirmal Coconut Oil Jar.jpg","weights":[{"label":"100 ml","price":35},{"label":"200 ml","price":64},{"label":"300 ml","price":97},{"label":"500 ml","price":157},{"label":"600 ml","price":192},{"label":"1 L","price":322}]},
  {"id":38,"name":"Coco Soul Cold Pressed Coconut Oil","nameKn":"ಕೋಕೋ ಸೋಲ್ ಕೋಲ್ಡ್ ಪ್ರೆಸ್ಡ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Coco Soul Cold Pressed Coconut Oil.jpg","weights":[{"label":"100 ml","price":57},{"label":"200 ml","price":107},{"label":"300 ml","price":157},{"label":"500 ml","price":242},{"label":"600 ml","price":292},{"label":"1 L","price":467}]},
  {"id":39,"name":"Max Care Coconut Oil Jar","nameKn":"ಮ್ಯಾಕ್ಸ್ ಕೇರ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ ಜಾರ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Max Care Coconut Oil Jar.jpg","weights":[{"label":"100 ml","price":35},{"label":"200 ml","price":62},{"label":"300 ml","price":97},{"label":"500 ml","price":152},{"label":"600 ml","price":187},{"label":"1 L","price":312}]},
  {"id":40,"name":"Kerala Ayurveda Virgin Coconut Oil","nameKn":"ಕೇರಳ ಆಯುರ್ವೇದ ವರ್ಜಿನ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Kerala Ayurveda Virgin Coconut Oil.jpg","weights":[{"label":"100 ml","price":52},{"label":"200 ml","price":97},{"label":"300 ml","price":142},{"label":"500 ml","price":217},{"label":"600 ml","price":262},{"label":"1 L","price":412}]},
  {"id":41,"name":"Dabur Coconut Oil Jar","nameKn":"ಡಾಬರ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ ಜಾರ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Dabur Coconut Oil Jar.jpg","weights":[{"label":"100 ml","price":35},{"label":"200 ml","price":64},{"label":"300 ml","price":97},{"label":"500 ml","price":157},{"label":"600 ml","price":192},{"label":"1 L","price":322}]},
  {"id":42,"name":"Patanjali Coconut Oil Jar","nameKn":"ಪತಂಜಲಿ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ ಜಾರ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Patanjali Coconut Oil Jar.jpg","weights":[{"label":"100 ml","price":33},{"label":"200 ml","price":62},{"label":"300 ml","price":92},{"label":"500 ml","price":152},{"label":"600 ml","price":182},{"label":"1 L","price":312}]},
  {"id":43,"name":"Indus Valley Virgin Coconut Oil","nameKn":"ಇಂಡಸ್ ವ್ಯಾಲಿ ವರ್ಜಿನ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Indus Valley Virgin Coconut Oil.jpg","weights":[{"label":"100 ml","price":55},{"label":"200 ml","price":102},{"label":"300 ml","price":152},{"label":"500 ml","price":232},{"label":"600 ml","price":282},{"label":"1 L","price":437}]},
  {"id":44,"name":"Ayurvedic Anti-Hair Fall Oil","nameKn":"ಆಯುರ್ವೇದಿಕ್ ಆಂಟಿ-ಹೇರ್ ಫಾಲ್ ಆಯಿಲ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Ayurvedic Anti-Hair Fall Oil 220.jpg","weights":[{"label":"100 ml","price":53},{"label":"200 ml","price":101},{"label":"300 ml","price":144},{"label":"500 ml","price":202},{"label":"600 ml","price":271},{"label":"1 L","price":377}]},
  {"id":45,"name":"Ayurvedic Hair Oil","nameKn":"ಆಯುರ್ವೇದಿಕ್ ಹೇರ್ ಆಯಿಲ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Ayurvedic Hair Oil 190.jpg","weights":[{"label":"100 ml","price":53},{"label":"200 ml","price":101},{"label":"300 ml","price":144},{"label":"500 ml","price":202},{"label":"600 ml","price":271},{"label":"1 L","price":377}]},
  {"id":46,"name":"Bajaj 100% Pure Castor Oil","nameKn":"ಬಜಾಜ್ 100% ಶುದ್ಧ ಕ್ಯಾಸ್ಟರ್ ಆಯಿಲ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Bajaj 100% Pure Castor Oil 162-190.jpg","weights":[{"label":"100 ml","price":53},{"label":"200 ml","price":101},{"label":"300 ml","price":144},{"label":"500 ml","price":202},{"label":"600 ml","price":271},{"label":"1 L","price":377}]},
  {"id":47,"name":"Bhringraj Hair Oil kama","nameKn":"ಭೃಂಗರಾಜ್ ಹೇರ್ ಆಯಿಲ್ ಕಾಮ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Bhringraj Hair Oil kama.jpg","weights":[{"label":"100 ml","price":53},{"label":"200 ml","price":101},{"label":"300 ml","price":144},{"label":"500 ml","price":202},{"label":"600 ml","price":271},{"label":"1 L","price":377}]},
  {"id":48,"name":"Brahmi Hair Oil","nameKn":"ಬ್ರಾಹ್ಮಿ ಹೇರ್ ಆಯಿಲ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Brahmi Hair Oil 200ml 232.jpg","weights":[{"label":"200 ml","price":242}]},
  {"id":49,"name":"Brahmi Hair Oil","nameKn":"ಬ್ರಾಹ್ಮಿ ಹೇರ್ ಆಯಿಲ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Brahmi Hair Oil 590.jpg","weights":[{"label":"500 ml","price":602}]},
  {"id":50,"name":"Brillare Tea Tree Hair Oil","nameKn":"ಬ್ರಿಲ್ಲಾರೆ ಟೀ ಟ್ರೀ ಹೇರ್ ಆಯಿಲ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Brillare Tea Tree Hair Oil 224-350.jpg","weights":[{"label":"100 ml","price":83},{"label":"200 ml","price":192},{"label":"300 ml","price":226},{"label":"500 ml","price":392},{"label":"600 ml","price":271},{"label":"1 L","price":377}]},
  {"id":51,"name":"Cooling Brahmi Amla Oil","nameKn":"ಕೂಲಿಂಗ್ ಬ್ರಾಹ್ಮಿ ಆಮ್ಲಾ ಆಯಿಲ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Cooling Brahmi Amla Oil 396.jpg","weights":[{"label":"500 ml","price":402}]},
  {"id":52,"name":"Dabur Castor Oil","nameKn":"ಡಾಬರ್ ಕ್ಯಾಸ್ಟರ್ ಆಯಿಲ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Dabur Castor Oil 215-270.jpg","weights":[{"label":"100 ml","price":42},{"label":"200 ml","price":64},{"label":"300 ml","price":97},{"label":"500 ml","price":174},{"label":"600 ml","price":277},{"label":"1 L","price":522}]},
  {"id":53,"name":"Dabur Castor Oil","nameKn":"ಡಾಬರ್ ಕ್ಯಾಸ್ಟರ್ ಆಯಿಲ್","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Dabur Castor Oil 240.jpg","weights":[{"label":"100 ml","price":42},{"label":"200 ml","price":64},{"label":"300 ml","price":97},{"label":"500 ml","price":174},{"label":"600 ml","price":277},{"label":"1 L","price":522}]},
  {"id":54,"name":"dabur Cool King Thanda Tel Hair Oil","nameKn":"ಡಾಬರ್ ಕೂಲ್ ಕಿಂಗ್ ಥಂಡಾ ತೇಲ್ ಹೇರ್ ಆಯಿಲ್","category":"Cooling Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\dabur Cool King Thanda Tel Hair Oil 266-146.jpg","weights":[{"label":"100 ml","price":42},{"label":"200 ml","price":64},{"label":"300 ml","price":97},{"label":"500 ml","price":174},{"label":"600 ml","price":277},{"label":"1 L","price":522}]},
  {"id":55,"name":"Dabur Vatika Imported Garlic Enriched Hair Oil","nameKn":"ಡಾಬರ್ ವಾಟಿಕಾ ಇಂಪೋರ್ಟೆಡ್ ಗಾರ್ಲಿಕ್ ಎನ್ರಿಚ್ಡ್ ಹೇರ್ ಆಯಿಲ್","category":"Cooling Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Dabur Vatika Imported Garlic Enriched Hair Oil 254.jpg","weights":[{"label":"100 ml","price":42},{"label":"200 ml","price":64},{"label":"300 ml","price":97},{"label":"500 ml","price":174},{"label":"600 ml","price":277},{"label":"1 L","price":522}]},
  {"id":115,"name":"Dabur Amla Hair Oil","nameKn":"ಡಾಬರ್ ವಾಟಿಕಾ ಇಂಪೋರ್ಟೆಡ್ ಗಾರ್ಲಿಕ್ ಎನ್ರಿಚ್ಡ್ ಹೇರ್ ಆಯಿಲ್","category":"Cooling Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Dabur Amla Hair Oil 102.jpg","weights":[{"label":"100 ml","price":42},{"label":"200 ml","price":64},{"label":"300 ml","price":97},{"label":"500 ml","price":174},{"label":"600 ml","price":277},{"label":"1 L","price":522}]},
  {"id":56,"name":"DR. Rashel Onion Black Seed Hair Oil","nameKn":"ಡಾ. ರಾಶೆಲ್ ಈರುಳ್ಳಿ ಬ್ಲ್ಯಾಕ್ ಸೀಡ್ ಹೇರ್ ಆಯಿಲ್","category":"Cooling Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\DR. Rashel Onion Black Seed Hair Oil 160.jpg","weights":[{"label":"100 ml","price":42},{"label":"200 ml","price":64},{"label":"300 ml","price":97},{"label":"500 ml","price":174},{"label":"600 ml","price":277},{"label":"1 L","price":522}]},
  {"id":57,"name":"Emami Kesh King Ayurvedic Hair Oil","nameKn":"ಎಮಾಮಿ ಕೇಶ್ ಕಿಂಗ್ ಆಯುರ್ವೇದಿಕ್ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Emami Kesh King Ayurvedic Hair Oil 100 ml 170.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":34},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":167}]},
  {"id":58,"name":"Fortune Vegetable Oil","nameKn":"ಫಾರ್ಚೂನ್ ವೆಜಿಟೇಬಲ್ ಆಯಿಲ್","category":"Vegetable Oil","description":"Refined vegetable oil for everyday cooking.","imageUrl":"images\\\\Fortune Vegetable Oil.jpg","weights":[{"label":"100 ml","price":18},{"label":"200 ml","price":30},{"label":"300 ml","price":44},{"label":"500 ml","price":72},{"label":"600 ml","price":87},{"label":"1 L","price":147}]},
  {"id":59,"name":"Dhara Vegetable Oil","nameKn":"ಧಾರಾ ವೆಜಿಟೇಬಲ್ ಆಯಿಲ್","category":"Vegetable Oil","description":"Refined vegetable oil for everyday cooking.","imageUrl":"images\\\\Dhara Vegetable Oil.jpg","weights":[{"label":"100 ml","price":17},{"label":"200 ml","price":28},{"label":"300 ml","price":42},{"label":"500 ml","price":70},{"label":"600 ml","price":84},{"label":"1 L","price":142}]},
  {"id":60,"name":"Ruchi Gold Vegetable Oil","nameKn":"ರುಚಿ ಗೋಲ್ಡ್ ವೆಜಿಟೇಬಲ್ ಆಯಿಲ್","category":"Vegetable Oil","description":"Refined vegetable oil for everyday cooking.","imageUrl":"images\\\\Ruchi Gold Vegetable Oil.jpg","weights":[{"label":"100 ml","price":16},{"label":"200 ml","price":27},{"label":"300 ml","price":40},{"label":"500 ml","price":67},{"label":"600 ml","price":80},{"label":"1 L","price":132}]},
  {"id":61,"name":"Gemini Vegetable Oil","nameKn":"ಜೆಮಿನಿ ವೆಜಿಟೇಬಲ್ ಆಯಿಲ್","category":"Vegetable Oil","description":"Refined vegetable oil for everyday cooking.","imageUrl":"images\\\\Gemini Vegetable Oil.jpg","weights":[{"label":"100 ml","price":17},{"label":"200 ml","price":29},{"label":"300 ml","price":42},{"label":"500 ml","price":70},{"label":"600 ml","price":82},{"label":"1 L","price":137}]},
  {"id":62,"name":"Sundrop Vegetable Oil","nameKn":"ಸನ್ಡ್ರಾಪ್ ವೆಜಿಟೇಬಲ್ ಆಯಿಲ್","category":"Vegetable Oil","description":"Refined vegetable oil for everyday cooking.","imageUrl":"images\\\\Sundrop Vegetable Oil.jpg","weights":[{"label":"100 ml","price":19},{"label":"200 ml","price":32},{"label":"300 ml","price":47},{"label":"500 ml","price":77},{"label":"600 ml","price":92},{"label":"1 L","price":157}]},
  {"id":63,"name":"Nature Fresh Vegetable Oil","nameKn":"ನೇಚರ್ ಫ್ರೆಶ್ ವೆಜಿಟೇಬಲ್ ಆಯಿಲ್","category":"Vegetable Oil","description":"Refined vegetable oil for everyday cooking.","imageUrl":"images\\\\Nature Fresh Vegetable Oil.jpg","weights":[{"label":"100 ml","price":18},{"label":"200 ml","price":31},{"label":"300 ml","price":46},{"label":"500 ml","price":74},{"label":"600 ml","price":90},{"label":"1 L","price":152}]},
  {"id":64,"name":"Gold Winner Vegetable Oil","nameKn":"ಗೋಲ್ಡ್ ವಿನ್ನರ್ ವೆಜಿಟೇಬಲ್ ಆಯಿಲ್","category":"Vegetable Oil","description":"Refined vegetable oil for everyday cooking.","imageUrl":"images\\\\Gold Winner Vegetable Oil.jpg","weights":[{"label":"100 ml","price":17},{"label":"200 ml","price":30},{"label":"300 ml","price":44},{"label":"500 ml","price":72},{"label":"600 ml","price":87},{"label":"1 L","price":145}]},
  {"id":65,"name":"Freedom Vegetable Oil","nameKn":"ಫ್ರೀಡಮ್ ವೆಜಿಟೇಬಲ್ ಆಯಿಲ್","category":"Vegetable Oil","description":"Refined vegetable oil for everyday cooking.","imageUrl":"images\\\\Freedom Vegetable Oil.jpg","weights":[{"label":"100 ml","price":18},{"label":"200 ml","price":31},{"label":"300 ml","price":45},{"label":"500 ml","price":76},{"label":"600 ml","price":88},{"label":"1 L","price":153},{"label":"Litre (Bottle)","price":181}]},
  {"id":66,"name":"Fortune Kachi Ghani Mustard Oil","nameKn":"ಫಾರ್ಚೂನ್ ಕಚ್ಚಿ ಘಾನಿ ಸಾಸಿವೆ ಎಣ್ಣೆ","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Fortune Kachi Ghani Mustard Oil.jpg","weights":[{"label":"200 ml","price":42},{"label":"500 ml","price":94},{"label":"1 L","price":195}]},
  {"id":67,"name":"Fortunate Fenugreek Hair Oil","nameKn":"ಫಾರ್ಚುನೇಟ್ ಮೆಂತ್ಯ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Fortunate Fenugreek Hair Oil  130.jpg","weights":[{"label":"200 ml","price":42},{"label":"500 ml","price":94},{"label":"1 L","price":195}]},
  {"id":68,"name":"Dhara Kachi Ghani Mustard Oil","nameKn":"ಧಾರಾ ಕಚ್ಚಿ ಘಾನಿ ಸಾಸಿವೆ ಎಣ್ಣೆ","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Dhara Kachi Ghani Mustard Oil.jpg","weights":[{"label":"100 ml","price":18},{"label":"200 ml","price":30},{"label":"300 ml","price":44},{"label":"500 ml","price":72},{"label":"600 ml","price":87},{"label":"1 L","price":142}]},
  {"id":69,"name":"Patanjali Mustard Oil","nameKn":"ಪತಂಜಲಿ ಸಾಸಿವೆ ಎಣ್ಣೆ","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Patanjali Mustard Oil.jpg","weights":[{"label":"100 ml","price":22},{"label":"200 ml","price":38},{"label":"300 ml","price":57},{"label":"500 ml","price":92},{"label":"600 ml","price":112},{"label":"1 L","price":197}]},
  {"id":70,"name":"Emami Healthy & Tasty Mustard Oil","nameKn":"ಎಮಾಮಿ ಹೆಲ್ತಿ & ಟೇಸ್ಟಿ ಸಾಸಿವೆ ಎಣ್ಣೆ","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Emami Healthy & Tasty Mustard Oil.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":34},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":167}]},
  {"id":72,"name":"Nature Fresh Mustard Oil","nameKn":"ನೇಚರ್ ಫ್ರೆಶ್ ಸಾಸಿವೆ ಎಣ್ಣೆ","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Nature Fresh Mustard Oil.jpg","weights":[{"label":"100 ml","price":18},{"label":"200 ml","price":30},{"label":"300 ml","price":44},{"label":"500 ml","price":72},{"label":"600 ml","price":87},{"label":"1 L","price":147}]},
  {"id":73,"name":"24 Mantra Organic Mustard Oil","nameKn":"24 ಮಂತ್ರ ಆರ್ಗಾನಿಕ್ ಸಾಸಿವೆ ಎಣ್ಣೆ","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\24 Mantra Organic Mustard Oil.jpg","weights":[{"label":"100 ml","price":27},{"label":"200 ml","price":47},{"label":"300 ml","price":67},{"label":"500 ml","price":112},{"label":"600 ml","price":132},{"label":"1 L","price":242}]},
  {"id":75,"name":"Haironic Hair Science Garlic Hair Oil","nameKn":"ಹೇರೋನಿಕ್ ಹೇರ್ ಸೈನ್ಸ್ ಗಾರ್ಲಿಕ್ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Haironic Hair Science Garlic Hair Oil 130-400.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":34},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":167}]},
  {"id":76,"name":"Hibiscus Hair Oil","nameKn":"ಹಿಬಿಸ್ಕಸ್ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Hibiscus Hair Oil 25-499.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":34},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":167}]},
  {"id":77,"name":"Keo Karpin Hair Oil","nameKn":"ಕೆಯೋ ಕಾರ್ಪಿನ್ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Keo Karpin Hair Oil 49-55.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":34},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":167}]},
  {"id":78,"name":"Kesh King Ayurvedic And Hair Oil","nameKn":"ಕೇಶ್ ಕಿಂಗ್ ಆಯುರ್ವೇದಿಕ್ ಆಂಡ್ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Kesh King Ayurvedic And Hair Oil 330.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":34},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":167}]},
  {"id":79,"name":"Kesh King Herbal Ayurvedic Hair Oil For Hair Growth","nameKn":"ಕೇಶ್ ಕಿಂಗ್ ಹರ್ಬಲ್ ಆಯುರ್ವೇದಿಕ್ ಹೇರ್ ಆಯಿಲ್ ಫಾರ್ ಹೇರ್ ಗ್ರೋತ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Kesh King Herbal Ayurvedic Hair Oil For Hair Growth 100 Ml 200.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":34},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":167}]},
  {"id":80,"name":"Mamaearth Rosemary Hair Growth Oil","nameKn":"ಮಾಮಾಅರ್ಥ್ ರೋಸ್ಮೇರಿ ಹೇರ್ ಗ್ರೋತ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Mamaearth Rosemary Hair Growth Oil 236.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":34},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":167}]},
  {"id":81,"name":"La Pink Methi Dana 8-in-1 Hair Oil","nameKn":"ಲಾ ಪಿಂಕ್ ಮೆಂತ್ಯ ದಾನಾ 8-ಇನ್-1 ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\La Pink Methi Dana 8-in-1 Hair Oil 315-450.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":34},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":167}]},
  {"id":82,"name":"Moroccanoil Pure Argan Oil","nameKn":"ಮೊರಾಕನಾಯಿಲ್ ಪ್ಯೂರ್ ಆರ್ಗನ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Moroccanoil Pure Argan Oil 6660.jpg","weights":[{"label":"100 ml","price":1902},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":83,"name":"Moroccanoil Treatment","nameKn":"ಮೊರಾಕನಾಯಿಲ್ ಟ್ರೀಟ್ಮೆಂಟ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Moroccanoil Treatment 1650.jpg","weights":[{"label":"100 ml","price":1902},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":84,"name":"Navratna Ayurvedic Cool Oil","nameKn":"ನವರತ್ನ ಆಯುರ್ವೇದಿಕ್ ಕೂಲ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Navratna Ayurvedic Cool Oil 330.jpg","weights":[{"label":"100 ml","price":1902},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":85,"name":"Olvedic Garlic Essential Oil Pure & Organic Therapeutic Grade Undiluted For Hair","nameKn":"ಒಲ್ವೆಡಿಕ್ ಗಾರ್ಲಿಕ್ ಎಸೆನ್ಷಿಯಲ್ ಆಯಿಲ್ ಪ್ಯೂರ್ & ಆರ್ಗಾನಿಕ್ ಥೆರಪ್ಯೂಟಿಕ್ ಗ್ರೇಡ್ ಅನ್ಡೈಲ್ಯೂಟೆಡ್ ಫಾರ್ ಹೇರ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Olvedic Garlic Essential Oil Pure & Organic Therapeutic Grade Undiluted For Hair 100.jpg","weights":[{"label":"100 ml","price":1902},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":86,"name":"Onion Hair Oil","nameKn":"ಈರುಳ್ಳಿ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Onion Hair Oil 200.jpg","weights":[{"label":"100 ml","price":192},{"label":"200 ml","price":142},{"label":"300 ml","price":262}]},
  {"id":87,"name":"Onion Hair Oil","nameKn":"ಈರುಳ್ಳಿ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Onion Hair Oil 220.jpg","weights":[{"label":"100 ml","price":192},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":88,"name":"Onion Hair Oil","nameKn":"ಈರುಳ್ಳಿ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Onion Hair Oil 300.jpg","weights":[{"label":"100 ml","price":1902},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":89,"name":"Parachute Advansed Gold Vitamin E Coconut Hair Oil","nameKn":"ಪ್ಯಾರಾಶೂಟ್ ಅಡ್ವಾನ್ಸ್ಡ್ ಗೋಲ್ಡ್ ವಿಟಮಿನ್ ಇ ಕೊಬ್ಬರಿ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Parachute Advansed Gold Vitamin E Coconut Hair Oil 160.jpg","weights":[{"label":"100 ml","price":192},{"label":"200 ml","price":142},{"label":"300 ml","price":262}]},
  {"id":90,"name":"WOW Skin Science Onion Black Seed Hair Oil","nameKn":"ವಾವ್ ಸ್ಕಿನ್ ಸೈನ್ಸ್ ಈರುಳ್ಳಿ ಬ್ಲ್ಯಾಕ್ ಸೀಡ್ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\WOW Skin Science Onion Black Seed Hair Oil 300-400.jpg","weights":[{"label":"100 ml","price":192},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":91,"name":"Vatika Multivitamin Garlic Hair Oil","nameKn":"ವಾಟಿಕಾ ಮಲ್ಟಿವಿಟಮಿನ್ ಗಾರ್ಲಿಕ್ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Vatika Multivitamin Garlic Hair Oil 330.jpg","weights":[{"label":"100 ml","price":1902},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":92,"name":"TNW The Natural Wash Neem Oil","nameKn":"ಟಿಎನ್ಡಬ್ಲ್ಯೂ ದ ನ್ಯಾಚುರಲ್ ವಾಶ್ ನೀಮ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\TNW The Natural Wash Neem Oil 130-230.jpg","weights":[{"label":"100 ml","price":192},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":93,"name":"Soulflower Olive Oil for Nourished Hair and Skin","nameKn":"ಸೋಲ್ಫ್ಲವರ್ ಆಲಿವ್ ಆಯಿಲ್ ಫಾರ್ ನೌರಿಶ್ಡ್ ಹೇರ್ ಅಂಡ್ ಸ್ಕಿನ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Soulflower Olive Oil for Nourished Hair and Skin 227-245.jpg","weights":[{"label":"100 ml","price":1902},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":94,"name":"Rey Naturals Cold Pressed Neem Oil","nameKn":"ರೇ ನ್ಯಾಚುರಲ್ಸ್ ಕೋಲ್ಡ್ ಪ್ರೆಸ್ಡ್ ನೀಮ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Rey Naturals Cold Pressed Neem Oil 230-300.jpg","weights":[{"label":"100 ml","price":192},{"label":"200 ml","price":142},{"label":"300 ml","price":262}]},
  {"id":95,"name":"Pilgrim Spanish Rosemary & Biotin Hair Growth Oil","nameKn":"ಪಿಲ್ಗ್ರಿಮ್ ಸ್ಪ್ಯಾನಿಶ್ ರೋಸ್ಮೇರಿ & ಬಯೋಟಿನ್ ಹೇರ್ ಗ್ರೋತ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Pilgrim Spanish Rosemary & Biotin Hair Growth Oil 222-400.jpg","weights":[{"label":"100 ml","price":192},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":96,"name":"Phillauri Onion Blackseed Herbal Hair Oil","nameKn":"ಫಿಲೌರಿ ಈರುಳ್ಳಿ ಬ್ಲ್ಯಾಕ್ಸೀಡ್ ಹರ್ಬಲ್ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Phillauri Onion Blackseed Herbal Hair Oil 180.jpg","weights":[{"label":"100 ml","price":1902},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":97,"name":"Parachute Advansed Tea Tree Essential Oil","nameKn":"ಪ್ಯಾರಾಶೂಟ್ ಅಡ್ವಾನ್ಸ್ಡ್ ಟೀ ಟ್ರೀ ಎಸೆನ್ಷಿಯಲ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Parachute Advansed Tea Tree Essential Oil 220-310.jpg","weights":[{"label":"100 ml","price":192},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":98,"name":"Parachute Advansed Olive Enriched Coconut Hair Oil","nameKn":"ಪ್ಯಾರಾಶೂಟ್ ಅಡ್ವಾನ್ಸ್ಡ್ ಆಲಿವ್ ಎನ್ರಿಚ್ಡ್ ಕೊಬ್ಬರಿ ಹೇರ್ ಆಯಿಲ್","category":"Mustard Oil","description":"Kachi Ghani mustard oil, rich in flavor.","imageUrl":"images\\\\Parachute Advansed Olive Enriched Coconut Hair Oil 213-298.jpg","weights":[{"label":"100 ml","price":1902},{"label":"200 ml","price":4892},{"label":"300 ml","price":6668}]},
  {"id":99,"name":"Max Care Virgin Coconut Oil","nameKn":"ಮ್ಯಾಕ್ಸ್ ಕೇರ್ ವರ್ಜಿನ್ ಕೊಬ್ಬರಿ ಎಣ್ಣೆ","category":"Coconut Oil","description":"Pure coconut oil for cooking, hair and skin care.","imageUrl":"images\\\\Max Care Virgin Coconut Oil 298-485.jpg","weights":[{"label":"100 ml","price":48},{"label":"200 ml","price":92},{"label":"300 ml","price":137},{"label":"500 ml","price":212},{"label":"600 ml","price":252},{"label":"1 L","price":402}]},
  {"id":100,"name":"Saffola Gold Pro Healthy Lifestyle Oil","nameKn":"ಸ್ಯಾಫೋಲಾ ಗೋಲ್ಡ್ ಪ್ರೋ ಹೆಲ್ತಿ ಲೈಫ್ ಸ್ಟೈಲ್ ಆಯಿಲ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Saffola Gold Pro Healthy Lifestyle Oil.jpg","weights":[{"label":"100 ml","price":25},{"label":"200 ml","price":42},{"label":"300 ml","price":62},{"label":"500 ml","price":102},{"label":"600 ml","price":122},{"label":"1 L","price":212}]},
  {"id":101,"name":"Sundrop Nutrilite Oil","nameKn":"ಸನ್ಡ್ರಾಪ್ ನ್ಯೂಟ್ರಿಲೈಟ್ ಆಯಿಲ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Sundrop Nutrilite Oil.jpg","weights":[{"label":"100 ml","price":23},{"label":"200 ml","price":40},{"label":"300 ml","price":60},{"label":"500 ml","price":97},{"label":"600 ml","price":117},{"label":"1 L","price":202}]},
  {"id":102,"name":"Fortune Xpert Total Balance Oil","nameKn":"ಫಾರ್ಚೂನ್ ಎಕ್ಸ್ಪರ್ಟ್ ಟೋಟಲ್ ಬ್ಯಾಲೆನ್ಸ್ ಆಯಿಲ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Fortune Xpert Total Balance Oil.jpg","weights":[{"label":"100 ml","price":22},{"label":"200 ml","price":38},{"label":"300 ml","price":57},{"label":"500 ml","price":94},{"label":"600 ml","price":112},{"label":"1 L","price":195}]},
  {"id":103,"name":"Gemini Multi Source Oil","nameKn":"ಜೆಮಿನಿ ಮಲ್ಟಿ ಸೋರ್ಸ್ ಆಯಿಲ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Gemini Multi Source Oil.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":36},{"label":"300 ml","price":54},{"label":"500 ml","price":90},{"label":"600 ml","price":107},{"label":"1 L","price":185}]},
  {"id":104,"name":"Nature Fresh Smart Balance Oil","nameKn":"ನೇಚರ್ ಫ್ರೆಶ್ ಸ್ಮಾರ್ಟ್ ಬ್ಯಾಲೆನ್ಸ್ ಆಯಿಲ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Nature Fresh Smart Balance Oil.jpg","weights":[{"label":"100 ml","price":22},{"label":"200 ml","price":37},{"label":"300 ml","price":56},{"label":"500 ml","price":92},{"label":"600 ml","price":110},{"label":"1 L","price":190}]},
  {"id":105,"name":"Saffola Tasty Oil","nameKn":"ಸ್ಯಾಫೋಲಾ ಟೇಸ್ಟಿ ಆಯಿಲ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Saffola Tasty Oil.jpg","weights":[{"label":"100 ml","price":23},{"label":"200 ml","price":40},{"label":"300 ml","price":60},{"label":"500 ml","price":97},{"label":"600 ml","price":117},{"label":"1 L","price":200}]},
  {"id":106,"name":"Sundrop Active Oil","nameKn":"ಸನ್ಡ್ರಾಪ್ ಆಕ್ಟಿವ್ ಆಯಿಲ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Sundrop Active Oil.jpg","weights":[{"label":"100 ml","price":21},{"label":"200 ml","price":36},{"label":"300 ml","price":54},{"label":"500 ml","price":90},{"label":"600 ml","price":107},{"label":"1 L","price":180}]},
  {"id":107,"name":"Fortune Blended Cooking Oil","nameKn":"ಫಾರ್ಚೂನ್ ಬ್ಲೆಂಡೆಡ್ ಕುಕಿಂಗ್ ಆಯಿಲ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Fortune Blended Cooking Oil.jpg","weights":[{"label":"100 ml","price":20},{"label":"200 ml","price":32},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":170}]},
  {"id":108,"name":"Dhara Balance Oil","nameKn":"ಧಾರಾ ಬ್ಯಾಲೆನ್ಸ್ ಆಯಿಲ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Dhara Balance Oil.jpg","weights":[{"label":"100 ml","price":20},{"label":"200 ml","price":32},{"label":"300 ml","price":50},{"label":"500 ml","price":82},{"label":"600 ml","price":97},{"label":"1 L","price":165}]},
  {"id":111,"name":"Anjali Groundnut Oil Tin","nameKn":"ಅಂಜಲಿ ಕಡಲೆಕಾಯಿ ಎಣ್ಣೆ ಟಿನ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Anjali Groundnut Oil Tin.jpg","weights":[{"label":"100 ml","price":32},{"label":"200 ml","price":57},{"label":"300 ml","price":87},{"label":"500 ml","price":152},{"label":"600 ml","price":182},{"label":"1 L","price":292}]},
  {"id":112,"name":"Engine Brand Groundnut Oil Drum","nameKn":"ಎಂಜಿನ್ ಬ್ರಾಂಡ್ ಕಡಲೆಕಾಯಿ ಎಣ್ಣೆ ಡ್ರಮ್","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Engine Brand Groundnut Oil Drum.jpg","weights":[{"label":"100 ml","price":33},{"label":"200 ml","price":60},{"label":"300 ml","price":90},{"label":"500 ml","price":157},{"label":"600 ml","price":187},{"label":"1 L","price":302}]},
  {"id":114,"name":"Organic Cold Pressed Sesame Oil","nameKn":"ಆರ್ಗಾನಿಕ್ ಕೋಲ್ಡ್ ಪ್ರೆಸ್ಡ್ ಎಳ್ಳು ಎಣ್ಣೆ","category":"Specialty Oil","description":"High‑quality cooking oil for specific health benefits.","imageUrl":"images\\\\Organic Cold Pressed Sesame Oil.jpg","weights":[{"label":"100 ml","price":50},{"label":"200 ml","price":87},{"label":"300 ml","price":132},{"label":"500 ml","price":222},{"label":"600 ml","price":262},{"label":"1 L","price":412}]},
  {"id":9600,"name":"Amul Pure Ghee","nameKn":"ಅಮೂಲ್ ಶುದ್ಧ ತುಪ್ಪ","category":"Ghee","description":"Rich traditional pure cow ghee for aromatic cooking.","imageUrl":"images/Amul Pure Ghee.jpg","weights":[{"label":"500 ml","price":622},{"label":"1 L","price":1222}]},
  {"id":9601,"name":"Amul Pure Ghee 1L Pouch","nameKn":"ಅಮೂಲ್ ತುಪ್ಪ 1L ಪೌಚ್","category":"Ghee","description":"Refill pouch of Amul pure cow ghee.","imageUrl":"images/Amul Pure Ghee 1 L Pouch 550-650.jpg","weights":[{"label":"1 L","price":602}]},
  {"id":9602,"name":"Mother Dairy Cow Ghee","nameKn":"ಮದರ್ ಡೈರಿ ಹಸುವಿನ ತುಪ್ಪ","category":"Ghee","description":"Pure granulated cow ghee with natural aroma.","imageUrl":"images/Mother Dairy Cow Ghee 600-750.jpg","weights":[{"label":"1 L","price":677}]},
  {"id":9603,"name":"Nandini Pure Cow Ghee","nameKn":"ನಂದಿನಿ ಶುದ್ಧ ಹಸುವಿನ ತುಪ್ಪ","category":"Ghee","description":"Karnataka\u2019s favourite Agmark pure cow ghee.","imageUrl":"images/Nandini Pure Cow Ghee 700.jpg","weights":[{"label":"500 ml","price":352},{"label":"1 L","price":702}]},
  {"id":9604,"name":"Sunflower Oil Refined","nameKn":"ಸೂರ್ಯಕಾಂತಿ ಅಡುಗೆ ಎಣ್ಣೆ","category":"Sunflower Oil","description":"Light refined sunflower cooking oil.","imageUrl":"images/Sunflower oil.jpg","weights":[{"label":"1 L","price":152},{"label":"5 L","price":742}]},
  {"id":9605,"name":"Pure Cow Ghee","nameKn":"ಶುದ್ಧ ಹಸುವಿನ ತುಪ್ಪ","category":"Ghee","description":"Pure clarified butter for cooking and sweets.","imageUrl":"images/Ghee.jpg","weights":[{"label":"500 ml","price":322},{"label":"1 L","price":632}]},
  {"id":9606,"name":"Amul Pure Cow Ghee Jar","nameKn":"ಅಮೂಲ್ ಹಸುವಿನ ತುಪ್ಪ ಜಾರ್","category":"Ghee","description":"Pure cow ghee in convenient glass/plastic jar.","imageUrl":"images/Amul Pure  cow Ghee 620.jpg","weights":[{"label":"1 L","price":622}]}
]';
  $data = json_decode($json, true);
  if (!is_array($data)) {
    return [];
  }
  $result = [];
  $seenNames = [];
  foreach ($data as $item) {
    $uniqueKey = $item['name'] . '|' . $item['category'];
    if (isset($seenNames[$uniqueKey])) {
      continue;
    }
    $seenNames[$uniqueKey] = true;
    $subcategory = 'other';
    $cat = strtolower($item['category'] ?? '');
    if (strpos($cat, 'olive') !== false) {
      $subcategory = 'olive';
    } elseif (strpos($cat, 'coconut') !== false) {
      $subcategory = 'coconut';
    } elseif (strpos($cat, 'mustard') !== false) {
      $subcategory = 'mustard';
    } elseif (strpos($cat, 'groundnut') !== false || strpos($cat, 'peanut') !== false) {
      $subcategory = 'groundnut';
    } elseif (strpos($cat, 'sunflower') !== false || strpos($cat, 'vegetable') !== false) {
      $subcategory = 'sunflower';
    }
    // Fix image path (supports both image and imageUrl)
    $imagePath = ksrNormalizeFlatImage($item['image'] ?? $item['imageUrl'] ?? '');
    $result[] = [
      'id' => $item['id'],
      'name' => $item['name'],
      'kn_name' => $item['nameKn'] ?? $item['name'],
      'price' => $item['weights'][0]['price'] ?? 0,
      'category' => $subcategory,
      'variants' => $item['weights'],
      'emoji' => '🧴',
      'description' => $item['description'] ?? 'Premium cooking oil',
      'image' => $imagePath
    ];
  }
  $fallback = $result;
  @file_put_contents($jsonPath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  return $fallback;
}
if (!function_exists('getFallbackProducts')) {
  function getFallbackProducts(): array
  {
    return getFallbackCookingOilProducts();
  }
}
// Get products (always load full catalog + merge DB entries cleanly)
$products = ksrMergeCategoryProducts(getCookingOilProducts(), getFallbackCookingOilProducts(), [
  'nameField' => 'name',
  'imageField' => 'image',
  'updateMatchedPrice' => false,
]);
// Ensure each product has a 'variants' array
foreach ($products as &$p) {
  if (!isset($p['variants']) || !is_array($p['variants']) || empty($p['variants'])) {
    $p['variants'] = [['label' => '1 Litre', 'price' => $p['price']]];
  }
}
unset($p);
// Cart data
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
// Top picks
$topPicks = array_slice($products, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>Pure Oils · ಶುದ್ಧ ಎಣ್ಣೆಗಳು · Premium Cooking Oils · KSR Guru JI</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <style>
    /* ===== All CSS from your original file (exactly as provided) ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background: #fef8ef;
      font-family: 'Inter', sans-serif;
      padding: 24px 20px;
      min-height: 100vh;
    }

    #app {
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
      background: white;
      border-radius: 44px;
      box-shadow: 0 25px 45px rgba(0, 0, 0, 0.05);
      padding: 28px 32px 40px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 18px;
      margin-bottom: 28px;
    }

    .shop-name h1 {
      font-size: 2.2rem;
      font-weight: 800;
      color: #3b2a1c;
      letter-spacing: -0.3px;
    }

    .shop-name .kannada {
      font-size: 1rem;
      color: #c16f3a;
      font-weight: 500;
      display: inline-block;
      margin-top: 4px;
    }

    .cart-link {
      background: #f6ede5;
      padding: 12px 26px;
      border-radius: 60px;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      transition: all 0.2s ease;
      box-shadow: 0 3px 8px rgba(0, 0, 0, 0.03);
      border: 1px solid #f0e2d6;
      text-decoration: none;
      color: inherit;
      font-weight: 600;
    }

    .cart-link i {
      font-size: 1.7rem;
      color: #a15526;
    }

    .cart-link .cart-badge {
      background: #c16f3a;
      color: white;
      font-weight: 700;
      font-size: 1rem;
      padding: 2px 12px;
      border-radius: 40px;
    }

    .cart-link:hover {
      background: #e6d8cd;
    }

    .secondary-nav {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 30px;
    }

    .secondary-nav a {
      background: #f4ede5;
      padding: 10px 22px;
      border-radius: 50px;
      font-size: 0.9rem;
      font-weight: 600;
      color: #5a3f28;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: 0.2s;
    }

    .secondary-nav a:hover {
      background: #e6d8cd;
      color: #3b2a1c;
    }

    .search-section {
      margin-bottom: 36px;
    }

    .search-box {
      display: flex;
      align-items: center;
      background: #faf5ef;
      border-radius: 70px;
      padding: 6px 24px;
      border: 1px solid #eadbcb;
      max-width: 520px;
      margin-bottom: 20px;
    }

    .search-box i {
      color: #c16f3a;
      font-size: 1.2rem;
      margin-right: 14px;
    }

    .search-box input {
      flex: 1;
      border: none;
      background: transparent;
      padding: 14px 0;
      font-size: 1rem;
      outline: none;
      color: #2d241b;
    }

    .filter-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .pill {
      background: #f4ede5;
      padding: 9px 24px;
      border-radius: 60px;
      font-size: 0.95rem;
      font-weight: 600;
      color: #5a3f28;
      cursor: pointer;
      transition: all 0.2s;
    }

    .pill.active {
      background: #9b5e2e;
      color: white;
      box-shadow: 0 4px 8px rgba(155, 94, 46, 0.2);
    }

    .pill:hover {
      background: #e3d3c5;
    }

    .top-picks {
      margin: 30px 0 48px;
      background: linear-gradient(125deg, #fff4ea, #ffe5d6);
      border-radius: 38px;
      padding: 26px 28px;
      border: 1px solid #fadbc2;
    }

    .top-picks h3 {
      font-size: 1.8rem;
      font-weight: 800;
      color: #3b2a1c;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .top-picks h3 i {
      color: #e67e22;
      font-size: 1.9rem;
    }

    .top-picks-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 26px;
    }

    .top-pick-card {
      background: white;
      border-radius: 32px;
      padding: 18px 12px 20px;
      text-align: center;
      transition: all 0.25s ease;
      box-shadow: 0 8px 18px rgba(0, 0, 0, 0.03);
      cursor: default;
    }

    .top-pick-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 18px 28px rgba(0, 0, 0, 0.08);
    }

    .top-pick-img {
      width: 100%;
      aspect-ratio: 1;
      border-radius: 28px;
      background: #fcf3ea;
      margin-bottom: 14px;
      font-size: 3.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .top-pick-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 28px;
    }

    .top-pick-name {
      font-weight: 800;
      font-size: 1rem;
      color: #2d241b;
    }

    .top-pick-price {
      font-size: 1.1rem;
      font-weight: 800;
      color: #b45f2b;
      margin: 8px 0;
    }

    .top-pick-btn {
      background: #b45f2b;
      border: none;
      color: white;
      width: 46px;
      height: 46px;
      border-radius: 50px;
      font-size: 1.2rem;
      cursor: pointer;
      transition: 0.12s linear;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto;
    }

    .top-pick-btn:active {
      transform: scale(0.92);
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
      gap: 30px;
      margin: 20px 0 50px;
    }

    .product-card {
      background: white;
      border-radius: 34px;
      padding: 20px 16px 24px;
      border: 1px solid #f3e6db;
      transition: all 0.25s;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      box-shadow: 0 5px 12px rgba(0, 0, 0, 0.02);
    }

    .product-card:hover {
      transform: translateY(-6px);
      border-color: #e9cfbb;
      box-shadow: 0 18px 30px rgba(0, 0, 0, 0.06);
    }

    .product-image {
      width: 110px;
      height: 110px;
      border-radius: 55px;
      overflow: hidden;
      margin: 0 auto 18px;
      background: #fcf3ea;
      border: 2px solid #eedbcb;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 55px;
    }

    .product-name {
      font-weight: 800;
      font-size: 1.1rem;
      color: #2d241b;
    }

    .product-name-kn {
      font-size: 0.75rem;
      color: #8b694c;
      margin-bottom: 8px;
    }

    .product-description {
      font-size: 0.8rem;
      color: #9f7b5c;
      margin: 8px 0 12px;
      line-height: 1.4;
      padding: 0 5px;
    }

    .variant-selector {
      width: 100%;
      background: #fef7f0;
      border: 1px solid #e8d9cd;
      border-radius: 60px;
      padding: 10px;
      font-size: 0.85rem;
      font-weight: 500;
      margin: 10px 0;
      cursor: pointer;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      margin-top: 8px;
    }

    .product-price {
      font-weight: 800;
      font-size: 1.3rem;
      color: #b45f2b;
    }

    .add-btn {
      background: #b45f2b;
      border: none;
      color: white;
      width: 48px;
      height: 48px;
      border-radius: 60px;
      font-size: 1.4rem;
      cursor: pointer;
      transition: 0.08s linear;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .add-btn.added-flash {
      background: #4caf50 !important;
      color: white !important;
    }

    .add-btn:active {
      transform: scale(0.94);
    }

    .cart-sidebar {
      position: fixed;
      top: 0;
      right: -420px;
      width: 400px;
      height: 100vh;
      background: #fffefc;
      box-shadow: -8px 0 40px rgba(0, 0, 0, 0.1);
      transition: right 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
      z-index: 1100;
      display: flex;
      flex-direction: column;
      border-left: 2px solid #f3e1d2;
    }

    .cart-sidebar.open {
      right: 0;
    }

    .cart-header {
      padding: 24px;
      border-bottom: 2px solid #f5e6db;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .cart-header h3 {
      font-size: 1.5rem;
      color: #3b2a1d;
      display: flex;
      gap: 10px;
    }

    .close-cart {
      font-size: 1.6rem;
      color: #a67b60;
      cursor: pointer;
      background: none;
      border: none;
    }

    .cart-items {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
    }

    .empty-cart {
      text-align: center;
      margin-top: 60px;
      color: #b99278;
    }

    .cart-item {
      display: flex;
      gap: 14px;
      margin-bottom: 24px;
      padding-bottom: 18px;
      border-bottom: 1px solid #f0e0d2;
      align-items: center;
    }

    .cart-item-image {
      width: 44px;
      height: 44px;
      background: #daf0e2;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      flex-shrink: 0;
      font-size: 1.5rem;
    }

    .cart-item-details {
      flex: 1;
    }

    .cart-item-name {
      font-weight: 700;
      margin-bottom: 4px;
      font-size: 0.9rem;
    }

    .cart-item-price {
      color: #c25a3a;
      font-weight: 600;
      font-size: 0.85rem;
    }

    .cart-item-actions {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 10px;
    }

    .cart-item-actions button {
      background: #f9ede5;
      width: 30px;
      height: 30px;
      border-radius: 40px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .cart-item-actions button:hover {
      background: #edd3c2;
    }

    .cart-footer {
      padding: 24px;
      border-top: 2px solid #f3e2d4;
    }

    .cart-total {
      display: flex;
      justify-content: space-between;
      font-weight: 800;
      font-size: 1.3rem;
      margin-bottom: 20px;
    }

    .checkout-btn,
    .continue-shopping {
      width: 100%;
      padding: 14px;
      border-radius: 50px;
      font-weight: 700;
      margin-bottom: 12px;
      border: none;
      cursor: pointer;
    }

    .checkout-btn {
      background: #c25a3a;
      color: white;
    }

    .continue-shopping {
      background: none;
      border: 1px solid #dcbfab;
      color: #5e4330;
    }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(3px);
      z-index: 1050;
      display: none;
    }

    .overlay.active {
      display: block;
    }

    footer {
      text-align: center;
      font-size: 0.8rem;
      color: #c6aa8f;
      margin-top: 28px;
      padding-top: 20px;
      border-top: 1px solid #f1e3d7;
    }

    @media (max-width: 680px) {
      #app {
        padding: 18px;
      }

      .product-grid {
        gap: 18px;
      }

      .top-picks-grid {
        gap: 16px;
      }

      .cart-link {
        padding: 8px 20px;
      }

      .cart-sidebar {
        width: 100%;
        right: -100%;
      }
    }
  </style>
  <link rel="stylesheet" href="category-style.css">
  <script src="gallery.js"></script>
  <link rel="stylesheet" href="../index.css">
  <link rel="stylesheet" href="../mobile-app.css?v=3.0" />
</head>

<body>
  <div id="app">
    <?php
    $base_url = '../';
    include __DIR__ . '/../includes/global-header.php';
    ?>

    <div class="search-section">
      <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchField" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
      </div>
      <div class="filter-pills" id="categoryPills">
        <div class="pill active" data-cat="all">All / ಎಲ್ಲ</div>
        <div class="pill" data-cat="coconut">Coconut / ತೆಂಗಿನ</div>
        <div class="pill" data-cat="sunflower">Sunflower / ಸೂರ್ಯಕಾಂತಿ</div>
        <div class="pill" data-cat="mustard">Mustard / ಸಾಸಿವೆ</div>
        <div class="pill" data-cat="groundnut">Groundnut / ಕಡಲೆಕಾಯಿ</div>
      </div>
    </div>
    <div class="top-picks" id="topPicksSection">
      <h3><i class="fas fa-fire"></i> Top Picks · Bestsellers</h3>
      <div class="top-picks-grid" id="topPicksGrid"></div>
    </div>
    <div class="product-grid" id="productGrid">
    </div>
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
  </div>
  <script>
    (function() {
      const products = <?php
                        $out = [];
                        foreach ($products as $p) {
                          $subcat = $p['category'] ?? 'other';
                          $out[] = [
                            'id' => $p['id'],
                            'nameEn' => $p['name'],
                            'nameKn' => $p['kn_name'] ?? $p['name'],
                            'category' => $subcat,
                            'variants' => $p['variants'] ?? [['label' => '1 Litre', 'price' => $p['price']]],
                            'emoji' => $p['emoji'] ?? '🧴',
                            'description' => $p['description'] ?? 'Cooking oil',
                            'image' => $p['image'] ?? '',
                            'images' => $p['images'] ?? []
                          ];
                        }
                        echo json_encode($out);
                        ?>;
      let cart = [];
      const basePath = 'cooking-oil.php';
      const cartCountSpan = document.getElementById('cartCount');
      const cartSidebar = document.getElementById('cartSidebar');
      const overlay = document.getElementById('overlay');
      const closeCartBtn = document.getElementById('closeCart');
      const continueShoppingBtn = document.getElementById('continueShopping');
      const checkoutBtn = document.getElementById('checkoutBtn');
      const cartItemsContainer = document.getElementById('cartItems');
      const totalPriceSpan = document.querySelector('.total-price');
      const searchField = document.getElementById('searchField');
      const filterPills = document.querySelectorAll('#categoryPills .pill');
      const cartLink = document.getElementById('cartLink');
      const grid = document.getElementById('productGrid');
      const topPicksGrid = document.getElementById('topPicksGrid');
      let activeCategory = 'all';
      let searchTerm = '';

      function loadCart() {
        fetch('../index.php?ajax=cart')
          .then(res => res.json())
          .then(data => {
            cart = data;
            updateCartUI();
            if (cartSidebar.classList.contains('open')) renderCartItems();
          })
          .catch(console.error);
      }

      function updateCartUI() {
        const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
        cartCountSpan.innerText = totalQty;
      }

      function addToCart(productId) {
        const prodIdNum = parseInt(productId);
        let product = null;
        if (typeof products !== 'undefined' && Array.isArray(products)) {
          product = products.find(p => p.id === prodIdNum);
        }
        // Find the variant selector in the DOM for this product
        const sel = document.querySelector(`.variant-selector[data-pid="${productId}"]`);
        let selectedVariant = null;
        let selectedPrice = 0;
        if (sel && product && product.variants) {
          const idx = parseInt(sel.value);
          selectedVariant = product.variants[idx];
          if (selectedVariant) {
            selectedPrice = parseFloat(selectedVariant.price);
          }
        } else if (product) {
          // Fallback to first variant
          const firstVar = product.variants ? product.variants[0] : null;
          selectedPrice = firstVar ? parseFloat(firstVar.price) : 0;
        }
        const formData = new FormData();
        formData.append('id', productId);
        formData.append('qty', 1);
        if (product) {
          let finalName = product.nameEn || '';
          let finalKn = product.nameKn || '';
          formData.append('name', finalName);
          formData.append('kn', finalKn);
          formData.append('price', selectedPrice);
          formData.append('custom_price', selectedPrice);
          if (selectedVariant) {
            formData.append('variant', selectedVariant.label);
          }
          formData.append('image', product.image || '');
        }
        fetch('../cart/add_cart.php', {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(res => res.text())
          .then(text => {
            let data;
            try {
              const jsonStart = text.indexOf('{');
              if (jsonStart !== -1) {
                data = JSON.parse(text.substring(jsonStart));
              }
            } catch (e) {
              console.warn("Cart response parse warning:", text);
            }
            if (!data) {
              console.error("Invalid cart response:", text);
              return;
            }
            if (data.success) {
              const cartArr = Array.isArray(data.cart) ? data.cart : Object.values(data.cart || {});
              if (typeof cart !== "undefined") {
                cart = cartArr;
              } else {
                window.cart = cartArr;
              }
              const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge, #cartBadgeCount");
              const totalCount = data.cartCount || cartArr.reduce((acc, i) => acc + (parseInt(i.qty) || 1), 0);
              badges.forEach(b => {
                b.innerText = totalCount || "";
              });
              if (typeof openCart === "function") {
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

      function updateCartQty(productId, qty) {
        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('id', productId);
        fd.append('qty', qty);
        fetch('../cart/update_cart.php', {
            method: 'POST',
            body: fd,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              cart = data.cart;
              updateCartUI();
              renderCartItems();
            }
          })
          .catch(console.error);
      }

      function removeFromCart(productId) {
        const fd = new FormData();
        fd.append('action', 'remove');
        fd.append('id', productId);
        fetch('../cart/update_cart.php', {
            method: 'POST',
            body: fd,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              cart = data.cart;
              updateCartUI();
              renderCartItems();
            }
          })
          .catch(console.error);
      }

      function openCart() {
        cartSidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        renderCartItems();
      }

      function closeCart() {
        cartSidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
      }

      function renderCartItems() {
        if (!cart.length) {
          cartItemsContainer.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-basket"></i><p>Your cart is empty</p></div>`;
          totalPriceSpan.innerText = '₹0';
          return;
        }
        let total = 0;
        let html = '';
        cart.forEach(item => {
          const itemPrice = parseFloat(item.price);
          const itemTotal = itemPrice * item.qty;
          total += itemTotal;
          html += `
        <div class="cart-item" data-id="${item.id}">
          <div class="cart-item-image">🧴</div>
          <div class="cart-item-details">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-price">₹${itemPrice.toFixed(2)} × ${item.qty} = ₹${itemTotal.toFixed(2)}</div>
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
        cartItemsContainer.innerHTML = html;
        totalPriceSpan.innerText = `₹${total.toFixed(2)}`;
      }

      function renderProducts() {
        const filtered = products.filter(p => {
          const matchCat = activeCategory === 'all' || p.category === activeCategory;
          const matchSearch = searchTerm === '' || p.nameEn.toLowerCase().includes(searchTerm) || p.nameKn.toLowerCase().includes(searchTerm);
          return matchCat && matchSearch;
        });
        if (!filtered.length) {
          grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No oils found ✨</div>';
          return;
        }
        grid.innerHTML = filtered.map(p => {
          const defaultVariant = 0;
          const defaultPrice = p.variants[defaultVariant]?.price || 0;
          const imgTag = window.ksrGalleryMediaHtml(p, { emoji: p.emoji || '🧴' });
          return `<div class="product-card">
        <div class="product-image">${imgTag}</div>
        <div class="product-name">${p.nameEn}</div>
        ${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}
        <div class="product-description">${p.description}</div>
        ${p.variants && p.variants.length > 1 
          ? `<select class="variant-selector" data-pid="${p.id}">
              ${p.variants.map((v, i) => `<option value="${i}" ${i===defaultVariant?'selected':''}>${v.label} - ₹${v.price}</option>`).join('')}
             </select>`
          : `<div class="variant-text" style="font-size:0.85rem; font-weight:600; color:#4f3e2e; text-align:center; margin:10px 0; padding:10px; background:#fef7f0; border-radius:60px; border:1px solid #e8d9cd;">${p.variants && p.variants[0] ? p.variants[0].label : '1 unit'}</div>`
        }
        <div class="price-row">
          <div class="product-price">₹${defaultPrice}</div>
          <button class="add-btn" data-pid="${p.id}" onclick="window.addToCart(${p.id})"><i class="fas fa-cart-plus"></i></button>
        </div>
      </div>`;
        }).join('');
        document.querySelectorAll('.variant-selector').forEach(sel => {
          sel.addEventListener('change', function() {
            const pid = parseInt(this.dataset.pid);
            const prod = products.find(p => p.id === pid);
            if (!prod) return;
            const idx = parseInt(this.value);
            const price = prod.variants[idx]?.price || 0;
            const priceDiv = this.closest('.product-card').querySelector('.product-price');
            if (priceDiv) priceDiv.innerText = `₹${price}`;
          });
        });
      }

      function renderTopPicks() {
        const topProds = products.filter(p => p.id % 2 === 1).slice(0, 6);
        if (!topProds.length) return;
        topPicksGrid.innerHTML = topProds.map(p => {
          const firstVariant = p.variants[0];
          const imgSrc = p.image ? p.image : '';
          const imgTag = imgSrc ? `<img src="${imgSrc}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='🧴'">` : '🧴';
          return `<div class="top-pick-card">
        <div class="top-pick-img">${imgTag}</div>
        <div class="top-pick-name">${p.nameEn}</div>
        <div class="top-pick-price">₹${firstVariant?.price || 0} (${firstVariant?.label || 'size'})</div>
        <button class="top-pick-btn" onclick="window.addToCart(${p.id})"><i class="fas fa-plus"></i></button>
      </div>`;
        }).join('');
      }
      searchField.addEventListener('input', function(e) {
        searchTerm = this.value.toLowerCase();
        renderProducts();
      });
      filterPills.forEach(pill => {
        pill.addEventListener('click', function() {
          filterPills.forEach(p => p.classList.remove('active'));
          this.classList.add('active');
          activeCategory = this.dataset.cat;
          renderProducts();
        });
      });
      cartLink.addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof openCart === 'function') {
          openCart();
        } else {
          window.location.href = '../cart/cart.php';
        }
      });
      closeCartBtn.addEventListener('click', closeCart);
      overlay.addEventListener('click', closeCart);
      continueShoppingBtn.addEventListener('click', closeCart);
      checkoutBtn.addEventListener('click', function() {
        if (cart.length) {
          window.location.href = '../checkout/checkout.php';
        } else {
          window.showFastCartToast ? window.showFastCartToast('Your cart is empty! Add items to proceed.') : alert('Cart is empty');
        }
      });
      window.addToCart = addToCart;
      window.changeQty = updateCartQty;
      window.removeItem = removeFromCart;
      loadCart();
      renderTopPicks();
      renderProducts();
    })();
  </script>


  <?php
  $base_url = '../';
  $currentCategory = 'cooking-oil';
  include __DIR__ . '/../includes/related-section.php';
  ?>

  <?php include __DIR__ . '/../includes/user-footer.php'; ?>
  <script src="sorting.js"></script>

  <!-- Mobile Bottom Navigation -->
  <?php if (file_exists(__DIR__ . '/../mobile-bottom-nav.php')) require_once __DIR__ . '/../mobile-bottom-nav.php'; ?>
  <script src="/assets/js/mobile-app.js?v=3.0" defer></script>
</body>

</html>