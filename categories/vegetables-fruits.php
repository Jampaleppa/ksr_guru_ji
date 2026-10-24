<?php
// ============================================================
// vegetables-fruits.php - Dynamic Vegetables & Fruits page
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
$categorySlug = 'vegetables-fruits';
$vegCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($vegCategory) {
    // Fetch products from this category
    $dbProducts = getProductsByCategory($vegCategory['id']);
    if (!empty($dbProducts)) {
        foreach ($dbProducts as $p) {
            // We need to map subcategories (based on a 'subcategory' field or category name)
            // For now, we'll use category_slug if available, else we'll fallback to a default.
            $subcategory = $p['subcategory'] ?? 'indianMain';
            // Price per kg: we assume price is per kg, or we can compute from weight.
            $pricePerKg = $p['price']; // assuming price is per kg
            $productsData[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'kannada' => $p['kn_name'] ?? $p['name'],
                'desc' => $p['description_en'] ?? 'Fresh & farm picked',
                'pricePerKg' => $pricePerKg,
                'category' => $subcategory,
                'imageUrl' => $p['image_url'] ?? ''
            ];
        }
    }
}
// Fallback to static data if no DB products found
if (empty($productsData)) {
    // Use a JSON string to define the fallback products (valid JSON)
    $fallbackJson = '[
  {"id":1,"name":"Apple","nameKn":"ಸೇಬು","category":"globalFruits","description":"Crisp & sweet","image":"images\\\\Apple.jpg","weights":[{"label":"1 kg","price":180}]},
  {"id":2,"name":"GreenApple","nameKn":"ಹಸಿರು ಸೇಬು","category":"globalFruits","description":"Tangy crisp","image":"images\\\\Green Apple.jpg","weights":[{"label":"1 kg","price":220}]},
  {"id":3,"name":"Banana","nameKn":"ಬಾಳೆ ಹಣ್ಣು","category":"indianFruits","description":"Energy booster","image":"images\\\\Banana.jpg","weights":[{"label":"1 dozen","price":50}]},
  {"id":4,"name":"Orange","nameKn":"ಕಿತ್ತಳೆ","category":"dailyFruits","description":"Juicy & tangy","image":"images\\\\Orange.jpg","weights":[{"label":"1 kg","price":90}]},
  {"id":5,"name":"Mango","nameKn":"ಮಾವಿನ ಹಣ್ಣು","category":"indianFruits","description":"King of fruits","image":"images\\\\Mango.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":6,"name":"Grapes","nameKn":"ದ್ರಾಕ್ಷಿ","category":"globalFruits","description":"Seedless green","image":"images\\\\Grapes.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":7,"name":"Pineapple","nameKn":"ಅನಾನಸ್","category":"dailyFruits","description":"Sweet & sour","image":"images\\\\Pineapple.jpg","weights":[{"label":"1 pc","price":80}]},
  {"id":8,"name":"Papaya","nameKn":"ಪಪ್ಪಾಯಿ","category":"indianFruits","description":"Digestive aid","image":"images\\\\Papaya.jpg","weights":[{"label":"1 kg","price":50}]},
  {"id":9,"name":"Watermelon","nameKn":"ಕಲ್ಲಂಗಡಿ","category":"dailyFruits","description":"Hydrating","image":"images\\\\Watermelon.jpg","weights":[{"label":"1 kg","price":35}]},
  {"id":10,"name":"Muskmelon","nameKn":"ಕರಬೂಜ","category":"dailyFruits","description":"Aromatic","image":"images\\\\Muskmelon.jpg","weights":[{"label":"1 kg","price":60}]},
  {"id":11,"name":"Guava","nameKn":"ಪೇರಲ","category":"indianFruits","description":"Rich in vitamin C","image":"images\\\\Guava.jpg","weights":[{"label":"1 kg","price":70}]},
  {"id":12,"name":"Pomegranate","nameKn":"ದಾಳಿಂಬೆ","category":"globalFruits","description":"Antioxidant rich","image":"images\\\\Pomegranate.jpg","weights":[{"label":"1 kg","price":160}]},
  {"id":13,"name":"Kiwi","nameKn":"ಕಿವಿ","category":"globalFruits","description":"Vitamin C powerhouse","image":"images\\\\Kiwi.jpg","weights":[{"label":"1 kg","price":400}]},
  {"id":14,"name":"Fig","nameKn":"ಅಂಜೂರ","category":"globalFruits","description":"Sweet & soft","image":"images\\\\Fig.jpg","weights":[{"label":"1 kg","price":300}]},
  {"id":15,"name":"Pear","nameKn":"ಪೇರ್","category":"globalFruits","description":"Juicy","image":"images\\\\Pear.jpg","weights":[{"label":"1 kg","price":200}]},
  {"id":17,"name":"Lychee","nameKn":"ಲೀಚಿ","category":"globalFruits","description":"Fragrant","image":"images\\\\Lychee.jpg","weights":[{"label":"1 kg","price":200}]},
  {"id":18,"name":"Jackfruit","nameKn":"ಹಲಸು","category":"indianFruits","description":"Largest tree fruit","image":"images\\\\Jackfruit.jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":19,"name":"Amla","nameKn":"ನೆಲ್ಲಿ","category":"indianFruits","description":"High vitamin C","image":"images\\\\Amla.jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":20,"name":"Passion Fruit","nameKn":"ಪ್ಯಾಷನ್ ಹಣ್ಣು","category":"globalFruits","description":"Tangy seeds","image":"images\\\\Passion Fruit.jpg","weights":[{"label":"1 kg","price":180}]},
  {"id":21,"name":"Cherry","nameKn":"ಚೆರ್ರಿ","category":"exotic","description":"Sweet red stone fruit","image":"images\\\\Cherry.jpg","weights":[{"label":"1 kg","price":250}]},
  {"id":23,"name":"Cranberry","nameKn":"ಕ್ರಾನ್ಬೆರ್ರಿ","category":"exotic","description":"Very tart red berry","image":"images\\\\Cranberry.jpg","weights":[{"label":"1 kg","price":400}]},
  {"id":24,"name":"Gooseberry (Indian Amla)","nameKn":"ಆಮಲಕ","category":"indianFruit","description":"Sour, rich in Vitamin C","image":"images\\\\Amla.jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":25,"name":"Gooseberry (Cape)","nameKn":"ಕೇಪ್ ಗೂಸ್ಬೆರ್ರಿ","category":"exotic","description":"Golden berry in husk","image":"images\\\\Cape Gooseberry.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":27,"name":"Currant (Black)","nameKn":"ಕಪ್ಪು ಕರ್ರಂಟ್","category":"exotic","description":"Intense flavor","image":"images\\\\Currant (Black).jpg","weights":[{"label":"1 kg","price":380}]},
  {"id":28,"name":"Currant (Red)","nameKn":"ಕೆಂಪು ಕರ್ರಂಟ್","category":"exotic","description":"Tart clusters","image":"images\\\\Currant (Red).jpg","weights":[{"label":"1 kg","price":360}]},
  {"id":29,"name":"Currant (White)","nameKn":"ಬಿಳಿ ಕರ್ರಂಟ್","category":"exotic","description":"Sweet mild","image":"images\\\\Currant (White).jpg","weights":[{"label":"1 kg","price":370}]},
  {"id":32,"name":"Açaí Berry","nameKn":"ಅಕಾಯ್ ಬೆರ್ರಿ","category":"exotic","description":"Purple antioxidant","image":"images\\\\Açaí Berry.jpg","weights":[{"label":"1 kg","price":600}]},
  {"id":33,"name":"Maqui Berry","nameKn":"ಮಾಕ್ವಿ ಬೆರ್ರಿ","category":"exotic","description":"Deep purple","image":"images\\\\Maqui.jpg","weights":[{"label":"1 kg","price":550}]},
  {"id":39,"name":"Raspberry","nameKn":"ವೈನ್ಬೆರ್ರಿ","category":"exotic","description":"Red with fuzzy hairs","image":"images\\\\Raspberry.jpg","weights":[{"label":"1 kg","price":280}]},
  {"id":44,"name":"Youngberry","nameKn":"ಯಂಗ್ಬೆರ್ರಿ","category":"exotic","description":"Deep purple","image":"images\\\\Youngberry.jpg","weights":[{"label":"1 kg","price":300}]},
  {"id":48,"name":"Camu Camu","nameKn":"ಕಾಮು ಕಾಮು","category":"exotic","description":"Extremely high Vitamin C","image":"images\\\\Camu Camu.jpg","weights":[{"label":"1 kg","price":600}]},
  {"id":54,"name":"Cempedak","nameKn":"ಸೆಂಪೆಡಾಕ್","category":"exotic","description":"Fragrant strong","image":"images\\\\Cempedak.jpg","weights":[{"label":"1 kg","price":160}]},
  {"id":59,"name":"Governor\'s Plum (Ramon)","nameKn":"ಗವರ್ನರ್ ಪ್ಲಮ್","category":"exotic","description":"Sweet-sour","image":"images\\\\Plum.jpg","weights":[{"label":"1 kg","price":110}]},
  {"id":60,"name":"Indian Plum (Jujube)","nameKn":"ಬೋರ್ ಹಣ್ಣು","category":"indianFruit","description":"Apple-like crisp","image":"images\\\\Indian Plum (Jujube).jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":63,"name":"Otaheite Gooseberry","nameKn":"ಒಟಹೈಟ್ ಗೂಸ್ಬೆರ್ರಿ","category":"exotic","description":"Sour clusters","image":"images\\\\Otaheite Gooseberry.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":64,"name":"Barbados Cherry (Acerola)","nameKn":"ಬಾರ್ಬಡೋಸ್ ಚೆರ್ರಿ","category":"exotic","description":"High Vitamin C","image":"images\\\\Barbados Cherry (Acerola).jpg","weights":[{"label":"1 kg","price":220}]},
  {"id":65,"name":"Hog Plum (Spondias)","nameKn":"ಅಂಬಾಡೆ ಹಣ್ಣು","category":"indianFruit","description":"Tart, used in pickles","image":"images\\\\Hog Plum (Spondias).jpg","weights":[{"label":"1 kg","price":70}]},
  {"id":66,"name":"Bilimbi (Cucumber Tree)","nameKn":"ಬಿಲಿಂಬಿ","category":"indianFruit","description":"Very sour","image":"images\\\\Bilimbi (Cucumber Tree).jpg","weights":[{"label":"1 kg","price":60}]},
  {"id":69,"name":"Abiu","nameKn":"ಅಬಿಯು","category":"exotic","description":"Yellow caramel-like","image":"images\\\\Abiu.jpg","weights":[{"label":"1 kg","price":280}]},
  {"id":73,"name":"Green Sapote","nameKn":"ಗ್ರೀನ್ ಸಪೋಟೆ","category":"exotic","description":"Sweet creamy","image":"images\\\\Green Sapote.jpg","weights":[{"label":"1 kg","price":340}]},
  {"id":74,"name":"Yellow Sapote","nameKn":"ಹಳದಿ ಸಪೋಟೆ","category":"exotic","description":"Smaller, sweeter","image":"images\\\\Yellow Sapote.jpg","weights":[{"label":"1 kg","price":330}]},
  {"id":77,"name":"Pepino Melon","nameKn":"ಪೆಪಿನೋ ಮೆಲನ್","category":"exotic","description":"Striped, melon-cucumber","image":"images\\\\Pepino.jpg","weights":[{"label":"1 kg","price":200}]},
  {"id":82,"name":"Bacupari","nameKn":"ಬಕುಪರಿ","category":"exotic","description":"Yellow, sweet-sour","image":"images\\\\Bacupari.jpg","weights":[{"label":"1 kg","price":420}]},
  {"id":107,"name":"Davidson\'s Plum","nameKn":"ಡೇವಿಡ್ಸನ್ ಪ್ಲಮ್","category":"exotic","description":"Very sour","image":"images\\\\Davidsons Plum.jpg","weights":[{"label":"1 kg","price":290}]},
  {"id":109,"name":"Desert Lime","nameKn":"ಡೆಸರ್ಟ್ ಲೈಮ್","category":"exotic","description":"Small intense citrus","image":"images\\\\Desert Lime.jpg","weights":[{"label":"1 kg","price":350}]},
  {"id":121,"name":"Potato","nameKn":"ಆಲೂಗಡ್ಡೆ","category":"indianMain","description":"Staple vegetable","image":"images\\\\Potato.jpg","weights":[{"label":"1 kg","price":25}]},
  {"id":122,"name":"Onion","nameKn":"ಈರುಳ್ಳಿ","category":"indianMain","description":"Pungent","image":"images\\\\Onion.jpg","weights":[{"label":"1 kg","price":30}]},
  {"id":123,"name":"Tomato","nameKn":"ಟೊಮಾಟೊ","category":"indianMain","description":"Juicy","image":"images\\\\Tomato.jpg","weights":[{"label":"1 kg","price":35}]},
  {"id":124,"name":"Carrot","nameKn":"ಕ್ಯಾರೆಟ್","category":"indianMain","description":"Sweet root","image":"images\\\\Carrot.jpg","weights":[{"label":"1 kg","price":50}]},
  {"id":125,"name":"Beetroot","nameKn":"ಬೀಟ್ರೂಟ್","category":"indianMain","description":"Earthy","image":"images\\\\Beetroot.jpg","weights":[{"label":"1 kg","price":45}]},
  {"id":126,"name":"Radish","nameKn":"ಮೂಲಂಗಿ","category":"indianMain","description":"Crunchy","image":"images\\\\Radish.jpg","weights":[{"label":"1 kg","price":30}]},
  {"id":127,"name":"Cabbage","nameKn":"ಎಲೆಕೋಸು","category":"indianMain","description":"Leafy head","image":"images\\\\Cabbage.jpg","weights":[{"label":"1 kg","price":30}]},
  {"id":128,"name":"Cauliflower","nameKn":"ಹೂಕೋಸು","category":"indianMain","description":"Curd like","image":"images\\\\Cauliflower.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":129,"name":"Broccoli","nameKn":"ಬ್ರೊಕೊಲಿ","category":"global","description":"Green tree","image":"images\\\\Broccoli.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":130,"name":"Capsicum","nameKn":"ದೊಣ್ಣೆ ಮೆಣಸು","category":"global","description":"Bell pepper","image":"images\\\\Capsicum.jpg","weights":[{"label":"1 kg","price":70}]},
  {"id":131,"name":"Green Chilli","nameKn":"ಹಸಿರು ಮೆಣಸಿನಕಾಯಿ","category":"dailyHerbs","description":"Spicy","image":"images\\\\Green Chilli.jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":132,"name":"Brinjal","nameKn":"ಬದನೇಕಾಯಿ","category":"indianMain","description":"Purple aubergine","image":"images\\\\Brinjal.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":133,"name":"Lady Finger (Okra)","nameKn":"ಬೆಂಡೆಕಾಯಿ","category":"indianMain","description":"Slender pod","image":"images\\\\Lady Finger (Okra).jpg","weights":[{"label":"1 kg","price":50}]},
  {"id":134,"name":"Bitter Gourd","nameKn":"ಹಾಗಲಕಾಯಿ","category":"indianMain","description":"Bitter","image":"images\\\\Bitter Gourd.jpg","weights":[{"label":"1 kg","price":60}]},
  {"id":135,"name":"Bottle Gourd","nameKn":"ಸೋರೆಕಾಯಿ","category":"indianMain","description":"Mild","image":"images\\\\Bottle Gourd.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":138,"name":"Pumpkin","nameKn":"ಸೀಗುಂಬಳ","category":"indianMain","description":"Orange flesh","image":"images\\\\Pumpkin.jpg","weights":[{"label":"1 kg","price":35}]},
  {"id":139,"name":"Ash Gourd","nameKn":"ಬೂದು ಕುಂಬಳ","category":"indianMain","description":"White flesh","image":"images\\\\Ash Gourd.jpg","weights":[{"label":"1 kg","price":30}]},
  {"id":140,"name":"Sweet Potato","nameKn":"ಸಿಹಿ ಗೆಣಸು","category":"indianMain","description":"Sweet tuber","image":"images\\\\Sweet Potato.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":143,"name":"Spinach","nameKn":"ಪಾಲಕ್","category":"indianLeafy","description":"Iron rich","image":"images\\\\Spinach.jpg","weights":[{"label":"1 kg","price":20}]},
  {"id":144,"name":"Fenugreek Leaves","nameKn":"ಮೆಂತೆ ಸೊಪ್ಪು","category":"indianLeafy","description":"Slightly bitter","image":"images\\\\Fenugreek Leaves.jpg","weights":[{"label":"1 kg","price":20}]},
  {"id":145,"name":"Coriander Leaves","nameKn":"ಕೊತ್ತಂಬರಿ ಸೊಪ್ಪು","category":"indianLeafy","description":"Herb","image":"images\\\\Coriander Leaves.jpg","weights":[{"label":"1 kg","price":10}]},
  {"id":146,"name":"Curry Leaves","nameKn":"ಕರಿಬೇವು","category":"indianLeafy","description":"Aromatic","image":"images\\\\Curry Leaves.jpg","weights":[{"label":"1 kg","price":10}]},
  {"id":147,"name":"Lettuce","nameKn":"ಲೆಟಿಸ್","category":"global","description":"Salad leaf","image":"images\\\\Lettuce.jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":148,"name":"Kale","nameKn":"ಕೇಲ್","category":"global","description":"Superfood","image":"images\\\\Kale.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":149,"name":"Celery","nameKn":"ಸೆಲರಿ","category":"global","description":"Crunchy stalk","image":"images\\\\Celery.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":150,"name":"Zucchini","nameKn":"ಝುಚಿನಿ","category":"global","description":"Summer squash","image":"images\\\\Zucchini.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":151,"name":"Mushroom","nameKn":"ಅಣಬೆ","category":"global","description":"Fungi","image":"images\\\\Mushroom.jpg","weights":[{"label":"1 kg","price":200}]},
  {"id":152,"name":"Corn","nameKn":"ಮೆಕ್ಕೆಜೋಳ","category":"global","description":"Sweet kernel","image":"images\\\\Corn.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":153,"name":"Baby Corn","nameKn":"ಬೇಬಿ ಕಾರ್ನ್","category":"global","description":"Young corn","image":"images\\\\Baby Corn.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":154,"name":"Spring Onion","nameKn":"ಹಸಿರು ಈರುಳ್ಳಿ","category":"dailyHerbs","description":"Mild onion","image":"images\\\\Spring Onion.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":155,"name":"Garlic","nameKn":"ಬೆಳ್ಳುಳ್ಳಿ","category":"dailyHerbs","description":"Pungent","image":"images\\\\Garlic.jpg","weights":[{"label":"1 kg","price":200}]},
  {"id":156,"name":"Ginger","nameKn":"ಶುಂಠಿ","category":"dailyHerbs","description":"Spicy root","image":"images\\\\Ginger.jpg","weights":[{"label":"1 kg","price":160}]},
  {"id":157,"name":"Drumstick","nameKn":"ನುಗ್ಗೆಕಾಯಿ","category":"indianMain","description":"Long pod","image":"images\\\\Drumstick.jpg","weights":[{"label":"1 kg","price":90}]},
  {"id":158,"name":"Raw Banana","nameKn":"ಬಾಳೆಕಾಯಿ","category":"indianMain","description":"Green cooking banana","image":"images\\\\Raw Banana.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":159,"name":"Green Peas","nameKn":"ಹಸಿರು ಬಟಾಣಿ","category":"indianMain","description":"Sweet peas","image":"images\\\\Green Peas.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":160,"name":"Beans","nameKn":"ಬೀನ್ಸ್","category":"indianMain","description":"Green beans","image":"images\\\\Beans all.jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":163,"name":"Raw Mango","nameKn":"ಮಾವಿನಕಾಯಿ","category":"indianMain","description":"Sour green mango","image":"images\\\\Raw Mango.jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":164,"name":"Turnip","nameKn":"ಟರ್ನಿಪ್","category":"global","description":"White root","image":"images\\\\Turnip.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":166,"name":"Cherry Tomato","nameKn":"ಚೆರಿ ಟೊಮಾಟೊ","category":"global","description":"Small sweet","image":"images\\\\Cherry Tomato.jpg","weights":[{"label":"1 kg","price":90}]},
  {"id":167,"name":"Leeks","nameKn":"ಲೀಕ್","category":"global","description":"Mild onion flavor","image":"images\\\\Leeks.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":168,"name":"Parsley","nameKn":"ಪಾರ್ಸ್ಲಿ","category":"global","description":"Herb","image":"images\\\\Parsley.jpg","weights":[{"label":"1 kg","price":100}]},
  {"id":169,"name":"Dill Leaves","nameKn":"ಸಬ್ಬಸಿಗೆ ಸೊಪ್ಪು","category":"indianLeafy","description":"Aromatic","image":"images\\\\Dill Leaves.jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":170,"name":"Rocket Leaves","nameKn":"ರಾಕೆಟ್ ಸೊಪ್ಪು","category":"global","description":"Peppery","image":"images\\\\Rocket Leaves.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":171,"name":"Artichoke","nameKn":"ಆರ್ಟಿಚೋಕ್","category":"global","description":"Edible flower","image":"images\\\\Artichoke.jpg","weights":[{"label":"1 kg","price":300}]},
  {"id":172,"name":"Brussels Sprouts","nameKn":"ಬ್ರಸೆಲ್ಸ್ ಮೊಳಕೆ","category":"global","description":"Tiny cabbage","image":"images\\\\Brussels Sprouts.jpg","weights":[{"label":"1 kg","price":250}]},
  {"id":173,"name":"Yellow Capsicum","nameKn":"ಹಳದಿ ದೊಣ್ಣೆ ಮೆಣಸು","category":"global","description":"Sweet","image":"images\\\\Yellow Capsicum.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":174,"name":"Red Capsicum","nameKn":"ಕೆಂಪು ದೊಣ್ಣೆ ಮೆಣಸು","category":"global","description":"Sweet","image":"images\\\\Red Capsicum.jpg","weights":[{"label":"1 kg","price":130}]},
  {"id":175,"name":"Purple Cabbage","nameKn":"ನೇರಳೆ ಎಲೆಕೋಸು","category":"global","description":"Colorful","image":"images\\\\Purple Cabbage.jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":176,"name":"White Onion","nameKn":"ಬಿಳಿ ಈರುಳ್ಳಿ","category":"indianMain","description":"Mild","image":"images\\\\White Onion.jpg","weights":[{"label":"1 kg","price":30}]},
  {"id":177,"name":"Shallots","nameKn":"ಚಿಕ್ಕ ಈರುಳ್ಳಿ","category":"indianMain","description":"Small onions","image":"images\\\\Shallots.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":179,"name":"Banana Stem","nameKn":"ಬಾಳೆಯ ಕಾಂಡ","category":"indianMain","description":"Fibrous","image":"images\\\\Banana Stem.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":180,"name":"Banana Flower","nameKn":"ಬಾಳೆಯ ಹೂವು","category":"indianMain","description":"Tender","image":"images\\\\Banana Flower.jpg","weights":[{"label":"1 kg","price":50}]},
  {"id":181,"name":"Green Papaya","nameKn":"ಹಸಿರು ಪಪ್ಪಾಯಿ","category":"indianMain","description":"Unripe","image":"images\\\\Green Papaya.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":182,"name":"Red Radish","nameKn":"ಕೆಂಪು ಮೂಲಂಗಿ","category":"indianMain","description":"Spicy","image":"images\\\\Red Radish.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":183,"name":"Broccoli Rabe","nameKn":"ಬ್ರೊಕೊಲಿ ರಾಬೆ","category":"global","description":"Leafy","image":"images\\\\Broccoli Rabe.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":184,"name":"Bamboo Shoots","nameKn":"ಬಿದಿರು ಚಿಗುರು","category":"global","description":"Crunchy","image":"images\\\\Bamboo Shoots.jpg","weights":[{"label":"1 kg","price":180}]},
  {"id":185,"name":"Edamame","nameKn":"ಊಡಮಮಹ","category":"global","description":"Soybeans","image":"images\\\\Edamame.jpg","weights":[{"label":"1 kg","price":220}]},
  {"id":186,"name":"Water Chestnut","nameKn":"ಸಿಂಘಾಟಾ","category":"global","description":"Aquatic nut","image":"images\\\\Water Chestnut.jpg","weights":[{"label":"1 kg","price":90}]},
  {"id":187,"name":"Green Garlic","nameKn":"ಹಸಿರು ಬೆಳ್ಳುಳ್ಳಿ","category":"dailyHerbs","description":"Mild","image":"images\\\\Green Garlic.jpg","weights":[{"label":"1 kg","price":100}]},
  {"id":188,"name":"Kohlrabi","nameKn":"ಕೊಹ್ಲ್ರಾಬಿ","category":"global","description":"Bulbous","image":"images\\\\Kohlrabi.jpg","weights":[{"label":"1 kg","price":60}]},
  {"id":189,"name":"Sweet Corn","nameKn":"ಸಿಹಿ ಮೆಕ್ಕೆಜೋಳ","category":"global","description":"Juicy kernels","image":"images\\\\Sweet Corn.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":190,"name":"Frozen Vegetables Mix","nameKn":"ಹೆಪ್ಪುಗಟ್ಟಿದ ತರಕಾರಿ ಮಿಕ್ಸ್","category":"global","description":"Mixed","image":"images\\\\Frozen Vegetables Mix.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":191,"name":"Winged Bean (Asparagus Pea)","nameKn":"ಶತಾವರಿ ಬೀನ್ಸ್","category":"indianMain","description":"Four-angled pod","image":"images\\\\Winged Bean (Asparagus Pea.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":192,"name":"Jackfruit Seeds","nameKn":"ಹಲಸಿನ ಬೀಜ","category":"indianMain","description":"Nutty & starchy","image":"images\\\\Jackfruit.jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":193,"name":"Lotus Stem (Kamal Kakdi)","nameKn":"ಕಮಲ ಕಾಕಡಿ","category":"indianMain","description":"Crunchy with holes","image":"images\\\\Lotus Stem (Kamal Kakdi).jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":196,"name":"Chinese Yam (Nagaimo)","nameKn":"ನಾಗೈಮೋ","category":"global","description":"Slimy when grated","image":"images\\\\Chinese Yam (Nagaimo).jpg","weights":[{"label":"1 kg","price":200}]},
  {"id":198,"name":"Arrowroot","nameKn":"ಅರಾರೂಟ್","category":"indianMain","description":"Starch source","image":"images\\\\Arrowroot.jpg","weights":[{"label":"1 kg","price":90}]},
  {"id":199,"name":"Chinese Potato (Koorka)","nameKn":"ಕೂರ್ಕಾ","category":"indianMain","description":"Wrinkled tuber","image":"images\\\\Chinese Potato (Koorka).jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":200,"name":"African Yam Bean","nameKn":"ಆಫ್ರಿಕನ್ ಯಾಮ್ ಬೀನ್","category":"global","description":"Tuber & seed","image":"images\\\\African Yam Bean.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":202,"name":"Bitter Melon (Small variety)","nameKn":"ಚಿಕ್ಕ ಹಾಗಲಕಾಯಿ","category":"indianMain","description":"More bitter","image":"images\\\\Bitter Melon (Small variety).jpg","weights":[{"label":"1 kg","price":70}]},
  {"id":203,"name":"Cucumber (Indian Kheera)","nameKn":"ಕೀರಾ","category":"indianMain","description":"Salad cucumber","image":"images\\\\Cucumber (Indian Kheera).jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":204,"name":"Cucumber (English/Hothouse)","nameKn":"ಇಂಗ್ಲೀಷ್ ಸೌತೆ","category":"global","description":"Seedless long","image":"images\\\\Cucumber (English/Hothouse).jpg","weights":[{"label":"1 kg","price":60}]},
  {"id":206,"name":"Calabash (Long bottle gourd)","nameKn":"ಲಾವು","category":"indianMain","description":"Long variety","image":"images\\\\Calabash (Long bottle gourd).jpg","weights":[{"label":"1 kg","price":35}]},
  {"id":208,"name":"Pepper (Banana Pepper)","nameKn":"ಬಾಳೆ ಮೆಣಸು","category":"global","description":"Mild yellow chili","image":"images\\\\Pepper (Banana Pepper).jpg","weights":[{"label":"1 kg","price":90}]},
  {"id":210,"name":"Pepper (Jalapeño)","nameKn":"ಜಲಪೆನೊ","category":"global","description":"Spicy medium","image":"images\\\\Pepper (Jalapeño).jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":211,"name":"Pepper (Habanero)","nameKn":"ಹಬನೆರೊ","category":"global","description":"Very hot","image":"images\\\\Pepper (Habanero).jpg","weights":[{"label":"1 kg","price":180}]},
  {"id":213,"name":"Pepper (Shishito)","nameKn":"ಶಿಶಿಟೊ","category":"global","description":"Mild Japanese","image":"images\\\\Shishito.jpg","weights":[{"label":"1 kg","price":160}]},
  {"id":219,"name":"Tomato (Green)","nameKn":"ಹಸಿರು ಟೊಮಾಟೊ","category":"indianMain","description":"Unripe tangy","image":"images\\\\Tomato (Green).jpg","weights":[{"label":"1 kg","price":40}]},
  {"id":222,"name":"Kundru (Ivy Gourd)","nameKn":"ಕುಂದ್ರು","category":"indianMain","description":"Same as tindora","image":"images\\\\Kundru (Ivy Gourd).jpg","weights":[{"label":"1 kg","price":60}]},
  {"id":232,"name":"Curry Leaf (Dried)","nameKn":"ಒಣ ಕರಿಬೇವು","category":"dailyHerbs","description":"Aromatic dried","image":"images\\\\Curry Leaf (Dried).jpg","weights":[{"label":"1 kg","price":50}]},
  {"id":233,"name":"Bay Leaf (Tej Patta)","nameKn":"ತೇಜ ಪತ್ತಾ","category":"dailyHerbs","description":"Aromatic leaf","image":"images\\\\Bay Leaf (Tej Patta).jpg","weights":[{"label":"1 kg","price":60}]},
  {"id":234,"name":"Holy Basil (Krishna Tulsi)","nameKn":"ಕೃಷ್ಣ ತುಳಸಿ","category":"dailyHerbs","description":"Purple leaves","image":"images\\\\Holy Basil (Krishna Tulsi).jpg","weights":[{"label":"1 kg","price":20}]},
  {"id":235,"name":"Lemon Balm","nameKn":"ಲೆಮನ್ ಬಾಮ್","category":"global","description":"Citrus mint","image":"images\\\\Lemon Balm.jpg","weights":[{"label":"1 kg","price":100}]},
  {"id":236,"name":"Stevia Leaves","nameKn":"ಸ್ಟೀವಿಯಾ","category":"dailyHerbs","description":"Natural sweetener","image":"images\\\\Stevia Leaves.jpg","weights":[{"label":"1 kg","price":80}]},
  {"id":239,"name":"Marjoram","nameKn":"ಮಾರ್ಜೋರಾಮ್","category":"global","description":"Sweet oregano","image":"images\\\\Marjoram.jpg","weights":[{"label":"1 kg","price":130}]},
  {"id":240,"name":"Lovage","nameKn":"ಲವೇಜ್","category":"global","description":"Celery-like","image":"images\\\\Lovage.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":241,"name":"Sorrel (Red Veined)","nameKn":"ಕೆಂಪು ಗೋಣಿ ಸೊಪ್ಪು","category":"global","description":"Decorative tangy leaf","image":"images\\\\Sorrel (Red Veined)Sweet Corn.jpg","weights":[{"label":"1 kg","price":90}]},
  {"id":244,"name":"Crosnes (Chinese Artichoke)","nameKn":"ಕ್ರೋಸ್ನ್ಸ್","category":"global","description":"Caterpillar-like tuber","image":"images\\\\Crosnes (Chinese Artichoke).jpg","weights":[{"label":"1 kg","price":450}]},
  {"id":246,"name":"Hamburg Parsley","nameKn":"ಹ್ಯಾಂಬರ್ಗ್ ಪಾರ್ಸ್ಲಿ","category":"global","description":"Parsley root","image":"images\\\\Hamburg Parsley.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":247,"name":"Arracacha","nameKn":"ಅರ್ರಾಕಾಚಾ","category":"global","description":"Andean root","image":"images\\\\Arracacha.jpg","weights":[{"label":"1 kg","price":220}]},
    {"id":249,"name":"Ahipa (Pacay)","nameKn":"ಅಹಿಪಾ","category":"global","description":"Sweet turnip-like","image":"images\\\\Ahipa (Pacay).jpg","weights":[{"label":"1 kg","price":260}]},
  {"id":250,"name":"Burdock Root (Gobo)","nameKn":"ಬರ್ಡಾಕ್","category":"global","description":"Earthy, used in Japanese cooking","image":"images\\\\Burdock Root (Gobo).jpg","weights":[{"label":"1 kg","price":180}]},
  {"id":251,"name":"Indian Sorrel (Gongura)","nameKn":"ಗೋಂಗುರಾ","category":"indianLeafy","description":"Tangy leaves","image":"images\\\\Indian Sorrel (Gongura).jpg","weights":[{"label":"1 kg","price":30}]},
  {"id":254,"name":"Chow Chow (Chayote)","nameKn":"ಚೌ ಚೌ","category":"indianMain","description":"Same as chayote","image":"images\\\\Chow Chow (Chayote).jpg","weights":[{"label":"1 kg","price":60}]},
  {"id":255,"name":"Tindora (Ivy Gourd)","nameKn":"ತೊಂಡೆಕಾಯಿ","category":"indianMain","description":"Already listed, but common","image":"images\\\\Tindora (Ivy Gourd).jpg","weights":[{"label":"1 kg","price":60}]},
  {"id":257,"name":"Mango Ginger","nameKn":"ಮಾವಿನ ಶುಂಠಿ","category":"dailyHerbs","description":"Tastes like raw mango","image":"images\\\\Mango Ginger.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":258,"name":"Lesser Galangal","nameKn":"ಸಣ್ಣ ಕಚೋರ","category":"dailyHerbs","description":"Aromatic root","image":"images\\\\Lesser Galangal.jpg","weights":[{"label":"1 kg","price":180}]},
  {"id":259,"name":"Turmeric Root (Fresh)","nameKn":"ಹಸಿ ಅರಿಶಿನ","category":"dailyHerbs","description":"Vivid orange root","image":"images\\\\Turmeric Root (Fresh.jpg","weights":[{"label":"1 kg","price":80}]},
    {"id":263,"name":"Arugula (Rocket)","nameKn":"ಅರುಗುಲಾ","category":"global","description":"Peppery salad leaf","image":"images\\\\Arugula (Rocket).jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":265,"name":"Tatsoi","nameKn":"ಟಾಟ್ಸೋಯಿ","category":"global","description":"Spoon-shaped mustard","image":"images\\\\Tatsoi.jpg","weights":[{"label":"1 kg","price":170}]},
  {"id":266,"name":"Komatsuna","nameKn":"ಕೊಮಾಟ್ಸುನಾ","category":"global","description":"Japanese spinach","image":"images\\\\Komatsuna.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":267,"name":"Samphire (Sea Asparagus)","nameKn":"ಸ್ಯಾಂಪೈರ್","category":"global","description":"Salty coastal plant","image":"images\\\\Samphire (Sea Asparagus).jpg","weights":[{"label":"1 kg","price":350}]},
  {"id":269,"name":"Cassava (Yuca)","nameKn":"ಮರಗೆಣಸು","category":"indianMain","description":"Starchy root","image":"images\\\\Cassava (Yuca).jpg","weights":[{"label":"1 kg","price":50}]},
  {"id":270,"name":"Malanga","nameKn":"ಮಲಂಗಾ","category":"global","description":"Tropical root","image":"images\\\\Malanga.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":277,"name":"Kai-lan (Chinese Broccoli)","nameKn":"ಕೈ-ಲಾನ್","category":"global","description":"Thick stems","image":"images\\\\Kai-lan (Chinese Broccoli).jpg","weights":[{"label":"1 kg","price":110}]},
  {"id":278,"name":"Gai Choy (Mustard Cabbage)","nameKn":"ಗೈ ಚಾಯ್","category":"global","description":"Spicy Chinese green","image":"images\\\\Gai Choy (Mustard Cabbage).jpg","weights":[{"label":"1 kg","price":90}]},
  {"id":279,"name":"Napa Cabbage","nameKn":"ನಾಪಾ ಕ್ಯಾಬೇಜ್","category":"global","description":"Chinese cabbage","image":"images\\\\Napa Cabbage.jpg","weights":[{"label":"1 kg","price":70}]},
  {"id":280,"name":"Choy Sum","nameKn":"ಚಾಯ್ ಸಮ್","category":"global","description":"Flowering white stem","image":"images\\\\Choy Sum.jpg","weights":[{"label":"1 kg","price":100}]},
  {"id":281,"name":"Apricot","nameKn":"ಏಪ್ರಿಕಾಟ್","category":"globalFruits","description":"Soft, sweet stone fruit","image":"images\\\\Apricot.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":282,"name":"Banana small","nameKn":"ಸಣ್ಣ ಬಾಳೆಹಣ್ಣು","category":"indianFruits","description":"Miniature sweet bananas","image":"images\\\\Banana small.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":283,"name":"Black Grapes","nameKn":"ಕಪ್ಪು ದ್ರಾಕ್ಷಿ","category":"globalFruits","description":"Sweet dark grapes","image":"images\\\\Black Grapes.jpg","weights":[{"label":"1 kg","price":180}]},
  {"id":284,"name":"Dragon Fruit","nameKn":"ಡ್ರಾಗನ್ ಹಣ್ಣು","category":"exotic","description":"Vibrant pink flesh with seeds","image":"images\\\\Dragon Fruit.jpg","weights":[{"label":"1 kg","price":160}]},
  {"id":285,"name":"Fresh Dates","nameKn":"ತಾಜಾ ಖರ್ಜೂರ","category":"exotic","description":"Sweet, sticky fruit from date palm","image":"images\\\\Fresh Dates.jpg","weights":[{"label":"1 kg","price":200}]},
  {"id":286,"name":"Sweet Tamarind","nameKn":"ಸಿಹಿ ಹುಣಸೆ","category":"indianFruits","description":"Sweet-sour pulp, used in chutneys","image":"images\\\\Sweet Tamarind.jpg","weights":[{"label":"1 kg","price":110}]},
  {"id":287,"name":"Frozen Berries Mix","nameKn":"ಹೆಪ್ಪುಗಟ್ಟಿದ ಬೆರ್ರಿಗಳ ಮಿಶ್ರಣ","category":"globalFruits","description":"Mixed frozen berries","image":"images\\\\Frozen Berries Mix.jpg","weights":[{"label":"1 kg","price":100}]},
  {"id":288,"name":"Green Grapes","nameKn":"ಹಸಿರು ದ್ರಾಕ್ಷಿ","category":"globalFruits","description":"Crisp, seedless green grapes","image":"images\\\\Green Grapes.jpg","weights":[{"label":"1 kg","price":90}]},
  {"id":289,"name":"Guava red","nameKn":"ಕೆಂಪು ಪೇರಲ","category":"indianFruits","description":"Sweet red‑fleshed guava","image":"images\\\\Guava red.jpg","weights":[{"label":"1 kg","price":110}]},
  {"id":290,"name":"Strawberry","nameKn":"ಸ್ಟ್ರಾಬೆರಿ","category":"globalFruits","description":"Sweet, juicy red berries","image":"images\\\\Strawberry.jpg","weights":[{"label":"1 kg","price":110}]},
  {"id":291,"name":"Jamun","nameKn":"ಜಾಮೂನ್","category":"indianFruits","description":"Purple astringent fruit","image":"images\\\\Jamun.jpg","weights":[{"label":"1 kg","price":140}]},
  {"id":292,"name":"Kokum","nameKn":"ಕೊಕುಮ್","category":"indianFruits","description":"Sour fruit used in drinks","image":"images\\\\Kokum.jpg","weights":[{"label":"1 kg","price":100}]},
  {"id":293,"name":"Gooseberry","nameKn":"ಗೂಸ್ಬೆರ್ರಿ","category":"globalFruits","description":"Tart green berries","image":"images\\\\Gooseberry.jpg","weights":[{"label":"1 kg","price":120}]},
  {"id":294,"name":"Tender Coconut","nameKn":"ಎಳೆ ನೀರಿನ ತೆಂಗು","category":"dailyFruits","description":"Fresh coconut water and soft flesh","image":"images\\\\Tender Coconut.jpg","weights":[{"label":"1 pc","price":50}]},
  {"id":295,"name":"Longan","nameKn":"ಲಾಂಗನ್","category":"exotic","description":"Sweet translucent flesh","image":"images\\\\Longan.jpg","weights":[{"label":"1 kg","price":180}]},
  {"id":296,"name":"Mandarin Orange","nameKn":"ಮ್ಯಾಂಡರಿನ್ ಕಿತ್ತಳೆ","category":"globalFruits","description":"Easy‑peel sweet orange","image":"images\\\\Mandarin Orange.jpg","weights":[{"label":"1 kg","price":200}]},
  {"id":297,"name":"Mulberry","nameKn":"ಹಿಪ್ಪು ನೇರಳೆ","category":"globalFruits","description":"Sweet dark purple berries","image":"images\\\\Mulberry.jpg","weights":[{"label":"1 kg","price":250}]},
  {"id":298,"name":"Peach","nameKn":"ಪೀಚ್","category":"globalFruits","description":"Velvety soft stone fruit","image":"images\\\\Peach.jpg","weights":[{"label":"1 kg","price":350}]},
  {"id":299,"name":"Rambutan","nameKn":"ರಾಂಬುಟಾನ್","category":"exotic","description":"Hairy red fruit, sweet and juicy","image":"images\\\\Rambutan.jpg","weights":[{"label":"1 kg","price":340}]},
  {"id":300,"name":"Sapota (Chikoo)","nameKn":"ಸಪೋಟ","category":"indianFruits","description":"Sweet, grainy brown fruit","image":"images\\\\Sapota (Chikoo).jpg","weights":[{"label":"1 kg","price":330}]},
  {"id":301,"name":"Star Fruit","nameKn":"ಕಮರಕ್ ಹಣ್ಣು","category":"exotic","description":"Star‑shaped, tangy and crisp","image":"images\\\\Star Fruit.jpg","weights":[{"label":"1 kg","price":600}]},
  {"id":302,"name":"Plum","nameKn":"ಆಲ್ಬುಕೋಸ್","category":"globalFruits","description":"Sweet juicy stone fruit","image":"images\\\\Plum.jpg","weights":[{"label":"1 kg","price":280}]},
  {"id":303,"name":"Nectarine","nameKn":"ನೆಕ್ಟರಿನ್","category":"globalFruits","description":"Smooth‑skinned peach‑like fruit","image":"images\\\\Nectarine.jpg","weights":[{"label":"1 kg","price":320}]},
  {"id":304,"name":"Custard Apple","nameKn":"ಸೀತಾಫಲ","category":"indianFruits","description":"Creamy sweet pulp with seeds","image":"images\\\\Custard Apple.jpg","weights":[{"label":"1 kg","price":150}]},
  {"id":305,"name":"Lime","nameKn":"ನಿಂಬೆ","category":"dailyFruits","description":"Tart green citrus","image":"images\\\\Lime.jpg","weights":[{"label":"1 per ","price":2}]}
]';
    $fallbackProducts = json_decode($fallbackJson, true);
    if (is_array($fallbackProducts)) {
        foreach ($fallbackProducts as $item) {
            $productsData[] = [
                'id' => count($productsData) + 1,
                'name' => $item['name'],
                'kannada' => $item['nameKn'] ?? $item['name'],
                'desc' => $item['description'] ?? 'Fresh & farm picked',
                'pricePerKg' => $item['weights'][0]['price'] ?? 0,
                'category' => $item['category'] ?? 'global',
                'imageUrl' => $item['image'] ?? ''
            ];
        }
    }
}
// ============================================================
// WEIGHT OPTIONS (same as static)
// ============================================================
$weightOptions = [
    ['grams' => 250, 'label' => '250g'],
    ['grams' => 500, 'label' => '500g'],
    ['grams' => 750, 'label' => '750g'],
    ['grams' => 1000, 'label' => '1kg'],
    ['grams' => 2000, 'label' => '2kg'],
    ['grams' => 3000, 'label' => '3kg'],
    ['grams' => 5000, 'label' => '5kg'],
    ['grams' => 10000, 'label' => '10kg'],
    ['grams' => 20000, 'label' => '20kg'],
    ['grams' => 50000, 'label' => '50kg']
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>KSR GURU – 250+ ತರಕಾರಿ & ಹಣ್ಣುಗಳು | Fresh Weights</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== SAME CSS AS STATIC VERSION ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        :root {
            --bg-body: #f5fbf9;
            --bg-surface: #ffffff;
            --text-primary: #1b3b3a;
            --text-secondary: #2d4f4c;
            --text-muted: #54706c;
            --border-light: #d0e6e1;
            --card-shadow: 0 12px 24px -12px rgba(20, 80, 70, 0.2);
            --hover-shadow: 0 28px 36px -14px #1b6b61;
            --accent-teal: #1b7f74;
            --accent-soft: #bef0e8;
            --header-gradient: linear-gradient(145deg, #10635b, #23877c);
            --footer-bg: #0e544b;
            --tagline-color: #dcfff8;
            --cart-bg: #ffffff;
            --empty-cart-icon: #b6d9d2;
            --weight-bg: #f0f9f7;
        }

        body.dark-mode {
            --bg-body: #10211f;
            --bg-surface: #1b2f2c;
            --text-primary: #e0f0ed;
            --text-secondary: #c0dad5;
            --text-muted: #9bbbb5;
            --border-light: #38635c;
            --card-shadow: 0 10px 18px -6px #040a09;
            --hover-shadow: 0 24px 32px -10px black;
            --accent-teal: #44c2b2;
            --accent-soft: #25554d;
            --header-gradient: linear-gradient(145deg, #0d4941, #166b60);
            --footer-bg: #0a3f38;
            --tagline-color: #bdeae2;
            --cart-bg: #1d332f;
            --empty-cart-icon: #42736b;
            --weight-bg: #2a4943;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            transition: background-color 0.25s, color 0.2s;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 18px;
            width: 100%;
        }

        header {
            background: var(--header-gradient);
            padding: 1rem 1.8rem;
            border-radius: 0 0 38px 38px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 12px 22px rgba(0, 45, 35, 0.25);
            margin-bottom: 1.8rem;
            color: white;
        }

        .logo-section {
            display: flex;
            flex-direction: column;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 2rem;
            font-weight: 700;
        }

        .logo i {
            font-size: 2.3rem;
            filter: drop-shadow(2px 4px 6px #02221e);
        }

        .tagline {
            font-size: 0.95rem;
            opacity: 0.95;
            color: var(--tagline-color);
        }

        .user-controls {
            display: flex;
            gap: 1.2rem;
            align-items: center;
        }

        .cart-link,
        .theme-toggle {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(6px);
            border-radius: 40px;
            padding: 10px 18px;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
            text-decoration: none;
        }

        .cart-link:hover,
        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: scale(1.02);
        }

        .cart-count {
            background: #ff9b71;
            color: #142f2b;
            font-weight: bold;
            border-radius: 40px;
            padding: 2px 12px;
            font-size: 1rem;
        }

        .category-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 2rem 0 1.2rem;
            justify-content: center;
        }

        .tab {
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            padding: 12px 28px;
            border-radius: 60px;
            font-weight: 600;
            font-size: 1.15rem;
            color: var(--text-secondary);
            cursor: pointer;
            box-shadow: var(--card-shadow);
            transition: 0.15s;
        }

        .tab.active {
            background: var(--accent-teal);
            color: white;
            border-color: var(--accent-teal);
            box-shadow: 0 14px 24px -12px var(--accent-teal);
        }

        .tab:hover {
            transform: translateY(-3px);
        }

        .search-filter {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 2rem 0 1.8rem;
        }

        .search-box {
            flex: 2 1 300px;
            display: flex;
            align-items: center;
            background: var(--bg-surface);
            border-radius: 56px;
            padding: 8px 22px;
            border: 1px solid var(--border-light);
            box-shadow: var(--card-shadow);
        }

        .search-box i {
            color: var(--accent-teal);
            font-size: 1.3rem;
            margin-right: 12px;
        }

        .search-box input {
            border: none;
            background: transparent;
            width: 100%;
            font-size: 1.1rem;
            padding: 10px 0;
            color: var(--text-primary);
            outline: none;
        }

        .filter-controls select {
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            padding: 14px 36px 14px 22px;
            border-radius: 50px;
            font-size: 1rem;
            color: var(--text-primary);
            cursor: pointer;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="%231b7f74" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>');
            background-repeat: no-repeat;
            background-position: right 18px center;
        }

        .category-section {
            display: none;
            margin-bottom: 3rem;
        }

        .category-section.active {
            display: block;
        }

        .category-section>h2 {
            font-size: 2rem;
            font-weight: 600;
            margin: 1rem 0 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 3px solid var(--accent-teal);
            padding-bottom: 0.6rem;
        }

        .subcategory {
            margin: 2.5rem 0 1.2rem;
        }

        .subcategory h3 {
            font-size: 1.7rem;
            margin-bottom: 1rem;
            color: var(--accent-teal);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 1.8rem;
            margin-bottom: 2rem;
        }

        .product-card {
            background: var(--bg-surface);
            border-radius: 32px;
            padding: 1rem 1rem 1.3rem;
            box-shadow: var(--card-shadow);
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            border: 1px solid var(--border-light);
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
        }

        .product-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent-soft);
            margin-bottom: 0.8rem;
            background: #eef3f1;
        }

        .product-card h4 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }

        .product-kannada {
            font-size: 0.85rem;
            color: var(--accent-teal);
            font-weight: 600;
            background: var(--accent-soft);
            padding: 0.2rem 0.8rem;
            border-radius: 40px;
            display: inline-block;
            margin: 0.3rem 0 0.2rem;
        }

        .product-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            padding: 0 4px;
        }

        .base-price-hint {
            font-size: 0.7rem;
            background: var(--weight-bg);
            display: inline-block;
            padding: 0.1rem 0.8rem;
            border-radius: 20px;
            margin: 4px 0;
            color: var(--text-secondary);
        }

        .weight-selector {
            width: 85%;
            margin: 8px 0 6px;
            padding: 8px;
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            border-radius: 60px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
        }

        .dynamic-price {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--accent-teal);
            margin: 8px 0 6px;
        }

        .add-to-cart {
            background: var(--accent-teal);
            border: none;
            color: white;
            padding: 10px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            margin-top: 6px;
            cursor: pointer;
            transition: 0.2s;
            width: 85%;
        }

        .add-to-cart:hover {
            background: #0f5f56;
            transform: scale(1.02);
        }

        footer {
            background: var(--footer-bg);
            color: white;
            padding: 2rem 1.8rem;
            border-radius: 44px 44px 0 0;
            margin-top: 3rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }

        .footer-links {
            display: flex;
            gap: 2rem;
        }

        .footer-links a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
        }

        @media (max-width: 600px) {
            .logo-section {
                width: 100%;
                min-width: 0;
            }

            .logo {
                min-width: 0;
                flex-wrap: wrap;
            }

            .logo h1 {
                min-width: 0;
                font-size: 1.25rem;
                overflow-wrap: anywhere;
            }

            .tagline {
                font-size: 0.75rem;
                overflow-wrap: anywhere;
            }

            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .product-image {
                width: 85px;
                height: 85px;
            }

            .dynamic-price {
                font-size: 1.3rem;
            }

            .add-to-cart {
                font-size: 0.75rem;
                width: 100%;
            }

            .weight-selector {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="category-style.css">
    <link rel="stylesheet" href="../index.css">
</head>

<body>
    <div class="container">
        <header>
            <div class="logo-section">
                <div class="logo">
                    <i class="fas fa-seedling"></i>
                    <img src="../company_logo.jpg" alt="KSR GURU JI" class="ksr-logo-img" style="width:42px;height:42px;border-radius:50%;object-fit:cover;box-shadow:0 4px 10px rgba(15,123,58,.2);border:2px solid rgba(255,255,255,.8);flex-shrink:0;margin-right:10px;" onerror="this.style.display='none'">
                    <h1>KSR GURU JI Vegetables and Fruits</h1>
                </div>
                <p class="tagline">ಕೆ ಎಸ್ ಆರ್ ಗುರು ತಾಜಾ ತರಕಾರಿ & ಹಣ್ಣುಗಳು | 250g · 500g · 750g · 1kg</p>
            </div>
            <div class="user-controls">
                <a href="../cart/cart.php" class="cart-link" id="cartLink">
                    <i class="fas fa-basket-shopping"></i>
                    <span class="cart-count" id="cartCount"><?php echo $cartCount; ?></span>
                </a>
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </header>
        <nav class="category-tabs">
            <button class="tab active" data-category="indian">🇮🇳 Indian Veg</button>
            <button class="tab" data-category="global">🌍 Global Veg</button>
            <button class="tab" data-category="daily">🧄 Daily Herbs</button>
            <button class="tab" data-category="fruits">🍎 Fruits</button>
        </nav>
        <div class="search-filter">
            <div class="search-box">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="ಹುಡುಕಿ (Apple, ತರಕಾರಿ...)" autocomplete="off">
            </div>
            <div class="filter-controls">
                <select id="sortSelect">
                    <option value="name">Sort by Name</option>
                    <option value="price-low">Price (Low to High)</option>
                    <option value="price-high">Price (High to Low)</option>
                </select>
            </div>
        </div>
        <main>
            <div class="category-section active" id="indian">
                <h2><i class="fas fa-pepper-hot"></i> Indian staples & leafy</h2>
                <div class="subcategory">
                    <h3><i class="fas fa-potato"></i> Everyday Indian vegetables</h3>
                    <div class="products-grid" id="indianMainGrid"></div>
                </div>
                <div class="subcategory">
                    <h3><i class="fas fa-leaf"></i> Indian leafy greens</h3>
                    <div class="products-grid" id="indianLeafyGrid"></div>
                </div>
            </div>
            <div class="category-section" id="global">
                <h2><i class="fas fa-globe"></i> Global veggies & exotic</h2>
                <div class="products-grid" id="globalGrid"></div>
            </div>
            <div class="category-section" id="daily">
                <h2><i class="fas fa-mortar-pestle"></i> Daily aromatics & herbs</h2>
                <div class="products-grid" id="dailyHerbsGrid"></div>
            </div>
            <div class="category-section" id="fruits">
                <h2><i class="fas fa-apple-whole"></i> Fresh Fruits – ಹಣ್ಣುಗಳು</h2>
                <div class="subcategory">
                    <h3><i class="fas fa-crown"></i> Popular Indian Fruits</h3>
                    <div class="products-grid" id="indianFruitsGrid"></div>
                </div>
                <div class="subcategory">
                    <h3><i class="fas fa-globe"></i> Global & Exotic Fruits</h3>
                    <div class="products-grid" id="globalFruitsGrid"></div>
                </div>
                <div class="subcategory">
                    <h3><i class="fas fa-heart-pulse"></i> Daily Health Fruits</h3>
                    <div class="products-grid" id="dailyFruitsGrid"></div>
                </div>
            </div>
        </main>
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
            const weightOptions = <?php echo json_encode($weightOptions); ?>;
            // ----- WEIGHT OPTIONS (same as static) -----
            const WEIGHT_OPTIONS = weightOptions;
            // ----- Helper: compute price -----
            function computePrice(pricePerKg, grams) {
                return (pricePerKg * grams) / 1000;
            }
            // ----- CART STATE (localStorage, but we also sync via AJAX) -----
            let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
            let currentSort = 'name';
            let currentSearch = '';
            // ----- DOM refs -----
            const containers = {
                indianMain: document.getElementById('indianMainGrid'),
                indianLeafy: document.getElementById('indianLeafyGrid'),
                global: document.getElementById('globalGrid'),
                dailyHerbs: document.getElementById('dailyHerbsGrid'),
                indianFruits: document.getElementById('indianFruitsGrid'),
                globalFruits: document.getElementById('globalFruitsGrid'),
                dailyFruits: document.getElementById('dailyFruitsGrid')
            };
            const cartCountSpan = document.getElementById('cartCount');
            const searchInput = document.getElementById('searchInput');
            const sortSelect = document.getElementById('sortSelect');
            const tabs = document.querySelectorAll('.tab');
            // ----- CART FUNCTIONS (with AJAX sync) -----
            function updateCartBadge() {
                const totalQty = cart.reduce((sum, i) => sum + (i.qty || 0), 0);
                cartCountSpan.innerText = totalQty;
                localStorage.setItem('glowCartNew', JSON.stringify(cart));
            }

            function addToCart(productId, weightLabel, unitPrice, productName, description, imageUrl) {
                const product = productsData.find(p => p.id === productId);
                const formData = new FormData();
                formData.append('id', productId);
                formData.append('qty', 1);
                formData.append('variant', weightLabel);
                formData.append('custom_price', unitPrice);
                formData.append('name', productName);
                formData.append('kn', product ? (product.knName || '') : '');
                formData.append('image', imageUrl);
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
            // ----- FILTER & SORT -----
            function getFilteredByCategory(category) {
                let filtered = productsData.filter(p => p.category === category);
                if (currentSearch) {
                    const term = currentSearch.toLowerCase();
                    filtered = filtered.filter(p =>
                        p.name.toLowerCase().includes(term) ||
                        p.kannada.toLowerCase().includes(term) ||
                        p.desc.toLowerCase().includes(term)
                    );
                }
                if (currentSort === 'name') {
                    filtered.sort((a, b) => a.name.localeCompare(b.name));
                } else if (currentSort === 'price-low') {
                    filtered.sort((a, b) => a.pricePerKg - b.pricePerKg);
                } else if (currentSort === 'price-high') {
                    filtered.sort((a, b) => b.pricePerKg - a.pricePerKg);
                }
                return filtered;
            }

            function renderGrid(container, category) {
                if (!container) return;
                const itemsList = getFilteredByCategory(category);
                if (itemsList.length === 0) {
                    container.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:2rem;">✨ ಯಾವುದೂ ಸಿಗಲಿಲ್ಲ ✨</div>`;
                    return;
                }
                let html = '';
                for (let p of itemsList) {
                    const defaultGrams = 250;
                    const defaultPrice = computePrice(p.pricePerKg, defaultGrams);
                    // Normalize image path (ensure it's web-friendly)
                    let imgUrl = p.imageUrl;
                    if (imgUrl && !imgUrl.startsWith('http') && !imgUrl.startsWith('data:')) {
                        const match = imgUrl.match(/images[\/\\](.*)/i);
                        if (match) {
                            imgUrl = '../images/' + match[1].replace(/\\/g, '/');
                        } else if (!imgUrl.startsWith('../') && !imgUrl.startsWith('images/')) {
                            const cleanUrl = imgUrl.startsWith('/') ? imgUrl.substring(1) : imgUrl;
                            imgUrl = '../' + cleanUrl;
                        }
                    }
                    html += `<div class="product-card" data-pid="${p.id}">
                <img src="${imgUrl}" alt="" class="product-image" onerror="this.src='https://placehold.co/200x200?text=${encodeURIComponent(p.name)}'">
                <h4>${p.name}</h4>
                <span class="product-kannada">${p.kannada}</span>
                <p class="product-desc">${p.desc}</p>
                <div class="base-price-hint">₹${p.pricePerKg}/kg</div>
                <select class="weight-selector" data-pid="${p.id}" data-baseprice="${p.pricePerKg}">
                    ${WEIGHT_OPTIONS.map(w => `<option value="${w.grams}" data-label="${w.label}" ${w.grams===250?'selected':''}>${w.label}</option>`).join('')}
                </select>
                <div class="dynamic-price" id="price-${p.id}">₹${defaultPrice.toFixed(2)}</div>
                <button class="add-to-cart" data-id="${p.id}" data-name="${p.name.replace(/['"]/g, '&quot;')}" data-desc="${p.desc.replace(/['"]/g, '&quot;')}" data-img="${imgUrl}"><i class="fas fa-cart-plus"></i> Add</button>
            </div>`;
                }
                container.innerHTML = html;
                // Attach weight selector events
                container.querySelectorAll('.weight-selector').forEach(sel => {
                    const pid = parseInt(sel.dataset.pid);
                    const base = parseFloat(sel.dataset.baseprice);
                    const priceSpan = document.getElementById(`price-${pid}`);
                    const update = () => {
                        const grams = parseInt(sel.value);
                        priceSpan.innerText = `₹${computePrice(base, grams).toFixed(2)}`;
                    };
                    sel.addEventListener('change', update);
                    update();
                });
                // Attach add to cart events
                container.querySelectorAll('.add-to-cart').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        const pid = parseInt(this.dataset.id);
                        const product = productsData.find(p => p.id === pid);
                        if (!product) return;
                        const card = this.closest('.product-card');
                        const select = card.querySelector('.weight-selector');
                        const selectedOpt = select.options[select.selectedIndex];
                        const weightLabel = selectedOpt.dataset.label;
                        const grams = parseInt(select.value);
                        const unitPrice = computePrice(product.pricePerKg, grams);
                        addToCart(pid, weightLabel, unitPrice, product.name, product.desc, this.dataset.img);
                    });
                });
            }

            function renderAll() {
                renderGrid(containers.indianMain, 'indianMain');
                renderGrid(containers.indianLeafy, 'indianLeafy');
                renderGrid(containers.global, 'global');
                renderGrid(containers.dailyHerbs, 'dailyHerbs');
                renderGrid(containers.indianFruits, 'indianFruits');
                renderGrid(containers.globalFruits, 'globalFruits');
                renderGrid(containers.dailyFruits, 'dailyFruits');
            }
            // ----- EVENT LISTENERS -----
            searchInput.addEventListener('input', function(e) {
                currentSearch = e.target.value;
                renderAll();
            });
            sortSelect.addEventListener('change', function(e) {
                currentSort = e.target.value;
                renderAll();
            });
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    const category = this.dataset.category;
                    document.querySelectorAll('.category-section').forEach(sec => sec.classList.remove('active'));
                    document.getElementById(category).classList.add('active');
                });
            });
            // Theme toggle
            document.getElementById('themeToggle').addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-moon');
                icon.classList.toggle('fa-sun');
            });
            // ----- INIT -----
            renderAll();
            updateCartBadge();
            // If we have a cart from session, we could also sync with server.
            // But we use localStorage for now.
            console.log('🌿 Vegetables & Fruits page loaded with ' + productsData.length + ' products');
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
                            window.showFastCartToast ? window.showFastCartToast('Your cart is empty! Add items to proceed.') : alert('Cart is empty');
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