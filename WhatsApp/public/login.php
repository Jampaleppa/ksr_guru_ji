<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - KSR GURU JI</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #00a884;
            --primary-dark: #008f6f;
            --primary-light: #e7f8f4;
            --bg-body: #f0f2f5;
            --bg-screen: #ffffff;
            --text-main: #111b21;
            --text-muted: #667781;
            --border: #e9edef;
            --danger: #dc2626;
            --danger-light: #fef2f2;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-body);
            margin: 0; padding: 0;
            min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            overflow-x: hidden;
        }
        .login-wrapper {
            width: 100%; max-width: 420px;
            min-height: 100vh;
            background: var(--bg-screen);
            display: flex; flex-direction: column; align-items: center;
            padding: 0 0 40px 0;
        }
        /* Green wave top banner */
        .top-banner {
            width: 100%;
            background: linear-gradient(160deg, #00a884 0%, #008f6f 100%);
            padding: 50px 20px 60px;
            text-align: center;
            border-radius: 0 0 50% 50% / 0 0 30px 30px;
            margin-bottom: 10px;
            position: relative;
        }
        .logo-circle {
            width: 90px; height: 90px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            border: 3px solid rgba(255,255,255,0.4);
            backdrop-filter: blur(4px);
        }
        .logo-circle i { font-size: 2.4rem; color: white; }
        .top-banner h1 { color: white; font-size: 1.7rem; font-weight: 800; margin: 0 0 6px; letter-spacing: -0.5px; }
        .top-banner p { color: rgba(255,255,255,0.85); font-size: 0.9rem; margin: 0; }

        .form-area { width: 100%; padding: 30px 28px 10px; }

        /* Phone field with +91 prefix */
        .field-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; letter-spacing: 0.3px; text-transform: uppercase; }
        .input-row {
            display: flex; align-items: center;
            border: 2px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            transition: border-color 0.2s;
            background: var(--bg-screen);
        }
        .input-row:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,168,132,0.12); }
        .input-row.error-field { border-color: var(--danger); }
        .phone-prefix {
            padding: 14px 12px 14px 16px;
            font-size: 1rem; font-weight: 600;
            color: var(--text-main);
            background: #f8f9fa;
            border-right: 1px solid var(--border);
            white-space: nowrap;
            user-select: none;
        }
        .input-row input {
            flex: 1; border: none; outline: none;
            padding: 14px 16px;
            font-size: 1rem; color: var(--text-main);
            background: transparent;
            min-width: 0;
        }
        .input-row input::placeholder { color: #b0bec5; }

        /* PIN field standalone */
        .pin-wrap {
            border: 2px solid var(--border);
            border-radius: 14px;
            display: flex; align-items: center;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .pin-wrap:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,168,132,0.12); }
        .pin-wrap.error-field { border-color: var(--danger); }
        .pin-wrap input {
            flex: 1; border: none; outline: none;
            padding: 14px 16px;
            font-size: 1rem; color: var(--text-main);
            background: transparent;
            letter-spacing: 4px;
        }
        .toggle-eye {
            padding: 14px 16px; cursor: pointer;
            color: var(--text-muted); font-size: 1.1rem;
            transition: color 0.2s; min-width: 44px; text-align: center;
        }
        .toggle-eye:hover { color: var(--primary); }

        .field-wrap { margin-bottom: 20px; }
        .error-msg {
            display: none;
            color: var(--danger);
            font-size: 0.8rem;
            margin-top: 6px;
            padding: 6px 10px;
            background: var(--danger-light);
            border-radius: 8px;
            align-items: center; gap: 6px;
        }
        .error-msg.show { display: flex; }

        /* Forgot PIN */
        .forgot-row { text-align: right; margin-bottom: 24px; margin-top: -8px; }
        .forgot-row a { color: var(--primary); font-size: 0.85rem; text-decoration: none; font-weight: 600; }

        /* Remember me */
        .remember-row { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
        .remember-row input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }
        .remember-row label { font-size: 0.9rem; color: var(--text-muted); cursor: pointer; }

        /* Submit button */
        .btn-login {
            width: 100%; padding: 15px;
            background: var(--primary);
            color: white; border: none;
            border-radius: 14px;
            font-size: 1.05rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.3px;
            transition: background 0.2s, transform 0.1s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            min-height: 52px;
        }
        .btn-login:active { background: var(--primary-dark); transform: scale(0.98); }
        .btn-login:disabled { background: #b2dfdb; cursor: not-allowed; transform: none; }
        .btn-login .spinner { display: none; width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.4); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; }
        .btn-login.loading .btn-label { display: none; }
        .btn-login.loading .spinner { display: block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .global-error {
            display: none; margin-bottom: 16px;
            background: var(--danger-light); border: 1px solid #fca5a5;
            border-radius: 12px; padding: 12px 16px;
            color: var(--danger); font-size: 0.9rem;
            align-items: center; gap: 10px;
        }
        .global-error.show { display: flex; }
        .global-error i { font-size: 1rem; flex-shrink: 0; }

        .divider { text-align: center; color: var(--text-muted); font-size: 0.85rem; margin: 20px 0; position: relative; }
        .divider::before, .divider::after { content: ''; position: absolute; top: 50%; width: 42%; height: 1px; background: var(--border); }
        .divider::before { left: 0; }
        .divider::after { right: 0; }

        .register-link { text-align: center; font-size: 0.95rem; color: var(--text-muted); }
        .register-link a { color: var(--primary); font-weight: 700; text-decoration: none; }
        .version-tag { text-align: center; color: #ccc; font-size: 0.75rem; margin-top: 24px; }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="top-banner">
        <div class="logo-circle">
            <i class="fas fa-comment-dots"></i>
        </div>
        <h1>KSR GURU JI</h1>
        <p>Your trusted community platform</p>
    </div>

    <div class="form-area">
        <div class="global-error" id="globalErr">
            <i class="fas fa-exclamation-circle"></i>
            <span id="globalErrMsg">Authentication failed. Please check your credentials.</span>
        </div>

        <!-- Phone number -->
        <div class="field-wrap">
            <div class="field-label">Mobile Number or @KSR ID</div>
            <div class="input-row" id="identifierWrap">
                <span class="phone-prefix" id="prefixDisplay">+91</span>
                <input type="text" id="ksrId" placeholder="98765 43210 or @ksr_id" inputmode="tel" autocomplete="tel" oninput="togglePrefix(this.value)">
            </div>
            <div class="error-msg" id="identifierErr"><i class="fas fa-circle-xmark"></i> <span>Please enter your mobile number or @KSR ID.</span></div>
        </div>

        <!-- PIN -->
        <div class="field-wrap">
            <div class="field-label">Security PIN</div>
            <div class="pin-wrap" id="pinWrap">
                <input type="password" id="password" placeholder="Enter your PIN" inputmode="numeric" maxlength="8" autocomplete="current-password">
                <span class="toggle-eye" id="eyeBtn" onclick="togglePin()"><i class="far fa-eye" id="eyeIcon"></i></span>
            </div>
            <div class="error-msg" id="pinErr"><i class="fas fa-circle-xmark"></i> <span>Please enter your security PIN.</span></div>
        </div>

        <!-- Forgot PIN -->
        <div class="forgot-row">
            <a href="../../forgot-password.php">Forgot PIN?</a>
            &nbsp;&middot;&nbsp;
            <a href="https://wa.me/918088584421" target="_blank" rel="noopener">WhatsApp Support</a>
        </div>

        <!-- Remember me -->
        <div class="remember-row">
            <input type="checkbox" id="rememberMe">
            <label for="rememberMe">Keep me signed in</label>
        </div>

        <button class="btn-login" id="loginBtn" onclick="processLogin()">
            <span class="btn-label">Log In</span>
            <div class="spinner"></div>
        </button>

        <div class="divider">or</div>
        <div class="register-link">Don't have an account? <a href="register.php">Register here</a></div>
        <div class="register-link" style="margin-top:8px;"><a href="../../index.php">← Back to KSR Store</a></div>
        <div class="version-tag">KSR GURU JI v3.0 &bull; Secure Login</div>
    </div>
</div>

<script>
    function togglePrefix(val) {
        const prefix = document.getElementById('prefixDisplay');
        const inp = document.getElementById('ksrId');
        if (val.startsWith('@')) {
            prefix.textContent = '@';
            inp.inputMode = 'text';
        } else {
            prefix.textContent = '+91';
            inp.inputMode = 'tel';
        }
    }

    function togglePin() {
        const inp = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'far fa-eye-slash';
        } else {
            inp.type = 'password';
            icon.className = 'far fa-eye';
        }
    }

    function showFieldError(wrapId, errId, show) {
        document.getElementById(wrapId).classList.toggle('error-field', show);
        document.getElementById(errId).classList.toggle('show', show);
    }

    function processLogin() {
        const ksridInput = document.getElementById('ksrId').value.trim();
        const passwordInput = document.getElementById('password').value.trim();
        const btn = document.getElementById('loginBtn');
        const globalErr = document.getElementById('globalErr');

        // Clear errors
        showFieldError('identifierWrap', 'identifierErr', false);
        showFieldError('pinWrap', 'pinErr', false);
        globalErr.classList.remove('show');

        let valid = true;
        if (!ksridInput) { showFieldError('identifierWrap', 'identifierErr', true); valid = false; }
        if (!passwordInput) { showFieldError('pinWrap', 'pinErr', true); valid = false; }
        if (!valid) return;

        // Loading state
        btn.classList.add('loading');
        btn.disabled = true;

        fetch('../api/auth/login.php', {
            method: 'POST',
            body: JSON.stringify({ identifier: ksridInput, pin: passwordInput }),
            headers: { 'Content-Type': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            btn.classList.remove('loading');
            btn.disabled = false;
            if (data.status === 'success') {
                window.location.href = 'chats.php';
            } else {
                document.getElementById('globalErrMsg').textContent = data.message || 'Authentication failed. Please try again.';
                globalErr.classList.add('show');
            }
        })
        .catch(() => {
            btn.classList.remove('loading');
            btn.disabled = false;
            document.getElementById('globalErrMsg').textContent = 'Network error. Please check your connection.';
            globalErr.classList.add('show');
        });
    }

    // Allow Enter key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') processLogin();
    });
</script>
</body>
</html>
