<?php
require_once 'helper.php';
// Simulated Finance, Payments, Refunds & Reconciliation Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Command Center | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F8FAFC; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Finance Navbar - specialized gold/dark theme */
        .fin-nav { background: #111827; border-bottom: 2px solid #1F2937; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: var(--shadow-sm); }
        .fin-nav .navbar-brand { color: #FBBF24; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; }
        .fin-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .fin-nav-links::-webkit-scrollbar { display: none; }
        .fin-nav-links a { color: #9CA3AF; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .fin-nav-links a:hover { color: white; background: #374151; }
        .fin-nav-links a.active { color: white; background: #4B5563; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .ph-title { font-size: 1.3rem; font-weight: 900; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 12px; }
        .ph-title i { color: #D97706; }
        
        /* KPI Dashboard */
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 8px; }
        .kpi-card { background: white; border-radius: var(--radius-md); padding: 20px; border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display:flex; flex-direction:column; justify-content:space-between; }
        .kpi-title { font-size: 0.8rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;}
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: var(--clr-text-primary); }
        .kpi-sub { font-size: 0.85rem; font-weight: 700; color: var(--clr-text-secondary); margin-top: 8px; display: flex; justify-content: space-between; }
        
        /* Panel Blocks */
        .panel { background: white; border-radius: var(--radius-md); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid var(--clr-border); background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: var(--clr-primary-dark); display: flex; align-items: center; gap: 8px; }

        /* DataTable */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; background: #F1F5F9; font-size: 0.75rem; font-weight: 800; color: var(--clr-text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--clr-border); }
        .data-table td { padding: 16px; border-bottom: 1px solid var(--clr-border); font-size: 0.9rem; font-weight: 600; color: var(--clr-text-primary); }
        .data-table tbody tr:hover { background: #F8FAFC; }
        
        /* Status Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        .bg-success { background: #F0FDF4; color: #16A34A; border-color: #BBF7D0; }
        .bg-warn { background: #FFF7ED; color: #D97706; border-color: #FFEDD5; }
        .bg-danger { background: #FEF2F2; color: #DC2626; border-color: #FECACA; }
        .bg-info { background: #EFF6FF; color: #2563EB; border-color: #BFDBFE; }

        /* Buttons */
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid var(--clr-border); background: white; color: var(--clr-text-primary); display: flex; align-items: center; gap: 8px; }
        .btn:hover { background: #F1F5F9; }
        .btn-primary { background: #111827; color: white; border-color: #000; }
        .btn-primary:hover { background: #1F2937; }

        /* Double-Entry Ledger Block */
        .ledger-box { background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 8px; padding: 16px; }
        .ledger-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; font-weight: 700; color: var(--clr-text-primary); }
        .ledger-row.debit { color: #16A34A; }
        .ledger-row.credit { color: #DC2626; padding-left: 20px; }
        .ledger-total { border-top: 1px solid var(--clr-border); margin-top: 12px; padding-top: 12px; font-weight: 900; }

    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="fin-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-coins"></i> FINANCE CENTER
        </div>
        <div class="fin-nav-links">
            <a href="#"  class="active">Dashboard</a>
            <a href="#" >Transactions</a>
            <a href="#" >Refunds</a>
            <a href="#" >Settlements</a>
            <a href="#" >Doctor Payouts</a>
            <a href="#" >Financial Ledger</a>
            <a href="#" >Reconciliation <span style="background:#EF4444; color:white; padding:2px 6px; border-radius:12px; font-size:0.75rem; margin-left:4px;">6</span></a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=11" style="width:28px; height:28px; border-radius:50%; border:2px solid #F59E0B;"> Finance Admin
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    
    <div class="main-col">
        
        <div class="page-header">
            <div class="ph-title"><i class="fas fa-chart-line"></i> Executive Financial Operations</div>
            <div style="display:flex; gap:12px;">
                <button class="btn"><i class="fas fa-file-invoice-dollar"></i> Manual Adjustment</button>
                <button class="btn btn-primary"><i class="fas fa-sync-alt"></i> Run Reconciliation</button>
            </div>
        </div>

        <div class="kpi-row">
            <div class="kpi-card" style="border-top: 3px solid #10B981;">
                <div class="kpi-title">Gross Volume <i class="fas fa-wallet text-success" style="font-size:1.2rem;"></i></div>
                <div class="kpi-val">₹2,84,500</div>
                <div class="kpi-sub"><span>Today's Payments</span> <span><i class="fas fa-arrow-up text-success"></i> 4%</span></div>
            </div>
            <div class="kpi-card" style="border-top: 3px solid #F59E0B;">
                <div class="kpi-title">Platform Revenue <i class="fas fa-chart-pie text-warning" style="font-size:1.2rem;"></i></div>
                <div class="kpi-val" style="color:#D97706;">₹38,250</div>
                <div class="kpi-sub"><span>Net Commissions</span> <span><i class="fas fa-arrow-up text-success"></i> 2%</span></div>
            </div>
            <div class="kpi-card" style="border-top: 3px solid #3B82F6;">
                <div class="kpi-title">Doctor Payables <i class="fas fa-user-md text-info" style="font-size:1.2rem;"></i></div>
                <div class="kpi-val" style="color:#2563EB;">₹1,24,600</div>
                <div class="kpi-sub"><span>Awaiting Payout</span> <span>(142 Doctors)</span></div>
            </div>
            <div class="kpi-card" style="border-top: 3px solid #EF4444;">
                <div class="kpi-title">Pending Settlement <i class="fas fa-hand-holding-usd text-danger" style="font-size:1.2rem;"></i></div>
                <div class="kpi-val" style="color:#DC2626;">₹46,300</div>
                <div class="kpi-sub"><span>Reconciliation Risk</span> <span class="badge bg-danger" style="font-size:0.65rem;">6 Alerts</span></div>
            </div>
        </div>

        <div style="display:flex; gap:24px;">
            
            <!-- Left Column: Transactions & Refunds -->
            <div style="flex:2; display:flex; flex-direction:column; gap:24px;">
                
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-exchange-alt"></i> Gateway Transactions</div>
                        <div style="display:flex; gap:8px;">
                            <input type="text" placeholder="Search Txn ID, Order..." style="padding:6px 12px; border:1px solid var(--clr-border); border-radius:6px; outline:none; font-family:inherit;">
                        </div>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Txn ID</th>
                                    <th>Reference</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span style="font-family:monospace; font-weight:800; color:#4F46E5;">PAY-1021</span></td>
                                    <td>APP-8832 <span style="font-size:0.75rem; color:var(--clr-text-secondary);">(Consult)</span></td>
                                    <td style="font-weight:900;">₹115</td>
                                    <td>UPI</td>
                                    <td><span class="badge bg-success">SUCCESS</span></td>
                                </tr>
                                <tr>
                                    <td><span style="font-family:monospace; font-weight:800; color:#4F46E5;">PAY-1022</span></td>
                                    <td>ORD-8821 <span style="font-size:0.75rem; color:var(--clr-text-secondary);">(Pharma)</span></td>
                                    <td style="font-weight:900;">₹1,240</td>
                                    <td>Card</td>
                                    <td><span class="badge bg-success">SUCCESS</span></td>
                                </tr>
                                <tr>
                                    <td><span style="font-family:monospace; font-weight:800; color:#4F46E5;">PAY-1024</span></td>
                                    <td>ORD-8839 <span style="font-size:0.75rem; color:var(--clr-text-secondary);">(Pharma)</span></td>
                                    <td style="font-weight:900; color:#DC2626;">₹860</td>
                                    <td>NetBank</td>
                                    <td><span class="badge bg-danger">FAILED</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-undo"></i> Refund & Adjustments Center</div>
                    </div>
                    <div class="panel-body">
                        <div style="display:flex; justify-content:space-between; align-items:center; border:1px solid #E5E7EB; padding:16px; border-radius:6px; margin-bottom:12px;">
                            <div>
                                <div style="font-size:0.8rem; font-weight:800; color:var(--clr-text-secondary);">REF-10291</div>
                                <div style="font-weight:900; font-size:1.1rem; color:var(--clr-text-primary);">₹1,240</div>
                                <div style="font-size:0.85rem; color:var(--clr-text-secondary); margin-top:4px;">Original: PAY-88421 • Order Cancelled</div>
                            </div>
                            <div><span class="badge bg-info">PROCESSING</span></div>
                            <div>
                                <button class="btn" style="font-size:0.75rem;">View Original</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Ledger Architecture & Risk -->
            <div style="flex:1; display:flex; flex-direction:column; gap:24px;">
                
                <div class="panel" style="border-color:#FECACA;">
                    <div class="panel-head" style="background:#FEF2F2; border-bottom-color:#FECACA;">
                        <div class="panel-title" style="color:#B91C1C;"><i class="fas fa-shield-alt"></i> Reconciliation Alert</div>
                    </div>
                    <div class="panel-body">
                        
                        <div style="border:1px solid #FECACA; background:white; padding:16px; border-radius:6px;">
                            <div style="font-size:0.8rem; font-weight:800; color:#B91C1C; margin-bottom:8px; text-transform:uppercase;">Mismatch Detected</div>
                            <div style="font-size:0.9rem; font-weight:700;">Payment: PAY-88291</div>
                            
                            <div style="margin-top:12px; font-size:0.85rem; display:flex; flex-direction:column; gap:6px;">
                                <div style="display:flex; justify-content:space-between;"><span>Internal Ledger:</span> <strong>₹1,240</strong></div>
                                <div style="display:flex; justify-content:space-between;"><span>Gateway Settlement:</span> <strong style="color:#DC2626;">₹1,225</strong></div>
                                <div style="display:flex; justify-content:space-between; border-top:1px dashed #FECACA; padding-top:6px; color:#B91C1C;"><span>Difference:</span> <strong>₹15</strong></div>
                            </div>

                            <button class="btn" style="width:100%; justify-content:center; margin-top:16px; border-color:#FCA5A5; color:#B91C1C;">Manual Review Required</button>
                        </div>

                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title"><i class="fas fa-book"></i> Ledger Truth Model</div>
                    </div>
                    <div class="panel-body">
                        <div style="font-size:0.8rem; font-weight:800; color:var(--clr-text-secondary); margin-bottom:12px; text-transform:uppercase;">Txn: PAY-1021 (Audio Consult)</div>
                        
                        <div class="ledger-box">
                            <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:800; color:var(--clr-text-secondary); margin-bottom:12px; border-bottom:1px solid var(--clr-border); padding-bottom:8px;">
                                <span>ACCOUNT ENTRY</span>
                                <div style="display:flex; gap:32px;"><span>DEBIT</span><span>CREDIT</span></div>
                            </div>
                            
                            <div class="ledger-row debit">
                                <span>Patient Receivable</span>
                                <div>₹115 <span style="display:inline-block; width:50px;"></span></div>
                            </div>
                            
                            <div class="ledger-row credit">
                                <span>Doctor Payable</span>
                                <div><span style="display:inline-block; width:50px;"></span> ₹100</div>
                            </div>
                            <div class="ledger-row credit">
                                <span>Platform Revenue</span>
                                <div><span style="display:inline-block; width:50px;"></span> ₹15</div>
                            </div>

                            <div class="ledger-row ledger-total">
                                <span>TRIAL BALANCE</span>
                                <div style="display:flex; gap:32px; color:var(--clr-text-primary);"><span>₹115</span><span>₹115</span></div>
                            </div>
                        </div>

                        <div style="font-size:0.75rem; font-weight:700; color:var(--clr-text-secondary); text-align:center; margin-top:12px;">
                            Double-Entry Ledger guarantees zero silent value leakage.
                        </div>

                    </div>
                </div>

            </div>
            
        </div>

    </div>

</div>

</body>
</html>
