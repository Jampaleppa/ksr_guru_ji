<?php
// ============================================================
// ready-to-eat.php - Dynamic Ready To Eat page
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
$categorySlug = 'ready-to-eat';
$readyCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($readyCategory) {
  // Fetch products from this category
  $dbProducts = getProductsByCategory($readyCategory['id']);
  if (!empty($dbProducts)) {
    foreach ($dbProducts as $p) {
      // Build description from description_en or weight
      $description = $p['description_en'] ?? ($p['weight'] ?? 'Ready in minutes');
      $productsData[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'kn' => $p['kn_name'] ?? $p['name'] . ' (ಸಿದ್ಧ ಊಟ)',
        'category' => $p['category_slug'] ?? 'rice',
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
  // Embedded static product data (same as original HTML)
  $rawProducts = [
    ['id' => 1, 'nameEn' => 'Veg Biryani Ready Meal', 'nameKn' => 'ವೆಜ್ ಬಿರಿಯಾನಿ', 'category' => 'rice', 'price' => 120, 'imageurl' => "images\\Veg Biryani Ready Meal.jpg"],
    ['id' => 2, 'nameEn' => 'Chicken Biryani Ready Meal', 'nameKn' => 'ಚಿಕನ್ ಬಿರಿಯಾನಿ', 'category' => 'rice', 'price' => 180, 'imageurl' => "images\\Chicken Biryani Ready Meal.jpg"],
    ['id' => 3, 'nameEn' => 'Paneer Biryani Ready Meal', 'nameKn' => 'ಪನೀರ್ ಬಿರಿಯಾನಿ', 'category' => 'rice', 'price' => 160, 'imageurl' => "images\\Paneer Biryani Ready Meal.jpg"],
    ['id' => 4, 'nameEn' => 'Veg Fried Rice', 'nameKn' => 'ವೆಜ್ ಫ್ರೈಡ್ ರೈಸ್', 'category' => 'rice', 'price' => 110, 'imageurl' => "images\\Veg Fried Rice.jpg"],
    ['id' => 5, 'nameEn' => 'Schezwan Fried Rice', 'nameKn' => 'ಸ್ಚೆಜ್ವಾನ್ ಫ್ರೈಡ್ ರೈಸ್', 'category' => 'rice', 'price' => 130, 'imageurl' => "images\\Schezwan Fried Rice.jpg"],
    ['id' => 6, 'nameEn' => 'Chicken Fried Rice', 'nameKn' => 'ಚಿಕನ್ ಫ್ರೈಡ್ ರೈಸ್', 'category' => 'rice', 'price' => 160, 'imageurl' => "images\\Chicken Fried Rice.jpg"],
    ['id' => 7, 'nameEn' => 'Veg Pulao', 'nameKn' => 'ವೆಜ್ ಪುಲಾವ್', 'category' => 'rice', 'price' => 100, 'imageurl' => "images\\Veg Pulao.jpg"],
    ['id' => 8, 'nameEn' => 'Jeera Rice', 'nameKn' => 'ಜೀರಾ ರೈಸ್', 'category' => 'rice', 'price' => 90, 'imageurl' => "images\\Jeera Rice.jpg"],
    ['id' => 9, 'nameEn' => 'Lemon Rice', 'nameKn' => 'ನಿಂಬೆ ಅನ್ನ', 'category' => 'rice', 'price' => 80, 'imageurl' => "images\\Lemon Rice.jpg"],
    ['id' => 10, 'nameEn' => 'Tamarind Rice', 'nameKn' => 'ಹುಣಸೆ ಅನ್ನ', 'category' => 'rice', 'price' => 80, 'imageurl' => "images\\Tamarind Rice.jpg"],
    ['id' => 11, 'nameEn' => 'Curd Rice', 'nameKn' => 'ಮೊಸರು ಅನ್ನ', 'category' => 'rice', 'price' => 70, 'imageurl' => "images\\Curd Rice.jpg"],
    ['id' => 12, 'nameEn' => 'Coconut Rice', 'nameKn' => 'ತೆಂಗಿನ ಅನ್ನ', 'category' => 'rice', 'price' => 80, 'imageurl' => "images\\Coconut Rice.jpg"],
    ['id' => 13, 'nameEn' => 'Tomato Rice', 'nameKn' => 'ಟೊಮೇಟೊ ಅನ್ನ', 'category' => 'rice', 'price' => 90, 'imageurl' => "images\\Tomato Rice.jpg"],
    ['id' => 14, 'nameEn' => 'Dal Khichdi', 'nameKn' => 'ದಾಲ್ ಖಿಚಡಿ', 'category' => 'rice', 'price' => 110, 'imageurl' => "images\\Dal Khichdi.jpg"],
    ['id' => 15, 'nameEn' => 'Veg Khichdi', 'nameKn' => 'ವೆಜ್ ಖಿಚಡಿ', 'category' => 'rice', 'price' => 100, 'imageurl' => "images\\Veg Khichdi.jpg"],
    ['id' => 16, 'nameEn' => 'Masala Khichdi', 'nameKn' => 'ಮಸಾಲಾ ಖಿಚಡಿ', 'category' => 'rice', 'price' => 110, 'imageurl' => "images\\Masala Khichdi.jpg"],
    ['id' => 17, 'nameEn' => 'Rajma Chawal', 'nameKn' => 'ರಾಜ್ಮಾ ಚಾವಲ್', 'category' => 'meals', 'price' => 140, 'imageurl' => "images\\Rajma Chawal.jpg"],
    ['id' => 18, 'nameEn' => 'Chole Chawal', 'nameKn' => 'ಛೋಲೆ ಚಾವಲ್', 'category' => 'meals', 'price' => 140, 'imageurl' => "images\\Chole Chawal.jpg"],
    ['id' => 19, 'nameEn' => 'Paneer Butter Masala Rice', 'nameKn' => 'ಪನೀರ್ ಬಟರ್ ಮಸಾಲಾ ರೈಸ್', 'category' => 'curries', 'price' => 160, 'imageurl' => "images\\Paneer Butter Masala Rice.jpg"],
    ['id' => 20, 'nameEn' => 'Dal Tadka Rice Bowl', 'nameKn' => 'ದಾಲ್ ತಡ್ಕಾ ರೈಸ್', 'category' => 'curries', 'price' => 130, 'imageurl' => "images\\Dal Tadka Rice Bowl.jpg"],
    ['id' => 21, 'nameEn' => 'Veg Noodles', 'nameKn' => 'ವೆಜ್ ನೂಡಲ್ಸ್', 'category' => 'meals', 'price' => 90, 'imageurl' => "images\\Veg Noodles.jpg"],
    ['id' => 22, 'nameEn' => 'Schezwan Noodles', 'nameKn' => 'ಸ್ಚೆಜ್ವಾನ್ ನೂಡಲ್ಸ್', 'category' => 'meals', 'price' => 110, 'imageurl' => "images\\Schezwan Noodles.jpg"],
    ['id' => 23, 'nameEn' => 'Chicken Noodles', 'nameKn' => 'ಚಿಕನ್ ನೂಡಲ್ಸ್', 'category' => 'meals', 'price' => 150, 'imageurl' => "images\\Chicken Noodles.jpg"],
    ['id' => 24, 'nameEn' => 'Hakka Noodles', 'nameKn' => 'ಹಕ್ಕಾ ನೂಡಲ್ಸ್', 'category' => 'meals', 'price' => 100, 'imageurl' => "images\\Hakka Noodles.jpg"],
    ['id' => 25, 'nameEn' => 'Veg Chowmein', 'nameKn' => 'ವೆಜ್ ಚೌಮೀನ್', 'category' => 'meals', 'price' => 100, 'imageurl' => "images\\Veg Chowmein.jpg"],
    ['id' => 26, 'nameEn' => 'Paneer Chowmein', 'nameKn' => 'ಪನೀರ್ ಚೌಮೀನ್', 'category' => 'meals', 'price' => 130, 'imageurl' => "images\\Paneer Chowmein.jpg"],
    ['id' => 27, 'nameEn' => 'Veg Manchurian Noodles', 'nameKn' => 'ಮಂಚೂರಿಯನ್ ನೂಡಲ್ಸ್', 'category' => 'meals', 'price' => 140, 'imageurl' => "images\\Veg Manchurian Noodles.jpg"],
    ['id' => 28, 'nameEn' => 'Chicken Manchurian Noodles', 'nameKn' => 'ಚಿಕನ್ ಮಂಚೂರಿಯನ್', 'category' => 'meals', 'price' => 180, 'imageurl' => "images\\Chicken Manchurian Noodles.jpg"],
    ['id' => 29, 'nameEn' => 'Cheese Nuggets', 'nameKn' => 'ಚೀಸ್ ನಗೆಟ್ಸ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Cheese Nuggets.jpg"],
    ['id' => 30, 'nameEn' => 'Cheese Pasta Bowl', 'nameKn' => 'ಚೀಸ್ ಪಾಸ್ತಾ ಬೌಲ್', 'category' => 'pasta', 'price' => 180, 'imageurl' => "images\\Cheese Pasta Bowl.jpg"],
    ['id' => 31, 'nameEn' => 'White Sauce Pasta', 'nameKn' => 'ವೈಟ್ ಸಾಸ್ ಪಾಸ್ತಾ', 'category' => 'meals', 'price' => 120, 'imageurl' => "images\\White Sauce Pasta.jpg"],
    ['id' => 32, 'nameEn' => 'Red Sauce Pasta', 'nameKn' => 'ರೆಡ್ ಸಾಸ್ ಪಾಸ್ತಾ', 'category' => 'meals', 'price' => 110, 'imageurl' => "images\\Red Sauce Pasta.jpg"],
    ['id' => 33, 'nameEn' => 'Cheese Pizza Slice', 'nameKn' => 'ಚೀಸ್ ಪಿಜ್ಜಾ ಸ್ಲೈಸ್', 'category' => 'pizza', 'price' => 180, 'imageurl' => "images\\Cheese Pizza Slice.jpg"],
    ['id' => 34, 'nameEn' => 'Cheese Sandwich', 'nameKn' => 'ಚೀಸ್ ಸ್ಯಾಂಡ್‌ವಿಚ್', 'category' => 'sandwich', 'price' => 180, 'imageurl' => "images\\Cheese Sandwich.jpg"],
    ['id' => 35, 'nameEn' => 'Veg Burger', 'nameKn' => 'ವೆಜ್ ಬರ್ಗರ್', 'category' => 'snacks', 'price' => 70, 'imageurl' => "images\\Veg Burger.jpg"],
    ['id' => 36, 'nameEn' => 'Chicken Burger', 'nameKn' => 'ಚಿಕನ್ ಬರ್ಗರ್', 'category' => 'snacks', 'price' => 120, 'imageurl' => "images\\Chicken Burger.jpg"],
    ['id' => 37, 'nameEn' => 'Paneer Burger', 'nameKn' => 'ಪನೀರ್ ಬರ್ಗರ್', 'category' => 'snacks', 'price' => 90, 'imageurl' => "images\\Paneer Burger.jpg"],
    ['id' => 38, 'nameEn' => 'Chicken Burrito', 'nameKn' => 'ಚಿಕನ್ ಬುರಿಟೊ', 'category' => 'burrito', 'price' => 180, 'imageurl' => "images\\Chicken Burrito.jpg"],
    ['id' => 39, 'nameEn' => 'Chicken Cutlet Pack', 'nameKn' => 'ಚಿಕನ್ ಕಟ್ಲೆಟ್ ಪ್ಯಾಕ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Chicken Cutlet Pack.jpg"],
    ['id' => 40, 'nameEn' => 'Chicken Dinner Combo', 'nameKn' => 'ಚಿಕನ್ ಡಿನ್ನರ್ ಕಾಂಬೊ', 'category' => 'combo', 'price' => 180, 'imageurl' => "images\\Chicken Dinner Combo.jpg"],
    ['id' => 41, 'nameEn' => 'Veg Wrap', 'nameKn' => 'ವೆಜ್ ರ್ಯಾಪ್', 'category' => 'snacks', 'price' => 90, 'imageurl' => "images\\Veg Wrap.jpg"],
    ['id' => 42, 'nameEn' => 'Paneer Wrap', 'nameKn' => 'ಪನೀರ್ ರ್ಯಾಪ್', 'category' => 'snacks', 'price' => 120, 'imageurl' => "images\\Paneer Wrap.jpg"],
    ['id' => 43, 'nameEn' => 'Chicken Wrap', 'nameKn' => 'ಚಿಕನ್ ರ್ಯಾಪ್', 'category' => 'snacks', 'price' => 150, 'imageurl' => "images\\Chicken Wrap.jpg"],
    ['id' => 44, 'nameEn' => 'Chicken Lunch Combo', 'nameKn' => 'ಚಿಕನ್ ಲಂಚ್ ಕಾಂಬೊ', 'category' => 'combo', 'price' => 180, 'imageurl' => "images\\Chicken Lunch Combo.jpg"],
    ['id' => 45, 'nameEn' => 'Chicken Momos', 'nameKn' => 'ಚಿಕನ್ ಮೊಮೊಸ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Chicken Momos.jpg"],
    ['id' => 46, 'nameEn' => 'Chicken Samosa', 'nameKn' => 'ಚಿಕನ್ ಸಮೋಸಾ', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Chicken Samosa.jpg"],
    ['id' => 47, 'nameEn' => 'Chicken Noodle Bowl', 'nameKn' => 'ಚಿಕನ್ ನೂಡಲ್ ಬೌಲ್', 'category' => 'meals', 'price' => 180, 'imageurl' => "images\\Chicken Noodle Bowl.jpg"],
    ['id' => 48, 'nameEn' => 'Chicken Noodles', 'nameKn' => 'ಚಿಕನ್ ನೂಡಲ್ಸ್', 'category' => 'meals', 'price' => 180, 'imageurl' => "images\\Chicken Noodles.jpg"],
    ['id' => 49, 'nameEn' => 'Chicken Nuggets Pack', 'nameKn' => 'ಚಿಕನ್ ನಗೆಟ್ಸ್ ಪ್ಯಾಕ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Chicken Nuggets Pack.jpg"],
    ['id' => 50, 'nameEn' => 'Chicken Pasta Bowl', 'nameKn' => 'ಚಿಕನ್ ಪಾಸ್ತಾ ಬೌಲ್', 'category' => 'meals', 'price' => 180, 'imageurl' => "images\\Chicken Pasta Bowl.jpg"],
    ['id' => 51, 'nameEn' => 'Chicken Pizza Slice', 'nameKn' => 'ಚಿಕನ್ ಪಿಜ್ಜಾ ಸ್ಲೈಸ್', 'category' => 'pizza', 'price' => 180, 'imageurl' => "images\\Chicken Pizza Slice.jpg"],
    ['id' => 52, 'nameEn' => 'Veg Samosa Pack', 'nameKn' => 'ಸಮೋಸಾ ಪ್ಯಾಕ್', 'category' => 'snacks', 'price' => 50, 'imageurl' => "images\\Veg Samosa Pack.jpg"],
    ['id' => 53, 'nameEn' => 'Chicken Puff', 'nameKn' => 'ಚಿಕನ್ ಪಫ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Chicken Puff.jpg"],
    ['id' => 54, 'nameEn' => 'Chicken Shawarma Wrap', 'nameKn' => 'ಚಿಕನ್ ಶಾವರ್ಮಾ ರ್ಯಾಪ್', 'category' => 'wrap', 'price' => 180, 'imageurl' => "images\\Chicken Shawarma Wrap.jpg"],
    ['id' => 55, 'nameEn' => 'Veg Spring Rolls', 'nameKn' => 'ಸ್ಪ್ರಿಂಗ್ ರೋಲ್', 'category' => 'snacks', 'price' => 90, 'imageurl' => "images\\Veg Spring Rolls.jpg"],
    ['id' => 56, 'nameEn' => 'Chicken Snack Bowl', 'nameKn' => 'ಚಿಕನ್ ಸ್ನ್ಯಾಕ್ ಬೌಲ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Chicken Snack Bowl.jpg"],
    ['id' => 57, 'nameEn' => 'Chicken Snack Combo Pack', 'nameKn' => 'ಚಿಕನ್ ಸ್ನ್ಯಾಕ್ ಕಾಂಬೊ ಪ್ಯಾಕ್', 'category' => 'combo', 'price' => 180, 'imageurl' => "images\\Chicken Snack Combo Pack.jpg"],
    ['id' => 58, 'nameEn' => 'Chicken Spring Rolls', 'nameKn' => 'ಚಿಕನ್ ಸ್ಪ್ರಿಂಗ್ ರೋಲ್ಸ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Chicken Spring Rolls.jpg"],
    ['id' => 59, 'nameEn' => 'Chicken Taco Meal', 'nameKn' => 'ಚಿಕನ್ ಟ್ಯಾಕೋ ಮೀಲ್', 'category' => 'taco', 'price' => 180, 'imageurl' => "images\\Chicken Taco Meal.jpg"],
    ['id' => 60, 'nameEn' => 'Chicken Wrap', 'nameKn' => 'ಚಿಕನ್ ರ್ಯಾಪ್', 'category' => 'wrap', 'price' => 180, 'imageurl' => "images\\Chicken Noodles.jpg"],
    ['id' => 61, 'nameEn' => 'Chole Chawal', 'nameKn' => 'ಛೋಲೆ ಚಾವಲ್', 'category' => 'meals', 'price' => 180, 'imageurl' => "images\\Chole Chawal.jpg"],
    ['id' => 63, 'nameEn' => 'Corn Nuggets', 'nameKn' => 'ಕಾರ್ನ್ ನಗೆಟ್ಸ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Corn Nuggets.jpg"],
    ['id' => 65, 'nameEn' => 'Crispy Potato Bites', 'nameKn' => 'ಕ್ರಿಸ್ಪಿ ಪೊಟಾಟೊ ಬೈಟ್ಸ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Crispy Potato Bites.jpg"],
    ['id' => 66, 'nameEn' => 'Curly Fries Pack', 'nameKn' => 'ಕರ್ಲಿ ಫ್ರೈಸ್ ಪ್ಯಾಕ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Curly Fries Pack.jpg"],
    ['id' => 67, 'nameEn' => 'Dal Makhani Rice Bowl', 'nameKn' => 'ದಾಲ್ ಮಖಾನಿ ರೈಸ್ ಬೌಲ್', 'category' => 'curries', 'price' => 180, 'imageurl' => "images\\Dal Makhani Rice Bowl.jpg"],
    ['id' => 69, 'nameEn' => 'Egg Puff', 'nameKn' => 'ಎಗ್ ಪಫ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Egg Puff.jpg"],
    ['id' => 70, 'nameEn' => 'Garlic Bread Cheese', 'nameKn' => 'ಗಾರ್ಲಿಕ್ ಬ್ರೆಡ್ ಚೀಸ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Garlic Bread Cheese.jpg"],
    ['id' => 71, 'nameEn' => 'Instant Pasta Cheese', 'nameKn' => 'ಇನ್ಸ್ಟಂಟ್ ಪಾಸ್ತಾ ಚೀಸ್', 'category' => 'pasta', 'price' => 180, 'imageurl' => "images\\Instant Pasta Cheese.jpg"],
    ['id' => 72, 'nameEn' => 'Instant Pasta Tomato', 'nameKn' => 'ಇನ್ಸ್ಟಂಟ್ ಪಾಸ್ತಾ ಟೊಮೇಟೊ', 'category' => 'pasta', 'price' => 180, 'imageurl' => "images\\Instant Pasta Tomato.jpg"],
    ['id' => 73, 'nameEn' => 'Instant Ramen Bowl', 'nameKn' => 'ಇನ್ಸ್ಟಂಟ್ ರಾಮೆನ್ ಬೌಲ್', 'category' => 'noodles', 'price' => 180, 'imageurl' => "images\\Instant Ramen Bowl.jpg"],
    ['id' => 74, 'nameEn' => 'Macaroni Pasta', 'nameKn' => 'ಮ್ಯಾಕರೋನಿ ಪಾಸ್ತಾ', 'category' => 'pasta', 'price' => 180, 'imageurl' => "images\\Macaroni Pasta.jpg"],
    ['id' => 76, 'nameEn' => 'Mini Meal Combo Chicken', 'nameKn' => 'ಮಿನಿ ಮೀಲ್ ಕಾಂಬೊ ಚಿಕನ್', 'category' => 'combo', 'price' => 180, 'imageurl' => "images\\Mini Meal Combo Chicken.jpg"],
    ['id' => 78, 'nameEn' => 'Mini Meal Combo Veg', 'nameKn' => 'ಮಿನಿ ಮೀಲ್ ಕಾಂಬೊ ವೆಜ್', 'category' => 'combo', 'price' => 180, 'imageurl' => "images\\Mini Meal Combo Veg.jpg"],
    ['id' => 79, 'nameEn' => 'Palak Paneer Rice Bowl', 'nameKn' => 'ಪಾಲಕ್ ಪನೀರ್ ರೈಸ್ ಬೌಲ್', 'category' => 'curries', 'price' => 180, 'imageurl' => "images\\Palak Paneer Rice Bowl.jpg"],
    ['id' => 80, 'nameEn' => 'Paneer Burrito', 'nameKn' => 'ಪನೀರ್ ಬುರಿಟೊ', 'category' => 'burrito', 'price' => 180, 'imageurl' => "images\\Paneer Burrito.jpg"],
    ['id' => 82, 'nameEn' => 'Paneer Croquettes', 'nameKn' => 'ಪನೀರ್ ಕ್ರೊಕ್ವೆಟ್ಸ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Paneer Croquettes.jpg"],
    ['id' => 83, 'nameEn' => 'Paneer Dinner Combo', 'nameKn' => 'ಪನೀರ್ ಡಿನ್ನರ್ ಕಾಂಬೊ', 'category' => 'combo', 'price' => 180, 'imageurl' => "images\\Paneer Dinner Combo.jpg"],
    ['id' => 85, 'nameEn' => 'Paneer Lunch Combo', 'nameKn' => 'ಪನೀರ್ ಲಂಚ್ ಕಾಂಬೊ', 'category' => 'combo', 'price' => 180, 'imageurl' => "images\\Paneer Lunch Combo.jpg"],
    ['id' => 86, 'nameEn' => 'Paneer Momos', 'nameKn' => 'ಪನೀರ್ ಮೊಮೊಸ್', 'category' => 'snacks', 'price' => 50, 'imageurl' => "images\\Paneer Momos.jpg"],
    ['id' => 87, 'nameEn' => 'Paneer Noodle Bowl', 'nameKn' => 'ಪನೀರ್ ನೂಡಲ್ ಬೌಲ್', 'category' => 'meals', 'price' => 180, 'imageurl' => "images\\Paneer Noodle Bowl.jpg"],
    ['id' => 88, 'nameEn' => 'Paneer Nuggets Pack', 'nameKn' => 'ಪನೀರ್ ನಗೆಟ್ಸ್ ಪ್ಯಾಕ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Paneer Nuggets Pack.jpg"],
    ['id' => 89, 'nameEn' => 'Paneer Pasta Bowl', 'nameKn' => 'ಪನೀರ್ ಪಾಸ್ತಾ ಬೌಲ್', 'category' => 'snacks', 'price' => 90, 'imageurl' => "images\\Paneer Pasta Bowl.jpg"],
    ['id' => 90, 'nameEn' => 'Paneer Pizza Slice', 'nameKn' => 'ಪನೀರ್ ಪಿಜ್ಜಾ ಸ್ಲೈಸ್', 'category' => 'pizza', 'price' => 180, 'imageurl' => "images\\Paneer Pizza Slice.jpg"],
    ['id' => 92, 'nameEn' => 'Paneer Puff', 'nameKn' => 'ಪನೀರ್ ಪಫ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Paneer Puff.jpg"],
    ['id' => 93, 'nameEn' => 'Paneer Shawarma Wrap', 'nameKn' => 'ಪನೀರ್ ಶಾವರ್ಮಾ ರ್ಯಾಪ್', 'category' => 'wrap', 'price' => 180, 'imageurl' => "images\\Paneer Shawarma Wrap.jpg"],
    ['id' => 94, 'nameEn' => 'Paneer Snack Bowl', 'nameKn' => 'ಪನೀರ್ ಸ್ನ್ಯಾಕ್ ಬೌಲ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Paneer Snack Bowl.jpg"],
    ['id' => 95, 'nameEn' => 'Paneer Spring Rolls', 'nameKn' => 'ಪನೀರ್ ಸ್ಪ್ರಿಂಗ್ ರೋಲ್ಸ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Paneer Spring Rolls.jpg"],
    ['id' => 96, 'nameEn' => 'Paneer Taco Meal', 'nameKn' => 'ಪನೀರ್ ಟ್ಯಾಕೋ ಮೀಲ್', 'category' => 'taco', 'price' => 180, 'imageurl' => "images\\Paneer Taco Meal.jpg"],
    ['id' => 97, 'nameEn' => 'Paneer Tikka Rice Bowl', 'nameKn' => 'ಪನೀರ್ ಟಿಕ್ಕಾ ರೈಸ್ ಬೌಲ್', 'category' => 'curries', 'price' => 180, 'imageurl' => "images\\Paneer Tikka Rice Bowl.jpg"],
    ['id' => 98, 'nameEn' => 'Paneer Wrap', 'nameKn' => 'ಪನೀರ್ ರ್ಯಾಪ್', 'category' => 'wrap', 'price' => 180, 'imageurl' => "images\\Paneer Wrap.jpg"],
    ['id' => 99, 'nameEn' => 'Potato Smiles Pack', 'nameKn' => 'ಪೊಟಾಟೊ ಸ್ಮೈಲ್ಸ್ ಪ್ಯಾಕ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Potato Smiles Pack.jpg"],
    ['id' => 100, 'nameEn' => 'Veg Burrito', 'nameKn' => 'ವೆಜ್ ಬುರಿಟೊ', 'category' => 'burrito', 'price' => 180, 'imageurl' => "images\\Veg Burrito.jpg"],
    ['id' => 102, 'nameEn' => 'Veg Croquettes', 'nameKn' => 'ವೆಜ್ ಕ್ರೊಕ್ವೆಟ್ಸ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Veg Croquettes.jpg"],
    ['id' => 103, 'nameEn' => 'Veg Cutlet Pack', 'nameKn' => 'ವೆಜ್ ಕಟ್ಲೆಟ್ ಪ್ಯಾಕ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Veg Cutlet Pack.jpg"],
    ['id' => 104, 'nameEn' => 'Veg Dinner Combo', 'nameKn' => 'ವೆಜ್ ಡಿನ್ನರ್ ಕಾಂಬೊ', 'category' => 'combo', 'price' => 180, 'imageurl' => "images\\Veg Dinner Combo.jpg"],
    ['id' => 105, 'nameEn' => 'Veg Fried Rice', 'nameKn' => 'ವೆಜ್ ಫ್ರೈಡ್ ರೈಸ್', 'category' => 'rice', 'price' => 180, 'imageurl' => "images\\Veg Fried Rice 1.jpg"],
    ['id' => 106, 'nameEn' => 'Veg Khichd', 'nameKn' => 'ವೆಜ್ ಖಿಚಡಿ', 'category' => 'rice', 'price' => 180, 'imageurl' => "images\\Veg Khichd.jpg"],
    ['id' => 107, 'nameEn' => 'Veg Korma Rice Bowl', 'nameKn' => 'ವೆಜ್ ಕೋರ್ಮಾ ರೈಸ್ ಬೌಲ್', 'category' => 'curries', 'price' => 180, 'imageurl' => "images\\Veg Korma Rice Bowl.jpg"],
    ['id' => 108, 'nameEn' => 'Veg Sandwich', 'nameKn' => 'ವೆಜ್ ಸ್ಯಾಂಡ್‌ವಿಚ್', 'category' => 'sandwich', 'price' => 180, 'imageurl' => "images\\Veg Sandwich.jpg"],
    ['id' => 109, 'nameEn' => 'Veg Pulao', 'nameKn' => 'ವೆಜ್ ಪುಲಾವ್', 'category' => 'rice', 'price' => 180, 'imageurl' => "images\\Veg Pulao.jpg"],
    ['id' => 110, 'nameEn' => 'Palak Paneer Rice Bowl', 'nameKn' => 'ಪಾಲಕ್ ಪನೀರ್ ರೈಸ್ ಬೌಲ್', 'category' => 'curries', 'price' => 180, 'imageurl' => "images\\Palak Paneer Rice Bowl.jpg"],
    ['id' => 111, 'nameEn' => 'Paneer Burrito', 'nameKn' => 'ಪನೀರ್ ಬುರಿಟೊ', 'category' => 'burrito', 'price' => 180, 'imageurl' => "images\\Paneer Burrito.jpg"],
    ['id' => 112, 'nameEn' => 'Paneer Croquettes', 'nameKn' => 'ಪನೀರ್ ಕ್ರೊಕ್ವೆಟ್ಸ್', 'category' => 'snacks', 'price' => 180, 'imageurl' => "images\\Paneer Croquettes.jpg"],
    ['id' => 58, 'nameEn' => 'Veg Momos', 'nameKn' => 'ಮೋಮೋಸ್', 'category' => 'snacks', 'price' => 90, 'imageurl' => "images\\Veg Momos.jpg"],
    ['id' => 64, 'nameEn' => 'Veg Nuggets Pack', 'nameKn' => 'ನಗೆಟ್ಸ್', 'category' => 'snacks', 'price' => 110, 'imageurl' => "images\\Veg Nuggets Pack.jpg"],
    ['id' => 68, 'nameEn' => 'French Fries Pack', 'nameKn' => 'ಫ್ರೆಂಚ್ ಫ್ರೈಸ್', 'category' => 'snacks', 'price' => 100, 'imageurl' => "images\\French Fries Pack.jpg"],
    ['id' => 75, 'nameEn' => 'Veg Taco Meal', 'nameKn' => 'ವೆಜ್ ಟ್ಯಾಕೋ', 'category' => 'meals', 'price' => 130, 'imageurl' => "images\\Veg Taco Meal.jpg"],
    ['id' => 81, 'nameEn' => 'Veg Shawarma Wrap', 'nameKn' => 'ಶಾವರ್ಮಾ', 'category' => 'meals', 'price' => 130, 'imageurl' => "images\\Veg Shawarma Wrap.jpg"],
    ['id' => 84, 'nameEn' => 'Garlic Bread Veg', 'nameKn' => 'ಗಾರ್ಲಿಕ್ ಬ್ರೆಡ್', 'category' => 'snacks', 'price' => 100, 'imageurl' => "images\\Garlic Bread Veg.jpg"],
    ['id' => 91, 'nameEn' => 'Veg Lunch Combo', 'nameKn' => 'ಲಂಚ್ ಕಾಂಬೊ', 'category' => 'meals', 'price' => 160, 'imageurl' => "images\\Veg Lunch Combo.jpg"],
    ['id' => 101, 'nameEn' => 'Veg Rice Bowl', 'nameKn' => 'ರೈಸ್ ಬೌಲ್', 'category' => 'rice', 'price' => 120, 'imageurl' => "images\\Veg Rice Bowl.jpg"],
    ['id' => 114, 'nameEn' => 'Butter Chicken Rice Bowl', 'nameKn' => 'ಬಟರ್ ಚಿಕನ್ ರೈಸ್', 'category' => 'curries', 'price' => 180, 'imageurl' => "images\\Butter Chicken Rice Bowl.jpg"],
    ['id' => 119, 'nameEn' => 'Ready Paratha Meal', 'nameKn' => 'ಪರಾಠಾ', 'category' => 'snacks', 'price' => 120, 'imageurl' => "images\\Ready Paratha Meal.jpg"],
    ['id' => 120, 'nameEn' => 'Family Meal Pack', 'nameKn' => 'ಫ್ಯಾಮಿಲಿ ಮಿಲ್ ಪ್ಯಾಕ್', 'category' => 'meals', 'price' => 400, 'imageurl' => "images\\Family Meal Pack.jpg"],
    ['id' => 113, 'nameEn' => 'Bingo Mad Angles', 'nameKn' => 'ಬಿಂಗೋ ಮ್ಯಾಡ್ ಆಂಗಲ್ಸ್', 'category' => 'chips', 'price' => 25, 'imageurl' => "images\\Bingo Mad Angles.jpg"],
    ['id' => 115, 'nameEn' => 'Frozen Butter Naan', 'nameKn' => 'ಫ್ರೋಜನ್ ಬಟರ್ ನಾನ್', 'category' => 'frozen', 'price' => 180, 'imageurl' => "images\\Chicken Biryani Ready Meal.jpg"],
    ['id' => 116, 'nameEn' => 'Frozen Chapati', 'nameKn' => 'ಫ್ರೋಜನ್ ಚಪಾತಿ', 'category' => 'frozen', 'price' => 160, 'imageurl' => "images\\Paneer Biryani Ready Meal.jpg"],
    ['id' => 117, 'nameEn' => 'Frozen Chicken Breast', 'nameKn' => 'ಫ್ರೋಜನ್ ಚಿಕನ್ ಬ್ರೆಸ್ಟ್', 'category' => 'frozen', 'price' => 110, 'imageurl' => "images\\Veg Fried Rice.jpg"],
    ['id' => 118, 'nameEn' => 'Frozen Chicken Sandwich', 'nameKn' => 'ಫ್ರೋಜನ್ ಚಿಕನ್ ಸ್ಯಾಂಡ್‌ವಿಚ್', 'category' => 'frozen', 'price' => 130, 'imageurl' => "images\\Schezwan Fried Rice.jpg"],
    ['id' => 121, 'nameEn' => 'Frozen Chicken Wings', 'nameKn' => 'ಫ್ರೋಜನ್ ಚಿಕನ್ ವಿಂಗ್ಸ್', 'category' => 'frozen', 'price' => 160, 'imageurl' => "images\\Chicken Fried Rice.jpg"],
    ['id' => 122, 'nameEn' => 'Frozen Veg Pulao', 'nameKn' => 'ಫ್ರೋಜನ್ ವೆಜ್ ಪುಲಾವ್', 'category' => 'frozen', 'price' => 100, 'imageurl' => "images\\Veg Pulao.jpg"],
    ['id' => 123, 'nameEn' => 'Frozen Dal Fry', 'nameKn' => 'ಫ್ರೋಜನ್ ದಾಲ್ ಫ್ರೈ', 'category' => 'frozen', 'price' => 90, 'imageurl' => "images\\Jeera Rice.jpg"],
    ['id' => 124, 'nameEn' => 'Frozen Fish Fillet', 'nameKn' => 'ಫ್ರೋಜನ್ ಫಿಶ್ ಫಿಲೆಟ್', 'category' => 'frozen', 'price' => 80, 'imageurl' => "images\\Lemon Rice.jpg"],
    ['id' => 125, 'nameEn' => 'Frozen French Fries', 'nameKn' => 'ಫ್ರೋಜನ್ ಫ್ರೆಂಚ್ ಫ್ರೈಸ್', 'category' => 'frozen', 'price' => 80, 'imageurl' => "images\\Tamarind Rice.jpg"],
    ['id' => 126, 'nameEn' => 'Frozen Fried Rice Veg', 'nameKn' => 'ಫ್ರೋಜನ್ ಫ್ರೈಡ್ ರೈಸ್ ವೆಜ್', 'category' => 'frozen', 'price' => 70, 'imageurl' => "images\\Curd Rice.jpg"],
    ['id' => 127, 'nameEn' => 'Frozen Garlic Naan', 'nameKn' => 'ಫ್ರೋಜನ್ ಗಾರ್ಲಿಕ್ ನಾನ್', 'category' => 'frozen', 'price' => 80, 'imageurl' => "images\\Coconut Rice.jpg"],
    ['id' => 128, 'nameEn' => 'Frozen Garlic Naan', 'nameKn' => 'ಫ್ರೋಜನ್ ಗಾರ್ಲಿಕ್ ನಾನ್', 'category' => 'frozen', 'price' => 90, 'imageurl' => "images\\Tomato Rice.jpg"],
    ['id' => 129, 'nameEn' => 'Frozen Green Peas', 'nameKn' => 'ಫ್ರೋಜನ್ ಗ್ರೀನ್ ಪೀಸ್', 'category' => 'frozen', 'price' => 110, 'imageurl' => "images\\Dal Khichdi.jpg"],
    ['id' => 130, 'nameEn' => 'Frozen Gulab Jamun', 'nameKn' => 'ಫ್ರೋಜನ್ ಗುಲಾಬ್ ಜಾಮುನ್', 'category' => 'frozen', 'price' => 100, 'imageurl' => "images\\Veg Khichdi.jpg"],
    ['id' => 131, 'nameEn' => 'Frozen Ice Cream Family Pack', 'nameKn' => 'ಫ್ರೋಜನ್ ಐಸ್ ಕ್ರೀಮ್ ಫ್ಯಾಮಿಲಿ ಪ್ಯಾಕ್', 'category' => 'frozen', 'price' => 110, 'imageurl' => "images\\Masala Khichdi.jpg"],
    ['id' => 132, 'nameEn' => 'Frozen Jalebi', 'nameKn' => 'ಫ್ರೋಜನ್ ಜಲೇಬಿ', 'category' => 'frozen', 'price' => 140, 'imageurl' => "images\\Rajma Chawal.jpg"],
    ['id' => 133, 'nameEn' => 'Frozen Kachori', 'nameKn' => 'ಫ್ರೋಜನ್ ಕಚೋರಿ', 'category' => 'frozen', 'price' => 140, 'imageurl' => "images\\Chole Chawal.jpg"],
    ['id' => 134, 'nameEn' => 'Frozen Kulfi Malai', 'nameKn' => 'ಫ್ರೋಜನ್ ಕುಲ್ಫಿ ಮಲಾಯಿ', 'category' => 'frozen', 'price' => 160, 'imageurl' => "images\\Paneer Butter Masala Rice.jpg"],
    ['id' => 135, 'nameEn' => 'Frozen Malabar Parotta', 'nameKn' => 'ಫ್ರೋಜನ್ ಮಲಬಾರ್ ಪರೋಟ್ಟಾ', 'category' => 'frozen', 'price' => 130, 'imageurl' => "images\\Dal Tadka Rice Bowl.jpg"],
    ['id' => 136, 'nameEn' => 'Frozen Muffins', 'nameKn' => 'ಫ್ರೋಜನ್ ಮಫಿನ್ಸ್', 'category' => 'frozen', 'price' => 90, 'imageurl' => "images\\Veg Noodles.jpg"],
    ['id' => 137, 'nameEn' => 'Frozen Paratha', 'nameKn' => 'ಫ್ರೋಜನ್ ಪರಾಠಾ', 'category' => 'frozen', 'price' => 110, 'imageurl' => "images\\Schezwan Noodles.jpg"],
    ['id' => 138, 'nameEn' => 'Frozen Prawns', 'nameKn' => 'ಫ್ರೋಜನ್ ಪ್ರಾನ್ಸ್', 'category' => 'frozen', 'price' => 150, 'imageurl' => "images\\Chicken Noodles.jpg"],
    ['id' => 139, 'nameEn' => 'Frozen Rasmalai', 'nameKn' => 'ಫ್ರೋಜನ್ ರಸ್ಮಲಾಯಿ', 'category' => 'frozen', 'price' => 100, 'imageurl' => "images\\Hakka Noodles.jpg"],
    ['id' => 140, 'nameEn' => 'Frozen Smoothie Mix', 'nameKn' => 'ಫ್ರೋಜನ್ ಸ್ಮೂಥಿ ಮಿಕ್ಸ್', 'category' => 'frozen', 'price' => 100, 'imageurl' => "images\\Veg Chowmein.jpg"],
    ['id' => 141, 'nameEn' => 'Frozen Veg Biryani', 'nameKn' => 'ಫ್ರೋಜನ್ ವೆಜ್ ಬಿರಿಯಾನಿ', 'category' => 'frozen', 'price' => 130, 'imageurl' => "images\\Paneer Chowmein.jpg"],
    ['id' => 142, 'nameEn' => 'Frozen Veg Momos', 'nameKn' => 'ಫ್ರೋಜನ್ ವೆಜ್ ಮೊಮೊಸ್', 'category' => 'frozen', 'price' => 140, 'imageurl' => "images\\Veg Manchurian Noodles.jpg"],
    ['id' => 143, 'nameEn' => 'Frozen Waffles', 'nameKn' => 'ಫ್ರೋಜನ್ ವಾಫಲ್ಸ್', 'category' => 'frozen', 'price' => 180, 'imageurl' => "images\\Chicken Manchurian Noodles.jpg"],
    ['id' => 144, 'nameEn' => 'Haldiram Aloo Bhujia', 'nameKn' => 'ಹಳ್ದಿರಾಮ್ ಆಲೂ ಭುಜಿಯಾ', 'category' => 'snacks', 'price' => 120, 'imageurl' => "images\\White Sauce Pasta.jpg"],
    ['id' => 145, 'nameEn' => 'Ice Cream Vanilla', 'nameKn' => 'ಐಸ್ ಕ್ರೀಮ್ ವೆನಿಲ್ಲಾ', 'category' => 'frozen', 'price' => 110, 'imageurl' => "images\\Red Sauce Pasta.jpg"],
    ['id' => 146, 'nameEn' => 'Jaggery', 'nameKn' => 'ಬೆಲ್ಲ', 'category' => 'sweetener', 'price' => 70, 'imageurl' => "images\\Veg Burger.jpg"],
    ['id' => 147, 'nameEn' => 'Knorr Sweet Corn Soup', 'nameKn' => 'ನಾರ್ ಸ್ವೀಟ್ ಕಾರ್ನ್ ಸೂಪ್', 'category' => 'soup', 'price' => 120, 'imageurl' => "images\\Chicken Burger.jpg"],
    ['id' => 148, 'nameEn' => 'Knorr Tomato Soup', 'nameKn' => 'ನಾರ್ ಟೊಮೇಟೊ ಸೂಪ್', 'category' => 'soup', 'price' => 90, 'imageurl' => "images\\Paneer Burger.jpg"],
    ['id' => 149, 'nameEn' => 'Knorr Veg Manchow Soup', 'nameKn' => 'ನಾರ್ ವೆಜ್ ಮಂಚೋ ಸೂಪ್', 'category' => 'soup', 'price' => 90, 'imageurl' => "images\\Veg Wrap.jpg"],
    ['id' => 150, 'nameEn' => 'Kurkure Masala Munch', 'nameKn' => 'ಕುರ್ಕುರೆ ಮಸಾಲಾ ಮಂಚ್', 'category' => 'chips', 'price' => 120, 'imageurl' => "images\\Paneer Wrap.jpg"],
    ['id' => 151, 'nameEn' => 'Lays Classic Chips', 'nameKn' => 'ಲೇಸ್ ಕ್ಲಾಸಿಕ್ ಚಿಪ್ಸ್', 'category' => 'chips', 'price' => 150, 'imageurl' => "images\\Chicken Wrap.jpg"],
    ['id' => 152, 'nameEn' => 'Lays Magic Masala', 'nameKn' => 'ಲೇಸ್ ಮ್ಯಾಜಿಕ್ ಮಸಾಲಾ', 'category' => 'chips', 'price' => 50, 'imageurl' => "images\\Veg Samosa Pack.jpg"],
    ['id' => 153, 'nameEn' => 'Maggi Family Pack', 'nameKn' => 'ಮ್ಯಾಗಿ ಫ್ಯಾಮಿಲಿ ಪ್ಯಾಕ್', 'category' => 'noodles', 'price' => 90, 'imageurl' => "images\\Veg Spring Rolls.jpg"],
    ['id' => 154, 'nameEn' => 'Maggi Hot Soup Veg', 'nameKn' => 'ಮ್ಯಾಗಿ ಹಾಟ್ ಸೂಪ್ ವೆಜ್', 'category' => 'soup', 'price' => 90, 'imageurl' => "images\\Veg Momos.jpg"],
    ['id' => 155, 'nameEn' => 'Maggi Masala Noodles', 'nameKn' => 'ಮ್ಯಾಗಿ ಮಸಾಲಾ ನೂಡಲ್ಸ್', 'category' => 'noodles', 'price' => 110, 'imageurl' => "images\\Veg Nuggets Pack.jpg"],
    ['id' => 156, 'nameEn' => 'French Fries Pack', 'nameKn' => 'ಫ್ರೆಂಚ್ ಫ್ರೈಸ್ ಪ್ಯಾಕ್', 'category' => 'snacks', 'price' => 100, 'imageurl' => "images\\French Fries Pack.jpg"],
    ['id' => 157, 'nameEn' => 'Maggi Tomato Soup', 'nameKn' => 'ಮ್ಯಾಗಿ ಟೊಮೇಟೊ ಸೂಪ್', 'category' => 'soup', 'price' => 130, 'imageurl' => "images\\Veg Taco Meal.jpg"],
    ['id' => 158, 'nameEn' => 'MTR Chole Masala', 'nameKn' => 'ಎಂಟಿಆರ್ ಛೋಲೆ ಮಸಾಲಾ', 'category' => 'curries', 'price' => 130, 'imageurl' => "images\\Veg Shawarma Wrap.jpg"],
    ['id' => 159, 'nameEn' => 'MTR Instant Filter Coffee', 'nameKn' => 'ಎಂಟಿಆರ್ ಇನ್ಸ್ಟಂಟ್ ಫಿಲ್ಟರ್ ಕಾಫಿ', 'category' => 'beverage', 'price' => 100, 'imageurl' => "images\\Garlic Bread Veg.jpg"],
    ['id' => 160, 'nameEn' => 'MTR Paneer Butter Masala', 'nameKn' => 'ಎಂಟಿಆರ್ ಪನೀರ್ ಬಟರ್ ಮಸಾಲಾ', 'category' => 'curries', 'price' => 160, 'imageurl' => "images\\Veg Lunch Combo.jpg"],
    ['id' => 161, 'nameEn' => 'Yippee Masala Noodles', 'nameKn' => 'ಯಿಪ್ಪೀ ಮಸಾಲಾ ನೂಡಲ್ಸ್', 'category' => 'noodles', 'price' => 120, 'imageurl' => "images\\Veg Rice Bowl.jpg"],
    ['id' => 162, 'nameEn' => 'Wai Wai Masala Noodles', 'nameKn' => 'ವೈ ವೈ ಮಸಾಲಾ ನೂಡಲ್ಸ್', 'category' => 'noodles', 'price' => 180, 'imageurl' => "images\\Butter Chicken Rice Bowl.jpg"],
    ['id' => 163, 'nameEn' => 'Saffola Masala Oats', 'nameKn' => 'ಸ್ಯಾಫೋಲಾ ಮಸಾಲಾ ಓಟ್ಸ್', 'category' => 'oats', 'price' => 120, 'imageurl' => "images\\Ready Paratha Meal.jpg"],
    ['id' => 164, 'nameEn' => 'Nescafe Classic', 'nameKn' => 'ನೆಸ್ಕಫೆ ಕ್ಲಾಸಿಕ್', 'category' => 'beverage', 'price' => 400, 'imageurl' => "images\\Family Meal Pack.jpg"]
  ];
  // Build productsData from rawProducts
  foreach ($rawProducts as $item) {
    $productsData[] = [
      'id' => $item['id'],
      'name' => $item['nameEn'],
      'kn' => $item['nameKn'],
      'category' => $item['category'],
      'price' => $item['price'],
      'imageUrl' => $item['imageurl'],
      'description' => '', // will be generated in JS
      'weight' => ''
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes">
  <title>Ready To Eat · ತಿನ್ನಲು ಸಿದ್ಧ · Fresh meals instantly</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
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
      max-width: 1280px;
      width: 100%;
      margin: 0 auto;
    }

    .main-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
      margin-bottom: 32px;
    }

    .brand h1 {
      font-size: 2.2rem;
      font-weight: 800;
      letter-spacing: -0.02em;
      background: linear-gradient(120deg, #c85c2a, #f0985f);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .brand .sub {
      font-size: 0.9rem;
      color: #bc7f5a;
      font-weight: 500;
    }

    .cart-link {
      background: #fef0e5;
      padding: 12px 24px;
      border-radius: 60px;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      transition: all 0.2s ease;
      border: 1px solid #ffdecb;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
      text-decoration: none;
      color: inherit;
      font-weight: 600;
      font-size: 0.95rem;
    }

    .cart-link:hover {
      background: #ffe3d4;
      transform: scale(1.02);
    }

    .cart-link i {
      font-size: 1.4rem;
      color: #c85c2a;
    }

    .cart-link .cart-badge {
      background: #c85c2a;
      color: white;
      padding: 2px 12px;
      border-radius: 50px;
      font-weight: 700;
      font-size: 1rem;
      margin-left: 4px;
    }

    .search-section {
      margin-bottom: 32px;
    }

    .search-box {
      display: flex;
      align-items: center;
      background: white;
      border-radius: 80px;
      padding: 6px 20px;
      border: 1px solid #ffe2cf;
      margin-bottom: 24px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    }

    .search-box i {
      color: #e5894a;
      font-size: 1.2rem;
      margin-right: 12px;
    }

    .search-box input {
      flex: 1;
      border: none;
      padding: 14px 0;
      font-size: 1rem;
      background: transparent;
      outline: none;
      font-weight: 500;
    }

    .filter-scroll {
      overflow-x: auto;
      scrollbar-width: thin;
      padding-bottom: 8px;
    }

    .filter-pills {
      display: flex;
      gap: 12px;
    }

    .pill {
      background: #fff3e8;
      padding: 8px 22px;
      border-radius: 60px;
      font-weight: 600;
      font-size: 0.85rem;
      color: #c85c2a;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.15s;
      border: 1px solid #ffe0cf;
    }

    .pill.active {
      background: #c85c2a;
      color: white;
      border-color: #c85c2a;
      box-shadow: 0 6px 12px rgba(200, 92, 42, 0.2);
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 28px;
      margin: 20px 0 40px;
    }

    .product-card {
      background: #fffefc;
      border-radius: 32px;
      padding: 20px 16px 20px;
      transition: all 0.25s cubic-bezier(0.2, 0, 0, 1);
      border: 1px solid #fae5d4;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02);
      display: flex;
      flex-direction: column;
    }

    .product-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 24px 34px -12px rgba(90, 50, 25, 0.15);
      border-color: #ffcdb0;
    }

    .product-img {
      width: 88px;
      height: 88px;
      object-fit: cover;
      border-radius: 28px;
      background: #faf0e7;
      border: 2px solid #ffdfcb;
      margin-bottom: 16px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.02);
    }

    .product-name {
      font-weight: 800;
      font-size: 1.25rem;
      color: #2e241d;
      line-height: 1.3;
    }

    .product-name-kn {
      font-size: 0.75rem;
      color: #bb8a69;
      margin: 4px 0 10px;
      font-weight: 500;
    }

    .product-desc {
      font-size: 0.8rem;
      color: #6b4c36;
      background: #fff6ef;
      padding: 8px 10px;
      border-radius: 24px;
      margin: 8px 0 12px;
      line-height: 1.35;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 8px;
    }

    .price {
      font-weight: 800;
      font-size: 1.5rem;
      color: #c85c2a;
      letter-spacing: -0.5px;
    }

    .price small {
      font-size: 0.7rem;
      font-weight: 500;
      color: #be8b68;
    }

    .desc-add-btn {
      background: linear-gradient(100deg, #c16132, #e07c45);
      border: none;
      border-radius: 60px;
      padding: 12px 8px;
      font-weight: 600;
      font-size: 0.75rem;
      color: white;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 16px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(200, 92, 42, 0.25);
    }

    .desc-add-btn i {
      font-size: 0.85rem;
    }

    .desc-add-btn:hover {
      background: #b14e22;
      transform: scale(0.96);
    }

    footer {
      text-align: center;
      font-size: 0.75rem;
      color: #c9a181;
      margin-top: 40px;
      padding-top: 20px;
      border-top: 1px solid #fee2d1;
    }

    @media (min-width: 768px) {
      .product-grid {
        gap: 32px;
      }
    }
  </style>
  <link rel="stylesheet" href="category-style.css">
  <link rel="stylesheet" href="../index.css">
</head>

<body>
  <div class="app-container">
    <div class="main-header">
      <div class="brand">
        <img src="../company_logo.jpg" alt="KSR GURU JI" class="ksr-logo-img" style="width:42px;height:42px;border-radius:50%;object-fit:cover;box-shadow:0 4px 10px rgba(15,123,58,.2);border:2px solid rgba(255,255,255,.8);flex-shrink:0;margin-right:10px;vertical-align:middle;" onerror="this.style.display='none'">
        <h1>KSR GURU JI Ready To Eat<br><span class="sub">ಕೆ ಎಸ್ ಆರ್ ಗುರು ತಿನ್ನಲು ಸಿದ್ಧ · instant meals</span></h1>
      </div>
      <!-- Cart link with live badge -->
      <a href="../cart/cart.php" class="cart-link" id="cartLink">
        <i class="fas fa-shopping-cart"></i>
        <span>Cart</span>
        <span class="cart-badge" id="cartCount"><?php echo $cartCount; ?></span>
      </a>
    </div>
    <div class="search-section">
      <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
      </div>
      <div class="filter-scroll">
        <div class="filter-pills" id="filterPills"></div>
      </div>
    </div>
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
      // ----- HELPER FUNCTIONS (same as static) -----
      function getShortDescription(name, category) {
        const n = name.toLowerCase();
        if (n.includes('biryani')) return '🕌 Aromatic & rich • ready in 3 mins';
        if (n.includes('fried rice')) return '🍚 Wok-tossed • classic comfort';
        if (n.includes('noodles') || n.includes('chowmein')) return '🍜 Savory noodles • quick stir-fry';
        if (n.includes('pasta')) return '🍝 Creamy Italian style • just heat';
        if (n.includes('burger')) return '🍔 Crispy patty • serve hot';
        if (n.includes('wrap') || n.includes('shawarma')) return '🌯 Zesty filling • wrap & enjoy';
        if (n.includes('samosa') || n.includes('puff')) return '🥟 Flaky & spiced • crunchy delight';
        if (n.includes('rice bowl') || n.includes('khichdi')) return '🍛 Wholesome bowl • instant meal';
        if (category === 'rice') return '🍚 Steamed perfection • ready to eat';
        if (category === 'curries') return '🍛 Flavorful gravy • microwave friendly';
        if (category === 'snacks') return '⚡ Crispy & quick • snack time';
        return '🔥 Just heat & serve • homemade taste';
      }

      function cardColorByTitle(title) {
        let hash = 0;
        for (let i = 0; i < title.length; i++) hash = ((hash << 5) - hash) + title.charCodeAt(i);
        const hue = Math.abs(hash % 360);
        return `hsl(${hue}, 62%, 84%)`;
      }
      // Augment products with description and ensure proper fields
      const products = productsData.map(p => {
        return {
          id: p.id,
          nameEn: p.name,
          nameKn: p.kn,
          category: p.category,
          price: p.price,
          image: p.imageUrl,
          description: p.description || getShortDescription(p.name, p.category)
        };
      });
      // ----- CART STATE (localStorage, but we also sync via AJAX to server) -----
      let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
      let activeCategory = 'all';
      let searchTerm = '';
      const grid = document.getElementById('productGrid');
      const cartCountSpan = document.getElementById('cartCount');
      const searchInput = document.getElementById('searchInput');
      const filterContainer = document.getElementById('filterPills');
      // Build filter pills dynamically
      const categories = ['all', ...new Set(products.map(p => p.category))];
      const categoryLabels = {
        all: 'All / ಎಲ್ಲ',
        rice: 'Rice / ಅನ್ನ',
        curries: 'Curries / ಸಾರು',
        snacks: 'Snacks / ತಿಂಡಿ',
        meals: 'Meals / ಊಟ',
        pasta: 'Pasta / ಪಾಸ್ತಾ',
        pizza: 'Pizza / ಪಿಜ್ಜಾ',
        sandwich: 'Sandwich / ಸ್ಯಾಂಡ್‌ವಿಚ್',
        wrap: 'Wrap / ರ್ಯಾಪ್',
        burrito: 'Burrito / ಬುರಿಟೊ',
        taco: 'Taco / ಟ್ಯಾಕೋ',
        combo: 'Combo / ಕಾಂಬೊ',
        chips: 'Chips / ಚಿಪ್ಸ್',
        noodles: 'Noodles / ನೂಡಲ್ಸ್',
        soup: 'Soup / ಸೂಪ್',
        frozen: 'Frozen / ಫ್ರೋಜನ್',
        beverage: 'Beverage / ಪಾನೀಯ',
        sweetener: 'Sweetener / ಸಿಹಿಕಾರಕ',
        oats: 'Oats / ಓಟ್ಸ್'
      };
      filterContainer.innerHTML = categories.map(cat => {
        const label = categoryLabels[cat] || cat;
        return `<div class="pill ${cat === 'all' ? 'active' : ''}" data-category="${cat}">${label}</div>`;
      }).join('');
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
        const filtered = products.filter(p => {
          if (activeCategory !== 'all' && p.category !== activeCategory) return false;
          if (searchTerm.trim()) {
            const term = searchTerm.trim().toLowerCase();
            return p.nameEn.toLowerCase().includes(term) || p.nameKn.toLowerCase().includes(term) || p.description.toLowerCase().includes(term);
          }
          return true;
        });
        if (!filtered.length) {
          grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:48px; background:#fff7ef; border-radius:48px;">🍽️ No items found — try another search</div>`;
          return;
        }
        grid.innerHTML = filtered.map(p => {
          const bg = cardColorByTitle(p.nameEn);
          const btnText = `${p.description.substring(0, 38)} ✨`;
          return `
                <div class="product-card" style="background: ${bg};" data-id="${p.id}">
                    <img class="product-img" src="${p.image}" alt="" loading="lazy" onerror="this.src='https://placehold.co/88x88?text=Meal'">
                    <div class="product-name">${p.nameEn.replace(/[&<>]/g, function(m){if(m==='&')return '&amp;';if(m==='<')return '&lt;';if(m==='>')return '&gt;';return m;})}</div>
                    <div class="product-name-kn">${p.nameKn.replace(/[&<>]/g, function(m){if(m==='&')return '&amp;';if(m==='<')return '&lt;';if(m==='>')return '&gt;';return m;})}</div>
                    <div class="product-desc"><i class="fa-regular fa-clock"></i> ${p.description}</div>
                    <div class="price-row">
                        <span class="price">₹${p.price} <small>inr</small></span>
                        <button class="desc-add-btn" data-id="${p.id}"><i class="fa-solid fa-cart-plus"></i> ${btnText}</button>
                    </div>
                </div>
            `;
        }).join('');
        document.querySelectorAll('.desc-add-btn').forEach(btn => {
          btn.addEventListener('click', () => addToCart(parseInt(btn.dataset.id)));
        });
      }
      // ----- EVENT BINDING -----
      document.querySelectorAll('.pill').forEach(p => {
        p.addEventListener('click', function() {
          document.querySelectorAll('.pill').forEach(pi => pi.classList.remove('active'));
          this.classList.add('active');
          activeCategory = this.dataset.category;
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