<?php
// ============================================================
// admin/login.php
// ------------------------------------------------------------
// KSR GURU Admin / Super Admin Login
//
//   Super Admin : username/email -> password -> WhatsApp OTP -> dashboard
//   Admin       : username/email -> password -> dashboard (no OTP)
//
// Security: password_verify, session regeneration, CSRF tokens,
// login rate limiting, OTP expiry + attempt limits, audit logging.
// ============================================================

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/inc/admin_auth.php';
require_once __DIR__ . '/inc/admin_otp.php';

$pdo  = getDBConnection();
$auth = admin_auth($pdo);
$otp  = new AdminOtpService($pdo);

$auth->bootSession();

$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$error  = '';
$notice = '';

// --- LOGOUT ---
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

// --- ALREADY AUTHENTICATED ---
if ($auth->check()) {
    header('Location: dashboard.php');
    exit;
}

$stage = $_SESSION['login_stage'] ?? 'credentials';

// ============================================================
// HANDLE POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'login';

    // --- CSRF gate for all POSTs ---
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Security token expired. Please try again.';
        $action = 'none';
    }

    // --- STAGE 1: credentials ---
    if ($action === 'login') {

        $login    = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($login === '' || $password === '') {
            $error = 'Please enter your username/email and password.';
        } else {
            $result = $auth->authenticate($login, $password, $ip);

            if ($result['status'] === 'done') {
                // Regular admin: session already established.
                header('Location: dashboard.php');
                exit;
            }

            if ($result['status'] === 'otp') {
                // Super admin: request the WhatsApp OTP.
                $admin = $result['admin'];
                $send  = $otp->issue($admin, 'login');

                $_SESSION['login_stage']    = 'otp';
                $_SESSION['otp_admin_id']   = (int) $admin['id'];
                $_SESSION['otp_admin_name'] = $admin['full_name'];
                $_SESSION['otp_started_at'] = time();

                if ($send['ok']) {
                    $stage  = 'otp';
                    $notice = $send['message'];
                } else {
                    $_SESSION['login_stage'] = 'credentials';
                    $stage = 'credentials';
                    $error = $send['message'];
                }
            } else {
                $error = $result['message'];
            }
        }
    }
    // --- STAGE 2: verify OTP ---
    elseif ($action === 'verify_otp') {

        $pendingId = (int) ($_SESSION['otp_admin_id'] ?? 0);

        if ($pendingId <= 0 || ($_SESSION['login_stage'] ?? '') !== 'otp') {
            $error  = 'Your verification session has expired. Please sign in again.';
            $stage  = 'credentials';
            $_SESSION['login_stage'] = 'credentials';
        } else {
            $code   = (string) ($_POST['otp'] ?? '');
            $verify = $otp->verify($pendingId, $code, 'login');

            if ($verify['ok']) {
                $admin = $auth->findAdminById($pendingId);
                if (!$admin || $admin['status'] !== 'active') {
                    $error = 'This admin account is no longer available.';
                    $stage = 'credentials';
                    $_SESSION['login_stage'] = 'credentials';
                } else {
                    // OTP verified -> establish the authenticated session.
                    $auth->establishSession($admin, $ip, true);

                    unset(
                        $_SESSION['login_stage'],
                        $_SESSION['otp_admin_id'],
                        $_SESSION['otp_admin_name'],
                        $_SESSION['otp_started_at']
                    );

                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = $verify['message'];
                $stage = 'otp';
            }
        }
    }
    // --- STAGE 2b: resend OTP ---
    elseif ($action === 'resend_otp') {

        $pendingId = (int) ($_SESSION['otp_admin_id'] ?? 0);
        if ($pendingId > 0) {
            $admin = $auth->findAdminById($pendingId);
            if ($admin) {
                $send = $otp->issue($admin, 'login');
                if ($send['ok']) {
                    $notice = $send['message'];
                } else {
                    $error = $send['message'];
                }
            }
        }
        $stage = 'otp';
    }
    // --- CANCEL OTP ---
    elseif ($action === 'cancel_otp') {
        unset(
            $_SESSION['login_stage'],
            $_SESSION['otp_admin_id'],
            $_SESSION['otp_admin_name'],
            $_SESSION['otp_started_at']
        );
        $stage = 'credentials';
    }
}

// Refresh stage from session on GET.
$stage = $_SESSION['login_stage'] ?? $stage;

