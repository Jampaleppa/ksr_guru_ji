<?php
// ============================================================
// delivery/seed_agents.php  (one-off / idempotent)
// Guarantees EXACTLY three delivery accounts in the system:
//   JK          8088584421
//   jampaleppa  8762474406
//   shankar     9590902742
// Every other user with role 'delivery' / 'delivery_boy' is
// removed, along with their orders assignments (orders are kept).
// Run:  php delivery/seed_agents.php
// ============================================================

require_once __DIR__ . '/bootstrap.php';

$password = $argv[1] ?? 'delivery@123';

$roster = DELIVERY_ROSTER;
$pdo    = delivery_db();

echo "Seeding delivery agents (password: {$password})\n";

// 1. Remove any delivery account that is not on the roster
$phones = array_column($roster, 'phone');
$inList = implode(',', array_fill(0, count($phones), '?'));

$stmt = $pdo->prepare(
    "SELECT id, name, email, phone FROM users
     WHERE role IN ('delivery','delivery_boy')
       AND COALESCE(phone,'') NOT IN ($inList)
       AND COALESCE(REPLACE(REPLACE(phone,'+91',''),' ',''),'') NOT IN ($inList)"
);
$stmt->execute(array_merge($phones, $phones));
$stale = $stmt->fetchAll();

foreach ($stale as $u) {
    // Release their orders back into the open pool (keep the order itself).
    $pdo->prepare('UPDATE orders SET driver_id = NULL, delivery_boy_id = NULL WHERE driver_id = ? OR delivery_boy_id = ?')
        ->execute([(int) $u['id'], (int) $u['id']]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([(int) $u['id']]);
    $pdo->prepare('DELETE FROM delivery_boys WHERE user_id = ?')->execute([(int) $u['id']]);
    echo "  removed: #{$u['id']} {$u['name']} ({$u['phone']})\n";
}

// 2. Upsert the three roster agents
$hash = password_hash($password, PASSWORD_DEFAULT);

foreach ($roster as $b) {
    $phoneTail = substr($b['phone'], -10);

    // Normalise the phone on any existing account (ignore +91 / spacing).
    $find = $pdo->prepare(
        "SELECT id FROM users
         WHERE role IN ('delivery','delivery_boy')
           AND REPLACE(REPLACE(REPLACE(COALESCE(phone,''),'+91',''),'-',''),' ','') LIKE ?
         LIMIT 1"
    );
    $find->execute(['%' . $phoneTail]);
    $existing = $find->fetchColumn();

    if ($existing) {
        $pdo->prepare("UPDATE users SET name = ?, fullname = ?, phone = ?, role = 'delivery', password = ? WHERE id = ?")
            ->execute([$b['name'], $b['name'], $b['phone'], $hash, (int) $existing]);
        $id = (int) $existing;
        $action = 'updated';
    } else {
        // Reuse a slot if the exact phone exists on a non-delivery account.
        $byPhone = $pdo->prepare('SELECT id FROM users WHERE REPLACE(REPLACE(REPLACE(COALESCE(phone,\'\'),\'+91\',\'\'),\'-\',\'\'),\' \',\'\') LIKE ? LIMIT 1');
        $byPhone->execute(['%' . $phoneTail]);
        $byPhoneId = $byPhone->fetchColumn();

        if ($byPhoneId) {
            $pdo->prepare("UPDATE users SET role = 'delivery', name = ?, fullname = ?, phone = ?, password = ? WHERE id = ?")
                ->execute([$b['name'], $b['name'], $b['phone'], $hash, (int) $byPhoneId]);
            $id = (int) $byPhoneId;
            $action = 'converted';
        } else {
            $pdo->prepare("INSERT INTO users (name, fullname, email, phone, password, role, created_at)
                          VALUES (?, ?, ?, ?, ?, 'delivery', NOW())")
                ->execute([
                    $b['name'],
                    $b['name'],
                    strtolower($b['code']) . '@ksrguruji.com',
                    $b['phone'],
                    $hash,
                ]);
            $id = (int) $pdo->lastInsertId();
            $action = 'created';
        }
    }

    // delivery_boys profile row
    $pdo->prepare('DELETE FROM delivery_boys WHERE user_id = ?')->execute([$id]);
    $pdo->prepare('INSERT INTO delivery_boys (user_id, vehicle_type, availability) VALUES (?, ?, 1)')
        ->execute([$id, 'Two-wheeler']);

    echo "  {$action}: #{$id} {$b['name']} ({$b['phone']})\n";
}

// 3. Report
echo "\nFinal delivery roster in DB:\n";
foreach ($pdo->query("SELECT id, name, phone FROM users WHERE role = 'delivery' ORDER BY id") as $r) {
    echo "  #{$r['id']}  {$r['name']}  {$r['phone']}\n";
}
$count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'delivery'")->fetchColumn();
echo "\nTotal delivery accounts: {$count} (expected 3)\n";
