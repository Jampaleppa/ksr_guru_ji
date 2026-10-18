<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$patientName = $_SESSION['user_name'] ?? 'John Doe';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy | KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --clr-primary: #0B1F3A; /* Deep Blue */
            --clr-secondary: #1976D2; /* Medical Blue */
            --clr-accent: #00897B; /* Teal */
            --clr-success: #2E7D32; /* Success Green */
            --clr-warning: #F9A825; /* Warning */
            --clr-danger: #D32F2F; /* Danger */
            --clr-bg: #F5F8FA; /* Background */
            --clr-card: #FFFFFF; /* White */
            --clr-text-main: #172033; /* Dark */
            --clr-text-muted: #667085; /* Muted */
            --clr-border: #E4E7EC;
            
            --padding-mobile: 16px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 4px 12px rgba(0,0,0,0.05);
            
            --font-family: 'Inter', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-family); background: var(--clr-bg); color: var(--clr-text-main); -webkit-font-smoothing: antialiased; padding-bottom: 80px; }

        /* Header */
        .app-header { background: var(--clr-primary); color: white; padding: 20px var(--padding-mobile); position: sticky; top: 0; z-index: 100; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .user-greeting { font-size: 1.1rem; font-weight: 800; }
        .user-loc { font-size: 0.8rem; opacity: 0.8; font-weight: 500; display: flex; align-items: center; gap: 4px; margin-top: 4px; }
        .cart-icon { position: relative; font-size: 1.25rem; }
        .cart-badge { position: absolute; top: -8px; right: -8px; background: var(--clr-danger); color: white; font-size: 0.65rem; font-weight: 800; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

        /* Search Box */
        .search-container { position: relative; }
        .search-input { width: 100%; padding: 14px 16px 14px 44px; border-radius: 12px; border: none; font-size: 0.95rem; font-weight: 500; font-family: inherit; box-shadow: 0 4px 15px rgba(0,0,0,0.1); outline: none; }
        .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--clr-secondary); }

        /* Prescription Banner */
        .rx-banner { margin: 24px var(--padding-mobile); background: linear-gradient(135deg, var(--clr-secondary), #4fc3f7); border-radius: var(--radius-lg); padding: 20px; display: flex; justify-content: space-between; align-items: center; color: white; box-shadow: var(--shadow-sm); }
        .rx-banner-content { flex: 1; }
        .rx-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 4px; }
        .rx-text { font-size: 0.85rem; font-weight: 500; opacity: 0.9; margin-bottom: 12px; }
        .rx-btn { background: white; color: var(--clr-secondary); padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 0.85rem; border: none; display: inline-flex; align-items: center; gap: 6px; }
        
        /* Section Title */
        .section-title { font-size: 1.1rem; font-weight: 800; padding: 0 var(--padding-mobile); margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
        .view-all { font-size: 0.85rem; font-weight: 700; color: var(--clr-secondary); text-decoration: none; }

        /* Categories Grid */
        .categories-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; padding: 0 var(--padding-mobile); margin-bottom: 32px; }
        .category-item { background: var(--clr-card); border-radius: var(--radius-md); padding: 12px 8px; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--clr-border); display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .cat-icon { width: 40px; height: 40px; border-radius: 50%; background: rgba(25, 118, 210, 0.1); color: var(--clr-secondary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .cat-name { font-size: 0.75rem; font-weight: 700; color: var(--clr-text-main); }

        /* Products Slider (Horizontal scroll for mobile) */
        .products-row { display: flex; overflow-x: auto; gap: 16px; padding: 4px var(--padding-mobile); margin-bottom: 32px; scroll-snap-type: x mandatory; }
        .products-row::-webkit-scrollbar { display: none; }
        .product-card { min-width: 160px; max-width: 160px; background: var(--clr-card); border-radius: var(--radius-lg); padding: 12px; box-shadow: var(--shadow-sm); border: 1px solid var(--clr-border); scroll-snap-align: start; }
        
        .p-image-wrap { background: var(--clr-bg); border-radius: 8px; margin-bottom: 12px; height: 100px; display: flex; align-items: center; justify-content: center; padding: 10px; position: relative; }
        .p-image-wrap img { max-width: 100%; max-height: 100%; mix-blend-mode: multiply; }
        .p-rx-req { position: absolute; top: 4px; right: 4px; font-size: 0.65rem; background: var(--clr-warning); color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 800; }

        .p-name { font-size: 0.9rem; font-weight: 800; color: var(--clr-text-main); margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .p-desc { font-size: 0.75rem; color: var(--clr-text-muted); font-weight: 500; margin-bottom: 8px; display: flex; flex-direction: column; }
        .p-price-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .p-price { font-size: 1rem; font-weight: 900; color: var(--clr-text-main); }
        .p-mrp { font-size: 0.7rem; color: var(--clr-text-muted); text-decoration: line-through; }

        /* Add to Cart Controls */
        .add-btn { width: 100%; background: var(--clr-secondary); color: white; border: none; padding: 8px; border-radius: 8px; font-weight: 800; font-size: 0.85rem; box-shadow: 0 4px 10px rgba(25,118,210,0.3); outline: none; cursor: pointer; transition: 0.2s; }
        .add-btn:active { transform: scale(0.95); }
        .qty-controls { display: none; justify-content: space-between; align-items: center; background: white; border: 1px solid var(--clr-secondary); border-radius: 8px; padding: 4px; }
        .qty-btn { width: 28px; height: 28px; background: var(--clr-secondary); color: white; border: none; border-radius: 6px; font-weight: 800; font-size: 1rem; display: flex; align-items: center; justify-content: center; }
        .qty-val { font-weight: 800; font-size: 0.9rem; width: 30px; text-align: center; }

        /* Mobile Bottom Nav */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: var(--clr-card); border-top: 1px solid var(--clr-border); display: flex; justify-content: space-around; padding: 12px 0; z-index: 1000; padding-bottom: calc(12px + env(safe-area-inset-bottom)); }
        .nav-item { display: flex; flex-direction: column; align-items: center; gap: 4px; color: var(--clr-text-muted); text-decoration: none; background: none; border: none; font-family: inherit; font-size: 0.7rem; font-weight: 700; width: 60px; }
        .nav-item i { font-size: 1.25rem; }
        .nav-item.active { color: var(--clr-secondary); }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






    <header class="app-header">
        <div class="header-top">
            <div>
                <div class="user-greeting">Hi, <?php echo htmlspecialchars($patientName); ?></div>
                <div class="user-loc"><i class="fas fa-map-marker-alt" style="color: var(--clr-warning);"></i> Delivering to 560001 <i class="fas fa-chevron-down" style="font-size:0.6rem;"></i></div>
            </div>
            <div class="cart-icon">
                <i class="fas fa-shopping-cart"></i>
                <div class="cart-badge" id="cartCount">0</div>
            </div>
        </div>
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Search medicines, tablets or health products">
        </div>
    </header>

    <div class="rx-banner">
        <div class="rx-banner-content">
            <div class="rx-title">Order with Prescription</div>
            <div class="rx-text">Upload your prescription and we will arrange the medicines for you.</div>
            <button class="rx-btn" onclick="window.location.href='pharmacy.php?rx=true'"><i class="fas fa-camera"></i> Upload Rx</button>
        </div>
        <i class="fas fa-file-prescription" style="font-size: 3rem; opacity: 0.8; margin-left: 10px;"></i>
    </div>

    <div class="section-title">
        Browse Categories 
        <a href="#" class="view-all">All</a>
    </div>
    <div class="categories-grid">
        <div class="category-item">
            <div class="cat-icon"><i class="fas fa-tablets"></i></div>
            <div class="cat-name">Tablets</div>
        </div>
        <div class="category-item">
            <div class="cat-icon" style="color:#00897B; background:rgba(0,137,123,0.1);"><i class="fas fa-syringe"></i></div>
            <div class="cat-name">Injections</div>
        </div>
        <div class="category-item">
            <div class="cat-icon" style="color:#E53935; background:rgba(229,57,53,0.1);"><i class="fas fa-heartbeat"></i></div>
            <div class="cat-name">Cardiac</div>
        </div>
        <div class="category-item">
            <div class="cat-icon" style="color:#8E24AA; background:rgba(142,36,170,0.1);"><i class="fas fa-pump-medical"></i></div>
            <div class="cat-name">Syrups</div>
        </div>
        <div class="category-item">
            <div class="cat-icon" style="color:#F9A825; background:rgba(249,168,37,0.1);"><i class="fas fa-eye-dropper"></i></div>
            <div class="cat-name">Eye Care</div>
        </div>
        <div class="category-item">
            <div class="cat-icon" style="color:#0288D1; background:rgba(2,136,209,0.1);"><i class="fas fa-lungs"></i></div>
            <div class="cat-name">Respiratory</div>
        </div>
        <div class="category-item">
            <div class="cat-icon" style="color:#43A047; background:rgba(67,160,71,0.1);"><i class="fas fa-first-aid"></i></div>
            <div class="cat-name">First Aid</div>
        </div>
        <div class="category-item">
            <div class="cat-icon" style="color:#D84315; background:rgba(216,67,21,0.1);"><i class="fas fa-stethoscope"></i></div>
            <div class="cat-name">Devices</div>
        </div>
    </div>

    <!-- Replace dynamically from DB in production -->
    <?php
    $demoMedicines = [
        ['id'=>1, 'name'=>'Paracetamol 500 mg', 'desc'=>'Tablet • 10 tablets', 'price'=>25, 'mrp'=>30, 'rx'=>false, 'img'=>'https://cdn-icons-png.flaticon.com/512/883/883356.png'],
        ['id'=>2, 'name'=>'Amoxicillin 250mg', 'desc'=>'Capsule • 15 caps', 'price'=>110, 'mrp'=>145, 'rx'=>true, 'img'=>'https://cdn-icons-png.flaticon.com/512/4140/4140047.png'],
        ['id'=>3, 'name'=>'Cough Syrup', 'desc'=>'Syrup • 100 ml', 'price'=>85, 'mrp'=>95, 'rx'=>false, 'img'=>'https://cdn-icons-png.flaticon.com/512/4320/4320330.png'],
        ['id'=>4, 'name'=>'Vitamin C Zinc', 'desc'=>'Tablet • 20 tablets', 'price'=>60, 'mrp'=>85, 'rx'=>false, 'img'=>'https://cdn-icons-png.flaticon.com/512/883/883356.png']
    ];
    ?>

    <div class="section-title">Popular Medicines</div>
    <div class="products-row">
        <?php foreach($demoMedicines as $med): ?>
        <div class="product-card">
            <div class="p-image-wrap">
                <img src="<?php echo $med['img']; ?>" alt="<?php echo $med['name']; ?>">
                <?php if($med['rx']): ?><div class="p-rx-req">Rx</div><?php endif; ?>
            </div>
            <div class="p-name"><?php echo $med['name']; ?></div>
            <div class="p-desc"><span><?php echo explode(' • ', $med['desc'])[0]; ?></span><span><?php echo explode(' • ', $med['desc'])[1]; ?></span></div>
            <div class="p-price-row">
                <div>
                    <div class="p-price">₹<?php echo $med['price']; ?></div>
                    <div class="p-mrp">MRP ₹<?php echo $med['mrp']; ?></div>
                </div>
            </div>
            
            <!-- State mapping -->
            <button class="add-btn" id="addBtn-<?php echo $med['id']; ?>" onclick="addToCart(<?php echo $med['id']; ?>)">ADD TO CART</button>
            <div class="qty-controls" id="qtyCtrl-<?php echo $med['id']; ?>">
                <button class="qty-btn" onclick="updateQty(<?php echo $med['id']; ?>, -1)">-</button>
                <div class="qty-val" id="qtyVal-<?php echo $med['id']; ?>">1</div>
                <button class="qty-btn" onclick="updateQty(<?php echo $med['id']; ?>, 1)">+</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Mobile Navigation -->
    <nav class="bottom-nav">
        <a href="patient-dashboard.php" class="nav-item"><i class="fas fa-home"></i>Home</a>
        <a href="patient-pharmacy.php" class="nav-item active"><i class="fas fa-pills"></i>Medicines</a>
        <a href="my-prescriptions.php" class="nav-item"><i class="fas fa-clipboard-list"></i>Rx</a>
        <a href="my-orders.php" class="nav-item"><i class="fas fa-box"></i>Orders</a>
        <a href="#" class="nav-item"><i class="fas fa-bars"></i>More</a>
    </nav>

    <script>
        let cartItems = {};

        function addToCart(id) {
            cartItems[id] = 1;
            document.getElementById(`addBtn-${id}`).style.display = 'none';
            document.getElementById(`qtyCtrl-${id}`).style.display = 'flex';
            document.getElementById(`qtyVal-${id}`).innerText = '1';
            updateCartBadge();
        }

        function updateQty(id, change) {
            let current = cartItems[id];
            current += change;
            if (current <= 0) {
                delete cartItems[id];
                document.getElementById(`qtyCtrl-${id}`).style.display = 'none';
                document.getElementById(`addBtn-${id}`).style.display = 'block';
            } else {
                cartItems[id] = current;
                document.getElementById(`qtyVal-${id}`).innerText = current;
            }
            updateCartBadge();
        }

        function updateCartBadge() {
            let total = 0;
            for(let k in cartItems) {
                total += cartItems[k];
            }
            document.getElementById('cartCount').innerText = total;
        }
    </script>
</body>
</html>
