<?php
/**
 * scripts/generate_category_catalog.php
 * Indexes all products from categories/*.php and saves to includes/category_catalog_data.php
 */

$files = glob(__DIR__ . '/../categories/*.php');
$catalog = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $catSlug = basename($file, '.php');

    // 1. JSON blocks with id, name, price / weights
    if (preg_match_all('/\{"id":(\d+),"name":"([^"]+)",(?:.*?"price":([0-9\.]+)|.*?"weights":\[(.*?)\])/s', $content, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $name = trim($row[2]);
            $lower = strtolower($name);
            $price = isset($row[3]) && $row[3] !== '' ? (float)$row[3] : 0.0;
            $weights = [];
            if (isset($row[4]) && $row[4] !== '') {
                if (preg_match_all('/\{"label":"([^"]+)","price":([0-9\.]+)\}/', $row[4], $wm, PREG_SET_ORDER)) {
                    foreach ($wm as $w) {
                        $weights[strtolower(trim($w[1]))] = (float)$w[2];
                        if ($price == 0.0) {
                            $price = (float)$w[2];
                        }
                    }
                }
            }
            if ($price > 0 && !isset($catalog[$lower])) {
                $catalog[$lower] = [
                    'id'       => (int)$row[1],
                    'name'     => $name,
                    'price'    => $price,
                    'category' => $catSlug,
                    'weights'  => $weights,
                ];
            }
        }
    }

    // 2. PHP array syntax: ['id' => ..., 'name' => '...', 'price' => ...]
    if (preg_match_all('/\[\s*["\']id["\']\s*=>\s*(\d+),\s*["\']name["\']\s*=>\s*["\']([^"\']+)["\'].*?["\']price["\']\s*=>\s*([0-9\.]+)/s', $content, $m2, PREG_SET_ORDER)) {
        foreach ($m2 as $row) {
            $name = trim($row[2]);
            $lower = strtolower($name);
            $price = (float)$row[3];
            if ($price > 0 && !isset($catalog[$lower])) {
                $catalog[$lower] = [
                    'id'       => (int)$row[1],
                    'name'     => $name,
                    'price'    => $price,
                    'category' => $catSlug,
                    'weights'  => [],
                ];
            }
        }
    }

    // 3. PHP array syntax with weights: ['id' => ..., 'name' => '...', 'weights' => [...]]
    if (preg_match_all('/\[\s*["\']id["\']\s*=>\s*(\d+),\s*["\']name["\']\s*=>\s*["\']([^"\']+)["\'].*?["\']weights["\']\s*=>\s*\[(.*?)\]/s', $content, $m3, PREG_SET_ORDER)) {
        foreach ($m3 as $row) {
            $name = trim($row[2]);
            $lower = strtolower($name);
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
            if ($price > 0 && !isset($catalog[$lower])) {
                $catalog[$lower] = [
                    'id'       => (int)$row[1],
                    'name'     => $name,
                    'price'    => $price,
                    'category' => $catSlug,
                    'weights'  => $weights,
                ];
            }
        }
    }
}

// 4. Index store fallback products from functions.php (including Fresh Bread Loaf, etc.)
require_once __DIR__ . '/../functions.php';
if (function_exists('getStoreFallbackProducts')) {
    foreach (getStoreFallbackProducts() as $fb) {
        $name = trim($fb['name']);
        $lower = strtolower($name);
        if (!isset($catalog[$lower])) {
            $catalog[$lower] = [
                'id'       => (int)$fb['id'],
                'name'     => $name,
                'price'    => (float)$fb['price'],
                'category' => $fb['category_name'] ?? 'general',
                'weights'  => [],
            ];
        }
    }
}

// Save to includes/category_catalog_data.php
$outPath = __DIR__ . '/../includes/category_catalog_data.php';
$export = var_export($catalog, true);
$code = "<?php\n// Auto-generated category catalog cache. Generated " . date('Y-m-d H:i:s') . ".\ndeclare(strict_types=1);\n\nreturn " . $export . ";\n";
file_put_contents($outPath, $code);

echo "Successfully exported " . count($catalog) . " items to $outPath\n";
