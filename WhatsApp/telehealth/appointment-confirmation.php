<?php
require_once 'helper.php';

// Generate simulated data from POST
$docId = $_POST['doc_id'] ?? 1;
$consultType = $_POST['type'] ?? 'video';
$appDate = $_POST['date'] ?? 'September 6, 2026';
$appTime = $_POST['time'] ?? '02:30 PM';
$totalFee = (float)($_POST['amount'] ?? 140);
$patientName = $_POST['patient_name'] ?? 'Jampaleppa';
$paymentMethod = $_POST['payment_method'] ?? 'UPI';
$txnId = $_POST['txn_id'] ?? ('TXN' . rand(1000000000, 9999999999));
$appointId = 'DA-2026-' . rand(1000000, 9999999);

$isCod = (stripos($paymentMethod, 'Cash') !== false || stripos($paymentMethod, 'COD') !== false);

$pdo = getAssistDB();

// Fetch Doctor Info
$doctor = null;
try {
    $stmt = $pdo->prepare("SELECT doctor_name FROM doctors WHERE id = ?");
    $stmt->execute([$docId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

if (!$doctor) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $docNameFallback = $_SESSION['user_name'] ?? 'DR. D C Rathod';
    $doctor = ['doctor_name' => $docNameFallback];
}

$patientMobile = !empty($_POST['patient_mobile']) ? trim((string)$_POST['patient_mobile']) : (!empty($_SESSION['user_mobile']) ? $_SESSION['user_mobile'] : '6362079192');

// ── WhatsApp appointment confirmation ─────────────────────────────────
// Non-blocking: WhatsApp failure must never break the confirmation display.
try {
    $waMobile = preg_replace('/\D+/', '', (string)$patientMobile);
    if (strlen($waMobile) === 10) {
        $waMobile = '91' . $waMobile;
    }
    if ($waMobile !== '') {
        $waFactoryPath = dirname(__DIR__, 2) . '/admin/inc/whatsapp/WhatsAppFactory.php';
        if (file_exists($waFactoryPath)) {
            require_once $waFactoryPath;
            $wa = WhatsAppFactory::make($pdo);
            $docName = $doctor['doctor_name'] ?? 'KSR Doctor';
            $wa->sendMessage(
                $waMobile,
                "🗓️ *Appointment Confirmed – KSR GURU JI*\n\n" .
                "Hello *{$patientName}*,\n" .
                "Your appointment has been successfully scheduled.\n\n" .
                "🆔 *Appointment ID:* {$appointId}\n" .
                "👨‍⚕️ *Doctor:* {$docName}\n" .
                "🩺 *Consultation:* " . ucfirst($consultType) . "\n" .
                "📅 *Date:* {$appDate}\n" .
                "🕐 *Time:* {$appTime}\n" .
                "💳 *Payment:* " . ($isCod ? 'Pay on Visit / COD' : 'Paid via UPI (₹' . number_format($totalFee, 2) . ')') . "\n\n" .
                "📍 Manage appointments: https://ksrgurujiupload.vercel.app/WhatsApp/telehealth/my-appointments.php\n" .
                "📞 Support: 6362079192"
            );
        }
    }
} catch (Throwable $waEx) {
    error_log('[appointment-confirmation WhatsApp] ' . $waEx->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed | KSR GURU JI Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; }
        .success-container { max-width: 540px; margin: 40px auto; padding: 0 16px; margin-bottom: 80px; }

        .success-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); padding: 40px 32px; text-align: center; margin-bottom: 24px; }
        
        .check-circle { width: 72px; height: 72px; border: 3px solid #16A34A; border-radius: 50%; color: #16A34A; font-size: 2.2rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; background:#F0FDF4; }
        
        .s-title { font-size: 1.6rem; font-weight: 800; color: var(--clr-text-primary); margin-bottom: 6px; }
        .s-amount { font-size: 1.15rem; color: var(--clr-text-secondary); margin-bottom: 24px; font-weight: 600; }
        
        .s-data { text-align: left; background: #F8FAFC; padding: 16px; border-radius: var(--radius-sm); border: 1px solid var(--clr-border); margin-bottom: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media(max-width:480px) { .s-data { grid-template-columns: 1fr; } }
        .s-data-label { font-size: 0.8rem; color: var(--clr-text-secondary); font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
        .s-data-val { font-size: 1rem; font-family: monospace; font-weight: 700; color: var(--clr-text-primary); word-break: break-all; }

        .s-badge { background: #DCFCE7; color: #166534; font-weight: 800; font-size: 1.05rem; padding: 12px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; gap: 8px; }

        .info-card { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); padding: 28px 24px; box-shadow: var(--shadow-sm); margin-bottom: 24px; }
        .info-title { font-size: 1rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 20px; border-bottom: 1px solid var(--clr-border); padding-bottom: 12px; }
        
        .info-row { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; font-size: 1rem; color: var(--clr-text-primary); font-weight: 600; }
        .info-row i { color: var(--clr-primary); width: 24px; text-align: center; font-size: 1.15rem; }
        
        .app-id-box { background: var(--clr-primary-light); border: 1px dashed var(--clr-primary); padding: 14px; border-radius: var(--radius-sm); margin-top: 20px; }
        .app-id-label { font-size: 0.8rem; color: var(--clr-primary-dark); font-weight: 700; margin-bottom: 2px; }
        .app-id-val { font-size: 1.2rem; font-family: monospace; font-weight: 800; color: var(--clr-primary); letter-spacing: 1.5px; }

        .action-flex { display: flex; flex-direction: column; gap: 12px; }
        .btn-action { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 16px; border-radius: var(--radius-md); font-weight: 700; font-size: 1.05rem; cursor: pointer; text-decoration: none; transition: 0.2s; border: 1px solid var(--clr-border); background: white; color: var(--clr-text-primary); }
        .btn-action:hover { background: #F8FAFC; }
        .btn-action.primary { background: var(--clr-primary); color: white; border-color: var(--clr-primary); }
        .btn-action.primary:hover { background: var(--clr-primary-dark); }
        
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>

<nav class="navbar" style="background: white; border-bottom:1px solid #E2E8F0;">
    <div class="container" style="display:flex; align-items:center; gap:12px;">
        <img src="QR.png" alt="Logo" style="height:36px; width:36px; object-fit:contain; border-radius:6px;" onerror="this.style.display='none';">
        <a href="index.php" class="navbar-brand" style="font-weight:800; color:#0F172A;"><i class="fas fa-heartbeat" style="color:#2563EB;"></i> KSR GURU JI DOCTOR ASSIST</a>
    </div>
</nav>

<div class="success-container">
    
    <!-- 7. Payment Success Page Block -->
    <div class="success-card">
        <div class="check-circle"><i class="fas fa-check"></i></div>
        <div class="s-title"><?php echo $isCod ? 'Appointment Booked!' : 'Payment & Booking Confirmed!'; ?></div>
        <div class="s-amount">₹<?php echo number_format($totalFee, 2); ?> &bull; <?php echo htmlspecialchars($paymentMethod); ?></div>
        
        <div class="s-data">
            <div>
                <div class="s-data-label">Method</div>
                <div class="s-data-val"><?php echo $isCod ? 'Cash & Delivery' : 'UPI Payment'; ?></div>
            </div>
            <div>
                <div class="s-data-label"><?php echo $isCod ? 'Booking Ref' : 'Transaction ID'; ?></div>
                <div class="s-data-val"><?php echo htmlspecialchars($txnId); ?></div>
            </div>
        </div>

        <div class="s-badge">
            <i class="fas fa-calendar-check"></i> Appointment Confirmed
        </div>
    </div>

    <!-- Appointment Information Block -->
    <div class="info-card">
        <div class="info-title">Appointment Details</div>
        
        <div class="info-row"><i class="fas fa-user-md"></i> <span>👨‍⚕️ <?php echo htmlspecialchars($doctor['doctor_name']); ?></span></div>
        <div class="info-row"><i class="fas <?php echo $consultType === 'audio' ? 'fa-headphones-alt' : 'fa-video'; ?>"></i> <span><?php echo ucfirst($consultType); ?> Consultation</span></div>
        <div class="info-row"><i class="fas fa-calendar-day"></i> <span><?php echo htmlspecialchars($appDate); ?></span></div>
        <div class="info-row"><i class="fas fa-clock"></i> <span><?php echo htmlspecialchars($appTime); ?></span></div>
        <div class="info-row"><i class="fas fa-user-injured"></i> <span>Patient: <?php echo htmlspecialchars($patientName); ?></span></div>

        <div class="app-id-box">
            <div class="app-id-label">Appointment ID</div>
            <div class="app-id-val"><?php echo $appointId; ?></div>
        </div>
    </div>

    <!-- Actions -->
    <div class="action-flex">
        <a href="#" onclick="generateInvoice()" class="btn-action primary">
            <i class="fas fa-file-invoice"></i> View Invoice
        </a>
        <a href="my-appointments.php" class="btn-action">
            <i class="fas fa-calendar-alt"></i> My Appointments
        </a>
        <a href="index.php" class="btn-action">
            <i class="fas fa-home"></i> Return to Home
        </a>
    </div>

</div>

<!-- Hidden Invoice Template -->
<div id="invoice-template" style="display:none; padding: 40px; background: white; font-family: monospace; max-width: 600px; border: 1px solid #ccc;">
    <h2 style="margin:0 0 10px 0;">KSR GURU JI DOCTOR ASSIST</h2>
    <p style="margin:0 0 15px 0;">Telehealth & Consultation Service</p>
    <div style="border-bottom:1px dashed #000; margin-bottom:20px;"></div>
    
    <p>Invoice Number: INV-<?php echo rand(1000, 9999); ?></p>
    <p>Invoice Date: <?php echo date("d M Y"); ?></p>
    <br>
    <p>Patient: <?php echo htmlspecialchars($patientName); ?></p>
    <p>Doctor: <?php echo htmlspecialchars($doctor['doctor_name']); ?></p>
    <p>Appointment ID: <?php echo $appointId; ?></p>
    <p>Consultation Type: <?php echo ucfirst($consultType); ?></p>
    <br>
    
    <div style="display:flex; justify-content:space-between;">
        <span>Doctor Consultation Fee</span>
        <span>₹<?php echo ($totalFee - 15); ?></span>
    </div>
    <div style="display:flex; justify-content:space-between;">
        <span>Platform Service Fee</span>
        <span>₹15</span>
    </div>
    <div style="border-bottom:1px dashed #000; margin:10px 0;"></div>
    <div style="display:flex; justify-content:space-between; font-weight:bold;">
        <span>Total</span>
        <span>₹<?php echo $totalFee; ?></span>
    </div>
    <br>
    <p>Payment Method: <?php echo htmlspecialchars($paymentMethod); ?></p>
    <p>Payment Reference: <?php echo htmlspecialchars($txnId); ?></p>
    <p>Payment Status: <strong><?php echo $isCod ? 'COLLECT ON CONSULTATION / VISIT' : 'PAID VIA UPI'; ?></strong></p>
    <div style="border-bottom:1px dashed #000; margin:20px 0;"></div>
    <p style="text-align:center;">Thank you for trusting KSR GURU JI Doctor Assist</p>
</div>

<script>
    function generateInvoice() {
        const content = document.getElementById('invoice-template').innerHTML;
        const printWindow = window.open('', '', 'width=800,height=600');
        printWindow.document.write('<html><head><title>Invoice - ' + '<?php echo $appointId; ?>' + '</title></head><body style="padding:20px;">');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
</script>

</body>
</html>
