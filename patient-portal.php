<?php
// ============================================================
// patient-portal.php - Patient Portal landing (before login)
// ============================================================
require_once __DIR__ . '/patient-auth.php';

if (pp_is_logged_in()) {
    pp_redirect(pp_dashboard_url());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal | KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand: #0F9D76;
            --brand-dark: #0B7D5E;
            --border: #E2E8F0;
            --muted: #64748B
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: 'Inter', Segoe UI, system-ui, sans-serif;
            background: linear-gradient(165deg, #F1F5F9, #E2E8F0);
            color: #0F172A
        }

        .wrap {
            max-width: 980px;
            margin: 0 auto;
            padding: 40px 18px 60px
        }

        .hero {
            text-align: center;
            margin-bottom: 32px
        }

        .hero i {
            font-size: 3rem;
            color: var(--brand)
        }

        .hero h1 {
            font-size: 1.8rem;
            margin: 12px 0 6px;
            font-weight: 800
        }

        .hero p {
            color: var(--muted);
            margin: 0
        }

        .flow {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin: 26px 0 34px
        }

        .step {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 9px 18px;
            font-size: .85rem;
            font-weight: 700;
            box-shadow: 0 6px 18px -12px rgba(15, 23, 42, .4)
        }

        .step b {
            color: var(--brand)
        }

        .arrow {
            color: var(--muted);
            font-size: .8rem
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 16px
        }

        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 14px 34px -26px rgba(15, 23, 42, .6);
            transition: .25s;
            display: block
        }

        .card:hover {
            transform: translateY(-3px);
            border-color: var(--brand);
            box-shadow: 0 18px 40px -22px rgba(15, 157, 118, .6)
        }

        .card i {
            font-size: 1.7rem;
            color: var(--brand)
        }

        .card h3 {
            font-size: 1.02rem;
            margin: 12px 0 6px;
            font-weight: 800
        }

        .card p {
            color: var(--muted);
            font-size: .86rem;
            margin: 0 0 14px
        }

        .btn {
            display: inline-block;
            background: var(--brand);
            color: #fff;
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 800;
            font-size: .85rem;
            text-decoration: none
        }

        .btn.ghost {
            background: transparent;
            color: var(--brand);
            border: 1px solid var(--brand)
        }

        .modules {
            margin-top: 40px
        }

        .modules h2 {
            font-size: 1.1rem;
            font-weight: 800
        }

        .mods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 14px
        }

        .mod {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            font-size: .86rem;
            font-weight: 700;
            color: #334155;
            text-align: center
        }

        .foot {
            text-align: center;
            margin-top: 36px;
            color: var(--muted);
            font-size: .86rem
        }

        .foot a {
            color: var(--brand);
            font-weight: 700;
            text-decoration: none
        }

        /* ── Mobile responsiveness ── */
        @media (max-width: 640px) {
            .wrap {
                padding: 24px 14px 40px;
            }
            .hero h1 {
                font-size: 1.45rem;
            }
            .hero p {
                font-size: 0.9rem;
            }
            .cards {
                grid-template-columns: 1fr;
            }
            .card .btn {
                display: block;
                text-align: center;
                width: 100%;
                min-height: 44px;
                padding: 12px;
            }
            .mods {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            html, body {
                overflow-x: hidden;
            }
            .wrap {
                padding: 18px 10px 32px;
            }
            .hero i {
                font-size: 2.3rem;
            }
            .hero h1 {
                font-size: 1.25rem;
            }
            .flow {
                gap: 6px;
                margin: 18px 0 24px;
            }
            .step {
                padding: 6px 12px;
                font-size: 0.76rem;
            }
            .arrow {
                font-size: 0.7rem;
            }
            .card {
                padding: 18px 16px;
                border-radius: 14px;
            }
            .card h3 {
                font-size: 0.95rem;
            }
            .mods {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .mod {
                padding: 10px 8px;
                font-size: 0.78rem;
            }
        }

        @media (max-width: 360px) {
            .wrap {
                padding: 14px 6px 28px;
            }
            .hero h1 {
                font-size: 1.15rem;
            }
            .mods {
                grid-template-columns: 1fr;
            }
            .flow .arrow {
                display: none;
            }
            .step {
                width: 100%;
                text-align: center;
                border-radius: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="hero">
            <i class="fas fa-heart-pulse"></i>
            <h1>KSR GURU JI &middot; Patient Portal</h1>
            <p>Appointments, consultations, prescriptions, pharmacy, lab reports &amp; payments &mdash; all in one place</p>
        </div>

        <div class="flow">
            <span class="step"><b>1</b> Register</span><span class="arrow">&rarr;</span>
            <span class="step"><b>2</b> WhatsApp OTP Verification</span><span class="arrow">&rarr;</span>
            <span class="step"><b>3</b> Account <b>ACTIVE</b></span><span class="arrow">&rarr;</span>
            <span class="step"><b>4</b> Patient Dashboard</span>
        </div>

        <div class="cards">
            <div class="card">
                <i class="fas fa-user-plus"></i>
                <h3>New Patient? Register</h3>
                <p>Full Name, DOB, Gender, Mobile, Password, Address &amp; Emergency Contact &mdash; verified instantly.</p>
                <a class="btn" href="patient-register.php"><i class="fab fa-whatsapp"></i> Register &amp; Verify</a>
            </div>
            <div class="card">
                <i class="fas fa-right-to-bracket"></i>
                <h3>Already Registered? Sign In</h3>
                <p>Login with mobile/email + password, or use a one-time WhatsApp OTP.</p>
                <a class="btn" href="patient-login.php"><i class="fas fa-unlock"></i> Patient Login</a>
            </div>
            <div class="card">
                <i class="fas fa-key"></i>
                <h3>Forgot Password</h3>
                <p>Reset your password securely with an OTP sent to your registered mobile.</p>
                <a class="btn ghost" href="patient-forgot-password.php">Reset Password</a>
            </div>
        </div>

        <div class="modules">
            <h2>After you sign in</h2>
            <div class="mods">
                <div class="mod">👤 Profile</div>
                <div class="mod">📅 Appointments</div>
                <div class="mod">🩺 Doctor Consultation</div>
                <div class="mod">💊 Prescriptions</div>
                <div class="mod">🛒 Pharmacy / Medicines</div>
                <div class="mod">🧪 Lab Reports</div>
                <div class="mod">📦 Orders</div>
                <div class="mod">💳 Payments &amp; Invoices</div>
                <div class="mod">🚚 Delivery Tracking</div>
                <div class="mod">🔔 Notifications</div>
                <div class="mod">🎧 Support</div>
            </div>
        </div>

        <div class="foot">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Back to home</a> &middot;
            <a href="WhatsApp/telehealth/index.php">Doctor Assist Platform</a>
        </div>
    </div>
</body>

</html>