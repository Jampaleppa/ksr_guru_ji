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
        :root { --primary: #00a884; --primary-dark: #008f6f; --bg-body: #ffffff; --text-muted: #8696a0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg-body); margin: 0; padding: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; }
        
        .header { margin-top: 50px; text-align: center; }
        .header h1 { color: var(--primary); font-size: 1.5rem; margin-bottom: 5px; }
        .header p { color: var(--text-muted); font-size: 0.95rem; margin-top: 0; padding: 0 20px; }
        
        .form-container { width: 100%; max-width: 400px; padding: 30px 20px; box-sizing: border-box; flex: 1; }
        
        .input-group { margin-bottom: 25px; position: relative; }
        .input-group input { width: 100%; border: none; border-bottom: 2px solid var(--primary); font-size: 1.1rem; padding: 10px 0; outline: none; background: transparent; transition: border-color 0.3s; color: #111b21; }
        .input-group input:focus { border-bottom-color: var(--primary-dark); }
        .input-group label { position: absolute; top: 12px; left: 0; color: var(--text-muted); font-size: 1.1rem; pointer-events: none; transition: 0.2s ease all; }
        
        .input-group input:focus ~ label, .input-group input:valid ~ label { top: -10px; font-size: 0.85rem; color: var(--primary); }
        
        .btn-primary { background: var(--primary); color: white; width: 100%; padding: 14px; border: none; border-radius: 24px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-primary:active { background: var(--primary-dark); transform: scale(0.98); }
        
        .links { text-align: center; margin-top: 20px; }
        .links a { color: var(--primary); text-decoration: none; font-weight: 600; font-size: 0.95rem; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Welcome Back</h1>
        <p>Enter your phone number or KSR ID to securely log in to KSR GURU JI.</p>
    </div>

    <div class="form-container">
        <div class="input-group">
            <input type="text" id="ksrId" required>
            <label>Mobile Number or @ksr_id</label>
        </div>
        
        <div class="input-group" style="margin-top: 30px;">
            <input type="password" id="password" required>
            <label>Security PIN</label>
        </div>
        
        <button class="btn-primary" onclick="processLogin()" style="margin-top: 20px;">Log In</button>
        
        <div class="links">
            <a href="register.php">Don't have an account? Register here</a>
        </div>
    </div>
    
    <script>
        function processLogin() {
            const ksridInput = document.getElementById('ksrId').value.trim();
            const passwordInput = document.getElementById('password').value.trim();
            
            if(!ksridInput || !passwordInput) {
                alert('Please enter your Identifier and PIN');
                return;
            }
            
            fetch('../api/auth/login.php', {
                method: 'POST',
                body: JSON.stringify({ identifier: ksridInput, pin: passwordInput }),
                headers: { 'Content-Type': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = 'chats.php';
                } else {
                    alert('Authentication Failed: ' + data.message);
                }
            })
            .catch(err => console.error(err));
        }
    </script>

</body>
</html>
