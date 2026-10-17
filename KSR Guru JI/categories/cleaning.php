<?php
// ============================================================
// cleaning.php - Dynamic Cleaning Supplies Page (images fixed)
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
// ============================================================
// HELPER: Normalize image path (Windows → web-relative)
// ============================================================
if (!function_exists('normalizeImagePath')) {
function normalizeImagePath(?string $rawPath): string {
    if (empty($rawPath)) return '';
    // Remove Windows drive letter and backslashes
    $clean = str_replace('\\', '/', $rawPath);
    // Remove everything before 'images/' and prepend a slash
    if (preg_match('#(?:.*?)(images/.*)$#i', $clean, $matches)) {
        return '/' . $matches[1];
    }
    // If no 'images/' found, just return the filename
    return basename($clean);
}
}
// ============================================================
// HELPER: Get cleaning products from DB or fallback static data
// ============================================================
function getCleaningProducts(string $search = ''): array {
    try {
        $pdo = getDBConnection();
        $categoryId = null;
        // Try to get category ID using 'slug' column first
        $schema = getProductSchemaInfo();
        $hasSlug = $schema["has_cat_slug"];
        if ($hasSlug) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
            $stmt->execute(['cleaning-supplies']);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($category) {
                $categoryId = $category['id'];
            }
        }
        // If slug didn't work, try by name
        if (!$categoryId) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name LIKE ? OR name LIKE ?");
            $stmt->execute(['%cleaning%', '%Cleaning%']);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($category) {
                $categoryId = $category['id'];
            }
        }
        if ($categoryId) {
            // Check for description columns
            $schema = getProductSchemaInfo();
        $descCol = $schema["desc_col"];
        $imgCol = $schema["img_col"];
            $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.emoji, p.icon,
                           {$descCol} AS description_en,
                           {$imgCol} AS image_url,
                           c.name AS category_name
                    FROM products p
                    JOIN categories c ON p.category_id = c.id
                    WHERE p.category_id = ?";
            $params = [$categoryId];
            if (!empty($search)) {
                $sql .= " AND (p.name LIKE ? OR p.kn_name LIKE ? OR {$descCol} LIKE ?)";
                $like = '%' . $search . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
            $sql .= " ORDER BY p.name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                // Normalize image paths
                foreach ($rows as &$row) {
                    if (!empty($row['image_url'])) {
                        $row['image_url'] = normalizeImagePath($row['image_url']);
                    }
                }
                return $rows;
            }
        }
    } catch (Throwable $e) {
        // Fall back to static data on any database error
    }
    return getFallbackProducts($search);
}
// ============================================================
// FALLBACK PRODUCTS – always generated fresh from static data
// ============================================================
function getFallbackProducts(string $search = ''): array {
    static $fallback = null;
    if ($fallback === null) {
        // All products with Windows paths (normalized later)
        $data = [
            ["id" => 1, "name" => "Floor Cleaner", "category" => "floor", "price" => 140, "imageUrl" => "images\\Floor Cleaner 130.jpg", "description" => "1L bottle, removes dirt and grime", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 2, "name" => "Toilet Cleaner", "category" => "bathroom", "price" => 160, "imageUrl" => "images\\Toilet Cleaner 195.jpg", "description" => "1L, disinfects and deodorizes", "icon" => "fa-toilet", "categoryName" => "Bathroom"],
            ["id" => 3, "name" => "Glass Cleaner", "category" => "glass", "price" => 75, "imageUrl" => "images\\Glass Cleaner 75.jpg", "description" => "500ml, streak-free shine", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 4, "name" => "Surface Cleaner", "category" => "all-purpose", "price" => 130, "imageUrl" => "images\\Surface Cleaner 130.jpg", "description" => "500ml, for all surfaces", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 5, "name" => "Disinfectant Liquid", "category" => "disinfectant", "price" => 170, "imageUrl" => "images\\Disinfectant Liquid 170.jpg", "description" => "1L, kills 99.9% germs", "icon" => "fa-shield-virus", "categoryName" => "Disinfectant"],
            ["id" => 6, "name" => "Phenyl", "category" => "floor", "price" => 150, "imageUrl" => "images\\Phenyl 210.jpg", "description" => "1L, concentrated floor cleaner", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 7, "name" => "Bleach Liquid", "category" => "disinfectant", "price" => 150, "imageUrl" => "images\\Bleach Liquid 150.jpg", "description" => "1L, kills germs", "icon" => "fa-shield-virus", "categoryName" => "Disinfectant"],
            ["id" => 8, "name" => "Window Cleaner Gel", "category" => "glass", "price" => 125, "imageUrl" => "images\\Window Cleaner Gel 125.jpg", "description" => "500ml, for windows", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 9, "name" => "Air Freshener", "category" => "all-purpose", "price" => 60, "imageUrl" => "images\\Air Freshener 60.jpg", "description" => "500ml, removes odors", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 10, "name" => "Laundry Detergent", "category" => "laundry", "price" => 300, "imageUrl" => "images\\Laundry Detergent 300.jpg", "description" => "1L, powerful", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 11, "name" => "Fabric Softener", "category" => "laundry", "price" => 290, "imageUrl" => "images\\Fabric Softener 290.jpg", "description" => "500ml, smooth feel", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 12, "name" => "Stain Remover", "category" => "laundry", "price" => 60, "imageUrl" => "images\\Stain Remover 60.jpg", "description" => "200ml, for tough stains", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 13, "name" => "Dishwash Liquid", "category" => "kitchen", "price" => 25, "imageUrl" => "images\\Dishwash Liquid 25.jpg", "description" => "500ml, cuts grease", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 14, "name" => "Dishwash Bar", "category" => "kitchen", "price" => 72, "imageUrl" => "images\\Dishwash Bar 72.jpg", "description" => "200g, for utensils", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 15, "name" => "Steel Scrubber", "category" => "tools", "price" => 20, "imageUrl" => "images\\Steel Scrubber 15.jpg", "description" => "1pc, for tough scrubbing", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 16, "name" => "Sponge", "category" => "tools", "price" => 15, "imageUrl" => "images\\Sponge 20.jpg", "description" => "1pc, for gentle cleaning", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 17, "name" => "Scouring Pad", "category" => "tools", "price" => 110, "imageUrl" => "images\\Scouring Pad 110.jpg", "description" => "1pc, for pots and pans", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 18, "name" => "Oven Cleaner", "category" => "kitchen", "price" => 220, "imageUrl" => "images\\Oven Cleaner 220.jpg", "description" => "500ml, removes burnt food", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 19, "name" => "Fridge Cleaner", "category" => "kitchen", "price" => 100, "imageUrl" => "images\\Fridge Cleaner 100.jpg", "description" => "500ml, odor eliminator", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 20, "name" => "Drain Cleaner", "category" => "all-purpose", "price" => 30, "imageUrl" => "images\\Drain Cleaner 30.jpg", "description" => "500ml, unblocks drains", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 21, "name" => "Mop", "category" => "tools", "price" => 610, "imageUrl" => "images\\Mop 610.jpg", "description" => "1pc, cotton mop", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 22, "name" => "Spin Mop Set", "category" => "tools", "price" => 800, "imageUrl" => "images\\Spin Mop Set 800.jpg", "description" => "1set, with bucket", "icon" => "fa-hand-sparkles", "categoryName" => "Tools"],
            ["id" => 23, "name" => "Bucket", "category" => "tools", "price" => 200, "imageUrl" => "images\\Bucket 200.jpg", "description" => "10L, plastic", "icon" => "fa-bucket", "categoryName" => "Tools"],
            ["id" => 24, "name" => "Broom (Soft)", "category" => "tools", "price" => 120, "imageUrl" => "images\\Broom (Soft) 190.jpg", "description" => "1pc, soft bristles", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 25, "name" => "Broom (Hard)", "category" => "tools", "price" => 130, "imageUrl" => "images\\Broom (Hard) 100.jpg", "description" => "1pc, hard bristles", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 26, "name" => "Dustpan", "category" => "tools", "price" => 50, "imageUrl" => "images\\Dustpan 50.jpg", "description" => "1pc, with handle", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 27, "name" => "Floor Brush", "category" => "tools", "price" => 80, "imageUrl" => "images\\Floor Brush 60.jpg", "description" => "1pc, stiff bristles", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 28, "name" => "Toilet Brush", "category" => "tools", "price" => 280, "imageUrl" => "images\\Toilet Brush 280.jpg", "description" => "1pc, with holder", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 29, "name" => "Hand Gloves", "category" => "tools", "price" => 60, "imageUrl" => "images\\Hand Gloves 50.jpg", "description" => "1pair, rubber", "icon" => "fa-hand-sparkles", "categoryName" => "Tools"],
            ["id" => 30, "name" => "Garbage Bags Small", "category" => "tools", "price" => 100, "imageUrl" => "images\\Garbage Bags Small 300.jpg", "description" => "30pcs, 10L", "icon" => "fa-trash-alt", "categoryName" => "Tools"],
            ["id" => 31, "name" => "Garbage Bags Large", "category" => "tools", "price" => 180, "imageUrl" => "images\\Garbage Bags Large 180.jpg", "description" => "20pcs, 20L", "icon" => "fa-trash-alt", "categoryName" => "Tools"],
            ["id" => 32, "name" => "Trash Can", "category" => "tools", "price" => 260, "imageUrl" => "images\\Trash Can 250.jpg", "description" => "20L, plastic", "icon" => "fa-trash-alt", "categoryName" => "Tools"],
            ["id" => 33, "name" => "Dusting Cloth", "category" => "tools", "price" => 200, "imageUrl" => "images\\Dusting Cloth 200.jpg", "description" => "2pcs, microfiber", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 34, "name" => "Microfiber Cloth", "category" => "tools", "price" => 150, "imageUrl" => "images\\Microfiber Cloth 150.jpg", "description" => "2pcs, for glass", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 35, "name" => "Feather Duster", "category" => "tools", "price" => 250, "imageUrl" => "images\\Feather Duster 250.jpg", "description" => "1pc, extendable", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 36, "name" => "Vacuum Cleaner", "category" => "tools", "price" => 8000, "imageUrl" => "images\\Vacuum Cleaner 6500.jpg", "description" => "1pc, bagless", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 37, "name" => "Handheld Vacuum", "category" => "tools", "price" => 3000, "imageUrl" => "images\\Handheld Vacuum 2800.jpg", "description" => "1pc, cordless", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 38, "name" => "Carpet Cleaner", "category" => "floor", "price" => 7000, "imageUrl" => "images\\Carpet Cleaner 7000.jpg", "description" => "500ml, removes stains", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 39, "name" => "Upholstery Cleaner", "category" => "all-purpose", "price" => 660, "imageUrl" => "images\\Upholstery Cleaner 660.jpg", "description" => "500ml, for sofas", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 41, "name" => "Laundry Basket", "category" => "laundry", "price" => 350, "imageUrl" => "images\\Laundry Basket 800.jpg", "description" => "1pc, plastic", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 42, "name" => "Cloth Clips", "category" => "laundry", "price" => 120, "imageUrl" => "images\\Cloth Clips 120.jpg", "description" => "20pcs, plastic", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 43, "name" => "Clothes Brush", "category" => "laundry", "price" => 100, "imageUrl" => "images\\Clothes Brush 100.jpg", "description" => "1pc, for lint", "icon" => "fa-brush", "categoryName" => "Laundry"],
            ["id" => 44, "name" => "Iron Cleaner", "category" => "laundry", "price" => 750, "imageUrl" => "images\\Iron Cleaner 750.jpg", "description" => "100ml, for steam irons", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 45, "name" => "Window Wiper", "category" => "glass", "price" => 390, "imageUrl" => "images\\Window Wiper 390.jpg", "description" => "1pc, squeegee", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 46, "name" => "Squeegee", "category" => "glass", "price" => 150, "imageUrl" => "images\\Squeegee 150.jpg", "description" => "1pc, for floors", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 47, "name" => "Air Freshener Spray", "category" => "all-purpose", "price" => 180, "imageUrl" => "images\\Air Freshener Spray 120.jpg", "description" => "250ml, fresh scent", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 49, "name" => "Incense Sticks", "category" => "all-purpose", "price" => 60, "imageUrl" => "images\\Incense Sticks 60.jpg", "description" => "100g, floral", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 50, "name" => "Camphor", "category" => "all-purpose", "price" => 35, "imageUrl" => "images\\Camphor 35.jpg", "description" => "50g, pure", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 51, "name" => "Moth Repellent Balls", "category" => "all-purpose", "price" => 100, "imageUrl" => "images\\Moth Repellent Balls 100.jpg", "description" => "100g, protects clothes", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 55, "name" => "Carpet Deodorizer", "category" => "floor", "price" => 1700, "imageUrl" => "images\\Carpet Deodorizer 1700.jpg", "description" => "500g, freshens carpets", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 56, "name" => "Pet Odor Eliminator", "category" => "all-purpose", "price" => 210, "imageUrl" => "images\\Pet Odor Eliminator 210.jpg", "description" => "500ml, for pets", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 57, "name" => "Garbage Odor Neutralizer", "category" => "tools", "price" => 55, "imageUrl" => "images\\Garbage Odor Neutralizer 55.jpg", "description" => "200g, for trash cans", "icon" => "fa-trash-alt", "categoryName" => "Tools"],
            ["id" => 58, "name" => "Bathroom Odor Eliminator Spray", "category" => "bathroom", "price" => 50, "imageUrl" => "images\\Bathroom Odor Eliminator Spray 50.jpg", "description" => "250ml, for bathrooms", "icon" => "fa-toilet", "categoryName" => "Bathroom"],
            ["id" => 59, "name" => "Air Purifying Bag", "category" => "all-purpose", "price" => 1700, "imageUrl" => "images\\Air Purifying Bag 1700.jpg", "description" => "1pc, natural odor absorber", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 61, "name" => "Mattress Cleaner Spray", "category" => "all-purpose", "price" => 250, "imageUrl" => "images\\Mattress Cleaner Spray 290.jpg", "description" => "500ml, removes odors", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 62, "name" => "Pillow Cleaner", "category" => "all-purpose", "price" => 150, "imageUrl" => "images\\Pillow Cleaner 660.jpg", "description" => "200ml, for pillows", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 64, "name" => "Sofa Cleaner", "category" => "all-purpose", "price" => 300, "imageUrl" => "images\\Sofa Cleaner 700.jpg", "description" => "500ml, upholstery", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 65, "name" => "Tile Cleaner", "category" => "floor", "price" => 180, "imageUrl" => "images\\Tile Cleaner 400.jpg", "description" => "1L, for floors", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 66, "name" => "Grout Cleaner", "category" => "floor", "price" => 200, "imageUrl" => "images\\Grout Cleaner 205.jpg", "description" => "500ml, for tiles", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 67, "name" => "Baby Surface Cleaner", "category" => "all-purpose", "price" => 300, "imageUrl" => "images\\Baby Surface Cleaner 300.jpg", "description" => "500ml, gentle", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 68, "name" => "Bathroom Cleaner Kit", "category" => "all-purpose", "price" => 800, "imageUrl" => "images\\Bathroom Cleaner Kit 800.jpg", "description" => "", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 69, "name" => "Floor Polish", "category" => "floor", "price" => 220, "imageUrl" => "images\\Floor Disinfectant Wipes 300.jpg", "description" => "500ml, for shine", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 70, "name" => "Glass Polish", "category" => "glass", "price" => 720, "imageUrl" => "images\\Glass Polish 720.jpg", "description" => "200ml, for mirrors", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 71, "name" => "Rust Remover", "category" => "all-purpose", "price" => 60, "imageUrl" => "images\\Rust Remover 60.jpg", "description" => "200ml, for metal", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 72, "name" => "Lime Scale Remover", "category" => "bathroom", "price" => 200, "imageUrl" => "images\\Lime Scale Remover 200.jpg", "description" => "200ml, for faucets", "icon" => "fa-toilet", "categoryName" => "Bathroom"],
            ["id" => 73, "name" => "Odor Eliminator", "category" => "all-purpose", "price" => 1800, "imageUrl" => "images\\Odor Eliminator 1800.jpg", "description" => "250ml, neutralizes smells", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 74, "name" => "Pet Stain Cleaner", "category" => "all-purpose", "price" => 880, "imageUrl" => "images\\Pet Stain Cleaner 880.jpg", "description" => "500ml, for pets", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 75, "name" => "Baby Surface Cleaner", "category" => "all-purpose", "price" => 200, "imageUrl" => "images\\Baby Surface Cleaner 200.jpg", "description" => "500ml, gentle", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 76, "name" => "Multipurpose Spray", "category" => "all-purpose", "price" => 186, "imageUrl" => "images\\Multipurpose Spray 186.jpg", "description" => "500ml, everyday use", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 77, "name" => "Antiseptic Liquid", "category" => "disinfectant", "price" => 150, "imageUrl" => "images\\Antiseptic Liquid.webp", "description" => "500ml, for wounds", "icon" => "fa-shield-virus", "categoryName" => "Disinfectant"],
            ["id" => 78, "name" => "Floor Disinfectant Wipes", "category" => "disinfectant", "price" => 180, "imageUrl" => "images\\Floor Disinfectant Wipes 250.jpg", "description" => "40pcs, for floors", "icon" => "fa-wipe", "categoryName" => "Disinfectant"],
            ["id" => 79, "name" => "Kitchen Degreaser", "category" => "kitchen", "price" => 150, "imageUrl" => "images\\Kitchen Degreaser 150.jpg", "description" => "500ml, for ovens", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 80, "name" => "Chimney Cleaner", "category" => "kitchen", "price" => 250, "imageUrl" => "images\\Chimney Cleaner 300.jpg", "description" => "500ml, removes grease", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 81, "name" => "Water Tank Cleaner", "category" => "all-purpose", "price" => 300, "imageUrl" => "images\\Water Tank Cleaner 380.jpg", "description" => "1L, for overhead tanks", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 82, "name" => "Pipe Cleaner", "category" => "all-purpose", "price" => 120, "imageUrl" => "images\\Pipe Cleaner120.jpg", "description" => "500ml, for drains", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 83, "name" => "Drain Opener Gel", "category" => "all-purpose", "price" => 240, "imageUrl" => "images\\Drain Opener Gel 600.jpg", "description" => "500ml, thick gel", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 84, "name" => "Toilet Freshener Block", "category" => "bathroom", "price" => 120, "imageUrl" => "images\\Toilet Freshener Block 200.jpg", "description" => "2pcs, for toilet", "icon" => "fa-toilet", "categoryName" => "Bathroom"],
            ["id" => 85, "name" => "Room Fragrance Diffuser", "category" => "all-purpose", "price" => 600, "imageUrl" => "images\\Room Fragrance Diffuser 350.jpg", "description" => "1pc, electric", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 86, "name" => "Essential Oil Cleaner", "category" => "all-purpose", "price" => 315, "imageUrl" => "images\\Essential Oil Cleaner 315.jpg", "description" => "100ml, natural", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 87, "name" => "Natural Vinegar Cleaner", "category" => "all-purpose", "price" => 280, "imageUrl" => "images\\Natural Vinegar Cleaner280.jpg", "description" => "500ml, eco-friendly", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 88, "name" => "Baking Soda Cleaner", "category" => "all-purpose", "price" => 400, "imageUrl" => "images\\Baking Soda Cleaner 400.jpg", "description" => "500g, multi-use", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 89, "name" => "Lemon Cleaner", "category" => "all-purpose", "price" => 140, "imageUrl" => "images\\Lemon Cleaner 140.jpg", "description" => "500ml, citrus scent", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 90, "name" => "Herbal Floor Cleaner", "category" => "floor", "price" => 180, "imageUrl" => "images\\Herbal Floor Cleaner 180.jpg", "description" => "1L, ayurvedic", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 91, "name" => "Organic Dishwash Liquid", "category" => "kitchen", "price" => 160, "imageUrl" => "images\\Organic Dishwash Liquid 160.jpg", "description" => "500ml, natural", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 92, "name" => "Organic Laundry Liquid", "category" => "laundry", "price" => 220, "imageUrl" => "images\\Organic Laundry Liquid 220.jpg", "description" => "1L, plant-based", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 93, "name" => "Bathroom Odor Eliminator Spray", "category" => "bathroom", "price" => 250, "imageUrl" => "images\\Bathroom Odor Eliminator Spray 250.jpg", "description" => "250ml, for bathrooms", "icon" => "fa-toilet", "categoryName" => "Bathroom"],
            ["id" => 94, "name" => "Disposable Gloves", "category" => "tools", "price" => 120, "imageUrl" => "images\\Disposable Gloves 120.jpg", "description" => "10pcs, powder-free", "icon" => "fa-hand-sparkles", "categoryName" => "Tools"],
            ["id" => 95, "name" => "Bathtub Cleaner", "category" => "bathroom", "price" => 420, "imageUrl" => "images\\Bathtub Cleaner 420.jpg", "description" => "250ml, for bathrooms", "icon" => "fa-toilet", "categoryName" => "Bathroom"],
            ["id" => 96, "name" => "Bio-Enzyme Cleaner", "category" => "all-purpose", "price" => 680, "imageUrl" => "images\\Bio Enzyme Cleaner 680.jpg", "description" => "500ml, natural", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 97, "name" => "Bleach Liquid", "category" => "disinfectant", "price" => 330, "imageUrl" => "images\\Bleach Liquid 330.jpg", "description" => "1L, kills germs", "icon" => "fa-shield-virus", "categoryName" => "Disinfectant"],
            ["id" => 98, "name" => "Broom (Hard)", "category" => "tools", "price" => 500, "imageUrl" => "images\\Broom (Hard) 500.jpg", "description" => "1pc, hard bristles", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 99, "name" => "Floor Cleaning Kit", "category" => "floor", "price" => 2400, "imageUrl" => "images\\Floor Cleaning Kit 2400.jpg", "description" => "1set, mop + bucket", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 100, "name" => "Broom (Hard)", "category" => "tools", "price" => 600, "imageUrl" => "images\\Broom (Hard) 600.jpg", "description" => "1pc, hard bristles", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 101, "name" => "Ceiling Cleaner Brush", "category" => "tools", "price" => 900, "imageUrl" => "images\\Ceiling Cleaner Brush 900.jpg", "description" => "1pc, extendable", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 102, "name" => "Fan Cleaning Brush", "category" => "tools", "price" => 2400, "imageUrl" => "images\\Fan Cleaning Brush 2400.jpg", "description" => "1pc, for ceiling fans", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 103, "name" => "AC Cleaning Spray", "category" => "all-purpose", "price" => 510, "imageUrl" => "images\\Home Care\\AC Cleaning Spray 510.jpg", "description" => "500ml, for AC coils", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 104, "name" => "Keyboard Cleaner", "category" => "tools", "price" => 790, "imageUrl" => "images\\Keyboard Cleaner 790.jpg", "description" => "100ml, gel", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 105, "name" => "Screen Cleaning Spray", "category" => "glass", "price" => 480, "imageUrl" => "images\\Screen Cleaning Spray 480.jpg", "description" => "100ml, for monitors", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 106, "name" => "Car Interior Cleaner", "category" => "all-purpose", "price" => 500, "imageUrl" => "images\\Car Interior Cleaner 500.jpg", "description" => "500ml, for dashboards", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 107, "name" => "Helmet Cleaner", "category" => "all-purpose", "price" => 180, "imageUrl" => "images\\Helmet Cleaner 180.jpg", "description" => "200ml, for helmets", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 108, "name" => "Glass Cloth Premium", "category" => "glass", "price" => 150, "imageUrl" => "images\\Glass Cloth Premium 150.jpg", "description" => "2pcs, lint-free", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 109, "name" => "Heavy Duty Scrubber", "category" => "tools", "price" => 200, "imageUrl" => "images\\Heavy Duty Scrubber 200.jpg", "description" => "1pc, for tough stains", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 110, "name" => "Broom (Soft)", "category" => "tools", "price" => 90, "imageUrl" => "images\\Broom (Soft) 90.jpg", "description" => "1pc, for soft bristles", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 111, "name" => "Broom (Soft)", "category" => "tools", "price" => 180, "imageUrl" => "images\\Broom (Soft) 180.jpg", "description" => "1pc, for soft bristles", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 112, "name" => "Broom (Soft)", "category" => "tools", "price" => 190, "imageUrl" => "images\\Broom (Soft) 190.jpg", "description" => "1pc, for soft bristles", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 113, "name" => "Broom (Soft)", "category" => "tools", "price" => 250, "imageUrl" => "images\\Broom (Soft) 250.jpg", "description" => "1pc, for soft bristles", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 114, "name" => "Paint Cleaner", "category" => "all-purpose", "price" => 630, "imageUrl" => "images\\Paint Cleaner 630.jpg", "description" => "200ml, for fresh paint", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 115, "name" => "Plastic Cleaner", "category" => "all-purpose", "price" => 999, "imageUrl" => "images\\Plastic Cleaner 999.jpg", "description" => "200ml, restores shine", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 116, "name" => "Electronic Cleaner Spray", "category" => "all-purpose", "price" => 480, "imageUrl" => "images\\Electronic Cleaner Spray 480.jpg", "description" => "200ml, safe for circuits", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 117, "name" => "Silver Polish", "category" => "all-purpose", "price" => 700, "imageUrl" => "images\\Silver Polish 700.jpg", "description" => "100ml, for silver", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 118, "name" => "Copper Cleaner", "category" => "all-purpose", "price" => 220, "imageUrl" => "images\\Copper Cleaner", "description" => "100ml, for copper", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 119, "name" => "Brass Cleaner", "category" => "all-purpose", "price" => 400, "imageUrl" => "images\\Brass Cleaner 399.jpg", "description" => "100ml, for brass", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 120, "name" => "Stone Cleaner", "category" => "floor", "price" => 2200, "imageUrl" => "images\\Stone Cleaner 2200.jpg", "description" => "500ml, for marble/granite", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 121, "name" => "Granite Cleaner", "category" => "floor", "price" => 450, "imageUrl" => "images\\Granite Cleaner 450.jpg", "description" => "500ml, for granite", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 122, "name" => "Marble Cleaner", "category" => "floor", "price" => 880, "imageUrl" => "images\\Marble Cleaner 880.jpg", "description" => "500ml, for marble", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 123, "name" => "Leather Cleaner", "category" => "all-purpose", "price" => 2250, "imageUrl" => "images\\Leather Cleaner 2250.jpg", "description" => "200ml, for sofas", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 124, "name" => "Wood Floor Cleaner", "category" => "floor", "price" => 400, "imageUrl" => "images\\Wood Floor Cleaner 400.jpg", "description" => "1L, for wooden floors", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 125, "name" => "Tile Polish Liquid", "category" => "floor", "price" => 240, "imageUrl" => "images\\Tile Polish Liquid 240.jpg", "description" => "500ml, for shine", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 128, "name" => "Carpet Deodorizer", "category" => "floor", "price" => 1700, "imageUrl" => "images\\Carpet Deodorizer 1700.jpg", "description" => "300g, powder", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 129, "name" => "Bucket", "category" => "tools", "price" => 100, "imageUrl" => "images\\Bucket 100.jpg", "description" => "10L, plastic", "icon" => "fa-bucket", "categoryName" => "Tools"],
            ["id" => 130, "name" => "Mattress Protector Spray", "category" => "all-purpose", "price" => 290, "imageUrl" => "images\\Mattress Protector Spray 290.jpg", "description" => "300ml, waterproofing", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 131, "name" => "Window Cleaning Kit", "category" => "glass", "price" => 190, "imageUrl" => "images\\Window Cleaning Kit 181.jpg", "description" => "1set, squeegee+cloth", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 132, "name" => "Floor Scrubbing Machine", "category" => "tools", "price" => 1810, "imageUrl" => "images\\Floor Scrubbing Machine 1810.jpg", "description" => "1unit, commercial", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 133, "name" => "Steam Cleaner", "category" => "tools", "price" => 4000, "imageUrl" => "images\\Steam Cleaner 4000.jpg", "description" => "1unit, for deep cleaning", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 134, "name" => "Pressure Washer", "category" => "tools", "price" => 7800, "imageUrl" => "images\\Pressure Washer 7800.jpg", "description" => "1unit, 1500W", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 135, "name" => "Industrial Mop", "category" => "tools", "price" => 1500, "imageUrl" => "images\\Industrial Mop 1500.jpg", "description" => "1pc, heavy duty", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 136, "name" => "Industrial Broom", "category" => "tools", "price" => 200, "imageUrl" => "images\\Industrial Broom 200.jpg", "description" => "1pc, large", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 137, "name" => "Bucket", "category" => "tools", "price" => 600, "imageUrl" => "images\\Bucket 600.jpg", "description" => "10L, plastic", "icon" => "fa-bucket", "categoryName" => "Tools"],
            ["id" => 138, "name" => "Wet Floor Sign Board", "category" => "tools", "price" => 800, "imageUrl" => "images\\Wet Floor Sign Board 800.jpg", "description" => "1pc, warning", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 139, "name" => "Janitor Cart", "category" => "tools", "price" => 500, "imageUrl" => "images\\Janitor Cart 500.jpg", "description" => "1pc, with bags", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 140, "name" => "Floor Sweeper Machine", "category" => "tools", "price" => 7000, "imageUrl" => "images\\Floor Sweeper Machine 7000.jpg", "description" => "1unit, automatic", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 141, "name" => "Air Freshener Refill", "category" => "all-purpose", "price" => 250, "imageUrl" => "images\\Air Freshener Refill 250.webp", "description" => "250ml, for dispensers", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 142, "name" => "Automatic Air Freshener", "category" => "all-purpose", "price" => 190, "imageUrl" => "images\\Automatic Air Freshener 190.jpg", "description" => "1pc, with motion sensor", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 143, "name" => "Soap Dispenser", "category" => "tools", "price" => 100, "imageUrl" => "images\\Soap Dispenser 100.jpg", "description" => "1pc, wall mount", "icon" => "fa-hand-sparkles", "categoryName" => "Tools"],
            ["id" => 144, "name" => "Camera Lens Cleaner", "category" => "tools", "price" => 280, "imageUrl" => "images\\Camera Lens Cleaner 280.jpg", "description" => "1pc, for camera lenses", "icon" => "fa-hand-sparkles", "categoryName" => "Tools"],
            ["id" => 145, "name" => "Hand Dryer Machine", "category" => "tools", "price" => 3000, "imageUrl" => "images\\Hand Dryer Machine 3000.jpg", "description" => "1pc, wall mounted", "icon" => "fa-hand-sparkles", "categoryName" => "Tools"],
            ["id" => 146, "name" => "Paper Towel Dispenser", "category" => "tools", "price" => 1100, "imageUrl" => "images\\Paper Towel Dispenser 1100.jpg", "description" => "1pc, stainless steel", "icon" => "fa-trash-alt", "categoryName" => "Tools"],
            ["id" => 147, "name" => "Toilet Paper Dispenser", "category" => "tools", "price" => 2450, "imageUrl" => "images\\Toilet Paper Dispenser 2450.jpg", "description" => "1pc, plastic", "icon" => "fa-trash-alt", "categoryName" => "Tools"],
            ["id" => 148, "name" => "Urinal Screen", "category" => "bathroom", "price" => 290, "imageUrl" => "images\\Urinal Screen 290.jpg", "description" => "2pcs, anti-odor", "icon" => "fa-toilet", "categoryName" => "Bathroom"],
            ["id" => 149, "name" => "Urinal Cleaner Block", "category" => "bathroom", "price" => 1400, "imageUrl" => "images\\Urinal Cleaner Block 1400.jpg", "description" => "2pcs, hanging", "icon" => "fa-toilet", "categoryName" => "Bathroom"],
            ["id" => 150, "name" => "Washroom Cleaner Kit", "category" => "bathroom", "price" => 140, "imageUrl" => "images\\Washroom Cleaner Kit 140.jpg", "description" => "1set, spray + brush + block", "icon" => "fa-toilet", "categoryName" => "Bathroom"],
            ["id" => 151, "name" => "Kitchen Sink Cleaner", "category" => "kitchen", "price" => 180, "imageUrl" => "images\\Kitchen Sink Cleaner.webp", "description" => "500ml, removes stains", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 152, "name" => "Drain Mesh Filter", "category" => "kitchen", "price" => 25, "imageUrl" => "images\\Drain Mesh Filter 25.jpg", "description" => "1pc, for sink", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 153, "name" => "Grease Trap Cleaner", "category" => "kitchen", "price" => 9500, "imageUrl" => "images\\Grease Trap Cleaner 9500.jpg", "description" => "500ml, for drains", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 154, "name" => "Oil Stain Remover", "category" => "laundry", "price" => 600, "imageUrl" => "images\\Oil Stain Remover 600.jpg", "description" => "200ml, for clothes", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 155, "name" => "Ink Stain Remover", "category" => "laundry", "price" => 200, "imageUrl" => "images\\Ink Stain Remover 200.jpg", "description" => "100ml, for fabrics", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 156, "name" => "Paint Stain Remover", "category" => "all-purpose", "price" => 700, "imageUrl" => "images\\Paint Stain Remover 700.jpg", "description" => "200ml, for surfaces", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 157, "name" => "Adhesive Remover", "category" => "all-purpose", "price" => 200, "imageUrl" => "images\\Adhesive Remover 200.jpg", "description" => "200ml, for stickers", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 158, "name" => "Glue Residue Cleaner", "category" => "all-purpose", "price" => 500, "imageUrl" => "images\\Glue Residue Cleaner 500.jpg", "description" => "200ml, for tough residue", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 159, "name" => "Sticker Remover Spray", "category" => "all-purpose", "price" => 350, "imageUrl" => "images\\Sticker Remover Spray 350.jpg", "description" => "200ml, for glass", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 160, "name" => "Glass Scratch Remover", "category" => "glass", "price" => 500, "imageUrl" => "images\\Glass Scratch Remover 500.jpg", "description" => "100ml, for minor scratches", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 161, "name" => "Tile Scrubber Brush", "category" => "tools", "price" => 200, "imageUrl" => "images\\Tile Scrubber Brush 200.jpg", "description" => "1pc, with handle", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 162, "name" => "Corner Cleaning Brush", "category" => "tools", "price" => 450, "imageUrl" => "images\\Corner Cleaning Brush 450.jpg", "description" => "1pc, for corners", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 163, "name" => "Grout Scrubber", "category" => "tools", "price" => 190, "imageUrl" => "images\\Grout Scrubber 190.jpg", "description" => "1pc, for grout lines", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 164, "name" => "Electric Scrubber", "category" => "tools", "price" => 2950, "imageUrl" => "images\\Electric Scrubber 2950.jpg", "description" => "1pc, rechargeable", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 165, "name" => "Camera Lens Cleaner", "category" => "tools", "price" => 360, "imageUrl" => "images\\Camera Lens Cleaner 360.jpg", "description" => "1pc, for camera lenses", "icon" => "fa-hand-sparkles", "categoryName" => "Tools"],
            ["id" => 166, "name" => "Bottle Cleaning Brush", "category" => "tools", "price" => 60, "imageUrl" => "images\\Bottle Cleaning Brush 60.jpg", "description" => "1pc, narrow", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 167, "name" => "Straw Cleaning Brush", "category" => "tools", "price" => 1600, "imageUrl" => "images\\Straw Cleaning Brush 1600.jpg", "description" => "1pc, thin", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 168, "name" => "Pipe Cleaning Brush", "category" => "tools", "price" => 1700, "imageUrl" => "images\\Pipe Cleaning Brush 1700.jpg", "description" => "1pc, flexible", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 169, "name" => "Sink Cleaning Brush", "category" => "tools", "price" => 100, "imageUrl" => "images\\Sink Cleaning Brush 270.jpg", "description" => "1pc, with handle", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 170, "name" => "Toilet Rim Brush", "category" => "tools", "price" => 270, "imageUrl" => "images\\Toilet Rim Brush 270.jpg", "description" => "1pc, for under rim", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 171, "name" => "Camphor", "category" => "all-purpose", "price" => 280, "imageUrl" => "images\\Camphor 280.jpg", "description" => "50g, pure", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 172, "name" => "Carpet Cleaner Liquid", "category" => "floor", "price" => 600, "imageUrl" => "images\\Carpet Cleaner Liquid 590.jpg", "description" => "500ml, removes stains", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 173, "name" => "Carpet Deodorizer", "category" => "floor", "price" => 1700, "imageUrl" => "images\\Carpet Deodorizer 1700.jpg", "description" => "500ml, removes stains", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 174, "name" => "Ceiling Cleaner Brush", "category" => "floor", "price" => 900, "imageUrl" => "images\\Ceiling Cleaner Brush 900.jpg", "description" => "500ml, removes stains", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 175, "name" => "Chimney Cleaner", "category" => "floor", "price" => 300, "imageUrl" => "images\\Chimney Cleaner 300.jpg", "description" => "500ml, removes stains", "icon" => "fa-bucket", "categoryName" => "Floor"],
            ["id" => 176, "name" => "Cleaning Caddy", "category" => "floor", "price" => 900, "imageUrl" => "images\\Cleaning Caddy 900.jpg", "description" => "500ml, removes stains", "icon" => "", "categoryName" => "Floor"],
            ["id" => 177, "name" => "Static Dust Cloth", "category" => "tools", "price" => 180, "imageUrl" => "images\\Static Dust Cloth 180.jpg", "description" => "5pcs, disposable", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 180, "name" => "Blind Cleaner Tool", "category" => "tools", "price" => 300, "imageUrl" => "images\\Blind Cleaner Tool 300.jpg", "description" => "1pc, for window blinds", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 181, "name" => "Cloth Clips", "category" => "tools", "price" => 60, "imageUrl" => "images\\Cloth Clips 35.jpg", "description" => "1pc, angled", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 182, "name" => "Closet Freshener", "category" => "tools", "price" => 350, "imageUrl" => "images\\Closet Freshener 350.jpg", "description" => "1pc, angled", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 183, "name" => "Cleaning Combo Kit", "category" => "tools", "price" => 250, "imageUrl" => "images\\Cleaning Combo Kit 250.jpg", "description" => "1pc, angled", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 184, "name" => "Door Frame Cleaner", "category" => "tools", "price" => 150, "imageUrl" => "images\\Door Frame Cleaner 150.jpg", "description" => "1pc, angled", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 187, "name" => "Compressed Air Cleaner", "category" => "tools", "price" => 3000, "imageUrl" => "images\\Compressed Air Cleaner 3000.jpg", "description" => "200ml, for electronics", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 188, "name" => "Screen Cleaning Cloth", "category" => "glass", "price" => 1800, "imageUrl" => "images\\Screen Cleaning Cloth 1800.jpg", "description" => "2pcs, microfiber", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 189, "name" => "Screen Cleaning Kit", "category" => "glass", "price" => 600, "imageUrl" => "images\\Screen Cleaning Kit 600.jpg", "description" => "1set, spray+cloth", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 190, "name" => "Laptop Cleaning Spray", "category" => "all-purpose", "price" => 522, "imageUrl" => "images\\Laptop Cleaning Spray 522.jpg", "description" => "100ml, for screens", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 191, "name" => "Cloth Clips", "category" => "tools", "price" => 60, "imageUrl" => "images\\Cloth Clips 45.jpg", "description" => "1pc, angled", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 192, "name" => "Cloth Clips", "category" => "tools", "price" => 120, "imageUrl" => "images\\Cloth Clips 120.jpg", "description" => "1pc, angled", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 193, "name" => "TV Screen Cleaner", "category" => "glass", "price" => 150, "imageUrl" => "images\\TV Screen Cleaner 150.jpg", "description" => "200ml, for LED", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 194, "name" => "Clothes Brush", "category" => "laundry", "price" => 200, "imageUrl" => "images\\Clothes Brush 200.jpg", "description" => "1pc, for lint", "icon" => "fa-brush", "categoryName" => "Laundry"],
            ["id" => 195, "name" => "Clothes Brush", "category" => "laundry", "price" => 300, "imageUrl" => "images\\Clothes Brush 300.jpg", "description" => "1pc, for lint", "icon" => "fa-brush", "categoryName" => "Laundry"],
            ["id" => 196, "name" => "Dusting Cloth", "category" => "tools", "price" => 360, "imageUrl" => "images\\Dusting Cloth 360.jpg", "description" => "2pcs, microfiber", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 197, "name" => "Dusting Cloth", "category" => "tools", "price" => 300, "imageUrl" => "images\\Dusting Cloth 300.jpg", "description" => "2pcs, microfiber", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 198, "name" => "Dustpan", "category" => "tools", "price" => 250, "imageUrl" => "images\\Dustpan 250.jpg", "description" => "1pc, with handle", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 199, "name" => "Electronic Cleaner Spray", "category" => "all-purpose", "price" => 500, "imageUrl" => "images\\Electronic Cleaner Spray 500.jpg", "description" => "200ml, safe for circuits", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 200, "name" => "Fabric Softener", "category" => "laundry", "price" => 360, "imageUrl" => "images\\Fabric Softener 360.jpg", "description" => "500ml, smooth feel", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 201, "name" => "Anti-Allergen Spray", "category" => "all-purpose", "price" => 390, "imageUrl" => "images\\Antifungal Spray 385.jpg", "description" => "300ml, for bedding", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 202, "name" => "Anti-Allergen Spray", "category" => "all-purpose", "price" => 1900, "imageUrl" => "images\\Anti-Allergen Spray 1900.jpg", "description" => "300ml, for bedding", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 204, "name" => "Pet Hair Remover Roller", "category" => "tools", "price" => 290, "imageUrl" => "images\\Pet Hair Remover Roller 290.jpg", "description" => "1pc, reusable", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 205, "name" => "Lint Roller", "category" => "laundry", "price" => 120, "imageUrl" => "images\\Lint Roller 120.jpg", "description" => "1pc, for clothes", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 206, "name" => "Lint Remover Machine", "category" => "laundry", "price" => 900, "imageUrl" => "images\\Lint Remover Machine 900.jpg", "description" => "1pc, battery operated", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 207, "name" => "Vacuum Cleaner", "category" => "tools", "price" => 5000, "imageUrl" => "images\\Vacuum Cleaner 4400.jpg", "description" => "1pc, bagless", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 208, "name" => "Trash Can", "category" => "tools", "price" => 600, "imageUrl" => "images\\Trash Can 600.jpg", "description" => "20L, plastic", "icon" => "fa-trash-alt", "categoryName" => "Tools"],
            ["id" => 209, "name" => "Trash Can", "category" => "tools", "price" => 800, "imageUrl" => "images\\Trash Can 800.jpg", "description" => "20L, plastic", "icon" => "fa-trash-alt", "categoryName" => "Tools"],
            ["id" => 210, "name" => "Trash Can", "category" => "tools", "price" => 1500, "imageUrl" => "images\\Trash Can 1500.jpg", "description" => "20L, plastic", "icon" => "fa-trash-alt", "categoryName" => "Tools"],
            ["id" => 211, "name" => "Steel Scrubber", "category" => "tools", "price" => 160, "imageUrl" => "images\\Steel Scrubber 155.jpg", "description" => "1pc, for tough scrubbing", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 212, "name" => "Steel Scrubber", "category" => "tools", "price" => 60, "imageUrl" => "images\\Steel Scrubber 50.jpg", "description" => "1pc, for tough scrubbing", "icon" => "fa-brush", "categoryName" => "Tools"],
            ["id" => 213, "name" => "Squeegee", "category" => "glass", "price" => 700, "imageUrl" => "images\\Squeegee 700.jpg", "description" => "1pc, for floors", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 214, "name" => "Soap Scum Remover", "category" => "bathroom", "price" => 330, "imageUrl" => "images\\Soap Scum Remover 330.jpg", "description" => "200ml, for showers", "icon" => "fa-toilet", "categoryName" => "Bathroom"],
            ["id" => 216, "name" => "Mirror Anti-Fog Spray", "category" => "glass", "price" => 250, "imageUrl" => "images\\Mirror Anti-Fog Spray 250.jpg", "description" => "100ml, for mirrors", "icon" => "fa-window-maximize", "categoryName" => "Glass"],
            ["id" => 218, "name" => "Septic Tank Cleaner", "category" => "all-purpose", "price" => 290, "imageUrl" => "images\\Septic Tank Cleaner 290.jpg", "description" => "1L, for septic", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 219, "name" => "Bio-Enzyme Cleaner", "category" => "all-purpose", "price" => 750, "imageUrl" => "images\\Bio-Enzyme Cleaner 750.jpg", "description" => "500ml, natural", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 221, "name" => "Green Cleaning Liquid", "category" => "all-purpose", "price" => 3280, "imageUrl" => "images\\Green Cleaning Liquid 3280.jpg", "description" => "1L, eco-friendly", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 222, "name" => "Plant-Based Cleaner", "category" => "all-purpose", "price" => 850, "imageUrl" => "images\\Plant-Based Cleaner 850.jpg", "description" => "500ml, natural", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 223, "name" => "Non-Toxic Cleaner", "category" => "all-purpose", "price" => 160, "imageUrl" => "images\\Non-Toxic Cleaner160.jpg", "description" => "500ml, safe for kids", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 225, "name" => "Refill Cleaning Pack", "category" => "all-purpose", "price" => 330, "imageUrl" => "images\\Refill Cleaning Pack 330.jpg", "description" => "1L, concentrated", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 228, "name" => "Reusable Spray Bottle", "category" => "tools", "price" => 50, "imageUrl" => "images\\Reusable Spray Bottle 50.jpg", "description" => "500ml, empty", "icon" => "fa-spray-can-sparkles", "categoryName" => "Tools"],
            ["id" => 230, "name" => "Measuring Cup (Cleaning)", "category" => "tools", "price" => 400, "imageUrl" => "images\\Measuring Cup 400.jpg", "description" => "1pc, 1L", "icon" => "fa-bucket", "categoryName" => "Tools"],
            ["id" => 231, "name" => "Measuring Spoon Set", "category" => "tools", "price" => 600, "imageUrl" => "images\\Measuring Spoon Set 600.jpg", "description" => "1set, plastic", "icon" => "fa-bucket", "categoryName" => "Tools"],
            ["id" => 232, "name" => "Cleaning Caddy", "category" => "tools", "price" => 900, "imageUrl" => "images\\Cleaning Caddy 900.jpg", "description" => "1pc, with handle", "icon" => "fa-bucket", "categoryName" => "Tools"],
            ["id" => 233, "name" => "Storage Rack (Cleaning)", "category" => "tools", "price" => 662, "imageUrl" => "images\\Storage Rack 662.jpg", "description" => "1pc, wall mounted", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 235, "name" => "Under Sink Organizer", "category" => "tools", "price" => 780, "imageUrl" => "images\\Under Sink Organizer 780.jpg", "description" => "1pc, adjustable", "icon" => "fa-broom", "categoryName" => "Tools"],
            ["id" => 236, "name" => "Closet Freshener", "category" => "all-purpose", "price" => 350, "imageUrl" => "images\\Closet Freshener 350.jpg", "description" => "200g, for cupboards", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 238, "name" => "Fridge Deodorizer", "category" => "kitchen", "price" => 450, "imageUrl" => "images\\Fridge Deodorizer 450.jpg", "description" => "100g, absorbs odors", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 239, "name" => "Garbage Bin Deodorizer", "category" => "all-purpose", "price" => 3300, "imageUrl" => "images\\Garbage Bin Deodorizer 3300.jpg", "description" => "200g, for bins", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 240, "name" => "Air Purifier Filter Cleaner", "category" => "all-purpose", "price" => 6680, "imageUrl" => "images\\Air Purifier Filter Cleaner 6680.jpg", "description" => "200ml, for filters", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 241, "name" => "AC Filter Cleaner", "category" => "all-purpose", "price" => 550, "imageUrl" => "images\\AC Filter Cleaner 550.jpg", "description" => "200ml, for AC", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 242, "name" => "Humidifier Cleaner", "category" => "all-purpose", "price" => 1100, "imageUrl" => "images\\Humidifier Cleaner 1100.jpg", "description" => "200ml, descaling", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 243, "name" => "Water Purifier Cleaner", "category" => "all-purpose", "price" => 430, "imageUrl" => "images\\Water Purifier Cleaner 430.jpg", "description" => "200ml, for RO", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 244, "name" => "Pipe Descaler", "category" => "all-purpose", "price" => 1200, "imageUrl" => "images\\Pipe Descaler 1200.jpg", "description" => "200ml, for pipes", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 245, "name" => "Boiler Cleaner", "category" => "all-purpose", "price" => 6000, "imageUrl" => "images\\Boiler Cleaner 6000.jpg", "description" => "500ml, for geysers", "icon" => "fa-spray-can-sparkles", "categoryName" => "All-Purpose"],
            ["id" => 246, "name" => "Coffee Machine Cleaner", "category" => "kitchen", "price" => 1000, "imageUrl" => "images\\Coffee Machine Cleaner 1000.jpg", "description" => "200ml, descaling", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 247, "name" => "Kettle Descaler", "category" => "kitchen", "price" => 680, "imageUrl" => "images\\Kettle Descaler 680.jpg", "description" => "200ml, for kettles", "icon" => "fa-kitchen-set", "categoryName" => "Kitchen"],
            ["id" => 249, "name" => "Washing Machine Cleaner", "category" => "laundry", "price" => 300, "imageUrl" => "images\\Washing Machine Cleaner 300.jpg", "description" => "200ml, for machines", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"],
            ["id" => 250, "name" => "Laundry Drum Cleaner", "category" => "laundry", "price" => 230, "imageUrl" => "images\\Laundry Drum Cleaner 230.jpg", "description" => "200ml, for drums", "icon" => "fa-jug-detergent", "categoryName" => "Laundry"]
        ];
        $fallback = [];
        $categoryMap = [
            'floor' => 'floor',
            'bathroom' => 'bathroom',
            'kitchen' => 'kitchen',
            'laundry' => 'laundry',
            'tools' => 'all-purpose',
        ];
        foreach ($data as $item) {
            $cat = strtolower($item['category'] ?? 'all-purpose');
            $mapped = $categoryMap[$cat] ?? 'all-purpose';
            $fallback[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'kn_name' => '',
                'price' => (float)$item['price'],
                'category' => $mapped,
                'emoji' => '🧹',           // fallback only when image fails
                'description_en' => $item['description'] ?? 'Cleaning supply',
                'icon' => $item['icon'] ?? 'fa-broom',
                'image_url' => normalizeImagePath($item['imageUrl'] ?? ''),
                'category_name' => ucfirst(str_replace('-', ' ', $mapped))
            ];
        }
    }
    // Apply search filter
    if (!empty($search)) {
        $searchLower = strtolower($search);
        return array_filter($fallback, function($item) use ($searchLower) {
            return strpos(strtolower($item['name']), $searchLower) !== false ||
                   strpos(strtolower($item['description_en']), $searchLower) !== false;
        });
    }
    return $fallback;
}
// ============================================================
// AJAX HANDLING (unchanged)
// ============================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'] ?? '';
    switch ($action) {
        case 'products':
            $search = $_GET['search'] ?? '';
            $products = getCleaningProducts($search);
            $result = array_map(function($p) {
                return [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'price' => (float)$p['price'],
                    'emoji' => $p['emoji'] ?? '🧹',
                    'description' => $p['description_en'] ?? '',
                    'category' => $p['category'] ?? 'all-purpose',
                    'knName' => $p['kn_name'] ?? '',
                    'image_url' => $p['image_url'] ?? ''
                ];
            }, $products);
            echo json_encode($result);
            exit;
        case 'cart':
            echo json_encode($_SESSION['cart']);
            exit;
        case 'add_cart':
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $product = null;
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("SELECT id, name, kn_name, price FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $product = null;
                }
                if (!$product) {
                    $fallback = getFallbackProducts();
                    foreach ($fallback as $fp) {
                        if ($fp['id'] == $id) {
                            $product = [
                                'id' => $fp['id'],
                                'name' => $fp['name'],
                                'kn_name' => $fp['kn_name'] ?? $fp['name'],
                                'price' => $fp['price']
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
                            'price' => (float)$product['price'],
                            'qty' => 1,
                            'icon' => 'fa-broom'
                        ];
                    }
                    echo json_encode(['success' => true, 'cart' => $_SESSION['cart']]);
                    exit;
                }
            }
            echo json_encode(['success' => false]);
            exit;
        case 'update_cart':
            $id = (int)($_POST['id'] ?? 0);
            $qty = (int)($_POST['qty'] ?? 0);
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
            $id = (int)($_POST['id'] ?? 0);
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
// ============================================================
// MAIN PAGE: Get products and render
// ============================================================
$products = getCleaningProducts();
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
// Top picks: first 6 products
$topPicks = array_slice($products, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>CleanHome · Professional Cleaning Supplies · KSR Guru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        /* ====== STYLES (same as original) ====== */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f9f6f2; padding: 24px 20px; min-height: 100vh; }
        #app { max-width: 1400px; width: 100%; margin: 0 auto; background: white; border-radius: 40px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05); padding: 28px 32px 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 16px; }
        .shop-name h1 { font-size: 2.2rem; font-weight: 700; color: #3e2c1b; line-height: 1.2; }
        .shop-name .kannada { font-size: 1.1rem; color: #b45f2b; font-weight: 500; }
        .cart-link { background: #f7ede3; padding: 12px 24px; border-radius: 60px; display: inline-flex; align-items: center; gap: 10px; transition: background 0.2s; text-decoration: none; color: inherit; font-weight: 600; font-size: 0.95rem; }
        .cart-link i { font-size: 1.4rem; color: #c97e2c; }
        .cart-link .cart-badge { background: #b45f2b; color: white; font-size: 0.85rem; font-weight: 700; padding: 2px 10px; border-radius: 40px; margin-left: 8px; }
        .cart-link:hover { background: #ffe2d0; }
        .secondary-nav { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 28px; }
        .secondary-nav a { background: #f3eee9; padding: 10px 20px; border-radius: 50px; font-size: 0.9rem; font-weight: 500; color: #5c3e24; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .secondary-nav a:hover { background: #e5d5c4; }
        .search-section { margin-bottom: 32px; }
        .search-box { display: flex; align-items: center; background: #f3eee9; border-radius: 60px; padding: 8px 24px; margin-bottom: 20px; border: 1px solid #e6d9cd; max-width: 500px; }
        .search-box i { color: #b45f2b; font-size: 1.2rem; margin-right: 14px; }
        .search-box input { flex: 1; border: none; background: transparent; padding: 14px 0; font-size: 1rem; outline: none; color: #2f2216; }
        .filter-pills { display: flex; flex-wrap: wrap; gap: 12px; }
        .pill { background: #f3eee9; padding: 10px 24px; border-radius: 60px; font-size: 1rem; font-weight: 500; color: #4f3e2e; cursor: pointer; transition: all 0.2s; }
        .pill.active { background: #5c3e24; color: white; }
        .pill:hover { background: #e0cfbf; }
        .hero { background: linear-gradient(135deg, #2f6e4f 0%, #3b8b5e 100%); color: white; padding: 40px 40px; border-radius: 36px; margin: 30px 0 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 30px; box-shadow: 0 15px 30px rgba(47,110,79,0.3); }
        .hero-content h2 { font-size: 2rem; margin-bottom: 15px; }
        .hero-content p { font-size: 1rem; opacity: 0.95; max-width: 500px; }
        .hero-btn { background: white; color: #2f6e4f; padding: 12px 30px; border-radius: 50px; font-weight: 600; font-size: 1rem; transition: 0.2s; display: inline-block; margin-top: 20px; text-decoration: none; }
        .hero-btn:hover { background: #f1f5f9; transform: translateY(-3px); }
        .hero-image i { font-size: 8rem; color: rgba(255,255,255,0.3); }
        .benefits-banner { background: #d9f0e5; padding: 25px 30px; border-radius: 60px; display: flex; align-items: center; gap: 20px; margin-bottom: 40px; border: 1px solid #b8e0d0; }
        .benefit-icon i { font-size: 3rem; color: #2f6e4f; }
        .benefit-content h3 { font-size: 1.3rem; margin-bottom: 5px; }
        .top-picks { margin: 32px 0 40px; background: linear-gradient(135deg, #fff6ed 0%, #fee9dd 100%); border-radius: 36px; padding: 24px 28px; border: 1px solid #fadbc8; }
        .top-picks h3 { font-size: 1.8rem; font-weight: 700; color: #3e2c1b; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .top-picks h3 i { color: #e67e22; font-size: 2rem; }
        .top-picks-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 24px; }
        .top-pick-card { background: white; border-radius: 28px; padding: 16px; text-align: center; box-shadow: 0 8px 18px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .top-pick-card:hover { transform: translateY(-6px); }
        .top-pick-img { width: 100%; aspect-ratio: 1; border-radius: 24px; object-fit: cover; background: #fcf4ec; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #a67b60; overflow: hidden; }
        .top-pick-img img { width: 100%; height: 100%; object-fit: cover; }
        .top-pick-name { font-weight: 700; font-size: 1rem; color: #2f2216; }
        .top-pick-price { font-size: 1.1rem; font-weight: 800; color: #b45f2b; margin: 8px 0; }
        .top-pick-btn { background: #b45f2b; border: none; color: white; width: 44px; height: 44px; border-radius: 50px; font-size: 1.2rem; cursor: pointer; transition: 0.1s; display: flex; align-items: center; justify-content: center; margin: 0 auto; }
        .top-pick-btn:active { transform: scale(0.94); }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 28px; margin-top: 20px; margin-bottom: 50px; }
        .product-card { background: white; border-radius: 32px; padding: 18px 16px 22px; box-shadow: 0 8px 24px rgba(0,0,0,0.04); border: 1px solid #f2e5d9; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; align-items: center; text-align: center; }
        .product-card:hover { transform: translateY(-6px); box-shadow: 0 20px 30px rgba(0,0,0,0.08); }
        .product-image { width: 110px; height: 110px; border-radius: 55px; overflow: hidden; margin: 0 auto 16px; background: #fcf4ec; border: 2px solid #f0d9c4; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #a67b60; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-name { font-weight: 700; font-size: 1.1rem; color: #2f2216; margin-top: 4px; }
        .product-name-kn { font-size: 0.8rem; color: #7b5f45; margin-bottom: 6px; }
        .product-category { font-size: 0.75rem; text-transform: uppercase; color: #af825c; font-weight: 600; margin-bottom: 4px; }
        .product-description { font-size: 0.8rem; color: #8a6e54; margin: 8px 0 12px; line-height: 1.4; padding: 0 4px; }
        .price-row { display: flex; justify-content: space-between; align-items: center; width: 100%; margin-top: 8px; }
        .product-price { font-weight: 800; font-size: 1.2rem; color: #b45f2b; }
        .add-btn { background: #b45f2b; border: none; color: white; width: 48px; height: 48px; border-radius: 60px; font-size: 1.4rem; cursor: pointer; transition: 0.1s; display: flex; align-items: center; justify-content: center; }
        .add-btn.added-flash { background: #4caf50 !important; color: white !important; }
        .add-btn:active { transform: scale(0.95); }
        .cart-sidebar { position: fixed; top: 0; right: -420px; width: 400px; height: 100vh; background: #fffefc; box-shadow: -8px 0 40px rgba(0,0,0,0.1); transition: right 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1); z-index: 1100; display: flex; flex-direction: column; border-left: 2px solid #f3e1d2; }
        .cart-sidebar.open { right: 0; }
        .cart-header { padding: 24px; border-bottom: 2px solid #f5e6db; display: flex; justify-content: space-between; align-items: center; }
        .cart-header h3 { font-size: 1.5rem; color: #3b2a1d; display: flex; gap: 10px; }
        .close-cart { font-size: 1.6rem; color: #a67b60; cursor: pointer; background: none; border: none; }
        .cart-items { flex: 1; overflow-y: auto; padding: 20px; }
        .empty-cart { text-align: center; margin-top: 60px; color: #b99278; }
        .cart-item { display: flex; gap: 14px; margin-bottom: 24px; padding-bottom: 18px; border-bottom: 1px solid #f0e0d2; align-items: center; }
        .cart-item-image { width: 44px; height: 44px; background: #daf0e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; font-size: 1.5rem; }
        .cart-item-details { flex: 1; }
        .cart-item-name { font-weight: 700; margin-bottom: 4px; font-size: 0.9rem; }
        .cart-item-price { color: #c25a3a; font-weight: 600; font-size: 0.85rem; }
        .cart-item-actions { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
        .cart-item-actions button { background: #f9ede5; width: 30px; height: 30px; border-radius: 40px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
        .cart-item-actions button:hover { background: #edd3c2; }
        .cart-footer { padding: 24px; border-top: 2px solid #f3e2d4; }
        .cart-total { display: flex; justify-content: space-between; font-weight: 800; font-size: 1.3rem; margin-bottom: 20px; }
        .checkout-btn, .continue-shopping { width: 100%; padding: 14px; border-radius: 50px; font-weight: 700; margin-bottom: 12px; border: none; cursor: pointer; }
        .checkout-btn { background: #c25a3a; color: white; }
        .continue-shopping { background: none; border: 1px solid #dcbfab; color: #5e4330; }
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(3px); z-index: 1050; display: none; }
        .overlay.active { display: block; }
        footer { text-align: center; font-size: 0.85rem; color: #b9a690; margin-top: 20px; padding-top: 16px; border-top: 1px solid #f0e3d7; }
        @media (max-width: 680px) {
            #app { padding: 20px; }
            .product-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 18px; }
            .top-picks-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
            .product-image { width: 100px; height: 100px; }
            .cart-sidebar { width: 100%; right: -100%; }
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
    <div class="hero">
        <div class="hero-content">
            <h2>Professional Cleaning, <br>For a Healthier Home</h2>
            <p>Eco‑friendly, powerful formulas that keep your family safe and your home sparkling.</p>
            <a href="../index.php" class="hero-btn">Shop Now <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="hero-image">
            <i class="fas fa-spray-can-sparkles"></i>
        </div>
    </div>
    <div class="benefits-banner">
        <div class="benefit-icon"><i class="fas fa-shield-alt"></i></div>
        <div class="benefit-content">
            <h3>Clean & Hygienic Home</h3>
            <p>Our products are designed to provide maximum cleanliness and protection for your family</p>
        </div>
    </div>
    <div class="search-section">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
        </div>
        <div class="filter-pills" id="filterPills">
            <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
            <div class="pill" data-category="floor">Floor / ನೆಲ</div>
            <div class="pill" data-category="bathroom">Bathroom / ಬಾತ್ರೂಮ್</div>
            <div class="pill" data-category="kitchen">Kitchen / ಅಡುಗೆಕೋಣೆ</div>
            <div class="pill" data-category="laundry">Laundry / ಲಾಂಡ್ರಿ</div>
            <div class="pill" data-category="all-purpose">All‑Purpose / ಎಲ್ಲಾ ಉದ್ದೇಶ</div>
        </div>
    </div>
    <!-- Top Picks / Bestsellers -->
    <div class="top-picks">
        <h3><i class="fas fa-fire"></i> Bestsellers · Top Picks</h3>
        <div class="top-picks-grid" id="topPicksGrid"></div>
    </div>
    <div class="product-grid" id="productGrid">
</div>
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
    </div>
<script>
(function() {
    // ====== PRODUCT DATA FROM PHP ======
    const products = <?php
        $productArray = [];
        foreach ($products as $p) {
            $productArray[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'price' => (float)$p['price'],
                'emoji' => $p['emoji'] ?? '🧹',
                'description' => $p['description_en'] ?? 'Clean home premium selection',
                'category' => $p['category'] ?? 'all-purpose',
                'knName' => $p['kn_name'] ?? '',
                'image_url' => $p['image_url'] ?? ''
            ];
        }
        echo json_encode($productArray);
    ?>;
    // ====== STATE ======
    let cart = <?php echo json_encode($_SESSION['cart']); ?>;
    let activeCategory = 'all';
    let searchTerm = '';
    // ====== DOM REFS ======
    const productGrid = document.getElementById('productGrid');
    const topPicksGrid = document.getElementById('topPicksGrid');
    const cartCountSpan = document.getElementById('cartCount');
    const searchInput = document.getElementById('searchInput');
    const filterPills = document.querySelectorAll('.pill');
    const cartSidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    const closeCartBtn = document.getElementById('closeCart');
    const continueShoppingBtn = document.getElementById('continueShopping');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const cartItemsContainer = document.getElementById('cartItems');
    const totalPriceSpan = document.querySelector('.total-price');
    const cartLink = document.getElementById('cartLink');
    // ====== HELPER FUNCTIONS ======
    function getImageSrc(product) {
        if (product.image_url && product.image_url.trim() !== '') {
            return product.image_url;
        }
        return null; // fallback to emoji
    }
    // ====== CART FUNCTIONS ======
    function updateCartUI() {
        const totalQty = cart.reduce((sum, i) => sum + i.qty, 0);
        cartCountSpan.innerText = totalQty;
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
    function updateCartQty(productId, qty) {
        const formData = new FormData();
        formData.append('id', productId);
        formData.append('qty', qty);
        fetch('cleaning.php?ajax=update_cart', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    cart = data.cart;
                    updateCartUI();
                    renderCartItems();
                }
            })
            .catch(console.error);
    }
    function removeFromCart(productId) {
        const formData = new FormData();
        formData.append('id', productId);
        fetch('cleaning.php?ajax=remove_cart', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    cart = data.cart;
                    updateCartUI();
                    renderCartItems();
                }
            })
            .catch(console.error);
    }
    function openCart() {
        cartSidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        renderCartItems();
    }
    function closeCart() {
        cartSidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    function renderCartItems() {
        if (!cart.length) {
            cartItemsContainer.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-basket"></i><p>Your cart is empty</p></div>`;
            totalPriceSpan.innerText = '₹0';
            return;
        }
        let total = 0;
        let html = '';
        cart.forEach(item => {
            const itemTotal = item.price * item.qty;
            total += itemTotal;
            html += `
                <div class="cart-item" data-id="${item.id}">
                    <div class="cart-item-image">🧹</div>
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
        cartItemsContainer.innerHTML = html;
        totalPriceSpan.innerText = `₹${total.toFixed(2)}`;
    }
    // ====== RENDER PRODUCTS ======
    function renderTopPicks() {
        if (!products.length) {
            topPicksGrid.innerHTML = '<p style="grid-column:1/-1; text-align:center;">No top picks available</p>';
            return;
        }
        const top = products.slice(0, 6);
        topPicksGrid.innerHTML = top.map(p => {
            const imgSrc = getImageSrc(p);
            return `
                <div class="top-pick-card">
                    <div class="top-pick-img">
                        ${imgSrc ? `<img src="${imgSrc}" alt="" onerror="this.parentElement.textContent='${p.emoji}'">` : p.emoji}
                    </div>
                    <div class="top-pick-name">${p.name}</div>
                    <div class="top-pick-price">₹${p.price.toFixed(2)}</div>
                    <button class="top-pick-btn" onclick="window.addToCart(${p.id})"><i class="fas fa-plus"></i></button>
                </div>
            `;
        }).join('');
    }
    function renderProducts() {
        let filtered = products.filter(p => {
            if (activeCategory !== 'all' && p.category !== activeCategory) return false;
            const term = searchTerm.trim().toLowerCase();
            return term === '' || p.name.toLowerCase().includes(term) || p.description.toLowerCase().includes(term);
        });
        if (!filtered.length) {
            productGrid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No cleaning products found ✨</div>`;
            return;
        }
        productGrid.innerHTML = filtered.map(p => {
            const imgSrc = getImageSrc(p);
            return `
                <div class="product-card">
                    <div class="product-image">
                        ${imgSrc ? `<img src="${imgSrc}" alt="" onerror="this.parentElement.textContent='${p.emoji}'">` : p.emoji}
                    </div>
                    <div class="product-category">${p.category}</div>
                    <div class="product-name">${p.name}</div>
                    <div class="product-description">${p.description}</div>
                    <div class="price-row">
                        <div class="product-price">₹${p.price.toFixed(2)}</div>
                        <button class="add-btn" data-id="${p.id}" onclick="window.addToCart(${p.id})"><i class="fas fa-cart-plus"></i></button>
                    </div>
                </div>
            `;
        }).join('');
    }
    // ====== EVENT BINDING ======
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
    cartLink.addEventListener('click', function(e) {
    e.preventDefault();
    if (typeof openCart === 'function') { openCart(); } else { window.location.href = '../cart/cart.php'; }
});
    closeCartBtn.addEventListener('click', closeCart);
    overlay.addEventListener('click', closeCart);
    continueShoppingBtn.addEventListener('click', closeCart);
    checkoutBtn.addEventListener('click', function() {
        if (cart.length) {
            window.location.href = '../checkout/checkout.php';
        } else {
            alert('Cart is empty');
        }
    });
    window.addToCart = addToCart;
    window.changeQty = updateCartQty;
    window.removeItem = removeFromCart;
    // ====== INIT ======
    renderTopPicks();
    renderProducts();
    updateCartUI();
    renderCartItems();
})();
</script>


  <?php
  $base_url = '../';
  $currentCategory = 'cleaning';
  include __DIR__ . '/../includes/related-section.php';
  ?>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
<script src="sorting.js"></script>
</body>
</html>
