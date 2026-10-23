<?php
require 'functions.php';
$ps = getProducts();
echo 'count=' . count($ps) . PHP_EOL;
if ($ps) {
    echo 'first_images=' . ($ps[0]['images'] ?? 'NO_IMAGES_KEY') . PHP_EOL;
    $norm = normalizeImagePath($ps[0]['images'] ?? '');
    echo 'normalized=' . $norm . PHP_EOL;
    echo 'file_exists=' . (is_file(__DIR__ . '/' . str_replace('../', '', $norm)) ? 'YES' : 'NO') . PHP_EOL;
}
