<?php
// ============================================================
// plastic-household.php - Dynamic Plastic Household page
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
$categorySlug = 'plastic-household';
$plasticCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($plasticCategory) {
  // Fetch products from this category
  $dbProducts = getProductsByCategory($plasticCategory['id']);
  if (!empty($dbProducts)) {
    foreach ($dbProducts as $p) {
      $productsData[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'kn' => $p['kn_name'] ?? $p['name'] . ' (ಪ್ಲಾಸ್ಟಿಕ್)',
        'category' => $p['category_slug'] ?? 'storage',
        'price' => $p['price'],
        'imageUrl' => $p['image_url'] ?? '',
        'description' => $p['description_en'] ?? ($p['weight'] ?? 'Durable plastic item'),
        'weight' => $p['weight'] ?? ''
      ];
    }
  }
}
// Fallback to static data if no DB products found
if (empty($productsData)) {
  // Embedded static product data (same as original HTML)
  $rawItems = [
    ['id' => 1, 'name' => 'Plastic Water Bottle', 'size' => '1 L', 'price' => 80, 'imageUrl' => 'images\\Plastic Water Bottle.jpg', 'description' => '1 liter plastic water bottle for daily use.'],
    ['id' => 2, 'name' => 'Plastic Water Bottle Large', 'size' => '2 L', 'price' => 120, 'imageUrl' => 'images\\Plastic Water Bottle Large.jpg', 'description' => '2 liter large plastic water bottle.'],
    ['id' => 3, 'name' => 'Plastic Storage Container', 'size' => '1 L', 'price' => 90, 'imageUrl' => 'images\\Plastic Storage Container 1.jpg', 'description' => '1 liter plastic storage container with lid.'],
    ['id' => 4, 'name' => 'Plastic Storage Container', 'size' => '2 L', 'price' => 130, 'imageUrl' => 'images\\Plastic Storage Container.jpg', 'description' => '2 liter plastic storage container.'],
    ['id' => 5, 'name' => 'Plastic Food Storage Box', 'size' => '3 pcs set', 'price' => 180, 'imageUrl' => 'images\\Plastic Food Storage Box.jpg', 'description' => 'Set of 3 plastic food storage boxes.'],
    ['id' => 6, 'name' => 'Plastic Lunch Box', 'size' => '1 set', 'price' => 220, 'imageUrl' => 'images\\Plastic Picnic Lunch Box.jpg', 'description' => 'Plastic lunch box set.'],
    ['id' => 7, 'name' => 'Plastic Spice Jar', 'size' => '200 ml', 'price' => 50, 'imageUrl' => 'images\\Plastic Spice Jar.jpg', 'description' => '200ml plastic spice jar.'],
    ['id' => 8, 'name' => 'Plastic Spice Jar Set', 'size' => '6 pcs', 'price' => 220, 'imageUrl' => 'images\\Plastic Spice Jar Set.jpg', 'description' => 'Set of 6 plastic spice jars.'],
    ['id' => 9, 'name' => 'Plastic Rice Container', 'size' => '10 kg', 'price' => 420, 'imageUrl' => 'images\\Plastic Rice Container.jpg', 'description' => '10kg plastic rice container.'],
    ['id' => 10, 'name' => 'Plastic Grain Storage Drum', 'size' => '25 L', 'price' => 580, 'imageUrl' => 'images\\Plastic Grain Storage Drum.jpg', 'description' => '25 liter plastic grain storage drum.'],
    ['id' => 11, 'name' => 'Plastic Bucket', 'size' => '15 L', 'price' => 220, 'imageUrl' => 'images\\Plastic Bucket.jpg', 'description' => '15 liter plastic bucket.'],
    ['id' => 12, 'name' => 'Plastic Bucket Large', 'size' => '20 L', 'price' => 280, 'imageUrl' => 'images\\Plastic Bucket Large.jpg', 'description' => '20 liter large plastic bucket.'],
    ['id' => 13, 'name' => 'Plastic Mug', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Mug.jpg', 'description' => 'Plastic mug.'],
    ['id' => 14, 'name' => 'Plastic Bath Mug Large', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Bath Mug Large.jpg', 'description' => 'Large plastic bath mug.'],
    ['id' => 15, 'name' => 'Plastic Basin', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Basin.jpg', 'description' => 'Plastic basin.'],
    ['id' => 16, 'name' => 'Plastic Laundry Basket', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Laundry Basket.jpg', 'description' => 'Plastic laundry basket.'],
    ['id' => 17, 'name' => 'Plastic Laundry Tub', 'size' => '25 L', 'price' => 320, 'imageUrl' => 'images\\Plastic Laundry Tub.jpg', 'description' => '25 liter plastic laundry tub.'],
    ['id' => 18, 'name' => 'Plastic Dish Rack', 'size' => '1 pc', 'price' => 380, 'imageUrl' => 'images\\Plastic Dish Rack.jpg', 'description' => 'Plastic dish rack.'],
    ['id' => 19, 'name' => 'Plastic Plate', 'size' => '1 pc', 'price' => 40, 'imageUrl' => 'images\\Plastic Plate.jpg', 'description' => 'Plastic plate.'],
    ['id' => 20, 'name' => 'Plastic Plate Set', 'size' => '6 pcs', 'price' => 220, 'imageUrl' => 'images\\Plastic Plate Set.jpg', 'description' => 'Set of 6 plastic plates.'],
    ['id' => 21, 'name' => 'Plastic Bowl', 'size' => '1 pc', 'price' => 35, 'imageUrl' => 'images\\Plastic Bowl.jpg', 'description' => 'Plastic bowl.'],
    ['id' => 22, 'name' => 'Plastic Bowl Set', 'size' => '6 pcs', 'price' => 180, 'imageUrl' => 'images\\Plastic Bowl Set.jpg', 'description' => 'Set of 6 plastic bowls.'],
    ['id' => 23, 'name' => 'Plastic Glass', 'size' => '1 pc', 'price' => 30, 'imageUrl' => 'images\\Plastic Glass.jpg', 'description' => 'Plastic glass.'],
    ['id' => 24, 'name' => 'Plastic Glass Set', 'size' => '6 pcs', 'price' => 160, 'imageUrl' => 'images\\Plastic Glass Set.jpg', 'description' => 'Set of 6 plastic glasses.'],
    ['id' => 25, 'name' => 'Plastic Jug', 'size' => '2 L', 'price' => 120, 'imageUrl' => 'images\\Plastic Jug.jpg', 'description' => '2 liter plastic jug.'],
    ['id' => 26, 'name' => 'Plastic Water Jug Large', 'size' => '4 L', 'price' => 180, 'imageUrl' => 'images\\Plastic Water Jug Large.jpg', 'description' => '4 liter large plastic water jug.'],
    ['id' => 27, 'name' => 'Plastic Serving Tray', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Serving Tray.jpg', 'description' => 'Plastic serving tray.'],
    ['id' => 28, 'name' => 'Plastic Serving Tray Set', 'size' => '2 pcs', 'price' => 260, 'imageUrl' => 'images\\Plastic Serving Tray Set.jpg', 'description' => 'Set of 2 plastic serving trays.'],
    ['id' => 29, 'name' => 'Plastic Dustbin Small', 'size' => '10 L', 'price' => 160, 'imageUrl' => 'images\\Plastic Dustbin Small.jpg', 'description' => '10 liter small plastic dustbin.'],
    ['id' => 30, 'name' => 'Plastic Dustbin Medium', 'size' => '20 L', 'price' => 240, 'imageUrl' => 'images\\Plastic Dustbin Medium.jpg', 'description' => '20 liter medium plastic dustbin.'],
    ['id' => 31, 'name' => 'Plastic Dustbin Large', 'size' => '40 L', 'price' => 420, 'imageUrl' => 'images\\Plastic Dustbin Large.jpg', 'description' => '40 liter large plastic dustbin.'],
    ['id' => 32, 'name' => 'Plastic Dustpan', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Dustpan.jpg', 'description' => 'Plastic dustpan.'],
    ['id' => 33, 'name' => 'Plastic Broom Holder', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Broom Holder.jpg', 'description' => 'Plastic broom holder.'],
    ['id' => 34, 'name' => 'Plastic Cloth Hanger', 'size' => '5 pcs', 'price' => 120, 'imageUrl' => 'images\\Plastic Cloth Hanger.jpg', 'description' => 'Set of 5 plastic cloth hangers.'],
    ['id' => 35, 'name' => 'Plastic Cloth Hanger Set', 'size' => '10 pcs', 'price' => 220, 'imageUrl' => 'images\\Plastic Cloth Hanger Set.jpg', 'description' => 'Set of 10 plastic cloth hangers.'],
    ['id' => 36, 'name' => 'Plastic Stool Small', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Stool Small.jpg', 'description' => 'Small plastic stool.'],
    ['id' => 37, 'name' => 'Plastic Stool Medium', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Stool Medium.jpg', 'description' => 'Medium plastic stool.'],
    ['id' => 38, 'name' => 'Plastic Stool Large', 'size' => '1 pc', 'price' => 420, 'imageUrl' => 'images\\Plastic Stool Large.jpg', 'description' => 'Large plastic stool.'],
    ['id' => 39, 'name' => 'Plastic Chair', 'size' => '1 pc', 'price' => 650, 'imageUrl' => 'images\\Plastic Chair.jpg', 'description' => 'Plastic chair.'],
    ['id' => 40, 'name' => 'Plastic Folding Chair', 'size' => '1 pc', 'price' => 850, 'imageUrl' => 'images\\Plastic Folding Chair.jpg', 'description' => 'Plastic folding chair.'],
    ['id' => 41, 'name' => 'Plastic Table Small', 'size' => '1 pc', 'price' => 1200, 'imageUrl' => 'images\\Plastic Table Small.jpg', 'description' => 'Small plastic table.'],
    ['id' => 42, 'name' => 'Plastic Table Medium', 'size' => '1 pc', 'price' => 1800, 'imageUrl' => 'images\\Plastic Table Medium.jpg', 'description' => 'Medium plastic table.'],
    ['id' => 43, 'name' => 'Plastic Shoe Rack', 'size' => '1 pc', 'price' => 750, 'imageUrl' => 'images\\Plastic Shoe Rack.jpg', 'description' => 'Plastic shoe rack.'],
    ['id' => 44, 'name' => 'Plastic Drawer Storage', 'size' => '3 layer', 'price' => 950, 'imageUrl' => 'images\\Plastic Drawer Storage.jpg', 'description' => '3-layer plastic drawer storage.'],
    ['id' => 45, 'name' => 'Plastic Drawer Storage Large', 'size' => '4 layer', 'price' => 1200, 'imageUrl' => 'images\\Plastic Drawer Storage Large.jpg', 'description' => '4-layer large plastic drawer storage.'],
    ['id' => 46, 'name' => 'Plastic Cutlery Stand', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Cutlery Stand.jpg', 'description' => 'Plastic cutlery stand.'],
    ['id' => 47, 'name' => 'Plastic Dish Drainer', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Dish Drainer.jpg', 'description' => 'Plastic dish drainer.'],
    ['id' => 48, 'name' => 'Plastic Sink Organizer', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Sink Organizer.jpg', 'description' => 'Plastic sink organizer.'],
    ['id' => 49, 'name' => 'Plastic Soap Box', 'size' => '1 pc', 'price' => 40, 'imageUrl' => 'images\\Plastic Soap Box.jpg', 'description' => 'Plastic soap box.'],
    ['id' => 50, 'name' => 'Plastic Soap Case Travel', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Soap Case Travel.jpg', 'description' => 'Travel plastic soap case.'],
    ['id' => 51, 'name' => 'Plastic Toothbrush Holder', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Toothbrush Holder.jpg', 'description' => 'Plastic toothbrush holder.'],
    ['id' => 52, 'name' => 'Plastic Bathroom Shelf', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Bathroom Shelf.jpg', 'description' => 'Plastic bathroom shelf.'],
    ['id' => 53, 'name' => 'Plastic Bathroom Corner Rack', 'size' => '1 pc', 'price' => 420, 'imageUrl' => 'images\\Plastic Bathroom Corner Rack.jpg', 'description' => 'Plastic bathroom corner rack.'],
    ['id' => 54, 'name' => 'Plastic Tumbler', 'size' => '1 pc', 'price' => 35, 'imageUrl' => 'images\\Plastic Tumbler.jpg', 'description' => 'Plastic tumbler.'],
    ['id' => 55, 'name' => 'Plastic Water Dispenser', 'size' => '10 L', 'price' => 650, 'imageUrl' => 'images\\Plastic Water Dispenser.jpg', 'description' => '10 liter plastic water dispenser.'],
    ['id' => 56, 'name' => 'Plastic Vegetable Basket', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Vegetable Basket.jpg', 'description' => 'Plastic vegetable basket.'],
    ['id' => 57, 'name' => 'Plastic Fruit Basket', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Fruit Basket.jpg', 'description' => 'Plastic fruit basket.'],
    ['id' => 58, 'name' => 'Plastic Kitchen Rack', 'size' => '1 pc', 'price' => 580, 'imageUrl' => 'images\\Plastic Kitchen Rack.jpg', 'description' => 'Plastic kitchen rack.'],
    ['id' => 59, 'name' => 'Plastic Cutting Board', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Cutting Board.jpg', 'description' => 'Plastic cutting board.'],
    ['id' => 60, 'name' => 'Plastic Rolling Pin', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Rolling Pin.jpg', 'description' => 'Plastic rolling pin.'],
    ['id' => 61, 'name' => 'Plastic Flour Container', 'size' => '5 kg', 'price' => 280, 'imageUrl' => 'images\\Plastic Flour Container.jpg', 'description' => '5kg plastic flour container.'],
    ['id' => 62, 'name' => 'Plastic Sugar Container', 'size' => '2 kg', 'price' => 140, 'imageUrl' => 'images\\Plastic Sugar Container.jpg', 'description' => '2kg plastic sugar container.'],
    ['id' => 63, 'name' => 'Plastic Salt Box', 'size' => '500 g', 'price' => 90, 'imageUrl' => 'images\\Plastic Salt Box.jpg', 'description' => '500g plastic salt box.'],
    ['id' => 64, 'name' => 'Plastic Oil Bottle', 'size' => '1 L', 'price' => 120, 'imageUrl' => 'images\\Plastic Oil Bottle.jpg', 'description' => '1 liter plastic oil bottle.'],
    ['id' => 65, 'name' => 'Plastic Pickle Jar', 'size' => '2 L', 'price' => 180, 'imageUrl' => 'images\\Plastic Pickle Jar.jpg', 'description' => '2 liter plastic pickle jar.'],
    ['id' => 66, 'name' => 'Plastic Ice Tray', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Ice Tray.jpg', 'description' => 'Plastic ice tray.'],
    ['id' => 67, 'name' => 'Plastic Ice Bucket', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Ice Bucket.jpg', 'description' => 'Plastic ice bucket.'],
    ['id' => 68, 'name' => 'Plastic Lunch Carrier', 'size' => '3 tier', 'price' => 280, 'imageUrl' => 'images\\Plastic Lunch Carrier.jpg', 'description' => '3-tier plastic lunch carrier.'],
    ['id' => 69, 'name' => 'Plastic Tiffin Box', 'size' => '1 set', 'price' => 220, 'imageUrl' => 'images\\Plastic Tiffin Box.jpg', 'description' => 'Plastic tiffin box set.'],
    ['id' => 70, 'name' => 'Plastic Water Can', 'size' => '20 L', 'price' => 420, 'imageUrl' => 'images\\Plastic Water Can.jpg', 'description' => '20 liter plastic water can.'],
    ['id' => 71, 'name' => 'Plastic Plant Pot', 'size' => '1 pc', 'price' => 90, 'imageUrl' => 'images\\Plastic Plant Pot.jpg', 'description' => 'Plastic plant pot.'],
    ['id' => 72, 'name' => 'Plastic Flower Pot Large', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Flower Pot Large.jpg', 'description' => 'Large plastic flower pot.'],
    ['id' => 73, 'name' => 'Plastic Garden Watering Can', 'size' => '5 L', 'price' => 220, 'imageUrl' => 'images\\Plastic Garden Watering Can.jpg', 'description' => '5 liter plastic watering can.'],
    ['id' => 74, 'name' => 'Plastic Storage Box Large', 'size' => '40 L', 'price' => 520, 'imageUrl' => 'images\\Plastic Storage Box Large.jpg', 'description' => '40 liter large plastic storage box.'],
    ['id' => 75, 'name' => 'Plastic Storage Box Extra Large', 'size' => '60 L', 'price' => 720, 'imageUrl' => 'images\\Plastic Storage Box Extra Large 1600.jpg', 'description' => '60 liter extra large plastic storage box.'],
    ['id' => 76, 'name' => 'Plastic Toy Basket', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Toy Basket.jpg', 'description' => 'Plastic toy basket.'],
    ['id' => 77, 'name' => 'Plastic Laundry Bag', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Laundry Bag.jpg', 'description' => 'Plastic laundry bag.'],
    ['id' => 78, 'name' => 'Plastic Cloth Clips', 'size' => '20 pcs', 'price' => 60, 'imageUrl' => 'images\\Plastic Cloth Clips.jpg', 'description' => 'Pack of 20 plastic cloth clips.'],
    ['id' => 79, 'name' => 'Plastic Cloth Clips Large', 'size' => '20 pcs', 'price' => 80, 'imageUrl' => 'images\\Plastic Cloth Clips Large.jpg', 'description' => 'Pack of 20 large plastic cloth clips.'],
    ['id' => 80, 'name' => 'Plastic Rope Clothesline', 'size' => '10 m', 'price' => 120, 'imageUrl' => 'images\\Plastic Rope Clothesline.jpg', 'description' => '10 meter plastic clothesline rope.'],
    ['id' => 81, 'name' => 'Plastic Dish Scrubber Holder', 'size' => '1 pc', 'price' => 70, 'imageUrl' => 'images\\Plastic Dish Scrubber Holder.jpg', 'description' => 'Plastic dish scrubber holder.'],
    ['id' => 82, 'name' => 'Plastic Kitchen Basket', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Kitchen Basket.jpg', 'description' => 'Plastic kitchen basket.'],
    ['id' => 83, 'name' => 'Plastic Egg Tray', 'size' => '12 eggs', 'price' => 80, 'imageUrl' => 'images\\Plastic Egg Tray.jpg', 'description' => '12-egg plastic tray.'],
    ['id' => 84, 'name' => 'Plastic Egg Storage Box', 'size' => '24 eggs', 'price' => 140, 'imageUrl' => 'images\\Plastic Egg Storage Box.jpg', 'description' => '24-egg plastic storage box.'],
    ['id' => 85, 'name' => 'Plastic Rice Washing Bowl', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Rice Washing Bowl.jpg', 'description' => 'Plastic rice washing bowl.'],
    ['id' => 86, 'name' => 'Plastic Colander', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Colander.jpg', 'description' => 'Plastic colander.'],
    ['id' => 87, 'name' => 'Plastic Funnel', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Funnel.jpg', 'description' => 'Plastic funnel.'],
    ['id' => 88, 'name' => 'Plastic Measuring Cup', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Measuring Cup.jpg', 'description' => 'Plastic measuring cup.'],
    ['id' => 89, 'name' => 'Plastic Measuring Spoon Set', 'size' => '1 set', 'price' => 120, 'imageUrl' => 'images\\Plastic Measuring Spoon Set.jpg', 'description' => 'Set of plastic measuring spoons.'],
    ['id' => 90, 'name' => 'Plastic Ladle', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Ladle.jpg', 'description' => 'Plastic ladle.'],
    ['id' => 91, 'name' => 'Plastic Spatula', 'size' => '1 pc', 'price' => 70, 'imageUrl' => 'images\\Plastic Spatula.jpg', 'description' => 'Plastic spatula.'],
    ['id' => 92, 'name' => 'Plastic Cooking Spoon', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Cooking Spoon.jpg', 'description' => 'Plastic cooking spoon.'],
    ['id' => 93, 'name' => 'Plastic Serving Spoon', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Serving Spoon.jpg', 'description' => 'Plastic serving spoon.'],
    ['id' => 94, 'name' => 'Plastic Storage Jar Set', 'size' => '3 pcs', 'price' => 220, 'imageUrl' => 'images\\Plastic Storage Jar Set.jpg', 'description' => 'Set of 3 plastic storage jars.'],
    ['id' => 95, 'name' => 'Plastic Airtight Container', 'size' => '1 L', 'price' => 120, 'imageUrl' => 'images\\Plastic Airtight Container.jpg', 'description' => '1 liter plastic airtight container.'],
    ['id' => 96, 'name' => 'Plastic Airtight Container Set', 'size' => '4 pcs', 'price' => 360, 'imageUrl' => 'images\\Plastic Airtight Container Set.jpg', 'description' => 'Set of 4 plastic airtight containers.'],
    ['id' => 97, 'name' => 'Plastic Kids Plate', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Kids Plate.jpg', 'description' => 'Kids plastic plate.'],
    ['id' => 98, 'name' => 'Plastic Kids Bowl', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Kids Bowl.jpg', 'description' => 'Kids plastic bowl.'],
    ['id' => 99, 'name' => 'Plastic Kids Cup', 'size' => '1 pc', 'price' => 50, 'imageUrl' => 'images\\Plastic Kids Cup.jpg', 'description' => 'Kids plastic cup.'],
    ['id' => 100, 'name' => 'Plastic Kids Lunch Box', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Kids Lunch Box.jpg', 'description' => 'Kids plastic lunch box.'],
    ['id' => 101, 'name' => 'Plastic Baby Feeding Bowl', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Baby Feeding Bowl.jpg', 'description' => 'Baby plastic feeding bowl.'],
    ['id' => 102, 'name' => 'Plastic Baby Spoon Set', 'size' => '2 pcs', 'price' => 90, 'imageUrl' => 'images\\Plastic Baby Spoon Set.jpg', 'description' => 'Set of 2 plastic baby spoons.'],
    ['id' => 103, 'name' => 'Plastic Baby Bottle', 'size' => '250 ml', 'price' => 160, 'imageUrl' => 'images\\Plastic Baby Bottle.jpg', 'description' => '250ml plastic baby bottle.'],
    ['id' => 104, 'name' => 'Plastic Baby Bottle Large', 'size' => '350 ml', 'price' => 220, 'imageUrl' => 'images\\Plastic Baby Bottle Large.jpg', 'description' => '350ml large plastic baby bottle.'],
    ['id' => 105, 'name' => 'Plastic Milk Bottle', 'size' => '1 L', 'price' => 120, 'imageUrl' => 'images\\Plastic Milk Bottle.jpg', 'description' => '1 liter plastic milk bottle.'],
    ['id' => 106, 'name' => 'Plastic Storage Basket', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Storage Basket.jpg', 'description' => 'Plastic storage basket.'],
    ['id' => 107, 'name' => 'Plastic Organizer Box', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Organizer Box.jpg', 'description' => 'Plastic organizer box.'],
    ['id' => 108, 'name' => 'Plastic Drawer Divider', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Drawer Divider.jpg', 'description' => 'Plastic drawer divider.'],
    ['id' => 109, 'name' => 'Plastic Cable Organizer', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Cable Organizer.jpg', 'description' => 'Plastic cable organizer.'],
    ['id' => 110, 'name' => 'Plastic Mobile Holder', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Mobile Holder.jpg', 'description' => 'Plastic mobile holder.'],
    ['id' => 111, 'name' => 'Plastic Remote Holder', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Remote Holder.jpg', 'description' => 'Plastic remote holder.'],
    ['id' => 112, 'name' => 'Plastic Key Holder', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Key Holder.jpg', 'description' => 'Plastic key holder.'],
    ['id' => 113, 'name' => 'Plastic Wall Shelf', 'size' => '1 pc', 'price' => 380, 'imageUrl' => 'images\\Plastic Wall Shelf.jpg', 'description' => 'Plastic wall shelf.'],
    ['id' => 114, 'name' => 'Plastic Storage Bin', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Storage Bin.jpg', 'description' => 'Plastic storage bin.'],
    ['id' => 115, 'name' => 'Plastic Waste Basket', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Waste Basket.jpg', 'description' => 'Plastic waste basket.'],
    ['id' => 116, 'name' => 'Plastic Pedal Dustbin', 'size' => '20 L', 'price' => 420, 'imageUrl' => 'images\\Plastic Pedal Dustbin.jpg', 'description' => '20 liter plastic pedal dustbin.'],
    ['id' => 117, 'name' => 'Plastic Pedal Dustbin Large', 'size' => '40 L', 'price' => 720, 'imageUrl' => 'images\\Plastic Pedal Dustbin Large.jpg', 'description' => '40 liter large plastic pedal dustbin.'],
    ['id' => 118, 'name' => 'Plastic Mop Bucket', 'size' => '1 pc', 'price' => 520, 'imageUrl' => 'images\\Plastic Mop Bucket.jpg', 'description' => 'Plastic mop bucket.'],
    ['id' => 119, 'name' => 'Plastic Cleaning Caddy', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Cleaning Caddy.jpg', 'description' => 'Plastic cleaning caddy.'],
    ['id' => 120, 'name' => 'Plastic Toilet Brush Set', 'size' => '1 set', 'price' => 180, 'imageUrl' => 'images\\Plastic Toilet Brush Set.jpg', 'description' => 'Plastic toilet brush set.'],
    ['id' => 121, 'name' => 'Plastic Sink Basket', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Sink Basket.jpg', 'description' => 'Plastic sink basket.'],
    ['id' => 122, 'name' => 'Plastic Soap Dispenser', 'size' => '500 ml', 'price' => 180, 'imageUrl' => 'images\\Plastic Soap Dispenser.jpg', 'description' => '500ml plastic soap dispenser.'],
    ['id' => 123, 'name' => 'Plastic Toothpaste Squeezer', 'size' => '1 pc', 'price' => 70, 'imageUrl' => 'images\\Plastic Toothpaste Squeezer.jpg', 'description' => 'Plastic toothpaste squeezer.'],
    ['id' => 124, 'name' => 'Plastic Bathroom Organizer', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Bathroom Organizer.jpg', 'description' => 'Plastic bathroom organizer.'],
    ['id' => 125, 'name' => 'Plastic Corner Storage Rack', 'size' => '3 layer', 'price' => 420, 'imageUrl' => 'images\\Plastic Corner Storage Rack.jpg', 'description' => '3-layer plastic corner storage rack.'],
    ['id' => 126, 'name' => 'Plastic Shower Caddy', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Shower Caddy.jpg', 'description' => 'Plastic shower caddy.'],
    ['id' => 127, 'name' => 'Plastic Towel Hanger', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Towel Hanger.jpg', 'description' => 'Plastic towel hanger.'],
    ['id' => 128, 'name' => 'Plastic Tissue Box', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Tissue Box.jpg', 'description' => 'Plastic tissue box.'],
    ['id' => 129, 'name' => 'Plastic Napkin Holder', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Napkin Holder.jpg', 'description' => 'Plastic napkin holder.'],
    ['id' => 130, 'name' => 'Plastic Food Cover', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Food Cover.jpg', 'description' => 'Plastic food cover.'],
    ['id' => 131, 'name' => 'Plastic Cake Container', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Cake Container.jpg', 'description' => 'Plastic cake container.'],
    ['id' => 132, 'name' => 'Plastic Bread Box', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Bread Box.jpg', 'description' => 'Plastic bread box.'],
    ['id' => 133, 'name' => 'Plastic Snack Container', 'size' => '1 L', 'price' => 140, 'imageUrl' => 'images\\Plastic Snack Container.jpg', 'description' => '1 liter plastic snack container.'],
    ['id' => 134, 'name' => 'Plastic Tea Container', 'size' => '1 L', 'price' => 160, 'imageUrl' => 'images\\Plastic Tea Container.jpg', 'description' => '1 liter plastic tea container.'],
    ['id' => 135, 'name' => 'Plastic Coffee Container', 'size' => '1 L', 'price' => 180, 'imageUrl' => 'images\\Plastic Coffee Container.jpg', 'description' => '1 liter plastic coffee container.'],
    ['id' => 136, 'name' => 'Plastic Biscuit Jar', 'size' => '2 L', 'price' => 220, 'imageUrl' => 'images\\Plastic Biscuit Jar.jpg', 'description' => '2 liter plastic biscuit jar.'],
    ['id' => 137, 'name' => 'Plastic Candy Jar', 'size' => '1.5 L', 'price' => 200, 'imageUrl' => 'images\\Plastic Candy Jar.jpg', 'description' => '1.5 liter plastic candy jar.'],
    ['id' => 138, 'name' => 'Plastic Airtight Snack Box', 'size' => '1 L', 'price' => 160, 'imageUrl' => 'images\\Plastic Airtight Snack Box.jpg', 'description' => '1 liter plastic airtight snack box.'],
    ['id' => 139, 'name' => 'Plastic Dry Fruit Container', 'size' => '750 g', 'price' => 140, 'imageUrl' => 'images\\Plastic Dry Fruit Container.jpg', 'description' => '750g plastic dry fruit container.'],
    ['id' => 140, 'name' => 'Plastic Salad Bowl', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Salad Bowl.jpg', 'description' => 'Plastic salad bowl.'],
    ['id' => 141, 'name' => 'Plastic Serving Bowl Large', 'size' => '2 L', 'price' => 220, 'imageUrl' => 'images\\Plastic Serving Bowl Large.jpg', 'description' => '2 liter large plastic serving bowl.'],
    ['id' => 142, 'name' => 'Plastic Serving Plate', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Serving Plate.jpg', 'description' => 'Plastic serving plate.'],
    ['id' => 143, 'name' => 'Plastic Party Plate Set', 'size' => '6 pcs', 'price' => 260, 'imageUrl' => 'images\\Plastic Party Plate Set.jpg', 'description' => 'Set of 6 plastic party plates.'],
    ['id' => 144, 'name' => 'Plastic Party Cup Set', 'size' => '6 pcs', 'price' => 220, 'imageUrl' => 'images\\Plastic Party Cup Set.jpg', 'description' => 'Set of 6 plastic party cups.'],
    ['id' => 145, 'name' => 'Plastic Picnic Basket', 'size' => '1 pc', 'price' => 420, 'imageUrl' => 'images\\Plastic Picnic Basket.jpg', 'description' => 'Plastic picnic basket.'],
    ['id' => 146, 'name' => 'Plastic Picnic Plate Set', 'size' => '8 pcs', 'price' => 320, 'imageUrl' => 'images\\Plastic Picnic Plate Set.jpg', 'description' => 'Set of 8 plastic picnic plates.'],
    ['id' => 147, 'name' => 'Plastic Picnic Lunch Box', 'size' => '1 set', 'price' => 380, 'imageUrl' => 'images\\Plastic Picnic Lunch Box.jpg', 'description' => 'Plastic picnic lunch box set.'],
    ['id' => 148, 'name' => 'Plastic Travel Water Bottle', 'size' => '750 ml', 'price' => 140, 'imageUrl' => 'images\\Plastic Travel Water Bottle.jpg', 'description' => '750ml plastic travel water bottle.'],
    ['id' => 149, 'name' => 'Plastic Travel Mug', 'size' => '350 ml', 'price' => 180, 'imageUrl' => 'images\\Plastic Travel Mug.jpg', 'description' => '350ml plastic travel mug.'],
    ['id' => 150, 'name' => 'Plastic Travel Food Container', 'size' => '1 pc', 'price' => 200, 'imageUrl' => 'images\\Plastic Travel Food Container.jpg', 'description' => 'Plastic travel food container.'],
    ['id' => 151, 'name' => 'Plastic Cosmetic Organizer', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Cosmetic Organizer.jpg', 'description' => 'Plastic cosmetic organizer.'],
    ['id' => 152, 'name' => 'Plastic Makeup Box', 'size' => '1 pc', 'price' => 420, 'imageUrl' => 'images\\Plastic Makeup Box.jpg', 'description' => 'Plastic makeup box.'],
    ['id' => 153, 'name' => 'Plastic Jewelry Organizer', 'size' => '1 pc', 'price' => 360, 'imageUrl' => 'images\\Plastic Jewelry Organizer.jpg', 'description' => 'Plastic jewelry organizer.'],
    ['id' => 154, 'name' => 'Plastic Hair Accessories Box', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Hair Accessories Box.jpg', 'description' => 'Plastic hair accessories box.'],
    ['id' => 155, 'name' => 'Plastic Sewing Box', 'size' => '1 pc', 'price' => 240, 'imageUrl' => 'images\\Plastic Sewing Box.jpg', 'description' => 'Plastic sewing box.'],
    ['id' => 156, 'name' => 'Plastic Medicine Box', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Medicine Box.jpg', 'description' => 'Plastic medicine box.'],
    ['id' => 157, 'name' => 'Plastic First Aid Box', 'size' => '1 pc', 'price' => 280, 'imageUrl' => 'images\\Plastic First Aid Box.jpg', 'description' => 'Plastic first aid box.'],
    ['id' => 158, 'name' => 'Plastic Toolbox', 'size' => '1 pc', 'price' => 420, 'imageUrl' => 'images\\Plastic Toolbox.jpg', 'description' => 'Plastic toolbox.'],
    ['id' => 159, 'name' => 'Plastic Storage Crate', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Storage Crate.jpg', 'description' => 'Plastic storage crate.'],
    ['id' => 160, 'name' => 'Plastic File Storage Box', 'size' => '1 pc', 'price' => 360, 'imageUrl' => 'images\\Plastic File Storage Box.jpg', 'description' => 'Plastic file storage box.'],
    ['id' => 161, 'name' => 'Plastic Document Organizer', 'size' => '1 pc', 'price' => 280, 'imageUrl' => 'images\\Plastic Document Organizer.jpg', 'description' => 'Plastic document organizer.'],
    ['id' => 162, 'name' => 'Plastic Pen Holder', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Pen Holder.jpg', 'description' => 'Plastic pen holder.'],
    ['id' => 163, 'name' => 'Plastic Desk Organizer', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Desk Organizer.jpg', 'description' => 'Plastic desk organizer.'],
    ['id' => 164, 'name' => 'Plastic Laptop Stand', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Laptop Stand.jpg', 'description' => 'Plastic laptop stand.'],
    ['id' => 165, 'name' => 'Plastic Tablet Stand', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Tablet Stand.jpg', 'description' => 'Plastic tablet stand.'],
    ['id' => 166, 'name' => 'Plastic Book Stand', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Book Stand.jpg', 'description' => 'Plastic book stand.'],
    ['id' => 167, 'name' => 'Plastic Magazine Holder', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Magazine Holder.jpg', 'description' => 'Plastic magazine holder.'],
    ['id' => 168, 'name' => 'Plastic Drawer Organizer', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Drawer Organizer.jpg', 'description' => 'Plastic drawer organizer.'],
    ['id' => 169, 'name' => 'Plastic Storage Drawer Unit', 'size' => '3 layer', 'price' => 780, 'imageUrl' => 'images\\Plastic Storage Drawer Unit 3800.jpg', 'description' => '3-layer plastic storage drawer unit.'],
    ['id' => 170, 'name' => 'Plastic Stackable Storage Box', 'size' => '20 L', 'price' => 420, 'imageUrl' => 'images\\Plastic Stackable Storage Box.jpg', 'description' => '20 liter plastic stackable storage box.'],
    ['id' => 171, 'name' => 'Plastic Folding Storage Box', 'size' => '25 L', 'price' => 460, 'imageUrl' => 'images\\Plastic Folding Storage Box.jpg', 'description' => '25 liter plastic folding storage box.'],
    ['id' => 172, 'name' => 'Plastic Basket With Lid', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Basket With Lid.jpg', 'description' => 'Plastic basket with lid.'],
    ['id' => 173, 'name' => 'Plastic Vegetable Storage Basket', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Vegetable Storage Basket 1900.jpg', 'description' => 'Plastic vegetable storage basket.'],
    ['id' => 174, 'name' => 'Plastic Onion Potato Basket', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Onion Potato Basket.jpg', 'description' => 'Plastic onion potato basket.'],
    ['id' => 175, 'name' => 'Plastic Refrigerator Organizer', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Refrigerator Organizer.jpg', 'description' => 'Plastic refrigerator organizer.'],
    ['id' => 176, 'name' => 'Plastic Fridge Egg Tray', 'size' => '24 eggs', 'price' => 140, 'imageUrl' => 'images\\Plastic Fridge Egg Tray.jpg', 'description' => '24-egg plastic fridge tray.'],
    ['id' => 177, 'name' => 'Plastic Fridge Bottle Holder', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Fridge Bottle Holder.jpg', 'description' => 'Plastic fridge bottle holder.'],
    ['id' => 178, 'name' => 'Plastic Freezer Container', 'size' => '1 L', 'price' => 120, 'imageUrl' => 'images\\Plastic Freezer Container.jpg', 'description' => '1 liter plastic freezer container.'],
    ['id' => 179, 'name' => 'Plastic Microwave Container', 'size' => '1 L', 'price' => 160, 'imageUrl' => 'images\\Plastic Microwave Container.jpg', 'description' => '1 liter plastic microwave container.'],
    ['id' => 180, 'name' => 'Plastic Microwave Lid Cover', 'size' => '1 pc', 'price' => 100, 'imageUrl' => 'images\\Plastic Microwave Lid Cover.jpg', 'description' => 'Plastic microwave lid cover.'],
    ['id' => 181, 'name' => 'Plastic Dish Cover', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Dish Cover.jpg', 'description' => 'Plastic dish cover.'],
    ['id' => 182, 'name' => 'Plastic Sink Dish Holder', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Sink Dish Holder.jpg', 'description' => 'Plastic sink dish holder.'],
    ['id' => 183, 'name' => 'Plastic Dish Drying Mat', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Dish Drying Mat.jpg', 'description' => 'Plastic dish drying mat.'],
    ['id' => 184, 'name' => 'Plastic Sponge Holder', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Sponge Holder.jpg', 'description' => 'Plastic sponge holder.'],
    ['id' => 185, 'name' => 'Plastic Garbage Bag Holder', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Garbage Bag Holder.jpg', 'description' => 'Plastic garbage bag holder.'],
    ['id' => 186, 'name' => 'Plastic Garbage Bin Large', 'size' => '40 L', 'price' => 520, 'imageUrl' => 'images\\Plastic Garbage Bin Large.jpg', 'description' => '40 liter large plastic garbage bin.'],
    ['id' => 187, 'name' => 'Plastic Garden Storage Box', 'size' => '60 L', 'price' => 780, 'imageUrl' => 'images\\Plastic Garden Storage Box.jpg', 'description' => '60 liter plastic garden storage box.'],
    ['id' => 188, 'name' => 'Plastic Tool Organizer Box', 'size' => '1 pc', 'price' => 380, 'imageUrl' => 'images\\Plastic Tool Organizer Box.jpg', 'description' => 'Plastic tool organizer box.'],
    ['id' => 189, 'name' => 'Plastic Hardware Organizer', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Hardware Organizer.jpg', 'description' => 'Plastic hardware organizer.'],
    ['id' => 190, 'name' => 'Plastic Nail Screw Organizer', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Nail Screw Organizer.jpg', 'description' => 'Plastic nail screw organizer.'],
    ['id' => 191, 'name' => 'Plastic Utility Basket', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Stackable Utility Basket.jpg', 'description' => 'Plastic utility basket.'],
    ['id' => 192, 'name' => 'Plastic Household Storage Tub', 'size' => '35 L', 'price' => 420, 'imageUrl' => 'images\\Plastic Household Storage Tub.jpg', 'description' => '35 liter platic household storage tub.'],
    ['id' => 193, 'name' => 'Plastic Multipurpose Basket', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Multipurpose Basket.jpg', 'description' => 'Plastic multipurpose basket.'],
    ['id' => 194, 'name' => 'Plastic Laundry Organizer', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Laundry Organizer.jpg', 'description' => 'Plastic laundry organizer.'],
    ['id' => 195, 'name' => 'Plastic Clothes Basket Large', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Clothes Basket Large.jpg', 'description' => 'Large plastic clothes basket.'],
    ['id' => 196, 'name' => 'Plastic Toy Organizer', 'size' => '1 pc', 'price' => 360, 'imageUrl' => 'images\\Plastic Toy Organizer.jpg', 'description' => 'Plastic toy organizer.'],
    ['id' => 197, 'name' => 'Plastic Kitchen Utility Rack', 'size' => '2 layer', 'price' => 420, 'imageUrl' => 'images\\Plastic Kitchen Utility Rack.jpg', 'description' => '2-layer plastic kitchen utility rack.'],
    ['id' => 198, 'name' => 'Plastic Bathroom Utility Rack', 'size' => '2 layer', 'price' => 380, 'imageUrl' => 'images\\Plastic Bathroom Utility Rack.jpg', 'description' => '2-layer plastic bathroom utility rack.'],
    ['id' => 199, 'name' => 'Plastic Multipurpose Storage Rack', 'size' => '3 layer', 'price' => 680, 'imageUrl' => 'images\\Plastic Multipurpose Storage Rack.jpg', 'description' => '3-layer plastic multipurpose storage rack.'],
    ['id' => 200, 'name' => 'Plastic Household Organizer Set', 'size' => '1 set', 'price' => 720, 'imageUrl' => 'images\\Plastic Household Organizer Set.jpg', 'description' => 'Plastic household organizer set.'],
    ['id' => 201, 'name' => 'Plastic Multi Storage Basket', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Multi Storage Basket.jpg', 'description' => 'Plastic multi storage basket.'],
    ['id' => 202, 'name' => 'Plastic Kitchen Storage Bin', 'size' => '15 L', 'price' => 260, 'imageUrl' => 'images\\Plastic Kitchen Storage Bin.jpg', 'description' => '15 liter plastic kitchen storage bin.'],
    ['id' => 203, 'name' => 'Plastic Storage Box With Wheels', 'size' => '40 L', 'price' => 520, 'imageUrl' => 'images\\Plastic Storage Box With Wheels.jpg', 'description' => '40 liter plastic storage box with wheels.'],
    ['id' => 204, 'name' => 'Plastic Rice Storage Container', 'size' => '15 kg', 'price' => 620, 'imageUrl' => 'images\\Plastic Rice Storage Container.jpg', 'description' => '15kg plastic rice storage container.'],
    ['id' => 205, 'name' => 'Plastic Atta Storage Container', 'size' => '10 kg', 'price' => 480, 'imageUrl' => 'images\\Plastic Atta Storage Container.jpg', 'description' => '10kg plastic atta storage container.'],
    ['id' => 206, 'name' => 'Plastic Grain Storage Container', 'size' => '20 L', 'price' => 560, 'imageUrl' => 'images\\Plastic Grain Storage Container.jpg', 'description' => '20 liter plastic grain storage container.'],
    ['id' => 207, 'name' => 'Plastic Airtight Food Container', 'size' => '1.5 L', 'price' => 180, 'imageUrl' => 'images\\Plastic Airtight Food Container.jpg', 'description' => '1.5 liter plastic airtight food container.'],
    ['id' => 208, 'name' => 'Plastic Airtight Food Container Set', 'size' => '4 pcs', 'price' => 420, 'imageUrl' => 'images\\Plastic Airtight Food Container Set.jpg', 'description' => 'Set of 4 plastic airtight food containers.'],
    ['id' => 209, 'name' => 'Plastic Kitchen Spoon Stand', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Kitchen Spoon Stand.jpg', 'description' => 'Plastic kitchen spoon stand.'],
    ['id' => 210, 'name' => 'Plastic Dishwashing Basket', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Dishwashing Basket.jpg', 'description' => 'Plastic dishwashing basket.'],
    ['id' => 211, 'name' => 'Plastic Vegetable Washing Basket', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Vegetable Washing Basket.jpg', 'description' => 'Plastic vegetable washing basket.'],
    ['id' => 212, 'name' => 'Plastic Fruit Washing Bowl', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Fruit Washing Bowl.jpg', 'description' => 'Plastic fruit washing bowl.'],
    ['id' => 213, 'name' => 'Plastic Sink Drainer Basket', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Sink Drainer Basket.jpg', 'description' => 'Plastic sink drainer basket.'],
    ['id' => 214, 'name' => 'Plastic Refrigerator Storage Tray', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Refrigerator Storage Tray.jpg', 'description' => 'Plastic refrigerator storage tray.'],
    ['id' => 215, 'name' => 'Plastic Fridge Organizer Box', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Fridge Organizer Box.jpg', 'description' => 'Plastic fridge organizer box.'],
    ['id' => 216, 'name' => 'Plastic Fridge Drawer Organizer', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Fridge Drawer Organizer.jpg', 'description' => 'Plastic fridge drawer organizer.'],
    ['id' => 217, 'name' => 'Plastic Bottle Organizer', 'size' => '1 pc', 'price' => 200, 'imageUrl' => 'images\\Plastic Bottle Organizer.jpg', 'description' => 'Plastic bottle organizer.'],
    ['id' => 218, 'name' => 'Plastic Water Bottle Rack', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Water Bottle Rack.jpg', 'description' => 'Plastic water bottle rack.'],
    ['id' => 219, 'name' => 'Plastic Kitchen Shelf Organizer', 'size' => '1 pc', 'price' => 280, 'imageUrl' => 'images\\Plastic Kitchen Shelf Organizer.jpg', 'description' => 'Plastic kitchen shelf organizer.'],
    ['id' => 220, 'name' => 'Plastic Under Sink Organizer', 'size' => '1 pc', 'price' => 360, 'imageUrl' => 'images\\Plastic Under Sink Organizer.jpg', 'description' => 'Plastic under sink organizer.'],
    ['id' => 221, 'name' => 'Plastic Kitchen Drawer Box', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Kitchen Drawer Box.jpg', 'description' => 'Plastic kitchen drawer box.'],
    ['id' => 222, 'name' => 'Plastic Modular Storage Box', 'size' => '20 L', 'price' => 380, 'imageUrl' => 'images\\Plastic Modular Storage Box.jpg', 'description' => '20 liter plastic modular storage box.'],
    ['id' => 223, 'name' => 'Plastic Stackable Drawer Box', 'size' => '1 pc', 'price' => 420, 'imageUrl' => 'images\\Plastic Stackable Drawer Box.jpg', 'description' => 'Plastic stackable drawer box.'],
    ['id' => 224, 'name' => 'Plastic Transparent Storage Box', 'size' => '30 L', 'price' => 460, 'imageUrl' => 'images\\Plastic Transparent Storage Box.jpg', 'description' => '30 liter plastic transparent storage box.'],
    ['id' => 225, 'name' => 'Plastic Heavy Duty Storage Box', 'size' => '60 L', 'price' => 720, 'imageUrl' => 'images\\Plastic Heavy Duty Storage Box.jpg', 'description' => '60 liter plastic heavy duty storage box.'],
    ['id' => 226, 'name' => 'Plastic Household Basket Small', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Household Basket Small.jpg', 'description' => 'Small plastic household basket.'],
    ['id' => 227, 'name' => 'Plastic Household Basket Medium', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Household Basket mediums.jpg', 'description' => 'Medium plastic household basket.'],
    ['id' => 228, 'name' => 'Plastic Household Basket Large', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Household Basket Large.jpg', 'description' => 'Large plastic household basket.'],
    ['id' => 229, 'name' => 'Plastic Clothes Organizer Box', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Clothes Organizer Box.jpg', 'description' => 'Plastic clothes organizer box.'],
    ['id' => 230, 'name' => 'Plastic Wardrobe Organizer', 'size' => '1 pc', 'price' => 360, 'imageUrl' => 'images\\Plastic Wardrobe Organizer.jpg', 'description' => 'Plastic wardrobe organizer.'],
    ['id' => 231, 'name' => 'Plastic Saree Storage Box', 'size' => '1 pc', 'price' => 420, 'imageUrl' => 'images\\Plastic Saree Storage Box.jpg', 'description' => 'Plastic saree storage box.'],
    ['id' => 232, 'name' => 'Plastic Blanket Storage Box', 'size' => '1 pc', 'price' => 480, 'imageUrl' => 'images\\Plastic Blanket Storage Box.jpg', 'description' => 'Plastic blanket storage box.'],
    ['id' => 233, 'name' => 'Plastic Shoe Storage Box', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Shoe Storage Box.jpg', 'description' => 'Plastic shoe storage box.'],
    ['id' => 234, 'name' => 'Plastic Shoe Rack Organizer', 'size' => '1 pc', 'price' => 580, 'imageUrl' => 'images\\Plastic Shoe Rack Organizer.jpg', 'description' => 'Plastic shoe rack organizer.'],
    ['id' => 235, 'name' => 'Plastic Shoe Cleaning Brush', 'size' => '1 pc', 'price' => 90, 'imageUrl' => 'images\\Plastic Shoe Cleaning Brush.jpg', 'description' => 'Plastic shoe cleaning brush.'],
    ['id' => 236, 'name' => 'Plastic Bathroom Bucket Set', 'size' => '1 set', 'price' => 420, 'imageUrl' => 'images\\Plastic Bathroom Bucket Set.jpg', 'description' => 'Plastic bathroom bucket set.'],
    ['id' => 237, 'name' => 'Plastic Bathroom Mug Set', 'size' => '2 pcs', 'price' => 120, 'imageUrl' => 'images\\Plastic Bathroom Mug Set.jpg', 'description' => 'Set of 2 plastic bathroom mugs.'],
    ['id' => 238, 'name' => 'Plastic Bathroom Storage Basket', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Bathroom Storage Basket.jpg', 'description' => 'Plastic bathroom storage basket.'],
    ['id' => 239, 'name' => 'Plastic Bathroom Shelf Rack', 'size' => '2 layer', 'price' => 360, 'imageUrl' => 'images\\Plastic Bathroom Shelf Rack.jpg', 'description' => '2-layer plastic bathroom shelf rack.'],
    ['id' => 240, 'name' => 'Plastic Bathroom Corner Shelf', 'size' => '3 layer', 'price' => 420, 'imageUrl' => 'images\\Plastic Bathroom Corner Shelf.jpg', 'description' => '3-layer plastic bathroom corner shelf.'],
    ['id' => 241, 'name' => 'Plastic Bathroom Toothbrush Cup', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Bathroom Toothbrush Cup.jpg', 'description' => 'Plastic bathroom toothbrush cup.'],
    ['id' => 242, 'name' => 'Plastic Bathroom Soap Stand', 'size' => '1 pc', 'price' => 80, 'imageUrl' => 'images\\Plastic Bathroom Soap Stand.jpg', 'description' => 'Plastic bathroom soap stand.'],
    ['id' => 243, 'name' => 'Plastic Bathroom Soap Tray', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Bathroom Soap Tray.jpg', 'description' => 'Plastic bathroom soap tray.'],
    ['id' => 244, 'name' => 'Plastic Bathroom Shampoo Holder', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Bathroom Shampoo Holder.jpg', 'description' => 'Plastic bathroom shampoo holder.'],
    ['id' => 245, 'name' => 'Plastic Bathroom Razor Holder', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Bathroom Razor Holder.jpg', 'description' => 'Plastic bathroom razor holder.'],
    ['id' => 246, 'name' => 'Plastic Cleaning Brush', 'size' => '1 pc', 'price' => 90, 'imageUrl' => 'images\\Plastic Cleaning Brush.jpg', 'description' => 'Plastic cleaning brush.'],
    ['id' => 247, 'name' => 'Plastic Floor Scrub Brush', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Floor Scrub Brush.jpg', 'description' => 'Plastic floor scrub brush.'],
    ['id' => 248, 'name' => 'Plastic Toilet Cleaning Brush', 'size' => '1 set', 'price' => 180, 'imageUrl' => 'images\\Plastic Toilet Cleaning Brush.jpg', 'description' => 'Plastic toilet cleaning brush set.'],
    ['id' => 249, 'name' => 'Plastic Floor Wiper', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Floor Wiper.jpg', 'description' => 'Plastic floor wiper.'],
    ['id' => 250, 'name' => 'Plastic Hand Scrubber', 'size' => '1 pc', 'price' => 60, 'imageUrl' => 'images\\Plastic Hand Scrubber.jpg', 'description' => 'Plastic hand scrubber.'],
    ['id' => 251, 'name' => 'Plastic Mop Bucket With Wringer', 'size' => '20 L', 'price' => 820, 'imageUrl' => 'images\\Plastic Mop Bucket With Wringer.jpg', 'description' => '20 liter plastic mop bucket with wringer.'],
    ['id' => 252, 'name' => 'Plastic Mop Handle Holder', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Mop Handle Holder.jpg', 'description' => 'Plastic mop handle holder.'],
    ['id' => 253, 'name' => 'Plastic Cleaning Storage Caddy', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Cleaning Storage Caddy.jpg', 'description' => 'Plastic cleaning storage caddy.'],
    ['id' => 254, 'name' => 'Plastic Garbage Dustbin Small', 'size' => '12 L', 'price' => 180, 'imageUrl' => 'images\\Plastic Pedal Dustbin.jpg', 'description' => '12 liter small plastic garbage dustbin.'],
    ['id' => 256, 'name' => 'Plastic Pedal Dustbin Large', 'size' => '30 L', 'price' => 380, 'imageUrl' => 'images\\Plastic Pedal Dustbin Large.jpg', 'description' => '30 liter plastic garbage dustbin with lid.'],
    ['id' => 259, 'name' => 'Plastic Garden Tool Box', 'size' => '1 pc', 'price' => 360, 'imageUrl' => 'images\\Plastic Garden Tool Box.jpg', 'description' => 'Plastic garden tool box.'],
    ['id' => 260, 'name' => 'Plastic Garden Basket', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Garden Basket.jpg', 'description' => 'Plastic garden basket.'],
    ['id' => 261, 'name' => 'Plastic Garden Plant Tray', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Garden Plant Tray.jpg', 'description' => 'Plastic garden plant tray.'],
    ['id' => 262, 'name' => 'Plastic Garden Seed Tray', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Garden Seed Tray.jpg', 'description' => 'Plastic garden seed tray.'],
    ['id' => 263, 'name' => 'Plastic Garden Pot Set', 'size' => '3 pcs', 'price' => 320, 'imageUrl' => 'images\\Plastic Garden Pot Set.jpg', 'description' => 'Set of 3 plastic garden pots.'],
    ['id' => 264, 'name' => 'Plastic Hanging Plant Pot', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Hanging Plant Pot.jpg', 'description' => 'Plastic hanging plant pot.'],
    ['id' => 265, 'name' => 'Plastic Balcony Planter', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Balcony Planter.jpg', 'description' => 'Plastic balcony planter.'],
    ['id' => 266, 'name' => 'Plastic Plant Pot Stand', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Plant Pot Stand.jpg', 'description' => 'Plastic plant pot stand.'],
    ['id' => 267, 'name' => 'Plastic Garden Water Sprayer', 'size' => '1 L', 'price' => 180, 'imageUrl' => 'images\\Plastic Garden Water Sprayer.jpg', 'description' => '1 liter plastic garden water sprayer.'],
    ['id' => 268, 'name' => 'Plastic Garden Hose Reel', 'size' => '1 pc', 'price' => 520, 'imageUrl' => 'images\\Plastic Garden Hose Reel.jpg', 'description' => 'Plastic garden hose reel.'],
    ['id' => 269, 'name' => 'Plastic Water Storage Tank Small', 'size' => '50 L', 'price' => 2200, 'imageUrl' => 'images\\Plastic Water Storage Tank Small.jpg', 'description' => '50 liter small plastic water storage tank.'],
    ['id' => 270, 'name' => 'Plastic Water Storage Tank Medium', 'size' => '100 L', 'price' => 3200, 'imageUrl' => 'images\\Plastic Water Storage Tank Medium.jpg', 'description' => '100 liter medium plastic water storage tank.'],
    ['id' => 271, 'name' => 'Plastic Water Drum With Lid', 'size' => '50 L', 'price' => 1200, 'imageUrl' => 'images\\Plastic Water Drum With Lid.jpg', 'description' => '50 liter plastic water drum with lid.'],
    ['id' => 272, 'name' => 'Plastic Water Barrel', 'size' => '100 L', 'price' => 1800, 'imageUrl' => 'images\\Plastic Water Barrel.jpg', 'description' => '100 liter plastic water barrel.'],
    ['id' => 273, 'name' => 'Plastic Utility Bucket', 'size' => '15 L', 'price' => 220, 'imageUrl' => 'images\\Plastic Utility Bucket.jpg', 'description' => 'Plastic utility bucket.'],
    ['id' => 274, 'name' => 'Plastic Multi Purpose Tub', 'size' => '25 L', 'price' => 280, 'imageUrl' => 'images\\Plastic Multi Purpose Tub.jpg', 'description' => '25 liter plastic multipurpose tub.'],
    ['id' => 275, 'name' => 'Plastic Household Tub Large', 'size' => '35 L', 'price' => 340, 'imageUrl' => 'images\\Plastic Household Tub Large.jpg', 'description' => '35 liter large plastic household tub.'],
    ['id' => 276, 'name' => 'Plastic Washing Tub', 'size' => '30 L', 'price' => 320, 'imageUrl' => 'images\\Plastic Washing Tub.jpg', 'description' => '30 liter plastic washing tub.'],
    ['id' => 277, 'name' => 'Plastic Baby Bath Tub', 'size' => '1 pc', 'price' => 620, 'imageUrl' => 'images\\Plastic Baby Bath Tub.jpg', 'description' => 'Plastic baby bath tub.'],
    ['id' => 278, 'name' => 'Plastic Baby Potty Seat', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Baby Potty Seat.jpg', 'description' => 'Plastic baby potty seat.'],
    ['id' => 279, 'name' => 'Plastic Kids Storage Box', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Kids Storage Box.jpg', 'description' => 'Plastic kids storage box.'],
    ['id' => 280, 'name' => 'Plastic Kids Toy Basket', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Kids Toy Basket.jpg', 'description' => 'Plastic kids toy basket.'],
    ['id' => 281, 'name' => 'Plastic Kids Toy Storage Box', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Kids Toy Storage Box 12000.jpg', 'description' => 'Plastic kids toy storage box.'],
    ['id' => 282, 'name' => 'Plastic Kids Study Table', 'size' => '1 pc', 'price' => 980, 'imageUrl' => 'images\\Plastic Kids Study Table.jpg', 'description' => 'Plastic kids study table.'],
    ['id' => 283, 'name' => 'Plastic Kids Study Chair', 'size' => '1 pc', 'price' => 620, 'imageUrl' => 'images\\Plastic Kids Study Chair.jpg', 'description' => 'Plastic kids study chair.'],
    ['id' => 284, 'name' => 'Plastic Kids Stool', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Kids Stool.jpg', 'description' => 'Plastic kids stool.'],
    ['id' => 285, 'name' => 'Plastic Kids Water Bottle', 'size' => '500 ml', 'price' => 120, 'imageUrl' => 'images\\Plastic Kids Water Bottle.jpg', 'description' => '500ml plastic kids water bottle.'],
    ['id' => 286, 'name' => 'Plastic Kids Lunch Box', 'size' => '1 set', 'price' => 220, 'imageUrl' => 'images\\Plastic Kids Lunch Box.jpg', 'description' => 'Plastic kids lunch box set.'],
    ['id' => 287, 'name' => 'Plastic Kids Snack Box', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Kids Snack Box.jpg', 'description' => 'Plastic kids snack box.'],
    ['id' => 288, 'name' => 'Plastic Kids Plate Set', 'size' => '3 pcs', 'price' => 180, 'imageUrl' => 'images\\Plastic Kids Plate Set.jpg', 'description' => 'Set of 3 plastic kids plates.'],
    ['id' => 289, 'name' => 'Plastic Kids Cup Set', 'size' => '3 pcs', 'price' => 120, 'imageUrl' => 'images\\Plastic Kids Cup Set.jpg', 'description' => 'Set of 3 plastic kids cups.'],
    ['id' => 290, 'name' => 'Plastic Kids Food Container', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Kids Food Container.jpg', 'description' => 'Plastic kids food container.'],
    ['id' => 291, 'name' => 'Plastic Utility Storage Crate', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Utility Storage Crate.jpg', 'description' => 'Plastic utility storage crate.'],
    ['id' => 292, 'name' => 'Plastic Foldable Storage Basket', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Foldable Storage Basket.jpg', 'description' => 'Plastic foldable storage basket.'],
    ['id' => 293, 'name' => 'Plastic Stackable Utility Basket', 'size' => '1 pc', 'price' => 240, 'imageUrl' => 'images\\Plastic Stackable Utility Basket.jpg', 'description' => 'Plastic stackable utility basket.'],
    ['id' => 294, 'name' => 'Plastic Portable Storage Box', 'size' => '1 pc', 'price' => 280, 'imageUrl' => 'images\\Plastic Portable Storage Box.jpg', 'description' => 'Plastic portable storage box.'],
    ['id' => 295, 'name' => 'Plastic Household Tool Box', 'size' => '1 pc', 'price' => 360, 'imageUrl' => 'images\\Plastic Household Tool Box.jpg', 'description' => 'Plastic household tool box.'],
    ['id' => 296, 'name' => 'Plastic Hardware Storage Box', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Hardware Storage Box.jpg', 'description' => 'Plastic hardware storage box.'],
    ['id' => 297, 'name' => 'Plastic Multipurpose Organizer', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Multipurpose Organizer.jpg', 'description' => 'Plastic multipurpose organizer.'],
    ['id' => 298, 'name' => 'Plastic Modular Rack Organizer', 'size' => '3 layer', 'price' => 620, 'imageUrl' => 'images\\Plastic Modular Rack Organizer.jpg', 'description' => '3-layer plastic modular rack organizer.'],
    ['id' => 299, 'name' => 'Plastic Storage Shelf Rack', 'size' => '3 layer', 'price' => 720, 'imageUrl' => 'images\\Plastic Storage Shelf Rack.jpg', 'description' => '3-layer plastic storage shelf rack.'],
    ['id' => 300, 'name' => 'Plastic Household Storage System', 'size' => '1 set', 'price' => 1200, 'imageUrl' => 'images\\Plastic Household Storage System.jpg', 'description' => 'Plastic household storage system set.'],
    ['id' => 301, 'name' => 'Plastic Storage Basket Small', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Storage Basket Small.jpg', 'description' => 'Small plastic storage basket.'],
    ['id' => 304, 'name' => 'Plastic Laundry Basket Small', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Laundry Basket Small.jpg', 'description' => 'Small plastic laundry basket.'],
    ['id' => 310, 'name' => 'Plastic Clothes Folding Basket', 'size' => '1 pc', 'price' => 240, 'imageUrl' => 'images\\Plastic Clothes Folding Basket.jpg', 'description' => 'Plastic clothes folding basket.'],
    ['id' => 320, 'name' => 'Plastic Pantry Container Set', 'size' => '6 pcs', 'price' => 520, 'imageUrl' => 'images\\Plastic Pantry Container Set.jpg', 'description' => 'Set of 6 plastic pantry containers.'],
    ['id' => 321, 'name' => 'Plastic Airtight Container 500g', 'size' => '500 g', 'price' => 90, 'imageUrl' => 'images\\Plastic Airtight Food Container.jpg', 'description' => '500g plastic airtight container.'],
    ['id' => 322, 'name' => 'Plastic Airtight Container 1kg', 'size' => '1 kg', 'price' => 120, 'imageUrl' => 'images\\Plastic Airtight Container Set.jpg', 'description' => '1kg plastic airtight container.'],
    ['id' => 323, 'name' => 'Plastic Airtight Container 2kg', 'size' => '2 kg', 'price' => 180, 'imageUrl' => 'images\\Plastic Airtight Container 500g,1,2,3,4,5,10,15,20,25 etc.jpg', 'description' => '2kg plastic airtight container.'],
    ['id' => 324, 'name' => 'Plastic Airtight Container 5kg', 'size' => '5 kg', 'price' => 320, 'imageUrl' => 'images\\Plastic Airtight Container.jpg', 'description' => '5kg plastic airtight container.'],
    ['id' => 325, 'name' => 'Plastic Rice Container 5kg', 'size' => '5 kg', 'price' => 260, 'imageUrl' => 'images\\Plastic Airtight Container 500g,1,2,3,4,5,10,15,20,25 etc.jpg', 'description' => '5kg plastic rice container.'],
    ['id' => 326, 'name' => 'Plastic Rice Container 10kg', 'size' => '10 kg', 'price' => 380, 'imageUrl' => 'images\\Plastic Rice Storage Container.jpg', 'description' => '10kg plastic rice container.'],
    ['id' => 327, 'name' => 'Plastic Rice Container 20kg', 'size' => '20 kg', 'price' => 620, 'imageUrl' => 'images\\Plastic Airtight Container 500g,1,2,3,4,5,10,15,20,25 etc.jpg', 'description' => '20kg plastic rice container.'],
    ['id' => 328, 'name' => 'Plastic Flour Container 5kg', 'size' => '5 kg', 'price' => 260, 'imageUrl' => 'images\\Plastic Atta Storage Container.jpg', 'description' => '5kg plastic flour container.'],
    ['id' => 329, 'name' => 'Plastic Flour Container 10kg', 'size' => '10 kg', 'price' => 360, 'imageUrl' => 'images\\Plastic Airtight Container.jpg', 'description' => '10kg plastic flour container.'],
    ['id' => 330, 'name' => 'Plastic Flour Container 20kg', 'size' => '20 kg', 'price' => 580, 'imageUrl' => 'images\\Plastic Airtight Container.jpg', 'description' => '20kg plastic flour container.'],
    ['id' => 331, 'name' => 'Plastic Water Bottle 500ml', 'size' => '500 ml', 'price' => 70, 'imageUrl' => 'images\\Plastic Baby Bottle Large.jpg', 'description' => '500ml plastic water bottle.'],
    ['id' => 332, 'name' => 'Plastic Water Bottle 1L', 'size' => '1 L', 'price' => 90, 'imageUrl' => 'images\\Plastic Water Bottle 1l.jpg', 'description' => '1 liter plastic water bottle.'],
    ['id' => 333, 'name' => 'Plastic Water Bottle 2L', 'size' => '2 L', 'price' => 120, 'imageUrl' => 'images\\Plastic Water Bottle 1L.jpg', 'description' => '2 liter plastic water bottle.'],
    ['id' => 334, 'name' => 'Plastic Sports/Gym Water Bottle', 'size' => '750 ml', 'price' => 160, 'imageUrl' => 'images\\Plastic Sports Water Bottle.jpg', 'description' => '750ml plastic sports water bottle.'],
    ['id' => 336, 'name' => 'Plastic Kids Cartoon Bottle', 'size' => '500 ml', 'price' => 140, 'imageUrl' => 'images\\Plastic Kids Cartoon Bottle.jpg', 'description' => '500ml plastic kids cartoon bottle.'],
    ['id' => 337, 'name' => 'Plastic Kids Straw Bottle', 'size' => '450 ml', 'price' => 150, 'imageUrl' => 'images\\Plastic Kids Water Bottle.jpg', 'description' => '450ml plastic kids straw bottle.'],
    ['id' => 338, 'name' => 'Plastic School Water Bottle', 'size' => '600 ml', 'price' => 160, 'imageUrl' => 'images\\Plastic School Water Bottle.jpg', 'description' => '600ml plastic school water bottle.'],
    ['id' => 339, 'name' => 'Plastic Juice Bottle', 'size' => '1 L', 'price' => 120, 'imageUrl' => 'images\\Plastic Juice Bottle.jpg', 'description' => '1 liter plastic juice bottle.'],
    ['id' => 340, 'name' => 'Plastic Fridge Bottle', 'size' => '1 L', 'price' => 140, 'imageUrl' => 'images\\Plastic Fridge Bottle.jpg', 'description' => '1 liter plastic fridge bottle.'],
    ['id' => 341, 'name' => 'Plastic Fridge Bottle Set', 'size' => '6 pcs', 'price' => 520, 'imageUrl' => 'images\\Plastic Fridge Bottle Set.jpg', 'description' => 'Set of 6 plastic fridge bottles.'],
    ['id' => 342, 'name' => 'Plastic Kitchen Oil Bottle', 'size' => '1 L', 'price' => 160, 'imageUrl' => 'images\\Plastic Kitchen Oil Bottle.jpg', 'description' => '1 liter plastic kitchen oil bottle.'],
    ['id' => 343, 'name' => 'Plastic Sauce Bottle', 'size' => '500 ml', 'price' => 120, 'imageUrl' => 'images\\Plastic Sauce Bottle.jpg', 'description' => '500ml plastic sauce bottle.'],
    ['id' => 344, 'name' => 'Plastic Honey Dispenser', 'size' => '250 ml', 'price' => 160, 'imageUrl' => 'images\\Plastic Honey Dispenser.jpg', 'description' => '250ml plastic honey dispenser.'],
    ['id' => 345, 'name' => 'Plastic Vinegar Bottle', 'size' => '500 ml', 'price' => 20, 'imageUrl' => 'images\\Plastic Vinegar Bottle.jpg', 'description' => '500ml plastic vinegar bottle.'],
    ['id' => 346, 'name' => 'Plastic Pickle Container', 'size' => '2 L', 'price' => 220, 'imageUrl' => 'images\\Plastic Pickle Container.jpg', 'description' => '2 liter plastic pickle container.'],
    ['id' => 347, 'name' => 'Plastic Spice Box', 'size' => '1 set', 'price' => 280, 'imageUrl' => 'images\\Plastic Spice Box.jpg', 'description' => 'Plastic spice box set.'],
    ['id' => 348, 'name' => 'Plastic Masala Box', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Masala Box.jpg', 'description' => 'Plastic masala box.'],
    ['id' => 349, 'name' => 'Plastic Masala Container Set', 'size' => '6 pcs', 'price' => 380, 'imageUrl' => 'images\\Plastic Masala Container Set (6 pcs).jpg', 'description' => 'Set of 6 plastic masala containers.'],
    ['id' => 350, 'name' => 'Plastic Spice Rack', 'size' => '1 pc', 'price' => 420, 'imageUrl' => 'images\\Plastic Spice Rack.jpg', 'description' => 'Plastic spice rack.'],
    ['id' => 351, 'name' => 'Plastic Kitchen Tool Holder', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Kitchen Tool Holder.jpg', 'description' => 'Plastic kitchen tool holder.'],
    ['id' => 352, 'name' => 'Plastic Spoon Holder', 'size' => '1 pc', 'price' => 120, 'imageUrl' => 'images\\Plastic Spoon Holder.jpg', 'description' => 'Plastic spoon holder.'],
    ['id' => 353, 'name' => 'Plastic Knife Holder', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Knife Holder.jpg', 'description' => 'Plastic knife holder.'],
    ['id' => 354, 'name' => 'Plastic Cutlery Organizer', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Cutlery Organizer.jpg', 'description' => 'Plastic cutlery organizer.'],
    ['id' => 355, 'name' => 'Plastic Utensil Holder', 'size' => '1 pc', 'price' => 200, 'imageUrl' => 'images\\Plastic Utensil Holder.jpg', 'description' => 'Plastic utensil holder.'],
    ['id' => 357, 'name' => 'Plastic Dish Rack 2 Layer', 'size' => '1 pc', 'price' => 520, 'imageUrl' => 'images\\Plastic Dish Rack 2 Layer.jpg', 'description' => '2-layer plastic dish rack.'],
    ['id' => 365, 'name' => 'Plastic Kitchen Trolley Large', 'size' => '4 layer', 'price' => 1200, 'imageUrl' => 'images\\Plastic Kitchen Trolley Large.jpg', 'description' => '4-layer large plastic kitchen trolley.'],
    ['id' => 369, 'name' => 'Plastic Rolling Storage Cart', 'size' => '4 layer', 'price' => 1100, 'imageUrl' => 'images\\Plastic Rolling Storage Cart.jpg', 'description' => '4-layer plastic rolling storage cart.'],
    ['id' => 374, 'name' => 'Plastic Kids Toy Organizer Rack', 'size' => '4 layer', 'price' => 960, 'imageUrl' => 'images\\Plastic Kids Toy Organizer Rack.jpg', 'description' => '4-layer plastic kids toy organizer rack.'],
    ['id' => 375, 'name' => 'Plastic Kids Book Storage Rack', 'size' => '1 pc', 'price' => 720, 'imageUrl' => 'images\\Plastic Kids Book Storage Rack.jpg', 'description' => 'Plastic kids book storage rack.'],
    ['id' => 376, 'name' => 'Plastic Study Table Organizer', 'size' => '1 pc', 'price' => 220, 'imageUrl' => 'images\\Plastic Study Table Organizer.jpg', 'description' => 'Plastic study table organizer.'],
    ['id' => 377, 'name' => 'Plastic Desk Drawer Organizer', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Desk Drawer Organizer.jpg', 'description' => 'Plastic desk drawer organizer.'],
    ['id' => 378, 'name' => 'Plastic Office Storage Basket', 'size' => '1 pc', 'price' => 200, 'imageUrl' => 'images\\Plastic Office Storage Basket.jpg', 'description' => 'Plastic office storage basket.'],
    ['id' => 379, 'name' => 'Plastic File Storage Basket', 'size' => '1 pc', 'price' => 240, 'imageUrl' => 'images\\Plastic File Storage Basket.jpg', 'description' => 'Plastic file storage basket.'],
    ['id' => 380, 'name' => 'Plastic Office Desk Organizer', 'size' => '1 pc', 'price' => 260, 'imageUrl' => 'images\\Plastic Office Desk Organizer.jpg', 'description' => 'Plastic office desk organizer.'],
    ['id' => 382, 'name' => 'Plastic Cable Storage Box', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Cable Storage Box.jpg', 'description' => 'Plastic cable storage box.'],
    ['id' => 383, 'name' => 'Plastic Remote Control Organizer', 'size' => '1 pc', 'price' => 160, 'imageUrl' => 'images\\Plastic Remote Control Organizer.jpg', 'description' => 'Plastic remote control organizer.'],
    ['id' => 384, 'name' => 'Plastic Mobile Charging Stand', 'size' => '1 pc', 'price' => 140, 'imageUrl' => 'images\\Plastic Mobile Charging Stand.jpg', 'description' => 'Plastic mobile charging stand.'],
    ['id' => 385, 'name' => 'Plastic Tablet Holder Stand', 'size' => '1 pc', 'price' => 180, 'imageUrl' => 'images\\Plastic Tablet Holder Stand.jpg', 'description' => 'Plastic tablet holder stand.'],
    ['id' => 386, 'name' => 'Plastic Laptop Cooling Stand', 'size' => '1 pc', 'price' => 320, 'imageUrl' => 'images\\Plastic Laptop Cooling Stand.jpg', 'description' => 'Plastic laptop cooling stand.'],
    ['id' => 390, 'name' => 'Plastic Storage Cabinet Small', 'size' => '1 pc', 'price' => 720, 'imageUrl' => 'images\\Plastic Storage Cabinet Small.jpg', 'description' => 'Small plastic storage cabinet.'],
    ['id' => 392, 'name' => 'Plastic Storage Cabinet Large', 'size' => '1 pc', 'price' => 1500, 'imageUrl' => 'images\\Plastic Storage Cabinet Large.jpg', 'description' => 'Large plastic storage cabinet.'],
    ['id' => 393, 'name' => 'Plastic Household Rack 3 Layer', 'size' => '1 pc', 'price' => 780, 'imageUrl' => 'images\\Plastic Household Rack 5 Layer.jpg', 'description' => '3-layer plastic household rack.'],
    ['id' => 394, 'name' => 'Plastic Household Rack 4 Layer', 'size' => '1 pc', 'price' => 980, 'imageUrl' => 'images\\Plastic Household Rack 5 Layer.jpg', 'description' => '4-layer plastic household rack.'],
    ['id' => 395, 'name' => 'Plastic Household Rack 5 Layer', 'size' => '1 pc', 'price' => 1200, 'imageUrl' => 'images\\Plastic Household Rack 5 Layer.jpg', 'description' => '5-layer plastic household rack.'],
    ['id' => 396, 'name' => 'Plastic Multipurpose Household Rack', 'size' => '1 pc', 'price' => 1100, 'imageUrl' => 'images\\Plastic Multipurpose Household Rack.jpg', 'description' => 'Plastic multipurpose household rack.'],
    ['id' => 397, 'name' => 'Plastic Household Storage Rack Premium', 'size' => '1 pc', 'price' => 1400, 'imageUrl' => 'images\\Plastic Household Storage Rack Premium.jpg', 'description' => 'Premium plastic household storage rack.'],
    ['id' => 398, 'name' => 'Plastic Household Storage Rack Deluxe', 'size' => '1 pc', 'price' => 1600, 'imageUrl' => 'images\\Plastic Household Storage Rack Deluxe.jpg', 'description' => 'Deluxe plastic household storage rack.'],
    ['id' => 399, 'name' => 'Plastic Household Storage Rack Ultra', 'size' => '1 pc', 'price' => 1800, 'imageUrl' => 'images\\Plastic Household Storage Rack Ultra.jpg', 'description' => 'Ultra plastic household storage rack.'],
    ['id' => 400, 'name' => 'Plastic Household Mega Storage System', 'size' => '1 set', 'price' => 2200, 'imageUrl' => 'images\\Plastic Household Mega Storage System.jpg', 'description' => 'Plastic household mega storage system set.']
  ];
  foreach ($rawItems as $item) {
    $category = 'storage';
    $nameLower = strtolower($item['name']);
    if (strpos($nameLower, 'bath') !== false || strpos($nameLower, 'soap') !== false || strpos($nameLower, 'shower') !== false || strpos($nameLower, 'toothbrush') !== false || strpos($nameLower, 'tumbler') !== false || strpos($nameLower, 'basin') !== false || strpos($nameLower, 'mug') !== false || strpos($nameLower, 'dispenser') !== false || strpos($nameLower, 'caddy')) {
      $category = 'bathroom';
    } elseif (strpos($nameLower, 'kitchen') !== false || strpos($nameLower, 'spice') !== false || strpos($nameLower, 'container') !== false || strpos($nameLower, 'rice') !== false || strpos($nameLower, 'plate') !== false || strpos($nameLower, 'bowl') !== false || strpos($nameLower, 'glass') !== false || strpos($nameLower, 'cutting') !== false || strpos($nameLower, 'ladle') !== false || strpos($nameLower, 'spoon') !== false || strpos($nameLower, 'jar') !== false || strpos($nameLower, 'tray') !== false || strpos($nameLower, 'oil') !== false || strpos($nameLower, 'salt') !== false || strpos($nameLower, 'sugar') !== false || strpos($nameLower, 'flour')) {
      $category = 'kitchen';
    } elseif (strpos($nameLower, 'dustbin') !== false || strpos($nameLower, 'broom') !== false || strpos($nameLower, 'mop') !== false || strpos($nameLower, 'cleaning') !== false || strpos($nameLower, 'brush') !== false || strpos($nameLower, 'garbage') !== false || strpos($nameLower, 'waste') !== false || strpos($nameLower, 'dustpan') !== false || strpos($nameLower, 'scrubber')) {
      $category = 'cleaning';
    }
    $productsData[] = [
      'id' => $item['id'],
      'name' => $item['name'] . ' (' . $item['size'] . ')',
      'kn' => $item['name'] . ' ' . $item['size'] . ' - ಪ್ಲಾಸ್ಟಿಕ್',
      'category' => $category,
      'price' => $item['price'],
      'imageUrl' => $item['imageUrl'],
      'description' => $item['description'],
      'weight' => $item['size']
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
  <title>Plastic Household · ಪ್ಲಾಸ್ಟಿಕ್ ಗೃಹೋಪಯೋಗಿ</title>
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- Google Font: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
      background: #f4f0ea;
      padding: 24px 20px;
      min-height: 100vh;
    }

    #app {
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
      background: white;
      border-radius: 48px;
      box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.08);
      padding: 28px 32px 42px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
      flex-wrap: wrap;
      gap: 18px;
    }

    .shop-name h1 {
      font-size: 2.2rem;
      font-weight: 800;
      background: linear-gradient(135deg, #2f5e8c, #e68a2e);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      letter-spacing: -0.3px;
    }

    .shop-name .kannada {
      font-size: 1rem;
      color: #e68a2e;
      font-weight: 500;
      margin-top: 4px;
    }

    .cart-link {
      background: #eef3f7;
      padding: 12px 24px;
      border-radius: 60px;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      transition: all 0.2s;
      border: 1px solid #d4e2ec;
      text-decoration: none;
      color: #2f5e8c;
      font-weight: 600;
    }

    .cart-link i {
      font-size: 1.6rem;
      color: #2f5e8c;
    }

    .cart-link .cart-count {
      background: #e68a2e;
      color: white;
      font-weight: 700;
      padding: 4px 14px;
      border-radius: 40px;
      font-size: 1rem;
      margin-left: 4px;
    }

    .cart-link:active {
      transform: scale(0.97);
    }

    .nav-links {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 32px;
      background: #f8fafc;
      padding: 8px 20px;
      border-radius: 80px;
      border: 1px solid #e2edf5;
    }

    .nav-link {
      background: transparent;
      padding: 10px 24px;
      border-radius: 50px;
      font-size: 0.9rem;
      font-weight: 600;
      color: #2f5e8c;
      text-decoration: none;
      transition: 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .nav-link i {
      font-size: 1rem;
    }

    .nav-link:hover {
      background: #e6f0f5;
      color: #e68a2e;
    }

    .search-section {
      margin-bottom: 36px;
    }

    .search-box {
      display: flex;
      align-items: center;
      background: #f5f8fa;
      border-radius: 80px;
      padding: 6px 24px;
      margin-bottom: 24px;
      border: 1px solid #dee9f0;
      max-width: 500px;
      transition: 0.2s;
    }

    .search-box:focus-within {
      border-color: #e68a2e;
      box-shadow: 0 0 0 3px rgba(230, 138, 46, 0.15);
    }

    .search-box i {
      color: #e68a2e;
      font-size: 1.2rem;
      margin-right: 14px;
    }

    .search-box input {
      flex: 1;
      border: none;
      background: transparent;
      padding: 14px 0;
      font-size: 0.95rem;
      outline: none;
      color: #2c4a6e;
    }

    .filter-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .pill {
      background: #f0f4f8;
      padding: 10px 24px;
      border-radius: 60px;
      font-size: 0.9rem;
      font-weight: 600;
      color: #2f5e8c;
      cursor: pointer;
      transition: all 0.2s;
      border: 1px solid transparent;
    }

    .pill.active {
      background: #e68a2e;
      color: white;
      box-shadow: 0 6px 14px rgba(230, 138, 46, 0.25);
    }

    .pill:hover {
      background: #e2eef5;
      transform: translateY(-2px);
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 28px;
      margin: 20px 0 50px;
    }

    .product-card {
      background: white;
      border-radius: 32px;
      padding: 22px 18px 24px;
      transition: all 0.25s;
      border: 1px solid #e5eef3;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      box-shadow: 0 6px 14px rgba(0, 0, 0, 0.02);
    }

    .product-card:hover {
      transform: translateY(-6px);
      border-color: #c8dde8;
      box-shadow: 0 20px 32px -12px rgba(0, 0, 0, 0.1);
    }

    .product-img {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      background: #f5f8fc;
      margin-bottom: 16px;
      border: 3px solid #e0edf5;
      transition: transform 0.2s;
    }

    .product-card:hover .product-img {
      transform: scale(1.04);
    }

    .product-name {
      font-weight: 700;
      font-size: 1.05rem;
      color: #2c4a6e;
    }

    .product-name-kn {
      font-size: 0.75rem;
      color: #7c9bb0;
      margin: 4px 0 8px;
    }

    .product-description {
      font-size: 0.75rem;
      color: #6a8aa3;
      margin: 8px 0 12px;
      line-height: 1.4;
      background: #fafefe;
      padding: 8px 10px;
      border-radius: 24px;
      width: 100%;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      margin-top: 8px;
    }

    .price {
      font-weight: 800;
      font-size: 1.4rem;
      color: #e68a2e;
    }

    .price small {
      font-size: 0.7rem;
      font-weight: 500;
    }

    .add-btn {
      background: #e68a2e;
      border: none;
      color: white;
      padding: 10px 20px;
      border-radius: 60px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: 0.15s;
      box-shadow: 0 3px 8px rgba(230, 138, 46, 0.25);
    }

    .add-btn:active {
      transform: scale(0.96);
      background: #cc7525;
    }

    footer {
      text-align: center;
      font-size: 0.8rem;
      color: #b7cfdf;
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid #e2edf5;
    }

    @media (max-width: 720px) {
      #app {
        padding: 18px;
      }

      .product-grid {
        gap: 16px;
      }

      .product-img {
        width: 120px;
        height: 120px;
      }

      .nav-links {
        overflow-x: auto;
        white-space: nowrap;
        flex-wrap: nowrap;
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
        <div class="pill active" data-category="all">✨ All / ಎಲ್ಲ</div>
        <div class="pill" data-category="storage">📦 Storage / ಸಂಗ್ರಹ</div>
        <div class="pill" data-category="kitchen">🍳 Kitchen / ಅಡುಗೆಮನೆ</div>
        <div class="pill" data-category="bathroom">🛁 Bathroom / ಸ್ನಾನಗೃಹ</div>
        <div class="pill" data-category="cleaning">🧹 Cleaning / ಶುಚಿಗೊಳಿಸುವಿಕೆ</div>
      </div>
    </div>
    <!-- Products grid -->
    <div class="product-grid" id="productGrid"></div>
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
      // Build product objects (already have all fields)
      const products = productsData.map(p => ({
        id: p.id,
        nameEn: p.name,
        nameKn: p.kn,
        category: p.category,
        price: p.price,
        image: p.imageUrl,
        description: p.description,
        weight: p.weight
      }));
      // ----- CART STATE (localStorage, but we also sync via AJAX to server) -----
      let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
      let activeCategory = 'all';
      let searchTerm = '';
      const productGrid = document.getElementById('productGrid');
      const cartCountSpan = document.getElementById('cartCount');
      const searchInput = document.getElementById('searchInput');
      const filterPills = document.querySelectorAll('.pill');
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
        let filtered = products.filter(p => {
          if (activeCategory !== 'all' && p.category !== activeCategory) return false;
          const term = searchTerm.trim().toLowerCase();
          return term === '' || p.nameEn.toLowerCase().includes(term) || p.nameKn.toLowerCase().includes(term);
        });
        if (filtered.length === 0) {
          productGrid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:40px;">😕 No products found / ಯಾವುದೂ ಇಲ್ಲ</div>`;
          return;
        }
        productGrid.innerHTML = filtered.map(p => `
            <div class="product-card">
                <img class="product-img" src="${p.image}" alt="" loading="lazy" onerror="this.src='https://placehold.co/150x150/f5f8fc/e68a2e?text=🏠'">
                <div class="product-name">${p.nameEn}</div>
                ${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}
                <div class="product-description">📄 ${p.description}</div>
                <div class="price-row">
                    <span class="price">₹${p.price} <small>/unit</small></span>
                    <button class="add-btn" data-id="${p.id}"><i class="fas fa-cart-plus"></i> Add</button>
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
      searchInput.addEventListener('input', e => {
        searchTerm = e.target.value;
        renderProducts();
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