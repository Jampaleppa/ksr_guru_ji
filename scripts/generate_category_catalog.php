<?php
/**
 * scripts/generate_category_catalog.php
 * Indexes all products from categories/*.php (and *-fallback.json) and saves to includes/category_catalog_data.php
 */

$files = glob(__DIR__ . '/../categories/*.php');
$catalog = [];

$addEntry = function (array $entry) use (&$catalog): void {
    $name = trim((string)($entry['name'] ?? ''));
    $lower = strtolower($name);
    if ($lower === '' || (float)($entry['price'] ?? 0) <= 0) {
        return;
    }
    if (!isset($catalog[$lower])) {
        $catalog[$lower] = $entry;
    }
};

foreach ($files as $file) {
    $content = file_get_contents($file);
    $catSlug = basename($file, '.php');

    // 1. JSON blocks (single-line objects) with id, name|nameEn, and price|weights|variants
    if (preg_match_all('/\{"id":(\d+),"(?:name|nameEn)":"([^"]+)",([^\r\n]+)\}/', $content, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $rawName = trim($row[2]);
            $decoded = json_decode('"' . str_replace("\\'", "'", $rawName) . '"');
            $name = is_string($decoded) ? $decoded : stripslashes($rawName);
            $body = $row[3];
            $weights = [];
            $price = 0.0;
            if (preg_match('/"(?:weights|variants)":\[(.*?)\]/', $body, $wmBlock)) {
                if (preg_match_all('/\{"(?:label|size)":"([^"]+)","price":([0-9\.]+)\}/', $wmBlock[1], $wm, PREG_SET_ORDER)) {
                    foreach ($wm as $w) {
                        $weights[strtolower(trim($w[1]))] = (float)$w[2];
                        if ($price == 0.0) {
                            $price = (float)$w[2];
                        }
                    }
                }
            }
            if (preg_match('/"price":([0-9\.]+)/', preg_replace('/"(?:weights|variants)":\[.*?\]/', '', $body), $pm)) {
                $price = (float)$pm[1];
            }
            $addEntry([
                'id'       => (int)$row[1],
                'name'     => $name,
                'price'    => $price,
                'category' => $catSlug,
                'weights'  => $weights,
            ]);
        }
    }

    // 2. PHP array syntax: ['id' => ..., 'name'|'nameEn' => '...', 'price' => ...]
    if (preg_match_all('/\[\s*["\']id["\']\s*=>\s*(\d+),[^\]]*?["\'](?:name|nameEn)["\']\s*=>\s*["\']([^"\']+)["\'][^\]]*?["\']price["\']\s*=>\s*([0-9\.]+)[^\]]*?\]/s', $content, $m2, PREG_SET_ORDER)) {
        foreach ($m2 as $row) {
            $name = trim($row[2]);
            $price = (float)$row[3];
            $weights = [];
            if ($catSlug === 'spices') {
                // Spices prices are per-1kg and selectable by gram steps in UI
                foreach ([5, 10, 50, 100, 150, 200, 250, 300, 500, 1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000] as $g) {
                    $label = $g >= 1000 ? ($g / 1000) . 'kg' : $g . 'g';
                    $weights[$label] = round(($price / 1000.0) * $g, 2);
                }
            }
            $addEntry([
                'id'       => (int)$row[1],
                'name'     => $name,
                'price'    => $price,
                'category' => $catSlug,
                'weights'  => $weights,
            ]);
        }
    }

    // 3. PHP array syntax with weights: ['id' => ..., 'name' => '...', 'weights' => [...]]
    if (preg_match_all('/\[\s*["\']id["\']\s*=>\s*(\d+),\s*["\']name["\']\s*=>\s*["\']([^"\']+)["\'].*?["\']weights["\']\s*=>\s*\[(.*?)\]/s', $content, $m3, PREG_SET_ORDER)) {
        foreach ($m3 as $row) {
            $name = trim($row[2]);
            $weights = [];
            $price = 0.0;
            if (preg_match_all('/\[\s*["\']label["\']\s*=>\s*["\']([^"\']+)["\'],\s*["\']price["\']\s*=>\s*([0-9\.]+)\s*\]/', $row[3], $wm, PREG_SET_ORDER)) {
                foreach ($wm as $w) {
                    $weights[strtolower(trim($w[1]))] = (float)$w[2];
                    if ($price == 0.0) {
                        $price = (float)$w[2];
                    }
                }
            }
            $addEntry([
                'id'       => (int)$row[1],
                'name'     => $name,
                'price'    => $price,
                'category' => $catSlug,
                'weights'  => $weights,
            ]);
        }
    }
}

// 4. Index store fallback products from functions.php (including Fresh Bread Loaf, etc.)
require_once __DIR__ . '/../functions.php';
if (function_exists('getStoreFallbackProducts')) {
    foreach (getStoreFallbackProducts() as $fb) {
        $addEntry([
            'id'       => (int)$fb['id'],
            'name'     => trim($fb['name']),
            'price'    => (float)$fb['price'],
            'category' => $fb['category_name'] ?? 'general',
            'weights'  => [],
        ]);
    }
}

// Save to includes/category_catalog_data.php
$outPath = __DIR__ . '/../includes/category_catalog_data.php';
$export = var_export($catalog, true);
$code = "<?php\n// Auto-generated category catalog cache. Generated " . date('Y-m-d H:i:s') . ".\ndeclare(strict_types=1);\n\nreturn " . $export . ";\n";
file_put_contents($outPath, $code);

echo "Successfully exported " . count($catalog) . " items to $outPath\n";
