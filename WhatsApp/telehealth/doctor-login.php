<?php
// ============================================================
// doctor-login.php - Dedicated Doctor Login Portal
// Sign in with registered email/username + password.
// Verification status (PENDING / VERIFIED / REJECTED / SUSPENDED)
// is enforced before the workspace is unlocked.
// ============================================================
require_once 'helper.php';
$pdo = getAssistDB();

// Already signed in as a doctor? Go straight to the workspace.
if (($_SESSION['user_role'] ?? '') === 'doctor' && !empty($_SESSION['user_id'])) {
    $existing = telehealthResolveDoctorId($pdo, (int)$_SESSION['user_id']);
    header('Location: doctor-dashboard.php');
    exit;
}

$loginMsg = '';
$loginSuccess = false;

// Live count shown in the brand panel
$verifiedDoctors = telehealthCount($pdo, "SELECT COUNT(*) FROM doctors WHERE verification_status = 'VERIFIED'");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_doctor'])) {
    try {
        $identifier = trim($_POST['login_identifier'] ?? '');
        $password   = (string)($_POST['login_password'] ?? '');

        if (!$identifier || !$password) {
            $loginMsg = 'Please enter both your registered email/username and password.';
        } else {
            // Accept registered email OR username (users.name)
            $stmt = $pdo->prepare(
                "SELECT * FROM users
                  WHERE role = 'doctor'
                    AND (email = ? OR name = ? OR phone = ?)
                  LIMIT 1"
            );
            $stmt->execute([$identifier, $identifier, $identifier]);
            $user = $stmt->fetch();

            if (!$user) {
                $loginMsg = 'Invalid doctor credentials or account not found.';
            } elseif (!password_verify($password, (string)$user['password'])) {
                $loginMsg = 'Invalid doctor credentials. Please check your password.';
            } else {
                $stmtD = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ? LIMIT 1");
                $stmtD->execute([$user['id']]);
                $doctor = $stmtD->fetch();

                if (!$doctor) {
                    $loginMsg = 'Doctor profile not found. Please register as a doctor first.';
                } elseif ($doctor['verification_status'] === 'PENDING') {
                    $loginMsg = 'Your practitioner profile is currently PENDING administrative license verification. Please check back later.';
                } elseif ($doctor['verification_status'] === 'REJECTED') {
                    $loginMsg = 'Your practitioner profile verification has been REJECTED. Please contact administrator support.';
                } elseif ($doctor['verification_status'] === 'SUSPENDED') {
                    $loginMsg = 'Your practitioner account is currently SUSPENDED. Please contact administrator support.';
                } else {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['simulated_role'] = 'doctor';
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $doctor['doctor_name'];
                    $_SESSION['user_role'] = 'doctor';
                    $_SESSION['doctor_id'] = (int)$doctor['id'];

                    logAuditEvent('DOCTOR_LOGIN', 'users', $user['id'], 'Doctor logged in successfully');

                    header('Location: doctor-dashboard.php');
                    exit;
                }
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
    <title>Doctor Login | KSR GURU JI Doctor Portal</title>
    <meta name="description" content="Secure sign-in for KSR GURU JI verified practitioners.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="portal-auth.css">
</head>

<body>
    <div class="auth-shell">

        <!-- Brand panel -->
        <aside class="auth-aside" style="background: var(--grad-accent);">
            <a href="index.php" class="aa-logo">
                <span class="aa-logo-mark"><i class="fas fa-stethoscope"></i></span>
                <span>KSR GURU JI · Doctor Assist</span>
            </a>

            <div>
                <span class="aa-eyebrow"><i class="fas fa-user-md"></i> Doctor Portal</span>
                <h1 class="aa-title">Your clinical workspace, unified.</h1>
                <p class="aa-lead">
                    Review full patient histories, run video &amp; audio consultations, issue
                    digitally signed prescriptions and track your practice — all from one console.
                </p>

                <ul class="aa-features">
                    <li><i class="fas fa-users"></i> Complete patient records &amp; vitals</li>
                    <li><i class="fas fa-video"></i> Video, audio &amp; chat consultations</li>
                    <li><i class="fas fa-file-prescription"></i> Digital prescription builder</li>
                    <li><i class="fas fa-indian-rupee-sign"></i> Earnings &amp; practice analytics</li>
                </ul>

                <div class="aa-stats">
                    <div>
                        <div class="aa-stat-val"><?php echo (int) $verifiedDoctors; ?></div>
                        <div class="aa-stat-lbl">Verified Practitioners</div>
                    </div>
                    <div>
                        <div class="aa-stat-val">NMC</div>
                        <div class="aa-stat-lbl">Licence Verified</div>
                    </div>
                    <div>
                        <div class="aa-stat-val">AES</div>
                        <div class="aa-stat-lbl">Encrypted Records</div>
                    </div>
                </div>
            </div>

            <div style="font-size:0.8rem;color:rgba(255,255,255,0.6);">
                &copy; <?php echo date('Y'); ?> KSR GURU JI &middot; Doctor Assist Platform
            </div>
        </aside>

        <!-- Form panel -->
        <main class="auth-panel">
            <div class="auth-card">
                <div class="auth-head">
                    <div class="auth-badge accent"><i class="fas fa-user-md"></i></div>
                    <h1>Doctor Sign In</h1>
                    <p>Secure login for clinical practitioners</p>
                </div>

                <div class="auth-form">
                    <?php if ($loginMsg): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-circle-exclamation"></i> <span><?php echo htmlspecialchars($loginMsg); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" autocomplete="off" novalidate>
                        <input type="hidden" name="login_doctor" value="1">

                        <div class="af-group">
                            <label class="af-label" for="login_identifier">Registered Email or Username</label>
                            <div class="af-wrap">
                                <i class="fas fa-id-badge lead"></i>
                                <input id="login_identifier" class="af-input" name="login_identifier" required
                                       placeholder="doctor@ksrguruji.com"
                                       autocomplete="username">
                            </div>
                        </div>

                        <div class="af-group">
                            <label class="af-label" for="login_password">Password</label>
                            <div class="af-wrap">
                                <i class="fas fa-lock lead"></i>
                                <input id="login_password" class="af-input" name="login_password" type="password"
                                       required placeholder="Enter your password" autocomplete="current-password">
                                <button type="button" class="af-toggle" data-toggle-password="login_password"
                                        aria-label="Show password"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>

                        <div class="af-row">
                            <label class="af-check">
                                <input type="checkbox" name="remember_me" value="1"> Keep me signed in
                            </label>
                            <a class="af-link" href="support-helpdesk.php">Need help?</a>
                        </div>

                        <button type="submit" class="af-submit accent">
                            <i class="fas fa-shield-halved"></i> Secure Login
                        </button>
                    </form>

                    <div class="af-hint">
                        <i class="fas fa-circle-info"></i>
                        <span>Accounts unlock only after KSR GURU JI completes medical licence verification.</span>
                    </div>
                </div>

                <div class="af-foot">
                    Not yet on the platform? <a href="doctor-register.php">Register as a doctor</a>
                    <div><a class="af-back" href="index.php"><i class="fas fa-arrow-left"></i> Return to Main Home</a></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.togglePassword);
                if (!input) return;

                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            });
        });
    </script>
</body>

</html>
