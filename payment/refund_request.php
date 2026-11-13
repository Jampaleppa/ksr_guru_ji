<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\RefundManager;
use KsrPay\AuditLogger;
use KsrPay\AmountCalculator;
use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$userId    = (int)$_SESSION['user_id'];
$paymentId = trim($_GET['pid'] ?? '');
$calc      = new AmountCalculator();
$sec       = new SecurityManager();
$csrf      = $sec->generateCSRFToken();
$success   = $error = '';

// Load payment
$payment = null;
if ($paymentId !== '') {
    $stmt = $pdo->prepare(
        "SELECT pt.*, o.order_number FROM payment_transactions pt
         JOIN orders o ON o.id = pt.order_id
         WHERE pt.payment_id = ? AND pt.status = 'success'
           AND (pt.user_id = ? OR o.user_id = ?) LIMIT 1"
    );
    $stmt->execute([$paymentId, $userId, $userId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $payment) {
    if (!$sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $reason = trim($_POST['reason'] ?? '');
        if (strlen($reason) < 10) {
            $error = 'Please describe the reason in at least 10 characters.';
        } else {
            try {
                $refund = new RefundManager($pdo, new AuditLogger($pdo));
                $refundId = $refund->requestRefund($paymentId, $userId, (int)$payment['amount_paise'], $reason);
                $success = "Refund request #{$refundId} submitted. We'll process it within 3–5 business days.";
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Request Refund — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
.refund-card{max-width:500px;margin:2rem auto;padding:1.5rem;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);background:#fff}
textarea{width:100%;padding:.75rem;border:2px solid #ddd;border-radius:8px;font-size:.95rem;resize:vertical}
textarea:focus{border-color:#1a7f4b;outline:none}
.btn-submit{padding:.8rem 2rem;background:#1a7f4b;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:700}
.alert{padding:.75rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:.92rem}
.alert-success{background:#e8f5e9;color:#1a7f4b;border:1px solid #c8e6c9}
.alert-error{background:#fde;color:#c00;border:1px solid #fbb}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../global-header.php')) include __DIR__ . '/../global-header.php'; ?>

<div class="refund-card">
  <h2><i class="fas fa-undo" style="color:#1a7f4b;"></i> Request Refund</h2>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <a href="/payment/history.php">← View Payment History</a>
  <?php elseif (!$payment): ?>
    <p style="color:#e53e3e;">Payment not found or not eligible for refund.</p>
    <a href="/payment/history.php">← Go to Payment History</a>
  <?php else: ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div style="background:#f9f9f9;border-radius:8px;padding:1rem;margin-bottom:1.2rem;font-size:.93rem">
      <div style="display:flex;justify-content:space-between">
        <span>Order</span><strong>#<?= htmlspecialchars($payment['order_number']) ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:.4rem">
        <span>Amount</span><strong><?= $calc->formatINR((int)$payment['amount_paise']) ?></strong>
      </div>
    </div>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="payment_id" value="<?= htmlspecialchars($paymentId) ?>">

      <label style="font-weight:600;display:block;margin-bottom:.4rem">Reason for refund *</label>
      <textarea name="reason" rows="4" placeholder="Please explain why you need a refund…" required minlength="10"></textarea>

      <p style="font-size:.82rem;color:#888;margin:.5rem 0 1rem">
        Refunds are processed within 3–5 business days after admin approval.
      </p>

      <button type="submit" class="btn-submit">Submit Refund Request</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>

