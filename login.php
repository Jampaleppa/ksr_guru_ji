<?php
// ============================================================
// login.php - Unified Login Page · KSR GURU JI
// Role-based routing: admin → admin/, delivery_boy → delivery/, customer → index.php
// ============================================================
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'customer';
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($role === 'doctor') {
        header('Location: WhatsApp/telehealth/doctor-dashboard.php');
    } elseif ($role === 'patient') {
        header('Location: WhatsApp/telehealth/patient-dashboard.php');
    } elseif ($role === 'delivery' || $role === 'delivery_boy') {
        header('Location: delivery/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$successMsg = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = getDBConnection();

            // Find user by email
            $stmt = $pdo->prepare('SELECT id, name, email, password, phone, role FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['user_phone'] = $user['phone'] ?? '';

                // Log the login event
                try {
                    $logStmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, entity, details, created_at) VALUES (?, 'LOGIN', 'users', ?, NOW())");
                    $logStmt->execute([$user['id'], 'User ' . $user['name'] . ' logged in as ' . $user['role']]);
                } catch (Exception $logEx) {
                    // Audit log table may not exist yet — silently ignore
                }

                // Role-based redirect
                $redirect = $_GET['redirect'] ?? '';
                if (!empty($redirect)) {
                    header('Location: ' . urldecode($redirect));
                    exit;
                }

                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'doctor':
                        header('Location: WhatsApp/telehealth/doctor-dashboard.php');
                        break;
                    case 'patient':
                        header('Location: WhatsApp/telehealth/patient-dashboard.php');
                        break;
                    case 'delivery':
                    case 'delivery_boy':
                        header('Location: delivery/dashboard.php');
                        break;
                    case 'customer':
                    default:
                        header('Location: index.php');
                        break;
                }
                exit;

            } else {
                $error = 'Invalid email or password. Please try again.';
                // Track failed logins (optional throttle)
                if (!isset($_SESSION['login_attempts'])) {
                    $_SESSION['login_attempts'] = 0;
                }
                $_SESSION['login_attempts']++;
            }
        } catch (PDOException $e) {
            error_log('Login DB error: ' . $e->getMessage());
            $error = 'A system error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes" />
    <title>Sign In · KSR GURU JI — Everything Your Family Needs</title>
    <meta name="description" content="Sign in to KSR GURU JI — your trusted daily essentials store. Access your orders, Doctor Assist portal, and more." />
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <style>
        /* ─── Design Tokens ─── */
        :root {
            --primary:        #0F7B3A;
            --primary-dark:   #0A5E2C;
            --primary-light:  #E8F5E9;
            --primary-grad:   linear-gradient(135deg, #0F7B3A 0%, #1EA85A 100%);
            --accent:         #E85D2C;
            --accent-grad:    linear-gradient(135deg, #E85D2C 0%, #F57C3A 100%);
            --bg:             #EFF5F1;
            --surface:        #FFFFFF;
            --border:         #E2EBE6;
            --text-dark:      #1A2520;
            --text-mid:       #4A6655;
            --text-muted:     #7A9A8A;
            --radius:         24px;
            --radius-sm:      14px;
            --shadow-sm:      0 4px 16px rgba(15, 123, 58, 0.07), 0 2px 6px rgba(0,0,0,0.04);
            --shadow-md:      0 12px 40px rgba(15, 123, 58, 0.13), 0 4px 16px rgba(0,0,0,0.06);
            --shadow-lg:      0 24px 64px rgba(15, 123, 58, 0.18), 0 8px 24px rgba(0,0,0,0.08);
            --transition:     0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', 'Poppins', sans-serif;
            background: var(--bg);
            background-image:
                radial-gradient(circle at 5% 5%, rgba(15,123,58,0.07) 0%, transparent 45%),
                radial-gradient(circle at 95% 5%, rgba(30,168,90,0.05) 0%, transparent 40%),
                radial-gradient(circle at 50% 95%, rgba(15,123,58,0.04) 0%, transparent 50%);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* ─── Card ─── */
        .login-card {
            width: 100%;
            max-width: 460px;
            background: var(--surface);
            border-radius: var(--radius);
            padding: 2.6rem 2.2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            animation: slideUp 0.4s cubic-bezier(0.25,0.46,0.45,0.94);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--primary-grad);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0);    }
        }

        /* ─── Header / Logo ─── */
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-ring {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background: var(--primary-grad);
            border-radius: 50%;
            box-shadow: 0 6px 20px rgba(15,123,58,0.28);
            margin-bottom: 1rem;
            border: 3px solid rgba(255,255,255,0.9);
            overflow: hidden;
        }

        .logo-ring img {
            width: 88%;
            height: 88%;
            object-fit: contain;
            border-radius: 50%;
        }

        .logo-ring .logo-fallback {
            font-size: 2.2rem;
            line-height: 1;
        }

        .brand-name {
            font-family: 'Poppins', sans-serif;
            font-size: 1.85rem;
            font-weight: 800;
            background: var(--primary-grad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }

        .brand-tagline {
            font-size: 0.82rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 4px;
        }

        .login-title {
            margin-top: 1rem;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
            letter-spacing: -0.3px;
        }

        /* ─── Alerts ─── */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.85rem 1.1rem;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 1.4rem;
            line-height: 1.4;
        }

        .alert-error {
            background: #FEE2E2;
            color: #B91C1C;
            border: 1px solid #FECACA;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .alert i { font-size: 1.1rem; flex-shrink: 0; }

        /* ─── Form ─── */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 0.88rem;
            margin-bottom: 7px;
            color: var(--text-dark);
        }

        .form-group label i {
            color: var(--primary);
            font-size: 0.85rem;
        }

        .form-group .input-wrap {
            position: relative;
        }

        .form-group input {
            width: 100%;
            padding: 0.85rem 1.1rem 0.85rem 2.8rem;
            border: 1.5px solid var(--border);
            border-radius: 50px;
            font-size: 0.97rem;
            font-family: inherit;
            background: var(--surface);
            color: var(--text-dark);
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15,123,58,0.1);
        }

        .form-group .field-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
            pointer-events: none;
            transition: color 0.2s;
        }

        .form-group input:focus ~ .field-icon,
        .form-group input:focus + .field-icon {
            color: var(--primary);
        }

        .form-group .toggle-pw {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            background: none;
            border: none;
            font-size: 0.9rem;
            padding: 4px;
            transition: color 0.2s;
        }

        .form-group .toggle-pw:hover { color: var(--primary); }

        /* ─── Remember / Forgot row ─── */
        .form-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.4rem;
            font-size: 0.85rem;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--text-mid);
            cursor: pointer;
        }

        .remember-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .forgot-link:hover { color: var(--accent); text-decoration: underline; }

        /* ─── Submit Button ─── */
        .btn-login {
            width: 100%;
            padding: 0.95rem;
            background: var(--accent-grad);
            color: #fff;
            border: none;
            border-radius: 50px;
            font-size: 1.05rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 18px rgba(232,93,44,0.32);
            transition: var(--transition);
            letter-spacing: 0.2px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(232,93,44,0.42);
        }

        .btn-login:active { transform: scale(0.97); }

        .btn-login .spinner {
            display: none;
            width: 18px; height: 18px;
            border: 2.5px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ─── Divider ─── */
        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ─── Role Chips (Quick Access) ─── */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 1.4rem;
        }

        .role-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            background: #FAFCFB;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--text-dark);
            font-size: 0.82rem;
            font-weight: 600;
        }

        .role-chip:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .role-chip .chip-icon {
            width: 30px; height: 30px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        /* ─── Footer Links ─── */
        .card-footer {
            text-align: center;
            margin-top: 1.6rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 2;
        }

        .card-footer a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .card-footer a:hover { color: var(--accent); text-decoration: underline; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted) !important;
            font-size: 0.85rem !important;
            font-weight: 500 !important;
            margin-top: 6px;
        }

        /* ─── Trust Row ─── */
        .trust-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            margin-top: 1.2rem;
            padding-top: 1.2rem;
            border-top: 1px solid var(--border);
            font-size: 0.72rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .trust-row span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .trust-row i { color: var(--primary); }

        /* ─── Mobile ─── */
        @media (max-width: 768px) {
            body { padding: 16px 12px; }
            .login-card { max-width: 440px; }
        }
        @media (max-width: 480px) {
            html, body { overflow-x: hidden; }
            body { padding: 12px 8px; }
            .login-card { padding: 1.8rem 1.2rem; border-radius: 18px; }
            .brand-name { font-size: 1.45rem; }
            .role-grid { grid-template-columns: 1fr; }
            .form-group input { font-size: 16px; padding-left: 2.6rem; }
            .trust-row { flex-wrap: wrap; gap: 10px; font-size: 0.7rem; }
        }
        @media (max-width: 360px) {
            .login-card { padding: 1.4rem 0.9rem; }
            .brand-name { font-size: 1.3rem; }
            .btn-login { font-size: 0.95rem; padding: 0.85rem; }
        }
    </style>
