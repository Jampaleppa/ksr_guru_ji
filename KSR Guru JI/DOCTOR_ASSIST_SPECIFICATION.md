# Doctor Assist Platform Specification

## Unified Product and Technical requirements

This document defines the unified concept, architecture, database schemas, security protocols, and operational workflows for the **Doctor Assist** platform. It integrates age-based clinical routing, AI-assisted prescription parsing, pharmacist verification, delivery logistics, notifications, and security protocols into a cohesive, secure healthcare delivery ecosystem.

---

## 1. System Overview & Architecture

The Doctor Assist platform connects Patients, Doctors, Pharmacists, Delivery Personnel, and Administrators. It employs age-based routing for non-emergency consultations, processes uploaded prescriptions using an AI engine validated by human pharmacists, and delivers orders securely while maintaining patients' privacy.

### Complete System Flow Chart (Architecture)

```text
                    DOCTOR ASSIST
                         │
          ┌──────────────┼──────────────┐
          │              │              │
       PATIENT         DOCTOR       PHARMACY
          │              │              │
          └──────────────┼──────────────┘
                         │
                    CONSULTATION
                         │
                  AGE-BASED ROUTING
                         │
             ┌───────────┼───────────┐
             │           │           │
           0–15        16–44         45+
             │           │           │
        Pediatric     General      Senior/
         Doctor        Doctor      Geriatric
             │           │           │
             └───────────┼───────────┘
                         │
                    PRESCRIPTION
                         │
                ┌────────┴────────┐
                │                 │
          Digital Rx         Image/PDF Rx
                │                 │
                └────────┬────────┘
                         │
                   DATE CHECK
                         │
              Configured 6-month rule
                         │
                     AI ASSIST
                         │
           ┌─────────────┼─────────────┐
           │             │             │
          OCR       Medicine Match   Error Flags
           │             │             │
           └─────────────┼─────────────┘
                         │
                 PROFESSIONAL REVIEW
                         │
                 APPROVED / REJECTED
                         │
                     PHARMACY
                         │
                      ORDER
                         │
                     PAYMENT
                         │
                     DELIVERY
                         │
                    PATIENT
                         │
                 NOTIFICATIONS
                         │
                  WhatsApp/SMS
```

---

## 2. Role-Based Access Control (RBAC)

The system enforces absolute data isolation based on five distinct user roles following the principle of least privilege:

```text
PATIENT
  │
  └── Can access own profile, medical records, consultations, prescriptions, and order history.

DOCTOR
  │
  └── Can access profile and medical history of authorized patients under active consultation.
  └── Can write assessments and issue/reissue digital prescriptions. No access to other doctors' records.

PHARMACY
  │
  └── Access limited strictly to approved prescriptions and order fulfillment details (medicine database matches, quantities).
  └── No access to a patient’s full medical history or clinical diagnosis notes.

DELIVERY
  │
  └── Access strictly limited to delivery name, contact number, delivery address, order status, and confirmation logs.
  └── No access to medical history, prescriptions, or clinical notes.

ADMIN
  │
  └── Access to audit logs, verifications, system config (6-month rule), WhatsApp templates, and system settings.
```

### Access Permissions Matrix

| Module                                |  Patient   |   Doctor   |  Pharmacy  | Delivery |   Admin    |
| :------------------------------------ | :--------: | :--------: | :--------: | :------: | :--------: |
| **Own Profile / Billing**             | Read/Write |    None    |    None    |   None   | Read/Write |
| **Medical History & Allergies**       | Read/Write |    Read    |    None    |   None   |    Read    |
| **Consultation & Assessment**         |    Read    | Read/Write |    None    |   None   |    Read    |
| **Prescriptions File/Output**         |    Read    | Read/Write |    Read    |   None   |    Read    |
| **Order Fullfillment details**        |    Read    |    None    | Read/Write |   None   | Read/Write |
| **Delivery Address & Phone**          |    Read    |    None    |    Read    |   Read   | Read/Write |
| **Verify status (Doctors)**           |    None    |    Read    |    None    |   None   | Read/Write |
| **Audit Logs & System Configuration** |    None    |    None    |    None    |   None   | Read/Write |

