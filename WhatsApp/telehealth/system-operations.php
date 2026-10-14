<?php
require_once 'helper.php';
// Simulated Platform Operations, Infrastructure & System Health Command Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Operations & Infrastructure | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Ops Navbar - Deep technical theme */
        .ops-nav { background: #020617; border-bottom: 2px solid #1E293B; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .ops-nav .navbar-brand { color: #818CF8; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .ops-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .ops-nav-links::-webkit-scrollbar { display: none; }
        .ops-nav-links a { color: #94A3B8; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .ops-nav-links a:hover { color: white; background: #1E293B; }
        .ops-nav-links a.active { color: white; background: #312E81; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .ph-title { font-size: 1.3rem; font-weight: 900; color: #E0E7FF; display: flex; align-items: center; gap: 12px; }
        
        /* KPI Dashboard */
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); padding: 16px; border: 1px solid #334155; display:flex; flex-direction:column; justify-content:space-between; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3); }
        .kpi-title { font-size: 0.8rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;}
        .kpi-val { font-size: 1.6rem; font-weight: 900; color: white; }
        .kpi-sub { font-size: 0.85rem; font-weight: 700; color: #64748B; margin-top: 4px; display:flex; align-items:center; gap:6px;}
        
        /* Panel Blocks */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; }

        /* Status Rows */
        .health-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #334155; background: #1E293B; border-radius: 6px; margin-bottom: 8px; }
        .health-row:last-child { margin-bottom: 0; }
        .health-label { font-size: 0.9rem; font-weight: 700; color: #F8FAFC; display:flex; align-items:center; gap:12px;}
        .health-meta { font-size: 0.8rem; color: #94A3B8; font-family: monospace; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        .bg-healthy { background: rgba(16, 185, 129, 0.2); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-warn { background: rgba(245, 158, 11, 0.2); color: #FBBF24; border-color: rgba(251, 191, 36, 0.3); }
        .bg-danger { background: rgba(239, 68, 68, 0.2); color: #F87171; border-color: rgba(248, 113, 113, 0.3); }

        /* Tech Bar */
        .tech-bar-container { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .tech-bar-label { display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 700; color: #94A3B8; }
        .tech-bar { height: 8px; background: #0F172A; border-radius: 4px; overflow: hidden; }
        .tech-bar-fill { height: 100%; border-radius: 4px; }

        /* Terminal Display */
        .terminal { background: #020617; border: 1px solid #334155; border-radius: 6px; padding: 12px; font-family: monospace; font-size: 0.8rem; color: #A7F3D0; height: 150px; overflow-y: auto; }
        .term-line { margin-bottom: 4px; display: flex; gap: 8px; }
        .term-time { color: #64748B; }
        .term-err { color: #F87171; }

        /* Buttons */
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; gap: 8px; }
        .btn:hover { background: #475569; }
        .btn-primary { background: #4F46E5; border-color: #4338CA; }
        .btn-primary:hover { background: #4338CA; }
        .btn-danger { background: #DC2626; border-color: #B91C1C; }
        .btn-danger:hover { background: #B91C1C; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="ops-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-server"></i> PLATFORM OPERATIONS
        </div>
        <div class="ops-nav-links">
            <a href="#"  class="active">Infrastructure Health</a>
            <a href="#" >API Gateway</a>
            <a href="#" >Database / Cache</a>
            <a href="#" >Background Jobs</a>
            <a href="#" >WebRTC Nodes</a>
            <a href="#" >Integrations</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:28px; height:28px; border-radius:50%; border:2px solid #818CF8;"> DevOps Lead
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    
    <div class="main-col">
        
        <div class="page-header">
            <div class="ph-title"><i class="fas fa-network-wired text-primary"></i> System Telemetry & DevOps Center</div>
            <div style="display:flex; gap:12px;">
                <button class="btn"><i class="fas fa-broom"></i> Clear Cache</button>
                <button class="btn btn-primary"><i class="fas fa-sync-alt"></i> Run Health Check</button>
                <button class="btn btn-danger"><i class="fas fa-power-off"></i> Maintenance Mode</button>
            </div>
        </div>

        <div class="kpi-row">
            <div class="kpi-card" style="border-top: 3px solid #10B981;">
                <div class="kpi-title">Global Uptime <i class="fas fa-heartbeat text-success"></i></div>
                <div class="kpi-val">99.98%</div>
                <div class="kpi-sub">All Core Nodes Operational</div>
            </div>
            <div class="kpi-card" style="border-top: 3px solid #818CF8;">
                <div class="kpi-title">API Response Latency <i class="fas fa-tachometer-alt"></i></div>
                <div class="kpi-val">124ms</div>
                <div class="kpi-sub" style="color:#34D399;"><i class="fas fa-arrow-down"></i> 12ms vs Last Hour</div>
            </div>
            <div class="kpi-card" style="border-top: 3px solid #F59E0B;">
                <div class="kpi-title">Background Queue Depth <i class="fas fa-list-ol text-warning"></i></div>
                <div class="kpi-val" style="color:#FBBF24;">48</div>
                <div class="kpi-sub">Processing Rate: 12/sec</div>
            </div>
            <div class="kpi-card" style="border-top: 3px solid #EF4444;">
                <div class="kpi-title">HTTP 5xx Errors <i class="fas fa-exclamation-triangle text-danger"></i></div>
                <div class="kpi-val" style="color:#F87171;">2</div>
                <div class="kpi-sub">Within Last 15 Minutes</div>
            </div>
        </div>

        <div style="display:flex; gap:24px;">
            
            <!-- Left Infrastructure Column -->
            <div style="flex:1; display:flex; flex-direction:column; gap:24px;">
                
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-database"></i> Core Infrastructure Nodes</div>
                    </div>
                    <div class="panel-body" style="padding:16px;">
                        
                        <div class="health-row">
                            <div class="health-label"><i class="fas fa-server" style="color:#818CF8;"></i> Application (PHP-FPM)</div>
                            <div style="display:flex; align-items:center; gap:16px;">
                                <div class="health-meta">Load: 0.42 / 1.0</div>
                                <span class="badge bg-healthy">HEALTHY</span>
                            </div>
                        </div>

                        <div class="health-row">
                            <div class="health-label"><i class="fas fa-database" style="color:#34D399;"></i> Primary MySQL Cluster</div>
                            <div style="display:flex; align-items:center; gap:16px;">
                                <div class="health-meta">Conn: 142/500</div>
                                <span class="badge bg-healthy">HEALTHY</span>
                            </div>
                        </div>

                        <div class="health-row">
                            <div class="health-label"><i class="fas fa-bolt" style="color:#FBBF24;"></i> Redis Cache Layer</div>
                            <div style="display:flex; align-items:center; gap:16px;">
                                <div class="health-meta">Hit: 94.2%</div>
                                <span class="badge bg-healthy">HEALTHY</span>
                            </div>
                        </div>

                        <div class="health-row" style="border-color:#7F1D1D;">
                            <div class="health-label"><i class="fas fa-video" style="color:#F87171;"></i> WebRTC Streaming Nodes</div>
                            <div style="display:flex; align-items:center; gap:16px;">
                                <div class="health-meta">Packet Loss: 1.4%</div>
                                <span class="badge bg-warn">DEGRADED</span>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-plug"></i> External Service Integrations</div>
                    </div>
                    <div class="panel-body" style="padding:16px;">
                        
                        <div class="tech-bar-container">
                            <div class="tech-bar-label">
                                <span><i class="fab fa-whatsapp"></i> WhatsApp Cloud API</span>
                                <span style="color:#34D399;">Active (42ms)</span>
                            </div>
                            <div class="tech-bar"><div class="tech-bar-fill" style="width:100%; background:#10B981;"></div></div>
                        </div>

                        <div class="tech-bar-container">
                            <div class="tech-bar-label">
                                <span><i class="fas fa-credit-card"></i> Payment Gateway (Razorpay/Stripe)</span>
                                <span style="color:#34D399;">Active (112ms)</span>
                            </div>
                            <div class="tech-bar"><div class="tech-bar-fill" style="width:100%; background:#10B981;"></div></div>
                        </div>

                        <div class="tech-bar-container">
                            <div class="tech-bar-label">
                                <span><i class="fas fa-envelope"></i> SMTP Email Relay</span>
                                <span>Processing Queue (18)</span>
                            </div>
                            <div class="tech-bar"><div class="tech-bar-fill" style="width:100%; background:#3B82F6;"></div></div>
                        </div>

                        <div class="tech-bar-container">
                            <div class="tech-bar-label">
                                <span><i class="fas fa-map-marker-alt"></i> Maps & Routing API</span>
                                <span style="color:#FBBF24;">Rate Limit Warning</span>
                            </div>
                            <div class="tech-bar"><div class="tech-bar-fill" style="width:85%; background:#F59E0B;"></div></div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Right Systems Column -->
            <div style="flex:1; display:flex; flex-direction:column; gap:24px;">
                
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-microchip"></i> Resource Utilization</div>
                    </div>
                    <div class="panel-body">
                        
                        <div style="display:flex; justify-content:space-between; margin-bottom:16px;">
                            <div style="text-align:center;">
                                <div style="font-size:1.5rem; font-weight:900; color:white;">42%</div>
                                <div style="font-size:0.75rem; color:#94A3B8; text-transform:uppercase;">CPU Load</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:1.5rem; font-weight:900; color:white;">68%</div>
                                <div style="font-size:0.75rem; color:#94A3B8; text-transform:uppercase;">RAM (32GB)</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:1.5rem; font-weight:900; color:white;">24%</div>
                                <div style="font-size:0.75rem; color:#94A3B8; text-transform:uppercase;">Storage</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:1.5rem; font-weight:900; color:#F87171;">78%</div>
                                <div style="font-size:0.75rem; color:#94A3B8; text-transform:uppercase;">Network I/O</div>
                            </div>
                        </div>
                        
                        <div style="height: 120px; background: repeating-linear-gradient(90deg, #1E293B, #1E293B 20px, #0F172A 20px, #0F172A 40px); border-radius: 6px; border: 1px solid #334155; position: relative;">
                            <!-- Simulated Graph Line -->
                            <svg width="100%" height="100%" viewBox="0 0 400 120" preserveAspectRatio="none">
                                <path d="M0,100 L40,80 L80,90 L120,60 L160,70 L200,40 L240,50 L280,20 L320,30 L360,10 L400,20" fill="none" stroke="#818CF8" stroke-width="2" />
                            </svg>
                        </div>
                        
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-stream"></i> Stream & Async Worker Logs</div>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <div class="terminal">
                            <div class="term-line"><span class="term-time">[13:45:01]</span> <span>[INFO] WhatsApp background worker processed 12 payload messages.</span></div>
                            <div class="term-line"><span class="term-time">[13:45:04]</span> <span>[INFO] Webhook received from Payment Gateway (PAY-9921_SUCCESS).</span></div>
                            <div class="term-line"><span class="term-time">[13:45:09]</span> <span>[INFO] Video Room Allocator dynamically scaled node [RT-04].</span></div>
                            <div class="term-line"><span class="term-time">[13:45:11]</span> <span class="term-err">[WARN] Maps API returned HTTP 429 (Too Many Requests). Retrying in 5s...</span></div>
                            <div class="term-line"><span class="term-time">[13:45:13]</span> <span>[INFO] Database Async Backup completed successfully (4.2GB).</span></div>
                            <div class="term-line"><span class="term-time">[13:45:16]</span> <span>[INFO] Analytics Data Warehouse ETL Job syncing [Orders] domain.</span></div>
                        </div>
                    </div>
                </div>

            </div>
            
        </div>

    </div>

</div>

</body>
</html>
