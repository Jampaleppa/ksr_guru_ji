<?php
require_once 'helper.php';
// Simulated Integration & External Connectivity Command Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration & Connectivity | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Integration Navbar - Deep Tech Purple theme */
        .api-nav { background: #1E1B4B; border-bottom: 2px solid #312E81; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .api-nav .navbar-brand { color: #A78BFA; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .api-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .api-nav-links::-webkit-scrollbar { display: none; }
        .api-nav-links a { color: #C4B5FD; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .api-nav-links a:hover { color: white; background: #3730A3; }
        .api-nav-links a.active { color: white; background: #4F46E5; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .ph-title { font-size: 1.3rem; font-weight: 900; color: #E0E7FF; display: flex; align-items: center; gap: 12px; }
        
        /* Provider Cards Container */
        .providers-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        
        /* Provider Card */
        .provider-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; gap: 16px; transition: 0.2s; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3); }
        .provider-card:hover { border-color: #6366F1; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.1); }
        
        .pc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #334155; padding-bottom: 12px; }
        .pc-title-area { display: flex; align-items: center; gap: 12px; }
        .pc-icon { width: 40px; height: 40px; border-radius: 8px; background: #0F172A; display: flex; justify-content: center; align-items: center; font-size: 1.2rem; color: #E2E8F0; border: 1px solid #475569; }
        .pc-title { font-size: 1rem; font-weight: 800; color: white; }
        .pc-version { font-size: 0.75rem; color: #94A3B8; font-family: monospace; background: #0F172A; padding: 2px 6px; border-radius: 4px; border: 1px solid #334155; margin-top: 4px; display: inline-block; }
        
        .pc-metrics { display: flex; justify-content: space-between; align-items: center; }
        .metric { text-align: left; }
        .metric-val { font-size: 1.1rem; font-weight: 800; color: #E2E8F0; display:flex; align-items:center; gap:6px;}
        .metric-lbl { font-size: 0.75rem; color: #94A3B8; text-transform: uppercase; margin-top:2px; }
        
        .pc-status-bar { border-left: 2px solid transparent; padding-left: 12px; font-size: 0.8rem; font-weight: 700; color: #94A3B8; display:flex; justify-content:space-between; align-items:center;}
        .status-healthy { border-left-color: #10B981; }
        .status-warn { border-left-color: #F59E0B; }
        .status-error { border-left-color: #EF4444; }

        /* API Gateway Panel */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; }

        .api-route { display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #0F172A; border: 1px solid #334155; border-radius: 6px; margin-bottom: 8px; }
        .api-method { font-family: monospace; font-size: 0.75rem; font-weight: 900; padding: 4px 8px; border-radius: 4px; margin-right: 12px; }
        .method-post { background: rgba(16, 185, 129, 0.2); color: #34D399; }
        .method-get { background: rgba(59, 130, 246, 0.2); color: #60A5FA; }
        .method-put { background: rgba(245, 158, 11, 0.2); color: #FBBF24; }
        
        .route-path { font-family: monospace; font-size: 0.85rem; color: #E2E8F0; }
        .route-latency { font-size: 0.85rem; font-weight: 700; color: #10B981; }

        /* Terminal Display */
        .terminal { background: #020617; border: 1px solid #334155; border-radius: 6px; padding: 12px; font-family: monospace; font-size: 0.8rem; color: #C4B5FD; height: 150px; overflow-y: auto; }
        .term-line { margin-bottom: 4px; display: flex; gap: 8px; }
        .term-time { color: #64748B; }
        .term-success { color: #34D399; }
        .term-warn { color: #FBBF24; }

        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        .bg-healthy { background: rgba(16, 185, 129, 0.2); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }

        /* Buttons */
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; gap: 8px; }
        .btn:hover { background: #475569; }
        .btn-primary { background: #6366F1; border-color: #4F46E5; }
        .btn-primary:hover { background: #4F46E5; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="api-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-network-wired"></i> EXTERNAL CONNECTIVITY
        </div>
        <div class="api-nav-links">
            <a href="#"  class="active">API Partners</a>
            <a href="#" >Webhooks</a>
            <a href="#" >Gateway Latency</a>
            <a href="#" >Auth Keys</a>
            <a href="#" >Service Mesh</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:28px; height:28px; border-radius:50%; border:2px solid #A78BFA;"> API Architect
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    <div class="main-col">
        
        <div class="page-header">
            <div class="ph-title"><i class="fas fa-satellite-dish" style="color:#6366F1;"></i> Platform External Integrations</div>
            <div style="display:flex; gap:12px;">
                <button class="btn"><i class="fas fa-key"></i> Rotate API Keys</button>
                <button class="btn btn-primary"><i class="fas fa-sync"></i> Sync Webhooks</button>
            </div>
        </div>

        <div class="providers-grid">
            
            <div class="provider-card">
                <div class="pc-header">
                    <div class="pc-title-area">
                        <div class="pc-icon" style="color:#25D366;"><i class="fab fa-whatsapp"></i></div>
                        <div>
                            <div class="pc-title">WhatsApp Cloud</div>
                            <div class="pc-version">API v16.0</div>
                        </div>
                    </div>
                    <span class="badge bg-healthy">Connected</span>
                </div>
                <div class="pc-metrics">
                    <div class="metric">
                        <div class="metric-val">42ms</div>
                        <div class="metric-lbl">Avg Latency</div>
                    </div>
                    <div class="metric">
                        <div class="metric-val">100%</div>
                        <div class="metric-lbl">Delivery Rate</div>
                    </div>
                </div>
                <div class="pc-status-bar status-healthy">
                    <span>Active Transport</span>
                    <i class="fas fa-check-circle" style="color:#10B981;"></i>
                </div>
            </div>

            <div class="provider-card">
                <div class="pc-header">
                    <div class="pc-title-area">
                        <div class="pc-icon" style="color:#4F46E5;"><i class="fas fa-credit-card"></i></div>
                        <div>
                            <div class="pc-title">Razorpay / Stripe</div>
                            <div class="pc-version">SDK v2.1.0</div>
                        </div>
                    </div>
                    <span class="badge bg-healthy">Connected</span>
                </div>
                <div class="pc-metrics">
                    <div class="metric">
                        <div class="metric-val">112ms</div>
                        <div class="metric-lbl">Gateway Delay</div>
                    </div>
                    <div class="metric">
                        <div class="metric-val">99.8%</div>
                        <div class="metric-lbl">Success Rate</div>
                    </div>
                </div>
                <div class="pc-status-bar status-warn">
                    <span>Minor Webhook Delay</span>
                    <i class="fas fa-exclamation-triangle" style="color:#F59E0B;"></i>
                </div>
            </div>

            <div class="provider-card">
                <div class="pc-header">
                    <div class="pc-title-area">
                        <div class="pc-icon" style="color:#0EA5E9;"><i class="fas fa-video"></i></div>
                        <div>
                            <div class="pc-title">WebRTC Streaming</div>
                            <div class="pc-version">Node v4.2</div>
                        </div>
                    </div>
                    <span class="badge bg-healthy">Connected</span>
                </div>
                <div class="pc-metrics">
                    <div class="metric">
                        <div class="metric-val">18ms</div>
                        <div class="metric-lbl">Signaling Latency</div>
                    </div>
                    <div class="metric">
                        <div class="metric-val">42</div>
                        <div class="metric-lbl">Active Rooms</div>
                    </div>
                </div>
                <div class="pc-status-bar status-healthy">
                    <span>Capacity Optimal</span>
                    <i class="fas fa-check-circle" style="color:#10B981;"></i>
                </div>
            </div>

        </div>

        <div style="display:flex; gap:24px;">
            
            <div class="panel" style="flex:1;">
                <div class="panel-head">
                    <div class="panel-title"><i class="fas fa-exchange-alt"></i> Internal API Gateway Routes</div>
                </div>
                <div class="panel-body">
                    
                    <div class="api-route">
                        <div style="display:flex; align-items:center;">
                            <span class="api-method method-post">POST</span>
                            <span class="route-path">/api/v1/payment/webhook_callback</span>
                        </div>
                        <div class="route-latency">~42ms</div>
                    </div>
                    
                    <div class="api-route">
                        <div style="display:flex; align-items:center;">
                            <span class="api-method method-get">GET</span>
                            <span class="route-path">/api/v2/prescriptions/{id}/verify</span>
                        </div>
                        <div class="route-latency">~18ms</div>
                    </div>

                    <div class="api-route">
                        <div style="display:flex; align-items:center;">
                            <span class="api-method method-put">PUT</span>
                            <span class="route-path">/api/v1/delivery/{id}/status</span>
                        </div>
                        <div class="route-latency" style="color:#F59E0B;">~195ms</div>
                    </div>

                </div>
            </div>

            <div class="panel" style="flex:1;">
                <div class="panel-head">
                    <div class="panel-title"><i class="fas fa-shield-alt"></i> Isolated Architecture Model</div>
                </div>
                <div class="panel-body">
                    <div style="font-size:0.85rem; font-weight:600; color:#94A3B8; line-height:1.6; margin-bottom:16px;">
                        This command center ensures all external dependencies (Gateways, AI Services, Messaging Providers) remain strictly sandboxed from the <strong>Core Healthcare Flow</strong>. 
                    </div>
                    <div class="terminal">
                        <div class="term-line"><span class="term-time">[14:22:11]</span> <span class="term-success">[200 OK] Received Webhook payload signature validation SUCCESS (Gateway).</span></div>
                        <div class="term-line"><span class="term-time">[14:22:14]</span> <span class="term-success">[201 CREATED] Dispatched payload to WhatsApp Message Queue.</span></div>
                        <div class="term-line"><span class="term-time">[14:22:18]</span> <span class="term-warn">[429 RATE LIMIT] Maps API warning. Engaging local cache fallback...</span></div>
                        <div class="term-line"><span class="term-time">[14:22:20]</span> <span class="term-success">[200 OK] Cache fallback successfully routed Delivery Driver Ping.</span></div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

</body>
</html>
