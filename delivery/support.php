<?php
// ============================================================
// delivery/support.php — Support & Complaints
// ============================================================
require_once __DIR__ . '/bootstrap.php';
delivery_require_login('support.php');

$pdo     = delivery_db();
$agentId = delivery_id();
$message = '';
$error   = '';

// Ensure support_tickets table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_support_tickets (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        agent_id   INTEGER NOT NULL,
        subject    TEXT NOT NULL,
        message    TEXT NOT NULL,
        status     TEXT NOT NULL DEFAULT 'open',
        response   TEXT,
        created_at TEXT DEFAULT (datetime('now', 'localtime')),
        updated_at TEXT DEFAULT (datetime('now', 'localtime'))
    )");
} catch (Throwable $e) {}

// Submit ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $msg     = trim($_POST['message'] ?? '');
    if (!$subject || !$msg) {
        $error = 'Subject and message are required.';
    } else {
        try {
            $pdo->prepare("INSERT INTO delivery_support_tickets (agent_id, subject, message) VALUES (?,?,?)")
                ->execute([$agentId, $subject, $msg]);
            $message = 'Support request submitted. We will get back to you soon.';
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch my tickets
$tickets = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM delivery_support_tickets WHERE agent_id=? ORDER BY id DESC LIMIT 30");
    $stmt->execute([$agentId]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

delivery_layout('Support', 'support', function () use ($tickets, $message, $error) {
?>
    <?php if ($message): ?>
    <div class="dp-card" style="border-left:4px solid #0f7b3a;background:#f0fdf4;">
        <div class="dp-sec" style="padding:12px 16px;color:#0f7b3a;font-weight:600;">
            <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($message, ENT_QUOTES) ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="dp-card" style="border-left:4px solid #dc2626;background:#fef2f2;">
        <div class="dp-sec" style="padding:12px 16px;color:#dc2626;font-weight:600;">
            <i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- QUICK CONTACTS -->
    <section class="dp-card">
        <div class="dp-card-h"><h3><i class="fa-solid fa-headset"></i> Quick Support</h3></div>
        <div class="dp-sec" style="padding:14px;display:flex;gap:10px;flex-wrap:wrap;">
            <a href="tel:8088584421" class="dp-btn dp-btn-primary"><i class="fa-solid fa-phone"></i> Call Office</a>
            <a href="https://wa.me/918088584421" target="_blank" class="dp-btn" style="background:#25D366;color:#fff;">
                <i class="fa-brands fa-whatsapp"></i> WhatsApp Office
            </a>
        </div>
    </section>

    <!-- SUBMIT COMPLAINT -->
    <section class="dp-card">
        <div class="dp-card-h"><h3><i class="fa-solid fa-ticket"></i> Submit Complaint / Request</h3></div>
        <div class="dp-sec" style="padding:16px;">
            <form method="POST" style="display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label class="dp-label">Subject</label>
                    <input name="subject" class="dp-input" placeholder="e.g. Payment issue, Customer complaint…" required>
                </div>
                <div>
                    <label class="dp-label">Details</label>
                    <textarea name="message" class="dp-input" rows="4" placeholder="Describe the issue in detail…" required style="resize:vertical;min-height:90px;"></textarea>
                </div>
                <button type="submit" class="dp-btn dp-btn-primary" style="max-width:160px;">
                    <i class="fa-solid fa-paper-plane"></i> Submit
                </button>
            </form>
        </div>
    </section>

    <!-- TICKET HISTORY -->
    <?php if (!empty($tickets)): ?>
    <section class="dp-card">
        <div class="dp-card-h"><h3><i class="fa-solid fa-clock-rotate-left"></i> My Tickets</h3></div>
        <div class="dp-list">
        <?php foreach ($tickets as $t):
            $statusColor = match($t['status']) {
                'resolved' => '#0f7b3a',
                'open'     => '#d97706',
                default    => '#6b7280',
            };
        ?>
            <div class="dp-item" style="flex-direction:column;align-items:flex-start;gap:6px;">
                <div style="display:flex;justify-content:space-between;width:100%;">
                    <strong><?= htmlspecialchars($t['subject'], ENT_QUOTES) ?></strong>
                    <span style="font-size:0.78rem;font-weight:700;color:<?= $statusColor ?>;"><?= ucfirst($t['status']) ?></span>
                </div>
                <small class="dp-muted"><?= htmlspecialchars(substr($t['message'], 0, 100), ENT_QUOTES) ?><?= strlen($t['message']) > 100 ? '…' : '' ?></small>
                <?php if (!empty($t['response'])): ?>
                <div style="background:#f0fdf4;border-radius:8px;padding:8px 12px;font-size:0.82rem;width:100%;margin-top:4px;">
                    <strong style="color:#0f7b3a;"><i class="fa-solid fa-reply"></i> Response:</strong>
                    <?= htmlspecialchars($t['response'], ENT_QUOTES) ?>
                </div>
                <?php endif; ?>
                <small class="dp-muted"><?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></small>
            </div>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
<style>
.dp-label { display:block; font-size:0.82rem; font-weight:600; color:#374151; margin-bottom:4px; }
</style>
<?php
});
?>
