<?php
// ============================================================
// pooja-items.php - Dynamic Pooja / Sacred Items page
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
$categorySlug = 'pooja-items';
$poojaCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($poojaCategory) {
  // Fetch products from this category
  $dbProducts = getProductsByCategory($poojaCategory['id']);
  if (!empty($dbProducts)) {
    foreach ($dbProducts as $p) {
      $description = $p['description_en'] ?? ($p['weight'] ?? 'Sacred item');
      $productsData[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'kn' => $p['kn_name'] ?? $p['name'] . ' (ಪೂಜಾ ವಸ್ತು)',
        'category' => $p['category_slug'] ?? 'other',
        'price' => $p['price'],
        'imageUrl' => $p['image_url'] ?? '',
        'description' => $description,
        'weight' => $p['weight'] ?? ''
      ];
    }
  }
}
// Fallback to static data if no DB products found
if (empty($productsData)) {
  // ----- STATIC DATA (original dataset) -----
  $rawPoojaItems = [
    ['id' => 1, 'name' => 'Agarbatti Sandalwood', 'imageUrl' => 'images\\Agarbatti Sandalwood.jpg', 'weight' => '1 pack', 'price' => 40],
    ['id' => 2, 'name' => 'Agarbatti Rose', 'imageUrl' => 'images\\Agarbatti Rose.jpg', 'weight' => '1 pack', 'price' => 35],
    ['id' => 3, 'name' => 'Agarbatti Jasmine', 'imageUrl' => 'images\\Agarbatti Jasmine 248.jpg', 'weight' => '1 pack', 'price' => 248],
    ['id' => 4, 'name' => 'Agarbatti Mogra', 'imageUrl' => 'images\\Agarbatti Mogra 120g 160-248.jpg', 'weight' => '1 pack', 'price' => 248],
    ['id' => 5, 'name' => 'Agarbatti Lavender', 'imageUrl' => 'images\\Agarbatti Lavender  140.jpg', 'weight' => '1 pack', 'price' => 140],
    ['id' => 6, 'name' => 'Dhoop Sticks', 'imageUrl' => 'images\\Dhoop Sticks ronak 175 (10).jpg', 'weight' => '1 pack', 'price' => 175],
    ['id' => 7, 'name' => 'Sambrani Cups', 'imageUrl' => 'images\\Cycle Pushkarini Sambrani Cups 12 89-95.jpg', 'weight' => '12 pcs', 'price' => 95],
    ['id' => 8, 'name' => 'Camphor Tablets', 'imageUrl' => 'images\\Pure Camphor Tablets 300.jpg', 'weight' => '100 g', 'price' => 300],
    ['id' => 9, 'name' => 'Camphor Premium', 'imageUrl' => 'images\\Vedanum 100% Pure Camphor  100.jpg', 'weight' => '200 g', 'price' => 140],
    ['id' => 10, 'name' => 'Cotton Wicks', 'imageUrl' => 'images\\Keli Economy Cotton Wicks 25.jpg', 'weight' => '100 pcs', 'price' => 30],
    ['id' => 11, 'name' => 'Cotton Wicks Large', 'imageUrl' => 'images\\Pooja Samagri Cotton Wicks 160.jpg', 'weight' => '200 pcs', 'price' => 160],
    ['id' => 12, 'name' => 'Pooja Cotton', 'imageUrl' => 'images\\Pooja Cotton  big ramakanthi 50.jpg', 'weight' => '100 g', 'price' => 40],
    ['id' => 13, 'name' => 'Ghee for Diya', 'imageUrl' => 'images\\Swarajya India Pooja Ghee For Diya & Lamps 125.jpg', 'weight' => '100 ml', 'price' => 130],
    ['id' => 14, 'name' => 'Cow Ghee Pooja', 'imageUrl' => 'images\\Ogu Cow Ghee Diya Batti For Puja Handmade Pooja Jyot Batti For Aarti  60-500.jpg', 'weight' => '200 ml', 'price' => 120],
    ['id' => 15, 'name' => 'Kumkum Powder', 'imageUrl' => 'images\\Shubhkart Darshana Kumkum Powder 20.jpg', 'weight' => '50 g', 'price' => 30],
    ['id' => 16, 'name' => 'Turmeric Powder Pooja', 'imageUrl' => 'images\\Turmeric Powder Pooja.jpg', 'weight' => '50 g', 'price' => 125],
    ['id' => 17, 'name' => 'Chandan Powder', 'imageUrl' => 'images\\Chandan Powder.jpg', 'weight' => '50 g', 'price' => 80],
    ['id' => 18, 'name' => 'Chandan Stick', 'imageUrl' => 'images\\giri Chandan Pure Mysore Sandal Incense Sticks 280.jpg', 'weight' => '1 pc', 'price' => 280],
    ['id' => 19, 'name' => 'Sindoor Powder', 'imageUrl' => 'images\\Brundavanam Organic Kumkum Sindoor Powder   300.jpg', 'weight' => '50 g', 'price' => 300],
    ['id' => 20, 'name' => 'Haldi Kumkum Set', 'imageUrl' => 'images\\Haldi Kumkum.jpg', 'weight' => '1 set', 'price' => 70],
    ['id' => 21, 'name' => 'Pooja Bell Brass', 'imageUrl' => 'images\\Dattatreya Brass Pooja Bell with Hanuman and Garun 2550.jpg', 'weight' => '1 pc', 'price' => 2550],
    ['id' => 22, 'name' => 'Pooja Thali Steel', 'imageUrl' => 'images\\Wish Divine Stainless Steel Pooja Thali Set 250.jpg', 'weight' => '1 pc', 'price' => 250],
    ['id' => 23, 'name' => 'Pooja Thali Brass', 'imageUrl' => 'images\\Wish Divine Stainless Steel Pooja Thali Set 250.jpg', 'weight' => '1 pc', 'price' => 290],
    ['id' => 24, 'name' => 'Diya Brass', 'imageUrl' => 'images\\Decowill Brass Kamakshi Diya Pure Brass 280.jpg', 'weight' => '1 pc', 'price' => 3000],
    ['id' => 25, 'name' => 'Diya Clay', 'imageUrl' => 'images\\Handmade Terracotta Clay Diyas 400.jpg', 'weight' => '6 pcs', 'price' => 800],
    ['id' => 26, 'name' => 'Decorative Diya', 'imageUrl' => 'images\\Traditional Brass Panch Aarti Diya with Wooden Handle, Temple Oil Lamp, Decorative Pooja Stand for Religious Ceremonies 400.jpg', 'weight' => '2 pcs', 'price' => 400],
    ['id' => 27, 'name' => 'Pancharti Diya', 'imageUrl' => 'images\\Brass Pancharti Diya 3000.jpg', 'weight' => '1 pc', 'price' => 3000],
    ['id' => 28, 'name' => 'Oil Lamp Stand', 'imageUrl' => 'images\\Handcrafted Brass Standing Oil Lamp 2800.jpg', 'weight' => '1 pc', 'price' => 2880],
    ['id' => 29, 'name' => 'Pooja Kalash Copper', 'imageUrl' => 'images\\Pure Copper Pooja Kalash 900.jpg', 'weight' => '1 pc', 'price' => 900],
    ['id' => 30, 'name' => 'Pooja Kalash Brass', 'imageUrl' => 'images\\Esplanade Brass Kalash for Pooja 350.jpg', 'weight' => '1 pc', 'price' => 350],
    ['id' => 31, 'name' => 'Coconut for Pooja', 'imageUrl' => 'images\\coconut-for-pooja.jpg', 'weight' => '1 pc', 'price' => 45],
    ['id' => 32, 'name' => 'Betel Leaves Pack', 'imageUrl' => 'images\\Betel Leaf 10.jpg', 'weight' => '10 pcs', 'price' => 10],
    ['id' => 33, 'name' => 'Betel Nuts', 'imageUrl' => 'images\\Betel Nuts for Pooja by Sohum 80.jpg', 'weight' => '100 g', 'price' => 90],
    ['id' => 34, 'name' => 'Dry Coconut', 'imageUrl' => 'images\\Dry Coconut 190.jpg', 'weight' => '1 pc', 'price' => 190],
    ['id' => 35, 'name' => 'Pooja Rice Akshata', 'imageUrl' => 'images\\Pooja Rice Akshata.jpg', 'weight' => '200 g', 'price' => 40],
    ['id' => 36, 'name' => 'Sacred Thread', 'imageUrl' => 'images\\10 Pcs White Janeu Sacred Thread 100.jpg', 'weight' => '1 roll', 'price' => 30],
    ['id' => 37, 'name' => 'Janeu Thread', 'imageUrl' => 'images\\10 Pcs White Janeu Sacred Thread 100.jpg', 'weight' => '1 pack', 'price' => 40],
    ['id' => 38, 'name' => 'Rudraksha Beads', 'imageUrl' => 'images\\Natural 5 Mukhi Rudraksha Mala (108 + 1 Beads) 450.jpg', 'weight' => '1 pc', 'price' => 480],
    ['id' => 39, 'name' => 'Tulsi Mala', 'imageUrl' => 'images\\108 Beads Original Tulsi Japa Mala 250.jpg', 'weight' => '1 pc', 'price' => 260],
    ['id' => 40, 'name' => 'Rudraksha Mala', 'imageUrl' => 'images\\Natural 5 Mukhi Rudraksha Mala (108 + 1 Beads) 450.jpg', 'weight' => '1 pc', 'price' => 350],
    ['id' => 41, 'name' => 'Pooja Mat', 'imageUrl' => 'images\\Pooja Mat 350.jpg', 'weight' => '1 pc', 'price' => 360],
    ['id' => 42, 'name' => 'Pooja Asan Cloth', 'imageUrl' => 'images\\Creative Space Red Velvet Pooja Cloth Aasan For Mandir With Golden Border  320.jpg', 'weight' => '1 pc', 'price' => 350],
    ['id' => 43, 'name' => 'Camphor Stand', 'imageUrl' => 'images\\TRISHVAR Stainless Steel Camphor Stand 150.jpg', 'weight' => '1 pc', 'price' => 190],
    ['id' => 44, 'name' => 'Incense Holder', 'imageUrl' => 'images\\Incense Holder.jpg', 'weight' => '1 pc', 'price' => 80],
    ['id' => 45, 'name' => 'Pooja Spoon Brass', 'imageUrl' => 'images\\Divine Sutra Brass Pooja Spoon 3.5 Inch 100.jpg', 'weight' => '1 pc', 'price' => 110],
    ['id' => 46, 'name' => 'Panchpatra Set', 'imageUrl' => 'images\\BHAKTI SHRINGAR Panchpatra Set with Achamani & Thali 600.jpg', 'weight' => '1 set', 'price' => 250],
    ['id' => 47, 'name' => 'Copper Lota', 'imageUrl' => 'images\\PrettyKrafts Copper Lota 450.jpg', 'weight' => '1 pc', 'price' => 280],
    ['id' => 48, 'name' => 'Gangajal Bottle', 'imageUrl' => 'images\\Gangajal Bottle.jpg', 'weight' => '250 ml', 'price' => 50],
    ['id' => 49, 'name' => 'Pooja Book', 'imageUrl' => 'images\\Pooja Book.jpg', 'weight' => '1 pc', 'price' => 120],
    ['id' => 50, 'name' => 'Pooja Samagri Kit', 'imageUrl' => 'images\\Pooja Samagri Kit for Daily Puja and Festivals  700.jpg', 'weight' => '1 set', 'price' => 750],
    ['id' => 51, 'name' => 'Lakshmi Idol Small', 'imageUrl' => 'images\\Dharma Statues Brass Lakshmi Fine 950.jpg', 'weight' => '1 pc', 'price' => 980],
    ['id' => 52, 'name' => 'Ganesh Idol Small', 'imageUrl' => 'images\\Eco-Friendly Clay Ganesha Murti for Home Decor 600.jpg', 'weight' => '1 pc', 'price' => 680],
    ['id' => 53, 'name' => 'Shiva Idol', 'imageUrl' => 'images\\Ekhasa 100% Pure Brass Shivling Decorative Showpiece 808.jpg', 'weight' => '1 pc', 'price' => 820],
    ['id' => 54, 'name' => 'Durga Idol', 'imageUrl' => 'images\\Ekhasa Durga Maa Idol 1200.jpg', 'weight' => '1 pc', 'price' => 1250],
    ['id' => 55, 'name' => 'Hanuman Idol', 'imageUrl' => 'images\\Blessing Hanuman Idol 350.jpg', 'weight' => '1 pc', 'price' => 380],
    ['id' => 56, 'name' => 'Krishna Idol', 'imageUrl' => 'images\\Svastika Krishna Idol for Car Dashboard 2500.jpg', 'weight' => '1 pc', 'price' => 2500],
    ['id' => 57, 'name' => 'Radha Krishna Idol', 'imageUrl' => 'images\\Radha Krishna Murti  460.jpg', 'weight' => '1 pc', 'price' => 450],
    ['id' => 58, 'name' => 'Shivling Stone', 'imageUrl' => 'images\\A & S VENTURES Tilak Marble Shivling Stone Shivlinga 280.jpg', 'weight' => '1 pc', 'price' => 280],
    ['id' => 59, 'name' => 'Shivling Brass', 'imageUrl' => 'images\\Premium Brass Miniature Shiv 1200.jpg', 'weight' => '1 pc', 'price' => 1320],
    ['id' => 60, 'name' => 'Pooja Garland Artificial', 'imageUrl' => 'images\\Pooja Garland Artificial.jpg', 'weight' => '1 pc', 'price' => 170],
    ['id' => 61, 'name' => 'Fresh Flower Garland', 'imageUrl' => 'images\\Fresh Flower Garland.jpg', 'weight' => '1 pc', 'price' => 280],
    ['id' => 62, 'name' => 'Lotus Flower', 'imageUrl' => 'images\\Lotus Flower.jpg', 'weight' => '1 pc', 'price' => 125],
    ['id' => 63, 'name' => 'Marigold Flower Pack', 'imageUrl' => 'images\\Marigold Flower Pack.jpg', 'weight' => '200 g', 'price' => 140],
    ['id' => 64, 'name' => 'Rose Flower Pack', 'imageUrl' => 'images\\Rose Flower Pack.jpg', 'weight' => '200 g', 'price' => 250],
    ['id' => 65, 'name' => 'Jasmine Flower Pack', 'imageUrl' => 'images\\Jasmine Flower Pack.jpg', 'weight' => '100 g', 'price' => 270],
    ['id' => 66, 'name' => 'Incense Cone', 'imageUrl' => 'images\\Incense Cone.jpg', 'weight' => '1 pack', 'price' => 345],
    ['id' => 67, 'name' => 'Sambrani Powder', 'imageUrl' => 'images\\Sambrani Powder.jpg', 'weight' => '100 g', 'price' => 180],
    ['id' => 68, 'name' => 'Temple Oil', 'imageUrl' => 'images\\Temple Oil.jpg', 'weight' => '200 ml', 'price' => 90],
    ['id' => 69, 'name' => 'Sesame Oil Pooja', 'imageUrl' => 'images\\Sesame Oil Pooja.jpg', 'weight' => '200 ml', 'price' => 120],
    ['id' => 70, 'name' => 'Mustard Oil Pooja', 'imageUrl' => 'images\\Mustard Oil Pooja.jpg', 'weight' => '200 ml', 'price' => 110],
    ['id' => 71, 'name' => 'Copper Plate', 'imageUrl' => 'images\\Copper Plate.jpg', 'weight' => '1 pc', 'price' => 250],
    ['id' => 72, 'name' => 'Brass Plate', 'imageUrl' => 'images\\Brass Thaali Plate 2800.jpg', 'weight' => '1 pc', 'price' => 2800],
    ['id' => 73, 'name' => 'Silver Plated Diya', 'imageUrl' => 'images\\Ojas Pure Silver Plated Taman Ancharan Palli Panchapatra Set 3 pcs 350.jpg', 'weight' => '1 pc', 'price' => 800],
    ['id' => 74, 'name' => 'Silver Plated Bell', 'imageUrl' => 'images\\Silver Plated Bell.jpg', 'weight' => '1 pc', 'price' => 450],
    ['id' => 75, 'name' => 'Pooja Storage Box', 'imageUrl' => 'images\\Pooja Storage Box.jpg', 'weight' => '1 pc', 'price' => 320],
    ['id' => 76, 'name' => 'Temple Lamp Large', 'imageUrl' => 'images\\Temple Lamp Large.jpg', 'weight' => '1 pc', 'price' => 650],
    ['id' => 77, 'name' => 'Wooden Mandir Small', 'imageUrl' => 'images\\Wooden Mandir Small.jpg', 'weight' => '1 pc', 'price' => 5000],
    ['id' => 78, 'name' => 'Wooden Mandir Medium', 'imageUrl' => 'images\\Wooden Mandir Medium.jpg', 'weight' => '1 pc', 'price' => 8000],
    ['id' => 79, 'name' => 'Brass Deepam Lamp', 'imageUrl' => 'images\\Brass Deepam Lamp.jpg', 'weight' => '1 pc', 'price' => 250],
    ['id' => 80, 'name' => 'Panchamrut Mix', 'imageUrl' => 'images\\panchamrut mix.jpg', 'weight' => '200 g', 'price' => 280],
    ['id' => 81, 'name' => 'Honey Pooja', 'imageUrl' => 'images\\honey pooja.jpg', 'weight' => '200 g', 'price' => 220],
    ['id' => 82, 'name' => 'Milk Powder Pooja', 'imageUrl' => 'images\\milk powder pooja.jpg', 'weight' => '200 g', 'price' => 110],
    ['id' => 83, 'name' => 'Sugar Candy Mishri', 'imageUrl' => 'images\\sugar candy mishri.jpg', 'weight' => '200 g', 'price' => 260],
    ['id' => 84, 'name' => 'Dry Fruits Pooja Mix', 'imageUrl' => 'images\\dry fruits pooja mix.jpg', 'weight' => '200 g', 'price' => 220],
    ['id' => 85, 'name' => 'Cloves Pooja', 'imageUrl' => 'images\\cloves pooja.jpg', 'weight' => '50 g', 'price' => 280],
    ['id' => 86, 'name' => 'Cardamom Pooja', 'imageUrl' => 'images\\Cardamom Pooja.jpg', 'weight' => '50 g', 'price' => 150],
    ['id' => 87, 'name' => 'Saffron Pooja', 'imageUrl' => 'images\\Saffron Pooja.jpg', 'weight' => '1 g', 'price' => 250],
    ['id' => 88, 'name' => 'Rose Water Pooja', 'imageUrl' => 'images\\Rose Water Pooja.jpg', 'weight' => '200 ml', 'price' => 180],
    ['id' => 89, 'name' => 'Attar Fragrance', 'imageUrl' => 'images\\attar fragrance.jpg', 'weight' => '10 ml', 'price' => 180],
    ['id' => 90, 'name' => 'Temple Camphor', 'imageUrl' => 'images\\temple camphor.jpg', 'weight' => '100 g', 'price' => 120],
    ['id' => 91, 'name' => 'Pooja Samagri Box Large', 'imageUrl' => 'images\\pooja samagri box large.jpg', 'weight' => '1 pc', 'price' => 550],
    ['id' => 92, 'name' => 'Pooja Basket', 'imageUrl' => 'images\\pooja basket.jpg', 'weight' => '1 pc', 'price' => 180],
    ['id' => 93, 'name' => 'Decorative Bell', 'imageUrl' => 'images\\decorative bell.jpg', 'weight' => '1 pc', 'price' => 220],
    ['id' => 94, 'name' => 'Copper Kalash Large', 'imageUrl' => 'images\\Novelika Copper Pooja Kalash 2000.jpg', 'weight' => '1 pc', 'price' => 450],
    ['id' => 95, 'name' => 'Brass Kalash Large', 'imageUrl' => 'images\\brass kalash large.jpg', 'weight' => '1 pc', 'price' => 650],
    ['id' => 96, 'name' => 'Holy Water Sprinkler', 'imageUrl' => 'images\\holy water sprinkler 320.jpg', 'weight' => '1 pc', 'price' => 320],
    ['id' => 97, 'name' => 'Aarti Plate Set', 'imageUrl' => 'images\\aarti plate set.jpg', 'weight' => '1 set', 'price' => 420],
    ['id' => 98, 'name' => 'Decorative Aarti Lamp', 'imageUrl' => 'images\\Traditional Brass Panch Aarti Diya with Wooden Handle, Temple Oil Lamp, Decorative Pooja Stand for Religious Ceremonies 400.jpg', 'weight' => '1 pc', 'price' => 580],
    ['id' => 99, 'name' => 'Temple Curtain', 'imageUrl' => 'images\\temple curtain.jpg', 'weight' => '1 pc', 'price' => 350],
    ['id' => 100, 'name' => 'Temple Door Bells', 'imageUrl' => 'images\\temple door bells.jpg', 'weight' => '1 pc', 'price' => 280],
    ['id' => 101, 'name' => 'Diwali Diyas Pack', 'imageUrl' => 'images\\Handmade Terracotta Clay Diyas 400.jpg', 'weight' => '12 pcs', 'price' => 120],
    ['id' => 102, 'name' => 'Festival Pooja Kit', 'imageUrl' => 'images\\Pooja Samagri Kit for Daily Puja and Festivals  700.jpg', 'weight' => '1 set', 'price' => 950],
    ['id' => 103, 'name' => 'Navratri Pooja Kit', 'imageUrl' => 'images\\navratri pooja kit.jpg', 'weight' => '1 set', 'price' => 1250],
    ['id' => 104, 'name' => 'Ganesh Chaturthi Pooja Kit', 'imageUrl' => 'images\\ganesh chaturthi pooja kit.jpg', 'weight' => '1 set', 'price' => 650],
    ['id' => 105, 'name' => 'Lakshmi Pooja Kit', 'imageUrl' => 'images\\lakshmi pooja kit.jpg', 'weight' => '1 set', 'price' => 880],
    ['id' => 106, 'name' => 'Satyanarayan Pooja Kit', 'imageUrl' => 'images\\Satya Narayan Puja Kit 1600.jpg', 'weight' => '1 set', 'price' => 1650],
    ['id' => 107, 'name' => 'Wedding Pooja Kit', 'imageUrl' => 'images\\wedding pooja kit.jpg', 'weight' => '1 set', 'price' => 1850],
    ['id' => 108, 'name' => 'Temple Decoration Cloth', 'imageUrl' => 'images\\Creative Space Red Velvet Pooja Cloth Aasan For Mandir With Golden Border  320.jpg', 'weight' => '1 pc', 'price' => 330],
    ['id' => 109, 'name' => 'Temple Flags', 'imageUrl' => 'images\\temple flags.jpg', 'weight' => '1 pc', 'price' => 120],
    ['id' => 110, 'name' => 'Sacred Cow Dung Cakes', 'imageUrl' => 'images\\sacred cow dung cakes.jpg', 'weight' => '10 pcs', 'price' => 370],
    ['id' => 111, 'name' => 'Gomutra Bottle', 'imageUrl' => 'images\\gomutra bottle.jpg', 'weight' => '250 ml', 'price' => 260],
    ['id' => 112, 'name' => 'Tulsi Leaves Pack', 'imageUrl' => 'images\\tulsi leaves pack.jpg', 'weight' => '50 g', 'price' => 340],
    ['id' => 113, 'name' => 'Durva Grass Pack', 'imageUrl' => 'images\\durva grass pack.jpg', 'weight' => '50 g', 'price' => 240],
    ['id' => 114, 'name' => 'Pooja Coconut Large', 'imageUrl' => 'images\\fresho! Coconut 50.jpg', 'weight' => '1 pc', 'price' => 65],
    ['id' => 115, 'name' => 'Sacred Red Cloth', 'imageUrl' => 'images\\sacred red cloth.jpg', 'weight' => '1 pc', 'price' => 480],
    ['id' => 116, 'name' => 'Yellow Pooja Cloth', 'imageUrl' => 'images\\yellow pooja cloth.jpg', 'weight' => '1 pc', 'price' => 280],
    ['id' => 117, 'name' => 'Pooja Garland Premium', 'imageUrl' => 'images\\pooja garland premium.jpg', 'weight' => '1 pc', 'price' => 120],
    ['id' => 118, 'name' => 'Decorative Mandir Light', 'imageUrl' => 'images\\decorative mandir light.jpg', 'weight' => '1 pc', 'price' => 280],
    ['id' => 119, 'name' => 'LED Temple Diya', 'imageUrl' => 'images\\led temple diya.jpg', 'weight' => '1 pc', 'price' => 150],
    ['id' => 120, 'name' => 'Pooja Gift Set', 'imageUrl' => 'images\\pooja gift set.jpg', 'weight' => '1 set', 'price' => 720],
    ['id' => 121, 'name' => 'Decorative Brass Lamp', 'imageUrl' => 'images\\decorative brass lamp.jpg', 'weight' => '1 pc', 'price' => 350],
    ['id' => 122, 'name' => 'Large Pancharti Diya', 'imageUrl' => 'images\\large pancharti diya.jpg', 'weight' => '1 pc', 'price' => 480],
    ['id' => 123, 'name' => 'Copper Water Pot', 'imageUrl' => 'images\\copper water pot.jpg', 'weight' => '1 pc', 'price' => 350],
    ['id' => 124, 'name' => 'Silver Plated Kalash', 'imageUrl' => 'images\\silver plated kalash.jpg', 'weight' => '1 pc', 'price' => 550],
    ['id' => 125, 'name' => 'Brass Bell Premium', 'imageUrl' => 'images\\PRIGMA PLAIN-BELL Brass Pooja Bell 250.jpg', 'weight' => '1 pc', 'price' => 320],
    ['id' => 126, 'name' => 'Temple Oil Lamp', 'imageUrl' => 'images\\temple oil lamp.jpg', 'weight' => '1 pc', 'price' => 450],
    ['id' => 127, 'name' => 'Decorative Mandir Cloth', 'imageUrl' => 'images\\decorative mandir cloth.jpg', 'weight' => '1 pc', 'price' => 180],
    ['id' => 128, 'name' => 'Sandalwood Incense Premium', 'imageUrl' => 'images\\sandalwood incense premium.jpg', 'weight' => '1 pack', 'price' => 95],
    ['id' => 129, 'name' => 'Floral Incense Premium', 'imageUrl' => 'images\\floral incense premium.jpg', 'weight' => '1 pack', 'price' => 190],
    ['id' => 130, 'name' => 'Temple Sambrani Pack', 'imageUrl' => 'images\\temple sambrani pack.jpg', 'weight' => '100 g', 'price' => 120],
    ['id' => 131, 'name' => 'Pooja Flower Basket', 'imageUrl' => 'images\\pooja flower basket.jpg', 'weight' => '1 pc', 'price' => 160],
    ['id' => 132, 'name' => 'Decorative Pooja Tray', 'imageUrl' => 'images\\decorative pooja tray.jpg', 'weight' => '1 pc', 'price' => 280],
    ['id' => 133, 'name' => 'Marble Ganesh Idol', 'imageUrl' => 'images\\Eco Friendly Handcrafted Clay 350.jpg', 'weight' => '1 pc', 'price' => 620],
    ['id' => 134, 'name' => 'Marble Lakshmi Idol', 'imageUrl' => 'images\\Dharma Statues Brass Lakshmi Fine 950.jpg', 'weight' => '1 pc', 'price' => 620],
    ['id' => 135, 'name' => 'Marble Shiva Idol', 'imageUrl' => 'images\\Premium Brass Miniature Shiv 1200.jpg', 'weight' => '1 pc', 'price' => 640],
    ['id' => 136, 'name' => 'Marble Krishna Idol', 'imageUrl' => 'images\\Svastika Krishna Idol for Car Dashboard 2500.jpg', 'weight' => '1 pc', 'price' => 640],
    ['id' => 137, 'name' => 'Marble Radha Krishna Idol', 'imageUrl' => 'images\\Radha Krishna Murti  460.jpg', 'weight' => '1 pc', 'price' => 780],
    ['id' => 138, 'name' => 'Marble Hanuman Idol', 'imageUrl' => 'images\\Blessing Hanuman Idol 350.jpg', 'weight' => '1 pc', 'price' => 650],
    ['id' => 139, 'name' => 'Marble Durga Idol', 'imageUrl' => 'images\\Ekhasa Durga Maa Idol 1200.jpg', 'weight' => '1 pc', 'price' => 690],
    ['id' => 140, 'name' => 'Marble Saraswati Idol', 'imageUrl' => 'images\\saraswati.jpg', 'weight' => '1 pc', 'price' => 720],
    ['id' => 141, 'name' => 'Brass Ganesh Idol', 'imageUrl' => 'images\\Pure Brass Ganesha Idol Size 450.jpg', 'weight' => '1 pc', 'price' => 520],
    ['id' => 142, 'name' => 'Brass Lakshmi Idol', 'imageUrl' => 'images\\Mini Brass Kamal Lakshmi Idol 490.jpg', 'weight' => '1 pc', 'price' => 520],
    ['id' => 143, 'name' => 'Brass Krishna Idol', 'imageUrl' => 'images\\StonKraft Krishna Murti Idol Statue Sculpture 2245.jpg', 'weight' => '1 pc', 'price' => 540],
    ['id' => 144, 'name' => 'Brass Shiva Idol', 'imageUrl' => 'images\\Brassaura Brass Shiva Idol 1380.jpg', 'weight' => '1 pc', 'price' => 1380],
    ['id' => 145, 'name' => 'Brass Durga Idol', 'imageUrl' => 'images\\Shri Astha Vinayak Brass Goddess Durga Idol 200.jpg', 'weight' => '1 pc', 'price' => 560],
    ['id' => 146, 'name' => 'Brass Hanuman Idol', 'imageUrl' => 'images\\Blessing Hanuman Idol 350.jpg', 'weight' => '1 pc', 'price' => 560],
    ['id' => 147, 'name' => 'Copper Ganesh Idol', 'imageUrl' => 'images\\copper ganesh idol.jpg', 'weight' => '1 pc', 'price' => 480],
    ['id' => 148, 'name' => 'Copper Lakshmi Idol', 'imageUrl' => 'images\\copper lakshmi idol.jpg', 'weight' => '1 pc', 'price' => 480],
    ['id' => 149, 'name' => 'Copper Shiva Idol', 'imageUrl' => 'images\\copper shiva idol.jpg', 'weight' => '1 pc', 'price' => 500],
    ['id' => 150, 'name' => 'Copper Krishna Idol', 'imageUrl' => 'images\\copper krishna idol.jpg', 'weight' => '1 pc', 'price' => 500],
    ['id' => 151, 'name' => 'Rudraksha Bracelet', 'imageUrl' => 'images\\rudraksha bracelet.jpg', 'weight' => '1 pc', 'price' => 150],
    ['id' => 152, 'name' => 'Rudraksha Pendant', 'imageUrl' => 'images\\rudraksha pendant.jpg', 'weight' => '1 pc', 'price' => 180],
    ['id' => 153, 'name' => 'Tulsi Bead Bracelet', 'imageUrl' => 'images\\tulsi bead bracelet.jpg', 'weight' => '1 pc', 'price' => 120],
    ['id' => 154, 'name' => 'Tulsi Bead Mala', 'imageUrl' => 'images\\108 Beads Original Tulsi Japa Mala 250.jpg', 'weight' => '1 pc', 'price' => 520],
    ['id' => 155, 'name' => 'Temple Fragrance Oil', 'imageUrl' => 'images\\temple fragrance oil.jpg', 'weight' => '10 ml', 'price' => 160],
    ['id' => 156, 'name' => 'Temple Camphor Premium', 'imageUrl' => 'images\\Temple Camphor.jpg', 'weight' => '100 g', 'price' => 150],
    ['id' => 157, 'name' => 'Decorative Aarti Thali', 'imageUrl' => 'images\\Classic Essentials Pooja Thali 220.jpg', 'weight' => '1 pc', 'price' => 980],
    ['id' => 158, 'name' => 'Temple Hanging Bells', 'imageUrl' => 'images\\temple hanging bells.jpg', 'weight' => '1 pc', 'price' => 760],
    ['id' => 159, 'name' => 'Decorative Temple Lamps', 'imageUrl' => 'images\\decorative temple lamps.jpg', 'weight' => '1 pc', 'price' => 420],
    ['id' => 160, 'name' => 'Festival Mandir Decoration Set', 'imageUrl' => 'images\\festival mandir decoration set.jpg', 'weight' => '1 set', 'price' => 3500],
    ['id' => 161, 'name' => 'Temple Wall Hanging', 'imageUrl' => 'images\\temple wall hanging.jpg', 'weight' => '1 pc', 'price' => 5220],
    ['id' => 162, 'name' => 'Pooja Room Door Toran', 'imageUrl' => 'images\\pooja room door toran.jpg', 'weight' => '1 pc', 'price' => 480],
    ['id' => 163, 'name' => 'Mango Leaves Toran', 'imageUrl' => 'images\\mango leaves toran.jpg', 'weight' => '1 pc', 'price' => 290],
    ['id' => 164, 'name' => 'Artificial Flower Toran', 'imageUrl' => 'images\\artificial flower toran.jpg', 'weight' => '1 pc', 'price' => 320],
    ['id' => 165, 'name' => 'Brass Hanging Lamp', 'imageUrl' => 'images\\brass hanging lamp.jpg', 'weight' => '1 pc', 'price' => 4580],
    ['id' => 170, 'name' => 'Automatic Incense Burner', 'imageUrl' => 'images\\automatic incense burner.jpg', 'weight' => '1 pc', 'price' => 10080],
    ['id' => 174, 'name' => 'Decorative Mandir Clock', 'imageUrl' => 'images\\decorative mandir clock.jpg', 'weight' => '1 pc', 'price' => 580],
    ['id' => 175, 'name' => 'Mandir Wall Frame Ganesh', 'imageUrl' => 'images\\mandir wall frame ganesh.jpg', 'weight' => '1 pc', 'price' => 340],
    ['id' => 176, 'name' => 'Mandir Wall Frame Lakshmi', 'imageUrl' => 'images\\mandir wall frame lakshmi.jpg', 'weight' => '1 pc', 'price' => 340],
    ['id' => 177, 'name' => 'Mandir Wall Frame Shiva', 'imageUrl' => 'images\\mandir wall frame shiva.jpg', 'weight' => '1 pc', 'price' => 340],
    ['id' => 178, 'name' => 'Mandir Wall Frame Krishna', 'imageUrl' => 'images\\mandir wall frame krishna.jpg', 'weight' => '1 pc', 'price' => 340],
    ['id' => 179, 'name' => 'Mandir Wall Frame Durga', 'imageUrl' => 'images\\mandir wall frame durga.jpg', 'weight' => '1 pc', 'price' => 340],
    ['id' => 180, 'name' => 'Mandir Wall Frame Hanuman', 'imageUrl' => 'images\\mandir wall frame hanuman.jpg', 'weight' => '1 pc', 'price' => 340],
    ['id' => 181, 'name' => 'Mandir Wall Frame Saraswati', 'imageUrl' => 'images\\mandir wall frame saraswati.jpg', 'weight' => '1 pc', 'price' => 440],
    ['id' => 182, 'name' => 'Mandir Decorative Plate', 'imageUrl' => 'images\\mandir decorative plate.jpg', 'weight' => '1 pc', 'price' => 1020],
    ['id' => 183, 'name' => 'Mandir Decorative Bells', 'imageUrl' => 'images\\mandir decorative bells.jpg', 'weight' => '1 pc', 'price' => 960],
    ['id' => 184, 'name' => 'Mandir Brass Stand', 'imageUrl' => 'images\\mandir brass stand.jpg', 'weight' => '1 pc', 'price' => 680],
    ['id' => 185, 'name' => 'Mandir Flower Vase', 'imageUrl' => 'images\\mandir flower vase.jpg', 'weight' => '1 pc', 'price' => 320],
    ['id' => 186, 'name' => 'Mandir Decorative Garland', 'imageUrl' => 'images\\mandir decorative garland.jpg', 'weight' => '1 pc', 'price' => 480],
    ['id' => 187, 'name' => 'Mandir Silver Plated Diya', 'imageUrl' => 'images\\mandir silver plated diya.jpg', 'weight' => '1 pc', 'price' => 2050],
    ['id' => 188, 'name' => 'Mandir Copper Diya', 'imageUrl' => 'images\\mandir copper diya.jpg', 'weight' => '1 pc', 'price' => 880],
    ['id' => 189, 'name' => 'Mandir Decorative Lamp', 'imageUrl' => 'images\\mandir decorative lamp.jpg', 'weight' => '1 pc', 'price' => 720],
    ['id' => 190, 'name' => 'Temple Decoration Lights', 'imageUrl' => 'images\\temple decoration lights.jpg', 'weight' => '1 set', 'price' => 850],
    ['id' => 191, 'name' => 'Temple Brass Umbrella', 'imageUrl' => 'images\\Temple Brass Umbrella.jpg', 'weight' => '1 pc', 'price' => 920],
    ['id' => 192, 'name' => 'Temple Copper Umbrella', 'imageUrl' => 'images\\temple copper umbrella.jpg', 'weight' => '1 pc', 'price' => 880],
    ['id' => 193, 'name' => 'Temple Decorative Fan', 'imageUrl' => 'images\\temple decorative fan.jpg', 'weight' => '1 pc', 'price' => 620],
    ['id' => 194, 'name' => 'Temple Pooja Basket Premium', 'imageUrl' => 'images\\temple pooja basket premium.jpg', 'weight' => '1 pc', 'price' => 360],
    ['id' => 195, 'name' => 'Temple Holy Water Bottle', 'imageUrl' => 'images\\temple holy water bottle.jpg', 'weight' => '250 ml', 'price' => 120],
    ['id' => 196, 'name' => 'Temple Sacred Ash Vibhuti', 'imageUrl' => 'images\\temple sacred ash vibhuti.jpg', 'weight' => '50 g', 'price' => 250],
    ['id' => 197, 'name' => 'Temple Sandal Paste', 'imageUrl' => 'images\\temple sandal paste.jpg', 'weight' => '50 g', 'price' => 400],
    ['id' => 198, 'name' => 'Temple Pooja Set Premium', 'imageUrl' => 'images\\temple pooja set premium.jpg', 'weight' => '1 set', 'price' => 720],
    ['id' => 199, 'name' => 'Temple Festival Kit Deluxe', 'imageUrl' => 'images\\temple festival kit deluxe.jpg', 'weight' => '1 set', 'price' => 950],
    ['id' => 200, 'name' => 'Complete Temple Pooja Kit', 'imageUrl' => 'images\\complete temple pooja kit.jpg', 'weight' => '1 set', 'price' => 2000]
  ];
  // Extra items (id 201-208)
  $extraItems = [
    ['id' => 201, 'name' => 'Lakshmi Idol Small', 'imageUrl' => 'images\\Eco-Friendly Ganesh Lakshmi Idol Set 900.jpg', 'weight' => '1 pc', 'price' => 920],
    ['id' => 202, 'name' => 'Ganesh Idol Small', 'imageUrl' => 'images\\Ekhasa Ganesha Idol 990.jpg', 'weight' => '1 pc', 'price' => 990],
    ['id' => 203, 'name' => 'Shiva Idol', 'imageUrl' => 'images\\Lord Shiva Idol 3050.jpg', 'weight' => '1 pc', 'price' => 3060],
    ['id' => 204, 'name' => 'Marble Ganesh Idol', 'imageUrl' => 'images\\Marble Ganesh Idol.jpg', 'weight' => '1 pc', 'price' => 620],
    ['id' => 205, 'name' => 'Brass Bell Premium', 'imageUrl' => 'images\\PRIGMA PLAIN-BELL Brass Pooja Bell 250.jpg', 'weight' => '1 pc', 'price' => 320],
    ['id' => 206, 'name' => 'Temple Oil Lamp', 'imageUrl' => 'images\\temple oil lamp.jpg', 'weight' => '1 pc', 'price' => 450],
    ['id' => 207, 'name' => 'Sandalwood Incense Premium', 'imageUrl' => 'images\\sandalwood incense premium.jpg', 'weight' => '1 pack', 'price' => 850],
    ['id' => 208, 'name' => 'Complete Temple Pooja Kit', 'imageUrl' => 'images\\complete temple pooja kit.jpg', 'weight' => '1 set', 'price' => 2000]
  ];
  $fullItems = array_merge($rawPoojaItems, $extraItems);
  // Build productsData from fullItems
  foreach ($fullItems as $item) {
    $name = $item['name'] . ($item['weight'] ? ' (' . $item['weight'] . ')' : '');
    $kn = $item['name'] . ' - ಪೂಜಾ ವಸ್ತು';
    $productsData[] = [
      'id' => $item['id'],
      'name' => $name,
      'kn' => $kn,
      'category' => 'other', // will be guessed in JS
      'price' => $item['price'],
      'imageUrl' => $item['imageUrl'],
      'description' => 'Sacred ' . $item['name'] . ' for daily rituals.',
      'weight' => $item['weight'] ?? ''
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes">
  <title>ಪೂಜಾ ಭಂಡಾರ · Sacred Pooja Store</title>
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* ===== SAME CSS AS STATIC VERSION ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: system-ui, 'Segoe UI', 'Noto Sans', sans-serif;
    }

    body {
      display: block;
    }

    .main-container {
      max-width: 1400px;
      width: 100%;
    }

    .brand-header {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      margin: 0.5rem 0 1.8rem;
    }

    .title-area h1 {
      font-size: 2rem;
      font-weight: 600;
      display: flex;
      flex-wrap: wrap;
      align-items: baseline;
      gap: 0.7rem;
      color: #2c4b3e;
    }

    .title-area h1 span:first-child {
      background: #cfe3cf;
      padding: 0.2rem 1.4rem;
      border-radius: 60px;
      font-size: 1.8rem;
      letter-spacing: 1px;
    }

    .title-area h1 span:last-child {
      font-size: 1.6rem;
      font-weight: 500;
      color: #5b3e2b;
    }

    .lang-row {
      font-size: 0.9rem;
      color: #6b8c64;
      margin-top: 0.2rem;
    }

    .cart-link {
      background: white;
      padding: 0.7rem 1.4rem;
      border-radius: 60px;
      box-shadow: 0 6px 14px rgba(0, 0, 0, 0.05);
      border: 1px solid #cddeca;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-size: 1.2rem;
      transition: 0.2s;
      background: #fefcf7;
      text-decoration: none;
      color: inherit;
      font-weight: 600;
    }

    .cart-link:hover {
      background: #e7f0e5;
      transform: scale(0.96);
    }

    .cart-count {
      background: #b6682c;
      color: white;
      border-radius: 40px;
      padding: 0.15rem 0.7rem;
      font-weight: 700;
      font-size: 0.85rem;
    }

    .search-section {
      width: min(100% - 2rem, 1280px);
      margin: 1.5rem auto 2rem;
    }

    .catalog-heading {
      margin-bottom: 1.5rem;
    }

    .catalog-heading h1 {
      color: #243a2c;
      font-size: 2rem;
      line-height: 1.15;
    }

    .catalog-heading p {
      color: #617063;
      margin-top: 0.45rem;
    }

    .search-box {
      display: flex;
      align-items: center;
      background: white;
      border-radius: 8px;
      padding: 0.25rem 0.35rem 0.25rem 1rem;
      box-shadow: 0 10px 24px rgba(60, 70, 40, 0.08);
      border: 1px solid #c6dbc1;
    }

    .search-box i {
      color: #639266;
      font-size: 1.2rem;
    }

    .search-box input {
      flex: 1;
      border: none;
      padding: 1rem 0.8rem;
      font-size: 1rem;
      background: transparent;
      outline: none;
      font-weight: 500;
    }

    .search-box button {
      background: #708d6b;
      border: none;
      color: white;
      padding: 0.8rem 2rem;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: 0.15s;
      margin: 6px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .search-box button:hover {
      background: #537A4E;
    }

    .filter-pills {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-start;
      gap: 0.7rem;
      margin: 1.8rem 0 1rem;
    }

    .pill {
      background: #e5efdf;
      color: #3d5c38;
      padding: 0.5rem 1.4rem;
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      border: 1px solid #b8cfb0;
      cursor: pointer;
      transition: 0.15s;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .pill:focus-visible,
    .search-box button:focus-visible,
    .add-to-cart-btn:focus-visible,
    .search-box input:focus-visible {
      outline: 3px solid #bf5b2b;
      outline-offset: 3px;
    }

    .pill.active {
      background: #638a5c;
      color: white;
      border-color: #456c3e;
      box-shadow: 0 4px 10px rgba(70, 100, 50, 0.2);
    }

    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(min(100%, 230px), 1fr));
      gap: 1rem;
      margin: 2rem 0 2rem;
    }

    .result-count {
      color: #617063;
      font-size: 0.9rem;
      margin-top: 1.25rem;
    }

    .product-card {
      background: #ffffffea;
      border-radius: 8px;
      padding: 0.85rem;
      box-shadow: 0 15px 28px rgba(70, 60, 30, 0.05);
      transition: all 0.25s ease;
      border: 1px solid #ebddcf;
      display: flex;
      flex-direction: column;
      position: relative;
    }

    .product-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 24px 36px rgba(30, 50, 20, 0.1);
      border-color: #cfdfc8;
    }

    .product-img-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
      aspect-ratio: 4 / 3;
      margin-bottom: 0.85rem;
      overflow: hidden;
      background: #f8f3e9;
      border-radius: 6px;
    }

    .product-img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      transition: 0.2s;
    }

    .product-category {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 700;
      color: #8f6b48;
      margin-bottom: 0.3rem;
    }

    .product-name {
      font-size: 1.15rem;
      font-weight: 700;
      color: #2f4d2d;
      line-height: 1.2;
    }

    .product-name-kn {
      font-size: 0.9rem;
      color: #7e6954;
      margin: 4px 0 6px;
      border-left: 3px solid #c2cc9a;
      padding-left: 8px;
    }

    .product-desc {
      font-size: 0.85rem;
      color: #526e48;
      background: #f6faf3;
      padding: 8px 12px;
      border-radius: 6px;
      margin: 12px 0 8px;
      line-height: 1.4;
    }

    .desc-kn {
      font-size: 0.8rem;
      color: #9a816a;
      margin-bottom: 12px;
      font-style: normal;
      background: #fefaf5;
      padding: 4px 0;
    }

    .price-area {
      display: flex;
      align-items: baseline;
      gap: 0.8rem;
      flex-wrap: wrap;
      margin: 8px 0 5px;
    }

    .price {
      font-size: 1.9rem;
      font-weight: 800;
      color: #d5712b;
    }

    .mrp {
      color: #a39582;
      text-decoration: line-through;
      font-size: 1rem;
    }

    .source-row {
      font-size: 0.7rem;
      color: #a48d78;
      margin: 0.4rem 0 0.5rem;
    }

    .add-to-cart-btn {
      background: #cfdfc2;
      border: none;
      border-radius: 6px;
      padding: 0.8rem 1rem;
      margin-top: 1rem;
      font-size: 0.9rem;
      font-weight: 700;
      color: #2e4327;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      cursor: pointer;
      transition: all 0.2s;
      border: 1px solid #b8c6a6;
      width: 100%;
    }

    .add-to-cart-btn:hover {
      background: #bbd0aa;
      transform: scale(0.97);
    }

    .no-results {
      text-align: center;
      padding: 3rem;
      background: #fef7e8;
      border-radius: 60px;
      color: #7f694a;
      display: none;
    }

    .footer-note {
      text-align: center;
      color: #749c6b;
      border-top: 1px solid #dccbba;
      padding-top: 2rem;
      margin-top: 1rem;
    }

    @media (max-width: 680px) {
      .search-section {
        width: min(100% - 1rem, 1280px);
      }

      .catalog-heading h1 {
        font-size: 1.6rem;
      }

      .search-box {
        flex-wrap: wrap;
      }

      .search-box input {
        min-width: 0;
      }

      .search-box button {
        justify-content: center;
        flex: 0 0 100%;
        margin: 0.25rem 0 0;
      }

      .filter-pills {
        flex-wrap: nowrap;
        justify-content: flex-start;
        overflow-x: auto;
        padding: 0 0 0.5rem;
      }

      .pill {
        flex: 0 0 auto;
      }
    }
  </style>
  <link rel="stylesheet" href="category-style.css">
  <link rel="stylesheet" href="../index.css">
