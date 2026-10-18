<?php
// ============================================================
// pet-supplies.php - Dynamic Pet Supplies page
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
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
// User info
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : 'Guest';
// ============================================================
// FETCH PRODUCTS - Try from DB first, fallback to static data
// ============================================================
$categorySlug = 'pet-supplies';
$petCategory = getCategoryBySlug($categorySlug);
$productsData = []; // will hold the final product array
if ($petCategory) {
  // Fetch products from this category
  $dbProducts = getProductsByCategory($petCategory['id']);
  if (!empty($dbProducts)) {
    foreach ($dbProducts as $p) {
      // Build description from DB fields
      $description = $p['description_en'] ?? ($p['weight'] ?? 'Premium pet product');
      $productsData[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'kn' => $p['kn_name'] ?? $p['name'] . ' (ಸಾಕುಪ್ರಾಣಿ)',
        'category' => $p['category_slug'] ?? 'other',
        'price' => $p['price'],
        'image' => $p['image_url'] ?? '',
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
    ['id' => 1, 'name' => 'Dog Dry Food', 'kn' => 'ನಾಯಿ ಒಣ ಆಹಾರ', 'category' => 'dog', 'price' => 550, 'image' => "images\\Dog Dry Food.jpg", 'desc' => 'High-protein dry kibble for adult dogs, supports healthy coat and digestion.'],
    ['id' => 2, 'name' => 'Cat Wet Food', 'kn' => 'ಬೆಕ್ಕು ಒದ್ದೆ ಆಹಾರ', 'category' => 'cat', 'price' => 320, 'image' => "images\\Cat Wet Food.jpg", 'desc' => 'Tender chunks in gravy, complete nutrition for feline friends.'],
    ['id' => 3, 'name' => 'Dog Treats', 'kn' => 'ನಾಯಿ ಟ್ರೀಟ್ಸ್', 'category' => 'treats', 'price' => 980, 'image' => "images\\Dog Treats.jpg", 'desc' => 'Crunchy, vitamin-enriched treats ideal for training and reward.'],
    ['id' => 4, 'name' => 'Cat Treats', 'kn' => 'ಬೆಕ್ಕು ಟ್ರೀಟ್ಸ್', 'category' => 'treats', 'price' => 160, 'image' => "images\\Cat Treats.jpg", 'desc' => 'Soft, irresistible creamy treats with chicken flavour.'],
    ['id' => 5, 'name' => 'Chew Toy', 'kn' => 'ಚೂಯಿಂಗ್ ಆಟಿಕೆ', 'category' => 'toys', 'price' => 680, 'image' => "images\\Chew Toy.jpg", 'desc' => 'Non-toxic, heavy-duty rubber toy that keeps dogs engaged and reduces anxiety.'],
    ['id' => 6, 'name' => 'Cat Toy', 'kn' => 'ಬೆಕ್ಕಿನ ಆಟಿಕೆ', 'category' => 'toys', 'price' => 580, 'image' => "images\\Cat Toy.jpg", 'desc' => 'Interactive feather wand with bell, stimulates natural hunting instincts.'],
    ['id' => 7, 'name' => 'Pet Leash', 'kn' => 'ಸಾಕುಪ್ರಾಣಿ ಪಟ್ಟಿ', 'category' => 'accessories', 'price' => 600, 'image' => "images\\Pet Leash.jpg", 'desc' => 'Nylon leash with padded handle, 4ft length, reflective stitching for night walks.'],
    ['id' => 8, 'name' => 'Pet Bed', 'kn' => 'ಸಾಕುಪ್ರಾಣಿ ಹಾಸಿಗೆ', 'category' => 'accessories', 'price' => 890, 'image' => "images\\Pet Bed.jpg", 'desc' => 'Plush, washable bed with memory foam, perfect for dogs and cats.'],
    ['id' => 9, 'name' => 'Cat Litter', 'kn' => 'ಬೆಕ್ಕು ಲಿಟ್ಟರ್', 'category' => 'cat', 'price' => 1050, 'image' => "images\\Cat Litter.jpg", 'desc' => 'Odour-control, clumping clay litter, low dust – 10kg bag.'],
    ['id' => 10, 'name' => 'Pet Grooming Brush', 'kn' => 'ಸಾಕುಪ್ರಾಣಿ ಅಂದಗೊಳಿಸುವ ಬ್ರಷ್', 'category' => 'accessories', 'price' => 450, 'image' => "images\\Pet Leash.jpg", 'desc' => 'Slicker brush for removing loose hair and mats, comfortable grip.'],
    ['id' => 11, 'name' => 'Dog Bowl Set', 'kn' => 'ನಾಯಿ ಬೌಲ್ ಸೆಟ್', 'category' => 'accessories', 'price' => 340, 'image' => "images\\Pet Bed.jpg", 'desc' => 'Two-pack anti-skid stainless steel bowls, dishwasher safe.'],
    ['id' => 12, 'name' => 'Puppy Training Pads', 'kn' => 'ಪಪ್ಪಿ ತರಬೇತಿ ಪ್ಯಾಡ್ಗಳು', 'category' => 'dog', 'price' => 590, 'image' => "images\\Dog Dry Food.jpg", 'desc' => 'Leak-proof, quick-absorbent pads (50 pcs) for housebreaking puppies.']
  ];
  foreach ($staticProducts as $item) {
    $productsData[] = [
      'id' => $item['id'],
      'name' => $item['name'],
      'kn' => $item['kn'],
      'category' => $item['category'],
      'price' => $item['price'],
      'image' => $item['image'],
      'description' => $item['desc'],
      'weight' => ''
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
  <title>Pet Supplies · ಪಿಇಟಿ ಸರಬರಾಜು</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <style>
    /* ===== SAME CSS AS STATIC VERSION ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      display: block;
    }

    #app {
      max-width: 1280px;
      width: 100%;
      background: white;
      border-radius: 2rem;
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.05);
      padding: 1.5rem;
    }

    .brand-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 2rem;
      border-bottom: 3px solid #ffdfc4;
      padding-bottom: 0.5rem;
    }

    .title h1 {
      font-size: 2rem;
      font-weight: 700;
      color: #c25e2a;
    }

    .title .kannada {
      font-size: 0.9rem;
      color: #e07c3c;
      margin-top: 0.2rem;
    }

    .cart-link {
      background: #ffede0;
      padding: 0.7rem 1.3rem;
      border-radius: 60px;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      border: 1px solid #ffd9ba;
      text-decoration: none;
      font-weight: 600;
      color: #c25e2a;
    }

    .cart-link i {
      font-size: 1.4rem;
      color: #e07c3c;
    }

    .cart-link .cart-count {
      background: #e07c3c;
      color: white;
      padding: 0.1rem 0.7rem;
      border-radius: 40px;
      font-weight: 700;
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
    }

    .search-wrapper i {
      color: #e07c3c;
      font-size: 1.1rem;
    }

    .search-wrapper input {
      flex: 1;
      border: none;
      background: transparent;
      padding: 1rem 0.8rem;
      font-size: 1rem;
      outline: none;
    }

    .search-wrapper button {
      background: #e07c3c;
      border: none;
      color: white;
      padding: 0.7rem 1.8rem;
      border-radius: 60px;
      cursor: pointer;
      font-weight: 600;
    }

    .filter-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 0.7rem;
      margin: 1.5rem 0 0.5rem;
    }

    .pill {
      background: #ffede0;
      color: #c25e2a;
      padding: 0.5rem 1.3rem;
      border-radius: 40px;
      font-size: 0.85rem;
      font-weight: 600;
      border: 1px solid #ffdcbf;
      cursor: pointer;
    }

    .pill.active {
      background: #e07c3c;
      color: white;
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
      gap: 1.8rem;
      margin: 2rem 0;
    }

    .product-card {
      background: white;
      border-radius: 28px;
      padding: 1.2rem;
      border: 1px solid #fae1ce;
      transition: all 0.2s;
    }

    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 24px rgba(224, 124, 60, 0.1);
    }

    .product-img {
      width: 70px;
      height: 70px;
      object-fit: cover;
      border-radius: 20px;
      background: #fef3ea;
      margin-bottom: 1rem;
      border: 1px solid #ffdfcb;
    }

    .product-name {
      font-weight: 700;
      font-size: 1.2rem;
      color: #3e2a21;
    }

    .product-name-kn {
      font-size: 0.8rem;
      color: #9b7b64;
      margin-bottom: 0.3rem;
    }

    .product-desc {
      font-size: 0.75rem;
      color: #ad886e;
      background: #fff7f0;
      padding: 6px 10px;
      border-radius: 40px;
      margin: 8px 0;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 8px;
    }

    .price {
      font-weight: 800;
      font-size: 1.4rem;
      color: #e07c3c;
    }

    .add-btn {
      background: #ffede0;
      border: none;
      padding: 8px 16px;
      border-radius: 50px;
      font-weight: 700;
      font-size: 0.8rem;
      color: #c25e2a;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      border: 1px solid #ffd2b5;
    }

    .add-btn:hover {
      background: #e07c3c;
      color: white;
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
      <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
      <div class="pill" data-category="dog">Dog Food / ನಾಯಿ ಆಹಾರ</div>
      <div class="pill" data-category="cat">Cat Food / ಬೆಕ್ಕು ಆಹಾರ</div>
      <div class="pill" data-category="treats">Treats / ಟ್ರೀಟ್ಸ್</div>
      <div class="pill" data-category="toys">Toys / ಆಟಿಕೆಗಳು</div>
      <div class="pill" data-category="accessories">Accessories / ಉಪಕರಣಗಳು</div>
    </div>
  </div>
  <div class="product-grid" id="productGrid"></div>
  <div class="no-results" id="noResultsMsg">😕 No items found · ದಯವಿಟ್ಟು ಬೇರೆ ಹುಡುಕಿ</div>
  </div>
  <script>
    // ============================================================
    // JAVASCRIPT – SESSION-BASED CART VIA AJAX + LOCALSTORAGE FALLBACK
    // ============================================================
    (function() {
      // ----- PRODUCT DATA FROM PHP (embedded) -----
      const products = <?php echo json_encode($productsData); ?>;
      // Normalize product images
      if (typeof products !== 'undefined' && Array.isArray(products)) {
        products.forEach(p => {
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
        let filtered = products.filter(p => {
          if (activeCategory !== 'all' && p.category !== activeCategory) return false;
          const term = searchTerm.trim().toLowerCase();
          if (term) {
            return p.name.toLowerCase().includes(term) || p.kn.toLowerCase().includes(term);
          }
          return true;
        });
        if (filtered.length === 0) {
          productGrid.innerHTML = '';
          noResultsMsg.style.display = 'block';
          return;
        }
        noResultsMsg.style.display = 'none';
        productGrid.innerHTML = filtered.map(prod => `
            <div class="product-card">
                <img class="product-img" src="${prod.image}" alt="${prod.name}" loading="lazy" onerror="this.src='https://placehold.co/70x70/fef3ea/e07c3c?text=🐾'">
                <div class="product-name">${prod.name}</div>
                <div class="product-name-kn">${prod.kn}</div>
                <div class="product-desc"><i class="fa-regular fa-comment-dots"></i> ${prod.description}</div>
                <div class="price-row">
                    <span class="price">₹${prod.price}</span>
                    <button class="add-btn" data-id="${prod.id}" title="Add to cart · ಕಾರ್ಟ್‌ಗೆ ಸೇರಿಸಿ">
                        <i class="fa-solid fa-cart-plus"></i> Add to cart
                    </button>
                </div>
            </div>
        `).join('');
        document.querySelectorAll('.add-btn').forEach(btn => {
          btn.addEventListener('click', () => addToCart(parseInt(btn.dataset.id)));
        });
      }
      // ----- SEARCH -----
      function performSearch() {
        searchTerm = searchInput.value;
        renderProducts();
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