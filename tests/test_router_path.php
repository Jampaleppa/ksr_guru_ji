<?php
$testPaths = ['/manifest.json', '/sw.js', '/downloads/ksr-guru-ji.apk', '/checkout.php', '/'];

foreach ($testPaths as $rawUri) {
    $uriPath = parse_url($rawUri, PHP_URL_PATH);
    $uriPath = urldecode($uriPath);
    $cleanPath = ltrim($uriPath, '/');
    
    $blockedPattern = '/(\.(sql|env|log|md|json|bat|ps1|sh|yml|yaml|ini|sqlite|lock|git|bak|conf|txt)$|^(\.|\\/|\.env|logs\/|scratch\/|\.vercel\/|includes\/|payment-core\/|database\/|admin\/database\/|WhatsApp\/telehealth\/seed|delivery\/seed|api\/debug))/i';
    
    $isManifest = ($cleanPath === 'manifest.json');
    $isSw       = ($cleanPath === 'sw.js');
    $isDownload = (bool)preg_match('#^downloads/(.+\.(apk|aab|mobileconfig))$#i', $cleanPath);
    $isBlocked  = preg_match($blockedPattern, $cleanPath) && !$isManifest;
    
    echo "Path: $rawUri\n";
    echo "  cleanPath    = '$cleanPath'\n";
    echo "  isManifest   = " . ($isManifest ? 'YES ✅' : 'no') . "\n";
    echo "  isSwJs       = " . ($isSw      ? 'YES ✅' : 'no') . "\n";
    echo "  isDownload   = " . ($isDownload ? 'YES ✅' : 'no') . "\n";
    echo "  wouldBlock   = " . ($isBlocked  ? 'YES ❌' : 'no') . "\n\n";
}
