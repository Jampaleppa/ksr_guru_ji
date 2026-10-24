<?php
// ============================================================
// patient-verify.php - WhatsApp OTP verification
// PENDING_VERIFICATION -> ACTIVE  (registration)
// also used for OTP login / password reset confirmation
// ============================================================
require_once __DIR__ . '/patient-auth.php';

$purpose  = pp_post('purpose') ?: ($_GET['purpose'] ?? 'registration');
$userId   = (int)($_SESSION['pending_verify_user'] ?? $_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    pp_redirect('patient-login.php');
}

$uStmt = pp_db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();
if (!$user) {
    pp_redirect('patient-login.php');
}

$destination = $user['mobile'] ?? ($user['phone'] ?? '');
$msg = '';
$msgType = 'error';
$otpSent = !empty($_SESSION['otp_delivery_ok']);
$deliveryFailureMessage = static function (): string {
    return match ($_SESSION['otp_delivery_error'] ?? '') {
        'configuration' => 'WhatsApp OTP delivery is not configured. Please use password login or contact support.',
        'rate_limited' => 'Too many OTP requests. Please wait before requesting another code.',
        'template_unavailable' => 'The WhatsApp authentication template is unavailable or not approved. Please contact support.',
        'delivery_failed' => 'WhatsApp could not accept the OTP. Please try again later or contact support.',
        default => 'We could not send your OTP. Please try again later or contact support.',
    };
};

// auto-send OTP when arriving fresh
if (!pp_is_post() && empty($_GET['resent'])
    && (($_SESSION['otp_delivery_context'] ?? '') !== ($userId . ':' . $purpose)
        || empty($_SESSION['otp_delivery_attempted']))) {
    pp_send_otp($userId, $destination, $purpose);
}
$otpSent = !empty($_SESSION['otp_delivery_ok']);
if (!$otpSent && !pp_is_post() && !empty($_SESSION['otp_delivery_error'])) {
    $msg = $deliveryFailureMessage();
}

