<?php
require_once 'helper.php';
// Master Ecosystem Watchtower & API Gateway Dashboard
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Ecosystem Watchtower | Doctor Assist Platform</title>
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

        body { 
            background: #020617; 
            margin: 0; 
            padding-bottom: 40px; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh;
            color: #F8FAFC; 
            font-family: 'Inter', system-ui, sans-serif;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(37, 99, 235, 0.08), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(16, 185, 129, 0.05), transparent 25%);
        }
        
        /* Master Watchtower Navbar */
        .master-nav { background: rgba(2, 6, 23, 0.8); backdrop-filter: blur(24px); border-bottom: 1px solid var(--glass-border); position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: var(--shadow-glass); }
        .master-nav .navbar-brand { color: #F8FAFC; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; }
        .master-nav .navbar-brand i { color: #38BDF8; }
        .master-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .master-nav-links::-webkit-scrollbar { display: none; }
        .master-nav-links a { color: #94A3B8; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .master-nav-links a:hover { color: white; background: rgba(255,255,255,0.05); }
        .master-nav-links a.active { color: white; background: rgba(14, 165, 233, 0.2); box-shadow: inset 0 0 15px rgba(14, 165, 233, 0.2); border: 1px solid rgba(14,165,233,0.3); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; flex-direction: column; }
        
        .grid-layout { display: grid; grid-template-columns: 3fr 1fr; gap: 24px; width: 100%; align-items: stretch; }

        /* Tech Panel */
        .panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border-radius: 12px; border: 1px solid #1E293B; overflow: hidden; display: flex; flex-direction: column; height: 100%; position: relative;}
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #1E293B; background: rgba(2, 6, 23, 0.8); display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; overflow-y:auto; }
        .panel-title { font-weight: 900; font-size: 0.95rem; color: #E2E8F0; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 1px;}
        
        /* Ecosystem Map */
        .eco-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .domain-node { background: #0F172A; border: 1px solid #334155; padding: 12px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; transition: 0.3s; }
        .domain-node:hover { border-color: #38BDF8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(56, 189, 248, 0.1); }
        .d-name { font-weight: 800; font-size: 0.8rem; color: white; display: flex; align-items: center; gap: 8px; }
        .d-status { height: 8px; width: 8px; border-radius: 50%; background: #10B981; box-shadow: 0 0 8px #10B981;}
        .d-event { font-size: 0.65rem; color: #94A3B8; margin-top: 6px; font-family: monospace; }
        
        /* Event Stream Feed */
        .feed-item { padding: 12px 16px; border-left: 2px solid #38BDF8; background: #0F172A; margin-bottom: 8px; border-radius: 0 8px 8px 0; font-family: monospace; }
        .feed-item .time { color: #64748B; font-size: 0.7rem; margin-bottom: 4px; }
        .feed-item .event { font-weight: 700; color: #E2E8F0; font-size: 0.85rem; }
        .feed-item .meta { color: #94A3B8; font-size: 0.75rem; margin-top: 4px; }

        /* Highlights */
        .text-neon { color: #38BDF8; }
        .text-purple { color: #C084FC; }
        .text-emerald { color: #34D399; }
        
        .pulse-dot { display: inline-block; width: 6px; height: 6px; background-color: #38BDF8; border-radius: 50%; animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite; }
        @keyframes ping { 75%, 100% { transform: scale(2); opacity: 0; } }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="master-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-globe"></i> DOCTOR ASSIST <span style="font-weight: 300;">PLATFORM ZERO</span>
        </div>
        <div class="master-nav-links">
            <a href="analytics-dashboard.php" class="active">API Gateway & Analytics</a>
            <a href="clinical-intelligence.php">AI Governance</a>
            <a href="admin-dashboard.php">Audit Trails</a>
            <a href="security-center.php">IAM & Security</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:#94A3B8;">
                <i class="fas fa-shield-alt" style="color:#10B981;"></i> System Admin
            </div>
        </div>
    </div>
</div>

<div class="app-container">

    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
        <div>
            <h1 style="margin:0; font-size:1.8rem; font-weight:900; color:white;"><i class="fas fa-satellite-dish text-neon"></i> Ecosystem Watchtower</h1>
            <div style="font-size:0.9rem; color:#94A3B8; margin-top:4px;">Global state monitoring, domain-driven event streams, and clinical interoperability.</div>
        </div>
        <div style="display:flex; gap: 12px; align-items:center;">
            <div style="font-family:monospace; color:#10B981; border: 1px solid rgba(16,185,129,0.3); background: rgba(16,185,129,0.1); padding: 6px 12px; border-radius: 4px; font-size: 0.8rem; font-weight: 800;">
                <span class="pulse-dot" style="background:#10B981; margin-right:4px;"></span> ALL DOMAINS HEALTHY
            </div>
        </div>
    </div>

    <!-- Core Domains Architecture Map -->
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title"><i class="fas fa-network-wired text-neon"></i> Independent Domain Telemetry</div>
            <div style="font-size:0.75rem; color:#64748B; font-weight:700;">Live Event Bus Subscriptions</div>
        </div>
        <div class="panel-body eco-grid">
            
            <a href="identity-security-command.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-fingerprint text-purple"></i> Identity Domain</div>
                    <div class="d-event">UserAuthenticated (12ms)</div>
                </div>
                <div class="d-status"></div>
            </a>
            
            <a href="schedule-orchestration.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-calendar-check text-neon"></i> Scheduling</div>
                    <div class="d-event">AppointmentBooked (8ms)</div>
                </div>
                <div class="d-status"></div>
            </a>
            
            <a href="consultation-command.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-stethoscope text-emerald"></i> Consultations</div>
                    <div class="d-event">ConsultationStarted (14ms)</div>
                </div>
                <div class="d-status"></div>
            </a>

            <a href="medication-formulary-command.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-file-prescription" style="color:#F43F5E;"></i> Medication</div>
                    <div class="d-event">PrescriptionIssued (21ms)</div>
                </div>
                <div class="d-status"></div>
            </a>

            <a href="diagnostic-command.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-vial" style="color:#F59E0B;"></i> Lab / Pathology</div>
                    <div class="d-event">LabResultVerified (45ms)</div>
                </div>
                <div class="d-status"></div>
            </a>

            <a href="cardiology-command.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-heartbeat" style="color:#EF4444;"></i> Cardiology Hub</div>
                    <div class="d-event">ClinicalSignalDetected (9ms)</div>
                </div>
                <div class="d-status"></div>
            </a>
            
            <a href="neurology-command.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-brain" style="color:#A78BFA;"></i> Neurology Hub</div>
                    <div class="d-event">StrokePathwayActive (Sync)</div>
                </div>
                <div class="d-status"></div>
            </a>

            <a href="emergency-triage-command.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-ambulance" style="color:#F97316;"></i> Emergency / Triage</div>
                    <div class="d-event">EmergencyEvaluation (Sync)</div>
                </div>
                <div class="d-status"></div>
            </a>

            <a href="logistics-command.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-store" style="color:#14B8A6;"></i> Pharmacy & Delivery</div>
                    <div class="d-event">PharmacyOrderCreated (31ms)</div>
                </div>
                <div class="d-status"></div>
            </a>

            <a href="finance-center.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-money-bill-wave" style="color:#84CC16;"></i> Finance Domain</div>
                    <div class="d-event">PaymentCompleted (82ms)</div>
                </div>
                <div class="d-status"></div>
            </a>
            
            <a href="master-patient-index.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-history text-neon"></i> Clinical Timeline</div>
                    <div class="d-event">TimelineUpdated (Pointer Sync)</div>
                </div>
                <div class="d-status"></div>
            </a>

            <a href="clinical-intelligence.php" class="domain-node" style="text-decoration:none;">
                <div>
                    <div class="d-name"><i class="fas fa-robot" style="color:#E879F9;"></i> AI Governance</div>
                    <div class="d-event">AISuggestionReviewed (Human OK)</div>
                </div>
                <div class="d-status"></div>
            </a>

        </div>
    </div>

    <!-- Live Architecture Layout -->
    <div class="grid-layout">
        
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-shield-alt text-purple"></i> Unified Platform Validation</div>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Master Policy Principle</th>
                            <th>Enforcement Layer</th>
                            <th>Status (Zero-Trust)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><i class="fas fa-check text-emerald"></i> Domain State Independence</td>
                            <td style="color:#94A3B8;">Event Bus / Microservice Pointers</td>
                            <td><span style="font-weight:900; color:#10B981;">ENFORCED</span></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-check text-emerald"></i> Immutable Clinical Records</td>
                            <td style="color:#94A3B8;">Clinical DB Versioning / Append-Only</td>
                            <td><span style="font-weight:900; color:#10B981;">ENFORCED</span></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-check text-emerald"></i> Human Clinical Authority</td>
                            <td style="color:#94A3B8;">AI Governance & Approval Hubs</td>
                            <td><span style="font-weight:900; color:#10B981;">ENFORCED</span></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-check text-emerald"></i> Break-Glass Emergency Access</td>
                            <td style="color:#94A3B8;">IAM Triage Overrides + Audit Triggers</td>
                            <td><span style="font-weight:900; color:#10B981;">ENFORCED</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Event Stream (Right Column) -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-stream text-neon"></i> Live Event Bus</div>
            </div>
            <div class="panel-body" style="padding: 16px;">
                <div class="feed-item">
                    <div class="time">14:15:22.012 - PharmacyBus</div>
                    <div class="event text-emerald">DeliveryAssigned</div>
                    <div class="meta">order_id: DA-882-RX</div>
                </div>
                <div class="feed-item">
                    <div class="time">14:15:19.451 - NeurologyBus</div>
                    <div class="event text-neon">StrokePathwayActive</div>
                    <div class="meta">case_id: N-991 • Triaged</div>
                </div>
                <div class="feed-item">
                    <div class="time">14:15:18.992 - ClinicalNotes</div>
                    <div class="event text-purple">ClinicalRecordAmended</div>
                    <div class="meta">ptr: v2 (Diff Saved)</div>
                </div>
                <div class="feed-item" style="border-color:#F59E0B;">
                    <div class="time">14:15:15.304 - AIGovernance</div>
                    <div class="event" style="color:#F59E0B;">AISuggestionReviewed</div>
                    <div class="meta">Human Overridden • Author: Dr. R.</div>
                </div>
                <div class="feed-item">
                    <div class="time">14:15:10.111 - FinanceBus</div>
                    <div class="event text-emerald">PaymentCompleted</div>
                    <div class="meta">txn_id: 104992XX</div>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>