</head>

<body>
  <div class="main-container">
    <?php
    $base_url = '../';
    include __DIR__ . '/../includes/global-header.php';
    ?>
  </div>
  <div class="search-section">
    <header class="catalog-heading">
      <h1>Daily pooja essentials</h1>
      <p>Incense, lamps, ritual accessories and more for your home mandir.</p>
    </header>
    <div class="search-box">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="search" id="searchInput" placeholder="Search products / ಹುಡುಕಿ" aria-label="Search pooja products">
      <button id="searchBtn" type="button"><i class="fa-solid fa-search"></i> Search / ಹುಡುಕು</button>
    </div>
    <p class="result-count" id="resultCount" aria-live="polite"></p>
    <div class="filter-pills" id="filterPills">
      <button class="pill active" type="button" data-filter="all" aria-pressed="true"><i class="fa-regular fa-star"></i> ಎಲ್ಲಾ / All</button>
      <button class="pill" type="button" data-filter="incense" aria-pressed="false"><i class="fa-solid fa-wind"></i> Incense</button>
      <button class="pill" type="button" data-filter="lamp" aria-pressed="false"><i class="fa-regular fa-lightbulb"></i> Lamps</button>
      <button class="pill" type="button" data-filter="flower" aria-pressed="false"><i class="fa-solid fa-fan"></i> Flowers</button>
      <button class="pill" type="button" data-filter="powder" aria-pressed="false"><i class="fa-solid fa-cubes"></i> Powders</button>
      <button class="pill" type="button" data-filter="thali" aria-pressed="false"><i class="fa-solid fa-plate"></i> Thali</button>
      <button class="pill" type="button" data-filter="kalash" aria-pressed="false"><i class="fa-regular fa-flask"></i> Kalash</button>
      <button class="pill" type="button" data-filter="bell" aria-pressed="false"><i class="fa-regular fa-bell"></i> Bells</button>
      <button class="pill" type="button" data-filter="idol" aria-pressed="false"><i class="fa-regular fa-chess-queen"></i> Idols</button>
      <button class="pill" type="button" data-filter="mala" aria-pressed="false"><i class="fa-regular fa-circle"></i> Mala</button>
      <button class="pill" type="button" data-filter="kit" aria-pressed="false"><i class="fa-solid fa-bag-shopping"></i> Kits</button>
      <button class="pill" type="button" data-filter="havan" aria-pressed="false"><i class="fa-solid fa-fire"></i> Havan</button>
      <button class="pill" type="button" data-filter="decor" aria-pressed="false"><i class="fa-regular fa-tree"></i> Decor</button>
      <button class="pill" type="button" data-filter="coin" aria-pressed="false"><i class="fa-regular fa-circle"></i> Coins</button>
      <button class="pill" type="button" data-filter="other" aria-pressed="false"><i class="fa-regular fa-star"></i> Other</button>
    </div>
    <div class="products-grid" id="productsGrid"></div>
  </div>
  <div class="no-results" id="noResultsMsg">
    <i class="fa-regular fa-face-frown"></i> ಯಾವುದೇ ವಸ್ತುಗಳು ಕಂಡುಬಂದಿಲ್ಲ / No items found
  </div>
  <div class="footer-note">
    <i class="fa-regular fa-tag"></i> Sacred items · Temple quality | ಎಲ್ಲಾ ಬೆಲೆಗಳು ಭಾರತೀಯ ಮಾರುಕಟ್ಟೆ ಆಧಾರಿತ
  </div>
  </div>
  <script>
    // ============================================================
    // JAVASCRIPT – SESSION-BASED CART VIA AJAX + LOCALSTORAGE FALLBACK
    // ============================================================
    (function() {
      // ----- PRODUCT DATA FROM PHP (embedded) -----
      const productsData = <?php echo json_encode($productsData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
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
      // ----- CATEGORY GUESSING & ENRICHMENT (same as static) -----
      function enrichProduct(item) {
        let cat = 'other';
        let icon = 'fa-regular fa-star';
        const lower = (item.name + ' ' + (item.weight || '')).toLowerCase();
        if (lower.includes('agarbatti') || lower.includes('incense') || lower.includes('dhoop') || lower.includes('camphor') || lower.includes('kapoor') || lower.includes('sambrani') || lower.includes('vibhuti')) cat = 'incense';
        else if (lower.includes('diya') || lower.includes('lamp') || lower.includes('deepam') || lower.includes('wicks') || lower.includes('pancharti')) cat = 'lamp';
        else if (lower.includes('flower') || lower.includes('garland') || lower.includes('tulsi') || lower.includes('lotus') || lower.includes('toran') || lower.includes('betel leaf') || lower.includes('marigold')) cat = 'flower';
        else if (lower.includes('kumkum') || lower.includes('haldi') || lower.includes('turmeric') || lower.includes('chandan') || lower.includes('sindoor') || lower.includes('gulal') || lower.includes('ash')) cat = 'powder';
        else if (lower.includes('thali') || lower.includes('plate') || lower.includes('spoon') || lower.includes('tray')) cat = 'thali';
        else if (lower.includes('kalash') || lower.includes('pot') || lower.includes('lota')) cat = 'kalash';
        else if (lower.includes('bell')) cat = 'bell';
        else if (lower.includes('idol') || lower.includes('murti') || lower.includes('ganesh') || lower.includes('shiva') || lower.includes('lakshmi')) cat = 'idol';
        else if (lower.includes('mala') || lower.includes('rudraksha') || lower.includes('bead')) cat = 'mala';
        else if (lower.includes('kit') || lower.includes('set') || lower.includes('samagri')) cat = 'kit';
        else if (lower.includes('havan') || lower.includes('ghee') || lower.includes('cow dung') || lower.includes('honey') || lower.includes('dry fruits')) cat = 'havan';
        else if (lower.includes('decor') || lower.includes('rangoli') || lower.includes('curtain') || lower.includes('cloth')) cat = 'decor';
        else if (lower.includes('coin')) cat = 'coin';
        const mrp = Math.round(item.price * 1.28);
        return {
          id: item.id,
          name: item.name,
          namekn: item.kn,
          image: item.imageUrl,
          price: item.price,
          mrp: mrp,
          category: cat,
          icon: icon,
          descen: item.description || 'Sacred item for daily rituals',
          desckn: item.kn + ' - ಪೂಜಾ ವಸ್ತು',
          source: 'ಶ್ರೀ ಭಂಡಾರ'
        };
      }
      const products = productsData.map(enrichProduct);
      // ----- CART STATE (localStorage, but we also sync via AJAX to server) -----
      let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
      let activeFilter = 'all';
      let searchTerm = '';
      const grid = document.getElementById('productsGrid');
      const searchInput = document.getElementById('searchInput');
      const searchBtn = document.getElementById('searchBtn');
      const noResults = document.getElementById('noResultsMsg');
      const resultCount = document.getElementById('resultCount');
      const filterPills = document.querySelectorAll('.pill');
      const cartCountSpan = document.getElementById('cartCount');

      function escapeHTML(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;'
        })[character]);
      }

      function updateCartBadge() {
        const totalQty = cart.reduce((sum, i) => sum + (i.qty || 0), 0);
        cartCountSpan.innerText = totalQty;
        localStorage.setItem('glowCartNew', JSON.stringify(cart));
      }

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

      function renderProducts() {
        const filtered = products.filter(p => {
          const catMatch = activeFilter === 'all' || p.category === activeFilter;
          const term = searchTerm.trim().toLowerCase();
          const searchMatch = term === '' || p.name.toLowerCase().includes(term) || p.namekn.toLowerCase().includes(term) || p.descen.toLowerCase().includes(term);
          return catMatch && searchMatch;
        });
        resultCount.textContent = `${filtered.length} ${filtered.length === 1 ? 'item' : 'items'}`;
        if (filtered.length === 0) {
          grid.innerHTML = '';
          noResults.style.display = 'block';
          return;
        }
        noResults.style.display = 'none';
        grid.innerHTML = filtered.map(prod => `
            <div class="product-card">
                <div class="product-img-wrapper">
              <img class="product-img" src="${escapeHTML(prod.image)}" alt="${escapeHTML(prod.name)}" loading="lazy" onerror="this.style.visibility='hidden'">
                </div>
            <div class="product-category"><i class="${escapeHTML(prod.icon)}"></i> ${escapeHTML(prod.category.toUpperCase())}</div>
            <div class="product-name">${escapeHTML(prod.name)}</div>
            <div class="product-name-kn">${escapeHTML(prod.namekn)}</div>
            <div class="product-desc"><i class="fa-regular fa-note-sticky"></i> ${escapeHTML(prod.descen)}</div>
            <div class="desc-kn">${escapeHTML(prod.desckn)}</div>
                <div class="price-area">
              <span class="price">₹${escapeHTML(prod.price)}</span>
              <span class="mrp">₹${escapeHTML(prod.mrp)}</span>
                </div>
            <div class="source-row"><i class="fa-regular fa-shop"></i> ${escapeHTML(prod.source)}</div>
            <button class="add-to-cart-btn" type="button" data-id="${escapeHTML(prod.id)}" title="Add this sacred item to your cart / ಕಾರ್ಟ್‌ಗೆ ಸೇರಿಸಿ">
                    <i class="fa-solid fa-cart-plus"></i> Add to cart · ಸೇರಿಸಿ
                </button>
            </div>
        `).join('');
        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
          btn.addEventListener('click', (e) => {
            const id = parseInt(btn.dataset.id);
            addToCart(id);
          });
        });
      }
      // ----- EVENT BINDING -----
      filterPills.forEach(pill => {
        pill.addEventListener('click', () => {
          filterPills.forEach(p => p.classList.remove('active'));
          filterPills.forEach(p => p.setAttribute('aria-pressed', 'false'));
          pill.classList.add('active');
          pill.setAttribute('aria-pressed', 'true');
          activeFilter = pill.dataset.filter;
          renderProducts();
        });
      });

      function doSearch() {
        searchTerm = searchInput.value;
        renderProducts();
      }
      searchBtn.addEventListener('click', doSearch);
      searchInput.addEventListener('input', doSearch);
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