<?php
// ============================================================
// pickles-sauces.php - Dynamic Pickles & Sauces page
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
$categorySlug = 'pickles-sauces';
$picklesCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($picklesCategory) {
  // Fetch products from this category
  $dbProducts = getProductsByCategory($picklesCategory['id']);
  if (!empty($dbProducts)) {
    foreach ($dbProducts as $p) {
      // Determine subcategory: we can use a 'subcategory' field or derive from name
      $subcategory = $p['subcategory'] ?? 'pickles'; // default
      $productsData[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'size' => $p['weight'] ?? '1 unit',
        'price' => $p['price'],
        'imageUrl' => $p['image_url'] ?? '',
        'description' => $p['description_en'] ?? 'Flavourful pickle',
        'category' => $subcategory,
        'baseDesc' => $p['description_en'] ?? 'Artisan crafted'
      ];
    }
  }
}
// Fallback to static data if no DB products found
if (empty($productsData)) {
  // Embedded static product data (same as original HTML)
  $staticProducts = [
    ['id' => 1, 'name' => 'Mango Pickle Classic', 'size' => '500 g', 'price' => 120, 'imageUrl' => 'images\\Mango Pickle Classic.jpg', 'category' => 'pickles', 'baseDesc' => 'Classic mango pickle 500g.'],
    ['id' => 2, 'name' => 'Mango Pickle Spicy', 'size' => '400 g', 'price' => 110, 'imageUrl' => 'images\\Mango Pickle Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy mango pickle 400g.'],
    ['id' => 3, 'name' => 'Mango Pickle Garlic Mix', 'size' => '500 g', 'price' => 135, 'imageUrl' => 'images\\Mango Pickle Garlic Mix.jpg', 'category' => 'pickles', 'baseDesc' => 'Garlic mixed mango pickle 500g.'],
    ['id' => 4, 'name' => 'Lemon Pickle Traditional', 'size' => '400 g', 'price' => 100, 'imageUrl' => 'images\\Lemon Pickle Traditional.jpg', 'category' => 'pickles', 'baseDesc' => 'Traditional lemon pickle 400g.'],
    ['id' => 5, 'name' => 'Lemon Pickle Sweet', 'size' => '350 g', 'price' => 95, 'imageUrl' => 'images\\Lemon Pickle Sweet.jpg', 'category' => 'pickles', 'baseDesc' => 'Sweet lemon pickle 350g.'],
    ['id' => 6, 'name' => 'Lemon Ginger Pickle', 'size' => '300 g', 'price' => 105, 'imageUrl' => 'images\\Lemon Ginger Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Lemon ginger pickle 300g.'],
    ['id' => 7, 'name' => 'Green Chilli Pickle', 'size' => '300 g', 'price' => 90, 'imageUrl' => 'images\\Green Chilli Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Green chilli pickle 300g.'],
    ['id' => 8, 'name' => 'Stuffed Chilli Pickle', 'size' => '350 g', 'price' => 120, 'imageUrl' => 'images\\Stuffed Chilli Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Stuffed chilli pickle 350g.'],
    ['id' => 9, 'name' => 'Garlic Pickle Spicy', 'size' => '300 g', 'price' => 115, 'imageUrl' => 'images\\Garlic Pickle Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy garlic pickle 300g.'],
    ['id' => 10, 'name' => 'Garlic Pickle Oil Based', 'size' => '400 g', 'price' => 125, 'imageUrl' => 'images\\Garlic Pickle Oil Based.jpg', 'category' => 'pickles', 'baseDesc' => 'Oil based garlic pickle 400g.'],
    ['id' => 11, 'name' => 'Mixed Vegetable Pickle', 'size' => '500 g', 'price' => 140, 'imageUrl' => 'images\\Mixed Vegetable Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Mixed vegetable pickle 500g.'],
    ['id' => 12, 'name' => 'Punjabi Mixed Pickle', 'size' => '450 g', 'price' => 135, 'imageUrl' => 'images\\Punjabi Mixed Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Punjabi style mixed pickle 450g.'],
    ['id' => 13, 'name' => 'Andhra Mango Avakaya', 'size' => '500 g', 'price' => 150, 'imageUrl' => 'images\\Andhra Mango Avakaya.jpg', 'category' => 'pickles', 'baseDesc' => 'Andhra style mango avakaya pickle 500g.'],
    ['id' => 14, 'name' => 'Gongura Pickle', 'size' => '350 g', 'price' => 145, 'imageUrl' => 'images\\Gongura Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Gongura (sorrel leaves) pickle 350g.'],
    ['id' => 15, 'name' => 'Tamarind Pickle', 'size' => '300 g', 'price' => 130, 'imageUrl' => 'images\\Tamarind Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Tamarind pickle 300g.'],
    ['id' => 16, 'name' => 'Tomato Pickle Andhra Style', 'size' => '400 g', 'price' => 135, 'imageUrl' => 'images\\Tomato Pickle Andhra Style.jpg', 'category' => 'pickles', 'baseDesc' => 'Andhra style tomato pickle 400g.'],
    ['id' => 17, 'name' => 'Carrot Pickle', 'size' => '300 g', 'price' => 90, 'imageUrl' => 'images\\Carrot Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Carrot pickle 300g.'],
    ['id' => 18, 'name' => 'Radish Pickle', 'size' => '300 g', 'price' => 95, 'imageUrl' => 'images\\Radish Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Radish pickle 300g.'],
    ['id' => 19, 'name' => 'Bitter Gourd Pickle', 'size' => '300 g', 'price' => 110, 'imageUrl' => 'images\\Bitter Gourd Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Bitter gourd pickle 300g.'],
    ['id' => 20, 'name' => 'Mango Sweet Pickle', 'size' => '400 g', 'price' => 120, 'imageUrl' => 'images\\Mango Sweet Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Sweet mango pickle 400g.'],
    ['id' => 21, 'name' => 'Tomato Ketchup Classic', 'size' => '1 kg', 'price' => 140, 'imageUrl' => 'images\\Tomato Ketchup Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic tomato ketchup 1kg.'],
    ['id' => 22, 'name' => 'Tomato Ketchup Family Pack', 'size' => '500 g', 'price' => 90, 'imageUrl' => 'images\\Tomato Ketchup Family Pack.jpg', 'category' => 'sauces', 'baseDesc' => 'Family pack tomato ketchup 500g.'],
    ['id' => 23, 'name' => 'Tomato Ketchup Premium', 'size' => '1 kg', 'price' => 150, 'imageUrl' => 'images\\Tomato Ketchup Premium.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium tomato ketchup 1kg.'],
    ['id' => 24, 'name' => 'Spicy Tomato Sauce', 'size' => '700 g', 'price' => 130, 'imageUrl' => 'images\\Spicy Tomato Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy tomato sauce 700g.'],
    ['id' => 25, 'name' => 'Garlic Tomato Sauce', 'size' => '700 g', 'price' => 140, 'imageUrl' => 'images\\Garlic Tomato Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic tomato sauce 700g.'],
    ['id' => 26, 'name' => 'Pizza Pasta Sauce', 'size' => '400 g', 'price' => 160, 'imageUrl' => 'images\\Pizza Pasta Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Pizza pasta sauce 400g.'],
    ['id' => 27, 'name' => 'Schezwan Sauce', 'size' => '200 g', 'price' => 80, 'imageUrl' => 'images\\Schezwan Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Schezwan sauce 200g.'],
    ['id' => 28, 'name' => 'Schezwan Chutney', 'size' => '250 g', 'price' => 85, 'imageUrl' => 'images\\Schezwan Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Schezwan chutney 250g.'],
    ['id' => 29, 'name' => 'Schezwan Stir Fry Sauce', 'size' => '500 g', 'price' => 150, 'imageUrl' => 'images\\Schezwan Stir Fry Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Schezwan stir fry sauce 500g.'],
    ['id' => 30, 'name' => 'Chilli Garlic Sauce', 'size' => '300 g', 'price' => 95, 'imageUrl' => 'images\\Chilli Garlic Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Chilli garlic sauce 300g.'],
    ['id' => 31, 'name' => 'Red Chilli Sauce', 'size' => '650 g', 'price' => 120, 'imageUrl' => 'images\\Red Chilli Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Red chilli sauce 650g.'],
    ['id' => 32, 'name' => 'Green Chilli Sauce', 'size' => '650 g', 'price' => 125, 'imageUrl' => 'images\\Green Chilli Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Green chilli sauce 650g.'],
    ['id' => 33, 'name' => 'Soy Sauce Classic', 'size' => '500 ml', 'price' => 150, 'imageUrl' => 'images\\Soy Sauce Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic soy sauce 500ml.'],
    ['id' => 34, 'name' => 'Dark Soy Sauce', 'size' => '500 ml', 'price' => 165, 'imageUrl' => 'images\\Dark Soy Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Dark soy sauce 500ml.'],
    ['id' => 35, 'name' => 'Light Soy Sauce', 'size' => '500 ml', 'price' => 155, 'imageUrl' => 'images\\Light Soy Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Light soy sauce 500ml.'],
    ['id' => 36, 'name' => 'Sweet Soy Sauce', 'size' => '500 ml', 'price' => 170, 'imageUrl' => 'images\\Sweet Soy Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Sweet soy sauce 500ml.'],
    ['id' => 37, 'name' => 'Teriyaki Sauce', 'size' => '300 g', 'price' => 190, 'imageUrl' => 'images\\Teriyaki Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Teriyaki sauce 300g.'],
    ['id' => 38, 'name' => 'BBQ Sauce', 'size' => '400 g', 'price' => 200, 'imageUrl' => 'images\\BBQ Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'BBQ sauce 400g.'],
    ['id' => 39, 'name' => 'Honey Mustard Sauce', 'size' => '250 g', 'price' => 180, 'imageUrl' => 'images\\Honey Mustard Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Honey mustard sauce 250g.'],
    ['id' => 40, 'name' => 'Yellow Mustard Sauce', 'size' => '250 g', 'price' => 160, 'imageUrl' => 'images\\Yellow Mustard Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Yellow mustard sauce 250g.'],
    ['id' => 41, 'name' => 'Dijon Mustard', 'size' => '200 g', 'price' => 220, 'imageUrl' => 'images\\Dijon Mustard.jpg', 'category' => 'sauces', 'baseDesc' => 'Dijon mustard 200g.'],
    ['id' => 42, 'name' => 'Garlic Dip Sauce', 'size' => '200 g', 'price' => 150, 'imageUrl' => 'images\\Garlic Dip Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic dip sauce 200g.'],
    ['id' => 43, 'name' => 'Mayonnaise Garlic', 'size' => '500 g', 'price' => 190, 'imageUrl' => 'images\\Mayonnaise Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic mayonnaise 500g.'],
    ['id' => 44, 'name' => 'Mayonnaise Classic', 'size' => '500 g', 'price' => 180, 'imageUrl' => 'images\\Mayonnaise Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic mayonnaise 500g.'],
    ['id' => 45, 'name' => 'Tandoori Sauce', 'size' => '350 g', 'price' => 170, 'imageUrl' => 'images\\Tandoori Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Tandoori sauce 350g.'],
    ['id' => 46, 'name' => 'Peri Peri Sauce', 'size' => '300 g', 'price' => 175, 'imageUrl' => 'images\\Peri Peri Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Peri peri sauce 300g.'],
    ['id' => 47, 'name' => 'Mexican Salsa', 'size' => '300 g', 'price' => 160, 'imageUrl' => 'images\\Mexican Salsa.jpg', 'category' => 'sauces', 'baseDesc' => 'Mexican salsa 300g.'],
    ['id' => 48, 'name' => 'Tomato Salsa', 'size' => '300 g', 'price' => 155, 'imageUrl' => 'images\\Tomato Salsa.jpg', 'category' => 'sauces', 'baseDesc' => 'Tomato salsa 300g.'],
    ['id' => 49, 'name' => 'Hot Sauce Extra Spicy', 'size' => '200 g', 'price' => 120, 'imageUrl' => 'images\\Hot Sauce Extra Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Extra spicy hot sauce 200g.'],
    ['id' => 50, 'name' => 'Thai Sweet Chilli Sauce', 'size' => '350 g', 'price' => 180, 'imageUrl' => 'images\\Thai Sweet Chilli Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Thai sweet chilli sauce 350g.'],
    ['id' => 51, 'name' => 'Mango Chutney', 'size' => '300 g', 'price' => 150, 'imageUrl' => 'images\\Mango Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Mango chutney 300g.'],
    ['id' => 52, 'name' => 'Tamarind Chutney', 'size' => '300 g', 'price' => 140, 'imageUrl' => 'images\\Tamarind Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Tamarind chutney 300g.'],
    ['id' => 53, 'name' => 'Mint Chutney', 'size' => '250 g', 'price' => 120, 'imageUrl' => 'images\\Mint Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Mint chutney 250g.'],
    ['id' => 54, 'name' => 'Coriander Chutney', 'size' => '250 g', 'price' => 120, 'imageUrl' => 'images\\Coriander Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Coriander chutney 250g.'],
    ['id' => 55, 'name' => 'Date Tamarind Chutney', 'size' => '300 g', 'price' => 150, 'imageUrl' => 'images\\Date Tamarind Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Date tamarind chutney 300g.'],
    ['id' => 56, 'name' => 'Ginger Pickle', 'size' => '250 g', 'price' => 140, 'imageUrl' => 'images\\Ginger Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Ginger pickle 250g.'],
    ['id' => 57, 'name' => 'Onion Pickle', 'size' => '300 g', 'price' => 120, 'imageUrl' => 'images\\Onion Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Onion pickle 300g.'],
    ['id' => 58, 'name' => 'Kerala Lime Pickle', 'size' => '350 g', 'price' => 145, 'imageUrl' => 'images\\Kerala Lime Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Kerala style lime pickle 350g.'],
    ['id' => 59, 'name' => 'Kerala Mango Pickle', 'size' => '400 g', 'price' => 150, 'imageUrl' => 'images\\Kerala Mango Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Kerala style mango pickle 400g.'],
    ['id' => 60, 'name' => 'Kerala Garlic Pickle', 'size' => '350 g', 'price' => 145, 'imageUrl' => 'images\\Kerala Garlic Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Kerala style garlic pickle 350g.'],
    ['id' => 61, 'name' => 'Punjabi Lemon Pickle', 'size' => '400 g', 'price' => 135, 'imageUrl' => 'images\\Punjabi Lemon Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Punjabi style lemon pickle 400g.'],
    ['id' => 62, 'name' => 'Gujarati Sweet Mango Pickle', 'size' => '350 g', 'price' => 140, 'imageUrl' => 'images\\Gujarati Sweet Mango Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Gujarati sweet mango pickle 350g.'],
    ['id' => 63, 'name' => 'Rajasthani Ker Sangri Pickle', 'size' => '250 g', 'price' => 180, 'imageUrl' => 'images\\Rajasthani Ker Sangri Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Rajasthani ker sangri pickle 250g.'],
    ['id' => 64, 'name' => 'Bamboo Shoot Pickle', 'size' => '300 g', 'price' => 170, 'imageUrl' => 'images\\Bamboo Shoot Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Bamboo shoot pickle 300g.'],
    ['id' => 65, 'name' => 'Fish Pickle Kerala', 'size' => '250 g', 'price' => 200, 'imageUrl' => 'images\\Fish Pickle Kerala.jpg', 'category' => 'pickles', 'baseDesc' => 'Kerala style fish pickle 250g.'],
    ['id' => 66, 'name' => 'Prawn Pickle', 'size' => '250 g', 'price' => 220, 'imageUrl' => 'images\\Prawn Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Prawn pickle 250g.'],
    ['id' => 67, 'name' => 'Chicken Pickle', 'size' => '250 g', 'price' => 210, 'imageUrl' => 'images\\Chicken Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Chicken pickle 250g.'],
    ['id' => 68, 'name' => 'Green Tomato Pickle', 'size' => '300 g', 'price' => 130, 'imageUrl' => 'images\\Green Tomato Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Green tomato pickle 300g.'],
    ['id' => 69, 'name' => 'Beetroot Pickle', 'size' => '300 g', 'price' => 120, 'imageUrl' => 'images\\Beetroot Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Beetroot pickle 300g.'],
    ['id' => 70, 'name' => 'Mango Ginger Pickle', 'size' => '300 g', 'price' => 135, 'imageUrl' => 'images\\Mango Ginger Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Mango ginger pickle 300g.'],
    ['id' => 71, 'name' => 'Jalapeno Sauce', 'size' => '300 g', 'price' => 160, 'imageUrl' => 'images\\Jalapeno Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Jalapeno sauce 300g.'],
    ['id' => 72, 'name' => 'Chipotle Sauce', 'size' => '300 g', 'price' => 190, 'imageUrl' => 'images\\Chipotle Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Chipotle sauce 300g.'],
    ['id' => 73, 'name' => 'Harissa Sauce', 'size' => '250 g', 'price' => 210, 'imageUrl' => 'images\\Harissa Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Harissa sauce 250g.'],
    ['id' => 74, 'name' => 'Pesto Sauce', 'size' => '200 g', 'price' => 220, 'imageUrl' => 'images\\Pesto Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Pesto sauce 200g.'],
    ['id' => 75, 'name' => 'Alfredo Pasta Sauce', 'size' => '400 g', 'price' => 210, 'imageUrl' => 'images\\Alfredo Pasta Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Alfredo pasta sauce 400g.'],
    ['id' => 76, 'name' => 'Arrabbiata Pasta Sauce', 'size' => '400 g', 'price' => 200, 'imageUrl' => 'images\\Arrabbiata Pasta Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Arrabbiata pasta sauce 400g.'],
    ['id' => 77, 'name' => 'Sweet & Sour Sauce', 'size' => '300 g', 'price' => 180, 'imageUrl' => 'images\\Sweet & Sour Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Sweet & sour sauce 300g.'],
    ['id' => 78, 'name' => 'Chilli Vinegar', 'size' => '500 ml', 'price' => 120, 'imageUrl' => 'images\\Chilli Vinegar.jpg', 'category' => 'sauces', 'baseDesc' => 'Chilli vinegar 500ml.'],
    ['id' => 79, 'name' => 'Apple Cider Vinegar Sauce', 'size' => '500 ml', 'price' => 160, 'imageUrl' => 'images\\Apple Cider Vinegar Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Apple cider vinegar sauce 500ml.'],
    ['id' => 80, 'name' => 'Garlic Vinegar Sauce', 'size' => '500 ml', 'price' => 150, 'imageUrl' => 'images\\Garlic Vinegar Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic vinegar sauce 500ml.'],
    ['id' => 81, 'name' => 'Spicy Mango Pickle Oil Free', 'size' => '400 g', 'price' => 135, 'imageUrl' => 'images\\Spicy Mango Pickle Oil Free.jpg', 'category' => 'pickles', 'baseDesc' => 'Oil free spicy mango pickle 400g.'],
    ['id' => 82, 'name' => 'Mango Pickle Mustard Style', 'size' => '500 g', 'price' => 145, 'imageUrl' => 'images\\Mango Pickle Mustard Style.jpg', 'category' => 'pickles', 'baseDesc' => 'Mustard style mango pickle 500g.'],
    ['id' => 83, 'name' => 'Mango Pickle Andhra Spicy', 'size' => '500 g', 'price' => 155, 'imageUrl' => 'images\\Mango Pickle Andhra Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Andhra spicy mango pickle 500g.'],
    ['id' => 84, 'name' => 'Mango Pickle Village Style', 'size' => '400 g', 'price' => 140, 'imageUrl' => 'images\\Mango Pickle Village Style.jpg', 'category' => 'pickles', 'baseDesc' => 'Village style mango pickle 400g.'],
    ['id' => 85, 'name' => 'Mango Pickle Garlic Infused', 'size' => '350 g', 'price' => 138, 'imageUrl' => 'images\\Mango Pickle Garlic Infused.jpg', 'category' => 'pickles', 'baseDesc' => 'Garlic infused mango pickle 350g.'],
    ['id' => 86, 'name' => 'Lemon Pickle Spicy', 'size' => '400 g', 'price' => 120, 'imageUrl' => 'images\\Lemon Pickle Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy lemon pickle 400g.'],
    ['id' => 87, 'name' => 'Lemon Pickle Oil Free', 'size' => '350 g', 'price' => 110, 'imageUrl' => 'images\\Lemon Pickle Oil Free.jpg', 'category' => 'pickles', 'baseDesc' => 'Oil free lemon pickle 350g.'],
    ['id' => 88, 'name' => 'Lemon Pickle Homemade Style', 'size' => '400 g', 'price' => 125, 'imageUrl' => 'images\\Lemon Pickle Homemade Style.jpg', 'category' => 'pickles', 'baseDesc' => 'Homemade style lemon pickle 400g.'],
    ['id' => 89, 'name' => 'Lemon Pickle Kerala Style', 'size' => '350 g', 'price' => 135, 'imageUrl' => 'images\\Lemon Pickle Kerala Style.jpg', 'category' => 'pickles', 'baseDesc' => 'Kerala style lemon pickle 350g.'],
    ['id' => 90, 'name' => 'Lemon Pickle Ginger Mix', 'size' => '300 g', 'price' => 115, 'imageUrl' => 'images\\Lemon Pickle Ginger Mix.jpg', 'category' => 'pickles', 'baseDesc' => 'Lemon ginger mix pickle 300g.'],
    ['id' => 91, 'name' => 'Green Chilli Pickle Mustard', 'size' => '300 g', 'price' => 110, 'imageUrl' => 'images\\Green Chilli Pickle Mustard.jpg', 'category' => 'pickles', 'baseDesc' => 'Mustard green chilli pickle 300g.'],
    ['id' => 92, 'name' => 'Green Chilli Pickle Garlic', 'size' => '300 g', 'price' => 120, 'imageUrl' => 'images\\Green Chilli Pickle Garlic.jpg', 'category' => 'pickles', 'baseDesc' => 'Garlic green chilli pickle 300g.'],
    ['id' => 93, 'name' => 'Green Chilli Pickle Andhra', 'size' => '350 g', 'price' => 125, 'imageUrl' => 'images\\Green Chilli Pickle Andhra.jpg', 'category' => 'pickles', 'baseDesc' => 'Andhra green chilli pickle 350g.'],
    ['id' => 94, 'name' => 'Stuffed Chilli Pickle North Style', 'size' => '350 g', 'price' => 135, 'imageUrl' => 'images\\Stuffed Chilli Pickle North Style.jpg', 'category' => 'pickles', 'baseDesc' => 'North style stuffed chilli pickle 350g.'],
    ['id' => 95, 'name' => 'Chilli Lemon Pickle', 'size' => '300 g', 'price' => 120, 'imageUrl' => 'images\\Chilli Lemon Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Chilli lemon pickle 300g.'],
    ['id' => 96, 'name' => 'Garlic Pickle Andhra Style', 'size' => '300 g', 'price' => 130, 'imageUrl' => 'images\\Garlic Pickle Andhra Style.jpg', 'category' => 'pickles', 'baseDesc' => 'Andhra style garlic pickle 300g.'],
    ['id' => 97, 'name' => 'Garlic Pickle Mustard Oil', 'size' => '400 g', 'price' => 140, 'imageUrl' => 'images\\Garlic Pickle Mustard Oil.jpg', 'category' => 'pickles', 'baseDesc' => 'Mustard oil garlic pickle 400g.'],
    ['id' => 98, 'name' => 'Garlic Pickle Village Recipe', 'size' => '350 g', 'price' => 135, 'imageUrl' => 'images\\Garlic Pickle Village Recipe.jpg', 'category' => 'pickles', 'baseDesc' => 'Village recipe garlic pickle 350g.'],
    ['id' => 99, 'name' => 'Garlic Chilli Pickle', 'size' => '300 g', 'price' => 125, 'imageUrl' => 'images\\Garlic Chilli Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Garlic chilli pickle 300g.'],
    ['id' => 100, 'name' => 'Garlic Lemon Pickle', 'size' => '300 g', 'price' => 120, 'imageUrl' => 'images\\Garlic Lemon Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Garlic lemon pickle 300g.'],
    ['id' => 101, 'name' => 'Mixed Vegetable Pickle Punjabi', 'size' => '500 g', 'price' => 160, 'imageUrl' => 'images\\Mixed Vegetable Pickle Punjabi.jpg', 'category' => 'pickles', 'baseDesc' => 'Punjabi mixed vegetable pickle 500g.'],
    ['id' => 102, 'name' => 'Mixed Vegetable Pickle Spicy', 'size' => '450 g', 'price' => 150, 'imageUrl' => 'images\\Mixed Vegetable Pickle Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy mixed vegetable pickle 450g.'],
    ['id' => 103, 'name' => 'Mixed Vegetable Pickle Homemade', 'size' => '400 g', 'price' => 145, 'imageUrl' => 'images\\Mixed Vegetable Pickle Homemade.jpg', 'category' => 'pickles', 'baseDesc' => 'Homemade mixed vegetable pickle 400g.'],
    ['id' => 104, 'name' => 'Mixed Vegetable Pickle Mustard', 'size' => '450 g', 'price' => 150, 'imageUrl' => 'images\\Mixed Vegetable Pickle Mustard.jpg', 'category' => 'pickles', 'baseDesc' => 'Mustard mixed vegetable pickle 450g.'],
    ['id' => 105, 'name' => 'Mixed Vegetable Pickle Oil Free', 'size' => '400 g', 'price' => 140, 'imageUrl' => 'images\\Mixed Vegetable Pickle Oil Free.jpg', 'category' => 'pickles', 'baseDesc' => 'Oil free mixed vegetable pickle 400g.'],
    ['id' => 106, 'name' => 'Andhra Gongura Pickle Spicy', 'size' => '350 g', 'price' => 160, 'imageUrl' => 'images\\Andhra Gongura Pickle Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy Andhra gongura pickle 350g.'],
    ['id' => 107, 'name' => 'Gongura Garlic Pickle', 'size' => '350 g', 'price' => 165, 'imageUrl' => 'images\\Gongura Garlic Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Gongura garlic pickle 350g.'],
    ['id' => 108, 'name' => 'Gongura Chilli Pickle', 'size' => '300 g', 'price' => 155, 'imageUrl' => 'images\\Gongura Chilli Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Gongura chilli pickle 300g.'],
    ['id' => 109, 'name' => 'Gongura Traditional Pickle', 'size' => '350 g', 'price' => 150, 'imageUrl' => 'images\\Gongura Traditional Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Traditional gongura pickle 350g.'],
    ['id' => 110, 'name' => 'Gongura Andhra Special', 'size' => '350 g', 'price' => 170, 'imageUrl' => 'images\\Gongura Andhra Special.jpg', 'category' => 'pickles', 'baseDesc' => 'Andhra special gongura pickle 350g.'],
    ['id' => 111, 'name' => 'Tomato Pickle Andhra Spicy', 'size' => '400 g', 'price' => 150, 'imageUrl' => 'images\\Tomato Pickle Andhra Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Andhra spicy tomato pickle 400g.'],
    ['id' => 112, 'name' => 'Tomato Pickle Garlic', 'size' => '350 g', 'price' => 145, 'imageUrl' => 'images\\Tomato Pickle Garlic.jpg', 'category' => 'pickles', 'baseDesc' => 'Garlic tomato pickle 350g.'],
    ['id' => 113, 'name' => 'Tomato Pickle Mustard Oil', 'size' => '350 g', 'price' => 140, 'imageUrl' => 'images\\Tomato Pickle Mustard Oil.jpg', 'category' => 'pickles', 'baseDesc' => 'Mustard oil tomato pickle 350g.'],
    ['id' => 114, 'name' => 'Tomato Pickle Homemade', 'size' => '400 g', 'price' => 150, 'imageUrl' => 'images\\Tomato Pickle Homemade.jpg', 'category' => 'pickles', 'baseDesc' => 'Homemade tomato pickle 400g.'],
    ['id' => 115, 'name' => 'Tomato Pickle Spicy Andhra', 'size' => '350 g', 'price' => 155, 'imageUrl' => 'images\\Tomato Pickle Spicy Andhra.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy Andhra tomato pickle 350g.'],
    ['id' => 116, 'name' => 'Tamarind Pickle Andhra Style', 'size' => '300 g', 'price' => 145, 'imageUrl' => 'images\\Tamarind Pickle Andhra Style.jpg', 'category' => 'pickles', 'baseDesc' => 'Andhra style tamarind pickle 300g.'],
    ['id' => 117, 'name' => 'Tamarind Garlic Pickle', 'size' => '300 g', 'price' => 150, 'imageUrl' => 'images\\Tamarind Garlic Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Tamarind garlic pickle 300g.'],
    ['id' => 118, 'name' => 'Tamarind Sweet Pickle', 'size' => '300 g', 'price' => 140, 'imageUrl' => 'images\\Tamarind Sweet Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Sweet tamarind pickle 300g.'],
    ['id' => 119, 'name' => 'Tamarind Chilli Pickle', 'size' => '300 g', 'price' => 150, 'imageUrl' => 'images\\Tamarind Chilli Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Tamarind chilli pickle 300g.'],
    ['id' => 120, 'name' => 'Tamarind Village Style Pickle', 'size' => '300 g', 'price' => 155, 'imageUrl' => 'images\\Tamarind Village Style Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Village style tamarind pickle 300g.'],
    ['id' => 121, 'name' => 'Tomato Ketchup Spicy', 'size' => '1 kg', 'price' => 150, 'imageUrl' => 'images\\Tomato Ketchup Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy tomato ketchup 1kg.'],
    ['id' => 122, 'name' => 'Tomato Ketchup Restaurant Pack', 'size' => '2 kg', 'price' => 250, 'imageUrl' => 'images\\Tomato Ketchup Restaurant Pack.jpg', 'category' => 'sauces', 'baseDesc' => 'Restaurant pack tomato ketchup 2kg.'],
    ['id' => 123, 'name' => 'Tomato Ketchup Classic Family', 'size' => '900 g', 'price' => 140, 'imageUrl' => 'images\\Tomato Ketchup Classic Family.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic family tomato ketchup 900g.'],
    ['id' => 124, 'name' => 'Tomato Ketchup Kids Pack', 'size' => '500 g', 'price' => 95, 'imageUrl' => 'images\\Tomato Ketchup Kids Pack.jpg', 'category' => 'sauces', 'baseDesc' => 'Kids pack tomato ketchup 500g.'],
    ['id' => 125, 'name' => 'Tomato Ketchup Glass Bottle', 'size' => '650 g', 'price' => 130, 'imageUrl' => 'images\\Tomato Ketchup Glass Bottle.jpg', 'category' => 'sauces', 'baseDesc' => 'Glass bottle tomato ketchup 650g.'],
    ['id' => 126, 'name' => 'Red Chilli Sauce Chinese Style', 'size' => '650 g', 'price' => 140, 'imageUrl' => 'images\\Red Chilli Sauce Chinese Style.jpg', 'category' => 'sauces', 'baseDesc' => 'Chinese style red chilli sauce 650g.'],
    ['id' => 127, 'name' => 'Red Chilli Sauce Extra Hot', 'size' => '650 g', 'price' => 145, 'imageUrl' => 'images\\Red Chilli Sauce Extra Hot.jpg', 'category' => 'sauces', 'baseDesc' => 'Extra hot red chilli sauce 650g.'],
    ['id' => 128, 'name' => 'Red Chilli Sauce Sweet Spicy', 'size' => '500 g', 'price' => 130, 'imageUrl' => 'images\\Red Chilli Sauce Sweet Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Sweet spicy red chilli sauce 500g.'],
    ['id' => 129, 'name' => 'Red Chilli Sauce Restaurant Pack', 'size' => '1 kg', 'price' => 190, 'imageUrl' => 'images\\Red Chilli Sauce Restaurant Pack.jpg', 'category' => 'sauces', 'baseDesc' => 'Restaurant pack red chilli sauce 1kg.'],
    ['id' => 130, 'name' => 'Red Chilli Sauce Garlic', 'size' => '650 g', 'price' => 150, 'imageUrl' => 'images\\Red Chilli Sauce Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic red chilli sauce 650g.'],
    ['id' => 131, 'name' => 'Green Chilli Sauce Spicy', 'size' => '650 g', 'price' => 150, 'imageUrl' => 'images\\Green Chilli Sauce Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy green chilli sauce 650g.'],
    ['id' => 132, 'name' => 'Green Chilli Sauce Mild', 'size' => '650 g', 'price' => 140, 'imageUrl' => 'images\\Green Chilli Sauce Mild.jpg', 'category' => 'sauces', 'baseDesc' => 'Mild green chilli sauce 650g.'],
    ['id' => 136, 'name' => 'Schezwan Sauce Chinese', 'size' => '300 g', 'price' => 120, 'imageUrl' => 'images\\Schezwan Sauce Chinese.jpg', 'category' => 'sauces', 'baseDesc' => 'Chinese schezwan sauce 300g.'],
    ['id' => 141, 'name' => 'Soy Sauce Light', 'size' => '500 ml', 'price' => 150, 'imageUrl' => 'images\\Soy Sauce Light.jpg', 'category' => 'sauces', 'baseDesc' => 'Light soy sauce 500ml.'],
    ['id' => 142, 'name' => 'Soy Sauce Dark', 'size' => '500 ml', 'price' => 165, 'imageUrl' => 'images\\Soy Sauce Dark.jpg', 'category' => 'sauces', 'baseDesc' => 'Dark soy sauce 500ml.'],
    ['id' => 146, 'name' => 'BBQ Sauce Smoky', 'size' => '400 g', 'price' => 220, 'imageUrl' => 'images\\BBQ Sauce Smoky.jpg', 'category' => 'sauces', 'baseDesc' => 'Smoky BBQ sauce 400g.'],
    ['id' => 150, 'name' => 'BBQ Sauce Premium', 'size' => '400 g', 'price' => 240, 'imageUrl' => 'images\\BBQ Sauce Premium.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium BBQ sauce 400g.'],
    ['id' => 151, 'name' => 'Sweet Chilli Sauce Thai', 'size' => '350 g', 'price' => 190, 'imageUrl' => 'images\\Sweet Chilli Sauce Thai.jpg', 'category' => 'sauces', 'baseDesc' => 'Thai sweet chilli sauce 350g.'],
    ['id' => 156, 'name' => 'Mexican Salsa Medium', 'size' => '300 g', 'price' => 170, 'imageUrl' => 'images\\Mexican Salsa Medium.jpg', 'category' => 'sauces', 'baseDesc' => 'Medium Mexican salsa 300g.'],
    ['id' => 157, 'name' => 'Mexican Salsa Spicy', 'size' => '300 g', 'price' => 175, 'imageUrl' => 'images\\Mexican Salsa Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy Mexican salsa 300g.'],
    ['id' => 162, 'name' => 'Mango Ginger Pickle Homemade', 'size' => '300 g', 'price' => 145, 'imageUrl' => 'images\\Mango Ginger Pickle Homemade.jpg', 'category' => 'pickles', 'baseDesc' => 'Homemade mango ginger pickle 300g.'],
    ['id' => 163, 'name' => 'Mango Ginger Pickle Mustard', 'size' => '350 g', 'price' => 155, 'imageUrl' => 'images\\Mango Ginger Pickle Mustard.jpg', 'category' => 'pickles', 'baseDesc' => 'Mustard mango ginger pickle 350g.'],
    ['id' => 164, 'name' => 'Mango Ginger Pickle Andhra', 'size' => '350 g', 'price' => 160, 'imageUrl' => 'images\\Mango Ginger Pickle Andhra.jpg', 'category' => 'pickles', 'baseDesc' => 'Andhra mango ginger pickle 350g.'],
    ['id' => 165, 'name' => 'Mango Ginger Pickle Traditional', 'size' => '300 g', 'price' => 150, 'imageUrl' => 'images\\Mango Ginger Pickle Traditional.jpg', 'category' => 'pickles', 'baseDesc' => 'Traditional mango ginger pickle 300g.'],
    ['id' => 166, 'name' => 'Carrot Pickle Spicy', 'size' => '300 g', 'price' => 120, 'imageUrl' => 'images\\Carrot Pickle Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy carrot pickle 300g.'],
    ['id' => 167, 'name' => 'Carrot Pickle Mustard Oil', 'size' => '300 g', 'price' => 125, 'imageUrl' => 'images\\Carrot Pickle Mustard Oil.jpg', 'category' => 'pickles', 'baseDesc' => 'Mustard oil carrot pickle 300g.'],
    ['id' => 171, 'name' => 'Radish Pickle Spicy', 'size' => '300 g', 'price' => 120, 'imageUrl' => 'images\\Radish Pickle Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy radish pickle 300g.'],
    ['id' => 176, 'name' => 'Bitter Gourd Pickle Spicy', 'size' => '300 g', 'price' => 140, 'imageUrl' => 'images\\Bitter Gourd Pickle Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy bitter gourd pickle 300g.'],
    ['id' => 181, 'name' => 'Beetroot Pickle Spicy', 'size' => '300 g', 'price' => 130, 'imageUrl' => 'images\\Beetroot Pickle Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy beetroot pickle 300g.'],
    ['id' => 186, 'name' => 'Onion Pickle Spicy', 'size' => '300 g', 'price' => 125, 'imageUrl' => 'images\\Onion Pickle Spicy.jpg', 'category' => 'pickles', 'baseDesc' => 'Spicy onion pickle 300g.'],
    ['id' => 188, 'name' => 'Onion Garlic Pickle', 'size' => '300 g', 'price' => 135, 'imageUrl' => 'images\\Onion Garlic Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Onion garlic pickle 300g.'],
    ['id' => 195, 'name' => 'Ginger Chilli Pickle', 'size' => '250 g', 'price' => 150, 'imageUrl' => 'images\\Ginger Chilli Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Ginger chilli pickle 250g.'],
    ['id' => 196, 'name' => 'Mint Chutney Classic', 'size' => '250 g', 'price' => 120, 'imageUrl' => 'images\\Mint Chutney Classic.jpg', 'category' => 'chutneys', 'baseDesc' => 'Classic mint chutney 250g.'],
    ['id' => 197, 'name' => 'Mint Chutney Spicy', 'size' => '250 g', 'price' => 125, 'imageUrl' => 'images\\Mint Chutney Spicy.jpg', 'category' => 'chutneys', 'baseDesc' => 'Spicy mint chutney 250g.'],
    ['id' => 198, 'name' => 'Mint Chutney Garlic', 'size' => '250 g', 'price' => 130, 'imageUrl' => 'images\\Mint Chutney Garlic.jpg', 'category' => 'chutneys', 'baseDesc' => 'Garlic mint chutney 250g.'],
    ['id' => 201, 'name' => 'Tamarind Chutney Sweet', 'size' => '300 g', 'price' => 150, 'imageUrl' => 'images\\Tamarind Chutney Sweet.jpg', 'category' => 'chutneys', 'baseDesc' => 'Sweet tamarind chutney 300g.'],
    ['id' => 204, 'name' => 'Tamarind Garlic Chutney', 'size' => '300 g', 'price' => 155, 'imageUrl' => 'images\\Tamarind Garlic Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Tamarind garlic chutney 300g.'],
    ['id' => 206, 'name' => 'Coriander Chutney Classic', 'size' => '250 g', 'price' => 120, 'imageUrl' => 'images\\Coriander Chutney Classic.jpg', 'category' => 'chutneys', 'baseDesc' => 'Classic coriander chutney 250g.'],
    ['id' => 208, 'name' => 'Coriander Chutney Spicy', 'size' => '250 g', 'price' => 130, 'imageUrl' => 'images\\Coriander Chutney Spicy.jpg', 'category' => 'chutneys', 'baseDesc' => 'Spicy coriander chutney 250g.'],
    ['id' => 211, 'name' => 'Jalapeno Sauce Mild', 'size' => '300 g', 'price' => 180, 'imageUrl' => 'images\\Jalapeno Sauce Mild.jpg', 'category' => 'sauces', 'baseDesc' => 'Mild jalapeno sauce 300g.'],
    ['id' => 212, 'name' => 'Jalapeno Sauce Spicy', 'size' => '300 g', 'price' => 185, 'imageUrl' => 'images\\Jalapeno Sauce Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy jalapeno sauce 300g.'],
    ['id' => 214, 'name' => 'Jalapeno Dip Sauce', 'size' => '300 g', 'price' => 195, 'imageUrl' => 'images\\Jalapeno Dip Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Jalapeno dip sauce 300g.'],
    ['id' => 216, 'name' => 'Chipotle Sauce Smoky', 'size' => '300 g', 'price' => 200, 'imageUrl' => 'images\\Chipotle Sauce Smoky.jpg', 'category' => 'sauces', 'baseDesc' => 'Smoky chipotle sauce 300g.'],
    ['id' => 217, 'name' => 'Chipotle Sauce Spicy', 'size' => '300 g', 'price' => 205, 'imageUrl' => 'images\\Chipotle Sauce Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy chipotle sauce 300g.'],
    ['id' => 220, 'name' => 'Chipotle Premium Sauce', 'size' => '350 g', 'price' => 220, 'imageUrl' => 'images\\Chipotle Premium Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium chipotle sauce 350g.'],
    ['id' => 222, 'name' => 'Pesto Sauce Garlic', 'size' => '200 g', 'price' => 235, 'imageUrl' => 'images\\Pesto Sauce Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic pesto sauce 200g.'],
    ['id' => 224, 'name' => 'Pesto Sauce Italian', 'size' => '200 g', 'price' => 245, 'imageUrl' => 'images\\Pesto Sauce Italian.jpg', 'category' => 'sauces', 'baseDesc' => 'Italian pesto sauce 200g.'],
    ['id' => 226, 'name' => 'Alfredo Pasta Sauce Creamy', 'size' => '400 g', 'price' => 230, 'imageUrl' => 'images\\Alfredo Pasta Sauce Creamy.jpg', 'category' => 'sauces', 'baseDesc' => 'Creamy alfredo pasta sauce 400g.'],
    ['id' => 227, 'name' => 'Alfredo Pasta Sauce Garlic', 'size' => '400 g', 'price' => 235, 'imageUrl' => 'images\\Alfredo Pasta Sauce Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic alfredo pasta sauce 400g.'],
    ['id' => 229, 'name' => 'Alfredo Pasta Sauce Italian', 'size' => '400 g', 'price' => 245, 'imageUrl' => 'images\\Alfredo Pasta Sauce Italian.jpg', 'category' => 'sauces', 'baseDesc' => 'Italian alfredo pasta sauce 400g.'],
    ['id' => 231, 'name' => 'Arrabbiata Sauce Spicy', 'size' => '400 g', 'price' => 220, 'imageUrl' => 'images\\Arrabbiata Sauce Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy arrabbiata sauce 400g.'],
    ['id' => 233, 'name' => 'Arrabbiata Sauce Garlic', 'size' => '400 g', 'price' => 230, 'imageUrl' => 'images\\Arrabbiata Sauce Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic arrabbiata sauce 400g.'],
    ['id' => 235, 'name' => 'Arrabbiata Sauce Premium', 'size' => '450 g', 'price' => 240, 'imageUrl' => 'images\\Arrabbiata Sauce Premium.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium arrabbiata sauce 450g.'],
    ['id' => 236, 'name' => 'Sweet & Sour Sauce Classic', 'size' => '300 g', 'price' => 180, 'imageUrl' => 'images\\Sweet & Sour Sauce Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic sweet & sour sauce 300g.'],
    ['id' => 237, 'name' => 'Sweet & Sour Sauce Chinese', 'size' => '300 g', 'price' => 185, 'imageUrl' => 'images\\Sweet & Sour Sauce Chinese.jpg', 'category' => 'sauces', 'baseDesc' => 'Chinese sweet & sour sauce 300g.'],
    ['id' => 238, 'name' => 'Sweet & Sour Sauce Pineapple', 'size' => '300 g', 'price' => 190, 'imageUrl' => 'images\\Sweet & Sour Sauce Pineapple.jpg', 'category' => 'sauces', 'baseDesc' => 'Pineapple sweet & sour sauce 300g.'],
    ['id' => 239, 'name' => 'Sweet & Sour Sauce Spicy', 'size' => '300 g', 'price' => 195, 'imageUrl' => 'images\\Sweet & Sour Sauce Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy sweet & sour sauce 300g.'],
    ['id' => 241, 'name' => 'Garlic Chutney Spicy', 'size' => '250 g', 'price' => 140, 'imageUrl' => 'images\\Garlic Chutney Spicy.jpg', 'category' => 'chutneys', 'baseDesc' => 'Spicy garlic chutney 250g.'],
    ['id' => 243, 'name' => 'Garlic Chutney Dry Style', 'size' => '200 g', 'price' => 150, 'imageUrl' => 'images\\Garlic Chutney Dry Style.jpg', 'category' => 'chutneys', 'baseDesc' => 'Dry style garlic chutney 200g.'],
    ['id' => 247, 'name' => 'Peanut Chutney Spicy', 'size' => '250 g', 'price' => 145, 'imageUrl' => 'images\\Peanut Chutney Spicy.jpg', 'category' => 'chutneys', 'baseDesc' => 'Spicy peanut chutney 250g.'],
    ['id' => 249, 'name' => 'Peanut Chutney Homemade', 'size' => '250 g', 'price' => 155, 'imageUrl' => 'images\\Peanut Chutney Homemade.jpg', 'category' => 'chutneys', 'baseDesc' => 'Homemade peanut chutney 250g.'],
    ['id' => 252, 'name' => 'Coconut Chutney Spicy', 'size' => '250 g', 'price' => 135, 'imageUrl' => 'images\\Coconut Chutney Spicy.jpg', 'category' => 'chutneys', 'baseDesc' => 'Spicy coconut chutney 250g.'],
    ['id' => 255, 'name' => 'Coconut Chutney Premium', 'size' => '300 g', 'price' => 150, 'imageUrl' => 'images\\Coconut Chutney Premium.jpg', 'category' => 'chutneys', 'baseDesc' => 'Premium coconut chutney 300g.'],
    ['id' => 256, 'name' => 'Curry Leaf Chutney', 'size' => '250 g', 'price' => 145, 'imageUrl' => 'images\\Curry Leaf Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Curry leaf chutney 250g.'],
    ['id' => 258, 'name' => 'Curry Leaf Chutney Garlic', 'size' => '250 g', 'price' => 155, 'imageUrl' => 'images\\Curry Leaf Chutney Garlic.jpg', 'category' => 'chutneys', 'baseDesc' => 'Garlic curry leaf chutney 250g.'],
    ['id' => 261, 'name' => 'Peri Peri Sauce Classic', 'size' => '300 g', 'price' => 200, 'imageUrl' => 'images\\Peri Peri Sauce Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic peri peri sauce 300g.'],
    ['id' => 263, 'name' => 'Peri Peri Sauce Garlic', 'size' => '300 g', 'price' => 205, 'imageUrl' => 'images\\Peri Peri Sauce Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic peri peri sauce 300g.'],
    ['id' => 266, 'name' => 'Tandoori Sauce Classic', 'size' => '350 g', 'price' => 190, 'imageUrl' => 'images\\Tandoori Sauce Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic tandoori sauce 350g.'],
    ['id' => 268, 'name' => 'Tandoori Sauce Garlic', 'size' => '350 g', 'price' => 200, 'imageUrl' => 'images\\Tandoori Sauce Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic tandoori sauce 350g.'],
    ['id' => 271, 'name' => 'Honey Mustard Sauce', 'size' => '250 g', 'price' => 190, 'imageUrl' => 'images\\Honey Mustard Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Honey mustard sauce 250g.'],
    ['id' => 273, 'name' => 'Honey Mustard Sauce Garlic', 'size' => '250 g', 'price' => 200, 'imageUrl' => 'images\\Honey Mustard Sauce Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic honey mustard sauce 250g.'],
    ['id' => 274, 'name' => 'Honey Mustard Sauce Lemon', 'size' => '250 g', 'price' => 205, 'imageUrl' => 'images\\Honey Mustard Sauce Lemon.jpg', 'category' => 'sauces', 'baseDesc' => 'Lemon honey mustard sauce 250g.'],
    ['id' => 276, 'name' => 'Dijon Mustard Classic', 'size' => '200 g', 'price' => 220, 'imageUrl' => 'images\\Dijon Mustard Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic dijon mustard 200g.'],
    ['id' => 279, 'name' => 'Dijon Mustard Lemon', 'size' => '200 g', 'price' => 235, 'imageUrl' => 'images\\Dijon Mustard Lemon.jpg', 'category' => 'sauces', 'baseDesc' => 'Lemon dijon mustard 200g.'],
    ['id' => 281, 'name' => 'Apple Chutney Sweet', 'size' => '300 g', 'price' => 170, 'imageUrl' => 'images\\Apple Chutney Sweet.jpg', 'category' => 'chutneys', 'baseDesc' => 'Sweet apple chutney 300g.'],
    ['id' => 282, 'name' => 'Apple Chutney Spicy', 'size' => '300 g', 'price' => 175, 'imageUrl' => 'images\\Apple Chutney Spicy.jpg', 'category' => 'chutneys', 'baseDesc' => 'Spicy apple chutney 300g.'],
    ['id' => 283, 'name' => 'Apple Chutney Cinnamon', 'size' => '300 g', 'price' => 180, 'imageUrl' => 'images\\Apple Chutney Cinnamon.jpg', 'category' => 'chutneys', 'baseDesc' => 'Cinnamon apple chutney 300g.'],
    ['id' => 286, 'name' => 'Pineapple Chutney Sweet', 'size' => '300 g', 'price' => 170, 'imageUrl' => 'images\\Pineapple Chutney Sweet.jpg', 'category' => 'chutneys', 'baseDesc' => 'Sweet pineapple chutney 300g.'],
    ['id' => 288, 'name' => 'Pineapple Chutney Garlic', 'size' => '300 g', 'price' => 180, 'imageUrl' => 'images\\Pineapple Chutney Garlic.jpg', 'category' => 'chutneys', 'baseDesc' => 'Garlic pineapple chutney 300g.'],
    ['id' => 292, 'name' => 'Mango Chutney Spicy', 'size' => '300 g', 'price' => 165, 'imageUrl' => 'images\\Mango Chutney Spicy.jpg', 'category' => 'chutneys', 'baseDesc' => 'Spicy mango chutney 300g.'],
    ['id' => 297, 'name' => 'Chilli Vinegar Spicy', 'size' => '500 ml', 'price' => 145, 'imageUrl' => 'images\\Chilli Vinegar Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy chilli vinegar 500ml.'],
    ['id' => 300, 'name' => 'Chilli Vinegar Premium', 'size' => '1 L', 'price' => 190, 'imageUrl' => 'images\\Chilli Vinegar Premium.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium chilli vinegar 1L.'],
    ['id' => 302, 'name' => 'Ginger Vinegar', 'size' => '500 ml', 'price' => 150, 'imageUrl' => 'images\\Ginger Vinegar.jpg', 'category' => 'sauces', 'baseDesc' => 'Ginger vinegar 500ml.'],
    ['id' => 304, 'name' => 'Red Chilli Vinegar', 'size' => '500 ml', 'price' => 160, 'imageUrl' => 'images\\Red Chilli Vinegar.jpg', 'category' => 'sauces', 'baseDesc' => 'Red chilli vinegar 500ml.'],
    ['id' => 308, 'name' => 'Apple Cider Chilli Sauce', 'size' => '500 ml', 'price' => 210, 'imageUrl' => 'images\\Apple Cider Chilli Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Apple cider chilli sauce 500ml.'],
    ['id' => 311, 'name' => 'Hot Sauce Classic', 'size' => '200 g', 'price' => 150, 'imageUrl' => 'images\\Hot Sauce Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic hot sauce 200g.'],
    ['id' => 312, 'name' => 'Hot Sauce Extra Hot', 'size' => '200 g', 'price' => 160, 'imageUrl' => 'images\\Hot Sauce Extra Hot.jpg', 'category' => 'sauces', 'baseDesc' => 'Extra hot sauce 200g.'],
    ['id' => 314, 'name' => 'Hot Sauce Mexican', 'size' => '200 g', 'price' => 170, 'imageUrl' => 'images\\Hot Sauce Mexican.jpg', 'category' => 'sauces', 'baseDesc' => 'Mexican hot sauce 200g.'],
    ['id' => 316, 'name' => 'Buffalo Wing Sauce', 'size' => '300 g', 'price' => 200, 'imageUrl' => 'images\\Buffalo Wing Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Buffalo wing sauce 300g.'],
    ['id' => 320, 'name' => 'Buffalo Wing Sauce Premium', 'size' => '350 g', 'price' => 220, 'imageUrl' => 'images\\Buffalo Wing Sauce Premium.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium buffalo wing sauce 350g.'],
    ['id' => 321, 'name' => 'Korean Gochujang Sauce', 'size' => '300 g', 'price' => 240, 'imageUrl' => 'images\\Korean Gochujang Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Korean gochujang sauce 300g.'],
    ['id' => 323, 'name' => 'Korean Gochujang Garlic Sauce', 'size' => '300 g', 'price' => 250, 'imageUrl' => 'images\\Korean Gochujang Garlic Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic Korean gochujang sauce 300g.'],
    ['id' => 326, 'name' => 'Korean Kimchi Sauce', 'size' => '300 g', 'price' => 230, 'imageUrl' => 'images\\Korean Kimchi Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Korean kimchi sauce 300g.'],
    ['id' => 328, 'name' => 'Korean Kimchi Garlic Sauce', 'size' => '300 g', 'price' => 240, 'imageUrl' => 'images\\Korean Kimchi Garlic Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic Korean kimchi sauce 300g.'],
    ['id' => 329, 'name' => 'Korean Kimchi Premium Sauce', 'size' => '350 g', 'price' => 250, 'imageUrl' => 'images\\Korean Kimchi Premium Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium Korean kimchi sauce 350g.'],
    ['id' => 330, 'name' => 'Korean Kimchi Restaurant Sauce', 'size' => '400 g', 'price' => 260, 'imageUrl' => 'images\\Korean Kimchi Restaurant Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Restaurant Korean kimchi sauce 400g.'],
    ['id' => 331, 'name' => 'Teriyaki Sauce Classic', 'size' => '300 g', 'price' => 210, 'imageUrl' => 'images\\Teriyaki Sauce Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic teriyaki sauce 300g.'],
    ['id' => 332, 'name' => 'Teriyaki Sauce Garlic', 'size' => '300 g', 'price' => 215, 'imageUrl' => 'images\\Teriyaki Sauce Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic teriyaki sauce 300g.'],
    ['id' => 333, 'name' => 'Teriyaki Sauce Honey', 'size' => '300 g', 'price' => 220, 'imageUrl' => 'images\\Teriyaki Sauce Honey.jpg', 'category' => 'sauces', 'baseDesc' => 'Honey teriyaki sauce 300g.'],
    ['id' => 334, 'name' => 'Teriyaki Sauce Premium', 'size' => '350 g', 'price' => 230, 'imageUrl' => 'images\\Teriyaki Sauce Premium.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium teriyaki sauce 350g.'],
    ['id' => 335, 'name' => 'Teriyaki Sauce Restaurant', 'size' => '400 g', 'price' => 240, 'imageUrl' => 'images\\Teriyaki Sauce Restaurant.jpg', 'category' => 'sauces', 'baseDesc' => 'Restaurant teriyaki sauce 400g.'],
    ['id' => 336, 'name' => 'Thai Green Curry Sauce', 'size' => '350 g', 'price' => 220, 'imageUrl' => 'images\\Thai Green Curry Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Thai green curry sauce 350g.'],
    ['id' => 337, 'name' => 'Thai Green Curry Spicy Sauce', 'size' => '350 g', 'price' => 225, 'imageUrl' => 'images\\Thai Green Curry Spicy Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy Thai green curry sauce 350g.'],
    ['id' => 338, 'name' => 'Thai Green Curry Garlic Sauce', 'size' => '350 g', 'price' => 230, 'imageUrl' => 'images\\Thai Green Curry Garlic Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic Thai green curry sauce 350g.'],
    ['id' => 341, 'name' => 'Thai Red Curry Sauce', 'size' => '350 g', 'price' => 220, 'imageUrl' => 'images\\Thai Red Curry Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Thai red curry sauce 350g.'],
    ['id' => 342, 'name' => 'Thai Red Curry Spicy Sauce', 'size' => '350 g', 'price' => 225, 'imageUrl' => 'images\\Thai Red Curry Spicy Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy Thai red curry sauce 350g.'],
    ['id' => 343, 'name' => 'Thai Red Curry Garlic Sauce', 'size' => '350 g', 'price' => 230, 'imageUrl' => 'images\\Thai Red Curry Garlic Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic Thai red curry sauce 350g.'],
    ['id' => 346, 'name' => 'Hoisin Sauce Classic', 'size' => '300 g', 'price' => 210, 'imageUrl' => 'images\\Hoisin Sauce Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic hoisin sauce 300g.'],
    ['id' => 347, 'name' => 'Hoisin Sauce Garlic', 'size' => '300 g', 'price' => 215, 'imageUrl' => 'images\\Hoisin Sauce Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic hoisin sauce 300g.'],
    ['id' => 348, 'name' => 'Hoisin Sauce Sweet', 'size' => '300 g', 'price' => 220, 'imageUrl' => 'images\\Hoisin Sauce Sweet.jpg', 'category' => 'sauces', 'baseDesc' => 'Sweet hoisin sauce 300g.'],
    ['id' => 351, 'name' => 'Black Bean Sauce', 'size' => '300 g', 'price' => 210, 'imageUrl' => 'images\\Black Bean Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Black bean sauce 300g.'],
    ['id' => 353, 'name' => 'Black Bean Spicy Sauce', 'size' => '300 g', 'price' => 220, 'imageUrl' => 'images\\Black Bean Spicy Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy black bean sauce 300g.'],
    ['id' => 356, 'name' => 'Worcestershire Sauce', 'size' => '300 ml', 'price' => 210, 'imageUrl' => 'images\\Worcestershire Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Worcestershire sauce 300ml.'],
    ['id' => 360, 'name' => 'Worcestershire Classic Sauce', 'size' => '300 ml', 'price' => 220, 'imageUrl' => 'images\\Worcestershire Classic Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic Worcestershire sauce 300ml.'],
    ['id' => 361, 'name' => 'Italian Herb Sauce', 'size' => '350 g', 'price' => 210, 'imageUrl' => 'images\\Italian Herb Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Italian herb sauce 350g.'],
    ['id' => 365, 'name' => 'Italian Herb Classic Sauce', 'size' => '350 g', 'price' => 220, 'imageUrl' => 'images\\Italian Herb Classic Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic Italian herb sauce 350g.'],
    ['id' => 366, 'name' => 'Mediterranean Garlic Sauce', 'size' => '350 g', 'price' => 210, 'imageUrl' => 'images\\Mediterranean Garlic Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Mediterranean garlic sauce 350g.'],
    ['id' => 367, 'name' => 'Mediterranean Lemon Sauce', 'size' => '350 g', 'price' => 215, 'imageUrl' => 'images\\Mediterranean Lemon Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Mediterranean lemon sauce 350g.'],
    ['id' => 368, 'name' => 'Mediterranean Herb Sauce', 'size' => '350 g', 'price' => 220, 'imageUrl' => 'images\\Mediterranean Herb Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Mediterranean herb sauce 350g.'],
    ['id' => 369, 'name' => 'Mediterranean Premium Sauce', 'size' => '400 g', 'price' => 230, 'imageUrl' => 'images\\Mediterranean Premium Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium Mediterranean sauce 400g.'],
    ['id' => 370, 'name' => 'Mediterranean Restaurant Sauce', 'size' => '450 g', 'price' => 240, 'imageUrl' => 'images\\Mediterranean Restaurant Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Restaurant Mediterranean sauce 450g.'],
    ['id' => 371, 'name' => 'Smoky Chipotle Sauce', 'size' => '300 g', 'price' => 210, 'imageUrl' => 'images\\Smoky Chipotle Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Smoky chipotle sauce 300g.'],
    ['id' => 372, 'name' => 'Smoky Chipotle Garlic Sauce', 'size' => '300 g', 'price' => 215, 'imageUrl' => 'images\\Smoky Chipotle Garlic Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic smoky chipotle sauce 300g.'],
    ['id' => 373, 'name' => 'Smoky Chipotle Premium Sauce', 'size' => '350 g', 'price' => 225, 'imageUrl' => 'images\\Smoky Chipotle Premium Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium smoky chipotle sauce 350g.'],
    ['id' => 374, 'name' => 'Smoky Chipotle Restaurant Sauce', 'size' => '400 g', 'price' => 235, 'imageUrl' => 'images\\Smoky Chipotle Restaurant Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Restaurant smoky chipotle sauce 400g.'],
    ['id' => 375, 'name' => 'Smoky Chipotle Classic Sauce', 'size' => '300 g', 'price' => 220, 'imageUrl' => 'images\\Smoky Chipotle Classic Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic smoky chipotle sauce 300g.'],
    ['id' => 376, 'name' => 'Classic Burger Sauce', 'size' => '300 g', 'price' => 200, 'imageUrl' => 'images\\Classic Burger Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic burger sauce 300g.'],
    ['id' => 377, 'name' => 'Spicy Burger Sauce', 'size' => '300 g', 'price' => 205, 'imageUrl' => 'images\\Spicy Burger Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy burger sauce 300g.'],
    ['id' => 378, 'name' => 'Garlic Burger Sauce', 'size' => '300 g', 'price' => 210, 'imageUrl' => 'images\\Garlic Burger Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic burger sauce 300g.'],
    ['id' => 379, 'name' => 'Premium Burger Sauce', 'size' => '350 g', 'price' => 220, 'imageUrl' => 'images\\Premium Burger Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium burger sauce 350g.'],
    ['id' => 380, 'name' => 'Restaurant Burger Sauce', 'size' => '400 g', 'price' => 230, 'imageUrl' => 'images\\Restaurant Burger Sauce.jpg', 'category' => 'sauces', 'baseDesc' => 'Restaurant burger sauce 400g.'],
    ['id' => 381, 'name' => 'Sandwich Spread Classic', 'size' => '300 g', 'price' => 190, 'imageUrl' => 'images\\Sandwich Spread Classic.jpg', 'category' => 'sauces', 'baseDesc' => 'Classic sandwich spread 300g.'],
    ['id' => 382, 'name' => 'Sandwich Spread Garlic', 'size' => '300 g', 'price' => 195, 'imageUrl' => 'images\\Sandwich Spread Garlic.jpg', 'category' => 'sauces', 'baseDesc' => 'Garlic sandwich spread 300g.'],
    ['id' => 383, 'name' => 'Sandwich Spread Spicy', 'size' => '300 g', 'price' => 200, 'imageUrl' => 'images\\Sandwich Spread Spicy.jpg', 'category' => 'sauces', 'baseDesc' => 'Spicy sandwich spread 300g.'],
    ['id' => 384, 'name' => 'Sandwich Spread Premium', 'size' => '350 g', 'price' => 210, 'imageUrl' => 'images\\Sandwich Spread Premium.jpg', 'category' => 'sauces', 'baseDesc' => 'Premium sandwich spread 350g.'],
    ['id' => 385, 'name' => 'Sandwich Spread Restaurant', 'size' => '400 g', 'price' => 220, 'imageUrl' => 'images\\Sandwich Spread Restaurant.jpg', 'category' => 'sauces', 'baseDesc' => 'Restaurant sandwich spread 400g.'],
    ['id' => 386, 'name' => 'Exotic Fruit Chutney', 'size' => '300 g', 'price' => 180, 'imageUrl' => 'images\\Exotic Fruit Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Exotic fruit chutney 300g.'],
    ['id' => 387, 'name' => 'Exotic Fruit Spicy Chutney', 'size' => '300 g', 'price' => 185, 'imageUrl' => 'images\\Exotic Fruit Spicy Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Spicy exotic fruit chutney 300g.'],
    ['id' => 388, 'name' => 'Exotic Fruit Garlic Chutney', 'size' => '300 g', 'price' => 190, 'imageUrl' => 'images\\Exotic Fruit Garlic Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Garlic exotic fruit chutney 300g.'],
    ['id' => 389, 'name' => 'Exotic Fruit Premium Chutney', 'size' => '350 g', 'price' => 200, 'imageUrl' => 'images\\Exotic Fruit Premium Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Premium exotic fruit chutney 350g.'],
    ['id' => 390, 'name' => 'Exotic Fruit Restaurant Chutney', 'size' => '400 g', 'price' => 210, 'imageUrl' => 'images\\Exotic Fruit Restaurant Chutney.jpg', 'category' => 'chutneys', 'baseDesc' => 'Restaurant exotic fruit chutney 400g.'],
    ['id' => 391, 'name' => 'Mango Sweet Pickle Premium', 'size' => '400 g', 'price' => 170, 'imageUrl' => 'images\\Mango Sweet Pickle Premium.jpg', 'category' => 'pickles', 'baseDesc' => 'Premium sweet mango pickle 400g.'],
    ['id' => 392, 'name' => 'Lemon Sweet Pickle Premium', 'size' => '400 g', 'price' => 165, 'imageUrl' => 'images\\Lemon Sweet Pickle Premium.jpg', 'category' => 'pickles', 'baseDesc' => 'Premium sweet lemon pickle 400g.'],
    ['id' => 393, 'name' => 'Garlic Sweet Pickle', 'size' => '350 g', 'price' => 175, 'imageUrl' => 'images\\Garlic Sweet Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Sweet garlic pickle 350g.'],
    ['id' => 394, 'name' => 'Ginger Sweet Pickle', 'size' => '350 g', 'price' => 250, 'imageUrl' => 'images\\Ginger Sweet Pickle (350 g).jpg', 'category' => 'pickles', 'baseDesc' => 'Sweet ginger pickle 350g.'],
    ['id' => 395, 'name' => 'Chilli Sweet Pickle', 'size' => '350 g', 'price' => 175, 'imageUrl' => 'images\\Chilli Sweet Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Sweet chilli pickle 350g.'],
    ['id' => 396, 'name' => 'Traditional Village Mango Pickle', 'size' => '500 g', 'price' => 190, 'imageUrl' => 'images\\Traditional Village Mango Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Traditional village mango pickle 500g.'],
    ['id' => 397, 'name' => 'Traditional Village Lemon Pickle', 'size' => '500 g', 'price' => 185, 'imageUrl' => 'images\\Traditional Village Lemon Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Traditional village lemon pickle 500g.'],
    ['id' => 398, 'name' => 'Traditional Village Garlic Pickle', 'size' => '500 g', 'price' => 195, 'imageUrl' => 'images\\Traditional Village Garlic Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Traditional village garlic pickle 500g.'],
    ['id' => 399, 'name' => 'Traditional Village Mixed Pickle', 'size' => '500 g', 'price' => 200, 'imageUrl' => 'images\\Traditional Village Mixed Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Traditional village mixed pickle 500g.'],
    ['id' => 400, 'name' => 'Traditional Village Special Pickle', 'size' => '500 g', 'price' => 210, 'imageUrl' => 'images\\Traditional Village Special Pickle.jpg', 'category' => 'pickles', 'baseDesc' => 'Traditional village special pickle 500g.']
  ];
  foreach ($staticProducts as $item) {
    $productsData[] = [
      'id' => $item['id'],
      'name' => $item['name'],
      'size' => $item['size'],
      'price' => $item['price'],
      'imageUrl' => $item['imageUrl'],
      'category' => $item['category'],
      'baseDesc' => $item['baseDesc']
    ];
  }
}
// Build the product array for JavaScript (including display name with size)
$productList = [];
foreach ($productsData as $p) {
  $productList[] = [
    'id' => $p['id'],
    'name' => $p['name'] . ' (' . $p['size'] . ')',
    'kn' => $p['name'] . ' ' . $p['size'] . ' · ' . ($p['category'] === 'pickles' ? 'ಉಪ್ಪಿನಕಾಯಿ' : ($p['category'] === 'sauces' ? 'ಸಾಸ್' : 'ಚಟ್ನಿ')),
    'category' => $p['category'],
    'price' => $p['price'],
    'imageUrl' => $p['imageUrl'],
    'baseDesc' => $p['baseDesc']
  ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
  <title>Swad & Achaar · Artisan Pickles & Sauces | Rustic Flavours</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    /* ===== SAME CSS AS STATIC VERSION ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      display: block;
    }

    .app-container {
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
    }

    .shop-header {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
      margin-bottom: 32px;
    }

    .brand h1 {
      font-family: 'Space Grotesk', monospace;
      font-size: 2.1rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      background: linear-gradient(125deg, #b54a35, #e58c4a);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .brand .tagline {
      font-size: 0.85rem;
      color: #d27f4a;
      font-weight: 500;
    }

    .cart-link {
      background: #fff0e6;
      border-radius: 80px;
      padding: 12px 24px;
      display: inline-flex;
      align-items: center;
      gap: 14px;
      transition: 0.2s;
      border: 1px solid #ffdfce;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.02);
      text-decoration: none;
      color: #b54a35;
      font-weight: 600;
      font-size: 0.95rem;
    }

    .cart-link:hover {
      background: #ffe4d4;
      transform: scale(1.01);
    }

    .cart-link i {
      font-size: 1.5rem;
      color: #b54a35;
    }

    .cart-link .cart-count {
      background: #b54a35;
      color: white;
      font-weight: 700;
      padding: 2px 12px;
      border-radius: 60px;
      font-size: 1rem;
      margin-left: 4px;
    }

    .search-section {
      margin-bottom: 32px;
    }

    .search-box {
      background: white;
      border-radius: 70px;
      padding: 8px 24px;
      display: flex;
      align-items: center;
      border: 1px solid #ffdecb;
      gap: 12px;
      margin-bottom: 24px;
      transition: 0.2s;
    }

    .search-box:focus-within {
      border-color: #e58c4a;
      box-shadow: 0 0 0 2px rgba(229, 140, 74, 0.2);
    }

    .search-box i {
      font-size: 1.2rem;
      color: #e58c4a;
    }

    .search-box input {
      flex: 1;
      border: none;
      padding: 14px 0;
      background: transparent;
      font-size: 1rem;
      outline: none;
      font-weight: 500;
    }

    .filter-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .pill {
      background: #fff3ea;
      padding: 10px 24px;
      border-radius: 60px;
      font-weight: 600;
      font-size: 0.85rem;
      color: #b54a35;
      cursor: pointer;
      white-space: nowrap;
      border: 1px solid #ffe0ce;
      transition: all 0.15s;
    }

    .pill.active {
      background: #b54a35;
      color: white;
      border-color: #b54a35;
      box-shadow: 0 6px 12px rgba(181, 74, 53, 0.2);
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 28px;
      margin: 32px 0 48px;
    }

    .product-card {
      background: #fffefc;
      border-radius: 32px;
      padding: 20px 16px 22px;
      transition: all 0.25s ease;
      border: 1px solid #fae2d2;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02);
      display: flex;
      flex-direction: column;
    }

    .product-card:hover {
      transform: translateY(-5px);
      border-color: #ffcdb2;
      box-shadow: 0 20px 30px -12px rgba(100, 55, 35, 0.12);
    }

    .product-img {
      width: 88px;
      height: 88px;
      object-fit: cover;
      border-radius: 28px;
      background: #fef0e6;
      border: 2px solid #ffe2cf;
      margin-bottom: 16px;
      box-shadow: 0 6px 10px rgba(0, 0, 0, 0.02);
    }

    .product-name {
      font-weight: 800;
      font-size: 1.1rem;
      color: #2f241c;
      line-height: 1.3;
    }

    .product-name-kn {
      font-size: 0.7rem;
      color: #c28562;
      margin: 5px 0 10px;
      font-weight: 500;
    }

    .product-description {
      font-size: 0.78rem;
      background: #fffaf5;
      padding: 8px 12px;
      border-radius: 28px;
      color: #6d4c38;
      margin: 8px 0 14px;
      line-height: 1.4;
      border-left: 3px solid #e5935a;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      margin-top: 6px;
    }

    .price {
      font-weight: 800;
      font-size: 1.5rem;
      color: #b54a35;
      letter-spacing: -0.02em;
    }

    .price small {
      font-size: 0.7rem;
      font-weight: 500;
      color: #c88762;
    }

    .descriptive-cart-btn {
      background: linear-gradient(100deg, #b54a35, #e07a48);
      border: none;
      border-radius: 60px;
      padding: 12px 8px;
      font-weight: 700;
      font-size: 0.72rem;
      color: white;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 16px;
      transition: 0.2s;
      box-shadow: 0 4px 12px rgba(181, 74, 53, 0.2);
      letter-spacing: 0.3px;
    }

    .descriptive-cart-btn i {
      font-size: 0.8rem;
    }

    .descriptive-cart-btn:hover {
      transform: scale(0.96);
      background: #9a3c2a;
    }

    .design-credits {
      margin-top: 20px;
      text-align: center;
      font-size: 0.68rem;
      color: #c2815a;
    }

    footer {
      text-align: center;
      font-size: 0.72rem;
      color: #c99874;
      margin-top: 28px;
      padding-top: 16px;
      border-top: 1px solid #f7e3d5;
    }

    @media (max-width: 640px) {
      .product-grid {
        gap: 18px;
      }

      .product-img {
        width: 72px;
        height: 72px;
      }

      .price {
        font-size: 1.3rem;
      }
    }
  </style>
  <link rel="stylesheet" href="category-style.css">
  <link rel="stylesheet" href="../index.css">
</head>

<body>
  <div class="app-container">
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
        <div class="pill" data-category="pickles">Pickles / ಉಪ್ಪಿನಕಾಯಿ</div>
        <div class="pill" data-category="sauces">Sauces / ಸಾಸ್</div>
        <div class="pill" data-category="chutneys">Chutneys / ಚಟ್ನಿ</div>
      </div>
    </div>
    <div class="product-grid" id="productGrid"></div>
    <div class="design-credits">
      <i class="fa-regular fa-compass"></i> craft inspired by · heritage spices ·
      <span style="color:#b54a35;">descriptive notes & warm terracotta</span>
    </div>
  </div>
  <script>
    // ============================================================
    // JAVASCRIPT – SESSION-BASED CART VIA AJAX + LOCALSTORAGE FALLBACK
    // ============================================================
    (function() {
      // ----- PRODUCT DATA FROM PHP (embedded) -----
      const productsData = <?php echo json_encode($productList); ?>;
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
      // ----- HELPER: descriptive button text based on category & name -----
      function getBtnText(name, category) {
        const lower = name.toLowerCase();
        if (category === 'pickles') return "➕ spicy crunch";
        if (category === 'sauces') {
          if (lower.includes('tomato')) return "➕ umami dip";
          if (lower.includes('chilli') || lower.includes('schezwan')) return "➕ bold heat";
          return "➕ savoury swirl";
        }
        if (category === 'chutneys') return "➕ zesty dip";
        return "➕ add flavour";
      }
      // Build full product array with description and button text
      const products = productsData.map(p => ({
        id: p.id,
        nameEn: p.name,
        nameKn: p.kn,
        category: p.category,
        price: p.price,
        image: p.imageUrl,
        description: p.baseDesc,
        btnLabel: getBtnText(p.name, p.category)
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
          if (term) {
            return p.nameEn.toLowerCase().includes(term) || p.nameKn.toLowerCase().includes(term);
          }
          return true;
        });
        if (filtered.length === 0) {
          productGrid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:3rem; color:#c28562;"><i class="fa-regular fa-face-frown"></i> No pickles found · try a different flavour</div>`;
          return;
        }
        productGrid.innerHTML = filtered.map(p => `
            <div class="product-card" data-id="${p.id}">
                <img class="product-img" src="${p.image}" alt="" loading="lazy" onerror="this.src='https://placehold.co/88x88/fef0e6/b54a35?text=🌶️'">
                <div class="product-name">${p.nameEn.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m] || m)}</div>
                <div class="product-name-kn">${p.nameKn.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m] || m)}</div>
                <div class="product-description"><i class="fa-regular fa-lemon"></i> ${p.description}</div>
                <div class="price-row">
                    <span class="price">₹${p.price} <small>inr</small></span>
                    <button class="descriptive-cart-btn" data-id="${p.id}" data-add>
                        <i class="fa-solid fa-bowl-food"></i>
                        <span>${p.btnLabel}</span>
                    </button>
                </div>
            </div>
        `).join('');
        document.querySelectorAll('.descriptive-cart-btn').forEach(btn => {
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