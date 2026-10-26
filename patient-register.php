<?php
// ============================================================
// patient-register.php - Patient Portal Registration
// Step 1: details + CAPTCHA + Terms consent
// Step 2 handled by patient-verify.php (WhatsApp OTP)
// ============================================================
require_once __DIR__ . '/patient-auth.php';

if (pp_is_logged_in()) {
    pp_redirect(pp_dashboard_url());
}

$errors = [];
$old = [
    'full_name' => '',
    'dob' => '',
    'gender' => 'Male',
    'mobile' => '',
    'email' => '',
    'address' => '',
    'emergency_contact' => '',
];

if (pp_is_post()) {
    foreach ($old as $k => $_) {
        $old[$k] = pp_post($k);
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');
    $agree    = isset($_POST['agree_terms']);
    $capToken = pp_post('captcha_token');
    $capInput = pp_post('captcha_answer');

    // --- validation ---
    if (mb_strlen($old['full_name']) < 3) {
        $errors[] = 'Please enter your full name.';
    }
    if ($old['dob'] === '' || !strtotime($old['dob'])) {
        $errors[] = 'Please enter a valid date of birth.';
    } elseif ((int)((time() - strtotime($old['dob'])) / 31557600) < 0 || (int)((time() - strtotime($old['dob'])) / 31557600) > 120) {
        $errors[] = 'Please enter a valid date of birth (age must be under 120).';
    }
    if (!in_array($old['gender'], ['Male', 'Female', 'Other'], true)) {
        $old['gender'] = 'Male';
    }

    $mobileDigits = preg_replace('/\D+/', '', $old['mobile']);
    if (strlen($mobileDigits) < 10 || strlen($mobileDigits) > 15) {
        $errors[] = 'Please enter a valid mobile number (10-15 digits).';
    } else {
        $old['mobile'] = $mobileDigits;
    }

    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must contain both letters and numbers.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirmation do not match.';
    }
    if (mb_strlen($old['address']) < 5) {
        $errors[] = 'Please enter your address.';
    }
    if (mb_strlen($old['emergency_contact']) < 3) {
        $errors[] = 'Please enter an emergency contact.';
    }
    if (!$agree) {
        $errors[] = 'You must agree to the Terms &amp; Privacy Policy.';
    }

    if (!pp_captcha_verify($capToken, $capInput)) {
        $errors[] = 'Incorrect CAPTCHA answer. Please try again.';
    }

    if (pp_is_rate_limited('register:' . pp_ip(), PATIENT_MAX_REGISTER_FAILS)) {
        $errors[] = 'Too many attempts. Please try again in ' . ceil(pp_rate_limit_wait() / 60) . ' minute(s).';
    }

    // --- uniqueness ---
    if (!$errors) {
        $uCols = pp_columns('users');
        $hasMobile = in_array('mobile', $uCols, true);
        $hasPhone = in_array('phone', $uCols, true);
        $whereParts = [];
        $params = [];
        if ($hasMobile) {
            $whereParts[] = "mobile = ?";
            $params[] = $old['mobile'];
        }
        if ($hasPhone) {
            $whereParts[] = "phone = ?";
            $params[] = $old['mobile'];
        }
        // Always check the exact email value that will be inserted
        $effectiveEmail = $old['email'] !== '' ? $old['email'] : ($old['mobile'] . '@patient.local');
        $whereParts[] = "email = ?";
        $params[] = $effectiveEmail;
        // Also check the synthetic fallback separately if a real email was given
        if ($old['email'] !== '') {
            $whereParts[] = "email = ?";
            $params[] = $old['mobile'] . '@patient.local';
        }

        $chk = pp_db()->prepare("SELECT id FROM users WHERE (" . implode(' OR ', $whereParts) . ") LIMIT 1");
        $chk->execute($params);
        if ($chk->fetch()) {
            $errors[] = 'An account with this mobile number or email already exists. Please log in.';
        }
    }

    if (!$errors) {
        try {
            pp_db()->beginTransaction();

            $uCols = pp_columns('users');
            $hasMobile = in_array('mobile', $uCols, true);
            $hasPhone = in_array('phone', $uCols, true);
            $hasAddress = in_array('address', $uCols, true);
            $hasStatus = in_array('account_status', $uCols, true);

            $insertCols = ['name', 'email', 'password', 'role'];
            $insertVals = [
                $old['full_name'],
                $old['email'] !== '' ? $old['email'] : ($old['mobile'] . '@patient.local'),
                password_hash($password, PASSWORD_DEFAULT),
                'customer',
            ];

            if ($hasPhone) {
                $insertCols[] = 'phone';
                $insertVals[] = $old['mobile'];
            }
            if ($hasMobile) {
                $insertCols[] = 'mobile';
                $insertVals[] = $old['mobile'];
            }
            if ($hasAddress) {
                $insertCols[] = 'address';
                $insertVals[] = $old['address'];
            }
            if ($hasStatus) {
                $insertCols[] = 'account_status';
                $insertVals[] = 'PENDING_VERIFICATION';
            }

            $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
            $sql = "INSERT INTO users (" . implode(', ', $insertCols) . ") VALUES ($placeholders)";
            $ins = pp_db()->prepare($sql);
            $ins->execute($insertVals);
            $userId = (int)pp_db()->lastInsertId();

            if (!pp_table_exists('patients')) {
                if (pp_driver() === 'sqlite') {
                    pp_db()->exec("CREATE TABLE IF NOT EXISTS patients (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER,
                        patient_name TEXT NOT NULL DEFAULT '',
                        date_of_birth TEXT,
                        gender TEXT,
                        mobile_number TEXT,
                        address TEXT,
                        emergency_contact TEXT,
                        created_at TEXT DEFAULT (datetime('now', 'localtime'))
                    )");
                } else {
                    pp_db()->exec("CREATE TABLE IF NOT EXISTS `patients` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `user_id` int(11) NOT NULL,
                        `patient_name` varchar(150) NOT NULL,
                        `date_of_birth` date DEFAULT NULL,
                        `gender` ENUM('Male','Female','Other') DEFAULT 'Male',
                        `mobile_number` varchar(20) DEFAULT NULL,
                        `address` text DEFAULT NULL,
                        `emergency_contact` varchar(150) DEFAULT NULL,
                        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `idx_patient_user` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }
            }

            $pCols = pp_columns('patients');
            $pInsertCols = ['user_id'];
            $pInsertVals = [$userId];

            if (in_array('patient_name', $pCols, true)) {
                $pInsertCols[] = 'patient_name';
                $pInsertVals[] = $old['full_name'];
            }
            if (in_array('name', $pCols, true)) {
                $pInsertCols[] = 'name';
                $pInsertVals[] = $old['full_name'];
            }
            if (in_array('date_of_birth', $pCols, true)) {
                $pInsertCols[] = 'date_of_birth';
                $pInsertVals[] = !empty($old['dob']) ? $old['dob'] : null;
            }
            if (in_array('dob', $pCols, true)) {
                $pInsertCols[] = 'dob';
                $pInsertVals[] = !empty($old['dob']) ? $old['dob'] : null;
            }
            if (in_array('gender', $pCols, true)) {
                $pInsertCols[] = 'gender';
                $pInsertVals[] = $old['gender'] ?: 'Male';
            }
            if (in_array('mobile_number', $pCols, true)) {
                $pInsertCols[] = 'mobile_number';
                $pInsertVals[] = $old['mobile'];
            }
            if (in_array('mobile', $pCols, true)) {
                $pInsertCols[] = 'mobile';
                $pInsertVals[] = $old['mobile'];
            }
            if (in_array('phone', $pCols, true) && !in_array('mobile', $pCols, true)) {
                $pInsertCols[] = 'phone';
                $pInsertVals[] = $old['mobile'];
            }
            if (in_array('address', $pCols, true)) {
                $pInsertCols[] = 'address';
                $pInsertVals[] = $old['address'];
            }
            if (in_array('emergency_contact', $pCols, true)) {
                $pInsertCols[] = 'emergency_contact';
                $pInsertVals[] = $old['emergency_contact'];
            }

            $pPlaceholders = implode(', ', array_fill(0, count($pInsertCols), '?'));
            $insP = pp_db()->prepare("INSERT INTO patients (" . implode(', ', $pInsertCols) . ") VALUES ($pPlaceholders)");
            $insP->execute($pInsertVals);

            pp_db()->commit();

            // ── WhatsApp registration success message ──────────────────────────────
            // Non-blocking: WhatsApp failure must never break the registration flow.
            try {
                $waMobile = preg_replace('/\D+/', '', $old['mobile']);
                if (strlen($waMobile) === 10) { $waMobile = '91' . $waMobile; }
                if ($waMobile !== '') {
                    require_once __DIR__ . '/admin/inc/whatsapp/WhatsAppFactory.php';
                    $wa = WhatsAppFactory::make(pp_db());
                    $wa->sendMessage($waMobile,
                        "🎉 *Welcome to KSR GURU JI!*\n\n" .
                        "Hello *{$old['full_name']}*,\n" .
                        "Your patient account has been created successfully.\n\n" .
                        "🛡️ Patient Portal: https://ksrguruji.com/patient-login.php\n" .
                        "📞 Support: 6362079192\n\n" .
                        "Please verify your mobile number to activate your account."
                    );
                }
            } catch (Throwable $waEx) {
                error_log('[patient-register WhatsApp] ' . $waEx->getMessage());
            }

            pp_clear_failures('register:' . pp_ip());
            $_SESSION['pending_verify_user'] = $userId;
            pp_redirect('patient-verify.php?purpose=registration');
        } catch (Throwable $ex) {
            if (pp_db()->inTransaction()) {
                pp_db()->rollBack();
            }
            $msg = $ex->getMessage();
            if (stripos($msg, 'duplicate') !== false || stripos($msg, 'unique') !== false) {
                $errors[] = 'An account with this mobile number or email already exists. Please log in.';
            } else {
                $errors[] = 'Registration could not be saved. Please try again.';
                error_log('patient-register error: ' . $msg);
            }
        }
    }
    pp_log_attempt('register:' . pp_ip(), empty($errors));
}

[$captchaToken, $captchaQuestion] = pp_captcha_create();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration | KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #0F9D76;
            --brand-dark: #0B7D5E;
            --border: #E2E8F0;
            --muted: #64748B;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', Segoe UI, system-ui, sans-serif;
            background: #F1F5F9;
            color: #0F172A;
            display: flex;
            justify-content: center;
            padding: 32px 16px 60px;
        }

        .card {
            width: 100%;
            max-width: 560px;
            background: #fff;
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 18px 50px -22px rgba(15, 23, 42, .35);
            border: 1px solid var(--border);
        }

        .brand {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand i {
            font-size: 2.4rem;
            color: var(--brand);
        }

        .brand h1 {
            font-size: 1.4rem;
            margin: 8px 0 4px;
            font-weight: 800;
        }

        .brand p {
            color: var(--muted);
            font-size: .9rem;
            margin: 0;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .full {
            grid-column: 1 / -1;
        }

        .f {
            margin-bottom: 16px;
        }

        .f label {
            display: block;
            font-size: .82rem;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .f input,
        .f select,
        .f textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: .94rem;
            font-family: inherit;
            background: #FBFCFD;
        }

        .f input:focus,
        .f select:focus,
        .f textarea:focus {
            outline: 2px solid rgba(15, 157, 118, .35);
            border-color: var(--brand);
        }

        .captcha-box {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .captcha-q {
            flex: 0 0 auto;
            background: #0F172A;
            color: #fff;
            font-weight: 800;
            padding: 11px 16px;
            border-radius: 10px;
            letter-spacing: 2px;
            font-size: 1.05rem;
            user-select: all;
        }

        .btn {
            width: 100%;
            background: var(--brand);
            color: #fff;
            border: 0;
            padding: 14px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
        }

        .btn:hover {
            background: var(--brand-dark);
        }

        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .alert-error {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }

        .alert ul {
            margin: 6px 0 0 18px;
            padding: 0;
        }

        .terms {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            font-size: .86rem;
            color: var(--muted);
            margin: 6px 0 20px;
        }

        .terms a {
            color: var(--brand);
            font-weight: 700;
            text-decoration: none;
        }

        .foot {
            text-align: center;
            margin-top: 20px;
            font-size: .9rem;
            color: var(--muted);
        }

        .foot a {
            color: var(--brand);
            font-weight: 700;
            text-decoration: none;
        }

        @media(max-width:520px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="brand">
            <i class="fas fa-user-plus"></i>
            <h1>Create Patient Account</h1>
            <p>KSR GURU JI &middot; Patient Portal</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> Please fix the following:
                <ul><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" novalidate>
            <input type="hidden" name="captcha_token" value="<?= pp_e($captchaToken) ?>">

            <div class="grid">
                <div class="f full">
                    <label for="full_name">Full Name *</label>
                    <input id="full_name" name="full_name" required value="<?= pp_e($old['full_name']) ?>" placeholder="e.g. Jampaleppa Rathod">
                </div>
                <div class="f">
                    <label for="dob">Date of Birth *</label>
                    <input id="dob" name="dob" type="date" required value="<?= pp_e($old['dob']) ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="f">
                    <label for="gender">Gender *</label>
                    <select id="gender" name="gender" required>
                        <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                            <option value="<?= $g ?>" <?= $old['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="f">
                    <label for="mobile">Mobile Number * (OTP sent here)</label>
                    <input id="mobile" name="mobile" inputmode="numeric" required value="<?= pp_e($old['mobile']) ?>" placeholder="10-15 digits">
                </div>
                <div class="f">
                    <label for="email">Email (optional)</label>
                    <input id="email" name="email" type="email" value="<?= pp_e($old['email']) ?>" placeholder="name@example.com">
                </div>
                <div class="f">
                    <label for="password">Password *</label>
                    <input id="password" name="password" type="password" required placeholder="Min 8 chars, letters + numbers">
                </div>
                <div class="f">
                    <label for="confirm_password">Confirm Password *</label>
                    <input id="confirm_password" name="confirm_password" type="password" required placeholder="Re-enter password">
                </div>
                <div class="f full">
                    <label for="address">Address *</label>
                    <textarea id="address" name="address" rows="2" required placeholder="House / Street / City / PIN"><?= pp_e($old['address']) ?></textarea>
                </div>
                <div class="f full">
                    <label for="emergency_contact">Emergency Contact * (name + number)</label>
                    <input id="emergency_contact" name="emergency_contact" required value="<?= pp_e($old['emergency_contact']) ?>" placeholder="e.g. Spouse 9876543210">
                </div>
                <div class="f full">
                    <label for="captcha_answer">CAPTCHA / Anti-bot Verification *</label>
                    <div class="captcha-box">
                        <span class="captcha-q"><?= pp_e($captchaQuestion) ?> = ?</span>
                        <input id="captcha_answer" name="captcha_answer" inputmode="numeric" required placeholder="Your answer">
                    </div>
                </div>
            </div>

            <label class="terms">
                <input type="checkbox" name="agree_terms" value="1" required>
                <span>I agree to the <a href="#" onclick="return false;">Terms &amp; Conditions</a> and
                    <a href="#" onclick="return false;">Privacy Policy</a> of KSR GURU JI.</span>
            </label>

            <button type="submit" class="btn"><i class="fas fa-user-check"></i> Register &amp; Verify Mobile</button>
        </form>

        <div class="foot">Already registered? <a href="patient-login.php">Sign in</a> &middot;
            <a href="index.php">Back to home</a>
        </div>
    </div>
</body>

</html>