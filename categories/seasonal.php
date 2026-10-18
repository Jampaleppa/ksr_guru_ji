<?php
// ============================================================
// seasonal-items.php - Dynamic Seasonal Items page
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
// Start session for cart
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
$categorySlug = 'seasonal-items';
$seasonalCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($seasonalCategory) {
  // Fetch products from this category
  $dbProducts = getProductsByCategory($seasonalCategory['id']);
  if (!empty($dbProducts)) {
    foreach ($dbProducts as $p) {
      $productsData[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'kn' => $p['kn_name'] ?? $p['name'] . ' (ಋತುಮಾನ)',
        'category' => $p['category_slug'] ?? 'summer',
        'price' => $p['price'],
        'image' => $p['image_url'] ?? '',
        'desc' => $p['description_en'] ?? 'Seasonal fresh item'
      ];
    }
  }
}
// Fallback to static data if no DB products found
if (empty($productsData)) {
  // Embedded static product data (same as original HTML)
  $staticProducts = [
    ['id' => 1, 'nameEn' => 'Alphonso Mango', 'nameKn' => 'ಅಲ್ಫಾನ್ಸೊ ಮಾವು', 'category' => 'summer', 'price' => 250, 'image' => "images\\Alphonso Mango.jpg", 'desc' => "Sweet & creamy king of mangoes."],
    ['id' => 2, 'nameEn' => 'Banganapalli Mango', 'nameKn' => 'ಬಂಗನಪಲ್ಲಿ ಮಾವು', 'category' => 'summer', 'price' => 180, 'image' => "images\\Banganapalli Mango.jpg", 'desc' => "Fibreless, golden, aromatic."],
    ['id' => 3, 'nameEn' => 'Totapuri Mango', 'nameKn' => 'ಟೋಟಾಪುರಿ ಮಾವು', 'category' => 'summer', 'price' => 140, 'image' => "images\\Totapuri Mango.jpg", 'desc' => "Tangy, firm, great for pickles."],
    ['id' => 4, 'nameEn' => 'Raw Mango', 'nameKn' => 'ಕಚ್ಚಾ ಮಾವು', 'category' => 'summer', 'price' => 60, 'image' => "images\\Raw Mango.jpg", 'desc' => "Sour, crunchy, perfect for chutney."],
    ['id' => 5, 'nameEn' => 'Watermelon', 'nameKn' => 'ಕಲ್ಲಂಗಡಿ', 'category' => 'summer', 'price' => 40, 'image' => "images\\Watermelon.jpg", 'desc' => "Juicy red, hydrating summer fruit."],
    ['id' => 6, 'nameEn' => 'Muskmelon', 'nameKn' => 'ಕರಬೂಜ', 'category' => 'summer', 'price' => 50, 'image' => "images\\Muskmelon.jpg", 'desc' => "Fragrant, sweet orange flesh."],
    ['id' => 7, 'nameEn' => 'Jackfruit', 'nameKn' => 'ಹಲಸಿನ ಹಣ್ಣು', 'category' => 'summer', 'price' => 90, 'image' => "images\\Jackfruit.jpg", 'desc' => "Large tropical fruit, sweet pods."],
    ['id' => 8, 'nameEn' => 'Lychee', 'nameKn' => 'ಲಿಚ್ಚಿ', 'category' => 'summer', 'price' => 200, 'image' => "images\\Lychee.jpg", 'desc' => "Juicy, translucent, floral sweet."],
    ['id' => 9, 'nameEn' => 'Jamun', 'nameKn' => 'ನೇರಳೆ', 'category' => 'summer', 'price' => 120, 'image' => "images\\Jamun.jpg", 'desc' => "Purple berry, astringent sweet."],
    ['id' => 10, 'nameEn' => 'Tender Coconut', 'nameKn' => 'ಎಳೆನೀರು', 'category' => 'summer', 'price' => 45, 'image' => "images\\Tender Coconut.jpg", 'desc' => "Refreshing coconut water and soft flesh."],
    ['id' => 11, 'nameEn' => 'Lemon', 'nameKn' => 'ನಿಂಬೆ', 'category' => 'summer', 'price' => 80, 'image' => "images\\Lemon.jpg", 'desc' => "Zesty, rich in Vitamin C."],
    ['id' => 12, 'nameEn' => 'Ice Apple', 'nameKn' => 'ತಾಟಿ ನುಂಗು', 'category' => 'summer', 'price' => 60, 'image' => "images\\Ice Apple.jpg", 'desc' => "Cooling, jelly-like fruit."],
    ['id' => 13, 'nameEn' => 'Kokum Fruit', 'nameKn' => 'ಕೋಕಂ', 'category' => 'summer', 'price' => 200, 'image' => "images\\Kokum Fruit.jpg", 'desc' => "Sour-sweet, used in cooling drinks."],
    ['id' => 14, 'nameEn' => 'Cucumber', 'nameKn' => 'ಸೌತೆಕಾಯಿ', 'category' => 'summer', 'price' => 40, 'image' => "images\\Cucumber.jpg", 'desc' => "Crisp, hydrating salad vegetable."],
    ['id' => 15, 'nameEn' => 'Tomato', 'nameKn' => 'ಟೊಮೇಟೊ', 'category' => 'summer', 'price' => 50, 'image' => "images\\Tomato.jpg", 'desc' => "Ripe, juicy versatile fruit-vegetable."],
    ['id' => 16, 'nameEn' => 'Green Chilli', 'nameKn' => 'ಹಸಿಮೆಣಸು', 'category' => 'summer', 'price' => 80, 'image' => "images\\Green Chilli.jpg", 'desc' => "Spicy, fresh, for heat lovers."],
    ['id' => 17, 'nameEn' => 'Mint Leaves', 'nameKn' => 'ಪುದೀನಾ', 'category' => 'summer', 'price' => 20, 'image' => "images\\Mint Leaves.jpg", 'desc' => "Aromatic, cooling herb."],
    ['id' => 18, 'nameEn' => 'Coriander Leaves', 'nameKn' => 'ಕೊತ್ತಂಬರಿ ಸೊಪ್ಪು', 'category' => 'summer', 'price' => 15, 'image' => "images\\Coriander Leaves.jpg", 'desc' => "Fresh, lemony, for garnishing."],
    ['id' => 19, 'nameEn' => 'Sabja Seeds', 'nameKn' => 'ಸಬ್ಜಾ ಬೀಜ', 'category' => 'summer', 'price' => 300, 'image' => "images\\Sabja Seeds.jpg", 'desc' => "Basil seeds for summer drinks."],
    ['id' => 20, 'nameEn' => 'Fresh Tamarind', 'nameKn' => 'ಹುಣಸೆ', 'category' => 'summer', 'price' => 200, 'image' => "images\\Fresh Tamarind.jpg", 'desc' => "Tangy pulp for chutneys."],
    ['id' => 21, 'nameEn' => 'Sweet Corn', 'nameKn' => 'ಸಿಹಿ ಮೆಕ್ಕೆಜೋಳ', 'category' => 'monsoon', 'price' => 35, 'image' => "images\\Sweet Corn.jpg", 'desc' => "Golden kernels, sweet and tender."],
    ['id' => 22, 'nameEn' => 'Baby Corn', 'nameKn' => 'ಬೇಬಿ ಕಾರ್ನ್', 'category' => 'monsoon', 'price' => 120, 'image' => "images\\Baby Corn.jpg", 'desc' => "Mini corn cobs, crunchy."],
    ['id' => 23, 'nameEn' => 'Mushroom', 'nameKn' => 'ಕುಕ್', 'category' => 'monsoon', 'price' => 180, 'image' => "images\\Mushroom.jpg", 'desc' => "Fresh button mushrooms."],
    ['id' => 24, 'nameEn' => 'Green Peas', 'nameKn' => 'ಹಸಿ ಬಟಾಣಿ', 'category' => 'monsoon', 'price' => 100, 'image' => "images\\Green Peas.jpg", 'desc' => "Sweet, plump, freshly shelled."],
    ['id' => 25, 'nameEn' => 'Cluster Beans', 'nameKn' => 'ಗೋರಿಕಾಯಿ', 'category' => 'monsoon', 'price' => 70, 'image' => "images\\Cluster Beans.jpg", 'desc' => "Fibrous, mildly bitter beans."],
    ['id' => 26, 'nameEn' => 'Snake Gourd', 'nameKn' => 'ಪಡುವಲಕಾಯಿ', 'category' => 'monsoon', 'price' => 60, 'image' => "images\\Snake Gourd.jpg", 'desc' => "Long, mild-flavored gourd."],
    ['id' => 27, 'nameEn' => 'Ridge Gourd', 'nameKn' => 'ಹೀರೆಕಾಯಿ', 'category' => 'monsoon', 'price' => 60, 'image' => "images\\Ridge Gourd.jpg", 'desc' => "Spongy texture, good for curries."],
    ['id' => 28, 'nameEn' => 'Bottle Gourd', 'nameKn' => 'ಸೋರೆಕಾಯಿ', 'category' => 'monsoon', 'price' => 40, 'image' => "images\\Bottle Gourd.jpg", 'desc' => "Light green, high water content."],
    ['id' => 29, 'nameEn' => 'Bitter Gourd', 'nameKn' => 'ಹಾಗಲಕಾಯಿ', 'category' => 'monsoon', 'price' => 80, 'image' => "images\\Bitter Gourd.jpg", 'desc' => "Distinct bitter taste, healthy."],
    ['id' => 30, 'nameEn' => 'Ivy Gourd', 'nameKn' => 'ತೊಂಡೆಕಾಯಿ', 'category' => 'monsoon', 'price' => 70, 'image' => "images\\Ivy Gourd.jpg", 'desc' => "Small, crunchy, stir-fry favorite."],
    ['id' => 31, 'nameEn' => 'Carrot', 'nameKn' => 'ಗಜ್ಜರಿ', 'category' => 'winter', 'price' => 50, 'image' => "images\\Carrot.jpg", 'desc' => "Sweet, crunchy orange root."],
    ['id' => 32, 'nameEn' => 'Beetroot', 'nameKn' => 'ಬೀಟ್ರೂಟ್', 'category' => 'winter', 'price' => 45, 'image' => "images\\Beetroot.jpg", 'desc' => "Earthy, deep red vegetable."],
    ['id' => 33, 'nameEn' => 'Radish', 'nameKn' => 'ಮೂಲಂಗಿ', 'category' => 'winter', 'price' => 40, 'image' => "images\\Radish.jpg", 'desc' => "White, peppery, crisp radish."],
    ['id' => 34, 'nameEn' => 'Cauliflower', 'nameKn' => 'ಹೂಕೋಸು', 'category' => 'winter', 'price' => 60, 'image' => "images\\Cauliflower.jpg", 'desc' => "White florets, versatile vegetable."],
    ['id' => 35, 'nameEn' => 'Cabbage', 'nameKn' => 'ಎಲೆಕೋಸು', 'category' => 'winter', 'price' => 40, 'image' => "images\\Cabbage.jpg", 'desc' => "Dense, leafy, great for salads."],
    ['id' => 36, 'nameEn' => 'Spinach', 'nameKn' => 'ಸೊಪ್ಪು', 'category' => 'winter', 'price' => 20, 'image' => "images\\Spinach.jpg", 'desc' => "Iron-rich leafy green."],
    ['id' => 37, 'nameEn' => 'Fenugreek Leaves', 'nameKn' => 'ಮೆಂತೆ ಸೊಪ್ಪು', 'category' => 'winter', 'price' => 25, 'image' => "images\\Fenugreek Leaves.jpg", 'desc' => "Slightly bitter, aromatic herb."],
    ['id' => 38, 'nameEn' => 'Fresh Peas', 'nameKn' => 'ಹಸಿ ಬಟಾಣಿ', 'category' => 'winter', 'price' => 120, 'image' => "images\\Fresh Peas.jpg", 'desc' => "Sweet, tender green peas."],
    ['id' => 39, 'nameEn' => 'Turnip', 'nameKn' => 'ಟರ್ನಿಪ್', 'category' => 'winter', 'price' => 50, 'image' => "images\\Turnip.jpg", 'desc' => "Mild, slightly sweet root."],
    ['id' => 40, 'nameEn' => 'Green Garlic', 'nameKn' => 'ಹಸಿ ಬೆಳ್ಳುಳ್ಳಿ', 'category' => 'winter', 'price' => 80, 'image' => "images\\Green Garlic.jpg", 'desc' => "Fresh, milder than dried garlic."],
    ['id' => 41, 'nameEn' => 'Sugarcane', 'nameKn' => 'ಕಬ್ಬು', 'category' => 'festival', 'price' => 40, 'image' => "images\\Sugarcane.jpg", 'desc' => "Sweet juice sticks."],
    ['id' => 42, 'nameEn' => 'Banana Leaf', 'nameKn' => 'ಬಾಳೆ ಎಲೆ', 'category' => 'festival', 'price' => 15, 'image' => "images\\Banana Leaf.jpg", 'desc' => "Natural plate for festive meals."],
    ['id' => 43, 'nameEn' => 'Coconut', 'nameKn' => 'ತೆಂಗಿನಕಾಯಿ', 'category' => 'festival', 'price' => 35, 'image' => "images\\Coconut.jpg", 'desc' => "Mature coconut for cooking."],
    ['id' => 44, 'nameEn' => 'Betel Leaves', 'nameKn' => 'ವೀಳ್ಯದೆಲೆ', 'category' => 'festival', 'price' => 20, 'image' => "images\\Betel Leaves.jpg", 'desc' => "Sacred leaves for rituals."],
    ['id' => 45, 'nameEn' => 'Betel Nuts', 'nameKn' => 'ಅಡಿಕೆ', 'category' => 'festival', 'price' => 200, 'image' => "images\\Betel Nuts.jpg", 'desc' => "Dried areca nut."],
    ['id' => 46, 'nameEn' => 'Fresh Turmeric', 'nameKn' => 'ಹಸಿ ಅರಿಶಿನ', 'category' => 'festival', 'price' => 90, 'image' => "images\\Fresh Turmeric.jpg", 'desc' => "Raw turmeric root."],
    ['id' => 47, 'nameEn' => 'Fresh Ginger', 'nameKn' => 'ಹಸಿ ಶುಂಠಿ', 'category' => 'festival', 'price' => 120, 'image' => "images\\Fresh Ginger.jpg", 'desc' => "Spicy, aromatic rhizome."],
    ['id' => 48, 'nameEn' => 'Pumpkin', 'nameKn' => 'ಕುಂಬಳಕಾಯಿ', 'category' => 'festival', 'price' => 50, 'image' => "images\\Pumpkin.jpg", 'desc' => "Sweet orange flesh."],
    ['id' => 49, 'nameEn' => 'Ash Gourd', 'nameKn' => 'ಬೂದುಕುಂಬಳ', 'category' => 'festival', 'price' => 40, 'image' => "images\\Ash Gourd.jpg", 'desc' => "White-fleshed winter melon."],
    ['id' => 50, 'nameEn' => 'Banana Flower', 'nameKn' => 'ಬಾಳೆ ಹೂವು', 'category' => 'festival', 'price' => 40, 'image' => "images\\Banana Flower.jpg", 'desc' => "Purple flower, used in salads."],
    ['id' => 51, 'nameEn' => 'Guava', 'nameKn' => 'ಪೇರಳೆ', 'category' => 'fruits', 'price' => 70, 'image' => "images\\Guava.jpg", 'desc' => "Tangy-sweet, rich in vitamin C."],
    ['id' => 52, 'nameEn' => 'Pineapple', 'nameKn' => 'ಅನಾನಸ್', 'category' => 'fruits', 'price' => 60, 'image' => "images\\Pineapple.jpg", 'desc' => "Juicy, tropical, sweet-tart."],
    ['id' => 53, 'nameEn' => 'Papaya', 'nameKn' => 'ಪಪ್ಪಾಯ', 'category' => 'fruits', 'price' => 50, 'image' => "images\\Papaya.jpg", 'desc' => "Soft, orange, digestive enzymes."],
    ['id' => 54, 'nameEn' => 'Dragon Fruit', 'nameKn' => 'ಡ್ರ್ಯಾಗನ್ ಹಣ್ಣು', 'category' => 'fruits', 'price' => 120, 'image' => "images\\Dragon Fruit.jpg", 'desc' => "Mild sweet, pink/white flesh."],
    ['id' => 55, 'nameEn' => 'Avocado', 'nameKn' => 'ಆವಕಾಡೊ', 'category' => 'fruits', 'price' => 200, 'image' => "images\\Avocado.jpg", 'desc' => "Buttery, nutrient-dense fruit."],
    ['id' => 56, 'nameEn' => 'Strawberry', 'nameKn' => 'ಸ್ಟ್ರಾಬೆರಿ', 'category' => 'fruits', 'price' => 250, 'image' => "images\\Strawberry.jpg", 'desc' => "Sweet red berries."],
    ['id' => 57, 'nameEn' => 'Blackberry', 'nameKn' => 'ಬ್ಲ್ಯಾಕ್ಬೆರಿ', 'category' => 'fruits', 'price' => 300, 'image' => "images\\Blackberry.jpg", 'desc' => "Dark, tart-sweet berries."],
    ['id' => 58, 'nameEn' => 'Blueberry', 'nameKn' => 'ಬ್ಲೂಬೆರಿ', 'category' => 'fruits', 'price' => 350, 'image' => "images\\Blueberry.jpg", 'desc' => "Tiny, antioxidant-rich."],
    ['id' => 59, 'nameEn' => 'Mulberry', 'nameKn' => 'ಮಲ್ಬೆರಿ', 'category' => 'fruits', 'price' => 220, 'image' => "images\\Mulberry.jpg", 'desc' => "Sweet, juicy, dark purple."],
    ['id' => 60, 'nameEn' => 'Passion Fruit', 'nameKn' => 'ಪ್ಯಾಶನ್ ಹಣ್ಣು', 'category' => 'fruits', 'price' => 200, 'image' => "images\\Passion Fruit.jpg", 'desc' => "Aromatic, tart, seed-filled."],
    ['id' => 61, 'nameEn' => 'Broccoli', 'nameKn' => 'ಬ್ರೊಕೋಲಿ', 'category' => 'premium', 'price' => 180, 'image' => "images\\Broccoli.jpg", 'desc' => "Green florets, nutrient-rich."],
    ['id' => 62, 'nameEn' => 'Zucchini', 'nameKn' => 'ಝುಚಿನಿ', 'category' => 'premium', 'price' => 160, 'image' => "images\\Zucchini.jpg", 'desc' => "Mild, tender summer squash."],
    ['id' => 63, 'nameEn' => 'Lettuce', 'nameKn' => 'ಲೆಟಿಸ್', 'category' => 'premium', 'price' => 120, 'image' => "images\\Lettuce.jpg", 'desc' => "Crunchy salad green."],
    ['id' => 64, 'nameEn' => 'Kale', 'nameKn' => 'ಕೇಲ್', 'category' => 'premium', 'price' => 200, 'image' => "images\\Kale.jpg", 'desc' => "Curly leafy superfood."],
    ['id' => 65, 'nameEn' => 'Cherry Tomato', 'nameKn' => 'ಚೆರ್ರಿ ಟೊಮೇಟೊ', 'category' => 'premium', 'price' => 150, 'image' => "images\\Cherry Tomato.jpg", 'desc' => "Small, sweet, bite-sized."],
    ['id' => 66, 'nameEn' => 'Baby Potato', 'nameKn' => 'ಚಿಕ್ಕ ಆಲೂಗಡ್ಡೆ', 'category' => 'premium', 'price' => 60, 'image' => "images\\Baby Potato.jpg", 'desc' => "Tiny, creamy potatoes."],
    ['id' => 67, 'nameEn' => 'Red Capsicum', 'nameKn' => 'ಕೆಂಪು ದೊಣ್ಣೆ ಮೆಣಸು', 'category' => 'premium', 'price' => 180, 'image' => "images\\Red Capsicum.jpg", 'desc' => "Sweet, bell pepper."],
    ['id' => 68, 'nameEn' => 'Yellow Capsicum', 'nameKn' => 'ಹಳದಿ ದೊಣ್ಣೆ ಮೆಣಸು', 'category' => 'premium', 'price' => 200, 'image' => "images\\Yellow Capsicum.jpg", 'desc' => "Sunny sweet pepper."],
    ['id' => 69, 'nameEn' => 'Green Capsicum', 'nameKn' => 'ಹಸಿ ದೊಣ್ಣೆ ಮೆಣಸು', 'category' => 'premium', 'price' => 120, 'image' => "images\\Green Capsicum.jpg", 'desc' => "Mild, crunchy bell pepper."],
    ['id' => 70, 'nameEn' => 'Purple Cabbage', 'nameKn' => 'ನೇರಳೆ ಎಲೆಕೋಸು', 'category' => 'premium', 'price' => 150, 'image' => "images\\Purple Cabbage.jpg", 'desc' => "Vibrant, antioxidant-rich."],
    ['id' => 71, 'nameEn' => 'Fruit Basket', 'nameKn' => 'ಹಣ್ಣುಗಳ ಬುಟ್ಟಿ', 'category' => 'combo', 'price' => 500, 'image' => "images\\Fruit Basket.jpg", 'desc' => "Assorted seasonal fruits."],
    ['id' => 72, 'nameEn' => 'Premium Fruit Basket', 'nameKn' => 'ಪ್ರೀಮಿಯಂ ಹಣ್ಣಿನ ಬುಟ್ಟಿ', 'category' => 'combo', 'price' => 800, 'image' => "images\\Premium Fruit Basket.jpg", 'desc' => "Exotic fruit selection."],
    ['id' => 73, 'nameEn' => 'Festival Fruit Combo', 'nameKn' => 'ಹಬ್ಬದ ಹಣ್ಣು ಕಾಂಬೊ', 'category' => 'combo', 'price' => 1000, 'image' => "images\\Festival Fruit Combo.jpg", 'desc' => "Special festive mix."],
    ['id' => 74, 'nameEn' => 'Seasonal Veg Pack', 'nameKn' => 'ಋತುಮಾನದ ತರಕಾರಿ ಪ್ಯಾಕ್', 'category' => 'combo', 'price' => 150, 'image' => "images\\Seasonal Veg Pack.jpg", 'desc' => "Curated seasonal vegetables."],
    ['id' => 75, 'nameEn' => 'Organic Veg Pack', 'nameKn' => 'ಸಾವಯವ ತರಕಾರಿ ಪ್ಯಾಕ್', 'category' => 'combo', 'price' => 300, 'image' => "images\\Organic Veg Pack.jpg", 'desc' => "Certified organic veggies."],
    ['id' => 76, 'nameEn' => 'Organic Fruit Pack', 'nameKn' => 'ಸಾವಯವ ಹಣ್ಣಿನ ಪ್ಯಾಕ್', 'category' => 'combo', 'price' => 350, 'image' => "images\\Organic Fruit Pack.jpg", 'desc' => "Organic fruit assortment."],
    ['id' => 77, 'nameEn' => 'Salad Pack', 'nameKn' => 'ಸಲಾಡ್ ಪ್ಯಾಕ್', 'category' => 'combo', 'price' => 120, 'image' => "images\\Salad Pack.jpg", 'desc' => "Fresh greens and veggies."],
    ['id' => 78, 'nameEn' => 'Family Veg Basket', 'nameKn' => 'ಕುಟುಂಬ ತರಕಾರಿ ಬುಟ್ಟಿ', 'category' => 'combo', 'price' => 250, 'image' => "images\\Family Veg Basket.jpg", 'desc' => "Family-sized veg pack."],
    ['id' => 81, 'nameEn' => 'Drumstick', 'nameKn' => 'ನುಗ್ಗೆಕಾಯಿ', 'category' => 'vegetables', 'price' => 120, 'image' => "images\\Drumstick.jpg", 'desc' => "Long, nutrient-rich pod."],
    ['id' => 82, 'nameEn' => 'Raw Banana', 'nameKn' => 'ಬಾಳೆಕಾಯಿ', 'category' => 'vegetables', 'price' => 50, 'image' => "images\\Raw Banana.jpg", 'desc' => "Green, starchy cooking banana."],
    ['id' => 83, 'nameEn' => 'Sweet Potato', 'nameKn' => 'ಸಿಹಿ ಗೆಣಸು', 'category' => 'vegetables', 'price' => 60, 'image' => "images\\Sweet Potato.jpg", 'desc' => "Sweet, orange-fleshed root."],
    ['id' => 84, 'nameEn' => 'Taro Root', 'nameKn' => 'ಕೆಸುವಿನ ಗೆಡ್ಡೆ', 'category' => 'vegetables', 'price' => 70, 'image' => "images\\Taro Root.jpg", 'desc' => "Nutty, starchy corm."],
    ['id' => 85, 'nameEn' => 'Yam', 'nameKn' => 'ಸುವರ್ಣ ಗೆಡ್ಡೆ', 'category' => 'vegetables', 'price' => 80, 'image' => "images\\Yam.jpg", 'desc' => "Dense, tuber vegetable."],
    ['id' => 86, 'nameEn' => 'Tapioca Root', 'nameKn' => 'ಮರಗೆಣಸು', 'category' => 'vegetables', 'price' => 60, 'image' => "images\\Tapioca Root.jpg", 'desc' => "Used for chips and starch."],
    ['id' => 87, 'nameEn' => 'Indian Gooseberry', 'nameKn' => 'ನೆಲ್ಲಿಕಾಯಿ', 'category' => 'fruits', 'price' => 120, 'image' => "images\\Indian Gooseberry.jpg", 'desc' => "Sour, vitamin C powerhouse."],
    ['id' => 88, 'nameEn' => 'Custard Apple', 'nameKn' => 'ಸೀತಾಫಲ', 'category' => 'fruits', 'price' => 200, 'image' => "images\\Custard Apple.jpg", 'desc' => "Creamy, sweet, seedy."],
    ['id' => 89, 'nameEn' => 'Sapota', 'nameKn' => 'ಸಪೋಟ', 'category' => 'fruits', 'price' => 80, 'image' => "images\\Sapota.jpg", 'desc' => "Brown, sweet, grainy flesh."],
    ['id' => 90, 'nameEn' => 'Star Fruit', 'nameKn' => 'ನಕ್ಷತ್ರ ಹಣ್ಣು', 'category' => 'fruits', 'price' => 150, 'image' => "images\\Star Fruit.jpg", 'desc' => "Star-shaped, tangy-sweet."],
    ['id' => 91, 'nameEn' => 'Fig Fruit', 'nameKn' => 'ಅಂಜೂರ', 'category' => 'fruits', 'price' => 300, 'image' => "images\\Fig Fruit.jpg", 'desc' => "Soft, sweet, seedy interior."],
    ['id' => 92, 'nameEn' => 'Persimmon', 'nameKn' => 'ಪರ್ಸಿಮನ್', 'category' => 'fruits', 'price' => 220, 'image' => "images\\Persimmon.jpg", 'desc' => "Orange, sweet, honey-like."],
    ['id' => 93, 'nameEn' => 'Rambutan', 'nameKn' => 'ರಾಂಬುಟಾನ್', 'category' => 'fruits', 'price' => 350, 'image' => "images\\Rambutan.jpg", 'desc' => "Hairy exterior, sweet flesh."],
    ['id' => 94, 'nameEn' => 'Mangosteen', 'nameKn' => 'ಮ್ಯಾಂಗೋಸ್ಟೀನ್', 'category' => 'fruits', 'price' => 400, 'image' => "images\\Mangosteen.jpg", 'desc' => "Purple rind, sweet segments."],
    ['id' => 95, 'nameEn' => 'Longan Fruit', 'nameKn' => 'ಲಾಂಗನ್', 'category' => 'fruits', 'price' => 320, 'image' => "images\\Longan Fruit.jpg", 'desc' => "Small, translucent, lychee-like."],
    ['id' => 96, 'nameEn' => 'Pomelo', 'nameKn' => 'ಪೊಮೆಲೊ', 'category' => 'fruits', 'price' => 180, 'image' => "images\\Pomelo.jpg", 'desc' => "Large citrus, sweet-tart."],
    ['id' => 98, 'nameEn' => 'Plantain Banana', 'nameKn' => 'ಬಾಳೆಹಣ್ಣು', 'category' => 'fruits', 'price' => 80, 'image' => "images\\Plantain Banana.jpg", 'desc' => "Starchy, cooking variety."],
    ['id' => 99, 'nameEn' => 'Baby Banana', 'nameKn' => 'ಚಿಕ್ಕ ಬಾಳೆ', 'category' => 'fruits', 'price' => 70, 'image' => "images\\Baby Banana.jpg", 'desc' => "Mini, sweet dessert banana."],
    ['id' => 100, 'nameEn' => 'Mini Pineapple', 'nameKn' => 'ಸಣ್ಣ ಅನಾನಸ್', 'category' => 'fruits', 'price' => 50, 'image' => "images\\Mini Pineapple.jpg", 'desc' => "Petite, extra sweet pineapple."],
    ['id' => 101, 'nameEn' => 'Fresh Basil', 'nameKn' => 'ತುಳಸಿ', 'category' => 'premium', 'price' => 80, 'image' => "images\\Fresh Basil.jpg", 'desc' => "Aromatic herb, sacred plant."],
    ['id' => 102, 'nameEn' => 'Parsley', 'nameKn' => 'ಪಾರ್ಸ್ಲಿ', 'category' => 'premium', 'price' => 90, 'image' => "images\\Parsley.jpg", 'desc' => "Fresh, mild, for garnish."],
    ['id' => 103, 'nameEn' => 'Dill Leaves', 'nameKn' => 'ಸಬ್ಬಸಿಗೆ ಸೊಪ್ಪು', 'category' => 'premium', 'price' => 80, 'image' => "images\\Dill Leaves.jpg", 'desc' => "Feathery, anise-flavored herb."],
    ['id' => 104, 'nameEn' => 'Spring Onion', 'nameKn' => 'ಈರುಳ್ಳಿ ಸೊಪ್ಪು', 'category' => 'premium', 'price' => 60, 'image' => "images\\Spring Onion.jpg", 'desc' => "Mild onion flavor."],
    ['id' => 105, 'nameEn' => 'Celery', 'nameKn' => 'ಸೆಲರಿ', 'category' => 'premium', 'price' => 200, 'image' => "images\\Celery.jpg", 'desc' => "Crunchy, slightly salty stalks."],
    ['id' => 106, 'nameEn' => 'Leek', 'nameKn' => 'ಲೀಕ್', 'category' => 'premium', 'price' => 180, 'image' => "images\\Leek.jpg", 'desc' => "Mild onion-like vegetable."],
    ['id' => 107, 'nameEn' => 'Fresh Curry Leaves', 'nameKn' => 'ಕರಿಬೇವು', 'category' => 'premium', 'price' => 10, 'image' => "images\\Fresh Curry Leaves.jpg", 'desc' => "Aromatic, essential for tempering."],
    ['id' => 108, 'nameEn' => 'Organic Spinach', 'nameKn' => 'ಸಾವಯವ ಸೊಪ್ಪು', 'category' => 'premium', 'price' => 40, 'image' => "images\\Organic Spinach.jpg", 'desc' => "Chemical-free leafy greens."],
    ['id' => 109, 'nameEn' => 'Organic Carrot', 'nameKn' => 'ಸಾವಯವ ಗಜ್ಜರಿ', 'category' => 'premium', 'price' => 80, 'image' => "images\\Organic Carrot.jpg", 'desc' => "Naturally grown carrots."],
    ['id' => 110, 'nameEn' => 'Organic Beetroot', 'nameKn' => 'ಸಾವಯವ ಬೀಟ್ರೂಟ್', 'category' => 'premium', 'price' => 80, 'image' => "images\\Organic Beetroot.jpg", 'desc' => "Organic, earthy root."],
    ['id' => 111, 'nameEn' => 'Organic Tomato', 'nameKn' => 'ಸಾವಯವ ಟೊಮೇಟೊ', 'category' => 'premium', 'price' => 90, 'image' => "images\\Organic Tomato.jpg", 'desc' => "Farm-fresh organic tomatoes."],
    ['id' => 112, 'nameEn' => 'Organic Potato', 'nameKn' => 'ಸಾವಯವ ಆಲೂಗಡ್ಡೆ', 'category' => 'premium', 'price' => 70, 'image' => "images\\Organic Potato.jpg", 'desc' => "Organic, non-GMO potatoes."],
    ['id' => 113, 'nameEn' => 'Organic Onion', 'nameKn' => 'ಸಾವಯವ ಈರುಳ್ಳಿ', 'category' => 'premium', 'price' => 80, 'image' => "images\\Organic Onion.jpg", 'desc' => "Sweet, organic onions."],
    ['id' => 114, 'nameEn' => 'Organic Lemon', 'nameKn' => 'ಸಾವಯವ ನಿಂಬೆ', 'category' => 'premium', 'price' => 100, 'image' => "images\\Organic Lemon.jpg", 'desc' => "Pesticide-free lemons."],
    ['id' => 115, 'nameEn' => 'Organic Cucumber', 'nameKn' => 'ಸಾವಯವ ಸೌತೆಕಾಯಿ', 'category' => 'premium', 'price' => 70, 'image' => "images\\Organic Cucumber.jpg", 'desc' => "Crunchy organic cucumber."],
    ['id' => 116, 'nameEn' => 'Organic Capsicum', 'nameKn' => 'ಸಾವಯವ ದೊಣ್ಣೆ ಮೆಣಸು', 'category' => 'premium', 'price' => 200, 'image' => "images\\Organic Capsicum.jpg", 'desc' => "Colorful organic bell peppers."],
    ['id' => 117, 'nameEn' => 'Organic Broccoli', 'nameKn' => 'ಸಾವಯವ ಬ್ರೊಕೋಲಿ', 'category' => 'premium', 'price' => 220, 'image' => "images\\Organic Broccoli.jpg", 'desc' => "Fresh organic broccoli."],
    ['id' => 118, 'nameEn' => 'Organic Lettuce', 'nameKn' => 'ಸಾವಯವ ಲೆಟಿಸ್', 'category' => 'premium', 'price' => 150, 'image' => "images\\Organic Lettuce.jpg", 'desc' => "Crisp organic salad greens."]
  ];
  foreach ($staticProducts as $item) {
    $productsData[] = [
      'id' => $item['id'],
      'name' => $item['nameEn'],
      'kn' => $item['nameKn'],
      'category' => $item['category'],
      'price' => $item['price'],
      'image' => $item['image'],
      'desc' => $item['desc']
    ];
  }
}
// Pre-define category labels for display
$categoryLabels = [
  'summer' => 'Summer / ಬೇಸಿಗೆ',
  'monsoon' => 'Monsoon / ಮಳೆಗಾಲ',
  'winter' => 'Winter / ಚಳಿಗಾಲ',
  'festival' => 'Festival / ಹಬ್ಬ',
  'fruits' => 'Fruits / ಹಣ್ಣುಗಳು',
  'vegetables' => 'Veggies / ತರಕಾರಿಗಳು',
  'premium' => 'Premium / ಪ್ರೀಮಿಯಂ',
  'combo' => 'Combo / ಕಾಂಬೊ'
];
$uniqueCategories = array_unique(array_column($productsData, 'category'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
  <title>Seasonal Items · ಋತುಮಾನದ ವಸ್ತುಗಳು</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <style>
    /* ===== SAME CSS AS STATIC VERSION ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      display: block;
    }

    #app {
      max-width: 1500px;
      width: 100%;
      background: white;
      border-radius: 2rem;
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.05);
      padding: 1.5rem;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 2rem;
      border-bottom: 3px solid #ffdfc4;
      padding-bottom: 0.5rem;
    }

    .shop-name h1 {
      font-size: 2rem;
      font-weight: 700;
      color: #c25e2a;
    }

    .shop-name .kannada {
      font-size: 0.9rem;
      color: #e07c3c;
      margin-top: 0.2rem;
    }

    .cart-link {
      background: #ffede0;
      padding: 0.7rem 1.3rem;
      border-radius: 60px;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      transition: all 0.2s ease;
      border: 1px solid #ffd9ba;
      text-decoration: none;
      color: #c25e2a;
      font-weight: 600;
      font-size: 0.95rem;
    }

    .cart-link i {
      font-size: 1.4rem;
      color: #e07c3c;
    }

    .cart-link .cart-badge {
      background: #e07c3c;
      color: white;
      padding: 0.1rem 0.7rem;
      border-radius: 40px;
      font-weight: 700;
      font-size: 0.9rem;
      margin-left: 4px;
    }

    .cart-link:hover {
      background: #ffe3d4;
      transform: scale(1.01);
    }

    .search-section {
      margin-bottom: 2rem;
    }

    .search-box {
      display: flex;
      align-items: center;
      background: #fef6ef;
      border-radius: 80px;
      padding: 0.2rem 0.2rem 0.2rem 1.8rem;
      border: 1px solid #ffdcc2;
    }

    .search-box i {
      color: #e07c3c;
      font-size: 1.1rem;
    }

    .search-box input {
      flex: 1;
      border: none;
      background: transparent;
      padding: 1rem 0.8rem;
      font-size: 1rem;
      outline: none;
    }

    .search-box button {
      background: #e07c3c;
      border: none;
      color: white;
      padding: 0.7rem 1.8rem;
      border-radius: 60px;
      cursor: pointer;
      font-weight: 600;
    }

    .filter-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 0.7rem;
      margin: 1.5rem 0 0.5rem;
    }

    .pill {
      background: #ffede0;
      color: #c25e2a;
      padding: 0.5rem 1.3rem;
      border-radius: 40px;
      font-size: 0.85rem;
      font-weight: 600;
      border: 1px solid #ffdcbf;
      cursor: pointer;
    }

    .pill.active {
      background: #e07c3c;
      color: white;
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
      gap: 1.8rem;
      margin: 2rem 0;
    }

    .product-card {
      background: white;
      border-radius: 28px;
      padding: 1.2rem;
      border: 1px solid #fae1ce;
      transition: all 0.2s;
    }

    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 24px rgba(224, 124, 60, 0.1);
    }

    .product-img {
      width: 70px;
      height: 70px;
      object-fit: cover;
      border-radius: 20px;
      background: #fef3ea;
      margin-bottom: 1rem;
      border: 1px solid #ffdfcb;
    }

    .product-name {
      font-weight: 700;
      font-size: 1.2rem;
      color: #3e2a21;
    }

    .product-name-kn {
      font-size: 0.8rem;
      color: #9b7b64;
      margin-bottom: 0.3rem;
    }

    .product-desc {
      font-size: 0.75rem;
      color: #ad886e;
      background: #fff7f0;
      padding: 6px 10px;
      border-radius: 40px;
      margin: 8px 0;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 8px;
    }

    .price {
      font-weight: 800;
      font-size: 1.4rem;
      color: #e07c3c;
    }

    .add-btn {
      background: #ffede0;
      border: none;
      padding: 8px 16px;
      border-radius: 50px;
      font-weight: 700;
      font-size: 0.8rem;
      color: #c25e2a;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      border: 1px solid #ffd2b5;
    }

    .add-btn:hover {
      background: #e07c3c;
      color: white;
    }

    .no-results {
      text-align: center;
      padding: 3rem;
      background: #fff9f2;
      border-radius: 60px;
      display: none;
    }

    footer {
      text-align: center;
      color: #cfb9a7;
      border-top: 1px solid #ffebdd;
      padding-top: 1.5rem;
      margin-top: 1rem;
    }

    @media (max-width: 640px) {
      #app {
        padding: 1rem;
      }

      .product-grid {
        gap: 1rem;
      }
    }
  </style>
  <link rel="stylesheet" href="category-style.css">
  <link rel="stylesheet" href="../index.css">
</head>

<body>
  <div id="app">
    <!-- header -->
    <?php
    $base_url = '../';
    include __DIR__ . '/../includes/global-header.php';
    ?>
    <!-- search & filter -->
    <div class="search-section">
      <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ">
        <button id="searchBtn">🔍 Search</button>
      </div>
      <div class="filter-pills" id="filterPills">
        <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
        <?php foreach ($uniqueCategories as $cat): ?>
          <div class="pill" data-category="<?php echo htmlspecialchars($cat); ?>">
            <?php echo isset($categoryLabels[$cat]) ? htmlspecialchars($categoryLabels[$cat]) : htmlspecialchars($cat); ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- product grid -->
    <div class="product-grid" id="productGrid"></div>
    <div class="no-results" id="noResultsMsg">😕 No items found · ದಯವಿಟ್ಟು ಬೇರೆ ಹುಡುಕಿ</div>
  </div>
  <script>
    // ============================================================
    // JAVASCRIPT – SESSION-BASED CART VIA AJAX + LOCALSTORAGE FALLBACK
    // ============================================================
    (function() {
      // ----- PRODUCT DATA FROM PHP (embedded) -----
      const productsData = <?php echo json_encode($productsData); ?>;
      // Normalize product images
      if (typeof productsData !== 'undefined' && Array.isArray(productsData)) {
        productsData.forEach(p => {
          let imgUrl = p.imageUrl || p.image || p.imageurl || '';
          if (imgUrl && !imgUrl.startsWith('http') && !imgUrl.startsWith('data:')) {
            const idx = imgUrl.toLowerCase().indexOf('images');
            if (idx !== -1) {
              imgUrl = '../' + imgUrl.substring(idx).replace(/\\/g, '/');
            } else if (!imgUrl.startsWith('../') && !imgUrl.startsWith('images/')) {
              const cleanUrl = imgUrl.startsWith('/') ? imgUrl.substring(1) : imgUrl;
              imgUrl = '../' + cleanUrl;
            }
          }
          if (p.imageUrl !== undefined) p.imageUrl = imgUrl;
          if (p.image !== undefined) p.image = imgUrl;
          if (p.imageurl !== undefined) p.imageurl = imgUrl;
        });
      }
      // ----- CART STATE (localStorage, but we also sync via AJAX to server) -----
      let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
      let activeCategory = 'all';
      let searchTerm = '';
      const productGrid = document.getElementById('productGrid');
      const cartCountSpan = document.getElementById('cartCount');
      const searchInput = document.getElementById('searchInput');
      const searchBtn = document.getElementById('searchBtn');
      const filterPills = document.querySelectorAll('.pill');
      const noResultsMsg = document.getElementById('noResultsMsg');
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
      // ----- RENDER PRODUCTS -----
      function renderProducts() {
        const filtered = productsData.filter(p => {
          const catMatch = activeCategory === 'all' || p.category === activeCategory;
          const term = searchTerm.trim().toLowerCase();
          const searchMatch = term === '' || p.name.toLowerCase().includes(term) || p.kn.toLowerCase().includes(term) || p.desc.toLowerCase().includes(term);
          return catMatch && searchMatch;
        });
        if (filtered.length === 0) {
          productGrid.innerHTML = '';
          noResultsMsg.style.display = 'block';
          return;
        }
        noResultsMsg.style.display = 'none';
        productGrid.innerHTML = filtered.map(prod => `
            <div class="product-card">
                <img class="product-img" src="${prod.image}" alt="${prod.name}" loading="lazy" onerror="this.src='https://placehold.co/70x70/fef3ea/e07c3c?text=🌿'">
                <div class="product-name">${prod.name}</div>
                <div class="product-name-kn">${prod.kn}</div>
                <div class="product-desc"><i class="fa-regular fa-comment-dots"></i> ${prod.desc}</div>
                <div class="price-row">
                    <span class="price">₹${prod.price}</span>
                    <button class="add-btn" data-id="${prod.id}" title="Add to cart / ಕಾರ್ಟ್‌ಗೆ ಸೇರಿಸಿ">
                        <i class="fa-solid fa-cart-plus"></i> Add to cart
                    </button>
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
      searchBtn.addEventListener('click', function() {
        searchTerm = searchInput.value;
        renderProducts();
      });
      searchInput.addEventListener('keyup', e => {
        if (e.key === 'Enter') {
          searchTerm = searchInput.value;
          renderProducts();
        }
      });
      // ----- INIT -----
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