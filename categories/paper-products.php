<?php
// ============================================================
// paper-products.php - Dynamic Paper Products page (MERGED)
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
// Get cart count
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
// User info
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : 'Guest';
// ============================================================
// FETCH PRODUCTS - Try from DB first, fallback to static data
// ============================================================
$categorySlug = 'paper-products';
$paperCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($paperCategory) {
    // Fetch products from this category
    $dbProducts = getProductsByCategory($paperCategory['id']);
    if (!empty($dbProducts)) {
        foreach ($dbProducts as $p) {
            // Build description from weight or other fields
            $weight = $p['weight'] ?? '1 pack';
            $productsData[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'kn' => $p['kn_name'] ?? $p['name'],
                'category' => $p['category_slug'] ?? 'other',
                'price' => $p['price'],
                'imageUrl' => $p['image_url'] ?? '',
                'weight' => $weight,
                'description_en' => $p['description_en'] ?? '',
                'description_kn' => $p['description_kn'] ?? ''
            ];
        }
    }
}
// Fallback to static data if no DB products found
if (empty($productsData)) {
    // ----- STATIC PRODUCT DATA (same as original HTML) -----
    $staticProducts = [];
    /**
     * Helper to add products
     */
    function addPaperProducts(array &$array, array $items, string $category, string $knSuffix): void {
        foreach ($items as $item) {
            $array[] = [
                'id' => count($array) + 1,
                'name' => $item['name'] . ' (' . $item['weight'] . ')',
                'kn' => $item['name'] . ' ' . $item['weight'] . ' - ' . $knSuffix,
                'category' => $category,
                'price' => $item['price'],
                'imageUrl' => $item['ImageUrl'] ?? '',
                'weight' => $item['weight'],
                'description_en' => $item['desc_en'] ?? '',
                'description_kn' => $item['desc_kn'] ?? ''
            ];
        }
    }
    // Define category suffixes
    $knSuffixes = [
        'tissues' => 'ಟಿಶ್ಯೂ',
        'towels' => 'ಪೇಪರ್ ಟವೆಲ್',
        'napkins' => 'ನ್ಯಾಪ್ಕಿನ್',
        'plates' => 'ಪ್ಲೇಟ್',
        'cups' => 'ಕಪ್'
    ];
    // ----- Tissues -----
    $tissues = [
        ['name' => 'Origami Soft Facial Tissue', 'ImageUrl' => 'images\\Origami 2 Ply Facial Tissues Pack 185.jpg', 'weight' => '200 sheets', 'price' => 190],
        ['name' => 'Premier Facial Tissue', 'ImageUrl' => 'images\\Tulips Premium Facial Tissue Paper Box 600.jpg', 'weight' => '200 sheets', 'price' => 600],
        ['name' => 'Paseo Facial Tissue', 'ImageUrl' => 'images\\Premium Aloe Facial Tissue 2600.jpg', 'weight' => '200 sheets', 'price' => 2600],
        ['name' => 'Bella Facial Tissue', 'ImageUrl' => 'images\\Bella Classic Comfort Maxi Softi Sanitary Pads 120.jpg', 'weight' => '180 sheets', 'price' => 120],
        ['name' => 'Soft N Cool Facial Tissue', 'ImageUrl' => 'images\\SoftTouch Premium Toilet Paper.jpg', 'weight' => '200 sheets', 'price' => 480],
        ['name' => 'Kleenex Facial Tissue', 'ImageUrl' => 'images\\Kleenex Ultra Soft Facial Tissues, 2 Cube Boxes  580.jpg', 'weight' => '160 sheets', 'price' => 580],
        ['name' => 'Origami Pocket Tissue', 'ImageUrl' => 'images\\Origami Klassic Premium Tissue Paper Napkins 55.jpg', 'weight' => '10 packs', 'price' => 60],
        ['name' => 'Paseo Pocket Tissue', 'ImageUrl' => 'images\\Paseo Aloe Vera 3 Ply ( 230.jpg', 'weight' => '3 Ply', 'price' => 230],
        ['name' => 'Premier Pocket Tissue', 'ImageUrl' => 'images\\Premium Kitchen Paper Towels.jpg', 'weight' => '10 packs', 'price' => 490],
        ['name' => 'SoftCare Facial Tissue', 'ImageUrl' => 'images\\SoftCare Facial Tissue 1400.jpg', 'weight' => '10 packs', 'price' => 1400],
        ['name' => 'Deluxe Facial Tissue Box', 'ImageUrl' => 'images\\Deluxe Facial Tissue Box.jpg', 'weight' => '200 sheets', 'price' => 100],
        ['name' => 'Silky Soft Facial Tissue', 'ImageUrl' => 'images\\Silky Soft Facial Tissue.jpg', 'weight' => '180 sheets', 'price' => 95],
        ['name' => 'Ultra Soft Facial Tissue', 'ImageUrl' => 'images\\Ultra Soft Facial Tissue.jpg', 'weight' => '200 sheets', 'price' => 110],
        ['name' => 'Premium Aloe Facial Tissue', 'ImageUrl' => 'images\\Premium Aloe Facial Tissue.jpg', 'weight' => '200 sheets', 'price' => 120],
        ['name' => 'Mint Fresh Facial Tissue', 'ImageUrl' => 'images\\Mint Fresh Facial Tissue.jpg', 'weight' => '200 sheets', 'price' => 105],
        ['name' => 'Travel Pocket Tissue Pack', 'ImageUrl' => 'images\\Travel Pocket Tissue Pack.jpg', 'weight' => '12 packs', 'price' => 40],
        ['name' => 'Kids Cartoon Pocket Tissue', 'ImageUrl' => 'images\\Kids Cartoon Pocket Tissue.jpg', 'weight' => '10 packs', 'price' => 38],
        ['name' => 'Eco Bamboo Facial Tissue', 'ImageUrl' => 'images\\Eco Bamboo Facial Tissue.jpg', 'weight' => '150 sheets', 'price' => 130],
        ['name' => 'Luxury Facial Tissue Box', 'ImageUrl' => 'images\\Luxury Facial Tissue Box.jpg', 'weight' => '200 sheets', 'price' => 140],
        ['name' => 'SoftCare Facial Tissue', 'ImageUrl' => 'images\\SoftCare Facial Tissue.jpg', 'weight' => '200 sheets', 'price' => 98]
    ];
    addPaperProducts($staticProducts, $tissues, 'tissues', $knSuffixes['tissues']);
    // ----- Paper Towels -----
    $towels = [
        ['name' => 'Origami Kitchen Towels', 'ImageUrl' => 'images\\SoftCare Kitchen Roll 160.jpg', 'weight' => '2 rolls', 'price' => 160],
        ['name' => 'Premier Kitchen Towels', 'ImageUrl' => 'images\\Premium Kitchen Paper Towels.jpg', 'weight' => '1 rolls', 'price' => 150],
        ['name' => 'Paseo Kitchen Towels', 'ImageUrl' => 'images\\Paseo Kitchen Towels.jpg', 'weight' => '2 rolls', 'price' => 175],
        ['name' => 'Bella Kitchen Towels', 'ImageUrl' => 'images\\Bella Jumbo Kitchen Roll 500.jpg', 'weight' => 'rolls', 'price' => 500],
        ['name' => 'Origami Mega Kitchen Roll', 'ImageUrl' => 'images\\Origami Non Woven Reusable & Washable Kitchen Towel Roll  200.jpg', 'weight' => '1 roll', 'price' => 210],
        ['name' => 'Premier Jumbo Kitchen Roll', 'ImageUrl' => 'images\\Premium Absorb Kitchen Roll 270.jpg', 'weight' => '1 roll', 'price' => 280],
        ['name' => 'StrongGrip Kitchen Towels', 'ImageUrl' => 'images\\StrongGrip Kitchen Towels 550.jpg', 'weight' => 'roll', 'price' => 560],
        ['name' => 'Beeta Tissue Kitchen Roll', 'ImageUrl' => 'images\\Beeta Tissue Kitchen Roll 198.jpg', 'weight' => '1 roll', 'price' => 200],
        ['name' => 'Softouch Kitchen Towel 4ply', 'ImageUrl' => 'images\\Softouch Kitchen Towel 4ply 220.jpg', 'weight' => 'rolls', 'price' => 220],
        ['name' => 'Kitchen Cleaning Paper Roll', 'ImageUrl' => 'images\\Kitchen Cleaning Paper Roll 260.jpg', 'weight' => '2 rolls', 'price' => 265],
        ['name' => 'Ultra Absorb Kitchen Towels', 'ImageUrl' => 'images\\Ultra Absorb Kitchen Towels.jpg', 'weight' => '2 rolls', 'price' => 170],
        ['name' => 'Premium Absorb Kitchen Roll', 'ImageUrl' => 'images\\Premium Absorb Kitchen Roll.jpg', 'weight' => '1 roll', 'price' => 120],
        ['name' => 'SoftTouch Kitchen Towels', 'ImageUrl' => 'images\\SoftTouch Kitchen Towels.jpg', 'weight' => '2 rolls', 'price' => 160],
        ['name' => 'Eco Friendly Kitchen Towels', 'ImageUrl' => 'images\\Eco Friendly Kitchen Towels.jpg', 'weight' => '2 rolls', 'price' => 180],
        ['name' => 'Heavy Duty Kitchen Roll', 'ImageUrl' => 'images\\Heavy Duty Kitchen Roll.jpg', 'weight' => '1 roll', 'price' => 125],
        ['name' => 'Max Absorb Kitchen Towels', 'ImageUrl' => 'images\\Max Absorb Kitchen Towels.jpg', 'weight' => '2 rolls', 'price' => 175],
        ['name' => 'Kitchen Cleaning Paper Roll', 'ImageUrl' => 'images\\Kitchen Cleaning Paper Roll.jpg', 'weight' => '1 roll', 'price' => 110],
        ['name' => 'Multipurpose Kitchen Towels', 'ImageUrl' => 'images\\Multipurpose Kitchen Towels.jpg', 'weight' => '2 rolls', 'price' => 165],
        ['name' => 'StrongGrip Kitchen Towels', 'ImageUrl' => 'images\\StrongGrip Kitchen Towels.jpg', 'weight' => '2 rolls', 'price' => 185],
        ['name' => 'SoftCare Kitchen Roll', 'ImageUrl' => 'images\\SoftCare Kitchen Roll.jpg', 'weight' => '1 roll', 'price' => 115]
    ];
    addPaperProducts($staticProducts, $towels, 'towels', $knSuffixes['towels']);
    // ----- Napkins -----
    $napkins = [
        ['name' => 'Origami Paper Napkins', 'ImageUrl' => 'images\\Origami So Soft Napkins 50.jpg', 'weight' => 'sheets', 'price' => 60],
        ['name' => 'Premier Paper Napkins', 'ImageUrl' => 'images\\Premier Paper Napkins - 1 Ply 1 pc Pouch (100 Pulls) 90.jpg', 'weight' => '100 sheets', 'price' => 70],
        ['name' => 'Bella Paper Napkins', 'ImageUrl' => 'images\\Bella Paper Napkins 120.jpg', 'weight' => '100 sheets', 'price' => 120],
        ['name' => 'Paseo Paper Napkins', 'ImageUrl' => 'images\\Paseo Luncheon Serviettes Paper Napkins 48.jpg', 'weight' => 'sheets', 'price' => 50],
        ['name' => 'Origami Dinner Napkins', 'ImageUrl' => 'images\\Origami Paper Napkins 40.jpg', 'weight' => 'sheets', 'price' => 50],
        ['name' => 'Premier Dinner Napkins', 'ImageUrl' => 'images\\Premier Tissue Paper Napkins 300.jpg', 'weight' => 'sheets', 'price' => 300],
        ['name' => 'Bella Dinner Napkins', 'ImageUrl' => 'images\\Bella Dinner Napkins 165.jpg', 'weight' => 'sheets', 'price' => 170],
        ['name' => 'Paseo Lunch Napkins', 'ImageUrl' => 'images\\Paseo Paper Napkins 50.jpg', 'weight' => 'sheets', 'price' => 88],
        ['name' => 'Origami Cocktail Napkins', 'ImageUrl' => 'images\\Origami Cocktail Napkins 76.jpg', 'weight' => '50 sheets', 'price' => 75],
        ['name' => 'Premier Cocktail Napkins', 'ImageUrl' => 'images\\Premier Cocktail Napkins 60.jpg', 'weight' => '50 sheets', 'price' => 70],
        ['name' => 'Premium Table Napkins', 'ImageUrl' => 'images\\Premium Table Napkins.jpg', 'weight' => '100 sheets', 'price' => 95],
        ['name' => 'Restaurant Paper Napkins', 'ImageUrl' => 'images\\Restaurant Paper Napkins.jpg', 'weight' => '200 sheets', 'price' => 120],
        ['name' => 'SoftTouch Dinner Napkins', 'ImageUrl' => 'images\\SoftTouch Dinner Napkins.jpg', 'weight' => '50 sheets', 'price' => 85],
        ['name' => 'Eco Friendly Paper Napkins', 'ImageUrl' => 'images\\Eco Friendly Paper Napkins.jpg', 'weight' => '100 sheets', 'price' => 105],
        ['name' => 'Party Printed Napkins', 'ImageUrl' => 'images\\Party Printed Napkins.jpg', 'weight' => '50 sheets', 'price' => 90],
        ['name' => 'Luxury Dinner Napkins', 'ImageUrl' => 'images\\Luxury Dinner Napkins.jpg', 'weight' => '50 sheets', 'price' => 110],
        ['name' => 'Colored Paper Napkins', 'ImageUrl' => 'images\\Colored Paper Napkins.jpg', 'weight' => '100 sheets', 'price' => 100],
        ['name' => 'Multipurpose Paper Napkins', 'ImageUrl' => 'images\\Multipurpose Paper Napkins.jpg', 'weight' => '100 sheets', 'price' => 98],
        ['name' => 'Pocket Lunch Napkins', 'ImageUrl' => 'images\\Pocket Lunch Napkins.jpg', 'weight' => '100 sheets', 'price' => 92],
        ['name' => 'SoftCare Paper Napkins', 'ImageUrl' => 'images\\SoftCare Paper Napkins.jpg', 'weight' => '100 sheets', 'price' => 97]
    ];
    addPaperProducts($staticProducts, $napkins, 'napkins', $knSuffixes['napkins']);
    // ----- Plates -----
    $plates = [
        ['name' => 'Disposable Paper Plates Small', 'ImageUrl' => 'images\\Disposable Paper Plates Small silver 75.jpg', 'weight' => 'pcs', 'price' => 80],
        ['name' => 'Disposable Paper Plates Medium', 'ImageUrl' => 'images\\Disposable Paper Plates  7 inch  Naturepac 70.jpg', 'weight' => 'pcs', 'price' => 70],
        ['name' => 'Disposable Paper Plates Large', 'ImageUrl' => 'images\\Disposable Paper Plates Large 1400.jpg', 'weight' => '50 pcs', 'price' => 1400],
        ['name' => 'Silver Laminated Paper Plates', 'ImageUrl' => 'images\\Silver Laminated Paper Plates 200.jpg', 'weight' => 'pcs', 'price' => 200],
        ['name' => 'Printed Party Paper Plates', 'ImageUrl' => 'images\\Printed Party Paper Plates 230.jpg', 'weight' => 'pcs', 'price' => 230],
        ['name' => 'Round Paper Plates', 'ImageUrl' => 'images\\Round Paper Plates 790.jpg', 'weight' => 'pcs', 'price' => 800],
        ['name' => 'Square Paper Plates', 'ImageUrl' => 'images\\Square Paper Plates.jpg', 'weight' => 'pcs', 'price' => 190],
        ['name' => 'Heavy Duty Paper Plates', 'ImageUrl' => 'images\\ECO SOUL Compostable Heavy Duty Disposable Bagasse Plates 600.jpg', 'weight' => 'pcs', 'price' => 600],
        ['name' => 'Eco Friendly Paper Plates', 'ImageUrl' => 'images\\Eco Friendly Paper Plates 200.jpg', 'weight' => 'pcs', 'price' => 200],
        ['name' => 'Biodegradable Paper Plates', 'ImageUrl' => 'images\\Origami Biodegradable Compostable Round Pulp Plates 200.jpg', 'weight' => 'pcs', 'price' => 200],
        ['name' => 'White Disposable Paper Plates', 'ImageUrl' => 'images\\White Disposable Paper Plates.jpg', 'weight' => '50 pcs', 'price' => 130],
        ['name' => 'Gold Laminated Paper Plates', 'ImageUrl' => 'images\\Gold Laminated Paper Plates.jpg', 'weight' => '25 pcs', 'price' => 140],
        ['name' => 'Festival Printed Paper Plates', 'ImageUrl' => 'images\\Festival Printed Paper Plates.jpg', 'weight' => '25 pcs', 'price' => 135],
        ['name' => 'Extra Thick Paper Plates', 'ImageUrl' => 'images\\Extra Thick Paper Plates.jpg', 'weight' => '25 pcs', 'price' => 160],
        ['name' => 'Eco Palm Leaf Plates', 'ImageUrl' => 'images\\Eco Palm Leaf Plates.jpg', 'weight' => '25 pcs', 'price' => 200],
        ['name' => 'Biodegradable Bagasse Plates', 'ImageUrl' => 'images\\Biodegradable Bagasse Plates.jpg', 'weight' => '25 pcs', 'price' => 210],
        ['name' => 'Snack Size Paper Plates', 'ImageUrl' => 'images\\Snack Size Paper Plates.jpg', 'weight' => '50 pcs', 'price' => 120],
        ['name' => 'Dinner Size Paper Plates', 'ImageUrl' => 'images\\Dinner Size Paper Plates.jpg', 'weight' => '50 pcs', 'price' => 170],
        ['name' => 'Kids Party Paper Plates', 'ImageUrl' => 'images\\Kids Party Paper Plates.jpg', 'weight' => '25 pcs', 'price' => 130],
        ['name' => 'Premium Party Paper Plates', 'ImageUrl' => 'images\\Premium Party Paper Plates.jpg', 'weight' => '25 pcs', 'price' => 150]
    ];
    addPaperProducts($staticProducts, $plates, 'plates', $knSuffixes['plates']);
    // ----- Cups -----
    $cups = [
        ['name' => 'Disposable Paper Cups Small', 'ImageUrl' => 'images\\Disposable Paper Cups Small 99.jpg', 'weight' => 'pcs', 'price' => 100],
        ['name' => 'Disposable Paper Cups Medium', 'ImageUrl' => 'images\\Disposable Paper Cups Medium 99.jpg', 'weight' => 'pcs', 'price' => 100],
        ['name' => 'Disposable Paper Cups Large', 'ImageUrl' => 'images\\Disposable Paper Cups Large 750.jpg', 'weight' => 'pcs', 'price' => 750],
        ['name' => 'Printed Paper Tea Cups', 'ImageUrl' => 'images\\Printed Paper Tea Cups 250.jpg', 'weight' => 'pcs', 'price' => 250],
        ['name' => 'Coffee Paper Cups', 'ImageUrl' => 'images\\Urban Platter Disposable Ripple Cups 300.jpg', 'weight' => 'pcs', 'price' => 300],
        ['name' => 'Ripple Wall Paper Cups', 'ImageUrl' => 'images\\Ripple Wall Paper Cups 190.jpg', 'weight' => 'pcs', 'price' => 190],
        ['name' => 'Double Wall Paper Cups', 'ImageUrl' => 'images\\Double Wall Paper Cups 850.jpg', 'weight' => 'pcs', 'price' => 870],
        ['name' => 'Eco Friendly Paper Cups', 'ImageUrl' => 'images\\Eco Friendly Paper Cups 280.jpg', 'weight' => 'pcs', 'price' => 280],
        ['name' => 'Biodegradable Paper Cups', 'ImageUrl' => 'images\\PERFY Paper Biodegradable Paper Cups 140.jpg', 'weight' => 'pcs', 'price' => 150],
        ['name' => 'Party Printed Paper Cups', 'ImageUrl' => 'images\\Origami Printed Paper Cups 200.jpg', 'weight' => 'pcs', 'price' => 200],
        ['name' => 'Tea Serving Paper Cups', 'ImageUrl' => 'images\\Tea Serving Paper Cups.jpg', 'weight' => '50 pcs', 'price' => 115],
        ['name' => 'Hot Beverage Paper Cups', 'ImageUrl' => 'images\\Hot Beverage Paper Cups.jpg', 'weight' => '50 pcs', 'price' => 150],
        ['name' => 'Cold Drink Paper Cups', 'ImageUrl' => 'images\\Cold Drink Paper Cups.jpg', 'weight' => '50 pcs', 'price' => 145],
        ['name' => 'Printed Coffee Paper Cups', 'ImageUrl' => 'images\\Printed Coffee Paper Cups.jpg', 'weight' => '50 pcs', 'price' => 160],
        ['name' => 'Eco Bamboo Paper Cups', 'ImageUrl' => 'images\\Eco Bamboo Paper Cups.jpg', 'weight' => '50 pcs', 'price' => 200],
        ['name' => 'Biodegradable Bagasse Cups', 'ImageUrl' => 'images\\Biodegradable Bagasse Cups.jpg', 'weight' => '50 pcs', 'price' => 210],
        ['name' => 'Kids Party Paper Cups', 'ImageUrl' => 'images\\Kids Party Paper Cups.jpg', 'weight' => '25 pcs', 'price' => 120],
        ['name' => 'Premium Ripple Coffee Cups', 'ImageUrl' => 'images\\Premium Ripple Coffee Cups.jpg', 'weight' => '25 pcs', 'price' => 180],
        ['name' => 'Double Wall Hot Cups', 'ImageUrl' => 'images\\Double Wall Hot Cups.jpg', 'weight' => '25 pcs', 'price' => 190],
        ['name' => 'StrongGrip Disposable Cups', 'ImageUrl' => 'images\\StrongGrip Disposable Cups.jpg', 'weight' => '50 pcs', 'price' => 155]
    ];
    addPaperProducts($staticProducts, $cups, 'cups', $knSuffixes['cups']);
    // Assign static products to main array (with normalized image paths)
    $productsData = array_map(function($p) {
        // Normalize image path: replace Windows backslashes and remove base folder
        $image = $p['imageUrl'] ?? '';
        if ($image && !str_starts_with($image, 'http') && !str_starts_with($image, 'data:')) {
            $image = str_replace('', '', $image);
            $image = str_replace('\\', '/', $image);
            if (!str_starts_with($image, 'images/') && !str_starts_with($image, '../')) {
                $image = '../images/' . $image;
            } elseif (!str_starts_with($image, '../')) {
                $image = '../' . $image;
            }
            $p['imageUrl'] = $image;
        }
        return $p;
    }, $staticProducts);
}
// ============================================================
// AJAX CART ENDPOINTS (all actions)
// ============================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'] ?? '';
    switch ($action) {
        case 'products':
            $search = $_GET['search'] ?? '';
            // Search filtering (optional)
            if ($search) {
                $filtered = array_filter($productsData, function($p) use ($search) {
                    $term = strtolower($search);
                    return strpos(strtolower($p['name']), $term) !== false || strpos(strtolower($p['kn']), $term) !== false;
                });
                echo json_encode(array_values($filtered));
            } else {
                echo json_encode($productsData);
            }
            exit;
        case 'cart':
            echo json_encode($_SESSION['cart']);
            exit;
        case 'add_cart':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $product = getProductById($id);
                if (!$product) {
                    // Search in static/fallback product list
                    foreach ($productsData as $p) {
                        if ($p['id'] == $id) {
                            $product = [
                                'id' => $p['id'],
                                'name' => $p['name'],
                                'kn_name' => $p['kn'],
                                'price' => $p['price'],
                                'icon' => 'fa-toilet-paper'
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
                            'icon' => $product['icon'] ?? 'fa-toilet-paper'
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>Paper Products · Premium Quality · ಕಾಗದದ ಉತ್ಪನ್ನಗಳು</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <style>
    /* ===== MERGED CSS from both versions ===== */
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body { background: #f9f6f2; padding: 24px 20px; min-height: 100vh; }
    #app { max-width: 1400px; width: 100%; margin: 0 auto; background: white; border-radius: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); padding: 28px 32px 40px; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 16px; }
    .shop-name h1 { font-size: 2.2rem; font-weight: 700; color: #3e2c1b; line-height: 1.2; }
    .shop-name .kannada { font-size: 1.1rem; color: #b45f2b; font-weight: 500; }
    .cart-link { background: #f7ede3; padding: 12px 24px; border-radius: 60px; display: inline-flex; align-items: center; gap: 10px; transition: background 0.2s; text-decoration: none; color: inherit; font-weight: 600; font-size: 0.95rem; }
    .cart-link i { font-size: 1.4rem; color: #b45f2b; }
    .cart-link .cart-badge { background: #b45f2b; color: white; font-size: 0.85rem; font-weight: 700; padding: 2px 10px; border-radius: 40px; margin-left: 6px; }
    .cart-link:hover { background: #ffe2d0; }
    .secondary-nav { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 28px; }
    .secondary-nav a { background: #f3eee9; padding: 10px 20px; border-radius: 50px; font-size: 0.9rem; font-weight: 500; color: #5c3e24; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; }
    .secondary-nav a:hover { background: #e5d5c4; }
    .search-section { margin-bottom: 32px; }
    .search-box { display: flex; align-items: center; background: #f3eee9; border-radius: 60px; padding: 8px 24px; margin-bottom: 20px; border: 1px solid #e6d9cd; max-width: 500px; }
    .search-box i { color: #b45f2b; font-size: 1.2rem; margin-right: 14px; }
    .search-box input { flex: 1; border: none; background: transparent; padding: 14px 0; font-size: 1rem; outline: none; color: #2f2216; }
    .filter-pills { display: flex; flex-wrap: wrap; gap: 12px; }
    .pill { background: #f3eee9; padding: 10px 24px; border-radius: 60px; font-size: 1rem; font-weight: 500; color: #4f3e2e; cursor: pointer; transition: all 0.2s; }
    .pill.active { background: #5c3e24; color: white; }
    .pill:hover { background: #e0cfbf; }
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 28px; margin-top: 20px; margin-bottom: 50px; }
    .product-card { background: white; border-radius: 32px; padding: 20px 18px 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.04); border: 1px solid #f2e5d9; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; align-items: center; text-align: center; }
    .product-card:hover { transform: translateY(-6px); box-shadow: 0 20px 30px rgba(0,0,0,0.08); }
    .product-img { width: 110px; height: 110px; border-radius: 55px; overflow: hidden; margin: 0 auto 16px; background: #fcf4ec; box-shadow: 0 8px 16px rgba(0,0,0,0.05); transition: transform 0.2s; }
    .product-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.2s; }
    .product-card:hover .product-img img { transform: scale(1.05); }
    .product-name { font-weight: 700; font-size: 1.1rem; color: #2f2216; margin-top: 4px; }
    .product-name-kn { font-size: 0.75rem; color: #7b5f45; margin-bottom: 8px; }
    .product-description { font-size: 0.8rem; color: #8a6e54; margin: 8px 0 12px; line-height: 1.4; padding: 0 4px; }
    .price-row { display: flex; justify-content: space-between; align-items: center; width: 100%; margin-top: 8px; }
    .price { font-weight: 800; font-size: 1.2rem; color: #b45f2b; }
    .price small { font-size: 0.7rem; font-weight: 500; color: #a07c5e; }
    .add-btn { background: #b45f2b; border: none; color: white; width: 48px; height: 48px; border-radius: 60px; font-size: 1.4rem; cursor: pointer; transition: 0.1s; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(180,95,43,0.2); }
    .add-btn:active { transform: scale(0.95); }
    .add-btn.added-flash { background: #4caf50 !important; }
    .cart-sidebar { position: fixed; top: 0; right: -420px; width: 400px; height: 100vh; background: #fffefc; box-shadow: -8px 0 40px rgba(0,0,0,0.1); transition: right 0.3s ease; z-index: 1100; display: flex; flex-direction: column; border-left: 2px solid #f2e5d9; }
    .cart-sidebar.open { right: 0; }
    .cart-header { padding: 24px; border-bottom: 2px solid #f2e5d9; display: flex; justify-content: space-between; align-items: center; }
    .close-cart { font-size: 1.6rem; color: #8a6e54; cursor: pointer; background: none; border: none; }
    .cart-items { flex: 1; overflow-y: auto; padding: 20px; }
    .empty-cart { text-align: center; margin-top: 60px; color: #8a6e54; }
    .cart-item { display: flex; gap: 14px; margin-bottom: 24px; padding-bottom: 18px; border-bottom: 1px solid #f2e5d9; align-items: center; }
    .cart-item-image { width: 44px; height: 44px; background: #FCF5EE; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .cart-item-name { font-weight: 700; margin-bottom: 4px; font-size: 0.9rem; }
    .cart-item-price { color: #b45f2b; font-weight: 600; font-size: 0.85rem; }
    .cart-item-actions { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
    .cart-item-actions button { background: #f3eee9; width: 30px; height: 30px; border-radius: 40px; border: none; cursor: pointer; }
    .cart-footer { padding: 24px; border-top: 2px solid #f2e5d9; }
    .cart-total { display: flex; justify-content: space-between; font-weight: 800; font-size: 1.3rem; margin-bottom: 20px; }
    .checkout-btn, .continue-shopping { width: 100%; padding: 14px; border-radius: 50px; font-weight: 700; margin-bottom: 12px; border: none; cursor: pointer; }
    .checkout-btn { background: #b45f2b; color: white; }
    .continue-shopping { background: none; border: 1px solid #e6d9cd; color: #2f2216; }
    .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(3px); z-index: 1050; display: none; }
    .overlay.active { display: block; }
    footer { text-align: center; font-size: 0.85rem; color: #b9a690; margin-top: 20px; padding-top: 16px; border-top: 1px solid #f0e3d7; }
    @media (max-width: 768px) { #app { padding: 20px; border-radius: 28px; } .header { flex-direction: column; align-items: stretch; gap: 14px; } .cart-link { align-self: flex-start; padding: 10px 20px; } .product-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; } .cart-sidebar { width: 100%; right: -100%; } }
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
      <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off" title="Type to filter products">
    </div>
    <div class="filter-pills" id="filterPills">
      <div class="pill active" data-category="all" title="Show all items">All / ಎಲ್ಲ</div>
      <div class="pill" data-category="tissues" title="Facial tissues & pocket tissues">Tissues / ಟಿಶ್ಯೂಗಳು</div>
      <div class="pill" data-category="towels" title="Kitchen paper towels">Paper Towels / ಪೇಪರ್ ಟವೆಲ್</div>
      <div class="pill" data-category="napkins" title="Dinner & lunch napkins">Napkins / ನ್ಯಾಪ್ಕಿನ್‌ಗಳು</div>
      <div class="pill" data-category="plates" title="Disposable paper plates">Plates / ಪ್ಲೇಟ್‌ಗಳು</div>
      <div class="pill" data-category="cups" title="Paper cups for hot/cold drinks">Cups / ಕಪ್‌ಗಳು</div>
    </div>
  </div>
  <div class="product-grid" id="productGrid"></div>
  <!-- Cart Sidebar (same as before) -->
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
// ============================================================
// JAVASCRIPT – SESSION-BASED CART VIA AJAX + LOCALSTORAGE FALLBACK
// ============================================================
(function() {
    // ----- PRODUCT DATA FROM PHP (embedded) -----
    const productsData = <?php echo json_encode($productsData); ?>;
    // ----- BUILD PRODUCTS WITH DESCRIPTIONS IF MISSING -----
    const products = productsData.map(p => {
        // Set default descriptions if not provided
        const cat = p.category;
        let descEn = p.description_en || '';
        let descKn = p.description_kn || '';
        if (!descEn) {
            switch(cat) {
                case 'tissues': descEn = 'Soft & absorbent facial tissue'; break;
                case 'towels': descEn = 'Strong & absorbent kitchen roll'; break;
                case 'napkins': descEn = 'Premium quality, soft on skin'; break;
                case 'plates': descEn = 'Disposable, eco-friendly plates'; break;
                case 'cups': descEn = 'Leak-proof cups for hot/cold drinks'; break;
                default: descEn = 'High quality paper product';
            }
        }
        if (!descKn) {
            switch(cat) {
                case 'tissues': descKn = 'ಮೃದು ಮತ್ತು ಹೀರಿಕೊಳ್ಳುವ ಟಿಶ್ಯೂ'; break;
                case 'towels': descKn = 'ಬಲವಾದ ಮತ್ತು ಹೀರಿಕೊಳ್ಳುವ ಕಿಚನ್ ರೋಲ್'; break;
                case 'napkins': descKn = 'ಪ್ರೀಮಿಯಂ ಗುಣಮಟ್ಟ, ಚರ್ಮಕ್ಕೆ ಮೃದು'; break;
                case 'plates': descKn = 'ಬಿಸಾಡಬಹುದಾದ, ಪರಿಸರ ಸ್ನೇಹಿ ಪ್ಲೇಟ್'; break;
                case 'cups': descKn = 'ಬಿಸಿ/ತಣ್ಣೀರಿಗೆ ಸೋರಿಕೆ-ರಹಿತ ಕಪ್'; break;
                default: descKn = 'ಉತ್ತಮ ಗುಣಮಟ್ಟದ ಕಾಗದದ ಉತ್ಪನ್ನ';
            }
        }
        // Ensure imageUrl is set
        let img = p.imageUrl || '';
        if (!img) img = 'https://via.placeholder.com/110?text=Paper';
        return {
            ...p,
            description_en: descEn,
            description_kn: descKn,
            imageUrl: img
        };
    });
    // ----- CART STATE (localStorage + server sync) -----
    let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
    let activeCategory = 'all';
    let searchTerm = '';
    // DOM refs
    const productGrid = document.getElementById('productGrid');
    const cartCountSpan = document.getElementById('cartCount');
    const searchInput = document.getElementById('searchInput');
    const filterPills = document.querySelectorAll('.pill');
    const cartLink = document.getElementById('cartLink');
    const cartSidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    const closeCartBtn = document.getElementById('closeCart');
    const continueShoppingBtn = document.getElementById('continueShopping');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const cartItemsContainer = document.getElementById('cartItems');
    const totalPriceSpan = document.querySelector('.total-price');
    // ----- UPDATE CART BADGE -----
    function updateCartBadge() {
        const totalQty = cart.reduce((sum, i) => sum + (i.qty || 0), 0);
        cartCountSpan.innerText = totalQty;
        localStorage.setItem('glowCartNew', JSON.stringify(cart));
    }
    // ----- ADD TO CART (local + server sync) -----
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
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
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
    // ----- CART SIDEBAR -----
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
            const itemTotal = item.price * item.qty;
            total += itemTotal;
            html += `
                <div class="cart-item" data-id="${item.id}">
                    <div class="cart-item-image">🧻</div>
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
        cartItemsContainer.innerHTML = html;
        totalPriceSpan.innerText = `₹${total.toFixed(2)}`;
    }
    // ----- UPDATE QUANTITY / REMOVE (cart sidebar) -----
    function updateCartQty(productId, qty) {
        const formData = new FormData();
        formData.append('id', productId);
        formData.append('qty', qty);
        fetch('paper-products.php?ajax=update_cart', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    cart = data.cart;
                    updateCartBadge();
                    renderCartItems();
                }
            })
            .catch(console.error);
    }
    function removeFromCart(productId) {
        const formData = new FormData();
        formData.append('id', productId);
        fetch('paper-products.php?ajax=remove_cart', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    cart = data.cart;
                    updateCartBadge();
                    renderCartItems();
                }
            })
            .catch(console.error);
    }
    // Expose these functions globally for inline onclick handlers
    window.addToCart = addToCart;
    window.changeQty = updateCartQty;
    window.removeItem = removeFromCart;
    // ----- RENDER PRODUCTS -----
    function renderProducts() {
        let filtered = products.filter(p => {
            if (activeCategory !== 'all' && p.category !== activeCategory) return false;
            const term = searchTerm.trim().toLowerCase();
            if (term) {
                return p.name.toLowerCase().includes(term) || p.kn.toLowerCase().includes(term);
            }
            return true;
        });
        if (!filtered.length) {
            productGrid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No paper products found ✨</div>`;
            return;
        }
        productGrid.innerHTML = filtered.map(p => `
            <div class="product-card" data-id="${p.id}">
                <div class="product-img"><img src="${p.imageUrl}" alt="" loading="lazy" onerror="this.src='https://via.placeholder.com/110?text=Paper'"></div>
                <div class="product-name">${p.name}</div>
                <div class="product-name-kn">${p.kn}</div>
                <div class="product-description">${p.description_en}<br><small style="color:#a07c5e;">${p.description_kn}</small></div>
                <div class="price-row">
                    <span class="price">₹${p.price} <small>/ pack</small></span>
                    <button class="add-btn" data-id="${p.id}"><i class="fa-solid fa-cart-plus"></i></button>
                </div>
            </div>
        `).join('');
        document.querySelectorAll('.add-btn').forEach(btn => {
            btn.addEventListener('click', () => addToCart(parseInt(btn.dataset.id)));
        });
    }
    // ----- EVENT BINDING -----
    filterPills.forEach(pill => {
        pill.addEventListener('click', () => {
            filterPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            activeCategory = pill.dataset.category;
            renderProducts();
        });
    });
    searchInput.addEventListener('input', e => { searchTerm = e.target.value; renderProducts(); });
    // Cart link
    cartLink.addEventListener('click', function(e) {
    e.preventDefault();
    if (typeof openCart === 'function') { openCart(); } else { window.location.href = '../cart/cart.php'; }
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
    // ----- LOAD CART FROM SERVER -----
    function loadCart() {
        fetch('paper-products.php?ajax=cart')
            .then(res => res.json())
            .then(data => {
                cart = data;
                updateCartBadge();
                if (cartSidebar.classList.contains('open')) renderCartItems();
            })
            .catch(console.error);
    }
    // ----- INIT -----
    loadCart();
    renderProducts();
})();
</script>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
<script src="sorting.js"></script>
</body>
</html>
