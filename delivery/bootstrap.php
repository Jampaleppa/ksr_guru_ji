<?php
// ============================================================
// delivery/bootstrap.php
// Shared core for EVERY delivery page and the delivery API.
// - DB + session
// - Session bridge from the main unified login (role = 'delivery')
// - Guard / auth
// - Exactly 3 delivery boys (roster) — no other names/numbers anywhere
// - WhatsApp message builder + sender (uses admin WhatsAppFactory seam)
// - In-app notification writer
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------------------------------------
// 1. THE ROSTER — the ONLY delivery agents in the whole system.
// ------------------------------------------------------------
const DELIVERY_ROSTER = [
    [
        'code'  => 'JK',
        'name'  => 'JK',
        'phone' => '8088584421',
        'label' => 'Delivery Boy · JK',
    ],
    [
        'code'  => 'JAMPALEPP',
        'name'  => 'jampaleppa',
        'phone' => '8762474406',
        'label' => 'Delivery Boy · jampaleppa',
    ],
    [
        'code'  => 'SHANKAR',
        'name'  => 'shankar',
        'phone' => '9590902742',
        'label' => 'Delivery Boy · shankar',
    ],
];

/** Phone digits → roster entry. */
function delivery_roster_by_phone(string $phone): ?array
{
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === null || $digits === '') {
        return null;
    }
    // Compare on the last 10 digits so +91 / 0 prefixes don't matter.
    $tail = substr($digits, -10);
    foreach (DELIVERY_ROSTER as $boy) {
        if (substr($boy['phone'], -10) === $tail) {
            return $boy;
        }
    }
    return null;
}

/** Name (case-insensitive) → roster entry. */
function delivery_roster_by_name(string $name): ?array
{
    $needle = strtolower(trim($name));
    if ($needle === '') {
        return null;
    }
    foreach (DELIVERY_ROSTER as $boy) {
        if (strtolower($boy['name']) === $needle || strtolower($boy['code']) === $needle) {
            return $boy;
        }
    }
    return null;
}

/** WhatsApp international form (no +, no spaces). */
function delivery_wa_number(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) === 10) {
        return '91' . $digits;
    }
    return $digits;
}

// ------------------------------------------------------------
// 2. Database
// ------------------------------------------------------------
function delivery_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = getDBConnection();
    return $pdo;
}

// ------------------------------------------------------------
// 3. Session bridge (main login sets user_id/user_role)
// ------------------------------------------------------------
function delivery_sync_session(): void
{
    if (isset($_SESSION['user_id']) && !isset($_SESSION['delivery_boy_id'])) {
        $role = $_SESSION['user_role'] ?? '';
        if ($role === 'delivery' || $role === 'delivery_boy') {
            $_SESSION['delivery_boy_id']    = (int) $_SESSION['user_id'];
            $_SESSION['delivery_boy_name']  = $_SESSION['user_name'] ?? 'Delivery Agent';
            $_SESSION['delivery_boy_email'] = $_SESSION['user_email'] ?? '';
            $_SESSION['delivery_boy_phone'] = $_SESSION['user_phone'] ?? '';
        }
    }
}
delivery_sync_session();

function delivery_is_logged_in(): bool
{
    return !empty($_SESSION['delivery_boy_id']);
}

function delivery_id(): int
{
    return (int) ($_SESSION['delivery_boy_id'] ?? 0);
}

function delivery_name(): string
{
    return (string) ($_SESSION['delivery_boy_name'] ?? 'Delivery Agent');
}

/** Phone of the logged-in agent (falls back to the roster match by name). */
function delivery_my_phone(): string
{
    $phone = (string) ($_SESSION['delivery_boy_phone'] ?? '');
    if ($phone !== '') {
        return $phone;
    }
    $boy = delivery_roster_by_name(delivery_name());
    return $boy['phone'] ?? '';
}

/** Roster entry for the logged-in agent (may be null for a fresh admin-made account). */
function delivery_my_roster(): ?array
{
    return delivery_roster_by_name(delivery_name());
}