---

## 3. Patient Registration & Age Categories

Registration collects demographic data, contact info, and medical baselines. To ensure clinical safety and prevent human error, the patient's age category is **never manually selected**. It is calculated programmatically at runtime.

### Registration Data Schema (MySQL)

```sql
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    patient_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    mobile_number VARCHAR(15) NOT NULL,
    address TEXT NOT NULL,
    emergency_contact VARCHAR(255) NOT NULL,
    medical_history TEXT DEFAULT NULL,
    allergies TEXT DEFAULT NULL,
    current_medications TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Age & Clinical Routing Logic

The age is calculated dynamically relative to the current timestamp:
$$\text{Age} = \text{floor}\left( \frac{\text{Current Date} - \text{DOB}}{365.25} \right)$$

Based on the calculated age, the routing engine assigns patients to specialized consultation views:

| Age Range (Years) | Automatic Category | Doctor Routing                         |
| :---------------- | :----------------- | :------------------------------------- |
| **0 – 15**        | Child              | **Pediatric / Children's Doctor**      |
| **16 – 44**       | Adult              | **General / Adult Doctor**             |
| **45+**           | Senior             | **Geriatric / Senior-Care Specialist** |

#### Dynamic Recalculation Rule

If `date_of_birth` changes (e.g. correction by Admin), the age category recalculates instantly.

---

## 4. Doctor Onboarding & Verification

To protect the platform's liability and patient safety, all doctors must undergo registration and credentials mapping. Only verified doctors can use functions requiring professional authorization (e.g., assessing patients and writing prescriptions).

### Doctor Profile Data Schema (MySQL)

```sql
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doctor_name VARCHAR(255) NOT NULL,
    qualification VARCHAR(255) NOT NULL,
    specialty VARCHAR(255) NOT NULL,
    registration_number VARCHAR(100) UNIQUE NOT NULL,
    registration_authority VARCHAR(255) NOT NULL,
    experience_years INT NOT NULL,
    clinic_hospital_details TEXT NOT NULL,
    verification_status ENUM('PENDING', 'UNDER_REVIEW', 'VERIFIED', 'REJECTED', 'SUSPENDED') DEFAULT 'PENDING',
    uploaded_docs_json JSON NOT NULL, -- Paths/metadata to uploaded professional documents
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Verification Status Lifecycle

- **PENDING:** Registered, documents uploaded, awaiting examination.
- **UNDER_REVIEW:** Admin or compliance team currently auditing credentials.
- **VERIFIED:** Fully vetted. Authorized to consult, assess, and prescribe.
- **REJECTED:** Application failed documentation checks. Access denied.
- **SUSPENDED:** Account deactivated due to complaints or admin action. All prescription capabilities revoked.

---

## 5. Consultation System & Prescription Management

### Consultation Lifecycle Flow

```text
Patient
   ↓
Select Doctor Assist
   ↓
Age-based recommendation
   ↓
Select doctor
   ↓
Appointment / consultation
   ↓
Doctor assessment
   ↓
Prescription if clinically appropriate
```

Consultation history must be stored securely, ensuring that access to patients' past clinical profiles is audited.

### Prescription Immutability Rules

1. **No Silent Updates:** Once a prescription is saved/finalized, it is locked and protected from modification.
2. **Version Control:** If a correction is needed, the doctor must reissue the prescription. The old record is flagged as `REISSUED`, and a new version is created referencing the previous `prescription_id` as `parent_prescription_id`.

### Consultation & Prescription Schema (MySQL)

