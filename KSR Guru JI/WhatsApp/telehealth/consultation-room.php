<?php
require_once 'helper.php';

// Mock DB Fetch for the patient & doctor logic
$patientName = 'Jampaleppa';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? TELEHEALTH_LEAD_DOCTOR_NAME;
$docSpec = TELEHEALTH_LEAD_DOCTOR_SPECIALTY;
$consultType = 'Video';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Room | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; background: #0F172A; font-family: 'Inter', sans-serif; overflow: hidden; }

        /* PRE-CALL CHECK OVERLAY */
        #precall-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #F8FAFC; z-index: 2000; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .precall-box { background: white; padding: 40px; border-radius: var(--radius-lg); box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
        .precall-title { font-size: 1.5rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 24px; text-align: center; }
        
        .check-item { display: flex; gap: 16px; margin-bottom: 20px; align-items: flex-start; }
        .check-icon { width: 40px; height: 40px; background: #F1F5F9; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--clr-text-secondary); }
        .check-text h4 { margin: 0 0 4px 0; font-size: 1rem; color: var(--clr-text-primary); }
        .check-status { color: #16A34A; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 4px; }
        .check-item.denied .check-status { color: #DC2626; }
        
        .btn-join-room { width: 100%; padding: 16px; background: var(--clr-primary); color: white; border: none; border-radius: var(--radius-md); font-weight: 700; font-size: 1.1rem; margin-top: 24px; cursor: pointer; transition: 0.3s; }
        .btn-join-room:hover { background: var(--clr-primary-dark); }

        /* ROOM LAYOUT */
        .room-container { display: flex; flex-direction: column; height: 100%; }

        /* Top Bar */
        .top-bar { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; background: rgba(15, 23, 42, 0.9); border-bottom: 1px solid rgba(255,255,255,0.1); color: white; }
        .top-left { display: flex; align-items: center; gap: 16px; }
        .secure-badge { background: #166534; padding: 4px 12px; border-radius: var(--radius-full); font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .doc-brand { font-size: 1.1rem; font-weight: 700; }
        .doc-brand span { color: #94A3B8; font-size: 0.9rem; font-weight: 400; margin-left: 8px; }
        
        .top-right { display: flex; align-items: center; gap: 24px; }
        .connect-status { color: #22C55E; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .timer { font-family: monospace; font-size: 1.2rem; font-weight: 700; background: rgba(0,0,0,0.3); padding: 6px 12px; border-radius: var(--radius-sm); }

        /* Video Area */
        .video-wrapper { flex: 1; position: relative; background: #000; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        
        .main-video { width: 100%; height: 100%; object-fit: cover; opacity: 0.8; }
        .main-video-overlay { position: absolute; bottom: 24px; left: 24px; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.5); font-size: 1.25rem; font-weight: 700; }

        .pip-video { position: absolute; bottom: 24px; right: 24px; width: 240px; height: 160px; background: #1E293B; border-radius: var(--radius-md); overflow: hidden; border: 2px solid rgba(255,255,255,0.2); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .pip-badge { position: absolute; bottom: 8px; left: 8px; background: rgba(0,0,0,0.6); color: white; padding: 4px 8px; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; }

        /* Bottom Controls */
        .control-bar { padding: 20px 24px; background: rgba(15, 23, 42, 0.95); display: flex; justify-content: center; align-items: center; gap: 24px; border-top: 1px solid rgba(255,255,255,0.1); }
        
        .ctrl-btn { display: flex; flex-direction: column; align-items: center; gap: 8px; background: transparent; border: none; color: white; cursor: pointer; min-width: 70px; transition: 0.2s; }
        .ctrl-icon { width: 50px; height: 50px; border-radius: 50%; background: #334155; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; transition: 0.2s; }
        .ctrl-btn:hover .ctrl-icon { background: #475569; }
        .ctrl-text { font-size: 0.8rem; font-weight: 600; color: #94A3B8; }
        
        .ctrl-btn.off .ctrl-icon { background: #DC2626; }
        .ctrl-btn.off .ctrl-text { color: #FECACA; }

        .btn-end { display: flex; flex-direction: column; align-items: center; gap: 8px; background: transparent; border: none; color: white; cursor: pointer; margin-left: 24px; }
        .btn-end .ctrl-icon { background: #DC2626; width: 60px; height: 60px; font-size: 1.5rem; }
        .btn-end:hover .ctrl-icon { background: #B91C1C; }

        /* OVERLAYS & MODALS */
        .side-panel { position: fixed; right: -400px; top: 70px; bottom: 100px; width: 350px; background: white; border-radius: var(--radius-lg) 0 0 var(--radius-lg); box-shadow: -5px 0 25px rgba(0,0,0,0.2); transition: 0.3s; display: flex; flex-direction: column; z-index: 100; overflow: hidden; }
        .side-panel.active { right: 0; }
        
        .panel-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; background: #F8FAFC; border-bottom: 1px solid var(--clr-border); }
        .panel-title { font-weight: 700; color: var(--clr-primary-dark); font-size: 1.1rem; }
        .panel-close { background: transparent; border: none; font-size: 1.25rem; color: var(--clr-text-secondary); cursor: pointer; }

        /* Chat Panel */
        .chat-area { flex: 1; padding: 16px; overflow-y: auto; background: #F1F5F9; display: flex; flex-direction: column; gap: 16px; }
        .msg { max-width: 80%; padding: 12px; border-radius: var(--radius-md); font-size: 0.95rem; line-height: 1.4; position: relative; }
        .msg-doc { align-self: flex-start; background: white; border: 1px solid var(--clr-border); border-bottom-left-radius: 0; color: var(--clr-text-primary); }
        .msg-pat { align-self: flex-end; background: var(--clr-primary); color: white; border-bottom-right-radius: 0; }
        .msg-name { font-size: 0.75rem; font-weight: 700; margin-bottom: 4px; opacity: 0.8; }
        
        .chat-input { padding: 16px; background: white; border-top: 1px solid var(--clr-border); display: flex; align-items: center; gap: 12px; }
        .chat-input input { flex: 1; padding: 12px; border: 1px solid var(--clr-border); border-radius: var(--radius-full); outline: none; }
        .chat-input button { background: var(--clr-primary); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; }

        /* Dark Modals for end flow */
        .dark-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 3000; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: 0.3s; padding: 16px; }
        .dark-modal-overlay.active { opacity: 1; pointer-events: all; }
        .dark-modal { background: #1E293B; color: white; width: 100%; max-width: 450px; border-radius: var(--radius-lg); padding: 32px; text-align: center; border: 1px solid rgba(255,255,255,0.1); }
        
        .dark-modal h2 { font-size: 1.5rem; margin-bottom: 16px; }
        .dark-modal p { color: #94A3B8; margin-bottom: 24px; font-size: 0.95rem; }
        
        .summary-box { background: rgba(0,0,0,0.3); border: 1px dashed rgba(255,255,255,0.2); padding: 16px; border-radius: var(--radius-md); text-align: left; margin-bottom: 24px; }
        .summary-box div { margin-bottom: 8px; color: #E2E8F0; }
        
        .modal-btns { display: flex; gap: 12px; }
        .btn-dark { flex: 1; padding: 12px; background: #334155; color: white; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; }
        .btn-red { flex: 1; padding: 12px; background: #DC2626; color: white; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; }

        /* Success End Modal */
        #completed-modal .dark-modal { background: white; color: var(--clr-text-primary); text-align: center; max-width: 400px; padding: 40px 32px; }
        .success-tick { width: 80px; height: 80px; background: #16A34A; color: white; font-size: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; }
        .completed-list { text-align: left; margin: 24px 0; background: #F8FAFC; border: 1px solid var(--clr-border); padding: 16px; border-radius: var(--radius-md); }
        .completed-list div { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-weight: 600; color: var(--clr-text-secondary); }
        .completed-list div i { color: #16A34A; }

        @media (max-width: 768px) {
            .pip-video { width: 120px; height: 160px; right: 16px; bottom: 16px; }
            .side-panel { width: 100%; right: -100%; top: 0; bottom: 85px; border-radius: 0; }
            .top-right { gap: 12px; }
            .control-bar { padding: 12px 0; gap: 12px; overflow-x: auto; justify-content: flex-start; }
            .btn-end { margin-left: 0; }
            .doc-brand span { display: none; }
        }
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

<!-- 2. ROOM INTERFACE -->
<div class="room-container">
    
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-left">
            <div class="secure-badge"><i class="fas fa-lock"></i> <span>Secure</span></div>
            <div class="doc-brand">👨‍⚕️ <?php echo htmlspecialchars($docName); ?> <span>• <?php echo htmlspecialchars($docSpec); ?></span></div>
        </div>
        <div class="top-right">
            <div id="record-status" style="display:none; align-items:center; gap:6px; color:#ef4444; font-size:0.9rem; font-weight:700; animation: blink 2s infinite;">
                <i class="fas fa-circle" style="font-size:0.6rem;"></i> REC
            </div>
            <div class="connect-status"><i class="fas fa-circle" style="font-size:0.6rem;"></i> Connected</div>
            <div class="timer" id="room-timer">00:00</div>
        </div>
        <style>@keyframes blink { 0% {opacity:1;} 50% {opacity:0.3;} 100% {opacity:1;} }</style>
    </div>

    <!-- Video Area -->
    <div class="video-wrapper">
        <!-- Mock Doctor Video Background -->
        <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=1200" class="main-video" style="filter: brightness(0.7);" alt="Doctor Video">
        
        <div class="main-video-overlay">
            <?php echo $docName; ?>
        </div>

        <!-- PIP Patient Video (Real WebRTC Camera) -->
        <div class="pip-video">
            <video id="local-video" autoplay muted playsinline style="width:100%; height:100%; object-fit:cover;"></video>
            <div class="pip-badge">👤 You</div>
        </div>
    </div>

    <!-- Bottom Control Bar -->
    <div class="control-bar">
        <button class="ctrl-btn" id="btn-mic" onclick="toggleCtrl('btn-mic', 'fa-microphone', 'fa-microphone-slash', 'Mic On', 'Muted')">
            <div class="ctrl-icon"><i class="fas fa-microphone"></i></div>
            <div class="ctrl-text">Mic On</div>
        </button>
        <button class="ctrl-btn" id="btn-cam" onclick="toggleCtrl('btn-cam', 'fa-video', 'fa-video-slash', 'Camera', 'Off')">
            <div class="ctrl-icon"><i class="fas fa-video"></i></div>
            <div class="ctrl-text">Camera</div>
        </button>
        <button class="ctrl-btn" id="btn-spk">
            <div class="ctrl-icon"><i class="fas fa-volume-up"></i></div>
            <div class="ctrl-text">Speaker</div>
        </button>
        <button class="ctrl-btn" onclick="togglePanel('chat-panel')">
            <div class="ctrl-icon"><i class="fas fa-comment-dots"></i></div>
            <div class="ctrl-text">Chat</div>
        </button>
        <button class="ctrl-btn" onclick="togglePanel('doc-panel')">
            <div class="ctrl-icon"><i class="fas fa-file-medical"></i></div>
            <div class="ctrl-text">Documents</div>
        </button>
        <!-- End -->
        <button class="btn-end" onclick="showEndModal()">
            <div class="ctrl-icon"><i class="fas fa-phone-slash"></i></div>
            <div class="ctrl-text" style="color:#FECACA;">End</div>
        </button>
    </div>

</div>

<!-- 3. SIDE PANELS -->
<!-- Chat Panel -->
<div class="side-panel" id="chat-panel">
    <div class="panel-header">
        <div class="panel-title">💬 Consultation Chat</div>
        <button class="panel-close" onclick="togglePanel('chat-panel')">&times;</button>
    </div>
    <div class="chat-area">
        <div class="msg msg-doc">
            <div class="msg-name">👨‍⚕️ <?php echo $docName; ?></div>
            Hello! Please describe the exact symptoms you have been experiencing today.
        </div>
        <div class="msg msg-pat">
            <div class="msg-name">👤 You</div>
            I have been experiencing severe loss of sensation and weakness in my left arm since yesterday morning.
        </div>
    </div>
    <div class="chat-input">
        <input type="text" placeholder="Type message.........">
        <button><i class="fas fa-paperclip" style="background:transparent; color:#94A3B8; margin-right:8px;"></i></button>
        <button><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<!-- Doc Panel -->
<div class="side-panel" id="doc-panel">
    <div class="panel-header">
        <div class="panel-title">📄 Medical Documents</div>
        <button class="panel-close" onclick="togglePanel('doc-panel')">&times;</button>
    </div>
    <div style="padding: 16px; overflow-y:auto;">
        
        <div style="border: 1px solid var(--clr-border); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 12px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-weight:600; font-size:0.95rem; display:flex; align-items:center; gap:8px;"><i class="fas fa-file-pdf" style="color:#DC2626;"></i> Blood Test Report</div>
            </div>
            <a href="#"  style="color:var(--clr-primary); font-size:0.9rem; font-weight:600; text-decoration:none;">View</a>
        </div>

        <div style="border: 1px solid var(--clr-border); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 12px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-weight:600; font-size:0.95rem; display:flex; align-items:center; gap:8px;"><i class="fas fa-file-image" style="color:var(--clr-primary);"></i> Medical Scan</div>
            </div>
            <a href="#"  style="color:var(--clr-primary); font-size:0.9rem; font-weight:600; text-decoration:none;">View</a>
        </div>

        <button style="width:100%; border:1px dashed var(--clr-primary); background:var(--clr-primary-light); color:var(--clr-primary); padding:16px; border-radius:var(--radius-md); font-weight:600; margin-top:16px; cursor:pointer;"><i class="fas fa-upload"></i> Upload Document</button>
    </div>
</div>

<!-- 4. END MODALS -->
<!-- Confirm End -->
<div class="dark-modal-overlay" id="end-modal">
    <div class="dark-modal">
        <h2 style="color:white;">END CONSULTATION?</h2>
        <p>Are you sure you want to leave this consultation?</p>
        
        <div class="summary-box">
            <div style="font-weight:700;">Consultation Summary</div>
            <div>Doctor: <?php echo $docName; ?></div>
            <div>Duration: <span id="end-timer-val">00:00</span></div>
            <div>Type: <?php echo $consultType; ?></div>
        </div>

        <div class="modal-btns">
            <button class="btn-dark" onclick="closeDarkModal('end-modal')">Continue</button>
            <button class="btn-red" onclick="finishConsultation()">End Consultation</button>
        </div>
    </div>
</div>

<!-- Completed Final State -->
<div class="dark-modal-overlay" id="completed-modal" style="background: rgba(15, 23, 42, 0.95);">
    <div class="dark-modal">
        <div class="success-tick"><i class="fas fa-check"></i></div>
        <h2 style="color:var(--clr-primary-dark);">Consultation Completed</h2>
        <div style="font-weight:600; font-size:1.1rem; color:var(--clr-text-primary); margin-bottom:4px;"><?php echo $docName; ?></div>
        <div style="color:var(--clr-text-secondary); margin-bottom:24px;"><?php echo $docSpec; ?></div>

        <div class="completed-list">
            <div><i class="fas fa-clock"></i> Duration: <span id="final-timer-val">00:00</span></div>
            <div><i class="fas fa-prescription-bottle-alt"></i> Prescription: ✓ Available</div>
            <div><i class="fas fa-file-invoice-dollar"></i> Invoice: ✓ Available</div>
        </div>

        <div style="display:flex; flex-direction:column; gap:12px;">
            <a href="my-prescriptions.php" style="background:var(--clr-primary); color:white; padding:12px; border-radius:var(--radius-md); font-weight:700; text-decoration:none;"><i class="fas fa-prescription"></i> View Prescription</a>
            <a href="my-orders.php" style="background:transparent; border:1px solid var(--clr-border); color:var(--clr-text-primary); padding:12px; border-radius:var(--radius-md); font-weight:600; text-decoration:none;"><i class="fas fa-file-invoice"></i> View Pharmacy Invoice</a>
            <a href="my-appointments.php" style="background:#F1F5F9; color:var(--clr-text-secondary); padding:12px; border-radius:var(--radius-md); font-weight:600; text-decoration:none; margin-top:12px;">Return to My Appointments</a>
        </div>
    </div>
</div>

<script>
    let timerInterval;
    let seconds = 0;
    
    // WebRTC Variables
    let localStream;
    let mediaRecorder;
    let recordedChunks = [];

    function enterRoom() {
        document.getElementById('precall-overlay').style.opacity = '0';
        setTimeout(() => {
            document.getElementById('precall-overlay').style.display = 'none';
            startTimer();
            initializeMediaAccess(); // Request Hardware Permissions
        }, 300);
    }

    async function initializeMediaAccess() {
        try {
            // This triggers the browser's built-in, mandatory security permission prompt
            localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            
            // Feed the hardware stream into the PIP video element
            const localVideo = document.getElementById('local-video');
            localVideo.srcObject = localStream;
            
            // Initialize recording of the stream
            startRecording(localStream);
        } catch (err) {
            console.error("Hardware access denied or unavailable: ", err);
            alert("Camera and Microphone permissions are strictly required for the Virtual Consultation.");
        }
    }

    function startRecording(stream) {
        // Enforce active recording for clinical compliance
        mediaRecorder = new MediaRecorder(stream, { mimeType: 'video/webm' });
        mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                recordedChunks.push(event.data);
            }
        };
        mediaRecorder.start(1000); // Capture in chunks
        
        // Show recording indicator
        document.getElementById('record-status').style.display = 'flex';
    }

    function startTimer() {
        timerInterval = setInterval(() => {
            seconds++;
            const m = Math.floor(seconds / 60).toString().padStart(2, '0');
            const s = (seconds % 60).toString().padStart(2, '0');
            const format = m + ':' + s;
            document.getElementById('room-timer').innerText = format;
            document.getElementById('end-timer-val').innerText = format;
            document.getElementById('final-timer-val').innerText = format;
        }, 1000);
    }

    function toggleCtrl(btnId, iOn, iOff, tOn, tOff) {
        const btn = document.getElementById(btnId);
        const icon = btn.querySelector('.ctrl-icon i');
        const text = btn.querySelector('.ctrl-text');
        
        btn.classList.toggle('off');
        
        if (btn.classList.contains('off')) {
            icon.classList.remove(iOn);
            icon.classList.add(iOff);
            text.innerText = tOff;
        } else {
            icon.classList.remove(iOff);
            icon.classList.add(iOn);
            text.innerText = tOn;
        }
    }

    function togglePanel(panelId) {
        const p = document.getElementById(panelId);
        if(p.classList.contains('active')) {
            p.classList.remove('active');
        } else {
            document.querySelectorAll('.side-panel').forEach(el => el.classList.remove('active'));
            p.classList.add('active');
        }
    }

    function showEndModal() {
        document.getElementById('end-modal').classList.add('active');
    }

    function closeDarkModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function finishConsultation() {
        clearInterval(timerInterval);
        
        // Stop recording and hardware tracks
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
        }
        
        closeDarkModal('end-modal');
        document.getElementById('completed-modal').classList.add('active');
    }
</script>

</body>
</html>
