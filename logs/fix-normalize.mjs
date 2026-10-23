import fs from "fs";
import path from "path";

const newFn = `// ============================================================
// HELPER: Normalize image path (Windows -> root-absolute web path)
// Falls back to the flat images/ copy when a subdirectory file is missing.
// ============================================================
if (!function_exists('normalizeImagePath')) {
    function normalizeImagePath(?string $rawPath): string {
        if (empty($rawPath)) return '';
        $clean = str_replace('\\\\', '/', $rawPath);
        if (preg_match('#(?:.*)(images/.*)$#i', $clean, $matches)) {
            $imagePath = preg_replace('#/+#', '/', $matches[1]);
            $localPath = __DIR__ . '/../' . $imagePath;
            if (!is_file($localPath)) {
                $flatPath = 'images/' . basename($imagePath);
                if (is_file(__DIR__ . '/../' . $flatPath)) {
                    $imagePath = $flatPath;
                }
            }
            // Root-absolute path works at every page depth
            return '/' . $imagePath;
        }
        // File may live at the project root (e.g. cake.jpg, Banner.png)
        $base = basename($clean);
        if (is_file(__DIR__ . '/../' . $base)) {
            return '/' . $base;
        }
        return '/images/' . $base;
    }
}`;

const dir = "categories";
const changed = [];
const skipped = [];
for (const f of fs.readdirSync(dir)) {
  if (!f.endsWith(".php")) continue;
  const p = path.join(dir, f);
  let src = fs.readFileSync(p, "utf8");
  const headerIdx = src.search(/\/\/ HELPER: Normalize image path/);
  if (headerIdx === -1) {
    skipped.push(f + " (no header)");
    continue;
  }
  const fnIdx = src.indexOf(
    "if (!function_exists('normalizeImagePath')) {",
    headerIdx,
  );
  if (fnIdx === -1) {
    skipped.push(f + " (no fn)");
    continue;
  }
  const endMarker = "\n}\n}";
  const endIdx = src.indexOf(endMarker, fnIdx);
  if (endIdx === -1) {
    skipped.push(f + " (no end)");
    continue;
  }
  const blockStart = src.lastIndexOf("// ===", fnIdx);
  src = src.slice(0, blockStart) + newFn + src.slice(endIdx + endMarker.length);
  fs.writeFileSync(p, src);
  changed.push(f);
}
console.log("updated:", changed.join(", "));
console.log("skipped:", skipped.join(" | "));
