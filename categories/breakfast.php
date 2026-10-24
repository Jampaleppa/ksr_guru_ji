<?php
  // ============================================================
  // breakfast.php - Dynamic Breakfast product page
  // ============================================================
  require_once __DIR__ . '/../config.php';
  require_once __DIR__ . '/../functions.php';
  // Define image base URL for breakfast category
  if (!defined('IMAGE_BASE_URL')) {
    define('IMAGE_BASE_URL', '../images/Breakfast/');
  }
  // Start session
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  // Get current user info if logged in
  $userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest';
  $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
  // Initialize cart if not exists
  if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
  }
  /**
   * Normalise image path: extract filename and build URL using IMAGE_BASE_URL
   */
  if (!function_exists('normalizeImagePath')) {
    function normalizeImagePath(string $rawPath): string
    {
      if (empty($rawPath)) return '';
      // Get the filename from the absolute path (e.g., "Kellogg's Corn Flakes Original.jpg")
      $filename = basename($rawPath);
      // Return the URL built from the base + filename
      return IMAGE_BASE_URL . $filename;
    }
  }
  // Handle AJAX requests for this page
  if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'] ?? '';
    switch ($action) {
      case 'products':
        $search = $_GET['search'] ?? '';
        // Mirror the page-body behaviour: fall back to the static catalogue when
        // the DB has no rows for this category, otherwise search returns nothing.
        $list = getBreakfastProducts($search);
        if (empty($list)) {
          $list = getFallbackProducts($search);
        }
        echo json_encode($list);
        exit;
      case 'cart':
        echo json_encode($_SESSION['cart']);
        exit;
      case 'add_cart':
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
          $product = getProductById($id);
          // If not found in DB, search in fallback static data
          if (!$product) {
            $fallbackProducts = getFallbackProducts();
            foreach ($fallbackProducts as $fp) {
              if ($fp['id'] == $id) {
                $product = [
                  'id' => $fp['id'],
                  'name' => $fp['name'],
                  'kn_name' => $fp['kn_name'] ?? $fp['name'],
                  'price' => $fp['price'],
                  'icon' => 'fa-bread-slice'
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
                'icon' => $product['icon'] ?? 'fa-bread-slice'
              ];
            }
            echo json_encode(['success' => true, 'cart' => $_SESSION['cart']]);
            exit;
          }
        }
        echo json_encode(['success' => false]);
        exit;
      case 'update_cart':
        $id = (int)($_POST['id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 0);
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
        $id = (int)($_POST['id'] ?? 0);
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
  // Helper function to fetch breakfast products using MySQLi
  function getBreakfastProducts(string $search = ''): array
  {
    try {
      $pdo = getDBConnection();

      // The products table has no image_url/description_en columns; resolve the
      // real ones dynamically (falls back to p.images / p.description).
      $schema = getProductSchemaInfo();
      $descCol = $schema['desc_col'];
      $imgCol  = $schema['img_col'];
      $slugCol = $schema['cat_slug_col'];   // 'c.slug' when it exists

      // Prefer the slug lookup, but fall back to the category name so the page
      // still works if the slug value differs (actual slug is 'breakfast').
      $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                       c.name AS category_name, c.kn_name AS category_kn,
                       {$imgCol} AS image_url, {$descCol} AS description_en
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE ({$slugCol} = ? OR c.name = ?)";
      $params = ['breakfast', 'Breakfast'];
      if ($search !== '') {
        $sql .= " AND (p.name LIKE ? OR p.kn_name LIKE ?)";
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
      }
      $sql .= " ORDER BY p.name ASC";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach ($rows as &$row) {
        $row['description_en'] = $row['description_en'] ?? '';
        $row['imageurl'] = normalizeImagePath($row['image_url'] ?? '');
        $row['category'] = $row['category_slug'] ?? 'cereal';
      }
      unset($row);
      return $rows;
    } catch (Exception $e) {
      // If DB fails, return fallback
      return getFallbackProducts($search);
    }
  }
  // ============================================================
  // FALLBACK: Static data (only used when DB is unavailable)
  // ============================================================
  function getFallbackProducts($search = '')
  {
    static $fallback = null;
    if ($fallback !== null) {
      return applySearch($fallback, $search);
    }
    $json = <<<'JSON'
[
  {"id":1,"name":"Kellogg's Corn Flakes Original","category":"cereal","price":350,"imageurl":"images\\\\Kellogg's Corn Flakes Original.jpg","nameKn":"Kellogg's ಕಾರ್ನ್ ಫ್ಲೇಕ್ಸ್","description":"Crispy flakes, rich in iron."},
  {"id":2,"name":"Kellogg's Chocos","category":"cereal","price":190,"imageurl":"images\\\\Kellogg's Chocos 190.jpg","nameKn":"Kellogg's ಚೋಕೋಸ್","description":"Chocolate flavored breakfast cereal."},
  {"id":3,"name":"Kellogg's Muesli Fruit & Nut","category":"cereal","price":320,"imageurl":"images\\\\Kellogg's Muesli Fruit & Nut 320.jpg","nameKn":"Kellogg's ಮೂಸ್ಲಿ ಫ್ರೂಟ್ & ನಟ್","description":"Whole grains with dried fruits."},
  {"id":4,"name":"Bagrry's Muesli Crunchy","category":"cereal","price":160,"imageurl":"images\\\\Bagrry's Muesli Crunchy 160.jpg","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ಕ್ರಂಚಿ ಮೂಸ್ಲಿ","description":"Crunchy muesli with nuts."},
  {"id":5,"name":"Saffola Oats","category":"cereal","price":20,"imageurl":"images\\\\Saffola Oats 20.jpg","nameKn":"ಸ್ಯಾಫೋಲಾ ಓಟ್ಸ್","description":"Heart-healthy rolled oats."},
  {"id":6,"name":"Quaker Oats","category":"cereal","price":174,"imageurl":"images\\\\Quaker Oats 174.jpg","nameKn":"ಕ್ವೇಕರ್ ಓಟ್ಸ್","description":"Classic oatmeal."},
  {"id":7,"name":"Yoga Bar Muesli","category":"cereal","price":190,"imageurl":"images\\\\Yoga Bar Muesli 190.jpg","nameKn":"ಯೋಗಾ ಬಾರ್ ಮೂಸ್ಲಿ","description":"Protein rich muesli."},
  {"id":8,"name":"Nestle Koko Krunch","category":"cereal","price":340,"imageurl":"images\\\\Nestle Koko Krunch 340.jpg","nameKn":"ನೆಸ್ಲೆ ಕೋಕೋ ಕ್ರಂಚ್","description":"Chocolatey rice puffs."},
  {"id":9,"name":"Patanjali Corn Flakes","category":"cereal","price":230,"imageurl":"images\\\\Patanjali Corn Flakes 230.jpg","nameKn":"ಪತಂಜಲಿ ಕಾರ್ನ್ ಫ್ಲೇಕ್ಸ್","description":"Natural corn flakes."},
  {"id":10,"name":"Soulfull Millet Muesli","category":"cereal","price":810,"imageurl":"images\\\\Soulfull Millet Muesli 810.jpg","nameKn":"ಸೋಲ್ಫುಲ್ ಮಿಲೆಟ್ ಮೂಸ್ಲಿ","description":"Millet based muesli."},
  {"id":11,"name":"Betty Crocker Pancake Mix","category":"pancake","price":243,"imageurl":"images\\\\Betty Crocker Pancake Mix 243.jpg","nameKn":"ಬೆಟ್ಟಿ ಕ್ರೋಕರ್ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Fluffy & easy to make pancake mix."},
  {"id":12,"name":"Pillsbury Pancake Mix","category":"pancake","price":95,"imageurl":"images\\\\Pillsbury Pancake Mix 95.jpg","nameKn":"ಪಿಲ್ಸ್ಬರಿ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Classic pancake mix."},
  {"id":13,"name":"Dr. Oetker FunFoods Pancake Mix","category":"pancake","price":95,"imageurl":"images\\\\Dr. Oetker FunFoods Pancake Mix 95.jpg","nameKn":"ಡಾ. ಓಟ್ಕರ್ ಫನ್‌ಫುಡ್ಸ್","description":"Quick and tasty."},
  {"id":14,"name":"Urban Platter Pancake Mix","category":"pancake","price":190,"imageurl":"images\\\\Urban Platter Pancake Mix' 190.jpg","nameKn":"ಅರ್ಬನ್ ಪ್ಲಾಟರ್","description":"Premium pancake mix."},
  {"id":15,"name":"Wheat flakes","category":"cereal","price":400,"imageurl":"images\\\\Wheat flakes 261-400.jpg","nameKn":"ಗೋಧಿ ಪದರಗಳು","description":"Whole wheat option."},
  {"id":16,"name":"Keya Pancake Mix","category":"pancake","price":370,"imageurl":"images\\\\Keya Pancake Mix 370.jpg","nameKn":"ಕೇಯಾ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"High protein mix."},
  {"id":17,"name":"24 Mantra Organic Pancake Mix","category":"pancake","price":60,"imageurl":"images\\\\24 Mantra Organic Pancake Mix 120.jpg","nameKn":"24 ಮಂತ್ರ ಸಾವಯವ","description":"Organic ingredients."},
  {"id":18,"name":"Slurrp Farm Millet Pancake Mix","category":"pancake","price":90,"imageurl":"images\\\\Slurrp Farm Millet Pancake Mix 90.jpg","nameKn":"ಸ್ಲರ್ಪ್ ಫಾರ್ಮ್ ಮಿಲೆಟ್","description":"Millet based healthy mix."},
  {"id":19,"name":"Nourish Organics Pancake Mix","category":"pancake","price":150,"imageurl":"images\\\\Nourish Organics Pancake Mix 150.jpg","nameKn":"ನೌರಿಶ್ ಆರ್ಗ್ಯಾನಿಕ್ಸ್","description":"Gluten free option."},
  {"id":20,"name":"MTR Instant Pancake Mix","category":"pancake","price":95,"imageurl":"images\\\\MTR Instant Pancake Mix 95.jpg","nameKn":"ಎಂಟಿಆರ್ ಇನ್ಸ್ಟಂಟ್","description":"Quick instant mix."},
  {"id":21,"name":"Farm Fresh White Eggs Pack","category":"eggs","price":260,"imageurl":"images\\\\Farm Fresh White Eggs Pack.jpg","nameKn":"ತಾಜಾ ಬಿಳಿ ಮೊಟ್ಟೆ","description":"Farm-fresh, protein-rich eggs."},
  {"id":22,"name":"Farm Fresh Brown Eggs Pack","category":"eggs","price":420,"imageurl":"images\\\\Farm Fresh Brown Eggs Pack.jpg","nameKn":"ತಾಜಾ ಕಂದು ಮೊಟ್ಟೆ","description":"Nutrient rich brown eggs."},
  {"id":23,"name":"Organic Eggs Pack","category":"eggs","price":75,"imageurl":"images\\\\Organic Eggs Pack.jpg","nameKn":"ಸಾವಯವ ಮೊಟ್ಟೆ","description":"Certified organic, free-range."},
  {"id":24,"name":"Country Eggs (Desi Eggs)","category":"eggs","price":90,"imageurl":"images\\\\Country Eggs (Desi Eggs).jpg","nameKn":"ದೇಸಿ ಮೊಟ್ಟೆ","description":"Traditional desi eggs."},
  {"id":25,"name":"Omega 3 Enriched Eggs","category":"eggs","price":85,"imageurl":"images\\\\Omega 3 Enriched Eggs.jpg","nameKn":"ಒಮೆಗಾ 3 ಮೊಟ್ಟೆ","description":"Omega-3 enriched for brain health."},
  {"id":26,"name":"Protein Rich Eggs Pack","category":"eggs","price":110,"imageurl":"images\\\\Protein Rich Eggs Pack.jpg","nameKn":"ಪ್ರೋಟೀನ್ ಮೊಟ್ಟೆ","description":"High protein content."},
  {"id":27,"name":"Local Farm Eggs Tray","category":"eggs","price":240,"imageurl":"images\\\\Local Farm Eggs Tray.jpg","nameKn":"ಸ್ಥಳೀಯ ಫಾರ್ಮ್ ಟ್ರೇ","description":"Farm fresh tray."},
  {"id":28,"name":"Premium Brown Eggs Tray","category":"eggs","price":270,"imageurl":"images\\\\Premium Brown Eggs Tray.jpg","nameKn":"ಪ್ರೀಮಿಯಂ ಕಂದು ಮೊಟ್ಟೆ","description":"Premium quality."},
  {"id":29,"name":"Organic Country Eggs Tray","category":"eggs","price":350,"imageurl":"images\\\\Organic Country Eggs Tray.jpg","nameKn":"ಸಾವಯವ ದೇಸಿ ಟ್ರೇ","description":"Organic desi eggs tray."},
  {"id":30,"name":"Budget White Eggs Tray","category":"eggs","price":210,"imageurl":"images\\\\Budget White Eggs Tray.jpg","nameKn":"ಅಗ್ಗದ ಬಿಳಿ ಮೊಟ್ಟೆ","description":"Economical white eggs."},
  {"id":31,"name":"24 Mantra Organic Pancake Mix","category":"pancake","price":60,"imageurl":"images\\\\24 Mantra Organic Pancake Mix 120.jpg","nameKn":"24 ಮಂತ್ರ ಸಾವಯವ","description":"Organic ingredients."},
  {"id":32,"name":"Amul Kool Flavored Milk 30","category":"drinks","price":50,"imageurl":"images\\\\Amul Kool Flavored Milk 30.jpg","nameKn":"ಅಮೂಲ್ ಕೂಲ್ ಫ್ಲೇವರ್ಡ್ ಮಿಲ್ಕ್","description":"Flavored milk drink."},
  {"id":33,"name":"Appams","category":"breakfast","price":400,"imageurl":"images\\\\Appams 394.jpg","nameKn":"ಅಪ್ಪಂ","description":"Soft fermented rice pancake."},
  {"id":34,"name":"Badam Milk","category":"drinks","price":210,"imageurl":"images\\\\Badam Milk 210.jpg","nameKn":"ಬಾದಾಮ್ ಹಾಲು","description":"Almond flavored milk."},
  {"id":35,"name":"Bagrry's Corn Flakes","category":"cereal","price":200,"imageurl":"images\\\\Bagrry's Corn Flakes 190.jpg","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ಕಾರ್ನ್ ಫ್ಲೇಕ್ಸ್","description":"Crispy corn flakes."},
  {"id":36,"name":"Bagrry's Crunchy Granola","category":"cereal","price":310,"imageurl":"images\\\\Bagrry's Crunchy Granola 310.jpg","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ಕ್ರಂಚಿ ಗ್ರಾನೋಲಾ","description":"Crunchy granola."},
  {"id":37,"name":"Bagrry's Fruit & Fibre Muesli","category":"cereal","price":740,"imageurl":"images\\\\Bagrry's Fruit & Fibre Muesli 740.jpg","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ಫ್ರೂಟ್ & ಫೈಬರ್ ಮೂಸ್ಲಿ","description":"High fibre muesli."},
  {"id":38,"name":"Bagrry's Muesli Crunchy","category":"cereal","price":160,"imageurl":"images\\\\Bagrry's Muesli Crunchy 160.jpg","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ಕ್ರಂಚಿ ಮೂಸ್ಲಿ","description":"Crunchy muesli."},
  {"id":39,"name":"Bagrry's white Oats","category":"cereal","price":160,"imageurl":"images\\\\Bagrry's white Oats 160.jpg","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ಬಿಳಿ ಓಟ್ಸ್","description":"Rolled white oats."},
  {"id":40,"name":"Bajra (Pearl Millet)","category":"grains","price":40,"imageurl":"images\\\\Bajra (Pearl Millet) 40.jpg","nameKn":"ಬಜ್ರ (ಮುತ್ತು ರಾಗಿ)","description":"Nutritious pearl millet grain."},
  {"id":41,"name":"Kellogg's Special K","category":"cereal","price":350,"imageurl":"images\\\\Kellogg's Special K.jpg","nameKn":"Kellogg's ಸ್ಪೆಷಲ್ ಕೆ","description":"Light and crispy."},
  {"id":42,"name":"Kellogg's Muesli No Added Sugar","category":"cereal","price":180,"imageurl":"images\\\\Kellogg's Muesli No Added Sugar 180.jpg","nameKn":"Kellogg's ನೋ ಸಕ್ಕರ್ ಮೂಸ್ಲಿ","description":"No added sugar."},
  {"id":43,"name":"Bagrry's Fruit & Fibre Muesli","category":"cereal","price":740,"imageurl":"images\\\\Bagrry's Fruit & Fibre Muesli 740.jpg","nameKn":"ಬ್ಯಾಗ್ರೀಸ್ ಫ್ರೂಟ್ & ಫೈಬರ್","description":"High fibre muesli."},
  {"id":44,"name":"Quaker Multigrain Oats","category":"cereal","price":320,"imageurl":"images\\\\Quaker Multigrain Oats 320.jpg","nameKn":"ಕ್ವೇಕರ್ ಮಲ್ಟಿಗ್ರೇನ್ ಓಟ್ಸ್","description":"Multigrain oats."},
  {"id":45,"name":"Saffola Masala Oats","category":"cereal","price":70,"imageurl":"images\\\\Saffola Masala Oats 70.jpg","nameKn":"ಸ್ಯಾಫೋಲಾ ಮಸಾಲಾ ಓಟ್ಸ್","description":"Savory masala oats."},
  {"id":46,"name":"Yoga Bar Dark Chocolate Muesli","category":"cereal","price":160,"imageurl":"images\\\\Yoga Bar Dark Chocolate Muesli 160.jpg","nameKn":"ಯೋಗಾ ಬಾರ್ ಡಾರ್ಕ್ ಚಾಕೊಲೇಟ್","description":"Dark chocolate muesli."},
  {"id":47,"name":"Nestle Gold Cornflakes","category":"cereal","price":270,"imageurl":"images\\\\Nestle Gold Cornflakes 270.jpg","nameKn":"ನೆಸ್ಲೆ ಗೋಲ್ಡ್ ಕಾರ್ನ್ಫ್ಲೇಕ್ಸ್","description":"Premium cornflakes."},
  {"id":48,"name":"Patanjali Oats","category":"cereal","price":150,"imageurl":"images\\\\Patanjali Oats 150.jpg","nameKn":"ಪತಂಜಲಿ ಓಟ್ಸ್","description":"Natural oats."},
  {"id":49,"name":"Soulfull Ragi Bites","category":"cereal","price":60,"imageurl":"images\\\\Soulfull Ragi Bites 60.jpg","nameKn":"ಸೋಲ್ಫುಲ್ ರಾಗಿ ಬೈಟ್ಸ್","description":"Ragi based bites."},
  {"id":50,"name":"True Elements Muesli","category":"cereal","price":485,"imageurl":"images\\\\True Elements Muesli 485.jpg","nameKn":"ಟ್ರೂ ಎಲಿಮೆಂಟ್ಸ್ ಮೂಸ್ಲಿ","description":"Clean label muesli."},
  {"id":51,"name":"Betty Crocker Chocolate Pancake Mix","category":"pancake","price":420,"imageurl":"images\\\\Betty Crocker Chocolate Pancake Mix 420.jpg","nameKn":"ಬೆಟ್ಟಿ ಕ್ರೋಕರ್ ಚಾಕೊಲೇಟ್","description":"Chocolate pancake mix."},
  {"id":52,"name":"Pillsbury Multigrain Pancake Mix","category":"pancake","price":85,"imageurl":"images\\\\Pillsbury Multigrain Pancake Mix 85.jpg","nameKn":"ಪಿಲ್ಸ್ಬರಿ ಮಲ್ಟಿಗ್ರೇನ್","description":"Multigrain mix."},
  {"id":53,"name":"Urban Platter Gluten Free Pancake Mix","category":"pancake","price":380,"imageurl":"images\\\\Urban Platter Gluten Free Pancake Mix.jpg","nameKn":"ಗ್ಲುಟನ್ ಫ್ರೀ","description":"Gluten free option."},
  {"id":54,"name":"Keya Chocolate Pancake Mix","category":"pancake","price":120,"imageurl":"images\\\\Keya Chocolate Pancake Mix 120.jpg","nameKn":"ಕೇಯಾ ಚಾಕೊಲೇಟ್","description":"Chocolate flavored."},
  {"id":55,"name":"Slurrp Farm Banana Pancake Mix","category":"pancake","price":290,"imageurl":"images\\\\Slurrp Farm Banana Pancake Mix.jpg","nameKn":"ಬಾಳೆಹಣ್ಣು ಪ್ಯಾನ್ಕೇಕ್","description":"Banana flavored."},
  {"id":56,"name":"24 Mantra Organic Ragi Pancake Mix","category":"pancake","price":60,"imageurl":"images\\\\24 Mantra Organic Ragi Pancake Mix 60.jpg","nameKn":"ರಾಗಿ ಪ್ಯಾನ್ಕೇಕ್","description":"Ragi pancake mix."},
  {"id":57,"name":"Nourish Organics Multigrain Pancake Mix","category":"pancake","price":4400,"imageurl":"images\\\\Nourish Organics Multigrain Pancake Mix 4400.jpg","nameKn":"ಮಲ್ಟಿಗ್ರೇನ್","description":"Multigrain organic."},
  {"id":58,"name":"DiSano Whole Wheat Pancake Mix","category":"pancake","price":260,"imageurl":"images\\\\DiSano Whole Wheat Pancake Mix 260.jpg","nameKn":"ಗೋಧಿ ಪ್ಯಾನ್ಕೇಕ್","description":"Whole wheat."},
  {"id":59,"name":"MTR Instant Multigrain Pancake Mix","category":"pancake","price":150,"imageurl":"images\\\\MTR Instant Multigrain Pancake Mix 150.jpg","nameKn":"ಮಲ್ಟಿಗ್ರೇನ್ ಇನ್ಸ್ಟಂಟ್","description":"Instant multigrain."},
  {"id":60,"name":"Veeba Pancake Mix","category":"pancake","price":290,"imageurl":"images\\\\Veeba Pancake Mix.jpg","nameKn":"ವೀಬಾ ಪ್ಯಾನ್ಕೇಕ್","description":"Easy to make."},
  {"id":61,"name":"Betty Crocker Blueberry Pancake Mix","category":"pancake","price":390,"imageurl":"images\\\\Betty Crocker Blueberry Pancake Mix 390.jpg","nameKn":"ಬೆಟ್ಟಿ ಕ್ರೋಕರ್ ಬ್ಲೂಬೆರ್ರಿ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Blueberry flavored pancake mix."},
  {"id":62,"name":"Banana Pancakes","category":"pancake","price":120,"imageurl":"images\\\\Banana Pancakes 120.jpg","nameKn":"ಬಾಳೆಹಣ್ಣು ಪ್ಯಾನ್ಕೇಕ್ಸ್","description":"Soft banana pancakes."},
  {"id":63,"name":"Barley","category":"grains","price":130,"imageurl":"images\\\\Barley 130.jpg","nameKn":"ಬಾರ್ಲಿ","description":"Whole barley grains."},
  {"id":64,"name":"Betty Crocker Chocolate Pancake Mix","category":"pancake","price":420,"imageurl":"images\\\\Betty Crocker Chocolate Pancake Mix 420.jpg","nameKn":"ಬೆಟ್ಟಿ ಕ್ರೋಕರ್ ಚಾಕೊಲೇಟ್ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Chocolate pancake mix."},
  {"id":65,"name":"Betty Crocker Pancake Mix","category":"pancake","price":243,"imageurl":"images\\\\Betty Crocker Pancake Mix 243.jpg","nameKn":"ಬೆಟ್ಟಿ ಕ್ರೋಕರ್ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Original pancake mix."},
  {"id":66,"name":"Broken Wheat (Daliya)","category":"grains","price":240,"imageurl":"images\\\\Broken Wheat (Daliya) 240.jpg","nameKn":"ಒಡೆದ ಗೋಧಿ (ದಲಿಯಾ)","description":"Cracked wheat porridge."},
  {"id":67,"name":"Cavin's Milkshake","category":"drinks","price":150,"imageurl":"images\\\\Cavin's Milkshake 150.jpg","nameKn":"ಕ್ಯಾವಿನ್ಸ್ ಮಿಲ್ಕ್ಶೇಕ್","description":"Creamy milkshake."},
  {"id":68,"name":"Chocolate Pancakes","category":"pancake","price":177,"imageurl":"images\\\\Chocolate Pancakes 177.jpg","nameKn":"ಚಾಕೊಲೇಟ್ ಪ್ಯಾನ್ಕೇಕ್ಸ್","description":"Chocolate pancakes."},
  {"id":69,"name":"Chocolate Syrup","category":"syrup","price":100,"imageurl":"images\\\\Chocolate Syrup 100.jpg","nameKn":"ಚಾಕೊಲೇಟ್ ಸಿರಪ್","description":"Sweet chocolate syrup."},
  {"id":70,"name":"Classic Pancakes","category":"pancake","price":400,"imageurl":"images\\\\Classic Pancakes 400.jpg","nameKn":"ಕ್ಲಾಸಿಕ್ ಪ್ಯಾನ್ಕೇಕ್ಸ್","description":"Classic buttermilk pancakes."},
  {"id":71,"name":"Coconut Water","category":"drinks","price":25,"imageurl":"images\\\\Coconut Water 25.jpg","nameKn":"ತೆಂಗಿನ ನೀರು","description":"Natural coconut water."},
  {"id":72,"name":"Dosa","category":"breakfast","price":142,"imageurl":"images\\\\Dosa 142.jpg","nameKn":"ದೋಸೆ","description":"Crispy fermented rice crepe."},
  {"id":73,"name":"Dr. Oetker FunFoods Pancake Mix","category":"pancake","price":2000,"imageurl":"images\\\\Dr. Oetker FunFoods Pancake Mix 2000.jpg","nameKn":"ಡಾ. ಓಟ್ಕರ್ ಫನ್‌ಫುಡ್ಸ್ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Premium pancake mix."},
  {"id":74,"name":"Frooti Mango Drink","category":"drinks","price":20,"imageurl":"images\\\\Frooti Mango Drink 10.jpg","nameKn":"ಫ್ರೂಟಿ ಮಾವಿನ ಹಣ್ಣಿನ ಪಾನೀಯ","description":"Mango flavored drink."},
  {"id":75,"name":"Hershey's Chocolate Milkshake","category":"drinks","price":120,"imageurl":"images\\\\Hershey's Chocolate Milkshake 120.jpg","nameKn":"ಹರ್ಷೆಸ್ ಚಾಕೊಲೇಟ್ ಮಿಲ್ಕ್ಶೇಕ್","description":"Chocolate milkshake."},
  {"id":76,"name":"honey","category":"sweetener","price":300,"imageurl":"images\\\\honey 300.jpg","nameKn":"ಜೇನು","description":"Natural honey."},
  {"id":77,"name":"Honey","category":"sweetener","price":570,"imageurl":"images\\\\Honey 570.jpg","nameKn":"ಜೇನು","description":"Pure honey."},
  {"id":78,"name":"Hot Chocolate","category":"drinks","price":630,"imageurl":"images\\\\Hot Chocolate 630.jpg","nameKn":"ಹಾಟ್ ಚಾಕೊಲೇಟ್","description":"Rich hot chocolate drink."},
  {"id":79,"name":"Idli","category":"breakfast","price":130,"imageurl":"images\\\\Idli 129.jpg","nameKn":"ಇಡ್ಲಿ","description":"Steamed rice cakes."},
  {"id":80,"name":"Jowar (Sorghum)","category":"grains","price":50,"imageurl":"images\\\\Jowar (Sorghum) 50.jpg","nameKn":"ಜೋಳ","description":"Whole sorghum grain."},
  {"id":81,"name":"Kellogg's Corn Flakes","category":"cereal","price":350,"imageurl":"images\\\\Kellogg's Corn Flakes 350.jpg","nameKn":"Kellogg's ಕಾರ್ನ್ ಫ್ಲೇಕ್ಸ್","description":"Crispy corn flakes."},
  {"id":82,"name":"Kellogg's Chocos","category":"cereal","price":190,"imageurl":"images\\\\Kellogg's Chocos 190.jpg","nameKn":"Kellogg's ಚೋಕೋಸ್","description":"Chocolate cereal."},
  {"id":83,"name":"Kellogg's Granola Almond & Honey","category":"cereal","price":95,"imageurl":"images\\\\Kellogg's Granola Almond & Honey 94.jpg","nameKn":"Kellogg's ಗ್ರಾನೋಲಾ ಬಾದಾಮಿ ಮತ್ತು ಜೇನು","description":"Almond and honey granola."},
  {"id":84,"name":"Kellogg's Muesli Fruit & Nut","category":"cereal","price":320,"imageurl":"images\\\\Kellogg's Muesli Fruit & Nut 320.jpg","nameKn":"Kellogg's ಮೂಸ್ಲಿ ಫ್ರೂಟ್ & ನಟ್","description":"Fruit and nut muesli."},
  {"id":85,"name":"Kellogg's Muesli No Added Sugar","category":"cereal","price":180,"imageurl":"images\\\\Kellogg's Muesli No Added Sugar 180.jpg","nameKn":"Kellogg's ಸಕ್ಕರೆ ರಹಿತ ಮೂಸ್ಲಿ","description":"No added sugar muesli."},
  {"id":86,"name":"Keya Pancake Mix","category":"pancake","price":370,"imageurl":"images\\\\Keya Pancake Mix 370.jpg","nameKn":"ಕೇಯಾ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"High protein pancake mix."},
  {"id":87,"name":"Kodiak Power Cakes 1670","category":"pancake","price":1680,"imageurl":"images\\\\Kodiak Power Cakes 1670.jpg","nameKn":"ಕೊಡಿಯಾಕ್ ಪವರ್ ಕೇಕ್ಸ್","description":"Protein rich pancake mix."},
  {"id":88,"name":"Maple Syrup","category":"syrup","price":1030,"imageurl":"images\\\\Maple Syrup 1030.jpg","nameKn":"ಮೇಪಲ್ ಸಿರಪ್","description":"Pure maple syrup."},
  {"id":89,"name":"MTR Instant Pancake Mix","category":"pancake","price":3200,"imageurl":"images\\\\MTR Instant Pancake Mix 3102.jpg","nameKn":"ಎಂಟಿಆರ್ ಇನ್ಸ್ಟಂಟ್ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Instant pancake mix."},
  {"id":90,"name":"Nestle Gold Cornflakes","category":"cereal","price":270,"imageurl":"images\\\\Nestle Gold Cornflakes 270.jpg","nameKn":"ನೆಸ್ಲೆ ಗೋಲ್ಡ್ ಕಾರ್ನ್ಫ್ಲೇಕ್ಸ್","description":"Premium cornflakes."},
  {"id":91,"name":"Nestle Koko Krunch","category":"cereal","price":340,"imageurl":"images\\\\Nestle Koko Krunch 340.jpg","nameKn":"ನೆಸ್ಲೆ ಕೋಕೋ ಕ್ರಂಚ್","description":"Chocolate rice puffs."},
  {"id":92,"name":"Nourish Organics Multigrain Pancake Mix","category":"pancake","price":4400,"imageurl":"images\\\\Nourish Organics Multigrain Pancake Mix 4400.jpg","nameKn":"ನೌರಿಶ್ ಆರ್ಗ್ಯಾನಿಕ್ಸ್ ಮಲ್ಟಿಗ್ರೇನ್ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Organic multigrain pancake mix."},
  {"id":93,"name":"Nourish Organics Pancake Mix 150","category":"pancake","price":150,"imageurl":"images\\\\Nourish Organics Pancake Mix 150.jpg","nameKn":"ನೌರಿಶ್ ಆರ್ಗ್ಯಾನಿಕ್ಸ್ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Organic pancake mix."},
  {"id":94,"name":"Oats Pancake","category":"pancake","price":500,"imageurl":"images\\\\Oats Pancake 350-500.jpg","nameKn":"ಓಟ್ಸ್ ಪ್ಯಾನ್ಕೇಕ್","description":"Healthy oats pancake."},
  {"id":95,"name":"Patanjali Corn Flakes 230","category":"cereal","price":233,"imageurl":"images\\\\Patanjali Corn Flakes 230.jpg","nameKn":"ಪತಂಜಲಿ ಕಾರ್ನ್ ಫ್ಲೇಕ್ಸ್","description":"Natural corn flakes."},
  {"id":96,"name":"Patanjali Oats","category":"cereal","price":150,"imageurl":"images\\\\Patanjali Oats 150.jpg","nameKn":"ಪತಂಜಲಿ ಓಟ್ಸ್","description":"Oats."},
  {"id":97,"name":"Pesarattu","category":"breakfast","price":220,"imageurl":"images\\\\Pesarattu 220.jpg","nameKn":"ಪೆಸರಟ್ಟು","description":"Moong dal crepe."},
  {"id":98,"name":"Pillsbury Multigrain Pancake Mix","category":"pancake","price":85,"imageurl":"images\\\\Pillsbury Multigrain Pancake Mix 85.jpg","nameKn":"ಪಿಲ್ಸ್ಬರಿ ಮಲ್ಟಿಗ್ರೇನ್ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Multigrain pancake mix."},
  {"id":99,"name":"Puramate Pancake Mix","category":"pancake","price":250,"imageurl":"images\\\\Puramate Pancake Mix 220.jpg","nameKn":"ಪುರಮೇಟ್ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Pancake mix."},
  {"id":100,"name":"Quaker Multigrain Oats","category":"cereal","price":320,"imageurl":"images\\\\Quaker Multigrain Oats 320.jpg","nameKn":"ಕ್ವೇಕರ್ ಮಲ್ಟಿಗ್ರೇನ್ ಓಟ್ಸ್","description":"Multigrain oats."},
  {"id":101,"name":"Quinoa","category":"grains","price":260,"imageurl":"images\\\\Quinoa 290.jpg","nameKn":"ಕಿನೋವಾ","description":"Protein rich quinoa."},
  {"id":102,"name":"Ragi (Finger Millet) 530","category":"grains","price":530,"imageurl":"images\\\\Ragi (Finger Millet) 530.jpg","nameKn":"ರಾಗಿ","description":"Nutritious finger millet."},
  {"id":103,"name":"Ragi 50","category":"grains","price":50,"imageurl":"images\\\\Ragi 50.jpg","nameKn":"ರಾಗಿ","description":"Finger millet."},
  {"id":104,"name":"Rice 376","category":"grains","price":380,"imageurl":"images\\\\Rice 376.jpg","nameKn":"ಅಕ್ಕಿ","description":"Raw rice."},
  {"id":105,"name":"Corn Flakes Original","category":"cereal","price":471,"imageurl":"images\\\\Corn Flakes Original 471.jpg","nameKn":"ಕಾರ್ನ್ ಫ್ಲೇಕ್ಸ್","description":"Corn flakes."},
  {"id":106,"name":"Saffola Masala Oats","category":"cereal","price":70,"imageurl":"images\\\\Saffola Masala Oats 70.jpg","nameKn":"ಸ್ಯಾಫೋಲಾ ಮಸಾಲಾ ಓಟ್ಸ್","description":"Savory masala oats."},
  {"id":107,"name":"Saffola Oats","category":"cereal","price":20,"imageurl":"images\\\\Saffola Oats 20.jpg","nameKn":"ಸ್ಯಾಫೋಲಾ ಓಟ್ಸ್","description":"Rolled oats."},
  {"id":108,"name":"Slurrp Farm Millet Pancake Mix","category":"pancake","price":90,"imageurl":"images\\\\Slurrp Farm Millet Pancake Mix 90.jpg","nameKn":"ಸ್ಲರ್ಪ್ ಫಾರ್ಮ್ ಮಿಲೆಟ್ ಪ್ಯಾನ್ಕೇಕ್ ಮಿಕ್ಸ್","description":"Millet pancake mix."},
  {"id":109,"name":"Soulfull Millet Muesli","category":"cereal","price":810,"imageurl":"images\\\\Soulfull Millet Muesli 810.jpg","nameKn":"ಸೋಲ್ಫುಲ್ ಮಿಲೆಟ್ ಮೂಸ್ಲಿ","description":"Millet based muesli."},
  {"id":110,"name":"Soulfull Ragi Bites","category":"cereal","price":60,"imageurl":"images\\\\Soulfull Ragi Bites 60.jpg","nameKn":"ಸೋಲ್ಫುಲ್ ರಾಗಿ ಬೈಟ್ಸ್","description":"Ragi bites."},
  {"id":111,"name":"Tata Gluco Plus Drink","category":"drinks","price":204,"imageurl":"images\\\\Tata Gluco Plus Drink 204.jpg","nameKn":"ಟಾಟಾ ಗ್ಲೂಕೋ ಪ್ಲಸ್ ಡ್ರಿಂಕ್","description":"Energy drink."},
  {"id":112,"name":"Tropicana Apple Juice","category":"drinks","price":100,"imageurl":"images\\\\Tropicana Apple Juice 100.jpg","nameKn":"ಟ್ರೋಪಿಕಾನಾ ಸೇಬಿನ ರಸ","description":"Apple juice."},
  {"id":113,"name":"True Elements Muesli","category":"cereal","price":485,"imageurl":"images\\\\True Elements Muesli 485.jpg","nameKn":"ಟ್ರೂ ಎಲಿಮೆಂಟ್ಸ್ ಮೂಸ್ಲಿ","description":"Healthy muesli."},
  {"id":114,"name":"Whole wheat flour","category":"flour","price":750,"imageurl":"images\\\\Whole wheat flour 750.jpg","nameKn":"ಗೋಧಿ ಹಿಟ್ಟು","description":"Whole wheat flour."},
  {"id":115,"name":"Yoga Bar Dark Chocolate Muesli","category":"cereal","price":750,"imageurl":"images\\\\Yoga Bar Dark Chocolate Muesli 160.jpg","nameKn":"ಯೋಗಾ ಬಾರ್ ಡಾರ್ಕ್ ಚಾಕೊಲೇಟ್ ಮೂಸ್ಲಿ","description":"Dark chocolate muesli."},
  {"id":116,"name":"Yoga Bar Muesli","category":"cereal","price":200,"imageurl":"images\\\\Yoga Bar Muesli 190.jpg","nameKn":"ಯೋಗಾ ಬಾರ್ ಮೂಸ್ಲಿ","description":"Protein rich muesli."}
]
JSON;
    $data = json_decode($json, true);
    if (!is_array($data)) {
      return [];
    }
    $result = [];
    foreach ($data as $item) {
      $subcat = $item['category'] ?? 'cereal';
      if ($subcat === 'cereal' || $subcat === 'grains' || $subcat === 'flour' || $subcat === 'sweetener') {
        $subcat = 'cereal';
      } elseif ($subcat === 'pancake' || $subcat === 'syrup' || $subcat === 'breakfast') {
        $subcat = 'pancake';
      } elseif ($subcat === 'eggs') {
        $subcat = 'eggs';
      } elseif ($subcat === 'drinks') {
        $subcat = 'drinks';
      } else {
        $subcat = 'cereal';
      }
      $result[] = [
        'id' => $item['id'],
        'name' => $item['name'],
        'kn_name' => $item['nameKn'] ?? $item['name'],
        'price' => $item['price'],
        'category' => $subcat,
        'emoji' => '',
        'description_en' => $item['description'] ?? 'Delicious breakfast item',
        'imageurl' => normalizeImagePath($item['imageurl'] ?? '')
      ];
    }
    $fallback = $result;
    return applySearch($fallback, $search);
  }
  function applySearch(array $products, string $search): array
  {
    if (empty($search)) return $products;
    $searchLower = strtolower($search);
    return array_filter($products, function ($p) use ($searchLower) {
      return strpos(strtolower($p['name']), $searchLower) !== false ||
        strpos(strtolower($p['kn_name']), $searchLower) !== false ||
        strpos(strtolower($p['description_en']), $searchLower) !== false;
    });
  }
  // Get products (breakfast foods category)
  $products = getBreakfastProducts();
  // If no products from DB, use fallback
  if (empty($products)) {
    $products = getFallbackProducts();
  }
  // Get cart count and total
  $cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
  $cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
  ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>Breakfast Bliss · ಬೆಳಗಿನ ತಿಂಡಿ · Fresh Morning Bites</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <style>
    /* ===== WARM MINIMALISM DESIGN - BREAKFAST EDITION ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      background: #fcf9f5;
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
      background: linear-gradient(135deg, #fff7eb 0%, #ffe9d0 100%);
      border-radius: 24px;
      border: 1px solid #ffd8b1;
      position: relative;
      overflow: hidden;
    }

    .category-header::before {
      content: '☀️';
      position: absolute;
      font-size: 8rem;
      opacity: 0.1;
      right: 5%;
      top: -20px;
    }

    .category-header h1 {
      font-size: 3rem;
      font-weight: 800;
      color: #7c3a00;
      margin-bottom: 12px;
      letter-spacing: -0.02em;
    }

    .category-header p {
      font-size: 1.15rem;
      color: #a65811;
      font-weight: 500;
    }

    .search-panel {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 32px;
    }

    .search-bar {
      background: #fffcf9;
      border-radius: 70px;
      padding: 12px 28px;
      display: flex;
      align-items: center;
      border: 1px solid #f6d1ad;
      gap: 12px;
      margin-bottom: 28px;
      box-shadow: 0 8px 20px rgba(220, 150, 80, 0.08);
      width: 100%;
      max-width: 650px;
      transition: all 0.3s;
    }

    .search-bar:focus-within {
      border-color: #d97706;
      box-shadow: 0 8px 24px rgba(217, 119, 6, 0.15);
    }

    .search-bar i {
      font-size: 1.3rem;
      color: #d97706;
    }

    .search-bar input {
      flex: 1;
      border: none;
      padding: 6px 0;
      background: transparent;
      font-size: 1.1rem;
      outline: none;
      color: #432100;
      font-weight: 500;
    }

    .search-bar input::placeholder {
      color: #b78351;
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
      background: #fffcf9;
      padding: 12px 28px;
      border-radius: 60px;
      font-weight: 600;
      font-size: 0.95rem;
      color: #814611;
      cursor: pointer;
      white-space: nowrap;
      border: 1px solid #f6d1ad;
      transition: all 0.3s ease;
    }

    .pill:hover {
      background: #ffebd6;
      transform: translateY(-2px);
    }

    .pill.active {
      background: #d97706;
      color: white;
      border-color: #d97706;
      box-shadow: 0 8px 16px rgba(217, 119, 6, 0.3);
    }

    .top-picks {
      margin: 32px 0 40px;
      background: linear-gradient(135deg, #fffcf9 0%, #fff4e6 100%);
      border-radius: 36px;
      padding: 36px;
      border: 1px solid #f6d1ad;
      box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.8);
    }

    .top-picks h3 {
      font-size: 1.8rem;
      font-weight: 800;
      color: #7c3a00;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .top-picks h3 i {
      color: #d97706;
      font-size: 2.2rem;
    }

    .top-picks-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 24px;
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 32px;
      margin: 32px 0 48px;
    }

    .product-card,
    .top-pick-card {
      background: white;
      border-radius: 28px;
      padding: 24px 20px;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      border: 1px solid #f6eedf;
      box-shadow: 0 8px 24px rgba(220, 150, 80, 0.06);
      display: flex;
      flex-direction: column;
      position: relative;
      align-items: center;
      text-align: center;
    }

    .product-card:hover,
    .top-pick-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(220, 150, 80, 0.12);
      border-color: #f6d1ad;
    }

    .product-img,
    .top-pick-img {
      width: 130px;
      height: 130px;
      border-radius: 32px;
      background: #fffaf5;
      border: 2px solid #ffeedb;
      margin: 0 auto 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      padding: 4px;
    }

    .product-img img,
    .top-pick-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 26px;
    }

    .product-title {
      font-weight: 800;
      font-size: 1.25rem;
      color: #3a1e00;
      line-height: 1.3;
      margin-bottom: 4px;
    }

    .top-pick-name {
      font-weight: 800;
      font-size: 1.1rem;
      color: #3a1e00;
      line-height: 1.3;
      margin-bottom: 6px;
    }

    .product-title-kn {
      font-size: 0.9rem;
      color: #a46220;
      margin: 0 0 14px;
      font-weight: 600;
    }

    .product-description {
      font-size: 0.85rem;
      background: #fffaf5;
      padding: 10px 14px;
      border-radius: 12px;
      color: #814611;
      margin: 8px 0 16px;
      line-height: 1.5;
      border-left: 4px solid #f3a34a;
      width: 100%;
      text-align: left;
    }

    .variant-pill {
      font-size: 0.85rem;
      font-weight: 700;
      color: #814611;
      background: #fffcf9;
      border: 1px solid #f6d1ad;
      border-radius: 50px;
      padding: 8px 16px;
      margin: 4px 0 16px;
      display: inline-block;
      width: 100%;
      max-width: 180px;
    }

    .price-row {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-top: auto;
      width: 100%;
    }

    .price {
      font-weight: 800;
      font-size: 1.6rem;
      color: #d97706;
      letter-spacing: -0.02em;
      margin-bottom: 16px;
    }

    .top-pick-price {
      font-size: 1.3rem;
      font-weight: 800;
      color: #d97706;
      margin: 10px 0 16px;
    }

    .descriptive-cart-btn,
    .top-pick-btn {
      background: #d97706;
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
      box-shadow: 0 6px 16px rgba(217, 119, 6, 0.3);
      outline: none;
    }

    .descriptive-cart-btn:hover,
    .top-pick-btn:hover {
      background: #b86202;
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(217, 119, 6, 0.4);
    }

    .descriptive-cart-btn:active,
    .top-pick-btn:active {
      transform: translateY(0);
      box-shadow: 0 4px 10px rgba(217, 119, 6, 0.3);
    }

    footer {
      text-align: center;
      font-size: 0.85rem;
      color: #b9a690;
      margin-top: 40px;
      padding-top: 24px;
      border-top: 1px solid #f0e3d7;
    }

    .design-credits {
      margin-top: 24px;
      text-align: center;
      font-size: 0.8rem;
      color: #a68465;
      background: #fffaf5;
      padding: 12px;
      border-radius: 12px;
      display: inline-block;
    }

    .design-credits a {
      color: #d97706;
      font-weight: 600;
      text-decoration: none;
      transition: 0.2s;
    }

    .design-credits a:hover {
      color: #b86202;
      text-decoration: underline;
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

      .search-bar {
        padding: 10px 20px;
        border-radius: 20px;
      }

      .filter-pills {
        justify-content: flex-start;
        padding-left: 4px;
        padding-right: 4px;
      }

      .top-picks {
        padding: 24px 16px;
        border-radius: 24px;
      }

      .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 16px;
      }

      .product-card {
        padding: 16px 12px;
        border-radius: 20px;
      }

      .product-img {
        width: 100px;
        height: 100px;
      }

      .product-title {
        font-size: 1.1rem;
      }

      .descriptive-cart-btn {
        padding: 12px;
        font-size: 0.95rem;
      }

      .price {
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

    <div class="search-panel">
      <div class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
      </div>
      <div class="filter-pills" id="filterPills">
        <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
        <div class="pill" data-category="cereal">Cereals / ಧಾನ್ಯಗಳು</div>
        <div class="pill" data-category="pancake">Pancakes / ಪ್ಯಾನ್ಕೇಕ್</div>
        <div class="pill" data-category="eggs">Eggs / ಮೊಟ್ಟೆ</div>
        <div class="pill" data-category="drinks">Drinks / ಪಾನೀಯ</div>
      </div>
    </div>
    <div class="top-picks">
      <h3><i class="fas fa-fire"></i> Top Picks · Bestsellers</h3>
      <div class="top-picks-grid" id="topPicksGrid"></div>
    </div>
    <div class="product-grid" id="productsGrid"></div>

  </div>
  </div>
  <script>
    (function() {
      // ============================================================
      // PRODUCT DATA FROM PHP (with images)
      // ============================================================
      const products = <?php
                        $productArray = [];
                        foreach ($products as $p) {
                          $subcat = $p['category'] ?? 'cereal';
                          $img = isset($p['imageurl']) ? $p['imageurl'] : '';
                          $productArray[] = [
                            'id' => $p['id'],
                            'name' => $p['name'],
                            'nameKn' => $p['kn_name'] ?? '',
                            'price' => $p['price'],
                            'category' => $subcat,
                            'description' => $p['description_en'] ?? 'Delicious breakfast item',
                            'image' => $img
                          ];
                        }
                        echo json_encode($productArray);
                        ?>;
      // ============================================================
      // CART STATE
      // ============================================================
      let cart = [];
      const basePath = 'breakfast.php';
      // DOM refs
      const productGrid = document.getElementById('productsGrid');
      const topPicksGrid = document.getElementById('topPicksGrid');
      const cartCountSpan = document.getElementById('cartCount');
      const cartSidebar = document.getElementById('cartSidebar');
      const overlay = document.getElementById('overlay');
      const closeCartBtn = document.getElementById('closeCart');
      const continueShoppingBtn = document.getElementById('continueShopping');
      const checkoutBtn = document.getElementById('checkoutBtn');
      const cartItemsContainer = document.getElementById('cartItems');
      const totalPriceSpan = document.querySelector('.total-price');
      const searchInput = document.getElementById('searchInput');
      const filterPills = document.querySelectorAll('.pill');
      const cartLink = document.getElementById('cartLink');
      let activeCategory = 'all';
      let searchTerm = '';
      // ============================================================
      // CART FUNCTIONS
      // ============================================================
      function loadCart() {
        fetch('../index.php?ajax=cart')
          .then(res => res.json())
          .then(data => {
            cart = data;
            updateCartCount();
            if (cartSidebar.classList.contains('open')) renderCartItems();
          })
          .catch(console.error);
      }

      function updateCartCount() {
        const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
        cartCountSpan.innerText = totalQty;
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
              badges.forEach(b => {
                b.innerText = cartArr.reduce((acc, i) => acc + (i.qty || 1), 0);
              });

              // Visual feedback on the button itself for multiple clicks
              const btn = document.querySelector(`button.descriptive-cart-btn[data-id="${productId}"]`) || document.querySelector(`button.top-pick-btn[onclick="window.addToCart(${productId})"]`);
              if (btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Added';
                btn.style.backgroundColor = '#16a34a';
                setTimeout(() => {
                  btn.innerHTML = originalHtml;
                  btn.style.backgroundColor = '';
                }, 2000);
              }

              // Re-sync background cart but don't slide drawer over screen
              if (typeof loadGlobalCart === "function") {
                loadGlobalCart();
                if (typeof openCart === 'function') openCart();
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
      // Inlined Cart Management Removed - Relies on global cart-drawer.php
      window.addToCart = addToCart;
      // ============================================================
      // RENDER PRODUCTS
      // ============================================================
      function renderProducts() {
        let filtered = products.filter(p => {
          if (activeCategory !== 'all' && p.category !== activeCategory) return false;
          if (searchTerm && !p.name.toLowerCase().includes(searchTerm) && !p.nameKn.toLowerCase().includes(searchTerm)) return false;
          return true;
        });
        if (!filtered.length) {
          productGrid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No breakfast items found ✨</div>`;
          return;
        }
        productGrid.innerHTML = filtered.map(p => `
      <div class="product-card">
        <div class="product-img">
          ${p.image ? `<img src="${p.image}" alt="" onerror="this.style.display='none'">` : `<i class="fas fa-utensils" style="font-size:2.5rem;color:#ccc;"></i>`}
        </div>
        <div class="product-title">${p.name}</div>
        <div class="product-title-kn">${p.nameKn || ''}</div>
        <div class="product-description">${p.description}</div>
        <div class="price">₹${parseFloat(p.price).toFixed(2)}</div>
        <button class="descriptive-cart-btn" data-id="${p.id}" onclick="window.addToCart(${p.id})"><i class="fas fa-cart-plus"></i> <span style="font-weight:700;">Add to Cart</span></button>
      </div>
    `).join('');
      }
      // ============================================================
      // RENDER TOP PICKS
      // ============================================================
      function renderTopPicks() {
        const featuredProds = products.filter(p => p.id % 2 === 1).slice(0, 6);
        if (!featuredProds.length) {
          topPicksGrid.innerHTML = '<p>No top picks available</p>';
          return;
        }
        topPicksGrid.innerHTML = featuredProds.map(p => `
      <div class="top-pick-card">
        <div class="top-pick-img">
          ${p.image ? `<img src="${p.image}" alt="" onerror="this.style.display='none'" style="width:100%;height:100%;object-fit:cover;border-radius:24px;">` : `<i class="fas fa-utensils" style="font-size:3rem;color:#ccc;"></i>`}
        </div>
        <div class="top-pick-name">${p.name}</div>
        <div class="top-pick-price">₹${parseFloat(p.price).toFixed(2)}</div>
        <button class="top-pick-btn" onclick="window.addToCart(${p.id})"><i class="fas fa-plus"></i></button>
      </div>
    `).join('');
      }
      // ============================================================
      // EVENT BINDING
      // ============================================================
      filterPills.forEach(pill => {
        pill.addEventListener('click', function() {
          filterPills.forEach(p => p.classList.remove('active'));
          this.classList.add('active');
          activeCategory = this.dataset.category;
          renderProducts();
        });
      });
      searchInput.addEventListener('input', function() {
        searchTerm = this.value.toLowerCase();
        renderProducts();
      });
      // Duplicate cart bindings removed. Handled by global cart integration
      // ============================================================
      // INIT
      // ============================================================
      // loadCart(); Removed
      renderTopPicks();
      renderProducts();
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
            window.showFastCartToast ? window.showFastCartToast('Your cart is empty! Add items to proceed.') : alert('Cart is empty');
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
</body>

</html>