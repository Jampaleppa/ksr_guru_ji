<?php
// ============================================================
// dishwash.php - Dynamic Dishwash product page (FIXED)
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
      echo json_encode(getDishwashProducts($search));
      exit;
    case 'cart':
      echo json_encode($_SESSION['cart']);
      exit;
    case 'add_cart':
      $id = (int) ($_POST['id'] ?? 0);
      if ($id) {
        $product = getProductById($id);
        if (!$product) {
          $fallbackProducts = getFallbackDishwashProducts();
          foreach ($fallbackProducts as $fp) {
            if ($fp['id'] == $id) {
              $product = [
                'id' => $fp['id'],
                'name' => $fp['name'],
                'kn_name' => $fp['kn_name'] ?? $fp['name'],
                'price' => $fp['price'],
                'icon' => 'fa-sponge'
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
              'icon' => $product['icon'] ?? 'fa-sponge'
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
// FETCH DISHWASH PRODUCTS FROM DATABASE (PDO)
// ============================================================
function getDishwashProducts(string $search = ''): array
{
  try {
    $pdo = getDBConnection();
    $schema = getProductSchemaInfo();
    $descCol = $schema["desc_col"];
    $imgCol = $schema["img_col"];
    $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                       c.name AS category_name, c.kn_name AS category_kn,
                       {$descCol} AS description_en,
                       {$imgCol} AS image
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE c.slug = 'dishwash' OR c.id = 'dishwash'";
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
      $row['description_en'] = $row['description_en'] ?? 'Quality dishwash product';
      if (!empty($row['image'])) {
        $row['image'] = normalizeImagePath($row['image']);
      }
      $row['variants'] = [['label' => '1 unit', 'price' => $row['price']]];
      $row['subcategory'] = 'accessories';
      if (stripos($row['name'], 'liquid') !== false || stripos($row['name'], 'gel') !== false) {
        $row['subcategory'] = 'liquid';
      } elseif (stripos($row['name'], 'powder') !== false) {
        $row['subcategory'] = 'powder';
      }
    }
    return $rows;
  } catch (Throwable $e) {
    return [];
  }
}
// ============================================================
// FALLBACK: If no products from DB, load static data from JSON
// ============================================================
function getFallbackDishwashProducts(): array
{
  static $fallback = null;
  if ($fallback !== null) {
    return $fallback;
  }
  $jsonPath = __DIR__ . '/dishwash-fallback.json';
  if (file_exists($jsonPath)) {
    $fallback = json_decode(file_get_contents($jsonPath), true);
    if (is_array($fallback)) {
      return $fallback;
    }
  }
  $json = '[
  {"id":1,"name":"Vim Dishwash Liquid Gel Lemon","nameKn":"Vim ಡಿಶ್ವಾಶ್ ಲಿಕ್ವಿಡ್ ಜೆಲ್ ಲಿಂಬೆ","category":"liquid","price":930,"imageUrl":"images\\\\Vim Dishwash Liquid Gel Lemon 930.jpg","description":"Lemon fresh gel, cuts grease effectively."},
  {"id":2,"name":"Pril Dishwash Liquid Lime","nameKn":"Pril ಡಿಶ್ವಾಶ್ ಲಿಕ್ವಿಡ್ ಲೈಮ್","category":"liquid","price":190,"imageUrl":"images\\\\Pril Dishwash Liquid Lime 190.jpg","description":"Lime scented, gentle on hands."},
  {"id":3,"name":"Exo Dishwash Liquid","nameKn":"Exo ಡಿಶ್ವಾಶ್ ಲಿಕ್ವಿಡ್","category":"liquid","price":110,"imageUrl":"images\\\\Exo Dishwash Liquid 200.jpg","description":"Powerful degreaser, economical."},
  {"id":4,"name":"Genteel Dishwash Liquid","nameKn":"Genteel ಡಿಶ್ವಾಶ್ ಲಿಕ್ವಿಡ್","category":"liquid","price":105,"imageUrl":"images\\\\Genteel Dishwash Liquid 101.jpg","description":"Mild on skin, tough on stains."},
  {"id":5,"name":"Fortune Dishwash Liquid","nameKn":"Fortune ಡಿಶ್ವಾಶ್ ಲಿಕ್ವಿಡ್","category":"liquid","price":260,"imageUrl":"images\\\\Fortune Dishwash Liquid 260.jpg","description":"Budget-friendly, removes oil quickly."},
  {"id":6,"name":"Godrej Ezee Dishwash Liquid","nameKn":"Godrej Ezee ಡಿಶ್ವಾಶ್ ಲಿಕ್ವಿಡ್","category":"liquid","price":130,"imageUrl":"images\\\\Godrej Ezee Dishwash Liquid 250.jpg","description":"Powerful formula, safe for utensils."},
  {"id":7,"name":"Amazon Brand Presto Dishwash Liquid","nameKn":"Amazon Presto ಡಿಶ್ವಾಶ್ ಲಿಕ್ವಿಡ್","category":"liquid","price":99,"imageUrl":"images\\\\Amazon Brand Presto Dishwash Liquid 450.jpg","description":"Value pack, long-lasting."},
  {"id":8,"name":"Herbal Strategi Dishwash Liquid","nameKn":"Herbal Strategi ಡಿಶ್ವಾಶ್ ಲಿಕ್ವಿಡ್","category":"liquid","price":320,"imageUrl":"images\\\\Herbal Strategi Dishwash Liquid 320.jpg","description":"Natural ingredients, eco-friendly."},
  {"id":9,"name":"Safewash Dishwash Liquid","nameKn":"Safewash ಡಿಶ್ವಾಶ್ ಲಿಕ್ವಿಡ್","category":"liquid","price":230,"imageUrl":"images\\\\Safewash Dishwash Liquid 225.jpg","description":"Antibacterial, fresh fragrance."},
  {"id":10,"name":"Clean Mate Dishwash Liquid","nameKn":"Clean Mate ಡಿಶ್ವಾಶ್ ಲಿಕ್ವಿಡ್","category":"liquid","price":140,"imageUrl":"images\\\\Clean Mate Dishwash Liquid 132.jpg","description":"Daily use, effective on grease."},
  {"id":41,"name":"Vim Dishwash Liquid Refill","nameKn":"Vim ರೀಫಿಲ್","category":"liquid","price":300,"imageUrl":"images\\\\Vim Dishwash Liquid Refill Pack 300.jpg","description":"Economy refill pack, same formula."},
  {"id":42,"name":"Exo Dishwash Liquid Refill","nameKn":"Exo ರೀಫಿಲ್","category":"liquid","price":280,"imageUrl":"images\\\\Exo Dishwash Liquid Refill 280.jpg","description":"Bulk refill, saves plastic."},
  {"id":43,"name":"Genteel Dishwash Liquid Refill","nameKn":"Genteel ರೀಫಿಲ್","category":"liquid","price":270,"imageUrl":"images\\\\Genteel Dishwash Liquid Refill 270.jpg","description":"Large pouch, easy pour."},
  {"id":11,"name":"Vim Dishwash Powder","nameKn":"Vim ಡಿಶ್ವಾಶ್ ಪುಡಿ","category":"powder","price":95,"imageUrl":"images\\\\Vim Dishwash Powder 85.jpg","description":"Powder formula, deep cleaning."},
  {"id":13,"name":"Exo Dishwash Powder","nameKn":"Exo ಡಿಶ್ವಾಶ್ ಪುಡಿ","category":"powder","price":85,"imageUrl":"images\\\\Exo Dishwash Powder 50.jpg","description":"Scrubs away burnt food."},
  {"id":14,"name":"Genteel Dishwash Powder","nameKn":"Genteel ಡಿಶ್ವಾಶ್ ಪುಡಿ","category":"powder","price":450,"imageUrl":"images\\\\Genteel Dishwash Powder 450.jpg","description":"Gentle on utensils, tough on dirt."},
  {"id":15,"name":"Fortune Dishwash Powder","nameKn":"Fortune ಡಿಶ್ವಾಶ್ ಪುಡಿ","category":"powder","price":660,"imageUrl":"images\\\\Fortune Dishwash Powder 660.jpg","description":"Best value for daily use."},
  {"id":16,"name":"Amazon Presto Dishwash Powder","nameKn":"Amazon Presto ಪುಡಿ","category":"powder","price":70,"imageUrl":"images\\\\Amazon Presto Dishwash Powder 590.jpg","description":"Fragrance-free, high efficiency."},
  {"id":17,"name":"Herbal Strategi Dishwash Powder","nameKn":"Herbal Strategi ಪುಡಿ","category":"powder","price":150,"imageUrl":"images\\\\Herbal Strategi Dishwash Powder 150.jpg","description":"Natural ingredients, plant-based."},
  {"id":18,"name":"Safewash Dishwash Powder","nameKn":"Safewash ಪುಡಿ","category":"powder","price":65,"imageUrl":"images\\\\Safewash Dishwash Powder.jpg","description":"Antibacterial, safe for families."},
  {"id":19,"name":"Clean Mate Dishwash Powder","nameKn":"Clean Mate ಪುಡಿ","category":"powder","price":780,"imageUrl":"images\\\\Clean Mate Dishwash Powder 760.jpg","description":"Small pack, travel friendly."},
  {"id":20,"name":"Eco Friendly Dishwash Powder","nameKn":"Eco Friendly ಪುಡಿ","category":"powder","price":200,"imageUrl":"images\\\\Eco Friendly Dishwash Powder 200.jpg","description":"Biodegradable, zero chemicals."},
  {"id":51,"name":"Vim Dishwash Powder Economy Pack","nameKn":"Vim ಎಕಾನಮಿ ಪ್ಯಾಕ್","category":"powder","price":440,"imageUrl":"images\\\\Vim Dishwash Powder Economy Pack 430.jpg","description":"Family pack, 2x quantity."},
  {"id":21,"name":"Finish Dishwasher 110 Tablets Classic","nameKn":"Finish ಕ್ಲಾಸಿಕ್ ಟ್ಯಾಬ್ಲೆಟ್","category":"tablets","price":1350,"imageUrl":"images\\\\Finish Dishwasher 110 Tablets Classic 1350.jpg","description":"All-in-one, machine safe."},
  {"id":22,"name":"Finish Quantum Tablets","nameKn":"Finish ಕ್ವಾಂಟಮ್","category":"tablets","price":1500,"imageUrl":"images\\\\Finish Quantum Dishwasher Tablets 1500.jpg","description":"Premium power, glass protection."},
  {"id":23,"name":"Bosch Dishwasher Tablets","nameKn":"Bosch ಟ್ಯಾಬ್ಲೆಟ್","category":"tablets","price":320,"imageUrl":"images\\\\Bosch Dishwasher Tablets 500.jpg","description":"Designed for Bosch machines."},
  {"id":24,"name":"IFB Dishwasher Tablets","nameKn":"IFB ಟ್ಯಾಬ್ಲೆಟ್","category":"tablets","price":280,"imageUrl":"images\\\\IFB Dishwasher Tablets 700.jpg","description":"Anti-limescale, rinse aid."},
  {"id":25,"name":"Fortune Dishwasher Tablets","nameKn":"Fortune ಟ್ಯಾಬ್ಲೆಟ್","category":"tablets","price":700,"imageUrl":"images\\\\Fortune Dishwasher Tablets 700.jpg","description":"Budget tablets, good for daily."},
  {"id":26,"name":"Genteel Dishwasher Tablets","nameKn":"Genteel ಟ್ಯಾಬ್ಲೆಟ್","category":"tablets","price":2700,"imageUrl":"images\\\\Genteel Dishwasher Tablets 2650.jpg","description":"Gentle on dishes, effective."},
  {"id":27,"name":"Pril Dishwasher Tablets Large Pack","nameKn":"Pril ದೊಡ್ಡ ಪ್ಯಾಕ್","category":"tablets","price":600,"imageUrl":"images\\\\Pril Dishwasher Tablets Large Pack 400.jpg","description":"30 tablets, great value."},
  {"id":28,"name":"All-in-One Dishwasher Tablets","nameKn":"ಆಲ್ ಇನ್ ವನ್","category":"tablets","price":650,"imageUrl":"images\\\\All-in-One Dishwasher Tablets 750.jpg","description":"Complete care with salt function."},
  {"id":31,"name":"Scotch Brite Scrub Sponge","nameKn":"Scotch Brite ಸ್ಕ್ರಬ್ ಸ್ಪಂಜ್","category":"accessories","price":70,"imageUrl":"images\\\\Scotch Brite Scrub Sponge 70.jpg","description":"Non-scratch, dual side sponge."},
  {"id":32,"name":"Scotch Brite Steel Scrubber","nameKn":"Scotch Brite ಸ್ಟೀಲ್ ಸ್ಕ್ರಬ್ಬರ್","category":"accessories","price":25,"imageUrl":"images\\\\Scotch Brite Steel Scrubber 25.jpg","description":"For tough stains, rust resistant."},
  {"id":33,"name":"Gala Scrub Pad","nameKn":"Gala ಸ್ಕ್ರಬ್ ಪ್ಯಾಡ್","category":"accessories","price":70,"imageUrl":"images\\\\Gala Scrub Pad 70.jpg","description":"Economical scrub pad."},
  {"id":34,"name":"Vim Scrubber Pad","nameKn":"Vim ಸ್ಕ್ರಬ್ಬರ್ ಪ್ಯಾಡ್","category":"accessories","price":15,"imageUrl":"images\\\\Vim Scrubber Pad 10.jpg","description":"Durable, good grip."},
  {"id":35,"name":"Plastic Dish Brush","nameKn":"ಪ್ಲಾಸ್ಟಿಕ್ ಡಿಶ್ ಬ್ರಷ್","category":"accessories","price":600,"imageUrl":"images\\\\Plastic Dish Brush 600.jpg","description":"Ergonomic handle, nylon bristles."},
  {"id":36,"name":"Steel Dish Cleaning Brush","nameKn":"ಸ್ಟೀಲ್ ಡಿಶ್ ಕ್ಲೀನಿಂಗ್ ಬ್ರಷ್","category":"accessories","price":70,"imageUrl":"images\\\\Steel Dish Cleaning Brush.jpg","description":"Stainless steel, long lasting."},
  {"id":37,"name":"Microfiber Cleaning Cloth","nameKn":"ಮೈಕ್ರೋಫೈಬರ್ ಕ್ಲೀನಿಂಗ್ ಕ್ಲಾತ್","category":"accessories","price":100,"imageUrl":"images\\\\Microfiber Cleaning Cloth 100.jpg","description":"Lint-free, absorbent."},
  {"id":38,"name":"Dishwashing Gloves Rubber","nameKn":"ಡಿಶ್ವಾಶಿಂಗ್ ಗ್ಲೌಸ್","category":"accessories","price":700,"imageUrl":"images\\\\Dishwashing Gloves Rubber 700.jpg","description":"Protects hands, non-slip."},
  {"id":39,"name":"Sink Cleaning Brush","nameKn":"ಸಿಂಕ್ ಕ್ಲೀನಿಂಗ್ ಬ್ರಷ್","category":"accessories","price":80,"imageUrl":"images\\\\Sink Cleaning Brush 50.jpg","description":"Angled head, for corners."},
  {"id":40,"name":"Soap Holder for Dishwash","nameKn":"ಸೋಪು ಹೋಲ್ಡರ್","category":"accessories","price":150,"imageUrl":"images\\\\Soap Holder for Dishwash 150.jpg","description":"Draining tray, keeps soap dry."},
  {"id":71,"name":"Scotch Brite Heavy Duty Scrubber","nameKn":"Scotch Brite ಹೆವಿ ಡ್ಯೂಟಿ","category":"accessories","price":30,"imageUrl":"images\\\\Scotch Brite Heavy Duty Scrubber 30.jpg","description":"For burnt pans, extra strong."},
  {"id":73,"name":"Gala Scrubber Combo Pack","nameKn":"Gala ಕಾಂಬೊ","category":"accessories","price":60,"imageUrl":"images\\\\Gala Scrubber Combo Pack 180.jpg","description":"Value pack of 5 scrub pads."},
  {"id":74,"name":"Long Handle Dish Brush","nameKn":"ಲಾಂಗ್ ಹ್ಯಾಂಡಲ್ ಬ್ರಷ್","category":"accessories","price":120,"imageUrl":"images\\\\Long Handle Dish Brush.jpg","description":"Reaches deep utensils."},
  {"id":75,"name":"Bottle Cleaning Brush Set","nameKn":"ಬಾಟಲ್ ಬ್ರಷ್ ಸೆಟ್","category":"accessories","price":1000,"imageUrl":"images\\\\Bottle Cleaning Brush Set 1000.jpg","description":"2 sizes, for narrow necks."},
  {"id":76,"name":"Dishwashing Gloves Premium","nameKn":"ಪ್ರೀಮಿಯಂ ಗ್ಲೌಸ್","category":"accessories","price":200,"imageUrl":"images\\\\Dishwashing Gloves Premium 200.jpg","description":"Cotton lined, extra comfort."},
  {"id":77,"name":"Sink Drain Cleaning Brush","nameKn":"ಸಿಂಕ್ ಡ್ರೈನ್ ಬ್ರಷ್","category":"accessories","price":400,"imageUrl":"images\\\\Sink Drain Cleaning Brush 400.jpg","description":"Flexible, removes debris."},
  {"id":78,"name":"Dish Drying Mat","nameKn":"ಡಿಶ್ ಡ್ರೈಯಿಂಗ್ ಮಾಟ್","category":"accessories","price":550,"imageUrl":"images\\\\Dish Drying Mat 550.jpg","description":"Quick dry, non-slip."},
  {"id":79,"name":"Soap Dispenser for Kitchen","nameKn":"ಸೋಪು ಡಿಸ್ಪೆನ್ಸರ್","category":"accessories","price":300,"imageUrl":"images\\\\Soap Dispenser for Kitchen 180.jpg","description":"Stainless steel, pump action."},
  {"id":80,"name":"Scrubber Holder Stand","nameKn":"ಸ್ಕ್ರಬ್ಬರ್ ಹೋಲ್ಡರ್","category":"accessories","price":80,"imageUrl":"images\\\\Scrubber Holder Stand 250.jpg","description":"Keeps sink organized."},
  {"id":81,"name":"Dish Rack Steel","nameKn":"ಡಿಶ್ ರ್ಯಾಕ್ ಸ್ಟೀಲ್","category":"accessories","price":900,"imageUrl":"images\\\\Dish Rack Steel.jpg","description":"Rust-proof, large capacity."},
  {"id":82,"name":"Dishwash Bar Holder","nameKn":"ಬಾರ್ ಹೋಲ್ಡರ್","category":"accessories","price":50,"imageUrl":"images\\\\Dishwash Bar Holder 140.jpg","description":"Simple plastic holder."},
  {"id":83,"name":"Sink Organizer Basket","nameKn":"ಸಿಂಕ್ ಆರ್ಗನೈಜರ್","category":"accessories","price":200,"imageUrl":"images\\\\Sink Organizer Basket 200.jpg","description":"Holds sponges and brushes."},
  {"id":84,"name":"Sponge with Handle","nameKn":"ಹ್ಯಾಂಡಲ್ ಸ್ಪಂಜ್","category":"accessories","price":660,"imageUrl":"images\\\\Sponge with Handle 660.jpg","description":"Prevents hand contact with water."},
  {"id":85,"name":"Dish Cleaning Brush Rotating","nameKn":"ರೊಟೇಟಿಂಗ್ ಬ್ರಷ್","category":"accessories","price":360,"imageUrl":"images\\\\Dish Cleaning Brush Rotating 360.jpg","description":"360° rotation, fills with soap."},
  {"id":86,"name":"Dish Cleaning Kit Combo","nameKn":"ಕ್ಲೀನಿಂಗ್ ಕಿಟ್ ಕಾಂಬೊ","category":"accessories","price":550,"imageUrl":"images\\\\Dish Cleaning Kit Combo 550.jpg","description":"Sponge, brush, gloves, cloth."},
  {"id":87,"name":"Premium Sink Cleaning Kit","nameKn":"ಪ್ರೀಮಿಯಂ ಸಿಂಕ್ ಕಿಟ್","category":"accessories","price":500,"imageUrl":"images\\\\Premium Sink Cleaning Kit 500.jpg","description":"Complete set with caddy."},
  {"id":88,"name":"Complete Kitchen Cleaning Set","nameKn":"ಕಂಪ್ಲೀಟ್ ಕಿಚನ್ ಸೆಟ್","category":"accessories","price":390,"imageUrl":"images\\\\Complete Kitchen Cleaning Set 390.jpg","description":"Everything for daily cleaning."}
]';
  $data = json_decode($json, true);
  if (!is_array($data)) {
    return [];
  }
  // Convert to our product format with image paths fixed
  $result = [];
  foreach ($data as $item) {
    $id = $item['id'] ?? (1000 + count($result));
    $category = $item['category'] ?? 'accessories';
    if (!in_array($category, ['liquid', 'powder', 'accessories'])) {
      $category = 'accessories';
    }
    // Fix image path: convert Windows path to web path
    $imagePath = $item['imageUrl'] ?? '';
    if ($imagePath && !str_starts_with($imagePath, 'http') && !str_starts_with($imagePath, 'data:')) {
      $imagePath = str_replace('D:\\KSR Guru JI\\', '', $imagePath);
      $imagePath = str_replace('\\', '/', $imagePath);
      if (!str_starts_with($imagePath, 'images/') && !str_starts_with($imagePath, '../')) {
        $imagePath = '../images/' . $imagePath;
      } elseif (!str_starts_with($imagePath, '../')) {
        $imagePath = '../' . $imagePath;
      }
    } else {
      $imagePath = ''; // fallback to emoji
    }
    $result[] = [
      'id' => $id,
      'name' => $item['name'],
      'kn_name' => $item['nameKn'] ?? $item['name'],
      'price' => (float) $item['price'],
      'category' => $category,
      'variants' => [['label' => '1 unit', 'price' => (float) $item['price']]],
      'emoji' => '🧴',
      'description_en' => $item['description'] ?? 'Quality dishwash product',
      'image' => $imagePath
    ];
  }
  $fallback = $result;
  file_put_contents($jsonPath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  return $fallback;
}
// Get products (dishwash category)
$products = getDishwashProducts();
// If no products from DB, use fallback
if (empty($products)) {
  $products = getFallbackDishwashProducts();
}
// Ensure each product has a 'variants' array and an 'image' key
foreach ($products as &$p) {
  if (!isset($p['variants']) || !is_array($p['variants']) || empty($p['variants'])) {
    $p['variants'] = [['label' => '1 unit', 'price' => $p['price']]];
  }
  // Ensure image key exists (fallback to empty)
  $p['image'] = $p['image'] ?? '';
}
unset($p);
// Get cart count and total
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
  <title>Dishwash · Premium Dishwashing Solutions · KSR Guru</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <style>
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

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
      flex-wrap: wrap;
      gap: 16px;
    }

    .shop-name h1 {
      font-size: 2.2rem;
      font-weight: 700;
      color: #3e2c1b;
      line-height: 1.2;
    }

    .shop-name .kannada {
      font-size: 1.1rem;
      color: #b45f2b;
      font-weight: 500;
    }

    .cart-link {
      background: #f7ede3;
      padding: 12px 24px;
      border-radius: 60px;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: background 0.2s;
      text-decoration: none;
      color: inherit;
      font-weight: 600;
      font-size: 0.95rem;
      position: relative;
    }

    .cart-link i {
      font-size: 1.4rem;
      color: #c97e2c;
    }

    .cart-link .cart-badge {
      background: #b45f2b;
      color: white;
      font-size: 0.85rem;
      font-weight: 700;
      padding: 2px 10px;
      border-radius: 40px;
      margin-left: 6px;
    }

    .cart-link:hover {
      background: #ffe2d0;
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

    .search-section {
      margin-bottom: 32px;
    }

    .search-box {
      display: flex;
      align-items: center;
      background: #f3eee9;
      border-radius: 60px;
      padding: 8px 24px;
      margin-bottom: 20px;
      border: 1px solid #e6d9cd;
      max-width: 500px;
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
      color: #2f2216;
    }

    .filter-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .pill {
      background: #f3eee9;
      padding: 10px 24px;
      border-radius: 60px;
      font-size: 1rem;
      font-weight: 500;
      color: #4f3e2e;
      cursor: pointer;
      transition: all 0.2s;
    }

    .pill.active {
      background: #5c3e24;
      color: white;
    }

    .pill:hover {
      background: #e0cfbf;
    }

    .top-picks {
      margin: 32px 0 40px;
      background: linear-gradient(135deg, #fff6ed 0%, #fee9dd 100%);
      border-radius: 36px;
      padding: 24px 28px;
      border: 1px solid #fadbc8;
    }

    .top-picks h3 {
      font-size: 1.8rem;
      font-weight: 700;
      color: #3e2c1b;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .top-picks h3 i {
      color: #e67e22;
      font-size: 2rem;
    }

    .top-picks-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 24px;
    }

    .top-pick-card {
      background: white;
      border-radius: 28px;
      padding: 16px;
      text-align: center;
      box-shadow: 0 8px 18px rgba(0, 0, 0, 0.05);
      transition: transform 0.2s;
    }

    .top-pick-card:hover {
      transform: translateY(-6px);
    }

    .top-pick-img {
      width: 100%;
      aspect-ratio: 1;
      border-radius: 24px;
      background: #fcf4ec;
      margin-bottom: 12px;
      font-size: 3.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .top-pick-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .top-pick-name {
      font-weight: 700;
      font-size: 1rem;
      color: #2f2216;
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
      width: 44px;
      height: 44px;
      border-radius: 50px;
      font-size: 1.2rem;
      cursor: pointer;
      transition: 0.1s;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto;
    }

    .top-pick-btn:active {
      transform: scale(0.94);
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 28px;
      margin-top: 20px;
      margin-bottom: 50px;
    }

    .product-card {
      background: white;
      border-radius: 32px;
      padding: 18px 16px 22px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
      border: 1px solid #f2e5d9;
      transition: transform 0.2s, box-shadow 0.2s;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }

    .product-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 30px rgba(0, 0, 0, 0.08);
    }

    .product-image {
      width: 100px;
      height: 100px;
      border-radius: 50px;
      background: #fcf4ec;
      border: 2px solid #f0d9c4;
      font-size: 3.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .product-name {
      font-weight: 700;
      font-size: 1.1rem;
      color: #2f2216;
      margin-top: 4px;
    }

    .product-name-kn {
      font-size: 0.8rem;
      color: #7b5f45;
      margin-bottom: 6px;
    }

    .product-description {
      font-size: 0.8rem;
      color: #8a6e54;
      margin: 6px 0 10px;
      line-height: 1.4;
      padding: 0 4px;
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
      font-size: 1.2rem;
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
      transition: 0.1s;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .add-btn.added-flash {
      background: #4caf50 !important;
      color: white !important;
    }

    .add-btn:active {
      transform: scale(0.95);
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
      font-size: 0.85rem;
      color: #b9a690;
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid #f0e3d7;
    }

    @media (max-width: 680px) {
      #app {
        padding: 20px;
      }

      .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 18px;
      }

      .top-picks-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      }

      .product-image {
        width: 90px;
        height: 90px;
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
        <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ">
      </div>
      <div class="filter-pills" id="filterPills">
        <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
        <div class="pill" data-category="liquid">Liquid / ದ್ರವ</div>
        <div class="pill" data-category="powder">Powder / ಪುಡಿ</div>
        <div class="pill" data-category="accessories">Accessories / ಉಪಕರಣಗಳು</div>
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
                          // Determine category
                          $subcat = $p['category'] ?? 'accessories';
                          if (!in_array($subcat, ['liquid', 'powder', 'accessories'])) {
                            $subcat = 'accessories';
                          }
                          $out[] = [
                            'id' => $p['id'],
                            'nameEn' => $p['name'],
                            'nameKn' => $p['kn_name'] ?? $p['name'],
                            'category' => $subcat,
                            'variants' => $p['variants'] ?? [['label' => '1 unit', 'price' => $p['price']]],
                            'emoji' => $p['emoji'] ?? '🧴',
                            'description' => $p['description_en'] ?? 'Dishwash helper product',
                            'image' => $p['image'] ?? ''
                          ];
                        }
                        echo json_encode($out);
                        ?>;
      let cart = [];
      const basePath = 'dishwash.php';
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
        const sel = document.querySelector(`.variant-selector[data-base="${productId}"]`);
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
          grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No dishwash products found ✨</div>';
          return;
        }
        grid.innerHTML = filtered.map(p => {
          const defaultVariant = 0;
          const defaultPrice = p.variants[defaultVariant]?.price || 0;
          const imageHtml = p.image ? `<img src="${p.image}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='${p.emoji}'">` : p.emoji;
          return `<div class="product-card" data-base="${p.id}">
        <div class="product-image">${imageHtml}</div>
        <div class="product-name">${p.nameEn}</div>
        ${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}
        <div class="product-description">${p.description}</div>
        ${p.variants && p.variants.length > 1 
          ? `<select class="variant-selector" data-base="${p.id}">
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
          const base = sel.dataset.base;
          const priceDiv = sel.closest('.product-card').querySelector('.product-price');
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