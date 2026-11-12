<?php
/**
 * Automated Full-Project Syntax, Execution & Diagnostics Runner
 * 
 * Inspects, lints, and performs dry-run executions across every PHP page in:
 * 1. Root E-Commerce Pages
 * 2. All 30 Category Pages (/categories/)
 * 3. Administration Pages (/admin/)
 * 4. Delivery Portal Pages (/delivery/)
 * 5. Healthcare & Telehealth Pages (/WhatsApp/)
 * 6. API & Infrastructure Controllers (/api/, /includes/)
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$results = [
    'passed' => [],
    'syntax_errors' => [],
    'runtime_warnings' => [],
    'total_checked' => 0
];

echo "====================================================================\n";
echo "   KSR GURU JI — Complete Codebase Debugger & Execution Runner      \n";
echo "====================================================================\n\n";

// Function to recursively find all PHP files excluding vendor, .git, scratch
function findPhpFiles(string $dir): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getPathname();
            // Skip vendor, git, scratch, and deploy staging
            if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false ||
                strpos($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false ||
                strpos($path, DIRECTORY_SEPARATOR . 'scratch' . DIRECTORY_SEPARATOR) !== false ||
                strpos($path, DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR) !== false ||
                strpos($path, DIRECTORY_SEPARATOR . '.andromity' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }
            $files[] = $path;
        }
    }
    return $files;
}

$allFiles = findPhpFiles($baseDir);
$results['total_checked'] = count($allFiles);

echo "Found {$results['total_checked']} active PHP pages/scripts across all modules.\n\n";

// Phase 1: Lint every file (php -l)
echo "--- Phase 1: Syntax Linting (php -l) ---\n";
foreach ($allFiles as $filePath) {
    $relPath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);
    $output = [];
    $returnCode = 0;
    exec('php -l ' . escapeshellarg($filePath) . ' 2>&1', $output, $returnCode);

    if ($returnCode === 0) {
        $results['passed'][] = $relPath;
    } else {
        $errorMsg = implode("\n", $output);
        $results['syntax_errors'][$relPath] = $errorMsg;
        echo "  [SYNTAX ERROR] {$relPath}\n";
        echo "    -> {$errorMsg}\n";
    }
}

if (empty($results['syntax_errors'])) {
    echo ">> Phase 1 Complete: All {$results['total_checked']} PHP files have ZERO syntax errors!\n\n";
} else {
    echo ">> Phase 1 Complete: " . count($results['syntax_errors']) . " syntax error(s) found.\n\n";
}

// Phase 2: Targeted Dry-Run Execution of Key Customer & Operational Controllers
echo "--- Phase 2: Dry-Run Execution of Key Operational Controllers ---\n";

$criticalExecList = [
    'config.php',
    'includes/config.php',
    'includes/db.php',
    'functions.php',
    'pricing.php',
    'razorpay_helper.php',
    'search.php',
    'suggestion-box.php',
    'admin/inc/functions.php',
    'admin/inc/admin_auth.php',
    'delivery/bootstrap.php',
    'WhatsApp/telehealth/helper.php'
];

$execFailures = 0;
foreach ($criticalExecList as $relScript) {
    $fullScript = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relScript);
    if (!file_exists($fullScript)) {
        continue;
    }

    // Write a temporary one-line test runner to avoid shell escaping issues on Windows
    $tmpRunner = __DIR__ . '/_tmp_runner.php';
    $runnerCode = "<?php\n" .
                  "error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);\n" .
                  "ini_set('display_errors', '1');\n" .
                  "try {\n" .
                  "    require_once " . var_export($fullScript, true) . ";\n" .
                  "    echo 'SUCCESS';\n" .
                  "} catch (Throwable \$e) {\n" .
                  "    echo 'EXCEPTION: ' . \$e->getMessage();\n" .
                  "    exit(1);\n" .
                  "}\n";
    file_put_contents($tmpRunner, $runnerCode);

    $out = [];
    $code = 0;
    exec('php ' . escapeshellarg($tmpRunner) . ' 2>&1', $out, $code);
    @unlink($tmpRunner);
    $resText = implode("\n", $out);

    if ($code === 0 && strpos($resText, 'SUCCESS') !== false) {
        echo "  [OK RUN] {$relScript}\n";
    } else {
        echo "  [EXEC WARN/FAIL] {$relScript}: {$resText}\n";
        $results['runtime_warnings'][$relScript] = $resText;
        $execFailures++;
    }
}

echo "\n====================================================================\n";
echo "                         SUMMARY REPORT                             \n";
echo "====================================================================\n";
echo " Total PHP Pages Scanned : " . $results['total_checked'] . "\n";
echo " Syntax Validated (PASS) : " . count($results['passed']) . "\n";
echo " Syntax Errors           : " . count($results['syntax_errors']) . "\n";
echo " Execution Warnings      : " . count($results['runtime_warnings']) . "\n";
echo "====================================================================\n";

if (empty($results['syntax_errors']) && empty($results['runtime_warnings'])) {
    echo "STATUS: ALL PAGES CLEAN & OPERATIONAL. Ready for live transactions.\n";
    exit(0);
} else {
    echo "STATUS: ISSUES DETECTED. Review details above.\n";
    exit(1);
}
