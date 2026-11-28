<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>KSR Services Hub | KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00a884;
            --primary-dark: #008f6f;
            --bg-app: #f0f2f5;
            --bg-screen: #ffffff;
            --text-main: #111b21;
            --text-muted: #667781;
            --border: #e9edef;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-app);
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: calc(76px + env(safe-area-inset-bottom, 0px));
        }
        .app-shell {
            max-width: 760px;
            margin: 0 auto;
            min-height: 100vh;
            background: var(--bg-app);
        }
        .app-header {
            background: var(--primary);
            color: #fff;
            padding: 13px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }
        .app-header h1 { font-size: 1.2rem; font-weight: 700; }
        .header-pill {
            background: rgba(255,255,255,0.18);
            color: #fff;
            text-decoration: none;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .content { padding: 14px; }

        .hero-card {
            background: linear-gradient(135deg, #00a884 0%, #0b7a62 100%);
            color: #fff;
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 14px;
            box-shadow: 0 6px 20px rgba(0,168,132,0.22);
        }
        .hero-card h2 { font-size: 1.15rem; margin-bottom: 6px; }
        .hero-card p { font-size: 0.88rem; opacity: 0.92; line-height: 1.45; margin-bottom: 14px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .hero-btn {
            background: #fff;
            color: #006c55;
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .hero-btn.secondary {
            background: rgba(255,255,255,0.16);
            color: #fff;
        }

        .search-bar {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .search-bar i { color: var(--text-muted); }
        .search-bar input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 16px;
            color: var(--text-main);
        }

        .group-label {
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin: 14px 4px 10px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        @media (min-width: 640px) {
            .services-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }

        .svc-card {
            background: #fff;
            border-radius: 16px;
            padding: 15px 13px;
            text-decoration: none;
            color: var(--text-main);
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .svc-card:active { transform: scale(0.97); }
        .svc-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        .svc-title { font-size: 0.95rem; font-weight: 700; line-height: 1.25; }
        .svc-desc { font-size: 0.78rem; color: var(--text-muted); line-height: 1.35; }
        .svc-tag {
            margin-top: auto;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .empty-state {
            display: none;
            text-align: center;
            padding: 28px 16px;
            color: var(--text-muted);
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: calc(62px + env(safe-area-inset-bottom, 0px));
            padding-bottom: env(safe-area-inset-bottom, 0px);
            background: var(--bg-screen);
            display: flex;
            justify-content: space-around;
            align-items: center;
            border-top: 1px solid var(--border);
            z-index: 100;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        .bnav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 500;
            gap: 3px;
            min-width: 58px;
            min-height: 46px;
        }
        .bnav-item .ico { font-size: 1.25rem; line-height: 1; }
        .bnav-item.active { color: var(--primary-dark); font-weight: 700; }
    </style>
</head>
<body>

<div class="app-shell">
    <header class="app-header">
        <h1>🛍️ Services</h1>
        <a href="../../index.php" class="header-pill"><i class="fas fa-store"></i> Main Store</a>
    </header>

    <div class="content">
        <div class="hero-card">
            <h2>KSR GURU JI Super Hub</h2>
            <p>Access Telehealth doctors, medicines, fresh groceries, B2B stationery, KSR PAY receipts, and parcel booking from one mobile screen.</p>
            <div class="hero-actions">
                <a href="../../telehealth/appointment.php" class="hero-btn"><i class="fas fa-calendar-check"></i> Book Doctor</a>
                <a href="../../index.php" class="hero-btn secondary"><i class="fas fa-shopping-bag"></i> Shop Items</a>
            </div>
        </div>

        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="search" id="serviceSearch" placeholder="Search services (telehealth, stationery, pay...)" oninput="filterServices()">
        </div>

        <div class="group-label">Healthcare &amp; Clinic</div>
        <div class="services-grid">
            <a class="svc-card svc-item" href="../../telehealth/index.php" data-search="telehealth quick book doctor consult clinic">
                <div class="svc-icon" style="background:#e0f2fe;">🩺</div>
                <div class="svc-title">Telehealth Hub</div>
                <div class="svc-desc">Video, audio &amp; clinic appointments</div>
                <div class="svc-tag">Book in 60s →</div>
            </a>

            <a class="svc-card svc-item" href="../telehealth/index.php" data-search="doctor assist portal intake patient">
                <div class="svc-icon" style="background:#dcfce7;">🏥</div>
                <div class="svc-title">Doctor Assist</div>
                <div class="svc-desc">Patient dashboard &amp; medical portal</div>
                <div class="svc-tag">Open Portal →</div>
            </a>

            <a class="svc-card svc-item" href="../telehealth/medicine/tablet.php" data-search="pharmacy medicine tablets health care">
                <div class="svc-icon" style="background:#fae8ff;">💊</div>
                <div class="svc-title">e-Pharmacy</div>
                <div class="svc-desc">Tablets, wellness &amp; essentials</div>
                <div class="svc-tag">Browse Meds →</div>
            </a>

            <a class="svc-card svc-item" href="../../telehealth/doctors.php" data-search="doctors specialist shivukumar rathod fees">
                <div class="svc-icon" style="background:#fef3c7;">👨‍⚕️</div>
                <div class="svc-title">Verified Doctors</div>
                <div class="svc-desc">View doctor profiles &amp; OPD fees</div>
                <div class="svc-tag">View Doctors →</div>
            </a>
        </div>

        <div class="group-label">Store, B2B &amp; Payments</div>
        <div class="services-grid">
            <a class="svc-card svc-item" href="../../index.php" data-search="shopping grocery vegetables fruits dairy store">
                <div class="svc-icon" style="background:#e7fce3;">🥬</div>
                <div class="svc-title">KSR Grocery Store</div>
                <div class="svc-desc">Fresh produce &amp; daily pantry items</div>
                <div class="svc-tag">Shop Now →</div>
            </a>

            <a class="svc-card svc-item" href="../../categories/stationery.php" data-search="stationery b2b paper lamination ink band paper">
                <div class="svc-icon" style="background:#ede9fe;">🖨️</div>
                <div class="svc-title">Stationery &amp; B2B</div>
                <div class="svc-desc">B2B paper, lamination, ink &amp; band paper</div>
                <div class="svc-tag">Order Stock →</div>
            </a>

            <a class="svc-card svc-item" href="../../payment/history.php" data-search="payments ksr pay history upi bank receipt">
                <div class="svc-icon" style="background:#dbeafe;">💳</div>
                <div class="svc-title">KSR PAY &amp; Receipts</div>
                <div class="svc-desc">UPI, bank transfer &amp; COD receipts</div>
                <div class="svc-tag">View Payments →</div>
            </a>

            <a class="svc-card svc-item" href="../../orders.php" data-search="orders track status delivery history">
                <div class="svc-icon" style="background:#ffedd5;">📋</div>
                <div class="svc-title">My Orders</div>
                <div class="svc-desc">Track active &amp; past deliveries</div>
                <div class="svc-tag">Track Orders →</div>
            </a>

            <a class="svc-card svc-item" href="../../categories/parcels.php" data-search="parcel delivery courier dispatch booking">
                <div class="svc-icon" style="background:#f1f5f9;">📦</div>
                <div class="svc-title">Parcel Hub</div>
                <div class="svc-desc">Book fast local &amp; statewide courier</div>
                <div class="svc-tag">Send Parcel →</div>
            </a>
        </div>

        <div class="group-label">Support &amp; Management</div>
        <div class="services-grid">
            <a class="svc-card svc-item" href="https://wa.me/918088584421" target="_blank" rel="noopener" data-search="whatsapp support help desk chat">
                <div class="svc-icon" style="background:#dcfce7;">💬</div>
                <div class="svc-title">WhatsApp Helpdesk</div>
                <div class="svc-desc">Direct chat with KSR support desk</div>
                <div class="svc-tag">+91 80885 84421 →</div>
            </a>

            <a class="svc-card svc-item" href="../../suggestion-box.php" data-search="suggestion feedback request item">
                <div class="svc-icon" style="background:#fef9c3;">💡</div>
                <div class="svc-title">Suggestion Box</div>
                <div class="svc-desc">Request items or send feedback</div>
                <div class="svc-tag">Submit Idea →</div>
            </a>

            <a class="svc-card svc-item" href="../admin/index.php" data-search="admin dashboard control panel business">
                <div class="svc-icon" style="background:#e2e8f0;">⚙️</div>
                <div class="svc-title">WhatsApp Admin</div>
                <div class="svc-desc">Messaging metrics &amp; store links</div>
                <div class="svc-tag">Open Console →</div>
            </a>
        </div>

        <div class="empty-state" id="emptyState">No services match your search.</div>
    </div>
</div>

<nav class="bottom-nav" aria-label="WhatsApp navigation">
    <a class="bnav-item" href="chats.php"><span class="ico">💬</span><span>Chats</span></a>
    <a class="bnav-item" href="updates.php"><span class="ico">🔄</span><span>Updates</span></a>
    <a class="bnav-item" href="calls.php"><span class="ico">📞</span><span>Calls</span></a>
    <a class="bnav-item active" href="services.php"><span class="ico">🛍️</span><span>Services</span></a>
    <a class="bnav-item" href="profile.php"><span class="ico">👤</span><span>Profile</span></a>
</nav>

<script>
function filterServices() {
    const q = document.getElementById('serviceSearch').value.trim().toLowerCase();
    let count = 0;
    document.querySelectorAll('.svc-item').forEach((card) => {
        const hay = (card.dataset.search || card.textContent).toLowerCase();
        const match = !q || hay.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) count++;
    });
    document.getElementById('emptyState').style.display = count === 0 ? 'block' : 'none';
}
</script>
</body>
</html>
