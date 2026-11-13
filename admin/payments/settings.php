<?php
declare(strict_types=1);

require_once __DIR__ . '/../../payment-core/bootstrap.php';

use KsrPay\SecurityManager;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php'); exit;
}

$sec     = new SecurityManager();
$csrf    = $sec->generateCSRFToken();
$adminId = (int)$_SESSION['user_id'];
$msg     = '';

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!$sec->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = '<div style="color:#c00">Invalid CSRF token.</div>';
    } else {
        $allowed = [
            'upi' => ['merchant_name', 'upi_id', 'display_name', 'qr_image_path'],
            'bank_transfer' => ['account_holder', 'bank_name', 'account_number', 'ifsc', 'account_type'],
        ];

        foreach ($allowed as $gateway => $keys) {
            foreach ($keys as $key) {
                $postKey = $gateway . '__' . $key;
                if (isset($_POST[$postKey])) {
                    $val = trim($_POST[$postKey]);
                    $pdo->prepare(
                        "INSERT INTO payment_gateway_settings (gateway_code, setting_key, setting_value, updated_by)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)"
                    )->execute([$gateway, $key, $val, $adminId]);
                }
            }
        }
        $msg = '<div style="color:#1a7f4b;background:#e8f5e9;padding:.7rem 1rem;border-radius:6px;margin-bottom:1rem">Settings saved successfully.</div>';
    }
}

// Load all non-secret settings
$stmt = $pdo->query("SELECT gateway_code, setting_key, setting_value FROM payment_gateway_settings WHERE is_secret = 0 ORDER BY gateway_code, setting_key");
$settings = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $settings[$r['gateway_code']][$r['setting_key']] = $r['setting_value'];
}

function sv(array $settings, string $gw, string $key): string {
    return htmlspecialchars($settings[$gw][$key] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Settings — Admin — KSR GURU JI PAY</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.admin-wrap{max-width:800px;margin:1.5rem auto;padding:0 1rem}
.section{background:#fff;border-radius:10px;padding:1.5rem;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:1.5rem}
.section h3{margin-top:0;color:#1a7f4b;font-size:1rem;border-bottom:2px solid #f0f0f0;padding-bottom:.6rem}
.form-row{display:flex;flex-direction:column;gap:.3rem;margin-bottom:1rem}
label{font-weight:600;font-size:.88rem;color:#555}
input[type=text]{padding:.65rem;border:2px solid #ddd;border-radius:7px;font-size:.93rem;width:100%;box-sizing:border-box}
input[type=text]:focus{border-color:#1a7f4b;outline:none}
.btn-save{padding:.8rem 2rem;background:#1a7f4b;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:700;font-size:.98rem}
.warning-box{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:.8rem 1rem;font-size:.88rem;margin-bottom:1rem}
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../global-header.php')) include __DIR__ . '/../../global-header.php'; ?>

<div class="admin-wrap">
  <h2><a href="/admin/payments/dashboard.php" style="color:#1a7f4b;text-decoration:none">← Dashboard</a> &nbsp; Payment Gateway Settings</h2>

  <?= $msg ?>

  <div class="warning-box">
    <i class="fas fa-exclamation-triangle" style="color:#f9a825;"></i>
    Changes take effect immediately for all new payments. Sensitive fields (Account Number, IFSC) are marked with 🔒.
    After SBI onboarding, replace the PLACEHOLDER values below.
  </div>

  <form method="post">
    <input type="hidden" name="csrf_token"    value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="save_settings" value="1">

    <!-- UPI Settings -->
    <div class="section">
      <h3><i class="fas fa-mobile-alt"></i> UPI Payment Settings</h3>
      <div class="form-row"><label>Merchant Name</label>
        <input type="text" name="upi__merchant_name" value="<?= sv($settings,'upi','merchant_name') ?>" placeholder="KSR GURU JI"></div>
      <div class="form-row"><label>UPI ID (after SBI onboarding)</label>
        <input type="text" name="upi__upi_id" value="<?= sv($settings,'upi','upi_id') ?>" placeholder="yourmerchant@sbi"></div>
      <div class="form-row"><label>Display Name (shown to customers)</label>
        <input type="text" name="upi__display_name" value="<?= sv($settings,'upi','display_name') ?>" placeholder="KSR GURU JI PAY"></div>
      <div class="form-row"><label>QR Image Path (server path after upload)</label>
        <input type="text" name="upi__qr_image_path" value="<?= sv($settings,'upi','qr_image_path') ?>" placeholder="/assets/images/upi-qr.png"></div>
    </div>

    <!-- Bank Transfer Settings -->
    <div class="section">
      <h3><i class="fas fa-university"></i> Bank Transfer Settings</h3>
      <div class="form-row"><label>Account Holder Name</label>
        <input type="text" name="bank_transfer__account_holder" value="<?= sv($settings,'bank_transfer','account_holder') ?>" placeholder="KSR GURU JI"></div>
      <div class="form-row"><label>Bank Name</label>
        <input type="text" name="bank_transfer__bank_name" value="<?= sv($settings,'bank_transfer','bank_name') ?>" placeholder="State Bank of India"></div>
      <div class="form-row"><label>Account Number 🔒</label>
        <input type="text" name="bank_transfer__account_number" value="<?= sv($settings,'bank_transfer','account_number') ?>" placeholder="Your account number" autocomplete="off"></div>
      <div class="form-row"><label>IFSC Code</label>
        <input type="text" name="bank_transfer__ifsc" value="<?= sv($settings,'bank_transfer','ifsc') ?>" placeholder="SBIN0001234"></div>
      <div class="form-row"><label>Account Type</label>
        <input type="text" name="bank_transfer__account_type" value="<?= sv($settings,'bank_transfer','account_type') ?>" placeholder="Savings"></div>
    </div>

    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Settings</button>
  </form>
</div>
</body>
</html>

