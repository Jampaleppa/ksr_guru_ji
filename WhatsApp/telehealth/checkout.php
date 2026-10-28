<?php
require_once 'helper.php';

// Configurable Admin fees
$platformFee = 15;
$deliveryFee = 15;
$discount = 50;
$cartSubtotal = 335;
$total = ($cartSubtotal + $platformFee + $deliveryFee) - $discount;

// Linkage State
$rxLinked = true;
$rxId = 'RX-2026-001245';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';
$issueDate = '05 Sep 2026';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Doctor Assist Pharmacy</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-glass: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
            --gradient-primary: linear-gradient(135deg, var(--clr-primary), var(--clr-primary-dark));
            --radius-xl: 16px;
        }

        body { background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%); font-family: 'Inter', sans-serif; min-height: 100vh; }
        .checkout-layout { max-width: 1200px; margin: 40px auto; padding: 0 16px; display: grid; grid-template-columns: 1fr 400px; gap: 32px; align-items: start; margin-bottom: 100px; position: relative; z-index: 10;}
        
        .c-card { background: var(--glass-bg); backdrop-filter: blur(12px); border-radius: var(--radius-xl); border: 1px solid var(--glass-border); box-shadow: var(--shadow-glass); padding: 28px; margin-bottom: 24px; transition: 0.3s; }
        .c-card:hover { border-color: rgba(255,255,255,0.8); box-shadow: 0 16px 40px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .c-title { font-size: 1.25rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 24px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 16px; }

        /* Progress Bar */
        .progress-bar { display: flex; justify-content: space-between; align-items: center; max-width: 800px; margin: 40px auto; position: relative; }
        .progress-bar::before { content: ''; position: absolute; top: 12px; left: 0; right: 0; height: 3px; background: rgba(0,0,0,0.05); z-index: 1; }
        .step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; color: var(--clr-text-secondary); transition: 0.3s; }
        .step-icon { width: 28px; height: 28px; border-radius: 50%; background: white; border: 3px solid rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: 0.3s; }
        .step.done .step-icon { background: var(--clr-primary); border-color: var(--clr-primary); color: white; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .step.active .step-icon { border-color: var(--clr-primary); background: white; color: var(--clr-primary); box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
        .step.done, .step.active { color: var(--clr-text-primary); font-weight: 800; }

        /* Rx Banner */
        .rx-banner { background: rgba(240, 253, 244, 0.7); backdrop-filter: blur(8px); border: 1px solid rgba(187, 247, 208, 0.7); padding: 24px; border-radius: var(--radius-xl); display: flex; justify-content: space-between; align-items: flex-start; }
        .rx-b-title { color: #166534; font-weight: 800; font-size: 1.15rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .rx-b-meta { font-size: 0.95rem; color: #166534; margin-bottom: 4px; font-weight: 500;}
        
        .rx-ticks div { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.9rem; color: #15803D; margin-bottom: 8px; }
        .rx-ticks div.wait { color: #EA580C; }

        /* Cart Items */
        .cart-item { display: flex; gap: 20px; padding: 20px 0; border-bottom: 1px solid rgba(0,0,0,0.05); transition: 0.2s; }
        .cart-item:last-child { border-bottom: none; padding-bottom: 0; }
        .cart-item:hover { background: rgba(255,255,255,0.3); border-radius: 12px; padding: 20px; margin: 0 -20px; }
        .ci-icon { width: 56px; height: 56px; background: white; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--clr-text-secondary); box-shadow: var(--shadow-glass); }
        .ci-main { flex: 1; }
        .ci-name { font-weight: 800; color: var(--clr-text-primary); font-size: 1.1rem; }
        .ci-meta { font-size: 0.9rem; color: var(--clr-text-secondary); margin: 6px 0; font-weight: 500;}
        .ci-rx { font-size: 0.85rem; font-weight: 700; color: #DC2626; display: flex; align-items: center; gap: 6px; margin-bottom: 10px; background: #FEF2F2; padding: 4px 10px; border-radius: 12px; width: max-content;}
        
        .ci-price-row { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; }
        .ci-price { font-size: 1.35rem; font-weight: 800; color: var(--clr-primary-dark); }
        
        .qty-ctrl { display: flex; align-items: center; gap: 16px; background: rgba(255,255,255,0.8); border: 1px solid rgba(0,0,0,0.05); border-radius: 50px; padding: 6px 14px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02); }
        .qty-ctrl button { border: none; background: transparent; font-weight: 800; color: var(--clr-primary); font-size: 1.1rem; cursor: pointer; transition: 0.2s;}
        .qty-ctrl button:hover { transform: scale(1.2); color: var(--clr-primary-dark); }
        .qty-ctrl span { font-weight: 800; width: 24px; text-align: center; }

        .btn-rem { border: none; background: rgba(220,38,38,0.05); color: #DC2626; padding: 8px 16px; border-radius: 50px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 0.9rem; transition: 0.2s; }
        .btn-rem:hover { background: #DC2626; color: white; }

        /* Matching Table */
        .match-card { background: rgba(255,255,255,0.5); padding: 24px; border-radius: var(--radius-xl); border: 1px solid rgba(0,0,0,0.05); box-shadow: inset 0 2px 10px rgba(0,0,0,0.02); }
        .match-row { display: grid; grid-template-columns: 1fr 20px 1fr 100px; gap: 16px; align-items: center; padding: 12px 0; border-bottom: 1px dashed rgba(0,0,0,0.05); font-size: 0.95rem; }
        .match-row:last-child { border-bottom: none; }
        .mr-head { font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px;}
        .ms-matched { color: #16A34A; font-weight: 800; }
        .ms-wait { color: #EA580C; font-weight: 800; }

        /* Selections */
        .select-box { border: 2px solid rgba(0,0,0,0.05); border-radius: var(--radius-xl); padding: 20px; margin-bottom: 16px; cursor: pointer; position: relative; transition: 0.3s; background: rgba(255,255,255,0.6); }
        .select-box.active { border-color: var(--clr-primary); background: rgba(255,255,255,0.9); box-shadow: 0 8px 24px rgba(37,99,235,0.1); }
        .select-box:hover { border-color: var(--clr-primary-light); background: white; }
        .sb-title { font-weight: 800; color: var(--clr-text-primary); margin-bottom: 6px; display: flex; gap: 10px; align-items: center; font-size: 1.05rem;}
        .sb-meta { font-size: 0.95rem; color: var(--clr-text-secondary); line-height: 1.5; font-weight: 500;}
        .sb-price { position: absolute; right: 20px; top: 20px; font-weight: 800; color: var(--clr-primary-dark); font-size: 1.1rem;}

        /* Forms */
        .input-box { width: 100%; border: 1px solid rgba(0,0,0,0.1); padding: 16px; border-radius: var(--radius-xl); font-family: inherit; margin-bottom: 16px; transition: 0.3s; background: rgba(255,255,255,0.8); font-size: 0.95rem; }
        .input-box:focus { outline: none; border-color: var(--clr-primary); box-shadow: 0 0 0 4px rgba(37,99,235,0.1); background: white; }
        .check-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 0.95rem; font-weight: 600; color: var(--clr-text-primary);}
        .check-row input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--clr-primary); }
        
        /* Summary Sidebar */
        .summary-card { background: var(--glass-bg); backdrop-filter: blur(24px); padding: 32px; border-radius: var(--radius-xl); border: 1px solid rgba(255,255,255,0.8); position: sticky; top: 32px; box-shadow: 0 24px 64px rgba(0,0,0,0.1); }
        .sum-row { display: flex; justify-content: space-between; font-weight: 600; color: var(--clr-text-secondary); margin-bottom: 16px; font-size: 1.05rem; }
        .sum-divider { border-top: 1px dashed rgba(0,0,0,0.1); margin: 24px 0; }
        .sum-total { font-size: 1.6rem; font-weight: 900; color: var(--clr-primary-dark); display: flex; justify-content: space-between; align-items: center; }
        
        .btn-place-order { background: var(--gradient-primary); color: white; border: none; padding: 20px; width: 100%; border-radius: 50px; font-weight: 900; font-size: 1.2rem; cursor: pointer; transition: 0.3s; margin-top: 32px; box-shadow: 0 8px 24px rgba(37,99,235,0.4); text-transform: uppercase; letter-spacing: 0.5px;}
        .btn-place-order:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(37,99,235,0.5); }

        /* PROCESSING MODAL */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 3000; opacity: 0; pointer-events: none; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); padding: 16px; }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(24px); width: 100%; max-width: 500px; border-radius: var(--radius-xl); padding: 48px 40px; text-align: center; box-shadow: 0 24px 64px rgba(0,0,0,0.2), inset 0 0 0 1px rgba(255,255,255,0.6); transform: scale(0.95) translateY(20px); transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .modal-overlay.active .modal-card { transform: scale(1) translateY(0); }
        
        .spin-stage, .success-stage { display: none; }
        .spin-stage.active, .success-stage.active { display: block; }
        
        .loader-ring { width: 70px; height: 70px; border: 8px solid rgba(37,99,235,0.1); border-top-color: var(--clr-primary); border-radius: 50%; animation: spin 1s infinite cubic-bezier(0.68, -0.55, 0.265, 1.55); margin: 0 auto 32px auto; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .success-icon { width: 80px; height: 80px; background: linear-gradient(135deg, #22C55E, #16A34A); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 32px auto; box-shadow: 0 8px 24px rgba(22, 163, 74, 0.3); }
        
        .pl-list { text-align: left; background: rgba(248, 250, 252, 0.7); border: 1px solid rgba(0,0,0,0.05); padding: 24px; border-radius: var(--radius-xl); margin-top: 32px; box-shadow: inset 0 2px 10px rgba(0,0,0,0.02); }
        .pl-item { display: flex; align-items: center; gap: 12px; font-weight: 700; margin-bottom: 12px; font-size: 1rem; }
        
        /* Navigation Override */
        .navbar-links { display: flex; gap: 24px; }
        .navbar-links a { color: var(--clr-text-secondary); font-weight: 700; text-decoration: none; transition: 0.2s; }
        .navbar-links a:hover { color: var(--clr-text-primary); }

        @media (max-width: 900px) {
            .checkout-layout { grid-template-columns: 1fr; margin-top: 0; }
            .summary-card { position: relative; top: 0; }
            .rx-banner { flex-direction: column; gap: 16px; }
        }
        @media (max-width: 768px) {
            .navbar-links {
                display: none !important;
            }

            .summary-card {
                padding: 24px 18px;
            }

            .match-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .cart-item {
                gap: 14px;
            }

            .modal-card {
                padding: 32px 20px;
            }
        }

        @media (max-width: 480px) {
            .cart-item {
                flex-direction: column;
            }

            .ci-price-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .summary-card {
                padding: 20px 14px;
                border-radius: var(--radius-lg);
            }

            .sum-total {
                font-size: 1.35rem;
            }

            .btn-place-order {
                padding: 16px;
                font-size: 1.05rem;
                margin-top: 20px;
            }

            .rx-banner {
                padding: 16px;
            }

            .select-box {
                padding: 14px;
            }

            .sb-price {
                position: static;
                margin-top: 8px;
            }
        }

        @media (max-width: 360px) {
            .ci-price {
                font-size: 1.15rem;
            }

            .btn-place-order {
                font-size: 0.95rem;
                padding: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="index.php" class="navbar-brand"><i class="fas fa-heartbeat"></i> KSR GURU JI DOCTOR ASSIST</a>
            <div class="navbar-links" style="display: flex; gap: 24px;">
                <a href="index.php">Home</a>
                <a href="find-doctor.php">Find Doctor</a>
                <a href="my-appointments.php">Appointments</a>
                <a href="pharmacy.php">Pharmacy</a>
                <a href="my-prescriptions.php">Prescriptions</a>
            </div>
        </div>
        <a href="#"  style="background:var(--clr-primary-light); color:var(--clr-primary); padding:10px 16px; border-radius:20px; text-decoration:none;"><i class="fas fa-user-circle"></i></a>
    </div>
</nav>

<div style="text-align: center; margin: 32px 0;">
    <h1 style="font-size: 2rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 4px;">Medicine Cart & Checkout</h1>
    <p style="color: var(--clr-text-secondary); font-size: 1.05rem;">Review your medicines before placing the order</p>
</div>

<div class="progress-bar">
    <div class="step done"><div class="step-icon"><i class="fas fa-check"></i></div>Cart</div>
    <div class="step done"><div class="step-icon"><i class="fas fa-check"></i></div>Prescription</div>
    <div class="step active"><div class="step-icon"><i class="fas fa-dot-circle"></i></div>Delivery</div>
    <div class="step"><div class="step-icon"></div>Payment</div>
    <div class="step"><div class="step-icon"></div>Order Confirmed</div>
</div>

<div class="checkout-layout">
    
    <!-- LEFT PANE: Checkout Flow -->
    <div>
        <!-- 3. Prescription Linkage Banner -->
        <?php if($rxLinked): ?>
        <div class="c-card" style="padding: 0;">
            <div class="rx-banner">
                <div>
                    <div class="rx-b-title"><i class="fas fa-shield-alt"></i> Prescription Linked</div>
                    <div class="rx-b-meta">Prescription: <span style="font-family:monospace;"><?php echo $rxId; ?></span></div>
                    <div class="rx-b-meta">Doctor: <?php echo $docName; ?></div>
                    <div class="rx-b-meta">Issued: <?php echo $issueDate; ?></div>
                </div>
                <div class="rx-ticks">
                    <div><i class="fas fa-check-circle"></i> Prescription available</div>
                    <div><i class="fas fa-check-circle"></i> Medicines matched</div>
                    <div class="wait"><i class="fas fa-hourglass-half"></i> Final pharmacist verification</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 4. Cart Mod -->
        <div class="c-card">
            <div class="c-title"><i class="fas fa-shopping-basket"></i> Your Medicines <span style="margin-left:auto; font-weight:600; font-size:0.9rem; color:var(--clr-text-secondary);">3 Items</span></div>
            
            <div class="cart-item">
                <div class="ci-icon"><i class="fas fa-pills"></i></div>
                <div class="ci-main">
                    <div class="ci-name">Paracetamol 500 mg</div>
                    <div class="ci-meta">Tablet · Strip of 10</div>
                    <div class="ci-rx"><i class="fas fa-prescription"></i> Prescription Required</div>
                    
                    <div class="ci-price-row">
                        <div class="ci-price">₹35</div>
                        <div class="qty-ctrl"><button>-</button><span>1</span><button>+</button></div>
                        <button class="btn-rem"><i class="fas fa-trash-alt"></i> Remove</button>
                    </div>
                </div>
            </div>

            <div class="cart-item">
                <div class="ci-icon"><i class="fas fa-pills"></i></div>
                <div class="ci-main">
                    <div class="ci-name">Medicine Name 2</div>
                    <div class="ci-meta">10 tablets · Pharma Ltd</div>
                    <div class="ci-rx"><i class="fas fa-prescription"></i> Prescription Required</div>
                    
                    <div class="ci-price-row">
                        <div class="ci-price">₹120</div>
                        <div class="qty-ctrl"><button>-</button><span>2</span><button>+</button></div>
                        <button class="btn-rem"><i class="fas fa-trash-alt"></i> Remove</button>
                    </div>
                </div>
            </div>

            <div class="cart-item">
                <div class="ci-icon" style="color: #16A34A;"><i class="fas fa-pump-medical"></i></div>
                <div class="ci-main">
                    <div class="ci-name">Vitamin / OTC Product</div>
                    <div class="ci-meta">Pack of 30 Gummies</div>
                    
                    <div class="ci-price-row">
                        <div class="ci-price">₹180</div>
                        <div class="qty-ctrl"><button>-</button><span>1</span><button>+</button></div>
                        <button class="btn-rem"><i class="fas fa-trash-alt"></i> Remove</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Matching Matrix -->
        <?php if($rxLinked): ?>
        <div class="c-card">
            <div class="c-title"><i class="fas fa-search"></i> Prescription Matching</div>
            <div class="match-card">
                <div class="match-row mr-head">
                    <div>Prescription Medicine</div>
                    <div></div>
                    <div>Cart Medicine</div>
                    <div>Status</div>
                </div>
                <div class="match-row">
                    <div>Paracetamol 500 mg</div>
                    <div>→</div>
                    <div>Paracetamol 500 mg</div>
                    <div class="ms-matched"><i class="fas fa-check"></i> Matched</div>
                </div>
                <div class="match-row">
                    <div>Medicine B 10 mg</div>
                    <div>→</div>
                    <div>Medicine B 10 mg</div>
                    <div class="ms-matched"><i class="fas fa-check"></i> Matched</div>
                </div>
                <div class="match-row" style="background:#FFF7ED; padding:8px; border-radius:4px; margin:0 -8px;">
                    <div>Medicine C</div>
                    <div>→</div>
                    <div>Medicine C</div>
                    <div class="ms-wait"><i class="fas fa-clock"></i> Review</div>
                </div>
            </div>
            <div style="font-size:0.85rem; color:#92400E; background:#FFFBEB; padding:12px; border-radius:4px; margin-top:16px;">
                <i class="fas fa-info-circle"></i> Pharmacist verification may be required before fulfillment to ensure safety. Substitutions are strictly controlled.
            </div>
        </div>
        <?php endif; ?>

        <!-- 6. Delivery Address -->
        <div class="c-card">
            <div class="c-title"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
            
            <div class="select-box active">
                <div class="sb-title">● Home <span style="font-size:0.85rem; color:#16A34A; margin-left:auto;">✓ Selected</span></div>
                <div class="sb-meta" style="margin-left: 20px;">
                    <strong style="color:var(--clr-text-primary);">Jampaleppa Rathod</strong><br>
                    #42 Healthcare Layout, Main Road<br>
                    Gajendragad, Karnataka - 582114
                </div>
            </div>

            <button style="border: 1px dashed var(--clr-primary); background: transparent; color: var(--clr-primary); width: 100%; padding: 12px; font-weight: 700; border-radius: var(--radius-sm); cursor: pointer;">+ Add New Address</button>
        </div>

        <!-- 7. Delivery Instructions & Settings -->
        <div class="c-card">
            <div class="c-title"><i class="fas fa-truck"></i> Delivery Settings</div>
            
            <div style="font-weight:700; margin-bottom:12px;">Delivery Speed</div>
            <div class="select-box active">
                <div class="sb-title">● Standard Delivery</div>
                <div class="sb-meta" style="margin-left:20px;">Estimated delivery: 2-3 days</div>
                <div class="sb-price">₹<?php echo $deliveryFee; ?></div>
            </div>
            <div class="select-box" style="opacity:0.6;">
                <div class="sb-title">○ Express Delivery</div>
                <div class="sb-meta" style="margin-left:20px;">Currently unavailable in your area</div>
            </div>

            <div style="font-weight:700; margin-top:24px; margin-bottom:12px;">Delivery Instructions</div>
            <input type="text" class="input-box" placeholder="Leave at reception / Call before delivery / Other...">
            <div class="check-row"><input type="checkbox"> Don't ring the doorbell</div>
            <div class="check-row"><input type="checkbox"> Call me before delivery</div>
        </div>

        <!-- 10. Payment Method -->
        <div class="c-card">
            <div class="c-title"><i class="fas fa-wallet"></i> Payment Method</div>
            
            <!-- UPI OPTION -->
            <div class="select-box active" id="sb-upi" onclick="choosePayment('upi')">
                <div class="sb-title">
                    <span id="radio-upi" style="color:var(--clr-primary);">●</span> UPI / Scan QR Code
                    <span style="background:#DCFCE7; color:#166534; font-size:0.75rem; padding:2px 8px; border-radius:10px; font-weight:800; margin-left:auto;">Instant</span>
                </div>
                <div class="sb-meta" style="margin-left:20px; display:flex; gap:8px; margin-top:4px;">
                    <span style="background:#F1F5F9; padding:2px 8px; border-radius:12px; font-size:0.8rem;">GPay</span>
                    <span style="background:#F1F5F9; padding:2px 8px; border-radius:12px; font-size:0.8rem;">PhonePe</span>
                    <span style="background:#F1F5F9; padding:2px 8px; border-radius:12px; font-size:0.8rem;">Paytm</span>
                    <span style="background:#F1F5F9; padding:2px 8px; border-radius:12px; font-size:0.8rem;">BHIM</span>
                </div>
            </div>

            <!-- UPI DETAILS & QR CODE -->
            <div id="checkout-upi-box" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:18px; margin-bottom:16px;">
                <div style="text-align:center; margin-bottom:16px;">
                    <div style="font-weight:800; color:#0F172A; font-size:1rem; margin-bottom:4px;">Scan & Pay ₹<?php echo number_format($total, 2); ?></div>
                    <div style="font-size:0.85rem; color:#64748B; margin-bottom:10px;">Use Google Pay, PhonePe, Paytm or any UPI App</div>
                    <div style="display:inline-block; padding:8px; background:white; border-radius:14px; border:1px solid #CBD5E1; box-shadow:0 4px 14px rgba(0,0,0,0.06);">
                        <img src="QR.png" alt="Scan UPI QR Code" style="width:180px; height:180px; object-fit:contain; display:block; border-radius:8px;" onerror="this.src='/QR.png';">
                    </div>
                </div>

                <div style="font-size:0.8rem; font-weight:800; color:#64748B; text-transform:uppercase; margin-bottom:8px; letter-spacing:0.5px;">Registered UPI IDs:</div>
                
                <!-- UPI ID 1 -->
                <div style="background:white; border:1px solid #E2E8F0; border-radius:8px; padding:10px 12px; display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                    <div>
                        <span style="font-size:0.75rem; color:#64748B; display:block; font-weight:600;">Primary SBI UPI</span>
                        <strong style="font-family:monospace; font-size:0.95rem; color:#0F172A;">jyampallepparathod-1@oksbi</strong>
                    </div>
                    <button type="button" onclick="copyUPI('jyampallepparathod-1@oksbi', this)" style="border:none; background:#F1F5F9; color:#0F172A; padding:6px 14px; border-radius:6px; font-weight:700; font-size:0.85rem; cursor:pointer;">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>

                <!-- UPI ID 2 -->
                <div style="background:white; border:1px solid #E2E8F0; border-radius:8px; padding:10px 12px; display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                    <div>
                        <span style="font-size:0.75rem; color:#64748B; display:block; font-weight:600;">Owner UPI (Amazon Pay / Axis)</span>
                        <strong style="font-family:monospace; font-size:0.95rem; color:#0F172A;">8088584421@apl</strong>
                    </div>
                    <button type="button" onclick="copyUPI('8088584421@apl', this)" style="border:none; background:#F1F5F9; color:#0F172A; padding:6px 14px; border-radius:6px; font-weight:700; font-size:0.85rem; cursor:pointer;">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>

                <label style="font-size:0.8rem; font-weight:700; color:#64748B; display:block; margin-bottom:4px;">UPI Reference / UTR No. (Optional):</label>
                <input type="text" id="chk-upi-ref" class="input-box" placeholder="12-digit UTR or sender UPI mobile number" style="margin-bottom:0; font-size:0.9rem; padding:10px 12px;">
            </div>

            <!-- CASH AND DELIVERY -->
            <div class="select-box" id="sb-cod" onclick="choosePayment('cod')">
                <div class="sb-title">
                    <span id="radio-cod">○</span> Cash on Delivery (Cash and Delivery)
                    <span style="background:#FEF3C7; color:#B45309; font-size:0.75rem; padding:2px 8px; border-radius:10px; font-weight:800; margin-left:auto;">Available</span>
                </div>
                <div class="sb-meta" style="margin-left:20px;">Pay ₹<?php echo number_format($total, 2); ?> with cash when medicines are delivered to your doorstep.</div>
            </div>
        </div>

    </div>

    <!-- RIGHT PANE: Summary Sidebar -->
    <div>
        <div class="summary-card">
            <h2 style="font-size:1.25rem; font-weight:800; color:var(--clr-primary-dark); margin-bottom:24px; border-bottom:1px solid var(--clr-border); padding-bottom:12px;">ORDER SUMMARY</h2>
            
            <div class="sum-row">
                <span>Medicines (3)</span>
                <span>₹<?php echo number_format($cartSubtotal, 2); ?></span>
            </div>
            <div class="sum-row" style="color:#16A34A;">
                <span>Discount</span>
                <span>-₹<?php echo number_format($discount, 2); ?></span>
            </div>
            <div class="sum-row">
                <span>Delivery</span>
                <span>₹<?php echo number_format($deliveryFee, 2); ?></span>
            </div>
            <div class="sum-row">
                <span>Platform / Service Fee</span>
                <span>₹<?php echo number_format($platformFee, 2); ?></span>
            </div>
            
            <div class="sum-divider"></div>
            
            <div class="sum-total">
                <span>TOTAL</span>
                <span>₹<?php echo number_format($total, 2); ?></span>
            </div>

            <div style="background:#EEF2FF; padding:16px; border-radius:var(--radius-sm); margin-top:24px;">
                <h4 style="color:var(--clr-primary-dark); font-size:0.95rem; margin-bottom:8px;"><i class="fas fa-tag"></i> Offers</h4>
                <div style="display:flex; gap:8px;">
                    <input type="text" class="input-box" style="margin:0; padding:8px; text-transform:uppercase;" value="HEALTH10" disabled>
                    <button style="border:none; background:var(--clr-primary); color:white; padding:8px 16px; border-radius:var(--radius-sm); font-weight:600;"><i class="fas fa-check"></i></button>
                </div>
                <div style="font-size:0.8rem; color:#16A34A; font-weight:600; margin-top:8px;">✓ Coupon applied. You saved ₹50.</div>
            </div>

            <!-- 12. Safety & Consent -->
            <div style="background:#F8FAFC; border:1px solid var(--clr-border); padding:16px; border-radius:var(--radius-sm); margin-top:24px;">
                <div style="font-size:0.9rem; font-weight:700; color:var(--clr-primary-dark); margin-bottom:8px;"><i class="fas fa-shield-alt"></i> Important</div>
                <ul style="margin:0 0 16px 20px; font-size:0.85rem; color:var(--clr-text-secondary); line-height:1.5;">
                    <li>Prescription medicines may require pharmacist verification.</li>
                    <li>Medicines fulfilled according to verified prescription.</li>
                    <li>Delivery timing is an estimate.</li>
                </ul>
                <div class="check-row" style="align-items:flex-start;"><input type="checkbox" checked> <span>I confirm that delivery and patient information is correct.</span></div>
                <div class="check-row" style="align-items:flex-start;"><input type="checkbox" checked> <span>I agree to the applicable pharmacy terms.</span></div>
            </div>

            <div style="text-align:center; margin-top:16px; font-weight:600; color:#166534; font-size:0.9rem;"><i class="fas fa-lock"></i> Secure Checkout</div>
            
            <button class="btn-place-order" onclick="triggerCheckout()">🛒 PLACE ORDER &mdash; ₹<?php echo number_format($total, 2); ?></button>

        </div>
    </div>

</div>

<!-- 14. & 15. Checkout Overlays -->
<div class="modal-overlay" id="checkout-modal">
    <div class="modal-card">
        
        <!-- Processing State (14) -->
        <div class="spin-stage active" id="mc-processing">
            <div class="loader-ring"></div>
            <h2 style="font-size:1.5rem; font-weight:800; color:var(--clr-primary-dark); margin-bottom:8px;">⏳ Processing Payment</h2>
            <div style="color:var(--clr-text-secondary);">Confirming your secure order...</div>
            
            <div class="pl-list">
                <div class="pl-item" style="color:#16A34A;"><i class="fas fa-check-circle"></i> Cart Validated</div>
                <div class="pl-item" style="color:#16A34A;"><i class="fas fa-check-circle"></i> Address Confirmed</div>
                <div class="pl-item" style="color:#EA580C;"><i class="fas fa-circle-notch fa-spin"></i> Awaiting Bank Confirmation</div>
            </div>
        </div>

        <!-- Success State (15) -->
        <div class="success-stage" id="mc-success">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <h2 style="font-size:1.5rem; font-weight:800; color:#16A34A; margin-bottom:8px;">Order Placed Successfully</h2>
            <div style="font-family:monospace; font-size:1.1rem; color:var(--clr-primary-dark); font-weight:700; margin-bottom:8px;">Order ID: MED-260905-1024</div>
            <div style="font-weight:600; color:var(--clr-text-secondary);">Estimated Delivery: 2-3 Days</div>
            
            <div class="pl-list" style="border-color:#BBF7D0; background:#F0FDF4;">
                <div class="pl-item" style="color:#166534;"><i class="fas fa-check"></i> Payment Successful (UPI)</div>
                <div class="pl-item" style="color:#166534;"><i class="fas fa-check"></i> Order Created</div>
                <div class="pl-item" style="color:#EA580C; background:#FFF7ED; padding:6px; border-radius:4px; margin-left:-6px; margin-right:-6px;"><i class="fas fa-hourglass-half"></i> Pharmacist Prescription Review Pending</div>
            </div>

            <div style="display:flex; flex-direction:column; gap:12px; margin-top:24px;">
                <button style="border:none; background:var(--clr-primary); color:white; padding:12px; border-radius:var(--radius-full); font-weight:700; cursor:pointer;" onclick="window.location.href='my-orders.php'">Track Order</button>
                <div style="display:flex; gap:12px;">
                    <button style="flex:1; border:1px solid var(--clr-border); background:white; color:var(--clr-text-primary); padding:12px; border-radius:var(--radius-full); font-weight:600; cursor:pointer;" onclick="window.location.href='my-prescriptions.php'"><i class="fas fa-file-medical"></i> View Rx</button>
                    <button style="flex:1; border:1px solid var(--clr-border); background:white; color:var(--clr-text-primary); padding:12px; border-radius:var(--radius-full); font-weight:600; cursor:pointer;" onclick="window.location.href='my-orders.php'"><i class="fas fa-download"></i> Invoice</button>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    let currentPaymentMethod = 'upi';

    function choosePayment(method) {
        currentPaymentMethod = method;
        const boxUpi = document.getElementById('sb-upi');
        const boxCod = document.getElementById('sb-cod');
        const upiDetails = document.getElementById('checkout-upi-box');
        const radioUpi = document.getElementById('radio-upi');
        const radioCod = document.getElementById('radio-cod');
        const placeBtn = document.querySelector('.btn-place-order');

        if (method === 'upi') {
            boxUpi.classList.add('active');
            boxCod.classList.remove('active');
            upiDetails.style.display = 'block';
            radioUpi.innerText = '●';
            radioUpi.style.color = 'var(--clr-primary)';
            radioCod.innerText = '○';
            radioCod.style.color = 'inherit';
            if (placeBtn) placeBtn.innerHTML = '🛒 PLACE ORDER &mdash; ₹<?php echo number_format($total, 2); ?> (UPI)';
        } else {
            boxCod.classList.add('active');
            boxUpi.classList.remove('active');
            upiDetails.style.display = 'none';
            radioCod.innerText = '●';
            radioCod.style.color = 'var(--clr-primary)';
            radioUpi.innerText = '○';
            radioUpi.style.color = 'inherit';
            if (placeBtn) placeBtn.innerHTML = '💵 PLACE ORDER &mdash; ₹<?php echo number_format($total, 2); ?> (CASH & DELIVERY)';
        }
    }

    function copyUPI(text, btn) {
        navigator.clipboard.writeText(text).then(function() {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check" style="color:#16A34A"></i> Copied!';
            btn.style.background = '#DCFCE7';
            setTimeout(() => {
                btn.innerHTML = original;
                btn.style.background = '#F1F5F9';
            }, 2000);
        }).catch(function() {
            prompt('Copy UPI ID:', text);
        });
    }

    async function triggerCheckout() {
        document.getElementById('checkout-modal').classList.add('active');
        document.getElementById('mc-processing').classList.add('active');
        document.getElementById('mc-success').classList.remove('active');

        const procTitle = document.querySelector('#mc-processing h2');
        if (procTitle) {
            procTitle.innerText = currentPaymentMethod === 'cod' ? '⏳ Registering Order...' : '⏳ Processing Payment...';
        }

        try {
            const formData = new FormData();
            formData.append('payment_method', currentPaymentMethod === 'cod' ? 'Cash and Delivery' : 'UPI');
            formData.append('total', '<?php echo $total; ?>');
            const refVal = document.getElementById('chk-upi-ref') ? document.getElementById('chk-upi-ref').value.trim() : '';
            if (refVal) formData.append('transaction_id', refVal);

            const res = await fetch('api_place_order.php', { 
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                setTimeout(() => {
                    document.getElementById('mc-processing').classList.remove('active');
                    document.getElementById('mc-success').classList.add('active');
                    
                    const successModal = document.getElementById('mc-success');
                    const orderIdEl = successModal.querySelector('div[style*="font-family:monospace"]');
                    if (orderIdEl) orderIdEl.innerText = 'Order ID: ' + data.order_number;

                    const payItem = successModal.querySelector('.pl-list .pl-item');
                    if (payItem) {
                        payItem.innerHTML = currentPaymentMethod === 'cod' 
                            ? '<i class="fas fa-money-bill-wave"></i> Payment: Cash and Delivery (₹<?php echo number_format($total, 2); ?>)' 
                            : '<i class="fas fa-check"></i> Payment Successful (UPI)';
                    }
                }, 1200);
            } else {
                throw new Error(data.message);
            }
        } catch (e) {
            alert("Order Processing Failed: " + e.message);
            document.getElementById('checkout-modal').classList.remove('active');
        }
    }
</script>

</body>
</html>
