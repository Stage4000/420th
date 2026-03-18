<?php
// Database connection handler

require_once 'config.php';

class Database {
    /** @var self|null */
    private static $instance = null;
    private PDO $connection;

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

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }

    /**
     * @param array<int, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @param array<int, mixed> $params
     */
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): string {
        return (string) $this->connection->lastInsertId();
    }

    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool {
        return $this->connection->commit();
    }

    public function rollback(): bool {
        return $this->connection->rollback();
    }

    /**
     * Check if a PDOException is due to a missing table (SQLSTATE 42S02)
     * @param PDOException $e The exception to check
     * @return bool True if the error is due to a missing table
     */
    public static function isTableNotFoundError(PDOException $e): bool {
        return $e->getCode() === '42S02' || strpos($e->getMessage(), '42S02') !== false;
    }

    /**
     * Create the server_settings table if it doesn't exist
     * This is called automatically when the table is detected as missing
     */
    public function createServerSettingsTable(): void {
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
