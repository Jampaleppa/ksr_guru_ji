<?php
/**
 * includes/category_image_helpers.php
 * Shared flat-image normalization, multi-image variant lookup, and DB+fallback
 * catalog merge helpers for all category pages.
 */
declare(strict_types=1);

if (!function_exists('ksrGetImageManifest')) {
    /**
     * Load the pre-generated image manifest (works on serverless without images/ on disk).
     */
    function ksrGetImageManifest(): array
    {
        static $manifest = null;
        if ($manifest !== null) {
            return $manifest;
        }
        $manifestPath = __DIR__ . '/image_variant_manifest.php';
        if (is_file($manifestPath)) {
            $loaded = require $manifestPath;
            if (is_array($loaded)) {
                $manifest = $loaded;
                return $manifest;
            }
        }
        $manifest = ['files' => [], 'stems' => []];
        return $manifest;
    }
}

if (!function_exists('ksrComputeImageStem')) {
    /**
     * Compute normalized product stem from a filename for matching multi-image variants.
     */
    function ksrComputeImageStem(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $s = preg_replace('/\s*-\s*copy(?:\s*\(\d+\))?$/i', '', $base);
        $s = preg_replace('/\s+\d+(?:\s*(?:g|gm|ml|kg|l|pcs|pc))?$/i', '', $s);
        $s = preg_replace('/[\s_-]+\d+$/i', '', $s);
        return strtolower(trim(preg_replace('/\s+/', ' ', (string)$s)));
    }
}

if (!function_exists('ksrNormalizeFlatImage')) {
    /**
     * Normalize any Windows/Unix/subdir image path to flat web-relative '../images/<file>'.
     * Uses exact disk casing from the manifest so Linux/CDN requests never 404.
     */
    function ksrNormalizeFlatImage(?string $rawPath, string $prefix = '../images/'): string
    {
        $rawPath = trim((string)$rawPath);
        if ($rawPath === '') {
            return '';
        }
        if (preg_match('#^(?:https?://|data:)#i', $rawPath)) {
            return $rawPath;
        }

        // Convert double-escaped or single backslashes and extract the basename cleanly
        $clean = str_replace(['\\\\', '\\'], '/', $rawPath);
        $clean = strtok($clean, '?#') ?: $clean;
        $base  = basename($clean);
        if ($base === '' || $base === '.' || $base === '..') {
            return '';
        }

        $manifest = ksrGetImageManifest();
        $lower    = strtolower($base);
        if (isset($manifest['files'][$lower])) {
            return $prefix . $manifest['files'][$lower];
        }

        // Local disk fallback check if a newly added image isn't in manifest yet
        $diskPath = __DIR__ . '/../images/' . $base;
        if (is_file($diskPath)) {
            return $prefix . $base;
        }

        return $prefix . $base;
    }
}

if (!function_exists('ksrMapDbFallbackImage')) {
    /**
     * Map seeded DB product names (when DB image column is NULL) to real product photos.
     */
    function ksrMapDbFallbackImage(string $productName, string $prefix = '../images/'): string
    {
        static $map = [
            'basmati rice'       => 'India Gate Basmati Rice Classic.jpg',
            'toor dal'           => 'Toor Dal.jpg',
            'sunflower oil'      => 'Sunflower oil.jpg',
            'coconut oil'        => 'Cold Pressed Coconut Oil.jpg',
            'full cream milk'    => 'Amul Gold Full Cream Milk.jpg',
            'curd'               => 'Curd (yogurt).jpg',
            'whole wheat bread'  => 'Whole Wheat Bread.jpg',
            'biscuits - marie'   => 'Biscuits.jpg',
            'maggi noodles'      => 'Maggi Masala Noodles.jpg',
            'red chilli powder'  => 'Red chili powder.jpg',
            'turmeric powder'    => 'Turmeric Powder.jpg',
            'salt'               => 'Salt.jpg',
            'surf excel'         => 'Laundry Detergent 300.jpg',
            'vim bar'            => 'Dishwash Bar 10.jpg',
            'colgate paste'      => 'Colgate Strong Teeth Toothpaste 90.jpg',
            'lifebuoy soap'      => 'Lifebuoy Total Soap 40.jpg',
        ];
        $key = strtolower(trim($productName));
        if (isset($map[$key])) {
            return ksrNormalizeFlatImage($map[$key], $prefix);
        }
        return '';
    }
}

