<?php
/**
 * scripts/generate_image_variant_manifest.php
 * Builds includes/image_variant_manifest.php from images/ on disk so PHP has
 * O(1) exact filename resolution and multi-image variant lookup both locally
 * and on serverless deployments where images/ is served from CDN.
 */
declare(strict_types=1);

$rootDir = dirname(__DIR__);
$imgDir  = $rootDir . '/images';
$outPath = $rootDir . '/includes/image_variant_manifest.php';

if (!is_dir($imgDir)) {
    fwrite(STDERR, "ERROR: images directory not found at $imgDir\n");
    exit(1);
}

function ksrManifestStem(string $filename): string
{
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $s = preg_replace('/\s*-\s*copy(?:\s*\(\d+\))?$/i', '', $base);
    $s = preg_replace('/\s+\d+(?:\s*(?:g|gm|ml|kg|l|pcs|pc))?$/i', '', $s);
    $s = preg_replace('/[\s_-]+\d+$/i', '', $s);
    return strtolower(trim(preg_replace('/\s+/', ' ', (string)$s)));
}

$filesMap = [];
$stemBuckets = [];

$entries = scandir($imgDir);
if ($entries === false) {
    fwrite(STDERR, "ERROR: Failed to scan $imgDir\n");
    exit(1);
}

sort($entries, SORT_NATURAL | SORT_FLAG_CASE);

foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    if (!is_file($imgDir . '/' . $entry)) {
        continue;
    }
    $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)) {
        continue;
    }
    $lower = strtolower($entry);
    $filesMap[$lower] = $entry;

    $stem = ksrManifestStem($entry);
    if ($stem !== '') {
        $stemBuckets[$stem][] = $entry;
    }
}

$stemsMulti = [];
foreach ($stemBuckets as $stem => $list) {
    if (count($list) > 1) {
        // Put non-numbered / non-copy canonical name first
        usort($list, function (string $a, string $b): int {
            $aCopy = stripos($a, '- copy') !== false ? 1 : 0;
            $bCopy = stripos($b, '- copy') !== false ? 1 : 0;
            if ($aCopy !== $bCopy) {
                return $aCopy <=> $bCopy;
            }
            return strlen($a) <=> strlen($b) ?: strnatcasecmp($a, $b);
        });
        $stemsMulti[$stem] = array_values(array_unique($list));
    }
}

$manifest = [
    'files' => $filesMap,
    'stems' => $stemsMulti,
];

$export = var_export($manifest, true);
$code = "<?php\n// Auto-generated flat image & multi-variant manifest. Generated "
      . date('Y-m-d H:i:s') . ".\n"
      . "// Total files: " . count($filesMap) . " | Multi-variant stems: " . count($stemsMulti) . "\n"
      . "declare(strict_types=1);\n\nreturn " . $export . ";\n";

file_put_contents($outPath, $code);
echo "Generated $outPath (" . count($filesMap) . " files, " . count($stemsMulti) . " multi-image stems, "
   . round(filesize($outPath) / 1024, 1) . " KB)\n";
