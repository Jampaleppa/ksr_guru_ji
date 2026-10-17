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
        :root { --primary: #00a884; --primary-dark: #008f6f; --bg-body: #ffffff; --text-main: #111b21; --text-muted: #667781; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg-body); margin: 0; padding: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; color: var(--text-main); }
        .auth-container { width: 100%; max-width: 400px; padding: 30px 20px; box-sizing: border-box; text-align: center; }
        .logo-title { color: var(--primary); font-size: 1.5rem; font-weight: 700; margin-bottom: 5px; }
        .subtitle { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 30px; }
        
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px; font-weight: 500; }
        .input-control { width: 100%; border: none; border-bottom: 2px solid var(--primary); padding: 10px 0; font-size: 1.1rem; outline: none; background: transparent; transition: border-color 0.3s; }
        .input-control:focus { border-bottom-color: var(--primary-dark); }
        
        .btn-primary { background: var(--primary); color: white; border: none; padding: 14px 24px; font-size: 1rem; font-weight: 600; border-radius: 50px; width: 100%; cursor: pointer; transition: background 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-primary:active { background: var(--primary-dark); }
        
        .auth-links { margin-top: 25px; font-size: 0.9rem; color: var(--text-muted); }
        .auth-links a { color: var(--primary); text-decoration: none; font-weight: 600; }

        /* OTP Screen Overlay */
        #otpScreen, #profileScreen { display: none; }
    </style>
</head>
<body>

<div class="auth-container" id="phoneScreen">
    <div class="logo-title">Welcome to KSR GURU JI</div>
    <div class="subtitle">Enter your phone number to register</div>
    
    <div class="input-group">
        <label>Phone Number</label>
        <div style="display:flex; align-items:flex-end;">
            <span style="font-size:1.1rem; border-bottom:2px solid var(--text-muted); padding:10px 10px 10px 0; color:var(--text-muted);">+91</span>
            <input type="tel" id="mobile" class="input-control" style="flex:1;" placeholder="00000 00000">
        </div>
    </div>
    
    <button class="btn-primary" onclick="requestOTP()">Next</button>
    <div class="auth-links">Already have an account? <a href="login.php">Login here</a></div>
</div>

<div class="auth-container" id="otpScreen">
    <div class="logo-title">Verifying number</div>
    <div class="subtitle" id="otpSubtitle">Waiting to automatically detect an SMS sent to...</div>
    
    <div class="input-group">
        <input type="text" id="otpCode" class="input-control" style="text-align:center; letter-spacing:8px; font-size:1.5rem;" placeholder="- - - - - -" maxlength="6">
    </div>
    <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:20px;">Enter code: <b style="color:var(--primary);">123456</b></div>
    
    <button class="btn-primary" onclick="verifyOTP()">Verify</button>
    <div class="auth-links" style="margin-top:20px;"><a href="#" onclick="switchScreen('phoneScreen')">Wrong number?</a></div>
</div>

<div class="auth-container" id="profileScreen">
    <div class="logo-title">Profile info</div>
    <div class="subtitle">Please provide your name and an optional profile photo</div>
    
    <div style="width:100px; height:100px; background:#e2e8f0; border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:2rem; cursor:pointer;">
        <i class="fas fa-camera"></i>
    </div>

    <div class="input-group">
        <input type="text" id="fullName" class="input-control" placeholder="Type your name here">
    </div>
    
    <button class="btn-primary" onclick="finishRegistration()">Complete Registration</button>
</div>

<script>
    function switchScreen(id) {
        document.getElementById('phoneScreen').style.display = 'none';
        document.getElementById('otpScreen').style.display = 'none';
        document.getElementById('profileScreen').style.display = 'none';
        document.getElementById(id).style.display = 'block';
    }

    function requestOTP() {
        const mobile = document.getElementById('mobile').value;
        if(mobile.length < 10) return alert('Enter valid phone number');
        document.getElementById('otpSubtitle').innerHTML = 'Waiting to automatically detect an SMS sent to<br><b>+91 ' + mobile + '</b>.';
        switchScreen('otpScreen');
    }

    function verifyOTP() {
        const code = document.getElementById('otpCode').value;
        if(code === '123456') {
            switchScreen('profileScreen');
        } else {
            alert('Invalid OTP. Use 123456 for testing.');
        }
    }

    function finishRegistration() {
        const name = document.getElementById('fullName').value;
        if(!name) return alert('Please enter your name');
        
        // Use correct relative path routing to the REST Controller endpoints
        fetch('../api/auth/register.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'register', name: name, mobile: document.getElementById('mobile').value })
        }).then(res => res.json()).then(data => {
            window.location.href = 'chats.php'; // Proceed to Chats
        });
    }
</script>
</body>
</html>
