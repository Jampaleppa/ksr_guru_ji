<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$ksrId   = $_SESSION['ksr_id']      ?? '@ksr_guest';
$name    = $_SESSION['display_name'] ?? 'KSR Member';
$mobile  = $_SESSION['mobile']       ?? '';
$initial = mb_substr($name, 0, 1, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>Profile | KSR GURU JI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #00a884;
            --primary-dark: #008f6f;
            --primary-soft: #e7fce3;
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
        .cover-band {
            height: 110px;
            background: linear-gradient(135deg, #00a884 0%, #0b7a62 100%);
            position: relative;
        }
        .avatar-wrap {
            position: absolute;
            bottom: -44px;
            left: 50%;
            transform: translateX(-50%);
        }
        .avatar-outer {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 3.5px solid #fff;
            background: var(--primary-soft);
            box-shadow: 0 4px 14px rgba(0,0,0,0.14);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            color: var(--primary-dark);
            font-weight: 700;
            overflow: hidden;
            cursor: pointer;
            position: relative;
        }
        .avatar-outer img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .cam-badge {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            border: 2px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
        }
        #avatarFileInput { display: none; }

        .profile-body {
            padding: 60px 16px 14px;
            text-align: center;
        }
        .display-name { font-size: 1.3rem; font-weight: 800; color: var(--text-main); margin-bottom: 2px; }
        .ksr-id-line { font-size: 0.88rem; color: var(--text-muted); margin-bottom: 4px; }
        .about-line { font-size: 0.9rem; color: var(--text-muted); font-style: italic; margin-bottom: 14px; }

        .action-row { display: flex; justify-content: center; gap: 14px; margin-bottom: 20px; }
        .pill-btn {
            border: 1.5px solid var(--border);
            background: #fff;
            border-radius: 999px;
            padding: 9px 18px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            color: var(--primary-dark);
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 40px;
        }
        .pill-btn:active { background: var(--primary-soft); }

        .section-card {
            background: #fff;
            border-radius: 16px;
            margin-bottom: 12px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .section-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px 10px;
        }
        .section-card-head h3 { font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .shortcut-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 13px 16px;
            border-top: 1px solid #f4f6f8;
            text-decoration: none;
            color: var(--text-main);
        }
        .shortcut-row:active { background: #f5f6f6; }
        .shortcut-ico {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .shortcut-label { flex: 1; font-size: 0.95rem; font-weight: 600; }
        .shortcut-arrow { font-size: 0.75rem; color: var(--text-muted); }
        .shortcut-row.logout-row { color: #d93025; }

        /* Modal shared styles */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(17,27,33,0.52);
            z-index: 300;
            align-items: flex-end;
            justify-content: center;
            padding: 12px;
        }
        .modal-backdrop.open { display: flex; }
        .modal-sheet {
            width: 100%;
            max-width: 540px;
            background: #fff;
            border-radius: 20px;
            padding: 22px 20px calc(22px + env(safe-area-inset-bottom, 0px));
            box-shadow: 0 10px 32px rgba(0,0,0,0.18);
            animation: slideUp 0.22s ease;
        }
        @keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: none; opacity: 1; } }
        .modal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .modal-head h2 { font-size: 1.1rem; font-weight: 800; }
        .close-btn { background: var(--bg-app); border: none; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; }
        .field-label { font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; }
        .field-input {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            font-size: 16px;
            font-family: inherit;
            margin-bottom: 14px;
            outline: none;
        }
        .field-input:focus { border-color: var(--primary); }
        .primary-btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            padding: 13px;
            cursor: pointer;
            min-height: 48px;
        }
        .primary-btn:active { background: var(--primary-dark); }
        .danger-btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            background: #d93025;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            padding: 13px;
            cursor: pointer;
            margin-top: 10px;
            min-height: 48px;
        }
        .ghost-btn {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            background: #fff;
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 600;
            padding: 12px;
            cursor: pointer;
            margin-top: 10px;
        }

        /* QR Code visual */
        .qr-box {
            width: 190px;
            height: 190px;
            margin: 16px auto;
            background: #fff;
            border: 2px solid var(--border);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            text-align: center;
            padding: 12px;
        }
        .qr-label { text-align: center; font-size: 0.88rem; color: var(--text-muted); margin-bottom: 14px; }
        .copy-id-btn {
            width: 100%;
            border: 1.5px solid var(--primary);
            border-radius: 12px;
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 0.95rem;
            font-weight: 700;
            padding: 12px;
            cursor: pointer;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Bottom Nav */
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

        .toast {
            position: fixed;
            bottom: calc(74px + env(safe-area-inset-bottom, 0px));
            left: 50%;
            transform: translateX(-50%);
            background: #111b21;
            color: #fff;
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 0.85rem;
            display: none;
            z-index: 500;
            white-space: nowrap;
        }
        .toast.show { display: block; }
    </style>
</head>
<body>

<div class="cover-band">
    <div class="avatar-wrap">
        <div class="avatar-outer" id="avatarOuter" onclick="document.getElementById('avatarFileInput').click()" role="button" aria-label="Change profile photo" tabindex="0">
            <span id="avatarInitial"><?php echo htmlspecialchars($initial); ?></span>
            <div class="cam-badge"><i class="fas fa-camera"></i></div>
        </div>
        <input type="file" id="avatarFileInput" accept="image/*" aria-label="Upload profile photo">
    </div>
</div>

<div class="profile-body">
    <div class="display-name" id="profileName"><?php echo htmlspecialchars($name); ?></div>
    <div class="ksr-id-line" id="profileKsrId"><?php echo htmlspecialchars($ksrId); ?></div>
    <div class="about-line" id="profileAbout">KSR GURU JI Member &mdash; Hubballi, Karnataka 🌿</div>

    <div class="action-row">
        <button type="button" class="pill-btn" onclick="openEditModal()">
            <i class="fas fa-pen"></i> Edit Profile
        </button>
        <button type="button" class="pill-btn" onclick="openQrModal()">
            <i class="fas fa-qrcode"></i> QR Code
        </button>
    </div>

    <!-- Quick Shortcuts -->
    <div class="section-card">
        <div class="section-card-head"><h3>Quick Links</h3></div>
        <a class="shortcut-row" href="../../orders.php">
            <div class="shortcut-ico" style="background:#fef9c3;">📋</div>
            <span class="shortcut-label">My Orders</span>
            <i class="fas fa-chevron-right shortcut-arrow"></i>
        </a>
        <a class="shortcut-row" href="../../payment/history.php">
            <div class="shortcut-ico" style="background:#dbeafe;">💳</div>
            <span class="shortcut-label">Payment History</span>
            <i class="fas fa-chevron-right shortcut-arrow"></i>
        </a>
        <a class="shortcut-row" href="../../telehealth/index.php">
            <div class="shortcut-ico" style="background:#dcfce7;">🩺</div>
            <span class="shortcut-label">Telehealth</span>
            <i class="fas fa-chevron-right shortcut-arrow"></i>
        </a>
        <a class="shortcut-row" href="../../index.php">
            <div class="shortcut-ico" style="background:#e7fce3;">🛒</div>
            <span class="shortcut-label">Main Store</span>
            <i class="fas fa-chevron-right shortcut-arrow"></i>
        </a>
        <a class="shortcut-row" href="https://wa.me/918088584421" target="_blank" rel="noopener">
            <div class="shortcut-ico" style="background:#dcfce7;">💬</div>
            <span class="shortcut-label">WhatsApp Support</span>
            <i class="fas fa-chevron-right shortcut-arrow"></i>
        </a>
    </div>

    <!-- Account Actions -->
    <div class="section-card">
        <div class="section-card-head"><h3>Account</h3></div>
        <button type="button" class="shortcut-row logout-row" onclick="openLogoutModal()">
            <div class="shortcut-ico" style="background:#fce7e7;">🚪</div>
            <span class="shortcut-label">Logout</span>
            <i class="fas fa-chevron-right shortcut-arrow"></i>
        </button>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal-backdrop" id="editModal" role="dialog" aria-modal="true" aria-label="Edit profile">
    <div class="modal-sheet">
        <div class="modal-head">
            <h2>Edit Profile</h2>
            <button type="button" class="close-btn" onclick="closeModal('editModal')" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <label class="field-label" for="editName">Display Name</label>
        <input type="text" id="editName" class="field-input" maxlength="40" placeholder="Your name" autocomplete="name">
        <label class="field-label" for="editAbout">About</label>
        <input type="text" id="editAbout" class="field-input" maxlength="80" placeholder="Short bio or status">
        <button type="button" class="primary-btn" onclick="saveProfile()">Save Changes</button>
        <button type="button" class="ghost-btn" onclick="closeModal('editModal')">Cancel</button>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal-backdrop" id="qrModal" role="dialog" aria-modal="true" aria-label="Your QR code">
    <div class="modal-sheet">
        <div class="modal-head">
            <h2>Share Profile</h2>
            <button type="button" class="close-btn" onclick="closeModal('qrModal')" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="qr-box" id="qrBox" aria-label="QR code placeholder">
            <!-- SVG QR placeholder -->
            <svg width="150" height="150" viewBox="0 0 150 150" fill="none" aria-hidden="true">
                <rect x="10" y="10" width="40" height="40" rx="4" fill="none" stroke="#111b21" stroke-width="6"/>
                <rect x="20" y="20" width="20" height="20" rx="2" fill="#111b21"/>
                <rect x="100" y="10" width="40" height="40" rx="4" fill="none" stroke="#111b21" stroke-width="6"/>
                <rect x="110" y="20" width="20" height="20" rx="2" fill="#111b21"/>
                <rect x="10" y="100" width="40" height="40" rx="4" fill="none" stroke="#111b21" stroke-width="6"/>
                <rect x="20" y="110" width="20" height="20" rx="2" fill="#111b21"/>
                <text x="75" y="85" text-anchor="middle" font-size="10" fill="#667781">Scan for</text>
                <text x="75" y="98" text-anchor="middle" font-size="9" fill="#667781" id="qrIdLabel"></text>
            </svg>
        </div>
        <div class="qr-label" id="qrLabel"></div>
        <button type="button" class="copy-id-btn" onclick="copyKsrId()">
            <i class="fas fa-copy"></i> Copy KSR ID
        </button>
        <button type="button" class="ghost-btn" onclick="closeModal('qrModal')">Close</button>
    </div>
</div>

<!-- Logout Confirm Modal -->
<div class="modal-backdrop" id="logoutModal" role="dialog" aria-modal="true" aria-label="Confirm logout">
    <div class="modal-sheet">
        <div class="modal-head">
            <h2>Logout?</h2>
            <button type="button" class="close-btn" onclick="closeModal('logoutModal')" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <p style="font-size:0.95rem;color:var(--text-muted);margin-bottom:18px;">You'll need to sign in again to access your chats and account.</p>
        <a href="index.php?action=logout" class="danger-btn" style="display:block;text-align:center;text-decoration:none;">
            Logout
        </a>
        <button type="button" class="ghost-btn" onclick="closeModal('logoutModal')">Stay Logged In</button>
    </div>
</div>

<nav class="bottom-nav" aria-label="WhatsApp navigation">
    <a class="bnav-item" href="chats.php"><span class="ico">💬</span><span>Chats</span></a>
    <a class="bnav-item" href="updates.php"><span class="ico">🔄</span><span>Updates</span></a>
    <a class="bnav-item" href="calls.php"><span class="ico">📞</span><span>Calls</span></a>
    <a class="bnav-item" href="services.php"><span class="ico">🛍️</span><span>Services</span></a>
    <a class="bnav-item active" href="profile.php"><span class="ico">👤</span><span>Profile</span></a>
</nav>

<div class="toast" id="toastBar"></div>

<script>
const LS_PROFILE = 'ksr_wa_profile_v1';

function showToast(msg) {
    const t = document.getElementById('toastBar');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2600);
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-backdrop').forEach((el) => {
    el.addEventListener('click', (e) => { if (e.target === el) el.classList.remove('open'); });
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.open').forEach((el) => el.classList.remove('open'));
    }
});

