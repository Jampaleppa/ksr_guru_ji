<?php
// ============================================================
// health-pharmacy.php - Dynamic Health & Pharmacy page (FIXED with images)
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
            $res = getHealthPharmacyProducts($search);
            if (empty($res)) {
                $res = getFallbackProducts();
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
                                'icon' => 'fa-capsules'
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
                            'id' => $id,
                            'name' => $product['name'],
                            'kn' => $product['kn_name'] ?? $product['name'],
                            'price' => $product['price'],
                            'qty' => 1,
                            'icon' => $product['icon'] ?? 'fa-capsules'
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
// Helper function to fetch health pharmacy products using PDO with Dynamic Column Checking
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
function getHealthPharmacyProducts(string $search = ''): array
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
        $whereCategory = $hasSlug ? "(c.slug = 'health-pharmacy' OR c.id = 'health-pharmacy' OR c.name LIKE '%health%')" : "(c.name LIKE '%health%')";
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
            $row['description_en'] = $row['description_en'] ?? 'Quality health product';
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
// Get products (health-pharmacy category)
$products = getHealthPharmacyProducts();
// ============================================================
// FALLBACK: If no products from DB, load static data from JSON
// ============================================================
function getFallbackProducts()
{
    static $fallback = null;
    if ($fallback !== null) {
        return $fallback;
    }
    $jsonPath = __DIR__ . '/health-pharmacy-fallback.json';
    if (file_exists($jsonPath)) {
        $fallback = json_decode(file_get_contents($jsonPath), true);
        if (is_array($fallback)) {
            return $fallback;
        }
    }
    $json = '[
  {"name":"Paracetamol Tablets 500mg","price":25,"weight":"10 tablets","imageUrl":"images\\\\Paracetamol Tablets 500mg.jpg"},
  {"name":"Vitamin C Tablets","price":120,"weight":"20 tablets","imageUrl":"images\\\\Vitamin C Tablets.jpg"},
  {"name":"Digital Thermometer","price":250,"weight":"1 unit","imageUrl":"images\\\\Digital Thermometer.jpg"},
  {"name":"Hand Sanitizer (Alcohol-Based)","price":160,"weight":"200 ml","imageUrl":"images\\\\Hand Sanitizer (Alcohol-Based) 1.jpg"},
  {"name":"Hand Sanitizer (Alcohol-Based)","price":200,"weight":"200 ml","imageUrl":"images\\\\Hand Sanitizer (Alcohol-Based)2.jpg"},
  {"name":"Dettol Antiseptic Liquid","price":180,"weight":"500 ml","imageUrl":"images\\\\Dettol Antiseptic Liquid 1.jpg"},
  {"name":"Dettol Antiseptic Liquid","price":320,"weight":"500 ml","imageUrl":"images\\\\Dettol Antiseptic Liquid 315.jpg"},
  {"name":"Pain Relief Balm","price":85,"weight":"25 g","imageUrl":"images\\\\Pain Relief Balm.jpg"},
  {"name":"Glucose Powder","price":140,"weight":"500 g","imageUrl":"images\\\\Glucose Powder.jpg"},
  {"name":"ORS Sachets","price":75,"weight":"Pack of 5","imageUrl":"images\\\\ORS Sachets.jpg"},
  {"name":"Face Mask (Surgical)","price":300,"weight":"Pack of 50","imageUrl":"images\\\\Face Mask (Surgical).jpg"},
  {"name":"Face Mask (Surgical)","price":80,"weight":"1","imageUrl":"images\\\\Face Mask (Surgical) 50.jpg"},
  {"name":"Blood Pressure Monitor","price":1500,"weight":"1 unit","imageUrl":"images\\\\Blood Pressure Monitor.jpg"},
  {"name":"Multivitamin Capsules","price":220,"weight":"30 capsules","imageUrl":"images\\\\Multivitamin Capsules.jpg"},
  {"name":"Protein Powder","price":1200,"weight":"1 kg","imageUrl":"images\\\\Protein Powder.jpg"},
  {"name":"First Aid Kit","price":450,"weight":"1 kit","imageUrl":"images\\\\First Aid Kit.jpg"},
  {"name":"Cough Syrup","price":95,"weight":"100 ml","imageUrl":"images\\\\Cough Syrup.jpg"},
  {"name":"Antacid Liquid","price":110,"weight":"170 ml","imageUrl":"images\\\\Antacid Liquid.jpg"},
  {"name":"Ibuprofen Tablets","price":35,"weight":"10 tablets","imageUrl":"images\\\\Ibuprofen Tablets.jpg"},
  {"name":"Zinc Tablets","price":150,"weight":"20 tablets","imageUrl":"images\\\\Zinc Tablets.jpg"},
  {"name":"Calcium Supplements","price":210,"weight":"30 tablets","imageUrl":"images\\\\Calcium Supplements.jpg"},
  {"name":"Ayurvedic Chyawanprash","price":320,"weight":"500 g","imageUrl":"images\\\\Ayurvedic Chyawanprash 180.jpg"},
  {"name":"Ayurvedic Chyawanprash","price":420,"weight":"500 g","imageUrl":"images\\\\Ayurvedic Chyawanprash 415.jpg"},
  {"name":"Ayurvedic Chyawanprash","price":1260,"weight":"500 g","imageUrl":"images\\\\Ayurvedic Chyawanprash 1260.jpg"},
  {"name":"Aloe Vera Gel","price":130,"weight":"150 ml","imageUrl":"images\\\\Aloe Vera Gel.jpg"},
  {"name":"Betadine Antiseptic Solution","price":160,"weight":"100 ml","imageUrl":"images\\\\Betadine Antiseptic Solution 1.jpg"},
  {"name":"Cotton Roll","price":60,"weight":"100 g","imageUrl":"images\\\\Cotton Roll.jpg"},
  {"name":"Band-Aid Strips","price":55,"weight":"Pack of 20","imageUrl":"images\\\\Band-Aid Strips.jpg"},
  {"name":"Pregnancy Test Kit","price":90,"weight":"1 unit","imageUrl":"images\\\\Pregnancy Test Kit.jpg"},
  {"name":"Steam Inhaler","price":350,"weight":"1 unit","imageUrl":"images\\\\Steam Inhaler.jpg"},
  {"name":"Nebulizer Machine","price":2200,"weight":"1 unit","imageUrl":"images\\\\Nebulizer Machine.jpg"},
  {"name":"Insulin Syringe","price":180,"weight":"Pack of 10","imageUrl":"images\\\\Insulin Syringe.jpg"},
  {"name":"Thermometer Strips","price":70,"weight":"Pack of 5","imageUrl":"images\\\\Thermometer Strips.jpg"},
  {"name":"Electrolyte Drink Powder","price":280,"weight":"1 kg","imageUrl":"images\\\\Electrolyte Drink Powder.jpg"},
  {"name":"Digestive Enzyme Syrup","price":170,"weight":"200 ml","imageUrl":"images\\\\Digestive Enzyme Syrup 170.jpg"},
  {"name":"Digestive Enzyme Syrup","price":200,"weight":"200 ml","imageUrl":"images\\\\Digestive Enzyme Syrup 200.jpg"},
  {"name":"Digestive Enzyme Syrup","price":230,"weight":"200 ml","imageUrl":"images\\\\Digestive Enzyme Syrup 230.jpg"},
  {"name":"Hair Growth Serum","price":400,"weight":"60 ml","imageUrl":"images\\\\Hair Growth Serum 400.jpg"},
  {"name":"Hair Growth Serum","price":1550,"weight":"60 ml","imageUrl":"images\\\\Hair Growth Serum 1550.jpg"},
  {"name":"Hair Growth Serum","price":780,"weight":"60 ml","imageUrl":"images\\\\Hair Growth Serum 780.jpg"},
  {"name":"Anti-Dandruff Shampoo","price":250,"weight":"180 ml","imageUrl":"images\\\\Anti-Dandruff Shampoo 250.jpg"},
  {"name":"Anti-Dandruff Shampoo","price":360,"weight":"180 ml","imageUrl":"images\\\\Anti-Dandruff Shampoo 360.jpg"},
  {"name":"Anti-Dandruff Shampoo","price":420,"weight":"180 ml","imageUrl":"images\\\\Anti-Dandruff Shampoo 420.jpg"},
  {"name":"Medicated Soap","price":75,"weight":"100 g","imageUrl":"images\\\\Medicated Soap.jpg"},
  {"name":"Eye Drops","price":85,"weight":"10 ml","imageUrl":"images\\\\Eye Drops.jpg"},
  {"name":"Nasal Spray","price":140,"weight":"20 ml","imageUrl":"images\\\\Nasal Spray.jpg"},
  {"name":"Hot Water Bag","price":240,"weight":"1 unit","imageUrl":"images\\\\Hot Water Bag 200.jpg"},
  {"name":"Hot Water Bag min","price":130,"weight":"1 unit","imageUrl":"images\\\\Hot Water Bag min 130.jpg"},
  {"name":"Digital Weighing Scale","price":900,"weight":"1 unit","imageUrl":"images\\\\Digital Weighing Scale 800.jpg"},
  {"name":"Digital Weighing Scale","price":650,"weight":"1 unit","imageUrl":"images\\\\Digital Weighing Scale 570.jpg"},
  {"name":"Pulse Oximeter","price":620,"weight":"1 unit","imageUrl":"images\\\\Pulse Oximeter 500.jpg"},
  {"name":"Pulse Oximeter","price":1350,"weight":"1 unit","imageUrl":"images\\\\Pulse Oximeter 1350.jpg"},
  {"name":"Pulse Oximeter","price":1600,"weight":"1 unit","imageUrl":"images\\\\Pulse Oximeter 1600.jpg"},
  {"name":"Knee Support Belt","price":450,"weight":"1 unit","imageUrl":"images\\\\Knee Support Belt 400.jpg"},
  {"name":"Knee Support Belt","price":600,"weight":"1 unit","imageUrl":"images\\\\Knee Support Belt 600.jpg"},
  {"name":"Knee Support Belt","price":700,"weight":"1 unit","imageUrl":"images\\\\Knee Support Belt 690.jpg"},
  {"name":"Knee Support Belt","price":820,"weight":"1 unit","imageUrl":"images\\\\Knee Support Belt 800.jpg"},
  {"name":"Back Pain Relief Spray","price":220,"weight":"50 ml","imageUrl":"images\\\\Back Pain Relief Spray 220.jpg"},
  {"name":"Back Pain Relief Spray","price":180,"weight":"50 ml","imageUrl":"images\\\\Back Pain Relief Spray 151.jpg"},
  {"name":"Back Pain Relief Spray","price":180,"weight":"50 ml","imageUrl":"images\\\\Back Pain Relief Spray 145.jpg"},
  {"name":"Back Pain Relief Spray","price":180,"weight":"50 ml","imageUrl":"images\\\\Back Pain Relief Spray 65.jpg"},
  {"name":"Back Pain Relief Spray relispray","price":380,"weight":"50 ml","imageUrl":"images\\\\Back Pain Relief Spray relispray 173-380.jpg"},
  {"name":"Back Pain Relief Spray moov","price":180,"weight":"50 ml","imageUrl":"images\\\\Back Pain Relief Spray moov 180.jpg"},
  {"name":"Vitamin D3 Capsules","price":180,"weight":"30 capsules","imageUrl":"images\\\\Vitamin D3 Capsules 40.jpg"},
  {"name":"Iron Supplements","price":350,"weight":"30 tablets","imageUrl":"images\\\\Iron Supplements 325.jpg"},
  {"name":"OZiva Plant Based Iron Supplements","price":850,"weight":"30 tablets","imageUrl":"images\\\\OZiva Plant Based Iron Supplements 800.jpg"},
  {"name":"Iron, Omega for Control Hair Fall, Hair Growth Supplements","price":160,"weight":"30 tablets","imageUrl":"images\\\\Iron, Omega for Control Hair Fall, Hair Growth Supplements  150.jpg"},
  {"name":"Probiotic Capsules","price":350,"weight":"20 capsules","imageUrl":"images\\\\Probiotic Capsules 325.jpg"},
  {"name":"Probiotic Capsules","price":750,"weight":"20 capsules","imageUrl":"images\\\\Probiotic Capsules 720.jpg"},
  {"name":"Diarrhea For Kids & Adults Strip Of 4 Capsules","price":350,"weight":"20 capsules","imageUrl":"images\\\\Diarrhea For Kids & Adults Strip Of 4 Capsules 165.jpg"},
  {"name":"Tata Probiotics 30 Billion CFUs+ 60 Capsules","price":250,"weight":"20 capsules","imageUrl":"images\\\\Tata Probiotics 30 Billion CFUs+ 60 Capsules 260.jpg"},
  {"name":"Antiseptic Cream","price":95,"weight":"30 g","imageUrl":"images\\\\Antiseptic Cream 40.jpg"},
  {"name":"Himalaya Antiseptic Cream","price":65,"weight":"20 g","imageUrl":"images\\\\Himalaya Antiseptic Cream 20g 55.jpg"},
  {"name":"Himalaya Antiseptic Cream","price":75,"weight":"30 g","imageUrl":"images\\\\Himalaya Antiseptic Cream 50.jpg"},
  {"name":"BoroPlus Antiseptic Cream","price":95,"weight":"30 g","imageUrl":"images\\\\BoroPlus Antiseptic Cream 95.jpg"},
  {"name":"Burn Relief Gel","price":90,"weight":"40 g","imageUrl":"images\\\\Burn Relief Gel 68.jpg"},
  {"name":"Mosquito Repellent Cream","price":65,"weight":"50 ml","imageUrl":"images\\\\Mosquito Repellent Cream 48.jpg"},
  {"name":"Mosquito Repellent Cream","price":110,"weight":"50 ml","imageUrl":"images\\\\Mosquito Repellent Cream 110.jpg"},
  {"name":"Odomos Naturals Mosquito Repellent Cream wellness forever","price":85,"weight":"50 ml","imageUrl":"images\\\\Odomos Naturals Mosquito Repellent Cream wellness forever 58.jpg"},
  {"name":"Liquid Hand Wash (Medicinal)","price":180,"weight":"250 ml","imageUrl":"images\\\\Liquid Hand Wash (Medicinal) 180.jpg"},
  {"name":"Medical Gloves","price":400,"weight":"Pack of 100","imageUrl":"images\\\\Medical Gloves.jpg"},
  {"name":"Face Shield","price":380,"weight":"1 unit","imageUrl":"images\\\\Face Shield.jpg"},
  {"name":"Surgical Tape","price":128,"weight":"1 roll","imageUrl":"images\\\\Surgical Tape 128.jpg"},
  {"name":"Romsons Kenpore Surgical Tape","price":360,"weight":"1 roll","imageUrl":"images\\\\Romsons Kenpore Surgical Tape 350.jpg"},
  {"name":"Doctor s Choice Micropore Surgical Tape","price":128,"weight":"1 roll","imageUrl":"images\\\\Doctor\'s Choice Micropore Surgical Tape 20.jpg"},
  {"name":"Crepe Bandage","price":90,"weight":"1 unit","imageUrl":"images\\\\Crepe Bandage 75.jpg"},
  {"name":"Hansaplast Cotton Crepe Bandage","price":150,"weight":"1 unit","imageUrl":"images\\\\Hansaplast Cotton Crepe Bandage 150.jpg"},
  {"name":"Digital Glucometer","price":700,"weight":"1 unit","imageUrl":"images\\\\Digital Glucometer 620.jpg"},
  {"name":"Glucometer Test Strips","price":800,"weight":"Pack of 50","imageUrl":"images\\\\Glucometer Test Strips.jpg"},
  {"name":"Lancets for Glucometer","price":250,"weight":"Pack of 100","imageUrl":"images\\\\lancets\\\\Lancets for Glucometer 130.jpg"},
  {"name":"Liver Tonic Syrup","price":140,"weight":"200 ml","imageUrl":"images\\\\Liver Tonic Syrup 120.jpg"},
  {"name":"Torque’s Torliv Ayurvedic Healthy Liver Tonic Syrup","price":140,"weight":"200 ml","imageUrl":"images\\\\Torque’s Torliv Ayurvedic Healthy Liver Tonic Syrup – 200m 130.jpg"},
  {"name":"Herbal Immunity Booster","price":260,"weight":"250 ml","imageUrl":"images\\\\Herbal Immunity Booster 230.jpg"},
  {"name":"Herbal Booster Original Tablets For Immunity Booster","price":960,"weight":"30 capsules","imageUrl":"images\\\\Herbal Booster Original Tablets For Immunity Booster (30 Tablets) 960.jpg"},
  {"name":"Attar Ayurveda Herbalvilla Immunity Booster capsules","price":300,"weight":"60 capsules","imageUrl":"images\\\\Attar Ayurveda Herbalvilla Immunity Booster capsules 60 capsules 300.jpg"},
  {"name":"Ambrosial Herbal Immunity Booster","price":700,"weight":"60 tablets","imageUrl":"images\\\\Ambrosial Herbal Immunity Booster 60 Tablets 630-700.jpg"},
  {"name":"Spice Sip Natural Immunity Booster Herbal Tea","price":300,"weight":"capsules","imageUrl":"images\\\\Spice Sip Natural Immunity Booster Herbal Tea165.jpg"},
  {"name":"Tulsi Drops","price":120,"weight":"30 ml","imageUrl":"images\\\\Tulsi Drops 160.jpg"},
  {"name":"Dabur Tulsi Drops","price":190,"weight":"30 ml","imageUrl":"images\\\\Dabur Tulsi Drops 120-190.jpg"},
  {"name":"Apollo Pharmacy Panch Tulsi Drops","price":180,"weight":"30 ml","imageUrl":"images\\\\Apollo Pharmacy Panch Tulsi Drops 175.jpg"},
  {"name":"MyUpchar Tulsi Drops","price":260,"weight":"30 ml","imageUrl":"images\\\\MyUpchar Tulsi Drops 260.jpg"},
  {"name":"mrit Tulsi Drops","price":330,"weight":"30 ml","imageUrl":"images\\\\mrit Tulsi Drops 330.jpg"},
  {"name":"Ashwagandha Capsules","price":810,"weight":"60 capsules","imageUrl":"images\\\\Ashwagandha Capsules 810.jpg"},
  {"name":"ORGANIC INDIA Ashwagandha Capsules","price":380,"weight":"60 capsules","imageUrl":"images\\\\ORGANIC INDIA Ashwagandha Capsules 380.jpg"},
  {"name":"Himalaya Pain Relief Oil","price":170,"weight":"100 ml","imageUrl":"images\\\\Himalaya Pain Relief Oil 100-150.jpg"},
  {"name":"Hiran Pain Relief Oil","price":150,"weight":"100 ml","imageUrl":"images\\\\Hiran Pain Relief Oil 480.jpg"},
  {"name":"Pain Relief Oil 50 ml Pack of 1 Krishna s Herbal & Ayurveda","price":150,"weight":"100 ml","imageUrl":"images\\\\Pain Relief Oil 50 ml Pack of 1 Krishna\'s Herbal & Ayurveda 140.jpg"},
  {"name":"Apollo Pharmacy Pain Relief Oil","price":120,"weight":"100 ml","imageUrl":"images\\\\Apollo Pharmacy Pain Relief Oil 120.jpg"},
  {"name":"Cold Relief Balm","price":60,"weight":"20 g","imageUrl":"images\\\\Cold Relief Balm 45.jpg"},
  {"name":"Cold Relief Balm","price":120,"weight":"20 g","imageUrl":"images\\\\Cold Relief Balm 46-120.jpg"},
  {"name":"Anti-Allergy Tablets","price":320,"weight":"10 tablets","imageUrl":"images\\\\Anti-Allergy Tablets 320.jpg"},
  {"name":"Throat Lozenges","price":65,"weight":"Pack of 20","imageUrl":"images\\\\Throat Lozenges 47.jpg"},
  {"name":"Strepsils Sore Throat Lozenges - Ginger & Lemon","price":65,"weight":"Pack of 20","imageUrl":"images\\\\Strepsils Sore Throat Lozenges - Ginger & Lemon 35.jpg"},
  {"name":"Oral Rehydration Drink","price":40,"weight":"250 ml","imageUrl":"images\\\\Oral Rehydration Drink 32.jpg"},
  {"name":"Foot Care Cream","price":100,"weight":"50 g","imageUrl":"images\\\\Foot Care Cream 95.jpg"},
  {"name":"Himalaya Foot Care Cream","price":140,"weight":"50 g","imageUrl":"images\\\\Himalaya Foot Care Cream 135.jpg"},
  {"name":"Moha Foot Care Cream","price":320,"weight":"50 g","imageUrl":"images\\\\Moha Foot Care Cream 318.jpg"},
  {"name":"Crack Heel Repair Cream","price":90,"weight":"60 g","imageUrl":"images\\\\Crack Heel Repair Cream 88.jpg"},
  {"name":"Heelmate Cracked Heel Repair Cream","price":140,"weight":"60 g","imageUrl":"images\\\\Heelmate Cracked Heel Repair Cream 136.jpg"},
  {"name":"Sunscreen Lotion SPF 50","price":350,"weight":"100 ml","imageUrl":"images\\\\lotion\\\\Sunscreen Lotion SPF 50.jpg"},
  {"name":"Lip Balm Medicated","price":75,"weight":"10 g","imageUrl":"images\\\\Lip Balm Medicated.jpg"},
  {"name":"Antifungal Powder","price":90,"weight":"100 g","imageUrl":"images\\\\Antifungal Powder 80.jpg"},
  {"name":"Toilet Seat Sanitizer Spray","price":80,"weight":"75 ml","imageUrl":"images\\\\Toilet Seat Sanitizer Spray 72.jpg"},
  {"name":"Toilet Seat Sanitizer Spray","price":200,"weight":"75 ml","imageUrl":"images\\\\Toilet Seat Sanitizer Spray  200.jpg"},
  {"name":"Evereve Pee Secure Toilet Seat Sanitizer Spray Combo","price":200,"weight":"75 ml","imageUrl":"images\\\\Evereve Pee Secure Toilet Seat Sanitizer Spray Combo 190.jpg"},
  {"name":"Harpic Toilet Seat Sanitizer & Disinfectant Spray","price":220,"weight":"75 ml","imageUrl":"images\\\\Harpic Toilet Seat Sanitizer & Disinfectant Spray 210.jpg"},
  {"name":"Disinfectant Surface Spray","price":220,"weight":"200 ml","imageUrl":"images\\\\Disinfectant Surface Spray 169.jpg"},
  {"name":"Vitamin B-Complex Tablets","price":170,"weight":"30 tablets","imageUrl":"images\\\\Vitamin B-Complex Tablets.jpg"},
  {"name":"Omega 3 Fish Oil Capsules","price":450,"weight":"60 capsules","imageUrl":"images\\\\Omega 3 Fish Oil Capsules.jpg"},
  {"name":"Collagen Powder","price":900,"weight":"250 g","imageUrl":"images\\\\Collagen Powder.jpg"},
  {"name":"Protein Bars","price":420,"weight":"Pack of 6","imageUrl":"images\\\\Protein Bars 420.jpg"},
  {"name":"Energy Drink Powder","price":360,"weight":"500 g","imageUrl":"images\\\\Energy Drink Powder.jpg"},
  {"name":"Anti-Itch Cream","price":295,"weight":"30 g","imageUrl":"images\\\\Anti-Itch Cream 290.jpg"},
  {"name":"Skin Rash Ointment","price":110,"weight":"25 g","imageUrl":"images\\\\Skin Rash Ointment.jpg"},
  {"name":"Acne Treatment Gel","price":250,"weight":"20 g","imageUrl":"images\\\\Acne Treatment Gel 230.jpg"},
  {"name":"Benzoyl Peroxide Acne Treatment Gel","price":250,"weight":"20 g","imageUrl":"images\\\\Benzoyl Peroxide Acne Treatment Gel 79.jpg"},
  {"name":"Scar Removal Gel","price":520,"weight":"20 g","imageUrl":"images\\\\Scar Removal Gel 500.jpg"},
  {"name":"Hair Loss Tablets","price":170,"weight":"tablets","imageUrl":"images\\\\Hair Loss Tablets refollium biotin 169.jpg"},
  {"name":"Beard Growth Oil","price":600,"weight":"50 ml","imageUrl":"images\\\\Beard Growth Oil 419-600.jpg"},
  {"name":"Bombay Shaving Company Beard Growth Oil","price":600,"weight":"50 ml","imageUrl":"images\\\\Bombay Shaving Company Beard Growth Oil 250-400.jpg"},
  {"name":"Urban Gabru Beard Booster Growth Oil","price":390,"weight":"50 ml","imageUrl":"images\\\\Urban Gabru Beard Booster Growth Oil 380.jpg"},
  {"name":"Ustraa Beard Growth Oil","price":350,"weight":"50 ml","imageUrl":"images\\\\Ustraa Beard Growth Oil 330.jpg"},
  {"name":"Nourish Mantra Advanced Beard Growth Oil Serum","price":360,"weight":"50 ml","imageUrl":"images\\\\Nourish Mantra Advanced Beard Growth Oil Serum 360.jpg"},
  {"name":"MAX-G Hefty Beard Growth Oil 50 ml Bottle","price":550,"weight":"50 ml","imageUrl":"images\\\\MAX-G Hefty Beard Growth Oil 50 ml Bottle 550.jpg"},
  {"name":"Baby Diaper Rash Cream","price":180,"weight":"50 g","imageUrl":"images\\\\Baby Diaper Rash Cream.jpg"},
  {"name":"Thermometer (Infrared)","price":900,"weight":"1 unit","imageUrl":"images\\\\Thermometer (Infrared) 870.jpg"},
  {"name":"Dr Trust USA Forehead Digital Infrared Thermometer","price":1400,"weight":"1 unit","imageUrl":"images\\\\Dr Trust USA Forehead Digital Infrared Thermometer 1400.jpg"},
  {"name":"Ice Gel Pack","price":120,"weight":"1 unit","imageUrl":"images\\\\Ice Gel Pack.jpg"},
  {"name":"Dr. Odin Electric Heating Pad","price":1000,"weight":"1 unit","imageUrl":"images\\\\Dr. Odin Electric Heating Pad 1000.jpg"},
  {"name":"electric heating pad multicolour","price":190,"weight":"1 unit","imageUrl":"images\\\\electric heating pad multicolour 160.jpg"},
  {"name":"Wheelchair Manual","price":9000,"weight":"1 unit","imageUrl":"images\\\\Wheelchair Manual 9000.jpg"},
  {"name":"Walking Stick","price":400,"weight":"1 unit","imageUrl":"images\\\\Walking Stick 400.jpg"},
  {"name":"Walking Stick","price":500,"weight":"1 unit","imageUrl":"images\\\\Walking Stick 475.jpg"},
  {"name":"Dr Trust USA Orthopedic King Size Cervical Pillow","price":1200,"weight":"1 unit","imageUrl":"images\\\\Dr Trust USA Orthopedic King Size Cervical Pillow 1200.jpg"},
  {"name":"Dr Trust USA Orthopedic King Size Cervical Pillow","price":1200,"weight":"1 unit","imageUrl":"images\\\\Dr Trust USA Orthopedic King Size Cervical Pillow 700.jpg"},
  {"name":"Cervical Collar","price":350,"weight":"1 unit","imageUrl":"images\\\\Cervical Collar 300.jpg"},
  {"name":"Dyna Soft Cervical Collar","price":350,"weight":"1 unit","imageUrl":"images\\\\Dyna Soft Cervical Collar 420.jpg"},
  {"name":"Nivia Orthopedic Basic Elbow Support","price":250,"weight":"1 unit","imageUrl":"images\\\\Nivia Orthopedic Basic Elbow Support 180.jpg"},
  {"name":"Fovera Elbow Support Brace with Strap","price":290,"weight":"1 unit","imageUrl":"images\\\\Fovera Elbow Support Brace with Strap 280.jpg"},
  {"name":"Lite Feel Hand And Wrist Support Band","price":200,"weight":"1 unit","imageUrl":"images\\\\Lite Feel Hand And Wrist Support Band 200.jpg"},
  {"name":"Lite Feel Hand And Wrist Support Band","price":200,"weight":"1 unit","imageUrl":"images\\\\Lite Feel Hand And Wrist Support Band 360.jpg"},
  {"name":"Compression Stockings","price":790,"weight":"Pair","imageUrl":"images\\\\Compression Stockings 790.jpg"},
  {"name":"Diabetes Care Powder","price":650,"weight":"400 g","imageUrl":"images\\\\Diabetes Care Powder 625.jpg"},
  {"name":"Sugar-Free Tablets","price":260,"weight":"100 tablets","imageUrl":"images\\\\Sugar-Free Tablets 260.jpg"},
  {"name":"Herbal Tea (Immunity)","price":380,"weight":"100 g","imageUrl":"images\\\\Herbal Tea (Immunity) 380.jpg"},
  {"name":"Immunity Herbal Tea","price":800,"weight":"100 g","imageUrl":"images\\\\Immunity Herbal Tea 800.jpg"},
  {"name":"Spice Sip Natural Immunity Booster Herbal Tea","price":170,"weight":"100 g","imageUrl":"images\\\\Spice Sip Natural Immunity Booster Herbal Tea165.jpg"},
  {"name":"Detox Juice","price":300,"weight":"L","imageUrl":"images\\\\Detox Juice 300.webp"},
  {"name":"Ayurvedic Detox Juice 1000 ml Pack of 1 Krishna s Herbal & Ayurveda","price":420,"weight":"L","imageUrl":"images\\\\Ayurvedic Detox Juice 1000 ml Pack of 1 Krishna\'s Herbal & Ayurveda 420.jpg"},
  {"name":"Detox Juice with Wheatgrass & Amla","price":300,"weight":"L","imageUrl":"images\\\\Detox Juice with Wheatgrass & Amla 300.jpg"},
  {"name":"Detox Juice with Wheatgrass & Amla","price":450,"weight":"L","imageUrl":"images\\\\Detox Juice with Wheatgrass & Amla 450.jpg"},
  {"name":"Multigrain Health Mix","price":320,"weight":"500 g","imageUrl":"images\\\\Multigrain Health Mix 300.jpg"},
  {"name":"Lion Health Mix Multigrain","price":160,"weight":"500 g","imageUrl":"images\\\\Lion Health Mix Multigrain  160.jpg"},
  {"name":"Manna Health Multigrain Mix","price":180,"weight":"500 g","imageUrl":"images\\\\Manna Health Multigrain Mix 165.jpg"},
  {"name":"Adult Diapers","price":220,"weight":"Pack","imageUrl":"images\\\\Adult Diapers 210.jpg"},
  {"name":"Dignity Premium Adult Diapers","price":500,"weight":"Pack","imageUrl":"images\\\\Dignity Premium Adult Diapers 500.jpg"},
  {"name":"Himalaya Adult Diapers Pants","price":410,"weight":"Pack","imageUrl":"images\\\\Himalaya Adult Diapers Pants 10\'S 410.jpg"},
  {"name":"Folic Acid Tablets","price":140,"weight":"30 tablets","imageUrl":"images\\\\Folic Acid Tablets 60.jpg"},
  {"name":"INLIFE Iron Folic Acid Supplement 60 Tablets","price":370,"weight":"30 tablets","imageUrl":"images\\\\INLIFE Iron Folic Acid Supplement 60 Tablets 352.jpg"},
  {"name":"Intas Folitas 5mg Folic Acid Tablets","price":40,"weight":"30 tablets","imageUrl":"images\\\\Intas Folitas 5mg Folic Acid Tablets 30.jpg"},
  {"name":"Magnesium Supplements","price":790,"weight":"30 tablets","imageUrl":"images\\\\Magnesium Supplements.jpg"},
  {"name":"Electrolyte Tablets","price":260,"weight":"20 tablets","imageUrl":"images\\\\Electrolyte Tablets 250.jpg"},
  {"name":"Anti-Diarrheal Tablets","price":120,"weight":"10 tablets","imageUrl":"images\\\\Anti-Diarrheal Tablets 120.jpg"},
  {"name":"Laxative Syrup","price":190,"weight":"200 ml","imageUrl":"images\\\\Laxative Syrup 185.jpg"},
  {"name":"Gas Relief Tablets","price":85,"weight":"15 tablets","imageUrl":"images\\\\Gas Relief Tablets.jpg"},
  {"name":"Motion Sickness Tablets","price":75,"weight":"10 tablets","imageUrl":"images\\\\Motion Sickness Tablets.jpg"},
  {"name":"Sleep Aid Tablets","price":110,"weight":"10 tablets","imageUrl":"images\\\\Sleep Aid Tablets.jpg"},
  {"name":"Stress Relief Capsules","price":2200,"weight":"30 capsules","imageUrl":"images\\\\Stress Relief Capsules.jpg"},
  {"name":"Memory Booster Syrup","price":590,"weight":"200 ml","imageUrl":"images\\\\Memory Booster Syrup.jpg"},
  {"name":"Joint Support Capsules","price":1200,"weight":"60 capsules","imageUrl":"images\\\\Joint Support Capsules 800-1200.jpg"},
  {"name":"Bone Health Powder","price":630,"weight":"500 g","imageUrl":"images\\\\Bone Health Powder 630.jpg"},
  {"name":"Kids Multivitamin Gummies","price":350,"weight":"60 gummies","imageUrl":"images\\\\Kids Multivitamin Gummies.jpg"}
]';
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    // Assign IDs sequentially and add Kannada name, category, description, variants
    $result = [];
    $id = 1000;
    foreach ($data as $item) {
        $name = $item['name'];
        $price = (float) $item['price'];
        $weight = $item['weight'];
        $imageUrl = $item['imageUrl'] ?? $item['image_url'] ?? '';
        // Determine category based on name keywords
        $category = 'vitamins'; // default
        $lower = strtolower($name);
        if (strpos($lower, 'pain') !== false || strpos($lower, 'balm') !== false || strpos($lower, 'relief') !== false || strpos($lower, 'spray') !== false) {
            $category = 'pain';
        } elseif (strpos($lower, 'dettol') !== false || strpos($lower, 'bandage') !== false || strpos($lower, 'mask') !== false || strpos($lower, 'first aid') !== false || strpos($lower, 'tape') !== false || strpos($lower, 'gloves') !== false || strpos($lower, 'shield') !== false) {
            $category = 'firstaid';
        }
        if ($imageUrl && !str_starts_with($imageUrl, 'http') && !str_starts_with($imageUrl, 'data:')) {
            $imageUrl = normalizeImagePath($imageUrl);
        }
        // Kannada name: we'll just use the same as English for fallback
        $knName = $name;
        $result[] = [
            'id' => $id++,
            'name' => $name,
            'kn_name' => $knName,
            'price' => $price,
            'category' => $category,
            'variants' => [['label' => $weight, 'price' => $price]],
            'emoji' => '💊',
            'description_en' => 'Trusted quality health product',
            'image_url' => $imageUrl,
            'image' => $imageUrl
        ];
    }
    $fallback = $result;
    file_put_contents($jsonPath, json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $fallback;
}
// If products from DB are empty, use fallback
if (empty($products)) {
    $products = getFallbackProducts();
}
// Ensure each product has standard keys
foreach ($products as &$p) {
    if (!isset($p['variants']) || !is_array($p['variants']) || empty($p['variants'])) {
        $p['variants'] = [['label' => '1 unit', 'price' => $p['price']]];
    }
    $p['image'] = $p['image_url'] ?? $p['imageUrl'] ?? $p['image'] ?? '';
}
unset($p);
// Get cart count and total
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
// Fallback to extract top picks (removed top picks section)
$topPicks = array_slice($products, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Health & Pharmacy · KSR Guru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap"
        rel="stylesheet">
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
            overflow: hidden;
            background: #fcf4ec;
            border: 2px solid #f0d9c4;
            display: flex;
            align-items: center;
            justify-content: center;
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
        <div class="search-section">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
            </div>
            <div class="filter-pills" id="filterPills">
                <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
                <div class="pill" data-category="vitamins">Vitamins / ವಿಟಮಿನ್ಸ್</div>
                <div class="pill" data-category="pain">Pain Relief / ನೋವು ನಿವಾರಕ</div>
                <div class="pill" data-category="firstaid">First Aid / ಪ್ರಥಮ ಚಿಕಿತ್ಸೆ</div>
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
                                    $subcat = $p['category'] ?? 'vitamins';
                                    $imageUrl = $p['image_url'] ?? $p['imageUrl'] ?? '';
                                    // Normalize image path if needed
                                    if ($imageUrl && !str_starts_with($imageUrl, 'http') && !str_starts_with($imageUrl, 'data:')) {
                                        $imageUrl = str_replace('D:\\KSR Guru JI\\', '', $imageUrl);
                                        $imageUrl = str_replace('\\', '/', $imageUrl);
                                        if (!str_starts_with($imageUrl, '../')) {
                                            $imageUrl = '../' . $imageUrl;
                                        }
                                    }
                                    $out[] = [
                                        'id' => $p['id'],
                                        'nameEn' => $p['name'],
                                        'nameKn' => $p['kn_name'] ?? $p['name'],
                                        'category' => $subcat,
                                        'variants' => $p['variants'] ?? [['label' => '1 unit', 'price' => $p['price']]],
                                        'emoji' => $p['emoji'] ?? '💊',
                                        'description' => $p['description_en'] ?? 'Trusted Quality Formula',
                                        'image' => $imageUrl
                                    ];
                                }
                                echo json_encode($out);
                                ?>;
            let cart = [];
            const basePath = 'health-pharmacy.php';
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
          <div class="cart-item-image">💊</div>
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
                    const matchSearch = searchTerm === '' || p.nameEn.toLowerCase().includes(searchTerm) || p.nameKn
                        .toLowerCase().includes(searchTerm);
                    return matchCat && matchSearch;
                });
                if (!filtered.length) {
                    grid.innerHTML =
                        '<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No health products found ✨</div>';
                    return;
                }
                grid.innerHTML = filtered.map(p => {
                    const defaultVariant = 0;
                    const defaultPrice = p.variants[defaultVariant]?.price || 0;
                    // Image fallback: if image exists use <img>, else emoji
                    const imageHtml = p.image ? `<img src="${p.image}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='${p.emoji}'">` : `<div style="font-size:3rem; margin:auto;">${p.emoji}</div>`;
                    return `
                    <div class="product-card" data-base="${p.id}">
                        <div class="product-image">${imageHtml}</div>
                        <div class="product-name">${p.nameEn}</div>
                        ${p.nameKn && p.nameKn.toLowerCase() !== p.nameEn.toLowerCase() ? `${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}` : ''}
                        <div class="product-description">${p.description}</div>
                        ${p.variants && p.variants.length > 1 
                          ? `<select class="weight-select" data-base="${p.id}">
                              ${p.variants.map((v, i) => `<option value="${i}" ${i === defaultVariant ? 'selected' : ''}>${v.label} - ₹${v.price}</option>`).join('')}
                             </select>`
                          : `<div class="variant-text" style="font-size:0.85rem; font-weight:600; color:#4f3e2e; text-align:center; padding:10px; margin:8px 0 6px; width:100%; max-width:200px; background:#faf4ee; border-radius:50px; border:1px solid #e6d9cd;">${p.variants && p.variants[0] ? p.variants[0].label : '1 unit'}</div>`
                        }
                        <div class="price-row">
                            <span class="product-price">₹${defaultPrice}</span>
                            <button class="add-btn" data-pid="${p.id}" onclick="window.addToCart(${p.id})"><i class="fas fa-cart-plus"></i></button>
                        </div>
                    </div>
                `;
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