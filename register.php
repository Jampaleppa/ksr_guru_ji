<?php
// ============================================================
// register.php - Customer Registration · KSR GURU JI
// Uses PDO — consistent with platform architecture
// ============================================================
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to home
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';
$formData = ['name' => '', 'email' => '', 'phone' => ''];

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $formData = ['name' => $name, 'email' => $email, 'phone' => $phone];
    $errors   = [];

    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Full name is required (min 2 characters).';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = 'Phone number must be 10-15 digits.';
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $pdo = getDBConnection();

            // Check if email already exists
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'This email is already registered. <a href="login.php" style="color:#0F7B3A;font-weight:700;">Login instead</a>';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt   = $pdo->prepare('INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $email, $phone, $hashed, 'customer']);

                $userId = $pdo->lastInsertId();
                $_SESSION['user_id']    = $userId;
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role']  = 'customer';

                // Audit log
                try {
                    $logStmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, entity, details, created_at) VALUES (?, 'REGISTER', 'users', ?, NOW())");
                    $logStmt->execute([$userId, "New customer registered: $name"]);
                } catch (Exception $e) { /* ignore */ }

                header('Location: index.php?welcome=1');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Register DB error: ' . $e->getMessage());
            $errors[] = 'A system error occurred. Please try again later.';
        }
    }

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Account · KSR GURU JI</title>
    <meta name="description" content="Register for KSR GURU JI — your trusted daily essentials store." />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Poppins:wght@700;800&display=swap" rel="stylesheet" />
    <style>
        :root {
            --primary:      #0F7B3A;
            --primary-dark: #0A5E2C;
            --primary-light:#E8F5E9;
            --primary-grad: linear-gradient(135deg, #0F7B3A 0%, #1EA85A 100%);
            --accent:       #E85D2C;
            --accent-grad:  linear-gradient(135deg, #E85D2C 0%, #F57C3A 100%);
            --bg:           #EFF5F1;
            --surface:      #FFFFFF;
            --border:       #E2EBE6;
            --text-dark:    #1A2520;
            --text-mid:     #4A6655;
            --text-muted:   #7A9A8A;
            --radius:       24px;
            --shadow-lg:    0 24px 64px rgba(15,123,58,0.18), 0 8px 24px rgba(0,0,0,0.08);
            --transition:   0.3s cubic-bezier(0.25,0.46,0.45,0.94);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            background-image:
                radial-gradient(circle at 5% 10%, rgba(15,123,58,0.07) 0%, transparent 45%),
                radial-gradient(circle at 95% 90%, rgba(30,168,90,0.05) 0%, transparent 40%);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .card {
            width: 100%;
            max-width: 480px;
            background: var(--surface);
            border-radius: var(--radius);
            padding: 2.4rem 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            animation: slideUp 0.4s cubic-bezier(0.25,0.46,0.45,0.94);
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--primary-grad);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0);    }
        }

        .header {
            text-align: center;
            margin-bottom: 1.8rem;
        }

        .logo-ring {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 68px;
            height: 68px;
            background: var(--primary-grad);
            border-radius: 50%;
            box-shadow: 0 6px 20px rgba(15,123,58,0.28);
            margin-bottom: 1rem;
            border: 3px solid rgba(255,255,255,0.9);
            overflow: hidden;
        }

        .logo-ring img {
            width: 88%; height: 88%;
            object-fit: contain;
            border-radius: 50%;
        }

        .brand-name {
            font-family: 'Poppins', sans-serif;
            font-size: 1.7rem;
            font-weight: 800;
            background: var(--primary-grad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .brand-sub { font-size: 0.82rem; color: var(--text-muted); margin-top: 4px; }

        .title {
            margin-top: 0.8rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 0.85rem 1rem;
            border-radius: 14px;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 1.2rem;
            line-height: 1.5;
        }
        .alert-error   { background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert i       { font-size: 1.05rem; flex-shrink: 0; margin-top: 2px; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-group { margin-bottom: 1.1rem; }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 0.87rem;
            margin-bottom: 6px;
            color: var(--text-dark);
        }

        .form-group label i    { color: var(--primary); font-size: 0.82rem; }
        .form-group label small { color: var(--text-muted); font-weight: 400; }

        .input-wrap { position: relative; }

        .form-group input {
            width: 100%;
            padding: 0.82rem 1.05rem 0.82rem 2.6rem;
            border: 1.5px solid var(--border);
            border-radius: 50px;
            font-size: 0.95rem;
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

        .field-icon {
            position: absolute;
            left: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
            pointer-events: none;
        }

        .toggle-pw {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            background: none;
            border: none;
            font-size: 0.9rem;
            padding: 4px;
        }

        .toggle-pw:hover { color: var(--primary); }

        /* Password strength */
        .pw-strength { margin-top: 6px; display: flex; gap: 4px; }
        .pw-bar { height: 3px; flex: 1; border-radius: 3px; background: #e5e7eb; transition: 0.3s; }
        .pw-bar.weak   { background: #ef4444; }
        .pw-bar.medium { background: #f59e0b; }
        .pw-bar.strong { background: #10b981; }
        .pw-label { font-size: 0.7rem; color: var(--text-muted); margin-top: 3px; }

        .btn-register {
            width: 100%;
            padding: 0.92rem;
            background: var(--accent-grad);
            color: #fff;
            border: none;
            border-radius: 50px;
            font-size: 1.02rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 18px rgba(232,93,44,0.32);
            transition: var(--transition);
            margin-top: 0.5rem;
        }

        .btn-register:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(232,93,44,0.42); }
        .btn-register:active { transform: scale(0.97); }

        .footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 2;
        }

        .footer a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .footer a:hover { color: var(--accent); text-decoration: underline; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            color: var(--text-muted) !important;
            font-weight: 500 !important;
        }

        .trust-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 1.1rem;
            padding-top: 1.1rem;
            border-top: 1px solid var(--border);
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        .trust-row span { display: flex; align-items: center; gap: 4px; }
        .trust-row i    { color: var(--primary); }

        @media (max-width: 480px) {
            .card { padding: 2rem 1.4rem; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="card">
    <!-- Header -->
    <div class="header">
        <div class="logo-ring">
            <img src="company_logo.jpg" alt="KSR GURU JI"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
            <span style="display:none; font-size:2rem;">🛒</span>
        </div>
        <div class="brand-name">KSR GURU JI</div>
        <div class="brand-sub">Everything Your Family Needs, Every Day</div>
        <div class="title">Create your free account</div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?> <a href="login.php" style="color:#065F46;font-weight:700;">Login now</a></span>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" id="regForm" novalidate>

        <div class="form-group">
            <label for="name"><i class="fas fa-user"></i> Full Name</label>
            <div class="input-wrap">
                <input type="text" id="name" name="name" placeholder="Your full name"
                       required autocomplete="name"
                       value="<?php echo htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8'); ?>" />
                <i class="fas fa-user field-icon"></i>
            </div>
        </div>

        <div class="form-group">
            <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
            <div class="input-wrap">
                <input type="email" id="email" name="email" placeholder="you@example.com"
                       required autocomplete="email"
                       value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>" />
                <i class="fas fa-at field-icon"></i>
            </div>
        </div>

        <div class="form-group">
            <label for="phone">
                <i class="fas fa-phone"></i> Phone Number
                <small>(optional)</small>
            </label>
            <div class="input-wrap">
                <input type="tel" id="phone" name="phone" placeholder="9876543210"
                       autocomplete="tel"
                       value="<?php echo htmlspecialchars($formData['phone'], ENT_QUOTES, 'UTF-8'); ?>" />
                <i class="fas fa-mobile-alt field-icon"></i>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password"
                           placeholder="••••••••" required autocomplete="new-password"
                           oninput="checkStrength(this.value)" />
                    <i class="fas fa-lock field-icon"></i>
                    <button type="button" class="toggle-pw" onclick="togglePw('password','pwIcon')">
                        <i class="fas fa-eye" id="pwIcon"></i>
                    </button>
                </div>
                <div class="pw-strength">
                    <div class="pw-bar" id="bar1"></div>
                    <div class="pw-bar" id="bar2"></div>
                    <div class="pw-bar" id="bar3"></div>
                </div>
                <div class="pw-label" id="pwLabel">Enter at least 6 characters</div>
            </div>

            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm Password</label>
                <div class="input-wrap">
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="••••••••" required autocomplete="new-password" />
                    <i class="fas fa-shield-alt field-icon"></i>
                    <button type="button" class="toggle-pw" onclick="togglePw('confirm_password','cpIcon')">
                        <i class="fas fa-eye" id="cpIcon"></i>
                    </button>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-register">
            <i class="fas fa-user-plus"></i> Create Account — It's Free!
        </button>
    </form>

    <!-- Footer -->
    <div class="footer">
        Already have an account? <a href="login.php">Sign in</a><br />
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Store</a>
    </div>

    <!-- Trust -->
    <div class="trust-row">
        <span><i class="fas fa-shield-alt"></i> Secure</span>
        <span><i class="fas fa-lock"></i> Encrypted</span>
        <span><i class="fas fa-user-check"></i> No Spam</span>
        <span><i class="fas fa-infinity"></i> Free Forever</span>
    </div>
</div>

<script>
function togglePw(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ic  = document.getElementById(iconId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ic.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

function checkStrength(val) {
    const b1 = document.getElementById('bar1');
    const b2 = document.getElementById('bar2');
    const b3 = document.getElementById('bar3');
    const lb = document.getElementById('pwLabel');
    const len = val.length;
    const hasUpper  = /[A-Z]/.test(val);
    const hasNumber = /[0-9]/.test(val);
    const hasSpecial= /[^a-zA-Z0-9]/.test(val);

    [b1,b2,b3].forEach(b => b.className = 'pw-bar');

    if (len === 0) {
        lb.textContent = 'Enter at least 6 characters';
        lb.style.color = '#94a3b8';
        return;
    }
    if (len < 6) {
        b1.classList.add('weak');
        lb.textContent = 'Too short';
        lb.style.color = '#ef4444';
    } else if ((hasUpper || hasNumber) && !hasSpecial && len < 10) {
        b1.classList.add('medium'); b2.classList.add('medium');
        lb.textContent = 'Medium strength';
        lb.style.color = '#f59e0b';
    } else if (len >= 10 && (hasUpper && hasNumber && hasSpecial)) {
        b1.classList.add('strong'); b2.classList.add('strong'); b3.classList.add('strong');
        lb.textContent = 'Strong password ✓';
        lb.style.color = '#10b981';
    } else {
        b1.classList.add('medium'); b2.classList.add('medium');
        lb.textContent = 'Moderate — add numbers/symbols';
        lb.style.color = '#f59e0b';
    }
}
</script>
</body>
</html>