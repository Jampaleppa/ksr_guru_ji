<?php
namespace Config;

use PDO;
use PDOException;

/**
 * Enterprise KSR GURU JI MySQL Database Singleton
 * Implements strict connection pooling, PDO prepared statement enforcement,
 * and robust error exception handling mapped to the v1.0 architecture.
 */
class Database {

    private static ?Database $instance = null;
    private ?PDO $connection = null;

    // Load from environment first; fall back to InfinityFree placeholders.
    // Never hardcode live credentials in source control.
    private $host;
    private $db;
    private $user;
    private $pass;
    private $charset = 'utf8mb4';

    private function loadCredentials(): void {
        $this->host = getenv('DB_HOST') ?: 'sqlXXX.infinityfree.com';
        $this->db   = getenv('DB_NAME') ?: 'if0_43170510_ksr_guru';
        $this->user = getenv('DB_USER') ?: 'if0_43170510';
        $this->pass = getenv('DB_PASS') ?: 'YOUR_INFINITYFREE_DB_PASSWORD';
    }

    private function __construct() {
        $this->loadCredentials();
        $dsn = "mysql:host={$this->host};dbname={$this->db};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw strict errors for instant debugging
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Enforce associative arrays (JSON compatible)
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Purely hardware-level secure prepared statements
            PDO::ATTR_PERSISTENT         => true                    // Enable connection pooling for high-throughput messaging
        ];

        try {
            $this->connection = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            // In a production environment, never output the direct SQL error to the UI
            // Log to `admin_logs` realistically.
            error_log("Secure DB Connection Error: " . $e->getMessage());
            die(json_encode(['status' => 'critical', 'message' => 'System Database Error. Check administrative logs.']));
        }
    }

    /**
     * Singleton Instance Generator
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Extracts the raw active PDO object
     */
    public function getConnection() {
        return $this->connection;
    }

    // Prevent physical duplication of the singleton pointer
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("Cannot unserialize a singleton.");
    }
}
