<?php
// ============================================================
// Database Singleton — PDO connection manager
// ============================================================

namespace App\Core;

class Database
{
    private static ?Database $instance = null;
    private \PDO $pdo;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['name'],
                $config['charset']
            );
            $this->pdo = new \PDO($dsn, $config['user'], $config['pass'], $config['options']);
        } catch (\Throwable $e) {
            // Fallback to SQLite if MySQL is unavailable
            $sqliteFile = is_writable(dirname(__DIR__)) ? dirname(__DIR__) . '/ksr_ai.sqlite' : sys_get_temp_dir() . '/ksr_ai.sqlite';
            $this->pdo = new \PDO('sqlite:' . $sqliteFile, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]);
        }
    }

    public static function getInstance(?array $config = null): self
    {
        if (self::$instance === null) {
            if ($config === null) {
                $configFile = file_exists(__DIR__ . '/../app.php') ? __DIR__ . '/../app.php' : __DIR__ . '/../../config/app.php';
                $allConfig = file_exists($configFile) ? require $configFile : [];
                $config = $allConfig['database'] ?? [
                    'host' => '127.0.0.1', 'port' => 3306, 'name' => 'ksr_guru',
                    'user' => getenv('DB_USER') ?: 'root',
                    'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
                    'charset' => 'utf8mb4',
                    'options' => [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                ];
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    /** Run a SELECT query, return all rows */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Run a SELECT query, return one row */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Run a SELECT query, return single column value */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /** Run INSERT/UPDATE/DELETE, return affected rows */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Insert and return last insert ID */
    public function insert(string $sql, array $params = []): string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    /** Begin transaction */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /** Commit transaction */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /** Rollback transaction */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /** Check if a table exists */
    public function tableExists(string $table): bool
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            [$this->config['name'], $table]
        );
        return ($result['cnt'] ?? 0) > 0;
    }

    /** Full-text search helper */
    public function fullTextSearch(string $table, string $column, string $query, int $limit = 20): array
    {
        $sql = "SELECT *, MATCH({$column}) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance 
                FROM {$table} 
                WHERE MATCH({$column}) AGAINST(? IN NATURAL LANGUAGE MODE) 
                ORDER BY relevance DESC 
                LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$query, $query, $limit]);
        return $stmt->fetchAll();
    }
}
