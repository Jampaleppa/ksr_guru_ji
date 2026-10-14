<?php
// ============================================================
// includes/db.php - Central Database Connection
// ============================================================

// --- Load configuration (if not already loaded) ---
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'sqlXXX.infinityfree.com');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'if0_43170510_ksr_guru');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'if0_43170510');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: 'YOUR_INFINITYFREE_DB_PASSWORD');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// --- Database class using Singleton pattern ---
class Database {
    /**
     * @var Database|null
     */
    private static ?Database $instance = null;

    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * @var string|null
     */
    private ?string $error = null;

    // Private constructor to prevent direct instantiation
    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . (defined('DB_PORT') ? ';port=' . DB_PORT : '') . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            // Log error (you can replace with a logger)
            error_log('Database connection failed: ' . $this->error);
            // Throw exception to stop execution
            throw new Exception('Database connection failed: ' . $this->error);
        }
    }

    // Get singleton instance
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Get PDO connection
    public function getConnection(): PDO {
        return $this->pdo;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {
        throw new Exception('Cannot unserialize a singleton.');
    }
}

// --- Helper function to get PDO connection ---
if (!function_exists('getDBConnection')) {
    function getDBConnection(): PDO {
        return Database::getInstance()->getConnection();
    }
}

// --- Optional: close connection (though PDO handles it automatically) ---
if (!function_exists('closeDBConnection')) {
    function closeDBConnection(): void {
        // PDO doesn't have a close method, but you can nullify the instance if needed
        // Database::$instance = null;
    }
}