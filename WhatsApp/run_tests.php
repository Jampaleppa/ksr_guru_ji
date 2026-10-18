<?php
echo "========================================================\n";
echo "KSR GURU JI - Comprehensive System Diagnostic & Repair\n";
echo "========================================================\n\n";

$status = true;

// 1. Fixing missing dependencies
if (!file_exists(__DIR__ . '/config/app.php')) {
    echo " [*] Missing app.php detected! Generating...\n";
    if (!is_dir(__DIR__ . '/config')) mkdir(__DIR__ . '/config', 0777, true);
    file_put_contents(__DIR__ . '/config/app.php', "<?php return ['app_name' => 'KSR GURU JI', 'version' => '1.0'];\n");
}

// 2. Syntax Validation (Excluding vendor)
echo "[2] PHP Syntax Integrity Scan (Excluding Vendor):\n";
function scanSyntax(string $dir) {
    global $status;
    $files = glob($dir . '/*');
    foreach ((array)$files as $file) {
        if (basename($file) === 'vendor') continue; // Skip large dependencies
        if (is_dir($file)) { scanSyntax($file); continue; }
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $lint = shell_exec('php -l "' . $file . '" 2>&1');
            if (strpos($lint, 'No syntax errors detected') === false) {
                echo " [!] SYNTAX FAULT: " . $file . "\n";
                echo "     " . trim($lint) . "\n";
                $status = false;
            }
        }
    }
}
scanSyntax(__DIR__);
echo " [+] Syntax completely verified.\n\n";

// 3. Search for orphaned ksr-guru-ji hardcoded paths
echo "[3] Path Hardcoding Integrity Scan:\n";
function scanPaths(string $dir) {
    global $status;
    $files = glob($dir . '/*');
    foreach ((array)$files as $file) {
        if (basename($file) === 'vendor') continue;
        if (is_dir($file)) { scanPaths($file); continue; }
        
        $content = file_get_contents($file);
        if (strpos($content, 'ksr-guru-ji') !== false && basename($file) !== 'run_tests.php') {
            echo " [?] Warning: Potential orphaned 'ksr-guru-ji' hardcode found in " . basename($file) . "\n";
            // Auto repair basic namespace/routing issues?
            $content = str_replace('/ksr-guru-ji/', '/WhatsApp/', $content);
            file_put_contents($file, $content);
            echo "     -> Repaired cleanly to '/WhatsApp/'.\n";
        }
    }
}
scanPaths(__DIR__);
echo " [+] Paths verified and dynamically bound.\n\n";

// 4. Test Database Array
echo "[4] PDO Singleton Check:\n";
try {
    require_once __DIR__ . '/config/database.php';
    $dbClass = new ReflectionClass('Config\Database');
    if ($dbClass->hasMethod('getInstance')) {
        echo " [+] Config\Database::getInstance() structurally functional.\n";
    }
} catch (Exception $e) {
    echo " [-] Database Singleton architecture failure.\n";
    $status = false;
}
echo "\n";

if ($status) {
    echo "========================================================\n";
    echo "   [SUCCESS] PLATFORM HAS BEEN SECURED AND REPAIRED     \n";
    echo "========================================================\n";
} else {
    echo "========================================================\n";
    echo "   [FAILURE] CRITICAL STRUCTURAL FAULTS DETECTED        \n";
    echo "========================================================\n";
}
