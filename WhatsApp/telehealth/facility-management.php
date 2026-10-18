<?php
require_once 'helper.php';
// Simulated Hospital / Clinic Organization, Facility, Department & Multi-Location Management Center
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility & Location Management | Doctor Assist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0F172A; margin: 0; padding-bottom: 40px; display: flex; flex-direction: column; min-height: 100vh; color: #F8FAFC; }
        
        /* Specialized Facility Navbar */
        .fac-nav { background: #064E3B; border-bottom: 2px solid #065F46; position: sticky; top: 0; z-index: 100; padding: 0 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); }
        .fac-nav .navbar-brand { color: #34D399; display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; }
        .fac-nav-links { display: flex; gap: 4px; overflow-x: auto; padding: 12px 0; }
        .fac-nav-links::-webkit-scrollbar { display: none; }
        .fac-nav-links a { color: #A7F3D0; text-decoration: none; padding: 8px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; white-space: nowrap; transition: 0.2s; }
        .fac-nav-links a:hover { color: white; background: #047857; }
        .fac-nav-links a.active { color: white; background: #10B981; }

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
        .side-item.active { background: rgba(16, 185, 129, 0.2); color: #10B981; border-left: 3px solid #10B981; border-radius: 0 6px 6px 0; }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 8px; }
        .kpi-card { background: #1E293B; border-radius: var(--radius-md); border: 1px solid #334155; padding: 16px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .kpi-val { font-size: 1.8rem; font-weight: 900; color: white; }
        .kpi-lbl { font-size: 0.8rem; color: #94A3B8; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
        .kpi-icon { position: absolute; right: -10px; bottom: -10px; font-size: 4rem; opacity: 0.05; color: white; }

        /* Facility Map/Hierarchy Card */
        .facility-card { background: #0F172A; border: 1px solid #334155; border-radius: 8px; padding: 20px; display: flex; flex-direction: column; gap: 16px; margin-bottom: 16px; }
        .fc-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .fc-name { font-size: 1.3rem; font-weight: 900; color: white; }
        .fc-meta { font-size: 0.85rem; color: #94A3B8; margin-top: 4px; }
        
        .fc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .fc-dep { background: #1E293B; border: 1px solid #334155; border-radius: 6px; padding: 12px; border-left: 3px solid #3B82F6; }
        .fc-dep-name { font-weight: 800; color: white; font-size: 0.95rem; margin-bottom: 6px; display:flex; justify-content:space-between;}
        .fc-dep-stats { font-size: 0.75rem; color: #94A3B8; display: flex; flex-direction: column; gap: 4px; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; gap: 4px; border: 1px solid transparent; }
        .bg-healthy { background: rgba(16, 185, 129, 0.2); color: #34D399; border-color: rgba(52, 211, 153, 0.3); }
        .bg-warn { background: rgba(245, 158, 11, 0.2); color: #FBBF24; border-color: rgba(245, 158, 11, 0.3); }
        .bg-danger { background: rgba(239, 68, 68, 0.2); color: #EF4444; border-color: rgba(239, 68, 68, 0.3); }
        .bg-neutral { background: rgba(148, 163, 184, 0.2); color: #94A3B8; border-color: rgba(148, 163, 184, 0.3); }
        .bg-purple { background: rgba(139, 92, 246, 0.2); color: #A78BFA; border-color: rgba(139, 92, 246, 0.3); }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.85rem; cursor: pointer; transition: 0.2s; border: 1px solid #475569; background: #334155; color: white; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn:hover { background: #475569; }
        .btn-primary { background: #10B981; border-color: #059669; color: white; }
        .btn-primary:hover { background: #059669; }
        .btn-sm { padding: 4px 10px; font-size: 0.75rem; }
    </style>
</head>
<body>
    <!-- KSR brand bar (shared) -->
    <link rel="stylesheet" href="partials/brandbar.css">
    <link rel="stylesheet" href="assist-style.css">
    <?php $ksr_active = ''; $ksr_depth = 0; include __DIR__ . '/partials/brandbar.php'; ?>






<div class="fac-nav">
    <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; width: 100%;">
        <div class="navbar-brand">
            <i class="fas fa-hospital"></i> FACILITY & LOCATION MANAGEMENT
        </div>
        <div class="fac-nav-links">
            <a href="#"  class="active">Hospital Network</a>
            <a href="#" >Departments & Clinics</a>
            <a href="#" >Rooms & Inventory</a>
            <a href="#" >Workforce Mapping</a>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; color:white;">
                <img src="https://i.pravatar.cc/150?img=60" style="width:28px; height:28px; border-radius:50%; border:2px solid #34D399;"> Ops Director
            </div>
        </div>
    </div>
</div>

<div class="app-container">
    <div class="sidebar">
        <div class="panel">
            <div class="side-nav">
                <div class="side-title">Global Network</div>
                <a href="#"  class="side-item active"><i class="fas fa-building" style="width:20px;"></i> Facilities Index</a>
                <a href="#"  class="side-item"><i class="fas fa-door-open" style="width:20px;"></i> Master Departments</a>
                <a href="#"  class="side-item"><i class="fas fa-bed" style="width:20px;"></i> Rooms Matrix</a>
                
                <div class="side-title">Operations</div>
                <a href="#"  class="side-item"><i class="fas fa-ambulance" style="width:20px;"></i> Emergency Triages</a>
                <a href="#"  class="side-item"><i class="fas fa-desktop" style="width:20px;"></i> Telemedicine Hubs</a>
                <a href="#"  class="side-item"><i class="fas fa-flask" style="width:20px;"></i> Lab & Imaging</a>
                
                <div class="side-title">Resource Mapping</div>
                <a href="#"  class="side-item"><i class="fas fa-user-md" style="width:20px;"></i> Doctor Assignments</a>
                <a href="#"  class="side-item"><i class="fas fa-boxes" style="width:20px;"></i> Equipment & Assets</a>
            </div>
        </div>
    </div>

    <div class="main-col">
        
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="margin:0; font-size:1.5rem; font-weight:900;"><i class="fas fa-network-wired" style="color:#10B981;"></i> Facility Operating Topology</h1>
                <div style="font-size:0.85rem; color:#94A3B8; margin-top:4px;">Organization: ABC Healthcare Network (ORG-000123)</div>
            </div>
            <button class="btn btn-primary"><i class="fas fa-plus"></i> New Facility</button>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card" style="border-bottom: 4px solid #3B82F6;">
                <i class="fas fa-hospital kpi-icon"></i>
                <div class="kpi-val">8</div>
                <div class="kpi-lbl">Total Facilities</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #10B981;">
                <i class="fas fa-door-open kpi-icon"></i>
                <div class="kpi-val">32</div>
                <div class="kpi-lbl">Active Departments</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #8B5CF6;">
                <i class="fas fa-user-md kpi-icon"></i>
                <div class="kpi-val">146</div>
                <div class="kpi-lbl">Assigned Doctors</div>
            </div>
            <div class="kpi-card" style="border-bottom: 4px solid #EF4444;">
                <i class="fas fa-ambulance kpi-icon"></i>
                <div class="kpi-val">2</div>
                <div class="kpi-lbl">Emergency Centers (OPEN)</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div class="panel-title"><i class="fas fa-sitemap"></i> Facility Hierarchy & Resource Scopes</div>
            </div>
            <div class="panel-body">
                <p style="font-size:0.85rem; color:#94A3B8; margin-top:0; margin-bottom:20px; line-height:1.5;">
                    This matrix proves that <strong>Facilities and Departments</strong> are fully isolated operational entities. A doctor assigned to the 'Cardiology' department in 'Mysuru Hospital' does not inherently receive clinical access to records originating in 'Bengaluru Rapid Care'.
                </p>

                <!-- Facility 1 -->
                <div class="facility-card">
                    <div class="fc-header">
                        <div>
                            <div class="fc-name">Mysuru Medical Center <span class="badge bg-healthy" style="margin-left:8px;">Operational</span></div>
                            <div class="fc-meta">FAC-00123 | Type: Multi-Specialty Hospital | Capacity: 85%</div>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-sm"><i class="fas fa-cog"></i> Config</button>
                        </div>
                    </div>
                    
                    <div class="fc-grid">
                        <div class="fc-dep">
                            <div class="fc-dep-name">Cardiology <span class="badge bg-healthy">Active</span></div>
                            <div class="fc-dep-stats">
                                <span><i class="fas fa-user-md"></i> 12 Doctors Assigned</span>
                                <span><i class="fas fa-bed"></i> 4 Rooms Configured</span>
                            </div>
                        </div>
                        <div class="fc-dep" style="border-left-color:#10B981;">
                            <div class="fc-dep-name">General Physician <span class="badge bg-healthy">Active</span></div>
                            <div class="fc-dep-stats">
                                <span><i class="fas fa-user-md"></i> 24 Doctors Assigned</span>
                                <span><i class="fas fa-bed"></i> 10 Rooms Configured</span>
                            </div>
                        </div>
                        <div class="fc-dep" style="border-left-color:#F59E0B;">
                            <div class="fc-dep-name">In-House Pharmacy <span class="badge bg-healthy">Active</span></div>
                            <div class="fc-dep-stats">
                                <span><i class="fas fa-pills"></i> 4 Pharmacists Assigned</span>
                                <span><i class="fas fa-box"></i> Inventory Linked: INV-MYS</span>
                            </div>
                        </div>
                        <div class="fc-dep" style="border-left-color:#EF4444;">
                            <div class="fc-dep-name">Emergency Triage <span class="badge bg-warn">High Vol</span></div>
                            <div class="fc-dep-stats">
                                <span><i class="fas fa-ambulance"></i> 8 Doctors Assigned</span>
                                <span><i class="fas fa-exclamation-triangle"></i> Special Access Rules Apply</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Facility 2 -->
                <div class="facility-card" style="border-color:#334155;">
                    <div class="fc-header">
                        <div>
                            <div class="fc-name">Bengaluru Rapid Care <span class="badge bg-neutral" style="margin-left:8px;">Degraded Ops</span></div>
                            <div class="fc-meta">FAC-00881 | Type: Urban Clinic | Capacity: 100%</div>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-sm"><i class="fas fa-cog"></i> Config</button>
                        </div>
                    </div>
                    
                    <div class="fc-grid">
                        <div class="fc-dep" style="border-left-color:#10B981; opacity:0.8;">
                            <div class="fc-dep-name">General Physician <span class="badge bg-healthy">Active</span></div>
                            <div class="fc-dep-stats">
                                <span><i class="fas fa-user-md"></i> 8 Doctors Assigned</span>
                                <span><i class="fas fa-bed"></i> 3 Rooms Configured</span>
                            </div>
                        </div>
                        <!-- Telemedicine Independent State -->
                         <div class="fc-dep" style="border-left-color:#8B5CF6;">
                            <div class="fc-dep-name">Telemedicine Hub <span class="badge bg-purple">Virtual</span></div>
                            <div class="fc-dep-stats">
                                <span><i class="fas fa-user-md"></i> 15 Doctors Authorized</span>
                                <span><i class="fas fa-video"></i> Global Virtual Rooms</span>
                            </div>
                        </div>
                        <div class="fc-dep" style="border-left-color:#64748B; background:#0F172A;">
                            <div class="fc-dep-name" style="color:#94A3B8;">Diagnostics Lab <span class="badge bg-danger">Maint</span></div>
                            <div class="fc-dep-stats">
                                <span><i class="fas fa-wrench"></i> MRI Scanner Calibration</span>
                                <span><i class="fas fa-calendar-times"></i> Bookings Suspended</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        
    </div>
</div>

</body>
</html>
