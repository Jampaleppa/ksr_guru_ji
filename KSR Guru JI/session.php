<?php
// ============================================================
// includes/session.php - Session Management
// ============================================================

// --- Start session if not already started ---
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    
    // Set session name (optional)
    session_name('KSR_SESSION');
    
    // Start session
    session_start();
}

// --- Regenerate session ID periodically (every 5 requests) ---
function regenerateSessionIfNeeded() {
    if (!isset($_SESSION['_regenerate_count'])) {
        $_SESSION['_regenerate_count'] = 0;
    }
    $_SESSION['_regenerate_count']++;
    
    if ($_SESSION['_regenerate_count'] >= 5) {
        session_regenerate_id(true);
        $_SESSION['_regenerate_count'] = 0;
    }
}
regenerateSessionIfNeeded();

// --- Flash message system ---
/**
 * Set flash message.
 *
 * @param string $key
 * @param mixed $value
 * @return void
 */
function setFlash(string $key, $value): void {
    $_SESSION['_flash'][$key] = $value;
}

/**
 * Get flash message.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getFlash(string $key, $default = null) {
    if (isset($_SESSION['_flash'][$key])) {
        $value = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]); // Remove after reading
        return $value;
    }
    return $default;
}

/**
 * Check if flash message exists.
 *
 * @param string $key
 * @return bool
 */
function hasFlash(string $key): bool {
    return isset($_SESSION['_flash'][$key]);
}

/**
 * Get or set flash message helper.
 *
 * @param string $key
 * @param mixed $value
 * @return mixed
 */
function flashMessage(string $key, $value = null) {
    if ($value !== null) {
        setFlash($key, $value);
    } else {
        return getFlash($key);
    }
}

// --- Session helpers ---
/**
 * Check if the user is logged in.
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is admin.
 *
 * @return bool
 */
function isAdmin(): bool {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin']);
}

/**
 * Check if current user is super admin.
 *
 * @return bool
 */
function isSuperAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

/**
 * Check if current user is a delivery boy.
 *
 * @return bool
 */
function isDeliveryBoy(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'delivery';
}

/**
 * Get current logged in user details.
 *
 * @return array|null
 */
function getCurrentUser(): ?array {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['role'] ?? 'customer'
        ];
    }
    return null;
}

// --- Login / Logout ---
/**
 * Log in a user.
 *
 * @param mixed $userId
 * @param string $name
 * @param string $email
 * @param string $role
 * @return void
 */
function loginUser($userId, string $name, string $email, string $role = 'customer'): void {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['role'] = $role;
    $_SESSION['login_time'] = time();
    session_regenerate_id(true);
}

/**
 * Log out the current user.
 *
 * @return void
 */
function logoutUser(): void {
    // Clear all session variables
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
}

// --- CSRF Token generation ---
/**
 * Generate CSRF token.
 *
 * @return string
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token.
 *
 * @param string $token
 * @return bool
 */
function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// --- Cart functions (for guest and logged-in users) ---
/**
 * Get total items in cart.
 *
 * @return int
 */
function getCartCount(): int {
    return isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'qty')) : 0;
}

/**
 * Get total cart price.
 *
 * @return float|int
 */
function getCartTotal() {
    if (!isset($_SESSION['cart'])) return 0;
    return array_sum(array_map(fn($item) => $item['price'] * $item['qty'], $_SESSION['cart']));
}

/**
 * Add a product to the cart.
 *
 * @param mixed $productId
 * @param int $qty
 * @param string|null $variant
 * @param float|int|null $price
 * @return void
 */
function addToCart($productId, int $qty = 1, ?string $variant = null, $price = null): void {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Build unique key
    $key = $productId;
    if ($variant !== null) {
        $key .= ':' . md5($variant);
    }
    
    // Check if exists
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $key) {
            $item['qty'] += $qty;
            return;
        }
    }
    
    // Add new item
    $item = [
        'id' => $key,
        'product_id' => $productId,
        'qty' => $qty,
        'variant' => $variant,
        'price' => $price ?? 0,
        'name' => ''
    ];
    $_SESSION['cart'][] = $item;
}

/**
 * Remove an item from the cart.
 *
 * @param mixed $itemId
 * @return void
 */
function removeFromCart($itemId): void {
    if (isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array_filter($_SESSION['cart'], fn($item) => $item['id'] != $itemId);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
}

/**
 * Clear the cart.
 *
 * @return void
 */
function clearCart(): void {
    unset($_SESSION['cart']);
}

// --- Security: Check if session has expired ---
function checkSessionTimeout() {
    if (isset($_SESSION['login_time'])) {
        $maxLifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600;
        if (time() - $_SESSION['login_time'] > $maxLifetime) {
            logoutUser();
            return false;
        }
    }
    return true;
}

// --- Auto-check session on every request ---
if (isset($_SESSION['user_id'])) {
    checkSessionTimeout();
}