<?php
require_once 'helper.php';

// Mock Patient State for Inbox
$patientName = 'Jampaleppa Rathod';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #F8FAFC;
        }

        .dash-layout {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 16px;
            margin-bottom: 100px;
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 32px;
        }

        .page-header {
            grid-column: 1 / -1;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--clr-primary-dark);
            margin-bottom: 8px;
        }

        .page-sub {
            color: var(--clr-text-secondary);
            font-size: 1.05rem;
        }

        .opt-actions {
            display: flex;
            gap: 12px;
        }

        .btn-opt {
            background: white;
            border: 1px solid var(--clr-border);
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            color: var(--clr-text-secondary);
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-opt:hover {
            border-color: var(--clr-primary);
            color: var(--clr-primary);
        }

        /* Notification Layout (Left Side) */
        .dash-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            border-bottom: 2px solid var(--clr-border);
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .tab {
            padding: 8px 16px;
            font-weight: 600;
            color: var(--clr-text-secondary);
            cursor: pointer;
            transition: 0.3s;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .tab:hover {
            background: #F1F5F9;
            color: var(--clr-text-primary);
        }

        .tab.active {
            background: var(--clr-primary-light);
            color: var(--clr-primary);
        }

        /* Notification Card System */
        .notif-card {
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--clr-border);
            box-shadow: var(--shadow-sm);
            padding: 20px;
            margin-bottom: 16px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            cursor: pointer;
            transition: 0.2s;
            position: relative;
            overflow: hidden;
        }

        .notif-card:hover {
            box-shadow: var(--shadow-md);
            border-color: #CBD5E1;
        }

        .notif-card.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--clr-primary);
        }

        .n-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .n-body {
            flex: 1;
        }

        .n-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .n-title {
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--clr-text-primary);
        }

        .n-time {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--clr-text-secondary);
        }

        .n-content {
            font-size: 0.95rem;
            color: var(--clr-text-secondary);
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .n-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--clr-text-primary);
            margin-bottom: 16px;
            font-family: monospace;
        }

        .n-actions {
            display: flex;
            gap: 12px;
        }

        .n-btn {
            background: #F1F5F9;
            border: 1px solid var(--clr-border);
            color: var(--clr-text-primary);
            font-weight: 600;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: 0.2s;
        }

        .n-btn:hover {
            background: #E2E8F0;
        }

        .n-btn.primary {
            background: var(--clr-primary);
            border-color: var(--clr-primary);
            color: white;
        }

        .n-btn.primary:hover {
            background: var(--clr-primary-dark);
        }

        /* Specific State Styles */
        .nc-important {
            border-color: #FECACA;
            background: #FEF2F2;
        }

        .nc-important::before {
            background: #DC2626;
        }

        .nc-important .n-icon {
            background: #DC2626;
            color: white;
        }

        .nc-ready {
            border-color: #BBF7D0;
            background: #F0FDF4;
        }

        .nc-ready::before {
            background: #16A34A;
        }

        .nc-ready .n-icon {
            background: #16A34A;
            color: white;
        }

        .cb-appoint {
            background: #EEF2FF;
            color: var(--clr-primary);
        }

        .cb-rx {
            background: #F3E8FF;
            color: #9333EA;
        }

        .cb-order {
            background: #FEF9C3;
            color: #CA8A04;
        }

        .cb-pay {
            background: #DCFCE7;
            color: #16A34A;
        }

        /* Comms Center (Right Side) */
        .comm-panel {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            height: fit-content;
            position: sticky;
            top: 24px;
        }

        .cp-head {
            background: #F8FAFC;
            padding: 20px;
            border-bottom: 1px solid var(--clr-border);
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--clr-primary-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cp-search {
            padding: 12px 20px;
            border-bottom: 1px solid var(--clr-border);
            position: relative;
        }

        .cp-search input {
            width: 100%;
            border: 1px solid var(--clr-border);
            padding: 10px 10px 10px 32px;
            border-radius: var(--radius-full);
            font-size: 0.9rem;
        }

        .cp-search i {
            position: absolute;
            left: 32px;
            top: 22px;
            color: var(--clr-text-secondary);
        }

        .chat-thread {
            padding: 16px 20px;
            border-bottom: 1px solid var(--clr-border);
            display: flex;
            gap: 12px;
            cursor: pointer;
            transition: 0.2s;
        }

        .chat-thread:hover {
            background: #F8FAFC;
        }

        .ct-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--clr-primary-light);
            color: var(--clr-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .ct-main {
            flex: 1;
        }

        .ct-title {
            font-weight: 700;
            color: var(--clr-text-primary);
            font-size: 0.95rem;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }

        .ct-meta {
            font-size: 0.85rem;
            color: var(--clr-text-secondary);
        }

        .ct-count {
            font-size: 0.75rem;
            font-weight: 700;
            background: #E2E8F0;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
        }

        /* MODALS */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: 0.3s;
            padding: 16px;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        .modal-card {
            background: white;
            width: 100%;
            max-width: 500px;
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transform: translateY(20px);
            transition: 0.3s;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }

        .modal-overlay.active .modal-card {
            transform: translateY(0);
        }

        .m-head {
            padding: 20px;
            border-bottom: 1px solid var(--clr-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 800;
            font-size: 1.1rem;
        }

        /* Preferences Module */
        .pref-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--clr-text-secondary);
        }

        .toggle {
            width: 40px;
            height: 20px;
            background: #CBD5E1;
            border-radius: 20px;
            position: relative;
            cursor: pointer;
            transition: 0.3s;
        }

        .toggle::after {
            content: '';
            position: absolute;
            left: 2px;
            top: 2px;
            width: 16px;
            height: 16px;
            background: white;
            border-radius: 10px;
            transition: 0.3s;
        }

        .toggle.on {
            background: #16A34A;
        }

        .toggle.on::after {
            left: 22px;
        }

        .pref-group {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px dashed var(--clr-border);
        }

        /* Active Chat Module */
        .chat-box {
            height: 400px;
            background: #F1F5F9;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .msg {
            max-width: 85%;
            padding: 12px;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            line-height: 1.4;
            position: relative;
        }

        .msg.sys {
            align-self: center;
            background: white;
            border: 1px dashed var(--clr-border);
            text-align: center;
            color: var(--clr-text-secondary);
            font-size: 0.85rem;
            max-width: 90%;
        }

        .msg.sys i {
            color: #16A34A;
            margin-right: 4px;
        }

        .msg.doc {
            align-self: flex-start;
            background: white;
            border: 1px solid var(--clr-border);
            border-bottom-left-radius: 0;
            color: var(--clr-text-primary);
        }

        .msg.pat {
            align-self: flex-end;
            background: var(--clr-primary);
            color: white;
            border-bottom-right-radius: 0;
        }

        .chat-input {
            padding: 16px;
            background: white;
            border-top: 1px solid var(--clr-border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        @media (max-width: 900px) {
            .dash-layout {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
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
                    <a href="index.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Home</a>
                    <a href="find-doctor.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Find Doctor</a>
                    <a href="medicine/tablet.php" style="color:var(--clr-text-secondary); text-decoration:none; font-weight:600;">Pharmacy</a>
                    <a href="notifications.php" style="color:var(--clr-primary); text-decoration:none; font-weight:600;">🔔 Notifications</a>
                </div>
            </div>
            <a href="profile.php" style="background:var(--clr-primary-light); color:var(--clr-primary); padding:10px 16px; border-radius:20px; text-decoration:none;"><i class="fas fa-user-circle"></i> Profile</a>
        </div>
    </nav>

    <div class="dash-layout">

        <div class="page-header">
            <div>
                <h1 class="page-title">Notifications & Messages</h1>
                <p class="page-sub">Stay updated about your healthcare journey</p>
            </div>
            <div class="opt-actions">
                <button class="btn-opt"><i class="fas fa-check-double"></i> Mark all as read</button>
                <button class="btn-opt" onclick="openModal('pref-modal')"><i class="fas fa-cog"></i> Preferences</button>
            </div>
        </div>

        <!-- LEFT STREAM -->
        <div>
            <div class="dash-tabs">
                <div class="tab active">All (8)</div>
                <div class="tab">Appointments (2)</div>
                <div class="tab">Prescriptions (1)</div>
                <div class="tab">Orders (3)</div>
                <div class="tab">Payments</div>
                <div class="tab">System</div>
            </div>

            <!-- 3. Important Notification -->
            <div class="notif-card nc-important unread">
                <div class="n-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="n-body">
                    <div class="n-head">
                        <div class="n-title" style="color:#DC2626;">IMPORTANT</div>
                        <div class="n-time">10 min ago</div>
                    </div>
                    <div class="n-content" style="color:#991B1B; font-weight:600;">
                        Your prescription requires clarification.<br>
                        <span style="font-weight:400;">Pharmacy has requested clarification from the Doctor before fulfillment.</span>
                    </div>
                    <div class="n-meta"><i class="fas fa-pills" style="color:#DC2626;"></i> Paracetamol 500 mg</div>
                    <div class="n-actions">
                        <button class="n-btn">View Prescription</button>
                        <button class="n-btn">Contact Support</button>
                    </div>
                </div>
            </div>

            <!-- 5. Room Readiness (Actionable Push) -->
            <div class="notif-card nc-ready unread">
                <div class="n-icon"><i class="fas fa-video"></i></div>
                <div class="n-body">
                    <div class="n-head">
                        <div class="n-title" style="color:#166534;">Doctor is Ready</div>
                        <div class="n-time" style="color:#16A34A; font-weight:800;">NOW</div>
                    </div>
                    <div class="n-content" style="color:#166534; font-weight:600;"><?php echo htmlspecialchars($docName); ?> is ready for your consultation.</div>
                    <div class="n-meta">Appointment: AP-2026-00452</div>
                    <div class="n-actions">
                        <button class="n-btn primary" onclick="location.href='consultation-room.php'"><i class="fas fa-sign-in-alt"></i> JOIN CONSULTATION</button>
                    </div>
                </div>
            </div>

            <!-- 6. New Prescription Issued -->
            <div class="notif-card unread">
                <div class="n-icon cb-rx"><i class="fas fa-file-prescription"></i></div>
                <div class="n-body">
                    <div class="n-head">
                        <div class="n-title">New Prescription</div>
                        <div class="n-time">25 min ago</div>
                    </div>
                    <div class="n-content"><?php echo htmlspecialchars($docName); ?> has issued a prescription after your consultation.</div>
                    <div class="n-meta" style="color:var(--clr-primary);">Prescription ID: RX-2026-001245</div>
                    <div class="n-actions">
                        <button class="n-btn">View Prescription</button>
                        <button class="n-btn primary">Order Medicines</button>
                    </div>
                </div>
            </div>

            <!-- 7. Pharmacy Verification -->
            <div class="notif-card">
                <div class="n-icon cb-order"><i class="fas fa-clinic-medical"></i></div>
                <div class="n-body">
                    <div class="n-head">
                        <div class="n-title">Prescription Verified</div>
                        <div class="n-time">1 hr ago</div>
                    </div>
                    <div class="n-content">Your prescription has been reviewed and verified by the pharmacist.</div>
                    <div class="n-meta">Order: MED-260905-1024</div>
                    <div class="n-actions">
                        <button class="n-btn">Track Order</button>
                    </div>
                </div>
            </div>

            <!-- 8. Payment Successful -->
            <div class="notif-card">
                <div class="n-icon cb-pay"><i class="fas fa-wallet"></i></div>
                <div class="n-body">
                    <div class="n-head">
                        <div class="n-title">Payment Successful</div>
                        <div class="n-time">2 hrs ago</div>
                    </div>
                    <div class="n-content">Payment of ₹315 was successfully completed.</div>
                    <div class="n-meta">Transaction: TXN84992110</div>
                    <div class="n-actions">
                        <button class="n-btn">Download Invoice</button>
                    </div>
                </div>
            </div>

        </div>

        <!-- 11. COMMUNICATION CENTER (Right Pillar) -->
        <div>
            <div class="comm-panel">
                <div class="cp-head"><i class="fas fa-comments"></i> Communication Center</div>
                <div class="cp-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search conversations...">
                </div>

                <div class="chat-thread" onclick="openModal('chat-modal')">
                    <div class="ct-icon"><i class="fas fa-user-md"></i></div>
                    <div class="ct-main">
                        <div class="ct-title"><?php echo htmlspecialchars($docName); ?> <span class="ct-count">2</span></div>
                        <div class="ct-meta">Consultation-related... <span style="float:right;">Today</span></div>
                    </div>
                </div>

                <div class="chat-thread">
                    <div class="ct-icon" style="background:#FEF9C3; color:#CA8A04;"><i class="fas fa-clinic-medical"></i></div>
                    <div class="ct-main">
                        <div class="ct-title">Pharmacy Support </div>
                        <div class="ct-meta">Order MED-260905-1024 <span style="float:right;">Yesterday</span></div>
                    </div>
                </div>

                <div class="chat-thread">
                    <div class="ct-icon" style="background:#F1F5F9; color:var(--clr-text-secondary);"><i class="fas fa-life-ring"></i></div>
                    <div class="ct-main">
                        <div class="ct-title">System Support </div>
                        <div class="ct-meta">Ticket #10452 <span style="float:right;">2 days ago</span></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- 14. PREFERENCES MODAL -->
    <div class="modal-overlay" id="pref-modal">
        <div class="modal-card">
            <div class="m-head">
                <div><i class="fas fa-cog"></i> Notification Preferences</div>
                <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('pref-modal')">&times;</button>
            </div>
            <div style="padding:24px;">

                <div class="pref-group">
                    <h3 style="font-size:1rem; font-weight:800; color:var(--clr-primary-dark); margin-bottom:16px;">Appointment Reminders</h3>
                    <div class="pref-item">Push Notification <div class="toggle on" onclick="this.classList.toggle('on')"></div>
                    </div>
                    <div class="pref-item">SMS Alerts <div class="toggle on" onclick="this.classList.toggle('on')"></div>
                    </div>
                    <div class="pref-item">Email Updates <div class="toggle on" onclick="this.classList.toggle('on')"></div>
                    </div>
                </div>

                <div class="pref-group">
                    <h3 style="font-size:1rem; font-weight:800; color:var(--clr-primary-dark); margin-bottom:16px;">Prescription Updates</h3>
                    <div class="pref-item">Push Notification <div class="toggle on" onclick="this.classList.toggle('on')"></div>
                    </div>
                    <div class="pref-item">Email (For secure PDFs) <div class="toggle on" onclick="this.classList.toggle('on')"></div>
                    </div>
                </div>

                <div class="pref-group" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
                    <h3 style="font-size:1rem; font-weight:800; color:var(--clr-primary-dark); margin-bottom:16px;">Promotional Messages</h3>
                    <div class="pref-item">Push Notification <div class="toggle" onclick="this.classList.toggle('on')"></div>
                    </div>
                    <div class="pref-item">Email <div class="toggle" onclick="this.classList.toggle('on')"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- 12. SECURE CLINICAL CHAT MODAL -->
    <div class="modal-overlay" id="chat-modal">
        <div class="modal-card" style="max-width:450px;">
            <div class="m-head" style="background:var(--clr-primary-dark); color:white; border-bottom:none;">
                <div><i class="fas fa-user-md"></i> <?php echo htmlspecialchars($docName); ?></div>
                <div style="font-size:0.85rem; background:rgba(0,0,0,0.3); padding:4px 12px; border-radius:20px; font-weight:600;"><i class="fas fa-lock"></i> Secure</div>
                <button style="border:none; background:transparent; color:white; font-size:1.5rem; cursor:pointer;" onclick="closeModal('chat-modal')">&times;</button>
            </div>

            <!-- 13. System Overlays in chat -->
            <div class="chat-box">

                <div class="msg sys"><i class="fas fa-shield-alt"></i> Consultation Chat Scope Activated</div>

                <div class="msg doc">Please upload the latest blood report before the consultation so I can review the lipid profile.</div>

                <div class="msg pat">Sure doctor, I have just uploaded it into the patient details portal.</div>

                <div class="msg doc">Thank you. I'm reviewing it now. See you in the consultation room shortly.</div>

                <div class="msg sys" style="border-color:#BBF7D0;"><i class="fas fa-check-circle" style="color:#16A34A;"></i> <strong>Event:</strong> Prescription has been issued.</div>
                <div class="msg sys" style="border-color:#BBF7D0;"><i class="fas fa-wallet" style="color:#16A34A;"></i> <strong>Payment:</strong> ₹315 successfully completed.</div>

            </div>

            <div class="chat-input">
                <i class="fas fa-paperclip" style="color:var(--clr-text-secondary); font-size:1.25rem; cursor:pointer;"></i>
                <input type="text" placeholder="Type a message..." style="flex:1; border:1px solid var(--clr-border); padding:10px 12px; border-radius:var(--radius-full); font-size:0.95rem;">
                <button style="background:var(--clr-primary); color:white; border:none; width:40px; height:40px; border-radius:50%; align-items:center; justify-content:center; display:flex; cursor:pointer;"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>


    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
    </script>

</body>

</html>