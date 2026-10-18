-- =================================================================================
-- DOCTOR ASSIST PLATFORM - MASTER ENTERPRISE SCHEMA
-- Strict Domain-Driven Design & Multi-Tenant Separation
-- =================================================================================

-- 1. Organizations & IAM (Identity & Access Management)
CREATE TABLE IF NOT EXISTS organizations (
    org_id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    status VARCHAR(50) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    user_id VARCHAR(36) PRIMARY KEY,
    identity_hash VARCHAR(255) UNIQUE NOT NULL,
    role ENUM('PATIENT', 'DOCTOR', 'PHARMACIST', 'DELIVERY', 'ADMIN') NOT NULL,
    status VARCHAR(50) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id VARCHAR(36) PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone_encrypted VARCHAR(255),
    email_encrypted VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 2. Master Configuration & Governance Control Plane
CREATE TABLE IF NOT EXISTS master_specializations (
    spec_id VARCHAR(36) PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'ACTIVE'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_configurations (
    config_id VARCHAR(36) PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value JSON NOT NULL,
    version INT DEFAULT 1,
    status VARCHAR(50) DEFAULT 'ACTIVE',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pricing_rule_versions (
    pricing_id VARCHAR(36) PRIMARY KEY,
    service_code VARCHAR(100) NOT NULL,
    doctor_fee DECIMAL(10, 2) NOT NULL,
    platform_fee DECIMAL(10, 2) NOT NULL,
    status ENUM('DRAFT', 'ACTIVE', 'SUPERSEDED') DEFAULT 'ACTIVE',
    effective_from TIMESTAMP CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Operations Plane: Scheduling & Telehealth
CREATE TABLE IF NOT EXISTS clinical_encounters (
    encounter_id VARCHAR(36) PRIMARY KEY,
    patient_id VARCHAR(36) NOT NULL,
    doctor_id VARCHAR(36) NOT NULL,
    org_id VARCHAR(36) NOT NULL,
    pricing_id VARCHAR(36) NOT NULL,
    status VARCHAR(50) DEFAULT 'SCHEDULED', -- e.g., SCHEDULED, ACTIVE, COMPLETED, CANCELLED
    consultation_mode VARCHAR(50), -- AUDIO, VIDEO, IN_PERSON
    start_time DATETIME,
    end_time DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id),
    FOREIGN KEY (org_id) REFERENCES organizations(org_id),
    FOREIGN KEY (pricing_id) REFERENCES pricing_rule_versions(pricing_id)
) ENGINE=InnoDB;

-- 4. Clinical State & Health Records (Append-Only)
CREATE TABLE IF NOT EXISTS clinical_timeline_events (
    event_id VARCHAR(36) PRIMARY KEY,
    patient_id VARCHAR(36) NOT NULL,
    domain_source VARCHAR(50) NOT NULL, -- e.g., PHARMACY, DIAGNOSTIC, CONSULTATION
    event_type VARCHAR(100) NOT NULL, 
    event_payload JSON NOT NULL, -- Immutable pointer payload
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prescriptions (
    prescription_id VARCHAR(36) PRIMARY KEY,
    encounter_id VARCHAR(36) NOT NULL,
    patient_id VARCHAR(36) NOT NULL,
    doctor_id VARCHAR(36) NOT NULL,
    status VARCHAR(50) DEFAULT 'ISSUED', -- DRAFT, ISSUED, VERIFIED, EXPIRED
    payload JSON NOT NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (encounter_id) REFERENCES clinical_encounters(encounter_id)
) ENGINE=InnoDB;

-- 5. Diagnostics, Laboratory & Imaging Center
CREATE TABLE IF NOT EXISTS diagnostic_orders (
    order_id VARCHAR(36) PRIMARY KEY,
    patient_id VARCHAR(36) NOT NULL,
    prescribing_doctor_id VARCHAR(36) NOT NULL,
    diagnostic_org_id VARCHAR(36) NOT NULL,
    status VARCHAR(50) DEFAULT 'ORDERED', -- ORDERED, SAMPLE_COLLECTED, PROCESSING, VERIFIED
    test_codes JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (diagnostic_org_id) REFERENCES organizations(org_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lab_results (
    result_id VARCHAR(36) PRIMARY KEY,
    order_id VARCHAR(36) NOT NULL,
    result_payload JSON NOT NULL,
    ai_safety_flag VARCHAR(50) DEFAULT 'NORMAL', -- NORMAL, CRITICAL
    verification_status VARCHAR(50) DEFAULT 'PENDING_REVIEW', -- PENDING_REVIEW, CLINICIAN_VERIFIED
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES diagnostic_orders(order_id)
) ENGINE=InnoDB;

-- 6. Pharmacy & Logistics (Transactional Safety)
CREATE TABLE IF NOT EXISTS pharmacy_inventory (
    item_id VARCHAR(36) PRIMARY KEY,
    org_id VARCHAR(36) NOT NULL,
    item_code VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    on_hand_qty INT NOT NULL DEFAULT 0,
    reserved_qty INT NOT NULL DEFAULT 0,
    batch_expiry DATE NOT NULL,
    FOREIGN KEY (org_id) REFERENCES organizations(org_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fulfillment_orders (
    order_id VARCHAR(36) PRIMARY KEY,
    patient_id VARCHAR(36) NOT NULL,
    prescription_id VARCHAR(36),
    pharmacy_org_id VARCHAR(36) NOT NULL,
    status VARCHAR(50) DEFAULT 'PENDING', -- PENDING, PACKING, DISPATCHED, DELIVERED
    FOREIGN KEY (pharmacy_org_id) REFERENCES organizations(org_id)
) ENGINE=InnoDB;

-- 7. Communication & Engagement Engine
CREATE TABLE IF NOT EXISTS notification_events (
    notification_id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    event_type VARCHAR(100) NOT NULL, 
    channel VARCHAR(50) NOT NULL, -- PUSH, SMS, WHATSAPP
    status VARCHAR(50) DEFAULT 'QUEUED', -- QUEUED, SENT, DELIVERED, FAILED
    payload JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;
