<?php
// ============================================================
// index.php - Main entry point (REDESIGNED VERSION)
// ============================================================
require_once 'config.php';
require_once 'functions.php';

// Start session for cart
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Normalize every cart line to the expected shape {id,price,qty,...}.
// Legacy/foreign sessions or a bad write can leave a scalar (int/string) in the
// array, which later causes "Trying to access array offset on int".
$_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function ($item) {
    return is_array($item)
        && isset($item['id'])
        && isset($item['price'], $item['qty'])
        && is_numeric($item['price']) && is_numeric($item['qty']);
}));

// Get categories and products from database
$categories = getCategories();
$products = getProducts();

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'] ?? '';

    switch ($action) {
        case 'products':
            $cat = $_GET['category'] ?? '';
            $search = $_GET['search'] ?? '';
            $raw_products = getProducts($cat, $search);
            foreach ($raw_products as &$rp) {
                if (!empty($rp['images'])) {
                    $rp['images'] = normalizeImagePath($rp['images']);
                }
            }
            echo json_encode($raw_products, JSON_UNESCAPED_UNICODE);
            exit;

        case 'categories':
            echo json_encode(getCategories(), JSON_UNESCAPED_UNICODE);
            exit;

        case 'cart':
            echo json_encode($_SESSION['cart'], JSON_UNESCAPED_UNICODE);
            exit;

        case 'add_cart':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id) {
                $product = getProductById($id);
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
                            'kn' => $product['kn_name'],
                            'price' => (float) $product['price'],
                            'qty' => 1,
                            'icon' => $product['icon'] ?: 'fa-box',
                            'image' => !empty($product['images']) ? normalizeImagePath($product['images']) : ''
                        ];
                    }
                    echo json_encode(['success' => true, 'cart' => $_SESSION['cart']], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            echo json_encode(['success' => false]);
            exit;

        case 'update_cart':
            $id = $_POST['id'] ?? '';
            $qty = (int) ($_POST['qty'] ?? 0);
            if ($id !== '' && $qty > 0) {
                foreach ($_SESSION['cart'] as &$item) {
                    if ((string)$item['id'] === (string)$id) {
                        $item['qty'] = $qty;
                        break;
                    }
                }
            } elseif ($id !== '' && $qty == 0) {
                $_SESSION['cart'] = array_filter($_SESSION['cart'], function ($i) use ($id) {
                    return (string)$i['id'] !== (string)$id;
                });
            }
            echo json_encode(['success' => true, 'cart' => array_values($_SESSION['cart'])], JSON_UNESCAPED_UNICODE);
            exit;

        case 'remove_cart':
            $id = $_POST['id'] ?? '';
            if ($id !== '') {
                $_SESSION['cart'] = array_filter($_SESSION['cart'], function ($i) use ($id) {
                    return (string)$i['id'] !== (string)$id;
                });
                echo json_encode(['success' => true, 'cart' => array_values($_SESSION['cart'])], JSON_UNESCAPED_UNICODE);
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

// Get category filter from URL
$activeCategory = $_GET['category'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$products = getProducts($activeCategory, $searchTerm);
$mainCategories = getMainCategories();

// Get cart count
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(function ($i) {
    return $i['price'] * $i['qty'];
}, $_SESSION['cart']));

// User info (if logged in)
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';

// For Best Sellers, we'll just pick first 8 products
$bestSellers = array_slice($products, 0, 8);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes" />
    <title>KSR GURU JI · ನಮ್ಮ ಅಂಗಡಿ</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- Google Fonts: Inter + Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="index.css?v=2.0" />
    <!-- Lottie Web Player (self-hosted, ships source maps) -->
    <script src="vendor/lottie-player/lottie-player.js"></script>
</head>

<body>
    <!-- ============================================================
    HEADER (sticky glass)
    ============================================================ -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <!-- Logo -->
                <div class="logo-container" onclick="window.location.href='index.php'" style="cursor: pointer;">
                    <div class="logo-image">
                        <img src="company_logo.jpg" alt="KSR GURU JI Logo" style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;" onerror="this.src='company_logo.jpg';" />
                    </div>
                    <div class="logo-text">
                        <span class="logo-brand">KSR GURU JI</span>
                        <span class="logo-tagline">Everything Your Family Needs, Every Day</span>
                    </div>
                </div>

                <!-- Search -->
                <div class="search-bar">
                    <input type="text" placeholder="Search for products..." id="globalSearchInput"
                        value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button class="search-btn" id="searchBtn"><i class="fas fa-search"></i></button>
                </div>

                <!-- Nav Links -->
                <div class="nav-links" id="navLinks">
                    <a href="categories/air-freshner.php" class="nav-link"><i class="fas fa-spray-can-sparkles"></i> Air Fresheners</a>
                    <a href="categories/bakery.php" class="nav-link"><i class="fas fa-bread-slice"></i> Bakery</a>
                    <a href="categories/bath-body.php" class="nav-link"><i class="fas fa-bath"></i> Bath & Body</a>
                    <a href="categories/breakfast.php" class="nav-link"><i class="fas fa-egg"></i> Breakfast</a>
                    <a href="categories/cleaning.php" class="nav-link"><i class="fas fa-broom"></i> Cleaning</a>
                    <a href="categories/cold-drinks.php" class="nav-link"><i class="fas fa-glass-water"></i> Cold Drinks</a>
                    <a href="categories/cooking-oil.php" class="nav-link"><i class="fas fa-droplet"></i> Cooking Oil</a>
                    <a href="categories/dairy.php" class="nav-link"><i class="fas fa-cow"></i> Dairy</a>
                    <a href="categories/dishwash.php" class="nav-link"><i class="fas fa-soap"></i> Dishwash</a>
                    <a href="categories/dry-fruits.php" class="nav-link"><i class="fas fa-seedling"></i> Dry Fruits</a>
                    <a href="categories/grains.php" class="nav-link"><i class="fas fa-wheat-awn"></i> Grains</a>
                    <a href="categories/hair-care.php" class="nav-link"><i class="fas fa-scissors"></i> Hair Care</a>
                    <a href="categories/health-pharmacy.php" class="nav-link"><i class="fas fa-prescription-bottle-medical"></i> Health Care</a>
                    <a href="categories/home-care.php" class="nav-link"><i class="fas fa-house"></i> Home Care</a>
                    <a href="categories/kitchen.php" class="nav-link"><i class="fas fa-kitchen-set"></i> Kitchen Care</a>
                    <a href="categories/laundry.php" class="nav-link"><i class="fas fa-shirt"></i> Laundry</a>
                    <a href="categories/personal-care.php" class="nav-link"><i class="fas fa-spa"></i> Personal Care</a>
                    <a href="categories/pet-supplies.php" class="nav-link"><i class="fas fa-paw"></i> Pet Care</a>
                    <a href="categories/pules-lentils.php" class="nav-link"><i class="fas fa-bowl-rice"></i> Pulses</a>
                    <a href="categories/paper-products.php" class="nav-link"><i class="fas fa-toilet-paper"></i> Paper Products</a>
                    <a href="categories/pickles.php" class="nav-link"><i class="fas fa-jar"></i> Pickles</a>
                    <a href="categories/plastic-household.php" class="nav-link"><i class="fas fa-box"></i> Plastic Household</a>
                    <a href="categories/pooja.php" class="nav-link"><i class="fas fa-hands-praying"></i> Pooja</a>
                    <a href="categories/ready-to-eat.php" class="nav-link"><i class="fas fa-bowl-food"></i> Ready to Eat</a>
                    <a href="categories/seasonal.php" class="nav-link"><i class="fas fa-cloud-sun"></i> Seasonal</a>
                    <a href="categories/snacks.php" class="nav-link"><i class="fas fa-cookie-bite"></i> Snacks</a>
                    <a href="categories/spices.php" class="nav-link"><i class="fas fa-pepper-hot"></i> Spices</a>
                    <a href="categories/stationery.php" class="nav-link"><i class="fas fa-pencil"></i> Stationery</a>
                    <a href="categories/vegetables-fruits.php" class="nav-link"><i class="fas fa-carrot"></i> Vegetables & Fruits</a>
                    <!-- ★ Product & Medical Suggest Box -->
                    <a href="WhatsApp/telehealth/medicine/tablet.php" class="nav-link"><i class="fas fa-medkit"></i> Medicines</a>
                    <a href="final-suggestion-box.php" class="nav-link"><i class="fas fa-lightbulb"></i> Suggestion Box</a>
                    <a href="categories/parcels.php" class="nav-link"><i class="fas fa-box"></i> Parcel Delivery</a>
                    <a href="WhatsApp/telehealth/index.php" class="nav-link" style="color: #059669; font-weight:700;"><i class="fas fa-stethoscope"></i> Doctor Assist</a>

                </div>

                <!-- Header Actions (Visible on Mobile & Desktop) -->
                <div class="header-actions">
                    <!-- Theme Toggle -->
                    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                        <span id="themeIcon">🌙</span>
                    </button>

                    <!-- Cart Link -->
                    <a href="cart/cart.php" class="cart-link" id="cartLink">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-text">Cart</span>
                        <span class="cart-badge" id="cartCount"><?php echo $cartCount; ?></span>
                    </a>

                    <!-- User Dropdown -->
                    <div class="user-dropdown">
                        <button class="user-btn" aria-label="User Profile"><i class="fas fa-user"></i></button>
                        <div class="dropdown-content">
                            <?php if ($isLoggedIn): ?>
                                <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                                <!-- <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                                <a href="register.php"><i class="fas fa-user-plus"></i> Register</a> -->
                            <?php endif; ?>
                            <a href="admin/index.php"><i class="fas fa-user-cog"></i> Admin</a>
                            <a href="delivery/index.php"><i class="fas fa-truck"></i> Delivery</a>
                            <a href="patient-portal.php"><i class="fas fa-heart-pulse"></i> Patient Portal</a>

                        </div>
                    </div>

                    <!-- Support Link icon style -->
                    <a href="support.php" class="nav-link-support" title="Support"><i class="fas fa-headset"></i></a>

                    <!-- Hamburger button for mobile menu -->
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
                </div>
            </nav>
        </div>
    </header>

    <!-- ============================================================
    HERO BANNER SLIDER
    ============================================================ -->
    <section class="hero" id="heroSlider">
        <div class="hero-slider">
            <div class="hero-slides" id="heroSlides">
                <!-- Slide 1 -->
                <div class="hero-slide">
                    <div class="hero-content">
                        <div class="badge">🚀 Farm Fresh Delivery</div>
                        <h2>Fresh Groceries &amp; <br /><span>Daily Essentials</span></h2>
                        <p>Order before 2 PM for same-day delivery. Healthy choice, healthy life.</p>
                        <div class="hero-btns">
                            <a href="?category=27" class="btn btn-primary">Shop Now <i class="fas fa-arrow-right"></i></a>
                            <a href="delivery/index.php" class="btn btn-secondary">Delivery Info</a>
                        </div>
                    </div>
                    <div class="hero-image">
                        <img src="Banner.png" onerror="this.src='https://img.icons8.com/fluency/300/groceries.png'" alt="KSR GURU JI Delivery" />
                        <div class="floating-badge"><i class="fas fa-truck-fast" style="color:var(--color-primary);"></i> Free Delivery</div>
                    </div>
                </div>
                <!-- Slide 2 -->
                <div class="hero-slide">
                    <div class="hero-content">
                        <div class="badge">🔥 Hot Deals</div>
                        <h2>Up to 50% Off<br /><span>Household Staples</span></h2>
                        <p>Grab limited-time discounts on kitchen goods, curries, snacks and bakery items.</p>
                        <div class="hero-btns">
                            <a href="?category=28" class="btn btn-primary" style="background:var(--gradient-accent); box-shadow: 0 4px 16px rgba(232, 93, 44, 0.25);">Shop Deals <i class="fas fa-percent"></i></a>
                            <a href="final-suggest.php" class="btn btn-secondary">Suggest Product</a>
                        </div>
                    </div>
                    <div class="hero-image">
                        <img src="kitechen all product.jpg" onerror="this.src='https://img.icons8.com/fluency/300/groceries.png'" alt="Groceries & Fruits" />
                        <div class="floating-badge"><i class="fas fa-tag" style="color:var(--color-accent);"></i> Premium Quality</div>
                    </div>
                </div>
                <!-- Slide 3 -->
                <div class="hero-slide">
                    <div class="hero-content">
                        <div class="badge">🎂 Bakery Special</div>
                        <h2>Celebrate Life with<br /><span>Homemade Cakes</span></h2>
                        <p>Order custom chocolate, vanilla, and wedding cakes freshly baked just for you.</p>
                        <div class="hero-btns">
                            <a href="categories/bakery.php" class="btn btn-primary">Bakery Shop <i class="fas fa-birthday-cake"></i></a>
                            <a href="track-order.php" class="btn btn-secondary">Track Order</a>
                        </div>
                    </div>
                    <div class="hero-image">
                        <img src="cake.jpg" alt="Bakery Cakes" onerror="this.src='https://img.icons8.com/fluency/300/pastry.png'" />
                        <div class="floating-badge"><i class="fas fa-star" style="color:var(--color-accent);"></i> Made to Order</div>
                    </div>
                </div>
            </div>

            <div class="slider-dots" id="sliderDots">
                <span class="active" data-index="0"></span>
                <span data-index="1"></span>
                <span data-index="2"></span>
            </div>
            <div class="slider-controls">
                <button id="prevSlide">‹</button>
                <button id="nextSlide">›</button>
            </div>
        </div>
    </section>

    <!-- ============================================================
    QUICK SERVICE SECTION
    ============================================================ -->
    <div class="container">
        <section class="quick-services">
            <div class="service-item">
                <div class="icon">🚀</div>
                <h4>Free Delivery</h4>
                <p>On all orders above ₹499</p>
            </div>
            <div class="service-item">
                <div class="icon">🔄</div>
                <h4>Easy Returns</h4>
                <p>No questions asked policy</p>
            </div>
            <div class="service-item">
                <div class="icon">💰</div>
                <h4>Best Prices</h4>
                <p>Guaranteed lower than market</p>
            </div>
            <div class="service-item">
                <div class="icon">🥝</div>
                <h4>100% Fresh</h4>
                <p>Sourced daily from farms</p>
            </div>
            <div class="service-item">
                <div class="icon">☎️</div>
                <h4>24/7 Support</h4>
                <p>Get instant chat assistance</p>
            </div>
        </section>
    </div>

    <!-- ============================================================
    BEST SELLERS (NEW SECTION)
    ============================================================ -->
    <?php if (!empty($bestSellers)): ?>
        <section class="best-sellers">
            <div class="container">
                <h2 class="section-title">🔥 Best Sellers · ಉತ್ತಮ ಮಾರಾಟ</h2>
                <div class="best-sellers-grid">
                    <?php foreach ($bestSellers as $p):
                        $imgPath = !empty($p['images']) ? normalizeImagePath($p['images']) : '';
                    ?>
                        <div class="best-card fade-up" onclick="openQuickView('<?php echo htmlspecialchars(addslashes($p['name']), ENT_QUOTES, 'UTF-8'); ?>', '₹<?php echo number_format($p['price'], 2); ?>', '<?php echo htmlspecialchars($p['icon'] ?? 'fa-box', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo $p['id']; ?>')">
                            <button class="wishlist-btn" onclick="toggleWishlist(this, event)"><i class="far fa-heart"></i></button>
                            <span class="badge">⭐ Best Seller</span>
                            <div class="product-img">
                                <?php if (!empty($imgPath)): ?>
                                    <img src="<?php echo htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.outerHTML='<i class=\'fas <?php echo htmlspecialchars($p['icon'] ?? "fa-box", ENT_QUOTES, "UTF-8"); ?>\'></i>'" />
                                <?php else: ?>
                                    <i class="fas <?php echo htmlspecialchars($p['icon'] ?? 'fa-box', ENT_QUOTES, 'UTF-8'); ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div class="product-name"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="product-category"><?php echo htmlspecialchars($p['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <span class="price">₹<?php echo number_format($p['price'], 2); ?></span>
                            <button class="add-btn" data-id="<?php echo $p['id']; ?>">
                                <i class="fas fa-cart-plus"></i> ಸೇರಿಸು
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ============================================================
    CATEGORIES (compact)
    ============================================================ -->
    <section class="categories">
        <div class="container">
            <div class="section-label">
                <h2 class="section-title">Shop by Category · ವಿಭಾಗಗಳು</h2>
                <small><a href="?">✕ ಎಲ್ಲ ತೆರವು</a></small>
            </div>
            <div class="categories-grid" id="categoriesContainer">
                <?php foreach ($mainCategories as $mainCat): ?>
                    <div class="category-group-title" data-main="<?php echo htmlspecialchars($mainCat['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="emoji"><?php echo htmlspecialchars($mainCat['emoji'] ?? '📦', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo htmlspecialchars($mainCat['name'], ENT_QUOTES, 'UTF-8'); ?> ·
                            <?php echo htmlspecialchars($mainCat['kn'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php
                    $subIds = array_map('trim', explode(',', $mainCat['sub_ids'] ?? ''));
                    $subCats = array_filter($categories, function ($c) use ($subIds) {
                        return in_array($c['id'], $subIds);
                    });
                    foreach ($subCats as $subCat):
                        $active = ($activeCategory == $subCat['id']) ? 'active' : '';
                    ?>
                        <a href="?category=<?php echo urlencode($subCat['id']); ?>"
                            class="category-card <?php echo $active; ?>">
                            <i class="fas <?php echo htmlspecialchars($subCat['icon'] ?? 'fa-box', ENT_QUOTES, 'UTF-8'); ?>"></i>
                            <div class="category-english"><?php echo htmlspecialchars($subCat['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="category-kannada"><?php echo htmlspecialchars($subCat['kn_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
    ADVANCED FILTER & SORT TOOLBAR
    ============================================================ -->
    <div class="container">
        <div class="filter-toolbar" id="filterToolbar">
            <div class="filter-row">
                <!-- Category Filter -->
                <div class="filter-chip">
                    <i class="fas fa-filter"></i>
                    <select id="categoryFilterSelect">
                        <option value="">ಎಲ್ಲ ವಿಭಾಗ (All Categories)</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($activeCategory == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars($cat['kn_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sort Options -->
                <div class="filter-chip">
                    <i class="fas fa-sort"></i>
                    <select id="sortSelect">
                        <option value="default">Sort By - Default · ಡೀಫಾಲ್ಟ್</option>
                        <option value="price_low">Price: Low → High</option>
                        <option value="price_high">Price: High → Low</option>
                        <option value="a_z">Name: A → Z</option>
                        <option value="z_a">Name: Z → A</option>
                        <option value="newest">Newest First</option>
                        <option value="popularity">Most Popular</option>
                    </select>
                </div>

                <!-- Price Range Filter -->
                <div class="filter-chip filter-chip-price">
                    <i class="fas fa-indian-rupee-sign"></i>
                    <input type="number" id="priceMin" placeholder="Min ₹" min="0" style="width:70px;border:none;background:transparent;outline:none;font-weight:600;color:var(--color-primary);font-family:inherit;">
                    <span style="color:var(--text-muted);">—</span>
                    <input type="number" id="priceMax" placeholder="Max ₹" min="0" style="width:70px;border:none;background:transparent;outline:none;font-weight:600;color:var(--color-primary);font-family:inherit;">
                    <button id="applyPriceFilter" style="background:var(--gradient-primary);color:white;border:none;padding:4px 12px;border-radius:20px;font-weight:700;font-size:0.7rem;cursor:pointer;">Go</button>
                </div>

                <!-- Product Count -->
                <div class="filter-chip">
                    <i class="fas fa-boxes-stacked"></i>
                    <span id="productCountLabel"><?php echo count($products); ?> Products</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
    AI SHOWCASE
    ============================================================ -->
    <section class="ai-showcase">
        <div class="container">
            <h2 class="section-title">🤖 AI Smart Picks · just for you</h2>
            <?php
            $featured = getFeaturedProduct();
            if ($featured):
                $featuredImg = !empty($featured['images']) ? normalizeImagePath($featured['images']) : '';
            ?>
                <div class="product-main" id="aiMainProduct">
                    <div class="product-main-image">
                        <?php if (!empty($featuredImg)): ?>
                            <img src="<?php echo htmlspecialchars($featuredImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($featured['name'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.outerHTML='<?php echo htmlspecialchars($featured['emoji'] ?? '📦', ENT_QUOTES, 'UTF-8'); ?>'" style="max-width:100%; max-height:100%; object-fit:contain; border-radius:12px;" />
                        <?php else: ?>
                            <?php echo htmlspecialchars($featured['emoji'] ?? '📦', ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="product-main-details">
                        <div class="product-main-category">
                            <?php echo htmlspecialchars(strtoupper($featured['category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="product-main-name"><?php echo htmlspecialchars($featured['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="product-main-price">₹<?php echo number_format($featured['price'], 2); ?></div>
                        <button class="btn-add-large" data-id="<?php echo $featured['id']; ?>">
                            <i class="fas fa-cart-plus"></i> Add to cart
                        </button>
                        <p style="color:var(--text-muted); margin-top:1rem;"><i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($featured['kn_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <h3 style="font-size:1.4rem; margin: 2.5rem 0 1.2rem; color: var(--text-dark); font-weight: 700; letter-spacing:-0.3px;">
                Recommended for you
            </h3>
            <div class="rec-grid" id="aiRecGrid">
                <?php
                $recs = getRecommendations($featured['id'] ?? 0, 4);
                foreach ($recs as $rec):
                    $recImg = !empty($rec['images']) ? normalizeImagePath($rec['images']) : '';
                ?>
                    <div class="rec-card" data-id="<?php echo $rec['id']; ?>" onclick="openQuickView('<?php echo htmlspecialchars(addslashes($rec['name']), ENT_QUOTES, 'UTF-8'); ?>', '₹<?php echo number_format($rec['price'], 2); ?>', '<?php echo htmlspecialchars($rec['icon'] ?? 'fa-box', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($recImg, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo $rec['id']; ?>')" style="cursor:pointer;">
                        <div class="rec-emoji">
                            <?php if (!empty($recImg)): ?>
                                <img src="<?php echo htmlspecialchars($recImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($rec['name'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.outerHTML='<?php echo htmlspecialchars($rec['emoji'] ?? '📦', ENT_QUOTES, 'UTF-8'); ?>'" style="max-width:100%; max-height:100%; object-fit:contain; border-radius:8px;" />
                            <?php else: ?>
                                <?php echo htmlspecialchars($rec['emoji'] ?? '📦', ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                        </div>
                        <h4><?php echo htmlspecialchars($rec['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                        <p>₹<?php echo number_format($rec['price'], 2); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================
    STATS COUNTER SECTION
    ============================================================ -->
    <div class="container">
        <section style="padding: 40px 0 20px;">
            <h2 class="section-title">🏆 Trusted by Thousands · ಸಾವಿರಾರು ಜನ ನಂಬಿದ ಅಂಗಡಿ</h2>
            <div class="stats-grid" id="statsGrid">
                <div class="stat-item fade-up">
                    <div class="number" data-target="5000">0</div>
                    <div style="font-size:0.85rem;color:var(--text-muted);font-weight:600;margin-top:4px;">Happy Customers</div>
                </div>
                <div class="stat-item fade-up">
                    <div class="number" data-target="1200">0</div>
                    <div style="font-size:0.85rem;color:var(--text-muted);font-weight:600;margin-top:4px;">Products Available</div>
                </div>
                <div class="stat-item fade-up">
                    <div class="number" data-target="15000">0</div>
                    <div style="font-size:0.85rem;color:var(--text-muted);font-weight:600;margin-top:4px;">Orders Delivered</div>
                </div>
                <div class="stat-item fade-up">
                    <div class="number" data-target="4">0</div>
                    <div style="font-size:0.85rem;color:var(--text-muted);font-weight:600;margin-top:4px;">Years of Service</div>
                </div>
            </div>
        </section>
    </div>

    <!-- ============================================================
    CUSTOMER REVIEWS / TESTIMONIALS
    ============================================================ -->
    <section style="padding: 40px 0;">
        <div class="container">
            <h2 class="section-title">⭐ Customer Reviews · ಗ್ರಾಹಕ ಅಭಿಪ್ರಾಯಗಳು</h2>
            <div class="review-grid">
                <div class="review-card fade-up">
                    <div class="stars">★★★★★</div>
                    <p>"Excellent quality products! Got my groceries delivered within 2 hours. The fresh vegetables were amazing — just like from the farm."</p>
                    <div class="reviewer">
                        <div style="width:40px;height:40px;border-radius:50%;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:1rem;">S</div>
                        <div>
                            <div class="name">Suresh Kumar</div>
                            <div style="font-size:0.75rem;color:var(--text-muted);">Gajendragad</div>
                        </div>
                    </div>
                </div>
                <div class="review-card fade-up">
                    <div class="stars">★★★★★</div>
                    <p>"ಬಹಳ ಒಳ್ಳೆಯ ಸೇವೆ! ಬೆಲೆ ಕಡಿಮೆ, ಗುಣಮಟ್ಟ ಉತ್ತಮ. KSR GURU JI ನಮ್ಮ ಮನೆಯ ದೈನಂದಿನ ಅಗತ್ಯಗಳಿಗೆ ಪ್ರಮುಖ ಆಧಾರ ಆಗಿದೆ."</p>
                    <div class="reviewer">
                        <div style="width:40px;height:40px;border-radius:50%;background:var(--gradient-accent);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:1rem;">L</div>
                        <div>
                            <div class="name">Lakshmi Devi</div>
                            <div style="font-size:0.75rem;color:var(--text-muted);">ಗಜೇಂದ್ರಗಡ</div>
                        </div>
                    </div>
                </div>
                <div class="review-card fade-up">
                    <div class="stars">★★★★★</div>
                    <p>"The Doctor Assist feature helped me get medicine advice quickly. The emergency delivery was a lifesaver for my elderly parents. Truly great service!"</p>
                    <div class="reviewer">
                        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:1rem;">R</div>
                        <div>
                            <div class="name">Rajesh Patil</div>
                            <div style="font-size:0.75rem;color:var(--text-muted);">Gajendragad</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
    APP DOWNLOAD BANNER
    ============================================================ -->
    <div class="container">
        <div class="app-banner fade-up">
            <div>
                <h2>📱 Order via WhatsApp · ಸುಲಭ ಆದೇಶ</h2>
                <p style="margin-top:8px;opacity:.9;">Chat with us on WhatsApp and place your order in seconds. No app download needed!</p>
            </div>
            <div class="app-buttons">
                <a href="https://wa.me/918088584421?text=Hi%20KSR%20GURU%20JI%2C%20I%20want%20to%20place%20an%20order" target="_blank" rel="noopener">
                    <i class="fab fa-whatsapp"></i> Order on WhatsApp
                </a>
                <a href="tel:8088584421">
                    <i class="fas fa-phone"></i> Call Us Now
                </a>
            </div>
        </div>
    </div>

    <!-- ============================================================
    NEWSLETTER / NOTIFICATIONS SIGNUP
    ============================================================ -->
    <div class="container">
        <div class="newsletter fade-up">
            <h2>🔔 Get Daily Deals Alerts · ಡೀಲ್ ಅಲರ್ಟ್</h2>
            <p style="color:var(--text-muted);margin-top:8px;">Subscribe via WhatsApp for exclusive discounts, fresh stock alerts, and special offers.</p>
            <div class="input-group" style="margin-top:20px;">
                <input type="tel" id="newsletterPhone" placeholder="Enter your mobile number" maxlength="10" />
                <button onclick="subscribeNewsletter()" style="background:var(--gradient-primary);color:white;border:none;padding:12px 28px;border-radius:var(--radius-btn);font-weight:700;cursor:pointer;transition:var(--transition);">
                    <i class="fab fa-whatsapp"></i> Subscribe
                </button>
            </div>
            <p style="font-size:0.75rem;color:var(--text-muted);margin-top:10px;">We respect your privacy. Unsubscribe anytime.</p>
        </div>
    </div>

    <!-- ============================================================
    PRODUCT GRID WITH PAGINATION
    ============================================================ -->
    <div class="container" style="padding-bottom:2rem;">
        <div class="section-label">
            <h2 class="section-title">ಉತ್ಪನ್ನಗಳು · Products</h2>
            <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Showing <span id="pageRange">1-8</span> of <span id="totalProductsCount"><?php echo count($products); ?></span></span>
        </div>
        <div class="products-grid" id="productsGrid">
            <?php if (empty($products)): ?>
                <div style="grid-column:1/-1; text-align:center; padding:40px 0; color:var(--text-muted);">
                    <i class="fas fa-box-open" style="font-size:2.4rem;display:block;margin-bottom:12px;color:var(--text-light);"></i>
                    ಯಾವುದೇ ಉತ್ಪನ್ನಗಳಿಲ್ಲ · No products
                </div>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <?php
                    $imgPath = !empty($p['images']) ? normalizeImagePath($p['images']) : '';
                    ?>
                    <div class="product-card fade-up"
                        data-price="<?php echo (float)$p['price']; ?>"
                        data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-id="<?php echo $p['id']; ?>"
                        onclick="openQuickView('<?php echo htmlspecialchars(addslashes($p['name']), ENT_QUOTES, 'UTF-8'); ?>', '₹<?php echo number_format($p['price'], 2); ?>', '<?php echo htmlspecialchars($p['icon'] ?? 'fa-box', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo $p['id']; ?>')">
                        <button class="wishlist-btn" onclick="toggleWishlist(this, event)"><i class="far fa-heart"></i></button>
                        <div class="product-img">
                            <?php if (!empty($imgPath)): ?>
                                <img src="<?php echo htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.outerHTML='<i class=\'fas <?php echo htmlspecialchars($p['icon'] ?? "fa-box", ENT_QUOTES, "UTF-8"); ?>\'></i>'" />
                            <?php else: ?>
                                <i class="fas <?php echo htmlspecialchars($p['icon'] ?? 'fa-box', ENT_QUOTES, 'UTF-8'); ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="product-name">
                            <span><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <small><?php echo htmlspecialchars($p['kn_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                        </div>
                        <div class="product-category"><?php echo htmlspecialchars($p['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="product-footer" onclick="event.stopPropagation()">
                            <span class="price">₹<?php echo number_format($p['price'], 2); ?></span>
                            <button class="add-btn" data-id="<?php echo $p['id']; ?>">
                                <i class="fas fa-cart-plus"></i> ಸೇರಿಸು
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-controls" id="paginationControls">
            <button class="page-btn" id="prevPage" disabled><i class="fas fa-chevron-left"></i> Previous</button>
            <div class="page-numbers" id="pageNumbers"></div>
            <button class="page-btn" id="nextPage">Next <i class="fas fa-chevron-right"></i></button>
        </div>
    </div>

    <!-- ============================================================
    RELATED PAGES / CATEGORIES SECTION
    ============================================================ -->
    <section class="related-pages">
        <div class="container">
            <h2 class="section-title"><i class="fas fa-compass" style="color:var(--color-primary);margin-right:8px;"></i>Explore More · ಇನ್ನಷ್ಟು ಅನ್ವೇಷಿಸಿ</h2>
            <div class="related-grid">
                <a href="categories/vegetables-fruits.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-carrot"></i></div>
                    <span>Vegetables & Fruits</span>
                </a>
                <a href="categories/bakery.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-bread-slice"></i></div>
                    <span>Bakery</span>
                </a>
                <a href="categories/dry-fruits.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-seedling"></i></div>
                    <span>Dry Fruits</span>
                </a>
                <a href="categories/spices.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-pepper-hot"></i></div>
                    <span>Spices & Masalas</span>
                </a>
                <a href="categories/grains.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-wheat-awn"></i></div>
                    <span>Grains</span>
                </a>
                <a href="categories/cooking-oil.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-droplet"></i></div>
                    <span>Cooking Oil</span>
                </a>
                <a href="categories/cleaning.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-broom"></i></div>
                    <span>Cleaning</span>
                </a>
                <a href="categories/personal-care.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-spa"></i></div>
                    <span>Personal Care</span>
                </a>
                <a href="categories/snacks.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-cookie-bite"></i></div>
                    <span>Snacks</span>
                </a>
                <a href="categories/dairy.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-cow"></i></div>
                    <span>Dairy</span>
                </a>

                <a href="support.php" class="related-card">
                    <div class="related-icon"><i class="fas fa-headset"></i></div>
                    <span>Support</span>
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================================
    INSPIRATIONAL QUOTES SECTION
    ============================================================ -->
    <section class="quotes-section">
        <div class="container">
            <div class="quote-card" id="quoteCard">
                <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                <blockquote id="quoteText">"The food you eat can be either the safest and most powerful form of medicine or the slowest form of poison."</blockquote>
                <cite id="quoteAuthor">— Ann Wigmore</cite>
            </div>
        </div>
    </section>

    <!-- ============================================================
    DELIVERY FEATURES
    ============================================================ -->
    <section class="delivery-features" id="delivery">
        <div class="container">
            <h2 class="section-title">Smart Delivery System</h2>
            <div class="features-grid">
                <div class="feature-card"><i class="fas fa-truck-fast"></i>
                    <h3>Same-day</h3>
                    <p>Order by 2 PM</p>
                </div>
                <div class="feature-card"><i class="fas fa-clock"></i>
                    <h3>Time-slot</h3>
                    <p>Choose slot</p>
                </div>
                <div class="feature-card"><i class="fas fa-ambulance"></i>
                    <h3>Emergency</h3>
                    <p>24/7 delivery</p>
                </div>
                <div class="feature-card"><i class="fas fa-map-marker-alt"></i>
                    <h3>Live Tracking</h3>
                    <p>Real-time</p>
                </div>
                <div class="feature-card"><i class="fab fa-whatsapp"></i>
                    <h3>WhatsApp</h3>
                    <p>Notifications</p>
                </div>
                <div class="feature-card"><i class="fas fa-sms"></i>
                    <h3>SMS Alerts</h3>
                    <p>Updates</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
    EMERGENCY BANNER
    ============================================================ -->
    <section class="emergency-banner">
        <div class="container">
            <div class="emergency-content">
                <div class="emergency-icon">🚨</div>
                <div>
                    <h2>Emergency Medicine Delivery</h2>
                    <p>Need medicines urgently? Call us now for 60-minute delivery*</p>
                    <a href="tel:8088584421" class="btn-emergency"><i class="fas fa-phone"></i> Call Now: 8088584421, 8762474406, 9590902742</a>
                    <p style="margin-top:8px; font-size:.78rem; opacity:.85">Delivery agents · <a href="delivery/login.php" style="color:#fff; text-decoration:underline">Agent login</a></p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
    SUPPORT
    ============================================================ -->
    <section class="support-section">
        <div class="container">
            <div class="support-content">
                <h2>24/7 Customer Support</h2>
                <p>We're here to help you with any queries or issues</p>
                <div class="support-contacts">
                    <div class="contact-card"><i class="fas fa-phone"></i>
                        <h4>Call Support</h4>
                        <p><a href="tel:8088584421">8088584421</a> · <a href="tel:8762474406">8762474406</a> · <a href="tel:9590902742">9590902742</a></p>
                    </div>
                    <div class="contact-card"><i class="fas fa-comments"></i>
                        <h4>In-app Chat</h4>
                        <p>Available 24/7</p><small>Powered by Genmine AI</small>
                    </div>
                    <div class="contact-card"><i class="fas fa-undo"></i>
                        <h4>Easy Returns</h4>
                        <p>Simple refund process</p><small>Call: 8088584421</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
    FOOTER
    ============================================================ -->
    <?php
    $base_url = '';
    include 'includes/user-footer.php';
    ?>


    <!-- ============================================================
    WHATSAPP & CHAT
    ============================================================ -->
    <div class="ksr-whatsapp-quick-action"
        onclick="window.open('https://wa.me/918088584421?text=Hi%20KSR%20GURU%2C%20I%20need%20help%20with%20my%20order','_blank')">
        <i class="fab fa-whatsapp fa-lg"></i><span>Order via WhatsApp</span>
    </div>
    <div class="chat-widget">
        <div class="chat-toggle" id="chatToggle"><i class="fas fa-comment-dots"></i></div>
        <div class="chat-box hidden" id="chatBox">
            <div class="chat-header"><i class="fas fa-headset"></i> KSR GURU JI AI <span>● online</span></div>
            <div class="chat-messages" id="chatMessages">
                <div class="message bot">
                    <div class="avatar"><i class="fas fa-robot"></i></div>
                    <div class="bubble">Hi! I'm your AI assistant. Ask about shipping, returns, or discounts.</div>
                </div>
            </div>
            <div class="chat-input-area"><input type="text" id="chatInput" placeholder="Type a message..." /><button id="sendChat"><i class="fas fa-paper-plane"></i></button></div>
        </div>
    </div>

    <!-- ============================================================
    SCROLL TO TOP & QUICK VIEW MODAL
    ============================================================ -->
    <button class="scroll-top" id="scrollTopBtn" aria-label="Scroll to top"><i class="fas fa-chevron-up"></i></button>

    <div class="quick-view-modal" id="quickViewModal">
        <div class="quick-view-content">
            <button class="close-modal" id="closeQuickView">&times;</button>
            <div id="quickViewImg" style="font-size: 6rem; background: var(--color-primary-light); border-radius: 12px; height: 180px; display: flex; align-items:center; justify-content:center; color: var(--color-primary); margin-bottom: 20px;">
                <i class="fas fa-box"></i>
            </div>
            <h3 id="quickViewName">Product Name</h3>
            <div class="qv-price" id="quickViewPrice">₹0.00</div>
            <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 0.9rem;">High quality product freshly sourced and packaged matching strict hygiene standard norms.</p>
            <button class="btn-add-large" id="quickViewAddBtn" style="margin: 0 auto;">Add to Cart</button>
        </div>
    </div>

    <!-- ============================================================
    CART MODAL
    ============================================================ -->
    <div class="modal-overlay" id="cartModal">
        <div class="cart-modal">
            <div class="cart-header">
                <span>ನಿಮ್ಮ ಬಂಡಿ · Your Cart</span>
                <button class="close-modal" id="closeModalBtn">&times;</button>
            </div>
            <div id="cartItemsContainer"></div>
            <div class="cart-total" id="cartTotal"></div>
            <div class="cart-modal-footer" id="cartModalFooter">
                <button class="btn-modal-checkout" id="modalCheckoutBtn">
                    <i class="fas fa-credit-card"></i> Proceed to Checkout / ಚೆಕ್-ಔಟ್
                </button>
                <a href="cart/cart.php" class="btn-modal-view-cart">
                    <i class="fas fa-shopping-cart"></i> View Detailed Cart / ಬಂಡಿ ನೋಡಿ
                </a>
            </div>
        </div>
    </div>

    <!-- ============================================================
    JAVASCRIPT – AJAX + animations
    ============================================================ -->
    <script>
        (function() {
            // ----- STATE -----
            let cart = [];
            let cartCount = <?php echo $cartCount; ?>;
            let currentCategory = '<?php echo addslashes($activeCategory); ?>';
            let searchTerm = '<?php echo addslashes($searchTerm); ?>';

            // ----- DOM refs -----
            const productsGrid = document.getElementById('productsGrid');
            const cartModal = document.getElementById('cartModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const cartCountBadge = document.getElementById('cartCount');
            const cartItemsContainer = document.getElementById('cartItemsContainer');
            const cartTotalSpan = document.getElementById('cartTotal');
            const globalSearchInput = document.getElementById('globalSearchInput');
            const searchBtn = document.getElementById('searchBtn');
            const categoryFilterSelect = document.getElementById('categoryFilterSelect');
            const cartLink = document.getElementById('cartLink');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const navLinks = document.getElementById('navLinks');

            // ----- Load cart from server -----
            function loadCart() {
                fetch('index.php?ajax=cart')
                    .then(res => res.json())
                    .then(data => {
                        cart = data;
                        cartCount = cart.reduce((acc, i) => acc + i.qty, 0);
                        updateCartBadge();
                        renderCartModal();
                    })
                    .catch(console.error);
            }

            // ----- Update cart badge -----
            function updateCartBadge() {
                if (cartCountBadge) cartCountBadge.innerText = cartCount;
            }

            // ----- Add to cart -----
            function addToCart(productId) {
                const formData = new FormData();
                formData.append('id', productId);
                fetch('index.php?ajax=add_cart', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            cart = data.cart;
                            cartCount = cart.reduce((acc, i) => acc + i.qty, 0);
                            updateCartBadge();
                            renderCartModal();
                            // Feedback
                            const btn = document.querySelector(`.add-btn[data-id="${productId}"]`) ||
                                document.querySelector(`.btn-add-large[data-id="${productId}"]`);
                            if (btn) {
                                const orig = btn.innerHTML;
                                btn.innerHTML = '✓ Added';
                                setTimeout(() => btn.innerHTML = orig, 1200);
                            }
                            // Open cart modal drawer instead of page redirect
                            setTimeout(() => {
                                openCartModal();
                            }, 600);
                        }
                    })
                    .catch(console.error);
            }

            // ----- Normalize image path in JS (Windows -> web-relative) -----
            function normalizeImagePathJS(rawPath) {
                if (!rawPath) return '';
                let clean = rawPath.replace(/\\/g, '/');
                const idx = clean.toLowerCase().indexOf('images/');
                if (idx !== -1) {
                    clean = clean.substring(idx);
                } else {
                    const parts = clean.split('/');
                    clean = 'images/' + parts[parts.length - 1];
                }
                clean = clean.replace(/%(?!25)/g, '%25');
                const path = window.location.pathname;
                if (path.includes('/categories/') || path.includes('/cart/') || path.includes('/checkout/') || path.includes('/delivery/') || path.includes('/admin/')) {
                    return '../' + clean;
                }
                return clean;
            }

            // ----- Render cart modal -----
            function renderCartModal() {
                const footerEl = document.getElementById('cartModalFooter');
                if (!cartItemsContainer) return;
                if (cart.length === 0) {
                    cartItemsContainer.innerHTML =
                        `<div class="empty-cart"><i class="fas fa-bag-shopping"></i><p>ಬಂಡಿ ಖಾಲಿ · Cart empty</p></div>`;
                    cartTotalSpan.innerText = 'ಒಟ್ಟು ₹0';
                    if (footerEl) footerEl.style.display = 'none';
                    return;
                }
                let itemsHtml = '',
                    total = 0;
                cart.forEach(item => {
                    total += item.price * item.qty;
                    const icon = item.icon || 'fa-box';
                    const itemImgSrc = item.image ? normalizeImagePathJS(item.image) : '';
                    const imgHtml = itemImgSrc ?
                        `<img src="${itemImgSrc}" alt="${item.name}" onerror="this.outerHTML='<i class=\\'fas ${icon}\\'></i>'" style="width:100%; height:100%; object-fit:cover; border-radius:4px;" />` :
                        `<i class="fas ${icon}"></i>`;
                    itemsHtml += `<div class="cart-item">
                            <div class="cart-item-img">${imgHtml}</div>
                            <div class="cart-item-details">
                                <div class="cart-item-title">${item.name} <span class="kannada">${item.kn || ''}</span></div>
                                <div class="cart-item-price">₹${item.price}</div>
                                <div class="cart-qty">
                                    <button class="qty-decr" data-id="${item.id}">−</button>
                                    <span>${item.qty}</span>
                                    <button class="qty-incr" data-id="${item.id}">+</button>
                                    <button class="qty-remove" data-id="${item.id}"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>`;
                });
                cartItemsContainer.innerHTML = itemsHtml;
                cartTotalSpan.innerText = `ಒಟ್ಟು ₹${total.toFixed(2)}`;
                if (footerEl) footerEl.style.display = 'flex';
            }

            // ----- Handle cart qty actions -----
            function handleCartQty(e) {
                const btn = e.target.closest('button');
                if (!btn || !btn.dataset.id) return;
                const id = btn.dataset.id;
                const item = cart.find(i => i.id == id);
                if (!item) return;

                let newQty = item.qty;
                if (btn.classList.contains('qty-incr')) newQty++;
                else if (btn.classList.contains('qty-decr')) newQty--;
                else if (btn.classList.contains('qty-remove')) newQty = 0;

                const formData = new FormData();
                formData.append('id', id);
                formData.append('qty', newQty);
                fetch('index.php?ajax=update_cart', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            cart = data.cart;
                            cartCount = cart.reduce((acc, i) => acc + i.qty, 0);
                            updateCartBadge();
                            renderCartModal();
                        }
                    })
                    .catch(console.error);
            }

            // ----- Open/Close cart modal -----
            function openCartModal() {
                cartModal.classList.add('show');
                loadCart();
            }

            function closeCartModal() {
                cartModal.classList.remove('show');
            }

            // ----- Load products via AJAX -----
            function loadProducts(category, search) {
                const params = new URLSearchParams();
                params.append('ajax', 'products');
                if (category) params.append('category', category);
                if (search) params.append('search', search);

                fetch('index.php?' + params.toString())
                    .then(res => res.json())
                    .then(data => {
                        if (data.length === 0) {
                            productsGrid.innerHTML =
                                `<div style="grid-column:1/-1; text-align:center; padding:40px 0; color:var(--text-muted);"><i class="fas fa-box-open" style="font-size:2.4rem;display:block;margin-bottom:12px;color:var(--text-light);"></i>ಯಾವುದೇ ಉತ್ಪನ್ನಗಳಿಲ್ಲ · No products</div>`;
                            return;
                        }
                        let html = '';
                        data.forEach(p => {
                            const imgPath = p.images || '';
                            let imgHtml = '';
                            if (imgPath) {
                                imgHtml = `<img src="${imgPath}" alt="${p.name.replace(/"/g, '&quot;')}" onerror="this.outerHTML='<i class=\\'fas ${p.icon || 'fa-box'}\\'></i>'" />`;
                            } else {
                                imgHtml = `<i class="fas ${p.icon || 'fa-box'}"></i>`;
                            }
                            const nameEsc = p.name.replace(/'/g, "\\'");
                            const imgPathEsc = imgPath.replace(/'/g, "\\'");
                            html += `<div class="product-card fade-up" onclick="openQuickView('${nameEsc}', '₹${parseFloat(p.price).toFixed(2)}', '${p.icon || 'fa-box'}', '${imgPathEsc}', '${p.id}')">
                                    <button class="wishlist-btn" onclick="toggleWishlist(this, event)"><i class="far fa-heart"></i></button>
                                    <div class="product-img">${imgHtml}</div>
                                    <div class="product-name">
                                        <span>${p.name}</span>
                                        <small>${p.kn_name || ''}</small>
                                    </div>
                                    <div class="product-category">${p.category_name || ''}</div>
                                    <div class="product-footer" onclick="event.stopPropagation()">
                                        <span class="price">₹${parseFloat(p.price).toFixed(2)}</span>
                                        <button class="add-btn" data-id="${p.id}">
                                            <i class="fas fa-cart-plus"></i> ಸೇರಿಸು
                                        </button>
                                    </div>
                                </div>`;
                        });
                        productsGrid.innerHTML = html;
                        // Trigger fade-up animation
                        observeFadeElements();
                    })
                    .catch(console.error);
            }

            // ----- Update URL without reload -----
            function updateURL(category, search) {
                const params = new URLSearchParams();
                if (category) params.set('category', category);
                if (search) params.set('search', search);
                const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.pushState({}, '', newUrl);
            }

            // ----- Intersection Observer for fade-up -----
            function observeFadeElements() {
                const els = document.querySelectorAll('.fade-up:not(.visible)');
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    threshold: 0.1
                });
                els.forEach(el => observer.observe(el));
            }

            // ----- EVENTS -----
            function init() {
                // Theme toggle
                const themeToggle = document.getElementById('themeToggle');
                const themeIcon = document.getElementById('themeIcon');
                const htmlTag = document.documentElement;

                // Load saved theme
                const savedTheme = localStorage.getItem('theme') || 'light';
                htmlTag.setAttribute('data-theme', savedTheme);
                if (themeIcon) {
                    themeIcon.innerText = savedTheme === 'dark' ? '☀️' : '🌙';
                }

                if (themeToggle) {
                    themeToggle.addEventListener('click', function() {
                        const currentTheme = htmlTag.getAttribute('data-theme');
                        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                        htmlTag.setAttribute('data-theme', newTheme);
                        localStorage.setItem('theme', newTheme);
                        if (themeIcon) {
                            themeIcon.innerText = newTheme === 'dark' ? '☀️' : '🌙';
                        }
                    });
                }

                // Hero Slider Carousel
                const slidesContainer = document.getElementById('heroSlides');
                const dots = document.querySelectorAll('#sliderDots span');
                const prevBtn = document.getElementById('prevSlide');
                const nextBtn = document.getElementById('nextSlide');
                let currentSlide = 0;
                const totalSlides = dots.length;
                let slideInterval;

                function showSlide(index) {
                    if (!slidesContainer) return;
                    currentSlide = (index + totalSlides) % totalSlides;
                    slidesContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
                    dots.forEach((dot, idx) => {
                        dot.classList.toggle('active', idx === currentSlide);
                    });
                }

                function startSlideShow() {
                    slideInterval = setInterval(() => {
                        showSlide(currentSlide + 1);
                    }, 5000);
                }

                function stopSlideShow() {
                    clearInterval(slideInterval);
                }

                if (slidesContainer) {
                    startSlideShow();
                    slidesContainer.addEventListener('mouseenter', stopSlideShow);
                    slidesContainer.addEventListener('mouseleave', startSlideShow);
                }

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        stopSlideShow();
                        showSlide(currentSlide - 1);
                        startSlideShow();
                    });
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        stopSlideShow();
                        showSlide(currentSlide + 1);
                        startSlideShow();
                    });
                }
                dots.forEach(dot => {
                    dot.addEventListener('click', function() {
                        stopSlideShow();
                        showSlide(parseInt(this.dataset.index));
                        startSlideShow();
                    });
                });

                // Wishlist Toggle
                window.toggleWishlist = function(btn, event) {
                    event.preventDefault();
                    event.stopPropagation();
                    btn.classList.toggle('liked');
                    const icon = btn.querySelector('i');
                    if (btn.classList.contains('liked')) {
                        icon.className = 'fas fa-heart';
                        btn.style.transform = 'scale(1.3)';
                        setTimeout(() => btn.style.transform = 'none', 200);
                    } else {
                        icon.className = 'far fa-heart';
                    }
                };

                // Ripple Button Effect
                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('.add-btn, .ripple-btn, .btn');
                    if (!btn) return;

                    const circle = document.createElement('span');
                    const diameter = Math.max(btn.clientWidth, btn.clientHeight);
                    const radius = diameter / 2;

                    circle.style.width = circle.style.height = `${diameter}px`;
                    const rect = btn.getBoundingClientRect();
                    circle.style.left = `${e.clientX - rect.left - radius}px`;
                    circle.style.top = `${e.clientY - rect.top - radius}px`;
                    circle.classList.add('ripple');
                    circle.style.position = 'absolute';
                    circle.style.borderRadius = '50%';
                    circle.style.transform = 'scale(0)';
                    circle.style.backgroundColor = 'rgba(255, 255, 255, 0.4)';
                    circle.style.animation = 'ripple-effect 0.6s linear';
                    circle.style.pointerEvents = 'none';

                    // Add keyframes inline style dynamically once
                    if (!document.getElementById('ksr-ripple-styles')) {
                        const styleEl = document.createElement('style');
                        styleEl.id = 'ksr-ripple-styles';
                        styleEl.innerHTML = `
                        @keyframes ripple-effect {
                            to { transform: scale(4); opacity: 0; }
                        }
                    `;
                        document.head.appendChild(styleEl);
                    }

                    btn.appendChild(circle);

                    setTimeout(() => {
                        circle.remove();
                    }, 600);
                });

                // Cart Fly Animation
                window.animateCartFly = function(btn) {
                    if (!btn) return;
                    const card = btn.closest('.product-card, .best-card') || document.querySelector('#aiMainProduct');
                    if (!card) return;

                    const imgEl = card.querySelector('.product-img i, .rec-emoji, .product-main-image');
                    if (!imgEl) return;

                    const clone = imgEl.cloneNode(true);
                    const rect = imgEl.getBoundingClientRect();
                    const cartLink = document.getElementById('cartLink');
                    if (!cartLink) return;
                    const cartRect = cartLink.getBoundingClientRect();

                    clone.style.position = 'fixed';
                    clone.style.top = `${rect.top}px`;
                    clone.style.left = `${rect.left}px`;
                    clone.style.width = `${rect.width}px`;
                    clone.style.height = `${rect.height}px`;
                    clone.style.zIndex = '9999';
                    clone.style.transition = 'all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
                    clone.style.display = 'flex';
                    clone.style.alignItems = 'center';
                    clone.style.justifyContent = 'center';
                    clone.style.fontSize = rect.height / 2 + 'px';
                    clone.style.pointerEvents = 'none';

                    document.body.appendChild(clone);

                    setTimeout(() => {
                        clone.style.top = `${cartRect.top}px`;
                        clone.style.left = `${cartRect.left}px`;
                        clone.style.width = '20px';
                        clone.style.height = '20px';
                        clone.style.fontSize = '10px';
                        clone.style.transform = 'rotate(360deg)';
                        clone.style.opacity = '0.3';
                    }, 50);

                    setTimeout(() => {
                        clone.remove();
                        const badge = document.getElementById('cartCount');
                        if (badge) {
                            badge.style.transform = 'scale(1.4)';
                            setTimeout(() => badge.style.transform = 'none', 200);
                        }
                    }, 850);
                };

                // Integrate fly animation inside addToCart
                const originalAddToCart = addToCart;
                addToCart = function(productId) {
                    const btn = document.querySelector(`.add-btn[data-id="${productId}"]`) ||
                        document.querySelector(`.btn-add-large[data-id="${productId}"]`);
                    if (btn) {
                        animateCartFly(btn);
                    }
                    originalAddToCart(productId);
                };

                // Scroll To Top
                const scrollTopBtn = document.getElementById('scrollTopBtn');
                window.addEventListener('scroll', function() {
                    if (window.scrollY > 300) {
                        scrollTopBtn.classList.add('visible');
                    } else {
                        scrollTopBtn.classList.remove('visible');
                    }
                });
                if (scrollTopBtn) {
                    scrollTopBtn.addEventListener('click', function() {
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    });
                }

                // Quick View modal
                const qvModal = document.getElementById('quickViewModal');
                const qvClose = document.getElementById('closeQuickView');
                const qvName = document.getElementById('quickViewName');
                const qvPrice = document.getElementById('quickViewPrice');
                const qvImg = document.getElementById('quickViewImg');

                window.openQuickView = function(name, price, icon, imageUrl = '', productId) {
                    if (!qvModal) return;
                    qvName.innerText = name;
                    qvPrice.innerText = price;
                    if (imageUrl) {
                        qvImg.innerHTML = `<img src="${imageUrl}" alt="${name}" onerror="this.outerHTML='<i class=\\'fas ${icon}\\'></i>'" style="max-width:100%; max-height:100%; object-fit:contain; border-radius:8px;" />`;
                    } else {
                        qvImg.innerHTML = `<i class="fas ${icon}"></i>`;
                    }
                    const qvAddBtn = document.getElementById('quickViewAddBtn');
                    if (qvAddBtn && productId) {
                        qvAddBtn.setAttribute('data-id', productId);
                    }
                    qvModal.classList.add('open');
                };

                if (qvClose) {
                    qvClose.addEventListener('click', function() {
                        qvModal.classList.remove('open');
                    });
                }
                if (qvModal) {
                    qvModal.addEventListener('click', function(e) {
                        if (e.target === qvModal) qvModal.classList.remove('open');
                    });
                }

                // Stats Animate Counter
                const stats = document.querySelectorAll('.stat-item .number');
                const counterIntersectionObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const target = parseInt(entry.target.dataset.target);
                            let current = 0;
                            const duration = 1500;
                            const step = Math.ceil(target / (duration / 30));
                            const interval = setInterval(() => {
                                current += step;
                                if (current >= target) {
                                    entry.target.innerText = target + (entry.target.dataset.target == '99' ? '%' : '+\u00A0');
                                    clearInterval(interval);
                                } else {
                                    entry.target.innerText = current;
                                }
                            }, 30);
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    threshold: 0.5
                });
                stats.forEach(el => counterIntersectionObserver.observe(el));

                // Mobile menu
                if (mobileMenuBtn) {
                    mobileMenuBtn.addEventListener('click', function() {
                        navLinks.classList.toggle('show');
                    });
                }

                // Search
                if (searchBtn) {
                    searchBtn.addEventListener('click', function() {
                        const term = globalSearchInput.value.trim();
                        searchTerm = term;
                        updateURL(currentCategory, term);
                        loadProducts(currentCategory, term);
                    });
                }
                if (globalSearchInput) {
                    globalSearchInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            searchBtn.click();
                        }
                    });
                    if (searchTerm) {
                        globalSearchInput.value = searchTerm;
                    }
                }

                // Category filter
                if (categoryFilterSelect) {
                    categoryFilterSelect.addEventListener('change', function() {
                        const cat = this.value;
                        currentCategory = cat;
                        updateURL(cat, searchTerm);
                        loadProducts(cat, searchTerm);
                        document.querySelectorAll('.category-card').forEach(el => {
                            el.classList.toggle('active', el.getAttribute('href') === '?category=' + cat);
                        });
                    });
                }

                // Cart link – open modal instead of redirect
                if (cartLink) {
                    cartLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        openCartModal();
                    });
                }

                // Cart modal close
                if (closeModalBtn) {
                    closeModalBtn.addEventListener('click', closeCartModal);
                }
                if (cartModal) {
                    cartModal.addEventListener('click', function(e) {
                        if (e.target === cartModal) closeCartModal();
                    });
                }

                // Modal Checkout listener
                const modalCheckoutBtn = document.getElementById('modalCheckoutBtn');
                if (modalCheckoutBtn) {
                    modalCheckoutBtn.addEventListener('click', function() {
                        window.location.href = 'checkout/checkout.php';
                    });
                }

                // Cart qty actions (delegated)
                if (cartItemsContainer) {
                    cartItemsContainer.addEventListener('click', handleCartQty);
                }

                // Add to cart (delegated)
                if (productsGrid) {
                    productsGrid.addEventListener('click', function(e) {
                        const btn = e.target.closest('.add-btn');
                        if (btn && btn.dataset.id) {
                            e.preventDefault();
                            addToCart(btn.dataset.id);
                        }
                    });
                }

                // AI large button
                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('.btn-add-large');
                    if (btn && btn.dataset.id) {
                        e.preventDefault();
                        addToCart(btn.dataset.id);
                    }
                });



                // Best seller add buttons
                document.querySelectorAll('.best-card .add-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const id = this.dataset.id;
                        if (id) addToCart(id);
                    });
                });

                // Category group titles
                document.querySelectorAll('.category-group-title').forEach(el => {
                    el.addEventListener('click', function() {
                        const mainId = this.dataset.main;
                        if (mainId) {
                            const firstSub = document.querySelector(`.category-card[href*="category=${mainId}"]`);
                            if (firstSub) firstSub.click();
                        }
                    });
                });

                // Chat widget
                const chatToggle = document.getElementById('chatToggle');
                const chatBox = document.getElementById('chatBox');
                const chatInput = document.getElementById('chatInput');
                const sendChat = document.getElementById('sendChat');
                const chatMessages = document.getElementById('chatMessages');

                if (chatToggle) {
                    chatToggle.addEventListener('click', () => chatBox.classList.toggle('hidden'));
                }

                let homepageChatToken = localStorage.getItem('ksr_homepage_chat_token') || '';

                if (!document.getElementById('ksr-chat-animation-styles')) {
                    const styleEl = document.createElement('style');
                    styleEl.id = 'ksr-chat-animation-styles';
                    styleEl.innerHTML = `
                    @keyframes dotPulse {
                        0%, 100% { transform: scale(0.6); opacity: 0.4; }
                        50% { transform: scale(1.1); opacity: 1; }
                    }
                    .dots-typingIndicator span {
                        display: inline-block;
                    }
                `;
                    document.head.appendChild(styleEl);
                }

                function addMessage(text, sender, suggestions = []) {
                    const msgDiv = document.createElement('div');
                    msgDiv.classList.add('message', sender);

                    let formatted = text;
                    if (sender === 'bot') {
                        formatted = formatChatMarkdown(text);
                    } else {
                        formatted = escHtml(text);
                    }

                    let sugHtml = '';
                    if (suggestions && suggestions.length > 0 && sender === 'bot') {
                        sugHtml = '<div class="chat-sug-chips" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">' +
                            suggestions.map(s => `<button class="sug-chip" style="font-size:0.75rem;padding:4px 10px;border-radius:12px;border:1px solid var(--primary);background:var(--primary-light);color:var(--primary);cursor:pointer;outline:none;" onclick="sendHomepageSuggestion('${s.replace(/'/g, "\\'")}')">${s}</button>`).join('') +
                            '</div>';
                    }

                    msgDiv.innerHTML =
                        `<div class="avatar"><i class="fas fa-${sender === 'bot' ? 'robot' : 'user'}"></i></div><div class="bubble">${formatted}${sugHtml}</div>`;
                    chatMessages.appendChild(msgDiv);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }

                window.sendHomepageSuggestion = function(text) {
                    if (chatInput) {
                        chatInput.value = text;
                        sendChat.click();
                    }
                };

                function escHtml(str) {
                    const d = document.createElement('div');
                    d.textContent = str;
                    return d.innerHTML;
                }

                function formatChatMarkdown(text) {
                    if (!text) return '';
                    return text
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                        .replace(/\*(.+?)\*/g, '<em>$1</em>')
                        .replace(/`(.+?)`/g, '<code>$1</code>')
                        .replace(/\n/g, '<br>');
                }

                let typingIndicator = null;

                function showTypingIndicator() {
                    typingIndicator = document.createElement('div');
                    typingIndicator.classList.add('message', 'bot', 'typing');
                    typingIndicator.innerHTML =
                        `<div class="avatar"><i class="fas fa-robot"></i></div><div class="bubble"><div class="dots-typingIndicator" style="display:flex;gap:4px;padding:4px 0;"><span style="width:6px;height:6px;border-radius:50%;background:var(--text-muted);animation:dotPulse 1.2s infinite ease-in-out;"></span><span style="width:6px;height:6px;border-radius:50%;background:var(--text-muted);animation:dotPulse 1.2s infinite ease-in-out;animation-delay:0.2s;"></span><span style="width:6px;height:6px;border-radius:50%;background:var(--text-muted);animation:dotPulse 1.2s infinite ease-in-out;animation-delay:0.4s;"></span></div></div>`;
                    chatMessages.appendChild(typingIndicator);
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }

                function removeTypingIndicator() {
                    if (typingIndicator) {
                        typingIndicator.remove();
                        typingIndicator = null;
                    }
                }

                if (sendChat) {
                    sendChat.addEventListener('click', function() {
                        const t = chatInput.value.trim();
                        if (!t) return;
                        addMessage(t, 'user');
                        chatInput.value = '';

                        showTypingIndicator();

                        fetch('ai-enterprise/api.php?action=chat', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Session-Token': homepageChatToken
                                },
                                body: JSON.stringify({
                                    message: t,
                                    session_token: homepageChatToken,
                                    category: 'general'
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                removeTypingIndicator();
                                if (data.success) {
                                    if (data.session_token) {
                                        homepageChatToken = data.session_token;
                                        localStorage.setItem('ksr_homepage_chat_token', homepageChatToken);
                                    }
                                    addMessage(data.message, 'bot', data.suggestions || []);
                                } else {
                                    addMessage('⚠️ Sorry, I could not process that request. Please try again.', 'bot');
                                }
                            })
                            .catch(err => {
                                removeTypingIndicator();
                                console.error('AI chat error:', err);
                                addMessage('⚠️ Error connecting to AI assistant.', 'bot');
                            });
                    });
                }
                if (chatInput) {
                    chatInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            sendChat.click();
                        }
                    });
                }

                // Load cart on init
                loadCart();

                // If category is set, load products for that category
                if (currentCategory) {
                    loadProducts(currentCategory, searchTerm);
                }

                // Observe initial fade elements
                observeFadeElements();

                // ============================================================
                // PAGINATION SYSTEM (8 products per page)
                // ============================================================
                const PRODUCTS_PER_PAGE = 8;
                let currentPage = 1;
                let allProductCards = [];
                let filteredCards = [];

                function initPagination() {
                    allProductCards = Array.from(document.querySelectorAll('#productsGrid .product-card'));
                    filteredCards = [...allProductCards];
                    currentPage = 1;
                    renderPagination();
                }

                function renderPagination() {
                    const totalPages = Math.ceil(filteredCards.length / PRODUCTS_PER_PAGE);
                    const start = (currentPage - 1) * PRODUCTS_PER_PAGE;
                    const end = start + PRODUCTS_PER_PAGE;

                    // Show/hide cards
                    allProductCards.forEach(card => card.style.display = 'none');
                    filteredCards.slice(start, end).forEach(card => {
                        card.style.display = '';
                        card.classList.add('visible');
                    });

                    // Update labels
                    const rangeEl = document.getElementById('pageRange');
                    const totalEl = document.getElementById('totalProductsCount');
                    const countLabel = document.getElementById('productCountLabel');
                    if (rangeEl) rangeEl.textContent = `${start + 1}-${Math.min(end, filteredCards.length)}`;
                    if (totalEl) totalEl.textContent = filteredCards.length;
                    if (countLabel) countLabel.textContent = `${filteredCards.length} Products`;

                    // Pagination buttons
                    const prevBtn = document.getElementById('prevPage');
                    const nextBtn = document.getElementById('nextPage');
                    const pageNumsEl = document.getElementById('pageNumbers');

                    if (prevBtn) prevBtn.disabled = currentPage <= 1;
                    if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

                    if (pageNumsEl) {
                        let html = '';
                        if (totalPages > 0) {
                            const maxVisibleBtn = 5;
                            let startPage = Math.max(1, currentPage - Math.floor(maxVisibleBtn / 2));
                            let endPage = startPage + maxVisibleBtn - 1;

                            if (endPage > totalPages) {
                                endPage = totalPages;
                                startPage = Math.max(1, endPage - maxVisibleBtn + 1);
                            }

                            if (startPage > 1) {
                                html += `<button class="page-num" data-page="1">1</button>`;
                                if (startPage > 2) {
                                    html += `<span class="page-ellipsis" style="display:flex;align-items:flex-end;justify-content:center;font-weight:bold;color:var(--text-muted);padding:0 4px;margin-bottom:4px;">...</span>`;
                                }
                            }

                            for (let i = startPage; i <= endPage; i++) {
                                html += `<button class="page-num ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
                            }

                            if (endPage < totalPages) {
                                if (endPage < totalPages - 1) {
                                    html += `<span class="page-ellipsis" style="display:flex;align-items:flex-end;justify-content:center;font-weight:bold;color:var(--text-muted);padding:0 4px;margin-bottom:4px;">...</span>`;
                                }
                                html += `<button class="page-num" data-page="${totalPages}">${totalPages}</button>`;
                            }
                        }
                        pageNumsEl.innerHTML = html;
                    }
                }

                document.getElementById('prevPage')?.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage--;
                        renderPagination();
                    }
                });
                document.getElementById('nextPage')?.addEventListener('click', () => {
                    const totalPages = Math.ceil(filteredCards.length / PRODUCTS_PER_PAGE);
                    if (currentPage < totalPages) {
                        currentPage++;
                        renderPagination();
                    }
                });
                document.getElementById('pageNumbers')?.addEventListener('click', (e) => {
                    const btn = e.target.closest('.page-num');
                    if (btn) {
                        currentPage = parseInt(btn.dataset.page);
                        renderPagination();
                    }
                });

                // ============================================================
                // SORTING SYSTEM
                // ============================================================
                const sortSelect = document.getElementById('sortSelect');
                if (sortSelect) {
                    sortSelect.addEventListener('change', function() {
                        const val = this.value;
                        let sorted = [...filteredCards];

                        switch (val) {
                            case 'price_low':
                                sorted.sort((a, b) => parseFloat(a.dataset.price) - parseFloat(b.dataset.price));
                                break;
                            case 'price_high':
                                sorted.sort((a, b) => parseFloat(b.dataset.price) - parseFloat(a.dataset.price));
                                break;
                            case 'a_z':
                                sorted.sort((a, b) => (a.dataset.name || '').localeCompare(b.dataset.name || ''));
                                break;
                            case 'z_a':
                                sorted.sort((a, b) => (b.dataset.name || '').localeCompare(a.dataset.name || ''));
                                break;
                            case 'newest':
                                sorted.sort((a, b) => parseInt(b.dataset.id) - parseInt(a.dataset.id));
                                break;
                            case 'popularity':
                                sorted.reverse();
                                break;
                            default:
                                break;
                        }

                        filteredCards = sorted;
                        const grid = document.getElementById('productsGrid');
                        filteredCards.forEach(card => grid.appendChild(card));
                        currentPage = 1;
                        renderPagination();
                    });
                }

                // ============================================================
                // PRICE RANGE FILTER
                // ============================================================
                document.getElementById('applyPriceFilter')?.addEventListener('click', function() {
                    const minVal = parseFloat(document.getElementById('priceMin')?.value) || 0;
                    const maxVal = parseFloat(document.getElementById('priceMax')?.value) || Infinity;

                    filteredCards = allProductCards.filter(card => {
                        const price = parseFloat(card.dataset.price) || 0;
                        return price >= minVal && price <= maxVal;
                    });

                    currentPage = 1;
                    renderPagination();
                });

                // ============================================================
                // ROTATING QUOTES
                // ============================================================
                const quotes = [{
                        text: '"The food you eat can be either the safest and most powerful form of medicine or the slowest form of poison."',
                        author: '— Ann Wigmore'
                    },
                    {
                        text: '"Let food be thy medicine and medicine be thy food."',
                        author: '— Hippocrates'
                    },
                    {
                        text: '"A healthy outside starts from the inside."',
                        author: '— Robert Urich'
                    },
                    {
                        text: '"Take care of your body. It\'s the only place you have to live."',
                        author: '— Jim Rohn'
                    },
                    {
                        text: '"Your diet is a bank account. Good food choices are good investments."',
                        author: '— Bethenny Frankel'
                    },
                    {
                        text: '"ನಿಮ್ಮ ಆಹಾರ ನಿಮ್ಮ ಔಷಧಿಯಾಗಲಿ, ನಿಮ್ಮ ಔಷಧಿ ನಿಮ್ಮ ಆಹಾರವಾಗಲಿ."',
                        author: '— ಹಿಪ್ಪೊಕ್ರೇಟ್ಸ್'
                    },
                    {
                        text: '"Fresh food is not a luxury. It\'s a fundamental right."',
                        author: '— KSR GURU JI'
                    },
                    {
                        text: '"One cannot think well, love well, sleep well, if one has not dined well."',
                        author: '— Virginia Woolf'
                    }
                ];
                let quoteIndex = 0;

                function rotateQuote() {
                    quoteIndex = (quoteIndex + 1) % quotes.length;
                    const textEl = document.getElementById('quoteText');
                    const authorEl = document.getElementById('quoteAuthor');
                    if (textEl && authorEl) {
                        textEl.style.opacity = '0';
                        authorEl.style.opacity = '0';
                        setTimeout(() => {
                            textEl.textContent = quotes[quoteIndex].text;
                            authorEl.textContent = quotes[quoteIndex].author;
                            textEl.style.opacity = '1';
                            authorEl.style.opacity = '1';
                        }, 400);
                    }
                }
                setInterval(rotateQuote, 10000);

                // Initialize pagination on page load
                initPagination();

                console.log('🚀 KSR GURU JI — Redesigned store loaded!');
            }



            // ----- Start -----
            document.addEventListener('DOMContentLoaded', init);

            // Handle back/forward browser buttons
            window.addEventListener('popstate', function() {
                const params = new URLSearchParams(window.location.search);
                const cat = params.get('category') || '';
                const search = params.get('search') || '';
                currentCategory = cat;
                searchTerm = search;
                if (globalSearchInput) globalSearchInput.value = search;
                if (categoryFilterSelect) categoryFilterSelect.value = cat;
                loadProducts(cat, search);
            });

        })();

        // ============================================================
        // GLOBAL HELPERS (outside IIFE - called via onclick)
        // ============================================================
        function subscribeNewsletter() {
            var phone = (document.getElementById('newsletterPhone') ? document.getElementById('newsletterPhone').value : '').trim();
            if (!phone || phone.length < 10) {
                alert('Please enter a valid 10-digit mobile number.');
                return;
            }
            var msg = encodeURIComponent('Hi KSR GURU JI! Please add ' + phone + ' to your daily deals list. Thank you!');
            window.open('https://wa.me/918088584421?text=' + msg, '_blank');
        }
    </script>
</body>

</html>