<?php
// ============================================================
// hair-care.php - Dynamic Hair Care product page (FIXED with images)
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
      echo json_encode(getHairCareProducts($search));
      exit;
    case 'cart':
      echo json_encode($_SESSION['cart']);
      exit;
    case 'add_cart':
      $id = (int) ($_POST['id'] ?? 0);
      if ($id) {
        $product = getProductById($id);
        if (!$product) {
          $fallbackProducts = getFallbackHairCareProducts();
          foreach ($fallbackProducts as $fp) {
            if ($fp['id'] == $id) {
              $product = [
                'id' => $fp['id'],
                'name' => $fp['name'],
                'kn_name' => $fp['kn_name'] ?? $fp['name'],
                'price' => $fp['price'],
                'icon' => 'fa-seedling'
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
              'icon' => $product['icon'] ?? 'fa-seedling'
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
// Helper function to fetch hair care products using PDO with Dynamic Column Checking
function getHairCareProducts(string $search = ''): array
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
    $whereCategory = $hasSlug ? "(c.slug = 'hair-care' OR c.id = 'hair-care' OR c.name LIKE '%hair%')" : "(c.name LIKE '%hair%')";
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
      $row['description_en'] = $row['description_en'] ?? 'Quality hair care product';
      if (!empty($row['image'])) {
        $row['image'] = normalizeImagePath($row['image']);
      }
      $row['imageUrl'] = $row['image'] ?? '';
      $row['variants'] = [['label' => '1 unit', 'price' => (float)$row['price']]];
    }
    return $rows;
  } catch (Throwable $e) {
    return [];
  }
}
// Fallback static product data
function getFallbackHairCareProducts(): array
{
  static $fallback = null;
  if ($fallback !== null) {
    return $fallback;
  }
  $jsonPath = __DIR__ . '/hair-care-fallback.json';
  if (file_exists($jsonPath)) {
    $fallback = json_decode(file_get_contents($jsonPath), true);
    if (is_array($fallback)) {
      return $fallback;
    }
  }
  $json = '[
  {"id":1,"nameEn":"Herbal Shampoo","category":"shampoo","price":180,"imageUrl":"images\\\\Herbal Shampoo.jpg"},
  {"id":2,"nameEn":"Anti-Dandruff Shampoo","category":"shampoo","price":250,"imageUrl":"images\\\\Anti-Dandruff Shampoo 250.jpg"},
  {"id":178,"nameEn":"Anti-Dandruff Shampoo","category":"shampoo","price":360,"imageUrl":"images\\\\Anti-Dandruff Shampoo 360.jpg"},
  {"id":179,"nameEn":"Anti-Dandruff Shampoo","category":"shampoo","price":420,"imageUrl":"images\\\\Anti-Dandruff Shampoo 420.jpg"},
  {"id":3,"nameEn":"Himalaya Gentle Daily Care Protein Shampoo","category":"shampoo","price":190,"imageUrl":"images\\\\Himalaya Gentle Daily Care Protein Shampoo 190.jpg"},
  {"id":180,"nameEn":"Biotique Kelp Protein Shampoo for Falling Hair","category":"shampoo","price":190,"imageUrl":"images\\\\Biotique Kelp Protein Shampoo for Falling Hair 370.jpg"},
  {"id":4,"nameEn":"Gentle Baby Shampoo","category":"shampoo","price":385,"imageUrl":"images\\\\Gentle Baby Shampoo 385.jpg"},
  {"id":181,"nameEn":"Cetaphil Baby Gentle Wash & Shampoo baby","category":"shampoo","price":300,"imageUrl":"images\\\\Cetaphil Baby Gentle Wash & Shampoo baby.jpg"},
  {"id":5,"nameEn":"Onion Hair Shampoo","category":"shampoo","price":260,"imageUrl":"images\\\\Onion Hair Shampoo.jpg"},
  {"id":6,"nameEn":"Ayurvedic Shampoo","category":"shampoo","price":220,"imageUrl":"images\\\\Forest Essentials Bhringraj & Shikakai Ayurvedic Anti Dandruff Hair Cleanser Shampoo 970.jpg"},
  {"id":181,"nameEn":"KP Namboodiri s Ayurvedic Hair Care Shampoo","category":"shampoo","price":220,"imageUrl":"images\\\\KP Namboodiri\'s Ayurvedic Hair Care Shampoo 210.jpg"},
  {"id":7,"nameEn":"Biotin Shampoo","category":"shampoo","price":320,"imageUrl":"images\\\\Biotin Shampoo.jpg"},
  {"id":8,"nameEn":"Keratin Smooth Shampoo","category":"shampoo","price":680,"imageUrl":"images\\\\Tresemme Keratin Smooth Shampoo 680.jpg"},
  {"id":182,"nameEn":"Tresemme Keratin Smooth Shampoo","category":"shampoo","price":620,"imageUrl":"images\\\\Tresemme Keratin Smooth Shampoo 620.jpg"},
  {"id":183,"nameEn":"Tresemme Keratin Smooth Shampoo","category":"shampoo","price":150,"imageUrl":"images\\\\Tresemme Keratin Smooth Shampoo 150.jpg"},
  {"id":9,"nameEn":"Color Protection Shampoo","category":"shampoo","price":350,"imageUrl":"images\\\\Color Protection Shampoo.jpg"},
  {"id":10,"nameEn":"Sulfate-Free Shampoo","category":"shampoo","price":420,"imageUrl":"images\\\\Sulfate-Free Shampoo.jpg"},
  {"id":11,"nameEn":"Tea Tree Shampoo","category":"shampoo","price":610,"imageUrl":"images\\\\The Body Shop Tea Tree Purifying & Balancing Shampoo 620.jpg"},
  {"id":12,"nameEn":"Volumizing Shampoo","category":"shampoo","price":290,"imageUrl":"images\\\\Volumizing Shampoo.jpg"},
  {"id":13,"nameEn":"Repair & Restore Shampoo","category":"shampoo","price":310,"imageUrl":"images\\\\Repair & Restore Shampoo.jpg"},
  {"id":14,"nameEn":"Charcoal Detox Shampoo","category":"shampoo","price":340,"imageUrl":"images\\\\Charcoal Detox Shampoo.jpg"},
  {"id":15,"nameEn":"Onion & Biotin Shampoo","category":"shampoo","price":360,"imageUrl":"images\\\\Onion & Biotin Shampoo.jpg"},
  {"id":16,"nameEn":"Apple Cider Vinegar Shampoo","category":"shampoo","price":380,"imageUrl":"images\\\\Apple Cider Vinegar Shampoo.jpg"},
  {"id":17,"nameEn":"Moisture Lock Shampoo","category":"shampoo","price":320,"imageUrl":"images\\\\Moisture Lock Shampoo.jpg"},
  {"id":18,"nameEn":"Daily Gentle Shampoo","category":"shampoo","price":210,"imageUrl":"images\\\\Daily Gentle Shampoo.jpg"},
  {"id":19,"nameEn":"Kids Tear-Free Shampoo","category":"shampoo","price":190,"imageUrl":"images\\\\Kids Tear-Free Shampoo.jpg"},
  {"id":20,"nameEn":"Keratin & Argan Shampoo","category":"shampoo","price":420,"imageUrl":"images\\\\Keratin & Argan Shampoo.jpg"},
  {"id":21,"nameEn":"Scalp Purifying Shampoo","category":"shampoo","price":350,"imageUrl":"images\\\\Scalp Purifying Shampoo.jpg"},
  {"id":22,"nameEn":"Anti-Hair Fall Onion Shampoo","category":"shampoo","price":380,"imageUrl":"images\\\\Anti-Hair Fall Onion Shampoo.jpg"},
  {"id":23,"nameEn":"Hydrating Aloe Shampoo","category":"shampoo","price":310,"imageUrl":"images\\\\Hydrating Aloe Shampoo.jpg"},
  {"id":24,"nameEn":"Damage Control Shampoo","category":"shampoo","price":330,"imageUrl":"images\\\\Damage Control Shampoo.jpg"},
  {"id":25,"nameEn":"Dandruff Control Neem Shampoo","category":"shampoo","price":260,"imageUrl":"images\\\\Dandruff Control Neem Shampoo.jpg"},
  {"id":26,"nameEn":"Hyaluronic Moisture Shampoo","category":"shampoo","price":480,"imageUrl":"images\\\\Hyaluronic Moisture Shampoo.jpg"},
  {"id":27,"nameEn":"Collagen Volume Shampoo","category":"shampoo","price":520,"imageUrl":"images\\\\Collagen Volume Shampoo.jpg"},
  {"id":28,"nameEn":"Anti-Pollution Shampoo","category":"shampoo","price":390,"imageUrl":"images\\\\Anti-Pollution Shampoo.jpg"},
  {"id":29,"nameEn":"Scalp Cooling Menthol Shampoo","category":"shampoo","price":270,"imageUrl":"images\\\\Scalp Cooling Menthol Shampoo.jpg"},
  {"id":30,"nameEn":"Hard Water Defense Shampoo","category":"shampoo","price":450,"imageUrl":"images\\\\Hard Water Defense Shampoo.jpg"},
  {"id":31,"nameEn":"Smoothening Conditioner","category":"conditioner","price":190,"imageUrl":"images\\\\soap\\\\Smoothening Conditioner.jpg"},
  {"id":32,"nameEn":"Hair Fall Control Conditioner","category":"conditioner","price":220,"imageUrl":"images\\\\soap\\\\Hair Fall Control Conditioner.jpg"},
  {"id":33,"nameEn":"Keratin Conditioner","category":"conditioner","price":420,"imageUrl":"images\\\\Keratin Conditioner 420.jpg"},
  {"id":185,"nameEn":"Keratin Conditioner","category":"conditioner","price":420,"imageUrl":"images\\\\Keratin Conditioner 300.jpg"},
  {"id":186,"nameEn":"Keratin Conditioner","category":"conditioner","price":420,"imageUrl":"images\\\\Tresemme Keratin Smooth Conditioner 200.jpg"},
  {"id":34,"nameEn":"Moisturizing Conditioner","category":"conditioner","price":210,"imageUrl":"images\\\\soap\\\\Moisturizing Conditioner.jpg"},
  {"id":35,"nameEn":"Damage Repair Conditioner","category":"conditioner","price":300,"imageUrl":"images\\\\Damage Repair Conditioner 300.jpg"},
  {"id":36,"nameEn":"Biotin Conditioner","category":"conditioner","price":300,"imageUrl":"images\\\\Biotin Conditioner 790.jpg"},
  {"id":37,"nameEn":"Argan Oil Conditioner","category":"conditioner","price":350,"imageUrl":"images\\\\Argan Oil Conditioner 300.jpg"},
  {"id":38,"nameEn":"Anti-Frizz Conditioner","category":"conditioner","price":260,"imageUrl":"images\\\\Anti-Frizz Conditioner.jpg"},
  {"id":39,"nameEn":"Color Protect Conditioner","category":"conditioner","price":290,"imageUrl":"images\\\\soap\\\\Color Protect Conditioner.jpg"},
  {"id":40,"nameEn":"Coconut Milk Conditioner","category":"conditioner","price":240,"imageUrl":"images\\\\soap\\\\Coconut Milk Conditioner.jpg"},
  {"id":41,"nameEn":"Herbal Conditioner","category":"conditioner","price":220,"imageUrl":"images\\\\soap\\\\Herbal Conditioner.jpg"},
  {"id":42,"nameEn":"Volume Boost Conditioner","category":"conditioner","price":400,"imageUrl":"images\\\\Volume Boost Conditioner 400.jpg"},
  {"id":43,"nameEn":"Deep Nourish Conditioner","category":"conditioner","price":260,"imageUrl":"images\\\\soap\\\\Deep Nourish Conditioner.jpg"},
  {"id":44,"nameEn":"Silk Smooth Conditioner","category":"conditioner","price":300,"imageUrl":"images\\\\soap\\\\Silk Smooth Conditioner.jpg"},
  {"id":45,"nameEn":"Strengthening Conditioner","category":"conditioner","price":280,"imageUrl":"images\\\\soap\\\\Strengthening Conditioner.jpg"},
  {"id":46,"nameEn":"Herbal Repair Conditioner","category":"conditioner","price":250,"imageUrl":"images\\\\soap\\\\Herbal Repair Conditioner.jpg"},
  {"id":47,"nameEn":"Leave-In Conditioner","category":"conditioner","price":350,"imageUrl":"images\\\\soap\\\\Leave-In Conditioner.jpg"},
  {"id":48,"nameEn":"Keratin Smooth Conditioner Pro","category":"conditioner","price":420,"imageUrl":"images\\\\Tresemme Keratin Smooth Conditioner 200.jpg"},
  {"id":49,"nameEn":"Coconut Repair Conditioner","category":"conditioner","price":280,"imageUrl":"images\\\\soap\\\\Coconut Repair Conditioner.jpg"},
  {"id":50,"nameEn":"Aloe Vera Conditioner","category":"conditioner","price":240,"imageUrl":"images\\\\soap\\\\Aloe Vera Conditioner.jpg.webp"},
  {"id":51,"nameEn":"Anti-Breakage Conditioner","category":"conditioner","price":310,"imageUrl":"images\\\\soap\\\\Anti-Breakage Conditioner.jpg"},
  {"id":52,"nameEn":"Volume Boost Protein Conditioner","category":"conditioner","price":330,"imageUrl":"images\\\\soap\\\\Volume Boost Protein Conditioner.jpg"},
  {"id":53,"nameEn":"Collagen Repair Conditioner","category":"conditioner","price":520,"imageUrl":"images\\\\soap\\\\Collagen Repair Conditioner.jpg"},
  {"id":54,"nameEn":"Hyaluronic Hydration Conditioner","category":"conditioner","price":480,"imageUrl":"images\\\\soap\\\\Hyaluronic Hydration Conditioner.jpg"},
  {"id":55,"nameEn":"Color Lock Conditioner","category":"conditioner","price":420,"imageUrl":"images\\\\soap\\\\Color Lock Conditioner.jpg"},
  {"id":56,"nameEn":"Anti-Pollution Conditioner","category":"conditioner","price":390,"imageUrl":"images\\\\soap\\\\Coconut Repair Conditioner.jpg"},
  {"id":57,"nameEn":"Coconut Hair Oil","category":"oil","price":180,"imageUrl":"images\\\\Parachute Coconut Hair Oil 180.jpg"},
  {"id":58,"nameEn":"Almond Hair Oil","category":"oil","price":360,"imageUrl":"images\\\\Bajaj Almond Drops Hair Oil 360.jpg"},
  {"id":59,"nameEn":"Amla Hair Oil","category":"oil","price":230,"imageUrl":"images\\\\Amla Hair Oil 230.jpg"},
  {"id":60,"nameEn":"Onion Hair Oil","category":"oil","price":250,"imageUrl":"images\\\\Onion Hair Oil 250.jpg"},
  {"id":61,"nameEn":"Ayurvedic Hair Oil","category":"oil","price":300,"imageUrl":"images\\\\Ayurvedic Hair Oil.jpg"},
  {"id":62,"nameEn":"Castor Oil","category":"oil","price":180,"imageUrl":"images\\\\Castor Hair Oil 240.jpg"},
  {"id":63,"nameEn":"Olive Hair Oil","category":"oil","price":240,"imageUrl":"images\\\\Olive Hair Oil.jpg"},
  {"id":64,"nameEn":"Brahmi Hair Oil","category":"oil","price":210,"imageUrl":"images\\\\Brahmi Hair Oil.jpg"},
  {"id":65,"nameEn":"Hibiscus Hair Oil","category":"oil","price":260,"imageUrl":"images\\\\Hibiscus Hair Oil.jpg"},
  {"id":66,"nameEn":"Onion Black Seed Oil","category":"oil","price":380,"imageUrl":"images\\\\Onion Black Seed Oil.jpg"},
  {"id":67,"nameEn":"Hair Growth Oil","category":"oil","price":420,"imageUrl":"images\\\\Hair Growth Oil.jpg"},
  {"id":68,"nameEn":"Cooling Hair Oil","category":"oil","price":190,"imageUrl":"images\\\\Cooling Hair Oil.jpg"},
  {"id":69,"nameEn":"Neem Hair Oil","category":"oil","price":240,"imageUrl":"images\\\\Neem Hair Oil.jpg"},
  {"id":70,"nameEn":"Tea Tree Hair Oil","category":"oil","price":280,"imageUrl":"images\\\\Tea Tree Hair Oil.jpg"},
  {"id":71,"nameEn":"Garlic Hair Oil","category":"oil","price":260,"imageUrl":"images\\\\Garlic Hair Oil.jpg"},
  {"id":72,"nameEn":"Fenugreek Hair Oil","category":"oil","price":240,"imageUrl":"images\\\\Fenugreek Hair Oil.jpg"},
  {"id":73,"nameEn":"Vitamin E Hair Oil","category":"oil","price":300,"imageUrl":"images\\\\Vitamin E Hair Oil.jpg"},
  {"id":74,"nameEn":"Onion Black Seed Hair Oil","category":"oil","price":420,"imageUrl":"images\\\\Onion Black Seed Hair Oil.jpg"},
  {"id":75,"nameEn":"Ayurvedic 18 Herbs Oil","category":"oil","price":390,"imageUrl":"images\\\\Ayurvedic 18 Herbs Oil.jpg"},
  {"id":76,"nameEn":"Bhringraj Hair Oil","category":"oil","price":560,"imageUrl":"images\\\\Bhringraj Hair Oil 560.jpg"},
  {"id":77,"nameEn":"Intensive Hair Growth Oil","category":"oil","price":450,"imageUrl":"images\\\\Intensive Hair Growth Oil.jpg"},
  {"id":78,"nameEn":"Cooling Brahmi Amla Oil","category":"oil","price":260,"imageUrl":"images\\\\Cooling Brahmi Amla Oil.jpg"},
  {"id":79,"nameEn":"Moroccan Argan Oil","category":"oil","price":850,"imageUrl":"images\\\\Moroccan Argan Oil.jpg"},
  {"id":80,"nameEn":"Cold Pressed Coconut Oil","category":"oil","price":450,"imageUrl":"images\\\\Cold Pressed Coconut Oil.jpg"},
  {"id":81,"nameEn":"Ayurvedic Kesh King Oil","category":"oil","price":380,"imageUrl":"images\\\\Ayurvedic Kesh King Oil.jpg"},
  {"id":82,"nameEn":"21 Herbs Hair Oil","category":"oil","price":720,"imageUrl":"images\\\\21 Herbs Hair Oil.jpg"},
  {"id":83,"nameEn":"Keratin Hair Mask","category":"other","price":450,"imageUrl":"images\\\\Keratin Hair Mask.jpg"},
  {"id":84,"nameEn":"Argan Oil Hair Mask","category":"other","price":500,"imageUrl":"images\\\\Argan Oil Hair Mask.jpg"},
  {"id":85,"nameEn":"Deep Conditioning Mask","category":"other","price":420,"imageUrl":"images\\\\Deep Conditioning Mask.jpg"},
  {"id":86,"nameEn":"Anti-Dandruff Hair Mask","category":"other","price":300,"imageUrl":"images\\\\Anti-Dandruff Hair Mask.jpg"},
  {"id":87,"nameEn":"Hair Spa Cream Mask","category":"other","price":550,"imageUrl":"images\\\\Hair Spa Cream Mask.jpg"},
  {"id":88,"nameEn":"Banana Hair Mask","category":"other","price":350,"imageUrl":"images\\\\Banana Hair Mask.jpg"},
  {"id":89,"nameEn":"Egg Protein Hair Mask","category":"other","price":300,"imageUrl":"images\\\\Egg Protein Hair Mask.jpg"},
  {"id":90,"nameEn":"Onion Hair Mask","category":"other","price":420,"imageUrl":"images\\\\Onion Hair Mask.jpg"},
  {"id":91,"nameEn":"Hair Fall Control Mask","category":"other","price":380,"imageUrl":"images\\\\Hair Fall Control Mask.jpg"},
  {"id":92,"nameEn":"Hydrating Hair Mask","category":"other","price":400,"imageUrl":"images\\\\Hydrating Hair Mask.jpg"},
  {"id":93,"nameEn":"Repair Therapy Mask","category":"other","price":450,"imageUrl":"images\\\\Repair Therapy Mask.jpg"},
  {"id":94,"nameEn":"Charcoal Hair Mask","category":"other","price":420,"imageUrl":"images\\\\Charcoal Hair Mask.jpg"},
  {"id":95,"nameEn":"Keratin Repair Mask","category":"other","price":500,"imageUrl":"images\\\\Keratin Repair Mask.jpg"},
  {"id":96,"nameEn":"Smooth & Shine Mask","category":"other","price":380,"imageUrl":"images\\\\Smooth & Shine Mask.jpg"},
  {"id":97,"nameEn":"Protein Boost Mask","category":"other","price":410,"imageUrl":"images\\\\Protein Boost Mask.jpg"},
  {"id":98,"nameEn":"Anti-Frizz Hair Mask","category":"other","price":390,"imageUrl":"images\\\\Anti-Frizz Hair Mask.jpg"},
  {"id":99,"nameEn":"Keratin Spa Mask","category":"other","price":600,"imageUrl":"images\\\\Keratin Spa Mask.jpg"},
  {"id":100,"nameEn":"Onion Black Seed Mask","category":"other","price":450,"imageUrl":"images\\\\Onion Black Seed Mask.jpg"},
  {"id":101,"nameEn":"Protein Repair Mask","category":"other","price":480,"imageUrl":"images\\\\Protein Repair Mask.jpg"},
  {"id":102,"nameEn":"Deep Nourish Cream Mask","category":"other","price":520,"imageUrl":"images\\\\Deep Nourish Cream Mask.jpg"},
  {"id":103,"nameEn":"Aloe Hydration Mask","category":"other","price":400,"imageUrl":"images\\\\Aloe Hydration Mask.jpg"},
  {"id":104,"nameEn":"Collagen Hair Spa Mask","category":"other","price":650,"imageUrl":"images\\\\Collagen Hair Spa Mask.jpg"},
  {"id":105,"nameEn":"Smoothening Hair Spa Cream","category":"other","price":600,"imageUrl":"images\\\\Smoothening Hair Spa Cream.jpg"},
  {"id":106,"nameEn":"Color Protection Hair Mask","category":"other","price":550,"imageUrl":"images\\\\Color Protection Hair Mask.jpg"},
  {"id":107,"nameEn":"Anti-Hair Fall Therapy Mask","category":"other","price":520,"imageUrl":"images\\\\Anti-Hair Fall Therapy Mask.jpg"},
  {"id":108,"nameEn":"Hair Growth Serum","category":"other","price":450,"imageUrl":"images\\\\Hair Growth Serum 400.jpg"},
  {"id":109,"nameEn":"Anti-Frizz Serum","category":"other","price":390,"imageUrl":"images\\\\Anti-Frizz Serum 390.jpg"},
  {"id":110,"nameEn":"Keratin Hair Serum","category":"other","price":250,"imageUrl":"images\\\\Keratin Hair Serum.jpg"},
  {"id":111,"nameEn":"Shine Boost Serum","category":"other","price":300,"imageUrl":"images\\\\Shine Boost Serum.jpg"},
  {"id":112,"nameEn":"Onion Hair Serum","category":"other","price":420,"imageUrl":"images\\\\Onion Hair Serum.jpg"},
  {"id":113,"nameEn":"Vitamin E Hair Serum","category":"other","price":350,"imageUrl":"images\\\\Vitamin E Hair Serum.jpg"},
  {"id":114,"nameEn":"Smooth Finish Serum","category":"other","price":300,"imageUrl":"images\\\\Smooth Finish Serum.jpg"},
  {"id":115,"nameEn":"Damage Repair Serum","category":"other","price":450,"imageUrl":"images\\\\Damage Repair Serum.jpg"},
  {"id":116,"nameEn":"Keratin Smooth Serum Pro","category":"other","price":520,"imageUrl":"images\\\\Keratin Smooth Serum Pro .jpg"},
  {"id":117,"nameEn":"Argan Oil Hair Serum","category":"other","price":480,"imageUrl":"images\\\\Argan Oil Hair Serum.jpg"},
  {"id":118,"nameEn":"Split End Repair Serum","category":"other","price":350,"imageUrl":"images\\\\Split End Repair Serum.jpg"},
  {"id":119,"nameEn":"Ultra Shine Hair Serum","category":"other","price":420,"imageUrl":"images\\\\Ultra Shine Hair Serum.jpg"},
  {"id":120,"nameEn":"Moroccan Argan Serum","category":"other","price":4450,"imageUrl":"images\\\\Moroccan Argan Serum.jpg"},
  {"id":121,"nameEn":"Hyaluronic Hair Serum","category":"other","price":600,"imageUrl":"images\\\\Hyaluronic Hair Serum.jpg"},
  {"id":122,"nameEn":"Frizz Control Serum Pro","category":"other","price":520,"imageUrl":"images\\\\Frizz Control Serum Pro.jpg"},
  {"id":123,"nameEn":"Heat Defense Hair Serum","category":"other","price":480,"imageUrl":"images\\\\Heat Defense Hair Serum.jpg"},
  {"id":124,"nameEn":"Hair Gel Strong Hold","category":"other","price":180,"imageUrl":"images\\\\hair color\\\\Hair Gel Strong Hold.jpg"},
  {"id":125,"nameEn":"Hair Wax Matte Finish","category":"other","price":220,"imageUrl":"images\\\\hair color\\\\Hair Wax Matte Finish.jpg"},
  {"id":126,"nameEn":"Hair Spray","category":"other","price":350,"imageUrl":"images\\\\soap\\\\Hair Spray.jpg"},
  {"id":127,"nameEn":"Heat Protection Spray","category":"other","price":400,"imageUrl":"images\\\\soap\\\\Heat Protection Spray.jpg"},
  {"id":128,"nameEn":"Hair Cream Styling","category":"other","price":220,"imageUrl":"images\\\\soap\\\\Hair Cream Styling.jpg"},
  {"id":129,"nameEn":"Curl Defining Cream","category":"other","price":400,"imageUrl":"images\\\\soap\\\\Curl Defining Cream.jpg"},
  {"id":130,"nameEn":"Hair Mousse","category":"other","price":350,"imageUrl":"images\\\\soap\\\\Hair Mousse.jpg"},
  {"id":131,"nameEn":"Beard Styling Wax","category":"other","price":280,"imageUrl":"images\\\\soap\\\\Beard Styling Wax.jpg"},
  {"id":132,"nameEn":"Strong Hold Hair Gel Pro","category":"other","price":250,"imageUrl":"images\\\\soap\\\\Strong Hold Hair Gel Pro.jpg"},
  {"id":133,"nameEn":"Matte Finish Hair Clay","category":"other","price":350,"imageUrl":"images\\\\soap\\\\Matte Finish Hair Clay.jpg"},
  {"id":134,"nameEn":"Professional Hair Spray","category":"other","price":450,"imageUrl":"images\\\\soap\\\\Professional Hair Spray.jpg"},
  {"id":135,"nameEn":"Curl Enhancing Gel","category":"other","price":380,"imageUrl":"images\\\\soap\\\\Curl Enhancing Gel.jpg"},
  {"id":136,"nameEn":"Hair Setting Spray Ultra Hold","category":"other","price":500,"imageUrl":"images\\\\soap\\\\Hair Setting Spray Ultra Hold.jpg"},
  {"id":137,"nameEn":"Hair Fiber Powder","category":"other","price":700,"imageUrl":"images\\\\soap\\\\Hair Fiber Powder.jpg"},
  {"id":138,"nameEn":"Sea Salt Hair Spray","category":"other","price":420,"imageUrl":"images\\\\soap\\\\Sea Salt Hair Spray.jpg"},
  {"id":139,"nameEn":"Hair Thickening Spray","category":"other","price":500,"imageUrl":"images\\\\soap\\\\Hair Thickening Spray.jpg"},
  {"id":140,"nameEn":"Curl Activator Cream","category":"other","price":450,"imageUrl":"images\\\\soap\\\\Curl Activator Cream.jpg"},
  {"id":141,"nameEn":"Herbal Hair Color","category":"other","price":180,"imageUrl":"images\\\\hair color\\\\Inuds Valley Herbal Hair Gel Color Singal Pack 600.jpg"},
  {"id":142,"nameEn":"Ammonia-Free Hair Dye","category":"other","price":350,"imageUrl":"images\\\\hair color\\\\Ammonia-Free Hair Dye.jpg"},
  {"id":143,"nameEn":"Henna Powder","category":"other","price":120,"imageUrl":"images\\\\hair color\\\\Henna Powder.jpg"},
  {"id":144,"nameEn":"Root Touch-Up Powder","category":"other","price":300,"imageUrl":"images\\\\hair color\\\\Root Touch-Up Powder.jpg"},
  {"id":145,"nameEn":"Hair Color Shampoo","category":"other","price":280,"imageUrl":"images\\\\hair color\\\\Hair Color Shampoo.jpg"},
  {"id":146,"nameEn":"Permanent Hair Color Cream","category":"other","price":400,"imageUrl":"images\\\\hair color\\\\Permanent Hair Color Cream.jpg"},
  {"id":147,"nameEn":"Instant Hair Color Spray","category":"other","price":350,"imageUrl":"images\\\\Instant Hair Color Spray.jpg"},
  {"id":148,"nameEn":"Beard Color Gel","category":"other","price":250,"imageUrl":"images\\\\Beard Color Gel 250.jpg"},
  {"id":149,"nameEn":"Organic Henna Mix","category":"other","price":180,"imageUrl":"images\\\\Organic Henna Mix.jpg"},
  {"id":150,"nameEn":"Color Protect Shampoo Dye","category":"other","price":300,"imageUrl":"images\\\\Color Protect Shampoo Dye.jpg"},
  {"id":151,"nameEn":"Salon Professional Hair Color Kit","category":"other","price":800,"imageUrl":"images\\\\Salon Professional Hair Color Kit.jpg"},
  {"id":152,"nameEn":"Blonde Highlight Kit","category":"other","price":900,"imageUrl":"images\\\\Blonde Highlight Kit.jpg"},
  {"id":153,"nameEn":"Temporary Hair Chalk","category":"other","price":300,"imageUrl":"images\\\\Temporary Hair Chalk.jpg"},
  {"id":154,"nameEn":"Hair Mascara Color","category":"other","price":250,"imageUrl":"images\\\\Hair Mascara Color.jpg"},
  {"id":155,"nameEn":"Hair Dryer","category":"other","price":1500,"imageUrl":"images\\\\Hair Dryer.jpg"},
  {"id":156,"nameEn":"Hair Straightener","category":"other","price":2000,"imageUrl":"images\\\\Hair Straightener.jpg"},
  {"id":157,"nameEn":"Hair Curler","category":"other","price":1800,"imageUrl":"images\\\\Hair Curler.jpg"},
  {"id":158,"nameEn":"Electric Hair Brush","category":"other","price":3500,"imageUrl":"images\\\\Electric Hair Brush .jpg"},
  {"id":159,"nameEn":"Trimmer (Hair & Beard)","category":"other","price":3200,"imageUrl":"images\\\\Cordless Hair Trimmer.jpg"},
  {"id":160,"nameEn":"Ionic Hair Dryer","category":"other","price":3200,"imageUrl":"images\\\\Ionic Hair Dryer.jpg"},
  {"id":161,"nameEn":"Ceramic Hair Straightener","category":"other","price":2500,"imageUrl":"images\\\\Ceramic Hair Straightener.jpg"},
  {"id":162,"nameEn":"Automatic Hair Curler","category":"other","price":3000,"imageUrl":"images\\\\Automatic Hair Curler.jpg"},
  {"id":163,"nameEn":"Multi-Styler Kit","category":"other","price":4500,"imageUrl":"images\\\\Multi-Styler Kit.jpg"},
  {"id":164,"nameEn":"Cordless Hair Trimmer","category":"other","price":2800,"imageUrl":"images\\\\Cordless Hair Trimmer.jpg"},
  {"id":165,"nameEn":"Professional Hair Dryer 2200W","category":"other","price":3500,"imageUrl":"images\\\\Professional Hair Dryer 2200W.jpg"},
  {"id":166,"nameEn":"Titanium Hair Straightener","category":"other","price":4000,"imageUrl":"images\\\\Titanium Hair Straightener.jpg"},
  {"id":167,"nameEn":"Curling Wand Professional","category":"other","price":3200,"imageUrl":"images\\\\Curling Wand Professional.jpg"},
  {"id":168,"nameEn":"Beard Grooming Kit","category":"other","price":2500,"imageUrl":"images\\\\Beard Grooming Kit.jpg"},
  {"id":169,"nameEn":"Hair Comb Set","category":"other","price":900,"imageUrl":"images\\\\Hair Comb Set.jpg"},
  {"id":170,"nameEn":"Hair Brush Wooden","category":"other","price":250,"imageUrl":"images\\\\Hair Brush Wooden.jpg"},
  {"id":171,"nameEn":"Hair Clips Pack","category":"other","price":100,"imageUrl":"images\\\\Hair Clips Pack.jpg"},
  {"id":172,"nameEn":"Hair Bands Elastic","category":"other","price":90,"imageUrl":"images\\\\Hair Bands Elastic.jpg"},
  {"id":173,"nameEn":"Hair Rollers Set","category":"other","price":300,"imageUrl":"images\\\\Hair Rollers Set.jpg"},
  {"id":174,"nameEn":"Satin Hair Scrunchies","category":"other","price":180,"imageUrl":"images\\\\Satin Hair Scrunchies.jpg"},
  {"id":175,"nameEn":"Anti-Static Hair Brush","category":"other","price":350,"imageUrl":"images\\\\Anti-Static Hair Brush.jpg"},
  {"id":176,"nameEn":"Detangling Hair Brush","category":"other","price":300,"imageUrl":"images\\\\Detangling Hair Brush.jpg"},
  {"id":177,"nameEn":"Sectioning Hair Clips","category":"other","price":200,"imageUrl":"images\\\\Sectioning Hair Clips.jpg"}
]';
  $data = json_decode($json, true);
  if (!is_array($data)) {
    return [];
  }
  $result = [];
  $usedIds = [];
  foreach ($data as $item) {
    $id = $item['id'];
    if (in_array($id, $usedIds)) {
      $id = 2000 + count($result);
    }
    $usedIds[] = $id;
    $category = $item['category'];
    if ($category === 'oill') {
      $category = 'oil';
    }
    if (!in_array($category, ['shampoo', 'conditioner', 'oil'])) {
      $category = 'other';
    }
    $imagePath = $item['imageUrl'] ?? '';
    if ($imagePath && !str_starts_with($imagePath, 'http') && !str_starts_with($imagePath, 'data:')) {
      $imagePath = normalizeImagePath($imagePath);
    }
    $result[] = [
      'id' => $id,
      'name' => $item['nameEn'],
      'kn_name' => $item['nameEn'],
      'price' => (float) $item['price'],
      'category' => $category,
      'variants' => [['label' => '1 unit', 'price' => (float) $item['price']]],
      'emoji' => '💇',
      'description_en' => 'Premium hair care product',
      'imageUrl' => $imagePath,
      'image' => $imagePath
    ];
  }
  $fallback = $result;
  file_put_contents($jsonPath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  return $fallback;
}
/**
 * Normalize image path: convert Windows absolute path to web-relative path
 *
 * @param string $path The raw image path
 * @return string      Normalized URL-friendly path
 */
if (!function_exists('normalizeImagePath')) {
  function normalizeImagePath(string $path): string
  {
    if (empty($path)) {
      return '';
    }
    // Replace Windows backslashes with forward slashes
    $path = str_replace('\\', '/', $path);
    // Remove the base drive/folder if present (assumes project root is one level up)
    if (strpos($path, 'D:/KSR Guru JI/') !== false) {
      $path = str_replace('D:/KSR Guru JI/', '', $path);
    } elseif (strpos($path, 'D:\\KSR Guru JI\\') !== false) {
      $path = str_replace('D:\\KSR Guru JI\\', '', $path);
    }
    // Ensure it's a web path (relative to the site root)
    if (!str_starts_with($path, 'http') && !str_starts_with($path, 'data:')) {
      if (!str_starts_with($path, '../')) {
        $path = '../' . $path;
      }
    }
    return $path;
  }
}
// Get products (hair-care category) - if empty, use fallback
$products = getHairCareProducts();
if (empty($products)) {
  $products = getFallbackHairCareProducts();
}
// Pre-fill variants and normalize image URL
foreach ($products as &$p) {
  $variants = is_array($p['variants'] ?? null) ? $p['variants'] : json_decode($p['variants'] ?? '[]', true);
  if (empty($variants)) {
    $variants = [
      ['label' => '1 unit', 'price' => $p['price']]
    ];
  }
  $p['variants'] = $variants;
  // Normalize image URL
  if (!empty($p['image_url'])) {
    $p['imageUrl'] = normalizeImagePath($p['image_url']);
  } elseif (!empty($p['imageUrl'])) {
    $p['imageUrl'] = normalizeImagePath($p['imageUrl']);
  } else {
    $p['imageUrl'] = '';
  }
}
unset($p);
// Get cart count and total
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
  <title>ĀyurHair | Natural Hair Care · KSR Guru</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <style>
    /* ===== WARM MINIMALISM DESIGN - HAIR CARE EDITION ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      background: #fdfaf9;
      padding: 24px 20px;
      min-height: 100vh;
    }

    #app {
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
      background: white;
      border-radius: 30px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.04);
      padding: 28px 32px 40px;
    }

    .category-header {
      text-align: center;
      margin-bottom: 2rem;
      padding: 40px 20px;
      background: linear-gradient(135deg, #fceeea 0%, #f7dfdb 100%);
      border-radius: 24px;
      border: 1px solid #f2cec7;
      position: relative;
      overflow: hidden;
    }

    .category-header::before {
      content: '✨';
      position: absolute;
      font-size: 8rem;
      opacity: 0.1;
      left: 5%;
      top: -20px;
    }

    .category-header h1 {
      font-size: 3rem;
      font-weight: 800;
      color: #733c41;
      margin-bottom: 12px;
      letter-spacing: -0.02em;
    }

    .category-header p {
      font-size: 1.15rem;
      color: #a15d65;
      font-weight: 500;
    }

    .search-section {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 32px;
    }

    .search-box {
      background: #fdfaf9;
      border-radius: 70px;
      padding: 12px 28px;
      display: flex;
      align-items: center;
      border: 1px solid #f2cec7;
      gap: 12px;
      margin-bottom: 28px;
      box-shadow: 0 8px 20px rgba(182, 107, 116, 0.08);
      width: 100%;
      max-width: 650px;
      transition: all 0.3s;
    }

    .search-box:focus-within {
      border-color: #c27a7e;
      box-shadow: 0 8px 24px rgba(194, 122, 126, 0.15);
    }

    .search-box i {
      font-size: 1.3rem;
      color: #c27a7e;
    }

    .search-box input {
      flex: 1;
      border: none;
      padding: 6px 0;
      background: transparent;
      font-size: 1.1rem;
      outline: none;
      color: #4a292d;
      font-weight: 500;
    }

    .search-box input::placeholder {
      color: #c49ba0;
    }

    .filter-pills {
      display: flex;
      gap: 12px;
      overflow-x: auto;
      padding-bottom: 12px;
      scrollbar-width: none;
      justify-content: center;
      width: 100%;
    }

    .filter-pills::-webkit-scrollbar {
      display: none;
    }

    .pill {
      background: #fdfaf9;
      padding: 12px 28px;
      border-radius: 60px;
      font-weight: 600;
      font-size: 0.95rem;
      color: #8e5459;
      cursor: pointer;
      white-space: nowrap;
      border: 1px solid #f2cec7;
      transition: all 0.3s ease;
    }

    .pill:hover {
      background: #fae6e3;
      transform: translateY(-2px);
    }

    .pill.active {
      background: #c27a7e;
      color: white;
      border-color: #c27a7e;
      box-shadow: 0 8px 16px rgba(194, 122, 126, 0.3);
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 32px;
      margin: 32px 0 48px;
    }

    .product-card {
      background: white;
      border-radius: 28px;
      padding: 24px 20px;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      border: 1px solid #f7eeeb;
      box-shadow: 0 8px 24px rgba(182, 107, 116, 0.06);
      display: flex;
      flex-direction: column;
      position: relative;
      align-items: center;
      text-align: center;
    }

    .product-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(182, 107, 116, 0.12);
      border-color: #f2cec7;
    }

    .product-image {
      width: 130px;
      height: 130px;
      border-radius: 32px;
      background: #fffcfb;
      border: 2px solid #fceef0;
      margin: 0 auto 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      padding: 4px;
    }

    .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 26px;
    }

    .product-name {
      font-weight: 800;
      font-size: 1.25rem;
      color: #4a292d;
      line-height: 1.3;
      margin-bottom: 4px;
    }

    .product-name-kn {
      font-size: 0.9rem;
      color: #a15d65;
      margin: 0 0 14px;
      font-weight: 600;
    }

    .product-description {
      font-size: 0.85rem;
      background: #fffcfb;
      padding: 10px 14px;
      border-radius: 12px;
      color: #8e5459;
      margin: 8px 0 16px;
      line-height: 1.5;
      border-left: 4px solid #da9599;
      width: 100%;
      text-align: left;
    }

    .weight-select,
    .variant-text {
      font-size: 0.85rem;
      font-weight: 700;
      color: #8e5459 !important;
      background: #fdfaf9 !important;
      border: 1px solid #f2cec7 !important;
      border-radius: 50px !important;
      padding: 10px 16px !important;
      margin: 4px 0 16px !important;
      display: inline-block;
      width: 100%;
      max-width: 180px;
      text-align: center;
      outline: none;
    }

    .price-row {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-top: auto;
      width: 100%;
    }

    .price,
    .dynamic-price {
      font-weight: 800;
      font-size: 1.6rem;
      color: #c27a7e;
      letter-spacing: -0.02em;
      margin-bottom: 16px;
    }

    .add-btn {
      background: #c27a7e;
      border: none;
      border-radius: 60px;
      padding: 14px;
      font-weight: 700;
      font-size: 1rem;
      color: white;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      box-shadow: 0 6px 16px rgba(194, 122, 126, 0.3);
      outline: none;
    }

    .add-btn::after {
      content: ' Add to Cart';
      font-weight: 700;
    }

    .add-btn:hover {
      background: #a65e63;
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(194, 122, 126, 0.4);
    }

    .add-btn:active {
      transform: translateY(0);
      box-shadow: 0 4px 10px rgba(194, 122, 126, 0.3);
    }

    /* Cart Sidebar shifted to global cart-drawer.php */
    .cart-sidebar {
      display: none !important;
    }

    .nav-links {
      display: none !important;
    }

    @media (max-width: 768px) {
      #app {
        padding: 20px 16px;
        border-radius: 20px;
      }

      .category-header h1 {
        font-size: 2.2rem;
      }

      .category-header {
        padding: 30px 16px;
      }

      .search-box {
        padding: 10px 20px;
        border-radius: 20px;
      }

      .filter-pills {
        justify-content: flex-start;
        padding-left: 4px;
        padding-right: 4px;
      }

      .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 16px;
        margin-top: 16px;
      }

      .product-card {
        padding: 16px 12px;
        border-radius: 20px;
      }

      .product-image {
        width: 100px;
        height: 100px;
      }

      .product-name {
        font-size: 1.1rem;
      }

      .add-btn {
        padding: 12px;
        font-size: 0.95rem;
      }

      .price,
      .dynamic-price {
        font-size: 1.4rem;
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
    <div class="category-header">
      <h1>✨ ĀyurHair | Natural Care</h1>
      <p>Nourishing hair care products for everyday glow</p>
    </div>
    <div class="search-section">
      <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
      </div>
      <div class="filter-pills" id="filterPills">
        <div class="pill active" data-category="all">🌿 All / ಎಲ್ಲ</div>
        <div class="pill" data-category="shampoo">🧴 Shampoo</div>
        <div class="pill" data-category="conditioner">💧 Conditioner</div>
        <div class="pill" data-category="oil">🫒 Hair Oil</div>
      </div>
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
                          $subcat = $p['category'] ?? 'shampoo';
                          $out[] = [
                            'id' => $p['id'],
                            'nameEn' => $p['name'],
                            'nameKn' => $p['kn_name'] ?? $p['name'],
                            'category' => $subcat,
                            'variants' => $p['variants'],
                            'emoji' => $p['emoji'] ?? '💇',
                            'description' => $p['description_en'] ?? 'Premium hair care product',
                            'imageUrl' => $p['imageUrl'] ?? ''
                          ];
                        }
                        echo json_encode($out);
                        ?>;
      let cart = [];
      const basePath = 'hair-care.php';
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
          <div class="cart-item-image">💇</div>
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
          grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No hair care products found ✨</div>';
          return;
        }
        grid.innerHTML = filtered.map(p => {
          const defaultVariant = 0;
          const defaultPrice = p.variants[defaultVariant]?.price || 0;
          // Image rendering with fallback
          const imageHtml = p.imageUrl ? `<img src="${p.imageUrl}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='${p.emoji}'">` : p.emoji;
          return `<div class="product-card" data-base="${p.id}">
          <div class="product-image">${imageHtml}</div>
          <div class="product-name">${p.nameEn}</div>
          ${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}
          <div class="product-description">${p.description}</div>
          ${p.variants && p.variants.length > 1 
            ? `<select class="weight-select" data-base="${p.id}">
                ${p.variants.map((v, i) => `<option value="${i}" ${i===defaultVariant?'selected':''}>${v.label} - ₹${v.price}</option>`).join('')}
               </select>`
            : `<div class="variant-text" style="font-size:0.85rem; font-weight:600; color:#4f3e2e; text-align:center; padding:8px 12px; margin-bottom:12px; width:100%; max-width:200px; background:#FEF9F4; border-radius:50px; border:1px solid #EADCCF;">${p.variants && p.variants[0] ? p.variants[0].label : '1 unit'}</div>`
          }
          <div class="price-row">
            <span class="dynamic-price">₹${defaultPrice}</span>
            <button class="add-btn" data-pid="${p.id}" onclick="window.addToCart(${p.id})"><i class="fas fa-cart-plus"></i></button>
          </div>
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
          alert('Cart is empty');
        }
      });
      window.addToCart = addToCart;
      window.changeQty = updateCartQty;
      window.removeItem = removeFromCart;
      loadCart();
      renderProducts();
    })();
  </script>

  <?php include __DIR__ . '/../includes/user-footer.php'; ?>
  <script src="sorting.js"></script>
</body>

</html>