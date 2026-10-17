<?php
// ============================================================
// kitchen.php - Dynamic Kitchenware product page (FIXED)
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
            $res = getKitchenProducts($search);
            if (empty($res)) {
                $res = getFallbackKitchenProducts();
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
                if (!$product) {
                    // Fallback to static data
                    $fallback = getFallbackKitchenProducts();
                    foreach ($fallback as $fp) {
                        if ($fp['id'] == $id) {
                            $product = [
                                'id' => $fp['id'],
                                'name' => $fp['name'],
                                'kn_name' => $fp['kn_name'] ?? $fp['name'],
                                'price' => $fp['price'],
                                'icon' => 'fa-utensils'
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
                            'icon' => $product['icon'] ?? 'fa-utensils'
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
// FETCH KITCHEN PRODUCTS FROM DATABASE (PDO)
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
function getKitchenProducts(string $search = ''): array
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
        $whereCategory = $hasSlug ? "(c.slug = 'kitchenware' OR c.id = 'kitchenware' OR c.name LIKE '%kitchen%')" : "(c.name LIKE '%kitchen%')";
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
            $row['description_en'] = $row['description_en'] ?? 'Quality kitchenware';
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
// FALLBACK: If no products from DB, load static data from JSON
// ============================================================
function getFallbackKitchenProducts()
{
    static $fallback = null;
    if ($fallback !== null) {
        return $fallback;
    }
    $jsonPath = __DIR__ . '/kitchen-fallback.json';
    if (file_exists($jsonPath)) {
        $fallback = json_decode(file_get_contents($jsonPath), true);
        if (is_array($fallback)) {
            return $fallback;
        }
    }
    $json = '[
  {"id":1,"nameEn":"Stainless Steel Frying Pan 20cm","category":"cookware","price":1260,"imageUrl":"images\\\\Stainless Steel Frying Pan 20cm 1260.jpg","nameKn":"ಸ್ಟೀಲ್ ಫ್ರೈ ಪ್ಯಾನ್ 20 ಸೆಂ","description":"20cm stainless steel, riveted handle."},
  {"id":2,"nameEn":"Stainless Steel Frying Pan 25cm","category":"cookware","price":1410,"imageUrl":"images\\\\Stainless Steel Frying Pan 25cm 1410.jpg","nameKn":"ಸ್ಟೀಲ್ ಫ್ರೈ ಪ್ಯಾನ್ 25 ಸೆಂ","description":"25cm, tri‑ply bottom for even heat."},
  {"id":3,"nameEn":"Non-stick Frying Pan 22cm","category":"cookware","price":720,"imageUrl":"images\\\\Non-stick Frying Pan 22cm 880.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಫ್ರೈ ಪ್ಯಾನ್ 22 ಸೆಂ","description":"PFOA‑free non‑stick coating."},
  {"id":4,"nameEn":"Non-stick Frying Pan 28cm","category":"cookware","price":920,"imageUrl":"images\\\\Non-stick Frying Pan 28cm 600.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಫ್ರೈ ಪ್ಯಾನ್ 28 ಸೆಂ","description":"Large family size, easy to clean."},
  {"id":5,"nameEn":"Aluminum Sauce Pan 1 L","category":"cookware","price":420,"imageUrl":"images\\\\Aluminum Sauce Pan 1 L 400.jpg","nameKn":"ಅಲ್ಯೂಮಿನಿಯಂ ಸಾಸ್ ಪ್ಯಾನ್ 1 ಲೀ","description":"Lightweight aluminium, good heat conductor."},
  {"id":6,"nameEn":"Aluminum Sauce Pan 2 L","category":"cookware","price":1180,"imageUrl":"images\\\\Aluminum Sauce Pan 2 L 1180.jpg","nameKn":"ಅಲ್ಯೂಮಿನಿಯಂ ಸಾಸ್ ಪ್ಯಾನ್ 2 ಲೀ","description":"2 litre capacity, ideal for curries."},
  {"id":7,"nameEn":"Stainless Steel Sauce Pan 1.5 L","category":"cookware","price":1220,"imageUrl":"images\\\\Stainless Steel Sauce Pan 1.5 L 1220.jpg","nameKn":"ಸ್ಟೀಲ್ ಸಾಸ್ ಪ್ಯಾನ್ 1.5 ಲೀ","description":"Sturdy, corrosion resistant."},
  {"id":8,"nameEn":"Stainless Steel Sauce Pan 3 L","category":"cookware","price":2500,"imageUrl":"images\\\\Stainless Steel Sauce Pan 3 L 2500.jpg","nameKn":"ಸ್ಟೀಲ್ ಸಾಸ್ ಪ್ಯಾನ್ 3 ಲೀ","description":"3L, with glass lid."},
  {"id":9,"nameEn":"Non-stick Cooking Pot 3 L","category":"cookware","price":999,"imageUrl":"images\\\\Non-stick Cooking Pot 3 L 999.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಕುಕ್ ಪಾಟ್ 3 ಲೀ","description":"Non‑stick interior, easy pour spout."},
  {"id":10,"nameEn":"Stainless Steel Cooking Pot 5 L","category":"cookware","price":2000,"imageUrl":"images\\\\Stainless Steel Cooking Pot 5 L 1995.jpg","nameKn":"ಸ್ಟೀಲ್ ಕುಕ್ ಪಾಟ್ 5 ಲೀ","description":"Large capacity for soups and stocks."},
  {"id":11,"nameEn":"Pressure Cooker 3 L","category":"cookware","price":1450,"imageUrl":"images\\\\Pressure Cooker 3 L 1450.jpg","nameKn":"ಪ್ರೆಶರ್ ಕುಕ್ಕರ್ 3 ಲೀ","description":"3 litre, safety valve, fast cooking."},
  {"id":12,"nameEn":"Pressure Cooker 5 L","category":"cookware","price":2100,"imageUrl":"images\\\\Pressure Cooker 5 L 2045.jpg","nameKn":"ಪ್ರೆಶರ್ ಕುಕ್ಕರ್ 5 ಲೀ","description":"5 litre, for larger families."},
  {"id":49,"nameEn":"Stainless Steel Casserole 2 L","category":"cookware","price":900,"imageUrl":"images\\\\Stainless Steel Casserole 2 L 600.jpg","nameKn":"ಸ್ಟೀಲ್ ಕ್ಯಾಸರೋಲ್ 2ಲೀ","description":"2L casserole, oven safe."},
  {"id":50,"nameEn":"Stainless Steel Casserole 5 L","category":"cookware","price":2100,"imageUrl":"images\\\\Stainless Steel Casserole 5 L 2100.jpg","nameKn":"ಸ್ಟೀಲ್ ಕ್ಯಾಸರೋಲ್ 5ಲೀ","description":"5L, perfect for parties."},
  {"id":51,"nameEn":"Non-stick Sauce Pan 1 L","category":"cookware","price":700,"imageUrl":"images\\\\Non-stick Sauce Pan 1 L 589.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಸಾಸ್ ಪ್ಯಾನ್ 1ಲೀ","description":"Small saucepan, non‑stick."},
  {"id":52,"nameEn":"Non-stick Sauce Pan 2 L","category":"cookware","price":1190,"imageUrl":"images\\\\Non-stick Sauce Pan 2 L 1199.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಸಾಸ್ ಪ್ಯಾನ್ 2ಲೀ","description":"2 litre, non‑stick, easy clean."},
  {"id":53,"nameEn":"Non-stick Cooking Pot 5 L","category":"cookware","price":1100,"imageUrl":"images\\\\Non-stick Cooking Pot 5 L 1089.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಕುಕ್ ಪಾಟ್ 5ಲೀ","description":"Large non‑stick pot."},
  {"id":54,"nameEn":"Aluminum Frying Pan 22 cm","category":"cookware","price":1420,"imageUrl":"images\\\\Aluminum Frying Pan 22 cm 1400.jpg","nameKn":"ಅಲ್ಯೂ. ಫ್ರೈ ಪ್ಯಾನ್ 22 ಸೆಂ","description":"Lightweight aluminium pan."},
  {"id":55,"nameEn":"Aluminum Frying Pan 28 cm","category":"cookware","price":620,"imageUrl":"images\\\\Aluminum Frying Pan 28 cm 600.jpg","nameKn":"ಅಲ್ಯೂ. ಫ್ರೈ ಪ್ಯಾನ್ 28 ಸೆಂ","description":"28cm, budget friendly."},
  {"id":56,"nameEn":"Stainless Steel Kadhai 2 L","category":"cookware","price":720,"imageUrl":"images\\\\Stainless Steel Kadhai 2 L 600.jpg","nameKn":"ಸ್ಟೀಲ್ ಕಡಾಯಿ 2ಲೀ","description":"Traditional kadhai, stainless steel."},
  {"id":57,"nameEn":"Stainless Steel Kadhai 3 L","category":"cookware","price":990,"imageUrl":"images\\\\Stainless Steel Kadhai 3 L 999.jpg","nameKn":"ಸ್ಟೀಲ್ ಕಡಾಯಿ 3ಲೀ","description":"3L, deep and wide."},
  {"id":58,"nameEn":"Non-stick Kadhai 2 L","category":"cookware","price":820,"imageUrl":"images\\\\Non-stick Kadhai 2 L 700.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಕಡಾಯಿ 2ಲೀ","description":"Non‑stick kadhai, less oil."},
  {"id":59,"nameEn":"Non-stick Kadhai 3 L","category":"cookware","price":1120,"imageUrl":"images\\\\Non-stick Kadhai 3 L 2400.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಕಡಾಯಿ 3ಲೀ","description":"3L non‑stick, ideal for biryani."},
  {"id":60,"nameEn":"Stainless Steel Fry Pan 30 cm","category":"cookware","price":920,"imageUrl":"images\\\\Stainless Steel Fry Pan 30 cm 1960.jpg","nameKn":"ಸ್ಟೀಲ್ ಫ್ರೈ ಪ್ಯಾನ್ 30 ಸೆಂ","description":"30cm, professional grade."},
  {"id":61,"nameEn":"Non-stick Griddle / Tawa 28 cm","category":"cookware","price":999,"imageUrl":"images\\\\Non-stick Griddle  Tawa 28 cm 999.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ತವಾ 28 ಸೆಂ","description":"Flat tawa for dosa, roti."},
  {"id":62,"nameEn":"Aluminum Griddle / Tawa 25 cm","category":"cookware","price":730,"imageUrl":"images\\\\Aluminum Griddle Tawa 25 cm 730.jpg","nameKn":"ಅಲ್ಯೂ. ತವಾ 25 ಸೆಂ","description":"Aluminium tawa, lightweight."},
  {"id":95,"nameEn":"Casserole Aluminium 2 L","category":"cookware","price":620,"imageUrl":"images\\\\Casserole Aluminium 2 L 460.jpg","nameKn":"ಅಲ್ಯೂ. ಕ್ಯಾಸರೋಲ್ 2ಲೀ","description":"Aluminium casserole, 2L."},
  {"id":96,"nameEn":"Casserole Aluminium 5 L","category":"cookware","price":2210,"imageUrl":"images\\\\Casserole Aluminium 5 L 2210.jpg","nameKn":"ಅಲ್ಯೂ. ಕ್ಯಾಸರೋಲ್ 5ಲೀ","description":"5L, heavy duty."},
  {"id":13,"nameEn":"Baking Tray Rectangular 9 inch","category":"bakeware","price":360,"imageUrl":"images\\\\Baking Tray Rectangular 9 inch 350.jpg","nameKn":"ಬೇಕಿಂಗ್ ಟ್ರೇ 9\"","description":"Non‑stick, rust resistant."},
  {"id":14,"nameEn":"Baking Tray Rectangular 12 inch","category":"bakeware","price":520,"imageUrl":"images\\\\Baking Tray Rectangular 12 inch 495.jpg","nameKn":"ಬೇಕಿಂಗ್ ಟ್ರೇ 12\"","description":"Large tray for cookies."},
  {"id":15,"nameEn":"Muffin Pan 6 Cup","category":"bakeware","price":420,"imageUrl":"images\\\\Muffin Pan 6 Cup 700.jpg","nameKn":"ಮಫಿನ್ ಪ್ಯಾನ್ ಕಪ್","description":"6 cup muffin pan."},
  {"id":16,"nameEn":"Baking Sheet Non-stick 20x30cm","category":"bakeware","price":520,"imageUrl":"images\\\\Baking Sheet Non-stick 20x30cm.jpg","nameKn":"ಬೇಕಿಂಗ್ ಶೀಟ್","description":"20x30cm, non‑stick."},
  {"id":18,"nameEn":"Round Cake Pan 10 inch","category":"bakeware","price":520,"imageUrl":"images\\\\Round Cake Pan 10 inch 500.jpg","nameKn":"ಕೇಕ್ ಪ್ಯಾನ್ 10\"","description":"Round cake pan, 10 inch."},
  {"id":19,"nameEn":"Baking Sheet Non-stick 30x40cm","category":"bakeware","price":999,"imageUrl":"images\\\\Baking Sheet Non-stick 30x40cm 999.jpg","nameKn":"ಬೇಕಿಂಗ್ ಶೀಟ್","description":"30x40cm, extra large."},
  {"id":42,"nameEn":"Measuring Cup Set 4 pcs","category":"bakeware","price":520,"imageUrl":"images\\\\Measuring Cup Set 4 pcs 500.jpg","nameKn":"ಅಳತೆ ಕಪ್ ಸೆಟ್","description":"Nesting cups, accurate."},
  {"id":43,"nameEn":"Measuring Spoon Set 4 pcs","category":"bakeware","price":660,"imageUrl":"images\\\\Measuring Spoon Set 4 pcs 600.jpg","nameKn":"ಅಳತೆ ಚಮಚ ಸೆಟ್","description":"Stainless steel spoons."},
  {"id":63,"nameEn":"Baking Sheet 30x30 cm","category":"bakeware","price":520,"imageUrl":"images\\\\Baking Sheet 30x30 cm 500.jpg","nameKn":"ಬೇಕಿಂಗ್ ಶೀಟ್ 30x30","description":"Square baking sheet."},
  {"id":64,"nameEn":"Round Cake Pan 6 inch","category":"bakeware","price":600,"imageUrl":"images\\\\Round Cake Pan 6 inch 600.jpg","nameKn":"ಕೇಕ್ ಪ್ಯಾನ್ 6\"","description":"Small cake pan."},
  {"id":65,"nameEn":"Round Cake Pan 12 inch","category":"bakeware","price":620,"imageUrl":"images\\\\Round Cake Pan 12 inch 600.jpg","nameKn":"ಕೇಕ್ ಪ್ಯಾನ್ 12\"","description":"Large party cake pan."},
  {"id":67,"nameEn":"Loaf Pan 9 inch","category":"bakeware","price":390,"imageUrl":"images\\\\Loaf Pan 9 inch 380.jpg","nameKn":"ಲೋಫ್ ಪ್ಯಾನ್ 9\"","description":"For banana bread, loaf cakes."},
  {"id":68,"nameEn":"Pie Dish 9 inch","category":"bakeware","price":500,"imageUrl":"images\\\\Pie Dish 9 inch 490.jpg","nameKn":"ಪೈ ಡಿಶ್ 9\"","description":"Fluted edge pie dish."},
  {"id":69,"nameEn":"Pizza Pan 12 inch","category":"bakeware","price":960,"imageUrl":"images\\\\Pizza Pan 12 inch 950.jpg","nameKn":"ಪಿಜ್ಜಾ ಪ್ಯಾನ್ 12\"","description":"Perforated for crisp crust."},
  {"id":70,"nameEn":"Silicone Baking Mat","category":"bakeware","price":520,"imageUrl":"images\\\\Silicone Baking Mat 490.jpg","nameKn":"ಸಿಲಿಕೋನ್ ಬೇಕಿಂಗ್ ಮ್ಯಾಟ್","description":"Reusable, non‑stick."},
  {"id":71,"nameEn":"Pastry Brush Silicone","category":"bakeware","price":500,"imageUrl":"images\\\\Pastry Brush Silicone 490.jpg","nameKn":"ಪೇಸ್ಟ್ರಿ ಬ್ರಷ್","description":"Heat resistant silicone."},
  {"id":97,"nameEn":"Square Baking Pan 8 inch","category":"bakeware","price":220,"imageUrl":"images\\\\Square Baking Pan 8 inch 200.jpg","nameKn":"ಚೌಕ ಬೇಕಿಂಗ್ ಪ್ಯಾನ್ 8\"","description":"Square pan, non‑stick."},
  {"id":98,"nameEn":"Square Baking Pan 10 inch","category":"bakeware","price":580,"imageUrl":"images\\\\Square Baking Pan 10 inch 524.jpg","nameKn":"ಚೌಕ ಬೇಕಿಂಗ್ ಪ್ಯಾನ್ 10\"","description":"10 inch square."},
  {"id":99,"nameEn":"Loaf Pan 12 inch","category":"bakeware","price":520,"imageUrl":"images\\\\Loaf Pan 12 inch 480.jpg","nameKn":"ಲೋಫ್ ಪ್ಯಾನ್ 12\"","description":"Extra long loaf pan."},
  {"id":100,"nameEn":"Tart Pan 9 inch","category":"bakeware","price":720,"imageUrl":"images\\\\Tart Pan 9 inch 700.jpg","nameKn":"ಟಾರ್ಟ್ ಪ್ಯಾನ್ 9\"","description":"Removable bottom."},
  {"id":20,"nameEn":"Chef Knife 8 inch","category":"knives","price":820,"imageUrl":"images\\\\Chef Knife 8 inch 800.jpg","nameKn":"ಚೆಫ್ ಚಾಕು 8\"","description":"High‑carbon stainless, ergonomic handle."},
  {"id":21,"nameEn":"Utility Knife 6 inch","category":"knives","price":720,"imageUrl":"images\\\\Utility Knife 6 inch 700.jpg","nameKn":"ಯುಟಿಲಿಟಿ ಚಾಕು 6\"","description":"Versatile cutting knife."},
  {"id":22,"nameEn":"Bread Knife 8 inch","category":"knives","price":1700,"imageUrl":"images\\\\Bread Knife 8 inch 1700.jpg","nameKn":"ಬ್ರೆಡ್ ಚಾಕು 8\"","description":"Serrated blade for bread."},
  {"id":23,"nameEn":"Paring Knife 4 inch","category":"knives","price":400,"imageUrl":"images\\\\Paring Knife 4 inch 400.jpg","nameKn":"ಪ್ಯಾರಿಂಗ್ ಚಾಕು 4\"","description":"Precision peeling knife."},
  {"id":24,"nameEn":"Kitchen Knife Set 3 pcs","category":"knives","price":720,"imageUrl":"images\\\\Kitchen Knife Set 3 pcs 600.jpg","nameKn":"ಚಾಕು ಸೆಟ್ 3 ಪಿ","description":"Chef, utility, paring."},
  {"id":72,"nameEn":"Chef Knife 10 inch","category":"knives","price":490,"imageUrl":"images\\\\Chef Knife 10 inch 490.jpg","nameKn":"ಚೆಫ್ ಚಾಕು 10\"","description":"Large chef knife."},
  {"id":73,"nameEn":"Knife Set 5 pcs","category":"knives","price":990,"imageUrl":"images\\\\Knife Set 5 pcs 900.jpg","nameKn":"ಚಾಕು ಸೆಟ್ 5 ಪಿ","description":"Complete knife set with block."},
  {"id":74,"nameEn":"Bread Knife 10 inch","category":"knives","price":520,"imageUrl":"images\\\\Bread Knife 10 inch 500.jpg","nameKn":"ಬ್ರೆಡ್ ಚಾಕು 10\"","description":"Extra long bread knife."},
  {"id":75,"nameEn":"Paring Knife 5 inch","category":"knives","price":490,"imageUrl":"images\\\\Paring Knife 5 inch 490.jpg","nameKn":"ಪ್ಯಾರಿಂಗ್ ಚಾಕು 5\"","description":"Slightly larger paring."},
  {"id":76,"nameEn":"Utility Knife 8 inch","category":"knives","price":460,"imageUrl":"images\\\\Utility Knife 8 inch 450.jpg","nameKn":"ಯುಟಿಲಿಟಿ ಚಾಕು 8\"","description":"Multipurpose utility."},
  {"id":77,"nameEn":"Knife Sharpener Manual","category":"knives","price":520,"imageUrl":"images\\\\Knife Sharpener Manual 500.jpg","nameKn":"ಚಾಕು ಶಾರ್ಪನರ್","description":"Two‑stage sharpener."},
  {"id":25,"nameEn":"Ladle Stainless Steel","category":"utensils","price":190,"imageUrl":"images\\\\Ladle Stainless Steel 190.jpg","nameKn":"ಸ್ಟೀಲ್ ಲ್ಯಾಡಲ್","description":"Deep bowl, long handle."},
  {"id":26,"nameEn":"Slotted Spoon Stainless Steel","category":"utensils","price":180,"imageUrl":"images\\\\Slotted Spoon Stainless Steel 180.jpg","nameKn":"ಸ್ಲಾಟೆಡ್ ಸ್ಪೂನ್","description":"Drains liquids easily."},
  {"id":27,"nameEn":"Spatula Nylon","category":"utensils","price":150,"imageUrl":"images\\\\Spatula Nylon 150.jpg","nameKn":"ನೈಲಾನ್ ಸ್ಪಾಟುಲಾ","description":"Heat‑resistant, won\'t scratch."},
  {"id":28,"nameEn":"Soup Ladle Stainless Steel","category":"utensils","price":350,"imageUrl":"images\\\\Soup Ladle Stainless Steel 350.jpg","nameKn":"ಸ್ಟೀಲ್ ಸೂಪ್ ಲ್ಯಾಡಲ್","description":"Large capacity."},
  {"id":29,"nameEn":"Serving Spoon Stainless Steel","category":"utensils","price":1110,"imageUrl":"images\\\\Serving Spoon Stainless Steel 1110.jpg","nameKn":"ಸ್ಟೀಲ್ ಸರ್ವಿಂಗ್ ಸ್ಪೂನ್","description":"Elegant serving."},
  {"id":30,"nameEn":"Tongs Stainless Steel","category":"utensils","price":1000,"imageUrl":"images\\\\Tongs Stainless Steel 1000.jpg","nameKn":"ಸ್ಟೀಲ್ ಟಾಂಗ್ಸ್","description":"Scalloped edges, locking."},
  {"id":31,"nameEn":"Kitchen Whisk Stainless Steel","category":"utensils","price":480,"imageUrl":"images\\\\Kitchen Whisk Stainless Steel 480.jpg","nameKn":"ಸ್ಟೀಲ್ ವಿಸ್ಕ್","description":"Balloon whisk for eggs."},
  {"id":32,"nameEn":"Rolling Pin Wooden 12 inch","category":"utensils","price":260,"imageUrl":"images\\\\Rolling Pin Wooden 12 inch 250.jpg","nameKn":"ಮರದ ಲಟ್ಟಣೆ 12\"","description":"Smooth wooden pin."},
  {"id":33,"nameEn":"Rolling Pin Wooden 18 inch","category":"utensils","price":420,"imageUrl":"images\\\\Rolling Pin Wooden 18 inch 400.jpg","nameKn":"ಮರದ ಲಟ್ಟಣೆ 18\"","description":"Long for chapatis."},
  {"id":34,"nameEn":"Pastry Brush Silicone","category":"utensils","price":490,"imageUrl":"images\\\\Pastry Brush Silicone 490.jpg","nameKn":"ಪೇಸ್ಟ್ರಿ ಬ್ರಷ್","description":"Silicone bristles."},
  {"id":35,"nameEn":"Garlic Press Stainless Steel","category":"utensils","price":990,"imageUrl":"images\\\\Garlic Press Stainless Steel.jpg","nameKn":"ಸ್ಟೀಲ್ ಗಾರ್ಲಿಕ್ ಪ್ರೆಸ್","description":"Easy squeeze, easy clean."},
  {"id":36,"nameEn":"Kitchen Strainer Stainless Steel","category":"utensils","price":280,"imageUrl":"images\\\\Kitchen Strainer Stainless Steel 250.jpg","nameKn":"ಸ್ಟ್ರೈನರ್","description":"Fine mesh."},
  {"id":37,"nameEn":"Colander Stainless Steel","category":"utensils","price":1010,"imageUrl":"images\\\\Colander Stainless Steel 999.jpg","nameKn":"ಕೋಲಾಂಡರ್","description":"Large holes, sturdy."},
  {"id":38,"nameEn":"Vegetable Chopper Manual","category":"utensils","price":220,"imageUrl":"images\\\\Vegetable Chopper Manual 160.jpg","nameKn":"ತರಕಾರಿ ಕಟ್ಟರ್","description":"Multi‑blade chopper."},
  {"id":39,"nameEn":"Fruit Juicer Plastic","category":"utensils","price":620,"imageUrl":"images\\\\Fruit Juicer Plastic 600.jpg","nameKn":"ಹಣ್ಣಿನ ಜ್ಯೂಸರ್","description":"Manual citrus juicer."},
  {"id":40,"nameEn":"Citrus Squeezer Aluminum","category":"utensils","price":1500,"imageUrl":"images\\\\Citrus Squeezer Aluminum 1500.jpg","nameKn":"ಸಿಟ್ರಸ್ ಸ್ಕ್ವೀಜರ್","description":"Heavy duty."},
  {"id":41,"nameEn":"Grater Stainless Steel","category":"utensils","price":320,"imageUrl":"images\\\\Grater Stainless Steel 300.jpg","nameKn":"ತುರಿ ಮಣೆ","description":"4‑sided box grater."},
  {"id":78,"nameEn":"Ladle Non-stick","category":"utensils","price":200,"imageUrl":"images\\\\Ladle Non-stick 200.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಲ್ಯಾಡಲ್","description":"Non‑stick coated."},
  {"id":79,"nameEn":"Slotted Spoon Non-stick","category":"utensils","price":380,"imageUrl":"images\\\\Slotted Spoon Non-stick 380.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಸ್ಲಾಟೆಡ್ ಸ್ಪೂನ್","description":"Safe for non‑stick pans."},
  {"id":80,"nameEn":"Spatula Silicone","category":"utensils","price":900,"imageUrl":"images\\\\Spatula Silicone 900.jpg","nameKn":"ಸಿಲಿಕೋನ್ ಸ್ಪಾಟುಲಾ","description":"Flexible, heat safe."},
  {"id":81,"nameEn":"Turner Stainless Steel","category":"utensils","price":180,"imageUrl":"images\\\\Turner Stainless Steel 180.jpg","nameKn":"ಸ್ಟೀಲ್ ಟರ್ನರ್","description":"Flat for flipping."},
  {"id":82,"nameEn":"Whisk Stainless Steel","category":"utensils","price":220,"imageUrl":"images\\\\Whisk Stainless Steel 200.jpg","nameKn":"ಸ್ಟೀಲ್ ವಿಸ್ಕ್","description":"Small whisk for sauces."},
  {"id":83,"nameEn":"Tongs Non-stick","category":"utensils","price":400,"imageUrl":"images\\\\Tongs Non-stick 400.jpg","nameKn":"ನಾನ್-ಸ್ಟಿಕ್ ಟಾಂಗ್ಸ್","description":"Silicone tips."},
  {"id":84,"nameEn":"Kitchen Shears Stainless Steel","category":"utensils","price":380,"imageUrl":"images\\\\Kitchen Shears Stainless Steel 380.jpg","nameKn":"ಕಿಚನ್ ಶಿಯರ್ಸ್","description":"Multi‑purpose scissors."},
  {"id":85,"nameEn":"Vegetable Peeler","category":"utensils","price":250,"imageUrl":"images\\\\Vegetable Peeler 250.jpg","nameKn":"ತರಕಾರಿ ಸಿಪ್ಪೆ ಕಟ್ಟರ್","description":"Stainless steel blade."},
  {"id":86,"nameEn":"Garlic Press","category":"utensils","price":480,"imageUrl":"images\\\\Garlic Press 480.jpg","nameKn":"ಬೆಳ್ಳುಳ್ಳಿ ಪ್ರೆಸ್","description":"Sturdy metal press."},
  {"id":87,"nameEn":"Citrus Juicer Manual","category":"utensils","price":2500,"imageUrl":"images\\\\Citrus Juicer Manual 2500.jpg","nameKn":"ಸಿಟ್ರಸ್ ಜ್ಯೂಸರ್","description":"Reamer style."},
  {"id":88,"nameEn":"Grater Box","category":"utensils","price":360,"imageUrl":"images\\\\Grater Box 350.jpg","nameKn":"ಗ್ರೇಟರ್ ಬಾಕ್ಸ್","description":"Container catches shreds."},
  {"id":89,"nameEn":"Rolling Pin Marble 12 inch","category":"utensils","price":999,"imageUrl":"images\\\\Rolling Pin Marble 12 inch 999.jpg","nameKn":"ಮಾರ್ಬಲ್ ಲಟ್ಟಣೆ 12\"","description":"Keeps dough cool."},
  {"id":90,"nameEn":"Chakla Medium","category":"utensils","price":2020,"imageUrl":"images\\\\Chakla Medium 2000.jpg","nameKn":"ಚಕ್ಲಾ ಮಧ್ಯಮ","description":"Traditional rolling board."},
  {"id":44,"nameEn":"Oil Dispenser Glass 500 ml","category":"storage","price":515,"imageUrl":"images\\\\Oil Dispenser Glass 500 ml 515.jpg","nameKn":"ಎಣ್ಣೆ ಡಿಸ್ಪೆನ್ಸರ್ ಗಾಜು","description":"Glass bottle, drip‑free."},
  {"id":45,"nameEn":"Oil Dispenser Plastic 1 L","category":"storage","price":480,"imageUrl":"images\\\\Oil Dispenser Plastic 1 L 480.jpg","nameKn":"ಎಣ್ಣೆ ಡಿಸ್ಪೆನ್ಸರ್ ಪ್ಲಾಸ್ಟಿಕ್","description":"Plastic, 1 litre."},
  {"id":46,"nameEn":"Food Storage Container Plastic 1 L","category":"storage","price":460,"imageUrl":"images\\\\Food Storage Container Plastic 1 L 400.jpg","nameKn":"ಆಹಾರ ಸಂಗ್ರಹ 1ಲೀ","description":"Airtight lid."},
  {"id":47,"nameEn":"Food Storage Container Plastic 2 L","category":"storage","price":920,"imageUrl":"images\\\\Food Storage Container Plastic 2 L 160.jpg","nameKn":"ಆಹಾರ ಸಂಗ್ರಹ 2ಲೀ","description":"2 litre capacity."},
  {"id":48,"nameEn":"Food Storage Container Plastic 5 L","category":"storage","price":990,"imageUrl":"images\\\\Food Storage Container Plastic 5 L 160.jpg","nameKn":"ಆಹಾರ ಸಂಗ್ರಹ 5ಲೀ","description":"Large for bulk storage."},
  {"id":92,"nameEn":"Food Storage Glass 2 L","category":"storage","price":820,"imageUrl":"images\\\\Food Storage Glass 2 L 700.jpg","nameKn":"ಗಾಜು ಸಂಗ್ರಹ 2ಲೀ","description":"Glass container, airtight."},
  {"id":93,"nameEn":"Plastic Storage 3 L","category":"storage","price":260,"imageUrl":"images\\\\Plastic Storage 3 L 200.jpg","nameKn":"ಪ್ಲಾಸ್ಟಿಕ್ ಸಂಗ್ರಹ 3ಲೀ","description":"Rectangle, stackable."},
  {"id":94,"nameEn":"Plastic Storage 7 L","category":"storage","price":420,"imageUrl":"images\\\\Plastic Storage 7 L 1599.jpg","nameKn":"ಪ್ಲಾಸ್ಟಿಕ್ ಸಂಗ್ರಹ 7ಲೀ","description":"Extra large storage."}
]';
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    $result = [];
    foreach ($data as $item) {
        // Map category for frontend
        $catMap = [
            'cookware' => 'cookware',
            'bakeware' => 'tools',
            'knives' => 'tools',
            'utensils' => 'tools',
            'storage' => 'storage'
        ];
        $category = $catMap[$item['category']] ?? 'cookware';
        // Normalize image path
        $imagePath = $item['imageUrl'] ?? '';
        if ($imagePath && !str_starts_with($imagePath, 'http') && !str_starts_with($imagePath, 'data:')) {
            $imagePath = normalizeImagePath($imagePath);
        }
        $result[] = [
            'id' => (int) $item['id'],
            'name' => $item['nameEn'],
            'kn_name' => $item['nameKn'] ?? $item['nameEn'],
            'price' => (float) $item['price'],
            'category' => $category,
            'variants' => [['label' => '1 unit', 'price' => (float) $item['price']]],
            'emoji' => '🍳',
            'description_en' => $item['description'] ?? 'Quality kitchenware',
            'imageUrl' => $imagePath
        ];
    }
    $fallback = $result;
    file_put_contents($jsonPath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $fallback;
}
// Get products from DB, fallback if empty
$products = getKitchenProducts();
if (empty($products)) {
    $products = getFallbackKitchenProducts();
}
// Ensure each product has standard keys
foreach ($products as &$p) {
    if (!isset($p['variants']) || !is_array($p['variants']) || empty($p['variants'])) {
        $p['variants'] = [['label' => '1 unit', 'price' => $p['price']]];
    }
    $p['imageUrl'] = $p['imageUrl'] ?? $p['image_url'] ?? $p['image'] ?? '';
}
unset($p);
// Cart totals
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
    <title>Kitchenware · KSR Guru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap"
        rel="stylesheet">
    <style>
        /* ===== SAME CSS AS YOUR STATIC VERSION (KEPT UNCHANGED) ===== */
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
            font-size: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            overflow: hidden;
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
        <div class="search-section">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
            </div>
            <div class="filter-pills" id="filterPills">
                <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
                <div class="pill" data-category="cookware">Cookware / ಪಾತ್ರೆಗಳು</div>
                <div class="pill" data-category="tools">Tools / ಉಪಕರಣಗಳು</div>
                <div class="pill" data-category="storage">Storage / ಶೇಖರಣೆ</div>
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
            // Products injected from PHP – now includes fallback data with images
            const products = <?php
                                $out = [];
                                foreach ($products as $p) {
                                    $subcat = $p['category'] ?? 'cookware';
                                    $out[] = [
                                        'id' => $p['id'],
                                        'nameEn' => $p['name'],
                                        'nameKn' => $p['kn_name'] ?? $p['name'],
                                        'category' => $subcat,
                                        'variants' => $p['variants'] ?? [['label' => '1 unit', 'price' => $p['price']]],
                                        'emoji' => $p['emoji'] ?? '🍳',
                                        'description' => $p['description_en'] ?? 'Quality Kitchenware',
                                        'image' => $p['imageUrl'] ?? ''
                                    ];
                                }
                                echo json_encode($out);
                                ?>;
            let cart = [];
            const basePath = 'kitchen.php';
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
          <div class="cart-item-image">🍳</div>
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
                    const matchSearch = searchTerm === '' || p.nameEn.toLowerCase().includes(searchTerm) || p
                        .nameKn.toLowerCase().includes(searchTerm);
                    return matchCat && matchSearch;
                });
                if (!filtered.length) {
                    grid.innerHTML =
                        '<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No kitchenware products found ✨</div>';
                    return;
                }
                grid.innerHTML = filtered.map(p => {
                    const defaultVariant = 0;
                    const defaultPrice = p.variants[defaultVariant]?.price || 0;
                    // Image or emoji fallback
                    const imageHtml = p.image ? `<img src="${p.image}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='${p.emoji}'">` : p.emoji;
                    return `<div class="product-card" data-base="${p.id}">
          <div class="product-image">${imageHtml}</div>
          <div class="product-name">${p.nameEn}</div>
          ${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}
          <div class="product-description">${p.description}</div>
        ${p.variants && p.variants.length > 1 
          ? `<select class="weight-select" data-base="${p.id}">
              ${p.variants.map((v, i) => `<option value="${i}" ${i === defaultVariant ? 'selected' : ''}>${v.label} - ₹${v.price}</option>`).join('')}
             </select>`
          : `<div class="variant-text" style="font-size:0.85rem; font-weight:600; color:#4f3e2e; text-align:center; margin:10px 0; padding:10px; background:#fef7f0; border-radius:60px; border:1px solid #e8d9cd;">${p.variants && p.variants[0] ? p.variants[0].label : '1 unit'}</div>`
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