<?php
declare(strict_types=1);
/**
 * scripts/cron_cod_reconciliation.php
 * ─────────────────────────────────────────────────────────────────
 * CRON: Daily COD reconciliation report (run once per day at 23:00)
 *
 * Uses `payment_transactions`, `cod_collections`, and `cod_handovers`.
 * Compatible with both MySQL and SQLite fallback.
 * ─────────────────────────────────────────────────────────────────
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

const MAX_DAYS_IN_TRANSIT = 2;   // Alert if cash not handed over after N days
const ALERT_EMAIL         = '';  // Set to 'accounts@example.com' to enable email alerts

require_once dirname(__DIR__) . '/payment-core/bootstrap.php';

use KsrPay\AuditLogger;
use KsrPay\AmountCalculator;

/** @var \PDO $pdo */
$pdo = $pdo ?? getDBConnection();

$now    = new DateTimeImmutable();
$today  = $now->format('Y-m-d');
$cutoff = $now->modify('-' . MAX_DAYS_IN_TRANSIT . ' days')->format('Y-m-d H:i:s');
$audit  = new AuditLogger($pdo);

echo sprintf("[%s] cron_cod_reconciliation — daily run for %s\n", $now->format('Y-m-d H:i:s'), $today);

function qs(PDO $pdo, string $sql, array $p = []): int
{
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return (int)$s->fetchColumn();
}

// ── 1. Today's COD summary ───────────────────────────────────────
$pendingPaise   = qs($pdo, "SELECT COALESCE(SUM(amount_paise),0) FROM payment_transactions WHERE method='cod' AND status='cod_pending' AND DATE(created_at)=?", [$today]);
$pendingCount   = qs($pdo, "SELECT COUNT(*) FROM payment_transactions WHERE method='cod' AND status='cod_pending' AND DATE(created_at)=?", [$today]);

$collectedPaise = qs($pdo, "SELECT COALESCE(SUM(collected_amount_paise),0) FROM cod_collections WHERE collection_status IN ('cod_collected','cod_delivered','cod_discrepancy') AND DATE(collected_at)=?", [$today]);
$collectedCount = qs($pdo, "SELECT COUNT(*) FROM cod_collections WHERE collection_status IN ('cod_collected','cod_delivered','cod_discrepancy') AND DATE(collected_at)=?", [$today]);

$handedPaise    = qs($pdo, "SELECT COALESCE(SUM(received_amount_paise),0) FROM cod_handovers WHERE handover_status IN ('completed','discrepancy') AND DATE(handover_at)=?", [$today]);
$handedCount    = qs($pdo, "SELECT COUNT(*) FROM cod_handovers WHERE handover_status IN ('completed','discrepancy') AND DATE(handover_at)=?", [$today]);

// Cash collected from customer with NO completed handover record yet
$inTransitSql =
    "FROM cod_collections cc
     LEFT JOIN cod_handovers ch
       ON ch.cod_collection_id = cc.id
      AND ch.handover_status IN ('completed','discrepancy')
     WHERE cc.collection_status IN ('cod_collected','cod_delivered','cod_discrepancy')
       AND ch.id IS NULL";

$totalInTransit      = qs($pdo, "SELECT COALESCE(SUM(cc.collected_amount_paise),0) {$inTransitSql}");
$totalInTransitCount = qs($pdo, "SELECT COUNT(*) {$inTransitSql}");

echo "\n── Today ({$today}) ──────────────────────────────────────────\n";
echo sprintf("  Pending delivery:  %3d orders  %s\n", $pendingCount,   AmountCalculator::formatINR($pendingPaise));
echo sprintf("  Collected today:   %3d orders  %s\n", $collectedCount, AmountCalculator::formatINR($collectedPaise));
echo sprintf("  Handed over today: %3d orders  %s\n", $handedCount,    AmountCalculator::formatINR($handedPaise));
echo sprintf("\n  Total cash in transit (all dates): %d orders  %s\n", $totalInTransitCount, AmountCalculator::formatINR($totalInTransit));

