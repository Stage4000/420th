<?php
// Migration script to add staff_notes table

require_once 'config.php';
require_once 'db.php';

echo "Starting migration: Add staff_notes table\n";
echo "==========================================\n\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if table already exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'staff_notes'");
    if ($tableCheck->rowCount() > 0) {
        echo "✓ staff_notes table already exists, skipping creation.\n";
    } else {
        echo "Creating staff_notes table...\n";
        
        $sql = "
        CREATE TABLE `staff_notes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `note_text` TEXT NOT NULL,
            `created_by_user_id` INT NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_by_user_id` INT NULL,
            `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`updated_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $conn->exec($sql);
        echo "✓ staff_notes table created successfully.\n";
    }
    
    echo "\n==========================================\n";
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