if (!function_exists('ksrGetImageVariants')) {
    /**
     * Return up to $max matching image variant paths (with primary image first)
     * for a product card gallery switcher.
     */
    function ksrGetImageVariants(?string $primaryPath, string $productName = '', string $prefix = '../images/', int $max = 5): array
    {
        $normalizedPrimary = ksrNormalizeFlatImage($primaryPath, $prefix);
        if ($normalizedPrimary === '' && $productName !== '') {
            $normalizedPrimary = ksrMapDbFallbackImage($productName, $prefix);
        }

        $manifest = ksrGetImageManifest();
        $variants = [];

        if ($normalizedPrimary !== '' && !preg_match('#^(?:https?://|data:)#i', $normalizedPrimary)) {
            $base = basename($normalizedPrimary);
            $stem = ksrComputeImageStem($base);
            $variants[] = $normalizedPrimary;
            if ($stem !== '' && isset($manifest['stems'][$stem])) {
                foreach ($manifest['stems'][$stem] as $altFile) {
                    $altPath = $prefix . $altFile;
                    if (!in_array($altPath, $variants, true)) {
                        $variants[] = $altPath;
                    }
                    if (count($variants) >= $max) {
                        break;
                    }
                }
            }
        } elseif ($normalizedPrimary !== '') {
            $variants[] = $normalizedPrimary;
        }

        // Also match variants directly by normalized product name if still single image
        if (count($variants) < 2 && $productName !== '') {
            $nameStem = ksrComputeImageStem(preg_replace('/\s*\([^)]*\)\s*$/', '', $productName));
            if ($nameStem !== '' && isset($manifest['stems'][$nameStem])) {
                foreach ($manifest['stems'][$nameStem] as $altFile) {
                    $altPath = $prefix . $altFile;
                    if (!in_array($altPath, $variants, true)) {
                        $variants[] = $altPath;
                    }
                    if (count($variants) >= $max) {
                        break;
                    }
                }
            }
        }

        return array_slice($variants, 0, $max);
    }
}

if (!function_exists('ksrNormalizeProductKey')) {
    /**
     * Canonical lowercase key used to deduplicate DB and fallback catalog rows by name.
     */
    function ksrNormalizeProductKey(string $name): string
    {
        $clean = (string)preg_replace('/\s*\([^)]*\)\s*$/', '', $name);
        $clean = strtolower(trim((string)preg_replace('/\s+/', ' ', $clean)));
        return $clean;
    }
}

