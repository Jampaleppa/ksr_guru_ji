<?php
require_once 'helper.php';
// Pull from session if available
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docName = $_SESSION['user_name'] ?? 'DR. D C Rathod';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings & Payouts | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #F1F5F9; padding-bottom: 80px; }
        
        /* Doctor Navbar */
        .doc-nav { background: #0F172A; }
        .doc-nav .navbar-brand { color: white; }
        .doc-nav .navbar-links a { color: #94A3B8; }
        .doc-nav .navbar-links a:hover, .doc-nav .navbar-links a.active { color: white; }
        .doc-nav .profile-badge { background: #1E293B; color: white; border: 1px solid #334155; }

        .container-main { max-width: 1400px; margin: 40px auto; padding: 0 20px; }
        
        /* Headers & Metrics */
        .dash-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
        .dh-title { font-size: 1.5rem; font-weight: 800; color: var(--clr-primary-dark); margin-bottom: 8px; }
        .dh-sub { color: var(--clr-text-secondary); font-size: 0.95rem; font-weight: 600; }

        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 24px; }
        .metric-card { background: white; padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); display: flex; align-items: center; justify-content: space-between; }
        
        .mc-val { font-size: 1.8rem; font-weight: 800; color: var(--clr-text-primary); line-height: 1; margin-bottom: 8px; display: flex; align-items: baseline; gap: 8px; }
        .mc-trend { font-size: 0.85rem; font-weight: 700; background: #F0FDF4; color: #16A34A; padding: 2px 8px; border-radius: 12px; }
        .mc-label { font-size: 0.9rem; font-weight: 700; color: var(--clr-text-secondary); text-transform: uppercase; }
        .mc-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

        .metrics-subgrid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px; }
        .sub-metric { background: white; padding: 16px 24px; border-radius: var(--radius-md); border: 1px solid var(--clr-border); display: flex; justify-content: space-between; align-items: center; }

        /* General Card Box */
        .c-box { background: white; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); box-shadow: var(--shadow-sm); padding: 24px; margin-bottom: 32px; }
        .cb-head { font-weight: 800; font-size: 1.1rem; color: var(--clr-primary-dark); border-bottom: 1px solid var(--clr-border); padding-bottom: 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
        
        .btn-action { background: white; border: 1px solid var(--clr-border); padding: 8px 16px; border-radius: 6px; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.2s; color: var(--clr-text-primary); }
        .btn-action:hover { background: #F8FAFC; border-color: var(--clr-primary); color: var(--clr-primary); }
        .btn-primary { background: var(--clr-primary); color: white; border-color: var(--clr-primary); }
        .btn-primary:hover { background: var(--clr-primary-dark); color: white; }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; font-weight: 700; color: var(--clr-text-secondary); font-size: 0.85rem; text-transform: uppercase; border-bottom: 1px solid var(--clr-border); }
        .data-table td { padding: 16px; border-bottom: 1px solid var(--clr-border); vertical-align: middle; }
        .data-table tr:hover { background: #F8FAFC; }

        .tx-id { font-family: monospace; font-weight: 800; color: var(--clr-primary); }
        .tx-amount { font-weight: 800; color: var(--clr-text-primary); font-size: 1.05rem; }
        
        .tx-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
        .tb-success { background: #F0FDF4; color: #16A34A; }
        .tb-refund { background: #FEF2F2; color: #DC2626; }
        .tb-pending { background: #FEF9C3; color: #CA8A04; }

        /* Financial Grid Layout */
        .fin-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 32px; }

        /* List items for breakdown */
        .breakdown-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed var(--clr-border); font-size: 0.95rem; }
        .breakdown-row.total { border-bottom: none; border-top: 2px solid var(--clr-text-primary); font-weight: 800; font-size: 1.1rem; margin-top: 8px; padding-top: 16px; }

        /* Modals */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; pointer-events: none; transition: 0.3s; padding: 16px; backdrop-filter: blur(4px); }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-card { background: white; width: 100%; max-width: 500px; border-radius: var(--radius-lg); box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; transform: translateY(20px); transition: 0.3s; }
        .modal-overlay.active .modal-card { transform: translateY(0); }
        .m-head { padding: 20px 24px; border-bottom: 1px solid var(--clr-border); display: flex; justify-content: space-between; align-items: center; font-weight: 800; font-size: 1.25rem; color: var(--clr-primary-dark); background: #F8FAFC; }
        .m-body { padding: 24px; }
        .m-foot { padding: 16px 24px; border-top: 1px solid var(--clr-border); display: flex; justify-content: flex-end; gap: 16px; background: #F8FAFC; }

        .search-bar { background: white; border: 1px solid var(--clr-border); padding: 10px 16px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 12px; width: 300px; }
        .search-bar input { border: none; outline: none; width: 100%; font-size: 0.95rem; font-family: inherit; }
        @media (max-width: 1024px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .metrics-subgrid {
                grid-template-columns: repeat(2, 1fr);
            }

            .fin-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container-main {
                padding: 0 14px;
                margin: 20px auto;
            }

            .dash-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .search-bar {
                width: 100% !important;
            }

            .c-box {
                padding: 16px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .cb-head {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .data-table {
                min-width: 600px;
            }

            .m-head,
            .m-body,
            .m-foot {
                padding: 16px;
            }

            .m-foot {
                flex-direction: column;
            }

            .m-foot button {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .dh-title {
                font-size: 1.3rem;
            }

            .dh-sub {
                font-size: 0.85rem;
            }

            .metrics-grid,
            .metrics-subgrid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .metric-card {
                padding: 16px;
            }

            .mc-val {
                font-size: 1.5rem;
            }

            .mc-icon {
                width: 46px;
                height: 46px;
                font-size: 1.3rem;
            }

            .sub-metric {
                padding: 12px 16px;
            }

            .modal-card {
                border-radius: 14px;
            }
        }

        @media (max-width: 360px) {
            .dh-title {
                font-size: 1.15rem;
            }

            .mc-val {
                font-size: 1.35rem;
            }
        }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0;
include __DIR__ . '/partials/brandbar.php';
$navActive = 'earnings';
include __DIR__ . '/partials/doctor-nav.php'; ?>








<div class="container-main">
    
    <div class="dash-header">
        <div>
            <div class="dh-title"><i class="fas fa-coins"></i> Earnings & Payments</div>
            <div class="dh-sub">Track consultation earnings, transactions, refunds and payouts</div>
        </div>
        <div style="display:flex; gap:12px;">
            <select style="padding:8px 16px; border:1px solid var(--clr-border); border-radius:6px; font-family:inherit; font-weight:700;">
                <option>This Month (Sep 2026)</option>
                <option>Last Month (Aug 2026)</option>
            </select>
            <button class="btn-action"><i class="fas fa-download"></i> Download Statement</button>
        </div>
    </div>

    <!-- MAIN METRICS -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div>
                <div class="mc-label" style="margin-bottom:8px;">Total Earnings</div>
                <div class="mc-val">₹2,84,650 <span class="mc-trend"><i class="fas fa-arrow-up"></i> 14.8%</span></div>
            </div>
            <div class="mc-icon" style="background:#F1F5F9; color:#64748B;"><i class="fas fa-chart-line"></i></div>
        </div>
        <div class="metric-card">
            <div>
                <div class="mc-label" style="margin-bottom:8px;">This Month</div>
                <div class="mc-val">₹42,850 <span class="mc-trend"><i class="fas fa-arrow-up"></i> 12.4%</span></div>
            </div>
            <div class="mc-icon" style="background:#EFF6FF; color:#2563EB;"><i class="fas fa-calendar-alt"></i></div>
        </div>
        <div class="metric-card">
            <div>
                <div class="mc-label" style="margin-bottom:8px;">Consultations</div>
                <div class="mc-val">384 <span class="mc-trend"><i class="fas fa-arrow-up"></i> 8.6%</span></div>
            </div>
            <div class="mc-icon" style="background:#FEF9C3; color:#CA8A04;"><i class="fas fa-stethoscope"></i></div>
        </div>
        <div class="metric-card" style="background:#F0FDF4; border-color:#BBF7D0;">
            <div>
                <div class="mc-label" style="margin-bottom:8px; color:#166534;">Available Payout</div>
                <div class="mc-val" style="color:#166534;">₹31,450</div>
            </div>
            <div class="mc-icon" style="background:#dcfce7; color:#16A34A;"><i class="fas fa-wallet"></i></div>
        </div>
    </div>

    <div class="metrics-subgrid">
        <div class="sub-metric">
            <div>
                <div class="mc-label">Audio</div>
                <div class="mc-val" style="font-size:1.4rem; margin:4px 0 0 0;">₹11,500</div>
            </div>
            <i class="fas fa-headphones" style="font-size:1.5rem; color:var(--clr-text-secondary);"></i>
        </div>
        <div class="sub-metric">
            <div>
                <div class="mc-label">Video</div>
                <div class="mc-val" style="font-size:1.4rem; margin:4px 0 0 0;">₹28,750</div>
            </div>
            <i class="fas fa-video" style="font-size:1.5rem; color:var(--clr-text-secondary);"></i>
        </div>
        <div class="sub-metric" style="border-color:#FECACA;">
            <div>
                <div class="mc-label" style="color:#DC2626;">Refunds</div>
                <div class="mc-val" style="font-size:1.4rem; margin:4px 0 0 0; color:#DC2626;">₹1,250</div>
            </div>
            <i class="fas fa-undo" style="font-size:1.5rem; color:#DC2626;"></i>
        </div>
    </div>

    <!-- TRANSACTIONS & BREAKDOWN -->
    <div class="fin-grid">
        
        <!-- TRANSACTIONS -->
        <div class="c-box">
            <div class="cb-head">
                <div><i class="fas fa-list"></i> Transactions</div>
                <div class="search-bar">
                    <i class="fas fa-search" style="color:var(--clr-text-secondary);"></i>
                    <input type="text" placeholder="Search TXN ID...">
                </div>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>TXN ID</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>05 Sep</td>
                        <td class="tx-id">TXN-84921</td>
                        <td><i class="fas fa-video text-primary"></i> Video</td>
                        <td class="tx-amount">+₹125</td>
                        <td><span class="tx-badge tb-success">SUCCESS</span></td>
                        <td><button class="btn-action" onclick="openModal('txn-modal')">View</button></td>
                    </tr>
                    <tr>
                        <td>05 Sep</td>
                        <td class="tx-id">TXN-84918</td>
                        <td><i class="fas fa-phone text-primary"></i> Audio</td>
                        <td class="tx-amount">+₹100</td>
                        <td><span class="tx-badge tb-success">SUCCESS</span></td>
                        <td><button class="btn-action">View</button></td>
                    </tr>
                    <tr>
                        <td>04 Sep</td>
                        <td class="tx-id">TXN-84877</td>
                        <td><i class="fas fa-video text-primary"></i> Video</td>
                        <td class="tx-amount" style="color:#DC2626;">-₹125</td>
                        <td><span class="tx-badge tb-refund">REFUNDED</span></td>
                        <td><button class="btn-action">View</button></td>
                    </tr>
                    <tr>
                        <td>04 Sep</td>
                        <td class="tx-id">TXN-84842</td>
                        <td><i class="fas fa-phone text-primary"></i> Audio</td>
                        <td class="tx-amount">+₹100</td>
                        <td><span class="tx-badge tb-success">SUCCESS</span></td>
                        <td><button class="btn-action">View</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- BREAKDOWN & PAYOUT -->
        <div>
            
            <div class="c-box">
                <div class="cb-head" style="margin-bottom:8px;">Earnings Breakdown</div>
                <div style="font-size:0.85rem; color:var(--clr-text-secondary); margin-bottom:16px;">September 2026</div>

                <div class="breakdown-row">
                    <span>Audio Consults (84 &times; ₹100)</span>
                    <span style="font-weight:700;">₹8,400</span>
                </div>
                <div class="breakdown-row">
                    <span>Video Consults (163 &times; ₹125)</span>
                    <span style="font-weight:700;">₹20,375</span>
                </div>
                <div class="breakdown-row">
                    <span>Follow-up (22 &times; ₹100)</span>
                    <span style="font-weight:700;">₹2,200</span>
                </div>
                
                <div class="breakdown-row" style="margin-top:16px; border-top:1px solid var(--clr-border); padding-top:16px; font-weight:700; color:var(--clr-text-primary);">
                    <span>Gross Doctor Earnings</span>
                    <span>₹30,975</span>
                </div>
                <div class="breakdown-row" style="color:#DC2626;">
                    <span>Platform Adjustments</span>
                    <span>-₹750</span>
                </div>
                <div class="breakdown-row" style="color:#DC2626;">
                    <span>Refund Adjustments</span>
                    <span>-₹500</span>
                </div>

                <div class="breakdown-row total">
                    <span>Net Earnings</span>
                    <span>₹29,725</span>
                </div>
            </div>

            <div class="c-box" style="background:#F8FAFC;">
                <div class="cb-head">Payouts</div>
                
                <div style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.9rem; font-weight:700; color:var(--clr-text-secondary);">Last Payout</span>
                    <span style="font-weight:800;">₹25,200 (01 Sep)</span>
                </div>
                <div style="margin-bottom:24px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.9rem; font-weight:700; color:var(--clr-text-secondary);">Payout Account</span>
                    <span style="font-family:monospace; font-weight:800;">•••• 4821</span>
                </div>

                <button class="btn-action btn-primary" style="width:100%; padding:12px;" onclick="openModal('payout-modal')">Request Payout (₹31,450)</button>
            </div>

        </div>

    </div>

</div>

<!-- TXN DETAILS MODAL -->
<div class="modal-overlay" id="txn-modal">
    <div class="modal-card">
        <div class="m-head">
            <div><i class="fas fa-receipt"></i> Transaction Details</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('txn-modal')">&times;</button>
        </div>
        <div class="m-body">
            
            <div style="display:flex; justify-content:space-between; margin-bottom:24px;">
                <div>
                    <div style="font-size:0.8rem; font-weight:800; color:var(--clr-text-secondary); text-transform:uppercase;">Transaction ID</div>
                    <div style="font-family:monospace; font-weight:800; font-size:1.1rem; color:var(--clr-primary);">TXN-84921</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.8rem; font-weight:800; color:var(--clr-text-secondary); text-transform:uppercase;">Date</div>
                    <div style="font-weight:700;">05 Sep 2026, 10:42 AM</div>
                </div>
            </div>

            <div style="background:#F8FAFC; border:1px solid var(--clr-border); border-radius:var(--radius-sm); padding:16px; margin-bottom:24px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; font-size:0.95rem;">
                    <div><span style="color:var(--clr-text-secondary);">Appt ID:</span> <br><strong>APP-20260905-1021</strong></div>
                    <div><span style="color:var(--clr-text-secondary);">Consultation:</span> <br><strong>Video</strong></div>
                    <div><span style="color:var(--clr-text-secondary);">Patient:</span> <br><strong>#DA-10482</strong></div>
                    <div><span style="color:var(--clr-text-secondary);">Doc State:</span> <br><strong style="color:#16A34A;">COMPLETED</strong></div>
                </div>
            </div>

            <div style="font-size:0.95rem; margin-bottom:24px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; padding-bottom:8px; border-bottom:1px dashed var(--clr-border);">
                    <span>Patient Payment (Total)</span>
                    <span style="font-weight:700;">₹140</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; color:var(--clr-text-secondary);">
                    <span>Platform Charge</span>
                    <span>-₹15</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:8px; padding-top:8px; border-top:1px solid var(--clr-border); font-size:1.1rem; font-weight:800; color:var(--clr-text-primary);">
                    <span>Doctor Net Earning</span>
                    <span style="color:#16A34A;">₹125</span>
                </div>
            </div>

            <div style="text-align:center; font-size:0.85rem; color:var(--clr-text-secondary);">
                Payment Gateway Ref: <span style="font-family:monospace;">PG-ZX982Y11</span> <br>
                <span style="color:#16A34A; font-weight:800; display:inline-block; margin-top:8px;"><i class="fas fa-check-circle"></i> PAYMENT SUCCESSFUL</span>
            </div>

        </div>
        <div class="m-foot">
            <button class="btn-action"><i class="fas fa-file-pdf"></i> Download Invoice</button>
            <button class="btn-action btn-primary" onclick="closeModal('txn-modal')">Close</button>
        </div>
    </div>
</div>

<!-- PAYOUT MODAL -->
<div class="modal-overlay" id="payout-modal">
    <div class="modal-card">
        <div class="m-head" style="background:#F0FDF4;">
            <div><i class="fas fa-money-check-alt" style="color:#16A34A;"></i> Request Payout</div>
            <button style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" onclick="closeModal('payout-modal')">&times;</button>
        </div>
        <div class="m-body">
            
            <div style="text-align:center; margin-bottom:24px;">
                <div style="font-size:0.9rem; font-weight:700; color:var(--clr-text-secondary); text-transform:uppercase;">Available Balance</div>
                <div style="font-size:2.5rem; font-weight:900; color:#166534;">₹31,450</div>
            </div>

            <div style="background:#F8FAFC; border:1px solid var(--clr-border); border-radius:var(--radius-sm); padding:16px; margin-bottom:24px;">
                <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:12px;">
                    <i class="fas fa-university" style="font-size:1.5rem; color:var(--clr-text-secondary); margin-top:4px;"></i>
                    <div>
                        <div style="font-weight:800;">HDFC Bank (•••• 4821)</div>
                        <div style="font-size:0.85rem; color:var(--clr-text-secondary);">IFSC: HDFC0001234 <br> <?php echo htmlspecialchars($docName); ?></div>
                    </div>
                    <div style="margin-left:auto; background:#F0FDF4; color:#16A34A; font-size:0.7rem; font-weight:800; padding:2px 8px; border-radius:10px;">VERIFIED</div>
                </div>
            </div>
            
            <div style="font-size:0.9rem; color:var(--clr-text-secondary); margin-bottom:8px;">
                <i class="fas fa-info-circle"></i> Payout processing typically takes 1-2 business days. Earnings are mapped directly to your verified payout account.
            </div>

        </div>
        <div class="m-foot">
            <button class="btn-action" onclick="closeModal('payout-modal')">Cancel</button>
            <button class="btn-action btn-primary" style="background:#166534; border-color:#166534;" onclick="closeModal('payout-modal')">Confirm Request Payout</button>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }
</script>

</body>
</html>
