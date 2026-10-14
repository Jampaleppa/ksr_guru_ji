<?php

/**
 * Doctor Assist - Enterprise PDO Database Connection & Schema Manager
 * Implements strict Multi-Tenant Architecture and Transactional Safety
 */

class Database {
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct() {
        $host = getenv('DB_HOST') ?: 'sqlXXX.infinityfree.com';
        $db   = getenv('DOCTOR_ASSIST_DB') ?: (getenv('DB_NAME') ?: 'if0_43170510_doctor_assist');
        $user = getenv('DB_USER') ?: 'if0_43170510';
        $pass = getenv('DB_PASS') ?: 'YOUR_INFINITYFREE_DB_PASSWORD';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Strict failures
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Enforce real prepared statements for security
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            
            // If DB doesn't exist, connect without DB name and try creation
            if ($e->getCode() == 1049) {
                try {
                    $dsn_no_db = "mysql:host=$host;charset=$charset";
                    $temp_pdo = new PDO($dsn_no_db, $user, $pass, $options);
                    $temp_pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $this->pdo = new PDO($dsn, $user, $pass, $options);
                    $this->initializeSchema();
                } catch (\PDOException $e2) {
                    throw new \PDOException($e2->getMessage(), (int)$e2->getCode());
                }
            } else {
                throw new \PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }

    /**
     * Initializes the Core Enterprise Schema based on the Domain Driven Design State Engines
     */
    private function initializeSchema() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS organizations (
                org_id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                status VARCHAR(50) DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;",

            "CREATE TABLE IF NOT EXISTS users (
                user_id VARCHAR(36) PRIMARY KEY,
                identity_hash VARCHAR(255) UNIQUE NOT NULL,
                role ENUM('PATIENT', 'DOCTOR', 'PHARMACIST', 'DELIVERY', 'ADMIN') NOT NULL,
                status VARCHAR(50) DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;",
            
            "CREATE TABLE IF NOT EXISTS user_profiles (
                user_id VARCHAR(36) PRIMARY KEY,
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                phone_encrypted VARCHAR(255),
                email_encrypted VARCHAR(255),
                FOREIGN KEY (user_id) REFERENCES users(user_id)
            ) ENGINE=InnoDB;",

            "CREATE TABLE IF NOT EXISTS clinical_encounters (
                encounter_id VARCHAR(36) PRIMARY KEY,
                patient_id VARCHAR(36) NOT NULL,
                doctor_id VARCHAR(36) NOT NULL,
                org_id VARCHAR(36) NOT NULL,
                status VARCHAR(50) DEFAULT 'SCHEDULED', -- e.g., SCHEDULED, ACTIVE, COMPLETED, CANCELLED
                start_time DATETIME,
                end_time DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES users(user_id),
                FOREIGN KEY (org_id) REFERENCES organizations(org_id)
            ) ENGINE=InnoDB;",

            "CREATE TABLE IF NOT EXISTS clinical_timeline_events (
                event_id VARCHAR(36) PRIMARY KEY,
                patient_id VARCHAR(36) NOT NULL,
                domain_source VARCHAR(50) NOT NULL, -- e.g., PHARMACY, DIAGNOSTIC, CONSULTATION
                event_type VARCHAR(100) NOT NULL, 
                event_payload JSON NOT NULL, -- Immutable pointer payload
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES users(user_id)
            ) ENGINE=InnoDB;",
            
            "CREATE TABLE IF NOT EXISTS master_configurations (
                config_id VARCHAR(36) PRIMARY KEY,
                config_key VARCHAR(100) UNIQUE NOT NULL,
                config_value JSON NOT NULL,
                version INT DEFAULT 1,
                status VARCHAR(50) DEFAULT 'ACTIVE',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;"
        ];

        foreach ($queries as $sql) {
            $this->pdo->exec($sql);
        }
    }
}

// Global helper function to abstract the singleton call
function getDB() {
    return Database::getInstance();
}
?>
