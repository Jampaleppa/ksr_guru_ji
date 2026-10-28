<?php
// ============================================================
// doctor-register.php - Doctor Portal registration
// Replaces the old Patient Reg / Doctor Reg modal that used to
// live on the telehealth landing page. Submissions create a
// role='doctor' user + a doctors profile in PENDING state for
// KSR GURU JI licence verification.
// ============================================================
require_once 'helper.php';

$pdo     = getAssistDB();
$regMsg  = '';
$regOk   = false;
$regData = [
    'doc_name'       => '',
    'doc_email'      => '',
    'doc_specialty'  => '',
    'doc_reg_number' => '',
    'doc_authority'  => '',
    'doc_experience' => '',
    'doc_clinic'     => 'KSR Clinic',
    'doc_qualification' => '',
];

$specialties = [
    'General Physician',
    'Pediatrics',
    'Cardiology',
    'Geriatrics',
    'Neuro Care',
    'Orthopedics',
    'Dermatology',
    'DENTAL',
    'Ophthalmology',
    'Pulmonology',
    'Gastroenterology',
    'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_doctor'])) {
    foreach (array_keys($regData) as $k) {
        $regData[$k] = trim((string)($_POST[$k] ?? ''));
    }

    $password = (string)($_POST['doc_password'] ?? '');
    $confirm  = (string)($_POST['doc_password_confirm'] ?? '');

    $errors = [];

    if ($regData['doc_name'] === '') {
        $errors[] = 'Legal name is required.';
    }
    if (!filter_var($regData['doc_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if ($regData['doc_specialty'] === '') {
        $errors[] = 'Primary specialty is required.';
    }
    if ($regData['doc_reg_number'] === '') {
        $errors[] = 'Medical registration number is required.';
    }
    if ($regData['doc_authority'] === '') {
        $errors[] = 'Registration authority is required.';
    }
    if ($regData['doc_experience'] !== '' && !ctype_digit($regData['doc_experience'])) {
        $errors[] = 'Years of experience must be a number.';
    }

    try {
        if (!$errors) {
            $dupe = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $dupe->execute([$regData['doc_email']]);

            if ($dupe->fetch()) {
                $errors[] = 'An account with this email already exists. Please sign in instead.';
            }
        }

        $dupeReg = null;
        if (!$errors) {
            $dupeReg = $pdo->prepare("SELECT id FROM doctors WHERE registration_number = ? LIMIT 1");
            $dupeReg->execute([$regData['doc_reg_number']]);

            if ($dupeReg->fetch()) {
                $errors[] = 'This medical registration number is already registered on the platform.';
            }
        }

        if (!$errors) {
            $pdo->beginTransaction();

            $insUser = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'doctor')");
            $insUser->execute([$regData['doc_name'], $regData['doc_email'], password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)$pdo->lastInsertId();

            $insDoc = $pdo->prepare(
                "INSERT INTO doctors
                    (user_id, doctor_name, qualification, specialty, registration_number,
                     registration_authority, experience_years, clinic_hospital_details,
                     verification_status, uploaded_docs_json)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', '[]')"
            );
            $insDoc->execute([
                $userId,
                $regData['doc_name'],
                $regData['doc_qualification'],
                $regData['doc_specialty'],
                $regData['doc_reg_number'],
                $regData['doc_authority'],
                (int)($regData['doc_experience'] ?: 0),
                $regData['doc_clinic'],
            ]);

            $pdo->commit();

            logAuditEvent('DOCTOR_REGISTER', 'users', $userId, 'New doctor profile submitted for verification');

            $regOk  = true;
            $regMsg = 'Profile submitted successfully. Our clinical team will verify your medical licence before your workspace unlocks.';
        } else {
            $regMsg = implode(' ', $errors);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $regMsg = 'We could not submit your profile right now. Please try again shortly.';
        error_log('doctor-register: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Registration | KSR GURU JI</title>
    <meta name="description" content="Join the KSR GURU JI Doctor Assist platform as a verified practitioner.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php $ksr_depth = 0; include __DIR__ . '/partials/favicons.php'; ?>
    <link rel="stylesheet" href="portal-auth.css">
</head>

<body>
    <div class="auth-shell">

        <aside class="auth-aside" style="background: var(--grad-accent);">
            <a href="index.php" class="aa-logo">
                <span class="aa-logo-mark"><i class="fas fa-stethoscope"></i></span>
                <span>KSR GURU JI · Doctor Assist</span>
            </a>

            <div>
                <span class="aa-eyebrow"><i class="fas fa-clipboard-check"></i> Practitioner Onboarding</span>
                <h1 class="aa-title">Join a network built for modern clinical practice.</h1>
                <p class="aa-lead">
                    Submit your credentials once. Our clinical governance team verifies your medical
                    licence before your Doctor Portal workspace is activated.
                </p>

                <ul class="aa-features">
                    <li><i class="fas fa-user-check"></i> Licence &amp; registration verification</li>
                    <li><i class="fas fa-users"></i> Full patient history on every consult</li>
                    <li><i class="fas fa-file-prescription"></i> Digital prescription builder</li>
                    <li><i class="fas fa-chart-line"></i> Practice &amp; earnings analytics</li>
                </ul>

                <div class="aa-stats">
                    <div>
                        <div class="aa-stat-val">3 Steps</div>
                        <div class="aa-stat-lbl">Apply &middot; Verify &middot; Practice</div>
                    </div>
                    <div>
                        <div class="aa-stat-val">Free</div>
                        <div class="aa-stat-lbl">To Join Platform</div>
                    </div>
                </div>
            </div>

            <div style="font-size:0.8rem;color:rgba(255,255,255,0.6);">
                &copy; <?php echo date('Y'); ?> KSR GURU JI &middot; Doctor Assist Platform
            </div>
        </aside>

        <main class="auth-panel">
            <div class="auth-card" style="max-width:520px;">
                <div class="auth-head">
                    <div class="auth-badge accent"><i class="fas fa-user-md"></i></div>
                    <h1>Register as a Doctor</h1>
                    <p>Submit your profile for KSR GURU JI verification</p>
                </div>

                <div class="auth-form">
                    <?php if ($regMsg): ?>
                        <div class="alert alert-<?php echo $regOk ? 'success' : 'error'; ?>">
                            <i class="fas <?php echo $regOk ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                            <span><?php echo htmlspecialchars($regMsg); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$regOk): ?>
                        <form method="POST" action="" novalidate>
                            <input type="hidden" name="register_doctor" value="1">

                            <div class="af-grid">
                                <div class="af-group full">
                                    <label class="af-label" for="doc_name">Legal Name *</label>
                                    <div class="af-wrap">
                                        <i class="fas fa-user lead"></i>
                                        <input id="doc_name" class="af-input" name="doc_name" required
                                            value="<?php echo htmlspecialchars($regData['doc_name']); ?>"
                                            placeholder="Dr. Full Name">
                                    </div>
                                </div>

                                <div class="af-group full">
                                    <label class="af-label" for="doc_email">Email Address *</label>
                                    <div class="af-wrap">
                                        <i class="fas fa-envelope lead"></i>
                                        <input id="doc_email" class="af-input" type="email" name="doc_email" required
                                            value="<?php echo htmlspecialchars($regData['doc_email']); ?>"
                                            placeholder="doctor@example.com">
                                    </div>
                                </div>

                                <div class="af-group">
                                    <label class="af-label" for="doc_password">Password *</label>
                                    <div class="af-wrap">
                                        <i class="fas fa-lock lead"></i>
                                        <input id="doc_password" class="af-input" type="password" name="doc_password" required
                                            placeholder="Min. 8 characters">
                                    </div>
                                </div>

                                <div class="af-group">
                                    <label class="af-label" for="doc_password_confirm">Confirm Password *</label>
                                    <div class="af-wrap">
                                        <i class="fas fa-lock lead"></i>
                                        <input id="doc_password_confirm" class="af-input" type="password"
                                            name="doc_password_confirm" required placeholder="Repeat password">
                                    </div>
                                </div>

                                <div class="af-group full">
                                    <label class="af-label" for="doc_specialty">Primary Specialty *</label>
                                    <select id="doc_specialty" class="af-input no-icon" name="doc_specialty" required>
                                        <option value="">Select specialty</option>
                                        <?php foreach ($specialties as $sp): ?>
                                            <option value="<?php echo htmlspecialchars($sp); ?>"
                                                <?php echo $regData['doc_specialty'] === $sp ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sp); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="af-group full">
                                    <label class="af-label" for="doc_qualification">Qualification</label>
                                    <div class="af-wrap">
                                        <i class="fas fa-graduation-cap lead"></i>
                                        <input id="doc_qualification" class="af-input" name="doc_qualification"
                                            value="<?php echo htmlspecialchars($regData['doc_qualification']); ?>"
                                            placeholder="MBBS, MD (Cardiology)">
                                    </div>
                                </div>

                                <div class="af-group">
                                    <label class="af-label" for="doc_reg_number">Medical Reg. Number *</label>
                                    <div class="af-wrap">
                                        <i class="fas fa-certificate lead"></i>
                                        <input id="doc_reg_number" class="af-input" name="doc_reg_number" required
                                            value="<?php echo htmlspecialchars($regData['doc_reg_number']); ?>"
                                            placeholder="e.g. KMC-123456">
                                    </div>
                                </div>

                                <div class="af-group">
                                    <label class="af-label" for="doc_authority">Reg. Authority *</label>
                                    <div class="af-wrap">
                                        <i class="fas fa-landmark lead"></i>
                                        <input id="doc_authority" class="af-input" name="doc_authority" required
                                            value="<?php echo htmlspecialchars($regData['doc_authority']); ?>"
                                            placeholder="e.g. NMC / KMC">
                                    </div>
                                </div>

                                <div class="af-group">
                                    <label class="af-label" for="doc_experience">Years of Experience</label>
                                    <div class="af-wrap">
                                        <i class="fas fa-briefcase lead"></i>
                                        <input id="doc_experience" class="af-input" type="number" min="0" name="doc_experience"
                                            value="<?php echo htmlspecialchars($regData['doc_experience']); ?>"
                                            placeholder="e.g. 5">
                                    </div>
                                </div>

                                <div class="af-group">
                                    <label class="af-label" for="doc_clinic">Clinic / Hospital</label>
                                    <div class="af-wrap">
                                        <i class="fas fa-hospital lead"></i>
                                        <input id="doc_clinic" class="af-input" name="doc_clinic"
                                            value="<?php echo htmlspecialchars($regData['doc_clinic']); ?>"
                                            placeholder="Clinic name">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="af-submit accent" style="margin-top:8px;">
                                <i class="fas fa-clipboard-check"></i> Submit Profile for Verification
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="doctor-login.php" class="af-submit accent" style="text-decoration:none;">
                            <i class="fas fa-right-to-bracket"></i> Go to Doctor Login
                        </a>
                    <?php endif; ?>
                </div>

                <div class="af-foot">
                    Already registered? <a href="doctor-login.php">Sign in to your workspace</a>
                    <div><a class="af-back" href="index.php"><i class="fas fa-arrow-left"></i> Back to home</a></div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>