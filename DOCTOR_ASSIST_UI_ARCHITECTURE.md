# 🏆 Doctor Assist — FINAL COMPLETE HOME PAGE SYSTEM

> **One application + one design system + one navigation framework + role-based dashboards + permission-driven modules + consistent UX across Web, Tablet and Mobile.**

No matter which module the user opens, Doctor Assist should always feel like the **same healthcare platform**.

---

# 1. 🌐 MASTER APPLICATION STRUCTURE

- Authentication (Login, MFA, Session Management)
- HOME (Role-Based Dashboard)
- CARE (Find Doctor, Appointment, Consultation, Emergency)
- CLINICAL (Records, Diagnoses, Labs, Imaging, Prescriptions)
- PHARMACY (Inventory, Dispensing, AI OCR Review)
- DELIVERY (Tracking, Manifests, Dispatch)
- FINANCE (Invoices, Refunds, Settlement)
- COMMUNICATION (Chat, SMS, WhatsApp, Push)
- SECURITY (Audit, Permissions, Emergency Access)
- ADMINISTRATION (Analytics, AI Governance, Configs)

---

# 2. 🎨 MASTER DESIGN SYSTEM

**Desktop:** Header + Sidebar + Main Content Grid
**Mobile:** Sticky Header + Single-Column Scrolling + Bottom Icon Navigation

---

# 3. COMPREHENSIVE ROLE DASHBOARDS

### 👤 PATIENT HOME

- **Purpose:** Simple + personalized + health-focused.
- Quick Actions, Active Consultations, Specialist Care Grid, Universal Health Timeline.

### 🩺 DOCTOR HOME

- **Purpose:** Today's workload + clinical decision workspace.
- KPIs: Waiting Patients, Active Consultations, Pending Labs, Prescription Reviews.

### ❤️ SPECIALIST HOME

- Sub-menus strictly mapped to specific domains (ECG, Holter, Seizure Monitoring, Endoscopy, etc.).

### 💊 PHARMACY HOME

- **Purpose:** Prescription → verification → inventory → dispensing → delivery.
- Quick verification arrays, low stock alerts, pending distribution orders.

### 🚚 DELIVERY HOME

- **Purpose:** Assigned deliveries + live operational workflow.
- Live active manifest with GPS mapping and delivery signature hooks.

### 🚨 EMERGENCY COMMAND CENTER

- Strictly segmented and separated from standard workflows with Live Triage capability.

---

# 4. 🧠 DASHBOARD ENGINE & DIRECTORY STRUCTURE

```text
DashboardEngine
       │
       ├── Identify User
       ├── Identify Role
       ├── Load Permissions
       ├── Load Widgets
       ├── Load Quick Actions
       └── Render Dashboard
```

Both HTML Architecture and Component Styling MUST rely on the exact unified UI library (`assist-style.css`) across all user portals to ensure true unification.
