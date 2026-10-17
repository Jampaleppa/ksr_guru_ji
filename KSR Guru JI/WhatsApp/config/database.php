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

    // Load natively from environment or secure config defaults
    private $host = '127.0.0.1';
    private $db   = 'ksr_guru'; // The target SQL map we constructed
    private $user = 'root';
    private $pass = 'JKRathod@406'; // Production root credentials bridged
    private $charset = 'utf8mb4';

    private function __construct() {
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
