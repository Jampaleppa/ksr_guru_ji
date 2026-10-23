<?php
$_SERVER['SCRIPT_NAME'] = '/index.php';
require 'functions.php';
$cases = [
    'images/Cleans/Floor Cleaner 130.jpg',   // subdir path that no longer exists -> flat fallback
    'images/Figaro Olive Oil.jpg',           // flat path
    'cake.jpg',                              // root file
    'images/oil/Figaro Olive Oil.jpg',       // ghost subdir -> flat fallback
];
foreach ($cases as $c) {
    echo str_pad($c, 45) . ' => ' . normalizeImagePath($c) . PHP_EOL;
}
$_SERVER['SCRIPT_NAME'] = '/categories/cooking-oil.php';
echo 'from categories page: ' . normalizeImagePath('images/Figaro Olive Oil.jpg') . PHP_EOL;
echo 'from categories page: ' . normalizeImagePath('images/oil/Figaro Olive Oil.jpg') . PHP_EOL;
