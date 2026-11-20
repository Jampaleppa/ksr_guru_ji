<?php
// api/manifest.php - Dedicated manifest.json serverless handler
// Vercel route: { "src": "/manifest.json", "dest": "/api/manifest.php" }
header('Content-Type: application/manifest+json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');

$mfPath = dirname(__DIR__) . '/manifest.json';
if (is_file($mfPath)) {
    readfile($mfPath);
} else {
    echo json_encode([
        "name"             => "KSR GURU JI â€“ à²¨à²®à³à²® à²…à²‚à²—à²¡à²¿",
        "short_name"       => "KSR Guru Ji",
        "description"      => "Groceries & daily essentials delivered in 45 minutes",
        "start_url"        => "/index.php?source=pwa",
        "scope"            => "/",
        "display"          => "standalone",
        "background_color" => "#ffffff",
        "theme_color"      => "#0f7b3a",
        "icons"            => [
            ["src" => "/icon-192.png", "sizes" => "192x192", "type" => "image/png", "purpose" => "any maskable"],
            ["src" => "/icon-512.png", "sizes" => "512x512", "type" => "image/png", "purpose" => "any maskable"]
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// v2
