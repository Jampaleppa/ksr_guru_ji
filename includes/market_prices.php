<?php
// ============================================================
// includes/market_prices.php - Daily Market Price & Selling Price System
// ============================================================
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Standard default profit margin (₹3.00 as requested: Selling price = Market price + ₹3)
 */
if (!defined('KSR_DEFAULT_PRICE_MARGIN')) {
    define('KSR_DEFAULT_PRICE_MARGIN', 3.00);
}

/**
 * Ensures daily_market_prices and market_price_history tables exist
 */
function ensureMarketPriceTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_market_prices (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        product_code   TEXT UNIQUE,
        name           TEXT NOT NULL,
        name_kn        TEXT,
        category       TEXT DEFAULT 'indianMain',
        description    TEXT,
        image          TEXT,
        unit_type      TEXT DEFAULT 'kg', -- 'kg', 'piece', 'packet', 'bunch', 'dozen'
        packet_sizes   TEXT DEFAULT '100g,250g,500g,1kg',
        market_price   REAL NOT NULL DEFAULT 0.00,
        profit_margin  REAL NOT NULL DEFAULT 3.00,
        selling_price  REAL NOT NULL DEFAULT 3.00,
        price_date     TEXT DEFAULT (date('now', 'localtime')),
        is_available   INTEGER DEFAULT 1,
        notes          TEXT,
        created_at     TEXT DEFAULT (datetime('now', 'localtime')),
        updated_at     TEXT DEFAULT (datetime('now', 'localtime'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS market_price_history (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id     INTEGER,
        product_name   TEXT NOT NULL,
        unit_type      TEXT DEFAULT 'kg',
        market_price   REAL NOT NULL,
        profit_margin  REAL NOT NULL DEFAULT 3.00,
        selling_price  REAL NOT NULL,
        effective_date TEXT NOT NULL,
        updated_by     TEXT DEFAULT 'Admin',
        notes          TEXT,
        created_at     TEXT DEFAULT (datetime('now', 'localtime'))
    )");
}

/**
 * Initial Catalog of Vegetables & Fruits with appropriate units and default base market rates
 */
function getDefaultMarketCatalog(): array
{
    return [
        // --- Popular Fruits ---
        ['name' => 'Apple', 'name_kn' => 'ಸೇಬು', 'category' => 'globalFruits', 'desc' => 'Crisp & sweet', 'image' => 'images/Apple.jpg', 'unit_type' => 'kg', 'market_price' => 177.00],
        ['name' => 'GreenApple', 'name_kn' => 'ಹಸಿರು ಸೇಬು', 'category' => 'globalFruits', 'desc' => 'Tangy crisp', 'image' => 'images/Green Apple.jpg', 'unit_type' => 'kg', 'market_price' => 217.00],
        ['name' => 'Banana', 'name_kn' => 'ಬಾಳೆ ಹಣ್ಣು', 'category' => 'indianFruits', 'desc' => 'Energy booster', 'image' => 'images/Banana.jpg', 'unit_type' => 'dozen', 'market_price' => 47.00],
        ['name' => 'Banana small', 'name_kn' => 'ಸಣ್ಣ ಬಾಳೆಹಣ್ಣು', 'category' => 'indianFruits', 'desc' => 'Miniature sweet bananas (ಏಲಕ್ಕಿ ಬಾಳೆ)', 'image' => 'images/Banana small.jpg', 'unit_type' => 'dozen', 'market_price' => 67.00],
        ['name' => 'Orange', 'name_kn' => 'ಕಿತ್ತಳೆ', 'category' => 'dailyFruits', 'desc' => 'Juicy & tangy', 'image' => 'images/Orange.jpg', 'unit_type' => 'kg', 'market_price' => 87.00],
        ['name' => 'Mango', 'name_kn' => 'ಮಾವಿನ ಹಣ್ಣು', 'category' => 'indianFruits', 'desc' => 'King of fruits', 'image' => 'images/Mango.jpg', 'unit_type' => 'kg', 'market_price' => 147.00],
        ['name' => 'Grapes', 'name_kn' => 'ದ್ರಾಕ್ಷಿ', 'category' => 'globalFruits', 'desc' => 'Seedless green', 'image' => 'images/Grapes.jpg', 'unit_type' => 'kg', 'market_price' => 117.00],
        ['name' => 'Black Grapes', 'name_kn' => 'ಕಪ್ಪು ದ್ರಾಕ್ಷಿ', 'category' => 'globalFruits', 'desc' => 'Sweet dark grapes', 'image' => 'images/Black Grapes.jpg', 'unit_type' => 'kg', 'market_price' => 177.00],
        ['name' => 'Pineapple', 'name_kn' => 'ಅನಾನಸ್', 'category' => 'dailyFruits', 'desc' => 'Sweet & sour', 'image' => 'images/Pineapple.jpg', 'unit_type' => 'piece', 'market_price' => 77.00],
        ['name' => 'Papaya', 'name_kn' => 'ಪಪ್ಪಾಯಿ', 'category' => 'indianFruits', 'desc' => 'Digestive aid', 'image' => 'images/Papaya.jpg', 'unit_type' => 'kg', 'market_price' => 47.00],
        ['name' => 'Watermelon', 'name_kn' => 'ಕಲ್ಲಂಗಡಿ', 'category' => 'dailyFruits', 'desc' => 'Hydrating fresh fruit', 'image' => 'images/Watermelon.jpg', 'unit_type' => 'kg', 'market_price' => 32.00],
        ['name' => 'Muskmelon', 'name_kn' => 'ಕರಬೂಜ', 'category' => 'dailyFruits', 'desc' => 'Aromatic & sweet', 'image' => 'images/Muskmelon.jpg', 'unit_type' => 'kg', 'market_price' => 57.00],
        ['name' => 'Guava', 'name_kn' => 'ಪೇರಲ', 'category' => 'indianFruits', 'desc' => 'Rich in vitamin C', 'image' => 'images/Guava.jpg', 'unit_type' => 'kg', 'market_price' => 67.00],
        ['name' => 'Guava red', 'name_kn' => 'ಕೆಂಪು ಪೇರಲ', 'category' => 'indianFruits', 'desc' => 'Sweet red-fleshed guava', 'image' => 'images/Guava red.jpg', 'unit_type' => 'kg', 'market_price' => 107.00],
        ['name' => 'Pomegranate', 'name_kn' => 'ದಾಳಿಂಬೆ', 'category' => 'globalFruits', 'desc' => 'Antioxidant rich pearls', 'image' => 'images/Pomegranate.jpg', 'unit_type' => 'kg', 'market_price' => 157.00],
        ['name' => 'Kiwi', 'name_kn' => 'ಕಿವಿ', 'category' => 'globalFruits', 'desc' => 'Vitamin C powerhouse', 'image' => 'images/Kiwi.jpg', 'unit_type' => 'piece', 'market_price' => 32.00],
        ['name' => 'Tender Coconut', 'name_kn' => 'ಎಳೆ ನೀರಿನ ತೆಂಗು', 'category' => 'dailyFruits', 'desc' => 'Fresh coconut water & malai', 'image' => 'images/Tender Coconut.jpg', 'unit_type' => 'piece', 'market_price' => 47.00],
        ['name' => 'Lime', 'name_kn' => 'ನಿಂಬೆ', 'category' => 'dailyFruits', 'desc' => 'Tart green fresh lemon', 'image' => 'images/Lime.jpg', 'unit_type' => 'piece', 'market_price' => 4.00],
        ['name' => 'Dragon Fruit', 'name_kn' => 'ಡ್ರಾಗನ್ ಹಣ್ಣು', 'category' => 'exotic', 'desc' => 'Vibrant pink superfood', 'image' => 'images/Dragon Fruit.jpg', 'unit_type' => 'piece', 'market_price' => 77.00],
        ['name' => 'Strawberry', 'name_kn' => 'ಸ್ಟ್ರಾಬೆರಿ', 'category' => 'globalFruits', 'desc' => 'Sweet, juicy red berries', 'image' => 'images/Strawberry.jpg', 'unit_type' => 'packet', 'packet_sizes' => '200g,400g,1kg', 'market_price' => 107.00],
        ['name' => 'Sapota (Chikoo)', 'name_kn' => 'ಸಪೋಟ', 'category' => 'indianFruits', 'desc' => 'Sweet brown sapodilla', 'image' => 'images/Sapota (Chikoo).jpg', 'unit_type' => 'kg', 'market_price' => 67.00],
        ['name' => 'Custard Apple', 'name_kn' => 'ಸೀತಾಫಲ', 'category' => 'indianFruits', 'desc' => 'Creamy sweet pulp', 'image' => 'images/Custard Apple.jpg', 'unit_type' => 'kg', 'market_price' => 147.00],
        ['name' => 'Jackfruit', 'name_kn' => 'ಹಲಸು', 'category' => 'indianFruits', 'desc' => 'Sweet golden carpels', 'image' => 'images/Jackfruit.jpg', 'unit_type' => 'kg', 'market_price' => 77.00],
        ['name' => 'Amla', 'name_kn' => 'ನೆಲ್ಲಿ', 'category' => 'indianFruits', 'desc' => 'Immunity booster gooseberry', 'image' => 'images/Amla.jpg', 'unit_type' => 'kg', 'market_price' => 77.00],

        // --- Everyday Vegetables ---
        ['name' => 'Potato', 'name_kn' => 'ಆಲೂಗಡ್ಡೆ', 'category' => 'indianMain', 'desc' => 'Fresh farm potatoes', 'image' => 'images/Potato.jpg', 'unit_type' => 'kg', 'market_price' => 22.00],
        ['name' => 'Onion', 'name_kn' => 'ಈರುಳ್ಳಿ', 'category' => 'indianMain', 'desc' => 'Quality red onions', 'image' => 'images/Onion.jpg', 'unit_type' => 'kg', 'market_price' => 27.00],
        ['name' => 'Tomato', 'name_kn' => 'ಟೊಮಾಟೊ', 'category' => 'indianMain', 'desc' => 'Ripe red country & hybrid tomatoes', 'image' => 'images/Tomato.jpg', 'unit_type' => 'kg', 'market_price' => 32.00],
        ['name' => 'Carrot', 'name_kn' => 'ಕ್ಯಾರೆಟ್', 'category' => 'indianMain', 'desc' => 'Crunchy sweet carrots', 'image' => 'images/Carrot.jpg', 'unit_type' => 'kg', 'market_price' => 47.00],
        ['name' => 'Beetroot', 'name_kn' => 'ಬೀಟ್ರೂಟ್', 'category' => 'indianMain', 'desc' => 'Fresh blood-red beets', 'image' => 'images/Beetroot.jpg', 'unit_type' => 'kg', 'market_price' => 42.00],
        ['name' => 'Radish', 'name_kn' => 'ಮೂಲಂಗಿ', 'category' => 'indianMain', 'desc' => 'Crisp white radish', 'image' => 'images/Radish.jpg', 'unit_type' => 'kg', 'market_price' => 27.00],
        ['name' => 'Cabbage', 'name_kn' => 'ಎಲೆಕೋಸು', 'category' => 'indianMain', 'desc' => 'Tight fresh head cabbage', 'image' => 'images/Cabbage.jpg', 'unit_type' => 'kg', 'market_price' => 27.00],
        ['name' => 'Cauliflower', 'name_kn' => 'ಹೂಕೋಸು', 'category' => 'indianMain', 'desc' => 'Clean white curd florets', 'image' => 'images/Cauliflower.jpg', 'unit_type' => 'piece', 'market_price' => 37.00],
        ['name' => 'Broccoli', 'name_kn' => 'ಬ್ರೊಕೊಲಿ', 'category' => 'global', 'desc' => 'Exotic nutrient-rich greens', 'image' => 'images/Broccoli.jpg', 'unit_type' => 'kg', 'market_price' => 117.00],
        ['name' => 'Capsicum', 'name_kn' => 'ದೊಣ್ಣೆ ಮೆಣಸು', 'category' => 'global', 'desc' => 'Crisp green bell pepper', 'image' => 'images/Capsicum.jpg', 'unit_type' => 'kg', 'market_price' => 67.00],
        ['name' => 'Green Chilli', 'name_kn' => 'ಹಸಿರು ಮೆಣಸಿನಕಾಯಿ', 'category' => 'dailyHerbs', 'desc' => 'Fresh spicy chillies', 'image' => 'images/Green Chilli.jpg', 'unit_type' => 'kg', 'market_price' => 77.00],
        ['name' => 'Brinjal', 'name_kn' => 'ಬದನೇಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Purple tender aubergine', 'image' => 'images/Brinjal.jpg', 'unit_type' => 'kg', 'market_price' => 37.00],
        ['name' => 'Lady Finger (Okra)', 'name_kn' => 'ಬೆಂಡೆಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Tender green bhendi', 'image' => 'images/Lady Finger (Okra).jpg', 'unit_type' => 'kg', 'market_price' => 47.00],
        ['name' => 'Bitter Gourd', 'name_kn' => 'ಹಾಗಲಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Fresh karela', 'image' => 'images/Bitter Gourd.jpg', 'unit_type' => 'kg', 'market_price' => 57.00],
        ['name' => 'Bottle Gourd', 'name_kn' => 'ಸೋರೆಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Tender lauki', 'image' => 'images/Bottle Gourd.jpg', 'unit_type' => 'piece', 'market_price' => 37.00],
        ['name' => 'Pumpkin', 'name_kn' => 'ಸೀಗುಂಬಳ', 'category' => 'indianMain', 'desc' => 'Sweet orange pumpkin', 'image' => 'images/Pumpkin.jpg', 'unit_type' => 'kg', 'market_price' => 32.00],
        ['name' => 'Ash Gourd', 'name_kn' => 'ಬೂದು ಕುಂಬಳ', 'category' => 'indianMain', 'desc' => 'Cooling winter melon', 'image' => 'images/Ash Gourd.jpg', 'unit_type' => 'kg', 'market_price' => 27.00],
        ['name' => 'Beans', 'name_kn' => 'ಬೀನ್ಸ್', 'category' => 'indianMain', 'desc' => 'Tender French beans', 'image' => 'images/Beans all.jpg', 'unit_type' => 'kg', 'market_price' => 77.00],
        ['name' => 'Drumstick', 'name_kn' => 'ನುಗ್ಗೆಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Fresh organic drumsticks', 'image' => 'images/Drumstick.jpg', 'unit_type' => 'bunch', 'market_price' => 37.00],
        ['name' => 'Sweet Corn', 'name_kn' => 'ಸಿಹಿ ಮೆಕ್ಕೆಜೋಳ', 'category' => 'global', 'desc' => 'Juicy sweet cobs', 'image' => 'images/Sweet Corn.jpg', 'unit_type' => 'piece', 'market_price' => 22.00],
        ['name' => 'Cucumber (Indian Kheera)', 'name_kn' => 'ಕೀರಾ ಸೌತೆಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Salad cucumber', 'image' => 'images/Cucumber (Indian Kheera).jpg', 'unit_type' => 'kg', 'market_price' => 37.00],
        ['name' => 'Garlic', 'name_kn' => 'ಬೆಳ್ಳುಳ್ಳಿ', 'category' => 'dailyHerbs', 'desc' => 'Desi pungent garlic', 'image' => 'images/Garlic.jpg', 'unit_type' => 'kg', 'market_price' => 197.00],
        ['name' => 'Ginger', 'name_kn' => 'ಶುಂಠಿ', 'category' => 'dailyHerbs', 'desc' => 'Fresh spicy ginger root', 'image' => 'images/Ginger.jpg', 'unit_type' => 'kg', 'market_price' => 157.00],

        // --- Leafy Greens & Herbs (Bunches) ---
        ['name' => 'Spinach', 'name_kn' => 'ಪಾಲಕ್ ಸೊಪ್ಪು', 'category' => 'indianLeafy', 'desc' => 'Fresh tender palak bunch', 'image' => 'images/Spinach.jpg', 'unit_type' => 'bunch', 'market_price' => 17.00],
        ['name' => 'Fenugreek Leaves', 'name_kn' => 'ಮೆಂತೆ ಸೊಪ್ಪು', 'category' => 'indianLeafy', 'desc' => 'Fresh methi greens', 'image' => 'images/Fenugreek Leaves.jpg', 'unit_type' => 'bunch', 'market_price' => 17.00],
        ['name' => 'Coriander Leaves', 'name_kn' => 'ಕೊತ್ತಂಬರಿ ಸೊಪ್ಪು', 'category' => 'indianLeafy', 'desc' => 'Aromatic fresh kothambari', 'image' => 'images/Coriander Leaves.jpg', 'unit_type' => 'bunch', 'market_price' => 9.00],
        ['name' => 'Curry Leaves', 'name_kn' => 'ಕರಿಬೇವು', 'category' => 'indianLeafy', 'desc' => 'Fresh aromatic karibevu', 'image' => 'images/Curry Leaves.jpg', 'unit_type' => 'bunch', 'market_price' => 7.00],
        ['name' => 'Dill Leaves', 'name_kn' => 'ಸಬ್ಬಸಿಗೆ ಸೊಪ್ಪು', 'category' => 'indianLeafy', 'desc' => 'Fragrant sabbasige bunch', 'image' => 'images/Dill Leaves.jpg', 'unit_type' => 'bunch', 'market_price' => 22.00],
        ['name' => 'Indian Sorrel (Gongura)', 'name_kn' => 'ಗೋಂಗುರಾ', 'category' => 'indianLeafy', 'desc' => 'Tangy Andhra gongura bunch', 'image' => 'images/Indian Sorrel (Gongura).jpg', 'unit_type' => 'bunch', 'market_price' => 22.00],
        ['name' => 'Holy Basil (Krishna Tulsi)', 'name_kn' => 'ಕೃಷ್ಣ ತುಳಸಿ', 'category' => 'dailyHerbs', 'desc' => 'Medicinal holy tulsi', 'image' => 'images/Holy Basil (Krishna Tulsi).jpg', 'unit_type' => 'bunch', 'market_price' => 17.00],
        ['name' => 'Mint Leaves (Pudina)', 'name_kn' => 'ಪುದೀನ', 'category' => 'dailyHerbs', 'desc' => 'Cooling fresh mint bunch', 'image' => 'images/Mint.jpg', 'unit_type' => 'bunch', 'market_price' => 12.00],

        // --- Packaged Specialty / Exotic ---
        ['name' => 'Mushroom', 'name_kn' => 'ಅಣಬೆ', 'category' => 'global', 'desc' => 'Fresh white button mushrooms', 'image' => 'images/Mushroom.jpg', 'unit_type' => 'packet', 'packet_sizes' => '200g,400g', 'market_price' => 47.00],
        ['name' => 'Frozen Vegetables Mix', 'name_kn' => 'ಹೆಪ್ಪುಗಟ್ಟಿದ ತರಕಾರಿ ಮಿಕ್ಸ್', 'category' => 'global', 'desc' => 'Mixed corn, peas, carrots', 'image' => 'images/Frozen Vegetables Mix.jpg', 'unit_type' => 'packet', 'packet_sizes' => '250g,500g,1kg', 'market_price' => 117.00],
        ['name' => 'Frozen Berries Mix', 'name_kn' => 'ಹೆಪ್ಪುಗಟ್ಟಿದ ಬೆರ್ರಿಗಳ ಮಿಶ್ರಣ', 'category' => 'globalFruits', 'desc' => 'Rich antioxidant berries pack', 'image' => 'images/Frozen Berries Mix.jpg', 'unit_type' => 'packet', 'packet_sizes' => '250g,500g,1kg', 'market_price' => 97.00],
        ['name' => 'Baby Corn', 'name_kn' => 'ಬೇಬಿ ಕಾರ್ನ್', 'category' => 'global', 'desc' => 'Crisp young corn packet', 'image' => 'images/Baby Corn.jpg', 'unit_type' => 'packet', 'packet_sizes' => '200g,500g', 'market_price' => 47.00]
    ];
}

/**
 * Seeds or synchronizes the daily market price catalog with default records
 */
function seedDailyMarketPrices(PDO $pdo): int
{
    ensureMarketPriceTables($pdo);
    $catalog = getDefaultMarketCatalog();
    $today = date('Y-m-d');
    $inserted = 0;

    $stmtCheck = $pdo->prepare("SELECT id FROM daily_market_prices WHERE name = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO daily_market_prices 
        (product_code, name, name_kn, category, description, image, unit_type, packet_sizes, market_price, profit_margin, selling_price, price_date, is_available)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

    foreach ($catalog as $item) {
        $stmtCheck->execute([$item['name']]);
        if (!$stmtCheck->fetch()) {
            $code = 'VEG_' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $item['name']));
            $market = (float)$item['market_price'];
            $margin = (float)KSR_DEFAULT_PRICE_MARGIN;
            $selling = round($market + $margin, 2);
            $packetSizes = $item['packet_sizes'] ?? '100g,250g,500g,1kg';

            $stmtInsert->execute([
                $code,
                $item['name'],
                $item['name_kn'],
                $item['category'],
                $item['desc'],
                $item['image'],
                $item['unit_type'] ?? 'kg',
                $packetSizes,
                $market,
                $margin,
                $selling,
                $today
            ]);
            $inserted++;
        }
    }
    return $inserted;
}

/**
 * Get all daily market prices for display or admin
 */
function getDailyMarketPrices(?string $category = null, ?string $search = null): array
{
    $pdo = getDBConnection();
    ensureMarketPriceTables($pdo);

    // If table is empty, auto-seed
    $count = (int)$pdo->query("SELECT COUNT(*) FROM daily_market_prices")->fetchColumn();
    if ($count === 0) {
        seedDailyMarketPrices($pdo);
    }

    $sql = "SELECT * FROM daily_market_prices WHERE is_available = 1";
    $params = [];

    if (!empty($category) && $category !== 'all') {
        $sql .= " AND category = ?";
        $params[] = $category;
    }

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR name_kn LIKE ? OR description LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql .= " ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Updates a product's market rate and records price history
 * Formula: Selling price = Market price + ₹3.00
 */
function updateDailyMarketPrice(
    int $id,
    float $marketPrice,
    ?string $unitType = null,
    float $profitMargin = KSR_DEFAULT_PRICE_MARGIN,
    string $notes = '',
    string $adminUser = 'Admin'
): bool {
    $pdo = getDBConnection();
    ensureMarketPriceTables($pdo);

    // Fetch existing record
    $stmt = $pdo->prepare("SELECT * FROM daily_market_prices WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return false;
    }

    $unit = $unitType ?: $item['unit_type'];
    $sellingPrice = round($marketPrice + $profitMargin, 2);
    $today = date('Y-m-d');

    // 1. Update daily_market_prices table
    $stmtUpdate = $pdo->prepare("UPDATE daily_market_prices SET 
        market_price = ?,
        profit_margin = ?,
        selling_price = ?,
        unit_type = ?,
        price_date = ?,
        notes = ?,
        updated_at = datetime('now', 'localtime')
        WHERE id = ?");
    $res = $stmtUpdate->execute([
        $marketPrice,
        $profitMargin,
        $sellingPrice,
        $unit,
        $today,
        $notes,
        $id
    ]);

    // 2. Insert into price history
    $stmtHist = $pdo->prepare("INSERT INTO market_price_history 
        (product_id, product_name, unit_type, market_price, profit_margin, selling_price, effective_date, updated_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtHist->execute([
        $id,
        $item['name'],
        $unit,
        $marketPrice,
        $profitMargin,
        $sellingPrice,
        $today,
        $adminUser,
        $notes
    ]);

    // 3. Optional: Sync to general products table if matching product exists
    try {
        $stmtSync = $pdo->prepare("UPDATE products SET price = ? WHERE name = ?");
        $stmtSync->execute([$sellingPrice, $item['name']]);
    } catch (Throwable $e) {}

    return $res;
}

/**
 * Returns available purchase options based on unit type
 */
function getPurchaseOptionsForUnit(string $unitType, float $sellingPrice, string $packetSizes = '100g,250g,500g,1kg'): array
{
    $options = [];
    switch ($unitType) {
        case 'kg':
            // Weights: 250g, 500g, 750g, 1kg, 2kg, 3kg, 5kg
            $weights = [
                ['grams' => 250, 'label' => '250g'],
                ['grams' => 500, 'label' => '500g'],
                ['grams' => 750, 'label' => '750g'],
                ['grams' => 1000, 'label' => '1 kg'],
                ['grams' => 2000, 'label' => '2 kg'],
                ['grams' => 3000, 'label' => '3 kg'],
                ['grams' => 5000, 'label' => '5 kg'],
            ];
            foreach ($weights as $w) {
                $calc = round(($sellingPrice * $w['grams']) / 1000, 2);
                $options[] = [
                    'label' => $w['label'],
                    'value' => (string)$w['grams'],
                    'price' => $calc,
                    'is_default' => ($w['grams'] === 500 || $w['grams'] === 1000)
                ];
            }
            break;

        case 'piece':
            $pieces = [1, 2, 3, 5, 10, 20];
            foreach ($pieces as $p) {
                $options[] = [
                    'label' => $p . ($p === 1 ? ' piece' : ' pieces'),
                    'value' => (string)$p,
                    'price' => round($sellingPrice * $p, 2),
                    'is_default' => ($p === 1)
                ];
            }
            break;

        case 'bunch':
            $bunches = [1, 2, 3, 5];
            foreach ($bunches as $b) {
                $options[] = [
                    'label' => $b . ($b === 1 ? ' bunch (ಕಟ್ಟು)' : ' bunches (ಕಟ್ಟುಗಳು)'),
                    'value' => (string)$b,
                    'price' => round($sellingPrice * $b, 2),
                    'is_default' => ($b === 1)
                ];
            }
            break;

        case 'dozen':
            $options = [
                ['label' => '1/2 dozen (6 pcs)', 'value' => '0.5', 'price' => round($sellingPrice * 0.5, 2), 'is_default' => false],
                ['label' => '1 dozen (12 pcs)', 'value' => '1', 'price' => round($sellingPrice, 2), 'is_default' => true],
                ['label' => '2 dozen (24 pcs)', 'value' => '2', 'price' => round($sellingPrice * 2, 2), 'is_default' => false],
            ];
            break;

        case 'packet':
        default:
            $sizes = array_filter(array_map('trim', explode(',', $packetSizes)));
            if (empty($sizes)) {
                $sizes = ['100g', '250g', '500g', '1kg'];
            }
            foreach ($sizes as $idx => $s) {
                // If label has weight, calculate fraction of 1kg if standard
                $mult = 1.0;
                if (preg_match('/(\d+)\s*g/i', $s, $m)) {
                    $mult = (float)$m[1] / 1000.0;
                } elseif (preg_match('/(\d+)\s*kg/i', $s, $m)) {
                    $mult = (float)$m[1];
                }
                $price = ($mult !== 1.0) ? round($sellingPrice * $mult, 2) : $sellingPrice;
                if ($price <= 0) $price = $sellingPrice;
                $options[] = [
                    'label' => "1 packet ({$s})",
                    'value' => $s,
                    'price' => $price,
                    'is_default' => ($idx === 0)
                ];
            }
            break;
    }
    return $options;
}

/**
 * Retrieves market price history for a given product or all products
 */
function getMarketPriceHistory(?int $productId = null, int $limit = 50): array
{
    $pdo = getDBConnection();
    ensureMarketPriceTables($pdo);

    if ($productId !== null && $productId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM market_price_history WHERE product_id = ? ORDER BY effective_date DESC, id DESC LIMIT ?");
        $stmt->execute([$productId, $limit]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM market_price_history ORDER BY effective_date DESC, id DESC LIMIT ?");
        $stmt->execute([$limit]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
