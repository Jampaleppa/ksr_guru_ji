<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$appConfig = require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Register - KSR GURU JI</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #00a884;
            --primary-dark: #008f6f;
            --primary-light: #e7f8f4;
            --bg-body: #ffffff;
            --bg-screen: #ffffff;
            --text-main: #111b21;
            --text-muted: #667781;
            --border: #e9edef;
            --danger: #dc2626;
            --danger-light: #fef2f2;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-body); margin: 0; padding: 0;
            min-height: 100vh; color: var(--text-main);
            overflow-x: hidden;
        }

        /* Progress Steps */
        .progress-header {
            background: linear-gradient(160deg, #00a884 0%, #008f6f 100%);
            padding: 20px 20px 30px;
            text-align: center; color: white;
        }
        .progress-header h2 { margin: 0 0 20px; font-size: 1.3rem; font-weight: 700; }
        .steps-row { display: flex; justify-content: center; align-items: center; gap: 0; }
        .step-dot {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; font-weight: 700;
            transition: all 0.3s;
            background: rgba(255,255,255,0.3); color: white;
        }
        .step-dot.active { background: white; color: var(--primary); transform: scale(1.15); }
        .step-dot.done { background: rgba(255,255,255,0.9); color: var(--primary); }
        .step-dot.done::after { content: '✓'; }
        .step-line { flex: 1; height: 2px; background: rgba(255,255,255,0.3); max-width: 60px; }
        .step-line.done { background: rgba(255,255,255,0.8); }
        .step-labels { display: flex; justify-content: center; gap: 55px; margin-top: 8px; }
        .step-labels span { font-size: 0.72rem; color: rgba(255,255,255,0.8); }

        /* Screens */
        .auth-screen { display: none; padding: 30px 24px; max-width: 420px; margin: 0 auto; }
        .auth-screen.active { display: block; }

        .screen-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 6px; color: var(--text-main); }
        .screen-sub { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 28px; line-height: 1.5; }

        /* Input Styles */
        .field-wrap { margin-bottom: 20px; }
        .field-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.3px; display: block; }
        .input-row {
            display: flex; align-items: center;
            border: 2px solid var(--border); border-radius: 14px;
            overflow: hidden; transition: border-color 0.2s;
        }
        .input-row:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,168,132,0.1); }
        .input-row.error-field { border-color: var(--danger); }
        .phone-prefix {
            padding: 14px 12px 14px 16px;
            font-size: 1rem; font-weight: 600;
            color: var(--text-main); background: #f8f9fa;
            border-right: 1px solid var(--border); white-space: nowrap;
        }
        .input-row input, .plain-input {
            flex: 1; border: none; outline: none;
            padding: 14px 16px; font-size: 1rem;
            color: var(--text-main); background: transparent; width: 100%;
        }
        .plain-input {
            border: 2px solid var(--border); border-radius: 14px;
            transition: border-color 0.2s;
        }
        .plain-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,168,132,0.1); outline: none; }
        .plain-input.error-field { border-color: var(--danger); }

        /* PIN field with eye */
        .pin-wrap {
            border: 2px solid var(--border); border-radius: 14px;
            display: flex; align-items: center; overflow: hidden; transition: border-color 0.2s;
        }
        .pin-wrap:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,168,132,0.1); }
        .pin-wrap.error-field { border-color: var(--danger); }
        .pin-wrap input { flex: 1; border: none; outline: none; padding: 14px 16px; font-size: 1rem; color: var(--text-main); background: transparent; letter-spacing: 4px; }
        .toggle-eye { padding: 14px 16px; cursor: pointer; color: var(--text-muted); font-size: 1.1rem; min-width: 44px; text-align: center; }

        .error-msg { display: none; color: var(--danger); font-size: 0.8rem; margin-top: 6px; padding: 6px 10px; background: var(--danger-light); border-radius: 8px; align-items: center; gap: 6px; }
        .error-msg.show { display: flex; }

        /* OTP input */
        .otp-input {
            width: 100%; border: 2px solid var(--border); border-radius: 14px;
            padding: 16px; font-size: 2rem; font-weight: 700;
            text-align: center; letter-spacing: 12px; outline: none;
            color: var(--text-main); transition: border-color 0.2s;
        }
        .otp-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,168,132,0.1); }
        .otp-hint { text-align: center; font-size: 0.85rem; color: var(--text-muted); margin-top: 10px; }
        .otp-hint b { color: var(--primary); }

        /* Avatar upload */
        .avatar-upload { text-align: center; margin-bottom: 24px; }
        .avatar-circle {
            width: 100px; height: 100px; border-radius: 50%;
            background: #e2e8f0; display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; color: #94a3b8; cursor: pointer;
            margin: 0 auto 10px; border: 3px dashed var(--border);
            transition: all 0.2s; position: relative;
        }
        .avatar-circle:hover { border-color: var(--primary); color: var(--primary); }
        .avatar-circle .cam-badge {
            position: absolute; bottom: 2px; right: 2px;
            width: 28px; height: 28px; border-radius: 50%;
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; border: 2px solid white;
        }
        .avatar-upload p { font-size: 0.85rem; color: var(--text-muted); margin: 0; }

        /* Terms checkbox */
        .terms-wrap { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 24px; padding: 16px; background: var(--primary-light); border-radius: 12px; }
        .terms-wrap input[type="checkbox"] { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--primary); flex-shrink: 0; cursor: pointer; }
        .terms-wrap label { font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; cursor: pointer; }
        .terms-wrap a { color: var(--primary); font-weight: 600; text-decoration: none; }

        /* Buttons */
        .btn-primary {
            width: 100%; padding: 15px; background: var(--primary);
            color: white; border: none; border-radius: 14px;
            font-size: 1.05rem; font-weight: 700; cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            display: flex; align-items: center; justify-content: center; gap: 10px; min-height: 52px;
        }
        .btn-primary:active { background: var(--primary-dark); transform: scale(0.98); }
        .btn-primary:disabled { background: #b2dfdb; cursor: not-allowed; }
        .btn-primary .spinner { display: none; width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.4); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; }
        .btn-primary.loading .btn-label { display: none; }
        .btn-primary.loading .spinner { display: block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .auth-links { margin-top: 20px; text-align: center; font-size: 0.9rem; color: var(--text-muted); }
        .auth-links a { color: var(--primary); font-weight: 700; text-decoration: none; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: var(--primary); font-size: 0.9rem; font-weight: 600; cursor: pointer; margin-bottom: 20px; text-decoration: none; }
    </style>
</head>
<body>

<!-- Progress Header -->
<div class="progress-header">
    <h2>Create Your Account</h2>
    <div class="steps-row">
        <div class="step-dot active" id="dot1">1</div>
        <div class="step-line" id="line1"></div>
        <div class="step-dot" id="dot2">2</div>
        <div class="step-line" id="line2"></div>
        <div class="step-dot" id="dot3">3</div>
    </div>
    <div class="step-labels">
        <span>Phone</span>
        <span>Verify</span>
        <span>Profile</span>
    </div>
</div>

<!-- STEP 1: Phone -->
<div class="auth-screen active" id="phoneScreen">
    <p class="screen-title">Enter your number</p>
    <p class="screen-sub">We'll send you a 6-digit code to verify your phone number.</p>

    <div class="field-wrap">
        <label class="field-label">Phone Number</label>
        <div class="input-row" id="phoneWrap">
            <span class="phone-prefix">🇮🇳 +91</span>
            <input type="tel" id="mobile" placeholder="98765 43210" inputmode="numeric" maxlength="10">
        </div>
        <div class="error-msg" id="phoneErr"><i class="fas fa-circle-xmark"></i> Please enter a valid 10-digit mobile number.</div>
    </div>

    <button class="btn-primary" onclick="requestOTP()">
        <span class="btn-label">Send Code <i class="fas fa-arrow-right"></i></span>
        <div class="spinner"></div>
    </button>
    <div class="auth-links">Already have an account? <a href="login.php">Login here</a></div>
</div>

<!-- STEP 2: OTP -->
<div class="auth-screen" id="otpScreen">
    <span class="back-link" onclick="switchScreen('phoneScreen')"><i class="fas fa-arrow-left"></i> Change number</span>
    <p class="screen-title">Verify your number</p>
    <p class="screen-sub" id="otpSubtitle">Enter the 6-digit code sent to your number.</p>

    <div class="field-wrap">
        <input type="text" class="otp-input" id="otpCode" placeholder="——————" maxlength="6" inputmode="numeric" autocomplete="one-time-code" oninput="onOtpInput(this)">
        <div class="error-msg" id="otpErr" style="margin-top:8px;"><i class="fas fa-circle-xmark"></i> Invalid code. Use <b>123456</b> for testing.</div>
        <div class="otp-hint">Test code: <b>123456</b></div>
    </div>

    <div style="text-align:center; margin-bottom:20px; color: var(--text-muted); font-size:0.85rem;">
        Didn't receive? <a id="resendLink" href="#" style="color:var(--primary); font-weight:600; text-decoration:none;" onclick="resendOTP(); return false;">Resend code</a>
        <span id="resendTimer" style="color:var(--text-muted);font-size:0.82rem;display:none;"> (<span id="resendCountdown">30</span>s)</span>
    </div>

    <button class="btn-primary" onclick="verifyOTP()">
        <span class="btn-label">Verify &amp; Continue</span>
        <div class="spinner"></div>
    </button>
</div>

<!-- STEP 3: Profile -->
<div class="auth-screen" id="profileScreen">
    <p class="screen-title">Set up your profile</p>
    <p class="screen-sub">Add your name and an optional profile photo. This is visible to other KSR users.</p>

    <div class="avatar-upload">
        <div class="avatar-circle" id="avatarCircle" role="button" tabindex="0" aria-label="Add profile photo" onclick="document.getElementById('avatarFileInput').click()" onkeydown="if(event.key==='Enter')this.click()">
            <i class="fas fa-user" id="avatarIcon"></i>
            <div class="cam-badge"><i class="fas fa-camera"></i></div>
        </div>
        <input type="file" id="avatarFileInput" accept="image/*" style="display:none;" aria-label="Upload profile photo">
        <p>Tap to add photo (optional)</p>
    </div>

    <div class="field-wrap">
        <label class="field-label" for="fullName">Full Name</label>
        <input type="text" class="plain-input" id="fullName" placeholder="Your name" autocomplete="name">
        <div class="error-msg" id="nameErr"><i class="fas fa-circle-xmark"></i> Please enter your name.</div>
    </div>

    <div class="field-wrap">
        <label class="field-label" for="aboutMe">About (optional)</label>
        <input type="text" class="plain-input" id="aboutMe" placeholder="Hey there! I am using KSR GURU JI." maxlength="80">
    </div>

    <div class="field-wrap">
        <label class="field-label" for="pinSet">Set Security PIN</label>
        <div class="pin-wrap" id="pinSetWrap">
            <input type="password" id="pinSet" placeholder="Choose a PIN" inputmode="numeric" maxlength="8">
            <span class="toggle-eye" onclick="togglePinSet()"><i class="far fa-eye" id="eyeIconSet"></i></span>
        </div>
        <div class="error-msg" id="pinSetErr"><i class="fas fa-circle-xmark"></i> Please set a security PIN (min. 4 digits).</div>
    </div>

    <div class="terms-wrap">
        <input type="checkbox" id="termsCheck">
        <label for="termsCheck">I agree to the <a href="../../terms.php">Terms of Service</a> and <a href="../../privacy.php">Privacy Policy</a> of KSR GURU JI.</label>
    </div>
    <div class="error-msg" id="termsErr" style="margin-top:-16px; margin-bottom:16px;"><i class="fas fa-circle-xmark"></i> Please accept the terms to continue.</div>

    <button class="btn-primary" id="regBtn" onclick="finishRegistration()">
        <span class="btn-label"><i class="fas fa-check-circle"></i> Complete Registration</span>
        <div class="spinner"></div>
    </button>
</div>

<script>
    // Avatar file picker with preview
    document.getElementById('avatarFileInput').addEventListener('change', function(e) {
        const file = e.target.files && e.target.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            const circle = document.getElementById('avatarCircle');
            const icon = document.getElementById('avatarIcon');
            if (icon) icon.style.display = 'none';
            let img = circle.querySelector('img');
            if (!img) {
                img = document.createElement('img');
                img.alt = 'Profile photo preview';
                img.style.cssText = 'width:100%;height:100%;border-radius:50%;object-fit:cover;position:absolute;top:0;left:0;';
                circle.style.position = 'relative';
                circle.insertBefore(img, circle.firstChild);
            }
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    // OTP auto-advance
    function onOtpInput(inp) {
        if (inp.value.length === 6) verifyOTP();
    }

    // Resend OTP with 30s cooldown
    let resendCooldown = null;
    function startResendCooldown() {
        const link = document.getElementById('resendLink');
        const timer = document.getElementById('resendTimer');
        const countdown = document.getElementById('resendCountdown');
        link.style.pointerEvents = 'none';
        link.style.opacity = '0.5';
        timer.style.display = 'inline';
        let secs = 30;
        countdown.textContent = secs;
        clearInterval(resendCooldown);
        resendCooldown = setInterval(function() {
            secs--;
            countdown.textContent = secs;
            if (secs <= 0) {
                clearInterval(resendCooldown);
                link.style.pointerEvents = '';
                link.style.opacity = '';
                timer.style.display = 'none';
            }
        }, 1000);
    }

    function resendOTP() {
        requestOTP();
        startResendCooldown();
    }

    function switchScreen(id) {
        document.querySelectorAll('.auth-screen').forEach(s => s.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        updateProgress(id);
    }

    function updateProgress(screenId) {
        const map = { phoneScreen: 1, otpScreen: 2, profileScreen: 3 };
        const step = map[screenId] || 1;
        [1,2,3].forEach(i => {
            const dot = document.getElementById('dot' + i);
            dot.classList.remove('active', 'done');
            if (i < step) dot.classList.add('done');
            else if (i === step) dot.classList.add('active');
        });
        [1,2].forEach(i => {
            const line = document.getElementById('line' + i);
            line.classList.toggle('done', i < step);
        });
    }

    function requestOTP() {
        const mobile = document.getElementById('mobile').value.trim();
        const wrap = document.getElementById('phoneWrap');
        const err = document.getElementById('phoneErr');
        wrap.classList.remove('error-field'); err.classList.remove('show');
        if (mobile.length < 10 || !/^\d{10}$/.test(mobile)) {
            wrap.classList.add('error-field'); err.classList.add('show'); return;
        }
        document.getElementById('otpSubtitle').innerHTML = 'Enter the 6-digit code sent to <b>+91 ' + mobile + '</b>';
        switchScreen('otpScreen');
        startResendCooldown();
    }

    function verifyOTP() {
        const code = document.getElementById('otpCode').value.trim();
        const err = document.getElementById('otpErr');
        err.classList.remove('show');
        if (code === '123456') {
            switchScreen('profileScreen');
        } else {
            err.classList.add('show');
        }
    }

    function togglePinSet() {
        const inp = document.getElementById('pinSet');
        const icon = document.getElementById('eyeIconSet');
        if (inp.type === 'password') { inp.type = 'text'; icon.className = 'far fa-eye-slash'; }
        else { inp.type = 'password'; icon.className = 'far fa-eye'; }
    }

    function finishRegistration() {
        const name = document.getElementById('fullName').value.trim();
        const pin = document.getElementById('pinSet').value.trim();
        const terms = document.getElementById('termsCheck').checked;
        const btn = document.getElementById('regBtn');

        document.getElementById('nameErr').classList.remove('show');
        document.getElementById('pinSetErr').classList.remove('show');
        document.getElementById('termsErr').classList.remove('show');
        document.getElementById('fullName').classList.remove('error-field');
        document.getElementById('pinSetWrap').classList.remove('error-field');

        let valid = true;
        if (!name) {
            document.getElementById('nameErr').classList.add('show');
            document.getElementById('fullName').classList.add('error-field');
            valid = false;
        }
        if (pin.length < 4) {
            document.getElementById('pinSetErr').classList.add('show');
            document.getElementById('pinSetWrap').classList.add('error-field');
            valid = false;
        }
        if (!terms) { document.getElementById('termsErr').classList.add('show'); valid = false; }
        if (!valid) return;

        btn.classList.add('loading'); btn.disabled = true;

        fetch('../api/auth/register.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'register',
                name: name,
                mobile: document.getElementById('mobile').value,
                pin: pin,
                about: document.getElementById('aboutMe').value
            }),
            headers: { 'Content-Type': 'application/json' }
        })
        .then(res => res.json())
        .then(() => { window.location.href = 'chats.php'; })
        .catch(() => { btn.classList.remove('loading'); btn.disabled = false; window.location.href = 'chats.php'; });
    }
</script>
</body>
</html>
