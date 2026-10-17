<?php
// ============================================================
// patient-login.php - MOVED
// The full Patient Portal login now lives at /patient-login.php
// (password + WhatsApp OTP login, remember me, forgot password).
// This shim keeps old links working.
// ============================================================
// URL of this page is /WhatsApp/public/telehealth/patient-login.php, so the
// project root is 3 levels up. (The real login lives at /patient-login.php.)
header('Location: ../../../patient-login.php');
exit;

/* ---- legacy implementation below (kept for reference) ----
require_once 'helper.php';
$pdo = getAssistDB();
$loginMsg = '';
$loginSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_patient'])) {
    try {
        $email = trim($_POST['login_email'] ?? '');
        $password = trim($_POST['login_password'] ?? '');

        if (!$email || !$password) {
            $loginMsg = 'Please enter both email and password.';
        } else {
            // Patient user should have role = 'user' or 'admin' or 'patient'
            // For now, allow regular 'user' role.
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role IN ('user', 'admin')");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (session_status() === PHP_SESSION_NONE) { session_start(); }
                $_SESSION['simulated_role'] = 'patient';
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];

                logAuditEvent('PATIENT_LOGIN', 'users', $user['id'], "Patient logged in successfully");

                // Route admin to master dashboard alternatively, or keep them as patient in this flow
                if ($user['role'] === 'admin') {
                    header("Location: admin-dashboard.php");
                } else {
                    header("Location: patient-dashboard.php");
                }
                exit;
            } else {
                $loginMsg = 'Invalid patient credentials or account not found.';
            }
        }
    } catch (PDOException $e) {
        $loginMsg = 'Database connection error. Please try again.';
        error_log($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login | Doctor Assist Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: 'Inter', sans-serif; }
        .login-card { background: white; padding: 40px; border-radius: var(--radius-lg); box-shadow: 0 10px 40px -10px rgba(0,0,0,0.1); width: 100%; max-width: 450px; border: 1px solid var(--clr-border); }
        .brand { text-align: center; margin-bottom: 32px; }
        .brand i { font-size: 2.5rem; color: #10B981; margin-bottom: 12px; }
        .brand h2 { font-size: 1.5rem; font-weight: 800; color: var(--clr-primary-dark); margin: 0; }
        .brand p { color: var(--clr-text-secondary); font-size: 0.9rem; margin-top: 4px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--clr-text-secondary); margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--clr-border); border-radius: var(--radius-md); font-family: inherit; font-size: 0.95rem; }
        .btn-submit { width: 100%; background: #10B981; color: white; padding: 14px; border: none; border-radius: var(--radius-md); font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.3s; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover { background: #059669; transform: translateY(-2px); }
        .alert { padding: 12px; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .alert-error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="login-card">
    <div class="brand">
        <i class="fas fa-user-circle"></i>
        <h2>Patient Portal</h2>
        <p>Login to book and manage appointments</p>
    </div>

    <?php if ($loginMsg): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($loginMsg); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="login_patient" value="1">
        <div class="form-group">
            <label>Registered Email</label>
            <input type="email" name="login_email" required placeholder="name@example.com">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="login_password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn-submit"><i class="fas fa-unlock"></i> Patient Sign In</button>
    </form>

    <div style="text-align:center; margin-top: 24px;">
        <a href="index.php" style="color:var(--clr-text-secondary); text-decoration:none; font-size:0.9rem; font-weight:600;"><i class="fas fa-arrow-left"></i> Return to Main Home</a>
    </div>
    </div>

</body>
</html>
*/
