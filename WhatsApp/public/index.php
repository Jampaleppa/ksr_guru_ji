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
 * Safely renders the native WhatsApp-style Splash Screen directly.
 * Prevents HTML duplication in the Front Controller.
 */
function renderSplashScreen($redirectTarget) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KSR GURU JI</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg-color: #ffffff; --primary: #00a884; --text-muted: #8696a0; }
        body { margin: 0; padding: 0; display: flex; flex-direction: column; align-items: center; justify-content: space-between; height: 100vh; background: var(--bg-color); font-family: 'Inter', -apple-system, sans-serif; }
        .spacer { flex: 1; }
        .logo-container { flex: 2; display: flex; flex-direction: column; align-items: center; justify-content: center; animation: pulse 2s infinite ease-in-out; }
        .logo-box { width: 80px; height: 80px; background: var(--primary); border-radius: 20px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0, 168, 132, 0.4); margin-bottom: 20px; }
        .logo-box i { font-size: 2.5rem; color: white; }
        .brand-name { font-size: 1.5rem; font-weight: 700; color: #111b21; letter-spacing: 0.5px; }
        .footer-brand { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; padding-bottom: 40px; }
        .footer-text { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 5px; }
        .footer-highlight { font-size: 1.1rem; font-weight: 600; color: var(--primary); letter-spacing: 1px; display: flex; align-items: center; gap: 5px; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.02); } 100% { transform: scale(1); } }
        .spinner { width: 24px; height: 24px; border: 3px solid rgba(0, 168, 132, 0.3); border-radius: 50%; border-top-color: var(--primary); animation: spin 1s ease-in-out infinite; margin-top: 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="spacer"></div>
    <div class="logo-container">
        <div class="logo-box"><i class="fab fa-whatsapp"></i></div>
        <div class="brand-name">KSR GURU JI</div>
        <div class="spinner"></div>
    </div>
    <div class="footer-brand">
        <span class="footer-text">from</span>
        <span class="footer-highlight"><i class="fas fa-infinity" style="font-size:0.9rem;"></i> KSR TECHNOLOGIES</span>
    </div>
    <script>
        setTimeout(() => { window.location.href = '<?= $redirectTarget ?>'; }, 2500); 
    </script>
</body>
</html>
<?php
}
?>
