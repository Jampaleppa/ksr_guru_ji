<?php
// ============================================================
// dairy.php - Dynamic Dairy product page (FIXED)
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
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
// Handle AJAX requests for this page
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'] ?? '';
    switch ($action) {
        case 'products':
            $search = $_GET['search'] ?? '';
            echo json_encode(getDairyProducts($search));
            exit;
        case 'cart':
            echo json_encode($_SESSION['cart']);
            exit;
        case 'add_cart':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $product = getProductById($id);
                // If not found in DB, search in fallback static data
                if (!$product) {
                    $fallbackProducts = getFallbackDairyProducts();
                    foreach ($fallbackProducts as $fp) {
                        if ($fp['id'] == $id) {
                            $product = [
                                'id' => $fp['id'],
                                'name' => $fp['name'],
                                'kn_name' => $fp['kn_name'] ?? $fp['name'],
                                'price' => $fp['price'],
                                'icon' => 'fa-cow'
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
                            'icon' => $product['icon'] ?? 'fa-cow'
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
    function normalizeImagePath(?string $rawPath): string {
        if (empty($rawPath)) return '';
        $clean = str_replace('\\', '/', $rawPath);
        // Remove everything before 'images/'
        if (preg_match('#(?:.*)(images/.*)$#i', $clean, $matches)) {
            return '../' . $matches[1];
        }
        return '../images/' . basename($clean);
    }
}
// ============================================================
// FETCH DAIRY PRODUCTS FROM DATABASE (PDO)
// ============================================================
function getDairyProducts(string $search = ''): array {
    try {
        $pdo = getDBConnection(); // uses PDO from config.php
        // Try to check column existence dynamically
        $schema = getProductSchemaInfo();
        $descCol = $schema["desc_col"];
        $imgCol = $schema["img_col"];
        $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                       c.name AS category_name, c.kn_name AS category_kn,
                       {$descCol} AS description_en,
                       {$imgCol} AS image
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE c.slug = 'dairy'";
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
        // Add default fields for frontend
        foreach ($rows as &$row) {
            $row['description_en'] = $row['description_en'] ?? 'Fresh dairy product';
            if (!empty($row['image'])) {
                $row['image'] = normalizeImagePath($row['image']);
            }
            $row['variants'] = []; // We'll build variants from price/weight later
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}
// ============================================================
// FALLBACK STATIC DATA (if no DB products)
// ============================================================
function getFallbackDairyProducts(): array {
    static $fallback = null;
    if ($fallback !== null) {
        return $fallback;
    }
    $jsonPath = __DIR__ . '/dairy-fallback.json';
    if (file_exists($jsonPath)) {
        $fallback = json_decode(file_get_contents($jsonPath), true);
        if (is_array($fallback)) {
            return $fallback;
        }
    }
    $json = '[
  {"id":1,"name":"Amul Gold Full Cream Milk","category":"milk","kn":"ಅಮೂಲ್ ಗೋಲ್ಡ್","variants":[{"size":"500 ml","price":34},{"size":"1 Litre","price":68}],"imageUrl":"images\\\\Amul Gold Full Cream Milk.jpg"},
  {"id":2,"name":"Amul Taaza Toned Milk","category":"milk","kn":"ಅಮೂಲ್ ತಾಜಾ","variants":[{"size":"500 ml","price":28},{"size":"1 Litre","price":65}],"imageUrl":"images\\\\Amul Taaza Toned Milk.jpg"},
  {"id":3,"name":"Amul Shakti Standardised Milk","category":"milk","kn":"ಅಮೂಲ್ ಶಕ್ತಿ","variants":[{"size":"500 ml","price":31}],"imageUrl":"images\\\\Amul Shakti Standardised Milk.jpg"},
  {"id":4,"name":"Amul Slim n Trim Double Toned","category":"milk","kn":"ಅಮೂಲ್ ಸ್ಲಿಮ್","variants":[{"size":"500 ml","price":23}],"imageUrl":"images\\\\Amul Slim n Trim Double Toned.jpg"},
  {"id":5,"name":"Amul Cow Milk","category":"milk","kn":"ಅಮೂಲ್ ಹಸುವಿನ ಹಾಲು","variants":[{"size":"500 ml","price":28}],"imageUrl":"images\\\\Amul Cow Milk.jpg"},
  {"id":6,"name":"Amul Taaza UHT Milk","category":"milk","kn":"ಅಮೂಲ್ ತಾಜಾ ಯುಎಚ್ಟಿ","variants":[{"size":"1 Litre","price":75}],"imageUrl":"images\\\\Amul Taaza UHT Milk.jpg"},
  {"id":7,"name":"Amul Calci+ Milk","category":"milk","kn":"ಅಮೂಲ್ ಕ್ಯಾಲ್ಸಿ+","variants":[{"size":"1 Litre","price":95}],"imageUrl":"images\\\\Amul Calci+ Milk.png"},
  {"id":8,"name":"Amul Butter","category":"butter","kn":"ಅಮೂಲ್ ಬೆಣ್ಣೆ","variants":[{"size":"100 g","price":59},{"size":"500 g","price":275}],"imageUrl":"images\\\\Amul Butter.jpg"},
  {"id":9,"name":"Amul Fresh Cream","category":"cream","kn":"ಅಮೂಲ್ ಕ್ರೀಮ್","variants":[{"size":"200 ml","price":65},{"size":"1 Litre","price":222}],"imageUrl":"images\\\\Amul Fresh Cream.jpg"},
  {"id":10,"name":"Amul Malai Paneer","category":"paneer","kn":"ಅಮೂಲ್ ಪನೀರ್","variants":[{"size":"200 g","price":92},{"size":"1 kg","price":440}],"imageUrl":"images\\\\Amul Malai Paneer.jpg"},
  {"id":11,"name":"Amul Masti Dahi","category":"yogurt","kn":"ಅಮೂಲ್ ದಹಿ","variants":[{"size":"200 g","price":20},{"size":"400 g","price":40},{"size":"1 kg","price":85}],"imageUrl":"images\\\\Amul Masti Dahi.jpg"},
  {"id":12,"name":"Amul Pure Ghee","category":"ghee","kn":"ಅಮೂಲ್ ತುಪ್ಪ","variants":[{"size":"500 ml","price":270},{"size":"1 Litre","price":525}],"imageUrl":"images\\\\Amul Pure Ghee.jpg"},
  {"id":13,"name":"Amul Cheese Block","category":"cheese","kn":"ಅಮೂಲ್ ಚೀಸ್","variants":[{"size":"200 g","price":134},{"size":"500 g","price":340}],"imageUrl":"images\\\\Amul Cheese Block.jpg"},
  {"id":14,"name":"Amul Kool Flavoured Milk","category":"beverages","kn":"ಅಮೂಲ್ ಕೂಲ್","variants":[{"size":"180 ml (Chocolate)","price":27},{"size":"180 ml (Strawberry)","price":27}],"imageUrl":"images\\\\Amul Kool Flavoured Milk.jpg"},
  {"id":15,"name":"Amul Buttermilk (Chaas)","category":"beverages","kn":"ಅಮೂಲ್ ಮಜ್ಜಿಗೆ","variants":[{"size":"200 ml","price":12}],"imageUrl":"images\\\\Amul Buttermilk (Chaas).jpg"},
  {"id":16,"name":"Amul Lassi","category":"beverages","kn":"ಅಮೂಲ್ ಲಸ್ಸಿ","variants":[{"size":"200 ml","price":18}],"imageUrl":"images\\\\Amul Lassi.jpg"},
  {"id":17,"name":"Nandini Toned Milk","category":"milk","kn":"ನಂದಿನಿ ಟೋನ್ಡ್","variants":[{"size":"550 ml","price":24},{"size":"1050 ml","price":44}],"imageUrl":"images\\\\Nandini Toned Milk.jpg"},
  {"id":18,"name":"Nandini Homogenised Milk","category":"milk","kn":"ನಂದಿನಿ ಹೋಮೋಜಿನೈಸ್ಡ್","variants":[{"size":"550 ml","price":25}],"imageUrl":"images\\\\Nandini Homogenised Milk.jpg"},
  {"id":19,"name":"Nandini Samrudhi Full Cream","category":"milk","kn":"ನಂದಿನಿ ಸಮೃದ್ಧಿ","variants":[{"size":"500 ml","price":53},{"size":"1 Litre","price":102}],"imageUrl":"images\\\\Nandini Samrudhi Full Cream.jpg"},
  {"id":20,"name":"Nandini Shubham Milk","category":"milk","kn":"ನಂದಿನಿ ಶುಭಮ್","variants":[{"size":"1050 ml","price":50}],"imageUrl":"images\\\\Nandini Shubham Milk.jpg"},
  {"id":21,"name":"Nandini Goodlife UHT","category":"milk","kn":"ನಂದಿನಿ ಗುಡ್‌ಲೈಫ್","variants":[{"size":"1 Litre","price":68}],"imageUrl":"images\\\\Nandini Goodlife UHT.jpg"},
  {"id":22,"name":"Nandini Slim Milk","category":"milk","kn":"ನಂದಿನಿ ಸ್ಲಿಮ್","variants":[{"size":"500 ml","price":25}],"imageUrl":"images\\\\Nandini Slim Milk.jpg"},
  {"id":23,"name":"Nandini A2 Cow Milk","category":"milk","kn":"ನಂದಿನಿ ಎ2 ಹಾಲು","variants":[{"size":"500 ml","price":40}],"imageUrl":"images\\\\Nandini A2 Cow Milk.jpg"},
  {"id":24,"name":"Nandini Butter","category":"butter","kn":"ನಂದಿನಿ ಬೆಣ್ಣೆ","variants":[{"size":"100 g","price":60},{"size":"500 g","price":286}],"imageUrl":"images\\\\Nandini Butter.jpg"},
  {"id":25,"name":"Nandini Paneer","category":"paneer","kn":"ನಂದಿನಿ ಪನೀರ್","variants":[{"size":"200 g","price":88},{"size":"1 kg","price":408}],"imageUrl":"images\\\\Nandini Paneer.jpg"},
  {"id":26,"name":"Nandini Curd","category":"yogurt","kn":"ನಂದಿನಿ ಮೊಸರು","variants":[{"size":"200 g","price":12},{"size":"500 g","price":28},{"size":"1 kg","price":54}],"imageUrl":"images\\\\Nandini Curd.jpg"},
  {"id":27,"name":"Nandini Pure Cow Ghee","category":"ghee","kn":"ನಂದಿನಿ ತುಪ್ಪ","variants":[{"size":"500 ml","price":305},{"size":"1 Litre","price":610}],"imageUrl":"images\\\\Nandini Pure Cow Ghee 650.jpg"},
  {"id":28,"name":"Nandini Lassi","category":"beverages","kn":"ನಂದಿನಿ ಲಸ್ಸಿ","variants":[{"size":"200 ml","price":22}],"imageUrl":"images\\\\Nandini Lassi.jpg"},
  {"id":29,"name":"Nandini Buttermilk","category":"beverages","kn":"ನಂದಿನಿ ಮಜ್ಜಿಗೆ","variants":[{"size":"200 ml","price":14}],"imageUrl":"images\\\\Nandini Buttermilk.jpg"},
  {"id":30,"name":"Nandini Dharwad Peda","category":"sweets","kn":"ನಂದಿನಿ ಧಾರವಾಡ ಪೇಡಾ","variants":[{"size":"250 g","price":125}],"imageUrl":"images\\\\Nandini Dharwad Peda.jpg"},
  {"id":31,"name":"Nandini Mysore Pak","category":"sweets","kn":"ನಂದಿನಿ ಮೈಸೂರು ಪಾಕ್","variants":[{"size":"250 g","price":165}],"imageUrl":"images\\\\Nandini Mysore Pak.jpg"},
  {"id":32,"name":"Heritage Toned UHT Milk","category":"milk","kn":"ಹೆರಿಟೇಜ್ ಟೋನ್ಡ್ ಯುಎಚ್ಟಿ","variants":[{"size":"1 Litre","price":66}],"imageUrl":"images\\\\Heritage Toned UHT Milk.jpg"},
  {"id":33,"name":"Heritage Full Cream Milk","category":"milk","kn":"ಹೆರಿಟೇಜ್ ಫುಲ್ ಕ್ರೀಮ್","variants":[{"size":"500 ml","price":46},{"size":"1 Litre","price":92}],"imageUrl":"images\\\\Heritage Full Cream Milk.jpg"},
  {"id":34,"name":"Heritage Cow Milk","category":"milk","kn":"ಹೆರಿಟೇಜ್ ಹಸುವಿನ ಹಾಲು","variants":[{"size":"500 ml","price":29}],"imageUrl":"images\\\\Heritage Cow Milk.jpg"},
  {"id":35,"name":"Heritage Double Toned Milk","category":"milk","kn":"ಹೆರಿಟೇಜ್ ಡಬಲ್ ಟೋನ್ಡ್","variants":[{"size":"500 ml","price":21}],"imageUrl":"images\\\\Heritage Double Toned Milk.jpg"},
  {"id":36,"name":"Heritage Organic Milk","category":"milk","kn":"ಹೆರಿಟೇಜ್ ಸಾವಯವ ಹಾಲು","variants":[{"size":"500 ml","price":50}],"imageUrl":"images\\\\Heritage Organic Milk.jpg"},
  {"id":37,"name":"Heritage Curd","category":"yogurt","kn":"ಹೆರಿಟೇಜ್ ಮೊಸರು","variants":[{"size":"500 g","price":40},{"size":"1 kg","price":78}],"imageUrl":"images\\\\Heritage Curd.jpg"},
  {"id":38,"name":"Heritage Flavoured Yogurt","category":"yogurt","kn":"ಹೆರಿಟೇಜ್ ಫ್ಲೇವರ್ಡ್ ಮೊಸರು","variants":[{"size":"90 g","price":22}],"imageUrl":"images\\\\Heritage Flavoured Yogurt.jpg"},
  {"id":39,"name":"Heritage Cow Ghee","category":"ghee","kn":"ಹೆರಿಟೇಜ್ ತುಪ್ಪ","variants":[{"size":"500 ml","price":330},{"size":"1 Litre","price":700}],"imageUrl":"images\\\\Heritage Cow Ghee 700, packet -50.jpg"},
  {"id":40,"name":"Heritage Butter","category":"butter","kn":"ಹೆರಿಟೇಜ್ ಬೆಣ್ಣೆ","variants":[{"size":"500 g","price":300}],"imageUrl":"images\\\\Heritage Butter.jpg"},
  {"id":41,"name":"Heritage Cheese Slices","category":"cheese","kn":"ಹೆರಿಟೇಜ್ ಚೀಸ್ ಸ್ಲೈಸ್","variants":[{"size":"100 g","price":110}],"imageUrl":"images\\\\Heritage Cheese Slices.jpg"},
  {"id":42,"name":"Dodla Full Cream Milk","category":"milk","kn":"ದೊಡ್ಲಾ ಫುಲ್ ಕ್ರೀಮ್","variants":[{"size":"500 ml","price":35},{"size":"1 Litre","price":70}],"imageUrl":"images\\\\Dodla Full Cream Milk.jpg"},
  {"id":43,"name":"Dodla Toned Milk","category":"milk","kn":"ದೊಡ್ಲಾ ಟೋನ್ಡ್","variants":[{"size":"500 ml","price":29}],"imageUrl":"images\\\\Dodla Toned Milk.jpg"},
  {"id":44,"name":"Dodla UHT Milk","category":"milk","kn":"ದೊಡ್ಲಾ ಯುಎಚ್ಟಿ","variants":[{"size":"500 ml","price":31}],"imageUrl":"images\\\\Dodla UHT Milk.jpg"},
  {"id":45,"name":"Dodla Curd","category":"yogurt","kn":"ದೊಡ್ಲಾ ಮೊಸರು","variants":[{"size":"500 g","price":28},{"size":"1 kg","price":61}],"imageUrl":"images\\\\Dodla Curd.jpg"},
  {"id":46,"name":"Dodla Low Fat Curd","category":"yogurt","kn":"ದೊಡ್ಲಾ ಲೋ ಫ್ಯಾಟ್ ಮೊಸರು","variants":[{"size":"1 kg","price":48}],"imageUrl":"images\\\\Dodla Low Fat Curd.jpg"},
  {"id":47,"name":"Dodla Cow Ghee","category":"ghee","kn":"ದೊಡ್ಲಾ ತುಪ್ಪ","variants":[{"size":"500 ml","price":347},{"size":"1 Litre","price":605}],"imageUrl":"images\\\\Dodla Cow Ghee 610 jar ,554 packet.jpg"},
  {"id":48,"name":"Dodla Paneer","category":"paneer","kn":"ದೊಡ್ಲಾ ಪನೀರ್","variants":[{"size":"200 g","price":95},{"size":"1 kg","price":333}],"imageUrl":"images\\\\Dodla Paneer.jpg"},
  {"id":49,"name":"Hatsun Cow Milk","category":"milk","kn":"ಹ್ಯಾಟ್ಸನ್ ಹಸುವಿನ ಹಾಲು","variants":[{"size":"500 ml","price":30}],"imageUrl":"images\\\\Hatsun Cow Milk.jpg"},
  {"id":50,"name":"Hatsun Fresh Cream","category":"cream","kn":"ಹ್ಯಾಟ್ಸನ್ ಕ್ರೀಮ್","variants":[{"size":"100 g","price":38}],"imageUrl":"images\\\\Hatsun Fresh Cream.jpg"},
  {"id":51,"name":"Hatsun Curd","category":"yogurt","kn":"ಹ್ಯಾಟ್ಸನ್ ಮೊಸರು","variants":[{"size":"500 g","price":36},{"size":"1 kg","price":115}],"imageUrl":"images\\\\Hatsun Curd.jpg"},
  {"id":69,"name":"hatsun cup","category":"yogurt","kn":"ಹ್ಯಾಟ್ಸನ್ ಮೊಸರು","variants":[{"size":"500 g","price":36},{"size":"1 kg","price":115}],"imageUrl":"images\\\\hatsun cup.jpg"},
  {"id":52,"name":"Hatsun Greek Yogurt","category":"yogurt","kn":"ಹ್ಯಾಟ್ಸನ್ ಗ್ರೀಕ್ ಮೊಸರು","variants":[{"size":"100 g (Fig&Dates)","price":50},{"size":"100 g (Cherry)","price":45}],"imageUrl":"images\\\\Hatsun Greek Yogurt.jpg"},
  {"id":53,"name":"Gokul Full Cream Milk","category":"milk","kn":"ಗೋಕುಲ್ ಫುಲ್ ಕ್ರೀಮ್","variants":[{"size":"1 Litre","price":76}],"imageUrl":"images\\\\Gokul Full Cream Milk.jpg"},
  {"id":54,"name":"Gokul Cow Milk","category":"milk","kn":"ಗೋಕುಲ್ ಹಸುವಿನ ಹಾಲು","variants":[{"size":"500 ml","price":27}],"imageUrl":"images\\\\Gokul Cow Milk.jpg"},
  {"id":55,"name":"Gokul Buffalo Ghee","category":"ghee","kn":"ಗೋಕುಲ್ ಎಮ್ಮೆ ತುಪ್ಪ","variants":[{"size":"500 ml","price":295}],"imageUrl":"images\\\\Gokul Buffalo Ghee 570,jar 540.jpg"},
  {"id":56,"name":"Gokul Paneer","category":"paneer","kn":"ಗೋಕುಲ್ ಪನೀರ್","variants":[{"size":"500 g","price":187}],"imageUrl":"images\\\\Gokul Paneer.jpg"},
  {"id":57,"name":"Gokul Basundi","category":"sweets","kn":"ಗೋಕುಲ್ ಬಾಸುಂಡಿ","variants":[{"size":"250 g","price":55}],"imageUrl":"images\\\\Gokul Basundi.jpg"},
  {"id":58,"name":"Gokul Shrikhand","category":"sweets","kn":"ಗೋಕುಲ್ ಶ್ರೀಖಂಡ್","variants":[{"size":"500 g","price":115}],"imageUrl":"images\\\\Gokul Shrikhand.jpg"},
  {"id":59,"name":"Generic Full Cream Milk","category":"milk","kn":"ಜನರಲ್ ಫುಲ್ ಕ್ರೀಮ್","variants":[{"size":"1 Litre","price":69}],"imageUrl":"images\\\\Generic Full Cream Milk.jpg"},
  {"id":60,"name":"Arokya Toned Milk","category":"milk","kn":"ಜನರಲ್ ಟೋನ್ಡ್","variants":[{"size":"1 Litre","price":57}],"imageUrl":"images\\\\Generic Toned Milk.jpg"},
  {"id":61,"name":"Arokya standarized milk","category":"milk","kn":"ಜನರಲ್ ಹಸುವಿನ ಹಾಲು","variants":[{"size":"1 Litre","price":59}],"imageUrl":"images\\\\Arokya standarized milk.jpg"},
  {"id":62,"name":"Arokya full cream","category":"milk","kn":"ಜನರಲ್ ಡಬಲ್ ಟೋನ್ಡ್","variants":[{"size":"1 Litre","price":51}],"imageUrl":"images\\\\Aroya full cream.jpg"},
  {"id":63,"name":"Generic Dahi (Curd)","category":"yogurt","kn":"ಜನರಲ್ ಮೊಸರು","variants":[{"size":"1 kg","price":85}],"imageUrl":"images\\\\Curd (yogurt).jpg"},
  {"id":64,"name":"Generic Paneer","category":"paneer","kn":"ಜನರಲ್ ಪನೀರ್","variants":[{"size":"200 g","price":92}],"imageUrl":"images\\\\Generic Paneer.jpg"},
  {"id":65,"name":"Generic Butter","category":"butter","kn":"ಜನರಲ್ ಬೆಣ್ಣೆ","variants":[{"size":"100 g","price":57}],"imageUrl":"images\\\\Butter.jpg"},
  {"id":66,"name":"Generic Ghee","category":"ghee","kn":"ಜನರಲ್ ತುಪ್ಪ","variants":[{"size":"1 Litre","price":645}],"imageUrl":"images\\\\Generic Ghee.jpg"},
  {"id":67,"name":"Curd","category":"yogurt","kn":"ಜನರಲ್ ಮೊಸರು","variants":[{"size":"1 packet","price":11}],"imageUrl":"images\\\\Curd.jpg"},
  {"id":68,"name":"Milk Butter Masale","category":"yogurt","kn":"ಜನರಲ್ ಮಿಲ್ಕ್ ಬಟರ್ ಮಸಾಲೆ","variants":[{"size":"1 packet","price":11}],"imageUrl":"images\\\\masala buttermilk.jpg"},
  {"id":70,"name":"Organic Buffalo Milk","category":"Milk","kn":"ಎಮ್ಮೆ ತುಪ್ಪ","variants":[{"size":"500 ml","price":295}],"imageUrl":"images\\\\Organic Buffalo Milk 90.jpg"},
  {"id":71,"name":"Milk","category":"Milk","kn":"","variants":[{"size":"500 ml","price":295}],"imageUrl":"images\\\\Milk.jpg"},
  {"id":72,"name":"Flavored Milk Vanilla","category":"yogurt","kn":"ಹೆರಿಟೇಜ್ ಫ್ಲೇವರ್ಡ್ ಮೊಸರು","variants":[{"size":"90 g","price":22}],"imageUrl":"images\\\\Flavored Milk Vanilla 200ml 20-35.jpg"},
  {"id":73,"name":"Flavored Milk Strawberry","category":"yogurt","kn":"ಹೆರಿಟೇಜ್ ಫ್ಲೇವರ್ಡ್ ಮೊಸರು","variants":[{"size":"90 g","price":22}],"imageUrl":"images\\\\Flavored Milk Strawberry 200ml (200ml).jpg"}
]';
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    // Build the fallback array
    $result = [];
    foreach ($data as $item) {
        $catMap = [
            'milk' => 'milk',
            'butter' => 'butter',
            'cream' => 'cream',
            'paneer' => 'paneer',
            'yogurt' => 'yogurt',
            'ghee' => 'ghee',
            'cheese' => 'cheese',
            'beverages' => 'beverages',
            'sweets' => 'other',
            'Milk' => 'milk'
        ];
        $category = $catMap[$item['category']] ?? 'other';
        $variants = [];
        foreach ($item['variants'] as $v) {
            $variants[] = [
                'label' => $v['size'],
                'price' => (float) $v['price']
            ];
        }
        $price = $variants[0]['price'] ?? 0;
        $result[] = [
            'id' => (int) $item['id'],
            'name' => $item['name'],
            'kn_name' => $item['kn'] ?? $item['name'],
            'price' => $price,
            'category' => $category,
            'variants' => $variants,
            'emoji' => '🥛',
            'description_en' => 'Fresh dairy product',
            'image' => normalizeImagePath($item['imageUrl'] ?? '')
        ];
    }
    $fallback = $result;
    file_put_contents($jsonPath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $fallback;
}
// Get products (dairy category)
$products = getDairyProducts();
// If no products from DB, use fallback
if (empty($products)) {
    $products = getFallbackDairyProducts();
}
// Ensure variants are set
foreach ($products as &$p) {
    $variants = is_array($p['variants'] ?? null) ? $p['variants'] : [];
    if (empty($variants)) {
        // Try to build from price and weight if available
        $variants = [['label' => '500 ml / 500 g', 'price' => $p['price']]];
    }
    $p['variants'] = $variants;
}
unset($p);
// Get cart count and total
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
// Top picks (first 6, or you can use a different logic)
$topPicks = array_slice($products, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title> KSR Guru JI Dairy | Premium Milk & Dairy Products</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <style>
    .shop-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 2rem; }
    .branding h1 { font-size: 2.2rem; font-weight: 700; color: #3e2c1b; line-height: 1.2; }
    .branding .kannada-tag { font-size: 0.85rem; color: #b45f2b; font-weight: 500; margin-top: 4px; }
    .search-panel { margin-bottom: 32px; }
    .search-bar { background: #f3eee9; border-radius: 70px; padding: 8px 24px; display: flex; align-items: center; border: 1px solid #e6d9cd; gap: 12px; margin-bottom: 24px; box-shadow: 0 6px 12px rgba(0,0,0,0.02); max-width: 600px;}
    .search-bar i { font-size: 1.2rem; color: #b45f2b; }
    .search-bar input { flex: 1; border: none; padding: 12px 0; background: transparent; font-size: 1rem; outline: none; color: #2f2216; font-weight: 500; }
    
    .filter-pills { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px; scrollbar-width: thin;}
    .pill { background: #f3eee9; padding: 10px 24px; border-radius: 60px; font-weight: 600; font-size: 0.85rem; color: #4f3e2e; cursor: pointer; white-space: nowrap; border: 1px solid transparent; transition: all 0.2s; }
    .pill.active { background: #5c3e24; color: white; border-color: #5c3e24; box-shadow: 0 6px 12px rgba(92,62,36,0.2); }
    
    .top-picks { margin: 32px 0 40px; background: linear-gradient(135deg, #fdf8f4 0%, #f3eee9 100%); border-radius: 36px; padding: 24px 28px; border: 1px solid #e6d9cd; }
    .top-picks h3 { font-size: 1.8rem; font-weight: 700; color: #3e2c1b; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
    .top-picks h3 i { color: #b45f2b; font-size: 2rem; }
    .top-picks-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 24px; }
    
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin: 32px 0 48px; }
    .product-card, .top-pick-card { background: white; border-radius: 28px; padding: 22px 18px 24px; transition: all 0.25s ease; border: 1px solid #f2e5d9; box-shadow: 0 8px 24px rgba(0,0,0,0.04); display: flex; flex-direction: column; position: relative; }
    .product-card:hover, .top-pick-card:hover { transform: translateY(-6px); box-shadow: 0 20px 30px rgba(0,0,0,0.08); border-color: #e5d5c4; }
    .product-img, .top-pick-img { width: 90px; height: 90px; object-fit: cover; border-radius: 32px; background: #fef0e6; border: 2px solid #ffe2cf; margin: 0 auto 16px; flex-shrink: 0; display:flex; align-items:center; justify-content:center;}
    .product-img img, .top-pick-img img { width: 100%; height: 100%; object-fit: cover; border-radius: 30px;}
    .product-title, .top-pick-name { font-weight: 800; font-size: 1.15rem; color: #2f2216; line-height: 1.3; text-align: center; }
    .product-title-kn { font-size: 0.75rem; color: #7b5f45; margin: 5px 0 12px; font-weight: 500; text-align: center; }
    
    .price-row { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-top: auto; }
    .price { font-weight: 800; font-size: 1.5rem; color: #b45f2b; letter-spacing: -0.02em; text-align:center; margin-bottom:12px; width:100%;}
    .top-pick-price { font-size: 1.1rem; font-weight: 800; color: #b45f2b; margin: 8px 0; text-align: center;}
    
    .descriptive-cart-btn, .top-pick-btn { background: #b45f2b; border: none; border-radius: 60px; padding: 12px 18px; font-weight: 700; font-size: 0.9rem; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: auto; transition: 0.2s; box-shadow: 0 4px 12px rgba(180,95,43,0.25); width: 100%;}
    .descriptive-cart-btn:hover, .top-pick-btn:hover { transform: scale(0.96); background: #9c5022; }
    
    .variant-select { width: 100%; margin: 0.8rem 0; padding: 0.6rem 1rem; border-radius: 30px; border: 1px solid #e6d9cd; background: #f3eee9; font-size: 0.85rem; font-weight: 600; color: #4f3e2e; cursor: pointer; text-align: center; }
    .variant-select:focus { outline: none; border-color: #b45f2b; }

    footer { text-align: center; font-size: 0.75rem; color: #b9a690; margin-top: 32px; padding-top: 18px; border-top: 1px solid #f0e3d7; }
    .design-credits { margin-top: 20px; text-align: center; font-size: 0.7rem; color: #a07c5e; }
    @media (max-width: 640px) { #app { padding: 20px; } .product-grid { gap: 18px; } .top-picks-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); } }
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
    <div class="filter-pills" id="filterChips">
      <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
      <div class="pill" data-category="milk">🥛 Milk</div>
      <div class="pill" data-category="yogurt">🍶 Curd/Yogurt</div>
      <div class="pill" data-category="cheese">🧀 Cheese</div>
      <div class="pill" data-category="butter">🧈 Butter</div>
      <div class="pill" data-category="paneer">🍞 Paneer</div>
      <div class="pill" data-category="ghee">✨ Ghee</div>
    </div>
  </div>
  
  <div class="top-picks" id="topPicksSection">
    <h3><i class="fas fa-fire"></i> Top Picks · Bestsellers</h3>
    <div class="top-picks-grid" id="topPicksGrid"></div>
  </div>
  
  <div class="product-grid" id="mainProductGrid"></div>
  

</div>
<script>
(function() {
  // Products from PHP
  const products = <?php
  $out = [];
  foreach ($products as $p) {
      $subcat = $p['category'] ?? 'other';
      // Map category to frontend category names (milk, yogurt, cheese, butter, paneer, ghee, other)
      // Already handled in fallback, but we ensure
      $out[] = [
          'id' => $p['id'],
          'nameEn' => $p['name'],
          'nameKn' => $p['kn_name'] ?? $p['name'],
          'category' => $subcat,
          'variants' => $p['variants'],
          'emoji' => $p['emoji'] ?? '🥛',
          'description' => $p['description_en'] ?? 'Dairy product',
          'image' => $p['image'] ?? ''
      ];
  }
  echo json_encode($out);
  ?>;
  let cart = [];
  const searchInput = document.getElementById('searchInput');
  const filterChips = document.querySelectorAll('#filterChips .chip');
  const cartLink = document.getElementById('cartLink');
  const grid = document.getElementById('mainProductGrid');
  const topPicksGrid = document.getElementById('topPicksGrid');
  let activeCategory = 'all';
  let searchTerm = '';
  function addToCart(productId) {
    const prodIdNum = parseInt(productId);
    let product = null;
    if (typeof products !== 'undefined' && Array.isArray(products)) {
        product = products.find(p => p.id === prodIdNum);
    }
    // Find the variant selector in the DOM for this product
    const sel = document.querySelector(`.variant-select[data-base="${productId}"]`);
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
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
    .then(text => {
        let data;
        try {
            const jsonStart = text.indexOf('{');
            data = JSON.parse(text.substring(jsonStart));
        } catch(e) {
            console.error("JSON parse error:", text);
            alert("Error parsing cart response.");
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
            const btn = document.querySelector(`button.descriptive-cart-btn[data-pid="${productId}"]`) || document.querySelector(`button.descriptive-cart-btn[onclick="window.addToCart(${productId})"]`);
            if (btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Added';
                btn.style.backgroundColor = '#16a34a';
                btn.style.color = '#ffffff';
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.style.backgroundColor = '';
                    btn.style.color = '';
                }, 2000);
            }
            
            if (typeof loadGlobalCart === "function") {
                loadGlobalCart();
            }
            if (typeof openCart === 'function') { openCart(); }

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
  function renderProducts() {
    const filtered = products.filter(p => {
      const matchCat = activeCategory === 'all' || p.category === activeCategory;
      const matchSearch = searchTerm === '' || p.nameEn.toLowerCase().includes(searchTerm) || p.nameKn.toLowerCase().includes(searchTerm);
      return matchCat && matchSearch;
    });
    if (!filtered.length) {
      grid.innerHTML = '<div class="empty-state"><i class="fa-regular fa-face-frown"></i> No products found</div>';
      return;
    }
    grid.innerHTML = filtered.map(p => {
      const defaultVariant = 0;
      const defaultPrice = p.variants[defaultVariant]?.price || 0;
      // Image: use <img> if image exists, else emoji
      const imageHtml = p.image ? `<img src="${p.image}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='${p.emoji}'">` : p.emoji;
      return `<div class="product-card" data-base="${p.id}">
        <div class="product-img">${imageHtml}</div>
        <div class="product-name">${p.nameEn}</div>
        ${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}
        ${p.variants && p.variants.length > 1 
          ? `<select class="variant-select" data-base="${p.id}">
              ${p.variants.map((v, i) => `<option value="${i}" ${i===defaultVariant?'selected':''}>${v.label} - ₹${v.price}</option>`).join('')}
             </select>`
          : `<div class="variant-text" style="font-size:0.85rem; font-weight:600; color:#4f3e2e; text-align:center; margin:0.8rem 0; padding:0.6rem 1rem; background:#fef4eb; border-radius:30px; border:1px solid #f2e5d9;">${p.variants && p.variants[0] ? p.variants[0].label : '1 unit'}</div>`
        }
        <div class="price-row">
          <span class="price" id="price-${p.id}">₹${defaultPrice} <small>inr</small></span>
          <button class="descriptive-cart-btn" data-pid="${p.id}" onclick="window.addToCart(${p.id})"><i class="fa-solid fa-cart-plus"></i> Add to Cart</button>
        </div>
      </div>`;
    }).join('');
    document.querySelectorAll('.variant-select').forEach(sel => {
      const base = sel.dataset.base;
      const priceSpan = document.getElementById(`price-${base}`);
      sel.addEventListener('change', function() {
        const prod = products.find(p => p.id == base);
        if (!prod) return;
        const idx = parseInt(this.value);
        const price = prod.variants[idx]?.price || 0;
        if (priceSpan) priceSpan.innerHTML = `₹${price} <small>inr</small>`;
      });
    });
  }
  function renderTopPicks() {
    const topProds = products.filter(p => p.id % 2 === 1).slice(0, 6);
    if (!topProds.length) return;
    topPicksGrid.innerHTML = topProds.map(p => {
      const firstVariant = p.variants[0];
      const imageHtml = p.image ? `<img src="${p.image}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='${p.emoji}'">` : p.emoji;
      return `<div class="top-card">
        <div class="top-img">${imageHtml}</div>
        <div class="top-name">${p.nameEn}</div>
        <div class="top-price">₹${firstVariant?.price || 0}</div>
        <button class="descriptive-cart-btn" onclick="window.addToCart(${p.id})"><i class="fa-solid fa-cart-plus"></i> Add to Cart</button>
      </div>`;
    }).join('');
  }
  searchInput.addEventListener('input', function(e) {
    searchTerm = this.value.toLowerCase();
    renderProducts();
  });
  filterChips.forEach(chip => {
    chip.addEventListener('click', function() {
      filterChips.forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      activeCategory = this.dataset.category;
      renderProducts();
    });
  });
  if (cartLink) {
    cartLink.addEventListener('click', function(e) {
      e.preventDefault();
      if (typeof openCart === 'function') { openCart(); } else { window.location.href = '../cart/cart.php'; }
    });
  }
  window.addToCart = addToCart;
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
    fetch('../index.php?ajax=update_cart', { method: 'POST', body: formData })
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
    fetch('../index.php?ajax=remove_cart', { method: 'POST', body: formData })
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