```sql
CREATE TABLE consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    symptoms TEXT NOT NULL,
    doctor_assessment TEXT DEFAULT NULL,
    diagnosis TEXT DEFAULT NULL,
    is_emergency BOOLEAN DEFAULT FALSE,
    consultation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    prescription_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ACTIVE', 'REISSUED', 'EXPIRED') DEFAULT 'ACTIVE',
    parent_prescription_id INT DEFAULT NULL, -- Self-referencing link for version tracking
    doctor_authentication_signature VARCHAR(255) NOT NULL, -- Hashed verification token
    FOREIGN KEY (consultation_id) REFERENCES consultations(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (parent_prescription_id) REFERENCES prescriptions(id)
);

CREATE TABLE prescription_medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medicine_id INT DEFAULT NULL,        -- Links to internal verified database if matched
    medicine_name VARCHAR(255) NOT NULL, -- Extracted or selected name
    strength VARCHAR(100) NOT NULL,      -- e.g., "500 mg"
    dosage_form VARCHAR(100) NOT NULL,   -- e.g., "Tablet", "Capsule"
    frequency VARCHAR(100) NOT NULL,     -- e.g., "1-0-1" or "Once daily"
    duration VARCHAR(50) NOT NULL,       -- e.g., "5 Days"
    instructions VARCHAR(255) DEFAULT '', -- e.g., "Before food"
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE SET NULL
);
```

---

## 6. Uploaded Prescription Validation (External RX Upload)

When a patient uploads an existing prescription, the system processes it through a sequential pipeline:

```text
File Upload -> Security Validation -> Document Quality Check ->
Date Threshold Assessment -> OCR/AI Information Extraction ->
Medicine Database Cross-Match -> Professional Pharmacist Review
```

### Technical Workflow Checklist

- **File Upload Types:** Accept only `JPG`, `JPEG`, `PNG`, and `PDF` options.
- **Security Validation:** Verify MIME types, restrict file sizes to `10MB`, scan for execution signatures, and block scripts.
- **Document Quality Check:** Analyze resolution and contrast. Alert user if the image is too blurry for OCR.
- **Prescription Date:** Parse written date. Check threshold conditions.
- **Doctor Details:** Extract name, registration number, and contact info if visual.
- **AI Extraction Engine:** Parse text using OCR and structure parsed data.
- **Medicine Database Cross-Match:** Validate extracted string against master catalog.
- **Professional Verification Route:** Send parsed object + original file to Pharmacy review queue.

---

## 7. Prescription Date & 6-Month Rule

The platform checks the date written on the uploaded prescription to determine eligibility:

```text
Prescription age ≤ Configured threshold (e.g. 6 months)
        ↓
RECENT / ELIGIBLE FOR REVIEW

Prescription age > Configured threshold (e.g. 6 months)
        ↓
OLD / NEW PRESCRIPTION MAY BE REQUIRED
```

### Clinical Customization Rules

1. **Threshold Configuration:** The threshold (default is 180 days / 6 months) is stored in the system config table and can be updated by the Administrator.
2. **India Prescription Rules Compliance:** The system must not claim that all prescriptions in India legally expire after exactly six months. Recency thresholds are configurable based on the drug class (e.g., Schedule H or X controlled medicines enforce shorter validity windows, sometimes down to 24 hours).

---

## 8. AI Medicine Assist

The AI layer extracts metadata from unstructured prescription files, matches them to the master database, and detects potential issues.

```text
Prescription Image
       ↓
   OCR & AI
       ↓
Medicine: ABC
Strength: 500 mg
Form: Tablet
Frequency: 1-0-1
Duration: 5 days
       ↓
Verified Medicine Database Match
       ↓
Human/Pharmacy Verification Queue
```

### AI Assist Capabilities:

- **Reading Prescription Text / OCR:** Digitizes raw handwriting and typed text.
- **Information Extraction:** Identifies medicine names, strengths, dosages, frequencies, and durations.
- **DB Verification Matching:** Queries the database using fuzzy matching to find the exact database record.
- **Safety Error Flags:** Marks unclear text blocks, duplicate active records, missing frequency rules, or expired dates.
- **Human Validation Queue:** Highlights suspicious/unclear items in the admin/pharmacy review interface.