function delivery_login(string $id, string $name, string $phone = '', string $email = ''): void
{
    session_regenerate_id(true);
    $_SESSION['delivery_boy_id']    = (int) $id;
    $_SESSION['delivery_boy_name']  = $name;
    $_SESSION['delivery_boy_phone'] = $phone;
    $_SESSION['delivery_boy_email'] = $email;
    $_SESSION['user_id']            = (int) $id;
    $_SESSION['user_role']          = 'delivery';
    $_SESSION['user_name']          = $name;
    if ($phone !== '') {
        $_SESSION['user_phone'] = $phone;
    }
    if ($email !== '') {
        $_SESSION['user_email'] = $email;
    }
}

function delivery_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Page guard — sends the agent to the delivery login when not authenticated. */
function delivery_require_login(string $redirectTo = 'dashboard.php'): void
{
    if (!delivery_is_logged_in()) {
        header('Location: login.php?redirect=' . rawurlencode($redirectTo));
        exit;
    }
}

// ------------------------------------------------------------
// 4. Small view helpers
// ------------------------------------------------------------
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** @param int|float|string|null $amount */
function delivery_money($amount): string
{
    return '₹' . number_format((float) $amount, 2);
}

/** Canonical order status pipeline. */
const DELIVERY_FLOW = [
    'pending'    => ['label' => 'Pending',            'icon' => 'fa-hourglass-half',   'tone' => 'warn'],
    'accepted'   => ['label' => 'Accepted',           'icon' => 'fa-thumbs-up',        'tone' => 'info'],
    'picked_up'  => ['label' => 'Picked Up',          'icon' => 'fa-box-open',         'tone' => 'info'],
    'in_transit' => ['label' => 'Out for Delivery',   'icon' => 'fa-truck-fast',       'tone' => 'accent'],
    'delivered'  => ['label' => 'Delivered',          'icon' => 'fa-circle-check',     'tone' => 'ok'],
    'cancelled'  => ['label' => 'Cancelled',          'icon' => 'fa-circle-xmark',     'tone' => 'bad'],
];

function delivery_status_meta(?string $status): array
{
    return DELIVERY_FLOW[$status] ?? [
        'label' => ucfirst((string) $status ?: 'Unknown'),
        'icon'  => 'fa-circle-question',
        'tone'  => 'muted',
    ];
}

function delivery_status_badge(?string $status): string
{
    $m = delivery_status_meta($status);
    return '<span class="ds-badge ds-' . e($m['tone']) . '"><i class="fa-solid ' . e($m['icon']) . '"></i>'
        . e($m['label']) . '</span>';
}

function delivery_time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '—';
    }
    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return intdiv($diff, 60) . 'm ago';
    if ($diff < 86400)  return intdiv($diff, 3600) . 'h ago';
    if ($diff < 604800) return intdiv($diff, 86400) . 'd ago';
    return date('d M Y', $ts);
}

// ------------------------------------------------------------
// 5. Notifications  (in-app + WhatsApp)
// ------------------------------------------------------------

/**
 * Create an in-app notification for a user.
 */
