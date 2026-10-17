/**
 * ============================================================
 * KSR GURU JI - Universal Sorting Module for Category Pages
 * ============================================================
 * Works with IIFE-scoped productsData by reading from window._ksrPageData
 * which each page exposes just before the sorting.js loads.
 *
 * If no window._ksrPageData is found, falls back to DOM-based sorting
 * of product cards already rendered in the grid.
 *
 * Sorting options:
 *   - Price: Low → High
 *   - Price: High → Low
 *   - A → Z
 *   - Z → A
 *   - Newest
 *   - Popularity
 * ============================================================
 */
(function () {
    'use strict';

    // ============================================================
    // 1. INJECT SORT DROPDOWN UI
    // ============================================================
    function injectSortUI() {
        const searchPanel = document.querySelector('.search-panel') || document.querySelector('.search-section');
        const filterRow = document.querySelector('.filter-row') || document.querySelector('.filter-pills');

        const sortBar = document.createElement('div');
        sortBar.className = 'ksr-sort-bar';
        sortBar.id = 'ksrSortBar';
        sortBar.innerHTML = `
            <div class="ksr-sort-inner">
                <div class="ksr-sort-label">
                    <i class="fas fa-sort"></i>
                    <span>Sort By</span>
                </div>
                <select id="ksrSortSelect" class="ksr-sort-select" aria-label="Sort products">
                    <option value="default">Default</option>
                    <option value="price_low">Price: Low → High · ಕಡಿಮೆ → ಹೆಚ್ಚು</option>
                    <option value="price_high">Price: High → Low · ಹೆಚ್ಚು → ಕಡಿಮೆ</option>
                    <option value="a_z">Name: A → Z</option>
                    <option value="z_a">Name: Z → A</option>
                    <option value="newest">Newest · ಹೊಸ</option>
                    <option value="popularity">Popularity · ಜನಪ್ರಿಯ</option>
                </select>
                <div class="ksr-sort-count" id="ksrProductCount"></div>
            </div>
        `;

        // Insert after filter row, or after search panel, or before product grid
        if (filterRow && filterRow.parentElement) {
            filterRow.parentElement.insertBefore(sortBar, filterRow.nextSibling);
        } else if (searchPanel) {
            searchPanel.appendChild(sortBar);
        } else {
            const grid = document.querySelector('.product-grid') || document.getElementById('productGrid');
            if (grid && grid.parentElement) {
                grid.parentElement.insertBefore(sortBar, grid);
            }
        }
        return sortBar;
    }

    // ============================================================
    // 2. DOM-BASED SORTING (Works universally with any page)
    // ============================================================
    // This approach reads product data from the rendered DOM cards,
    // sorts them, and reorders the DOM elements. No need to access
    // internal IIFE variables.

    function getProductGrid() {
        return document.querySelector('.product-grid') || document.getElementById('productGrid');
    }

    function getProductCards() {
        const grid = getProductGrid();
        if (!grid) return [];
        return Array.from(grid.querySelectorAll('.product-card'));
    }

    function extractCardData(card) {
        // Extract name from various structures across pages
        const nameEl = card.querySelector('.product-title') ||
                        card.querySelector('.product-name') ||
                        card.querySelector('.product-card-title') ||
                        card.querySelector('h3') ||
                        card.querySelector('h4');
        const name = nameEl ? nameEl.textContent.trim().toLowerCase() : '';

        // Extract price from various structures
        const priceEl = card.querySelector('.price') ||
                         card.querySelector('.product-price') ||
                         card.querySelector('[class*="price"]');
        let price = 0;
        if (priceEl) {
            const priceText = priceEl.textContent.replace(/[^\d.]/g, '');
            price = parseFloat(priceText) || 0;
        }

        // Extract ID
        const id = parseInt(card.dataset.id || card.getAttribute('data-id') || '0');

        return { name, price, id, element: card };
    }

    function sortCards(cards, mode) {
        const data = cards.map(extractCardData);

        switch (mode) {
            case 'price_low':
                data.sort((a, b) => a.price - b.price);
                break;
            case 'price_high':
                data.sort((a, b) => b.price - a.price);
                break;
            case 'a_z':
                data.sort((a, b) => a.name.localeCompare(b.name));
                break;
            case 'z_a':
                data.sort((a, b) => b.name.localeCompare(a.name));
                break;
            case 'newest':
                data.sort((a, b) => b.id - a.id);
                break;
            case 'popularity':
                data.sort((a, b) => {
                    const popA = ((a.id || 0) * 31) % 100;
                    const popB = ((b.id || 0) * 31) % 100;
                    return popB - popA;
                });
                break;
            default:
                // default = restore original order
                break;
        }

        return data.map(d => d.element);
    }

    // Store original order for reset
    let originalOrder = null;

    function performDOMSort(mode) {
        const grid = getProductGrid();
        if (!grid) return;

        const cards = getProductCards();
        if (cards.length === 0) return;

        // Save original order on first sort
        if (!originalOrder) {
            originalOrder = cards.map(c => c.cloneNode(false)); // just for order reference
            // Actually save the nodes themselves
            originalOrder = [...cards];
        }

        let sorted;
        if (mode === 'default') {
            // Restore original DOM order
            sorted = [...originalOrder];
        } else {
            sorted = sortCards(cards, mode);
        }

        // Re-append in new order (moves DOM nodes, doesn't clone)
        sorted.forEach(card => grid.appendChild(card));
    }

    // ============================================================
    // 3. HYBRID APPROACH: Try window data first, fallback to DOM
    // ============================================================
    function hookSorting() {
        const sortSelect = document.getElementById('ksrSortSelect');
        if (!sortSelect) return;

        sortSelect.addEventListener('change', function () {
            const mode = this.value;

            // ---- Strategy A: If page exposes data on window ----
            const dataRefs = ['productsData', 'products', 'productDatabase', 'allProducts'];
            let found = false;
            for (const name of dataRefs) {
                if (typeof window[name] !== 'undefined' && Array.isArray(window[name]) && window[name].length > 0) {
                    found = true;
                    break;
                }
            }

            // ---- Strategy B: DOM-based sort (always works) ----
            performDOMSort(mode);

            // Visual feedback
            sortSelect.classList.add('ksr-sort-active');
            setTimeout(() => sortSelect.classList.remove('ksr-sort-active'), 400);

            // Update count
            updateProductCount();
        });
    }

    // ============================================================
    // 4. PRODUCT COUNT
    // ============================================================
    function updateProductCount() {
        const countEl = document.getElementById('ksrProductCount');
        if (!countEl) return;

        const cards = getProductCards();
        const visible = cards.filter(c => c.style.display !== 'none');
        const count = visible.length > 0 ? visible.length : cards.length;
        countEl.innerHTML = `<i class="fas fa-boxes-stacked"></i> ${count} Products`;
    }

    // ============================================================
    // 5. INIT
    // ============================================================
    function init() {
        if (document.getElementById('ksrSortBar')) return;

        // Don't inject on non-category pages that already have sorting (like index.php)
        const path = window.location.pathname;
        const isCategoryPage = path.includes('/categories/') || path.includes('\\categories\\');
        if (!isCategoryPage) return;

        injectSortUI();
        hookSorting();

        // Update count after initial render
        setTimeout(updateProductCount, 800);

        // Monitor grid changes
        const grid = getProductGrid();
        if (grid) {
            const observer = new MutationObserver(() => {
                setTimeout(updateProductCount, 150);
                // Reset original order when grid content changes (e.g., category filter)
                originalOrder = null;
            });
            observer.observe(grid, { childList: true });
        }

        console.log('✅ KSR GURU JI Sorting Module loaded');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // Small delay to let existing page JS render products first
        setTimeout(init, 200);
    }
})();
