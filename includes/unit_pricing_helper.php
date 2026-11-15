<?php
// ============================================================
// includes/unit_pricing_helper.php
// ------------------------------------------------------------
// Generalized flexible unit/quantity pricing helper for all
// KSR GURU JI product categories.
//
// Usage:
//   $options = getPurchaseOptionsForCategory('dry-fruits', 480.00);
//   // Returns array of ['label'=>'250 g','value'=>'0.25','price'=>120.00,'unit'=>'g']
//
// Pricing constraint: Selling price = Market price + ₹3
// (Market price markup is handled UPSTREAM – the $basePrice
//  parameter should already be the final selling price.)
// ============================================================

declare(strict_types=1);

if (!function_exists('getPurchaseOptionsForCategory')) {

    /**
     * Returns available purchase options (weight/volume/count steps)
     * for a given category slug and base selling price (per primary unit).
     *
     * @param  string $categorySlug  Canonical slug e.g. 'dry-fruits', 'dairy'
     * @param  float  $basePrice     Selling price per primary unit (after +₹3 markup)
     * @return array<int, array{label:string, value:string, price:float, unit:string, primary:bool}>
     */
    function getPurchaseOptionsForCategory(string $categorySlug, float $basePrice): array
    {
        $slug = strtolower(trim($categorySlug));

        // --------------------------------------------------------
        // Category → option definitions
        // Each entry: [multiplier, label, unit, isPrimary]
        // multiplier = fraction of the primary unit this option represents
        // e.g. for a per-kg base, 0.25 → 250 g
        // --------------------------------------------------------
        $definitions = [

            // ---- Vegetables & Fruits (per kg) ------------------
            'vegetables-fruits' => [
                [0.25,  '250 g',    'g',      false],
                [0.5,   '500 g',    'g',      false],
                [1.0,   '1 kg',     'kg',     true],
                [2.0,   '2 kg',     'kg',     false],
                [5.0,   '5 kg',     'kg',     false],
            ],

            // ---- Bakery (per piece as default; handle packet separately) ----
            'bakery' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Packet', 'packet', false],
                [6.0,   '6 Pieces', 'piece',  false],
                [12.0,  '1 Dozen',  'dozen',  false],
            ],

            // ---- Dairy (per litre / 1 kg) ----------------------
            'dairy' => [
                [0.5,   '500 ml',   'ml',     false],
                [1.0,   '1 Litre',  'litre',  true],
                [2.0,   '2 Litre',  'litre',  false],
                [5.0,   '5 Litre',  'litre',  false],
            ],

            // ---- Cooking Oil (per litre) ----------------------
            'cooking-oil' => [
                [0.1,   '100 ml',   'ml',     false],
                [0.5,   '500 ml',   'ml',     false],
                [1.0,   '1 Litre',  'litre',  true],
                [5.0,   '5 Litre',  'litre',  false],
                [15.0,  '15 Litre', 'litre',  false],
            ],

            // ---- Dry Fruits & Nuts (per kg) -------------------
            'dry-fruits' => [
                [0.1,   '100 g',    'g',      false],
                [0.25,  '250 g',    'g',      true],
                [0.5,   '500 g',    'g',      false],
                [1.0,   '1 kg',     'kg',     false],
            ],

            // ---- Cleaning Products (per piece / bottle) --------
            'cleaning-products' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Bottle', 'bottle', false],
                [1.0,   '1 Packet', 'packet', false],
                [1.0,   '1 Litre',  'litre',  false],
            ],

            // ---- Spices & Masalas (per kg) ---------------------
            'spices-masalas' => [
                [0.05,  '50 g',     'g',      false],
                [0.1,   '100 g',    'g',      true],
                [0.25,  '250 g',    'g',      false],
                [0.5,   '500 g',    'g',      false],
            ],

            // ---- Snacks (per 100 g) ----------------------------
            'snacks' => [
                [1.0,   '1 Packet', 'packet', true],
                [1.0,   '1 Piece',  'piece',  false],
                [1.0,   '1 Box',    'box',    false],
                [2.5,   '250 g',    'g',      false],
            ],

            // ---- Stationery (per piece) ------------------------
            'stationery' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Packet', 'packet', false],
                [1.0,   '1 Box',    'box',    false],
                [1.0,   '1 Set',    'set',    false],
            ],

            // ---- Grains & Cereals (per kg) ---------------------
            'grains-cereals' => [
                [1.0,   '1 kg',     'kg',     true],
                [2.0,   '2 kg',     'kg',     false],
                [5.0,   '5 kg',     'kg',     false],
                [10.0,  '10 kg',    'kg',     false],
                [25.0,  '25 kg',    'kg',     false],
            ],

            // ---- Hair Care (per piece / bottle) ----------------
            'hair-care' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Bottle', 'bottle', false],
                [1.0,   '1 Pack',   'pack',   false],
            ],

            // ---- Personal Care (per piece) ---------------------
            'personal-care' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Pack',   'pack',   false],
                [2.0,   '2 Pieces', 'piece',  false],
                [3.0,   '3 Pieces', 'piece',  false],
            ],

            // ---- Bath & Body (per piece / bottle) --------------
            'bath-body' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Bottle', 'bottle', false],
                [1.0,   '1 Pack',   'pack',   false],
            ],

            // ---- Home Care (per piece) -------------------------
            'home-care' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Set',    'set',    false],
                [1.0,   '1 Pack',   'pack',   false],
            ],

            // ---- Dishwash (per piece / bar) --------------------
            'dishwash' => [
                [1.0,   '1 Bar',    'bar',    true],
                [1.0,   '1 Bottle', 'bottle', false],
                [1.0,   '1 Pouch',  'pouch',  false],
            ],

            // ---- Pickles & Sauces (per piece / bottle) ---------
            'pickles-sauces' => [
                [1.0,   '1 Bottle', 'bottle', true],
                [1.0,   '1 Jar',    'jar',    false],
                [1.0,   '1 Pack',   'pack',   false],
            ],

            // ---- Plastic Household Products (per piece) --------
            'plastic-household' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Set',    'set',    false],
                [1.0,   '1 Pack',   'pack',   false],
            ],

            // ---- Pooja Items (per piece) -----------------------
            'pooja-items' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Pack',   'pack',   false],
                [1.0,   '1 Set',    'set',    false],
            ],

            // ---- Ready-to-Eat Foods (per packet / piece) -------
            'ready-to-eat' => [
                [1.0,   '1 Packet', 'packet', true],
                [1.0,   '1 Box',    'box',    false],
                [1.0,   '1 Piece',  'piece',  false],
            ],

            // ---- Seasonal Items (per piece) --------------------
            'seasonal-items' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Pack',   'pack',   false],
                [1.0,   '1 Set',    'set',    false],
            ],

            // ---- Pet Supplies (per piece / bag) ----------------
            'pet-supplies' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Pack',   'pack',   false],
                [1.0,   '1 Bag',    'bag',    false],
            ],

            // ---- Paper Products (per packet / roll) ------------
            'paper-products' => [
                [1.0,   '1 Packet', 'packet', true],
                [1.0,   '1 Roll',   'roll',   false],
                [1.0,   '1 Box',    'box',    false],
            ],

            // ---- Cold Drinks & Beverages (per piece / bottle) --
            'cold-drinks' => [
                [1.0,   '1 Bottle', 'bottle', true],
                [1.0,   '1 Can',    'can',    false],
                [1.0,   '1 Packet', 'packet', false],
                [6.0,   '6 Pack',   'pack',   false],
            ],

            // ---- Health & Pharmacy (per strip / bottle / piece) -
            'health-pharmacy' => [
                [1.0,   '1 Strip',  'strip',  true],
                [1.0,   '1 Bottle', 'bottle', false],
                [1.0,   '1 Piece',  'piece',  false],
                [1.0,   '1 Box',    'box',    false],
            ],

            // ---- Kitchen Essentials (per piece) ----------------
            'kitchen-essentials' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Set',    'set',    false],
                [1.0,   '1 Pack',   'pack',   false],
            ],

            // ---- Laundry (per kg / piece / bottle) -------------
            'laundry' => [
                [1.0,   '1 kg',     'kg',     true],
                [1.0,   '1 Bottle', 'bottle', false],
                [1.0,   '1 Bar',    'bar',    false],
                [2.0,   '2 kg',     'kg',     false],
            ],

            // ---- Air Fresheners (per piece / bottle / spray) ---
            'air-fresheners' => [
                [1.0,   '1 Piece',  'piece',  true],
                [1.0,   '1 Spray',  'spray',  false],
                [1.0,   '1 Bottle', 'bottle', false],
            ],
        ];

        $opts = $definitions[$slug] ?? [
            [1.0, '1 Piece', 'piece', true],
        ];

        $result = [];
        foreach ($opts as [$multiplier, $label, $unit, $isPrimary]) {
            $price = round($basePrice * $multiplier, 2);
            $result[] = [
                'label'   => $label,
                'value'   => (string) $multiplier,
                'price'   => $price,
                'unit'    => $unit,
                'primary' => $isPrimary,
            ];
        }

        return $result;
    }

} // end if !function_exists

