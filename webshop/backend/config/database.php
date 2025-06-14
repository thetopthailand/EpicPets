<?php
/**
 * Database Configuration และ Connection Management
 * ระบบจัดการการเชื่อมต่อฐานข้อมูลที่ปลอดภัย
 */

class Database {
    private static $instance = null;
    private $connection;
    private $host;
    private $username;
    private $password;
    private $database;
    private $charset;

    private function __construct() {
        // โหลดการตั้งค่าจากไฟล์ environment หรือ config
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
        $this->database = $_ENV['DB_NAME'] ?? 'minecraft_webshop';
        $this->charset = 'utf8mb4';

        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE utf8mb4_unicode_ci"
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            // เพิ่มความปลอดภัยเพิ่มเติม
            $this->connection->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }

    public function getConnection() {
        // ตรวจสอบการเชื่อมต่อและเชื่อมต่อใหม่หากจำเป็น
        try {
            $this->connection->query('SELECT 1');
        } catch (PDOException $e) {
            $this->connect();
        }
        
        return $this->connection;
    }

    /**
     * Execute prepared statement with parameters
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Database operation failed.");
        }
    }

    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Fetch multiple rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollback();
    }

    /**
     * Check if in transaction
     */
    public function inTransaction() {
        return $this->connection->inTransaction();
    }

    /**
     * Sanitize input for LIKE queries
     */
    public function escapeLike($string) {
        return str_replace(['%', '_'], ['\%', '\_'], $string);
    }

    /**
     * Close connection
     */
    public function close() {
        $this->connection = null;
    }

    // ป้องกัน cloning
    private function __clone() {}
    
    // ป้องกัน unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Database Migration Helper
 */
class DatabaseMigration {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Run all pending migrations
     */
    public function runMigrations() {
        $this->createMigrationsTable();
        
        $migrationFiles = glob(__DIR__ . '/../database/migrations/*.sql');
        sort($migrationFiles);

        foreach ($migrationFiles as $file) {
            $filename = basename($file);
            
            if (!$this->isMigrationRun($filename)) {
                $this->runMigration($file, $filename);
            }
        }
    }

    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->db->execute($sql);
    }

    private function isMigrationRun($filename) {
        $result = $this->db->fetchOne(
            "SELECT id FROM migrations WHERE filename = ?",
            [$filename]
        );
        
        return $result !== false;
    }

    private function runMigration($file, $filename) {
        try {
            $sql = file_get_contents($file);
            
            $this->db->beginTransaction();
            
            // Execute migration
            $this->db->getConnection()->exec($sql);
            
            // Record migration
            $this->db->execute(
                "INSERT INTO migrations (filename) VALUES (?)",
                [$filename]
            );
            
            $this->db->commit();
            
            echo "Migration {$filename} executed successfully.\n";
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Migration {$filename} failed: " . $e->getMessage());
        }
    }
}
?>

