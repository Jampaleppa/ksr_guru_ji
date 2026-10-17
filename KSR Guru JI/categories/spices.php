<?php
// ============================================================
// spices-masalas.php - Dynamic Spices & Masalas page
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
            $res = getSpiceProducts($search);
            if (empty($res)) {
                $fallbackRaw = getFallbackSpices();
                $res = [];
                foreach ($fallbackRaw as $item) {
                    $res[] = [
                        'id' => $item['id'],
                        'name' => $item['nameEn'],
                        'kn_name' => $item['nameKn'] ?? '',
                        'category' => $item['category'] ?? 'whole',
                        'price' => $item['price'],
                        'image_url' => normalizeImagePath($item['imageUrl'] ?? ''),
                        'description_en' => $item['description'] ?? '',
                        'default_weight' => 250
                    ];
                }
                if ($search !== '') {
                    $res = array_filter($res, function ($p) use ($search) {
                        return (stripos($p['name'], $search) !== false ||
                            (isset($p['kn_name']) && stripos($p['kn_name'], $search) !== false) ||
                            (isset($p['description_en']) && stripos($p['description_en'], $search) !== false));
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
            $variantSize = $_POST['variantSize'] ?? '250g';
            if ($id) {
                $product = getProductById($id);
                if (!$product) {
                    // Fallback to static data
                    $fallback = getFallbackSpices();
                    foreach ($fallback as $fp) {
                        if ($fp['id'] == $id) {
                            $product = [
                                'id' => $fp['id'],
                                'name' => $fp['nameEn'],
                                'kn_name' => $fp['nameKn'] ?? '',
                                'price' => $fp['price'],
                                'icon' => 'fa-mortar-pestle'
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
                            'name' => $product['name'] . ' (' . $variantSize . ')',
                            'kn' => $product['kn_name'] ?? $product['name'],
                            'price' => $product['price'],
                            'qty' => 1,
                            'icon' => $product['icon'] ?? 'fa-mortar-pestle',
                            'variantSize' => $variantSize
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
// FETCH SPICE PRODUCTS - PDO version
// ============================================================
function getSpiceProducts(string $search = ''): array
{
    try {
        $pdo = getDBConnection();
        // Dynamic column checks for products table
        $schemaProd = getProductSchemaInfo();
        $descCol = $schemaProd["desc_col"];
        $imgCol = $schemaProd["img_col"];
        $weightCol = $schemaProd["has_weight"] ? 'p.weight' : '250';
        // Dynamic column check for categories table
        $schema = getProductSchemaInfo();
        $hasSlug = $schema["has_cat_slug"];
        $whereCategory = $hasSlug ? "(c.slug = 'spices-masalas' OR c.id = 'spices-masalas' OR c.name LIKE '%Spice%' OR c.name LIKE '%Masala%')" : "(c.name LIKE '%Spice%' OR c.name LIKE '%Masala%')";
        $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                       c.name AS category_name, c.kn_name AS category_kn,
                       {$descCol} AS description_en, {$imgCol} AS image_url, {$weightCol} AS default_weight
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
            $row['description_en'] = $row['description_en'] ?? 'Premium quality spice';
            $row['default_weight'] = $row['default_weight'] ?? 250; // default 250g
            if (!empty($row['image_url'])) {
                $row['image_url'] = normalizeImagePath($row['image_url']);
            }
            $row['category'] = $row['category_name'] ?? 'whole';
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}
// ============================================================
// FALLBACK: If no products from DB, load static data
// ============================================================
function getFallbackSpices(): array
{
    static $fallback = null;
    if ($fallback !== null) {
        return $fallback;
    }
    $jsonPath = __DIR__ . '/spices-fallback.json';
    if (file_exists($jsonPath)) {
        $fallback = json_decode(file_get_contents($jsonPath), true);
        if (is_array($fallback)) {
            return $fallback;
        }
    }
    // Full product list from your static data (shortened for brevity, but complete)
    $data = [
        ['id' => 1, 'nameEn' => 'Turmeric Powder', 'nameKn' => 'ಅರಿಶಿನ ಪುಡಿ', 'category' => 'ground', 'price' => 220, 'imageUrl' => 'images/Turmeric Powder.jpg', 'description' => 'Bright yellow, earthy flavor, essential for curries.'],
        ['id' => 2, 'nameEn' => 'Red Chilli Powder', 'nameKn' => 'ಕೆಂಪು ಮೆಣಸಿನ ಪುಡಿ', 'category' => 'ground', 'price' => 300, 'imageUrl' => 'images/Red chili powder.jpg', 'description' => 'Hot and vibrant, adds heat to dishes.'],
        ['id' => 3, 'nameEn' => 'Kashmiri Chilli Powder', 'nameKn' => 'ಕಾಶ್ಮೀರಿ ಮೆಣಸಿನ ಪುಡಿ', 'category' => 'ground', 'price' => 420, 'imageUrl' => 'images/Chilli Powder Standard Red Chilli 180.jpg', 'description' => 'Mild heat, brilliant red color.'],
        ['id' => 4, 'nameEn' => 'Coriander Powder', 'nameKn' => 'ಕೊತ್ತಂಬರಿ ಪುಡಿ', 'category' => 'ground', 'price' => 240, 'imageUrl' => 'images/Coriander Powder.jpg', 'description' => 'Citrusy, nutty, foundation of Indian cooking.'],
        ['id' => 5, 'nameEn' => 'Cumin Seeds', 'nameKn' => 'ಜೀರಿಗೆ', 'category' => 'whole', 'price' => 520, 'imageUrl' => 'images/Cumin Seeds.jpg', 'description' => 'Earthy, warm, essential tempering spice.'],
        ['id' => 6, 'nameEn' => 'Cumin Powder', 'nameKn' => 'ಜೀರಿಗೆ ಪುಡಿ', 'category' => 'ground', 'price' => 540, 'imageUrl' => 'images/Organic Cumin Powder.jpg', 'description' => 'Ground cumin for easy use in curries.'],
        ['id' => 7, 'nameEn' => 'Black Pepper', 'nameKn' => 'ಕರಿಮೆಣಸು', 'category' => 'whole', 'price' => 900, 'imageUrl' => 'images/Black Pepper.jpg', 'description' => 'Pungent, sharp, "king of spices".'],
        ['id' => 8, 'nameEn' => 'White Pepper', 'nameKn' => 'ಬಿಳಿ ಮೆಣಸು', 'category' => 'whole', 'price' => 1200, 'imageUrl' => 'images/White Pepper.jpg', 'description' => 'Milder, used in light-colored dishes.'],
        ['id' => 9, 'nameEn' => 'Mustard Seeds', 'nameKn' => 'ಸಾಸಿವೆ', 'category' => 'whole', 'price' => 180, 'imageUrl' => 'images/Mustard Seeds.jpg', 'description' => 'Nutty, pungent when cracked.'],
        ['id' => 10, 'nameEn' => 'Fenugreek Seeds', 'nameKn' => 'ಮೆಂತ್ಯ', 'category' => 'whole', 'price' => 160, 'imageUrl' => 'images/Fenugreek Seeds.jpg', 'description' => 'Slightly bitter, used in pickles and curries.'],
        ['id' => 11, 'nameEn' => 'Fennel Seeds', 'nameKn' => 'ಸೋಂಪು', 'category' => 'whole', 'price' => 420, 'imageUrl' => 'images/Fennel Seeds.jpg', 'description' => 'Sweet, licorice-like, used as mouth freshener.'],
        ['id' => 12, 'nameEn' => 'Ajwain Seeds', 'nameKn' => 'ಓಮ', 'category' => 'whole', 'price' => 480, 'imageUrl' => 'images/Ajwain Seeds.jpg', 'description' => 'Thyme-like, aids digestion.'],
        ['id' => 13, 'nameEn' => 'Nigella Seeds', 'nameKn' => 'ಕೃಷ್ಣ ಜೀರಿಗೆ', 'category' => 'whole', 'price' => 600, 'imageUrl' => 'images/Nigella Seeds.jpg', 'description' => 'Black cumin, onion-like flavor.'],
        ['id' => 14, 'nameEn' => 'Poppy Seeds', 'nameKn' => 'ಗಸಗಸೆ', 'category' => 'whole', 'price' => 1400, 'imageUrl' => 'images/Poppy Seeds.jpg', 'description' => 'Nutty, used in rich gravies.'],
        ['id' => 15, 'nameEn' => 'Cardamom', 'nameKn' => 'ಏಲಕ್ಕಿ', 'category' => 'whole', 'price' => 3200, 'imageUrl' => 'images/Cardamom.jpg', 'description' => 'Aromatic, sweet, queen of spices.'],
        ['id' => 16, 'nameEn' => 'Black Cardamom', 'nameKn' => 'ಕರಿ ಏಲಕ್ಕಿ', 'category' => 'whole', 'price' => 1200, 'imageUrl' => 'images/Black Cardamom.jpg', 'description' => 'Smoky, robust, used in biryanis.'],
        ['id' => 17, 'nameEn' => 'Cloves', 'nameKn' => 'ಲವಂಗ', 'category' => 'whole', 'price' => 900, 'imageUrl' => 'images/Cloves 1860.jpg', 'description' => 'Intense, sweet, numbing aroma.'],
        ['id' => 18, 'nameEn' => 'Cinnamon', 'nameKn' => 'ದಾಲ್ಚಿನ್ನಿ', 'category' => 'whole', 'price' => 620, 'imageUrl' => 'images/Cinnamon.jpg', 'description' => 'Warm, sweet, woody bark.'],
        ['id' => 19, 'nameEn' => 'Bay Leaves', 'nameKn' => 'ತೆಂಗಿನ ಕಾಯಿ ಎಲೆ', 'category' => 'whole', 'price' => 320, 'imageUrl' => 'images/Bay Leaves.jpg', 'description' => 'Herbal, used in rice and curries.'],
        ['id' => 20, 'nameEn' => 'Star Anise', 'nameKn' => 'ತಾರೆ ಸೋಂಪು', 'category' => 'whole', 'price' => 850, 'imageUrl' => 'images/Star Anise.jpg', 'description' => 'Licorice flavor, star-shaped.'],
        ['id' => 21, 'nameEn' => 'Nutmeg', 'nameKn' => 'ಜಾಯಿಕಾಯಿ', 'category' => 'whole', 'price' => 900, 'imageUrl' => 'images/Nutmeg.jpg', 'description' => 'Sweet, nutty, grate fresh.'],
        ['id' => 22, 'nameEn' => 'Mace', 'nameKn' => 'ಜಾಪತ್ರೆ', 'category' => 'whole', 'price' => 1400, 'imageUrl' => 'images/Mace.jpg', 'description' => 'Delicate, similar to nutmeg.'],
        ['id' => 23, 'nameEn' => 'Asafoetida (Hing)', 'nameKn' => 'ಹಿಂಗು', 'category' => 'specialty', 'price' => 3500, 'imageUrl' => 'images/Specialty Spices Asafoetida.jpg', 'description' => 'Pungent resin, replaces onion/garlic.'],
        ['id' => 24, 'nameEn' => 'Dry Ginger Powder', 'nameKn' => 'ಶುಂಠಿ ಪುಡಿ', 'category' => 'ground', 'price' => 400, 'imageUrl' => 'images/Dry Ginger Powder.jpg', 'description' => 'Warm, zesty, digestive.'],
        ['id' => 25, 'nameEn' => 'Garlic Powder', 'nameKn' => 'ಬೆಳ್ಳುಳ್ಳಿ ಪುಡಿ', 'category' => 'ground', 'price' => 420, 'imageUrl' => 'images/Garlic Powder.jpg', 'description' => 'Savory, concentrated garlic flavor.'],
        ['id' => 26, 'nameEn' => 'Onion Powder', 'nameKn' => 'ಈರುಳ್ಳಿ ಪುಡಿ', 'category' => 'ground', 'price' => 380, 'imageUrl' => 'images/Onion Powder.jpg', 'description' => 'Sweet, savory, for rubs and dips.'],
        ['id' => 27, 'nameEn' => 'Curry Leaves Dry', 'nameKn' => 'ಕರಿಬೇವಿನ ಪುಡಿ', 'category' => 'ground', 'price' => 200, 'imageUrl' => 'images/Curry Leaves Dry.jpg', 'description' => 'Aromatic, essential for South Indian dishes.'],
        ['id' => 28, 'nameEn' => 'Tamarind Powder', 'nameKn' => 'ಹುಣಸೆ ಪುಡಿ', 'category' => 'ground', 'price' => 280, 'imageUrl' => 'images/Tamarind Powder.jpg', 'description' => 'Tangy, souring agent.'],
        ['id' => 29, 'nameEn' => 'Mango Powder (Amchur)', 'nameKn' => 'ಮಾವಿನ ಪುಡಿ', 'category' => 'ground', 'price' => 360, 'imageUrl' => 'images/Mango Powder (Amchur).jpg', 'description' => 'Tangy, fruity, used in chaats.'],
        ['id' => 30, 'nameEn' => 'Saffron', 'nameKn' => 'ಕೇಸರಿ', 'category' => 'specialty', 'price' => 180000, 'imageUrl' => 'images/Saffron.jpg', 'description' => 'Exotic, floral, world\'s most expensive spice.'],
        ['id' => 31, 'nameEn' => 'Garam Masala', 'nameKn' => 'ಗರಂ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/Garam Masala.jpg', 'description' => 'Warm spice blend for finishing dishes.'],
        ['id' => 32, 'nameEn' => 'Kitchen King Masala', 'nameKn' => 'ಕಿಚನ್ ಕಿಂಗ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Kitchen King Masala.jpg', 'description' => 'All-purpose curry masala.'],
        ['id' => 33, 'nameEn' => 'Biryani Masala', 'nameKn' => 'ಬಿರಿಯಾನಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 480, 'imageUrl' => 'images/Biryani Masala.jpg', 'description' => 'Fragrant mix for biryani rice.'],
        ['id' => 34, 'nameEn' => 'Pav Bhaji Masala', 'nameKn' => 'ಪಾವ್ ಭಾಜಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Pav Bhaji Masala.jpg', 'description' => 'Street-style spicy blend.'],
        ['id' => 35, 'nameEn' => 'Chaat Masala', 'nameKn' => 'ಚಾಟ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 380, 'imageUrl' => 'images/Chaat Masala.jpg', 'description' => 'Tangy, spicy, for snacks and fruits.'],
        ['id' => 36, 'nameEn' => 'Tandoori Masala', 'nameKn' => 'ತಂದೂರಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 450, 'imageUrl' => 'images/Tandoori Masala.jpg', 'description' => 'Smoky, red, for grilled meats.'],
        ['id' => 37, 'nameEn' => 'Butter Chicken Masala', 'nameKn' => 'ಬಟರ್ ಚಿಕನ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 480, 'imageUrl' => 'images/Butter Chicken Masala.jpg', 'description' => 'Creamy, rich blend for butter chicken.'],
        ['id' => 38, 'nameEn' => 'Chicken Masala', 'nameKn' => 'ಚಿಕನ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 450, 'imageUrl' => 'images/Chicken Masala.jpg', 'description' => 'Robust blend for chicken curries.'],
        ['id' => 39, 'nameEn' => 'Mutton Masala', 'nameKn' => 'ಮಟನ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 460, 'imageUrl' => 'images/Mutton Masala.jpg', 'description' => 'Hearty, bold for mutton dishes.'],
        ['id' => 40, 'nameEn' => 'Fish Masala', 'nameKn' => 'ಮೀನು ಮಸಾಲಾ', 'category' => 'blend', 'price' => 430, 'imageUrl' => 'images/Fish Masala.jpg', 'description' => 'Tangy, coastal-style for fish.'],
        ['id' => 41, 'nameEn' => 'Paneer Masala', 'nameKn' => 'ಪನೀರ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Paneer Masala.jpg', 'description' => 'Mild, creamy for paneer dishes.'],
        ['id' => 42, 'nameEn' => 'Shahi Paneer Masala', 'nameKn' => 'ಶಾಹಿ ಪನೀರ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 440, 'imageUrl' => 'images/Shahi Paneer Masala.jpg', 'description' => 'Royal, rich blend for paneer.'],
        ['id' => 43, 'nameEn' => 'Rajma Masala', 'nameKn' => 'ರಾಜ್ಮಾ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Rajma Masala.jpg', 'description' => 'Punjabi-style for kidney beans.'],
        ['id' => 44, 'nameEn' => 'Chole Masala', 'nameKn' => 'ಚೋಲೆ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Chole Masala.jpg', 'description' => 'Tangy, spicy for chickpeas.'],
        ['id' => 45, 'nameEn' => 'Dal Fry Masala', 'nameKn' => 'ದಾಲ್ ಫ್ರೈ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 380, 'imageUrl' => 'images/Dal Fry Masala.jpg', 'description' => 'Tempering mix for lentils.'],
        ['id' => 46, 'nameEn' => 'Sambar Powder', 'nameKn' => 'ಸಾಂಬಾರ್ ಪುಡಿ', 'category' => 'blend', 'price' => 380, 'imageUrl' => 'images/Sambar Powder.jpg', 'description' => 'South Indian lentil stew base.'],
        ['id' => 47, 'nameEn' => 'Rasam Powder', 'nameKn' => 'ರಸಂ ಪುಡಿ', 'category' => 'blend', 'price' => 360, 'imageUrl' => 'images/Rasam Powder.jpg', 'description' => 'Peppery, tangy soup base.'],
        ['id' => 48, 'nameEn' => 'Idli Podi', 'nameKn' => 'ಇಡ್ಲಿ ಪುಡಿ', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Idli Podi.jpg', 'description' => 'Chutney powder for idli/dosa.'],
        ['id' => 49, 'nameEn' => 'Vangi Bath Powder', 'nameKn' => 'ವಂಗಿ ಬಾತ್ ಪುಡಿ', 'category' => 'blend', 'price' => 450, 'imageUrl' => 'images/Vangi Bath Powder.jpg', 'description' => 'Eggplant rice spice mix.'],
        ['id' => 50, 'nameEn' => 'Bisi Bele Bath Powder', 'nameKn' => 'ಬಿಸಿ ಬೇಳೆ ಬಾತ್ ಪುಡಿ', 'category' => 'blend', 'price' => 460, 'imageUrl' => 'images/Bisi Bele Bath Powder.jpg', 'description' => 'Karnataka-style rice spice blend.'],
        ['id' => 51, 'nameEn' => 'Dry Red Chilli', 'nameKn' => 'ಒಣಗಿದ ಕೆಂಪು ಮೆಣಸು', 'category' => 'whole', 'price' => 240, 'imageUrl' => 'images/Dry Red Chilli.jpg', 'description' => 'Whole dried red chilies for tempering.'],
        ['id' => 52, 'nameEn' => 'Dry Coriander Seeds', 'nameKn' => 'ಕೊತ್ತಂಬರಿ ಬೀಜ', 'category' => 'whole', 'price' => 200, 'imageUrl' => 'images/Dry Coriander Seeds.jpg', 'description' => 'Citrusy seeds, grind fresh.'],
        ['id' => 53, 'nameEn' => 'Sesame Seeds White', 'nameKn' => 'ಬಿಳಿ ಎಳ್ಳು', 'category' => 'whole', 'price' => 240, 'imageUrl' => 'images/Sesame Seeds White.jpg', 'description' => 'Nutty, used in breads and chutneys.'],
        ['id' => 54, 'nameEn' => 'Sesame Seeds Black', 'nameKn' => 'ಕರಿ ಎಳ್ಳು', 'category' => 'whole', 'price' => 280, 'imageUrl' => 'images/Sesame Seeds Black.jpg', 'description' => 'Earthy, used in Asian cooking.'],
        ['id' => 55, 'nameEn' => 'Dill Seeds', 'nameKn' => 'ಸಬ್ಬಸಿಗೆ ಬೀಜ', 'category' => 'whole', 'price' => 320, 'imageUrl' => 'images/Dill Seeds.jpg', 'description' => 'Aromatic, like dill weed.'],
        ['id' => 56, 'nameEn' => 'Caraway Seeds', 'nameKn' => 'ಶಾಹಿ ಜೀರಿಗೆ', 'category' => 'whole', 'price' => 420, 'imageUrl' => 'images/Caraway Seeds.jpg', 'description' => 'Rye bread flavor, digestive.'],
        ['id' => 57, 'nameEn' => 'Panch Phoron Mix', 'nameKn' => 'ಪಂಚ ಫೊರನ್', 'category' => 'blend', 'price' => 450, 'imageUrl' => 'images/Panch Phoron Mix.jpg', 'description' => 'Bengali five-spice blend.'],
        ['id' => 58, 'nameEn' => 'Paprika', 'nameKn' => 'ಪಾಪ್ರಿಕಾ', 'category' => 'ground', 'price' => 450, 'imageUrl' => 'images/Paprika.jpg', 'description' => 'Sweet, mild red pepper powder.'],
        ['id' => 59, 'nameEn' => 'Smoked Paprika', 'nameKn' => 'ಹೊಗೆಯ ಪಾಪ್ರಿಕಾ', 'category' => 'ground', 'price' => 520, 'imageUrl' => 'images/Smoked Paprika.jpg', 'description' => 'Deep, smoky flavor.'],
        ['id' => 60, 'nameEn' => 'Oregano', 'nameKn' => 'ಒರೆಗಾನೊ', 'category' => 'specialty', 'price' => 320, 'imageUrl' => 'images/Oregano.jpg', 'description' => 'Mediterranean herb for pizza.'],
        ['id' => 61, 'nameEn' => 'Thyme', 'nameKn' => 'ಥೈಮ್', 'category' => 'specialty', 'price' => 350, 'imageUrl' => 'images/Thyme.jpg', 'description' => 'Earthy, floral, for roasts.'],
        ['id' => 62, 'nameEn' => 'Rosemary', 'nameKn' => 'ರೋಸ್ಮರಿ', 'category' => 'specialty', 'price' => 360, 'imageUrl' => 'images/Rosemary.jpg', 'description' => 'Pine-like, for lamb and potatoes.'],
        ['id' => 63, 'nameEn' => 'Basil', 'nameKn' => 'ತುಳಸಿ', 'category' => 'specialty', 'price' => 300, 'imageUrl' => 'images/Basil.jpg', 'description' => 'Sweet basil for Italian dishes.'],
        ['id' => 64, 'nameEn' => 'Cilantro (Coriander Leaves)', 'nameKn' => 'ಕೊತ್ತಂಬರಿ ಸೊಪ್ಪು', 'category' => 'specialty', 'price' => 200, 'imageUrl' => 'images/Cilantro (Coriander Leaves).jpg', 'description' => 'Fresh, citrusy herb.'],
        ['id' => 65, 'nameEn' => 'Mint', 'nameKn' => 'ಪುದೀನಾ', 'category' => 'specialty', 'price' => 250, 'imageUrl' => 'images/Mint.jpg', 'description' => 'Cooling, for chutneys and teas.'],
        ['id' => 66, 'nameEn' => 'Taco Seasoning', 'nameKn' => 'ಟಾಕೊ ಸೀಸನಿಂಗ್', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Taco Seasoning.jpg', 'description' => 'Mexican-style spice mix.'],
        ['id' => 67, 'nameEn' => 'Cajun Seasoning', 'nameKn' => 'ಕಜುನ್ ಸೀಸನಿಂಗ್', 'category' => 'blend', 'price' => 440, 'imageUrl' => 'images/Cajun Seasoning.jpg', 'description' => 'Spicy, herby, for Louisiana cuisine.'],
        ['id' => 68, 'nameEn' => 'Organic Turmeric', 'nameKn' => 'ಸಾವಯವ ಅರಿಶಿನ', 'category' => 'specialty', 'price' => 360, 'imageUrl' => 'images/Organic Turmeric.jpg', 'description' => 'Certified organic, high curcumin.'],
        ['id' => 69, 'nameEn' => 'Organic Chilli Powder', 'nameKn' => 'ಸಾವಯವ ಮೆಣಸಿನ ಪುಡಿ', 'category' => 'specialty', 'price' => 420, 'imageUrl' => 'images/Organic Chilli Powder.jpg', 'description' => 'Organic, pure heat.'],
        ['id' => 70, 'nameEn' => 'Organic Coriander Powder', 'nameKn' => 'ಸಾವಯವ ಕೊತ್ತಂಬರಿ ಪುಡಿ', 'category' => 'specialty', 'price' => 380, 'imageUrl' => 'images/Organic Coriander Powder.jpg', 'description' => 'Organic, fragrant.'],
        ['id' => 71, 'nameEn' => 'Organic Cumin Powder', 'nameKn' => 'ಸಾವಯವ ಜೀರಿಗೆ ಪುಡಿ', 'category' => 'specialty', 'price' => 420, 'imageUrl' => 'images/Organic Cumin Powder.jpg', 'description' => 'Organic, earthy.'],
        ['id' => 72, 'nameEn' => 'Organic Garam Masala', 'nameKn' => 'ಸಾವಯವ ಗರಂ ಮಸಾಲಾ', 'category' => 'specialty', 'price' => 480, 'imageUrl' => 'images/Organic Garam Masala.jpg', 'description' => 'Organic blend, no additives.'],
        ['id' => 73, 'nameEn' => 'Organic Curry Powder', 'nameKn' => 'ಸಾವಯವ ಕರಿ ಪುಡಿ', 'category' => 'specialty', 'price' => 420, 'imageUrl' => 'images/Organic Curry Powder.jpg', 'description' => 'Organic, mild curry blend.'],
        ['id' => 74, 'nameEn' => 'Organic Pepper', 'nameKn' => 'ಸಾವಯವ ಮೆಣಸು', 'category' => 'specialty', 'price' => 1100, 'imageUrl' => 'images/Organic Pepper.jpg', 'description' => 'Organic black peppercorns.'],
        ['id' => 75, 'nameEn' => 'Organic Cinnamon', 'nameKn' => 'ಸಾವಯವ ದಾಲ್ಚಿನ್ನಿ', 'category' => 'specialty', 'price' => 720, 'imageUrl' => 'images/Organic Cinnamon.jpg', 'description' => 'Ceylon cinnamon, organic.'],
        ['id' => 76, 'nameEn' => 'Organic Cardamom', 'nameKn' => 'ಸಾವಯವ ಏಲಕ್ಕಿ', 'category' => 'specialty', 'price' => 3500, 'imageUrl' => 'images/Organic Cardamom.jpg', 'description' => 'Premium green cardamom.'],
        ['id' => 77, 'nameEn' => 'Organic Cloves', 'nameKn' => 'ಸಾವಯವ ಲವಂಗ', 'category' => 'specialty', 'price' => 1100, 'imageUrl' => 'images/Organic Cloves.jpg', 'description' => 'Organic, aromatic cloves.'],
        ['id' => 78, 'nameEn' => 'Pepper Mix', 'nameKn' => 'ಮೆಣಸು ಮಿಶ್ರಣ', 'category' => 'blend', 'price' => 800, 'imageUrl' => 'images/Pepper Mix.jpg', 'description' => 'Black, white, green peppercorns.'],
        ['id' => 79, 'nameEn' => 'Lemon Pepper', 'nameKn' => 'ನಿಂಬೆ ಮೆಣಸು', 'category' => 'blend', 'price' => 620, 'imageUrl' => 'images/Lemon Pepper.jpg', 'description' => 'Citrusy, zesty seasoning.'],
        ['id' => 80, 'nameEn' => 'Garlic Pepper', 'nameKn' => 'ಬೆಳ್ಳುಳ್ಳಿ ಮೆಣಸು', 'category' => 'blend', 'price' => 620, 'imageUrl' => 'images/Garlic Pepper.jpg', 'description' => 'Savory, garlicky pepper blend.'],
        ['id' => 81, 'nameEn' => 'Peri Peri Masala', 'nameKn' => 'ಪೇರಿ ಪೇರಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/Peri Peri Masala.jpg', 'description' => 'African-Portuguese spicy blend.'],
        ['id' => 82, 'nameEn' => 'BBQ Spice Mix', 'nameKn' => 'ಬಾರ್ಬಿಕ್ಯೂ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/BBQ Spice Mix.jpg', 'description' => 'Smoky, sweet, for grilling.'],
        ['id' => 83, 'nameEn' => 'Curry Powder', 'nameKn' => 'ಕರಿ ಪುಡಿ', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Curry Powder.jpg', 'description' => 'Western-style curry blend.'],
        ['id' => 84, 'nameEn' => 'Pickle Masala', 'nameKn' => 'ಉಪ್ಪಿನಕಾಯಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Pickle Masala.jpg', 'description' => 'Spicy blend for pickling.'],
        ['id' => 85, 'nameEn' => 'Tea Masala', 'nameKn' => 'ಚಹಾ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 380, 'imageUrl' => 'images/Tea Masala.jpg', 'description' => 'Spices for masala chai.'],
        ['id' => 86, 'nameEn' => 'Jaljeera Powder', 'nameKn' => 'ಜಲಜೀರಾ ಪುಡಿ', 'category' => 'ground', 'price' => 320, 'imageUrl' => 'images/Jaljeera Powder.jpg', 'description' => 'Tangy cumin drink mix.'],
        ['id' => 87, 'nameEn' => 'Pani Puri Masala', 'nameKn' => 'ಪಾನಿ ಪೂರಿ ಮಸಾಲಾ', 'category' => 'ground', 'price' => 340, 'imageUrl' => 'images/Pani Puri Masala.jpg', 'description' => 'Spicy water for pani puri.'],
        ['id' => 88, 'nameEn' => 'Maggi Masala Mix', 'nameKn' => 'ಮ್ಯಾಗಿ ಮಸಾಲಾ ಮಿಕ್ಸ್', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Maggi Masala Mix.jpg', 'description' => 'Noodle seasoning blend.'],
        ['id' => 89, 'nameEn' => 'Pasta Seasoning', 'nameKn' => 'ಪಾಸ್ಟಾ ಸೀಸನಿಂಗ್', 'category' => 'blend', 'price' => 380, 'imageUrl' => 'images/Pasta Seasoning.jpg', 'description' => 'Italian herb mix for pasta.'],
        ['id' => 90, 'nameEn' => 'Pizza Seasoning', 'nameKn' => 'ಪಿಜ್ಜಾ ಸೀಸನಿಂಗ್', 'category' => 'blend', 'price' => 380, 'imageUrl' => 'images/Pizza Seasoning.jpg', 'description' => 'Oregano, basil, garlic for pizza.'],
        ['id' => 91, 'nameEn' => 'Ginger Garlic Powder', 'nameKn' => 'ಶುಂಠಿ ಬೆಳ್ಳುಳ್ಳಿ ಪುಡಿ', 'category' => 'ground', 'price' => 420, 'imageUrl' => 'images/Ginger Garlic Powder.jpg', 'description' => 'Convenient ginger-garlic blend.'],
        ['id' => 92, 'nameEn' => 'Dry Mint Powder', 'nameKn' => 'ಪುದೀನ ಪುಡಿ', 'category' => 'ground', 'price' => 360, 'imageUrl' => 'images/Dry Mint Powder.jpg', 'description' => 'Dried mint for chutneys.'],
        ['id' => 93, 'nameEn' => 'Lemongrass Dry', 'nameKn' => 'ಲೆಮನ್ ಗ್ರಾಸ್ ಪುಡಿ', 'category' => 'ground', 'price' => 380, 'imageUrl' => 'images/Lemongrass Dry.jpg', 'description' => 'Citrusy, for soups and teas.'],
        ['id' => 94, 'nameEn' => 'Tamarind Paste Powder', 'nameKn' => 'ಹುಣಸೆ ಪೇಸ್ಟ್ ಪುಡಿ', 'category' => 'ground', 'price' => 360, 'imageUrl' => 'images/Tamarind Paste Powder.jpg', 'description' => 'Concentrated tamarind powder.'],
        ['id' => 95, 'nameEn' => 'Spice Grinder Mix', 'nameKn' => 'ಸ್ಪೈಸ್ ಮಿಕ್ಸ್', 'category' => 'blend', 'price' => 480, 'imageUrl' => 'images/Spice Grinder Mix.jpg', 'description' => 'Whole spices for grinding.'],
        ['id' => 96, 'nameEn' => 'Premium Garam Masala', 'nameKn' => 'ಪ್ರೀಮಿಯಂ ಗರಂ ಮಸಾಲಾ', 'category' => 'specialty', 'price' => 560, 'imageUrl' => 'images/Premium Garam Masala.jpg', 'description' => 'Deluxe aromatic blend.'],
        ['id' => 97, 'nameEn' => 'Hyderabadi Biryani Masala', 'nameKn' => 'ಹೈದರಾಬಾದಿ ಬಿರಿಯಾನಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/Hyderabadi Biryani Masala.jpg', 'description' => 'Authentic Hyderabadi flavor.'],
        ['id' => 98, 'nameEn' => 'Bombay Biryani Masala', 'nameKn' => 'ಬಾಂಬೆ ಬಿರಿಯಾನಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/Bombay Biryani Masala.jpg', 'description' => 'Mumbai-style biryani blend.'],
        ['id' => 99, 'nameEn' => 'Kolhapuri Masala', 'nameKn' => 'ಕೊಲ್ಹಾಪುರಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/Kolhapuri Masala.jpg', 'description' => 'Fiery Maharashtrian blend.'],
        ['id' => 100, 'nameEn' => 'Malvani Masala', 'nameKn' => 'ಮಾಲ್ವಾಣಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 540, 'imageUrl' => 'images/Malvani Masala.jpg', 'description' => 'Coastal Konkani spice mix.'],
        ['id' => 101, 'nameEn' => 'Goda Masala', 'nameKn' => 'ಗೋಡಾ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 560, 'imageUrl' => 'images/Goda Masala.jpg', 'description' => 'Sweet-spicy Maharashtrian blend.'],
        ['id' => 102, 'nameEn' => 'Gunpowder Masala', 'nameKn' => 'ಗನ್ ಪೌಡರ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/Gunpowder Masala.jpg', 'description' => 'Spicy chutney powder.'],
        ['id' => 103, 'nameEn' => 'Kashmiri Garam Masala', 'nameKn' => 'ಕಾಶ್ಮೀರಿ ಗರಂ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 560, 'imageUrl' => 'images/Kashmiri Garam Masala.jpg', 'description' => 'Subtle, aromatic Kashmiri blend.'],
        ['id' => 104, 'nameEn' => 'Butter Paneer Masala', 'nameKn' => 'ಬಟರ್ ಪನೀರ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/Butter Paneer Masala.jpg', 'description' => 'Rich gravy spice mix.'],
        ['id' => 105, 'nameEn' => 'Veg Curry Masala', 'nameKn' => 'ವೆಜ್ ಕರಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/Veg Curry Masala.jpg', 'description' => 'Vegetable curry blend.'],
        ['id' => 106, 'nameEn' => 'Dal Tadka Masala', 'nameKn' => 'ದಾಲ್ ತಡ್ಕಾ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/Dal Tadka Masala.jpg', 'description' => 'Tempering spice for dal.'],
        ['id' => 107, 'nameEn' => 'Meat Curry Masala', 'nameKn' => 'ಮೀಟ್ ಕರಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 540, 'imageUrl' => 'images/Meat Curry Masala.jpg', 'description' => 'Hearty blend for meat curries.'],
        ['id' => 108, 'nameEn' => 'Dry Curry Leaves Powder', 'nameKn' => 'ಕರಿಬೇವಿನ ಪುಡಿ', 'category' => 'ground', 'price' => 320, 'imageUrl' => 'images/Dry Curry Leaves Powder.jpg', 'description' => 'Aromatic curry leaf powder.'],
        ['id' => 109, 'nameEn' => 'Curry Base Masala', 'nameKn' => 'ಕರಿ ಬೇಸ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 480, 'imageUrl' => 'images/Curry Base Masala.jpg', 'description' => 'Foundation for many curries.'],
        ['id' => 110, 'nameEn' => 'Spice Gift Pack', 'nameKn' => 'ಮಸಾಲೆ ಗಿಫ್ಟ್ ಪ್ಯಾಕ್', 'category' => 'specialty', 'price' => 1200, 'imageUrl' => 'images/Spice Gift Pack.jpg', 'description' => 'Assorted spice sampler.'],
        ['id' => 111, 'nameEn' => 'Kitchen Spice Kit', 'nameKn' => 'ಕಿಚನ್ ಸ್ಪೈಸ್ ಕಿಟ್', 'category' => 'specialty', 'price' => 1500, 'imageUrl' => 'images/Kitchen Spice Kit.jpg', 'description' => 'Complete kitchen spice set.'],
        ['id' => 112, 'nameEn' => 'Spice Combo Pack', 'nameKn' => 'ಮಸಾಲೆ ಕಾಂಬೋ ಪ್ಯಾಕ್', 'category' => 'specialty', 'price' => 900, 'imageUrl' => 'images/Spice Combo Pack.jpg', 'description' => 'Value pack of essential spices.'],
        ['id' => 113, 'nameEn' => 'Whole Spice Combo', 'nameKn' => 'ಸಂಪೂರ್ಣ ಮಸಾಲೆ ಕಾಂಬೋ', 'category' => 'specialty', 'price' => 850, 'imageUrl' => 'images/Whole Spice Combo.jpg', 'description' => 'Whole spices collection.'],
        ['id' => 114, 'nameEn' => 'Premium Spice Box', 'nameKn' => 'ಪ್ರೀಮಿಯಂ ಸ್ಪೈಸ್ ಬಾಕ್ಸ್', 'category' => 'specialty', 'price' => 1600, 'imageUrl' => 'images/Premium Spice Box.jpg', 'description' => 'Luxury wooden spice box.'],
        ['id' => 115, 'nameEn' => 'Masala Gift Box', 'nameKn' => 'ಮಸಾಲಾ ಗಿಫ್ಟ್ ಬಾಕ್ಸ್', 'category' => 'specialty', 'price' => 1800, 'imageUrl' => 'images/Masala Gift Box.jpg', 'description' => 'Elegant gift box of blends.'],
        ['id' => 116, 'nameEn' => 'Exotic Spice Mix', 'nameKn' => 'ಎಕ್ಸೋಟಿಕ್ ಸ್ಪೈಸ್ ಮಿಕ್ಸ್', 'category' => 'blend', 'price' => 680, 'imageUrl' => 'images/Exotic Spice Mix.jpg', 'description' => 'Rare global spice blend.'],
        ['id' => 117, 'nameEn' => 'BBQ Rub Masala', 'nameKn' => 'ಬಾರ್ಬಿಕ್ಯೂ ರಬ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 620, 'imageUrl' => 'images/BBQ Rub Masala.jpg', 'description' => 'Dry rub for barbecues.'],
        ['id' => 118, 'nameEn' => 'Steak Seasoning', 'nameKn' => 'ಸ್ಟೀಕ್ ಸೀಸನಿಂಗ್', 'category' => 'blend', 'price' => 620, 'imageUrl' => 'images/Steak Seasoning.jpg', 'description' => 'Robust seasoning for steaks.'],
        ['id' => 119, 'nameEn' => 'Herb Mix', 'nameKn' => 'ಹರ್ಬ್ ಮಿಕ್ಸ್', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Herb Mix.jpg', 'description' => 'Dried herbs blend.'],
        ['id' => 120, 'nameEn' => 'Lemon Herb Mix', 'nameKn' => 'ನಿಂಬೆ ಹರ್ಬ್ ಮಿಕ್ಸ್', 'category' => 'blend', 'price' => 440, 'imageUrl' => 'images/Lemon Herb Mix.jpg', 'description' => 'Citrus-herb seasoning.'],
        ['id' => 121, 'nameEn' => 'Curry Leaf Powder', 'nameKn' => 'ಕರಿಬೇವಿನ ಪುಡಿ', 'category' => 'ground', 'price' => 360, 'imageUrl' => 'images/Curry Leaf Powder.jpg', 'description' => 'Pure dried curry leaf powder.'],
        ['id' => 122, 'nameEn' => 'Mint Masala', 'nameKn' => 'ಪುದೀನ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 380, 'imageUrl' => 'images/Mint Masala.jpg', 'description' => 'Mint-based chutney powder.'],
        ['id' => 123, 'nameEn' => 'Tangy Masala', 'nameKn' => 'ಟ್ಯಾಂಗಿ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Tangy Masala.jpg', 'description' => 'Sour and spicy blend.'],
        ['id' => 124, 'nameEn' => 'Spicy Curry Powder', 'nameKn' => 'ಸ್ಪೈಸಿ ಕರಿ ಪುಡಿ', 'category' => 'blend', 'price' => 420, 'imageUrl' => 'images/Spicy Curry Powder.jpg', 'description' => 'Extra-hot curry blend.'],
        ['id' => 125, 'nameEn' => 'House Blend Masala', 'nameKn' => 'ಹೌಸ್ ಬ್ಲೆಂಡ್ ಮಸಾಲಾ', 'category' => 'blend', 'price' => 480, 'imageUrl' => 'images/House Blend Masala.jpg', 'description' => 'Signature chef blend.'],
        ['id' => 126, 'nameEn' => 'Masala Chai Mix', 'nameKn' => 'ಮಸಾಲಾ ಚಹಾ ಮಿಕ್ಸ್', 'category' => 'blend', 'price' => 380, 'imageUrl' => 'images/Masala Chai Mix.jpg', 'description' => 'Spiced tea blend.'],
        ['id' => 127, 'nameEn' => 'Pumpkin Spice', 'nameKn' => 'ಪಂಪ್ಕಿನ್ ಸ್ಪೈಸ್', 'category' => 'blend', 'price' => 520, 'imageUrl' => 'images/Pumpkin Spice.jpg', 'description' => 'Cinnamon, nutmeg, ginger for desserts.'],
        ['id' => 128, 'nameEn' => 'Berbere Spice', 'nameKn' => 'ಬೆರ್ಬೆರೆ ಸ್ಪೈಸ್', 'category' => 'blend', 'price' => 580, 'imageUrl' => 'images/Berbere Spice.jpg', 'description' => 'Ethiopian hot spice blend.']
    ];
    $fallback = $data;
    file_put_contents($jsonPath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $fallback;
}
// Get products
$products = getSpiceProducts();
if (empty($products)) {
    $fallbackProducts = getFallbackSpices();
    foreach ($fallbackProducts as $item) {
        $products[] = [
            'id' => $item['id'],
            'name' => $item['nameEn'],
            'kn_name' => $item['nameKn'] ?? '',
            'category' => $item['category'] ?? 'whole',
            'price' => $item['price'],
            'image_url' => normalizeImagePath($item['imageUrl'] ?? ''),
            'description_en' => $item['description'] ?? '',
            'default_weight' => 250
        ];
    }
}
// Get cart count and total
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
// User info
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : 'Guest';
// Top picks (using the same IDs as static version)
$topPickIds = [1, 5, 7, 15, 23, 27, 28, 39];
$topPicks = array_filter($products, fn($p) => in_array($p['id'], $topPickIds));
if (empty($topPicks)) {
    $topPicks = array_slice($products, 0, 6);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>KSR Guru · Premium Spices & Masalas | Fresh from India</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        /* ===== SAME CSS AS STATIC VERSION ===== */
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
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            text-decoration: none;
            color: inherit;
            font-weight: 600;
            font-size: 0.95rem;
            position: relative;
        }

        .cart-link i {
            font-size: 1.4rem;
            color: #b45f2b;
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
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
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
            max-width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 20px;
            background: #fcf4ec;
            margin: 0 auto 12px;
            display: block;
        }

        .top-pick-name {
            font-weight: 700;
            font-size: 1rem;
            color: #2f2216;
        }

        .top-pick-price {
            font-size: 1rem;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            padding: 20px 18px 22px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
            border: 1px solid #f2e5d9;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.08);
        }

        .product-image {
            width: 100%;
            max-width: 180px;
            height: 180px;
            margin: 0 auto 16px;
            border-radius: 28px;
            overflow: hidden;
            background: #fcf4ec;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s;
        }

        .product-card:hover .product-image img {
            transform: scale(1.02);
        }

        .product-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #2f2216;
            text-align: center;
        }

        .product-name-kn {
            font-size: 0.8rem;
            color: #7b5f45;
            text-align: center;
            margin-bottom: 8px;
        }

        .product-desc {
            font-size: 0.75rem;
            color: #8a6e54;
            text-align: center;
            margin-bottom: 12px;
            line-height: 1.4;
            padding: 0 4px;
        }

        .weight-select {
            width: 100%;
            background: #fef8f2;
            border: 1px solid #e6d9cd;
            border-radius: 50px;
            padding: 10px 12px;
            font-size: 0.85rem;
            margin: 10px 0;
            cursor: pointer;
            font-weight: 500;
            text-align: center;
        }

        .dynamic-price {
            font-weight: 800;
            font-size: 1.6rem;
            color: #b45f2b;
            text-align: center;
            margin: 8px 0 12px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            border-top: 1px solid #f0e3d7;
            padding-top: 14px;
        }

        .price small {
            font-size: 0.75rem;
            font-weight: 500;
            color: #a07c5e;
        }

        .add-btn {
            background: #b45f2b;
            border: none;
            color: white;
            width: 46px;
            height: 46px;
            border-radius: 60px;
            font-size: 1.3rem;
            cursor: pointer;
            transition: 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .add-btn:active {
            transform: scale(0.95);
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
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 18px;
            }

            .top-picks-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }

            .product-image {
                max-width: 150px;
                height: 150px;
            }

            .dynamic-price {
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

        <div class="search-section">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
            </div>
            <div class="filter-pills" id="filterPills">
                <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
                <div class="pill" data-category="whole">Whole / ಸಂಪೂರ್ಣ</div>
                <div class="pill" data-category="ground">Ground / ಪುಡಿ</div>
                <div class="pill" data-category="blend">Blends / ಮಿಶ್ರಣ</div>
                <div class="pill" data-category="specialty">Specialty / ವಿಶೇಷ</div>
            </div>
        </div>
        <!-- Top Picks -->
        <div class="top-picks">
            <h3><i class="fas fa-fire"></i> Top Picks · Bestsellers</h3>
            <div class="top-picks-grid" id="topPicksGrid"></div>
        </div>
        <!-- All products -->
        <div class="product-grid" id="productGrid"></div>
    </div>
    <script>
        // ============================================================
        // JAVASCRIPT – SESSION-BASED CART VIA AJAX
        // ============================================================
        (function() {
            // ----- PRODUCT DATA FROM PHP (embedded) -----
            const products = <?php echo json_encode($products); ?>;
            // Normalize product images (convert Windows paths to web paths)
            products.forEach(p => {
                let img = p.image_url || p.image || '';
                if (img && !img.startsWith('http') && !img.startsWith('data:')) {
                    const idx = img.toLowerCase().indexOf('images');
                    if (idx !== -1) {
                        img = '../' + img.substring(idx).replace(/\\/g, '/');
                    } else if (!img.startsWith('../') && !img.startsWith('images/')) {
                        const clean = img.startsWith('/') ? img.substring(1) : img;
                        img = '../' + clean.replace(/\\/g, '/');
                    }
                }
                p.image_url = img;
            });
            // ----- WEIGHT OPTIONS -----
            const weightOptions = [5, 10, 50, 100, 150, 200, 250, 300, 500, 1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000];

            function formatWeightLabel(g) {
                return g >= 1000 ? (g / 1000) + 'kg' : g + 'g';
            }

            function calcPrice(product, grams) {
                return (product.price / 1000) * grams;
            }
            // ----- CART STATE (from PHP session) -----
            let cart = <?php echo json_encode($_SESSION['cart'] ?? []); ?>;
            let cartCount = <?php echo $cartCount; ?>;
            // ----- DOM refs -----
            const cartCountSpan = document.getElementById('cartCount');
            const productGrid = document.getElementById('productGrid');
            const topPicksGrid = document.getElementById('topPicksGrid');
            const searchInput = document.getElementById('searchInput');
            const filterPills = document.querySelectorAll('.pill');
            let activeCategory = 'all';
            let searchTerm = '';
            // ----- CART AJAX FUNCTIONS -----
            function fetchCart() {
                fetch('spices-masalas.php?ajax=cart')
                    .then(res => res.json())
                    .then(data => {
                        cart = data;
                        cartCount = cart.reduce((sum, i) => sum + i.qty, 0);
                        updateCartBadge();
                    })
                    .catch(console.error);
            }

            function updateCartBadge() {
                cartCountSpan.innerText = cartCount;
            }

            function addToCart(productId, grams) {
                const product = products.find(p => p.id === productId);
                if (!product) return;
                const weightLabel = formatWeightLabel(grams);
                const unitPrice = calcPrice(product, grams);
                const formData = new FormData();
                formData.append('id', productId);
                formData.append('qty', 1);
                formData.append('variant', weightLabel);
                formData.append('custom_price', unitPrice.toFixed(2));
                formData.append('name', product.name || product.nameEn || '');
                formData.append('kn', product.kn || product.nameKn || product.kn_name || '');
                formData.append('image', product.image_url || product.image || '');
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
            // ----- RENDER FUNCTIONS -----
            function renderTopPicks() {
                const topPickIds = [1, 5, 7, 15, 23, 27, 28, 39];
                const topItems = products.filter(p => topPickIds.includes(p.id));
                if (!topItems.length) {
                    // fallback: show first 6
                    topPicksGrid.innerHTML = '';
                    return;
                }
                topPicksGrid.innerHTML = topItems.slice(0, 6).map(p => {
                    const price250 = calcPrice(p, 250).toFixed(2);
                    return `
                <div class="top-pick-card">
                    <img src="${p.image_url}" class="top-pick-img" alt="" loading="lazy" onerror="this.src='https://placehold.co/130x130?text=Spice'">
                    <div class="top-pick-name">${p.name}</div>
                    <div class="top-pick-price">₹${price250} (250g)</div>
                    <button class="top-pick-btn" data-id="${p.id}"><i class="fas fa-cart-plus"></i></button>
                </div>
            `;
                }).join('');
                document.querySelectorAll('.top-pick-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const pid = parseInt(this.dataset.id);
                        addToCart(pid, 250);
                    });
                });
            }

            function renderProducts() {
                let filtered = products.filter(p => {
                    if (activeCategory !== 'all' && p.category !== activeCategory) return false;
                    const term = searchTerm.trim().toLowerCase();
                    if (term) {
                        return p.name.toLowerCase().includes(term) || (p.kn_name && p.kn_name.toLowerCase().includes(term));
                    }
                    return true;
                });
                if (!filtered.length) {
                    productGrid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No spices found ✨</div>`;
                    return;
                }
                productGrid.innerHTML = filtered.map(p => {
                    const defaultWeight = p.default_weight || 250;
                    const defaultPrice = calcPrice(p, defaultWeight).toFixed(2);
                    return `
                <div class="product-card">
                    <div class="product-image">
                        <img src="${p.image_url}" alt="" loading="lazy" onerror="this.src='https://placehold.co/180x180?text=Spice'">
                    </div>
                    <div class="product-name">${p.name}</div>
                    <div class="product-name-kn">${p.kn_name || ''}</div>
                    <div class="product-desc">${p.description_en || ''}</div>
                    <select class="weight-select" data-id="${p.id}">
                        ${weightOptions.map(g => `<option value="${g}" ${g === defaultWeight ? 'selected' : ''}>${formatWeightLabel(g)}</option>`).join('')}
                    </select>
                    <div class="dynamic-price" id="price-${p.id}">₹${defaultPrice}</div>
                    <div class="price-row">
                        <div class="price"><small>₹${p.price}/kg</small></div>
                        <button class="add-btn" data-id="${p.id}"><i class="fa-solid fa-cart-plus"></i></button>
                    </div>
                </div>
            `;
                }).join('');
                // dynamic price update on weight change
                document.querySelectorAll('.weight-select').forEach(select => {
                    const pid = parseInt(select.dataset.id);
                    const product = products.find(p => p.id === pid);
                    if (!product) return;
                    const updatePrice = () => {
                        const grams = parseInt(select.value);
                        const newPrice = calcPrice(product, grams);
                        const span = document.getElementById(`price-${pid}`);
                        if (span) span.innerText = `₹${newPrice.toFixed(2)}`;
                    };
                    select.addEventListener('change', updatePrice);
                    updatePrice();
                });
                // add-to-cart buttons
                document.querySelectorAll('.add-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        const pid = parseInt(this.dataset.id);
                        const card = this.closest('.product-card');
                        const weightSelect = card.querySelector('.weight-select');
                        const grams = parseInt(weightSelect.value);
                        addToCart(pid, grams);
                    });
                });
            }
            // ----- EVENT LISTENERS -----
            filterPills.forEach(pill => {
                pill.addEventListener('click', function() {
                    filterPills.forEach(p => p.classList.remove('active'));
                    this.classList.add('active');
                    activeCategory = this.dataset.category;
                    renderProducts();
                });
            });
            searchInput.addEventListener('input', function(e) {
                searchTerm = this.value;
                renderProducts();
            });
            // Cart link (opens modal or redirects)
            document.getElementById('cartLink').addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof openCart === 'function') {
                    openCart();
                } else {
                    window.location.href = '../cart/cart.php';
                }
            });
            // ----- INIT -----
            renderTopPicks();
            renderProducts();
            updateCartBadge();
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
                // Fallback load if cart is undefined or empty
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
                    const itemImgSrc = item.image ? normalizeImagePathJS(item.image) : '';
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
            // Setup Event Listeners for the sidebar elements
            function setupCartSidebarEvents() {
                const closeBtn = document.getElementById('closeCart');
                const overlay = document.getElementById('overlay');
                const continueBtn = document.getElementById('continueShopping');
                const checkoutBtn = document.getElementById('checkoutBtn');
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
                // Intercept clicks on cart icons/links in the header to open sidebar drawer
                const headerCartBtns = document.querySelectorAll('.cart-link, #cartLink, #cartInfoIcon');
                headerCartBtns.forEach(btn => {
                    // Clone node to drop all pre-existing, duplicate handlers
                    const clone = btn.cloneNode(true);
                    btn.parentNode.replaceChild(clone, btn);
                    clone.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openCart();
                    });
                });
                // Hide legacy cart overlay modal if it exists to prevent display conflicts
                const legacyOverlay = document.getElementById('cartOverlay');
                if (legacyOverlay) {
                    legacyOverlay.style.display = 'none';
                    legacyOverlay.style.pointerEvents = 'none';
                }
                window.changeQty = updateCartQty;
                window.removeItem = removeFromCart;
                // Sync cart list on initialize
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
            // Initialize
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', setupCartSidebarEvents);
            } else {
                setupCartSidebarEvents();
            }
        })();
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