// ============================================================
// Convenience: build an HTML <select> for a product's units
// ============================================================
if (!function_exists('renderUnitSelect')) {
    /**
     * Renders an HTML <select> element for unit/quantity selection.
     *
     * @param string $productId  Unique product identifier (for cart JS hooks)
     * @param string $slug       Category slug
     * @param float  $basePrice  Selling price per primary unit
     * @param string $extraClass Optional extra CSS class
     */
    function renderUnitSelect(
        string $productId,
        string $slug,
        float  $basePrice,
        string $extraClass = ''
    ): string {
        $opts = getPurchaseOptionsForCategory($slug, $basePrice);
        $html  = '<select class="unit-select ' . htmlspecialchars($extraClass, ENT_QUOTES) . '" ';
        $html .= 'data-product-id="' . htmlspecialchars($productId, ENT_QUOTES) . '" ';
        $html .= 'data-base-price="' . number_format($basePrice, 2, '.', '') . '">';
        foreach ($opts as $opt) {
            $selected = $opt['primary'] ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($opt['value'], ENT_QUOTES) . '" ';
            $html .= 'data-price="' . number_format($opt['price'], 2, '.', '') . '" ';
            $html .= 'data-unit="' . htmlspecialchars($opt['unit'], ENT_QUOTES) . '"' . $selected . '>';
            $html .= htmlspecialchars($opt['label'], ENT_QUOTES);
            $html .= ' — ₹' . number_format($opt['price'], 2);
            $html .= '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}
