<?php
require_once 'helper.php';
// Simulated Delivery & Logistics Command Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Command Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Logistics Navbar - specialized deep purple theme */
        .log-nav { background: #312E81; border-bottom: 2px solid #3730A3; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: var(--shadow-sm); }
        .log-nav .navbar-brand { color: white; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; }
        .log-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .log-nav-links::-webkit-scrollbar { display: none; }
        .log-nav-links a { color: #A5B4FC; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .log-nav-links a:hover { color: white; background: #3730A3; }
        .log-nav-links a.active { color: white; background: #4338CA; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .ph-title { font-size: 1.3rem; font-weight: 900; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 12px; }
        .ph-title i { color: #4F46E5; }
        
        /* KPI Dashboard */
        .kpi-wrapper { display: flex; flex-direction: column; gap: 16px; }
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .kpi-card { background: white; border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display:flex; flex-direction:column; justify-content:space-between; }
        .kpi-title { font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 4px; display: flex; justify-content: space-between; }
        .kpi-val { font-size: 1.6rem; font-weight: 900; color: var(--clr-text-primary); margin-bottom: 8px; }
        .kpi-sub { font-size: 0.75rem; font-weight: 700; color: var(--clr-text-secondary); display: flex; align-items: center; gap: 4px; }
        
        /* Panel Blocks */
        .panel { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid var(--clr-border); background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 8px; }

        /* Map Simulation */
        .live-map { background: #E2E8F0; width: 100%; height: 350px; border-radius: 8px; position: relative; overflow: hidden; border: 1px inset #CBD5E1; display:flex; align-items:center; justify-content:center; }
        .map-marker { position: absolute; display: flex; align-items: center; gap: 6px; background: white; padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid var(--clr-border); cursor: pointer; }
        .dot { width: 10px; height: 10px; border-radius: 50%; }
        .dot-green { background: #10B981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); }
        .dot-blue { background: #3B82F6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .dot-orange { background: #F59E0B; }
        .dot-red { background: #EF4444; box-shadow: 0 0 0 3px #FCA5A5; animation: pulse 1.5s infinite; }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .map-controls { position: absolute; bottom: 16px; left: 16px; display: flex; gap: 8px; }
        
        /* DataTable */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; background: #F1F5F9; font-size: 0.75rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--clr-border); }
        .data-table td { padding: 16px; border-bottom: 1px solid var(--clr-border); font-size: 0.9rem; font-weight: 600; color: var(--clr-text-primary); }
        .data-table tbody tr:hover { background: #F8FAFC; }
        
        /* Status Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        .bg-del { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
        .bg-pick { background: #FFF7ED; color: #C2410C; border-color: #FFEDD5; }
        .bg-warn { background: #FEF2F2; color: #B91C1C; border-color: #FECACA; }
        .bg-wait { background: #F3F4F6; color: #4B5563; border-color: #E5E7EB; }

        /* Buttons */
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid var(--clr-border); background: white; color: var(--clr-text-primary); display: flex; align-items: center; gap: 8px; }
        .btn:hover { background: #F1F5F9; }
        .btn-primary { background: #4F46E5; color: white; border-color: #4338CA; }
        .btn-primary:hover { background: #4338CA; }

        /* Privacy Banner */
        .privacy-banner { background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px; font-size: 0.85rem; font-weight: 700; color: #064E3B; }
        
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="log-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-truck-fast"></i> LOGISTICS COMMAND
        </div>
        <div class="log-nav-links">
            <a href="logistics-command.php" class="active">Command Center</a>
            <a href="delivery-dashboard.php">Delivery Operations</a>
            <a href="pharmacy-orders.php">Orders & Packing</a>
            <a href="driver-dashboard.php">Driver Tracking</a>
            <a href="ambulance-acute-care.php">Ambulance Routing</a>
            <a href="master-ecosystem-watchtower.php">Watchtower</a>
            <a href="analytics-dashboard.php">Analytics</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:28px; height:28px; border-radius:50%; border:2px solid #6366F1;"> Delivery Admin
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    
    <div class="main-col">
        
        <div class="page-header">
            <div class="ph-title"><i class="fas fa-satellite-dish"></i> Real-Time Operations</div>
            <div style="display:flex; gap:12px;">
                <button class="btn"><i class="fas fa-download"></i> Export</button>
                <button class="btn btn-primary"><i class="fas fa-robot"></i> Auto-Assign Routes</button>
            </div>
        </div>

        <div class="kpi-wrapper">
            <div class="kpi-row">
                <div class="kpi-card" style="border-top: 3px solid #6366F1;">
                    <div class="kpi-title">Active Deliveries <i class="fas fa-truck text-primary"></i></div>
                    <div class="kpi-val">38</div>
                    <div class="kpi-sub">+8 logged today</div>
                </div>
                <div class="kpi-card" style="border-top: 3px solid #10B981;">
                    <div class="kpi-title">Out for Delivery <i class="fas fa-route text-success"></i></div>
                    <div class="kpi-val">24</div>
                    <div class="kpi-sub" style="color:#059669;">In Transit</div>
                </div>
                <div class="kpi-card" style="border-top: 3px solid #F59E0B;">
                    <div class="kpi-title">SLA Risk / Delayed <i class="fas fa-exclamation-triangle text-warning"></i></div>
                    <div class="kpi-val" style="color:#D97706;">6</div>
                    <div class="kpi-sub">Attention Required</div>
                </div>
                <div class="kpi-card" style="border-top: 3px solid #3B82F6;">
                    <div class="kpi-title">Drivers Online <i class="fas fa-motorcycle text-info"></i></div>
                    <div class="kpi-val">28 <span style="font-size:1rem; color:var(--clr-text-secondary);">/ 35</span></div>
                    <div class="kpi-sub">Zone Availability: High</div>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:24px;">
            
            <!-- Left Column: Map & Triage -->
            <div style="flex:2; display:flex; flex-direction:column; gap:24px;">
                
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-map-marked-alt"></i> Live Delivery Map</div>
                        <div style="display:flex; gap:8px;">
                            <span class="badge" style="background:#F1F5F9;"><span class="dot dot-green"></span> Available</span>
                            <span class="badge" style="background:#F1F5F9;"><span class="dot dot-blue"></span> Transit</span>
                            <span class="badge" style="background:#F1F5F9;"><span class="dot dot-red"></span> Deviation</span>
                        </div>
                    </div>
                    <div class="panel-body" style="padding:16px;">
                        
                        <div class="live-map">
                            
                            <!-- Stylized Map Grid -->
                            <div style="position:absolute; width:100%; height:100%; background-image: radial-gradient(#CBD5E1 1px, transparent 1px); background-size: 20px 20px; opacity:0.5;"></div>
                            
                            <!-- Route Line -->
                            <svg style="position:absolute; width:100%; height:100%; top:0; left:0; z-index:1; pointer-events:none;">
                                <path d="M 120 180 L 250 150 L 400 210" stroke="#3B82F6" stroke-width="3" fill="none" stroke-dasharray="6,4"></path>
                            </svg>

                            <!-- Markers -->
                            <div class="map-marker" style="top:25%; left:15%; z-index:2;">
                                <span class="dot dot-green"></span> D-102
                            </div>
                            <div class="map-marker" style="top:40%; left:30%; z-index:2;">
                                <i class="fas fa-store" style="color:#C2410C;"></i> PH-03
                            </div>
                            <div class="map-marker" style="top:35%; left:50%; z-index:2;">
                                <span class="dot dot-blue"></span> D-125
                            </div>
                            <div class="map-marker" style="top:50%; left:70%; z-index:2;">
                                <i class="fas fa-home" style="color:#4B5563;"></i> Patient
                            </div>

                            <!-- Deviation Alert Marker -->
                            <div class="map-marker" style="top:70%; left:45%; z-index:2; border-color:#FCA5A5;">
                                <span class="dot dot-red"></span> D-109 (Deviation)
                            </div>

                            <div class="map-controls">
                                <button class="btn" style="padding:4px 8px; font-size:0.75rem;"><i class="fas fa-plus"></i></button>
                                <button class="btn" style="padding:4px 8px; font-size:0.75rem;"><i class="fas fa-minus"></i></button>
                                <button class="btn" style="padding:4px 8px; font-size:0.75rem;">Zones</button>
                                <button class="btn" style="padding:4px 8px; font-size:0.75rem;">Traffic</button>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-list-ul"></i> Active Delivery Queue</div>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Patient City</th>
                                    <th>Driver ID</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $pdo = getAssistDB();
                                $stmt = $pdo->query("SELECT * FROM orders WHERE status IN ('pending', 'processing', 'shipped') ORDER BY id DESC LIMIT 5");
                                $activeOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach($activeOrders as $o) {
                                    $driver = $o['driver_id'] ? 'D-'.$o['driver_id'] : '—';
                                    $statusBadge = '<span class="badge bg-wait">Pending</span>';
                                    if ($o['status'] === 'shipped') $statusBadge = '<span class="badge bg-del">Out Delivery</span>';
                                    if ($o['status'] === 'processing') $statusBadge = '<span class="badge bg-pick">Processing</span>';
                                    echo "<tr>";
                                    echo "<td><span style='font-weight:800; color:#4F46E5;'>#".htmlspecialchars($o['order_number'])."</span></td>";
                                    echo "<td>".htmlspecialchars($o['city'])."</td>";
                                    echo "<td><i class='fas fa-motorcycle text-primary'></i> ".$driver."</td>";
                                    echo "<td><span style='font-family:monospace; font-weight:800;'>₹".$o['total']."</span></td>";
                                    echo "<td><span style='font-family:monospace; font-weight:800;'>".strtoupper($o['payment_method'])."</span></td>";
                                    echo "<td>".$statusBadge."</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Right Column: Sidebar Operations -->
            <div style="flex:1; display:flex; flex-direction:column; gap:24px;">
                
                <div class="panel">
                    <div class="panel-head" style="background:#FEF2F2; border-bottom-color:#FECACA;">
                        <div class="panel-title" style="color:#B91C1C;"><i class="fas fa-exclamation-triangle"></i> Tactical Triage</div>
                    </div>
                    <div class="panel-body">
                        
                        <!-- Route Deviation Alert -->
                        <div style="border:1px solid #FECACA; background:white; padding:12px; border-radius:6px; margin-bottom:16px;">
                            <div style="font-size:0.8rem; font-weight:800; color:#B91C1C; margin-bottom:4px; text-transform:uppercase;">Route Deviation Detected</div>
                            <div style="font-size:0.9rem; font-weight:700;">Driver: D-109</div>
                            <div style="font-size:0.85rem; color:var(--clr-text-secondary); margin-bottom:12px;">Deviation: +1.9 km vs System Route</div>
                            <div style="display:flex; gap:8px;">
                                <button class="btn" style="font-size:0.75rem; padding:4px 8px;">Contact Driver</button>
                                <button class="btn" style="font-size:0.75rem; padding:4px 8px; color:#B91C1C; border-color:#FECACA;">Escalate</button>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-user-shield"></i> Privacy Architecture</div>
                    </div>
                    <div class="panel-body" style="padding:16px;">
                        
                        <div class="privacy-banner">
                            <i class="fas fa-lock" style="font-size:1.2rem;"></i>
                            Minimum Necessary Access Engine Active
                        </div>

                        <div style="background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:6px; padding:12px; height:150px; overflow:hidden; position:relative;">
                            <div style="font-size:0.75rem; font-weight:800; color:var(--clr-text-secondary); text-transform:uppercase; margin-bottom:8px;">Driver Mobile Payload</div>
                            
                            <ul style="margin:0; padding-left:20px; font-size:0.85rem; color:var(--clr-text-primary); line-height:1.6; font-weight:700;">
                                <li><i class="fas fa-check text-success"></i> Order ID (#1021)</li>
                                <li><i class="fas fa-check text-success"></i> Handover OTP required</li>
                                <li><i class="fas fa-check text-success"></i> Geolocation coordinates</li>
                                <li style="color:#DC2626; text-decoration:line-through;"><i class="fas fa-times"></i> Clinical Notes Blocked</li>
                                <li style="color:#DC2626; text-decoration:line-through;"><i class="fas fa-times"></i> Medical History Blocked</li>
                            </ul>
                        </div>

                    </div>
                </div>

            </div>
            
        </div>

    </div>

</div>

</body>
</html>