function delivery_notify_user(int $userId, string $title, string $message, string $type = 'info'): int
{
    if ($userId <= 0) {
        return 0;
    }
    try {
        $stmt = delivery_db()->prepare(
            'INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())'
        );
        $stmt->execute([$userId, $title, $message, $type]);
        return (int) delivery_db()->lastInsertId();
    } catch (Throwable $e) {
        error_log('delivery_notify_user: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Queue + attempt a WhatsApp message.
 * Uses the existing provider seam (settings table) so real Meta Cloud API
 * works the moment credentials are filled in; otherwise it logs.
 * Always records a row in whatsapp_logs so nothing is silently lost.
 *
 * @return array{ok:bool,mode:string,error:string}
 */
function delivery_send_whatsapp(string $phone, string $message, string $template = 'delivery_update'): array
{
    $wa = delivery_wa_number($phone);
    if ($wa === '' || strlen($wa) < 10) {
        return ['ok' => false, 'mode' => 'none', 'error' => 'invalid number'];
    }

    $ok    = false;
    $mode  = 'log';
    $error = '';

    try {
        require_once __DIR__ . '/../admin/inc/whatsapp/WhatsAppFactory.php';
        $provider = WhatsAppFactory::make(delivery_db());
        $mode     = $provider->name();
        $ok       = (bool) $provider->sendMessage($wa, $message);
        if (!$ok) {
            $error = 'provider "' . $mode . '" did not accept the message';
        }
    } catch (Throwable $e) {
        $ok    = false;
        $mode  = 'log';
        $error = $e->getMessage();
    }

    try {
        $stmt = delivery_db()->prepare(
            'INSERT INTO whatsapp_logs (recipient, template, message, status, response_payload)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $wa,
            $template,
            $message,
            $ok ? 'sent' : 'failed',
            $ok ? 'accepted by ' . $mode : $error,
        ]);
    } catch (Throwable $e) {
        error_log('delivery_send_whatsapp log: ' . $e->getMessage());
    }

    return ['ok' => $ok, 'mode' => $mode, 'error' => $error];
}

/** wa.me deep link for a message (used by the in-app "Notify on WhatsApp" buttons). */
function delivery_wa_link(string $phone, string $message = ''): string
{
    $url = 'https://wa.me/' . delivery_wa_number($phone);
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}

/**
 * Notify every agent in the roster (new order / status broadcast).
 *
 * @return array<int,array{phone:string,ok:bool,mode:string,error:string}>
 */
function delivery_broadcast_roster(string $message, string $template = 'delivery_broadcast'): array
{
    $out = [];
    foreach (DELIVERY_ROSTER as $boy) {
        $out[$boy['phone']] = delivery_send_whatsapp($boy['phone'], $message, $template);
    }
    return $out;
}

/** Notify ONE agent by roster code / name / phone. */
function delivery_notify_agent(string $who, string $message, string $template = 'delivery_update'): array
{
    $boy = delivery_roster_by_phone($who) ?? delivery_roster_by_name($who);
    if (!$boy) {
        return ['ok' => false, 'mode' => 'none', 'error' => 'unknown agent: ' . $who];
    }
    return delivery_send_whatsapp($boy['phone'], $message, $template);
}

/** Standard status-change message text. */
function delivery_status_message(array $order, string $status, string $agentName = ''): string
{
    $m    = delivery_status_meta($status);
    $when = date('d M Y, h:i A');
    return implode("\n", array_filter([
        '*KSR GURU JI — Delivery Update*',
        '',
        'Order: *' . ($order['order_number'] ?? ('#' . ($order['id'] ?? ''))) . '*',
        'Status: *' . $m['label'] . '*',
        'Customer: ' . ($order['name'] ?? '-'),
        'Items: ₹' . number_format((float) ($order['total'] ?? 0), 2),
        'Where: ' . trim(($order['address'] ?? '-') . ', ' . ($order['city'] ?? '')),
        $agentName !== '' ? 'Agent: ' . $agentName : '',
        'Time: ' . $when,
    ]));
}

/** New-order broadcast to all three agents. */
function delivery_new_order_message(array $order): string
{
    return implode("\n", [
        '*🚨 NEW ORDER — KSR GURU JI*',
        '',
        'Order: *' . ($order['order_number'] ?? ('#' . ($order['id'] ?? ''))) . '*',
        'Customer: ' . ($order['name'] ?? '-'),
        'Phone: ' . ($order['phone'] ?? '-'),
        'Amount: ₹' . number_format((float) ($order['total'] ?? 0), 2),
        'Pay: ' . strtoupper((string) ($order['payment_method'] ?? '-')),
        'Address: ' . trim(($order['address'] ?? '-') . ', ' . ($order['city'] ?? '') . ' ' . ($order['pincode'] ?? '')),
        '',
        'Open the Delivery portal to accept: /delivery/orders.php',
    ]);
}

// ------------------------------------------------------------
// 6. Layout
// ------------------------------------------------------------
function delivery_page_start(string $title, string $active = 'dashboard'): void
{
    $agentName = delivery_name();
    $unread    = delivery_unread_count();
    $roster    = DELIVERY_ROSTER;
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0f7b3a">
        <title><?= e($title) ?> · Delivery · KSR GURU JI</title>
        <link rel="icon" href="../company_logo.jpg">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="delivery-pages.css?v=1">
    </head>

    <body>
        <div class="dp-shell">
            <header class="dp-topbar">
                <div class="dp-topbar-l">
                    <img src="../company_logo.jpg" alt="KSR GURU JI" class="dp-logo" onerror="this.style.visibility='hidden'">
                    <div>
                        <div class="dp-topbar-t">KSR GURU JI</div>
                        <div class="dp-topbar-s">Delivery · <?= e($title) ?></div>
                    </div>
                </div>
                <div class="dp-topbar-r">
                    <a href="notifications.php" class="dp-icon-btn" title="Notifications">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($unread > 0): ?><span class="dp-dot" id="dpUnreadDot"><?= (int) $unread ?></span><?php endif; ?>
                    </a>
                    <a href="profile.php" class="dp-agent">
                        <span class="dp-agent-n"><?= e($agentName) ?></span>
                        <span class="dp-agent-p"><?= e(delivery_my_phone()) ?></span>
                    </a>
                </div>
            </header>

            <main class="dp-main">
            <?php
        }

        function delivery_page_end(): void
        {
            ?>
            </main>

            <nav class="dp-nav">
                <a href="dashboard.php" class="dp-nav-i <?= $GLOBALS['dp_active'] === 'dashboard' ? 'on' : '' ?>">
                    <i class="fa-solid fa-house"></i><span>Home</span>
                </a>
                <a href="orders.php" class="dp-nav-i <?= $GLOBALS['dp_active'] === 'orders' ? 'on' : '' ?>">
                    <i class="fa-solid fa-receipt"></i><span>Orders</span>
                </a>
                <a href="earnings.php" class="dp-nav-i <?= $GLOBALS['dp_active'] === 'earnings' ? 'on' : '' ?>">
                    <i class="fa-solid fa-wallet"></i><span>Earnings</span>
                </a>
                <a href="notifications.php" class="dp-nav-i <?= $GLOBALS['dp_active'] === 'notifications' ? 'on' : '' ?>">
                    <i class="fa-solid fa-bell"></i><span>Alerts</span>
                </a>
                <a href="profile.php" class="dp-nav-i <?= $GLOBALS['dp_active'] === 'profile' ? 'on' : '' ?>">
                    <i class="fa-solid fa-user"></i><span>Profile</span>
                </a>
            </nav>
        </div>
        <div class="dp-toast" id="dpToast"></div>
        <script src="js/delivery-common.js?v=1"></script>
    </body>

    </html>
<?php
        }

        /** Wrapper that sets the active nav key and renders the layout. */
        function delivery_layout(string $title, string $active, callable $body): void
        {
            $GLOBALS['dp_active'] = $active;
            delivery_page_start($title, $active);
            $body();
            delivery_page_end();
        }

        // ------------------------------------------------------------
        // 7. Counts
        // ------------------------------------------------------------
        function delivery_unread_count(): int
        {
            try {
                $stmt = delivery_db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
                $stmt->execute([delivery_id()]);
                return (int) $stmt->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        }

        function delivery_open_orders_count(int $agentId): int
        {
            try {
                $stmt = delivery_db()->prepare(
                    "SELECT COUNT(*) FROM orders
             WHERE (driver_id = ? OR delivery_boy_id = ?)
               AND status NOT IN ('delivered','cancelled')"
                );
                $stmt->execute([$agentId, $agentId]);
                return (int) $stmt->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        }

        function delivery_unassigned_count(): int
        {
            try {
                $stmt = delivery_db()->prepare(
                    "SELECT COUNT(*) FROM orders
             WHERE driver_id IS NULL AND delivery_boy_id IS NULL AND status IN ('pending','confirmed')"
                );
                $stmt->execute();
                return (int) $stmt->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        }
