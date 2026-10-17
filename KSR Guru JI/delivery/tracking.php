<?php
// ============================================================
// delivery/tracking.php — customer-facing live order tracking
// Reads the REAL order from the database. No mock data, no
// placeholder agent names — only the registered agents.
// ============================================================
require_once __DIR__ . '/bootstrap.php';

$ref   = trim((string) ($_GET['ref'] ?? $_GET['order'] ?? ''));
$order = null;
$items = [];
$timeline = [];

if ($ref !== '') {
    $stmt = delivery_db()->prepare('SELECT * FROM orders WHERE id = ? OR order_number = ? LIMIT 1');
    $stmt->execute([ctype_digit($ref) ? (int) $ref : 0, $ref]);
    $order = $stmt->fetch() ?: null;

    if ($order) {
        $s = delivery_db()->prepare('SELECT product_name, quantity, price, total FROM order_items WHERE order_id = ?');
        $s->execute([(int) $order['id']]);
        $items = $s->fetchAll();

        $s = delivery_db()->prepare('SELECT status, notes, created_at FROM order_timeline WHERE order_id = ? ORDER BY created_at ASC');
        $s->execute([(int) $order['id']]);
        $timeline = $s->fetchAll();
    }
}

$agentName  = 'Not assigned yet';
$agentPhone = '';
if ($order && !empty($order['driver_id'])) {
    $s = delivery_db()->prepare('SELECT name, phone FROM users WHERE id = ?');
    $s->execute([(int) $order['driver_id']]);
    $a = $s->fetch();
    if ($a) {
        $agentName  = (string) ($a['name'] ?: 'Delivery Agent');
        $agentPhone = (string) $a['phone'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f7b3a">
    <title><?= $order ? e($order['order_number']) . ' · Track' : 'Track your order' ?> · KSR GURU JI</title>
    <link rel="icon" href="../company_logo.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="delivery-pages.css?v=1">
    <style>
        body {
            padding: 0 0 40px;
        }

        .tk-wrap {
            max-width: 640px;
            margin: 0 auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .tk-head {
            text-align: center;
            padding: 18px 0 4px;
        }

        .tk-head img {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            object-fit: cover;
        }

        .tk-head h1 {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .tk-head p {
            color: var(--t3);
            font-size: .82rem;
            font-weight: 600;
        }

        .tk-form {
            display: flex;
            gap: 8px;
        }

        .tk-form input {
            flex: 1;
        }
    </style>
</head>

<body>
    <div class="tk-wrap">

        <div class="tk-head">
            <img src="../company_logo.jpg" alt="KSR GURU JI" onerror="this.style.display='none'">
            <h1>Track your order</h1>
            <p>Live status, updated in real time</p>
        </div>

        <section class="dp-card">
            <form class="tk-form" method="get" action="tracking.php">
                <input type="text" name="ref" id="refIn" value="<?= e($ref) ?>" placeholder="Order number or ID" required>
                <button class="dp-btn dp-btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Track</button>
            </form>
        </section>

        <?php if ($ref !== '' && !$order): ?>
            <section class="dp-card">
                <div class="dp-empty" style="padding:34px 16px">
                    <i class="fa-solid fa-circle-question"></i>
                    We couldn't find an order for "<b><?= e($ref) ?></b>".<br>Please check the number and try again.
                </div>
            </section>
        <?php endif; ?>

        <?php if ($order):
            $steps = ['pending', 'accepted', 'picked_up', 'in_transit', 'delivered'];
            $cur = array_search($order['status'], $steps, true);
            $curIdx = $cur === false ? 0 : $cur;
            $m = delivery_status_meta($order['status']);
        ?>
            <section class="dp-card">
                <div class="dp-card-h">
                    <h3><i class="fa-solid fa-receipt"></i> <?= e($order['order_number']) ?></h3>
                    <?= delivery_status_badge($order['status']) ?>
                </div>
                <div class="dp-sec" style="padding-top:12px">
                    <div class="dp-kv"><span>Status</span><b><?= e($m['label']) ?></b></div>
                    <div class="dp-kv"><span>Customer</span><b><?= e($order['name']) ?></b></div>
                    <div class="dp-kv"><span>Delivering to</span>
                        <b><?= e(trim(($order['address'] ?? '') . ', ' . ($order['city'] ?? '') . ' ' . ($order['pincode'] ?? ''))) ?></b>
                    </div>
                    <div class="dp-kv"><span>Order value</span><b><?= e(delivery_money($order['total'])) ?></b></div>
                    <div class="dp-kv"><span>Payment</span><b><?= e(strtoupper((string) $order['payment_method'])) ?></b></div>
                    <div class="dp-kv"><span>Placed on</span>
                        <b><?= e(date('d M Y, h:i A', strtotime($order['placed_at'] ?: $order['created_at']))) ?></b>
                    </div>
                    <?php if ($order['delivered_at']): ?>
                        <div class="dp-kv"><span>Delivered on</span>
                            <b><?= e(date('d M Y, h:i A', strtotime($order['delivered_at']))) ?></b>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="dp-card">
                <div class="dp-card-h">
                    <h3><i class="fa-solid fa-route"></i> Progress</h3>
                </div>
                <div class="dp-tl">
                    <?php foreach ($steps as $i => $s):
                        $sm = delivery_status_meta($s);
                        $reached = $i <= $curIdx;
                    ?>
                        <div class="dp-tl-i <?= $reached ? 'on' : '' ?>">
                            <div class="dp-tl-dot"><i class="fa-solid <?= e($sm['icon']) ?>"></i></div>
                            <div class="dp-tl-b">
                                <b><?= e($sm['label']) ?></b>
                                <small><?= $reached ? 'Completed' : 'Pending' ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($agentPhone !== ''): ?>
                <section class="dp-card">
                    <div class="dp-card-h">
                        <h3><i class="fa-solid fa-person-biking"></i> Your delivery agent</h3>
                    </div>
                    <div class="dp-sec" style="padding-top:12px; display:flex; align-items:center; gap:13px">
                        <span class="dp-av" style="width:48px;height:48px;font-size:1.1rem"><?= e(strtoupper(substr($agentName, 0, 1))) ?></span>
                        <div style="flex:1">
                            <b style="display:block;font-size:.92rem"><?= e($agentName) ?></b>
                            <small style="color:var(--t3);font-size:.76rem;font-weight:600"><?= e($agentPhone) ?></small>
                        </div>
                    </div>
                    <div class="dp-actions">
                        <a class="dp-btn dp-btn-ghost" href="tel:<?= e($agentPhone) ?>"><i class="fa-solid fa-phone"></i> Call</a>
                        <a class="dp-btn dp-btn-wa" target="_blank" rel="noopener"
                            href="<?= e(delivery_wa_link($agentPhone, 'Hi ' . $agentName . ', I am following up on my KSR GURU JI order ' . $order['order_number'] . '.')) ?>">
                            <i class="fa-brands fa-whatsapp"></i> WhatsApp
                        </a>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($items): ?>
                <section class="dp-card">
                    <div class="dp-card-h">
                        <h3><i class="fa-solid fa-boxes-stacked"></i> Items</h3>
                    </div>
                    <div class="dp-list">
                        <?php foreach ($items as $it): ?>
                            <div class="dp-row">
                                <div class="dp-row-l">
                                    <b><?= e($it['product_name']) ?></b>
                                    <small>Qty <?= (int) $it['quantity'] ?> × <?= e(delivery_money($it['price'])) ?></small>
                                </div>
                                <div class="dp-row-r"><b><?= e(delivery_money($it['total'] ?? $it['price'] * $it['quantity'])) ?></b></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($timeline): ?>
                <section class="dp-card">
                    <div class="dp-card-h">
                        <h3><i class="fa-solid fa-timeline"></i> History</h3>
                    </div>
                    <div class="dp-tl">
                        <?php foreach (array_reverse($timeline) as $t): $tm = delivery_status_meta($t['status']); ?>
                            <div class="dp-tl-i on">
                                <div class="dp-tl-dot"><i class="fa-solid <?= e($tm['icon']) ?>"></i></div>
                                <div class="dp-tl-b">
                                    <b><?= e($tm['label']) ?></b>
                                    <small><?= e(date('d M Y, h:i A', strtotime($t['created_at']))) ?><?= $t['notes'] ? ' · ' . e($t['notes']) : '' ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <script>
                // Auto-refresh while the order is still in progress
                (function poll() {
                    if (<?= json_encode(in_array($order['status'], ['delivered', 'cancelled'], true)) ?>) return;
                    setTimeout(function() {
                        fetch('api.php?action=track&ref=' + encodeURIComponent(<?= json_encode($order['order_number']) ?>), {
                                cache: 'no-store'
                            })
                            .then(function(r) {
                                return r.json();
                            })
                            .then(function(j) {
                                if (j.success && j.order && j.order.status !== <?= json_encode($order['status']) ?>) {
                                    location.reload();
                                } else {
                                    poll();
                                }
                            })
                            .catch(function() {
                                poll();
                            });
                    }, 20000);
                })();
            </script>
        <?php endif; ?>

        <p class="dp-muted" style="text-align:center">
            Need help? WhatsApp <a href="https://wa.me/918088584421" target="_blank" rel="noopener">8088584421</a>
        </p>
    </div>
</body>

</html>