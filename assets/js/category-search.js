/**
 * KSR GURU JI - Global Category Search & Missing Product Suggestion Integration
 * Loaded across all category pages:
 * 1. Pressing Enter in #searchInput redirects to global /search.php?q=...
 * 2. If in-category filter yields 0 items, provides 1-click CTA: "Search All 36+ Categories" or "Suggest Missing Product".
 */
(function() {
  document.addEventListener('DOMContentLoaded', function() {
    const searchInputs = document.querySelectorAll('#searchInput, .search-bar input, input[name="search"], input[name="q"]');
    
    searchInputs.forEach(input => {
      // Add enter key handler to trigger global search
      input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          const val = this.value.trim();
          if (val.length > 0) {
            e.preventDefault();
            window.location.href = '/search.php?q=' + encodeURIComponent(val);
          }
        }
      });
    });

    // Observe productGridEl for empty states and enhance them with Global Search & Suggestion CTAs
    const productGrid = document.getElementById('productGrid') || document.querySelector('.product-grid, .products-grid');
    if (productGrid) {
      const observer = new MutationObserver(function() {
        // If the grid contains the empty message
        const text = productGrid.textContent || '';
        if (text.includes('No items found') || text.includes('No products') || text.includes('ಯಾವುದೇ ಉತ್ಪನ್ನ ಇಲ್ಲ')) {
          if (!productGrid.querySelector('.ksr-search-enhance')) {
            const input = document.getElementById('searchInput') || document.querySelector('.search-bar input');
            const term = input ? input.value.trim() : '';
            const encTerm = encodeURIComponent(term);

            const waText = encodeURIComponent(`Hello KSR GURU JI! I am looking for "${term}". Can you source this for me?`);
            const ctaDiv = document.createElement('div');
            ctaDiv.className = 'ksr-search-enhance';
            ctaDiv.style.cssText = 'grid-column: 1 / -1; margin-top: 15px; display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;';
            ctaDiv.innerHTML = `
              <a href="/search.php?q=${encTerm}" style="display:inline-flex; align-items:center; gap:8px; background:#0F7B3A; color:#fff; padding:10px 20px; border-radius:24px; text-decoration:none; font-weight:700; font-size:0.9rem; box-shadow:0 3px 10px rgba(15,123,58,0.2);">
                <i class="fa-solid fa-magnifying-glass"></i> Search All 36+ Categories
              </a>
              <a href="/suggestion-box.php?product=${encTerm}&not_found=1" style="display:inline-flex; align-items:center; gap:8px; background:#E85D2C; color:#fff; padding:10px 20px; border-radius:24px; text-decoration:none; font-weight:700; font-size:0.9rem; box-shadow:0 3px 10px rgba(232,93,44,0.2);">
                <i class="fa-solid fa-lightbulb"></i> Suggest Missing Product
              </a>
              <a href="https://wa.me/918762474406?text=${waText}" target="_blank" style="display:inline-flex; align-items:center; gap:8px; background:#25D366; color:#fff; padding:10px 20px; border-radius:24px; text-decoration:none; font-weight:700; font-size:0.9rem; box-shadow:0 3px 10px rgba(37,211,102,0.2);">
                <i class="fa-brands fa-whatsapp"></i> WhatsApp 8762474406
              </a>
            `;
            productGrid.appendChild(ctaDiv);
          }
        }
      });

      observer.observe(productGrid, { childList: true, subtree: true });
    }
  });
})();

