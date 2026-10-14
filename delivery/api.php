<?php
// ============================================================
// delivery/api.php
// Single JSON endpoint used by every delivery page for real-time
// data, actions and WhatsApp / notification dispatch.
// ============================================================

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function api_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_fail(string $error, int $code = 400): void
{
    api_out(['success' => false, 'error' => $error], $code);
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

// Public actions (login + customer-facing tracking)
if (in_array($action, ['login', 'roster', 'track'], true)) {
    if ($action === 'roster') {
        api_out(['success' => true, 'roster' => DELIVERY_ROSTER]);
    }
    if ($action === 'login') {
        handle_login();
    }
    // 'track' falls through to the switch below without a session
} else {
    // Everything else needs a session
    if (!delivery_is_logged_in()) {
        api_fail('Unauthorized — please sign in again.', 401);
    }
}

try {
    switch ($action) {

        // ---------------------------------------------------------
        // Dashboard
        // ---------------------------------------------------------
        case 'dashboard':
            $id = delivery_id();
            $pdo = delivery_db();
            $today = date('Y-m-d');

            $deliveredToday = (int) scalar(
                $pdo,
                "SELECT COUNT(*) FROM orders WHERE (driver_id = ? OR delivery_boy_id = ?) AND status = 'delivered' AND DATE(delivered_at) = ?",
                [$id, $id, $today]
            );

            $activeOrders = (int) scalar(
                $pdo,
                "SELECT COUNT(*) FROM orders WHERE (driver_id = ? OR delivery_boy_id = ?) AND status NOT IN ('delivered','cancelled')",
                [$id, $id]
            );

            $unassigned = delivery_unassigned_count();

            $earnedToday = (float) scalar(
                $pdo,
                "SELECT COALESCE(SUM(total),0) FROM orders WHERE (driver_id = ? OR delivery_boy_id = ?) AND status = 'delivered' AND DATE(delivered_at) = ?",
                [$id, $id, $today]
            );

            $earnedWeek = (float) scalar(
                $pdo,
                "SELECT COALESCE(SUM(total),0) FROM orders WHERE (driver_id = ? OR delivery_boy_id = ?) AND status = 'delivered' AND delivered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$id, $id]
            );

            // Last 7 days delivered count (sparkline)
            $trend = [];
            $stmt = $pdo->prepare(
                "SELECT DATE(delivered_at) d, COUNT(*) c, COALESCE(SUM(total),0) amt
                 FROM orders
                 WHERE (driver_id = ? OR delivery_boy_id = ?)
                   AND status = 'delivered'
                   AND delivered_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                 GROUP BY DATE(delivered_at)"
            );
            $stmt->execute([$id, $id]);
            $byDay = [];
            foreach ($stmt->fetchAll() as $r) {
                $byDay[$r['d']] = ['c' => (int) $r['c'], 'amt' => (float) $r['amt']];
            }
            for ($i = 6; $i >= 0; $i--) {
                $d  = date('Y-m-d', strtotime("-$i day"));
                $lbl = strtoupper(substr(date('D', strtotime($d)), 0, 2));
                $trend[] = [
                    'label'  => $lbl,
                    'date'   => $d,
                    'count'  => $byDay[$d]['c'] ?? 0,
                    'amount' => $byDay[$d]['amt'] ?? 0.0,
                ];
            }

            $recent = fetch_all(
                "SELECT id, order_number, name, city, total, status, created_at
                 FROM orders
                 WHERE driver_id = ? OR delivery_boy_id = ?
                 ORDER BY created_at DESC LIMIT 6",
                [$id, $id]
            );

            api_out([
                'success' => true,
                'agent'   => ['name' => delivery_name(), 'phone' => delivery_my_phone()],
                'stats'   => [
                    'delivered_today' => $deliveredToday,
                    'active_orders'   => $activeOrders,
                    'unassigned'      => $unassigned,
                    'earned_today'    => $earnedToday,
                    'earned_week'     => $earnedWeek,
                    'unread'          => delivery_unread_count(),
                ],
                'trend'   => $trend,
                'recent'  => $recent,
                'server_time' => date('c'),
            ]);
            break;

        // ---------------------------------------------------------
        // Order list
        // ---------------------------------------------------------
        case 'orders':
            $id   = delivery_id();
            $pdo  = delivery_db();
            $mine = ($_GET['scope'] ?? 'mine') === 'mine';

            $where = $mine
                ? "(o.driver_id = ? OR o.delivery_boy_id = ?)"
                : "(o.driver_id IS NULL AND o.delivery_boy_id IS NULL)";
            $params = $mine ? [$id, $id] : [];

            $status = (string) ($_GET['status'] ?? 'all');
            if ($status !== 'all') {
                $allowed = status_filter_map($status);
                if ($allowed) {
                    $where .= ' AND o.status IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')';
                    $params = array_merge($params, $allowed);
                }
            }

            $q = trim((string) ($_GET['q'] ?? ''));
            if ($q !== '') {
                $where .= ' AND (o.order_number LIKE ? OR o.name LIKE ? OR o.phone LIKE ?)';
                $like = '%' . $q . '%';
                $params = array_merge($params, [$like, $like, $like]);
            }

            $rows = fetch_all(
                "SELECT o.id, o.order_number, o.name, o.phone, o.address, o.city, o.state, o.pincode,
                        o.total, o.payment_method, o.payment_status, o.status, o.created_at, o.placed_at,
                        o.driver_id, o.delivery_boy_id
                 FROM orders o
                 WHERE $where
                 ORDER BY FIELD(o.status,'pending','confirmed','accepted','picked_up','in_transit','delivered','cancelled'),
                          o.created_at DESC
                 LIMIT 200",
                $params
            );

            $counts = ['all' => count($rows)];
            foreach (delivery_status_buckets() as $k => $label) {
                $counts[$k] = 0;
            }
            foreach ($rows as $r) {
                $counts['all']++;
                foreach (delivery_status_buckets() as $k => $lbl) {
                    if (bucket_matches($k, (string) $r['status'])) {
                        $counts[$k]++;
                    }
                }
            }

            api_out(['success' => true, 'orders' => $rows, 'counts' => $counts, 'server_time' => date('c')]);
            break;

        // ---------------------------------------------------------
        // Single order + items + timeline
        // ---------------------------------------------------------
        case 'order':
            $orderId = (int) ($_GET['id'] ?? 0);
            $data    = load_order($orderId);
            if (!$data) {
                api_fail('Order not found', 404);
            }
            api_out(['success' => true, 'order' => $data, 'server_time' => date('c')]);
            break;

        // ---------------------------------------------------------
        // Accept an unassigned order
        // ---------------------------------------------------------
        case 'accept':
            $orderId = (int) ($_POST['id'] ?? 0);
            $pdo = delivery_db();
            $id  = delivery_id();

            $row = fetch_row("SELECT id, status, driver_id, delivery_boy_id, order_number FROM orders WHERE id = ?", [$orderId]);
            if (!$row) {
                api_fail('Order not found', 404);
            }
            if ($row['driver_id'] || $row['delivery_boy_id']) {
                api_fail('This order was already taken by another agent.', 409);
            }

            set_order_agent($orderId, $id);
            set_order_status($orderId, 'accepted', $id, 'Accepted by ' . delivery_name());

            $order = load_order($orderId);

            // Notify the customer + the other two agents
            $customerUserId = (int) scalar($pdo, 'SELECT user_id FROM orders WHERE id = ?', [$orderId]);
            if ($customerUserId > 0) {
                delivery_notify_user(
                    $customerUserId,
                    'Delivery agent assigned',
                    ($order['order_number'] ?? '') . ' — ' . delivery_name() . ' is on the way.',
                    'delivery'
                );
            }
            broadcast_others($orderId, delivery_name());

            api_out(['success' => true, 'order' => $order, 'message' => 'Order accepted.']);
            break;

        // ---------------------------------------------------------
        // Status update
        // ---------------------------------------------------------
        case 'status':
            $orderId = (int) ($_POST['id'] ?? 0);
            $status  = (string) ($_POST['status'] ?? '');
            if (!isset(DELIVERY_FLOW[$status])) {
                api_fail('Unknown status: ' . $status);
            }

            $row = fetch_row('SELECT id, driver_id, delivery_boy_id, status FROM orders WHERE id = ?', [$orderId]);
            if (!$row) {
                api_fail('Order not found', 404);
            }
            $id = delivery_id();
            $assigned = (int) ($row['driver_id'] ?: 0) === $id || (int) ($row['delivery_boy_id'] ?: 0) === $id;
            if (!$assigned && !$row['driver_id'] && !$row['delivery_boy_id']) {
                set_order_agent($orderId, $id);
            } elseif (!$assigned) {
                api_fail('This order belongs to another agent.', 403);
            }

            $alreadyDone = in_array((string) $row['status'], ['delivered', 'cancelled'], true);
            if ($alreadyDone && (string) $row['status'] !== $status) {
                api_fail('Order is already ' . delivery_status_meta($row['status'])['label'] . '.', 409);
            }

            set_order_status($orderId, $status, $id, 'Status → ' . delivery_status_meta($status)['label']);

            if ($status === 'delivered') {
                mark_delivered($orderId, $id);
            }

            $order = load_order($orderId);

            // In-app notification for the customer
            $customerUserId = (int) scalar(delivery_db(), 'SELECT user_id FROM orders WHERE id = ?', [$orderId]);
            if ($customerUserId > 0) {
                delivery_notify_user(
                    $customerUserId,
                    'Order ' . delivery_status_meta($status)['label'],
                    ($order['order_number'] ?? '') . ' — ' . delivery_status_meta($status)['label'],
                    'delivery'
                );
            }

            // WhatsApp: status update to the customer + roster broadcast
            $waCustomer = delivery_send_whatsapp(
                (string) ($order['phone'] ?? ''),
                delivery_status_message($order, $status, delivery_name()),
                'order_status_' . $status
            );
            $waRoster = delivery_broadcast_roster(
                '📦 ' . ($order['order_number'] ?? '') . ' → ' . delivery_status_meta($status)['label']
                    . ' by ' . delivery_name(),
                'order_status_broadcast'
            );

            api_out([
                'success' => true,
                'order'   => $order,
                'whatsapp' => ['customer' => $waCustomer, 'roster' => $waRoster],
                'message' => 'Marked as ' . delivery_status_meta($status)['label'] . '.',
            ]);
            break;

        // ---------------------------------------------------------
        // Earnings
        // ---------------------------------------------------------
        case 'earnings':
            $id  = delivery_id();
            $pdo = delivery_db();
            $range = (string) ($_GET['range'] ?? 'week');

            [$from, $to, $label] = earnings_range($range);

            $daily = fetch_all(
                "SELECT DATE(delivered_at) d, COUNT(*) c, COALESCE(SUM(total),0) amt
                 FROM orders
                 WHERE (driver_id = ? OR delivery_boy_id = ?)
                   AND status = 'delivered'
                   AND DATE(delivered_at) BETWEEN ? AND ?
                 GROUP BY DATE(delivered_at)
                 ORDER BY d ASC",
                [$id, $id, $from, $to]
            );

            $totals = fetch_row(
                "SELECT COUNT(*) c, COALESCE(SUM(total),0) amt
                 FROM orders
                 WHERE (driver_id = ? OR delivery_boy_id = ?) AND status = 'delivered'
                   AND DATE(delivered_at) BETWEEN ? AND ?",
                [$id, $id, $from, $to]
            ) ?: ['c' => 0, 'amt' => 0];

            $byMethod = fetch_all(
                "SELECT payment_method m, COUNT(*) c, COALESCE(SUM(total),0) amt
                 FROM orders
                 WHERE (driver_id = ? OR delivery_boy_id = ?) AND status = 'delivered'
                   AND DATE(delivered_at) BETWEEN ? AND ?
                 GROUP BY payment_method",
                [$id, $id, $from, $to]
            );

            $deliveredList = fetch_all(
                "SELECT id, order_number, name, city, total, payment_method, delivered_at
                 FROM orders
                 WHERE (driver_id = ? OR delivery_boy_id = ?) AND status = 'delivered'
                   AND DATE(delivered_at) BETWEEN ? AND ?
                 ORDER BY delivered_at DESC LIMIT 100",
                [$id, $id, $from, $to]
            );

            $pendingPayout = (float) scalar(
                $pdo,
                "SELECT COALESCE(SUM(total),0) FROM orders
                 WHERE (driver_id = ? OR delivery_boy_id = ?)
                   AND status IN ('accepted','picked_up','in_transit')",
                [$id, $id]
            );

            $cashToCollect = (float) scalar(
                $pdo,
                "SELECT COALESCE(SUM(total),0) FROM orders
                 WHERE (driver_id = ? OR delivery_boy_id = ?)
                   AND payment_method = 'cod'
                   AND status IN ('accepted','picked_up','in_transit')",
                [$id, $id]
            );

            api_out([
                'success' => true,
                'range'   => ['key' => $range, 'label' => $label, 'from' => $from, 'to' => $to],
                'summary' => [
                    'deliveries'      => (int) $totals['c'],
                    'amount'          => (float) $totals['amt'],
                    'avg_per_order'   => (int) $totals['c'] > 0 ? (float) $totals['amt'] / (int) $totals['c'] : 0.0,
                    'pending_payout'  => $pendingPayout,
                    'cash_to_collect' => $cashToCollect,
                ],
                'daily'      => $daily,
                'by_method'  => $byMethod,
                'deliveries' => $deliveredList,
                'server_time' => date('c'),
            ]);
            break;

        // ---------------------------------------------------------
        // Notifications
        // ---------------------------------------------------------
        case 'notifications':
            $id  = delivery_id();
            $rows = fetch_all(
                'SELECT id, title, message, type, is_read, created_at
                 FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100',
                [$id]
            );
            $wa = fetch_all(
                "SELECT id, recipient, template, message, status, created_at
                 FROM whatsapp_logs ORDER BY id DESC LIMIT 40"
            );
            api_out([
                'success'      => true,
                'notifications' => $rows,
                'whatsapp_log' => $wa,
                'unread'       => delivery_unread_count(),
                'server_time'  => date('c'),
            ]);
            break;

        case 'read_all':
            $stmt = delivery_db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            $stmt->execute([delivery_id()]);
            api_out(['success' => true, 'unread' => 0]);
            break;

        case 'read':
            $nid = (int) ($_POST['id'] ?? 0);
            $stmt = delivery_db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$nid, delivery_id()]);
            api_out(['success' => true]);
            break;

        // ---------------------------------------------------------
        // WhatsApp: notify one agent, or all three
        // ---------------------------------------------------------
        case 'notify_agent':
            $who   = (string) ($_POST['agent'] ?? '');
            $order = trim((string) ($_POST['message'] ?? ''));
            if ($who === '') {
                api_fail('Pick an agent first.');
            }
            $body = $order !== '' ? $order : 'Test notification from KSR GURU JI delivery portal.';
            $res  = delivery_notify_agent($who, $body, 'manual_agent_notify');
            api_out([
                'success' => true,
                'sent'    => $res,
                'link'    => delivery_wa_link(
                    (delivery_roster_by_phone($who) ?? delivery_roster_by_name($who))['phone'] ?? '',
                    $body
                ),
                'message' => $res['ok']
                    ? 'Message dispatched (' . $res['mode'] . ').'
                    : 'Recorded in the WhatsApp log. Open WhatsApp to send it manually.',
            ]);
            break;

        case 'notify_all':
            $msg = trim((string) ($_POST['message'] ?? ''));
            if ($msg === '') {
                api_fail('Message cannot be empty.');
            }
            $results = delivery_broadcast_roster($msg, 'manual_broadcast');
            api_out(['success' => true, 'results' => $results, 'message' => 'Broadcast sent to all three agents.']);
            break;

        // ---------------------------------------------------------
        // Profile update
        // ---------------------------------------------------------
        case 'update_profile':
            $id    = delivery_id();
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $name  = trim((string) ($_POST['name'] ?? ''));

            $phoneDigits = preg_replace('/\D+/', '', $phone);
            $allowed = array_column(DELIVERY_ROSTER, 'phone');
            $isRosterPhone = $phoneDigits !== '' && in_array(substr($phoneDigits, -10), array_map(fn($p) => substr($p, -10), $allowed), true);
            if ($phone !== '' && !$isRosterPhone) {
                api_fail('Phone must be one of the registered delivery agents: JK (8088584421), jampaleppa (8762474406), shankar (9590902742).');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                api_fail('Invalid email address.');
            }

            try {
                $stmt = delivery_db()->prepare('UPDATE users SET name = ?, phone = ?, email = ? WHERE id = ? AND role = \'delivery\'');
                $stmt->execute([
                    $name !== '' ? $name : delivery_name(),
                    $phone !== '' ? $phone : delivery_my_phone(),
                    $email !== '' ? $email : (string) ($_SESSION['delivery_boy_email'] ?? ''),
                    $id,
                ]);
            } catch (Throwable $e) {
                api_fail('Could not save: ' . $e->getMessage());
            }

            $_SESSION['delivery_boy_name']  = $name !== '' ? $name : delivery_name();
            $_SESSION['delivery_boy_phone'] = $phone !== '' ? $phone : delivery_my_phone();
            $_SESSION['delivery_boy_email'] = $email !== '' ? $email : (string) ($_SESSION['delivery_boy_email'] ?? '');
            $_SESSION['user_name'] = $_SESSION['delivery_boy_name'];
            $_SESSION['user_phone'] = $_SESSION['delivery_boy_phone'];

            api_out(['success' => true, 'message' => 'Profile updated.']);
            break;

        case 'profile':
            $id  = delivery_id();
            $row = fetch_row("SELECT id, name, fullname, email, phone, role FROM users WHERE id = ?", [$id]) ?: [];
            $stats = fetch_row(
                "SELECT COUNT(*) c, COALESCE(SUM(total),0) amt FROM orders
                 WHERE (driver_id = ? OR delivery_boy_id = ?) AND status = 'delivered'",
                [$id, $id]
            ) ?: ['c' => 0, 'amt' => 0];
            $recentWa = fetch_all(
                "SELECT recipient, template, message, status, created_at
                 FROM whatsapp_logs ORDER BY id DESC LIMIT 15"
            );
            api_out([
                'success'  => true,
                'profile'  => [
                    'name'   => $row['name'] ?? delivery_name(),
                    'email'  => $row['email'] ?? '',
                    'phone'  => $row['phone'] ?? delivery_my_phone(),
                    'role'   => 'Delivery Agent',
                ],
                'stats'    => ['deliveries' => (int) $stats['c'], 'amount' => (float) $stats['amt']],
                'roster'   => DELIVERY_ROSTER,
                'wa_log'   => $recentWa,
                'server_time' => date('c'),
            ]);
            break;

        // ---------------------------------------------------------
        // Tracking (customer-facing live status)
        // ---------------------------------------------------------
        case 'track':
            $ref = (string) ($_GET['ref'] ?? $_GET['order'] ?? '');
            if ($ref === '') {
                api_fail('Provide an order number.');
            }
            $stmt = delivery_db()->prepare(
                'SELECT * FROM orders WHERE id = ? OR order_number = ? LIMIT 1'
            );
            $stmt->execute([ctype_digit($ref) ? (int) $ref : 0, $ref]);
            $order = $stmt->fetch();
            if (!$order) {
                api_fail('No order found for "' . $ref . '".', 404);
            }
            $agentName = '—';
            $agentPhone = '';
            if (!empty($order['driver_id'])) {
                $a = fetch_row('SELECT name, phone FROM users WHERE id = ?', [(int) $order['driver_id']]);
                if ($a) {
                    $agentName  = (string) $a['name'];
                    $agentPhone = (string) $a['phone'];
                }
            }
            $timeline = fetch_all(
                'SELECT status, notes, created_at FROM order_timeline WHERE order_id = ? ORDER BY created_at ASC',
                [(int) $order['id']]
            );
            api_out([
                'success' => true,
                'order'   => [
                    'id'            => (int) $order['id'],
                    'order_number'  => $order['order_number'],
                    'status'        => $order['status'],
                    'name'          => $order['name'],
                    'address'       => trim(($order['address'] ?? '') . ', ' . ($order['city'] ?? '')),
                    'total'         => (float) $order['total'],
                    'payment_method' => $order['payment_method'],
                    'placed_at'     => $order['placed_at'] ?? $order['created_at'],
                    'delivered_at'  => $order['delivered_at'],
                    'agent_name'    => $agentName,
                    'agent_phone'   => $agentPhone,
                ],
                'timeline' => $timeline,
                'server_time' => date('c'),
            ]);
            break;

        // ---------------------------------------------------------
        // Idle ping (keeps the session alive + returns unread count)
        // ---------------------------------------------------------
        case 'ping':
            api_out([
                'success' => true,
                'unread'  => delivery_unread_count(),
                'open'    => delivery_open_orders_count(delivery_id()),
                'server_time' => date('c'),
            ]);
            break;

        default:
            api_fail('Unknown action: ' . $action);
    }
} catch (Throwable $e) {
    error_log('delivery/api error: ' . $e->getMessage());
    api_fail('Server error: ' . $e->getMessage(), 500);
}