function openEditModal() {
    document.getElementById('editName').value = document.getElementById('profileName').textContent;
    document.getElementById('editAbout').value = document.getElementById('profileAbout').textContent.replace(/\u2014.*$/, '').trim();
    openModal('editModal');
    setTimeout(() => document.getElementById('editName').focus(), 100);
}

function saveProfile() {
    const newName = document.getElementById('editName').value.trim();
    const newAbout = document.getElementById('editAbout').value.trim();
    if (!newName) { showToast('Name cannot be empty'); return; }
    document.getElementById('profileName').textContent = newName;
    document.getElementById('avatarInitial').textContent = newName.charAt(0);
    if (newAbout) {
        document.getElementById('profileAbout').textContent = newAbout;
    }
    try {
        const saved = JSON.parse(localStorage.getItem(LS_PROFILE) || '{}');
        saved.name = newName;
        saved.about = newAbout;
        localStorage.setItem(LS_PROFILE, JSON.stringify(saved));
    } catch (_) {}
    closeModal('editModal');
    showToast('Profile updated');
}

function openQrModal() {
    const ksrId = document.getElementById('profileKsrId').textContent;
    document.getElementById('qrIdLabel').textContent = ksrId;
    document.getElementById('qrLabel').textContent = ksrId;
    openModal('qrModal');
}

