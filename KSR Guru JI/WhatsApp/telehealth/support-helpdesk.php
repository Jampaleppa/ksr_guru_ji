<?php
require_once 'helper.php';
// Simulated Support & Helpdesk Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Support Navbar */
        .sup-nav { background: white; border-bottom: 1px solid var(--clr-border); position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: var(--shadow-sm); }
        .sup-nav .navbar-brand { color: var(--clr-primary-dark); display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; }
        .sup-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .sup-nav-links::-webkit-scrollbar { display: none; }
        .sup-nav-links a { color: var(--clr-text-secondary); text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.9rem; white-space: nowrap; transition: 0.2s; }
        .sup-nav-links a:hover { color: var(--clr-primary-dark); background: #F1F5F9; }
        .sup-nav-links a.active { color: var(--clr-primary); background: #EFF6FF; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        /* Layout */
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }
        .side-col { width: 350px; display: flex; flex-direction: column; gap: 24px; flex-shrink: 0; }

        /* Dashboard KPI */
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .kpi-card { background: white; border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); }
        .kpi-title { font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: var(--clr-text-primary); }
        
        /* Panels */
        .panel { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; overflow: hidden; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid var(--clr-border); background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 0; display:flex; flex-direction:column; flex:1; }

        /* Ticket Table */
        .ticket-table { width: 100%; border-collapse: collapse; }
        .ticket-table th { text-align: left; padding: 12px 16px; background: #F1F5F9; font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--clr-border); }
        .ticket-table td { padding: 16px; border-bottom: 1px solid var(--clr-border); font-size: 0.9rem; font-weight: 600; color: var(--clr-text-primary); vertical-align: middle; }
        .ticket-table tr:hover td { background: #F8FAFC; cursor: pointer; }
        
        /* Badges */
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
        .bg-critical { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .bg-high { background: #FFF7ED; color: #EA580C; border: 1px solid #FFEDD5; }
        .bg-med { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }
        .bg-low { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }

        /* Ticket Viewer */
        .chat-area { flex: 1; min-height: 300px; background: #F8FAFC; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 16px; }
        .msg-bubble { max-width: 80%; padding: 12px 16px; border-radius: 12px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .msg-user { background: white; border: 1px solid var(--clr-border); align-self: flex-start; border-bottom-left-radius: 4px; }
        .msg-agent { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E3A8A; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-note { background: #FEF3C7; border: 1px solid #FDE68A; align-self: stretch; text-align: center; font-size: 0.8rem; font-weight: 800; color: #92400E; display: flex; flex-direction: column; gap: 4px; }
        
        .chat-input { padding: 16px; background: white; border-top: 1px solid var(--clr-border); }
        .chat-input textarea { width: 100%; border: 1px solid var(--clr-border); border-radius: 8px; padding: 12px; font-family: inherit; font-size: 0.95rem; resize: none; margin-bottom: 8px; }
        .chat-input textarea:focus { outline: none; border-color: var(--clr-primary); }

        /* Context Box */
        .context-box { background: #F8FAFC; border: 1px solid var(--clr-border); border-radius: 6px; padding: 12px; margin-bottom: 16px; }
        .ctx-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.85rem; }
        .ctx-link { color: var(--clr-primary); font-weight: 800; text-decoration: none; cursor:pointer; }
        .ctx-link:hover { text-decoration: underline; }

        /* Buttons */
        .btn-action { background: white; border: 1px solid var(--clr-border); padding: 8px 16px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer; color: var(--clr-text-primary); display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; }
        .btn-action:hover { background: #F1F5F9; }
        .btn-primary { background: var(--clr-primary); color: white; border-color: var(--clr-primary); }
        .btn-primary:hover { background: var(--clr-primary-dark); }
        .btn-danger { background: #FEF2F2; color: #DC2626; border-color: #FECACA; }
        .btn-danger:hover { background: #FEE2E2; }
        
        /* Emergency */
        .emergency-banner { background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; padding: 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="sup-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-headset"></i> HELPDESK
        </div>
        <div class="sup-nav-links">
            <a href="#"  class="active">Dashboard</a>
            <a href="#" >My Tickets <span style="background:#EF4444; color:white; padding:2px 6px; border-radius:12px; font-size:0.75rem; margin-left:4px;">8</span></a>
            <a href="#" >Live Support</a>
            <a href="#" >SLA Watch</a>
            <a href="#" >Escalations</a>
            <a href="#" >Knowledge Base</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:30px; height:30px; border-radius:50%;"> Support Admin
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    
    <div class="main-col">
        
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-title">Open Tickets <i class="fas fa-ticket-alt"></i></div>
                <div class="kpi-val">248</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Critical <i class="fas fa-exclamation-circle text-danger"></i></div>
                <div class="kpi-val" style="color:#DC2626;">6</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">SLA At Risk <i class="fas fa-clock text-warning"></i></div>
                <div class="kpi-val" style="color:#D97706;">18</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Live Chats <i class="fas fa-comment-dots text-primary"></i></div>
                <div class="kpi-val" style="color:#2563EB;">14</div>
            </div>
        </div>

        <!-- Ticket Table -->
        <div class="panel">
            <div class="panel-head">
                <div style="font-weight:800; font-size:1.1rem; color:var(--clr-primary-dark);"><i class="fas fa-list"></i> Ticket Queue</div>
                <div style="display:flex; gap:8px;">
                    <button class="btn-action">All</button>
                    <button class="btn-action" style="background:#F1F5F9;">Open</button>
                    <button class="btn-action">Assigned</button>
                    <input type="text" placeholder="Search ticket, user..." style="padding:6px 12px; border:1px solid var(--clr-border); border-radius:6px; outline:none; font-family:inherit;">
                </div>
            </div>
            <div class="panel-body">
                <table class="ticket-table">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>User</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>SLA Target</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="background:#F8FAFC; border-left: 3px solid var(--clr-primary);">
                            <td><span style="font-family:monospace; color:var(--clr-primary); font-weight:800;">T100921</span></td>
                            <td>Patient A</td>
                            <td>Payment</td>
                            <td><span class="badge bg-critical">🔴 Critical</span></td>
                            <td>Open</td>
                            <td><span style="color:#DC2626;"><i class="fas fa-stopwatch"></i> 18 min</span></td>
                        </tr>
                        <tr>
                            <td><span style="font-family:monospace; color:var(--clr-primary); font-weight:800;">T100922</span></td>
                            <td>Dr. B</td>
                            <td>Technical</td>
                            <td><span class="badge bg-high">🟠 High</span></td>
                            <td>Assigned</td>
                            <td><i class="fas fa-stopwatch"></i> 42 min</td>
                        </tr>
                        <tr>
                            <td><span style="font-family:monospace; color:var(--clr-primary); font-weight:800;">T100923</span></td>
                            <td>Pharmacy C</td>
                            <td>Order Fulfillment</td>
                            <td><span class="badge bg-med">🟡 Medium</span></td>
                            <td>Waiting</td>
                            <td><i class="fas fa-stopwatch"></i> 3 hrs</td>
                        </tr>
                        <tr>
                            <td><span style="font-family:monospace; color:var(--clr-primary); font-weight:800;">T100924</span></td>
                            <td>Driver D</td>
                            <td>Delivery Logistics</td>
                            <td><span class="badge bg-low">🟢 Normal</span></td>
                            <td>Resolved</td>
                            <td>—</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Active Ticket Sidebar -->
    <div class="side-col">
        
        <div class="emergency-banner">
            <div>
                <div style="color:#B91C1C; font-weight:900; font-size:0.9rem;"><i class="fas fa-ambulance"></i> Emergency Detect</div>
                <div style="font-size:0.8rem; color:#991B1B; font-weight:600;">System flagged potential critical safety issue.</div>
            </div>
            <button class="btn-action btn-danger" style="margin:0;">Escalate</button>
        </div>

        <div class="panel" style="flex:1;">
            <div class="panel-head" style="flex-direction:column; align-items:flex-start; gap:12px;">
                <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                    <div style="font-weight:900; font-size:1.1rem;">Ticket #T100921</div>
                    <span class="badge bg-critical">🔴 CRITICAL</span>
                </div>
                <div style="font-size:0.9rem; font-weight:700; color:var(--clr-text-primary);">Payment issue — consultation booking</div>
            </div>
            
            <div class="panel-body">
                
                <!-- Cross-Domain Context -->
                <div style="padding:16px; border-bottom:1px solid var(--clr-border);">
                    <div style="font-size:0.75rem; font-weight:800; color:var(--clr-text-secondary); text-transform:uppercase; margin-bottom:8px;">Linked Operations</div>
                    <div class="context-box">
                        <div class="ctx-row"><span style="color:var(--clr-text-secondary); font-weight:700;">Customer</span> <span class="ctx-link">Patient A (P100921)</span></div>
                        <div class="ctx-row"><span style="color:var(--clr-text-secondary); font-weight:700;">Appointment</span> <span class="ctx-link">A88201</span></div>
                        <div class="ctx-row"><span style="color:var(--clr-text-secondary); font-weight:700;">Payment</span> <span class="ctx-link text-success">PAY98231 (SUCCESS)</span></div>
                        <div class="ctx-row"><span style="color:var(--clr-text-secondary); font-weight:700;">Doctor</span> <span class="ctx-link">Dr. ABC (D10082)</span></div>
                    </div>
                </div>

                <!-- Chat Viewer -->
                <div class="chat-area">
                    <div class="msg-bubble msg-user">
                        My payment was deducted but appointment still shows payment pending. I have the receipt.
                        <div style="font-size:0.75rem; color:var(--clr-text-secondary); margin-top:4px; font-weight:700;">Patient A • 10:12 AM</div>
                    </div>
                    
                    <div class="msg-note">
                        <span><i class="fas fa-lock"></i> INTERNAL NOTE</span>
                        <span>Auto-reconciliation failed. Payment Gateway implies SUCCESS. Ticket assigned to Finance Support.</span>
                    </div>

                    <div class="msg-bubble msg-agent">
                        We are checking the payment transaction and appointment status right now. Please hold on.
                        <div style="font-size:0.75rem; color:#60A5FA; margin-top:4px; font-weight:700;">Support-102 • 10:15 AM</div>
                    </div>
                </div>

                <!-- Input -->
                <div class="chat-input">
                    <textarea rows="3" placeholder="Type reply to patient or add internal note..."></textarea>
                    <div style="display:flex; justify-content:space-between;">
                        <div style="display:flex; gap:8px;">
                            <button class="btn-action" title="Add Attachment"><i class="fas fa-paperclip"></i></button>
                            <button class="btn-action" title="Internal Note" style="background:#FFFBEB; border-color:#FDE68A; color:#D97706;"><i class="fas fa-lock"></i> Note</button>
                        </div>
                        <button class="btn-action btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                    </div>
                </div>
                
            </div>
        </div>

    </div>

</div>

</body>
</html>