if (pp_is_post()) {
    $action = pp_post('action');
    $code   = preg_replace('/\D+/', '', pp_post('otp'));

    if ($action === 'resend') {
        $otpSent = pp_send_otp($userId, $destination, $purpose);
        if ($otpSent) {
            $msg = 'WhatsApp accepted a new OTP request for delivery.';
            $msgType = 'success';
        } else {
            $msg = $deliveryFailureMessage();
        }
    } elseif (preg_match('/^\d{4,8}$/', $code)) {
        if (pp_verify_otp($userId, $code, $purpose)) {
            if ($purpose === 'registration') {
                pp_activate_account($userId);

                // Send WhatsApp registration success confirmation message
                try {
                    $waMobile = preg_replace('/\D+/', '', (string)($user['mobile'] ?? ($user['phone'] ?? '')));
                    if (strlen($waMobile) === 10) { $waMobile = '91' . $waMobile; }
                    if ($waMobile !== '') {
                        require_once __DIR__ . '/admin/inc/whatsapp/WhatsAppFactory.php';
                        $wa = WhatsAppFactory::make(pp_db());
                        $patientName = $user['name'] ?? 'Patient';
                        $wa->sendMessage($waMobile,
                            "🎉 *Registration Complete & Verified!*\n\n" .
                            "Namaste *{$patientName}*,\n" .
                            "Your KSR GURU JI Patient Portal account is now active.\n\n" .
                            "✅ Verified Practitioners available:\n" .
                            "   • Dr. Shivukumar Rathod (MBBS, MD)\n" .
                            "   • Dr. D C Rathod (MBBS)\n" .
                            "✅ Maintain all medical records, vitals & digital prescriptions.\n\n" .
                            "🔗 Portal: https://ksrgurujiupload.vercel.app/WhatsApp/telehealth/patient-dashboard.php\n" .
                            "📞 Support: 6362079192"
                        );
                    }
                } catch (Throwable $waEx) {
                    error_log('[patient-verify WhatsApp Success] ' . $waEx->getMessage());
                }

                unset($_SESSION['pending_verify_user']);
                pp_login($user, false);
                $_SESSION['patient_registration_success'] = true;
                unset($_SESSION['otp_delivery_ok'], $_SESSION['otp_delivery_attempted'], $_SESSION['otp_delivery_error'], $_SESSION['otp_delivery_context']);
                pp_log_attempt('verify:' . $userId, true, 'otp');
                pp_redirect(pp_dashboard_url());
            } elseif ($purpose === 'login') {
                // OTP login: establish the session immediately
                unset($_SESSION['pending_verify_user']);
                pp_login($user, pp_post('remember_me') !== '');
                unset($_SESSION['otp_delivery_ok'], $_SESSION['otp_delivery_attempted'], $_SESSION['otp_delivery_error'], $_SESSION['otp_delivery_context']);
                pp_log_attempt('verify:' . $userId, true, 'otp');
                pp_redirect(pp_dashboard_url());
            } else {
                $_SESSION['otp_verified_user'] = $userId;
                pp_redirect('patient-reset-password.php');
            }
        } else {
            $msg = 'Invalid or expired OTP. Please check and retry.';
            $msgType = 'error';
            pp_log_attempt('verify:' . $userId, false, 'otp');
        }
    } else {
        $msg = 'Please enter the valid OTP.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Mobile | KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #0F9D76;
            --brand-dark: #0B7D5E;
            --border: #E2E8F0;
            --muted: #64748B;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', Segoe UI, system-ui, sans-serif;
            background: #F1F5F9;
            color: #0F172A;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 24px 16px;
        }

        .card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 18px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 18px 50px -22px rgba(15, 23, 42, .35);
            border: 1px solid var(--border);
        }

        .wa {
            width: 78px;
            height: 78px;
            margin: 0 auto 14px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #25D366;
            color: #fff;
            font-size: 2.1rem;
            box-shadow: 0 10px 24px -10px rgba(37, 211, 102, .9);
        }

        h1 {
            font-size: 1.3rem;
            margin: 0 0 6px;
            font-weight: 800;
        }

        .sub {
            color: var(--muted);
            font-size: .9rem;
            margin: 0 0 22px;
        }

        .otp-row {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 18px;
        }

        .otp-row input {
            width: 44px;
            height: 54px;
            text-align: center;
            font-size: 1.35rem;
            font-weight: 800;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .otp-row input:focus {
            outline: 2px solid rgba(15, 157, 118, .35);
            border-color: var(--brand);
        }

        .btn {
            width: 100%;
            background: var(--brand);
            color: #fff;
            border: 0;
            padding: 13px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
        }

        .btn:hover {
            background: var(--brand-dark);
        }

        .btn-ghost {
            background: transparent;
            color: var(--brand);
            font-weight: 700;
            margin-top: 14px;
            border: 0;
            cursor: pointer;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 600;
            margin-bottom: 18px;
            text-align: left;
        }

        .alert-error {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }

        .alert-success {
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #A7F3D0;
        }

        .status {
            display: inline-flex;
            gap: 6px;
            align-items: center;
            font-size: .75rem;
            font-weight: 800;
            padding: 5px 12px;
            border-radius: 999px;
            margin-bottom: 14px;
        }

        .status-pending {
            background: #FEF3C7;
            color: #B45309;
        }

        .hint {
            font-size: .8rem;
            color: var(--muted);
            margin-top: 16px;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="wa"><i class="fab fa-whatsapp"></i></div>

        <span class="status status-pending">
            <i class="fas fa-hourglass-half"></i> Account status: <?= pp_e(pp_user_status($user)) ?>
        </span>

        <h1>Verify your mobile</h1>
        <p class="sub">
            <?php if ($otpSent): ?>
                WhatsApp accepted a 6-digit OTP for delivery to <b>+<?= pp_e($destination) ?></b>. Valid for 5 minutes.
            <?php else: ?>
                An OTP has not been delivered to WhatsApp. Please use Resend OTP after delivery is configured.
            <?php endif; ?>
        </p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>">
                <i class="fas <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="otpForm" autocomplete="one-time-code">
            <input type="hidden" name="purpose" value="<?= pp_e($purpose) ?>">
            <div class="otp-row">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" inputmode="numeric" maxlength="1" pattern="\d" required aria-label="Digit <?= $i + 1 ?>">
                <?php endfor; ?>
            </div>
            <button type="submit" class="btn"><i class="fas fa-shield-halved"></i> Verify &amp; Activate</button>
        </form>

        <form method="POST" style="margin-top:6px;">
            <input type="hidden" name="action" value="resend">
            <input type="hidden" name="purpose" value="<?= pp_e($purpose) ?>">
            <button type="submit" class="btn-ghost">Resend OTP</button>
        </form>

        <p class="hint">
            <?php if ($purpose === 'login'): ?>
                <a href="patient-login.php?mode=password" style="color:var(--brand);font-weight:700;">Use password login instead</a><br>
            <?php endif; ?>
            Wrong number? <a href="patient-login.php" style="color:var(--brand);font-weight:700;">Try another account</a>
        </p>
    </div>

    <script>
        (function() {
            var inputs = Array.prototype.slice.call(document.querySelectorAll('.otp-row input'));
            inputs.forEach(function(el, i) {
                el.addEventListener('input', function() {
                    el.value = el.value.replace(/\D/g, '');
                    if (el.value && i < inputs.length - 1) inputs[i + 1].focus();
                });
                el.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !el.value && i > 0) inputs[i - 1].focus();
                });
                el.addEventListener('paste', function(e) {
                    var t = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    if (t.length) {
                        e.preventDefault();
                        inputs.forEach(function(b, j) {
                            if (b !== el) b.value = t[j] || '';
                        });
                        el.value = t[0] || '';
                        var next = Math.min(t.length, inputs.length - 1);
                        inputs[next].focus();
                    }
                });
            });
            var form = document.getElementById('otpForm');
            form.addEventListener('submit', function(e) {
                var code = inputs.map(function(i) {
                    return i.value;
                }).join('');
                if (code.length !== 6) {
                    e.preventDefault();
                    alert('Enter all 6 digits.');
                    return;
                }
                var h = document.createElement('input');
                h.type = 'hidden';
                h.name = 'otp';
                h.value = code;
                form.appendChild(h);
            });
            inputs[0].focus();
        })();
    </script>
</body>

</html>