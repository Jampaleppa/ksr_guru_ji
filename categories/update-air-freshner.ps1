$content = Get-Content -Raw -Encoding UTF8 "d:\KSR GURU JI\categories\air-freshner.php"
$newCss = @"
  <style>
    /* ===== MODERN FRESH DESIGN (cool breeze & nature tones) ===== */
    :root {
      --primary: #0284c7;        /* Sky Blue */
      --primary-light: #38bdf8;
      --secondary: #0d9488;      /* Teal */
      --bg-color: #f0fdfa;       /* Minty white background */
      --card-bg: #ffffff;
      --text-dark: #0f172a;
      --text-muted: #64748b;
      --border-color: #e2e8f0;
      --accent-bg: #e0f2fe;
      --pill-bg: #f8fafc;
      --btn-grad: linear-gradient(135deg, #0284c7, #0d9488);
      --btn-hover: linear-gradient(135deg, #0369a1, #0f766e);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { background: var(--bg-color); font-family: 'Inter', sans-serif; padding: 28px 20px 48px; display: flex; justify-content: center; color: var(--text-dark); background-image: radial-gradient(circle at 10% 20%, rgba(2, 132, 199, 0.03) 0%, transparent 40%), radial-gradient(circle at 90% 80%, rgba(13, 148, 136, 0.04) 0%, transparent 40%); }
    .app-wrapper { max-width: 1400px; width: 100%; margin: 0 auto; }
    .shop-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 2.5rem; background: rgba(255, 255, 255, 0.6); padding: 20px 30px; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.8); }
    .branding h1 { font-family: 'Space Grotesk', monospace; font-size: 2.4rem; font-weight: 800; letter-spacing: -0.02em; background: linear-gradient(130deg, var(--primary), var(--secondary)); -webkit-background-clip: text; background-clip: text; color: transparent; display: flex; align-items: center; gap: 12px; }
    .branding .kannada-tag { font-size: 0.95rem; color: var(--primary); font-weight: 600; margin-top: 6px; letter-spacing: 0.02em; }
    .cart-link { background: var(--card-bg); border-radius: 80px; padding: 12px 28px; display: inline-flex; align-items: center; gap: 14px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid var(--accent-bg); box-shadow: 0 8px 20px rgba(2, 132, 199, 0.08); text-decoration: none; color: var(--primary); font-weight: 700; font-size: 1.05rem; }
    .cart-link:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(2, 132, 199, 0.15); background: var(--accent-bg); }
    .cart-link i { font-size: 1.5rem; color: var(--secondary); }
    .cart-link .cart-count { background: var(--btn-grad); color: white; font-weight: 800; padding: 3px 14px; border-radius: 60px; font-size: 1.1rem; margin-left: 6px; box-shadow: 0 4px 10px rgba(2,132,199,0.3); }
    
    .search-panel { margin-bottom: 35px; }
    .search-bar { background: var(--card-bg); border-radius: 70px; padding: 8px 28px; display: flex; align-items: center; border: 1px solid rgba(2, 132, 199, 0.15); gap: 16px; margin-bottom: 28px; box-shadow: 0 8px 24px rgba(0,0,0,0.04); transition: all 0.3s ease; }
    .search-bar:focus-within { border-color: var(--primary-light); box-shadow: 0 8px 30px rgba(2, 132, 199, 0.12); transform: translateY(-2px); }
    .search-bar i { font-size: 1.3rem; color: var(--primary-light); }
    .search-bar input { flex: 1; border: none; padding: 16px 0; background: transparent; font-size: 1.05rem; outline: none; font-weight: 500; color: var(--text-dark); }
    .search-bar input::placeholder { color: #94a3b8; }
    
    .filter-row { overflow-x: auto; padding-bottom: 12px; scrollbar-width: none; }
    .filter-row::-webkit-scrollbar { display: none; }
    .pill-group { display: flex; gap: 14px; padding: 4px; }
    .pill { background: var(--pill-bg); padding: 10px 28px; border-radius: 60px; font-weight: 600; font-size: 0.95rem; color: var(--text-muted); cursor: pointer; white-space: nowrap; border: 1px solid var(--border-color); transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
    .pill:hover { border-color: var(--primary-light); color: var(--primary); transform: translateY(-2px); }
    .pill.active { background: var(--btn-grad); color: white; border-color: transparent; box-shadow: 0 8px 20px rgba(2, 132, 199, 0.25); }
    
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 32px; margin: 32px 0 56px; }
    .product-card { background: var(--card-bg); border-radius: 32px; padding: 24px; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 12px 30px rgba(0,0,0,0.03); display: flex; flex-direction: column; position: relative; overflow: hidden; }
    .product-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px; background: var(--btn-grad); opacity: 0; transition: opacity 0.3s; }
    .product-card:hover { transform: translateY(-10px); box-shadow: 0 24px 48px -12px rgba(2, 132, 199, 0.15); border-color: rgba(2, 132, 199, 0.2); }
    .product-card:hover::before { opacity: 1; }
    
    .product-img { width: 100%; aspect-ratio: 1/1; object-fit: cover; border-radius: 24px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; overflow: hidden; transition: transform 0.5s ease; position: relative; }
    .product-card:hover .product-img { transform: scale(1.03); border-color: var(--primary-light); }
    .product-img img { width: 100%; height: 100%; object-fit: cover; transition: all 0.5s ease; }
    .product-card:hover .product-img img { transform: scale(1.08); }
    
    .product-title { font-weight: 800; font-size: 1.35rem; color: var(--text-dark); line-height: 1.4; margin-bottom: 4px; }
    .product-title-kn { font-size: 0.9rem; color: var(--primary); margin-bottom: 14px; font-weight: 600; }
    .product-description { font-size: 0.85rem; background: var(--bg-color); padding: 12px 16px; border-radius: 20px; color: var(--text-muted); margin: 8px 0 18px; line-height: 1.5; border-left: 3px solid var(--secondary); font-weight: 500; }
    
    .price-row { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-top: auto; padding-top: 8px; }
    .price { font-weight: 800; font-size: 1.8rem; color: var(--primary); letter-spacing: -0.03em; }
    .price small { font-size: 0.8rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; }
    
    .descriptive-cart-btn { background: var(--btn-grad); border: none; border-radius: 60px; padding: 14px 20px; font-weight: 700; font-size: 1rem; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 22px; transition: all 0.3s ease; box-shadow: 0 8px 20px rgba(2, 132, 199, 0.25); text-transform: uppercase; letter-spacing: 0.5px; }
    .descriptive-cart-btn i { font-size: 1.1rem; }
    .descriptive-cart-btn:hover { background: var(--btn-hover); transform: translateY(-3px) scale(1.02); box-shadow: 0 12px 24px rgba(2, 132, 199, 0.4); }
    
    .product-badge { position: absolute; top: 16px; left: 16px; background: linear-gradient(135deg, #ef4444, #f43f5e); color: white; padding: 6px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; z-index: 2; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); border: 1px solid rgba(255,255,255,0.4); }
    .product-source-row { font-size: 0.75rem; color: #94a3b8; margin: 12px 0 0; display: flex; justify-content: space-between; align-items: center; width: 100%; border-top: 1px dashed var(--border-color); padding-top: 12px; font-weight: 600; gap: 8px; flex-wrap: wrap; }
    .product-source-row .stock-tag { font-weight: 700; color: var(--secondary); background: #ccfbf1; padding: 2px 8px; border-radius: 20px; }
    .product-source-row .ad-tag { border-radius: 20px; padding: 3px 10px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
    .product-source-row .ad-tag.sponsored { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
    .product-source-row .ad-tag.trusted { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    
    footer { text-align: center; font-size: 0.85rem; color: var(--text-muted); margin-top: 40px; padding-top: 24px; border-top: 1px solid var(--border-color); font-weight: 500; letter-spacing: 0.02em; }
    .design-credits { margin-top: 24px; text-align: center; font-size: 0.8rem; color: #94a3b8; font-weight: 500; }
    .design-credits a { color: var(--primary); text-decoration: none; font-weight: 600; transition: color 0.2s; }
    .design-credits a:hover { color: var(--secondary); text-decoration: underline; }
    @media (max-width: 768px) {
      .shop-header { flex-direction: column; text-align: center; justify-content: center; padding: 20px; border-radius: 20px; }
      .branding h1 { justify-content: center; font-size: 2rem; }
      .product-grid { grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; }
      .pill { padding: 8px 20px; font-size: 0.9rem; }
      .search-bar { padding: 6px 20px; }
    }
    @media (max-width: 480px) {
      .product-grid { grid-template-columns: 1fr; }
      .price { font-size: 1.6rem; }
      .branding h1 { font-size: 1.8rem; }
    }
  </style>
"@
$content = $content -replace '(?s)<style>.*?</style>', $newCss

$newCredits = @"
  <div class="design-credits">
    <i class="fa-regular fa-gem"></i> premium collections: 
    <a href="../index.php">Home Page</a> · 
    <a href="personal-care.php">Personal Care</a> · 
    <a href="stationery.php">Stationery</a> 
    <span style="margin:0 5px; color: var(--primary);">✦</span> fresh & airy · dynamic interactions
  </div>
  <footer>fresh fragrance for every space · ಪ್ರತಿ ಸ್ಥಳಕ್ಕೂ ತಾಜಾ ಸುಗಂಧ</footer>
"@
$content = $content -replace '(?s)<div class="design-credits">.*?</footer>', $newCredits

$content = $content -replace '<i class="fa-solid fa-seedling"></i>', '<i class="fa-solid fa-wind"></i>'

Set-Content -Path "d:\KSR GURU JI\categories\air-freshner.php" -Value $content -Encoding UTF8
