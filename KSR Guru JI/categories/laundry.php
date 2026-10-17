<?php
// ============================================================
// laundry.php - Dynamic Laundry product page (with images)
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
// ============================================================
// Helper: normalize image path (Windows absolute -> web relative)
// ============================================================
if (!function_exists('normalizeImagePath')) {
function normalizeImagePath(?string $path): string
{
  if (empty($path)) return '';
  // Normalize backslashes to forward slashes
  $path = str_replace('\\', '/', $path);
  // Extract everything after "images/"
  if (preg_match('#.*?(images/.*)$#i', $path, $matches)) {
    // Root-relative path with leading slash
    return '/' . $matches[1];
  }
  // Fallback: prepend /images/ if not present
  return '/images/' . ltrim($path, '/');
}
}
// ============================================================
// FETCH PRODUCTS FROM DATABASE (fallback to static)
// ============================================================
function getLaundryProducts()
{
  try {
    $pdo = getDBConnection();
    // Check columns
    $schema = getProductSchemaInfo();
    $descCol = $schema["desc_col"];
    $imgCol = $schema["img_col"];
    $schema = getProductSchemaInfo();
    $hasSlug = $schema["has_cat_slug"];
    $whereCategory = $hasSlug ? "(c.slug = 'laundry' OR c.name LIKE '%laundry%')" : "(c.id = 'laundry' OR c.name LIKE '%laundry%')";
    $sql = "SELECT p.id, p.name, p.kn_name, p.price, p.category_id, p.icon, p.emoji, p.is_featured,
                       c.name AS category_name, c.kn_name AS category_kn,
                       {$descCol} AS description_en,
                       {$imgCol} AS image
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE {$whereCategory}
                ORDER BY p.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($rows as $row) {
      $subcategory = 'detergent';
      $nameLower = strtolower($row['name']);
      if (strpos($nameLower, 'softener') !== false || strpos($nameLower, 'conditioner') !== false || strpos($nameLower, 'fragrance') !== false || strpos($nameLower, 'scent') !== false) {
        $subcategory = 'softener';
      } elseif (strpos($nameLower, 'stain') !== false || strpos($nameLower, 'remover') !== false) {
        $subcategory = 'stain';
      } elseif (strpos($nameLower, 'bleach') !== false || strpos($nameLower, 'whitener') !== false) {
        $subcategory = 'bleach';
      } elseif (strpos($nameLower, 'brush') !== false || strpos($nameLower, 'bag') !== false || strpos($nameLower, 'peg') !== false || strpos($nameLower, 'basket') !== false || strpos($nameLower, 'glove') !== false || strpos($nameLower, 'sheet') !== false || strpos($nameLower, 'starch') !== false) {
        $subcategory = 'accessories';
      }
      $imageUrl = !empty($row['image']) ? normalizeImagePath($row['image']) : '';
      $result[] = [
        'id' => $row['id'],
        'nameEn' => $row['name'],
        'category' => $subcategory,
        'price' => $row['price'],
        'weight' => $row['weight'] ?? '1 unit',
        'imageurl' => $imageUrl,
        'nameKn' => $row['kn_name'] ?? $row['name'],
        'description' => $row['description_en'] ?? 'Quality laundry product',
        'emoji' => $row['emoji'] ?? '🧺'
      ];
    }
    return $result;
  } catch (Throwable $e) {
    return [];
  }
}
// ============================================================
// FALLBACK STATIC DATA (complete list from original HTML)
// ============================================================
function getFallbackLaundryProducts()
{
  static $fallback = null;
  if ($fallback !== null) return $fallback;
  $staticJson = '[
  {"id":1,"nameEn":"Detergent Powder Regular","category":"detergent","price":120,"weight":"1 kg","imageurl":"images\\\\Detergent Powder Regular 120.jpg","nameKn":"ಡಿಟರ್ಜೆಂಟ್ ಪೌಡರ್ ರೆಗ್ಯುಲರ್","description":"Powerful stain removal formula, works on tough dirt. Safe for all fabrics and maintains brightness.","emoji":"🧺"},
  {"id":2,"nameEn":"Detergent Powder Lemon","category":"detergent","price":125,"weight":"1 kg","imageurl":"images\\\\Detergent Powder Lemon 80.jpg","nameKn":"ಡಿಟರ್ಜೆಂಟ್ ಪೌಡರ್ ಲೆಮನ್","description":"Fresh lemon fragrance with natural cleaning agents. Removes odors and gives clothes a citrus shine.","emoji":"🧺"},
  {"id":3,"nameEn":"Detergent Powder Advanced Clean","category":"detergent","price":135,"weight":"1 kg","imageurl":"images\\\\Detergent Powder Advanced Clean 105.jpg","nameKn":"ಡಿಟರ್ಜೆಂಟ್ ಪೌಡರ್ ಅಡ್ವಾನ್ಸ್ಡ್","description":"Deep cleaning technology for high-efficiency machines. Removes embedded stains and odors effectively.","emoji":"🧺"},
  {"id":4,"nameEn":"Liquid Detergent Front Load","category":"detergent","price":350,"weight":"1 L","imageurl":"images\\\\Liquid Detergent Front Load 350.jpg","nameKn":"ಲಿಕ್ವಿಡ್ ಡಿಟರ್ಜೆಂಟ್ ಫ್ರಂಟ್ ಲೋಡ್","description":"HE compatible low-sudsing liquid. Protects fabric fibers and delivers superior cleaning for front-loaders.","emoji":"🧺"},
  {"id":5,"nameEn":"Liquid Detergent Top Load","category":"detergent","price":250,"weight":"1 L","imageurl":"images\\\\Liquid Detergent Top Load 250.jpg","nameKn":"ಲಿಕ್ವಿಡ್ ಡಿಟರ್ಜೆಂಟ್ ಟಾಪ್ ಲೋಡ್","description":"High-sudsing formula perfect for top-load washers. Tackles grease and brightens whites instantly.","emoji":"🧺"},
  {"id":6,"nameEn":"Laundry Soap Bar","category":"detergent","price":20,"weight":"200 g","imageurl":"images\\\\Laundry Soap Bar 20.jpg","nameKn":"ಲಾಂಡ್ರಿ ಸೋಪು ಬಾರ್","description":"Classic laundry bar for handwash. Gentle on skin yet removes stains from collars and cuffs.","emoji":"🧺"},
  {"id":7,"nameEn":"Laundry Soap Multipack","category":"detergent","price":510,"weight":"1 kg","imageurl":"images\\\\Laundry Soap Multipack 500.jpg","nameKn":"ಲಾಂಡ್ರಿ ಸೋಪು ಮಲ್ಟಿಪ್ಯಾಕ್","description":"Value pack of 4 bars. Biodegradable formula trusted for generations. Great for everyday laundry.","emoji":"🧺"},
  {"id":8,"nameEn":"Color Care Detergent","category":"detergent","price":350,"weight":"1 kg","imageurl":"images\\\\Color Care Detergent 350.jpg","nameKn":"ಕಲರ್ ಕೇರ್ ಡಿಟರ್ಜೆಂಟ್","description":"Protects vibrant colors from fading. Special polymers lock dye and prevent color transfer.","emoji":"🧺"},
  {"id":9,"nameEn":"White Bright Detergent","category":"detergent","price":1200,"weight":"1 kg","imageurl":"images\\\\White Bright Detergent 1200.jpg","nameKn":"ವೈಟ್ ಬ್ರೈಟ್ ಡಿಟರ್ಜೆಂಟ್","description":"Optical brighteners make dull whites look new. Removes greyish residue from repeated washes.","emoji":"🧺"},
  {"id":10,"nameEn":"Wool & Delicate Detergent","category":"detergent","price":175,"weight":"500 ml","imageurl":"images\\\\Wool & Delicate Detergent 140.jpg","nameKn":"ಉಣ್ಣೆ ಮತ್ತು ಡೆಲಿಕೇಟ್ ಡಿಟರ್ಜೆಂಟ್","description":"Enzyme-rich formula safe for silk, wool and cashmere. Maintains texture and prevents shrinkage.","emoji":"🧺"},
  {"id":11,"nameEn":"Baby Clothes Detergent","category":"detergent","price":225,"weight":"1 L","imageurl":"images\\\\Baby Clothes Detergent 700.jpg","nameKn":"ಬೇಬಿ ಕ್ಲೋತ್ಸ್ ಡಿಟರ್ಜೆಂಟ್","description":"Hypoallergenic and fragrance-free. Dermatologically tested, gentle on baby\'s sensitive skin.","emoji":"🧺"},
  {"id":12,"nameEn":"Anti Bacterial Laundry Liquid","category":"detergent","price":500,"weight":"1 L","imageurl":"images\\\\Anti Bacterial Laundry Liquid 1980.jpg","nameKn":"ಆಂಟಿ ಬ್ಯಾಕ್ಟೀರಿಯಲ್ ಲಾಂಡ್ರಿ ಲಿಕ್ವಿಡ್","description":"Kills 99.9% of germs and bacteria. Sanitizes clothes deeply, ideal for towels and linens.","emoji":"🧺"},
  {"id":13,"nameEn":"Fabric Conditioner Fresh","category":"softener","price":340,"weight":"860 ml","imageurl":"images\\\\Fabric Conditioner Fresh 340.jpg","nameKn":"ಫ್ಯಾಬ್ರಿಕ್ ಕಂಡೀಷನರ್ ಫ್ರೆಶ್","description":"Long-lasting freshness that lasts for days. Reduces static cling and makes ironing easier.","emoji":"🧺"},
  {"id":14,"nameEn":"Fabric Conditioner Floral","category":"softener","price":390,"weight":"860 ml","imageurl":"images\\\\Fabric Conditioner Floral 390.jpg","nameKn":"ಫ್ಯಾಬ್ರಿಕ್ ಕಂಡೀಷನರ್ ಫ್ಲೋರಲ್","description":"Wildflower bouquet scent with extra softness. Keeps fabrics fluffy and vibrant for longer.","emoji":"🧺"},
  {"id":15,"nameEn":"Fabric Softener Lavender","category":"softener","price":360,"weight":"860 ml","imageurl":"images\\\\Fabric Softener Lavender 360.jpg","nameKn":"ಫ್ಯಾಬ್ರಿಕ್ ಸಾಫ್ಟ್ನರ್ ಲ್ಯಾವೆಂಡರ್","description":"Calming lavender aroma that relaxes your senses. Natural extract softens fibers beautifully.","emoji":"🧺"},
  {"id":16,"nameEn":"Fabric Softener Rose Fresh","category":"softener","price":750,"weight":"860 ml","imageurl":"images\\\\Fabric Softener Rose Fresh 750.jpg","nameKn":"ಫ್ಯಾಬ್ರಿಕ್ ಸಾಫ್ಟ್ನರ್ ರೋಸ್","description":"Romantic rose scent infused with essential oils. Provides intense softness for towels and bedsheets.","emoji":"🧺"},
  {"id":17,"nameEn":"Fabric Conditioner Silk Care","category":"softener","price":205,"weight":"860 ml","imageurl":"images\\\\Fabric Conditioner Silk Care 450.jpg","nameKn":"ಸಿಲ್ಕ್ ಕೇರ್ ಕಂಡೀಷನರ್","description":"Premium conditioner for delicate silks. Enhances drape and prevents fabric damage.","emoji":"🧺"},
  {"id":18,"nameEn":"Fabric Softener Refill Pack","category":"softener","price":280,"weight":"1 L","imageurl":"images\\\\Fabric Softener Refill Pack 280.jpg","nameKn":"ಫ್ಯಾಬ್ರಿಕ್ ಸಾಫ್ಟ್ನರ್ ರೀಫಿಲ್ ಪ್ಯಾಕ್","description":"Economical refill pack with same great quality. Reduces plastic waste without compromising softness.","emoji":"🧺"},
  {"id":19,"nameEn":"Stain Remover Powder","category":"stain","price":250,"weight":"500 g","imageurl":"images\\\\Stain Remover Powder 250.jpg","nameKn":"ಸ್ಟೇನ್ ರಿಮೂವರ್ ಪೌಡರ್","description":"Oxygen-based powder erases coffee, wine and grass stains. Safe for colors and whites.","emoji":"🧺"},
  {"id":20,"nameEn":"Stain Remover Liquid","category":"stain","price":60,"weight":"500 ml","imageurl":"images\\\\Stain Remover Liquid 60.jpg","nameKn":"ಸ್ಟೇನ್ ರಿಮೂವರ್ ಲಿಕ್ವಿಡ್","description":"Pre-treat stubborn spots with enzyme boost formula. Works on grease, ink and lipstick.","emoji":"🧺"},
  {"id":21,"nameEn":"Laundry Spray Stain Remover","category":"stain","price":300,"weight":"300 ml","imageurl":"images\\\\Laundry Spray Stain Remover 300.jpg","nameKn":"ಲಾಂಡ್ರಿ ಸ್ಪ್ರೇ ಸ್ಟೇನ್ ರಿಮೂವರ್","description":"Spray & wash convenience for instant stain treatment. Portable bottle for on-the-go use.","emoji":"🧺"},
  {"id":22,"nameEn":"Oxygen Stain Remover","category":"stain","price":800,"weight":"500 g","imageurl":"images\\\\Oxygen Stain Remover 800.jpg","nameKn":"ಆಕ್ಸಿಜನ್ ಸ್ಟೇನ್ ರಿಮೂವರ್","description":"Active oxygen crystals lift stains without chlorine. Eco-friendly and septic safe.","emoji":"🧺"},
  {"id":23,"nameEn":"Laundry Bleach Liquid","category":"bleach","price":400,"weight":"500 ml","imageurl":"images\\\\Laundry Bleach Liquid 400.jpg","nameKn":"ಲಾಂಡ್ರಿ ಬ್ಲೀಚ್ ಲಿಕ್ವಿಡ್","description":"Chlorine-based whitener for white cottons. Removes tough stains and restores whiteness.","emoji":"🧺"},
  {"id":24,"nameEn":"Laundry Bleach Powder","category":"bleach","price":170,"weight":"500 g","imageurl":"images\\\\Laundry Bleach Powder 170.jpg","nameKn":"ಲಾಂಡ್ರಿ ಬ್ಲೀಚ್ ಪೌಡರ್","description":"Powdered bleach for deep whitening. Effective on yellowing and mildew stains.","emoji":"🧺"},
  {"id":25,"nameEn":"Chlorine Bleach","category":"bleach","price":290,"weight":"500 ml","imageurl":"images\\\\Chlorine Bleach 290.jpg","nameKn":"ಕ್ಲೋರಿನ್ ಬ್ಲೀಚ್","description":"Regular chlorine bleach for sanitizing and whitening. Use with caution on colors.","emoji":"🧺"},
  {"id":26,"nameEn":"Oxygen Bleach Powder","category":"bleach","price":2400,"weight":"500 g","imageurl":"images\\\\Oxygen Bleach Powder 2400.jpg","nameKn":"ಆಕ್ಸಿಜನ್ ಬ್ಲೀಚ್ ಪೌಡರ್","description":"Color-safe oxygen bleach removes dinginess naturally. Works on all washable fabrics.","emoji":"🧺"},
  {"id":27,"nameEn":"Laundry Brush Heavy Duty","category":"accessories","price":140,"weight":"1 pc","imageurl":"images\\\\Laundry Brush Heavy Duty 140.jpg","nameKn":"ಲಾಂಡ್ರಿ ಬ್ರಷ್ ಹೆವಿ ಡ್ಯೂಟಿ","description":"Sturdy bristles for scrubbing cuffs, collars and stubborn stains. Ergonomic handle.","emoji":"🧺"},
  {"id":28,"nameEn":"Cloth Scrubbing Brush","category":"accessories","price":100,"weight":"1 pc","imageurl":"images\\\\Cloth Scrubbing Brush 100.jpg","nameKn":"ಕ್ಲಾತ್ ಸ್ಕ್ರಬ್ಬಿಂಗ್ ಬ್ರಷ್","description":"Dual-sided brush for gentle and heavy scrubbing. Ideal for delicate fabrics.","emoji":"🧺"},
  {"id":29,"nameEn":"Laundry Mesh Washing Bag","category":"accessories","price":110,"weight":"1 pc","imageurl":"images\\\\Laundry Mesh Washing Bag 110.jpg","nameKn":"ಲಾಂಡ್ರಿ ಮೆಶ್ ವಾಶಿಂಗ್ ಬ್ಯಾಗ್","description":"Protects delicates from tangling and tearing. Zipper closure with reinforced edges.","emoji":"🧺"},
  {"id":30,"nameEn":"Laundry Washing Ball","category":"accessories","price":115,"weight":"2 pcs","imageurl":"images\\\\Laundry Washing Ball 115.jpg","nameKn":"ಲಾಂಡ್ರಿ ವಾಶಿಂಗ್ ಬಾಲ್","description":"Eco-friendly washing balls reduce detergent need. Improves agitation for cleaner clothes.","emoji":"🧺"},
  {"id":31,"nameEn":"Dryer Sheets","category":"accessories","price":380,"weight":"30 pcs","imageurl":"images\\\\Dryer Sheets 380.jpg","nameKn":"ಡ್ರೈಯರ್ ಶೀಟ್ಸ್","description":"Anti-static sheets reduce wrinkles and add freshness. Prevents lint from sticking.","emoji":"🧺"},
  {"id":32,"nameEn":"Clothes Pegs Plastic","category":"accessories","price":530,"weight":"12 pcs","imageurl":"images\\\\Clothes Pegs Plastic 530.jpg","nameKn":"ಕ್ಲೋತ್ಸ್ ಪೆಗ್ಸ್ ಪ್ಲಾಸ್ಟಿಕ್","description":"Strong plastic pegs with weather resistance. Holds clothes firmly even on windy days.","emoji":"🧺"},
  {"id":33,"nameEn":"Laundry Basket Plastic","category":"accessories","price":999,"weight":"1 pc","imageurl":"images\\\\Laundry Basket Plastic 999.jpg","nameKn":"ಲಾಂಡ್ರಿ ಬಾಸ್ಕೆಟ್ ಪ್ಲಾಸ್ಟಿಕ್","description":"Large capacity basket with ventilation holes. Lightweight and easy to carry.","emoji":"🧺"},
  {"id":34,"nameEn":"Laundry Perfume Booster","category":"softener","price":300,"weight":"200 ml","imageurl":"images\\\\Laundry Perfume Booster 300.jpg","nameKn":"ಲಾಂಡ್ರಿ ಪರ್ಫ್ಯೂಮ್ ಬೂಸ್ಟರ್","description":"Concentrated fragrance beads that last for weeks. Adds luxurious scent to every load.","emoji":"🧺"},
  {"id":35,"nameEn":"Fabric Whitener","category":"bleach","price":80,"weight":"500 ml","imageurl":"images\\\\Fabric Whitener 80.jpg","nameKn":"ಫ್ಯಾಬ್ರಿಕ್ ವೈಟ್ನರ್","description":"Optical whitener for dull gray clothes. Restores brilliance without chlorine bleach.","emoji":"🧺"},
  {"id":36,"nameEn":"Laundry Pods","category":"detergent","price":500,"weight":"20 pcs","imageurl":"images\\\\Laundry Pods 500.jpg","nameKn":"ಲಾಂಡ್ರಿ ಪಾಡ್ಸ್","description":"Pre-measured pods with 3-in-1 cleaning action. No mess, no waste, perfect for busy households.","emoji":"🧺"},
  {"id":37,"nameEn":"Eco Friendly Detergent Powder","category":"detergent","price":290,"weight":"1 kg","imageurl":"images\\\\Eco Friendly Detergent Powder 290.jpg","nameKn":"ಎಕೋ ಫ್ರೆಂಡ್ಲಿ ಡಿಟರ್ಜೆಂಟ್ ಪೌಡರ್","description":"Plant-based and biodegradable. Gentle on skin and safe for greywater systems.","emoji":"🧺"},
  {"id":38,"nameEn":"Eco Friendly Liquid Detergent","category":"detergent","price":600,"weight":"1 L","imageurl":"images\\\\Eco Friendly Liquid Detergent 600.jpg","nameKn":"ಎಕೋ ಫ್ರೆಂಡ್ಲಿ ಲಿಕ್ವಿಡ್ ಡಿಟರ್ಜೆಂಟ್","description":"Concentrated eco liquid with natural enzymes. Plastic-neutral packaging.","emoji":"🧺"},
  {"id":39,"nameEn":"Hand Wash Detergent Liquid","category":"detergent","price":320,"weight":"500 ml","imageurl":"images\\\\Hand Wash Detergent Liquid 320.jpg","nameKn":"ಹ್ಯಾಂಡ್ ವಾಶ್ ಡಿಟರ್ಜೆಂಟ್ ಲಿಕ್ವಿಡ್","description":"Mild formula for handwashing delicate clothes. Creates gentle foam and rinses easily.","emoji":"🧺"},
  {"id":40,"nameEn":"Saree Care Detergent","category":"detergent","price":600,"weight":"500 ml","imageurl":"images\\\\Saree Care Detergent 600.jpg","nameKn":"ಸೀರೆ ಕೇರ್ ಡಿಟರ್ಜೆಂಟ್","description":"Special detergent for silk and embroidered sarees. Preserves zari and color brightness.","emoji":"🧺"},
  {"id":41,"nameEn":"Sportswear Detergent","category":"detergent","price":850,"weight":"500 ml","imageurl":"images\\\\Sportswear Detergent 850.jpg","nameKn":"ಸ್ಪೋರ್ಟ್ಸ್ವೇರ್ ಡಿಟರ್ಜೆಂಟ್","description":"Removes sweat odors and bacteria from gym clothes. Maintains technical fabric performance.","emoji":"🧺"},
  {"id":42,"nameEn":"Laundry Disinfectant","category":"bleach","price":280,"weight":"500 ml","imageurl":"images\\\\Laundry Disinfectant 280.jpg","nameKn":"ಲಾಂಡ್ರಿ ಡಿಸ್ಇನ್ಫೆಕ್ಟಂಟ್","description":"Kills household germs and viruses. Safe to use with all detergents, fresh scent.","emoji":"🧺"},
  {"id":43,"nameEn":"Ironing Spray Starch","category":"accessories","price":550,"weight":"400 ml","imageurl":"images\\\\Ironing Spray Starch 550.jpg","nameKn":"ಐರನಿಂಗ್ ಸ್ಪ್ರೇ ಸ್ಟಾರ್ಚ್","description":"Creates crisp finishes on cotton shirts. Reduces ironing time and repels dirt.","emoji":"🧺"},
  {"id":44,"nameEn":"Laundry Fragrance Beads","category":"softener","price":280,"weight":"200 g","imageurl":"images\\\\Laundry Fragrance Beads 280.jpg","nameKn":"ಲಾಂಡ್ರಿ ಫ್ರ್ಯಾಗ್ರನ್ಸ್ ಬೀಡ್ಸ್","description":"Long-lasting scent booster that works for up to 12 weeks. Adds fragrance directly to fabrics.","emoji":"🧺"},
  {"id":45,"nameEn":"Laundry Scent Booster","category":"softener","price":195,"weight":"200 g","imageurl":"images\\\\Laundry Scent Booster.jpg","nameKn":"ಲಾಂಡ್ರಿ ಸೆಂಟ್ ಬೂಸ್ಟರ್","description":"Intense fragrance pearls that release freshness with every wear. Customizable scent strength.","emoji":"🧺"},
  {"id":47,"nameEn":"Laundry Deodorizer Powder","category":"accessories","price":1600,"weight":"500 g","imageurl":"images\\\\Laundry Deodorizer Powder.jpg","nameKn":"ಲಾಂಡ್ರಿ ಡಿಯೋಡೋರೈಜರ್ ಪೌಡರ್","description":"Neutralizes tough odors like smoke and sweat. Safe for all fabrics and septic systems.","emoji":"🧺"},
  {"id":48,"nameEn":"Fabric Starch Powder","category":"accessories","price":120,"weight":"500 g","imageurl":"images\\\\Fabric Starch Powder 900.jpg","nameKn":"ಫ್ಯಾಬ್ರಿಕ್ ಸ್ಟಾರ್ಚ್ ಪೌಡರ್","description":"Traditional starch for crisp cottons and linens. Mix with water for desired stiffness.","emoji":"🧺"},
  {"id":49,"nameEn":"Laundry Bucket Detergent Pack","category":"detergent","price":400,"weight":"1 kg","imageurl":"images\\\\Laundry Bucket Detergent Pack 400.jpg","nameKn":"ಲಾಂಡ್ರಿ ಬಕೆಟ್ ಡಿಟರ್ಜೆಂಟ್ ಪ್ಯಾಕ್","description":"Bulk pack with reusable bucket. Powder detergent for over 100 washes, economical choice.","emoji":"🧺"},
  {"id":50,"nameEn":"Laundry Color Catcher Sheets","category":"accessories","price":300,"weight":"20 pcs","imageurl":"images\\\\Laundry Color Catcher Sheets 300.jpg","nameKn":"ಲಾಂಡ್ರಿ ಕಲರ್ ಕ್ಯಾಚರ್ ಶೀಟ್ಸ್","description":"Traps loose dyes to prevent color bleeding. Lets you mix lights and darks safely.","emoji":"🧺"},
  {"id":51,"nameEn":"Heavy Duty Stain Remover","category":"stain","price":180,"weight":"500 g","imageurl":"images\\\\Heavy Duty Stain Remover 22.webp","nameKn":"ಹೆವಿ ಡ್ಯೂಟಿ ಸ್ಟೇನ್ ರಿಮೂವರ್","description":"Industrial strength for oil, paint and grease. Powerful yet safe for most fabrics.","emoji":"🧺"},
  {"id":52,"nameEn":"Chlorine Bleach Strong","category":"bleach","price":450,"weight":"500 ml","imageurl":"images\\\\Chlorine Bleach Strong 450.jpg","nameKn":"ಕ್ಲೋರಿನ್ ಬ್ಲೀಚ್ ಸ್ಟ್ರಾಂಗ್","description":"Extra strength formula for heavy whitening. Ideal for hospital and hotel linen.","emoji":"🧺"},
  {"id":53,"nameEn":"Laundry Washing Paddle","category":"accessories","price":300,"weight":"1 pc","imageurl":"images\\\\Laundry Washing Paddle 300.jpg","nameKn":"ಲಾಂಡ್ರಿ ವಾಶಿಂಗ್ ಪ್ಯಾಡಲ್","description":"Traditional paddle for hand washing large items. Helps agitate clothes without bending.","emoji":"🧺"},
  {"id":54,"nameEn":"Clothes Drying Stand","category":"accessories","price":1500,"weight":"1 pc","imageurl":"images\\\\Clothes Drying Stand.jpg","nameKn":"ಕ್ಲೋತ್ಸ್ ಡ್ರೈಯಿಂಗ್ ಸ್ಟ್ಯಾಂಡ್","description":"Collapsible drying rack with 15 meters of space. Rust-resistant steel construction.","emoji":"🧺"},
  {"id":55,"nameEn":"Laundry Gloves","category":"accessories","price":150,"weight":"1 pair","imageurl":"images\\\\Laundry Gloves 150.jpg","nameKn":"ಲಾಂಡ್ರಿ ಗ್ಲೌಸ್","description":"Waterproof gloves protect hands from harsh detergents. Non-slip texture for grip.","emoji":"🧺"}
]';
  $data = json_decode($staticJson, true);
  foreach ($data as &$item) {
    // Normalize image path
    if (!empty($item['imageurl'])) {
      $item['imageurl'] = normalizeImagePath($item['imageurl']);
    }
    // Ensure emoji is set
    if (!isset($item['emoji']) || empty($item['emoji'])) {
      $item['emoji'] = '🧺';
    }
  }
  $fallback = $data;
  return $fallback;
}
// ============================================================
// GET PRODUCTS (DB first, fallback to static)
// ============================================================
$productsData = getLaundryProducts();
if (empty($productsData)) {
  $productsData = getFallbackLaundryProducts();
}
// ============================================================
// AJAX CART ENDPOINTS
// ============================================================
if (isset($_GET['ajax'])) {
  header('Content-Type: application/json');
  $action = $_GET['ajax'] ?? '';
  switch ($action) {
    case 'products':
      $search = $_GET['search'] ?? '';
      echo json_encode($productsData);
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
                'name' => $p['nameEn'],
                'kn_name' => $p['nameKn'],
                'price' => $p['price'],
                'icon' => 'fa-jug-detergent'
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
              'icon' => $product['icon'] ?? 'fa-jug-detergent'
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>Laundry Basket · KSR Guru</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <style>
    /* ===== SAME CSS AS STATIC VERSION ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      background: #eef4fc;
      padding: 24px 20px;
      min-height: 100vh;
    }

    #app {
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 40px;
      box-shadow: 0 20px 40px rgba(0, 37, 55, 0.05);
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
      color: #1e6f9f;
    }

    .shop-name .kannada {
      font-size: 1.1rem;
      color: #348f8f;
      margin-top: 6px;
    }

    .cart-link {
      background: #e5f3ff;
      padding: 12px 24px;
      border-radius: 60px;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: background 0.2s;
      text-decoration: none;
      color: #1e6f9f;
      font-weight: 600;
    }

    .cart-badge {
      background: #2b9b9b;
      color: white;
      font-size: 0.85rem;
      font-weight: 700;
      padding: 2px 10px;
      border-radius: 40px;
    }

    .cart-link:hover {
      background: #d0e7fc;
    }

    .nav-links {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }

    .nav-links a {
      background: #f0f6fc;
      border: 1px solid #d0e6f2;
      padding: 8px 16px;
      border-radius: 30px;
      text-decoration: none;
      color: #1e6f9f;
      font-weight: 500;
      font-size: 0.85rem;
    }

    .nav-links a:hover {
      background: #e2effa;
    }

    .search-section {
      margin-bottom: 32px;
    }

    .search-box {
      display: flex;
      align-items: center;
      background: #f2f8fd;
      border-radius: 60px;
      padding: 8px 24px;
      margin-bottom: 20px;
      border: 1px solid #d0e6f2;
      max-width: 500px;
    }

    .search-box i {
      color: #2b9b9b;
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
      color: #1b4f6e;
    }

    .filter-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .pill {
      background: #f0f6fc;
      padding: 10px 24px;
      border-radius: 60px;
      font-size: 1rem;
      font-weight: 500;
      color: #1e6f9f;
      cursor: pointer;
      transition: all 0.2s;
    }

    .pill.active {
      background: #1e6f9f;
      color: white;
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 28px;
      margin-top: 20px;
      margin-bottom: 50px;
    }

    .product-card {
      background: white;
      border-radius: 32px;
      padding: 18px 16px 22px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
      border: 1px solid #e4edf3;
      transition: transform 0.2s, box-shadow 0.2s;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }

    .product-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 30px rgba(0, 0, 0, 0.08);
    }

    .product-image {
      width: 110px;
      height: 110px;
      border-radius: 55px;
      overflow: hidden;
      background: #f6fafd;
      border: 2px solid #c2def0;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
      flex-shrink: 0;
    }

    .product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .product-image .emoji-fallback {
      font-size: 3.5rem;
    }

    .product-name {
      font-weight: 700;
      font-size: 1.1rem;
      color: #1c567a;
      margin-top: 4px;
    }

    .product-name-kn {
      font-size: 0.8rem;
      color: #5889a3;
      margin-bottom: 6px;
    }

    .product-description {
      font-size: 0.8rem;
      color: #6d8faa;
      margin: 6px 0 10px;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      margin-top: auto;
    }

    .product-price {
      font-weight: 800;
      font-size: 1.2rem;
      color: #1e6f9f;
    }

    .add-btn {
      background: #2b9b9b;
      border: none;
      color: white;
      width: 48px;
      height: 48px;
      border-radius: 60px;
      font-size: 1.4rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .add-btn.added-flash {
      background: #4caf50 !important;
    }

    .cart-sidebar {
      position: fixed;
      top: 0;
      right: -420px;
      width: 400px;
      height: 100vh;
      background: #fffefc;
      box-shadow: -8px 0 40px rgba(0, 0, 0, 0.1);
      transition: right 0.3s ease;
      z-index: 1100;
      display: flex;
      flex-direction: column;
      border-left: 2px solid #e4edf3;
    }

    .cart-sidebar.open {
      right: 0;
    }

    .cart-header {
      padding: 24px;
      border-bottom: 2px solid #e4edf3;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .close-cart {
      font-size: 1.6rem;
      color: #6d8faa;
      cursor: pointer;
      background: none;
      border: none;
    }

    .cart-items {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
    }

    .empty-cart {
      text-align: center;
      margin-top: 60px;
      color: #6d8faa;
    }

    .cart-item {
      display: flex;
      gap: 14px;
      margin-bottom: 24px;
      padding-bottom: 18px;
      border-bottom: 1px solid #e4edf3;
      align-items: center;
    }

    .cart-item-image {
      width: 44px;
      height: 44px;
      background: #f6fafd;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }

    .cart-item-name {
      font-weight: 700;
      margin-bottom: 4px;
      font-size: 0.9rem;
    }

    .cart-item-price {
      color: #1e6f9f;
      font-weight: 600;
      font-size: 0.85rem;
    }

    .cart-item-actions {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 10px;
    }

    .cart-item-actions button {
      background: #f0f6fc;
      width: 30px;
      height: 30px;
      border-radius: 40px;
      border: none;
      cursor: pointer;
    }

    .cart-footer {
      padding: 24px;
      border-top: 2px solid #e4edf3;
    }

    .cart-total {
      display: flex;
      justify-content: space-between;
      font-weight: 800;
      font-size: 1.3rem;
      margin-bottom: 20px;
    }

    .checkout-btn,
    .continue-shopping {
      width: 100%;
      padding: 14px;
      border-radius: 50px;
      font-weight: 700;
      margin-bottom: 12px;
      border: none;
      cursor: pointer;
    }

    .checkout-btn {
      background: #1e6f9f;
      color: white;
    }

    .continue-shopping {
      background: none;
      border: 1px solid #d0e6f2;
      color: #1b4f6e;
    }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(3px);
      z-index: 1050;
      display: none;
    }

    .overlay.active {
      display: block;
    }

    footer {
      text-align: center;
      font-size: 0.85rem;
      color: #91b3cb;
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid #e4edf3;
    }

    @media (max-width: 768px) {
      #app {
        padding: 20px;
        border-radius: 28px;
      }

      .header {
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
      }

      .cart-link {
        align-self: flex-start;
        padding: 10px 20px;
      }

      .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
      }

      .cart-sidebar {
        width: 100%;
        right: -100%;
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
        <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
        <div class="pill" data-category="detergent">Detergents / ಡಿಟರ್ಜೆಂಟ್‌ಗಳು</div>
        <div class="pill" data-category="softener">Softeners / ಸಾಫ್ಟ್‌ನರ್‌ಗಳು</div>
        <div class="pill" data-category="stain">Stain Removers / ಕಲೆ ನಿವಾರಕಗಳು</div>
        <div class="pill" data-category="accessories">Accessories / ಬಿಡಿಭಾಗಗಳು</div>
      </div>
    </div>
    <div class="product-grid" id="productGrid">
    </div>
    <div class="cart-sidebar" id="cartSidebar">
      <div class="cart-header">
        <h3><i class="fas fa-shopping-cart"></i> Your Cart</h3>
        <button class="close-cart" id="closeCart">&times;</button>
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
      const products = <?php echo json_encode($productsData); ?>;
      let cart = [];
      const basePath = 'laundry.php';
      const cartCountSpan = document.getElementById('cartCount');
      const cartSidebar = document.getElementById('cartSidebar');
      const overlay = document.getElementById('overlay');
      const closeCartBtn = document.getElementById('closeCart');
      const continueShoppingBtn = document.getElementById('continueShopping');
      const checkoutBtn = document.getElementById('checkoutBtn');
      const cartItemsContainer = document.getElementById('cartItems');
      const totalPriceSpan = document.querySelector('.total-price');
      const searchInput = document.getElementById('searchInput');
      const filterPills = document.querySelectorAll('#filterPills .pill');
      const cartLink = document.getElementById('cartLink');
      const grid = document.getElementById('productGrid');
      let activeCategory = 'all';
      let searchTerm = '';

      function loadCart() {
        fetch('../index.php?ajax=cart')
          .then(res => res.json())
          .then(data => {
            cart = data;
            updateCartUI();
            if (cartSidebar.classList.contains('open')) renderCartItems();
          })
          .catch(console.error);
      }

      function updateCartUI() {
        const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
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
        fetch('../index.php?ajax=remove_cart', {
            method: 'POST',
            body: formData
          })
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
          <div class="cart-item-image">🧺</div>
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

      function renderProducts() {
        const filtered = products.filter(p => {
          const matchCat = activeCategory === 'all' || p.category === activeCategory;
          const matchSearch = searchTerm === '' || p.nameEn.toLowerCase().includes(searchTerm) || p.nameKn.toLowerCase().includes(searchTerm);
          return matchCat && matchSearch;
        });
        if (!filtered.length) {
          grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:60px;">✨ No laundry products found ✨</div>';
          return;
        }
        grid.innerHTML = filtered.map(p => {
          const imgSrc = p.imageurl || '';
          // Display actual image if available, otherwise fallback to emoji
          const imageHtml = imgSrc ?
            `<img src="${imgSrc}" alt="" loading="lazy" onerror="this.style.display='none';this.parentElement.innerHTML='<span class=\\'emoji-fallback\\'>${p.emoji || '🧺'}</span>'">` :
            `<span class="emoji-fallback">${p.emoji || '🧺'}</span>`;
          return `
        <div class="product-card">
          <div class="product-image">${imageHtml}</div>
          <div class="product-name">${p.nameEn}</div>
          ${p.nameKn && p.nameKn.toLowerCase() !== (p.nameEn || p.name).toLowerCase() ? `<div class="product-name-kn">${p.nameKn}</div>` : ""}
          <div class="product-description">${p.description}</div>
          <div class="price-row">
            <span class="product-price">₹${p.price}</span>
            <button class="add-btn" data-pid="${p.id}" onclick="window.addToCart(${p.id})"><i class="fas fa-cart-plus"></i></button>
          </div>
        </div>
      `;
        }).join('');
      }
      searchInput.addEventListener('input', function(e) {
        searchTerm = this.value.toLowerCase();
        renderProducts();
      });
      filterPills.forEach(pill => {
        pill.addEventListener('click', function() {
          filterPills.forEach(p => p.classList.remove('active'));
          this.classList.add('active');
          activeCategory = this.dataset.category;
          renderProducts();
        });
      });
      cartLink.addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof openCart === 'function') {
          openCart();
        } else {
          window.location.href = '../cart/cart.php';
        }
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
      loadCart();
      renderProducts();
    })();
  </script>
  <?php include __DIR__ . '/../includes/user-footer.php'; ?>
  <script src="sorting.js"></script>
</body>

</html>