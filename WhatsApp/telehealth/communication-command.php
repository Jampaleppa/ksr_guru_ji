<?php
require_once 'helper.php';
// Simulated Communication, Notifications & Messaging Command Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Command | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Communication Navbar */
        .comm-nav { background: #064E3B; border-bottom: 2px solid #047857; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .comm-nav .navbar-brand { color: #A7F3D0; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .comm-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .comm-nav-links::-webkit-scrollbar { display: none; }
        .comm-nav-links a { color: #6EE7B7; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .comm-nav-links a:hover { color: white; background: #047857; }
        .comm-nav-links a.active { color: white; background: #059669; box-shadow: 0 0 10px rgba(5, 150, 105, 0.5); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout Grid */
        .grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; width: 100%; align-items: stretch; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; height: 100%; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; overflow-y:auto; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px;}

        /* Unified Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; color: #94A3B8; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; border-bottom: 2px solid #334155; letter-spacing: 0.5px; }
        .data-table td { padding: 16px; border-bottom: 1px solid #334155; vertical-align: middle; color: white; font-size: 0.9rem; font-weight: 600; }
        .data-table tr:hover { background: #0F172A; }
        .data-table tr:last-child td { border-bottom: none; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; gap: 4px; border: 1px solid transparent; letter-spacing:0.5px;}
        .bg-success { background: rgba(16, 185, 129, 0.1); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-channel-wa { background: rgba(37, 211, 102, 0.1); color: #25D366; border-color: rgba(37, 211, 102, 0.3); }
        .bg-channel-sms { background: rgba(59, 130, 246, 0.1); color: #60A5FA; border-color: rgba(59, 130, 246, 0.3); }
        .bg-channel-push { background: rgba(168, 85, 247, 0.1); color: #C084FC; border-color: rgba(168, 85, 247, 0.3); }
        .bg-warning { background: rgba(245, 158, 11, 0.1); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; margin-bottom:4px;}
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }

        /* Secure Messaging UI */
        .chat-container { display:flex; flex-direction:column; height:400px; border:1px solid #334155; border-radius:8px; overflow:hidden;}
        .chat-header { background: #0F172A; padding:12px 16px; border-bottom:1px solid #334155; display:flex; justify-content:space-between; align-items:center;}
        .chat-body { flex:1; background:#1E293B; padding:16px; overflow-y:auto; display:flex; flex-direction:column; gap:16px; }
        
        .chat-msg { max-width:80%; display:flex; flex-direction:column; gap:4px; }
        .chat-msg-dr { align-self:flex-start; }
        .chat-msg-pt { align-self:flex-end; align-items:flex-end; }
        
        .msg-bubble { padding:10px 14px; border-radius:12px; font-size:0.9rem; line-height:1.4; color:white; }
        .chat-msg-dr .msg-bubble { background: #334155; border-bottom-left-radius: 2px; }
        .chat-msg-pt .msg-bubble { background: #059669; border-bottom-right-radius: 2px; }
        
        .msg-time { font-size:0.7rem; color:#94A3B8; font-weight:700; }
        
        .chat-input { display:flex; padding:12px; background:#0F172A; border-top:1px solid #334155; gap:12px;}
        .chat-input input { flex:1; background:#1E293B; border:1px solid #334155; color:white; padding:10px 16px; border-radius:20px; outline:none; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;}
        .btn:hover { background: #475569; }
        .btn-brand { background: #059669; border-color: #047857; color: white; }
        .btn-brand:hover { background: #047857; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="comm-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-satellite-dish"></i> ENGAGEMENT & NOTIFICATION COMMAND
        </div>
        <div class="comm-nav-links">
            <a href="#"  class="active">Live Delivery Pipeline</a>
            <a href="#" >Reminders Engine</a>
            <a href="#" >Secure Doctor-Patient Comms</a>
            <a href="#" >Channel Preferences</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <i class="fas fa-headset" style="color:#6EE7B7;"></i> Engagement Admin
            </div>
        </div>
    </div>
</div>

<div class="app-container" style="flex-direction:column;">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.5rem; font-weight:900; color:white;"><i class="fas fa-broadcast-tower" style="color:#10B981;"></i> Multi-Channel Delivery Router</h1>
            <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Demonstrating pure event routing: Communication delivers state changes. It does not own the clinical state.</div>
        </div>
    </div>

    <!-- Active Communication KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
            <div class="kpi-val">24,580</div>
            <div class="kpi-lbl">Notifications Sent (24h)</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #25D366;">
            <div class="kpi-val">98.2%</div>
            <div class="kpi-lbl">WhatsApp Delivery Rate</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #38BDF8;">
            <div class="kpi-val">326</div>
            <div class="kpi-lbl">Active Secure Threads</div>
        </div>
        <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
            <div class="kpi-val">12</div>
            <div class="kpi-lbl">Escalated Failures</div>
        </div>
    </div>

    <div class="grid-layout">
        
        <!-- Cross-Domain Event Router Log -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-stream"></i> Omnichannel Event Tracker</div>
            </div>
            <div class="panel-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subscribed Domain Event</th>
                            <th>Routing Strategy</th>
                            <th>Delivery Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight:800; color:white; font-size:0.9rem;">Appointment Confirmed</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Source: Scheduling Engine</div>
                            </td>
                            <td>
                                <span class="badge bg-channel-wa"><i class="fab fa-whatsapp"></i> WhatsApp</span>
                                <span class="badge bg-channel-push"><i class="fas fa-mobile-alt"></i> In-App</span>
                            </td>
                            <td>
                                <span class="badge bg-success">DELIVERED</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:800; color:white; font-size:0.9rem;">Pharmacy Order Dispatched</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Source: Fleet Logistics</div>
                            </td>
                            <td>
                                <span class="badge bg-channel-sms"><i class="fas fa-sms"></i> SMS</span>
                            </td>
                            <td>
                                <span class="badge bg-success">DELIVERED</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight:800; color:white; font-size:0.9rem;">Follow-up Reminder (24hr)</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:4px;">Source: Care Management</div>
                            </td>
                            <td>
                                <span class="badge bg-channel-wa"><i class="fab fa-whatsapp"></i> WhatsApp</span>
                            </td>
                            <td>
                                <span class="badge bg-warning">FALLBACK ESCALATION</span>
                                <div style="font-size:0.7rem; color:#94A3B8; margin-top:4px;">Retrying via SMS...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Secure Doctor-Patient Thread -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-user-md"></i> Authorized Micro-Messaging</div>
                <span class="badge" style="background:rgba(16,185,129,0.1); color:#34D399; border:1px solid rgba(52,211,153,0.3);">POLICY: ACTIVE</span>
            </div>
            <div class="panel-body">
                
                <div style="font-size:0.8rem; color:#94A3B8; text-align:center; margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #334155;">
                    <i class="fas fa-lock"></i> End-to-End Encrypted. Context tied to Consultation CON-10245.
                </div>

                <div class="chat-container">
                    <div class="chat-header">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <img src="https://i.pravatar.cc/150?img=12" style="width:32px; height:32px; border-radius:50%; border:2px solid #334155;">
                            <div>
                                <div style="color:white; font-weight:800; font-size:0.9rem;">Dr. Priya</div>
                                <div style="color:#34D399; font-size:0.75rem; font-weight:700;">● Online</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-body">
                        <div class="chat-msg chat-msg-dr">
                            <div class="msg-bubble">Hello Rahul. Please upload the requested Allergy Panel report so I can verify the final prescription variables.</div>
                            <div class="msg-time">10:15 AM • Read</div>
                        </div>
                        
                        <div class="chat-msg chat-msg-pt">
                            <div class="msg-bubble">I have uploaded the report in the Health Records section.</div>
                            <div class="msg-time">10:17 AM • Delivered</div>
                        </div>

                        <div class="chat-msg chat-msg-pt">
                            <div class="msg-bubble" style="background:#1E293B; border:1px solid #334155; display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-file-pdf" style="color:#EF4444; font-size:1.2rem;"></i>
                                <span style="font-weight:700;">Allergy_Panel_XYZ.pdf</span>
                            </div>
                            <div class="msg-time">10:17 AM • Delivered</div>
                        </div>
                    </div>
                    
                    <div class="chat-input">
                        <input type="text" placeholder="Type a secure message...">
                        <button class="btn btn-brand" style="border-radius:50%; width:40px; height:40px; padding:0;"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>
