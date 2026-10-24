<?php
require_once 'helper.php';

// Dynamically generate or load patient from DB
$pdo = getAssistDB();
$reqPatientId = (int)($_GET['patient_id'] ?? ($_GET['id'] ?? 0));
$apptId = !empty($_GET['apt_id']) ? htmlspecialchars((string)$_GET['apt_id']) : ('APT-' . date('Ymd-') . strtolower(date('hia')));

$patientData = null;
if ($reqPatientId > 0) {
    try {
        $stPat = $pdo->prepare("SELECT p.*, u.email as user_email FROM patients p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = ? OR p.user_id = ? LIMIT 1");
        $stPat->execute([$reqPatientId, $reqPatientId]);
        $patientData = $stPat->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}
}
if (!$patientData) {
    try {
        $patientData = $pdo->query("SELECT p.*, u.email as user_email FROM patients p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}
}

if (!$patientData) {
    $patientName = 'Rahul Kumar';
    $patientId = 'PT-000124';
    $patientAge = 32;
    $patientGender = 'Male';
} else {
    $patientName = !empty($patientData['patient_name']) ? $patientData['patient_name'] : (!empty($patientData['name']) ? $patientData['name'] : 'Rahul Kumar');
    $patientId = !empty($patientData['ksr_id']) ? $patientData['ksr_id'] : ('PT-' . str_pad((string)$patientData['id'], 6, '0', STR_PAD_LEFT));
    $dob = $patientData['date_of_birth'] ?? ($patientData['dob'] ?? '1994-05-15');
    $patientAge = $dob ? (new DateTimeImmutable((string)$dob))->diff(new DateTimeImmutable('today'))->y : 32;
    if ($patientAge <= 0) $patientAge = 32;
    $patientGender = !empty($patientData['gender']) ? $patientData['gender'] : 'Male';
}
    // Mode & Fee Calculation
    $mode = isset($_GET['mode']) && $_GET['mode'] === 'audio' ? 'Audio' : 'Video';
    $baseFee = $mode === 'Audio' ? 100 : 125;
    $platformCharge = 15;
    $totalFee = $baseFee + $platformCharge;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Consultation Room | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(15, 23, 42, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --shadow-glass: 0 8px 32px 0 rgba(0, 0, 0, 0.5);
            --radius-md: 12px;
            --radius-xl: 20px;
        }
        
        body { background: #020617; /* Deep space dark background */ margin: 0; padding: 0; color: white; display: flex; flex-direction: column; height: 100vh; overflow: hidden; font-family: 'Inter', sans-serif;}
        
        /* Top Navigation Bar */
        .room-nav { background: rgba(2, 6, 23, 0.85); backdrop-filter: blur(16px); padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); box-shadow: 0 4px 20px rgba(0,0,0,0.5); z-index: 100;}
        .rn-left { display: flex; align-items: center; gap: 20px; }
        .rn-right { display: flex; align-items: center; gap: 24px; }
        .rn-badge { background: rgba(255,255,255,0.05); padding: 6px 14px; border-radius: 8px; font-family: monospace; font-size: 0.95rem; color: #94A3B8; border: 1px solid rgba(255,255,255,0.05);}
        
        .timer-badge { display: flex; align-items: center; gap: 10px; font-weight: 900; font-size: 1.25rem; color: #F8FAFC; text-shadow: 0 2px 4px rgba(0,0,0,0.5);}
        .connection-status { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 800; color: #4ADE80; background: rgba(34, 197, 94, 0.15); padding: 8px 16px; border-radius: 20px; border: 1px solid rgba(34, 197, 94, 0.3); box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);}

        /* Main Workspace Grid */
        .room-workspace { display: grid; grid-template-columns: 1fr 420px; height: calc(100vh - 75px); }

        /* Left: Video Area */
        .video-area { position: relative; display: flex; flex-direction: column; background: #000; box-shadow: inset -10px 0 30px rgba(0,0,0,0.5);}
        
        .main-video { flex: 1; position: relative; display: flex; align-items: center; justify-content: center; background: url('https://images.unsplash.com/photo-1533227268428-f9ed0900f9bf?auto=format&fit=crop&q=80&w=1200&h=800') center/cover no-repeat; }
        .main-video::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, transparent 40%); pointer-events: none; }
        
        .pat-name-overlay { position: absolute; bottom: 32px; left: 32px; font-size: 1.5rem; font-weight: 900; text-shadow: 0 4px 12px rgba(0,0,0,0.8); z-index: 10; display: flex; align-items: center; gap: 16px; }
        .pat-chip { background: rgba(15,23,42,0.6); backdrop-filter: blur(8px); padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 4px 10px rgba(0,0,0,0.3);}
        
        .pip-video { position: absolute; top: 32px; right: 32px; width: 280px; height: 180px; background: rgba(15,23,42,0.9); backdrop-filter: blur(12px); border-radius: var(--radius-md); border: 2px solid rgba(255,255,255,0.15); box-shadow: 0 16px 40px rgba(0,0,0,0.6); overflow: hidden; display: flex; align-items: center; justify-content: center; z-index: 10; transition: 0.3s;}
        .pip-video:hover { transform: scale(1.05); border-color: rgba(255,255,255,0.3); }

        /* Video Controls */
        .video-controls { background: rgba(2, 6, 23, 0.9); backdrop-filter: blur(16px); padding: 24px; display: flex; justify-content: center; gap: 20px; border-top: 1px solid rgba(255,255,255,0.05); }
        .vc-btn { width: 56px; height: 56px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: white; font-size: 1.35rem; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.2);}
        .vc-btn:hover { background: rgba(255,255,255,0.15); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.4);}
        .vc-btn.danger { background: #DC2626; border-color: #EF4444; box-shadow: 0 4px 16px rgba(220,38,38,0.4);}
        .vc-btn.danger:hover { background: #B91C1C; transform: scale(1.05);}
        .vc-btn.active { background: var(--clr-primary); border-color: var(--clr-primary-light); box-shadow: 0 4px 16px rgba(37,99,235,0.4);}

        /* Right: Clinical Sidebar */
        .clinical-sidebar { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(24px); border-left: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; overflow: hidden;}
        
        .sidebar-tabs { display: flex; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(2, 6, 23, 0.8); backdrop-filter: blur(16px);}
        .sb-tab { flex: 1; background: transparent; border: none; color: #94A3B8; font-weight: 800; padding: 16px 12px; cursor: pointer; transition: 0.3s; font-size: 0.85rem; text-transform: uppercase; border-bottom: 3px solid transparent; letter-spacing: 0.5px;}
        .sb-tab.active { color: white; border-bottom-color: var(--clr-primary); background: rgba(255,255,255,0.05); }
        .sb-tab:hover:not(.active) { color: white; background: rgba(255,255,255,0.03); }

        .sidebar-content { flex: 1; overflow-y: auto; padding: 24px; }
        
        /* Context Panel */
        .context-box { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(8px); border-radius: var(--radius-md); padding: 20px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05); box-shadow: inset 0 2px 10px rgba(0,0,0,0.2); transition: 0.3s;}
        .context-box:hover { border-color: rgba(255,255,255,0.1); background: rgba(30, 41, 59, 0.8);}
        .cb-label { font-size: 0.75rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; letter-spacing: 0.5px;}
        .cb-val { font-size: 1rem; font-weight: 800; color: #F8FAFC; }

        /* Live Notes */
        .live-note-field { margin-bottom: 20px; }
        .live-note-field label { display: block; font-size: 0.85rem; font-weight: 800; color: #94A3B8; margin-bottom: 8px; }
        .live-note-field textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: var(--radius-md); padding: 16px; color: white; font-family: inherit; font-size: 0.95rem; resize: vertical; min-height: 80px; transition: 0.3s; box-shadow: inset 0 4px 10px rgba(0,0,0,0.3);}
        .live-note-field textarea:focus { outline: none; border-color: var(--clr-primary); background: rgba(0,0,0,0.5); box-shadow: inset 0 4px 10px rgba(0,0,0,0.5), 0 0 0 3px rgba(37,99,235,0.2);}

        /* Chat */
        .chat-bubble { padding: 14px 18px; border-radius: 16px; font-size: 0.95rem; margin-bottom: 16px; max-width: 85%; font-weight: 500; line-height: 1.4; box-shadow: 0 4px 12px rgba(0,0,0,0.2);}
        .chat-bubble.doc { background: var(--gradient-primary); border-bottom-right-radius: 4px; margin-left: auto; border: 1px solid rgba(255,255,255,0.1);}
        .chat-bubble.pat { background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(4px); border-bottom-left-radius: 4px; color: #E2E8F0; border: 1px solid rgba(255,255,255,0.1); }

        /* Quick Actions */
        .qa-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 32px; padding-top: 32px; border-top: 1px dashed rgba(255,255,255,0.1); }
        .qa-btn { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 16px; border-radius: var(--radius-md); font-weight: 800; font-size: 0.85rem; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 10px; transition: 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.2);}
        .qa-btn:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0,0,0,0.4);}
        .qa-btn i { font-size: 1.35rem; color: var(--clr-primary-light); }

        /* Modals (Dark Theme override) */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(2, 6, 23, 0.8); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; pointer-events: none; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); padding: 16px; backdrop-filter: blur(12px); }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(24px); width: 100%; max-width: 600px; border-radius: var(--radius-xl); box-shadow: 0 24px 64px rgba(0,0,0,0.5), inset 0 0 0 1px rgba(255,255,255,0.1); overflow: hidden; transform: translateY(30px) scale(0.95); transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .modal-overlay.active .modal-card { transform: translateY(0) scale(1); }
        .m-head { padding: 24px 32px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; font-weight: 900; font-size: 1.35rem; color: white; background: rgba(0,0,0,0.2); }
        .m-body { padding: 32px; }
        .m-foot { padding: 20px 32px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: flex-end; gap: 16px; background: rgba(0,0,0,0.3); }
        
        .btn-solid { background: white; color: black; border: none; padding: 12px 24px; border-radius: var(--radius-md); font-weight: 800; cursor: pointer; transition: 0.3s;}
        .btn-solid:hover { background: #E2E8F0; }
        .btn-danger { background: #DC2626; color: white; border: none; padding: 12px 24px; border-radius: var(--radius-md); font-weight: 800; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 12px rgba(220,38,38,0.3);}
        .btn-danger:hover { background: #B91C1C; transform: translateY(-2px);}

        /* PRE-CALL CHECK OVERLAY */
        #precall-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #020617; z-index: 2000; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .precall-box { background: rgba(15, 23, 42, 0.95); padding: 40px; border-radius: var(--radius-lg); box-shadow: 0 20px 40px rgba(0,0,0,0.5); width: 100%; max-width: 450px; border: 1px solid rgba(255,255,255,0.1); }
        .precall-title { font-size: 1.5rem; font-weight: 900; color: #38BDF8; margin-bottom: 24px; text-align: center; }
        .check-item { display: flex; gap: 16px; margin-bottom: 20px; align-items: flex-start; }
        .check-icon { width: 40px; height: 40px; background: rgba(0,0,0,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #94A3B8; }
        .check-text h4 { margin: 0 0 4px 0; font-size: 1rem; color: white; }
        .check-status { color: #10B981; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 4px; }
        .btn-join-room { width: 100%; padding: 16px; background: #2563EB; color: white; border: none; border-radius: var(--radius-md); font-weight: 800; font-size: 1.1rem; margin-top: 24px; cursor: pointer; transition: 0.3s; }
        .btn-join-room:hover { background: #1D4ED8; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<!-- 1. PRE-CALL CHECK -->
<div id="precall-overlay">
    <div class="precall-box">
        <h2 class="precall-title">GET READY</h2>
        
        <div class="check-item">
            <div class="check-icon"><i class="fas fa-microphone"></i></div>
            <div class="check-text">
                <h4>Microphone</h4>
                <div class="check-status"><i class="fas fa-check"></i> Working</div>
            </div>
        </div>
        <div class="check-item">
            <div class="check-icon"><i class="fas fa-video"></i></div>
            <div class="check-text">
                <h4>Camera</h4>
                <div class="check-status"><i class="fas fa-check"></i> Working</div>
            </div>
        </div>
        <div class="check-item">
            <div class="check-icon"><i class="fas fa-volume-up"></i></div>
            <div class="check-text">
                <h4>Speaker</h4>
                <div class="check-status"><i class="fas fa-check"></i> Working</div>
            </div>
        </div>
        <div class="check-item">
            <div class="check-icon"><i class="fas fa-wifi"></i></div>
            <div class="check-text">
                <h4>Internet</h4>
                <div class="check-status"><i class="fas fa-check"></i> Good Connection</div>
            </div>
        </div>

        <button class="btn-join-room" onclick="enterRoom()">Join Consultation</button>
    </div>
</div>

<!-- TOP NAV -->
<div class="room-nav">
    <div class="rn-left">
        <div style="font-weight:900; color:var(--clr-primary); font-size:1.2rem;"><i class="fas fa-heartbeat"></i> DOCTOR ASSIST</div>
        <div class="rn-badge">CON-20260905-1042</div>
        <div class="rn-badge" style="color:var(--clr-primary-light); border:1px solid rgba(37,99,235,0.3);"><i class="fas fa-lock"></i> SECURE <?php echo strtoupper($mode); ?></div>
        <div class="rn-badge" style="color:#A78BFA; border:1px solid rgba(167, 139, 250, 0.3);"><i class="fas fa-rupee-sign"></i> <?php echo $totalFee; ?> (<?php echo $baseFee; ?>+<?php echo $platformCharge; ?>)</div>
    </div>
    <div class="rn-right">
        <div class="connection-status" id="conn-status-badge"><i class="fas fa-signal"></i> Waiting for Patient...</div>
        <div class="timer-badge"><span style="display:inline-block; width:8px; height:8px; background:#DC2626; border-radius:50%; animation: pulse 2s infinite;"></span> <span id="call-timer">00:00</span></div>
    </div>
</div>

<!-- WORKSPACE -->
<div class="room-workspace">
    
    <!-- LEFT: VIDEO CONSOLE -->
    <div class="video-area">
        
        <div class="main-video">
            <!-- Simulated Video -->
            <div class="pat-name-overlay">
                👤 <?php echo htmlspecialchars($patientName); ?> 
                <span class="pat-chip"><?php echo htmlspecialchars($patientId); ?></span>
                <span class="pat-chip"><?php echo htmlspecialchars($patientAge); ?> Yrs</span>
            </div>
            
            <div class="pip-video">
                <i class="fas fa-user-md" style="font-size:3rem; color:rgba(255,255,255,0.2);"></i>
            </div>
        </div>

        <div class="video-controls">
            <button class="vc-btn active" title="Microphone"><i class="fas fa-microphone"></i></button>
            <button class="vc-btn active" title="Camera"><i class="fas fa-video"></i></button>
            <button class="vc-btn" title="Screen Share"><i class="fas fa-desktop"></i></button>
            <button class="vc-btn" title="Network Diagnostics"><i class="fas fa-chart-line"></i></button>
            <button class="vc-btn" title="Settings"><i class="fas fa-cog"></i></button>
            <div style="width:24px;"></div>
            <button class="vc-btn danger" style="width:120px; border-radius:30px; font-weight:800; font-size:0.9rem;" onclick="openModal('end-modal')">
                <i class="fas fa-phone-slash" style="margin-right:8px;"></i> END
            </button>
        </div>

    </div>

    <!-- RIGHT: CLINICAL SIDEBAR -->
    <div class="clinical-sidebar">
        
        <div class="sidebar-tabs">
            <button class="sb-tab" onclick="switchTab('context')"><i class="fas fa-info-circle"></i> Context</button>
            <button class="sb-tab active" onclick="switchTab('notes')"><i class="fas fa-edit"></i> Notes</button>
            <button class="sb-tab" onclick="switchTab('chat')"><i class="fas fa-comments"></i> Chat</button>
        </div>

        <div class="sidebar-content">
            
            <!-- TAB: PATIENT CONTEXT -->
            <div id="tab-context" style="display:none;">
                <div class="context-box" style="border-left:3px solid #DC2626;">
                    <div class="cb-label"><i class="fas fa-exclamation-triangle"></i> Allergies</div>
                    <div class="cb-val">No known allergies</div>
                </div>
                
                <div class="context-box">
                    <div class="cb-label"><i class="fas fa-stethoscope"></i> Current Conditions</div>
                    <div class="cb-val">Hypertension (Diagnosed: 2025)</div>
                </div>

                <div class="context-box">
                    <div class="cb-label"><i class="fas fa-pills"></i> Current Medicines</div>
                    <div class="cb-val">Amlodipine 5mg (1-0-0)</div>
                </div>

                <div class="context-box" style="background:rgba(255,255,255,0.02);">
                    <div class="cb-label"><i class="fas fa-history"></i> Last Consultation</div>
                    <div class="cb-val" style="font-size:0.85rem;">02 Sep 2026 — General Review</div>
                    <button style="background:transparent; border:1px solid rgba(255,255,255,0.2); color:white; padding:4px 8px; border-radius:4px; font-size:0.75rem; margin-top:8px; cursor:pointer;">View Timeline <i class="fas fa-external-link-alt"></i></button>
                </div>
            </div>

            <!-- TAB: LIVE NOTES -->
            <div id="tab-notes" style="display:block;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <span style="font-weight:800; color:#F8FAFC;"><i class="fas fa-file-medical"></i> Live Clinical Notes</span>
                    <span style="font-size:0.7rem; color:#16A34A; background:rgba(22,163,74,0.1); padding:2px 6px; border-radius:4px;"><i class="fas fa-save"></i> Auto-saved</span>
                </div>

                <div class="live-note-field">
                    <label>Chief Complaint / Symptoms</label>
                    <textarea>Patient reports moderate fever and persistent dry cough for the last 48 hours. Mentions mild fatigue.</textarea>
                </div>

                <div class="live-note-field">
                    <label>Clinical Assessment</label>
                    <textarea>Suspected viral upper respiratory infection. Vitals stable clinically based on visual assessment.</textarea>
                </div>

                <div class="qa-grid">
                    <button class="qa-btn" onclick="window.location.href='build-prescription.php'"><i class="fas fa-pills"></i> Create Prescription</button>
                    <button class="qa-btn" onclick="window.location.href='consultation-workspace.php'"><i class="fas fa-stethoscope"></i> Add Diagnosis</button>
                    <button class="qa-btn" onclick="window.location.href='doctor-appointments.php'"><i class="fas fa-calendar-plus"></i> Schedule Follow-up</button>
                    <button class="qa-btn" onclick="window.location.href='patient-master-record.php'"><i class="fas fa-file-pdf"></i> View Documents</button>
                </div>
            </div>

            <!-- TAB: SECURE CHAT -->
            <div id="tab-chat" style="display:none; display:flex; flex-direction:column; height:100%;">
                
                <div style="flex:1; overflow-y:auto; padding-bottom:16px;">
                    <div style="text-align:center; font-size:0.75rem; color:#94A3B8; margin-bottom:16px;">
                        <i class="fas fa-lock"></i> Messages are encrypted & consultation-scoped.
                    </div>
                    
                    <div class="chat-bubble doc">
                        Please confirm if your temperature has crossed 101°F today.
                        <div style="font-size:0.7rem; text-align:right; margin-top:4px; opacity:0.7;">10:45 AM</div>
                    </div>

                    <div class="chat-bubble pat">
                        Yes doctor, it was 101.4 exactly two hours ago.
                        <div style="font-size:0.7rem; text-align:right; margin-top:4px; opacity:0.7;">10:46 AM</div>
                    </div>
                </div>

                <div style="display:flex; gap:8px;">
                    <input type="text" placeholder="Type a message..." style="flex:1; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); padding:12px; border-radius:6px; color:white; font-family:inherit;">
                    <button style="background:var(--clr-primary); border:none; color:white; width:45px; border-radius:6px; cursor:pointer;"><i class="fas fa-paper-plane"></i></button>
                </div>

            </div>

        </div>
    </div>
</div>

<!-- END CONSULTATION MODAL -->
<div class="modal-overlay" id="end-modal">
    <div class="modal-card">
        <div class="m-head" style="background:rgba(220, 38, 38, 0.1);">
            <div><i class="fas fa-exclamation-circle" style="color:#EF4444;"></i> End Consultation</div>
            <button style="border:none; background:transparent; font-size:1.5rem; color:white; cursor:pointer;" onclick="closeModal('end-modal')">&times;</button>
        </div>
        <div class="m-body">
            <p style="font-size:1.05rem; margin-bottom:24px;">Are you sure you want to end this connection? The patient will be disconnected.</p>
            
            <div style="background:rgba(255,255,255,0.05); padding:16px; border-radius:8px; font-size:0.95rem;">
                <div style="font-weight:800; color:#94A3B8; text-transform:uppercase; margin-bottom:12px; font-size:0.8rem;">Before you close:</div>
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <span><i class="fas fa-check-circle" style="color:#16A34A;"></i> Clinical Notes Saved</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <span><i class="fas fa-check-circle" style="color:#16A34A;"></i> Diagnosis Recorded</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; color:#FCD34D;">
                    <span><i class="far fa-circle"></i> Prescription Required</span>
                    <button style="background:transparent; border:1px solid rgba(255,255,255,0.2); color:white; font-size:0.75rem; padding:2px 8px; border-radius:4px; cursor:pointer;">Draft Now</button>
                </div>
            </div>
        </div>
        <div class="m-foot">
            <button class="btn-solid" style="background:transparent; color:white; border:1px solid rgba(255,255,255,0.2);" onclick="closeModal('end-modal')">Cancel</button>
            <!-- In real flow, routes to Consultation Workspace to complete Rx and finalize -->
            <button class="btn-danger" id="btn-finalize" disabled style="opacity: 0.5; cursor: not-allowed;" onclick="window.location.href='consultation-workspace.php'">End & Finalize Record (Wait 5m)</button>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.getElementById('tab-context').style.display = 'none';
        document.getElementById('tab-notes').style.display = 'none';
        document.getElementById('tab-chat').style.display = 'none';
        
        document.querySelectorAll('.sb-tab').forEach(btn => btn.classList.remove('active'));
        event.currentTarget.classList.add('active');

        document.getElementById('tab-' + tab).style.display = tab === 'chat' ? 'flex' : 'block';
    }

    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    
    let timerInterval = null;
    let secondsElapsed = 0;
    const MIN_DURATION = 5 * 60; // 5 minutes
    const MAX_DURATION = 10 * 60; // 10 minutes

    function enterRoom() { 
        document.getElementById('precall-overlay').style.display = 'none';
        document.getElementById('conn-status-badge').innerHTML = '<i class="fas fa-signal"></i> Connection Excellent';
        startTimer();
    }

    function startTimer() {
        if(timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            secondsElapsed++;
            
            const m = Math.floor(secondsElapsed / 60).toString().padStart(2, '0');
            const s = (secondsElapsed % 60).toString().padStart(2, '0');
            document.getElementById('call-timer').innerText = `${m}:${s}`;
            
            // Check 5 minutes mark
            const btnFinalize = document.getElementById('btn-finalize');
            if (secondsElapsed === MIN_DURATION) {
                btnFinalize.disabled = false;
                btnFinalize.style.opacity = '1';
                btnFinalize.style.cursor = 'pointer';
                btnFinalize.innerText = 'End & Finalize Record';
                // Provide visual cue
                btnFinalize.style.boxShadow = '0 0 15px rgba(220, 38, 38, 0.8)';
            } else if (secondsElapsed < MIN_DURATION) {
                let remaining = MIN_DURATION - secondsElapsed;
                let rm = Math.floor(remaining / 60);
                let rs = remaining % 60;
                btnFinalize.innerText = `Wait ${rm}m ${rs}s to Finalize`;
            }

            // Check 10 minutes maximum termination mark
            if (secondsElapsed >= MAX_DURATION) {
                clearInterval(timerInterval);
                alert("Maximum consultation duration of 10 minutes reached. The session will now automatically close.");
                window.location.href = 'consultation-workspace.php';
            }
        }, 1000); // Ticks every 1s
    }

</script>

<style>
    @keyframes pulse {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.5); opacity: 0.5; }
        100% { transform: scale(1); opacity: 1; }
    }
</style>

</body>
</html>
