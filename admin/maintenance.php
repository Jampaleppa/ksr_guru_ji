<?php
// ============================================================
// admin/maintenance.php - System maintenance
// Access: any authenticated admin (Super Admin included)
// ============================================================
$currentPage = 'maintenance';
require_once __DIR__ . '/inc/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Maintenance Mode | KSR GURU Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --sidebar-bg: #0f172a;
            --sidebar-text: #e2e8f0;
            --sidebar-active: #3b82f6;
            --main-bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --accent: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #8b5cf6;
            --shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.02);
        }

        body.dark {
            --sidebar-bg: #020617;
            --main-bg: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --border-light: #334155;
            --shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--main-bg);
            color: var(--text-primary);
            transition: background 0.3s, color 0.2s;
            overflow-x: hidden;
        }

        /* Sidebar (reused from dashboard) */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .sidebar.collapsed { width: 85px; }
        .sidebar.collapsed .admin-logo div, .sidebar.collapsed .profile-info,
        .sidebar.collapsed .nav-item span, .sidebar.collapsed .nav-section h4,
        .sidebar.collapsed .system-status .status-text, .sidebar.collapsed .logout-btn span { display: none; }
        .sidebar-header { padding: 24px 20px; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .admin-logo { display: flex; align-items: center; gap: 12px; color: white; }
        .admin-logo i { font-size: 28px; color: var(--accent); }
        .admin-logo h2 { font-size: 1.35rem; font-weight: 700; }
        .sidebar-toggle { background: none; border: none; color: white; font-size: 1.3rem; cursor: pointer; }
        .admin-profile { padding: 24px 20px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .profile-image i { font-size: 48px; color: #94a3b8; }
        .profile-info h3 { font-size: 1rem; color: white; }
        .profile-info p { font-size: 0.75rem; color: #94a3b8; }
        .status-indicator { width: 10px; height: 10px; background: #10b981; border-radius: 50%; position: absolute; right: 20px; top: 45px; }
        .sidebar-nav { flex: 1; padding: 20px 0; }
        .nav-section { margin-bottom: 24px; }
        .nav-section h4 { padding: 0 20px; font-size: 0.7rem; color: #64748b; margin-bottom: 12px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 10px 20px; color: var(--sidebar-text); text-decoration: none; transition: 0.2s; }
        .nav-item i { width: 24px; }
        .nav-item .badge { background: #334155; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; margin-left: auto; }
        .nav-item.active { background: rgba(59,130,246,0.2); border-left: 3px solid var(--accent); color: white; }
        .nav-item:hover:not(.active) { background: rgba(255,255,255,0.05); }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.08); }
        .status-item { display: flex; justify-content: space-between; font-size: 0.75rem; color: #94a3b8; margin-bottom: 8px; }
        .logout-btn { width: 100%; background: rgba(239,68,68,0.2); border: none; padding: 10px; border-radius: 12px; color: #f87171; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; }

        /* Main content */
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            padding: 20px 28px;
        }
        .sidebar.collapsed ~ .main-content { margin-left: 85px; }

        .top-header {
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 28px;
        }
        .header-left h1 { font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .breadcrumb { color: var(--text-secondary); font-size: 0.85rem; }
        .header-right { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
        .search-box { background: var(--card-bg); border-radius: 40px; padding: 8px 16px; display: flex; align-items: center; gap: 8px; box-shadow: var(--shadow); }
        .search-box input { background: transparent; border: none; outline: none; color: var(--text-primary); }
        .header-actions { display: flex; gap: 12px; }
        .header-btn { background: var(--card-bg); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; color: var(--text-primary); position: relative; box-shadow: var(--shadow); }
        .notification-count { position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border-radius: 20px; font-size: 0.7rem; padding: 0 5px; }
        .user-menu { display: flex; align-items: center; gap: 10px; background: var(--card-bg); padding: 6px 16px; border-radius: 50px; cursor: pointer; }

        /* Maintenance specific styles */
        .maintenance-toggle-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: var(--shadow);
        }
        .toggle-status {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .maintenance-badge {
            padding: 8px 16px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .maintenance-badge.enabled { background: #fee2e2; color: #b91c1c; }
        .maintenance-badge.disabled { background: #d1fae5; color: #065f46; }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 30px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: var(--danger);
        }
        input:checked + .slider:before {
            transform: translateX(30px);
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        .settings-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-light);
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border-radius: 12px;
            border: 1px solid var(--border-light);
            background: var(--main-bg);
            color: var(--text-primary);
        }
        .btn {
            padding: 8px 20px;
            border-radius: 40px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-light);
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .preview-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 20px;
            margin-top: 24px;
            box-shadow: var(--shadow);
        }
        .maintenance-preview {
            background: #f9fafb;
            border-radius: 16px;
            padding: 32px;
            text-align: center;
            border: 1px solid var(--border-light);
        }
        .ip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .ip-tag {
            background: var(--gray-100);
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .ip-tag button {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--danger);
        }
        .add-ip {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        .add-ip input {
            flex: 1;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
            }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
        }
    </style>
    <link rel="stylesheet" href="css/admin-theme.css?v=1.6">
    <script src="js/admin-theme.js?v=1.6" defer></script>
</head>
<body>
    <!-- ===== SIDEBAR ===== -->
    <?php $currentPage = 'maintenance'; include 'inc/sidebar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <div class="header-left"><h1><i class="fas fa-tools"></i> Maintenance Mode</h1><div class="breadcrumb"><span>System</span><i class="fas fa-chevron-right"></i><span class="active">Maintenance</span></div></div>
            <div class="header-right">
                <div class="search-box"><i class="fas fa-search"></i><input type="text" placeholder="Search..."></div>
                <div class="header-actions"><button class="header-btn notification-btn"><i class="fas fa-bell"></i><span class="notification-count">5</span></button><button class="header-btn theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button><button class="header-btn fullscreen-btn" id="fullscreenBtn"><i class="fas fa-expand"></i></button></div>
                <div class="user-menu"><div class="user-avatar"><i class="fas fa-user-circle"></i></div><div class="user-info"><span class="user-name">Admin User</span><span class="user-role">Super Admin</span></div><i class="fas fa-chevron-down"></i></div>
            </div>
        </header>

        <!-- Main Toggle Card -->
        <div class="maintenance-toggle-card">
            <div class="toggle-status">
                <h2>Maintenance Mode</h2>
                <span class="maintenance-badge" id="modeBadge">Disabled</span>
            </div>
            <div>
                <label class="toggle-switch">
                    <input type="checkbox" id="maintenanceToggle">
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="settings-grid">
            <!-- Message Settings -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-envelope-open-text"></i> Maintenance Message</div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" id="maintenanceTitle" placeholder="e.g., We'll be back soon!">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea id="maintenanceMessage" rows="3" placeholder="Explain why the site is down and when it will be back..."></textarea>
                </div>
                <div class="form-group">
                    <label>Estimated Return Time</label>
                    <input type="text" id="eta" placeholder="e.g., 2 hours, Tomorrow 10:00 AM">
                </div>
                <div class="form-group">
                    <label>Contact Email (optional)</label>
                    <input type="email" id="contactEmail" placeholder="support@example.com">
                </div>
            </div>

            <!-- Schedule Maintenance -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-calendar-alt"></i> Schedule Maintenance</div>
                <div class="form-group">
                    <label>Start Date & Time</label>
                    <input type="datetime-local" id="scheduleStart">
                </div>
                <div class="form-group">
                    <label>End Date & Time</label>
                    <input type="datetime-local" id="scheduleEnd">
                </div>
                <button class="btn btn-primary" id="scheduleBtn">Schedule Maintenance</button>
                <div class="form-group" style="margin-top: 16px;">
                    <label>Current Schedule</label>
                    <div id="scheduleInfo" style="font-size: 0.875rem; color: var(--text-secondary);">No scheduled maintenance</div>
                </div>
            </div>

            <!-- Whitelist IPs -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-shield-alt"></i> IP Whitelist</div>
                <p style="font-size: 0.875rem; margin-bottom: 12px;">Users from these IPs can access the site even during maintenance.</p>
                <div id="ipListContainer" class="ip-list"></div>
                <div class="add-ip">
                    <input type="text" id="newIp" placeholder="e.g., 192.168.1.1 or 10.0.0.0/24">
                    <button class="btn btn-outline" id="addIpBtn">Add IP</button>
                </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="preview-card">
            <div class="card-title"><i class="fas fa-eye"></i> Preview (What visitors will see)</div>
            <div id="previewContainer" class="maintenance-preview">
                <!-- Preview content will be dynamically inserted -->
            </div>
        </div>
    </main>

    <script>
        // ---------- Load/Save State ----------
        let maintenanceState = {
            enabled: false,
            title: "Under Maintenance",
            message: "We are currently performing scheduled maintenance. Please check back soon!",
            eta: "Coming back shortly",
            contactEmail: "",
            schedule: null, // { start, end }
            whitelist: []   // array of IP strings
        };

        function loadState() {
            const saved = localStorage.getItem('ksr_maintenance');
            if (saved) {
                maintenanceState = JSON.parse(saved);
            } else {
                // default values
                maintenanceState.title = "Under Maintenance";
                maintenanceState.message = "We are currently performing scheduled maintenance. Please check back soon!";
                maintenanceState.eta = "Coming back shortly";
                maintenanceState.whitelist = [];
            }
            updateUI();
        }

        function saveState() {
            localStorage.setItem('ksr_maintenance', JSON.stringify(maintenanceState));
            updateUI();
        }

        function updateUI() {
            // Update toggle switch
            document.getElementById('maintenanceToggle').checked = maintenanceState.enabled;
            document.getElementById('modeBadge').innerText = maintenanceState.enabled ? "Enabled" : "Disabled";
            document.getElementById('modeBadge').className = `maintenance-badge ${maintenanceState.enabled ? 'enabled' : 'disabled'}`;
            document.getElementById('sideStatus').innerText = maintenanceState.enabled ? "On" : "Off";

            // Update form fields
            document.getElementById('maintenanceTitle').value = maintenanceState.title || "";
            document.getElementById('maintenanceMessage').value = maintenanceState.message || "";
            document.getElementById('eta').value = maintenanceState.eta || "";
            document.getElementById('contactEmail').value = maintenanceState.contactEmail || "";

            // Schedule display
            if (maintenanceState.schedule && maintenanceState.schedule.start && maintenanceState.schedule.end) {
                const startDate = new Date(maintenanceState.schedule.start);
                const endDate = new Date(maintenanceState.schedule.end);
                document.getElementById('scheduleInfo').innerHTML = `From ${startDate.toLocaleString()} to ${endDate.toLocaleString()}<br><button class="btn btn-outline btn-sm" id="clearScheduleBtn" style="margin-top: 8px; padding: 4px 12px;">Clear Schedule</button>`;
                const clearBtn = document.getElementById('clearScheduleBtn');
                if (clearBtn) clearBtn.onclick = () => { maintenanceState.schedule = null; saveState(); };
            } else {
                document.getElementById('scheduleInfo').innerHTML = 'No scheduled maintenance';
            }

            // IP whitelist
            const ipContainer = document.getElementById('ipListContainer');
            ipContainer.innerHTML = maintenanceState.whitelist.map(ip => `
                <div class="ip-tag">
                    ${ip}
                    <button class="remove-ip" data-ip="${ip}"><i class="fas fa-times"></i></button>
                </div>
            `).join('');
            document.querySelectorAll('.remove-ip').forEach(btn => {
                btn.addEventListener('click', () => {
                    const ip = btn.dataset.ip;
                    maintenanceState.whitelist = maintenanceState.whitelist.filter(i => i !== ip);
                    saveState();
                });
            });

            // Update preview
            renderPreview();
        }

        function renderPreview() {
            const previewDiv = document.getElementById('previewContainer');
            const title = maintenanceState.title || "Under Maintenance";
            const message = maintenanceState.message || "We are currently performing maintenance.";
            const eta = maintenanceState.eta ? `<p><i class="fas fa-hourglass-half"></i> Estimated return: ${maintenanceState.eta}</p>` : "";
            const email = maintenanceState.contactEmail ? `<p><i class="fas fa-envelope"></i> Contact: ${maintenanceState.contactEmail}</p>` : "";
            previewDiv.innerHTML = `
                <div style="max-width: 500px; margin: 0 auto;">
                    <i class="fas fa-tools" style="font-size: 4rem; color: var(--warning); margin-bottom: 1rem;"></i>
                    <h2>${escapeHtml(title)}</h2>
                    <p>${escapeHtml(message)}</p>
                    ${eta}
                    ${email}
                </div>
            `;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // Event listeners
        document.getElementById('maintenanceToggle').addEventListener('change', (e) => {
            maintenanceState.enabled = e.target.checked;
            saveState();
        });

        document.getElementById('scheduleBtn').addEventListener('click', () => {
            const start = document.getElementById('scheduleStart').value;
            const end = document.getElementById('scheduleEnd').value;
            if (start && end) {
                maintenanceState.schedule = { start, end };
                saveState();
                document.getElementById('scheduleStart').value = '';
                document.getElementById('scheduleEnd').value = '';
            } else {
                alert('Please set both start and end times.');
            }
        });

        document.getElementById('addIpBtn').addEventListener('click', () => {
            const ip = document.getElementById('newIp').value.trim();
            if (ip && !maintenanceState.whitelist.includes(ip)) {
                maintenanceState.whitelist.push(ip);
                saveState();
                document.getElementById('newIp').value = '';
            } else if (maintenanceState.whitelist.includes(ip)) {
                alert('IP already in whitelist.');
            }
        });

        // Save message fields on change (debounced or on blur)
        const saveMessageFields = () => {
            maintenanceState.title = document.getElementById('maintenanceTitle').value;
            maintenanceState.message = document.getElementById('maintenanceMessage').value;
            maintenanceState.eta = document.getElementById('eta').value;
            maintenanceState.contactEmail = document.getElementById('contactEmail').value;
            saveState();
        };

        document.getElementById('maintenanceTitle').addEventListener('change', saveMessageFields);
        document.getElementById('maintenanceMessage').addEventListener('change', saveMessageFields);
        document.getElementById('eta').addEventListener('change', saveMessageFields);
        document.getElementById('contactEmail').addEventListener('change', saveMessageFields);

        // Auto-save on input (optional, use blur to reduce saves)
        const autoSaveFields = ['maintenanceTitle', 'maintenanceMessage', 'eta', 'contactEmail'];
        autoSaveFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('blur', saveMessageFields);
        });

        // Theme toggle, sidebar, fullscreen (basic)
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark');
            const icon = themeToggle.querySelector('i');
            if (document.body.classList.contains('dark')) icon.classList.replace('fa-moon', 'fa-sun');
            else icon.classList.replace('fa-sun', 'fa-moon');
        });
        const sidebarToggle = document.getElementById('sidebarToggle');
        sidebarToggle.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
        document.getElementById('fullscreenBtn').addEventListener('click', () => {
            if (!document.fullscreenElement) document.documentElement.requestFullscreen();
            else document.exitFullscreen();
        });
        document.querySelector('.logout-btn').addEventListener('click', () => alert('Logged out (demo)'));

        // Initialize
        loadState();
    </script>
</body>
</html>