<?php
// ============================================================
// stationery.php - Dynamic Stationery page
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
$categorySlug = 'stationery';
$stationeryCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($stationeryCategory) {
  // Fetch products from this category
  $dbProducts = getProductsByCategory($stationeryCategory['id']);
  if (!empty($dbProducts)) {
    foreach ($dbProducts as $p) {
      // Build description from description_en or use default
      $description = $p['description_en'] ?? ($p['weight'] ?? 'Quality stationery');
      $productsData[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'kn' => $p['kn_name'] ?? '',
        'category' => $p['category_slug'] ?? 'office',
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
  $staticProducts = [
    ['id' => 1, 'nameEn' => 'Ball Pen', 'category' => 'pens', 'price' => 10, 'imageUrl' => 'images\\Ball Pen.jpg', 'nameKn' => 'ಬಾಲ್ ಪೆನ್', 'description' => 'Smooth writing, long-lasting ink'],
    ['id' => 2, 'nameEn' => 'Gel Pen', 'category' => 'pens', 'price' => 15, 'imageUrl' => 'images\\Gel Pen.jpg', 'nameKn' => 'ಜೆಲ್ ಪೆನ್', 'description' => 'Vibrant colors, effortless flow'],
    ['id' => 3, 'nameEn' => 'Fountain Pen', 'category' => 'pens', 'price' => 70, 'imageUrl' => 'images\\Fountain Pen 50.jpg', 'nameKn' => 'ಫೌಂಟೇನ್ ಪೆನ್', 'description' => 'Classic elegance, refillable'],
    ['id' => 4, 'nameEn' => 'Pencil', 'category' => 'pens', 'price' => 5, 'imageUrl' => 'images\\Pencil.jpg', 'nameKn' => 'ಪೆನ್ಸಿಲ್', 'description' => 'HB grade, perfect for sketching'],
    ['id' => 5, 'nameEn' => 'Mechanical Pencil', 'category' => 'pens', 'price' => 35, 'imageUrl' => 'images\\Mechanical Pencil nicpro.jpg', 'nameKn' => 'ಮೆಕ್ಯಾನಿಕಲ್ ಪೆನ್ಸಿಲ್', 'description' => 'No sharpening needed, 0.7mm lead'],
    ['id' => 6, 'nameEn' => 'Eraser', 'category' => 'office', 'price' => 5, 'imageUrl' => 'images\\Eraser dom.jpg', 'nameKn' => 'ರಬ್ಬರ್', 'description' => 'Soft, smudge-free erasing'],
    ['id' => 7, 'nameEn' => 'Sharpener', 'category' => 'office', 'price' => 8, 'imageUrl' => 'images\\Sharpener.jpg', 'nameKn' => 'ಶಾರ್ಪನರ್', 'description' => 'Dual hole, metal blade'],
    ['id' => 8, 'nameEn' => 'Ruler', 'category' => 'office', 'price' => 15, 'imageUrl' => 'images\\Ruler.jpg', 'nameKn' => 'ಸ್ಕೇಲ್', 'description' => '15cm transparent plastic'],
    ['id' => 9, 'nameEn' => 'Geometry Box', 'category' => 'office', 'price' => 120, 'imageUrl' => 'images\\Geometry Box dom.jpg', 'nameKn' => 'ಜ್ಯಾಮಿತಿ ಪೆಟ್ಟಿಗೆ', 'description' => 'Complete set: compass, divider, scale'],
    ['id' => 10, 'nameEn' => 'Compass', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Compass.jpg', 'nameKn' => 'ಕಂಪಾಸ್', 'description' => 'Metal, adjustable for circles'],
    ['id' => 11, 'nameEn' => 'Divider', 'category' => 'office', 'price' => 20, 'imageUrl' => 'images\\Divider.jpg', 'nameKn' => 'ಡಿವೈಡರ್', 'description' => 'For accurate measurements'],
    ['id' => 12, 'nameEn' => 'Protractor', 'category' => 'office', 'price' => 15, 'imageUrl' => 'images\\Protractor.png', 'nameKn' => 'ಪ್ರೊಟ್ರಾಕ್ಟರ್', 'description' => '180° clear plastic'],
    ['id' => 13, 'nameEn' => 'Notebook', 'category' => 'notebooks', 'price' => 40, 'imageUrl' => 'images\\Notebook.jpg', 'nameKn' => 'ನೋಟ್ಬುಕ್', 'description' => '100 pages, ruled A5'],
    ['id' => 14, 'nameEn' => 'Spiral Notebook', 'category' => 'notebooks', 'price' => 70, 'imageUrl' => 'images\\Spiral Notebook.jpg', 'nameKn' => 'ಸುರುಳಿ ನೋಟ್ಬುಕ್', 'description' => 'Tear-resistant, perforated sheets'],
    ['id' => 15, 'nameEn' => 'Drawing Book', 'category' => 'art', 'price' => 60, 'imageUrl' => 'images\\Drawing Book.jpg', 'nameKn' => 'ಡ್ರಾಯಿಂಗ್ ಬುಕ್', 'description' => 'Thick pages, ideal for sketching'],
    ['id' => 16, 'nameEn' => 'Sketch Book', 'category' => 'art', 'price' => 80, 'imageUrl' => 'images\\Sketch Book.jpg', 'nameKn' => 'ಸ್ಕೆಚ್ ಬುಕ್', 'description' => 'Hardbound, acid-free paper'],
    ['id' => 17, 'nameEn' => 'Coloring Book', 'category' => 'art', 'price' => 50, 'imageUrl' => 'images\\Coloring Book.jpg', 'nameKn' => 'ಬಣ್ಣದ ಪುಸ್ತಕ', 'description' => 'Fun designs for all ages'],
    ['id' => 18, 'nameEn' => 'Highlighter', 'category' => 'pens', 'price' => 35, 'imageUrl' => 'images\\Highlighter.jpg', 'nameKn' => 'ಹೈಲೈಟರ್', 'description' => 'Neon yellow, chisel tip'],
    ['id' => 19, 'nameEn' => 'Marker', 'category' => 'pens', 'price' => 40, 'imageUrl' => 'images\\Marker camlin.jpg', 'nameKn' => 'ಮಾರ್ಕರ್', 'description' => 'Permanent, fine tip'],
    ['id' => 20, 'nameEn' => 'Whiteboard Marker', 'category' => 'pens', 'price' => 45, 'imageUrl' => 'images\\Whiteboard Marker.jpg', 'nameKn' => 'ವೈಟ್‌ಬೋರ್ಡ್ ಮಾರ್ಕರ್', 'description' => 'Easy erase, low odor'],
    ['id' => 21, 'nameEn' => 'Board Duster', 'category' => 'office', 'price' => 30, 'imageUrl' => 'images\\Board Duster.jpg', 'nameKn' => 'ಬೋರ್ಡ್ ಡಸ್ಟರ್', 'description' => 'Felt pad, magnetic'],
    ['id' => 22, 'nameEn' => 'Glue Stick', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Glue Stick.jpg', 'nameKn' => 'ಗ್ಲೂ ಸ್ಟಿಕ್', 'description' => 'Washable, no mess'],
    ['id' => 23, 'nameEn' => 'Fevicol Glue', 'category' => 'office', 'price' => 20, 'imageUrl' => 'images\\Fevicol Glue.jpg', 'nameKn' => 'ಫೆವಿಕಾಲ್ ಅಂಟು', 'description' => 'Strong bonding, tube pack'],
    ['id' => 24, 'nameEn' => 'Scissors', 'category' => 'office', 'price' => 50, 'imageUrl' => 'images\\Scissors general.jpg', 'nameKn' => 'ಕತ್ತರಿ', 'description' => 'Stainless steel, 8 inch'],
    ['id' => 25, 'nameEn' => 'Paper Cutter', 'category' => 'office', 'price' => 30, 'imageUrl' => 'images\\Paper Cutter.jpg', 'nameKn' => 'ಪೇಪರ್ ಕಟ್ಟರ್', 'description' => 'Retractable blade, safety lock'],
    ['id' => 26, 'nameEn' => 'Stapler', 'category' => 'office', 'price' => 90, 'imageUrl' => 'images\\Stapler.jpg', 'nameKn' => 'ಸ್ಟೇಪ್ಲರ್', 'description' => 'Heavy duty, 20 sheet capacity'],
    ['id' => 27, 'nameEn' => 'Stapler Pins', 'category' => 'office', 'price' => 20, 'imageUrl' => 'images\\Stapler Pins.jpg', 'nameKn' => 'ಸ್ಟೇಪ್ಲರ್ ಪಿನ್', 'description' => 'Box of 1000, 24/6 size'],
    ['id' => 28, 'nameEn' => 'Paper Clips', 'category' => 'office', 'price' => 15, 'imageUrl' => 'images\\Paper Clips.jpg', 'nameKn' => 'ಪೇಪರ್ ಕ್ಲಿಪ್', 'description' => 'Galvanized, 100 pcs'],
    ['id' => 29, 'nameEn' => 'Binder Clips', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Binder Clips.jpg', 'nameKn' => 'ಬೈಂಡರ್ ಕ್ಲಿಪ್', 'description' => 'Assorted sizes, strong grip'],
    ['id' => 30, 'nameEn' => 'File Folder', 'category' => 'office', 'price' => 30, 'imageUrl' => 'images\\File Folder normal.jpg', 'nameKn' => 'ಫೈಲ್ ಫೋಲ್ಡರ್', 'description' => 'Manila, letter size'],
    ['id' => 31, 'nameEn' => 'Plastic File', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\File Folder lightweight project.jpg', 'nameKn' => 'ಪ್ಲಾಸ್ಟಿಕ್ ಫೈಲ್', 'description' => 'Transparent, button closure'],
    ['id' => 32, 'nameEn' => 'Expanding File', 'category' => 'office', 'price' => 120, 'imageUrl' => 'images\\Expanding File.jpg', 'nameKn' => 'ಎಕ್ಸ್ಪಾಂಡಿಂಗ್ ಫೈಲ್', 'description' => '13 pockets, accordion style'],
    ['id' => 33, 'nameEn' => 'Envelope', 'category' => 'office', 'price' => 5, 'imageUrl' => 'images\\Envelope.jpg', 'nameKn' => 'ಕವರ್', 'description' => 'White, self-adhesive'],
    ['id' => 34, 'nameEn' => 'Brown Envelope', 'category' => 'office', 'price' => 10, 'imageUrl' => 'images\\Brown Envelope.jpg', 'nameKn' => 'ಕಂದು ಕವರ್', 'description' => 'Kraft paper, secure'],
    ['id' => 35, 'nameEn' => 'Pen Stand', 'category' => 'office', 'price' => 60, 'imageUrl' => 'images\\Pen Stand.jpg', 'nameKn' => 'ಪೆನ್ ಸ್ಟ್ಯಾಂಡ್', 'description' => 'Mesh metal, non-slip base'],
    ['id' => 36, 'nameEn' => 'Pencil Box', 'category' => 'office', 'price' => 70, 'imageUrl' => 'images\\Pencil Box.jpg', 'nameKn' => 'ಪೆನ್ಸಿಲ್ ಬಾಕ್ಸ್', 'description' => 'Hard plastic, 2 compartments'],
    ['id' => 37, 'nameEn' => 'Desk Organizer', 'category' => 'office', 'price' => 150, 'imageUrl' => 'images\\Desk Organizer.jpg', 'nameKn' => 'ಡೆಸ್ಕ್ ಆರ್ಗನೈಜರ್', 'description' => 'Wood finish, 5 slots'],
    ['id' => 38, 'nameEn' => 'Calculator', 'category' => 'office', 'price' => 250, 'imageUrl' => 'images\\Calculator large.jpg', 'nameKn' => 'ಕ್ಯಾಲ್ಕುಲೇಟರ್', 'description' => 'Basic 12-digit, solar+battery'],
    ['id' => 39, 'nameEn' => 'Scientific Calculator', 'category' => 'office', 'price' => 650, 'imageUrl' => 'images\\Calculator sine.jpg', 'nameKn' => 'ವೈಜ್ಞಾನಿಕ ಕ್ಯಾಲ್ಕುಲೇಟರ್', 'description' => '240 functions, approved for exams'],
    ['id' => 40, 'nameEn' => 'Whiteboard', 'category' => 'office', 'price' => 350, 'imageUrl' => 'images\\Whiteboard.jpg', 'nameKn' => 'ವೈಟ್‌ಬೋರ್ಡ್', 'description' => 'Magnetic, 2x3 feet'],
    ['id' => 41, 'nameEn' => 'Notice Board', 'category' => 'office', 'price' => 450, 'imageUrl' => 'images\\Notice Board.jpg', 'nameKn' => 'ನೋಟಿಸ್ ಬೋರ್ಡ್', 'description' => 'Cork surface, aluminum frame'],
    ['id' => 42, 'nameEn' => 'Chalk Box', 'category' => 'art', 'price' => 20, 'imageUrl' => 'images\\Chalk Box.jpg', 'nameKn' => 'ಸೀಮೆಸುಣ್ಣದ ಬಾಕ್ಸ್', 'description' => 'White chalk, 12 pieces'],
    ['id' => 43, 'nameEn' => 'Color Chalk', 'category' => 'art', 'price' => 30, 'imageUrl' => 'images\\Chalk Box color.jpg', 'nameKn' => 'ಬಣ್ಣದ ಸೀಮೆಸುಣ್ಣ', 'description' => 'Assorted colors, dustless'],
    ['id' => 44, 'nameEn' => 'Sketch Pens', 'category' => 'art', 'price' => 80, 'imageUrl' => 'images\\Sketch Pens.jpg', 'nameKn' => 'ಸ್ಕೆಚ್ ಪೆನ್ನುಗಳು', 'description' => '12 vibrant shades, fine tip'],
    ['id' => 45, 'nameEn' => 'Color Pencils', 'category' => 'art', 'price' => 90, 'imageUrl' => 'images\\Color Pencils.jpg', 'nameKn' => 'ಬಣ್ಣದ ಪೆನ್ಸಿಲ್', 'description' => 'Set of 24, soft core'],
    ['id' => 46, 'nameEn' => 'Crayons', 'category' => 'art', 'price' => 70, 'imageUrl' => 'images\\Crayons camel.jpg', 'nameKn' => 'ಕ್ರಯೋನ್ಸ್', 'description' => 'Non-toxic, 24 colors'],
    ['id' => 47, 'nameEn' => 'Water Colors', 'category' => 'art', 'price' => 120, 'imageUrl' => 'images\\Water Colors.jpg', 'nameKn' => 'ವಾಟರ್ ಕಲರ್', 'description' => '12 pan set with brush'],
    ['id' => 48, 'nameEn' => 'Oil Pastels', 'category' => 'art', 'price' => 110, 'imageUrl' => 'images\\Oil Pastels dom.jpg', 'nameKn' => 'ಆಯಿಲ್ ಪಾಸ್ಟೆಲ್', 'description' => 'Rich pigments, blendable'],
    ['id' => 49, 'nameEn' => 'Paint Brush Set', 'category' => 'art', 'price' => 90, 'imageUrl' => 'images\\DOMS Colourmate Synthetic Hair Paint Brush Set 105.jpg', 'nameKn' => 'ಬಣ್ಣದ ಕುಂಚಗಳು', 'description' => '5 sizes, nylon bristles'],
    ['id' => 50, 'nameEn' => 'Palette', 'category' => 'art', 'price' => 40, 'imageUrl' => 'images\\Palette.jpg', 'nameKn' => 'ಪ್ಯಾಲೆಟ್', 'description' => 'Plastic, 10 wells'],
    ['id' => 51, 'nameEn' => 'Tape Roll', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Tape Roll.jpg', 'nameKn' => 'ಟೇಪ್ ರೋಲ್', 'description' => 'Transparent, 1 inch wide'],
    ['id' => 52, 'nameEn' => 'Double Tape', 'category' => 'office', 'price' => 40, 'imageUrl' => 'images\\Double Tape.jpg', 'nameKn' => 'ಡಬಲ್ ಸೈಡ್ ಟೇಪ್', 'description' => 'Strong adhesive, both sides'],
    ['id' => 53, 'nameEn' => 'Masking Tape', 'category' => 'office', 'price' => 35, 'imageUrl' => 'images\\Masking Tape.jpg', 'nameKn' => 'ಮಾಸ್ಕಿಂಗ್ ಟೇಪ್', 'description' => 'Painters tape, easy removal'],
    ['id' => 54, 'nameEn' => 'Packing Tape', 'category' => 'office', 'price' => 50, 'imageUrl' => 'images\\Packing Tape.jpg', 'nameKn' => 'ಪ್ಯಾಕಿಂಗ್ ಟೇಪ್', 'description' => 'High strength, 2 inch'],
    ['id' => 55, 'nameEn' => 'Glue Gun', 'category' => 'office', 'price' => 350, 'imageUrl' => 'images\\Glue Gun.jpg', 'nameKn' => 'ಗ್ಲೂ ಗನ್', 'description' => '20W, fast heat'],
    ['id' => 56, 'nameEn' => 'Glue Gun Sticks', 'category' => 'office', 'price' => 100, 'imageUrl' => 'images\\Glue Gun Sticks.jpg', 'nameKn' => 'ಗ್ಲೂ ಸ್ಟಿಕ್ಗಳು', 'description' => 'Pack of 20, 7mm'],
    ['id' => 57, 'nameEn' => 'Label Stickers', 'category' => 'office', 'price' => 40, 'imageUrl' => 'images\\Label Stickers.jpg', 'nameKn' => 'ಲೇಬಲ್ ಸ್ಟಿಕ್ಕರ್', 'description' => 'White, write-on surface'],
    ['id' => 58, 'nameEn' => 'Name Stickers', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Name Stickers.jpg', 'nameKn' => 'ಹೆಸರು ಸ್ಟಿಕ್ಕರ್', 'description' => 'Colorful, for notebooks'],
    ['id' => 59, 'nameEn' => 'Rubber Bands', 'category' => 'office', 'price' => 20, 'imageUrl' => 'images\\Rubber Bands.jpg', 'nameKn' => 'ರಬ್ಬರ್ ಬ್ಯಾಂಡ್', 'description' => 'Assorted sizes, 50 pcs'],
    ['id' => 60, 'nameEn' => 'Thumb Pins', 'category' => 'office', 'price' => 15, 'imageUrl' => 'images\\Thumb Pins.jpg', 'nameKn' => 'ಥಂಬ್ ಪಿನ್', 'description' => 'Plastic head, 20 pcs'],
    ['id' => 61, 'nameEn' => 'Push Pins', 'category' => 'office', 'price' => 20, 'imageUrl' => 'images\\Push Pins.jpg', 'nameKn' => 'ಪುಶ್ ಪಿನ್', 'description' => 'Steel point, 20 pcs'],
    ['id' => 62, 'nameEn' => 'Board Pins', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Board Pins.jpg', 'nameKn' => 'ಬೋರ್ಡ್ ಪಿನ್', 'description' => 'For cork boards, 30 pcs'],
    ['id' => 63, 'nameEn' => 'Clip Board', 'category' => 'office', 'price' => 90, 'imageUrl' => 'images\\Clip Board.jpg', 'nameKn' => 'ಕ್ಲಿಪ್ ಬೋರ್ಡ್', 'description' => 'Hardboard, metal clip'],
    ['id' => 64, 'nameEn' => 'File Separator', 'category' => 'office', 'price' => 30, 'imageUrl' => 'images\\File Separator.jpg', 'nameKn' => 'ಫೈಲ್ ಸೆಪರೇಟರ್', 'description' => 'Tabbed, 5 sections'],
    ['id' => 65, 'nameEn' => 'Document Wallet', 'category' => 'office', 'price' => 50, 'imageUrl' => 'images\\Document Wallet.jpg', 'nameKn' => 'ಡಾಕ್ಯುಮೆಂಟ್ ವಾಲೆಟ್', 'description' => 'Clear poly, flap closure'],
    ['id' => 66, 'nameEn' => 'Transparent Sheet', 'category' => 'office', 'price' => 20, 'imageUrl' => 'images\\Transparent Sheet.jpg', 'nameKn' => 'ಪಾರದರ್ಶಕ ಹಾಳೆ', 'description' => 'A4, 100 microns'],
    ['id' => 67, 'nameEn' => 'Laminating Sheet', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Laminating Sheet.jpg', 'nameKn' => 'ಲ್ಯಾಮಿನೇಟಿಂಗ್ ಶೀಟ್', 'description' => 'Pouch, 3 mil'],
    ['id' => 68, 'nameEn' => 'Laminating Machine', 'category' => 'office', 'price' => 1800, 'imageUrl' => 'images\\Laminating Machine.jpg', 'nameKn' => 'ಲ್ಯಾಮಿನೇಟಿಂಗ್ ಮೆಷಿನ್', 'description' => 'A4 size, warm-up indicator'],
    ['id' => 69, 'nameEn' => 'Ink Bottle', 'category' => 'office', 'price' => 50, 'imageUrl' => 'images\\Ink Bottle camlin.jpg', 'nameKn' => 'ಶಾಯಿ ಬಾಟಲಿ', 'description' => 'Blue, 60ml'],
    ['id' => 70, 'nameEn' => 'Printer Ink', 'category' => 'office', 'price' => 450, 'imageUrl' => 'images\\Printer Ink bro.jpg', 'nameKn' => 'ಪ್ರಿಂಟರ್ ಇಂಕ್', 'description' => 'Black, compatible with HP'],
    ['id' => 71, 'nameEn' => 'Correction Pen', 'category' => 'pens', 'price' => 35, 'imageUrl' => 'images\\Correction Pen.jpg', 'nameKn' => 'ಕರೆಕ್ಷನ್ ಪೆನ್', 'description' => 'Quick drying, 8ml'],
    ['id' => 72, 'nameEn' => 'Correction Tape', 'category' => 'office', 'price' => 40, 'imageUrl' => 'images\\Correction Tape.jpg', 'nameKn' => 'ಕರೆಕ್ಷನ್ ಟೇಪ್', 'description' => 'No drying time, refillable'],
    ['id' => 73, 'nameEn' => 'Paper Punch', 'category' => 'office', 'price' => 120, 'imageUrl' => 'images\\Paper Punch.jpg', 'nameKn' => 'ಪೇಪರ್ ಪಂಚ್', 'description' => '2-hole, adjustable'],
    ['id' => 74, 'nameEn' => 'Staple Remover', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Staple Remover.jpg', 'nameKn' => 'ಸ್ಟೇಪಲ್ ರಿಮೂವರ್', 'description' => 'Sharp claw, comfortable grip'],
    ['id' => 75, 'nameEn' => 'Cutter Blades', 'category' => 'office', 'price' => 20, 'imageUrl' => 'images\\Cutter Blades.jpg', 'nameKn' => 'ಕಟ್ಟರ್ ಬ್ಲೇಡ್', 'description' => '10 pack, snap-off type'],
    ['id' => 76, 'nameEn' => 'Metal Ruler', 'category' => 'office', 'price' => 35, 'imageUrl' => 'images\\Metal Ruler.jpg', 'nameKn' => 'ಲೋಹದ ಸ್ಕೇಲ್', 'description' => 'Stainless, 30cm'],
    ['id' => 77, 'nameEn' => 'Drafting Pencil', 'category' => 'pens', 'price' => 60, 'imageUrl' => 'images\\Drafting Pencil.jpg', 'nameKn' => 'ಡ್ರಾಫ್ಟಿಂಗ್ ಪೆನ್ಸಿಲ್', 'description' => '0.5mm, lead hardness indicator'],
    ['id' => 78, 'nameEn' => 'Graph Notebook', 'category' => 'notebooks', 'price' => 70, 'imageUrl' => 'images\\Graph Notebook.jpg', 'nameKn' => 'ಗ್ರಾಫ್ ನೋಟ್ಬುಕ್', 'description' => '1cm grid, 80 pages'],
    ['id' => 79, 'nameEn' => 'Attendance Register', 'category' => 'notebooks', 'price' => 120, 'imageUrl' => 'images\\Attendance Register.jpg', 'nameKn' => 'ಹಾಜರಿ ನೋಂದಣಿ', 'description' => 'Hardbound, 200 pages'],
    ['id' => 80, 'nameEn' => 'Record Book', 'category' => 'notebooks', 'price' => 90, 'imageUrl' => 'images\\Record Book.jpg', 'nameKn' => 'ರೆಕಾರ್ಡ್ ಬುಕ್', 'description' => 'Ruled, numbered pages'],
    ['id' => 81, 'nameEn' => 'Ledger Book', 'category' => 'notebooks', 'price' => 250, 'imageUrl' => 'images\\Ledger Book.jpg', 'nameKn' => 'ಲೆಡ್ಜರ್ ಬುಕ್', 'description' => 'Accounts format, 300 pages'],
    ['id' => 82, 'nameEn' => 'Cash Book', 'category' => 'notebooks', 'price' => 200, 'imageUrl' => 'images\\Cash Book.jpg', 'nameKn' => 'ಕ್ಯಾಷ್ ಬುಕ್', 'description' => 'Simple cash ledger'],
    ['id' => 83, 'nameEn' => 'Diary', 'category' => 'notebooks', 'price' => 150, 'imageUrl' => 'images\\Diary.jpg', 'nameKn' => 'ಡೈರಿ', 'description' => 'Leather bound, daily planner'],
    ['id' => 84, 'nameEn' => 'Planner', 'category' => 'notebooks', 'price' => 200, 'imageUrl' => 'images\\Planner.jpg', 'nameKn' => 'ಪ್ಲಾನರ್', 'description' => 'Weekly/monthly, goal tracker'],
    ['id' => 85, 'nameEn' => 'Calendar', 'category' => 'office', 'price' => 120, 'imageUrl' => 'images\\Calendar.jpg', 'nameKn' => 'ಕ್ಯಾಲೆಂಡರ್', 'description' => 'Wall hanging, 12 months'],
    ['id' => 86, 'nameEn' => 'Bookmark', 'category' => 'office', 'price' => 15, 'imageUrl' => 'images\\Bookmark.jpg', 'nameKn' => 'ಬುಕ್‌ಮಾರ್ಕ್', 'description' => 'Magnetic, colorful set'],
    ['id' => 87, 'nameEn' => 'Photo Paper', 'category' => 'office', 'price' => 300, 'imageUrl' => 'images\\Photo Paper.jpg', 'nameKn' => 'ಫೋಟೋ ಪೇಪರ್', 'description' => 'Glossy, A4, 20 sheets'],
    ['id' => 88, 'nameEn' => 'Index Cards', 'category' => 'office', 'price' => 40, 'imageUrl' => 'images\\Index Cards.jpg', 'nameKn' => 'ಇಂಡೆಕ್ಸ್ ಕಾರ್ಡ್', 'description' => '3x5 inch, ruled'],
    ['id' => 89, 'nameEn' => 'Carbon Paper', 'category' => 'office', 'price' => 30, 'imageUrl' => 'images\\Carbon Paper.jpg', 'nameKn' => 'ಕಾರ್ಬನ್ ಪೇಪರ್', 'description' => 'Black, 10 sheets'],
    ['id' => 90, 'nameEn' => 'Tracing Paper', 'category' => 'office', 'price' => 40, 'imageUrl' => 'images\\Tracing Paper.jpg', 'nameKn' => 'ಟ್ರೇಸಿಂಗ್ ಪೇಪರ್', 'description' => 'Translucent, A4, 20 sheets'],
    ['id' => 91, 'nameEn' => 'Graph Paper', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Graph Paper.jpg', 'nameKn' => 'ಗ್ರಾಫ್ ಪೇಪರ್', 'description' => '1mm grid, 50 sheets'],
    ['id' => 92, 'nameEn' => 'Sticky Notes', 'category' => 'office', 'price' => 30, 'imageUrl' => 'images\\Sticky Notes.jpg', 'nameKn' => 'ಸ್ಟಿಕಿ ನೋಟ್ಸ್', 'description' => '3x3 inch, 100 sheets'],
    ['id' => 93, 'nameEn' => 'Index Tabs', 'category' => 'office', 'price' => 20, 'imageUrl' => 'images\\Index Tabs.jpg', 'nameKn' => 'ಇಂಡೆಕ್ಸ್ ಟ್ಯಾಬ್', 'description' => 'Self-adhesive, 25 pack'],
    ['id' => 94, 'nameEn' => 'Paper Weight', 'category' => 'office', 'price' => 80, 'imageUrl' => 'images\\Paper Weight.jpg', 'nameKn' => 'ಪೇಪರ್ ವೇಟ್', 'description' => 'Glass, decorative'],
    ['id' => 95, 'nameEn' => 'Book Stand', 'category' => 'office', 'price' => 150, 'imageUrl' => 'images\\Book Stand.jpg', 'nameKn' => 'ಪುಸ್ತಕ ಸ್ಟ್ಯಾಂಡ್', 'description' => 'Adjustable, metal'],
    ['id' => 96, 'nameEn' => 'Magazine Holder', 'category' => 'office', 'price' => 200, 'imageUrl' => 'images\\Magazine Holder.jpg', 'nameKn' => 'ಮ್ಯಾಗಜೈನ್ ಹೋಲ್ಡರ್', 'description' => 'Wire mesh, black'],
    ['id' => 97, 'nameEn' => 'ID Card Holder', 'category' => 'office', 'price' => 40, 'imageUrl' => 'images\\ID Card Holder.jpg', 'nameKn' => 'ಐಡಿ ಕಾರ್ಡ್ ಹೋಲ್ಡರ್', 'description' => 'Vertical, with clip'],
    ['id' => 98, 'nameEn' => 'Lanyard', 'category' => 'office', 'price' => 30, 'imageUrl' => 'images\\Lanyard.jpg', 'nameKn' => 'ಲ್ಯಾನ್ಯಾರ್ಡ್', 'description' => 'Black, safety breakaway'],
    ['id' => 99, 'nameEn' => 'School Bag', 'category' => 'office', 'price' => 800, 'imageUrl' => 'images\\School Bag.jpg', 'nameKn' => 'ಸ್ಕೂಲ್ ಬ್ಯಾಗ್', 'description' => 'Waterproof, 2 compartments'],
    ['id' => 100, 'nameEn' => 'Lunch Box', 'category' => 'office', 'price' => 250, 'imageUrl' => 'images\\Lunch Box.jpg', 'nameKn' => 'ಲಂಚ್ ಬಾಕ್ಸ್', 'description' => 'Leakproof, microwave safe'],
    ['id' => 101, 'nameEn' => 'Water Bottle', 'category' => 'office', 'price' => 150, 'imageUrl' => 'images\\Water Bottle.jpg', 'nameKn' => 'ನೀರಿನ ಬಾಟಲಿ', 'description' => 'BPA free, 1 liter'],
    ['id' => 102, 'nameEn' => 'Exam Pad', 'category' => 'notebooks', 'price' => 120, 'imageUrl' => 'images\\Exam Pad.jpg', 'nameKn' => 'ಪರೀಕ್ಷಾ ಪ್ಯಾಡ್', 'description' => 'Answer sheets, 100 pages'],
    ['id' => 103, 'nameEn' => 'Name Badge', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Name Badge.jpg', 'nameKn' => 'ಹೆಸರಿನ ಬ್ಯಾಡ್ಜ್', 'description' => 'Plastic, pin attachment'],
    ['id' => 104, 'nameEn' => 'School Label Pack', 'category' => 'office', 'price' => 30, 'imageUrl' => 'images\\School Label Pack.jpg', 'nameKn' => 'ಸ್ಕೂಲ್ ಲೇಬಲ್ ಪ್ಯಾಕ್', 'description' => 'For books, 24 labels'],
    ['id' => 105, 'nameEn' => 'Kids Craft Paper', 'category' => 'art', 'price' => 60, 'imageUrl' => 'images\\Kids Craft Paper.jpg', 'nameKn' => 'ಮಕ್ಕಳ ಕ್ರಾಫ್ಟ್ ಪೇಪರ್', 'description' => 'Assorted colors, 50 sheets'],
    ['id' => 106, 'nameEn' => 'Foam Sheets', 'category' => 'art', 'price' => 80, 'imageUrl' => 'images\\Foam Sheets.jpg', 'nameKn' => 'ಫೋಮ್ ಶೀಟ್ಸ್', 'description' => 'A4, 12 colors'],
    ['id' => 107, 'nameEn' => 'Glitter Pens', 'category' => 'pens', 'price' => 120, 'imageUrl' => 'images\\Glitter Pens.jpg', 'nameKn' => 'ಗ್ಲಿಟರ್ ಪೆನ್ನುಗಳು', 'description' => 'Shimmer ink, 6 pack'],
    ['id' => 108, 'nameEn' => 'Calligraphy Pen', 'category' => 'pens', 'price' => 180, 'imageUrl' => 'images\\Calligraphy Pen.jpg', 'nameKn' => 'ಕ್ಯಾಲಿಗ್ರಫಿ ಪೆನ್', 'description' => 'Nib set, includes ink'],
    ['id' => 109, 'nameEn' => 'Brush Pens', 'category' => 'pens', 'price' => 220, 'imageUrl' => 'images\\Brush Pens.jpg', 'nameKn' => 'ಬ್ರಷ್ ಪೆನ್ನುಗಳು', 'description' => 'Flexible tip, 10 colors'],
    ['id' => 110, 'nameEn' => 'Mini Stapler', 'category' => 'office', 'price' => 60, 'imageUrl' => 'images\\Mini Stapler.jpg', 'nameKn' => 'ಮಿನಿ ಸ್ಟೇಪ್ಲರ್', 'description' => 'Compact, fits 10 sheets'],
    ['id' => 111, 'nameEn' => 'Mini Scissors', 'category' => 'office', 'price' => 35, 'imageUrl' => 'images\\Mini Scissors.jpg', 'nameKn' => 'ಮಿನಿ ಕತ್ತರಿ', 'description' => '4 inch, precision tip'],
    ['id' => 112, 'nameEn' => 'Whiteboard Cleaner', 'category' => 'office', 'price' => 90, 'imageUrl' => 'images\\Whiteboard Cleaner.jpg', 'nameKn' => 'ವೈಟ್‌ಬೋರ್ಡ್ ಕ್ಲೀನರ್', 'description' => 'Spray bottle, 200ml'],
    ['id' => 113, 'nameEn' => 'Office Folder Set', 'category' => 'office', 'price' => 180, 'imageUrl' => 'images\\Office Folder Set.jpg', 'nameKn' => 'ಆಫೀಸ್ ಫೋಲ್ಡರ್ ಸೆಟ್', 'description' => '5 folders, assorted colors'],
    ['id' => 114, 'nameEn' => 'Writing Pad', 'category' => 'notebooks', 'price' => 70, 'imageUrl' => 'images\\Writing Pad.jpg', 'nameKn' => 'ರೈಟಿಂಗ್ ಪ್ಯಾಡ್', 'description' => 'Perforated, 50 sheets'],
    ['id' => 115, 'nameEn' => 'Student Kit', 'category' => 'office', 'price' => 250, 'imageUrl' => 'images\\Student Kit.jpg', 'nameKn' => 'ವಿದ್ಯಾರ್ಥಿ ಕಿಟ್', 'description' => 'Pens, pencils, eraser, sharpener'],
    ['id' => 116, 'nameEn' => 'Office Kit', 'category' => 'office', 'price' => 350, 'imageUrl' => 'images\\Office Kit.jpg', 'nameKn' => 'ಆಫೀಸ್ ಕಿಟ್', 'description' => 'Stapler, tape, scissors, clips'],
    ['id' => 117, 'nameEn' => 'Craft Glue', 'category' => 'office', 'price' => 40, 'imageUrl' => 'images\\Craft Glue.jpg', 'nameKn' => 'ಕ್ರಾಫ್ಟ್ ಅಂಟು', 'description' => 'Clear, dries quickly'],
    ['id' => 118, 'nameEn' => 'Stamp Ink', 'category' => 'office', 'price' => 60, 'imageUrl' => 'images\\Stamp Ink.jpg', 'nameKn' => 'ಸ್ಟಾಂಪ್ ಇಂಕ್', 'description' => 'Blue, 30ml bottle'],
    ['id' => 119, 'nameEn' => 'Desk Calendar', 'category' => 'office', 'price' => 250, 'imageUrl' => 'images\\INNAXA 2026 Desk Calendar 250.jpg', 'nameKn' => 'ಡೆಸ್ಕ್ ಕ್ಯಾಲೆಂಡರ್', 'description' => 'Tear-off, 2025 edition'],
    ['id' => 120, 'nameEn' => 'Sticky Flags', 'category' => 'office', 'price' => 45, 'imageUrl' => 'images\\Sticky Flags.jpg', 'nameKn' => 'ಸ್ಟಿಕಿ ಫ್ಲ್ಯಾಗ್ಸ್', 'description' => 'Assorted colors, 50 flags'],
    ['id' => 121, 'nameEn' => 'Ball Pen', 'category' => 'pens', 'price' => 10, 'imageUrl' => 'images\\Ball Pen2.jpg', 'nameKn' => 'ಬಾಲ್ ಪೆನ್', 'description' => 'Smooth writing, long-lasting ink'],
    ['id' => 122, 'nameEn' => 'Label Stickers', 'category' => 'office', 'price' => 40, 'imageUrl' => 'images\\Label Stickers.jpg', 'nameKn' => 'ಲೇಬಲ್ ಸ್ಟಿಕ್ಕರ್', 'description' => 'White, write-on surface'],
    ['id' => 123, 'nameEn' => 'Name Stickers', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Name Stickers.jpg', 'nameKn' => 'ಹೆಸರು ಸ್ಟಿಕ್ಕರ್', 'description' => 'Colorful, for notebooks'],
    ['id' => 124, 'nameEn' => 'Rubber Bands', 'category' => 'office', 'price' => 20, 'imageUrl' => 'images\\Rubber Bands.jpg', 'nameKn' => 'ರಬ್ಬರ್ ಬ್ಯಾಂಡ್', 'description' => 'Assorted sizes, 50 pcs'],
    ['id' => 125, 'nameEn' => 'Thumb Pins', 'category' => 'office', 'price' => 15, 'imageUrl' => 'images\\Thumb Pins.jpg', 'nameKn' => 'ಥಂಬ್ ಪಿನ್', 'description' => 'Plastic head, 20 pcs'],
    ['id' => 126, 'nameEn' => 'Push Pins', 'category' => 'office', 'price' => 20, 'imageUrl' => 'images\\Push Pins.jpg', 'nameKn' => 'ಪುಶ್ ಪಿನ್', 'description' => 'Steel point, 20 pcs'],
    ['id' => 127, 'nameEn' => 'Board Pins', 'category' => 'office', 'price' => 25, 'imageUrl' => 'images\\Board Pins.jpg', 'nameKn' => 'ಬೋರ್ಡ್ ಪಿನ್', 'description' => 'For cork boards, 30 pcs'],
    ['id' => 128, 'nameEn' => 'Clip Board', 'category' => 'office', 'price' => 90, 'imageUrl' => 'images\\Clip Board.jpg', 'nameKn' => 'ಕ್ಲಿಪ್ ಬೋರ್ಡ್', 'description' => 'Hardboard, metal clip'],
    ['id' => 129, 'nameEn' => 'Sticky Notes', 'category' => 'office', 'price' => 30, 'imageUrl' => 'images\\Sticky Notes.jpg', 'nameKn' => 'ಸ್ಟಿಕಿ ನೋಟ್ಸ್', 'description' => '3x3 inch, 100 sheets'],
    ['id' => 130, 'nameEn' => 'Calligraphy Pen', 'category' => 'pens', 'price' => 180, 'imageUrl' => 'images\\Calligraphy Pen.jpg', 'nameKn' => 'ಕ್ಯಾಲಿಗ್ರಫಿ ಪೆನ್', 'description' => 'Nib set, includes ink'],
    ['id' => 131, 'nameEn' => 'Correction Pen whitnet', 'category' => 'pens', 'price' => 180, 'imageUrl' => 'images\\Correction Pen whitnet.jpg', 'nameKn' => 'ಕ್ಯಾಲಿಗ್ರಫಿ ಪೆನ್', 'description' => 'Nib set, includes ink'],
    ['id' => 132, 'nameEn' => 'Glitter Pens', 'category' => 'pens', 'price' => 180, 'imageUrl' => 'images\\Glitter Pens.jpg', 'nameKn' => 'ಕ್ಯಾಲಿಗ್ರಫಿ ಪೆನ್', 'description' => 'Nib set, includes ink'],
    ['id' => 133, 'nameEn' => 'Reynolds DFINE Ball Pen', 'category' => 'pens', 'price' => 350, 'imageUrl' => 'images\\Reynolds DFINE Ball Pen Set 350.jpg', 'nameKn' => 'ಕ್ಯಾಲಿಗ್ರಫಿ ಪೆನ್', 'description' => 'Nib set, includes ink'],
    ['id' => 134, 'nameEn' => 'Board Duster', 'category' => 'Duster', 'price' => 250, 'imageUrl' => 'images\\Board Duster.jpg', 'nameKn' => '', 'description' => 'Nib set, includes ink'],
    ['id' => 135, 'nameEn' => 'Board Duster', 'category' => 'Duster', 'price' => 280, 'imageUrl' => 'images\\Board Duster2.jpg', 'nameKn' => '', 'description' => 'Nib set, includes ink'],
    ['id' => 136, 'nameEn' => 'Paint Brush Set', 'category' => 'art', 'price' => 280, 'imageUrl' => 'images\\Camel Champ Brush Set of 7 190.jpg', 'nameKn' => 'ಬಣ್ಣದ ಕುಂಚಗಳು', 'description' => '5 sizes, nylon bristles']
  ];
  // Build productsData from static
  foreach ($staticProducts as $item) {
    $productsData[] = [
      'id' => $item['id'],
      'name' => $item['nameEn'],
      'kn' => $item['nameKn'] ?? '',
      'category' => $item['category'],
      'price' => $item['price'],
      'imageUrl' => $item['imageUrl'],
      'description' => $item['description'],
      'weight' => ''
    ];
  }
}
// Define top pick IDs (from static)
$topPickIds = [1, 5, 13, 24, 18, 15, 9, 28, 38, 44];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>Stationery · Premium Supplies · ಸ್ಟೇಷನರಿ</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
      background: #f9f6f2;
      padding: 24px 20px;
      min-height: 100vh;
    }

    #app {
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
      background: white;
      border-radius: 40px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
      padding: 28px 32px 40px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
      flex-wrap: wrap;
      gap: 16px;
    }

    .shop-name h1 {
      font-size: 2.2rem;
      font-weight: 700;
      color: #3e2c1b;
      line-height: 1.2;
    }

    .shop-name .kannada {
      font-size: 1.1rem;
      color: #b45f2b;
      font-weight: 500;
    }

    .cart-link {
      background: #f7ede3;
      padding: 12px 28px;
      border-radius: 60px;
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      font-weight: 600;
      font-size: 1rem;
      color: #5c3e24;
      transition: 0.2s;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .cart-link i {
      font-size: 1.4rem;
      color: #b45f2b;
    }

    .cart-link:hover {
      background: #e5d5c4;
      transform: translateY(-2px);
    }

    .cart-link span {
      background: #b45f2b;
      color: white;
      font-size: 0.9rem;
      font-weight: 700;
      padding: 2px 12px;
      border-radius: 40px;
      margin-left: 4px;
    }

    .secondary-nav {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 28px;
    }

    .secondary-nav a {
      background: #f3eee9;
      padding: 10px 20px;
      border-radius: 50px;
      font-size: 0.9rem;
      font-weight: 500;
      color: #5c3e24;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: background 0.2s;
    }

    .secondary-nav a:hover {
      background: #e5d5c4;
    }

    .search-section {
      margin-bottom: 32px;
    }

    .search-box {
      display: flex;
      align-items: center;
      background: #f3eee9;
      border-radius: 60px;
      padding: 8px 24px;
      margin-bottom: 20px;
      border: 1px solid #e6d9cd;
      max-width: 500px;
    }

    .search-box i {
      color: #b45f2b;
      font-size: 1.2rem;
      margin-right: 14px;
    }

    .search-box input {
      flex: 1;
      border: none;
      background: transparent;
      padding: 14px 0;
      font-size: 1rem;
      outline: none;
      color: #2f2216;
    }

    .filter-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .pill {
      background: #f3eee9;
      padding: 10px 24px;
      border-radius: 60px;
      font-size: 1rem;
      font-weight: 500;
      color: #4f3e2e;
      cursor: pointer;
      transition: all 0.2s;
    }

    .pill.active {
      background: #5c3e24;
      color: white;
    }

    .pill:hover {
      background: #e0cfbf;
    }

    .top-picks-section {
      margin: 40px 0 24px;
    }

    .section-title {
      font-size: 1.7rem;
      font-weight: 700;
      color: #3e2c1b;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title i {
      color: #b45f2b;
      font-size: 1.8rem;
    }

    .top-picks-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 22px;
      margin-bottom: 30px;
    }

    .top-pick-card {
      background: #fefaf6;
      border-radius: 28px;
      padding: 16px 12px;
      border: 1px solid #f2e5d9;
      text-align: center;
      display: flex;
      flex-direction: column;
      height: 100%;
      transition: 0.2s;
    }

    .top-pick-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
    }

    .top-pick-img {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 20px;
      background: #fcf4ec;
      margin: 0 auto 12px;
      display: block;
    }

    .top-pick-name {
      font-weight: 700;
      font-size: 0.95rem;
      color: #2f2216;
    }

    .top-pick-price {
      font-weight: 600;
      color: #b45f2b;
      margin: 6px 0 12px;
      font-size: 0.85rem;
    }

    .top-pick-btn {
      background: #b45f2b;
      border: none;
      color: white;
      padding: 8px 12px;
      border-radius: 40px;
      font-weight: 600;
      font-size: 0.8rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      justify-content: center;
      margin-top: auto;
      width: 100%;
      transition: 0.1s;
    }

    .top-pick-btn:active {
      transform: scale(0.96);
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 28px;
      margin-top: 20px;
      margin-bottom: 40px;
    }

    .product-card {
      background: white;
      border-radius: 32px;
      padding: 18px 16px 20px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
      border: 1px solid #f2e5d9;
      display: flex;
      flex-direction: column;
      height: 100%;
      transition: 0.2s;
    }

    .product-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 30px rgba(0, 0, 0, 0.08);
    }

    .product-image {
      width: 90px;
      height: 90px;
      margin: 0 auto 16px;
      border-radius: 28px;
      overflow: hidden;
      background: #fcf4ec;
      flex-shrink: 0;
    }

    .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .product-name {
      font-weight: 700;
      font-size: 1rem;
      color: #2f2216;
      text-align: center;
    }

    .product-name-kn {
      font-size: 0.7rem;
      color: #7b5f45;
      text-align: center;
      margin-bottom: 8px;
    }

    .product-desc {
      font-size: 0.7rem;
      color: #8a6e54;
      margin: 6px 0 12px;
      line-height: 1.4;
      text-align: center;
    }

    .weight-select {
      width: 100%;
      padding: 8px 12px;
      border-radius: 40px;
      border: 1px solid #e6d9cd;
      background: #fefaf6;
      font-size: 0.8rem;
      margin: 8px 0;
      cursor: pointer;
      font-weight: 500;
      color: #2f2216;
      text-align: center;
    }

    .dynamic-price {
      font-weight: 800;
      font-size: 1.2rem;
      color: #b45f2b;
      text-align: center;
      margin: 8px 0;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: auto;
      gap: 12px;
      padding-top: 12px;
      border-top: 1px solid #f0e3d7;
    }

    .price small {
      font-size: 0.7rem;
      font-weight: 500;
      color: #a07c5e;
    }

    .add-btn {
      background: #b45f2b;
      border: none;
      color: white;
      width: 44px;
      height: 44px;
      border-radius: 60px;
      font-size: 1.2rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: 0.1s;
    }

    .add-btn:active {
      transform: scale(0.95);
    }

    footer {
      text-align: center;
      font-size: 0.85rem;
      color: #b9a690;
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid #f0e3d7;
    }

    @media (max-width: 680px) {
      #app {
        padding: 20px;
      }

      .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 18px;
      }

      .cart-link {
        padding: 8px 20px;
      }

      .top-picks-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      }

      .product-image {
        width: 75px;
        height: 75px;
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
      <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off"></div>
      <div class="filter-pills" id="filterPills">
        <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
        <div class="pill" data-category="pens">Pens / ಪೆನ್ನುಗಳು</div>
        <div class="pill" data-category="notebooks">Notebooks / ನೋಟ್‌ಬುಕ್‌ಗಳು</div>
        <div class="pill" data-category="art">Art / ಕಲೆ</div>
        <div class="pill" data-category="office">Office / ಕಚೇರಿ</div>
      </div>
    </div>
    <div class="top-picks-section">
      <div class="section-title"><i class="fas fa-star"></i> Top Picks · ಪ್ರಮುಖ ಆಯ್ಕೆಗಳು</div>
      <div class="top-picks-grid" id="topPicksGrid"></div>
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
      // ----- QUANTITY OPTIONS -----
      const quantityOptions = [1, 2, 5, 10, 15, 20, 30, 40, 50];

      function formatQtyLabel(qty) {
        return `${qty} pc${qty > 1 ? 's' : ''}`;
      }
      // ----- TOP PICK IDs -----
      const topPickIds = <?php echo json_encode($topPickIds); ?>;
      // ----- CART STATE (localStorage as fallback, but we'll use session via AJAX) -----
      let cart = JSON.parse(localStorage.getItem('stationeryCart')) || [];
      // We'll try to fetch from session via AJAX, but for simplicity, use localStorage for now.
      // However, we want to sync with server. We'll use the same approach as other pages:
      // use localStorage for quick display, but also call server endpoints.
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
        localStorage.setItem('stationeryCart', JSON.stringify(cart));
      }
      // ----- ADD TO CART (local + server sync) -----
      function addToCartAndRedirect(productId, selectedQty) {
        const product = products.find(p => p.id === productId);
        if (!product) return;
        const totalPrice = product.price * selectedQty;
        const formData = new FormData();
        formData.append('id', productId);
        formData.append('qty', 1);
        formData.append('variant', selectedQty + ' piece(s)');
        formData.append('custom_price', totalPrice.toFixed(2));
        formData.append('name', product.name || '');
        formData.append('kn', product.kn || '');
        formData.append('image', product.imageUrl || '');
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
      // ----- RENDER TOP PICKS -----
      function renderTopPicks() {
        const topProducts = products.filter(p => topPickIds.includes(p.id));
        const topGrid = document.getElementById('topPicksGrid');
        if (!topGrid) return;
        if (topProducts.length === 0) {
          topGrid.innerHTML = '';
          return;
        }
        const defaultQty = 2;
        topGrid.innerHTML = topProducts.map(p => {
          const totalPrice = (p.price * defaultQty).toFixed(2);
          return `<div class="top-pick-card">
                <img src="${p.imageUrl}" class="top-pick-img" alt="" onerror="this.src='https://placehold.co/200x200?text=Stationery'">
                <div class="top-pick-name">${p.name}</div>
                <div class="top-pick-price">₹${totalPrice} (${formatQtyLabel(defaultQty)})</div>
                <button class="top-pick-btn" data-id="${p.id}" data-qty="${defaultQty}"><i class="fas fa-cart-plus"></i> Add</button>
            </div>`;
        }).join('');
        document.querySelectorAll('.top-pick-btn').forEach(btn => {
          btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const pid = parseInt(btn.dataset.id);
            const qty = parseInt(btn.dataset.qty);
            addToCartAndRedirect(pid, qty);
          });
        });
      }
      // ----- RENDER PRODUCTS -----
      function renderProducts() {
        let filtered = products.filter(p => {
          if (activeCategory !== 'all' && p.category !== activeCategory) return false;
          const term = searchTerm.trim().toLowerCase();
          if (term) {
            return p.name.toLowerCase().includes(term) || (p.kn && p.kn.toLowerCase().includes(term));
          }
          return true;
        });
        if (!filtered.length) {
          productGrid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No stationery found ✨</div>`;
          return;
        }
        productGrid.innerHTML = filtered.map(p => {
          const defaultQty = 1;
          const defaultPrice = (p.price * defaultQty).toFixed(2);
          return `<div class="product-card" data-id="${p.id}">
                <div class="product-image"><img src="${p.imageUrl}" alt="" loading="lazy" onerror="this.src='https://placehold.co/400x400?text=Stationery'"></div>
                <div class="product-name">${p.name}</div>
                <div class="product-name-kn">${p.kn || 'ಸ್ಟೇಷನರಿ'}</div>
                <div class="product-desc">${p.description}</div>
                <select class="weight-select" data-id="${p.id}">
                    ${quantityOptions.map(qty => `<option value="${qty}" ${qty === defaultQty ? 'selected' : ''}>${formatQtyLabel(qty)}</option>`).join('')}
                </select>
                <div class="dynamic-price" id="price-${p.id}">₹${defaultPrice}</div>
                <div class="price-row">
                    <div class="price"><small>₹${p.price} / piece</small></div>
                    <button class="add-btn" data-id="${p.id}" data-add><i class="fa-solid fa-cart-plus"></i></button>
                </div>
            </div>`;
        }).join('');
        // dynamic price update
        document.querySelectorAll('.weight-select').forEach(select => {
          const pid = parseInt(select.dataset.id);
          const product = products.find(p => p.id === pid);
          const updatePrice = () => {
            const qty = parseInt(select.value);
            const total = product.price * qty;
            const span = document.getElementById(`price-${pid}`);
            if (span) span.innerText = `₹${total.toFixed(2)}`;
          };
          select.addEventListener('change', updatePrice);
          updatePrice();
        });
        // add to cart buttons
        document.querySelectorAll('.add-btn').forEach(btn => {
          btn.addEventListener('click', (e) => {
            const pid = parseInt(btn.dataset.id);
            const card = btn.closest('.product-card');
            const qtySelect = card.querySelector('.weight-select');
            const selectedQty = parseInt(qtySelect.value);
            addToCartAndRedirect(pid, selectedQty);
            btn.style.transform = 'scale(0.9)';
            setTimeout(() => btn.style.transform = '', 120);
          });
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
      // Build products array from data (ensure all fields)
      const products = productsData.map(p => ({
        id: p.id,
        name: p.name,
        kn: p.kn || '',
        category: p.category || 'office',
        price: parseFloat(p.price),
        imageUrl: p.imageUrl || '',
        description: p.description || 'Quality stationery'
      }));
      // Load cart from localStorage
      const storedCart = localStorage.getItem('stationeryCart');
      if (storedCart) {
        try {
          cart = JSON.parse(storedCart);
        } catch (e) {
          cart = [];
        }
        updateCartBadge();
      }
      renderTopPicks();
      renderProducts();
      updateCartBadge();
      // Listen for storage changes from other tabs
      window.addEventListener('storage', (e) => {
        if (e.key === 'stationeryCart') {
          const newCart = JSON.parse(e.newValue);
          if (newCart) {
            cart = newCart;
            updateCartBadge();
          }
        }
      });
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