### Safety Rails

**The AI MUST NOT independently prescribe medicines, change dosage structures, or auto-approve prescriptions.** It acts as a parsing tool, with all changes requiring human pharmacist authorization.

---

## 9. Medicine Database

Medications must be verified against a master database. This database contains validated catalog data instead of guessing values using the AI.

### Medicine Master Data Schema (MySQL)

```sql
CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_name VARCHAR(255) UNIQUE NOT NULL,
    generic_name VARCHAR(255) NOT NULL,
    strength VARCHAR(100) NOT NULL,
    dosage_form VARCHAR(100) NOT NULL, -- e.g. "Tablet", "Capsule", "Syrup"
    medicine_type ENUM('Tablet', 'Capsule', 'Syrup', 'Injection', 'Cream', 'Ointment', 'Drops', 'Inhaler', 'Powder', 'Suspension', 'Solution') NOT NULL,
    manufacturer VARCHAR(255) NOT NULL,
    prescription_required BOOLEAN DEFAULT TRUE,
    product_status ENUM('ACTIVE', 'DISCONTINUED', 'OUT_OF_STOCK') DEFAULT 'ACTIVE'
);

-- Specific formulation variants for Tablets and Capsules to capture release features
CREATE TABLE medicine_formulations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    formulation_type ENUM('Immediate Release', 'Extended Release', 'Enteric Coated', 'Chewable', 'Dispersible', 'Sublingual') NOT NULL,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);
```

---

## 10. Pharmacy Verification

After prescription processing, the results must go through a professional verification step. The pharmacist must audit the AI extraction against the original document side-by-side.

```text
Prescription Document (Upload)
      ↓
AI Assist OCR & Parsing
      ↓
Medicine Database Match
      ↓
Pharmacy/Qualified Professional Review
      ↓
APPROVED / REJECTED / MANUAL REVIEW
```

**Rule:** The pharmacy must not blindly accept what the AI recognizes. Pharmacists check for correct brand/generic mappings, strengths, safety warnings, and date correctness before approving.

---

## 11. Medicine Order Lifecycle

Orders follow a strict state machine to prevent unverified medicine sales:

```text
Create Order
      ↓
Pharmacy accepts
      ↓
Medicine packed
      ↓
Delivery assigned
      ↓
Out for delivery
      ↓
Delivered
```

### Order Statuses State Table

| Status                  | Description                                                                    |
| :---------------------- | :----------------------------------------------------------------------------- |
| **PENDING**             | Order created by user; payment details outstanding or awaiting initial checks. |
| **PRESCRIPTION_REVIEW** | Undergoing verification (AI processing + Pharmacist audit queue).              |
| **APPROVED**            | Prescription is verified; awaiting customer payment if order is not prepaid.   |
| **PHARMACY_ACCEPTED**   | Pharmacist accepts the order for packing post-payment.                         |
| **PACKING**             | Pharmacist packs the medication.                                               |
| **READY_FOR_PICKUP**    | Package sealed; awaiting dispatch collection by driver.                        |
| **OUT_FOR_DELIVERY**    | Out with the assigned driver.                                                  |
| **DELIVERED**           | Successfully handed over.                                                      |
| **CANCELLED**           | Cancelled by patient or admin prior to packing.                                |
| **REJECTED**            | Failed verification (e.g. invalid prescription, bad date, fake doc info).      |

### Orders Schema containing Delivery Constraints

```sql
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    billing_address TEXT NOT NULL,
    delivery_address TEXT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('PENDING', 'PAID', 'REFUNDED') DEFAULT 'PENDING',
    order_status ENUM('PENDING', 'PRESCRIPTION_REVIEW', 'APPROVED', 'PHARMACY_ACCEPTED', 'PACKING', 'READY_FOR_PICKUP', 'OUT_FOR_DELIVERY', 'DELIVERED', 'CANCELLED', 'REJECTED') DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);
```

---

## 12. Delivery System

