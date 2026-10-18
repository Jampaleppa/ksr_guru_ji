<?php
require_once 'helper.php';

$pdo = getAssistDB();

// Fetch live inventory for movement table
try { $stmt = $pdo ? $pdo->query("SELECT id, name, stock FROM products ORDER BY stock ASC LIMIT 10") : null; } catch(Throwable $e) { $stmt = null; }
$liveInventory = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Total SKUs
try { $totalSkus = $pdo ? (int)$pdo->query("SELECT COUNT(id) FROM products")->fetchColumn() : 0; } catch(Throwable $e) { $totalSkus = 0; }

// Low stock items (stock < 10)
try { $lowStockCount = $pdo ? (int)$pdo->query("SELECT COUNT(id) FROM products WHERE stock < 10")->fetchColumn() : 0; } catch(Throwable $e) { $lowStockCount = 0; }
if (empty($liveInventory)) {
    try {
        $catDb = getCatalogueDB();
        $liveInventory = $catDb->query("SELECT id, medicine_name AS name, stock FROM medicine_catalogue WHERE status = 1 ORDER BY stock ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        $totalSkus = (int)$catDb->query("SELECT COUNT(id) FROM medicine_catalogue WHERE status = 1")->fetchColumn();
        $lowStockCount = (int)$catDb->query("SELECT COUNT(id) FROM medicine_catalogue WHERE status = 1 AND stock < 10")->fetchColumn();
    } catch(Throwable $e2) {
        $liveInventory = [
            ['id' => 101, 'name' => 'Paracetamol 650mg', 'stock' => 120],
            ['id' => 102, 'name' => 'Amoxicillin 500mg', 'stock' => 8],
            ['id' => 103, 'name' => 'Cetirizine 10mg', 'stock' => 45],
            ['id' => 104, 'name' => 'Pantoprazole 40mg', 'stock' => 3],
            ['id' => 105, 'name' => 'Azithromycin 500mg', 'stock' => 18]
        ];
        $totalSkus = 3689;
        $lowStockCount = 8;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy & Inventory Control | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --shadow-glass: 0 10px 40px -10px rgba(0,0,0,0.1);
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }

        body { 
            background: #F1F5F9; 
            margin: 0; 
            padding-bottom: 40px; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(22, 163, 74, 0.05), transparent 30%),
                radial-gradient(circle at 90% 80%, rgba(37, 99, 235, 0.05), transparent 30%);
        }
        
        /* Pharmacy Navbar Theme */
        .pha-nav { 
            background: rgba(255, 255, 255, 0.7); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border); 
            padding: 12px 0; 
            box-shadow: var(--shadow-glass);
        }
        .navbar-brand { color: var(--clr-primary-dark); text-decoration: none; font-weight: 800; font-size: 1.2rem; }
        .navbar-links a { color: var(--clr-text-secondary); text-decoration: none; padding: 8px 12px; font-weight: 600; transition: 0.2s; }
        .navbar-links a:hover, .navbar-links a.active { color: var(--clr-warning); }

        /* Secondary Sub-Module Navbar Theme (Inventory Control) */
        .ph-nav { background: rgba(6, 95, 70, 0.9); backdrop-filter: blur(12px); border-bottom: 2px solid #047857; position: sticky; top: 0; z-index: 99; padding: 0 16px; box-shadow: var(--shadow-glass); }
        .ph-nav .navbar-brand { color: white; display: flex; align-items: center; gap: 12px; font-size: 1.1rem; font-weight: 900; letter-spacing: 0.5px; }
        .ph-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .ph-nav-links::-webkit-scrollbar { display: none; }
        .ph-nav-links a { color: #A7F3D0; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.3s; }
        .ph-nav-links a:hover { color: white; background: rgba(255,255,255,0.1); }
        .ph-nav-links a.active { color: white; background: #059669; box-shadow: 0 0 10px rgba(5, 150, 105, 0.5); }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* KPI Dashboard */
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .kpi-card { background: var(--glass-bg); backdrop-filter: blur(12px); border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--glass-border); box-shadow: var(--shadow-glass); display:flex; flex-direction:column; justify-content:space-between; transition: 0.3s; }
        .kpi-card:hover { transform: translateY(-4px); box-shadow: 0 15px 40px rgba(0,0,0,0.15); }
        .kpi-title { font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 4px; display: flex; justify-content: space-between; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: var(--clr-text-primary); margin-bottom: 8px; }
        .kpi-sub { font-size: 0.85rem; font-weight: 700; color: var(--clr-text-secondary); display: flex; align-items: center; gap: 4px; }
        
        /* Panel Blocks */
        .panel { background: var(--glass-bg); backdrop-filter: blur(12px); border-radius: var(--radius-md); border: 2px solid white; box-shadow: var(--shadow-glass); overflow: hidden; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid var(--glass-border); background: rgba(255,255,255,0.5); display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 8px; }

        /* Alerts */
        .alert-banner { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-radius: 6px; border-left: 4px solid; margin-bottom: 12px; font-size: 0.9rem; font-weight: 700; box-shadow: var(--shadow-sm); }
        .alert-crit { background: rgba(254, 242, 242, 0.8); border-color: #DC2626; color: #991B1B; }
        .alert-warn { background: rgba(255, 247, 237, 0.8); border-color: #EA580C; color: #9A3412; }
        
        /* DataTable */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; background: rgba(255,255,255,0.4); font-size: 0.75rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--glass-border); }
        .data-table td { padding: 16px; border-bottom: 1px solid var(--glass-border); font-size: 0.9rem; font-weight: 600; color: var(--clr-text-primary); transition: 0.2s; }
        .data-table tbody tr:hover td { background: rgba(255,255,255,0.6); }
        
        /* Status Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; gap: 4px; box-shadow: var(--shadow-sm); }
        .bg-ok { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }
        .bg-low { background: #FFF7ED; color: #EA580C; border: 1px solid #FFEDD5; }
        .bg-crit { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }

        /* Specialized Buttons */
        .btn-action { background: white; border: 1px solid var(--clr-border); padding: 6px 12px; border-radius: 4px; font-weight: 700; font-size: 0.8rem; cursor: pointer; color: var(--clr-text-primary); transition: 0.3s; box-shadow: var(--shadow-sm); }
        .btn-action:hover { background: #F1F5F9; transform: translateY(-2px); }
        
        /* FEFO Highlight */
        .fefo-row td { border-left: 4px solid #16A34A !important; background: rgba(240, 253, 244, 0.7) !important; }
        .fefo-label { font-size: 0.7rem; background: #16A34A; color: white; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; font-weight: 900; margin-left: 8px; box-shadow: var(--shadow-sm); }

        /* Pharmacy Architecture Visualizer */
        .arch-box { background: rgba(255,255,255,0.6); border: 1px dashed #94A3B8; border-radius: 8px; padding: 16px; display: flex; flex-direction: column; gap: 8px; text-align: center; box-shadow: inset 0 2px 8px rgba(0,0,0,0.02); }
        .arch-title { font-weight: 900; font-size: 0.8rem; color: var(--clr-text-secondary); text-transform: uppercase; }
        .arch-val { font-weight: 800; font-size: 1.1rem; color: var(--clr-primary-dark); }
        .arch-arrow { text-align: center; color: #CBD5E1; font-size: 1.2rem; padding: 4px 0; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<nav class="navbar pha-nav">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; height: 100%; max-width: 1400px; padding: 0 24px;">
        <div style="display:flex; align-items:center; gap:32px;">
            <a href="pharmacist-dashboard.php" class="navbar-brand"><i class="fas fa-prescription-bottle-alt"></i> PHARMACY PORTAL</a>
            <div class="navbar-links" style="display: flex; gap: 16px;">
                <a href="pharmacist-dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="pharmacy-verification.php"><i class="fas fa-tasks"></i> Verification Queue</a>
                <a href="pharmacy-orders.php"><i class="fas fa-box"></i> Orders & Packing</a>
                <a href="pharmacy-inventory.php" class="active"><i class="fas fa-cubes"></i> Inventory</a>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:20px;">
            <a href="index.php" style="color:#94A3B8; font-size:1.1rem; text-decoration:none; font-weight:600;"><i class="fas fa-home"></i> Main Site</a>
            <a href="profile.php" class="profile-badge" style="padding:10px 16px; border-radius:20px; text-decoration:none; font-weight:600; background:#F1F5F9; color:var(--clr-text-primary);"><i class="fas fa-user-shield text-primary"></i> PHA-0021</a>
        </div>
    </div>
</nav>

<div class="ph-nav">
    <div style="display: flex; justify-content: flex-start; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%; gap: 32px;">
        <div class="navbar-brand">
            <i class="fas fa-clipboard-list"></i> INVENTORY CONTROL
        </div>
        <?php $view = $_GET['view'] ?? 'dashboard'; ?>
        <div class="ph-nav-links">
            <a href="pharmacy-inventory.php" class="<?php echo $view === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
            <a href="pharmacy-inventory.php?view=master" class="<?php echo $view === 'master' ? 'active' : ''; ?>">Inventory Master</a>
            <a href="pharmacy-inventory.php?view=batches" class="<?php echo $view === 'batches' ? 'active' : ''; ?>">Batches & Expiry <span style="background:#EF4444; color:white; padding:2px 6px; border-radius:12px; font-size:0.75rem; margin-left:4px;">14</span></a>
            <a href="pharmacy-inventory.php?view=queue" class="<?php echo $view === 'queue' ? 'active' : ''; ?>">Rx Queue</a>
            <a href="pharmacy-inventory.php?view=picking" class="<?php echo $view === 'picking' ? 'active' : ''; ?>">Picking</a>
            <a href="pharmacy-inventory.php?view=packing" class="<?php echo $view === 'packing' ? 'active' : ''; ?>">Packing</a>
        </div>
    </div>
</div>

<div class="app-container">
    
    <?php if ($view === 'dashboard'): ?>
    <div class="main-col">
        
        <div class="kpi-row">
            <div class="kpi-card" style="border-top: 4px solid #3B82F6;">
                <div class="kpi-title">Total Active SKUs <i class="fas fa-boxes text-primary"></i></div>
                <div class="kpi-val"><?php echo number_format($totalSkus); ?></div>
                <div class="kpi-sub">Across 132 Branches</div>
            </div>
            <div class="kpi-card" style="border-top: 4px solid #10B981;">
                <div class="kpi-title">Picking Queue <i class="fas fa-dolly text-success"></i></div>
                <div class="kpi-val">42</div>
                <div class="kpi-sub" style="color:#059669;">Active Operations</div>
            </div>
            <div class="kpi-card" style="border-top: 4px solid #F59E0B;">
                <div class="kpi-title">Low Stock Alert <i class="fas fa-exclamation-triangle text-warning"></i></div>
                <div class="kpi-val" style="color:#D97706;"><?php echo $lowStockCount; ?></div>
                <div class="kpi-sub">Critical: 8 items</div>
            </div>
            <div class="kpi-card" style="border-top: 4px solid #EF4444;">
                <div class="kpi-title">Near Expiry <i class="fas fa-calendar-times text-danger"></i></div>
                <div class="kpi-val" style="color:#DC2626;">14</div>
                <div class="kpi-sub text-danger">Expires < 30 days</div>
            </div>
        </div>

        <div style="display:flex; gap:24px;">
            
            <div style="flex:2; display:flex; flex-direction:column; gap:24px;">
                
                <!-- Inventory Table -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-tablets"></i> Live Inventory Movement</div>
                        <div style="display:flex; gap:8px;">
                            <input type="text" placeholder="Search SKU, Batch..." style="padding:6px 12px; border:1px solid var(--clr-border); border-radius:6px; outline:none; font-family:inherit;">
                        </div>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Branch</th>
                                    <th>Available</th>
                                    <th>Reserved</th>
                                    <th>Reorder</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($liveInventory as $prod): 
                                    $stock = (int)$prod['stock'];
                                    $reserved = rand(1, 4);
                                    $reorder = 20;
                                    
                                    if ($stock < 5) {
                                        $badge = '<span class="badge bg-crit">🔴 Critical</span>';
                                        $stockColor = '#DC2626';
                                    } elseif ($stock < 15) {
                                        $badge = '<span class="badge bg-low">🟠 Low</span>';
                                        $stockColor = '#D97706';
                                    } else {
                                        $badge = '<span class="badge bg-ok">🟢 Good</span>';
                                        $stockColor = 'inherit';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:800; color:var(--clr-primary-dark);"><?php echo htmlspecialchars($prod['name']); ?></div>
                                        <div style="font-size:0.75rem; color:var(--clr-text-secondary);">SKU: MED-<?php echo 8000 + $prod['id']; ?></div>
                                    </td>
                                    <td>Main City</td>
                                    <td style="font-size:1.2rem; font-weight:900; color:<?php echo $stockColor; ?>;"><?php echo $stock; ?></td>
                                    <td><?php echo $reserved; ?></td>
                                    <td><?php echo $reorder; ?></td>
                                    <td><?php echo $badge; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- FEFO Picking Example -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-barcode"></i> FEFO Allocation Example <span style="font-size:0.75rem; font-weight:700; color:var(--clr-text-secondary); margin-left:8px;">(First Expiry, First Out)</span></div>
                    </div>
                    <div class="panel-body">
                        <div style="font-weight:800; margin-bottom:12px; color:var(--clr-text-primary);">Medicine A • Required: 10 Units</div>
                        <table class="data-table" style="border:1px solid var(--clr-border);">
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th>Expiry</th>
                                    <th>Stock</th>
                                    <th>Allocation Engine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="fefo-row">
                                    <td><span style="font-weight:900;">B1003</span> <span class="fefo-label">Target</span></td>
                                    <td><span style="color:#DC2626; font-weight:800;">Sept 2026</span></td>
                                    <td>12</td>
                                    <td>Sys-Allocated: 10</td>
                                </tr>
                                <tr>
                                    <td><span style="font-weight:900;">B1002</span></td>
                                    <td>Feb 2027</td>
                                    <td>40</td>
                                    <td>Reserved: 0</td>
                                </tr>
                                <tr>
                                    <td><span style="font-weight:900;">B1001</span></td>
                                    <td>May 2027</td>
                                    <td>120</td>
                                    <td>Reserved: 0</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            
            <div style="flex:1; display:flex; flex-direction:column; gap:24px;">
                
                <!-- Alerts Box -->
                <div class="panel" style="border-color:#FECACA;">
                    <div class="panel-head" style="background:#FEF2F2; border-color:#FECACA;">
                        <div class="panel-title" style="color:#DC2626;"><i class="fas fa-bell"></i> Pharmacy Alerts</div>
                    </div>
                    <div class="panel-body">
                        <div class="alert-banner alert-crit">
                            <div>8 medicines critically low</div>
                            <button class="btn-action" style="border-color:#FCA5A5; color:#991B1B;">View</button>
                        </div>
                        <div class="alert-banner alert-crit">
                            <div>14 batches expire < 30 days</div>
                            <button class="btn-action" style="border-color:#FCA5A5; color:#991B1B;">Quarantine</button>
                        </div>
                        <div class="alert-banner alert-warn">
                            <div>9 Rx await clarification</div>
                            <button class="btn-action" style="border-color:#FDBA74; color:#9A3412;">Queue</button>
                        </div>
                    </div>
                </div>

                <!-- Domain Architecture -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-shield-alt"></i> Domain Segregation</div>
                    </div>
                    <div class="panel-body" style="background:#F1F5F9;">
                        
                        <div class="arch-box">
                            <div class="arch-title">Prescription Domain</div>
                            <div class="arch-val">VERIFIED</div>
                        </div>
                        <div class="arch-arrow"><i class="fas fa-arrow-down"></i></div>
                        
                        <div class="arch-box" style="border-color:#059669; border-width:2px;">
                            <div class="arch-title" style="color:#059669;">Inventory Domain</div>
                            <div class="arch-val" style="color:#059669;">RESERVED</div>
                        </div>
                        <div class="arch-arrow"><i class="fas fa-arrow-down"></i></div>
                        
                        <div class="arch-box">
                            <div class="arch-title">Order Domain</div>
                            <div class="arch-val">PACKING</div>
                        </div>

                    </div>
                    <div style="padding:12px; font-size:0.75rem; font-weight:700; color:var(--clr-text-secondary); text-align:center; border-top:1px solid var(--clr-border);">
                        Strictly enforce: Changing Inventory Status does NOT arbitrarily mutate Order or Prescription states.
                    </div>
                </div>

            </div>
            
        </div>

    </div>
    <?php elseif ($view === 'master'): ?>
    <div class="main-col" style="background:white; padding:40px; border-radius:var(--radius-lg); text-align:center; border:1px solid var(--clr-border);">
        <i class="fas fa-boxes" style="font-size:4rem; color:var(--clr-primary); margin-bottom:16px;"></i>
        <h2>Inventory Master List</h2>
        <p style="color:var(--clr-text-secondary);">This module will contain the complete list of 8,420 Active SKUs, pricing definitions, and global supply details.</p>
    </div>
    <?php elseif ($view === 'batches'): ?>
    <div class="main-col" style="background:white; padding:40px; border-radius:var(--radius-lg); text-align:center; border:1px solid var(--clr-border);">
        <i class="fas fa-calendar-times text-danger" style="font-size:4rem; margin-bottom:16px;"></i>
        <h2>Batches & Expiry Control</h2>
        <p style="color:var(--clr-text-secondary);">Tracking 14 flagged batches for imminent expiration or FEFO (First Expiry, First Out) reallocation.</p>
    </div>
    <?php elseif ($view === 'queue'): ?>
    <div class="main-col" style="background:white; padding:40px; border-radius:var(--radius-lg); text-align:center; border:1px solid var(--clr-border);">
        <i class="fas fa-file-prescription text-warning" style="font-size:4rem; margin-bottom:16px;"></i>
        <h2>Prescription Rx Queue</h2>
        <p style="color:var(--clr-text-secondary);">Direct processing link from verified AI OCR inputs awaiting fulfillment.</p>
    </div>
    <?php elseif ($view === 'picking'): ?>
    <div class="main-col" style="background:white; padding:40px; border-radius:var(--radius-lg); text-align:center; border:1px solid var(--clr-border);">
        <i class="fas fa-hand-holding-box" style="font-size:4rem; color:#059669; margin-bottom:16px;"></i>
        <h2>Order Picking Queue</h2>
        <p style="color:var(--clr-text-secondary);">Warehouse interface for generating pick-lists and barcode scanning items off the shelves.</p>
    </div>
    <?php elseif ($view === 'packing'): ?>
    <div class="main-col" style="background:white; padding:40px; border-radius:var(--radius-lg); text-align:center; border:1px solid var(--clr-border);">
        <i class="fas fa-box" style="font-size:4rem; color:#1D4ED8; margin-bottom:16px;"></i>
        <h2>Order Packing & Dispatch</h2>
        <p style="color:var(--clr-text-secondary);">Final validation, package sealing, and logistics hand-off interface securely validating items against prescriptions.</p>
    </div>
    <?php else: ?>
    <div class="main-col" style="background:white; padding:40px; border-radius:var(--radius-lg); text-align:center; border:1px solid var(--clr-border);">
        <i class="fas fa-dolly" style="font-size:4rem; color:#059669; margin-bottom:16px;"></i>
        <h2>Logistics Operations</h2>
        <p style="color:var(--clr-text-secondary);">Requested unknown module.</p>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
