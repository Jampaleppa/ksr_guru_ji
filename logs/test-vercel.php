<?php
// Simulate Vercel conditions: no MySQL -> PDO sqlite fallback, categories/ page depth
require 'includes/functions.php';
$pdo = getDBConnection();
echo 'driver=' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . PHP_EOL;
$cats = getCategories();
echo 'categories=' . count($cats) . PHP_EOL;
$prods = getProducts();
echo 'products=' . count($prods) . PHP_EOL;
if ($prods) {
    $p = $prods[0];
    echo 'images_raw=' . ($p['images'] ?? '(none)') . PHP_EOL;
    echo 'normalized=' . normalizeImagePath($p['images'] ?? '') . PHP_EOL;
}
// Simulate a category page: which images would be produced from a path like images/oil/xxx.jpg
$fake = 'images/oil/Figaro Olive Oil.jpg';
echo 'normalize_fake_prefix_fallback=' . normalizeImagePath($fake) . PHP_EOL;
