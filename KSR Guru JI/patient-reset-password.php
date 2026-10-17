<?php
// ============================================================
// patient-reset-password.php - set new password after OTP verify
// ============================================================
require_once __DIR__ . '/patient-auth.php';

$userId = (int)($_SESSION['otp_verified_user'] ?? 0);
if ($userId <= 0) {
    pp_redirect('patient-login.php');
}

$msg = '';
$msgType = 'error';

if (pp_is_post()) {
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if (strlen($password) < 8) {
        $msg = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $msg = 'Password must contain letters and numbers.';
    } elseif ($password !== $confirm) {
        $msg = 'Password and confirmation do not match.';
    } else {
        pp_db()->prepare("UPDATE users SET password = ?, failed_logins = 0 WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        // invalidate remember tokens for safety
        pp_db()->prepare("DELETE FROM patient_remember_tokens WHERE user_id = ?")->execute([$userId]);

        unset($_SESSION['otp_verified_user'], $_SESSION['pending_verify_user']);
        pp_log_attempt('reset:' . $userId, true, 'otp');
        pp_redirect('patient-login.php?reset=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password | KSR GURU JI</title>
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
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="brand"><i class="fas fa-lock-open"></i>
            <h1>Set New Password</h1>
            <p>Choose a strong password for your account</p>
        </div>
        <?php if ($msg): ?><div class="alert"><?= pp_e($msg) ?></div><?php endif; ?>
        <form method="POST" autocomplete="new-password">
            <div class="f"><label>New Password</label><input type="password" name="password" required minlength="8"></div>
            <div class="f"><label>Confirm Password</label><input type="password" name="confirm_password" required minlength="8"></div>
            <button class="btn"><i class="fas fa-check"></i> Update Password</button>
        </form>
    </div>
</body>

</html>