<?php
// ============================================================
// bath-body.php - Dynamic Bath & Body product page
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Get current user info if logged in
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
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
            echo json_encode(getBathBodyProducts($search));
            exit;
        case 'cart':
            echo json_encode($_SESSION['cart']);
            exit;
        default:
            echo json_encode(['error' => 'Invalid action']);
            exit;
    }
}
// ============================================================
// HELPER: Fetch bath & body products using MySQL
// ============================================================
function getBathBodyProducts(string $search = ''): array
{
    global $pdo;
    // If $pdo is not available (e.g., not configured), return empty
    if (!isset($pdo)) {
        return [];
    }
    $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                   c.name AS category_name, c.kn_name AS category_kn
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE c.id = 'bath-body'";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (p.name LIKE ? OR p.kn_name LIKE ?)";
        $searchWild = '%' . $search . '%';
        $params[] = $searchWild;
        $params[] = $searchWild;
    }
    $sql .= " ORDER BY p.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['description_en'] = '';
        $row['variants'] = null;
        $rows[] = $row;
    }
    return $rows;
}
// ============================================================
// FALLBACK: Load all 550+ products from the provided JSON
// ============================================================
function getFallbackProducts() {
    static $fallback = null;
    if ($fallback !== null) {
        return $fallback;
    }
    // The complete JSON data from user's reference (embedded in code)
    $json = '[
  {"id":1,"name":"Soap Bar","nameKn":"ಸೋಪ್ ಬಾರ್","category":"soap","description":"Classic soap bar for daily cleansing. Removes dirt and leaves skin fresh.","image":"images\\\\Soap Bar 50.jpg","weights":[{"label":"100g","price":50}]},
  {"id":2,"name":"Herbal Soap","nameKn":"ಹರ್ಬಲ್ ಸೋಪ್","category":"soap","description":"Infused with natural herbs. Gentle on skin with a refreshing fragrance.","image":"images\\\\Herbal Soap 220.jpg","weights":[{"label":"125g","price":220}]},
  {"id":3,"name":"Medicated Soap","nameKn":"ಮೆಡಿಕೇಟೆಡ್ ಸೋಪ್","category":"soap","description":"Antibacterial formula helps fight skin infections. Dermatologist recommended.","image":"images\\\\Medicated Soap 80.jpg","weights":[{"label":"100g","price":80}]},
  {"id":4,"name":"Body Wash","nameKn":"ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Gentle cleansing body wash with moisturizing properties.","image":"images\\\\Body Wash 420.jpg","weights":[{"label":"250ml","price":420}]},
  {"id":5,"name":"Moisturizing Body Wash","nameKn":"ಮಾಯಿಶ್ಚರೈಜಿಂಗ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Keeps skin hydrated and soft. Suitable for dry skin types.","image":"images\\\\Moisturizing Body Wash 347.jpg","weights":[{"label":"300ml","price":347}]},
  {"id":6,"name":"Charcoal Body Wash","nameKn":"ಚಾರ್ಕೋಲ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Deep cleanses pores with activated charcoal. Removes toxins and oil.","image":"images\\\\Charcoal Body Wash 250.jpg","weights":[{"label":"250ml","price":250}]},
  {"id":7,"name":"Shampoo","nameKn":"ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Daily care shampoo for healthy and shiny hair.","image":"images\\\\Shampoo 120.jpg","weights":[{"label":"200ml","price":120}]},
  {"id":8,"name":"Anti-Dandruff Shampoo","nameKn":"ಆಂಟಿ-ಡ್ಯಾಂಡ್ರಫ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Controls dandruff and soothes itchy scalp.","image":"images\\\\Anti-Dandruff Shampoo 170.jpg","weights":[{"label":"180ml","price":170}]},
  {"id":9,"name":"Herbal Shampoo","nameKn":"ಹರ್ಬಲ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Made with natural extracts for gentle hair care.","image":"images\\\\Herbal Shampoo 300.jpg","weights":[{"label":"200ml","price":300}]},
  {"id":10,"name":"Volume Boost Conditioner","nameKn":"ಕಂಡೀಷನರ್","category":"conditioner","description":"Adds shine and manages frizz for smooth hair.","image":"images\\\\Volume Boost Conditioner 400.jpg","weights":[{"label":"200ml","price":400}]},
  {"id":11,"name":"Hair Conditioner Smooth","nameKn":"ಹೇರ್ ಕಂಡೀಷನರ್ ಸ್ಮೂತ್","category":"conditioner","description":"Silky smooth formula for easy detangling.","image":"images\\\\Hair Conditioner Smooth 350.jpg","weights":[{"label":"250ml","price":350}]},
  {"id":12,"name":"Parachute 100% Pure Coconut Hair Oil","nameKn":"ಕೊಕೊನಟ್ ಹೇರ್ ಆಯಿಲ್","category":"hairoil","description":"Nourishes hair from root to tip. Promotes healthy growth.","image":"images\\\\Parachute 100% Pure Coconut Hair Oil  99.jpg","weights":[{"label":"200ml","price":100}]},
  {"id":13,"name":"Almond Hair Oil","nameKn":"ಆಲ್ಮಂಡ್ ಹೇರ್ ಆಯಿಲ್","category":"hairoil","description":"Rich in Vitamin E for strong and shiny hair.","image":"images\\\\Bajaj Almond Drops Hair Oil 75.jpg","weights":[{"label":"150ml","price":80}]},
  {"id":14,"name":"Amla Hair Oil","nameKn":"ಆಮ್ಲಾ హేర్ ఆయిల్","category":"hairoil","description":"Prevents premature graying and strengthens hair follicles.","image":"images\\\\Amla Hair Oil 230.jpg","weights":[{"label":"200ml","price":230}]},
  {"id":15,"name":"Face Wash","nameKn":"ఫేస్ వాష్","category":"facewash","description":"Gentle face wash for daily cleansing.","image":"images\\\\Face Wash 440.jpg","weights":[{"label":"100ml","price":440}]},
  {"id":17,"name":"Neem Face Wash","nameKn":"ನೀಮ್ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Antibacterial formula for acne-prone skin.","image":"images\\\\Neem Face Wash 195.jpg","weights":[{"label":"100ml","price":200}]},
  {"id":18,"name":"Face Scrub","nameKn":"ಫೇಸ್ ಸ್ಕ್ರಬ್","category":"scrub","description":"Exfoliates dead skin cells for a radiant glow.","image":"images\\\\Face Scrub 200.jpg","weights":[{"label":"100g","price":200}]},
  {"id":19,"name":"Body Scrub","nameKn":"ಬಾಡಿ ಸ್ಕ್ರಬ್","category":"scrub","description":"Removes dullness and reveals smooth skin.","image":"images\\\\Body Scrub 390.jpg","weights":[{"label":"200g","price":3900}]},
  {"id":20,"name":"Face Cream","nameKn":"ಫೇಸ್ ಕ್ರೀಮ್","category":"cream","description":"Daily moisturizer for soft and supple skin.","image":"images\\\\Face Cream 190.jpg","weights":[{"label":"100g","price":190}]},
  {"id":21,"name":"Moisturizing Cream","nameKn":"ಮಾಯಿಶ್ಚರೈಜಿಂಗ್ ಕ್ರೀಮ್","category":"cream","description":"Deep hydration for dry and flaky skin.","image":"images\\\\Moisturizing Cream 340.jpg","weights":[{"label":"200g","price":350}]},
  {"id":22,"name":"Body Lotion","nameKn":"ಬಾಡಿ ಲೋಷನ್","category":"lotion","description":"Lightweight hydration for all skin types.","image":"images\\\\Body Lotion 220.jpg","weights":[{"label":"200ml","price":220}]},
  {"id":23,"name":"Body Lotion","nameKn":"ಬಾಡಿ ಲೋಷನ್","category":"lotion","description":"Moisturizes and nourishes dry skin.","image":"images\\\\Nivea Aloe Hydration Body Lotion 190.jpg","weights":[{"label":"100ml","price":190}]},
  {"id":28,"name":"Perfume","nameKn":"ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Premium fragrance for men and women.","image":"images\\\\Perfume 275.jpg","weights":[{"label":"100ml","price":280}]},
  {"id":29,"name":"Body Mist","nameKn":"ಬಾಡಿ ಮಿಸ್ಟ್","category":"perfume","description":"Light and refreshing daily spray.","image":"images\\\\Body Mist 350.jpg","weights":[{"label":"150ml","price":350}]},
  {"id":30,"name":"Talcum Powder","nameKn":"ಟಾಲ್ಕಮ್ ಪೌಡರ್","category":"talc","description":"Keeps skin dry and fresh.","image":"images\\\\Powder\\\\Cooling Talcum Powder 250.jpg","weights":[{"label":"1 pc","price":260}]},
  {"id":31,"name":"Baby Powder","nameKn":"ಬೇಬಿ ಪೌಡರ್","category":"talc","description":"Soft and gentle for baby skin.","image":"images\\\\Powder\\\\Baby Powder 498.jpg","weights":[{"label":"400g","price":500}]},
  {"id":32,"name":"Hand Wash Liquid","nameKn":"ಹೆಂಡ್ ವಾಷ್ ಲಿಕುಡ್","category":"handwash","description":"Effective germ protection.","image":"images\\\\Hand Wash Liquid 80.jpg","weights":[{"label":"250ml","price":80}]},
  {"id":33,"name":"Antibacterial Hand Wash","nameKn":"ಆಂಟಿ ಬ್ಯಾಕ್ಟೀರಿಯಲ್ ಹ್ಯಾಂಡ್ ವಾಶ್","category":"handwash","description":"Kills 99.9% germs.","image":"images\\\\Antibacterial Hand Wash 120.jpg","weights":[{"label":"250ml","price":120}]},
  {"id":34,"name":"Hand Sanitizer","nameKn":"ಹ್ಯಾಂಡ್ ಸ್ಯಾನಿಟೈಜರ್","category":"sanitizer","description":"Alcohol based sanitizer for on-the-go.","image":"images\\\\Hand Sanitizer 50.jpg","weights":[{"label":"100ml","price":70}]},
  {"id":35,"name":"Pocket Sanitizer","nameKn":"ಪಾಕೆಟ್ ಸ್ಯಾನಿಟೈಜರ್","category":"sanitizer","description":"Compact size for travel.","image":"images\\\\Pocket Sanitizer 400.jpg","weights":[{"label":"50ml","price":400}]},
  {"id":36,"name":"Razor","nameKn":"ರೇಜರ್","category":"razor","description":"Safe and sharp blade for smooth shave.","image":"images\\\\Razor 150.jpg","weights":[{"label":"1pc","price":150}]},
  {"id":37,"name":"Shaving Razor Set","nameKn":"ಶೇವಿಂಗ್ ರೇಜರ್ ಸೆಟ್","category":"razor","description":"Includes 3 high quality razors.","image":"images\\\\Shaving Razor Set 500.jpg","weights":[{"label":"3pcs","price":500}]},
  {"id":38,"name":"Shaving Cream","nameKn":"ಶೇವಿಂಗ್ ಕ್ರೀಮ್","category":"shavecream","description":"Rich lather for smooth glide.","image":"images\\\\Shaving Cream 250.jpg","weights":[{"label":"100g","price":250}]},
  {"id":39,"name":"Shaving Gel","nameKn":"ಶೇವಿಂಗ್ ಜೆಲ್","category":"shavecream","description":"Cooling gel for sensitive skin.","image":"images\\\\Shaving Gel 450.jpg","weights":[{"label":"100g","price":450}]},
  {"id":40,"name":"After Shave Lotion","nameKn":"ಆಫ್ಟರ್ ಶೇವ್ ಲೋಷನ್","category":"aftershave","description":"Soothes skin post shave.","image":"images\\\\After Shave Lotion 250.jpg","weights":[{"label":"100ml","price":250}]},
  {"id":41,"name":"Toothpaste","nameKn":"ಟೂತ್ ಪೇಸ್ಟ್","category":"toothpaste","description":"Cavity protection and fresh breath.","image":"images\\\\toothpaste\\\\Toothpaste 85.jpg","weights":[{"label":"150g","price":90}]},
  {"id":42,"name":"Herbal Toothpaste","nameKn":"ಟೂತ್ ಬ್ರಷ್","category":"toothbrush","description":"Soft bristles for gentle cleaning.","image":"images\\\\toothpaste\\\\Herbal Toothpaste 158.jpg","weights":[{"label":"1pc","price":160}]},
  {"id":43,"name":"Mouthwash","nameKn":"ಮೌತ್ ವಾಶ್","category":"mouthwash","description":"Kills bad breath germs.","image":"images\\\\Mouthwash 260.jpg","weights":[{"label":"250ml","price":180}]},
  {"id":44,"name":"Dental Floss","nameKn":"ಡೆಂಟಲ್ ಫ್ಲಾಸ್","category":"dental","description":"Removes plaque between teeth.","image":"images\\\\Dental Floss 190.jpg","weights":[{"label":"1pc","price":120}]},
  {"id":45,"name":"Lip Balm","nameKn":"ಲಿಪ್ ಬಾಮ್","category":"lipbalm","description":"Moisturizes dry lips.","image":"images\\\\Lip Balm 300.jpg","weights":[{"label":"10g","price":120}]},
  {"id":46,"name":"Medicated Lip Balm","nameKn":"ಮೆಡಿಕೇಟೆಡ್ ಲಿಪ್ ಬಾಮ್","category":"lipbalm","description":"Heals chapped lips.","image":"images\\\\Medicated Lip Balm 370.jpg","weights":[{"label":"100g","price":370}]},
  {"id":49,"name":"Nail Cutter","nameKn":"ನೇಲ್ ಕಟ್ಟರ್","category":"nailcare","description":"Sharp and precise cutting.","image":"images\\\\Nail Cutter 120.jpg","weights":[{"label":"1pc","price":80}]},
  {"id":50,"name":"Manicure Kit","nameKn":"ಮ್ಯಾನಿಕ್ಯೂರ್ ಕಿಟ್","category":"nailcare","description":"Complete set for nail care.","image":"images\\\\Manicure Kit 550.jpg","weights":[{"label":"1set","price":400}]},
  {"id":51,"name":"Hair Brush","nameKn":"ಹೇರ್ ಬ್ರಷ್","category":"hairbrush","description":"Detangles and smooths hair.","image":"images\\\\Hair Brush 200.jpg","weights":[{"label":"1pc","price":150}]},
  {"id":52,"name":"Comb","nameKn":"ಕಂಬ್","category":"comb","description":"Durable and anti-static.","image":"images\\\\Comb 100.jpg","weights":[{"label":"1pc","price":40}]},
  {"id":53,"name":"Ultra Shine Hair Serum","nameKn":"ಹೇರ್ ಸೀರಮ್","category":"hairserum","description":"Adds shine and controls frizz.","image":"images\\\\Ultra Shine Hair Serum.jpg","weights":[{"label":"100ml","price":300}]},
  {"id":54,"name":"Hair Gel","nameKn":"ಹೇರ್ ಜೆಲ್","category":"hairgel","description":"Strong hold for styling.","image":"images\\\\Hair Gel 640.jpg","weights":[{"label":"ml","price":650}]},
  {"id":55,"name":"Hair Wax","nameKn":"ಹೇರ್ ವ್ಯಾಕ್ಸ್","category":"hairwax","description":"Matte finish with flexible hold.","image":"images\\\\Hair Wax 300.jpg","weights":[{"label":"100g","price":300}]},
  {"id":56,"name":"Face Pack","nameKn":"ಫೇಸ್ ಪ್ಯಾಕ್","category":"facepack","description":"Clay based for deep cleansing.","image":"images\\\\Face Pack.jpg","weights":[{"label":"100g","price":200}]},
  {"id":57,"name":"Sheet Mask","nameKn":"ಶೀಟ್ ಮಾಸ್ಕ್","category":"mask","description":"Hydrating single-use mask.","image":"images\\\\Sheet Mask 150.jpg","weights":[{"label":"1pc","price":150}]},
  {"id":58,"name":"Eye Cream","nameKn":"ಐ ಕ್ರೀಮ್","category":"eyecream","description":"Reduces dark circles and puffiness.","image":"images\\\\Eye Cream 250.jpg","weights":[{"label":"g","price":250}]},
  {"id":59,"name":"Face Toner","nameKn":"ಫೇಸ್ ಟೋನರ್","category":"toner","description":"Balances skin pH levels.","image":"images\\\\Face Toner 450.jpg","weights":[{"label":"150ml","price":250}]},
  {"id":60,"name":"Makeup Remover","nameKn":"ಮೇಕಪ್ ರಿಮೂವರ್","category":"makeupremover","description":"Oil-free gentle formula.","image":"images\\\\Makeup Remover 250.jpg","weights":[{"label":"100ml","price":300}]},
  {"id":61,"name":"Bath Salt","nameKn":"ಬಾತ್ ಸಾಲ್ಟ್","category":"bathsalt","description":"Relaxes muscles and detoxifies.","image":"images\\\\Bath Salt 450.jpg","weights":[{"label":"200g","price":450}]},
  {"id":63,"name":"Loofah","nameKn":"ಲೂಫಾ","category":"loofah","description":"Natural exfoliating sponge.","image":"images\\\\Loofah 60.jpg","weights":[{"label":"1pc","price":60}]},
  {"id":64,"name":"Bath Sponge","nameKn":"ಬಾತ್ ಸ್ಪಾಂಜ್","category":"sponge","description":"Soft and gentle lather.","image":"images\\\\Bath Sponge 190.jpg","weights":[{"label":"1pc","price":100}]},
  {"id":65,"name":"Bath Towel","nameKn":"ಬಾತ್ ಟವೆಲ್","category":"towel","description":"Absorbent cotton towel.","image":"images\\\\Bath Towel 1000.jpg","weights":[{"label":"1pc","price":500}]},
  {"id":66,"name":"Face Towel","nameKn":"ಫೇಸ್ ಟವೆಲ್","category":"towel","description":"Soft for face care.","image":"images\\\\Face Towel 350.jpg","weights":[{"label":"1pc","price":150}]},
  {"id":67,"name":"Bath Robe","nameKn":"ಬಾತ್ ರೋಬ್","category":"robe","description":"Comfortable cotton robe.","image":"images\\\\Bath Robe 1200.jpg","weights":[{"label":"1pc","price":1500}]},
  {"id":68,"name":"Bath Slippers","nameKn":"ಬಾತ್ ಸ್ಲಿಪ್ಪರ್ಸ್","category":"slippers","description":"Anti-slip rubber soles.","image":"images\\\\Bath Slippers 550.jpg","weights":[{"label":"1pair","price":550}]},
  {"id":69,"name":"Shower Cap","nameKn":"ಶವರ್ ಕ್ಯಾಪ್","category":"showercap","description":"Keeps hair dry while bathing.","image":"images\\\\Shower Cap 80.jpg","weights":[{"label":"1pc","price":80}]},
  {"id":70,"name":"Cotton Balls","nameKn":"ಕಾಟನ್ ಬಾಲ್ಸ್","category":"cotton","description":"Soft and absorbent.","image":"images\\\\Cotton Balls 85.jpg","weights":[{"label":"100g","price":90}]},
  {"id":71,"name":"Cotton Pads","nameKn":"ಕಾಟನ್ ಪ್ಯಾಡ್ಸ್","category":"cotton","description":"For makeup removal.","image":"images\\\\Cotton Pads 160.jpg","weights":[{"label":"100pcs","price":160}]},
  {"id":72,"name":"Bandage Roll","nameKn":"ಬ್ಯಾಂಡೇಜ್ ರೋಲ್","category":"firstaid","description":"Sterile gauze roll.","image":"images\\\\Bandage Roll 200.jpg","weights":[{"label":"1pc","price":200}]},
  {"id":73,"name":"Antiseptic Liquid","nameKn":"ಆಂಟಿಸೆಪ್ಟಿಕ್ ಲಿಕ್ವಿಡ್","category":"firstaid","description":"Disinfects wounds and cuts.","image":"images\\\\Antiseptic Liquid 140.jpg","weights":[{"label":"500ml","price":150}]},
  {"id":74,"name":"Healing Ointment","nameKn":"ಹೀಲಿಂಗ್ ಆಯಿಂಟುಂಟು","category":"firstaid","description":"For minor burns and scrapes.","image":"images\\\\Healing Ointment 4700.jpg","weights":[{"label":"50g","price":4780}]},
  {"id":75,"name":"Baby Soap","nameKn":"ಬೇಬಿ ಸೋಪ್","category":"baby","description":"Mild and tear-free.","image":"images\\\\Baby Soap 200.jpg","weights":[{"label":"100g","price":200}]},
  {"id":76,"name":"Baby Body Wash","nameKn":"ಬೇಬಿ ಬಾಡಿ ವಾಶ್","category":"baby","description":"Gentle cleanser for babies.","image":"images\\\\Powder\\\\Baby Body Wash 350.jpg","weights":[{"label":"200ml","price":250}]},
  {"id":77,"name":"Baby Lotion","nameKn":"ಬೇಬಿ ಲೋಷನ್","category":"baby","description":"Keeps baby skin soft.","image":"images\\\\Baby Lotion 120.jpg","weights":[{"label":"200ml","price":300}]},
  {"id":78,"name":"Baby Oil","nameKn":"ಬೇಬಿ ಆಯಿಲ್","category":"baby","description":"For baby massage.","image":"images\\\\Baby Oil 280.jpg","weights":[{"label":"200ml","price":220}]},
  {"id":79,"name":"Diaper Cream","nameKn":"ಡೈಪರ್ ಕ್ರೀಮ್","category":"baby","description":"Prevents diaper rash.","image":"images\\\\Diaper Cream 120.jpg","weights":[{"label":"100g","price":250}]},
  {"id":80,"name":"Baby Wipes","nameKn":"ಬೇಬಿ ವೈಪ್ಸ್","category":"baby","description":"Alcohol free gentle wipes.","image":"images\\\\Baby Wipes 210.jpg","weights":[{"label":"80pcs","price":180}]},
  {"id":81,"name":"Exfoliating Scrub","nameKn":"ಎಕ್ಸ್ಫೋಲಿಯೇಟಿಂಗ್ ಸ್ಕ್ರಬ್","category":"scrub","description":"Removes dead skin cells for a radiant glow.","image":"images\\\\Exfoliating Scrub 200.jpg","weights":[{"label":"200g","price":280}]},
  {"id":82,"name":"Massage Oil","nameKn":"ಮಸಾಜ್ ಆಯಿಲ್","category":"massageoil","description":"Relaxes muscles and improves blood circulation.","image":"images\\\\tablet\\\\HIMALAYA PAIN MASSAGE OIL 100 ML 250.jpg","weights":[{"label":"200ml","price":260}]},
  {"id":83,"name":"Aroma Oil","nameKn":"ಅರೋಮಾ ಆಯಿಲ್","category":"aromaoil","description":"Creates a calming and relaxing atmosphere.","image":"images\\\\Lavender Aroma Oil 400.jpg","weights":[{"label":"50ml","price":300}]},
  {"id":84,"name":"Essential Oil","nameKn":"ಎಸೆನ್ಷಿಯಲ್ ಆಯಿಲ್","category":"essentialoil","description":"Pure therapeutic grade oil for aromatherapy.","image":"images\\\\Essential Oil 400.jpg","weights":[{"label":"30ml","price":350}]},
  {"id":85,"name":"Foot Soak Salt","nameKn":"ಫುಟ್ ಸೋಕ್ ಸಾಲ್ಟ್","category":"footcare","description":"Relieves tired and achy feet.","image":"images\\\\Foot Soak Salt 750.jpg","weights":[{"label":"200g","price":220}]},
  {"id":86,"name":"Foot File","nameKn":"ಫುಟ್ ಫೈಲ್","category":"footcare","description":"Removes dead skin and calluses from feet.","image":"images\\\\Foot File 200.jpg","weights":[{"label":"1pc","price":120}]},
  {"id":87,"name":"Cracked Heel Cream","nameKn":"ಕ್ರ್ಯಾಕ್ಡ್ ಹೀಲ್ ಕ್ರೀಮ್","category":"footcream","description":"Repairs and softens dry cracked heels.","image":"images\\\\Cracked Heel Cream 110.jpg","weights":[{"label":"50g","price":200}]},
  {"id":88,"name":"Body Butter","nameKn":"ಬಾಡಿ ಬಟರ್","category":"bodybutter","description":"Deeply moisturizes and nourishes dry skin.","image":"images\\\\Body Butter 550.jpg","weights":[{"label":"200g","price":350}]},
  {"id":89,"name":"Body Mist Floral","nameKn":"ಬಾಡಿ ಮಿಸ್ಟ್ ಫ್ಲೋರಲ್","category":"perfume","description":"Light and refreshing floral fragrance.","image":"images\\\\Body Mist Floral 500.jpg","weights":[{"label":"150ml","price":600}]},
  {"id":90,"name":"Body Mist Citrus","nameKn":"ಬಾಡಿ ಮಿಸ್ಟ್ ಸಿಟ್ರಸ್","category":"perfume","description":"Energizing citrus scent for daily use.","image":"images\\\\Body Mist Citrus 680.jpg","weights":[{"label":"150ml","price":600}]},
  {"id":91,"name":"Hair Mask","nameKn":"ಹೇರ್ ಮಾಸ್ಕು","category":"hairmask","description":"Deep conditioning treatment for damaged hair.","image":"images\\\\Hair Mask 180.jpg","weights":[{"label":"200g","price":400}]},
  {"id":93,"name":"Detox Body Wash","nameKn":"ಡಿಟಾಕ್ಸ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Removes impurities and toxins from skin.","image":"images\\\\Detox Body Wash 450.jpg","weights":[{"label":"250ml","price":450}]},
  {"id":94,"name":"Herbal Cream","nameKn":"ಹರ್ಬಲ್ ಕ್ರೀಮ್","category":"cream","description":"Natural ingredients for healthy skin.","image":"images\\\\Herbal Cream 20.jpg","weights":[{"label":"100g","price":320}]},
  {"id":95,"name":"Aloe Vera Gel","nameKn":"ಅಲೋವೆರಾ ಜೆಲ್","category":"aloe","description":"Soothes sunburns and hydrates skin.","image":"images\\\\Aloe Vera Gel 100.jpg","weights":[{"label":"200g","price":180}]},
  {"id":96,"name":"Rose Water","nameKn":"ರೋಸ್ ವಾಟರ್","category":"toner","description":"Natural toner for glowing skin.","image":"images\\\\Rose Water 80.jpg","weights":[{"label":"200ml","price":150}]},
  {"id":97,"name":"Glycerin","nameKn":"ಗ್ಲಿಸರಿನ್","category":"moisturizer","description":"Hydrates and softens dry skin.","image":"images\\\\Glycerin 400.jpg","weights":[{"label":"100ml","price":120}]},
  {"id":98,"name":"Petroleum Jelly","nameKn":"ಪೆಟ್ರೋಲಿಯಂ ಜೆಲ್ಲಿ","category":"moisturizer","description":"Protects and heals dry, chapped skin.","image":"images\\\\Petroleum Jelly 500.jpg","weights":[{"label":"100g","price":140}]},
  {"id":99,"name":"Sunblock Cream","nameKn":"ಸನ್ ಬ್ಲಾಕ್ ಕ್ರೀಮ್","category":"sunscreen","description":"High protection against UV rays.","image":"images\\\\Sunblock Cream 280.jpg","weights":[{"label":"100ml","price":400}]},
  {"id":100,"name":"After Sun Gel","nameKn":"ಆಫ್ಟರ್ ಸನ್ ಜೆಲ್","category":"sunscreen","description":"Cools and hydrates sun-exposed skin.","image":"images\\\\After Sun Gel 320.jpg","weights":[{"label":"100ml","price":250}]},
  {"id":101,"name":"Refreshing Body Wash","nameKn":"ರಿಫ್ರೆಶಿಂಗ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Energizing formula for morning showers.","image":"images\\\\Refreshing Body Wash 280.jpg","weights":[{"label":"300ml","price":260}]},
  {"id":102,"name":"Luxury Soap","nameKn":"ಲಕ್ಷ್ಮಿ ಸೋಪ್","category":"soap","description":"Premium fragrance and moisturizing properties.","image":"images\\\\Luxury Soap 260.jpg","weights":[{"label":"125g","price":260}]},
  {"id":103,"name":"Premium Perfume","nameKn":"ಪ್ರೀಮಿಯಂ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Long-lasting luxury fragrance.","image":"images\\\\Premium Perfume.jpg","weights":[{"label":"100ml","price":1500}]},
  {"id":104,"name":"Sports Deodorant","nameKn":"ಸ್ಪೋರ್ಟ್ಸ್ ಡಿಯೋಡರೆಂಟ್","category":"deo","description":"Extra protection for active lifestyle.","image":"images\\\\Sports Deodorant  230.jpg","weights":[{"label":"150ml","price":300}]},
  {"id":105,"name":"Whitening Lotion","nameKn":"ವೈಟನಿಂಗ್ ಲೋಷನ್","category":"lotion","description":"Helps even out skin tone naturally.","image":"images\\\\Whitening Lotion 190.jpg","weights":[{"label":"300ml","price":350}]},
  {"id":106,"name":"Night Cream","nameKn":"ನೈಟ್ ಕ್ರೀಮ್","category":"cream","description":"Repairs skin while you sleep.","image":"images\\\\Night Cream 400.jpg","weights":[{"label":"100g","price":500}]},
  {"id":107,"name":"Day Cream","nameKn":"ಡೇ ಕ್ರೀಮ್","category":"cream","description":"Daily protection and hydration.","image":"images\\\\Day Cream 500.jpg","weights":[{"label":"100g","price":450}]},
  {"id":108,"name":"Face Serum","nameKn":"ഫേസ് സീരമ്മ്","category":"serum","description":"Concentrated treatment for targeted skin concerns.","image":"images\\\\Face Serum 200.jpg","weights":[{"label":"30ml","price":700}]},
  {"id":109,"name":"Vitamin C Serum","nameKn":"വിറ്റമിൻ സി സീരമ്മ്","category":"serum","description":"Brightens skin and reduces dark spots.","image":"images\\\\Vitamin C Serum 615.jpg","weights":[{"label":"30ml","price":900}]},
  {"id":110,"name":"Face Oil","nameKn":"ഫേസ് ആയിൽ","category":"faceoil","description":"Nourishing oil for radiant skin.","image":"images\\\\Face Oil 700.jpg","weights":[{"label":"30ml","price":600}]},
  {"id":111,"name":"Body Oil","nameKn":"ಬಾಡಿ ಆಯಿಲ್","category":"bodyoil","description":"Locks in moisture after shower.","image":"images\\\\Body Oil 650.jpg","weights":[{"label":"200ml","price":300}]},
  {"id":112,"name":"Hair Tonic","nameKn":"ಹೇರ್ ಟಾನಿಕ್","category":"hairtonic","description":"Promotes healthy hair growth.","image":"images\\\\Hair Tonic 610.jpg","weights":[{"label":"100ml","price":350}]},
  {"id":113,"name":"Dandruff Treatment Oil","nameKn":"ಡ್ಯಾಂಡ್ರಫ್ ಟ್ರೀಟ್ಮೆಂಟ್ ಆಯಿಲ್","category":"hairoil","description":"Controls dandruff and soothes scalp.","image":"images\\\\Dandruff Treatment Oil 370.jpg","weights":[{"label":"100ml","price":300}]},
  {"id":114,"name":"Hair Color","nameKn":"ಹೇರ್ ಕಲರ್","category":"haircolor","description":"Ammonia-free hair coloring kit.","image":"images\\\\hair color\\\\Hair Color 200.jpg","weights":[{"label":"1kit","price":250}]},
  {"id":115,"name":"Bleach Kit","nameKn":"ಬ್ಲೀಚ್ ಕಿಟ್","category":"bleach","description":"For facial and body hair lightening.","image":"images\\\\Bleach Kit 720.jpg","weights":[{"label":"1set","price":200}]},
  {"id":116,"name":"Wax Strips","nameKn":"ವ್ಯಾಕ್ಸ್ ಸ್ಟ್ರಿಪ್ಸ್","category":"waxing","description":"Ready-to-use for hair removal.","image":"images\\\\Wax Strips 90.jpg","weights":[{"label":"1pcs","price":90}]},
  {"id":117,"name":"Epilator","nameKn":"ಎಪಿಲೇಟರ್","category":"epilator","description":"Electric hair remover for smooth skin.","image":"images\\\\Epilator 500.jpg","weights":[{"label":"1pc","price":2500}]},
  {"id":118,"name":"Trimmer","nameKn":"ಟ್ರಿಮ್ಮರ್","category":"trimmer","description":"Multipurpose grooming trimmer.","image":"images\\\\Cordless Hair Trimmer.jpg","weights":[{"label":"1pc","price":2000}]},
  {"id":119,"name":"Shower Gel","nameKn":"ಶವರ್ ಜೆಲ್","category":"bodywash","description":"Refreshing gel for daily shower.","image":"images\\\\Shower Gel 150.jpg","weights":[{"label":"250ml","price":220}]},
  {"id":120,"name":"Body Foam Cleanser","nameKn":"ಬಾಡಿ ಫೋಮ್ ಕ್ಲೆನ್ಸರ್","category":"bodywash","description":"Lightweight foam for gentle cleansing.","image":"images\\\\Body Foam Cleanser 400.jpg","weights":[{"label":"200ml","price":240}]},
  {"id":121,"name":"Deep Clean Body Wash","nameKn":"ಡೀಪ್ ಕ್ಲೀನ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Removes dirt and excess oil thoroughly.","image":"images\\\\Deep Clean Body Wash 295.jpg","weights":[{"label":"300ml","price":280}]},
  {"id":122,"name":"Herbal Refresh Body Wash","nameKn":"ಹರ್ಬಲ್ ರಿಫ್ರೆಶ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Natural herbal extracts for freshness.","image":"images\\\\Herbal Refresh Body Wash 300.jpg","weights":[{"label":"250ml","price":300}]},
  {"id":123,"name":"Glycerin Soap","nameKn":"ಗ್ಲಿಸರಿನ್ ಸೋಪ್","category":"soap","description":"Transparent soap for sensitive skin.","image":"images\\\\Glycerin Soap 110.jpg","weights":[{"label":"125g","price":110}]},
  {"id":124,"name":"Sandal Soap","nameKn":"ಸ್ಯಾಂಡಲ್ ಸೋಪ್","category":"soap","description":"Classic sandalwood fragrance.","image":"images\\\\Sandal Soap 80.jpg","weights":[{"label":"100g","price":80}]},
  {"id":125,"name":"Aloe Soap","nameKn":"ಅಲೋ ಸೋಪ್","category":"soap","description":"Soothing aloe vera formula.","image":"images\\\\Aloe Soap 160.jpg","weights":[{"label":"100g","price":160}]},
  {"id":126,"name":"Protein Shampoo","nameKn":"ಪ್ರೋಟೀನ್ ಷಾಂಪೂ","category":"shampoo","description":"Strengthens hair with protein.","image":"images\\\\Protein Shampoo 250.jpg","weights":[{"label":"200ml","price":220}]},
  {"id":127,"name":"Keratin Shampoo","nameKn":"ಕೆರಾಟಿನ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Repairs damaged hair with keratin.","image":"images\\\\Keratin Shampoo 500.jpg","weights":[{"label":"250ml","price":500}]},
  {"id":128,"name":"Keratin Conditioner","nameKn":"ಕೆರಾಟಿನ್ ಕಂಡೀಷನರ್","category":"conditioner","description":"Smooths and detangles hair.","image":"images\\\\Keratin Conditioner 420.jpg","weights":[{"label":"200ml","price":320}]},
  {"id":129,"name":"Damage Repair Conditioner","nameKn":"ಡ್ಯಾಮೇಜ್ ರಿಪೇರ್ ಕಂಡೀಷನರ್","category":"conditioner","description":"Repairs split ends and damage.","image":"images\\\\Damage Repair Conditioner 300.jpg","weights":[{"label":"250ml","price":300}]},
  {"id":130,"name":"Onion Hair Oil","nameKn":"ಆನಿಯನ್ ಹೇರ್ ಆಯಿಲ್","category":"hairoil","description":"Promotes hair growth and reduces fall.","image":"images\\\\Onion Hair Oil 250.jpg","weights":[{"label":"200ml","price":240}]},
  {"id":131,"name":"Herbal Hair Oil","nameKn":"ಹರ್ಬಲ್ ಹೇರ್ ಆಯಿಲ್","category":"hairoil","description":"Traditional herbal blend for strong hair.","image":"images\\\\Herbal Hair Oil 650.jpg","weights":[{"label":"200ml","price":200}]},
  {"id":132,"name":"Anti-Frizz Serum","nameKn":"ಆಂಟಿ-ಫ್ರಿಜ್ ಸೀರಮ್","category":"hairserum","description":"Controls frizz and flyaways.","image":"images\\\\Anti-Frizz Serum 390.jpg","weights":[{"label":"100ml","price":350}]},
  {"id":133,"name":"Silky Hair Serum","nameKn":"ಸಿಲ್ಕಿ ಹೇರ್ ಸೀರಮ್","category":"hairserum","description":"Adds instant shine and smoothness.","image":"images\\\\Silky Hair Serum 380.jpg","weights":[{"label":"100ml","price":320}]},
  {"id":134,"name":"Oil Control Face Wash","nameKn":"ಆಯಿಲ್ ಕಂಟ್ರೋಲ್ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Controls excess sebum production.","image":"images\\\\Oil Control Face Wash 280.jpg","weights":[{"label":"150ml","price":290}]},
  {"id":135,"name":"Hydrating Face Wash","nameKn":"ಹೈಡ್ರೇಟಿಂಗ್ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Cleanses without stripping moisture.","image":"images\\\\Hydrating Face Wash 330.jpg","weights":[{"label":"150ml","price":200}]},
  {"id":136,"name":"Vitamin C Face Wash","nameKn":"ವಿಟಮಿನ್ ಸಿ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Brightens dull skin with vitamin C.","image":"images\\\\Vitamin C Face Wash 250.jpg","weights":[{"label":"150ml","price":220}]},
  {"id":137,"name":"Coffee Face Scrub","nameKn":"ಕಾಫಿ ಫೇಸ್ ಸ್ಕ್ರಬ್","category":"scrub","description":"Exfoliates and energizes skin.","image":"images\\\\Coffee Face Scrub 210.jpg","weights":[{"label":"100g","price":220}]},
  {"id":138,"name":"Sugar Body Scrub","nameKn":"ಶುಗರ್ ಬಾಡಿ ಸ್ಕ್ರಬ್","category":"scrub","description":"Gentle exfoliation for soft skin.","image":"images\\\\Sugar Body Scrub 550.jpg","weights":[{"label":"200g","price":300}]},
  {"id":139,"name":"Anti-Aging Cream","nameKn":"ಆಂಟಿ-ಏಜಿಂಗ್ ಕ್ರೀಮ್","category":"cream","description":"Reduces fine lines and wrinkles.","image":"images\\\\Anti-Aging Cream 170.jpg","weights":[{"label":"50g","price":600}]},
  {"id":140,"name":"Brightening Cream","nameKn":"ಬ್ರೈಟನಿಂಗ್ ಕ್ರೀಮ್","category":"cream","description":"Reduces dark spots and uneven tone.","image":"images\\\\Brightening Cream 250.jpg","weights":[{"label":"100g","price":450}]},
  {"id":141,"name":"Aloe Body Lotion","nameKn":"ಅಲೋ ಬಾಡಿ ಲೋಶನ್","category":"lotion","description":"Soothes and hydrates dry skin.","image":"images\\\\Aloe Body Lotion 320.jpg","weights":[{"label":"300ml","price":300}]},
  {"id":142,"name":"Cocoa Body Lotion","nameKn":"ಕೊಕ್ಕೊ ಬಾಡಿ ಲೋಷನ್","category":"lotion","description":"Rich cocoa butter moisturization.","image":"images\\\\Cocoa Body Lotion 380.jpg","weights":[{"label":"300ml","price":380}]},
  {"id":143,"name":"Sunscreen Gel SPF50","nameKn":"ಸನ್ ಸ್ಕ್ರೀನ್ ಜೆಲ್ SPF50","category":"sunscreen","description":"Lightweight gel formula, non-greasy.","image":"images\\\\Sunscreen Gel SPF50 350.jpg","weights":[{"label":"100ml","price":500}]},
  {"id":144,"name":"Herbal Deodorant","nameKn":"ಹರ್ಬಲ್ ಡಿಯೋಡರೆಂಟ್","category":"deo","description":"Natural ingredients for odor protection.","image":"images\\\\Herbal Deodorant 180.jpg","weights":[{"label":"150ml","price":280}]},
  {"id":145,"name":"Luxury Perfume Floral","nameKn":"ಲಕ್ಷ್ಮಿ ಪರ್ಫ್ಯೂಮ್ ಫ್ಲೋರಲ್","category":"perfume","description":"Exquisite floral bouquet fragrance.","image":"images\\\\Perfume 275.jpg","weights":[{"label":"100ml","price":2000}]},
  {"id":146,"name":"Luxury Perfume Woody","nameKn":"ಲಕ್ಷ್ಮಿ ಪರ್ಫ್ಯೂಮ್ ವುಡಿ","category":"perfume","description":"Warm and earthy woody scent.","image":"images\\\\Luxury Perfume Floral 200.jpg","weights":[{"label":"100ml","price":2200}]},
  {"id":147,"name":"Cooling Talcum Powder","nameKn":"ಕೂಲಿಂಗ್ ಟಾಲ್ಕಮ್ ಪೌಡರ್","category":"talc","description":"Refreshing powder with cooling effect.","image":"images\\\\Powder\\\\Cooling Talcum Powder 250.jpg","weights":[{"label":"200g","price":140}]},
  {"id":148,"name":"Herbal Hand Wash","nameKn":"ಹರ್ಬಲ್ ಹ್ಯಾಂಡ್ ವಾಶ್","category":"handwash","description":"Gentle on hands, tough on germs.","image":"images\\\\Herbal Hand Wash 150.jpg","weights":[{"label":"250ml","price":150}]},
  {"id":149,"name":"Aloe Sanitizer","nameKn":"ಅಲೋ ಸ್ಯಾನಿಟೈಜರ್","category":"sanitizer","description":"Aloe enriched alcohol sanitizer.","image":"images\\\\Aloe Sanitizer 65.jpg","weights":[{"label":"100ml","price":90}]},
  {"id":150,"name":"Sensitive Skin Shaving Gel","nameKn":"ಸೆನ್ಸಿಟಿವ್ ಸ್ಕಿನ್ ಶೇವಿಂಗ್ ಜೆಲ್","category":"shavecream","description":"Fragrance-free for sensitive skin.","image":"images\\\\Sensitive Skin Shaving Gel 99.jpg","weights":[{"label":"100g","price":200}]},
  {"id":151,"name":"After Shave Balm","nameKn":"ಆಫ್ಟರ್ ಶೇವ್ ಬಾಮ್","category":"aftershave","description":"Soothes and moisturizes post-shave.","image":"images\\\\After Shave Balm 395.jpg","weights":[{"label":"100ml","price":300}]},
  {"id":152,"name":"Herbal Toothpaste","nameKn":"ಹರ್ಬಲ್ ಟೂತ್ ಪೇಸ್ಟ್","category":"toothpaste","description":"Ayurvedic formula for oral health.","image":"images\\\\Toothpaste\\\\Herbal Toothpaste 158.jpg","weights":[{"label":"150g","price":110}]},
  {"id":153,"name":"Fresh Mint Mouthwash","nameKn":"ಫ್ರೆಶ್ ಮಿಂಟ್ ಮೌತ್ ವಾಶ್","category":"mouthwash","description":"Long-lasting minty freshness.","image":"images\\\\Fresh Mint Mouthwash 180.jpg","weights":[{"label":"300ml","price":220}]},
  {"id":154,"name":"Strawberry Lip Balm","nameKn":"ಸ್ಟ್ರಾಬೆರಿ ಲಿಪ್ ಬಾಮ್","category":"lipbalm","description":"Delicious strawberry flavor.","image":"images\\\\Strawberry Lip Balm 250.jpg","weights":[{"label":"10g","price":130}]},
  {"id":155,"name":"Foot Repair Cream","nameKn":"ಫುಟ್ ರಿಪೇರ್ ಕ್ರೀಮ್","category":"footcream","description":"Repairs dry and cracked feet.","image":"images\\\\Foot Repair Cream 350.jpg","weights":[{"label":"50g","price":220}]},
  {"id":156,"name":"Nourishing Hand Cream","nameKn":"ನೌರಿಶಿಂಗ್ ಹ್ಯಾಂಡ್ ಕ್ರೀಮ್","category":"handcream","description":"Keeps hands soft and smooth.","image":"images\\\\Nourishing Hand Cream 250.jpg","weights":[{"label":"50g","price":280}]},
  {"id":157,"name":"Round Hair Brush","nameKn":"ರೌಂಡ್ ಹೇರ್ ಬ್ರಷ್","category":"hairbrush","description":"For volumizing and styling.","image":"images\\\\Round Hair Brush 225.jpg","weights":[{"label":"1pc","price":200}]},
  {"id":158,"name":"Wide Tooth Comb","nameKn":"ವೈಡ್ ಟೂತ್ ಕಾಂಬ್","category":"comb","description":"Detangles wet hair gently.","image":"images\\\\Wide Tooth Comb.jpg","weights":[{"label":"1pc","price":60}]},
  {"id":159,"name":"Strong Hold Gel","nameKn":"ಸ್ಟ್ರಾಂಗ್ ಹೋಲ್ಡ್ ಜೆಲ್","category":"hairgel","description":"All-day strong hold styling.","image":"images\\\\Strong Hold Gel 620.jpg","weights":[{"label":"150ml","price":200}]},
  {"id":160,"name":"Matte Hair Wax","nameKn":"ಮ್ಯಾಟ್ ಹೇರ್ ವ್ಯಾಕ್ಸ್","category":"hairwax","description":"Natural matte finish with texture.","image":"images\\\\Matte Hair Wax 250.jpg","weights":[{"label":"100g","price":300}]},
  {"id":161,"name":"Clay Face Pack","nameKn":"ಕ್ಲೇ ಫೇಸ್ ಪ್ಯಾಕ್","category":"facepack","description":"Purifies and detoxifies skin.","image":"images\\\\Clay Face Pack 370.jpg","weights":[{"label":"100g","price":370}]},
  {"id":162,"name":"Charcoal Sheet Mask","nameKn":"ಚಾರ್ಕೋಲ್ ಶೀಟ್ ಮಾಸ್ಕ್","category":"mask","description":"Detoxifying charcoal face mask.","image":"images\\\\Charcoal Sheet Mask 150.jpg","weights":[{"label":"1pc","price":120}]},
  {"id":163,"name":"Under Eye Gel","nameKn":"ಅಂಡರ್ ಐ ಜೆಲ್","category":"eyecream","description":"Reduces puffiness and dark circles.","image":"images\\\\Under Eye Gel 240.jpg","weights":[{"label":"30g","price":450}]},
  {"id":164,"name":"Hydrating Toner","nameKn":"ಹೈಡ್ರೇಟಿಂಗ್ ಟೋನರ್","category":"toner","description":"Refreshes and balances skin.","image":"images\\\\Hydrating Toner 400.jpg","weights":[{"label":"150ml","price":300}]},
  {"id":165,"name":"Oil-Free Makeup Remover","nameKn":"ಆಯಿಲ್-ಫ್ರೀ ಮೇಕಪ್ ರಿಮೂವರ್","category":"makeupremover","description":"Removes makeup without greasy residue.","image":"images\\\\Oil-Free Makeup Remover 240.jpg","weights":[{"label":"200ml","price":350}]},
  {"id":166,"name":"Lavender Bath Salt","nameKn":"ಲ್ಯಾವೆಂಡರ್ ಬಾತ್ ಸಾಲ್ಟ್","category":"bathsalt","description":"Calming lavender aromatherapy.","image":"images\\\\Lavender Bath Salt 620.jpg","weights":[{"label":"200g","price":300}]},
  {"id":167,"name":"Relaxing Bath Oil","nameKn":"ರಿಲ್ಯಾಕ್ಸಿಂಗ್ ಬಾತ್ ಆಯಿಲ್","category":"bathoil","description":"Essential oils for relaxation.","image":"images\\\\Under Eye Gel 240.jpg","weights":[{"label":"100ml","price":350}]},
  {"id":168,"name":"Natural Loofah","nameKn":"ನ್ಯಾಚುರಲ್ ಲೂಫಾ","category":"loofah","description":"Biodegradable and eco-friendly.","image":"images\\\\Natural Loofah.jpg","weights":[{"label":"1pc","price":100}]},
  {"id":169,"name":"Premium Bath Sponge","nameKn":"ಪ್ರೀಮಿಯಂ ಬಾತ್ ಸ್ಪಾಂಜ್","category":"sponge","description":"Soft and durable bathing sponge.","image":"images\\\\Premium Bath Sponge.jpg","weights":[{"label":"1pc","price":150}]},
  {"id":170,"name":"Cotton Bath Towel","nameKn":"ಕಾಟನ್ ಬಾತ್ ಟವೆಲ್","category":"towel","description":"Premium quality cotton towel.","image":"images\\\\Cotton Bath Towel 350.jpg","weights":[{"label":"1pc","price":600}]},
  {"id":171,"name":"Microfiber Face Towel","nameKn":"ಮೈಕ್ರೋಫೈಬರ್ ಫೇಸ್ ಟವೆಲ್","category":"towel","description":"Gentle on facial skin.","image":"images\\\\Microfiber Face Towel.jpg","weights":[{"label":"1pc","price":200}]},
  {"id":172,"name":"Cotton Bath Robe","nameKn":"ಕಾಟನ್ ಬಾತ್ ರೋಬ್","category":"robe","description":"Soft and absorbent cotton robe.","image":"images\\\\Cotton Bath Robe.jpg","weights":[{"label":"1pc","price":1800}]},
  {"id":173,"name":"Anti-Slip Bath Slippers","nameKn":"ಆಂಟಿ-ಸ್ಲಿಪ್ ಬಾತ್ ಸ್ಲಿಪ್ಪರ್ಸ್","category":"slippers","description":"Safe grip on wet floors.","image":"images\\\\Anti-Slip Bath Slippers.jpg","weights":[{"label":"1pair","price":350}]},
  {"id":174,"name":"Reusable Shower Cap","nameKn":"ರಿಯೂಸಬಲ್ ಶವರ್ ಕ್ಯಾಪ್","category":"showercap","description":"Eco-friendly and washable.","image":"images\\\\Reusable Shower Cap.jpg","weights":[{"label":"1pc","price":80}]},
  {"id":175,"name":"Organic Cotton Balls","nameKn":"ಆರ್ಗಾನಿಕ್ ಕಾಟನ್ ಬಾಲ್ಸ್","category":"cotton","description":"100% organic cotton.","image":"images\\\\Organic Cotton Balls.jpg","weights":[{"label":"100g","price":120}]},
  {"id":176,"name":"Makeup Wipes","nameKn":"ಮೇಕಪ್ ವೈಪ್ಸ್","category":"wipes","description":"Gentle makeup removal wipes.","image":"images\\\\Makeup Wipes 320.jpg","weights":[{"label":"50pcs","price":180}]},
  {"id":179,"name":"Baby Gentle Wash","nameKn":"ಬೇಬಿ ಜೆಂಟಲ್ ವಾಶ್","category":"baby","description":"Tear-free and hypoallergenic.","image":"images\\\\Baby Gentle Wash 350.jpg","weights":[{"label":"200ml","price":300}]},
  {"id":180,"name":"Baby Moisturizing Lotion","nameKn":"ಬೇಬಿ ಮಾಯಿಶ್ಚರೈಜಿಂಗ್ ಲೋಷನ್","category":"baby","description":"Keeps baby skin soft and smooth.","image":"images\\\\Baby Moisturizing Lotion 900.jpg","weights":[{"label":"200ml","price":350}]},
  {"id":181,"name":"Baby Massage Oil","nameKn":"ಬೇಬಿ ಮಸಾಜ್ ಆಯಿಲ್","category":"baby","description":"Gentle oil for baby massage.","image":"images\\\\Baby Massage Oil 330.jpg","weights":[{"label":"200ml","price":260}]},
  {"id":182,"name":"Sensitive Baby Wipes","nameKn":"ಸೆನ್ಸಿಟಿವ್ ಬೇಬಿ ವೈಪ್ಸ್","category":"baby","description":"Alcohol and fragrance free.","image":"images\\\\Sensitive Baby Wipes 270.jpg","weights":[{"label":"80pcs","price":200}]},
  {"id":183,"name":"Ayurvedic Massage Oil","nameKn":"ಆಯುರ್ವೇದಿಕ್ ಮಸಾಜ್ ಆಯಿಲ್","category":"massageoil","description":"Traditional ayurvedic formula.","image":"images\\\\Ayurvedic Massage Oil 185.jpg","weights":[{"label":"200ml","price":300}]},
  {"id":184,"name":"Lavender Aroma Oil","nameKn":"ಲ್ಯಾವೆಂಡರ್ ಅರೋಮಾ ಆಯಿಲ್","category":"aromaoil","description":"Calming lavender scent for aromatherapy.","image":"images\\\\Lavender Aroma Oil 400.jpg","weights":[{"label":"50ml","price":350}]},
  {"id":185,"name":"Tea Tree Oil","nameKn":"ಟೀ ಟ್ರೀ ಆಯಿಲ್","category":"essentialoil","description":"Natural antiseptic properties.","image":"images\\\\Tea Tree Oil 350.jpg","weights":[{"label":"30ml","price":400}]},
  {"id":186,"name":"Herbal Foot Soak","nameKn":"ಹರ್ಬಲ್ ಫುಟ್ ಸೋಕ್","category":"footcare","description":"Refreshes tired feet with herbs.","image":"images\\\\Herbal Foot Soak.jpg","weights":[{"label":"200g","price":250}]},
  {"id":187,"name":"Electric Foot File","nameKn":"ಎಲೆಕ್ಟ್ರಿಕ್ ಫುಟ್ ಫೈಲ್","category":"footcare","description":"Automatic callus remover.","image":"images\\\\Baby Gentle Wash 350.jpg","weights":[{"label":"1pc","price":1200}]},
  {"id":188,"name":"Advanced Heel Repair Cream","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ಹೀಲ್ ರಿಪೇರ್ ಕ್ರೀಮ್","category":"footcream","description":"Intensive treatment for cracked heels.","image":"images\\\\Advanced Heel Repair Cream 350.jpg","weights":[{"label":"50g","price":250}]},
  {"id":189,"name":"Shea Body Butter","nameKn":"ಶಿಯಾ ಬಾಡಿ ಬಟರ್","category":"bodybutter","description":"Rich shea butter moisturization.","image":"images\\\\Shea Body Butter 1100.jpg","weights":[{"label":"200g","price":400}]},
  {"id":190,"name":"Vanilla Body Mist","nameKn":"ವ್ಯಾನಿಲ್ಲಾ ಬಾಡಿ ಮಿಸ್ಟ್","category":"perfume","description":"Light and sweet vanilla fragrance.","image":"images\\\\Vanilla Body Mist 350.jpg","weights":[{"label":"150ml","price":700}]},
  {"id":191,"name":"Elite Luxury Perfume","nameKn":"ಸಿಟ್ರಸ್ ಬಾಡಿ ಮಿಸ್ಟ್","category":"perfume","description":"Fresh and zesty citrus scent.","image":"images\\\\Elite Luxury Perfume.jpg","weights":[{"label":"150ml","price":650}]},
  {"id":192,"name":"Mint Foot Spray","nameKn":"ಮಿಂಟ್ ಫುಟ್ ಸ್ಪ್ರೇ","category":"footcare","description":"Refreshes and deodorizes feet.","image":"images\\\\Mint Foot Spray.jpg","weights":[{"label":"1 pc","price":430}]},
  {"id":193,"name":"Aloe Hand Sanitizer","nameKn":"ಅಲೋ ಹ್ಯಾಂಡ್ ಸ್ಯಾನಿಟೈಜರ್","category":"sanitizer","description":"Alcohol sanitizer with aloe for hydration.","image":"images\\\\Aloe Hand Sanitizer.jpg","weights":[{"label":"100ml","price":220}]},
  {"id":194,"name":"Rose Body Oil","nameKn":"ರೋಸ್ ಬಾಡಿ ಆಯಿಲ್","category":"bodyoil","description":"Infused with rose extracts for soft skin.","image":"images\\\\Rose Body Oil.jpg","weights":[{"label":"200ml","price":350}]},
  {"id":195,"name":"Organic Aloe Gel","nameKn":"ಆರ್ಗಾನಿಕ್ ಅಲೋ ಜೆಲ್","category":"aloe","description":"Pure and organic aloe vera.","image":"images\\\\Organic Aloe Gel 350.jpg","weights":[{"label":"200g","price":220}]},
  {"id":196,"name":"Pure Rose Water","nameKn":"ಪ್ಯೂರ್ ರೋಸ್ ವಾಟರ್","category":"toner","description":"Distilled from fresh roses.","image":"images\\\\Pure Rose Water 800.jpg","weights":[{"label":"200ml","price":180}]},
  {"id":197,"name":"Vegetable Glycerin","nameKn":"ವೆಜಿಟೇಬಲ್ ಗ್ಲಿಸರಿನ್","category":"moisturizer","description":"Plant-based moisturizing agent.","image":"images\\\\Vegetable Glycerin.jpg","weights":[{"label":"100ml","price":140}]},
  {"id":198,"name":"Petroleum Jelly Advanced","nameKn":"ಪೆಟ್ರೋಲಿಯಂ ಜೆಲ್ಲಿ ಅಡ್ವಾನ್ಸ್ಡ್","category":"moisturizer","description":"Enriched for better skin healing.","image":"images\\\\Petroleum Jelly Advanced 400.jpg","weights":[{"label":"100g","price":400}]},
  {"id":199,"name":"Sport Sunblock SPF50","nameKn":"ಸ್ಪೋರ್ಟ್ ಸನ್ ಬ್ಲಾಕ್ SPF50","category":"sunscreen","description":"Water-resistant for outdoor sports.","image":"images\\\\Sport Sunblock SPF50 500.jpg","weights":[{"label":"100ml","price":500}]},
  {"id":200,"name":"Cooling After Sun Gel","nameKn":"ಕೂಲಿಂಗ್ ಆಫ್ಟರ್ ಸನ್ ಜೆಲ್","category":"sunscreen","description":"Cools and hydrates sun-exposed skin.","image":"images\\\\Cooling After Sun Gel 200.jpg","weights":[{"label":"100ml","price":280}]},
  {"id":201,"name":"Hydra Body Foam","nameKn":"ಹೈಡ್ರಾ ಬಾಡಿ ಫೋಮ್","category":"bodywash","description":"Lightweight foam with hydrating properties for daily use.","image":"images\\\\Hydra Body Foam.jpg","weights":[{"label":"200ml","price":260}]},
  {"id":202,"name":"Charcoal Soap","nameKn":"ಚಾರ್ಕೋಲ್ ಸೋಪ್","category":"soap","description":"Detoxifying soap with activated charcoal for deep cleansing.","image":"images\\\\Charcoal Detox Soap 250.jpg","weights":[{"label":"100g","price":90}]},
  {"id":203,"name":"Milk Cream Soap","nameKn":"ಮಿಲ್ಕ್ ಕ್ರೀಮ್ ಸೋಪ್","category":"soap","description":"Nourishing soap with milk cream for soft skin.","image":"images\\\\Milk Cream Soap.jpg","weights":[{"label":"100g","price":80}]},
  {"id":204,"name":"Anti Hair Fall Shampoo","nameKn":"ಆಂಟಿ ಹೇರ್ ಫಾಲ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Reduces hair fall and strengthens roots.","image":"images\\\\Anti Hair Fall Shampoo 320.jpg","weights":[{"label":"200ml","price":320}]},
  {"id":205,"name":"Volume Boost Conditioner","nameKn":"ವಾಲ್ಯೂಮ್ ಬೂಸ್ಟ್ ಕಂಡೀಷನರ್","category":"conditioner","description":"Adds volume to fine hair.","image":"images\\\\Volume Boost Conditioner 400.jpg","weights":[{"label":"200ml","price":280}]},
  {"id":206,"name":"Hair Growth Tonic","nameKn":"ಹೇರ್ ಗ್ರೋತ್ ಟಾನಿಕ್","category":"hairtonic","description":"Promotes faster and thicker hair growth.","image":"images\\\\Hair Growth Tonic.jpg","weights":[{"label":"100ml","price":380}]},
  {"id":207,"name":"Hyaluronic Serum","nameKn":"ಹೈಲುರೋನಿಕ್ ಸೀರಮ್","category":"serum","description":"Intense hydration that plumps and smooths skin.","image":"images\\\\Hyaluronic Hair Serum.jpg","weights":[{"label":"30ml","price":950}]},
  {"id":208,"name":"Retinol Serum","nameKn":"ರೆಟಿನಾಲ್ ಸೀರಮ್","category":"serum","description":"Anti-aging serum that reduces fine lines and wrinkles.","image":"images\\\\Retinol Serum.jpg","weights":[{"label":"30ml","price":1100}]},
  {"id":209,"name":"Argan Face Oil","nameKn":"ಅರ್ಗಾನ್ ಫೇಸ್ ಆಯಿಲ್","category":"faceoil","description":"Rich in vitamin E and antioxidants for glowing skin.","image":"images\\\\Argan Face Oil.jpg","weights":[{"label":"30ml","price":800}]},
  {"id":210,"name":"Nourishing Body Oil","nameKn":"ನೌರಿಶಿಂಗ್ ಬಾಡಿ ಆಯಿಲ್","category":"bodyoil","description":"Deeply moisturizes and nourishes skin.","image":"images\\\\Nourishing Body Oil.jpg","weights":[{"label":"200ml","price":350}]},
  {"id":211,"name":"Herbal Hair Color","nameKn":"ಹರ್ಬಲ್ ಹೇರ್ ಕಲರ್","category":"haircolor","description":"Ammonia-free natural hair coloring kit.","image":"images\\\\hair color\\\\Inuds Valley Herbal Hair Gel Color Singal Pack 600.jpg","weights":[{"label":"1kit","price":300}]},
  {"id":212,"name":"Advanced Bleach Kit","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ಬ್ಲೀಚ್ ಕಿಟ್","category":"bleach","description":"Gentle bleach for facial and body hair.","image":"images\\\\Advanced Bleach Kit.jpg","weights":[{"label":"1set","price":250}]},
  {"id":213,"name":"Cold Wax Strips","nameKn":"ಕೋಲ್ಡ್ ವ್ಯಾಕ್ಸ್ ಸ್ಟ್ರಿಪ್ಸ್","category":"waxing","description":"Easy-to-use cold wax strips for quick hair removal.","image":"images\\\\Cold Wax Strips.jpg","weights":[{"label":"20pcs","price":300}]},
  {"id":214,"name":"Electric Epilator","nameKn":"ಎಲೆಕ್ಟ್ರಿಕ್ ಎಪಿಲೇಟರ್","category":"epilator","description":"Removes hair from the root for long-lasting smoothness.","image":"images\\\\Electric Epilator.jpg","weights":[{"label":"1pc","price":1500}]},
  {"id":215,"name":"Soothing After Wax Gel","nameKn":"ಸೂಥಿಂಗ್ ಆಫ್ಟರ್ ವಾಕ್ಸ್ ಜೆಲ್","category":"afterwax","description":"Calms and soothes skin after waxing.","image":"images\\\\Soothing After Wax Gel.jpg","weights":[{"label":"100ml","price":250}]},
  {"id":216,"name":"Natural Deodorant Stick","nameKn":"ನ್ಯಾಚುರಲ್ ಡಿಯೋಡರೆಂಟ್ ಸ್ಟಿಕ್","category":"deo","description":"Aluminum-free deodorant with natural ingredients.","image":"images\\\\Natural Deodorant Stick.jpg","weights":[{"label":"50g","price":280}]},
  {"id":217,"name":"Refreshing Body Spray","nameKn":"ರಿಫ್ರೆಶಿಂಗ್ ಬಾಡಿ ಸ್ಪ್ರೇ","category":"perfume","description":"Light and invigorating body fragrance.","image":"images\\\\perfume9.jpg","weights":[{"label":"150ml","price":600}]},
  {"id":218,"name":"Moisture Lock Body Wash","nameKn":"ಮಾಯಿಶ್ಚರ್ ಲಾಕ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Locks in moisture for 24 hours.","image":"images\\\\Moisture Lock Body Wash.jpg","weights":[{"label":"300ml","price":300}]},
  {"id":219,"name":"Ayurvedic Body Wash","nameKn":"ಆಯುರ್ವೇದಿಕ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Traditional Ayurvedic cleansing formula.","image":"images\\\\Ayurvedic Body Wash 250.jpg","weights":[{"label":"250ml","price":280}]},
  {"id":220,"name":"Organic Handmade Soap","nameKn":"ಆರ್ಗಾನಿಕ್ ಹ್ಯಾಂಡ್ ಮೇಡ್ ಸೋಪ್","category":"soap","description":"Small-batch artisan soap with natural ingredients.","image":"images\\\\Organic Handmade Soap.jpg","weights":[{"label":"125g","price":150}]},
  {"id":221,"name":"Signature Perfume","nameKn":"ಸಿಗ್ನೇಚರ್ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Unique signature scent for all occasions.","image":"images\\\\Signature Perfume.jpg","weights":[{"label":"100ml","price":2500}]},
  {"id":222,"name":"Long Lasting Deodorant","nameKn":"ಲಾಂಗ್ ಲಾಸ್ಟಿಂಗ್ ಡಿಯೋಡರೆಂಟ್","category":"deo","description":"48-hour protection against odor.","image":"images\\\\Long Lasting Deodorant.jpg","weights":[{"label":"150ml","price":320}]},
  {"id":223,"name":"Ultra Hydrating Lotion","nameKn":"ಅಲ್ಟ್ರಾ ಹೈಡ್ರೇಟಿಂಗ್ ಲೋಷನ್","category":"lotion","description":"Deep hydration for extremely dry skin.","image":"images\\\\Ultra Hydrating Lotion  650.jpg","weights":[{"label":"300ml","price":680}]},
  {"id":224,"name":"Repair Night Cream","nameKn":"ರಿಪೇರ್ ನೈಟ್ ಕ್ರೀಮ್","category":"cream","description":"Overnight repair for damaged skin.","image":"images\\\\Repair Night Cream.jpg","weights":[{"label":"100g","price":550}]},
  {"id":225,"name":"Alcohol-Free Toner","nameKn":"ಆಲ್ಕೊಹಾಲ್-ಫ್ರೀ ಟೋನರ್","category":"toner","description":"Gentle toner that doesnt strip natural oils.","image":"images\\\\Alcohol-Free Toner.jpg","weights":[{"label":"150ml","price":320}]},
  {"id":226,"name":"Micellar Water","nameKn":"ಮಿಸೆಲ್ಲಾರ್ ವಾಟರ್","category":"makeupremover","description":"All-in-one cleanser and makeup remover.","image":"images\\\\Micellar Water.jpg","weights":[{"label":"200ml","price":400}]},
  {"id":227,"name":"Detox Bath Salt","nameKn":"ಡಿಟಾಕ್ಸ್ ಬಾತ್ ಸಾಲ್ಟ್","category":"bathsalt","description":"Removes toxins and relaxes muscles.","image":"images\\\\Detox Bath Salt.jpg","weights":[{"label":"200g","price":320}]},
  {"id":228,"name":"Eucalyptus Bath Oil","nameKn":"ಯೂಕಲಿಪ್ಟಸ್ ಬಾತ್ ಆಯಿಲ್","category":"bathoil","description":"Clears sinuses and refreshes body.","image":"images\\\\Eucalyptus Bath Oil.jpg","weights":[{"label":"100ml","price":1780}]},
  {"id":229,"name":"Exfoliating Loofah","nameKn":"ಎಕ್ಸ್ಫೋಲಿಯೇಟಿಂಗ್ ಲೂಫಾ","category":"loofah","description":"Removes dead skin cells effectively.","image":"images\\\\Exfoliating Loofah.jpg","weights":[{"label":"1pc","price":120}]},
  {"id":230,"name":"Soft Bath Sponge","nameKn":"ಸಾಫ್ಟ್ ಬಾತ್ ಸ್ಪಂಜ್","category":"sponge","description":"Gentle exfoliation for sensitive skin.","image":"images\\\\Soft Bath Sponge.jpg","weights":[{"label":"1pc","price":130}]},
  {"id":235,"name":"Premium Cotton Pads","nameKn":"ಪ್ರೀಮಿಯಂ ಕಾಟನ್ ಪ್ಯಾಡ್ಸ್","category":"cotton","description":"Lint-free for toner and makeup removal.","image":"images\\\\Premium Cotton Pads.jpg","weights":[{"label":"100pcs","price":150}]},
  {"id":236,"name":"Facial Cleansing Wipes","nameKn":"ಫೇಶಿಯಲ್ ಕ್ಲೆನ್ಸಿಂಗ್ ವೈಪ್ಸ್","category":"wipes","description":"Quick and easy daily cleansing.","image":"images\\\\Facial Cleansing Wipes.jpg","weights":[{"label":"50pcs","price":200}]},
  {"id":237,"name":"Relaxing Body Massage Oil","nameKn":"ರಿಲ್ಯಾಕ್ಸಿಂಗ್ ಬಾಡಿ ಮಸಾಜ್ ಆಯಿಲ್","category":"massageoil","description":"Blend of relaxing essential oils.","image":"images\\\\Relaxing Body Massage Oil.jpg","weights":[{"label":"200ml","price":320}]},
  {"id":238,"name":"Rose Aroma Oil","nameKn":"ರೋಸ್ ಅರೋಮಾ ಆಯಿಲ್","category":"aromaoil","description":"Romantic and uplifting rose scent.","image":"images\\\\Rose Aroma Oil.jpg","weights":[{"label":"50ml","price":380}]},
  {"id":239,"name":"Peppermint Oil","nameKn":"ಪೆಪ್ಪರ್ ಮಿಂಟ್ ಆಯಿಲ್","category":"essentialoil","description":"Invigorating and cooling essential oil.","image":"images\\\\Peppermint Oil.jpg","weights":[{"label":"30ml","price":420}]},
  {"id":240,"name":"Mint Foot Soak","nameKn":"ಮಿಂಟ್ ಫುಟ್ ಸೋಕ್","category":"footcare","description":"Revitalizes tired feet with mint.","image":"images\\\\Mint Foot Soak.jpg","weights":[{"label":"200g","price":260}]},
  {"id":241,"name":"Professional Foot File","nameKn":"ಪ್ರೊಫೆಷನಲ್ ಫುಟ್ ಫೈಲ್","category":"footcare","description":"Removes calluses and smooths feet.","image":"images\\\\Professional Foot File.jpg","weights":[{"label":"1pc","price":200}]},
  {"id":242,"name":"Intensive Heel Repair Cream","nameKn":"ಇಂಟೆನ್ಸ್ ಹೀಲ್ ರಿಪೇರ್ ಕ್ರೀಮ್","category":"footcream","description":"Repairs and softens cracked heels.","image":"images\\\\Intensive Heel Repair Cream.jpg","weights":[{"label":"50g","price":280}]},
  {"id":243,"name":"Shea Body Butter","nameKn":"ಶಿಯಾ ಬಾಡಿ ಬಟರ್","category":"bodybutter","description":"Deeply moisturizing shea butter.","image":"images\\\\Shea Body Butter 1100.jpg","weights":[{"label":"200g","price":450}]},
  {"id":244,"name":"Vanilla Body Mist","nameKn":"ವ್ಯಾನಿಲ್ಲಾ ಬಾಡಿ ಮಿಸ್ಟ್","category":"perfume","description":"Sweet and comforting vanilla scent.","image":"images\\\\Vanilla Body Mist 350.jpg","weights":[{"label":"150ml","price":750}]},
  {"id":245,"name":"Citrus Body Mist","nameKn":"ಸಿಟ್ರಸ್ ಬಾಡಿ ಮಿಸ್ಟ್","category":"perfume","description":"Fresh and energizing citrus fragrance.","image":"images\\\\Body Mist Citrus 680.jpg","weights":[{"label":"150ml","price":700}]},
  {"id":246,"name":"Aloe Cooling Gel","nameKn":"ಅಲೋ ಕೂಲಿಂಗ್ ಜೆಲ್","category":"aloe","description":"Soothes sunburns and irritated skin.","image":"images\\\\Aloe Cooling Gel.jpg","weights":[{"label":"200g","price":240}]},
  {"id":247,"name":"Organic Rose Water","nameKn":"ಆರ್ಗಾನಿಕ್ ರೋಸ್ ವಾಟರ್","category":"toner","description":"Certified organic rose water.","image":"images\\\\Organic Rose Water.jpg","weights":[{"label":"200ml","price":200}]},
  {"id":248,"name":"Pure Glycerin","nameKn":"ಪ್ಯೂರ್ ಗ್ಲಿಸರಿನ್","category":"moisturizer","description":"Humectant that draws moisture to skin.","image":"images\\\\Glycerin 400.jpg","weights":[{"label":"100ml","price":150}]},
  {"id":249,"name":"Healing Jelly","nameKn":"ಹೀಲಿಂಗ್ ಜೆಲ್ಲಿ","category":"moisturizer","description":"Multi-purpose healing ointment.","image":"images\\\\Healing Jelly.jpg","weights":[{"label":"1 pc","price":880}]},
  {"id":250,"name":"Ultra Gentle Foam Cleanser","nameKn":"ಅಲ್ಟ್ರಾ ಜೆಂಟಲ್ ಫೋಮ್ ಕ್ಲೆನ್ಸರ್","category":"bodywash","description":"pH balanced for sensitive skin.","image":"images\\\\Ultra Gentle Foam Cleanser.jpg","weights":[{"label":"200ml","price":300}]},
  {"id":251,"name":"Hydrating Milk Body Wash","nameKn":"ಹೈಡ್ರೇಟಿಂಗ್ ಮಿಲ್ಕ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Milk protein enriched body wash.","image":"images\\\\Hydrating Milk Body Wash 550.jpg","weights":[{"label":"300ml","price":550}]},
  {"id":252,"name":"Neem Purifying Body Wash","nameKn":"ನೀಮ್ ಪ್ಯೂರಿಫೈಯಿಂಗ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Antibacterial neem body wash.","image":"images\\\\Neem Purifying Body Wash 350.jpg","weights":[{"label":"250ml","price":290}]},
  {"id":253,"name":"Ayurvedic Herbal Soap","nameKn":"ಆಯುರ್ವೇದಿಕ್ ಹರ್ಬಲ್ ಸೋಪ್","category":"soap","description":"Traditional 12-herb formula.","image":"images\\\\Ayurvedic Herbal Soap 170.jpg","weights":[{"label":"125g","price":170}]},
  {"id":254,"name":"Olive Oil Soap","nameKn":"ಆಲಿವ್ ಆಯಿಲ್ ಸೋಪ್","category":"soap","description":"Castile-style olive oil soap.","image":"images\\\\Olive Oil Soap 120.jpg","weights":[{"label":"100g","price":130}]},
  {"id":255,"name":"Shea Butter Soap","nameKn":"ಶಿಯಾ ಬಟರ್ ಸೋಪ್","category":"soap","description":"Moisturizing shea butter soap.","image":"images\\\\Shea Butter Soap 300.jpg","weights":[{"label":"125g","price":300}]},
  {"id":256,"name":"Herbal Anti Hair Fall Shampoo","nameKn":"ಹರ್ಬಲ್ ಆಂಟಿ ಹೇರ್ ಫಾಲ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Natural herbs to reduce hair fall.","image":"images\\\\Herbal Anti Hair Fall Shampoo 220.jpg","weights":[{"label":"200ml","price":220}]},
  {"id":257,"name":"Smoothening Shampoo","nameKn":"ಸ್ಮೂತನಿಂಗ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Tames frizz for silky smooth hair.","image":"images\\\\Smoothening Shampoo 1040.jpg","weights":[{"label":"340ml","price":1040}]},
  {"id":258,"name":"Intense Repair Conditioner","nameKn":"ಇಂಟೆನ್ಸ್ ರಿಪೇರ್ ಕಂಡೀಷನರು","category":"conditioner","description":"Deep conditioning for damaged hair.","image":"images\\\\Intense Repair Conditioner 385.jpg","weights":[{"label":"200ml","price":300}]},
  {"id":259,"name":"Silk Shine Conditioner","nameKn":"ಸಿಲ್ಕ್ ಶೈನ್ ಕಂಡೀಷನರ್","category":"conditioner","description":"Adds brilliant shine to dull hair.","image":"images\\\\Silk Shine Conditioner 450.jpg","weights":[{"label":"250ml","price":330}]},
  {"id":260,"name":"Bhringraj Hair Oil","nameKn":"ಭೃಂಗರಾಜ್ ಹೇರ್ ಆಯಿಲ್","category":"hairoil","description":"Ayurvedic oil for hair growth.","image":"images\\\\Bhringraj Hair Oil 560.jpg","weights":[{"label":"200ml","price":260}]},
  {"id":261,"name":"Castor Hair Oil","nameKn":"ಕ್ಯಾಸ್ಟರ್ ಹೇರ್ ಆಯಿಲ್","category":"hairoil","description":"Thickens and strengthens hair.","image":"images\\\\Castor Hair Oil 240.jpg","weights":[{"label":"150ml","price":220}]},
  {"id":262,"name":"Keratin Smooth Serum","nameKn":"ಕೆರಾಟಿನ್ ಸ್ಮೂತ್ ಸೀರಮ್","category":"hairserum","description":"Smooths hair with keratin protein.","image":"images\\\\Keratin Smooth Serum 210.jpg","weights":[{"label":"100ml","price":400}]},
  {"id":263,"name":"Gloss Hair Serum","nameKn":"ಗ್ಲಾಸ್ ಹೇರ್ ಸೀರಮ್","category":"hairserum","description":"Adds high-gloss shine to hair.","image":"images\\\\Gloss Hair Serum.jpg","weights":[{"label":"100ml","price":380}]},
  {"id":264,"name":"Tea Tree Face Wash","nameKn":"ಟೀ ಟ್ರೀ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Antibacterial face wash for acne.","image":"images\\\\Tea Tree Face Wash 350.jpg","weights":[{"label":"150ml","price":230}]},
  {"id":265,"name":"Deep Clean Face Wash","nameKn":"ಡೀಪ್ ಕ್ಲೀನ್ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Removes deep-seated dirt and oil.","image":"images\\\\Deep Clean Face Wash 170.jpg","weights":[{"label":"150ml","price":240}]},
  {"id":266,"name":"Walnut Face Scrub","nameKn":"ವಾಲ್ನಟ್ ಫೇಸ್ ಸ್ಕ್ರಬ್","category":"scrub","description":"Exfoliates with natural walnut granules.","image":"images\\\\Walnut Face Scrub 280.jpg","weights":[{"label":"100g","price":240}]},
  {"id":267,"name":"Apricot Body Scrub","nameKn":"ಏಪ್ರಿಕಾಟ್ ಬಾಡಿ ಸ್ಕ್ರಬ್","category":"scrub","description":"Gentle exfoliation with apricot seeds.","image":"images\\\\Apricot Body Scrub.jpg","weights":[{"label":"200g","price":320}]},
  {"id":268,"name":"Wrinkle Repair Cream","nameKn":"ರಿಂಕಲ್ ರಿಪೇರ್ ಕ್ರೀಮ್","category":"cream","description":"Reduces appearance of fine lines.","image":"images\\\\Wrinkle Repair Cream 2999.jpg","weights":[{"label":"50g","price":650}]},
  {"id":269,"name":"Hydra Glow Cream","nameKn":"ಹೈಡ್ರಾ ಗ್ಲೋ ಕ್ರೀಮ್","category":"cream","description":"Hydrates and brightens skin.","image":"images\\\\Hydra Glow Cream.jpg","weights":[{"label":"100g","price":480}]},
  {"id":270,"name":"Vitamin E Body Lotion","nameKn":"ವಿಟಮಿನ್ ಇ ಬಾಡಿ ಲೋಷನ್","category":"lotion","description":"Antioxidant-rich body lotion.","image":"images\\\\Vitamin E Body Lotion.jpg","weights":[{"label":"300ml","price":360}]},
  {"id":271,"name":"Ultra Moist Body Lotion","nameKn":"ಅಲ್ಟ್ರಾ ಮಾಯಿಸ್ಟ್ ಬಾಡಿ ಲೋಷನ್","category":"lotion","description":"24-hour moisture for dry skin.","image":"images\\\\Ultra Moist Body Lotion.jpg","weights":[{"label":"300ml","price":390}]},
  {"id":272,"name":"Matte Finish Sunscreen SPF50","nameKn":"ಮ್ಯಾಟ್ ಫಿನಿಶ್ ಸನ್ ಸ್ಕ್ರೀನ್ SPF50","category":"sunscreen","description":"Non-greasy matte finish sunscreen.","image":"images\\\\Matte Finish Sunscreen SPF50 750.jpg","weights":[{"label":"100ml","price":550}]},
  {"id":273,"name":"Fresh Active Deodorant","nameKn":"ಫ್ರೆಶ್ ಆಕ್ಟಿವ್ ಡಿಯೋಡರೆಂಟ್","category":"deo","description":"Keeps you fresh all day.","image":"images\\\\Fresh Active Deodorant.jpg","weights":[{"label":"150ml","price":300}]},
  {"id":274,"name":"Elite Perfume","nameKn":"ಎಲೈಟ್ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Premium luxury fragrance.","image":"images\\\\Elite Perfume.jpg","weights":[{"label":"100ml","price":2800}]},
  {"id":275,"name":"Woody Musk Perfume","nameKn":"ವುಡಿ ಮಸ್ಕ್ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Warm and sensual woody scent.","image":"images\\\\Woody Musk Perfume.jpg","weights":[{"label":"100ml","price":3000}]},
  {"id":276,"name":"Herbal Talc Powder","nameKn":"ಹರ್ಬಲ್ ಟಾಲ್ಕಮ್ ಪೌಡರ್","category":"talc","description":"Ayurvedic talcum powder.","image":"images\\\\Herbal Talc Powder.jpg","weights":[{"label":"200g","price":160}]},
  {"id":277,"name":"Moisturizing Hand Wash","nameKn":"ಮಾಯಿಶ್ಚರೈಜಿಂಗ್ ಹ್ಯಾಂಡ್ ವಾಶ್","category":"handwash","description":"Cleans without drying hands.","image":"images\\\\Moisturizing Hand Wash.jpg","weights":[{"label":"250ml","price":120}]},
  {"id":278,"name":"Herbal Sanitizer Gel","nameKn":"ಹರ್ಬಲ್ ಸ್ಯಾನಿಟೈಜರ್ ಜೆಲ್","category":"sanitizer","description":"Natural ingredient sanitizer.","image":"images\\\\Herbal Sanitizer Gel.jpg","weights":[{"label":"100ml","price":100}]},
  {"id":279,"name":"Cooling Shaving Gel","nameKn":"ಕೂಲಿಂಗ್ ಶೇವಿಂಗ್ ಜೆಲ್","category":"shavecream","description":"Cooling sensation for smoother shave.","image":"images\\\\Cooling Shaving Gel.jpg","weights":[{"label":"100g","price":220}]},
  {"id":280,"name":"Refreshing After Shave","nameKn":"ರಿಫ್ರೆಶಿಂಗ್ ಆಫ್ಟರ್ ಶೇವ್","category":"aftershave","description":"Alcohol-free soothing after shave.","image":"images\\\\Refreshing After Shave.jpg","weights":[{"label":"100ml","price":320}]},
  {"id":281,"name":"Whitening Toothpaste","nameKn":"ವೈಟನಿಂಗ್ ಟೂತ್ ಪೇಸ್ಟ್","category":"toothpaste","description":"Removes surface stains for whiter teeth.","image":"images\\\\Whitening Toothpaste.jpg","weights":[{"label":"150g","price":130}]},
  {"id":282,"name":"Herbal Mouthwash","nameKn":"ಹರ್ಬಲ್ ಮೌತ್ ವಾಶ್","category":"mouthwash","description":"Natural herb-infused mouthwash.","image":"images\\\\Herbal Mouthwash.jpg","weights":[{"label":"300ml","price":240}]},
  {"id":283,"name":"Chocolate Lip Balm","nameKn":"ಚಾಕೊಲೇಟ್ ಲಿಪ್ ಬಾಮ್","category":"lipbalm","description":"Yummy chocolate flavored lip balm.","image":"images\\\\Chocolate Lip Balm.jpg","weights":[{"label":"10g","price":140}]},
  {"id":284,"name":"Moist Foot Cream","nameKn":"ಮಾಯಿಸ್ಟ್ ಫುಟ್ ಕ್ರೀಮ್","category":"footcream","description":"Deep moisturization for feet.","image":"images\\\\Moist Foot Cream.jpg","weights":[{"label":"50g","price":230}]},
  {"id":285,"name":"Soft Touch Hand Cream","nameKn":"ಸಾಫ್ಟ್ ಟಚ್ ಹ್ಯಾಂಡ್ ಕ್ರೀಮ್","category":"handcream","description":"Leaves hands silky smooth.","image":"images\\\\Soft Touch Hand Cream.jpg","weights":[{"label":"50g","price":200}]},
  {"id":286,"name":"Paddle Hair Brush","nameKn":"ಪ್ಯಾಡಲ್ ಹೇರ್ ಬ್ರಷ್","category":"hairbrush","description":"Large paddle brush for detangling.","image":"images\\\\Paddle Hair Brush.jpg","weights":[{"label":"1pc","price":220}]},
  {"id":287,"name":"Carbon Comb","nameKn":"ಕಾರ್ಬನ್ ಕಂಬ್","category":"comb","description":"Anti-static carbon fiber comb.","image":"images\\\\Carbon Comb.jpg","weights":[{"label":"1pc","price":80}]},
  {"id":288,"name":"Ultra Hold Gel","nameKn":"ಅಲ್ಟ್ರಾ ಹೋಲ್ಡ್ ಜೆಲ್","category":"hairgel","description":"Maximum hold for all-day styles.","image":"images\\\\Ultra Hold Gel.jpg","weights":[{"label":"150ml","price":220}]},
  {"id":289,"name":"Shine Hair Wax","nameKn":"ಶೈನ್ ಹೇರ್ ವ್ಯಾಕ್ಸ್","category":"hairwax","description":"Adds shine with medium hold.","image":"images\\\\Shine Hair Wax.jpg","weights":[{"label":"100g","price":320}]},
  {"id":290,"name":"Multani Mitti Pack","nameKn":"ಮುಲ್ತಾನಿ ಮಿಟ್ಟಿ ಪ್ಯಾಕ್","category":"facepack","description":"Traditional clay for oil control.","image":"images\\\\Multani Mitti Pack.jpg","weights":[{"label":"100g","price":180}]},
  {"id":291,"name":"Vitamin Sheet Mask","nameKn":"ವಿಟಮಿನ್ ಶೀಟ್ ಮಾಸ್ಕ್","category":"mask","description":"Vitamin-infused single-use mask.","image":"images\\\\Vitamin Sheet Mask.jpg","weights":[{"label":"1pc","price":150}]},
  {"id":292,"name":"Anti Dark Circle Cream","nameKn":"ಆಂಟಿ ಡಾರ್ಕ್ ಸರ್ಕಲ್ ಕ್ರೀಮ್","category":"eyecream","description":"Reduces dark circles and puffiness.","image":"images\\\\Anti Dark Circle Cream.jpg","weights":[{"label":"30g","price":500}]},
  {"id":293,"name":"Green Tea Toner","nameKn":"ಗ್ರೀನ್ ಟೀ ಟೋನರ್","category":"toner","description":"Antioxidant toner for glowing skin.","image":"images\\\\Green Tea Toner.jpg","weights":[{"label":"150ml","price":320}]},
  {"id":294,"name":"Herbal Makeup Remover","nameKn":"ಹರ್ಬಲ್ ಮೇಕಪ್ ರಿಮೂವರ್","category":"makeupremover","description":"Gentle herbal formula.","image":"images\\\\Herbal Makeup Remover.jpg","weights":[{"label":"200ml","price":360}]},
  {"id":295,"name":"Detox Herbal Bath Salt","nameKn":"ಡಿಟಾಕ್ಸ್ ಹರ್ಬಲ್ ಬಾತ್ ಸಾಲ್ಟ್","category":"bathsalt","description":"Herbal detox bath soak.","image":"images\\\\Detox Herbal Bath Salt 950.jpg","weights":[{"label":"200g","price":350}]},
  {"id":296,"name":"Calming Bath Oil","nameKn":"ಕ್ಯಾಲ್ಮಿಂಗ್ ಬಾತ್ ಆಯಿಲ್","category":"bathoil","description":"Promotes relaxation and calm.","image":"images\\\\Calming Bath Oil.jpg","weights":[{"label":"100ml","price":400}]},
  {"id":297,"name":"Organic Bath Loofah","nameKn":"ಆರ್ಗಾನಿಕ್ ಬಾತ್ ಲೂಫಾ","category":"loofah","description":"Natural and biodegradable loofah.","image":"images\\\\Organic Bath Loofah.jpg","weights":[{"label":"1pc","price":140}]},
  {"id":298,"name":"Soft Foam Sponge","nameKn":"ಸಾಫ್ಟ್ ಫೋಮ್ ಸ್ಪಾಂಜ್","category":"sponge","description":"Extra soft for sensitive skin.","image":"images\\\\Soft Foam Sponge.jpg","weights":[{"label":"1pc","price":160}]},
  {"id":299,"name":"Premium Cotton Towel","nameKn":"ಪ್ರೀಮಿಯಂ ಕಾಟನ್ ಟವೆಲ್","category":"towel","description":"Luxury 700 GSM cotton towel.","image":"images\\\\Premium Cotton Towel.jpg","weights":[{"label":"1pc","price":900}]},
  {"id":300,"name":"Soft Face Towel","nameKn":"ಸಾಫ್ಟ್ ಫೇಸ್ ಟವೆಲ್","category":"towel","description":"Gentle on facial skin.","image":"images\\\\Premium Cotton Towel.jpg","weights":[{"label":"1pc","price":250}]},
  {"id":301,"name":"Luxury Cotton Robe","nameKn":"ಲಕ್ಷ್ಮಿ ಕಾಟನ್ ರೋಬ್","category":"robe","description":"Premium quality cotton bathrobe with soft lining.","image":"images\\\\Luxury Cotton Robe.jpg","weights":[{"label":"1pc","price":2500}]},
  {"id":302,"name":"Soft Bath Slippers","nameKn":"ಸಾಫ್ಟ್ ಬಾತ್ ಸ್ಲಿಪ್ಪರ್ಸ್","category":"slippers","description":"Plush memory foam bath slippers.","image":"images\\\\Soft Bath Slippers.jpg","weights":[{"label":"1pair","price":450}]},
  {"id":303,"name":"Elastic Shower Cap","nameKn":"ಎಲಾಸ್ಟಿಕ್ ಶವರ್ ಕ್ಯಾಪ್","category":"showercap","description":"Stretchy and comfortable shower cap.","image":"images\\\\Elastic Shower Cap.jpg","weights":[{"label":"1pc","price":140}]},
  {"id":304,"name":"Soft Cotton Pads","nameKn":"ಸಾಫ್ಟ್ ಕಾಟನ್ ಪ್ಯಾಡ್ಸ್","category":"cotton","description":"Dual-textured cotton pads for cleansing.","image":"images\\\\Soft Cotton Pads.jpg","weights":[{"label":"100pcs","price":160}]},
  {"id":305,"name":"Aloe Vera Wipes","nameKn":"ಅಲೋವೆರಾ ವೈಪ್ಸ್","category":"wipes","description":"Aloe-infused facial cleansing wipes.","image":"images\\\\Little\'s Soft Cleansing Baby Wipes with Aloe Vera, Jojoba Oil and Vitamin E, Lid Pack 223.jpg","weights":[{"label":"50pcs","price":220}]},
  {"id":306,"name":"Antibacterial Ointment","nameKn":"ಆಂಟಿ ಬ್ಯಾಕ್ಟೀರಿಯಲ್ ಆಯಿಂಟ್ಮೆಂಟ್","category":"firstaid","description":"Prevents infection in minor cuts and wounds.","image":"images\\\\Antibacterial Ointment.jpg","weights":[{"label":"50g","price":240}]},
  {"id":307,"name":"Gentle Baby Soap","nameKn":"ಜೆಂಟಲ್ ಬೇಬಿ ಸೋಪ್","category":"baby","description":"Mild and moisturizing baby soap.","image":"images\\\\Gentle Baby Soap.jpg","weights":[{"label":"100g","price":140}]},
  {"id":308,"name":"Tear-Free Baby Wash","nameKn":"ಟಿಯರ್-ಫ್ರೀ ಬೇಬಿ ವಾಶ್","category":"baby","description":"No more tears gentle baby wash.","image":"images\\\\Tear-Free Baby Wash.jpg","weights":[{"label":"200ml","price":320}]},
  {"id":309,"name":"Soft Baby Lotion","nameKn":"ಸಾಫ್ಟ್ ಬೇಬಿ ಲೋಷನ್","category":"baby","description":"Keeps baby\'s skin soft and protected.","image":"images\\\\Soft Baby Lotion.jpg","weights":[{"label":"200ml","price":380}]},
  {"id":310,"name":"Herbal Baby Oil","nameKn":"ಹರ್ಬಲ್ ಬೇಬಿ ಆಯಿಲ್","category":"baby","description":"Natural herbal oil for baby massage.","image":"images\\\\Herbal Baby Oil.jpg","weights":[{"label":"200ml","price":280}]},
  {"id":311,"name":"Organic Baby Wipes","nameKn":"ಆರ್ಗಾನಿಕ್ ಬೇಬಿ ವೈಪ್ಸ್","category":"baby","description":"Chemical-free organic baby wipes.","image":"images\\\\Organic Baby Wipes.jpg","weights":[{"label":"80pcs","price":220}]},
  {"id":312,"name":"Herbal Massage Oil","nameKn":"ಹರ್ಬಲ್ ಮಸಾಜ್ ಆಯಿಲ್","category":"massageoil","description":"Traditional herbal blend for massage.","image":"images\\\\Herbal Massage Oil.jpg","weights":[{"label":"200ml","price":320}]},
  {"id":313,"name":"Sandal Aroma Oil","nameKn":"ಸ್ಯಾಂಡಲ್ ಅರೋಮಾ ಆಯಿಲ್","category":"aromaoil","description":"Calming sandalwood aroma oil.","image":"images\\\\Sandal Aroma Oil.jpg","weights":[{"label":"50ml","price":420}]},
  {"id":314,"name":"Lavender Essential Oil","nameKn":"ಲ್ಯಾವೆಂಡರ್ ಎಸೆನ್ಷಿಯಲ್ ಆಯಿಲ್","category":"essentialoil","description":"Pure lavender oil for relaxation.","image":"images\\\\Lavender Essential Oil.jpg","weights":[{"label":"30ml","price":450}]},
  {"id":315,"name":"Detox Foot Soak","nameKn":"ಡಿಟಾಕ್ಸ್ ಫುಟ್ ಸೋಕ್","category":"footcare","description":"Removes toxins through feet.","image":"images\\\\Detox Foot Soak.jpg","weights":[{"label":"200g","price":280}]},
  {"id":316,"name":"Rechargeable Foot File","nameKn":"ರೀಚಾರ್ಜ್ ಮಾಡಬಲ್ಲ ಫುಟ್ ಫೈಲ್","category":"footcare","description":"Electric callus remover with LED.","image":"images\\\\Rechargeable Foot File.jpg","weights":[{"label":"1pc","price":1500}]},
  {"id":317,"name":"Almond Body Butter","nameKn":"ಆಲ್ಮಂಡ್ ಬಾಡಿ ಬಟರ್","category":"bodybutter","description":"Nourishing almond oil body butter.","image":"images\\\\Almond Body Butter.jpg","weights":[{"label":"200g","price":450}]},
  {"id":318,"name":"Floral Fresh Body Mist","nameKn":"ಫ್ಲೋರಲ್ ಫ್ರೆಶ್ ಬಾಡಿ ಮಿಸ್ಟ್","category":"perfume","description":"Light floral fragrance for daily wear.","image":"images\\\\Floral Fresh Body Mist.jpg","weights":[{"label":"150ml","price":750}]},
  {"id":319,"name":"Protein Hair Mask","nameKn":"ಪ್ರೋಟೀನ್ ಹೇರ್ ಮಾಸ್ಕ್","category":"hairmask","description":"Strengthens hair with proteins.","image":"images\\\\Protein Hair Mask.jpg","weights":[{"label":"200g","price":500}]},
  {"id":320,"name":"Salon Hair Spa Cream","nameKn":"ಸಲೂನ್ ಹೇರ್ ಸ್ಪಾ ಕ್ರೀಮ್","category":"hairspa","description":"Professional salon-quality spa cream.","image":"images\\\\Salon Hair Spa Cream.jpg","weights":[{"label":"200g","price":550}]},
  {"id":321,"name":"Herbal Detox Wash","nameKn":"ಹರ್ಬಲ್ ಡಿಟಾಕ್ಸ್ ವಾಶ್","category":"bodywash","description":"Herbal formula for body detox.","image":"images\\\\Herbal Detox Wash 560.jpg","weights":[{"label":"250ml","price":560}]},
  {"id":322,"name":"Herbal Glow Cream","nameKn":"ಹರ್ಬಲ್ ಗ್ಲೋ ಕ್ರೀಮ್","category":"cream","description":"Natural herbs for radiant skin.","image":"images\\\\Herbal Glow Cream.jpg","weights":[{"label":"100g","price":360}]},
  {"id":323,"name":"Soothing Aloe Gel","nameKn":"ಸೂಥಿಂಗ್ ಅಲೋ ಜೆಲ್","category":"aloe","description":"Cools and soothes irritated skin.","image":"images\\\\Soothing Aloe Gel.jpg","weights":[{"label":"200g","price":260}]},
  {"id":324,"name":"Distilled Rose Water","nameKn":"ಡಿಸ್ಟಿಲ್ಡ್ ರೋಸ್ ವಾಟರ್","category":"toner","description":"Pure distilled rose water mist.","image":"images\\\\Steam Distilled Rose Water.jpg","weights":[{"label":"200ml","price":220}]},
  {"id":325,"name":"Moisturizing Glycerin","nameKn":"ಮಾಯಿಶ್ಚರೈಜಿಂಗ್ ಗ್ಲಿಸರಿನ್","category":"moisturizer","description":"Locks in moisture for soft skin.","image":"images\\\\Skin Protect Glycerin.jpg","weights":[{"label":"100ml","price":160}]},
  {"id":326,"name":"Advanced Healing Jelly","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ಹೀಲಿಂಗ್ ಜೆಲ್ಲಿ","category":"moisturizer","description":"Intensive skin repair jelly.","image":"images\\\\Advanced Healing Jelly.jpg","weights":[{"label":"100g","price":200}]},
  {"id":327,"name":"Outdoor Sunblock SPF50","nameKn":"ಔಟ್ಡೋರ್ ಸನ್ ಬ್ಲಾಕ್ SPF50","category":"sunscreen","description":"Water-resistant outdoor sunblock.","image":"images\\\\Outdoor Sunblock SPF50.jpg","weights":[{"label":"100ml","price":550}]},
  {"id":328,"name":"Soothing After Sun Gel","nameKn":"ಸೂಥಿಂಗ್ ಆಫ್ಟರ್ ಸನ್ ಜೆಲ್","category":"sunscreen","description":"Cools and repairs sun-exposed skin.","image":"images\\\\Soothing After Sun Gel.jpg","weights":[{"label":"100ml","price":300}]},
  {"id":329,"name":"Ultra Clean Foam Wash","nameKn":"ಅಲ್ಟ್ರಾ ಕ್ಲೀನ್ ಫೋಮ್ ವಾಶ್","category":"bodywash","description":"Deep cleansing foam body wash.","image":"images\\\\Ultra Clean Foam Wash.jpg","weights":[{"label":"200ml","price":320}]},
  {"id":330,"name":"Milk Protein Soap","nameKn":"ಮಿಲ್ಕ್ ಪ್ರೋಟೀನ್ ಸೋಪ್","category":"soap","description":"Nourishing milk protein enriched soap.","image":"images\\\\Milk Protein Soap 80.jpg","weights":[{"label":"125g","price":80}]},
  {"id":331,"name":"Classic Perfume","nameKn":"ಕ್ಲಾಸಿಕ್ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Timeless classic fragrance.","image":"images\\\\Classic Perfume.jpg","weights":[{"label":"100ml","price":3200}]},
  {"id":332,"name":"Extra Fresh Deodorant","nameKn":"ಎಕ್ಸ್ಟ್ರಾ ಫ್ರೆಶ್ ಡಿಯೋಡರೆಂಟ್","category":"deo","description":"Extra strength freshness.","image":"images\\\\Extra Fresh Deodorant.jpg","weights":[{"label":"150ml","price":340}]},
  {"id":333,"name":"Nourishing Body Lotion","nameKn":"ನೌರಿಶಿಂಗ್ ಬಾಡಿ ಲೋಷನ್","category":"lotion","description":"Rich nourishment for dry skin.","image":"images\\\\Nourishing Body Lotion 380.jpg","weights":[{"label":"300ml","price":400}]},
  {"id":334,"name":"Advanced Night Repair Cream","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ನೈಟ್ ರಿಪೇರ್ ಕ್ರೀಮ್","category":"cream","description":"Overnight cellular repair cream.","image":"images\\\\Advanced Night Repair Cream.jpg","weights":[{"label":"100g","price":650}]},
  {"id":335,"name":"Refreshing Toner","nameKn":"ರಿಫ್ರೆಶಿಂಗ್ ಟೋನರ್","category":"toner","description":"Wakes up tired skin.","image":"images\\\\Refreshing Toner.jpg","weights":[{"label":"150ml","price":340}]},
  {"id":336,"name":"Micellar Cleansing Water","nameKn":"ಮಿಸೆಲ್ಲಾರ್ ಕ್ಲೆನ್ಸಿಂಗ್ ವಾಟರ್","category":"makeupremover","description":"All-in-one micellar cleanser.","image":"images\\\\Micellar Cleansing Water.jpg","weights":[{"label":"200ml","price":420}]},
  {"id":337,"name":"Relaxing Bath Salt","nameKn":"ರಿಲ್ಯಾಕ್ಸಿಂಗ್ ಬಾತ್ ಸಾಲ್ಟ್","category":"bathsalt","description":"Stress-relieving bath salt.","image":"images\\\\Relaxing Bath Salt 620.jpg","weights":[{"label":"200g","price":360}]},
  {"id":338,"name":"Herbal Relax Bath Oil","nameKn":"ಹರ್ಬಲ್ ರಿಲ್ಯಾಕ್ಸ್ ಬಾತ್ ಆಯಿಲ್","category":"bathoil","description":"Aromatherapy bath oil.","image":"images\\\\Herbal Relax Bath Oil.jpg","weights":[{"label":"100ml","price":420}]},
  {"id":339,"name":"Exfoliating Bath Pad","nameKn":"ಎಕ್ಸ್ಫೋಲಿಯೇಟಿಂಗ್ ಬಾತ್ ಪ್ಯಾಡ್","category":"loofah","description":"Dual-sided exfoliating pad.","image":"images\\\\Exfoliating Bath Pad.jpg","weights":[{"label":"1pc","price":150}]},
  {"id":340,"name":"Premium Soft Sponge","nameKn":"ಪ್ರೀಮಿಯಂ ಸಾಫ್ಟ್ ಸ್ಪಾಂಜ್","category":"sponge","description":"Luxuriously soft bath sponge.","image":"images\\\\Premium Soft Sponge.jpg","weights":[{"label":"1pc","price":180}]},
  {"id":341,"name":"Designer Bath Towel","nameKn":"ಡಿಸೈನರ್ ಬಾತ್ ಟವೆಲ್","category":"towel","description":"Stylish designer bath towel.","image":"images\\\\Designer Bath Towel.jpg","weights":[{"label":"1pc","price":950}]},
  {"id":342,"name":"Designer Bath Robe","nameKn":"ಡಿಸೈನರ್ ಬಾತ್ ರೋಬ್","category":"robe","description":"Elegant designer bathrobe.","image":"images\\\\Designer Bath Robe.jpg","weights":[{"label":"1pc","price":2800}]},
  {"id":343,"name":"Premium Slippers","nameKn":"ಪ್ರೀಮಿಯಂ ಸ್ಲಿಪ್ಪರ್ಸ್","category":"slippers","description":"Premium quality bath slippers.","image":"images\\\\Premium Slippers.jpg","weights":[{"label":"1pair","price":500}]},
  {"id":344,"name":"Premium Shower Cap","nameKn":"ಪ್ರೀಮಿಯಂ ಶವರ್ ಕ್ಯಾಪ್","category":"showercap","description":"Durable and stylish shower cap.","image":"images\\\\Premium Shower Cap.jpg","weights":[{"label":"1pc","price":150}]},
  {"id":345,"name":"Organic Cotton Pads","nameKn":"ಆರ್ಗಾನಿಕ್ ಕಾಟನ್ ಪ್ಯಾಡ್ಸ್","category":"cotton","description":"USDA certified organic cotton pads.","image":"images\\\\Organic Cotton Pads.jpg","weights":[{"label":"100pcs","price":180}]},
  {"id":346,"name":"Refreshing Wipes","nameKn":"ರಿಫ್ರೆಶಿಂಗ್ ವೈಪ್ಸ್","category":"wipes","description":"Cooling and refreshing facial wipes.","image":"images\\\\Refreshing Wipes.jpg","weights":[{"label":"50pcs","price":240}]},
  {"id":347,"name":"Relax Body Oil","nameKn":"ರಿಲ್ಯಾಕ್ಸ್ ಬಾಡಿ ಆಯಿಲ್","category":"massageoil","description":"Deep relaxation massage oil.","image":"images\\\\Relax Body Oil.jpg","weights":[{"label":"200ml","price":340}]},
  {"id":348,"name":"Relax Aroma Oil","nameKn":"ರಿಲ್ಯಾಕ್ಸ್ ಅರೋಮಾ ಆಯಿಲ್","category":"aromaoil","description":"Stress-relief aromatherapy oil.","image":"images\\\\Relax Aroma Oil.jpg","weights":[{"label":"50ml","price":450}]},
  {"id":349,"name":"Eucalyptus Oil","nameKn":"ಯೂಕಲಿಪ್ಟಸ್ ಆಯಿಲ್","category":"essentialoil","description":"Respiratory relief essential oil.","image":"images\\\\Eucalyptus Oil.jpg","weights":[{"label":"30ml","price":480}]},
  {"id":350,"name":"Deep Moist Foam Cleanser","nameKn":"ಡೀಪ್ ಮಾಯಿಸ್ಟ್ ಫೋಮ್ ಕ್ಲೆನ್ಸರ್","category":"bodywash","description":"Hydrating foam for dry skin.","image":"images\\\\Deep Moist Foam Cleanser.jpg","weights":[{"label":"200ml","price":340}]},
  {"id":351,"name":"Herbal Cooling Body Wash","nameKn":"ಹರ್ಬಲ್ ಕೂಲಿಂಗ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Cooling herbal body wash for summer.","image":"images\\\\Herbal Cooling Body Wash 289.jpg","weights":[{"label":"300ml","price":340}]},
  {"id":352,"name":"Neem Soap","nameKn":"ನೀಮ್ ಸೋಪ್","category":"soap","description":"Antibacterial neem soap for acne-prone skin.","image":"images\\\\Neem Soap 25.jpg","weights":[{"label":"100g","price":25}]},
  {"id":353,"name":"Herbal Strength Shampoo","nameKn":"ಹರ್ಬಲ್ ಸ್ಟ್ರೆಂತ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Strengthens hair from roots.","image":"images\\\\Herbal Strength Shampoo 390.jpg","weights":[{"label":"200ml","price":280}]},
  {"id":354,"name":"Hydra Conditioner","nameKn":"ಹೈಡ್ರಾ ಕಂಡೀಷನರ್","category":"conditioner","description":"Intense hydration for thirsty hair.","image":"images\\\\Hydra Conditioner 290.jpg","weights":[{"label":"200ml","price":320}]},
  {"id":355,"name":"Herbal Strength Oil","nameKn":"ಹರ್ಬಲ್ ಸ್ಟ್ರೆಂತ್ ಆಯಿಲ್","category":"hairoil","description":"Herbal oil for strong hair.","image":"images\\\\Herbal Strength Oil 240.jpg","weights":[{"label":"200ml","price":260}]},
  {"id":356,"name":"Hair Repair Serum","nameKn":"ಹೇರ್ ರಿಪೇರ್ ಸೀರಮ್","category":"hairserum","description":"Repairs split ends and damage.","image":"images\\\\Hair Repair Serum 500.jpg","weights":[{"label":"100ml","price":420}]},
  {"id":357,"name":"Charcoal Deep Face Wash","nameKn":"ಚಾರ್ಕೋಲ್ ಡೀಪ್ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Deep pore cleansing with charcoal.","image":"images\\\\Charcoal Deep Face Wash 170.jpg","weights":[{"label":"150ml","price":250}]},
  {"id":358,"name":"Vitamin C Scrub","nameKn":"ವಿಟಮಿನ್ ಸಿ ಸ್ಕ್ರಬ್","category":"scrub","description":"Brightening scrub with vitamin C.","image":"images\\\\Vitamin C Scrub 350.jpg","weights":[{"label":"100g","price":260}]},
  {"id":359,"name":"Glow Boost Cream","nameKn":"ಗ್ಲೋ ಬೂಸ್ಟ್ ಕ್ರೀಮ್","category":"cream","description":"Instantly brightens dull skin.","image":"images\\\\Glow Boost Cream 850.jpg","weights":[{"label":"100g","price":500}]},
  {"id":360,"name":"Ultra Glow Lotion","nameKn":"ಅಲ್ಟ್ರಾ ಗ್ಲೋ ಲೋಷನ್","category":"lotion","description":"Radiance-boosting body lotion.","image":"images\\\\Ultra Glow Lotion 240.jpg","weights":[{"label":"300ml","price":420}]},
  {"id":361,"name":"Waterproof Sunscreen SPF50","nameKn":"ವಾಟರ್ಪ್ರೂಫ್ ಸನ್ಸ್ಕ್ರೀನ್ SPF50","category":"sunscreen","description":"Water-resistant sunscreen for swimming.","image":"images\\\\Waterproof Sunscreen SPF50 160.jpg","weights":[{"label":"100ml","price":600}]},
  {"id":362,"name":"Sport Active Deo","nameKn":"ಸ್ಪೋರ್ಟ್ ಆಕ್ಟಿವ್ ಡಿಯೋ","category":"deo","description":"Active sports deodorant.","image":"images\\\\Sport Active Deo.jpg","weights":[{"label":"150ml","price":360}]},
  {"id":363,"name":"Luxury Signature Perfume","nameKn":"ಲಕ್ಷ್ಮಿ ಸಿಗ್ನೇಚರ್ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Luxury signature perfume.","image":"images\\\\Signature Luxury Perfume.jpg","weights":[{"label":"100ml","price":3500}]},
  {"id":374,"name":"Vanilla Body Butter","nameKn":"ವೆನಿಲ್ಲಾ ಬಾಡಿ ಬಟರ್","category":"bodybutter","description":"Sweet vanilla scented body butter.","image":"images\\\\Vanilla Body Butter.jpg","weights":[{"label":"200g","price":460}]},
  {"id":375,"name":"Hair Repair Mask","nameKn":"ಹೇರ್ ರಿಪೇರ್ ಮಾಸ್ಕ್","category":"hairmask","description":"Intensive repair for damaged hair.","image":"images\\\\Deep Repair Hair Mask 190.jpg","weights":[{"label":"200g","price":520}]},
  {"id":376,"name":"Green Detox Wash","nameKn":"ಗ್ರೀನ್ ಡಿಟಾಕ್ಸ್ ವಾಶ್","category":"bodywash","description":"Green tea detox body wash.","image":"images\\\\Green Detox Wash 325.jpg","weights":[{"label":"250ml","price":340}]},
  {"id":377,"name":"Hydra Aloe Gel","nameKn":"ಹೈಡ್ರಾ ಅಲೋ ಜೆಲ್","category":"aloe","description":"Hydrating aloe vera gel.","image":"images\\\\Hydra Aloe Gel.jpg","weights":[{"label":"200g","price":280}]},
  {"id":378,"name":"Natural Rose Water","nameKn":"ನ್ಯಾಚುರಲ್ ರೋಸ್ ವಾಟರ್","category":"toner","description":"100% natural rose water.","image":"images\\\\Natural Rose Water.jpg","weights":[{"label":"200ml","price":240}]},
  {"id":379,"name":"Hydrating Glycerin","nameKn":"ಹೈಡ್ರೇಟಿಂಗ್ ಗ್ಲಿಸರಿನ್","category":"moisturizer","description":"Skin hydrating glycerin.","image":"images\\\\Hydrating Glycerin.jpg","weights":[{"label":"100ml","price":170}]},
  {"id":380,"name":"Healing Skin Jelly","nameKn":"ಹೀಲಿಂಗ್ ಸ್ಕಿನ್ ಜೆಲ್ಲಿ","category":"moisturizer","description":"Multi-purpose skin healing jelly.","image":"images\\\\Healing Skin Jelly.jpg","weights":[{"label":"100g","price":220}]},
  {"id":381,"name":"Ultra Gentle Body Foam","nameKn":"ಅಲ್ಟ್ರಾ ಜೆಂಟಲ್ ಬಾಡಿ ಫೋಮ್","category":"bodywash","description":"Extra gentle foam for sensitive skin.","image":"images\\\\Ultra Gentle Body Foam.jpg","weights":[{"label":"200ml","price":360}]},
  {"id":382,"name":"Deep Moist Body Wash","nameKn":"ಡೀಪ್ ಮಾಯಿಸ್ಟ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Deep moisturizing body wash.","image":"images\\\\Deep Moist Body Wash 420.jpg","weights":[{"label":"300ml","price":360}]},
  {"id":383,"name":"Herbal Milk Soap","nameKn":"ಹರ್ಬಲ್ ಮಿಲ್ಕ್ ಸೋಪ್","category":"soap","description":"Herbal milk enriched soap.","image":"images\\\\Herbal Milk Soap 240.jpg","weights":[{"label":"125g","price":130}]},
  {"id":384,"name":"Keratin Repair Shampoo","nameKn":"ಕೆರಾಟಿನ್ ರಿಪೇರ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Repairs with keratin protein.","image":"images\\\\Keratin Repair Shampoo 3000.jpg","weights":[{"label":"200ml","price":3000}]},
  {"id":385,"name":"Smooth Conditioner","nameKn":"ಸ್ಮೂತ್ ಕಂಡೀಷನರ್","category":"conditioner","description":"Makes hair smooth and manageable.","image":"images\\\\Smooth Conditioner 150.jpg","weights":[{"label":"200ml","price":340}]},
  {"id":386,"name":"Advanced Hair Oil","nameKn":"ಅಡ್ವಾನ್ಸೆಡ್ ಹೇರ್ ಆಯಿಲ್","category":"hairoil","description":"Advanced formula for hair health.","image":"images\\\\Advanced Hair Oil 800.jpg","weights":[{"label":"200ml","price":280}]},
  {"id":387,"name":"Gloss Repair Serum","nameKn":"ಗ್ಲಾಸ್ ರಿಪೇರ್ ಸೀರಮ್","category":"hairserum","description":"Adds gloss while repairing.","image":"images\\\\Gloss Repair Serum 420.jpg","weights":[{"label":"100ml","price":440}]},
  {"id":388,"name":"Daily Face Wash","nameKn":"ಡೈಲಿ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Gentle daily facial cleanser.","image":"images\\\\Daily Face Wash 410.jpg","weights":[{"label":"150ml","price":260}]},
  {"id":389,"name":"Herbal Scrub","nameKn":"ಹರ್ಬಲ್ ಸ್ಕ್ರಬ್","category":"scrub","description":"Natural herbal exfoliating scrub.","image":"images\\\\Herbal Scrub 180.jpg","weights":[{"label":"100g","price":270}]},
  {"id":390,"name":"Daily Glow Cream","nameKn":"ಡೈಲಿ ಗ್ಲೋ ಕ್ರೀಮ್","category":"cream","description":"Daily moisturizer for glowing skin.","image":"images\\\\Daily Glow Cream.jpg","weights":[{"label":"100g","price":520}]},
  {"id":391,"name":"Soft Skin Lotion","nameKn":"ಸಾಫ್ಟ್ ಸ್ಕಿನ್ ಲೋಷನ್","category":"lotion","description":"Leaves skin soft and smooth.","image":"images\\\\Soft Skin Lotion 320.jpg","weights":[{"label":"300ml","price":440}]},
  {"id":392,"name":"Ultra Shield SPF50","nameKn":"ಅಲ್ಟ್ರಾ ಶೀಲ್ಡ್ SPF50","category":"sunscreen","description":"Maximum protection sunscreen.","image":"images\\\\Ultra Shield SPF50 900.jpg","weights":[{"label":"100ml","price":650}]},
  {"id":393,"name":"Active Deo Spray","nameKn":"ಆಕ್ಟಿವ್ ಡಿಯೋ ಸ್ಪ್ರೇ","category":"deo","description":"Active lifestyle deodorant spray.","image":"images\\\\Active Deo Spray.jpg","weights":[{"label":"150ml","price":380}]},
  {"id":394,"name":"Premium Elite Perfume","nameKn":"ಪ್ರೀಮಿಯಂ ಎಲೈಟ್ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Premium elite fragrance collection.","image":"images\\\\Premium Elite Perfume.jpg","weights":[{"label":"100ml","price":3800}]},
  {"id":395,"name":"Premium Talcum Powder","nameKn":"ಪ್ರೀಮಿಯಂ ಟಾಲ್ಕಮ್ ಪೌಡರ್","category":"talc","description":"Premium quality talc.","image":"images\\\\Premium Talcum Powder.jpg","weights":[{"label":"200g","price":200}]},
  {"id":396,"name":"Gentle Hand Wash","nameKn":"ಜೆಂಟಲ್ ಹ್ಯಾಂಡ್ ವಾಷ್","category":"handwash","description":"Gentle on sensitive hands.","image":"images\\\\Gentle Hand Wash.jpg","weights":[{"label":"250ml","price":140}]},
  {"id":397,"name":"Advanced Sanitizer","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ಸ್ಯಾನಿಟೈಜರ್","category":"sanitizer","description":"Advanced germ protection.","image":"images\\\\Advanced Sanitizer.jpg","weights":[{"label":"100ml","price":120}]},
  {"id":398,"name":"Almond Glow Butter","nameKn":"ಆಲ್ಮಂಡ್ ಗ್ಲೋ ಬಟರ್","category":"bodybutter","description":"Almond butter for glowing skin.","image":"images\\\\Almond Glow Butter.jpg","weights":[{"label":"200g","price":480}]},
  {"id":399,"name":"Advanced Repair Mask","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ರಿಪೇರ್ ಮಾಸ್ಕ್","category":"hairmask","description":"Advanced damage repair mask.","image":"images\\\\Advanced Repair Mask.jpg","weights":[{"label":"200g","price":550}]},
  {"id":400,"name":"Silk Foam Cleanser","nameKn":"ಸಿಲ್ಕ್ ಫೋಮ್ ಕ್ಲೆನ್ಸರ್","category":"bodywash","description":"Leaves skin silky smooth.","image":"images\\\\Silk Foam Cleanser.jpg","weights":[{"label":"200ml","price":2100}]},
  {"id":401,"name":"Ultra Hydration Body Wash","nameKn":"ಅಲ್ಟ್ರಾ ಹೈಡ್ರೇಶನ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Maximum hydration body wash.","image":"images\\\\Ultra Hydration Body Wash 1820.jpg","weights":[{"label":"300ml","price":380}]},
  {"id":402,"name":"Herbal Glow Body Wash","nameKn":"ಹರ್ಬಲ್ ಗ್ಲೋ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Herbal body wash for radiance.","image":"images\\\\Herbal Glow Body Wash.jpg","weights":[{"label":"250ml","price":350}]},
  {"id":403,"name":"Goat Milk Soap","nameKn":"ಗೋಟ್ ಮಿಲ್ಕ್ ಸೋಪ್","category":"soap","description":"Gentle goat milk soap.","image":"images\\\\Goat Milk Soap.jpg","weights":[{"label":"125g","price":160}]},
  {"id":404,"name":"Turmeric Soap","nameKn":"ಅರಿಶಿನ ಸೋಪ್","category":"soap","description":"Natural turmeric soap for glowing skin.","image":"images\\\\Turmeric Soap 152.jpg","weights":[{"label":"100g","price":120}]},
  {"id":405,"name":"Charcoal Detox Soap","nameKn":"ಚಾರ್ಕೋಲ್ ಡಿಟಾಕ್ಸ್ ಸೋಪ್","category":"soap","description":"Detoxifying charcoal soap.","image":"images\\\\Charcoal Detox Soap 250.jpg","weights":[{"label":"100g","price":250}]},
  {"id":406,"name":"Argan Oil Shampoo","nameKn":"ಅರ್ಗಾನ್ ಆಯಿಲ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Moroccan argan oil shampoo.","image":"images\\\\Argan Oil Shampoo 165.jpg","weights":[{"label":"200ml","price":160}]},
  {"id":407,"name":"Deep Repair Shampoo","nameKn":"ಡೀಪ್ ರಿಪೇರ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Deep repair for damaged hair.","image":"images\\\\Deep Repair Shampoo 610.jpg","weights":[{"label":"250ml","price":610}]},
  {"id":408,"name":"Moisture Lock Conditioner","nameKn":"ಮಾಯಿಶ್ಚರ್ ಲಾಕ್ ಕಂಡೀಷನರ್","category":"conditioner","description":"Locks in moisture for soft hair.","image":"images\\\\Moisture Lock Conditioner 960.jpg","weights":[{"label":"200ml","price":350}]},
  {"id":409,"name":"Herbal Shine Conditioner","nameKn":"ಹರ್ಬಲ್ ಶೈನ್ ಕಂಡೀಷನರ್","category":"conditioner","description":"Herbal conditioner for shiny hair.","image":"images\\\\Herbal Shine Conditioner.jpg","weights":[{"label":"250ml","price":370}]},
  {"id":410,"name":"Keratin Hair Oil","nameKn":"ಕೆರಾಟಿನ್ ಹೇರ್ ಆಯಿಲ್","category":"hairoil","description":"Keratin-enriched hair oil.","image":"images\\\\Keratin Hair Oil 250.jpg","weights":[{"label":"200ml","price":300}]},
  {"id":411,"name":"Herbal Nourish Oil","nameKn":"ಹರ್ಬಲ್ ನೌರಿಶ್ ಆಯಿಲ್","category":"hairoil","description":"Nourishing herbal hair oil.","image":"images\\\\Herbal Nourish Oil.jpg","weights":[{"label":"200ml","price":280}]},
  {"id":412,"name":"Ultra Shine Serum","nameKn":"ಅಲ್ಟ್ರಾ ಶೈನ್ ಸೀರಮ್","category":"hairserum","description":"Intense shine hair serum.","image":"images\\\\Ultra Shine Serum 120.jpg","weights":[{"label":"100ml","price":460}]},
  {"id":413,"name":"Advanced Smooth Serum","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ಸ್ಮೂತ್ ಸೀರಮ್","category":"hairserum","description":"Advanced smoothing formula.","image":"images\\\\Advanced Smooth Serum 500.jpg","weights":[{"label":"100ml","price":480}]},
  {"id":414,"name":"Brightening Face Wash","nameKn":"ಬ್ರೈಟನಿಂಗ್ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Brightens dull complexion.","image":"images\\\\Brightening Face Wash 500.jpg","weights":[{"label":"150ml","price":270}]},
  {"id":415,"name":"Daily Care Face Wash","nameKn":"ಡೈಲಿ ಕೇರ್ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Daily essential face wash.","image":"images\\\\Daily Care Face Wash 340.jpg","weights":[{"label":"150ml","price":280}]},
  {"id":416,"name":"Apricot Scrub","nameKn":"ಏಪ್ರಿಕಾಟ್ ಸ್ಕ್ರಬ್","category":"scrub","description":"Gentle apricot exfoliating scrub.","image":"images\\\\Apricot Scrub 160.jpg","weights":[{"label":"100g","price":280}]},
  {"id":417,"name":"Detox Body Scrub","nameKn":"ಡಿಟಾಕ್ಸ್ ಬಾಡಿ ಸ್ಕ್ರಬ್","category":"scrub","description":"Body detoxifying scrub.","image":"images\\\\Detox Body Scrub.jpg","weights":[{"label":"200g","price":340}]},
  {"id":418,"name":"Anti-Wrinkle Cream","nameKn":"ಆಂಟಿ-ರಿಂಕಲ್ ಕ್ರೀಮ್","category":"cream","description":"Reduces signs of aging.","image":"images\\\\Anti-Wrinkle Cream 270.jpg","weights":[{"label":"50g","price":700}]},
  {"id":419,"name":"Glow Repair Cream","nameKn":"ಗ್ಲೋ ರಿಪೇರ್ ಕ್ರೀಮ್","category":"cream","description":"Repairs and brightens skin.","image":"images\\\\Glow Repair Cream.jpg","weights":[{"label":"100g","price":550}]},
  {"id":420,"name":"Vitamin Glow Lotion","nameKn":"ವಿಟಮಿನ್ ಗ್ಲೋ ಲೋಷನ್","category":"lotion","description":"Vitamin-enriched glowing lotion.","image":"images\\\\Vitamin Glow Lotion 450.jpg","weights":[{"label":"300ml","price":450}]},
  {"id":421,"name":"Deep Nourish Lotion","nameKn":"ಡೀಪ್ ನೌರಿಶ್ ಲೋಷನ್","category":"lotion","description":"Deep nourishment for dry skin.","image":"images\\\\Deep Nourish Lotion 110.jpg","weights":[{"label":"300ml","price":480}]},
  {"id":422,"name":"Matte Sunscreen SPF60","nameKn":"ಮ್ಯಾಟ್ ಸನ್ ಸ್ಕ್ರೀನ್ SPF60","category":"sunscreen","description":"Matte finish high protection.","image":"images\\\\Matte Sunscreen SPF60 480.jpg","weights":[{"label":"100ml","price":700}]},
  {"id":423,"name":"Active Fresh Deo","nameKn":"ಆಕ್ಟಿವ್ ಫ್ರೆಶ್ ಡಿಯೋ","category":"deo","description":"Active fresh deodorant.","image":"images\\\\Active Fresh Deo.jpg","weights":[{"label":"150ml","price":400}]},
  {"id":424,"name":"Exclusive Perfume","nameKn":"ಎಕ್ಸ್ಕ್ಲೂಸಿವ್ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Exclusive luxury perfume.","image":"images\\\\Exclusive Perfume.jpg","weights":[{"label":"100ml","price":4000}]},
  {"id":425,"name":"Premium Oud Perfume","nameKn":"ಪ್ರೀಮಿಯಂ ಔದ್ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Rich oud fragrance.","image":"images\\\\Premium Oud Perfume.jpg","weights":[{"label":"100ml","price":4500}]},
  {"id":426,"name":"Cooling Fresh Powder","nameKn":"ಕೂಲಿಂಗ್ ಫ್ರೆಶ್ ಪೌಡರ್","category":"talc","description":"Cooling freshness talc.","image":"images\\\\Cooling Fresh Powder.jpg","weights":[{"label":"200g","price":220}]},
  {"id":427,"name":"Soft Touch Hand Wash","nameKn":"ಸಾಫ್ಟ್ ಟಚ್ ಹ್ಯಾಂಡ್ ವಾಶ್","category":"handwash","description":"Soft touch hand wash.","image":"images\\\\Soft Touch Hand Wash.jpg","weights":[{"label":"250ml","price":150}]},
  {"id":428,"name":"Herbal Protection Sanitizer","nameKn":"ಹರ್ಬಲ್ ಪ್ರೊಟೆಕ್ಷನ್ ಸ್ಯಾನಿಟೈಜರ್","category":"sanitizer","description":"Herbal protection sanitizer.","image":"images\\\\Herbal Protection Sanitizer.jpg","weights":[{"label":"100ml","price":130}]},
  {"id":429,"name":"Ultra Smooth Shaving Gel","nameKn":"ಅಲ್ಟ್ರಾ ಸ್ಮೂತ್ ಶೇವಿಂಗ್ ಜೆಲ್","category":"shavecream","description":"Ultra-smooth shaving experience.","image":"images\\\\Ultra Smooth Shaving Gel.jpg","weights":[{"label":"100g","price":260}]},
  {"id":430,"name":"Soothing After Shave Balm","nameKn":"ಸೂಥಿಂಗ್ ಆಫ್ಟರ್ ಶೇವ್ ಬಾಮ್","category":"aftershave","description":"Soothes post-shave irritation.","image":"images\\\\Soothing After Shave Balm.jpg","weights":[{"label":"100ml","price":360}]},
  {"id":431,"name":"Sensitive Toothpaste","nameKn":"ಸೆನ್ಸಿಟಿವ್ ಟೂತ್ ಪೇಸ್ಟ್","category":"toothpaste","description":"For sensitive teeth and gums.","image":"images\\\\Sensitive Toothpaste.jpg","weights":[{"label":"150g","price":160}]},
  {"id":432,"name":"Complete Care Mouthwash","nameKn":"ಕಂಪ್ಲೀಟ್ ಕೇರ್ ಮೌತ್ ವಾಶ್","category":"mouthwash","description":"Complete oral care mouthwash.","image":"images\\\\Complete Care Mouthwash.jpg","weights":[{"label":"300ml","price":280}]},
  {"id":433,"name":"Mint Lip Balm","nameKn":"ಮಿಂಟ್ ಲಿಪ್ ಬಾಮ್","category":"lipbalm","description":"Refreshing mint lip balm.","image":"images\\\\Mint Lip Balm.jpg","weights":[{"label":"10g","price":160}]},
  {"id":434,"name":"Crack Heal Foot Cream","nameKn":"ಕ್ರ್ಯಾಕ್ ಹೀಲ್ ಫುಟ್ ಕ್ರೀಮ್","category":"footcream","description":"Heals cracked heels fast.","image":"images\\\\Crack Heal Foot Cream.jpg","weights":[{"label":"50g","price":260}]},
  {"id":435,"name":"Moist Hand Cream","nameKn":"ಮಾಯಿಸ್ಟ್ ಹ್ಯಾಂಡ್ ಕ್ರೀಮ್","category":"handcream","description":"Intense hand moisturization.","image":"images\\\\Moist Hand Cream.jpg","weights":[{"label":"50g","price":240}]},
  {"id":436,"name":"Detangling Hair Brush","nameKn":"ಡಿಟ್ಯಾಂಗ್ಲಿಂಗ್ ಹೇರ್ ಬ್ರಷ್","category":"hairbrush","description":"Detangles wet or dry hair.","image":"images\\\\Detangling Hair Brush.jpg","weights":[{"label":"1pc","price":250}]},
  {"id":437,"name":"Professional Comb","nameKn":"ಪ್ರೊಫೆಷನಲ್ ಕಂಬ್","category":"comb","description":"Professional salon comb.","image":"images\\\\Professional Comb.jpg","weights":[{"label":"1pc","price":100}]},
  {"id":438,"name":"Extra Hold Gel","nameKn":"ಎಕ್ಸ್ಟ್ರಾ ಹೋಲ್ಡ್ ಜೆಲ್","category":"hairgel","description":"Maximum extra hold gel.","image":"images\\\\Extra Hold Gel.jpg","weights":[{"label":"150ml","price":260}]},
  {"id":439,"name":"Strong Matte Wax","nameKn":"ಸ್ಟ್ರಾಂಗ್ ಮ್ಯಾಟ್ ವ್ಯಾಕ್ಸ್","category":"hairwax","description":"Strong matte finish wax.","image":"images\\\\Strong Matte Wax.jpg","weights":[{"label":"100g","price":350}]},
  {"id":440,"name":"Herbal Glow Pack","nameKn":"ಹರ್ಬಲ್ ಗ್ಲೋ ಪ್ಯಾಕ್","category":"facepack","description":"Herbal face pack for glow.","image":"images\\\\Herbal Glow Pack.jpg","weights":[{"label":"100g","price":220}]},
  {"id":441,"name":"Gold Sheet Mask","nameKn":"ಗೋಲ್ಡ್ ಶೀಟ್ ಮಾಸ್ಕ್","category":"mask","description":"Luxurious gold-infused sheet mask.","image":"images\\\\Gold Sheet Mask.jpg","weights":[{"label":"1pc","price":180}]},
  {"id":442,"name":"Collagen Boost Mask","nameKn":"ಕಾಲಾಜನ್ ಬೂಸ್ಟ್ ಮಾಸ್ಕ್","category":"mask","description":"Boosts collagen for firm skin.","image":"images\\\\Collagen Boost Mask.jpg","weights":[{"label":"1pc","price":240}]},
  {"id":443,"name":"Vitamin C Face Pack","nameKn":"ವಿಟಮಿನ್ ಸಿ ಫೇಸ್ ಪ್ಯಾಕ್","category":"facepack","description":"Brightening vitamin C face pack.","image":"images\\\\Vitamin C Face Pack.jpg","weights":[{"label":"100g","price":200}]},
  {"id":444,"name":"Herbal Detox Bath Salt","nameKn":"ಹರ್ಬಲ್ ಡಿಟಾಕ್ಸ್ ಬಾತ್ ಸಾಲ್ಟ್","category":"bathsalt","description":"Herbal detoxifying bath salt.","image":"images\\\\Herbal Detox Bath Salt.jpg","weights":[{"label":"200g","price":350}]},
  {"id":445,"name":"Ocean Bath Salt","nameKn":"ಓಷನ್ ಬಾತ್ ಸಾಲ್ಟ್","category":"bathsalt","description":"Ocean mineral bath salt.","image":"images\\\\Ocean Bath Salt 330.jpg","weights":[{"label":"200g","price":380}]},
  {"id":446,"name":"Herbal Calm Bath Oil","nameKn":"ಹರ್ಬಲ್ ಕ್ಯಾಲ್ಮ್ ಬಾತ್ ಆಯಿಲ್","category":"bathoil","description":"Calming herbal bath oil.","image":"images\\\\Herbal Calm Bath Oil.jpg","weights":[{"label":"100ml","price":450}]},
  {"id":447,"name":"Premium Natural Loofah","nameKn":"ಪ್ರೀಮಿಯಂ ನ್ಯಾಚುರಲ್ ಲೂಫಾ","category":"loofah","description":"Premium natural loofah.","image":"images\\\\Premium Natural Loofah.jpg","weights":[{"label":"1pc","price":180}]},
  {"id":448,"name":"Luxury Bath Sponge","nameKn":"ಲಕ್ಷ್ಮಿ ಬಾತ್ ಸ್ಪಾಂಜ್","category":"sponge","description":"Luxury bath sponge.","image":"images\\\\Luxury Bath Sponge.jpg","weights":[{"label":"1pc","price":200}]},
  {"id":449,"name":"Ultra Soft Bath Towel","nameKn":"ಅಲ್ಟ್ರಾ ಸಾಫ್ಟ್ ಬಾತ್ ಟವೆಲ್","category":"towel","description":"Ultra-soft premium towel.","image":"images\\\\Ultra Soft Bath Towel.jpg","weights":[{"label":"1pc","price":1000}]},
  {"id":450,"name":"Premium Face Towel","nameKn":"ಪ್ರೀಮಿಯಂ ಫೇಸ್ ಟವೆಲ್","category":"towel","description":"Premium quality face towel.","image":"images\\\\Premium Face Towel.jpg","weights":[{"label":"1pc","price":300}]},
  {"id":451,"name":"Premium Cotton Robe","nameKn":"ಪ್ರೀಮಿಯಂ ಕಾಟನ್ ರೋಬ್","category":"robe","description":"Luxurious premium cotton bathrobe for ultimate comfort.","image":"images\\\\Premium Cotton Robe.jpg","weights":[{"label":"1pc","price":3000}]},
  {"id":452,"name":"Anti-Slip Soft Slippers","nameKn":"ಆಂಟಿ-ಸ್ಲಿಪ್ ಸಾಫ್ಟ್ ಸ್ಲಿಪ್ಪರ್ಸ್","category":"slippers","description":"Non-slip soft bath slippers with memory foam.","image":"images\\\\Anti-Slip Soft Slippers.jpg","weights":[{"label":"1pair","price":550}]},
  {"id":453,"name":"Waterproof Shower Cap","nameKn":"ವಾಟರ್ ಪ್ರೂಫ್ ಶವರ್ ಕ್ಯಾಪ್","category":"showercap","description":"Fully waterproof shower cap with elastic band.","image":"images\\\\Waterproof Shower Cap.jpg","weights":[{"label":"1pc","price":180}]},
  {"id":454,"name":"Ultra Soft Cotton Pads","nameKn":"ಅಲ್ಟ್ರಾ ಸಾಫ್ಟ್ ಕಾಟನ್ ಪ್ಯಾಡ್ಸ್","category":"cotton","description":"Extra soft cotton pads for sensitive skin.","image":"images\\\\Ultra Soft Cotton Pads.jpg","weights":[{"label":"100pcs","price":200}]},
  {"id":455,"name":"Vitamin Wipes","nameKn":"ವಿಟಮಿನ್ ವೈಪ್ಸ್","category":"wipes","description":"Vitamin-enriched facial cleansing wipes.","image":"images\\\\Vitamin Wipes.jpg","weights":[{"label":"50pcs","price":260}]},
  {"id":456,"name":"Healing Skin Cream","nameKn":"ಹೀಲಿಂಗ್ ಸ್ಕಿನ್ ಕ್ರೀಮ್","category":"firstaid","description":"Heals minor skin irritations and cuts.","image":"images\\\\Healing Skin Cream.jpg","weights":[{"label":"50g","price":260}]},
  {"id":457,"name":"Baby Moist Soap","nameKn":"ಬೇಬಿ ಮಾಯಿಸ್ಟ್ ಸೋಪ್","category":"baby","description":"Extra moisturizing soap for baby\'s delicate skin.","image":"images\\\\Baby Moist Soap.jpg","weights":[{"label":"100g","price":160}]},
  {"id":458,"name":"Baby Care Wash","nameKn":"ಬೇಬಿ ಕೇರ್ ವಾಶ್","category":"baby","description":"Complete gentle care baby wash.","image":"images\\\\Baby Care Wash.jpg","weights":[{"label":"200ml","price":350}]},
  {"id":459,"name":"Baby Smooth Lotion","nameKn":"ಬೇಬಿ ಸ್ಮೂತ್ ಲೋಷನ್","category":"baby","description":"Makes baby\'s skin smooth and soft.","image":"images\\\\Baby Smooth Lotion.jpg","weights":[{"label":"200ml","price":400}]},
  {"id":460,"name":"Baby Gentle Oil","nameKn":"ಬೇಬಿ ಜೆಂಟಲ್ ಆಯಿಲ್","category":"baby","description":"Gentle massage oil for babies.","image":"images\\\\Baby Gentle Oil.jpg","weights":[{"label":"200ml","price":300}]},
  {"id":461,"name":"Baby Fresh Wipes","nameKn":"ಬೇಬಿ ಫ್ರೆಶ್ ವೈಪ್ಸ್","category":"baby","description":"Fresh and gentle baby wipes.","image":"images\\\\Baby Fresh Wipes.jpg","weights":[{"label":"80pcs","price":240}]},
  {"id":462,"name":"Relax Therapy Oil","nameKn":"ರಿಲ್ಯಾಕ್ಸ್ ಥೆರಪಿ ಆಯಿಲ್","category":"massageoil","description":"Therapeutic relaxation massage oil.","image":"images\\\\Relax Therapy Oil.jpg","weights":[{"label":"200ml","price":360}]},
  {"id":463,"name":"Jasmine Aroma Oil","nameKn":"ಜಾಸ್ಮಿನ್ ಅರೋಮಾ ಆಯಿಲ್","category":"aromaoil","description":"Sweet jasmine aromatherapy oil.","image":"images\\\\Jasmine Aroma Oil.jpg","weights":[{"label":"50ml","price":480}]},
  {"id":464,"name":"Rose Essential Oil","nameKn":"ರೋಸ್ ಎಸೆನ್ಷಿಯಲ್ ಆಯಿಲ್","category":"essentialoil","description":"Pure rose essential oil for luxury skincare.","image":"images\\\\Rose Essential Oil.jpg","weights":[{"label":"30ml","price":500}]},
  {"id":465,"name":"Refreshing Foot Soak","nameKn":"ರಿಫ್ರೆಶಿಂಗ್ ಫುಟ್ ಸೋಕ್","category":"footcare","description":"Refreshes and revitalizes tired feet.","image":"images\\\\Refreshing Foot Soak.jpg","weights":[{"label":"200g","price":300}]},
  {"id":466,"name":"Professional Electric Foot File","nameKn":"ಪ್ರೊಫೆಷನಲ್ ಎಲೆಕ್ಟ್ರಿಕ್ ಫುಟ್ ಫೈಲ್","category":"footcare","description":"Professional grade electric callus remover.","image":"images\\\\Professional Electric Foot File.jpg","weights":[{"label":"1pc","price":2800}]},
  {"id":467,"name":"Argan Body Butter","nameKn":"ಅರ್ಗಾನ್ ಬಾಡಿ ಬಟರ್","category":"bodybutter","description":"Moroccan argan oil body butter.","image":"images\\\\Argan Body Butter.jpg","weights":[{"label":"200g","price":1280}]},
  {"id":468,"name":"Premium Floral Mist","nameKn":"ಪ್ರೀಮಿಯಂ ಫ್ಲೋರಲ್ ಮಿಸ್ಟ್","category":"perfume","description":"Premium floral body mist for daily freshness.","image":"images\\\\Premium Floral Mist.jpg","weights":[{"label":"150ml","price":800}]},
  {"id":469,"name":"Keratin Smooth Mask","nameKn":"ಕೆರಾಟಿನ್ ಸ್ಮೂತ್ ಮಾಸ್ಕ್","category":"hairmask","description":"Keratin-enriched smoothing hair mask.","image":"images\\\\Keratin Smooth Mask.jpg","weights":[{"label":"200g","price":550}]},
  {"id":470,"name":"Professional Spa Cream","nameKn":"ಪ್ರೊಫೆಷನಲ್ ಸ್ಪಾ ಕ್ರೀಮ್","category":"hairspa","description":"Professional salon spa treatment cream.","image":"images\\\\Professional Hair Spa Cream 800.jpg","weights":[{"label":"200g","price":600}]},
  {"id":471,"name":"Herbal Deep Detox Wash","nameKn":"ಹರ್ಬಲ್ ಡೀಪ್ ಡಿಟಾಕ್ಸ್ ವಾಶ್","category":"bodywash","description":"Deep detox herbal body wash.","image":"images\\\\Herbal Deep Detox Wash.jpg","weights":[{"label":"250ml","price":350}]},
  {"id":472,"name":"Ayurvedic Skin Cream","nameKn":"ಆಯುರ್ವೇದಿಕ್ ಸ್ಕಿನ್ ಕ್ರೀಮ್","category":"cream","description":"Traditional Ayurvedic skin cream.","image":"images\\\\Ayurvedic Skin Cream.jpg","weights":[{"label":"100g","price":380}]},
  {"id":473,"name":"Fresh Aloe Gel","nameKn":"ಫ್ರೆಶ್ ಅಲೋ ಜೆಲ್","category":"aloe","description":"Fresh aloe vera gel with no additives.","image":"images\\\\Fresh Aloe Gel.jpg","weights":[{"label":"200g","price":300}]},
  {"id":474,"name":"Steam Distilled Rose Water","nameKn":"ಸ್ಟೀಮ್ ಡಿಸ್ಟಿಲ್ಡ್ ರೋಸ್ ವಾಟರ್","category":"toner","description":"Pure steam distilled rose water.","image":"images\\\\Steam Distilled Rose Water.jpg","weights":[{"label":"200ml","price":260}]},
  {"id":475,"name":"Skin Protect Glycerin","nameKn":"ಸ್ಕಿನ್ ಪ್ರೊಟೆಕ್ಟ್ ಗ್ಲಿಸರಿನ್","category":"moisturizer","description":"Protects and moisturizes skin.","image":"images\\\\Skin Protect Glycerin.jpg","weights":[{"label":"100ml","price":180}]},
  {"id":476,"name":"Ultra Repair Jelly","nameKn":"ಅಲ್ಟ್ರಾ ರಿಪೇರ್ ಜೆಲ್ಲಿ","category":"moisturizer","description":"Ultra-strength healing jelly.","image":"images\\\\Ultra Repair Jelly.jpg","weights":[{"label":"100g","price":240}]},
  {"id":477,"name":"Deep Clean Foam","nameKn":"ಡೀಪ್ ಕ್ಲೀನ್ ಫೋಮ್","category":"bodywash","description":"Deep cleansing foam body wash.","image":"images\\\\Deep Clean Foam.jpg","weights":[{"label":"200ml","price":400}]},
  {"id":478,"name":"Ultra Glow Body Wash","nameKn":"ಅಲ್ಟ್ರಾ ಗ್ಲೋ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Body wash for radiant glowing skin.","image":"images\\\\Ultra Glow Body Wash 350.jpg","weights":[{"label":"300ml","price":400}]},
  {"id":479,"name":"Herbal Premium Soap","nameKn":"ಹರ್ಬಲ್ ಪ್ರೀಮಿಯಂ ಸೋಪ್","category":"soap","description":"Premium quality herbal soap.","image":"images\\\\Premium Herbal Soap 630.jpg","weights":[{"label":"125g","price":630}]},
  {"id":480,"name":"Signature Luxury Perfume","nameKn":"ಸಿಗ್ನೇಚರ್ ಲಕ್ಷ್ಮಿ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Signature luxury fragrance for special occasions.","image":"images\\\\Signature Luxury Perfume.jpg","weights":[{"label":"100ml","price":5000}]},
  {"id":481,"name":"24H Fresh Deo","nameKn":"24H ಫ್ರೆಶ್ ಡಿಯೋ","category":"deo","description":"24-hour freshness deodorant.","image":"images\\\\24H Fresh Deo.jpg","weights":[{"label":"150ml","price":420}]},
  {"id":482,"name":"Glow Boost Lotion","nameKn":"ಗ್ಲೋ ಬೂಸ್ಟ್ ಲೋಷನ್","category":"lotion","description":"Boosts skin radiance and glow.","image":"images\\\\Glow Boost Lotion.jpg","weights":[{"label":"300ml","price":2500}]},
  {"id":483,"name":"Ultimate Repair Cream","nameKn":"ಅಲ್ಟಿಮೇಟ್ ರಿಪೇರ್ ಕ್ರೀಮ್","category":"cream","description":"Ultimate repair for damaged skin.","image":"images\\\\Ultimate Repair Cream 2000.jpg","weights":[{"label":"100g","price":700}]},
  {"id":484,"name":"Hydra Boost Toner","nameKn":"ಹೈಡ್ರಾ ಬೂಸ್ಟ್ ಟೋನರ್","category":"toner","description":"Boosts skin hydration levels.","image":"images\\\\Hydra Boost Toner.jpg","weights":[{"label":"150ml","price":380}]},
  {"id":485,"name":"Ultra Clean Remover","nameKn":"ಅಲ್ಟ್ರಾ ಕ್ಲೀನ್ ರಿಮೂವರ್","category":"makeupremover","description":"Ultra-clean makeup remover.","image":"images\\\\Ultra Clean Remover.jpg","weights":[{"label":"200ml","price":480}]},
  {"id":486,"name":"Spa Bath Salt","nameKn":"ಸ್ಪಾ ಬಾತ್ ಸಾಲ್ಟ್","category":"bathsalt","description":"Luxury spa-quality bath salt.","image":"images\\\\Spa Bath Salt 450.jpg","weights":[{"label":"200g","price":400}]},
  {"id":487,"name":"Aromatherapy Bath Oil","nameKn":"ಅರೋಮಾಥೆರಪಿ ಬಾತ್ ಆಯಿಲ್","category":"bathoil","description":"Essential oil blend for aromatherapy bath.","image":"images\\\\Aromatherapy Bath Oil.jpg","weights":[{"label":"100ml","price":480}]},
  {"id":488,"name":"Spa Loofah","nameKn":"ಸ್ಪಾ ಲೂಫಾ","category":"loofah","description":"Professional spa quality loofah.","image":"images\\\\Spa Loofah.jpg","weights":[{"label":"1pc","price":200}]},
  {"id":489,"name":"Deluxe Sponge","nameKn":"ಡೀಲಕ್ಸ್ ಸ್ಪಾಂಜ್","category":"sponge","description":"Deluxe bath sponge for luxury bathing.","image":"images\\\\Deluxe Sponge.jpg","weights":[{"label":"1pc","price":220}]},
  {"id":490,"name":"Hotel Quality Towel","nameKn":"ಹೋಟೆಲ್ ಕ್ವಾಲಿಟಿ ಟವೆಲ್","category":"towel","description":"Hotel quality premium bath towel.","image":"images\\\\towel\\\\Hotel Quality Towel.jpg","weights":[{"label":"1pc","price":1200}]},
  {"id":491,"name":"Hotel Style Robe","nameKn":"ಹೋಟೆಲ್ ಸ್ಟೈಲ್ ರೋಬ್","category":"robe","description":"Luxury hotel style bathrobe.","image":"images\\\\Hotel Style Robe.jpg","weights":[{"label":"1pc","price":3500}]},
  {"id":492,"name":"Hotel Slippers","nameKn":"ಹೋಟೆಲ್ ಸ್ಲಿಪ್ಪರ್ಸ್","category":"slippers","description":"Comfortable hotel quality slippers.","image":"images\\\\Hotel Slippers.jpg","weights":[{"label":"1pair","price":600}]},
  {"id":493,"name":"Premium Elastic Cap","nameKn":"ಪ್ರೀಮಿಯಂ ಎಲಾಸ್ಟಿಕ್ ಕ್ಯಾಪ್","category":"showercap","description":"Premium elastic shower cap.","image":"images\\\\Premium Elastic Cap.jpg","weights":[{"label":"1pc","price":200}]},
  {"id":494,"name":"Medical Cotton Pads","nameKn":"ಮೆಡಿಕಲ್ ಕಾಟನ್ ಪ್ಯಾಡ್ಸ್","category":"cotton","description":"Medical grade sterile cotton pads.","image":"images\\\\Medical Cotton Pads.jpg","weights":[{"label":"100pcs","price":220}]},
  {"id":495,"name":"Deep Clean Wipes","nameKn":"ಡೀಪ್ ಕ್ಲೀನ್ ವೈಪ್ಸ್","category":"wipes","description":"Deep cleansing facial wipes.","image":"images\\\\Deep Clean Wipes.jpg","weights":[{"label":"50pcs","price":280}]},
  {"id":496,"name":"Spa Relax Oil","nameKn":"ಸ್ಪಾ ರಿಲ್ಯಾಕ್ಸ್ ಆಯಿಲ್","category":"massageoil","description":"Spa quality relaxation massage oil.","image":"images\\\\Spa Relax Oil.jpg","weights":[{"label":"200ml","price":380}]},
  {"id":497,"name":"Herbal Aroma Oil","nameKn":"ಹರ್ಬಲ್ ಅರೋಮಾ ಆಯಿಲ್","category":"aromaoil","description":"Herbal blend aromatherapy oil.","image":"images\\\\Herbal Aroma Oil.jpg","weights":[{"label":"50ml","price":500}]},
  {"id":498,"name":"Chamomile Oil","nameKn":"ಕ್ಯಾಮೊಮೈಲ್ ಆಯಿಲ್","category":"essentialoil","description":"Calming chamomile essential oil.","image":"images\\\\Chamomile Oil.jpg","weights":[{"label":"30ml","price":520}]},
  {"id":499,"name":"Ultra Smooth Butter","nameKn":"ಅಲ್ಟ್ರಾ ಸ್ಮೂತ್ ಬಟರ್","category":"bodybutter","description":"Ultra-smooth body butter formula.","image":"images\\\\Ultra Smooth Butter.jpg","weights":[{"label":"200g","price":520}]},
  {"id":500,"name":"Advanced Foam Cleanser","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ಫೋಮ್ ಕ್ಲೆನ್ಸರ್","category":"bodywash","description":"Advanced foaming body cleanser.","image":"images\\\\Advanced Foam Cleanser.jpg","weights":[{"label":"200ml","price":420}]},
  {"id":501,"name":"Skin Repair Body Wash","nameKn":"ಸ್ಕಿನ್ ರಿಪೇರ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Repairs and nourishes skin.","image":"images\\\\Skin Repair Body Wash 1999.jpg","weights":[{"label":"300ml","price":420}]},
  {"id":502,"name":"Organic Herbal Soap","nameKn":"ಆರ್ಗಾನಿಕ್ ಹರ್ಬಲ್ ಸೋಪ್","category":"soap","description":"Certified organic herbal soap.","image":"images\\\\Herbal Soap 220.jpg","weights":[{"label":"125g","price":220}]},
  {"id":503,"name":"Ultimate Repair Shampoo","nameKn":"ಅಲ್ಟಿಮೇಟ್ ರಿಪೇರ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Ultimate repair for severely damaged hair.","image":"images\\\\Ultimate Repair Shampoo 1100.jpg","weights":[{"label":"200ml","price":1100}]},
  {"id":504,"name":"Ultra Repair Conditioner","nameKn":"ಅಲ್ಟ್ರಾ ರಿಪೇರ್ ಕಂಡೀಷನರ್","category":"conditioner","description":"Ultra-strength repair conditioner.","image":"images\\\\Ultra Repair Conditioner.jpg","weights":[{"label":"200ml","price":360}]},
  {"id":505,"name":"Advanced Nourish Oil","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ನೌರಿಶ್ ಆಯಿಲ್","category":"hairoil","description":"Advanced nourishing hair oil.","image":"images\\\\Advanced Nourish Oil 400.jpg","weights":[{"label":"200ml","price":300}]},
  {"id":506,"name":"Ultimate Shine Serum","nameKn":"ಅಲ್ಟಿಮೇಟ್ ಶೈನ್ ಸೀರಮ್","category":"hairserum","description":"Ultimate shine boosting serum.","image":"images\\\\Ultimate Shine Serum 120.jpg","weights":[{"label":"100ml","price":500}]},
  {"id":507,"name":"Glow Boost Face Wash","nameKn":"ಗ್ಲೋ ಬೂಸ್ಟ್ ಫೇಸ್ ವಾಶ್","category":"facewash","description":"Face wash that boosts skin glow.","image":"images\\\\Glow Boost Face Wash.jpg","weights":[{"label":"150ml","price":300}]},
  {"id":508,"name":"Deep Exfoliating Scrub","nameKn":"ಡೀಪ್ ಎಕ್ಸ್ಫೋಲಿಯೇಟಿಂಗ್ ಸ್ಕ್ರಬ್","category":"scrub","description":"Deep exfoliation for smooth skin.","image":"images\\\\Deep Exfoliating Scrub 180.jpg","weights":[{"label":"100g","price":300}]},
  {"id":509,"name":"Hydra Repair Cream","nameKn":"ಹೈಡ್ರಾ ರಿಪೇರ್ ಕ್ರೀಮ್","category":"cream","description":"Hydrates and repairs skin.","image":"images\\\\Hydra Repair Cream 850.jpg","weights":[{"label":"100g","price":750}]},
  {"id":510,"name":"Ultra Repair Lotion","nameKn":"ಅಲ್ಟ್ರಾ ರಿಪೇರ್ ಲೋಷನ್","category":"lotion","description":"Ultra-strength repair body lotion.","image":"images\\\\Ultra Repair Lotion 2000.jpg","weights":[{"label":"300ml","price":2000}]},
  {"id":511,"name":"Ultra Defense SPF60","nameKn":"ಅಲ್ಟ್ರಾ ಡಿಫೆನ್ಸ್ SPF60","category":"sunscreen","description":"Maximum defense sunscreen.","image":"images\\\\Ultra Defense SPF60 600.jpg","weights":[{"label":"100ml","price":750}]},
  {"id":516,"name":"Hydra Foam Cleanser","nameKn":"ಹೈಡ್ರಾ ಫೋಮ್ ಕ್ಲೆನ್ಸರ್","category":"bodywash","description":"Hydrating foam body cleanser.","image":"images\\\\Hydra Foam Cleanser.jpg","weights":[{"label":"200ml","price":440}]},
  {"id":517,"name":"Ultra Hydrating Wash","nameKn":"ಅಲ್ಟ್ರಾ ಹೈಡ್ರೇಟಿಂಗ್ ವಾಶ್","category":"bodywash","description":"Ultra hydrating body wash.","image":"images\\\\Ultra Hydrating Wash 250.jpg","weights":[{"label":"300ml","price":440}]},
  {"id":518,"name":"Premium Milk Soap","nameKn":"ಪ್ರೀಮಿಯಂ ಮಿಲ್ಕ್ ಸೋಪ್","category":"soap","description":"Premium milk protein soap.","image":"images\\\\Premium Milk Soap.jpg","weights":[{"label":"125g","price":180}]},
  {"id":519,"name":"Keratin Ultra Shampoo","nameKn":"ಕೆರಾಟಿನ್ ಅಲ್ಟ್ರಾ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Keratin ultra-strength shampoo.","image":"images\\\\Keratin Ultra Shampoo 1800.jpg","weights":[{"label":"200ml","price":1800}]},
  {"id":520,"name":"Keratin Ultra Conditioner","nameKn":"ಕೆರಾಟಿನ್ ಅಲ್ಟ್ರಾ ಕಂಡೀಷನರ್","category":"conditioner","description":"Keratin ultra-strength conditioner.","image":"images\\\\Keratin Ultra Conditioner 400.jpg","weights":[{"label":"200ml","price":380}]},
  {"id":521,"name":"Ultra Care Hair Oil","nameKn":"ಅಲ್ಟ್ರಾ ಕೇರ್ ಹೇರ್ ಆಯಿಲ್","category":"hairoil","description":"Ultra care hair oil formula.","image":"images\\\\Ultra Care Hair Oil 1250.jpg","weights":[{"label":"200ml","price":1320}]},
  {"id":522,"name":"Ultra Smooth Serum","nameKn":"ಅಲ್ಟ್ರಾ ಸ್ಮೂತ್ ಸೀರಮ್","category":"hairserum","description":"Ultra-smoothing hair serum.","image":"images\\\\Ultra Smooth Serum 460.jpg","weights":[{"label":"100ml","price":520}]},
  {"id":523,"name":"Daily Care Cleanser","nameKn":"ಡೈಲಿ ಕೇರ್ ಕ್ಲೆನ್ಸರ್","category":"facewash","description":"Daily facial care cleanser.","image":"images\\\\Daily Care Cleanser.jpg","weights":[{"label":"150ml","price":320}]},
  {"id":524,"name":"Advanced Scrub","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ಸ್ಕ್ರಬ್","category":"scrub","description":"Advanced exfoliating scrub.","image":"images\\\\Advanced Scrub 400.jpg","weights":[{"label":"100g","price":320}]},
  {"id":525,"name":"Advanced Repair Cream","nameKn":"ಅಡ್ವಾನ್ಸ್ಡ್ ರಿಪೇರ್ ಕ್ರೀಮ್","category":"cream","description":"Advanced skin repair cream.","image":"images\\\\Advanced Repair Cream 2400.jpg","weights":[{"label":"100g","price":800}]},
  {"id":526,"name":"Hydra Smooth Lotion","nameKn":"ಹೈಡ್ರಾ ಸ್ಮೂತ್ ಲೋಷನ್","category":"lotion","description":"Hydrating smooth body lotion.","image":"images\\\\Hydra Smooth Lotion 2210.jpg","weights":[{"label":"300ml","price":2540}]},
  {"id":527,"name":"Extreme SPF70 Sunscreen","nameKn":"ಎಕ್ಸ್ಟ್ರೀಮ್ SPF70 ಸನ್ ಸ್ಕ್ರೀನ್","category":"sunscreen","description":"Extreme protection SPF70 sunscreen.","image":"images\\\\Extreme SPF70 Sunscreen 500.jpg","weights":[{"label":"100ml","price":800}]},
  {"id":528,"name":"Extreme Fresh Deo","nameKn":"ಎಕ್ಸ್ಟ್ರೀಮ್ ಫ್ರೆಶ್ ಡಿಯೋ","category":"deo","description":"Extreme freshness deodorant.","image":"images\\\\Extreme Fresh Deo.jpg","weights":[{"label":"150ml","price":480}]},
  {"id":529,"name":"Elite Luxury Perfume","nameKn":"ಎಲೈಟ್ ಲಕ್ಷ್ಮಿ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Elite luxury perfume collection.","image":"images\\\\Elite Luxury Perfume.jpg","weights":[{"label":"100ml","price":7000}]},
  {"id":530,"name":"Premium Nourish Butter","nameKn":"ಪ್ರೀಮಿಯಂ ನೌರಿಶ್ ಬಟರ್","category":"bodybutter","description":"Premium nourishing body butter.","image":"images\\\\Premium Nourish Butter.jpg","weights":[{"label":"200g","price":600}]},
  {"id":531,"name":"Deep Nourish Mask","nameKn":"ಡೀಪ್ ನೌರಿಶ್ ಮಾಸ್ಕ್","category":"hairmask","description":"Deep nourishing hair mask.","image":"images\\\\Deep Nourish Cream Mask.jpg","weights":[{"label":"200g","price":650}]},
  {"id":532,"name":"Sensitive Foam Cleanser","nameKn":"ಸೆನ್ಸಿಟಿವ್ ಫೋಮ್ ಕ್ಲೆನ್ಸರ್","category":"bodywash","description":"For sensitive skin foam cleanser.","image":"images\\\\Sensitive Foam Cleanser.jpg","weights":[{"label":"200ml","price":460}]},
  {"id":533,"name":"Hydra Silk Body Wash","nameKn":"ಹೈಡ್ರಾ ಸಿಲ್ಕ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Silky smooth hydrating body wash.","image":"images\\\\Hydra Silk Body Wash 250.jpg","weights":[{"label":"300ml","price":460}]},
  {"id":534,"name":"Ultra Smooth Soap","nameKn":"ಅಲ್ಟ್ರಾ ಸ್ಮೂತ್ ಸೋಪ್","category":"soap","description":"Ultra-smooth moisturizing soap.","image":"images\\\\Ultra Smooth Soap 90.jpg","weights":[{"label":"125g","price":90}]},
  {"id":535,"name":"Ultimate Care Shampoo","nameKn":"ಅಲ್ಟಿಮೇಟ್ ಕೇರ್ ಶ್ಯಾಂಪೂ","category":"shampoo","description":"Ultimate care for all hair types.","image":"images\\\\Ultimate Care Shampoo 4750.jpg","weights":[{"label":"ml","price":4750}]},
  {"id":536,"name":"Ultimate Care Conditioner","nameKn":"ಅಲ್ಟಿಮೇಟ್ ಕೇರ್ ಕಂಡೀಷನರ್","category":"conditioner","description":"Ultimate care conditioner.","image":"images\\\\Ultimate Care Conditioner.jpg","weights":[{"label":"200ml","price":400}]},
  {"id":537,"name":"Premium Care Hair Oil","nameKn":"ಪ್ರೀಮಿಯಂ ಕೇರ್ ಹೇರ್ ಆಯಿಲ್","category":"hairoil","description":"Premium care hair oil.","image":"images\\\\Premium Care Hair Oil 4700.jpg","weights":[{"label":"200ml","price":4740}]},
  {"id":543,"name":"Extreme Defense SPF80","nameKn":"ಎಕ್ಸ್ಟ್ರೀಮ್ ಡಿಫೆನ್ಸ್ SPF80","category":"sunscreen","description":"Extreme defense high protection sunscreen.","image":"images\\\\Extreme Defense SPF80 790.jpg","weights":[{"label":"100ml","price":800}]},
  {"id":544,"name":"Ultra Active Deo","nameKn":"ಅಲ್ಟ್ರಾ ಆಕ್ಟಿವ್ ಡಿಯೋ","category":"deo","description":"Ultra active deodorant for sports.","image":"images\\\\Ultra Active Deo.jpg","weights":[{"label":"150ml","price":500}]},
  {"id":545,"name":"Signature Elite Perfume","nameKn":"ಸಿಗ್ನೇಚರ್ ಎಲೈಟ್ ಪರ್ಫ್ಯೂಮ್","category":"perfume","description":"Signature elite luxury perfume.","image":"images\\\\Signature Elite Perfume.jpg","weights":[{"label":"100ml","price":8000}]},
  {"id":546,"name":"Ultimate Butter","nameKn":"ಅಲ್ಟಿಮೇಟ್ ಬಟರ್","category":"bodybutter","description":"Ultimate body butter for deep moisture.","image":"images\\\\Ultimate Butter.jpg","weights":[{"label":"200g","price":650}]},
  {"id":547,"name":"Premium Repair Mask","nameKn":"ಪ್ರೀಮಿಯಂ ರಿಪೇರ್ ಮಾಸ್ಕ್","category":"hairmask","description":"Premium hair repair mask.","image":"images\\\\Premium Repair Mask.jpg","weights":[{"label":"200g","price":700}]},
  {"id":548,"name":"Ultra Soft Foam Cleanser","nameKn":"ಅಲ್ಟ್ರಾ ಸಾಫ್ಟ್ ಫೋಮ್ ಕ್ಲೆನ್ಸರ್","category":"bodywash","description":"Ultra soft foam for gentle cleansing.","image":"images\\\\Hydra Silk Body Wash 250.jpg","weights":[{"label":"100ml","price":280}]},
  {"id":549,"name":"Ultra Care Body Wash","nameKn":"ಅಲ್ಟ್ರಾ ಕೇರ್ ಬಾಡಿ ವಾಶ್","category":"bodywash","description":"Ultimate care body wash formula.","image":"images\\\\Ultra Care Body Wash 4700.jpg","weights":[{"label":"300ml","price":480}]},
  {"id":550,"name":"Premium Herbal Soap","nameKn":"ಪ್ರೀಮಿಯಂ ಹರ್ಬಲ್ ಸೋಪ್","category":"soap","description":"Premium herbal soap.","image":"images\\\\Premium Herbal Soap 630.jpg","weights":[{"label":"125g","price":630}]}
]';
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    $result = [];
    foreach ($data as $item) {
        // Determine category for frontend filter
        $subcategory = 'soaps';
        $cat = strtolower($item['category'] ?? '');
        if (strpos($cat, 'wash') !== false || strpos($cat, 'shower') !== false || strpos($cat, 'foam') !== false || strpos($cat, 'bodywash') !== false) {
            $subcategory = 'wash';
        } elseif (strpos($cat, 'hair') !== false || strpos($cat, 'oil') !== false || strpos($cat, 'shampoo') !== false || strpos($cat, 'conditioner') !== false || strpos($cat, 'serum') !== false || strpos($cat, 'mask') !== false) {
            $subcategory = 'hair';
        } elseif (strpos($cat, 'soap') !== false || strpos($cat, 'bar') !== false) {
            $subcategory = 'soaps';
        } else {
            $name = strtolower($item['name'] ?? '');
            if (strpos($name, 'soap') !== false || strpos($name, 'bar') !== false) {
                $subcategory = 'soaps';
            } elseif (strpos($name, 'wash') !== false || strpos($name, 'shower') !== false || strpos($name, 'foam') !== false) {
                $subcategory = 'wash';
            } elseif (strpos($name, 'hair') !== false || strpos($name, 'oil') !== false || strpos($name, 'shampoo') !== false || strpos($name, 'conditioner') !== false) {
                $subcategory = 'hair';
            }
        }
        $firstWeight = $item['weights'][0] ?? ['price' => 0];
        $price = $firstWeight['price'];
        // Convert image path to web path
        $image = $item['image'] ?? '';
        $image = str_replace('D:\\KSR Guru JI\\', '', $image);
        $image = str_replace('\\', '/', $image);
        if ($image && !str_starts_with($image, 'http') && !str_starts_with($image, 'data:')) {
            $image = '../' . ltrim($image, '/');
        }
        $result[] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'kn_name' => $item['nameKn'] ?? $item['name'],
            'price' => $price,
            'category' => $subcategory,
            'emoji' => '🧴',
            'category_name' => $item['category'] ?? '',
            'description_en' => $item['description'] ?? '',
            'variants' => $item['weights'] ?? [],
            'image' => $image
        ];
    }
    $fallback = $result;
    return $fallback;
}
// ============================================================
// Get products: Try DB first, then fallback to static
// ============================================================
$products = [];
// If DB connection exists, try to fetch
if (isset($pdo)) {
    $products = getBathBodyProducts();
}
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes">
    <title>ಬಾಥ್ ಮತ್ತು ಬೋಡಿ · Bath & Body · KSR Guru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ===== SAME STYLES AS YOUR ORIGINAL ===== */
        :root {
            --bg: #f5f8f6;
            --card: #ffffff;
            --border: #e2ede5;
            --text: #223326;
            --text-sub: #495e50;
            --accent: #6b9f7a;
            --accent-dark: #4d7a5b;
            --green: #2a7541;
            --danger: #cf4f4f;
            --radius: 20px;
            --pill-radius: 40px;
            --shadow: 0 8px 25px rgba(30, 60, 40, 0.05);
            --transition: 0.2s ease;
            --top-nav-bg: #fbfdfc;
            --user-bg: #ebf3ee;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Noto Sans', sans-serif; }
        body { display: block; }
        .top-nav { background: var(--top-nav-bg); border-bottom: 1px solid #d2e4d7; padding: 0.6rem 1.2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.8rem; position: sticky; top: 0; z-index: 100; width: 100%; box-shadow: 0 2px 12px rgba(0,0,0,0.03); }
        .nav-brand { font-weight: 800; font-size: 1.3rem; background: linear-gradient(135deg, #2a7541, #6b9f7a); -webkit-background-clip: text; background-clip: text; color: transparent; display: flex; align-items: center; gap: 8px; white-space: nowrap; }
        .nav-brand i { color: #6b9f7a; background: none; -webkit-background-clip: unset; background-clip: unset; font-size: 1.4rem; }
        .nav-center { display: flex; align-items: center; gap: 0.6rem; flex: 1; max-width: 500px; min-width: 200px; }
        .nav-search { display: flex; align-items: center; background: white; border-radius: 50px; padding: 0.15rem 0.15rem 0.15rem 1rem; border: 1px solid #c8ded0; flex: 1; min-width: 160px; }
        .nav-search i { color: #8cb89a; font-size: 0.9rem; }
        .nav-search input { flex: 1; border: none; padding: 0.6rem 0.5rem; font-size: 0.9rem; background: transparent; outline: none; min-width: 100px; }
        .nav-search button { background: #6b9f7a; border: none; color: white; padding: 0.55rem 1.1rem; border-radius: 50px; font-size: 0.85rem; font-weight: 600; cursor: pointer; white-space: nowrap; border: 1px solid #568463; transition: var(--transition); }
        .nav-search button:hover { background: #568463; }
        .user-cart-info { display: flex; align-items: center; gap: 1rem; flex-shrink: 0; }
        .user-info { display: flex; align-items: center; gap: 0.5rem; background: var(--user-bg); padding: 0.5rem 1rem; border-radius: 50px; cursor: pointer; font-weight: 600; font-size: 0.85rem; color: var(--text); border: 1px solid #bed6c7; white-space: nowrap; transition: var(--transition); }
        .user-info:hover { background: #daf0e2; }
        .user-info i { font-size: 1.2rem; color: #6b9f7a; }
        .cart-info { position: relative; background: white; padding: 0.5rem 0.9rem; border-radius: 50px; border: 1px solid #c8ded0; display: flex; align-items: center; gap: 6px; transition: var(--transition); white-space: nowrap; cursor: pointer; }
        .cart-info:hover { background: #f5fdf8; border-color: #a4cdb1; }
        .cart-info a { display: flex; align-items: center; gap: 6px; text-decoration: none; color: inherit; font-weight: 600; font-size: 0.85rem; }
        .cart-info i { font-size: 1.3rem; color: #355e42; }
        .cart-count { background: #2a7541; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; position: absolute; top: -6px; right: -6px; }
        .main-container { max-width: 1400px; width: 100%; padding: 0 1rem; }
        .category-section { margin: 1.2rem 0 0.8rem; }
        .category-tabs { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; }
        .category-btn { background: #ebf3ee; color: #355e42; padding: 0.55rem 1.3rem; border-radius: 40px; font-size: 0.9rem; font-weight: 500; border: 1px solid #bed6c7; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: var(--transition); user-select: none; white-space: nowrap; }
        .category-btn:hover { background: #d7edd8; }
        .category-btn.active { background: var(--accent); color: white; border-color: var(--accent-dark); font-weight: 600; }
        .category-btn i { font-size: 0.85rem; }
        .controls-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.8rem; margin: 0.8rem 0 1.2rem; }
        .controls-row select { padding: 0.5rem 1rem; border-radius: 30px; border: 1px solid #bed6c7; background: white; font-size: 0.85rem; font-weight: 500; cursor: pointer; color: var(--text); outline: none; transition: var(--transition); }
        .controls-row select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(107,159,122,0.08); }
        .result-count { font-size: 0.85rem; color: var(--text-sub); font-weight: 500; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.4rem; margin: 1rem 0 2rem; }
        .product-card { background: var(--card); border-radius: var(--radius); padding: 1.3rem; box-shadow: var(--shadow); border: 1px solid var(--border); position: relative; transition: var(--transition); display: flex; flex-direction: column; align-items: center; text-align: center; cursor: default; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 16px 30px rgba(25,50,35,0.09); border-color: #bed6c7; }
        .product-img { width: 110px; height: 110px; border-radius: 55px; overflow: hidden; background: #e8f4ec; margin: 0 auto 0.8rem; border: 3px solid #d2e8d9; box-shadow: 0 6px 12px rgba(0,0,0,0.03); transition: transform 0.2s; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 3rem; }
        .product-img img { width: 100%; height: 100%; object-fit: cover; }
        .product-card:hover .product-img { transform: scale(1.03); }
        .product-category-tag { font-size: 0.7rem; text-transform: uppercase; color: #4d7a5b; font-weight: 700; margin-bottom: 0.15rem; letter-spacing: 1px; }
        .product-name { font-size: 1.2rem; font-weight: 700; color: #15281a; margin: 0.3rem 0 0.1rem; line-height: 1.3; }
        .product-name-kn { font-size: 0.85rem; color: #364e3c; border-bottom: 1px dotted #c9decb; padding-bottom: 0.35rem; margin-bottom: 0.5rem; display: inline-block; }
        .price-area { display: flex; gap: 0.6rem; align-items: baseline; justify-content: center; margin: 0.4rem 0; }
        .price { font-size: 1.4rem; font-weight: 700; color: var(--green); }
        .add-to-cart-btn { background: #c5edd1; border: none; border-radius: 40px; padding: 0.7rem 1rem; font-size: 0.9rem; font-weight: 600; color: #12331d; margin-top: 0.4rem; cursor: pointer; border: 1px solid #a3dbb4; display: flex; align-items: center; gap: 7px; width: 100%; justify-content: center; transition: var(--transition); white-space: nowrap; }
        .add-to-cart-btn:hover { background: #b0e5be; }
        .add-to-cart-btn.added-flash { background: #aef0a3 !important; border-color: #8cde7e !important; color: #1b4d12 !important; }
        .no-results { text-align: center; padding: 3rem; background: #ebf3ee; border-radius: 30px; color: #495e50; display: none; grid-column: 1 / -1; }
        .cart-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.35); backdrop-filter: blur(3px); align-items: center; justify-content: center; z-index: 1000; }
        .cart-panel { background: #fbfcfa; max-width: 440px; width: 92%; max-height: 82vh; border-radius: 28px; padding: 1.5rem; border: 1px solid #bed6c7; overflow-y: auto; box-shadow: 0 20px 50px rgba(20,40,30,0.12); }
        .cart-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #bed6c7; padding-bottom: 0.7rem; font-size: 1.3rem; font-weight: 700; color: var(--text); }
        .close-cart { background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #6b9f7a; padding: 0; line-height: 1; }
        .cart-items-list { list-style: none; margin: 0.8rem 0; max-height: 340px; overflow-y: auto; }
        .cart-item { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 0.8rem; padding: 0.5rem 0.7rem; background: #eef5f0; border-radius: 50px; border: 1px solid #d2e4d7; }
        .cart-item-icon { width: 44px; height: 44px; background: #daf0e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; font-size: 1.5rem; }
        .cart-item-details { flex: 1; min-width: 0; }
        .cart-item-name { font-weight: 600; color: #1a2f20; font-size: 0.85rem; }
        .cart-item-price { font-weight: 600; color: var(--green); font-size: 0.85rem; }
        .cart-item-actions { display: flex; align-items: center; gap: 0.4rem; flex-shrink: 0; }
        .cart-item-actions button { background: #daf0e2; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; font-weight: 700; font-size: 0.9rem; transition: var(--transition); border: 1px solid #bed6c7; }
        .cart-item-actions button:hover { background: #bed6c7; }
        .cart-total-row { font-size: 1.3rem; font-weight: 700; border-top: 2px solid #bed6c7; padding-top: 0.8rem; text-align: right; color: var(--green); margin-bottom: 0.6rem; }
        .cart-actions-row { display: flex; flex-direction: column; gap: 0.5rem; }
        .btn-cart-action { width: 100%; padding: 0.75rem; border-radius: 50px; font-size: 1rem; font-weight: 600; cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; gap: 8px; transition: var(--transition); }
        .btn-checkout-local { background: var(--accent); color: white; border: 1px solid var(--accent-dark); }
        .btn-checkout-local:hover { background: var(--accent-dark); }
        .btn-clear-cart { background: transparent; border: 1px solid #bed6c7; color: #495e50; font-size: 0.85rem; padding: 0.5rem; }
        .btn-clear-cart:hover { background: #fee2e2; border-color: #d64045; color: #d64045; }
        .cart-divider-text { text-align: center; color: #8cb89a; font-size: 0.78rem; margin: 0.3rem 0; }
        .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); color: white; padding: 10px 22px; border-radius: 60px; font-size: 0.82rem; z-index: 2000; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.15); white-space: nowrap; animation: slideUpToast 0.3s ease; display: flex; align-items: center; gap: 7px; }
        @keyframes slideUpToast { from { opacity: 0; transform: translateX(-50%) translateY(20px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
        .toast.success { background: #1b7a36; }
        .toast.error { background: #c63939; }
        .footer-note { text-align: center; color: #495e50; border-top: 1px solid #c9decb; padding: 1.5rem 0; margin-top: 1rem; font-size: 0.85rem; width: 100%; }
        .footer-note a { color: #2a7541; font-weight: 600; text-decoration: none; }
        .footer-note a:hover { text-decoration: underline; }
        @media (max-width: 768px) {
            .top-nav { flex-direction: column; align-items: stretch; gap: 0.6rem; padding: 0.6rem 0.9rem; }
            .nav-center { max-width: 100%; order: 3; }
            .user-cart-info { justify-content: space-between; order: 2; }
            .nav-brand { order: 1; justify-content: center; }
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        }
        @media (max-width: 480px) {
            .products-grid { grid-template-columns: repeat(2, 1fr); gap: 0.7rem; }
            .product-card { padding: 0.9rem; }
            .category-btn { padding: 0.4rem 0.9rem; font-size: 0.78rem; }
        }
    </style>
  <link rel="stylesheet" href="category-style.css">
    <link rel="stylesheet" href="../index.css">
</head>
<body>
<?php
  $base_url = '../';
  include __DIR__ . '/../includes/global-header.php';
?>
    <div class="main-container">
        <div class="category-section">
            <div class="category-tabs" id="categoryButtons">
                <button class="category-btn active" data-subcategory="all"><i class="fa-solid fa-star"></i> ಎಲ್ಲಾ | All</button>
                <button class="category-btn" data-subcategory="soaps"><i class="fa-solid fa-soap"></i> Soaps</button>
                <button class="category-btn" data-subcategory="wash"><i class="fa-solid fa-shower"></i> Body Wash</button>
                <button class="category-btn" data-subcategory="hair"><i class="fa-solid fa-spa"></i> Hair Care</button>
            </div>
        </div>
        <div class="controls-row">
            <select id="sortFilter">
                <option value="default">Sort By - Default</option>
                <option value="price-low">💰 Price: Low to High</option>
                <option value="price-high">💰 Price: High to Low</option>
                <option value="name">🔤 Name: A to Z</option>
            </select>
            <span class="result-count" id="resultCount">Showing all products</span>
        </div>
        <div class="products-grid" id="productsGrid">
            <?php if (empty($products)): ?>
                    <div class="no-results" style="display: block;">
                        <i class="fa-regular fa-face-frown"></i> ಯಾವುದೇ ಉತ್ಪನ್ನಗಳು ಕಂಡುಬಂದಿಲ್ಲ / No products found
                    </div>
            <?php else: ?>
                    <?php foreach ($products as $p): ?>
                                <div class="product-card" data-sub="<?php echo $p['category']; ?>" data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>">
                                    <div class="product-img">
                                        <?php if (!empty($p['image']) && file_exists(__DIR__ . '/' . $p['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($p['emoji'] ?? '🧴'); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-category-tag"><?php echo htmlspecialchars($p['category_name'] ?? ''); ?></div>
                                    <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <div class="product-name-kn"><?php echo htmlspecialchars($p['kn_name'] ?? ''); ?></div>
                                    <div class="price-area">
                                        <span class="price">₹<?php echo number_format($p['price'], 2); ?></span>
                                    </div>
                                    <button class="add-to-cart-btn" data-id="<?php echo $p['id']; ?>" onclick="window.addToCart(<?php echo $p['id']; ?>)">
                                        <i class="fa-solid fa-cart-plus"></i> Add to Cart
                                    </button>
                                </div>
                    <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="no-results" id="noResultsMsg">
            <i class="fa-regular fa-face-frown"></i> ಯಾವುದೇ ಉತ್ಪನ್ನಗಳು ಕಂಡುಬಂದಿಲ್ಲ / No products found
        </div>
    </div>
    <div class="cart-overlay" id="cartOverlay">
        <div class="cart-panel">
            <div class="cart-header">
                <span><i class="fa-solid fa-cart-shopping"></i> ನಿಮ್ಮ ಕಾರ್ಟ್ / Your Cart</span>
                <button class="close-cart" id="closeCartBtn">&times;</button>
            </div>
            <ul class="cart-items-list" id="cartItemsList"></ul>
            <div class="cart-total-row" id="cartTotal">₹0</div>
            <div class="cart-actions-row">
                <button class="btn-cart-action btn-checkout-local" id="checkoutLocalBtn">
                    <i class="fa-solid fa-check-circle"></i> Quick Checkout
                </button>
                <div class="cart-divider-text">OR</div>
                <button class="btn-cart-action btn-clear-cart" id="clearCartBtn">
                    <i class="fa-solid fa-trash-alt"></i> Clear Cart
                </button>
            </div>
        </div>
    </div>
    <div class="footer-note">
        <i class="fa-regular fa-tag"></i> 100% Brand Guarantee &amp; Secure Payments
        <br><small>🛒 <a href="../index.php">KSR Guru JI</a> — Back to Store</small>
    </div>
    <script>
    (function() {
        let cart = [];
        const basePath = 'bath-body.php';
        const grid = document.getElementById('productsGrid');
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');
        const noResults = document.getElementById('noResultsMsg');
        const cartCountEl = document.getElementById('cartCount');
        const resultCount = document.getElementById('resultCount');
        const sortFilter = document.getElementById('sortFilter');
        const categoryButtons = document.querySelectorAll('.category-btn');
        let currentProducts = <?php echo json_encode($products); ?>;
        function loadCart() {
            fetch('../index.php?ajax=cart')
                .then(r => r.json())
                .then(data => { cart = data; updateCartBadge(); renderCartModal(); })
                .catch(console.error);
        }
        function updateCartBadge() {
            const cartArray = Array.isArray(cart) ? cart : (cart ? Object.values(cart) : []);
            if(cartCountEl) cartCountEl.innerText = cartArray.reduce((acc, i) => acc + (i.qty || 1), 0);
        }
        function addToCart(productId) {
            let product = null;
            if (typeof products !== 'undefined' && Array.isArray(products)) {
                product = products.find(p => p.id === productId);
            } else if (typeof productsData !== 'undefined' && Array.isArray(productsData)) {
                product = productsData.find(p => p.id === productId);
            } else if (typeof productDatabase !== 'undefined' && Array.isArray(productDatabase)) {
                product = productDatabase.find(p => p.id === productId);
            } else if (typeof currentProducts !== 'undefined' && Array.isArray(currentProducts)) {
                product = currentProducts.find(p => p.id === productId);
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
            .then(res => res.text())
            .then(text => {
                let data;
                try {
                    const jsonStart = text.indexOf('{');
                    if (jsonStart !== -1) {
                        data = JSON.parse(text.substring(jsonStart));
                    } else {
                        throw new Error('Invalid JSON');
                    }
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
                    
                    // Visual feedback on the button itself for multiple rapid clicks
                    const btn = document.querySelector(`button.add-to-cart-btn[data-id="${productId}"]`);
                    if (btn) {
                        const originalHtml = btn.innerHTML;
                        btn.innerHTML = '<i class="fa-solid fa-check"></i> Added';
                        btn.classList.add('added-flash');
                        setTimeout(() => {
                            btn.innerHTML = originalHtml;
                            btn.classList.remove('added-flash');
                        }, 2000);
                    }
                    
                    // Notify background state to re-fetch if sidebar is open, but do not aggressively slide out
                    if (typeof loadGlobalCart === "function") {
                        loadGlobalCart();
                    }
            if (typeof openCart === 'function') { openCart(); }

                } else {
                    alert(data.error || 'Failed to add product');
                }
            })
            .catch(err => {
                console.error(err);
                alert("Error adding product to cart.");
            });
        }

        // Search & Filter functionality
        function filterAndSortProducts() {
            const term = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const activeBtn = document.querySelector('.category-btn.active');
            const subcat = activeBtn ? activeBtn.dataset.subcategory : 'all';
            const sortVal = sortFilter.value;
            let filtered = currentProducts.filter(p => {
                const matchesSearch = p.name.toLowerCase().includes(term) || (p.kn_name && p.kn_name.toLowerCase().includes(term));
                if (!matchesSearch) return false;
                if (subcat === 'all') return true;
                if (subcat === 'soaps') {
                    return p.category === 'soaps';
                }
                if (subcat === 'wash') {
                    return p.category === 'wash';
                }
                if (subcat === 'hair') {
                    return p.category === 'hair';
                }
                return true;
            });
            // Sort products
            if (sortVal === 'price-low') {
                filtered.sort((a,b) => parseFloat(a.price) - parseFloat(b.price));
            } else if (sortVal === 'price-high') {
                filtered.sort((a,b) => parseFloat(b.price) - parseFloat(a.price));
            } else if (sortVal === 'name') {
                filtered.sort((a, b) => a.name.localeCompare(b.name));
            }
            // Render
            const cards = grid.querySelectorAll('.product-card');
            if (filtered.length === 0) {
                noResults.style.display = 'block';
                // Hide all cards
                cards.forEach(c => c.style.display = 'none');
                resultCount.innerText = 'Showing 0 products';
            } else {
                noResults.style.display = 'none';
                resultCount.innerText = `Showing ${filtered.length} products`;
                // Construct mapping of id to displaying index or build standard HTML:
                let html = '';
                filtered.forEach(p => {
                    let imgHtml = p.emoji || '🧴';
                    if (p.image) {
                        imgHtml = `<img src="${p.image}" alt="" style="width:100%;height:100%;object-fit:cover;">`;
                    }
                    html += `
                        <div class="product-card">
                            <div class="product-img">${imgHtml}</div>
                            <div class="product-category-tag">${p.category_name || ''}</div>
                            <div class="product-name">${p.name}</div>
                            <div class="product-name-kn">${p.kn_name || ''}</div>
                            <div class="price-area">
                                <span class="price">₹${parseFloat(p.price).toFixed(2)}</span>
                            </div>
                            <button class="add-to-cart-btn" data-id="${p.id}" onclick="window.addToCart(${p.id})">
                                <i class="fa-solid fa-cart-plus"></i> Add to Cart
                            </button>
                        </div>
                    `;
                });
                grid.innerHTML = html;
            }
        }

        // Search trigger
        if(searchInput) searchInput.addEventListener('input', filterAndSortProducts);
        if(searchBtn) searchBtn.addEventListener('click', filterAndSortProducts);
        if(sortFilter) sortFilter.addEventListener('change', filterAndSortProducts);
        // Category pills trigger
        categoryButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                categoryButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterAndSortProducts();
            });
        });
        window.addToCart = addToCart;
        loadCart();
        filterAndSortProducts();
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


  <?php
  $base_url = '../';
  $currentCategory = 'bath-body';
  include __DIR__ . '/../includes/related-section.php';
?>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
<script src="sorting.js"></script>
</body>
</html>

