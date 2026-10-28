<?php
// ============================================================
// profile.php - User profile management
// ============================================================
require_once 'config.php';
require_once 'functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current user data
$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT id, fullname, email, phone, role FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];

    // Validate name
    if (empty($name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters.';
    }

    // Validate phone (optional)
    if (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = 'Phone number must be 10-15 digits.';
    }

    // Validate password change (optional)
    $updatePassword = false;
    if (!empty($password) || !empty($confirm_password)) {
        if (strlen($password) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }
        $updatePassword = true;
    }

    if (empty($errors)) {
        if ($updatePassword) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET fullname = ?, phone = ?, password = ? WHERE id = ?');
            $success = $stmt->execute([$name, $phone, $hashed, $userId]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET fullname = ?, phone = ? WHERE id = ?');
            $success = $stmt->execute([$name, $phone, $userId]);
        }

        if ($success) {
            $_SESSION['user_name'] = $name;
            $user['fullname'] = $name;
            $user['phone'] = $phone;
            $message = 'Profile updated successfully!';
        } else {
            $error = 'Database error. Please try again later.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes" />
    <title>My Profile · KSR GURU JI</title>
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
            --border-radius-sm: 14px;
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
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-container {
            max-width: 520px;
            width: 100%;
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2.5rem 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid #EEF2F6;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-header .avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 50%;
            font-size: 2.4rem;
            color: var(--white);
            box-shadow: 0 4px 16px rgba(15, 123, 58, 0.25);
            margin-bottom: 0.8rem;
        }

        .profile-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .profile-header .role-badge {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.2rem 1.2rem;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 4px;
        }

        .profile-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 1.2rem;
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

        .form-group label .optional {
            font-weight: 400;
            color: var(--text-light);
            font-size: 0.8rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1.2rem;
            border: 1.5px solid #E2E8F0;
            border-radius: 60px;
            font-size: 1rem;
            transition: var(--transition);
            font-family: var(--font-family);
            background: var(--white);
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(15, 123, 58, 0.12);
            outline: none;
        }

        .form-group input:disabled {
            background: #F1F5F9;
            color: var(--text-muted);
        }

        .message {
            padding: 0.8rem 1.2rem;
            border-radius: 60px;
            font-size: 0.9rem;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid;
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
        }

        .btn-save {
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
            margin-top: 0.5rem;
        }

        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(232, 93, 44, 0.4);
        }

        .btn-save:active {
            transform: scale(0.97);
        }

        .profile-actions {
            display: flex;
            gap: 12px;
            margin-top: 1.5rem;
        }

        .btn-logout {
            flex: 1;
            padding: 0.8rem;
            background: #EF4444;
            color: var(--white);
            border: none;
            border-radius: 60px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-logout:hover {
            background: #DC2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.3);
        }

        .btn-back {
            flex: 1;
            padding: 0.8rem;
            background: var(--primary-light);
            color: var(--primary);
            border: none;
            border-radius: 60px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-back:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
        }

        .divider {
            border-top: 1px solid #E2E8F0;
            margin: 1.5rem 0;
        }

        @media (max-width: 480px) {
            html, body {
                overflow-x: hidden;
            }
            body {
                padding: 12px 8px;
            }
            .profile-container {
                padding: 1.8rem 1.2rem;
                border-radius: 18px;
            }
            .profile-header h1 {
                font-size: 1.45rem;
            }
            .form-group input {
                font-size: 16px;
            }
            .profile-actions {
                flex-direction: column;
                gap: 8px;
            }
            .btn-save, .btn-logout, .btn-back {
                padding: 0.85rem;
                font-size: 0.95rem;
            }
        }
        @media (max-width: 360px) {
            .profile-container {
                padding: 1.4rem 0.9rem;
            }
            .profile-header h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>

<body>
    <div class="profile-container">
        <div class="profile-header">
            <div class="avatar"><i class="fas fa-user"></i></div>
            <h1><?php echo htmlspecialchars($user['fullname'] ?? 'User'); ?></h1>
            <span class="role-badge">
                <?php echo htmlspecialchars(ucfirst($user['role'] ?? 'customer')); ?>
            </span>
            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
        </div>

        <?php if ($message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name"><i class="fas fa-user"></i> Full Name</label>
                <input type="text" id="name" name="name"
                    value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required />
            </div>
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled />
                <small style="color:var(--text-light);font-size:0.75rem;">Email cannot be changed</small>
            </div>
            <div class="form-group">
                <label for="phone"><i class="fas fa-phone"></i> Phone Number <span
                        class="optional">(optional)</span></label>
                <input type="tel" id="phone" name="phone" placeholder="9876543210"
                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" />
            </div>

            <div class="divider"></div>
            <h4 style="margin-bottom:1rem;color:var(--text-dark);font-weight:600;">
                <i class="fas fa-key" style="color:var(--primary);"></i> Change Password
                <span style="font-weight:400;font-size:0.8rem;color:var(--text-light);">(leave blank to keep
                    current)</span>
            </h4>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> New Password <span class="optional">(min 6
                        chars)</span></label>
                <input type="password" id="password" name="password" placeholder="••••••••" />
            </div>
            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" />
            </div>

            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> Update Profile
            </button>
        </form>

        <div class="profile-actions">
            <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                <a href="admin/dashboard.php" class="btn-back" style="background:var(--secondary);color:white;"><i
                        class="fas fa-user-shield"></i> Admin Panel</a>
            <?php elseif (isset($user['role']) && $user['role'] === 'delivery'): ?>
                <a href="delivery/dashboard.php" class="btn-back" style="background:var(--secondary);color:white;"><i
                        class="fas fa-shipping-fast"></i> Delivery Panel</a>
            <?php endif; ?>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Store</a>
            <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</body>

</html>