</head>
<body>
    <div class="login-card">

        <!-- Logo & Branding -->
        <div class="login-header">
            <div class="logo-ring">
                <img src="company_logo.jpg" alt="KSR GURU JI"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                <span class="logo-fallback" style="display:none;">🛒</span>
            </div>
            <div class="brand-name">KSR GURU JI</div>
            <div class="brand-tagline">Everything Your Family Needs, Every Day</div>
            <div class="login-title">Sign in to your account</div>
        </div>

        <!-- Error / Success -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''), ENT_QUOTES, 'UTF-8'); ?>" novalidate>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                <div class="input-wrap">
                    <input type="email" id="email" name="email"
                           placeholder="you@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           required autofocus autocomplete="email" />
                    <i class="fas fa-at field-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password"
                           placeholder="••••••••"
                           required autocomplete="current-password" />
                    <i class="fas fa-lock field-icon"></i>
                    <button type="button" class="toggle-pw" onclick="togglePassword()" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>

            <div class="form-meta">
                <label class="remember-label">
                    <input type="checkbox" name="remember" id="remember" />
                    Remember me
                </label>
                <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <div class="spinner" id="loginSpinner"></div>
                <i class="fas fa-sign-in-alt" id="loginIcon"></i>
                <span id="loginBtnText">Sign In</span>
            </button>
        </form>

        <!-- Role Quick Access -->
        <div class="divider">or access as</div>
        <div class="role-grid">
            <a href="admin/login.php" class="role-chip">
                <div class="chip-icon" style="background: rgba(239,68,68,0.1); color: #ef4444;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <span>Admin Panel</span>
            </a>
            <a href="delivery/delivery-boy.php?page=login" class="role-chip">
                <div class="chip-icon" style="background: rgba(139,92,246,0.1); color: #8b5cf6;">
                    <i class="fas fa-truck"></i>
                </div>
                <span>Delivery Agent</span>
            </a>

            <a href="register.php" class="role-chip">
                <div class="chip-icon" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                    <i class="fas fa-user-plus"></i>
                </div>
                <span>New Account</span>
            </a>
            <a href="WhatsApp/telehealth/index.php" class="role-chip">
                <div class="chip-icon" style="background: rgba(15,150,105,0.1); color: #0f9669;">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <span>Doctor Assist</span>
            </a>
        </div>

        <!-- Footer -->
        <div class="card-footer">
            Don't have an account? <a href="register.php">Register now</a><br />
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Store</a>
        </div>

        <!-- Trust Badges -->
        <div class="trust-row">
            <span><i class="fas fa-shield-alt"></i> Secure Login</span>
            <span><i class="fas fa-lock"></i> SSL Encrypted</span>
            <span><i class="fas fa-user-check"></i> Role-Protected</span>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const pw = document.getElementById('password');
            const ic = document.getElementById('pwIcon');
            if (pw.type === 'password') {
                pw.type = 'text';
                ic.className = 'fas fa-eye-slash';
            } else {
                pw.type = 'password';
                ic.className = 'fas fa-eye';
            }
        }

        // Show spinner on submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn    = document.getElementById('loginBtn');
            const icon   = document.getElementById('loginIcon');
            const spinner= document.getElementById('loginSpinner');
            const text   = document.getElementById('loginBtnText');
            btn.disabled = true;
            icon.style.display    = 'none';
            spinner.style.display = 'block';
            text.textContent      = 'Signing in…';
            // Re-enable after 8s as safety
            setTimeout(() => {
                btn.disabled = false;
                icon.style.display    = '';
                spinner.style.display = 'none';
                text.textContent      = 'Sign In';
            }, 8000);
        });
    </script>
</body>
</html>