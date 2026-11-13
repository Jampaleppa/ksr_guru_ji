<?php
declare(strict_types=1);

require_once __DIR__ . '/../payment-core/bootstrap.php';

use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$sec     = new SecurityManager();
$csrf    = $sec->generateCSRFToken();
$success = $error = '';
$userId  = (int)($_SESSION['user_id'] ?? 0);

$paymentId = trim($_GET['pid'] ?? '');
$orderId   = (int)($_GET['oid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please reload.';
    } else {
        $orderRef   = trim($_POST['order_ref']    ?? '');
        $issueType  = trim($_POST['issue_type']   ?? '');
        $desc       = trim($_POST['description']  ?? '');
        $contact    = trim($_POST['contact']      ?? '');

        $validIssues = ['payment_deducted', 'payment_not_confirmed', 'refund_pending', 'wrong_amount', 'other'];

        if ($orderRef === '' || !in_array($issueType, $validIssues, true) || strlen($desc) < 20) {
            $error = 'Please fill all fields. Description must be at least 20 characters.';
        } else {
            // Load order if possible
            $orderIdFromRef = null;
            if ($orderRef !== '') {
                $oStmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
                $oStmt->execute([$orderRef]);
                $orderIdFromRef = $oStmt->fetchColumn() ?: null;
            }

            $pStmt = $pdo->prepare(
                "SELECT id FROM payment_transactions WHERE payment_id = ? LIMIT 1"
            );
            $pStmt->execute([$paymentId ?: '']);
            $ptId = $pStmt->fetchColumn() ?: null;

            $pdo->prepare(
                "INSERT INTO payment_disputes
                    (payment_transaction_id, order_id, user_id, subject, description)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([
                $ptId,
                $orderIdFromRef ?? ($orderId ?: null),
                $userId ?: null,
                substr($issueType . ': ' . $orderRef, 0, 200),
                $desc,
            ]);

            $success = 'Your complaint has been submitted. Our team will contact you within 24 hours.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Support — KSR GURU JI</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.support-card{max-width:540px;margin:2rem auto;padding:1.5rem;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);background:#fff}
.form-group{margin-bottom:1rem}
label{font-weight:600;display:block;margin-bottom:.4rem;font-size:.93rem}
input[type=text],select,textarea{width:100%;padding:.7rem;border:2px solid #ddd;border-radius:8px;font-size:.93rem}
input:focus,select:focus,textarea:focus{border-color:#1a7f4b;outline:none}
.btn-submit{padding:.85rem 2rem;background:#1a7f4b;color:#fff;font-weight:700;border:none;border-radius:8px;cursor:pointer;font-size:1rem}
.alert{padding:.75rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:.92rem}
.alert-s{background:#e8f5e9;color:#1a7f4b;border:1px solid #c8e6c9}
.alert-e{background:#fde;color:#c00;border:1px solid #fbb}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../global-header.php')) include __DIR__ . '/../global-header.php'; ?>

<div class="support-card">
  <h2><i class="fas fa-headset" style="color:#1a7f4b;"></i> Payment Support</h2>
  <p style="color:#888;font-size:.9rem;margin-bottom:1.5rem">
    For payment issues, deductions, or refunds — fill the form below.
  </p>

  <?php if ($success): ?>
    <div class="alert alert-s"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <a href="/">← Back to Home</a>
  <?php else: ?>
    <?php if ($error): ?><div class="alert alert-e"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

      <div class="form-group">
        <label>Order Number / Payment Reference *</label>
        <input type="text" name="order_ref"
               value="<?= htmlspecialchars($_POST['order_ref'] ?? '') ?>"
               placeholder="e.g. KSR-20241105 or ORD-123" required>
      </div>

      <div class="form-group">
        <label>Issue Type *</label>
        <select name="issue_type" required>
          <option value="">— Select issue —</option>
          <option value="payment_deducted">Money deducted but order not confirmed</option>
          <option value="payment_not_confirmed">Payment done but showing pending</option>
          <option value="refund_pending">Refund not received</option>
          <option value="wrong_amount">Wrong amount charged</option>
          <option value="other">Other payment issue</option>
        </select>
      </div>

      <div class="form-group">
        <label>Description *</label>
        <textarea name="description" rows="4" required minlength="20"
                  placeholder="Describe the issue in detail, including UTR/bank transaction reference if available…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label>Contact Number</label>
        <input type="text" name="contact" placeholder="Your phone number" maxlength="15"
               value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>">
      </div>

      <button type="submit" class="btn-submit">
        <i class="fas fa-paper-plane"></i> Submit Complaint
      </button>
    </form>
  <?php endif; ?>

  <div style="margin-top:1.5rem;font-size:.85rem;color:#aaa;border-top:1px solid #f0f0f0;padding-top:1rem">
    <i class="fas fa-clock"></i> Response within 24 business hours.<br>
    <i class="fab fa-whatsapp" style="color:#25d366;"></i>
    WhatsApp: <a href="https://wa.me/919XXXXXXXXX" target="_blank">+91-9XXXXXXXXX</a>
  </div>
</div>
</body>
</html>