function copyKsrId() {
    const ksrId = document.getElementById('profileKsrId').textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(ksrId).then(() => showToast('KSR ID copied!')).catch(() => fallbackCopy(ksrId));
    } else {
        fallbackCopy(ksrId);
    }
}

function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); showToast('KSR ID copied!'); } catch (_) { showToast('Copy not supported'); }
    document.body.removeChild(ta);
}

function openLogoutModal() { openModal('logoutModal'); }

// Avatar file picker
document.getElementById('avatarFileInput').addEventListener('change', (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) { showToast('Please pick an image file'); return; }
    const reader = new FileReader();
    reader.onload = (ev) => {
        const outer = document.getElementById('avatarOuter');
        let img = outer.querySelector('img');
        if (!img) {
            img = document.createElement('img');
            img.alt = 'Profile photo';
        }
        img.src = ev.target.result;
        const initial = document.getElementById('avatarInitial');
        if (initial) initial.style.display = 'none';
        outer.insertBefore(img, outer.firstChild);
        showToast('Profile photo updated');
    };
    reader.readAsDataURL(file);
});

// Keyboard on avatar
document.getElementById('avatarOuter').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        document.getElementById('avatarFileInput').click();
    }
});

// Restore saved profile from localStorage
(function restoreProfile() {
    try {
        const saved = JSON.parse(localStorage.getItem(LS_PROFILE) || '{}');
        if (saved.name) {
            document.getElementById('profileName').textContent = saved.name;
            document.getElementById('avatarInitial').textContent = saved.name.charAt(0);
        }
        if (saved.about) {
            document.getElementById('profileAbout').textContent = saved.about;
        }
    } catch (_) {}
})();
</script>
</body>
</html>
