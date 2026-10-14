<?php
// ============================================================
// includes/config.php - Application Configuration
// ============================================================

require_once __DIR__ . '/../logger.php';

// --- Environment detection ---
$env = getenv('APP_ENV') ?: 'production'; // 'production', 'development', 'staging'
define('APP_ENV', $env);
define('IS_DEVELOPMENT', $env === 'development');

// --- Site URLs ---
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
define('BASE_URL', $protocol . $host . $basePath);
define('BASE_PATH', realpath(__DIR__ . '/../') . '/');

// --- Database configuration ---
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'sqlXXX.infinityfree.com');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'if0_43170510_ksr_guru');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'if0_43170510');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: 'YOUR_INFINITYFREE_DB_PASSWORD');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// --- Application settings ---
define('SITE_NAME', 'KSR GURU JI');
define('SITE_TAGLINE', 'Everything Your Family Needs, Every Day');
define('ADMIN_EMAIL', 'support@ksrguru.com');
define('CONTACT_PHONE', '+91 8088584421');
define('CONTACT_PHONE2', '+91 8762474406');

// --- Cart & Delivery settings ---
define('DELIVERY_CHARGE', 15.00);
define('COD_LIMIT', 2000);
define('FREE_DELIVERY_THRESHOLD', 500);
define('MIN_ORDER_AMOUNT', 100);

// --- Security ---
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_ATTEMPT_WINDOW', 15); // minutes

// --- Upload paths ---
define('UPLOAD_DIR', BASE_PATH . 'uploads/');
define('PRODUCT_IMAGE_DIR', UPLOAD_DIR . 'products/');
define('CATEGORY_IMAGE_DIR', UPLOAD_DIR . 'categories/');
define('TEMP_DIR', UPLOAD_DIR . 'temp/');

// --- Timezone ---
date_default_timezone_set('Asia/Kolkata');

// --- Error reporting based on environment ---
if (IS_DEVELOPMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . 'logs/error.log');
}

// --- Ensure required directories exist ---
$dirs = [UPLOAD_DIR, PRODUCT_IMAGE_DIR, CATEGORY_IMAGE_DIR, TEMP_DIR, BASE_PATH . 'logs/'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// --- Load database connection ---
require_once __DIR__ . '/db.php';

// --- Helper functions for config ---
function getBaseUrl() {
    return BASE_URL;
}

function getAssetUrl($path = '') {
    return BASE_URL . 'assets/' . ltrim($path, '/');
}

function siteName() {
    return SITE_NAME;
}