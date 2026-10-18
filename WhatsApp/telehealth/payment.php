<?php
require_once 'helper.php';

// Simulate POST from Review
$docId = $_POST['doc_id'] ?? 1;
$consultType = $_POST['type'] ?? 'video';
$appDate = $_POST['date'] ?? 'September 6, 2026';
$appTime = $_POST['time'] ?? '02:30 PM';
$totalFee = (float)($_POST['amount'] ?? 140);
$patientName = $_POST['patient_name'] ?? 'Jampaleppa';

$pdo = getAssistDB();

// Fetch Doctor Info
$doctor = null;
try {
    $stmt = $pdo->prepare("SELECT doctor_name, specialty FROM doctors WHERE id = ?");
    $stmt->execute([$docId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

if (!$doctor) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $docNameFallback = $_SESSION['user_name'] ?? 'DR. D C Rathod';
    if ($docId == 2) {
        $doctor = ['doctor_name' => 'Dr. Shivukumar Rathod', 'specialty' => 'Pediatrics & General Medicine'];
    } else {
        $doctor = ['doctor_name' => $docNameFallback, 'specialty' => TELEHEALTH_LEAD_DOCTOR_SPECIALTY];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment | KSR GURU JI Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .flow-container { max-width: 680px; margin: 32px auto; padding: 0 16px; margin-bottom: 120px; }

        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--clr-text-secondary); font-weight: 600; margin-bottom: 16px; text-decoration: none; transition: var(--transition); }
        .back-link:hover { color: var(--clr-primary); }

        .page-title { font-size: 1.8rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 6px; }
        .security-badge { font-size: 0.95rem; font-weight: 700; color: #16A34A; margin-bottom: 24px; display: flex; align-items: center; gap: 8px; }
        
        .step-tracker { display: flex; align-items: center; gap: 8px; font-size: 0.95rem; font-weight: 600; color: var(--clr-text-secondary); margin-bottom: 28px; }
        .dots { display: flex; gap: 6px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--clr-border); }
        .dot.active { background: var(--clr-primary); }
        .dot.passed { background: var(--clr-primary-light); }

        /* Block formatting */
        .card-panel { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm); }
        
        /* Summary Block */
        .summary-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--clr-border); }
        .doc-name { font-size: 1.15rem; font-weight: 800; color: var(--clr-text-primary); margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .doc-name span { color: #16A34A; font-size: 0.85rem; font-weight: 700; }
        .doc-spec { font-size: 0.95rem; color: var(--clr-text-secondary); }

        .summary-details { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.95rem; color: var(--clr-text-primary); }
        @media(max-width:550px) { .summary-details { grid-template-columns: 1fr; } }
        .summary-details i { color: var(--clr-primary); width: 20px; text-align: center; }

        .section-title { font-size: 1.05rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px; }

        /* Payment Methods */
        .pay-method-card { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border: 2px solid var(--clr-border); border-radius: var(--radius-md); margin-bottom: 12px; cursor: pointer; transition: 0.2s; background: white; }
        .pay-method-card:hover { border-color: var(--clr-primary-light); background: #F8FAFC; }
        .pay-method-card.selected { border-color: var(--clr-primary); background: #F0FDF4; }
        
        .pay-left { display: flex; align-items: center; gap: 16px; }
        .pay-icon { font-size: 1.5rem; }
        .pay-title { font-weight: 800; color: var(--clr-text-primary); margin-bottom: 4px; font-size: 1.05rem; }
        .pay-desc { font-size: 0.85rem; color: var(--clr-text-secondary); }
        
        .pay-radio { width: 22px; height: 22px; border: 2px solid var(--clr-border); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .pay-method-card.selected .pay-radio { border-color: var(--clr-primary); }
        .pay-method-card.selected .pay-radio::after { content: ''; width: 12px; height: 12px; background: var(--clr-primary); border-radius: 50%; }

        .upi-expansion { padding: 22px; background: white; border: 2px solid var(--clr-primary); border-top: none; border-radius: 0 0 var(--radius-md) var(--radius-md); margin-top: -14px; margin-bottom: 16px; display: none; }
        
        /* Breakdown */
        .breakdown-row { display: flex; justify-content: space-between; font-size: 1rem; color: var(--clr-text-secondary); margin-bottom: 12px; font-weight: 500; }
        .breakdown-total { border-top: 1px dashed var(--clr-border); padding-top: 16px; margin-top: 8px; display: flex; justify-content: space-between; font-weight: 900; font-size: 1.35rem; color: var(--clr-primary-dark); }
        
        /* Sticky Button */
        .btn-sticky-container { position: fixed; bottom: 0; left: 0; width: 100%; padding: 16px; background: white; border-top: 1px solid var(--clr-border); box-shadow: 0 -4px 16px rgba(0,0,0,0.08); text-align: center; z-index: 50; }
        .btn-pay { width: 100%; max-width: 680px; padding: 18px; font-size: 1.15rem; font-weight: 800; background: var(--clr-primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 10px; transition: 0.3s; box-shadow: 0 6px 20px rgba(37,99,235,0.3); }
        .btn-pay.disabled { background: #94A3B8; cursor: not-allowed; box-shadow: none; }
        .btn-pay:not(.disabled):hover { background: var(--clr-primary-dark); transform: translateY(-2px); }

        /* Loader Overlay */
        .loader-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.95); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .loader-overlay.active { opacity: 1; pointer-events: all; }
        .spinner { width: 60px; height: 60px; border: 4px solid var(--clr-border); border-top-color: var(--clr-primary); border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 24px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loader-title { font-size: 1.5rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 10px; }
        .loader-text { font-size: 1rem; color: var(--clr-text-secondary); margin-bottom: 20px; font-weight: 500; }
        .loader-amount { font-size: 1.3rem; font-weight: 800; color: var(--clr-text-primary); }
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

<div class="flow-container">
    <a href="javascript:history.back()" class="back-link"><i class="fas fa-arrow-left"></i> Back to Review</a>
    
    <h1 class="page-title">Payment & Confirmation</h1>
    <div class="security-badge"><i class="fas fa-shield-alt"></i> Protected by KSR GURU JI Healthcare Protocol</div>

    <div class="step-tracker">
        Step 6 of 6
        <div class="dots">
            <div class="dot passed"></div><div class="dot passed"></div><div class="dot passed"></div>
            <div class="dot passed"></div><div class="dot passed"></div><div class="dot active"></div>
        </div>
    </div>

    <!-- 2. Appointment Summary -->
    <div class="card-panel">
        <div class="summary-header">
            <div>
                <div class="doc-name">👨‍⚕️ <?php echo htmlspecialchars($doctor['doctor_name']); ?> <span><i class="fas fa-check-circle"></i> Verified</span></div>
                <div class="doc-spec"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
            </div>
        </div>
        <div class="summary-details">
            <div><i class="fas <?php echo $consultType === 'audio' ? 'fa-headphones-alt' : 'fa-video'; ?>"></i> <?php echo ucfirst($consultType); ?> Consultation</div>
            <div><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($appDate); ?></div>
            <div><i class="fas fa-clock"></i> <?php echo htmlspecialchars($appTime); ?></div>
            <div><i class="fas fa-user-injured"></i> Patient: <b><?php echo htmlspecialchars($patientName); ?></b></div>
        </div>
    </div>

    <!-- 3. Payment Methods -->
    <h2 class="section-title">SELECT PAYMENT METHOD</h2>
    
    <!-- UPI METHOD (QR & ID) -->
    <div class="pay-method-card selected" id="pm-upi" onclick="selectMethod('upi')">
        <div class="pay-left">
            <div class="pay-icon" style="background:#E0F2FE; color:#0284C7; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
                <i class="fas fa-qrcode"></i>
            </div>
            <div>
                <div class="pay-title" style="display:flex; align-items:center; gap:8px;">
                    UPI / QR Code Scan & Pay 
                    <span style="background:#DCFCE7; color:#166534; font-size:0.75rem; padding:2px 8px; border-radius:10px; font-weight:800;">INSTANT</span>
                </div>
                <div class="pay-desc">GPay • PhonePe • Paytm • BHIM • Amazon Pay</div>
            </div>
        </div>
        <div class="pay-radio"></div>
    </div>
    
    <div class="upi-expansion" id="upi-box" style="display:block;">
        <div style="text-align:center; margin-bottom:20px;">
            <div style="font-weight:800; color:var(--clr-primary-dark); font-size:1.1rem; margin-bottom:4px;">Scan QR Code to Pay ₹<?php echo number_format($totalFee, 2); ?></div>
            <div style="font-size:0.85rem; color:var(--clr-text-secondary); margin-bottom:14px;">Open your favorite UPI app & scan the QR code below</div>
            
            <div style="display:inline-block; padding:12px; background:white; border-radius:16px; border:2px solid #E2E8F0; box-shadow:0 8px 24px rgba(0,0,0,0.06);">
                <img src="QR.png" alt="KSR GURU JI UPI QR Code" style="width:210px; height:210px; object-fit:contain; display:block; border-radius:8px;" onerror="this.src='/QR.png';">
            </div>
            
            <div style="margin-top:12px;">
                <a href="upi://pay?pa=jyampallepparathod-1@oksbi&pn=KSR%20GURU%20JI&am=<?php echo $totalFee; ?>&cu=INR" class="btn-pay" style="display:inline-flex; width:auto; padding:10px 22px; font-size:0.9rem; background:#4338CA; color:white; border-radius:50px; text-decoration:none; box-shadow:0 4px 14px rgba(67,56,202,0.3);">
                    <i class="fas fa-bolt"></i> Tap to Open UPI App
                </a>
            </div>
        </div>

        <div style="border-top:1px dashed #CBD5E1; padding-top:16px;">
            <div style="font-size:0.85rem; font-weight:800; color:var(--clr-text-secondary); margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">Registered UPI IDs:</div>
            
            <!-- UPI ID 1 -->
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:12px 14px; display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; gap:8px;">
                <div>
                    <span style="font-size:0.75rem; color:#64748B; font-weight:600; display:block;">Primary SBI UPI</span>
                    <strong style="font-family:monospace; font-size:1rem; color:#0F172A;" id="upi-val-1">jyampallepparathod-1@oksbi</strong>
                </div>
                <button type="button" onclick="copyUPI('jyampallepparathod-1@oksbi', this)" style="border:none; background:#E2E8F0; color:#0F172A; padding:7px 14px; border-radius:8px; font-weight:800; font-size:0.85rem; cursor:pointer; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>

            <!-- UPI ID 2 -->
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:12px 14px; display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; gap:8px;">
                <div>
                    <span style="font-size:0.75rem; color:#64748B; font-weight:600; display:block;">Owner UPI (Amazon Pay / Axis)</span>
                    <strong style="font-family:monospace; font-size:1rem; color:#0F172A;" id="upi-val-2">8088584421@apl</strong>
                </div>
                <button type="button" onclick="copyUPI('8088584421@apl', this)" style="border:none; background:#E2E8F0; color:#0F172A; padding:7px 14px; border-radius:8px; font-weight:800; font-size:0.85rem; cursor:pointer; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>

            <label style="font-size:0.85rem; font-weight:700; color:var(--clr-text-secondary); display:block; margin-bottom:6px;">UPI UTR / Reference No. (Optional)</label>
            <input type="text" placeholder="12-digit UTR number or sender UPI number" id="upi-input" oninput="checkInput()" style="background:white; border:1.5px solid #CBD5E1; width:100%; padding:12px; border-radius:10px; font-size:0.95rem; font-family:inherit;">
        </div>
    </div>

    <!-- CASH AND DELIVERY / PAY ON VISIT -->
    <div class="pay-method-card" id="pm-cod" onclick="selectMethod('cod')">
        <div class="pay-left">
            <div class="pay-icon" style="background:#DCFCE7; color:#16A34A; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <div class="pay-title" style="display:flex; align-items:center; gap:8px;">
                    Cash and Delivery / Pay on Visit
                    <span style="background:#FEF3C7; color:#B45309; font-size:0.75rem; padding:2px 8px; border-radius:10px; font-weight:800;">ZERO ADVANCE</span>
                </div>
                <div class="pay-desc">Pay ₹<?php echo number_format($totalFee, 2); ?> in cash during consultation visit or at delivery</div>
            </div>
        </div>
        <div class="pay-radio"></div>
    </div>

    <!-- 4. Price Breakdown -->
    <div class="card-panel" style="margin-top: 28px;">
        <h2 class="section-title">PAYMENT SUMMARY</h2>
        <div class="breakdown-row">
            <span>Doctor Consultation Fee</span>
            <span>₹<?php echo number_format($totalFee - 15, 2); ?></span>
        </div>
        <div class="breakdown-row">
            <span>Platform Service Fee</span>
            <span>₹15.00</span>
        </div>
        <div class="breakdown-total">
            <span>TOTAL AMOUNT</span>
            <span>₹<?php echo number_format($totalFee, 2); ?></span>
        </div>
    </div>

</div>

<!-- 5. Payment Button -->
<div class="btn-sticky-container">
    <button class="btn-pay" id="main-pay-btn" onclick="processPayment()">
        🔒 Confirm UPI Payment — ₹<?php echo number_format($totalFee, 2); ?>
    </button>
</div>

<!-- 6. Processing Screen Overlay -->
<div class="loader-overlay" id="processing-overlay">
    <div class="spinner"></div>
    <div class="loader-title" id="proc-title">Processing Payment...</div>
    <div class="loader-text" id="proc-sub">Please don't close this window.</div>
    <div class="loader-amount">Amount: ₹<?php echo number_format($totalFee, 2); ?></div>
</div>

<form id="success-flow-form" method="POST" action="appointment-confirmation.php">
    <input type="hidden" name="doc_id" value="<?php echo htmlspecialchars((string)$docId); ?>">
    <input type="hidden" name="type" value="<?php echo htmlspecialchars((string)$consultType); ?>">
    <input type="hidden" name="date" value="<?php echo htmlspecialchars((string)$appDate); ?>">
    <input type="hidden" name="time" value="<?php echo htmlspecialchars((string)$appTime); ?>">
    <input type="hidden" name="amount" value="<?php echo htmlspecialchars((string)$totalFee); ?>">
    <input type="hidden" name="patient_name" value="<?php echo htmlspecialchars((string)$patientName); ?>">
    <input type="hidden" name="payment_method" id="form-pay-method" value="UPI">
    <!-- Generate txn id -->
    <input type="hidden" name="txn_id" id="form-txn-id" value="<?php echo 'UPI-' . rand(1000000000, 9999999999); ?>">
</form>

<script>
    let selectedMethod = 'upi';

    function selectMethod(method) {
        selectedMethod = method;
        
        // Reset all
        document.querySelectorAll('.pay-method-card').forEach(el => {
            el.classList.remove('selected');
            el.style.borderBottomLeftRadius = 'var(--radius-md)';
            el.style.borderBottomRightRadius = 'var(--radius-md)';
        });
        document.getElementById('upi-box').style.display = 'none';

        const box = document.getElementById('pm-' + method);
        if (box) {
            box.classList.add('selected');
            
            if(method === 'upi') {
                box.style.borderBottomLeftRadius = '0';
                box.style.borderBottomRightRadius = '0';
                document.getElementById('upi-box').style.display = 'block';
                document.getElementById('form-pay-method').value = 'UPI (jyampallepparathod-1@oksbi)';
                document.getElementById('main-pay-btn').innerHTML = '🔒 Confirm UPI Payment — ₹<?php echo number_format($totalFee, 2); ?>';
            } else if (method === 'cod') {
                document.getElementById('form-pay-method').value = 'Cash and Delivery';
                document.getElementById('main-pay-btn').innerHTML = '💵 Confirm Booking (Cash and Delivery) — ₹<?php echo number_format($totalFee, 2); ?>';
            }
        }

        checkInput();
    }

    function checkInput() {
        const btn = document.getElementById('main-pay-btn');
        if (selectedMethod === 'upi') {
            const utr = document.getElementById('upi-input').value.trim();
            if (utr.length > 0) {
                document.getElementById('form-txn-id').value = utr;
            }
            btn.classList.remove('disabled');
        } else if (selectedMethod === 'cod') {
            document.getElementById('form-txn-id').value = 'COD-' + Math.floor(100000 + Math.random() * 900000);
            btn.classList.remove('disabled');
        }
    }

    function copyUPI(text, btn) {
        navigator.clipboard.writeText(text).then(function() {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check" style="color:#16A34A"></i> Copied!';
            btn.style.background = '#DCFCE7';
            setTimeout(() => {
                btn.innerHTML = original;
                btn.style.background = '#E2E8F0';
            }, 2000);
        }).catch(function() {
            prompt('Copy UPI ID:', text);
        });
    }

    function processPayment() {
        if(document.getElementById('main-pay-btn').classList.contains('disabled')) return;
        
        const overlay = document.getElementById('processing-overlay');
        const title = document.getElementById('proc-title');
        const sub = document.getElementById('proc-sub');
        
        if (selectedMethod === 'cod') {
            title.innerText = 'Confirming Appointment...';
            sub.innerText = 'Booking registered with Cash and Delivery.';
        } else {
            title.innerText = 'Verifying UPI Payment...';
            sub.innerText = 'Confirming transaction with Doctor Assist.';
        }
        
        overlay.classList.add('active');

        setTimeout(() => {
            document.getElementById('success-flow-form').submit();
        }, 1200);
    }
</script>

</body>
</html>
