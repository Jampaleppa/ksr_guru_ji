<?php
// ============================================================
// personal-care.php - Dynamic Personal Care page
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
$categorySlug = 'personal-care';
$personalCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($personalCategory) {
  // Fetch products from this category
  $dbProducts = getProductsByCategory($personalCategory['id']);
  if (!empty($dbProducts)) {
    foreach ($dbProducts as $p) {
      // Build description from weight or other fields
      $description = $p['description_en'] ?? ($p['weight'] ?? '1 unit');
      $productsData[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'kn' => $p['kn_name'] ?? $p['name'] . ' (ವೈಯಕ್ತಿಕ ಆರೈಕೆ)',
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
  // Embedded static product data (same as original HTML)
  $rawItems = [
    // Skincare
    ['name' => 'Nivea Soft Light Moisturizer', 'imageurl' => "images\\Nivea Soft Light Moisturizer 140.jpg", 'weight' => '200 g', 'price' => 299, 'category' => 'skincare'],
    ['name' => 'Pond\'s Light Moisturizer', 'imageurl' => "images\\Ponds Light Moisturizer.jpg ", 'weight' => '100 g', 'price' => 175, 'category' => 'skincare'],
    ['name' => 'Garnier Bright Complete Serum Cream', 'imageurl' => "images\\Garnier Bright Complete Serum Cream 320.jpg", 'weight' => '50 g', 'price' => 320, 'category' => 'skincare'],
    ['name' => 'Lakme Peach Milk Moisturizer', 'imageurl' => "images\\Lakme Peach Milk Moisturizer 220.jpg", 'weight' => '120 ml', 'price' => 260, 'category' => 'skincare'],
    ['name' => 'Himalaya Neem Face Wash', 'imageurl' => "images\\Himalaya Neem Face Wash 310.jpg ", 'weight' => '150 ml', 'price' => 310, 'category' => 'skincare'],
    ['name' => 'Clean & Clear Face Wash', 'imageurl' => "images\\Clean & Clear Face Wash 240.jpg ", 'weight' => '150 ml', 'price' => 165, 'category' => 'skincare'],
    ['name' => 'Cetaphil Gentle Cleanser', 'imageurl' => "images\\Cetaphil Gentle Cleanser 680.jpg ", 'weight' => '125 ml', 'price' => 680, 'category' => 'skincare'],
    ['name' => 'Lotus Herbals WhiteGlow Cream', 'imageurl' => "images\\Lotus Herbals WhiteGlow Cream 480.jpg", 'weight' => '50 g', 'price' => 495, 'category' => 'skincare'],
    ['name' => 'Mamaearth Vitamin C Face Cream', 'imageurl' => "images\\Mamaearth Vitamin C Face Cream 250.jpg ", 'weight' => '50 g', 'price' => 260, 'category' => 'skincare'],
    ['name' => 'Biotique Bio Morning Nectar Cream', 'imageurl' => "images\\Biotique Bio Morning Nectar Cream 620.jpg", 'weight' => '50 g', 'price' => 620, 'category' => 'skincare'],
    ['name' => 'Nivea Aloe Hydration Body Lotion', 'imageurl' => "images\\Nivea Aloe Hydration Body Lotion 190.jpg ", 'weight' => '200 ml', 'price' => 190, 'category' => 'skincare'],
    ['name' => 'Vaseline Intensive Care Lotion', 'imageurl' => "images\\Vaseline Intensive Care Lotion 220.jpg ", 'weight' => '200 ml', 'price' => 220, 'category' => 'skincare'],
    ['name' => 'Pond\'s Super Light Gel', 'imageurl' => "images\\Pond's Super Light Gel.jpg ", 'weight' => '100 ml', 'price' => 220, 'category' => 'skincare'],
    // Haircare
    ['name' => 'Mamaearth BhringAmla Shampoo', 'imageurl' => "images\\Mamaearth BhringAmla Shampoo 350.jpg ", 'weight' => '250 ml', 'price' => 360, 'category' => 'haircare'],
    ['name' => 'Indulekha Bringha Shampoo', 'imageurl' => "images\\Indulekha Bringha Shampoo 250.jpg ", 'weight' => '200 ml', 'price' => 260, 'category' => 'haircare'],
    ['name' => 'Vaadi Herbals Onion Shampoo', 'imageurl' => "images\\Vaadi Herbals Onion Shampoo 190.jpg ", 'weight' => '250 ml', 'price' => 200, 'category' => 'haircare'],
    ['name' => 'Just Herbs Amla Neem Root Nourishing Shampoo', 'imageurl' => "images\\Just Herbs Amla Neem Root Nourishing Shampoo 280.jpg ", 'weight' => '200 ml', 'price' => 283, 'category' => 'haircare'],
    ['name' => 'Head & Shoulders Anti Dandruff Shampoo', 'imageurl' => "images\\Head & Shoulders Anti Dandruff Shampoo 200.jpg ", 'weight' => '340 ml', 'price' => 200, 'category' => 'haircare'],
    ['name' => 'Clinic Plus Strong & Long Shampoo', 'imageurl' => "images\\Clinic Plus Strong & Long Shampoo 580.jpg ", 'weight' => '340 ml', 'price' => 590, 'category' => 'haircare'],
    ['name' => 'Dove Hair Fall Rescue Shampoo', 'imageurl' => "images\\Dove Hair Fall Rescue Shampoo 630.jpg ", 'weight' => '340 ml', 'price' => 630, 'category' => 'haircare'],
    ['name' => 'Tresemme Keratin Smooth Shampoo', 'imageurl' => "images\\Tresemme Keratin Smooth Shampoo 150.jpg ", 'weight' => '340 ml', 'price' => 170, 'category' => 'haircare'],
    ['name' => 'Parachute Coconut Hair Oil', 'imageurl' => "images\\Parachute Coconut Hair Oil 200.jpg ", 'weight' => '200 ml', 'price' => 210, 'category' => 'haircare'],
    ['name' => 'Indulekha Bringha Hair Oil', 'imageurl' => "images\\Indulekha Bringha Hair Oil 290.jpg ", 'weight' => '100 ml', 'price' => 300, 'category' => 'haircare'],
    // Oral Care
    ['name' => 'Dabur Red Toothpaste', 'imageurl' => "images\\Toothpaste\\Dabur Red Toothpaste 207.jpg ", 'weight' => '200 g', 'price' => 210, 'category' => 'oral'],
    ['name' => 'Patanjali Dant Kanti Natural Toothpaste', 'imageurl' => "images\\Patanjali Dant Kanti Natural Toothpaste 60.jpg ", 'weight' => '200 g', 'price' => 73, 'category' => 'oral'],
    ['name' => 'Vicco Vajradanti Toothpaste', 'imageurl' => "images\\Toothpaste\\Vicco Vajradanti Toothpaste 160.jpg ", 'weight' => '100 g', 'price' => 169, 'category' => 'oral'],
    ['name' => 'Himalaya Botanique Complete Care Toothpaste', 'imageurl' => "images\\Toothpaste\\Himalaya Botanique Complete Care Toothpaste 350.jpg ", 'weight' => '150 g', 'price' => 352, 'category' => 'oral'],
    ['name' => 'Colgate Strong Teeth Toothpaste', 'imageurl' => "images\\Toothpaste\\Colgate Strong Teeth Toothpaste 90.jpg ", 'weight' => '200 g', 'price' => 100, 'category' => 'oral'],
    ['name' => 'Sensodyne Repair & Protect', 'imageurl' => "images\\Toothpaste\\Sensodyne Repair & Protect 200.jpg ", 'weight' => '100 g', 'price' => 220, 'category' => 'oral'],
    // Bath & Body
    ['name' => 'Dettol Original Bathing Soap Bar', 'imageurl' => "images\\soap\\Dettol Original Bathing Soap Bar 250.jpg ", 'weight' => '125 g', 'price' => 249, 'category' => 'bath'],
    ['name' => 'Dove Cream Beauty Bathing Bar', 'imageurl' => "images\\soap\\Dove Cream Beauty Bathing Bar 60.jpg ", 'weight' => '100 g', 'price' => 75, 'category' => 'bath'],
    ['name' => 'Lux Rose & Vitamin E Soap', 'imageurl' => "images\\soap\\Lux Rose & Vitamin E Soap 75.jpg ", 'weight' => '100 g', 'price' => 80, 'category' => 'bath'],
    ['name' => 'Pears Pure & Gentle Soap', 'imageurl' => "images\\soap\\Pears Pure & Gentle Soap 220.jpg ", 'weight' => '125 g', 'price' => 65, 'category' => 'bath'],
    ['name' => 'Lifebuoy Total Soap', 'imageurl' => "images\\soap\\Lifebuoy Total Soap 40.jpg ", 'weight' => '125 g', 'price' => 45, 'category' => 'bath'],
    ['name' => 'Fiama Shower Gel', 'imageurl' => "images\\Fiama Shower Gel 150.jpg ", 'weight' => '100 ml', 'price' => 160, 'category' => 'bath'],
    // Men's grooming
    ['name' => 'Gillette Mach3 Razor', 'imageurl' => "images\\Gillette Mach3 Razor 280.jpg ", 'weight' => '1 unit', 'price' => 296, 'category' => 'mens'],
    ['name' => 'Gillette Shaving Foam', 'imageurl' => "images\\Gillette Shaving Foam 95.jpg ", 'weight' => 'cream', 'price' => 105, 'category' => 'mens'],
    ['name' => 'Nivea Men Face Wash', 'imageurl' => "images\\Nivea Men Face Wash 145.jpg ", 'weight' => 'cream', 'price' => 150, 'category' => 'mens'],
    ['name' => 'Beardo Beard Oil', 'imageurl' => "images\\Beardo Beard Oil 350.jpg ", 'weight' => '30 ml', 'price' => 350, 'category' => 'mens'],
    ['name' => 'Old Spice After Shave Lotion', 'imageurl' => "images\\Old Spice After Shave Lotion 220.jpg ", 'weight' => '100 ml', 'price' => 230, 'category' => 'mens']
  ];
  // Build productsData from rawItems
  foreach ($rawItems as $item) {
    $name = $item['name'] . ' (' . $item['weight'] . ')';
    $kn = $item['name'] . ' ' . $item['weight'];
    $productsData[] = [
      'id' => count($productsData) + 1,
      'name' => $name,
      'kn' => $kn,
      'category' => $item['category'],
      'price' => $item['price'],
      'imageUrl' => $item['imageurl'],
      'description' => '', // will be generated in JS
      'weight' => $item['weight']
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
  <title>Botanical Essence | Mindful Personal Care</title>
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- Google Fonts: Inter + Space Grotesk -->
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

    .app-wrapper {
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
      display: block;
    }

    .shop-header {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
      margin-bottom: 2rem;
    }

    .branding h1 {
      font-family: 'Space Grotesk', monospace;
      font-size: 2.2rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      background: linear-gradient(130deg, #c45a2c, #f0975e);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .branding .kannada-tag {
      font-size: 0.85rem;
      color: #cb825b;
      font-weight: 500;
      margin-top: 4px;
    }

    .cart-link {
      background: #fff1e6;
      border-radius: 80px;
      padding: 12px 24px;
      display: inline-flex;
      align-items: center;
      gap: 14px;
      transition: 0.2s;
      border: 1px solid #ffe0ce;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
      text-decoration: none;
      color: #c45a2c;
      font-weight: 600;
      font-size: 0.95rem;
    }

    .cart-link:hover {
      background: #ffe7db;
      transform: scale(1.01);
    }

    .cart-link i {
      font-size: 1.5rem;
      color: #c45a2c;
    }

    .cart-link .cart-count {
      background: #c45a2c;
      color: white;
      font-weight: 700;
      padding: 2px 12px;
      border-radius: 60px;
      font-size: 1rem;
      margin-left: 4px;
    }

    .search-panel {
      margin-bottom: 32px;
    }

    .search-bar {
      background: white;
      border-radius: 70px;
      padding: 6px 24px;
      display: flex;
      align-items: center;
      border: 1px solid #ffe2d0;
      gap: 12px;
      margin-bottom: 24px;
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.02);
    }

    .search-bar i {
      font-size: 1.2rem;
      color: #e07a44;
    }

    .search-bar input {
      flex: 1;
      border: none;
      padding: 15px 0;
      background: transparent;
      font-size: 1rem;
      outline: none;
      font-weight: 500;
    }

    .filter-row {
      overflow-x: auto;
      padding-bottom: 8px;
      scrollbar-width: thin;
    }

    .pill-group {
      display: flex;
      gap: 12px;
    }

    .pill {
      background: #fff5ed;
      padding: 8px 24px;
      border-radius: 60px;
      font-weight: 600;
      font-size: 0.85rem;
      color: #c45a2c;
      cursor: pointer;
      white-space: nowrap;
      border: 1px solid #ffd7be;
      transition: all 0.15s;
    }

    .pill.active {
      background: #c45a2c;
      color: white;
      border-color: #c45a2c;
      box-shadow: 0 6px 12px rgba(196, 90, 44, 0.2);
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 30px;
      margin: 32px 0 48px;
    }

    .product-card {
      background: #fffefb;
      border-radius: 36px;
      padding: 22px 18px 24px;
      transition: all 0.25s ease;
      border: 1px solid #f8e3d4;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.02);
      display: flex;
      flex-direction: column;
    }

    .product-card:hover {
      transform: translateY(-6px);
      border-color: #ffcfb0;
      box-shadow: 0 24px 36px -12px rgba(80, 48, 24, 0.12);
    }

    .product-img {
      width: 90px;
      height: 90px;
      object-fit: cover;
      border-radius: 32px;
      background: #fef0e6;
      border: 2px solid #ffe2cf;
      margin-bottom: 16px;
    }

    .product-title {
      font-weight: 800;
      font-size: 1.2rem;
      color: #2c241e;
      line-height: 1.3;
    }

    .product-title-kn {
      font-size: 0.75rem;
      color: #c2825b;
      margin: 5px 0 12px;
      font-weight: 500;
    }

    .product-description {
      font-size: 0.8rem;
      background: #fff8f2;
      padding: 8px 12px;
      border-radius: 28px;
      color: #6c4d38;
      margin: 8px 0 14px;
      line-height: 1.4;
      border-left: 3px solid #e7a06f;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      flex-wrap: wrap;
      margin-top: 6px;
    }

    .price {
      font-weight: 800;
      font-size: 1.6rem;
      color: #c45a2c;
      letter-spacing: -0.02em;
    }

    .price small {
      font-size: 0.7rem;
      font-weight: 500;
      color: #c58760;
    }

    .descriptive-cart-btn {
      background: linear-gradient(100deg, #c45a2c, #e37f48);
      border: none;
      border-radius: 60px;
      padding: 12px 8px;
      font-weight: 700;
      font-size: 0.75rem;
      color: white;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 18px;
      transition: 0.2s;
      box-shadow: 0 4px 12px rgba(196, 90, 44, 0.25);
    }

    .descriptive-cart-btn i {
      font-size: 0.8rem;
    }

    .descriptive-cart-btn:hover {
      transform: scale(0.96);
      background: #a8451c;
    }

    footer {
      text-align: center;
      font-size: 0.75rem;
      color: #cc9c7a;
      margin-top: 32px;
      padding-top: 18px;
      border-top: 1px solid #f7e3d5;
    }

    .design-credits {
      margin-top: 20px;
      text-align: center;
      font-size: 0.7rem;
      color: #bf8f6e;
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
  <div class="app-wrapper">
    <?php
    $base_url = '../';
    include __DIR__ . '/../includes/global-header.php';
    ?>
    <div class="search-panel">
      <div class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
      </div>
      <div class="sort-container" style="margin-bottom: 20px; text-align: right;">
        <select id="sortSelect" style="padding: 8px 16px; border-radius: 60px; border: 1px solid var(--border-color, #eee); font-family: inherit; font-size: 0.9rem; font-weight: 600; color: var(--text-dark, #333); outline: none;">
          <option value="default">Sort By - Default</option>
          <option value="price_low">Price: Low to High</option>
          <option value="price_high">Price: High to Low</option>
        </select>
      </div>
      <div class="filter-row">
        <div class="pill-group" id="filterPills">
          <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
          <div class="pill" data-category="skincare">Skincare / ಚರ್ಮದ ಆರೈಕೆ</div>
          <div class="pill" data-category="haircare">Hair Care / ಕೂದಲ ಆರೈಕೆ</div>
          <div class="pill" data-category="oral">Oral Care / ಹಲ್ಲಿನ ಆರೈಕೆ</div>
          <div class="pill" data-category="bath">Bath & Body / ಸ್ನಾನ ಮತ್ತು ದೇಹ</div>
          <div class="pill" data-category="mens">Men's Grooming / ಪುರುಷರ ಗ್ರೂಮಿಂಗ್</div>
        </div>
      </div>
    </div>
    <div class="product-grid" id="productGrid"></div>
    <div class="design-credits">
      <i class="fa-regular fa-compass"></i> design references:
      <a href="#" style="color:#c45a2c; text-decoration:none;">awwwards</a> ·
      <a href="#" style="color:#c45a2c; text-decoration:none;">siteInspire</a> ·
      <a href="#" style="color:#c45a2c; text-decoration:none;">minimal gallery</a>
      <span style="margin:0 5px">✦</span> warm minimalism · descriptive cart buttons
    </div>
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
      function categoryLabel(cat) {
        const map = {
          skincare: 'ಚರ್ಮದ ಆರೈಕೆ',
          haircare: 'ಕೂದಲ ಆರೈಕೆ',
          oral: 'ಹಲ್ಲಿನ ಆರೈಕೆ',
          bath: 'ಸ್ನಾನ ಮತ್ತು ದೇಹ',
          mens: 'ಪುರುಷರ ಗ್ರೂಮಿಂಗ್'
        };
        return map[cat] || '';
      }

      function generateProductDescription(name) {
        const n = name.toLowerCase();
        if (n.includes('moisturizer') || n.includes('cream')) return "🌿 Deep hydration · smooth, soft finish";
        if (n.includes('face wash') || n.includes('cleanser')) return "🧼 Gentle cleansing · removes dirt & oil";
        if (n.includes('shampoo')) return "💧 Nourishing scalp · healthy shine";
        if (n.includes('toothpaste')) return "🪥 Enamel strength · long-lasting fresh breath";
        if (n.includes('soap') || n.includes('bath') || n.includes('body wash')) return "🫧 Natural extracts · velvety skin";
        if (n.includes('oil') && (n.includes('hair') || n.includes('coconut'))) return "✨ Lustrous strands · root nourishment";
        if (n.includes('razor') || n.includes('shaving')) return "🪒 Close shave · no irritation";
        if (n.includes('serum')) return "🌟 Boosts radiance · anti-aging";
        if (n.includes('sunscreen')) return "☀️ SPF protection · daily shield";
        return "🍃 Ayurvedic essence · gentle care";
      }

      function getDescriptiveButtonText(name) {
        const n = name.toLowerCase();
        if (n.includes('nivea')) return "➕ Hydra soft";
        if (n.includes('pond')) return "➕ Light gel";
        if (n.includes('garnier')) return "➕ Vitamin glow";
        if (n.includes('himalaya')) return "➕ Neem fresh";
        if (n.includes('mamaearth')) return "➕ Organic care";
        if (n.includes('dove')) return "➕ Silk moisture";
        if (n.includes('gillette')) return "➕ Precision shave";
        if (n.includes('indulekha')) return "➕ Ayurveda herbs";
        if (n.includes('dabur')) return "➕ Herbal strength";
        if (n.includes('beardo')) return "➕ Beard comfort";
        return "➕ Add to kit";
      }
      // Augment products with description and button text, and ensure proper fields
      const products = productsData.map(p => {
        const nameEn = p.name; // already includes weight from DB or fallback
        const nameKn = p.kn || p.name + ' ' + (p.weight || '');
        return {
          id: p.id,
          nameEn: nameEn,
          nameKn: nameKn,
          category: p.category || 'other',
          price: p.price,
          image: p.imageUrl,
          description: generateProductDescription(nameEn),
          buttonText: getDescriptiveButtonText(nameEn)
        };
      });
      // ----- CART STATE (localStorage, but we also sync via AJAX to server) -----
      let cart = JSON.parse(localStorage.getItem('glowCartNew')) || [];
      let activeCategory = 'all';
      let searchTerm = '';
      const productGridEl = document.getElementById('productGrid');
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
        const filtered = products.filter(p => {
          if (activeCategory !== 'all' && p.category !== activeCategory) return false;
          const term = searchTerm.trim().toLowerCase();
          if (term) {
            return p.nameEn.toLowerCase().includes(term) || p.nameKn.toLowerCase().includes(term);
          }
          return true;
        });
        if (filtered.length === 0) {
          productGridEl.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:3rem; color:#bb8a69;">✨ No items found / ಯಾವುದೇ ಉತ್ಪನ್ನ ಇಲ್ಲ</div>`;
          return;
        }
        productGridEl.innerHTML = filtered.map(p => `
            <div class="product-card" data-id="${p.id}">
                <img class="product-img" src="${p.image}" alt="" loading="lazy" onerror="this.src='https://placehold.co/90x90/fef0e6/c45a2c?text=🌿'">
                <div class="product-title">${p.nameEn}</div>
                <div class="product-title-kn">${p.nameKn}</div>
                <div class="product-description"><i class="fa-regular fa-note-sticky"></i> ${p.description}</div>
                <div class="price-row">
                    <span class="price">₹${p.price} <small>inr</small></span>
                    <button class="descriptive-cart-btn" data-id="${p.id}" data-add>
                        <i class="fa-solid fa-seedling"></i>
                        <span>${p.buttonText}</span>
                    </button>
                </div>
            </div>
        `).join('');
        document.querySelectorAll('.descriptive-cart-btn').forEach(btn => {
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