// ── 2. Overdue cash-in-transit alert ─────────────────────────────
$overdueStmt = $pdo->prepare(
    "SELECT cc.id, pt.payment_id, cc.collected_amount_paise, cc.collected_at,
            u.name AS partner_name, o.order_number
       FROM cod_collections cc
       JOIN payment_transactions pt ON pt.id = cc.payment_transaction_id
       JOIN orders o ON o.id = cc.order_id
       LEFT JOIN users u ON u.id = cc.delivery_partner_id
       LEFT JOIN cod_handovers ch
         ON ch.cod_collection_id = cc.id
        AND ch.handover_status IN ('completed','discrepancy')
      WHERE cc.collection_status IN ('cod_collected','cod_delivered','cod_discrepancy')
        AND ch.id IS NULL
        AND cc.collected_at IS NOT NULL
        AND cc.collected_at < ?
      ORDER BY cc.collected_at ASC"
);
$overdueStmt->execute([$cutoff]);
$overdueRows = $overdueStmt->fetchAll(PDO::FETCH_ASSOC);

$hasOverdue   = !empty($overdueRows);
$overduePaise = array_sum(array_map('intval', array_column($overdueRows, 'collected_amount_paise')));

echo "\n── Overdue Cash-in-Transit (>" . MAX_DAYS_IN_TRANSIT . " days) ───────────────\n";
if (!$hasOverdue) {
    echo "  OK — No overdue cash in transit.\n";
} else {
    echo sprintf(
        "  ALERT: %d collection(s) overdue — %s NOT yet handed over:\n",
        count($overdueRows),
        AmountCalculator::formatINR($overduePaise)
    );
    foreach ($overdueRows as $r) {
        echo sprintf(
            "     COD #%d | %s | %s | %s | collected %s | partner: %s\n",
            $r['id'],
            $r['payment_id'],
            $r['order_number'],
            AmountCalculator::formatINR((int)$r['collected_amount_paise']),
            substr((string)$r['collected_at'], 0, 16),
            $r['partner_name'] ?? 'Unassigned'
        );
    }
}

// ── 3. Write audit log entry ─────────────────────────────────────
$auditNotes = sprintf(
    "COD Daily Reconciliation %s: pending=%d (%.2f INR), collected=%d (%.2f INR), handed=%d (%.2f INR), in-transit=%d (%.2f INR), overdue=%d",
    $today,
    $pendingCount, $pendingPaise / 100,
    $collectedCount, $collectedPaise / 100,
    $handedCount, $handedPaise / 100,
    $totalInTransitCount, $totalInTransit / 100,
    count($overdueRows)
);

$audit->log(
    eventType: 'COD_DAILY_RECONCILIATION',
    notes:     $auditNotes,
    actorType: 'system',
    actorId:   0,
);

// ── 4. Optional email alert ──────────────────────────────────────
if ($hasOverdue && ALERT_EMAIL !== '') {
    $subject = "[KSR PAY ALERT] {$today} — " . count($overdueRows) . " overdue COD collection(s)";
    $body    = "KSR GURU JI PAY — COD Reconciliation Alert\n\nDate: {$today}\n";
    $body   .= sprintf("Overdue (>%d days): %d collections — %s\n\n", MAX_DAYS_IN_TRANSIT, count($overdueRows), AmountCalculator::formatINR($overduePaise));
    foreach ($overdueRows as $r) {
        $body .= sprintf("  - COD #%d | %s | %s | collected %s\n",
            $r['id'], $r['order_number'], AmountCalculator::formatINR((int)$r['collected_amount_paise']), substr((string)$r['collected_at'], 0, 16));
    }
    $body .= "\nPlease action immediately in Admin -> COD Management.\n";
    @mail(ALERT_EMAIL, $subject, $body, "From: noreply@ksrguruji.com\r\nContent-Type: text/plain; charset=utf-8");
    echo "\n  Alert email sent to " . ALERT_EMAIL . "\n";
}

echo sprintf(
    "\n[%s] cron_cod_reconciliation complete — exit %s\n",
    (new DateTimeImmutable())->format('Y-m-d H:i:s'),
    $hasOverdue ? '1 (overdue alert)' : '0'
);

exit($hasOverdue ? 1 : 0);
