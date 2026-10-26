<?php
// ============================================================
// includes/db.php - Central Database Connection
// ============================================================

// --- Load configuration (if not already loaded) ---
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'ksr_guru');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
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
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
        }

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log('Database connection failed: ' . $this->error);
            // MySQL unavailable (Vercel serverless / local dev without MySQL).
            // Fall back to persistent SQLite on disk so orders and payments persist across requests!
            $sqliteDir = is_writable(__DIR__) ? __DIR__ : sys_get_temp_dir();
            $sqliteFile = $sqliteDir . '/ksr_local_database.sqlite';
            $this->pdo = new PDO('sqlite:' . $sqliteFile, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            if (function_exists('_bootstrapSQLiteSchema')) {
                _bootstrapSQLiteSchema($this->pdo);
            } elseif (is_file(__DIR__ . '/includes/db.php')) {
                require_once __DIR__ . '/includes/db.php';
                _bootstrapSQLiteSchema($this->pdo);
            }
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