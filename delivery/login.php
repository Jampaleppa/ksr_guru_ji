<?php
// ============================================================
// delivery/login.php — agent sign-in (password only, 3 roster accounts)
// ============================================================
require_once __DIR__ . '/bootstrap.php';

if (delivery_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$redirect = (string) ($_GET['redirect'] ?? 'dashboard.php');
$roster   = DELIVERY_ROSTER;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f7b3a">
    <title>Delivery Login · KSR GURU JI</title>
    <link rel="icon" href="../company_logo.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="delivery-pages.css?v=1">
</head>

<body class="dp-auth">
    <div class="dp-auth-wrap">
        <div class="dp-auth-card">
            <img src="../company_logo.jpg" class="dp-auth-logo" alt="KSR GURU JI"
                onerror="this.style.display='none'">
            <h1>KSR GURU JI</h1>
            <p class="dp-auth-sub">Delivery Agent Portal</p>

            <form id="loginForm" novalidate>
                <label class="dp-field">
                    <span>Phone or Email</span>
                    <input type="text" id="identifier" name="identifier" inputmode="tel"
                        autocomplete="username" placeholder="8088584421" required>
                </label>
                <label class="dp-field">
                    <span>Password</span>
                    <input type="password" id="password" name="password"
                        autocomplete="current-password" placeholder="••••••" required>
                </label>
                <div class="dp-err" id="loginErr" hidden></div>
                <button type="submit" class="dp-btn dp-btn-primary dp-btn-block" id="loginBtn">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In
                </button>
            </form>

            <div class="dp-roster">
                <div class="dp-roster-h"><i class="fa-solid fa-users"></i> Registered Delivery Agents</div>
                <?php foreach ($roster as $b): ?>
                    <button type="button" class="dp-roster-i" data-phone="<?= e($b['phone']) ?>">
                        <span class="dp-av"><?= e(strtoupper(substr($b['name'], 0, 1))) ?></span>
                        <span class="dp-roster-n">
                            <b>Delivery Boy: <?= e($b['name']) ?></b>
                            <small><?= e($b['phone']) ?></small>
                        </span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                <?php endforeach; ?>
            </div>

            <a href="../index.php" class="dp-back"><i class="fa-solid fa-house"></i> Back to KSR GURU JI</a>
        </div>
    </div>
    <div class="dp-toast" id="dpToast"></div>
    <script>
        // Minimal toast (login page is standalone — no delivery-common.js needed)
        function dpToast(msg, tone) {
            let t = document.getElementById('dpToast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'dpToast';
                t.className = 'dp-toast';
                document.body.appendChild(t);
            }
            t.textContent = msg;
            t.className = 'dp-toast on ' + (tone || 'ok');
            clearTimeout(t._t);
            t._t = setTimeout(() => {
                t.className = 'dp-toast';
            }, 3400);
        }
        const REDIRECT = <?= json_encode($redirect) ?>;
        document.getElementById('loginForm').addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const btn = document.getElementById('loginBtn');
            const err = document.getElementById('loginErr');
            err.hidden = true;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Signing in…';
            try {
                const r = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        identifier: document.getElementById('identifier').value.trim(),
                        password: document.getElementById('password').value
                    })
                });
                const j = await r.json();
                if (j.success) {
                    dpToast('Welcome, ' + j.agent.name + '!', 'ok');
                    setTimeout(() => location.href = REDIRECT, 400);
                } else {
                    err.textContent = j.error || 'Login failed';
                    err.hidden = false;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> Sign In';
                }
            } catch (e) {
                err.textContent = 'Network error. Please try again.';
                err.hidden = false;
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> Sign In';
            }
        });
        document.querySelectorAll('.dp-roster-i').forEach(b => {
            b.addEventListener('click', () => {
                document.getElementById('identifier').value = b.dataset.phone;
                document.getElementById('password').focus();
            });
        });
    </script>
</body>

</html>