$csrf = $auth->csrfToken();
$otpGreetingName = $_SESSION['otp_admin_name'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - KSR GURU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #05140b, #0c2b18, #07190e);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .admin-container {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: stretch;
            max-width: 1100px;
            width: 100%;
        }

        .admin-login-card {
            flex: 1;
            min-width: 340px;
            max-width: 460px;
            padding: 40px 35px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.6);
            color: white;
            transition: transform 0.3s ease;
        }

        .admin-login-card:hover { transform: translateY(-4px); }

        .admin-logo { text-align: center; margin-bottom: 30px; }

        .logo-icon {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #0f7b3a, #1ea85a);
            border-radius: 50%;
            display: inline-flex;
            align-items: center; justify-content: center;
            font-size: 32px; color: white;
            margin-bottom: 12px;
            box-shadow: 0 10px 30px rgba(15, 123, 58, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.8);
            overflow: hidden;
        }

        .admin-logo h1 {
            font-size: 28px; font-weight: 700; letter-spacing: 1px;
            background: linear-gradient(135deg, #ffffff, #b3cbbd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .admin-logo h1 span { color: #1ea85a; -webkit-text-fill-color: initial; }

        .admin-subtitle { color: #829a8a; font-size: 14px; font-weight: 300; letter-spacing: 2px; }

        .form-header { margin-bottom: 25px; }
        .form-header h2 { font-size: 22px; font-weight: 600; }
        .form-header p { color: #829a8a; font-size: 14px; margin-top: 4px; }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; font-size: 13px; font-weight: 500;
            color: #cbd5e0; margin-bottom: 6px;
        }
        .form-group label i { margin-right: 8px; color: #1ea85a; }

        .form-group input {
            width: 100%; padding: 14px 18px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px; color: white; font-size: 15px;
            transition: all 0.3s ease; outline: none;
            font-family: 'Poppins', sans-serif;
        }
        .form-group input::placeholder { color: #64748b; }
        .form-group input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: #1ea85a;
            box-shadow: 0 0 0 4px rgba(30, 168, 90, 0.15);
        }

        .otp-input {
            text-align: center;
            letter-spacing: 12px;
            font-size: 26px !important;
            font-weight: 600;
            padding: 16px !important;
            font-family: monospace !important;
        }

        .password-input { position: relative; }
        .toggle-password {
            position: absolute; right: 16px; top: 50%;
            transform: translateY(-50%);
            background: transparent; border: none;
            color: #8892b0; cursor: pointer; font-size: 18px; padding: 4px;
        }
        .toggle-password:hover { color: white; }

        .form-options {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 28px;
        }
        .checkbox-label {
            display: flex; align-items: center; gap: 10px;
            color: #829a8a; font-size: 14px; cursor: pointer;
        }
        .checkbox-label input[type="checkbox"] {
            width: 18px; height: 18px; accent-color: #1ea85a; cursor: pointer;
        }
        .forgot-password { color: #e85d2c; font-size: 14px; text-decoration: none; transition: 0.3s; }
        .forgot-password:hover { color: #ff855c; text-decoration: underline; }

        .admin-login-btn {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #0f7b3a, #1ea85a);
            border: none; border-radius: 14px; color: white;
            font-size: 16px; font-weight: 600; cursor: pointer;
            transition: all 0.3s ease; display: flex;
            align-items: center; justify-content: center; gap: 12px;
            font-family: 'Poppins', sans-serif;
        }
        .admin-login-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(15, 123, 58, 0.4); }
        .admin-login-btn:active { transform: translateY(0); }

        .btn-link {
            background: transparent; border: none; color: #829a8a;
            font-size: 14px; cursor: pointer; text-decoration: underline;
            font-family: 'Poppins', sans-serif; padding: 6px;
        }
        .btn-link:hover { color: #1ea85a; }

        .stage-actions {
            display: flex; justify-content: space-between;
            align-items: center; margin-top: 14px;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5; padding: 12px 18px; border-radius: 12px;
            font-size: 14px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-danger i { font-size: 18px; }

        .alert-info {
            background: rgba(30, 168, 90, 0.15);
            border: 1px solid rgba(30, 168, 90, 0.25);
            color: #86efac; padding: 12px 18px; border-radius: 12px;
            font-size: 13.5px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-info i { font-size: 18px; }

        .security-notice {
            margin-top: 25px; padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            display: flex; align-items: center; gap: 12px;
            color: #64748b; font-size: 13px;
        }
        .security-notice i { color: #1ea85a; font-size: 18px; }

        .admin-features {
            flex: 1; min-width: 250px; max-width: 340px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 35px 28px; color: white;
            display: flex; flex-direction: column; justify-content: center;
        }
        .admin-features h3 { font-size: 20px; font-weight: 600; margin-bottom: 24px; color: #e2e8f0; }
        .admin-features h3 i { color: #1ea85a; margin-right: 10px; }
        .features-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .feature-item {
            background: rgba(255, 255, 255, 0.04);
            border-radius: 16px; padding: 20px 12px; text-align: center;
            transition: 0.3s; border: 1px solid transparent;
        }
        .feature-item:hover {
            background: rgba(15, 123, 58, 0.1);
            border-color: rgba(15, 123, 58, 0.2);
            transform: translateY(-4px);
        }
        .feature-item i { font-size: 28px; color: #1ea85a; display: block; margin-bottom: 10px; }
        .feature-item span { font-size: 13px; font-weight: 500; color: #cbd5e0; }

        @media (max-width: 768px) {
            .admin-container { flex-direction: column; align-items: center; }
            .admin-login-card, .admin-features { max-width: 100%; width: 100%; }
            .admin-features { padding: 25px; }
        }
        @media (max-width: 480px) {
            .admin-login-card { padding: 24px 18px; border-radius: 14px; }
            .admin-logo h1 { font-size: 1.5rem; }
            .logo-icon { width: 60px; height: 60px; }
            .admin-features { padding: 18px 14px; }
            .features-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .feature-item { padding: 14px 10px; }
        }

        @media (max-width: 360px) {
            .admin-logo h1 { font-size: 1.3rem; }
            .features-grid { grid-template-columns: 1fr; }
            .admin-login-card { padding: 18px 12px; }
        }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>

<body class="login-body">
    <div class="admin-container">
        <div class="admin-login-card">
            <div class="admin-logo">
                <div class="logo-icon">
                    <img src="../company_logo.jpg" alt="KSR GURU JI Logo"
                         style="max-width: 85%; max-height: 85%; object-fit: contain; border-radius: 50%;"
                         onerror="this.style.display='none';this.parentElement.innerHTML='🛡️';">
                </div>
                <h1>KSR <span>GURU JI</span></h1>
                <p class="admin-subtitle">Administrator Portal</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($notice): ?>
                <div class="alert-info">
                    <i class="fab fa-whatsapp"></i>
                    <span><?= htmlspecialchars($notice) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($stage === 'otp'): ?>
                <!-- ============ STAGE 2: WHATSAPP OTP ============ -->
                <form method="POST" action="" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <div class="form-header">
                        <h2><i class="fab fa-whatsapp" style="color:#1ea85a;"></i> WhatsApp Verification</h2>
                        <p>Enter the 6-digit code sent to the WhatsApp number on file for
                           <strong><?= htmlspecialchars($otpGreetingName) ?></strong>.</p>
                    </div>

                    <div class="form-group">
                        <label for="otp"><i class="fas fa-key"></i> Verification Code</label>
                        <input type="text" id="otp" name="otp" class="otp-input"
                               inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                               placeholder="000000" autocomplete="one-time-code" required autofocus>
                    </div>

                    <button type="submit" name="action" value="verify_otp" class="admin-login-btn">
                        <i class="fas fa-shield-alt"></i> Verify &amp; Continue
                    </button>

                    <div class="stage-actions">
                        <button type="submit" name="action" value="resend_otp" class="btn-link"
                                formnovalidate>
                            <i class="fas fa-redo"></i> Resend code
                        </button>
                        <button type="submit" name="action" value="cancel_otp" class="btn-link"
                                formnovalidate>
                            <i class="fas fa-arrow-left"></i> Back to login
                        </button>
                    </div>

                    <div class="security-notice">
                        <i class="fas fa-clock"></i>
                        <p>Codes expire after 5 minutes. Attempts and resends are limited.</p>
                    </div>
                </form>
            <?php else: ?>
                <!-- ============ STAGE 1: CREDENTIALS ============ -->
                <form class="admin-login-form" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <div class="form-header">
                        <h2>Sign In</h2>
                        <p>Enter your credentials to access the admin panel</p>
                    </div>

                    <div class="form-group">
                        <label for="adminLogin"><i class="fas fa-user"></i> Username or Email</label>
                        <input type="text" id="adminLogin" name="login"
                               placeholder="superadmin / admin@ksrguru.com" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="adminPassword"><i class="fas fa-lock"></i> Password</label>
                        <div class="password-input">
                            <input type="password" id="adminPassword" name="password"
                                   placeholder="Enter your password" required>
                            <button type="button" class="toggle-password" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" id="rememberMe">
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot-password">Forgot Password?</a>
                    </div>

                    <button type="submit" name="action" value="login" class="admin-login-btn">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>

                    <div class="security-notice">
                        <i class="fas fa-shield-alt"></i>
                        <p>Super Admin sign-in is protected by WhatsApp OTP. Unauthorized access is prohibited.</p>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- ======== FEATURES SIDEBAR ======== -->
        <div class="admin-features">
            <h3><i class="fas fa-crown"></i> Admin Features</h3>
            <div class="features-grid">
                <div class="feature-item"><i class="fas fa-chart-line"></i><span>Dashboard Analytics</span></div>
                <div class="feature-item"><i class="fas fa-boxes"></i><span>Inventory Management</span></div>
                <div class="feature-item"><i class="fas fa-users-cog"></i><span>User Management</span></div>
                <div class="feature-item"><i class="fas fa-file-invoice-dollar"></i><span>Order Processing</span></div>
                <div class="feature-item"><i class="fas fa-truck"></i><span>Live Delivery Tracking</span></div>
                <div class="feature-item"><i class="fas fa-shield-halved"></i><span>RBAC &amp; Audit Logs</span></div>
            </div>
            <p style="margin-top: 25px; color: #475569; font-size: 13px; text-align: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 20px;">
                <i class="fas fa-lock" style="color: #1ea85a;"></i> SSL Secured • 256-bit Encryption
            </p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('adminPassword');
            if (toggleBtn && passwordInput) {
                toggleBtn.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }

            // OTP field: digits only.
            const otpField = document.getElementById('otp');
            if (otpField) {
                otpField.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').slice(0, 6);
                });
            }
        });
    </script>
</body>

</html>