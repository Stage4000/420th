<?php
// Database connection handler

require_once 'config.php';

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollback() {
        return $this->connection->rollback();
    }

    /**
     * Check if a PDOException is due to a missing table (SQLSTATE 42S02)
     * @param PDOException $e The exception to check
     * @return bool True if the error is due to a missing table
     */
    public static function isTableNotFoundError($e) {
        return $e->getCode() === '42S02' || strpos($e->getMessage(), '42S02') !== false;
    }

    /**
     * Create the server_settings table if it doesn't exist
     * This is called automatically when the table is detected as missing
     */
    public function createServerSettingsTable() {
        // Create server_settings table
        $this->query("
            CREATE TABLE IF NOT EXISTS `server_settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `setting_key` VARCHAR(100) UNIQUE NOT NULL,
                `setting_value` TEXT NULL,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `updated_by_user_id` INT NULL,
                INDEX `idx_setting_key` (`setting_key`),
                FOREIGN KEY (`updated_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insert default settings
        $defaultSettings = [
            ['rcon_enabled', '0'],
            ['rcon_host', ''],
            ['rcon_port', '2306'],
            ['rcon_password', ''],
            ['whitelist_agreement', DEFAULT_WHITELIST_AGREEMENT]
        ];
        
        foreach ($defaultSettings as $setting) {
            $this->query(
                "INSERT INTO server_settings (setting_key, setting_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE setting_key = setting_key",
                $setting
            );
        }
    }
}
