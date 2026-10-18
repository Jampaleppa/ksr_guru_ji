<?php
// ============================================================
// snacks.php - Dynamic Snacks page (with images)
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
// Helper: normalize image path (convert Windows absolute to web relative)
if (!function_exists('normalizeImagePath')) {
    function normalizeImagePath(?string $path): string
    {
        if (empty($path)) return '';
        // Normalize backslashes to forward slashes
        $path = str_replace('\\', '/', $path);
        // Extract everything after the last occurrence of "images/"
        if (preg_match('#.*?(images/.*)$#i', $path, $matches)) {
            // Return with leading slash → root‑relative
            return '/' . $matches[1];
        }
        // Fallback: just return the filename (should not happen)
        return '/' . ltrim($path, '/');
    }
}
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
// ============================================================
// FETCH SNACK PRODUCTS FROM DATABASE (PDO) - with fallback
// ============================================================
function getSnacksProducts(string $search = ''): array
{
    try {
        $pdo = getDBConnection();
        // Dynamic column checks
        $schema = getProductSchemaInfo();
        $descCol = $schema["desc_col"];
        $imgCol = $schema["img_col"];
        // Check category slug column
        $schema = getProductSchemaInfo();
        $hasSlug = $schema["has_cat_slug"];
        $whereCategory = $hasSlug ? "(c.slug = 'snacks' OR c.name LIKE '%snacks%')" : "(c.id = 'snacks' OR c.name LIKE '%snacks%')";
        $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                       c.name AS category_name, c.kn_name AS category_kn,
                       {$descCol} AS description_en,
                       {$imgCol} AS image
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE {$whereCategory}";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (p.name LIKE ? OR p.kn_name LIKE ? OR {$descCol} LIKE ?)";
            $searchWild = '%' . $search . '%';
            $params[] = $searchWild;
            $params[] = $searchWild;
            $params[] = $searchWild;
        }
        $sql .= " ORDER BY p.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $desc = $row['description_en'] ?? 'Delicious snacks';
            $imageUrl = !empty($row['image']) ? normalizeImagePath($row['image']) : '';
            $result[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'price' => $row['price'],
                'category' => $row['category_name'] ?? 'chips',
                'image' => $imageUrl,
                'desc' => $desc,
                'weight' => ''
            ];
        }
        return $result;
    } catch (Throwable $e) {
        return [];
    }
}
// ============================================================
// FALLBACK: Large static snack data (merged from provided JSON)
// ============================================================
function getFallbackSnacksProducts()
{
    static $fallback = null;
    if ($fallback !== null) return $fallback;
    $staticData = [
        ["id" => 1, "name" => "Potato Chips", "imageUrl" => "images\\Potato Chips.jpg", "weight" => "1 pack", "price" => 20, "category" => "chips", "desc" => "Classic salted potato chips, crispy and golden."],
        ["id" => 2, "name" => "Masala Chips", "imageUrl" => "images\\Masala Corn Chips.jpg", "weight" => "1 pack", "price" => 25, "category" => "chips", "desc" => "Tangy masala twist, irresistible crunch."],
        ["id" => 3, "name" => "Banana Chips", "imageUrl" => "images\\Banana Chips.jpg", "weight" => "1 pack", "price" => 40, "category" => "chips", "desc" => "Thin sliced, salted Kerala-style banana chips."],
        ["id" => 4, "name" => "Tapioca Chips", "imageUrl" => "images\\Tapioca Chips.jpg", "weight" => "1 pack", "price" => 45, "category" => "chips", "desc" => "Crunchy yucca chips with black pepper."],
        ["id" => 5, "name" => "Murukku", "imageUrl" => "images\\Murukku.jpg", "weight" => "1 pack", "price" => 60, "category" => "namkeen", "desc" => "Spiral rice & urad dal crisp."],
        ["id" => 6, "name" => "Chakli", "imageUrl" => "images\\Chakli.jpg", "weight" => "1 pack", "price" => 70, "category" => "namkeen", "desc" => "Traditional spicy disc snack."],
        ["id" => 7, "name" => "Bhujia", "imageUrl" => "images\\Bhujia.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Thin gram flour noodles, spicy."],
        ["id" => 8, "name" => "Aloo Bhujia", "imageUrl" => "images\\Aloo Bhujia.webp", "weight" => "1 pack", "price" => 75, "category" => "namkeen", "desc" => "Potato based crispy sev."],
        ["id" => 9, "name" => "Nylon Sev", "imageUrl" => "images\\Nylon Sev.jpg", "weight" => "1 pack", "price" => 60, "category" => "namkeen", "desc" => "Fine, crunchy sev for chaats."],
        ["id" => 10, "name" => "Ratlami Sev", "imageUrl" => "images\\Ratlami Sev.jpg", "weight" => "1 pack", "price" => 90, "category" => "namkeen", "desc" => "Medium spice, bold namkeen."],
        ["id" => 11, "name" => "Corn Chips", "imageUrl" => "images\\Corn Chips.jpg", "weight" => "1 pack", "price" => 30, "category" => "chips", "desc" => "Sweet corn flavour puffs."],
        ["id" => 12, "name" => "Nachos", "imageUrl" => "images\\Nachos.jpg", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Triangle tortilla chips."],
        ["id" => 13, "name" => "Cheese Balls", "imageUrl" => "images\\Cheese Balls.jpg", "weight" => "1 pack", "price" => 40, "category" => "chips", "desc" => "Cheese flavoured crunchy balls."],
        ["id" => 14, "name" => "Rings Snack", "imageUrl" => "images\\Rings Snack.jpg", "weight" => "1 pack", "price" => 30, "category" => "chips", "desc" => "Onion ring shaped maida crisp."],
        ["id" => 15, "name" => "Popcorn Salted", "imageUrl" => "images\\Popcorn Salted.jpg", "weight" => "1 pack", "price" => 40, "category" => "chips", "desc" => "Butter salted popcorn."],
        ["id" => 16, "name" => "Popcorn Butter", "imageUrl" => "images\\Popcorn Butter.jpg", "weight" => "1 pack", "price" => 45, "category" => "chips", "desc" => "Movie style buttery popcorn."],
        ["id" => 17, "name" => "Caramel Popcorn", "imageUrl" => "images\\Caramel Popcorn.jpeg", "weight" => "1 pack", "price" => 60, "category" => "sweets", "desc" => "Sweet & crunchy caramel coated."],
        ["id" => 18, "name" => "Peanuts Salted", "imageUrl" => "images\\Peanuts Salted.jpg", "weight" => "1 pack", "price" => 30, "category" => "namkeen", "desc" => "Roasted peanuts with salt."],
        ["id" => 19, "name" => "Masala Peanuts", "imageUrl" => "images\\Masala Peanuts.jpg", "weight" => "1 pack", "price" => 40, "category" => "namkeen", "desc" => "Spicy gram flour coated peanuts."],
        ["id" => 20, "name" => "Roasted Chana", "imageUrl" => "images\\Roasted Chana.jpg", "weight" => "1 pack", "price" => 35, "category" => "namkeen", "desc" => "Protein rich roasted chickpeas."],
        ["id" => 21, "name" => "Bombay Mix", "imageUrl" => "images\\Bombay Mix.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Spicy mixture of sev, nuts & lentils."],
        ["id" => 22, "name" => "Navratan Mix", "imageUrl" => "images\\Navratan Mix.jpg", "weight" => "1 pack", "price" => 90, "category" => "namkeen", "desc" => "Nine treasure crunchy blend."],
        ["id" => 23, "name" => "Punjabi Mix", "imageUrl" => "images\\Punjabi Mix.jpg", "weight" => "1 pack", "price" => 85, "category" => "namkeen", "desc" => "Punjabi style spicy tadka mix."],
        ["id" => 24, "name" => "Gujarati Mix", "imageUrl" => "images\\Gujarati Mix.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Sweet & spicy Gujarati farsan."],
        ["id" => 25, "name" => "South Indian Mixture", "imageUrl" => "images\\South Indian Mixture.jpg", "weight" => "1 pack", "price" => 90, "category" => "namkeen", "desc" => "Curry leaf & coconut flavored mix."],
        ["id" => 26, "name" => "Khatta Meetha Mix", "imageUrl" => "images\\Khatta Meetha Mix.jpg", "weight" => "1 pack", "price" => 85, "category" => "namkeen", "desc" => "Sweet & tangy khatta meetha blend."],
        ["id" => 27, "name" => "Dal Mixture", "imageUrl" => "images\\Dal Mixture.jpg", "weight" => "1 pack", "price" => 75, "category" => "namkeen", "desc" => "Lentil based crunchy mix."],
        ["id" => 28, "name" => "Corn Mixture", "imageUrl" => "images\\Corn Mixture.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Flakes & corn crispy blend."],
        ["id" => 29, "name" => "Chivda", "imageUrl" => "images\\Chivda.jpg", "weight" => "1 pack", "price" => 70, "category" => "namkeen", "desc" => "Pressed rice flakes savory snack."],
        ["id" => 30, "name" => "Poha Chivda", "imageUrl" => "images\\Poha Chivda.jpg", "weight" => "1 pack", "price" => 75, "category" => "namkeen", "desc" => "Flattened rice with peanuts."],
        ["id" => 31, "name" => "Samosa", "imageUrl" => "images\\Samosa.jpg", "weight" => "1 pack", "price" => 15, "category" => "namkeen", "desc" => "Crispy pastry with spiced potato."],
        ["id" => 32, "name" => "Mini Samosa", "imageUrl" => "images\\Mini Samosa.jpg", "weight" => "1 pack", "price" => 120, "category" => "namkeen", "desc" => "Bite sized samosa party pack."],
        ["id" => 33, "name" => "Kachori", "imageUrl" => "images\\Kachori.jpg", "weight" => "1 pack", "price" => 20, "category" => "namkeen", "desc" => "Stuffed flaky kachori."],
        ["id" => 34, "name" => "Dal Kachori", "imageUrl" => "images\\Dal Kachori.jpg", "weight" => "1 pack", "price" => 25, "category" => "namkeen", "desc" => "Lentil filled spicy kachori."],
        ["id" => 35, "name" => "Aloo Bonda", "imageUrl" => "images\\Aloo Bonda.jpg", "weight" => "1 pack", "price" => 20, "category" => "namkeen", "desc" => "Potato dumpling fritter."],
        ["id" => 36, "name" => "Onion Pakoda", "imageUrl" => "images\\Onion Pakoda.jpg", "weight" => "1 pack", "price" => 25, "category" => "namkeen", "desc" => "Crispy onion fritters."],
        ["id" => 37, "name" => "Bread Pakoda", "imageUrl" => "images\\Bread Pakoda.jpg", "weight" => "1 pack", "price" => 25, "category" => "namkeen", "desc" => "Stuffed bread deep fried."],
        ["id" => 38, "name" => "Mirchi Bajji", "imageUrl" => "images\\Mirchi Bajji.jpg", "weight" => "1 pack", "price" => 20, "category" => "namkeen", "desc" => "Spicy chilli fritter."],
        ["id" => 39, "name" => "Paneer Pakoda", "imageUrl" => "images\\Paneer Pakoda.jpg", "weight" => "1 pack", "price" => 40, "category" => "namkeen", "desc" => "Cottage cheese fritter."],
        ["id" => 40, "name" => "Veg Cutlet", "imageUrl" => "images\\Veg Cutlet.jpg", "weight" => "1 pack", "price" => 30, "category" => "namkeen", "desc" => "Mixed vegetable patty."],
        ["id" => 41, "name" => "Biscuit Pack", "imageUrl" => "images\\Biscuit Pack.jpg", "weight" => "1 pack", "price" => 20, "category" => "biscuits", "desc" => "Classic tea time biscuits."],
        ["id" => 42, "name" => "Cream Biscuit", "imageUrl" => "images\\Cream Biscuit.jpg", "weight" => "1 pack", "price" => 30, "category" => "biscuits", "desc" => "Cream filled sandwich biscuits."],
        ["id" => 43, "name" => "Chocolate Biscuit", "imageUrl" => "images\\Chocolate Biscuit.jpg", "weight" => "1 pack", "price" => 40, "category" => "biscuits", "desc" => "Choco chip cookies."],
        ["id" => 44, "name" => "Rusk", "imageUrl" => "images\\Rusk.jpg", "weight" => "1 pack", "price" => 50, "category" => "biscuits", "desc" => "Twice baked bread rusk."],
        ["id" => 45, "name" => "Cake Slice", "imageUrl" => "images\\Cake Slice.jpg", "weight" => "1 pack", "price" => 40, "category" => "biscuits", "desc" => "Vanilla sponge cake slice."],
        ["id" => 46, "name" => "Cup Cake", "imageUrl" => "images\\Cup Cake.jpg", "weight" => "1 pack", "price" => 35, "category" => "biscuits", "desc" => "Mini butter cupcake."],
        ["id" => 47, "name" => "Muffin", "imageUrl" => "images\\Muffin.jpg", "weight" => "1 pack", "price" => 50, "category" => "biscuits", "desc" => "Chocolate chip muffin."],
        ["id" => 48, "name" => "Doughnut", "imageUrl" => "images\\Doughnut.jpg", "weight" => "1 pack", "price" => 60, "category" => "sweets", "desc" => "Glazed ring doughnut."],
        ["id" => 49, "name" => "Pastry", "imageUrl" => "images\\Pastry.jpg", "weight" => "1 pack", "price" => 70, "category" => "sweets", "desc" => "Butter cream pastry."],
        ["id" => 50, "name" => "Brownie", "imageUrl" => "images\\Brownie.jpg", "weight" => "1 pack", "price" => 80, "category" => "sweets", "desc" => "Fudgy chocolate brownie."],
        ["id" => 51, "name" => "Dry Fruits Mix", "imageUrl" => "images\\Dry Fruits Mix.jpg", "weight" => "1 pack", "price" => 200, "category" => "namkeen", "desc" => "Premium dry fruit trail mix."],
        ["id" => 52, "name" => "Almond Snack Pack", "imageUrl" => "images\\Almond Snack Pack.jpg", "weight" => "1 pack", "price" => 150, "category" => "namkeen", "desc" => "Roasted salted almonds."],
        ["id" => 53, "name" => "Cashew Snack", "imageUrl" => "images\\Cashew Snack.jpg", "weight" => "1 pack", "price" => 180, "category" => "namkeen", "desc" => "Spiced cashews."],
        ["id" => 54, "name" => "Pistachio Snack", "imageUrl" => "images\\Pistachio Snack.jpg", "weight" => "1 pack", "price" => 220, "category" => "namkeen", "desc" => "Premium salted pistachios."],
        ["id" => 55, "name" => "Trail Mix", "imageUrl" => "images\\Trail Mix.jpg", "weight" => "1 pack", "price" => 180, "category" => "namkeen", "desc" => "Nut, seed & raisin mix."],
        ["id" => 56, "name" => "Granola Bar", "imageUrl" => "images\\Granola Bar.jpg", "weight" => "1 pack", "price" => 40, "category" => "sweets", "desc" => "Oats & honey energy bar."],
        ["id" => 57, "name" => "Protein Bar", "imageUrl" => "images\\Protein Bar.jpg", "weight" => "1 pack", "price" => 80, "category" => "sweets", "desc" => "High protein snack bar."],
        ["id" => 58, "name" => "Energy Bar", "imageUrl" => "images\\Energy Bar.jpg", "weight" => "1 pack", "price" => 60, "category" => "sweets", "desc" => "Dates & nuts bar."],
        ["id" => 59, "name" => "Oats Cookies", "imageUrl" => "images\\Oats Cookies.jpg", "weight" => "1 pack", "price" => 70, "category" => "biscuits", "desc" => "Whole wheat oats cookies."],
        ["id" => 60, "name" => "Multigrain Chips", "imageUrl" => "images\\Multigrain Chips.jpg", "weight" => "1 pack", "price" => 80, "category" => "chips", "desc" => "Healthy multigrain crisp."],
        ["id" => 61, "name" => "Bhel Puri Mix", "imageUrl" => "images\\Bhel Puri Mix.jpg", "weight" => "1 pack", "price" => 40, "category" => "namkeen", "desc" => "Ready to mix bhel puri."],
        ["id" => 62, "name" => "Sev Puri Pack", "imageUrl" => "images\\Sev Puri Pack.jpg", "weight" => "1 pack", "price" => 40, "category" => "namkeen", "desc" => "Sev puri chaat base."],
        ["id" => 63, "name" => "Pani Puri Kit", "imageUrl" => "images\\Pani Puri Kit.jpg", "weight" => "1 pack", "price" => 60, "category" => "namkeen", "desc" => "Puri + masala + chutney."],
        ["id" => 64, "name" => "Papdi Chaat Mix", "imageUrl" => "images\\Papdi Chaat Mix.jpg", "weight" => "1 pack", "price" => 50, "category" => "namkeen", "desc" => "Crispy papdi with spices."],
        ["id" => 65, "name" => "Ragda Patties", "imageUrl" => "images\\Ragda Patties.jpg", "weight" => "1 pack", "price" => 50, "category" => "namkeen", "desc" => "Potato patty set (2 pieces)."],
        ["id" => 66, "name" => "Vada Pav", "imageUrl" => "images\\Vada Pav.jpg", "weight" => "1 pack", "price" => 20, "category" => "namkeen", "desc" => "Mumbai style vada pav."],
        ["id" => 67, "name" => "Dabeli", "imageUrl" => "images\\Dabeli.jpg", "weight" => "1 pack", "price" => 25, "category" => "namkeen", "desc" => "Kutchi dabeli with garlic chutney."],
        ["id" => 68, "name" => "Pav Bhaji Bread Pack", "imageUrl" => "images\\Pav Bhaji Bread Pack.jpg", "weight" => "1 pack", "price" => 35, "category" => "namkeen", "desc" => "Fresh pav (4 pcs) + bhaji masala."],
        ["id" => 69, "name" => "Chana Chaat", "imageUrl" => "images\\Chana Chaat.jpg", "weight" => "1 pack", "price" => 30, "category" => "namkeen", "desc" => "Spicy chickpea chaat."],
        ["id" => 70, "name" => "Corn Chaat", "imageUrl" => "images\\Corn Chaat .jpg", "weight" => "1 pack", "price" => 40, "category" => "namkeen", "desc" => "Tangy corn masala mix."],
        ["id" => 71, "name" => "Khakhra", "imageUrl" => "images\\Khakhra.jpg", "weight" => "1 pack", "price" => 60, "category" => "biscuits", "desc" => "Thin whole wheat cracker."],
        ["id" => 72, "name" => "Mathri", "imageUrl" => "images\\Mathri.jpg", "weight" => "1 pack", "price" => 70, "category" => "biscuits", "desc" => "Salted flaky mathri."],
        ["id" => 73, "name" => "Namak Para", "imageUrl" => "images\\Namak Para.jpg", "weight" => "1 pack", "price" => 60, "category" => "biscuits", "desc" => "Salty diamond shaped crisp."],
        ["id" => 74, "name" => "Shakar Para", "imageUrl" => "images\\Shakar Para.jpg", "weight" => "1 pack", "price" => 60, "category" => "sweets", "desc" => "Sweet sugar coated crisp."],
        ["id" => 75, "name" => "Bakarwadi", "imageUrl" => "images\\Bakarwadi.jpg", "weight" => "1 pack", "price" => 90, "category" => "namkeen", "desc" => "Spiral maharashtrian snack."],
        ["id" => 76, "name" => "Fafda", "imageUrl" => "images\\Fafda.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Crispy gram flour fafda."],
        ["id" => 77, "name" => "Gathiya", "imageUrl" => "images\\Gathiya.jpg", "weight" => "1 pack", "price" => 70, "category" => "namkeen", "desc" => "Thin besan gathiya."],
        ["id" => 78, "name" => "Thepla Chips", "imageUrl" => "images\\Thepla Chips.jpg", "weight" => "1 pack", "price" => 80, "category" => "chips", "desc" => "Methi thepla flavored chips."],
        ["id" => 79, "name" => "Masala Papad", "imageUrl" => "images\\Masala Papad.jpg", "weight" => "1 pack", "price" => 40, "category" => "namkeen", "desc" => "Roasted papad with masala."],
        ["id" => 80, "name" => "Roasted Makhana", "imageUrl" => "images\\Roasted Makhana.jpg", "weight" => "1 pack", "price" => 120, "category" => "namkeen", "desc" => "Foxnut super snack, lightly spiced."],
        ["id" => 81, "name" => "Chocolate Popcorn", "imageUrl" => "images\\Chocolate Popcorn.jpg", "weight" => "1 pack", "price" => 70, "category" => "sweets", "desc" => "Belgian chocolate coated popcorn."],
        ["id" => 82, "name" => "Cheese Crackers", "imageUrl" => "images\\Cheese Crackers.jpg", "weight" => "1 pack", "price" => 50, "category" => "biscuits", "desc" => "Cheese flavored square crackers."],
        ["id" => 83, "name" => "Wheat Crackers", "imageUrl" => "images\\Wheat Crackers.jpg", "weight" => "1 pack", "price" => 45, "category" => "biscuits", "desc" => "High fiber wheat crackers."],
        ["id" => 84, "name" => "Cream Rolls", "imageUrl" => "images\\Cream Rolls.jpg", "weight" => "1 pack", "price" => 60, "category" => "sweets", "desc" => "Custard filled cream rolls."],
        ["id" => 85, "name" => "Peanut Chikki", "imageUrl" => "images\\Peanut Chikki.jpg", "weight" => "1 pack", "price" => 40, "category" => "sweets", "desc" => "Jaggery & peanut brittle."],
        ["id" => 86, "name" => "Sesame Chikki", "imageUrl" => "images\\Sesame Chikki.jpg", "weight" => "1 pack", "price" => 45, "category" => "sweets", "desc" => "Sesame seed sweet bar."],
        ["id" => 87, "name" => "Coconut Chips", "imageUrl" => "images\\Coconut Chips.jpg", "weight" => "1 pack", "price" => 70, "category" => "chips", "desc" => "Toasted coconut crisp chips."],
        ["id" => 88, "name" => "Sweet Corn Snack", "imageUrl" => "images\\Sweet Corn Snack.jpg", "weight" => "1 pack", "price" => 50, "category" => "chips", "desc" => "Sweet corn crispies."],
        ["id" => 89, "name" => "Masala Corn", "imageUrl" => "images\\Masala Corn.jpg", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Spicy corn puffs."],
        ["id" => 90, "name" => "BBQ Chips", "imageUrl" => "images\\BBQ Chips.jpg", "weight" => "1 pack", "price" => 50, "category" => "chips", "desc" => "Smoky barbecue flavour."],
        ["id" => 91, "name" => "Peri Peri Chips", "imageUrl" => "images\\Peri Peri Chips.jpg", "weight" => "1 pack", "price" => 50, "category" => "chips", "desc" => "Hot peri peri potato crisps."],
        ["id" => 92, "name" => "Salsa Nachos", "imageUrl" => "images\\Salsa Nachos.jpg", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Salsa flavored tortilla chips."],
        ["id" => 93, "name" => "Cheese Nachos", "imageUrl" => "images\\Cheese Nachos.jpg", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Cheese dusted nachos."],
        ["id" => 94, "name" => "Jalapeno Chips", "imageUrl" => "images\\Jalapeno Corn Chips.jpg", "weight" => "1 pack", "price" => 55, "category" => "chips", "desc" => "Spicy jalapeno flavored chips."],
        ["id" => 95, "name" => "Potato Wafers", "imageUrl" => "images\\Potato Wafers.jpg", "weight" => "1 pack", "price" => 30, "category" => "chips", "desc" => "Thin salted potato wafers."],
        ["id" => 96, "name" => "Masala Murukku", "imageUrl" => "images\\Masala Murukku.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Spiced rice flour murukku."],
        ["id" => 97, "name" => "Tomato Chips", "imageUrl" => "images\\Tomato Chips.jpg", "weight" => "1 pack", "price" => 50, "category" => "chips", "desc" => "Tangy tomato flavor chips."],
        ["id" => 98, "name" => "Garlic Chips", "imageUrl" => "images\\Garlic Chips.jpg", "weight" => "1 pack", "price" => 55, "category" => "chips", "desc" => "Garlic infused potato crisps."],
        ["id" => 99, "name" => "Onion Rings", "imageUrl" => "images\\Onion Rings.jpg", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Crispy onion ring snack."],
        ["id" => 100, "name" => "Cheese Puffs", "imageUrl" => "images\\Cheese Puffs.jpg", "weight" => "1 pack", "price" => 40, "category" => "chips", "desc" => "Airy cheese flavored puffs."],
        ["id" => 101, "name" => "Spicy Corn Rings", "imageUrl" => "images\\Spicy Corn Rings.jpg", "weight" => "1 pack", "price" => 35, "category" => "chips", "desc" => "Ring shaped spicy corn snack."],
        ["id" => 102, "name" => "BBQ Corn Chips", "imageUrl" => "images\\BBQ Corn Chips.jpg", "weight" => "1 pack", "price" => 50, "category" => "chips", "desc" => "BBQ flavor corn crisps."],
        ["id" => 103, "name" => "Masala Khakhra", "imageUrl" => "images\\Masala Khakhra.jpg", "weight" => "1 pack", "price" => 70, "category" => "biscuits", "desc" => "Spicy whole wheat khakhra."],
        ["id" => 104, "name" => "Jeera Khakhra", "imageUrl" => "images\\Jeera Khakhra.jpg", "weight" => "1 pack", "price" => 70, "category" => "biscuits", "desc" => "Cumin flavored khakhra."],
        ["id" => 105, "name" => "Butter Cookies", "imageUrl" => "images\\Butter Cookies.jpg", "weight" => "1 pack", "price" => 90, "category" => "biscuits", "desc" => "Rich butter cookies."],
        ["id" => 106, "name" => "Chocolate Cookies", "imageUrl" => "images\\Chocolate Cookies.jpg", "weight" => "1 pack", "price" => 100, "category" => "biscuits", "desc" => "Double chocolate chip cookies."],
        ["id" => 107, "name" => "Fruit Cake Slice", "imageUrl" => "images\\Fruit Cake Slice.jpg", "weight" => "1 pack", "price" => 60, "category" => "sweets", "desc" => "Rich fruit cake slice."],
        ["id" => 108, "name" => "Cream Wafers", "imageUrl" => "images\\Cream Wafers.jpg", "weight" => "1 pack", "price" => 50, "category" => "biscuits", "desc" => "Cream wafer rolls."],
        ["id" => 109, "name" => "Choco Pie", "imageUrl" => "images\\Choco Pie.jpg", "weight" => "1 pack", "price" => 120, "category" => "sweets", "desc" => "Chocolate coated marshmallow pie."],
        ["id" => 110, "name" => "Mini Pizza Snack", "imageUrl" => "images\\Mini Pizza Snack.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Mini pizza flavored bites."],
        ["id" => 111, "name" => "Veg Puff", "imageUrl" => "images\\Veg Puff.jpg", "weight" => "1 pack", "price" => 25, "category" => "namkeen", "desc" => "Flaky puff with veg filling."],
        ["id" => 113, "name" => "Egg Puff", "imageUrl" => "images\\Egg Puff.jpg", "weight" => "1 pack", "price" => 30, "category" => "namkeen", "desc" => "Puff pastry with egg filling."],
        ["id" => 114, "name" => "Potato Croquettes", "imageUrl" => "images\\Potato Croquettes.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Breaded potato cylinders."],
        ["id" => 115, "name" => "Veg Nuggets", "imageUrl" => "images\\Veg Nuggets.jpg", "weight" => "1 pack", "price" => 120, "category" => "namkeen", "desc" => "Crunchy veggie nuggets."],
        ["id" => 116, "name" => "French Fries Pack", "imageUrl" => "images\\French Fries Pack.jpg", "weight" => "1 pack", "price" => 100, "category" => "chips", "desc" => "Frozen french fries."],
        ["id" => 117, "name" => "Sweet Potato Chips", "imageUrl" => "images\\Sweet Potato Chips.jpg", "weight" => "1 pack", "price" => 70, "category" => "chips", "desc" => "Healthy sweet potato crisps."],
        ["id" => 118, "name" => "Jackfruit Chips", "imageUrl" => "images\\Jackfruit Chips.jpg", "weight" => "1 pack", "price" => 120, "category" => "chips", "desc" => "Crispy raw jackfruit chips."],
        ["id" => 119, "name" => "Ragi Chips", "imageUrl" => "images\\Ragi Chips.jpg", "weight" => "1 pack", "price" => 90, "category" => "chips", "desc" => "Finger millet based chips."],
        ["id" => 120, "name" => "Millet Chips", "imageUrl" => "images\\Millet Chips.jpg", "weight" => "1 pack", "price" => 95, "category" => "chips", "desc" => "Multimillet crunchy chips."],
        ["id" => 121, "name" => "Quinoa Chips", "imageUrl" => "images\\Quinoa Chips.jpg", "weight" => "1 pack", "price" => 120, "category" => "chips", "desc" => "Protein rich quinoa chips."],
        ["id" => 122, "name" => "Masala Makhana", "imageUrl" => "images\\Masala Makhana.jpg", "weight" => "1 pack", "price" => 140, "category" => "namkeen", "desc" => "Spiced foxnut snack."],
        ["id" => 123, "name" => "Chocolate Makhana", "imageUrl" => "images\\Chocolate Makhana.jpg", "weight" => "1 pack", "price" => 150, "category" => "sweets", "desc" => "Chocolate coated makhana."],
        ["id" => 124, "name" => "Honey Roasted Peanuts", "imageUrl" => "images\\Honey Roasted Peanuts.jpg", "weight" => "1 pack", "price" => 110, "category" => "namkeen", "desc" => "Sweet honey coated peanuts."],
        ["id" => 125, "name" => "Wasabi Peanuts", "imageUrl" => "images\\Wasabi Peanuts.jpg", "weight" => "1 pack", "price" => 130, "category" => "namkeen", "desc" => "Wasabi coated green peas."],
        ["id" => 126, "name" => "Cheese Crackers", "imageUrl" => "images\\Cheese Crackers.jpg", "weight" => "1 pack", "price" => 60, "category" => "biscuits", "desc" => "Savory cheese crackers."],
        ["id" => 127, "name" => "Wheat Crackers", "imageUrl" => "images\\Wheat Crackers.jpg", "weight" => "1 pack", "price" => 55, "category" => "biscuits", "desc" => "Whole wheat salted crackers."],
        ["id" => 128, "name" => "Salt Crackers", "imageUrl" => "images\\Salt Crackers.jpg", "weight" => "1 pack", "price" => 50, "category" => "biscuits", "desc" => "Plain salted crackers."],
        ["id" => 129, "name" => "Cream Rolls", "imageUrl" => "images\\Cream Rolls.jpg", "weight" => "1 pack", "price" => 70, "category" => "sweets", "desc" => "Cream filled roll pastries."],
        ["id" => 130, "name" => "Coconut Cookies", "imageUrl" => "images\\Coconut Cookies.jpg", "weight" => "1 pack", "price" => 90, "category" => "biscuits", "desc" => "Toasted coconut cookies."],
        ["id" => 131, "name" => "Oats Cookies", "imageUrl" => "images\\Oats Cookies.jpg", "weight" => "1 pack", "price" => 100, "category" => "biscuits", "desc" => "Healthy oats cookies."],
        ["id" => 132, "name" => "Almond Cookies", "imageUrl" => "images\\Almond Cookies.jpg", "weight" => "1 pack", "price" => 120, "category" => "biscuits", "desc" => "Butter almond cookies."],
        ["id" => 133, "name" => "Cashew Cookies", "imageUrl" => "images\\Cashew Cookies.jpg", "weight" => "1 pack", "price" => 130, "category" => "biscuits", "desc" => "Cashew rich cookies."],
        ["id" => 134, "name" => "Butter Rusk", "imageUrl" => "images\\Butter Rusk.jpg", "weight" => "1 pack", "price" => 80, "category" => "biscuits", "desc" => "Buttery sweet rusk."],
        ["id" => 135, "name" => "Milk Rusk", "imageUrl" => "images\\Milk Rusk.jpg", "weight" => "1 pack", "price" => 85, "category" => "biscuits", "desc" => "Milk flavored rusk."],
        ["id" => 136, "name" => "Garlic Bread Snack", "imageUrl" => "images\\Garlic Bread Snack.jpg", "weight" => "1 pack", "price" => 70, "category" => "namkeen", "desc" => "Garlic toast bites."],
        ["id" => 137, "name" => "Cheese Bread Snack", "imageUrl" => "images\\Cheese Bread Snack.jpg", "weight" => "1 pack", "price" => 75, "category" => "namkeen", "desc" => "Cheese flavored bread snack."],
        ["id" => 138, "name" => "Veg Roll Snack", "imageUrl" => "images\\Veg Roll Snack.jpg", "weight" => "1 pack", "price" => 60, "category" => "namkeen", "desc" => "Spring roll style veg snack."],
        ["id" => 141, "name" => "Sweet Corn Cups", "imageUrl" => "images\\Sweet Corn Cups.webp", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Sweet corn snack cups."],
        ["id" => 142, "name" => "Masala Corn Cups", "imageUrl" => "images\\Masala Corn Cups.jpg", "weight" => "1 pack", "price" => 65, "category" => "chips", "desc" => "Masala flavored corn cups."],
        ["id" => 143, "name" => "Popcorn Cheese", "imageUrl" => "images\\Popcorn Cheese.jpg", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Cheese popcorn."],
        ["id" => 144, "name" => "Popcorn Caramel", "imageUrl" => "images\\Popcorn Caramel.jpeg", "weight" => "1 pack", "price" => 70, "category" => "sweets", "desc" => "Caramel coated popcorn."],
        ["id" => 145, "name" => "Popcorn Chocolate", "imageUrl" => "images\\Popcorn Chocolate.jpg", "weight" => "1 pack", "price" => 75, "category" => "sweets", "desc" => "Chocolate drizzled popcorn."],
        ["id" => 146, "name" => "Spicy Potato Wafers", "imageUrl" => "images\\Spicy Potato Wafers.jpg", "weight" => "1 pack", "price" => 45, "category" => "chips", "desc" => "Spicy wafers."],
        ["id" => 147, "name" => "Tomato Wafers", "imageUrl" => "images\\Tomato Wafers.jpg", "weight" => "1 pack", "price" => 45, "category" => "chips", "desc" => "Tomato flavored wafers."],
        ["id" => 148, "name" => "Cream Biscuits", "imageUrl" => "images\\Cream Biscuits.jpg", "weight" => "1 pack", "price" => 30, "category" => "biscuits", "desc" => "Cream sandwich biscuits."],
        ["id" => 149, "name" => "Chocolate Biscuits", "imageUrl" => "images\\Chocolate Biscuits.jpg", "weight" => "1 pack", "price" => 40, "category" => "biscuits", "desc" => "Chocolate cream biscuits."],
        ["id" => 150, "name" => "Strawberry Biscuits", "imageUrl" => "images\\Strawberry Biscuits.jpg", "weight" => "1 pack", "price" => 40, "category" => "biscuits", "desc" => "Strawberry cream biscuits."],
        ["id" => 151, "name" => "Orange Biscuits", "imageUrl" => "images\\Orange Biscuits.jpg", "weight" => "1 pack", "price" => 35, "category" => "biscuits", "desc" => "Orange flavor biscuits."],
        ["id" => 152, "name" => "Peanut Chikki", "imageUrl" => "images\\Peanut Chikki.jpg", "weight" => "1 pack", "price" => 40, "category" => "sweets", "desc" => "Peanut brittle."],
        ["id" => 153, "name" => "Sesame Chikki", "imageUrl" => "images\\Sesame Chikki.jpg", "weight" => "1 pack", "price" => 45, "category" => "sweets", "desc" => "Sesame seed brittle."],
        ["id" => 154, "name" => "Coconut Chikki", "imageUrl" => "images\\Coconut Chikki.jpg", "weight" => "1 pack", "price" => 45, "category" => "sweets", "desc" => "Coconut jaggery bar."],
        ["id" => 155, "name" => "Dry Fruit Chikki", "imageUrl" => "images\\Dry Fruit Chikki.jpg", "weight" => "1 pack", "price" => 80, "category" => "sweets", "desc" => "Dry fruit & nut chikki."],
        ["id" => 158, "name" => "Spicy Mixture", "imageUrl" => "images\\Spicy Mixture.jpg", "weight" => "1 pack", "price" => 90, "category" => "namkeen", "desc" => "Hot and spicy mixture."],
        ["id" => 159, "name" => "Gujarati Gathiya", "imageUrl" => "images\\Gujarati Gathiya.jpg", "weight" => "1 pack", "price" => 85, "category" => "namkeen", "desc" => "Thick gathiya snack."],
        ["id" => 160, "name" => "Ratlami Sev", "imageUrl" => "images\\Ratlami Sev.jpg", "weight" => "1 pack", "price" => 95, "category" => "namkeen", "desc" => "Spicy sev from Ratlam."],
        ["id" => 161, "name" => "Nylon Sev", "imageUrl" => "images\\Nylon Sev.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Thin nylon sev."],
        ["id" => 162, "name" => "Dal Mixture", "imageUrl" => "images\\Dal Mixture.jpg", "weight" => "1 pack", "price" => 85, "category" => "namkeen", "desc" => "Lentil mixture."],
        ["id" => 163, "name" => "Corn Mixture", "imageUrl" => "images\\Corn Mixture.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Corn flakes mixture."],
        ["id" => 164, "name" => "Bombay Mixture", "imageUrl" => "images\\Bombay Mixture.jpg", "weight" => "1 pack", "price" => 90, "category" => "namkeen", "desc" => "Classic Bombay mix."],
        ["id" => 165, "name" => "Punjabi Mixture", "imageUrl" => "images\\Punjabi Mixture.jpg", "weight" => "1 pack", "price" => 95, "category" => "namkeen", "desc" => "Punjabi tadka mix."],
        ["id" => 166, "name" => "Chivda Mix", "imageUrl" => "images\\Chivda Mix.jpg", "weight" => "1 pack", "price" => 85, "category" => "namkeen", "desc" => "Chivda with peanuts."],
        ["id" => 167, "name" => "Poha Chivda", "imageUrl" => "images\\Poha Chivda.jpg", "weight" => "1 pack", "price" => 80, "category" => "namkeen", "desc" => "Flattened rice chivda."],
        ["id" => 168, "name" => "Dry Fruit Mixture", "imageUrl" => "images\\Dry Fruit Mixture.jpg", "weight" => "1 pack", "price" => 120, "category" => "namkeen", "desc" => "Premium dry fruit mix."],
        ["id" => 169, "name" => "Masala Papad Pack", "imageUrl" => "images\\Masala Papad Pack.jpg", "weight" => "1 pack", "price" => 60, "category" => "namkeen", "desc" => "Masala papad (10 pcs)."],
        ["id" => 170, "name" => "Roasted Chana Snack", "imageUrl" => "images\\Roasted Chana Snack.jpg", "weight" => "1 pack", "price" => 50, "category" => "namkeen", "desc" => "Roasted black chana."],
        ["id" => 171, "name" => "Masala Chana Snack", "imageUrl" => "images\\Masala Chana Snack.jpg", "weight" => "1 pack", "price" => 60, "category" => "namkeen", "desc" => "Spicy roasted chana."],
        ["id" => 172, "name" => "Sweet Corn Snack", "imageUrl" => "images\\Sweet Corn Snack.jpg", "weight" => "1 pack", "price" => 65, "category" => "chips", "desc" => "Sweet corn puffs."],
        ["id" => 173, "name" => "Roasted Peanut Pack", "imageUrl" => "images\\Roasted Peanut Pack.jpg", "weight" => "1 pack", "price" => 55, "category" => "namkeen", "desc" => "Plain roasted peanuts."],
        ["id" => 174, "name" => "Masala Peanut Pack", "imageUrl" => "images\\Masala Peanut Pack.jpg", "weight" => "1 pack", "price" => 65, "category" => "namkeen", "desc" => "Masala coated peanuts."],
        ["id" => 175, "name" => "Garlic Peanut Pack", "imageUrl" => "images\\Garlic Peanut Pack.jpg", "weight" => "1 pack", "price" => 70, "category" => "namkeen", "desc" => "Garlic flavored peanuts."],
        ["id" => 177, "name" => "BBQ Peanut Pack", "imageUrl" => "images\\BBQ Peanut Pack.jpg", "weight" => "1 pack", "price" => 75, "category" => "namkeen", "desc" => "BBQ peanuts."],
        ["id" => 178, "name" => "Spicy Peanut Pack", "imageUrl" => "images\\Spicy Peanut Pack.jpg", "weight" => "1 pack", "price" => 70, "category" => "namkeen", "desc" => "Extra spicy peanuts."],
        ["id" => 179, "name" => "Lemon Peanut Pack", "imageUrl" => "images\\Lemon Peanut Pack.jpg", "weight" => "1 pack", "price" => 65, "category" => "namkeen", "desc" => "Lemon chili peanuts."],
        ["id" => 181, "name" => "Caramel Peanut Pack", "imageUrl" => "images\\Caramel Peanut Pack.jpg", "weight" => "1 pack", "price" => 80, "category" => "sweets", "desc" => "Caramel candy peanuts."],
        ["id" => 184, "name" => "Spicy Corn Chips", "imageUrl" => "images\\Spicy Corn Chips.jpg", "weight" => "1 pack", "price" => 50, "category" => "chips", "desc" => "Spicy corn chips."],
        ["id" => 185, "name" => "Cheese Corn Chips", "imageUrl" => "images\\Cheese Corn Chips.jpg", "weight" => "1 pack", "price" => 55, "category" => "chips", "desc" => "Cheese flavored corn chips."],
        ["id" => 186, "name" => "BBQ Corn Chips", "imageUrl" => "images\\BBQ Corn Chips.jpg", "weight" => "1 pack", "price" => 55, "category" => "chips", "desc" => "BBQ corn chips."],
        ["id" => 187, "name" => "Masala Corn Chips", "imageUrl" => "images\\Masala Corn Chips.jpg", "weight" => "1 pack", "price" => 50, "category" => "chips", "desc" => "Masala corn chips."],
        ["id" => 189, "name" => "Garlic Corn Chips", "imageUrl" => "images\\Garlic Corn Chips.jpg", "weight" => "1 pack", "price" => 55, "category" => "chips", "desc" => "Garlic corn chips."],
        ["id" => 191, "name" => "Lemon Corn Chips", "imageUrl" => "images\\Lemon Corn Chips.jpg", "weight" => "1 pack", "price" => 55, "category" => "chips", "desc" => "Lemon flavored corn chips."],
        ["id" => 193, "name" => "Jalapeno Corn Chips", "imageUrl" => "images\\Jalapeno Corn Chips.jpg", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Jalapeno corn chips."],
        ["id" => 194, "name" => "Salsa Corn Chips", "imageUrl" => "images\\Salsa Corn Chips.jpg", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Salsa corn chips."],
        ["id" => 196, "name" => "Mexican Nachos", "imageUrl" => "images\\Mexican Nachos.jpg", "weight" => "1 pack", "price" => 65, "category" => "chips", "desc" => "Mexican style nachos."],
        ["id" => 245, "name" => "Mexican Nachos", "imageUrl" => "images\\Mexican Nachos.jpg", "weight" => "1 pack", "price" => 75, "category" => "chips", "desc" => "Mexican style nachos."],
        ["id" => 260, "name" => "Garlic Corn Chips", "imageUrl" => "images\\Garlic Corn Chips.jpg", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Garlic corn chips."],
        ["id" => 262, "name" => "Lemon Corn Chips", "imageUrl" => "images\\Lemon Corn Chips.jpg", "weight" => "1 pack", "price" => 60, "category" => "chips", "desc" => "Lemon corn chips."],
        ["id" => 264, "name" => "Salsa Corn Chips", "imageUrl" => "images\\Salsa Corn Chips.jpg", "weight" => "1 pack", "price" => 65, "category" => "chips", "desc" => "Salsa corn chips."],
        ["id" => 265, "name" => "Cheese Corn Chips", "imageUrl" => "images\\Cheese Corn Chips.jpg", "weight" => "1 pack", "price" => 65, "category" => "chips", "desc" => "Cheese corn chips."],
        ["id" => 266, "name" => "BBQ Corn Chips", "imageUrl" => "images\\BBQ Corn Chips.jpg", "weight" => "1 pack", "price" => 65, "category" => "chips", "desc" => "BBQ corn chips."]
    ];
    $result = [];
    foreach ($staticData as $item) {
        $imageUrl = !empty($item['imageUrl']) ? normalizeImagePath($item['imageUrl']) : '';
        $result[] = [
            'id' => $item['id'],
            'name' => $item['name'] . (!empty($item['weight']) ? ' (' . $item['weight'] . ')' : ''),
            'price' => $item['price'],
            'category' => $item['category'],
            'image' => $imageUrl,
            'desc' => $item['desc'],
            'weight' => $item['weight'] ?? ''
        ];
    }
    $fallback = $result;
    return $fallback;
}
// Get products from DB, fallback if empty
$productsData = getSnacksProducts();
if (empty($productsData)) {
    $productsData = getFallbackSnacksProducts();
}
// Handle AJAX requests (cart endpoints)
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'] ?? '';
    switch ($action) {
        case 'products':
            $search = $_GET['search'] ?? '';
            if ($search) {
                $filtered = array_filter($productsData, function ($p) use ($search) {
                    $term = strtolower($search);
                    return strpos(strtolower($p['name']), $term) !== false || strpos(strtolower($p['desc']), $term) !== false;
                });
                echo json_encode(array_values($filtered));
            } else {
                echo json_encode($productsData);
            }
            exit;
        case 'cart':
            echo json_encode($_SESSION['cart']);
            exit;
        case 'add_cart':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $product = getProductById($id);
                if (!$product) {
                    foreach ($productsData as $p) {
                        if ($p['id'] == $id) {
                            $product = [
                                'id' => $p['id'],
                                'name' => $p['name'],
                                'kn_name' => $p['name'],
                                'price' => $p['price'],
                                'icon' => 'fa-bowl-food'
                            ];
                            break;
                        }
                    }
                }
                if ($product) {
                    $found = false;
                    foreach ($_SESSION['cart'] as &$item) {
                        if ($item['id'] == $id) {
                            $item['qty'] += 1;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $_SESSION['cart'][] = [
                            'id' => $product['id'],
                            'name' => $product['name'],
                            'kn' => $product['kn_name'] ?? $product['name'],
                            'price' => $product['price'],
                            'qty' => 1,
                            'icon' => $product['icon'] ?? 'fa-bowl-food'
                        ];
                    }
                    echo json_encode(['success' => true, 'cart' => $_SESSION['cart']]);
                    exit;
                }
            }
            echo json_encode(['success' => false]);
            exit;
        case 'update_cart':
            $id = (int) ($_POST['id'] ?? 0);
            $qty = (int) ($_POST['qty'] ?? 0);
            if ($id && $qty > 0) {
                foreach ($_SESSION['cart'] as &$item) {
                    if ($item['id'] == $id) {
                        $item['qty'] = $qty;
                        break;
                    }
                }
            } elseif ($id && $qty == 0) {
                $_SESSION['cart'] = array_filter($_SESSION['cart'], fn($i) => $i['id'] != $id);
            }
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            echo json_encode(['success' => true, 'cart' => $_SESSION['cart']]);
            exit;
        case 'remove_cart':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $_SESSION['cart'] = array_filter($_SESSION['cart'], fn($i) => $i['id'] != $id);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
                echo json_encode(['success' => true, 'cart' => $_SESSION['cart']]);
                exit;
            }
            echo json_encode(['success' => false]);
            exit;
        case 'clear_cart':
            $_SESSION['cart'] = [];
            echo json_encode(['success' => true]);
            exit;
        default:
            echo json_encode(['error' => 'Invalid action']);
            exit;
    }
}
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>SnackStreet · 300+ Crunchy Delights</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Font: Inter + Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            display: block;
        }

        :root {
            --primary: #e07c3c;
            --primary-dark: #c25e2a;
            --secondary: #ffb27a;
            --bg-soft: #fff3e8;
            --card-bg: #ffffff;
            --text-dark: #3e2a21;
            --text-mute: #9b7b64;
            --pill-bg: #ffede0;
            --shadow: 0 12px 28px rgba(0, 0, 0, 0.04);
        }

        #app {
            max-width: 1500px;
            width: 100%;
            background: var(--card-bg);
            border-radius: 2rem;
            box-shadow: var(--shadow);
            padding: 1.5rem 1.8rem 2.5rem;
        }

        .brand-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #ffdfc4;
        }

        .title h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #e07c3c, #f7a55a);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .title .kannada {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary-dark);
            letter-spacing: 0.5px;
        }

        .cart-link {
            background: var(--pill-bg);
            padding: 0.7rem 1.5rem;
            border-radius: 60px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: 0.2s;
            border: 1px solid #ffd9ba;
            text-decoration: none;
            color: inherit;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .cart-link i {
            font-size: 1.4rem;
            color: var(--primary);
        }

        .cart-link .cart-count {
            background: var(--primary);
            color: white;
            padding: 0.1rem 0.7rem;
            border-radius: 40px;
            font-weight: 700;
            margin-left: 4px;
        }

        .cart-link:hover {
            background: #ffe3d4;
            transform: scale(1.02);
        }

        .search-section {
            margin-bottom: 2rem;
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            background: #fef6ef;
            border-radius: 80px;
            padding: 0.2rem 0.2rem 0.2rem 1.8rem;
            border: 1px solid #ffdcc2;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }

        .search-wrapper i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .search-wrapper input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 1rem 0.8rem;
            font-size: 1rem;
            outline: none;
            font-weight: 500;
        }

        .search-wrapper button {
            background: var(--primary);
            border: none;
            color: white;
            padding: 0.7rem 1.8rem;
            border-radius: 60px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.15s;
        }

        .filter-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
            margin: 1.5rem 0 0.5rem;
        }

        .pill {
            background: var(--pill-bg);
            color: var(--primary-dark);
            padding: 0.5rem 1.3rem;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #ffdcbf;
            cursor: pointer;
            transition: 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .pill.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 10px rgba(224, 124, 60, 0.2);
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 1.8rem;
            margin: 2rem 0;
        }

        .product-card {
            background: var(--card-bg);
            border-radius: 28px;
            padding: 1.2rem 1rem 1.5rem;
            transition: all 0.25s;
            border: 1px solid #fae1ce;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-6px);
            border-color: #ffcba8;
            box-shadow: 0 20px 30px -12px rgba(224, 124, 60, 0.12);
        }

        .product-img {
            width: 80px;
            height: 80px;
            background: #fef3ea;
            border-radius: 24px;
            margin-bottom: 1rem;
            border: 1px solid #ffdfcb;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 24px;
        }

        .product-name {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--text-dark);
        }

        .product-desc {
            font-size: 0.75rem;
            color: #ad886e;
            background: #fff7f0;
            padding: 6px 10px;
            border-radius: 40px;
            margin: 8px 0;
            line-height: 1.3;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 6px;
        }

        .price {
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--primary);
        }

        .add-btn {
            background: #ffede0;
            border: none;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: 0.15s;
            border: 1px solid #ffd2b5;
        }

        .add-btn:hover {
            background: var(--primary);
            color: white;
            transform: scale(0.96);
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

            .product-name {
                font-size: 1rem;
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
    </div>
    <div class="search-section">
        <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ">
            <button id="searchBtn">🔍 Search</button>
        </div>
        <div class="filter-pills" id="filterPills">
            <span class="pill active" data-category="all">🍽️ All / ಎಲ್ಲ</span>
            <span class="pill" data-category="chips">🥔 Chips</span>
            <span class="pill" data-category="namkeen">🥨 Namkeen</span>
            <span class="pill" data-category="biscuits">🍪 Biscuits</span>
            <span class="pill" data-category="sweets">🍬 Sweets</span>
        </div>
    </div>
    <div class="product-grid" id="productGrid"></div>
    <div class="no-results" id="noResultsMsg">😕 No matching snacks · ದಯವಿಟ್ಟು ಬೇರೆ ಹುಡುಕಿ</div>
    </div>
    <script>
        // ============================================================
        // JAVASCRIPT – SESSION-BASED CART VIA AJAX + LOCALSTORAGE FALLBACK
        // ============================================================
        (function() {
            // ----- PRODUCT DATA FROM PHP (embedded) -----
            const productsData = <?php echo json_encode($productsData); ?>;
            // Ensure each product has imageUrl
            const products = productsData.map(p => ({
                id: p.id,
                name: p.name,
                price: p.price,
                category: p.category,
                image: p.image || '',
                desc: p.desc || "Irresistible crunch, perfectly crispy.",
                weight: p.weight || ''
            }));
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
                const filtered = products.filter(p => {
                    const catMatch = activeCategory === 'all' || p.category === activeCategory;
                    const term = searchTerm.trim().toLowerCase();
                    const searchMatch = term === '' || p.name.toLowerCase().includes(term) || (p.desc && p.desc.toLowerCase().includes(term));
                    return catMatch && searchMatch;
                });
                if (filtered.length === 0) {
                    productGrid.innerHTML = '';
                    noResultsMsg.style.display = 'block';
                    return;
                }
                noResultsMsg.style.display = 'none';
                productGrid.innerHTML = filtered.map(prod => {
                    const imageHtml = prod.image ?
                        `<img src="${prod.image}" alt="${prod.name}" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23e07c3c%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpath d=%22M12 2L2 7l10 5 10-5-10-5z%22/%3E%3Cpath d=%22M2 17l10 5 10-5%22/%3E%3Cpath d=%22M2 12l10 5 10-5%22/%3E%3C/svg%3E'">` :
                        `<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#e07c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>`;
                    return `
                <div class="product-card">
                    <div class="product-img">${imageHtml}</div>
                    <div class="product-name">${prod.name.replace(/[&<>]/g, function(m){if(m==='&')return '&amp;';if(m==='<')return '&lt;';if(m==='>')return '&gt;';return m;})}</div>
                    <div class="product-desc"><i class="fa-regular fa-comment-dots"></i> ${prod.desc}</div>
                    <div class="price-row">
                        <span class="price">₹${prod.price}</span>
                        <button class="add-btn" data-id="${prod.id}" title="Add to cart · ಕಾರ್ಟ್‌ಗೆ ಸೇರಿಸಿ">
                            <i class="fa-solid fa-cart-plus"></i> Add to cart
                        </button>
                    </div>
                </div>
            `;
                }).join('');
                document.querySelectorAll('.add-btn').forEach(btn => {
                    btn.addEventListener('click', () => addToCart(parseInt(btn.dataset.id)));
                });
            }

            function performSearch() {
                searchTerm = searchInput.value;
                renderProducts();
            }
            // Event listeners
            filterPills.forEach(pill => {
                pill.addEventListener('click', () => {
                    filterPills.forEach(p => p.classList.remove('active'));
                    pill.classList.add('active');
                    activeCategory = pill.dataset.category;
                    renderProducts();
                });
            });
            searchBtn.addEventListener('click', performSearch);
            searchInput.addEventListener('keyup', e => {
                if (e.key === 'Enter') performSearch();
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