// ============================================================
// Helpers
// ============================================================

function scalar(PDO $pdo, string $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $v = $stmt->fetchColumn();
    return $v === false ? 0 : $v;
}

function fetch_all(string $sql, array $params = []): array
{
    $stmt = delivery_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_row(string $sql, array $params = []): ?array
{
    $stmt = delivery_db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function status_filter_map(string $filter): array
{
    $map = [
        'pending'   => ['pending', 'confirmed'],
        'accepted'  => ['accepted'],
        'picked'    => ['picked_up'],
        'out'       => ['in_transit'],
        'delivered' => ['delivered'],
        'cancelled' => ['cancelled'],
    ];
    return $map[$filter] ?? [];
}

function delivery_status_buckets(): array
{
    return [
        'pending'   => 'Pending',
        'accepted'  => 'Accepted',
        'picked'    => 'Picked Up',
        'out'       => 'Out',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];
}

function bucket_matches(string $bucket, string $status): bool
{
    $m = status_filter_map($bucket);
    return in_array($status, $m, true);
}

function earnings_range(string $range): array
{
    switch ($range) {
        case 'today':
            return [date('Y-m-d'), date('Y-m-d'), 'Today'];
        case 'month':
            return [date('Y-m-01'), date('Y-m-d'), 'This Month'];
        case 'all':
            return ['1970-01-01', date('Y-m-d'), 'All Time'];
        case 'week':
        default:
            return [date('Y-m-d', strtotime('-6 day')), date('Y-m-d'), 'Last 7 Days'];
    }
}

function set_order_agent(int $orderId, int $agentId): void
{
    $stmt = delivery_db()->prepare(
        'UPDATE orders SET driver_id = ?, delivery_boy_id = ? WHERE id = ?'
    );
    $stmt->execute([$agentId, $agentId, $orderId]);
}

function set_order_status(int $orderId, string $status, ?int $by, string $note = ''): void
{
    $pdo = delivery_db();
    $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $orderId]);
    try {
        $pdo->prepare('INSERT INTO order_timeline (order_id, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$orderId, $status, $note, $by]);
    } catch (Throwable $e) {
        error_log('timeline insert: ' . $e->getMessage());
    }
}

function mark_delivered(int $orderId, int $agentId): void
{
    $pdo = delivery_db();
    $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
        ->execute([$orderId]);

    // COD collected
    try {
        $pdo->prepare(
            "UPDATE payments SET status = 'cod_collected', collected_at = NOW(), collected_by = ?, collected_amount = amount
             WHERE order_id = ? AND method = 'cod' AND status <> 'cod_collected'"
        )->execute([delivery_name(), $orderId]);
    } catch (Throwable $e) {
        // payments may not have a row — fine
    }

    try {
        $pdo->prepare(
            'INSERT INTO order_delivery (order_id, delivery_user_id, delivery_time) VALUES (?, ?, NOW())'
        )->execute([$orderId, $agentId]);
    } catch (Throwable $e) {
        // duplicate delivery row — ignore
    }
}

function load_order(int $orderId): ?array
{
    $row = fetch_row(
        'SELECT * FROM orders WHERE id = ?',
        [$orderId]
    );
    if (!$row) {
        return null;
    }
    $row['items'] = fetch_all(
        'SELECT id, product_name, quantity, price, total FROM order_items WHERE order_id = ?',
        [$orderId]
    );
    $row['timeline'] = fetch_all(
        'SELECT status, notes, created_at FROM order_timeline WHERE order_id = ? ORDER BY created_at ASC',
        [$orderId]
    );
    $row['payment'] = fetch_row(
        'SELECT method, status, amount, transaction_id FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1',
        [$orderId]
    );
    if (!empty($row['driver_id'])) {
        $a = fetch_row('SELECT name, phone FROM users WHERE id = ?', [(int) $row['driver_id']]);
        $row['agent_name']  = $a['name'] ?? '—';
        $row['agent_phone'] = $a['phone'] ?? '';
    } else {
        $row['agent_name']  = 'Unassigned';
        $row['agent_phone'] = '';
    }
    return $row;
}

/** Tell the other two agents about this order. */
function broadcast_others(int $orderId, string $exceptName): void
{
    $order = load_order($orderId);
    if (!$order) {
        return;
    }
    $msg = '📦 ' . ($order['order_number'] ?? '') . ' taken by ' . $exceptName
        . ' — status ' . delivery_status_meta((string) $order['status'])['label'];
    foreach (DELIVERY_ROSTER as $boy) {
        if (strcasecmp($boy['name'], $exceptName) === 0) {
            continue;
        }
        delivery_send_whatsapp($boy['phone'], $msg, 'order_claimed');
    }
}

// ============================================================
// Login
// ============================================================
function handle_login(): void
{
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password   = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        api_fail('Enter your phone/email and password.');
    }

    $pdo  = delivery_db();
    $stmt = $pdo->prepare(
        "SELECT id, name, fullname, email, phone, password
         FROM users
         WHERE role = 'delivery' AND (phone = ? OR email = ?)
         LIMIT 1"
    );
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password'])) {
        api_fail('Invalid credentials.');
    }

    $name = (string) ($user['name'] ?: $user['fullname'] ?: 'Delivery Agent');
    delivery_login((int) $user['id'], $name, (string) $user['phone'], (string) $user['email']);

    api_out([
        'success' => true,
        'agent'   => ['name' => $name, 'phone' => (string) $user['phone']],
        'message' => 'Welcome, ' . $name . '!',
    ]);
}
