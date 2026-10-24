<?php
// ============================================================
// grains.php - Dynamic Grains & Cereals product page (FIXED)
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
      echo json_encode(getGrainsProducts($search));
      exit;
    case 'cart':
      echo json_encode($_SESSION['cart']);
      exit;
    case 'add_cart':
      $id = (int) ($_POST['id'] ?? 0);
      if ($id) {
        $product = getProductById($id);
        if (!$product) {
          $fallback = getFallbackGrainsProducts();
          foreach ($fallback as $fp) {
            if ($fp['id'] == $id) {
              $product = [
                'id' => $fp['id'],
                'name' => $fp['name'],
                'kn_name' => $fp['kn_name'],
                'price' => $fp['price'],
                'icon' => 'fa-wheat'
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
              'icon' => $product['icon'] ?? 'fa-wheat'
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
    if (empty($rawPath)) return '';
    $clean = str_replace('\\', '/', $rawPath);
    if (preg_match('#(?:.*)(images/.*)$#i', $clean, $matches)) {
      return '../' . $matches[1];
    }
    return '../images/' . basename($clean);
  }
}
// ============================================================
// FETCH GRAIN PRODUCTS FROM DATABASE (PDO)
// ============================================================
function getGrainsProducts(string $search = ''): array
{
  try {
    $pdo = getDBConnection();
    // Dynamic column checks for products table
    $schema = getProductSchemaInfo();
    $descCol = $schema["desc_col"];
    $imgCol = $schema["img_col"];
    // Dynamic column check for categories table
    $schema = getProductSchemaInfo();
    $hasSlug = $schema["has_cat_slug"];
    $whereCategory = $hasSlug ? "(c.slug = 'grains' OR c.id = 'grains' OR c.name LIKE '%grains%')" : "(c.name LIKE '%grains%' OR c.name LIKE '%cereals%')";
    $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                       c.name AS category_name, c.kn_name AS category_kn,
                       {$descCol} AS description_en,
                       {$imgCol} AS image
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE {$whereCategory}";
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
      $row['description_en'] = $row['description_en'] ?? 'Quality grain product';
      if (!empty($row['image'])) {
        $row['image'] = normalizeImagePath($row['image']);
      }
      $row['imageUrl'] = $row['image'] ?? '';
      $row['variants'] = [['label' => '1 kg', 'price' => (float)$row['price']]];
    }
    return $rows;
  } catch (Throwable $e) {
    return [];
  }
}
// ============================================================
// FALLBACK: If no products from DB, load static data from JSON
// ============================================================
function getFallbackGrainsProducts()
{
  static $fallback = null;
  if ($fallback !== null) {
    return $fallback;
  }
  $jsonPath = __DIR__ . '/grains-fallback.json';
  if (file_exists($jsonPath)) {
    $fallback = json_decode(file_get_contents($jsonPath), true);
    if (is_array($fallback)) {
      return $fallback;
    }
  }
  $json = '[
  {"id":1,"name":"Sona Masoori Rice Premium","nameKn":"ಸೋನಾ ಮಸೂರಿ ಅಕ್ಕಿ ಪ್ರೀಮಿಯಂ","category":"rice","description":"Premium Sona Masoori rice.","imageurl":"images\\\\Sona Masoori Rice Premium.jpeg","weights":[{"label":"1 kg","price":85},{"label":"2 kg","price":170},{"label":"5 kg","price":425},{"label":"10 kg","price":850},{"label":"15 kg","price":1275},{"label":"25 kg","price":2125}]},
  {"id":2,"name":"Sona Masoori Rice Standard","nameKn":"ಸೋನಾ ಮಸೂರಿ ಅಕ್ಕಿ ಸ್ಟ್ಯಾಂಡರ್ಡ್","category":"rice","description":"Standard Sona Masoori rice.","imageurl":"images\\\\Sona Masoori Rice Standard.jpg","weights":[{"label":"1 kg","price":82},{"label":"2 kg","price":164},{"label":"5 kg","price":410},{"label":"10 kg","price":820},{"label":"15 kg","price":1230},{"label":"25 kg","price":2050}]},
  {"id":3,"name":"Basmati Rice Classic","nameKn":"ಬಾಸ್ಮತಿ ಅಕ್ಕಿ ಕ್ಲಾಸಿಕ್","category":"rice","description":"Classic basmati rice.","imageurl":"images\\\\India Gate Basmati Rice Classic.jpg","weights":[{"label":"1 kg","price":120},{"label":"2 kg","price":240},{"label":"5 kg","price":600},{"label":"10 kg","price":1200},{"label":"15 kg","price":1800},{"label":"25 kg","price":3000}]},
  {"id":4,"name":"Basmati Rice Premium Long Grain","nameKn":"ಬಾಸ್ಮತಿ ಅಕ್ಕಿ ಪ್ರೀಮಿಯಂ ಲಾಂಗ್ ಗ್ರೇನ್","category":"rice","description":"Premium long grain basmati rice.","imageurl":"images\\\\Basmati Rice Premium Long Grain.jpg","weights":[{"label":"1 kg","price":124},{"label":"2 kg","price":248},{"label":"5 kg","price":620},{"label":"10 kg","price":1240},{"label":"15 kg","price":1860},{"label":"25 kg","price":3100}]},
  {"id":5,"name":"Kolam Rice","nameKn":"ಕೊಳಂ ಅಕ್ಕಿ","category":"rice","description":"Kolam rice.","imageurl":"images\\\\Kolam Rice .jpg","weights":[{"label":"1 kg","price":70},{"label":"2 kg","price":140},{"label":"5 kg","price":350},{"label":"10 kg","price":700},{"label":"15 kg","price":1050},{"label":"25 kg","price":1750}]},
  {"id":6,"name":"Kolam Rice Economy","nameKn":"ಕೊಳಂ ಅಕ್ಕಿ ಎಕಾನಮಿ","category":"rice","description":"Economy kolam rice.","imageurl":"images\\\\Kolam Rice Economy.jpg","weights":[{"label":"1 kg","price":68},{"label":"2 kg","price":136},{"label":"5 kg","price":340},{"label":"10 kg","price":680},{"label":"15 kg","price":1020},{"label":"25 kg","price":1700}]},
  {"id":7,"name":"Idli Rice","nameKn":"ಇಡ್ಲಿ ಅಕ್ಕಿ","category":"rice","description":"Idli rice.","imageurl":"images\\\\Idli Rice.jpg","weights":[{"label":"1 kg","price":60},{"label":"2 kg","price":120},{"label":"5 kg","price":300},{"label":"10 kg","price":600},{"label":"15 kg","price":900},{"label":"25 kg","price":1500}]},
  {"id":8,"name":"Idli Rice Bulk","nameKn":"ಇಡ್ಲಿ ಅಕ್ಕಿ ಬಲ್ಕ್","category":"rice","description":"Bulk idli rice.","imageurl":"images\\\\Idli Rice Bulk.jpg","weights":[{"label":"1 kg","price":54},{"label":"2 kg","price":108},{"label":"5 kg","price":270},{"label":"10 kg","price":540},{"label":"15 kg","price":810},{"label":"25 kg","price":1350}]},
  {"id":9,"name":"Ponni Boiled Rice","nameKn":"ಪೊನ್ನಿ ಬೇಯಿಸಿದ ಅಕ್ಕಿ","category":"rice","description":"Ponni boiled rice.","imageurl":"images\\\\Ponni Boiled Rice.jpg","weights":[{"label":"1 kg","price":75},{"label":"2 kg","price":150},{"label":"5 kg","price":375},{"label":"10 kg","price":750},{"label":"15 kg","price":1125},{"label":"25 kg","price":1875}]},
  {"id":10,"name":"Ponni Raw Rice","nameKn":"ಪೊನ್ನಿ ಕಚ್ಚಾ ಅಕ್ಕಿ","category":"rice","description":"Ponni raw rice.","imageurl":"images\\\\Ponni Raw Rice.jpg","weights":[{"label":"1 kg","price":72},{"label":"2 kg","price":144},{"label":"5 kg","price":360},{"label":"10 kg","price":720},{"label":"15 kg","price":1080},{"label":"25 kg","price":1800}]},
  {"id":11,"name":"Brown Rice Organic","nameKn":"ಕಂದು ಅಕ್ಕಿ ಸಾವಯವ","category":"rice","description":"Organic brown rice.","imageurl":"images\\\\Brown Rice Organic.jpg","weights":[{"label":"1 kg","price":110},{"label":"2 kg","price":220},{"label":"5 kg","price":550},{"label":"10 kg","price":1100},{"label":"15 kg","price":1650},{"label":"25 kg","price":2750}]},
  {"id":12,"name":"Brown Rice Premium","nameKn":"ಕಂದು ಅಕ್ಕಿ ಪ್ರೀಮಿಯಂ","category":"rice","description":"Premium brown rice.","imageurl":"images\\\\Brown Rice Premium.jpg","weights":[{"label":"1 kg","price":104},{"label":"2 kg","price":208},{"label":"5 kg","price":520},{"label":"10 kg","price":1040},{"label":"15 kg","price":1560},{"label":"25 kg","price":2600}]},
  {"id":13,"name":"Red Rice Kerala","nameKn":"ಕೇರಳ ಕೆಂಪು ಅಕ್ಕಿ","category":"rice","description":"Kerala red rice.","imageurl":"images\\\\Red Rice Kerala.jpg","weights":[{"label":"1 kg","price":130},{"label":"2 kg","price":260},{"label":"5 kg","price":650},{"label":"10 kg","price":1300},{"label":"15 kg","price":1950},{"label":"25 kg","price":3250}]},
  {"id":14,"name":"Red Rice Bulk","nameKn":"ಕೆಂಪು ಅಕ್ಕಿ ಬಲ್ಕ್","category":"rice","description":"Bulk red rice.","imageurl":"images\\\\Red Rice Bulk.jpg","weights":[{"label":"1 kg","price":120},{"label":"2 kg","price":240},{"label":"5 kg","price":600},{"label":"10 kg","price":1200},{"label":"15 kg","price":1800},{"label":"25 kg","price":3000}]},
  {"id":15,"name":"Black Rice Organic","nameKn":"ಕಪ್ಪು ಅಕ್ಕಿ ಸಾವಯವ","category":"rice","description":"Organic black rice.","imageurl":"images\\\\Black Rice Organic.jpg","weights":[{"label":"1 kg","price":180},{"label":"2 kg","price":360},{"label":"5 kg","price":900},{"label":"10 kg","price":1800},{"label":"15 kg","price":2700},{"label":"25 kg","price":4500}]},
  {"id":16,"name":"Jeera Samba Rice","nameKn":"ಜೀರಾ ಸಾಂಬಾ ಅಕ್ಕಿ","category":"rice","description":"Jeera samba rice.","imageurl":"images\\\\Jeera Samba Rice.jpg","weights":[{"label":"1 kg","price":140},{"label":"2 kg","price":280},{"label":"5 kg","price":700},{"label":"10 kg","price":1400},{"label":"15 kg","price":2100},{"label":"25 kg","price":3500}]},
  {"id":17,"name":"Jeera Samba Bulk","nameKn":"ಜೀರಾ ಸಾಂಬಾ ಬಲ್ಕ್","category":"rice","description":"Bulk jeera samba rice.","imageurl":"images\\\\Jeera Samba Bulk.jpg","weights":[{"label":"1 kg","price":136},{"label":"2 kg","price":272},{"label":"5 kg","price":680},{"label":"10 kg","price":1360},{"label":"15 kg","price":2040},{"label":"25 kg","price":3400}]},
  {"id":18,"name":"Matta Rice Kerala","nameKn":"ಕೇರಳ ಮಟ್ಟಾ ಅಕ್ಕಿ","category":"rice","description":"Kerala matta rice.","imageurl":"images\\\\Matta Rice Kerala.jpg","weights":[{"label":"1 kg","price":90},{"label":"2 kg","price":180},{"label":"5 kg","price":450},{"label":"10 kg","price":900},{"label":"15 kg","price":1350},{"label":"25 kg","price":2250}]},
  {"id":19,"name":"Matta Rice Bulk","nameKn":"ಮಟ್ಟಾ ಅಕ್ಕಿ ಬಲ್ಕ್","category":"rice","description":"Bulk matta rice.","imageurl":"images\\\\Matta Rice Bulk.jpg","weights":[{"label":"1 kg","price":350},{"label":"2 kg","price":700},{"label":"5 kg","price":1750},{"label":"10 kg","price":3500}]},
  {"id":20,"name":"Broken Rice Economy","nameKn":"ಒಡೆದ ಅಕ್ಕಿ ಎಕಾನಮಿ","category":"rice","description":"Economy broken rice.","imageurl":"images\\\\Broken Rice Economy.jpg","weights":[{"label":"1 kg","price":40},{"label":"2 kg","price":80},{"label":"5 kg","price":200},{"label":"10 kg","price":400},{"label":"15 kg","price":600},{"label":"25 kg","price":1000}]},
  {"id":21,"name":"Broken Rice Bulk","nameKn":"ಒಡೆದ ಅಕ್ಕಿ ಬಲ್ಕ್","category":"rice","description":"Bulk broken rice.","imageurl":"images\\\\Broken Rice Bulk.jpg","weights":[{"label":"1 kg","price":36},{"label":"2 kg","price":72},{"label":"5 kg","price":180},{"label":"10 kg","price":360},{"label":"15 kg","price":540},{"label":"25 kg","price":900}]},
  {"id":22,"name":"Steam Rice Premium","nameKn":"ಸ್ಟೀಮ್ ಅಕ್ಕಿ ಪ್ರೀಮಿಯಂ","category":"rice","description":"Premium steam rice.","imageurl":"images\\\\Steam Rice Premium.jpg","weights":[{"label":"1 kg","price":95},{"label":"2 kg","price":190},{"label":"5 kg","price":475},{"label":"10 kg","price":950},{"label":"15 kg","price":1425},{"label":"25 kg","price":2375}]},
  {"id":23,"name":"Steam Rice Bulk","nameKn":"ಸ್ಟೀಮ್ ಅಕ್ಕಿ ಬಲ್ಕ್","category":"rice","description":"Bulk steam rice.","imageurl":"images\\\\Steam Rice Bulk.jpg","weights":[{"label":"1 kg","price":90},{"label":"2 kg","price":180},{"label":"5 kg","price":450},{"label":"10 kg","price":900},{"label":"15 kg","price":1350},{"label":"25 kg","price":2250}]},
  {"id":24,"name":"Parboiled Rice","nameKn":"ಪಾರ್ಬಾಯಿಲ್ಡ್ ಅಕ್ಕಿ","category":"rice","description":"Parboiled rice.","imageurl":"images\\\\Parboiled Rice.jpg","weights":[{"label":"1 kg","price":80},{"label":"2 kg","price":160},{"label":"5 kg","price":400},{"label":"10 kg","price":800},{"label":"15 kg","price":1200},{"label":"25 kg","price":2000}]},
  {"id":25,"name":"Parboiled Rice Bulk","nameKn":"ಪಾರ್ಬಾಯಿಲ್ಡ್ ಅಕ್ಕಿ ಬಲ್ಕ್","category":"rice","description":"Bulk parboiled rice.","imageurl":"images\\\\Parboiled Rice Bulk.jpg","weights":[{"label":"1 kg","price":75},{"label":"2 kg","price":150},{"label":"5 kg","price":375},{"label":"10 kg","price":750},{"label":"15 kg","price":1125},{"label":"25 kg","price":1875}]},
  {"id":26,"name":"Organic Sona Masoori Rice","nameKn":"ಸಾವಯವ ಸೋನಾ ಮಸೂರಿ ಅಕ್ಕಿ","category":"rice","description":"Organic Sona Masoori rice.","imageurl":"images\\\\Organic Sona Masoori Rice.jpg","weights":[{"label":"1 kg","price":130},{"label":"2 kg","price":260},{"label":"5 kg","price":650},{"label":"10 kg","price":1300},{"label":"15 kg","price":1950},{"label":"25 kg","price":3250}]},
  {"id":27,"name":"Organic Basmati Rice","nameKn":"ಸಾವಯವ ಬಾಸ್ಮತಿ ಅಕ್ಕಿ","category":"rice","description":"Organic basmati rice.","imageurl":"images\\\\Organic Basmati Rice.jpg","weights":[{"label":"1 kg","price":180},{"label":"2 kg","price":360},{"label":"5 kg","price":900},{"label":"10 kg","price":1800},{"label":"15 kg","price":2700},{"label":"25 kg","price":4500}]},
  {"id":28,"name":"Mini Mogra Rice","nameKn":"ಮಿನಿ ಮೊಗ್ರಾ ಅಕ್ಕಿ","category":"rice","description":"Mini mogra rice.","imageurl":"images\\\\Mini Mogra Rice.jpg","weights":[{"label":"1 kg","price":100},{"label":"2 kg","price":200},{"label":"5 kg","price":500},{"label":"10 kg","price":1000},{"label":"15 kg","price":1500},{"label":"25 kg","price":2500}]},
  {"id":29,"name":"Mini Mogra Bulk","nameKn":"ಮಿನಿ ಮೊಗ್ರಾ ಬಲ್ಕ್","category":"rice","description":"Bulk mini mogra rice.","imageurl":"images\\\\Mini Mogra Bulk.jpg","weights":[{"label":"1 kg","price":96},{"label":"2 kg","price":192},{"label":"5 kg","price":480},{"label":"10 kg","price":960},{"label":"15 kg","price":1440},{"label":"25 kg","price":2400}]},
  {"id":30,"name":"Gobindobhog Rice","nameKn":"ಗೋಬಿಂದೋಭೋಗ್ ಅಕ್ಕಿ","category":"rice","description":"Gobindobhog rice.","imageurl":"images\\\\Gobindobhog Rice.jpg","weights":[{"label":"1 kg","price":160},{"label":"2 kg","price":320},{"label":"5 kg","price":800},{"label":"10 kg","price":1600},{"label":"15 kg","price":2400},{"label":"25 kg","price":4000}]},
  {"id":31,"name":"Jasmine Rice Imported","nameKn":"ಜಾಸ್ಮಿನ್ ಅಕ್ಕಿ ಆಮದು","category":"rice","description":"Imported jasmine rice.","imageurl":"images\\\\Jasmine Rice Imported.jpg","weights":[{"label":"1 kg","price":200},{"label":"2 kg","price":400},{"label":"5 kg","price":1000},{"label":"10 kg","price":2000},{"label":"15 kg","price":3000},{"label":"25 kg","price":5000}]},
  {"id":32,"name":"Thai Jasmine Rice","nameKn":"ಥಾಯ್ ಜಾಸ್ಮಿನ್ ಅಕ್ಕಿ","category":"rice","description":"Thai jasmine rice.","imageurl":"images\\\\Thai Jasmine Rice.jpg","weights":[{"label":"1 kg","price":190},{"label":"2 kg","price":380},{"label":"5 kg","price":950},{"label":"10 kg","price":1900},{"label":"15 kg","price":2850},{"label":"25 kg","price":4750}]},
  {"id":33,"name":"Sticky Rice","nameKn":"ಸ್ಟಿಕಿ ಅಕ್ಕಿ","category":"rice","description":"Sticky rice.","imageurl":"images\\\\Sticky Rice.jpg","weights":[{"label":"1 kg","price":150},{"label":"2 kg","price":300},{"label":"5 kg","price":750},{"label":"10 kg","price":1500},{"label":"15 kg","price":2250},{"label":"25 kg","price":3750}]},
  {"id":34,"name":"Sushi Rice","nameKn":"ಸುಶಿ ಅಕ್ಕಿ","category":"rice","description":"Sushi rice.","imageurl":"images\\\\Sushi Rice.jpg","weights":[{"label":"1 kg","price":220},{"label":"2 kg","price":440},{"label":"5 kg","price":1100},{"label":"10 kg","price":2200},{"label":"15 kg","price":3300},{"label":"25 kg","price":5500}]},
  {"id":35,"name":"Arborio Rice","nameKn":"ಅರ್ಬೋರಿಯೋ ಅಕ್ಕಿ","category":"rice","description":"Arborio rice.","imageurl":"images\\\\Arborio Rice.jpg","weights":[{"label":"1 kg","price":250},{"label":"2 kg","price":500},{"label":"5 kg","price":1250},{"label":"10 kg","price":2500},{"label":"15 kg","price":3750},{"label":"25 kg","price":6250}]},
  {"id":36,"name":"Rice Flour Pack","nameKn":"ಅಕ್ಕಿ ಹಿಟ್ಟು ಪ್ಯಾಕ್","category":"flour","description":"Rice flour.","imageurl":"images\\\\Rice Flour Pack.jpg","weights":[{"label":"1 kg","price":60},{"label":"2 kg","price":120},{"label":"5 kg","price":300},{"label":"10 kg","price":600},{"label":"15 kg","price":900},{"label":"25 kg","price":1500}]},
  {"id":37,"name":"Rice Rava","nameKn":"ಅಕ್ಕಿ ರವೆ","category":"flour","description":"Rice rava.","imageurl":"images\\\\Rice Rava.jpg","weights":[{"label":"1 kg","price":55},{"label":"2 kg","price":110},{"label":"5 kg","price":275},{"label":"10 kg","price":550},{"label":"15 kg","price":825},{"label":"25 kg","price":1375}]},
  {"id":38,"name":"Flattened Rice (Poha Thick)","nameKn":"ದಪ್ಪ ಪೋಹಾ","category":"flour","description":"Thick poha.","imageurl":"images\\\\Flattened Rice (Poha Thick).jpg","weights":[{"label":"1 kg","price":70},{"label":"2 kg","price":140},{"label":"5 kg","price":350},{"label":"10 kg","price":700},{"label":"15 kg","price":1050},{"label":"25 kg","price":1750}]},
  {"id":39,"name":"Flattened Rice (Poha Thin)","nameKn":"ತೆಳು ಪೋಹಾ","category":"flour","description":"Thin poha.","imageurl":"images\\\\Flattened Rice (Poha Thin).jpg","weights":[{"label":"1 kg","price":65},{"label":"2 kg","price":130},{"label":"5 kg","price":325},{"label":"10 kg","price":650},{"label":"15 kg","price":975},{"label":"25 kg","price":1625}]},
  {"id":40,"name":"Puffed Rice (Murmura)","nameKn":"ಮುರ್ಮುರಾ","category":"snacks","description":"Puffed rice.","imageurl":"images\\\\Puffed Rice (Murmura).jpg","weights":[{"label":"1 kg","price":80},{"label":"2 kg","price":160},{"label":"5 kg","price":400},{"label":"10 kg","price":800},{"label":"15 kg","price":1200},{"label":"25 kg","price":2000}]},
  {"id":41,"name":"Rice Bran Oil Pack Rice","nameKn":"ರೈಸ್ ಬ್ರಾನ್ ಆಯಿಲ್ ಪ್ಯಾಕ್ ಅಕ್ಕಿ","category":"rice","description":"Rice bran oil mixed rice.","imageurl":"images\\\\Rice Bran Oil Pack Rice.jpg","weights":[{"label":"1 kg","price":90},{"label":"2 kg","price":180},{"label":"5 kg","price":450},{"label":"10 kg","price":900},{"label":"15 kg","price":1350},{"label":"25 kg","price":2250}]},
  {"id":42,"name":"Raw Rice Local","nameKn":"ಸ್ಥಳೀಯ ಕಚ್ಚಾ ಅಕ್ಕಿ","category":"rice","description":"Local raw rice.","imageurl":"images\\\\Raw Rice Local.jpg","weights":[{"label":"1 kg","price":50},{"label":"2 kg","price":100},{"label":"5 kg","price":250},{"label":"10 kg","price":500},{"label":"15 kg","price":750},{"label":"25 kg","price":1250}]},
  {"id":43,"name":"Raw Rice Bulk","nameKn":"ಕಚ್ಚಾ ಅಕ್ಕಿ ಬಲ್ಕ್","category":"rice","description":"Bulk raw rice.","imageurl":"images\\\\Raw Rice Bulk.jpg","weights":[{"label":"1 kg","price":44},{"label":"2 kg","price":88},{"label":"5 kg","price":220},{"label":"10 kg","price":440},{"label":"15 kg","price":660},{"label":"25 kg","price":1100}]},
  {"id":44,"name":"Premium Export Basmati","nameKn":"ಪ್ರೀಮಿಯಂ ಎಕ್ಸ್ಪೋರ್ಟ್ ಬಾಸ್ಮತಿ","category":"rice","description":"Premium export basmati.","imageurl":"images\\\\Premium Export Basmati.jpg","weights":[{"label":"1 kg","price":150},{"label":"2 kg","price":300},{"label":"5 kg","price":750},{"label":"10 kg","price":1500},{"label":"15 kg","price":2250},{"label":"25 kg","price":3750}]},
  {"id":45,"name":"Deluxe Aged Basmati Rice","nameKn":"ಡೀಲಕ್ಸ್ ಏಜ್ಡ್ ಬಾಸ್ಮತಿ","category":"rice","description":"Deluxe aged basmati.","imageurl":"images\\\\Deluxe Aged Basmati Rice.jpg","weights":[{"label":"1 kg","price":180},{"label":"2 kg","price":360},{"label":"5 kg","price":900},{"label":"10 kg","price":1800},{"label":"15 kg","price":2700},{"label":"25 kg","price":4500}]},
  {"id":46,"name":"Daily Use Rice Pack","nameKn":"ದೈನಂದಿನ ಬಳಕೆಯ ಅಕ್ಕಿ ಪ್ಯಾಕ್","category":"rice","description":"Daily use rice.","imageurl":"images\\\\Daily Use Rice Pack.jpg","weights":[{"label":"1 kg","price":60},{"label":"2 kg","price":120},{"label":"5 kg","price":300},{"label":"10 kg","price":600},{"label":"15 kg","price":900},{"label":"25 kg","price":1500}]},
  {"id":47,"name":"Economy Rice Pack","nameKn":"ಎಕಾನಮಿ ಅಕ್ಕಿ ಪ್ಯಾಕ್","category":"rice","description":"Economy rice pack.","imageurl":"images\\\\Economy Rice Pack.jpg","weights":[{"label":"1 kg","price":55},{"label":"2 kg","price":110},{"label":"5 kg","price":275},{"label":"10 kg","price":550},{"label":"15 kg","price":825},{"label":"25 kg","price":1375}]},
  {"id":48,"name":"Family Pack Rice","nameKn":"ಫ್ಯಾಮಿಲಿ ಪ್ಯಾಕ್ ಅಕ್ಕಿ","category":"rice","description":"Family pack rice.","imageurl":"images\\\\Family Pack Rice.jpg","weights":[{"label":"1 kg","price":50},{"label":"2 kg","price":100},{"label":"5 kg","price":250},{"label":"10 kg","price":500},{"label":"15 kg","price":750},{"label":"25 kg","price":1250}]},
  {"id":49,"name":"Super Fine Rice","nameKn":"ಸೂಪರ್ ಫೈನ್ ಅಕ್ಕಿ","category":"rice","description":"Super fine rice.","imageurl":"images\\\\Super Fine Rice.jpg","weights":[{"label":"1 kg","price":110},{"label":"2 kg","price":220},{"label":"5 kg","price":550},{"label":"10 kg","price":1100},{"label":"15 kg","price":1650},{"label":"25 kg","price":2750}]},
  {"id":50,"name":"Festival Special Rice","nameKn":"ಹಬ್ಬದ ವಿಶೇಷ ಅಕ್ಕಿ","category":"rice","description":"Festival special rice.","imageurl":"images\\\\Festival Special Rice.jpg","weights":[{"label":"1 kg","price":120},{"label":"2 kg","price":240},{"label":"5 kg","price":600},{"label":"10 kg","price":1200},{"label":"15 kg","price":1800},{"label":"25 kg","price":3000}]},
  {"id":51,"name":"Whole Wheat Atta Organic","nameKn":"ಸಂಪೂರ್ಣ ಗೋಧಿ ಅಟ್ಟ ಸಾವಯವ","category":"atta","description":"Organic whole wheat flour for nutritious chapatis.","image":"images\\\\pulses\\\\Whole Wheat Atta Organic.jpg","weights":[{"label":"1kg","price":85},{"label":"5kg","price":420},{"label":"10kg","price":820}]},
  {"id":52,"name":"Multigrain Atta Premium","nameKn":"ಮಲ್ಟಿಗ್ರೇನ್ ಅಟ್ಟ ಪ್ರೀಮಿಯಂ","category":"atta","description":"Premium multigrain flour for healthy rotis.","image":"images\\\\pulses\\\\Multigrain Atta Premium.jpg","weights":[{"label":"1kg","price":95},{"label":"5kg","price":470},{"label":"10kg","price":920}]},
  {"id":53,"name":"Maida Refined Flour","nameKn":"ಮೈದಾ ಸಂಸ್ಕರಿತ ಹಿಟ್ಟು","category":"flour","description":"Refined white flour for baking and confectionery.","image":"images\\\\pulses\\\\Maida Refined Flour 60.jpg","weights":[{"label":"1kg","price":55},{"label":"5kg","price":260},{"label":"10kg","price":510}]},
  {"id":54,"name":"Sooji / Rava Fine","nameKn":"ಸೂಜಿ / ರವೆ ಫೈನ್","category":"rava","description":"Fine semolina for upma, halwa and idli.","image":"images\\\\pulses\\\\Sooji  Rava Fine 500g 40.jpg","weights":[{"label":"500g","price":35},{"label":"1kg","price":70},{"label":"5kg","price":330}]},
  {"id":55,"name":"Besan Gram Flour","nameKn":"ಬೆಸನ್ ಕಡಲೆ ಹಿಟ್ಟು","category":"besan","description":"Fine gram flour for pakoras and sweets.","image":"images\\\\pulses\\\\Besan Gram Flour 45.jpg","weights":[{"label":"500g","price":60},{"label":"1kg","price":115},{"label":"5kg","price":560}]},
  {"id":56,"name":"Rice Flour","nameKn":"ಅಕ್ಕಿ ಹಿಟ್ಟು","category":"flour","description":"Fine rice flour for idli, dosa and snacks.","image":"images\\\\pulses\\\\Organic Rice Flour 70.jpg","weights":[{"label":"500g","price":40},{"label":"1kg","price":75},{"label":"5kg","price":360}]},
  {"id":57,"name":"Corn Flour","nameKn":"ಕಾರ್ನ್ ಹಿಟ್ಟು","category":"flour","description":"Yellow corn flour for baking and thickening.","image":"images\\\\pulses\\\\Corn Flour 172.jpg","weights":[{"label":"200g","price":35},{"label":"500g","price":75},{"label":"1kg","price":140}]},
  {"id":58,"name":"Jowar Flour","nameKn":"ಜೋಳದ ಹಿಟ್ಟು","category":"millet","description":"Gluten‑free sorghum flour for bhakri and roti.","image":"images\\\\pulses\\\\Good Life Jowar Flour 500 g 40.jpg","weights":[{"label":"1kg","price":70},{"label":"5kg","price":330}]},
  {"id":59,"name":"Bajra Flour","nameKn":"ಬಜ್ರಾ ಹಿಟ್ಟು","category":"millet","description":"Pearl millet flour for traditional rotlas.","image":"images\\\\pulses\\\\Bajra Flour 69.jpg","weights":[{"label":"1kg","price":65},{"label":"5kg","price":320}]},
  {"id":60,"name":"Ragi Flour","nameKn":"ರಾಗಿ ಹಿಟ್ಟು","category":"millet","description":"Finger millet flour for nutritious ragi mudde.","image":"images\\\\pulses\\\\Ragi Flour 46.jpg","weights":[{"label":"500g","price":45},{"label":"1kg","price":85},{"label":"5kg","price":410}]},
  {"id":62,"name":"Barley Flour","nameKn":"ಬಾರ್ಲಿ ಹಿಟ್ಟು","category":"flour","description":"Healthy barley flour for chapatis and baking.","image":"images\\\\pulses\\\\Barley Flour 200g 110.jpg","weights":[{"label":"500g","price":50},{"label":"1kg","price":95},{"label":"5kg","price":460}]},
  {"id":63,"name":"Oats Flour","nameKn":"ಓಟ್ಸ್ ಹಿಟ್ಟು","category":"flour","description":"Oats flour for healthy baking and breakfast.","image":"images\\\\pulses\\\\Oats Flour 200g 30.jpg","weights":[{"label":"500g","price":80},{"label":"1kg","price":150},{"label":"5kg","price":720}]},
  {"id":64,"name":"Soybean Flour","nameKn":"ಸೋಯಾಬೀನ್ ಹಿಟ್ಟು","category":"flour","description":"High‑protein soy flour for nutrition.","image":"images\\\\pulses\\\\Soybean Flour 500g 75.jpg","weights":[{"label":"500g","price":70},{"label":"1kg","price":130},{"label":"5kg","price":640}]},
  {"id":65,"name":"Millet Flour Mix","nameKn":"ಮಿಲೆಟ್ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"millet","description":"Blend of various millet flours.","image":"images\\\\pulses\\\\Millet Flour Mix 80g 20.jpg","weights":[{"label":"1kg","price":120},{"label":"5kg","price":580}]},
  {"id":66,"name":"Buckwheat Flour","nameKn":"ಬಕ್‌ವೀಟ್ ಹಿಟ್ಟು","category":"flour","description":"Gluten‑free buckwheat flour for pancakes.","image":"images\\\\pulses\\\\Buckwheat Flour 500g 220.jpg","weights":[{"label":"500g","price":95},{"label":"1kg","price":180}]},
  {"id":67,"name":"Quinoa Flour","nameKn":"ಕಿನೋವಾ ಹಿಟ್ಟು","category":"flour","description":"Nutrient‑dense quinoa flour.","image":"images\\\\pulses\\\\Quinoa Flour 40.jpg","weights":[{"label":"500g","price":150},{"label":"1kg","price":300}]},
  {"id":68,"name":"Almond Flour","nameKn":"ಬಾದಾಮಿ ಹಿಟ್ಟು","category":"nutflour","description":"Finely ground almond flour for keto baking.","image":"images\\\\pulses\\\\Almond Flour 500.jpg","weights":[{"label":"200g","price":180},{"label":"500g","price":420}]},
  {"id":69,"name":"Coconut Flour","nameKn":"ತೆಂಗಿನ ಹಿಟ್ಟು","category":"nutflour","description":"High‑fiber coconut flour for low‑carb recipes.","image":"images\\\\pulses\\\\Coconut Flour 183.jpg","weights":[{"label":"200g","price":110},{"label":"500g","price":260}]},
  {"id":70,"name":"Sattu Flour","nameKn":"ಸತ್ತು ಹಿಟ್ಟು","category":"flour","description":"Roasted gram flour for cooling drinks and parathas.","image":"images\\\\pulses\\\\Sattu Flour 100.jpg","weights":[{"label":"500g","price":65},{"label":"1kg","price":120},{"label":"5kg","price":580}]},
  {"id":71,"name":"Idli Rice Flour","nameKn":"ಇಡ್ಲಿ ಅಕ್ಕಿ ಹಿಟ್ಟು","category":"flour","description":"Special rice flour for fluffy idlis.","image":"images\\\\pulses\\\\Idli Rice Flour 80.jpg","weights":[{"label":"1kg","price":80}]},
  {"id":72,"name":"Dosa Flour Mix","nameKn":"ದೋಸಾ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Ready‑to‑use dosa flour mix.","image":"images\\\\pulses\\\\Dosa Flour Mix 125.jpg","weights":[{"label":"500g","price":65}]},
  {"id":73,"name":"Instant Roti Mix","nameKn":"ತ್ವರಿತ ರೊಟ್ಟಿ ಮಿಶ್ರಣ","category":"flour","description":"Quick roti flour mix.","image":"images\\\\pulses\\\\Instant Roti Mix 350g 88.jpg","weights":[{"label":"500g","price":70}]},
  {"id":74,"name":"Pizza Flour","nameKn":"ಪಿಜ್ಜಾ ಹಿಟ್ಟು","category":"flour","description":"High‑protein flour for pizza bases.","image":"images\\\\pulses\\\\Pizza Flour 300.jpg","weights":[{"label":"500g","price":90}]},
  {"id":75,"name":"Bread Flour","nameKn":"ಬ್ರೆಡ್ ಹಿಟ್ಟು","category":"flour","description":"Strong flour for home‑baked bread.","image":"images\\\\pulses\\\\Bread Flour.jpg","weights":[{"label":"1kg","price":120}]},
  {"id":76,"name":"Cake Flour","nameKn":"ಕೇಕ್ ಹಿಟ್ಟು","category":"flour","description":"Soft flour for light and airy cakes.","image":"images\\\\pulses\\\\Cake Flour 350.jpg","weights":[{"label":"500g","price":95}]},
  {"id":77,"name":"Pancake Mix Flour","nameKn":"ಪ್ಯಾನ್‌ಕೇಕ್ ಮಿಶ್ರಣ ಹಿಟ್ಟು","category":"flour","description":"Ready pancake mix with flour.","image":"images\\\\pulses\\\\Pancake Mix Flour 266.jpg","weights":[{"label":"500g","price":120}]},
  {"id":78,"name":"Tempura Flour","nameKn":"ಟೆಂಪುರಾ ಹಿಟ್ಟು","category":"flour","description":"Special flour for crispy tempura.","image":"images\\\\pulses\\\\Tempura Flour 305.jpg","weights":[{"label":"500g","price":140}]},
  {"id":79,"name":"Self Raising Flour","nameKn":"ಸ್ವಯಂ ಏರುವ ಹಿಟ್ಟು","category":"flour","description":"Flour with baking powder added.","image":"images\\\\pulses\\\\Self Raising Flour 485.jpg","weights":[{"label":"1kg","price":130}]},
  {"id":80,"name":"Gluten Free Flour Mix","nameKn":"ಗ್ಲುಟನ್ ಮುಕ್ತ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Blend of gluten‑free flours.","image":"images\\\\pulses\\\\Gluten Free Flour Mix 210.jpg","weights":[{"label":"500g","price":210}]},
  {"id":81,"name":"Sprouted Wheat Flour","nameKn":"ಮೊಳಕೆಯೊಡೆದ ಗೋಧಿ ಹಿಟ್ಟು","category":"atta","description":"Nutrient‑rich sprouted wheat flour.","image":"images\\\\pulses\\\\Sprouted Wheat Flour 160.jpg","weights":[{"label":"1kg","price":140}]},
  {"id":82,"name":"Durum Wheat Flour","nameKn":"ಡ್ಯುರಮ್ ಗೋಧಿ ಹಿಟ್ಟು","category":"flour","description":"High‑protein durum wheat for pasta.","image":"images\\\\pulses\\\\Durum Wheat Flour 500g 41.jpg","weights":[{"label":"1kg","price":120}]},
  {"id":83,"name":"Semolina Coarse","nameKn":"ಒರಟು ರವೆ","category":"rava","description":"Coarse semolina for upma and pasta.","image":"images\\\\pulses\\\\Semolina Coarse 250.jpg","weights":[{"label":"1kg","price":85}]},
  {"id":84,"name":"Semolina Fine","nameKn":"ನಯವಾದ ರವೆ","category":"rava","description":"Fine semolina for halwa and baked goods.","image":"images\\\\pulses\\\\Semolina Fine 30.jpg","weights":[{"label":"1kg","price":90}]},
  {"id":85,"name":"Organic Wheat Flour","nameKn":"ಸಾವಯವ ಗೋಧಿ ಹಿಟ್ಟು","category":"atta","description":"Certified organic whole wheat flour.","image":"images\\\\pulses\\\\Organic Wheat Flour 80.jpg","weights":[{"label":"1kg","price":95},{"label":"5kg","price":470},{"label":"10kg","price":920}]},
  {"id":86,"name":"Whole Grain Flour Mix","nameKn":"ಸಂಪೂರ್ಣ ಧಾನ್ಯ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Mix of whole grains for bread making.","image":"images\\\\pulses\\\\Whole Grain Flour Mix 280.jpg","weights":[{"label":"1kg","price":130},{"label":"5kg","price":640}]},
  {"id":87,"name":"Protein Flour Mix","nameKn":"ಪ್ರೋಟೀನ್ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"High‑protein flour blend.","image":"images\\\\pulses\\\\Protein Flour Mix 600.jpg","weights":[{"label":"500g","price":180},{"label":"1kg","price":340}]},
  {"id":88,"name":"High Fiber Flour","nameKn":"ಹೆಚ್ಚಿನ ಫೈಬರ್ ಹಿಟ್ಟು","category":"flour","description":"Extra fiber enriched flour.","image":"images\\\\pulses\\\\High Fiber Flour 400.jpg","weights":[{"label":"1kg","price":150},{"label":"5kg","price":720}]},
  {"id":89,"name":"Low Carb Flour Mix","nameKn":"ಕಡಿಮೆ ಕಾರ್ಬ್ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Keto‑friendly low carb flour.","image":"images\\\\pulses\\\\Low Carb Flour Mix 500.jpg","weights":[{"label":"500g","price":240},{"label":"1kg","price":450}]},
  {"id":90,"name":"Amaranth Flour","nameKn":"ಅಮರಂತ್ ಹಿಟ್ಟು","category":"flour","description":"Gluten‑free amaranth flour.","image":"images\\\\pulses\\\\Amaranth Flour 280.jpg","weights":[{"label":"500g","price":110},{"label":"1kg","price":210}]},
  {"id":91,"name":"Teff Flour","nameKn":"ಟೆಫ್ ಹಿಟ್ಟು","category":"flour","description":"Ancient grain teff flour.","image":"images\\\\pulses\\\\Teff Flour 1800.jpg","weights":[{"label":"500g","price":260},{"label":"1kg","price":500}]},
  {"id":92,"name":"Cassava Flour","nameKn":"ಕಸ್ಸಾವಾ ಹಿಟ್ಟು","category":"flour","description":"Gluten‑free cassava flour.","image":"images\\\\pulses\\\\Cassava Flour 190.jpg","weights":[{"label":"500g","price":220},{"label":"1kg","price":420}]},
  {"id":93,"name":"Arrowroot Flour","nameKn":"ಅರೋರುಟ್ ಹಿಟ್ಟು","category":"flour","description":"Starch powder for thickening.","image":"images\\\\pulses\\\\Arrowroot Flour 300.jpg","weights":[{"label":"500g","price":140},{"label":"1kg","price":270}]},
  {"id":94,"name":"Potato Flour","nameKn":"ಆಲೂಗಡ್ಡೆ ಹಿಟ್ಟು","category":"flour","description":"Potato starch flour for baking.","image":"images\\\\pulses\\\\Potato Flour 290.jpg","weights":[{"label":"500g","price":150},{"label":"1kg","price":300}]},
  {"id":95,"name":"Chickpea Flour Premium","nameKn":"ಕಡಲೆ ಹಿಟ್ಟು ಪ್ರೀಮಿಯಂ","category":"besan","description":"Premium chickpea flour for crispy snacks.","image":"images\\\\pulses\\\\Chickpea Flour Premium.jpg","weights":[{"label":"1kg","price":130},{"label":"5kg","price":640}]},
  {"id":96,"name":"Cornmeal Flour","nameKn":"ಕಾರ್ನ್‌ಮೀಲ್ ಹಿಟ್ಟು","category":"flour","description":"Coarse cornmeal for polenta and breading.","image":"images\\\\pulses\\\\Cornmeal Flour 70.jpg","weights":[{"label":"1kg","price":90},{"label":"5kg","price":420}]},
  {"id":97,"name":"Polenta Flour","nameKn":"ಪೋಲೆಂಟಾ ಹಿಟ್ಟು","category":"flour","description":"Italian style polenta cornmeal.","image":"images\\\\pulses\\\\Polenta Flour 450.jpg","weights":[{"label":"500g","price":120},{"label":"1kg","price":230}]},
  {"id":98,"name":"Sorghum Flour","nameKn":"ಜೋಳದ ಹಿಟ್ಟು","category":"millet","description":"Gluten‑free sorghum flour.","image":"images\\\\pulses\\\\Sorghum Flour 300.jpg","weights":[{"label":"1kg","price":95},{"label":"5kg","price":460}]},
  {"id":99,"name":"Spelt Flour","nameKn":"ಸ್ಪೆಲ್ಟ್ ಹಿಟ್ಟು","category":"flour","description":"Ancient grain spelt flour.","image":"images\\\\pulses\\\\Spelt Flour 200.jpg","weights":[{"label":"500g","price":240},{"label":"1kg","price":460}]},
  {"id":100,"name":"Rye Flour","nameKn":"ರೈ ಹಿಟ್ಟು","category":"flour","description":"Dark rye flour for sourdough.","image":"images\\\\pulses\\\\Rye Flour 550.jpg","weights":[{"label":"500g","price":180},{"label":"1kg","price":350}]},
  {"id":102,"name":"Lentil Flour","nameKn":"ಮಸೂರ ಹಿಟ್ಟು","category":"pulseflour","description":"Red lentil flour for protein‑rich rotis.","image":"images\\\\pulses\\\\Lentil Flour 300.jpg","weights":[{"label":"500g","price":150},{"label":"1kg","price":280}]},
  {"id":104,"name":"Green Gram Flour","nameKn":"ಹೆಸರು ಕಾಳು ಹಿಟ್ಟು","category":"pulseflour","description":"Moong dal flour for high‑protein dishes.","image":"images\\\\pulses\\\\Green Gram Flour.jpg","weights":[{"label":"500g","price":140},{"label":"1kg","price":260}]},
  {"id":106,"name":"Black Gram Flour","nameKn":"ಉದ್ದಿನ ಹಿಟ್ಟು","category":"pulseflour","description":"Urad dal flour for crispy vadas.","image":"images\\\\pulses\\\\Black Gram Flour 140.jpg","weights":[{"label":"500g","price":150},{"label":"1kg","price":300}]},
  {"id":108,"name":"Mixed Pulse Flour","nameKn":"ಮಿಶ್ರ ದ್ವಿದಳ ಧಾನ್ಯ ಹಿಟ್ಟು","category":"pulseflour","description":"Blend of pulses for nutrition.","image":"images\\\\pulses\\\\Mixed Pulse Flour 30.jpg","weights":[{"label":"500g","price":160},{"label":"1kg","price":300}]},
  {"id":110,"name":"Organic Millet Flour","nameKn":"ಸಾವಯವ ಮಿಲೆಟ್ ಹಿಟ್ಟು","category":"millet","description":"Certified organic millet flour.","image":"images\\\\pulses\\\\Organic Millet Flour 200.jpg","weights":[{"label":"500g","price":120},{"label":"1kg","price":230}]},
  {"id":112,"name":"Organic Ragi Flour","nameKn":"ಸಾವಯವ ರಾಗಿ ಹಿಟ್ಟು","category":"millet","description":"Organic finger millet flour.","image":"images\\\\pulses\\\\Organic Ragi Flour 100.jpg","weights":[{"label":"500g","price":70},{"label":"1kg","price":130}]},
  {"id":101,"name":"Organic Jowar Flour","nameKn":"ಸಾವಯವ ಜೋಳದ ಹಿಟ್ಟು","category":"millet","description":"Organic sorghum flour.","image":"images\\\\pulses\\\\Organic Jowar Flour 70.jpg","weights":[{"label":"500g","price":75},{"label":"1kg","price":140}]},
  {"id":103,"name":"Organic Bajra Flour","nameKn":"ಸಾವಯವ ಬಜ್ರಾ ಹಿಟ್ಟು","category":"millet","description":"Organic pearl millet flour.","image":"images\\\\pulses\\\\Organic Bajra Flour 75.jpg","weights":[{"label":"500g","price":70},{"label":"1kg","price":135}]},
  {"id":105,"name":"Organic Rice Flour","nameKn":"ಸಾವಯವ ಅಕ್ಕಿ ಹಿಟ್ಟು","category":"flour","description":"Organic rice flour for gluten‑free cooking.","image":"images\\\\pulses\\\\Organic Rice Flour 70.jpg","weights":[{"label":"500g","price":60},{"label":"1kg","price":115}]},
  {"id":107,"name":"Organic Besan Flour","nameKn":"ಸಾವಯವ ಬೆಸನ್ ಹಿಟ್ಟು","category":"besan","description":"Organic gram flour.","image":"images\\\\pulses\\\\Organic Besan Flour 200.jpg","weights":[{"label":"500g","price":85},{"label":"1kg","price":160}]},
  {"id":109,"name":"Instant Idli Mix Flour","nameKn":"ತ್ವರಿತ ಇಡ್ಲಿ ಮಿಶ್ರಣ ಹಿಟ್ಟು","category":"flour","description":"Ready idli flour mix.","image":"images\\\\pulses\\\\Instant Idli Mix Flour.jpg","weights":[{"label":"500g","price":75}]},
  {"id":113,"name":"Aashirvaad Whole Wheat Atta","nameKn":"ಆಶೀರ್ವಾದ್ ಸಂಪೂರ್ಣ ಗೋಧಿ ಅಟ್ಟ","category":"wheat","description":"Aashirvaad whole wheat atta.","imageurl":"images\\\\Aashirvaad Whole Wheat Atta.jpg","weights":[{"label":"1 kg","price":60},{"label":"2 kg","price":120},{"label":"5 kg","price":300},{"label":"10 kg","price":600},{"label":"15 kg","price":900},{"label":"25 kg","price":1500}]},
  {"id":114,"name":"Instant Dosa Mix Flour","nameKn":"ತ್ವರಿತ ದೋಸಾ ಮಿಶ್ರಣ ಹಿಟ್ಟು","category":"flour","description":"Ready dosa flour mix.","image":"images\\\\pulses\\\\Instant Dosa Mix Flour.jpg","weights":[{"label":"500g","price":75}]},
  {"id":115,"name":"Upma Rava Mix","nameKn":"ಉಪ್ಪಿಟ್ಟು ರವೆ ಮಿಶ್ರಣ","category":"rava","description":"Ready upma semolina mix.","image":"images\\\\pulses\\\\Upma Rava Mix 40.jpg","weights":[{"label":"500g","price":65}]},
  {"id":116,"name":"Chapati Atta Economy","nameKn":"ಚಪಾತಿ ಅಟ್ಟ ಎಕಾನಮಿ","category":"atta","description":"Economy whole wheat atta for daily use.","image":"images\\\\pulses\\\\Chapati Atta Economy 230.jpg","weights":[{"label":"5kg","price":260},{"label":"10kg","price":500}]},
  {"id":117,"name":"Chapati Atta Premium","nameKn":"ಚಪಾತಿ ಅಟ್ಟ ಪ್ರೀಮಿಯಂ","category":"atta","description":"Premium quality atta for soft chapatis.","image":"images\\\\pulses\\\\Chapati Atta Premium 263.jpg.webp","weights":[{"label":"5kg","price":310},{"label":"10kg","price":610}]},
  {"id":118,"name":"Stone Ground Wheat Flour","nameKn":"ಕಲ್ಲಿನ ಗಿರಣಿ ಗೋಧಿ ಹಿಟ್ಟು","category":"atta","description":"Traditional stone‑ground whole wheat flour.","image":"images\\\\pulses\\\\Stone Ground Wheat Flour 400.jpg","weights":[{"label":"1kg","price":90},{"label":"5kg","price":440},{"label":"10kg","price":860}]},
  {"id":119,"name":"Organic Multigrain Atta","nameKn":"ಸಾವಯವ ಮಲ್ಟಿಗ್ರೇನ್ ಅಟ್ಟ","category":"atta","description":"Organic blend of healthy grains.","image":"images\\\\pulses\\\\Organic Multigrain Atta 484.jpg","weights":[{"label":"1kg","price":140},{"label":"5kg","price":680},{"label":"10kg","price":1320}]},
  {"id":120,"name":"Protein Wheat Atta","nameKn":"ಪ್ರೋಟೀನ್ ಗೋಧಿ ಅಟ್ಟ","category":"atta","description":"High‑protein wheat flour for fitness.","image":"images\\\\pulses\\\\Protein Wheat Atta 115.jpg","weights":[{"label":"1kg","price":130},{"label":"5kg","price":640}]},
  {"id":121,"name":"Diabetic Friendly Atta","nameKn":"ಮಧುಮೇಹ ಸ್ನೇಹಿ ಅಟ್ಟ","category":"atta","description":"Low‑GI flour for diabetics.","image":"images\\\\pulses\\\\Diabetic Friendly Atta 490.jpg","weights":[{"label":"1kg","price":150},{"label":"5kg","price":720}]},
  {"id":122,"name":"Kids Nutrition Atta","nameKn":"ಮಕ್ಕಳ ಪೋಷಣೆ ಅಟ್ಟ","category":"atta","description":"Nutrient‑enriched atta for children.","image":"images\\\\pulses\\\\Kids Nutrition Atta 205.jpg","weights":[{"label":"1kg","price":140},{"label":"5kg","price":680}]},
  {"id":123,"name":"Senior Health Atta","nameKn":"ಹಿರಿಯರ ಆರೋಗ್ಯ ಅಟ್ಟ","category":"atta","description":"Easy‑to‑digest atta for seniors.","image":"images\\\\pulses\\\\Senior Health Atta 280.jpg","weights":[{"label":"1kg","price":150},{"label":"5kg","price":720}]},
  {"id":124,"name":"Low GI Atta Flour","nameKn":"ಕಡಿಮೆ ಜಿಐ ಅಟ್ಟ ಹಿಟ್ಟು","category":"atta","description":"Low glycemic index atta.","image":"images\\\\pulses\\\\Low GI Atta Flour 2300.jpg","weights":[{"label":"1kg","price":145},{"label":"5kg","price":700}]},
  {"id":125,"name":"Protein Ragi Mix Flour","nameKn":"ಪ್ರೋಟೀನ್ ರಾಗಿ ಮಿಶ್ರಣ ಹಿಟ್ಟು","category":"millet","description":"High‑protein ragi flour blend.","image":"images\\\\pulses\\\\Protein Ragi Mix Flour 50.jpg","weights":[{"label":"500g","price":120},{"label":"1kg","price":220}]},
  {"id":126,"name":"Energy Grain Flour Mix","nameKn":"ಶಕ್ತಿ ಧಾನ್ಯ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Energy‑boosting grain flour mix.","image":"images\\\\pulses\\\\Energy Grain Flour Mix 285.jpg","weights":[{"label":"500g","price":130},{"label":"1kg","price":250}]},
  {"id":127,"name":"Fitness Flour Mix","nameKn":"ಫಿಟ್ನೆಸ್ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Flour mix for active lifestyles.","image":"images\\\\pulses\\\\Fitness Flour Mix 80g 20.jpg","weights":[{"label":"500g","price":150},{"label":"1kg","price":300}]},
  {"id":128,"name":"Weight Loss Flour Mix","nameKn":"ತೂಕ ಇಳಿಕೆ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Low‑calorie flour for weight management.","image":"images\\\\pulses\\\\Weight Loss Flour Mix.jpg","weights":[{"label":"500g","price":180},{"label":"1kg","price":340}]},
  {"id":129,"name":"Traditional Chakki Atta","nameKn":"ಸಾಂಪ್ರದಾಯಿಕ ಚಕ್ಕಿ ಅಟ್ಟ","category":"atta","description":"Stone‑milled chakki atta.","image":"images\\\\pulses\\\\Traditional Chakki Atta 262.jpg","weights":[{"label":"5kg","price":300},{"label":"10kg","price":590}]},
  {"id":130,"name":"Village Style Atta","nameKn":"ಗ್ರಾಮೀಣ ಶೈಲಿಯ ಅಟ್ಟ","category":"atta","description":"Rustic whole wheat flour.","image":"images\\\\pulses\\\\Village Style Atta 400.jpg","weights":[{"label":"5kg","price":300},{"label":"10kg","price":570}]},
  {"id":131,"name":"Punjabi Wheat Atta","nameKn":"ಪಂಜಾಬಿ ಗೋಧಿ ಅಟ್ಟ","category":"atta","description":"Premium Punjabi wheat flour.","image":"images\\\\pulses\\\\Punjabi Wheat Atta 370.jpg","weights":[{"label":"5kg","price":320},{"label":"10kg","price":620}]},
  {"id":132,"name":"South Indian Rice Flour","nameKn":"ದಕ್ಷಿಣ ಭಾರತದ ಅಕ್ಕಿ ಹಿಟ್ಟು","category":"flour","description":"Fine rice flour for South Indian dishes.","image":"images\\\\pulses\\\\South Indian Rice Flour 60.jpg.webp","weights":[{"label":"1kg","price":85},{"label":"5kg","price":410}]},
  {"id":133,"name":"Kerala Appam Flour","nameKn":"ಕೇರಳ ಅಪ್ಪಂ ಹಿಟ್ಟು","category":"flour","description":"Special flour for soft appams.","image":"images\\\\pulses\\\\Kerala Appam Flour 120.jpg","weights":[{"label":"1kg","price":95},{"label":"5kg","price":460}]},
  {"id":134,"name":"Instant Puttu Flour","nameKn":"ತ್ವರಿತ ಪುಟ್ಟು ಹಿಟ್ಟು","category":"flour","description":"Ready‑to‑use puttu flour.","image":"images\\\\pulses\\\\Instant Puttu Flour 60.jpg","weights":[{"label":"1kg","price":95},{"label":"5kg","price":450}]},
  {"id":135,"name":"Tandoori Roti Flour","nameKn":"ತಂದೂರಿ ರೊಟ್ಟಿ ಹಿಟ್ಟು","category":"flour","description":"Flour for tandoori rotis and naans.","image":"images\\\\pulses\\\\Tandoori Roti Flour500.jpg","weights":[{"label":"1kg","price":110},{"label":"5kg","price":520}]},
  {"id":136,"name":"Naan Flour Mix","nameKn":"ನಾನ್ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Ready mix for soft naans.","image":"images\\\\pulses\\\\Naan Flour Mix 55.jpg","weights":[{"label":"1kg","price":120},{"label":"5kg","price":580}]},
  {"id":137,"name":"Paratha Flour Mix","nameKn":"ಪರಾಠಾ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Flour mix for flaky parathas.","image":"images\\\\pulses\\\\Paratha Flour Mix 115.jpg","weights":[{"label":"1kg","price":115},{"label":"5kg","price":560}]},
  {"id":138,"name":"Bhatura Flour Mix","nameKn":"ಭಟೂರಾ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Mix for fluffy bhaturas.","image":"images\\\\pulses\\\\Bhatura Flour Mix 45.jpg","weights":[{"label":"1kg","price":120},{"label":"5kg","price":580}]},
  {"id":139,"name":"Kachori Flour Mix","nameKn":"ಕಚೋರಿ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Special mix for crispy kachoris.","image":"images\\\\pulses\\\\Kachori Flour Mix 70.jpg","weights":[{"label":"500g","price":95},{"label":"1kg","price":180}]},
  {"id":140,"name":"Pakora Flour Mix","nameKn":"ಪಕೋಡಾ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Ready mix for crispy pakoras.","image":"images\\\\pulses\\\\Pakora Flour Mix 300.jpg","weights":[{"label":"500g","price":85},{"label":"1kg","price":160}]},
  {"id":141,"name":"Dhokla Flour Mix","nameKn":"ಢೋಕ್ಲಾ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Flour mix for soft dhoklas.","image":"images\\\\pulses\\\\Dhokla Flour Mix 120.jpg","weights":[{"label":"500g","price":95},{"label":"1kg","price":180}]},
  {"id":142,"name":"Handvo Flour Mix","nameKn":"ಹಾಂಡ್ವೋ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Mix for traditional handvo.","image":"images\\\\pulses\\\\Handvo Flour Mix 70.jpg","weights":[{"label":"500g","price":95},{"label":"1kg","price":180}]},
  {"id":143,"name":"Thepla Flour Mix","nameKn":"ಥೇಪ್ಲಾ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Mix for soft methi theplas.","image":"images\\\\pulses\\\\Thepla Flour Mix 168.jpg","weights":[{"label":"500g","price":90},{"label":"1kg","price":170}]},
  {"id":144,"name":"Khakhra Flour Mix","nameKn":"ಖಖ್ರಾ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Flour mix for crispy khakhras.","image":"images\\\\pulses\\\\Khakhra Flour Mix 85.jpg","weights":[{"label":"500g","price":95},{"label":"1kg","price":180}]},
  {"id":145,"name":"Whole Wheat Grain","nameKn":"ಸಂಪೂರ್ಣ ಗೋಧಿ ಕಾಳು","category":"wheat","description":"Whole wheat grain.","imageurl":"images\\\\Raw Wheat Bulk 10,25kg 2000.jpg","weights":[{"label":"1 kg","price":45},{"label":"2 kg","price":90},{"label":"5 kg","price":225},{"label":"10 kg","price":450},{"label":"15 kg","price":675},{"label":"25 kg","price":1125}]},
  {"id":146,"name":"Whole Wheat Grain Bulk","nameKn":"ಸಂಪೂರ್ಣ ಗೋಧಿ ಕಾಳು ಬಲ್ಕ್","category":"wheat","description":"Whole wheat grain.","imageurl":"images\\\\Whole Wheat Flour Fine Bulk.jpg","weights":[{"label":"1 kg","price":40},{"label":"2 kg","price":80},{"label":"5 kg","price":200},{"label":"10 kg","price":400},{"label":"15 kg","price":600},{"label":"25 kg","price":1000}]},
  {"id":147,"name":"Broken Wheat (Dalia)","nameKn":"ಒಡೆದ ಗೋಧಿ (ದಾಲಿಯಾ)","category":"wheat","description":"Broken wheat dalia.","imageurl":"images\\\\Broken Wheat (Dalia).jpg","weights":[{"label":"1 kg","price":60},{"label":"2 kg","price":120},{"label":"5 kg","price":300},{"label":"10 kg","price":600},{"label":"15 kg","price":900},{"label":"25 kg","price":1500}]},
  {"id":148,"name":"Broken Wheat (Dalia) Bulk","nameKn":"ಒಡೆದ ಗೋಧಿ ಬಲ್ಕ್","category":"wheat","description":"Broken wheat dalia.","imageurl":"images\\\\Broken Wheat (Dalia) Bulk.jpg","weights":[{"label":"1 kg","price":56},{"label":"2 kg","price":112},{"label":"5 kg","price":280},{"label":"10 kg","price":560},{"label":"15 kg","price":840},{"label":"25 kg","price":1400}]},
  {"id":149,"name":"Roasted Dalia","nameKn":"ಹುರಿದ ದಾಲಿಯಾ","category":"wheat","description":"Roasted dalia.","imageurl":"images\\\\Roasted Dalia.jpg","weights":[{"label":"1 kg","price":70},{"label":"2 kg","price":140},{"label":"5 kg","price":350},{"label":"10 kg","price":700},{"label":"15 kg","price":1050},{"label":"25 kg","price":1750}]},
  {"id":150,"name":"Roasted Dalia Bulk","nameKn":"ಹುರಿದ ದಾಲಿಯಾ ಬಲ್ಕ್","category":"wheat","description":"Roasted dalia.","imageurl":"images\\\\Roasted Dalia Bulk.jpg","weights":[{"label":"1 kg","price":64},{"label":"2 kg","price":128},{"label":"5 kg","price":320},{"label":"10 kg","price":640},{"label":"15 kg","price":960},{"label":"25 kg","price":1600}]},
  {"id":151,"name":"Wheat Flour Maida","nameKn":"ಗೋಧಿ ಹಿಟ್ಟು ಮೈದಾ","category":"wheat","description":"Maida.","imageurl":"images\\\\Maida.jpg","weights":[{"label":"1 kg","price":50},{"label":"2 kg","price":100},{"label":"5 kg","price":250},{"label":"10 kg","price":500},{"label":"15 kg","price":750},{"label":"25 kg","price":1250}]},
  {"id":152,"name":"Maida Bulk Pack","nameKn":"ಮೈದಾ ಬಲ್ಕ್ ಪ್ಯಾಕ್","category":"wheat","description":"Maida.","imageurl":"images\\\\Maida Bulk Pack.jpg","weights":[{"label":"1 kg","price":45},{"label":"2 kg","price":90},{"label":"5 kg","price":225},{"label":"10 kg","price":450},{"label":"15 kg","price":675},{"label":"25 kg","price":1125}]},
  {"id":153,"name":"Suji (Rava)","nameKn":"ಸೂಜಿ (ರವೆ)","category":"wheat","description":"Suji rava.","imageurl":"images\\\\Suji (Rava).jpg","weights":[{"label":"1 kg","price":55},{"label":"2 kg","price":110},{"label":"5 kg","price":275},{"label":"10 kg","price":550},{"label":"15 kg","price":825},{"label":"25 kg","price":1375}]},
  {"id":154,"name":"Suji Bulk Pack","nameKn":"ಸೂಜಿ ಬಲ್ಕ್ ಪ್ಯಾಕ್","category":"wheat","description":"Suji rava.","imageurl":"images\\\\Suji Bulk Pack.jpg","weights":[{"label":"1 kg","price":52},{"label":"2 kg","price":104},{"label":"5 kg","price":260},{"label":"10 kg","price":520},{"label":"15 kg","price":780},{"label":"25 kg","price":1300}]},
  {"id":155,"name":"Fine Sooji","nameKn":"ನಯವಾದ ಸೂಜಿ","category":"wheat","description":"Fine sooji.","imageurl":"images\\\\Fine Sooji.jpg","weights":[{"label":"1 kg","price":60},{"label":"2 kg","price":120},{"label":"5 kg","price":300},{"label":"10 kg","price":600},{"label":"15 kg","price":900},{"label":"25 kg","price":1500}]},
  {"id":157,"name":"Wheat Bran","nameKn":"ಗೋಧಿ ಹೊಟ್ಟು","category":"wheat","description":"Wheat bran.","imageurl":"images\\\\Wheat Bran.jpg","weights":[{"label":"1 kg","price":40},{"label":"2 kg","price":80},{"label":"5 kg","price":200},{"label":"10 kg","price":400},{"label":"15 kg","price":600},{"label":"25 kg","price":1000}]},
  {"id":158,"name":"Wheat Bran Bulk","nameKn":"ಗೋಧಿ ಹೊಟ್ಟು ಬಲ್ಕ್","category":"wheat","description":"Wheat bran.","imageurl":"images\\\\Wheat Bran Bulk.jpg","weights":[{"label":"1 kg","price":35},{"label":"2 kg","price":70},{"label":"5 kg","price":175},{"label":"10 kg","price":350},{"label":"15 kg","price":525},{"label":"25 kg","price":875}]},
  {"id":159,"name":"Organic Wheat Grain","nameKn":"ಸಾವಯವ ಗೋಧಿ ಕಾಳು","category":"wheat","description":"Organic wheat grain.","imageurl":"images\\\\Organic Wheat Grain.jpg","weights":[{"label":"1 kg","price":70},{"label":"2 kg","price":140},{"label":"5 kg","price":350},{"label":"10 kg","price":700},{"label":"15 kg","price":1050},{"label":"25 kg","price":1750}]},
  {"id":160,"name":"Organic Wheat Grain Bulk","nameKn":"ಸಾವಯವ ಗೋಧಿ ಕಾಳು ಬಲ್ಕ್","category":"wheat","description":"Organic wheat grain.","imageurl":"images\\\\Organic Wheat Grain Bulk.jpg","weights":[{"label":"1 kg","price":66},{"label":"2 kg","price":132},{"label":"5 kg","price":330},{"label":"10 kg","price":660},{"label":"15 kg","price":990},{"label":"25 kg","price":1650}]},
  {"id":161,"name":"Gluten Free Wheat Mix","nameKn":"ಗ್ಲುಟನ್ ಫ್ರೀ ಗೋಧಿ ಮಿಶ್ರಣ","category":"wheat","description":"Gluten free wheat mix.","imageurl":"images\\\\Gluten Free Wheat Mix.jpg","weights":[{"label":"1 kg","price":120},{"label":"2 kg","price":240},{"label":"5 kg","price":600},{"label":"10 kg","price":1200},{"label":"15 kg","price":1800},{"label":"25 kg","price":3000}]},
  {"id":162,"name":"Thalipeeth Flour Mix","nameKn":"ಥಾಳೀಪೀಠ್ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Maharashtrian multi‑grain flour mix.","image":"images\\\\pulses\\\\Thalipeeth Flour Mix .jpg","weights":[{"label":"1kg","price":210}]},
  {"id":163,"name":"Missi Roti Flour Mix","nameKn":"ಮಿಸ್ಸಿ ರೊಟ್ಟಿ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Mix for spicy missi rotis.","image":"images\\\\pulses\\\\Missi Roti Flour Mix 400g 100.jpg","weights":[{"label":"500g","price":90},{"label":"1kg","price":170}]},
  {"id":164,"name":"Bhakri Flour Mix","nameKn":"ಭಾಕ್ರಿ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Flour for traditional bhakri.","image":"images\\\\pulses\\\\Bhakri Flour Mix 360.jpg","weights":[{"label":"1kg","price":120},{"label":"5kg","price":580}]},
  {"id":165,"name":"Rotla Flour Mix","nameKn":"ರೋಟ್ಲಾ ಹಿಟ್ಟು ಮಿಶ್ರಣ","category":"flour","description":"Gujarati rotla flour mix.","image":"images\\\\pulses\\\\Rotla Flour Mix 140.jpg","weights":[{"label":"1kg","price":115},{"label":"5kg","price":560}]},
  {"id":166,"name":"Organic Chakki Atta","nameKn":"ಸಾವಯವ ಚಕ್ಕಿ ಅಟ್ಟ","category":"atta","description":"Organic stone‑ground chakki atta.","image":"images\\\\pulses\\\\Organic Chakki Atta 75.jpg","weights":[{"label":"5kg","price":420},{"label":"10kg","price":820}]},
  {"id":167,"name":"Organic Multigrain Flour","nameKn":"ಸಾವಯವ ಮಲ್ಟಿಗ್ರೇನ್ ಹಿಟ್ಟು","category":"atta","description":"Organic multigrain flour blend.","image":"images\\\\pulses\\\\Organic Multigrain Flour 125.jpg","weights":[{"label":"5kg","price":650},{"label":"10kg","price":1280}]},
  {"id":168,"name":"Fitness Multigrain Atta","nameKn":"ಫಿಟ್ನೆಸ್ ಮಲ್ಟಿಗ್ರೇನ್ ಅಟ್ಟ","category":"atta","description":"High‑fiber multigrain atta for fitness.","image":"images\\\\pulses\\\\Fitness Multigrain Atta 10k 650.jpg","weights":[{"label":"1kg","price":160},{"label":"5kg","price":760}]},
  {"id":169,"name":"Diabetic Multigrain Atta","nameKn":"ಮಧುಮೇಹ ಮಲ್ಟಿಗ್ರೇನ್ ಅಟ್ಟ","category":"atta","description":"Diabetic‑friendly multigrain flour.","image":"images\\\\pulses\\\\Diabetic Multigrain Atta 1200.jpg","weights":[{"label":"1kg","price":165},{"label":"5kg","price":780}]},
  {"id":170,"name":"Healthy Grain Atta Mix","nameKn":"ಆರೋಗ್ಯಕರ ಧಾನ್ಯ ಅಟ್ಟ ಮಿಶ್ರಣ","category":"atta","description":"Nutrient‑rich atta mix.","image":"images\\\\pulses\\\\Healthy Grain Atta Mix 240.jpg","weights":[{"label":"1kg","price":170}]},
  {"id":172,"name":"Desi Wheat Grain","nameKn":"ದೇಸಿ ಗೋಧಿ ಕಾಳು","category":"wheat","description":"Desi wheat grain.","imageurl":"images\\\\Desi Wheat Grain.jpg","weights":[{"label":"1 kg","price":50},{"label":"2 kg","price":100},{"label":"5 kg","price":250},{"label":"10 kg","price":500},{"label":"15 kg","price":750},{"label":"25 kg","price":1250}]},
  {"id":173,"name":"Desi Wheat Grain Bulk","nameKn":"ದೇಸಿ ಗೋಧಿ ಕಾಳು ಬಲ್ಕ್","category":"wheat","description":"Desi wheat grain.","imageurl":"images\\\\Desi Wheat Grain Bulk.jpg","weights":[{"label":"1 kg","price":46},{"label":"2 kg","price":92},{"label":"5 kg","price":230},{"label":"10 kg","price":460},{"label":"15 kg","price":690},{"label":"25 kg","price":1150}]},
  {"id":174,"name":"Hard Wheat Grain","nameKn":"ಗಟ್ಟಿ ಗೋಧಿ ಕಾಳು","category":"wheat","description":"Hard wheat grain.","imageurl":"images\\\\Hard Wheat Grain.jpg","weights":[{"label":"1 kg","price":55},{"label":"2 kg","price":110},{"label":"5 kg","price":275},{"label":"10 kg","price":550},{"label":"15 kg","price":825},{"label":"25 kg","price":1375}]},
  {"id":175,"name":"Hard Wheat Grain Bulk","nameKn":"ಗಟ್ಟಿ ಗೋಧಿ ಕಾಳು ಬಲ್ಕ್","category":"wheat","description":"Hard wheat grain.","imageurl":"images\\\\Hard Wheat Grain Bulk.jpg","weights":[{"label":"1 kg","price":52},{"label":"2 kg","price":104},{"label":"5 kg","price":260},{"label":"10 kg","price":520},{"label":"15 kg","price":780},{"label":"25 kg","price":1300}]},
  {"id":176,"name":"Soft Wheat Grain","nameKn":"ಮೆತ್ತನೆಯ ಗೋಧಿ ಕಾಳು","category":"wheat","description":"Soft wheat grain.","imageurl":"images\\\\Soft Wheat Grain.jpg","weights":[{"label":"1 kg","price":48},{"label":"2 kg","price":96},{"label":"5 kg","price":240},{"label":"10 kg","price":480},{"label":"15 kg","price":720},{"label":"25 kg","price":1200}]},
  {"id":177,"name":"Soft Wheat Grain Bulk","nameKn":"ಮೆತ್ತನೆಯ ಗೋಧಿ ಕಾಳು ಬಲ್ಕ್","category":"wheat","description":"Soft wheat grain.","imageurl":"images\\\\Soft Wheat Grain Bulk.jpg","weights":[{"label":"1 kg","price":48},{"label":"2 kg","price":96},{"label":"5 kg","price":240},{"label":"10 kg","price":480},{"label":"15 kg","price":720},{"label":"25 kg","price":1200}]},
  {"id":178,"name":"Whole Wheat Flour Fine","nameKn":"ನಯವಾದ ಸಂಪೂರ್ಣ ಗೋಧಿ ಹಿಟ್ಟು","category":"wheat","description":"Fine whole wheat flour.","imageurl":"images\\\\Whole Wheat Flour Fine.jpg","weights":[{"label":"1 kg","price":60},{"label":"2 kg","price":120},{"label":"5 kg","price":300},{"label":"10 kg","price":600},{"label":"15 kg","price":900},{"label":"25 kg","price":1500}]},
  {"id":179,"name":"Whole Wheat Flour Fine Bulk","nameKn":"ನಯವಾದ ಸಂಪೂರ್ಣ ಗೋಧಿ ಹಿಟ್ಟು ಬಲ್ಕ್","category":"wheat","description":"Fine whole wheat flour.","imageurl":"images\\\\Whole Wheat Flour Fine Bulk.jpg","weights":[{"label":"1 kg","price":58},{"label":"2 kg","price":116},{"label":"5 kg","price":290},{"label":"10 kg","price":580},{"label":"15 kg","price":870},{"label":"25 kg","price":1450}]},
  {"id":180,"name":"Whole Wheat Flour Coarse","nameKn":"ಒರಟು ಸಂಪೂರ್ಣ ಗೋಧಿ ಹಿಟ್ಟು","category":"wheat","description":"Coarse whole wheat flour.","imageurl":"images\\\\Whole Wheat Flour Coarse.jpg","weights":[{"label":"1 kg","price":58},{"label":"2 kg","price":116},{"label":"5 kg","price":290},{"label":"10 kg","price":580},{"label":"15 kg","price":870},{"label":"25 kg","price":1450}]},
  {"id":181,"name":"Whole Wheat Flour Coarse Bulk","nameKn":"ಒರಟು ಸಂಪೂರ್ಣ ಗೋಧಿ ಹಿಟ್ಟು ಬಲ್ಕ್","category":"wheat","description":"Coarse whole wheat flour.","imageurl":"images\\\\Whole Wheat Flour Coarse Bulk.jpg","weights":[{"label":"1 kg","price":56},{"label":"2 kg","price":112},{"label":"5 kg","price":280},{"label":"10 kg","price":560},{"label":"15 kg","price":840},{"label":"25 kg","price":1400}]},
  {"id":182,"name":"Bran Rich Atta","nameKn":"ಹೊಟ್ಟು ಸಮೃದ್ಧ ಅಟ್ಟ","category":"wheat","description":"Bran rich atta.","imageurl":"images\\\\Bran Rich Atta.jpg","weights":[{"label":"1 kg","price":72},{"label":"2 kg","price":144},{"label":"5 kg","price":360},{"label":"10 kg","price":720},{"label":"15 kg","price":1080},{"label":"25 kg","price":1800}]},
  {"id":183,"name":"Bran Rich Atta Bulk","nameKn":"ಹೊಟ್ಟು ಸಮೃದ್ಧ ಅಟ್ಟ ಬಲ್ಕ್","category":"wheat","description":"Bran rich atta.","imageurl":"images\\\\Bran Rich Atta Bulk.jpg","weights":[{"label":"1 kg","price":69},{"label":"2 kg","price":138},{"label":"5 kg","price":345},{"label":"10 kg","price":690},{"label":"15 kg","price":1035},{"label":"25 kg","price":1725}]},
  {"id":184,"name":"High Fiber Atta","nameKn":"ಹೆಚ್ಚಿನ ಫೈಬರ್ ಅಟ್ಟ","category":"wheat","description":"High fiber atta.","imageurl":"images\\\\High Fiber Atta.jpg","weights":[{"label":"1 kg","price":78},{"label":"2 kg","price":156},{"label":"5 kg","price":390},{"label":"10 kg","price":780},{"label":"15 kg","price":1170},{"label":"25 kg","price":1950}]},
  {"id":185,"name":"High Fiber Atta Bulk","nameKn":"ಹೆಚ್ಚಿನ ಫೈಬರ್ ಅಟ್ಟ ಬಲ್ಕ್","category":"wheat","description":"High fiber atta.","imageurl":"images\\\\High Fiber Atta Bulk.jpg","weights":[{"label":"1 kg","price":76},{"label":"2 kg","price":152},{"label":"5 kg","price":380},{"label":"10 kg","price":760},{"label":"15 kg","price":1140},{"label":"25 kg","price":1900}]},
  {"id":186,"name":"Protein Rich Wheat Flour","nameKn":"ಪ್ರೋಟೀನ್ ಸಮೃದ್ಧ ಗೋಧಿ ಹಿಟ್ಟು","category":"wheat","description":"Protein rich wheat flour.","imageurl":"images\\\\Protein Rich Wheat Flour.jpg","weights":[{"label":"1 kg","price":85},{"label":"2 kg","price":170},{"label":"5 kg","price":425},{"label":"10 kg","price":850},{"label":"15 kg","price":1275},{"label":"25 kg","price":2125}]},
  {"id":187,"name":"Protein Rich Wheat Bulk","nameKn":"ಪ್ರೋಟೀನ್ ಸಮೃದ್ಧ ಗೋಧಿ ಬಲ್ಕ್","category":"wheat","description":"Protein rich wheat flour.","imageurl":"images\\\\Protein Rich Wheat Bulk.jpg","weights":[{"label":"1 kg","price":78},{"label":"2 kg","price":156},{"label":"5 kg","price":390},{"label":"10 kg","price":780},{"label":"15 kg","price":1170},{"label":"25 kg","price":1950}]},
  {"id":188,"name":"Whole Wheat Pasta Flour","nameKn":"ಸಂಪೂರ್ಣ ಗೋಧಿ ಪಾಸ್ಟಾ ಹಿಟ್ಟು","category":"wheat","description":"Whole wheat pasta flour.","imageurl":"images\\\\Whole Wheat Pasta Flour.jpg","weights":[{"label":"1 kg","price":90},{"label":"2 kg","price":180},{"label":"5 kg","price":450},{"label":"10 kg","price":900},{"label":"15 kg","price":1350},{"label":"25 kg","price":2250}]},
  {"id":189,"name":"Whole Wheat Pizza Flour","nameKn":"ಸಂಪೂರ್ಣ ಗೋಧಿ ಪಿಜ್ಜಾ ಹಿಟ್ಟು","category":"wheat","description":"Whole wheat pizza flour.","imageurl":"images\\\\Whole Wheat Pizza Flour.jpg","weights":[{"label":"1 kg","price":95},{"label":"2 kg","price":190},{"label":"5 kg","price":475},{"label":"10 kg","price":950},{"label":"15 kg","price":1425},{"label":"25 kg","price":2375}]},
  {"id":190,"name":"Bakery Wheat Flour","nameKn":"ಬೇಕರಿ ಗೋಧಿ ಹಿಟ್ಟು","category":"wheat","description":"Bakery wheat flour.","imageurl":"images\\\\Bakery Wheat Flour.jpg","weights":[{"label":"1 kg","price":80},{"label":"2 kg","price":160},{"label":"5 kg","price":400},{"label":"10 kg","price":800},{"label":"15 kg","price":1200},{"label":"25 kg","price":2000}]},
  {"id":191,"name":"Bakery Wheat Flour Bulk","nameKn":"ಬೇಕರಿ ಗೋಧಿ ಹಿಟ್ಟು ಬಲ್ಕ್","category":"wheat","description":"Bakery wheat flour.","imageurl":"images\\\\Bakery Wheat Flour Bulk.jpg","weights":[{"label":"1 kg","price":75},{"label":"2 kg","price":150},{"label":"5 kg","price":375},{"label":"10 kg","price":750},{"label":"15 kg","price":1125},{"label":"25 kg","price":1875}]},
  {"id":192,"name":"Quaker Oats Rolled Oats","nameKn":"ಕ್ವೇಕರ್ ರೋಲ್ಡ್ ಓಟ್ಸ್","category":"oats","description":"Quaker rolled oats.","imageurl":"images\\\\Quaker Oats Rolled Oats.jpg","weights":[{"label":"1 kg","price":180},{"label":"2 kg","price":360},{"label":"5 kg","price":900},{"label":"10 kg","price":1800},{"label":"15 kg","price":2700},{"label":"25 kg","price":4500}]},
  {"id":194,"name":"Quaker Instant Oats","nameKn":"ಕ್ವೇಕರ್ ಇನ್ಸ್ಟೆಂಟ್ ಓಟ್ಸ್","category":"oats","description":"Quaker instant oats.","imageurl":"images\\\\Quaker Instant Oats.jpg","weights":[{"label":"1 kg","price":190},{"label":"2 kg","price":380},{"label":"5 kg","price":950},{"label":"10 kg","price":1900},{"label":"15 kg","price":2850},{"label":"25 kg","price":4750}]},
  {"id":196,"name":"Kellogg\'s Oats","nameKn":"ಕೆಲ್ಲಾಗ್ಸ್ ಓಟ್ಸ್","category":"oats","description":"Kellogg\'s oats.","imageurl":"images\\\\Kellogg\'s Oats.jpg","weights":[{"label":"1 kg","price":175},{"label":"2 kg","price":350},{"label":"5 kg","price":875},{"label":"10 kg","price":1750},{"label":"15 kg","price":2625},{"label":"25 kg","price":4375}]},
  {"id":198,"name":"Saffola Oats","nameKn":"ಸಾಫೋಲಾ ಓಟ್ಸ್","category":"oats","description":"Saffola oats.","imageurl":"images\\\\Saffola Oats.jpg","weights":[{"label":"1 kg","price":200},{"label":"2 kg","price":400},{"label":"5 kg","price":1000},{"label":"10 kg","price":2000},{"label":"15 kg","price":3000},{"label":"25 kg","price":5000}]},
  {"id":199,"name":"Saffola Masala Oats Classic","nameKn":"ಸಾಫೋಲಾ ಮಸಾಲಾ ಓಟ್ಸ್ ಕ್ಲಾಸಿಕ್","category":"oats","description":"Saffola masala oats classic.","imageurl":"images\\\\Saffola Masala Oats Classic.jpg","weights":[{"label":"1 kg","price":280},{"label":"2 kg","price":560},{"label":"5 kg","price":1400},{"label":"10 kg","price":2800},{"label":"15 kg","price":4200},{"label":"25 kg","price":7000}]},
  {"id":200,"name":"Saffola Masala Oats Veggie Twist","nameKn":"ಸಾಫೋಲಾ ಮಸಾಲಾ ಓಟ್ಸ್ ವೆಜ್ಜಿ ಟ್ವಿಸ್ಟ್","category":"oats","description":"Saffola masala oats veggie twist.","imageurl":"images\\\\Saffola Masala Oats Veggie Twist.jpg","weights":[{"label":"1 kg","price":290},{"label":"2 kg","price":580},{"label":"5 kg","price":1450},{"label":"10 kg","price":2900},{"label":"15 kg","price":4350},{"label":"25 kg","price":7250}]},
  {"id":201,"name":"Saffola Masala Oats Peppy Tomato","nameKn":"ಸಾಫೋಲಾ ಮಸಾಲಾ ಓಟ್ಸ್ ಪೆಪ್ಪಿ ಟೊಮೆಟೊ","category":"oats","description":"Saffola masala oats peppy tomato.","imageurl":"images\\\\Saffola Masala Oats Peppy Tomato.jpg","weights":[{"label":"1 kg","price":290},{"label":"2 kg","price":580},{"label":"5 kg","price":1450},{"label":"10 kg","price":2900},{"label":"15 kg","price":4350},{"label":"25 kg","price":7250}]},
  {"id":202,"name":"Saffola Masala Oats Curry & Pepper","nameKn":"ಸಾಫೋಲಾ ಮಸಾಲಾ ಓಟ್ಸ್ ಕರಿ & ಪೆಪ್ಪರ್","category":"oats","description":"Saffola masala oats curry & pepper.","imageurl":"images\\\\Saffola Masala Oats Curry & Pepper.jpg","weights":[{"label":"1 kg","price":300},{"label":"2 kg","price":600},{"label":"5 kg","price":1500},{"label":"10 kg","price":3000},{"label":"15 kg","price":4500},{"label":"25 kg","price":7500}]},
  {"id":203,"name":"Saffola Masala Oats Chinese","nameKn":"ಸಾಫೋಲಾ ಮಸಾಲಾ ಓಟ್ಸ್ ಚೈನೀಸ್","category":"oats","description":"Saffola masala oats Chinese.","imageurl":"images\\\\Saffola Masala Oats Chinese.jpg","weights":[{"label":"1 kg","price":300},{"label":"2 kg","price":600},{"label":"5 kg","price":1500},{"label":"10 kg","price":3000},{"label":"15 kg","price":4500},{"label":"25 kg","price":7500}]},
  {"id":204,"name":"Saffola Oats Active","nameKn":"ಸಾಫೋಲಾ ಓಟ್ಸ್ ಆಕ್ಟಿವ್","category":"oats","description":"Saffola oats active.","imageurl":"images\\\\Saffola Oats Active.jpg","weights":[{"label":"1 kg","price":210},{"label":"2 kg","price":420},{"label":"5 kg","price":1050},{"label":"10 kg","price":2100},{"label":"15 kg","price":3150},{"label":"25 kg","price":5250}]},
  {"id":205,"name":"Bagrry\'s White Oats","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ವೈಟ್ ಓಟ್ಸ್","category":"oats","description":"Bagrry\'s white oats.","imageurl":"images\\\\Bagrry\'s White Oats.jpg","weights":[{"label":"1 kg","price":185},{"label":"2 kg","price":370},{"label":"5 kg","price":925},{"label":"10 kg","price":1850},{"label":"15 kg","price":2775},{"label":"25 kg","price":4625}]},
  {"id":206,"name":"Bagrry\'s Rolled Oats","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ರೋಲ್ಡ್ ಓಟ್ಸ್","category":"oats","description":"Bagrry\'s rolled oats.","imageurl":"images\\\\Bagrry\'s Rolled Oats.jpg","weights":[{"label":"1 kg","price":190},{"label":"2 kg","price":380},{"label":"5 kg","price":950},{"label":"10 kg","price":1900},{"label":"15 kg","price":2850},{"label":"25 kg","price":4750}]},
  {"id":207,"name":"Bagrry\'s Instant Oats","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ಇನ್ಸ್ಟೆಂಟ್ ಓಟ್ಸ್","category":"oats","description":"Bagrry\'s instant oats.","imageurl":"images\\\\Bagrry\'s Instant Oats.jpg","weights":[{"label":"1 kg","price":213},{"label":"2 kg","price":425},{"label":"5 kg","price":1063},{"label":"10 kg","price":2125},{"label":"15 kg","price":3188},{"label":"25 kg","price":5313}]},
  {"id":208,"name":"Bagrry\'s Steel Cut Oats","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ಸ್ಟೀಲ್ ಕಟ್ ಓಟ್ಸ್","category":"oats","description":"Bagrry\'s steel cut oats.","imageurl":"images\\\\Bagrry\'s Steel Cut Oats.jpg","weights":[{"label":"1 kg","price":220},{"label":"2 kg","price":440},{"label":"5 kg","price":1100},{"label":"10 kg","price":2200},{"label":"15 kg","price":3300},{"label":"25 kg","price":5500}]},
  {"id":209,"name":"Yoga Bar Oats","nameKn":"ಯೋಗಾ ಬಾರ್ ಓಟ್ಸ್","category":"oats","description":"Yoga Bar oats.","imageurl":"images\\\\Yoga Bar Oats.jpg","weights":[{"label":"1 kg","price":240},{"label":"2 kg","price":480},{"label":"5 kg","price":1200},{"label":"10 kg","price":2400},{"label":"15 kg","price":3600},{"label":"25 kg","price":6000}]},
  {"id":210,"name":"Yoga Bar Instant Oats","nameKn":"ಯೋಗಾ ಬಾರ್ ಇನ್ಸ್ಟೆಂಟ್ ಓಟ್ಸ್","category":"oats","description":"Yoga Bar instant oats.","imageurl":"images\\\\Yoga Bar Instant Oats.jpg","weights":[{"label":"1 kg","price":275},{"label":"2 kg","price":550},{"label":"5 kg","price":1375},{"label":"10 kg","price":2750},{"label":"15 kg","price":4125},{"label":"25 kg","price":6875}]},
  {"id":211,"name":"True Elements Rolled Oats","nameKn":"ಟ್ರೂ ಎಲಿಮೆಂಟ್ಸ್ ರೋಲ್ಡ್ ಓಟ್ಸ್","category":"oats","description":"True Elements rolled oats.","imageurl":"images\\\\True Elements Rolled Oats.jpg","weights":[{"label":"1 kg","price":260},{"label":"2 kg","price":520},{"label":"5 kg","price":1300},{"label":"10 kg","price":2600},{"label":"15 kg","price":3900},{"label":"25 kg","price":6500}]},
  {"id":212,"name":"True Elements Steel Cut Oats","nameKn":"ಟ್ರೂ ಎಲಿಮೆಂಟ್ಸ್ ಸ್ಟೀಲ್ ಕಟ್ ಓಟ್ಸ್","category":"oats","description":"True Elements steel cut oats.","imageurl":"images\\\\True Elements Steel Cut Oats.jpg","weights":[{"label":"1 kg","price":280},{"label":"2 kg","price":560},{"label":"5 kg","price":1400},{"label":"10 kg","price":2800},{"label":"15 kg","price":4200},{"label":"25 kg","price":7000}]},
  {"id":213,"name":"True Elements Instant Oats","nameKn":"ಟ್ರೂ ಎಲಿಮೆಂಟ್ಸ್ ಇನ್ಸ್ಟೆಂಟ್ ಓಟ್ಸ್","category":"oats","description":"True Elements instant oats.","imageurl":"images\\\\True Elements Instant Oats.jpg","weights":[{"label":"1 kg","price":300},{"label":"2 kg","price":600},{"label":"5 kg","price":1500},{"label":"10 kg","price":3000},{"label":"15 kg","price":4500},{"label":"25 kg","price":7500}]},
  {"id":214,"name":"BB Royal Oats","nameKn":"ಬಿಬಿ ರಾಯಲ್ ಓಟ್ಸ್","category":"oats","description":"BB Royal oats.","imageurl":"images\\\\BB Royal Oats.jpg","weights":[{"label":"1 kg","price":160},{"label":"2 kg","price":320},{"label":"5 kg","price":800},{"label":"10 kg","price":1600},{"label":"15 kg","price":2400},{"label":"25 kg","price":4000}]},
  {"id":215,"name":"BB Royal Instant Oats","nameKn":"ಬಿಬಿ ರಾಯಲ್ ಇನ್ಸ್ಟೆಂಟ್ ಓಟ್ಸ್","category":"oats","description":"BB Royal instant oats.","imageurl":"images\\\\BB Royal Instant Oats.jpg","weights":[{"label":"1 kg","price":188},{"label":"2 kg","price":375},{"label":"5 kg","price":938},{"label":"10 kg","price":1875},{"label":"15 kg","price":2813},{"label":"25 kg","price":4688}]},
  {"id":216,"name":"Nourish Organics Oats","nameKn":"ನೌರಿಶ್ ಆರ್ಗಾನಿಕ್ಸ್ ಓಟ್ಸ್","category":"oats","description":"Nourish Organics oats.","imageurl":"images\\\\Nourish Organics Oats.jpg","weights":[{"label":"1 kg","price":250},{"label":"2 kg","price":500},{"label":"5 kg","price":1250},{"label":"10 kg","price":2500},{"label":"15 kg","price":3750},{"label":"25 kg","price":6250}]},
  {"id":217,"name":"Nourish Organics Instant Oats","nameKn":"ನೌರಿಶ್ ಆರ್ಗಾನಿಕ್ಸ್ ಇನ್ಸ್ಟೆಂಟ್ ಓಟ್ಸ್","category":"oats","description":"Nourish Organics instant oats.","imageurl":"images\\\\Nourish Organics Instant Oats.jpg","weights":[{"label":"1 kg","price":325},{"label":"2 kg","price":650},{"label":"5 kg","price":1625},{"label":"10 kg","price":3250},{"label":"15 kg","price":4875},{"label":"25 kg","price":8125}]},
  {"id":218,"name":"Organic India Oats","nameKn":"ಆರ್ಗಾನಿಕ್ ಇಂಡಿಯಾ ಓಟ್ಸ್","category":"oats","description":"Organic India oats.","imageurl":"images\\\\Organic India Oats.jpg","weights":[{"label":"1 kg","price":270},{"label":"2 kg","price":540},{"label":"5 kg","price":1350},{"label":"10 kg","price":2700},{"label":"15 kg","price":4050},{"label":"25 kg","price":6750}]},
  {"id":220,"name":"24 Mantra Organic Oats","nameKn":"24 ಮಂತ್ರ ಸಾವಯವ ಓಟ್ಸ್","category":"oats","description":"24 Mantra organic oats.","imageurl":"images\\\\24 Mantra Organic Oats.jpg","weights":[{"label":"1 kg","price":255},{"label":"2 kg","price":510},{"label":"5 kg","price":1275},{"label":"10 kg","price":2550},{"label":"15 kg","price":3825},{"label":"25 kg","price":6375}]},
  {"id":221,"name":"Pro Nature Organic Oats","nameKn":"ಪ್ರೋ ನೇಚರ್ ಸಾವಯವ ಓಟ್ಸ್","category":"oats","description":"Pro Nature organic oats.","imageurl":"images\\\\Pro Nature Organic Oats.jpg","weights":[{"label":"1 kg","price":240},{"label":"2 kg","price":480},{"label":"5 kg","price":1200},{"label":"10 kg","price":2400},{"label":"15 kg","price":3600},{"label":"25 kg","price":6000}]},
  {"id":222,"name":"Disano Oats","nameKn":"ಡಿಸಾನೋ ಓಟ್ಸ್","category":"oats","description":"Disano oats.","imageurl":"images\\\\Disano Oats.jpg","weights":[{"label":"1 kg","price":190},{"label":"2 kg","price":380},{"label":"5 kg","price":950},{"label":"10 kg","price":1900},{"label":"15 kg","price":2850},{"label":"25 kg","price":4750}]},
  {"id":223,"name":"Disano Instant Oats","nameKn":"ಡಿಸಾನೋ ಇನ್ಸ್ಟೆಂಟ್ ಓಟ್ಸ್","category":"oats","description":"Disano instant oats.","imageurl":"images\\\\Disano Instant Oats.jpg","weights":[{"label":"1 kg","price":238},{"label":"2 kg","price":475},{"label":"5 kg","price":1188},{"label":"10 kg","price":2375},{"label":"15 kg","price":3563},{"label":"25 kg","price":5938}]},
  {"id":224,"name":"Keya Oats","nameKn":"ಕೇಯಾ ಓಟ್ಸ್","category":"oats","description":"Keya oats.","imageurl":"images\\\\Keya Oats.jpg","weights":[{"label":"1 kg","price":200},{"label":"2 kg","price":400},{"label":"5 kg","price":1000},{"label":"10 kg","price":2000},{"label":"15 kg","price":3000},{"label":"25 kg","price":5000}]},
  {"id":225,"name":"Keya Instant Oats","nameKn":"ಕೇಯಾ ಇನ್ಸ್ಟೆಂಟ್ ಓಟ್ಸ್","category":"oats","description":"Keya instant oats.","imageurl":"images\\\\Keya Instant Oats.jpg","weights":[{"label":"1 kg","price":250},{"label":"2 kg","price":500},{"label":"5 kg","price":1250},{"label":"10 kg","price":2500},{"label":"15 kg","price":3750},{"label":"25 kg","price":6250}]},
  {"id":226,"name":"Urban Platter Rolled Oats","nameKn":"ಅರ್ಬನ್ ಪ್ಲಾಟರ್ ರೋಲ್ಡ್ ಓಟ್ಸ್","category":"oats","description":"Urban Platter rolled oats.","imageurl":"images\\\\Urban Platter Rolled OATS.jpg","weights":[{"label":"1 kg","price":300},{"label":"2 kg","price":600},{"label":"5 kg","price":1500},{"label":"10 kg","price":3000},{"label":"15 kg","price":4500},{"label":"25 kg","price":7500}]},
  {"id":227,"name":"Urban Platter Steel Cut Oats","nameKn":"ಅರ್ಬನ್ ಪ್ಲಾಟರ್ ಸ್ಟೀಲ್ ಕಟ್ ಓಟ್ಸ್","category":"oats","description":"Urban Platter steel cut oats.","imageurl":"images\\\\Urban Platter Steel Cut Oats.jpg","weights":[{"label":"1 kg","price":320},{"label":"2 kg","price":640},{"label":"5 kg","price":1600},{"label":"10 kg","price":3200},{"label":"15 kg","price":4800},{"label":"25 kg","price":8000}]},
  {"id":228,"name":"Urban Platter Instant Oats","nameKn":"ಅರ್ಬನ್ ಪ್ಲಾಟರ್ ಇನ್ಸ್ಟೆಂಟ್ ಓಟ್ಸ್","category":"oats","description":"Urban Platter instant oats.","imageurl":"images\\\\Urban Platter Instant Oats.jpg","weights":[{"label":"1 kg","price":350},{"label":"2 kg","price":700},{"label":"5 kg","price":1750},{"label":"10 kg","price":3500},{"label":"15 kg","price":5250},{"label":"25 kg","price":8750}]},
  {"id":229,"name":"Whole Wheat Atta Premium","nameKn":"ಸಂಪೂರ್ಣ ಗೋಧಿ ಅಟ್ಟ ಪ್ರೀಮಿಯಂ","category":"atta","description":"Premium whole wheat flour for soft and healthy chapatis.","image":"images\\\\pulses\\\\Whole Wheat Atta Premium 290.jpg","weights":[{"label":"1kg","price":50},{"label":"5kg","price":290},{"label":"10kg","price":560}]}
]';
  $data = json_decode($json, true);
  if (!is_array($data)) {
    return [];
  }
  $result = [];
  foreach ($data as $item) {
    $variants = [];
    if (isset($item['weights']) && is_array($item['weights'])) {
      foreach ($item['weights'] as $w) {
        $variants[] = [
          'label' => $w['label'],
          'price' => (float) $w['price']
        ];
      }
    } else {
      $variants = [['label' => '1 unit', 'price' => (float) ($item['price'] ?? 0)]];
    }
    $cat = $item['category'] ?? 'rice';
    $emojiMap = [
      'rice' => '🍚',
      'atta' => '🌾',
      'wheat' => '🌾',
      'flour' => '🌾',
      'millet' => '🌽',
      'oats' => '🌾',
      'rava' => '🌾',
      'besan' => '🌾',
      'pulseflour' => '🌾',
      'nutflour' => '🌾',
      'snacks' => '🍿'
    ];
    $emoji = $emojiMap[$cat] ?? '🌾';
    // Normalize image path
    $imagePath = $item['imageurl'] ?? $item['image'] ?? '';
    if ($imagePath && !str_starts_with($imagePath, 'http') && !str_starts_with($imagePath, 'data:')) {
      $imagePath = normalizeImagePath($imagePath);
    }
    $result[] = [
      'id' => (int) $item['id'],
      'name' => $item['name'],
      'kn_name' => $item['nameKn'] ?? $item['name'],
      'price' => (float) ($variants[0]['price'] ?? 0),
      'category' => $cat,
      'variants' => $variants,
      'emoji' => $emoji,
      'description_en' => $item['description'] ?? 'Premium grain product',
      'imageUrl' => $imagePath,
      'image' => $imagePath
    ];
  }
  $fallback = $result;
  @file_put_contents($jsonPath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  return $fallback;
}
if (!function_exists('getFallbackProducts')) {
  function getFallbackProducts() {
    return getFallbackGrainsProducts();
  }
}
// Get products from DB, fallback if empty
$products = getGrainsProducts();
if (empty($products)) {
  $products = getFallbackGrainsProducts();
}
// Ensure variants are arrays for each product
foreach ($products as &$p) {
  if (!isset($p['variants']) || !is_array($p['variants']) || empty($p['variants'])) {
    $p['variants'] = [['label' => '1 kg', 'price' => $p['price']]];
  }
}
unset($p);
// Cart totals
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
// Top picks (first 6)
$topPicks = array_slice($products, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>Grains & Cereals · Heritage Grains Market · KSR Guru</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <style>
    /* ===== Same CSS as before ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      display: block;
    }

    #app {
      max-width: 1440px;
      width: 100%;
      background: #ffffff;
      border-radius: 44px;
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.06);
      padding: 32px 36px 44px;
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
      color: #2c1f14;
      letter-spacing: -0.02em;
    }

    .shop-name .kannada {
      font-size: 1.1rem;
      font-weight: 500;
      color: #b45f2b;
      margin-left: 4px;
    }

    .cart-link {
      background: #f5ede6;
      padding: 12px 28px;
      border-radius: 60px;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      transition: background 0.2s, transform 0.15s;
      border: 1px solid #ede3d9;
      text-decoration: none;
      color: inherit;
      font-weight: 600;
    }

    .cart-link:hover {
      background: #ece0d4;
      transform: scale(1.02);
    }

    .cart-link i {
      font-size: 1.6rem;
      color: #5c3e24;
    }

    .cart-badge {
      background: #b45f2b;
      color: #fff;
      font-weight: 700;
      font-size: 1rem;
      padding: 2px 14px;
      border-radius: 40px;
    }

    .search-section {
      display: flex;
      flex-wrap: wrap;
      gap: 18px;
      align-items: center;
      margin-bottom: 32px;
    }

    .search-box {
      flex: 2;
      min-width: 220px;
      display: flex;
      align-items: center;
      background: #f5ede6;
      border-radius: 60px;
      padding: 4px 24px;
      border: 1px solid #e6d9cd;
    }

    .search-box i {
      color: #b45f2b;
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
      color: #2c1f14;
      font-weight: 500;
    }

    .filter-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .pill {
      background: #f5ede6;
      padding: 10px 24px;
      border-radius: 60px;
      font-size: 0.95rem;
      font-weight: 600;
      color: #4f3a2a;
      cursor: pointer;
      transition: all 0.2s;
      border: 1px solid transparent;
    }

    .pill.active {
      background: #2c1f14;
      color: #fff;
      border-color: #2c1f14;
      box-shadow: 0 4px 12px rgba(44, 31, 20, 0.20);
    }

    .pill:hover:not(.active) {
      background: #e8d8cb;
    }

    .top-picks {
      background: linear-gradient(135deg, #fff6ed 0%, #fee9dd 100%);
      border-radius: 36px;
      padding: 28px 30px 34px;
      margin-bottom: 40px;
      border: 1px solid #fadbc8;
    }

    .top-picks h3 {
      font-size: 1.8rem;
      font-weight: 700;
      color: #2c1f14;
      margin-bottom: 22px;
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .top-picks h3 i {
      color: #e67e22;
      font-size: 2rem;
    }

    .top-picks-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 24px;
    }

    .top-pick-card {
      background: #fff;
      border-radius: 28px;
      padding: 18px 16px 20px;
      text-align: center;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.04);
      transition: transform 0.2s;
      border: 1px solid #f0e3d7;
    }

    .top-pick-card:hover {
      transform: translateY(-6px);
    }

    .top-pick-img {
      width: 80px;
      height: 80px;
      border-radius: 40px;
      margin: 0 auto 12px;
      background: #fcf4ec;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .top-pick-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 40px;
    }

    .top-pick-name {
      font-weight: 700;
      font-size: 1rem;
      color: #2c1f14;
    }

    .top-pick-price {
      font-size: 1.15rem;
      font-weight: 800;
      color: #b45f2b;
      margin: 6px 0 12px;
    }

    .top-pick-btn {
      background: #b45f2b;
      border: none;
      color: #fff;
      width: 48px;
      height: 48px;
      border-radius: 50%;
      font-size: 1.2rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto;
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 30px;
      margin-top: 16px;
      margin-bottom: 48px;
    }

    .product-card {
      background: #ffffff;
      border-radius: 32px;
      padding: 22px 20px 26px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.03);
      border: 1px solid #f0e3d7;
      transition: transform 0.25s, box-shadow 0.3s;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }

    .product-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 24px 40px rgba(0, 0, 0, 0.07);
    }

    .product-image {
      width: 100px;
      height: 100px;
      border-radius: 50px;
      background: #fcf4ec;
      border: 3px solid #f0e3d7;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50px;
    }

    .product-name {
      font-weight: 700;
      font-size: 1.2rem;
      color: #2c1f14;
      line-height: 1.3;
    }

    .product-name-kn {
      font-size: 0.85rem;
      color: #7b5f45;
      margin-bottom: 8px;
    }

    .product-description {
      font-size: 0.85rem;
      color: #6e5540;
      margin: 6px 0 14px;
      background: #faf4ee;
      padding: 6px 16px;
      border-radius: 40px;
    }

    .weight-select {
      width: 100%;
      max-width: 200px;
      background: #faf4ee;
      border: 1px solid #e6d9cd;
      border-radius: 50px;
      padding: 10px 16px;
      font-size: 0.9rem;
      font-weight: 600;
      color: #2c1f14;
      cursor: pointer;
      margin: 8px 0 6px;
      text-align: center;
    }

    .dynamic-price {
      font-weight: 800;
      font-size: 1.7rem;
      color: #b45f2b;
      margin: 6px 0 10px;
    }

    .add-btn {
      background: #b45f2b;
      border: none;
      color: #fff;
      padding: 14px 28px;
      border-radius: 60px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      width: 100%;
      justify-content: center;
    }

    .add-btn.added-flash {
      background: #4caf50 !important;
      color: white !important;
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
      border-left: 2px solid #f3e2d4;
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
      font-size: 0.85rem;
      color: #b9a690;
      margin-top: 16px;
      padding-top: 18px;
      border-top: 1px solid #f0e3d7;
    }

    @media (max-width: 768px) {
      #app {
        padding: 20px 16px 32px;
        border-radius: 28px;
      }

      .header {
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
      }

      .shop-name h1 {
        font-size: 1.7rem;
      }

      .cart-link {
        align-self: flex-start;
        padding: 10px 20px;
      }

      .filter-pills {
        justify-content: center;
      }

      .top-picks {
        padding: 20px 18px 26px;
      }

      .top-picks-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 16px;
      }

      .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 20px;
      }

      .product-image {
        width: 130px;
        height: 130px;
      }

      .cart-sidebar {
        width: 100%;
        right: -100%;
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
    <div class="search-section">
      <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" />
      </div>
      <div class="filter-pills" id="filterPills">
        <div class="pill active" data-category="all">🌾 All / ಎಲ್ಲ</div>
        <div class="pill" data-category="rice">🍚 Rice / ಅಕ್ಕಿ</div>
        <div class="pill" data-category="wheat">🌾 Wheat / ಗೋಧಿ</div>
        <div class="pill" data-category="millet">🌽 Millet / ರಾಗಿ</div>
      </div>
    </div>
    <!-- TOP PICKS -->
    <div class="top-picks" id="topPicksSection">
      <h3><i class="fas fa-star"></i> Our Top Picks · ಸಿರಿಧಾನ್ಯ ವಿಶೇಷ</h3>
      <div class="top-picks-grid" id="topPicksGrid"></div>
    </div>
    <div class="product-grid" id="productGrid">
    </div>
    <div class="cart-sidebar" id="cartSidebar">
      <div class="cart-header">
        <h3><i class="fas fa-shopping-cart"></i> Your Cart</h3>
        <button class="close-cart" id="closeCart">&times;</button>
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
                          $subcat = $p['category'] ?? 'rice';
                          $variants = $p['variants'] ?? [['label' => '1 kg', 'price' => $p['price'] ?? 0]];
                          $out[] = [
                            'id' => $p['id'],
                            'nameEn' => $p['name'],
                            'nameKn' => $p['kn_name'] ?? $p['name'],
                            'category' => $subcat,
                            'variants' => $variants,
                            'emoji' => $p['emoji'] ?? '🌾',
                            'description' => $p['description_en'] ?? 'Premium grain products',
                            'image' => $p['imageUrl'] ?? ''
                          ];
                        }
                        echo json_encode($out);
                        ?>;
      let cart = [];
      const basePath = 'grains.php';
      const cartCountSpan = document.getElementById('cartCount');
      const cartSidebar = document.getElementById('cartSidebar');
      const overlay = document.getElementById('overlay');
      const closeCartBtn = document.getElementById('closeCart');
      const continueShoppingBtn = document.getElementById('continueShopping');
      const checkoutBtn = document.getElementById('checkoutBtn');
      const cartItemsContainer = document.getElementById('cartItems');
      const totalPriceSpan = document.querySelector('.total-price');
      const searchInput = document.getElementById('searchInput');
      const filterPills = document.querySelectorAll('#filterPills .pill');
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
        const sel = document.querySelector(`.weight-select[data-base="${productId}"]`);
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
          <div class="cart-item-image">🌾</div>
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
          grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No grain products found ✨</div>';
          return;
        }
        grid.innerHTML = filtered.map(p => {
          const defaultVariant = 0;
          const defaultPrice = p.variants[defaultVariant]?.price || 0;
          // Image fallback
          const imageHtml = p.image ? `<img src="${p.image}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='${p.emoji}'">` : p.emoji;
          return `<div class="product-card" data-base="${p.id}">
          <div class="product-image">${imageHtml}</div>
          <div class="product-name">${p.nameEn}</div>
          ${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}
          <div class="product-description">${p.description}</div>
          ${p.variants && p.variants.length > 1 
            ? `<select class="weight-select" data-base="${p.id}">
                ${p.variants.map((v, i) => `<option value="${i}" ${i===defaultVariant?'selected':''}>${v.label} - ₹${v.price}</option>`).join('')}
               </select>`
            : `<div class="variant-text" style="font-size:0.85rem; font-weight:600; color:#4f3e2e; text-align:center; padding:10px; margin:8px 0 6px; width:100%; max-width:200px; background:#faf4ee; border-radius:50px; border:1px solid #e6d9cd;">${p.variants && p.variants[0] ? p.variants[0].label : '1 unit'}</div>`
          }
          <div class="dynamic-price">₹${defaultPrice}</div>
          <button class="add-btn" data-pid="${p.id}" onclick="window.addToCart(${p.id})"><i class="fas fa-cart-plus"></i></button>
        </div>`;
        }).join('');
        document.querySelectorAll('.weight-select').forEach(sel => {
          const base = sel.dataset.base;
          const priceDiv = sel.closest('.product-card').querySelector('.dynamic-price');
          sel.addEventListener('change', function() {
            const prod = products.find(p => p.id == base);
            if (!prod) return;
            const idx = parseInt(this.value);
            const price = prod.variants[idx]?.price || 0;
            if (priceDiv) priceDiv.innerText = `₹${price}`;
          });
        });
      }

      function renderTopPicks() {
        const topProds = products.filter(p => p.id % 2 === 1).slice(0, 6);
        if (!topProds.length) return;
        topPicksGrid.innerHTML = topProds.map(p => {
          const firstVariant = p.variants[0];
          const imageHtml = p.image ? `<img src="${p.image}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='${p.emoji}'">` : p.emoji;
          return `<div class="top-pick-card">
          <div class="top-pick-img">${imageHtml}</div>
          <div class="top-pick-name">${p.nameEn}</div>
          <div class="top-pick-price">₹${firstVariant?.price || 0}</div>
          <button class="top-pick-btn" onclick="window.addToCart(${p.id})"><i class="fas fa-plus"></i></button>
        </div>`;
        }).join('');
      }
      searchInput.addEventListener('input', function(e) {
        searchTerm = this.value.toLowerCase();
        renderProducts();
      });
      filterPills.forEach(pill => {
        pill.addEventListener('click', function() {
          filterPills.forEach(p => p.classList.remove('active'));
          this.classList.add('active');
          activeCategory = this.dataset.category;
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
  <?php include __DIR__ . '/../includes/user-footer.php'; ?>
  <script src="sorting.js"></script>
</body>

</html>