<?php
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('d:/KSR GURU JI/WhatsApp'));
foreach ($it as $file) {
    if ($file->getExtension() == 'php') {
        exec('php -l "' . $file->getPathname() . '" 2>&1', $out, $ret);
        if ($ret !== 0) {
            echo $file->getPathname() . "\n";
            echo implode("\n", $out) . "\n------------------------\n";
        }
        $out = [];
    }
}
?>
