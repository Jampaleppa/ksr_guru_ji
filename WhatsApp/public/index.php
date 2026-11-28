<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/app.php';

/**
 * ========================================================
 * KSR GURU JI v1.0 - Global Application Front-Controller
 * ========================================================
 * This engine intercepts all incoming HTTP Traffic and dynamically
 * maps it to the correct UI view or API endpoint cleanly.
 */

// 1. Identify the requested URI route path securely
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = basename($requestUri);

// 2. Map Clean Routes to physical files
switch ($route) {
    case 'login':
    case 'login.php':
        require __DIR__ . '/login.php';
        break;
        
    case 'register':
    case 'register.php':
        require __DIR__ . '/register.php';
        break;

    case 'chats':
    case 'chats.php':
        require __DIR__ . '/chats.php';
        break;

    case 'updates':
    case 'updates.php':
        require __DIR__ . '/updates.php';
        break;

    case 'calls':
    case 'calls.php':
        require __DIR__ . '/calls.php';
        break;

    case 'services':
    case 'services.php':
        require __DIR__ . '/services.php';
        break;

    case 'profile':
    case 'profile.php':
        require __DIR__ . '/profile.php';
        break;
        
    case 'admin':
        header('Location: ../admin/index.php');
        exit;

    case 'logout':
    case 'logout.php':
        session_destroy();
        header("Location: login.php");
        exit;

    // 3. Default Root - The Splash Screen
    case '':
    case '/':
    case 'index.php':
    default:
        $isLoggedIn = isset($_SESSION['user_id']);
        $redirectTarget = $isLoggedIn ? 'chats' : 'register';
        renderSplashScreen($redirectTarget);
        break;
}

/**
 * Renders a responsive mobile-first KSR splash screen.
 * Auto-redirects after 1200ms; Enter Now button skips the wait.
 */
function renderSplashScreen($redirectTarget) {
    $target = htmlspecialchars($redirectTarget, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #00a884; --primary-dark: #008f6f; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%; overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #fff;
        }
        body {
            display: flex; flex-direction: column;
            align-items: center; justify-content: space-between;
            padding: env(safe-area-inset-top, 20px) 24px
                     calc(env(safe-area-inset-bottom, 24px) + 24px) 24px;
        }
        .top-brand {
            align-self: flex-start;
            font-size: 0.82rem; font-weight: 700;
            color: var(--primary); opacity: 0.75;
            letter-spacing: 0.8px; text-transform: uppercase;
            padding-top: 8px;
        }
        .center-block {
            display: flex; flex-direction: column;
            align-items: center; gap: 0;
            flex: 1; justify-content: center;
        }
        .logo-ring {
            width: 96px; height: 96px;
            border-radius: 28px;
            background: linear-gradient(135deg, #00a884 0%, #25d366 100%);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 28px rgba(0,168,132,0.38);
            margin-bottom: 22px;
            animation: float 3s ease-in-out infinite;
        }
        .logo-ring i { font-size: 2.9rem; color: #fff; }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-7px); }
        }
        .brand-name {
            font-size: 1.75rem; font-weight: 800;
            color: #111b21; letter-spacing: -0.5px;
            margin-bottom: 8px; text-align: center;
        }
        .brand-tagline {
            font-size: 0.95rem; color: #667781;
            text-align: center; line-height: 1.4;
            max-width: 280px; margin-bottom: 32px;
        }
        .enter-btn {
            display: inline-flex; align-items: center; gap: 10px;
            background: var(--primary);
            color: #fff; border: none; border-radius: 999px;
            font-size: 1.05rem; font-weight: 700;
            padding: 15px 36px; cursor: pointer;
            box-shadow: 0 6px 20px rgba(0,168,132,0.32);
            text-decoration: none;
            transition: background 0.18s, transform 0.12s;
            min-height: 54px;
        }
        .enter-btn:active { background: var(--primary-dark); transform: scale(0.97); }
        .progress-bar-wrap {
            width: 160px; height: 3px;
            background: #e9edef; border-radius: 3px;
            overflow: hidden; margin-top: 22px;
        }
        .progress-bar-fill {
            height: 100%;
            width: 0%; background: var(--primary);
            border-radius: 3px;
            transition: width 0.1s linear;
        }
        .footer-line {
            font-size: 0.78rem; color: #8696a0;
            display: flex; align-items: center; gap: 5px;
        }
        .footer-line strong { color: var(--primary); font-weight: 700; }
    </style>
</head>
<body>
    <div class="top-brand">KSR GURU JI</div>

    <div class="center-block">
        <div class="logo-ring"><i class="fab fa-whatsapp"></i></div>
        <div class="brand-name">KSR GURU JI</div>
        <div class="brand-tagline">Fresh produce, Telehealth &amp; B2B supplies — all in one app 🌿</div>
        <a class="enter-btn" id="enterBtn" href="<?= $target ?>">
            Enter Now <i class="fas fa-arrow-right"></i>
        </a>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" id="progressFill"></div>
        </div>
    </div>

    <div class="footer-line">
        <i class="fas fa-infinity" style="font-size:0.9rem;"></i>
        from <strong>KSR TECHNOLOGIES</strong>
    </div>

    <script>
        (function() {
            var target = <?= json_encode($redirectTarget) ?>;
            var DURATION = 1200;
            var start = Date.now();
            var fill = document.getElementById('progressFill');
            var timer = setInterval(function() {
                var pct = Math.min(100, ((Date.now() - start) / DURATION) * 100);
                fill.style.width = pct + '%';
                if (pct >= 100) {
                    clearInterval(timer);
                    window.location.href = target;
                }
            }, 50);
            document.getElementById('enterBtn').addEventListener('click', function(e) {
                e.preventDefault();
                clearInterval(timer);
                fill.style.width = '100%';
                window.location.href = target;
            });
        })();
    </script>
</body>
</html>
<?php
}
?>
