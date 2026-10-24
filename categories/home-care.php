<?php
// ============================================================
// home-care.php - Dynamic Home Care product page (with images)
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
      $res = getHomeCareProducts($search);
      if (empty($res)) {
        $res = getFallbackHomeCareProducts();
        if ($search !== '') {
          $res = array_filter($res, function ($p) use ($search) {
            return stripos($p['name'], $search) !== false || stripos($p['kn_name'] ?? '', $search) !== false;
          });
          $res = array_values($res);
        }
      }
      echo json_encode($res);
      exit;
    case 'cart':
      echo json_encode($_SESSION['cart']);
      exit;
    case 'add_cart':
      $id = (int) ($_POST['id'] ?? 0);
      if ($id) {
        $product = getProductById($id);
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
              'icon' => $product['icon']
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
// Helper: Normalize image path
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
// Helper function to fetch home care products using PDO with Dynamic Column Checking
function getHomeCareProducts(string $search = ''): array
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
    $whereCategory = $hasSlug ? "(c.slug = 'home-care' OR c.id = 'home-care' OR c.name LIKE '%home%')" : "(c.name LIKE '%home%' OR c.name LIKE '%house%')";
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
      $row['description_en'] = $row['description_en'] ?? 'Quality home care product';
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
// ============================================================
// FALLBACK: If no products from DB, load static JSON data
// ============================================================
function getFallbackHomeCareProducts()
{
  static $fallback = null;
  if ($fallback !== null) {
    return $fallback;
  }
  $jsonPath = __DIR__ . '/home-care-fallback.json';
  if (file_exists($jsonPath)) {
    $fallback = json_decode(file_get_contents($jsonPath), true);
    if (is_array($fallback)) {
      return $fallback;
    }
  }
  $json = '[
  {"id":1,"name":"White Phenyl Regular","nameKn":"ಬಿಳಿ ಫಿನೈಲ್ (ನಿಯಮಿತ)","category":"cleaning","price":320,"imageUrl":"images\\\\White Phenyl Regular 319.jpg","description":"Classic floor disinfectant, fresh scent."},
  {"id":2,"name":"White Phenyl Premium","nameKn":"ಬಿಳಿ ಫಿನೈಲ್ (ಪ್ರೀಮಿಯಂ)","category":"cleaning","price":800,"imageUrl":"images\\\\White Phenyl Premium 800.jpg","description":"Stronger formula, long-lasting fragrance."},
  {"id":3,"name":"White Phenyl Economy","nameKn":"ಬಿಳಿ ಫಿನೈಲ್ (ಎಕಾನಮಿ)","category":"cleaning","price":300,"imageUrl":"images\\\\White Phenyl Economy 300.jpg","description":"Budget-friendly, daily use."},
  {"id":4,"name":"Black Phenyl Standard","nameKn":"ಕಪ್ಪು ಫಿನೈಲ್ (ಸ್ಟ್ಯಾಂಡರ್ಡ್)","category":"cleaning","price":250,"imageUrl":"images\\\\Black Phenyl Standard 240.jpg","description":"Strong germicidal action."},
  {"id":5,"name":"Black Phenyl Heavy Duty","nameKn":"ಕಪ್ಪು ಫಿನೈಲ್ (ಹೆವಿ ಡ್ಯೂಟಿ)","category":"cleaning","price":450,"imageUrl":"images\\\\Black Phenyl Heavy Duty 450.jpg","description":"Industrial strength cleans."},
  {"id":6,"name":"Floor Cleaner Lemon","nameKn":"ನೆಲ ಕ್ಲೀನರ್ (ನಿಂಬೆ)","category":"cleaning","price":220,"imageUrl":"images\\\\Floor Cleaner Lemon.jpg","description":"Citrus freshness, removes stains."},
  {"id":7,"name":"Floor Cleaner Jasmine","nameKn":"ನೆಲ ಕ್ಲೀನರ್ (ಮಲ್ಲಿಗೆ)","category":"cleaning","price":50,"imageUrl":"images\\\\Floor Cleaner Jasmine 50.jpg","description":"Floral fragrance, gentle on floors."},
  {"id":8,"name":"Floor Cleaner Lavender","nameKn":"ನೆಲ ಕ್ಲೀನರ್ (ಲ್ಯಾವೆಂಡರ್)","category":"cleaning","price":200,"imageUrl":"images\\\\Floor Cleaner Lavender 200.jpg","description":"Calming scent, effective cleans."},
  {"id":9,"name":"Floor Cleaner Rose","nameKn":"ನೆಲ ಕ್ಲೀನರ್ (ಗುಲಾಬಿ)","category":"cleaning","price":850,"imageUrl":"images\\\\Floor Cleaner Rose 850.jpg","description":"Rose aroma, shines tiles."},
  {"id":10,"name":"Floor Cleaner Neem","nameKn":"ನೆಲ ಕ್ಲೀನರ್ (ಬೇವು)","category":"cleaning","price":130,"imageUrl":"images\\\\Floor Cleaner Neem 130.jpg","description":"Natural antibacterial properties."},
  {"id":12,"name":"Floor Cleaner Organic","nameKn":"ಸಾವಯವ ನೆಲ ಕ್ಲೀನರ್","category":"cleaning","price":3900,"imageUrl":"images\\\\Floor Cleaner Organic 3900.jpg","description":"Eco‑friendly, biodegradable."},
  {"id":13,"name":"Floor Cleaner Premium 2 L","nameKn":"ಪ್ರೀಮಿಯಂ ನೆಲ ಕ್ಲೀನರ್ 2L","category":"cleaning","price":360,"imageUrl":"images\\\\Floor Cleaner Premium.jpg","description":"Large pack, superior shine."},
  {"id":14,"name":"Floor Cleaner Economy 500 ml","nameKn":"ಎಕಾನಮಿ ನೆಲ ಕ್ಲೀನರ್ 500ml","category":"cleaning","price":70,"imageUrl":"images\\\\Floor Cleaner Economy 500 ml.jpg","description":"Small bottle, perfect for trials."},
  {"id":15,"name":"Floor Cleaner Bulk 5 L","nameKn":"ಬಲ್ಕ್ ನೆಲ ಕ್ಲೀನರ್ 5L","category":"cleaning","price":450,"imageUrl":"images\\\\Floor Cleaner Bulk 5 L.jpg","description":"Commercial size, cost effective."},
  {"id":16,"name":"Pine Oil Floor Cleaner","nameKn":"ಪೈನ್ ಆಯಿಲ್ ನೆಲ ಕ್ಲೀನರ್","category":"cleaning","price":100,"imageUrl":"images\\\\Pine Oil Floor Cleaner 100.jpg","description":"Natural pine fragrance, degreases."},
  {"id":17,"name":"Disinfectant Floor Cleaner","nameKn":"ಸೋಂಕುನಿವಾರಕ ನೆಲ ಕ್ಲೀನರ್","category":"cleaning","price":140,"imageUrl":"images\\\\Disinfectant Floor Cleaner 130.jpg","description":"Kills 99.9% germs."},
  {"id":18,"name":"Multi Surface Cleaner","nameKn":"ಬಹು ಮೇಲ್ಮೈ ಕ್ಲೀನರ್","category":"cleaning","price":140,"imageUrl":"images\\\\Multi Surface Cleaner 130.jpg","description":"Safe for glass, wood, tiles."},
  {"id":19,"name":"Marble Floor Cleaner","nameKn":"ಮಾರ್ಬಲ್ ನೆಲ ಕ್ಲೀನರ್","category":"cleaning","price":770,"imageUrl":"images\\\\Marble Floor Cleaner 770.jpg","description":"Special formula for marble."},
  {"id":20,"name":"Tile Floor Cleaner","nameKn":"ಟೈಲ್ ನೆಲ ಕ್ಲೀನರ್","category":"cleaning","price":220,"imageUrl":"images\\\\Tile Floor Cleaner 2120.jpg","description":"Removes grout stains easily."},
  {"id":21,"name":"Anti Bacterial Floor Cleaner","nameKn":"ಆಂಟಿ ಬ್ಯಾಕ್ಟೀರಿಯಲ್ ನೆಲ ಕ್ಲೀನರ್","category":"cleaning","price":180,"imageUrl":"images\\\\Anti Bacterial Floor Cleaner 170.jpg","description":"Protects against bacteria."},
  {"id":22,"name":"Bio Enzyme Floor Cleaner","nameKn":"ಜೈವಿಕ ಕಿಣ್ವ ನೆಲ ಕ್ಲೀನರ್","category":"cleaning","price":350,"imageUrl":"images\\\\Bio Enzyme Floor Cleaner 350.jpg","description":"Natural enzymatic cleaning."},
  {"id":23,"name":"Kids Safe Floor Cleaner","nameKn":"ಮಕ್ಕಳ ಸುರಕ್ಷಿತ ನೆಲ ಕ್ಲೀನರ್","category":"cleaning","price":390,"imageUrl":"images\\\\Kids Safe Floor Cleaner.jpg","description":"Non‑toxic, child‑friendly."},
  {"id":24,"name":"Pet Friendly Floor Cleaner","nameKn":"ಸಾಕುಪ್ರಾಣಿ ಸ್ನೇಹಿ ನೆಲ ಕ್ಲೀನರ್","category":"cleaning","price":460,"imageUrl":"images\\\\Pet Friendly Floor Cleaner 460.jpg","description":"Safe for paws, removes pet odors."},
  {"id":25,"name":"Floor Cleaner Combo Pack","nameKn":"ನೆಲ ಕ್ಲೀನರ್ ಕಾಂಬೊ","category":"cleaning","price":300,"imageUrl":"images\\\\Floor Cleaner Combo Pack 200.jpg","description":"2 bottles of 1L each."},
  {"id":26,"name":"Dishwash Liquid Lemon","nameKn":"ಪಾತ್ರೆ ತೊಳೆಯುವ ದ್ರವ (ನಿಂಬೆ)","category":"cleaning","price":100,"imageUrl":"images\\\\Dishwash Liquid Lemon 90.jpg","description":"Cuts grease, fresh lemon."},
  {"id":27,"name":"Dishwash Liquid Green","nameKn":"ಹಸಿರು ಪಾತ್ರೆ ದ್ರವ","category":"cleaning","price":580,"imageUrl":"images\\\\Dishwash Liquid Green.jpg","description":"Eco‑friendly, mild on hands."},
  {"id":28,"name":"Dishwash Liquid Orange","nameKn":"ಪಾತ್ರೆ ದ್ರವ (ಕಿತ್ತಳೆ)","category":"cleaning","price":190,"imageUrl":"images\\\\Dishwash Liquid Orange 190.jpg","description":"Citrus power, removes oil."},
  {"id":29,"name":"Dishwash Gel Premium","nameKn":"ಪ್ರೀಮಿಯಂ ಜೆಲ್","category":"cleaning","price":310,"imageUrl":"images\\\\Dishwash Gel Premium 310.jpg","description":"Thick gel, 2x cleaning action."},
  {"id":30,"name":"Dishwash Bar Regular","nameKn":"ಪಾತ್ರೆ ಸಾಬೂನು","category":"cleaning","price":10,"imageUrl":"images\\\\Dishwash Bar Regular 10.jpg","description":"Traditional bar, economical."},
  {"id":31,"name":"Dishwash Bar Pack (4 pcs)","nameKn":"ಸಾಬೂನು ಪ್ಯಾಕ್ (4)","category":"cleaning","price":80,"imageUrl":"images\\\\Dishwash Bar Pack (4 pcs) 80.jpg","description":"Family pack, saves money."},
  {"id":32,"name":"Dishwash Powder","nameKn":"ಪಾತ್ರೆ ಪುಡಿ","category":"cleaning","price":320,"imageUrl":"images\\\\Dishwash Powder 300.jpg","description":"Powder formula, quick dissolve."},
  {"id":33,"name":"Kitchen Cleaner Spray","nameKn":"ಅಡುಗೆಕೋಣೆ ಸ್ಪ್ರೇ","category":"cleaning","price":200,"imageUrl":"images\\\\Kitchen Cleaner Spray 200.jpg","description":"Cuts through grease on counters."},
  {"id":34,"name":"Kitchen Degreaser","nameKn":"ಡಿಗ್ರೀಸರ್","category":"cleaning","price":200,"imageUrl":"images\\\\Kitchen Degreaser 200.jpg","description":"Powerful for exhaust and stoves."},
  {"id":35,"name":"Gas Stove Cleaner","nameKn":"ಗ್ಯಾಸ್ ಸ್ಟೌವ್ ಕ್ಲೀನರ್","category":"cleaning","price":180,"imageUrl":"images\\\\Gas Stove Cleaner 1450.jpg","description":"Removes burnt food stains."},
  {"id":36,"name":"Chimney Cleaner Liquid","nameKn":"ಚಿಮ್ನಿ ಕ್ಲೀನರ್","category":"cleaning","price":590,"imageUrl":"images\\\\Chimney Cleaner Liquid 590.jpg","description":"Liquid spray for chimneys."},
  {"id":37,"name":"Oven Cleaner Spray","nameKn":"ಒವನ್ ಕ್ಲೀನರ್ ಸ್ಪ್ರೇ","category":"cleaning","price":300,"imageUrl":"images\\\\Oven Cleaner Spray 300.jpg","description":"Safe for ovens, no fumes."},
  {"id":38,"name":"Microwave Cleaner","nameKn":"ಮೈಕ್ರೋವೇವ್ ಕ್ಲೀನರ್","category":"cleaning","price":500,"imageUrl":"images\\\\Microwave Cleaner 500.jpg","description":"Removes food residue easily."},
  {"id":39,"name":"Dishwash Combo Pack","nameKn":"ಕಾಂಬೊ ಪ್ಯಾಕ್","category":"cleaning","price":500,"imageUrl":"images\\\\Dishwash Combo Pack 500.jpg","description":"Liquid + bar + sponge."},
  {"id":40,"name":"Dishwash Bulk Pack","nameKn":"ಬಲ್ಕ್ ಪ್ಯಾಕ್","category":"cleaning","price":50,"imageUrl":"images\\\\Dishwash Bulk Pack 50.jpg","description":"5L liquid, huge savings."},
  {"id":41,"name":"Herbal Dishwash Liquid","nameKn":"ಹರ್ಬಲ್ ಪಾತ್ರೆ ದ್ರವ","category":"cleaning","price":600,"imageUrl":"images\\\\Herbal Dishwash Liquid 600.jpg","description":"Natural ingredients, gentle."},
  {"id":42,"name":"Organic Dishwash Liquid","nameKn":"ಸಾವಯವ ಪಾತ್ರೆ ದ್ರವ","category":"cleaning","price":1460,"imageUrl":"images\\\\Organic Dishwash Liquid 1400.jpg","description":"Certified organic, zero chemicals."},
  {"id":43,"name":"Anti Bacterial Dishwash","nameKn":"ಆಂಟಿ ಬ್ಯಾಕ್ಟೀರಿಯಲ್","category":"cleaning","price":360,"imageUrl":"images\\\\Anti Bacterial Dishwash 360.jpg","description":"Kills germs on utensils."},
  {"id":44,"name":"Dishwash Tablets Pack","nameKn":"ಟ್ಯಾಬ್ಲೆಟ್ ಪ್ಯಾಕ್","category":"cleaning","price":500,"imageUrl":"images\\\\Dishwash Tablets Pack 500.jpg","description":"For dishwasher machines, 30 tablets."},
  {"id":45,"name":"Premium Dishwash Kit","nameKn":"ಪ್ರೀಮಿಯಂ ಕಿಟ್","category":"cleaning","price":1300,"imageUrl":"images\\\\Premium Dishwash Kit 1290.jpg","description":"Complete set with stand."},
  {"id":46,"name":"Toilet Cleaner Liquid","nameKn":"ಶೌಚಾಲಯ ಕ್ಲೀನರ್","category":"cleaning","price":50,"imageUrl":"images\\\\Toilet Cleaner Liquid 50.jpg","description":"Disinfects and deodorizes."},
  {"id":47,"name":"Toilet Cleaner Thick","nameKn":"ದಪ್ಪ ಟಾಯ್ಲೆಟ್ ಕ್ಲೀನರ್","category":"cleaning","price":110,"imageUrl":"images\\\\Toilet Cleaner Thick 110.jpg","description":"Gel formula, clings to surfaces."},
  {"id":48,"name":"Bathroom Cleaner Spray","nameKn":"ಸ್ನಾನಗೃಹ ಕ್ಲೀನರ್","category":"cleaning","price":460,"imageUrl":"images\\\\Bathroom Cleaner Spray 460.jpg","description":"Removes soap scum & hard water."},
  {"id":49,"name":"Tile Cleaner Liquid","nameKn":"ಟೈಲ್ ಕ್ಲೀನರ್","category":"cleaning","price":160,"imageUrl":"images\\\\Tile Cleaner Liquid 150.jpg","description":"Brightens grout lines."},
  {"id":50,"name":"Acid Toilet Cleaner","nameKn":"ಆಸಿಡ್ ಟಾಯ್ಲೆಟ್ ಕ್ಲೀನರ್","category":"cleaning","price":150,"imageUrl":"images\\\\Acid Toilet Cleaner 150.jpg","description":"Removes tough limescale."},
  {"id":51,"name":"Gel Toilet Cleaner","nameKn":"ಜೆಲ್ ಟಾಯ್ಲೆಟ್ ಕ್ಲೀನರ್","category":"cleaning","price":360,"imageUrl":"images\\\\Gel Toilet Cleaner 360.jpg","description":"Under‑rim gel, fresh scent."},
  {"id":52,"name":"Bathroom Cleaner Combo","nameKn":"ಸ್ನಾನಗೃಹ ಕಾಂಬೊ","category":"cleaning","price":300,"imageUrl":"images\\\\Bathroom Cleaner Combo 300.jpg","description":"Spray + gel + wipes."},
  {"id":53,"name":"Bathroom Cleaner Bulk","nameKn":"ಸ್ನಾನಗೃಹ ಬಲ್ಕ್","category":"cleaning","price":1000,"imageUrl":"images\\\\Bathroom Cleaner Bulk 1000.jpg","description":"5L, commercial use."},
  {"id":54,"name":"Herbal Bathroom Cleaner","nameKn":"ಹರ್ಬಲ್ ಸ್ನಾನಗೃಹ ಕ್ಲೀನರ್","category":"cleaning","price":550,"imageUrl":"images\\\\Herbal Bathroom Cleaner 550.jpg","description":"Plant‑based, safe for septic."},
  {"id":55,"name":"Organic Bathroom Cleaner","nameKn":"ಸಾವಯವ ಸ್ನಾನಗೃಹ ಕ್ಲೀನರ್","category":"cleaning","price":680,"imageUrl":"images\\\\Organic Bathroom Cleaner 680.jpg","description":"Biodegradable, eco‑label."},
  {"id":56,"name":"Anti Bacterial Bathroom Cleaner","nameKn":"ಆಂಟಿ ಬ್ಯಾಕ್ಟೀರಿಯಲ್","category":"cleaning","price":360,"imageUrl":"images\\\\Anti Bacterial Bathroom Cleaner 350.jpg","description":"Kills germs in bathrooms."},
  {"id":57,"name":"Glass Cleaner Spray","nameKn":"ಗಾಜಿನ ಕ್ಲೀನರ್","category":"cleaning","price":50,"imageUrl":"images\\\\Glass Cleaner Spray 40.jpg","description":"Streak‑free, ammonia based."},
  {"id":58,"name":"Mirror Cleaner Spray","nameKn":"ಕನ್ನಡಿ ಕ್ಲೀನರ್","category":"cleaning","price":700,"imageUrl":"images\\\\Mirror Cleaner Spray 700.jpg","description":"Anti‑fog formula."},
  {"id":59,"name":"Floor Disinfectant Liquid","nameKn":"ನೆಲದ ಸೋಂಕುನಿವಾರಕ","category":"cleaning","price":250,"imageUrl":"images\\\\Floor Disinfectant Liquid 250.jpg","description":"Kills 99.9% germs on floors."},
  {"id":60,"name":"Bathroom Freshener Liquid","nameKn":"ಸ್ನಾನಗೃಹ ತಾಜಾಕಾರಕ","category":"air","price":360,"imageUrl":"images\\\\air\\\\Bathroom Freshener Liquid360.jpg","description":"Long‑lasting freshness."},
  {"id":61,"name":"Urinal Cleaner Block","nameKn":"ಮೂತ್ರ ವಿಸರ್ಜನಾ ಕ್ಲೀನರ್","category":"cleaning","price":720,"imageUrl":"images\\\\Urinal Cleaner Block 720.jpg","description":"Hanging block for urinals."},
  {"id":62,"name":"Toilet Cleaning Kit","nameKn":"ಟಾಯ್ಲೆಟ್ ಕ್ಲೀನಿಂಗ್ ಕಿಟ್","category":"cleaning","price":1680,"imageUrl":"images\\\\Toilet Cleaning Kit1680.jpg","description":"Brush + holder + cleaner."},
  {"id":63,"name":"Premium Bathroom Kit","nameKn":"ಪ್ರೀಮಿಯಂ ಬಾತ್ರೂಮ್ ಕಿಟ್","category":"cleaning","price":1900,"imageUrl":"images\\\\Premium Bathroom Kit 1900.jpg","description":"5 products + caddy."},
  {"id":64,"name":"Hard Water Stain Remover","nameKn":"ಗಟ್ಟಿ ನೀರಿನ ಕಲೆ ತೆಗೆಯುವ ದ್ರವ","category":"cleaning","price":350,"imageUrl":"images\\\\Hard Water Stain Remover 350.jpg","description":"Removes limescale from taps."},
  {"id":65,"name":"Mold Remover Spray","nameKn":"ಮೋಲ್ಡ್ ರಿಮೂವರ್","category":"cleaning","price":410,"imageUrl":"images\\\\Mold Remover Spray 410.jpg","description":"Kills bathroom mold."},
  {"id":66,"name":"Bathroom Foam Cleaner","nameKn":"ಫೋಮ್ ಕ್ಲೀನರ್","category":"cleaning","price":350,"imageUrl":"images\\\\Bathroom Foam Cleaner 350.jpg","description":"Foam cling, no drip."},
  {"id":67,"name":"Tile Grout Cleaner","nameKn":"ಟೈಲ್ ಗ್ರೌಟ್ ಕ್ಲೀನರ್","category":"cleaning","price":460,"imageUrl":"images\\\\Tile Grout Cleaner 460.jpg","description":"Brush‑on gel for grout."},
  {"id":68,"name":"Anti Scale Cleaner","nameKn":"ಆಂಟಿ ಸ್ಕೇಲ್ ಕ್ಲೀನರ್","category":"cleaning","price":450,"imageUrl":"images\\\\Anti Scale Cleaner 450.jpg","description":"Prevents scale buildup."},
  {"id":69,"name":"Bathroom Cleaner Tablets","nameKn":"ಕ್ಲೀನರ್ ಟ್ಯಾಬ್ಲೆಟ್ಸ್","category":"cleaning","price":300,"imageUrl":"images\\\\Bathroom Cleaner Tablets 300.jpg","description":"Dissolvable tablets for toilets."},
  {"id":71,"name":"Washing Powder Regular","nameKn":"ವಾಷಿಂಗ್ ಪೌಡರ್","category":"laundry","price":120,"imageUrl":"images\\\\Washing Powder Regular 190.jpg","description":"Effective on everyday stains."},
  {"id":72,"name":"Washing Powder Premium","nameKn":"ಪ್ರೀಮಿಯಂ ವಾಷಿಂಗ್ ಪೌಡರ್","category":"laundry","price":800,"imageUrl":"images\\\\Washing Powder Premium 800.jpg","description":"Enhanced stain removal."},
  {"id":73,"name":"Liquid Detergent","nameKn":"ಲಿಕ್ವಿಡ್ ಡಿಟರ್ಜೆಂಟ್","category":"laundry","price":250,"imageUrl":"images\\\\Liquid Detergent 250.jpg","description":"Concentrated, works in cold water."},
  {"id":74,"name":"Liquid Detergent Bulk","nameKn":"ಬಲ್ಕ್ ಲಿಕ್ವಿಡ್ ಡಿಟರ್ಜೆಂಟ್","category":"laundry","price":820,"imageUrl":"images\\\\Liquid Detergent Bulk 810.jpg","description":"5L bottle, economical."},
  {"id":75,"name":"Fabric Conditioner","nameKn":"ಫ್ಯಾಬ್ರಿಕ್ ಕಂಡೀಷನರ್","category":"laundry","price":220,"imageUrl":"images\\\\Fabric Conditioner 210.jpg","description":"Softens clothes, fresh scent."},
  {"id":76,"name":"Stain Remover Liquid","nameKn":"ಕಲೆ ತೆಗೆಯುವ ದ್ರವ","category":"laundry","price":260,"imageUrl":"images\\\\Stain Remover Liquid 260.jpg","description":"Targets oil, ink, food stains."},
  {"id":78,"name":"Cleaning Powder","nameKn":"ಕ್ಲೀನಿಂಗ್ ಪೌಡರ್","category":"cleaning","price":100,"imageUrl":"images\\\\Cleaning Powder.jpg","description":"Abrasive for tough soil."},
  {"id":80,"name":"Carpet Cleaner Liquid","nameKn":"ಕಾರ್ಪೆಟ್ ಕ್ಲೀನರ್","category":"cleaning","price":250,"imageUrl":"images\\\\Carpet Cleaner Liquid.jpg","description":"Removes deep stains from carpets."},
  {"id":81,"name":"Sofa Cleaner Spray","nameKn":"ಸೋಫಾ ಕ್ಲೀನರ್ ಸ್ಪ್ರೇ","category":"cleaning","price":220,"imageUrl":"images\\\\Sofa Cleaner Spray.jpg","description":"Foam action, safe for fabric."},
  {"id":82,"name":"Multipurpose Cleaner Spray","nameKn":"ಬಹುಪಯೋಗಿ ಕ್ಲೀನರ್","category":"cleaning","price":180,"imageUrl":"images\\\\Multipurpose Cleaner Spray.jpg","description":"All‑purpose for home."},
  {"id":83,"name":"Dust Cleaner Spray","nameKn":"ಡಸ್ಟ್ ಕ್ಲೀನರ್","category":"cleaning","price":140,"imageUrl":"images\\\\Dust Cleaner Spray.jpg","description":"Anti‑static, repels dust."},
  {"id":85,"name":"Room Freshener Gel","nameKn":"ರೂಮ್ ಫ್ರೆಶ್ನರ್ ಜೆಲ್","category":"air","price":120,"imageUrl":"images\\\\Room Freshener Gel.jpg","description":"Long‑lasting gel, no spray."},
  {"id":86,"name":"Naphthalene Balls","nameKn":"ನ್ಯಾಫ್ತಲೀನ್ ಬಾಲ್ಸ್","category":"air","price":90,"imageUrl":"images\\\\Naphthalene Balls.jpg","description":"Moth repellent, deodorizer."},
  {"id":88,"name":"Laundry Combo Pack","nameKn":"ಲಾಂಡ್ರಿ ಕಾಂಬೊ","category":"laundry","price":350,"imageUrl":"images\\\\Laundry Combo Pack.jpg","description":"Powder + liquid + softener."},
  {"id":89,"name":"Cleaning Combo Kit","nameKn":"ಕ್ಲೀನಿಂಗ್ ಕಾಂಬೊ ಕಿಟ್","category":"cleaning","price":1600,"imageUrl":"images\\\\Cleaning Combo Kit.jpg","description":"Essential tools + cleaners."},
  {"id":90,"name":"Household Cleaning Kit","nameKn":"ಮನೆ ಕ್ಲೀನಿಂಗ್ ಕಿಟ್","category":"cleaning","price":600,"imageUrl":"images\\\\Household Cleaning Kit.jpg","description":"All‑in‑one cleaning set."},
  {"id":91,"name":"Eco Friendly Cleaner","nameKn":"ಪರಿಸರ ಸ್ನೇಹಿ ಕ್ಲೀನರ್","category":"cleaning","price":200,"imageUrl":"images\\\\Eco Friendly Cleaner.jpg","description":"Plant‑based, biodegradable."},
  {"id":92,"name":"Bio Enzyme Cleaner","nameKn":"ಜೈವಿಕ ಕಿಣ್ವ ಕ್ಲೀನರ್","category":"cleaning","price":210,"imageUrl":"images\\\\Bio Enzyme Cleaner.jpg","description":"Breaks down organic waste."},
  {"id":93,"name":"Premium Cleaning Kit","nameKn":"ಪ್ರೀಮಿಯಂ ಕ್ಲೀನಿಂಗ್ ಕಿಟ್","category":"cleaning","price":700,"imageUrl":"images\\\\Premium Cleaning Kit.jpg","description":"Luxury set with caddy."},
  {"id":96,"name":"Home Hygiene Kit","nameKn":"ಮನೆಯ ನೈರ್ಮಲ್ಯ ಕಿಟ್","category":"cleaning","price":1600,"imageUrl":"images\\\\Home Hygiene Kit.jpg","description":"Disinfectants + wipes."},
  {"id":97,"name":"Festival Cleaning Kit","nameKn":"ಹಬ್ಬದ ಕ್ಲೀನಿಂಗ್ ಕಿಟ್","category":"cleaning","price":700,"imageUrl":"images\\\\Festival Cleaning Kit.jpg","description":"Special kit for deep cleaning."},
  {"id":98,"name":"Deep Cleaning Kit","nameKn":"ಡೀಪ್ ಕ್ಲೀನಿಂಗ್ ಕಿಟ್","category":"cleaning","price":900,"imageUrl":"images\\\\Deep Cleaning Kit.jpg","description":"Complete professional kit."},
  {"id":99,"name":"Super Saver Cleaning Combo","nameKn":"ಸೂಪರ್ ಸೇವರ್ ಕಾಂಬೊ","category":"cleaning","price":1000,"imageUrl":"images\\\\Super Saver Cleaning Combo.jpg","description":"Best value with 8 items."},
  {"id":100,"name":"Ultimate Home Care Combo","nameKn":"ಅಲ್ಟಿಮೇಟ್ ಹೋಮ್ ಕೇರ್","category":"cleaning","price":1200,"imageUrl":"images\\\\Ultimate Home Care Combo.jpg","description":"12 essential cleaners."},
  {"id":101,"name":"Microfiber Cloth (3 pcs)","nameKn":"ಮೈಕ್ರೋಫೈಬರ್ ಬಟ್ಟೆ","category":"tools","price":90,"imageUrl":"images\\\\Microfiber Cloth (3 pcs).jpg","description":"Lint‑free, washable."},
  {"id":102,"name":"Scrub Brush Set","nameKn":"ಸ್ಕ್ರಬ್ ಬ್ರಷ್ ಸೆಟ್","category":"tools","price":1500,"imageUrl":"images\\\\Scrub Brush Set.jpg","description":"2 brushes for kitchen & bath."},
  {"id":103,"name":"Floor Cleaner Lavender","nameKn":"ನೆಲ ಕ್ಲೀನರ್ (ಲ್ಯಾವೆಂಡರ್)","category":"cleaning","price":1600,"imageUrl":"images\\\\Floor Cleaner Lavender 116.jpg","description":"Calming scent, effective cleans."}
]';
  $data = json_decode($json, true);
  if (!is_array($data)) {
    return [];
  }
  $result = [];
  $idCounter = 1000; // avoid ID conflicts with DB
  foreach ($data as $item) {
    // Map category for frontend filtering
    $cat = $item['category'];
    if ($cat === 'air') {
      $frontCat = 'freshener';
    } elseif ($cat === 'tools') {
      $frontCat = 'cleaning';
    } else {
      $frontCat = $cat;
    }
    // Normalize image path
    $imagePath = $item['imageUrl'] ?? '';
    if ($imagePath && !str_starts_with($imagePath, 'http') && !str_starts_with($imagePath, 'data:')) {
      $imagePath = normalizeImagePath($imagePath);
    }
    $result[] = [
      'id' => $idCounter++,
      'name' => $item['name'],
      'kn_name' => $item['nameKn'] ?? $item['name'],
      'price' => (float) $item['price'],
      'category' => $frontCat,
      'emoji' => '🏠',
      'description_en' => $item['description'] ?? 'Trusted cleaning product',
      'image_url' => $imagePath,
      'image' => $imagePath,
      'variants' => [['label' => '1 unit', 'price' => (float) $item['price']]]
    ];
  }
  $fallback = $result;
  @file_put_contents($jsonPath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  return $fallback;
}
if (!function_exists('getFallbackProducts')) {
  function getFallbackProducts() {
    return getFallbackHomeCareProducts();
  }
}
// Get products from DB first, fallback to static if empty
$products = getHomeCareProducts();
if (empty($products)) {
  $products = getFallbackHomeCareProducts();
}
// Ensure each product has standard keys
foreach ($products as &$p) {
  if (!isset($p['variants']) || !is_array($p['variants']) || empty($p['variants'])) {
    $p['variants'] = [['label' => '1 unit', 'price' => $p['price']]];
  }
  $p['image_url'] = $p['image_url'] ?? $p['image'] ?? '';
}
unset($p);
// Get cart count and total
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
// Top picks (first 8)
$topPicks = array_slice($products, 0, 8);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>Home Care · KSR Guru</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background: #f9f6f2;
      padding: 24px 20px;
      min-height: 100vh;
      font-family: 'Inter', sans-serif;
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
    }

    .cart-badge {
      background: #b45f2b;
      color: white;
      font-size: 0.85rem;
      font-weight: 700;
      padding: 2px 10px;
      border-radius: 40px;
    }

    .cart-link:hover {
      background: #ffe2d0;
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
      width: 110px;
      height: 110px;
      border-radius: 55px;
      background: #fcf4ec;
      border: 2px solid #f0d9c4;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      margin-bottom: 16px;
    }

    .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 55px;
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
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      margin-top: auto;
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
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .add-btn.added-flash {
      background: #4caf50 !important;
    }

    .cart-sidebar {
      position: fixed;
      top: 0;
      right: -420px;
      width: 400px;
      height: 100vh;
      background: #fffefc;
      box-shadow: -8px 0 40px rgba(0, 0, 0, 0.1);
      transition: right 0.3s ease;
      z-index: 1100;
      display: flex;
      flex-direction: column;
      border-left: 2px solid #f2e5d9;
    }

    .cart-sidebar.open {
      right: 0;
    }

    .cart-header {
      padding: 24px;
      border-bottom: 2px solid #f2e5d9;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .close-cart {
      font-size: 1.6rem;
      color: #8a6e54;
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
      color: #8a6e54;
    }

    .cart-item {
      display: flex;
      gap: 14px;
      margin-bottom: 24px;
      padding-bottom: 18px;
      border-bottom: 1px solid #f2e5d9;
      align-items: center;
    }

    .cart-item-image {
      width: 44px;
      height: 44px;
      background: #FCF5EE;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
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
      color: #b45f2b;
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
      background: #f3eee9;
      width: 30px;
      height: 30px;
      border-radius: 40px;
      border: none;
      cursor: pointer;
    }

    .cart-footer {
      padding: 24px;
      border-top: 2px solid #f2e5d9;
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
      background: #b45f2b;
      color: white;
    }

    .continue-shopping {
      background: none;
      border: 1px solid #e6d9cd;
      color: #2f2216;
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

    @media (max-width: 768px) {
      #app {
        padding: 20px;
        border-radius: 28px;
      }

      .header {
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
      }

      .cart-link {
        align-self: flex-start;
        padding: 10px 20px;
      }

      .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
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
    <div> </div>
    <div class="search-section">
      <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
      </div>
      <div class="filter-pills" id="filterPills">
        <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
        <div class="pill" data-category="cleaning">Cleaning / ಶುಚಿಗೊಳಿಸುವಿಕೆ</div>
        <div class="pill" data-category="laundry">Laundry / ಲಾಂಡ್ರಿ</div>
        <div class="pill" data-category="freshener">Air Freshener / ಏರ್ ಫ್ರೆಶ್ನರ್</div>
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
                          $subcat = $p['category'] ?? 'cleaning';
                          if (!in_array($subcat, ['cleaning', 'laundry', 'freshener'])) {
                            $subcat = 'cleaning';
                          }
                          $out[] = [
                            'id' => $p['id'],
                            'nameEn' => $p['name'],
                            'nameKn' => $p['kn_name'] ?? $p['name'],
                            'category' => $subcat,
                            'variants' => $p['variants'] ?? [['label' => '1 unit', 'price' => $p['price']]],
                            'emoji' => $p['emoji'] ?? '🏠',
                            'description' => $p['description_en'] ?? 'Trusted Cleaning Formula',
                            'image' => $p['image_url'] ?? ''
                          ];
                        }
                        echo json_encode($out);
                        ?>;
      let cart = [];
      const basePath = 'home-care.php';
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
          <div class="cart-item-image">🏠</div>
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
          grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No home-care products found ✨</div>';
          return;
        }
        grid.innerHTML = filtered.map(p => {
          const defaultVariant = 0;
          const defaultPrice = p.variants[defaultVariant]?.price || 0;
          // Image rendering: if image exists, show <img>, else emoji
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
          <div class="price-row">
            <span class="product-price">₹${defaultPrice}</span>
            <button class="add-btn" data-pid="${p.id}" onclick="window.addToCart(${p.id})"><i class="fas fa-cart-plus"></i></button>
          </div>
        </div>`;
        }).join('');
        document.querySelectorAll('.weight-select').forEach(sel => {
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
      renderProducts();
    })();
  </script>
  <?php include __DIR__ . '/../includes/user-footer.php'; ?>
  <script src="sorting.js"></script>
</body>

</html>