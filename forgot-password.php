<?php
// ============================================================
// forgot-password.php - Forgot Password Request and Reset Flow
// ============================================================
require_once 'config.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$user = null;

global $conn;

// Phase 2: Token is provided - Validate it
if (!empty($token)) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id, fullname, email FROM users WHERE reset_token = ? AND reset_expiry > NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Invalid or expired reset token. Please request a new link below.';
        $token = ''; // Clear token to fall back to request form
    }
}

// Phase 1: Request Reset Link (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && empty($token)) {
    $email = trim($_POST['email']);
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('SELECT id, fullname, email FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            // Generate token
            $resetToken = bin2hex(random_bytes(32));
            $resetExpiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

            $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?');
            if ($stmt->execute([$resetToken, $resetExpiry, $targetUser['id']])) {
                // Construct reset link
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
                $host = $_SERVER['HTTP_HOST'];
                $resetLink = $protocol . "://" . $host . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/forgot-password.php?token=" . $resetToken;

                $mailSubject = "Password Reset Link - KSR GURU JI";
                $mailMessage = "Hi " . htmlspecialchars($targetUser['fullname']) . ",\n\n" .
                    "We received a request to reset your password. Click the link below to set a new password:\n\n" .
                    $resetLink . "\n\n" .
                    "If you did not request this, please ignore this email.\n";

                // Log local mail for verification / fallback
                $logContent = "[" . date('Y-m-d H:i:s') . "] To: " . $email . "\nSubject: " . $mailSubject . "\nMessage:\n" . $mailMessage . "\n============================================\n\n";
                file_put_contents(__DIR__ . '/mail_log.txt', $logContent, FILE_APPEND);

                // Try to send via standard PHP mail
                @mail($email, $mailSubject, $mailMessage, "From: no-reply@ksrguruji.com\r\nReply-To: support@ksrguruji.com");

                $success = 'A password reset link has been sent to your email. (For local testing, check mail_log.txt in the project directory)';
            } else {
                $error = 'Database error. Please try again.';
            }
        } else {
            // Screen message is user-friendly but avoids email enumeration disclosures in production;
            // however, since it is a local portal, we can tell them if email doesn't exist.
            $error = 'No user account found with that email address.';
        }
    }
}

// Phase 2: Handle Password Update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['confirm_password']) && $user && !empty($token)) {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $pdo = getDBConnection();
        $stmt = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?');

        if ($stmt->execute([$hashed, $user['id']])) {
            $success = 'Your password has been reset successfully. You can now login.';
            $user = null;
            $token = ''; // Clear token to show request form or success page
        } else {
            $error = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes" />
    <title>Reset Password · KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <style>
        :root {
            --primary: #0F7B3A;
            --primary-dark: #0A5E2C;
            --primary-light: #E8F5E9;
            --primary-gradient: linear-gradient(135deg, #0F7B3A 0%, #1EA85A 100%);
            --secondary: #E85D2C;
            --secondary-gradient: linear-gradient(135deg, #E85D2C 0%, #F57C3A 100%);
            --bg-body: #F0F4F8;
            --white: #FFFFFF;
            --text-dark: #1A1F2E;
            --text-muted: #5A6A7E;
            --text-light: #8A9BB0;
            --shadow-lg: 0 24px 56px rgba(15, 123, 58, 0.18), 0 8px 20px rgba(0, 0, 0, 0.08);
            --border-radius: 20px;
            --transition: 0.30s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            --font-family: 'Inter', 'Poppins', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background: var(--bg-body);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 445px;
            width: 100%;
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2.5rem 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid #EEF2F6;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header .logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: var(--primary-gradient);
            border-radius: 30px;
            font-size: 2rem;
            color: var(--white);
            box-shadow: 0 4px 12px rgba(15, 123, 58, 0.25);
            margin-bottom: 0.8rem;
        }

        .header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .header p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 1.4rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 6px;
            color: var(--text-dark);
        }

        .form-group label i {
            color: var(--primary);
            margin-right: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1.2rem;
            border: 1.5px solid #E2E8F0;
            border-radius: 60px;
            font-size: 1rem;
            transition: var(--transition);
            font-family: var(--font-family);
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 123, 58, 0.12);
            outline: none;
        }

        .btn-submit {
            width: 100%;
            padding: 0.9rem;
            background: var(--secondary-gradient);
            color: var(--white);
            border: none;
            border-radius: 60px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 16px rgba(232, 93, 44, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(232, 93, 44, 0.4);
        }

        .btn-submit:active {
            transform: scale(0.97);
        }

        .message {
            padding: 0.8rem 1.2rem;
            border-radius: 60px;
            font-size: 0.88rem;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid;
            line-height: 1.4;
        }

        .message.success {
            background: #D1FAE5;
            color: #065F46;
            border-color: #A7F3D0;
        }

        .message.error {
            background: #FEE2E2;
            color: #DC2626;
            border-color: #FECACA;
        }

        .message i {
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .footer-links {
            text-align: center;
            margin-top: 1.8rem;
            font-size: 0.95rem;
            color: var(--text-muted);
        }

        .footer-links a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .back-home {
            display: inline-block;
            margin-top: 1.2rem;
            color: var(--text-light);
            font-size: 0.9rem;
            transition: var(--transition);
            text-decoration: none;
        }

        .back-home:hover {
            color: var(--primary);
        }

        .back-home i {
            margin-right: 6px;
        }

        @media (max-width: 480px) {
            html, body {
                overflow-x: hidden;
            }
            body {
                padding: 12px 8px;
            }
            .container {
                padding: 1.8rem 1.2rem;
                border-radius: 18px;
            }
            .header h1 {
                font-size: 1.4rem;
            }
            .form-group input {
                font-size: 16px;
            }
            .btn-submit {
                font-size: 1rem;
                padding: 0.85rem;
            }
        }
        @media (max-width: 360px) {
            .container {
                padding: 1.4rem 0.9rem;
            }
            .header h1 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="logo">🔑</div>
            <h1><?php echo (!empty($token) && $user) ? 'Set New Password' : 'Forgot Password'; ?></h1>
            <p><?php
            if (!empty($token) && $user) {
                echo 'Hi ' . htmlspecialchars($user['fullname']) . ', key in your new password below.';
            } else {
                echo 'Enter your email address to receive a password reset link.';
            }
            ?></p>
        </div>

        <?php if ($success): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?> <a href="login.php"
                        style="color:var(--primary);font-weight:600;text-decoration:underline;">Login now</a></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($token) && $user && !$success): ?>
            <!-- Phase 2 Form: Reset Password -->
            <form method="POST">
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> New Password <span
                            style="font-weight:400;color:var(--text-light);font-size:0.8rem;">(min 6 chars)</span></label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required />
                </div>
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required />
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Reset Password
                </button>
            </form>
        <?php elseif (empty($token) && !$success): ?>
            <!-- Phase 1 Form: Request Password Reset -->
            <form method="POST">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autofocus />
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-share-square"></i> Send Reset Link
                </button>
            </form>
        <?php endif; ?>

        <div class="footer-links">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            <br />
            <a href="index.php" class="back-home"><i class="fas fa-store"></i> Return to Store</a>
        </div>
    </div>
</body>

</html>