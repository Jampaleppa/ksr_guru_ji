<?php
// ============================================================
// dry-fruits.php - Dynamic Dry Fruits & Nuts product page
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
            echo json_encode(getDryFruitsProducts($search));
            exit;
        case 'cart':
            echo json_encode($_SESSION['cart']);
            exit;
        case 'add_cart':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $product = getProductById($id);
                // If not found in DB, search fallback
                if (!$product) {
                    $fallbackProducts = getFallbackDryFruitsProducts();
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
            $_SESSION['cart'] = array_values($_SESSION['cart']); // re-index
            echo json_encode(['success' => true, 'cart' => $_SESSION['cart']]);
            exit;
        case 'remove_cart':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $_SESSION['cart'] = array_filter($_SESSION['cart'], fn($i) => $i['id'] != $id);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // re-index
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
// Helper function to fetch dry fruits products from database (PDO version)
function getDryFruitsProducts(string $search = ''): array
{
    try {
        $pdo = getDBConnection();
        // Dynamically detect column presence
        $schema = getProductSchemaInfo();
        $descCol = $schema["desc_col"];
        $imgCol = $schema["img_col"];
        // Try to get category id for 'dry-fruits' slug
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute(['dry-fruits']);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        $catId = $cat ? $cat['id'] : null;
        $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                       c.name AS category_name, c.kn_name AS category_kn,
                       {$descCol} AS description_en,
                       {$imgCol} AS imageUrl
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE 1=1";
        $params = [];
        if ($catId) {
            $sql .= " AND p.category_id = ?";
            $params[] = $catId;
        } else {
            // Fallback: search by category name containing 'dry' or 'fruit'
            $sql .= " AND (c.name LIKE ? OR c.name LIKE ?)";
            $params[] = '%Dry%';
            $params[] = '%Fruit%';
        }
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
            $row['description_en'] = $row['description_en'] ?? 'Premium Dry Fruit or Nut product';
            if (!empty($row['imageUrl'])) {
                $row['imageUrl'] = normalizeImagePath($row['imageUrl']);
            }
            $row['variants'] = null;
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}
// ============================================================
// FALLBACK: Parse the provided CSV-like product data
// ============================================================
function getFallbackDryFruitsProducts(): array
{
    static $fallback = null;
    if ($fallback !== null) {
        return $fallback;
    }
    $jsonPath = __DIR__ . '/dry-fruits-fallback.json';
    if (file_exists($jsonPath)) {
        $fallback = json_decode(file_get_contents($jsonPath), true);
        if (is_array($fallback)) {
            return $fallback;
        }
    }
    // Raw data as a multi-line string (copied from your input, shortened for brevity)
    // We'll include the full data as in your original code.
    $rawData = <<<CSV
1, Almonds, images\\Dry Fruits & Snacks\\Almonds.jpg, 10g,50g,100g,200g,250g,500g,1kg, ₹2300
4, Cashews,images\\Dry Fruits & Snacks\\Cashews.jpg, 10g,50g,100g,200g,250g,500g,1kg, ₹2900
6, Walnuts, images\\Dry Fruits & Snacks\\Walnuts 2400.jpg,10g,50g,100g,200g,250g,500g,1kg, ₹2700
8, Pistachios,images\\Dry Fruits & Snacks\\Pistachios 700.jpg, 10g,50g,100g,200g,250g,500g,1kg,₹3100
10, Hazelnuts, images\\Dry Fruits & Snacks\\Hazelnuts.jpg, 10g,50g,100g,200g,250g,500g,1kg, ₹2900
12, Macadamia Nuts, images\\Dry Fruits & Snacks\\Macadamia Nuts.jpg, 10g,50g,100g,200g,250g,500g,1kg,₹4800
14, Peanuts, images\\Dry Fruits & Snacks\\Peanuts.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹950
16, Brazil Nuts, images\\Dry Fruits & Snacks\\Brazil Nuts Roasted.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹4200
18, Almonds Blanched, images\\Dry Fruits & Snacks\\Almonds Blanched.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹2500
20, Cashew Kernels, images\\Dry Fruits & Snacks\\Cashew Kernels.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹3100
### Nuts / ಬೀಜಗಳು
22, Raisins Black, images\\Dry Fruits & Snacks\\Raisins Black.jpg ,10g,50g,100g,200g,250g,500g, 1kg, ₹1100
24, Raisins Green, images\\Dry Fruits & Snacks\\Raisins Green.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1200
26, Dates Medjool,images\\Dry Fruits & Snacks\\Dates Medjool.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1700
28, Figs Dry, images\\Dry Fruits & Snacks\\Figs Dry.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹2300
30, Apricots Dried, images\\Dry Fruits & Snacks\\Apricots Dried.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹2300
32, Prunes Dried, images\\Dry Fruits & Snacks\\Prunes Dried.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹2100
34, Mulberries White, images\\Dry Fruits & Snacks\\Mulberries White.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1700
36, Mulberries Black, images\\Dry Fruits & Snacks\\Mulberries Black.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1800
38, Mango Slices Dry, images\\Dry Fruits & Snacks\\Mango Slices Dry.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1300
40, Pineapple Dried, images\\Dry Fruits & Snacks\\Pineapple Dried.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1500
42, Banana Chips, images\\Dry Fruits & Snacks\\Banana Chips.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹750
44, Coconut Pieces Dry, images\\Dry Fruits & Snacks\\Coconut Pieces Dry.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹950
46, Papaya Dried, images\\Dry Fruits & Snacks\\Papaya Dried.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1300
48, Pine Nuts, images\\Dry Fruits & Snacks\\Pine Nuts.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹3900
50, Dried Cherries,images\\Dry Fruits & Snacks\\Dried Cherries.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹2900
### Seeds / ಬಿತ್ತನೆ
52, Chia Seeds, images\\Dry Fruits & Snacks\\Chia Seeds.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹2300
54, Flax Seeds, images\\Dry Fruits & Snacks\\Flax Seeds.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1700
56, Pumpkin Seeds, images\\Dry Fruits & Snacks\\Pumpkin Seeds.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹2300
58, Sunflower Seeds, images\\Dry Fruits & Snacks\\Sunflower Seeds.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1500
60, Sesame Seeds, images\\Dry Fruits & Snacks\\Sesame Seeds.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1100
### Mixed / ಮಿಶ್ರ
62, Mixed Nuts, images\\Dry Fruits & Snacks\\Mixed Nuts.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹2700
64, Mixed Dry Fruits, images\\Dry Fruits & Snacks\\Mixed Dry Fruits.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹2300
66, Trail Mix,images\\Dry Fruits & Snacks\\Trail Mix.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1700
68, Nut & Seed Mix,images\\Dry Fruits & Snacks\\Nut & Seed Mix.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1900
70, Premium Fruit & Nut Mix, images\\Dry Fruits & Snacks\\Premium Fruit & Nut Mix.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2800
72, Dry Fruit Mix with Almonds,images\\Dry Fruits & Snacks\\Dry Fruit Mix with Almonds.jpg, 1kg, ₹2500
74, Dry Fruit Mix with Cashews, images\\Dry Fruits & Snacks\\Dry Fruit Mix with Cashews.jpg, 1kg, ₹2700
76, Dry Fruit Mix with Walnuts, images\\Dry Fruits & Snacks\\Dry Fruit Mix with Walnuts.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2800
78, Energy Mix, images\\Dry Fruits & Snacks\\Energy Mix.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2300
80, Superfood Mix,images\\Dry Fruits & Snacks\\Superfood Mix.jpg,10g,50g,100g,200g,250g,500g,1kg, ₹3000
82, Protein Nut Mix, images\\Dry Fruits & Snacks\\Protein Nut Mix.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2800
83, Gourmet Dry Fruits, images\\Dry Fruits & Snacks\\Gourmet Dry Fruits.jpg,10g,50g,100g,200g,250g, 500g, ₹1800
84, Gourmet Dry Fruits, images\\Dry Fruits & Snacks\\Gourmet Dry Fruits.jpg,10g,50g,100g,200g,250g, 1kg, ₹3500
85, Healthy Snack Mix, images\\Dry Fruits & Snacks\\Healthy Snack Mix.jpg,10g,50g,100g,200g,250g, 500g, ₹1300
86, Healthy Snack Mix, images\\Dry Fruits & Snacks\\Healthy Snack Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹2500
87, Nutty Delight Mix, images\\Dry Fruits & Snacks\\Nutty Delight Mix.jpg,10g,50g,100g,200g,250g, 500g, ₹1400
88, Nutty Delight Mix, images\\Dry Fruits & Snacks\\Nutty Delight Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹2700
90, Fruit & Nut Premium Mix, images\\Dry Fruits & Snacks\\Fruit & Nut Premium Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹2800
92, Choco Nut Mix, images\\Dry Fruits & Snacks\\Choco Nut Mix.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2300
94, Exotic Nut Mix,images\\Dry Fruits & Snacks\\Exotic Nut Mix.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹3000
96, Premium Trail Mix,images\\Dry Fruits & Snacks\\Premium Trail Mix.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2700
98, Super Nut Mix, images\\Dry Fruits & Snacks\\Super Nut Mix.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2800
100, Deluxe Dry Fruit Mix, images\\Dry Fruits & Snacks\\Deluxe Dry Fruit Mix.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹3500
### Nuts / ಬೀಜಗಳು (continued)
101, Almonds Roasted, images\\Dry Fruits & Snacks\\Almonds Roasted.jpg,10g,50g,100g,200g,250g, 500g, ₹1300
104, Cashews Roasted,images\\Dry Fruits & Snacks\\Cashews Roasted.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹3100
106, Walnuts Roasted, images\\Dry Fruits & Snacks\\Walnuts Roasted.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2800
108, Pistachios Roasted, images\\Dry Fruits & Snacks\\Pistachios Roasted.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹3200
110, Hazelnuts Roasted, images\\Dry Fruits & Snacks\\Hazelnuts Roasted.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹3000
112, Macadamia Roasted,images\\Dry Fruits & Snacks\\Macadamia Roasted.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹5000
114, Peanuts Roasted, images\\Dry Fruits & Snacks\\Peanuts Roasted.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹1000
116, Brazil Nuts Roasted,images\\Dry Fruits & Snacks\\Brazil Nuts Roasted.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹4400
118, Almonds Sliced, images\\Dry Fruits & Snacks\\Almonds Sliced.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2600
120, Cashew Pieces, images\\Dry Fruits & Snacks\\Cashew Pieces.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹3100
### Dry Fruits / ಒಣಹಣ್ಣುಗಳು (continued)
121, Golden Raisins,images\\Dry Fruits & Snacks\\Golden Raisins.jpg,10g,50g,100g,200g,250g, 500g, ₹650
124, Cranberries Dried, images\\Dry Fruits & Snacks\\Cranberries Dried.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2800
126, Dried Blueberries, images\\Dry Fruits & Snacks\\Dried Blueberries.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹3000
128, Dried Strawberries, images\\Dry Fruits & Snacks\\Dried Strawberries.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2800
130, Dried Black Currants, images\\Dry Fruits & Snacks\\Dried Black Currants.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹3000
132, Dried Mulberries Red, images\\Dry Fruits & Snacks\\Dried Mulberries Red.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹1800
134, Dried Mango Cubes, images\\Dry Fruits & Snacks\\Dried Mango Cubes.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹1500
136, Dried Papaya Cubes, images\\Dry Fruits & Snacks\\Dried Papaya Cubes.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹1300
138, Dried Pineapple Cubes, images\\Dry Fruits & Snacks\\Dried Pineapple Cubes.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹1500
140, Dried Apple Rings, images\\Dry Fruits & Snacks\\Dried Apple Rings.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹1300
### Seeds / ಬಿತ್ತನೆ (continued)
142, Quinoa Seeds,images\\Dry Fruits & Snacks\\Quinoa Seeds.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹2300
144, Hemp Seeds, images\\Dry Fruits & Snacks\\Hemp Seeds.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹2800
146, Poppy Seeds, images\\Dry Fruits & Snacks\\Poppy Seeds.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1700
148, Caraway Seeds, images\\Dry Fruits & Snacks\\Caraway Seeds.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1500
150, Nigella Seeds, images\\Dry Fruits & Snacks\\Nigella Seeds.jpg, 10g,50g,100g,200g,250g,500g, 1kg, ₹1700
### Mixed / ಮಿಶ್ರ (continued)
152, Fruit & Nut Mix Roasted, images\\Dry Fruits & Snacks\\Fruit & Nut Mix Roasted.jpg,10g,50g,100g,200g,250g, 1kg, ₹3000
154, Super Nut & Seed Mix,   images\\Dry Fruits & Snacks\\Super Nut & Seed Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹2800
156, Gourmet Trail Mix, images\\Dry Fruits & Snacks\\Gourmet Trail Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3000
158, Premium Nutty Delight, images\\Dry Fruits & Snacks\\Premium Nutty Delight.jpg,10g,50g,100g,200g,250g, 1kg, ₹3200
160, Fruit & Seed Premium Mix,images\\Dry Fruits & Snacks\\Fruit & Seed Premium Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹2800
162, Energy Nut Mix, images\\Dry Fruits & Snacks\\Energy Nut Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹2700
164, Superfood Nut Mix, images\\Dry Fruits & Snacks\\Superfood Nut Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3000
166, Nut & Fruit Gourmet Mix, images\\Dry Fruits & Snacks\\Nut & Fruit Gourmet Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3200
168, Protein Trail Mix, images\\Dry Fruits & Snacks\\Protein Trail Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹2800
170, Deluxe Nut & Fruit Mix, images\\Dry Fruits & Snacks\\Deluxe Nut & Fruit Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3500
172, Choco Nut Gourmet Mix,images\\Dry Fruits & Snacks\\Choco Nut Gourmet Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3000
174, Exotic Dry Fruit Mix, images\\Dry Fruits & Snacks\\Exotic Dry Fruit Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3500
176, Premium Nutty Trail Mix, images\\Dry Fruits & Snacks\\Premium Nutty Trail Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3200
178, Gourmet Nut & Fruit Delight, images\\Dry Fruits & Snacks\\Gourmet Nut & Fruit Delight.jpg,10g,50g,100g,200g,250g, 1kg, ₹3500
180, Super Deluxe Mix, images\\Dry Fruits & Snacks\\Super Deluxe Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3700
182, Protein Nut Delight, images\\Dry Fruits & Snacks\\Protein Nut Delight.jpg,10g,50g,100g,200g,250g, 1kg, ₹3500
184, Healthy Gourmet Mix,images\\Dry Fruits & Snacks\\Healthy Gourmet Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3200
186, Deluxe Trail Mix, images\\Dry Fruits & Snacks\\Deluxe Trail Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3500
188, Super Nut Delight Mix,images\\Dry Fruits & Snacks\\Super Nut Delight Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3700
190, Exotic Fruit & Nut Mix, images\\Dry Fruits & Snacks\\Exotic Fruit & Nut Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3900
192, Premium Trail Gourmet, images\\Dry Fruits & Snacks\\Premium Trail Gourmet.jpg,10g,50g,100g,200g,250g, 1kg, ₹3500
194, Deluxe Nut Mix, images\\Dry Fruits & Snacks\\Deluxe Nut Mix.jpg,10g,50g,100g,200g,250g,500g, 1kg, ₹3500
196, Nut & Seed Gourmet Mix, images\\Dry Fruits & Snacks\\Nut & Seed Gourmet Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3700
198, Choco Fruit Nut Mix, images\\Dry Fruits & Snacks\\Choco Fruit Nut Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3000
200, Super Premium Nut Mix, images\\Dry Fruits & Snacks\\Super Premium Nut Mix.jpg,10g,50g,100g,200g,250g, 1kg, ₹3900
CSV;
    $lines = explode("\n", $rawData);
    $products = [];
    $currentCategory = 'nuts';
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        if (strpos($line, '###') === 0) {
            if (stripos($line, 'Nuts') !== false || stripos($line, 'ಬೀಜಗಳು') !== false) {
                $currentCategory = 'nuts';
            } elseif (stripos($line, 'Dry Fruits') !== false || stripos($line, 'ಒಣಹಣ್ಣುಗಳು') !== false) {
                $currentCategory = 'dryfruits';
            } elseif (stripos($line, 'Seeds') !== false || stripos($line, 'ಬಿತ್ತನೆ') !== false) {
                $currentCategory = 'seeds';
            } elseif (stripos($line, 'Mixed') !== false || stripos($line, 'ಮಿಶ್ರ') !== false) {
                $currentCategory = 'mixed';
            }
            continue;
        }
        $parts = explode(',', $line);
        if (count($parts) < 3) continue;
        $id = trim($parts[0]);
        $name = trim($parts[1]);
        $image = trim($parts[2]);
        $pricePart = trim(end($parts));
        $pricePerKg = (float) str_replace(['₹', ' '], '', $pricePart);
        $weightParts = array_slice($parts, 3, -1);
        $variants = [];
        foreach ($weightParts as $wp) {
            $wp = trim($wp);
            if (empty($wp)) continue;
            $weightLabel = $wp;
            $grams = 0;
            if (strpos($wp, 'kg') !== false) {
                $grams = (float) str_replace('kg', '', $wp) * 1000;
            } elseif (strpos($wp, 'g') !== false) {
                $grams = (float) str_replace('g', '', $wp);
            } else {
                continue;
            }
            if ($grams > 0) {
                $price = ($pricePerKg / 1000) * $grams;
                $variants[] = [
                    'label' => $weightLabel,
                    'price' => round($price, 2)
                ];
            }
        }
        if (empty($variants)) {
            $variants[] = ['label' => '1 kg', 'price' => $pricePerKg];
        }
        $knName = $name;
        $description = "Premium quality " . strtolower($name) . ", naturally sourced.";
        $emoji = '🌰';
        if ($currentCategory === 'dryfruits') $emoji = '🍇';
        elseif ($currentCategory === 'seeds') $emoji = '🌱';
        elseif ($currentCategory === 'mixed') $emoji = '🥜';
        // Convert Windows path to web relative path
        $imagePath = $image;
        if ($imagePath && !str_starts_with($imagePath, 'http') && !str_starts_with($imagePath, 'data:')) {
            $imagePath = str_replace('', '', $imagePath);
            $imagePath = str_replace('\\', '/', $imagePath);
            if (!str_starts_with($imagePath, 'images/') && !str_starts_with($imagePath, '../')) {
                $imagePath = '../images/' . $imagePath;
            } elseif (!str_starts_with($imagePath, '../')) {
                $imagePath = '../' . $imagePath;
            }
        } else {
            $imagePath = ''; // fallback to emoji
        }
        $products[] = [
            'id' => (int) $id,
            'name' => $name,
            'kn_name' => $knName,
            'price' => $pricePerKg,
            'category' => $currentCategory,
            'variants' => $variants,
            'emoji' => $emoji,
            'description_en' => $description,
            'imageUrl' => $imagePath
        ];
    }
    $fallback = $products;
    @file_put_contents($jsonPath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $fallback;
}
if (!function_exists('getFallbackProducts')) {
    function getFallbackProducts(): array {
        return getFallbackDryFruitsProducts();
    }
}
// Get products from database
$products = getDryFruitsProducts();
// If no products from DB, use fallback
if (empty($products)) {
    $fallbackProducts = getFallbackDryFruitsProducts();
    // Convert fallback to same structure as DB products
    $products = [];
    foreach ($fallbackProducts as $fp) {
        $products[] = [
            'id' => $fp['id'],
            'name' => $fp['name'],
            'kn_name' => $fp['kn_name'],
            'price' => $fp['price'],
            'variants' => $fp['variants'],
            'emoji' => $fp['emoji'],
            'description_en' => $fp['description_en'],
            'category' => $fp['category'],
            'imageUrl' => $fp['imageUrl'] ?? ''
        ];
    }
}
// Pre-fill variants locally (decode JSON if string, or use directly if array)
foreach ($products as &$p) {
    $variants = is_array($p['variants'] ?? null) ? $p['variants'] : json_decode($p['variants'] ?? '[]', true);
    if (empty($variants)) {
        $variants = [['label' => '250 g', 'price' => $p['price']]];
    }
    $p['variants'] = $variants;
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
    <title>DryFruits & Nuts · ಒಣಹಣ್ಣು ಮತ್ತು ಬೀಜಗಳು · Premium Quality · KSR Guru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fcf3ea;
            color: #2d241c;
            line-height: 1.8;
            padding: 20px 12px;
        }

        .market {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 40px;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.08);
            padding: 24px 28px 36px;
        }

        .brand-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 28px;
            border-bottom: 2px solid #f5ca6e;
            padding-bottom: 16px;
        }

        .logo h1 {
            font-size: 1.9rem;
            font-weight: 800;
            background: linear-gradient(135deg, #d47b3a, #b58f6b);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .logo p {
            font-size: 0.8rem;
            color: #b98f6b;
            margin-top: 4px;
        }

        .cart-link {
            background: #fff5ec;
            padding: 10px 20px;
            border-radius: 60px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-weight: 600;
            color: #a86034;
            border: 1px solid #f0dfcf;
            transition: all 0.2s;
        }

        .cart-link:hover {
            background: #faeadc;
            transform: translateY(-2px);
        }

        .cart-badge {
            background: #c97c3e;
            color: white;
            padding: 2px 10px;
            border-radius: 40px;
            font-size: 0.8rem;
        }

        .search-area {
            margin: 20px 0 28px;
        }

        .search-box {
            background: white;
            border: 1px solid #eedfcb;
            border-radius: 60px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-box:focus-within {
            border-color: #c97c3e;
            box-shadow: 0 4px 12px rgba(201, 124, 62, 0.1);
        }

        .search-box i {
            color: #c97c3e;
            font-size: 1.1rem;
        }

        .search-box input {
            flex: 1;
            border: none;
            background: transparent;
            outline: none;
            font-size: 0.95rem;
        }

        .filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .chip {
            background: #fef7f0;
            padding: 8px 20px;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid #eedfcb;
            color: #6b4d32;
        }

        .chip.active {
            background: #c97c3e;
            border-color: #c97c3e;
            color: white;
        }

        .chip:hover:not(.active) {
            background: #f3e6da;
        }

        .top-picks {
            background: #fffaf2;
            border-radius: 32px;
            padding: 20px 24px;
            margin: 20px 0 36px;
            border: 1px solid #f3e3d4;
        }

        .top-picks h3 {
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            color: #7b4a2c;
        }

        .top-picks h3 i {
            color: #884c0f;
        }

        .top-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1.2rem;
        }

        .top-card {
            background: white;
            border-radius: 24px;
            padding: 12px;
            text-align: center;
            border: 1px solid #f3e6db;
            transition: 0.2s;
        }

        .top-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -12px rgba(0, 0, 0, 0.08);
        }

        .top-img {
            width: 80px;
            height: 80px;
            border-radius: 40px;
            margin: 0 auto 10px;
            background: #fcf2e6;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .top-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .top-name {
            font-weight: 700;
            font-size: 0.85rem;
            color: #5a3c24;
        }

        .top-price {
            font-weight: 700;
            color: #c97c3e;
            font-size: 0.85rem;
            margin: 6px 0;
        }

        .top-btn {
            background: #f5e8dd;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 30px;
            color: #e99a5a;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 28px;
            margin: 30px 0 40px;
        }

        .product-card {
            background: white;
            border-radius: 32px;
            padding: 18px 16px 20px;
            border: 1px solid #f2e4d6;
            transition: all 0.2s ease;
            text-align: center;
        }

        .product-card:hover {
            transform: translateY(-5px);
            border-color: #e7cfbb;
            box-shadow: 0 20px 30px -16px rgba(0, 0, 0, 0.1);
        }

        .product-img {
            width: 100px;
            height: 100px;
            border-radius: 30px;
            margin: 0 auto 12px;
            background: #fef3e8;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-name {
            font-weight: 700;
            font-size: 1rem;
            color: #4f311d;
        }

        .product-name-kn {
            font-size: 0.7rem;
            color: #8b694b;
            margin-bottom: 6px;
        }

        .product-desc {
            font-size: 0.75rem;
            color: #8b694b;
            line-height: 1.4;
            margin: 8px 0 10px;
            padding: 0 4px;
        }

        .variant-select {
            width: 90%;
            margin: 8px auto;
            padding: 8px 10px;
            border-radius: 50px;
            border: 1px solid #ebdfd2;
            background: #fefbf8;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
            padding: 0 4px;
        }

        .price {
            font-weight: 800;
            font-size: 1.1rem;
            color: #c97c3e;
        }

        .descriptive-cart-btn {
            background: #f5e8dd;
            border: none;
            width: 100%;
            padding: 10px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            color: #c97c3e;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 10px;
        }

        .descriptive-cart-btn.added-flash {
            background: #4caf50 !important;
            color: white !important;
        }

        .descriptive-cart-btn:hover {
            background: #e7d3c2;
        }

        .descriptive-cart-btn:active {
            transform: scale(0.96);
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
            font-size: 0.75rem;
            color: #c5ad94;
            border-top: 1px solid #f0e2d4;
            padding-top: 24px;
            margin-top: 12px;
        }

        @media (max-width: 680px) {
            .market {
                padding: 16px;
            }

            .products-grid {
                gap: 16px;
            }

            .product-img {
                width: 85px;
                height: 85px;
            }

            .top-grid {
                gap: 12px;
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
    <div class="market">
        <?php
        $base_url = '../';
        include __DIR__ . '/../includes/global-header.php';
        ?>
        <div class="search-area">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
            </div>
            <div class="filter-chips" id="filterChips">
                <div class="chip active" data-cat="all">All / ಎಲ್ಲ</div>
                <div class="chip" data-cat="nuts">🥜 Nuts / ಬೀಜಗಳು</div>
                <div class="chip" data-cat="dryfruits">🍇 Dry Fruits / ಒಣಹಣ್ಣುಗಳು</div>
                <div class="chip" data-cat="seeds">🌱 Seeds / ಬಿತ್ತನೆ</div>
            </div>
        </div>
        <div class="top-picks" id="topPicksSection">
            <h3><i class="fa-regular fa-star"></i> Top Picks · Bestsellers</h3>
            <div class="top-grid" id="topPicksGrid"></div>
        </div>
        <div class="products-grid" id="mainProductGrid"></div>

        <script>
            (function() {
                const products = <?php
                                    $out = [];
                                    foreach ($products as $p) {
                                        $subcat = $p['category'] ?? 'nuts';
                                        if (!isset($p['category']) || $p['category'] === null) {
                                            $n = strtolower($p['name']);
                                            if (strpos($n, 'raisin') !== false || strpos($n, 'date') !== false || strpos($n, 'fig') !== false || strpos($n, 'apricot') !== false || strpos($n, 'prune') !== false || strpos($n, 'mulberry') !== false || strpos($n, 'cherry') !== false || strpos($n, 'berry') !== false || strpos($n, 'fruit') !== false) {
                                                $subcat = 'dryfruits';
                                            } elseif (strpos($n, 'seed') !== false || strpos($n, 'chia') !== false || strpos($n, 'flax') !== false || strpos($n, 'pumpkin') !== false || strpos($n, 'sesame') !== false) {
                                                $subcat = 'seeds';
                                            } else {
                                                $subcat = 'nuts';
                                            }
                                        }
                                        $out[] = [
                                            'id' => $p['id'],
                                            'nameEn' => $p['name'],
                                            'nameKn' => $p['kn_name'] ?? $p['name'],
                                            'category' => $subcat,
                                            'variants' => $p['variants'],
                                            'emoji' => $p['emoji'] ?? '🌰',
                                            'description' => $p['description_en'] ?? 'Premium Dry Fruit or Nut product',
                                            'image' => $p['imageUrl'] ?? ''
                                        ];
                                    }
                                    echo json_encode($out);
                                    ?>;
                let cart = [];
                const basePath = 'dry-fruits.php';
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
                                badges.forEach(b => {
                                    b.innerText = cartArr.reduce((acc, i) => acc + (i.qty || 1), 0);
                                });

                                // Visual feedback on button
                                const btn = document.querySelector(`button.descriptive-cart-btn[data-pid="${productId}"]`) || document.querySelector(`button.descriptive-cart-btn[onclick="window.addToCart(${productId})"]`);
                                if (btn) {
                                    const originalHtml = btn.innerHTML;
                                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Added';
                                    btn.classList.add('added-flash');
                                    setTimeout(() => {
                                        btn.innerHTML = originalHtml;
                                        btn.classList.remove('added-flash');
                                    }, 2000);
                                }

                                if (typeof loadGlobalCart === "function") {
                                    loadGlobalCart();
                                }
                                if (typeof openCart === 'function') {
                                    openCart();
                                }

                            } else {
                                alert(data.error || 'Failed to add product');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert("Network error.");
                        });
                }

                function renderProducts() {
                    const filtered = products.filter(p => {
                        const matchCat = activeCategory === 'all' || p.category === activeCategory;
                        const matchSearch = searchTerm === '' || p.nameEn.toLowerCase().includes(searchTerm) || p.nameKn.toLowerCase().includes(searchTerm);
                        return matchCat && matchSearch;
                    });
                    if (!filtered.length) {
                        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:50px;">✨ No products found ✨</div>';
                        return;
                    }
                    grid.innerHTML = filtered.map(p => {
                        const defaultVariant = 0;
                        const defaultPrice = p.variants[defaultVariant]?.price || 0;
                        // Image HTML: if image exists, show <img>; else show emoji
                        let imageHtml = p.emoji;
                        if (p.image && p.image.trim() !== '') {
                            imageHtml = `<img src="${p.image}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='${p.emoji}'">`;
                        }
                        return `<div class="product-card" data-base="${p.id}">
        <div class="product-img">${imageHtml}</div>
        <div class="product-name">${p.nameEn}</div>
        ${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}
        <div class="product-desc">${p.description}</div>
        ${p.variants && p.variants.length > 1 
          ? `<select class="variant-select" data-base="${p.id}">
              ${p.variants.map((v, i) => `<option value="${i}" ${i===defaultVariant?'selected':''}>${v.label} - ₹${v.price}</option>`).join('')}
             </select>`
          : `<div class="variant-text" style="font-size:0.85rem; font-weight:600; color:#4f3e2e; text-align:center; padding:8px 10px; margin:8px auto; width:90%; background:#fefbf8; border-radius:50px; border:1px solid #ebdfd2;">${p.variants && p.variants[0] ? p.variants[0].label : '1 unit'}</div>`
        }
        <div class="price-row">
          <span class="price" id="price-${p.id}">₹${defaultPrice}</span>
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
                            if (priceSpan) priceSpan.innerText = `₹${price}`;
                        });
                    });
                }

                function renderTopPicks() {
                    const topProds = products.filter(p => p.id % 2 === 1).slice(0, 6);
                    if (!topProds.length) return;
                    topPicksGrid.innerHTML = topProds.map(p => {
                        const firstVariant = p.variants[0];
                        let imageHtml = p.emoji;
                        if (p.image && p.image.trim() !== '') {
                            imageHtml = `<img src="${p.image}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='${p.emoji}'">`;
                        }
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
                        activeCategory = this.dataset.cat;
                        renderProducts();
                    });
                });
                if (cartLink) {
                    cartLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (typeof openCart === 'function') {
                            openCart();
                        } else {
                            window.location.href = '../cart/cart.php';
                        }
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