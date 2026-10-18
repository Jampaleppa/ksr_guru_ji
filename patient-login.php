<?php
// ============================================================
// patient-login.php - Patient Portal Login
// Password login | OTP login | Remember Me | Forgot Password
// ============================================================
require_once __DIR__ . '/patient-auth.php';

pp_remember_consume();
if (pp_is_logged_in()) {
    pp_redirect(pp_dashboard_url());
}

$mode   = pp_post('mode') ?: ($_GET['mode'] ?? 'password');
$msg = '';
$msgType = 'error';
$identifier = pp_post('identifier');

$safeNext = pp_dashboard_url(); // next= only honoured for local php files
if (isset($_GET['next']) && preg_match('/^[A-Za-z0-9_\-]+\.php$/', (string)$_GET['next'])) {
    $safeNext = (string)$_GET['next'];
}

if (pp_is_post()) {
    $remember = isset($_POST['remember_me']);

    if (pp_is_rate_limited('login:' . $identifier)) {
        $msg = 'Too many failed attempts. Try again in ' . ceil(pp_rate_limit_wait() / 60) . ' minute(s).';
    } else {
        $user = pp_find_user($identifier);

        if (!$user) {
            $msg = 'No account found for this mobile or email.';
            pp_log_attempt('login:' . $identifier, false);
        } else {
            $status = pp_user_status($user);

            if ($status === 'SUSPENDED' || $status === 'LOCKED') {
                $msg = 'This account is ' . strtolower($status) . '. Please contact support.';
                pp_log_attempt('login:' . $identifier, false);
            } elseif ($status === 'PENDING_VERIFICATION') {
                $_SESSION['pending_verify_user'] = (int)$user['id'];
                pp_redirect('patient-verify.php?purpose=registration');
            } elseif ($mode === 'otp') {
                $_SESSION['pending_verify_user'] = (int)$user['id'];
                pp_send_otp((int)$user['id'], $user['mobile'] ?? ($user['phone'] ?? ''), 'login');
                pp_redirect('patient-verify.php?purpose=login');
            } else {
                $password = (string)($_POST['password'] ?? '');
                if (password_verify($password, (string)$user['password'])) {
                    pp_login($user, $remember);
                    pp_log_attempt('login:' . $identifier, true);
                    pp_redirect($safeNext);
                } else {
                    if (in_array('failed_logins', pp_db()->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN), true)) {
                        pp_db()->prepare("UPDATE users SET failed_logins = failed_logins + 1 WHERE id = ?")
                            ->execute([$user['id']]);
                    }
                    $msg = 'Incorrect password.';
                    pp_log_attempt('login:' . $identifier, false);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login | KSR GURU JI</title>
    <meta name="description" content="Sign in to the KSR GURU JI Patient Portal to manage appointments, prescriptions, lab reports and pharmacy orders.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="WhatsApp/telehealth/portal-auth.css">
</head>

<body>
    <div class="auth-shell">

        <!-- Brand panel -->
        <aside class="auth-aside">
            <a href="index.php" class="aa-logo">
                <span class="aa-logo-mark"><i class="fas fa-heart-pulse"></i></span>
                <span>KSR GURU JI</span>
            </a>

            <div>
                <span class="aa-eyebrow"><i class="fas fa-shield-halved"></i> Patient Portal</span>
                <h1 class="aa-title">Your complete care journey, in one place.</h1>
                <p class="aa-lead">
                    Book consultations, track prescriptions, view lab reports and order medicines —
                    securely connected to verified practitioners across the KSR GURU JI ecosystem.
                </p>

                <ul class="aa-features">
                    <li><i class="fas fa-calendar-check"></i> Real-time appointment scheduling</li>
                    <li><i class="fas fa-file-prescription"></i> Digital prescriptions &amp; refills</li>
                    <li><i class="fas fa-flask"></i> Lab reports &amp; medical documents</li>
                    <li><i class="fab fa-whatsapp"></i> Passwordless OTP sign-in</li>
                </ul>

                <div class="aa-stats">
                    <div>
                        <div class="aa-stat-val">24/7</div>
                        <div class="aa-stat-lbl">Portal Access</div>
                    </div>
                    <div>
                        <div class="aa-stat-val">100%</div>
                        <div class="aa-stat-lbl">Verified Doctors</div>
                    </div>
                    <div>
                        <div class="aa-stat-val">Secure</div>
                        <div class="aa-stat-lbl">Encrypted Records</div>
                    </div>
                </div>
            </div>

            <div style="font-size:0.8rem;color:rgba(255,255,255,0.6);">
                &copy; <?php echo date('Y'); ?> KSR GURU JI &middot; Registered Telemedicine Services
            </div>
        </aside>

        <!-- Form panel -->
        <main class="auth-panel">
            <div class="auth-card">
                <div class="auth-head">
                    <div class="auth-badge"><i class="fas fa-user-circle"></i></div>
                    <h1>Welcome Back</h1>
                    <p>Sign in to manage appointments, prescriptions &amp; reports</p>
                </div>

                <div class="auth-form">
                    <?php if ($msg): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-circle-exclamation"></i> <span><?= pp_e($msg) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off" novalidate>
                        <input type="hidden" name="mode" value="password">

                        <div class="af-group">
                            <label class="af-label" for="identifier">Mobile / Email</label>
                            <div class="af-wrap">
                                <i class="fas fa-at lead"></i>
                                <input id="identifier" class="af-input" name="identifier" required
                                       value="<?= pp_e($identifier) ?>"
                                       placeholder="9876543210 or name@example.com"
                                       inputmode="email" autocomplete="username">
                            </div>
                        </div>

                        <div class="af-group">
                            <label class="af-label" for="password">Password</label>
                            <div class="af-wrap">
                                <i class="fas fa-lock lead"></i>
                                <input id="password" class="af-input" name="password" type="password"
                                       placeholder="Enter your password" autocomplete="current-password">
                                <button type="button" class="af-toggle" data-toggle-password="password"
                                        aria-label="Show password"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>

                        <div class="af-row">
                            <label class="af-check">
                                <input type="checkbox" name="remember_me" value="1"> Remember Me
                            </label>
                            <a class="af-link" href="patient-forgot-password.php">Forgot Password?</a>
                        </div>

                        <button type="submit" class="af-submit">
                            <i class="fas fa-right-to-bracket"></i> Sign In
                        </button>
                    </form>

                    <div class="af-divider">or</div>

                    <form method="POST">
                        <input type="hidden" name="mode" value="otp">
                        <input type="hidden" name="identifier" value="<?= pp_e($identifier) ?>">
                        <button type="submit" class="af-alt">
                            <i class="fab fa-whatsapp" style="color:#25D366;"></i> Login with OTP (WhatsApp)
                        </button>
                    </form>
                </div>

                <div class="af-foot">
                    New to KSR GURU JI? <a href="patient-register.php">Create patient account</a>
                    <div><a class="af-back" href="index.php"><i class="fas fa-arrow-left"></i> Back to home</a></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.togglePassword);
                if (!input) return;

                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            });
        });
    </script>
</body>

</html>