The Delivery system restricts the visibility of patient records. Delivery personnel can only access logistics-related data.

### Driver View Constraints

- **Visible Info:** Delivery customer name, mobile contact, delivery address, pickup completion, and delivery status updates.
- **Hidden/Restricted Info:** Diagnostic history, prescriptions, list of specific medications, and consultations.

### Delivery Schema

```sql
CREATE TABLE order_delivery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    delivery_user_id INT NOT NULL, -- Delivery driver user account
    pickup_time TIMESTAMP NULL,
    delivery_time TIMESTAMP NULL,
    recipient_signature_json JSON DEFAULT NULL, -- Storing coords or validation codes
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (delivery_user_id) REFERENCES users(id)
);
```

---

## 13. WhatsApp Notification System

The **WhatsApp Business Platform API** is integrated to send transactional notifications.

### Target Templates

- **Appointment Confirmed:** "Hi {{1}}, your appointment with {{2}} is confirmed for {{3}}."
- **Prescription Ready:** "Hi {{1}}, Dr. {{2}} has finalized your digital prescription. You can view it in your dashboard."
- **Order Confirmed:** "Your order {{1}} has been confirmed. Total amount: {{2}}."
- **Order Dispatched:** "Your order {{1}} has been dispatched. Track here: {{2}}."
- **Order Out for Delivery:** "Your order {{1}} is out for delivery. Courier contact: {{2}}."
- **Order Delivered:** "Your order {{1}} has been delivered. Thank you!"

### Privacy Compliance Rules

- **No Diagnostic/Medical Info on WhatsApp:** Notifications must never contain lists of medicines, generic active compounds, dosage directions, or clinical diagnosis names.
- **Audit-logging of Notifications:**

```sql
CREATE TABLE notifications_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type ENUM('WHATSAPP', 'SMS', 'EMAIL') NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    payload_json JSON NOT NULL, -- Parameters used to complete the message template
    dispatch_status ENUM('SENT', 'DELIVERED', 'FAILED') DEFAULT 'SENT',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 14. Emergency Pathway System

Emergency is an age-independent gateway. The clinical routing system must distinguish routine settings from acute settings.

```text
🚨 EMERGENCY
      ↓
Urgency guidance & triage steps
      ↓
Emergency physician / triage routing
      ↓
Healthcare facility & emergency contact locator
```

### Safety Principles

- **Age-Independence:** Age alone should **never** classify a patient into an emergency pathway.
- **Routing Logic:** Routine geriatric checkups for seniors (45+) route to Geriatric/Senior-care. Emergency symptoms across **any** age group route to the Emergency pathway immediately.
- **Triage Safeguards:** The gateway gives immediate directions (e.g., call 108 in India/112 in EU/911 in US) and lists nearby ER coordinates.

---

## 15. Admin Dashboard Panel

The Admin panel provides a central view of all platform operations:

- **Patients Directory:** Manage registration, profiles, and billing histories.
- **Doctors Directory & Verification:** Verify documentation and manage approval status (`VERIFIED`, `REJECTED`, `SUSPENDED`).
- **Appointments & Consultations:** Monitor patient-doctor bookings and consultation statuses.
- **Prescription Auditing:** Manage the AI review queue for uploaded files.
- **Medicine Database Catalog:** Add and configure brand/generic items, formulation rules, and requirements.
- **Orders & Payments:** Track billing, refunds, state processes, and pharmacist completions.
- **Delivery Operations:** Assign packages and monitor driver statuses/times.
- **WhatsApp log audits:** Tracks parameters and delivery states of templates.
- **System Settings:** Configure duration rules (e.g., the 6-month recency threshold).

---

## 16. Security & Compliance Checklist

Because this platform processes Protected Health Information (PHI), security must be built in by design:

- **HTTPS Encryption:** Require TLS 1.3 for all endpoints.
- **Password Hashing:** Use Argon2id or bcrypt for user credentials.
- **Prepared SQL Statements:** Enforce prepared statements (`PDO::prepare()`) for all database operations to prevent SQL Injection.
- **CSRF & XSS Protection:** Use anti-CSRF tokens for all state mutations (POST/PUT/DELETE) and escape output dynamically via `htmlspecialchars()`.
- **Cookie & Session Safety:** Configure cookies with `HttpOnly`, `Secure`, and `SameSite=Strict`.
- **Document Isolation:** Store prescription documents outside the public root. Stream files securely through authentication proxy scripts.
- **File Validation Rules:** Validate MIME types and restrict uploads to parsed PDFs, JPGs, JPEGs, and PNGs under 10MB.
- **Field-Level Encryption:** Encrypt sensitive fields (e.g. patient name, phone, medical history) at rest in MySQL using AES-256-CBC.
- **System Audit Logging:** Track all status transitions, verification status updates, and prescription queries.

### Audit Log Schema

```sql
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL, -- NULL if system action or guest
    action VARCHAR(255) NOT NULL, -- e.g., 'DOCTOR_VERIFIED', 'PRESCRIPTION_EDIT_ATTEMPT'
    target_table VARCHAR(100) NOT NULL,
    record_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 17. Tech Stack Recommendations

