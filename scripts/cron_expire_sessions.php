<?php

declare(strict_types=1);
/**
 * scripts/cron_expire_sessions.php
 * ─────────────────────────────────────────────────────────────────
 * CRON: Expire stale payment sessions (run every 15 minutes)
 *
 * Recommended crontab entry (Linux / WAMP Task Scheduler):
 *   * /15 * * * * php d:/ksr_guru_ji_upload/scripts/cron_expire_sessions.php >> d:/ksr_guru_ji_upload/storage/payment/logs/cron_sessions.log 2>&1
 *
 * What it does:
 *   • Finds all payment_transactions rows in a pending/awaiting status
 *     whose `expires_at` timestamp has passed.
 *   • Calls PaymentSessionManager::checkAndExpire() → 'expired' for each.
 *   • All transitions are audited in payment_audit_logs (immutable).
 * ─────────────────────────────────────────────────────────────────
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once dirname(__DIR__) . '/payment-core/bootstrap.php';

use KsrPay\PaymentSessionManager;
use KsrPay\PaymentStateMachine;
use KsrPay\AuditLogger;

/** @var \PDO $pdo */
$pdo = $pdo ?? getDBConnection();

$started    = new DateTimeImmutable();
$nowSql     = $started->format('Y-m-d H:i:s');
$expiredOk  = 0;
$expiredErr = 0;
$skipped    = 0;

$sessionMgr = new PaymentSessionManager($pdo);
$audit      = new AuditLogger($pdo);
$sm         = new PaymentStateMachine($pdo, $audit);

$expirableStatuses = [
    'initiated',
    'pending',
    'processing',
    'upi_awaiting',
    'upi_pending_verification',
];

$placeholders = implode(',', array_fill(0, count($expirableStatuses), '?'));

$stmt = $pdo->prepare(
    "SELECT payment_id, expires_at, status
       FROM payment_transactions
      WHERE status IN ({$placeholders})
        AND expires_at IS NOT NULL
        AND expires_at < ?
      ORDER BY expires_at ASC
      LIMIT 500"
);
$stmt->execute(array_merge($expirableStatuses, [$nowSql]));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($rows);
echo sprintf(
    "[%s] cron_expire_sessions — found %d candidate(s)\n",
    $nowSql,
    $total
);

foreach ($rows as $row) {
    $pid = $row['payment_id'];
    try {
        $ok = $sessionMgr->checkAndExpire($pid, $sm);
        if ($ok) {
            $expiredOk++;
            echo "  [OK]   {$pid} -> expired\n";
        } else {
            $skipped++;
            echo "  [SKIP] {$pid} already terminal or not stale\n";
        }
    } catch (Throwable $e) {
        $expiredErr++;
        echo "  [ERR]  {$pid} — " . $e->getMessage() . "\n";
    }
}

$elapsed = (new DateTimeImmutable())->getTimestamp() - $started->getTimestamp();
echo sprintf(
    "[%s] Done — expired: %d | skipped: %d | errors: %d | elapsed: %ds\n",
    (new DateTimeImmutable())->format('Y-m-d H:i:s'),
    $expiredOk,
    $skipped,
    $expiredErr,
    $elapsed
);

exit($expiredErr > 0 ? 1 : 0);
