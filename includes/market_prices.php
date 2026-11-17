<?php
// ============================================================
// includes/market_prices.php - Daily Market Price & Selling Price System
// KSR GURU JI: Selling Price = Market Price + ₹3.00
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
 * Ensures daily_market_prices and market_price_history tables exist and have all recommended columns:
 * product_name, category, market_price, profit_margin, selling_price,
 * unit_type, base_quantity, display_unit, price_date, stock_status, is_active
 */
function ensureMarketPriceTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_market_prices (
        id             INTEGER PRIMARY KEY AUTOINCREMENT,
        product_code   TEXT UNIQUE,
        name           TEXT NOT NULL,
        product_name   TEXT,
        name_kn        TEXT,
        category       TEXT DEFAULT 'indianMain',
        description    TEXT,
        image          TEXT,
        unit_type      TEXT DEFAULT 'kg', -- 'gram', 'piece', 'packet', 'bunch', 'dozen', 'kg'
        base_quantity  REAL DEFAULT 1,    -- e.g. 250 for gram, 1 for piece/packet
        display_unit   TEXT DEFAULT 'kg', -- e.g. '250 g', 'Piece', 'Packet', 'Bunch'
        packet_sizes   TEXT DEFAULT '100g,250g,500g,1kg',
        market_price   REAL NOT NULL DEFAULT 0.00,
        profit_margin  REAL NOT NULL DEFAULT 3.00,
        selling_price  REAL NOT NULL DEFAULT 3.00,
        price_date     TEXT DEFAULT (date('now', 'localtime')),
        stock_status   TEXT DEFAULT 'Available',
        is_active      INTEGER DEFAULT 1,
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
        base_quantity  REAL DEFAULT 1,
        display_unit   TEXT DEFAULT 'kg',
        market_price   REAL NOT NULL,
        profit_margin  REAL NOT NULL DEFAULT 3.00,
        selling_price  REAL NOT NULL,
        effective_date TEXT NOT NULL,
        updated_by     TEXT DEFAULT 'Admin',
        notes          TEXT,
        created_at     TEXT DEFAULT (datetime('now', 'localtime'))
    )");

    // Safe dynamic column migration for existing databases
    try {
        $existingCols = [];
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(daily_market_prices)");
            if ($stmt) {
                while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $existingCols[] = strtolower($c['name']);
                }
            }
        } else {
            $stmt = $pdo->query("SHOW COLUMNS FROM daily_market_prices");
            if ($stmt) {
                while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $existingCols[] = strtolower($c['Field'] ?? $c['field'] ?? '');
                }
            }
        }

        if (!in_array('product_name', $existingCols, true)) {
            $pdo->exec("ALTER TABLE daily_market_prices ADD COLUMN product_name TEXT");
        }
        if (!in_array('base_quantity', $existingCols, true)) {
            $pdo->exec("ALTER TABLE daily_market_prices ADD COLUMN base_quantity REAL DEFAULT 1");
        }
        if (!in_array('display_unit', $existingCols, true)) {
            $pdo->exec("ALTER TABLE daily_market_prices ADD COLUMN display_unit TEXT DEFAULT ''");
        }
        if (!in_array('stock_status', $existingCols, true)) {
            $pdo->exec("ALTER TABLE daily_market_prices ADD COLUMN stock_status TEXT DEFAULT 'Available'");
        }
        if (!in_array('is_active', $existingCols, true)) {
            $pdo->exec("ALTER TABLE daily_market_prices ADD COLUMN is_active INTEGER DEFAULT 1");
        }

        // Sync product_name with name where missing
        $pdo->exec("UPDATE daily_market_prices SET product_name = name WHERE product_name IS NULL OR product_name = ''");
    } catch (Throwable $e) {
        // Silently continue if column already exists
    }
}

/**
 * Initial Catalog of Vegetables & Fruits with appropriate units and default base market rates
 * Selling Price = Market Price + ₹3.00
 */
