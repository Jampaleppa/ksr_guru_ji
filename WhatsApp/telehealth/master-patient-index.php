<?php
require_once 'helper.php';
// Simulated Master Patient Index & Identity Management Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Patient Index | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Identity Navbar */
        .mpi-nav { background: #1E1B4B; border-bottom: 2px solid #312E81; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .mpi-nav .navbar-brand { color: #818CF8; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .mpi-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .mpi-nav-links::-webkit-scrollbar { display: none; }
        .mpi-nav-links a { color: #A5B4FC; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .mpi-nav-links a:hover { color: white; background: #3730A3; }
        .mpi-nav-links a.active { color: white; background: #4F46E5; }

        .app-container { display: flex; flex: 1; max-width: 1400px; margin: 0 auto; width: 100%; padding: 24px; gap: 24px; }
        
        .sidebar { width: 260px; flex-shrink: 0; display: flex; flex-direction: column; gap: 16px; }
        .main-col { flex: 1; display: flex; flex-direction: column; gap: 24px; min-width: 0; }

        /* Panel Standard */
        .panel { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; overflow: hidden; display: flex; flex-direction: column; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid #334155; background: #0F172A; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; flex: 1; }
        .panel-title { font-weight: 900; font-size: 1.05rem; color: #E0E7FF; display: flex; align-items: center; gap: 8px; }

        /* Sidebar Navigation */
        .side-nav { display: flex; flex-direction: column; gap: 4px; }
        .side-title { font-size: 0.75rem; font-weight: 800; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px 4px 16px; }
        .side-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; color: #CBD5E1; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem; transition: 0.2s; }
        .side-item:hover { background: #334155; color: white; }
        .side-item.active { background: rgba(79, 70, 229, 0.2); color: #818CF8; border-left: 3px solid #818CF8; border-radius: 0 6px 6px 0; }
        .side-badge { margin-left: auto; background: #EF4444; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 12px; font-weight: 900; }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 8px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; }
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
        .kpi-icon { position: absolute; right: -10px; bottom: -10px; font-size: 4rem; opacity: 0.05; color: white; }

        /* Duplicate Review Card */
        .duplicate-card { background: #0F172A; border: 1px solid #F59E0B; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
        .dc-header { background: rgba(245, 158, 11, 0.1); padding: 12px 20px; border-bottom: 1px solid rgba(245, 158, 11, 0.3); display: flex; justify-content: space-between; align-items: center; }
        .dc-title { font-weight: 900; color: #FBBF24; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
        .dc-score { background: #F59E0B; color: #000; padding: 4px 10px; border-radius: 12px; font-weight: 900; font-size: 0.8rem; display: flex; align-items: center; gap: 4px;}
        
        .dc-body { padding: 0; display: flex; }
        .dc-col { flex: 1; padding: 20px; }
        .dc-col:first-child { border-right: 1px solid #334155; }
        
        .dc-field { display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center; }
        .dc-lbl { font-size: 0.75rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; }
        .dc-val { font-size: 0.95rem; color: white; font-weight: 600; display:flex; align-items:center; gap:6px;}
        .match-match { color: #10B981; }
        .match-diff { color: #EF4444; }
        .dc-master-tag { background: #3B82F6; color: white; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; font-weight: 900; }

        .dc-footer { padding: 16px 20px; border-top: 1px solid #334155; display: flex; justify-content: flex-end; gap: 12px; background: #1E293B; }

        /* Identifier Grid */
        .id-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
        .id-card { background: #0F172A; border: 1px solid #334155; border-radius: 8px; padding: 16px; }
        .id-card-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .id-type { font-weight: 800; font-size: 0.85rem; color: #94A3B8; text-transform: uppercase; }
        .id-val { font-size: 1.1rem; font-weight: 900; color: white; font-family: monospace; }
        .id-meta { font-size: 0.75rem; color: #64748B; margin-top: 8px; }

        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        .bg-verified { background: rgba(16, 185, 129, 0.2); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-warn { background: rgba(245, 158, 11, 0.2); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-danger { background: rgba(239, 68, 68, 0.2); color: #EF4444; border-color: rgba(239, 68, 68, 0.3); }
        .bg-info { background: rgba(56, 189, 248, 0.2); color: #38BDF8; border-color: rgba(56, 189, 248, 0.3); }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn:hover { background: #475569; }
        .btn-primary { background: #4F46E5; border-color: #4338CA; color: white; }
        .btn-primary:hover { background: #4338CA; }
        .btn-success { background: #10B981; border-color: #059669; color: white; }
        .btn-danger { background: #EF4444; border-color: #DC2626; color: white; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="mpi-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-fingerprint"></i> MASTER PATIENT INDEX (MPI)
        </div>
        <div class="mpi-nav-links">
            <a href="#"  class="active">Identity Resolution</a>
            <a href="#" >Patient Registry</a>
            <a href="#" >Cross-Facility Links</a>
            <a href="#" >Audit & Compliance</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=52" style="width:28px; height:28px; border-radius:50%; border:2px solid #818CF8;"> Identity Officer
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    <div class="sidebar">
        <div class="panel">
            <div class="side-nav">
                <div class="side-title">Resolution Queues</div>
                <a href="#"  class="side-item active"><i class="fas fa-code-branch" style="width:20px;"></i> Duplicate Detection <span class="side-badge">29</span></a>
                <a href="#"  class="side-item"><i class="fas fa-id-card-clip" style="width:20px;"></i> Pending KYC Verifications</a>
                <a href="#"  class="side-item"><i class="fas fa-exclamation-triangle" style="width:20px;"></i> Identity Conflicts</a>
                
                <div class="side-title">Master Identity</div>
                <a href="#"  class="side-item"><i class="fas fa-users" style="width:20px;"></i> MPI Directory</a>
                <a href="#"  class="side-item"><i class="fas fa-link" style="width:20px;"></i> Merge / Unmerge Engine</a>
                <a href="#"  class="side-item"><i class="fas fa-upload" style="width:20px;"></i> Organization Import</a>
                
                <div class="side-title">Security & Audit</div>
                <a href="#"  class="side-item"><i class="fas fa-shield-alt" style="width:20px;"></i> Access Spoofing Alerts</a>
                <a href="#"  class="side-item"><i class="fas fa-history" style="width:20px;"></i> Core Identity Ledger</a>
            </div>
        </div>
    </div>

    <div class="main-col">
        
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="margin:0; font-size:1.5rem; font-weight:900;"><i class="fas fa-search-plus" style="color:#818CF8;"></i> Duplicate & Identity Resolution Queue</h1>
                <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Evaluating new registrations against existing Master Patient Index (MPI) records.</div>
            </div>
            <button class="btn btn-primary"><i class="fas fa-sync"></i> Run Batch Match Engine</button>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
                <i class="fas fa-users kpi-icon"></i>
                <div class="kpi-val">1.2M</div>
                <div class="kpi-lbl">Total Verified Patients</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #F59E0B;">
                <i class="fas fa-clone kpi-icon"></i>
                <div class="kpi-val">347</div>
                <div class="kpi-lbl">In Review Queue</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
                <i class="fas fa-check-double kpi-icon"></i>
                <div class="kpi-val">29</div>
                <div class="kpi-lbl">Pending Merges</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #EF4444;">
                <i class="fas fa-id-card-alt kpi-icon"></i>
                <div class="kpi-val">17</div>
                <div class="kpi-lbl">Identity Conflicts</div>
            </div>
        </div>

        <!-- System Architecture Notice -->
        <div style="background: rgba(129, 140, 248, 0.1); border: 1px solid rgba(129, 140, 248, 0.3); padding: 12px; border-radius: 8px; margin-bottom: 12px; font-size: 0.85rem; color: #E0E7FF;">
            <i class="fas fa-info-circle"></i> <strong>Architectural Rule:</strong> 
            Merging identities links the Master Patient Index IDs. It does <strong>not</strong> rewrite or destroy the historical organization-specific identifiers (MRNs) or alter the immutable Clinical Records attached to either profile.
        </div>

        <!-- High Confidence Duplicate Review -->
        <div class="duplicate-card">
            <div class="dc-header">
                <div class="dc-title"><i class="fas fa-exclamation-circle"></i> High Match Probability Detected</div>
                <div class="dc-score"><i class="fas fa-percent"></i> 94% Match Score</div>
            </div>
            <div class="dc-body">
                <!-- Existing MPI Record -->
                <div class="dc-col">
                    <div style="font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; margin-bottom: 16px; display:flex; align-items:center; gap:8px;">
                        Existing Patient (MPI Master) <span class="dc-master-tag">SOURCE TRUTH</span>
                    </div>
                    
                    <div class="dc-field">
                        <span class="dc-lbl">Full Name</span>
                        <span class="dc-val match-match">Ravi Kumar</span>
                    </div>
                    <div class="dc-field">
                        <span class="dc-lbl">Date of Birth</span>
                        <span class="dc-val match-match">12-04-1984 </span>
                    </div>
                    <div class="dc-field">
                        <span class="dc-lbl">Mobile Number</span>
                        <span class="dc-val match-match">+91 98*** **234 <i class="fas fa-check-circle" style="color:#10B981; font-size:0.8rem;"></i></span>
                    </div>
                    <div class="dc-field">
                        <span class="dc-lbl">Email</span>
                        <span class="dc-val match-diff" style="color:white;">ravi***@gmail.com</span>
                    </div>
                    <div class="dc-field">
                        <span class="dc-lbl">MPI ID</span>
                        <span class="dc-val" style="font-family:monospace; color:#38BDF8;">MPI-00010234</span>
                    </div>
                </div>

                <!-- New Candidate -->
                <div class="dc-col" style="background:#020617;">
                    <div style="font-size: 0.8rem; color: #94A3B8; font-weight: 800; text-transform: uppercase; margin-bottom: 16px;">
                        New Incoming Record (Hospital A Import)
                    </div>
                    
                    <div class="dc-field">
                        <span class="dc-lbl">Full Name</span>
                        <span class="dc-val match-match">Ravi K. Kumar <i class="fas fa-exclamation-triangle" style="color:#F59E0B; font-size:0.8rem;" title="Slight mismatch"></i></span>
                    </div>
                    <div class="dc-field">
                        <span class="dc-lbl">Date of Birth</span>
                        <span class="dc-val match-match">12-04-1984</span>
                    </div>
                    <div class="dc-field">
                        <span class="dc-lbl">Mobile Number</span>
                        <span class="dc-val match-match">+91 98*** **234 <i class="fas fa-check-circle" style="color:#10B981; font-size:0.8rem;"></i></span>
                    </div>
                    <div class="dc-field">
                        <span class="dc-lbl">Email</span>
                        <span class="dc-val match-diff" style="color:#EF4444;">Not Provided</span>
                    </div>
                    <div class="dc-field">
                        <span class="dc-lbl">Import ID</span>
                        <span class="dc-val" style="font-family:monospace;">IMP-8842-A</span>
                    </div>
                </div>
            </div>
            <div class="dc-footer">
                <input type="text" placeholder="Audit Reason (Required)" style="background:#0F172A; border:1px solid #334155; color:white; padding:8px 12px; border-radius:6px; flex:1;">
                <button class="btn"><i class="fas fa-unlink"></i> Distinct Patients</button>
                <button class="btn btn-success"><i class="fas fa-compress-arrows-alt"></i> Approve Merge to MPI</button>
            </div>
        </div>

        <!-- Master Identifier Map -->
        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-id-badge"></i> Example Multi-Organization Patient Architecture</div>
            </div>
            <div class="panel-body">
                <p style="font-size:0.85rem; color:#94A3B8; margin-top:0; margin-bottom:20px; line-height:1.5;">
                    A single Patient logically holds multiple organizational Medical Record Numbers (MRNs), Login Accounts, and Verified IDs, all strictly bound under one global <strong>Master Patient Identity Index (MPI)</strong>.
                </p>

                <div class="id-grid">
                    <!-- Identity Node -->
                    <div class="id-card" style="border-color:#4F46E5;">
                        <div class="id-card-head">
                            <span class="id-type" style="color:#818CF8;"><i class="fas fa-fingerprint"></i> Platform Master (MPI)</span>
                            <span class="badge bg-verified">Primary</span>
                        </div>
                        <div class="id-val">MPI-00010234</div>
                        <div class="id-meta">System Truth | Active since 2018</div>
                    </div>
                    
                    <!-- Auth Node -->
                    <div class="id-card">
                        <div class="id-card-head">
                            <span class="id-type"><i class="fas fa-user-lock"></i> Authentication Account</span>
                            <span class="badge bg-verified">Active</span>
                        </div>
                        <div class="id-val">USR-841920</div>
                        <div class="id-meta">Login Enabled | MFA Active</div>
                    </div>

                    <!-- Hospital MRN -->
                    <div class="id-card">
                        <div class="id-card-head">
                            <span class="id-type"><i class="fas fa-hospital"></i> Mysuru Hospital (MRN)</span>
                            <span class="badge bg-info">Linked</span>
                        </div>
                        <div class="id-val">MRN-MYS-77442</div>
                        <div class="id-meta">Imported Oct 2024 | Cardiology Dept</div>
                    </div>
                    
                    <!-- Telemedicine ID -->
                    <div class="id-card">
                        <div class="id-card-head">
                            <span class="id-type"><i class="fas fa-laptop-medical"></i> Telehealth Profile</span>
                            <span class="badge bg-info">Linked</span>
                        </div>
                        <div class="id-val">PA-000123</div>
                        <div class="id-meta">Native Doctor Assist Telehealth Registration</div>
                    </div>
                </div>

            </div>
        </div>
        
    </div>
</div>

</body>
</html>