- **Frontend:** HTML5, CSS3 (using CSS variables and global theme files supporting glassmorphism features), Vanilla JavaScript, and Font Awesome for icons.
- **Backend:** PHP 8.x under strict typing configurations, utilizing PDO for secure database requests.
- **Database:** MySQL 8.0 configured with InnoDB for transactional safety and foreign key constraints.
- **AI Engine:** Tesseract OCR / Google Vision API + OpenAI GPT-4o API for metadata extraction and fuzzy string matching.
- **Notifications:** Official WhatsApp Business Platform API and REST hooks.

---

## 18. Custom "Multi-Item" Retail Suggestion Workflow & UI Tokens

The KSR GURU JI ecosystem relies heavily on hybrid order intake models, requiring highly performant, multi-item client-side forms such as the Final Suggestion Box (`final-suggestion-box.php`). These forms must queue inputs natively via JS structures.

### Workflow & Interaction Model

1. **Queue (Client-Side Storage):** Users input single-item rows across several distinct data categories (e.g., `Product Name`, `Category`, `Unit`, `Quantity`, `Current Price`, `Suggested Shop`, `Brand`, `Note`).
2. **Immediate "Ping" Architecture:** Clicking the Primary `Add Product & WhatsApp` button logs the item visually in a DOM list while simultaneously dispatching a background or native WhatsApp link pop-up per item, creating constant interaction loops.
3. **Batch Commit (Final Submit):** A universal `FINAL SUBMIT` asynchronously executes an AJAX or POST JSON packet, transforming the full javascript array into SQL rows mapped by `submission_id` -> atomic insertions across `product_submission_items`.

### Schema For Native E-Commerce Submissions

```sql
CREATE TABLE product_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    different_product VARCHAR(255) DEFAULT NULL,
    status ENUM('PENDING', 'REVIEWED', 'FULFILLED', 'REJECTED') DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE product_submission_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    current_price DECIMAL(10,2) NOT NULL,
    suggested_shop VARCHAR(255) DEFAULT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    product_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES product_submissions(id) ON DELETE CASCADE
);
```

### Aesthetic UI Tokens ("Warm Minimalism")

- **Layouts:** Use implicit grid `.row-Xcol` structures (e.g. `.row-3col` for desktop) strictly converting to 1-column layouts organically on smaller phones using `grid-template-columns: 1fr !important`.
- **Card Accents:** Employ `#fafafa` soft background panels surrounded by `var(--border)` soft blue-toned borders for high legibility inputs.
- **Typography:** Ensure `display: grid; gap: 15px; margin-bottom: 15px;` consistency between labels and `input`/`select` fields for fluid padding.
- **Micro-Animations:** Use subtle `.product-card:hover { background: #f5f9ff; }` hover states on DOM cards dynamically injected by JavaScript queues instead of hard borders.
