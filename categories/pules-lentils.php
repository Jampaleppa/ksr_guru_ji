<?php
// ============================================================
// pulses-lentils.php - Dynamic Pulses & Dal page
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
$categorySlug = 'pulses-lentils';
$pulsesCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($pulsesCategory) {
    // Fetch products from this category
    $dbProducts = getProductsByCategory($pulsesCategory['id']);
    if (!empty($dbProducts)) {
        foreach ($dbProducts as $p) {
            // Build variants from weight/price or variants JSON
            $variants = [];
            if (!empty($p['variants'])) {
                $variants = is_array($p['variants']) ? $p['variants'] : json_decode($p['variants'], true);
            } elseif (!empty($p['weight']) && !empty($p['price'])) {
                // Single variant
                $variants = [['label' => $p['weight'], 'price' => $p['price']]];
            } else {
                $variants = [['label' => '1 unit', 'price' => $p['price']]];
            }
            $productsData[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'nameKn' => $p['kn_name'] ?? '',
                'category' => $p['category_slug'] ?? 'other',
                'description' => $p['description_en'] ?? '',
                'imageurl' => $p['image_url'] ?? '',
                'weights' => $variants
            ];
        }
    }
}
// Fallback to static data if no DB products found
if (empty($productsData)) {
    // Embedded static product data (original static list compressed for space)
    $staticProducts = [
        ['id'=>1,'name'=>'Toor Dal (Arhar Dal)','nameKn'=>'ತೊವ್ವೆ ಬೇಳೆ','category'=>'toor','description'=>'Split pigeon pea, rich in protein and fiber. Used in sambar and dal.','imageurl'=>'images\\Toor Dal.jpg','weights'=>[['label'=>'250g','price'=>35],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>130],['label'=>'5kg','price'=>640]]],
        ['id'=>2,'name'=>'Moong Dal','nameKn'=>'ಹೆಸರು ಬೇಳೆ','category'=>'moong','description'=>'Split green gram, light and easy to digest. Perfect for khichdi and soups.','imageurl'=>'images\\Organic Green Gram.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>75],['label'=>'1kg','price'=>145],['label'=>'5kg','price'=>700]]],
        ['id'=>3,'name'=>'Whole Green Gram','nameKn'=>'ಹೆಸರು ಕಾಳು','category'=>'moong','description'=>'Whole moong beans with green skin. High in fibre and protein.','imageurl'=>'images\\Green gram.jpg','weights'=>[['label'=>'250g','price'=>38],['label'=>'500g','price'=>72],['label'=>'1kg','price'=>140],['label'=>'5kg','price'=>680]]],
        ['id'=>4,'name'=>'Split Green Gram','nameKn'=>'ಸಿಪ್ಪೆ ತೆಗೆದ ಹೆಸರು ಕಾಳು','category'=>'moong','description'=>'Split moong dal without skin. Quick cooking, ideal for soups.','imageurl'=>'images\\Split Green Gram.jpg','weights'=>[['label'=>'250g','price'=>42],['label'=>'500g','price'=>80],['label'=>'1kg','price'=>155],['label'=>'5kg','price'=>750]]],
        ['id'=>5,'name'=>'Urad Dal','nameKn'=>'ಉದ್ದಿನ ಬೇಳೆ','category'=>'urad','description'=>'Split black gram, creamy texture. Used in dosa, idli and vada.','imageurl'=>'images\\Urad Dal.jpg','weights'=>[['label'=>'250g','price'=>45],['label'=>'500g','price'=>88],['label'=>'1kg','price'=>170],['label'=>'5kg','price'=>820]]],
        ['id'=>6,'name'=>'Whole Black Gram','nameKn'=>'ಸಂಪೂರ್ಣ ಉದ್ದಿನ ಕಾಳು','category'=>'urad','description'=>'Whole black gram with black skin, rich in iron and protein.','imageurl'=>'images\\Black Gram (Urad).jpg','weights'=>[['label'=>'250g','price'=>42],['label'=>'500g','price'=>80],['label'=>'1kg','price'=>155],['label'=>'5kg','price'=>750]]],
        ['id'=>7,'name'=>'Split Black Gram','nameKn'=>'ಸಿಪ್ಪೆ ತೆಗೆದ ಉದ್ದಿನ ಕಾಳು','category'=>'urad','description'=>'Split black gram without skin, white in colour. Used in batters.','imageurl'=>'images\\Black Gram (Urad) spit.jpg','weights'=>[['label'=>'250g','price'=>47],['label'=>'500g','price'=>90],['label'=>'1kg','price'=>175],['label'=>'5kg','price'=>840]]],
        ['id'=>8,'name'=>'Masoor Dal','nameKn'=>'ಮಸೂರ ಬೇಳೆ','category'=>'masoor','description'=>'Red split lentil, cooks quickly. Popular in soups and curries.','imageurl'=>'images\\Masoor Dal.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>9,'name'=>'Whole Masoor','nameKn'=>'ಸಂಪೂರ್ಣ ಮಸೂರ','category'=>'masoor','description'=>'Whole brown lentil with skin. High in fibre, used in stews.','imageurl'=>'images\\Whole Masoor.jpg','weights'=>[['label'=>'250g','price'=>28],['label'=>'500g','price'=>55],['label'=>'1kg','price'=>105],['label'=>'5kg','price'=>500]]],
        ['id'=>10,'name'=>'Red Lentil','nameKn'=>'ಕೆಂಪು ಮಸೂರ','category'=>'masoor','description'=>'Red lentil, similar to masoor dal. Mild and creamy when cooked.','imageurl'=>'images\\Red Lentil.jpg','weights'=>[['label'=>'250g','price'=>32],['label'=>'500g','price'=>60],['label'=>'1kg','price'=>115],['label'=>'5kg','price'=>550]]],
        ['id'=>11,'name'=>'Brown Lentil','nameKn'=>'ಕಂದು ಮಸೂರ','category'=>'lentil','description'=>'Brown lentils hold shape well. Great for salads and hearty stews.','imageurl'=>'images\\Brown Lentil.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>12,'name'=>'Green Lentil','nameKn'=>'ಹಸಿರು ಮಸೂರ','category'=>'lentil','description'=>'Green lentils, earthy flavour. Used in European and Indian dishes.','imageurl'=>'images\\Green Lentil.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>13,'name'=>'Black Lentil','nameKn'=>'ಕಪ್ಪು ಮಸೂರ','category'=>'lentil','description'=>'Black beluga lentils, tiny and rich. Used in salads and soups.','imageurl'=>'images\\Black Lentil.jpg','weights'=>[['label'=>'250g','price'=>38],['label'=>'500g','price'=>72],['label'=>'1kg','price'=>140],['label'=>'5kg','price'=>680]]],
        ['id'=>14,'name'=>'French Green Lentil','nameKn'=>'ಫ್ರೆಂಚ್ ಹಸಿರು ಮಸೂರ','category'=>'lentil','description'=>'French green lentils (Puy), robust flavour. Retain shape well.','imageurl'=>'images\\French Green Lentil.jpg','weights'=>[['label'=>'250g','price'=>45],['label'=>'500g','price'=>85],['label'=>'1kg','price'=>165],['label'=>'5kg','price'=>800]]],
        ['id'=>15,'name'=>'Yellow Lentil','nameKn'=>'ಹಳದಿ ಮಸೂರ','category'=>'lentil','description'=>'Yellow lentils, mild and creamy. Used in Indian dals and soups.','imageurl'=>'images\\Yellow Lentil.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>16,'name'=>'Beluga Lentil','nameKn'=>'ಬೆಲುಗಾ ಮಸೂರ','category'=>'lentil','description'=>'Black caviar-like lentils, nutty flavour. Great in salads.','imageurl'=>'images\\Beluga Lentils.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>76],['label'=>'1kg','price'=>148],['label'=>'5kg','price'=>720]]],
        ['id'=>17,'name'=>'Crimson Lentil','nameKn'=>'ಕಡುಗೆಂಪು ಮಸೂರ','category'=>'lentil','description'=>'Crimson red lentils, cook quickly and turn golden. Used in dals.','imageurl'=>'images\\Crimson Lentil.jpg','weights'=>[['label'=>'250g','price'=>33],['label'=>'500g','price'=>62],['label'=>'1kg','price'=>120],['label'=>'5kg','price'=>580]]],
        ['id'=>18,'name'=>'Chana Dal','nameKn'=>'ಕಡಲೆ ಬೇಳೆ','category'=>'chana','description'=>'Split Bengal gram, sweet and nutty. Used in dals and sweets.','imageurl'=>'images\\Chana Dal.jpg','weights'=>[['label'=>'250g','price'=>38],['label'=>'500g','price'=>72],['label'=>'1kg','price'=>140],['label'=>'5kg','price'=>680]]],
        ['id'=>19,'name'=>'Kala Chana','nameKn'=>'ಕಪ್ಪು ಕಡಲೆ','category'=>'chana','description'=>'Black chickpeas, small and dark. Rich in iron, used in curries.','imageurl'=>'images\\Kala Chana.jpg','weights'=>[['label'=>'250g','price'=>32],['label'=>'500g','price'=>60],['label'=>'1kg','price'=>115],['label'=>'5kg','price'=>550]]],
        ['id'=>20,'name'=>'Kabuli Chana','nameKn'=>'ಕಾಬೂಲಿ ಕಡಲೆ','category'=>'chana','description'=>'Large white chickpeas (garbanzo), used in salads and curries.','imageurl'=>'images\\Kabuli Chana.jpg','weights'=>[['label'=>'250g','price'=>45],['label'=>'500g','price'=>85],['label'=>'1kg','price'=>165],['label'=>'5kg','price'=>800]]],
        ['id'=>21,'name'=>'Desi Chickpea','nameKn'=>'ದೇಸಿ ಕಡಲೆ','category'=>'chana','description'=>'Local variety of chickpea, smaller and darker. Used in traditional dishes.','imageurl'=>'images\\Desi Chickpea.jpg','weights'=>[['label'=>'250g','price'=>35],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>130],['label'=>'5kg','price'=>640]]],
        ['id'=>22,'name'=>'White Chickpea','nameKn'=>'ಬಿಳಿ ಕಡಲೆ','category'=>'chana','description'=>'White chickpeas, creamy texture. Common in Mediterranean cuisine.','imageurl'=>'images\\White Chickpea.jpg','weights'=>[['label'=>'250g','price'=>42],['label'=>'500g','price'=>80],['label'=>'1kg','price'=>155],['label'=>'5kg','price'=>750]]],
        ['id'=>23,'name'=>'Black Chickpea','nameKn'=>'ಕಪ್ಪು ಕಡಲೆ','category'=>'chana','description'=>'Black chickpeas, similar to kala chana but slightly larger.','imageurl'=>'images\\Black Chickpea.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>24,'name'=>'Green Chickpea','nameKn'=>'ಹಸಿರು ಕಡಲೆ','category'=>'chana','description'=>'Fresh green chickpeas, sweet and crunchy. Used in salads and curries.','imageurl'=>'images\\Green Chickpea.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>76],['label'=>'1kg','price'=>148],['label'=>'5kg','price'=>720]]],
        ['id'=>25,'name'=>'Fresh Chickpea','nameKn'=>'ತಾಜಾ ಕಡಲೆ','category'=>'chana','description'=>'Fresh chickpeas, harvested green. Eaten raw or lightly cooked.','imageurl'=>'images\\Fresh Chickpea.jpg','weights'=>[['label'=>'250g','price'=>50],['label'=>'500g','price'=>95],['label'=>'1kg','price'=>185],['label'=>'5kg','price'=>900]]],
        ['id'=>26,'name'=>'Rajma (Red Kidney Bean)','nameKn'=>'ರಾಜ್ಮಾ','category'=>'rajma','description'=>'Red kidney beans, popular in North Indian curries. Rich in protein.','imageurl'=>'images\\kidney-beans-rajma.jpg','weights'=>[['label'=>'250g','price'=>48],['label'=>'500g','price'=>92],['label'=>'1kg','price'=>180],['label'=>'5kg','price'=>870]]],
        ['id'=>27,'name'=>'White Kidney Bean','nameKn'=>'ಬಿಳಿ ರಾಜ್ಮಾ','category'=>'rajma','description'=>'White kidney beans, mild flavour. Used in salads and stews.','imageurl'=>'images\\White Kidney Bean.jpg','weights'=>[['label'=>'250g','price'=>45],['label'=>'500g','price'=>85],['label'=>'1kg','price'=>165],['label'=>'5kg','price'=>800]]],
        ['id'=>28,'name'=>'Light Red Kidney Bean','nameKn'=>'ತಿಳಿ ಕೆಂಪು ರಾಜ್ಮಾ','category'=>'rajma','description'=>'Light red kidney beans, slightly milder than dark red.','imageurl'=>'images\\Light Red Kidney Bean.jpg','weights'=>[['label'=>'250g','price'=>46],['label'=>'500g','price'=>88],['label'=>'1kg','price'=>170],['label'=>'5kg','price'=>820]]],
        ['id'=>29,'name'=>'Dark Red Kidney Bean','nameKn'=>'ಗಾಢ ಕೆಂಪು ರಾಜ್ಮಾ','category'=>'rajma','description'=>'Dark red kidney beans, rich colour and flavour. Used in chili.','imageurl'=>'images\\Dark Red Kidney Bean.jpg','weights'=>[['label'=>'250g','price'=>50],['label'=>'500g','price'=>95],['label'=>'1kg','price'=>185],['label'=>'5kg','price'=>900]]],
        ['id'=>30,'name'=>'Speckled Kidney Bean','nameKn'=>'ಚುಕ್ಕೆ ರಾಜ್ಮಾ','category'=>'rajma','description'=>'Speckled kidney beans, attractive appearance and nutty taste.','imageurl'=>'images\\Speckled Kidney Bean.jpg','weights'=>[['label'=>'250g','price'=>48],['label'=>'500g','price'=>92],['label'=>'1kg','price'=>180],['label'=>'5kg','price'=>870]]],
        ['id'=>31,'name'=>'Black Bean','nameKn'=>'ಕಪ್ಪು ಬೀನ್ಸ್','category'=>'bean','description'=>'Black beans, popular in Latin American cuisine. Creamy texture.','imageurl'=>'images\\Black Bean.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>76],['label'=>'1kg','price'=>148],['label'=>'5kg','price'=>720]]],
        ['id'=>32,'name'=>'White Bean','nameKn'=>'ಬಿಳಿ ಬೀನ್ಸ್','category'=>'bean','description'=>'White beans, mild flavour. Used in soups and salads.','imageurl'=>'images\\White Bean.jpg','weights'=>[['label'=>'250g','price'=>38],['label'=>'500g','price'=>72],['label'=>'1kg','price'=>140],['label'=>'5kg','price'=>680]]],
        ['id'=>33,'name'=>'Navy Bean','nameKn'=>'ನೇವಿ ಬೀನ್ಸ್','category'=>'bean','description'=>'Small white beans, often used in baked beans.','imageurl'=>'images\\Navy Beans.jpg','weights'=>[['label'=>'250g','price'=>35],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>130],['label'=>'5kg','price'=>640]]],
        ['id'=>34,'name'=>'Great Northern Bean','nameKn'=>'ಗ್ರೇಟ್ ನಾರ್ದರ್ನ್ ಬೀನ್','category'=>'bean','description'=>'Large white bean, creamy and mild. Good in soups.','imageurl'=>'images\\Great Northern Bean.jpg','weights'=>[['label'=>'250g','price'=>38],['label'=>'500g','price'=>72],['label'=>'1kg','price'=>140],['label'=>'5kg','price'=>680]]],
        ['id'=>35,'name'=>'Cannellini Bean','nameKn'=>'ಕ್ಯಾನೆಲ್ಲಿನಿ ಬೀನ್ಸ್','category'=>'bean','description'=>'Italian white bean, smooth and buttery. Used in minestrone.','imageurl'=>'images\\Cannellini Beans.jpg','weights'=>[['label'=>'250g','price'=>42],['label'=>'500g','price'=>80],['label'=>'1kg','price'=>155],['label'=>'5kg','price'=>750]]],
        ['id'=>36,'name'=>'Pinto Bean','nameKn'=>'ಪಿಂಟೊ ಬೀನ್ಸ್','category'=>'bean','description'=>'Pinto beans, speckled brown. Popular in Mexican dishes.','imageurl'=>'images\\Pinto Bean.jpg','weights'=>[['label'=>'250g','price'=>36],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>132],['label'=>'5kg','price'=>640]]],
        ['id'=>37,'name'=>'Cranberry Bean','nameKn'=>'ಕ್ರಾನ್ಬೆರಿ ಬೀನ್ಸ್','category'=>'bean','description'=>'Cranberry beans, pink speckled. Nutty flavour, used in salads.','imageurl'=>'images\\Cranberry Beans.jpg','weights'=>[['label'=>'250g','price'=>44],['label'=>'500g','price'=>84],['label'=>'1kg','price'=>162],['label'=>'5kg','price'=>780]]],
        ['id'=>38,'name'=>'Borlotti Bean','nameKn'=>'ಬೊರ್ಲೊಟ್ಟಿ ಬೀನ್ಸ್','category'=>'bean','description'=>'Borlotti beans, similar to cranberry. Creamy texture.','imageurl'=>'images\\Borlotti Bean.jpg','weights'=>[['label'=>'250g','price'=>44],['label'=>'500g','price'=>84],['label'=>'1kg','price'=>162],['label'=>'5kg','price'=>780]]],
        ['id'=>39,'name'=>'Haricot Bean','nameKn'=>'ಹ್ಯಾರಿಕಾಟ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Small white bean, similar to navy bean. Used in baked dishes.','imageurl'=>'images\\Haricot Bean.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>40,'name'=>'Lima Bean','nameKn'=>'ಲಿಮಾ ಬೀನ್ಸ್','category'=>'bean','description'=>'Large flat beans, buttery and starchy. Used in succotash.','imageurl'=>'images\\Lima Bean.jpg','weights'=>[['label'=>'250g','price'=>46],['label'=>'500g','price'=>88],['label'=>'1kg','price'=>170],['label'=>'5kg','price'=>820]]],
        ['id'=>41,'name'=>'Butter Bean','nameKn'=>'ಬಟರ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Butter beans, large and creamy. Great in casseroles.','imageurl'=>'images\\Butter Bean.jpg','weights'=>[['label'=>'250g','price'=>48],['label'=>'500g','price'=>92],['label'=>'1kg','price'=>180],['label'=>'5kg','price'=>870]]],
        ['id'=>42,'name'=>'Fava Bean','nameKn'=>'ಫಾವಾ ಬೀನ್ಸ್','category'=>'bean','description'=>'Broad beans, known as fava. Earthy flavour, used in Mediterranean cooking.','imageurl'=>'images\\Fava Bean.jpg','weights'=>[['label'=>'250g','price'=>50],['label'=>'500g','price'=>95],['label'=>'1kg','price'=>185],['label'=>'5kg','price'=>900]]],
        ['id'=>43,'name'=>'Broad Bean','nameKn'=>'ಬ್ರಾಡ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Fresh broad beans, green and tender. Used in salads and stews.','imageurl'=>'images\\Broad Bean.jpg','weights'=>[['label'=>'250g','price'=>42],['label'=>'500g','price'=>80],['label'=>'1kg','price'=>155],['label'=>'5kg','price'=>750]]],
        ['id'=>44,'name'=>'Hyacinth Bean','nameKn'=>'ಹೈಸಿಂತ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Hyacinth beans, used in Indian cuisine. Rich in nutrients.','imageurl'=>'images\\Hyacinth Bean.jpg','weights'=>[['label'=>'250g','price'=>38],['label'=>'500g','price'=>72],['label'=>'1kg','price'=>140],['label'=>'5kg','price'=>680]]],
        ['id'=>45,'name'=>'Field Bean','nameKn'=>'ಫೀಲ್ಡ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Field beans, used as cover crop. Edible and nutritious.','imageurl'=>'images\\Field Bean.jpg','weights'=>[['label'=>'250g','price'=>32],['label'=>'500g','price'=>60],['label'=>'1kg','price'=>115],['label'=>'5kg','price'=>550]]],
        ['id'=>46,'name'=>'Lablab Bean','nameKn'=>'ಲ್ಯಾಬ್ಲಾಬ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Lablab beans, also known as hyacinth bean. Used in Asian cooking.','imageurl'=>'images\\Lablab Bean.jpg','weights'=>[['label'=>'250g','price'=>36],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>132],['label'=>'5kg','price'=>640]]],
        ['id'=>47,'name'=>'Scarlet Runner Bean','nameKn'=>'ಸ್ಕಾರ್ಲೆಟ್ ರನ್ನರ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Scarlet runner beans, colourful and flavourful. Used in stews.','imageurl'=>'images\\Scarlet Runner Bean.jpg','weights'=>[['label'=>'250g','price'=>42],['label'=>'500g','price'=>80],['label'=>'1kg','price'=>155],['label'=>'5kg','price'=>750]]],
        ['id'=>48,'name'=>'Runner Bean','nameKn'=>'ರನ್ನರ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Runner beans, similar to scarlet runner. Used in European dishes.','imageurl'=>'images\\Runner Bean.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>76],['label'=>'1kg','price'=>148],['label'=>'5kg','price'=>720]]],
        ['id'=>49,'name'=>'Adzuki Bean','nameKn'=>'ಅಡ್ಜುಕಿ ಬೀನ್ಸ್','category'=>'bean','description'=>'Adzuki beans, small and reddish. Used in sweet pastes and salads.','imageurl'=>'images\\Adzuki Bean.jpg','weights'=>[['label'=>'250g','price'=>44],['label'=>'500g','price'=>84],['label'=>'1kg','price'=>162],['label'=>'5kg','price'=>780]]],
        ['id'=>50,'name'=>'Mung Bean','nameKn'=>'ಹೆಸರು ಕಾಳು','category'=>'moong','description'=>'Whole mung beans, small and green. Used in sprouts and curries.','imageurl'=>'images\\Mung Bean.jpg','weights'=>[['label'=>'250g','price'=>38],['label'=>'500g','price'=>72],['label'=>'1kg','price'=>140],['label'=>'5kg','price'=>680]]],
        ['id'=>51,'name'=>'Black-eyed Pea','nameKn'=>'ಕಪ್ಪು ಕಣ್ಣಿನ ಬಟಾಣಿ','category'=>'bean','description'=>'Black-eyed peas, creamy texture. Popular in Southern US and African cuisine.','imageurl'=>'images\\Black-eyed Peas.jpg','weights'=>[['label'=>'250g','price'=>32],['label'=>'500g','price'=>60],['label'=>'1kg','price'=>115],['label'=>'5kg','price'=>550]]],
        ['id'=>52,'name'=>'Cowpea','nameKn'=>'ಕೌಪಿಯಾ','category'=>'bean','description'=>'Cowpeas, diverse varieties. Rich in protein, used in stews.','imageurl'=>'images\\Cowpea.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>53,'name'=>'Lobia','nameKn'=>'ಲೋಬಿಯಾ','category'=>'bean','description'=>'Lobia, also known as black-eyed peas. Mild and creamy.','imageurl'=>'images\\Lobia.jpg','weights'=>[['label'=>'250g','price'=>32],['label'=>'500g','price'=>60],['label'=>'1kg','price'=>115],['label'=>'5kg','price'=>550]]],
        ['id'=>54,'name'=>'Yardlong Bean','nameKn'=>'ಯಾರ್ಡ್ ಲಾಂಗ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Long green beans, used in Asian stir-fries and curries.','imageurl'=>'images\\Yardlong Bean.jpg','weights'=>[['label'=>'250g','price'=>28],['label'=>'500g','price'=>55],['label'=>'1kg','price'=>105],['label'=>'5kg','price'=>500]]],
        ['id'=>55,'name'=>'Asparagus Bean','nameKn'=>'ಆಸ್ಪ್ಯಾರಗಸ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Asparagus beans, similar to yardlong. Tender and flavourful.','imageurl'=>'images\\Asparagus Bean.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>56,'name'=>'Moth Bean','nameKn'=>'ಮೊಥ ಬೀನ್ಸ್','category'=>'bean','description'=>'Moth beans, also known as matki. Used in sprouts and curries.','imageurl'=>'images\\Moth Bean.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>57,'name'=>'Matki','nameKn'=>'ಮಟಕಿ','category'=>'bean','description'=>'Matki (moth beans), small and nutty. Used in Maharashtrian cuisine.','imageurl'=>'images\\Matki.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>58,'name'=>'Horse Gram','nameKn'=>'ಹುರಳಿ ಕಾಳು','category'=>'horsegram','description'=>'Horse gram, rich in iron and protein. Used in South Indian dishes.','imageurl'=>'images\\Horse Gram.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>59,'name'=>'Kulthi','nameKn'=>'ಕುಲ್ಥಿ','category'=>'horsegram','description'=>'Kulthi, another name for horse gram. Used in soups and dals.','imageurl'=>'images\\Kulthi.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>60,'name'=>'Soybean','nameKn'=>'ಸೋಯಾ ಬೀನ್ಸ್','category'=>'soy','description'=>'Soybeans, versatile and protein-rich. Used in tofu, milk, and flour.','imageurl'=>'images\\Soybeans.jpg','weights'=>[['label'=>'250g','price'=>42],['label'=>'500g','price'=>80],['label'=>'1kg','price'=>155],['label'=>'5kg','price'=>750]]],
        ['id'=>61,'name'=>'Black Soybean','nameKn'=>'ಕಪ್ಪು ಸೋಯಾ ಬೀನ್ಸ್','category'=>'soy','description'=>'Black soybeans, high in antioxidants. Used in Asian cuisine.','imageurl'=>'images\\Black Soybean.jpg','weights'=>[['label'=>'250g','price'=>48],['label'=>'500g','price'=>92],['label'=>'1kg','price'=>180],['label'=>'5kg','price'=>870]]],
        ['id'=>62,'name'=>'Yellow Soybean','nameKn'=>'ಹಳದಿ ಸೋಯಾ ಬೀನ್ಸ್','category'=>'soy','description'=>'Yellow soybeans, common variety. Used in soy milk and tofu.','imageurl'=>'images\\Yellow Soybean.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>76],['label'=>'1kg','price'=>148],['label'=>'5kg','price'=>720]]],
        ['id'=>63,'name'=>'Green Soybean','nameKn'=>'ಹಸಿರು ಸೋಯಾ ಬೀನ್ಸ್','category'=>'soy','description'=>'Green soybeans (edamame), harvested young. Eaten as snack.','imageurl'=>'images\\Green Soybean.jpg','weights'=>[['label'=>'250g','price'=>50],['label'=>'500g','price'=>95],['label'=>'1kg','price'=>185],['label'=>'5kg','price'=>900]]],
        ['id'=>64,'name'=>'Edamame','nameKn'=>'ಎಡಮೇಮ್','category'=>'soy','description'=>'Edamame, young green soybeans. Steamed and salted as appetizer.','imageurl'=>'images\\Edamame.jpg','weights'=>[['label'=>'250g','price'=>55],['label'=>'500g','price'=>105],['label'=>'1kg','price'=>200],['label'=>'5kg','price'=>980]]],
        ['id'=>65,'name'=>'Dry Pea','nameKn'=>'ಒಣ ಬಟಾಣಿ','category'=>'pea','description'=>'Dried peas, green or yellow. Used in soups and split pea dishes.','imageurl'=>'images\\Dry Pea.jpg','weights'=>[['label'=>'250g','price'=>28],['label'=>'500g','price'=>55],['label'=>'1kg','price'=>105],['label'=>'5kg','price'=>500]]],
        ['id'=>66,'name'=>'Yellow Split Pea','nameKn'=>'ಹಳದಿ ಸಿಪ್ಪೆ ಬಟಾಣಿ','category'=>'pea','description'=>'Yellow split peas, quick cooking. Used in dals and soups.','imageurl'=>'images\\Yellow Split Pea.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>67,'name'=>'Green Split Pea','nameKn'=>'ಹಸಿರು ಸಿಪ್ಪೆ ಬಟಾಣಿ','category'=>'pea','description'=>'Green split peas, similar to yellow. Used in soups.','imageurl'=>'images\\Green Split Pea.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>68,'name'=>'Garden Pea','nameKn'=>'ತೋಟದ ಬಟಾಣಿ','category'=>'pea','description'=>'Fresh garden peas, sweet and tender. Used in curries and rice.','imageurl'=>'images\\Garden Pea.jpg','weights'=>[['label'=>'250g','price'=>35],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>130],['label'=>'5kg','price'=>640]]],
        ['id'=>69,'name'=>'Snow Pea','nameKn'=>'ಸ್ನೋ ಬಟಾಣಿ','category'=>'pea','description'=>'Snow peas, flat and crisp. Used in stir-fries and salads.','imageurl'=>'images\\Snow Pea.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>76],['label'=>'1kg','price'=>148],['label'=>'5kg','price'=>720]]],
        ['id'=>70,'name'=>'Sugar Snap Pea','nameKn'=>'ಸಕ್ಕರ್ ಸ್ನ್ಯಾಪ್ ಬಟಾಣಿ','category'=>'pea','description'=>'Sugar snap peas, crunchy and sweet. Eaten raw or lightly cooked.','imageurl'=>'images\\Sugar Snap Pea.jpg','weights'=>[['label'=>'250g','price'=>45],['label'=>'500g','price'=>85],['label'=>'1kg','price'=>165],['label'=>'5kg','price'=>800]]],
        ['id'=>71,'name'=>'Marrowfat Pea','nameKn'=>'ಮ್ಯಾರೋಫ್ಯಾಟ್ ಬಟಾಣಿ','category'=>'pea','description'=>'Marrowfat peas, large and starchy. Used in British mushy peas.','imageurl'=>'images\\Marrowfat Pea.jpg','weights'=>[['label'=>'250g','price'=>32],['label'=>'500g','price'=>60],['label'=>'1kg','price'=>115],['label'=>'5kg','price'=>550]]],
        ['id'=>72,'name'=>'Pigeon Pea','nameKn'=>'ತೊಗರಿ ಬೇಳೆ','category'=>'toor','description'=>'Pigeon pea, also known as toor dal. Staple in Indian cuisine.','imageurl'=>'images\\Pigeon Pea.jpg','weights'=>[['label'=>'250g','price'=>35],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>130],['label'=>'5kg','price'=>640]]],
        ['id'=>73,'name'=>'Green Pigeon Pea','nameKn'=>'ಹಸಿರು ತೊಗರಿ ಬೇಳೆ','category'=>'toor','description'=>'Fresh green pigeon peas, used in curries and salads.','imageurl'=>'images\\Green Pigeon Pea.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>76],['label'=>'1kg','price'=>148],['label'=>'5kg','price'=>720]]],
        ['id'=>74,'name'=>'White Pea','nameKn'=>'ಬಿಳಿ ಬಟಾಣಿ','category'=>'pea','description'=>'White peas, used in North Indian street food (matar).','imageurl'=>'images\\White Pea.jpg','weights'=>[['label'=>'250g','price'=>28],['label'=>'500g','price'=>55],['label'=>'1kg','price'=>105],['label'=>'5kg','price'=>500]]],
        ['id'=>75,'name'=>'Green Pea','nameKn'=>'ಹಸಿರು ಬಟಾಣಿ','category'=>'pea','description'=>'Green peas, fresh or frozen. Versatile vegetable.','imageurl'=>'images\\Green PeasSoybean.jpg','weights'=>[['label'=>'250g','price'=>35],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>130],['label'=>'5kg','price'=>640]]],
        ['id'=>76,'name'=>'Black Turtle Bean','nameKn'=>'ಕಪ್ಪು ಆಮೆ ಬೀನ್ಸ್','category'=>'bean','description'=>'Black turtle beans, similar to black beans. Rich and earthy.','imageurl'=>'images\\Black Turtle Bean.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>76],['label'=>'1kg','price'=>148],['label'=>'5kg','price'=>720]]],
        ['id'=>77,'name'=>'Pink Bean','nameKn'=>'ಗುಲಾಬಿ ಬೀನ್ಸ್','category'=>'bean','description'=>'Pink beans, creamy and mild. Used in Caribbean cuisine.','imageurl'=>'images\\Pink Bean.jpg','weights'=>[['label'=>'250g','price'=>38],['label'=>'500g','price'=>72],['label'=>'1kg','price'=>140],['label'=>'5kg','price'=>680]]],
        ['id'=>78,'name'=>'Anasazi Bean','nameKn'=>'ಅನಸಾಜಿ ಬೀನ್ಸ್','category'=>'bean','description'=>'Anasazi beans, red and white speckled. Sweet and nutty.','imageurl'=>'images\\Anasazi Bean.jpg','weights'=>[['label'=>'250g','price'=>46],['label'=>'500g','price'=>88],['label'=>'1kg','price'=>170],['label'=>'5kg','price'=>820]]],
        ['id'=>79,'name'=>'Flageolet Bean','nameKn'=>'ಫ್ಲಾಜಿಯೊಲೆಟ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Flageolet beans, small and pale green. Delicate flavour.','imageurl'=>'images\\Flageolet Bean.jpg','weights'=>[['label'=>'250g','price'=>48],['label'=>'500g','price'=>92],['label'=>'1kg','price'=>180],['label'=>'5kg','price'=>870]]],
        ['id'=>80,'name'=>'Tepary Bean','nameKn'=>'ಟೆಪರಿ ಬೀನ್ಸ್','category'=>'bean','description'=>'Tepary beans, ancient variety. Drought‑tolerant and nutty.','imageurl'=>'images\\Tepary Bean.jpg','weights'=>[['label'=>'250g','price'=>44],['label'=>'500g','price'=>84],['label'=>'1kg','price'=>162],['label'=>'5kg','price'=>780]]],
        ['id'=>81,'name'=>'Jacob\'s Cattle Bean','nameKn'=>'ಜಾಕಬ್‌ಸ್ ಕ್ಯಾಟಲ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Jacob\'s cattle beans, white with red speckles. Good for baking.','imageurl'=>'images\\Jacob\'s Cattle Bean.jpg','weights'=>[['label'=>'250g','price'=>42],['label'=>'500g','price'=>80],['label'=>'1kg','price'=>155],['label'=>'5kg','price'=>750]]],
        ['id'=>82,'name'=>'Appaloosa Bean','nameKn'=>'ಅಪ್ಪಲೂಸಾ ಬೀನ್ಸ್','category'=>'bean','description'=>'Appaloosa beans, white with black spots. Creamy texture.','imageurl'=>'images\\Appaloosa Bean.jpg','weights'=>[['label'=>'250g','price'=>44],['label'=>'500g','price'=>84],['label'=>'1kg','price'=>162],['label'=>'5kg','price'=>780]]],
        ['id'=>83,'name'=>'Mayocoba Bean','nameKn'=>'ಮಯೋಕೋಬಾ ಬೀನ್ಸ್','category'=>'bean','description'=>'Mayocoba beans, creamy yellow. Used in Latin American dishes.','imageurl'=>'images\\Mayocoba Bean.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>76],['label'=>'1kg','price'=>148],['label'=>'5kg','price'=>720]]],
        ['id'=>84,'name'=>'Yellow Eye Bean','nameKn'=>'ಹಳದಿ ಕಣ್ಣಿನ ಬೀನ್ಸ್','category'=>'bean','description'=>'Yellow eye beans, creamy and mild. Used in baked beans.','imageurl'=>'images\\Yellow Eye Bean.jpg','weights'=>[['label'=>'250g','price'=>38],['label'=>'500g','price'=>72],['label'=>'1kg','price'=>140],['label'=>'5kg','price'=>680]]],
        ['id'=>85,'name'=>'Calypso Bean','nameKn'=>'ಕ್ಯಾಲಿಪ್ಸೊ ಬೀನ್ಸ್','category'=>'bean','description'=>'Calypso beans, black and white. Ornamental and edible.','imageurl'=>'images\\Calypso Bean.jpg','weights'=>[['label'=>'250g','price'=>46],['label'=>'500g','price'=>88],['label'=>'1kg','price'=>170],['label'=>'5kg','price'=>820]]],
        ['id'=>86,'name'=>'Tiger Eye Bean','nameKn'=>'ಟೈಗರ್ ಐ ಬೀನ್ಸ್','category'=>'bean','description'=>'Tiger eye beans, tan with dark markings. Nutty flavour.','imageurl'=>'images\\Tiger Eye Bean.jpg','weights'=>[['label'=>'250g','price'=>44],['label'=>'500g','price'=>84],['label'=>'1kg','price'=>162],['label'=>'5kg','price'=>780]]],
        ['id'=>87,'name'=>'Red Bean','nameKn'=>'ಕೆಂಪು ಬೀನ್ಸ್','category'=>'bean','description'=>'Red beans, small and soft. Used in Cuban and Cajun cuisines.','imageurl'=>'images\\Red Bean.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>88,'name'=>'Pink Eye Pea','nameKn'=>'ಗುಲಾಬಿ ಕಣ್ಣಿನ ಬಟಾಣಿ','category'=>'bean','description'=>'Pink eye peas, similar to black-eyed peas. Creamy and mild.','imageurl'=>'images\\Pink Eye Pea.jpg','weights'=>[['label'=>'250g','price'=>32],['label'=>'500g','price'=>60],['label'=>'1kg','price'=>115],['label'=>'5kg','price'=>550]]],
        ['id'=>89,'name'=>'Crowder Pea','nameKn'=>'ಕ್ರೌಡರ್ ಬಟಾಣಿ','category'=>'bean','description'=>'Crowder peas, black-eyed peas variety. Dense and flavourful.','imageurl'=>'images\\Crowder Pea.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>90,'name'=>'Purple Hull Pea','nameKn'=>'ನೇರಳೆ ಚಿಪ್ಪಿನ ಬಟಾಣಿ','category'=>'bean','description'=>'Purple hull peas, similar to black-eyed peas. Sweet and creamy.','imageurl'=>'images\\Purple Hull Pea.jpg','weights'=>[['label'=>'250g','price'=>32],['label'=>'500g','price'=>60],['label'=>'1kg','price'=>115],['label'=>'5kg','price'=>550]]],
        ['id'=>91,'name'=>'Lady Pea','nameKn'=>'ಲೇಡಿ ಬಟಾಣಿ','category'=>'bean','description'=>'Lady peas, small and delicate. Used in Southern US cooking.','imageurl'=>'images\\Lady Pea.jpg','weights'=>[['label'=>'250g','price'=>36],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>132],['label'=>'5kg','price'=>640]]],
        ['id'=>92,'name'=>'Rice Bean','nameKn'=>'ರೈಸ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Rice beans, small and brown. Cook quickly, used in Asia.','imageurl'=>'images\\Rice Bean.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>93,'name'=>'Bambara Groundnut','nameKn'=>'ಬಾಂಬಾರಾ ಕಡಲೆ','category'=>'nut','description'=>'Bambara groundnut, underground pea. High protein, used in Africa.','imageurl'=>'images\\Bambara Groundnut.jpg','weights'=>[['label'=>'250g','price'=>42],['label'=>'500g','price'=>80],['label'=>'1kg','price'=>155],['label'=>'5kg','price'=>750]]],
        ['id'=>94,'name'=>'Winged Bean','nameKn'=>'ರೆಕ್ಕೆಯ ಬೀನ್ಸ್','category'=>'bean','description'=>'Winged beans, all parts edible. Rich in protein.','imageurl'=>'images\\Winged Bean.jpg','weights'=>[['label'=>'250g','price'=>44],['label'=>'500g','price'=>84],['label'=>'1kg','price'=>162],['label'=>'5kg','price'=>780]]],
        ['id'=>95,'name'=>'Velvet Bean','nameKn'=>'ವೆಲ್ವೆಟ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Velvet beans, used as cover crop and medicinal.','imageurl'=>'images\\Velvet Bean.jpg','weights'=>[['label'=>'250g','price'=>38],['label'=>'500g','price'=>72],['label'=>'1kg','price'=>140],['label'=>'5kg','price'=>680]]],
        ['id'=>96,'name'=>'Jack Bean','nameKn'=>'ಜಾಕ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Jack beans, large and tropical. Used in some African cuisines.','imageurl'=>'images\\Jack Bean.jpg','weights'=>[['label'=>'250g','price'=>36],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>132],['label'=>'5kg','price'=>640]]],
        ['id'=>97,'name'=>'Sword Bean','nameKn'=>'ಸೋರ್ಡ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Sword beans, long pods. Used in Asian stir-fries.','imageurl'=>'images\\Sword Bean.jpg','weights'=>[['label'=>'250g','price'=>40],['label'=>'500g','price'=>76],['label'=>'1kg','price'=>148],['label'=>'5kg','price'=>720]]],
        ['id'=>98,'name'=>'Guar Bean','nameKn'=>'ಗ್ವಾರ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Guar beans, source of guar gum. Young pods eaten as vegetable.','imageurl'=>'images\\Guar Bean.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>99,'name'=>'Cluster Bean','nameKn'=>'ಕ್ಲಸ್ಟರ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Cluster beans, also called guar. Used in Indian vegetable dishes.','imageurl'=>'images\\Cluster Bean.jpg','weights'=>[['label'=>'250g','price'=>32],['label'=>'500g','price'=>60],['label'=>'1kg','price'=>115],['label'=>'5kg','price'=>550]]],
        ['id'=>100,'name'=>'Lupin','nameKn'=>'ಲುಪಿನ್','category'=>'bean','description'=>'Lupin beans, high protein and low carb. Used in Mediterranean.','imageurl'=>'images\\Lupin.jpg','weights'=>[['label'=>'250g','price'=>44],['label'=>'500g','price'=>84],['label'=>'1kg','price'=>162],['label'=>'5kg','price'=>780]]],
        ['id'=>101,'name'=>'White Lupin','nameKn'=>'ಬಿಳಿ ಲುಪಿನ್','category'=>'bean','description'=>'White lupin, similar to regular lupin. Mild and creamy.','imageurl'=>'images\\White Lupin.jpg','weights'=>[['label'=>'250g','price'=>46],['label'=>'500g','price'=>88],['label'=>'1kg','price'=>170],['label'=>'5kg','price'=>820]]],
        ['id'=>102,'name'=>'Blue Lupin','nameKn'=>'ನೀಲಿ ಲುಪಿನ್','category'=>'bean','description'=>'Blue lupin, used as forage. Seeds edible after processing.','imageurl'=>'images\\Blue Lupin.jpg','weights'=>[['label'=>'250g','price'=>44],['label'=>'500g','price'=>84],['label'=>'1kg','price'=>162],['label'=>'5kg','price'=>780]]],
        ['id'=>103,'name'=>'Yellow Lupin','nameKn'=>'ಹಳದಿ ಲುಪಿನ್','category'=>'bean','description'=>'Yellow lupin, sweet and nutty. Used in Europe.','imageurl'=>'images\\Yellow Lupin.jpg','weights'=>[['label'=>'250g','price'=>46],['label'=>'500g','price'=>88],['label'=>'1kg','price'=>170],['label'=>'5kg','price'=>820]]],
        ['id'=>104,'name'=>'Andean Lupin','nameKn'=>'ಆಂಡಿಯನ್ ಲುಪಿನ್','category'=>'bean','description'=>'Andean lupin (chocho), used in South American cuisine.','imageurl'=>'images\\Andean Lupin.jpg','weights'=>[['label'=>'250g','price'=>48],['label'=>'500g','price'=>92],['label'=>'1kg','price'=>180],['label'=>'5kg','price'=>870]]],
        ['id'=>105,'name'=>'Marama Bean','nameKn'=>'ಮರಾಮಾ ಬೀನ್ಸ್','category'=>'bean','description'=>'Marama bean, African tuber. Seeds are edible.','imageurl'=>'images\\Marama Bean.jpg','weights'=>[['label'=>'250g','price'=>50],['label'=>'500g','price'=>95],['label'=>'1kg','price'=>185],['label'=>'5kg','price'=>900]]],
        ['id'=>106,'name'=>'Kersting\'s Groundnut','nameKn'=>'ಕರ್ಸ್ಟಿಂಗ್ ನೆಲಗಡಲೆ','category'=>'nut','description'=>'Kersting\'s groundnut, similar to Bambara. Nutty flavour.','imageurl'=>'images\\Kersting\'s Groundnut.jpg','weights'=>[['label'=>'250g','price'=>44],['label'=>'500g','price'=>84],['label'=>'1kg','price'=>162],['label'=>'5kg','price'=>780]]],
        ['id'=>107,'name'=>'Earth Pea','nameKn'=>'ಭೂಮಿ ಬಟಾಣಿ','category'=>'pea','description'=>'Earth pea, small underground pea. Used in some traditional dishes.','imageurl'=>'images\\Earth Pea.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>108,'name'=>'Fenugreek Seed','nameKn'=>'ಮೆಂತ್ಯ ಕಾಳು','category'=>'seed','description'=>'Fenugreek seeds, aromatic and bitter. Used as spice.','imageurl'=>'images\\Fenugreek Seed.jpg','weights'=>[['label'=>'100g','price'=>25],['label'=>'250g','price'=>55],['label'=>'500g','price'=>100],['label'=>'1kg','price'=>190]]],
        ['id'=>109,'name'=>'Tamarind Seed','nameKn'=>'ಹುಣಸೆ ಕಾಳು','category'=>'seed','description'=>'Tamarind seeds, used in traditional medicine and as thickener.','imageurl'=>'images\\Tamarind Seed.jpg','weights'=>[['label'=>'100g','price'=>20],['label'=>'250g','price'=>45],['label'=>'500g','price'=>85],['label'=>'1kg','price'=>160]]],
        ['id'=>110,'name'=>'Peanut','nameKn'=>'ಕಡಲೆಕಾಯಿ','category'=>'nut','description'=>'Peanuts (groundnuts), rich in oil and protein. Used in snacks and cooking.','imageurl'=>'images\\Peanut.jpg','weights'=>[['label'=>'250g','price'=>55],['label'=>'500g','price'=>105],['label'=>'1kg','price'=>200],['label'=>'5kg','price'=>980]]],
        ['id'=>111,'name'=>'Val Bean','nameKn'=>'ವಾಲ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Val beans (horse gram), also called kulthi. Used in South India.','imageurl'=>'images\\Val Bean.jpg','weights'=>[['label'=>'250g','price'=>30],['label'=>'500g','price'=>58],['label'=>'1kg','price'=>110],['label'=>'5kg','price'=>530]]],
        ['id'=>112,'name'=>'Sem Bean','nameKn'=>'ಸೆಮ್ ಬೀನ್ಸ್','category'=>'bean','description'=>'Sem beans, also known as hyacinth beans. Used in curries.','imageurl'=>'images\\Sem Bean.jpg','weights'=>[['label'=>'250g','price'=>32],['label'=>'500g','price'=>60],['label'=>'1kg','price'=>115],['label'=>'5kg','price'=>550]]],
        ['id'=>113,'name'=>'Avare Bean','nameKn'=>'ಅವರೆ ಕಾಳು','category'=>'bean','description'=>'Avare beans, used in Karnataka cuisine. Fresh or dried.','imageurl'=>'images\\Avare Bean.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>114,'name'=>'Safed Vatana','nameKn'=>'ಸಫೇದ್ ವಟಾಣಾ','category'=>'pea','description'=>'White peas, used in North Indian chaat and curries.','imageurl'=>'images\\Safed Vatana.jpg','weights'=>[['label'=>'250g','price'=>28],['label'=>'500g','price'=>55],['label'=>'1kg','price'=>105],['label'=>'5kg','price'=>500]]],
        ['id'=>115,'name'=>'Hara Vatana','nameKn'=>'ಹರಾ ವಟಾಣಾ','category'=>'pea','description'=>'Green peas, fresh or dried. Used in Indian cuisine.','imageurl'=>'images\\Hara Vatana.jpg','weights'=>[['label'=>'250g','price'=>35],['label'=>'500g','price'=>68],['label'=>'1kg','price'=>130],['label'=>'5kg','price'=>640]]],
        ['id'=>116,'name'=>'Brown Chickpea','nameKn'=>'ಕಂದು ಕಡಲೆ','category'=>'chana','description'=>'Brown chickpeas, similar to kala chana. Rich in fibre.','imageurl'=>'images\\chickpeas-kabuli-chana.jpg','weights'=>[['label'=>'250g','price'=>34],['label'=>'500g','price'=>65],['label'=>'1kg','price'=>125],['label'=>'5kg','price'=>600]]],
        ['id'=>117,'name'=>'Organic Green Gram','nameKn'=>'ಸಾವಯವ ಹೆಸರು ಕಾಳು','category'=>'moong','description'=>'Certified organic whole green gram. Chemical‑free.','imageurl'=>'images\\Organic Green Gram.jpg','weights'=>[['label'=>'250g','price'=>50],['label'=>'500g','price'=>95],['label'=>'1kg','price'=>185],['label'=>'5kg','price'=>900]]],
        ['id'=>118,'name'=>'Organic Red Lentil','nameKn'=>'ಸಾವಯವ ಕೆಂಪು ಮಸೂರ','category'=>'masoor','description'=>'Certified organic red lentil. Naturally grown.','imageurl'=>'images\\Red Lentil.jpg','weights'=>[['label'=>'250g','price'=>45],['label'=>'500g','price'=>85],['label'=>'1kg','price'=>165],['label'=>'5kg','price'=>800]]]
    ];
    // Assign the static data to productsData
    $productsData = $staticProducts;
}
// Define top pick IDs (first 6 by default)
$topPickIds = array_slice(array_column($productsData, 'id'), 0, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes" />
    <title>Pulses & Dal · Heritage Grains Market</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <!-- Google Font Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet" />
    <style>
        /* ===== SAME CSS AS STATIC VERSION ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { display: block; }
        #app { max-width: 1440px; width: 100%; background: #ffffff; border-radius: 44px; box-shadow: 0 24px 60px rgba(0,0,0,0.06); padding: 32px 36px 44px; transition: padding 0.2s; }
        .header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 18px; margin-bottom: 28px; }
        .shop-name h1 { font-size: 2.2rem; font-weight: 800; color: #2c1f14; letter-spacing: -0.02em; }
        .shop-name .kannada { font-size: 1.1rem; font-weight: 500; color: #b45f2b; margin-left: 4px; }
        .cart-link { background: #f5ede6; padding: 12px 28px; border-radius: 60px; display: inline-flex; align-items: center; gap: 12px; transition: background 0.2s, transform 0.15s; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #ede3d9; text-decoration: none; color: inherit; font-weight: 600; }
        .cart-link:hover { background: #ece0d4; transform: scale(1.02); }
        .cart-link i { font-size: 1.6rem; color: #5c3e24; }
        .cart-link .cart-badge { background: #b45f2b; color: #fff; font-weight: 700; font-size: 1rem; padding: 2px 14px; border-radius: 40px; min-width: 32px; text-align: center; margin-left: 4px; }
        .secondary-nav { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 28px; }
        .secondary-nav a { background: #f5ede6; padding: 10px 22px; border-radius: 50px; font-size: 0.9rem; font-weight: 600; color: #4f3a2a; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s, color 0.2s; border: 1px solid transparent; }
        .secondary-nav a:hover { background: #e8d8cb; color: #2c1f14; }
        .secondary-nav a i { font-size: 1rem; color: #b45f2b; }
        .search-section { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; margin-bottom: 32px; }
        .search-box { flex: 2; min-width: 220px; display: flex; align-items: center; background: #f5ede6; border-radius: 60px; padding: 4px 24px; border: 1px solid #e6d9cd; transition: border 0.2s, box-shadow 0.2s; }
        .search-box:focus-within { border-color: #b45f2b; box-shadow: 0 0 0 3px rgba(180,95,43,0.15); }
        .search-box i { color: #b45f2b; font-size: 1.2rem; margin-right: 14px; }
        .search-box input { flex: 1; border: none; background: transparent; padding: 14px 0; font-size: 1rem; outline: none; color: #2c1f14; font-weight: 500; }
        .search-box input::placeholder { color: #a48d78; font-weight: 400; }
        .filter-pills { display: flex; flex-wrap: wrap; gap: 10px; }
        .pill { background: #f5ede6; padding: 10px 24px; border-radius: 60px; font-size: 0.95rem; font-weight: 600; color: #4f3a2a; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; user-select: none; }
        .pill.active { background: #2c1f14; color: #fff; border-color: #2c1f14; box-shadow: 0 4px 12px rgba(44,31,20,0.20); }
        .pill:hover:not(.active) { background: #e8d8cb; }
        .top-picks { background: linear-gradient(135deg, #fff6ed 0%, #fee9dd 100%); border-radius: 36px; padding: 28px 30px 34px; margin-bottom: 40px; border: 1px solid #fadbc8; }
        .top-picks h3 { font-size: 1.8rem; font-weight: 700; color: #2c1f14; margin-bottom: 22px; display: flex; align-items: center; gap: 14px; }
        .top-picks h3 i { color: #e67e22; font-size: 2rem; }
        .top-picks-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 24px; }
        .top-pick-card { background: #fff; border-radius: 28px; padding: 18px 16px 20px; text-align: center; box-shadow: 0 6px 18px rgba(0,0,0,0.04); transition: transform 0.2s, box-shadow 0.2s; border: 1px solid #f0e3d7; }
        .top-pick-card:hover { transform: translateY(-6px); box-shadow: 0 14px 28px rgba(0,0,0,0.08); }
        .top-pick-img { width: 100%; aspect-ratio: 1/1; border-radius: 24px; object-fit: cover; background: #fcf4ec; margin-bottom: 12px; border: 2px solid #f5ede6; }
        .top-pick-name { font-weight: 700; font-size: 1rem; color: #2c1f14; line-height: 1.3; }
        .top-pick-price { font-size: 1.15rem; font-weight: 800; color: #b45f2b; margin: 6px 0 12px; }
        .top-pick-btn { background: #b45f2b; border: none; color: #fff; width: 48px; height: 48px; border-radius: 50%; font-size: 1.2rem; cursor: pointer; transition: background 0.15s, transform 0.1s; box-shadow: 0 4px 12px rgba(180,95,43,0.25); }
        .top-pick-btn:hover { background: #8f4c22; }
        .top-pick-btn:active { transform: scale(0.92); }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin-top: 16px; margin-bottom: 48px; }
        .product-card { background: #ffffff; border-radius: 32px; padding: 22px 20px 26px; box-shadow: 0 6px 20px rgba(0,0,0,0.03); border: 1px solid #f0e3d7; transition: transform 0.25s cubic-bezier(0.2,0,0,1), box-shadow 0.3s; display: flex; flex-direction: column; align-items: center; text-align: center; }
        .product-card:hover { transform: translateY(-8px); box-shadow: 0 24px 40px rgba(0,0,0,0.07); border-color: #e0cebe; }
        .product-image { width: 160px; height: 160px; border-radius: 50%; overflow: hidden; margin: 0 auto 18px; background: #fcf4ec; border: 3px solid #f0e3d7; flex-shrink: 0; transition: border-color 0.2s, transform 0.3s; }
        .product-card:hover .product-image { border-color: #dac0ae; transform: scale(1.02); }
        .product-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
        .product-card:hover .product-image img { transform: scale(1.06); }
        .product-name { font-weight: 700; font-size: 1.2rem; color: #2c1f14; line-height: 1.3; margin-bottom: 2px; }
        .product-name-kn { font-size: 0.85rem; color: #7b5f45; margin-bottom: 8px; font-weight: 500; }
        .product-description { font-size: 0.85rem; color: #6e5540; margin: 6px 0 14px; line-height: 1.4; background: #faf4ee; padding: 6px 16px; border-radius: 40px; display: inline-block; font-weight: 500; }
        .weight-select { width: 100%; max-width: 200px; background: #faf4ee; border: 1px solid #e6d9cd; border-radius: 50px; padding: 10px 16px; font-size: 0.9rem; font-weight: 600; color: #2c1f14; cursor: pointer; margin: 8px 0 6px; text-align: center; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23b45f2b' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; background-size: 14px; }
        .weight-select:focus { border-color: #b45f2b; outline: none; box-shadow: 0 0 0 3px rgba(180,95,43,0.12); }
        .dynamic-price { font-weight: 800; font-size: 1.7rem; color: #b45f2b; margin: 6px 0 10px; letter-spacing: -0.5px; }
        .add-btn { background: #b45f2b; border: none; color: #fff; padding: 14px 28px; border-radius: 60px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background 0.2s, transform 0.1s; display: inline-flex; align-items: center; gap: 12px; box-shadow: 0 6px 16px rgba(180,95,43,0.25); width: 100%; justify-content: center; }
        .add-btn i { font-size: 1.1rem; }
        .add-btn:hover { background: #8f4c22; }
        .add-btn:active { transform: scale(0.96); }
        footer { text-align: center; font-size: 0.85rem; color: #b9a690; margin-top: 16px; padding-top: 18px; border-top: 1px solid #f0e3d7; font-weight: 500; }
        @media (max-width: 768px) { #app { padding: 20px 16px 32px; border-radius: 28px; } .header { flex-direction: column; align-items: stretch; gap: 14px; } .shop-name h1 { font-size: 1.7rem; } .cart-link { align-self: flex-start; padding: 10px 20px; } .secondary-nav { gap: 8px; } .secondary-nav a { font-size: 0.8rem; padding: 8px 16px; } .search-section { flex-direction: column; align-items: stretch; } .filter-pills { justify-content: center; } .pill { font-size: 0.85rem; padding: 8px 18px; } .top-picks { padding: 20px 18px 26px; } .top-picks h3 { font-size: 1.4rem; } .top-picks-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 16px; } .product-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; } .product-image { width: 130px; height: 130px; } }
        @media (max-width: 480px) { .product-grid { grid-template-columns: 1fr 1fr; gap: 14px; } .product-card { padding: 16px 12px 20px; border-radius: 24px; } .product-image { width: 100px; height: 100px; } .product-name { font-size: 0.95rem; } .dynamic-price { font-size: 1.3rem; } .add-btn { font-size: 0.85rem; padding: 12px 16px; } .top-picks-grid { grid-template-columns: 1fr 1fr; } .top-pick-img { aspect-ratio: 1/1; } }
    </style>
  <link rel="stylesheet" href="category-style.css">
    <link rel="stylesheet" href="../index.css">
</head>
<body>
    <div id="app">
        <!-- HEADER -->
          <?php
  $base_url = '../';
  include __DIR__ . '/../includes/global-header.php';
?>
        <!-- SEARCH & FILTERS -->
        <div class="search-section">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" />
            </div>
            <div class="filter-pills" id="filterPills">
                <div class="pill active" data-category="all">🌾 All / ಎಲ್ಲ</div>
                <div class="pill" data-category="toor">Toor Dal / ತೊವ್ವೆ ಬೇಳೆ</div>
                <div class="pill" data-category="moong">Moong Dal / ಹೆಸರು ಬೇಳೆ</div>
                <div class="pill" data-category="urad">Urad Dal / ಉದ್ದಿನ ಬೇಳೆ</div>
                <div class="pill" data-category="masoor">Masoor Dal / ಮಸೂರ ಬೇಳೆ</div>
                <div class="pill" data-category="chana">Chana / ಕಡಲೆ</div>
                <div class="pill" data-category="rajma">Rajma / ರಾಜ್ಮಾ</div>
                <div class="pill" data-category="beans">Beans / ಬೀನ್ಸ್</div>
            </div>
        </div>
        <!-- TOP PICKS -->
        <div class="top-picks">
            <h3><i class="fas fa-star"></i> Our Top Picks · ಸಿರಿಧಾನ್ಯ ವಿಶೇಷ</h3>
            <div class="top-picks-grid" id="topPicksGrid"></div>
        </div>
        <!-- PRODUCT GRID -->
        <div class="product-grid" id="productGrid"></div>
            </div>
    <script>
        // ============================================================
        // JAVASCRIPT – SESSION-BASED CART VIA AJAX + LOCALSTORAGE FALLBACK
        // ============================================================
        (function() {
            // ----- PRODUCT DATA FROM PHP (embedded) -----
            const productDatabase = <?php echo json_encode($productsData); ?>;
  // Normalize product images
  if (typeof productDatabase !== 'undefined' && Array.isArray(productDatabase)) {
    productDatabase.forEach(p => {
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
            // ----- TOP PICK IDs (from PHP) -----
            const topPickIds = <?php echo json_encode($topPickIds); ?>;
            // ----- CART STATE (localStorage, but we also sync via AJAX) -----
            let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
            let activeCategory = 'all';
            let searchTerm = '';
            // ----- DOM REFS -----
            const productGrid = document.getElementById('productGrid');
            const topPicksGrid = document.getElementById('topPicksGrid');
            const cartCountSpan = document.getElementById('cartCount');
            const searchInput = document.getElementById('searchInput');
            const filterPills = document.querySelectorAll('.pill');
            // ----- UPDATE CART BADGE -----
            function updateCartBadge() {
                const total = cart.reduce((sum, i) => sum + (i.qty || 0), 0);
                cartCountSpan.innerText = total;
                localStorage.setItem('glowCartNew', JSON.stringify(cart));
            }
            // ----- ADD TO CART (local + server sync) -----
            function addToCart(productId, weightLabel, price, productName) {
    const product = productDatabase.find(p => p.id === productId);
    const imageUrl = product ? (product.imageurl || '') : '';
    const knName = product ? (product.kn_name || '') : '';
    const formData = new FormData();
    formData.append('id', productId);
    formData.append('qty', 1);
    formData.append('variant', weightLabel);
    formData.append('custom_price', price);
    formData.append('name', productName);
    formData.append('kn', knName);
    formData.append('image', imageUrl);
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
            // ----- RENDER PRODUCTS -----
            function renderProducts() {
                let filtered = productDatabase.filter(p => {
                    if (activeCategory !== 'all' && p.category !== activeCategory) return false;
                    if (searchTerm.trim()) {
                        const term = searchTerm.trim().toLowerCase();
                        return p.name.toLowerCase().includes(term) || p.nameKn.includes(term);
                    }
                    return true;
                });
                if (!filtered.length) {
                    productGrid.innerHTML =
                        `<div style="grid-column:1/-1; text-align:center; padding:48px 20px; color:#a48d78;">🌾 No pulses match your search</div>`;
                    return;
                }
                productGrid.innerHTML = filtered.map(p => {
                    const weightOpts = p.weights.map((w, i) =>
                        `<option value="${w.price}" data-label="${w.label}">${w.label} — ₹${w.price}</option>`
                    ).join('');
                    return `
                    <div class="product-card" data-id="${p.id}">
                      <div class="product-image"><img src="${p.imageurl}" alt="" loading="lazy" onerror="this.src='https://placehold.co/160x160/f8f4f0/b45f2b?text=🌾'" /></div>
                      <div class="product-name">${p.name}</div>
                      ${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}
                      <div class="product-description">✨ ${p.description}</div>
                      ${p.weights && p.weights.length > 1 
                        ? `<select class="weight-select" data-id="${p.id}">
                            ${weightOpts}
                           </select>`
                        : `<div class="variant-text" style="font-size:0.85rem; font-weight:600; color:#4f3e2e; text-align:center; padding:10px; margin:8px 0 6px; width:100%; max-width:200px; background:#faf4ee; border-radius:50px; border:1px solid #e6d9cd;">${p.weights && p.weights[0] ? p.weights[0].label : '1 unit'}</div>`
                      }
                      <div class="dynamic-price" data-price-display="${p.id}">₹${p.weights[0].price}</div>
                      <button class="add-btn" data-id="${p.id}">
                        <i class="fa-solid fa-cart-plus"></i> Add to Cart
                      </button>
                    </div>
                  `;
                }).join('');
                // weight change → update price
                document.querySelectorAll('.weight-select').forEach(sel => {
                    sel.addEventListener('change', function(e) {
                        const card = this.closest('.product-card');
                        const priceDisplay = card.querySelector('.dynamic-price');
                        priceDisplay.innerText = `₹${this.value}`;
                    });
                });
                // add to cart
                document.querySelectorAll('.add-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        const card = this.closest('.product-card');
                        const select = card.querySelector('.weight-select');
                        const pid = parseInt(this.dataset.id);
                        const product = productDatabase.find(p => p.id === pid);
                        if (!product) return;
                        
                        let weightLabel = '';
                        let price = 0;
                        if (select) {
                            const opt = select.options[select.selectedIndex];
                            weightLabel = opt.dataset.label;
                            price = parseInt(select.value);
                        } else if (product.weights && product.weights.length > 0) {
                            weightLabel = product.weights[0].label;
                            price = parseInt(product.weights[0].price);
                        } else {
                            weightLabel = '1 unit';
                            price = parseInt(document.querySelector(`[data-price-display="${pid}"]`).innerText.replace('₹', ''));
                        }
                        addToCart(pid, weightLabel, price, product.name);
                    });
                });
            }
            // ----- RENDER TOP PICKS -----
            function renderTopPicks() {
                const top = productDatabase.filter(p => topPickIds.includes(p.id));
                if (!top.length) return;
                topPicksGrid.innerHTML = top.map(p => {
                    const defaultPrice = p.weights[0].price;
                    const defaultLabel = p.weights[0].label;
                    return `
                    <div class="top-pick-card">
                      <img class="top-pick-img" src="${p.imageurl}" alt="" loading="lazy" onerror="this.src='https://placehold.co/200/f8f4f0/b45f2b?text=🌾'" />
                      <div class="top-pick-name">${p.name}</div>
                      <div class="top-pick-price">₹${defaultPrice}</div>
                      <button class="top-pick-btn" data-id="${p.id}" data-weight="${defaultLabel}" data-price="${defaultPrice}">
                        <i class="fas fa-shopping-cart"></i>
                      </button>
                    </div>
                  `;
                }).join('');
                document.querySelectorAll('.top-pick-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const pid = parseInt(this.dataset.id);
                        const wLabel = this.dataset.weight;
                        const price = parseInt(this.dataset.price);
                        const product = productDatabase.find(p => p.id === pid);
                        if (!product) return;
                        addToCart(pid, wLabel, price, product.name);
                        this.style.transform = 'scale(0.9)';
                        setTimeout(() => this.style.transform = '', 140);
                    });
                });
            }
            // ----- FILTERS & SEARCH -----
            filterPills.forEach(pill => {
                pill.addEventListener('click', function() {
                    filterPills.forEach(p => p.classList.remove('active'));
                    this.classList.add('active');
                    activeCategory = this.dataset.category;
                    renderProducts();
                });
            });
            searchInput.addEventListener('input', function(e) {
                searchTerm = e.target.value;
                renderProducts();
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


  <?php
  $base_url = '../';
  $currentCategory = 'pulses';
  include __DIR__ . '/../includes/related-section.php';
  ?>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
<script src="sorting.js"></script>
</body>
</html>
