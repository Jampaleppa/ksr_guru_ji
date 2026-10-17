<?php
// ============================================================
// patient-forgot-password.php - request reset via WhatsApp OTP
// ============================================================
require_once __DIR__ . '/patient-auth.php';

$msg = '';
$msgType = 'error';
$sentTo = '';

if (pp_is_post()) {
  $identifier = pp_post('identifier');

  if (pp_is_rate_limited('forgot:' . pp_ip())) {
    $msg = 'Too many requests. Try again in ' . ceil(pp_rate_limit_wait() / 60) . ' minute(s).';
  } else {
    $user = pp_find_user($identifier);
    if (!$user) {
      // do not leak account existence
      $msg = 'If an account exists, an OTP has been sent.';
      $msgType = 'success';
    } else {
      $_SESSION['pending_verify_user'] = (int)$user['id'];
      pp_send_otp((int)$user['id'], $user['mobile'] ?? ($user['phone'] ?? ''), 'reset_password');
      $sentTo = $user['mobile'] ?? ($user['phone'] ?? '');
      pp_log_attempt('forgot:' . $identifier, true);
      header('Location: patient-verify.php?purpose=reset_password');
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | KSR GURU JI</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --brand: #0F9D76;
      --brand-dark: #0B7D5E;
      --border: #E2E8F0;
      --muted: #64748B
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      font-family: 'Inter', Segoe UI, system-ui, sans-serif;
      background: #F1F5F9;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 24px 16px
    }

    .card {
      width: 100%;
      max-width: 420px;
      background: #fff;
      border-radius: 18px;
      padding: 32px;
      box-shadow: 0 18px 50px -22px rgba(15, 23, 42, .35);
      border: 1px solid var(--border)
    }

    .brand {
      text-align: center;
      margin-bottom: 22px
    }

    .brand i {
      font-size: 2.4rem;
      color: var(--brand)
    }

    .brand h1 {
      font-size: 1.25rem;
      margin: 8px 0 4px;
      font-weight: 800
    }

    .brand p {
      color: var(--muted);
      font-size: .88rem;
      margin: 0
    }

    .f {
      margin-bottom: 16px
    }

    .f label {
      display: block;
      font-size: .82rem;
      font-weight: 700;
      color: var(--muted);
      margin-bottom: 6px
    }

    .f input {
      width: 100%;
      padding: 11px 12px;
      border: 1px solid var(--border);
      border-radius: 10px;
      font-size: .94rem;
      background: #FBFCFD
    }

    .btn {
      width: 100%;
      background: var(--brand);
      color: #fff;
      border: 0;
      padding: 13px;
      border-radius: 12px;
      font-weight: 800;
      cursor: pointer
    }

    .btn:hover {
      background: var(--brand-dark)
    }

    .alert {
      padding: 12px 14px;
      border-radius: 10px;
      font-size: .88rem;
      font-weight: 600;
      margin-bottom: 18px;
      text-align: left
    }

    .alert-error {
      background: #FEF2F2;
      color: #DC2626;
      border: 1px solid #FECACA
    }

    .alert-success {
      background: #ECFDF5;
      color: #047857;
      border: 1px solid #A7F3D0
    }

    .foot {
      text-align: center;
      margin-top: 20px;
      font-size: .9rem
    }

    a.link {
      color: var(--brand);
      font-weight: 700;
      text-decoration: none
    }
  </style>
</head>

<body>
  <div class="card">
    <div class="brand"><i class="fas fa-key"></i>
      <h1>Forgot Password</h1>
      <p>We'll verify you with a WhatsApp OTP</p>
    </div>
    <?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= pp_e($msg) ?></div><?php endif; ?>
    <form method="POST">
      <div class="f"><label>Mobile / Email</label>
        <input name="identifier" required placeholder="9876543210 or name@example.com" autofocus>
      </div>
      <button class="btn"><i class="fab fa-whatsapp" style="color:#25D366;"></i> Send Reset OTP</button>
    </form>
    <div class="foot"><a class="link" href="patient-login.php"><i class="fas fa-arrow-left"></i> Back to login</a></div>
  </div>
</body>

</html>