function getDefaultMarketCatalog(): array
{
    return [
        // ============================================================
        // 1. Leafy Vegetables – Packet (₹3 automatic pricing: Selling = Market + ₹3)
        // ============================================================
        ['name' => 'Coriander Leaves', 'name_kn' => 'ಕೊತ್ತಂಬರಿ ಸೊಪ್ಪು', 'category' => 'indianLeafy', 'desc' => 'Aromatic fresh kothambari packet', 'image' => 'images/Coriander Leaves.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'market_price' => 15.00],
        ['name' => 'Curry Leaves', 'name_kn' => 'ಕರಿಬೇವು', 'category' => 'indianLeafy', 'desc' => 'Fresh aromatic karibevu packet', 'image' => 'images/Curry Leaves.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'market_price' => 15.00],
        ['name' => 'Dill Leaves', 'name_kn' => 'ಸಬ್ಬಸಿಗೆ ಸೊಪ್ಪು', 'category' => 'indianLeafy', 'desc' => 'Fragrant sabbasige packet', 'image' => 'images/Dill Leaves.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'market_price' => 15.00],
        ['name' => 'Fenugreek Leaves', 'name_kn' => 'ಮೆಂತೆ ಸೊಪ್ಪು', 'category' => 'indianLeafy', 'desc' => 'Fresh methi greens packet', 'image' => 'images/Fenugreek Leaves.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'market_price' => 10.00],
        ['name' => 'Indian Sorrel (Gongura)', 'name_kn' => 'ಗೋಂಗುರಾ', 'category' => 'indianLeafy', 'desc' => 'Tangy Andhra gongura packet', 'image' => 'images/Indian Sorrel (Gongura).jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'market_price' => 20.00],
        ['name' => 'Spinach', 'name_kn' => 'ಪಾಲಕ್ ಸೊಪ್ಪು', 'category' => 'indianLeafy', 'desc' => 'Fresh tender palak packet', 'image' => 'images/Spinach.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'market_price' => 10.00],

        // ============================================================
        // 2. Vegetables – Piece (₹3 automatic pricing: Selling = Market + ₹3)
        // ============================================================
        ['name' => 'Radish', 'name_kn' => 'ಮೂಲಂಗಿ', 'category' => 'indianMain', 'desc' => 'Crisp white radish piece', 'image' => 'images/Radish.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 10.00],
        ['name' => 'Pumpkin', 'name_kn' => 'ಸೀಗುಂಬಳ', 'category' => 'indianMain', 'desc' => 'Sweet orange pumpkin piece', 'image' => 'images/Pumpkin.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 40.00],
        ['name' => 'Green Papaya', 'name_kn' => 'ಹಸಿ ಪಪ್ಪಾಯಿ', 'category' => 'indianMain', 'desc' => 'Raw cooking papaya piece', 'image' => 'images/Papaya.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 40.00],
        ['name' => 'Drumstick', 'name_kn' => 'ನುಗ್ಗೆಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Fresh organic drumstick piece', 'image' => 'images/Drumstick.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 10.00],
        ['name' => 'Cauliflower', 'name_kn' => 'ಹೂಕೋಸು', 'category' => 'indianMain', 'desc' => 'Clean white cauliflower piece', 'image' => 'images/Cauliflower.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 20.00],
        ['name' => 'Cabbage', 'name_kn' => 'ಎಲೆಕೋಸು', 'category' => 'indianMain', 'desc' => 'Tight fresh head cabbage piece', 'image' => 'images/Cabbage.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 25.00],
        ['name' => 'Bottle Gourd', 'name_kn' => 'ಸೋರೆಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Tender fresh lauki piece', 'image' => 'images/Bottle Gourd.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 25.00],

        // ============================================================
        // 3. Vegetables – 250 Gram (Base Unit = 250g, Selling = Market + ₹3)
        // ============================================================
        ['name' => 'White Onion', 'name_kn' => 'ಬಿಳಿ ಈರುಳ್ಳಿ', 'category' => 'indianMain', 'desc' => 'Sweet mild white onions', 'image' => 'images/White Onion.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 25.00],
        ['name' => 'Tomato (Green)', 'name_kn' => 'ಹಸಿರು ಟೊಮೆಟೊ', 'category' => 'indianMain', 'desc' => 'Tangy raw green tomatoes', 'image' => 'images/Tomato.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 15.00],
        ['name' => 'Tomato', 'name_kn' => 'ಟೊಮಾಟೊ', 'category' => 'indianMain', 'desc' => 'Ripe red country tomatoes (₹18 / 250 g)', 'image' => 'images/Tomato.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 15.00],
        ['name' => 'Sweet Potato', 'name_kn' => 'ಗೆಣಸು', 'category' => 'indianMain', 'desc' => 'Nutritious sweet potatoes', 'image' => 'images/Sweet Potato.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 20.00],
        ['name' => 'Potato', 'name_kn' => 'ಆಲೂಗಡ್ಡೆ', 'category' => 'indianMain', 'desc' => 'Fresh farm potatoes', 'image' => 'images/Potato.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 20.00],
        ['name' => 'Onion', 'name_kn' => 'ಈರುಳ್ಳಿ', 'category' => 'indianMain', 'desc' => 'Quality red onions', 'image' => 'images/Onion.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 20.00],
        ['name' => 'Lady Finger (Okra)', 'name_kn' => 'ಬೆಂಡೆಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Tender green bhendi', 'image' => 'images/Lady Finger (Okra).jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 20.00],
        ['name' => 'Cucumber (Indian Kheera)', 'name_kn' => 'ಕೀರಾ ಸೌತೆಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Fresh crisp salad cucumber', 'image' => 'images/Cucumber (Indian Kheera).jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 25.00],
        ['name' => 'Carrot', 'name_kn' => 'ಕ್ಯಾರೆಟ್', 'category' => 'indianMain', 'desc' => 'Crunchy sweet carrots', 'image' => 'images/Carrot.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 20.00],
        ['name' => 'Brinjal', 'name_kn' => 'ಬದನೇಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Purple tender aubergine', 'image' => 'images/Brinjal.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 15.00],
        ['name' => 'Bitter Melon (Small Variety)', 'name_kn' => 'ಸಣ್ಣ ಹಾಗಲಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Small desi bitter gourd', 'image' => 'images/Bitter Melon (Small Variety).jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 30.00],
        ['name' => 'Bitter Gourd', 'name_kn' => 'ಹಾಗಲಕಾಯಿ', 'category' => 'indianMain', 'desc' => 'Fresh green karela', 'image' => 'images/Bitter Gourd.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 25.00],
        ['name' => 'Beetroot', 'name_kn' => 'ಬೀಟ್ರೂಟ್', 'category' => 'indianMain', 'desc' => 'Fresh blood-red beets', 'image' => 'images/Beetroot.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 25.00],
        ['name' => 'Ash Gourd', 'name_kn' => 'ಬೂದು ಕುಂಬಳ', 'category' => 'indianMain', 'desc' => 'Cooling winter melon', 'image' => 'images/Ash Gourd.jpg', 'unit_type' => 'gram', 'base_quantity' => 250, 'display_unit' => '250 g', 'market_price' => 30.00],

        // ============================================================
        // 4. Special Vegetables – Packet (₹3 automatic pricing: Selling = Market + ₹3)
        // ============================================================
        ['name' => 'Winged Bean (Asparagus Pea)', 'name_kn' => 'ರೆಕ್ಕೆ ಹುರುಳಿ', 'category' => 'global', 'desc' => 'Exotic tender winged beans', 'image' => 'images/Winged Bean (Asparagus Pea.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'market_price' => 50.00],

        // ============================================================
        // 5. Popular Fresh Fruits & Aromatics
        // ============================================================
        ['name' => 'Apple', 'name_kn' => 'ಸೇಬು', 'category' => 'globalFruits', 'desc' => 'Crisp & sweet Royal Gala', 'image' => 'images/Apple.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 177.00],
        ['name' => 'GreenApple', 'name_kn' => 'ಹಸಿರು ಸೇಬು', 'category' => 'globalFruits', 'desc' => 'Tangy crisp Granny Smith', 'image' => 'images/Green Apple.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 217.00],
        ['name' => 'Banana', 'name_kn' => 'ಬಾಳೆ ಹಣ್ಣು', 'category' => 'indianFruits', 'desc' => 'Energy booster Robusta', 'image' => 'images/Banana.jpg', 'unit_type' => 'dozen', 'base_quantity' => 1, 'display_unit' => 'Dozen', 'market_price' => 47.00],
        ['name' => 'Banana small', 'name_kn' => 'ಸಣ್ಣ ಬಾಳೆಹಣ್ಣು', 'category' => 'indianFruits', 'desc' => 'Miniature sweet bananas (ಏಲಕ್ಕಿ ಬಾಳೆ)', 'image' => 'images/Banana small.jpg', 'unit_type' => 'dozen', 'base_quantity' => 1, 'display_unit' => 'Dozen', 'market_price' => 67.00],
        ['name' => 'Orange', 'name_kn' => 'ಕಿತ್ತಳೆ', 'category' => 'dailyFruits', 'desc' => 'Juicy & tangy Nagpur oranges', 'image' => 'images/Orange.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 87.00],
        ['name' => 'Mango', 'name_kn' => 'ಮಾವಿನ ಹಣ್ಣು', 'category' => 'indianFruits', 'desc' => 'King of fruits Alphonso', 'image' => 'images/Mango.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 147.00],
        ['name' => 'Grapes', 'name_kn' => 'ದ್ರಾಕ್ಷಿ', 'category' => 'globalFruits', 'desc' => 'Seedless green Thompson', 'image' => 'images/Grapes.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 117.00],
        ['name' => 'Black Grapes', 'name_kn' => 'ಕಪ್ಪು ದ್ರಾಕ್ಷಿ', 'category' => 'globalFruits', 'desc' => 'Sweet dark grapes Sharad', 'image' => 'images/Black Grapes.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 177.00],
        ['name' => 'Pineapple', 'name_kn' => 'ಅನಾನಸ್', 'category' => 'dailyFruits', 'desc' => 'Sweet & sour Queen pineapple', 'image' => 'images/Pineapple.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 77.00],
        ['name' => 'Papaya', 'name_kn' => 'ಪಪ್ಪಾಯಿ', 'category' => 'indianFruits', 'desc' => 'Digestive aid Red Lady', 'image' => 'images/Papaya.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 47.00],
        ['name' => 'Watermelon', 'name_kn' => 'ಕಲ್ಲಂಗಡಿ', 'category' => 'dailyFruits', 'desc' => 'Hydrating fresh fruit', 'image' => 'images/Watermelon.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 32.00],
        ['name' => 'Muskmelon', 'name_kn' => 'ಕರಬೂಜ', 'category' => 'dailyFruits', 'desc' => 'Aromatic & sweet melon', 'image' => 'images/Muskmelon.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 57.00],
        ['name' => 'Guava', 'name_kn' => 'ಪೇರಲ', 'category' => 'indianFruits', 'desc' => 'Rich in vitamin C', 'image' => 'images/Guava.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 67.00],
        ['name' => 'Guava red', 'name_kn' => 'ಕೆಂಪು ಪೇರಲ', 'category' => 'indianFruits', 'desc' => 'Sweet red-fleshed guava', 'image' => 'images/Guava red.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 107.00],
        ['name' => 'Pomegranate', 'name_kn' => 'ದಾಳಿಂಬೆ', 'category' => 'globalFruits', 'desc' => 'Antioxidant rich pearls', 'image' => 'images/Pomegranate.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 157.00],
        ['name' => 'Kiwi', 'name_kn' => 'ಕಿವಿ', 'category' => 'globalFruits', 'desc' => 'Vitamin C powerhouse', 'image' => 'images/Kiwi.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 32.00],
        ['name' => 'Tender Coconut', 'name_kn' => 'ಎಳೆ ನೀರಿನ ತೆಂಗು', 'category' => 'dailyFruits', 'desc' => 'Fresh coconut water & malai', 'image' => 'images/Tender Coconut.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 47.00],
        ['name' => 'Lime', 'name_kn' => 'ನಿಂಬೆ', 'category' => 'dailyFruits', 'desc' => 'Tart green fresh lemon', 'image' => 'images/Lime.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 4.00],
        ['name' => 'Dragon Fruit', 'name_kn' => 'ಡ್ರಾಗನ್ ಹಣ್ಣು', 'category' => 'exotic', 'desc' => 'Vibrant pink superfood', 'image' => 'images/Dragon Fruit.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 77.00],
        ['name' => 'Strawberry', 'name_kn' => 'ಸ್ಟ್ರಾಬೆರಿ', 'category' => 'globalFruits', 'desc' => 'Sweet, juicy red berries', 'image' => 'images/Strawberry.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'packet_sizes' => '200g,400g,1kg', 'market_price' => 107.00],
        ['name' => 'Sapota (Chikoo)', 'name_kn' => 'ಸಪೋಟ', 'category' => 'indianFruits', 'desc' => 'Sweet brown sapodilla', 'image' => 'images/Sapota (Chikoo).jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 67.00],
        ['name' => 'Custard Apple', 'name_kn' => 'ಸೀತಾಫಲ', 'category' => 'indianFruits', 'desc' => 'Creamy sweet pulp', 'image' => 'images/Custard Apple.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 147.00],
        ['name' => 'Jackfruit', 'name_kn' => 'ಹಲಸು', 'category' => 'indianFruits', 'desc' => 'Sweet golden carpels', 'image' => 'images/Jackfruit.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 77.00],
        ['name' => 'Amla', 'name_kn' => 'ನೆಲ್ಲಿ', 'category' => 'indianFruits', 'desc' => 'Immunity booster gooseberry', 'image' => 'images/Amla.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 77.00],

        // Everyday Veg / Herbs
        ['name' => 'Broccoli', 'name_kn' => 'ಬ್ರೊಕೊಲಿ', 'category' => 'global', 'desc' => 'Exotic nutrient-rich greens', 'image' => 'images/Broccoli.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 117.00],
        ['name' => 'Capsicum', 'name_kn' => 'ದೊಣ್ಣೆ ಮೆಣಸು', 'category' => 'global', 'desc' => 'Crisp green bell pepper', 'image' => 'images/Capsicum.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 67.00],
        ['name' => 'Green Chilli', 'name_kn' => 'ಹಸಿರು ಮೆಣಸಿನಕಾಯಿ', 'category' => 'dailyHerbs', 'desc' => 'Fresh spicy chillies', 'image' => 'images/Green Chilli.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 77.00],
        ['name' => 'Beans', 'name_kn' => 'ಬೀನ್ಸ್', 'category' => 'indianMain', 'desc' => 'Tender French beans', 'image' => 'images/Beans all.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 77.00],
        ['name' => 'Sweet Corn', 'name_kn' => 'ಸಿಹಿ ಮೆಕ್ಕೆಜೋಳ', 'category' => 'global', 'desc' => 'Juicy sweet cobs', 'image' => 'images/Sweet Corn.jpg', 'unit_type' => 'piece', 'base_quantity' => 1, 'display_unit' => 'Piece', 'market_price' => 22.00],
        ['name' => 'Garlic', 'name_kn' => 'ಬೆಳ್ಳುಳ್ಳಿ', 'category' => 'dailyHerbs', 'desc' => 'Desi pungent garlic', 'image' => 'images/Garlic.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 197.00],
        ['name' => 'Ginger', 'name_kn' => 'ಶುಂಠಿ', 'category' => 'dailyHerbs', 'desc' => 'Fresh spicy ginger root', 'image' => 'images/Ginger.jpg', 'unit_type' => 'kg', 'base_quantity' => 1, 'display_unit' => 'kg', 'market_price' => 157.00],
        ['name' => 'Holy Basil (Krishna Tulsi)', 'name_kn' => 'ಕೃಷ್ಣ ತುಳಸಿ', 'category' => 'dailyHerbs', 'desc' => 'Medicinal holy tulsi', 'image' => 'images/Holy Basil (Krishna Tulsi).jpg', 'unit_type' => 'bunch', 'base_quantity' => 1, 'display_unit' => 'Bunch', 'market_price' => 17.00],
        ['name' => 'Mint Leaves (Pudina)', 'name_kn' => 'ಪುದೀನ', 'category' => 'dailyHerbs', 'desc' => 'Cooling fresh mint bunch', 'image' => 'images/Mint.jpg', 'unit_type' => 'bunch', 'base_quantity' => 1, 'display_unit' => 'Bunch', 'market_price' => 12.00],
        ['name' => 'Mushroom', 'name_kn' => 'ಅಣಬೆ', 'category' => 'global', 'desc' => 'Fresh white button mushrooms', 'image' => 'images/Mushroom.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'packet_sizes' => '200g,400g', 'market_price' => 47.00],
        ['name' => 'Frozen Vegetables Mix', 'name_kn' => 'ಹೆಪ್ಪುಗಟ್ಟಿದ ತರಕಾರಿ ಮಿಕ್ಸ್', 'category' => 'global', 'desc' => 'Mixed corn, peas, carrots', 'image' => 'images/Frozen Vegetables Mix.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'packet_sizes' => '250g,500g,1kg', 'market_price' => 117.00],
        ['name' => 'Frozen Berries Mix', 'name_kn' => 'ಹೆಪ್ಪುಗಟ್ಟಿದ ಬೆರ್ರಿಗಳ ಮಿಶ್ರಣ', 'category' => 'globalFruits', 'desc' => 'Rich antioxidant berries pack', 'image' => 'images/Frozen Berries Mix.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'packet_sizes' => '250g,500g,1kg', 'market_price' => 97.00],
        ['name' => 'Baby Corn', 'name_kn' => 'ಬೇಬಿ ಕಾರ್ನ್', 'category' => 'global', 'desc' => 'Crisp young corn packet', 'image' => 'images/Baby Corn.jpg', 'unit_type' => 'packet', 'base_quantity' => 1, 'display_unit' => 'Packet', 'packet_sizes' => '200g,500g', 'market_price' => 47.00]
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

    $stmtCheck = $pdo->prepare("SELECT id, name FROM daily_market_prices WHERE name = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO daily_market_prices 
        (product_code, name, product_name, name_kn, category, description, image, unit_type, base_quantity, display_unit, packet_sizes, market_price, profit_margin, selling_price, price_date, stock_status, is_active, is_available)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', 1, 1)");

    $stmtUpdate = $pdo->prepare("UPDATE daily_market_prices SET
        product_name = ?,
        name_kn = ?,
        category = ?,
        description = ?,
        image = ?,
        unit_type = ?,
        base_quantity = ?,
        display_unit = ?,
        market_price = ?,
        profit_margin = ?,
        selling_price = ?,
        stock_status = 'Available',
        is_active = 1,
        is_available = 1
        WHERE id = ?");

    foreach ($catalog as $item) {
        $stmtCheck->execute([$item['name']]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $market = (float)$item['market_price'];
        $margin = (float)KSR_DEFAULT_PRICE_MARGIN;
        $selling = round($market + $margin, 2);
        $packetSizes = $item['packet_sizes'] ?? '100g,250g,500g,1kg';
        $baseQty = (float)($item['base_quantity'] ?? ($item['unit_type'] === 'gram' ? 250 : 1));
        $displayUnit = $item['display_unit'] ?? ($item['unit_type'] === 'gram' ? '250 g' : ucfirst($item['unit_type']));

        if (!$existing) {
            $code = 'VEG_' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $item['name']));
            $stmtInsert->execute([
                $code,
                $item['name'],
                $item['name'],
                $item['name_kn'],
                $item['category'],
                $item['desc'],
                $item['image'],
                $item['unit_type'] ?? 'kg',
                $baseQty,
                $displayUnit,
                $packetSizes,
                $market,
                $margin,
                $selling,
                $today
            ]);
            $inserted++;
        } else {
            // Update existing product to sync new unit_type, base_quantity, and prices
            $stmtUpdate->execute([
                $item['name'],
                $item['name_kn'],
                $item['category'],
                $item['desc'],
                $item['image'],
                $item['unit_type'] ?? 'kg',
                $baseQty,
                $displayUnit,
                $market,
                $margin,
                $selling,
                (int)$existing['id']
            ]);
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

    $sql = "SELECT * FROM daily_market_prices WHERE (is_available = 1 OR is_active = 1)";
    $params = [];

    if (!empty($category) && $category !== 'all') {
        $sql .= " AND category = ?";
        $params[] = $category;
    }

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR name_kn LIKE ? OR description LIKE ? OR product_name LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
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
    string $adminUser = 'Admin',
    ?float $baseQuantity = null,
    ?string $displayUnit = null
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
    if ($baseQuantity !== null) {
        $baseQty = $baseQuantity;
    } elseif ($unitType !== null && $unitType !== $item['unit_type']) {
        $baseQty = ($unit === 'gram') ? 250.0 : 1.0;
    } else {
        $baseQty = (float)($item['base_quantity'] ?? ($unit === 'gram' ? 250.0 : 1.0));
    }

    if ($displayUnit !== null) {
        $dispUnit = $displayUnit;
    } elseif ($unitType !== null && $unitType !== $item['unit_type']) {
        $dispUnit = ($unit === 'gram') ? '250 g' : ucfirst($unit);
    } else {
        $dispUnit = !empty($item['display_unit']) ? $item['display_unit'] : ($unit === 'gram' ? '250 g' : ucfirst($unit));
    }

    $sellingPrice = round($marketPrice + $profitMargin, 2);
    $today = date('Y-m-d');

    // 1. Update daily_market_prices table
    $stmtUpdate = $pdo->prepare("UPDATE daily_market_prices SET 
        market_price = ?,
        profit_margin = ?,
        selling_price = ?,
        unit_type = ?,
        base_quantity = ?,
        display_unit = ?,
        price_date = ?,
        notes = ?,
        updated_at = datetime('now', 'localtime')
        WHERE id = ?");
    $res = $stmtUpdate->execute([
        $marketPrice,
        $profitMargin,
        $sellingPrice,
        $unit,
        $baseQty,
        $dispUnit,
        $today,
        $notes,
        $id
    ]);

    // 2. Insert into price history
    try {
        $stmtHist = $pdo->prepare("INSERT INTO market_price_history 
            (product_id, product_name, unit_type, base_quantity, display_unit, market_price, profit_margin, selling_price, effective_date, updated_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtHist->execute([
            $id,
            $item['name'],
            $unit,
            $baseQty,
            $dispUnit,
            $marketPrice,
            $profitMargin,
            $sellingPrice,
            $today,
            $adminUser,
            $notes
        ]);
    } catch (Throwable $e) {
        // Fallback for schemas with older history table without base_quantity/display_unit
        $stmtHistLegacy = $pdo->prepare("INSERT INTO market_price_history 
            (product_id, product_name, unit_type, market_price, profit_margin, selling_price, effective_date, updated_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtHistLegacy->execute([
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
    }

    // 3. Optional: Sync to general products table if matching product exists
    try {
        $stmtSync = $pdo->prepare("UPDATE products SET price = ? WHERE name = ?");
        $stmtSync->execute([$sellingPrice, $item['name']]);
    } catch (Throwable $e) {}

    return $res;
}

/**
 * Returns available purchase options based on unit type and base quantity
 *
 * Rule: Store base unit and its price separately.
 * For example:
 * - Tomato has base quantity 250 g at selling price ₹18.
 *   If customer selects 500 g -> calculate 2 × ₹18 = ₹36.
 *   If customer selects 1 kg  -> calculate 4 × ₹18 = ₹72.
 * - Coriander Leaves has base unit Packet at selling price ₹18.
 *   Options: 1 packet (₹18), 2 packets (₹36), 3 packets (₹54)...
 * - Radish has base unit Piece at selling price ₹13.
 *   Options: 1 piece (₹13), 2 pieces (₹26), 3 pieces (₹39)...
 */
function getPurchaseOptionsForUnit(
    string $unitType,
    float $sellingPrice,
    string $packetSizes = '100g,250g,500g,1kg',
    float $baseQuantity = 0.0,
    string $displayUnit = ''
): array {
    $options = [];
    $is250g = ($unitType === 'gram' || (int)$baseQuantity === 250 || str_contains(strtolower($displayUnit), '250'));

    if ($is250g) {
        // Base quantity is 250 g. $sellingPrice is for 250 g (e.g. Tomato ₹18 / 250 g).
        // 250g = 1 unit, 500g = 2 units (₹36), 750g = 3 units (₹54), 1 kg = 4 units (₹72)
        $weights = [
            ['grams' => 250,  'label' => '250g', 'mult' => 1,  'is_default' => true],
            ['grams' => 500,  'label' => '500g', 'mult' => 2,  'is_default' => false],
            ['grams' => 750,  'label' => '750g', 'mult' => 3,  'is_default' => false],
            ['grams' => 1000, 'label' => '1 kg', 'mult' => 4,  'is_default' => false],
            ['grams' => 2000, 'label' => '2 kg', 'mult' => 8,  'is_default' => false],
            ['grams' => 3000, 'label' => '3 kg', 'mult' => 12, 'is_default' => false],
            ['grams' => 5000, 'label' => '5 kg', 'mult' => 20, 'is_default' => false],
        ];
        foreach ($weights as $w) {
            $options[] = [
                'label' => $w['label'],
                'value' => (string)$w['grams'],
                'price' => round($sellingPrice * $w['mult'], 2),
                'is_default' => $w['is_default']
            ];
        }
        return $options;
    }

    switch ($unitType) {
        case 'kg':
            // Weight per 1 kg (1000g). $sellingPrice is for 1 kg.
            $weights = [
                ['grams' => 250,  'label' => '250g', 'is_default' => false],
                ['grams' => 500,  'label' => '500g', 'is_default' => false],
                ['grams' => 750,  'label' => '750g', 'is_default' => false],
                ['grams' => 1000, 'label' => '1 kg', 'is_default' => true],
                ['grams' => 2000, 'label' => '2 kg', 'is_default' => false],
                ['grams' => 3000, 'label' => '3 kg', 'is_default' => false],
                ['grams' => 5000, 'label' => '5 kg', 'is_default' => false],
            ];
            foreach ($weights as $w) {
                $calc = round(($sellingPrice * $w['grams']) / 1000, 2);
                $options[] = [
                    'label' => $w['label'],
                    'value' => (string)$w['grams'],
                    'price' => $calc,
                    'is_default' => $w['is_default']
                ];
            }
            break;

        case 'piece':
            $pieces = [1, 2, 3, 5, 10];
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
                ['label' => '1 dozen (12 pcs)',  'value' => '1',   'price' => round($sellingPrice, 2),       'is_default' => true],
                ['label' => '2 dozen (24 pcs)',  'value' => '2',   'price' => round($sellingPrice * 2, 2),   'is_default' => false],
            ];
            break;

        case 'packet':
        default:
            // If custom sizes with gram/kg fractions were passed (e.g. Frozen Veg)
            if (!empty($packetSizes) && $packetSizes !== '100g,250g,500g,1kg' && (str_contains($packetSizes, 'g') || str_contains($packetSizes, 'kg'))) {
                $sizes = array_filter(array_map('trim', explode(',', $packetSizes)));
                foreach ($sizes as $idx => $s) {
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
            } else {
                // Discrete packets: 1 packet, 2 packets, 3 packets, 5 packets
                $packets = [1, 2, 3, 5];
                foreach ($packets as $pkt) {
                    $options[] = [
                        'label' => $pkt . ($pkt === 1 ? ' packet' : ' packets'),
                        'value' => (string)$pkt,
                        'price' => round($sellingPrice * $pkt, 2),
                        'is_default' => ($pkt === 1)
                    ];
                }
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