if (!function_exists('ksrMergeCategoryProducts')) {
    /**
     * Merge DB items onto full fallback catalog without hiding fallback items or
     * colliding on DB auto-increment IDs (1..20) vs category JSON IDs.
     *
     * Options:
     *   'nameField'          => string  (default 'name')
     *   'imageField'         => string  (default 'image')
     *   'prefix'             => string  (default '../images/')
     *   'updateMatchedPrice' => bool    (default false; set true only for matching single-unit items)
     *   'dbIdOffset'         => int     (default 90000 for non-colliding unmatched DB rows)
     */
    function ksrMergeCategoryProducts(array $dbItems, array $fallbackItems, array $opts = []): array
    {
        $nameField          = (string)($opts['nameField'] ?? 'name');
        $altNameField       = (string)($opts['altNameField'] ?? ($nameField === 'name' ? 'nameEn' : 'name'));
        $imageField         = (string)($opts['imageField'] ?? 'image');
        $prefix             = (string)($opts['prefix'] ?? '../images/');
        $updateMatchedPrice = (bool)($opts['updateMatchedPrice'] ?? false);
        $dbIdOffset         = (int)($opts['dbIdOffset'] ?? 90000);

        $merged = $fallbackItems;

        // Index fallback entries by canonical name
        $indexByKey = [];
        foreach ($merged as $idx => &$item) {
            $itemName = (string)($item[$nameField] ?? $item[$altNameField] ?? '');
            $key = ksrNormalizeProductKey($itemName);
            if ($key !== '' && !isset($indexByKey[$key])) {
                $indexByKey[$key] = $idx;
            }
            // Normalize existing primary image & attach multi-image variants
            $rawImg = (string)($item[$imageField] ?? $item['imageUrl'] ?? $item['imageurl'] ?? $item['image_url'] ?? $item['image'] ?? '');
            $normImg = ksrNormalizeFlatImage($rawImg, $prefix);
            $item[$imageField] = $normImg;
            $item['images']    = ksrGetImageVariants($normImg, $itemName, $prefix);
        }
        unset($item);

        // Process DB items: update matched entries OR prepend new DB-only entries
        $extras = [];
        $usedIds = [];
        foreach ($merged as $m) {
            if (isset($m['id'])) {
                $usedIds[(int)$m['id']] = true;
            }
        }

        foreach ($dbItems as $dbItem) {
            $dbName = (string)($dbItem[$nameField] ?? $dbItem[$altNameField] ?? '');
            $key    = ksrNormalizeProductKey($dbName);
            if ($key !== '' && isset($indexByKey[$key])) {
                $idx = $indexByKey[$key];
                if ($updateMatchedPrice && isset($dbItem['price']) && (float)$dbItem['price'] > 0) {
                    $merged[$idx]['price'] = (float)$dbItem['price'];
                    if (isset($merged[$idx]['weights']) && is_array($merged[$idx]['weights'])) {
                        foreach ($merged[$idx]['weights'] as &$w) {
                            $w['price'] = (float)$dbItem['price'];
                        }
                        unset($w);
                    }
                    if (isset($merged[$idx]['variants']) && is_array($merged[$idx]['variants'])) {
                        foreach ($merged[$idx]['variants'] as &$v) {
                            $v['price'] = (float)$dbItem['price'];
                        }
                        unset($v);
                    }
                }
                if (!empty($dbItem[$imageField])) {
                    $normDbImg = ksrNormalizeFlatImage((string)$dbItem[$imageField], $prefix);
                    if ($normDbImg !== '') {
                        $merged[$idx][$imageField] = $normDbImg;
                        $merged[$idx]['images']    = ksrGetImageVariants($normDbImg, $dbName, $prefix);
                    }
                }
                continue;
            }

            // Ensure unique non-colliding ID for DB-only item
            $rawId = (int)($dbItem['id'] ?? 0);
            $newId = ($rawId > 0 && !isset($usedIds[$rawId])) ? $rawId : ($dbIdOffset + max(1, $rawId));
            while (isset($usedIds[$newId])) {
                $newId++;
            }
            $usedIds[$newId] = true;
            $dbItem['id'] = $newId;

            $rawImg = (string)($dbItem[$imageField] ?? $dbItem['imageUrl'] ?? $dbItem['imageurl'] ?? $dbItem['image_url'] ?? $dbItem['image'] ?? '');
            $normImg = ksrNormalizeFlatImage($rawImg, $prefix);
            if ($normImg === '') {
                $normImg = ksrMapDbFallbackImage($dbName, $prefix);
            }
            $dbItem[$imageField] = $normImg;
            $dbItem['images']    = ksrGetImageVariants($normImg, $dbName, $prefix);
            $extras[] = $dbItem;
        }

        if (!empty($extras)) {
            $merged = array_merge($extras, $merged);
        }

        return $merged;
    }
}
