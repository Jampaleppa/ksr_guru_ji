<?php
// ============================================================
// includes/category_products.php
// Shared helper: load products for any category page.
// Applies KSR GURU JI pricing rule: Selling = Market + ₹3.00
// Works with MySQL (production) and SQLite (dev/Vercel fallback).
// ============================================================
declare(strict_types=1);

if (!defined('KSR_DEFAULT_PRICE_MARGIN')) {
    define('KSR_DEFAULT_PRICE_MARGIN', 3.00); // NEVER CHANGE
}

/**
 * Load products for a category by its slug.
 *
 * Returns an array of product arrays with keys:
 *   id, name, kn_name, price (selling), market_price, unit,
 *   description, image, category, status
 *
 * Selling price = market_price + KSR_DEFAULT_PRICE_MARGIN (₹3)
 * If market_price = 0, uses price column as selling price directly.
 *
 * @param string $slug      Category slug e.g. 'bath-body'
 * @param string $search    Optional search term
 * @param int    $limit     Max products to return (0 = all)
 * @return array
 */
function getCategoryProducts(string $slug, string $search = '', int $limit = 0): array
{
    static $cache = [];
    $cacheKey = $slug . '|' . $search . '|' . $limit;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    try {
        $pdo    = getDBConnection();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Detect available columns
        if ($driver === 'sqlite') {
            $cols = array_column($pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        } else {
            $cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN, 0);
        }

        $hasMarketPrice = in_array('market_price', $cols, true);
        $hasKnName      = in_array('kn_name', $cols, true);
        $hasUnit        = in_array('unit', $cols, true);
        $hasStatus      = in_array('status', $cols, true);
        $hasCategoryId  = in_array('category_id', $cols, true);

        $marketCol  = $hasMarketPrice ? 'p.market_price' : '0';
        $knNameCol  = $hasKnName ? 'p.kn_name' : "''";
        $unitCol    = $hasUnit ? 'p.unit' : "'piece'";
        $statusCond = $hasStatus ? " AND p.status = 1" : '';

        // Try category join or fallback to slug/category column
        if ($hasCategoryId) {
            $catWhere = "(c.slug = ? OR c.name = ?)";
            $catJoin  = "LEFT JOIN categories c ON c.id = p.category_id";
            $catParams = [$slug, $slug];
        } else {
            $catWhere = "(p.category = ? OR p.category LIKE ?)";
            $catJoin  = "";
            $catParams = [$slug, '%' . $slug . '%'];
        }

        $searchWhere  = '';
        $searchParams = [];
        if ($search !== '') {
            $searchWhere    = " AND (p.name LIKE ? OR $knNameCol LIKE ?)";
            $searchParams[] = '%' . $search . '%';
            $searchParams[] = '%' . $search . '%';
        }

        $limitSql = $limit > 0 ? " LIMIT " . (int) $limit : '';

        $sql = "SELECT
                    p.id,
                    p.name,
                    $knNameCol       AS kn_name,
                    p.price          AS base_price,
                    $marketCol       AS market_price,
                    $unitCol         AS unit,
                    p.description,
                    p.image,
                    p.category,
                    p.mrp
                FROM products p
                $catJoin
                WHERE $catWhere
                  $statusCond
                  $searchWhere
                ORDER BY p.name ASC
                $limitSql";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($catParams, $searchParams));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return $cache[$cacheKey] = [];
        }

        // Apply pricing rule: Selling = Market + ₹3
        $result = [];
        foreach ($rows as $row) {
            $marketPrice  = (float) ($row['market_price'] ?? 0);
            $basePrice    = (float) ($row['base_price']   ?? 0);
            $sellingPrice = $marketPrice > 0
                ? round($marketPrice + KSR_DEFAULT_PRICE_MARGIN, 2)
                : ($basePrice > 0 ? $basePrice : 0);

            $result[] = [
                'id'           => (int) $row['id'],
                'name'         => (string) ($row['name']   ?? ''),
                'kn_name'      => (string) ($row['kn_name'] ?? ''),
                'price'        => $sellingPrice,
                'market_price' => $marketPrice,
                'selling_price'=> $sellingPrice,
                'unit'         => (string) ($row['unit']        ?? 'piece'),
                'description'  => (string) ($row['description'] ?? ''),
                'image'        => (string) ($row['image']       ?? ''),
                'category'     => (string) ($row['category']    ?? $slug),
                'mrp'          => (float)  ($row['mrp']         ?? 0),
            ];
        }

        return $cache[$cacheKey] = $result;

    } catch (Throwable $e) {
        error_log("getCategoryProducts($slug): " . $e->getMessage());
        return [];
    }
}

/**
 * Convenience: returns product count for a category.
 */
function getCategoryProductCount(string $slug): int
{
    return count(getCategoryProducts($slug));
}

/**
 * Build a product card data array suitable for JSON encoding to JS.
 * Normalises prices and adds display-ready fields.
 *
 * @param array  $products   From getCategoryProducts()
 * @param string $slug       Category slug (for unit options)
 * @return array
 */
function buildProductCardsJson(array $products, string $slug = ''): array
{
    if (function_exists('getPurchaseOptionsForCategory') && $slug) {
        $unitOptions = getPurchaseOptionsForCategory($slug);
    } else {
        $unitOptions = null;
    }

    return array_map(function (array $p) use ($unitOptions) {
        return [
            'id'           => $p['id'],
            'name'         => $p['name'],
            'nameKn'       => $p['kn_name'] ?: $p['name'],
            'price'        => $p['price'],
            'marketPrice'  => $p['market_price'],
            'sellingPrice' => $p['selling_price'],
            'unit'         => $p['unit'],
            'description'  => $p['description'],
            'image'        => $p['image'],
            'category'     => $p['category'],
            'options'      => $unitOptions,
        ];
    }, $products);
}
